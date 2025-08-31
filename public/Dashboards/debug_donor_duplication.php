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

// Create lookup arrays
$donors_by_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_id[$donor['donor_id']] = $donor;
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

$blood_by_screening = [];
foreach ($blood_collections as $blood) {
    $blood_by_screening[$blood['screening_id']] = $blood;
}

// Track processed donors
$donors_with_blood = [];
$donors_with_physical = [];
$donors_with_screening = [];

// Process with detailed logging
$donor_history = [];
$counter = 1;
$debug_log = [];

// PRIORITY 1: Blood Collections
foreach ($blood_collections as $blood_info) {
    $screening_id = $blood_info['screening_id'];
    $screening_info = $screenings_by_id[$screening_id] ?? null;
    
    if (!$screening_info) {
        $debug_log[] = "BLOOD: No screening found for screening_id: $screening_id";
        continue;
    }
    
    $donor_info = $donors_by_id[$screening_info['donor_form_id']] ?? null;
    if (!$donor_info) {
        $debug_log[] = "BLOOD: No donor found for donor_form_id: " . $screening_info['donor_form_id'];
        continue;
    }
    
    $donor_id = $donor_info['donor_id'];
    $donors_with_blood[$donor_id] = true;
    
    $debug_log[] = "BLOOD: Processing donor_id: $donor_id, Name: " . $donor_info['surname'] . ", " . $donor_info['first_name'];
    
    $history_entry = [
        'no' => $counter,
        'date' => $blood_info['start_time'] ?? $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_type' => 'Blood Collection',
        'donor_id' => $donor_id,
        'stage' => 'blood_collection'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 2: Physical Examinations
foreach ($physical_exams as $physical_info) {
    $donor_id = $physical_info['donor_id'];
    
    if (isset($donors_with_blood[$donor_id])) {
        $debug_log[] = "PHYSICAL: Skipping donor_id: $donor_id (already in blood collection)";
        continue;
    }
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) {
        $debug_log[] = "PHYSICAL: No donor found for donor_id: $donor_id";
        continue;
    }
    
    $donors_with_physical[$donor_id] = true;
    
    $debug_log[] = "PHYSICAL: Processing donor_id: $donor_id, Name: " . $donor_info['surname'] . ", " . $donor_info['first_name'];
    
    $history_entry = [
        'no' => $counter,
        'date' => $physical_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_type' => 'Physical Exam',
        'donor_id' => $donor_id,
        'stage' => 'physical_examination'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 3: Screening Forms
foreach ($screening_forms as $screening_info) {
    $donor_id = $screening_info['donor_form_id'];
    
    if (isset($donors_with_blood[$donor_id]) || isset($donors_with_physical[$donor_id])) {
        $debug_log[] = "SCREENING: Skipping donor_id: $donor_id (already processed)";
        continue;
    }
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) {
        $debug_log[] = "SCREENING: No donor found for donor_id: $donor_id";
        continue;
    }
    
    $donors_with_screening[$donor_id] = true;
    
    $debug_log[] = "SCREENING: Processing donor_id: $donor_id, Name: " . $donor_info['surname'] . ", " . $donor_info['first_name'];
    
    $history_entry = [
        'no' => $counter,
        'date' => $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_type' => 'Screening',
        'donor_id' => $donor_id,
        'stage' => 'screening_form'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// Check for duplicates
$donor_ids_processed = [];
$duplicates = [];
foreach ($donor_history as $entry) {
    $donor_id = $entry['donor_id'];
    if (isset($donor_ids_processed[$donor_id])) {
        $duplicates[] = [
            'donor_id' => $donor_id,
            'name' => $entry['surname'] . ', ' . $entry['first_name'],
            'first_stage' => $donor_ids_processed[$donor_id]['stage'],
            'second_stage' => $entry['stage']
        ];
    } else {
        $donor_ids_processed[$donor_id] = $entry;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Duplication Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .duplicate-entry { background-color: #ffe6e6; padding: 10px; margin: 5px 0; border-radius: 3px; }
        .log-entry { font-family: monospace; margin: 2px 0; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Donor Duplication Debug Report</h1>
        
        <div class="debug-section">
            <h3>Data Summary</h3>
            <ul>
                <li>Donor Forms: <?php echo count($donor_forms); ?></li>
                <li>Medical Histories: <?php echo count($medical_histories); ?></li>
                <li>Screening Forms: <?php echo count($screening_forms); ?></li>
                <li>Physical Exams: <?php echo count($physical_exams); ?></li>
                <li>Blood Collections: <?php echo count($blood_collections); ?></li>
            </ul>
        </div>

        <div class="debug-section">
            <h3>Processing Log</h3>
            <div style="max-height: 400px; overflow-y: auto; background-color: #f8f9fa; padding: 10px;">
                <?php foreach ($debug_log as $log): ?>
                    <div class="log-entry"><?php echo htmlspecialchars($log); ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="debug-section">
            <h3>Duplicate Detection</h3>
            <?php if (empty($duplicates)): ?>
                <div class="alert alert-success">No duplicates found!</div>
            <?php else: ?>
                <div class="alert alert-danger">
                    Found <?php echo count($duplicates); ?> duplicate(s):
                </div>
                <?php foreach ($duplicates as $duplicate): ?>
                    <div class="duplicate-entry">
                        <strong>Donor ID:</strong> <?php echo htmlspecialchars($duplicate['donor_id']); ?><br>
                        <strong>Name:</strong> <?php echo htmlspecialchars($duplicate['name']); ?><br>
                        <strong>First Stage:</strong> <?php echo htmlspecialchars($duplicate['first_stage']); ?><br>
                        <strong>Second Stage:</strong> <?php echo htmlspecialchars($duplicate['second_stage']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="debug-section">
            <h3>All Processed Donors</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Donor ID</th>
                        <th>Name</th>
                        <th>Stage</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donor_history as $entry): ?>
                        <tr>
                            <td><?php echo $entry['no']; ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['surname'] . ', ' . $entry['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['stage']); ?></td>
                            <td><?php echo htmlspecialchars($entry['date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="debug-section">
            <h3>Raw Data Analysis</h3>
            
            <h4>Donor Forms with Multiple Records</h4>
            <?php
            $donor_counts = [];
            foreach ($donor_forms as $donor) {
                $donor_id = $donor['donor_id'];
                $donor_counts[$donor_id] = ($donor_counts[$donor_id] ?? 0) + 1;
            }
            $multiple_donors = array_filter($donor_counts, function($count) { return $count > 1; });
            ?>
            <?php if (empty($multiple_donors)): ?>
                <p>No donor forms with multiple records found.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($multiple_donors as $donor_id => $count): ?>
                        <li>Donor ID <?php echo $donor_id; ?>: <?php echo $count; ?> records</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h4>Screening Forms with Multiple Records</h4>
            <?php
            $screening_counts = [];
            foreach ($screening_forms as $screening) {
                $donor_id = $screening['donor_form_id'];
                $screening_counts[$donor_id] = ($screening_counts[$donor_id] ?? 0) + 1;
            }
            $multiple_screenings = array_filter($screening_counts, function($count) { return $count > 1; });
            ?>
            <?php if (empty($multiple_screenings)): ?>
                <p>No screening forms with multiple records found.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($multiple_screenings as $donor_id => $count): ?>
                        <li>Donor ID <?php echo $donor_id; ?>: <?php echo $count; ?> screening records</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h4>Physical Exams with Multiple Records</h4>
            <?php
            $physical_counts = [];
            foreach ($physical_exams as $physical) {
                $donor_id = $physical['donor_id'];
                $physical_counts[$donor_id] = ($physical_counts[$donor_id] ?? 0) + 1;
            }
            $multiple_physicals = array_filter($physical_counts, function($count) { return $count > 1; });
            ?>
            <?php if (empty($multiple_physicals)): ?>
                <p>No physical exams with multiple records found.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($multiple_physicals as $donor_id => $count): ?>
                        <li>Donor ID <?php echo $donor_id; ?>: <?php echo $count; ?> physical exam records</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


