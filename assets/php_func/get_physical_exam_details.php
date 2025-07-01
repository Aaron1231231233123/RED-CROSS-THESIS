<?php
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

if (!isset($_GET['physical_exam_id']) || empty($_GET['physical_exam_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Physical exam ID is required'
    ]);
    exit;
}

$physical_exam_id = $_GET['physical_exam_id'];

try {
    // Fetch physical examination details
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . urlencode($physical_exam_id) . '&select=*',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception('Failed to fetch physical examination data');
    }

    $physical_exam_data = json_decode($response, true);

    if (empty($physical_exam_data)) {
        echo json_encode([
            'success' => false,
            'error' => 'Physical examination not found'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => $physical_exam_data[0]
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 