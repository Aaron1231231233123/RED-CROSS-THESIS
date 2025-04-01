<?php
// Start session if not already started
session_start();

// Include database connection
include_once '../../assets/conn/db_conn.php';

// Set header to return JSON
header('Content-Type: application/json');

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);

// Check if there's a donor_id in the request
if (isset($data['donor_id']) && !empty($data['donor_id'])) {
    // Store the donor_id in the session
    $_SESSION['donor_id'] = $data['donor_id'];
    
    // Check if this is for processing or viewing/editing
    if (isset($data['view_mode']) && $data['view_mode'] === true) {
        $_SESSION['view_mode'] = true;
        error_log("Session updated: donor_id set to {$_SESSION['donor_id']}, view_mode flag set to true");
    } else {
        $_SESSION['admin_processing'] = true; // Flag to indicate admin is processing
        error_log("Session updated: donor_id set to {$_SESSION['donor_id']}, admin_processing flag set to true");
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Donor ID stored in session',
        'donor_id' => $_SESSION['donor_id'],
        'mode' => isset($_SESSION['view_mode']) ? 'view' : 'process'
    ]);
} else {
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => 'No donor ID provided'
    ]);
}
?> 