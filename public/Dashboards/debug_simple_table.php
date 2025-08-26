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
    
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    
    $history_entry = [
        'no' => $counter,
        'date' => $blood_info['start_time'] ?? $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_id' => $donor_id,
        'stage' => 'blood_collection',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Completed)' : 'New (Completed)',
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 2: Process Physical Examinations
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
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Collection)' : 'New (Collection)',
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System'
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
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Physical)' : 'New (Physical)',
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System'
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
        'donor_id' => $donor_id,
        'stage' => 'medical_review',
        'donor_type' => ($medical_info && isset($medical_info['medical_approval'])) ? 'Returning (Screening)' : 'New (Screening)',
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// Apply pagination (same as main dashboard)
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$total_records = count($donor_history);
$total_pages = ceil($total_records / $records_per_page);

if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$donor_history = array_slice($donor_history, $offset, $records_per_page);

$page_counter = 1;
foreach ($donor_history as &$entry) {
    $entry['no'] = $page_counter++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Table Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table th {
            background-color: #b22222;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Simple Table Debug</h1>
        <p>Current Page: <?php echo $current_page; ?></p>
        <p>Total Records: <?php echo $total_records; ?></p>
        <p>Records on this page: <?php echo count($donor_history); ?></p>
        
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Date</th>
                    <th>Surname</th>
                    <th>First Name</th>
                    <th>Interviewer</th>
                    <th>Donor Type</th>
                    <th>Registered via</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($donor_history && is_array($donor_history)): ?>
                    <?php foreach($donor_history as $index => $entry): ?>
                        <tr>
                            <td><?php echo isset($entry['no']) ? $entry['no'] : ''; ?></td>
                            <td><?php 
                                if (isset($entry['date'])) {
                                    $date = new DateTime($entry['date']);
                                    echo $date->format('F d, Y');
                                } else {
                                    echo 'N/A';
                                }
                            ?></td>
                            <td><?php echo isset($entry['surname']) ? htmlspecialchars($entry['surname']) : ''; ?></td>
                            <td><?php echo isset($entry['first_name']) ? htmlspecialchars($entry['first_name']) : ''; ?></td>
                            <td>N/A</td>
                            <td><?php echo htmlspecialchars($entry['donor_type']); ?></td>
                            <td><?php echo isset($entry['registered_via']) ? htmlspecialchars($entry['registered_via']) : ''; ?></td>
                            <td>
                                <button type="button" class="btn btn-info btn-sm me-1" style="width: 35px; height: 30px;">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-warning btn-sm" style="width: 35px; height: 30px;">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No donor records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Donor medical history navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">Previous</a>
                </li>
                
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</body>
</html>



