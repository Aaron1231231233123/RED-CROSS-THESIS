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
    
    // File Upload Directory (Change this to your Supabase storage path)
    $uploadDir = "uploads/";

    // Ensure directory exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Handle file uploads
    $signatureData = [];

    // Upload Donor Signature
    if (!empty($_FILES['donor_signature']['tmp_name'])) {
        $donorSigPath = $uploadDir . uniqid("donor_") . ".png";
        if (move_uploaded_file($_FILES['donor_signature']['tmp_name'], $donorSigPath)) {
            $signatureData['donor_signature'] = $donorSigPath; // Store file path
        } else {
            error_log("Error uploading donor signature");
        }
    }

    // Upload Guardian Signature
    if (!empty($_FILES['guardian_signature']['tmp_name'])) {
        $guardianSigPath = $uploadDir . uniqid("guardian_") . ".png";
        if (move_uploaded_file($_FILES['guardian_signature']['tmp_name'], $guardianSigPath)) {
            $signatureData['guardian_signature'] = $guardianSigPath; // Store file path
        } else {
            error_log("Error uploading guardian signature");
        }
    }

    // Validate Civil Status
    $allowedCivilStatuses = ['Single', 'Married', 'Widowed', 'Divorced'];
    $civilStatus = in_array($donorData['civil_status'], $allowedCivilStatuses) 
        ? $donorData['civil_status']
        : 'Single';

    // Prepare Data for Supabase
    $supabaseData = [
        'prc_donor_number' => $donorData['prc_donor_number'] ?? null,
        'doh_nnbnets_barcode' => $donorData['doh_nnbnets_barcode'] ?? null,
        'surname' => $donorData['surname'] ?? null,
        'first_name' => $donorData['first_name'] ?? null,
        'middle_name' => $donorData['middle_name'] ?? null,
        'birthdate' => $donorData['birthdate'] ?? null,
        'age' => (int)($donorData['age'] ?? 0),
        'sex' => $donorData['sex'] ?? null,
        'civil_status' => $civilStatus,
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

    // Merge signature file paths
    $supabaseData = array_merge($supabaseData, $signatureData);

    // Debugging
    error_log('Supabase Submission Data: ' . print_r($supabaseData, true));

    // Send Data to Supabase
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

    // Handle Response
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

        /* Add eSignature styles without affecting existing ones */
        .signature-pad-container {
            margin: 10px auto;
            width: 100%;
            max-width: 400px;
            display: none;
            border: 2px solid #ddd;
            border-radius: 5px;
            background: white;
            padding: 10px 10px 10px 10px;  /* Removed right padding */
            position: relative;
        }
        
        .signature-pad-container.active {
            display: block;
        }
        
        .signature-pad-container.expanded {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 800px;
            width: 90%;
            height: auto;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            padding: 20px;
        }

        .signature-pad-container.expanded canvas {
            height: 400px;
        }

        .maximize-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 20px;
            cursor: pointer !important;
            z-index: 1061 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            pointer-events: auto !important;
        }

        .maximize-btn:hover {
            color: #c9302c;
        }

        .signature-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 1059;
            display: none;
            pointer-events: auto;
        }

        .signature-overlay.active {
            display: block;
        }
        
        .signature-type-selector {
            margin-bottom: 10px;
            display: flex;
            gap: 20px;
        }
        
        .signature-type-selector label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
        
        .signature-controls {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .signature-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background-color: #d9534f;
            color: white;
            font-weight: bold;
        }
        
        .signature-btn:hover {
            background-color: #c9302c;
        }
        
        #signaturePad {
            width: 100%;
            height: 150px;
            border: none;
        }
        
        .signature-method-selector {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 15px 0;
            width: 100%;
            position: relative;
            min-height: 30px;
        }

        .signature-method-selector label {
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            width: auto;
            white-space: nowrap;
        }

        .signature-method-selector input[type="radio"] {
            width: 20px;
            height: 20px;
            margin: 0;
            position: relative;
        }

        /* Add tooltip/instruction styles */
        .signature-instructions {
            background: #f8f8f8;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 16px;
            color: #333;
        }

        .signature-instructions h4 {
            color: #b22222;
            margin: 0 0 15px 0;
            font-size: 20px;
        }

        .signature-instructions ul {
            margin: 0;
            padding-left: 25px;
        }

        .signature-instructions li {
            margin-bottom: 8px;
            font-size: 16px;
            line-height: 1.5;
        }

        .signature-pad-instructions {
            display: none;
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
        }

        .signature-pad-instructions.active {
            display: block;
        }

        .signature-pad-container canvas {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .signature-controls {
            margin-top: 15px;
            text-align: center;
        }

        .signature-btn {
            min-width: 100px;
            margin: 0 5px;
        }

        /* Improve radio button visibility */
        input[type="radio"] {
            width: 18px;
            height: 18px;
            vertical-align: middle;
        }

        /* Make the canvas border more visible */
        canvas {
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        input[type="file"].donor-declaration-button {
            display: block;
            width: 275px;
            margin: 10px auto;
            text-align: center;
            padding: 8px 15px;
            position: relative;
        }

        .donor-declaration-file-input-wrapper {
            position: relative;
            width: fit-content;
            margin: 10px auto;
        }

        .donor-declaration-clear-file {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            color: #fff;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            display: none;
            padding: 0;
            margin: 0;
            line-height: 1;
            z-index: 2;
        }

        .donor-declaration-clear-file:hover {
            color: #ffd;
        }

        /* Update styles to prevent unwanted interactions */
        .signature-container, .signature-wrapper {
            position: relative;
            z-index: 1060;
            pointer-events: auto;
        }

        .signature-wrapper.fullscreen {
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 1060 !important;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 1200px;
            pointer-events: auto !important;
        }

        .signature-wrapper.fullscreen .maximize-btn {
            top: 20px;
            right: 20px;
        }

        /* Prevent any pointer events on signature images */
        .donor-declaration-img {
            pointer-events: none;
        }

        /* Ensure form elements stay below fullscreen elements */
        form {
            z-index: 1058;
        }

    </style>
</head>
<body>
<form action="donor-declaration.php"  id="donorForm" method="POST" enctype="multipart/form-data">
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

        <div class="signature-instructions">
            <h4>How to Sign Your Form:</h4>
            <ul>
                <li><strong>Upload File:</strong> If you have a scanned copy of your signature, select "Upload File" and choose your signature image.</li>
                <li><strong>Draw Signature:</strong> If you want to sign digitally:
                    <ul>
                        <li>Select "Draw Signature"</li>
                        <li>Use your mouse or finger (on touch screen) to sign in the box that appears</li>
                        <li>Click "Clear" if you want to try again</li>
                        <li>Click "Save" when you're satisfied with your signature</li>
                    </ul>
                </li>
                <li><strong>Note:</strong> For ages 16-17, a parent/guardian signature is required along with their relationship to the donor.</li>
            </ul>
        </div>

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
                <div class="signature-method-selector">
                    <label>
                        <input type="radio" name="guardian_method" value="upload" checked> Upload File
                    </label>
                    <label>
                        <input type="radio" name="guardian_method" value="draw"> Draw Signature
                    </label>
                </div>
                <input type="file" id="guardian-signature" name="guardian_signature" accept="image/png, image/jpeg" class="donor-declaration-button">
                <div class="signature-pad-instructions" id="guardianPadInstructions">
                    Use your mouse or finger to sign below. Take your time to create a clear signature.
                </div>
                <div class="signature-pad-container" id="guardianSignaturePad">
                    <canvas id="guardianPad"></canvas>
                    <button type="button" class="maximize-btn" onclick="toggleFullscreen(this.parentElement, 'guardian', event)">⤢</button>
                    <div class="signature-controls">
                        <button type="button" class="signature-btn" id="clearGuardianSignature">Clear</button>
                        <button type="button" class="signature-btn" id="saveGuardianSignature">Save</button>
                    </div>
                </div>
                <div id="guardianError" class="error-message"></div>
            </div>
        
            <!-- Relationship Input -->
            <div>
                <input class="donor-declaration-input" type="text" id="relationship" name="relationship" placeholder="Enter Relationship">
                <div id="relationshipError" class="error-message"></div>
            </div>
        
            <!-- Donor Signature Upload -->
            <div>
                <div class="signature-method-selector">
                    <label>
                        <input type="radio" name="donor_method" value="upload" checked> Upload File
                    </label>
                    <label>
                        <input type="radio" name="donor_method" value="draw"> Draw Signature
                    </label>
                </div>
                <input type="file" id="donor-signature" name="donor_signature" class="donor-declaration-button" accept="image/png, image/jpeg">
                <div class="signature-pad-instructions" id="donorPadInstructions">
                    Use your mouse or finger to sign below. Take your time to create a clear signature.
                </div>
                <div class="signature-pad-container" id="donorSignaturePad">
                    <canvas id="donorPad"></canvas>
                    <button type="button" class="maximize-btn" onclick="toggleFullscreen(this.parentElement, 'donor', event)">⤢</button>
                    <div class="signature-controls">
                        <button type="button" class="signature-btn" id="clearDonorSignature">Clear</button>
                        <button type="button" class="signature-btn" id="saveDonorSignature">Save</button>
                    </div>
                </div>
                <div id="donorError" class="error-message"></div>
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

    <!-- Signature Pad Library -->
    <script src="/REDCROSS/assets/js/signature_pad.umd.min.js"></script>
    <script>
        // Verify if SignaturePad loaded correctly
        window.addEventListener('load', function() {
            if (typeof SignaturePad === 'undefined') {
                console.error('Failed to load signature pad library');
                alert('Failed to load signature functionality. Please try refreshing the page.');
            } else {
                console.log('SignaturePad library loaded successfully');
                // Initialize signature pads
                const donorPad = new SignaturePad(document.getElementById('donorPad'));
                const guardianPad = new SignaturePad(document.getElementById('guardianPad'));
            }
        });
    </script>
    <script>
document.addEventListener("DOMContentLoaded", function () { 
    let confirmationDialog = document.getElementById("confirmationDialog");
    let loadingSpinner = document.getElementById("loadingSpinner");
    let triggerModalButton = document.getElementById("triggerModalButton");
    let cancelButton = document.getElementById("cancelButton");
    let confirmButton = document.getElementById("confirmButton");
    let donorForm = document.getElementById("donorForm");

    // Validation & Modal Trigger
    triggerModalButton.addEventListener("click", function (event) {
        event.preventDefault(); // Prevent form auto-submission

        let guardianSignature = document.getElementById("guardian-signature").files.length;
        let relationship = document.querySelector(".donor-declaration-input").value.trim();
        let donorSignature = document.getElementById("donor-signature").files.length;

        let relationshipError = document.getElementById("relationshipError");
        let donorError = document.getElementById("donorError");
        let guardianError = document.getElementById("guardianError");

        // Reset error messages
        document.querySelectorAll(".error-message").forEach(el => el.style.display = "none");
        document.querySelectorAll(".is-invalid").forEach(el => el.classList.remove("is-invalid"));

        let errors = [];

        // Validation Rules
        if (guardianSignature === 0 && relationship === "" && donorSignature === 0) {
            errors.push({ element: donorError, message: "Donor Signature is required." });
            document.getElementById("donor-signature").classList.add("is-invalid");
        }
        if (guardianSignature > 0 && relationship === "" && donorSignature === 0) {
            errors.push({ element: relationshipError, message: "Relationship is required if a guardian signs." });
            document.querySelector(".donor-declaration-input").classList.add("is-invalid");
        }
        if (relationship !== "" && guardianSignature === 0) {
            errors.push({ element: guardianError, message: "Guardian Signature is required if relationship is provided." });
            document.getElementById("guardian-signature").classList.add("is-invalid");
        }
        if (guardianSignature > 0 && donorSignature > 0) {
            errors.push({ element: guardianError, message: "Only one signature is needed. Remove either the Donor or Guardian signature." });
            document.getElementById("guardian-signature").classList.add("is-invalid");
            document.getElementById("donor-signature").classList.add("is-invalid");
        }

        // Display Errors
        errors.forEach(err => {
            err.element.textContent = err.message;
            err.element.style.display = "block";
        });

        if (errors.length === 0) {
            openModal();
        }
    });

    // Open Modal Function (Fix)
    function openModal() {
        console.log("Modal should open now."); // Debugging
        confirmationDialog.style.display = "block";
        confirmationDialog.classList.add("show");
        confirmationDialog.classList.remove("hide");
        triggerModalButton.disabled = true;
    }

    // Close Modal Function
    function closeModal() {
        confirmationDialog.style.display = "none";
        confirmationDialog.classList.add("hide");
        confirmationDialog.classList.remove("show");
        triggerModalButton.disabled = false;
    }

    // If "Yes" is clicked, show loader & submit form
    confirmButton.addEventListener("click", function () {
    closeModal();
    loadingSpinner.style.display = "block"; // Show loader
    
    // Ensure the form actually submits
    donorForm.submit();
        setTimeout(() => {
            loadingSpinner.style.display = "none"; // Hide loader
            donorForm.submit(); 
        }, 1000); // Reduced time to 1 second for a faster transition
    });

    // If "No" is clicked, just close the modal
    cancelButton.addEventListener("click", closeModal);
});

document.addEventListener('DOMContentLoaded', function() {
    // Initialize signature pads
    const donorPad = new SignaturePad(document.getElementById('donorPad'));
    const guardianPad = new SignaturePad(document.getElementById('guardianPad'));
    
    // Setup file clear functionality first
    function setupFileClearButton(inputId) {
        const fileInput = document.getElementById(inputId);
        const wrapper = document.createElement('div');
        wrapper.className = 'donor-declaration-file-input-wrapper';
        fileInput.parentNode.insertBefore(wrapper, fileInput);
        wrapper.appendChild(fileInput);
        
        const clearButton = document.createElement('button');
        clearButton.className = 'donor-declaration-clear-file';
        clearButton.innerHTML = '×';
        clearButton.type = 'button';
        wrapper.appendChild(clearButton);

        fileInput.addEventListener('change', function() {
            clearButton.style.display = this.files.length ? 'block' : 'none';
        });

        clearButton.addEventListener('click', function(e) {
            e.stopPropagation();
            fileInput.value = '';
            clearButton.style.display = 'none';
        });

        return { wrapper, clearButton };
    }

    // Setup clear buttons for both file inputs
    const donorElements = setupFileClearButton('donor-signature');
    const guardianElements = setupFileClearButton('guardian-signature');

    function saveSignature(pad, type) {
        if (pad.isEmpty()) {
            alert('Please provide a signature first.');
            return;
        }
        
        try {
            // Get the canvas element and its data
            const canvas = pad.canvas;
            const ctx = canvas.getContext('2d');
            
            // Create a temporary canvas with white background
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = canvas.width;
            tempCanvas.height = canvas.height;
            const tempCtx = tempCanvas.getContext('2d');
            
            // Fill with white background
            tempCtx.fillStyle = '#fff';
            tempCtx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Draw the signature on top
            tempCtx.drawImage(canvas, 0, 0);
            
            // Convert to PNG
            const imageData = tempCanvas.toDataURL('image/png');
            
            // Create a file from the image data
            const byteString = atob(imageData.split(',')[1]);
            const mimeString = imageData.split(',')[0].split(':')[1].split(';')[0];
            const ab = new ArrayBuffer(byteString.length);
            const ia = new Uint8Array(ab);
            for (let i = 0; i < byteString.length; i++) {
                ia[i] = byteString.charCodeAt(i);
            }
            const blob = new Blob([ab], { type: mimeString });
            const file = new File([blob], `${type}_signature.png`, { type: 'image/png' });
            
            // Create a new FileList containing the signature file
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            
            // Update the file input
            const fileInput = document.getElementById(`${type}-signature`);
            fileInput.files = dataTransfer.files;
            
            // Show the file input and trigger change event
            fileInput.style.display = 'block';
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            
            // Hide the signature pad
            document.getElementById(`${type}SignaturePad`).classList.remove('active');
            document.getElementById(`${type}PadInstructions`).classList.remove('active');
            document.querySelector(`input[name="${type}_method"][value="upload"]`).checked = true;
            
        } catch (error) {
            console.error('Error saving signature:', error);
            alert('Failed to save signature. Please try again.');
        }
    }

    // Handle donor signature method selection
    document.querySelectorAll('input[name="donor_method"]').forEach(radio => {
        radio.addEventListener('change', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const padContainer = document.getElementById('donorSignaturePad');
            const fileInput = document.getElementById('donor-signature');
            const instructions = document.getElementById('donorPadInstructions');
            
            if (this.value === 'draw') {
                padContainer.classList.add('active');
                instructions.classList.add('active');
                fileInput.style.display = 'none';
                const canvas = document.getElementById('donorPad');
                canvas.width = padContainer.offsetWidth;
                canvas.height = 150;
            } else {
                padContainer.classList.remove('active');
                instructions.classList.remove('active');
                fileInput.style.display = 'block';
            }
        });
    });
    
    // Handle guardian signature method selection
    document.querySelectorAll('input[name="guardian_method"]').forEach(radio => {
        radio.addEventListener('change', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const padContainer = document.getElementById('guardianSignaturePad');
            const fileInput = document.getElementById('guardian-signature');
            const instructions = document.getElementById('guardianPadInstructions');
            
            if (this.value === 'draw') {
                padContainer.classList.add('active');
                instructions.classList.add('active');
                fileInput.style.display = 'none';
                const canvas = document.getElementById('guardianPad');
                canvas.width = padContainer.offsetWidth;
                canvas.height = 150;
            } else {
                padContainer.classList.remove('active');
                instructions.classList.remove('active');
                fileInput.style.display = 'block';
            }
        });
    });
    
    // Clear signatures
    document.getElementById('clearDonorSignature').addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        donorPad.clear();
    });
    
    document.getElementById('clearGuardianSignature').addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        guardianPad.clear();
    });
    
    // Add save button event listeners
    document.getElementById('saveDonorSignature').addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        saveSignature(donorPad, 'donor');
    });
    
    document.getElementById('saveGuardianSignature').addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        saveSignature(guardianPad, 'guardian');
    });

    // Add fullscreen toggle function
    function toggleFullscreen(container, type, event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        
        const isExpanded = container.classList.contains('expanded');
        const overlay = document.getElementById('signatureOverlay');
        const maximizeBtn = container.querySelector('.maximize-btn');
        const pad = type === 'donor' ? donorPad : guardianPad;
        
        if (isExpanded) {
            container.classList.remove('expanded');
            overlay.classList.remove('active');
            maximizeBtn.innerHTML = '⤢';
            // Resize canvas back to normal
            const canvas = pad.canvas;
            canvas.width = container.offsetWidth - 20;
            canvas.height = 150;
        } else {
            container.classList.add('expanded');
            overlay.classList.add('active');
            maximizeBtn.innerHTML = '⤡';
            // Resize canvas to larger size
            const canvas = pad.canvas;
            canvas.width = container.offsetWidth - 40;
            canvas.height = 400;
        }
        
        // Redraw signature
        pad.fromData(pad.toData());
    }

    // Make toggleFullscreen available globally
    window.toggleFullscreen = toggleFullscreen;

    // Add overlay if it doesn't exist
    if (!document.getElementById('signatureOverlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'signatureOverlay';
        overlay.className = 'signature-overlay';
        document.body.appendChild(overlay);
        
        // Close expanded view when clicking overlay
        overlay.addEventListener('click', function(e) {
            const expandedContainer = document.querySelector('.signature-pad-container.expanded');
            if (expandedContainer) {
                const type = expandedContainer.id === 'donorSignaturePad' ? 'donor' : 'guardian';
                toggleFullscreen(expandedContainer, type, e);
            }
        });
    }
});

    </script>
</body>
</html>
