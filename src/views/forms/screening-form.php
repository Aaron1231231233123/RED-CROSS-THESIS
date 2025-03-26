<?php
session_start();

// Check if both donor_id and medical_history_id exist in session
if (!isset($_SESSION['donor_id']) || !isset($_SESSION['medical_history_id'])) {
    header('Location: ../../../public/Dashboards/dashboard-staff-donor-submission.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../../assets/conn/db_conn.php';
    
    try {
        // Check if this is a disapproval submission
        if (isset($_POST['action']) && $_POST['action'] === 'disapprove') {
            if (empty($_POST['disapproval_reason'])) {
                throw new Exception("Please provide a reason for disapproval");
            }

            // Prepare the data for screening form with disapproval
            $screening_data = [
                'donor_form_id' => $_SESSION['donor_id'],
                'medical_history_id' => $_SESSION['medical_history_id'],
                'disapproval_reason' => $_POST['disapproval_reason'],
                'body_weight' => 0,
                'specific_gravity' => '',
                'hemoglobin' => '',
                'hematocrit' => '',
                'rbc_count' => '',
                'wbc_count' => '',
                'platelet_count' => 0,
                'blood_type' => '',
                'donation_type' => '',
                'interviewer_name' => $_POST['interviewer'],
                'interview_date' => date('Y-m-d')
            ];
        } else {
            // Regular submission (existing code)
            $screening_data = [
                'donor_form_id' => $_SESSION['donor_id'],
                'medical_history_id' => $_SESSION['medical_history_id'],
                'body_weight' => $_POST['body-wt'],
                'specific_gravity' => $_POST['sp-gr'],
                'hemoglobin' => $_POST['hgb'],
                'hematocrit' => $_POST['hct'],
                'rbc_count' => $_POST['rbc'],
                'wbc_count' => $_POST['wbc'],
                'platelet_count' => intval($_POST['plt-count']),
                'blood_type' => $_POST['blood-type'],
                'donation_type' => $_POST['donation-type'],
                'mobile_location' => $_POST['mobile-place'],
                'mobile_organizer' => $_POST['mobile-organizer'],
                'patient_name' => $_POST['patient-name'],
                'hospital' => $_POST['hospital'],
                'patient_blood_type' => $_POST['blood-type-patient'],
                'component_type' => $_POST['wb-component'],
                'units_needed' => intval($_POST['no-units']),
                'has_previous_donation' => isset($_POST['history']) && $_POST['history'] === 'yes',
                'red_cross_donations' => intval($_POST['red-cross']),
                'hospital_donations' => intval($_POST['hospital-history']),
                'last_rc_donation_date' => $_POST['last-rc-donation-date'],
                'last_hosp_donation_date' => $_POST['last-hosp-donation-date'],
                'last_rc_donation_place' => $_POST['last-rc-donation-place'],
                'last_hosp_donation_place' => $_POST['last-hosp-donation-place'],
                'interviewer_name' => $_POST['interviewer'],
                'interview_date' => date('Y-m-d')
            ];
        }

        // Initialize cURL session for Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form');

        // Set the headers
        $headers = array(
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        );

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($screening_data));

        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 201) {
            // Store the response data
            $response_data = json_decode($response, true);
            
            // Store screening form ID in session for future use
            $_SESSION['screening_form_id'] = $response_data['id'];

            // Update the donor status to indicate they're ready for physical examination
            $update_donor_ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?id=eq.' . $_SESSION['donor_id']);
            
            $update_headers = array(
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            );

            $update_data = [
                'status' => 'pending_physical',
                'screening_completed_at' => date('Y-m-d H:i:s')
            ];

            curl_setopt($update_donor_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($update_donor_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($update_donor_ch, CURLOPT_HTTPHEADER, $update_headers);
            curl_setopt($update_donor_ch, CURLOPT_POSTFIELDS, json_encode($update_data));

            $update_response = curl_exec($update_donor_ch);
            $update_http_code = curl_getinfo($update_donor_ch, CURLINFO_HTTP_CODE);
            curl_close($update_donor_ch);

            if ($update_http_code === 204) {
                // Clear the session since we're done with this process
                session_destroy();
                header('Location: ../../../public/Dashboards/dashboard-staff-donor-submission.php');
                exit();
            } else {
                error_log("Error updating donor status: " . $update_response);
                echo "<script>alert('Error: Failed to update donor status');</script>";
            }
        } else {
            // Handle error
            error_log("Error submitting screening form: " . $response);
            echo "<script>alert('Error: Failed to submit screening form');</script>";
        }
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        echo "<script>alert('Error: " . htmlspecialchars($e->getMessage()) . "');</script>";
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
                    <td><input type="number" step="0.01" name="body-wt" required></td>
                    <td><input type="text" name="sp-gr" required></td>
                    <td><input type="text" name="hgb" required></td>
                    <td><input type="text" name="hct" required></td>
                    <td><input type="text" name="rbc" required></td>
                    <td><input type="text" name="wbc" required></td>
                    <td><input type="number" name="plt-count" required></td>
                    <td><input type="text" name="blood-type" required></td>
                </tr>
            </table>

            <div class="screening-form-donation">
                <p>TYPE OF DONATION (Donor's Choice):</p>
                <div class="donation-options">
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="in-house" required> 
                        <span class="checkmark"></span>
                        IN-HOUSE
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="walk-in" required> 
                        <span class="checkmark"></span>
                        WALK-IN/VOLUNTARY
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="replacement" required> 
                        <span class="checkmark"></span>
                        REPLACEMENT
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="patient-directed" required> 
                        <span class="checkmark"></span>
                        PATIENT-DIRECTED
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="mobile" required> 
                        <span class="checkmark"></span>
                        Mobile Blood Donation
                    </label>
                </div>
                
                <div class="mobile-donation-section">
                    <div class="mobile-donation-fields">
                        <label>
                            PLACE: 
                            <input type="text" name="mobile-place">
                        </label>
                        <label>
                            ORGANIZER: 
                            <input type="text" name="mobile-organizer">
                        </label>
                    </div>
                </div>
            </div>
            

            <table class="screening-form-patient">
                <tr>
                    <th>Patient Name</th>
                    <th>Hospital</th>
                    <th>Blood Type</th>
                    <th>WB/Component</th>
                    <th>No. of units</th>
                </tr>
                <tr>
                    <td><input type="text" name="patient-name"></td>
                    <td><input type="text" name="hospital"></td>
                    <td><input type="text" name="blood-type-patient"></td>
                    <td><input type="text" name="wb-component"></td>
                    <td><input type="number" name="no-units"></td>
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
                    <td><input type="number" name="red-cross" value="0"></td>
                    <td><input type="number" name="hospital-history" value="0"></td>
                </tr>
                <tr>
                    <th>Date of last donation</th>
                    <td><input type="date" name="last-rc-donation-date"></td>
                    <td><input type="date" name="last-hosp-donation-date"></td>
                </tr>
                <tr>
                    <th>Place of last donation</th>
                    <td><input type="text" name="last-rc-donation-place"></td>
                    <td><input type="text" name="last-hosp-donation-place"></td>
                </tr>

            </table>

            <div class="screening-form-footer">
                <label>INTERVIEWER (print name & sign): <input type="text" name="interviewer" required></label>
                <label>PRC Office</label>
                <p>Date: <span id="current-date"></span></p>
            </div>
            <div class="submit-section">
                <button type="button" class="submit-button" id="triggerModalButton">Submit</button>
                <button type="button" class="disapprove-button" id="triggerDisapproveModalButton">Disapprove</button>
            </div>
        </div>
    </form>
    <!-- Disapproval Modal -->
    <div class="confirmation-modal" id="disapprovalDialog">
        <div class="modal-header">Provide Reason for Disapproval</div>
        <div class="modal-body">
            <textarea id="disapprovalReason" class="form-control" rows="4" placeholder="Enter reason for disapproval..."></textarea>
        </div>
        <div class="modal-actions">
            <button class="modal-button cancel-action" id="cancelDisapproveButton">Cancel</button>
            <button class="modal-button disapprove-action" id="confirmDisapproveButton">Confirm Disapproval</button>
        </div>
    </div>

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
        document.getElementById("current-date").textContent = new Date().toLocaleDateString();

        let confirmationDialog = document.getElementById("confirmationDialog");
        let loadingSpinner = document.getElementById("loadingSpinner");
        let triggerModalButton = document.getElementById("triggerModalButton");
        let cancelButton = document.getElementById("cancelButton");
        let confirmButton = document.getElementById("confirmButton");
        let disapprovalDialog = document.getElementById("disapprovalDialog");
        let triggerDisapproveModalButton = document.getElementById("triggerDisapproveModalButton");
        let cancelDisapproveButton = document.getElementById("cancelDisapproveButton");
        let confirmDisapproveButton = document.getElementById("confirmDisapproveButton");
        let disapprovalReason = document.getElementById("disapprovalReason");
        let form = document.getElementById("screeningForm");

        // Open Modal
        triggerModalButton.addEventListener("click", function() {
            // Check if required fields are filled
            if (!form.checkValidity()) {
                alert("Please fill in all required fields before proceeding.");
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

        // Yes Button (Triggers form submission)
        confirmButton.addEventListener("click", function() {
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
                if(response.ok) {
                    window.location.href = "../../../public/Dashboards/dashboard-staff-donor-submission.php";
                } else {
                    throw new Error('Form submission failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Error submitting form. Please try again.");
                loadingSpinner.style.display = "none";
            });
        });

        // No Button (Closes Modal)
        cancelButton.addEventListener("click", function() {
            closeModal();
        });

        // Open Disapproval Modal
        triggerDisapproveModalButton.addEventListener("click", function() {
            disapprovalDialog.classList.remove("hide");
            disapprovalDialog.classList.add("show");
            disapprovalDialog.style.display = "block";
            triggerDisapproveModalButton.disabled = true;
        });

        // Close Disapproval Modal Function
        function closeDisapprovalModal() {
            disapprovalDialog.classList.remove("show");
            disapprovalDialog.classList.add("hide");
            setTimeout(() => {
                disapprovalDialog.style.display = "none";
                triggerDisapproveModalButton.disabled = false;
            }, 300);
        }

        // Confirm Disapproval
        confirmDisapproveButton.addEventListener("click", function() {
            if (!disapprovalReason.value.trim()) {
                alert("Please provide a reason for disapproval");
                return;
            }

            closeDisapprovalModal();
            loadingSpinner.style.display = "block";

            // Create form data with disapproval action
            const formData = new FormData();
            formData.append('action', 'disapprove');
            formData.append('disapproval_reason', disapprovalReason.value.trim());
            formData.append('interviewer', document.querySelector('input[name="interviewer"]').value);

            // Submit the form
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if(response.ok) {
                    window.location.href = "../../../public/Dashboards/dashboard-staff-donor-submission.php";
                } else {
                    throw new Error('Form submission failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Error submitting form. Please try again.");
                loadingSpinner.style.display = "none";
            });
        });

        // Cancel Disapproval
        cancelDisapproveButton.addEventListener("click", closeDisapprovalModal);

    </script>
</body>
</html>