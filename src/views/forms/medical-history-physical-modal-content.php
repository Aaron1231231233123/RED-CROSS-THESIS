<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get donor_id from request
$donor_id = isset($_GET['donor_id']) ? $_GET['donor_id'] : null;

if (!$donor_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing donor_id']);
    exit();
}

// Set donor_id in session for form processing
$_SESSION['donor_id'] = $donor_id;

// Debug: Check what's in the session
echo "<!-- Session Debug: donor_id = " . htmlspecialchars($donor_id) . " -->";
echo "<!-- Session Debug: screening_id = " . (isset($_SESSION['screening_id']) ? htmlspecialchars($_SESSION['screening_id']) : 'NOT SET') . " -->";
echo "<!-- Session Debug: All session vars = " . htmlspecialchars(print_r($_SESSION, true)) . " -->";

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Role not set']);
    exit();
}

$role_id = (int)$_SESSION['role_id'];

if ($role_id !== 1 && $role_id !== 3) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid role']);
    exit();
}

// For staff role, get the staff role type
$user_role = '';
if ($role_id === 3) {
    if (isset($_SESSION['user_staff_role'])) {
        $user_role = strtolower($_SESSION['user_staff_role']);
    } elseif (isset($_SESSION['user_staff_roles'])) {
        $user_role = strtolower($_SESSION['user_staff_roles']);
    } elseif (isset($_SESSION['staff_role'])) {
        $user_role = strtolower($_SESSION['staff_role']);
    }
    
    $valid_roles = ['reviewer', 'interviewer', 'physician'];
    if (!in_array($user_role, $valid_roles)) {
        $user_role = 'physician'; // Default to physician for physical dashboard
    }
}

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
    if (!empty($data)) {
        $medical_history_data = $data[0];
        error_log("Medical history data found: " . json_encode($medical_history_data));
    } else {
        error_log("No medical history data found for donor_id: $donor_id");
    }
} else {
    error_log("Failed to fetch medical history data. HTTP code: $http_code");
}
?>

<!-- Include Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Include Medical History Approval CSS -->
<link rel="stylesheet" href="../../assets/css/medical-history-approval-modals.css">

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
    
    /* Bootstrap Button Styles for Medical History Approval */
    .btn {
        display: inline-block;
        font-weight: 400;
        line-height: 1.5;
        text-align: center;
        text-decoration: none;
        vertical-align: middle;
        cursor: pointer;
        user-select: none;
        border: 1px solid transparent;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        border-radius: 0.375rem;
        transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .btn-outline-danger {
        color: #dc3545;
        border-color: #dc3545;
        background-color: transparent;
    }
    
    .btn-outline-danger:hover {
        color: #fff;
        background-color: #dc3545;
        border-color: #dc3545;
    }
    
    .btn-success {
        color: #fff;
        background-color: #198754;
        border-color: #198754;
    }
    
    .btn-success:hover {
        color: #fff;
        background-color: #157347;
        border-color: #146c43;
    }
    
    .px-4 {
        padding-left: 1.5rem !important;
        padding-right: 1.5rem !important;
    }
    
    .me-2 {
        margin-right: 0.5rem !important;
    }
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
        content: "✏️";
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

<form method="POST" action="medical-history-process.php" id="modalMedicalHistoryForm">
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
        <button class="prev-button" id="modalPrevButton" style="display: none;">&#8592; Previous</button>
        <button class="edit-button" id="modalEditButton" style="margin-right: 10px;">Edit</button>
        <?php
        // Try to get screening_id from multiple sources
        $screening_id = null;
        
        // First try session
        if (isset($_SESSION['screening_id']) && !empty($_SESSION['screening_id'])) {
            $screening_id = $_SESSION['screening_id'];
        }
        // Then try GET parameter
        elseif (isset($_GET['screening_id']) && !empty($_GET['screening_id'])) {
            $screening_id = $_GET['screening_id'];
        }
        // Then try to extract from medical history data if available
        elseif ($medical_history_data && isset($medical_history_data['screening_id'])) {
            $screening_id = $medical_history_data['screening_id'];
        }
        // Try to get from screening_form table using donor_id
        elseif ($donor_id) {
            $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_id=eq.' . $donor_id . '&select=screening_id&order=created_at.desc&limit=1');
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $screening_data = json_decode($response, true);
                if (!empty($screening_data)) {
                    $screening_id = $screening_data[0]['screening_id'];
                    echo "<!-- Found screening_id from screening_form: $screening_id -->";
                } else {
                    echo "<!-- No screening data found for donor_id: $donor_id -->";
                }
            } else {
                echo "<!-- Failed to fetch screening data. HTTP code: $http_code -->";
            }
        }
        // Try to get from physical_examination table using donor_id
        elseif ($donor_id) {
            $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id . '&select=screening_id&order=created_at.desc&limit=1');
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $physical_data = json_decode($response, true);
                if (!empty($physical_data)) {
                    $screening_id = $physical_data[0]['screening_id'];
                    echo "<!-- Found screening_id from physical_examination: $screening_id -->";
                } else {
                    echo "<!-- No physical examination data found for donor_id: $donor_id -->";
                }
            } else {
                echo "<!-- Failed to fetch physical examination data. HTTP code: $http_code -->";
            }
        }
        // Finally, try to get from donor profile if available
        elseif (isset($_SESSION['current_screening_id'])) {
            $screening_id = $_SESSION['current_screening_id'];
        }
        
        // If still no screening_id, use a placeholder
        if (!$screening_id) {
            $screening_id = 'no-screening-id';
        }
        ?>
        
        <button type="button" class="btn btn-outline-danger decline-medical-history-btn px-4" 
                data-donor-id="<?php echo htmlspecialchars($donor_id); ?>">
            <i class="fas fa-times-circle me-2"></i>Decline
        </button>
        <button class="next-button" id="modalNextButton">Next →</button>
    </div>
