<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Set JSON response headers
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $donor_id = isset($_POST['donor_id']) ? $_POST['donor_id'] : null;
        $action = isset($_POST['action']) ? $_POST['action'] : null;
        
        if (!$donor_id) {
            throw new Exception('Missing donor ID');
        }
        
        if ($action === 'complete') {
            // Store that we've completed the declaration form
            $_SESSION['declaration_completed'] = true;
            
            // Log successful registration
            error_log("Donor registration completed successfully for donor ID: " . $donor_id);
            
            // Set a flag for registered donor in session
            $_SESSION['donor_registered'] = true;
            $_SESSION['donor_registered_id'] = $donor_id;
            
            // Fetch donor name for logging
            $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . $donor_id . '&select=first_name,surname');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $donorData = json_decode($response, true);
                if (!empty($donorData)) {
                    $donorName = $donorData[0]['first_name'] . ' ' . $donorData[0]['surname'];
                    $_SESSION['donor_registered_name'] = $donorName;
                    error_log("Declaration form - Donor registration completed for: " . $donorName . " (ID: " . $donor_id . ")");
                }
            }
            
            // Clear any previous registration data from session to avoid conflicts
            unset($_SESSION['donor_form_data']);
            unset($_SESSION['donor_form_timestamp']);
            unset($_SESSION['donor_id']);
            unset($_SESSION['medical_history_id']);
            unset($_SESSION['screening_id']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Declaration completed successfully',
                'redirect' => false
            ]);
        } else {
            throw new Exception('Invalid action specified');
        }
        
    } catch (Exception $e) {
        error_log("Error in declaration form processing: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?> 