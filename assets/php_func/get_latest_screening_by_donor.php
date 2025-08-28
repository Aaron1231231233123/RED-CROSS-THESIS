<?php
header('Content-Type: application/json');
require_once '../conn/db_conn.php';

try {
    if (!isset($_GET['donor_id'])) {
        echo json_encode(['success' => false, 'message' => 'donor_id is required']);
        exit;
    }

    $donor_id = intval($_GET['donor_id']);
    if ($donor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid donor_id']);
        exit;
    }

    $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=*&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch data', 'http' => $http_code]);
        exit;
    }

    $rows = json_decode($response, true) ?: [];
    if (!empty($rows)) {
        echo json_encode(['success' => true, 'data' => $rows[0]]);
    } else {
        echo json_encode(['success' => true, 'data' => null]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>




