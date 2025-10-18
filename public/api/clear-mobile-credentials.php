<?php
/**
 * Clear Mobile Credentials API
 * 
 * This endpoint clears mobile credentials from the session
 * after the admin has viewed them.
 */

header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action']) || $input['action'] !== 'clear_credentials') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit();
    }
    
    // Clear mobile credentials from session
    unset($_SESSION['mobile_account_generated']);
    unset($_SESSION['mobile_credentials']);
    unset($_SESSION['show_credentials_modal']);
    unset($_SESSION['post_registration_redirect']);
    
    // Log the action
    error_log("Mobile credentials cleared from session");
    
    echo json_encode(['success' => true, 'message' => 'Credentials cleared successfully']);
    
} catch (Exception $e) {
    error_log("Error clearing mobile credentials: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>
