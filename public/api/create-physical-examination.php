<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once '../../assets/conn/db_conn.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$donorId = $input['donor_id'] ?? null;
$remarks = $input['remarks'] ?? null;
$needsReview = $input['needs_review'] ?? null;

if (!$donorId || !$remarks || !isset($needsReview)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Missing required fields: donor_id, remarks, needs_review'
    ]);
    exit;
}

try {
    // Prepare data for insertion
    $insertData = [
        'donor_id' => (int)$donorId,
        'remarks' => $remarks,
        'needs_review' => (bool)$needsReview,
        'created_at' => date('c'),
        'updated_at' => date('c')
    ];
    
    // Log the data being inserted
    error_log('Creating physical examination record with data: ' . json_encode($insertData));
    
    // Prepare cURL request to Supabase
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/rest/v1/physical_examination');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($insertData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Prefer: return=minimal'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('cURL error: ' . $error);
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode([
            'success' => true,
            'message' => 'Physical examination record created successfully',
            'data' => $insertData
        ]);
    } else {
        throw new Exception('Supabase error: HTTP ' . $httpCode . ' - ' . $response);
    }
    
} catch (Exception $e) {
    error_log('Error creating physical examination record: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create physical examination record: ' . $e->getMessage()
    ]);
}
?>
