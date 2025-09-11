<?php
session_start();
require_once '../conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$action = isset($_POST['action']) ? $_POST['action'] : null;
$donor_id = isset($_POST['donor_id']) ? $_POST['donor_id'] : null;
$screening_id = isset($_POST['screening_id']) ? $_POST['screening_id'] : null;

// Get user's name from session
$user_id = $_SESSION['user_id'];

// Fetch user's name from the users table
$ch = curl_init(SUPABASE_URL . '/rest/v1/users?user_id=eq.' . $user_id . '&select=first_name,surname');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$phlebotomist_name = '';
if ($http_code === 200) {
    $user_data = json_decode($response, true);
    if (!empty($user_data)) {
        $user = $user_data[0];
        // Format name as "First Name Surname"
        $phlebotomist_name = trim($user['first_name'] . ' ' . $user['surname']);
    }
}

if (!$action || !$donor_id || !$screening_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

if ($action === 'transition_to_physical') {
    try {
        // First, check if a physical examination record already exists
        $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Failed to check existing physical examination');
        }
        
        $existing_records = json_decode($response, true);
        
        if (empty($existing_records)) {
            // Create new physical examination record
            $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'donor_id' => $donor_id,
                'screening_id' => $screening_id,
                'phlebotomist' => $phlebotomist_name,
                'created_at' => date('c'),
                'updated_at' => date('c')
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 201) {
                throw new Exception('Failed to create physical examination record');
            }
        } else {
            // Update existing record with new screening_id
            $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'screening_id' => $screening_id,
                'phlebotomist' => $phlebotomist_name,
                'updated_at' => date('c')
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 204) {
                throw new Exception('Failed to update physical examination record');
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Physical examination record updated successfully']);
        
    } catch (Exception $e) {
        error_log('Error in handle_screening_transition.php: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
