<?php
// Start output buffering to catch any unexpected output
ob_start();

// Suppress all error output to ensure clean response
error_reporting(0);
ini_set('display_errors', 0);

// Debug: Log that we're starting
error_log("Medical History Modal: Starting for donor_id: " . ($_GET['donor_id'] ?? 'not set'));

// Include database connection
// Try multiple paths to handle different include contexts
$db_conn_paths = [
    __DIR__ . '/../../../assets/conn/db_conn.php',  // From src/views/forms/
    dirname(dirname(dirname(__DIR__))) . '/assets/conn/db_conn.php',  // Absolute from project root
    '../../../assets/conn/db_conn.php'  // Original relative path
];

$db_conn_loaded = false;
foreach ($db_conn_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_conn_loaded = true;
        break;
    }
}

// If database connection constants are not defined, try to load from a known location
if (!$db_conn_loaded && (!defined('SUPABASE_URL') || !defined('SUPABASE_API_KEY'))) {
    // Try to find the db_conn.php file by searching up the directory tree
    $current_dir = __DIR__;
    for ($i = 0; $i < 5; $i++) {
        $test_path = $current_dir . str_repeat('/..', $i) . '/assets/conn/db_conn.php';
        if (file_exists($test_path)) {
            require_once $test_path;
            $db_conn_loaded = true;
            break;
        }
    }
}

// Get donor_id from request
$donor_id = isset($_GET['donor_id']) ? $_GET['donor_id'] : null;

if (!$donor_id) {
    // Clear any existing output
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo '<div class="alert alert-danger">Missing donor_id parameter</div>';
    // Don't flush here - let the parent script handle output buffering
    // Use return instead of exit() when included from another file
    // This allows the parent script to handle the error gracefully
    return;
}

// Check if this is view-only mode (for approved donors)
$view_only = isset($_GET['view_only']) && $_GET['view_only'] == '1';

// Set user role for admin context
$user_role = 'admin';

// Fetch existing medical history data and donor's sex
$medical_history_data = null;
$donor_sex = null;

// First fetch donor's sex from donor_form table
$ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . $donor_id . '&select=sex');

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
    error_log("Donor sex query response for donor_id $donor_id: " . $response);
    if (!empty($donor_data)) {
        $donor_sex = strtolower($donor_data[0]['sex']);
        error_log("Donor sex found: " . $donor_sex);
    } else {
        error_log("No donor data found for donor_id: $donor_id");
    }
} else {
    error_log("Failed to fetch donor sex data. HTTP code: $http_code");
}

// Then fetch medical history data
$ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id);

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
    error_log("Medical history query response for donor_id $donor_id: " . $response);
    if (!empty($data) && is_array($data)) {
        $medical_history_data = $data[0];
        error_log("Medical history data found: " . json_encode($medical_history_data));
    } else {
        error_log("No medical history data found for donor_id: $donor_id - creating empty structure");
        // Create empty medical history data structure for new donors
        $medical_history_data = [];
    }
} else {
    error_log("Failed to fetch medical history data. HTTP code: $http_code, Response: " . $response);
    // Create empty medical history data structure if fetch fails
    $medical_history_data = [];
}
?>

<style>
    /* Step Indicators */
    .step-indicators {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 40px;
        position: relative;
        gap: 0;
        max-width: 460px;
        margin-left: auto;
        margin-right: auto;
        padding: 20px 0;
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
    
    /* Form Steps */
    .form-step {
        display: none;
    }
    
    .form-step.active {
        display: block;
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
    
    /* Form Layout */
    .form-container {
        display: grid;
        grid-template-columns: 60px 1fr 80px 80px 200px;
        gap: 1px;
        width: 100%;
        margin-bottom: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
        background-color: #ddd;
    }
    
    .form-group {
        display: contents;
    }
    
    .form-header {
        font-weight: bold;
        text-align: center;
        background-color: #9c0000;
        color: white;
        padding: 12px 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        min-height: 50px;
        font-size: 14px;
        word-wrap: break-word;
    }
    
    .question-number {
        text-align: center;
        font-weight: bold;
        padding: 12px 8px;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 60px;
        background-color: #fff;
        box-sizing: border-box;
    }
    
    .question-text {
        padding: 12px 15px;
        font-size: 14px;
        line-height: 1.4;
        display: flex;
        align-items: center;
        min-height: 60px;
        background-color: #fff;
        box-sizing: border-box;
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        text-align: left;
    }
    
    .radio-cell {
        text-align: center;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 12px 8px;
        min-height: 60px;
        background-color: #fff;
        box-sizing: border-box;
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
    
    .radio-container input[type="radio"]:checked ~ .checkmark {
        background-color: #9c0000;
        border-color: #9c0000;
    }
    
    .radio-container:hover .checkmark {
        border-color: #9c0000;
        box-shadow: 0 0 0 2px rgba(156, 0, 0, 0.1);
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
        padding: 12px 10px;
        display: flex;
        align-items: center;
        min-height: 60px;
        background-color: #fff;
        box-sizing: border-box;
    }
    
    .remarks-input {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        height: 36px;
        font-size: 14px;
        background-color: #fff;
        cursor: pointer;
    }
    
    /* Modal Footer */
    .modal-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-top: 1px solid #e0e0e0;
        margin-top: 30px;
        background-color: #f8f9fa;
    }
    
    .footer-left {
        flex: 1;
    }
    
        .footer-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }
    
        .prev-button,
     .next-button,
     .submit-button,
     .edit-button {
         padding: 8px 20px;
         border-radius: 5px;
         cursor: pointer;
         font-weight: bold;
         font-size: 15px;
         z-index: 10;
         transition: background-color 0.3s ease;
         box-sizing: border-box;
         min-width: 110px; /* keep stable width */
         display: inline-flex;
         align-items: center;
         justify-content: center;
         line-height: 1.2;
         min-height: 44px; /* stabilize height */
     }
     
           .edit-button {
          background-color: white;
          color: #007bff;
          border: 2px solid #007bff;
          display: flex;
          align-items: center;
          gap: 5px;
          font-weight: bold;
          padding: 10px 20px;
          border-radius: 6px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
          transition: all 0.3s ease;
      }
      
      .edit-button:hover {
          background-color: #007bff;
          color: white;
          transform: translateY(-1px);
          box-shadow: 0 4px 8px rgba(0,0,0,0.15);
      }
     
     .edit-button::before {
         content: "âœï¸";
         font-size: 14px;
     }
     
     .edit-button.no-icon::before {
         content: none;
     }
     
           .save-button {
          background-color: #28a745;
          color: white;
          border: none;
          font-weight: bold;
          padding: 10px 25px;
          border-radius: 6px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
          transition: all 0.3s ease;
      }
      
      .save-button:hover {
          background-color: #218838;
          transform: translateY(-1px);
          box-shadow: 0 4px 8px rgba(0,0,0,0.15);
      }
    
    .prev-button {
        background-color: #f5f5f5;
        color: #666;
        border: 1px solid #ddd;
        margin-right: 10px;
    }
    
    .prev-button:hover {
        background-color: #e0e0e0;
    }
    
    .next-button,
    .submit-button {
        background-color: #9c0000;
        color: white;
        border: none;
    }
    
    .next-button:hover,
    .submit-button:hover {
        background-color: #7e0000;
    }
    
    /* Error styling */
    .error-message {
        color: #9c0000;
        font-size: 14px;
        margin: 15px auto;
        text-align: center;
        font-weight: bold;
        display: none;
        max-width: 80%;
    }
    
    .question-container.error {
        background-color: rgba(255, 0, 0, 0.05);
        border-left: 3px solid #ff0000;
        padding-left: 10px;
        animation: shake 0.5s ease-in-out;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
         .question-highlight {
         color: #ff0000 !important;
         font-weight: bold;
     }
     
     /* Custom confirmation modal styling */
     #customConfirmModal .modal-content {
         border-radius: 10px;
         border: none;
         box-shadow: 0 10px 30px rgba(0,0,0,0.3);
     }
     
     #customConfirmModal .modal-header {
         border-radius: 10px 10px 0 0;
         border-bottom: 1px solid rgba(255,255,255,0.2);
     }
     
     #customConfirmModal .modal-body {
         padding: 25px;
         font-size: 16px;
         line-height: 1.5;
     }
     
     #customConfirmModal .modal-footer {
         border-top: 1px solid #e9ecef;
         padding: 20px 25px;
     }
     
     #customConfirmModal .btn {
         padding: 10px 25px;
         border-radius: 6px;
         font-weight: 600;
         transition: all 0.3s ease;
     }
     
     #customConfirmModal .btn-primary {
         background-color: #9c0000;
         border-color: #9c0000;
     }
     
     #customConfirmModal .btn-primary:hover {
         background-color: #8b0000;
         border-color: #8b0000;
         transform: translateY(-1px);
     }
