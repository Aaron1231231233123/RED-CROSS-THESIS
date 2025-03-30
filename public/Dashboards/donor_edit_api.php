<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Check if required parameters are provided
if (!isset($_GET['donor_id']) || !isset($_GET['eligibility_id'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$donor_id = $_GET['donor_id'];
$eligibility_id = $_GET['eligibility_id'];

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
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
}

// Function to fetch eligibility record
function fetchEligibilityRecord($eligibilityId) {
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
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
}

// Fetch data
$donorInfo = fetchDonorInfo($donor_id);
$eligibilityInfo = fetchEligibilityRecord($eligibility_id);

// Check if data is available
if (!$donorInfo) {
    echo json_encode(['error' => 'Donor information not found']);
    exit;
}

if (!$eligibilityInfo) {
    echo json_encode(['error' => 'Eligibility information not found']);
    exit;
}

// Return data as JSON
echo json_encode([
    'donor' => $donorInfo,
    'eligibility' => $eligibilityInfo
]); 