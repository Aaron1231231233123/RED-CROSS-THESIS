<?php
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require_once '../../assets/conn/db_conn.php';
// Shared helpers (provides supabaseRequest)
@include_once __DIR__ . '/../Dashboards/module/optimized_functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Admin only
$required_role = 1;
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== $required_role) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['user_id']) || !isset($input['is_active'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$user_id = $input['user_id'];
$is_active = (bool)$input['is_active'];

// Validate user_id is not empty and is a valid UUID format
if (empty($user_id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $user_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID format']);
    exit();
}

try {
    // Update user status in Supabase
    $endpoint = "users?user_id=eq.$user_id";
    $data = ['is_active' => $is_active];
    
    $response = supabaseRequest($endpoint, 'PATCH', $data);
    
    if (isset($response['data']) && !empty($response['data'])) {
        $action = $is_active ? 'activated' : 'deactivated';
        echo json_encode([
            'success' => true, 
            'message' => "User has been $action successfully",
            'user_id' => $user_id,
            'is_active' => $is_active
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
    }
    
} catch (Exception $e) {
    error_log("Error updating user status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