</style>

<div class="step-indicators" id="modalStepIndicators">
    <div class="step active" id="modalStep1" data-step="1">1</div>
    <div class="step-connector active" id="modalLine1-2"></div>
    <div class="step" id="modalStep2" data-step="2">2</div>
    <div class="step-connector" id="modalLine2-3"></div>
    <div class="step" id="modalStep3" data-step="3">3</div>
    <div class="step-connector" id="modalLine3-4"></div>
    <div class="step" id="modalStep4" data-step="4">4</div>
    <div class="step-connector" id="modalLine4-5"></div>
    <div class="step" id="modalStep5" data-step="5">5</div>
    <div class="step-connector" id="modalLine5-6"></div>
    <div class="step" id="modalStep6" data-step="6">6</div>
</div>

<form method="POST" action="medical-history-process-admin.php" id="modalMedicalHistoryForm">
    <input type="hidden" name="donor_id" value="<?php echo htmlspecialchars($donor_id); ?>">
    <input type="hidden" name="action" id="modalSelectedAction" value="">
    
    <!-- Step 1: Health & Risk Assessment -->
    <div class="form-step active" data-step="1">
        <div class="step-title">HEALTH & RISK ASSESSMENT:</div>
        <div class="step-description">Tick the appropriate answer.</div>
        
        <div class="form-container" data-step-container="1">
            <div class="form-header">#</div>
            <div class="form-header">Question</div>
            <div class="form-header">YES</div>
            <div class="form-header">NO</div>
            <div class="form-header">REMARKS</div>
        </div>
    </div>
    
    <!-- Step 2: In the past 6 months have you -->
    <div class="form-step" data-step="2">
        <div class="step-title">IN THE PAST 6 MONTHS HAVE YOU:</div>
        <div class="step-description">Tick the appropriate answer.</div>
        
        <div class="form-container" data-step-container="2">
            <div class="form-header">#</div>
            <div class="form-header">Question</div>
            <div class="form-header">YES</div>
            <div class="form-header">NO</div>
            <div class="form-header">REMARKS</div>
        </div>
    </div>
    
    <!-- Step 3: In the past 12 months have you -->
    <div class="form-step" data-step="3">
        <div class="step-title">IN THE PAST 12 MONTHS HAVE YOU:</div>
        <div class="step-description">Tick the appropriate answer.</div>
        
        <div class="form-container" data-step-container="3">
            <div class="form-header">#</div>
            <div class="form-header">Question</div>
            <div class="form-header">YES</div>
            <div class="form-header">NO</div>
            <div class="form-header">REMARKS</div>
        </div>
    </div>
    
    <!-- Step 4: Have you ever -->
    <div class="form-step" data-step="4">
        <div class="step-title">HAVE YOU EVER:</div>
        <div class="step-description">Tick the appropriate answer.</div>
        
        <div class="form-container" data-step-container="4">
            <div class="form-header">#</div>
            <div class="form-header">Question</div>
            <div class="form-header">YES</div>
            <div class="form-header">NO</div>
            <div class="form-header">REMARKS</div>
        </div>
    </div>
    
    <!-- Step 5: Had any of the following -->
    <div class="form-step" data-step="5">
        <div class="step-title">HAD ANY OF THE FOLLOWING:</div>
        <div class="step-description">Tick the appropriate answer.</div>
        
        <div class="form-container" data-step-container="5">
            <div class="form-header">#</div>
            <div class="form-header">Question</div>
            <div class="form-header">YES</div>
            <div class="form-header">NO</div>
            <div class="form-header">REMARKS</div>
        </div>
    </div>
    
    <!-- Step 6: For Female Donors Only -->
    <div class="form-step" data-step="6">
        <div class="step-title">FOR FEMALE DONORS ONLY:</div>
        <div class="step-description">Tick the appropriate answer.</div>
        
        <div class="form-container" data-step-container="6">
            <div class="form-header">#</div>
            <div class="form-header">Question</div>
            <div class="form-header">YES</div>
            <div class="form-header">NO</div>
            <div class="form-header">REMARKS</div>
        </div>
    </div>
    
         <div class="error-message" id="modalValidationError">Please answer all questions before proceeding to the next step.</div>
 </form>
 
 <!-- Custom Confirmation Modal (namespaced) -->
 <div class="modal fade" id="mhCustomConfirmModal" tabindex="-1" aria-labelledby="mhCustomConfirmModalLabel" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered">
         <div class="modal-content">
             <div class="modal-header" style="background: #9c0000; color: white;">
                 <h5 class="modal-title" id="mhCustomConfirmModalLabel">
                     <i class="fas fa-question-circle me-2"></i>
                     Confirm Action
                 </h5>
                 <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
             </div>
             <div class="modal-body">
                 <p id="mhCustomConfirmMessage">Are you sure you want to proceed?</p>
             </div>
             <div class="modal-footer">
                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                 <button type="button" class="btn btn-primary" id="mhCustomConfirmYes">Yes, Proceed</button>
             </div>
         </div>
     </div>
 </div>

<div class="modal-footer">
    <div class="footer-left"></div>
    <div class="footer-right">
        <button class="prev-button" id="modalPrevButton" style="display: none;">
            <i class="fas fa-arrow-left me-1"></i>Previous
        </button>
        <button class="next-button" id="modalNextButton">
            Next <i class="fas fa-arrow-right ms-1"></i>
        </button>
        <?php if (!$view_only): ?>
        <button class="btn btn-success" id="modalSubmitButton" style="display: none; margin-left: 10px;">
            <i class="fas fa-check me-2"></i>Submit Medical History
        </button>
        <button class="btn btn-danger" id="modalDeclineButton" style="display: none; margin-left: 10px;">
            <i class="fas fa-times me-2"></i>Decline Medical History
        </button>
        <button class="btn btn-success" id="modalApproveButton" style="display: none; margin-left: 10px;">
            <i class="fas fa-check-circle me-2"></i>Approve Medical History
        </button>
        <?php else: ?>
        <!-- View-only mode: Navigation buttons remain, action buttons hidden by JavaScript -->
        <?php endif; ?>
    </div>
</div>

<!-- Decline Medical History Confirmation Modal -->
<div class="modal fade" id="declineMedicalHistoryModal" tabindex="-1" aria-labelledby="declineMedicalHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="declineMedicalHistoryModalLabel">
                    <i class="fas fa-times-circle me-2"></i>
                    Decline Medical History?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <h5 class="mb-3">Are you sure you want to decline this donor's medical history?</h5>
                    <p class="text-muted mb-4">The donor will be marked as ineligible for donation.</p>
                    
                    <div class="mb-3">
                        <label for="declineReason" class="form-label fw-semibold">Reason for declinement:</label>
                        <textarea class="form-control" id="declineReason" rows="4" 
                                  placeholder="Please provide a detailed reason for declining this donor's medical history..." 
                                  required maxlength="500" style="min-height: 100px;"></textarea>
                        <div class="form-text">
                            <span id="declineCharCount">0/500 characters</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary px-4" id="confirmDeclineBtn" disabled>
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Medical History Confirmation Modal -->
<div class="modal fade" id="approveMedicalHistoryModal" tabindex="-1" aria-labelledby="approveMedicalHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="approveMedicalHistoryModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Approve Medical History?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Are you sure you want to approve this donor's medical history?</h5>
                    <p class="text-muted mb-4">This will allow the donor to proceed to the next step in the evaluation process.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-success px-4" id="confirmApproveBtn">
                    <i class="fas fa-check me-2"></i>Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Medical History Declined Success Modal -->
