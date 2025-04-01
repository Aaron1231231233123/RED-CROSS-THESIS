<?php
session_start();
// Supabase Configuration
define("SUPABASE_URL", "https://nwakbxwglhxcpunrzstf.supabase.co");
define("SUPABASE_API_KEY", "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im53YWtieHdnbGh4Y3B1bnJ6c3RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDIyODA1NzIsImV4cCI6MjA1Nzg1NjU3Mn0.y4CIbDT2UQf2ieJTQukuJRRzspSZPehSgNKivBwpvc4");


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../public/login.php");
    exit();
}

// Check for correct role (admin with role_id 1)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// Initialize $donor_data array
$donor_data = array();
$isEditMode = false;

// Check if we're in edit mode and have a donor ID
if (isset($_GET['mode']) && $_GET['mode'] === 'edit' && (isset($_GET['donor_id']) || isset($_SESSION['donor_id']))) {
    $isEditMode = true;
    $donor_id = $_GET['donor_id'] ?? $_SESSION['donor_id'];
    
    // Log the donor_id for debugging
    error_log("Loading donor form in edit mode for donor_id: $donor_id");
    
    // Fetch donor details from the Supabase API
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq.$donor_id&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Accept: application/json"
        ],
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code == 200) {
        $donor_records = json_decode($response, true);
        
        if (!empty($donor_records)) {
            $donor_data = $donor_records[0];
            error_log("Donor data fetched successfully: " . print_r($donor_data, true));
        } else {
            error_log("No donor records found for ID: $donor_id");
        }
    } else {
        error_log("Failed to fetch donor data: HTTP Code $http_code, Response: $response");
    }
    
    curl_close($ch);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Store form data in session
    $_SESSION['donor_data'] = $_POST;
    // Redirect to the signature page
    header("Location: donor-declaration.php");
    exit();
}