</div>

<!-- Data for JavaScript -->
<script type="application/json" id="modalData">
{
    "medicalHistoryData": <?php echo $medical_history_data ? json_encode($medical_history_data) : 'null'; ?>,
    "donorSex": <?php echo json_encode(strtolower($donor_sex)); ?>,
    "userRole": <?php echo json_encode($user_role); ?>
}
</script>
 
<script>
// Enhanced Edit Button Functionality
function initializeEditFunctionality() {
    console.log('=== EDIT FUNCTIONALITY INITIALIZATION START ===');
    const editButton = document.getElementById('modalEditButton');
    
    if (editButton) {
        console.log('✅ Edit button found, initializing functionality...');
        
        // Remove any existing event listeners
        editButton.replaceWith(editButton.cloneNode(true));
        const newEditButton = document.getElementById('modalEditButton');
        
        newEditButton.addEventListener('click', function() {
            console.log('🎯 Edit button clicked - starting to enable fields...');
            
            // Enable editing of form fields
            const form = document.getElementById('modalMedicalHistoryForm');
            const radioButtons = form.querySelectorAll('input[type="radio"]');
            const selectFields = form.querySelectorAll('select.remarks-input');
            const textInputs = form.querySelectorAll('input[type="text"], textarea');
            
            console.log('Found radio buttons:', radioButtons.length);
            console.log('Found select fields:', selectFields.length);
            console.log('Found text inputs:', textInputs.length);
            
            // Enable all form inputs
            radioButtons.forEach(input => {
                input.disabled = false;
                input.readOnly = false;
                console.log('Enabled radio button:', input.name);
            });
            
            selectFields.forEach(input => {
                input.disabled = false;
                input.readOnly = false;
                console.log('Enabled select field:', input.name);
            });
            
            textInputs.forEach(input => {
                input.disabled = false;
                input.readOnly = false;
                console.log('Enabled text input:', input.name);
            });
            
            // Change button text to indicate editing mode
            newEditButton.textContent = 'Save';
            newEditButton.classList.remove('edit-button');
            newEditButton.classList.add('save-button');
            
            // Add save functionality
            newEditButton.onclick = function() {
                console.log('Save button clicked');
                showSaveConfirmationModal();
            };
        });
    } else {
        console.log('❌ Edit button not found - checking DOM...');
        console.log('Available buttons:', document.querySelectorAll('button').length);
        console.log('Modal content:', document.getElementById('medicalHistoryModalContent')?.innerHTML?.substring(0, 200));
    }
    console.log('=== EDIT FUNCTIONALITY INITIALIZATION END ===');
}

function saveEditedData() {
    const form = document.getElementById('modalMedicalHistoryForm');
    const formData = new FormData(form);
    
    // Add action for saving
    formData.append('action', 'save_edit');
    
    console.log('Saving edited data...');
    
    // Submit the form data
    fetch('../../src/views/forms/medical-history-process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message using custom modal
            if (window.customConfirm || window.mhCustomConfirm) {
                (window.customConfirm || window.mhCustomConfirm)('Medical history data saved successfully!', function() {
                    // Just close the modal, no additional action needed
                });
            } else {
                alert('Medical history data saved successfully!');
            }
            
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
            // Show error modal
            showErrorModal('Error updating data: ' + (data.message || 'Unknown error occurred'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Show error modal
        showErrorModal('An error occurred while saving the data. Please try again.');
    });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEditFunctionality);
} else {
    initializeEditFunctionality();
}

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

// Also initialize when the modal content is dynamically loaded
if (typeof window !== 'undefined') {
    window.initializeEditFunctionality = initializeEditFunctionality;
    window.mhCustomConfirm = mhCustomConfirm;
    console.log('✅ initializeEditFunctionality and mhCustomConfirm exposed to global scope');
}
</script>

