<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Debug session data
error_log("Session data in physical-examination-form.php: " . print_r($_SESSION, true));
error_log("Role ID type: " . gettype($_SESSION['role_id']) . ", Value: " . $_SESSION['role_id']);

// Process POST data from the dashboard form if available
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['screening_id']) && !empty($_POST['screening_id'])) {
        $_SESSION['screening_id'] = $_POST['screening_id'];
        error_log("Setting screening_id in session from POST: " . $_POST['screening_id']);
    }
    
    if (isset($_POST['donor_id']) && !empty($_POST['donor_id'])) {
        $_SESSION['donor_id'] = $_POST['donor_id'];
        error_log("Setting donor_id in session from POST: " . $_POST['donor_id']);
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../public/login.php");
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    error_log("Invalid role_id: " . $_SESSION['role_id']);
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// For staff role (role_id 3), check for required session variables
if ($_SESSION['role_id'] == 3) {
if (!isset($_SESSION['donor_id'])) {
        error_log("Missing donor_id in session for staff - redirecting to dashboard");
        header('Location: ../../../public/Dashboards/dashboard-staff-physical-submission.php');
    exit();
}
    if (!isset($_SESSION['screening_id'])) {
        error_log("Missing screening_id in session for staff - redirecting to dashboard");
        header('Location: ../../../public/Dashboards/dashboard-staff-physical-submission.php');
    exit();
    }
}

// Debug log to check all session variables
error_log("All session variables in physical-examination-form.php: " . print_r($_SESSION, true));

// Get the donor_id from session
$donor_id = $_SESSION['donor_id'];
error_log("Processing donor_id: $donor_id");

// Initialize PDO connection
try {
    $host = 'nwakbxwglhxcpunrzstf.supabase.co';
    $db   = 'postgres';
    $port = '5432';
    $user = 'postgres';
    $pass = 'Red@Cross_2023';
    $charset = 'utf8mb4';

    $dsn = "pgsql:host=$host;port=$port;dbname=$db;user=$user;password=$pass";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    error_log("PDO connection successfully established");
} catch (\PDOException $e) {
    error_log("PDO connection error: " . $e->getMessage());
    // Fallback to using curl if PDO connection fails
    $pdo = null;
}

// Check if user is an interviewer (staff with user_staff_role = 'Interviewer')
$is_interviewer = false;
$is_physician = false;

if ($_SESSION['role_id'] == 3) {
    // Get the user's staff role from the database using a direct query
    $user_id = $_SESSION['user_id'];
    
    if ($pdo) {
        try {
            // Use direct database query instead of cURL
            $stmt = $pdo->prepare("SELECT user_staff_roles FROM user_roles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $staff_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Staff role check result: " . print_r($staff_data, true));
            
            if ($staff_data && isset($staff_data['user_staff_roles'])) {
                $user_staff_role = strtolower($staff_data['user_staff_roles']);
                
                // Only check for 'physician' role (lowercase)
                $is_physician = ($user_staff_role === 'physician');
                
                error_log("User staff role: " . $staff_data['user_staff_roles']);
                error_log("Is physician: " . ($is_physician ? 'true' : 'false'));
            } else {
                error_log("No user staff roles found for user ID: $user_id");
                
                // If we have role_id 3 (staff) but no specific staff role data returned
                if ($_SESSION['role_id'] == 3) {
                    // Assume this is a physician as fallback
                    $is_physician = true;
                    error_log("No staff role data found for role_id 3, assuming physician");
                }
            }
        } catch (\PDOException $e) {
            error_log("PDO query error: " . $e->getMessage());
            // Fallback to cURL if the query fails
            $pdo = null;
        }
    }
    
    // Fallback to cURL if PDO is not available or query failed
    if (!$pdo) {
        error_log("Using cURL fallback for user role detection");
        // Initialize cURL
        $ch = curl_init(SUPABASE_URL . '/rest/v1/user_roles?select=user_staff_roles&user_id=eq.' . $user_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("Staff role check response: " . $response);
        error_log("HTTP code: " . $http_code);

        if ($http_code === 200) {
            $staff_data = json_decode($response, true);
            if (is_array($staff_data) && !empty($staff_data)) {
                $user_staff_roles = strtolower($staff_data[0]['user_staff_roles']);
                // Only check for 'physician' role (lowercase)
                $is_physician = ($user_staff_roles === 'physician');
                
                error_log("User staff role: " . $staff_data[0]['user_staff_roles']);
                error_log("Is physician: " . ($is_physician ? 'true' : 'false'));
            }
        }
    }
}

// For physicians, get the vital signs data from the physical_examination table
$vitals_data = null;
if ($is_physician && isset($_SESSION['donor_id'])) {
    $donor_id = $_SESSION['donor_id'];
    error_log("Physician: looking up vital signs for donor_id: $donor_id");
    
    // Direct query to physical_examination table to get the latest values
    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=blood_pressure,pulse_rate,body_temp&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Physical examination table response code: $http_code");
    error_log("Physical examination table data: $response");
    
    $physical_data = json_decode($response, true);
    
    if (is_array($physical_data) && !empty($physical_data) && isset($physical_data[0])) {
        $vitals_data = $physical_data[0];
        error_log("Found vitals data from physical examination table: " . print_r($vitals_data, true));
    } else {
        error_log("No vitals data found in physical examination table, checking screening_form");
        
        // If no data in physical_examination, check screening_form as fallback
        $screening_id = isset($_SESSION['screening_id']) ? $_SESSION['screening_id'] : null;
        if ($screening_id) {
            $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=blood_pressure,pulse_rate,body_temp&screening_id=eq.' . $screening_id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("Screening form response code: $http_code");
            error_log("Screening form data: $response");
            
            $screening_data = json_decode($response, true);
            
            if (is_array($screening_data) && !empty($screening_data) && isset($screening_data[0])) {
                $vitals_data = $screening_data[0];
                error_log("Found vitals data from screening form: " . print_r($vitals_data, true));
            } else {
                error_log("No vitals data found in screening form either");
            }
        }
    }
    
    // Log what we found
    if ($vitals_data) {
        error_log("Final vitals data to be displayed: " . print_r($vitals_data, true));
    } else {
        error_log("No vitals data found in any table");
    }
}

// Final fallback - check if we have data in the POST request or $_SESSION
// This would happen if data was sent from the dashboard but not properly saved yet
if (!$vitals_data || (!$vitals_data['blood_pressure'] && !$vitals_data['pulse_rate'] && !$vitals_data['body_temp'])) {
    error_log("Attempting to get vital signs from POST or SESSION data");
    
    // Initialize vital signs data
    $vital_signs_from_request = [
        'blood_pressure' => null,
        'pulse_rate' => null,
        'body_temp' => null
    ];
    
    // Check POST data
    if (isset($_POST['blood_pressure']) && !empty($_POST['blood_pressure'])) {
        $vital_signs_from_request['blood_pressure'] = $_POST['blood_pressure'];
        error_log("Found blood_pressure in POST: " . $_POST['blood_pressure']);
    }
    
    if (isset($_POST['pulse_rate']) && !empty($_POST['pulse_rate'])) {
        $vital_signs_from_request['pulse_rate'] = $_POST['pulse_rate'];
        error_log("Found pulse_rate in POST: " . $_POST['pulse_rate']);
    }
    
    if (isset($_POST['body_temp']) && !empty($_POST['body_temp'])) {
        $vital_signs_from_request['body_temp'] = $_POST['body_temp'];
        error_log("Found body_temp in POST: " . $_POST['body_temp']);
    }
    
    // Check if we found any data
    if ($vital_signs_from_request['blood_pressure'] || $vital_signs_from_request['pulse_rate'] || $vital_signs_from_request['body_temp']) {
        $vitals_data = $vital_signs_from_request;
        error_log("Using vital signs from request data: " . print_r($vitals_data, true));
    } else {
        error_log("No vital signs found in request data either");
    }
}

// Handle form submission directly in this file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug log the raw POST data
        error_log("Raw POST data: " . print_r($_POST, true));
        
        // Check if screening_id exists
        if (!isset($_SESSION['screening_id'])) {
            throw new Exception("No screening_id found in session");
        }
        
        // Prepare base data for insertion
        $physical_exam_data = [
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

        // For all roles, include all fields if they're provided
        if (isset($_POST['blood_pressure']) && !empty($_POST['blood_pressure'])) {
            $physical_exam_data['blood_pressure'] = $_POST['blood_pressure'];
        }
        
        if (isset($_POST['pulse_rate']) && !empty($_POST['pulse_rate'])) {
            $physical_exam_data['pulse_rate'] = intval($_POST['pulse_rate']);
        }
        
        if (isset($_POST['body_temp']) && !empty($_POST['body_temp'])) {
            $physical_exam_data['body_temp'] = floatval($_POST['body_temp']);
        }
        
        if (isset($_POST['gen_appearance']) && !empty($_POST['gen_appearance'])) {
            $physical_exam_data['gen_appearance'] = $_POST['gen_appearance'];
        }
        
        if (isset($_POST['skin']) && !empty($_POST['skin'])) {
            $physical_exam_data['skin'] = $_POST['skin'];
        }
        
        if (isset($_POST['heent']) && !empty($_POST['heent'])) {
            $physical_exam_data['heent'] = $_POST['heent'];
        }
        
        if (isset($_POST['heart_and_lungs']) && !empty($_POST['heart_and_lungs'])) {
            $physical_exam_data['heart_and_lungs'] = $_POST['heart_and_lungs'];
        }
        
        if (isset($_POST['remarks']) && !empty($_POST['remarks'])) {
            $physical_exam_data['remarks'] = $_POST['remarks'];
        } else {
            $physical_exam_data['remarks'] = "Pending";
        }
        
        if (isset($_POST['reason']) && !empty($_POST['reason'])) {
            $physical_exam_data['reason'] = $_POST['reason'];
        }
        
        if (isset($_POST['blood_bag_type']) && !empty($_POST['blood_bag_type'])) {
            $physical_exam_data['blood_bag_type'] = $_POST['blood_bag_type'];
        }
        
        error_log("Submitting complete data: " . print_r($physical_exam_data, true));

        // Determine whether to insert or update based on role
        $donor_id = intval($_SESSION['donor_id']);
        $should_check_existing = ($is_physician); // Only physicians should check for existing records to update

        // If we need to check for existing records
        if ($should_check_existing) {
            // Check if a record already exists for this donor
            $existing_record_check = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
            curl_setopt($existing_record_check, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($existing_record_check, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($existing_record_check);
            $http_code = curl_getinfo($existing_record_check, CURLINFO_HTTP_CODE);
            curl_close($existing_record_check);
            
            $existing_records = json_decode($response, true);
            $record_exists = (is_array($existing_records) && !empty($existing_records) && isset($existing_records[0]));
            
            if ($record_exists) {
                $physical_exam_id = $existing_records[0]['physical_exam_id'];
                error_log("Found existing record ID: $physical_exam_id for donor ID: $donor_id - will UPDATE");
                
                // Initialize cURL session for Supabase to UPDATE instead of INSERT
                $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . $physical_exam_id);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH'); // Use PATCH to update
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json',
                    'Prefer: return=minimal'
                ]);
                
                // Convert the data to JSON for the request
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($physical_exam_data));
                
                // Execute the request
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                // Check if the update was successful
                if ($http_code >= 200 && $http_code < 300) {
                    error_log("Successfully updated physical examination record: $http_code");
                    
                    // Double check role before redirect
                    error_log("Final role check before redirect:");
                    error_log("role_id: " . $_SESSION['role_id']);
                    error_log("is_physician: " . ($is_physician ? 'true' : 'false'));
                    
                    // Redirect based on role
                    if ($_SESSION['role_id'] == 1) {
                        // Admin redirect to blood collection
                        error_log("Redirecting admin to blood collection form");
                        header('Location: blood-collection-form.php');
                        exit();
                    } else if ($_SESSION['role_id'] == 3) {
                        if ($is_physician) {
                            // Physician - redirect to physical submission dashboard
                            error_log("Redirecting physician to physical submission dashboard");
                            header('Location: ../../../public/Dashboards/dashboard-staff-physical-submission.php');
                            exit();
                        } else {
                            // Other staff roles - redirect to main dashboard
                            error_log("Redirecting other staff to main dashboard");
                            header('Location: ../../../public/Dashboards/dashboard-staff-main.php');
                            exit();
                        }
                    } else {
                        // Default redirect for other roles
                        error_log("Redirecting to main dashboard (default)");
                        header('Location: ../../../public/Dashboards/dashboard-staff-main.php');
                        exit();
                    }
                } else {
                    // If the update failed, throw an exception
                    throw new Exception("Failed to update physical examination data. HTTP code: $http_code, Response: $response");
                }
            } else {
                error_log("No existing records found for donor ID: $donor_id - will INSERT");
                $should_insert = true;
            }
        } else {
            // For admin (role_id 1) and interviewer (role_id 3 + interviewer), always insert
            error_log("User role requires INSERT (admin or interviewer)");
            $should_insert = true;
        }

        // INSERT logic (for admin, interviewer, or physician with no existing record)
        if ($should_insert ?? true) {
        // Initialize cURL session for Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');

        // Set the headers
        $headers = array(
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        );

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($physical_exam_data));

        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Debug log
        error_log("Supabase response code: " . $http_code);
        error_log("Supabase response: " . $response);
        
        curl_close($ch);

        if ($http_code === 201) {
                // Record saved successfully
                error_log("Physical examination form submitted successfully");
                
                // Redirect based on role
                if ($_SESSION['role_id'] == 1) {
                    // Admin redirect to blood collection
                    header('Location: blood-collection-form.php');
                    exit();
                } else if ($_SESSION['role_id'] == 3 && $is_physician) {
                    // Physician - redirect to blood collection
                    header('Location: ../../../public/Dashboards/dashboard-staff-physical-submission.php');
                    exit();
                } else {
                    // Other staff redirect
                    header('Location: ../../../public/Dashboards/dashboard-staff-main.php');
                    exit();
                }
            } else {
                throw new Exception("Failed to submit physical examination form. HTTP Code: " . $http_code . ", Response: " . $response);
            }
        }
        
    } catch (Exception $e) {
        error_log("Error in physical-examination-form.php: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        // Redirect back to the form with an error parameter
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Physical Examination Form</title>
    <style>
       /* General Styling */
    body {
        font-family: Arial, sans-serif;
        background-color: #f9f9f9;
        margin: 0;
        padding: 20px;
    }

    /* Physical Examination Section */
    .physical-examination {
        background: #fff;
        padding: 2%;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        max-width: 900px;
        margin: auto;
    }

    .physical-examination h3 {
        font-size: 18px;
        font-weight: bold;
        color: #242b31;
        margin-bottom: 15px;
        border-bottom: 2px solid #a82020;
        padding-bottom: 8px;
    }

    /* Table Styling */
    .physical-examination-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    .physical-examination-table th,
    .physical-examination-table td {
        border: 1px solid #ccc;
        padding: 10px;
        text-align: center;
    }

    .physical-examination-table th {
        background-color: #242b31;
        color: white;
        font-weight: bold;
    }

    .physical-examination-table input[type="text"] {
        width: 90%;
        padding: 6px;
        border: 1px solid #bbb;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    .physical-examination-table input[type="text"]:focus {
        border-color: #a82020;
        outline: none;
    }

    /* Remarks Section */
    .remarks-section {
        margin-bottom: 20px;
    }

    .remarks-section h4 {
        font-size: 16px;
        font-weight: bold;
        color: #242b31;
        margin-bottom: 10px;
    }

    .remarks-options {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 10px;
    }

    .remarks-option {
        display: flex;
        align-items: center;
        font-weight: bold;
        color: #242b31;
        gap: 8px;
        background: #fff;
        padding: 10px 15px;
        border-radius: 6px;
        border: 1px solid #ddd;
        cursor: pointer;
    }

    /* Custom Radio Button Styling */
    .remarks-option input {
        opacity: 0;
        position: absolute;
    }

    .radio-mark {
        width: 18px;
        height: 18px;
        background-color: #fff;
        border: 2px solid #a82020;
        border-radius: 50%;
        display: inline-block;
        position: relative;
        transition: background-color 0.3s ease;
    }

    .remarks-option input:checked ~ .radio-mark {
        background-color: #a82020;
    }

    .radio-mark::after {
        content: "";
        position: absolute;
        display: none;
        left: 50%;
        top: 50%;
        width: 8px;
        height: 8px;
        background-color: white;
        border-radius: 50%;
        transform: translate(-50%, -50%);
    }

    .remarks-option input:checked ~ .radio-mark::after {
        display: block;
    }

    /* Reason Input Styling */
    .reason-input {
        margin-top: 15px;
        margin-bottom: 30px;
    }

    .reason-input textarea {
        width: 100%;
        max-width: 100%;
        padding: 12px;
        border: 1px solid #bbb;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.3s ease;
        resize: vertical;
        min-height: 150px;
    }

    .reason-input label {
        display: block;
        margin-bottom: 10px;
        font-weight: bold;
        color: #242b31;
    }

    .reason-input textarea:focus {
        border-color: #a82020;
        outline: none;
        box-shadow: 0 0 5px rgba(168, 32, 32, 0.2);
    }

    .physical-examination-table input[type="number"] {
        width: 90%;
        padding: 6px;
        border: 1px solid #bbb;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    .physical-examination-table input[type="number"]:focus {
        border-color: #a82020;
        outline: none;
    }

    /* Invalid input highlighting */
    .physical-examination-table input:invalid {
        border-color: #a82020;
        background-color: #fff8f8;
    }

    .physical-examination-table input:invalid:focus {
        box-shadow: 0 0 0 0.2rem rgba(168, 32, 32, 0.25);
    }

    /* Form validation message styling */
    .physical-examination-table input:invalid + span::before {
        content: '⚠';
        color: #a82020;
        margin-left: 5px;
    }

    @media (max-width: 600px) {
        .reason-input textarea {
            min-height: 120px;
        }
    }

    /* Blood Bag Section */
    .blood-bag-section {
        margin-bottom: 20px;
    }

    .blood-bag-section h4 {
        font-size: 16px;
        font-weight: bold;
        color: #242b31;
        margin-bottom: 10px;
    }

    .blood-bag-options {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }

    .blood-bag-option {
        display: flex;
        align-items: center;
        font-weight: bold;
        color: #242b31;
        gap: 8px;
        background: #fff;
        padding: 10px 15px;
        border-radius: 6px;
        border: 1px solid #ddd;
        cursor: pointer;
    }

    /* Custom Checkbox Styling */
    .blood-bag-option input {
        opacity: 0;
        position: absolute;
    }

    .checkmark {
        width: 18px;
        height: 18px;
        background-color: #fff;
        border: 2px solid #a82020;
        border-radius: 4px;
        display: inline-block;
        position: relative;
        transition: background-color 0.3s ease;
    }

    .blood-bag-option input:checked ~ .checkmark {
        background-color: #a82020;
    }

    .checkmark::after {
        content: "";
        position: absolute;
        display: none;
        left: 5px;
        top: 1px;
        width: 5px;
        height: 10px;
        border: solid white;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }

    .blood-bag-option input:checked ~ .checkmark::after {
        display: block;
    }
        /* Submit Button Section */
        .submit-section {
            text-align: right;
            margin-top: 20px;
        }

        .submit-button {
            background-color: #a82020;
            color: white;
            font-weight: bold;
            padding: 12px 22px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-size: 15px;
        }

        .submit-button:hover {
            background-color: #8a1a1a;
            transform: translateY(-2px);
        }

        .submit-button:active {
            transform: translateY(0);
        }
        /* Submit Button Section */
        .submit-section {
            text-align: right;
            margin-top: 20px;
        }

        .submit-button {
            background-color: #a82020;
            color: white;
            font-weight: bold;
            padding: 12px 22px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-size: 15px;
        }

        .submit-button:hover {
            background-color: #8a1a1a;
            transform: translateY(-2px);
        }

        .submit-button:active {
            transform: translateY(0);
        }

        /* Loader Animation -- Modal Design */
        .loading-spinner {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 8px solid #ddd;
            border-top: 8px solid #a82020;
            animation: rotateSpinner 1s linear infinite;
            display: none;
            z-index: 10000;
            transform: translate(-50%, -50%);
        }

        @keyframes rotateSpinner {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Confirmation Modal */
        .confirmation-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            text-align: center;
            z-index: 9999;
            border-radius: 10px;
            width: 300px;
            display: none;
            opacity: 0;
        }

        /* Fade-in and Fade-out Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -55%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
            to {
                opacity: 0;
                transform: translate(-50%, -55%);
            }
        }

        .confirmation-modal.show {
            display: block;
            animation: fadeIn 0.3s ease-in-out forwards;
        }

        .confirmation-modal.hide {
            animation: fadeOut 0.3s ease-in-out forwards;
        }

        .modal-header {
            font-size: 18px;
            font-weight: bold;
            color: #242b31;
            margin-bottom: 15px;
        }

        .modal-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .modal-button {
            width: 45%;
            padding: 10px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        .cancel-action {
            background: #6c757d;
            color: white;
        }

        .cancel-action:hover {
            background: #5a6268;
        }

        .confirm-action {
            background: #a82020;
            color: white;
        }

        .confirm-action:hover {
            background: #8a1a1a;
        }
/* Responsive Adjustments */
@media (max-width: 600px) {
    .physical-examination-table th,
    .physical-examination-table td {
        padding: 8px;
        font-size: 14px;
    }

    .remarks-options,
    .blood-bag-option {
        flex-direction: column;
    }

    .reason-input textarea {
        min-height: 80px;
    }
}
    </style>
</head>
<body>
        <div class="physical-examination">
            <?php if (isset($_GET['error']) && isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 20px; border: 1px solid #f5c6cb; border-radius: 4px;">
                    <?php 
                    echo htmlspecialchars($_SESSION['error_message']); 
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if ($is_physician): ?>
            <!-- Special message for physicians -->
            <div class="physician-notice" style="background-color: #e8f4f8; padding: 15px; margin-bottom: 20px; border-radius: 5px; border-left: 5px solid #242b31;">
                <h4 style="color: #242b31; margin-top: 0;">Physician View</h4>
                
                <?php if ($vitals_data && (isset($vitals_data['blood_pressure']) || isset($vitals_data['pulse_rate']) || isset($vitals_data['body_temp']))): ?>
                    <p style="margin-bottom: 5px;">The vital signs (Blood Pressure, Pulse Rate, and Body Temp) shown below were collected earlier during screening and are displayed for your reference.</p>
                    <p style="margin-bottom: 0;">Please review these vital signs to help determine if the donor is healthy, and complete all other fields as needed to proceed with the physical examination.</p>
                <?php else: ?>
                    <p style="margin-bottom: 0;">Please complete all fields as needed to proceed with the physical examination.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <form id="physicalExamForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <h3>V. PHYSICAL EXAMINATION (To be accomplished by the Blood Bank Physician)</h3>
                <!-- Add hidden field for donor_id -->
                <input type="hidden" name="donor_id" value="<?php echo isset($_SESSION['donor_id']) ? htmlspecialchars($_SESSION['donor_id']) : ''; ?>">
                
                <table class="physical-examination-table">
                    <thead>
                        <tr>
                            <th>Blood Pressure</th>
                            <th>Pulse Rate</th>
                            <th>Body Temp.</th>
                            <th>Gen. Appearance</th>
                            <th>Skin</th>
                            <th>HEENT</th>
                            <th>Heart and Lungs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <?php if ($is_physician && $vitals_data && isset($vitals_data['blood_pressure'])): ?>
                                    <!-- For physicians: Read-only blood pressure with visual indicator -->
                                    <div style="font-weight: bold; font-size: 1.2em; color: #242b31; background-color: #f5f5f5; padding: 8px; border-radius: 4px; border-left: 3px solid #a82020;">
                                        <?php echo htmlspecialchars($vitals_data['blood_pressure']); ?>
                                    </div>
                                    <input type="hidden" name="blood_pressure" value="<?php echo htmlspecialchars($vitals_data['blood_pressure']); ?>">
                                <?php else: ?>
                                    <input type="text" name="blood_pressure" placeholder="e.g., 120/80" pattern="[0-9]{2,3}/[0-9]{2,3}" title="Format: systolic/diastolic e.g. 120/80" required>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_physician && $vitals_data && isset($vitals_data['pulse_rate'])): ?>
                                    <!-- For physicians: Read-only pulse rate with visual indicator -->
                                    <div style="font-weight: bold; font-size: 1.2em; color: #242b31; background-color: #f5f5f5; padding: 8px; border-radius: 4px; border-left: 3px solid #a82020;">
                                        <?php echo htmlspecialchars($vitals_data['pulse_rate']); ?> BPM
                                    </div>
                                    <input type="hidden" name="pulse_rate" value="<?php echo htmlspecialchars($vitals_data['pulse_rate']); ?>">
                                <?php else: ?>
                                    <input type="number" name="pulse_rate" placeholder="BPM" min="0" max="300" required>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_physician && $vitals_data && isset($vitals_data['body_temp'])): ?>
                                    <!-- For physicians: Read-only body temperature with visual indicator -->
                                    <div style="font-weight: bold; font-size: 1.2em; color: #242b31; background-color: #f5f5f5; padding: 8px; border-radius: 4px; border-left: 3px solid #a82020;">
                                        <?php echo htmlspecialchars($vitals_data['body_temp']); ?> °C
                                    </div>
                                    <input type="hidden" name="body_temp" value="<?php echo htmlspecialchars($vitals_data['body_temp']); ?>">
                                <?php else: ?>
                                    <input type="number" name="body_temp" placeholder="°C" step="0.1" min="35" max="42" required>
                                <?php endif; ?>
                            </td>
                            <td><input type="text" name="gen_appearance" placeholder="Enter observation" required></td>
                            <td><input type="text" name="skin" placeholder="Enter observation" required></td>
                            <td><input type="text" name="heent" placeholder="Enter observation" required></td>
                            <td><input type="text" name="heart_and_lungs" placeholder="Enter observation" required></td>
                        </tr>
                    </tbody>
                </table>
            
                <div class="remarks-section">
                    <h4>REMARKS:</h4>
                    <div class="remarks-options">
                        <label class="remarks-option">
                            <input type="radio" name="remarks" value="Accepted" > 
                            <span class="radio-mark"></span>
                            Accepted
                        </label>
                        <label class="remarks-option">
                            <input type="radio" name="remarks" value="Temporarily Deferred"> 
                            <span class="radio-mark"></span>
                            Temporarily Deferred
                        </label>
                        <label class="remarks-option">
                            <input type="radio" name="remarks" value="Permanently Deferred"> 
                            <span class="radio-mark"></span>
                            Permanently Deferred
                        </label>
                        <label class="remarks-option">
                            <input type="radio" name="remarks" value="Refused"> 
                            <span class="radio-mark"></span>
                            Refused
                        </label>
                    </div>
                    <div class="reason-input">
                        <label>Reason: <textarea name="reason" placeholder="Enter detailed reason" rows="4"></textarea></label>
                    </div>
                </div>
            
                <div class="blood-bag-section">
                    <h4>Blood bag to be used: (mark [√] appropriate box)</h4>
                    <div class="blood-bag-options">
                        <label class="blood-bag-option">
                            <input type="radio" name="blood_bag_type" value="Single" > 
                            <span class="checkmark"></span>
                            Single
                        </label>
                        <label class="blood-bag-option">
                            <input type="radio" name="blood_bag_type" value="Multiple"> 
                            <span class="checkmark"></span>
                            Multiple
                        </label>
                        <label class="blood-bag-option">
                            <input type="radio" name="blood_bag_type" value="Top & Bottom"> 
                            <span class="checkmark"></span>
                            Top & Bottom
                        </label>
                    </div>
                </div>
                <div class="submit-section">
                    <button class="submit-button" id="triggerModalButton">Submit</button>
                </div>
            </form>
        </div>
        <!-- Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationDialog">
        <div class="modal-header">Do you want to continue?</div>
        <div class="modal-actions">
            <button class="modal-button cancel-action" id="cancelButton">No</button>
            <button class="modal-button confirm-action" id="confirmButton">Yes</button>
        </div>
    </div>    

    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner"></div>



    <script>
        let confirmationDialog = document.getElementById("confirmationDialog");
        let loadingSpinner = document.getElementById("loadingSpinner");
        let triggerModalButton = document.getElementById("triggerModalButton");
        let cancelButton = document.getElementById("cancelButton");
        let confirmButton = document.getElementById("confirmButton");
        let physicalExamForm = document.getElementById("physicalExamForm");

        // Form validation function
        function validateForm() {
            // Validate all required fields
            const requiredFields = document.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '#bbb';
                }
            });
            
            // Validate that at least one option is selected for the required radio button groups
            if (!document.querySelector('input[name="remarks"]:checked')) {
                alert('Please select a Remarks option');
                return false;
            }
            
            if (!document.querySelector('input[name="blood_bag_type"]:checked')) {
                alert('Please select a Blood Bag type');
                return false;
            }
            
            return isValid;
        }

        // Open Modal
        triggerModalButton.addEventListener("click", function(e) {
            e.preventDefault();
            if (!validateForm()) {
                alert('Please fill in all required fields');
                return;
            }
            confirmationDialog.classList.remove("hide");
            confirmationDialog.classList.add("show");
            confirmationDialog.style.display = "block";
            triggerModalButton.disabled = true;
        });

        // Close Modal Function
        function closeModal() {
            confirmationDialog.classList.remove("show");
            confirmationDialog.classList.add("hide");
            setTimeout(() => {
                confirmationDialog.style.display = "none";
                triggerModalButton.disabled = false;
            }, 300);
        }

        // Yes Button (Submit Form)
        confirmButton.addEventListener("click", function() {
            closeModal();
            loadingSpinner.style.display = "block";
            physicalExamForm.submit();
        });

        // No Button (Closes Modal)
        cancelButton.addEventListener("click", function() {
            closeModal();
        });

        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!$is_physician && $_SESSION['role_id'] != 1): ?>
            // If not physician or admin, show unauthorized message
            const formContainer = document.querySelector('.physical-examination');
            if (formContainer) {
                formContainer.innerHTML = `
                    <div style="background-color: #f8f8f8; color: #242b31; padding: 20px; border-radius: 5px; text-align: center; border-left: 5px solid #a82020;">
                        <h3>Unauthorized Access</h3>
                        <p>Only physicians are authorized to access and complete this physical examination form.</p>
                        <a href="../../../public/Dashboards/dashboard-staff-main.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background-color: #a82020; color: white; text-decoration: none; border-radius: 5px;">Return to Dashboard</a>
                    </div>
                `;
            }
            <?php elseif ($is_physician): ?>
            // For physicians, show a notice that they need to complete all fields
            const formHeader = document.querySelector('h3');
            if (formHeader) {
                const notice = document.createElement('div');
                notice.style.backgroundColor = '#f8f8f8';
                notice.style.color = '#242b31';
                notice.style.padding = '10px';
                notice.style.borderRadius = '5px';
                notice.style.marginBottom = '15px';
                notice.style.borderLeft = '5px solid #a82020';
                notice.innerHTML = '<strong>Notice:</strong> As a Physician, you are required to complete all fields in this physical examination form.';
                formHeader.parentNode.insertBefore(notice, formHeader.nextSibling);
            }
            
            // Show a subtle highlight on required fields
            document.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
                if (field.type !== 'radio' && field.type !== 'checkbox') {
                    field.style.borderLeft = '3px solid #a82020';
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>