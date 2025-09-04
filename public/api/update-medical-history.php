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

$medicalHistoryId = $input['medical_history_id'] ?? null;
$needsReview = $input['needs_review'] ?? null;

// Log the received data for debugging
error_log('Medical history update request received: ' . json_encode($input));
error_log('Extracted medical_history_id: ' . $medicalHistoryId);
error_log('Extracted needs_review: ' . ($needsReview ? 'true' : 'false'));

if (!$medicalHistoryId || !isset($needsReview)) {
    error_log('Missing required fields - medical_history_id: ' . ($medicalHistoryId ? 'present' : 'missing') . ', needs_review: ' . (isset($needsReview) ? 'present' : 'missing'));
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Missing required fields: medical_history_id, needs_review'
    ]);
    exit;
}

try {
    // Prepare data for update
    $updateData = [
        'needs_review' => (bool)$needsReview,
        'updated_at' => date('c')
    ];
    
    // Log the data being updated
    error_log('Updating medical history record with data: ' . json_encode($updateData));
    
    // Prepare cURL request to Supabase
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/rest/v1/medical_history?medical_history_id=eq.' . urlencode($medicalHistoryId));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
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
            'message' => 'Medical history record updated successfully',
            'data' => $updateData
        ]);
    } else {
        error_log('Supabase error response: ' . $response);
        throw new Exception('Supabase error: HTTP ' . $httpCode . ' - ' . $response);
    }
    
} catch (Exception $e) {
    error_log('Error updating medical history record: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update medical history record: ' . $e->getMessage()
    ]);
}
?>
