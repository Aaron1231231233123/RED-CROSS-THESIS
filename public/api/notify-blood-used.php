<?php
/**
 * Notify Donor When Blood Was Used
 * This API is called by admin when blood is handed over to a hospital
 * It creates a notification for the donor about which hospital received their blood
 */

session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/web_push_sender.php';

header('Content-Type: application/json; charset=UTF-8');

// Check if user is logged in and is admin/staff
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only allow admin (role_id = 1) or staff (role_id = 3) to send notifications
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;

    $headers = [
        "Content-Type: application/json",
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Prefer: return=representation"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return json_decode($response, true);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$unit_id = $input['unit_id'] ?? null;
$hospital_request_id = $input['hospital_request_id'] ?? null;

if (!$unit_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unit ID is required']);
    exit;
}

try {
    // Get blood unit details
    $unit_query = "blood_bank_units?select=unit_id,unit_serial_number,donor_id,blood_type&unit_id=eq.$unit_id&limit=1";
    $unit_data = supabaseRequest($unit_query);
    
    if (!isset($unit_data['data']) || empty($unit_data['data'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Blood unit not found']);
        exit;
    }
    
    $unit = $unit_data['data'][0];
    $donor_id = $unit['donor_id'];
    
    // Get hospital name from blood request
    $hospital_name = 'Unknown Hospital';
    if ($hospital_request_id) {
        $request_query = "blood_requests?select=hospital_admitted&request_id=eq.$hospital_request_id&limit=1";
        $request_data = supabaseRequest($request_query);
        
        if (isset($request_data['data']) && !empty($request_data['data'])) {
            $hospital_name = $request_data['data'][0]['hospital_admitted'] ?? 'Unknown Hospital';
        }
    }
    
    // Get donor's push subscription
    $subscription_query = "push_subscriptions?select=donor_id,endpoint,p256dh,auth&donor_id=eq.$donor_id&limit=1";
    $subscription_data = supabaseRequest($subscription_query);
    
    $notification_sent = false;
    
    // Send push notification if subscription exists
    if (isset($subscription_data['data']) && !empty($subscription_data['data'])) {
        $subscription = $subscription_data['data'][0];
        
        $payload = [
            'title' => '🩸 Your Blood Was Used',
            'body' => "Your blood donation (Unit: {$unit['unit_serial_number']}) was successfully used at {$hospital_name}.",
            'icon' => '/assets/image/PRC_Logo.png',
            'badge' => '/assets/image/PRC_Logo.png',
            'data' => [
                'url' => '/donor-notifications',
                'type' => 'blood_used',
                'unit_id' => $unit_id,
                'hospital_name' => $hospital_name,
                'unit_serial' => $unit['unit_serial_number']
            ],
            'requireInteraction' => true,
            'tag' => 'blood-used-notification'
        ];
        
        $formatted_subscription = [
            'donor_id' => $subscription['donor_id'],
            'endpoint' => $subscription['endpoint'],
            'keys' => [
                'p256dh' => $subscription['p256dh'] ?? '',
                'auth' => $subscription['auth'] ?? ''
            ]
        ];
        
        try {
            $pushSender = new WebPushSender();
            $payload_json = json_encode($payload);
            $result = $pushSender->sendNotification($formatted_subscription, $payload_json);
            
            if ($result['success']) {
                $notification_sent = true;
                
                // Log to donor_notifications table
                $notification_log = [
                    'donor_id' => $donor_id,
                    'payload_json' => $payload_json,
                    'status' => 'sent',
                    'sent_at' => date('c')
                ];
                supabaseRequest('donor_notifications', 'POST', $notification_log);
            }
        } catch (Exception $e) {
            error_log('Error sending push notification: ' . $e->getMessage());
        }
    }
    
    // Always log the notification even if push wasn't sent
    $notification_log = [
        'donor_id' => $donor_id,
        'payload_json' => json_encode([
            'title' => '🩸 Your Blood Was Used',
            'body' => "Your blood donation (Unit: {$unit['unit_serial_number']}) was successfully used at {$hospital_name}.",
            'type' => 'blood_used',
            'hospital_name' => $hospital_name,
            'unit_serial' => $unit['unit_serial_number']
        ]),
        'status' => $notification_sent ? 'sent' : 'pending',
        'sent_at' => date('c')
    ];
    
    supabaseRequest('donor_notifications', 'POST', $notification_log);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification sent successfully',
        'notification_sent' => $notification_sent,
        'hospital_name' => $hospital_name
    ]);
    
} catch (Exception $e) {
    error_log('Error notifying donor: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error sending notification: ' . $e->getMessage()
    ]);
}
?>


