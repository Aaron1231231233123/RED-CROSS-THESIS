<?php
require_once '../conn/db_conn.php';

// Function to fetch physical examination information for a donor
function fetchPhysicalExaminationInfo($donorId) {
    // First, get donor information including prc_donor_number
    $donor_curl = curl_init();
    curl_setopt_array($donor_curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId . "&select=donor_id,surname,first_name,middle_name,prc_donor_number,birthdate,sex&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ]
    ]);
    
    $donor_response = curl_exec($donor_curl);
    $donor_err = curl_error($donor_curl);
    curl_close($donor_curl);
    
    if ($donor_err) {
        error_log("Error fetching donor info: " . $donor_err);
        return null;
    }
    
    $donor_data = json_decode($donor_response, true);
    $donor_info = !empty($donor_data) ? $donor_data[0] : null;
    
    // Then, get screening form data
    $screening_curl = curl_init();
    curl_setopt_array($screening_curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donorId . "&select=*&order=created_at.desc&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ]
    ]);
    
    $screening_response = curl_exec($screening_curl);
    $screening_err = curl_error($screening_curl);
    curl_close($screening_curl);
    
    if ($screening_err) {
        error_log("Error fetching screening info: " . $screening_err);
        return null;
    }
    
    $screening_data = json_decode($screening_response, true);
    $screening_info = !empty($screening_data) ? $screening_data[0] : null;
    
    // Then, get physical examination data
    $physical_curl = curl_init();
    curl_setopt_array($physical_curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $donorId . "&select=physical_exam_id,donor_id,blood_pressure,pulse_rate,body_temp,gen_appearance,skin,heent,heart_and_lungs,remarks,reason,blood_bag_type,status,created_at,updated_at,disapproval_reason,needs_review,physician,screening_id&order=created_at.desc&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ]
    ]);
    
    $physical_response = curl_exec($physical_curl);
    $physical_err = curl_error($physical_curl);
    curl_close($physical_curl);
    
    if ($physical_err) {
        error_log("Error fetching physical examination info: " . $physical_err);
        return null;
    }
    
    $physical_exam = json_decode($physical_response, true);
    $physical_info = !empty($physical_exam) ? $physical_exam[0] : null;
    
    // Combine all data
    $combined_data = [];
    if ($donor_info) {
        $combined_data = array_merge($combined_data, $donor_info);
    }
    if ($screening_info) {
        $combined_data = array_merge($combined_data, $screening_info);
    }
    if ($physical_info) {
        $combined_data = array_merge($combined_data, $physical_info);
    }
    
    return !empty($combined_data) ? $combined_data : null;
}

// Handle direct calls to this script with donor_id parameter
if (isset($_GET['donor_id'])) {
    $donorId = $_GET['donor_id'];
    $physicalExamInfo = fetchPhysicalExaminationInfo($donorId);
    
    header('Content-Type: application/json');
    if ($physicalExamInfo) {
        echo json_encode([
            'success' => true,
            'data' => $physicalExamInfo
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No physical examination record found for this donor'
        ]);
    }
}
?>
