<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Debug session data
error_log("Session data in physical-examination-form.php: " . print_r($_SESSION, true));
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
    if (!isset($_SESSION['screening_id'])) {
        error_log("Missing screening_id in session for staff");
        header('Location: screening-form.php');
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
error_log("All session variables in physical-examination-form.php: " . print_r($_SESSION, true));
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
        color: #721c24;
        margin-bottom: 15px;
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
        background-color: #d9534f;
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
        border-color: #721c24;
        outline: none;
    }

    /* Remarks Section */
    .remarks-section {
        margin-bottom: 20px;
    }

    .remarks-section h4 {
        font-size: 16px;
        font-weight: bold;
        color: #721c24;
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
        color: #721c24;
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
        border: 2px solid #721c24;
        border-radius: 50%;
        display: inline-block;
        position: relative;
        transition: background-color 0.3s ease;
    }

    .remarks-option input:checked ~ .radio-mark {
        background-color: #721c24;
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
        color: #721c24;
    }

    .reason-input textarea:focus {
        border-color: #721c24;
        outline: none;
        box-shadow: 0 0 5px rgba(114, 28, 36, 0.2);
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
        border-color: #721c24;
        outline: none;
    }

    /* Invalid input highlighting */
    .physical-examination-table input:invalid {
        border-color: #dc3545;
        background-color: #fff8f8;
    }

    .physical-examination-table input:invalid:focus {
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }

    /* Form validation message styling */
    .physical-examination-table input:invalid + span::before {
        content: '⚠';
        color: #dc3545;
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
        color: #721c24;
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
        color: #721c24;
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
        border: 2px solid #721c24;
        border-radius: 4px;
        display: inline-block;
        position: relative;
        transition: background-color 0.3s ease;
    }

    .blood-bag-option input:checked ~ .checkmark {
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

    .blood-bag-option input:checked ~ .checkmark::after {
        display: block;
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
            <form id="physicalExamForm" method="POST" action="../../handlers/physical-examination-handler.php">
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
                            <td><input type="text" name="blood_pressure" placeholder="e.g., 120/80" pattern="[0-9]{2,3}/[0-9]{2,3}" title="Format: systolic/diastolic e.g. 120/80" required></td>
                            <td><input type="number" name="pulse_rate" placeholder="BPM" min="0" max="300" required></td>
                            <td><input type="number" name="body_temp" placeholder="°C" step="0.1" min="35" max="42" required></td>
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
                            <input type="radio" name="remarks" value="Accepted" required> 
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
                            <input type="radio" name="blood_bag_type" value="Single" required> 
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
                        <label class="blood-bag-option">
                            <input type="radio" name="blood_bag_type" value="Apheresis"> 
                            <span class="checkmark"></span>
                            Apheresis
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
            const requiredFields = [
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

            let isValid = true;
            requiredFields.forEach(field => {
                const element = document.querySelector(`[name="${field}"]`);
                if (!element.value) {
                    element.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    element.style.borderColor = '#bbb';
                }
            });

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
    </script>
</body>
</html>