<?php
// Disable error display to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

require_once '../conn/db_conn.php';

// Function to generate intelligent interviewer remarks based on medical history
function generateInterviewerRemarks($data) {
    $remarks = [];
    
    // Medical approval assessment
    $medical_approval = $data['medical_approval'] ?? '';
    $needs_review = $data['needs_review'] ?? false;
    
    if ($medical_approval) {
        if ($medical_approval === 'approved' || $medical_approval === 'approved') {
            $remarks[] = "Medical history approved for donation";
        } elseif ($medical_approval === 'rejected' || $medical_approval === 'rejected') {
            $remarks[] = "Medical history indicates rejection - donor not eligible";
        } else {
            $remarks[] = "Medical history status: {$medical_approval}";
        }
    }
    
    // Needs review assessment
    if ($needs_review === true || $needs_review === 'true' || $needs_review === 1) {
        $remarks[] = "Medical history requires additional review before final approval";
    } elseif ($needs_review === false || $needs_review === 'false' || $needs_review === 0) {
        $remarks[] = "Medical history review completed";
    }
    
    // Blood type confirmation
    $blood_type = $data['blood_type'] ?? '';
    if ($blood_type) {
        $remarks[] = "Blood type {$blood_type} confirmed";
    }
    
    // Body weight assessment
    $body_weight = floatval($data['body_weight'] ?? 0);
    if ($body_weight > 0) {
        if ($body_weight < 50) {
            $remarks[] = "Underweight donor (below 50kg) - requires careful monitoring";
        } elseif ($body_weight >= 50 && $body_weight <= 100) {
            $remarks[] = "Normal weight range - suitable for donation";
        } else {
            $remarks[] = "Overweight donor - assess cardiovascular risk";
        }
    }
    
    return !empty($remarks) ? implode('. ', $remarks) . '.' : 'No specific medical assessment remarks noted';
}

// Function to generate physical exam notes from examination findings
function generatePhysicalExamNotes($data) {
    $notes = [];
    
    // General appearance
    $gen_appearance = $data['gen_appearance'] ?? '';
    if ($gen_appearance && $gen_appearance !== 'N/A' && $gen_appearance !== '') {
        $notes[] = "General appearance: {$gen_appearance}";
    }
    
    // Skin examination
    $skin = $data['skin'] ?? '';
    if ($skin && $skin !== 'N/A' && $skin !== '') {
        $notes[] = "Skin examination: {$skin}";
    }
    
    // HEENT examination
    $heent = $data['heent'] ?? '';
    if ($heent && $heent !== 'N/A' && $heent !== '') {
        $notes[] = "HEENT (Head, Eyes, Ears, Nose, Throat): {$heent}";
    }
    
    // Heart and lungs examination
    $heart_and_lungs = $data['heart_and_lungs'] ?? '';
    if ($heart_and_lungs && $heart_and_lungs !== 'N/A' && $heart_and_lungs !== '') {
        $notes[] = "Heart and lungs: {$heart_and_lungs}";
    }
    
    return !empty($notes) ? implode('. ', $notes) . '.' : 'No specific physical examination findings noted.';
}

