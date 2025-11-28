    <?php
/**
 * Broadcast Blood Drive Notifications API
 * Endpoint: POST /public/api/broadcast-blood-drive.php
 * Sends push notifications and email fallback to donors for blood drives
 */

// Start output buffering to prevent any HTML/errors from being sent before JSON
ob_start();

// Disable error display but keep error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Increase execution time and memory for large donor lists
set_time_limit(300); // 5 minutes max
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

// Set JSON headers early
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();

// Try to load required files with error handling
try {
    require_once '../../assets/conn/db_conn.php';
    require_once '../../assets/php_func/vapid_config.php';
    require_once '../../assets/php_func/web_push_sender.php';
    require_once '../../assets/php_func/email_sender.php';
    require_once '../Dashboards/module/optimized_functions.php';
} catch (Exception $e) {
    // Clear any output and send JSON error
    ob_clean();
    http_response_code(500);
    $errorResponse = json_encode([
        'success' => false,
        'message' => 'Failed to load required files: ' . $e->getMessage()
    ]);
    echo $errorResponse ?: '{"success":false,"message":"Failed to load required files"}';
    ob_end_flush();
    exit();
}

// Clear any output that might have been generated during file includes
ob_clean();

$GLOBALS['notificationLogQueue'] = [];

/**
 * Queue notification log entries for batch insertion
 */
function logNotification($bloodDriveId, $donorId, $type, $status, $reason = null, $recipient = null, $payload = null, $error = null) {
    $logData = [
        'blood_drive_id' => $bloodDriveId,
        'donor_id' => $donorId,
        'notification_type' => $type,
        'status' => $status,
        'reason' => $reason,
        'recipient' => $recipient,
        'error_message' => $error,
        'created_at' => date('c')
    ];
    
    if ($payload !== null) {
        $logData['payload_json'] = json_encode($payload);
    }
    
    $GLOBALS['notificationLogQueue'][] = $logData;
}

/**
 * Flush queued notification logs to Supabase in batches
 */
function flushNotificationLogs() {
    if (empty($GLOBALS['notificationLogQueue'])) {
        return;
    }
    
    $chunks = array_chunk($GLOBALS['notificationLogQueue'], 200);
    foreach ($chunks as $chunk) {
        @supabaseRequest("notification_logs", "POST", $chunk, true, 'return=minimal');
    }
    
    $GLOBALS['notificationLogQueue'] = [];
}

function formatDriveDateLabel($date) {
    if (empty($date)) {
        return '';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    return date('F j, Y', $timestamp);
}

function formatDriveTimeLabel($time) {
    if (empty($time)) {
        return '';
    }
    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return $time;
    }
    return date('g:i A', $timestamp);
}

function buildNotificationPayload($venue, $driveDate, $driveTime, $override = []) {
    $title = !empty($override['title'])
        ? $override['title']
        : ($venue ? "Blood Drive at {$venue}" : 'Blood Drive Notification');
    
    $payload = [
        'title' => $title,
        'venue' => $override['venue'] ?? $venue,
        'date' => $override['date'] ?? $driveDate,
        'time' => $override['time'] ?? $driveTime
    ];
    
    $defaultMessage = 'You have a new blood drive notification.';
    if (!empty($payload['venue']) && !empty($payload['date']) && !empty($payload['time'])) {
        $defaultMessage = sprintf(
            'Join us at %s on %s at %s.',
            $payload['venue'],
            formatDriveDateLabel($payload['date']),
            formatDriveTimeLabel($payload['time'])
        );
    }
    
    $payload['message'] = $override['message'] ?? $defaultMessage;
    
    return $payload;
}

/**
 * Create pending donor_notifications entries in batched Supabase inserts
 */
