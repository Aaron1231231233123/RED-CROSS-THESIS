<?php
/**
 * Admin Donor Medical History Form Content
 * Step 2 of admin registration flow
 * This is a wrapper that includes the existing admin MH content
 */

// Get donor_id from session (set by step 1)
$donor_id = isset($_SESSION['donor_id']) ? $_SESSION['donor_id'] : null;

if (!$donor_id) {
    echo '<div class="alert alert-danger">Donor ID not found. Please complete step 1 first.</div>';
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

// Include the existing admin MH content
// Note: We need to modify the form action to work with our modal flow
ob_start();
include 'medical-history-modal-content-admin.php';
$content = ob_get_clean();

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

