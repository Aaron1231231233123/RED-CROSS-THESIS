<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

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

// Function to fetch eligibility record
function fetchEligibilityRecord($eligibilityId) {
    // If eligibility_id starts with "pending_", this is a pending donor without an eligibility record
    if ($eligibilityId && strpos($eligibilityId, 'pending_') === 0) {
        return [
            'eligibility_id' => $eligibilityId,
            'status' => 'pending',
            'blood_type' => 'Pending',
            'donation_type' => 'Pending',
            'start_date' => date('Y-m-d'),
            'end_date' => null,
            'is_pending' => true
        ];
    }
    
    // Otherwise, fetch from the eligibility table
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
                'is_pending' => true
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

// Fetch data
$donorInfo = fetchDonorInfo($donor_id);

if (!$donorInfo) {
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
$eligibilityInfo = fetchEligibilityRecord($eligibility_id);

// Return data as JSON
echo json_encode([
    'donor' => $donorInfo,
    'eligibility' => $eligibilityInfo
]); 