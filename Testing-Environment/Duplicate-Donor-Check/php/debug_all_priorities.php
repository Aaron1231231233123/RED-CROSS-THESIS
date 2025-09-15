<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /RED-CROSS-THESIS/public/login.php");
    exit();
}

// Get all data (same as main dashboard)
$donor_ch = curl_init();
curl_setopt_array($donor_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form?select=*&order=submitted_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$donor_response = curl_exec($donor_ch);
$donor_forms = json_decode($donor_response, true) ?: [];
curl_close($donor_ch);

$medical_ch = curl_init();
curl_setopt_array($medical_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/medical_history?select=*&order=created_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$medical_response = curl_exec($medical_ch);
$medical_histories = json_decode($medical_response, true) ?: [];
curl_close($medical_ch);

$screening_ch = curl_init();
curl_setopt_array($screening_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=*&order=created_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$screening_response = curl_exec($screening_ch);
$screening_forms = json_decode($screening_response, true) ?: [];
curl_close($screening_ch);

$physical_ch = curl_init();
curl_setopt_array($physical_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?select=*&order=created_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$physical_response = curl_exec($physical_ch);
$physical_exams = json_decode($physical_response, true) ?: [];
curl_close($physical_ch);

$blood_ch = curl_init();
curl_setopt_array($blood_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?select=*&order=start_time.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$blood_response = curl_exec($blood_ch);
$blood_collections = json_decode($blood_response, true) ?: [];
curl_close($blood_ch);

// Create lookup arrays (same as main dashboard)
$donors_by_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_id[$donor['donor_id']] = $donor;
}

$donors_by_form_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_form_id[$donor['donor_id']] = $donor;
}

$medical_by_donor = [];
foreach ($medical_histories as $medical) {
    $medical_by_donor[$medical['donor_id']] = $medical;
}

$screenings_by_donor = [];
foreach ($screening_forms as $screening) {
    $screenings_by_donor[$screening['donor_form_id']] = $screening;
}

$screenings_by_id = [];
foreach ($screening_forms as $screening) {
    $screenings_by_id[$screening['screening_id']] = $screening;
}

$physicals_by_donor = [];
foreach ($physical_exams as $physical) {
    $physicals_by_donor[$physical['donor_id']] = $physical;
}

// Create sets to track donors already processed
$donors_with_blood = [];
$donors_with_physical = [];
$donors_with_screening = [];

// Process the donor history with ALL PRIORITIES (same as main dashboard)
$donor_history = [];
$counter = 1;

echo "<h2>Processing ALL Priority Levels</h2>";

// PRIORITY 1: Process Blood Collections (Highest Priority)
echo "<h3>PRIORITY 1: Blood Collections</h3>";
foreach ($blood_collections as $blood_info) {
    $screening_id = $blood_info['screening_id'];
    $screening_info = $screenings_by_id[$screening_id] ?? null;
    if (!$screening_info) continue;
    
    $donor_info = $donors_by_form_id[$screening_info['donor_form_id']] ?? null;
    if (!$donor_info) continue;
    
    $donor_id = $donor_info['donor_id'];
    
    if (isset($donors_with_blood[$donor_id])) continue;
    $donors_with_blood[$donor_id] = true;
    
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    
    $history_entry = [
        'no' => $counter,
        'date' => $blood_info['start_time'] ?? $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_id' => $donor_id,
        'stage' => 'blood_collection',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Completed)' : 'New (Completed)'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
    
    echo "Added: " . $donor_info['surname'] . ", " . $donor_info['first_name'] . " (Donor ID: $donor_id) - Stage: blood_collection<br>";
}

// PRIORITY 2: Process Physical Examinations
echo "<h3>PRIORITY 2: Physical Examinations</h3>";
foreach ($physical_exams as $physical_info) {
    $donor_id = $physical_info['donor_id'];
    
    if (isset($donors_with_blood[$donor_id])) continue;
    
    if (isset($donors_with_physical[$donor_id])) continue;
    $donors_with_physical[$donor_id] = true;
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) continue;
    
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    
    $history_entry = [
        'no' => $counter,
        'date' => $physical_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_id' => $donor_id,
        'stage' => 'physical_examination',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Collection)' : 'New (Collection)'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
    
    echo "Added: " . $donor_info['surname'] . ", " . $donor_info['first_name'] . " (Donor ID: $donor_id) - Stage: physical_examination<br>";
}

// PRIORITY 3: Process Screening Forms
echo "<h3>PRIORITY 3: Screening Forms</h3>";
foreach ($screening_forms as $screening_info) {
    $donor_form_id = $screening_info['donor_form_id'];
    
    $donor_info = $donors_by_form_id[$donor_form_id] ?? null;
    if (!$donor_info) continue;
    
    $donor_id = $donor_info['donor_id'];
    
    if (isset($donors_with_blood[$donor_id]) || isset($donors_with_physical[$donor_id])) continue;
    
    if (isset($donors_with_screening[$donor_id])) continue;
    $donors_with_screening[$donor_id] = true;
    
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    
    $history_entry = [
        'no' => $counter,
        'date' => $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_id' => $donor_id,
        'stage' => 'screening_form',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Physical)' : 'New (Physical)'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
    
    echo "Added: " . $donor_info['surname'] . ", " . $donor_info['first_name'] . " (Donor ID: $donor_id) - Stage: screening_form<br>";
}

// PRIORITY 4: Process Donor Forms (Medical Review)
echo "<h3>PRIORITY 4: Donor Forms (Medical Review)</h3>";
$all_processed_donors = array_merge($donors_with_blood, $donors_with_physical, $donors_with_screening);
foreach ($donor_forms as $donor_info) {
    $donor_id = $donor_info['donor_id'];
    
    if (isset($all_processed_donors[$donor_id])) continue;
    
    $has_screening = false;
    foreach ($screenings_by_donor as $screening_donor_form_id => $screening) {
        if ($screening_donor_form_id == $donor_id) {
            $has_screening = true;
            break;
        }
    }
    
    if ($has_screening) continue;
    
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    
    $history_entry = [
        'no' => $counter,
        'date' => $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_id' => $donor_id,
        'stage' => 'medical_review',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Screening)' : 'New (Screening)'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
    
    echo "Added: " . $donor_info['surname'] . ", " . $donor_info['first_name'] . " (Donor ID: $donor_id) - Stage: medical_review<br>";
}

echo "<h3>Final Results:</h3>";
echo "<p>Total entries in donor_history: " . count($donor_history) . "</p>";

// Check for duplicates
$names_count = [];
$duplicates = [];
foreach ($donor_history as $entry) {
    $name = $entry['surname'] . ', ' . $entry['first_name'];
    if (!isset($names_count[$name])) {
        $names_count[$name] = [];
    }
    $names_count[$name][] = $entry;
}

foreach ($names_count as $name => $entries) {
    if (count($entries) > 1) {
        $duplicates[$name] = $entries;
    }
}

if (!empty($duplicates)) {
    echo "<div style='background: #ffe6e6; padding: 10px; margin: 5px;'>";
    echo "<strong>DUPLICATES FOUND:</strong><br>";
    foreach ($duplicates as $name => $entries) {
        echo "<strong>$name</strong> appears " . count($entries) . " times:<br>";
        foreach ($entries as $entry) {
            echo "- No: " . $entry['no'] . ", Donor ID: " . $entry['donor_id'] . ", Stage: " . $entry['stage'] . "<br>";
        }
        echo "<br>";
    }
    echo "</div>";
} else {
    echo "<p style='color: green;'>No duplicates found!</p>";
}

echo "<h3>All Entries:</h3>";
echo "<table border='1' style='border-collapse: collapse; font-size: 12px;'>";
echo "<tr><th>No</th><th>Name</th><th>Donor ID</th><th>Stage</th><th>Donor Type</th></tr>";
foreach ($donor_history as $entry) {
    $name = $entry['surname'] . ', ' . $entry['first_name'];
    echo "<tr>";
    echo "<td>" . $entry['no'] . "</td>";
    echo "<td>" . htmlspecialchars($name) . "</td>";
    echo "<td>" . $entry['donor_id'] . "</td>";
    echo "<td>" . $entry['stage'] . "</td>";
    echo "<td>" . $entry['donor_type'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Priorities Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>All Priorities Debug</h1>
    </div>
</body>
</html>










