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
    // Fetch eligibility records with status 'approved' and include the screening_id
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?status=eq.approved&select=eligibility_id,donor_id,screening_id,created_at,status",
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
    
    if (empty($eligibilityRecords)) {
        // No eligibility records found
        $approvedDonations = [];
    } else {
        // Process each eligibility record
        foreach ($eligibilityRecords as $eligibility) {
            $donorId = $eligibility['donor_id'];
            $screeningId = $eligibility['screening_id'] ?? null;
            
            // Log for debugging
            error_log("Processing eligibility record with screening_id: " . $screeningId);
            
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
            
            // Initialize blood type and donation type as empty strings, not default values
            $bloodType = "";
            $donationType = "";
            
            // Fetch screening data if screening_id is available
            if ($screeningId) {
                $screeningCurl = curl_init();
                curl_setopt_array($screeningCurl, [
                    CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?screening_id=eq." . $screeningId . "&select=blood_type,donation_type",
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
                
                if (!$screeningErr) {
                    $screeningData = json_decode($screeningResponse, true);
                    if (is_array($screeningData) && !empty($screeningData)) {
                        $bloodType = $screeningData[0]['blood_type'] ?? "";
                        $donationType = $screeningData[0]['donation_type'] ?? "";
                        
                        // Log screening data for debugging
                        error_log("Found screening data: " . json_encode($screeningData));
                    } else {
                        error_log("No screening data found for ID $screeningId");
                    }
                } else {
                    error_log("Error fetching screening data: $screeningErr");
                }
            } else {
                error_log("No screening_id available for eligibility record " . $eligibility['eligibility_id']);
                
                // If no screening_id is available, try to fetch directly from the screening_form using donor_id
                $directScreeningCurl = curl_init();
                curl_setopt_array($directScreeningCurl, [
                    CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donorId . "&select=blood_type,donation_type",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        "apikey: " . SUPABASE_API_KEY,
                        "Authorization: Bearer " . SUPABASE_API_KEY,
                        "Content-Type: application/json"
                    ],
                ]);
                
                $directScreeningResponse = curl_exec($directScreeningCurl);
                $directScreeningErr = curl_error($directScreeningCurl);
                curl_close($directScreeningCurl);
                
                if (!$directScreeningErr) {
                    $directScreeningData = json_decode($directScreeningResponse, true);
                    if (is_array($directScreeningData) && !empty($directScreeningData)) {
                        $bloodType = $directScreeningData[0]['blood_type'] ?? "";
                        $donationType = $directScreeningData[0]['donation_type'] ?? "";
                        
                        error_log("Found direct screening data: " . json_encode($directScreeningData));
                    } else {
                        error_log("No direct screening data found for donor ID $donorId");
                    }
                } else {
                    error_log("Error fetching direct screening data: $directScreeningErr");
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
                'age' => $age,
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
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?select=eligibility_id,screening_id,donor_id,status&limit=5",
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
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?select=screening_id,donor_id,blood_type,donation_type&limit=5",
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