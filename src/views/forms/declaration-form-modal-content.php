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

// Fetch donor information
$donorData = null;

try {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . $donor_id);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $donorData = json_decode($response, true);
        if (!is_array($donorData) || empty($donorData)) {
            throw new Exception("Donor not found with ID: " . $donor_id);
        }
        $donorData = $donorData[0]; // Get the first result
    } else {
        throw new Exception("Failed to fetch donor data. HTTP Code: " . $http_code);
    }
} catch (Exception $e) {
    error_log("Error fetching donor data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error retrieving donor information']);
    exit();
}

// Calculate age from birthdate if not in donor data
if (!isset($donorData['age']) && isset($donorData['birthdate'])) {
    $birthdate = new DateTime($donorData['birthdate']);
    $today = new DateTime();
    $donorData['age'] = $birthdate->diff($today)->y;
}

// Format today's date
$today = date('F d, Y');
?>

<style>
    .declaration-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px 0;
        border-bottom: 2px solid #9c0000;
    }
    
    .declaration-header h2 {
        color: #9c0000;
        font-weight: bold;
        font-size: 24px;
        margin-bottom: 5px;
    }
    
    .declaration-header h3 {
        color: #9c0000;
        font-weight: 600;
        font-size: 20px;
        margin: 0;
    }
    
    .donor-info {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
        border: 1px solid #e0e0e0;
    }
    
    .donor-info-row {
        display: flex;
        flex-wrap: wrap;
        margin-bottom: 15px;
        gap: 20px;
    }
    
    .donor-info-item {
        flex: 1;
        min-width: 200px;
    }
    
    .donor-info-label {
        font-weight: bold;
        font-size: 14px;
        color: #555;
        margin-bottom: 5px;
    }
    
    .donor-info-value {
        font-size: 16px;
        color: #333;
        font-weight: 500;
    }
    
    .declaration-content {
        text-align: justify;
        line-height: 1.8;
        margin: 30px 0;
        font-size: 15px;
    }
    
    .declaration-content p {
        margin-bottom: 20px;
    }
    
    .bold {
        font-weight: bold;
        color: #9c0000;
    }
    
    .signature-section {
        margin-top: 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 0;
        border-top: 1px solid #e0e0e0;
    }
    
    .signature-box {
        text-align: center;
        padding: 15px 0;
        border-top: 2px solid #333;
        width: 250px;
        font-weight: 500;
        color: #555;
    }
    
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
    
    .modal-btn {
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
        transition: all 0.3s ease;
        border: none;
    }
    
    .modal-btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    
    .modal-btn-secondary:hover {
        background-color: #5a6268;
    }
    
    .modal-btn-success {
        background-color: #9c0000;
        color: white;
    }
    
    .modal-btn-success:hover {
        background-color: #7e0000;
    }
    
    .modal-btn-print {
        background-color: #17a2b8;
        color: white;
        margin-right: 10px;
    }
    
    .modal-btn-print:hover {
        background-color: #138496;
    }
</style>

<div class="declaration-header">
    <h2>PHILIPPINE RED CROSS</h2>
    <h3>BLOOD DONOR DECLARATION FORM</h3>
</div>

<div class="donor-info">
    <div class="donor-info-row">
        <div class="donor-info-item">
            <div class="donor-info-label">Donor Name:</div>
            <div class="donor-info-value">
                <?php 
                // Create full name from components with proper validation
                $surname = isset($donorData['surname']) ? htmlspecialchars(trim($donorData['surname'])) : '';
                $firstName = isset($donorData['first_name']) ? htmlspecialchars(trim($donorData['first_name'])) : '';
                $middleName = isset($donorData['middle_name']) ? htmlspecialchars(trim($donorData['middle_name'])) : '';
                
                // Build the full name with proper formatting
                $fullName = $surname;
                if (!empty($firstName)) {
                    $fullName .= ', ' . $firstName;
                }
                if (!empty($middleName)) {
                    $fullName .= ' ' . $middleName;
                }
                
                echo $fullName;
                ?>
            </div>
        </div>
        <div class="donor-info-item">
            <div class="donor-info-label">Age:</div>
            <div class="donor-info-value"><?php echo isset($donorData['age']) ? htmlspecialchars($donorData['age']) : ''; ?></div>
        </div>
        <div class="donor-info-item">
            <div class="donor-info-label">Sex:</div>
            <div class="donor-info-value"><?php echo isset($donorData['sex']) ? htmlspecialchars($donorData['sex']) : ''; ?></div>
        </div>
    </div>
    <div class="donor-info-row">
        <div class="donor-info-item">
            <div class="donor-info-label">Address:</div>
            <div class="donor-info-value"><?php echo isset($donorData['permanent_address']) ? htmlspecialchars($donorData['permanent_address']) : ''; ?></div>
        </div>
    </div>
</div>

<div class="declaration-content">
    <p>I hereby voluntarily donate my blood to the Philippine Red Cross, which is authorized to withdraw my blood and utilize it in any way they deem advisable. I understand this is a voluntary donation and I will receive no payment.</p>
    
    <p>I have been <span class="bold">properly advised</span> on the blood donation procedure, including possible discomfort (needle insertion) and risks (temporary dizziness, fainting, bruising, or rarely, infection at the needle puncture site).</p>
    
    <p>I confirm that I have given <span class="bold">truthful answers</span> to all questions during the medical interview and donor screening process. I understand the significance of providing accurate information for my safety and the safety of potential recipients.</p>
    
    <p>I understand that my blood will be <span class="bold">tested for infectious diseases</span> including HIV, Hepatitis B, Hepatitis C, Syphilis, and Malaria. I consent to these tests being performed, and if any test is reactive, my blood donation will be discarded and I will be notified.</p>
    
    <p>I <span class="bold">authorize the Philippine Red Cross</span> to contact me for notification of any test results requiring medical attention and for future blood donation campaigns.</p>
</div>

<div class="signature-section">
    <div class="signature-box">
        Donor's Signature
    </div>
    <div class="signature-box">
        Date: <?php echo $today; ?>
    </div>
</div>

<form method="POST" action="declaration-form-process.php" id="modalDeclarationForm">
    <input type="hidden" name="donor_id" value="<?php echo htmlspecialchars($donor_id); ?>">
    <input type="hidden" name="action" id="modalDeclarationAction" value="">
</form>

<div class="modal-footer">
    <div class="footer-left"></div>
    <div class="footer-right">
        <button type="button" class="modal-btn modal-btn-print" onclick="printDeclaration()">
            <i class="fas fa-print"></i> Print Declaration
        </button>
        <button type="button" class="modal-btn modal-btn-success" onclick="submitDeclarationForm()">
            <i class="fas fa-check-circle"></i> Complete Registration
        </button>
    </div>
</div>

<!-- Data for JavaScript -->
<script type="application/json" id="modalDeclarationData">
{
    "donorData": <?php echo json_encode($donorData); ?>,
    "userRole": <?php echo json_encode($user_role); ?>
}
</script>

<script>
// Functions are now globally available from the main dashboard
// Print and submit functions are handled by the parent window
console.log('Declaration form modal content loaded');
</script> 