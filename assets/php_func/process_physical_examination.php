<?php
// Start output buffering to prevent any unwanted output
ob_start();

// Disable error display but enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set custom error handler to log errors instead of displaying them
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true; // Don't execute PHP internal error handler
}
set_error_handler("customErrorHandler");

// Set headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

session_start();

require_once '../conn/db_conn.php';

// Helper function to format data for Supabase
function formatPhysicalExamDataForSupabase($post_data) {
    return [
        'donor_id' => intval($post_data['donor_id']),
        'blood_pressure' => strval($post_data['blood_pressure']),
        'pulse_rate' => intval($post_data['pulse_rate']),
        'body_temp' => number_format(floatval($post_data['body_temp']), 1),
        'gen_appearance' => strval(trim($post_data['gen_appearance'])),
        'skin' => strval(trim($post_data['skin'])),
        'heent' => strval(trim($post_data['heent'])),
        'heart_and_lungs' => strval(trim($post_data['heart_and_lungs'])),
        'remarks' => strval(trim($post_data['remarks'])),
        'blood_bag_type' => strval(trim($post_data['blood_bag_type'])),
        'reason' => isset($post_data['reason']) ? strval(trim($post_data['reason'])) : ''
    ];
}

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

// Main processing
try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    // Check for correct roles (admin role_id 1 or staff role_id 3)
    if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $json = file_get_contents('php://input');
    error_log("Raw JSON input: " . $json);
    
    $data = json_decode($json, true);
    
    if (!$data) {
        $json_error = json_last_error_msg();
        error_log("JSON decode error: " . $json_error);
        throw new Exception('Invalid JSON data received: ' . $json_error);
    }
    
    error_log("Parsed data: " . json_encode($data));

    // Validate required fields
    $required_fields = [
        'donor_id', 'blood_pressure', 'pulse_rate', 'body_temp',
        'gen_appearance', 'skin', 'heent', 'heart_and_lungs',
        'remarks', 'blood_bag_type'
    ];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Get the donor_id and check for existing physical examination
    $donor_id = intval($data['donor_id']);
    $screening_id = isset($data['screening_id']) ? $data['screening_id'] : null;
    
    // Check for existing physical examination record
    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,screening_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    if ($response === false) {
        throw new Exception('Failed to check existing physical examination records');
    }
    
    $existing_records = json_decode($response, true);
    curl_close($ch);
    
    $should_update = false;
    $physical_exam_id = null;
    
    if (!empty($existing_records) && isset($existing_records[0]['physical_exam_id'])) {
        $should_update = true;
        $physical_exam_id = $existing_records[0]['physical_exam_id'];
        // Use existing screening_id if not provided in current request
        if (!$screening_id && isset($existing_records[0]['screening_id'])) {
            $screening_id = $existing_records[0]['screening_id'];
        }
    }
    
    // Format data for Supabase
    $physical_exam_data = formatPhysicalExamDataForSupabase($data);
    
    // Add common fields for both insert and update
    $physical_exam_data['needs_review'] = false;
    $physical_exam_data['physician'] = getPhysicianName($_SESSION['user_id']);
    $physical_exam_data['updated_at'] = gmdate('Y-m-d H:i:s') . '+00';
    
    // Add screening_id if available
    if ($screening_id) {
        $physical_exam_data['screening_id'] = $screening_id;
    }
    
    if ($should_update) {
        // UPDATE existing record
        $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . $physical_exam_id);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    } else {
        // INSERT new record
        $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
        curl_setopt($ch, CURLOPT_POST, true);
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($physical_exam_data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception('Failed to submit physical examination data');
    }
    
    if (($should_update && $http_code >= 200 && $http_code < 300) || (!$should_update && $http_code === 201)) {
        // Get the physical_exam_id for blood collection updates
        if (!$should_update) {
            // For new records, get the created physical_exam_id
            $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response !== false) {
                $new_records = json_decode($response, true);
                if (!empty($new_records) && isset($new_records[0]['physical_exam_id'])) {
                    $physical_exam_id = $new_records[0]['physical_exam_id'];
                }
            }
        }
        
        // Handle blood collection record
        error_log("Starting blood collection processing - physical_exam_id: $physical_exam_id, screening_id: $screening_id");
        
        if ($physical_exam_id) {
            // Check if blood collection record already exists
            $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id&physical_exam_id=eq.' . $physical_exam_id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("Blood collection lookup response - HTTP: $http_code, Response: $response");
            
            if ($response !== false && $http_code === 200) {
                $existing_collection = json_decode($response, true);
                error_log("Parsed existing collection: " . json_encode($existing_collection));
                
                if (!empty($existing_collection)) {
                    // UPDATE existing blood collection record
                    $blood_collection_id = $existing_collection[0]['blood_collection_id'];
                    error_log("Updating existing blood collection: $blood_collection_id");
                    
                    $update_payload = [
                        'needs_review' => true,
                        'updated_at' => gmdate('Y-m-d H:i:s') . '+00'
                    ];
                    
                    $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?blood_collection_id=eq.' . $blood_collection_id);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json',
                        'Prefer: return=minimal'
                    ]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update_payload));
                    
                    $update_response = curl_exec($ch);
                    $update_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    error_log("Blood collection PATCH response - HTTP: $update_http_code, Response: $update_response");
                    
                    if ($update_http_code < 200 || $update_http_code >= 300) {
                        error_log("blood_collection PATCH failed: HTTP $update_http_code Resp: $update_response");
                    } else {
                        error_log("blood_collection PATCH successful: Updated blood_collection_id $blood_collection_id");
                    }
                } else {
                    // INSERT new blood collection record
                    error_log("No existing blood collection found, creating new record");
                    
                    $payload = [
                        'physical_exam_id' => $physical_exam_id,
                        'needs_review' => true,
                        'status' => 'pending',
                        'blood_bag_brand' => 'TERUMO',
                        'blood_bag_type' => 'T',
                        'updated_at' => gmdate('Y-m-d H:i:s') . '+00'
                    ];
                    
                    // Add screening_id if available
                    if ($screening_id) {
                        $payload['screening_id'] = $screening_id;
                    }
                    
                    error_log("Blood collection INSERT payload: " . json_encode($payload));
                    
                    $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json',
                        'Prefer: return=minimal'
                    ]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                    
                    $insert_response = curl_exec($ch);
                    $insert_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    error_log("Blood collection INSERT response - HTTP: $insert_http_code, Response: $insert_response");
                    
                    if ($insert_http_code < 200 || $insert_http_code >= 300) {
                        error_log("blood_collection INSERT failed: HTTP $insert_http_code Resp: $insert_response");
                    } else {
                        error_log("blood_collection INSERT successful");
                    }
                }
            } else {
                error_log("Failed to lookup blood collection records - HTTP: $http_code, Response: $response");
            }
        } else {
            error_log("No physical_exam_id available for blood collection processing");
        }
        
        // Get physician name for response
        $physician_name = getPhysicianName($_SESSION['user_id']);
        
        // Check if this is an accepted examination
        $is_accepted = isset($data['is_accepted_examination']) && $data['is_accepted_examination'] === true;
        $remarks = isset($data['remarks']) ? trim($data['remarks']) : '';
        
        if ($is_accepted || strtolower($remarks) === 'accepted') {
            // For accepted examinations, only update physical_examination table
            // Do NOT create eligibility record
            echo json_encode([
                'success' => true,
                'message' => 'Physical examination submitted successfully (Accepted)',
                'physician_name' => $physician_name,
                'action_performed' => $should_update ? 'updated' : 'created',
                'eligibility_updated' => false,
                'note' => 'Eligibility table not updated for accepted examinations'
            ]);
        } else {
            // For non-accepted examinations (deferrals/refused), you would handle eligibility here
            // But those are handled by the defer modal, not this endpoint
            echo json_encode([
                'success' => true,
                'message' => 'Physical examination submitted successfully',
                'physician_name' => $physician_name,
                'action_performed' => $should_update ? 'updated' : 'created',
                'eligibility_updated' => false,
                'note' => 'Non-accepted examinations should use defer endpoint'
            ]);
        }
    } else {
        throw new Exception("Failed to submit physical examination. HTTP Code: $http_code");
    }
    
} catch (Exception $e) {
    error_log("Error in process_physical_examination.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Fatal error in process_physical_examination.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

// Ensure clean output
ob_end_flush(); 