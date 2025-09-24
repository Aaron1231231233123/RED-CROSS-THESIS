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
    
    // Query the physical_examination table for the donor using Supabase cURL
    $url = SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . urlencode($donor_id) . '&order=updated_at.desc,created_at.desc&limit=1';
    
    // Log the URL being queried
    error_log("Physical examination query URL: " . $url);
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
        throw new Exception('Failed to fetch physical examination data');
    }
    
    $result = json_decode($response, true);
    
    // Log the response for debugging
    error_log("Physical examination API response for donor_id $donor_id: " . json_encode($result));
    
    if (!empty($result)) {
        $physicalData = $result[0];
        echo json_encode([
            'success' => true,
            'physical_exam' => [
                'physical_exam_id' => $physicalData['physical_exam_id'] ?? null,
                'blood_pressure' => $physicalData['blood_pressure'] ?? null,
                'pulse_rate' => $physicalData['pulse_rate'] ?? null,
                'body_temp' => $physicalData['body_temp'] ?? null,
                'gen_appearance' => $physicalData['gen_appearance'] ?? null,
                'skin' => $physicalData['skin'] ?? null,
                'heent' => $physicalData['heent'] ?? null,
                'heart_and_lungs' => $physicalData['heart_and_lungs'] ?? null,
                'body_weight' => $physicalData['body_weight'] ?? null,
                'remarks' => $physicalData['remarks'] ?? null,
                'reason' => $physicalData['reason'] ?? null,
                'blood_bag_type' => $physicalData['blood_bag_type'] ?? null,
                'disapproval_reason' => $physicalData['disapproval_reason'] ?? null,
                'needs_review' => $physicalData['needs_review'] ?? null,
                'physician' => $physicalData['physician'] ?? null,
                'screening_id' => $physicalData['screening_id'] ?? null,
                // Do not expose legacy status; use remarks only
            ]
        ]);
    } else {
        // Return default values if no physical examination found
        $defaultData = [
            'physical_exam_id' => null,
            'blood_pressure' => null,
            'pulse_rate' => null,
            'body_temp' => null,
            'gen_appearance' => null,
            'skin' => null,
            'heent' => null,
            'heart_and_lungs' => null,
            'body_weight' => null,
            'remarks' => null,
            'reason' => null,
            'blood_bag_type' => null,
            'disapproval_reason' => null,
            'needs_review' => null,
            'physician' => null,
            'screening_id' => null,
            //'status' => null
        ];
        
        echo json_encode([
            'success' => true,
            'physical_exam' => $defaultData
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error fetching physical examination data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
