<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /REDCROSS/public/login.php");
    exit();
}

// Get current page from URL
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;

// Get all data (same as main file)
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

// Create lookup arrays (same as main file)
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

$blood_by_screening = [];
foreach ($blood_collections as $blood) {
    $blood_by_screening[$blood['screening_id']] = $blood;
}

// Process the donor history (same as main file)
$donors_with_blood = [];
$donors_with_physical = [];
$donors_with_screening = [];

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
    $donors_with_blood[$donor_id] = true;
    
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
        'stage' => 'blood_collection'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 2: Process Physical Examinations
foreach ($physical_exams as $physical_info) {
    $donor_id = $physical_info['donor_id'];
    if (isset($donors_with_blood[$donor_id])) continue;
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) continue;
    
    $donors_with_physical[$donor_id] = true;
    
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
        'stage' => 'physical_examination'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 3: Process Screening Forms
foreach ($screening_forms as $screening_info) {
    $donor_form_id = $screening_info['donor_form_id'];
    $donor_info = $donors_by_form_id[$donor_form_id] ?? null;
    if (!$donor_info) continue;
    
    $donor_id = $donor_info['donor_id'];
    if (isset($donors_with_blood[$donor_id]) || isset($donors_with_physical[$donor_id])) continue;
    
    $donors_with_screening[$donor_id] = true;
    
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
        'stage' => 'screening_form'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 4: Process Donor Forms
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
        'interviewer' => $medical_info['interviewer_name'] ?? 'N/A',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Screening)' : 'New (Screening)',
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
        'donor_id' => $donor_id,
        'stage' => 'medical_review'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// Sort the data (same as main file)
usort($donor_history, function($a, $b) {
    $a_is_new = strpos($a['donor_type'], 'New') === 0;
    $b_is_new = strpos($b['donor_type'], 'New') === 0;
    
    if ($a_is_new && !$b_is_new) return -1;
    if (!$a_is_new && $b_is_new) return 1;
    
    $stage_priority = [
        'Medical' => 1,
        'Screening' => 2,
        'Physical' => 3,
        'Collection' => 4,
        'Completed' => 5
    ];
    
    $a_stage_name = '';
    $b_stage_name = '';
    foreach ($stage_priority as $stage => $priority) {
        if (strpos($a['donor_type'], $stage) !== false) {
            $a_stage_name = $stage;
        }
        if (strpos($b['donor_type'], $stage) !== false) {
            $b_stage_name = $stage;
        }
    }
    
    if ($a_stage_name !== $b_stage_name) {
        return $stage_priority[$a_stage_name] - $stage_priority[$b_stage_name];
    }
    
    return strtotime($a['date']) - strtotime($b['date']);
});

// Renumber after sorting
$counter = 1;
foreach ($donor_history as &$entry) {
    $entry['no'] = $counter++;
}

// Pagination logic (same as main file)
$total_records = count($donor_history);
$total_pages = ceil($total_records / $records_per_page);
$offset = ($current_page - 1) * $records_per_page;

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Slice the array to get only the records for the current page
$paginated_history = array_slice($donor_history, $offset, $records_per_page);

// Renumber the entries for the current page
$page_counter = 1;
foreach ($paginated_history as &$entry) {
    $entry['no'] = $page_counter++;
}

// Check for duplicates in paginated data
$donor_ids_seen = [];
$duplicates_found = [];
foreach ($paginated_history as $entry) {
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
    <title>Pagination Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .duplicate { background-color: #ffe6e6; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Pagination Debug - Page <?php echo $current_page; ?></h1>
        
        <div class="debug-section">
            <h3>Pagination Summary</h3>
            <ul>
                <li>Total Records: <?php echo $total_records; ?></li>
                <li>Records Per Page: <?php echo $records_per_page; ?></li>
                <li>Total Pages: <?php echo $total_pages; ?></li>
                <li>Current Page: <?php echo $current_page; ?></li>
                <li>Offset: <?php echo $offset; ?></li>
                <li>Records on Current Page: <?php echo count($paginated_history); ?></li>
                <li>Duplicates on Current Page: <?php echo count($duplicates_found); ?></li>
            </ul>
        </div>

        <?php if (!empty($duplicates_found)): ?>
        <div class="debug-section">
            <h3>Duplicates Found on Current Page</h3>
            <div class="alert alert-danger">
                Found <?php echo count($duplicates_found); ?> duplicate entries on page <?php echo $current_page; ?>!
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

        <div class="debug-section">
            <h3>All Records on Current Page</h3>
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
                    <?php foreach ($paginated_history as $entry): ?>
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

        <div class="debug-section">
            <h3>Navigation</h3>
            <nav aria-label="Debug navigation">
                <ul class="pagination justify-content-center">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
</body>
</html>