<div class="modal fade" id="medicalHistoryDeclinedModal" tabindex="-1" aria-labelledby="medicalHistoryDeclinedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryDeclinedModalLabel">
                    <i class="fas fa-ban me-2"></i>
                    Medical History Declined
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-times-circle text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Donor's medical history has been declined.</h5>
                    <p class="text-muted mb-4">Donor marked as ineligible for donation.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Medical History Approved Success Modal -->
<div class="modal fade" id="medicalHistoryApprovedModal" tabindex="-1" aria-labelledby="medicalHistoryApprovedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryApprovedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Medical History Approved
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">The donor's medical history has been approved.</h5>
                    <p class="text-muted mb-4">The donor can now proceed to the next step in the evaluation process.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-primary px-4" id="proceedToPhysicalExamBtn">
                    <i class="fas fa-arrow-right me-2"></i>Proceed to Physical Examination
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Data for JavaScript -->
<?php
    // Prepare robust JSON payload with safe escaping and UTF-8 substitution
    $mh_json = json_encode(
        $medical_history_data,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($mh_json === false) { $mh_json = 'null'; }

    $sex_val = is_string($donor_sex) ? strtolower($donor_sex) : '';
    $sex_json = json_encode($sex_val, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $role_val = isset($user_role) ? $user_role : '';
    $role_json = json_encode($role_val, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script type="application/json" id="modalData">{"medicalHistoryData": <?php echo $mh_json; ?>, "donorSex": <?php echo $sex_json; ?>, "userRole": <?php echo $role_json; ?>}</script>
 
<script>
// Function to update medical history completion status
function updateMedicalHistoryCompletion(donorId) {
    console.log('=== UPDATING MEDICAL HISTORY COMPLETION ===');
    console.log('Donor ID:', donorId);
    console.log('API URL: ../../../assets/php_func/update_medical_history_completion.php');
    
    const updateData = {
        donor_id: donorId
    };
    
    console.log('Request data:', updateData);
    
    fetch('../../../assets/php_func/update_medical_history_completion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(updateData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            console.log('✅ Medical history completion status updated successfully');
            // Refresh the donor details to show updated badge
            if (typeof window.fetchDonorDetails === 'function') {
                console.log('Refreshing donor details...');
                window.fetchDonorDetails(donorId, window.currentEligibilityId);
            }
        } else {
            console.error('❌ Failed to update medical history completion status:', data.error);
        }
    })
    .catch(error => {
        console.error('❌ Error updating medical history completion status:', error);
    });
}

// Function to generate medical history questions (called from admin dashboard)
window.generateAdminMedicalHistoryQuestions = function() {
    try {
        console.log('=== ADMIN MEDICAL HISTORY QUESTIONS RENDERING START ===');
        const dataEl = document.getElementById('modalData');
        if (!dataEl) { console.error('modalData element not found'); return; }
        const parsed = JSON.parse(dataEl.textContent || '{}');
        const medicalHistoryData = parsed.medicalHistoryData || {};
        const donorSex = String(parsed.donorSex || '').toLowerCase();
        
        console.log('Medical History Data:', medicalHistoryData);
        console.log('Donor Sex:', donorSex);
        console.log('Parsed Data:', parsed);

        // Map q# -> column name from medical_history to prefill accurately
        const fieldByQuestion = {
            1: 'feels_well', 2: 'previously_refused', 3: 'testing_purpose_only', 4: 'understands_transmission_risk',
            5: 'recent_alcohol_consumption', 6: 'recent_aspirin', 7: 'recent_medication', 8: 'recent_donation',
            9: 'zika_travel', 10: 'zika_contact', 11: 'zika_sexual_contact', 12: 'blood_transfusion',
            13: 'surgery_dental', 14: 'tattoo_piercing', 15: 'risky_sexual_contact', 16: 'unsafe_sex',
            17: 'hepatitis_contact', 18: 'imprisonment', 19: 'uk_europe_stay', 20: 'foreign_travel',
            21: 'drug_use', 22: 'clotting_factor', 23: 'positive_disease_test', 24: 'malaria_history',
            25: 'std_history', 26: 'cancer_blood_disease', 27: 'heart_disease', 28: 'lung_disease',
            29: 'kidney_disease', 30: 'chicken_pox', 31: 'chronic_illness', 32: 'recent_fever',
            33: 'pregnancy_history', 34: 'last_childbirth', 35: 'recent_miscarriage', 36: 'breastfeeding',
            37: 'last_menstruation'
        };

        // Ensure structural hosts exist even if markup was trimmed by loader
        (function ensureStructure(){
            const root = document.getElementById('medicalHistoryModalAdminContent') || document.getElementById('medicalHistoryModalContent') || document.body;
            console.log('Root element found:', !!root, root?.id);
            let form = document.getElementById('modalMedicalHistoryForm');
            console.log('Form element found:', !!form);
            if (!form) {
                console.log('Creating form element');
                form = document.createElement('form');
                form.method = 'POST';
                form.action = 'medical-history-process-admin.php';
                form.id = 'modalMedicalHistoryForm';
                const hiddenDonor = document.createElement('input');
                hiddenDonor.type = 'hidden';
                hiddenDonor.name = 'donor_id';
                try { hiddenDonor.value = (parsed && parsed.medicalHistoryData && parsed.medicalHistoryData.donor_id) ? parsed.medicalHistoryData.donor_id : ''; } catch(_) {}
                form.appendChild(hiddenDonor);
                root.appendChild(form);
                console.log('Form created and appended to root');
            }
            const stepTitleByNum = {
                1: 'HEALTH & RISK ASSESSMENT:',
                2: 'IN THE PAST 6 MONTHS HAVE YOU:',
                3: 'IN THE PAST 12 MONTHS HAVE YOU:',
                4: 'HAVE YOU EVER:',
                5: 'HAD ANY OF THE FOLLOWING:',
                6: 'FOR FEMALE DONORS ONLY:'
            };
            const stepDesc = 'Tick the appropriate answer.';
            for (let n = 1; n <= 6; n++) {
                let host = document.querySelector(`.form-step[data-step="${n}"]`);
                if (!host) {
                    host = document.createElement('div');
                    host.className = 'form-step' + (n === 1 ? ' active' : '');
                    host.setAttribute('data-step', String(n));
                    const title = document.createElement('div');
                    title.className = 'step-title';
                    title.textContent = stepTitleByNum[n] || `STEP ${n}`;
                    const desc = document.createElement('div');
                    desc.className = 'step-description';
                    desc.textContent = stepDesc;
                    const container = document.createElement('div');
                    container.className = 'form-container';
                    container.setAttribute('data-step-container', String(n));
                    container.innerHTML = [
                        '<div class="form-header">#</div>',
                        '<div class="form-header">Question</div>',
                        '<div class="form-header">YES</div>',
                        '<div class="form-header">NO</div>',
                        '<div class="form-header">REMARKS</div>'
                    ].join('');
                    host.appendChild(title);
                    host.appendChild(desc);
                    host.appendChild(container);
                    form.appendChild(host);
                }
            }
        })();

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

        // Full question set mirroring staff modal
        const questionsByStep = {
            1: [
                { q: 1, text: 'Do you feel well and healthy today?' },
                { q: 2, text: 'Have you ever been refused as a blood donor or told not to donate blood for any reasons?' },
                { q: 3, text: 'Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?' },
                { q: 4, text: 'Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?' },
                { q: 5, text: 'Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?' },
                { q: 6, text: 'In the last 3 DAYS have you taken aspirin?' },
                { q: 7, text: 'In the past 4 WEEKS have you taken any medications and/or vaccinations?' },
                { q: 8, text: 'In the past 3 MONTHS have you donated whole blood, platelets or plasma?' }
            ],
            2: [
                { q: 9, text: 'Been to any places in the Philippines or countries infected with ZIKA Virus?' },
                { q: 10, text: 'Had sexual contact with a person who was confirmed to have ZIKA Virus Infection?' },
                { q: 11, text: 'Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?' }
            ],
            3: [
                { q: 12, text: 'Received blood, blood products and/or had tissue/organ transplant or graft?' },
                { q: 13, text: 'Had surgical operation or dental extraction?' },
                { q: 14, text: 'Had a tattoo applied, ear and body piercing, acupuncture, needle stick Injury or accidental contact with blood?' },
                { q: 15, text: 'Had sexual contact with high risks individuals or in exchange for material or monetary gain?' },
                { q: 16, text: 'Engaged in unprotected, unsafe or casual sex?' },
                { q: 17, text: 'Had jaundice/hepatitis/personal contact with person who had hepatitis?' },
                { q: 18, text: 'Been incarcerated, Jailed or imprisoned?' },
                { q: 19, text: 'Spent time or have relatives in the United Kingdom or Europe?' }
            ],
            4: [
                { q: 20, text: 'Travelled or lived outside of your place of residence or outside the Philippines?' },
                { q: 21, text: 'Taken prohibited drugs (orally, by nose, or by injection)?' },
                { q: 22, text: 'Used clotting factor concentrates?' },
                { q: 23, text: 'Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?' },
                { q: 24, text: 'Had Malaria or Hepatitis in the past?' },
                { q: 25, text: 'Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?' }
            ],
            5: [
                { q: 26, text: 'Cancer, blood disease or bleeding disorder (haemophilia)?' },
                { q: 27, text: 'Heart disease/surgery, rheumatic fever or chest pains?' },
                { q: 28, text: 'Lung disease, tuberculosis or asthma?' },
                { q: 29, text: 'Kidney disease, thyroid disease, diabetes, epilepsy?' },
                { q: 30, text: 'Chicken pox and/or cold sores?' },
                { q: 31, text: 'Any other chronic medical condition or surgical operations?' },
                { q: 32, text: 'Have you recently had rash and/or fever? Was/were this/these also associated with arthralgia or arthritis or conjunctivitis?' }
            ],
            6: donorSex === 'female' ? [
                { q: 33, text: 'Are you currently pregnant or have you ever been pregnant?' },
                { q: 34, text: 'When was your last childbirth?' },
                { q: 35, text: 'In the past 1 YEAR, did you have a miscarriage or abortion?' },
                { q: 36, text: 'Are you currently breastfeeding?' },
                { q: 37, text: 'When was your last menstrual period?' }
            ] : []
        };

        function createCell(html, className) {
            const div = document.createElement('div');
            div.className = className;
            div.innerHTML = html;
            return div;
        }

        Object.keys(questionsByStep).forEach(stepNum => {
            const questions = questionsByStep[stepNum];
            console.log(`Processing Step ${stepNum}:`, questions);
            if (!questions || questions.length === 0) {
                console.log(`No questions for step ${stepNum}`);
                return;
            }
            let container = document.querySelector(`.form-container[data-step-container="${stepNum}"]`);
            if (!container) {
                // Create the container structure if missing (ensure idempotent rendering)
                const stepHost = document.querySelector(`.form-step[data-step="${stepNum}"]`);
                if (!stepHost) {
                    console.error(`Step host not found for step ${stepNum}`);
                    return;
                }
                const newContainer = document.createElement('div');
                newContainer.className = 'form-container';
                newContainer.setAttribute('data-step-container', String(stepNum));
                // Header row to mirror expected structure
                newContainer.innerHTML = [
                    '<div class="form-header">#</div>',
                    '<div class="form-header">Question</div>',
                    '<div class="form-header">YES</div>',
                    '<div class="form-header">NO</div>',
                    '<div class="form-header">REMARKS</div>'
                ].join('');
                stepHost.appendChild(newContainer);
                container = newContainer;
            }
            console.log(`Found container for step ${stepNum}, adding ${questions.length} questions`);

            questions.forEach((q, idx) => {
                const number = createCell(String(idx + 1), 'question-number');
                const text = createCell(q.text, 'question-text');
                // Prefer mapped DB field; fallback to q-key
                const qNum = q.q;
                const fieldName = fieldByQuestion[qNum];
                let saved = undefined;
                if (fieldName && Object.prototype.hasOwnProperty.call(medicalHistoryData, fieldName)) {
                    saved = medicalHistoryData[fieldName];
                } else {
                    saved = medicalHistoryData['q' + qNum];
                }
                const yesChecked = (saved === true || saved === 'yes' || saved === 'Yes') ? 'checked' : '';
                const noChecked = (saved === false || saved === 'no' || saved === 'No') ? 'checked' : '';

                const yes = createCell(
                    `<label class=\"radio-container\">\n                        <input type=\"radio\" name=\"q${qNum}\" value=\"Yes\" ${yesChecked}>\n                        <span class=\"checkmark\"></span>\n                    </label>`,
                    'radio-cell'
                );
                const no = createCell(
                    `<label class=\"radio-container\">\n                        <input type=\"radio\" name=\"q${qNum}\" value=\"No\" ${noChecked}>\n                        <span class=\"checkmark\"></span>\n                    </label>`,
                    'radio-cell'
                );
                const remarksKey = `q${qNum}_remarks`;
                const mappedRemarksKey = fieldName ? `${fieldName}_remarks` : remarksKey;
                const remarksVal = medicalHistoryData[mappedRemarksKey] ? String(medicalHistoryData[mappedRemarksKey]) : (medicalHistoryData[remarksKey] ? String(medicalHistoryData[remarksKey]) : '');
                const remarksOptionsForQ = remarksOptions[qNum] || ["None"];
                const remarksSelectOptions = remarksOptionsForQ.map(option => {
                    const selected = remarksVal === option ? 'selected' : '';
                    return `<option value="${option}" ${selected}>${option}</option>`;
                }).join('');
                const remarks = createCell(
                    `<select class=\"remarks-input\" name=\"${remarksKey}\">${remarksSelectOptions}</select>`,
                    'remarks-cell'
                );

                container.appendChild(number);
                container.appendChild(text);
                container.appendChild(yes);
                container.appendChild(no);
                container.appendChild(remarks);
            });
        });

        // Hide female-only step 6 when donor is male
        if (donorSex === 'male') {
            try {
                const step6 = document.getElementById('modalStep6');
                const line56 = document.getElementById('modalLine5-6');
                if (step6) step6.style.display = 'none';
                if (line56) line56.style.display = 'none';
                const step6Form = document.querySelector('.form-step[data-step="6"]');
                if (step6Form) step6Form.style.display = 'none';
                console.log('Hiding female-only step 6 for male donor');
            } catch (_) {}
        }
        
        console.log('Medical history questions rendering completed successfully');
        
        // Additional verification: count total questions rendered
        const totalQuestions = document.querySelectorAll('.question-text').length;
        console.log(`Total questions rendered: ${totalQuestions}`);
        
        // Check if any containers have content
        const containers = document.querySelectorAll('.form-container[data-step-container]');
        containers.forEach((container, index) => {
            const questions = container.querySelectorAll('.question-text');
            console.log(`Step ${index + 1} container has ${questions.length} questions`);
        });
        
    } catch (e) {
        console.error('Failed to render medical history questions:', e);
        console.error('Stack trace:', e.stack);
    }
};

// Fallback: Auto-call if not called externally (for backward compatibility)
setTimeout(() => {
    if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
        // Check if questions are already rendered
        const existingQuestions = document.querySelectorAll('.question-text');
        if (existingQuestions.length === 0) {
            console.log('Auto-calling question generation (fallback)');
            window.generateAdminMedicalHistoryQuestions();
        }
    }
}, 500);
</script>

