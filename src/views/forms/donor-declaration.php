<?php
session_start();
define("SUPABASE_URL", "https://nwakbxwglhxcpunrzstf.supabase.co");
define("SUPABASE_API_KEY", "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im53YWtieHdnbGh4Y3B1bnJ6c3RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDIyODA1NzIsImV4cCI6MjA1Nzg1NjU3Mn0.y4CIbDT2UQf2ieJTQukuJRRzspSZPehSgNKivBwpvc4");

// Check if form is submitted from first page
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_SESSION['donor_data'])) {
    $_SESSION['donor_data'] = $_POST;
    header("Location: donor-declaration.php");
    exit();
}

// Handle form submission to Supabase
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['donor_data'])) {
    $donorData = array_merge($_SESSION['donor_data'], $_POST);
    
    // Handle file uploads
    $signatureData = [];
    if (!empty($_FILES['donor_signature']['tmp_name'])) {
        $donorSignature = file_get_contents($_FILES['donor_signature']['tmp_name']);
        $signatureData['donor_signature'] = base64_encode($donorSignature);
    }

    if (!empty($_FILES['guardian_signature']['tmp_name'])) {
        $guardianSignature = file_get_contents($_FILES['guardian_signature']['tmp_name']);
        $signatureData['guardian_signature'] = base64_encode($guardianSignature);
    }
    
    // Define EXACTLY what Supabase expects for civil_status
    $allowedCivilStatuses = ['Single', 'Married', 'Widowed', 'Divorced'];
    
    // Get and validate civil status - this ensures it matches Supabase's constraint
    $civilStatus = in_array($donorData['civil_status'], $allowedCivilStatuses) 
        ? $donorData['civil_status']
        : 'Single'; // Default to Single if invalid (shouldn't happen with select)
    
    // Prepare data for Supabase - all fields match your table exactly
    $supabaseData = [
        'prc_donor_number' => $donorData['prc_donor_number'] ?? null,
        'doh_nnbnets_barcode' => $donorData['doh_nnbnets_barcode'] ?? null,
        'surname' => $donorData['surname'] ?? null,
        'first_name' => $donorData['first_name'] ?? null,
        'middle_name' => $donorData['middle_name'] ?? null,
        'birthdate' => $donorData['birthdate'] ?? null,
        'age' => (int)($donorData['age'] ?? 0),
        'sex' => $donorData['sex'] ?? null,
        'civil_status' => $civilStatus, // Using validated value
        'permanent_address' => $donorData['permanent_address'] ?? null,
        'office_address' => $donorData['office_address'] ?? null,
        'nationality' => $donorData['nationality'] ?? null,
        'religion' => $donorData['religion'] ?? null,
        'education' => $donorData['education'] ?? null,
        'occupation' => $donorData['occupation'] ?? null,
        'telephone' => $donorData['telephone'] ?? null,
        'mobile' => $donorData['mobile'] ?? null,
        'email' => $donorData['email'] ?? null,
        'id_school' => $donorData['id_school'] ?? null,
        'id_company' => $donorData['id_company'] ?? null,
        'id_prc' => $donorData['id_prc'] ?? null,
        'id_drivers' => $donorData['id_drivers'] ?? null,
        'id_sss_gsis_bir' => $donorData['id_sss_gsis_bir'] ?? null,
        'id_others' => $donorData['id_others'] ?? null,
        'relationship' => $donorData['relationship'] ?? null,
        'submitted_at' => date('Y-m-d H:i:s'),
    ];
    
    // Merge signature data if available
    $supabaseData = array_merge($supabaseData, $signatureData);
    
    // Debug output
    error_log('Supabase Submission Data: ' . print_r($supabaseData, true));

    // Send to Supabase
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($supabaseData),
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ],
        CURLOPT_FAILONERROR => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Handle response
    if ($curlError) {
        echo '<script>alert("Network Error: ' . addslashes($curlError) . '");</script>';
    } elseif ($httpCode >= 400) {
        $errorDetails = json_decode($response, true) ?: $response;
        error_log("Supabase Error ($httpCode): " . print_r($errorDetails, true));
        echo '<script>alert("Validation Error: ' . addslashes($errorDetails['message'] ?? 'Check console') . '"); console.error(' . json_encode([
            'error' => $errorDetails,
            'submitted_data' => $supabaseData
        ]) . ');</script>';
    } else {
        unset($_SESSION['donor_data']);
        echo '<script>alert("Submission successful!"); window.location.href = "donor-form.php";</script>';
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donor Form</title>
    <style>
        /* General Styles */
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
        }
        .donor-declaration-container {
            width: 70%;
            margin: auto;
            padding: 25px;
            text-align: center;
            background-color: white;
            border-radius: 10px;
            box-shadow: 3px 3px 10px rgba(0, 0, 0, 0.349);
        }
        
        /* Grid Layout */
        .donor-declaration-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            margin-bottom: 15px;
            gap: 10%;
            align-items: flex-start; /* Keeps elements aligned */
            min-height: 10px; /* Prevents shifting */
        }
        .donor-declaration-header-row {
            grid-template-columns: 2fr 1fr;
            font-size: 18px;
            margin-bottom: 20px;
        }

        /* Button & Input Styling */
        .donor-declaration-button {
            background-color: #d9534f; /* Red */
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
            display: inline-block;
            transition: background 0.3s;
            border: none;
        }
        .donor-declaration-button[type="file"] {
            padding: 8px;
            cursor: pointer;
            border: none;
            appearance: none;
            position: relative;
        }
        .donor-declaration-button::file-selector-button {
            background: #fff;
            border: none;
            padding: 5px 10px;
            margin-right: 5px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }
        .donor-declaration-button:hover {
            background-color: #c9302c;
        }
        
        .donor-declaration-input {
            padding: 10px;
            border: 2px solid rgba(0, 0, 0, 0.575);
            border-radius: 5px;
            text-align: center;
            font-size: 16px;
        }

        .declaration-title {
            padding: 20px;
            text-align: justify;
            font-family: Arial, sans-serif;
        }

        .declaration-title h2 {
            color: #b22222; /* Red cross theme color */
            font-size: 24px;
            margin-bottom: 15px;
        }

        .declaration-title p {
            font-size: 16px;
            line-height: 1.6;
            color: #333;
        }

        .bold {
            font-weight: bold;
            color: #b22222; /* Highlighted words in red */
        }
        /* Styled HR - Professional Look */
        .styled-hr {
            border: 0;
            height: 4px;
            background: #b22222;
            margin-top: 1%;
            margin-bottom: 2%;
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
            border-top: 8px solid #c9302c;
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
/* General error message styling */
.error-message {
    color: red;
    font-size: 14px;
    display: block;
    min-height: 20px; /* Ensures space for error messages */
    margin-top: 5px; /* Adds space below input */
}


/* Specific input error highlighting */
input.is-invalid,
input.is-invalid:focus,
input.is-invalid + .error-message {
    border-color: #d9534f; /* Light red background */
}

/* File input styling */
input[type="file"].is-invalid {
    border: 2px solid #d9534f;
    background-color: #f8d7da;
}

    </style>
</head>
<body>
<form action="donor-declaration.php" method="POST">
    <div class="donor-declaration-container">
        <div class="declaration-container">
            <div class="declaration-title"><h2>III. Donor's Declaration</h2>
            <p>
                I <span class="bold">certify</span> that I am the person referred to above and that all the entries are read and well <span class="bold">understood</span> by me and to the best of my knowledge, <span class="bold">truthfully</span> answered all the questions in this Blood Donor Interview Sheet.
            </p>
            <p>
                I <span class="bold">understand</span> that all questions are pertinent for my safety and for the benefit of the patient who will undergo blood transfusion.
            </p>
            <p>
                I am <span class="bold">voluntarily</span> giving my blood through the Philippine Red Cross, without remuneration, for the use of persons in need of this vital fluid without regard to rank, race, color, creed, religion, or political persuasion.
            </p>
            <p>
                I <span class="bold">understand</span> that my blood will be screened for malaria, syphilis, hepatitis B, hepatitis C, and HIV. I am aware that the screening tests are not diagnostic and may yield false positive results. Should any of the screening tests give a reactive result, I <span class="bold">authorize</span> the Red Cross to inform me utilizing the information I have supplied, subject the results to confirmatory tests, offer counseling, and to dispose of my donated blood in any way it may deem advisable for the safety of the majority of the populace.
            </p>
            <p>
                I <span class="bold">confirm</span> that I am over the age of 18 years.
            </p>
            <p>
                I <span class="bold">understand</span> that all information hereinto is treated confidential in compliance with the <span class="bold">Data Privacy Act of 2012</span>. I therefore <span class="bold">authorize</span> the Philippine Red Cross to utilize the information I supplied for purposes of research or studies for the benefit and safety of the community.
            </p>
    </div>
    <hr class="styled-hr">

        <div class="donor-declaration-row donor-declaration-header-row">
            <div><strong>For those ages 16-17</strong></div>
            <div><strong>Donor's Signature</strong></div>
        </div>
        
        <div class="donor-declaration-row">
            <div><strong>Signature of Parent/Guardian</strong></div>
            <div><strong>Relationship to Blood Donor</strong></div>
        </div>
        
        <div class="donor-declaration-row">
       
            <!-- Parent/Guardian Signature Upload -->
            <div>
                <input type="file" id="guardian-signature" name="guardian_signature" accept="image/png, image/jpeg" class="donor-declaration-button">
                <div id="guardianError" class="error-message"></div> <!-- Corrected placement -->
            </div>
        
            <!-- Relationship Input -->
            <div>
                <input class="donor-declaration-input" type="text" id="relationship" name="relationship" placeholder="Enter Relationship">
                <div id="relationshipError" class="error-message"></div> <!-- Corrected placement -->
            </div>
        
            <!-- Donor Signature Upload -->
            <div>
                <input type="file" id="donor-signature" name="donor_signature" class="donor-declaration-button" accept="image/png, image/jpeg">
                <div id="donorError" class="error-message"></div> <!-- Corrected placement -->
            </div>
        </div>
</form>

        </div>
        
        <div class="submit-section">
            <button class="submit-button" id="triggerModalButton">Submit</button>
        </div>
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
document.getElementById("triggerModalButton").addEventListener("click", function(event) {
    event.preventDefault(); // Prevent form submission

    let guardianSignature = document.getElementById("guardian-signature").files.length;
    let relationship = document.querySelector(".donor-declaration-input").value.trim();
    let donorSignature = document.getElementById("donor-signature").files.length;

    let relationshipError = document.getElementById("relationshipError");
    let donorError = document.getElementById("donorError");
    let guardianError = document.getElementById("guardianError");

    // Reset errors
    document.querySelectorAll(".error-message").forEach(el => el.style.display = "none");
    document.querySelectorAll(".is-invalid").forEach(el => el.classList.remove("is-invalid"));

    let errors = [];

    // Rule 1: If Guardian Signature & Relationship are blank → Donor Signature is required
    if (guardianSignature === 0 && relationship === "" && donorSignature === 0) {
        errors.push({ element: donorError, message: "Donor Signature is required." });
        document.getElementById("donor-signature").classList.add("is-invalid");
    }

    // Rule 2: If Guardian Signature exists, but Relationship is blank & Donor Signature is blank → Relationship is required
    if (guardianSignature > 0 && relationship === "" && donorSignature === 0) {
        errors.push({ element: relationshipError, message: "Relationship is required if a guardian signs." });
        document.querySelector(".donor-declaration-input").classList.add("is-invalid");
    }

    // Rule 3: If Relationship is filled → Guardian Signature is required
    if (relationship !== "" && guardianSignature === 0) {
        errors.push({ element: guardianError, message: "Guardian Signature is required if relationship is provided." });
        document.getElementById("guardian-signature").classList.add("is-invalid");
    }

    // Rule 4: If both Donor & Guardian Signatures are uploaded → Error
    if (guardianSignature > 0 && donorSignature > 0) {
        errors.push({ element: guardianError, message: "Only one signature is needed. Remove either the Donor or Guardian signature." });
        document.getElementById("guardian-signature").classList.add("is-invalid");
        document.getElementById("donor-signature").classList.add("is-invalid");
    }

    // Display errors
    errors.forEach(err => {
        err.element.textContent = err.message;
        err.element.style.display = "block";
    });


    if (errors.length === 0) {
        let confirmationDialog = document.getElementById("confirmationDialog");
        let triggerModalButton = document.getElementById("triggerModalButton");

        confirmationDialog.classList.remove("hide");
        confirmationDialog.classList.add("show");
        confirmationDialog.style.display = "block";
        triggerModalButton.disabled = true; // Disable button while modal is open
    }
});

// Modal logic
let confirmationDialog = document.getElementById("confirmationDialog");
let loadingSpinner = document.getElementById("loadingSpinner");
let cancelButton = document.getElementById("cancelButton");
let confirmButton = document.getElementById("confirmButton");

// Close Modal Function
function closeModal() {
    confirmationDialog.classList.remove("show");
    confirmationDialog.classList.add("hide");
    setTimeout(() => {
        confirmationDialog.style.display = "none";
        document.getElementById("triggerModalButton").disabled = false; // Re-enable button
    }, 300);
}

// Yes Button (Triggers Loading Spinner)
confirmButton.addEventListener("click", function() {
    closeModal();
    loadingSpinner.style.display = "block"; // Show loader
    setTimeout(() => {
        loadingSpinner.style.display = "none"; 
    }, 2000);
});

// No Button (Closes Modal)
cancelButton.addEventListener("click", function() {
    closeModal();
});
document.getElementById("triggerModalButton").addEventListener("click", function(event) {
    event.preventDefault();
    
    let guardianSignature = document.getElementById("guardian-signature").files.length;
    let relationship = document.querySelector(".donor-declaration-input").value.trim();
    let donorSignature = document.getElementById("donor-signature").files.length;

    // [Keep your existing validation code]
    
    if (errors.length === 0) {
        openModal();
    }
});

// Modal logic
let confirmationDialog = document.getElementById("confirmationDialog");
let loadingSpinner = document.getElementById("loadingSpinner");
let cancelButton = document.getElementById("cancelButton");
let confirmButton = document.getElementById("confirmButton");

// Close Modal Function
function closeModal() {
    confirmationDialog.classList.remove("show");
    confirmationDialog.classList.add("hide");
    setTimeout(() => {
        confirmationDialog.style.display = "none";
        document.getElementById("triggerModalButton").disabled = false;
    }, 300);
}

// Yes Button (Submit form)
confirmButton.addEventListener("click", function() {
    closeModal();
    loadingSpinner.style.display = "block";
    
    // Submit the form after showing spinner
    setTimeout(() => {
        document.querySelector("form").submit();
    }, 1000);
});

// No Button (Closes Modal)
cancelButton.addEventListener("click", function() {
    closeModal();
});
    </script>
</body>
</html>
