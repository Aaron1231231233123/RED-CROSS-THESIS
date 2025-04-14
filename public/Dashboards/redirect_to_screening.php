<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Debug logging
error_log("redirect_to_screening.php called");

// Get data from POST or GET
$screening_id = $_REQUEST['screening_id'] ?? null;
$donor_id = $_REQUEST['donor_id'] ?? null;

// Debug log values
error_log("Received screening_id: $screening_id");
error_log("Received donor_id: $donor_id");

// Set session variables
if ($screening_id) {
    $_SESSION['screening_id'] = $screening_id;
}

if ($donor_id) {
    $_SESSION['donor_id'] = $donor_id;
}

// Debug log session after setting
error_log("Session after setting variables: " . print_r($_SESSION, true));

// Redirect to screening form
header("Location: ../../src/views/forms/screening-form.php");
exit(); 