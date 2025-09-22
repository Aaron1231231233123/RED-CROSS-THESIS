<?php
// API to update blood unit with hospital request ID
session_start();
require_once '../../assets/conn/db_conn.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['unit_id']) || !isset($input['hospital_request_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unit ID and Hospital Request ID are required']);
    exit;
}

$unit_id = $input['unit_id'];
$hospital_request_id = intval($input['hospital_request_id']);

try {
    // Update the blood unit with the hospital request ID
    $update_data = [
        'hospital_request_id' => $hospital_request_id,
        'status' => 'handed_over',
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $response = supabaseRequest(
        "blood_bank_units?unit_id=eq." . $unit_id,
        'PATCH',
        $update_data
    );
    
    if ($response['code'] >= 200 && $response['code'] < 300) {
        echo json_encode(['success' => true, 'message' => 'Blood unit updated successfully']);
    } else {
        throw new Exception('Failed to update blood unit. HTTP Code: ' . $response['code']);
    }
    
} catch (Exception $e) {
    error_log("Error updating blood unit: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
