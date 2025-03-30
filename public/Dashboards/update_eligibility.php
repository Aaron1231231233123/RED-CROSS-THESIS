<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Function to update eligibility record
function updateEligibilityRecord($eligibilityId, $data) {
    // Convert "true"/"false" strings to boolean values for database
    if (isset($data['collection_successful'])) {
        $data['collection_successful'] = ($data['collection_successful'] === 'true');
    }
    
    // Remove donor info fields that shouldn't be included in eligibility update
    unset($data['surname']);
    unset($data['first_name']);
    unset($data['middle_name']);
    unset($data['donor_id']);
    
    // Add updated_at timestamp
    $data['updated_at'] = date('c'); // ISO 8601 format
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?eligibility_id=eq." . $eligibilityId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ],
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ["success" => false, "error" => "cURL Error #:" . $err];
    } else {
        // Check if update was successful (HTTP 204 No Content)
        if ($httpCode == 204) {
            return ["success" => true];
        } else {
            return ["success" => false, "error" => "Failed to update record. HTTP Code: " . $httpCode . ", Response: " . $response];
        }
    }
}

// Get JSON data from POST request
$inputJSON = file_get_contents('php://input');
$inputData = json_decode($inputJSON, true);

// Validate required fields
if (!isset($inputData['eligibility_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing eligibility_id parameter']);
    exit;
}

// Update the record
$result = updateEligibilityRecord($inputData['eligibility_id'], $inputData);

// Return the result
header('Content-Type: application/json');
echo json_encode($result); 