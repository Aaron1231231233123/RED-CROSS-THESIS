<?php
/**
 * Broadcast Blood Drive Notifications API
 * Endpoint: POST /public/api/broadcast-blood-drive.php
 * Sends push notifications to donors for blood drives
 */

session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/vapid_config.php';
require_once '../../assets/php_func/web_push_sender.php';
require_once '../Dashboards/module/optimized_functions.php';

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

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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
    
    // Create blood drive record
    $blood_drive_data = [
        'location' => $location,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'drive_date' => $drive_date,
        'drive_time' => $drive_time,
        'radius_km' => $radius_km,
        'blood_types' => $blood_types,
        'message_template' => $custom_message,
        'status' => 'scheduled',
        'created_by' => $_SESSION['user_id'] ?? 1,
        'created_at' => date('c')
    ];
    
    $blood_drive_response = supabaseRequest("blood_drive_notifications", "POST", $blood_drive_data);
    
    if (!isset($blood_drive_response['data']) || empty($blood_drive_response['data'])) {
        throw new Exception('Failed to create blood drive record');
    }
    
    $blood_drive_id = $blood_drive_response['data'][0]['id'];
    
    // Find eligible donors within radius using PostGIS
    // First get all donors with coordinates
    $donors_response = supabaseRequest("donor_form?select=donor_id,surname,first_name,middle_name,mobile,permanent_latitude,permanent_longitude&permanent_latitude=not.is.null&permanent_longitude=not.is.null");
    
    // Filter donors by distance (since we can't use complex PostGIS queries directly)
    $eligible_donors = [];
    if (isset($donors_response['data']) && !empty($donors_response['data'])) {
        foreach ($donors_response['data'] as $donor) {
            // Calculate distance between donor and blood drive location
            $donor_lat = floatval($donor['permanent_latitude']);
            $donor_lng = floatval($donor['permanent_longitude']);
            
            // Simple distance calculation (approximate)
            $distance = calculateDistance($latitude, $longitude, $donor_lat, $donor_lng);
            
            if ($distance <= $radius_km) {
                $eligible_donors[] = $donor;
            }
        }
    }
    
    // Get blood types for eligible donors (if blood type filtering is needed)
    if (!empty($blood_types) && !empty($eligible_donors)) {
        $donor_ids = array_column($eligible_donors, 'donor_id');
        // Note: You'll need to adjust this based on your actual blood type table name
        $blood_types_response = supabaseRequest("blood_types?select=donor_id,blood_type&donor_id=in.(" . implode(',', $donor_ids) . ")");
        
        if (isset($blood_types_response['data'])) {
            $blood_type_map = [];
            foreach ($blood_types_response['data'] as $bt) {
                $blood_type_map[$bt['donor_id']] = $bt['blood_type'];
            }
            
            // Filter donors by blood type
            $eligible_donors = array_filter($eligible_donors, function($donor) use ($blood_type_map, $blood_types) {
                $donor_blood_type = $blood_type_map[$donor['donor_id']] ?? null;
                return in_array($donor_blood_type, $blood_types);
            });
        }
    }
    
    // Get push subscriptions for eligible donors
    $donor_ids = array_column($eligible_donors, 'donor_id');
    $subscriptions_response = supabaseRequest("push_subscriptions?select=*&donor_id=in.(" . implode(',', $donor_ids) . ")");
    $subscriptions = isset($subscriptions_response['data']) ? $subscriptions_response['data'] : [];
    
    // Create notification payload
    $notification_payload = [
        'title' => '🩸 Blood Drive Alert',
        'body' => $custom_message ?: "Blood drive scheduled in $location on $drive_date at $drive_time. Your blood type is needed!",
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
    
    // Send notifications
    $pushSender = new WebPushSender();
    $results = [
        'sent' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    foreach ($subscriptions as $subscription) {
        try {
            $payload_json = json_encode($notification_payload);
            $result = $pushSender->sendNotification($subscription, $payload_json);
            
            // Log notification
            $notification_data = [
                'donor_id' => $subscription['donor_id'],
                'payload_json' => $payload_json,
                'status' => $result['success'] ? 'sent' : 'failed',
                'blood_drive_id' => $blood_drive_id,
                'error_message' => $result['success'] ? null : $result['error'],
                'created_at' => date('c')
            ];
            
            supabaseRequest("donor_notifications", "POST", $notification_data);
            
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'donor_id' => $subscription['donor_id'],
                    'error' => $result['error']
                ];
            }
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = [
                'donor_id' => $subscription['donor_id'],
                'error' => $e->getMessage()
            ];
            
            error_log("Push notification error for donor {$subscription['donor_id']}: " . $e->getMessage());
        }
    }
    
    // Return results
    echo json_encode([
        'success' => true,
        'message' => "Blood drive notifications sent successfully",
        'blood_drive_id' => $blood_drive_id,
        'results' => $results,
        'total_donors_found' => count($eligible_donors),
        'total_subscriptions' => count($subscriptions)
    ]);
    
} catch (Exception $e) {
    error_log("Broadcast blood drive error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
