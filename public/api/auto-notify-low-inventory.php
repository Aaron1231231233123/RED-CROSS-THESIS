<?php
/**
 * Auto-Notify Low Inventory API
 * Endpoint: POST /public/api/auto-notify-low-inventory.php
 * Automatically sends notifications to donors when blood inventory drops to 25 units or below
 * 
 * Simplified Approach:
 * - Sends notifications ONLY to donors in push_subscriptions table
 * - Logs all notifications to donor_notifications table
 * - Implements rate limiting to prevent spam (once per day, configurable)
 * 
 * Integration:
 * - push_subscriptions table: Source of truth - ONLY notify donors in this table
 * - donor_notifications table: Logs all notification attempts
 * - low_inventory_notifications table: Rate limiting to prevent duplicates
 */

// Start output buffering to prevent any HTML/errors from being sent before JSON
ob_start();

// Disable error display but keep error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Increase execution time for processing multiple donors
set_time_limit(300); // 5 minutes max
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

// Set JSON headers early
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load required files: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit();
}

// Clear any output that might have been generated during file includes
ob_clean();

/**
 * Get current blood inventory counts by blood type
 * Returns array with blood type as key and count as value
 */
function getBloodInventoryByType() {
    $blood_types = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    $inventory = [];
    
    $today = date('Y-m-d');
    
    // Query blood_bank_units for valid (non-expired, non-handed-over) units
    // Count by blood_type where status != 'expired' AND status != 'handed_over' AND expires_at >= today
    foreach ($blood_types as $blood_type) {
        // Escape blood type for URL (especially for + and - characters)
        $blood_type_escaped = urlencode($blood_type);
        
        // Query valid units: status is 'Valid' or 'reserved', not 'handed_over', and not expired
        // Note: Using expires_at >= today to exclude expired units, and status filter for valid units
        // Status values: 'Valid', 'reserved', 'handed_over', 'Expired'
        $query = "blood_bank_units?select=unit_id,status&blood_type=eq.$blood_type_escaped&status=neq.handed_over&expires_at=gte.$today";
        $response = supabaseRequest($query);
        
        $count = 0;
        if (isset($response['data']) && is_array($response['data'])) {
            // Count valid and reserved units (exclude handed_over and expired)
            foreach ($response['data'] as $unit) {
                $status = $unit['status'] ?? '';
                // Count if status is 'Valid' or 'reserved' (case-insensitive check)
                if (strtolower($status) === 'valid' || strtolower($status) === 'reserved') {
                    $count++;
                }
            }
        }
        
        $inventory[$blood_type] = $count;
    }
    
    return $inventory;
}

/**
 * Check if donor was already notified for this blood type within the rate limit period
 * @param int $donor_id Donor ID
 * @param string $blood_type Blood type
 * @param int $rate_limit_days Number of days to wait before allowing another notification (default: 1 day)
 * @return bool True if already notified recently, false otherwise
 */
function wasNotifiedRecently($donor_id, $blood_type, $rate_limit_days = 1) {
    $cutoff_date = date('Y-m-d\TH:i:s\Z', strtotime("-$rate_limit_days days"));
    
    $query = "low_inventory_notifications?select=id&donor_id=eq.$donor_id&blood_type=eq.$blood_type&notification_date=gte.$cutoff_date&status=eq.sent";
    $response = supabaseRequest($query);
    
    if (isset($response['data']) && is_array($response['data']) && count($response['data']) > 0) {
        return true;
    }
    
    return false;
}

/**
 * Log notification attempt to low_inventory_notifications table
 * Note: blood_type can be 'ALL' for general low inventory alerts
 * @param int $donor_id Donor ID
 * @param string $blood_type Blood type or 'ALL' for general alerts
 * @param int $units_at_time Minimum units available
 * @param string $notification_type 'push' or 'email'
 * @param string $status 'sent', 'failed', or 'skipped'
 * @param array $low_inventory_types Optional: array of low inventory types (used if blood_type is 'ALL')
 */
