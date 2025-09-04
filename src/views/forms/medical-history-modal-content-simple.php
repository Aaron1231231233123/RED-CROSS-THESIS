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
        $user_role = 'interviewer'; // Default fallback
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
    if (!empty($data)) {
        $medical_history_data = $data[0];
        error_log("Medical history data found for donor_id $donor_id: " . json_encode($medical_history_data));
    } else {
        error_log("No medical history data found for donor_id: $donor_id");
    }
} else {
    error_log("Failed to fetch medical history data. HTTP code: $http_code");
}

// Fetch donor information for display
$ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . $donor_id . '&select=first_name,last_name,sex,age,blood_type');

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY
]);

$response = curl_exec($ch);
$donor_info = null;
if ($response !== false) {
    $donor_info = json_decode($response, true);
    if (!empty($donor_info)) {
        $donor_info = $donor_info[0];
    }
}
curl_close($ch);
?>

<style>
.medical-history-modal-content {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 100%;
    margin: 0 auto;
    background: #fff;
}

.medical-history-header {
    background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    text-align: center;
}

.donor-info-display {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}

.donor-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.donor-info-item {
    display: flex;
    flex-direction: column;
}

.donor-info-label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.donor-info-value {
    font-size: 1.1rem;
    color: #212529;
    font-weight: 500;
}

.medical-history-form {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.form-step {
    display: none;
    padding: 25px;
}

.form-step.active {
    display: block;
}

.step-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #b22222;
    margin-bottom: 10px;
    text-align: center;
    border-bottom: 2px solid #b22222;
    padding-bottom: 10px;
}

.step-description {
    text-align: center;
    color: #6c757d;
    margin-bottom: 20px;
    font-style: italic;
}

.form-container {
    display: grid;
    grid-template-columns: 60px 1fr 80px 80px 150px;
    gap: 1px;
    background: #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 20px;
}

.form-header {
    font-weight: bold;
    text-align: center;
    background-color: #9c0000;
    color: white;
    padding: 15px 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 50px;
    font-size: 14px;
}

.question-number {
    text-align: center;
    font-weight: bold;
    padding: 15px 10px;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60px;
    background-color: #fff;
}

.question-text {
    padding: 15px;
    font-size: 14px;
    line-height: 1.4;
    display: flex;
    align-items: center;
    min-height: 60px;
    background-color: #fff;
    word-wrap: break-word;
    text-align: left;
}

.radio-cell {
    text-align: center;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 15px 10px;
    min-height: 60px;
    background-color: #fff;
}

.radio-container {
    position: relative;
    cursor: pointer;
    display: inline-block;
    margin: 0 auto;
    width: 20px;
    height: 20px;
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
    border: 2px solid #ccc;
    border-radius: 50%;
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
    left: 6px;
    top: 6px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: white;
}

.remarks-cell {
    padding: 15px 10px;
    display: flex;
    align-items: center;
    min-height: 60px;
    background-color: #fff;
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

.form-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    border-top: 1px solid #e0e0e0;
    margin-top: 20px;
}

.nav-button {
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    border: none;
}

.prev-button {
    background-color: #6c757d;
    color: white;
}

.prev-button:hover {
    background-color: #5a6268;
}

.next-button, .submit-button {
    background-color: #b22222;
    color: white;
}

.next-button:hover, .submit-button:hover {
    background-color: #8b0000;
}

.error-message {
    color: #dc3545;
    font-size: 14px;
    margin: 15px auto;
    text-align: center;
    font-weight: bold;
    display: none;
    max-width: 80%;
}

.question-container.error {
    background-color: rgba(255, 0, 0, 0.05);
    border-left: 3px solid #dc3545;
    padding-left: 10px;
}

.question-highlight {
    color: #dc3545 !important;
    font-weight: bold;
}

@media (max-width: 768px) {
    .form-container {
        grid-template-columns: 50px 1fr 70px 70px 120px;
        font-size: 12px;
    }
    
    .form-header, .question-number, .question-text, .radio-cell, .remarks-cell {
        padding: 10px 8px;
        min-height: 50px;
    }
    
    .step-title {
        font-size: 1.1rem;
    }
}
</style>

