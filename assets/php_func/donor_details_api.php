<?php
// Include database connection
include_once '../conn/db_conn.php';

// Check if required parameters are provided
if (!isset($_GET['donor_id'])) {
    echo json_encode(['error' => 'Missing donor_id parameter']);
    exit;
}

$donor_id = $_GET['donor_id'];
$eligibility_id = $_GET['eligibility_id'] ?? null;

// Debug log
error_log("Fetching donor details for donor_id: $donor_id, eligibility_id: $eligibility_id");

// Function to fetch donor information
function fetchDonorInfo($donorId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId,
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
        error_log("cURL Error fetching donor info: " . $err);
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No donor data found for ID: $donorId");
        }
        return !empty($data) ? $data[0] : null;
    }
}

// Function to fetch physical examination data (for declined donors)
function fetchPhysicalExamData($physicalExamId) {
    error_log("Fetching physical exam data for ID: $physicalExamId");
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?physical_exam_id=eq." . $physicalExamId,
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
        error_log("cURL Error fetching physical exam data: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No physical exam data found for ID: $physicalExamId");
            return null;
        }
        error_log("Successfully retrieved physical exam data for ID: $physicalExamId");
        return $data[0];
    }
}

// Function to fetch screening data by donor ID
function fetchScreeningDataByDonorId($donorId) {
    error_log("Fetching screening data for donor_id: $donorId");
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donorId . "&order=created_at.desc&limit=1",
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
        error_log("cURL Error fetching screening data by donor ID: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No screening data found for donor ID: $donorId");
            return null;
        }
        error_log("Successfully retrieved screening data for donor ID: $donorId");
        return $data[0];
    }
}