<script>
// Step navigation for the modal (admin side) â€“ mirrors staff behavior in a lightweight way
(function initModalStepNavigation(){
    try {
        const dataEl = document.getElementById('modalData');
        if (!dataEl) return;
        const parsed = JSON.parse(dataEl.textContent || '{}');
        const donorSex = String(parsed.donorSex || '').toLowerCase();
        const totalSteps = donorSex === 'male' ? 5 : 6;

        let currentStep = 1;
        const form = document.getElementById('modalMedicalHistoryForm');
        const prevButton = document.getElementById('modalPrevButton');
        const nextButton = document.getElementById('modalNextButton');
        const errorMessage = document.getElementById('modalValidationError');
        const stepIndicators = document.querySelectorAll('#modalStepIndicators .step');
        const stepConnectors = document.querySelectorAll('#modalStepIndicators .step-connector');

        function updateStepDisplay() {
            // Show only current step
            const steps = form ? form.querySelectorAll('.form-step') : [];
            steps.forEach(s => s.classList.remove('active'));
            const active = form ? form.querySelector(`.form-step[data-step="${currentStep}"]`) : null;
            if (active) active.classList.add('active');

            // Indicators
            stepIndicators.forEach(i => {
                const step = parseInt(i.getAttribute('data-step'));
                if (step < currentStep) { i.classList.add('Completed','active'); }
                else if (step === currentStep) { i.classList.add('active'); i.classList.remove('completed'); }
                else { i.classList.remove('active','Completed'); }
            });
            stepConnectors.forEach((c, idx) => {
                if (idx + 1 < currentStep) c.classList.add('active');
                else c.classList.remove('active');
            });

            // Buttons
            if (prevButton) prevButton.style.display = currentStep === 1 ? 'none' : 'inline-block';
            if (nextButton) {
                // Check if view-only mode is active
                const isViewOnly = window.medicalHistoryViewOnly === true || 
                                 (typeof window.mhApplyViewOnlyMode !== 'undefined' && 
                                  document.getElementById('modalSubmitButton') && 
                                  document.getElementById('modalSubmitButton').style.display === 'none');
                
                if (currentStep === totalSteps) {
                    // Check if we're in admin registration flow
                    const isRegistrationFlow = window.__adminDonorRegistrationFlow === true;
                    if (isViewOnly && !isRegistrationFlow) {
                        // In view-only mode (but not registration), show "Close" button instead of "Submit"
                        nextButton.innerHTML = '<i class="fas fa-times me-1"></i>Close';
                    } else {
                        // In registration flow or normal mode, show "Submit"
                        nextButton.innerHTML = '<i class="fas fa-check me-1"></i>Submit';
                    }
                } else {
                    nextButton.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                }
            }
            
            // Hide submit button in view-only mode on last step
            const submitButton = document.getElementById('modalSubmitButton');
            if (submitButton && currentStep === totalSteps) {
                const isViewOnly = window.medicalHistoryViewOnly === true;
                if (isViewOnly) {
                    submitButton.style.display = 'none';
                }
            }

            if (errorMessage) errorMessage.style.display = 'none';
        }

        function validateCurrentStep() {
            const stepEl = form ? form.querySelector(`.form-step[data-step="${currentStep}"]`) : null;
            if (!stepEl) return true; // nothing to validate
            const radios = Array.from(stepEl.querySelectorAll('input[type="radio"]'));
            if (radios.length === 0) return true;
            const names = Array.from(new Set(radios.map(r => r.name)));
            const allAnswered = names.every(n => stepEl.querySelector(`input[name="${n}"]:checked`));
            if (!allAnswered && errorMessage) {
                errorMessage.textContent = 'Please answer all questions before proceeding to the next step.';
                errorMessage.style.display = 'block';
            }
            return allAnswered;
        }

        if (nextButton) {
            nextButton.addEventListener('click', function(e){
                e.preventDefault();
                
                // Check if view-only mode is active
                const isViewOnly = window.medicalHistoryViewOnly === true;
                
                if (currentStep < totalSteps) {
                    if (!validateCurrentStep()) return;
                    currentStep++;
                    updateStepDisplay();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    // Final step
                    // Check if we're in admin registration flow
                    const isRegistrationFlow = window.__adminDonorRegistrationFlow === true;
                    if (isViewOnly && !isRegistrationFlow) {
                        // In view-only mode (but not registration), close the modal instead of submitting
                        console.log('View-only mode: Closing medical history modal');
                        if (typeof closeMedicalHistoryModal === 'function') {
                            closeMedicalHistoryModal();
                        } else {
                            // Fallback: simple close
                            const modal = document.getElementById('medicalHistoryModal');
                            if (modal) {
                                modal.classList.remove('show');
                                modal.style.display = 'none';
                            }
                        }
                    } else {
                        // Normal mode or registration flow: dispatch submit event
                        console.log('Final step reached - dispatching submit event');
                        try {
                            const evt = new Event('submit', { bubbles: true, cancelable: true });
                            form && form.dispatchEvent(evt);
                            console.log('Submit event dispatched successfully');
                        } catch (err) {
                            console.error('Error dispatching submit event:', err);
                        }
                    }
                }
            });
        }
        if (prevButton) {
            prevButton.addEventListener('click', function(e){
                e.preventDefault();
                if (currentStep > 1) {
                    currentStep--;
                    updateStepDisplay();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        }

        updateStepDisplay();
    } catch (_) { /* noop */ }
})();
</script>

<script>
// Form submit handler for admin context
(function initAdminFormSubmit(){
    function attachFormHandler() {
        try {
            if (window.__adminDonorRegistrationFlow === true) {
                console.log('Admin donor registration flow detected - skipping legacy medical history submit handler');
                return;
            }
            const form = document.getElementById('modalMedicalHistoryForm');
            console.log('Form element found:', !!form);
            if (!form) {
                console.log('Form not found - retrying in 100ms');
                setTimeout(attachFormHandler, 100);
                return;
            }

            // Gate this handler to ADMIN context only
            const isAdminContext = !!document.getElementById('medicalHistoryModalAdmin') || !!document.getElementById('medicalHistoryModal');
            console.log('Admin context check:', isAdminContext);
            if (!isAdminContext) {
                console.log('Not in admin context - retrying in 100ms');
                setTimeout(attachFormHandler, 100);
                return;
            }

        form.addEventListener('submit', function(e){
            console.log('Form submit event triggered');
            // Intercept default submission so admin stays on page
            e.preventDefault();
            e.stopPropagation();
            
            // For admin context, always use 'admin_complete' action
            const formData = new FormData(form);
            formData.set('action', 'admin_complete');
            
            console.log('Admin medical history form submission with admin_complete action');
            
            fetch('../../src/views/forms/medical-history-process-admin.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(res => {
                if (res && res.success) {
                    console.log('Admin medical history completed successfully');
                    
                    // Close the medical history modal by dispatching a custom event
                    console.log('Dispatching close event for MH modal');
                    
                    // First, let's try to close the modal directly from here as a test
                    const modalElement = document.getElementById('medicalHistoryModalAdmin');
                    console.log('Modal element found from modal content:', !!modalElement);
                    if (modalElement) {
                        console.log('Modal classes:', modalElement.className);
                        console.log('Modal style display:', modalElement.style.display);
                        console.log('Modal is visible:', modalElement.classList.contains('show'));
                    }
                    
                    const closeEvent = new CustomEvent('closeMedicalHistoryModal', { 
                        detail: { 
                            reason: 'form_submitted',
                            donorId: form.querySelector('input[name="donor_id"]')?.value
                        } 
                    });
                    window.dispatchEvent(closeEvent);
                    
                    // Refresh donor details to show updated status
                    if (typeof window.fetchDonorDetails === 'function') {
                        const donorIdInput = form.querySelector('input[name="donor_id"]');
                        const donorId = donorIdInput ? donorIdInput.value : null;
                        if (donorId) {
                            console.log('Refreshing donor details after MH completion');
                            window.fetchDonorDetails(donorId, window.currentEligibilityId);
                        }
                    }
                    
                    // Show completion confirmation modal immediately after closing
                    setTimeout(() => {
                        const completionModal = document.getElementById('medicalHistoryCompletionModal');
                        if (completionModal) {
                            const modal = new bootstrap.Modal(completionModal);
                            modal.show();
                        } else {
                            console.log('Medical history completion modal not found - showing fallback message');
                            // Fallback: show a simple alert
                            alert('Medical History has been completed successfully!');
                        }
                    }, 300);
                } else {
                    console.error('Admin medical history completion failed:', res?.message);
                    // Show error message
                    const em = document.getElementById('modalValidationError');
                    if (em) {
                        em.textContent = res?.message || 'Failed to complete medical history.';
                        em.style.display = 'block';
                    }
                }
            }).catch(err => {
                console.error('Admin medical history completion error:', err);
                // Show error message
                const em = document.getElementById('modalValidationError');
                if (em) {
                    em.textContent = 'Network error occurred while completing medical history.';
                    em.style.display = 'block';
                }
            });
        });
        } catch(_) { /* noop */ }
    }
    
    // Start trying to attach the handler
    attachFormHandler();
})();
</script>

 <script>
 // Enhanced Edit Button Functionality (namespaced + idempotent)
 function mhInitializeEditFunctionality() {
    // Edit functionality removed
    return;
     
     if (editButton) {
         
         // Remove any existing event listeners
         editButton.replaceWith(editButton.cloneNode(true));
         const newEditButton = document.getElementById('modalEditButton');
         
            newEditButton.addEventListener('click', function() {
             
             // Enable editing of form fields
             const form = document.getElementById('modalMedicalHistoryForm');
             const radioButtons = form.querySelectorAll('input[type="radio"]');
             const selectFields = form.querySelectorAll('select.remarks-input');
             const textInputs = form.querySelectorAll('input[type="text"], textarea');
             
             //console.log('Found radio buttons:', radioButtons.length);
             //console.log('Found select fields:', selectFields.length);
             //console.log('Found text inputs:', textInputs.length);
             
             // Enable all form inputs
             radioButtons.forEach(input => {
                 input.disabled = false;
                 input.readOnly = false;
                 //console.log('Enabled radio button:', input.name);
             });
             
             selectFields.forEach(input => {
                 input.disabled = false;
                 input.readOnly = false;
                 //console.log('Enabled select field:', input.name);
             });
             
             textInputs.forEach(input => {
                 input.disabled = false;
                 input.readOnly = false;
                 //console.log('Enabled text input:', input.name);
             });
             
             // Change button text to indicate editing mode
            // Keep button hidden after enabling edit; saving is disabled in MH modal
            newEditButton.style.display = 'none';
         });
     } else {
         //console.log('âŒ Edit button not found - checking DOM...');
         //console.log('Available buttons:', document.querySelectorAll('button').length);
         //console.log('Modal content:', document.getElementById('medicalHistoryModalContent')?.innerHTML?.substring(0, 200));
     }
     //console.log('=== EDIT FUNCTIONALITY INITIALIZATION END ===');
 }
 
function saveEditedData() {
    // Edit functionality removed
    return Promise.resolve();
     
     // Add action for saving - use 'next' which is valid for saving without approval changes
     formData.append('action', 'next');
     
     //console.log('Saving edited data...');
     
     // Submit the form data
     fetch('../../src/views/forms/medical-history-process-admin.php', {
         method: 'POST',
         body: formData
     })
     .then(response => response.json())
     .then(data => {
         if (data.success) {
             //console.log('Data saved successfully');
             
             // Show a small, quiet success toast
             mhShowQuietSuccessToast();
             
             // Reset button to edit mode
             const editButton = document.getElementById('modalEditButton');
             editButton.textContent = 'Edit';
             editButton.classList.remove('save-button');
             editButton.classList.add('edit-button');
             
             // Disable form fields again (respect original disabled state)
             const radioButtons = form.querySelectorAll('input[type="radio"]');
             const selectFields = form.querySelectorAll('select.remarks-input');
             const textInputs = form.querySelectorAll('input[type="text"], textarea');
             
             radioButtons.forEach(input => {
                 const wasOriginallyDisabled = input.getAttribute('data-originally-disabled') === 'true';
                 input.disabled = wasOriginallyDisabled;
                 input.readOnly = wasOriginallyDisabled;
             });
             
             selectFields.forEach(input => {
                 const wasOriginallyDisabled = input.getAttribute('data-originally-disabled') === 'true';
                 input.disabled = wasOriginallyDisabled;
                 input.readOnly = wasOriginallyDisabled;
             });
             
             textInputs.forEach(input => {
                 const wasOriginallyDisabled = input.getAttribute('data-originally-disabled') === 'true';
                 input.disabled = wasOriginallyDisabled;
                 input.readOnly = wasOriginallyDisabled;
             });
         } else {
             console.error('Save failed:', data.message);
             mhShowQuietErrorToast(data.message || 'Save failed');
         }
     })
     .catch(error => {
         console.error('Save error:', error);
         mhShowQuietErrorToast('Network error occurred');
     });
 }
 
// Edit functionality removed

 // Custom confirmation function (namespaced) to replace browser confirm
 function mhCustomConfirm(message, onConfirm) {
     const modal = new bootstrap.Modal(document.getElementById('mhCustomConfirmModal'));
     const messageElement = document.getElementById('mhCustomConfirmMessage');
     const confirmButton = document.getElementById('mhCustomConfirmYes');
     
     messageElement.textContent = message;
     
     // Remove any existing event listeners
     const newConfirmButton = confirmButton.cloneNode(true);
     confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);
     
     newConfirmButton.addEventListener('click', function() {
         modal.hide();
         if (onConfirm) onConfirm();
     });
     
     modal.show();
 }
 
 // Also expose namespaced initializer
 if (typeof window !== 'undefined') {
     window.mhCustomConfirm = mhCustomConfirm;
 }
 </script>

<!-- Save Confirmation Modal removed for staff context -->

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="errorModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0" style="font-size: 1.1rem;" id="errorMessage">An error occurred while saving the data.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Save confirmation disabled in this modal
function mhShowSaveConfirmationModal() { return; }

// Function to show error modal
function mhShowErrorModal(message) {
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    const errorMessage = document.getElementById('errorMessage');
    if (errorMessage) {
        errorMessage.textContent = message;
    }
    errorModal.show();
}

// Update the save button click handler to show confirmation first
function mhBindConfirmSaveHandler() {
    // No-op in staff context
    const confirmSaveBtn = document.getElementById('confirmSaveBtn');
    if (confirmSaveBtn) { try { confirmSaveBtn.style.display = 'none'; } catch(_) {} }
}

// Bind immediately (content is already in DOM when this script runs)
mhBindConfirmSaveHandler();

// Also re-bind whenever the modal is shown (scoped to avoid redeclare errors)
(function(){
    // No-op
})();

// Override the save button click to show confirmation first
function mhInitializeSaveConfirmation() {
    const saveButton = document.getElementById('modalEditButton');
    if (saveButton && saveButton.textContent === 'Save') {
        saveButton.onclick = function() {
            //console.log('Save button clicked - showing confirmation');
            mhShowSaveConfirmationModal();
        };
    }
}

// Call this after the edit functionality is initialized
setTimeout(mhInitializeSaveConfirmation, 100);

// Function to set needs_review status for admin medical history
function setAdminNeedsReview(needsReview) {
    try {
        // Try to find donor ID input with multiple selectors and retry mechanism
        let donorIdInput = document.querySelector('input[name="donor_id"]');
        
        // If not found, try alternative selectors
        if (!donorIdInput) {
            donorIdInput = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
        }
        
        // If still not found, try to get from URL parameters or global variables
        if (!donorIdInput) {
            // Check if donor_id is available in URL
            const urlParams = new URLSearchParams(window.location.search);
            const donorIdFromUrl = urlParams.get('donor_id');
            
            if (donorIdFromUrl) {
                console.log(`Using donor ID from URL: ${donorIdFromUrl}`);
                updateNeedsReviewDirectly(donorIdFromUrl, needsReview);
                return;
            }
            
            // Check if donor_id is available in global variables
            if (window.currentAdminDonorData && window.currentAdminDonorData.donor_id) {
                console.log(`Using donor ID from global variable: ${window.currentAdminDonorData.donor_id}`);
                updateNeedsReviewDirectly(window.currentAdminDonorData.donor_id, needsReview);
                return;
            }
            
            // If still not found, retry after a short delay
            console.log('Donor ID not found, retrying in 500ms...');
            setTimeout(() => {
                setAdminNeedsReview(needsReview);
            }, 500);
            return;
        }
        
        const donorId = donorIdInput.value;
        if (!donorId) {
            console.log('Donor ID input found but no value, retrying in 500ms...');
            setTimeout(() => {
                setAdminNeedsReview(needsReview);
            }, 500);
            return;
        }
        
        console.log(`Setting needs_review to ${needsReview} for donor ${donorId}`);
        updateNeedsReviewDirectly(donorId, needsReview);
        
    } catch (error) {
        console.error('Error in setAdminNeedsReview:', error);
    }
}

// Helper function to actually perform the needs_review update
function updateNeedsReviewDirectly(donorId, needsReview) {
    // Only update needs_review, don't touch medical_approval when setting to TRUE
    const updateData = {
        donor_id: donorId,
        needs_review: needsReview
    };
    
    // When setting needs_review to TRUE, we should NOT change medical_approval
    // When setting to FALSE, the approve/decline functions will handle medical_approval
    
    fetch('../../public/api/update-medical-history.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(updateData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`Successfully set needs_review to ${needsReview} for donor ${donorId}`);
        } else {
            console.error('Failed to update needs_review:', data.error);
        }
    })
    .catch(error => {
        console.error('Error updating needs_review:', error);
    });
}

