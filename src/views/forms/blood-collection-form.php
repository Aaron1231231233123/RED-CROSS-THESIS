<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Debug logging
error_log("blood-collection-form.php accessed. User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role ID: " . ($_SESSION['role_id'] ?? 'not set'));
error_log("POST data: " . json_encode($_POST));
error_log("Session data: " . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../public/login.php");
    exit();
}

// Check if role_id or donor_id are coming from POST data and set them in session
if (isset($_POST['role_id']) && !isset($_SESSION['role_id'])) {
    $_SESSION['role_id'] = $_POST['role_id'];
    error_log("Setting role_id from POST: " . $_POST['role_id']);
}

if (isset($_POST['donor_id']) && !isset($_SESSION['donor_id'])) {
    $_SESSION['donor_id'] = $_POST['donor_id'];
    error_log("Setting donor_id from POST: " . $_POST['donor_id']);
}

// Always ensure role_id 3 is allowed
if (isset($_POST['physical_exam_id']) && !empty($_POST['physical_exam_id'])) {
    error_log("Staff access attempt with physical_exam_id: " . $_POST['physical_exam_id']);
    if (!isset($_SESSION['role_id'])) {
        $_SESSION['role_id'] = 3; // Set staff role (3) for users coming from blood collection page
        error_log("Automatically setting role_id to 3 (staff) for user coming from blood collection page");
    }
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3) {
    error_log("Unauthorized access attempt to blood-collection-form.php. User role: " . $_SESSION['role_id']);
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// If we made it here, the user is either an admin or a phlebotomist staff member

// Check if donor_id exists in session
if (!isset($_SESSION['donor_id'])) {
    // For staff directly accessing from the blood collection dashboard,
    // the donor_id might be in POST but not yet in session
    if (isset($_POST['donor_id']) && !empty($_POST['donor_id'])) {
        $_SESSION['donor_id'] = $_POST['donor_id'];
        error_log("Setting donor_id from POST for staff user: " . $_POST['donor_id']);
    } 
    // Only redirect if we still don't have donor_id after the checks
    else {
        error_log("Missing donor_id in session and POST data");
        header('Location: ../../../public/Dashboards/dashboard-Inventory-System.php');
        exit();
    }
}

// Function to generate next sequence number
function getNextSequenceNumber($existing_numbers) {
    if (empty($existing_numbers)) {
        return '0001';
    }
    
    $max_number = 0;
    foreach ($existing_numbers as $number) {
        $sequence = intval(substr($number, -4));
        if ($sequence > $max_number) {
            $max_number = $sequence;
        }
    }
    return str_pad($max_number + 1, 4, '0', STR_PAD_LEFT);
}

// Generate unit serial number
$today = date('Ymd');
$prefix = "BC-" . $today . "-";

// Get existing serial numbers for today
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?select=unit_serial_number&unit_serial_number=like.' . urlencode($prefix . '%'),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$response = curl_exec($ch);
curl_close($ch);

$existing_numbers = [];
if ($response) {
    $results = json_decode($response, true);
    foreach ($results as $result) {
        $existing_numbers[] = $result['unit_serial_number'];
    }
}

$sequence = getNextSequenceNumber($existing_numbers);
$generated_serial = $prefix . $sequence;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount']) && isset($_POST['blood-bag'])) {
    try {
        // Get and validate unit serial number first
        $unit_serial_number = $_POST['serial_number'] ?? '';
        if (empty($unit_serial_number)) {
            // Regenerate the serial number if missing from POST
            $today = date('Ymd');
            $prefix = "BC-" . $today . "-";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?select=unit_serial_number&unit_serial_number=like.' . urlencode($prefix . '%'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $existing_numbers = [];
            if ($response) {
                $results = json_decode($response, true);
                foreach ($results as $result) {
                    $existing_numbers[] = $result['unit_serial_number'];
                }
            }
            $sequence = getNextSequenceNumber($existing_numbers);
            $unit_serial_number = $prefix . $sequence;
        }
        if (empty($unit_serial_number)) {
            throw new Exception("Unit serial number is required");
        }

        // Check if unit serial number already exists
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?unit_serial_number=eq.' . urlencode($unit_serial_number),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $existing_unit = json_decode($response, true);
        if (!empty($existing_unit)) {
            throw new Exception("This unit serial number is already in use. Please use a unique serial number.");
        }

        // Rest of the validation and data collection
        if (!isset($_POST['blood-bag'])) {
            throw new Exception("Please select a blood bag type");
        }

        $blood_bag_parts = explode('-', $_POST['blood-bag']);
        if (count($blood_bag_parts) < 2) {
            throw new Exception("Invalid blood bag format");
        }

        // Get the brand (last part)
        $blood_bag_brand = end($blood_bag_parts);
        // Get the type (everything before the brand)
        array_pop($blood_bag_parts);
        $blood_bag_type = implode('-', $blood_bag_parts);

        // Validate blood bag brand
        $valid_brands = ['KARMI', 'TERUMO', 'SPECIAL BAG', 'APHERESIS'];
        if (!in_array($blood_bag_brand, $valid_brands)) {
            throw new Exception("Invalid blood bag brand");
        }

        // Get other form data
        $amount_taken = !empty($_POST['amount']) ? floatval($_POST['amount']) : null;
        if ($amount_taken === null || $amount_taken <= 0) {
            throw new Exception("Please enter a valid amount");
        }
        
        // Make sure the amount is within a valid range to prevent numeric overflow
        // Now using integer for amount_taken (int4)
        if ($amount_taken > 999) {
            throw new Exception("Amount is too large. Maximum allowed is 999");
        }
        
        // Convert to integer for int4 field
        $amount_taken = intval($amount_taken);
        
        // Validate again after conversion to make sure we have a positive integer
        if ($amount_taken <= 0) {
            throw new Exception("Amount must be a positive integer value");
        }

        $is_successful = isset($_POST['successful']) ? $_POST['successful'] === 'YES' : null;
        if ($is_successful === null) {
            throw new Exception("Please select whether the collection was successful");
        }

        // Get textarea values with proper trimming and default values
        $donor_reaction = trim($_POST['reaction'] ?? '');
        $management_done = trim($_POST['management'] ?? '');
        
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';

        if (empty($start_time) || empty($end_time)) {
            throw new Exception("Start time and end time are required");
        }

        // Convert times to proper timestamp format
        $today = date('Y-m-d');
        $start_timestamp = date('Y-m-d\TH:i:s.000\Z', strtotime("$today $start_time"));
        $end_timestamp = date('Y-m-d\TH:i:s.000\Z', strtotime("$today $end_time"));

        // Get the latest screening_id that doesn't have a blood collection yet
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=screening_id&order=created_at.desc',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $screening_data = json_decode($response, true);
        if (empty($screening_data)) {
            throw new Exception("No screening form found");
        }

        // Find the first screening ID that doesn't have a blood collection
        $screening_id = null;
        foreach ($screening_data as $screening) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?screening_id=eq.' . $screening['screening_id'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $existing_collection = json_decode($response, true);
            if (empty($existing_collection)) {
                $screening_id = $screening['screening_id'];
                break;
            }
        }

        if ($screening_id === null) {
            throw new Exception("No available screening form found for blood collection");
        }

        // Prepare data for Supabase
        $data = [
            'screening_id' => $screening_id,
            'blood_bag_brand' => $blood_bag_brand,
            'blood_bag_type' => $blood_bag_type,
            'amount_taken' => intval($amount_taken), // Ensure it's an integer
            'is_successful' => $is_successful,
            'donor_reaction' => $donor_reaction ?: null, // Ensure empty string becomes null
            'management_done' => $management_done ?: null, // Ensure empty string becomes null
            'unit_serial_number' => $unit_serial_number, // Use validated unit serial number
            'start_time' => $start_timestamp,
            'end_time' => $end_timestamp,
            'status' => 'pending'
        ];

        // Add physical_exam_id if provided in the POST request
        if (isset($_POST['physical_exam_id']) && !empty($_POST['physical_exam_id'])) {
            $data['physical_exam_id'] = $_POST['physical_exam_id'];
            error_log('Using physical_exam_id from POST: ' . $_POST['physical_exam_id']);
        } else {
            // Fetch physical_exam_id for the current donor as fallback
            $donor_id = $_SESSION['donor_id'];
            
            $physical_exam_ch = curl_init();
            curl_setopt_array($physical_exam_ch, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $donor_id . "&select=physical_exam_id&order=created_at.desc&limit=1",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json'
                ]
            ]);
            $physical_exam_response = curl_exec($physical_exam_ch);
            curl_close($physical_exam_ch);
            
            $physical_exam_info = json_decode($physical_exam_response, true);
            if (!empty($physical_exam_info) && isset($physical_exam_info[0]['physical_exam_id'])) {
                $data['physical_exam_id'] = $physical_exam_info[0]['physical_exam_id'];
                error_log('Using physical_exam_id from database lookup: ' . $data['physical_exam_id']);
            } else {
                error_log('No physical_exam_id found for donor_id: ' . $donor_id);
            }
        }

        // Add debug logging
        error_log('Complete data sent to Supabase: ' . json_encode($data));
        
        // Send data to Supabase
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation', // Changed to get full response for debugging
                'X-Client-Info: blood-collection-form'  // Add client info for tracking
            ]
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Get curl error information if request failed
        if($response === false) {
            $curl_error = curl_error($ch);
            error_log('cURL Error: ' . $curl_error);
            throw new Exception('Connection error: ' . $curl_error);
        }
        
        curl_close($ch);

        // Debug information
        error_log('Supabase Response Code: ' . $http_code);
        error_log('Supabase Response: ' . $response);
        error_log('Data sent to Supabase: ' . json_encode($data));

        if ($http_code === 201) {
            // Parse the response to get the blood collection ID
            $collection_response = json_decode($response, true);
            $blood_collection_id = $collection_response[0]['blood_collection_id'] ?? null;
            
            if ($blood_collection_id) {
                // Manually create eligibility record since the DB trigger may not be executing
                error_log("Creating eligibility record after blood collection");
                
                // Get all necessary data for the eligibility record
                // 1. Get donor_id, medical_history_id from the screening record
                $screening_ch = curl_init();
                curl_setopt_array($screening_ch, [
                    CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?screening_id=eq." . $screening_id . "&select=donor_form_id,medical_history_id,blood_type,donation_type",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json'
                    ]
                ]);
                $screening_response = curl_exec($screening_ch);
                curl_close($screening_ch);
                
                $screening_info = json_decode($screening_response, true);
                
                if (!empty($screening_info)) {
                    $donor_id = $screening_info[0]['donor_form_id'] ?? $_SESSION['donor_id'];
                    $medical_history_id = $screening_info[0]['medical_history_id'] ?? null;
                    $blood_type = $screening_info[0]['blood_type'] ?? null;
                    $donation_type = $screening_info[0]['donation_type'] ?? null;
                    
                    // 2. Get physical_exam_id for this donor
                    $physical_exam_ch = curl_init();
                    curl_setopt_array($physical_exam_ch, [
                        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $donor_id . "&select=physical_exam_id&order=created_at.desc&limit=1",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json'
                        ]
                    ]);
                    $physical_exam_response = curl_exec($physical_exam_ch);
                    curl_close($physical_exam_ch);
                    
                    $physical_exam_info = json_decode($physical_exam_response, true);
                    $physical_exam_id = !empty($physical_exam_info) ? $physical_exam_info[0]['physical_exam_id'] : null;
                    
                    // Calculate end date - Default to 9 months for successful donations
                    $end_date = new DateTime();
                    if ($is_successful) {
                        $end_date->modify('+9 months');
                    } else {
                        $end_date->modify('+3 months'); // Default for failed collection
                    }
                    $end_date_formatted = $end_date->format('Y-m-d\TH:i:s.000\Z');
                    
                    // Determine the status
                    $status = $is_successful ? 'approved' : 'failed_collection';
                    
                    // Prepare eligibility data
                    $eligibility_data = [
                        'donor_id' => $donor_id,
                        'medical_history_id' => $medical_history_id,
                        'screening_id' => $screening_id,
                        'physical_exam_id' => $physical_exam_id,
                        'blood_collection_id' => $blood_collection_id,
                        'blood_type' => $blood_type,
                        'donation_type' => $donation_type,
                        'blood_bag_type' => $blood_bag_type,
                        'blood_bag_brand' => $blood_bag_brand,
                        'amount_collected' => $amount_taken,
                        'collection_successful' => $is_successful,
                        'donor_reaction' => $donor_reaction ?: null,
                        'management_done' => $management_done ?: null,
                        'collection_start_time' => $start_timestamp,
                        'collection_end_time' => $end_timestamp,
                        'unit_serial_number' => $unit_serial_number,
                        'start_date' => date('Y-m-d\TH:i:s.000\Z'),
                        'end_date' => $end_date_formatted,
                        'status' => $status,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Remove null values to prevent database errors
                    foreach ($eligibility_data as $key => $value) {
                        if ($value === null) {
                            unset($eligibility_data[$key]);
                        }
                    }
                    
                    // Create eligibility record
                    $eligibility_ch = curl_init();
                    curl_setopt_array($eligibility_ch, [
                        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => json_encode($eligibility_data),
                        CURLOPT_HTTPHEADER => [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json',
                            'Prefer: return=representation'
                        ]
                    ]);
                    
                    $eligibility_response = curl_exec($eligibility_ch);
                    $eligibility_http_code = curl_getinfo($eligibility_ch, CURLINFO_HTTP_CODE);
                    curl_close($eligibility_ch);
                    
                    error_log("Eligibility creation response code: " . $eligibility_http_code);
                    error_log("Eligibility creation response: " . $eligibility_response);
                }
            }
            
            // Success - redirect to list of donations with walk-in status
            if ($_SESSION['role_id'] === 1) {
                // Admin redirect
                header('Location: ../../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?status=pending');
            } else {
                // Staff redirect - go back to the blood collection submission dashboard
                error_log("Blood collection success - redirecting staff user back to blood collection dashboard");
                header('Location: ../../../public/Dashboards/dashboard-staff-blood-collection-submission.php?success=1');
            }
            exit;
        } else {
            // Log the error
            error_log('Supabase Error: ' . $response);
            error_log('Supabase HTTP Code: ' . $http_code);
            error_log('Supabase Data: ' . json_encode($data));
            throw new Exception("Failed to save blood collection data. Please try again.");
        }
    } catch (Exception $e) {
        // Log the error and set error message
        error_log("Error in blood collection form: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: blood-collection-form.php?error=1');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Blood Collection Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 20px;
        }
        /* Blood Collection Section */
        .blood-collection {
            background: #fff;
            padding: 2%;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: auto;
        }
        
        /* Error message styling */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            font-weight: bold;
        }

        .blood-collection h3 {
            font-size: 18px;
            font-weight: bold;
            color: #721c24;
            margin-bottom: 15px;
        }

        /* Blood Bag Used Table */
        .blood-bag-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .blood-bag-table th,
        .blood-bag-table td {
            border: 1px solid #ccc;
            padding: 12px;
            text-align: center;
            font-size: 14px;
            position: relative; 
        }

        .blood-bag-table th {
            background-color: #d9534f; 
            color: white;
            font-weight: bold;
        }

        .blood-bag-table td {
            background-color: #fff; 
            color: #333; 
            cursor: pointer;
        }

        /* Hids Radio Design */
        .blood-bag-table input[type="radio"] {
            opacity: 0; 
            position: absolute; 
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer; 
        }
       
        /* Border Color Change on Selection */
        .blood-bag-table td:has(input[type="radio"]:checked) {
            background-color: #0c5460;
            color: white; 
        }

        /* Hover Effect for Cells */
        .blood-bag-table td:hover {
            background-color: #f1f1f1; 
        }
        /* Amount Section */
        .amount-section {
            margin-bottom: 15px;
        }

        .amount-section label {
            font-weight: bold;
            color: #721c24;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .amount-section input[type="text"] {
            width: 100%;
            max-width: 200px;
            padding: 8px;
            border: 1px solid #bbb;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .amount-section input[type="text"]:focus {
            border-color: #721c24;
            outline: none;
        }

        /* Successful Section */
        .successful-section {
            margin-bottom: 15px;
        }

        .successful-section h4 {
            font-size: 16px;
            font-weight: bold;
            color: #721c24;
            margin-bottom: 10px;
        }

        .successful-options {
            display: flex;
            gap: 15px;
        }

        .successful-option {
            display: flex;
            align-items: center;
            font-weight: bold;
            color: #721c24;
            gap: 8px;
            background: #fff;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            cursor: pointer;
            position: relative;
        }

        /* Custom Radio Button Styling */
        .successful-option input {
            opacity: 0;
            position: absolute;
        }

        .checkmark {
            width: 18px;
            height: 18px;
            background-color: #fff;
            border: 2px solid #721c24;
            border-radius: 50%;
            display: inline-block;
            position: relative;
            transition: background-color 0.3s ease;
        }

        .successful-option input:checked ~ .checkmark {
            background-color: #721c24;
        }

        .checkmark::after {
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

        .successful-option input:checked ~ .checkmark::after {
            display: block;
        }

        .successful-option:hover .checkmark {
            border-color: #5a171c;
        }

        /* Reaction and Management Sections */
        .reaction-section,
        .management-section {
            margin-bottom: 25px;
            padding: 28px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #d9534f;
            width: 100%;
            max-width: 800px;
        }

        .reaction-section label,
        .management-section label {
            font-size: 16px;
            font-weight: bold;
            color: #721c24;
            display: block;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .reaction-section textarea,
        .management-section textarea {
            width: 100%;
            height: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s ease;
            resize: none;
            font-family: Arial, sans-serif;
        }

        .reaction-section textarea:focus,
        .management-section textarea:focus {
            border-color: #d9534f;
            box-shadow: 0 0 0 2px rgba(217, 83, 79, 0.1);
            outline: none;
        }

        /* Physician Section */
        .physician-section {
            margin-bottom: 20px;
        }

        .physician-section h4 {
            font-size: 16px;
            font-weight: bold;
            color: #721c24;
            margin-bottom: 10px;
        }

        .physician-section label {
            font-weight: bold;
            color: #721c24;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .physician-section input[type="text"] {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            border: 1px solid #bbb;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .physician-section input[type="text"]:focus {
            border-color: #721c24;
            outline: none;
        }

        /* Submit Button Section */
        .submit-section {
            text-align: right;
            margin-top: 20px;
        }

        .submit-button {
            background-color: #d9534f;
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
            background-color: #c9302c;
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
            border-top: 8px solid #d9534f;
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
            from { opacity: 0; transform: translate(-50%, -55%); }
            to { opacity: 1; transform: translate(-50%, -50%); }
        }

        @keyframes fadeOut {
            from { opacity: 1; transform: translate(-50%, -50%); }
            to { opacity: 0; transform: translate(-50%, -55%); }
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
            color: #d9534f;
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
            background: #aaa;
            color: white;
        }

        .cancel-action:hover {
            background: #888;
        }

        .confirm-action {
            background: #d9534f;
            color: white;
        }

        .confirm-action:hover {
            background: #c9302c;
        }
        /* Responsive Adjustments */
        @media (max-width: 600px) {
            .reaction-section input[type="text"],
            .management-section input[type="text"],
            .physician-section input[type="text"] {
                max-width: 100%;
            }

            .submit-button {
                width: 100%;
                padding: 12px;
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 600px) {
            .blood-bag-table th,
            .blood-bag-table td {
                padding: 8px;
                font-size: 14px;
            }

            .successful-options {
                flex-direction: column;
            }

            .amount-section input[type="text"],
            .reaction-section input[type="text"],
            .management-section input[type="text"],
            .physician-section input[type="text"] {
                max-width: 100%;
            }
        }
        .time-section {
            margin-bottom: 20px;
            display: flex;
            gap: 40px;
            align-items: center;
            justify-content: flex-start;
        }
        .time-input-group {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .time-input-group label {
            font-weight: bold;
            color: #721c24;
            margin-bottom: 8px;
        }
        .time-input-wrapper {
            position: relative;
            display: inline-block;
        }
        .time-input-wrapper input[type="text"] {
            padding: 8px 12px;
            padding-left: 35px; /* Space for the clock icon */
            border: 2px solid #d9534f;
            border-radius: 8px;
            font-size: 16px;
            font-family: monospace;
            color: #721c24;
            background-color: #fff;
            width: 120px;
            text-align: center;
            letter-spacing: 2px;
            cursor: pointer;
        }
        .time-input-wrapper::before {
            content: "⏱";
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #d9534f;
            z-index: 1;
            pointer-events: none;
        }
        .time-input-wrapper::after {
            content: "s";
            position: absolute;
            right: -20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #721c24;
            font-weight: bold;
        }
        .time-input-wrapper .ampm-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 100%;
            cursor: pointer;
            background: linear-gradient(to right, transparent, rgba(217, 83, 79, 0.1));
            border-radius: 0 6px 6px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        .time-input-wrapper .ampm-toggle:hover {
            background: rgba(217, 83, 79, 0.2);
        }
        .time-input-wrapper input[type="text"]:focus {
            outline: none;
            border-color: #721c24;
            box-shadow: 0 0 0 2px rgba(114, 28, 36, 0.2);
        }
        /* Add styles for the unit serial number field */
        .unit-serial-field {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border: 2px solid #d9534f;
            border-radius: 6px;
            color: #721c24;
            font-size: 16px;
            font-weight: bold;
            font-family: monospace;
            width: 200px;
            text-align: center;
            cursor: not-allowed;
        }
        
        .unit-serial-label {
            color: #721c24;
            font-weight: bold;
            margin-bottom: 8px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="blood-collection">
        <?php if (isset($_GET['error']) && isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 20px; border: 1px solid #f5c6cb; border-radius: 4px;">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="form-info" style="background-color: #e8f4ff; padding: 10px; margin-bottom: 20px; border: 1px solid #c5e2ff; border-radius: 4px;">
            <p style="margin: 0;"><strong>Note:</strong> The "Amount of Blood Taken" must be a whole number (integer). Decimal values are not supported.</p>
        </div>
        
        <form id="bloodCollectionForm" method="POST">
            <h3>VI. BLOOD COLLECTION (To be accomplished by the phlebotomist)</h3>
            
            <?php
            // Add hidden input for physical_exam_id if it was passed from the dashboard
            if (isset($_POST['physical_exam_id']) && !empty($_POST['physical_exam_id'])) {
                echo '<input type="hidden" name="physical_exam_id" value="' . htmlspecialchars($_POST['physical_exam_id']) . '">';
                error_log("Including physical_exam_id from POST in form: " . $_POST['physical_exam_id']);
            }
            ?>
            
            <div class="blood-bag-used">
                <h4>Blood Bag Used:</h4>
                <table class="blood-bag-table">
                    <thead>
                        <tr>
                            <th colspan="4">KARMI</th>
                            <th colspan="4">TERUMO</th>
                            <th colspan="2">SPECIAL BAG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <!-- KARMI options -->
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="S-KARMI">
                                    S
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="D-KARMI">
                                    D
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="T-KARMI">
                                    T
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="Q-KARMI">
                                    Q
                                </label>
                            </td>
                            
                            <!-- TERUMO options -->
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="S-TERUMO">
                                    S
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="D-TERUMO">
                                    D
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="T-TERUMO">
                                    T
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="Q-TERUMO">
                                    Q
                                </label>
                            </td>
                            
                            <!-- SPECIAL BAG options -->
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="FK T&B-SPECIAL BAG">
                                    FK T&B
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="TRM T&B-SPECIAL BAG">
                                    TRM T&B
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="amount-section">
                <label>Amount of Blood Taken: <input type="number" name="amount" placeholder="Enter amount" min="1" step="1" required></label>
            </div>

            <div class="successful-section">
                <h4>Successful:</h4>
                <div class="successful-options">
                    <label class="successful-option">
                        <input type="radio" name="successful" value="YES">
                        <span class="checkmark"></span>
                        YES
                    </label>
                    <label class="successful-option">
                        <input type="radio" name="successful" value="NO">
                        <span class="checkmark"></span>
                        NO
                    </label>
                </div>
            </div>

            <div class="reaction-section">
                <label>DONOR REACTION:</label>
                <textarea name="reaction" placeholder="Enter any donor reactions during blood collection"><?php echo isset($_POST['reaction']) ? htmlspecialchars($_POST['reaction']) : ''; ?></textarea>
            </div>
        
            <div class="management-section">
                <label>MANAGEMENT DONE:</label>
                <textarea name="management" placeholder="Enter management procedures performed"><?php echo isset($_POST['management']) ? htmlspecialchars($_POST['management']) : ''; ?></textarea>
            </div>
        
            <div class="physician-section">
                <label class="unit-serial-label">UNIT SERIAL NUMBER:</label>
                <input type="text" name="serial_number" class="unit-serial-field" value="<?php echo htmlspecialchars($generated_serial); ?>" readonly>
            </div>

            <div class="time-section">
                <div class="time-input-group">
                    <label>Start Time</label>
                    <div class="time-input-wrapper">
                        <input type="text" name="start_time" required placeholder="--:-- --" maxlength="8">
                        <div class="ampm-toggle" title="Click to toggle AM/PM">⇅</div>
                    </div>
                </div>
                <div class="time-input-group">
                    <label>End Time</label>
                    <div class="time-input-wrapper">
                        <input type="text" name="end_time" required placeholder="--:-- --" maxlength="8">
                        <div class="ampm-toggle" title="Click to toggle AM/PM">⇅</div>
                    </div>
                </div>
            </div>
        
            <div class="submit-section">
                <button type="submit" class="submit-button" id="triggerModalButton">Submit</button>
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
        // Add form submission validation
        document.getElementById('bloodCollectionForm').addEventListener('submit', function(e) {
            const bagType = document.querySelector('input[name="blood-bag"]:checked');
            
            if (!bagType) {
                e.preventDefault();
                alert('Please select a blood bag type');
                return false;
            }

            // Show confirmation modal
            e.preventDefault();
            document.getElementById('confirmationDialog').classList.add('show');
        });

        // Add click handler for the entire td
        document.querySelectorAll('.blood-bag-table td').forEach(td => {
            td.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    // Remove selected class from all cells
                    document.querySelectorAll('.blood-bag-table td').forEach(cell => {
                        cell.classList.remove('selected');
                    });
                    // Add selected class to clicked cell
                    this.classList.add('selected');
                }
            });
        });

        // Modal handling
        const confirmationDialog = document.getElementById('confirmationDialog');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const form = document.getElementById('bloodCollectionForm');

        // Yes Button (Submits Form)
        document.getElementById('confirmButton').addEventListener('click', function() {
            confirmationDialog.classList.remove('show');
            loadingSpinner.style.display = 'block';
            form.submit();
        });

        // No Button (Closes Modal)
        document.getElementById('cancelButton').addEventListener('click', function() {
            confirmationDialog.classList.remove('show');
        });

        function formatTimeInput(input) {
            const wrapper = input.closest('.time-input-wrapper');
            const ampmToggle = wrapper.querySelector('.ampm-toggle');

            // Set current time on click
            input.addEventListener('click', function(e) {
                // Don't set time if clicking on the AM/PM toggle area
                if (e.clientX > input.getBoundingClientRect().right - 30) {
                    return;
                }
                
                if (!this.value || this.value === '--:-- --') {
                    const now = new Date();
                    let hours = now.getHours();
                    const minutes = now.getMinutes();
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    const timeString = 
                        (hours < 10 ? '0' + hours : hours) + ':' +
                        (minutes < 10 ? '0' + minutes : minutes) + ' ' +
                        ampm;
                    this.value = timeString;
                }
            });

            // Handle manual input
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^0-9]/g, '');
                
                if (value.length > 4) {
                    value = value.substr(0, 4);
                }
                
                if (value.length >= 2) {
                    value = value.substr(0, 2) + ':' + value.substr(2);
                }
                
                if (value.length >= 5) {
                    let hours = parseInt(value.substr(0, 2));
                    if (hours > 12) {
                        hours = 12;
                        value = '12' + value.substr(2);
                    }
                    if (hours === 0) {
                        value = '12' + value.substr(2);
                    }
                    const currentAMPM = this.value.slice(-2);
                    if (currentAMPM === 'AM' || currentAMPM === 'PM') {
                        value += ' ' + currentAMPM;
                    } else {
                        value += ' AM';
                    }
                }
                
                e.target.value = value;
            });

            // Toggle AM/PM when clicking the toggle button
            ampmToggle.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                if (input.value && input.value !== '--:-- --') {
                    const timeWithoutAMPM = input.value.slice(0, -2);
                    const currentAMPM = input.value.slice(-2);
                    input.value = timeWithoutAMPM + (currentAMPM === 'AM' ? 'PM' : 'AM');
                }
            });
        }

        // Initialize time inputs
        document.querySelectorAll('.time-input-wrapper input[type="text"]').forEach(input => {
            formatTimeInput(input);
        });
    </script>
</body>
</html>