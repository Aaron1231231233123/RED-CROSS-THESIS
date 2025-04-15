<?php
session_start();
require_once '../conn/db_conn.php';

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

// Generate a secure token
$random = bin2hex(random_bytes(16));
$timestamp = time();
$token = hash('sha256', $donor_id . $random . $timestamp);

// Store the token mapping in the session
if (!isset($_SESSION['donor_tokens'])) {
    $_SESSION['donor_tokens'] = [];
}

// Clean up expired tokens
foreach ($_SESSION['donor_tokens'] as $key => $value) {
    if ($value['expires'] < time()) {
        unset($_SESSION['donor_tokens'][$key]);
    }
}

// Store new token
$_SESSION['donor_tokens'][$token] = [
    'donor_id' => $donor_id,
    'expires' => time() + 3600 // Token expires in 1 hour
];

echo json_encode(['success' => true, 'token' => $token]); 