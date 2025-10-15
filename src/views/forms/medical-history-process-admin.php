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
            // Format name as "First Name Surname"
            $interviewer_name = trim($user['first_name'] . ' ' . $user['surname']);
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
                // Format name as "First Name Surname"
                $interviewer_name = trim($user['first_name'] . ' ' . $user['surname']);
            }
        }
    }

    // Initialize the update data array
    $medical_history_data = [
        'donor_id' => $donor_id,
        'interviewer' => $interviewer_name
    ];
    
    // Check existing medical history approval status to maintain consistency
    $existing_approval_status = null;
    try {
        $checkCurl = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_approval,needs_review&donor_id=eq.' . $donor_id . '&limit=1');
        curl_setopt($checkCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($checkCurl, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $checkResponse = curl_exec($checkCurl);
        $checkHttpCode = curl_getinfo($checkCurl, CURLINFO_HTTP_CODE);
        curl_close($checkCurl);
        
        if ($checkHttpCode === 200) {
            $existingData = json_decode($checkResponse, true);
            if (!empty($existingData)) {
                $existing_approval_status = $existingData[0]['medical_approval'] ?? null;
                $existing_needs_review = $existingData[0]['needs_review'] ?? null;
                error_log("Existing medical history status - approval: " . ($existing_approval_status ?? 'null') . ", needs_review: " . ($existing_needs_review ? 'true' : 'false'));
            }
        }
    } catch (Exception $e) {
        error_log("Error checking existing medical history status: " . $e->getMessage());
    }

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
        $medical_history_data['medical_approval'] = 'Declined';
        $medical_history_data['needs_review'] = false;
        error_log("Processing decline action");
    } elseif ($action === 'admin_complete') {
        // For 'admin_complete' action, mark review as completed
        $medical_history_data['needs_review'] = false;
        $medical_history_data['is_admin'] = 'True';  // Use string 'True' for PostgreSQL boolean
        error_log("Processing admin_complete action - marking review as Completed and is_admin as True");
    } elseif ($action === 'next') {
        // For 'next' action, maintain consistency with existing approval status
        if ($existing_approval_status === 'Approved') {
            // If already approved, ensure needs_review is false for consistency
            $medical_history_data['needs_review'] = false;
            error_log("Processing next action - maintaining approved status with needs_review = false");
        } elseif ($existing_approval_status === 'Declined') {
            // If already declined, ensure needs_review is false for consistency
            $medical_history_data['needs_review'] = false;
            error_log("Processing next action - maintaining declined status with needs_review = false");
        } else {
            // For new or pending records, don't change approval status
            error_log("Processing next action - draft saving without approval status change");
        }
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

    // Final consistency check: ensure needs_review matches approval status
    if (isset($medical_history_data['medical_approval'])) {
        if ($medical_history_data['medical_approval'] === 'Approved' || $medical_history_data['medical_approval'] === 'Declined') {
            // If approved or declined, needs_review should always be false
            $medical_history_data['needs_review'] = false;
            error_log("Consistency check: Set needs_review to false for " . $medical_history_data['medical_approval'] . " status");
        }
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
