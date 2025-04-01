<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../public/login.php");
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    header("Location: ../../../public/unauthorized.php");
    exit();
}



// Check if donor_id exists in session
if (!isset($_SESSION['donor_id'])) {
    error_log("Missing donor_id in session");
    header('Location: ../../../public/Dashboards/dashboard-Inventory-System.php');
    exit();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and validate unit serial number first
        $unit_serial_number = $_POST['serial_number'] ?? '';
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
        // Assuming the database field is numeric(5,2) or similar
        if ($amount_taken > 999.99) {
            throw new Exception("Amount is too large. Maximum allowed is 999.99");
        }
        
        // Format amount to ensure it has exactly 2 decimal places to match database expectations
        $amount_taken = number_format($amount_taken, 2, '.', '');

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
            'amount_taken' => $amount_taken,
            'is_successful' => $is_successful,
            'donor_reaction' => $donor_reaction,
            'management_done' => $management_done,
            'unit_serial_number' => $generated_serial,
            'start_time' => $start_timestamp,
            'end_time' => $end_timestamp,
            'status' => 'pending'
        ];

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
                'Prefer: return=minimal'
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
            // Success - redirect to list of donations with walk-in status
            if ($_SESSION['role_id'] === 1) {
                header('Location: ../../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?status=pending.php');
            } else {
                header('Location: ../../../public/Dashboards/dashboard-staff-blood-collection-submission.php');
            }
            exit;
        } else {
            // Log the error
            error_log("Error inserting blood collection data. HTTP Code: " . $http_code . " Response: " . $response);
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
        <form id="bloodCollectionForm" method="POST">
            <h3>VI. BLOOD COLLECTION (To be accomplished by the phlebotomist)</h3>
            <div class="blood-bag-used">
                <h4>Blood Bag Used:</h4>
                <table class="blood-bag-table">
                    <thead>
                        <tr>
                            <th colspan="4">KARMI</th>
                            <th colspan="4">TERUMO</th>
                            <th colspan="2">SPECIAL BAG</th>
                            <th colspan="4">APHERESIS</th>
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
                            
                            <!-- APHERESIS options -->
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="FRES-APHERESIS">
                                    FRES
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="AMI-APHERESIS">
                                    AMI
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="HAE-APHERESIS">
                                    HAE
                                </label>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="blood-bag" value="TRI-APHERESIS">
                                    TRI
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="amount-section">
                <label>Amount of Blood Taken: <input type="text" name="amount" placeholder="Enter amount"></label>
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