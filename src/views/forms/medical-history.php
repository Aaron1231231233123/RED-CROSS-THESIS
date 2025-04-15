<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../public/login.php");
    exit();
}

// Debug log session data
error_log("Session data in medical-history.php: " . print_r($_SESSION, true));
error_log("Role ID type: " . gettype($_SESSION['role_id']) . ", Value: " . $_SESSION['role_id']);

// Get donor_id from hashed ID or direct parameter
if (isset($_GET['hid']) && isset($_SESSION['donor_hashes'][$_GET['hid']])) {
    $donor_id = $_SESSION['donor_hashes'][$_GET['hid']];
    $_SESSION['donor_id'] = $donor_id;
} elseif (isset($_GET['donor_id'])) {
    $donor_id = $_GET['donor_id'];
    $_SESSION['donor_id'] = $donor_id;
} else {
    error_log("No donor_id found in URL parameters");
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id'])) {
    error_log("Role ID not set in session");
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// Convert role_id to integer for proper comparison
$role_id = (int)$_SESSION['role_id'];
error_log("Converted role_id to integer: " . $role_id);

if ($role_id !== 1 && $role_id !== 3) {
    error_log("Invalid role_id: " . $role_id);
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// For staff role, ensure we have a valid donor_id
if ($role_id === 3) {
    if (!isset($donor_id) || empty($donor_id)) {
        error_log("Missing or empty donor_id for staff role");
        header('Location: ../../../public/Dashboards/dashboard-staff-medical-history-submissions.php');
        exit();
    }
    $_SESSION['donor_id'] = $donor_id;
}

// Fetch existing medical history data if donor_id is set
$medical_history_data = null;
$donor_sex = null;

if (isset($_SESSION['donor_id'])) {
    // First fetch donor's sex from donor_form table
    $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . $_SESSION['donor_id'] . '&select=sex');
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($http_code === 200) {
        $donor_data = json_decode($response, true);
        if (!empty($donor_data)) {
            $donor_sex = strtolower($donor_data[0]['sex']);
            error_log("Fetched donor sex: " . $donor_sex);
        }
    }

    // Then fetch medical history data
    $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $_SESSION['donor_id']);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (!empty($data)) {
            $medical_history_data = $data[0];
            error_log("Fetched medical history data: " . print_r($medical_history_data, true));
        }
    }
}

