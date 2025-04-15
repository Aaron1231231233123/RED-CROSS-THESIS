<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['donor_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing donor ID']);
    exit;
}

$donor_id = $_GET['donor_id'];
$salt = "RedCross2024"; // Same salt as in dashboard file
$hash = hash('sha256', $donor_id . $salt);

// Store the mapping in session for verification
if (!isset($_SESSION['donor_hashes'])) {
    $_SESSION['donor_hashes'] = [];
}
$_SESSION['donor_hashes'][$hash] = $donor_id;

echo json_encode(['success' => true, 'hash' => $hash]); 