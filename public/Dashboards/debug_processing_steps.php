<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /REDCROSS/public/login.php");
    exit();
}

// Get all data
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

// Create lookup arrays
$donors_by_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_id[$donor['donor_id']] = $donor;
}

$donors_by_form_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_form_id[$donor['donor_id']] = $donor; // In this case, donor_id IS the donor_form_id
}

$screenings_by_id = [];
foreach ($screening_forms as $screening) {
    $screenings_by_id[$screening['screening_id']] = $screening;
}

// Create sets to track donors already processed at higher priority levels
$donors_with_blood = [];
$donors_with_physical = [];
$donors_with_screening = [];

// Process the donor history with HIERARCHY PRIORITY
$donor_history = [];
$counter = 1;

// Find specific donors to track
$villanueva_donor_id = null;
$oscares_donor_ids = [];

foreach ($donor_forms as $donor) {
    if (strpos($donor['surname'], 'Villanueva') !== false && strpos($donor['first_name'], 'Erica Nicole') !== false) {
        $villanueva_donor_id = $donor['donor_id'];
    }
    if (strpos($donor['surname'], 'Oscares') !== false && strpos($donor['first_name'], 'Nelwin James') !== false) {
        $oscares_donor_ids[] = $donor['donor_id'];
    }
}

echo "<h3>Tracking Donor IDs:</h3>";
echo "<p>Villanueva, Erica Nicole: Donor ID " . ($villanueva_donor_id ?? 'NOT FOUND') . "</p>";
echo "<p>Oscares, Nelwin James: Donor IDs " . implode(', ', $oscares_donor_ids) . "</p>";

// PRIORITY 1: Process Blood Collections (Highest Priority) - Donors who completed donation
echo "<h3>PRIORITY 1: Blood Collections Processing</h3>";
foreach ($blood_collections as $blood_info) {
    $screening_id = $blood_info['screening_id'];
    
    // Find the screening form for this blood collection
    $screening_info = $screenings_by_id[$screening_id] ?? null;
    if (!$screening_info) {
        continue;
    }
    
    // Find the donor form for this screening
    $donor_info = $donors_by_form_id[$screening_info['donor_form_id']] ?? null;
    if (!$donor_info) {
        continue;
    }
    
    $donor_id = $donor_info['donor_id'];
    
    // Track specific donors
    $is_tracked = false;
    if ($donor_id == $villanueva_donor_id) {
        $is_tracked = true;
        echo "<div style='background: #ffe6e6; padding: 10px; margin: 5px;'>";
        echo "<strong>TRACKING Villanueva, Erica Nicole (Donor ID: $donor_id)</strong><br>";
        echo "Blood Collection ID: " . $blood_info['blood_collection_id'] . "<br>";
        echo "Screening ID: " . $screening_id . "<br>";
        echo "Already in donors_with_blood: " . (isset($donors_with_blood[$donor_id]) ? 'YES' : 'NO') . "<br>";
    }
    
    if (in_array($donor_id, $oscares_donor_ids)) {
        $is_tracked = true;
        echo "<div style='background: #e6f3ff; padding: 10px; margin: 5px;'>";
        echo "<strong>TRACKING Oscares, Nelwin James (Donor ID: $donor_id)</strong><br>";
        echo "Blood Collection ID: " . $blood_info['blood_collection_id'] . "<br>";
        echo "Screening ID: " . $screening_id . "<br>";
        echo "Already in donors_with_blood: " . (isset($donors_with_blood[$donor_id]) ? 'YES' : 'NO') . "<br>";
    }
    
    // Skip if this donor has already been processed
    if (isset($donors_with_blood[$donor_id])) {
        if ($is_tracked) {
            echo "SKIPPING - Already processed<br>";
            echo "</div>";
        }
        continue;
    }
    
    $donors_with_blood[$donor_id] = true; // Mark this donor as processed
    
    if ($is_tracked) {
        echo "ADDING to donor_history<br>";
        echo "</div>";
    }
    
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

echo "<h3>Final Results:</h3>";
echo "<p>Total entries in donor_history: " . count($donor_history) . "</p>";
echo "<p>Donors with blood: " . count($donors_with_blood) . "</p>";

echo "<h3>All Entries in donor_history:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>No</th><th>Name</th><th>Donor ID</th><th>Stage</th></tr>";
foreach ($donor_history as $entry) {
    $name = $entry['surname'] . ', ' . $entry['first_name'];
    echo "<tr>";
    echo "<td>" . $entry['no'] . "</td>";
    echo "<td>" . htmlspecialchars($name) . "</td>";
    echo "<td>" . $entry['donor_id'] . "</td>";
    echo "<td>" . $entry['stage'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Tracking Arrays Status:</h3>";
echo "<p>donors_with_blood: " . implode(', ', array_keys($donors_with_blood)) . "</p>";
echo "<p>donors_with_physical: " . implode(', ', array_keys($donors_with_physical)) . "</p>";
echo "<p>donors_with_screening: " . implode(', ', array_keys($donors_with_screening)) . "</p>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Steps Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Processing Steps Debug</h1>
        <?php echo $output ?? ''; ?>
    </div>
</body>
</html>










