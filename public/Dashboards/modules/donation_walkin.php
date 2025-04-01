<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

/**
 * Function to fetch walk-in donations data from Supabase
 * These are people who donated without prior application
 */
function fetchWalkinDonations() {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?donation_source=eq.walk_in&select=eligibility_id,donor_id,blood_type,donation_type,start_date,status,blood_collection_id&order=created_at.desc",
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
        // Ensure we have a valid JSON response
        $eligibilityData = json_decode($response, true);
        
        // Check if JSON decode was successful and the result is an array
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ["error" => "JSON decode error: " . json_last_error_msg()];
        }
        
        // Check if eligibilityData is an array
        if (!is_array($eligibilityData)) {
            // Try alternate format if the first one failed
            return fetchWalkinDonationsAlt();
        }
        
        // If empty array, try the alternate version
        if (empty($eligibilityData)) {
            return fetchWalkinDonationsAlt();
        }
        
        // Process each eligibility record
        foreach ($eligibilityData as $key => $eligibility) {
            if (!is_array($eligibility)) {
                continue; // Skip invalid entries
            }
            
            // Get donor information
            $donorData = fetchDonorInfo($eligibility['donor_id']);
            if ($donorData) {
                $eligibilityData[$key]['donor_data'] = $donorData;
            }
            
            // Get screening form data
            $screeningData = fetchScreeningFormData($eligibility['donor_id']);
            if ($screeningData) {
                $eligibilityData[$key]['screening_data'] = $screeningData;
            }
        }
        
        return $eligibilityData;
    }
}

/**
 * Alternative function to fetch walk-in donations with dash in name
 */
function fetchWalkinDonationsAlt() {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?donation_source=eq.walk-in&select=eligibility_id,donor_id,blood_type,donation_type,start_date,status,blood_collection_id&order=created_at.desc",
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
        // Ensure we have a valid JSON response
        $eligibilityData = json_decode($response, true);
        
        // Check if JSON decode was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ["error" => "JSON decode error in alt method: " . json_last_error_msg()];
        }
        
        // Check if the result is an array
        if (!is_array($eligibilityData)) {
            return ["error" => "Response is not an array", "data" => []];
        }
        
        // Process each eligibility record
        foreach ($eligibilityData as $key => $eligibility) {
            if (!is_array($eligibility)) {
                continue; // Skip invalid entries
            }
            
            // Get donor information
            $donorData = fetchDonorInfo($eligibility['donor_id']);
            if ($donorData) {
                $eligibilityData[$key]['donor_data'] = $donorData;
            }
            
            // Get screening form data
            $screeningData = fetchScreeningFormData($eligibility['donor_id']);
            if ($screeningData) {
                $eligibilityData[$key]['screening_data'] = $screeningData;
            }
        }
        
        return $eligibilityData;
    }
}

/**
 * Last resort method to check for general walk-in data
 */
function fetchWalkinDonationsGeneral() {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?select=eligibility_id,donor_id,blood_type,donation_type,start_date,status,blood_collection_id,donation_source&order=created_at.desc",
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
        // Ensure we have a valid JSON response
        $allData = json_decode($response, true);
        
        // Check if JSON decode was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ["error" => "JSON decode error in general method: " . json_last_error_msg()];
        }
        
        // Check if the result is an array
        if (!is_array($allData)) {
            return ["error" => "Response is not an array", "data" => []];
        }
        
        // Filter for walk-in donations by looking at various potential values
        $eligibilityData = [];
        foreach ($allData as $entry) {
            if (is_array($entry) && isset($entry['donation_source'])) {
                $source = strtolower($entry['donation_source']);
                if ($source === 'walk_in' || $source === 'walk-in' || $source === 'walkin') {
                    $eligibilityData[] = $entry;
                }
            }
        }
        
        // Process each eligibility record
        foreach ($eligibilityData as $key => $eligibility) {
            // Get donor information
            $donorData = fetchDonorInfo($eligibility['donor_id']);
            if ($donorData) {
                $eligibilityData[$key]['donor_data'] = $donorData;
            }
            
            // Get screening form data
            $screeningData = fetchScreeningFormData($eligibility['donor_id']);
            if ($screeningData) {
                $eligibilityData[$key]['screening_data'] = $screeningData;
            }
        }
        
        return $eligibilityData;
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

/**
 * Function to fetch screening form data by donor_id
 */
function fetchScreeningFormData($donorId) {
    if (empty($donorId)) {
        return null;
    }
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_id=eq." . $donorId . "&select=*",
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

// Try to fetch walk-in donations with error handling
try {
    $walkinDonations = fetchWalkinDonations();
    
    // If we got an error message or empty array, try the general method
    if (
        (isset($walkinDonations['error']) && $walkinDonations['error']) || 
        (is_array($walkinDonations) && empty($walkinDonations))
    ) {
        $walkinDonations = fetchWalkinDonationsGeneral();
    }
    
    // If still empty, provide a default empty array
    if (!is_array($walkinDonations)) {
        $walkinDonations = [];
    }
} catch (Exception $e) {
    // Handle any exceptions
    $walkinDonations = ["error" => "Exception: " . $e->getMessage(), "data" => []];
}

// Check for errors
$error = null;
if (isset($walkinDonations['error'])) {
    $error = $walkinDonations['error'];
}
?> 