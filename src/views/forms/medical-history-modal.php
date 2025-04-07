<?php
// Start the session to maintain state
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History Modal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f0f0;
        }
        
        .modal {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            width: 800px;
            max-width: 90%;
            position: relative;
            overflow: hidden;
        }
        
        .modal-header {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #fff;
            position: relative;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        
        .modal-title {
            color: #9c0000;
            font-size: 26px;
            font-weight: bold;
            margin: 0;
            text-align: center;
            width: 100%;
        }
        
        .close-button {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #888;
            position: absolute;
            right: 15px;
            top: 12px;
        }
        
        .modal-body {
            padding: 20px;
            background-color: #fff;
        }
        
        .step-indicators {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            gap: 0;
            max-width: 460px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: white;
            color: #666;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #ddd;
            font-weight: bold;
            position: relative;
            z-index: 2;
            margin: 0;
            font-size: 16px;
        }
        
        .step.active, .step.completed {
            background-color: #9c0000;
            color: white;
            border-color: #9c0000;
        }
        
        .step-connector {
            height: 1px;
            background-color: #ddd;
            width: 40px;
            flex-grow: 0;
            margin: 0;
            padding: 0;
        }
        
        .step-connector.active {
            background-color: #9c0000;
        }
        
        .step-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
            color: #9c0000;
            font-size: 22px;
        }
        
        .step-description {
            text-align: center;
            margin-bottom: 20px;
            font-style: italic;
            color: #666;
            font-size: 13px;
        }
        
        .form-group {
            display: grid;
            grid-template-columns: 0.5fr 4fr 0.75fr 0.75fr 2fr;
            gap: 5px;
            margin-bottom: 5px;
            align-items: center;
            width: 100%;
        }
        
        .form-header {
            font-weight: bold;
            text-align: center;
            background-color: #9c0000;
            color: white;
            padding: 8px 5px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            height: 36px;
            font-size: 14px;
        }
        
        .form-section-title {
            grid-column: 1 / span 5;
            font-weight: bold;
            background-color: #f5f5f5;
            padding: 10px;
            margin-top: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        
        .question-number {
            text-align: center;
            font-weight: bold;
            padding: 5px 0;
            font-size: 16px;
        }
        
        .question-text {
            padding: 5px 8px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .radio-cell {
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 4px 0;
        }
        
        .radio-container {
            position: relative;
            cursor: pointer;
            display: inline-block;
            margin: 0 auto;
            width: 20px;
            height: 20px;
            line-height: 1;
        }
        
        .radio-container input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 20px;
            width: 20px;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 3px;
            box-sizing: border-box;
        }
        
        .radio-container:hover input ~ .checkmark {
            background-color: #f9f9f9;
        }
        
        /* Checked state - red background with white checkmark */
        .radio-container input[type="radio"]:checked ~ .checkmark {
            background-color: #9c0000;
            border-color: #9c0000;
        }
        
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }
        
        .radio-container input[type="radio"]:checked ~ .checkmark:after {
            display: block;
            left: 7px;
            top: 3px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .remarks-cell {
            padding: 2px;
        }
        
        .remarks-input {
            width: 100%;
            padding: 3px 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            height: 28px;
            font-size: 14px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            padding: 12px 15px;
            border-top: 1px solid #f0f0f0;
            background-color: #fff;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        
        .prev-button,
        .next-button,
        .submit-button {
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
        }
        
        .prev-button {
            background-color: #f5f5f5;
            color: #666;
            border: 1px solid #ddd;
            margin-right: auto;
            display: none;
        }
        
        .next-button,
        .submit-button {
            background-color: #9c0000;
            color: white;
            border: none;
        }
        
        .form-step {
            display: none;
        }
        
        .form-step.active {
            display: block;
        }
        
        /* Style for validation error messages */
        .error-message {
            color: #9c0000;
            font-size: 14px;
            margin: 15px auto;
            text-align: center;
            font-weight: bold;
            display: none;
            max-width: 80%;
        }
        
        /* Style for highlighting unanswered questions */
        .question-highlight {
            background-color: rgba(156, 0, 0, 0.1);
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Medical History</h2>
            <button class="close-button">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="step-indicators">
                <div class="step active" id="step1" data-step="1">1</div>
                <div class="step-connector active" id="line1-2"></div>
                <div class="step" id="step2" data-step="2">2</div>
                <div class="step-connector" id="line2-3"></div>
                <div class="step" id="step3" data-step="3">3</div>
                <div class="step-connector" id="line3-4"></div>
                <div class="step" id="step4" data-step="4">4</div>
                <div class="step-connector" id="line4-5"></div>
                <div class="step" id="step5" data-step="5">5</div>
                <div class="step-connector" id="line5-6"></div>
                <div class="step" id="step6" data-step="6">6</div>
            </div>
            
            <form id="medicalHistoryForm" method="post" action="process_medical_history.php">
                <!-- Step 1: Health & Risk Assessment -->
                <div class="form-step active" data-step="1">
                    <div class="step-title">HEALTH & RISK ASSESSMENT:</div>
                    <div class="step-description">Tick the appropriate answer.</div>
                    
                    <div class="form-group">
                        <div class="form-header">#</div>
                        <div class="form-header">Question</div>
                        <div class="form-header">YES</div>
                        <div class="form-header">NO</div>
                        <div class="form-header">REMARKS</div>
                        
                        <div class="question-number">1</div>
                        <div class="question-text">Do you feel well and healthy today?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q1" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q1" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q1_remarks">
                                <option value="None">None</option>
                                <option value="Feeling Unwell">Feeling Unwell</option>
                                <option value="Fatigue">Fatigue</option>
                                <option value="Fever">Fever</option>
                                <option value="Other Health Issues">Other Health Issues</option>
                            </select>
                        </div>
                        
                        <div class="question-number">2</div>
                        <div class="question-text">Have you ever been refused as a blood donor or told not to donate blood for any reasons?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q2" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q2" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q2_remarks">
                                <option value="None">None</option>
                                <option value="Low Hemoglobin">Low Hemoglobin</option>
                                <option value="Medical Condition">Medical Condition</option>
                                <option value="Recent Surgery">Recent Surgery</option>
                                <option value="Other Refusal Reason">Other Refusal Reason</option>
                            </select>
                        </div>
                        
                        <div class="question-number">3</div>
                        <div class="question-text">Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q3" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q3" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q3_remarks">
                                <option value="None">None</option>
                                <option value="HIV Test">HIV Test</option>
                                <option value="Hepatitis Test">Hepatitis Test</option>
                                <option value="Other Test Purpose">Other Test Purpose</option>
                            </select>
                        </div>
                        
                        <div class="question-number">4</div>
                        <div class="question-text">Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q4" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q4" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q4_remarks">
                                <option value="None">None</option>
                                <option value="Understood">Understood</option>
                                <option value="Needs More Information">Needs More Information</option>
                            </select>
                        </div>
                        
                        <div class="question-number">5</div>
                        <div class="question-text">Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q5" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q5" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q5_remarks">
                                <option value="None">None</option>
                                <option value="Beer">Beer</option>
                                <option value="Wine">Wine</option>
                                <option value="Liquor">Liquor</option>
                                <option value="Multiple Types">Multiple Types</option>
                            </select>
                        </div>
                        
                        <div class="question-number">6</div>
                        <div class="question-text">In the last 3 DAYS have you taken aspirin?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q6" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q6" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q6_remarks">
                                <option value="None">None</option>
                                <option value="Pain Relief">Pain Relief</option>
                                <option value="Fever">Fever</option>
                                <option value="Other Medication Purpose">Other Medication Purpose</option>
                            </select>
                        </div>
                        
                        <div class="question-number">7</div>
                        <div class="question-text">In the past 4 WEEKS have you taken any medications and/or vaccinations?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q7" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q7" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q7_remarks">
                                <option value="None">None</option>
                                <option value="Antibiotics">Antibiotics</option>
                                <option value="Vitamins">Vitamins</option>
                                <option value="Vaccines">Vaccines</option>
                                <option value="Other Medications">Other Medications</option>
                            </select>
                        </div>
                        
                        <div class="question-number">8</div>
                        <div class="question-text">In the past 3 MONTHS have you donated whole blood, platelets or plasma?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q8" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q8" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q8_remarks">
                                <option value="None">None</option>
                                <option value="Red Cross Donation">Red Cross Donation</option>
                                <option value="Hospital Donation">Hospital Donation</option>
                                <option value="Other Donation Type">Other Donation Type</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: In the past 6 months have you -->
                <div class="form-step" data-step="2">
                    <div class="step-title">IN THE PAST 6 MONTHS HAVE YOU:</div>
                    <div class="step-description">Tick the appropriate answer.</div>
                    
                    <div class="form-group">
                        <div class="form-header">#</div>
                        <div class="form-header">Question</div>
                        <div class="form-header">YES</div>
                        <div class="form-header">NO</div>
                        <div class="form-header">REMARKS</div>
                        
                        <div class="question-number">9</div>
                        <div class="question-text">Been to any places in the Philippines or countries infected with ZIKA Virus?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q9" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q9" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q9_remarks">
                                <option value="None">None</option>
                                <option value="Local Travel">Local Travel</option>
                                <option value="International Travel">International Travel</option>
                                <option value="Specific Location">Specific Location</option>
                            </select>
                        </div>
                        
                        <div class="question-number">10</div>
                        <div class="question-text">Had sexual contact with a person who was confirmed to have ZIKA Virus Infection?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q10" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q10" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q10_remarks">
                                <option value="None">None</option>
                                <option value="Direct Contact">Direct Contact</option>
                                <option value="Indirect Contact">Indirect Contact</option>
                                <option value="Suspected Case">Suspected Case</option>
                            </select>
                        </div>
                        
                        <div class="question-number">11</div>
                        <div class="question-text">Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q11" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q11" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q11_remarks">
                                <option value="None">None</option>
                                <option value="Partner Travel History">Partner Travel History</option>
                                <option value="Unknown Exposure">Unknown Exposure</option>
                                <option value="Other Risk">Other Risk</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: In the past 12 months have you -->
                <div class="form-step" data-step="3">
                    <div class="step-title">IN THE PAST 12 MONTHS HAVE YOU:</div>
                    <div class="step-description">Tick the appropriate answer.</div>
                    
                    <div class="form-group">
                        <div class="form-header">#</div>
                        <div class="form-header">Question</div>
                        <div class="form-header">YES</div>
                        <div class="form-header">NO</div>
                        <div class="form-header">REMARKS</div>
                        
                        <div class="question-number">12</div>
                        <div class="question-text">Received blood, blood products and/or had tissue/organ transplant or graft?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q12" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q12" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q12_remarks">
                                <option value="None">None</option>
                                <option value="Blood Transfusion">Blood Transfusion</option>
                                <option value="Organ Transplant">Organ Transplant</option>
                                <option value="Other Procedure">Other Procedure</option>
                            </select>
                        </div>
                        
                        <div class="question-number">13</div>
                        <div class="question-text">Had surgical operation or dental extraction?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q13" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q13" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q13_remarks">
                                <option value="None">None</option>
                                <option value="Major Surgery">Major Surgery</option>
                                <option value="Minor Surgery">Minor Surgery</option>
                                <option value="Dental Work">Dental Work</option>
                            </select>
                        </div>
                        
                        <div class="question-number">14</div>
                        <div class="question-text">Had a tattoo applied, ear and body piercing, acupuncture, needle stick injury or accidental contact with blood?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q14" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q14" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q14_remarks">
                                <option value="None">None</option>
                                <option value="Tattoo">Tattoo</option>
                                <option value="Piercing">Piercing</option>
                                <option value="Acupuncture">Acupuncture</option>
                                <option value="Blood Exposure">Blood Exposure</option>
                            </select>
                        </div>
                        
                        <div class="question-number">15</div>
                        <div class="question-text">Had sexual contact with high risks individuals or in exchange for material or monetary gain?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q15" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q15" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q15_remarks">
                                <option value="None">None</option>
                                <option value="High Risk Contact">High Risk Contact</option>
                                <option value="Multiple Partners">Multiple Partners</option>
                                <option value="Other Risk Factors">Other Risk Factors</option>
                            </select>
                        </div>
                        
                        <div class="question-number">16</div>
                        <div class="question-text">Engaged in unprotected, unsafe or casual sex?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q16" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q16" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q16_remarks">
                                <option value="None">None</option>
                                <option value="Unprotected Sex">Unprotected Sex</option>
                                <option value="Casual Contact">Casual Contact</option>
                                <option value="Other Risk Behavior">Other Risk Behavior</option>
                            </select>
                        </div>
                        
                        <div class="question-number">17</div>
                        <div class="question-text">Had jaundice/hepatitis/personal contact with person who had hepatitis?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q17" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q17" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q17_remarks">
                                <option value="None">None</option>
                                <option value="Personal History">Personal History</option>
                                <option value="Family Contact">Family Contact</option>
                                <option value="Other Exposure">Other Exposure</option>
                            </select>
                        </div>
                        
                        <div class="question-number">18</div>
                        <div class="question-text">Been incarcerated, jailed or imprisoned?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q18" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q18" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q18_remarks">
                                <option value="None">None</option>
                                <option value="Short Term">Short Term</option>
                                <option value="Long Term">Long Term</option>
                                <option value="Other Details">Other Details</option>
                            </select>
                        </div>
                        
                        <div class="question-number">19</div>
                        <div class="question-text">Spent time or have relatives in the United Kingdom or Europe?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q19" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q19" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q19_remarks">
                                <option value="None">None</option>
                                <option value="UK Stay">UK Stay</option>
                                <option value="Europe Stay">Europe Stay</option>
                                <option value="Duration of Stay">Duration of Stay</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Step 4: Have you ever -->
                <div class="form-step" data-step="4">
                    <div class="step-title">HAVE YOU EVER:</div>
                    <div class="step-description">Tick the appropriate answer.</div>
                    
                    <div class="form-group">
                        <div class="form-header">#</div>
                        <div class="form-header">Question</div>
                        <div class="form-header">YES</div>
                        <div class="form-header">NO</div>
                        <div class="form-header">REMARKS</div>
                        
                        <div class="question-number">20</div>
                        <div class="question-text">Travelled or lived outside of your place of residence or outside the Philippines?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q20" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q20" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q20_remarks">
                                <option value="None">None</option>
                                <option value="Local Travel">Local Travel</option>
                                <option value="International Travel">International Travel</option>
                                <option value="Duration">Duration</option>
                            </select>
                        </div>
                        
                        <div class="question-number">21</div>
                        <div class="question-text">Taken prohibited drugs (orally, by nose, or by injection)?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q21" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q21" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q21_remarks">
                                <option value="None">None</option>
                                <option value="Recreational">Recreational</option>
                                <option value="Medical">Medical</option>
                                <option value="Other Usage">Other Usage</option>
                            </select>
                        </div>
                        
                        <div class="question-number">22</div>
                        <div class="question-text">Used clotting factor concentrates?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q22" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q22" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q22_remarks">
                                <option value="None">None</option>
                                <option value="Treatment History">Treatment History</option>
                                <option value="Current Use">Current Use</option>
                                <option value="Other Details">Other Details</option>
                            </select>
                        </div>
                        
                        <div class="question-number">23</div>
                        <div class="question-text">Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q23" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q23" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q23_remarks">
                                <option value="None">None</option>
                                <option value="HIV">HIV</option>
                                <option value="Hepatitis">Hepatitis</option>
                                <option value="Syphilis">Syphilis</option>
                                <option value="Malaria">Malaria</option>
                            </select>
                        </div>
                        
                        <div class="question-number">24</div>
                        <div class="question-text">Had Malaria or Hepatitis in the past?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q24" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q24" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q24_remarks">
                                <option value="None">None</option>
                                <option value="Past Infection">Past Infection</option>
                                <option value="Treatment History">Treatment History</option>
                                <option value="Other Details">Other Details</option>
                            </select>
                        </div>
                        
                        <div class="question-number">25</div>
                        <div class="question-text">Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q25" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q25" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q25_remarks">
                                <option value="None">None</option>
                                <option value="Current Infection">Current Infection</option>
                                <option value="Past Treatment">Past Treatment</option>
                                <option value="Other Details">Other Details</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Step 5: Had any of the following -->
                <div class="form-step" data-step="5">
                    <div class="step-title">HAD ANY OF THE FOLLOWING:</div>
                    <div class="step-description">Tick the appropriate answer.</div>
                    
                    <div class="form-group">
                        <div class="form-header">#</div>
                        <div class="form-header">Question</div>
                        <div class="form-header">YES</div>
                        <div class="form-header">NO</div>
                        <div class="form-header">REMARKS</div>
                        
                        <div class="question-number">26</div>
                        <div class="question-text">Cancer, blood disease or bleeding disorder (haemophilia)?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q26" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q26" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q26_remarks">
                                <option value="None">None</option>
                                <option value="Cancer Type">Cancer Type</option>
                                <option value="Blood Disease">Blood Disease</option>
                                <option value="Bleeding Disorder">Bleeding Disorder</option>
                            </select>
                        </div>
                        
                        <div class="question-number">27</div>
                        <div class="question-text">Heart disease/surgery, rheumatic fever or chest pains?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q27" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q27" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q27_remarks">
                                <option value="None">None</option>
                                <option value="Heart Disease">Heart Disease</option>
                                <option value="Surgery History">Surgery History</option>
                                <option value="Current Treatment">Current Treatment</option>
                            </select>
                        </div>
                        
                        <div class="question-number">28</div>
                        <div class="question-text">Lung disease, tuberculosis or asthma?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q28" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q28" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q28_remarks">
                                <option value="None">None</option>
                                <option value="Active TB">Active TB</option>
                                <option value="Asthma">Asthma</option>
                                <option value="Other Respiratory Issues">Other Respiratory Issues</option>
                            </select>
                        </div>
                        
                        <div class="question-number">29</div>
                        <div class="question-text">Kidney disease, thyroid disease, diabetes, epilepsy?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q29" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q29" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q29_remarks">
                                <option value="None">None</option>
                                <option value="Kidney Disease">Kidney Disease</option>
                                <option value="Thyroid Issue">Thyroid Issue</option>
                                <option value="Diabetes">Diabetes</option>
                                <option value="Epilepsy">Epilepsy</option>
                            </select>
                        </div>
                        
                        <div class="question-number">30</div>
                        <div class="question-text">Chicken pox and/or cold sores?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q30" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q30" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q30_remarks">
                                <option value="None">None</option>
                                <option value="Recent Infection">Recent Infection</option>
                                <option value="Past Infection">Past Infection</option>
                                <option value="Other Details">Other Details</option>
                            </select>
                        </div>
                        
                        <div class="question-number">31</div>
                        <div class="question-text">Any other chronic medical condition or surgical operations?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q31" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q31" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q31_remarks">
                                <option value="None">None</option>
                                <option value="Condition Type">Condition Type</option>
                                <option value="Treatment Status">Treatment Status</option>
                                <option value="Other Details">Other Details</option>
                            </select>
                        </div>
                        
                        <div class="question-number">32</div>
                        <div class="question-text">Have you recently had rash and/or fever? Was/were this/these also associated with arthralgia or arthritis or conjunctivitis?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q32" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q32" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q32_remarks">
                                <option value="None">None</option>
                                <option value="Recent Fever">Recent Fever</option>
                                <option value="Rash">Rash</option>
                                <option value="Joint Pain">Joint Pain</option>
                                <option value="Eye Issues">Eye Issues</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Step 6: General Health History -->
                <div class="form-step" data-step="6">
                    <div class="step-title">GENERAL HEALTH HISTORY:</div>
                    <div class="step-description">Tick the appropriate answer.</div>
                    
                    <div class="form-group">
                        <div class="form-header">#</div>
                        <div class="form-header">Question</div>
                        <div class="form-header">YES</div>
                        <div class="form-header">NO</div>
                        <div class="form-header">REMARKS</div>
                        
                        <div class="question-number">33</div>
                        <div class="question-text">Have you been diagnosed with any chronic health conditions in the past year?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q33" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q33" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q33_remarks">
                                <option value="None">None</option>
                                <option value="Autoimmune Condition">Autoimmune Condition</option>
                                <option value="Metabolic Disorder">Metabolic Disorder</option>
                                <option value="Other Condition">Other Condition</option>
                            </select>
                        </div>
                        
                        <div class="question-number">34</div>
                        <div class="question-text">Have you had any major surgery in the past year?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q34" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q34" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q34_remarks">
                                <option value="None">None</option>
                                <option value="Less than 3 months ago">Less than 3 months ago</option>
                                <option value="3-6 months ago">3-6 months ago</option>
                                <option value="6-12 months ago">6-12 months ago</option>
                            </select>
                        </div>
                        
                        <div class="question-number">35</div>
                        <div class="question-text">Are you currently taking any medications regularly?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q35" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q35" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q35_remarks">
                                <option value="None">None</option>
                                <option value="Blood Pressure Medication">Blood Pressure Medication</option>
                                <option value="Anticoagulants">Anticoagulants</option>
                                <option value="Other Medication">Other Medication</option>
                            </select>
                        </div>
                        
                        <div class="question-number">36</div>
                        <div class="question-text">Have you ever had a severe allergic reaction requiring medical attention?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q36" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q36" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q36_remarks">
                                <option value="None">None</option>
                                <option value="Food Allergy">Food Allergy</option>
                                <option value="Drug Allergy">Drug Allergy</option>
                                <option value="Other Allergy">Other Allergy</option>
                            </select>
                        </div>
                        
                        <div class="question-number">37</div>
                        <div class="question-text">Do you have any family history of blood disorders?</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q37" value="Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q37" value="No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q37_remarks">
                                <option value="None">None</option>
                                <option value="Hemophilia">Hemophilia</option>
                                <option value="Thalassemia">Thalassemia</option>
                                <option value="Sickle Cell">Sickle Cell</option>
                                <option value="Other Blood Disorder">Other Blood Disorder</option>
                            </select>
                        </div>
                    </div>
                </div>
            </form>
            
            <div class="error-message" id="validationError">Please answer all questions before proceeding to the next step.</div>
        </div>
        
        <div class="modal-footer">
            <button class="prev-button" id="prevButton">&#8592; Previous</button>
            <button class="next-button" id="nextButton">Next →</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get form and button elements
            const form = document.getElementById('medicalHistoryForm');
            const prevButton = document.getElementById('prevButton');
            const nextButton = document.getElementById('nextButton');
            const closeButton = document.querySelector('.close-button');
            const errorMessage = document.getElementById('validationError');
            
            // Get all form steps and step indicators
            const formSteps = document.querySelectorAll('.form-step');
            const stepIndicators = document.querySelectorAll('.step');
            const stepConnectors = document.querySelectorAll('.step-connector');
            
            // Current step
            let currentStep = 1;
            const totalSteps = formSteps.length;
            
            // Function to clear any question highlights
            function clearQuestionHighlights() {
                const questions = document.querySelectorAll('.question-text');
                questions.forEach(question => {
                    question.parentElement.classList.remove('question-highlight');
                });
            }
            
            // Function to validate the current step
            function validateCurrentStep() {
                const currentStepElement = document.querySelector(`.form-step[data-step="${currentStep}"]`);
                if (!currentStepElement) return true;
                
                // Clear any previous highlights
                clearQuestionHighlights();
                
                // Get all radio buttons in the current step
                const questionGroups = {};
                
                // Find all radio buttons in the current step and group them by name
                const radioButtons = currentStepElement.querySelectorAll('input[type="radio"]');
                radioButtons.forEach(radio => {
                    if (!questionGroups[radio.name]) {
                        questionGroups[radio.name] = [];
                    }
                    questionGroups[radio.name].push(radio);
                });
                
                // Check if each question group has at least one radio button checked
                let allQuestionsAnswered = true;
                const unansweredQuestions = [];
                
                for (const name in questionGroups) {
                    const groupChecked = questionGroups[name].some(radio => radio.checked);
                    if (!groupChecked) {
                        allQuestionsAnswered = false;
                        unansweredQuestions.push(name);
                    }
                }
                
                // Update error message with the number of unanswered questions
                if (unansweredQuestions.length > 0) {
                    const questionText = unansweredQuestions.length === 1 ? 'question' : 'questions';
                    errorMessage.textContent = `Please answer all ${questionText} before proceeding. You have ${unansweredQuestions.length} ${questionText} remaining.`;
                    
                    // Highlight unanswered questions
                    unansweredQuestions.forEach(name => {
                        const firstRadio = document.querySelector(`input[name="${name}"]`);
                        if (firstRadio) {
                            // Find the parent row to highlight
                            const row = firstRadio.closest('.form-group');
                            if (row) {
                                const questionTextElem = row.querySelector('.question-text');
                                if (questionTextElem) {
                                    questionTextElem.parentElement.classList.add('question-highlight');
                                }
                            }
                        }
                    });
                }
                
                return allQuestionsAnswered;
            }
            
            // Function to update step display
            function updateStepDisplay() {
                // Hide all steps
                formSteps.forEach(step => {
                    step.classList.remove('active');
                });
                
                // Show current step
                const activeStep = document.querySelector(`.form-step[data-step="${currentStep}"]`);
                if (activeStep) {
                    activeStep.classList.add('active');
                }
                
                // Update step indicators
                stepIndicators.forEach(indicator => {
                    const step = parseInt(indicator.getAttribute('data-step'));
                    
                    if (step < currentStep) {
                        indicator.classList.add('completed');
                        indicator.classList.add('active');
                    } else if (step === currentStep) {
                        indicator.classList.add('active');
                        indicator.classList.remove('completed');
                    } else {
                        indicator.classList.remove('active');
                        indicator.classList.remove('completed');
                    }
                });
                
                // Update step connectors
                stepConnectors.forEach((connector, index) => {
                    if (index + 1 < currentStep) {
                        connector.classList.add('active');
                    } else {
                        connector.classList.remove('active');
                    }
                });
                
                // Update button text for the last step
                if (currentStep === totalSteps) {
                    nextButton.textContent = 'Submit';
                } else {
                    nextButton.textContent = 'Next →';
                }
                
                // Show/hide previous button
                if (currentStep === 1) {
                    prevButton.style.display = 'none';
                } else {
                    prevButton.style.display = 'block';
                }
                
                // Hide error message and clear highlights when changing steps
                errorMessage.style.display = 'none';
                clearQuestionHighlights();
            }
            
            // Handle next button click
            nextButton.addEventListener('click', function() {
                // Validate current step
                if (validateCurrentStep()) {
                    // If validation passes and not on the last step, go to next step
                    if (currentStep < totalSteps) {
                        currentStep++;
                        updateStepDisplay();
                    } else {
                        // Submit the form if on the last step
                        form.submit();
                    }
                    // Hide error message
                    errorMessage.style.display = 'none';
                } else {
                    // Show error message if validation fails
                    errorMessage.style.display = 'block';
                    // Scroll to the top of the form to show the error message
                    form.scrollIntoView({ behavior: 'smooth' });
                }
            });
            
            // Handle previous button click
            prevButton.addEventListener('click', function() {
                if (currentStep > 1) {
                    currentStep--;
                    updateStepDisplay();
                }
            });
            
            // Handle close button click
            closeButton.addEventListener('click', function() {
                // You can add custom close behavior here
                // For now, let's just log it
                console.log('Modal closed');
                window.close();
            });
            
            // Add event listeners to radio buttons to handle checked state
            document.addEventListener('change', function(e) {
                if (e.target.type === 'radio') {
                    const questionRow = e.target.closest('.form-group');
                    if (questionRow) {
                        const questionTextElem = questionRow.querySelector('.question-text');
                        if (questionTextElem) {
                            questionTextElem.parentElement.classList.remove('question-highlight');
                        }
                    }
                    
                    // Re-validate to update error message and highlights
                    if (errorMessage.style.display === 'block') {
                        if (validateCurrentStep()) {
                            errorMessage.style.display = 'none';
                        }
                    }
                }
            });
            
            // Initialize display
            updateStepDisplay();
        });
    </script>
</body>
</html> 