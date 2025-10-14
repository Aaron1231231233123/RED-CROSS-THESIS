<?php
session_start();
require_once '../conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if donor_id parameter exists
if (!isset($_GET['donor_id']) || empty($_GET['donor_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing donor ID parameter']);
    exit();
}

$donor_id = intval($_GET['donor_id']);

// Enable output compression for faster transfer
if (!ob_start('ob_gzhandler')) ob_start();

try {
    // Initialize curl multi handle for parallel requests
    $mh = curl_multi_init();
    $handles = [];
    
    // 1. Fetch donor information from donor_form table
    $ch1 = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,age,sex,civil_status,permanent_address,nationality,occupation,mobile,email,submitted_at,prc_donor_number&donor_id=eq.' . $donor_id);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    curl_setopt($ch1, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch1, CURLOPT_CONNECTTIMEOUT, 5);
    curl_multi_add_handle($mh, $ch1);
    $handles['donor_form'] = $ch1;
    
    // 2. Fetch ALL donor records for donation history (only needed fields)
    $ch2 = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,submitted_at,blood_type,mobile,donation_type&order=submitted_at.desc');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
    curl_multi_add_handle($mh, $ch2);
    $handles['all_donors'] = $ch2;
    
    // 3. Fetch eligibility information with physician from physical examination
    $ch3 = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donor_id . '&select=*,physical_examination(physician)&order=created_at.desc');
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    curl_setopt($ch3, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch3, CURLOPT_CONNECTTIMEOUT, 5);
    curl_multi_add_handle($mh, $ch3);
    $handles['eligibility'] = $ch3;
    
    // 4. Fetch medical history needs_review
    $ch4 = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id . '&select=needs_review&order=created_at.desc&limit=1');
    curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch4, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    curl_setopt($ch4, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch4, CURLOPT_CONNECTTIMEOUT, 5);
    curl_multi_add_handle($mh, $ch4);
    $handles['medical_history'] = $ch4;
    
    // 5. Fetch physical examination data (deferral status)
    $ch5 = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=temporarily_deferred,permanently_deferred,refuse,temp_deferral_reason,perm_deferral_reason,refuse_reason,remarks,created_at&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch5, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch5, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    curl_setopt($ch5, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch5, CURLOPT_CONNECTTIMEOUT, 5);
    curl_multi_add_handle($mh, $ch5);
    $handles['physical_exam'] = $ch5;
    
    // 6. Fetch screening information
    $ch6 = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=screening_id,interview_date,body_weight,specific_gravity,blood_type,created_at&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch6, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch6, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    curl_setopt($ch6, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch6, CURLOPT_CONNECTTIMEOUT, 5);
    curl_multi_add_handle($mh, $ch6);
    $handles['screening'] = $ch6;
    
    // Execute all requests in parallel
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);
    
    // Collect responses
    $responses = [];
    foreach ($handles as $key => $ch) {
        $responses[$key] = [
            'content' => curl_multi_getcontent($ch),
            'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    
    // Process donor_form response
    if ($responses['donor_form']['http_code'] !== 200) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching donor data',
            'http_code' => $responses['donor_form']['http_code']
        ]);
        ob_end_flush();
        exit();
    }
    
    $donor_data = json_decode($responses['donor_form']['content'], true);
    if (!is_array($donor_data) || empty($donor_data)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Donor not found']);
        ob_end_flush();
        exit();
    }
    
    $current_donor = $donor_data[0];
    
    // Process donation history
    $donation_count = 0;
    $latest_submission = null;
    $donation_history = [];
    
    if ($responses['all_donors']['http_code'] === 200) {
        $all_donors = json_decode($responses['all_donors']['content'], true) ?: [];
        
        // Create key for matching donors
        $key = ($current_donor['surname'] ?? '') . '|' . 
               ($current_donor['first_name'] ?? '') . '|' . 
               ($current_donor['middle_name'] ?? '') . '|' . 
               ($current_donor['birthdate'] ?? '');
        
        foreach ($all_donors as $donor) {
            $donor_key = ($donor['surname'] ?? '') . '|' . 
                       ($donor['first_name'] ?? '') . '|' . 
                       ($donor['middle_name'] ?? '') . '|' . 
                       ($donor['birthdate'] ?? '');
            
            if ($donor_key === $key) {
                $donation_count++;
                $donation_history[] = [
                    'donor_id' => $donor['donor_id'],
                    'date' => $donor['submitted_at'],
                    'blood_type' => $donor['blood_type'] ?? 'Unknown',
                    'donation_type' => $donor['donation_type'] ?? 'Unknown',
                    'contact' => $donor['mobile'] ?? 'Not provided'
                ];
                
                if (!$latest_submission || $donor['submitted_at'] > $latest_submission) {
                    $latest_submission = $donor['submitted_at'];
                }
            }
        }
        
        // Sort donation history by date (newest first)
        usort($donation_history, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
    }
    
    // Process eligibility data
    $eligibility_info = [];
    if ($responses['eligibility']['http_code'] === 200) {
        $eligibility_data = json_decode($responses['eligibility']['content'], true);
        $eligibility_info = !empty($eligibility_data) ? $eligibility_data : [];
    }
    
    // Process medical history needs_review
    $needs_review = false;
    if ($responses['medical_history']['http_code'] === 200) {
        $medical_data = json_decode($responses['medical_history']['content'], true);
        if (!empty($medical_data) && isset($medical_data[0]['needs_review'])) {
            $needs_review = $medical_data[0]['needs_review'];
        }
    }
    
    // Process deferral status
    $deferral_info = [
        'isDeferred' => false,
        'isRefused' => false,
        'deferralType' => null,
        'reason' => null,
        'examDate' => null,
        'remarks' => null
    ];
    
    if ($responses['physical_exam']['http_code'] === 200) {
        $physical_exam_data = json_decode($responses['physical_exam']['content'], true);
        
        if (is_array($physical_exam_data) && !empty($physical_exam_data)) {
            $latest_exam = $physical_exam_data[0];
            
            // Check for permanent deferral first (highest priority)
            if (isset($latest_exam['permanently_deferred']) && $latest_exam['permanently_deferred'] === true) {
                $deferral_info['isDeferred'] = true;
                $deferral_info['deferralType'] = 'permanently_deferred';
                $deferral_info['reason'] = $latest_exam['perm_deferral_reason'] ?? null;
            }
            // Check for temporary deferral
            else if (isset($latest_exam['temporarily_deferred']) && $latest_exam['temporarily_deferred'] === true) {
                $deferral_info['isDeferred'] = true;
                $deferral_info['deferralType'] = 'temporarily_deferred';
                $deferral_info['reason'] = $latest_exam['temp_deferral_reason'] ?? null;
            }
            // Check for refusal
            else if (isset($latest_exam['refuse']) && $latest_exam['refuse'] === true) {
                $deferral_info['isRefused'] = true;
                $deferral_info['reason'] = $latest_exam['refuse_reason'] ?? null;
            }
            // If not deferred or refused by boolean flags, check the remarks field
            else if (isset($latest_exam['remarks'])) {
                $remarks = $latest_exam['remarks'];
                
                switch ($remarks) {
                    case 'Permanently Deferred':
                        $deferral_info['isDeferred'] = true;
                        $deferral_info['deferralType'] = 'permanently_deferred';
                        $deferral_info['reason'] = $deferral_info['reason'] ?? 'Based on physician remarks';
                        break;
                    case 'Temporarily Deferred':
                        $deferral_info['isDeferred'] = true;
                        $deferral_info['deferralType'] = 'temporarily_deferred';
                        $deferral_info['reason'] = $deferral_info['reason'] ?? 'Based on physician remarks';
                        break;
                    case 'Refused':
                        $deferral_info['isRefused'] = true;
                        $deferral_info['reason'] = $deferral_info['reason'] ?? 'Based on physician remarks';
                        break;
                }
            }
            
            $deferral_info['examDate'] = $latest_exam['created_at'] ?? null;
            $deferral_info['remarks'] = $latest_exam['remarks'] ?? null;
        }
    }
    
    // Process screening data
    $screening_info = null;
    if ($responses['screening']['http_code'] === 200) {
        $screening_data = json_decode($responses['screening']['content'], true);
        if (is_array($screening_data) && !empty($screening_data)) {
            $latest_screening = $screening_data[0];
            $screening_info = [
                'screening_id' => $latest_screening['screening_id'] ?? null,
                'interview_date' => $latest_screening['interview_date'] ?? $latest_screening['created_at'] ?? null,
                'body_weight' => $latest_screening['body_weight'] ?? null,
                'specific_gravity' => $latest_screening['specific_gravity'] ?? null,
                'blood_type' => $latest_screening['blood_type'] ?? null
            ];
        }
    }
    
    // Assemble complete response
    $current_donor['donation_count'] = $donation_count;
    $current_donor['latest_submission'] = $latest_submission;
    $current_donor['donation_history'] = $donation_history;
    $current_donor['eligibility'] = $eligibility_info;
    $current_donor['needs_review'] = $needs_review;
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode([
        'success' => true,
        'data' => $current_donor,
        'deferral' => $deferral_info,
        'screening' => $screening_info
    ]);
    ob_end_flush();
    exit();
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit();
}
?>



