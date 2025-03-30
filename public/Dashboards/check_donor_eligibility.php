<?php
require_once '../../assets/conn/db_conn.php';

header('Content-Type: application/json');

if (!isset($_GET['donor_id'])) {
    echo json_encode(['error' => 'Donor ID is required']);
    exit;
}

$donorId = $_GET['donor_id'];

// Call the PostgreSQL function get_eligibility_status directly
$curl = curl_init();
$query = "SELECT * FROM get_eligibility_status(" . intval($donorId) . ")";

curl_setopt_array($curl, [
    CURLOPT_URL => SUPABASE_URL . "/rest/v1/rpc/get_eligibility_status",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Content-Type: application/json"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        "p_donor_id" => intval($donorId)
    ])
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo json_encode(['error' => "cURL Error #:" . $err]);
    exit;
}

$data = json_decode($response, true);

if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Invalid response from server: ' . json_last_error_msg()]);
    exit;
}

// Return the eligibility status
echo json_encode([
    'is_eligible' => $data['is_eligible'] ?? false,
    'status_message' => $data['status_message'] ?? 'Unknown status',
    'remaining_days' => $data['remaining_days'] ?? 0,
    'end_date' => $data['end_date'] ?? null
]);
?> 