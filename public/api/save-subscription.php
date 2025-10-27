<?php
/**
 * Save Push Subscription API
 * Endpoint: POST /public/api/save-subscription.php
 * Saves donor's push subscription for Web Push notifications
 */

session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/vapid_config.php';

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
    $required_fields = ['donor_id', 'endpoint', 'p256dh', 'auth'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $donor_id = intval($input['donor_id']);
    $endpoint = $input['endpoint'];
    $p256dh = $input['p256dh'];
    $auth = $input['auth'];
    $expires_at = isset($input['expires_at']) ? $input['expires_at'] : null;
    
    // Validate donor exists
    $donor_check = supabaseRequest("donor_form?select=donor_id,surname,first_name&donor_id=eq.$donor_id");
    if (!isset($donor_check['data']) || empty($donor_check['data'])) {
        throw new Exception('Donor not found');
    }
    
    // Prepare subscription data
    $subscription_data = [
        'donor_id' => $donor_id,
        'endpoint' => $endpoint,
        'p256dh' => $p256dh,
        'auth' => $auth,
        'expires_at' => $expires_at,
        'updated_at' => date('c')
    ];
    
    // Check if subscription already exists for this donor and endpoint
    $existing = supabaseRequest("push_subscriptions?select=id&donor_id=eq.$donor_id&endpoint=eq." . urlencode($endpoint));
    
    if (isset($existing['data']) && !empty($existing['data'])) {
        // Update existing subscription
        $subscription_id = $existing['data'][0]['id'];
        $response = supabaseRequest("push_subscriptions?id=eq.$subscription_id", "PATCH", $subscription_data);
        
        if (isset($response['data'])) {
            $message = 'Subscription updated successfully';
        } else {
            throw new Exception('Failed to update subscription');
        }
    } else {
        // Insert new subscription
        $response = supabaseRequest("push_subscriptions", "POST", $subscription_data);
        
        if (isset($response['data']) && !empty($response['data'])) {
            $message = 'Subscription saved successfully';
        } else {
            throw new Exception('Failed to save subscription: ' . json_encode($response));
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'vapid_public_key' => getVapidPublicKey()
    ]);
    
} catch (Exception $e) {
    error_log("Save subscription error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
