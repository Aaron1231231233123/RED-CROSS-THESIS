<?php
// Start output buffering to catch any unexpected output
ob_start();

// Suppress all error output to ensure clean response
error_reporting(0);
ini_set('display_errors', 0);

// Include database connection
require_once '../conn/db_conn.php';

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
} finally {
    // Clean up output buffer
    ob_end_clean();
}

/**
 * Approve medical history for a donor
 */
function approveMedicalHistory($donor_id) {
    // Update medical_history table to set approval status
    $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    
    $updateData = json_encode([
        'medical_approval' => 'Approved',
        'approval_date' => date('Y-m-d H:i:s'),
        'approved_by' => 'admin' // You might want to get this from session
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 && $http_code !== 204) {
        throw new Exception('Failed to approve medical history');
    }
    
    // Update eligibility table to reflect the approval
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
    
    if ($http_code !== 200 && $http_code !== 204) {
        throw new Exception('Failed to update eligibility status');
    }
    
    return [
        'message' => 'Medical history approved successfully',
        'data' => ['donor_id' => $donor_id, 'status' => 'Approved']
    ];
}

/**
 * Decline medical history for a donor
 */
function declineMedicalHistory($donor_id, $decline_reason) {
    // Update medical_history table to set decline status
    $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    
    $updateData = json_encode([
        'medical_approval' => 'Not Approved',
        'decline_reason' => $decline_reason,
        'decline_date' => date('Y-m-d H:i:s'),
        'declined_by' => 'admin' // You might want to get this from session
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 && $http_code !== 204) {
        throw new Exception('Failed to decline medical history');
    }
    
    // Update eligibility table to reflect the decline
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
    
    if ($http_code !== 200 && $http_code !== 204) {
        throw new Exception('Failed to update eligibility status');
    }
    
    return [
        'message' => 'Medical history declined successfully',
        'data' => ['donor_id' => $donor_id, 'status' => 'Not Approved', 'reason' => $decline_reason]
    ];
}
?>
