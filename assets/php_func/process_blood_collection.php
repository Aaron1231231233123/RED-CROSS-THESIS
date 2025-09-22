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
        case 'complete_blood_collection':
            $result = completeBloodCollection($donor_id);
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
 * Complete blood collection for a donor
 */
function completeBloodCollection($donor_id) {
    // Update eligibility table to mark blood collection as completed
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
        'collection_status' => 'Completed',
        'status' => 'approved',
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 && $http_code !== 204) {
        throw new Exception('Failed to update blood collection status');
    }
    
    // Create blood collection record
    $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection');
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    
    $collectionData = json_encode([
        'donor_id' => $donor_id,
        'collection_date' => date('Y-m-d H:i:s'),
        'status' => 'Completed',
        'collected_by' => 'admin', // You might want to get this from session
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $collectionData);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 && $http_code !== 201) {
        // Don't throw error here as the main status update succeeded
        error_log('Warning: Failed to create blood collection record for donor ' . $donor_id);
    }
    
    return [
        'message' => 'Blood collection completed successfully',
        'data' => ['donor_id' => $donor_id, 'status' => 'Completed']
    ];
}
?>