<?php
session_start();
// Supabase Configuration
define("SUPABASE_URL", "https://nwakbxwglhxcpunrzstf.supabase.co");
define("SUPABASE_API_KEY", "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im53YWtieHdnbGh4Y3B1bnJ6c3RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDIyODA1NzIsImV4cCI6MjA1Nzg1NjU3Mn0.y4CIbDT2UQf2ieJTQukuJRRzspSZPehSgNKivBwpvc4");


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php");
    exit();
}

// Check for correct role (admin with role_id 1)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../../public/unauthorized.php");
    exit();
}


// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Store form data in session
    $_SESSION['donor_data'] = $_POST;
    // Redirect to the signature page
    header("Location: donor-declaration.php");
    exit();
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
    <form class="donor_form_container" id="donorForm" action="donor-form.php" method="POST">
        <div class="donor_form_header">
            <div>
                <label class="donor_form_label">PRC BLOOD DONOR NUMBER:</label>
                <input type="text" class="donor_form_input" name="prc_donor_number" readonly> <!-- This field is set by the database -->
            </div>
            <h2>BLOOD DONOR INTERVIEW SHEET</h2>
            <div>
                <label class="donor_form_label">DOH NNBNets Barcode:</label>
                <input type="text" class="donor_form_input" name="doh_nnbnets_barcode" readonly> <!-- This field is set by the database -->
            </div>
        </div>
        <div class="donor_form_section">
            <h3>I. PERSONAL DATA <i>(to be filled up by the donor):</i></h3>
            <h3>NAME:</h3>
            <div class="donor_form_grid grid-3">
                <div>
                    <label class="donor_form_label">Surname</label>
                    <input type="text" class="donor_form_input" name="surname">
                </div>
                <div>
                    <label class="donor_form_label">First Name</label>
                    <input type="text" class="donor_form_input" name="first_name">
                </div>
                <div>
                    <label class="donor_form_label">Middle Name</label>
                    <input type="text" class="donor_form_input" name="middle_name">
                </div>
            </div>
            <div class="donor_form_grid grid-4">
                <div>
                    <label class="donor_form_label">Birthdate</label>
                    <input type="date" class="donor_form_input" name="birthdate">
                </div>
                <div>
                    <label class="donor_form_label">Age</label>
                    <input type="number" class="donor_form_input" name="age">
                </div>
                <div>
                    <label class="donor_form_label">Sex</label>
                    <select class="donor_form_input" name="sex">
                    <option value=""></option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Female</option>
                    </select>
                </div>
                <div>
                    <label class="donor_form_label">Civil Status</label>
                    <select class="donor_form_input" name="civil_status">
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Divorced">Divorced</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="donor_form_section">
            <h3>PERMANENT ADDRESS</h3>
            <input type="text" class="donor_form_input" name="permanent_address">
            
            <h3>OFFICE ADDRESS</h3>
            <div class="donor_form_grid grid-1">
                <input type="text" class="donor_form_input" name="office_address">
            </div>
            <div class="donor_form_grid grid-4">
                <div>
                    <label class="donor_form_label">Nationality</label>
                    <input type="text" class="donor_form_input" name="nationality">
                </div>
                <div>
                    <label class="donor_form_label">Religion</label>
                    <input type="text" class="donor_form_input" name="religion">
                </div>
                <div>
                    <label class="donor_form_label">Education</label>
                    <input type="text" class="donor_form_input" name="education">
                </div>
                <div>
                    <label class="donor_form_label">Occupation</label>
                    <input type="text" class="donor_form_input" name="occupation">
                </div>
            </div>
        </div>
        <div class="donor_form_section">
            <h3>CONTACT No.:</h3>
            <div class="donor_form_grid grid-3">
                <div>
                    <label class="donor_form_label">Telephone No.</label>
                    <input type="text" class="donor_form_input" name="telephone">
                </div>
                <div>
                    <label class="donor_form_label">Mobile No.</label>
                    <input type="text" class="donor_form_input" name="mobile">
                </div>
                <div>
                    <label class="donor_form_label">Email Address</label>
                    <input type="email" class="donor_form_input" name="email">
                </div>
            </div>
        </div>
        <div class="donor_form_section">
            <h3>IDENTIFICATION No.:</h3>
            <div class="donor_form_grid grid-6">
            <div>
                <label class="donor_form_label">School</label>
                <input type="text" class="donor_form_input" name="id_school">
            </div>
            <div>
                <label class="donor_form_label">Company</label>
                <input type="text" class="donor_form_input" name="id_company">
            </div>
            <div>
                <label class="donor_form_label">PRC</label>
                <input type="text" class="donor_form_input" name="id_prc">
            </div>
            <div>
                <label class="donor_form_label">Driver's</label>
                <input type="text" class="donor_form_input" name="id_drivers">
            </div>
            <div>
                <label class="donor_form_label">SSS/GSIS/BIR</label>
                <input type="text" class="donor_form_input" name="id_sss_gsis_bir">
            </div>
            <div>
                <label class="donor_form_label">Others</label>
                <input type="text" class="donor_form_input" name="id_others">
            </div>
        </div>
        </div>
        <div class="submit-section">
            <button class="submit-button" id="triggerModalButton">Next</button>
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
