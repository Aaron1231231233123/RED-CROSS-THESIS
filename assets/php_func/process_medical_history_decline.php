<?php
/**
 * Process Medical History Decline
 * This file handles medical history decline with full database operations:
 * - Updates medical_history table with decline data
 * - Creates/updates eligibility record
 * - Creates/updates physical examination record
 * Similar to defer donor functionality
 */

session_start();
require_once '../conn/db_conn.php';

// Set content type to JSON
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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // If no JSON, try POST data
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate required fields
    $required_fields = ['donor_id', 'decline_reason', 'restriction_type'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            exit;
        }
    }
    
    $donor_id = (int)$input['donor_id'];
    $decline_reason = trim($input['decline_reason']);
    $restriction_type = $input['restriction_type']; // 'temporary' or 'permanent'
    $duration = isset($input['duration']) ? $input['duration'] : null;
    $end_date = isset($input['end_date']) ? $input['end_date'] : null;
    $screening_id = isset($input['screening_id']) && !empty($input['screening_id']) ? $input['screening_id'] : null;
    
    error_log("Processing medical history decline for donor_id: $donor_id");
    error_log("Restriction type: $restriction_type, Duration: $duration, End date: $end_date");
    
    // Calculate end_date from duration if not provided
    if ($restriction_type === 'temporary' && $duration && !$end_date) {
        $end_date = date('c', strtotime("+$duration days"));
    }
    
    // Calculate temporary_deferred text
    $temporaryDeferredText = null;
    if ($restriction_type === 'temporary' && $duration) {
        $days = intval($duration);
        if ($days > 0) {
            $months = floor($days / 30);
            $remainingDays = $days % 30;
            
            if ($months > 0 && $remainingDays > 0) {
                $temporaryDeferredText = "$months month" . ($months > 1 ? 's' : '') . " $remainingDays day" . ($remainingDays > 1 ? 's' : '');
            } else if ($months > 0) {
                $temporaryDeferredText = "$months month" . ($months > 1 ? 's' : '');
            } else {
                $temporaryDeferredText = "$days day" . ($days > 1 ? 's' : '');
            }
        } else {
            $temporaryDeferredText = 'Immediate';
        }
    } else if ($restriction_type === 'permanent') {
        $temporaryDeferredText = 'Permanent/Indefinite';
    }
    
    // Step 1: Update medical_history table
    $medical_history_update = [
        'medical_approval' => 'Not Approved',
        'needs_review' => false,
        'decline_reason' => $decline_reason,
        'decline_date' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Add restriction_type, duration, end_date if they exist in medical_history table
    // (These fields may not exist, so we'll try to update them if they do)
    if (isset($input['restriction_type'])) {
        $medical_history_update['restriction_type'] = $restriction_type;
    }
    if ($duration) {
        $medical_history_update['deferral_duration'] = $duration;
    }
    if ($end_date) {
        $medical_history_update['deferral_end_date'] = $end_date;
    }
    
    $ch_medical = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id);
    curl_setopt($ch_medical, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_medical, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch_medical, CURLOPT_POSTFIELDS, json_encode($medical_history_update));
    curl_setopt($ch_medical, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    
    $medical_response = curl_exec($ch_medical);
    $medical_http_code = curl_getinfo($ch_medical, CURLINFO_HTTP_CODE);
    curl_close($ch_medical);
    
    if ($medical_http_code < 200 || $medical_http_code >= 300) {
        error_log("Failed to update medical_history: HTTP $medical_http_code - $medical_response");
        throw new Exception("Failed to update medical history record");
    }
    
    error_log("Medical history updated successfully");
    
    // Step 2: Fetch source data for eligibility record
    $screening_data = null;
    $physical_exam_data = null;
    $medical_history_id = null;
    
    // Fetch screening data
    if ($screening_id) {
        $ch_screening = curl_init(SUPABASE_URL . '/rest/v1/screening_form?screening_id=eq.' . $screening_id);
        curl_setopt($ch_screening, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_screening, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        $screening_response = curl_exec($ch_screening);
        $screening_http = curl_getinfo($ch_screening, CURLINFO_HTTP_CODE);
        curl_close($ch_screening);
        
        if ($screening_http === 200) {
            $screening_result = json_decode($screening_response, true);
            if (!empty($screening_result)) {
                $screening_data = $screening_result[0];
            }
        }
    }
    
    // Fetch physical examination data
    $ch_physical = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch_physical, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_physical, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    $physical_response = curl_exec($ch_physical);
    $physical_http = curl_getinfo($ch_physical, CURLINFO_HTTP_CODE);
    curl_close($ch_physical);
    
    if ($physical_http === 200) {
        $physical_result = json_decode($physical_response, true);
        if (!empty($physical_result)) {
            $physical_exam_data = $physical_result[0];
        }
    }
    
    // Fetch medical_history_id
    $ch_mh = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id . '&select=medical_history_id&order=created_at.desc&limit=1');
    curl_setopt($ch_mh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_mh, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    $mh_response = curl_exec($ch_mh);
    $mh_http = curl_getinfo($ch_mh, CURLINFO_HTTP_CODE);
    curl_close($ch_mh);
    
    if ($mh_http === 200) {
        $mh_result = json_decode($mh_response, true);
        if (!empty($mh_result)) {
            $medical_history_id = $mh_result[0]['medical_history_id'] ?? null;
        }
    }
    
    // Step 3: Create/Update eligibility record
    $eligibility_data = [
        'donor_id' => $donor_id,
        'medical_history_id' => $medical_history_id,
        'screening_id' => $screening_id,
        'physical_exam_id' => $physical_exam_data['physical_exam_id'] ?? null,
        'blood_collection_id' => null,
        'blood_type' => $screening_data['blood_type'] ?? null,
        'donation_type' => $screening_data['donation_type'] ?? null,
        'blood_bag_type' => 'Declined - Medical History',
        'blood_bag_brand' => 'Declined - Medical History',
        'amount_collected' => 0,
        'collection_successful' => false,
        'donor_reaction' => 'Declined - Medical History',
        'management_done' => 'Donor marked as ineligible due to medical history decline',
        'collection_start_time' => null,
        'collection_end_time' => null,
        'unit_serial_number' => null,
        'disapproval_reason' => $decline_reason,
        'start_date' => date('c'),
        'end_date' => $end_date,
        'status' => $restriction_type === 'temporary' ? 'temporary deferred' : 'permanently deferred',
        'registration_channel' => 'PRC Portal',
        'blood_pressure' => $physical_exam_data['blood_pressure'] ?? null,
        'pulse_rate' => $physical_exam_data['pulse_rate'] ?? null,
        'body_temp' => $physical_exam_data['body_temp'] ?? null,
        'gen_appearance' => $physical_exam_data['gen_appearance'] ?? null,
        'skin' => $physical_exam_data['skin'] ?? null,
        'heent' => $physical_exam_data['heent'] ?? null,
        'heart_and_lungs' => $physical_exam_data['heart_and_lungs'] ?? null,
        'body_weight' => $screening_data['body_weight'] ?? $physical_exam_data['body_weight'] ?? null,
        'temporary_deferred' => $temporaryDeferredText,
        'created_at' => date('c'),
        'updated_at' => date('c')
    ];
    
    $ch_eligibility = curl_init(SUPABASE_URL . '/rest/v1/eligibility');
    curl_setopt($ch_eligibility, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_eligibility, CURLOPT_POST, true);
    curl_setopt($ch_eligibility, CURLOPT_POSTFIELDS, json_encode($eligibility_data));
    curl_setopt($ch_eligibility, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    
    $eligibility_response = curl_exec($ch_eligibility);
    $eligibility_http = curl_getinfo($ch_eligibility, CURLINFO_HTTP_CODE);
    curl_close($ch_eligibility);
    
    if ($eligibility_http !== 201 && $eligibility_http !== 200) {
        error_log("Failed to create eligibility record: HTTP $eligibility_http - $eligibility_response");
        // Don't throw exception, just log - eligibility might already exist
    } else {
        error_log("Eligibility record created/updated successfully");
    }
    
    // Step 4: Update or create physical examination record
    $remarks = $restriction_type === 'temporary' ? 'Temporarily Deferred' : 'Permanently Deferred';
    
    $physical_exam_id = $physical_exam_data['physical_exam_id'] ?? null;
    
    if ($physical_exam_id) {
        // Update existing physical examination
        $physical_update = [
            'remarks' => $remarks,
            'needs_review' => false,
            'disapproval_reason' => $decline_reason,
            'updated_at' => date('c')
        ];
        
        $ch_physical_update = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . $physical_exam_id);
        curl_setopt($ch_physical_update, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_physical_update, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch_physical_update, CURLOPT_POSTFIELDS, json_encode($physical_update));
        curl_setopt($ch_physical_update, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        
        $physical_update_response = curl_exec($ch_physical_update);
        $physical_update_http = curl_getinfo($ch_physical_update, CURLINFO_HTTP_CODE);
        curl_close($ch_physical_update);
        
        if ($physical_update_http >= 200 && $physical_update_http < 300) {
            error_log("Physical examination updated successfully");
        } else {
            error_log("Failed to update physical examination: HTTP $physical_update_http");
        }
    } else {
        // Create new physical examination record
        $physical_create = [
            'donor_id' => $donor_id,
            'blood_pressure' => null,
            'pulse_rate' => null,
            'body_temp' => null,
            'gen_appearance' => null,
            'skin' => null,
            'heent' => null,
            'heart_and_lungs' => null,
            'remarks' => $remarks,
            'disapproval_reason' => $decline_reason,
            'needs_review' => false,
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];
        
        $ch_physical_create = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
        curl_setopt($ch_physical_create, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_physical_create, CURLOPT_POST, true);
        curl_setopt($ch_physical_create, CURLOPT_POSTFIELDS, json_encode($physical_create));
        curl_setopt($ch_physical_create, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        
        $physical_create_response = curl_exec($ch_physical_create);
        $physical_create_http = curl_getinfo($ch_physical_create, CURLINFO_HTTP_CODE);
        curl_close($ch_physical_create);
        
        if ($physical_create_http === 201) {
            error_log("Physical examination record created successfully");
        } else {
            error_log("Failed to create physical examination: HTTP $physical_create_http");
        }
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Medical history declined successfully',
        'data' => [
            'donor_id' => $donor_id,
            'restriction_type' => $restriction_type,
            'duration' => $duration,
            'end_date' => $end_date
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error processing medical history decline: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>

