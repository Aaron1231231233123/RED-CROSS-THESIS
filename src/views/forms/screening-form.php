<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Debug session data
error_log("Session data in screening-form.php: " . print_r($_SESSION, true));
error_log("Role ID type: " . gettype($_SESSION['role_id']) . ", Value: " . $_SESSION['role_id']);

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
if ($_SESSION['role_id'] === 3) {
    if (!isset($_SESSION['donor_id'])) {
        error_log("Missing donor_id in session for staff");
        header('Location: ../../../public/Dashboards/dashboard-Inventory-System.php');
        exit();
    }
    if (!isset($_SESSION['medical_history_id'])) {
        error_log("Missing medical_history_id in session for staff");
        header('Location: medical-history.php');
        exit();
    }
} else {
    // For admin role (role_id 1), set donor_id to 46 if not set
    if (!isset($_SESSION['donor_id'])) {
        $_SESSION['donor_id'] = 46;
        error_log("Set donor_id to 46 for admin role");
    }
}

// Debug log to check all session variables
error_log("All session variables in screening-form.php: " . print_r($_SESSION, true));

// Get interviewer information from users table
$ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=surname,first_name,middle_name&user_id=eq.' . $_SESSION['user_id']);

$headers = array(
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY
);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$interviewer_data = json_decode($response, true);
curl_close($ch);

// Log the response for debugging
error_log("Supabase response code: " . $http_code);
error_log("Supabase response: " . $response);
error_log("Interviewer data: " . print_r($interviewer_data, true));

// Set default interviewer name
$interviewer_name = 'Unknown Interviewer';

// Check if we have valid data
if ($http_code === 200 && is_array($interviewer_data) && !empty($interviewer_data)) {
    $interviewer = $interviewer_data[0];
    if (isset($interviewer['surname']) && isset($interviewer['first_name'])) {
        $interviewer_name = $interviewer['surname'] . ', ' . 
                          $interviewer['first_name'] . ' ' . 
                          ($interviewer['middle_name'] ?? '');
        error_log("Set interviewer name to: " . $interviewer_name);
    } else {
        error_log("Missing required fields in interviewer data");
    }
} else {
    error_log("Failed to get interviewer data. HTTP Code: " . $http_code);
}

