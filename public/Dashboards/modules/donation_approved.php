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
    // First, get all declined donors to exclude them
    $declinedDonorIds = [];
    
    // Check physical examination for declined/deferred donors
    $declinedCurl = curl_init();
    curl_setopt_array($declinedCurl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?or=(remarks.eq.Temporarily%20Deferred,remarks.eq.Permanently%20Deferred,remarks.eq.Refused)&select=donor_id,donor_form_id",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $declinedResponse = curl_exec($declinedCurl);
    curl_close($declinedCurl);
    
    if ($declinedResponse) {
        $declinedRecords = json_decode($declinedResponse, true);
        if (is_array($declinedRecords)) {
            foreach ($declinedRecords as $record) {
                // Add both donor_id and donor_form_id to the exclusion list
                if (!empty($record['donor_id'])) {
                    $declinedDonorIds[] = $record['donor_id'];
                }
                if (!empty($record['donor_form_id'])) {
                    $declinedDonorIds[] = $record['donor_form_id'];
                }
            }
        }
    }
    
    // Also check eligibility table for all non-approved statuses
    $declinedEligCurl = curl_init();
    curl_setopt_array($declinedEligCurl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?or=(status.neq.approved,disapproval_reason.not.is.null)&select=donor_id",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $declinedEligResponse = curl_exec($declinedEligCurl);
    curl_close($declinedEligCurl);
    
    if ($declinedEligResponse) {
        $declinedEligRecords = json_decode($declinedEligResponse, true);
        if (is_array($declinedEligRecords)) {
            foreach ($declinedEligRecords as $record) {
                if (!empty($record['donor_id'])) {
                    $declinedDonorIds[] = $record['donor_id'];
                }
            }
        }
    }
    
    // Remove duplicates
    $declinedDonorIds = array_unique($declinedDonorIds);
    error_log("Found " . count($declinedDonorIds) . " declined donor IDs to exclude");
    
    // Now fetch eligibility records with status 'approved'
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?status=eq.approved&select=eligibility_id,donor_id,created_at",
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
        throw new Exception("Error fetching eligibility data: " . $err);
    }
    
    $eligibilityRecords = json_decode($response, true);
    
    if (!is_array($eligibilityRecords)) {
        throw new Exception("Invalid response format from eligibility API");
    }
    
    error_log("Found " . count($eligibilityRecords) . " approved eligibility records");
    
    if (empty($eligibilityRecords)) {
        // No eligibility records found
        $approvedDonations = [];
    } else {
        // Process each eligibility record
        foreach ($eligibilityRecords as $eligibility) {
            $donorId = $eligibility['donor_id'];
            
            // Skip this donor if they're in the declined list
            if (in_array($donorId, $declinedDonorIds)) {
                error_log("Skipping donor ID $donorId because they are in declined list");
                continue;
            }
            
            // Double-check physical examination remarks for this specific donor
            $remarksCurl = curl_init();
            curl_setopt_array($remarksCurl, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $donorId . "&select=remarks",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ],
            ]);
            
            $remarksResponse = curl_exec($remarksCurl);
            curl_close($remarksCurl);
            
            $remarksData = json_decode($remarksResponse, true);
            if (is_array($remarksData) && !empty($remarksData)) {
                $remarks = $remarksData[0]['remarks'] ?? '';
                // Skip if remarks indicate deferral or refusal
                if (in_array($remarks, ['Temporarily Deferred', 'Permanently Deferred', 'Refused'])) {
                    error_log("Skipping donor ID $donorId because physical exam remarks: $remarks");
                    continue;
                }
            }
            
            // Fetch donor data
            $donorCurl = curl_init();
            curl_setopt_array($donorCurl, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId . "&select=donor_id,surname,first_name,middle_name,birthdate,sex",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ],
            ]);
            
            $donorResponse = curl_exec($donorCurl);
            $donorErr = curl_error($donorCurl);
            curl_close($donorCurl);
            
            if ($donorErr) {
                error_log("Error fetching donor data for ID $donorId: $donorErr");
                continue;
            }
            
            $donorData = json_decode($donorResponse, true);
            
            if (!is_array($donorData) || empty($donorData)) {
                error_log("No donor data found for ID $donorId");
                continue;
            }
            
            $donor = $donorData[0];
            
            // Fetch screening data using donor_form_id
            $screeningCurl = curl_init();
            curl_setopt_array($screeningCurl, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donorId . "&select=screening_id,blood_type,donation_type",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ],
            ]);
            
            $screeningResponse = curl_exec($screeningCurl);
            $screeningErr = curl_error($screeningCurl);
            curl_close($screeningCurl);
            
            // Initialize blood type and donation type
            $bloodType = "";
            $donationType = "";
            
            if (!$screeningErr) {
                $screeningData = json_decode($screeningResponse, true);
                if (is_array($screeningData) && !empty($screeningData)) {
                    $bloodType = $screeningData[0]['blood_type'] ?? "";
                    $donationType = $screeningData[0]['donation_type'] ?? "";
                    
                    // Log screening data for debugging
                    error_log("Found screening data for donor ID $donorId: " . json_encode($screeningData[0]));
                } else {
                    error_log("No screening data found for donor ID $donorId");
                }
            } else {
                error_log("Error fetching screening data: $screeningErr");
            }
            
            // If blood type or donation type is still empty, try a more generic query
            if (empty($bloodType) || empty($donationType)) {
                error_log("Trying alternative query for screening data for donor ID $donorId");
                
                // Try a more permissive query without any join conditions
                $allScreeningCurl = curl_init();
                curl_setopt_array($allScreeningCurl, [
                    CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?order=created_at.desc&select=screening_id,donor_form_id,blood_type,donation_type",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        "apikey: " . SUPABASE_API_KEY,
                        "Authorization: Bearer " . SUPABASE_API_KEY,
                        "Content-Type: application/json"
                    ],
                ]);
                
                $allScreeningResponse = curl_exec($allScreeningCurl);
                curl_close($allScreeningCurl);
                
                $allScreeningData = json_decode($allScreeningResponse, true);
                if (is_array($allScreeningData) && !empty($allScreeningData)) {
                    // Log for debugging
                    error_log("Found " . count($allScreeningData) . " total screening records");
                    
                    // Check each screening record to find one that matches our donor
                    foreach ($allScreeningData as $screening) {
                        if (isset($screening['donor_form_id']) && $screening['donor_form_id'] == $donorId) {
                            $bloodType = $screening['blood_type'] ?? $bloodType;
                            $donationType = $screening['donation_type'] ?? $donationType;
                            error_log("Found matching screening record for donor ID $donorId");
                            break;
                        }
                    }
                }
            }
            
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
                'eligibility_id' => $eligibility['eligibility_id'],
                'donor_id' => $donorId,
                'surname' => $donor['surname'] ?? '',
                'first_name' => $donor['first_name'] ?? '',
                'middle_name' => $donor['middle_name'] ?? '',
                'age' => $age ?: '0',
                'sex' => $donor['sex'] ?? '',
                'blood_type' => $bloodType,
                'donation_type' => $donationType,
                'status' => 'Approved',
                'date_submitted' => $createdAt
            ];
            
            $approvedDonations[] = $donation;
        }
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