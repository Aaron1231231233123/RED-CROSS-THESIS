<?php
session_start();
header('Content-Type: application/json');

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

function getInterviewerName($user_id) {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=*&user_id=eq.' . $user_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $users = json_decode($response, true);
        if (!empty($users) && isset($users[0])) {
            $user = $users[0];
            return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        }
    }
    return 'Unknown';
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON data received');
        }

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

        // Format data for Supabase
        $physical_exam_data = formatPhysicalExamDataForSupabase($data);
        
        // Get the donor_id
        $donor_id = intval($data['donor_id']);
        
        // Determine whether to insert or update based on role
        $user_id = $_SESSION['user_id'];
        $is_physician = false;
        
        // Check if user is a physician
        if ($_SESSION['role_id'] == 3) {
            $ch = curl_init(SUPABASE_URL . '/rest/v1/user_roles?select=user_staff_roles&user_id=eq.' . $user_id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $staff_data = json_decode($response, true);
            if (!empty($staff_data) && isset($staff_data[0]['user_staff_roles'])) {
                $is_physician = (strtolower($staff_data[0]['user_staff_roles']) === 'physician');
            }
        }
        
        $should_update = false;
        $physical_exam_id = null;
        
        // If physician, check for existing records to update
        if ($is_physician) {
            $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $existing_records = json_decode($response, true);
            
            if (!empty($existing_records) && isset($existing_records[0]['physical_exam_id'])) {
                $should_update = true;
                $physical_exam_id = $existing_records[0]['physical_exam_id'];
            }
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
        
        if (($should_update && $http_code >= 200 && $http_code < 300) || (!$should_update && $http_code === 201)) {
            // Get interviewer name
            $interviewer_name = getInterviewerName($_SESSION['user_id']);
            
            // Check if this is an accepted examination (don't update eligibility table)
            $is_accepted = isset($data['is_accepted_examination']) && $data['is_accepted_examination'] === true;
            $remarks = isset($data['remarks']) ? trim($data['remarks']) : '';
            
            if ($is_accepted || strtolower($remarks) === 'accepted') {
                // For accepted examinations, only update physical_examination table
                // Do NOT create eligibility record
                echo json_encode([
                    'success' => true,
                    'message' => 'Physical examination submitted successfully (Accepted)',
                    'interviewer_name' => $interviewer_name,
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
                    'interviewer_name' => $interviewer_name,
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
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 