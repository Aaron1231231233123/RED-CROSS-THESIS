<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Array to hold declined donations
$declinedDonations = [];
$error = null;

// Debug flag - set to true for additional logging
$DEBUG = true;

// Log if debug is enabled
function debug_log($message) {
    global $DEBUG;
    if ($DEBUG) {
        error_log("[DEBUG] " . $message);
    }
}

try {
    debug_log("Starting donation_declined.php");
    
    // First, try to fetch from physical_examination table directly since that's where the remarks field is
    debug_log("Trying to fetch data directly from physical_examination table first");
    $declinedDonations = fetchDeniedFromPhysicalExam();
    
    // If we still don't have any records, try the eligibility table as a fallback
    if (empty($declinedDonations)) {
        debug_log("No records found in physical_examination, trying eligibility table as fallback");
        // Fetch eligibility records with status 'declined'
        $curl = curl_init();
        
        $eligibilityUrl = SUPABASE_URL . "/rest/v1/eligibility?status=eq.declined&select=*&order=created_at.desc&limit=50";
        debug_log("Querying eligibility with URL: " . $eligibilityUrl);
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $eligibilityUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json",
                "Prefer: count=exact"
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        debug_log("Eligibility API response HTTP code: " . $httpCode);
        
        if ($err) {
            throw new Exception("Error fetching eligibility data: " . $err);
        }
        
        // Make sure we have a valid response before trying to decode it
        if (!empty($response)) {
            debug_log("Raw eligibility response first 100 chars: " . substr($response, 0, 100));
            
            $eligibilityRecords = json_decode($response, true);
            
            // Check if JSON decode was successful and returned an array
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON decode error: " . json_last_error_msg());
            }
            
            if (!is_array($eligibilityRecords)) {
                throw new Exception("Invalid response format from eligibility API (not an array)");
            }
            
            debug_log("Found " . count($eligibilityRecords) . " declined eligibility records");
            
            // Process each eligibility record
            foreach ($eligibilityRecords as $eligibility) {
                // Make sure eligibility is an array before trying to access its elements
                if (!is_array($eligibility)) {
                    debug_log("Invalid eligibility record (not an array): " . print_r($eligibility, true));
                    continue;
                }
                
                $donorId = $eligibility['donor_id'] ?? null;
                
                // Skip if no donor ID
                if (empty($donorId)) {
                    debug_log("Skipping eligibility record with no donor_id");
                    continue;
                }
                
                // Get donor information
                $donorData = fetchDonorInfo($donorId);
                if (!$donorData) continue; // Skip if no donor info found
                
                // Format the rejection date
                $rejectionDate = isset($eligibility['created_at']) ? 
                    date('M d, Y', strtotime($eligibility['created_at'])) : date('M d, Y');
                
                // Create basic donation record from eligibility
                $declinedDonations[] = [
                    'eligibility_id' => $eligibility['eligibility_id'] ?? ('declined_' . $donorId),
                    'donor_id' => $donorId,
                    'surname' => $donorData['surname'] ?? '',
                    'first_name' => $donorData['first_name'] ?? '',
                    'middle_name' => $donorData['middle_name'] ?? '',
                    'rejection_source' => 'Eligibility', // Generic source since we don't know the specific reason
                    'rejection_reason' => $eligibility['rejection_reason'] ?? 'Declined in eligibility check',
                    'rejection_date' => $rejectionDate,
                    'remarks_status' => $eligibility['remarks'] ?? 'Unknown',
                    'status' => 'declined'
                ];
                
                debug_log("Added declined donor from eligibility: " . ($donorData['surname'] ?? 'Unknown') . ", " . ($donorData['first_name'] ?? 'Unknown'));
            }
        } else {
            debug_log("Empty response from eligibility API");
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    debug_log("Error in donation_declined.php: " . $error);
}

/**
 * Function to fetch donor information by donor_id
 */
function fetchDonorInfo($donorId) {
    if (empty($donorId)) {
        debug_log("fetchDonorInfo: Empty donor_id provided");
        return null;
    }
    
    $curl = curl_init();
    $donorUrl = SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId . "&select=donor_id,surname,first_name,middle_name,birthdate,age,sex";
    
    debug_log("Fetching donor info for donor_id: " . $donorId);
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $donorUrl,
        CURLOPT_RETURNTRANSFER => true,
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
        debug_log("Error fetching donor info: " . $err);
        return null;
    }
    
    debug_log("Donor info API HTTP code: " . $httpCode);
    
    if (empty($response) || $response === '[]') {
        debug_log("No donor record found for donor_id: " . $donorId);
        return null;
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        debug_log("JSON decode error in fetchDonorInfo: " . json_last_error_msg());
        return null;
    }
    
    if (empty($data)) {
        debug_log("Empty donor data array after JSON decode for donor_id: " . $donorId);
        return null;
    }
    
    debug_log("Successfully retrieved donor info for: " . $data[0]['surname'] . ", " . $data[0]['first_name']);
    return $data[0];
}

/**
 * Function to fetch declined donors directly from physical_examination table
 * Used for cases where eligibility records might not be available yet
 */