// Admin new donor processing flow integration
function mhInitializeAdminFlow() {
    console.log('Initializing admin flow for medical history modal');
    
    // Note: needs_review will be set to TRUE when the admin starts reviewing
    // and set to FALSE when they complete the review via admin_complete action
    
    // Admin flow now uses standard form submission - no custom submit button needed
    // Just hide approve/decline buttons for admin flow
    const declineButton = document.getElementById('modalDeclineButton');
    const approveButton = document.getElementById('modalApproveButton');
    
    if (declineButton) declineButton.style.display = 'none';
    if (approveButton) approveButton.style.display = 'none';
    
    // Handle decline button click
    if (declineButton) {
        declineButton.addEventListener('click', function() {
            // Show decline confirmation modal
            const declineModal = new bootstrap.Modal(document.getElementById('declineMedicalHistoryModal'));
            declineModal.show();
        });
    }
    
    // Handle approve button click
    if (approveButton) {
        approveButton.addEventListener('click', function() {
            // Show approve confirmation modal
            const approveModal = new bootstrap.Modal(document.getElementById('approveMedicalHistoryModal'));
            approveModal.show();
        });
    }
    
    // Initialize decline modal functionality
    mhInitializeDeclineModal();
    
    // Initialize approve modal functionality
    mhInitializeApproveModal();
}

