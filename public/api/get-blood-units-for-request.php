<?php
// API to get blood units for a specific request
session_start();
require_once '../../assets/conn/db_conn.php';

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
    
    // Convert to database format
    $db_compatible_types = [];
    foreach ($compatible_types as $type) {
        $blood_type = substr($type, 0, -1);
        $rh_factor = substr($type, -1) === '+' ? 'Positive' : 'Negative';
        $db_compatible_types[] = $blood_type . '|' . $rh_factor;
    }
    
    // Build the query to get available blood units
    $blood_type_conditions = [];
    foreach ($db_compatible_types as $type) {
        list($bt, $rh) = explode('|', $type);
        $blood_type_conditions[] = "(blood_type.eq.{$bt}&rh_factor.eq.{$rh})";
    }
    
    $blood_type_filter = implode(',', $blood_type_conditions);
    $endpoint = "blood_bank_units?select=unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,hospital_request_id&or=({$blood_type_filter})&status=eq.Valid&hospital_request_id=is.null&order=collected_at.asc&limit=" . $needed_units;
    
    $response = supabaseRequest($endpoint);
    if (isset($response['data'])) {
        $blood_units = $response['data'];
        
        // Format the response
        $formatted_units = [];
        foreach ($blood_units as $unit) {
            $formatted_units[] = [
                'unit_id' => $unit['unit_id'],
                'serial_number' => $unit['unit_serial_number'],
                'blood_type' => $unit['blood_type'],
                'bag_type' => $unit['bag_type'] ?: 'Standard',
                'bag_brand' => $unit['bag_brand'] ?: 'N/A',
                'expiration_date' => date('Y-m-d', strtotime($unit['expires_at'])),
                'status' => $unit['status']
            ];
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
            'total_units' => count($formatted_units)
        ]);
    } else {
        throw new Exception('Failed to fetch blood units');
    }
    
} catch (Exception $e) {
    error_log("Error fetching blood units for request: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
