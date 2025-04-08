<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php");
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    error_log("Invalid role_id: " . $_SESSION['role_id']);
    header("Location: ../../public/unauthorized.php");
    exit();
}

// For staff role (role_id 3), check for required session variables
if ($_SESSION['role_id'] === 3) {
    if (!isset($_SESSION['donor_id'])) {    
        error_log("Missing donor_id in session for staff");
        header('Location: ../../public/Dashboards/dashboard-Inventory-System.php');
        exit();
    }
    if (!isset($_SESSION['screening_id'])) {
        error_log("Missing screening_id in session for staff");
        header('Location: ../views/forms/screening-form.php');
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

        // Validate required fields
        $required_fields = [
            'blood_pressure',
            'pulse_rate',
            'body_temp',
            'gen_appearance',
            'skin',
            'heent',
            'heart_and_lungs',
            'remarks',
            'blood_bag_type'
        ];

        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                error_log("Missing required field: " . $field);
                throw new Exception("Missing required field: " . $field);
            }
        }

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

        // Only add disapproval_reason if remarks is not "Accepted"
        if (isset($_POST['reason']) && !empty($_POST['reason'])) {
            $data['disapproval_reason'] = strval(trim($_POST['reason'])); // text
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
                        $end_date->modify('+9 months'); // 9 months for approved donors
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
                    
                    if (!empty($existing_eligibility)) {
                        // Update existing eligibility record
                        $eligibility_id = $existing_eligibility[0]['eligibility_id'];
                        
                        $update_data = [
                            'status' => $status,
                            'physical_exam_id' => $response_data[0]['physical_exam_id'],
                            'remarks' => $remarks,
                            'updated_at' => date('Y-m-d H:i:s'),
                            'end_date' => $end_date_formatted
                        ];
                        
                        // Add optional fields if available
                        if ($screening_id) $update_data['screening_id'] = $screening_id;
                        if ($medical_history_id) $update_data['medical_history_id'] = $medical_history_id;
                        if ($blood_type) $update_data['blood_type'] = $blood_type;
                        if ($donation_type) $update_data['donation_type'] = $donation_type;
                        
                        // Add disapproval reason if status is declined
                        if ($status === 'declined' && isset($data['disapproval_reason']) && !empty($data['disapproval_reason'])) {
                            $update_data['disapproval_reason'] = $data['disapproval_reason'];
                        }
                        
                        $update_ch = curl_init();
                        curl_setopt_array($update_ch, [
                            CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?eligibility_id=eq." . $eligibility_id,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST => "PATCH",
                            CURLOPT_POSTFIELDS => json_encode($update_data),
                            CURLOPT_HTTPHEADER => [
                                'apikey: ' . SUPABASE_API_KEY,
                                'Authorization: Bearer ' . SUPABASE_API_KEY,
                                'Content-Type: application/json',
                                'Prefer: return=minimal'
                            ]
                        ]);
                        
                        $update_response = curl_exec($update_ch);
                        $update_http_code = curl_getinfo($update_ch, CURLINFO_HTTP_CODE);
                        curl_close($update_ch);
                        
                        error_log("Eligibility update response code: " . $update_http_code);
                        error_log("Updated eligibility record with status: " . $status);
                    } else {
                        // Create new eligibility record
                        $new_eligibility_data = [
                            'donor_id' => $data['donor_id'],
                            'physical_exam_id' => $response_data[0]['physical_exam_id'],
                            'status' => $status,
                            'remarks' => $remarks,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'start_date' => date('Y-m-d\TH:i:s.000\Z'),
                            'end_date' => $end_date_formatted
                        ];
                        
                        // Add optional fields if available
                        if ($screening_id) $new_eligibility_data['screening_id'] = $screening_id;
                        if ($medical_history_id) $new_eligibility_data['medical_history_id'] = $medical_history_id;
                        if ($blood_type) $new_eligibility_data['blood_type'] = $blood_type;
                        if ($donation_type) $new_eligibility_data['donation_type'] = $donation_type;
                        
                        // Add disapproval reason if status is declined
                        if ($status === 'declined' && isset($data['disapproval_reason']) && !empty($data['disapproval_reason'])) {
                            $new_eligibility_data['disapproval_reason'] = $data['disapproval_reason'];
                        }
                        
                        $create_ch = curl_init();
                        curl_setopt_array($create_ch, [
                            CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS => json_encode($new_eligibility_data),
                            CURLOPT_HTTPHEADER => [
                                'apikey: ' . SUPABASE_API_KEY,
                                'Authorization: Bearer ' . SUPABASE_API_KEY,
                                'Content-Type: application/json',
                                'Prefer: return=representation'
                            ]
                        ]);
                        
                        $create_response = curl_exec($create_ch);
                        $create_http_code = curl_getinfo($create_ch, CURLINFO_HTTP_CODE);
                        curl_close($create_ch);
                        
                        error_log("Eligibility creation response code: " . $create_http_code);
                        error_log("Created new eligibility record with status: " . $status);
                    }
                } catch (Exception $ee) {
                    error_log("Exception when creating/updating eligibility record: " . $ee->getMessage());
                    // Continue with normal flow even if eligibility creation fails
                }
                
                // Different redirections based on role
            if ($_SESSION['role_id'] === 1) {
                    // Admin (role_id 1) - Direct to blood collection
                    error_log("Admin role: Redirecting to blood collection form");
                header('Location: ../views/forms/blood-collection-form.php');
                } else {
                    // Staff (role_id 3) - Back to dashboard
                    error_log("Staff role: Redirecting to dashboard");
                    header('Location: ../../public/Dashboards/dashboard-staff-physical-submission.php');
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