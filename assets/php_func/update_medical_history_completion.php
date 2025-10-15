<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Include database connection
require_once '../conn/db_conn.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Debug logging
    error_log("=== MEDICAL HISTORY COMPLETION UPDATE ===");
    error_log("Raw input: " . file_get_contents('php://input'));
    error_log("Decoded input: " . print_r($input, true));
    
    // Validate required fields
    if (!isset($input['donor_id']) || empty($input['donor_id'])) {
        error_log("ERROR: Donor ID is missing or empty");
        throw new Exception('Donor ID is required');
    }
    
    $donor_id = $input['donor_id'];
    $is_admin = isset($input['is_admin']) ? $input['is_admin'] : true;
    error_log("Processing donor_id: " . $donor_id . ", is_admin: " . ($is_admin ? 'True' : 'False'));
    
    // Update the is_admin column for the medical history record
    $updateUrl = SUPABASE_URL . '/rest/v1/medical_history';
    $updateData = [
        'is_admin' => $is_admin ? 'True' : 'False',  // Use string format for PostgreSQL boolean
        'updated_at' => date('c') // ISO 8601 format
    ];
    
    $fullUrl = $updateUrl . '?donor_id=eq.' . urlencode($donor_id);
    error_log("Update URL: " . $fullUrl);
    error_log("Update data: " . json_encode($updateData));
    error_log("Supabase URL: " . SUPABASE_URL);
    error_log("API Key length: " . strlen(SUPABASE_API_KEY));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
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
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("CURL Response: " . $response);
    error_log("HTTP Code: " . $httpCode);
    error_log("CURL Error: " . $curlError);
    
    if ($curlError) {
        throw new Exception('CURL Error: ' . $curlError);
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        // Success - return success response
        echo json_encode([
            'success' => true,
            'message' => 'Medical history completion status updated successfully',
            'donor_id' => $donor_id,
            'is_admin' => $is_admin
        ]);
    } else {
        // Handle different HTTP error codes
        $errorMessage = 'Failed to update medical history completion status';
        if ($httpCode === 404) {
            $errorMessage = 'Medical history record not found for donor';
        } elseif ($httpCode === 400) {
            $errorMessage = 'Invalid request data';
        } elseif ($httpCode >= 500) {
            $errorMessage = 'Database server error';
        }
        
        throw new Exception($errorMessage . ' (HTTP ' . $httpCode . ')');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
