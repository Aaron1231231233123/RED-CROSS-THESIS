<?php
session_start();
require_once '../../assets/conn/db_conn.php';



// For staff role (role_id 3), ensure we have donor_id and screening_id
if ($_SESSION['role_id'] == 3) {
    $missing_data = false;
    
    // Check if we have donor_id, either from session or POST
    if (!isset($_SESSION['donor_id']) && (!isset($_POST['donor_id']) || empty($_POST['donor_id']))) {
        error_log("Missing donor_id for staff role");
        $missing_data = true;
    } elseif (isset($_POST['donor_id']) && !isset($_SESSION['donor_id'])) {
        // Set from POST if needed
        $_SESSION['donor_id'] = $_POST['donor_id'];
        error_log("Set donor_id from POST: " . $_POST['donor_id']);
    }
    
    // Check if we have screening_id, either from session or POST
    if (!isset($_SESSION['screening_id']) && (!isset($_POST['screening_id']) || empty($_POST['screening_id']))) {
        error_log("Missing screening_id for staff role");
        $missing_data = true;
    } elseif (isset($_POST['screening_id']) && !isset($_SESSION['screening_id'])) {
        // Set from POST if needed
        $_SESSION['screening_id'] = $_POST['screening_id'];
        error_log("Set screening_id from POST: " . $_POST['screening_id']);
    }
    
    // If still missing data, redirect
    if ($missing_data) {
        error_log("Missing required data for physical examination - redirecting to dashboard");
        header('Location: ../../public/Dashboards/dashboard-staff-physical-submission.php');
    exit();
    }
} else {
    // For admin role (role_id 1), set donor_id to 46 if not set
    if (!isset($_SESSION['donor_id'])) {
        $_SESSION['donor_id'] = 46;
        error_log("Set donor_id to 46 for admin role");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug log
        error_log("POST data received in handler: " . print_r($_POST, true));
        error_log("Session data in handler: " . print_r($_SESSION, true));
        // Prepare data for insertion
        $data = [
            'donor_id' => intval($_SESSION['donor_id']), // int4
            'blood_pressure' => strval($_POST['blood_pressure']), // varchar
            'pulse_rate' => intval($_POST['pulse_rate']), // int4
            'body_temp' => number_format(floatval($_POST['body_temp']), 1), // numeric with 1 decimal place
            'gen_appearance' => strval(trim($_POST['gen_appearance'])), // text
            'skin' => strval(trim($_POST['skin'])), // text
            'heent' => strval(trim($_POST['heent'])), // text
            'heart_and_lungs' => strval(trim($_POST['heart_and_lungs'])), // text
            'remarks' => strval(trim($_POST['remarks'])), // varchar
            'blood_bag_type' => strval(trim($_POST['blood_bag_type'])) // varchar
        ];

        // Only add disapproval_reason if provided
        if (isset($_POST['reason']) && !empty($_POST['reason'])) {
            $data['recommendation'] = strval(trim($_POST['reason'])); // Changed from disapproval_reason to recommendation
        }

        // Debug log the data being sent
        error_log("Data being sent to Supabase: " . print_r($data, true));

        // Initialize cURL session for Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');

        $headers = array(
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        );

        $json_data = json_encode($data);
        if ($json_data === false) {
            error_log("JSON encoding error: " . json_last_error_msg());
            throw new Exception("Error preparing data for submission");
        }

        // Debug log before sending
        error_log("Final JSON being sent: " . $json_data);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        // Debug log
        error_log("Supabase response code in handler: " . $http_code);
        error_log("Supabase response in handler: " . $response);
        error_log("CURL error if any: " . $curl_error);
        error_log("Request URL: " . SUPABASE_URL . '/rest/v1/physical_examination');
        error_log("Request Headers: " . print_r($headers, true));
        error_log("Request Data: " . $json_data);

        curl_close($ch);

        if ($http_code === 201) {
            // Parse the response to get the physical examination ID
            $response_data = json_decode($response, true);
            
            if (is_array($response_data) && isset($response_data[0]['physical_exam_id'])) {
                $_SESSION['physical_examination_id'] = $response_data[0]['physical_exam_id'];
                error_log("Stored physical_examination_id in session: " . $_SESSION['physical_examination_id']);
                
                // NEW CODE: Create eligibility record based on physical examination status
                try {
                    // Get the remarks value to determine eligibility status
                    $remarks = $data['remarks'] ?? '';
                    $status = 'pending'; // Default status
                    
                    // Determine status based on remarks
                    if ($remarks === 'Accepted') {
                        $status = 'approved';
                    } else if (in_array($remarks, ['Temporarily Deferred', 'Permanently Deferred', 'Refused'])) {
                        $status = 'declined';
                    }
                    
                    // Calculate appropriate end date
                    $end_date = new DateTime();
                    if ($status === 'approved') {
                        $end_date->modify('+3 months'); // 3 months for approved donors
                    } else if ($status === 'declined') {
                        if (strpos($remarks, 'Permanently') !== false) {
                            $end_date->modify('+100 years'); // Long time for permanent deferral
                        } else if (strpos($remarks, 'Temporarily') !== false) {
                            $end_date->modify('+6 months'); // 6 months for temporary deferrals
                        } else {
                            $end_date->modify('+3 months'); // 3 months for other declined reasons
                        }
                    } else {
                        $end_date->modify('+3 days'); // Short time for pending
                    }
                    $end_date_formatted = $end_date->format('Y-m-d\TH:i:s.000\Z');
                    
                    // Check if an eligibility record already exists for this donor
                    $eligibility_check_ch = curl_init();
                    curl_setopt_array($eligibility_check_ch, [
                        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?donor_id=eq." . $data['donor_id'] . "&select=eligibility_id&order=created_at.desc&limit=1",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json'
                        ]
                    ]);
                    $eligibility_check_response = curl_exec($eligibility_check_ch);
                    curl_close($eligibility_check_ch);
                    
                    $existing_eligibility = json_decode($eligibility_check_response, true);
                    
                    // Get the medical history ID and screening ID
                    $medical_history_ch = curl_init();
                    curl_setopt_array($medical_history_ch, [
                        CURLOPT_URL => SUPABASE_URL . "/rest/v1/medical_history?donor_id=eq." . $data['donor_id'] . "&select=medical_history_id&order=created_at.desc&limit=1",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json'
                        ]
                    ]);
                    $medical_history_response = curl_exec($medical_history_ch);
                    curl_close($medical_history_ch);
                    
                    $medical_history_data = json_decode($medical_history_response, true);
                    $medical_history_id = !empty($medical_history_data) ? $medical_history_data[0]['medical_history_id'] : null;
                    
                    $screening_ch = curl_init();
                    curl_setopt_array($screening_ch, [
                        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $data['donor_id'] . "&select=screening_id,blood_type,donation_type&order=created_at.desc&limit=1",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json'
                        ]
                    ]);
                    $screening_response = curl_exec($screening_ch);
                    curl_close($screening_ch);
                    
                    $screening_data = json_decode($screening_response, true);
                    $screening_id = !empty($screening_data) ? $screening_data[0]['screening_id'] : null;
                    $blood_type = !empty($screening_data) ? $screening_data[0]['blood_type'] : null;
                    $donation_type = !empty($screening_data) ? $screening_data[0]['donation_type'] : null;
                    
                    // Note: Eligibility records are automatically created by database triggers
                
                // Different redirections based on role
            if ($_SESSION['role_id'] === 1) {
                    // Admin (role_id 1) - Direct to blood collection
                    error_log("Admin role: Redirecting to blood collection form");
                    $physical_exam_id = $_SESSION['physical_examination_id'] ?? null;
                    $donor_id = $_SESSION['donor_id'] ?? null;
                    $redirect_url = '../views/forms/blood-collection-form.php';
                    if ($donor_id) {
                        $redirect_url .= '?donor_id=' . urlencode($donor_id);
                        if ($physical_exam_id) {
                            $redirect_url .= '&physical_exam_id=' . urlencode($physical_exam_id);
                        }
                    }
                    header('Location: ' . $redirect_url);
                } else {
                    error_log("Staff role: Redirecting to appropriate donor list");
                    
                    if ($status === 'approved') {
                        // Redirect to approved donors list
                        header('Location: ../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?status=approved&processed=true');
                    } else if ($status === 'declined') {
                        // Redirect to declined donors list
                        header('Location: ../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?status=declined&processed=true');
                    } else {
                        // Redirect to pending donors list
                        header('Location: ../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?status=pending&processed=true');
                    }
                }
                
                // NEW CONDITION FOR ROLE ID 3 - Redirect to physical submission dashboard
                if ($_SESSION['role_id'] === 3) {
                    error_log("Role 3 specific redirection: Going to physical submission dashboard");
                    header('Location: ../../public/Dashboards/dashboard-staff-physical-submission.php');
                    exit();
                }
                
                exit();
            } else {
                error_log("Invalid response format from database: " . print_r($response_data, true));
                throw new Exception("Invalid response format from database");
            }
        } else {
            // Log the error with more details
            error_log("Error inserting physical examination. HTTP Code: " . $http_code);
            error_log("Response: " . $response);
            error_log("CURL Error: " . $curl_error);
            throw new Exception("Failed to save physical examination data. Error code: " . $http_code);
        }

    } catch (Exception $e) {
        // Log the error and redirect with error message
        error_log("Error in physical-examination-handler.php: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ../views/forms/physical-examination-form.php?error=1');
        exit();
    }
} else {
    // Not a POST request - redirect back to form
    header('Location: ../views/forms/physical-examination-form.php');
    exit();
}
?> 