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

/**
 * Calculate distance between two points using Haversine formula
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

/**
 * Log notification attempt to notification_logs table
 * OPTIMIZATION: Non-blocking - use error suppression for faster execution
 */
function logNotification($bloodDriveId, $donorId, $type, $status, $reason = null, $recipient = null, $payload = null, $error = null) {
    $logData = [
        'blood_drive_id' => $bloodDriveId,
        'donor_id' => $donorId,
        'notification_type' => $type, // 'push', 'email', 'sms'
        'status' => $status, // 'sent', 'failed', 'skipped'
        'reason' => $reason,
        'recipient' => $recipient,
        'error_message' => $error,
        'created_at' => date('c')
    ];
    
    if ($payload !== null) {
        $logData['payload_json'] = json_encode($payload);
    }
    
    // Try to log to notification_logs table - non-blocking with error suppression
    // This won't slow down the main notification flow
    @supabaseRequest("notification_logs", "POST", $logData);
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
    $required_fields = ['location', 'drive_date', 'drive_time', 'latitude', 'longitude'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $location = $input['location'];
    $drive_date = $input['drive_date'];
    $drive_time = $input['drive_time'];
    $latitude = floatval($input['latitude']);
    $longitude = floatval($input['longitude']);
    $radius_km = isset($input['radius_km']) ? intval($input['radius_km']) : 10;
    $blood_types = isset($input['blood_types']) ? $input['blood_types'] : [];
    $custom_message = isset($input['custom_message']) ? $input['custom_message'] : '';
    
    // If blood_types is empty, it means "all blood types" - set to all possible types
    if (empty($blood_types)) {
        $blood_types = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    }
    
    // Create blood drive record
    // Remove created_at as it has DEFAULT NOW() in the table
    $blood_drive_data = [
        'location' => $location,
        'latitude' => $latitude, // DECIMAL type in database
        'longitude' => $longitude, // DECIMAL type in database
        'drive_date' => $drive_date,
        'drive_time' => $drive_time,
        'radius_km' => $radius_km,
        'blood_types' => $blood_types, // Always include blood_types array
        'status' => 'scheduled'
    ];
    
    // Only include message_template if it's not empty
    if (!empty($custom_message)) {
        $blood_drive_data['message_template'] = $custom_message;
    }
    
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
        'location' => $location,
        'drive_date' => $drive_date,
        'drive_time' => $drive_time,
        'message_template' => $custom_message
    ];
    
    // Find eligible donors within radius
    // OPTIMIZATION: Return early response, process notifications asynchronously
    // For now, we'll limit the initial donor fetch to avoid timeout
    $eligible_donors = [];
    $limit = 2000; // Fetch more per batch for faster processing
    $offset = 0;
    $max_donors_to_check = 3000; // Limit to reasonable number for performance
    
    // Fetch donors in batches and filter by distance
    $donors_checked = 0;
    do {
        $query = "donor_form?select=donor_id,surname,first_name,middle_name,mobile,email,permanent_latitude,permanent_longitude&permanent_latitude=not.is.null&permanent_longitude=not.is.null&limit=$limit&offset=$offset";
        
        $donors_response = supabaseRequest($query);
        
        if (!isset($donors_response['data']) || empty($donors_response['data'])) {
            break; // No more donors
        }
        
        $batch_count = count($donors_response['data']);
        $donors_checked += $batch_count;
        
        // Process this batch
        foreach ($donors_response['data'] as $donor) {
            // Calculate distance between donor and blood drive location
            $donor_lat = floatval($donor['permanent_latitude'] ?? 0);
            $donor_lng = floatval($donor['permanent_longitude'] ?? 0);
            
            if ($donor_lat == 0 || $donor_lng == 0) {
                continue; // Skip donors without valid coordinates
            }
            
            // Calculate distance - optimized
            $distance = calculateDistance($latitude, $longitude, $donor_lat, $donor_lng);
            
            if ($distance <= $radius_km) {
                $eligible_donors[] = $donor;
            }
        }
        
        // Check if we've hit our limit
        if ($donors_checked >= $max_donors_to_check) {
            error_log("Reached donor check limit ($max_donors_to_check). Found " . count($eligible_donors) . " eligible donors.");
            break;
        }
        
        $offset += $limit;
        
        // If we got fewer results than the limit, we've reached the end
        if ($batch_count < $limit) {
            break;
        }
        
    } while ($donors_checked < $max_donors_to_check);
    
    // Get blood types for eligible donors (if blood type filtering is needed)
    if (!empty($blood_types) && !empty($eligible_donors)) {
        $donor_ids = array_column($eligible_donors, 'donor_id');
        
        // Only query if we have donor IDs
        if (!empty($donor_ids)) {
            // Try to get blood types from screening_form (more reliable)
            $donor_ids_param = implode(',', $donor_ids);
            $screening_response = supabaseRequest("screening_form?select=donor_form_id,blood_type&donor_form_id=in.(" . $donor_ids_param . ")&blood_type=not.is.null&order=created_at.desc");
            
            if (isset($screening_response['data']) && !empty($screening_response['data'])) {
                $blood_type_map = [];
                foreach ($screening_response['data'] as $screening) {
                    $donor_form_id = $screening['donor_form_id'];
                    // Only use the most recent blood type for each donor
                    if (!isset($blood_type_map[$donor_form_id])) {
                        $blood_type_map[$donor_form_id] = $screening['blood_type'];
                    }
                }
                
                // Filter donors by blood type
                $eligible_donors = array_filter($eligible_donors, function($donor) use ($blood_type_map, $blood_types) {
                    $donor_blood_type = $blood_type_map[$donor['donor_id']] ?? null;
                    return in_array($donor_blood_type, $blood_types);
                });
            }
        }
    }
    
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
        'title' => '🩸 Blood Drive Alert',
        'body' => $custom_message ?: "Blood drive near you! Tap to confirm your slot.",
        'icon' => '/assets/image/PRC_Logo.png',
        'badge' => '/assets/image/PRC_Logo.png',
        'data' => [
            'url' => '/blood-drive-details?id=' . $blood_drive_id,
            'blood_drive_id' => $blood_drive_id,
            'location' => $location,
            'date' => $drive_date,
            'time' => $drive_time,
            'type' => 'blood_drive'
        ],
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
                $payload_json = json_encode($push_payload);
                $result = $pushSender->sendNotification($subscription, $payload_json);
                
                if ($result['success']) {
                    $results['push']['sent']++;
                    $notified_donors[$donor_id] = 'push';
                    
                    // Log to donor_notifications table (if exists) - non-blocking
                    // Use error suppression for faster execution
                    $notification_data = [
                        'donor_id' => $donor_id,
                        'payload_json' => $payload_json,
                        'status' => 'sent',
                        'blood_drive_id' => $blood_drive_id
                        // sent_at will be set automatically by database DEFAULT NOW()
                    ];
                    @supabaseRequest("donor_notifications", "POST", $notification_data);
                    
                    // Log to notification_logs - non-blocking
                    @logNotification($blood_drive_id, $donor_id, 'push', 'sent', null, $subscription['endpoint'] ?? null, $push_payload);
                    
                } else {
                    $results['push']['failed']++;
                    $results['push']['errors'][] = [
                        'donor_id' => $donor_id,
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                    
                    // Log failed push to donor_notifications table - non-blocking
                    $failed_notification_data = [
                        'donor_id' => $donor_id,
                        'payload_json' => $payload_json,
                        'status' => 'failed',
                        'blood_drive_id' => $blood_drive_id
                    ];
                    @supabaseRequest("donor_notifications", "POST", $failed_notification_data);
                    
                    // Log failed push to notification_logs - non-blocking
                    @logNotification($blood_drive_id, $donor_id, 'push', 'failed', 'push_send_failed', $subscription['endpoint'] ?? null, $push_payload, $result['error'] ?? 'Unknown error');
                }
                
            } catch (Exception $e) {
                $results['push']['failed']++;
                $results['push']['errors'][] = [
                    'donor_id' => $donor_id,
                    'error' => $e->getMessage()
                ];
                
                // Log exception to donor_notifications table - non-blocking
                $exception_notification_data = [
                    'donor_id' => $donor_id,
                    'payload_json' => $payload_json ?? json_encode($push_payload),
                    'status' => 'failed',
                    'blood_drive_id' => $blood_drive_id
                ];
                @supabaseRequest("donor_notifications", "POST", $exception_notification_data);
                
                @logNotification($blood_drive_id, $donor_id, 'push', 'failed', 'exception', $subscription['endpoint'] ?? null, $push_payload, $e->getMessage());
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
                
                // Log to donor_notifications table (if exists) - non-blocking
                $email_payload = [
                    'type' => 'blood_drive',
                    'blood_drive_id' => $blood_drive_id,
                    'location' => $location,
                    'date' => $drive_date,
                    'time' => $drive_time,
                    'message' => $custom_message ?: "Blood drive near you! Please consider donating."
                ];
                $email_notification_data = [
                    'donor_id' => $donor_id,
                    'payload_json' => json_encode($email_payload),
                    'status' => 'sent',
                    'blood_drive_id' => $blood_drive_id
                ];
                @supabaseRequest("donor_notifications", "POST", $email_notification_data);
                
                @logNotification($blood_drive_id, $donor_id, 'email', 'sent', null, $donor_email);
            } else {
                $results['email']['failed']++;
                $results['email']['errors'][] = [
                    'donor_id' => $donor_id,
                    'error' => $emailResult['error'] ?? 'Unknown error',
                    'reason' => $emailResult['reason'] ?? null
                ];
                @logNotification($blood_drive_id, $donor_id, 'email', 'failed', $emailResult['reason'] ?? 'email_send_failed', $donor_email, null, $emailResult['error'] ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            $results['email']['failed']++;
            $results['email']['errors'][] = [
                'donor_id' => $donor_id,
                'error' => $e->getMessage()
            ];
            
            @logNotification($blood_drive_id, $donor_id, 'email', 'failed', 'exception', $donor_email, null, $e->getMessage());
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
    
    echo $response;
    ob_end_flush(); // End output buffering and send
    exit(); // Ensure script stops here
    
} catch (Exception $e) {
    error_log("Broadcast blood drive error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
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
