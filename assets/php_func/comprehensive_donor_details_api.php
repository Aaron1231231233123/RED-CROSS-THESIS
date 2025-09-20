<?php
// Start output buffering to catch any unexpected output
ob_start();

// Suppress all error output to ensure clean JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Include database connection
include_once '../conn/db_conn.php';

// Check if required parameters are provided
if (!isset($_GET['donor_id'])) {
    ob_clean();
    echo json_encode(['error' => 'Missing donor_id parameter']);
    ob_end_flush();
    exit;
}

$donor_id = $_GET['donor_id'];
$eligibility_id = $_GET['eligibility_id'] ?? null;

// Debug log
error_log("Fetching comprehensive donor details for donor_id: $donor_id, eligibility_id: $eligibility_id");

// Function to fetch donor information from donor_form
function fetchDonorFormData($donorId) {
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
        error_log("cURL Error fetching donor form data: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        if (empty($data) || !is_array($data)) {
            error_log("No donor form data found for ID: $donorId");
            return null;
        }
        return $data[0];
    }
}

// Function to fetch screening form data
function fetchScreeningFormData($donorId) {
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
        error_log("cURL Error fetching screening form data: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        if (empty($data) || !is_array($data)) {
            error_log("No screening form data found for donor ID: $donorId");
            return null;
        }
        return $data[0];
    }
}

// Function to fetch medical history data
function fetchMedicalHistoryData($donorId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/medical_history?donor_id=eq." . $donorId . "&order=created_at.desc&limit=1",
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
        error_log("cURL Error fetching medical history data: " . $err);
        return null;
    } else {
        if ($httpCode !== 200) {
            error_log("HTTP Error fetching medical history data: HTTP $httpCode");
            return null;
        }
        
        $data = json_decode($response, true);
        if (empty($data) || !is_array($data)) {
            error_log("No medical history data found for donor ID: $donorId - this is normal for some donors");
            return null;
        }
        return $data[0];
    }
}

// Function to fetch physical examination data
function fetchPhysicalExaminationData($donorId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $donorId . "&order=created_at.desc&limit=1",
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
        error_log("cURL Error fetching physical examination data: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        if (empty($data) || !is_array($data)) {
            error_log("No physical examination data found for donor ID: $donorId");
            return null;
        }
        return $data[0];
    }
}

// Function to fetch eligibility data
function fetchEligibilityData($eligibilityId) {
    if (!$eligibilityId || strpos($eligibilityId, 'pending_') === 0 || strpos($eligibilityId, 'declined_') === 0) {
        return null;
    }
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?eligibility_id=eq." . $eligibilityId . "&select=eligibility_id,status,donation_type,blood_type,created_at,updated_at",
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
        error_log("cURL Error fetching eligibility data: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        if (empty($data) || !is_array($data)) {
            error_log("No eligibility data found for ID: $eligibilityId");
            return null;
        }
        return $data[0];
    }
}

// Function to fetch blood collection data
function fetchBloodCollectionData($donorId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/blood_collection?donor_id=eq." . $donorId . "&order=created_at.desc&limit=1",
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
        error_log("cURL Error fetching blood collection data: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        if (empty($data) || !is_array($data)) {
            error_log("No blood collection data found for donor ID: $donorId");
            return null;
        }
        return $data[0];
    }
}

try {
    // Fetch data from all sources
    error_log("Starting data fetch for donor_id: $donor_id, eligibility_id: $eligibility_id");
    
    $donorForm = fetchDonorFormData($donor_id);
    error_log("Donor form data: " . ($donorForm ? "Found" : "Not found"));
    
    // Only fetch other data if donor form exists
    if (!$donorForm) {
        error_log("No donor form data found for ID: $donor_id");
        ob_clean();
        echo json_encode(['error' => 'Donor information not found']);
        ob_end_flush();
        exit;
    }
    
    // Try to fetch other data, but don't fail if they don't exist
    $screeningForm = null;
    $medicalHistory = null;
    $physicalExamination = null;
    $eligibility = null;
    $bloodCollection = null;
    
    try {
        $screeningForm = fetchScreeningFormData($donor_id);
        error_log("Screening form data: " . ($screeningForm ? "Found" : "Not found"));
    } catch (Exception $e) {
        error_log("Error fetching screening form: " . $e->getMessage());
    }
    
    try {
        $medicalHistory = fetchMedicalHistoryData($donor_id);
        error_log("Medical history data: " . ($medicalHistory ? "Found" : "Not found"));
    } catch (Exception $e) {
        error_log("Error fetching medical history: " . $e->getMessage());
    }
    
    try {
        $physicalExamination = fetchPhysicalExaminationData($donor_id);
        error_log("Physical examination data: " . ($physicalExamination ? "Found" : "Not found"));
    } catch (Exception $e) {
        error_log("Error fetching physical examination: " . $e->getMessage());
    }
    
    try {
        $eligibility = fetchEligibilityData($eligibility_id);
        error_log("Eligibility data: " . ($eligibility ? "Found" : "Not found"));
    } catch (Exception $e) {
        error_log("Error fetching eligibility: " . $e->getMessage());
    }
    
    try {
        $bloodCollection = fetchBloodCollectionData($donor_id);
        error_log("Blood collection data: " . ($bloodCollection ? "Found" : "Not found"));
    } catch (Exception $e) {
        error_log("Error fetching blood collection: " . $e->getMessage());
    }
    
    // Calculate age if birthdate is available
    if (!empty($donorForm['birthdate'])) {
        $birthdate = new DateTime($donorForm['birthdate']);
        $today = new DateTime();
        $donorForm['age'] = $birthdate->diff($today)->y;
    } else {
        $donorForm['age'] = 'N/A';
    }
    
    // Determine completion status for each process
    $completionStatus = [
        'donor_form' => !empty($donorForm),
        'screening_form' => !empty($screeningForm),
        'medical_history' => !empty($medicalHistory),
        'physical_examination' => !empty($physicalExamination),
        'eligibility' => !empty($eligibility),
        'blood_collection' => !empty($bloodCollection)
    ];
    
    error_log("Returning comprehensive data for donor_id: $donor_id");
    
    // Return comprehensive data
    $response = [
        'donor_form' => $donorForm,
        'screening_form' => $screeningForm ?: (object)[],
        'medical_history' => $medicalHistory ?: (object)[],
        'physical_examination' => $physicalExamination ?: (object)[],
        'eligibility' => $eligibility ?: (object)[],
        'blood_collection' => $bloodCollection ?: (object)[],
        'completion_status' => $completionStatus
    ];
    
    // Clear any unexpected output and send clean JSON
    ob_clean();
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in comprehensive_donor_details_api.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clear any unexpected output and send clean error JSON
    ob_clean();
    echo json_encode(['error' => 'An error occurred while processing your request: ' . $e->getMessage()]);
}

// End output buffering
ob_end_flush();
?>
