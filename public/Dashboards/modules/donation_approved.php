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
    // First, get all donors with non-accepted remarks in physical examination
    $declinedDonorIds = [];
    
    // Query physical examination for non-accepted remarks
    $physicalExamQuery = curl_init();
    curl_setopt_array($physicalExamQuery, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?remarks=neq.Accepted&select=donor_id,donor_form_id,remarks",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $physicalExamResponse = curl_exec($physicalExamQuery);
    curl_close($physicalExamQuery);
    
    if ($physicalExamResponse) {
        $physicalExamRecords = json_decode($physicalExamResponse, true);
        if (is_array($physicalExamRecords)) {
            error_log("Found " . count($physicalExamRecords) . " physical exam records with non-accepted remarks");
            
            foreach ($physicalExamRecords as $record) {
                $remarks = isset($record['remarks']) ? $record['remarks'] : 'Unknown';
                error_log("Physical exam record with remarks '" . $remarks . "' for donor_id: " . 
                    ($record['donor_id'] ?? 'null') . ", donor_form_id: " . ($record['donor_form_id'] ?? 'null'));
                
                // Add both donor_id and donor_form_id to exclusion list
                if (!empty($record['donor_id'])) {
                    $declinedDonorIds[] = $record['donor_id'];
                }
                if (!empty($record['donor_form_id'])) {
                    $declinedDonorIds[] = $record['donor_form_id'];
                }
            }
        }
    }
    
    // Remove duplicates
    $declinedDonorIds = array_unique($declinedDonorIds);
    error_log("Found " . count($declinedDonorIds) . " unique donor IDs to exclude based on physical exam remarks");
    
    // Now fetch eligibility records with status 'approved'
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?status=eq.approved&select=eligibility_id,donor_id,created_at&order=created_at.desc",
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
            
            // Skip this donor if they're in the excluded list
            if (in_array($donorId, $declinedDonorIds)) {
                error_log("Skipping donor ID $donorId because they have non-accepted physical exam remarks");
                continue;
            }
            
            // For each eligibility record, check if there's a physical examination with non-accepted remarks
            $physicalExamCheck = curl_init();
            curl_setopt_array($physicalExamCheck, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $donorId . "&select=remarks",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ],
            ]);
            
            $physicalExamCheckResponse = curl_exec($physicalExamCheck);
            curl_close($physicalExamCheck);
            
            // Check if we have a physical exam record and if it has non-accepted remarks
            $skipThisDonor = false;
            if ($physicalExamCheckResponse) {
                $physicalExamCheckRecords = json_decode($physicalExamCheckResponse, true);
                if (is_array($physicalExamCheckRecords) && !empty($physicalExamCheckRecords)) {
                    foreach ($physicalExamCheckRecords as $examRecord) {
                        $remarks = $examRecord['remarks'] ?? '';
                        if ($remarks !== 'Accepted' && !empty($remarks)) {
                            error_log("Excluding donor ID $donorId due to physical exam remarks: $remarks");
                            $skipThisDonor = true;
                            break;
                        }
                    }
                }
            }
            
            if ($skipThisDonor) {
                continue;
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
            
            // Make sure donor_id exists
            if (!isset($donor['donor_id']) || empty($donor['donor_id'])) {
                error_log("Donor data missing donor_id for record ID $donorId");
                continue;
            }
            
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