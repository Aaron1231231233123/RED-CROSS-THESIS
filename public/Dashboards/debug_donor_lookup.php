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

// Show the actual fields in the data
$donor_fields = [];
$screening_fields = [];

if (!empty($donor_forms)) {
    $donor_fields = array_keys($donor_forms[0]);
}

if (!empty($screening_forms)) {
    $screening_fields = array_keys($screening_forms[0]);
}

// Create lookup arrays
$donors_by_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_id[$donor['donor_id']] = $donor;
}

// Check what field screening forms use to reference donors
$screening_donor_references = [];
foreach ($screening_forms as $screening) {
    $screening_donor_references[] = [
        'screening_id' => $screening['screening_id'],
        'donor_id' => $screening['donor_id'] ?? 'NOT_FOUND',
        'donor_form_id' => $screening['donor_form_id'] ?? 'NOT_FOUND',
        'all_fields' => array_keys($screening)
    ];
}

// Simulate the exact processing logic from the main file
$donor_history = [];
$counter = 1;
$donors_with_blood = [];
$donors_with_physical = [];
$donors_with_screening = [];

// PRIORITY 3: Process Screening Forms (Low Priority)
foreach ($screening_forms as $screening_info) {
    // Check what field to use for donor lookup
    $donor_reference = $screening_info['donor_id'] ?? $screening_info['donor_form_id'] ?? null;
    
    if (!$donor_reference) {
        continue;
    }
    
    // Get the donor info
    $donor_info = $donors_by_id[$donor_reference] ?? null;
    if (!$donor_info) {
        continue;
    }
    
    $donor_id = $donor_info['donor_id'];
    
    // Skip if this donor already has blood collection or physical exam (higher priorities)
    if (isset($donors_with_blood[$donor_id]) || isset($donors_with_physical[$donor_id])) {
        continue;
    }
    
    $donors_with_screening[$donor_id] = true;
    
    $history_entry = [
        'no' => $counter,
        'date' => $screening_info['created_at'],
        'surname' => $donor_info['surname'],
        'first_name' => $donor_info['first_name'],
        'donor_id' => $donor_id,
        'screening_id' => $screening_info['screening_id'],
        'donor_reference' => $donor_reference
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
    <title>Donor Lookup Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .duplicate { background-color: #ffe6e6; }
        .issue { background-color: #fff3cd; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Donor Lookup Debug</h1>
        
        <div class="debug-section">
            <h3>Summary</h3>
            <ul>
                <li>Total Donor Forms: <?php echo count($donor_forms); ?></li>
                <li>Total Screening Forms: <?php echo count($screening_forms); ?></li>
                <li>Total Processed Entries: <?php echo count($donor_history); ?></li>
                <li>Duplicates Found: <?php echo count($duplicates_found); ?></li>
            </ul>
        </div>

        <div class="debug-section">
            <h3>Donor Form Fields</h3>
            <div class="alert alert-info">
                Available fields in donor_forms: <?php echo implode(', ', $donor_fields); ?>
            </div>
        </div>

        <div class="debug-section">
            <h3>Screening Form Fields</h3>
            <div class="alert alert-info">
                Available fields in screening_forms: <?php echo implode(', ', $screening_fields); ?>
            </div>
        </div>

        <div class="debug-section">
            <h3>Screening Form Donor References</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Screening ID</th>
                        <th>Donor ID</th>
                        <th>Donor Form ID</th>
                        <th>All Fields</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($screening_donor_references, 0, 10) as $ref): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ref['screening_id']); ?></td>
                            <td><?php echo htmlspecialchars($ref['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($ref['donor_form_id']); ?></td>
                            <td><?php echo htmlspecialchars(implode(', ', $ref['all_fields'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                        <th>Donor Reference</th>
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
                            <td><?php echo htmlspecialchars($entry['donor_reference']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="debug-section">
            <h3>Sample Donor Forms</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Donor ID</th>
                        <th>Name</th>
                        <th>All Fields</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($donor_forms, 0, 5) as $donor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($donor['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($donor['surname'] . ', ' . $donor['first_name']); ?></td>
                            <td><?php echo htmlspecialchars(implode(', ', array_keys($donor))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
