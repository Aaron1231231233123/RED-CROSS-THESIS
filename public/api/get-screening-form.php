<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get donor_id from query parameters
$donor_id = $_GET['donor_id'] ?? null;

if (!$donor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing donor_id parameter']);
    exit;
}

try {
    // Include database connection
    require_once '../../assets/conn/db_conn.php';
    
    // Query the screening_form table for the donor using Supabase cURL
    // The screening_form table uses donor_form_id, not donor_id
    // First try: direct donor_form_id relationship
    $url = SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1';
    
    // Log the URL being queried
    error_log("Screening form query URL (donor_form_id): " . $url);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception('Failed to fetch screening form data');
    }
    
    $result = json_decode($response, true);
    
    // Log the response for debugging
    error_log("Screening form API response for donor_id $donor_id: " . json_encode($result));
    
    // If no result found with donor_form_id, try medical_history relationship
    if (empty($result)) {
        error_log("No screening form found with donor_form_id, trying to find through medical_history");
        
        // First, get the medical_history_id for this donor
        $medicalUrl = SUPABASE_URL . '/rest/v1/medical_history?donor_form_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1';
        error_log("Medical history query URL: " . $medicalUrl);
        
        $ch3 = curl_init($medicalUrl);
        curl_setopt_array($ch3, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        
        $medicalResponse = curl_exec($ch3);
        curl_close($ch3);
        
        if ($medicalResponse !== false) {
            $medicalResult = json_decode($medicalResponse, true);
            error_log("Medical history API response for donor_id $donor_id: " . json_encode($medicalResult));
            
            if (!empty($medicalResult)) {
                $medicalHistoryId = $medicalResult[0]['medical_history_id'] ?? null;
                if ($medicalHistoryId) {
                    error_log("Found medical_history_id: $medicalHistoryId, now searching for screening_form");
                    
                    // Now search for screening_form using medical_history_id
                    $screeningUrl = SUPABASE_URL . '/rest/v1/screening_form?medical_history_id=eq.' . urlencode($medicalHistoryId) . '&order=created_at.desc&limit=1';
                    error_log("Screening form query URL (medical_history_id): " . $screeningUrl);
                    
                    $ch4 = curl_init($screeningUrl);
                    curl_setopt_array($ch4, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY
                        ]
                    ]);
                    
                    $screeningResponse = curl_exec($ch4);
                    curl_close($ch4);
                    
                    if ($screeningResponse !== false) {
                        $result = json_decode($screeningResponse, true);
                        error_log("Screening form API response (medical_history_id) for donor_id $donor_id: " . json_encode($result));
                    }
                }
            }
        }
    }
    
    if (!empty($result)) {
        $screeningData = $result[0];
        echo json_encode([
            'success' => true,
            'screening_form' => [
                'screening_id' => $screeningData['screening_id'] ?? null,
                'donor_form_id' => $screeningData['donor_form_id'] ?? null,
                'medical_history_id' => $screeningData['medical_history_id'] ?? null,
                'interviewer_id' => $screeningData['interviewer_id'] ?? null,
                'body_weight' => $screeningData['body_weight'] ?? null,
                'specific_gravity' => $screeningData['specific_gravity'] ?? null,
                'blood_type' => $screeningData['blood_type'] ?? null,
                'mobile_organizer' => $screeningData['mobile_organizer'] ?? null,
                'patient_name' => $screeningData['patient_name'] ?? null,
                'hospital' => $screeningData['hospital'] ?? null,
                'patient_blood_type' => $screeningData['patient_blood_type'] ?? null,
                'component_type' => $screeningData['component_type'] ?? null,
                'units_needed' => $screeningData['units_needed'] ?? null,
                'has_previous_donation' => $screeningData['has_previous_donation'] ?? null,
                'red_cross_donations' => $screeningData['red_cross_donations'] ?? null,
                'hospital_donations' => $screeningData['hospital_donations'] ?? null,
                'last_rc_donation_date' => $screeningData['last_rc_donation_date'] ?? null,
                'last_hosp_donation_date' => $screeningData['last_hosp_donation_date'] ?? null,
                'last_rc_donation_place' => $screeningData['last_rc_donation_place'] ?? null,
                'last_hosp_donation_place' => $screeningData['last_hosp_donation_place'] ?? null,
                'interview_date' => $screeningData['interview_date'] ?? null,
                'disapproval_reason' => $screeningData['disapproval_reason'] ?? null,
                'mobile_location' => $screeningData['mobile_location'] ?? null,
                'donation_type' => $screeningData['donation_type'] ?? null,
                'needs_review' => $screeningData['needs_review'] ?? null,
                'staff' => $screeningData['staff'] ?? null
            ]
        ]);
    } else {
        // Return default values if no screening form found
        $defaultData = [
            'screening_id' => null,
            'donor_form_id' => null,
            'medical_history_id' => null,
            'interviewer_id' => null,
            'body_weight' => null,
            'specific_gravity' => null,
            'blood_type' => null,
            'mobile_organizer' => null,
            'patient_name' => null,
            'hospital' => null,
            'patient_blood_type' => null,
            'component_type' => null,
            'units_needed' => null,
            'has_previous_donation' => null,
            'red_cross_donations' => null,
            'hospital_donations' => null,
            'last_rc_donation_date' => null,
            'last_hosp_donation_date' => null,
            'last_rc_donation_place' => null,
            'last_hosp_donation_place' => null,
            'interview_date' => null,
            'disapproval_reason' => null,
            'mobile_location' => null,
            'donation_type' => null,
            'needs_review' => null,
            'staff' => null
        ];
        
        echo json_encode([
            'success' => true,
            'screening_form' => $defaultData
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error fetching screening form data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
