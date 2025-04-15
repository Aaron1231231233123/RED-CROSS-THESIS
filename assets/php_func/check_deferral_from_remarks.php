<?php
require_once '../conn/db_conn.php';

// Simple function to check if a donor is deferred based on physical examination remarks
function isDonorDeferredByRemarks($donorId) {
    // Get the most recent physical examination record for this donor
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $donorId . "&select=remarks&order=created_at.desc&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        error_log("Error checking physical examination remarks: " . $err);
        return false;
    }
    
    $physical_exam = json_decode($response, true);
    
    // Check if there are any physical examination records and if the remarks indicate deferral
    if (!empty($physical_exam) && isset($physical_exam[0]['remarks'])) {
        $remarks = $physical_exam[0]['remarks'];
        error_log("Checking donor $donorId physical exam remarks: $remarks");
        
        // Check if remarks indicate deferral
        return in_array($remarks, ['Temporarily Deferred', 'Permanently Deferred', 'Refused']);
    }
    
    return false;
}

// Handle direct calls to this script with donor_id parameter
if (isset($_GET['donor_id'])) {
    $donorId = $_GET['donor_id'];
    $isDeferred = isDonorDeferredByRemarks($donorId);
    
    header('Content-Type: application/json');
    echo json_encode([
        'donor_id' => $donorId,
        'is_deferred' => $isDeferred,
        'checked_by' => 'remarks_field'
    ]);
}
?> 