// Apply view-only mode for approved donors (make all fields read-only but keep navigation)
window.mhApplyViewOnlyMode = function() {
    console.log('Applying view-only mode to medical history form');
    
    // Set view-only flag globally
    window.medicalHistoryViewOnly = true;
    
    // Hide only action buttons (submit, approve, decline), but keep navigation buttons
    const prevButton = document.getElementById('modalPrevButton');
    const nextButton = document.getElementById('modalNextButton');
    const submitButton = document.getElementById('modalSubmitButton');
    const declineButton = document.getElementById('modalDeclineButton');
    const approveButton = document.getElementById('modalApproveButton');
    
    // Keep navigation buttons visible for view-only mode
    // They will be shown/hidden by the normal step navigation logic
    if (submitButton) {
        submitButton.style.display = 'none';
        submitButton.style.visibility = 'hidden';
    }
    if (declineButton) {
        declineButton.style.display = 'none';
        declineButton.style.visibility = 'hidden';
    }
    if (approveButton) {
        approveButton.style.display = 'none';
        approveButton.style.visibility = 'hidden';
    }
    
    // Force update step display to show "Close" instead of "Submit" if on last step
    // BUT NOT if we're in registration flow
    if (typeof updateStepDisplay === 'function') {
        setTimeout(() => {
            try {
                // Check if we're in admin registration flow
                const isRegistrationFlow = window.__adminDonorRegistrationFlow === true;
                if (!isRegistrationFlow) {
                    // Try to trigger updateStepDisplay if it's accessible
                    const nextBtn = document.getElementById('modalNextButton');
                    if (nextBtn && nextBtn.textContent.includes('Submit')) {
                        nextBtn.innerHTML = '<i class="fas fa-times me-1"></i>Close';
                    }
                }
            } catch(e) {
                console.warn('Could not update button text:', e);
            }
        }, 100);
    }
    
    // Make all form inputs read-only (but keep navigation functional)
    const form = document.getElementById('modalMedicalHistoryForm');
    if (form) {
        // Get all text inputs, selects, and textareas - use readonly for better visual feedback
        const textInputs = form.querySelectorAll('input[type="text"], input[type="number"], input[type="date"], textarea, select');
        textInputs.forEach(input => {
            // Skip hidden inputs and buttons
            if (input.type !== 'hidden' && input.type !== 'button' && input.type !== 'submit') {
                input.setAttribute('readonly', 'readonly');
                input.disabled = true;
                input.style.cursor = 'not-allowed';
                input.style.backgroundColor = '#f8f9fa';
            }
        });
        
        // Handle radio buttons and checkboxes separately - use disabled
        const radios = form.querySelectorAll('input[type="radio"]');
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        [...radios, ...checkboxes].forEach(input => {
            input.disabled = true;
            input.style.cursor = 'not-allowed';
            // Add visual indicator with opacity
            const label = input.closest('label') || (input.nextElementSibling && input.nextElementSibling.tagName === 'LABEL' ? input.nextElementSibling : null);
            if (label) {
                label.style.opacity = '0.7';
                label.style.cursor = 'not-allowed';
            }
        });
    }
    
    // Keep step navigation - don't show all steps at once
    // Let the normal step navigation work, just with read-only fields
    
    console.log('View-only mode applied successfully - navigation enabled');
};

