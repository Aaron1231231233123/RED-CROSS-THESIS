<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php");
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    error_log("Invalid role_id: " . $_SESSION['role_id']);
    header("Location: ../../public/unauthorized.php");
    exit();
}

// For staff role (role_id 3), check for required session variables
if ($_SESSION['role_id'] === 3) {
    if (!isset($_SESSION['donor_id'])) {
        error_log("Missing donor_id in session for staff");
        header('Location: ../../public/Dashboards/dashboard-Inventory-System.php');
        exit();
    }
    if (!isset($_SESSION['screening_id'])) {
        error_log("Missing screening_id in session for staff");
        header('Location: ../views/forms/screening-form.php');
        exit();
    }
} else {
    // For admin role (role_id 1), set donor_id to 46 if not set
    if (!isset($_SESSION['donor_id'])) {
        $_SESSION['donor_id'] = 46;
        error_log("Set donor_id to 46 for admin role");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug log
        error_log("POST data received in handler: " . print_r($_POST, true));
        error_log("Session data in handler: " . print_r($_SESSION, true));

        // Validate required fields
        $required_fields = [
            'blood_pressure',
            'pulse_rate',
            'body_temp',
            'gen_appearance',
            'skin',
            'heent',
            'heart_and_lungs',
            'remarks',
            'blood_bag_type'
        ];

        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                error_log("Missing required field: " . $field);
                throw new Exception("Missing required field: " . $field);
            }
        }

        // Prepare data for insertion
        $data = [
            'donor_id' => intval($_SESSION['donor_id']), // int4
            'blood_pressure' => strval($_POST['blood_pressure']), // varchar
            'pulse_rate' => intval($_POST['pulse_rate']), // int4
            'body_temp' => number_format(floatval($_POST['body_temp']), 1), // numeric with 1 decimal place
            'gen_appearance' => strval(trim($_POST['gen_appearance'])), // text
            'skin' => strval(trim($_POST['skin'])), // text
            'heent' => strval(trim($_POST['heent'])), // text
            'heart_and_lungs' => strval(trim($_POST['heart_and_lungs'])), // text
            'remarks' => strval(trim($_POST['remarks'])), // varchar
            'blood_bag_type' => strval(trim($_POST['blood_bag_type'])) // varchar
        ];

        // Only add disapproval_reason if remarks is not "Accepted"
        if (isset($_POST['reason']) && !empty($_POST['reason'])) {
            $data['disapproval_reason'] = strval(trim($_POST['reason'])); // text
        }

        // Debug log the data being sent
        error_log("Data being sent to Supabase: " . print_r($data, true));

        // Initialize cURL session for Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
        
        $headers = array(
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        );

        $json_data = json_encode($data);
        if ($json_data === false) {
            error_log("JSON encoding error: " . json_last_error_msg());
            throw new Exception("Error preparing data for submission");
        }

        // Debug log before sending
        error_log("Final JSON being sent: " . $json_data);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        // Debug log
        error_log("Supabase response code in handler: " . $http_code);
        error_log("Supabase response in handler: " . $response);
        error_log("CURL error if any: " . $curl_error);
        error_log("Request URL: " . SUPABASE_URL . '/rest/v1/physical_examination');
        error_log("Request Headers: " . print_r($headers, true));
        error_log("Request Data: " . $json_data);

        curl_close($ch);

        if ($http_code === 201) {
            // Parse the response to get the physical examination ID
            $response_data = json_decode($response, true);
            
            if (is_array($response_data) && isset($response_data[0]['physical_exam_id'])) {
                $_SESSION['physical_examination_id'] = $response_data[0]['physical_exam_id'];
                error_log("Stored physical_examination_id in session: " . $_SESSION['physical_examination_id']);
                
                // Different redirections based on role
                if ($_SESSION['role_id'] === 1) {
                    // Admin (role_id 1) - Direct to blood collection
                    error_log("Admin role: Redirecting to blood collection form");
                    header('Location: ../views/forms/blood-collection-form.php');
                } else {
                    // Staff (role_id 3) - Back to dashboard
                    error_log("Staff role: Redirecting to dashboard");
                    header('Location: ../../public/Dashboards/dashboard-staff-physical-submission.php');
                }
                exit();
            } else {
                error_log("Invalid response format from database: " . print_r($response_data, true));
                throw new Exception("Invalid response format from database");
            }
        } else {
            // Log the error with more details
            error_log("Error inserting physical examination. HTTP Code: " . $http_code);
            error_log("Response: " . $response);
            error_log("CURL Error: " . $curl_error);
            throw new Exception("Failed to save physical examination data. Error code: " . $http_code);
        }

    } catch (Exception $e) {
        // Log the error and redirect with error message
        error_log("Error in physical-examination-handler.php: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ../views/forms/physical-examination-form.php?error=1');
        exit();
    }
} else {
    // Not a POST request - redirect back to form
    header('Location: ../views/forms/physical-examination-form.php');
    exit();
}
?> 