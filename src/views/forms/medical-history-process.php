<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Role not set']);
    exit();
}

$role_id = (int)$_SESSION['role_id'];

if ($role_id !== 1 && $role_id !== 3) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit();
}

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    error_log("=== START OF MODAL FORM SUBMISSION ===");
    error_log("Raw POST data: " . print_r($_POST, true));
    
    // Get donor_id from POST data
    $donor_id = isset($_POST['donor_id']) ? $_POST['donor_id'] : null;
    
    if (!$donor_id) {
        throw new Exception("Missing donor_id");
    }
    
    // Initialize the update data array
    $medical_history_data = [
        'donor_id' => $donor_id
    ];

    // Check which button was clicked and set the approval status
    $action = isset($_POST['action']) ? $_POST['action'] : null;
    
    if ($action === 'approve') {
        $medical_history_data['medical_approval'] = 'Approved';
        $medical_history_data['needs_review'] = false;
    } elseif ($action === 'decline') {
        $medical_history_data['medical_approval'] = 'Declined';
        $medical_history_data['needs_review'] = false;
    } elseif ($action === 'next') {
        // For 'next' action, just save the data without setting approval status
        // This allows for draft saving
    } else {
        throw new Exception("Invalid action specified");
    }

    // Helper function to get field name based on question number
    function getFieldName($count) {
        $fields = [
            1 => 'feels_well', 2 => 'previously_refused', 3 => 'testing_purpose_only', 4 => 'understands_transmission_risk',
            5 => 'recent_alcohol_consumption', 6 => 'recent_aspirin', 7 => 'recent_medication', 8 => 'recent_donation',
            9 => 'zika_travel', 10 => 'zika_contact', 11 => 'zika_sexual_contact', 12 => 'blood_transfusion',
            13 => 'surgery_dental', 14 => 'tattoo_piercing', 15 => 'risky_sexual_contact', 16 => 'unsafe_sex',
            17 => 'hepatitis_contact', 18 => 'imprisonment', 19 => 'uk_europe_stay', 20 => 'foreign_travel',
            21 => 'drug_use', 22 => 'clotting_factor', 23 => 'positive_disease_test', 24 => 'malaria_history',
            25 => 'std_history', 26 => 'cancer_blood_disease', 27 => 'heart_disease', 28 => 'lung_disease',
            29 => 'kidney_disease', 30 => 'chicken_pox', 31 => 'chronic_illness', 32 => 'recent_fever',
            33 => 'pregnancy_history', 34 => 'last_childbirth', 35 => 'recent_miscarriage', 36 => 'breastfeeding',
            37 => 'last_menstruation'
        ];
        return $fields[$count] ?? null;
    }

    // Process all question responses
    for ($i = 1; $i <= 37; $i++) {
        if (isset($_POST["q$i"])) {
            $fieldName = getFieldName($i);
            if ($fieldName) {
                $medical_history_data[$fieldName] = $_POST["q$i"] === 'Yes';
                if (isset($_POST["q{$i}_remarks"]) && $_POST["q{$i}_remarks"] !== 'None') {
                    $medical_history_data[$fieldName . '_remarks'] = $_POST["q{$i}_remarks"];
                }
            }
        }
    }

    // NEW: Fetch screening data for session storage (not database storage)
    $screening_data_for_session = [];
    try {
        $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);

        $screening_response = curl_exec($ch);
        $screening_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($screening_http_code === 200) {
            $screening_data = json_decode($screening_response, true);
            if (is_array($screening_data) && !empty($screening_data)) {
                $latest_screening = $screening_data[0];
                
                // Store screening data in session (not in medical_history table)
                $screening_data_for_session = [
                    'body_weight' => $latest_screening['body_weight'] ?? null,
                    'specific_gravity' => $latest_screening['specific_gravity'] ?? null,
                    'blood_type' => $latest_screening['blood_type'] ?? null,
                    'donation_type' => $latest_screening['donation_type'] ?? null,
                    'mobile_location' => $latest_screening['mobile_location'] ?? null,
                    'mobile_organizer' => $latest_screening['mobile_organizer'] ?? null,
                    'patient_name' => $latest_screening['patient_name'] ?? null,
                    'hospital' => $latest_screening['hospital'] ?? null,
                    'patient_blood_type' => $latest_screening['patient_blood_type'] ?? null,
                    'component_type' => $latest_screening['component_type'] ?? null,
                    'units_needed' => $latest_screening['units_needed'] ?? null
                ];
                
                error_log("Screening data for session: " . print_r($screening_data_for_session, true));
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching screening data: " . $e->getMessage());
        // Continue without screening data
    }

    error_log("Final medical history data to be sent: " . print_r($medical_history_data, true));

    // Update the medical history record (only with existing fields)
    $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id);
    
    $jsonData = json_encode($medical_history_data);
    error_log("JSON data being sent: " . $jsonData);
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    error_log("Supabase response code: " . $http_code);
    error_log("Supabase response: " . $response);
    
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        // Store the screening data in session for declaration form
        $_SESSION['transferred_screening_data'] = $screening_data_for_session;
        $_SESSION['medical_history_processed'] = true;
        
        echo json_encode([
            'success' => true,
            'message' => 'Medical history processed successfully',
            'action' => $action
        ]);
    } else {
        throw new Exception("Failed to update medical history. HTTP code: " . $http_code . ". Response: " . $response);
    }

} catch (Exception $e) {
    error_log("Error in medical history processing: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 