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
    
    // Get donor_id from POST data first, then fallback to session
    $donor_id = isset($_POST['donor_id']) ? $_POST['donor_id'] : (isset($_SESSION['donor_id']) ? $_SESSION['donor_id'] : null);
    
    if (!$donor_id) {
        error_log("Missing donor_id - POST data: " . print_r($_POST, true));
        error_log("Session data: " . print_r($_SESSION, true));
        throw new Exception("Missing donor_id");
    }
    
    // Get user's name from session
    $user_id = $_SESSION['user_id'];
    
    // Fetch user's name from the users table using unified API function
    $interviewer_name = '';
    if (function_exists('makeSupabaseApiCall')) {
        $user_data = makeSupabaseApiCall(
            'users',
            ['first_name', 'surname'],
            ['user_id' => 'eq.' . $user_id]
        );
        
        if (!empty($user_data) && is_array($user_data)) {
            $user = $user_data[0];
            // Format name as "First Name Surname - Staff"
            $interviewer_name = trim($user['first_name'] . ' ' . $user['surname']) . ' - Staff';
        }
    } else {
        // Fallback to direct CURL if unified function not available
        $ch = curl_init(SUPABASE_URL . '/rest/v1/users?user_id=eq.' . $user_id . '&select=first_name,surname');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $user_data = json_decode($response, true);
            if (!empty($user_data)) {
                $user = $user_data[0];
                // Format name as "First Name Surname - Staff"
                $interviewer_name = trim($user['first_name'] . ' ' . $user['surname']) . ' - Staff';
            }
        }
    }

    // Initialize the update data array
    $medical_history_data = [
        'donor_id' => $donor_id,
        'interviewer' => $interviewer_name
    ];

    // Check which button was clicked and set the approval status
    $action = isset($_POST['action']) ? $_POST['action'] : null;
    
    // Debug: Log the action value
    error_log("Action received: " . ($action ? $action : 'NULL'));
    error_log("All POST keys: " . implode(', ', array_keys($_POST)));
    
    if ($action === 'approve') {
        $medical_history_data['medical_approval'] = 'Approved';
        $medical_history_data['needs_review'] = false;
        error_log("Processing approve action");
    } elseif ($action === 'decline') {
        $medical_history_data['medical_approval'] = 'Not Approved';
        $medical_history_data['needs_review'] = false;
        error_log("Processing decline action");
    } elseif ($action === 'next') {
        // For 'next' action, just save the data without setting approval status
        // This allows for draft saving
        error_log("Processing next action");
    } else {
        error_log("Invalid action specified: " . ($action ? $action : 'NULL'));
        throw new Exception("Invalid action specified: " . ($action ? $action : 'NULL'));
    }

    // Include shared utility functions
    require_once '../../../assets/php_func/medical_history_utils.php';

    // Process all question responses
    for ($i = 1; $i <= 37; $i++) {
        if (isset($_POST["q$i"])) {
            $fieldName = getMedicalHistoryFieldName($i);
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
        // Reset screening, PE, and blood collection when MH is updated for existing donor
        // This preserves approved historical data but resets current cycle records
        require_once '../../../assets/php_func/reset_donor_workflow_on_mh_update.php';
        $reset_results = resetDonorWorkflowOnMHUpdate($donor_id);
        
        error_log("Workflow reset results for donor_id $donor_id: " . json_encode($reset_results));
        
        // Store the screening data in session for declaration form
        $_SESSION['transferred_screening_data'] = $screening_data_for_session;
        $_SESSION['medical_history_processed'] = true;
        
        $reset_message = '';
        if ($reset_results['screening_reset'] || $reset_results['physical_exam_reset'] || $reset_results['blood_collection_reset']) {
            $reset_message = ' Workflow records reset.';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Medical history processed successfully' . $reset_message,
            'action' => $action,
            'reset_results' => $reset_results
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