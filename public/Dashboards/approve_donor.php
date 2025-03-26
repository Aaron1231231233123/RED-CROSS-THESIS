<?php
require_once '../../assets/conn/db_conn.php';

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['donor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
    exit;
}

$donorId = $data['donor_id'];

// Initialize cURL session
$ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?id=eq.' . urlencode($donorId));

// Set headers
$headers = [
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY,
    'Content-Type: application/json',
    'Prefer: return=minimal'
];

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => 'approved']));

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check if request was successful
if ($httpCode === 204) {
    echo json_encode(['success' => true, 'message' => 'Donor approved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to approve donor']);
} 