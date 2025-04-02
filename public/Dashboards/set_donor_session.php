<?php
// Start session if not already started
session_start();

// Include database connection
include_once '../../assets/conn/db_conn.php';

// Set header to return JSON
header('Content-Type: application/json');

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);

// Check if there's a donor_id in the request
if (isset($data['donor_id']) && !empty($data['donor_id'])) {
    // Store the donor_id in the session
    $_SESSION['donor_id'] = $data['donor_id'];
    
    // Check if this is for processing or viewing/editing
    if (isset($data['view_mode']) && $data['view_mode'] === true) {
        $_SESSION['view_mode'] = true;
        error_log("Session updated: donor_id set to {$_SESSION['donor_id']}, view_mode flag set to true");
    } else {
        $_SESSION['admin_processing'] = true; // Flag to indicate admin is processing
        error_log("Session updated: donor_id set to {$_SESSION['donor_id']}, admin_processing flag set to true");
        
        // Call the create_eligibility_record.php to ensure eligibility record is created
        try {
            $donor_id = $data['donor_id'];
            error_log("Calling create_eligibility_record.php for donor_id: $donor_id");
            
            // Make API call to create/update eligibility record
            $ch = curl_init('./create_eligibility_record.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode(['donor_id' => $donor_id, 'processing_flow' => true]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ]
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            
            if ($err) {
                error_log("cURL error creating eligibility record: $err");
            } else {
                $result = json_decode($response, true);
                if (isset($result['success']) && $result['success']) {
                    error_log("Successfully created/updated eligibility record for donor ID: $donor_id");
                    if (isset($result['status'])) {
                        error_log("Donor status is now: " . $result['status']);
                    }
                } else {
                    error_log("Failed to create/update eligibility record: " . ($result['error'] ?? 'Unknown error'));
                }
            }
            
            error_log("Eligibility record creation response: " . substr($response, 0, 200) . "... (HTTP code: $http_code)");
        } catch (Exception $e) {
            error_log("Error creating eligibility record: " . $e->getMessage());
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Donor ID stored in session',
        'donor_id' => $_SESSION['donor_id'],
        'mode' => isset($_SESSION['view_mode']) ? 'view' : 'process'
    ]);
} else {
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => 'No donor ID provided'
    ]);
}
?> 