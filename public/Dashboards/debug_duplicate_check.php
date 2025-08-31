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

// Create lookup arrays
$donors_by_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_id[$donor['donor_id']] = $donor;
}

$screenings_by_id = [];
foreach ($screening_forms as $screening) {
    $screenings_by_id[$screening['screening_id']] = $screening;
}

$screenings_by_donor = [];
foreach ($screening_forms as $screening) {
    $screenings_by_donor[$screening['donor_form_id']] = $screening;
}

$physicals_by_donor = [];
foreach ($physical_exams as $physical) {
    $physicals_by_donor[$physical['donor_id']] = $physical;
}

$blood_by_screening = [];
foreach ($blood_collections as $blood) {
    $blood_by_screening[$blood['screening_id']] = $blood;
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

// Check for multiple physical exams per donor
$physical_exams_by_donor = [];
foreach ($physical_exams as $physical) {
    $donor_id = $physical['donor_id'];
    if (!isset($physical_exams_by_donor[$donor_id])) {
        $physical_exams_by_donor[$donor_id] = [];
    }
    $physical_exams_by_donor[$donor_id][] = $physical;
}

// Simulate the processing logic
$donor_history = [];
$counter = 1;
$donors_with_blood = [];
$donors_with_physical = [];

// PRIORITY 1: Process Blood Collections
foreach ($blood_collections as $blood_info) {
    $screening_id = $blood_info['screening_id'];
    $screening_info = $screenings_by_id[$screening_id] ?? null;
    if (!$screening_info) {
        continue;
    }
    
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
        'stage' => 'blood_collection',
        'blood_collection_id' => $blood_info['blood_collection_id']
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 2: Process Physical Examinations
foreach ($physical_exams as $physical_info) {
    $donor_id = $physical_info['donor_id'];
    
    if (isset($donors_with_blood[$donor_id])) {
        continue;
    }
    
    if (isset($donors_with_physical[$donor_id])) {
        echo "DUPLICATE PHYSICAL EXAM FOUND for donor_id: $donor_id<br>";
    }
    
    $donors_with_physical[$donor_id] = true;
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) {
        continue;
    }
    
    $history_entry = [
        'no' => $counter,
        'date' => $physical_info['created_at'],
        'surname' => $donor_info['surname'],
        'first_name' => $donor_info['first_name'],
        'donor_id' => $donor_id,
        'stage' => 'physical_examination',
        'physical_exam_id' => $physical_info['id'] ?? 'N/A'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duplicate Check Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .duplicate { background-color: #ffe6e6; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Duplicate Check Debug</h1>
        
        <div class="debug-section">
            <h3>Summary</h3>
            <ul>
                <li>Total Donor Forms: <?php echo count($donor_forms); ?></li>
                <li>Total Blood Collections: <?php echo count($blood_collections); ?></li>
                <li>Total Physical Exams: <?php echo count($physical_exams); ?></li>
                <li>Total Processed Entries: <?php echo count($donor_history); ?></li>
            </ul>
        </div>

        <div class="debug-section">
            <h3>Multiple Blood Collections per Donor</h3>
            <?php 
            $multiple_blood = array_filter($blood_collections_by_donor, function($collections) {
                return count($collections) > 1;
            });
            ?>
            <?php if (empty($multiple_blood)): ?>
                <div class="alert alert-success">No donors with multiple blood collections found!</div>
            <?php else: ?>
                <div class="alert alert-warning">Found <?php echo count($multiple_blood); ?> donors with multiple blood collections!</div>
                <?php foreach ($multiple_blood as $donor_id => $collections): ?>
                    <?php $donor = $donors_by_id[$donor_id] ?? null; ?>
                    <div class="duplicate">
                        <strong>Donor ID <?php echo $donor_id; ?>: <?php echo $donor ? $donor['surname'] . ', ' . $donor['first_name'] : 'Unknown'; ?></strong>
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
            <h3>Multiple Physical Exams per Donor</h3>
            <?php 
            $multiple_physical = array_filter($physical_exams_by_donor, function($exams) {
                return count($exams) > 1;
            });
            ?>
            <?php if (empty($multiple_physical)): ?>
                <div class="alert alert-success">No donors with multiple physical exams found!</div>
            <?php else: ?>
                <div class="alert alert-warning">Found <?php echo count($multiple_physical); ?> donors with multiple physical exams!</div>
                <?php foreach ($multiple_physical as $donor_id => $exams): ?>
                    <?php $donor = $donors_by_id[$donor_id] ?? null; ?>
                    <div class="duplicate">
                        <strong>Donor ID <?php echo $donor_id; ?>: <?php echo $donor ? $donor['surname'] . ', ' . $donor['first_name'] : 'Unknown'; ?></strong>
                        <ul>
                            <?php foreach ($exams as $exam): ?>
                                <li>Physical Exam ID: <?php echo $exam['id'] ?? 'N/A'; ?> - Date: <?php echo $exam['created_at']; ?></li>
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
                        <th>Stage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donor_history as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['no']); ?></td>
                            <td><?php echo htmlspecialchars($entry['date']); ?></td>
                            <td><?php echo htmlspecialchars($entry['surname'] . ', ' . $entry['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['stage']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