// Function to fetch physical examination information for a donor
function fetchPhysicalExaminationInfo($donorId) {
    // First, get donor information
    $donor_curl = curl_init();
    curl_setopt_array($donor_curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId . "&select=donor_id,surname,first_name,middle_name,prc_donor_number,birthdate,sex&limit=1",
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
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donorId . "&order=created_at.desc&limit=1",
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
        return null;
    }
    
    $screening_data = json_decode($screening_response, true);
    $screening_info = !empty($screening_data) ? $screening_data[0] : null;
    error_log("Screening query URL: " . SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donorId . "&order=created_at.desc&limit=1");
    error_log("Screening response: " . $screening_response);
    error_log("Screening data: " . json_encode($screening_data));
    error_log("Screening info: " . json_encode($screening_info));
    
    // If no data found with donor_form_id, try with donor_id
    if (empty($screening_info)) {
        error_log("No screening data found with donor_form_id, trying donor_id...");
        $screening_curl2 = curl_init();
        curl_setopt_array($screening_curl2, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_id=eq." . $donorId . "&order=created_at.desc&limit=1",
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
    
    // Get physical examination data
    $physical_curl = curl_init();
    curl_setopt_array($physical_curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $donorId . "&select=blood_pressure,pulse_rate,body_temp,gen_appearance,skin,heent,heart_and_lungs,reason,status,disapproval_reason,remarks,created_at&order=created_at.desc&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ]
    ]);
    
    $physical_response = curl_exec($physical_curl);
    $physical_err = curl_error($physical_curl);
    curl_close($physical_curl);
    
    if ($physical_err) {
        error_log("Error fetching physical examination info: " . $physical_err);
        return null;
    }
    
    $physical_exam = json_decode($physical_response, true);
    $physical_info = !empty($physical_exam) ? $physical_exam[0] : null;
    error_log("Physical info: " . json_encode($physical_info));
    
    // Get medical history data
    $medical_curl = curl_init();
    curl_setopt_array($medical_curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/medical_history?donor_id=eq." . $donorId . "&select=medical_approval,needs_review&order=created_at.desc&limit=1",
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
    if ($donor_info) {
        $combined_data = array_merge($combined_data, $donor_info);
    }
    if ($screening_info) {
        $combined_data = array_merge($combined_data, $screening_info);
    }
    if ($physical_info) {
        $combined_data = array_merge($combined_data, $physical_info);
    }
    if ($medical_info) {
        $combined_data = array_merge($combined_data, $medical_info);
    }
    
    // Generate intelligent interviewer remarks based on medical history
    $combined_data['reason'] = generateInterviewerRemarks($combined_data);
    
    // Generate physical exam notes from examination findings
    $combined_data['physical_exam_notes'] = generatePhysicalExamNotes($combined_data);
    
    error_log("Final combined data: " . json_encode($combined_data));
    
    return !empty($combined_data) ? $combined_data : null;
}

// Handle direct calls to this script with eligibility_id parameter
if (isset($_GET['eligibility_id'])) {
    $eligibilityId = $_GET['eligibility_id'];
    
    // Get eligibility data with donor information joined (ONLY physician from physical_examination)
    $eligibility_curl = curl_init();
    curl_setopt_array($eligibility_curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?eligibility_id=eq." . $eligibilityId . "&select=*,blood_pressure,pulse_rate,body_temp,body_weight,gen_appearance,skin,heent,heart_and_lungs,donor_form(surname,first_name,middle_name,prc_donor_number,birthdate,sex),physical_examination(physician)&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ]
    ]);
    
    $eligibility_response = curl_exec($eligibility_curl);
    $eligibility_err = curl_error($eligibility_curl);
    $http_code = curl_getinfo($eligibility_curl, CURLINFO_HTTP_CODE);
    curl_close($eligibility_curl);
    
    header('Content-Type: application/json');
    
    if ($eligibility_err) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching eligibility data: ' . $eligibility_err
        ]);
        exit;
    }
    
    if ($http_code !== 200) {
        echo json_encode([
            'success' => false,
            'message' => 'HTTP Error ' . $http_code . ': ' . $eligibility_response
        ]);
        exit;
    }
    
    $eligibility_data = json_decode($eligibility_response, true);
    
    // Debug: Log the raw response from Supabase
    error_log("Raw Supabase response: " . $eligibility_response);
    error_log("Decoded eligibility data: " . json_encode($eligibility_data));
    
    if (!empty($eligibility_data)) {
        $eligibility_info = $eligibility_data[0];
        
        // Flatten the nested data structure
        $combined_data = $eligibility_info;
        
        // Extract donor_form data
        if (isset($eligibility_info['donor_form'])) {
            $donor_form = $eligibility_info['donor_form'];
            $combined_data['surname'] = $donor_form['surname'] ?? '';
            $combined_data['first_name'] = $donor_form['first_name'] ?? '';
            $combined_data['middle_name'] = $donor_form['middle_name'] ?? '';
            $combined_data['prc_donor_number'] = $donor_form['prc_donor_number'] ?? '';
            $combined_data['birthdate'] = $donor_form['birthdate'] ?? '';
            $combined_data['sex'] = $donor_form['sex'] ?? '';
            // blood_type comes from eligibility table, not donor_form
        }
        
        // Extract ONLY physician from physical_examination data
        if (isset($eligibility_info['physical_examination'])) {
            $physical_exam = $eligibility_info['physical_examination'];
            $combined_data['physician'] = $physical_exam['physician'] ?? '';
        }
        
        // All other data comes from eligibility table (body_temp, body_weight, skin, heent, heart_and_lungs, etc.)
        // These are already in $eligibility_info from the main query
        
        // Generate intelligent interviewer remarks based on physical data
        $combined_data['reason'] = generateInterviewerRemarks($combined_data);
        
        // Generate physical exam notes from examination findings
        $combined_data['physical_exam_notes'] = generatePhysicalExamNotes($combined_data);
        
        // Debug: Log the data being returned
        error_log("Combined data being returned: " . json_encode($combined_data));
        error_log("Available fields in eligibility record: " . implode(', ', array_keys($combined_data)));
        
        echo json_encode([
            'success' => true,
            'data' => $combined_data,
            'debug' => [
                'eligibility_id' => $eligibilityId,
                'available_fields' => array_keys($combined_data),
                'has_blood_pressure' => isset($combined_data['blood_pressure']),
                'has_pulse_rate' => isset($combined_data['pulse_rate']),
                'has_body_temp' => isset($combined_data['body_temp']),
                'has_body_weight' => isset($combined_data['body_weight']),
                'has_skin' => isset($combined_data['skin']),
                'has_heent' => isset($combined_data['heent']),
                'has_heart_and_lungs' => isset($combined_data['heart_and_lungs']),
                'has_gen_appearance' => isset($combined_data['gen_appearance']),
                'blood_pressure_value' => $combined_data['blood_pressure'] ?? 'NOT_SET',
                'pulse_rate_value' => $combined_data['pulse_rate'] ?? 'NOT_SET',
                'body_temp_value' => $combined_data['body_temp'] ?? 'NOT_SET',
                'body_weight_value' => $combined_data['body_weight'] ?? 'NOT_SET'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No eligibility record found with ID: ' . $eligibilityId
        ]);
    }
}
// Handle direct calls to this script with donor_id parameter (legacy support)
elseif (isset($_GET['donor_id'])) {
    $donorId = $_GET['donor_id'];
    $physicalExamInfo = fetchPhysicalExaminationInfo($donorId);
    
    header('Content-Type: application/json');
    if ($physicalExamInfo) {
        echo json_encode([
            'success' => true,
            'data' => $physicalExamInfo
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No physical examination record found for this donor'
        ]);
    }
}
?>