// Initialize decline modal functionality
function mhInitializeDeclineModal() {
    const declineReason = document.getElementById('declineReason');
    const declineCharCount = document.getElementById('declineCharCount');
    const confirmDeclineBtn = document.getElementById('confirmDeclineBtn');
    
    if (declineReason && declineCharCount && confirmDeclineBtn) {
        // Character count and validation
        declineReason.addEventListener('input', function() {
            const length = this.value.length;
            declineCharCount.textContent = `${length}/500 characters`;
            
            // Enable/disable submit button based on minimum length
            if (length >= 10) {
                confirmDeclineBtn.disabled = false;
                confirmDeclineBtn.classList.remove('btn-secondary');
                confirmDeclineBtn.classList.add('btn-danger');
            } else {
                confirmDeclineBtn.disabled = true;
                confirmDeclineBtn.classList.remove('btn-danger');
                confirmDeclineBtn.classList.add('btn-secondary');
            }
        });
        
        // Handle confirm decline
        confirmDeclineBtn.addEventListener('click', function() {
            const reason = declineReason.value.trim();
            if (reason.length < 10) {
                alert('Please provide a reason with at least 10 characters.');
                return;
            }
            
            // Process decline
            mhProcessDecline(reason);
        });
    }
}

// Initialize approve modal functionality
function mhInitializeApproveModal() {
    const confirmApproveBtn = document.getElementById('confirmApproveBtn');
    
    if (confirmApproveBtn) {
        confirmApproveBtn.addEventListener('click', function() {
            // Process approval
            mhProcessApproval();
        });
    }
}

