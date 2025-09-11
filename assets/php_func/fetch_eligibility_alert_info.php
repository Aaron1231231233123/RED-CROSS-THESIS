<?php
session_start();
require_once '../conn/db_conn.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get donor_id from request
$donor_id = isset($_GET['donor_id']) ? $_GET['donor_id'] : null;

if (!$donor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing donor_id']);
    exit();
}

try {
    // Fetch latest eligibility data for the donor
    $ch = curl_init();
    $url = SUPABASE_URL . '/rest/v1/eligibility?select=status,temporary_deferred,start_date,created_at,eligibility_id&donor_id=eq.' . $donor_id . '&order=created_at.desc,eligibility_id.desc&limit=1';
    
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
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        
        if (is_array($data) && !empty($data)) {
            $eligibility_record = $data[0];
            
            // Debug log
            error_log("Eligibility Record: " . print_r($eligibility_record, true));
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'status' => $eligibility_record['status'],
                    'temporary_deferred' => $eligibility_record['temporary_deferred'],
                    'start_date' => $eligibility_record['start_date'],
                    'created_at' => $eligibility_record['created_at'],
                    'eligibility_id' => $eligibility_record['eligibility_id']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No eligibility record found'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch eligibility data',
            'http_code' => $http_code
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
