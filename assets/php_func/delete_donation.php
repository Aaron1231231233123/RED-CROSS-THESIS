<?php
// Include database connection
include_once '../conn/db_conn.php';

// Check if eligibility_id is provided
if (!isset($_GET['eligibility_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing eligibility_id parameter']);
    exit;
}

$eligibility_id = $_GET['eligibility_id'];

// Function to delete eligibility record
function deleteEligibilityRecord($eligibilityId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?eligibility_id=eq." . $eligibilityId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "DELETE",
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ["success" => false, "error" => "cURL Error #:" . $err];
    } else {
        // Check if deletion was successful (HTTP 204 No Content)
        if ($httpCode == 204) {
            return ["success" => true];
        } else {
            return ["success" => false, "error" => "Failed to delete record. HTTP Code: " . $httpCode];
        }
    }
}

// Delete the record
$result = deleteEligibilityRecord($eligibility_id);

// Return the result
echo json_encode($result); 