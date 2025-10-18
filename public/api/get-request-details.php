<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get request ID from query parameter
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request ID']);
    exit();
}

try {
    // Fetch request details
    $response = supabaseRequest("blood_requests?request_id=eq." . $request_id);
    
    if (!isset($response['data']) || empty($response['data'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
        exit();
    }
    
    $request = $response['data'][0];
    
    // Get admin name if handed over
    $handed_over_by_admin = 'Not handed over yet';
    if (!empty($request['handed_over_by'])) {
        try {
            $adminResponse = supabaseRequest("users?select=first_name,surname&user_id=eq." . $request['handed_over_by']);
            if ($adminResponse['code'] === 200 && !empty($adminResponse['data'])) {
                $admin = $adminResponse['data'][0];
                $first_name = trim($admin['first_name'] ?? '');
                $surname = trim($admin['surname'] ?? '');
                
                if (!empty($first_name) && !empty($surname)) {
                    $handed_over_by_admin = "Dr. $first_name $surname";
                } elseif (!empty($first_name)) {
                    $handed_over_by_admin = "Dr. $first_name";
                } elseif (!empty($surname)) {
                    $handed_over_by_admin = "Dr. $surname";
                }
            }
        } catch (Exception $e) {
            error_log("Error getting admin name: " . $e->getMessage());
        }
    }
    
    // Return formatted data
    echo json_encode([
        'success' => true,
        'data' => [
            'request_id' => $request['request_id'],
            'patient_name' => $request['patient_name'] ?? '',
            'patient_age' => $request['patient_age'] ?? '',
            'patient_gender' => $request['patient_gender'] ?? '',
            'patient_blood_type' => $request['patient_blood_type'] ?? '',
            'rh_factor' => $request['rh_factor'] ?? '',
            'units_requested' => $request['units_requested'] ?? '',
            'patient_diagnosis' => $request['patient_diagnosis'] ?? '',
            'hospital_admitted' => $request['hospital_admitted'] ?? '',
            'physician_name' => $request['physician_name'] ?? '',
            'is_asap' => $request['is_asap'] ?? false,
            'status' => $request['status'] ?? '',
            'requested_on' => $request['requested_on'] ?? '',
            'when_needed' => $request['when_needed'] ?? '',
            'handed_over_by' => $request['handed_over_by'] ?? '',
            'handed_over_date' => $request['handed_over_date'] ?? '',
            'handed_over_by_admin' => $handed_over_by_admin
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching request details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
