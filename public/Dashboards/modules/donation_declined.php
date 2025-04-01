<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

/**
 * Function to fetch declined donations data from Supabase
 * These are rejected applications with reasons from the physical examination
 */
function fetchDeclinedDonations() {
    $curl = curl_init();
    
    // Get all physical examination records with reason and disapproval_reason fields
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?select=physical_exam_id,donor_id,reason,disapproval_reason,created_at&order=created_at.desc",
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
        $physicalExamData = json_decode($response, true);
        
        // Check if JSON decode was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ["error" => "JSON decode error: " . json_last_error_msg()];
        }
        
        // Check if the result is an array
        if (!is_array($physicalExamData)) {
            return ["error" => "Response is not an array", "data" => []];
        }
        
        // Output debug info about the actual data received
        if (empty($physicalExamData)) {
            return ["error" => "No records found in physical_examination table. Please check your database.", "data" => []];
        }
        
        // Format results to match the display from the image
        $formattedData = [];
        
        foreach ($physicalExamData as $exam) {
            if (!isset($exam['donor_id'])) continue;
            
            // Skip if there's no reason to decline
            if (empty($exam['reason']) && empty($exam['disapproval_reason'])) continue;
            
            // Get donor information
            $donorData = fetchDonorInfo($exam['donor_id']);
            if (!$donorData) continue; // Skip if no donor info found
            
            // Use disapproval_reason if available, otherwise fall back to reason field
            $rejectionReason = !empty($exam['disapproval_reason']) ? 
                $exam['disapproval_reason'] : 
                ($exam['reason'] ?? 'Unspecified reason');
            
            // Create formatted record with all required fields
            $record = [
                'eligibility_id' => 'declined_' . $exam['physical_exam_id'], // Create a pseudo eligibility ID
                'donor_id' => $exam['donor_id'],
                'rejection_source' => 'Physical Examination',
                'rejection_reason' => $rejectionReason,
                'rejection_date' => date('M d, Y', strtotime($exam['created_at'] ?? 'now')),
                'status' => 'declined',
                'donor_data' => $donorData
            ];
            
            $formattedData[] = $record;
        }
        
        return $formattedData;
    }
}

/**
 * Function to fetch all records from physical_examination table for debugging
 */
function fetchAllPhysicalExaminations() {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?select=physical_exam_id,donor_id,reason,disapproval_reason,created_at&limit=10",
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
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return ["error" => "JSON decode error in fetchAllPhysicalExaminations"];
        }
        
        // Get the raw data for debugging
        $dataCount = count($data);
        $reasonCount = 0;
        $disapprovalCount = 0;
        
        foreach ($data as $item) {
            if (!empty($item['reason'])) $reasonCount++;
            if (!empty($item['disapproval_reason'])) $disapprovalCount++;
        }
        
        return [
            "data" => $data,
            "count" => $dataCount,
            "with_reason" => $reasonCount,
            "with_disapproval" => $disapprovalCount
        ];
    }
}

/**
 * Function to fetch donor information by donor_id
 */
function fetchDonorInfo($donorId) {
    if (empty($donorId)) {
        return null;
    }
    
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
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }
        return !empty($data) ? $data[0] : null;
    }
}

// Try to fetch declined donations with detailed error handling
try {
    // First try to get some sample data from the physical_examination table for debugging
    $sampleExams = fetchAllPhysicalExaminations();
    
    // Now fetch the actual declined donations
    $declinedDonations = fetchDeclinedDonations();
    
    // If still not an array, provide a default empty array
    if (!is_array($declinedDonations)) {
        $declinedDonations = [];
    }
} catch (Exception $e) {
    // Handle any exceptions
    $declinedDonations = ["error" => "Exception: " . $e->getMessage(), "data" => []];
}

// Check for errors
$error = null;
if (isset($declinedDonations['error'])) {
    $error = $declinedDonations['error'];
}

// Add detailed debugging information for no data scenario
if (empty($declinedDonations) || count($declinedDonations) === 0) {
    $debug_info = "";
    
    if (isset($sampleExams) && is_array($sampleExams)) {
        $debug_info = " Found " . ($sampleExams['count'] ?? 0) . " records in the physical_examination table, ";
        $debug_info .= "with " . ($sampleExams['with_reason'] ?? 0) . " having 'reason' field populated ";
        $debug_info .= "and " . ($sampleExams['with_disapproval'] ?? 0) . " having 'disapproval_reason' field populated.";
    }
    
    $error = "Error loading data: No declined donations found in the physical_examination table." . $debug_info;
}
?>