function logLowInventoryNotification($donor_id, $blood_type, $units_at_time, $notification_type, $status, $low_inventory_types = null) {
    // If blood_type is 'ALL', use the first low inventory type for database constraint
    // The table has a CHECK constraint for specific blood types, so we need a valid one
    $valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    $db_blood_type = $blood_type;
    
    if ($blood_type === 'ALL' || !in_array($blood_type, $valid_blood_types)) {
        // Use first low inventory type if available, otherwise default to 'O+'
        if ($low_inventory_types && !empty($low_inventory_types) && is_array($low_inventory_types)) {
            $db_blood_type = $low_inventory_types[0]; // Use first low inventory type
        } else {
            $db_blood_type = 'O+'; // Default fallback
        }
    }
    
    $logData = [
        'donor_id' => $donor_id,
        'blood_type' => $db_blood_type, // Use valid blood type for database constraint
        'units_at_time' => $units_at_time,
        'notification_type' => $notification_type,
        'status' => $status,
        'notification_date' => date('c')
    ];
    
    // Use error suppression for non-blocking logging
    @supabaseRequest("low_inventory_notifications", "POST", $logData);
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(200);
    exit();
}

try {
    // Get JSON input (optional - can be called without parameters)
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Configuration: Threshold for low inventory (default: 25)
    $threshold = isset($input['threshold']) ? intval($input['threshold']) : 25;
    
    // Configuration: Rate limit in days (default: 1 day, configurable to 45 days)
    $rate_limit_days = isset($input['rate_limit_days']) ? intval($input['rate_limit_days']) : 1;
    
    // Validate rate limit (must be between 1 and 45 days)
    if ($rate_limit_days < 1 || $rate_limit_days > 45) {
        $rate_limit_days = 1; // Default to 1 day if invalid
    }
    
    // Step 1: Get current blood inventory by type
    $inventory = getBloodInventoryByType();
    
    // Step 2: Find blood types that are at or below threshold
    $low_inventory_types = [];
    foreach ($inventory as $blood_type => $count) {
        if ($count <= $threshold) {
            $low_inventory_types[] = $blood_type;
        }
    }
    
    // If no blood types are low, return early
    if (empty($low_inventory_types)) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'No low inventory detected. All blood types are above threshold.',
            'threshold' => $threshold,
            'inventory' => $inventory,
            'notifications_sent' => 0
        ]);
        ob_end_flush();
        exit();
    }
    
    // Step 3: Get ALL push subscriptions (simplified approach)
    // Query push_subscriptions table directly - this is the source of truth
    // We'll notify ONLY donors with push subscriptions
    $subscriptions = [];
    $donors_with_push = [];
    
    // Get all push subscriptions in batches (optimized to prevent timeout)
    $batch_size = 100;
    $offset = 0;
    $max_subscriptions = 1000; // Reduced limit to prevent timeout
    
    do {
        // Query push_subscriptions with correct column names: p256dh and auth (not keys)
        $subscriptions_query = "push_subscriptions?select=donor_id,endpoint,p256dh,auth&limit=$batch_size&offset=$offset";
        $subscriptions_response = supabaseRequest($subscriptions_query);
        
        // Check for errors in response
        if (isset($subscriptions_response['error'])) {
            error_log("Error querying push_subscriptions: " . $subscriptions_response['error']);
            error_log("Response code: " . ($subscriptions_response['code'] ?? 'unknown'));
            break;
        }
        
        // Handle response - check both 'data' key and direct array
        $response_data = null;
        if (isset($subscriptions_response['data']) && is_array($subscriptions_response['data'])) {
            $response_data = $subscriptions_response['data'];
        } elseif (is_array($subscriptions_response) && !isset($subscriptions_response['code'])) {
            // Sometimes supabaseRequest returns array directly
            $response_data = $subscriptions_response;
        }
        
        if ($response_data !== null && is_array($response_data)) {
            $batch_count = count($response_data);
            if ($batch_count == 0) {
                break; // No more subscriptions
            }
            
            // Format subscriptions to match WebPushSender expected format
            // Convert p256dh and auth to keys object
            foreach ($response_data as $sub) {
                $formatted_sub = [
                    'donor_id' => $sub['donor_id'],
                    'endpoint' => $sub['endpoint'],
                    'keys' => [
                        'p256dh' => $sub['p256dh'] ?? '',
                        'auth' => $sub['auth'] ?? ''
                    ]
                ];
                $subscriptions[] = $formatted_sub;
            }
            
            $offset += $batch_count;
            
            error_log("Fetched $batch_count push subscriptions (total: " . count($subscriptions) . ")");
            
            // Check if we've hit our limit
            if (count($subscriptions) >= $max_subscriptions) {
                error_log("Reached subscription limit ($max_subscriptions). Processing " . count($subscriptions) . " subscriptions.");
                break;
            }
        } else {
            // Log what we got for debugging
            error_log("Unexpected response format from push_subscriptions query");
            error_log("Response: " . json_encode($subscriptions_response));
            break; // No more data or unexpected format
        }
        
        // Small delay between batches
        usleep(25000); // 0.025 second delay
    } while (count($subscriptions) < $max_subscriptions);
    
    if (empty($subscriptions)) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'No push subscriptions found. No notifications sent.',
            'threshold' => $threshold,
            'low_inventory_types' => $low_inventory_types,
            'inventory' => $inventory,
            'notifications_sent' => 0
        ]);
        ob_end_flush();
        exit();
    }
    
    // Get unique donor IDs from subscriptions
    $donors_with_push = array_unique(array_column($subscriptions, 'donor_id'));
    
    // Step 4: Batch check rate limiting (optimized - check all at once instead of per donor)
    // Get all recent notifications for these donors in batches to avoid URL length issues
    $cutoff_date = date('Y-m-d\TH:i:s\Z', strtotime("-$rate_limit_days days"));
    $recently_notified_donors = [];
    
    // Process rate limiting check in batches (with error handling)
    $rate_limit_batch_size = 100;
    $donor_ids_batches = array_chunk($donors_with_push, $rate_limit_batch_size);
    
    // Limit rate limit checks to prevent timeout (max 5 batches = 500 donors)
    $max_rate_limit_batches = 5;
    $batch_count = 0;
    
    foreach ($donor_ids_batches as $batch) {
        if ($batch_count >= $max_rate_limit_batches) {
            error_log("Rate limit check: Reached max batches limit. Skipping remaining checks.");
            break;
        }
        
        $donor_ids_param = implode(',', $batch);
        $recent_notifications_query = "low_inventory_notifications?select=donor_id&donor_id=in.($donor_ids_param)&notification_date=gte.$cutoff_date&status=eq.sent";
        $recent_notifications_response = supabaseRequest($recent_notifications_query);
        
        // If rate limit check fails, continue anyway (don't block notifications)
        if (isset($recent_notifications_response['error'])) {
            error_log("Rate limit check failed for batch, continuing anyway: " . $recent_notifications_response['error']);
            continue;
        }
        
        if (isset($recent_notifications_response['data']) && is_array($recent_notifications_response['data'])) {
            $batch_notified = array_unique(array_column($recent_notifications_response['data'], 'donor_id'));
            $recently_notified_donors = array_merge($recently_notified_donors, $batch_notified);
        }
        
        $batch_count++;
        // Small delay between batches
        usleep(10000); // 0.01 second delay
    }
    
    $recently_notified_donors = array_unique($recently_notified_donors);
    
    // Get donor details only for donors we'll actually notify (optimization)
    $donor_details = [];
    $donors_to_notify = array_diff($donors_with_push, $recently_notified_donors);
    
    if (!empty($donors_to_notify)) {
        $batch_size = 100;
        $donor_ids_batches = array_chunk($donors_to_notify, $batch_size);
        
        foreach ($donor_ids_batches as $batch) {
            $donor_ids_param = implode(',', $batch);
            $donors_query = "donor_form?select=donor_id,surname,first_name,middle_name,email,mobile&donor_id=in.($donor_ids_param)";
            $donors_response = supabaseRequest($donors_query);
            
            if (isset($donors_response['data']) && is_array($donors_response['data'])) {
                foreach ($donors_response['data'] as $donor) {
                    $donor_id = $donor['donor_id'];
                    $donor_details[$donor_id] = $donor;
                }
            }
            
            // Small delay between batches
            usleep(25000); // 0.025 second delay
        }
    }
    
    // Step 5: Send notifications (with rate limiting check)
    $pushSender = new WebPushSender();
    $emailSender = new EmailSender();
    
    // Create summary message for low inventory types
    $low_types_list = implode(', ', $low_inventory_types);
    $min_units = min(array_intersect_key($inventory, array_flip($low_inventory_types)));
    
    $results = [
        'push' => ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []],
        'email' => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
        'low_inventory_types' => $low_inventory_types
    ];
    
    // Process push notifications - send ONLY to donors in push_subscriptions
    // Process in smaller batches to prevent timeout
    $notification_batch_size = 50;
    $processed_count = 0;
    $max_to_process = 500; // Limit total notifications to prevent timeout
    
    foreach ($subscriptions as $subscription) {
        // Limit total notifications to prevent timeout
        if ($processed_count >= $max_to_process) {
            error_log("Reached notification limit ($max_to_process). Stopping to prevent timeout.");
            break;
        }
        
        $donor_id = $subscription['donor_id'];
        
        // Skip if already notified recently (rate limiting - checked in batch above)
        if (in_array($donor_id, $recently_notified_donors)) {
            $results['push']['skipped']++;
            logLowInventoryNotification($donor_id, 'ALL', $min_units, 'push', 'skipped', $low_inventory_types);
            continue;
        }
        
        $processed_count++;
        
        // Get donor details if available (for personalized message)
        $donor = $donor_details[$donor_id] ?? null;
        $donorName = 'Valued Donor';
        if ($donor) {
            $donorName = trim(($donor['first_name'] ?? '') . ' ' . ($donor['middle_name'] ?? '') . ' ' . ($donor['surname'] ?? ''));
            if (empty(trim($donorName))) {
                $donorName = 'Valued Donor';
            }
        }
        
        // Create push notification payload (general low inventory alert)
        $push_payload = [
            'title' => '🩸 Low Blood Inventory Alert',
            'body' => "URGENT: Blood inventory is critically low ($low_types_list). Your donation is urgently needed!",
            'icon' => '/assets/image/PRC_Logo.png',
            'badge' => '/assets/image/PRC_Logo.png',
            'data' => [
                'url' => '/donation-request',
                'low_inventory_types' => $low_inventory_types,
                'min_units' => $min_units,
                'type' => 'low_inventory'
            ],
            'requireInteraction' => true,
            'tag' => 'low-inventory-alert'
        ];
        
        try {
            // Debug: Log subscription data (without sensitive keys)
            error_log("Processing notification for donor $donor_id");
            error_log("Endpoint: " . ($subscription['endpoint'] ?? 'MISSING'));
            error_log("Has keys: " . (isset($subscription['keys']) ? 'YES' : 'NO'));
            if (isset($subscription['keys'])) {
                error_log("Has p256dh: " . (isset($subscription['keys']['p256dh']) && !empty($subscription['keys']['p256dh']) ? 'YES (length: ' . strlen($subscription['keys']['p256dh']) . ')' : 'NO/EMPTY'));
                error_log("Has auth: " . (isset($subscription['keys']['auth']) && !empty($subscription['keys']['auth']) ? 'YES (length: ' . strlen($subscription['keys']['auth']) . ')' : 'NO/EMPTY'));
            }
            
            $payload_json = json_encode($push_payload);
            $result = $pushSender->sendNotification($subscription, $payload_json);
            
            // Debug: Log result details
            error_log("Notification result for donor $donor_id: success=" . ($result['success'] ? 'YES' : 'NO') . ", http_code=" . ($result['http_code'] ?? 'N/A') . ", error=" . ($result['error'] ?? 'NONE'));
            
            if ($result['success']) {
                $results['push']['sent']++;
                
                // Log to donor_notifications table (same as blood drive notifications)
                // Use error suppression for faster execution
                $notification_data = [
                    'donor_id' => $donor_id,
                    'payload_json' => $payload_json,
                    'status' => 'sent',
                    'sent_at' => date('c')  // Use sent_at instead of created_at
                ];
                // Note: blood_drive_id is not included for low inventory notifications
                @supabaseRequest("donor_notifications", "POST", $notification_data);
                
                // Also log to low_inventory_notifications for rate limiting
                logLowInventoryNotification($donor_id, 'ALL', $min_units, 'push', 'sent', $low_inventory_types);
            } else {
                $results['push']['failed']++;
                
                // Log detailed error information
                $error_msg = $result['error'] ?? 'Unknown error';
                $http_code = $result['http_code'] ?? 0;
                $response_body = $result['response'] ?? '';
                
                error_log("Push notification failed for donor $donor_id: HTTP $http_code - $error_msg");
                if ($response_body) {
                    error_log("Response body: " . substr($response_body, 0, 500));
                }
                
                // Store error details in results for API response
                $results['push']['errors'][] = [
                    'donor_id' => $donor_id,
                    'http_code' => $http_code,
                    'error' => $error_msg,
                    'response' => substr($response_body, 0, 200)
                ];
                
                // Log failed notification to donor_notifications with error details
                $notification_data = [
                    'donor_id' => $donor_id,
                    'payload_json' => $payload_json,
                    'status' => 'failed',
                    'sent_at' => date('c'),  // Use sent_at instead of created_at
                    'error_message' => "HTTP $http_code: $error_msg"  // Add error message
                ];
                @supabaseRequest("donor_notifications", "POST", $notification_data);
                
                // Also log to low_inventory_notifications for tracking
                logLowInventoryNotification($donor_id, 'ALL', $min_units, 'push', 'failed', $low_inventory_types);
            }
        } catch (Exception $e) {
            $results['push']['failed']++;
            
            // Log exception to donor_notifications
            $notification_data = [
                'donor_id' => $donor_id,
                'payload_json' => $payload_json ?? null,
                'status' => 'failed',
                'sent_at' => date('c')  // Use sent_at instead of created_at
            ];
            @supabaseRequest("donor_notifications", "POST", $notification_data);
            
            // Also log to low_inventory_notifications
            logLowInventoryNotification($donor_id, 'ALL', $min_units, 'push', 'failed', $low_inventory_types);
            error_log("Push notification error for donor $donor_id: " . $e->getMessage());
        }
        
        // Small delay to avoid overwhelming the server
        usleep(10000); // 0.01 second delay
        
        // Batch delay every 50 notifications
        if ($processed_count % $notification_batch_size == 0) {
            usleep(100000); // 0.1 second delay every 50 notifications
        }
    }
    
    // Step 6: Email notifications removed - ONLY send to push_subscriptions
    // The system now ONLY notifies donors in push_subscriptions table
    // No email fallback to prevent sending to wrong donors
    
    // Return results
    $total_notifications = $results['push']['sent']; // Only push notifications now
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Low inventory notifications processed',
        'threshold' => $threshold,
        'rate_limit_days' => $rate_limit_days,
        'inventory' => $inventory,
        'low_inventory_types' => $low_inventory_types,
        'summary' => [
            'push_sent' => $results['push']['sent'],
            'push_failed' => $results['push']['failed'],
            'push_skipped' => $results['push']['skipped'],
            'email_sent' => $results['email']['sent'],
            'email_failed' => $results['email']['failed'],
            'email_skipped' => $results['email']['skipped'],
            'total_notified' => $total_notifications
        ],
        'results' => $results,
        'errors' => $results['push']['errors'] ?? []  // Include detailed error information
    ], JSON_PRETTY_PRINT);
    ob_end_flush();
    exit();
    
} catch (Exception $e) {
    error_log("Auto-notify low inventory error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    ob_end_flush();
    exit();
    
} catch (Error $e) {
    error_log("Fatal error in auto-notify-low-inventory.php: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred. Please check server logs.'
    ]);
    ob_end_flush();
    exit();
}
?>

