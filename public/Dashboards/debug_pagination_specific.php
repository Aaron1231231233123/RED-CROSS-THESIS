<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /REDCROSS/public/login.php");
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

$screenings_by_id = [];
foreach ($screening_forms as $screening) {
    $screenings_by_id[$screening['screening_id']] = $screening;
}

// Create sets to track donors already processed
$donors_with_blood = [];
$donors_with_physical = [];
$donors_with_screening = [];

// Process the donor history (same as main dashboard)
$donor_history = [];
$counter = 1;

// PRIORITY 1: Process Blood Collections
foreach ($blood_collections as $blood_info) {
    $screening_id = $blood_info['screening_id'];
    $screening_info = $screenings_by_id[$screening_id] ?? null;
    if (!$screening_info) continue;
    
    $donor_info = $donors_by_form_id[$screening_info['donor_form_id']] ?? null;
    if (!$donor_info) continue;
    
    $donor_id = $donor_info['donor_id'];
    
    if (isset($donors_with_blood[$donor_id])) continue;
    $donors_with_blood[$donor_id] = true;
    
    $history_entry = [
        'no' => $counter,
        'date' => $blood_info['start_time'] ?? $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_id' => $donor_id,
        'stage' => 'blood_collection'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// Test pagination for different pages
$records_per_page = 10;

echo "<h2>Pagination Debug</h2>";
echo "<p>Total records: " . count($donor_history) . "</p>";
echo "<p>Records per page: $records_per_page</p>";

// Test each page
for ($page = 1; $page <= ceil(count($donor_history) / $records_per_page); $page++) {
    $offset = ($page - 1) * $records_per_page;
    $page_data = array_slice($donor_history, $offset, $records_per_page);
    
    echo "<h3>Page $page (Offset: $offset)</h3>";
    echo "<p>Records on this page: " . count($page_data) . "</p>";
    
    // Check for duplicates on this page
    $names_on_page = [];
    $duplicates_on_page = [];
    
    foreach ($page_data as $entry) {
        $name = $entry['surname'] . ', ' . $entry['first_name'];
        if (isset($names_on_page[$name])) {
            $duplicates_on_page[] = $name;
        } else {
            $names_on_page[$name] = true;
        }
    }
    
    if (!empty($duplicates_on_page)) {
        echo "<div style='background: #ffe6e6; padding: 10px; margin: 5px;'>";
        echo "<strong>DUPLICATES FOUND on Page $page:</strong><br>";
        foreach ($duplicates_on_page as $duplicate) {
            echo "- $duplicate<br>";
        }
        echo "</div>";
    } else {
        echo "<p style='color: green;'>No duplicates on this page</p>";
    }
    
    echo "<table border='1' style='border-collapse: collapse; font-size: 12px;'>";
    echo "<tr><th>No</th><th>Name</th><th>Donor ID</th><th>Stage</th></tr>";
    foreach ($page_data as $entry) {
        $name = $entry['surname'] . ', ' . $entry['first_name'];
        echo "<tr>";
        echo "<td>" . $entry['no'] . "</td>";
        echo "<td>" . htmlspecialchars($name) . "</td>";
        echo "<td>" . $entry['donor_id'] . "</td>";
        echo "<td>" . $entry['stage'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<hr>";
}

// Check specific donors across all pages
echo "<h3>Specific Donor Tracking</h3>";
$villanueva_entries = [];
$oscares_entries = [];

foreach ($donor_history as $entry) {
    $name = $entry['surname'] . ', ' . $entry['first_name'];
    if (strpos($name, 'Villanueva, Erica Nicole') !== false) {
        $villanueva_entries[] = $entry;
    }
    if (strpos($name, 'Oscares, Nelwin James') !== false) {
        $oscares_entries[] = $entry;
    }
}

echo "<h4>Villanueva, Erica Nicole Entries:</h4>";
echo "<p>Found " . count($villanueva_entries) . " entries</p>";
foreach ($villanueva_entries as $entry) {
    echo "- No: " . $entry['no'] . ", Donor ID: " . $entry['donor_id'] . ", Stage: " . $entry['stage'] . "<br>";
}

echo "<h4>Oscares, Nelwin James Entries:</h4>";
echo "<p>Found " . count($oscares_entries) . " entries</p>";
foreach ($oscares_entries as $entry) {
    echo "- No: " . $entry['no'] . ", Donor ID: " . $entry['donor_id'] . ", Stage: " . $entry['stage'] . "<br>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagination Specific Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Pagination Specific Debug</h1>
    </div>
</body>
</html>










