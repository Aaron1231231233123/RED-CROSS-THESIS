<?php
// Fetch Donor Medical Information API
// Returns comprehensive donor medical information for the donor information medical modal

// Disable error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Include database connection
require_once '../conn/db_conn.php';

// Set JSON header
header('Content-Type: application/json');

// Function to fetch comprehensive donor medical information
function fetchDonorMedicalInfo($eligibilityId) {
    try {
        // First get the eligibility record to get the donor_id
        $eligibility_curl = curl_init();
        curl_setopt_array($eligibility_curl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?eligibility_id=eq." . $eligibilityId . "&select=donor_id,blood_type,donation_type,blood_pressure,pulse_rate,body_temp,gen_appearance,skin,heent,heart_and_lungs,body_weight,disapproval_reason,status,created_at,screening_id,medical_history_id&limit=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ]
        ]);
        
        $eligibility_response = curl_exec($eligibility_curl);
        $eligibility_err = curl_error($eligibility_curl);
        curl_close($eligibility_curl);
        
        if ($eligibility_err) {
            error_log("Error fetching eligibility info: " . $eligibility_err);
            return null;
        }
        
        $eligibility_data = json_decode($eligibility_response, true);
        $eligibility_info = !empty($eligibility_data) ? $eligibility_data[0] : null;
        
        if (!$eligibility_info || !isset($eligibility_info['donor_id'])) {
            error_log("No eligibility record found or missing donor_id");
            return null;
        }
        
        $donorId = $eligibility_info['donor_id'];
        error_log("Found donor_id: " . $donorId . " from eligibility_id: " . $eligibilityId);
        
        // Get donor basic information
        $donor_curl = curl_init();
        curl_setopt_array($donor_curl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId . "&select=donor_id,surname,first_name,middle_name,prc_donor_number,birthdate,sex,civil_status,permanent_address,nationality,mobile,occupation&limit=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ]
        ]);
        
        $donor_response = curl_exec($donor_curl);
        $donor_err = curl_error($donor_curl);
        curl_close($donor_curl);
        
        if ($donor_err) {
            error_log("Error fetching donor info: " . $donor_err);
            return null;
        }
        
        $donor_data = json_decode($donor_response, true);
        $donor_info = !empty($donor_data) ? $donor_data[0] : null;
        error_log("Donor info: " . json_encode($donor_info));
        
        // Get screening form data
        $screening_curl = curl_init();
        curl_setopt_array($screening_curl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donorId . "&select=body_weight,blood_type,donation_type,screening_date,created_at&order=created_at.desc&limit=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ]
        ]);
        
        $screening_response = curl_exec($screening_curl);
        $screening_err = curl_error($screening_curl);
        curl_close($screening_curl);
        
        if ($screening_err) {
            error_log("Error fetching screening info: " . $screening_err);
        }
        
        $screening_data = json_decode($screening_response, true);
        $screening_info = !empty($screening_data) ? $screening_data[0] : null;
        error_log("Screening info: " . json_encode($screening_info));
        
        // If no data found with donor_form_id, try with donor_id
        if (empty($screening_info)) {
            error_log("No screening data found with donor_form_id, trying donor_id...");
            $screening_curl2 = curl_init();
            curl_setopt_array($screening_curl2, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_id=eq." . $donorId . "&select=body_weight,blood_type,donation_type,screening_date,created_at&order=created_at.desc&limit=1",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ]
            ]);
            
            $screening_response2 = curl_exec($screening_curl2);
            $screening_err2 = curl_error($screening_curl2);
            curl_close($screening_curl2);
            
            if (!$screening_err2) {
                $screening_data2 = json_decode($screening_response2, true);
                $screening_info = !empty($screening_data2) ? $screening_data2[0] : null;
                error_log("Screening data with donor_id: " . json_encode($screening_info));
            }
        }
        
        // Get medical history data
        $medical_curl = curl_init();
        curl_setopt_array($medical_curl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/medical_history?donor_id=eq." . $donorId . "&select=medical_approval,needs_review,created_at&order=created_at.desc&limit=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ]
        ]);
        
        $medical_response = curl_exec($medical_curl);
        $medical_err = curl_error($medical_curl);
        curl_close($medical_curl);
        
        if ($medical_err) {
            error_log("Error fetching medical history info: " . $medical_err);
        }
        
        $medical_data = json_decode($medical_response, true);
        $medical_info = !empty($medical_data) ? $medical_data[0] : null;
        error_log("Medical info: " . json_encode($medical_info));
        
        // Combine all data
        $combined_data = [];
        if ($eligibility_info) {
            $combined_data = array_merge($combined_data, $eligibility_info);
        }
        if ($donor_info) {
            $combined_data = array_merge($combined_data, $donor_info);
        }
        if ($screening_info) {
            $combined_data = array_merge($combined_data, $screening_info);
        }
        if ($medical_info) {
            $combined_data = array_merge($combined_data, $medical_info);
        }
        
        // Generate medical history result and concise interviewer/phys exam notes
        $combined_data['medical_approval'] = determineMedicalHistoryResult($combined_data);
        $combined_data['medical_notes'] = generateConciseInterviewerNotes($combined_data);
        $combined_data['physical_exam_notes'] = generateConcisePhysicalExamNotes($combined_data);
        
        // Determine screening status
        $combined_data['screening_status'] = determineScreeningStatus($combined_data);
        
        error_log("Final combined medical data: " . json_encode($combined_data));
        
        return !empty($combined_data) ? $combined_data : null;
        
    } catch (Exception $e) {
        error_log("Exception in fetchDonorMedicalInfo: " . $e->getMessage());
        return null;
    }
}

