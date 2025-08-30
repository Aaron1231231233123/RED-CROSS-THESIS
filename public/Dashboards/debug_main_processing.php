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

// Create lookup arrays for faster processing
$donors_by_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_id[$donor['donor_id']] = $donor;
}

// Create lookup by donor_form_id for screening forms
$donors_by_form_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_form_id[$donor['donor_id']] = $donor; // donor_id is the same as donor_form_id
}

$medical_by_donor = [];
foreach ($medical_histories as $medical) {
    $medical_by_donor[$medical['donor_id']] = $medical;
}

$screenings_by_donor = [];
foreach ($screening_forms as $screening) {
    $screenings_by_donor[$screening['donor_form_id']] = $screening;
}

// Create a lookup by screening_id for blood collection processing
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

// Create sets to track donors already processed at higher priority levels
$donors_with_blood = [];
$donors_with_physical = [];
$donors_with_screening = [];
$donors_with_medical = [];

// Process the donor history with HIERARCHY PRIORITY
$donor_history = [];
$counter = 1;

// PRIORITY 1: Process Blood Collections (Highest Priority) - Donors who completed donation
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
    $donors_with_blood[$donor_id] = true; // Mark this donor as processed
    
    // Find medical history for this donor
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    
    $history_entry = [
        'no' => $counter,
        'date' => $blood_info['start_time'] ?? $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'interviewer' => $medical_info['interviewer_name'] ?? 'N/A',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Completed)' : 'New (Completed)',
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
        'donor_id' => $donor_id,
        'stage' => 'blood_collection',
        'medical_history_id' => $medical_info['medical_history_id'] ?? null
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 2: Process Physical Examinations (Medium Priority) - Only if not already in blood collection
foreach ($physical_exams as $physical_info) {
    $donor_id = $physical_info['donor_id'];
    
    // Skip if this donor already has blood collection (higher priority)
    if (isset($donors_with_blood[$donor_id])) {
        continue;
    }
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) {
        continue;
    }
    
    $donors_with_physical[$donor_id] = true; // Mark this donor as processed
    
    // Find medical history for this donor
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    
    $history_entry = [
        'no' => $counter,
        'date' => $physical_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'interviewer' => $medical_info['interviewer_name'] ?? 'N/A',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Collection)' : 'New (Collection)',
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
        'donor_id' => $donor_id,
        'stage' => 'physical_examination',
        'medical_history_id' => $medical_info['medical_history_id'] ?? null
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 3: Process Screening Forms (Low Priority) - Only if not in physical exam or blood collection
foreach ($screening_forms as $screening_info) {
    $donor_form_id = $screening_info['donor_form_id'];
    
    // Get the donor info using donor_form_id
    $donor_info = $donors_by_form_id[$donor_form_id] ?? null;
    if (!$donor_info) {
        continue;
    }
    
    $donor_id = $donor_info['donor_id'];
    
    // Skip if this donor already has blood collection or physical exam (higher priorities)
    if (isset($donors_with_blood[$donor_id]) || isset($donors_with_physical[$donor_id])) {
        continue;
    }
    
    $donors_with_screening[$donor_id] = true; // Mark this donor as processed
    
    // Find medical history for this donor
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    
    $history_entry = [
        'no' => $counter,
        'date' => $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'interviewer' => $medical_info['interviewer_name'] ?? 'N/A',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Physical)' : 'New (Physical)',
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
        'donor_id' => $donor_id,
        'stage' => 'screening_form',
        'medical_history_id' => $medical_info['medical_history_id'] ?? null
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 4: Process Donor Forms with ONLY registration (Medical Review stage) - Only if not in any other stage
$all_processed_donors = array_merge($donors_with_blood, $donors_with_physical, $donors_with_screening);
foreach ($donor_forms as $donor_info) {
    $donor_id = $donor_info['donor_id'];
    
    // Skip if this donor already has blood collection, physical exam, or screening (higher priorities)
    if (isset($all_processed_donors[$donor_id])) {
        continue;
    }
    
    // Check if this donor has screening form
    // Since screenings_by_donor is keyed by donor_form_id, we need to check if this donor_id exists as a key
    $has_screening = false;
    foreach ($screenings_by_donor as $screening_donor_form_id => $screening) {
        if ($screening_donor_form_id == $donor_id) {
            $has_screening = true;
            break;
        }
    }
    
    // Skip if donor already has screening (covered in priority 3)
    if ($has_screening) {
        continue;
    }
    
    // Check if this donor has medical history
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    
    $history_entry = [
        'no' => $counter,
        'date' => $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'interviewer' => $medical_info['interviewer_name'] ?? 'N/A',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Screening)' : 'New (Screening)',
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
        'donor_id' => $donor_id,
        'stage' => 'medical_review',
        'medical_history_id' => $medical_info['medical_history_id'] ?? null
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

// Check for specific donors mentioned in the images
$villanueva_entries = [];
$oscares_entries = [];
foreach ($donor_history as $entry) {
    if (strpos($entry['surname'], 'Villanueva') !== false && strpos($entry['first_name'], 'Erica Nicole') !== false) {
        $villanueva_entries[] = $entry;
    }
    if (strpos($entry['surname'], 'Oscares') !== false && strpos($entry['first_name'], 'Nelwin James') !== false) {
        $oscares_entries[] = $entry;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Processing Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .duplicate { background-color: #ffe6e6; }
        .issue { background-color: #fff3cd; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Main Processing Debug</h1>
        
        <div class="debug-section">
            <h3>Summary</h3>
            <ul>
                <li>Total Donor Forms: <?php echo count($donor_forms); ?></li>
                <li>Total Medical Histories: <?php echo count($medical_histories); ?></li>
                <li>Total Screening Forms: <?php echo count($screening_forms); ?></li>
                <li>Total Physical Exams: <?php echo count($physical_exams); ?></li>
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
                        <th>Stage</th>
                        <th>Donor Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($duplicates_found as $entry): ?>
                        <tr class="duplicate">
                            <td><?php echo htmlspecialchars($entry['no']); ?></td>
                            <td><?php echo htmlspecialchars($entry['date']); ?></td>
                            <td><?php echo htmlspecialchars($entry['surname'] . ', ' . $entry['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['stage']); ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_type']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($villanueva_entries)): ?>
        <div class="debug-section">
            <h3>Villanueva, Erica Nicole Entries</h3>
            <div class="alert alert-warning">
                Found <?php echo count($villanueva_entries); ?> entries for Villanueva, Erica Nicole
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Donor ID</th>
                        <th>Stage</th>
                        <th>Donor Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($villanueva_entries as $entry): ?>
                        <tr class="duplicate">
                            <td><?php echo htmlspecialchars($entry['no']); ?></td>
                            <td><?php echo htmlspecialchars($entry['date']); ?></td>
                            <td><?php echo htmlspecialchars($entry['surname'] . ', ' . $entry['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['stage']); ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_type']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($oscares_entries)): ?>
        <div class="debug-section">
            <h3>Oscares, Nelwin James Entries</h3>
            <div class="alert alert-warning">
                Found <?php echo count($oscares_entries); ?> entries for Oscares, Nelwin James
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Donor ID</th>
                        <th>Stage</th>
                        <th>Donor Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oscares_entries as $entry): ?>
                        <tr class="duplicate">
                            <td><?php echo htmlspecialchars($entry['no']); ?></td>
                            <td><?php echo htmlspecialchars($entry['date']); ?></td>
                            <td><?php echo htmlspecialchars($entry['surname'] . ', ' . $entry['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($entry['stage']); ?></td>
                            <td><?php echo htmlspecialchars($entry['donor_type']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

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
                        <th>Donor Type</th>
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
                            <td><?php echo htmlspecialchars($entry['donor_type']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>









