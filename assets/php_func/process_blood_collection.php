<?php
session_start();
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $required_fields = [
        'physical_exam_id',
        'donor_id',
        'blood_bag_type',
        'amount_taken',
        'is_successful',
        'start_time',
        'end_time',
        'unit_serial_number'
    ];

    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || ($input[$field] === '' && $field !== 'donor_reaction' && $field !== 'management_done')) {
            throw new Exception("Field $field is required");
        }
    }

    // Validate blood bag format
    $blood_bag_parts = explode('-', $input['blood_bag_type']);
    if (count($blood_bag_parts) < 2) {
        throw new Exception('Invalid blood bag format');
    }

    // Get the brand (last part) and type (everything before the brand)
    $blood_bag_brand = end($blood_bag_parts);
    array_pop($blood_bag_parts);
    $blood_bag_type = implode('-', $blood_bag_parts);

    // Validate blood bag brand
    $valid_brands = ['KARMI', 'TERUMO', 'SPECIAL BAG', 'APHERESIS'];
    if (!in_array($blood_bag_brand, $valid_brands)) {
        throw new Exception('Invalid blood bag brand');
    }

    // Validate amount
    $amount_taken = intval($input['amount_taken']);
    if ($amount_taken <= 0 || $amount_taken > 999) {
        throw new Exception('Amount must be between 1 and 999');
    }

    // Validate is_successful
    $is_successful = filter_var($input['is_successful'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($is_successful === null) {
        throw new Exception('Invalid success status');
    }

    // Check if unit serial number already exists
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?unit_serial_number=eq.' . urlencode($input['unit_serial_number']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $existing_unit = json_decode($response, true);
    if (!empty($existing_unit)) {
        throw new Exception('This unit serial number is already in use. Please use a unique serial number.');
    }
    
    // Check if blood collection already exists for this physical exam
    $physical_exam_check_ch = curl_init();
    curl_setopt_array($physical_exam_check_ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?physical_exam_id=eq.' . urlencode($input['physical_exam_id']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    $physical_exam_check_response = curl_exec($physical_exam_check_ch);
    curl_close($physical_exam_check_ch);

    $existing_collection = json_decode($physical_exam_check_response, true);
    if (!empty($existing_collection)) {
        throw new Exception('Blood collection already exists for this physical examination.');
    }

    // Convert times to proper timestamp format
    $today = date('Y-m-d');
    $start_timestamp = date('Y-m-d\TH:i:s.000\Z', strtotime("$today " . $input['start_time']));
    $end_timestamp = date('Y-m-d\TH:i:s.000\Z', strtotime("$today " . $input['end_time']));

    // Get the latest screening_id that doesn't have a blood collection yet
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=screening_id&order=created_at.desc',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $screening_data = json_decode($response, true);
    if (empty($screening_data)) {
        throw new Exception('No screening form found');
    }

    // Find the first screening ID that doesn't have a blood collection
    $screening_id = null;
    foreach ($screening_data as $screening) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?screening_id=eq.' . $screening['screening_id'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $existing_collection = json_decode($response, true);
        if (empty($existing_collection)) {
            $screening_id = $screening['screening_id'];
            break;
        }
    }

    if ($screening_id === null) {
        throw new Exception('No available screening form found for blood collection');
    }

    // Prepare data for Supabase
    $data = [
        'screening_id' => $screening_id,
        'physical_exam_id' => $input['physical_exam_id'],
        'blood_bag_brand' => $blood_bag_brand,
        'blood_bag_type' => $blood_bag_type,
        'amount_taken' => $amount_taken,
        'is_successful' => $is_successful,
        'donor_reaction' => !empty($input['donor_reaction']) ? trim($input['donor_reaction']) : null,
        'management_done' => !empty($input['management_done']) ? trim($input['management_done']) : null,
        'unit_serial_number' => $input['unit_serial_number'],
        'start_time' => $start_timestamp,
        'end_time' => $end_timestamp,
        'status' => 'pending'
    ];

    // Send data to Supabase
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 201) {
        // Parse the response to get the blood collection ID
        $collection_response = json_decode($response, true);
        $blood_collection_id = $collection_response[0]['blood_collection_id'] ?? null;
        
        if ($blood_collection_id) {
            // Check if eligibility record already exists for this physical exam
            $eligibility_check_ch = curl_init();
            curl_setopt_array($eligibility_check_ch, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?physical_exam_id=eq." . urlencode($input['physical_exam_id']),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]
            ]);
            $eligibility_check_response = curl_exec($eligibility_check_ch);
            curl_close($eligibility_check_ch);
            
            $existing_eligibility = json_decode($eligibility_check_response, true);
            if (!empty($existing_eligibility)) {
                // Eligibility record already exists, don't create another
                echo json_encode([
                    'success' => true,
                    'message' => 'Blood collection recorded successfully (eligibility already exists)',
                    'blood_collection_id' => $blood_collection_id
                ]);
                exit;
            }
            
            // Create eligibility record
            $screening_ch = curl_init();
            curl_setopt_array($screening_ch, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?screening_id=eq." . $screening_id . "&select=donor_form_id,medical_history_id,blood_type,donation_type",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]
            ]);
            $screening_response = curl_exec($screening_ch);
            curl_close($screening_ch);
            
            $screening_info = json_decode($screening_response, true);
            
            if (!empty($screening_info)) {
                $donor_id = $screening_info[0]['donor_form_id'] ?? $input['donor_id'];
                $medical_history_id = $screening_info[0]['medical_history_id'] ?? null;
                $blood_type = $screening_info[0]['blood_type'] ?? null;
                $donation_type = $screening_info[0]['donation_type'] ?? null;
                
                // Calculate end date
                $end_date = new DateTime();
                if ($is_successful) {
                    $end_date->modify('+9 months');
                } else {
                    $end_date->modify('+3 months');
                }
                $end_date_formatted = $end_date->format('Y-m-d\TH:i:s.000\Z');
                
                $status = $is_successful ? 'approved' : 'failed_collection';
                
                $eligibility_data = [
                    'donor_id' => $donor_id,
                    'medical_history_id' => $medical_history_id,
                    'screening_id' => $screening_id,
                    'physical_exam_id' => $input['physical_exam_id'],
                    'blood_collection_id' => $blood_collection_id,
                    'blood_type' => $blood_type,
                    'donation_type' => $donation_type,
                    'blood_bag_type' => $blood_bag_type,
                    'blood_bag_brand' => $blood_bag_brand,
                    'amount_collected' => $amount_taken,
                    'collection_successful' => $is_successful,
                    'donor_reaction' => !empty($input['donor_reaction']) ? trim($input['donor_reaction']) : null,
                    'management_done' => !empty($input['management_done']) ? trim($input['management_done']) : null,
                    'collection_start_time' => $start_timestamp,
                    'collection_end_time' => $end_timestamp,
                    'unit_serial_number' => $input['unit_serial_number'],
                    'start_date' => date('Y-m-d\TH:i:s.000\Z'),
                    'end_date' => $end_date_formatted,
                    'status' => $status,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Remove null values
                foreach ($eligibility_data as $key => $value) {
                    if ($value === null) {
                        unset($eligibility_data[$key]);
                    }
                }
                
                // Create eligibility record
                $eligibility_ch = curl_init();
                curl_setopt_array($eligibility_ch, [
                    CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($eligibility_data),
                    CURLOPT_HTTPHEADER => [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json'
                    ]
                ]);
                
                curl_exec($eligibility_ch);
                curl_close($eligibility_ch);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Blood collection recorded successfully',
            'blood_collection_id' => $blood_collection_id
        ]);
    } else {
        throw new Exception('Failed to save blood collection data: ' . $response);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 