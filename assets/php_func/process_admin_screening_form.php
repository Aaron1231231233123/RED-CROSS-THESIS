<?php
session_start();
require_once '../conn/db_conn.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin access required.']);
    exit();
}

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
    $donationType = $data['donation_type'];
    
    // First, remove it from the array
    unset($data['donation_type']);
    
    // Create a string representation for the enum
    $data['donation_type'] = $donationType;
    
    // Log the transformation
    error_log("Transformed donation_type for Supabase: " . $donationType);
    
    return $data;
}

// Function to process admin screening form submission
function processAdminScreeningForm() {
    header('Content-Type: application/json');
    
    // Enable error logging
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    try {
        // Debug log the raw POST data
        error_log("====== BEGIN ADMIN SCREENING FORM SUBMISSION ======");
        error_log("Raw POST data: " . print_r($_POST, true));
        error_log("Session data: " . print_r($_SESSION, true));
        error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
        error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        // Get donor_id from POST
        $donor_id = null;
        if (isset($_POST['donor_id'])) {
            $donor_id = $_POST['donor_id'];
        } else {
            throw new Exception("Missing donor_id");
        }
        
        // Get medical_history_id
        $medical_history_id = null;
        
        // First, check if medical_history_id exists for this donor
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
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
                    'understands_transmission_risk' => true,
                    'is_admin' => true // Mark as admin processed
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

        // Get donation type from POST
        $donation_type = '';
        if (isset($_POST['donation-type'])) {
            $donation_type = mapDonationType($_POST['donation-type']);
        } elseif (isset($_POST['donor_type']) && $_POST['donor_type'] === 'Mobile') {
            $donation_type = 'mobile';
        }
        
        // Prepare the base data for insertion
        $screening_data = [
            'donor_form_id' => $donor_id,
            'medical_history_id' => $medical_history_id,
            'interviewer_id' => $_SESSION['user_id'],
            'body_weight' => isset($_POST['body-wt']) ? floatval($_POST['body-wt']) : null,
            'specific_gravity' => isset($_POST['sp-gr']) ? $_POST['sp-gr'] : null,
            'blood_type' => isset($_POST['blood-type']) ? $_POST['blood-type'] : null,
            'donation_type' => $donation_type,
            'interview_date' => date('Y-m-d'),
            'mobile_location' => isset($_POST['mobile-place']) ? $_POST['mobile-place'] : null,
            'mobile_organizer' => isset($_POST['mobile-organizer']) ? $_POST['mobile-organizer'] : null,
            'patient_name' => isset($_POST['patient-name']) ? $_POST['patient-name'] : null,
            'hospital' => isset($_POST['hospital']) ? $_POST['hospital'] : null,
            'patient_blood_type' => isset($_POST['patient-blood-type']) ? $_POST['patient-blood-type'] : null,
            'units_needed' => isset($_POST['no-units']) && !empty($_POST['no-units']) ? intval($_POST['no-units']) : null
        ];

        // Fetch staff info: surname, first_name, office_address
        $staff_full_name = '';
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
                }
            }
        }

        if ($staff_full_name !== '') {
            $screening_data['staff'] = $staff_full_name;
        }

        // Check if there's an existing screening record to update
        $should_update = false;
        $existing_screening_id = null;

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

        if ($should_update && $existing_screening_id) {
            // UPDATE existing record
            $screening_data['updated_at'] = date('Y-m-d H:i:s');
            unset($screening_data['created_at']); // Remove created_at for UPDATE
            
            $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?screening_id=eq.' . $existing_screening_id);
            
            $headers = array(
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation',
                'Accept: application/json'
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
                error_log("Admin screening form updated successfully for donor_id: $donor_id");
                echo json_encode([
                    'success' => true,
                    'screening_id' => $existing_screening_id,
                    'message' => 'Admin screening form updated successfully'
                ]);
                exit();
            } else {
                throw new Exception("Failed to update screening form. HTTP Code: " . $http_code . ", Response: " . $response);
            }
        } else {
            // INSERT new record
            $screening_data['created_at'] = date('Y-m-d H:i:s');
            $screening_data['updated_at'] = date('Y-m-d H:i:s');
            
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
            
            error_log("Admin screening form data being inserted: " . $jsonData);
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 201) {
                $response_data = json_decode($response, true);
                
                if (is_array($response_data) && isset($response_data[0]['screening_id'])) {
                    $screening_id = $response_data[0]['screening_id'];
                    error_log("Admin screening form created successfully for donor_id: $donor_id, screening_id: $screening_id");
                    
                    echo json_encode([
                        'success' => true,
                        'screening_id' => $screening_id,
                        'donor_id' => $donor_id,
                        'message' => 'Admin screening form submitted successfully'
                    ]);
                    exit();
                } else {
                    error_log("Invalid response format for donor_id $donor_id. Response: " . $response);
                    throw new Exception("Invalid response format or missing screening_id: " . substr($response, 0, 500));
                }
            } else {
                error_log("Failed to submit screening form for donor_id $donor_id. HTTP Code: $http_code, Response: " . substr($response, 0, 1000));
                throw new Exception("Failed to submit screening form. HTTP Code: " . $http_code . ", Response: " . substr($response, 0, 500));
            }
        }
    } catch (Exception $e) {
        error_log("Error in admin screening form submission: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit();
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    processAdminScreeningForm();
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>

