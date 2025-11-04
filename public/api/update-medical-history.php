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
$donorId = $input['donor_id'] ?? null;
$needsReview = $input['needs_review'] ?? null;
$medicalApproval = $input['medical_approval'] ?? null; // optional

// Log the received data for debugging
error_log('Medical history update request received: ' . json_encode($input));
error_log('Extracted medical_history_id: ' . $medicalHistoryId);
error_log('Extracted donor_id: ' . $donorId);
error_log('Extracted needs_review: ' . ($needsReview !== null ? ($needsReview ? 'true' : 'false') : 'null'));

// Accept either medical_history_id or donor_id
$identifier = $medicalHistoryId ?: $donorId;
$identifierField = $medicalHistoryId ? 'medical_history_id' : 'donor_id';

if (!$identifier) {
    error_log('Missing required field - identifier missing');
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Missing required field: medical_history_id or donor_id'
    ]);
    exit;
}

try {
    // Prepare data for update - include all fields that might be sent
    $updateData = [
        'updated_at' => date('c')
    ];
    
    // Add optional fields if provided
    if ($needsReview !== null) {
        $updateData['needs_review'] = (bool)$needsReview;
    }
    if ($medicalApproval !== null) {
        $updateData['medical_approval'] = $medicalApproval;
    }
    if (isset($input['decline_reason'])) {
        $updateData['decline_reason'] = $input['decline_reason'];
    }
    if (isset($input['decline_date'])) {
        $updateData['decline_date'] = $input['decline_date'];
    }
    if (isset($input['restriction_type'])) {
        $updateData['restriction_type'] = $input['restriction_type'];
    }
    if (isset($input['deferral_duration'])) {
        $updateData['deferral_duration'] = $input['deferral_duration'];
    }
    if (isset($input['deferral_end_date'])) {
        $updateData['deferral_end_date'] = $input['deferral_end_date'];
    }
    
    // Log the data being updated
    error_log('Updating medical history record with data: ' . json_encode($updateData));
    
    // Prepare cURL request to Supabase
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/rest/v1/medical_history?' . $identifierField . '=eq.' . urlencode($identifier));
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
        // Invalidate cache to ensure status updates immediately (if function exists)
        try {
            // Try to invalidate cache if the function exists
            if (function_exists('invalidateCache')) {
                invalidateCache();
                error_log("Medical History Update API - Cache invalidated for donor: " . ($donorId ?: $identifier));
            }
        } catch (Exception $cache_error) {
            // Cache invalidation is optional, don't fail if it errors
            error_log("Medical History Update API - Cache invalidation error: " . $cache_error->getMessage());
        }

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