// Function to determine medical history result based on eligibility data
function determineMedicalHistoryResult($data) {
    // User requirement: if eligibility has both screening_id and medical_history_id → Approved
    $hasScreening = !empty($data['screening_id']);
    $hasMedicalHistory = !empty($data['medical_history_id']);
    if ($hasScreening && $hasMedicalHistory) {
        return 'Approved';
    }

    // Previous fallback: infer from basic fields
    $body_weight = $data['body_weight'] ?? '';
    $blood_type = $data['blood_type'] ?? '';
    if (!empty($body_weight) && !empty($blood_type)) {
        return 'Approved';
    }
    return 'Pending';
}

// Concise interviewer notes
function generateConciseInterviewerNotes($data) {
    $approval = determineMedicalHistoryResult($data);
    if ($approval === 'Approved') {
        return 'Approved.';
    }
    return 'Pending.';
}

// Concise physical exam notes
function generateConcisePhysicalExamNotes($data) {
    $parts = [];
    if (!empty($data['gen_appearance'])) $parts[] = 'Appearance: ' . $data['gen_appearance'];
    if (!empty($data['skin'])) $parts[] = 'Skin: ' . $data['skin'];
    if (!empty($data['heent'])) $parts[] = 'HEENT: ' . $data['heent'];
    if (!empty($data['heart_and_lungs'])) $parts[] = 'Heart/Lungs: ' . $data['heart_and_lungs'];
    if (empty($parts)) return 'N/A';
    return implode('. ', $parts) . '.';
}

// Function to determine screening status
function determineScreeningStatus($data) {
    $body_weight = $data['body_weight'] ?? '';
    $blood_type = $data['blood_type'] ?? '';
    $donation_type = $data['donation_type'] ?? '';
    $blood_pressure = $data['blood_pressure'] ?? '';
    $pulse_rate = $data['pulse_rate'] ?? '';
    $body_temp = $data['body_temp'] ?? '';
    
    // Check if there's any vital signs information to summarize
    $hasVitalSigns = !empty($blood_pressure) || !empty($pulse_rate) || !empty($body_temp) || !empty($body_weight) || !empty($blood_type);
    
    if ($hasVitalSigns) {
        return 'Success';
    } else {
        return 'Pending';
    }
}

// Handle the API request
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['eligibility_id'])) {
        $eligibilityId = $_GET['eligibility_id'];
        
        if (empty($eligibilityId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Eligibility ID is required'
            ]);
            exit;
        }
        
        error_log("Fetching donor medical info for eligibility_id: " . $eligibilityId);
        $donorMedicalData = fetchDonorMedicalInfo($eligibilityId);
        
        if ($donorMedicalData) {
            echo json_encode([
                'success' => true,
                'data' => $donorMedicalData
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No donor medical information found'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method or missing eligibility_id parameter'
        ]);
    }
} catch (Exception $e) {
    error_log("Exception in API handler: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
