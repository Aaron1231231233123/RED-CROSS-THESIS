<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get donor_id from query parameters
$donor_id = $_GET['donor_id'] ?? null;

if (!$donor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing donor_id parameter']);
    exit;
}

try {
    // Include database connection
    require_once '../../assets/conn/db_conn.php';
    
    // Query the donor_form table for the donor using Supabase cURL (same pattern as get_donor_info.php)
    $url = SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception('Failed to fetch donor form data');
    }
    
    $result = json_decode($response, true);
    
    if (!empty($result) && is_array($result) && isset($result[0])) {
        $donorData = $result[0];
        echo json_encode([
            'success' => true,
            'donor_form' => [
                'registration_channel' => $donorData['registration_channel'] ?? 'PRC Portal'
            ]
        ]);
    } else {
        // Return default values if no donor form found
        $defaultData = [
            'registration_channel' => 'PRC Portal'
        ];
        
        echo json_encode([
            'success' => true,
            'donor_form' => $defaultData
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error fetching donor form data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
