<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Array to hold donor data
$pendingDonations = [];
$error = null;

try {
    // First, get all eligibility records with approved status to filter out approved donors
    $approvedDonorIds = [];
    $eligibilityCurl = curl_init();
    
    curl_setopt_array($eligibilityCurl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?status=eq.approved&select=donor_id",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $eligibilityResponse = curl_exec($eligibilityCurl);
    $eligibilityErr = curl_error($eligibilityCurl);
    
    curl_close($eligibilityCurl);
    
    // Process eligibility data to get list of donor IDs that already have approved status
    if (!$eligibilityErr) {
        $eligibilityData = json_decode($eligibilityResponse, true);
        if (is_array($eligibilityData)) {
            foreach ($eligibilityData as $eligibility) {
                if (isset($eligibility['donor_id'])) {
                    $approvedDonorIds[] = $eligibility['donor_id'];
                }
            }
        }
    }
    
    // Direct connection to get donor_form data
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?limit=100&order=submitted_at.desc",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Accept: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    // Check for errors
    if ($err) {
        $error = "cURL Error: " . $err;
    } else {
        if ($http_code != 200) {
            $error = "API Error: HTTP Code " . $http_code;
        } else {
            // Parse the response
            $donorData = json_decode($response, true);
            
            // Check JSON decode
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = "JSON decode error: " . json_last_error_msg();
            } else {
                // Check if the response is an array
                if (!is_array($donorData)) {
                    $error = "Response is not an array";
                } else {
                    // Log the first donor record to see all available fields
                    if (!empty($donorData)) {
                        error_log("First donor record fields: " . print_r(array_keys($donorData[0]), true));
                    }
                    
                    // Process each donor
                    foreach ($donorData as $donor) {
                        // Skip donors who already have an approved eligibility record
                        if (in_array($donor['donor_id'], $approvedDonorIds)) {
                            continue;
                        }
                        
                        // Try multiple possible date fields
                        $dateSubmitted = '';
                        
                        // Check each possible date field and use the first one available
                        if (!empty($donor['created_at'])) {
                            $dateSubmitted = date('M d, Y', strtotime($donor['created_at']));
                        } elseif (!empty($donor['submitted_at'])) {
                            $dateSubmitted = date('M d, Y', strtotime($donor['submitted_at']));
                        } elseif (!empty($donor['date_submitted'])) {
                            $dateSubmitted = date('M d, Y', strtotime($donor['date_submitted']));
                        } else {
                            // If no date field is found, use today's date
                            $dateSubmitted = date('M d, Y');
                        }
                        
                        // Create a simplified record with ONLY the required fields
                        $pendingDonations[] = [
                            'donor_id' => $donor['donor_id'] ?? '',
                            'surname' => $donor['surname'] ?? '',
                            'first_name' => $donor['first_name'] ?? '',
                            'birthdate' => $donor['birthdate'] ?? '',
                            'sex' => $donor['sex'] ?? '',
                            'date_submitted' => $dateSubmitted,
                            'eligibility_id' => 'pending_' . ($donor['donor_id'] ?? '0')
                        ];
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    $error = "Exception: " . $e->getMessage();
}

// Set error message if no records found
if (empty($pendingDonations) && !$error) {
    $error = "No pending donors found in the donor_form table.";
}

// Debug: Show exact values being used
error_log("SUPABASE_URL: " . SUPABASE_URL);
error_log("API Key Length: " . strlen(SUPABASE_API_KEY));
error_log("Filtered out " . count($approvedDonorIds) . " approved donors");
error_log("Found " . count($pendingDonations) . " pending donors");
?> 