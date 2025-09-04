<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
$required_fields = ['physical_exam_id', 'remarks', 'needs_review'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Include database connection
    require_once '../../assets/conn/db_conn.php';
    
    $physical_exam_id = $input['physical_exam_id'];
    $remarks = $input['remarks'];
    $needs_review = $input['needs_review'];
    
    // Log the update attempt
    error_log("Updating physical_examination - ID: $physical_exam_id, Remarks: $remarks, Needs Review: " . ($needs_review ? 'true' : 'false'));
    
    // Prepare the update data
    $updateData = [
        'remarks' => $remarks,
        'needs_review' => $needs_review,
        'updated_at' => date('c')
    ];
    
    // Convert to JSON for Supabase
    $jsonData = json_encode($updateData);
    
    // Update the physical_examination record using Supabase cURL
    $url = SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . urlencode($physical_exam_id);
    
    error_log("Physical examination update URL: " . $url);
    error_log("Physical examination update data: " . $jsonData);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Physical examination update response: " . $response);
    error_log("Physical examination update HTTP code: " . $httpCode);
    
    if ($response === false) {
        throw new Exception('Failed to update physical examination record');
    }
    
    // Check if the update was successful
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode([
            'success' => true,
            'message' => 'Physical examination updated successfully',
            'data' => [
                'physical_exam_id' => $physical_exam_id,
                'remarks' => $remarks,
                'needs_review' => $needs_review
            ]
        ]);
    } else {
        throw new Exception("Update failed with HTTP code: $httpCode");
    }
    
} catch (Exception $e) {
    error_log("Error updating physical examination: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
