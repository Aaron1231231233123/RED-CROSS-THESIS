<?php
session_start();
require_once '../conn/db_conn.php';

// Helper function to ensure donation type is compatible with database enum
function mapDonationType($donationType) {
    // List of valid donation types in the database
    $validTypes = [
        'in-house', 'walk-in', 'replacement', 'patient-directed', 
        'mobile', 'mobile-walk-in', 'mobile-replacement', 'mobile-patient-directed'
    ];
    
    // If the donation type is already valid, return it
    if (in_array($donationType, $validTypes)) {
        return $donationType;
    }
    
    // Map mobile types to 'mobile' if not explicitly supported
    if (strpos($donationType, 'mobile') === 0) {
        // Check if it's one of our new specific mobile types
        if (in_array($donationType, ['mobile-walk-in', 'mobile-replacement', 'mobile-patient-directed'])) {
            return $donationType;
        }
        return 'mobile'; // Default fallback for any other mobile type
    }
    
    // Default fallback
    return 'walk-in';
}

// Special function to format data for Supabase PostgreSQL with user-defined types
function formatDataForSupabase($data) {
    if (!isset($data['donation_type'])) {
        return $data;
    }
    
    // Handle donation_type as a special case - it's a user-defined type in Postgres
    // Convert it to the format Supabase PostgreSQL expects for user-defined types
    $donationType = $data['donation_type'];
    
    // First, remove it from the array
    unset($data['donation_type']);
    
    // Create a string representation for the enum
    $data['donation_type'] = $donationType;
    
    // Log the transformation
    error_log("Transformed donation_type for Supabase: " . $donationType);
    
    return $data;
}

