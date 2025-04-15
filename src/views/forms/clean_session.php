<?php
// Start session
session_start();

// Set headers for JSON response
header('Content-Type: application/json');

try {
    // Log the session cleanup
    error_log("Cleaning donor registration session data before new registration");
    
    // Clear all registration-related session data
    if (isset($_SESSION['donor_form_data'])) {
        unset($_SESSION['donor_form_data']);
    }
    
    if (isset($_SESSION['donor_form_timestamp'])) {
        unset($_SESSION['donor_form_timestamp']);
    }
    
    if (isset($_SESSION['donor_id'])) {
        unset($_SESSION['donor_id']);
    }
    
    if (isset($_SESSION['medical_history_id'])) {
        unset($_SESSION['medical_history_id']);
    }
    
    if (isset($_SESSION['screening_id'])) {
        unset($_SESSION['screening_id']);
    }
    
    // Clear any other donor-related session data
    if (isset($_SESSION['donor_registered'])) {
        unset($_SESSION['donor_registered']);
    }
    
    if (isset($_SESSION['donor_registered_id'])) {
        unset($_SESSION['donor_registered_id']);
    }
    
    if (isset($_SESSION['donor_registered_name'])) {
        unset($_SESSION['donor_registered_name']);
    }
    
    if (isset($_SESSION['declaration_completed'])) {
        unset($_SESSION['declaration_completed']);
    }
    
    // Log the cleaned state
    error_log("Registration session data cleared. Session now contains keys: " . 
             implode(', ', array_keys($_SESSION)));
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Session data cleaned successfully'
    ]);
} catch (Exception $e) {
    error_log("Error cleaning session data: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => 'Failed to clean session data: ' . $e->getMessage()
    ]);
}
?>
