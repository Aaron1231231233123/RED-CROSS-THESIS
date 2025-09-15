<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /RED-CROSS-THESIS/public/login.php");
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

$screenings_by_id = [];
foreach ($screening_forms as $screening) {
    $screenings_by_id[$screening['screening_id']] = $screening;
}

// Check for multiple blood collections per screening
$blood_collections_by_screening = [];
foreach ($blood_collections as $blood) {
    $screening_id = $blood['screening_id'];
    if (!isset($blood_collections_by_screening[$screening_id])) {
        $blood_collections_by_screening[$screening_id] = [];
    }
    $blood_collections_by_screening[$screening_id][] = $blood;
}

// Check for multiple blood collections per donor
$blood_collections_by_donor = [];
foreach ($blood_collections as $blood) {
    $screening = $screenings_by_id[$blood['screening_id']] ?? null;
    if ($screening) {
        $donor_id = $screening['donor_form_id'];
        if (!isset($blood_collections_by_donor[$donor_id])) {
            $blood_collections_by_donor[$donor_id] = [];
        }
        $blood_collections_by_donor[$donor_id][] = $blood;
    }
}

// Simulate the exact processing logic from the main file
$donor_history = [];
$counter = 1;
$donors_with_blood = [];

// PRIORITY 1: Process Blood Collections (Highest Priority)
foreach ($blood_collections as $blood_info) {
    $screening_id = $blood_info['screening_id'];
    
    // Find the screening form for this blood collection
    $screening_info = $screenings_by_id[$screening_id] ?? null;
    if (!$screening_info) {
        continue;
    }
    
    // Find the donor form for this screening
    $donor_info = $donors_by_id[$screening_info['donor_form_id']] ?? null;
    if (!$donor_info) {
        continue;
    }
    
    $donor_id = $donor_info['donor_id'];
    
    // Check if this donor already has blood collection
    if (isset($donors_with_blood[$donor_id])) {
        echo "DUPLICATE BLOOD COLLECTION FOUND for donor_id: $donor_id<br>";
    }
    
    $donors_with_blood[$donor_id] = true;
    
    $history_entry = [
        'no' => $counter,
        'date' => $blood_info['start_time'],
        'surname' => $donor_info['surname'],
        'first_name' => $donor_info['first_name'],
        'donor_id' => $donor_id,
        'screening_id' => $screening_id,
        'blood_collection_id' => $blood_info['blood_collection_id']
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// Check for duplicates in the final array
$donor_ids_seen = [];
$duplicates_found = [];
foreach ($donor_history as $entry) {
    $donor_id = $entry['donor_id'];
    if (isset($donor_ids_seen[$donor_id])) {
        $duplicates_found[] = $entry;
    } else {
        $donor_ids_seen[$donor_id] = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Collections Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .duplicate { background-color: #ffe6e6; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Blood Collections Debug</h1>
        
        <div class="debug-section">
            <h3>Summary</h3>
            <ul>
                <li>Total Donor Forms: <?php echo count($donor_forms); ?></li>
                <li>Total Screening Forms: <?php echo count($screening_forms); ?></li>
                <li>Total Blood Collections: <?php echo count($blood_collections); ?></li>
                <li>Total Processed Entries: <?php echo count($donor_history); ?></li>
                <li>Duplicates Found: <?php echo count($duplicates_found); ?></li>
            </ul>
        </div>

        <?php if (!empty($duplicates_found)): ?>
        <div class="debug-section">
            <h3>Duplicates Found in Final Array</h3>
            <div class="alert alert-danger">
                Found <?php echo count($duplicates_found); ?> duplicate entries in the final donor_history array!
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Donor ID</th>
                        <th>Screening ID</th>
                        <th>Blood Collection ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($duplicates_found as $entry): ?>
                        <tr class="duplicate">
                            <td><?php echo htmlspecialchars($entry['no']); ?></td>
                            <td><?php echo htmlspecialchars($entry['date']); ?></td>
                            <td><?php echo htmlspecialchars($entry['surname'] . ', ' . $entry['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['screening_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['blood_collection_id']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="debug-section">
            <h3>Multiple Blood Collections per Screening</h3>
            <?php 
            $multiple_per_screening = array_filter($blood_collections_by_screening, function($collections) {
                return count($collections) > 1;
            });
            ?>
            <?php if (empty($multiple_per_screening)): ?>
                <div class="alert alert-success">No screenings with multiple blood collections found!</div>
            <?php else: ?>
                <div class="alert alert-warning">Found <?php echo count($multiple_per_screening); ?> screenings with multiple blood collections!</div>
                <?php foreach ($multiple_per_screening as $screening_id => $collections): ?>
                    <?php $screening = $screenings_by_id[$screening_id] ?? null; ?>
                    <div class="duplicate">
                        <strong>Screening ID <?php echo $screening_id; ?></strong>
                        <?php if ($screening): ?>
                            <?php $donor = $donors_by_id[$screening['donor_form_id']] ?? null; ?>
                            <?php if ($donor): ?>
                                <br>Donor: <?php echo $donor['surname'] . ', ' . $donor['first_name']; ?> (ID: <?php echo $donor['donor_id']; ?>)
                            <?php endif; ?>
                        <?php endif; ?>
                        <ul>
                            <?php foreach ($collections as $collection): ?>
                                <li>Blood Collection ID: <?php echo $collection['blood_collection_id']; ?> - Date: <?php echo $collection['start_time']; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="debug-section">
            <h3>Multiple Blood Collections per Donor</h3>
            <?php 
            $multiple_per_donor = array_filter($blood_collections_by_donor, function($collections) {
                return count($collections) > 1;
            });
            ?>
            <?php if (empty($multiple_per_donor)): ?>
                <div class="alert alert-success">No donors with multiple blood collections found!</div>
            <?php else: ?>
                <div class="alert alert-warning">Found <?php echo count($multiple_per_donor); ?> donors with multiple blood collections!</div>
                <?php foreach ($multiple_per_donor as $donor_id => $collections): ?>
                    <?php $donor = $donors_by_id[$donor_id] ?? null; ?>
                    <div class="duplicate">
                        <strong>Donor ID <?php echo $donor_id; ?>: <?php echo $donor ? $donor['surname'] . ', ' . $donor['first_name'] : 'Unknown'; ?></strong>
                        <ul>
                            <?php foreach ($collections as $collection): ?>
                                <li>Blood Collection ID: <?php echo $collection['blood_collection_id']; ?> - Screening ID: <?php echo $collection['screening_id']; ?> - Date: <?php echo $collection['start_time']; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="debug-section">
            <h3>All Processed Entries</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Donor ID</th>
                        <th>Screening ID</th>
                        <th>Blood Collection ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donor_history as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['no']); ?></td>
                            <td><?php echo htmlspecialchars($entry['date']); ?></td>
                            <td><?php echo htmlspecialchars($entry['surname'] . ', ' . $entry['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['screening_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['blood_collection_id']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
