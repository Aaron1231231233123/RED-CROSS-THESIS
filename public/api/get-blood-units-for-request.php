<?php
// API to get blood units for a specific request
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../Dashboards/module/optimized_functions.php';
require_once '../../assets/php_func/buffer_blood_manager.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get request ID from query parameters
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if (!$request_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Request ID is required']);
    exit;
}

try {
    // First, get the request details to know what blood type is needed
    $requestResponse = supabaseRequest("blood_requests?request_id=eq." . $request_id);
    if (!isset($requestResponse['data']) || empty($requestResponse['data'])) {
        throw new Exception('Request not found');
    }
    
    $request = $requestResponse['data'][0];
    $needed_blood_type = $request['patient_blood_type'];
    $needed_rh_factor = $request['rh_factor'];
    $needed_units = intval($request['units_requested']);
    
    // Get compatible blood types
    $compatible_types = [];
    $is_positive = $needed_rh_factor === 'Positive';
    
    switch ($needed_blood_type) {
        case 'O':
            if ($is_positive) {
                $compatible_types = ['O+', 'O-'];
            } else {
                $compatible_types = ['O-'];
            }
            break;
        case 'A':
            if ($is_positive) {
                $compatible_types = ['A+', 'A-', 'O+', 'O-'];
            } else {
                $compatible_types = ['A-', 'O-'];
            }
            break;
        case 'B':
            if ($is_positive) {
                $compatible_types = ['B+', 'B-', 'O+', 'O-'];
            } else {
                $compatible_types = ['B-', 'O-'];
            }
            break;
        case 'AB':
            if ($is_positive) {
                $compatible_types = ['AB+', 'AB-', 'A+', 'A-', 'B+', 'B-', 'O+', 'O-'];
            } else {
                $compatible_types = ['AB-', 'A-', 'B-', 'O-'];
            }
            break;
    }
    
    // Build filter using blood_type values directly (e.g., A+, O-)
    // Encode each value for URL but keep commas/parens in place
    $encodedTypes = array_map(function($t) { return rawurlencode($t); }, $compatible_types);
    $inFilter = 'in.(' . implode(',', $encodedTypes) . ')';

    // Only include units collected within the last 45 days
    $threshold = gmdate('Y-m-d\TH:i:s\Z', strtotime('-45 days'));
    
    // Only include units that are NOT expired (expiration date >= today)
    $today = gmdate('Y-m-d\TH:i:s\Z');

    // Order by expiration date (nearest expiration first) - FIFO based on expiration
    // This ensures we use blood that expires soonest first, but only if not expired
    $endpoint = "blood_bank_units"
        . "?select=unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,hospital_request_id"
        . "&blood_type={$inFilter}"
        . "&status=eq.Valid&hospital_request_id=is.null"
        . "&collected_at=gte." . rawurlencode($threshold)
        . "&expires_at=gte." . rawurlencode($today)
        . "&order=expires_at.asc&limit={$needed_units}";

    $response = supabaseRequest($endpoint);
    if (isset($response['code']) && $response['code'] >= 200 && $response['code'] < 300 && isset($response['data'])) {
        $blood_units = $response['data'];
        
        $bufferContext = getBufferBloodContext();
        $bufferUnitsUsed = [];
        
        // Format the response
        $formatted_units = [];
        foreach ($blood_units as $unit) {
            $isBuffer = isBufferUnitFromLookup($unit, $bufferContext['buffer_lookup']);
            if ($isBuffer) {
                $bufferUnitsUsed[] = [
                    'unit_id' => $unit['unit_id'],
                    'serial_number' => $unit['unit_serial_number'],
                    'blood_type' => $unit['blood_type']
                ];
            }
            $formatted_units[] = [
                'unit_id' => $unit['unit_id'],
                'serial_number' => $unit['unit_serial_number'],
                'blood_type' => $unit['blood_type'],
                'bag_type' => $unit['bag_type'] ?: 'Standard',
                'bag_brand' => $unit['bag_brand'] ?: 'N/A',
                'expiration_date' => date('Y-m-d', strtotime($unit['expires_at'])),
                'status' => $unit['status'],
                'is_buffer' => $isBuffer
            ];
        }
        
        $bufferUsagePayload = [
            'used' => !empty($bufferUnitsUsed),
            'units' => $bufferUnitsUsed,
            'unit_serials' => array_column($bufferUnitsUsed, 'serial_number'),
            'message' => ''
        ];
        if ($bufferUsagePayload['used']) {
            $bufferUsagePayload['message'] = sprintf(
                'Using %d buffer unit%s (%s) to fulfill this request.',
                count($bufferUnitsUsed),
                count($bufferUnitsUsed) > 1 ? 's' : '',
                implode(', ', $bufferUsagePayload['unit_serials'])
            );
            logBufferUsageEvent($bufferUnitsUsed, $request_id, $_SESSION['user_id'] ?? null, 'preview');
        }
        
        echo json_encode([
            'success' => true,
            'request' => [
                'request_id' => $request['request_id'],
                'patient_name' => $request['patient_name'],
                'hospital_admitted' => $request['hospital_admitted'],
                'units_requested' => $needed_units,
                'blood_type_needed' => $needed_blood_type . ($is_positive ? '+' : '-')
            ],
            'blood_units' => $formatted_units,
            'total_units' => count($formatted_units),
            'buffer_usage' => $bufferUsagePayload,
            'buffer_reserve' => $bufferContext['count']
        ]);
    } else {
        $detail = isset($response['error']) ? $response['error'] : 'Unknown error';
        throw new Exception('Failed to fetch blood units: ' . $detail);
    }
    
} catch (Exception $e) {
    error_log("Error fetching blood units for request: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
