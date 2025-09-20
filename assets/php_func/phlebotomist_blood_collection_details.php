<?php
session_start();
require_once '../conn/db_conn.php';
require 'user_roles_staff.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$donor_id = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : null;

if (!$donor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
    exit;
}

try {
    // Get donor information from donor_form table
    $donor_url = SUPABASE_URL . '/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,age,sex,prc_donor_number&donor_id=eq.' . $donor_id;
    $ch = curl_init($donor_url);
    $headers = array(
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $donor_response = curl_exec($ch);
    $donor_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($donor_http_code !== 200) {
        throw new Exception('Failed to fetch donor information');
    }

    $donor_data = json_decode($donor_response, true);
    if (empty($donor_data)) {
        throw new Exception('Donor not found');
    }

    $donor = $donor_data[0];

    // First get physical exam ID for this donor
    $physical_exam_url = SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1';
    $ch = curl_init($physical_exam_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $exam_response = curl_exec($ch);
    $exam_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $blood_collection = null;
    if ($exam_http_code === 200) {
        $exam_data = json_decode($exam_response, true);
        if (!empty($exam_data)) {
            $physical_exam_id = $exam_data[0]['physical_exam_id'];
            
            // Now get blood collection data using the physical exam ID
            $collection_url = SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id,physical_exam_id,is_successful,donor_reaction,blood_bag_type,blood_bag_brand,amount_taken,start_time,end_time,unit_serial_number,phlebotomist,created_at,blood_expiration&physical_exam_id=eq.' . $physical_exam_id . '&order=created_at.desc&limit=1';
            $ch = curl_init($collection_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $collection_response = curl_exec($ch);
            $collection_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($collection_http_code === 200) {
                $collection_data = json_decode($collection_response, true);
                if (!empty($collection_data)) {
                    $blood_collection = $collection_data[0];
                }
            }
        }
    }

    // Fallback: if no collection by physical_exam_id, try by latest screening_id
    if ($blood_collection === null) {
        $screening_url = SUPABASE_URL . '/rest/v1/screening_form?select=screening_id&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1';
        $ch = curl_init($screening_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $screening_response = curl_exec($ch);
        $screening_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($screening_http === 200) {
            $screening_data = json_decode($screening_response, true);
            if (!empty($screening_data)) {
                $screening_id = $screening_data[0]['screening_id'];
                $collection_url = SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id,screening_id,is_successful,donor_reaction,blood_bag_type,blood_bag_brand,amount_taken,start_time,end_time,unit_serial_number,phlebotomist,created_at,blood_expiration&screening_id=eq.' . $screening_id . '&order=created_at.desc&limit=1';
                $ch = curl_init($collection_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $collection_response = curl_exec($ch);
                $collection_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($collection_http_code === 200) {
                    $collection_data = json_decode($collection_response, true);
                    if (!empty($collection_data)) {
                        $blood_collection = $collection_data[0];
                    }
                }
            }
        }
    }

    // Calculate age if not present
    if (empty($donor['age']) && !empty($donor['birthdate'])) {
        $birthDate = new DateTime($donor['birthdate']);
        $today = new DateTime();
        $donor['age'] = $birthDate->diff($today)->y;
    }

    // Format donor name
    $full_name = trim(($donor['surname'] ?? '') . ', ' . ($donor['first_name'] ?? '') . ' ' . ($donor['middle_name'] ?? ''));
    
    // Format donor ID (remove PRC- prefix if present)
    $display_donor_id = $donor['prc_donor_number'] ?? $donor_id;
    if (!empty($display_donor_id) && strpos($display_donor_id, 'PRC-') === 0) {
        $display_donor_id = substr($display_donor_id, 4);
    }

    // Get blood type from screening form (using donor_form_id field)
    $blood_type = 'N/A';
    $screening_url = SUPABASE_URL . '/rest/v1/screening_form?select=blood_type&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1';
    $ch = curl_init($screening_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $screening_response = curl_exec($ch);
    $screening_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($screening_http_code === 200) {
        $screening_data = json_decode($screening_response, true);
        if (!empty($screening_data) && !empty($screening_data[0]['blood_type'])) {
            $blood_type = $screening_data[0]['blood_type'];
        }
    }

    // Prepare response data
    $response_data = [
        'donor' => [
            'id' => $donor_id,
            'name' => $full_name,
            'age' => $donor['age'] ?? 'N/A',
            'gender' => $donor['sex'] ?? 'N/A',
            'donor_id' => $display_donor_id,
            'blood_type' => $blood_type
        ],
        'blood_collection' => $blood_collection
    ];

    echo json_encode([
        'success' => true,
        'data' => $response_data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