<div class="medical-history-modal-content">
    <!-- Header with Donor Information -->
    <div class="medical-history-header">
        <h3><i class="fas fa-file-medical me-2"></i>Medical History Review</h3>
        <p class="mb-0">Review and approve donor's medical history information</p>
    </div>

    <!-- Donor Information Display -->
    <?php if ($donor_info): ?>
    <div class="donor-info-display">
        <h5><i class="fas fa-user me-2"></i>Donor Information</h5>
        <div class="donor-info-grid">
            <div class="donor-info-item">
                <span class="donor-info-label">Name</span>
                <span class="donor-info-value"><?php echo htmlspecialchars($donor_info['first_name'] . ' ' . $donor_info['last_name']); ?></span>
            </div>
            <div class="donor-info-item">
                <span class="donor-info-label">Age</span>
                <span class="donor-info-value"><?php echo htmlspecialchars($donor_info['age'] ?? 'N/A'); ?></span>
            </div>
            <div class="donor-info-item">
                <span class="donor-info-label">Sex</span>
                <span class="donor-info-value"><?php echo htmlspecialchars(ucfirst($donor_info['sex'] ?? 'N/A')); ?></span>
            </div>
            <div class="donor-info-item">
                <span class="donor-info-label">Blood Type</span>
                <span class="donor-info-value"><?php echo htmlspecialchars($donor_info['blood_type'] ?? 'N/A'); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Medical History Form -->
    <div class="medical-history-form">
        <form method="POST" action="medical-history-process.php" id="modalMedicalHistoryForm">
            <input type="hidden" name="donor_id" value="<?php echo htmlspecialchars($donor_id); ?>">
            <input type="hidden" name="action" id="modalSelectedAction" value="">
            
            <!-- Step 1: Health & Risk Assessment -->
            <div class="form-step active" data-step="1">
                <div class="step-title">HEALTH & RISK ASSESSMENT</div>
                <div class="step-description">Tick the appropriate answer.</div>
                
                <div class="form-container" data-step-container="1">
                    <div class="form-header">#</div>
                    <div class="form-header">Question</div>
                    <div class="form-header">YES</div>
                    <div class="form-header">NO</div>
                    <div class="form-header">REMARKS</div>
                    
                    <div class="question-number">1</div>
                    <div class="question-text">Are you feeling healthy and well today?</div>
                    <div class="radio-cell">
                        <label class="radio-container">
                            <input type="radio" name="q1" value="yes" <?php echo (isset($medical_history_data['q1']) && $medical_history_data['q1'] === 'yes') ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                        </label>
                    </div>
                    <div class="radio-cell">
                        <label class="radio-container">
                            <input type="radio" name="q1" value="no" <?php echo (isset($medical_history_data['q1']) && $medical_history_data['q1'] === 'no') ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                        </label>
                    </div>
                    <div class="remarks-cell">
                        <input type="text" class="remarks-input" name="q1_remarks" value="<?php echo htmlspecialchars($medical_history_data['q1_remarks'] ?? ''); ?>" placeholder="Any remarks...">
                    </div>
                    
                    <div class="question-number">2</div>
                    <div class="question-text">Do you have any current illness or infection?</div>
                    <div class="radio-cell">
                        <label class="radio-container">
                            <input type="radio" name="q2" value="yes" <?php echo (isset($medical_history_data['q2']) && $medical_history_data['q2'] === 'yes') ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                        </label>
                    </div>
                    <div class="radio-cell">
                        <label class="radio-container">
                            <input type="radio" name="q2" value="no" <?php echo (isset($medical_history_data['q2']) && $medical_history_data['q2'] === 'no') ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                        </label>
                    </div>
                    <div class="remarks-cell">
                        <input type="text" class="remarks-input" name="q2_remarks" value="<?php echo htmlspecialchars($medical_history_data['q2_remarks'] ?? ''); ?>" placeholder="Any remarks...">
                    </div>
                </div>
                
                <div class="form-navigation">
                    <button type="button" class="nav-button prev-button" onclick="previousStep(1)" style="display: none;">Previous</button>
                    <button type="button" class="nav-button next-button" onclick="nextStep(1)">Next</button>
                </div>
            </div>
            
            <!-- Step 2: Past 6 Months -->
            <div class="form-step" data-step="2">
                <div class="step-title">IN THE PAST 6 MONTHS HAVE YOU</div>
                <div class="step-description">Tick the appropriate answer.</div>
                
                <div class="form-container" data-step-container="2">
                    <div class="form-header">#</div>
                    <div class="form-header">Question</div>
                    <div class="form-header">YES</div>
                    <div class="form-header">NO</div>
                    <div class="form-header">REMARKS</div>
                    
                    <div class="question-number">3</div>
                    <div class="question-text">Had surgery or been hospitalized?</div>
                    <div class="radio-cell">
                        <label class="radio-container">
                            <input type="radio" name="q3" value="yes" <?php echo (isset($medical_history_data['q3']) && $medical_history_data['q3'] === 'yes') ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                        </label>
                    </div>
                    <div class="radio-cell">
                        <label class="radio-container">
                            <input type="radio" name="q3" value="no" <?php echo (isset($medical_history_data['q3']) && $medical_history_data['q3'] === 'no') ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                        </label>
                    </div>
                    <div class="remarks-cell">
                        <input type="text" class="remarks-input" name="q3_remarks" value="<?php echo htmlspecialchars($medical_history_data['q3_remarks'] ?? ''); ?>" placeholder="Any remarks...">
                    </div>
                </div>
                
                <div class="form-navigation">
                    <button type="button" class="nav-button prev-button" onclick="previousStep(2)">Previous</button>
                    <button type="button" class="nav-button next-button" onclick="nextStep(2)">Next</button>
                </div>
            </div>
            
            <!-- Additional steps would continue here... -->
            
            <div class="error-message" id="modalValidationError">Please answer all questions before proceeding to the next step.</div>
        </form>
    </div>