<!-- Save Confirmation Modal -->
<div class="modal fade" id="saveConfirmationModal" tabindex="-1" aria-labelledby="saveConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="saveConfirmationModalLabel">
                    <i class="fas fa-save me-2"></i>
                    Confirm Save
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0" style="font-size: 1.1rem;">Are you sure you want to save the medical history data?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn px-4" style="background-color: #b22222; border-color: #b22222; color: white;" id="confirmSaveBtn">Yes, Save</button>
            </div>
        </div>
    </div>
</div>

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
// Function to show save confirmation modal
function showSaveConfirmationModal() {
    const saveConfirmationModal = new bootstrap.Modal(document.getElementById('saveConfirmationModal'));
    saveConfirmationModal.show();
}

// Function to show error modal
function showErrorModal(message) {
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    const errorMessage = document.getElementById('errorMessage');
    if (errorMessage) {
        errorMessage.textContent = message;
    }
    errorModal.show();
}

// Update the save button click handler to show confirmation first
function bindConfirmSaveHandler() {
    const confirmSaveBtn = document.getElementById('confirmSaveBtn');
    if (!confirmSaveBtn) return;
    // Replace existing handlers to avoid duplicates
    const newBtn = confirmSaveBtn.cloneNode(true);
    confirmSaveBtn.parentNode.replaceChild(newBtn, confirmSaveBtn);
    newBtn.addEventListener('click', function() {
        const saveConfirmationModal = bootstrap.Modal.getInstance(document.getElementById('saveConfirmationModal'));
        if (saveConfirmationModal) {
            saveConfirmationModal.hide();
        }
        saveEditedData();
    });
}

// Bind immediately (content is already in DOM when this script runs)
bindConfirmSaveHandler();

// Also re-bind whenever the modal is shown (in case the DOM was re-rendered)
const saveConfirmEl = document.getElementById('saveConfirmationModal');
if (saveConfirmEl) {
    saveConfirmEl.addEventListener('shown.bs.modal', bindConfirmSaveHandler);
}

// Override the save button click to show confirmation first
function initializeSaveConfirmation() {
    const saveButton = document.getElementById('modalEditButton');
    if (saveButton && saveButton.textContent === 'Save') {
        saveButton.onclick = function() {
            console.log('Save button clicked - showing confirmation');
            showSaveConfirmationModal();
        };
    }
}

// Call this after the edit functionality is initialized
setTimeout(initializeSaveConfirmation, 100);

// Initialize medical history approval functionality
setTimeout(() => {
    if (typeof initializeMedicalHistoryApproval === 'function') {
        initializeMedicalHistoryApproval();
    } else {
        console.log('Medical history approval functions not loaded yet');
    }
}, 200);

// Debug: Check modal loading status
setTimeout(() => {
    const declineModal = document.getElementById('medicalHistoryDeclineModal');
    const approvalModal = document.getElementById('medicalHistoryApprovalModal');
    
    const declineStatus = document.getElementById('declineModalStatus');
    const approvalStatus = document.getElementById('approvalModalStatus');
    
    if (declineStatus) {
        declineStatus.textContent = declineModal ? 'Found' : 'Not Found';
        declineStatus.style.color = declineModal ? 'green' : 'red';
    }
    
    if (approvalStatus) {
        approvalStatus.textContent = approvalModal ? 'Found' : 'Not Found';
        approvalStatus.style.color = approvalModal ? 'green' : 'red';
    }
    
    console.log('Modal Debug - Decline:', declineModal, 'Approval:', approvalModal);
}, 500);
</script>

<!-- Include Medical History Approval Modals -->
<?php 
echo "<!-- Loading medical history approval modals... -->";
$modalPath = '../modals/medical-history-approval-modals.php';
if (file_exists($modalPath)) {
    include $modalPath;
    echo "<!-- Medical history approval modals loaded successfully -->";
} else {
    echo "<!-- ERROR: Modal file not found at: $modalPath -->";
    echo "<!-- Current directory: " . __DIR__ . " -->";
}
?>

<!-- Debug: Check if modals are loaded -->
<div id="modalDebugInfo" style="display: none;">
    <p>Decline Modal: <span id="declineModalStatus">Checking...</span></p>
    <p>Approval Modal: <span id="approvalModalStatus">Checking...</span></p>
</div>

<!-- Fallback Modal for Testing (remove this after fixing the main modal) -->
<div class="modal fade" id="fallbackDeclineModal" tabindex="-1" aria-labelledby="fallbackDeclineModalLabel" aria-hidden="true" style="z-index: 99999 !important;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="fallbackDeclineModalLabel">Decline Medical History (Fallback)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This is a fallback modal for testing. The main modal system should be working.</p>
                <p>If you see this, there's an issue with the main modal include.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include Medical History Approval CSS -->
<link rel="stylesheet" href="../../assets/css/medical-history-approval-modals.css">

<!-- Include Medical History Approval JavaScript -->
<script src="../../assets/js/medical-history-approval.js"></script>
