<?php
/**
 * Get Donor Notifications API
 * Fetches all notifications for a logged-in donor including:
 * - Blood usage notifications (when their blood was used at a hospital)
 * - Low inventory alerts (when blood bank is low)
 * - Other system notifications
 */

session_start();
require_once '../../assets/conn/db_conn.php';

header('Content-Type: application/json; charset=UTF-8');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get donor_id from session or query parameter
$donor_id = $_SESSION['donor_id'] ?? $_GET['donor_id'] ?? null;

if (!$donor_id) {
    // Try to get donor_id from donor_form table using user_id
    $user_id = $_SESSION['user_id'];
    $query = "donor_form?user_id=eq.$user_id&select=id&limit=1";
    $response = supabaseRequest($query);
    
    if (isset($response['data']) && !empty($response['data'])) {
        $donor_id = $response['data'][0]['id'];
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Donor ID not found']);
        exit;
    }
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

try {
    $notifications = [];
    
    // 1. Get blood usage notifications (when donor's blood was used at a hospital)
    // Query blood_bank_units that are handed_over and linked to this donor
    $blood_units_query = "blood_bank_units?select=unit_id,unit_serial_number,blood_type,handed_over_at,hospital_request_id&donor_id=eq.$donor_id&status=eq.handed_over&order=handed_over_at.desc&limit=10";
    $blood_units = supabaseRequest($blood_units_query);
    
    if (isset($blood_units['data']) && is_array($blood_units['data'])) {
        foreach ($blood_units['data'] as $unit) {
            $hospital_name = 'Unknown Hospital';
            $hospital_request_id = $unit['hospital_request_id'] ?? null;
            
            // Get hospital name from blood_requests table
            if ($hospital_request_id) {
                $request_query = "blood_requests?select=hospital_admitted&request_id=eq.$hospital_request_id&limit=1";
                $request_data = supabaseRequest($request_query);
                
                if (isset($request_data['data']) && !empty($request_data['data'])) {
                    $hospital_name = $request_data['data'][0]['hospital_admitted'] ?? 'Unknown Hospital';
                }
            }
            
            $notifications[] = [
                'id' => 'blood_used_' . $unit['unit_id'],
                'type' => 'blood_used',
                'title' => '🩸 Your Blood Was Used',
                'message' => "Your blood donation (Unit: {$unit['unit_serial_number']}, Type: {$unit['blood_type']}) was successfully used at {$hospital_name}.",
                'hospital_name' => $hospital_name,
                'unit_serial' => $unit['unit_serial_number'],
                'blood_type' => $unit['blood_type'],
                'date' => $unit['handed_over_at'],
                'priority' => 'info', // Blue/info color
                'read' => false,
                'created_at' => $unit['handed_over_at']
            ];
        }
    }
    
    // 2. Get low inventory alerts from donor_notifications table
    $low_inventory_query = "donor_notifications?select=id,payload_json,sent_at,status&donor_id=eq.$donor_id&order=sent_at.desc&limit=10";
    $low_inventory_notifications = supabaseRequest($low_inventory_query);
    
    if (isset($low_inventory_notifications['data']) && is_array($low_inventory_notifications['data'])) {
        foreach ($low_inventory_notifications['data'] as $notif) {
            $payload = is_string($notif['payload_json']) ? json_decode($notif['payload_json'], true) : $notif['payload_json'];
            
            // Check if this is a low inventory notification
            if (isset($payload['data']['type']) && $payload['data']['type'] === 'low_inventory') {
                $notifications[] = [
                    'id' => 'low_inventory_' . $notif['id'],
                    'type' => 'low_inventory',
                    'title' => $payload['title'] ?? '🩸 Low Blood Inventory Alert',
                    'message' => $payload['body'] ?? 'Blood inventory is critically low. Your donation is urgently needed!',
                    'priority' => 'urgent', // Red/urgent color
                    'read' => false,
                    'created_at' => $notif['sent_at'],
                    'blood_types' => $payload['data']['low_inventory_types'] ?? []
                ];
            }
        }
    }
    
    // 3. Get low inventory notifications from low_inventory_notifications table (if exists)
    $low_inv_query = "low_inventory_notifications?select=id,blood_type,units_at_time,notification_date,status&donor_id=eq.$donor_id&status=eq.sent&order=notification_date.desc&limit=5";
    $low_inv_data = supabaseRequest($low_inv_query);
    
    if (isset($low_inv_data['data']) && is_array($low_inv_data['data'])) {
        foreach ($low_inv_data['data'] as $low_inv) {
            // Check if we already have this notification (avoid duplicates)
            $exists = false;
            foreach ($notifications as $existing) {
                if ($existing['type'] === 'low_inventory' && 
                    strpos($existing['created_at'], substr($low_inv['notification_date'], 0, 10)) !== false) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $blood_type = $low_inv['blood_type'] ?? 'ALL';
                $units = $low_inv['units_at_time'] ?? 0;
                
                $notifications[] = [
                    'id' => 'low_inv_' . $low_inv['id'],
                    'type' => 'low_inventory',
                    'title' => '🩸 Urgent: Low Blood Inventory',
                    'message' => "Blood inventory is critically low ({$blood_type}: {$units} units remaining). Your donation is urgently needed!",
                    'priority' => 'urgent',
                    'read' => false,
                    'created_at' => $low_inv['notification_date'],
                    'blood_types' => [$blood_type],
                    'units_remaining' => $units
                ];
            }
        }
    }
    
    // Sort notifications by date (newest first)
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Count unread notifications
    $unread_count = 0;
    foreach ($notifications as $notif) {
        if (!$notif['read']) {
            $unread_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count,
        'total_count' => count($notifications)
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching donor notifications: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
}
?>