// Debug log to check all session variables
error_log("All session variables in medical-history.php: " . print_r($_SESSION, true));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log("=== START OF FORM SUBMISSION ===");
        error_log("Raw POST data: " . print_r($_POST, true));
        error_log("Action value: " . (isset($_POST['action']) ? $_POST['action'] : 'not set'));

        // Initialize the update data array
        $medical_history_data = [
            'donor_id' => $_SESSION['donor_id']
        ];

        // Check which button was clicked and set the approval status
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            error_log("Processing action: " . $action);
            
            if ($action === 'approve') {
                $medical_history_data['medical_approval'] = 'Approved';
                error_log("Setting status to Approved");
            } elseif ($action === 'decline') {
                $medical_history_data['medical_approval'] = 'Declined';
                error_log("Setting status to Declined");
            } else {
                error_log("Unknown action value: " . $action);
            }
        } else {
            error_log("No action value found in POST data");
        }

        // Process all question responses
        for ($i = 1; $i <= 37; $i++) {
            if (isset($_POST["q$i"])) {
                $fieldName = getFieldName($i);
                if ($fieldName) {
                    $medical_history_data[$fieldName] = $_POST["q$i"] === 'Yes';
                    if (isset($_POST["q{$i}_remarks"]) && $_POST["q{$i}_remarks"] !== 'None') {
                        $medical_history_data[$fieldName . '_remarks'] = $_POST["q{$i}_remarks"];
                    }
                }
            }
        }

        error_log("Final data to be sent: " . print_r($medical_history_data, true));

        // Update the medical history record
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $_SESSION['donor_id']);
        
        $jsonData = json_encode($medical_history_data);
        error_log("JSON data being sent: " . $jsonData);
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log("Supabase response code: " . $http_code);
        error_log("Supabase response: " . $response);
        
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            error_log("Update successful, redirecting to dashboard");
            header('Location: ../../../public/Dashboards/dashboard-staff-medical-history-submissions.php');
                    exit();
        } else {
            throw new Exception("Error updating medical history. HTTP Code: $http_code, Response: " . $response);
        }
    } catch (Exception $e) {
        error_log("Error in form submission: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    error_log("=== END OF FORM SUBMISSION ===");
}

function getFieldName($count) {
    $fields = [
        1 => 'feels_well',
        2 => 'previously_refused',
        3 => 'testing_purpose_only',
        4 => 'understands_transmission_risk',
        5 => 'recent_alcohol_consumption',
        6 => 'recent_aspirin',
        7 => 'recent_medication',
        8 => 'recent_donation',
        9 => 'zika_travel',
        10 => 'zika_contact',
        11 => 'zika_sexual_contact',
        12 => 'blood_transfusion',
        13 => 'surgery_dental',
        14 => 'tattoo_piercing',
        15 => 'risky_sexual_contact',
        16 => 'unsafe_sex',
        17 => 'hepatitis_contact',
        18 => 'imprisonment',
        19 => 'uk_europe_stay',
        20 => 'foreign_travel',
        21 => 'drug_use',
        22 => 'clotting_factor',
        23 => 'positive_disease_test',
        24 => 'malaria_history',
        25 => 'std_history',
        26 => 'cancer_blood_disease',
        27 => 'heart_disease',
        28 => 'lung_disease',
        29 => 'kidney_disease',
        30 => 'chicken_pox',
        31 => 'chronic_illness',
        32 => 'recent_fever',
        33 => 'pregnancy_history',
        34 => 'last_childbirth',
        35 => 'recent_miscarriage',
        36 => 'breastfeeding',
        37 => 'last_menstruation'
    ];
    return $fields[$count] ?? null;
}

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
            position: relative;
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

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
        }

        .approval-buttons {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .action-buttons-wrapper {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            padding: 20px 0;
            margin-top: 30px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 120px;
            transition: all 0.3s ease;
        }

        .btn-decline {
            background-color: #E31837;  /* Red Cross red */
            color: white;
            box-shadow: 0 2px 4px rgba(227, 24, 55, 0.2);
        }

        .btn-decline:hover {
            background-color: #C41230;  /* Darker Red Cross red */
            box-shadow: 0 4px 8px rgba(227, 24, 55, 0.3);
            transform: translateY(-1px);
        }

        .btn-approve {
            background-color: #007C2E;  /* Professional green */
            color: white;
            box-shadow: 0 2px 4px rgba(0, 124, 46, 0.2);
        }

        .btn-approve:hover {
            background-color: #006626;  /* Darker green */
            box-shadow: 0 4px 8px rgba(0, 124, 46, 0.3);
            transform: translateY(-1px);
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
            // Get the medical history data and donor sex from PHP
            const medicalHistoryData = <?php echo $medical_history_data ? json_encode($medical_history_data) : 'null'; ?>;
            const donorSex = <?php echo json_encode(strtolower($donor_sex)); ?>;
            const isMale = donorSex === 'male';
            
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
                    // Skip the female section header for male donors
                    if (q.includes("FOR FEMALE DONORS ONLY") && isMale) {
                        return;
                    }
                    // Skip rendering the section and its questions for male donors
                    if (isMale && q.includes("FOR FEMALE DONORS ONLY")) {
                        return;
                    }
                    document.write(`<div class='bold'>${q}</div>`);
                } else {
                    // Skip female-specific questions (q33-q37) for male donors
                    if (isMale && count >= 33 && count <= 37) {
                        count++;
                        return;
                    }

                    // Get the field name based on the data structure
                    const getFieldName = (count) => {
                        const fields = {
                            1: 'feels_well',
                            2: 'previously_refused',
                            3: 'testing_purpose_only',
                            4: 'understands_transmission_risk',
                            5: 'recent_alcohol_consumption',
                            6: 'recent_aspirin',
                            7: 'recent_medication',
                            8: 'recent_donation',
                            9: 'zika_travel',
                            10: 'zika_contact',
                            11: 'zika_sexual_contact',
                            12: 'blood_transfusion',
                            13: 'surgery_dental',
                            14: 'tattoo_piercing',
                            15: 'risky_sexual_contact',
                            16: 'unsafe_sex',
                            17: 'hepatitis_contact',
                            18: 'imprisonment',
                            19: 'uk_europe_stay',
                            20: 'foreign_travel',
                            21: 'drug_use',
                            22: 'clotting_factor',
                            23: 'positive_disease_test',
                            24: 'malaria_history',
                            25: 'std_history',
                            26: 'cancer_blood_disease',
                            27: 'heart_disease',
                            28: 'lung_disease',
                            29: 'kidney_disease',
                            30: 'chicken_pox',
                            31: 'chronic_illness',
                            32: 'recent_fever',
                            33: 'pregnancy_history',
                            34: 'last_childbirth',
                            35: 'recent_miscarriage',
                            36: 'breastfeeding',
                            37: 'last_menstruation'
                        };
                        return fields[count];
                    };

                    const fieldName = getFieldName(count);
                    const value = medicalHistoryData ? medicalHistoryData[fieldName] : null;
                    const remarks = medicalHistoryData ? medicalHistoryData[fieldName + '_remarks'] : null;

                    document.write(`<div class='cell number'>${count}</div>`);
                    document.write(`<div class='cell'>${q}</div>`);
                    document.write(`
                        <div class='cell checkbox'>
                            <label class="blood-bag-option">
                                <input type='radio' name='q${count}' value='Yes' ${value === true ? 'checked' : ''} required>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class='cell checkbox'>
                            <label class="blood-bag-option">
                                <input type='radio' name='q${count}' value='No' ${value === false ? 'checked' : ''} required>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class='cell'>
                            <select class="medical-history-remarks" name='q${count}_remarks' required>
                                ${remarksOptions[count].map(option => 
                                    `<option value="${option}" ${remarks === option ? 'selected' : ''}>${option}</option>`
                                ).join('')}
                            </select>
                        </div>
                    `);
                    count++;
                }
            });

            // After all questions are rendered, add the action buttons
            document.write(`
                <div class="action-buttons-wrapper">
                    <div class="action-buttons">
                        <button type="submit" name="action" value="decline" class="btn btn-decline" onclick="return confirmAction('decline');">
                            DECLINE
                        </button>
                        <button type="submit" name="action" value="approve" class="btn btn-approve" onclick="return confirmAction('approve');">
                            APPROVE
                        </button>
                    </div>
                </div>
            `);
        </script>
            
        <!-- Add a hidden input to store the action -->
        <input type="hidden" name="action" id="selectedAction" value="">
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

        // Remove any existing submit buttons
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtns = document.querySelectorAll('input[type="submit"], button.submit-btn');
            submitBtns.forEach(btn => {
                if (btn.parentElement) {
                    btn.parentElement.remove();
                }
            });

            // Hide the female section completely for male donors
            if (isMale) {
                const femaleSections = document.querySelectorAll('div.bold');
                femaleSections.forEach(section => {
                    if (section.textContent.includes('FOR FEMALE DONORS ONLY')) {
                        section.style.display = 'none';
                        let nextElement = section.nextElementSibling;
                        while (nextElement && !nextElement.classList.contains('bold')) {
                            nextElement.style.display = 'none';
                            nextElement = nextElement.nextElementSibling;
                        }
                    }
                });
            }
        });

        // Function to handle button clicks
        function confirmAction(action) {
            console.log('Button clicked:', action);
            document.getElementById('selectedAction').value = action;
            return true;
        }

        // Add form submission handler
        document.getElementById('medicalHistoryForm').addEventListener('submit', function(e) {
            const action = document.getElementById('selectedAction').value;
            console.log('Form submitting with action:', action);
        });
    </script>
</body>
</html>