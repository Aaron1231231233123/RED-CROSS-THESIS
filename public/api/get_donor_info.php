<?php
require_once '../../assets/conn/db_conn.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['donor_id'])) {
        throw new Exception('Donor ID is required');
    }

    $donor_id = $_GET['donor_id'];

    // Get donor information from Supabase
    $donor_url = SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . urlencode($donor_id);
    $ch = curl_init($donor_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    $donor_response = curl_exec($ch);
    curl_close($ch);

    if ($donor_response === false) {
        throw new Exception('Failed to fetch donor information');
    }

    $donor_data = json_decode($donor_response, true);
    if (empty($donor_data)) {
        throw new Exception('Donor not found');
    }
    $donor = $donor_data[0]; // Get first donor record

    // Get eligibility information from Supabase
    $eligibility_url = SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1';
    $ch = curl_init($eligibility_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    $eligibility_response = curl_exec($ch);
    curl_close($ch);

    if ($eligibility_response === false) {
        throw new Exception('Failed to fetch eligibility information');
    }

    $eligibility_data = json_decode($eligibility_response, true);
    if (empty($eligibility_data)) {
        throw new Exception('Eligibility record not found');
    }
    $eligibility = $eligibility_data[0]; // Get first eligibility record

    // Calculate age if birthdate is available
    if (!empty($donor['birthdate'])) {
        $birthDate = new DateTime($donor['birthdate']);
        $today = new DateTime();
        $donor['age'] = $birthDate->diff($today)->y;
    }

    echo json_encode([
        'success' => true,
        'donor' => $donor,
        'eligibility' => $eligibility
    ]);

} catch (Exception $e) {
    error_log('Error in get_donor_info.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 