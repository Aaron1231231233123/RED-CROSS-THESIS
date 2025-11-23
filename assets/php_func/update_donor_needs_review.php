<?php
// update_donor_needs_review.php
// API endpoint to update medical_history needs_review to true for existing donors

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Enable error reporting for debugging (but don't display errors)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once __DIR__ . '/../conn/db_conn.php';
    
    if (empty(SUPABASE_URL) || empty(SUPABASE_API_KEY)) {
        throw new Exception("Supabase configuration is missing");
    }
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed");
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['donor_id'])) {
        throw new Exception("Missing required field: donor_id");
    }
    
    $donor_id = intval($input['donor_id']);
    
    // First, check if medical_history record exists for this donor
    $check_url = SUPABASE_URL . "/rest/v1/medical_history?select=medical_history_id&donor_id=eq." . $donor_id . "&limit=1";
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $check_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $check_response = curl_exec($curl);
    $check_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($check_http_code !== 200) {
        throw new Exception("Failed to check medical history record");
    }
    
    $medical_history_records = json_decode($check_response, true);
    
    if (empty($medical_history_records)) {
        // No medical_history record exists - create one with needs_review = true
        $create_url = SUPABASE_URL . "/rest/v1/medical_history";
        $create_data = [
            'donor_id' => $donor_id,
            'needs_review' => true,
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $create_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($create_data),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json",
                "Prefer: return=representation"
            ],
        ]);
        
        $create_response = curl_exec($curl);
        $create_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($create_http_code >= 200 && $create_http_code < 300) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Medical history record created with needs_review set to true',
                'action' => 'created'
            ]);
        } else {
            throw new Exception("Failed to create medical history record");
        }
    } else {
        // Medical history record exists - update needs_review to true
        $medical_history_id = $medical_history_records[0]['medical_history_id'];
        $update_url = SUPABASE_URL . "/rest/v1/medical_history?medical_history_id=eq." . $medical_history_id;
        
        $update_data = [
            'needs_review' => true,
            'updated_at' => date('c')
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $update_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($update_data),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json",
                "Prefer: return=representation"
            ],
        ]);
        
        $update_response = curl_exec($curl);
        $update_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($update_http_code >= 200 && $update_http_code < 300) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Medical history updated - needs_review set to true',
                'action' => 'updated',
                'medical_history_id' => $medical_history_id
            ]);
        } else {
            throw new Exception("Failed to update medical history record. HTTP: " . $update_http_code);
        }
    }
    
} catch (Exception $e) {
    error_log("Update donor needs_review error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update donor information',
        'error' => $e->getMessage()
    ]);
}
?>