</div>

<script>
// Simple step navigation for the modal content
function nextStep(currentStep) {
    const currentStepEl = document.querySelector(`[data-step="${currentStep}"]`);
    const nextStepEl = document.querySelector(`[data-step="${currentStep + 1}"]`);
    
    if (nextStepEl) {
        currentStepEl.classList.remove('active');
        nextStepEl.classList.add('active');
        
        // Update navigation buttons
        const prevBtn = currentStepEl.querySelector('.prev-button');
        const nextBtn = currentStepEl.querySelector('.next-button');
        
        if (prevBtn) prevBtn.style.display = 'block';
        if (nextBtn) nextBtn.textContent = 'Next';
        
        // Check if this is the last step
        const nextNextStep = document.querySelector(`[data-step="${currentStep + 2}"]`);
        if (!nextNextStep) {
            nextBtn.textContent = 'Submit';
            nextBtn.classList.remove('next-button');
            nextBtn.classList.add('submit-button');
        }
    }
}

function previousStep(currentStep) {
    const currentStepEl = document.querySelector(`[data-step="${currentStep}"]`);
    const prevStepEl = document.querySelector(`[data-step="${currentStep - 1}"]`);
    
    if (prevStepEl) {
        currentStepEl.classList.remove('active');
        prevStepEl.classList.add('active');
        
        // Update navigation buttons
        const prevBtn = prevStepEl.querySelector('.prev-button');
        const nextBtn = prevStepEl.querySelector('.next-button');
        
        if (prevBtn && currentStep - 1 === 1) prevBtn.style.display = 'none';
        if (nextBtn) {
            nextBtn.textContent = 'Next';
            nextBtn.classList.remove('submit-button');
            nextBtn.classList.add('next-button');
        }
    }
}

// Initialize form when loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Medical history form content loaded successfully');
});
</script>


