<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

/**
 * Function to fetch successfully completed donations data from Supabase
 * These are successfully completed donations (from approved applicants or walk-ins)
 */
function fetchDonatedDonations() {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?status=eq.donated&collection_successful=eq.true&select=eligibility_id,donor_id,blood_type,donation_type,start_date,status,blood_collection_id&order=created_at.desc",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json",
            "Prefer: count=exact"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ["error" => "cURL Error #:" . $err];
    } else {
        $eligibilityData = json_decode($response, true);
        
        // For each eligibility record, fetch related donor and blood collection data
        foreach ($eligibilityData as $key => $eligibility) {
            // Get donor information
            $donorData = fetchDonorInfo($eligibility['donor_id']);
            if ($donorData) {
                $eligibilityData[$key]['donor_data'] = $donorData;
            }
            
            // Get blood collection data if available
            if (!empty($eligibility['blood_collection_id'])) {
                $bloodCollectionData = fetchBloodCollectionData($eligibility['blood_collection_id']);
                if ($bloodCollectionData) {
                    $eligibilityData[$key]['blood_collection_data'] = $bloodCollectionData;
                }
            }
        }
        
        return $eligibilityData;
    }
}

/**
 * Function to fetch donor information by donor_id
 */
function fetchDonorInfo($donorId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId . "&select=donor_id,surname,first_name,middle_name,birthdate,age,sex",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return null;
    } else {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
}

/**
 * Function to fetch blood collection data by blood_collection_id
 */
function fetchBloodCollectionData($bloodCollectionId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/blood_collection?blood_collection_id=eq." . $bloodCollectionId . "&select=*",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return null;
    } else {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
}

// Fetch all completed donations
$donatedDonations = fetchDonatedDonations();

// Check for errors
$error = null;
if (isset($donatedDonations['error'])) {
    $error = $donatedDonations['error'];
}
?> 