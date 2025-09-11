<?php
session_start();
require_once '../conn/db_conn.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get eligibility_id from request
$eligibility_id = isset($_GET['eligibility_id']) ? $_GET['eligibility_id'] : null;

if (!$eligibility_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing eligibility_id']);
    exit();
}

try {
    // Fetch eligibility data joined with donor_form data
    $ch = curl_init();
    $url = SUPABASE_URL . '/rest/v1/eligibility?select=*,donor_form!inner(*)&eligibility_id=eq.' . $eligibility_id;
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        
        if (is_array($data) && !empty($data)) {
            $eligibility_record = $data[0];
            $donor_data = $eligibility_record['donor_form'];
            
            // Combine eligibility and donor data
            $combined_data = array_merge($eligibility_record, $donor_data);
            
            // Map eligibility fields to physical examination format
            $physical_exam_data = [
                'eligibility_id' => $combined_data['eligibility_id'],
                'donor_id' => $combined_data['donor_id'],
                'surname' => $combined_data['surname'],
                'first_name' => $combined_data['first_name'],
                'middle_name' => $combined_data['middle_name'],
                'birthdate' => $combined_data['birthdate'],
                'sex' => $combined_data['sex'],
                'prc_donor_number' => $combined_data['prc_donor_number'],
                'blood_type' => $combined_data['blood_type'],
                'donation_type' => $combined_data['donation_type'] ?? 'N/A',
                'created_at' => $combined_data['created_at'],
                'screening_date' => $combined_data['start_date'] ?? $combined_data['created_at'],
                'status' => $combined_data['status'],
                'temporary_deferred' => $combined_data['temporary_deferred'],
                'start_date' => $combined_data['start_date'],
                
                // Physical examination fields from eligibility table
                'blood_pressure' => $combined_data['blood_pressure'],
                'body_weight' => $combined_data['body_weight'],
                'pulse_rate' => $combined_data['pulse_rate'],
                'body_temp' => $combined_data['body_temp'],
                'reason' => $combined_data['disapproval_reason'] ?? 'No issues noted',
                // Combine physical examination fields
                'physical_exam_notes' => implode(' | ', array_filter([
                    $combined_data['gen_appearance'] ? 'General Appearance: ' . $combined_data['gen_appearance'] : null,
                    $combined_data['skin'] ? 'Skin: ' . $combined_data['skin'] : null,
                    $combined_data['heent'] ? 'HEENT: ' . $combined_data['heent'] : null,
                    $combined_data['heart_and_lungs'] ? 'Heart & Lungs: ' . $combined_data['heart_and_lungs'] : null
                ])) ?: 'No examination notes',
                // Set remarks based on medical_history_id and screening_form_id presence
                'remarks' => (!empty($combined_data['medical_history_id']) && !empty($combined_data['screening_id'])) ? 'Approved' : 'Pending',
                'disapproval_reason' => $combined_data['disapproval_reason']
            ];
            
            echo json_encode([
                'success' => true,
                'data' => $physical_exam_data
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No eligibility record found'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch eligibility data',
            'http_code' => $http_code
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