// Process medical history decline
function mhProcessDecline(reason) {
    console.log('Processing medical history decline:', reason);
    
    // Show loading state
    const confirmDeclineBtn = document.getElementById('confirmDeclineBtn');
    const originalText = confirmDeclineBtn.innerHTML;
    confirmDeclineBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    confirmDeclineBtn.disabled = true;
    
    // Get donor ID from the form
    const form = document.getElementById('modalMedicalHistoryForm');
    const donorIdInput = form.querySelector('input[name="donor_id"]');
    const donorId = donorIdInput ? donorIdInput.value : null;
    
    if (!donorId) {
        console.error('No donor ID found for decline processing');
        confirmDeclineBtn.innerHTML = originalText;
        confirmDeclineBtn.disabled = false;
        mhShowQuietErrorToast('Error: No donor ID found');
        return;
    }
    
    // Set needs_review to FALSE before processing decline
    setAdminNeedsReview(false);
    
    // Make API call to update medical history approval status
    const formData = new FormData();
    formData.append('action', 'decline_medical_history');
    formData.append('donor_id', donorId);
    formData.append('decline_reason', reason);
    
    fetch('../../assets/php_func/process_medical_history_approval.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Medical history declined successfully');
            
            // Close decline modal
            const declineModal = bootstrap.Modal.getInstance(document.getElementById('declineMedicalHistoryModal'));
            if (declineModal) {
                declineModal.hide();
            }
            
            // Close medical history modal
            const medicalHistoryModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModalAdmin'));
            if (medicalHistoryModal) {
                medicalHistoryModal.hide();
            }
            
            // Show decline success modal
            setTimeout(() => {
                const declinedModal = new bootstrap.Modal(document.getElementById('medicalHistoryDeclinedModal'));
                declinedModal.show();
            }, 300);
            
        } else {
            console.error('Failed to decline medical history:', data.message);
            mhShowQuietErrorToast(data.message || 'Failed to decline medical history');
        }
    })
    .catch(error => {
        console.error('Error declining medical history:', error);
        mhShowQuietErrorToast('Network error occurred while declining medical history');
    })
    .finally(() => {
        // Reset button state
        confirmDeclineBtn.innerHTML = originalText;
        confirmDeclineBtn.disabled = false;
        
        // Clear form
        document.getElementById('declineReason').value = '';
        document.getElementById('declineCharCount').textContent = '0/500 characters';
    });
}

// Process medical history approval
function mhProcessApproval() {
    console.log('Processing medical history approval');
    
    // Show loading state
    const confirmApproveBtn = document.getElementById('confirmApproveBtn');
    const originalText = confirmApproveBtn.innerHTML;
    confirmApproveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    confirmApproveBtn.disabled = true;
    
    // Get donor ID from the form
    const form = document.getElementById('modalMedicalHistoryForm');
    const donorIdInput = form.querySelector('input[name="donor_id"]');
    const donorId = donorIdInput ? donorIdInput.value : null;
    
    if (!donorId) {
        console.error('No donor ID found for approval processing');
        confirmApproveBtn.innerHTML = originalText;
        confirmApproveBtn.disabled = false;
        mhShowQuietErrorToast('Error: No donor ID found');
        return;
    }
    
    // Set needs_review to FALSE before processing approval
    setAdminNeedsReview(false);
    
    // Make API call to update medical history approval status
    const formData = new FormData();
    formData.append('action', 'approve_medical_history');
    formData.append('donor_id', donorId);
    
    fetch('../../assets/php_func/process_medical_history_approval.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Medical history approved successfully');
            
            // Close approve modal
            const approveModal = bootstrap.Modal.getInstance(document.getElementById('approveMedicalHistoryModal'));
            if (approveModal) {
                approveModal.hide();
            }
            
            // Close medical history modal
            const medicalHistoryModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModalAdmin'));
            if (medicalHistoryModal) {
                medicalHistoryModal.hide();
            }
            
            // Show approve success modal
            setTimeout(() => {
                const approvedModal = new bootstrap.Modal(document.getElementById('medicalHistoryApprovedModal'));
                approvedModal.show();
                
                // Add event listener for the proceed button
                const proceedBtn = document.getElementById('proceedToPhysicalExamBtn');
                if (proceedBtn) {
                    // Capture donorId in closure
                    const capturedDonorId = donorId;
                    proceedBtn.addEventListener('click', function() {
                        approvedModal.hide();
                        setTimeout(() => {
                            // Medical history completion status will be updated by the PHP processing file
                        console.log('Medical history form submitted with admin_complete action - is_admin should be updated by PHP');
                            
                            // Show medical history completion confirmation
                            const completionModal = document.getElementById('medicalHistoryCompletionModal');
                            if (completionModal) {
                                const modal = new bootstrap.Modal(completionModal);
                                modal.show();
                            } else {
                                console.error('Medical history completion modal not found');
                            }
                        }, 300);
                    });
                }
            }, 300);
            
        } else {
            console.error('Failed to approve medical history:', data.message);
            mhShowQuietErrorToast(data.message || 'Failed to approve medical history');
        }
    })
    .catch(error => {
        console.error('Error approving medical history:', error);
        mhShowQuietErrorToast('Network error occurred while approving medical history');
    })
    .finally(() => {
        // Reset button state
        confirmApproveBtn.innerHTML = originalText;
        confirmApproveBtn.disabled = false;
    });
}

// Initialize admin flow
setTimeout(mhInitializeAdminFlow, 200);

// Also try to initialize admin flow when the modal content is loaded
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(mhInitializeAdminFlow, 500);
    
    // Check if view-only mode should be applied
    <?php if ($view_only): ?>
    setTimeout(function() {
        if (typeof window.mhApplyViewOnlyMode === 'function') {
            window.mhApplyViewOnlyMode();
        }
    }, 800);
    <?php endif; ?>
});

// Force admin flow initialization for admin context
if (typeof window !== 'undefined' && window.location && window.location.href.includes('dashboard-Inventory-System-list-of-donations')) {
    setTimeout(mhInitializeAdminFlow, 1000);
    
    // Check if view-only mode should be applied
    <?php if ($view_only): ?>
    setTimeout(function() {
        if (typeof window.mhApplyViewOnlyMode === 'function') {
            window.mhApplyViewOnlyMode();
        }
    }, 1500);
    <?php endif; ?>
}

// Note: needs_review management is now handled through the form submission process
// with the admin_complete action instead of direct API calls

// Show a small, quiet success toast
function mhShowQuietSuccessToast() {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        font-size: 14px;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    toast.textContent = 'Changes saved';
    document.body.appendChild(toast);
    
    // Show and auto-hide
    setTimeout(() => toast.style.opacity = '1', 10);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 2000);
}

// Show a small, quiet error toast
function mhShowQuietErrorToast(message) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #dc3545;
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        font-size: 14px;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    toast.textContent = 'Error: ' + message;
    document.body.appendChild(toast);
    
    // Show and auto-hide
    setTimeout(() => toast.style.opacity = '1', 10);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 4000);
}
</script>

<?php
// Clean up output buffer and send response
ob_end_flush();
?>
