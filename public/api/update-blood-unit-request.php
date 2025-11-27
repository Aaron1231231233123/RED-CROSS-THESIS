<?php
// API to update blood unit with hospital request ID
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../Dashboards/module/optimized_functions.php';
require_once '../../assets/php_func/buffer_blood_manager.php';

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

if (!$input || !isset($input['unit_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unit ID is required']);
    exit;
}

$unit_id = $input['unit_id'];
$request_id = isset($input['request_id']) ? intval($input['request_id']) : null;

try {
    // Get request data to get hospital_admitted if request_id is provided
    $hospital_from = null;
    if ($request_id) {
        $requestResponse = supabaseRequest("blood_requests?request_id=eq." . $request_id);
        if (isset($requestResponse['data']) && !empty($requestResponse['data'])) {
            $request_data = $requestResponse['data'][0];
            $hospital_from = $request_data['hospital_admitted'] ?? null;
        }
    }
    
    $bufferContext = getBufferBloodContext();
    $unitResponse = supabaseRequest("blood_bank_units?select=unit_id,unit_serial_number,blood_type&unit_id=eq." . $unit_id);
    $unitData = isset($unitResponse['data'][0]) ? $unitResponse['data'][0] : null;
    $isBufferUnit = $unitData ? isBufferUnitFromLookup($unitData, $bufferContext['buffer_lookup']) : false;
    
    // Update the blood unit to mark as handed over and assign to request
    $update_data = [
        'status' => 'handed_over',
        'handed_over_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'is_check' => false
    ];
    
    // Add request_id and hospital_from if provided
    // Note: request_id is int4, hospital_from is text
    if ($request_id) {
        // Use request_id (int4) - existing column in blood_bank_units
        $update_data['request_id'] = intval($request_id);
    }
    
    if ($hospital_from) {
        // Use hospital_from (text) - existing column in blood_bank_units
        $update_data['hospital_from'] = strval($hospital_from);
    }
    
    $response = supabaseRequest(
        "blood_bank_units?unit_id=eq." . $unit_id,
        'PATCH',
        $update_data
    );
    
    if ($response['code'] >= 200 && $response['code'] < 300) {
        if ($isBufferUnit && $unitData) {
            logBufferUsageEvent([[
                'unit_id' => $unit_id,
                'serial_number' => $unitData['unit_serial_number'] ?? '',
                'blood_type' => $unitData['blood_type'] ?? ''
            ]], $request_id, $_SESSION['user_id'] ?? null, 'handover');
        }
        echo json_encode(['success' => true, 'message' => 'Blood unit updated successfully', 'updated' => $response['data']]);
    } else {
        $errorMsg = 'Failed to update blood unit. HTTP Code: ' . $response['code'];
        if (isset($response['error'])) {
            $errorMsg .= '. Error: ' . (is_string($response['error']) ? $response['error'] : json_encode($response['error']));
        }
        if (isset($response['message'])) {
            $errorMsg .= '. Message: ' . (is_string($response['message']) ? $response['message'] : json_encode($response['message']));
        }
        throw new Exception($errorMsg);
    }
    
} catch (Exception $e) {
    error_log("Error updating blood unit: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
