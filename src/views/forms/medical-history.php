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

// Check if donor_id is passed via URL parameter
if (isset($_GET['donor_id']) && !empty($_GET['donor_id'])) {
    $_SESSION['donor_id'] = $_GET['donor_id'];
    error_log("Set donor_id from URL parameter: " . $_SESSION['donor_id']);
}

// Only check donor_id for staff role (role_id 3)
if ($_SESSION['role_id'] === 3 && !isset($_SESSION['donor_id'])) {
    error_log("Missing donor_id in session for staff");
    header('Location: ../../../public/Dashboards/dashboard-Inventory-System.php');
    exit();
}



// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug log the POST data
        error_log("POST data received: " . print_r($_POST, true));
        error_log("Session data before processing: " . print_r($_SESSION, true));

        // Prepare the data for insertion
        $medical_history_data = [
            'donor_id' => $_SESSION['donor_id'],
            'feels_well' => isset($_POST['q1']) && $_POST['q1'] === 'Yes',
            'feels_well_remarks' => $_POST['q1_remarks'] !== 'None' ? $_POST['q1_remarks'] : null,
            'previously_refused' => isset($_POST['q2']) && $_POST['q2'] === 'Yes',
            'previously_refused_remarks' => $_POST['q2_remarks'] !== 'None' ? $_POST['q2_remarks'] : null,
            'testing_purpose_only' => isset($_POST['q3']) && $_POST['q3'] === 'Yes',
            'testing_purpose_only_remarks' => $_POST['q3_remarks'] !== 'None' ? $_POST['q3_remarks'] : null,
            'understands_transmission_risk' => isset($_POST['q4']) && $_POST['q4'] === 'Yes',
            'understands_transmission_risk_remarks' => $_POST['q4_remarks'] !== 'None' ? $_POST['q4_remarks'] : null,
            'recent_alcohol_consumption' => isset($_POST['q5']) && $_POST['q5'] === 'Yes',
            'recent_alcohol_consumption_remarks' => $_POST['q5_remarks'] !== 'None' ? $_POST['q5_remarks'] : null,
            'recent_aspirin' => isset($_POST['q6']) && $_POST['q6'] === 'Yes',
            'recent_aspirin_remarks' => $_POST['q6_remarks'] !== 'None' ? $_POST['q6_remarks'] : null,
            'recent_medication' => isset($_POST['q7']) && $_POST['q7'] === 'Yes',
            'recent_medication_remarks' => $_POST['q7_remarks'] !== 'None' ? $_POST['q7_remarks'] : null,
            'recent_donation' => isset($_POST['q8']) && $_POST['q8'] === 'Yes',
            'recent_donation_remarks' => $_POST['q8_remarks'] !== 'None' ? $_POST['q8_remarks'] : null,
            'zika_travel' => isset($_POST['q9']) && $_POST['q9'] === 'Yes',
            'zika_travel_remarks' => $_POST['q9_remarks'] !== 'None' ? $_POST['q9_remarks'] : null,
            'zika_contact' => isset($_POST['q10']) && $_POST['q10'] === 'Yes',
            'zika_contact_remarks' => $_POST['q10_remarks'] !== 'None' ? $_POST['q10_remarks'] : null,
            'zika_sexual_contact' => isset($_POST['q11']) && $_POST['q11'] === 'Yes',
            'zika_sexual_contact_remarks' => $_POST['q11_remarks'] !== 'None' ? $_POST['q11_remarks'] : null,
            'blood_transfusion' => isset($_POST['q12']) && $_POST['q12'] === 'Yes',
            'blood_transfusion_remarks' => $_POST['q12_remarks'] !== 'None' ? $_POST['q12_remarks'] : null,
            'surgery_dental' => isset($_POST['q13']) && $_POST['q13'] === 'Yes',
            'surgery_dental_remarks' => $_POST['q13_remarks'] !== 'None' ? $_POST['q13_remarks'] : null,
            'tattoo_piercing' => isset($_POST['q14']) && $_POST['q14'] === 'Yes',
            'tattoo_piercing_remarks' => $_POST['q14_remarks'] !== 'None' ? $_POST['q14_remarks'] : null,
            'risky_sexual_contact' => isset($_POST['q15']) && $_POST['q15'] === 'Yes',
            'risky_sexual_contact_remarks' => $_POST['q15_remarks'] !== 'None' ? $_POST['q15_remarks'] : null,
            'unsafe_sex' => isset($_POST['q16']) && $_POST['q16'] === 'Yes',
            'unsafe_sex_remarks' => $_POST['q16_remarks'] !== 'None' ? $_POST['q16_remarks'] : null,
            'hepatitis_contact' => isset($_POST['q17']) && $_POST['q17'] === 'Yes',
            'hepatitis_contact_remarks' => $_POST['q17_remarks'] !== 'None' ? $_POST['q17_remarks'] : null,
            'imprisonment' => isset($_POST['q18']) && $_POST['q18'] === 'Yes',
            'imprisonment_remarks' => $_POST['q18_remarks'] !== 'None' ? $_POST['q18_remarks'] : null,
            'uk_europe_stay' => isset($_POST['q19']) && $_POST['q19'] === 'Yes',
            'uk_europe_stay_remarks' => $_POST['q19_remarks'] !== 'None' ? $_POST['q19_remarks'] : null,
            'foreign_travel' => isset($_POST['q20']) && $_POST['q20'] === 'Yes',
            'foreign_travel_remarks' => $_POST['q20_remarks'] !== 'None' ? $_POST['q20_remarks'] : null,
            'drug_use' => isset($_POST['q21']) && $_POST['q21'] === 'Yes',
            'drug_use_remarks' => $_POST['q21_remarks'] !== 'None' ? $_POST['q21_remarks'] : null,
            'clotting_factor' => isset($_POST['q22']) && $_POST['q22'] === 'Yes',
            'clotting_factor_remarks' => $_POST['q22_remarks'] !== 'None' ? $_POST['q22_remarks'] : null,
            'positive_disease_test' => isset($_POST['q23']) && $_POST['q23'] === 'Yes',
            'positive_disease_test_remarks' => $_POST['q23_remarks'] !== 'None' ? $_POST['q23_remarks'] : null,
            'malaria_history' => isset($_POST['q24']) && $_POST['q24'] === 'Yes',
            'malaria_history_remarks' => $_POST['q24_remarks'] !== 'None' ? $_POST['q24_remarks'] : null,
            'std_history' => isset($_POST['q25']) && $_POST['q25'] === 'Yes',
            'std_history_remarks' => $_POST['q25_remarks'] !== 'None' ? $_POST['q25_remarks'] : null,
            'cancer_blood_disease' => isset($_POST['q26']) && $_POST['q26'] === 'Yes',
            'cancer_blood_disease_remarks' => $_POST['q26_remarks'] !== 'None' ? $_POST['q26_remarks'] : null,
            'heart_disease' => isset($_POST['q27']) && $_POST['q27'] === 'Yes',
            'heart_disease_remarks' => $_POST['q27_remarks'] !== 'None' ? $_POST['q27_remarks'] : null,
            'lung_disease' => isset($_POST['q28']) && $_POST['q28'] === 'Yes',
            'lung_disease_remarks' => $_POST['q28_remarks'] !== 'None' ? $_POST['q28_remarks'] : null,
            'kidney_disease' => isset($_POST['q29']) && $_POST['q29'] === 'Yes',
            'kidney_disease_remarks' => $_POST['q29_remarks'] !== 'None' ? $_POST['q29_remarks'] : null,
            'chicken_pox' => isset($_POST['q30']) && $_POST['q30'] === 'Yes',
            'chicken_pox_remarks' => $_POST['q30_remarks'] !== 'None' ? $_POST['q30_remarks'] : null,
            'chronic_illness' => isset($_POST['q31']) && $_POST['q31'] === 'Yes',
            'chronic_illness_remarks' => $_POST['q31_remarks'] !== 'None' ? $_POST['q31_remarks'] : null,
            'recent_fever' => isset($_POST['q32']) && $_POST['q32'] === 'Yes',
            'recent_fever_remarks' => $_POST['q32_remarks'] !== 'None' ? $_POST['q32_remarks'] : null,
            'pregnancy_history' => isset($_POST['q33']) && $_POST['q33'] === 'Yes',
            'pregnancy_history_remarks' => $_POST['q33_remarks'] !== 'None' ? $_POST['q33_remarks'] : null,
            'last_childbirth' => isset($_POST['q34']) && $_POST['q34'] === 'Yes',
            'last_childbirth_remarks' => $_POST['q34_remarks'] !== 'None' ? $_POST['q34_remarks'] : null,
            'recent_miscarriage' => isset($_POST['q35']) && $_POST['q35'] === 'Yes',
            'recent_miscarriage_remarks' => $_POST['q35_remarks'] !== 'None' ? $_POST['q35_remarks'] : null,
            'breastfeeding' => isset($_POST['q36']) && $_POST['q36'] === 'Yes',
            'breastfeeding_remarks' => $_POST['q36_remarks'] !== 'None' ? $_POST['q36_remarks'] : null,
            'last_menstruation' => isset($_POST['q37']) && $_POST['q37'] === 'Yes',
            'last_menstruation_remarks' => $_POST['q37_remarks'] !== 'None' ? $_POST['q37_remarks'] : null
        ];

        // Remove any null values from the data array
        $medical_history_data = array_filter($medical_history_data, function($value) {
            return $value !== null;
        });

        // Debug log
        error_log("Submitting medical history data: " . print_r($medical_history_data, true));

        // Initialize cURL session for Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history');

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
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($medical_history_data));

        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Debug log
        error_log("Supabase response code: " . $http_code);
        error_log("Supabase response: " . $response);
        
        curl_close($ch);

        if ($http_code === 201) {
            // Parse the response to get the medical history ID
            $response_data = json_decode($response, true);
            
            // Debug log the response data
            error_log("Supabase response data: " . print_r($response_data, true));
            
            // Check if we have a valid response array and it contains the medical_history_id
            if (is_array($response_data) && isset($response_data[0]['medical_history_id'])) {
                $_SESSION['medical_history_id'] = $response_data[0]['medical_history_id'];
                error_log("Stored medical_history_id in session: " . $_SESSION['medical_history_id']);
                
                // Both admin and staff should go to screening form
                error_log("Redirecting to screening form");
                header('Location: screening-form.php');
                exit();
            } else {
                error_log("Invalid response format or missing medical_history_id: " . print_r($response_data, true));
                throw new Exception("Medical history ID not found in response. Response: " . $response);
            }
        } else {
            throw new Exception("Failed to submit medical history. HTTP Code: " . $http_code . " Response: " . $response);
        }
    } catch (Exception $e) {
        error_log("Error in medical history form: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: medical-history.php?error=1');
        exit();
    }
}

