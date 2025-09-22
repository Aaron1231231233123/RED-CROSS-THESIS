<?php
// Blood Bank Unit Details API
// Fetches comprehensive details for a specific blood bank unit

// Include database connection
include_once '../conn/db_conn.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Get unit ID from query parameters
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : null;

if (!$unit_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Unit ID is required']);
    exit;
}

try {
    // Fetch blood bank unit details with related data
    $unit_url = SUPABASE_URL . '/rest/v1/blood_bank_units?select=unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,blood_collection_id,hospital_request_id,created_at,updated_at&unit_id=eq.' . urlencode($unit_id) . '&limit=1';
    
    $ch = curl_init($unit_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    ]);
    
    $unit_response = curl_exec($ch);
    $unit_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($unit_http_code !== 200) {
        throw new Exception('Failed to fetch blood bank unit details');
    }
    
    $unit_data = json_decode($unit_response, true);
    if (empty($unit_data)) {
        throw new Exception('Blood bank unit not found');
    }
    
    $unit = $unit_data[0];
    
    // Fetch donor details
    $donor_url = SUPABASE_URL . '/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,sex,civil_status&donor_id=eq.' . urlencode($unit['donor_id']) . '&limit=1';
    
    $ch = curl_init($donor_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    ]);
    
    $donor_response = curl_exec($ch);
    $donor_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $donor = null;
    if ($donor_http_code === 200) {
        $donor_data = json_decode($donor_response, true);
        if (!empty($donor_data)) {
            $donor = $donor_data[0];
        }
    }
    
    // Fetch blood collection details for phlebotomist info
    $phlebotomist_name = 'Not Available';
    $collected_from = 'Blood Bank';
    
    if (!empty($unit['blood_collection_id'])) {
        $collection_url = SUPABASE_URL . '/rest/v1/blood_collection?select=phlebotomist,created_at&blood_collection_id=eq.' . urlencode($unit['blood_collection_id']) . '&limit=1';
        
        $ch = curl_init($collection_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]);
        
        $collection_response = curl_exec($ch);
        $collection_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($collection_http_code === 200) {
            $collection_data = json_decode($collection_response, true);
            if (!empty($collection_data) && !empty($collection_data[0]['phlebotomist'])) {
                $phlebotomist_name = $collection_data[0]['phlebotomist'];
            }
        }
    }
    
    // Fetch hospital information if hospital_request_id is available
    $recipient_hospital = 'Not Assigned';
    if (!empty($unit['hospital_request_id'])) {
        $hospital_url = SUPABASE_URL . '/rest/v1/blood_requests?select=hospital_name,hospital_admitted&request_id=eq.' . urlencode($unit['hospital_request_id']) . '&limit=1';
        
        $ch = curl_init($hospital_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]);
        
        $hospital_response = curl_exec($ch);
        $hospital_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($hospital_http_code === 200) {
            $hospital_data = json_decode($hospital_response, true);
            if (!empty($hospital_data)) {
                $hospital_info = $hospital_data[0];
                $recipient_hospital = $hospital_info['hospital_name'] ?: $hospital_info['hospital_admitted'] ?: 'Hospital Request #' . $unit['hospital_request_id'];
            }
        }
    }
    
    // Determine blood status based on current status and expiration
    $today = new DateTime();
    $expiration_date = new DateTime($unit['expires_at']);
    $collection_date = new DateTime($unit['collected_at']);
    
    $blood_status = 'Available';
    if ($unit['status'] === 'handed_over') {
        $blood_status = 'Used';
    } elseif ($today > $expiration_date) {
        $blood_status = 'Expired';
    } elseif ($unit['status'] === 'reserved') {
        $blood_status = 'Reserved';
    }
    
    // Format dates
    $collection_date_formatted = $collection_date->format('Y-m-d');
    $expiration_date_formatted = $expiration_date->format('Y-m-d');
    
    // Prepare response data
    $response_data = [
        'unit_id' => $unit['unit_id'],
        'unit_serial_number' => $unit['unit_serial_number'],
        'blood_type' => $unit['blood_type'],
        'collection_date' => $collection_date_formatted,
        'expiration_date' => $expiration_date_formatted,
        'collected_from' => $collected_from,
        'phlebotomist_name' => $phlebotomist_name,
        'recipient_hospital' => $recipient_hospital,
        'blood_status' => $blood_status,
        'bag_type' => $unit['bag_type'] ?: 'Standard',
        'bag_brand' => $unit['bag_brand'] ?: 'N/A',
        'donor' => $donor ? [
            'donor_id' => $donor['donor_id'],
            'surname' => $donor['surname'] ?: '',
            'first_name' => $donor['first_name'] ?: '',
            'middle_name' => $donor['middle_name'] ?: '',
            'birthdate' => $donor['birthdate'] ?: '',
            'sex' => $donor['sex'] ?: '',
            'civil_status' => $donor['civil_status'] ?: ''
        ] : null
    ];
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
