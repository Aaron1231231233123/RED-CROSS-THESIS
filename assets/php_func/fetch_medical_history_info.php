<?php
// Fetch Medical History Information API
// Returns medical history information for a specific donor

// Disable error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Include database connection
require_once '../conn/db_conn.php';

// Set JSON header
header('Content-Type: application/json');

// Get donor_id from query parameters
$donor_id = isset($_GET['donor_id']) ? intval($_GET['donor_id']) : null;

if (!$donor_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing donor_id parameter'
    ]);
    exit;
}

try {
    // Get medical history data by donor_id
    $medical_curl = curl_init();
    curl_setopt_array($medical_curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/medical_history?donor_id=eq." . $donor_id . "&select=medical_history_id,medical_approval,needs_review,created_at,updated_at&order=created_at.desc&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ]
    ]);
    
    $medical_response = curl_exec($medical_curl);
    $medical_err = curl_error($medical_curl);
    curl_close($medical_curl);
    
    if ($medical_err) {
        error_log("Error fetching medical history info: " . $medical_err);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch medical history data'
        ]);
        exit;
    }
    
    $medical_data = json_decode($medical_response, true);
    $medical_info = !empty($medical_data) ? $medical_data[0] : null;
    
    if (!$medical_info) {
        echo json_encode([
            'success' => false,
            'error' => 'No medical history record found for this donor'
        ]);
        exit;
    }
    
    // Return the medical history information
    echo json_encode([
        'success' => true,
        'data' => $medical_info
    ]);
    
} catch (Exception $e) {
    error_log("Exception in fetch_medical_history_info.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>
