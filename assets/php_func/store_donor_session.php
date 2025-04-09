<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the received request
$raw_data = file_get_contents('php://input');
error_log("Received data in store_donor_session.php: " . $raw_data);

// Get the JSON data from the request
$data = json_decode($raw_data, true);

// Log the decoded data
error_log("Decoded data: " . print_r($data, true));

if (isset($data['donor_id'])) {
    // Store the donor_id in the session
    $_SESSION['donor_id'] = $data['donor_id'];
    error_log("Successfully stored donor_id in session: " . $data['donor_id']);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'donor_id' => $data['donor_id']]);
} else {
    error_log("Error: No donor_id provided in the request");
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No donor_id provided']);
} 