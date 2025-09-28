<?php
// Lightweight endpoint to fetch the latest medical_history row by donor_id
// Response: { success: true, data: { medical_approval, needs_review, created_at, updated_at, medical_history_id, donor_id } }

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once '../conn/db_conn.php';

try {
    // Require donor_id
    $donor_id = isset($_GET['donor_id']) ? trim($_GET['donor_id']) : '';
    if ($donor_id === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing donor_id parameter'
        ]);
        exit;
    }

    // Build Supabase REST request: latest medical_history by donor_id
    $url = SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . urlencode($donor_id)
         . '&select=medical_history_id,donor_id,medical_approval,needs_review,created_at,updated_at'
         . '&order=updated_at.desc,created_at.desc&limit=1';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'cURL error: ' . $err
        ]);
        exit;
    }

    if ($http < 200 || $http >= 300) {
        http_response_code($http);
        echo json_encode([
            'success' => false,
            'error' => 'HTTP error ' . $http,
            'raw' => $response
        ]);
        exit;
    }

    $data = json_decode($response, true);
    $row = (!empty($data) && isset($data[0])) ? $data[0] : null;

    echo json_encode([
        'success' => true,
        'data' => $row
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>


