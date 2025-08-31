<?php
// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Make sure we have an active session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug log
error_log("Hospital account check - Session: " . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in - redirecting to login");
    header("Location: /RED-CROSS-THESIS/public/login.php");
    exit();
}

// Convert role_id to integer for proper comparison
$user_role_id = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
error_log("User role ID: " . $user_role_id . " (Type: " . gettype($user_role_id) . ")");

// Check for hospital role (role_id = 2)
$required_role = 2; // Hospital Role
if ($user_role_id !== $required_role) {
    error_log("Access denied - User role_id: " . $user_role_id . ", Required role: " . $required_role);
    header("Location: /RED-CROSS-THESIS/public/login.php");
    exit();
}

// Fetch user details from the users table
$user_id = $_SESSION['user_id'];

// Function to make Supabase request
function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $ch = curl_init($url);
    
    $headers = array(
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Fetch user data from Supabase
$users_response = supabaseRequest("users?user_id=eq.$user_id&select=surname,first_name");

if ($users_response && is_array($users_response) && count($users_response) > 0) {
    $user_data = $users_response[0];
    $_SESSION['user_surname'] = $user_data['surname'];
    $_SESSION['user_first_name'] = $user_data['first_name'];
}

// Remove PDO connection code and replace with Supabase REST API handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_blood_request'])) {
    try {
        // Set when_needed based on is_asap with current timezone
        $whenNeeded = isset($_POST['is_asap']) && $_POST['is_asap'] === 'true' 
            ? date('Y-m-d H:i:s') 
            : $_POST['when_needed'];

        // Get current date and time in Asia/Manila timezone
        $currentDateTime = date('Y-m-d H:i:s');

        // Prepare the request data
        $requestData = array(
            'user_id' => $_SESSION['user_id'],
            'patient_name' => $_POST['patient_name'],
            'patient_age' => intval($_POST['patient_age']),
            'patient_gender' => $_POST['patient_gender'],
            'patient_diagnosis' => $_POST['patient_diagnosis'],
            'patient_blood_type' => $_POST['blood_type'],
            'rh_factor' => $_POST['rh_factor'],
            'component' => $_POST['component'],
            'units_requested' => intval($_POST['units_requested']),
            'is_asap' => isset($_POST['is_asap']) && $_POST['is_asap'] === 'true',
            'when_needed' => $whenNeeded,
            'physician_name' => $_SESSION['user_surname'],
            'physician_signature' => $_POST['physician_signature'],
            'hospital_admitted' => $_SESSION['user_first_name'],
            'status' => 'Pending',
            'requested_on' => $currentDateTime,
            'last_updated' => $currentDateTime
        );

        // Make POST request to Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_requests');
        
        // Set headers
        $headers = array(
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        );

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if request was successful
        if ($httpCode >= 200 && $httpCode < 300) {
            $_SESSION['success_message'] = "Blood request submitted successfully!";
        } else {
            throw new Exception("Error submitting request. HTTP Code: " . $httpCode);
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error submitting request: " . $e->getMessage();
    }

    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>