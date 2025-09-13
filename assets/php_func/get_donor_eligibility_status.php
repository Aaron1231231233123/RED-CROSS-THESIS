<?php
session_start();
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$donor_id = isset($_GET['donor_id']) ? $_GET['donor_id'] : null;

if (!$donor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing donor_id']);
    exit();
}

try {
    // Get the latest eligibility record for the donor
    $ch = curl_init();
    $url = SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1';
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Failed to fetch eligibility data');
    }
    
    $data = json_decode($response, true);
    
    if (empty($data)) {
        // For new donors, return success but with status 'new'
        echo json_encode([
            'success' => true, // Changed to true so frontend can handle it properly
            'data' => [
                'status' => 'new',
                'message' => 'New donor - no eligibility check needed'
            ]
        ]);
        exit();
    }
    
    $eligibility = $data[0];
    $result = [
        'success' => true,
        'data' => [
            'status' => $eligibility['status'],
            'temporary_deferred' => $eligibility['temporary_deferred'],
            'start_date' => $eligibility['start_date'],
            'end_date' => $eligibility['end_date'],
            'disapproval_reason' => $eligibility['disapproval_reason']
        ]
    ];
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
