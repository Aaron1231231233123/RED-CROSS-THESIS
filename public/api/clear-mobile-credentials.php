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
    
    if (!isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit();
    }
    
    // Handle "mark_as_shown" action - just mark credentials as shown without clearing them
    if ($input['action'] === 'mark_as_shown') {
        // Mark as shown in session
        $_SESSION['mobile_credentials_shown'] = true;
        
        // Mark as shown in cookie (expires in 5 minutes to prevent showing on dashboards)
        $shownExpiry = time() + 60*5; // 5 minutes (same as credentials cookie)
        setcookie('mobile_credentials_shown', 'true', $shownExpiry, '/');
        
        error_log("Mobile credentials marked as shown");
        echo json_encode(['success' => true, 'message' => 'Credentials marked as shown']);
        exit();
    }
    
    // Handle "clear_credentials" action - clear everything
    if ($input['action'] !== 'clear_credentials') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit();
    }
    
    // Clear mobile credentials from session
    unset($_SESSION['mobile_account_generated']);
    unset($_SESSION['mobile_credentials']);
    unset($_SESSION['show_credentials_modal']);
    unset($_SESSION['post_registration_redirect']);
    unset($_SESSION['donor_registered_name']);
    unset($_SESSION['mobile_credentials_shown']);
    
    // Also clear cookies (set them to expire immediately)
    $expiry = time() - 3600; // Set to past time to delete
    setcookie('mobile_account_generated', '', $expiry, '/');
    setcookie('mobile_email', '', $expiry, '/');
    setcookie('mobile_password', '', $expiry, '/');
    setcookie('donor_name', '', $expiry, '/');
    setcookie('mobile_credentials_shown', '', $expiry, '/');
    
    // Log the action
    error_log("Mobile credentials cleared from session and cookies");
    
    echo json_encode(['success' => true, 'message' => 'Credentials cleared successfully']);
    
} catch (Exception $e) {
    error_log("Error clearing mobile credentials: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>
