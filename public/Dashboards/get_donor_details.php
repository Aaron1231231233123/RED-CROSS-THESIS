<?php
require_once '../../assets/conn/db_conn.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Donor ID is required']);
    exit;
}

$donorId = $_GET['id'];

// Initialize cURL session
$ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?id=eq.' . urlencode($donorId));

// Set headers
$headers = [
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY
];

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check if request was successful
if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (!empty($data)) {
        echo json_encode($data[0]);
    } else {
        echo json_encode(['error' => 'Donor not found']);
    }
} else {
    echo json_encode(['error' => 'Failed to fetch donor details']);
} 