<?php
// Include database connection
require_once '../../conn/db_conn.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get the action from POST data
    $action = $_POST['action'] ?? '';
    $donor_id = $_POST['donor_id'] ?? '';
    
    if (empty($action) || empty($donor_id)) {
        throw new Exception('Missing required parameters');
    }
    
    // Validate donor ID
    if (!is_numeric($donor_id)) {
        throw new Exception('Invalid donor ID');
    }
    
    switch ($action) {
        case 'approve_medical_history':
            $result = approveMedicalHistory($donor_id);
            break;
            
        case 'decline_medical_history':
            $decline_reason = $_POST['decline_reason'] ?? '';
            if (empty($decline_reason)) {
                throw new Exception('Decline reason is required');
            }
            $result = declineMedicalHistory($donor_id, $decline_reason);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => $result['data'] ?? null
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Approve medical history for a donor
 */
function approveMedicalHistory($donor_id) {
    // Check if medical history record exists
    $checkCurl = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id);
    curl_setopt($checkCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($checkCurl, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    
    $checkResponse = curl_exec($checkCurl);
    $checkHttpCode = curl_getinfo($checkCurl, CURLINFO_HTTP_CODE);
    curl_close($checkCurl);
    
    if ($checkHttpCode !== 200) {
        throw new Exception('Failed to check existing medical history');
    }
    
    $existingRecords = json_decode($checkResponse, true) ?: [];
    $medical_history_id = null;
    
    if (!empty($existingRecords)) {
        $medical_history_id = $existingRecords[0]['medical_history_id'];
    }
    
    // Prepare update data
    $updateData = [
        'medical_approval' => 'Approved',
        'needs_review' => false,
        'updated_at' => gmdate('c')
    ];
    
    if ($medical_history_id) {
        // Update existing record using medical_history_id
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?medical_history_id=eq.' . $medical_history_id);
    } else {
        // Create new record if none exists
        $updateData['donor_id'] = $donor_id;
        $updateData['created_at'] = gmdate('c');
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history');
        curl_setopt($ch, CURLOPT_POST, true);
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!$medical_history_id) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 && $http_code !== 204 && $http_code !== 201) {
        throw new Exception('Failed to approve medical history');
    }
    
    // Attempt to update eligibility table (non-blocking - eligibility status changes only when leaving Pending status)
    $ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donor_id);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    
    $updateData = json_encode([
        'review_status' => 'Approved',
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Eligibility update is optional - don't throw error if it fails
    // Eligibility status will change only when it leaves Pending status (unless deferred or declined)
    
    return [
        'message' => 'Medical history approved successfully',
        'data' => ['donor_id' => $donor_id, 'status' => 'Approved']
    ];
}

/**
 * Decline medical history for a donor
 */
function declineMedicalHistory($donor_id, $decline_reason) {
    // Check if medical history record exists
    $checkCurl = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id);
    curl_setopt($checkCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($checkCurl, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    
    $checkResponse = curl_exec($checkCurl);
    $checkHttpCode = curl_getinfo($checkCurl, CURLINFO_HTTP_CODE);
    curl_close($checkCurl);
    
    if ($checkHttpCode !== 200) {
        throw new Exception('Failed to check existing medical history');
    }
    
    $existingRecords = json_decode($checkResponse, true) ?: [];
    $medical_history_id = null;
    
    if (!empty($existingRecords)) {
        $medical_history_id = $existingRecords[0]['medical_history_id'];
    }
    
    // Prepare update data
    $updateData = [
        'medical_approval' => 'Not Approved',
        'needs_review' => false,
        'disapproval_reason' => $decline_reason,
        'updated_at' => gmdate('c')
    ];
    
    if ($medical_history_id) {
        // Update existing record using medical_history_id
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?medical_history_id=eq.' . $medical_history_id);
    } else {
        // Create new record if none exists
        $updateData['donor_id'] = $donor_id;
        $updateData['created_at'] = gmdate('c');
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history');
        curl_setopt($ch, CURLOPT_POST, true);
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!$medical_history_id) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 && $http_code !== 204 && $http_code !== 201) {
        throw new Exception('Failed to decline medical history');
    }
    
    // Attempt to update eligibility table (non-blocking - eligibility status changes only when leaving Pending status)
    $ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donor_id);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    
    $updateData = json_encode([
        'review_status' => 'Declined',
        'status' => 'ineligible',
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Eligibility update is optional - don't throw error if it fails
    // Eligibility status will change only when it leaves Pending status (unless deferred or declined)
    
    return [
        'message' => 'Medical history declined successfully',
        'data' => ['donor_id' => $donor_id, 'status' => 'Not Approved', 'reason' => $decline_reason]
    ];
}
?>