// Function to fetch eligibility record
function fetchEligibilityRecord($eligibilityId) {
    global $donor_id;
    error_log("Processing eligibility record: $eligibilityId");

    // If eligibility_id starts with "pending_", this is a pending donor without an eligibility record
    if ($eligibilityId && strpos($eligibilityId, 'pending_') === 0) {
        error_log("Handling pending eligibility record");
        return [
            'eligibility_id' => $eligibilityId,
            'status' => 'pending',
            'blood_type' => 'Pending',
            'donation_type' => 'Pending',
            'start_date' => date('Y-m-d'),
            'end_date' => null,
            'is_pending' => true,
            // Explicitly set these fields to null for pending donors
            'blood_bag_type' => null,
            'amount_collected' => null, 
            'donor_reaction' => null,
            'management_done' => null
        ];
    }
    
    // If eligibility_id starts with "declined_", this is a declined donor from physical examination
    if ($eligibilityId && strpos($eligibilityId, 'declined_') === 0) {
        error_log("Handling declined eligibility record");
        
        // Extract the physical exam ID from the format "declined_[physical_exam_id]"
        $physicalExamId = substr($eligibilityId, strlen('declined_'));
        error_log("Extracted physical exam ID: $physicalExamId");
        
        $bloodType = "Unknown";
        $donationType = "Unknown";
        
        // First try to get the physical exam record to get more details
        if (is_numeric($physicalExamId)) {
            $physicalExamData = fetchPhysicalExamData($physicalExamId);
            
            // If we have physical exam data, extract what we can
            if ($physicalExamData) {
                error_log("Found physical exam data for ID: $physicalExamId");
                $remarks = $physicalExamData['remarks'] ?? '';
                $disapprovalReason = $physicalExamData['disapproval_reason'] ?? '';
                
                // Some physical exam records might have blood type or donation type
                if (!empty($physicalExamData['blood_type'])) {
                    $bloodType = $physicalExamData['blood_type'];
                    error_log("Found blood type from physical exam: $bloodType");
                }
                
                if (!empty($physicalExamData['donation_type'])) {
                    $donationType = $physicalExamData['donation_type'];
                    error_log("Found donation type from physical exam: $donationType");
                }
            }
        }
        
        // If we still don't have blood type or donation type, try looking in the screening form
        if ($bloodType === "Unknown" || $donationType === "Unknown") {
            $screeningData = fetchScreeningDataByDonorId($donor_id);
            
            if ($screeningData) {
                error_log("Found screening data for donor ID: $donor_id");
                
                if (!empty($screeningData['blood_type']) && $bloodType === "Unknown") {
                    $bloodType = $screeningData['blood_type'];
                    error_log("Found blood type from screening: $bloodType");
                }
                
                if (!empty($screeningData['donation_type']) && $donationType === "Unknown") {
                    $donationType = $screeningData['donation_type'];
                    error_log("Found donation type from screening: $donationType");
                }
            }
        }
        
        // Return the declined eligibility record with all available information
        error_log("Returning declined eligibility record with blood_type: $bloodType, donation_type: $donationType");
        return [
            'eligibility_id' => $eligibilityId,
            'status' => 'declined',
            'blood_type' => $bloodType,
            'donation_type' => $donationType,
            'start_date' => date('Y-m-d'),
            'end_date' => null,
            // Explicitly set these fields to null for declined donors
            'blood_bag_type' => null,
            'amount_collected' => null,
            'donor_reaction' => null,
            'management_done' => null
        ];
    }
    
    // Otherwise, fetch from the eligibility table
    error_log("Fetching from eligibility table for ID: $eligibilityId");
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?eligibility_id=eq." . $eligibilityId,
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
        error_log("cURL Error fetching eligibility: " . $err);
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No eligibility data found for ID: $eligibilityId");
            // Return a default eligibility object for pending donors
            return [
                'eligibility_id' => $eligibilityId ?? 'pending_' . $donor_id,
                'status' => 'pending',
                'blood_type' => 'Pending',
                'donation_type' => 'Pending',
                'start_date' => date('Y-m-d'),
                'end_date' => null,
                'is_pending' => true,
                // Explicitly set these fields to null for pending donors
                'blood_bag_type' => null,
                'amount_collected' => null, 
                'donor_reaction' => null,
                'management_done' => null
            ];
        }
        
        $eligibilityRecord = $data[0];
        
        // If there's a screening_id, fetch blood_type and donation_type from screening_form
        if (!empty($eligibilityRecord['screening_id'])) {
            $screeningData = fetchScreeningData($eligibilityRecord['screening_id']);
            
            if ($screeningData && !isset($screeningData['error'])) {
                // Override blood_type and donation_type with data from screening form if available
                if (!empty($screeningData['blood_type'])) {
                    $eligibilityRecord['blood_type'] = $screeningData['blood_type'];
                }
                
                if (!empty($screeningData['donation_type'])) {
                    $eligibilityRecord['donation_type'] = $screeningData['donation_type'];
                }
            }
        }
        
        return $eligibilityRecord;
    }
}

// Function to fetch screening form data
function fetchScreeningData($screeningId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?screening_id=eq." . $screeningId,
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
        error_log("cURL Error fetching screening data: " . $err);
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No screening data found for ID: $screeningId");
            return null;
        }
        return $data[0];
    }
}

try {
    // Fetch data
    $donorInfo = fetchDonorInfo($donor_id);
    
    if (!$donorInfo) {
        error_log("No donor information found for ID: $donor_id");
        echo json_encode(['error' => 'Donor information not found']);
        exit;
    }
    
    // Calculate age if birthdate is available
    if (!empty($donorInfo['birthdate'])) {
        $birthdate = new DateTime($donorInfo['birthdate']);
        $today = new DateTime();
        $donorInfo['age'] = $birthdate->diff($today)->y;
    } else {
        $donorInfo['age'] = 'N/A';
    }
    
    // Fetch eligibility info
    error_log("Fetching eligibility record with ID: $eligibility_id");
    $eligibilityInfo = fetchEligibilityRecord($eligibility_id);
    
    if (isset($eligibilityInfo['error'])) {
        error_log("Error fetching eligibility: " . $eligibilityInfo['error']);
    } else {
        error_log("Successfully fetched eligibility record: " . json_encode(['status' => $eligibilityInfo['status'] ?? 'unknown']));
    }
    
    // Return data as JSON
    echo json_encode([
        'donor' => $donorInfo,
        'eligibility' => $eligibilityInfo
    ]);

} catch (Exception $e) {
    error_log("Error in donor_details_api.php: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while processing your request: ' . $e->getMessage()]);
} 