<?php
// Start session
session_start();

// Set headers for JSON response
header('Content-Type: application/json');

// Get referrer for redirection
$redirect_url = isset($_SESSION['donor_form_referrer']) 
    ? $_SESSION['donor_form_referrer'] 
    : '../../public/Dashboards/dashboard-Inventory-System.php';

try {
    // Log the cancellation
    error_log("Cancelling donor registration process. donor_form_data=" . 
              (isset($_SESSION['donor_form_data']) ? 'present' : 'absent') . 
              ", donor_id=" . ($_SESSION['donor_id'] ?? 'not set'));
    
    // Clear all registration-related session data
    unset($_SESSION['donor_form_data']);
    unset($_SESSION['donor_form_timestamp']);
    unset($_SESSION['donor_id']); // Only clear if not yet inserted into database
    
    // Keep donor_form_referrer for navigation purposes
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Registration cancelled successfully',
        'redirect_url' => $redirect_url
    ]);
} catch (Exception $e) {
    error_log("Error cancelling registration: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => 'Failed to cancel registration: ' . $e->getMessage(),
        'redirect_url' => $redirect_url
    ]);
}
?> 