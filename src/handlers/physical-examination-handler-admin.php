<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once '../../assets/conn/db_conn.php';

// Helper function to get physician name from user_id
function getPhysicianName($user_id) {
    try {
        $ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=first_name,surname&user_id=eq.' . $user_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response !== false) {
            $users = json_decode($response, true);
            if (!empty($users) && isset($users[0])) {
                $user = $users[0];
                $first_name = trim($user['first_name'] ?? '');
                $surname = trim($user['surname'] ?? '');
                
                if (!empty($first_name) && !empty($surname)) {
                    return "Dr. $first_name $surname";
                } elseif (!empty($first_name)) {
                    return "Dr. $first_name";
                } elseif (!empty($surname)) {
                    return "Dr. $surname";
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting physician name: " . $e->getMessage());
    }
    return 'Dr. Unknown';
}

// Admin-specific physical examination handler
// This handler is specifically designed for admin workflows

// Set content type to JSON
header('Content-Type: application/json');

// Check if user has admin role (role_id = 1)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    error_log("Admin physical examination handler: Invalid role - " . ($_SESSION['role_id'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Access denied - Admin role required']);
    exit();
}

// For admin role, ensure we have donor_id
if (!isset($_POST['donor_id']) || empty($_POST['donor_id'])) {
    error_log("Admin physical examination handler: Missing donor_id");
    echo json_encode(['success' => false, 'message' => 'Missing donor_id']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Log submission for tracking
        error_log("Admin Physical Examination Handler - Processing physical examination for donor: " . $_POST['donor_id']);
        
        // Debug log all POST data
        error_log("Admin Physical Examination Handler - POST data: " . print_r($_POST, true));
        
        // Validate required fields with more detailed logging
        $required_fields = ['blood_pressure', 'pulse_rate', 'body_temp', 'gen_appearance', 'skin', 'heent', 'heart_and_lungs'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            $value = $_POST[$field] ?? '';
            $is_set = isset($_POST[$field]);
            $is_empty = empty(trim($value));
            
            error_log("Admin Physical Examination Handler - Field $field: " . ($is_set ? 'SET' : 'NOT SET') . ", " . ($is_empty ? 'EMPTY' : 'NOT EMPTY') . " (value: '$value')");
            
            if (!$is_set || $is_empty) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            error_log("Admin Physical Examination Handler - Missing required fields: " . implode(', ', $missing_fields));
            throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
        }
        
        // Get screening_id for this donor
        $screening_id = null;
        $screening_ch = curl_init();
        curl_setopt_array($screening_ch, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $_POST['donor_id'] . "&select=screening_id&order=created_at.desc&limit=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]
        ]);
        $screening_response = curl_exec($screening_ch);
        curl_close($screening_ch);
        
        if ($screening_response) {
            $screening_data = json_decode($screening_response, true);
            $screening_id = !empty($screening_data) ? $screening_data[0]['screening_id'] : null;
            error_log("Admin Physical Examination Handler - Found screening_id: " . ($screening_id ?? 'null'));
        }

        // Get physician name for the logged-in user
        $physician_name = getPhysicianName($_SESSION['user_id']);
        error_log("Admin Physical Examination Handler - Physician name: " . $physician_name);

        // Prepare data for insertion
        $data = [
            'donor_id' => intval($_POST['donor_id']), // int4
            'screening_id' => $screening_id, // Include screening_id for referential integrity
            'physician' => $physician_name, // Include physician name
            'blood_pressure' => strval($_POST['blood_pressure']), // varchar
            'pulse_rate' => intval($_POST['pulse_rate']), // int4
            'body_temp' => number_format(floatval($_POST['body_temp']), 1), // numeric with 1 decimal place
            'gen_appearance' => strval(trim($_POST['gen_appearance'])), // text
            'skin' => strval(trim($_POST['skin'])), // text
            'heent' => strval(trim($_POST['heent'])), // text
            'heart_and_lungs' => strval(trim($_POST['heart_and_lungs'])), // text
            'remarks' => 'Accepted', // Admin always accepts for blood collection
            'needs_review' => false, // Admin has completed the review
            'blood_bag_type' => 'Single' // Default for admin flow
        ];

        // Only add recommendation if provided
        if (isset($_POST['reason']) && !empty($_POST['reason'])) {
            $data['recommendation'] = strval(trim($_POST['reason']));
        }

        // Log data being sent
        error_log("Admin Physical Examination Handler - Sending data to Supabase for donor: " . $data['donor_id']);

        // Check for existing physical examination record first
        $existing_check = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $data['donor_id'] . '&order=created_at.desc&limit=1');
        curl_setopt($existing_check, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($existing_check, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json'
        ]);
        
        $existing_response = curl_exec($existing_check);
        $existing_http_code = curl_getinfo($existing_check, CURLINFO_HTTP_CODE);
        curl_close($existing_check);
        
        $should_update = false;
        $physical_exam_id = null;
        
        if ($existing_http_code === 200) {
            $existing_records = json_decode($existing_response, true);
            if (!empty($existing_records) && isset($existing_records[0]['physical_exam_id'])) {
                $should_update = true;
                $physical_exam_id = $existing_records[0]['physical_exam_id'];
                error_log("Admin Physical Examination Handler - Found existing record, will update: " . $physical_exam_id);
            } else {
                error_log("Admin Physical Examination Handler - No existing record found, will create new");
            }
        } else {
            error_log("Admin Physical Examination Handler - Failed to check existing records, will create new");
        }

        // Initialize cURL session for Supabase
        if ($should_update) {
            // UPDATE existing record
            $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . $physical_exam_id);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            error_log("Admin Physical Examination Handler - Updating existing physical examination record");
        } else {
            // INSERT new record
            $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
            curl_setopt($ch, CURLOPT_POST, true);
            error_log("Admin Physical Examination Handler - Creating new physical examination record");
        }

        $headers = array(
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        );

        $json_data = json_encode($data);
        if ($json_data === false) {
            error_log("Admin Physical Examination Handler - JSON encoding error: " . json_last_error_msg());
            throw new Exception("Error preparing data for submission");
        }

        // Debug log before sending
        error_log("Admin Physical Examination Handler - Submitting to Supabase");

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        // Log response
        error_log("Admin Physical Examination Handler - Supabase response code: " . $http_code);

        curl_close($ch);

        if (($should_update && $http_code >= 200 && $http_code < 300) || (!$should_update && $http_code === 201)) {
            // Parse the response to get the physical examination ID
            $response_data = json_decode($response, true);
            
            if (is_array($response_data) && isset($response_data[0]['physical_exam_id'])) {
                $physical_exam_id = $response_data[0]['physical_exam_id'];
                error_log("Admin Physical Examination Handler - " . ($should_update ? "Updated" : "Created") . " physical_exam_id: " . $physical_exam_id);
                
                // Create blood collection record for admin workflow (only if it doesn't exist)
                try {
                    // Check if blood collection record already exists
                    $collection_check = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id&physical_exam_id=eq.' . $physical_exam_id . '&limit=1');
                    curl_setopt($collection_check, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($collection_check, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json'
                    ]);
                    
                    $collection_check_response = curl_exec($collection_check);
                    $collection_check_http_code = curl_getinfo($collection_check, CURLINFO_HTTP_CODE);
                    curl_close($collection_check);
                    
                    $collection_exists = false;
                    if ($collection_check_http_code === 200) {
                        $collection_check_data = json_decode($collection_check_response, true);
                        $collection_exists = !empty($collection_check_data);
                    }
                    
                    if (!$collection_exists) {
                        // Create blood collection record only if it doesn't exist
                        $collectionData = [
                            'physical_exam_id' => $physical_exam_id,
                            'needs_review' => true,
                            'status' => 'pending',
                            'created_at' => date('Y-m-d\TH:i:s.000\Z'),
                            'updated_at' => date('Y-m-d\TH:i:s.000\Z')
                        ];
                        
                        // Add screening_id if available
                        if (!empty($screening_id)) {
                            $collectionData['screening_id'] = $screening_id;
                        }
                        
                        $collection_ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection');
                        curl_setopt($collection_ch, CURLOPT_POST, true);
                        curl_setopt($collection_ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($collection_ch, CURLOPT_POSTFIELDS, json_encode($collectionData));
                        curl_setopt($collection_ch, CURLOPT_HTTPHEADER, [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json',
                            'Prefer: return=representation'
                        ]);
                        
                        $collection_response = curl_exec($collection_ch);
                        $collection_http_code = curl_getinfo($collection_ch, CURLINFO_HTTP_CODE);
                        curl_close($collection_ch);
                        
                        if ($collection_http_code === 201) {
                            $collection_data = json_decode($collection_response, true);
                            $blood_collection_id = $collection_data[0]['blood_collection_id'] ?? null;
                            error_log("Admin Physical Examination Handler - Created blood collection record: " . $blood_collection_id);
                        } else {
                            error_log("Admin Physical Examination Handler - Failed to create blood collection record: HTTP $collection_http_code");
                        }
                    } else {
                        error_log("Admin Physical Examination Handler - Blood collection record already exists, skipping creation");
                    }
                } catch (Exception $collection_error) {
                    error_log("Admin Physical Examination Handler - Blood collection creation error: " . $collection_error->getMessage());
                }
                
                // Create eligibility record for admin workflow
                try {
                    $status = 'approved'; // Admin always approves for blood collection
                    
                    // Calculate end date (3 months for approved donors)
                    $end_date = new DateTime();
                    $end_date->modify('+3 months');
                    $end_date_formatted = $end_date->format('Y-m-d\TH:i:s.000\Z');
                    
                    // Get related IDs
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
                    
                    // Get medical history ID
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
                    
                    // Note: Eligibility records are automatically created by database triggers
                    
                    // Cache invalidation will be handled by the frontend refresh
                    error_log("Admin Physical Examination Handler - Physical examination completed for donor: " . $data['donor_id']);
                    
                    // Return success response for admin
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Physical examination ' . ($should_update ? 'updated' : 'completed') . ' successfully',
                        'physical_exam_id' => $physical_exam_id,
                        'donor_id' => $data['donor_id'],
                        'next_step' => 'blood_collection',
                        'action_performed' => $should_update ? 'updated' : 'created'
                    ]);
                    exit();
                    
                } catch (Exception $eligibility_error) {
                    error_log("Admin Physical Examination Handler - Eligibility creation error: " . $eligibility_error->getMessage());
                    // Still return success for physical examination creation
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Physical examination ' . ($should_update ? 'updated' : 'completed') . ' successfully',
                        'physical_exam_id' => $physical_exam_id,
                        'donor_id' => $data['donor_id'],
                        'next_step' => 'blood_collection',
                        'action_performed' => $should_update ? 'updated' : 'created',
                        'warning' => 'Eligibility record creation failed'
                    ]);
                    exit();
                }
                
            } else {
                error_log("Admin Physical Examination Handler - Invalid response format: " . print_r($response_data, true));
                throw new Exception("Invalid response format from database");
            }
        } else {
            // Log the error with more details
            error_log("Admin Physical Examination Handler - Error inserting physical examination. HTTP Code: " . $http_code);
            error_log("Admin Physical Examination Handler - Response: " . $response);
            error_log("Admin Physical Examination Handler - CURL Error: " . $curl_error);
            throw new Exception("Failed to save physical examination data. Error code: " . $http_code);
        }

    } catch (Exception $e) {
        // Log the error and return JSON error response
        error_log("Admin Physical Examination Handler - Error: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
} else {
    // Not a POST request - return error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Clean up any unexpected output and ensure only JSON is sent
$unexpected_output = ob_get_clean();
if (!empty($unexpected_output)) {
    error_log("Admin Physical Examination Handler - Unexpected output detected: " . $unexpected_output);
    // Still send the JSON response, but log the issue
}
?>
