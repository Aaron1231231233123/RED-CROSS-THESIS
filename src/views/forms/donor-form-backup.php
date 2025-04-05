<?php
session_start();

// Initialize error variable
$errorMessage = '';

// Include database connection from assets
try {
    include_once '../../../assets/conn/db_conn.php';
    
    // Make the constants available as global variables for use in cURL requests
    $SUPABASE_URL = SUPABASE_URL;
    $SUPABASE_API_KEY = SUPABASE_API_KEY;
    
    // Check if Supabase connection variables are defined
    if (empty($SUPABASE_URL) || empty($SUPABASE_API_KEY)) {
        throw new Exception("Database connection parameters are not properly defined");
    }
} catch (Exception $e) {
    $errorMessage = "Database connection error: " . $e->getMessage();
    error_log("DB Connection Error: " . $e->getMessage());
}

// Initialize variables to store donor data
$donorData = null;
$editMode = false;

// Check if donor ID is provided in URL or session
if (isset($_GET['donor_id']) || isset($_SESSION['donor_id'])) {
    // We're in edit mode
    $editMode = true;
    $donor_id = isset($_GET['donor_id']) ? $_GET['donor_id'] : $_SESSION['donor_id'];
    
    // Logging attempt to fetch donor data
    error_log("Attempting to fetch donor data for ID: $donor_id");
    
    try {
        // Fetch donor data from Supabase using the correct donor_form table
        $ch = curl_init("$SUPABASE_URL/rest/v1/donor_form?donor_id=eq.$donor_id");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $SUPABASE_API_KEY,
            'Authorization: Bearer ' . $SUPABASE_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $donors = json_decode($response, true);
            if (!empty($donors)) {
                $donorData = $donors[0];
                error_log("Successfully fetched donor data: " . json_encode($donorData));
            } else {
                error_log("No donor found with ID: $donor_id");
            }
        } else {
            error_log("Error fetching donor data: HTTP $httpCode - $response");
        }
    } catch (Exception $e) {
        error_log("Exception fetching donor data: " . $e->getMessage());
    }
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect all form data
    $formData = [
        'surname' => $_POST['surname'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'middle_name' => $_POST['middle_name'] ?? '',
        'birthdate' => $_POST['birthdate'] ?? '',
        'age' => $_POST['age'] ?? '',
        'sex' => $_POST['sex'] ?? '',
        'civil_status' => $_POST['civil_status'] ?? '',
        'permanent_address' => $_POST['permanent_address'] ?? '',
        'office_address' => $_POST['office_address'] ?? '',
        'nationality' => $_POST['nationality'] ?? '',
        'religion' => $_POST['religion'] ?? '',
        'education' => $_POST['education'] ?? '',
        'occupation' => $_POST['occupation'] ?? '',
        'telephone' => $_POST['telephone'] ?? '',
        'mobile' => $_POST['mobile'] ?? '',
        'email' => $_POST['email'] ?? '',
        'id_school' => $_POST['id_school'] ?? '',
        'id_company' => $_POST['id_company'] ?? '',
        'id_prc' => $_POST['id_prc'] ?? '',
        'id_drivers' => $_POST['id_drivers'] ?? '',
        'id_sss_gsis_bir' => $_POST['id_sss_gsis_bir'] ?? '',
        'id_others' => $_POST['id_others'] ?? '',
    ];

    // Check if we're updating an existing donor
    if (isset($_POST['donor_id']) && !empty($_POST['donor_id'])) {
        // Update existing donor
        $donor_id = $_POST['donor_id'];
        $formData['donor_id'] = $donor_id;
        
        // Retain the existing PRC donor number and DOH barcode
        if (!empty($_POST['prc_donor_number'])) {
            $formData['prc_donor_number'] = $_POST['prc_donor_number'];
        }
        if (!empty($_POST['doh_nnbnets_barcode'])) {
            $formData['doh_nnbnets_barcode'] = $_POST['doh_nnbnets_barcode'];
        }
        
        // Log update attempt
        error_log("Updating donor with ID: $donor_id");
        
        try {
            // Update donor record in the database
            $ch = curl_init("$SUPABASE_URL/rest/v1/donor_form?donor_id=eq.$donor_id");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($formData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $SUPABASE_API_KEY,
                'Authorization: Bearer ' . $SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                error_log("Donor updated successfully");
                
                // Store updated donor data in session for next forms
                $_SESSION['donor_data'] = $formData;
                
                // Redirect back to the donation list - using relative path
                header('Location: ../../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?update_success=1');
                exit;
            } else {
                error_log("Error updating donor: " . $response);
                // Handle error (could display error message)
            }
        } catch (Exception $e) {
            error_log("Exception updating donor: " . $e->getMessage());
        }
    } else {
        // New donor submission
        // Generate unique donor number and barcode if not in edit mode
        if (empty($formData['prc_donor_number'])) {
            $formData['prc_donor_number'] = generateDonorNumber();
        }
        if (empty($formData['doh_nnbnets_barcode'])) {
            $formData['doh_nnbnets_barcode'] = generateNNBNetBarcode();
        }
        
        // Store donor data in session for next forms
        $_SESSION['donor_data'] = $formData;
        
        // Redirect to signature page
        header('Location: donor-declaration.php');
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donor Interview Sheet</title>
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
    <form class="donor_form_container" id="donorForm" action="donor-form.php<?php echo $editMode ? '?mode=edit' : ''; ?>" method="POST">
        <?php if ($editMode && $donorData): ?>
        <input type="hidden" name="donor_id" value="<?php echo htmlspecialchars($donorData['donor_id']); ?>">
        <?php endif; ?>
        <div class="donor_form_header">
            <div>
                <label class="donor_form_label">PRC BLOOD DONOR NUMBER:</label>
                <input type="text" class="donor_form_input" name="prc_donor_number" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['prc_donor_number'] ?? '') : ''; ?>" readonly> <!-- Auto-generated -->
            </div>
            <h2>BLOOD DONOR INTERVIEW SHEET</h2>
            <div>
                <label class="donor_form_label">DOH NNBNets Barcode:</label>
                <input type="text" class="donor_form_input" name="doh_nnbnets_barcode" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['doh_nnbnets_barcode'] ?? '') : ''; ?>" readonly> <!-- Auto-generated -->
            </div>
        </div>
        <div class="donor_form_section">
            <h3>I. PERSONAL DATA <i>(to be filled up by the donor):</i></h3>
            <h3>NAME:</h3>
            <div class="donor_form_grid grid-3">
                <div>
                    <label class="donor_form_label">Surname</label>
                    <input type="text" class="donor_form_input" name="surname" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['surname'] ?? '') : ''; ?>">
                </div>
                <div>
                    <label class="donor_form_label">First Name</label>
                    <input type="text" class="donor_form_input" name="first_name" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['first_name'] ?? '') : ''; ?>">
                </div>
                <div>
                    <label class="donor_form_label">Middle Name</label>
                    <input type="text" class="donor_form_input" name="middle_name" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['middle_name'] ?? '') : ''; ?>">
                </div>
            </div>
            <div class="donor_form_grid grid-4">
                <div>
                    <label class="donor_form_label">Birthdate</label>
                    <input type="date" class="donor_form_input" name="birthdate" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['birthdate'] ?? '') : ''; ?>">
                </div>
                <div>
                    <label class="donor_form_label">Age</label>
                    <input type="number" class="donor_form_input" name="age" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['age'] ?? '') : ''; ?>">
                </div>
                <div>
                    <label class="donor_form_label">Sex</label>
                    <select class="donor_form_input" name="sex">
                    <option value=""></option>
                        <option value="Male" <?php echo ($editMode && $donorData && ($donorData['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($editMode && $donorData && ($donorData['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($editMode && $donorData && ($donorData['sex'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="donor_form_label">Civil Status</label>
                    <select class="donor_form_input" name="civil_status">
                        <option value="Single" <?php echo ($editMode && $donorData && ($donorData['civil_status'] ?? '') === 'Single') ? 'selected' : ''; ?>>Single</option>
                        <option value="Married" <?php echo ($editMode && $donorData && ($donorData['civil_status'] ?? '') === 'Married') ? 'selected' : ''; ?>>Married</option>
                        <option value="Widowed" <?php echo ($editMode && $donorData && ($donorData['civil_status'] ?? '') === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                        <option value="Divorced" <?php echo ($editMode && $donorData && ($donorData['civil_status'] ?? '') === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="donor_form_section">
            <h3>PERMANENT ADDRESS</h3>
            <input type="text" class="donor_form_input" name="permanent_address" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['permanent_address'] ?? '') : ''; ?>">
            
            <h3>OFFICE ADDRESS</h3>
            <div class="donor_form_grid grid-1">
                <input type="text" class="donor_form_input" name="office_address" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['office_address'] ?? '') : ''; ?>">
            </div>
            <div class="donor_form_grid grid-4">
                <div>
                    <label class="donor_form_label">Nationality</label>
                    <input type="text" class="donor_form_input" name="nationality" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['nationality'] ?? '') : ''; ?>">
                </div>
                <div>
                    <label class="donor_form_label">Religion</label>
                    <input type="text" class="donor_form_input" name="religion" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['religion'] ?? '') : ''; ?>">
                </div>
                <div>
                    <label class="donor_form_label">Education</label>
                    <input type="text" class="donor_form_input" name="education" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['education'] ?? '') : ''; ?>">
                </div>
                <div>
                    <label class="donor_form_label">Occupation</label>
                    <input type="text" class="donor_form_input" name="occupation" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['occupation'] ?? '') : ''; ?>">
                </div>
            </div>
        </div>
        <div class="donor_form_section">
            <h3>CONTACT No.:</h3>
            <div class="donor_form_grid grid-3">
                <div>
                    <label class="donor_form_label">Telephone No.</label>
                    <input type="text" class="donor_form_input" name="telephone" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['telephone'] ?? '') : ''; ?>">
                </div>
                <div>
                    <label class="donor_form_label">Mobile No.</label>
                    <input type="text" class="donor_form_input" name="mobile" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['mobile'] ?? '') : ''; ?>">
                </div>
                <div>
                    <label class="donor_form_label">Email Address</label>
                    <input type="email" class="donor_form_input" name="email" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['email'] ?? '') : ''; ?>">
                </div>
            </div>
        </div>
        <div class="donor_form_section">
            <h3>IDENTIFICATION No.:</h3>
            <div class="donor_form_grid grid-6">
            <div>
                <label class="donor_form_label">School</label>
                <input type="text" class="donor_form_input" name="id_school" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['id_school'] ?? '') : ''; ?>">
            </div>
            <div>
                <label class="donor_form_label">Company</label>
                <input type="text" class="donor_form_input" name="id_company" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['id_company'] ?? '') : ''; ?>">
            </div>
            <div>
                <label class="donor_form_label">PRC</label>
                <input type="text" class="donor_form_input" name="id_prc" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['id_prc'] ?? '') : ''; ?>">
            </div>
            <div>
                <label class="donor_form_label">Driver's</label>
                <input type="text" class="donor_form_input" name="id_drivers" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['id_drivers'] ?? '') : ''; ?>">
            </div>
            <div>
                <label class="donor_form_label">SSS/GSIS/BIR</label>
                <input type="text" class="donor_form_input" name="id_sss_gsis_bir" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['id_sss_gsis_bir'] ?? '') : ''; ?>">
            </div>
            <div>
                <label class="donor_form_label">Others</label>
                <input type="text" class="donor_form_input" name="id_others" value="<?php echo $editMode && $donorData ? htmlspecialchars($donorData['id_others'] ?? '') : ''; ?>">
            </div>
        </div>
        </div>
        <div class="submit-section">
            <button class="submit-button" id="triggerModalButton"><?php echo $editMode ? 'Update' : 'Next'; ?></button>
        </div>
    </form>
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
document.addEventListener("DOMContentLoaded", function () {
    let confirmationDialog = document.getElementById("confirmationDialog");
    let loadingSpinner = document.getElementById("loadingSpinner");
    let triggerModalButton = document.getElementById("triggerModalButton");
    let cancelButton = document.getElementById("cancelButton");
    let confirmButton = document.getElementById("confirmButton");
    let donorForm = document.getElementById("donorForm");

    // Generate PRC Donor Number (format: PRC-YYYY-XXXXX where X is random number)
    const generatePrcDonorNumber = () => {
        const year = new Date().getFullYear();
        const randomNum = Math.floor(10000 + Math.random() * 90000); // 5-digit random number
        return `PRC-${year}-${randomNum}`;
    };

    // Generate DOH NNBNETS Barcode (Format: DOH-YYYYXXXX where X is random number)
    const generateDohBarcode = () => {
        const year = new Date().getFullYear();
        const randomNum = Math.floor(1000 + Math.random() * 9000); // 4-digit random number
        return `DOH-${year}${randomNum}`;
    };

    // Set initial values for PRC donor number and DOH barcode only for new donors
    const isEditMode = <?php echo $editMode ? 'true' : 'false'; ?>;
    if (!isEditMode) {
        const prcDonorNumberField = document.querySelector('input[name="prc_donor_number"]');
        const dohBarcodeField = document.querySelector('input[name="doh_nnbnets_barcode"]');
        
        // Only set if the fields are empty
        if (prcDonorNumberField && prcDonorNumberField.value === '') {
            prcDonorNumberField.value = generatePrcDonorNumber();
        }
        
        if (dohBarcodeField && dohBarcodeField.value === '') {
            dohBarcodeField.value = generateDohBarcode();
        }
    }

    // Open Modal Function
    function openModal() {
        confirmationDialog.classList.remove("hide");
        confirmationDialog.classList.add("show");
        confirmationDialog.style.display = "block";
        triggerModalButton.disabled = true; // Disable button while modal is open
    }

    // Close Modal Function
    function closeModal() {
        confirmationDialog.classList.remove("show");
        confirmationDialog.classList.add("hide");
        setTimeout(() => {
            confirmationDialog.style.display = "none";
            triggerModalButton.disabled = false; // Re-enable button
        }, 300);
    }

    // Show confirmation modal when form is about to be submitted
    donorForm.addEventListener("submit", function (event) {
        event.preventDefault(); // Stop immediate submission
        openModal(); // Show modal
    });

    // If "Yes" is clicked, show loader & submit form
    confirmButton.addEventListener("click", function () {
        closeModal();
        loadingSpinner.style.display = "block"; // Show loader
        setTimeout(() => {
            loadingSpinner.style.display = "none"; // Hide loader
            donorForm.submit(); // Now submit the form
        }, 2000);
    });

    // If "No" is clicked, just close the modal
    cancelButton.addEventListener("click", closeModal);
    // Add form validation before showing modal
    document.getElementById("triggerModalButton").addEventListener("click", function(event) {
            event.preventDefault();
            
            // Basic validation - check required fields
            let requiredFields = ['surname', 'first_name', 'birthdate', 'age', 'sex'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                let value = document.querySelector(`[name="${field}"]`).value.trim();
                if (!value) {
                    isValid = false;
                    document.querySelector(`[name="${field}"]`).style.borderColor = "#d9534f";
                } else {
                    document.querySelector(`[name="${field}"]`).style.borderColor = "";
                }
            });
            
            if (isValid) {
                openModal();
            } else {
                alert("Please fill in all required fields");
            }
        });
});

    </script>
</body>
</html>