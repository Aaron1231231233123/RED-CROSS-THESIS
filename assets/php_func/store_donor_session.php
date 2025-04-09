<?php
session_start();

// Get the JSON data from the request
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (isset($data['donor_id'])) {
    // Store the donor_id in the session
    $_SESSION['donor_id'] = $data['donor_id'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'No donor_id provided']);
} 