// Log session state for debugging
error_log("Session state in screening-form.php: " . print_r($_SESSION, true));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug log the raw POST data
        error_log("Raw POST data: " . print_r($_POST, true));

        // Prepare the data for insertion
        $screening_data = [
            'donor_form_id' => $_SESSION['donor_id'],
            'medical_history_id' => $_SESSION['medical_history_id'],
            'interviewer_id' => $_SESSION['user_id'],
            'body_weight' => floatval($_POST['body-wt']),
            'specific_gravity' => $_POST['sp-gr'] ?: "",
            'hemoglobin' => $_POST['hgb'] ?: "",
            'hematocrit' => $_POST['hct'] ?: "",
            'rbc_count' => $_POST['rbc'] ?: "",
            'wbc_count' => $_POST['wbc'] ?: "",
            'platelet_count' => intval($_POST['plt-count']),
            'blood_type' => $_POST['blood-type'],
            'donation_type' => $_POST['donation-type'],
            'has_previous_donation' => isset($_POST['history']) && $_POST['history'] === 'yes',
            'interview_date' => date('Y-m-d'),
            'red_cross_donations' => isset($_POST['history']) && $_POST['history'] === 'yes' ? intval($_POST['red-cross']) : 0,
            'hospital_donations' => isset($_POST['history']) && $_POST['history'] === 'yes' ? intval($_POST['hospital-history']) : 0,
            'last_rc_donation_place' => isset($_POST['history']) && $_POST['history'] === 'yes' ? ($_POST['last-rc-donation-place'] ?: "") : "",
            'last_hosp_donation_place' => isset($_POST['history']) && $_POST['history'] === 'yes' ? ($_POST['last-hosp-donation-place'] ?: "") : "",
            'last_rc_donation_date' => isset($_POST['history']) && $_POST['history'] === 'yes' && !empty($_POST['last-rc-donation-date']) ? $_POST['last-rc-donation-date'] : '0001-01-01',
            'last_hosp_donation_date' => isset($_POST['history']) && $_POST['history'] === 'yes' && !empty($_POST['last-hosp-donation-date']) ? $_POST['last-hosp-donation-date'] : '0001-01-01',
            'mobile_location' => $_POST['donation-type'] === 'mobile' ? ($_POST['mobile-place'] ?: "") : "",
            'mobile_organizer' => $_POST['donation-type'] === 'mobile' ? ($_POST['mobile-organizer'] ?: "") : "",
            'patient_name' => $_POST['donation-type'] === 'mobile' ? ($_POST['patient-name'] ?: "") : "",
            'hospital' => $_POST['donation-type'] === 'mobile' ? ($_POST['hospital'] ?: "") : "",
            'patient_blood_type' => $_POST['donation-type'] === 'mobile' ? ($_POST['blood-type-patient'] ?: "") : "",
            'component_type' => $_POST['donation-type'] === 'mobile' ? ($_POST['wb-component'] ?: "") : "",
            'units_needed' => $_POST['donation-type'] === 'mobile' && !empty($_POST['no-units']) ? intval($_POST['no-units']) : 0
        ];

        // Debug log the prepared data
        error_log("Prepared screening data: " . print_r($screening_data, true));

        // Initialize cURL session for Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form');

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
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($screening_data));

        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Debug log
        error_log("Supabase response code: " . $http_code);
        error_log("Supabase response: " . $response);
        
        curl_close($ch);

        if ($http_code === 201) {
            // Parse the response
            $response_data = json_decode($response, true);
            
            if (is_array($response_data) && isset($response_data[0]['screening_id'])) {
                $_SESSION['screening_id'] = $response_data[0]['screening_id'];
                
                // Different redirections based on role
                if ($_SESSION['role_id'] === 1) {
                    // Admin (role_id 1) - Direct to physical examination
                    error_log("Admin role: Redirecting to physical examination form");
                    header('Location: physical-examination-form.php');
                    exit();
                } else {
                    // Staff (role_id 3) - Return JSON response for AJAX handling
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'screening_id' => $response_data[0]['screening_id']
                    ]);
                    exit();
                }
            } else {
                throw new Exception("Invalid response format");
            }
        } else {
            throw new Exception("Failed to submit screening form. HTTP Code: " . $http_code . ", Response: " . $response);
        }
    } catch (Exception $e) {
        error_log("Error in screening form submission: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Screening Form</title>
    <style>
       /* General Styling */
body {
    font-family: Arial, sans-serif;
    background-color: #f9f9f9;
    margin: 0;
    padding: 20px;
}

/* Screening Form Container */
.screening-form {
    background: #fff;
    padding: 2%;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 900px;
    margin: auto;
}

/* Title Styling */
.screening-form-title {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 20px;
}

/* Tables Styling */
.screening-form-table, 
.screening-form-patient, 
.screening-form-history-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.screening-form-table th,
.screening-form-table td,
.screening-form-patient th,
.screening-form-patient td,
.screening-form-history-table th,
.screening-form-history-table td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: center;
}

.screening-form-table th,
.screening-form-patient th,
.screening-form-history-table th {
    background-color: #d9534f;
    color: white;
    font-weight: bold;
}

/* Input Fields inside Tables */
.screening-form-table input,
.screening-form-patient input,
.screening-form-history-table input {
    width: 95%;
    padding: 5px 2px 5px 2px;
    border: 1px solid #bbb;
    border-radius: 4px;
}

/* Donation Section Styling */
.screening-form-donation {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    border: 1px solid #ddd;
}

/* Donation Title Styling */
.screening-form-donation p {
    font-weight: bold;
    color: #721c24;
    margin-bottom: 10px;
    font-size: 18px;
}

/* Donation Type Options */
.donation-options {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.donation-option {
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
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.donation-option:hover {
    background-color: #f0f0f0;
    transform: translateY(-2px);
}

/* Custom Checkbox Styling */
.donation-option input {
    opacity: 0;
    position: absolute;
}

.checkmark {
    width: 18px;
    height: 18px;
    background-color: #fff;
    border: 2px solid #721c24;
    border-radius: 4px;
    display: inline-block;
    position: relative;
    transition: background-color 0.3s ease;
}

.donation-option input:checked ~ .checkmark {
    background-color: #721c24;
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

.donation-option input:checked ~ .checkmark::after {
    display: block;
}

/* Mobile Donation Section */
.mobile-donation-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: #fff;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.mobile-donation-label {
    display: flex;
    align-items: center;
    font-weight: bold;
    color: #721c24;
    gap: 8px;
    cursor: pointer;
}

.mobile-donation-fields {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 10px;
}

.mobile-donation-fields label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-weight: bold;
    color: #721c24;
}

.mobile-donation-fields input[type="text"] {
    width: 100%;
    max-width: 400px;
    padding: 8px;
    border: 1px solid #bbb;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.mobile-donation-fields input[type="text"]:focus {
    border-color: #721c24;
    outline: none;
}

/* Placeholder Styling */
.mobile-donation-fields input::placeholder {
    color: #999;
    font-style: italic;
}

/* History Section */
.screening-form-history {
    background: #d1ecf1;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.screening-form-history p {
    font-weight: bold;
    color: #0c5460;
}

/* Footer Styling */
.screening-form-footer {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin-top: 20px;
}

.screening-form-footer input {
    border: none;
    border-bottom: 1px solid #000;
    padding: 3px;
    width: 50%;
    text-align: center;
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
            color: #d50000;
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
            background: #c9302c;
            color: white;
        }

        .confirm-action:hover {
            background: #691b19;
        }
/* Responsive Adjustments */
@media (max-width: 600px) {
    .donation-options {
        flex-direction: column;
    }

    .mobile-donation-fields input[type="text"] {
        max-width: 100%;
    }

    .screening-form-footer input {
        width: 80%;
    }

    .screening-form-table th,
    .screening-form-table td,
    .screening-form-patient th,
    .screening-form-patient td,
    .screening-form-history-table th,
    .screening-form-history-table td {
        padding: 6px;
        font-size: 14px;
    }

    .screening-form-donation p {
        font-size: 16px;
    }

    .screening-form-title {
        font-size: 20px;
    }
}

.disapprove-button {
    background-color: #dc3545;
    color: white;
    font-weight: bold;
    padding: 12px 22px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    font-size: 15px;
    margin-left: 10px;
}

.disapprove-button:hover {
    background-color: #c82333;
    transform: translateY(-2px);
}

.disapprove-action {
    background: #dc3545;
    color: white;
}

.disapprove-action:hover {
    background: #c82333;
}

.modal-body {
    margin: 15px 0;
}

.modal-body textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    resize: vertical;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
}

.history-table th, .history-table td {
    padding: 10px;
    border: 1px solid #ddd;
}

.history-table th {
    background-color: #d9534f;
    color: white;
    text-align: left;
}

.history-table td input {
    width: 100%;
    padding: 5px;
    border: 1px solid #ccc;
}

.history-table tr:first-child th {
    text-align: center;
}

select {
    width: 95%;
    padding: 5px 2px 5px 2px;
    border: 1px solid #bbb;
    border-radius: 4px;
    background-color: white;
}

select:focus {
    outline: none;
    border-color: #721c24;
}

    </style>
</head>
<body>
    <form method="POST" action="" id="screeningForm">
        <div class="screening-form">
            <h2 class="screening-form-title">IV. INITIAL SCREENING <span>(To be filled up by the interviewer)</span></h2>
            
            <table class="screening-form-table">
                <tr>
                    <th>BODY WT</th>
                    <th>SP. GR</th>
                    <th>HGB</th>
                    <th>HCT</th>
                    <th>RBC</th>
                    <th>WBC</th>
                    <th>PLT Count</th>
                    <th>BLOOD TYPE</th>
                </tr>
                <tr>
                    <td><input type="number" step="0.01" name="body-wt" value="<?php echo isset($_POST['body-wt']) ? htmlspecialchars($_POST['body-wt']) : ''; ?>" required></td>
                    <td><input type="text" name="sp-gr" value="<?php echo isset($_POST['sp-gr']) ? htmlspecialchars($_POST['sp-gr']) : ''; ?>" required></td>
                    <td><input type="text" name="hgb" value="<?php echo isset($_POST['hgb']) ? htmlspecialchars($_POST['hgb']) : ''; ?>" required></td>
                    <td><input type="text" name="hct" value="<?php echo isset($_POST['hct']) ? htmlspecialchars($_POST['hct']) : ''; ?>" required></td>
                    <td><input type="text" name="rbc" value="<?php echo isset($_POST['rbc']) ? htmlspecialchars($_POST['rbc']) : ''; ?>" required></td>
                    <td><input type="text" name="wbc" value="<?php echo isset($_POST['wbc']) ? htmlspecialchars($_POST['wbc']) : ''; ?>" required></td>
                    <td><input type="number" name="plt-count" value="<?php echo isset($_POST['plt-count']) ? htmlspecialchars($_POST['plt-count']) : ''; ?>" required></td>
                    <td>
                        <select name="blood-type" required>
                            <option value="" disabled <?php echo !isset($_POST['blood-type']) ? 'selected' : ''; ?>>Select Blood Type</option>
                            <?php
                            $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                            foreach ($bloodTypes as $type) {
                                $selected = (isset($_POST['blood-type']) && $_POST['blood-type'] === $type) ? 'selected' : '';
                                echo "<option value=\"$type\" $selected>$type</option>";
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>

            <div class="screening-form-donation">
                <p>TYPE OF DONATION (Donor's Choice):</p>
                <div class="donation-options">
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="in-house" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'in-house') ? 'checked' : ''; ?> required> 
                        <span class="checkmark"></span>
                        IN-HOUSE
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="walk-in" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'walk-in') ? 'checked' : (isset($_SESSION['role_id']) && $_SESSION['role_id'] === 1 ? 'checked' : ''); ?> required> 
                        <span class="checkmark"></span>
                        WALK-IN/VOLUNTARY
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="replacement" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'replacement') ? 'checked' : ''; ?> required> 
                        <span class="checkmark"></span>
                        REPLACEMENT
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="patient-directed" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'patient-directed') ? 'checked' : ''; ?> required> 
                        <span class="checkmark"></span>
                        PATIENT-DIRECTED
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="mobile" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'mobile') ? 'checked' : ''; ?> required> 
                        <span class="checkmark"></span>
                        Mobile Blood Donation
                    </label>
                </div>
                
                <div class="mobile-donation-section" id="mobileDonationSection" style="display: none;">
                    <div class="mobile-donation-fields">
                        <label>
                            PLACE: 
                            <input type="text" name="mobile-place" value="<?php echo isset($_POST['mobile-place']) ? htmlspecialchars($_POST['mobile-place']) : ''; ?>">
                        </label>
                        <label>
                            ORGANIZER: 
                            <input type="text" name="mobile-organizer" value="<?php echo isset($_POST['mobile-organizer']) ? htmlspecialchars($_POST['mobile-organizer']) : ''; ?>">
                        </label>
                    </div>
                </div>
            </div>
            

            <table class="screening-form-patient" id="patientDetailsTable" style="display: none;">
                <tr>
                    <th>Patient Name</th>
                    <th>Hospital</th>
                    <th>Blood Type</th>
                    <th>WB/Component</th>
                    <th>No. of units</th>
                </tr>
                <tr>
                    <td><input type="text" name="patient-name" value="<?php echo isset($_POST['patient-name']) ? htmlspecialchars($_POST['patient-name']) : ''; ?>"></td>
                    <td><input type="text" name="hospital" value="<?php echo isset($_POST['hospital']) ? htmlspecialchars($_POST['hospital']) : ''; ?>"></td>
                    <td>
                        <select name="blood-type-patient">
                            <option value="" disabled <?php echo !isset($_POST['blood-type-patient']) ? 'selected' : ''; ?>>Select Blood Type</option>
                            <?php
                            foreach ($bloodTypes as $type) {
                                $selected = (isset($_POST['blood-type-patient']) && $_POST['blood-type-patient'] === $type) ? 'selected' : '';
                                echo "<option value=\"$type\" $selected>$type</option>";
                            }
                            ?>
                        </select>
                    </td>
                    <td><input type="text" name="wb-component" value="<?php echo isset($_POST['wb-component']) ? htmlspecialchars($_POST['wb-component']) : ''; ?>"></td>
                    <td><input type="number" name="no-units" value="<?php echo isset($_POST['no-units']) ? htmlspecialchars($_POST['no-units']) : ''; ?>"></td>
                </tr>
            </table>

            <div class="screening-form-history">
                <p>History of previous donation? (Donor's Opinion)</p>
                <label><input type="radio" name="history" value="yes" required> YES</label>
                <label><input type="radio" name="history" value="no" required> NO</label>
            </div>

            <table class="screening-form-history-table">
                <tr>
                    <th></th>
                    <th>Red Cross</th>
                    <th>Hospital</th>
                </tr>
                <tr>
                    <th>No. of times</th>
                    <td><input type="number" name="red-cross" min="0" value="<?php echo isset($_POST['red-cross']) ? htmlspecialchars($_POST['red-cross']) : '0'; ?>"></td>
                    <td><input type="number" name="hospital-history" min="0" value="<?php echo isset($_POST['hospital-history']) ? htmlspecialchars($_POST['hospital-history']) : '0'; ?>"></td>
                </tr>
                <tr>
                    <th>Date of last donation</th>
                    <td><input type="date" name="last-rc-donation-date" value="<?php echo isset($_POST['last-rc-donation-date']) ? htmlspecialchars($_POST['last-rc-donation-date']) : ''; ?>"></td>
                    <td><input type="date" name="last-hosp-donation-date" value="<?php echo isset($_POST['last-hosp-donation-date']) ? htmlspecialchars($_POST['last-hosp-donation-date']) : ''; ?>"></td>
                </tr>
                <tr>
                    <th>Place of last donation</th>
                    <td><input type="text" name="last-rc-donation-place" value="<?php echo isset($_POST['last-rc-donation-place']) ? htmlspecialchars($_POST['last-rc-donation-place']) : ''; ?>"></td>
                    <td><input type="text" name="last-hosp-donation-place" value="<?php echo isset($_POST['last-hosp-donation-place']) ? htmlspecialchars($_POST['last-hosp-donation-place']) : ''; ?>"></td>
                </tr>
            </table>

            <div class="screening-form-footer">
                <label>INTERVIEWER (print name & sign): <input type="text" name="interviewer" value="<?php echo htmlspecialchars($interviewer_name); ?>" readonly></label>
                <label>PRC Office</label>
                <p>Date: <?php echo date('m/d/Y'); ?></p>
            </div>
            <div class="submit-section">
                <button type="button" class="submit-button" id="triggerModalButton">Submit</button>
            </div>
        </div>
    </form>
    <!-- Existing Confirmation Modal -->
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
        document.addEventListener('DOMContentLoaded', function() {
            let confirmationDialog = document.getElementById("confirmationDialog");
            let loadingSpinner = document.getElementById("loadingSpinner");
            let triggerModalButton = document.getElementById("triggerModalButton");
            let cancelButton = document.getElementById("cancelButton");
            let confirmButton = document.getElementById("confirmButton");
            let form = document.getElementById("screeningForm");

            // Open Submit Modal
            triggerModalButton.addEventListener("click", function() {
                if (!form.checkValidity()) {
                    alert("Please fill in all required fields before proceeding.");
                    return;
                }

                confirmationDialog.classList.remove("hide");
                confirmationDialog.classList.add("show");
                confirmationDialog.style.display = "block";
                triggerModalButton.disabled = true;
            });

            // Close Submit Modal
            function closeModal() {
                confirmationDialog.classList.remove("show");
                confirmationDialog.classList.add("hide");
                setTimeout(() => {
                    confirmationDialog.style.display = "none";
                    triggerModalButton.disabled = false;
                }, 300);
            }

            // Handle Submit Confirmation
            confirmButton.addEventListener("click", function() {
                // Validate numeric fields
                const numericFields = {
                    'body-wt': 'Body Weight',
                    'plt-count': 'Platelet Count',
                    'no-units': 'Number of Units'
                };

                for (const [fieldName, label] of Object.entries(numericFields)) {
                    const field = document.querySelector(`input[name="${fieldName}"]`);
                    if (field && field.value && isNaN(field.value)) {
                        alert(`${label} must be a valid number`);
                        return;
                    }
                }

                closeModal();
                loadingSpinner.style.display = "block";
                
                // Get all form data
                const formData = new FormData(form);
                
                // Submit the form
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    // Check if the response is JSON
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        // For admin role, response will be a redirect
                        window.location.href = 'physical-examination-form.php';
                        return null;
                    }
                })
                .then(data => {
                    loadingSpinner.style.display = "none";
                    if (data === null) {
                        // Admin redirect already handled
                        return;
                    }
                    if (data.success) {
                        window.location.href = "../../../public/Dashboards/dashboard-staff-donor-submission.php";
                    } else {
                        throw new Error(data.error || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    loadingSpinner.style.display = "none";
                    alert(error.message || "Error submitting form. Please try again.");
                });
            });

            // Cancel Submit
            cancelButton.addEventListener("click", closeModal);

            // Add radio button change handler for mobile donation section and patient details
            const donationTypeRadios = document.querySelectorAll('input[name="donation-type"]');
            const mobileDonationSection = document.getElementById('mobileDonationSection');
            const patientDetailsTable = document.getElementById('patientDetailsTable');

            donationTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'mobile') {
                        // Show mobile donation section with only Place and Organizer
                        mobileDonationSection.style.display = 'block';
                        // Hide patient details table
                        patientDetailsTable.style.display = 'none';
                    } else if (this.value === 'patient-directed') {
                        // Show patient details table
                        patientDetailsTable.style.display = 'table';
                        // Hide mobile donation section
                        mobileDonationSection.style.display = 'none';
                    } else {
                        // Hide both sections for other options
                        mobileDonationSection.style.display = 'none';
                        patientDetailsTable.style.display = 'none';
                    }
                });
            });

            // Check initial state for mobile donation
            const selectedDonationType = document.querySelector('input[name="donation-type"]:checked');
            if (selectedDonationType) {
                if (selectedDonationType.value === 'mobile') {
                    // Show mobile donation section
                    mobileDonationSection.style.display = 'block';
                    // Hide patient details table
                    patientDetailsTable.style.display = 'none';
                } else if (selectedDonationType.value === 'patient-directed') {
                    // Show patient details table
                    patientDetailsTable.style.display = 'table';
                    // Hide mobile donation section
                    mobileDonationSection.style.display = 'none';
                } else {
                    // Hide both sections for other options
                    mobileDonationSection.style.display = 'none';
                    patientDetailsTable.style.display = 'none';
                }
            }
            

            // Add handler for donation history radio buttons
            const historyRadios = document.querySelectorAll('input[name="history"]');
            const historyTable = document.querySelector('.screening-form-history-table');

            historyRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    const historyInputs = historyTable.querySelectorAll('input');
                    
                    if (this.value === 'yes') {
                        historyInputs.forEach(input => {
                            input.removeAttribute('disabled');
                            if (input.type === 'number' && !input.value) {
                                input.value = '0';
                            }
                        });
                    } else {
                        historyInputs.forEach(input => {
                            input.setAttribute('disabled', 'disabled');
                            if (input.type === 'number') {
                                input.value = '0';
                            } else {
                                input.value = '';
                            }
                        });
                    }
                });
            });

            // Check initial state of history radio
            const selectedHistory = document.querySelector('input[name="history"]:checked');
            if (selectedHistory) {
                selectedHistory.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>