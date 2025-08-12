<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include_once '../../assets/conn/db_conn.php';

$approvedDonations = [];
$error = null;

try {
    // Get all eligibility records
    $eligibilityCurl = curl_init();
    curl_setopt_array($eligibilityCurl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?select=*&order=created_at.desc",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $eligibilityResponse = curl_exec($eligibilityCurl);
    curl_close($eligibilityCurl);
    
    if ($eligibilityResponse === false) {
        throw new Exception("Failed to fetch eligibility data");
    }
    
    $eligibilityData = json_decode($eligibilityResponse, true);
    if (!is_array($eligibilityData)) {
        throw new Exception("Invalid eligibility data format");
    }
    
    // Track processed donor IDs to avoid duplicates
    $processedDonorIds = [];
    
    // Process each eligibility record
    foreach ($eligibilityData as $eligibility) {
        $donorId = $eligibility['donor_id'] ?? null;
        if (!$donorId) continue;
        
        // Skip if we've already processed this donor
        if (in_array($donorId, $processedDonorIds)) {
            error_log("Skipping duplicate eligibility record for donor ID: " . $donorId);
            continue;
        }
        
        // Add to processed list
        $processedDonorIds[] = $donorId;
        
        // Get donor information
        $donorCurl = curl_init();
        curl_setopt_array($donorCurl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId . "&select=*",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ],
        ]);
        
        $donorResponse = curl_exec($donorCurl);
        curl_close($donorCurl);
        
        if ($donorResponse === false) {
            error_log("Failed to fetch donor data for ID: " . $donorId);
            continue;
        }
        
        $donorData = json_decode($donorResponse, true);
        if (!is_array($donorData) || empty($donorData)) {
            error_log("Invalid or empty donor data for ID: " . $donorId);
            continue;
        }
        
        $donor = $donorData[0]; // Get the first (and should be only) donor record
        
        // Get blood type and donation type from screening data if available
        $bloodType = $eligibility['blood_type'] ?? '';
        $donationType = $eligibility['donation_type'] ?? '';
        
        // Calculate age
        $birthdate = $donor['birthdate'] ?? '';
        $age = '';
        if ($birthdate) {
            $birthDate = new DateTime($birthdate);
            $today = new DateTime();
            $age = $birthDate->diff($today)->y;
        }
        
        // Format date
        $createdAt = isset($eligibility['created_at']) ? 
            date('M d, Y', strtotime($eligibility['created_at'])) : '';
            
        // Combine all data
        $donation = [
            'eligibility_id' => $eligibility['eligibility_id'] ?? '',
            'donor_id' => $donorId ?? '',
            'surname' => $donor['surname'] ?? '',
            'first_name' => $donor['first_name'] ?? '',
            'middle_name' => $donor['middle_name'] ?? '',
            'age' => $age ?: '0',
            'sex' => $donor['sex'] ?? '',
            'blood_type' => $bloodType ?? '',
            'donation_type' => $donationType ?? '',
            'status' => 'Approved',
            'date_submitted' => $createdAt ?? ''
        ];
        
        $approvedDonations[] = $donation;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Error in donation_approved.php: " . $error);
    $approvedDonations = ['error' => $error];
}

// Set error message if no records found
if (empty($approvedDonations) && !$error) {
    $error = "No approved donation records found.";
    error_log("Approved Donations: No records found with no API errors");
}

// Detailed debug logging
error_log("Approved Donations Module - Records found: " . count($approvedDonations));

// Check all tables for better diagnostics
if (empty($approvedDonations) || isset($approvedDonations['error'])) {
    // Check eligibility table
    $eligibilityCurl = curl_init();
    curl_setopt_array($eligibilityCurl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?select=eligibility_id,donor_id,status&limit=5",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $eligibilityResponse = curl_exec($eligibilityCurl);
    curl_close($eligibilityCurl);
    
    $eligibilityData = json_decode($eligibilityResponse, true);
    if (is_array($eligibilityData) && !empty($eligibilityData)) {
        error_log("Eligibility table sample records: " . json_encode($eligibilityData));
    } else {
        error_log("No data found in eligibility table");
    }
    
    // Check screening_form table
    $screeningCurl = curl_init();
    curl_setopt_array($screeningCurl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?select=screening_id,donor_form_id,blood_type,donation_type&limit=5",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $screeningResponse = curl_exec($screeningCurl);
    curl_close($screeningCurl);
    
    $screeningData = json_decode($screeningResponse, true);
    if (is_array($screeningData) && !empty($screeningData)) {
        error_log("Screening table sample records: " . json_encode($screeningData));
    } else {
        error_log("No data found in screening_form table");
    }
}
?>