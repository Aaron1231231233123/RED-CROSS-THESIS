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

try {
    // Fetch donor information from donor_form table (expanded fields)
    $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select='
        . 'donor_id,surname,first_name,middle_name,birthdate,age,sex,civil_status,permanent_address,'
        . 'nationality,occupation,telephone,mobile,email,submitted_at,prc_donor_number'
        . '&donor_id=eq.' . $donor_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $donor_data = json_decode($response, true);
        
        if (is_array($donor_data) && !empty($donor_data)) {
            $current_donor = $donor_data[0];
            
            // Now get all records for this donor to calculate donation count
            // We match by surname, first_name, middle_name, and birthdate to identify the same person
            $key = ($current_donor['surname'] ?? '') . '|' . 
                   ($current_donor['first_name'] ?? '') . '|' . 
                   ($current_donor['middle_name'] ?? '') . '|' . 
                   ($current_donor['birthdate'] ?? '');
                   
            $ch2 = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=donor_id,submitted_at,blood_type,telephone,mobile,donation_type&order=submitted_at.desc');
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]);
            
            $all_donors_response = curl_exec($ch2);
            curl_close($ch2);
            
            $all_donors = json_decode($all_donors_response, true) ?: [];
            
            // Count donations and find the latest submission
            $donation_count = 0;
            $latest_submission = null;
            $latest_donor_id = null;
            $donation_history = [];
            
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
                        'contact' => ($donor['mobile'] ?? ($donor['telephone'] ?? 'Not provided'))
                    ];
                    
                    if (!$latest_submission || $donor['submitted_at'] > $latest_submission) {
                        $latest_submission = $donor['submitted_at'];
                        $latest_donor_id = $donor['donor_id'];
                    }
                }
            }
            
            // Sort donation history by date (newest first)
            usort($donation_history, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
            
            // Get eligibility information for this donor
            $eligibility_ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donor_id . '&select=*&order=created_at.desc&limit=1');
            curl_setopt($eligibility_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($eligibility_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]);
            
            $eligibility_response = curl_exec($eligibility_ch);
            curl_close($eligibility_ch);
            
            $eligibility_data = json_decode($eligibility_response, true);
            $eligibility_info = !empty($eligibility_data) ? $eligibility_data[0] : null;
            
            // Fetch medical history for the latest donation
            $medical_history = null;
            if ($latest_donor_id) {
                $medical_ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $latest_donor_id . '&select=*&order=created_at.desc&limit=1');
                curl_setopt($medical_ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($medical_ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]);
                
                $medical_response = curl_exec($medical_ch);
                curl_close($medical_ch);
                
                $medical_data = json_decode($medical_response, true);
                $medical_history = !empty($medical_data) ? $medical_data[0] : null;
            }
            
            // Add donation count and latest submission to the donor data
            $current_donor['donation_count'] = $donation_count;
            $current_donor['latest_submission'] = $latest_submission;
            $current_donor['donation_history'] = $donation_history;
            $current_donor['eligibility'] = $eligibility_info;
            $current_donor['medical_history'] = $medical_history;
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $current_donor
            ]);
            exit();
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Donor not found'
            ]);
            exit();
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching donor data',
            'http_code' => $http_code,
            'response' => $response
        ]);
        exit();
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
    exit();
}
?> 