function createPendingDonorNotifications($donors, $payloadJson, $bloodDriveId) {
    if (empty($donors)) {
        return;
    }
    
    $records = [];
    foreach ($donors as $donor) {
        if (!isset($donor['donor_id'])) {
            continue;
        }
        $records[] = [
            'donor_id' => $donor['donor_id'],
            'payload_json' => $payloadJson,
            'status' => 'pending',
            'blood_drive_id' => $bloodDriveId
        ];
    }
    
    if (empty($records)) {
        return;
    }
    
    foreach (array_chunk($records, 500) as $batch) {
        $response = supabaseRequest("donor_notifications", "POST", $batch, true, 'return=minimal');
        if (isset($response['error'])) {
            throw new Exception('Failed to create donor notifications: ' . ($response['error'] ?? 'Unknown error'));
        }
    }
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    ob_end_flush();
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required_fields = ['venue', 'drive_date', 'drive_time'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $venue = trim($input['venue'] ?? ($input['location'] ?? ''));
    $drive_date = $input['drive_date'];
    $drive_time = $input['drive_time'];
    $latitude = isset($input['latitude']) && $input['latitude'] !== ''
        ? floatval($input['latitude'])
        : 10.72020000;
    $longitude = isset($input['longitude']) && $input['longitude'] !== ''
        ? floatval($input['longitude'])
        : 122.56210000;
    $notification_payload_input = (isset($input['notification_payload']) && is_array($input['notification_payload']))
        ? $input['notification_payload']
        : [];
    $notification_payload = buildNotificationPayload($venue, $drive_date, $drive_time, $notification_payload_input);
    
    // Create blood drive record
    // Remove created_at as it has DEFAULT NOW() in the table
    $blood_drive_data = [
        'location' => $venue,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'drive_date' => $drive_date,
        'drive_time' => $drive_time,
        'radius_km' => 10,
        'status' => 'scheduled',
        'message_template' => $notification_payload['message']
    ];
    
    // Only include created_by if we have a valid user ID
    if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
        $blood_drive_data['created_by'] = intval($_SESSION['user_id']);
    }
    
    // Note: created_at is excluded as it has DEFAULT NOW() in the table schema
    
    // Try to create blood drive record
    $blood_drive_response = supabaseRequest("blood_drive_notifications", "POST", $blood_drive_data);
    
    // Check for connection/HTTP errors
    if (isset($blood_drive_response['error']) || !isset($blood_drive_response['data']) || empty($blood_drive_response['data'])) {
        $errorMsg = $blood_drive_response['error'] ?? 'Unknown error';
        $errorCode = $blood_drive_response['code'] ?? 0;
        
        // Provide more helpful error messages based on error code
        if ($errorCode == 0) {
            $errorMsg = "Unable to connect to Supabase. Please check your internet connection and Supabase service status.";
        } elseif ($errorCode == 400) {
            $errorMsg .= " - Bad request. Check that all required fields are present and data types match the table schema.";
        } elseif ($errorCode == 401 || $errorCode == 403) {
            $errorMsg .= " - Authentication/Authorization error. Check your API key permissions.";
        } elseif ($errorCode == 404) {
            $errorMsg .= " - Table not found. Ensure blood_drive_notifications table exists.";
        } elseif ($errorCode == 409) {
            $errorMsg .= " - Conflict. Record may already exist or constraint violation.";
        } elseif ($errorCode >= 500) {
            $errorMsg .= " - Server error. Supabase may be experiencing issues.";
        }
        
        error_log("Blood drive creation error: HTTP Code $errorCode - $errorMsg");
        error_log("Request URL: " . SUPABASE_URL . "/rest/v1/blood_drive_notifications");
        error_log("Request data: " . json_encode($blood_drive_data));
        error_log("Full response: " . json_encode($blood_drive_response));
        
        throw new Exception('Failed to create blood drive record: ' . $errorMsg);
    }
    
    // Check if we got valid response data
    if (!isset($blood_drive_response['data']) || empty($blood_drive_response['data'])) {
        $responseCode = $blood_drive_response['code'] ?? 'unknown';
        error_log("Blood drive creation failed: HTTP Code $responseCode");
        error_log("Response: " . json_encode($blood_drive_response));
        throw new Exception('Failed to create blood drive record. Server returned: ' . json_encode($blood_drive_response));
    }
    
    $blood_drive_id = $blood_drive_response['data'][0]['id'];
    $blood_drive_info = [
        'id' => $blood_drive_id,
        'venue' => $venue,
        'location' => $venue,
        'drive_date' => $drive_date,
        'drive_time' => $drive_time,
        'message_template' => $notification_payload['message'],
        'notification_payload' => $notification_payload
    ];
    
    // Fetch all donors for broadcast
    $eligible_donors = [];
    $limit = 2000; // Fetch more per batch for faster processing
    $offset = 0;
    
    $donors_checked = 0;
    do {
        $query = "donor_form?select=donor_id,surname,first_name,middle_name,mobile,email&limit=$limit&offset=$offset";
        
        $donors_response = supabaseRequest($query);
        
        if (!isset($donors_response['data']) || empty($donors_response['data'])) {
            break; // No more donors
        }
        
        $batch_count = count($donors_response['data']);
        $donors_checked += $batch_count;
        $eligible_donors = array_merge($eligible_donors, $donors_response['data']);
        
        $offset += $limit;
        
        // If we got fewer results than the limit, we've reached the end
        if ($batch_count < $limit) {
            break;
        }
        
    } while (true);
    
    // Create pending donor_notifications entries (single payload reuse)
    $notification_payload_json = json_encode($notification_payload);
    createPendingDonorNotifications($eligible_donors, $notification_payload_json, $blood_drive_id);
    
    // Get push subscriptions for eligible donors
    $donor_ids = array_column($eligible_donors, 'donor_id');
    $subscriptions = [];
    $donors_with_push = [];
    
    if (!empty($donor_ids)) {
        // Build query for push subscriptions - use proper Supabase array syntax
        $donor_ids_param = implode(',', $donor_ids);
        // Query with correct column names: p256dh and auth (not keys)
        $subscriptions_response = supabaseRequest("push_subscriptions?select=donor_id,endpoint,p256dh,auth&donor_id=in.(" . $donor_ids_param . ")");
        
        if (isset($subscriptions_response['data']) && !empty($subscriptions_response['data'])) {
            // Format subscriptions to match WebPushSender expected format
            $subscriptions = [];
            foreach ($subscriptions_response['data'] as $sub) {
                $subscriptions[] = [
                    'donor_id' => $sub['donor_id'],
                    'endpoint' => $sub['endpoint'],
                    'keys' => [
                        'p256dh' => $sub['p256dh'] ?? '',
                        'auth' => $sub['auth'] ?? ''
                    ]
                ];
            }
            $donors_with_push = array_unique(array_column($subscriptions, 'donor_id'));
        }
    }
    
    // Create push notification payload (short, action-driven message)
    $push_payload = [
        'title' => $notification_payload['title'],
        'body' => $notification_payload['message'],
        'icon' => '/assets/image/PRC_Logo.png',
        'badge' => '/assets/image/PRC_Logo.png',
        'data' => array_merge($notification_payload, [
            'url' => '/blood-drive-details?id=' . $blood_drive_id,
            'blood_drive_id' => $blood_drive_id,
            'type' => 'blood_drive'
        ]),
        'actions' => [
            [
                'action' => 'rsvp',
                'title' => 'RSVP',
                'icon' => '/assets/image/rsvp-icon.png'
            ],
            [
                'action' => 'dismiss',
                'title' => 'Dismiss'
            ]
        ],
        'requireInteraction' => true,
        'tag' => 'blood-drive-' . $blood_drive_id
    ];
    
    // Initialize notification results
    $results = [
        'push' => [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ],
        'email' => [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ],
        'skipped' => [
            'count' => 0,
            'reasons' => []
        ]
    ];
    
    // Track which donors have been notified (to avoid duplicates)
    $notified_donors = [];
    
    // Step 1: Send push notifications to subscribed donors
    // OPTIMIZATION: Process in batches to avoid timeout and show progress
    $pushSender = new WebPushSender();
    $batch_size = 50; // Process 50 at a time
    $push_notifications_sent = 0;
    
    $subscriptions_batches = array_chunk($subscriptions, $batch_size);
    
    foreach ($subscriptions_batches as $batch) {
        foreach ($batch as $subscription) {
            $donor_id = $subscription['donor_id'];
            
            try {
                $pushPayloadJson = json_encode($push_payload);
                $result = $pushSender->sendNotification($subscription, $pushPayloadJson);
                
                if ($result['success']) {
                    $results['push']['sent']++;
                    $notified_donors[$donor_id] = 'push';
                    
                    // Log to notification_logs - non-blocking
                    @logNotification($blood_drive_id, $donor_id, 'push', 'sent', null, $subscription['endpoint'] ?? null, $notification_payload);
                    
                } else {
                    $results['push']['failed']++;
                    $results['push']['errors'][] = [
                        'donor_id' => $donor_id,
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                    
                    // Log failed push to notification_logs - non-blocking
                    @logNotification($blood_drive_id, $donor_id, 'push', 'failed', 'push_send_failed', $subscription['endpoint'] ?? null, $notification_payload, $result['error'] ?? 'Unknown error');
                }
                
            } catch (Exception $e) {
                $results['push']['failed']++;
                $results['push']['errors'][] = [
                    'donor_id' => $donor_id,
                    'error' => $e->getMessage()
                ];
                
                @logNotification($blood_drive_id, $donor_id, 'push', 'failed', 'exception', $subscription['endpoint'] ?? null, $notification_payload, $e->getMessage());
                error_log("Push notification error for donor $donor_id: " . $e->getMessage());
            }
        }
        
        // Small delay between batches to prevent overwhelming the server
        usleep(50000); // 0.05 second delay (reduced for faster processing)
    }
    
    // Step 2: Send email notifications to donors WITHOUT push subscriptions (fallback)
    // OPTIMIZATION: Process emails in batches
    $emailSender = new EmailSender();
    $email_batch_count = 0;
    
    foreach ($eligible_donors as $donor) {
        $donor_id = $donor['donor_id'];
        
        // Skip if already notified via push
        if (isset($notified_donors[$donor_id])) {
            continue;
        }
        
        // Skip if donor has push subscription (even if push failed, don't send email to avoid spam)
        if (in_array($donor_id, $donors_with_push)) {
            $results['email']['skipped']++;
            logNotification($blood_drive_id, $donor_id, 'email', 'skipped', 'has_push_subscription');
            continue;
        }
        
        // Check if donor has email
        $donor_email = $donor['email'] ?? null;
        
        if (empty($donor_email)) {
            $results['skipped']['count']++;
            $results['skipped']['reasons']['no_email'] = ($results['skipped']['reasons']['no_email'] ?? 0) + 1;
            logNotification($blood_drive_id, $donor_id, 'email', 'skipped', 'no_email');
            continue;
        }
        
        // Send email notification
        try {
            $emailResult = $emailSender->sendEmailNotification($donor, $blood_drive_info);
            
            if ($emailResult['success']) {
                $results['email']['sent']++;
                $notified_donors[$donor_id] = 'email';
                
                @logNotification($blood_drive_id, $donor_id, 'email', 'sent', null, $donor_email, $notification_payload);
            } else {
                $results['email']['failed']++;
                $results['email']['errors'][] = [
                    'donor_id' => $donor_id,
                    'error' => $emailResult['error'] ?? 'Unknown error',
                    'reason' => $emailResult['reason'] ?? null
                ];
                @logNotification($blood_drive_id, $donor_id, 'email', 'failed', $emailResult['reason'] ?? 'email_send_failed', $donor_email, $notification_payload, $emailResult['error'] ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            $results['email']['failed']++;
            $results['email']['errors'][] = [
                'donor_id' => $donor_id,
                'error' => $e->getMessage()
            ];
            
            @logNotification($blood_drive_id, $donor_id, 'email', 'failed', 'exception', $donor_email, $notification_payload, $e->getMessage());
            error_log("Email notification error for donor $donor_id: " . $e->getMessage());
        }
        
        // Process emails in batches to avoid timeout
        $email_batch_count++;
        if ($email_batch_count % 25 == 0) {
            usleep(50000); // 0.05 second delay every 25 emails (faster processing)
        }
    }
    
    // Calculate summary statistics
    $total_donors_found = count($eligible_donors);
    $total_push_subscriptions = count($subscriptions);
    $total_push_sent = $results['push']['sent'];
    $total_email_sent = $results['email']['sent'];
    $total_failed = $results['push']['failed'] + $results['email']['failed'];
    $total_skipped = $results['email']['skipped'] + $results['skipped']['count'];
    
    // Return comprehensive results IMMEDIATELY
    // OPTIMIZATION: Return response quickly, logging happens asynchronously
    ob_clean(); // Ensure no output before JSON
    
    // Note: Notifications are sent synchronously above, but we return immediately after
    $response = json_encode([
        'success' => true,
        'message' => "Blood drive notifications processed successfully",
        'blood_drive_id' => $blood_drive_id,
        'summary' => [
            'total_donors_found' => $total_donors_found,
            'push_subscriptions' => $total_push_subscriptions,
            'push_sent' => $total_push_sent,
            'push_failed' => $results['push']['failed'],
            'email_sent' => $total_email_sent,
            'email_failed' => $results['email']['failed'],
            'email_skipped' => $results['email']['skipped'],
            'total_notified' => $total_push_sent + $total_email_sent,
            'total_failed' => $total_failed,
            'total_skipped' => $total_skipped,
            'skip_reasons' => $results['skipped']['reasons']
        ],
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
    // Check for JSON encoding errors
    if ($response === false) {
        $jsonError = json_last_error_msg();
        error_log("JSON encoding error: " . $jsonError);
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to encode response: ' . $jsonError
        ]);
        exit();
    }
    
    flushNotificationLogs();
    echo $response;
    ob_end_flush(); // End output buffering and send
    exit(); // Ensure script stops here
    
} catch (Exception $e) {
    error_log("Broadcast blood drive error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    flushNotificationLogs();
    ob_clean(); // Clear any output before JSON
    http_response_code(400);
    $errorResponse = json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    if ($errorResponse === false) {
        error_log("JSON encoding error in exception handler: " . json_last_error_msg());
        echo '{"success":false,"message":"An error occurred"}';
    } else {
        echo $errorResponse;
    }
    ob_end_flush();
    exit();
    
} catch (Error $e) {
    // Catch PHP fatal errors, parse errors, etc.
    error_log("Fatal error in broadcast-blood-drive.php: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    flushNotificationLogs();
    ob_clean();
    http_response_code(500);
    $errorResponse = json_encode([
        'success' => false,
        'message' => 'Server error occurred. Please check server logs.'
    ]);
    
    if ($errorResponse === false) {
        error_log("JSON encoding error in error handler: " . json_last_error_msg());
        echo '{"success":false,"message":"Server error occurred"}';
    } else {
        echo $errorResponse;
    }
    ob_end_flush();
    exit();
}
