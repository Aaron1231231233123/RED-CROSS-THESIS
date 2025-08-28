<?php
session_start();
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$physical_exam_id = $input['physical_exam_id'] ?? null;

if (!$physical_exam_id) {
    echo json_encode(['success' => false, 'message' => 'Physical exam ID is required']);
    exit;
}

// NOTE: We no longer block based on existing eligibility. History is allowed.

try {
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

    // Derive phlebotomist name from input or session
    $session_first = isset($_SESSION['first_name']) ? trim($_SESSION['first_name']) : '';
    $session_last  = isset($_SESSION['surname']) ? trim($_SESSION['surname']) : '';
    $session_full  = trim($session_first . ' ' . $session_last);
    $phlebotomist_name = !empty($input['phlebotomist']) ? trim($input['phlebotomist']) : $session_full;

    // If still empty, try to fetch from users table using session user id
    if ($phlebotomist_name === '' || $phlebotomist_name === null) {
        $session_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['id']) ? $_SESSION['id'] : null);
        if (!empty($session_user_id)) {
            $u = curl_init();
            // Try common id columns: user_id first, then id
            $users_url = SUPABASE_URL . '/rest/v1/users?select=surname,first_name&user_id=eq.' . urlencode($session_user_id) . '&limit=1';
            curl_setopt_array($u, [
                CURLOPT_URL => $users_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]
            ]);
            $u_resp = curl_exec($u);
            $u_code = curl_getinfo($u, CURLINFO_HTTP_CODE);
            curl_close($u);
            $u_rows = ($u_code === 200) ? json_decode($u_resp, true) : [];

            if (empty($u_rows)) {
                // Fallback try id column
                $u = curl_init();
                $users_url2 = SUPABASE_URL . '/rest/v1/users?select=surname,first_name&id=eq.' . urlencode($session_user_id) . '&limit=1';
                curl_setopt_array($u, [
                    CURLOPT_URL => $users_url2,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY
                    ]
                ]);
                $u_resp = curl_exec($u);
                $u_code = curl_getinfo($u, CURLINFO_HTTP_CODE);
                curl_close($u);
                $u_rows = ($u_code === 200) ? json_decode($u_resp, true) : [];
            }

            if (!empty($u_rows) && isset($u_rows[0])) {
                $uf = trim(($u_rows[0]['first_name'] ?? '') . ' ' . ($u_rows[0]['surname'] ?? ''));
                if ($uf !== '') {
                    $phlebotomist_name = $uf;
                }
            }
        }
    }

    // Convert times to proper timestamp format (needed for both UPDATE and INSERT)
    $today = date('Y-m-d');
    $start_timestamp = date('Y-m-d\TH:i:s.000\Z', strtotime("$today " . $input['start_time']));
    $end_timestamp   = date('Y-m-d\TH:i:s.000\Z', strtotime("$today " . $input['end_time']));
    
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
        // UPDATE path: increment amount_taken and update other fields
        $existing_row = $existing_collection[0];
        $existing_amount = intval($existing_row['amount_taken'] ?? 0);
        $new_amount_total = $existing_amount + $amount_taken;

        // If client generated a new unit_serial_number, ensure it is unique among other rows
        if (!empty($input['unit_serial_number'])) {
            $chk = curl_init();
            curl_setopt_array($chk, [
                CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?unit_serial_number=eq.' . urlencode($input['unit_serial_number']) . '&select=physical_exam_id',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]
            ]);
            $chk_resp = curl_exec($chk);
            curl_close($chk);
            $matches = json_decode($chk_resp, true) ?: [];
            // Allow reuse if the serial belongs to the same physical_exam_id; block if it belongs to a different one
            $conflict = false;
            foreach ($matches as $m) {
                if (isset($m['physical_exam_id']) && (string)$m['physical_exam_id'] !== (string)$input['physical_exam_id']) {
                    $conflict = true; break;
                }
            }
            if ($conflict) {
                throw new Exception('This unit serial number is already in use by a different record.');
            }
        }

        $update_payload = [
            'amount_taken'    => $new_amount_total,
            'is_successful'   => $is_successful,
            'donor_reaction'  => !empty($input['donor_reaction']) ? trim($input['donor_reaction']) : null,
            'management_done' => !empty($input['management_done']) ? trim($input['management_done']) : null,
            'start_time'      => $start_timestamp,
            'end_time'        => $end_timestamp,
            'blood_bag_type'  => $blood_bag_type,
            'blood_bag_brand' => $blood_bag_brand,
            'unit_serial_number' => !empty($input['unit_serial_number']) ? $input['unit_serial_number'] : null,
            'status'          => 'pending',
            'needs_review'    => false,
            'phlebotomist'    => (!empty($phlebotomist_name) ? $phlebotomist_name : null),
            'updated_at'      => date('Y-m-d H:i:s')
        ];

        // Remove null values to avoid overwriting with null
        foreach ($update_payload as $k => $v) { if ($v === null) unset($update_payload[$k]); }

        // Include primary key for upsert
        $update_payload_with_pk = $update_payload;
        if (!empty($existing_row['blood_collection_id'])) {
            $update_payload_with_pk['blood_collection_id'] = $existing_row['blood_collection_id'];
        }

        $ch_up = curl_init();
        curl_setopt_array($ch_up, [
            // Use upsert to avoid URL filter type casting issues
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([$update_payload_with_pk]),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: resolution=merge-duplicates, return=representation'
            ]
        ]);
        $update_resp = curl_exec($ch_up);
        $update_code = curl_getinfo($ch_up, CURLINFO_HTTP_CODE);
        curl_close($ch_up);

        if ($update_code >= 200 && $update_code < 300) {
            $updated_rows = json_decode($update_resp, true);
            $blood_collection_id = $updated_rows[0]['blood_collection_id'] ?? ($existing_row['blood_collection_id'] ?? null);

            // Return success; eligibility and blood bank units are handled by DB triggers
            echo json_encode(['success' => true, 'message' => 'Blood collection updated (amount incremented).', 'blood_collection_id' => $blood_collection_id]);
            exit;
        } else {
            throw new Exception('Failed to update existing blood collection: ' . $update_resp . ' | URL: ' . SUPABASE_URL . '/rest/v1/blood_collection' . ' | Payload: ' . json_encode($update_payload_with_pk));
        }
    }

    // (timestamps already prepared above)

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

    // For INSERT: enforce unit_serial uniqueness
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?unit_serial_number=eq.' . urlencode($input['unit_serial_number']) . '&select=blood_collection_id',
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
        'status' => 'pending',
        'phlebotomist' => (!empty($phlebotomist_name) ? $phlebotomist_name : null)
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
            // Eligibility and blood bank unit creation are handled by DB triggers
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