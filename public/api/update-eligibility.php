<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

// Required fields
$required_fields = ['donor_id', 'status', 'disapproval_reason'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Include database connection
    require_once '../../assets/conn/db_conn.php';
    
    // Log the incoming data for debugging
    error_log("Eligibility update request: " . json_encode($input));
    error_log("Eligibility data being prepared: " . json_encode($input));
    
    // Prepare data for Supabase insert - populate ALL fields from source tables
    $eligibilityData = [
        'donor_id' => (int)$input['donor_id'],
        'medical_history_id' => $input['medical_history_id'] ?? null,
        'screening_id' => $input['screening_id'] ?? null,
        'physical_exam_id' => $input['physical_exam_id'] ?? null,
        'blood_collection_id' => null, // Only field allowed to be null
        'blood_type' => $input['blood_type'] ?? null,
        'donation_type' => $input['donation_type'] ?? null,
        'blood_bag_type' => $input['blood_bag_type'] ?? 'Declined - Medical History',
        'blood_bag_brand' => $input['blood_bag_brand'] ?? 'Declined - Medical History',
        'amount_collected' => 0, // Default for declined donors
        'collection_successful' => false, // Default for declined donors
        'donor_reaction' => 'Declined - Medical History',
        'management_done' => 'Donor marked as ineligible due to medical history decline',
        'collection_start_time' => null,
        'collection_end_time' => null,
        'unit_serial_number' => null,
        'disapproval_reason' => $input['disapproval_reason'],
        'start_date' => $input['start_date'] ?? date('c'),
        'end_date' => $input['end_date'] ?? null,
        'status' => $input['status'] ?? 'declined',
        'registration_channel' => $input['registration_channel'] ?? 'PRC Portal',
        'blood_pressure' => $input['blood_pressure'] ?? null,
        'pulse_rate' => $input['pulse_rate'] ?? null,
        'body_temp' => $input['body_temp'] ?? null,
        'gen_appearance' => $input['gen_appearance'] ?? null,
        'skin' => $input['skin'] ?? null,
        'heent' => $input['heent'] ?? null,
        'heart_and_lungs' => $input['heart_and_lungs'] ?? null,
        'body_weight' => $input['body_weight'] ?? null,
        'temporary_deferred' => $input['temporary_deferred'] ?? null,
        'created_at' => $input['created_at'] ?? date('c'),
        'updated_at' => $input['updated_at'] ?? date('c')
    ];
    
    // Log the final eligibility data being inserted
    error_log("Final eligibility data for insertion: " . json_encode($eligibilityData));
    
    // Insert into Supabase using cURL (same pattern as get_donor_info.php)
    $url = SUPABASE_URL . '/rest/v1/eligibility';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($eligibilityData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Prefer: return=minimal'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception('Failed to insert eligibility record');
    }
    
    if ($httpCode >= 400) {
        throw new Exception('Failed to insert eligibility record: HTTP ' . $httpCode . ' - ' . $response);
    }
    
    error_log("Eligibility record inserted successfully");
    
    echo json_encode([
        'success' => true,
        'message' => 'Eligibility record inserted successfully',
        'data' => $input
    ]);
    
} catch (Exception $e) {
    error_log("Error updating eligibility: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