// Function to safely get value from donor data
function getDonorValue($key, $default = '') {
    global $donor_data;
    return isset($donor_data[$key]) && !empty($donor_data[$key]) ? htmlspecialchars($donor_data[$key]) : $default;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEditMode ? 'Edit Donor Information' : 'Blood Donor Interview Sheet'; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #000;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .donor_form_header {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            align-items: center;
            text-align: center;
            margin-bottom: 10px;
            color: #b22222;
        }

        .donor_form_container {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 1400px;
            width: 100%;
            font-size: 14px;
        }

        .donor_form_label {
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }

        .donor_form_input {
            width: 100%;
            padding: 6px;
            margin-bottom: 10px;
            border: 1px solid #000;
            border-radius: 3px;
            font-size: 14px;
            box-sizing: border-box;
            color: #757272;
        }

        .donor_form_grid {
            display: grid;
            gap: 5px;
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .grid-1 {
            grid-template-columns: 1fr;
        }
        .grid-6 {
    grid-template-columns: repeat(6, 1fr); 
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
    </style>
</head>
<body>
    <form method="post" action="">
        <div class="donor_form_container">
            <div class="donor_form_header">
                <img src="../../../public/assets/images/PRC-logo.png" alt="PRC Logo" width="100">
                <h2><strong>PHILIPPINE RED CROSS</strong><br>Blood Donor Interview Sheet</h2>
                <div></div>
            </div>
            <!-- Form sections with prefilled values -->
            <h3>I. PERSONAL INFORMATION</h3>
            <div class="donor_form_grid grid-4">
                <div>
                    <label class="donor_form_label">Surname</label>
                    <input type="text" class="donor_form_input" name="surname" required value="<?php echo getDonorValue('surname'); ?>">
                </div>
                <div>
                    <label class="donor_form_label">First Name</label>
                    <input type="text" class="donor_form_input" name="first_name" required value="<?php echo getDonorValue('first_name'); ?>">
                </div>
                <div>
                    <label class="donor_form_label">Middle Name</label>
                    <input type="text" class="donor_form_input" name="middle_name" value="<?php echo getDonorValue('middle_name'); ?>">
                </div>
                <div>
                    <label class="donor_form_label">Suffix</label>
                    <input type="text" class="donor_form_input" name="suffix" placeholder="Jr., Sr., III, etc." value="<?php echo getDonorValue('suffix'); ?>">
                </div>
            </div>

            <div class="donor_form_grid grid-3">
                <div>
                    <label class="donor_form_label">Date of Birth</label>
                    <input type="date" class="donor_form_input" name="birthdate" required value="<?php echo getDonorValue('birthdate'); ?>">
                </div>
                <div>
                    <label class="donor_form_label">Sex</label>
                    <select class="donor_form_input" name="sex" required>
                        <option value="Male" <?php echo getDonorValue('sex') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo getDonorValue('sex') === 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div>
                    <label class="donor_form_label">Civil Status</label>
                    <select class="donor_form_input" name="civil_status" required>
                        <option value="Single" <?php echo getDonorValue('civil_status') === 'Single' ? 'selected' : ''; ?>>Single</option>
                        <option value="Married" <?php echo getDonorValue('civil_status') === 'Married' ? 'selected' : ''; ?>>Married</option>
                        <option value="Widowed" <?php echo getDonorValue('civil_status') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                        <option value="Divorced" <?php echo getDonorValue('civil_status') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                        <option value="Separated" <?php echo getDonorValue('civil_status') === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                    </select>
                </div>
            </div>

            <div class="donor_form_grid grid-3">
                <div>
                    <label class="donor_form_label">Citizenship</label>
                    <input type="text" class="donor_form_input" name="citizenship" required value="<?php echo getDonorValue('citizenship', 'Filipino'); ?>">
                </div>
                <div>
                    <label class="donor_form_label">Religion</label>
                    <input type="text" class="donor_form_input" name="religion" value="<?php echo getDonorValue('religion'); ?>">
                </div>
                <div>
                    <label class="donor_form_label">Occupation</label>
                    <input type="text" class="donor_form_input" name="occupation" value="<?php echo getDonorValue('occupation'); ?>">
                </div>
            </div>

            <div class="donor_form_grid grid-1">
                <div>
                    <label class="donor_form_label">Permanent Address</label>
                    <input type="text" class="donor_form_input" name="permanent_address" required value="<?php echo getDonorValue('permanent_address'); ?>">
                </div>
            </div>

            <div class="donor_form_grid grid-1">
                <div>
                    <label class="donor_form_label">Office Address</label>
                    <input type="text" class="donor_form_input" name="office_address" value="<?php echo getDonorValue('office_address'); ?>">
                </div>
            </div>

            <div class="donor_form_grid grid-3">
                <div>
                    <label class="donor_form_label">Blood Type (if known)</label>
                    <select class="donor_form_input" name="blood_type">
                        <option value="" <?php echo getDonorValue('blood_type') === '' ? 'selected' : ''; ?>>Unknown</option>
                        <option value="A+" <?php echo getDonorValue('blood_type') === 'A+' ? 'selected' : ''; ?>>A+</option>
                        <option value="A-" <?php echo getDonorValue('blood_type') === 'A-' ? 'selected' : ''; ?>>A-</option>
                        <option value="B+" <?php echo getDonorValue('blood_type') === 'B+' ? 'selected' : ''; ?>>B+</option>
                        <option value="B-" <?php echo getDonorValue('blood_type') === 'B-' ? 'selected' : ''; ?>>B-</option>
                        <option value="AB+" <?php echo getDonorValue('blood_type') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                        <option value="AB-" <?php echo getDonorValue('blood_type') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                        <option value="O+" <?php echo getDonorValue('blood_type') === 'O+' ? 'selected' : ''; ?>>O+</option>
                        <option value="O-" <?php echo getDonorValue('blood_type') === 'O-' ? 'selected' : ''; ?>>O-</option>
                    </select>
                </div>
                <div>
                    <label class="donor_form_label">Mobile Number</label>
                    <input type="tel" class="donor_form_input" name="mobile_number" required value="<?php echo getDonorValue('mobile_number'); ?>">
                </div>
                <div>
                    <label class="donor_form_label">Email Address</label>
                    <input type="email" class="donor_form_input" name="email" value="<?php echo getDonorValue('email'); ?>">
                </div>
            </div>

            <div class="donor_form_grid grid-3">
                <div>
                    <label class="donor_form_label">Person to Contact in Case of Emergency</label>
                    <input type="text" class="donor_form_input" name="emergency_contact_name" required value="<?php echo getDonorValue('emergency_contact_name'); ?>">
                </div>
                <div>
                    <label class="donor_form_label">Relationship</label>
                    <input type="text" class="donor_form_input" name="emergency_contact_relationship" required value="<?php echo getDonorValue('emergency_contact_relationship'); ?>">
                </div>
                <div>
                    <label class="donor_form_label">Mobile Number</label>
                    <input type="tel" class="donor_form_input" name="emergency_contact_mobile" required value="<?php echo getDonorValue('emergency_contact_mobile'); ?>">
                </div>
            </div>

            <!-- More form sections continue as in the original... -->

            <div class="submit-section">
                <button type="submit" class="submit-button">
                    <?php echo $isEditMode ? 'Update Donor Information' : 'Submit Donor Information'; ?>
                </button>
            </div>
        </div>
    </form>

    <!-- Loading spinner -->
    <div class="loading-spinner" id="loadingSpinner"></div>

    <!-- Confirmation modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="modal-header">
            Confirm Submission
        </div>
        <div class="modal-content">
            Are you sure you want to submit this donor information?
        </div>
        <div class="modal-actions">
            <button class="modal-button" style="background-color: #f44336; color: white;" id="confirmSubmit">Yes</button>
            <button class="modal-button" style="background-color: #e7e7e7; color: black;" id="cancelSubmit">No</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const confirmationModal = document.getElementById('confirmationModal');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const confirmSubmitBtn = document.getElementById('confirmSubmit');
            const cancelSubmitBtn = document.getElementById('cancelSubmit');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                confirmationModal.classList.add('show');
            });

            confirmSubmitBtn.addEventListener('click', function() {
                confirmationModal.classList.remove('show');
                confirmationModal.classList.add('hide');
                loadingSpinner.style.display = 'block';
                
                setTimeout(() => {
                    form.submit();
                }, 1000);
            });

            cancelSubmitBtn.addEventListener('click', function() {
                confirmationModal.classList.remove('show');
                confirmationModal.classList.add('hide');
                
                setTimeout(() => {
                    confirmationModal.classList.remove('hide');
                    confirmationModal.style.display = 'none';
                }, 300);
            });
        });
    </script>
</body>
</html>
