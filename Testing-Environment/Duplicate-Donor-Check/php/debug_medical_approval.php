<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Simple debug for donor 140 (Serue, Michael)
echo "<h2>Debug for Donor 140 (Serue, Michael)</h2>";

// Get donor form data
$donor_ch = curl_init();
curl_setopt_array($donor_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.140&select=donor_id,surname,first_name',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$donor_response = curl_exec($donor_ch);
$donor_data = json_decode($donor_response, true);
curl_close($donor_ch);

echo "<h3>Donor Data:</h3>";
echo "<pre>";
print_r($donor_data);
echo "</pre>";

// Get medical history data for donor 140
$medical_ch = curl_init();
curl_setopt_array($medical_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.140&select=donor_id,medical_history_id,medical_approval',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$medical_response = curl_exec($medical_ch);
$medical_data = json_decode($medical_response, true);
curl_close($medical_ch);

echo "<h3>Medical History Data for Donor 140:</h3>";
echo "<pre>";
print_r($medical_data);
echo "</pre>";

// Check if donor has eligibility
$eligibility_ch = curl_init();
curl_setopt_array($eligibility_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.140&select=donor_id',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$eligibility_response = curl_exec($eligibility_ch);
$eligibility_data = json_decode($eligibility_response, true);
curl_close($eligibility_ch);

echo "<h3>Eligibility Data for Donor 140:</h3>";
echo "<pre>";
print_r($eligibility_data);
echo "</pre>";

// Test the getDonorType function
function getDonorType($donor_id, $medical_info, $eligibility_by_donor, $stage = 'medical_review') {
    // Check if donor has eligibility record (Returning donor)
    $has_eligibility = isset($eligibility_by_donor[$donor_id]);
    
    // Check if medical approval is approved (not empty and not null)
    $is_approved = ($medical_info && isset($medical_info['medical_approval']) && !empty($medical_info['medical_approval']) && $medical_info['medical_approval'] !== null);
    
    // Determine the stage suffix based on the current stage with prioritization
    $stage_suffix = '';
    switch ($stage) {
        case 'blood_collection':
            $stage_suffix = 'Completed'; // Highest priority
            break;
        case 'physical_examination':
            $stage_suffix = 'Collection'; // High priority
            break;
        case 'screening_form':
            $stage_suffix = 'Physical'; // Medium priority
            break;
        case 'medical_review':
            $stage_suffix = $is_approved ? 'Screening' : 'Medical'; // Lowest priority
            break;
        default:
            $stage_suffix = 'Medical'; // Default lowest priority
    }
    
    if ($has_eligibility) {
        // Returning donor
        return 'Returning (' . $stage_suffix . ')';
    } else {
        // New donor
        return 'New (' . $stage_suffix . ')';
    }
}

// Test the function
$medical_info = $medical_data[0] ?? null;
$eligibility_by_donor = [];
if ($eligibility_data && count($eligibility_data) > 0) {
    $eligibility_by_donor[140] = $eligibility_data[0];
}

echo "<h3>Function Test:</h3>";
echo "<p>Medical Info: " . ($medical_info ? 'EXISTS' : 'NULL') . "</p>";
echo "<p>Medical Approval Value: " . ($medical_info ? ($medical_info['medical_approval'] ?? 'NULL') : 'N/A') . "</p>";
echo "<p>Is Approved: " . (($medical_info && isset($medical_info['medical_approval']) && !empty($medical_info['medical_approval']) && $medical_info['medical_approval'] !== null) ? 'YES' : 'NO') . "</p>";
echo "<p>Has Eligibility: " . (isset($eligibility_by_donor[140]) ? 'YES' : 'NO') . "</p>";
echo "<p>Donor Type: " . getDonorType(140, $medical_info, $eligibility_by_donor, 'medical_review') . "</p>";
?>
