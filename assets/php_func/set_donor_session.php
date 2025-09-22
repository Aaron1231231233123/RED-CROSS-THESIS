<?php
session_start();
require_once '../conn/db_conn.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['donor_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$donor_id = $input['donor_id'];
$action = $input['action'];

if ($action === 'set_donor_session') {
    // Set donor_id in session
    $_SESSION['donor_id'] = $donor_id;
    
    // For admin users, we might need to set additional session variables
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        // Admin user - set minimal required session variables
        $_SESSION['donor_id'] = $donor_id;
        
        // Try to get screening_id if available
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donor_id . "&order=created_at.desc&limit=1",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ],
            ]);
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($http_code === 200) {
                $data = json_decode($response, true);
                if (!empty($data)) {
                    $_SESSION['screening_id'] = $data[0]['screening_id'];
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching screening_id: " . $e->getMessage());
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Session variables set successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>