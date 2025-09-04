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
    
    // Test 1: Get all screening_form records to see what's in the table
    $url1 = SUPABASE_URL . '/rest/v1/screening_form?limit=10&order=created_at.desc';
    error_log("Test 1 - All screening_form records: " . $url1);
    
    $ch1 = curl_init($url1);
    curl_setopt_array($ch1, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $response1 = curl_exec($ch1);
    curl_close($ch1);
    
    $allRecords = json_decode($response1, true);
    
    // Test 2: Try to find by donor_form_id (correct field name)
    $url2 = SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . urlencode($donor_id);
    error_log("Test 2 - By donor_form_id: " . $url2);
    
    $ch2 = curl_init($url2);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $response2 = curl_exec($ch2);
    curl_close($ch2);
    
    $byDonorFormId = json_decode($response2, true);
    
    // Test 3: Try to find by medical_history_id (if we can get it)
    $url3 = SUPABASE_URL . '/rest/v1/medical_history?donor_form_id=eq.' . urlencode($donor_id) . '&limit=1';
    error_log("Test 3 - Medical history for donor_form_id: " . $url3);
    
    $ch3 = curl_init($url3);
    curl_setopt_array($ch3, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $response3 = curl_exec($ch3);
    curl_close($ch3);
    
    $medicalHistory = json_decode($response3, true);
    
    // Test 4: If we found medical_history, try to find screening_form by medical_history_id
    $screeningByMedicalHistory = null;
    if (!empty($medicalHistory) && isset($medicalHistory[0]['medical_history_id'])) {
        $medicalHistoryId = $medicalHistory[0]['medical_history_id'];
        $url4 = SUPABASE_URL . '/rest/v1/screening_form?medical_history_id=eq.' . urlencode($medicalHistoryId);
        error_log("Test 4 - Screening form by medical_history_id: " . $url4);
        
        $ch4 = curl_init($url4);
        curl_setopt_array($ch4, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        
        $response4 = curl_exec($ch4);
        curl_close($ch4);
        
        $screeningByMedicalHistory = json_decode($response4, true);
    }
    
    echo json_encode([
        'success' => true,
        'donor_id' => $donor_id,
        'test_results' => [
            'all_records_sample' => $allRecords,
            'by_donor_form_id' => $byDonorFormId,
            'medical_history' => $medicalHistory,
            'screening_by_medical_history' => $screeningByMedicalHistory
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in test-screening-form: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
