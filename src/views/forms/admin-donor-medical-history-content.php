<?php
/**
 * Admin Donor Medical History Form Content
 * Step 2 of admin registration flow
 * This is a wrapper that includes the existing admin MH content
 */

// Start output buffering to catch any errors
ob_start();

// Get donor_id from session (set by step 1) or from GET parameter
$donor_id = isset($_SESSION['donor_id']) ? $_SESSION['donor_id'] : (isset($_GET['donor_id']) ? $_GET['donor_id'] : null);

if (!$donor_id) {
    ob_clean();
    echo '<div class="alert alert-danger">Donor ID not found. Please complete step 1 first.</div>';
    ob_end_flush();
    exit();
}

// Set view_only to false for new registration
$view_only = false;

// Set user role for admin context
$user_role = 'admin';

// Fetch donor's sex from donor_form table
$donor_sex = null;
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
    if (!empty($donor_data)) {
        $donor_sex = strtolower($donor_data[0]['sex']);
    }
}

// Fetch existing medical history data (should be empty for new donors)
$medical_history_data = null;
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
    if (!empty($data) && is_array($data)) {
        $medical_history_data = $data[0];
    } else {
        $medical_history_data = [];
    }
} else {
    $medical_history_data = [];
}

// Determine if female (for step 6)
$isFemale = ($donor_sex === 'female');

// Temporarily set $_GET['donor_id'] so the included file can access it
// (medical-history-modal-content-admin.php expects $_GET['donor_id'])
$original_get_donor_id = isset($_GET['donor_id']) ? $_GET['donor_id'] : null;
$_GET['donor_id'] = $donor_id;

// Define a constant for the base path to help with relative includes
// This ensures the included file can find the database connection
if (!defined('BASE_PATH')) {
    // Calculate base path: from src/views/forms/ to project root
    define('BASE_PATH', dirname(dirname(dirname(__DIR__))));
}

// Include the existing admin MH content using absolute path
// Note: We need to modify the form action to work with our modal flow
$include_path = __DIR__ . '/medical-history-modal-content-admin.php';
if (!file_exists($include_path)) {
    ob_clean();
    echo '<div class="alert alert-danger">Medical history form file not found at: ' . htmlspecialchars($include_path) . '</div>';
    ob_end_flush();
    exit();
}

// Start a new output buffer for the included content
ob_start();
$include_error = false;
try {
    include $include_path;
    $content = ob_get_clean();
    
    // Check if content is valid
    if ($content === false || (is_string($content) && trim($content) === '')) {
        throw new Exception('Medical history form content is empty or invalid');
    }
} catch (Exception $e) {
    ob_end_clean();
    $include_error = true;
    $content = '<div class="alert alert-danger">Error loading medical history form: ' . htmlspecialchars($e->getMessage()) . '</div>';
    error_log('Error including medical-history-modal-content-admin.php: ' . $e->getMessage());
} catch (Error $e) {
    ob_end_clean();
    $include_error = true;
    $content = '<div class="alert alert-danger">Fatal error loading medical history form: ' . htmlspecialchars($e->getMessage()) . '</div>';
    error_log('Fatal error including medical-history-modal-content-admin.php: ' . $e->getMessage());
}

// Restore original $_GET['donor_id'] if it existed
if ($original_get_donor_id !== null) {
    $_GET['donor_id'] = $original_get_donor_id;
} else {
    unset($_GET['donor_id']);
}

// Replace form action to use our API endpoint
$content = str_replace(
    'action="medical-history-process-admin.php"',
    'action="../../../public/api/admin-donor-registration-submit.php"',
    $content
);

// Add hidden field for step
$content = str_replace(
    '<input type="hidden" name="donor_id"',
    '<input type="hidden" name="step" value="2">
    <input type="hidden" name="donor_id"',
    $content
);

// Replace the action hidden field to use admin_complete for new registration
$content = str_replace(
    '<input type="hidden" name="action" id="modalSelectedAction" value="">',
    '<input type="hidden" name="action" id="modalSelectedAction" value="admin_complete">',
    $content
);

echo $content;
?>

