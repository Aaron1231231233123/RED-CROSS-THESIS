<?php
header('Content-Type: application/json');
require_once '../conn/db_conn.php';

// Support both screening_id and donor_id parameters
$screening_id = $_GET['screening_id'] ?? null;
$donor_id = $_GET['donor_id'] ?? null;

if (!$screening_id && !$donor_id) {
    echo json_encode(['success' => false, 'message' => 'Either screening_id or donor_id is required']);
    exit();
}

try {
    // Build the query based on available parameter
    $query_url = SUPABASE_URL . '/rest/v1/screening_form?select=*';
    
    if ($screening_id) {
        $query_url .= '&screening_id=eq.' . $screening_id;
    } else {
        $query_url .= '&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1';
    }
    
    // Fetch screening form data
    $ch = curl_init($query_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0])) {
            echo json_encode([
                'success' => true,
                'data' => $data[0]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Screening not found']);
        }
    } else {
        throw new Exception('Failed to fetch screening data. HTTP Code: ' . $http_code);
    }

} catch (Exception $e) {
    error_log('Error in get_screening_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 