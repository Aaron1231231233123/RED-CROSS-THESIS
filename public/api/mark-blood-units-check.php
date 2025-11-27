<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../Dashboards/module/optimized_functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['unit_ids']) || !is_array($input['unit_ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'unit_ids array is required']);
    exit;
}

$targetState = isset($input['is_check']) ? (bool)$input['is_check'] : true;
$unitIds = array_values(array_filter(array_map(function ($value) {
    if (is_string($value) || is_numeric($value)) {
        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }
    return null;
}, $input['unit_ids'])));

if (empty($unitIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid unit identifiers provided']);
    exit;
}

$timestamp = date('Y-m-d H:i:s');

try {
    foreach ($unitIds as $unitId) {
        $response = supabaseRequest(
            "blood_bank_units?unit_id=eq." . rawurlencode($unitId),
            'PATCH',
            [
                'is_check' => $targetState,
                'updated_at' => $timestamp
            ]
        );

        if (!isset($response['code']) || $response['code'] < 200 || $response['code'] >= 300) {
            $detail = isset($response['error']) ? json_encode($response['error']) : 'Unknown error';
            throw new Exception("Failed to update unit {$unitId}: {$detail}");
        }
    }

    echo json_encode(['success' => true, 'unit_ids' => $unitIds, 'state' => $targetState]);
} catch (Exception $e) {
    error_log("Mark blood units check failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

