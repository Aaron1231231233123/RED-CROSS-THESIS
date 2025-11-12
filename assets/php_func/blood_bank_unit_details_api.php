<?php
// Blood Bank Unit Details API
// Fetches comprehensive details for a specific blood bank unit

// Enable error logging but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    // Include from_hospital field for recipient hospital display (if it exists)
    // Try with from_hospital first, fallback without it if field doesn't exist
    $unit_url = SUPABASE_URL . '/rest/v1/blood_bank_units?select=unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,blood_collection_id,hospital_request_id,from_hospital,created_at,updated_at&unit_id=eq.' . urlencode($unit_id) . '&limit=1';
    
    $ch = curl_init($unit_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    ]);
    
    $unit_response = curl_exec($ch);
    $unit_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // If query fails, try without from_hospital field (in case it doesn't exist)
    if ($unit_http_code !== 200) {
        error_log("Blood Bank Unit Details API - First query failed with HTTP $unit_http_code, trying without from_hospital field");
        if ($curl_error) {
            error_log("Blood Bank Unit Details API - cURL Error: " . $curl_error);
        }
        
        // Retry without from_hospital field
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
            throw new Exception('Failed to fetch blood bank unit details. HTTP Code: ' . $unit_http_code);
        }
    }
    
    $unit_data = json_decode($unit_response, true);
    if (empty($unit_data)) {
        throw new Exception('Blood bank unit not found');
    }
    
    $unit = $unit_data[0];
    
    // Validate required fields exist
    if (!isset($unit['unit_id'])) {
        throw new Exception('Invalid unit data: missing unit_id');
    }
    
    // Fetch donor details (only if donor_id exists)
    $donor = null;
    if (!empty($unit['donor_id'])) {
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
        
        if ($donor_http_code === 200) {
            $donor_data = json_decode($donor_response, true);
            if (!empty($donor_data)) {
                $donor = $donor_data[0];
            }
        }
    }
    
    // Fetch phlebotomist name from blood_collection table using blood_collection_id
    // Connect blood_collection_id from blood_bank_units to blood_collection table
    // Get the phlebotomist column value from blood_collection table
    // This only happens when modal is opened (lazy loading)
    $phlebotomist_name = 'Not Available';
    
    if (!empty($unit['blood_collection_id'])) {
        // Query blood_collection table using blood_collection_id to get phlebotomist name
        $collection_url = SUPABASE_URL . '/rest/v1/blood_collection?select=phlebotomist&blood_collection_id=eq.' . urlencode($unit['blood_collection_id']) . '&limit=1';
        
        $ch = curl_init($collection_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]);
        
        $collection_response = curl_exec($ch);
        $collection_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("Blood Bank Unit Details API - cURL Error fetching phlebotomist: " . $curl_error);
        }
        
        if ($collection_http_code === 200) {
            $collection_data = json_decode($collection_response, true);
            if (!empty($collection_data) && isset($collection_data[0]['phlebotomist']) && !empty($collection_data[0]['phlebotomist'])) {
                $phlebotomist_name = trim($collection_data[0]['phlebotomist']);
                error_log("Blood Bank Unit Details API - Found phlebotomist: " . $phlebotomist_name . " for blood_collection_id: " . $unit['blood_collection_id']);
            } else {
                error_log("Blood Bank Unit Details API - No phlebotomist found for blood_collection_id: " . $unit['blood_collection_id']);
            }
        } else {
            error_log("Blood Bank Unit Details API - HTTP Error fetching phlebotomist: " . $collection_http_code . " for blood_collection_id: " . $unit['blood_collection_id']);
        }
    } else {
        error_log("Blood Bank Unit Details API - No blood_collection_id found for unit_id: " . $unit_id);
    }
    
    // Get recipient hospital from from_hospital field in blood_bank_units
    // Check if from_hospital field exists and has a value
    $recipient_hospital = 'Not Assigned';
    if (isset($unit['from_hospital']) && !empty($unit['from_hospital'])) {
        $recipient_hospital = $unit['from_hospital'];
    } elseif (isset($unit['hospital_request_id']) && !empty($unit['hospital_request_id'])) {
        // Fallback: If from_hospital is not set, try to get from hospital_request
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
    
    // Use the actual status field from the database (not calculated)
    // Format status for display (capitalize first letter, handle special cases)
    $status = $unit['status'] ?? 'valid';
    $blood_status = ucfirst(str_replace('_', ' ', $status));
    
    // Handle special status formatting
    if (strtolower($status) === 'handed_over' || strtolower($status) === 'handed over') {
        $blood_status = 'Handed Over';
    } elseif (strtolower($status) === 'disposed') {
        $blood_status = 'Disposed';
    }
    
    // Format dates - handle potential null or invalid dates
    $collection_date_formatted = 'N/A';
    $expiration_date_formatted = 'N/A';
    
    if (!empty($unit['collected_at'])) {
        try {
            $collection_date = new DateTime($unit['collected_at']);
            $collection_date_formatted = $collection_date->format('Y-m-d');
        } catch (Exception $e) {
            error_log("Blood Bank Unit Details API - Error parsing collection date: " . $e->getMessage());
            $collection_date_formatted = $unit['collected_at']; // Use raw value as fallback
        }
    }
    
    if (!empty($unit['expires_at'])) {
        try {
            $expiration_date = new DateTime($unit['expires_at']);
            $expiration_date_formatted = $expiration_date->format('Y-m-d');
        } catch (Exception $e) {
            error_log("Blood Bank Unit Details API - Error parsing expiration date: " . $e->getMessage());
            $expiration_date_formatted = $unit['expires_at']; // Use raw value as fallback
        }
    }
    
    // Prepare response data - use null coalescing to handle missing fields
    $response_data = [
        'unit_id' => $unit['unit_id'] ?? null,
        'unit_serial_number' => $unit['unit_serial_number'] ?? 'N/A',
        'blood_type' => $unit['blood_type'] ?? 'N/A',
        'collection_date' => $collection_date_formatted,
        'expiration_date' => $expiration_date_formatted,
        'collected_from' => 'Blood Bank', // Default value
        'phlebotomist_name' => $phlebotomist_name,
        'recipient_hospital' => $recipient_hospital,
        'blood_status' => $blood_status, // Now uses actual status field
        'bag_type' => $unit['bag_type'] ?? 'Standard',
        'bag_brand' => $unit['bag_brand'] ?? 'N/A',
        'donor' => $donor ? [
            'donor_id' => $donor['donor_id'] ?? null,
            'surname' => $donor['surname'] ?? '',
            'first_name' => $donor['first_name'] ?? '',
            'middle_name' => $donor['middle_name'] ?? '',
            'birthdate' => $donor['birthdate'] ?? '',
            'sex' => $donor['sex'] ?? '',
            'civil_status' => $donor['civil_status'] ?? ''
        ] : null
    ];
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    error_log("Blood Bank Unit Details API - Exception: " . $e->getMessage());
    error_log("Blood Bank Unit Details API - Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'unit_id' => $unit_id ?? 'unknown'
    ]);
} catch (Error $e) {
    error_log("Blood Bank Unit Details API - Fatal Error: " . $e->getMessage());
    error_log("Blood Bank Unit Details API - Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'unit_id' => $unit_id ?? 'unknown'
    ]);
}
?>
