<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Lightweight endpoint to transition a donor from screening to physical examination review
// - If physical_examination record for donor_id exists: update needs_review=true and updated_at
// - If none exists: create with needs_review=true and updated_at
// - Always set screening_form.needs_review=false for the donor's screening
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $payload = json_decode($raw_input, true);
    // Support both JSON and form-encoded
    if (!$payload || !is_array($payload)) {
        $payload = $_POST;
    }
    if (is_array($payload) && isset($payload['action']) && $payload['action'] === 'transition_to_physical') {
        header('Content-Type: application/json');

        try {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                exit;
            }
            if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $donor_id = intval($payload['donor_id'] ?? 0);
            if ($donor_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid donor_id']);
                exit;
            }

            // Get screening_id if provided (from screening form submission)
            $screening_id = $payload['screening_id'] ?? null;

            $now_iso = gmdate('c');

            // 1) Check if physical_examination exists
            $check_ch = curl_init();
            curl_setopt_array($check_ch, [
                CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $donor_id . '&limit=1',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]
            ]);
            $check_resp = curl_exec($check_ch);
            $check_http = curl_getinfo($check_ch, CURLINFO_HTTP_CODE);
            curl_close($check_ch);

            $existing_exam = ($check_http === 200) ? (json_decode($check_resp, true) ?: []) : [];
            $has_existing = is_array($existing_exam) && !empty($existing_exam);

            // 2) Insert or Update physical_examination
            if ($has_existing) {
                // UPDATE existing record - only update needs_review and updated_at
                $pe_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id);
                curl_setopt($pe_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                
                $pe_body = [
                    'needs_review' => true,
                    'updated_at' => $now_iso
                ];
                
                // Add screening_id if provided
                if ($screening_id) {
                    $pe_body['screening_id'] = $screening_id;
                }
            } else {
                // INSERT new record - include all required fields
                $pe_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
                curl_setopt($pe_ch, CURLOPT_POST, true);
                
                $pe_body = [
                    'donor_id' => $donor_id,
                    'needs_review' => true,
                    'updated_at' => $now_iso
                ];
                
                // Add screening_id if provided
                if ($screening_id) {
                    $pe_body['screening_id'] = $screening_id;
                }
            }
            curl_setopt($pe_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($pe_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            curl_setopt($pe_ch, CURLOPT_POSTFIELDS, json_encode($pe_body));
            $pe_resp = curl_exec($pe_ch);
            $pe_http = curl_getinfo($pe_ch, CURLINFO_HTTP_CODE);
            curl_close($pe_ch);

            // Log the physical examination update attempt
            error_log("Physical examination update - Donor ID: $donor_id, Has existing: " . ($has_existing ? 'true' : 'false') . ", HTTP: $pe_http, Response: $pe_resp, Body: " . json_encode($pe_body));

            // For inserts, some schemas require more fields; tolerate failure and continue as long as screening update proceeds
            $pe_ok = $has_existing ? ($pe_http >= 200 && $pe_http < 300) : ($pe_http === 201);

            // 3) Set medical_history.needs_review=false
            $mh_ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id);
            curl_setopt($mh_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($mh_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($mh_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            $mh_body = [
                'needs_review' => false
            ];
            curl_setopt($mh_ch, CURLOPT_POSTFIELDS, json_encode($mh_body));
            $mh_resp = curl_exec($mh_ch);
            $mh_http = curl_getinfo($mh_ch, CURLINFO_HTTP_CODE);
            curl_close($mh_ch);

            if (!($mh_http >= 200 && $mh_http < 300)) {
                echo json_encode(['success' => false, 'message' => 'Failed updating medical_history', 'http' => $mh_http]);
                exit;
            }

            // 4) Set screening_form.needs_review=false
            $sf_ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . $donor_id);
            curl_setopt($sf_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($sf_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($sf_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            $sf_body = [
                'needs_review' => false
            ];
            curl_setopt($sf_ch, CURLOPT_POSTFIELDS, json_encode($sf_body));
            $sf_resp = curl_exec($sf_ch);
            $sf_http = curl_getinfo($sf_ch, CURLINFO_HTTP_CODE);
            curl_close($sf_ch);

            if (!($sf_http >= 200 && $sf_http < 300)) {
                echo json_encode(['success' => false, 'message' => 'Failed updating screening_form', 'http' => $sf_http]);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Transitioned to physical examination review - Medical history and screening form updated', 'physical_updated' => $pe_ok]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Add this function at the top with other PHP code
function generateSecureToken($donor_id) {
    // Create a unique token using donor_id and a random component
    $random = bin2hex(random_bytes(16));
    $timestamp = time();
    $token = hash('sha256', $donor_id . $random . $timestamp);
    
    // Store the token mapping in the session
    if (!isset($_SESSION['donor_tokens'])) {
        $_SESSION['donor_tokens'] = [];
    }
    $_SESSION['donor_tokens'][$token] = [
        'donor_id' => $donor_id,
        'expires' => time() + 3600 // Token expires in 1 hour
    ];
    
    return $token;
}

// Add this function near the top after session_start()
function hashDonorId($donor_id) {
    $salt = "RedCross2024"; // Adding a salt for extra security
    return hash('sha256', $donor_id . $salt);
}

// Helper function to determine donor type based on eligibility and table IDs
function getDonorType($donor_id, $medical_info, $eligibility_by_donor, $stage = 'medical_review', $screening_info = null, $physical_info = null) {
    // Check if donor has eligibility record (Returning donor)
    $has_eligibility = isset($eligibility_by_donor[$donor_id]);
    
    if ($has_eligibility) {
        // RETURNING DONOR LOGIC - Based on needs_review status
        $needs_review_stage = '';
        
        // Check needs_review status from different tables (highest priority first)
        if ($physical_info && isset($physical_info['needs_review']) && $physical_info['needs_review'] === true) {
            $needs_review_stage = 'Physical';
        }
        elseif ($screening_info && isset($screening_info['needs_review']) && $screening_info['needs_review'] === true) {
            $needs_review_stage = 'Screening';
        }
        elseif ($medical_info && isset($medical_info['needs_review']) && $medical_info['needs_review'] === true) {
            $needs_review_stage = 'Medical';
        }
        else {
            // If no needs_review is true, determine stage based on current stage
    switch ($stage) {
        case 'blood_collection':
                    $needs_review_stage = 'Collection';
            break;
        case 'physical_examination':
                    $needs_review_stage = 'Physical';
            break;
        case 'screening_form':
                    $needs_review_stage = 'Screening';
            break;
        case 'medical_review':
                    $needs_review_stage = 'Medical';
            break;
        default:
                    $needs_review_stage = 'Medical';
            }
    }
    
        return 'Returning';
    } else {
        // NEW DONOR LOGIC - Based on medical_approval and table IDs
        $stage_name = '';
        
        // Check which tables have IDs (highest priority first)
        if (isset($physical_info['physical_examination_id']) && $physical_info['physical_examination_id']) {
            $stage_name = 'Collection';
        }
        elseif (isset($screening_info['screening_id']) && $screening_info['screening_id']) {
            $stage_name = 'Physical';
        }
        elseif (isset($medical_info['medical_history_id']) && $medical_info['medical_history_id']) {
            // Check if medical_approval is "Approved"
            if (isset($medical_info['medical_approval']) && $medical_info['medical_approval'] === 'Approved') {
                $stage_name = 'Screening';
            } else {
                $stage_name = 'Medical';
            }
        }
        else {
            $stage_name = 'Medical'; // Default for new donors (not approved yet)
        }
        
        return 'New';
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /RED-CROSS-THESIS/public/login.php");
    exit();
}
// Check for correct role (admin with role_id 3)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../../public/unauthorized.php");
    exit();
}

// Add pagination settings
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Initialize the donors array
$donors = [];

// OPTIMIZED APPROACH: Parallel cURL requests and selective data fetching
$multi_handle = curl_multi_init();
$curl_handles = [];

// Define the queries with optimized field selection
$queries = [
    'donor_forms' => '/rest/v1/donor_form?select=donor_id,surname,first_name,submitted_at,registration_channel,prc_donor_number&order=submitted_at.desc',
    // include needs_review flag and updated_at to prioritize and display review time
    'medical_histories' => '/rest/v1/medical_history?select=donor_id,medical_history_id,medical_approval,needs_review,created_at,updated_at&order=created_at.desc',
    'screening_forms' => '/rest/v1/screening_form?select=screening_id,donor_form_id,needs_review,created_at&order=created_at.desc',
    'physical_exams' => '/rest/v1/physical_examination?select=donor_id,needs_review,created_at&order=created_at.desc',
    'blood_collections' => '/rest/v1/blood_collection?select=screening_id,start_time&order=start_time.desc',
    'eligibility_records' => '/rest/v1/eligibility?select=donor_id&order=created_at.desc'
];

// Create parallel cURL requests
foreach ($queries as $key => $query) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    $curl_handles[$key] = $ch;
    curl_multi_add_handle($multi_handle, $ch);
}

// Execute all requests in parallel
$running = null;
do {
    curl_multi_exec($multi_handle, $running);
    curl_multi_select($multi_handle);
} while ($running > 0);

// Get results
$donor_forms = json_decode(curl_multi_getcontent($curl_handles['donor_forms']), true) ?: [];
$medical_histories = json_decode(curl_multi_getcontent($curl_handles['medical_histories']), true) ?: [];
$screening_forms = json_decode(curl_multi_getcontent($curl_handles['screening_forms']), true) ?: [];
$physical_exams = json_decode(curl_multi_getcontent($curl_handles['physical_exams']), true) ?: [];
$blood_collections = json_decode(curl_multi_getcontent($curl_handles['blood_collections']), true) ?: [];
$eligibility_records = json_decode(curl_multi_getcontent($curl_handles['eligibility_records']), true) ?: [];

// Clean up
foreach ($curl_handles as $ch) {
    curl_multi_remove_handle($multi_handle, $ch);
    curl_close($ch);
}
curl_multi_close($multi_handle);

// Create optimized lookup arrays with pre-allocated size
$donors_by_id = array_column($donor_forms, null, 'donor_id');
$medical_by_donor = array_column($medical_histories, null, 'donor_id');
$screenings_by_donor = array_column($screening_forms, null, 'donor_form_id');
$physicals_by_donor = array_column($physical_exams, null, 'donor_id');
$blood_by_screening = array_column($blood_collections, null, 'screening_id');
$eligibility_by_donor = array_column($eligibility_records, null, 'donor_id');

// Create sets to track donors already processed at higher priority levels
$donors_with_blood = [];
$donors_with_physical = [];
$donors_with_screening = [];

// Process the donor history with OPTIMIZED HIERARCHY PRIORITY
$donor_history = [];
$counter = 1;

// FILTER: Only process Medical Review stage donors (New Medical and Returning Medical)
// Skip Blood Collections, Physical Examinations, and Screening Forms for New donors

// PRIORITY 1: Process Blood Collections (Highest Priority) - Only for Returning donors
foreach ($blood_collections as $blood_info) {
    $screening_id = $blood_info['screening_id'];
    $screening_info = $screenings_by_donor[$screening_id] ?? null;
    if (!$screening_info) continue;
    
    $donor_info = $donors_by_id[$screening_info['donor_form_id']] ?? null;
    if (!$donor_info) continue;
    
    $donor_id = $donor_info['donor_id'];
    $donors_with_blood[$donor_id] = true;
    
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    $donor_type_label = getDonorType($donor_id, $medical_info, $eligibility_by_donor, 'blood_collection');
    
    // Only include if it's a Returning donor (has eligibility record)
    if (isset($eligibility_by_donor[$donor_id])) {
            $donor_history[] = [
        'no' => $counter++,
        'date' => ($medical_info['updated_at'] ?? null) ?: ($blood_info['start_time'] ?? $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
        'interviewer' => 'N/A', // interviewer_name column doesn't exist in medical_history table
        'donor_type' => $donor_type_label,
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
        'donor_id' => $donor_id,
        'stage' => 'blood_collection',
        'medical_history_id' => $medical_info['medical_history_id'] ?? null
    ];
    }
}

// PRIORITY 2: Process Physical Examinations (Medium Priority) - Only for Returning donors
foreach ($physical_exams as $physical_info) {
    $donor_id = $physical_info['donor_id'];
    if (isset($donors_with_blood[$donor_id])) continue;
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) continue;
    
    $donors_with_physical[$donor_id] = true;
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    // compute donor_type_label safely
    $donor_type_label = getDonorType($donor_id, $medical_info, $eligibility_by_donor, 'physical_examination', null, $physical_info);
    
    // Only include if it's a Returning donor (has eligibility record)
    if (isset($eligibility_by_donor[$donor_id])) {
        $donor_history[] = [
            'no' => $counter++,
            'date' => ($medical_info['updated_at'] ?? null) ?: ($physical_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
            'surname' => $donor_info['surname'] ?? 'N/A',
            'first_name' => $donor_info['first_name'] ?? 'N/A',
            'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
            'interviewer' => 'N/A', // interviewer_name column doesn't exist in medical_history table
            'donor_type' => $donor_type_label,
            'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
            'donor_id' => $donor_id,
            'stage' => 'physical_examination',
            'medical_history_id' => $medical_info['medical_history_id'] ?? null
        ];
    }
}

// PRIORITY 3: Process Screening Forms (Low Priority) - Only for Returning donors
foreach ($screening_forms as $screening_info) {
    $donor_id = $screening_info['donor_form_id'];
    if (isset($donors_with_blood[$donor_id]) || isset($donors_with_physical[$donor_id])) continue;
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) continue;
    
    $donors_with_screening[$donor_id] = true;
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    // compute donor_type_label safely
    $donor_type_label = getDonorType($donor_id, $medical_info, $eligibility_by_donor, 'screening_form', $screening_info);
    
    // Only include if it's a Returning donor (has eligibility record)
    if (isset($eligibility_by_donor[$donor_id])) {
        $donor_history[] = [
            'no' => $counter++,
            'date' => ($medical_info['updated_at'] ?? null) ?: ($screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
            'surname' => $donor_info['surname'] ?? 'N/A',
            'first_name' => $donor_info['first_name'] ?? 'N/A',
            'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
            'interviewer' => 'N/A', // interviewer_name column doesn't exist in medical_history table
            'donor_type' => $donor_type_label,
            'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
            'donor_id' => $donor_id,
            'stage' => 'screening_form',
            'medical_history_id' => $medical_info['medical_history_id'] ?? null
        ];
    }
}

// PRIORITY 4: Process Donor Forms with ONLY registration (Medical Review stage)
// Only include New donors in Medical stage and Returning donors in Medical stage
$all_processed_donors = $donors_with_blood + $donors_with_physical + $donors_with_screening;
foreach ($donor_forms as $donor_info) {
    $donor_id = $donor_info['donor_id'];
    if (isset($all_processed_donors[$donor_id])) continue;
    if (isset($screenings_by_donor[$donor_id])) continue;
    
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    // compute donor_type_label safely
    $donor_type_label = getDonorType($donor_id, $medical_info, $eligibility_by_donor, 'medical_review');
    
    // Only include if it's a New donor (no eligibility record) or Returning donor in Medical stage
    $is_new_donor = !isset($eligibility_by_donor[$donor_id]);
    $is_returning_medical = isset($eligibility_by_donor[$donor_id]) && strpos($donor_type_label, 'Medical') !== false;
    
    if ($is_new_donor || $is_returning_medical) {
        $donor_history[] = [
            'no' => $counter++,
            'date' => ($medical_info['updated_at'] ?? null) ?: ($donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
            'surname' => $donor_info['surname'] ?? 'N/A',
            'first_name' => $donor_info['first_name'] ?? 'N/A',
            'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
            'interviewer' => 'N/A', // interviewer_name column doesn't exist in medical_history table
            'donor_type' => $donor_type_label,
            'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
            'donor_id' => $donor_id,
            'stage' => 'medical_review',
            'medical_history_id' => $medical_info['medical_history_id'] ?? null
        ];
    }
}

// Prioritize donors with empty medical_approval OR needs_review=true, then others by oldest first
usort($donor_history, function($a, $b) use ($medical_by_donor) {
    // Get medical approval and needs_review status directly from database
    $a_medical_approval = !empty($a['donor_id']) && isset($medical_by_donor[$a['donor_id']]) ? ($medical_by_donor[$a['donor_id']]['medical_approval'] ?? null) : null;
    $b_medical_approval = !empty($b['donor_id']) && isset($medical_by_donor[$b['donor_id']]) ? ($medical_by_donor[$b['donor_id']]['medical_approval'] ?? null) : null;
    
    $a_needs_review = !empty($a['donor_id']) && isset($medical_by_donor[$a['donor_id']]) && ($medical_by_donor[$a['donor_id']]['needs_review'] === true);
    $b_needs_review = !empty($b['donor_id']) && isset($medical_by_donor[$b['donor_id']]) && ($medical_by_donor[$b['donor_id']]['needs_review'] === true);
    
    // Priority 1: Donors with empty approval OR needs_review=true
    $a_priority = (empty($a_medical_approval) || $a_needs_review);
    $b_priority = (empty($b_medical_approval) || $b_needs_review);
    
    if ($a_priority !== $b_priority) return $a_priority ? -1 : 1;
    
    // Priority 2: All donors by timestamp (oldest first - ascending)
    $toDt = function($s) {
        try { return new DateTime($s); } catch (Exception $e) { return new DateTime('@0'); }
    };
    $adt = $toDt($a['date'] ?? null);
    $bdt = $toDt($b['date'] ?? null);

    $a_timestamp = (float)$adt->format('U.u');
    $b_timestamp = (float)$bdt->format('U.u');
    
    if ($a_timestamp === $b_timestamp) return 0;
    return ($a_timestamp < $b_timestamp) ? -1 : 1;
});

// Calculate counts from already processed data
$new_count = 0;
$returning_count = 0;
$today_count = 0;
$today = date('Y-m-d');

// Count from donor_history (already processed and sorted)
foreach ($donor_history as $entry) {
    if (strpos($entry['donor_type'], 'New') === 0) {
        $new_count++;
    } elseif (strpos($entry['donor_type'], 'Returning') === 0) {
        $returning_count++;
    }
    
    // Count today's submissions
    if (isset($entry['date'])) {
        $submission_date = date('Y-m-d', strtotime($entry['date']));
        if ($submission_date === $today) {
            $today_count++;
        }
    }
}

$total_records = count($donor_history);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Optimized pagination with duplicate removal
$total_records = count($donor_history);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Remove duplicates and slice for pagination in one operation
$seen_donors = [];
$unique_donor_history = [];
$page_start = ($current_page - 1) * $records_per_page;
$page_end = $page_start + $records_per_page;
$current_count = 0;

foreach ($donor_history as $entry) {
    $donor_key = $entry['donor_id'] . '_' . $entry['stage'];
    if (!isset($seen_donors[$donor_key])) {
        $seen_donors[$donor_key] = true;
        if ($current_count >= $page_start && $current_count < $page_end) {
            $entry['no'] = $current_count - $page_start + 1;
            $unique_donor_history[] = $entry;
        }
        $current_count++;
    }
}

$donor_history = $unique_donor_history;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Medical History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="../../assets/js/screening_form_modal.js"></script>
    <style>
        :root {
            --bg-color: #f5f5f5;
            --text-color: #000;
            --hover-bg: #f0f0f0;
            --primary-color: #b22222; /* Red Cross red */
            --primary-dark: #8b0000; /* Darker red for hover and separator */
            --active-color: #b22222;
            --table-header-bg: #b22222;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Header styling */
        .dashboard-home-header {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-left {
            display: flex;
            align-items: center;
        }
        
        .header-title {
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            color: #333;
        }
        
        .header-date {
            color: #777;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .register-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.4rem 0.75rem;
            border-radius: 3px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .logout-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.4rem 0.75rem;
            border-radius: 3px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background-color: var(--primary-dark);
            color: white;
            text-decoration: none;
        }

        /* Main Content */
        .main-content {
            padding: 1rem;
            background-color: var(--bg-color);
        }
        
        .content-wrapper {
            background-color: white;
            border-radius: 0px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eaeaea;
        }

        .dashboard-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
        }

        .dashboard-date {
            color: #777;
            font-size: 0.9rem;
        }

        /* Status Cards */
        .dashboard-staff-status {
            display: flex;
            justify-content: space-between;
            gap: 1rem; 
            margin-bottom: 1.5rem;
        }
        
        .status-card {
            flex: 1;
            border-radius: 0;
            background-color: white;
            border: 1px solid #e0e0e0;
            padding: 1rem;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            display: block;
            transition: all 0.2s ease-in-out;
        }
        
        .status-card:hover {
            text-decoration: none;
            color: #333;
            background-color: #f8f8f8;
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .status-card.active {
            border-top: 3px solid var(--primary-dark);
            background-color: #f8f8f8;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        
        .dashboard-staff-count {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #333;
        }
        
        .dashboard-staff-title {
            font-weight: bold;
            font-size: 0.95rem;
            margin-bottom: 0;
            color: #555;
        }
        
        .welcome-section {
            margin-bottom: 1.5rem;
        }
        
        .welcome-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0;
            color: #333;
        }

        /* Red line separator */
        .red-separator {
            height: 4px;
            background-color: #8b0000;
            border: none;
            margin: 1.5rem 0;
            width: 100%;
            opacity: 1;
        }

        /* Table Styling */
        .dashboard-staff-tables {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 0;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .dashboard-staff-tables thead th {
            background-color: var(--table-header-bg);
            color: white;
            padding: 10px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 0;
        }

        .dashboard-staff-tables tbody td {
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.95rem;
        }

        .dashboard-staff-tables tbody tr:nth-child(odd) {
            background-color: #f8f9fa;
        }

        .dashboard-staff-tables tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        .dashboard-staff-tables tbody tr:hover {
            background-color: #f0f0f0;
        }

        .dashboard-staff-tables tbody tr{
            cursor: pointer;
        }

        /* Search bar */
        .search-container {
            margin-bottom: 1.5rem;
        }

        #searchInput {
            border-radius: 0;
            height: 45px;
            border-color: #ddd;
            font-size: 1rem;
            padding: 0.5rem 0.75rem;
        }

        /* Pagination Styles */
        .pagination-container {
            margin-top: 2rem;
        }

        .pagination {
            justify-content: center;
        }

        .page-link {
            color: #333;
            border-color: #dee2e6;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .page-link:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
            border-color: #dee2e6;
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #fff;
            border-color: #dee2e6;
        }


        
        /* Section header */
        .section-header {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: #333;
        }

        /* Badge styles for Donor Type and Registered via */
        .badge-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
            line-height: 1;
            white-space: nowrap;
        }
        .badge-new { background-color: #2e7d32; }            /* green */
        .badge-returning { background-color: #1976d2; }      /* blue */
        .badge-mobile { background-color: #fb8c00; }         /* orange */
        .badge-system { background-color: #6a1b9a; }         /* purple */
        /* Extra padding for Registered via badges */
        .badge-registered { padding: 6px 16px; }
        /* Donor type colored text (no badge) */
        .type-new { color: #2e7d32; font-weight: 700; }
        .type-returning { color: #1976d2; font-weight: 700; }

        /* Donor Status modal header styling */
        #deferralStatusModal .modal-header {
            background-color: var(--table-header-bg);
            color: #fff;
        }
        #deferralStatusModal .modal-header .btn-close {
            filter: invert(1);
            opacity: 1;
        }

        /* Blur background when modal is open */
        .modal-backdrop.show {
            backdrop-filter: blur(4px);
        }

        /* Screening Form Modal Styles */
        .screening-modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .screening-modal-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            border-bottom: none;
        }

        .screening-modal-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .screening-modal-body {
            padding: 0;
            background-color: #f8f9fa;
            min-height: 400px;
        }

        .screening-modal-footer {
            background-color: white;
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 15px 15px;
            padding: 1.5rem;
        }

        /* Progress Indicator */
        .screening-progress-container {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            position: relative;
        }

        .screening-progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .screening-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .screening-step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
            margin-bottom: 8px;
        }

        .screening-step-label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
        }

        .screening-step.active .screening-step-number,
        .screening-step.completed .screening-step-number {
            background: #b22222;
            color: white;
        }

        .screening-step.active .screening-step-label,
        .screening-step.completed .screening-step-label {
            color: #b22222;
            font-weight: 600;
        }

        .screening-progress-line {
            position: absolute;
            top: 40%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: 1;
        }

        .screening-progress-fill {
            height: 100%;
            background: #b22222;
            width: 0%;
            transition: width 0.5s ease;
        }

        /* Step Content */
        .screening-step-content {
            display: none;
            padding: 30px;
            animation: fadeIn 0.3s ease;
        }

        .screening-step-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .screening-step-title {
            margin-bottom: 25px;
        }

        .screening-step-title h6 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .screening-step-title p {
            color: #6c757d;
            margin-bottom: 0;
            font-size: 14px;
        }

        /* Form Elements */
        .screening-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .screening-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .screening-input:focus {
            outline: none;
            border-color: #b22222;
            box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.1);
        }

        .screening-input-group {
            position: relative;
        }

        .screening-input-suffix {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-weight: 500;
            pointer-events: none;
        }

        /* Donation Categories */
        .screening-donation-categories {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .screening-category-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .screening-category-card:hover {
            border-color: #b22222;
            box-shadow: 0 4px 12px rgba(178, 34, 34, 0.1);
        }

        .screening-category-title {
            font-size: 16px;
            font-weight: 700;
            color: #b22222;
            margin-bottom: 15px;
            text-align: center;
        }

        .screening-donation-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .screening-donation-option {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border: 2px solid #f8f9fa;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .screening-donation-option:hover {
            border-color: #b22222;
            background: white;
        }

        .screening-donation-option input {
            display: none;
        }

        .screening-radio-custom {
            width: 20px;
            height: 20px;
            border: 2px solid #e9ecef;
            border-radius: 50%;
            margin-right: 12px;
            position: relative;
            transition: all 0.3s ease;
        }

        .screening-donation-option input:checked ~ .screening-radio-custom {
            border-color: #b22222;
            background: #b22222;
        }

        .screening-donation-option input:checked ~ .screening-radio-custom::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .screening-option-text {
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .screening-donation-option input:checked ~ .screening-option-text {
            color: #b22222;
            font-weight: 600;
        }

        /* Detail Cards */
        .screening-detail-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .screening-detail-title {
            font-size: 16px;
            font-weight: 600;
            color: #b22222;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }

        /* History Section */
        .screening-history-question {
            margin-bottom: 25px;
        }

        .screening-radio-group {
            display: flex;
            gap: 20px;
        }

        .screening-radio-option {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 12px 20px;
            border: 2px solid #f8f9fa;
            border-radius: 8px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .screening-radio-option:hover {
            border-color: #b22222;
            background: white;
        }

        .screening-radio-option input {
            display: none;
        }

        .screening-radio-option input:checked ~ .screening-radio-custom {
            border-color: #b22222;
            background: #b22222;
        }

        .screening-radio-option input:checked ~ .screening-option-text {
            color: #b22222;
            font-weight: 600;
        }

        .screening-history-table .table-danger th {
            background-color: #b22222 !important;
            border-color: #b22222 !important;
            color: white;
        }

        /* New Donation History Grid Layout */
        .screening-history-grid {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }

        .screening-history-header {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            background: #b22222;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .screening-history-header-text {
            padding: 12px 16px;
            text-align: left;
        }

        .screening-history-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            border-bottom: 1px solid #e9ecef;
        }

        .screening-history-row:last-child {
            border-bottom: none;
        }

        .screening-history-label {
            background: #f8f9fa;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            font-weight: 500;
            color: #495057;
            border-right: 1px solid #e9ecef;
        }

        .screening-history-column {
            padding: 8px 16px;
            display: flex;
            align-items: center;
        }

        .screening-history-input {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .screening-history-input:focus {
            border-color: #b22222;
            box-shadow: 0 0 0 2px rgba(178, 34, 34, 0.1);
            outline: none;
        }

        .screening-history-input::placeholder {
            color: #6c757d;
            font-style: italic;
        }

        /* Ensure input groups work properly in the grid */
        .screening-history-column .screening-input-group {
            width: 100%;
            position: relative;
        }

        .screening-history-column .screening-input-group .screening-input {
            padding-right: 35px;
        }

        .screening-history-column .screening-input-icon {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            pointer-events: none;
        }

        /* Patient Table */
        .screening-patient-table-container {
            margin-top: 15px;
        }

        .screening-patient-table {
            margin-bottom: 0;
        }

        .screening-patient-table .table-danger th {
            background-color: #b22222 !important;
            border-color: #b22222 !important;
            color: white;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            padding: 12px 8px;
        }

        .screening-patient-table td {
            padding: 8px;
            vertical-align: middle;
        }

        .screening-patient-table .form-control-sm {
            font-size: 13px;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }

        .screening-patient-table .form-control-sm:focus {
            border-color: #b22222;
            box-shadow: 0 0 0 2px rgba(178, 34, 34, 0.1);
        }

        /* Review Section */
        .screening-review-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .screening-review-title {
            font-size: 16px;
            font-weight: 600;
            color: #b22222;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }

        .screening-review-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }

        .screening-review-item:last-child {
            border-bottom: none;
        }

        .screening-review-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 14px;
        }

        .screening-review-value {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .screening-interviewer-info {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
        }

        /* Responsive adjustments for screening form */
        @media (max-width: 991.98px) {
            .screening-progress-steps {
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }
            
            .screening-step {
                min-width: 60px;
            }
            
            .screening-step-label {
                font-size: 10px;
            }
            
            .screening-radio-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .screening-donation-categories {
                gap: 15px;
            }
            
            .screening-patient-table-container {
                overflow-x: auto;
            }
            
            .screening-patient-table {
                min-width: 600px;
            }
            
            .screening-patient-table th,
            .screening-patient-table td {
                min-width: 120px;
                font-size: 12px;
            }
        }
        
        /* Physical Examination Modal Styles */
        #physicalExaminationModal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        #physicalExaminationModal .modal-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        #physicalExaminationModal .modal-body {
            padding: 1.5rem;
            background-color: #f8f9fa;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        #physicalExaminationModal .modal-footer {
            background-color: white;
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 15px 15px;
            padding: 1.5rem;
        }
        
        #physicalExaminationModal .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        #physicalExaminationModal .card-header {
            border-radius: 12px 12px 0 0;
            border-bottom: none;
            padding: 1rem 1.5rem;
        }
        
        #physicalExaminationModal .card-body {
            padding: 1.5rem;
        }
        
        #physicalExaminationModal .form-control,
        #physicalExaminationModal .form-control:read-only {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 14px;
        }
        
        #physicalExaminationModal .form-control:read-only {
            color: #495057;
            font-weight: 500;
        }
        
        #physicalExaminationModal .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        #physicalExaminationModal .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
            border-radius: 8px;
        }
        
        #physicalExaminationModal textarea.form-control {
            resize: none;
            min-height: 80px;
        }
        
        /* Responsive adjustments for physical examination modal */
        @media (max-width: 767.98px) {
            #physicalExaminationModal .modal-dialog {
                margin: 0.5rem;
            }
            
            #physicalExaminationModal .modal-body {
                padding: 1rem;
                max-height: 60vh;
            }
            
            #physicalExaminationModal .card-body {
                padding: 1rem;
            }
            
            #physicalExaminationModal .row.g-2 > .col-6,
            #physicalExaminationModal .row.g-2 > .col-md-4 {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>

<body class="light-mode">
    <div class="container-fluid p-0">
        <!-- Header -->
        <div class="dashboard-home-header">
            <div class="header-left">
                <h4 class="header-title">Interviewer Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
            </div>
            <div class="header-right">
                <button class="register-btn" onclick="showConfirmationModal()">
                    Register Donor
                </button>
                <a href="../../assets/php_func/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="row g-0">
            <!-- Main Content -->
            <main class="col-12 main-content">
                <div class="content-wrapper">
                    <div class="welcome-section">
                        <h2 class="welcome-title">Welcome, Interviewer!</h2>
                        <p class="text-muted mb-0">This dashboard shows only donors in the Medical stage (New Medical and Returning Medical).</p>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="dashboard-staff-status">
                        <a href="#" class="status-card active">
                            <p class="dashboard-staff-count"><?php echo $new_count; ?></p>
                            <p class="dashboard-staff-title">New Donors</p>
                        </a>
                        <a href="#" class="status-card">
                            <p class="dashboard-staff-count"><?php echo $returning_count; ?></p>
                            <p class="dashboard-staff-title">Eligible Donors</p>
                        </a>
                        <a href="#" class="status-card">
                            <p class="dashboard-staff-count"><?php echo $today_count; ?></p>
                            <p class="dashboard-staff-title">Today's Summary</p>
                        </a>
                    </div>
                    
                    <h5 class="section-header">Medical Stage Donors Only</h5>
                    
                    <!-- Search Bar -->
                    <div class="search-container">
                        <input type="text" 
                            class="form-control" 
                            id="searchInput" 
                            placeholder="Search donors...">
                    </div>
                    
                    <hr class="red-separator">
                    
                    <div class="table-responsive">
                        <table class="dashboard-staff-tables table-hover">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Date</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Interviewer</th>
                                    <th>Donor Type</th>
                                    <th>Registered via</th>
                                    <th>View</th>
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php if($donor_history && is_array($donor_history)): ?>
                                    <?php foreach($donor_history as $index => $entry): ?>
                                        <?php
                                        // Calculate age if missing but birthdate is available
                                        if (empty($entry['age']) && !empty($entry['birthdate'])) {
                                            $birthDate = new DateTime($entry['birthdate']);
                                            $today = new DateTime();
                                            $entry['age'] = $birthDate->diff($today)->y;
                                        }
                                        ?>
                                        <tr class="clickable-row" data-donor-id="<?php echo $entry['donor_id']; ?>" data-stage="<?php echo htmlspecialchars($entry['stage']); ?>" data-donor-type="<?php echo htmlspecialchars($entry['donor_type']); ?>">
                                            <td><?php echo $entry['no']; ?></td>
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
                                            <td><?php echo isset($entry['interviewer']) ? htmlspecialchars($entry['interviewer']) : 'N/A'; ?></td>
                                            <td><span class="<?php echo stripos($entry['donor_type'],'returning')===0 ? 'type-returning' : 'type-new'; ?>"><?php echo htmlspecialchars($entry['donor_type']); ?></span></td>
                                            <td><span class="badge-tag badge-registered <?php echo strtolower($entry['registered_via'])==='mobile' ? 'badge-mobile' : 'badge-system'; ?>"><?php echo htmlspecialchars($entry['registered_via']); ?></span></td>
                                            <td>
                                                <button type="button" class="btn btn-info btn-sm view-donor-btn me-1" 
                                                        data-donor-id="<?php echo $entry['donor_id']; ?>" 
                                                        title="View Details"
                                                        style="width: 35px; height: 30px;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No donor records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Donor medical history navigation">
                                <ul class="pagination justify-content-center">
                                    <!-- Previous button -->
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" <?php echo $current_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Previous</a>
                                    </li>
                                    
                                    <!-- Page numbers -->
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <!-- Next button -->
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" <?php echo $current_page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    </div>
    <!-- Deferral Status Modal -->
    <div class="modal fade" id="deferralStatusModal" tabindex="-1" aria-labelledby="deferralStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                    <h5 class="modal-title" id="deferralStatusModalLabel">Donor Status & Donation History</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                    <div id="deferralStatusContent">
                        <div class="d-flex justify-content-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
            
                    
                        </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="proceedToMedicalHistory">Proceed to Medical History</button>
                <button type="button" class="btn btn-outline-primary" id="markReviewFromMain">Mark for Medical Review</button>
                            </div>
                            </div>
                            </div>
</div>

    <!-- Stage Notice Modal (Read-only for non-medical stages) -->
    <div class="modal fade" id="stageNoticeModal" tabindex="-1" aria-labelledby="stageNoticeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stageNoticeModalLabel">Notice: Different Processing Stage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="stageNoticeBody">
                    <!-- Filled dynamically -->
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="stageNoticeViewBtn">View Details</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Returning Donor Info Modal (friendly notice before viewing) -->
    <div class="modal fade" id="returningInfoModal" tabindex="-1" aria-labelledby="returningInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="returningInfoModalLabel">Returning Donor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="returningInfoBody">
                    This donor has previous donations. You can safely view details in read-only mode from here. Processing for new medical history occurs only under New (Medical).
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="returningInfoViewBtn">View Details</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Screening Details Modal -->
    <div class="modal fade" id="screeningDetailsModal" tabindex="-1" aria-labelledby="screeningDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="screeningDetailsLabel">Screening Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="screeningDetailsBody">
                    <!-- Populated dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="markReturningReviewBtn">Mark for Medical Review</button>
            </div>
        </div>
    </div>
</div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title" id="confirmationModalLabel">
                        <i class="fas fa-user-plus me-2"></i>
                        Register New Donor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-0" style="font-size: 1.1rem;">Are you sure you want to proceed to the donor registration form?</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn px-4" style="background-color: #b22222; border-color: #b22222; color: white;" onclick="proceedToDonorForm()">Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Medical History Modal -->
    <div class="modal fade" id="medicalHistoryModal" tabindex="-1" aria-labelledby="medicalHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="medicalHistoryModalLabel">Medical History Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="medicalHistoryModalContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Declaration Form Modal -->
    <div class="modal fade" id="declarationFormModal" tabindex="-1" aria-labelledby="declarationFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="declarationFormModalLabel">Declaration Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="declarationFormModalContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Processing Confirmation Modal -->
    <div class="modal fade" id="dataProcessingConfirmModal" tabindex="-1" aria-labelledby="dataProcessingConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background:#8b0000;color:#fff;">
                    <h5 class="modal-title" id="dataProcessingConfirmLabel">Submit Medical Screening</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please confirm the outcome of this screening.</p>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="screeningOutcome" id="approveOption" value="approve" checked>
                        <label class="form-check-label" for="approveOption">
                            Approve for Donation
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="screeningOutcome" id="draftOption" value="draft">
                        <label class="form-check-label" for="draftOption">
                            Save as Draft
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmProcessingBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
                <div class="modal-body text-center">
                    <div class="spinner-border" style="width: 3.5rem; height: 3.5rem; color: #b22222;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-white mt-3 mb-0">Please wait...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Screening Mark Modal -->
    <div class="modal fade" id="confirmScreeningMarkModal" tabindex="-1" aria-labelledby="confirmScreeningMarkLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmScreeningMarkLabel">Mark Screening for Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Do you want to mark this donor's screening record for review?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmScreeningMarkBtn">Yes, mark it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Confirmation Modal -->
    <div class="modal fade" id="reviewConfirmModal" tabindex="-1" aria-labelledby="reviewConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background:#8b0000;color:#fff;">
                    <h5 class="modal-title" id="reviewConfirmLabel">Review Medical History</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    This will redirect you to the medical history the donor just submitted. Do you want to proceed?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="reviewConfirmProceedBtn">Proceed</button>
                </div>
            </div>
        </div>
    </div>



    <script>
        // Pass PHP data to JavaScript
        const medicalByDonor = <?php echo json_encode($medical_by_donor); ?>;
        
        function showConfirmationModal() {
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            confirmationModal.show();
        }

        function proceedToDonorForm() {
            // Hide confirmation modal
            const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            confirmationModal.hide();

            // Show loading modal
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            loadingModal.show();

            // Redirect after a short delay to show loading animation
            setTimeout(() => {
                window.location.href = '../../src/views/forms/donor-form-modal.php';
            }, 800);
        }
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            const donorTableBody = document.getElementById('donorTableBody');
            const deferralStatusModal = new bootstrap.Modal(document.getElementById('deferralStatusModal'));
            const deferralStatusContent = document.getElementById('deferralStatusContent');
            const stageNoticeModal = new bootstrap.Modal(document.getElementById('stageNoticeModal'));
            const stageNoticeBody = document.getElementById('stageNoticeBody');
            const stageNoticeViewBtn = document.getElementById('stageNoticeViewBtn');
            const returningInfoModal = new bootstrap.Modal(document.getElementById('returningInfoModal'));
            const returningInfoViewBtn = document.getElementById('returningInfoViewBtn');
            const markReturningReviewBtn = document.getElementById('markReturningReviewBtn');
            const markReviewFromMain = document.getElementById('markReviewFromMain');
            
            let currentDonorId = null;
            let allowProcessing = false;
            let modalContextType = 'new_medical'; // 'new_medical' | 'new_other_stage' | 'returning' | 'other'
            let currentStage = null; // 'medical_review' | 'screening_form' | 'physical_examination' | 'blood_collection'
            
            // Store original rows for search reset
            const originalRows = Array.from(donorTableBody.getElementsByTagName('tr'));
            
            // Optimized search functionality with debouncing
            let searchTimeout;
            if (searchInput && searchInput.addEventListener) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performOptimizedSearch(this.value.toLowerCase());
                }, 300); // 300ms debounce
            });
            }
            
            // Add click event to all clickable rows
            document.querySelectorAll('.clickable-row').forEach(row => {
                row.addEventListener('click', function() {
                    const donorId = this.getAttribute('data-donor-id');
                    const stageAttr = this.getAttribute('data-stage');
                    const donorTypeLabel = this.getAttribute('data-donor-type');
                    if (!donorId) return;
                    
                    // Set global variables for modal context
                    window.currentDonorId = donorId;
                    window.currentDonorType = donorTypeLabel || 'New';
                    window.currentDonorStage = stageAttr || 'Medical';
                    
                        currentDonorId = donorId;
                    const lowerType = (donorTypeLabel || '').toLowerCase();
                    const isNew = lowerType.startsWith('new');
                    const isReturning = lowerType.startsWith('returning');
                    // Derive stage from donor type text to avoid mismatches
                    const typeText = lowerType;
                    let stageFromType = 'unknown';
                    if (typeText.includes('medical')) stageFromType = 'medical_review';
                    else if (typeText.includes('screening')) stageFromType = 'screening_form';
                    else if (typeText.includes('physical')) stageFromType = 'physical_examination';
                    else if (typeText.includes('collection') || typeText.includes('completed')) stageFromType = 'blood_collection';
                    const effectiveStage = stageFromType !== 'unknown' ? stageFromType : stageAttr;
                    currentStage = effectiveStage;
                    // Allow processing for new donors in medical_review OR any donor with needs_review=true
                    allowProcessing = (isNew && (effectiveStage === 'medical_review')) || 
                                    (effectiveStage === 'medical_review' && currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review);
                    // Determine modal context type
                    if (allowProcessing) {
                        modalContextType = 'new_medical'; // Can process medical history
                    } else if (isNew) {
                        modalContextType = 'new_other_stage';
                    } else if (isReturning) {
                        modalContextType = 'returning';
                    } else {
                        modalContextType = 'other';
                    }
                    window.modalContextType = modalContextType;
                    
                    if (!allowProcessing && !isReturning) {
                        // Show read-only notice modal
                        const stageTitleMap = {
                            'screening_form': 'Screening Stage',
                            'physical_examination': 'Physical Examination Stage',
                            'blood_collection': 'Blood Collection Stage'
                        };
                        const friendlyStage = stageTitleMap[effectiveStage] || 'Different Stage';
                        const newOrReturningNote = isNew
                            ? `This record is <strong>New</strong> but not in the Medical stage (<strong>${friendlyStage}</strong>).`
                            : `This record is <strong>Returning</strong>. This page is dedicated to processing <strong>New (Medical)</strong> only.`;
                        stageNoticeBody.innerHTML = `
                            <p>${newOrReturningNote}</p>
                            <p><strong>Note:</strong> Medical history processing on this page is available only for <strong>New (Medical)</strong> records.</p>
                            <div class="alert alert-info mb-0">
                                <div><strong>Donor type:</strong> ${donorTypeLabel || ''}</div>
                                <div class="small text-muted">You can view read-only details for reference.</div>
                            </div>`;
                        stageNoticeModal.show();
                        
                        // Bind view details to open the existing details modal without processing
                        if (stageNoticeViewBtn) {
                            stageNoticeViewBtn.onclick = () => {
                            stageNoticeModal.hide();
                            // Prepare details modal in read-only mode
                            deferralStatusContent.innerHTML = `
                                <div class=\"d-flex justify-content-center\">\n                                    <div class=\"spinner-border text-primary\" role=\"status\">\n                                        <span class=\"visually-hidden\">Loading...</span>\n                                    </div>\n                                </div>`;
                            
                            // Hide proceed button in read-only mode
                            const proceedButton = getProceedButton();
                            if (proceedButton && proceedButton.style) {
                                proceedButton.style.display = 'none';
                                proceedButton.textContent = 'Review Medical History';
                            }
                            deferralStatusModal.show();
                            fetchDonorStatusInfo(donorId);
                        };
                        }
                        return;
                    }
                    
                    if (isReturning) {
                        // Check if returning donor has needs_review=true
                        const hasNeedsReview = currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review;
                        
                        if (effectiveStage === 'medical_review' || hasNeedsReview) {
                            // Returning (Medical) OR Returning with needs_review: go straight to details with Review available
                        deferralStatusContent.innerHTML = `
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                    </div>
                            </div>`;
                            const proceedButton = getProceedButton();
                            if (proceedButton && proceedButton.style) {
                                proceedButton.style.display = 'inline-block';
                                proceedButton.textContent = 'Review Medical History';
                            }
                            if (markReviewFromMain) markReviewFromMain.style.display = 'none';
                        deferralStatusModal.show();
                        fetchDonorStatusInfo(donorId);
                            return;
                        }
                        // Returning but not Medical and no needs_review: friendly confirmation with mark option
                        returningInfoModal.show();
                        if (returningInfoViewBtn) {
                            returningInfoViewBtn.onclick = () => {
                            returningInfoModal.hide();
                            deferralStatusContent.innerHTML = `
                                <div class="d-flex justify-content-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>`;
                            const proceedButton = getProceedButton();
                            if (proceedButton && proceedButton.style) proceedButton.style.display = 'none';
                            deferralStatusModal.show();
                            fetchDonorStatusInfo(donorId);
                        };
                        }
                        // Mark for review handler
                        if (markReturningReviewBtn) {
                            markReturningReviewBtn.onclick = () => {
                                fetch('../../assets/php_func/update_needs_review.php', {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json' },
                                    body: new URLSearchParams({ donor_id: donorId })
                                })
                                .then(r => r.json())
                                .then(res => {
                                    if (res && res.success) {
                                        returningInfoModal.hide();
                                        // Use custom modal instead of browser alert
                                        if (window.customConfirm) {
                                            window.customConfirm('Marked for Medical Review.', function() {
                                                // Refresh to apply resorting by date/priority
                                                window.location.reload();
                                            });
                                        } else {
                                            alert('Marked for Medical Review.');
                                            // Refresh to apply resorting by date/priority
                                            window.location.reload();
                                        }
                                        // Optionally update UI label to Returning (Medical)
                                        const row = document.querySelector(`tr[data-donor-id="${donorId}"]`);
                                        if (row) {
                                            const donorTypeCell = row.querySelector('td:nth-child(6)');
                                            if (donorTypeCell && donorTypeCell.textContent.toLowerCase().includes('returning')) {
                                                donorTypeCell.textContent = 'Returning (Medical)';
                                                row.setAttribute('data-donor-type', 'Returning (Medical)');
                                            }
                                        }
                                        // Refresh to apply resorting by date/priority
                                        // window.location.reload(); // Moved to modal callback
                                    } else {
                                        // Use custom modal instead of browser alert
                                        if (window.customConfirm) {
                                            window.customConfirm('Failed to mark for review.', function() {
                                                // Just close the modal, no additional action needed
                                            });
                                        } else {
                                            alert('Failed to mark for review.');
                                        }
                                    }
                                })
                                .catch(() => {
                                    // Use custom modal instead of browser alert
                                    if (window.customConfirm) {
                                        window.customConfirm('Failed to mark for review.', function() {
                                            // Just close the modal, no additional action needed
                                        });
                                    } else {
                                        alert('Failed to mark for review.');
                                    }
                                });
                            };
                        }
                        // Enable main modal mark button only for returning
                        if (markReviewFromMain) {
                            markReviewFromMain.style.display = 'inline-block';
                            markReviewFromMain.onclick = () => {
                                fetch('../../assets/php_func/update_needs_review.php', {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json' },
                                    body: new URLSearchParams({ donor_id: donorId })
                                })
                                .then(r => r.json())
                                .then(res => {
                                    if (res && res.success) {
                                        // Use custom modal instead of browser alert
                                        if (window.customConfirm) {
                                            window.customConfirm('Marked for Medical Review.', function() {
                                                // Refresh to apply resorting by date/priority
                                                window.location.reload();
                                            });
                                        } else {
                                            alert('Marked for Medical Review.');
                                            // Refresh to apply resorting by date/priority
                                            window.location.reload();
                                        }
                                        const row = document.querySelector(`tr[data-donor-id="${donorId}"]`);
                                        if (row) {
                                            const donorTypeCell = row.querySelector('td:nth-child(6)');
                                            if (donorTypeCell && donorTypeCell.textContent.toLowerCase().includes('returning')) {
                                                donorTypeCell.textContent = 'Returning (Medical)';
                                                row.setAttribute('data-donor-type', 'Returning (Medical)');
                                            }
                                            const dateCell = row.querySelector('td:nth-child(2)');
                                            if (dateCell && res.updated_at) {
                                                const d = new Date(res.updated_at);
                                                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                                                dateCell.textContent = d.toLocaleDateString('en-US', options);
                                            }
                                        }
                                        // Refresh to apply resorting by date/priority
                                        // window.location.reload(); // Moved to modal callback
                                    } else {
                                        // Use custom modal instead of browser alert
                                        if (window.customConfirm) {
                                            window.customConfirm('Failed to mark for review.', function() {
                                                // Just close the modal, no additional action needed
                                            });
                                        } else {
                                            alert('Failed to mark for review.');
                                        }
                                    }
                                })
                                .catch(() => {
                                    // Use custom modal instead of browser alert
                                    if (window.customConfirm) {
                                        window.customConfirm('Failed to mark for review.', function() {
                                            // Just close the modal, no additional action needed
                                        });
                                    } else {
                                        alert('Failed to mark for review.');
                                    }
                                });
                            };
                        }
                        return;
                    }
                    
                    // Allow processing: show details and keep proceed button visible
                    deferralStatusContent.innerHTML = `
                        <div class="d-flex justify-content-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>`;
                    
                    const proceedButton = getProceedButton();
                    if (proceedButton && proceedButton.style) {
                        proceedButton.style.display = 'inline-block';
                        proceedButton.textContent = 'Review Medical History';
                    }
                    // Hide mark button for non-returning/new-medical flow
                    if (markReviewFromMain) markReviewFromMain.style.display = 'none';
                    deferralStatusModal.show();
                    fetchDonorStatusInfo(donorId);
                });
            });
            
            // Function to fetch donor status information
            function fetchDonorStatusInfo(donorId) {
                // First, fetch donor information
                fetch('../../assets/php_func/fetch_donor_info.php?donor_id=' + donorId)
                    .then(response => response.json())
                    .then(donorData => {
                        // Next, check physical examination table for deferral status
                        fetch('../../assets/php_func/check_deferral_status.php?donor_id=' + donorId)
                            .then(response => response.json())
                            .then(deferralData => {
                                displayDonorInfo(donorData, deferralData);
                                
                                // After getting deferral info, fetch screening info
                                fetch('../../assets/php_func/fetch_screening_info.php?donor_id=' + donorId)
                                    .then(response => response.json())
                                    .then(() => {})
                                    .catch(error => { console.error("Error fetching screening info:", error); });
                            })
                            .catch(error => {
                                console.error("Error checking deferral status:", error);
                                deferralStatusContent.innerHTML = `<div class="alert alert-danger">Error checking deferral status: ${error.message}</div>`;
                            });
                    })
                    .catch(error => {
                        console.error("Error fetching donor info:", error);
                        deferralStatusContent.innerHTML = `<div class="alert alert-danger">Error fetching donor information: ${error.message}</div>`;
                    });
            }
            
            // Function to display donor and deferral information
            function displayDonorInfo(donorData, deferralData) {
                let donorInfoHTML = '';
                const safe = (v) => v || 'N/A';
                
                if (donorData && donorData.success) {
                    const donor = donorData.data || {};
                    const fullName = `${safe(donor.surname)}, ${safe(donor.first_name)} ${safe(donor.middle_name)}`.trim();
                    const currentStatus = (() => {
                        // Get donor type from the row data
                        const donorType = window.currentDonorType || 'New';
                        const donorStage = window.currentDonorStage || 'Medical';
                        
                        if (donorType === 'New') {
                            if (donorStage === 'Medical') return 'Medical';
                            if (donorStage === 'Screening') return 'Screening';
                            if (donorStage === 'Physical') return 'Physical';
                            if (donorStage === 'Collection') return 'Collection';
                            return 'Medical'; // Default
                        } else if (donorType === 'Returning') {
                            if (donorStage === 'Medical') return 'Medical';
                            if (donorStage === 'Screening') return 'Screening';
                            if (donorStage === 'Physical') return 'Physical';
                            if (donorStage === 'Collection') return 'Collection';
                            return 'Medical'; // Default
                        }
                        return 'Medical'; // Default fallback
                    })();
                    
                    // Header
                        donorInfoHTML += `
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 style="color:#9c0000; font-weight:700; margin:0;">Donor Profile</h5>
                                <div style="font-weight:700; font-size:1.1rem;"><strong>Donor ID:</strong> ${safe(donor.prc_donor_number || 'N/A')}</div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center small text-muted">
                                <div><strong>${fullName}</strong> &nbsp; ${safe(donor.age)}${donor.sex ? ', ' + donor.sex : ''}</div>
                                <div><strong>Current Status:</strong> ${currentStatus}</div>
                            </div>
                            <hr/>
                            </div>`;
                        
                    // Donor Information (from donor_form)
                            donorInfoHTML += `
                        <div class="mb-3">
                            <h6 class="mb-2">Donor Information</h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Birthdate</label>
                                    <input class="form-control" value="${safe(donor.birthdate)}" disabled>
                                    </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Civil Status</label>
                                    <input class="form-control" value="${safe(donor.civil_status)}" disabled>
                                            </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Address</label>
                                    <input class="form-control" value="${safe(donor.permanent_address)}" disabled>
                                        </div>
                                            <div class="col-md-6">
                                    <label class="form-label small mb-1">Nationality</label>
                                    <input class="form-control" value="${safe(donor.nationality)}" disabled>
                                            </div>
                                            <div class="col-md-6">
                                    <label class="form-label small mb-1">Mobile Number</label>
                                    <input class="form-control" value="${safe(donor.mobile || donor.telephone)}" disabled>
                                            </div>
                                            <div class="col-md-6">
                                    <label class="form-label small mb-1">Occupation</label>
                                    <input class="form-control" value="${safe(donor.occupation)}" disabled>
                                        </div>
                                    </div>
                                </div>`;
                    
                    // Donation History Table
                    let donations = Array.isArray(donor.donation_history) ? donor.donation_history : [];
                    // If no donation history array, synthesize a single row from eligibility info
                    if ((!donations || donations.length === 0) && donor.eligibility) {
                        const el = donor.eligibility;
                        donations = [{
                            date: el.start_date || el.created_at || donor.latest_submission || null,
                            gateway: donor.registration_channel || 'System',
                            blood_type: el.blood_type || '-',
                            next_eligible_date: el.end_date || null,
                            medical_history_status: (donor.medical_history && (donor.medical_history.medical_approval || donor.medical_history.fitness_result === 'Accepted')) ? 'Successful' : 'Pending'
                        }];
                    }
                    let donationRows = '';
                    donations.forEach(d => {
                        donationRows += `
                            <tr>
                                <td>${safe(formatDate(d.date))}</td>
                                <td>${safe(d.gateway || donor.registration_channel)}</td>
                                <td>${safe(d.blood_type)}</td>
                                <td>${safe((donor.eligibility && donor.eligibility.end_date ? formatDate(donor.eligibility.end_date) : (d.next_eligible_date ? formatDate(d.next_eligible_date) : null)))}</td>
                                <td>${safe(d.medical_history_status)}</td>
                                </tr>`;
                            });
                    if (!donationRows) {
                        donationRows = `<tr><td colspan="5" class="text-center text-muted">No donation history available</td></tr>`;
                    }
                            donorInfoHTML += `
                        <div class="mb-3">
                            <h6 class="mb-2">Donation History</h6>
                                        <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                                <thead class="table-dark">
                                                    <tr>
                                            <th>Date of Donation</th>
                                            <th>Gateway</th>
                                            <th>Blood</th>
                                            <th>Next Eligible Date</th>
                                            <th>Medical History</th>
                                                    </tr>
                                                </thead>
                                    <tbody>${donationRows}</tbody>
                                            </table>
                                    </div>
                                </div>`;
                    
                    // Medical History Table (summarized)
                    const medHist = donor.medical_history || null;
                    let medRows = '';
                    if (medHist) {
                        medRows += `
                            <tr>
                                <td>${safe(formatDate(medHist.date_screened || donor.latest_submission))}</td>
                                <td>${safe(medHist.vital_signs || 'Normal')}</td>
                                <td>${safe(medHist.hematology || 'Within Normal Range')}</td>
                                <td>${safe(medHist.fitness_result || (medHist.medical_approval ? 'Accepted' : 'Deferred'))}</td>
                                <td>${safe(medHist.physician || '-')}</td>
                                <td>${safe(medHist.action || '')}</td>
                            </tr>`;
                    }
                    if (!medRows) {
                        medRows = `<tr><td colspan="6" class="text-center text-muted">No medical history recorded</td></tr>`;
                    }
                        donorInfoHTML += `
                        <div class="mb-2">
                            <h6 class="mb-2">Medical History</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Date Screened</th>
                                            <th>Vital Signs</th>
                                            <th>Hematology</th>
                                            <th>Fitness Result</th>
                                            <th>Physician</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="returningMedRows">${medRows}</tbody>
                                </table>
                                </div>
                        </div>`;
                    
                    // If returning, enrich medical section with screening details
                    if (modalContextType === 'returning' && donor.donor_id) {
                        fetch('../../assets/php_func/fetch_screening_info.php?donor_id=' + donor.donor_id)
                            .then(r => r.json())
                            .then(scr => {
                                if (scr && scr.success && scr.data) {
                                    const s = scr.data;
                                    // Build a summarized single-row view inside the table
                                    const dateScreened = formatDate(s.interview_date) || formatDate(donor.latest_submission) || 'N/A';
                                    const vital = s.body_weight ? 'BW: ' + s.body_weight + ' kg' : 'N/A';
                                    const hematology = [
                                        s.hemoglobin ? 'Hgb: ' + s.hemoglobin : null,
                                        s.hematocrit ? 'Hct: ' + s.hematocrit : null,
                                        s.rbc_count ? 'RBC: ' + s.rbc_count : null,
                                        s.wbc_count ? 'WBC: ' + s.wbc_count : null,
                                        s.platelet_count ? 'Plt: ' + s.platelet_count : null,
                                        (s.blood_type || (donor.eligibility ? donor.eligibility.blood_type : null)) ? 'BT: ' + (s.blood_type || donor.eligibility.blood_type) : null
                                    ].filter(Boolean).join(', ') || 'N/A';
                                    const fitness = (donor.medical_history && (donor.medical_history.medical_approval || donor.medical_history.fitness_result === 'Accepted')) ? 'Accepted' : 'Deferred';
                                    const physician = (donor.medical_history && donor.medical_history.physician) ? donor.medical_history.physician : '-';
                                    const rowHtml = '<tr><td>' + dateScreened + '</td><td>' + vital + '</td><td>' + hematology + '</td><td>' + fitness + '</td><td>' + physician + '</td><td><button type="button" class="btn btn-sm btn-outline-primary" id="viewScreeningBtn"><i class="fas fa-eye"></i></button></td></tr>';
                                    const tbody = document.getElementById('returningMedRows');
                                    if (tbody) tbody.innerHTML = rowHtml;
                                    // Wire action button to show full details
                                    const btn = document.getElementById('viewScreeningBtn');
                                    if (btn) {
                                        btn.onclick = () => {
                                            const modalBody = document.getElementById('screeningDetailsBody');
                                            if (modalBody) {
                                                // Calculate blood type separately to avoid template literal syntax issues
                                                const bloodType = s.blood_type || (donor.eligibility && donor.eligibility.blood_type ? donor.eligibility.blood_type : 'N/A');
                                                const modalContent = '<div class="row g-2">' +
                                                    '<div class="col-md-4"><div class="small text-muted">Interview Date</div><div>' + (formatDate(s.interview_date) || 'N/A') + '</div></div>' +
                                                    '<div class="col-md-4"><div class="small text-muted">Body Weight</div><div>' + (s.body_weight ? s.body_weight + ' kg' : 'N/A') + '</div></div>' +
                                                    '<div class="col-md-4"><div class="small text-muted">Specific Gravity</div><div>' + (s.specific_gravity || 'N/A') + '</div></div>' +
                                                    '<div class="col-md-4"><div class="small text-muted">Hemoglobin</div><div>' + (s.hemoglobin || 'N/A') + '</div></div>' +
                                                    '<div class="col-md-4"><div class="small text-muted">Hematocrit</div><div>' + (s.hematocrit || 'N/A') + '</div></div>' +
                                                    '<div class="col-md-4"><div class="small text-muted">RBC Count</div><div>' + (s.rbc_count || 'N/A') + '</div></div>' +
                                                    '<div class="col-md-4"><div class="small text-muted">WBC Count</div><div>' + (s.wbc_count || 'N/A') + '</div></div>' +
                                                    '<div class="col-md-4"><div class="small text-muted">Platelet Count</div><div>' + (s.platelet_count || 'N/A') + '</div></div>' +
                                                    '<div class="col-md-4"><div class="small text-muted">Blood Type</div><div>' + bloodType + '</div></div>' +
                                                    '</div>';
                                                modalBody.innerHTML = modalContent;
                                                const m = new bootstrap.Modal(document.getElementById('screeningDetailsModal'));
                                                m.show();
                                            }
                                        };
                                    }
                                }
                            })
                            .catch(() => {});
                    }
                }
                
                // Returning banner (suppress when in Medical stage or has needs_review)
                if (modalContextType === 'returning' && currentStage !== 'medical_review' && !(currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review)) {
                    donorInfoHTML = '<div class="alert alert-primary mb-3"><strong>Returning donor:</strong> This donor has previous donations. Review details as needed. Processing here is for New (Medical) and donors needing review only.</div>' + donorInfoHTML;
                }
                
                deferralStatusContent.innerHTML = donorInfoHTML;
                // Ensure proceed button visibility reflects current stage capability
                try {
                    const proceedButton = getProceedButton();
                    if (proceedButton && proceedButton.style) {
                        // Show button for donors who can process OR have needs_review=true
                        const hasNeedsReview = currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review;
                        const showReview = allowProcessing || currentStage === 'medical_review' || hasNeedsReview;
                        proceedButton.style.display = showReview ? 'inline-block' : 'none';
                        proceedButton.textContent = 'Review Medical History';
                    }
                } catch (e) {}
            }
            
            // Helper function to capitalize first letter
            function ucfirst(string) {
                if (!string) return '';
                return string.charAt(0).toUpperCase() + string.slice(1);
            }
            
            // Format date helper function
            function formatDate(dateString) {
                if (!dateString) return null;
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                });
            }
            
            // Handle proceed button click with confirmation
            function openMedicalHistoryForCurrentDonor() {
                if (!currentDonorId) return;
                
                // Check if this is a returning donor with physical examination data
                const isReturningDonor = window.currentDonorType && window.currentDonorType.toLowerCase().includes('returning');
                
                if (isReturningDonor) {
                    // For returning donors, show physical examination modal first
                    showPhysicalExaminationModal(currentDonorId);
                } else {
                    // For new donors, proceed directly to medical history
                    proceedToMedicalHistoryModal();
                }
            }
            
            // Function to show physical examination modal
            function showPhysicalExaminationModal(donorId) {
                const physicalModal = new bootstrap.Modal(document.getElementById('physicalExaminationModal'));
                const modalContent = document.getElementById('physicalExaminationModalContent');
                
                // Reset modal content to loading state
                modalContent.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                // Show the modal
                physicalModal.show();
                
                // Fetch physical examination data
                fetch('../../assets/php_func/fetch_physical_examination_info.php?donor_id=' + donorId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            displayPhysicalExaminationInfo(data.data, donorId);
                        } else {
                            modalContent.innerHTML = `
                                <div class="alert alert-info">
                                    <h6>No Physical Examination Data</h6>
                                    <p>This returning donor doesn't have physical examination records yet.</p>
                                    <p>You can proceed directly to medical history review.</p>
                                </div>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching physical examination info:', error);
                        modalContent.innerHTML = `
                            <div class="alert alert-danger">
                                <h6>Error Loading Data</h6>
                                <p>Unable to load physical examination information. Please try again.</p>
                            </div>`;
                    });
            }
            
            // Function to display physical examination information
            function displayPhysicalExaminationInfo(physicalData, donorId) {
                const modalContent = document.getElementById('physicalExaminationModalContent');
                
                // Format date
                const formatDate = (dateString) => {
                    if (!dateString) return 'N/A';
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                };
                
                // Calculate age from birthdate
                const calculateAge = (birthdate) => {
                    if (!birthdate) return 'N/A';
                    const birth = new Date(birthdate);
                    const today = new Date();
                    let age = today.getFullYear() - birth.getFullYear();
                    const monthDiff = today.getMonth() - birth.getMonth();
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                        age--;
                    }
                    return age;
                };
                
                const safe = (value) => value || 'N/A';
                const fullName = `${safe(physicalData.surname)}, ${safe(physicalData.first_name)} ${safe(physicalData.middle_name)}`.trim();
                const age = calculateAge(physicalData.birthdate);
                const donorAgeGender = `${age}${physicalData.sex ? ', ' + physicalData.sex : ''}`;
                
                const physicalHTML = `
                    <!-- Donor Information Header (Simple Layout like 2nd image) -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div style="color:#666; font-size:0.9rem; margin-bottom:5px;">Date Screened: ${formatDate(physicalData.created_at)}</div>
                                <div style="color:#333; font-weight:700; font-size:1.2rem; margin-bottom:5px;">${fullName}</div>
                                <div style="color:#333; font-size:1rem;">${donorAgeGender}</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="color:#666; font-size:0.9rem; margin-bottom:5px;">&nbsp;</div>
                                <div style="color:#333; font-weight:700; font-size:1.1rem; margin-bottom:5px;">Donor ID ${safe(physicalData.prc_donor_number || 'N/A')}</div>
                                <div style="color:#333; font-size:1rem;">${safe(physicalData.blood_type || 'N/A')}</div>
                            </div>
                        </div>
                        <hr style="margin: 15px 0;"/>
                    </div>
                    
                    <!-- Physical Examination Results Section -->
                    <div class="mb-3">
                        <h6 class="mb-2" style="color:#b22222; font-weight:700;">Physical Examination Results</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Interviewer Remarks</label>
                                <input class="form-control" value="${safe(physicalData.reason)}" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small mb-1">Physical Exam Notes</label>
                                <input class="form-control" value="${safe(physicalData.skin)}${physicalData.heent ? ' | HEENT: ' + physicalData.heent : ''}${physicalData.heart_and_lungs ? ' | Heart & Lungs: ' + physicalData.heart_and_lungs : ''}" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Blood Type</label>
                                <input class="form-control" value="${safe(physicalData.blood_type)}" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small mb-1">Type of Donation</label>
                                <input class="form-control" value="${safe(physicalData.donation_type)}" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vital Signs Section -->
                    <div class="mb-3">
                        <h6 class="mb-2" style="color:#b22222; font-weight:700;">Vital Signs</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-danger">
                                    <tr>
                                        <th>Blood Pressure (BP)</th>
                                        <th>Weight</th>
                                        <th>Pulse</th>
                                        <th>Temperature</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input class="form-control form-control-sm" value="${safe(physicalData.blood_pressure)}" readonly></td>
                                        <td><input class="form-control form-control-sm" value="${safe(physicalData.body_weight)}" readonly></td>
                                        <td><input class="form-control form-control-sm" value="${safe(physicalData.pulse_rate)}" readonly></td>
                                        <td><input class="form-control form-control-sm" value="${safe(physicalData.body_temp)}" readonly></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Final Assessment Section -->
                    <div class="mb-3">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Fitness to Donate</label>
                                <input class="form-control" value="${safe(physicalData.remarks)}" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small mb-1">Final Remarks</label>
                                <input class="form-control" value="${safe(physicalData.disapproval_reason)}" readonly>
                            </div>
                        </div>
                    </div>
                `;
                
                modalContent.innerHTML = physicalHTML;
                
                // Store donor ID for the proceed button
                modalContent.setAttribute('data-donor-id', donorId);
            }
            
            // Function to proceed to medical history modal
            function proceedToMedicalHistoryModal() {
                // Hide the deferral status modal first
                deferralStatusModal.hide();
                
                // Show the medical history modal
                const medicalHistoryModal = new bootstrap.Modal(document.getElementById('medicalHistoryModal'));
                const modalContent = document.getElementById('medicalHistoryModalContent');
                
                // Reset modal content to loading state
                modalContent.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                // Show the modal
                medicalHistoryModal.show();
                
                // Load the medical history form content
                fetch('../../src/views/forms/medical-history-modal-content.php?donor_id=' + currentDonorId)
                    .then(response => response.text())
                    .then(data => {
                        modalContent.innerHTML = data;
                        
                        // Execute any script tags in the loaded content
                        const scripts = modalContent.querySelectorAll('script');
                        scripts.forEach(script => {
                            try {
                                const newScript = document.createElement('script');
                                if (script.type) newScript.type = script.type;
                                if (script.src) {
                                    newScript.src = script.src;
                                } else {
                                    newScript.text = script.textContent || '';
                                }
                                document.body.appendChild(newScript);
                            } catch (e) {
                                console.log('Script execution error:', e);
                            }
                        });
                        
                        // After loading content, generate the questions
                        generateMedicalHistoryQuestions();
                    })
                    .catch(error => {
                        console.error('Error loading medical history form:', error);
                        modalContent.innerHTML = '<div class="alert alert-danger"><h6>Error Loading Form</h6><p>Unable to load the medical history form. Please try again.</p><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>';
                    });
            }

            // Helper function to get proceed button
            function getProceedButton() {
                return document.getElementById('proceedToMedicalHistory');
            }
            
            // Bind proceed button event listener after DOM is ready
            setTimeout(() => {
                const proceedButton = getProceedButton();
                if (proceedButton && proceedButton.addEventListener) {
                    proceedButton.addEventListener('click', function() {
                        if (!currentDonorId) return;
                        const confirmModal = new bootstrap.Modal(document.getElementById('reviewConfirmModal'));
                        const proceedBtn = document.getElementById('reviewConfirmProceedBtn');
                        if (proceedBtn) {
                            proceedBtn.onclick = () => { confirmModal.hide(); openMedicalHistoryForCurrentDonor(); };
                        }
                        confirmModal.show();
                    });
                }
                
                // Bind physical examination modal proceed button
                const physicalProceedButton = document.getElementById('proceedToMedicalHistoryFromPhysical');
                if (physicalProceedButton && physicalProceedButton.addEventListener) {
                    physicalProceedButton.addEventListener('click', function() {
                        // Hide physical examination modal
                        const physicalModal = bootstrap.Modal.getInstance(document.getElementById('physicalExaminationModal'));
                        if (physicalModal) {
                            physicalModal.hide();
                        }
                        
                        // Proceed to medical history modal
                        proceedToMedicalHistoryModal();
                    });
                }
            }, 100);
            
            // Optimized search function with caching
            function performOptimizedSearch(searchTerm) {
                if (!searchTerm.trim()) {
                    // Show all rows if search is empty
                    originalRows.forEach(row => row.style.display = '');
                    return;
                }
                
                // Use cached search results for better performance
                const searchResults = originalRows.filter(row => {
                    const cells = row.getElementsByTagName('td');
                    if (cells.length === 0) return false;
                    
                    // Search in surname, first name, and donor type columns
                    const surname = (cells[2]?.textContent || '').toLowerCase();
                    const firstName = (cells[3]?.textContent || '').toLowerCase();
                    const donorType = (cells[5]?.textContent || '').toLowerCase();
                    
                    return surname.includes(searchTerm) || 
                           firstName.includes(searchTerm) || 
                           donorType.includes(searchTerm);
                });
                
                // Hide all rows first
                originalRows.forEach(row => row.style.display = 'none');
                
                // Show matching rows
                searchResults.forEach(row => row.style.display = '');
            }
            
            // Update placeholder based on selected category
            if (searchCategory && searchCategory.addEventListener) {
            searchCategory.addEventListener('change', function() {
                const category = this.value;
                let placeholder = 'Search by ';
                switch(category) {
                    case 'surname': placeholder += 'surname...'; break;
                    case 'firstname': placeholder += 'first name...'; break;
                    case 'age': placeholder += 'age...'; break;
                    default: placeholder = 'Search donors...';
                }
                    if (searchInput) {
                searchInput.placeholder = placeholder;
                    }
            });
            }

            // Handle Mark for Medical Review from main details modal
            // Do not bind a global handler; enabled per-row only for returning
        });



        // Function to generate medical history questions in the modal
        function generateMedicalHistoryQuestions() {
            console.log("Starting to generate medical history questions...");
            
            // Get data from the JSON script tag
            const modalDataScript = document.getElementById('modalData');
            if (!modalDataScript) {
                console.error("Modal data script not found");
                return;
            }
            
            let modalData;
            try {
                modalData = JSON.parse(modalDataScript.textContent);
            } catch (e) {
                console.error("Error parsing modal data:", e);
                return;
            }
            
            console.log("Modal data:", modalData);
            
            const modalMedicalHistoryData = modalData.medicalHistoryData;
            const modalDonorSex = modalData.donorSex;
            const modalUserRole = modalData.userRole;
            const modalIsMale = modalDonorSex === 'male';
            
            console.log("User role:", modalUserRole);
            console.log("Donor sex:", modalDonorSex);
            console.log("Is male:", modalIsMale);
            console.log("Medical history data:", modalMedicalHistoryData);
            
            // Only make fields required for reviewers (who can edit)
            const modalIsReviewer = modalUserRole === 'reviewer';
            const modalRequiredAttr = modalIsReviewer ? 'required' : '';
            
            // Define questions by step
            const questionsByStep = {
                1: [
                    { q: 1, text: "Do you feel well and healthy today?" },
                    { q: 2, text: "Have you ever been refused as a blood donor or told not to donate blood for any reasons?" },
                    { q: 3, text: "Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?" },
                    { q: 4, text: "Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?" },
                    { q: 5, text: "Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?" },
                    { q: 6, text: "In the last 3 DAYS have you taken aspirin?" },
                    { q: 7, text: "In the past 4 WEEKS have you taken any medications and/or vaccinations?" },
                    { q: 8, text: "In the past 3 MONTHS have you donated whole blood, platelets or plasma?" }
                ],
                2: [
                    { q: 9, text: "Been to any places in the Philippines or countries infected with ZIKA Virus?" },
                    { q: 10, text: "Had sexual contact with a person who was confirmed to have ZIKA Virus Infection?" },
                    { q: 11, text: "Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?" }
                ],
                3: [
                    { q: 12, text: "Received blood, blood products and/or had tissue/organ transplant or graft?" },
                    { q: 13, text: "Had surgical operation or dental extraction?" },
                    { q: 14, text: "Had a tattoo applied, ear and body piercing, acupuncture, needle stick Injury or accidental contact with blood?" },
                    { q: 15, text: "Had sexual contact with high risks individuals or in exchange for material or monetary gain?" },
                    { q: 16, text: "Engaged in unprotected, unsafe or casual sex?" },
                    { q: 17, text: "Had jaundice/hepatitis/personal contact with person who had hepatitis?" },
                    { q: 18, text: "Been incarcerated, Jailed or imprisoned?" },
                    { q: 19, text: "Spent time or have relatives in the United Kingdom or Europe?" }
                ],
                4: [
                    { q: 20, text: "Travelled or lived outside of your place of residence or outside the Philippines?" },
                    { q: 21, text: "Taken prohibited drugs (orally, by nose, or by injection)?" },
                    { q: 22, text: "Used clotting factor concentrates?" },
                    { q: 23, text: "Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?" },
                    { q: 24, text: "Had Malaria or Hepatitis in the past?" },
                    { q: 25, text: "Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?" }
                ],
                5: [
                    { q: 26, text: "Cancer, blood disease or bleeding disorder (haemophilia)?" },
                    { q: 27, text: "Heart disease/surgery, rheumatic fever or chest pains?" },
                    { q: 28, text: "Lung disease, tuberculosis or asthma?" },
                    { q: 29, text: "Kidney disease, thyroid disease, diabetes, epilepsy?" },
                    { q: 30, text: "Chicken pox and/or cold sores?" },
                    { q: 31, text: "Any other chronic medical condition or surgical operations?" },
                    { q: 32, text: "Have you recently had rash and/or fever? Was/were this/these also associated with arthralgia or arthritis or conjunctivitis?" }
                ],
                6: [
                    { q: 33, text: "Are you currently pregnant or have you ever been pregnant?" },
                    { q: 34, text: "When was your last childbirth?" },
                    { q: 35, text: "In the past 1 YEAR, did you have a miscarriage or abortion?" },
                    { q: 36, text: "Are you currently breastfeeding?" },
                    { q: 37, text: "When was your last menstrual period?" }
                ]
            };
            
            // Define remarks options based on question type
            const modalRemarksOptions = {
                1: ["None", "Feeling Unwell", "Fatigue", "Fever", "Other Health Issues"],
                2: ["None", "Low Hemoglobin", "Medical Condition", "Recent Surgery", "Other Refusal Reason"],
                3: ["None", "HIV Test", "Hepatitis Test", "Other Test Purpose"],
                4: ["None", "Understood", "Needs More Information"],
                5: ["None", "Beer", "Wine", "Liquor", "Multiple Types"],
                6: ["None", "Pain Relief", "Fever", "Other Medication Purpose"],
                7: ["None", "Antibiotics", "Vitamins", "Vaccines", "Other Medications"],
                8: ["None", "Red Cross Donation", "Hospital Donation", "Other Donation Type"],
                9: ["None", "Local Travel", "International Travel", "Specific Location"],
                10: ["None", "Direct Contact", "Indirect Contact", "Suspected Case"],
                11: ["None", "Partner Travel History", "Unknown Exposure", "Other Risk"],
                12: ["None", "Blood Transfusion", "Organ Transplant", "Other Procedure"],
                13: ["None", "Major Surgery", "Minor Surgery", "Dental Work"],
                14: ["None", "Tattoo", "Piercing", "Acupuncture", "Blood Exposure"],
                15: ["None", "High Risk Contact", "Multiple Partners", "Other Risk Factors"],
                16: ["None", "Unprotected Sex", "Casual Contact", "Other Risk Behavior"],
                17: ["None", "Personal History", "Family Contact", "Other Exposure"],
                18: ["None", "Short Term", "Long Term", "Other Details"],
                19: ["None", "UK Stay", "Europe Stay", "Duration of Stay"],
                20: ["None", "Local Travel", "International Travel", "Duration"],
                21: ["None", "Recreational", "Medical", "Other Usage"],
                22: ["None", "Treatment History", "Current Use", "Other Details"],
                23: ["None", "HIV", "Hepatitis", "Syphilis", "Malaria"],
                24: ["None", "Past Infection", "Treatment History", "Other Details"],
                25: ["None", "Current Infection", "Past Treatment", "Other Details"],
                26: ["None", "Cancer Type", "Blood Disease", "Bleeding Disorder"],
                27: ["None", "Heart Disease", "Surgery History", "Current Treatment"],
                28: ["None", "Active TB", "Asthma", "Other Respiratory Issues"],
                29: ["None", "Kidney Disease", "Thyroid Issue", "Diabetes", "Epilepsy"],
                30: ["None", "Recent Infection", "Past Infection", "Other Details"],
                31: ["None", "Condition Type", "Treatment Status", "Other Details"],
                32: ["None", "Recent Fever", "Rash", "Joint Pain", "Eye Issues"],
                33: ["None", "Current Pregnancy", "Past Pregnancy", "Other Details"],
                34: ["None", "Less than 6 months", "6-12 months ago", "More than 1 year ago"],
                35: ["None", "Less than 3 months ago", "3-6 months ago", "6-12 months ago"],
                36: ["None", "Currently Breastfeeding", "Recently Stopped", "Other"],
                37: ["None", "Within last week", "1-2 weeks ago", "2-4 weeks ago", "More than 1 month ago"]
            };
            
            // Get the field name based on the data structure
            const getModalFieldName = (count) => {
                const fields = {
                    1: 'feels_well', 2: 'previously_refused', 3: 'testing_purpose_only', 4: 'understands_transmission_risk',
                    5: 'recent_alcohol_consumption', 6: 'recent_aspirin', 7: 'recent_medication', 8: 'recent_donation',
                    9: 'zika_travel', 10: 'zika_contact', 11: 'zika_sexual_contact', 12: 'blood_transfusion',
                    13: 'surgery_dental', 14: 'tattoo_piercing', 15: 'risky_sexual_contact', 16: 'unsafe_sex',
                    17: 'hepatitis_contact', 18: 'imprisonment', 19: 'uk_europe_stay', 20: 'foreign_travel',
                    21: 'drug_use', 22: 'clotting_factor', 23: 'positive_disease_test', 24: 'malaria_history',
                    25: 'std_history', 26: 'cancer_blood_disease', 27: 'heart_disease', 28: 'lung_disease',
                    29: 'kidney_disease', 30: 'chicken_pox', 31: 'chronic_illness', 32: 'recent_fever',
                    33: 'pregnancy_history', 34: 'last_childbirth', 35: 'recent_miscarriage', 36: 'breastfeeding',
                    37: 'last_menstruation'
                };
                return fields[count];
            };
            
                         // Generate questions for each step
             for (let step = 1; step <= 6; step++) {
                 // Skip step 6 for male donors
                 if (step === 6 && modalIsMale) {
                     continue;
                 }
                 
                 const stepContainer = document.querySelector(`[data-step-container="${step}"]`);
                 if (!stepContainer) {
                     console.error(`Step container ${step} not found`);
                     continue;
                 }
                 
                 const stepQuestions = questionsByStep[step] || [];
                 
                 stepQuestions.forEach(questionData => {
                     const fieldName = getModalFieldName(questionData.q);
                     const value = modalMedicalHistoryData ? modalMedicalHistoryData[fieldName] : null;
                     const remarks = modalMedicalHistoryData ? modalMedicalHistoryData[fieldName + '_remarks'] : null;
                     
                     // Create a form group for each question
                     const questionRow = document.createElement('div');
                     questionRow.className = 'form-group';
                     questionRow.innerHTML = `
                         <div class="question-number">${questionData.q}</div>
                         <div class="question-text">${questionData.text}</div>
                         <div class="radio-cell">
                             <label class="radio-container">
                                 <input type="radio" name="q${questionData.q}" value="Yes" ${value === true ? 'checked' : ''} ${modalRequiredAttr}>
                                 <span class="checkmark"></span>
                             </label>
                         </div>
                         <div class="radio-cell">
                             <label class="radio-container">
                                 <input type="radio" name="q${questionData.q}" value="No" ${value === false ? 'checked' : ''} ${modalRequiredAttr}>
                                 <span class="checkmark"></span>
                             </label>
                         </div>
                         <div class="remarks-cell">
                             <select class="remarks-input" name="q${questionData.q}_remarks" ${modalRequiredAttr}>
                                 ${modalRemarksOptions[questionData.q].map(option => 
                                     `<option value="${option}" ${remarks === option ? 'selected' : ''}>${option}</option>`
                                 ).join('')}
                             </select>
                         </div>
                     `;
                     
                     stepContainer.appendChild(questionRow);
                 });
             }
            
            // Initialize step navigation
            initializeModalStepNavigation(modalUserRole, modalIsMale);
            
            // Make form fields read-only for interviewers and physicians (but allow Edit button to override)
            // Don't make readonly for donors with needs_review=true or current status "Medical"
            const currentDonorStatus = window.currentDonorStage || 'Medical';
            const currentDonorId = window.currentDonorId;
            const hasNeedsReview = currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review;
            
            if ((modalUserRole === 'interviewer' || modalUserRole === 'physician') && currentDonorStatus !== 'Medical' && !hasNeedsReview) {
                setTimeout(() => {
                    const radioButtons = document.querySelectorAll('#modalMedicalHistoryForm input[type="radio"]');
                    const selectFields = document.querySelectorAll('#modalMedicalHistoryForm select.remarks-input');
                    
                    radioButtons.forEach(radio => {
                        radio.disabled = true;
                        radio.setAttribute('data-originally-disabled', 'true');
                    });
                    
                    selectFields.forEach(select => {
                        select.disabled = true;
                        select.setAttribute('data-originally-disabled', 'true');
                    });
                    
                    console.log("Made form fields read-only for role:", modalUserRole);
                    
                    // Initialize edit functionality after fields are made read-only
                    console.log('🔄 Calling initializeEditFunctionality after making fields read-only...');
                    
                    // Wait a bit more to ensure the modal content JavaScript has executed
                    setTimeout(() => {
                        if (window.initializeEditFunctionality) {
                            console.log('✅ Found initializeEditFunctionality, calling it...');
                            window.initializeEditFunctionality();
                        } else {
                            console.log('❌ window.initializeEditFunctionality is still not available');
                            console.log('Available window functions:', Object.keys(window).filter(key => key.includes('initialize')));
                        }
                    }, 50);
                }, 100);
            } else {
                // For reviewers, initialize edit functionality immediately
                console.log('🔄 Calling initializeEditFunctionality for reviewer role...');
                setTimeout(() => {
                    if (window.initializeEditFunctionality) {
                        console.log('✅ Found initializeEditFunctionality for reviewer, calling it...');
                        window.initializeEditFunctionality();
                    } else {
                        console.log('❌ window.initializeEditFunctionality is not available for reviewer');
                    }
                }, 50);
            }
        }
        
        // Initialize step navigation for the modal
        function initializeModalStepNavigation(userRole, isMale) {
            let currentStep = 1;
            const totalSteps = isMale ? 5 : 6;
            
            const stepIndicators = document.querySelectorAll('#modalStepIndicators .step');
            const stepConnectors = document.querySelectorAll('#modalStepIndicators .step-connector');
            const formSteps = document.querySelectorAll('#modalMedicalHistoryForm .form-step');
            const prevButton = document.getElementById('modalPrevButton');
            const nextButton = document.getElementById('modalNextButton');
            const errorMessage = document.getElementById('modalValidationError');
            
            // Hide step 6 for male donors
            if (isMale) {
                const step6 = document.getElementById('modalStep6');
                const line56 = document.getElementById('modalLine5-6');
                if (step6) step6.style.display = 'none';
                if (line56) line56.style.display = 'none';
            }
            
            function updateStepDisplay() {
                // Hide all steps
                formSteps.forEach(step => {
                    step.classList.remove('active');
                });
                
                // Show current step
                const activeStep = document.querySelector(`#modalMedicalHistoryForm .form-step[data-step="${currentStep}"]`);
                if (activeStep) {
                    activeStep.classList.add('active');
                }
                
                // Update step indicators
                stepIndicators.forEach(indicator => {
                    const step = parseInt(indicator.getAttribute('data-step'));
                    
                    if (step < currentStep) {
                        indicator.classList.add('completed');
                        indicator.classList.add('active');
                    } else if (step === currentStep) {
                        indicator.classList.add('active');
                        indicator.classList.remove('completed');
                    } else {
                        indicator.classList.remove('active');
                        indicator.classList.remove('completed');
                    }
                });
                
                // Update step connectors
                stepConnectors.forEach((connector, index) => {
                    if (index + 1 < currentStep) {
                        connector.classList.add('active');
                    } else {
                        connector.classList.remove('active');
                    }
                });
                
                // Update buttons
                if (currentStep === 1) {
                    prevButton.style.display = 'none';
                } else {
                    prevButton.style.display = 'block';
                }
                
                if (currentStep === totalSteps) {
                    if (userRole === 'reviewer') {
                        nextButton.innerHTML = 'DECLINE';
                        nextButton.onclick = () => submitModalForm('decline');
                        
                        // Add approve button
                        if (!document.getElementById('modalApproveButton')) {
                            const approveBtn = document.createElement('button');
                            approveBtn.className = 'next-button';
                            approveBtn.innerHTML = 'APPROVE';
                            approveBtn.id = 'modalApproveButton';
                            approveBtn.onclick = () => submitModalForm('approve');
                            nextButton.parentNode.appendChild(approveBtn);
                        }
                    } else {
                        nextButton.innerHTML = 'NEXT';
                        nextButton.onclick = () => submitModalForm('next');
                    }
                } else {
                    nextButton.innerHTML = 'Next →';
                    nextButton.onclick = () => {
                        if (validateCurrentModalStep()) {
                            currentStep++;
                            updateStepDisplay();
                            errorMessage.style.display = 'none';
                        }
                    };
                    
                    // Remove approve button if it exists
                    const approveBtn = document.getElementById('modalApproveButton');
                    if (approveBtn) {
                        approveBtn.remove();
                    }
                }
            }
            
            function validateCurrentModalStep() {
                const currentStepElement = document.querySelector(`#modalMedicalHistoryForm .form-step[data-step="${currentStep}"]`);
                if (!currentStepElement) return false;
                
                const radioGroups = {};
                const radios = currentStepElement.querySelectorAll('input[type="radio"]');
                
                radios.forEach(radio => {
                    radioGroups[radio.name] = true;
                });
                
                let allAnswered = true;
                for (const groupName in radioGroups) {
                    const answered = document.querySelector(`input[name="${groupName}"]:checked`) !== null;
                    if (!answered) {
                        allAnswered = false;
                        break;
                    }
                }
                
                if (!allAnswered) {
                    errorMessage.style.display = 'block';
                    errorMessage.textContent = 'Please answer all questions before proceeding to the next step.';
                    return false;
                }
                
                return true;
            }
            
            // Bind event handlers
            prevButton.addEventListener('click', () => {
                if (currentStep > 1) {
                    currentStep--;
                    updateStepDisplay();
                    errorMessage.style.display = 'none';
                }
            });
            
            // Initialize display
            updateStepDisplay();
        }

        // Function to handle modal form submission
        function submitModalForm(action) {
            let message = '';
            if (action === 'approve') {
                message = 'Are you sure you want to approve this donor and proceed to the declaration form?';
            } else if (action === 'decline') {
                message = 'Are you sure you want to decline this donor?';
            } else if (action === 'next') {
                message = 'Do you want to proceed to the declaration form?';
            }
            
            // Use custom confirmation instead of browser confirm
            if (window.customConfirm) {
                window.customConfirm(message, function() {
                    processFormSubmission(action);
                });
            } else {
                // Fallback to browser confirm if custom confirm is not available
                if (confirm(message)) {
                    processFormSubmission(action);
                }
            }
        }
        
        // Separate function to handle the actual form submission
        function processFormSubmission(action) {
                document.getElementById('modalSelectedAction').value = action;
                
                // Submit the form via AJAX
                const form = document.getElementById('modalMedicalHistoryForm');
                const formData = new FormData(form);
                
                fetch('../../src/views/forms/medical-history-process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (action === 'next' || action === 'approve') {
                            // Close medical history modal and open screening form modal
                            const medicalModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                            medicalModal.hide();
                            
                            // Get the current donor_id
                            const donorIdInput = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
                            const donorId = donorIdInput ? donorIdInput.value : null;
                            
                            // If Returning (Medical), ask to mark screening for review
                            try {
                                if (donorId && window.currentStage === 'medical_review' && (window.modalContextType === 'returning' || document.querySelector(`tr[data-donor-id="${donorId}"]`)?.getAttribute('data-donor-type')?.toLowerCase().includes('returning'))) {
                                    const csm = new bootstrap.Modal(document.getElementById('confirmScreeningMarkModal'));
                                    const btn = document.getElementById('confirmScreeningMarkBtn');
                                    btn.onclick = () => {
                                        fetch('../../assets/php_func/update_screening_needs_review.php', {
                                            method: 'POST',
                                            headers: { 'Accept': 'application/json' },
                                            body: new URLSearchParams({ donor_id: donorId })
                                        }).then(r => r.json()).finally(() => { csm.hide(); });
                                    };
                                    csm.show();
                                }
                            } catch (e) {}
                            
                            if (donorId) {
                                // Show screening form modal instead of declaration form
                                showScreeningFormModal(donorId);
                            } else {
                                // Use custom modal instead of browser alert
                                if (window.customConfirm) {
                                    window.customConfirm('Error: Donor ID not found', function() {
                                        // Just close the modal, no additional action needed
                                    });
                                } else {
                                    // Use custom modal instead of browser alert
                                    if (window.customConfirm) {
                                        window.customConfirm('Error: Donor ID not found', function() {
                                            // Just close the modal, no additional action needed
                                        });
                            } else {
                                alert('Error: Donor ID not found');
                                    }
                                }
                            }
                        } else if (action === 'decline') {
                            // Close modal and refresh the main page for decline only
                            const modal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                            modal.hide();
                            window.location.reload();
                        }
                    } else {
                        // Use custom modal instead of browser alert
                        if (window.customConfirm) {
                            window.customConfirm('Error: ' + (data.message || 'Unknown error occurred'), function() {
                                // Just close the modal, no additional action needed
                            });
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error occurred'));
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Use custom modal instead of browser alert
                    if (window.customConfirm) {
                        window.customConfirm('An error occurred while processing the form.', function() {
                            // Just close the modal, no additional action needed
                        });
                    } else {
                    alert('An error occurred while processing the form.');
                    }
                });
        }
        
        // Function to show screening form modal
        function showScreeningFormModal(donorId) {
            console.log('Showing screening form modal for donor ID:', donorId);
            
            // Set donor data for the screening form
            window.currentDonorData = { donor_id: donorId };
            
            // Show the screening form modal
            const screeningModal = new bootstrap.Modal(document.getElementById('screeningFormModal'));
            screeningModal.show();
            
            // Set the donor ID in the screening form
            const donorIdInput = document.querySelector('#screeningFormModal input[name="donor_id"]');
            if (donorIdInput) {
                donorIdInput.value = donorId;
            }
            
            // Initialize the screening form
            if (window.initializeScreeningForm) {
                window.initializeScreeningForm(donorId);
            }
        }
        
        // Function to show declaration form modal
        window.showDeclarationFormModal = function(donorId) {
            console.log('Showing declaration form modal for donor ID:', donorId);
            
            const declarationModal = new bootstrap.Modal(document.getElementById('declarationFormModal'));
            const modalContent = document.getElementById('declarationFormModalContent');
            
            // Reset modal content to loading state
            modalContent.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="min-height: 200px;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 mb-0">Loading Declaration Form...</p>
                    </div>
                </div>`;
            
            // Show the modal
            declarationModal.show();
            
            // Load the declaration form content
            fetch('../../src/views/forms/declaration-form-modal-content.php?donor_id=' + donorId)
                .then(response => {
                    console.log('Declaration form response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    console.log('Declaration form content loaded successfully');
                    modalContent.innerHTML = data;
                    
                    // Ensure print function is available globally
                    window.printDeclaration = function() {
                        console.log('Print function called');
                        const printWindow = window.open('', '_blank');
                        const content = document.querySelector('.declaration-header').outerHTML + 
                                       document.querySelector('.donor-info').outerHTML + 
                                       document.querySelector('.declaration-content').outerHTML + 
                                       document.querySelector('.signature-section').outerHTML;
                        
                        printWindow.document.write(`
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <title>Declaration Form - Philippine Red Cross</title>
                                <style>
                                    body { 
                                        font-family: Arial, sans-serif; 
                                        padding: 20px; 
                                        line-height: 1.5;
                                    }
                                    .declaration-header { 
                                        text-align: center; 
                                        margin-bottom: 30px; 
                                        border-bottom: 2px solid #9c0000;
                                        padding-bottom: 20px;
                                    }
                                    .declaration-header h2, .declaration-header h3 { 
                                        color: #9c0000; 
                                        margin: 5px 0; 
                                        font-weight: bold;
                                    }
                                    .donor-info { 
                                        background-color: #f8f9fa; 
                                        padding: 20px; 
                                        margin: 20px 0; 
                                        border: 1px solid #ddd; 
                                        border-radius: 8px;
                                    }
                                    .donor-info-row { 
                                        display: flex; 
                                        margin-bottom: 15px; 
                                        gap: 20px; 
                                        flex-wrap: wrap;
                                    }
                                    .donor-info-item { 
                                        flex: 1; 
                                        min-width: 200px;
                                    }
                                    .donor-info-label { 
                                        font-weight: bold; 
                                        font-size: 14px; 
                                        color: #555; 
                                        margin-bottom: 5px;
                                    }
                                    .donor-info-value { 
                                        font-size: 16px; 
                                        color: #333; 
                                    }
                                    .declaration-content { 
                                        line-height: 1.8; 
                                        margin: 30px 0; 
                                        text-align: justify;
                                    }
                                    .declaration-content p { 
                                        margin-bottom: 20px; 
                                    }
                                    .bold { 
                                        font-weight: bold; 
                                        color: #9c0000; 
                                    }
                                    .signature-section { 
                                        margin-top: 40px; 
                                        display: flex; 
                                        justify-content: space-between; 
                                        page-break-inside: avoid;
                                    }
                                    .signature-box { 
                                        text-align: center; 
                                        padding: 15px 0; 
                                        border-top: 2px solid #333; 
                                        width: 250px; 
                                        font-weight: 500;
                                    }
                                    @media print {
                                        body { margin: 0; }
                                        .declaration-header { page-break-after: avoid; }
                                        .signature-section { page-break-before: avoid; }
                                    }
                                </style>
                            </head>
                            <body>${content}</body>
                            </html>
                        `);
                        printWindow.document.close();
                        printWindow.focus();
                        setTimeout(() => {
                            printWindow.print();
                        }, 500);
                    };
                    
                    // Ensure submit function is available globally
                    window.submitDeclarationForm = function() {
                        console.log('Submit declaration form called');
                        
                        // Close declaration form modal
                        const declarationModal = bootstrap.Modal.getInstance(document.getElementById('declarationFormModal'));
                        if (declarationModal) {
                            declarationModal.hide();
                        }
                        
                        // Show data processing confirmation modal
                        const dataProcessingModal = new bootstrap.Modal(document.getElementById('dataProcessingConfirmModal'));
                        dataProcessingModal.show();
                        
                        // Handle confirmation button click
                        document.getElementById('confirmProcessingBtn').onclick = function() {
                            const selectedOutcome = document.querySelector('input[name="screeningOutcome"]:checked').value;
                            console.log('Selected outcome:', selectedOutcome);
                            
                            // Close confirmation modal
                            dataProcessingModal.hide();
                            
                            // Process the declaration form
                            const form = document.getElementById('modalDeclarationForm');
                            if (!form) {
                                // Use custom modal instead of browser alert
                                if (window.customConfirm) {
                                    window.customConfirm('Form not found. Please try again.', function() {
                                        // Just close the modal, no additional action needed
                                    });
                                } else {
                                alert('Form not found. Please try again.');
                                }
                                return;
                            }
                            
                            document.getElementById('modalDeclarationAction').value = 'complete';
                            
                            // Submit the form via AJAX
                            const formData = new FormData(form);
                            
                            fetch('../../src/views/forms/declaration-form-process.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Show success message based on outcome
                                    if (selectedOutcome === 'approve') {
                                        // Use custom modal instead of browser alert
                                        if (window.customConfirm) {
                                            // Add a longer delay to ensure the modal shows and DOM is ready
                                            setTimeout(() => {
                                                window.customConfirm('Donor approved for donation and forwarded to physician for physical examination. Proceed to print the donor declaration form and hand it to the donor.', function() {
                                                    // Force complete page reload after user clicks OK
                                                    window.location.href = window.location.href;
                                                });
                                            }, 500);
                                        } else {
                                            console.log('Custom modal not available, using fallback alert');
                                            alert('Donor approved for donation and forwarded to physician for physical examination. Proceed to print the donor declaration form and hand it to the donor.');
                                    // Force complete page reload
                                    window.location.href = window.location.href;
                                        }
                                    } else {
                                        // Use custom modal instead of browser alert
                                        if (window.customConfirm) {
                                            window.customConfirm('Donor registration saved as draft successfully!', function() {
                                                // Force complete page reload after user clicks OK
                                                window.location.href = window.location.href;
                                            });
                                        } else {
                                            alert('Donor registration saved as draft successfully!');
                                            // Force complete page reload
                                            window.location.href = window.location.href;
                                        }
                                    }
                                    console.log('Registration complete, reloading page...');
                                    
                                    // Remove the automatic reload since we handle it in the modal callback
                                    // window.location.href = window.location.href;
                                } else {
                                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                // Use custom modal instead of browser alert
                                if (window.customConfirm) {
                                    window.customConfirm('An error occurred while processing the form.', function() {
                                        // Just close the modal, no additional action needed
                            });
                                } else {
                                    alert('An error occurred while processing the form.');
                        }
                            });
                        };
                    };
                })
                .catch(error => {
                    console.error('Error loading declaration form:', error);
                    modalContent.innerHTML = `
                        <div class="alert alert-danger text-center" style="margin: 50px 20px;">
                            <h5 class="alert-heading">
                                <i class="fas fa-exclamation-triangle"></i> Error Loading Form
                            </h5>
                            <p>Unable to load the declaration form. Please try again.</p>
                            <hr>
                            <p class="mb-0">Error details: ' + error.message + '</p>
                            <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>`;
                });
        }
        
        // Add loading functionality for data processing
        function showProcessingModal(message = 'Processing medical history data...') {
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            const loadingText = document.querySelector('#loadingModal p');
            if (loadingText) {
                loadingText.textContent = message;
            }
            loadingModal.show();
        }
        
        function hideProcessingModal() {
            const loadingModal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
            if (loadingModal) {
                loadingModal.hide();
            }
        }
        
        // Make functions globally available
        window.showProcessingModal = showProcessingModal;
        window.hideProcessingModal = hideProcessingModal;
        
        // Show loading when medical history forms are submitted
        document.addEventListener('submit', function(e) {
            if (e.target && (e.target.classList.contains('medical-form') || e.target.id.includes('medical'))) {
                showProcessingModal('Submitting medical history data...');
            }
        });
        
        // Show loading for medical history AJAX calls
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const url = args[0];
            if (typeof url === 'string' && (url.includes('medical') || url.includes('approval'))) {
                showProcessingModal('Processing medical data...');
            }
            return originalFetch.apply(this, args).finally(() => {
                setTimeout(hideProcessingModal, 500);
            });
        };
        
        // Custom confirmation function to replace browser confirm
        function customConfirm(message, onConfirm) {
            // Create a simple modal without Bootstrap
            const modalHTML = `
                <div id="simpleCustomModal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: white;
                        padding: 30px;
                        border-radius: 10px;
                        max-width: 500px;
                        width: 90%;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    ">
                        <div style="
                            background: #9c0000;
                            color: white;
                            padding: 15px 20px;
                            margin: -30px -30px 20px -30px;
                            border-radius: 10px 10px 0 0;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        ">
                            <h5 style="margin: 0;">
                                <i class="fas fa-question-circle me-2"></i>
                                Confirm Action
                            </h5>
                            <button onclick="closeSimpleModal()" style="
                                background: none;
                                border: none;
                                color: white;
                                font-size: 20px;
                                cursor: pointer;
                            ">&times;</button>
                        </div>
                        <p style="font-size: 16px; line-height: 1.5; margin-bottom: 25px;">${message}</p>
                        <div style="text-align: right;">
                            <button onclick="closeSimpleModal()" style="
                                background: #6c757d;
                                color: white;
                                border: none;
                                padding: 10px 25px;
                                border-radius: 6px;
                                margin-right: 10px;
                                cursor: pointer;
                            ">Cancel</button>
                            <button onclick="confirmSimpleModal()" style="
                                background: #9c0000;
                                color: white;
                                border: none;
                                padding: 10px 25px;
                                border-radius: 6px;
                                cursor: pointer;
                            ">Yes, Proceed</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing modal
            const existingModal = document.getElementById('simpleCustomModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Set up confirmation function
            window.confirmSimpleModal = function() {
                closeSimpleModal();
                if (onConfirm) onConfirm();
            };
            
            window.closeSimpleModal = function() {
                const modal = document.getElementById('simpleCustomModal');
                if (modal) {
                    modal.remove();
                }
            };
        }

        // Make customConfirm globally available
        window.customConfirm = customConfirm;
        
        

    </script>

    <!-- Custom Confirmation Modal -->
    <div class="modal fade" id="customConfirmModal" tabindex="-1" aria-labelledby="customConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: #9c0000; color: white;">
                    <h5 class="modal-title" id="customConfirmModalLabel">
                        <i class="fas fa-question-circle me-2"></i>
                        Confirm Action
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="customConfirmMessage">Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="customConfirmYes">Yes, Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Custom confirmation modal styling */
        #customConfirmModal .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        #customConfirmModal .modal-header {
            background: #9c0000; /* Red Cross themed */
            color: white;
            border-radius: 10px 10px 0 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        #customConfirmModal .modal-body {
            padding: 25px;
            font-size: 16px;
            line-height: 1.5;
        }
        
        #customConfirmModal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 20px 25px;
        }
        
        #customConfirmModal .btn {
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        #customConfirmModal .btn-primary {
            background-color: #9c0000;
            border-color: #9c0000;
        }
        
        #customConfirmModal .btn-primary:hover {
            background-color: #8b0000;
            border-color: #8b0000;
            transform: translateY(-1px);
        }
        
        /* Ensure modal appears above everything */
        #customConfirmModal {
            z-index: 99999 !important;
        }
        
        #customConfirmModal .modal-backdrop {
            z-index: 99998 !important;
        }
        
        /* Force modal to be visible */
        #customConfirmModal.show {
            display: block !important;
            opacity: 1 !important;
        }
        
        /* Ensure backdrop is visible */
        .modal-backdrop.show {
            opacity: 0.5 !important;
        }
    </style>

    <!-- Include Screening Form Modal -->
    <?php include '../../src/views/forms/staff_donor_initial_screening_form_modal.php'; ?>

    <!-- Physical Examination Review Modal -->
    <div class="modal fade" id="physicalExaminationModal" tabindex="-1" aria-labelledby="physicalExaminationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title" id="physicalExaminationModalLabel">
                        <i class="fas fa-stethoscope me-2"></i>
                        Physical Examination Results
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="physicalExaminationModalContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="proceedToMedicalHistoryFromPhysical">Proceed to Medical History</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>