// Debug log to check all session variables
error_log("All session variables in medical-history.php: " . print_r($_SESSION, true));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donor Interview</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            margin: 20px;
        }
        .form-container {
            display: grid;
            grid-template-columns: 0.5fr 4fr 1fr 1fr 3fr;
            gap: 5px;
            max-width: 54%;
            margin: auto;
            padding: 2%;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            font-weight: bold;
            text-align: center;
            background-color: #d32f2f;
            color: white;
            padding: 10px;
            border-radius: 5px;
        }
        .bold {
            font-weight: bold;
            grid-column: span 5;
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .cell {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
        }
        .number {
            text-align: center;
            font-weight: bold;
            justify-content: center;
        }
        .checkbox {
            text-align: center;
            justify-content: center;
        }
        input[type="checkbox"] {
            transform: scale(1.2);
        }
        input[type="text"] {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .title, .subtitle{
            margin-left: 22%;
        }
        .title {
            text-align: left;
            font-size: 200%;
            font-weight: bold;
            margin-bottom: 10px;
            
        }
        .subtitle {
            text-align: left;
            font-style: italic;
            margin-bottom: 20px;
        }
        /* Submit Button Section */
        .submit-section {
            grid-column: span 5;
            display: flex;
            justify-content: flex-end;
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
       

        .blood-bag-option {
    display: flex;
    align-items: center;
    font-weight: bold;
    color: #721c24;
    gap: 8px;
    background: #fff;
    padding: 10px 15px;
    border-radius: 6px;
    cursor: pointer;
}

/* Hide the default radio */
.blood-bag-option input {
    opacity: 0;
    position: absolute;
}

/* Custom checkbox look */
.checkmark {
    width: 18px;
    height: 18px;
    background-color: #fff;
    border: 2px solid #000000;
    border-radius: 4px;
    display: inline-block;
    position: relative;
    transition: background-color 0.3s ease;
}

/* Change background when selected */
.blood-bag-option input:checked ~ .checkmark {
    background-color: #0559f5;
}

/* Checkmark tick */
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

/* Show tick when selected */
.blood-bag-option input:checked ~ .checkmark::after {
    display: block;
}
.medical-history-remarks {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
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
    </style>
</head>
<body>
    <div class="title">II. Medical History</div>
    <div class="subtitle">Please read carefully and answer all relevant questions. Tick (check) the appropriate answer.</div>
    <form method="POST" action="" id="medicalHistoryForm" class="form-container">
        <div class="header">#</div>
        <div class="header">Question</div>
        <div class="header">Yes</div>
        <div class="header">No</div>
        <div class="header">Remarks</div>
        
        <script>
            const questions = [
                "Do you feel well and healthy today?",
                "Have you ever been refused as a blood donor or told not to donate blood for any reasons?",
                "Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?",
                "Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?",
                "Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?",
                "In the last 3 DAYS have you taken aspirin?",
                "In the past 4 WEEKS have you taken any medications and/or vaccinations?",
                "In the past 3 MONTHS have you donated whole blood, platelets or plasma?",
                "IN THE PAST 6 MONTHS HAVE YOU:",
                "Been to any places in the Philippines or countries infected with ZIKA Virus?",
                "Had sexual contact with a person who was confirmed to have ZIKA Virus Infection?",
                "Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?",
                "IN THE PAST 12 MONTHS HAVE YOU:",
                "Received blood, blood products and/or had tissue/organ transplant or graft?",
                "Had surgical operation or dental extraction?",
                "Had a tattoo applied, ear and body piercing, acupuncture, needle stick Injury or accidental contact with blood?",
                "Had sexual contact with high risks individuals or in exchange for material or monetary gain?",
                "Engaged in unprotected, unsafe or casual sex?",
                "Had jaundice/hepatitis/personal contact with person who had hepatitis?",
                "Been incarcerated, Jailed or imprisoned?",
                "Spent time or have relatives in the United Kingdom or Europe?",
                "HAVE YOU EVER:",
                "Travelled or lived outside of your place of residence or outside the Philippines?",
                "Taken prohibited drugs (orally, by nose, or by injection)?",
                "Used clotting factor concentrates?",
                "Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?",
                "Had Malaria or Hepatitis in the past?",
                "Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?",
                "HAD ANY OF THE FOLLOWING:",
                "Cancer, blood disease or bleeding disorder (haemophilia)?",
                "Heart disease/surgery, rheumatic fever or chest pains?",
                "Lung disease, tuberculosis or asthma?",
                "Kidney disease, thyroid disease, diabetes, epilepsy?",
                "Chicken pox and/or cold sores?",
                "Any other chronic medical condition or surgical operations?",
                "Have you recently had rash and/or fever? Was/were this/these also associated with arthralgia or arthritis or conjunctivitis?",
                "FOR FEMALE DONORS ONLY:",
                "Are you currently pregnant or have you ever been pregnant?",
                "When was your last childbirth?",
                "In the past 1 YEAR, did you have a miscarriage or abortion?",
                "Are you currently breastfeeding?",
                "When was your last menstrual period?"
            ];
            
            // Define remarks options based on question type
            const remarksOptions = {
                // General Health (q1)
                1: ["None", "Feeling Unwell", "Fatigue", "Fever", "Other Health Issues"],
                
                // Previous Refusal (q2)
                2: ["None", "Low Hemoglobin", "Medical Condition", "Recent Surgery", "Other Refusal Reason"],
                
                // Testing Purpose (q3-4)
                3: ["None", "HIV Test", "Hepatitis Test", "Other Test Purpose"],
                4: ["None", "Understood", "Needs More Information"],
                
                // Recent Consumption (q5-6)
                5: ["None", "Beer", "Wine", "Liquor", "Multiple Types"],
                6: ["None", "Pain Relief", "Fever", "Other Medication Purpose"],
                
                // Recent Medical History (q7-8)
                7: ["None", "Antibiotics", "Vitamins", "Vaccines", "Other Medications"],
                8: ["None", "Red Cross Donation", "Hospital Donation", "Other Donation Type"],
                
                // Zika Related (q9-11)
                9: ["None", "Local Travel", "International Travel", "Specific Location"],
                10: ["None", "Direct Contact", "Indirect Contact", "Suspected Case"],
                11: ["None", "Partner Travel History", "Unknown Exposure", "Other Risk"],
                
                // Medical Procedures (q12-16)
                12: ["None", "Blood Transfusion", "Organ Transplant", "Other Procedure"],
                13: ["None", "Major Surgery", "Minor Surgery", "Dental Work"],
                14: ["None", "Tattoo", "Piercing", "Acupuncture", "Blood Exposure"],
                15: ["None", "High Risk Contact", "Multiple Partners", "Other Risk Factors"],
                16: ["None", "Unprotected Sex", "Casual Contact", "Other Risk Behavior"],
                
                // Medical Conditions (q17-25)
                17: ["None", "Personal History", "Family Contact", "Other Exposure"],
                18: ["None", "Short Term", "Long Term", "Other Details"],
                19: ["None", "UK Stay", "Europe Stay", "Duration of Stay"],
                20: ["None", "Local Travel", "International Travel", "Duration"],
                21: ["None", "Recreational", "Medical", "Other Usage"],
                22: ["None", "Treatment History", "Current Use", "Other Details"],
                23: ["None", "HIV", "Hepatitis", "Syphilis", "Malaria"],
                24: ["None", "Past Infection", "Treatment History", "Other Details"],
                25: ["None", "Current Infection", "Past Treatment", "Other Details"],
                
                // Chronic Conditions (q26-32)
                26: ["None", "Cancer Type", "Blood Disease", "Bleeding Disorder"],
                27: ["None", "Heart Disease", "Surgery History", "Current Treatment"],
                28: ["None", "Active TB", "Asthma", "Other Respiratory Issues"],
                29: ["None", "Kidney Disease", "Thyroid Issue", "Diabetes", "Epilepsy"],
                30: ["None", "Recent Infection", "Past Infection", "Other Details"],
                31: ["None", "Condition Type", "Treatment Status", "Other Details"],
                32: ["None", "Recent Fever", "Rash", "Joint Pain", "Eye Issues"],
                
                // Female Specific (q33-37)
                33: ["None", "Current Pregnancy", "Past Pregnancy", "Other Details"],
                34: ["None", "Less than 6 months", "6-12 months ago", "More than 1 year ago"],
                35: ["None", "Less than 3 months ago", "3-6 months ago", "6-12 months ago"],
                36: ["None", "Currently Breastfeeding", "Recently Stopped", "Other"],
                37: ["None", "Within last week", "1-2 weeks ago", "2-4 weeks ago", "More than 1 month ago"]
            };
            
            let count = 1;
            questions.forEach(q => {
                if (q.includes(":")) {
                    document.write(`<div class='bold'>${q}</div>`);
                } else {
                    document.write(`<div class='cell number'>${count}</div>`);
                    document.write(`<div class='cell'>${q}</div>`);
                    document.write(`
                        <div class='cell checkbox'>
                            <label class="blood-bag-option">
                                <input type='radio' name='q${count}' value='Yes' required>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class='cell checkbox'>
                            <label class="blood-bag-option">
                                <input type='radio' name='q${count}' value='No' required>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class='cell'>
                            <select class="medical-history-remarks" name='q${count}_remarks'>
                                ${remarksOptions[count].map(option => 
                                    `<option value="${option}">${option}</option>`
                                ).join('')}
                            </select>
                        </div>
                    `);
                    count++;
                }
            });
        </script>
        <div class="submit-section">
            <button type="submit" class="submit-button" id="submitButton">Submit</button>
        </div>
    </form>

    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let loadingSpinner = document.getElementById("loadingSpinner");
            let submitButton = document.getElementById("submitButton");
            let form = document.getElementById("medicalHistoryForm");

            // Handle direct form submission
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                
                if (!form.checkValidity()) {
                    alert("Please fill in all required fields before proceeding.");
                    return;
                }
                
                loadingSpinner.style.display = "block";
                form.submit();
            });
        });
    </script>

</body>
</html>