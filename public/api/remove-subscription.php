<?php
/**
 * Remove Push Subscription API
 * Endpoint: POST /public/api/remove-subscription.php
 * Removes donor's push subscription
 */

session_start();
require_once '../../assets/conn/db_conn.php';

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
    if (!isset($input['donor_id']) || empty($input['donor_id'])) {
        throw new Exception('Missing required field: donor_id');
    }
    
    $donor_id = intval($input['donor_id']);
    
    // Remove all subscriptions for this donor
    $response = supabaseRequest("push_subscriptions?donor_id=eq.$donor_id", "DELETE");
    
    if (isset($response['data']) || (isset($response['message']) && strpos($response['message'], 'success') !== false)) {
        echo json_encode([
            'success' => true,
            'message' => 'Subscription removed successfully'
        ]);
    } else {
        throw new Exception('Failed to remove subscription');
    }
    
} catch (Exception $e) {
    error_log("Remove subscription error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>



