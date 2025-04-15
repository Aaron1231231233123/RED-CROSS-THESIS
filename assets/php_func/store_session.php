<?php
session_start();
require_once '../conn/db_conn.php';

// Set response header
header('Content-Type: application/json');

// Log the request
error_log("store_session.php received: " . print_r($_POST, true));

// Store session data
if (isset($_POST['screening_id'])) {
    $_SESSION['screening_id'] = $_POST['screening_id'];
    error_log("Set screening_id in session: " . $_POST['screening_id']);
}

if (isset($_POST['donor_id'])) {
    $_SESSION['donor_id'] = $_POST['donor_id'];
    error_log("Set donor_id in session: " . $_POST['donor_id']);
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Session variables set successfully',
    'session_data' => [
        'screening_id' => $_SESSION['screening_id'] ?? null,
        'donor_id' => $_SESSION['donor_id'] ?? null
    ]
]); 