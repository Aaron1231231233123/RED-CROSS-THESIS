<?php
session_start();
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

// Log raw input for debugging
error_log('Raw input: ' . $raw_input);

if (!$input) {
    $json_error = json_last_error_msg();
    error_log('JSON decode error: ' . $json_error);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid JSON data', 
        'error_details' => $json_error,
        'raw_input' => substr($raw_input, 0, 100) . '...'
    ]);
    exit;
}

$physical_exam_id = $input['physical_exam_id'] ?? null;

if (!$physical_exam_id) {
    echo json_encode(['success' => false, 'message' => 'Physical exam ID is required']);
    exit;
}

try {
    // Log the incoming data for debugging
    error_log('Processing blood collection with data: ' . print_r($input, true));

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

    // Convert times to proper timestamp format
    $today = date('Y-m-d');
    $start_timestamp = date('Y-m-d\TH:i:s.000\Z', strtotime("$today " . $input['start_time']));
    $end_timestamp   = date('Y-m-d\TH:i:s.000\Z', strtotime("$today " . $input['end_time']));

    // Check if blood collection already exists
    $check_url = SUPABASE_URL . '/rest/v1/blood_collection?physical_exam_id=eq.' . urlencode($physical_exam_id);
    $ch = curl_init($check_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    $check_response = curl_exec($ch);
    $check_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log('Check response code: ' . $check_http_code . ', Response: ' . $check_response);

    $existing = json_decode($check_response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error for check response: ' . json_last_error_msg());
    }

    // Prepare data for update/insert
    $data = [
        'physical_exam_id' => $physical_exam_id,
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
        'needs_review' => false,
        'phlebotomist' => $phlebotomist_name,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Remove null values
    foreach ($data as $k => $v) {
        if ($v === null) unset($data[$k]);
    }

    // If updating, include the ID
    if (!empty($existing)) {
        $data['blood_collection_id'] = $existing[0]['blood_collection_id'];
    }

    // Send to Supabase
    $url = SUPABASE_URL . '/rest/v1/blood_collection';
    $payload = !empty($existing) ? [$data] : $data;
    $prefer_header = !empty($existing) ? 'resolution=merge-duplicates, return=representation' : 'return=representation';
    
    error_log('Sending request to: ' . $url);
    error_log('Payload: ' . json_encode($payload));
    error_log('Prefer header: ' . $prefer_header);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: ' . $prefer_header
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $result = json_decode($response, true);
        $blood_collection_id = $result[0]['blood_collection_id'] ?? null;

        echo json_encode([
            'success' => true,
            'message' => 'Blood collection ' . (!empty($existing) ? 'updated' : 'created') . ' successfully',
            'blood_collection_id' => $blood_collection_id
        ]);
    } else {
        throw new Exception('Failed to ' . (!empty($existing) ? 'update' : 'create') . ' blood collection: ' . $response);
    }

} catch (Exception $e) {
    $unexpected_output = ob_get_clean();
    if ($unexpected_output) {
        error_log('Unexpected output before error: ' . $unexpected_output);
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'unexpected_output' => $unexpected_output ?: null,
            'error_trace' => $e->getTraceAsString()
        ]
    ]);
} finally {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
}
?>