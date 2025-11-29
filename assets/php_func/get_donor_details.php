<?php
header('Content-Type: application/json');
require_once '../conn/db_conn.php';

if (!isset($_GET['donor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
    exit();
}

$donor_id = $_GET['donor_id'];

try {
    // Fetch donor form data
    $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=*&donor_id=eq.' . $donor_id);
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
            echo json_encode(['success' => false, 'message' => 'Donor not found']);
        }
    } else {
        throw new Exception('Failed to fetch donor data. HTTP Code: ' . $http_code);
    }

} catch (Exception $e) {
    error_log('Error in get_donor_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 