function fetchDeniedFromPhysicalExam() {
    $resultArray = [];
    
    // First, let's get all remarks values to see what's actually in the database
    $curl = curl_init();
    $requestUrl = SUPABASE_URL . "/rest/v1/physical_examination?select=remarks&order=created_at.desc&limit=50";
    
    debug_log("Querying physical_examination for all remarks values: " . $requestUrl);
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $requestUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    debug_log("Physical exam remarks query HTTP code: " . $httpCode);
    
    $allRemarks = [];
    
    if (!empty($response)) {
        $remarksData = json_decode($response, true);
        if (is_array($remarksData)) {
            foreach ($remarksData as $record) {
                if (isset($record['remarks']) && !empty($record['remarks'])) {
                    $allRemarks[] = $record['remarks'];
                }
            }
            $uniqueRemarks = array_unique($allRemarks);
            debug_log("All unique remarks values in database: " . implode(", ", $uniqueRemarks));
        }
    }
    
    // Now get physical examination records with non-accepted remarks
    $curl = curl_init();
    
    // Query specifically for the exact enum values we want
    $requestUrl = SUPABASE_URL . "/rest/v1/physical_examination?or=(remarks.eq.Temporarily%20Deferred,remarks.eq.Permanently%20Deferred,remarks.eq.Refused)&select=*&order=created_at.desc&limit=100";
    
    debug_log("Querying physical_examination with URL: " . $requestUrl);
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $requestUrl,
        CURLOPT_RETURNTRANSFER => true,
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
        debug_log("Error fetching from physical_examination: " . $err);
        return $resultArray;
    }
    
    debug_log("Response HTTP code: " . $httpCode);
    
    if (empty($response)) {
        debug_log("Empty response from physical_examination API");
        return $resultArray;
    }
    
    // Log raw response for debugging
    debug_log("Raw physical_examination response first 100 chars: " . substr($response, 0, 100));
    
    $physicalExamData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        debug_log("JSON decode error in fetchDeniedFromPhysicalExam: " . json_last_error_msg());
        return $resultArray;
    }
    
    if (!is_array($physicalExamData)) {
        debug_log("Invalid response format from physical_examination API (not an array)");
        return $resultArray;
    }
    
    debug_log("Found " . count($physicalExamData) . " total records in physical_examination");
    
    if (empty($physicalExamData)) {
        debug_log("No records found in physical_examination");
        return $resultArray;
    }
    
    $declinedCount = 0;
    
    // Case insensitive target values
    $targetRemarks = [
        'Temporarily Deferred',
        'Permanently Deferred',
        'Refused'
    ];
    
    foreach ($physicalExamData as $exam) {
        // Make sure exam is an array before trying to access its elements
        if (!is_array($exam)) {
            debug_log("Invalid physical exam record (not an array): " . print_r($exam, true));
            continue;
        }
        
        // Get the remarks value (should be Enum)
        $remarks = $exam['remarks'] ?? '';
        
        // Explicitly handle NULL values coming from database
        if ($remarks === null) {
            debug_log("Skipping record with NULL remarks");
            continue;
        }
        
        debug_log("Processing physical exam record: donor_id=" . ($exam['donor_id'] ?? 'unknown') . 
                  ", remarks=" . $remarks);
        
        // Exact match against target remarks enum values
        $isDeclined = in_array($remarks, $targetRemarks);
        
        // Skip if not declined
        if (!$isDeclined) {
            debug_log("Skipping record with remarks: " . $remarks . " (not in target list)");
            continue;
        }
        
        $declinedCount++;
        
        $donorId = $exam['donor_id'] ?? null;
        if (!$donorId) {
            debug_log("Skipping record with no donor_id");
            continue;
        }
        
        // Get donor information
        $donorInfo = fetchDonorInfo($donorId);
        if (!$donorInfo) {
            debug_log("Could not find donor info for donor_id: " . $donorId);
            continue;
        }
        
        // Use disapproval_reason if available, otherwise fall back to reason field
        $rejectionReason = !empty($exam['disapproval_reason']) ? 
            $exam['disapproval_reason'] : ($exam['reason'] ?? 'Unspecified reason');
        
        // Create record with all required fields
        $resultArray[] = [
            'eligibility_id' => 'declined_' . $exam['physical_exam_id'],
            'donor_id' => $donorId,
            'surname' => $donorInfo['surname'] ?? '',
            'first_name' => $donorInfo['first_name'] ?? '',
            'middle_name' => $donorInfo['middle_name'] ?? '',
            'rejection_source' => $remarks, // Use the actual value from db
            'rejection_reason' => $rejectionReason,
            'rejection_date' => date('M d, Y', strtotime($exam['created_at'] ?? 'now')),
            'remarks_status' => $remarks,
            'status' => 'declined'
        ];
        
        debug_log("Added declined donor to results: " . ($donorInfo['surname'] ?? 'Unknown') . ", " . ($donorInfo['first_name'] ?? 'Unknown'));
    }
    
    debug_log("Total physical exam records: " . count($physicalExamData) . ", total declined: " . $declinedCount . ", total added: " . count($resultArray));
    return $resultArray;
}

// Set error message if no records found
if (empty($declinedDonations) && !$error) {
    $error = "No declined donation records found.";
    debug_log("No declined donations found, setting error message");
}

// Log diagnostic information
debug_log("Declined Donations Module - Records found: " . count($declinedDonations));
?>