// Function to process screening form submission
function processScreeningForm() {
    header('Content-Type: application/json');
    
    try {
        // Debug log the raw POST data
        error_log("====== BEGIN SCREENING FORM SUBMISSION ======");
        error_log("Raw POST data: " . print_r($_POST, true));
        error_log("Session data: " . print_r($_SESSION, true));
        
        // Get donor_id from POST or session
        $donor_id = null;
        if (isset($_POST['donor_id'])) {
            $donor_id = $_POST['donor_id'];
            $_SESSION['donor_id'] = $donor_id;
        } elseif (isset($_SESSION['donor_id'])) {
            $donor_id = $_SESSION['donor_id'];
        } else {
            throw new Exception("Missing donor_id");
        }
        
        // Get medical_history_id
        $medical_history_id = null;
        
        // First, check if medical_history_id exists for this donor
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $medical_history_data = json_decode($response, true);
            if (is_array($medical_history_data) && !empty($medical_history_data)) {
                $medical_history_id = $medical_history_data[0]['medical_history_id'];
                error_log("Found existing medical_history_id: $medical_history_id for donor_id: $donor_id");
            } else {
                // No record found - create a new medical_history entry
                error_log("No medical history record found for donor_id: $donor_id - creating one now");
                
                // Prepare minimal medical history data
                $medical_history_data = [
                    'donor_id' => $donor_id,
                    'feels_well' => true,
                    'previously_refused' => false,
                    'testing_purpose_only' => false,
                    'understands_transmission_risk' => true
                ];
                
                // Create the medical history record
                $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($medical_history_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ]);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code === 201) {
                    $response_data = json_decode($response, true);
                    if (is_array($response_data) && isset($response_data[0]['medical_history_id'])) {
                        $medical_history_id = $response_data[0]['medical_history_id'];
                        error_log("Created new medical_history_id: $medical_history_id for donor_id: $donor_id");
                    } else {
                        error_log("Failed to create medical history - invalid response format: " . $response);
                        throw new Exception("Failed to create medical history record");
                    }
                } else {
                    error_log("Failed to create medical history - HTTP Code: $http_code, Response: $response");
                    throw new Exception("Failed to create medical history record. HTTP Code: $http_code");
                }
            }
        }

        if (!$medical_history_id) {
            throw new Exception("Could not create or find medical_history_id");
        }

        // Check if user_id exists in session
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("User session not found. Please log in again.");
        }
        
        // Prepare the base data for insertion
        $screening_data = [
            'donor_form_id' => $donor_id,
            'medical_history_id' => $medical_history_id,
            'interviewer_id' => $_SESSION['user_id'],
            'body_weight' => floatval($_POST['body-wt']),
            'specific_gravity' => $_POST['sp-gr'] ?: "",
            'blood_type' => $_POST['blood-type'],
            'donation_type' => mapDonationType($_POST['donation-type']),
            'has_previous_donation' => isset($_POST['history']) && $_POST['history'] === 'yes',
            'interview_date' => date('Y-m-d'),
            'red_cross_donations' => isset($_POST['history']) && $_POST['history'] === 'yes' ? intval($_POST['red-cross']) : 0,
            'hospital_donations' => isset($_POST['history']) && $_POST['history'] === 'yes' ? intval($_POST['hospital-history']) : 0,
            'last_rc_donation_place' => isset($_POST['history']) && $_POST['history'] === 'yes' ? ($_POST['last-rc-donation-place'] ?: "") : "",
            'last_hosp_donation_place' => isset($_POST['history']) && $_POST['history'] === 'yes' ? ($_POST['last-hosp-donation-place'] ?: "") : "",
            'last_rc_donation_date' => isset($_POST['history']) && $_POST['history'] === 'yes' && !empty($_POST['last-rc-donation-date']) ? $_POST['last-rc-donation-date'] : '0001-01-01',
            'last_hosp_donation_date' => isset($_POST['history']) && $_POST['history'] === 'yes' && !empty($_POST['last-hosp-donation-date']) ? $_POST['last-hosp-donation-date'] : '0001-01-01',
            'mobile_location' => strpos($_POST['donation-type'], 'mobile') === 0 ? ($_POST['mobile-place'] ?: "") : "",
            'mobile_organizer' => strpos($_POST['donation-type'], 'mobile') === 0 ? ($_POST['mobile-organizer'] ?: "") : "",
            'patient_name' => ($_POST['donation-type'] === 'patient-directed' || $_POST['donation-type'] === 'mobile-patient-directed') ? ($_POST['patient-name'] ?: "") : "",
            'hospital' => ($_POST['donation-type'] === 'patient-directed' || $_POST['donation-type'] === 'mobile-patient-directed') ? ($_POST['hospital'] ?: "") : "",
            'patient_blood_type' => ($_POST['donation-type'] === 'patient-directed' || $_POST['donation-type'] === 'mobile-patient-directed') ? ($_POST['blood-type-patient'] ?: "") : "",
            'component_type' => ($_POST['donation-type'] === 'patient-directed' || $_POST['donation-type'] === 'mobile-patient-directed') ? ($_POST['wb-component'] ?: "") : "",
            'units_needed' => ($_POST['donation-type'] === 'patient-directed' || $_POST['donation-type'] === 'mobile-patient-directed') && !empty($_POST['no-units']) ? intval($_POST['no-units']) : 0
        ];

        // Fetch staff info: surname, first_name, office_address
        $staff_full_name = '';
        $staff_office_address = '';
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $u_ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=surname,first_name,office_address&user_id=eq.' . $user_id);
            curl_setopt($u_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($u_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]);
            $u_resp = curl_exec($u_ch);
            $u_http = curl_getinfo($u_ch, CURLINFO_HTTP_CODE);
            curl_close($u_ch);
            if ($u_http === 200) {
                $u_rows = json_decode($u_resp, true) ?: [];
                if (!empty($u_rows)) {
                    $u = $u_rows[0];
                    $surname = isset($u['surname']) ? trim($u['surname']) : '';
                    $firstname = isset($u['first_name']) ? trim($u['first_name']) : '';
                    $staff_full_name = trim($surname . (strlen($surname) && strlen($firstname) ? ', ' : '') . $firstname);
                    $staff_office_address = isset($u['office_address']) ? trim($u['office_address']) : '';
                }
            }
        }

        if ($staff_full_name !== '') {
            $screening_data['staff'] = $staff_full_name;
        }

        // Check if there's an existing screening record to update
        $should_update = false;
        $existing_screening_id = null;

        if (isset($_POST['screening_id']) && !empty($_POST['screening_id'])) {
            $existing_screening_id = $_POST['screening_id'];
            $should_update = true;
        } else {
            // Check if there's an existing record for this donor
            $ch_check = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=screening_id&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
            curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_check, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]);
            
            $check_response = curl_exec($ch_check);
            $check_http_code = curl_getinfo($ch_check, CURLINFO_HTTP_CODE);
            curl_close($ch_check);
            
            if ($check_http_code === 200) {
                $existing_records = json_decode($check_response, true);
                if (is_array($existing_records) && !empty($existing_records)) {
                    $existing_screening_id = $existing_records[0]['screening_id'];
                    $should_update = true;
                }
            }
        }

        if ($should_update && $existing_screening_id) {
            // UPDATE existing record
            $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?screening_id=eq.' . $existing_screening_id);
            
            $headers = array(
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation',
                'Accept: application/json',
                'X-PostgreSQL-Identifier-Case-Sensitive: true'
            );
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            
            $formatted_data = formatDataForSupabase($screening_data);
            $jsonData = json_encode($formatted_data);
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code >= 200 && $http_code < 300) {
                $_SESSION['screening_id'] = $existing_screening_id;
                echo json_encode([
                    'success' => true,
                    'screening_id' => $existing_screening_id,
                    'message' => 'Screening form updated successfully'
                ]);
                exit();
            } else {
                throw new Exception("Failed to update screening form. HTTP Code: " . $http_code . ", Response: " . $response);
            }
        } else {
            // INSERT new record
            // If the user explicitly selected No history, set defaults
            if (!(isset($_POST['history']) && $_POST['history'] === 'yes')) {
                $screening_data['has_previous_donation'] = false;
                $screening_data['red_cross_donations'] = 1;
                $screening_data['hospital_donations'] = 0;
                $screening_data['last_rc_donation_date'] = date('Y-m-d');
                if (!empty($staff_office_address)) {
                    $screening_data['last_rc_donation_place'] = $staff_office_address;
                }
            }
            $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form');

            $headers = array(
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            );

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            
            $formatted_data = formatDataForSupabase($screening_data);
            $jsonData = json_encode($formatted_data);
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 201) {
                $response_data = json_decode($response, true);
                
                if (is_array($response_data) && isset($response_data[0]['screening_id'])) {
                    $_SESSION['screening_id'] = $response_data[0]['screening_id'];
                    
                    echo json_encode([
                        'success' => true,
                        'screening_id' => $response_data[0]['screening_id'],
                        'message' => 'Screening form submitted successfully'
                    ]);
                    exit();
                } else {
                    throw new Exception("Invalid response format or missing screening_id: " . $response);
                }
            } else {
                throw new Exception("Failed to submit screening form. HTTP Code: " . $http_code . ", Response: " . $response);
            }
        }
    } catch (Exception $e) {
        error_log("Error in screening form submission: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit();
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    processScreeningForm();
}
?> 