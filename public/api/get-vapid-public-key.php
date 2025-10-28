<?php
/**
 * Get VAPID Public Key API
 * Endpoint: GET /public/api/get-vapid-public-key.php
 * Returns the VAPID public key for client-side push subscription
 */

require_once '../../assets/php_func/vapid_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    echo json_encode([
        'success' => true,
        'vapid_public_key' => getVapidPublicKey()
    ]);
    
} catch (Exception $e) {
    error_log("Get VAPID public key error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get VAPID public key'
    ]);
}
?>



