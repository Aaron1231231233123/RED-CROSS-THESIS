<?php
// Prevent any output before headers
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// Check if user is logged in before including other files
if (!isset($_SESSION['user_id'])) {
    header("Location: /RED-CROSS-THESIS/public/login.php");
    exit();
}

require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Debug logging to help troubleshoot
error_log("Starting dashboard-staff-physical-submission.php");

/**
 * Unified CURL API call function to reduce duplication
 * @param string $endpoint - The API endpoint to call
 * @param array $selectFields - Fields to select (optional)
 * @param array $filters - Additional filters (optional)
 * @return array - Decoded JSON response or empty array on error
 */
function makeSupabaseApiCall($endpoint, $selectFields = [], $filters = []) {
    try {
        $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
        
        // Build query parameters
        $params = [];
        if (!empty($selectFields)) {
            $params[] = 'select=' . implode(',', $selectFields);
        }
        
        // Add filters
        foreach ($filters as $key => $value) {
            $params[] = $key . '=' . urlencode($value);
        }
        
        if (!empty($params)) {
            $url .= '?' . implode('&', $params);
        }
        
        $ch = curl_init($url);
        $headers = [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            error_log("CURL error in API call to: " . $endpoint);
            return [];
        }
        
        return json_decode($response, true) ?: [];
        
    } catch (Exception $e) {
        error_log("Error in API call to " . $endpoint . ": " . $e->getMessage());
        return [];
    }
}

// 1. Get all screening records using unified API function
$all_screenings = makeSupabaseApiCall(
    'screening_form',
    ['screening_id', 'created_at', 'blood_type', 'donation_type', 'donor_form_id', 'disapproval_reason', 'needs_review'],
    ['order' => 'created_at.desc']
);

// 2b. Get eligibility records to classify donors as New or Returning
$eligibility_records = makeSupabaseApiCall('eligibility', ['donor_id']);
$eligibility_by_donor = [];
foreach ($eligibility_records as $er) {
    if (isset($er['donor_id'])) {
        $eligibility_by_donor[(int)$er['donor_id']] = true;
    }
}

// 2c. Get medical history approval map (for UI read-only rules and action swapping)
$medical_history_rows = makeSupabaseApiCall(
    'medical_history',
    ['donor_id', 'medical_approval', 'needs_review']
);
$medical_by_donor = [];
foreach ($medical_history_rows as $mh) {
    if (!isset($mh['donor_id'])) { continue; }
    $did = (string)$mh['donor_id'];
    $medical_by_donor[$did] = [
        'medical_approval' => isset($mh['medical_approval']) ? $mh['medical_approval'] : null,
        'needs_review' => isset($mh['needs_review']) ? (
            ($mh['needs_review'] === true) || ($mh['needs_review'] === 1) || ($mh['needs_review'] === '1') ||
            (is_string($mh['needs_review']) && in_array(strtolower(trim($mh['needs_review'])), ['true','t','yes','y'], true))
        ) : false,
    ];
}

// 2. Get all physical examination records with full details
$physical_exams = makeSupabaseApiCall(
    'physical_examination',
    ['physical_exam_id', 'screening_id', 'donor_id', 'remarks', 'disapproval_reason', 'gen_appearance', 'heart_and_lungs', 'skin', 'reason', 'blood_pressure', 'pulse_rate', 'body_temp', 'blood_bag_type', 'created_at', 'updated_at', 'needs_review', 'physician', 'status'],
    ['order' => 'created_at.desc']
);

// Create arrays to organize physical exams by status and collect all donor IDs
$existing_physical_exam_donor_ids = [];
$pending_physical_exam_donor_ids = [];
$physical_exam_data = []; // Store full physical exam data by donor_id
$all_donor_ids = []; // Collect all donor IDs for batch fetching

foreach ($physical_exams as $exam) {
    if (isset($exam['donor_id'])) {
        $donor_id = $exam['donor_id'];
        $physical_exam_data[$donor_id] = $exam; // Store full exam data
        $all_donor_ids[] = $donor_id; // Collect donor ID for batch fetch
        
        // Check if this physical exam has "pending" remarks
        if (isset($exam['remarks']) && strtolower($exam['remarks']) === 'pending') {
            $pending_physical_exam_donor_ids[] = $donor_id;
        } else {
            $existing_physical_exam_donor_ids[] = $donor_id;
        }
    }
}

// Also collect donor IDs from screening records
foreach ($all_screenings as $screening) {
    if (isset($screening['donor_form_id'])) {
        $all_donor_ids[] = $screening['donor_form_id'];
    }
}

// Remove duplicates and batch fetch all donor data
$all_donor_ids = array_unique($all_donor_ids);
$donor_data_cache = [];

if (!empty($all_donor_ids)) {
    // Batch fetch donor data using IN clause
    $donor_ids_str = implode(',', $all_donor_ids);
    $donors_data = makeSupabaseApiCall(
        'donor_form',
        ['donor_id', 'surname', 'first_name', 'middle_name'],
        ['donor_id' => 'in.(' . $donor_ids_str . ')']
    );
    
    // Create a cache for quick donor lookup
    foreach ($donors_data as $donor) {
        if (isset($donor['donor_id'])) {
            $donor_data_cache[$donor['donor_id']] = $donor;
        }
    }
}

// 3. Process all screenings and physical exams
$all_records = [];
$skipped_count = 0;

// First, process all physical examination records
foreach ($physical_exams as $exam) {
    if (isset($exam['donor_id'])) {
        $donor_id = $exam['donor_id'];
        
        // Get donor data from cache
        $donor_data = isset($donor_data_cache[$donor_id]) ? $donor_data_cache[$donor_id] : [
            'surname' => 'Unknown',
            'first_name' => 'Unknown',
            'middle_name' => ''
        ];
        
        // Create a record for this physical exam
        $record = [
            'type' => 'physical_exam',
            'physical_exam_id' => $exam['physical_exam_id'],
            'donor_id' => $donor_id,
            'created_at' => $exam['created_at'],
            'updated_at' => $exam['updated_at'],
            'physical_exam' => $exam,
            'donor_form' => $donor_data,
            'has_pending_exam' => (isset($exam['remarks']) && strtolower($exam['remarks']) === 'pending'),
            'needs_review' => isset($exam['needs_review']) ? (
                ($exam['needs_review'] === true) || ($exam['needs_review'] === 1) || ($exam['needs_review'] === '1') ||
                (is_string($exam['needs_review']) && in_array(strtolower(trim($exam['needs_review'])), ['true','t','yes','y'], true))
            ) : false,
            'donor_type' => isset($eligibility_by_donor[$donor_id]) ? 'Returning' : 'New',
            'stage_label' => 'Physical'
        ];
        
        $all_records[] = $record;
    }
}

// Then, process screening records that don't have physical exams yet
foreach ($all_screenings as $screening) {
    // Skip if not valid array data
    if (!is_array($screening)) {
        continue;
    }
    
    // Skip records that have disapproval reasons
    if (!empty($screening['disapproval_reason'])) {
        $skipped_count++;
        continue;
    }
    
    // Get donor information for this screening record
    if (isset($screening['donor_form_id'])) {
        $donor_id = $screening['donor_form_id'];
        
        // Skip if this donor already has a physical exam (we already processed it above)
        if (in_array($donor_id, $existing_physical_exam_donor_ids) || in_array($donor_id, $pending_physical_exam_donor_ids)) {
            $skipped_count++;
            continue;
        }
        
        // Get donor data from cache
        $donor_data = isset($donor_data_cache[$donor_id]) ? $donor_data_cache[$donor_id] : [
            'surname' => 'Unknown',
            'first_name' => 'Unknown',
            'middle_name' => ''
        ];
        
        // Create a record for this screening
        $record = [
            'type' => 'screening',
            'screening_id' => $screening['screening_id'],
            'donor_id' => $donor_id,
            'created_at' => $screening['created_at'],
            'screening_data' => $screening,
            'donor_form' => $donor_data,
            'has_pending_exam' => false,
            'needs_review' => isset($screening['needs_review']) ? (
                ($screening['needs_review'] === true) || ($screening['needs_review'] === 1) || ($screening['needs_review'] === '1') ||
                (is_string($screening['needs_review']) && in_array(strtolower(trim($screening['needs_review'])), ['true','t','yes','y'], true))
            ) : false,
            'donor_type' => isset($eligibility_by_donor[$donor_id]) ? 'Returning' : 'New',
            'stage_label' => 'Physical'
        ];
        
        $all_records[] = $record;
    }
}

// Performance optimization complete

// Count stats for dashboard cards
$pending_physical_exams_count = 0; // Pending or needs_review items
$active_physical_exams_count = 0; // Completed physical exams
$todays_summary_count = 0; // Today's records
$new_count = 0; // New donors (by eligibility absence)
$returning_count = 0; // Returning donors (by eligibility presence)

$today = date('Y-m-d');

foreach ($all_records as $record) {
    // Count pending (screenings without physical exams) or needs_review items
    $is_pending = ($record['type'] === 'screening') ||
                  ($record['type'] === 'physical_exam' && isset($record['physical_exam']['remarks']) && strtolower($record['physical_exam']['remarks']) === 'pending');
    $needs_review = isset($record['needs_review']) && $record['needs_review'] === true;
    if ($is_pending || $needs_review) {
        $pending_physical_exams_count++;
    }
    
    // Count active (completed physical exams)
    if ($record['type'] === 'physical_exam' && isset($record['physical_exam']['remarks'])) {
        $remarks = strtolower($record['physical_exam']['remarks']);
        if ($remarks == 'accepted') {
            $active_physical_exams_count++;
        }
    }
    
    // Count today's records
    if (isset($record['created_at']) && date('Y-m-d', strtotime($record['created_at'])) === $today) {
        $todays_summary_count++;
    }

    // Count New vs Returning
    if (isset($record['donor_type'])) {
        if ($record['donor_type'] === 'Returning') $returning_count++;
        else $new_count++;
    }
}

error_log("Card stats - Pending: $pending_physical_exams_count, Active: $active_physical_exams_count, Today's: $todays_summary_count");

// Handle pagination
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Handle status filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Filter records based on the selected status (optimized)
$display_records = [];

switch ($status_filter) {
    case 'all':
        $display_records = $all_records;
        break;
        
    case 'active':
        // Use array_filter for better performance
        $display_records = array_filter($all_records, function($record) {
            return $record['type'] === 'physical_exam' && 
                   isset($record['physical_exam']['remarks']) && 
                   strtolower($record['physical_exam']['remarks']) === 'accepted';
        });
        break;
        
    case 'today':
        $today = date('Y-m-d');
        $display_records = array_filter($all_records, function($record) use ($today) {
            return isset($record['created_at']) && date('Y-m-d', strtotime($record['created_at'])) === $today;
        });
        break;
        
    case 'pending':
        $display_records = array_filter($all_records, function($record) {
            $is_pending = ($record['type'] === 'screening') ||
                          ($record['type'] === 'physical_exam' && isset($record['physical_exam']['remarks']) && strtolower($record['physical_exam']['remarks']) === 'pending');
            $needs_review = isset($record['needs_review']) && $record['needs_review'] === true;
            return $is_pending || $needs_review;
        });
        break;
        
    case 'new':
        $display_records = array_filter($all_records, function($record) {
            return isset($record['donor_type']) && $record['donor_type'] === 'New';
        });
        break;

    case 'returning':
        $display_records = array_filter($all_records, function($record) {
            return isset($record['donor_type']) && $record['donor_type'] === 'Returning';
        });
        break;

    default:
        $display_records = $all_records;
        break;
}

// Sort records with priority:
// 1) needs_review === true first
// 2) then pending (screening or physical_exam with remarks 'pending')
// 3) FIFO by time
//    - For physical_examination rows: use updated_at, normalized to a plain timestamp (strip fractional seconds and timezone)
//    - Otherwise: use created_at
usort($display_records, function($a, $b) {
    $a_needs_review = isset($a['needs_review']) && $a['needs_review'] === true;
    $b_needs_review = isset($b['needs_review']) && $b['needs_review'] === true;

    if ($a_needs_review !== $b_needs_review) {
        return $a_needs_review ? -1 : 1;
    }

    $a_is_pending = ($a['type'] === 'screening') ||
        ($a['type'] === 'physical_exam' && isset($a['physical_exam']['remarks']) && strtolower($a['physical_exam']['remarks']) === 'pending');
    $b_is_pending = ($b['type'] === 'screening') ||
        ($b['type'] === 'physical_exam' && isset($b['physical_exam']['remarks']) && strtolower($b['physical_exam']['remarks']) === 'pending');

    if ($a_is_pending !== $b_is_pending) {
        return $a_is_pending ? -1 : 1;
    }

    // Time comparison
    // Helper to normalize ISO timestampz to a plain "Y-m-d H:i:s" timestamp (ignore timezone/fractional seconds)
    $normalizeTs = function($ts) {
        if (!$ts || !is_string($ts)) return 0;
        // Remove fractional seconds
        $s = preg_replace('/\.[0-9]{1,6}/', '', $ts);
        // Replace 'T' with space
        $s = str_replace('T', ' ', $s);
        // Drop trailing timezone like 'Z' or +hh:mm/-hh:mm
        $s = preg_replace('/(Z|[+-][0-9]{2}:[0-9]{2})$/', '', $s);
        return strtotime(trim($s)) ?: 0;
    };

    // For physical exams, use normalized updated_at; else use created_at
    $a_time = 0;
    if ($a['type'] === 'physical_exam' && !empty($a['updated_at'])) {
        $a_time = $normalizeTs($a['updated_at']);
    } else {
        $a_time = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    }

    $b_time = 0;
    if ($b['type'] === 'physical_exam' && !empty($b['updated_at'])) {
        $b_time = $normalizeTs($b['updated_at']);
    } else {
        $b_time = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    }
    return $a_time <=> $b_time;
});

$total_records = count($display_records);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Calculate the offset for this page
$offset = ($current_page - 1) * $records_per_page;

// Slice the array to get only the records for the current page
$records = array_slice($display_records, $offset, $records_per_page);

// Debug info message
$debug_info = "";
if (empty($display_records)) {
    $debug_info = "No records found for the selected filter.";
} else {
    $debug_info = "Showing " . count($display_records) . " records for the selected filter.";
}

// For admin view
$isAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Staff Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/defer-donor-modal.css">
    <link rel="stylesheet" href="../../assets/css/medical-history-approval-modals.css">
    <script src="../../assets/js/physical_examination_modal.js"></script>
    <script src="../../assets/js/defer_donor_modal.js"></script>
    
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

        /* UNIQUE HEADER STYLING - Completely isolated from external CSS */
        .physician-dashboard-header-unique {
            background: white !important;
            border-bottom: 1px solid #e0e0e0 !important;
            padding: 0.75rem 1rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            /* CRITICAL: Force static positioning */
            position: static !important;
            top: auto !important;
            left: 0 !important;
            right: auto !important;
            height: auto !important;
            z-index: auto !important;
            box-shadow: none !important;
            /* Prevent any external CSS from affecting this */
            margin: 0 !important;
            transform: none !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Force header to stay in place NO MATTER WHAT */
        body.modal-open .physician-dashboard-header-unique,
        html body.modal-open .physician-dashboard-header-unique,
        html body .physician-dashboard-header-unique,
        .modal-open .physician-dashboard-header-unique,
        .physician-dashboard-header-unique.modal-open {
            position: static !important;
            left: 0 !important;
            right: auto !important;
            top: auto !important;
            z-index: auto !important;
            margin: 0 !important;
            padding: 0.75rem 1rem !important;
            transform: none !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Prevent Bootstrap from affecting body */
        body.modal-open,
        html body.modal-open {
            padding-right: 0 !important;
            overflow: auto !important;
        }
        
        /* COMPLETE ISOLATION: Prevent any external CSS from affecting dashboard */
        .physician-dashboard-header-unique * {
            box-sizing: border-box !important;
        }
        
        /* Override any potential conflicts with Bootstrap or other CSS */
        .physician-dashboard-header-unique {
            all: unset !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            background: white !important;
            border-bottom: 1px solid #e0e0e0 !important;
            padding: 0.75rem 1rem !important;
            position: static !important;
            top: auto !important;
            left: 0 !important;
            right: auto !important;
            height: auto !important;
            z-index: auto !important;
            box-shadow: none !important;
            margin: 0 !important;
            transform: none !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        .physician-header-left-unique {
            display: flex;
            align-items: center;
        }
        
        .physician-header-title-unique {
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            color: #333;
        }
        
        .physician-header-date-unique {
            color: #777;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .physician-header-right-unique {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .physician-register-btn-unique {
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
        
        .physician-logout-btn-unique {
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
        
        .physician-logout-btn-unique:hover {
            background-color: var(--primary-dark);
            color: white;
            text-decoration: none;
        }

        /* Main Content - OVERRIDE main dashboard CSS */
        .main-content {
            padding: 1rem !important;
            background-color: var(--bg-color) !important;
            /* CRITICAL: Override margin-left from main CSS */
            margin-left: 0 !important;
            width: 100% !important;
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

        /* Physical Examination Modal Styles */
        .physical-examination-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .physical-examination-modal.show {
            opacity: 1;
        }

        .physical-modal-content {
            background: white;
            border-radius: 15px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .physical-examination-modal.show .physical-modal-content {
            transform: translateY(0);
        }

        .physical-modal-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .physical-modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .physical-close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }

        .physical-close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Medical History Modal Styles - Matching Physical Examination Modal */
        .medical-history-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10080;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .medical-history-modal.show {
            opacity: 1;
        }

        .medical-modal-content {
            background: white;
            border-radius: 15px;
            max-width: 1200px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .medical-history-modal.show .medical-modal-content {
            transform: translateY(0);
        }

        .medical-modal-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .medical-modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .medical-close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }

        .medical-close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .medical-modal-body {
            padding: 30px;
        }

        /* Physical Examination Progress Indicator - Matching Screening Modal */
        .physical-progress-container {
            background: white !important;
            padding: 20px !important;
            border-bottom: 1px solid #e9ecef !important;
            position: relative !important;
            display: block !important;
            visibility: visible !important;
        }

        .physical-progress-steps {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            position: relative !important;
            z-index: 2 !important;
            visibility: visible !important;
        }

        .physical-step {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }

        .physical-step-number {
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            background: #e9ecef !important;
            color: #6c757d !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: bold !important;
            font-size: 16px !important;
            transition: all 0.3s ease !important;
            margin-bottom: 8px !important;
            border: none !important;
            box-sizing: border-box !important;
        }

        .physical-step-label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
        }

        .physical-step.active .physical-step-number,
        .physical-step.completed .physical-step-number {
            background: #b22222 !important;
            color: white !important;
        }

        .physical-step.active .physical-step-label,
        .physical-step.completed .physical-step-label {
            color: #b22222 !important;
            font-weight: 600 !important;
        }

        .physical-progress-line {
            position: absolute;
            top: 40%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: 1;
        }

        .physical-progress-fill {
            height: 100%;
            background: #b22222;
            width: 0%;
            transition: width 0.5s ease;
        }

        /* Modal Form */
        .physical-modal-form {
            padding: 30px;
        }

        /* Step Content - Different from Progress Steps */
        .physical-step-content {
            display: none !important;
            animation: fadeIn 0.3s ease;
        }

        .physical-step-content.active {
            display: block !important;
        }

        /* Progress Step Indicators */
        .physical-step {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }

        .physical-step-content h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .physical-step-content p.text-muted {
            margin-bottom: 25px;
        }

        /* Initial Screening Summary Styles */
        .initial-screening-container {
            padding: 25px;
        }
        
        /* Header Section */
        .screening-header-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #b22222;
        }
        
        .donor-info-primary {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .donor-name-display {
            color: #b22222;
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
        }
        
        .donor-id-display {
            color: #6c757d;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .donor-info-right {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .donor-basic-details {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .info-badge {
            background: #ffffff;
            color: #495057;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            border: 1px solid #dee2e6;
        }
        
        .blood-type-badge {
            background: #b22222;
            color: white;
            font-weight: 600;
            border: 1px solid #b22222;
        }
        
        .screening-date-badge {
            background: #ffffff;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            color: #495057;
            font-weight: 500;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }
        
        .date-label {
            color: #6c757d;
            font-weight: 500;
            margin-right: 6px;
            font-size: 0.9rem;
        }
        
        /* Information Sections */
        .screening-info-grid {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #e9ecef;
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-section-right {
            border-left: 2px solid #f0f0f0;
            padding-left: 25px;
        }
        
        .section-title {
            color: #b22222;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .info-grid {
            display: grid;
            gap: 12px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item .label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .info-item .value {
            color: #212529;
            font-weight: 600;
            text-align: right;
        }
        
        /* Address Section */
        .address-section {
            margin: 25px 0;
            padding: 15px 0;
            border-top: 1px solid #e9ecef;
        }
        
        .address-display {
            margin-top: 8px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            color: #495057;
            font-style: italic;
        }
        
        /* Status Section */
        .exam-status-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .status-verification {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 20px 0;
        }
        
        .status-icon {
            font-size: 2rem;
            color: #28a745;
            margin-bottom: 5px;
        }
        
        .status-text {
            color: #28a745;
            font-weight: 600;
            font-size: 1.1rem;
            text-align: center;
        }
        
        .status-note {
            color: #6c757d;
            font-size: 0.9rem;
            text-align: center;
            font-style: italic;
            max-width: 400px;
            line-height: 1.4;
        }

        /* Option Cards */
        .physical-blood-bag-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .physical-option-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            position: relative;
            overflow: hidden;
            text-align: center;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .physical-option-card:hover {
            border-color: #b22222;
            box-shadow: 0 6px 20px rgba(178, 34, 34, 0.15);
            transform: translateY(-2px);
        }

        .physical-option-card input[type="radio"] {
            display: none;
        }

        .physical-option-card input[type="radio"]:checked + .physical-option-content {
            color: #b22222;
        }

        /* Active Selection Border */
        .physical-option-card input[type="radio"]:checked {
            + .physical-option-content {
                color: #b22222;
            }
        }

        .physical-option-card:has(input[type="radio"]:checked) {
            border-color: #b22222 !important;
            border-width: 2px;
            background: white;
            box-shadow: none;
            transform: none;
        }

        .physical-option-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .physical-option-content i {
            font-size: 1.8rem;
            color: #b22222;
            margin-bottom: 5px;
        }

        /* Fallback for browsers that don't support :has() */
        .physical-option-card.selected {
            border-color: #b22222 !important;
            border-width: 2px;
            background: white;
            box-shadow: none;
            transform: none;
        }



        /* Examination Report Styles */
        .examination-report {
            background: #ffffff;
            border-radius: 8px;
            padding: 30px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Report Header */
        .report-header {
            border-bottom: 2px solid #b22222;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .report-title h5 {
            color: #b22222;
            font-weight: 700;
            margin: 0 0 10px 0;
            font-size: 1.3rem;
        }
        
        .report-meta {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .report-physician {
            font-weight: 500;
        }
        
        .report-date {
            font-style: italic;
        }
        
        /* Report Sections */
        .report-section {
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #495057;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .section-header i {
            color: #b22222;
            font-size: 1.2rem;
        }
        
        .section-content {
            padding-left: 25px;
        }
        
        /* Vital Signs Grid */
        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .vital-item {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        
        .vital-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .vital-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #212529;
        }
        
        .vital-unit {
            color: #6c757d;
            font-size: 0.85rem;
            font-style: italic;
            margin-left: 2px;
        }
        
        /* Examination Findings */
        .examination-findings {
            display: grid;
            gap: 12px;
        }
        
        .finding-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 15px;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .finding-row:last-child {
            border-bottom: none;
        }
        
        .finding-label {
            color: #495057;
            font-weight: 500;
        }
        
        .finding-value {
            color: #212529;
            font-weight: 400;
        }
        
        /* Assessment Content */
        .assessment-content {
            display: grid;
            gap: 15px;
        }
        
        .assessment-result,
        .assessment-reason,
        .assessment-collection {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            padding: 10px 0;
        }
        
        .result-label,
        .reason-label,
        .collection-label {
            color: #495057;
            font-weight: 500;
        }
        
        .result-value,
        .reason-value,
        .collection-value {
            color: #212529;
            font-weight: 600;
        }
        
        /* Signature Section */
        .report-signature {
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid #dee2e6;
        }
        
        .signature-line {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .signature-line span {
            color: #495057;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .physician-name {
            color: #b22222 !important;
            font-weight: 600 !important;
            font-size: 1rem !important;
            border-bottom: 1px solid #6c757d;
            padding-bottom: 2px;
            min-width: 150px;
            text-align: center;
        }
        
        .signature-note {
            color: #6c757d;
            font-size: 0.8rem;
            font-style: italic;
            text-align: center;
            line-height: 1.4;
        }

        /* Modal Footer */
        .physical-modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
            background-color: white;
            border-radius: 0 0 15px 15px;
        }

        .physical-nav-buttons {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
        }

        .physical-nav-buttons .btn {
            padding: 12px 25px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .physical-cancel-btn {
            margin-right: auto;
        }

        .physical-defer-btn {
            border-color: #dc3545;
            color: #dc3545;
            background-color: white;
        }

        .physical-defer-btn:hover {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }

        .physical-defer-btn:focus {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .physical-medical-history-btn {
            border-color: #007bff;
            color: #007bff;
            background-color: white;
        }
        .physical-medical-history-btn:hover {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
        }
        .physical-medical-history-btn:focus {
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* Toast Messages */
        .physical-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            border-left: 4px solid #b22222;
        }

        .physical-toast.show {
            transform: translateX(0);
        }

        .physical-toast-success {
            border-left-color: #28a745;
        }

        .physical-toast-error {
            border-left-color: #dc3545;
        }

        .physical-toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .physical-toast-content i {
            font-size: 1.2rem;
        }

        .physical-toast-success i {
            color: #28a745;
        }

        .physical-toast-error i {
            color: #dc3545;
        }

        /* Form Validation */
        .form-control.is-invalid {
            border-color: #dc3545;
        }

        .form-control.is-valid {
            border-color: #28a745;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .physical-modal-content {
                width: 95%;
                margin: 20px;
            }

            .physical-progress-container {
                padding: 20px;
            }

            .physical-progress-steps {
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }

            .physical-step {
                min-width: 60px;
            }

            .physical-step-label {
                font-size: 10px;
            }

            .physical-blood-bag-options {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .physical-option-card {
                padding: 15px 10px;
                min-height: 70px;
            }
            
            .physical-option-content i {
                font-size: 1.5rem;
            }
            
            .physical-option-content {
                font-size: 13px;
            }

            .physical-modal-form {
                padding: 20px;
            }

            .physical-nav-buttons {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }

            .physical-nav-buttons .btn {
                width: 100%;
                margin-right: 0 !important;
            }

            .physical-cancel-btn {
                order: 1;
            }

            .physical-defer-btn {
                order: 2;
            }
            
            /* Examination Report Mobile */
            .examination-report {
                padding: 20px;
            }
            
            .report-meta {
                flex-direction: column;
                gap: 5px;
                align-items: flex-start;
            }
            
            .vital-signs-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            
            .vital-item {
                text-align: center;
                flex-direction: row;
                gap: 4px;
                font-size: 0.85rem;
            }
            
            .vital-label {
                font-size: 0.75rem;
            }
            
            .vital-value {
                font-size: 0.9rem;
                font-weight: 600;
            }
            
            .vital-unit {
                font-size: 0.75rem;
            }
            
            .finding-row {
                grid-template-columns: 1fr;
                gap: 5px;
            }
            
            .finding-label {
                font-weight: 600;
                color: #b22222;
            }
            
            .assessment-result,
            .assessment-reason,
            .assessment-collection {
                grid-template-columns: 1fr;
                gap: 5px;
            }
            
            .result-label,
            .reason-label,
            .collection-label {
                font-weight: 600;
                color: #b22222;
            }
            
            .signature-line {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .signature-space {
                width: 200px;
                margin: 0;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Badge styling */
        .badge.bg-primary {
            background-color: var(--primary-color) !important;
            font-size: 0.95rem;
            padding: 0.3rem 0.6rem;
            font-weight: 600;
            border-radius: 4px;
        }

        .badge.bg-secondary {
            background-color: #6c757d !important;
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
        }

        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #212529;
            font-size: 0.85rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-success {
            background-color: #28a745 !important;
            color: white;
            font-size: 0.85rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-danger {
            background-color: #dc3545 !important;
            color: white;
            font-size: 0.85rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Action button styling */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 4px;
        }

        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }

        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            color: #212529;
        }
        
        /* Section header */
        .section-header {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: #333;
        }
         /* Loader Animation -- Modal Design */
         .loading-spinner {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 8px solid #ddd;
            border-top: 8px solid #d9534f;
            animation: rotateSpinner 1s linear infinite;
            display: none;
            z-index: 10000;
            transform: translate(-50%, -50%);
        }

        @keyframes rotateSpinner {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        /* Note: Confirmation modal CSS has been removed as it's no longer needed */

        /* Observation text styling - clear and professional */
        .observation-text {
            font-size: 0.9rem;
            line-height: 1.4;
            max-width: 350px;
            word-wrap: break-word;
            cursor: help;
            color: #495057;
            font-weight: 400;
            font-family: 'Segoe UI', system-ui, sans-serif;
            transition: color 0.2s ease;
        }

        .observation-text:hover {
            color: #212529 !important;
            text-decoration: underline;
        }

        /* Vital signs emphasis */
        .observation-text .vitals {
            color: #28a745;
            font-weight: 500;
        }

        

        /* Global Button Styling */
        .btn {
            border-radius: 4px !important;
        }
        
        /* Donor type colored text (no badge) */
        .type-new { color: #2e7d32; font-weight: 700; }
        .type-returning { color: #1976d2; font-weight: 700; }
    </style>
    <style>
        /* Initial Screening Modal - match MH design and ensure proper backdrop layering */
        #screeningFormModal {
            z-index: 1055 !important;
        }
        #screeningFormModal .modal-dialog {
            max-width: 800px;
            width: 90%;
        }
        #screeningFormModal .modal-content.screening-modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        #screeningFormModal .screening-modal-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: #fff;
            border: none;
            padding: 16px 20px;
        }
        #screeningFormModal .btn-close.btn-close-white { filter: invert(1); }
        #screeningFormModal .screening-modal-body { padding: 24px; }
        #screeningFormModal .screening-modal-footer {
            background: #fff;
            border-top: 1px solid #e9ecef;
            padding: 16px 20px;
        }
        /* Ensure backdrop is visible and does not block inputs */
        .modal-backdrop.show { opacity: .5 !important; }
        #screeningFormModal * { pointer-events: auto; }
    </style>
    <style>
        /* Extend screening modal UI to match MH look */
        #screeningFormModal .screening-progress-container { background:#fff; padding:20px; border-bottom:1px solid #e9ecef; position:relative; }
        #screeningFormModal .screening-progress-steps { display:flex; justify-content:space-between; align-items:center; position:relative; z-index:2; }
        #screeningFormModal .screening-step { display:flex; flex-direction:column; align-items:center; cursor:pointer; transition:all .3s ease; }
        #screeningFormModal .screening-step-number { width:40px; height:40px; border-radius:50%; background:#e9ecef; color:#6c757d; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; margin-bottom:8px; border:none; }
        #screeningFormModal .screening-step-label { font-size:12px; color:#6c757d; font-weight:500; text-align:center; }
        #screeningFormModal .screening-step.active .screening-step-number,
        #screeningFormModal .screening-step.completed .screening-step-number { background:#b22222; color:#fff; }
        #screeningFormModal .screening-step.active .screening-step-label,
        #screeningFormModal .screening-step.completed .screening-step-label { color:#b22222; font-weight:600; }
        #screeningFormModal .screening-progress-line { position:absolute; top:40%; left:0; right:0; height:2px; background:#e9ecef; transform:translateY(-50%); z-index:1; }
        #screeningFormModal .screening-progress-fill { height:100%; background:#b22222; width:0%; transition:width .5s ease; }

        #screeningFormModal .screening-step-content { display:none; animation: fadeIn .3s ease; }
        #screeningFormModal .screening-step-content.active { display:block; }
        #screeningFormModal .screening-step-title h6 { color:#333; margin-bottom:10px; font-size:1.1rem; font-weight:600; }
        #screeningFormModal .screening-step-title p { color:#6c757d; margin-bottom:20px; }

        #screeningFormModal .screening-label { color:#6c757d; font-weight:500; font-size:.95rem; }
        #screeningFormModal .screening-input { width:100%; border:1px solid #e9ecef; border-radius:6px; padding:.5rem .75rem; transition:border-color .2s ease; }
        #screeningFormModal .screening-input:focus { outline:none; border-color:#b22222; box-shadow:0 0 0 .15rem rgba(178,34,34,.15); }
        #screeningFormModal .screening-input-group { position:relative; }
        #screeningFormModal .screening-input-suffix { position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#6c757d; font-size:.85rem; }

        #screeningFormModal .screening-detail-card { background:#f8f9fa; border:1px solid #ddd; padding:20px; border-radius:8px; margin-bottom:16px; }
        #screeningFormModal .screening-category-title { background:#e9ecef; color:#b22222; font-weight:700; text-align:center; padding:10px; margin:-20px -20px 15px -20px; }

        #screeningFormModal .screening-review-title { color:#b22222; font-weight:700; margin-bottom:10px; }
        #screeningFormModal .screening-review-item { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f8f9fa; }
        #screeningFormModal .screening-review-item:last-child { border-bottom:none; }
        #screeningFormModal .screening-review-label { color:#6c757d; font-weight:500; }
        #screeningFormModal .screening-review-value { color:#212529; font-weight:600; }

        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    </style>
</head>
<body class="light-mode">
    <div class="container-fluid p-0">
        <!-- Header -->
        <div class="physician-dashboard-header-unique" id="physicianDashboardHeaderUnique">
            <div class="physician-header-left-unique">
                <h4 class="physician-header-title-unique">Physician Dashboard <span class="physician-header-date-unique"><?php echo date('l, M d, Y'); ?></span></h4>
            </div>
            <div class="physician-header-right-unique">
                <button class="physician-register-btn-unique" onclick="showConfirmationModal()">
                    Register Donor
                </button>
                <a href="../../assets/php_func/logout.php" class="physician-logout-btn-unique">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="row g-0">
            <!-- Main Content -->
            <main class="col-12 main-content">
                <div class="content-wrapper">
                    <div class="welcome-section">
                        <h2 class="welcome-title">Welcome, Physician!</h2>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="dashboard-staff-status">
                        <a href="?status=all" class="status-card <?php echo (!isset($_GET['status']) || $_GET['status'] === 'all') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo count($all_records); ?></p>
                            <p class="dashboard-staff-title">All Records</p>
                        </a>
                        <a href="?status=pending" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $pending_physical_exams_count; ?></p>
                            <p class="dashboard-staff-title">Pending Physical Exams</p>
                        </a>
                        <a href="?status=active" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'active') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $active_physical_exams_count; ?></p>
                            <p class="dashboard-staff-title">Active Physical Exams</p>
                        </a>
                        <a href="?status=today" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'today') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $todays_summary_count; ?></p>
                            <p class="dashboard-staff-title">Today's Summary</p>
                        </a>
                    </div>
                    
                    <h5 class="section-header">Physical Examination Records</h5>
                    
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
                                    <th>SURNAME</th>
                                    <th>First Name</th>
                                    <th>Donor Type</th>
                                    <th>Status</th>
                                    <th>Physician</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="screeningTableBody">
                                <?php 
                                if (!empty($records)) {
                                    $counter = ($current_page - 1) * $records_per_page + 1; // Initialize counter with pagination
                                    foreach ($records as $record) {
                                        // Format the full name
                                        $surname = isset($record['donor_form']['surname']) ? $record['donor_form']['surname'] : '';
                                        $firstName = isset($record['donor_form']['first_name']) ? $record['donor_form']['first_name'] : '';
                                        
                                        // Format the date - use the same timestamp logic as FIFO sorting
                                        $date_timestamp = null;
                                        if ($record['type'] === 'physical_exam' && !empty($record['updated_at'])) {
                                            $date_timestamp = $record['updated_at'];
                                        } else {
                                            $date_timestamp = $record['created_at'] ?? null;
                                        }
                                        $date = $date_timestamp ? date('F j, Y', strtotime($date_timestamp)) : 'Unknown';
                                        
                                        // Determine Results and Observation based on record type and data
                                        $result = 'N/A';
                                        $observation = 'N/A';
                                        $status = 'Pending';
                                        
                                        if ($record['type'] === 'physical_exam' && isset($record['physical_exam'])) {
                                            $exam = $record['physical_exam'];
                                            
                                            // Set status: Pending when needs_review is true; else reflect remarks
                                            $needs_review_flag = isset($exam['needs_review']) && (
                                                ($exam['needs_review'] === true) || ($exam['needs_review'] === 1) || ($exam['needs_review'] === '1') ||
                                                (is_string($exam['needs_review']) && in_array(strtolower(trim($exam['needs_review'])), ['true','t','yes','y'], true))
                                            );
                                            if ($needs_review_flag) {
                                                $status = 'Pending';
                                            } elseif (!empty($exam['remarks'])) {
                                                $status = ucfirst($exam['remarks']); // Use actual remarks like "Accepted", "Temporarily Deferred", etc.
                                            } else {
                                                $status = 'Pending';
                                            }
                                            
                                            // Results column - based on remarks and disapproval_reason
                                            if (!empty($exam['remarks'])) {
                                                $result = ucfirst($exam['remarks']);
                                                if (!empty($exam['disapproval_reason']) && strtolower($exam['remarks']) !== 'accepted') {
                                                    $result .= ' - ' . $exam['disapproval_reason'];
                                                }
                                            }
                                            
                                            // Observation column - clear and understandable medical summary
                                            $observation_parts = [];
                                            $tooltip_details = [];
                                            
                                            // Primary assessment - General appearance (most important)
                                            if (!empty($exam['gen_appearance'])) {
                                                $appearance = ucfirst(strtolower($exam['gen_appearance']));
                                                $observation_parts[] = $appearance . ' condition';
                                                $tooltip_details[] = 'Physical Condition: ' . $exam['gen_appearance'];
                                            }
                                            
                                            // Vital signs (if available) - very important for medical staff
                                            $vital_signs = '';
                                            if (!empty($exam['blood_pressure'])) {
                                                $vital_signs .= 'BP ' . $exam['blood_pressure'];
                                                $tooltip_details[] = 'Blood Pressure: ' . $exam['blood_pressure'];
                                            }
                                            if (!empty($exam['pulse_rate'])) {
                                                if ($vital_signs) $vital_signs .= ', ';
                                                $vital_signs .= 'Pulse ' . $exam['pulse_rate'];
                                                $tooltip_details[] = 'Pulse Rate: ' . $exam['pulse_rate'];
                                            }
                                            if (!empty($exam['body_temp'])) {
                                                if ($vital_signs) $vital_signs .= ', ';
                                                $vital_signs .= 'Temp ' . $exam['body_temp'];
                                                $tooltip_details[] = 'Body Temperature: ' . $exam['body_temp'];
                                            }
                                            
                                            // Heart and lungs assessment
                                            if (!empty($exam['heart_and_lungs'])) {
                                                $heart_lungs = ucfirst(strtolower($exam['heart_and_lungs']));
                                                $observation_parts[] = $heart_lungs . ' heart/lungs';
                                                $tooltip_details[] = 'Heart & Lungs: ' . $exam['heart_and_lungs'];
                                            }
                                            
                                            // Skin condition
                                            if (!empty($exam['skin'])) {
                                                $skin = ucfirst(strtolower($exam['skin']));
                                                $observation_parts[] = $skin . ' skin';
                                                $tooltip_details[] = 'Skin Condition: ' . $exam['skin'];
                                            }
                                            
                                            // Additional notes/reasons
                                            if (!empty($exam['reason'])) {
                                                $tooltip_details[] = 'Additional Notes: ' . $exam['reason'];
                                            }
                                            
                                            // Create the main observation text
                                            if (!empty($observation_parts)) {
                                                if (count($observation_parts) == 1) {
                                                    $observation = $observation_parts[0];
                                                } elseif (count($observation_parts) == 2) {
                                                    $observation = $observation_parts[0] . ', ' . $observation_parts[1];
                                                } else {
                                                    $observation = $observation_parts[0] . ', ' . $observation_parts[1] . ' + more';
                                                }
                                                
                                                // Add vital signs if available (priority display)
                                                if ($vital_signs) {
                                                    if (strlen($observation . ' (' . $vital_signs . ')') <= 60) {
                                                        $observation .= ' (' . $vital_signs . ')';
                                                    } else {
                                                        $observation .= ' (vitals recorded)';
                                                    }
                                                }
                                            } else if ($vital_signs) {
                                                // If only vital signs available
                                                $observation = 'Vitals: ' . $vital_signs;
                                            }
                                            
                                            // Create comprehensive tooltip
                                            $full_observation = !empty($tooltip_details) ? implode(' • ', $tooltip_details) : '';
                                        } else {
                                            // For screening records without physical exams
                                            $status = 'Pending';
                                            $result = 'N/A';
                                            $observation = 'Awaiting physical examination';
                                            $full_observation = 'Screening completed, waiting for physical examination';
                                        }
                                        
                                        // Prepare encoded data for JavaScript
                                        if ($record['type'] === 'physical_exam') {
                                            $encoded_data = json_encode([
                                                'physical_exam_id' => $record['physical_exam_id'] ?? '',
                                                'screening_id' => $record['physical_exam']['screening_id'] ?? ($record['screening_id'] ?? ''),
                                                'donor_form_id' => $record['donor_id'] ?? '',
                                                'has_pending_exam' => $record['has_pending_exam'] ?? false,
                                                'type' => 'physical_exam'
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT);
                                        } else {
                                            $encoded_data = json_encode([
                                                'screening_id' => $record['screening_id'] ?? '',
                                                'donor_form_id' => $record['donor_id'] ?? '',
                                                'has_pending_exam' => $record['has_pending_exam'] ?? false,
                                                'type' => 'screening'
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT);
                                        }
                                        ?>
                                        <tr class="clickable-row" data-screening='<?php echo htmlspecialchars($encoded_data); ?>'>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($date); ?></td>
                                            <td><?php echo htmlspecialchars(strtoupper($surname)); ?></td>
                                            <td><?php echo htmlspecialchars($firstName); ?></td>
                                            <td>
                                                <?php 
                                                // Donor type text only (no stage label), colored
                                                $donor_type = isset($record['donor_type']) ? $record['donor_type'] : 'New';
                                                $type_text = $donor_type; // Only show donor type, no stage label
                                                $cls = ($donor_type === 'Returning') ? 'type-returning' : 'type-new';
                                                ?>
                                                <span class="<?php echo $cls; ?>"><?php echo htmlspecialchars($type_text); ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                // Determine badge color based on actual remarks
                                                $badge_class = 'bg-warning'; // Default for pending
                                                $status_lower = strtolower($status);
                                                if ($status_lower === 'accepted') {
                                                    $badge_class = 'bg-success';
                                                } elseif (strpos($status_lower, 'defer') !== false || strpos($status_lower, 'reject') !== false || strpos($status_lower, 'decline') !== false) {
                                                    $badge_class = 'bg-danger';
                                                } elseif ($status_lower === 'pending') {
                                                    $badge_class = 'bg-warning';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                // Physician column: show physical_examination.physician or Pending for screenings
                                                $physician_name = 'Pending';
                                                if ($record['type'] === 'physical_exam' && isset($record['physical_exam'])) {
                                                    if (!empty($record['physical_exam']['physician'])) {
                                                        $physician_name = $record['physical_exam']['physician'];
                                                    } else {
                                                        $physician_name = (strtolower($status) === 'pending') ? 'Pending' : 'N/A';
                                                    }
                                                }
                                                echo htmlspecialchars($physician_name);
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                // Determine if this record is pending or needs review (editable)
                                                $is_pending = false;
                                                $needs_review_flag = isset($record['needs_review']) && $record['needs_review'] === true;
                                                if ($record['type'] === 'screening') {
                                                    $is_pending = true; // Screenings are always pending (need physical exam)
                                                } elseif ($record['type'] === 'physical_exam' && isset($record['physical_exam']['remarks'])) {
                                                    $remarks = strtolower($record['physical_exam']['remarks']);
                                                    $is_pending = ($remarks === 'pending');
                                                }
                                                
                                                // Revert to default behavior: editable only when pending or needs_review
                                                $is_editable = ($is_pending || $needs_review_flag);
                                                ?>
                                                
                                                <?php if ($is_editable): ?>
                                                    <!-- Pending records: Show edit button only -->
                                                    <button type="button" class="btn btn-warning btn-sm edit-btn" 
                                                            data-screening='<?php echo htmlspecialchars($encoded_data); ?>'
                                                            title="Edit Physical Examination">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Completed records: Show view button only -->
                                                    <button type="button" class="btn btn-info btn-sm view-btn" 
                                                            data-screening='<?php echo htmlspecialchars($encoded_data); ?>'
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                <?php 
                                    }
                                } else {
                                    echo '<tr><td colspan="7" class="text-center">No records found for the selected filter</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Donor medical history navigation">
                                <ul class="pagination justify-content-center">
                                    <!-- Previous button -->
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>" <?php echo $current_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Previous</a>
                                    </li>
                                    
                                    <!-- Page numbers -->
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <!-- Next button -->
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>" <?php echo $current_page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>    
                
                <!-- Loading Spinner -->
                <div class="loading-spinner" id="loadingSpinner"></div>
            </main>
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

        <?php include '../../src/views/modals/defer-donor-modal.php'; ?>
    <?php include '../../src/views/modals/physical-examination-modal.php'; ?>
    
    <?php include '../../src/views/forms/physician-screening-form-content-modal.php'; ?>

    <!-- Physical Examination Modal is included from external file -->

    <!-- Physical Examination Approve Confirmation Modal -->
    <div class="modal fade" id="physicalExamApproveConfirmModal" tabindex="-1" aria-labelledby="physicalExamApproveConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title" id="physicalExamApproveConfirmModalLabel">
                        <i class="fas fa-check-circle me-2"></i>
                        Approve Donor for Donation?
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-0" style="font-size: 1.1rem;">Confirm this donor is fit to donate blood?</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4" id="confirmApprovePhysicalExamBtn">
                        <i class="fas fa-check me-2"></i>Approve
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Physical Examination Accepted Success Modal (uniform with MH) -->
    <div class="modal fade" id="physicalExamAcceptedModal" tabindex="-1" aria-labelledby="physicalExamAcceptedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title" id="physicalExamAcceptedModalLabel">
                        <i class="fas fa-check-circle me-2"></i>
                        Physical Examination Accepted
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="text-center">
                        <div class="mb-3">
                            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="text-success mb-3">Approval Successful!</h5>
                        <p class="text-muted mb-0">The donor is medically cleared for donation.</p>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">
                        <i class="fas fa-check me-2"></i>Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Donor Profile Modal -->
    <div class="modal fade" id="donorProfileModal" tabindex="-1" aria-labelledby="donorProfileModalLabel" aria-hidden="true" style="z-index: 10060;">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title" id="donorProfileModalLabel">
                        <i class="fas fa-user me-2"></i>
                        Donor Profile & Status
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div id="donorProfileModalContent">
                        <!-- Content will be loaded dynamically -->
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading donor profile...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0" id="donorProfileFooter" style="display: none;">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary px-4" id="proceedToPhysicalBtn" style="background-color: #b22222; border-color: #b22222; display: none;">
                        <i class="fas fa-stethoscope me-2"></i>Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Medical History Modal -->
    <div class="medical-history-modal" id="medicalHistoryModal">
        <div class="medical-modal-content">
            <div class="medical-modal-header">
                <h3><i class="fas fa-file-medical me-2"></i>Medical History Review & Approval</h3>
                <button type="button" class="medical-close-btn" onclick="closeMedicalHistoryModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="medical-modal-body">
                <div id="medicalHistoryModalContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading medical history...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP data to JavaScript
        const medicalByDonor = <?php echo json_encode($medical_by_donor ?? []); ?>;
        
        /**
         * Unified API call function to reduce duplication
         * @param {string} url - The API endpoint URL
         * @param {Object} options - Fetch options (optional)
         * @returns {Promise} - Promise that resolves to JSON response
         */
        async function makeApiCall(url, options = {}) {
            try {
                // Determine if we're sending FormData
                const isFormData = options.body instanceof FormData;
                
                const fetchOptions = {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        ...options.headers
                    },
                    ...options
                };
                
                // If sending FormData, don't set Content-Type (let browser set it with boundary)
                if (isFormData) {
                    delete fetchOptions.headers['Content-Type'];
                }
                
                const response = await fetch(url, fetchOptions);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return await response.json();
            } catch (error) {
                console.error('API call error:', error);
                throw error;
            }
        }
        
        /**
         * Unified modal management functions
         */
        const ModalManager = {
            show: function(modalId) {
                const el = document.getElementById(modalId);
                if (!el) return null;
                // Use static backdrop for screening modal to avoid accidental closes
                const opts = (modalId === 'screeningFormModal') ? { backdrop: 'static', keyboard: false, focus: true } : {};
                // Ensure attributes reflect options (helps with third-party triggers)
                if (modalId === 'screeningFormModal') {
                    try { el.setAttribute('data-bs-backdrop', 'static'); } catch(_) {}
                    try { el.setAttribute('data-bs-keyboard', 'false'); } catch(_) {}
                }
                const modal = new bootstrap.Modal(el, opts);
                modal.show();
                return modal;
            },
            
            hide: function(modalId) {
                const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
                if (modal) {
                    modal.hide();
                }
            },
            
            getInstance: function(modalId) {
                return bootstrap.Modal.getInstance(document.getElementById(modalId));
            }
        };

        // Shim: silence unused initializer in physician flow
        if (typeof window.initializeMedicalHistoryApproval !== 'function') {
            window.initializeMedicalHistoryApproval = function(){};
        }
        
        function showConfirmationModal() {
            ModalManager.show('confirmationModal');
        }

        function proceedToDonorForm() {
            // Hide confirmation modal
            ModalManager.hide('confirmationModal');

            // Show loading modal
            ModalManager.show('loadingModal');

            // Redirect after a short delay to show loading animation
            setTimeout(() => {
                window.location.href = '../../src/views/forms/donor-form-modal.php';
            }, 800);
        }
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            const screeningTableBody = document.getElementById('screeningTableBody');
            let currentScreeningData = null;

            // Store original rows for search reset
            const originalRows = Array.from(screeningTableBody.getElementsByTagName('tr'));

            // Attach click event to view buttons
            function attachButtonClickHandlers() {
                // Remove existing event listeners first
                document.querySelectorAll(".view-btn").forEach(button => {
                    button.removeEventListener("click", button.viewClickHandler);
                    // On every show, force-refresh content to ensure latest DB state
                    try {
                        window.lastDonorProfileContext = { donorId: donorId, screeningData: screeningData };
                        setTimeout(() => { refreshDonorProfileModal({ donorId, screeningData }); }, 60);
                    } catch(_) {}
                });
                document.querySelectorAll(".edit-btn").forEach(button => {
                    button.removeEventListener("click", button.editClickHandler);
                });

                document.querySelectorAll(".view-btn").forEach(button => {
                    button.viewClickHandler = function(e) {
                        e.stopPropagation(); // Prevent row click
                        try {
                            currentScreeningData = JSON.parse(this.getAttribute('data-screening'));
                            console.log("Selected screening:", currentScreeningData);
                            
                            // Open donor profile modal instead of confirmation dialog
                            openDonorProfileModal(currentScreeningData);
                        } catch (e) {
                            console.error("Error parsing screening data:", e);
                            alert("Error selecting this record. Please try again.");
                        }
                    };
                    button.addEventListener("click", button.viewClickHandler);
                });

                // Attach click event to edit buttons
                document.querySelectorAll(".edit-btn").forEach(button => {
                    button.editClickHandler = function(e) {
                        e.stopPropagation(); // Prevent row click
                        try {
                            currentScreeningData = JSON.parse(this.getAttribute('data-screening'));
                            console.log("Selected record for editing:", currentScreeningData);
                            
                            // Open donor profile modal for editing
                            openDonorProfileModal(currentScreeningData);
                        } catch (e) {
                            console.error("Error parsing screening data:", e);
                            alert("Error selecting this record. Please try again.");
                        }
                    };
                    button.addEventListener("click", button.editClickHandler);
                    });
            }

            // Only call once
            attachButtonClickHandlers();

            // Search functionality
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const category = searchCategory ? searchCategory.value : 'all';
                
                if (!searchTerm) {
                    originalRows.forEach(row => row.style.display = '');
                    return;
                }

                originalRows.forEach(row => {
                    const cells = Array.from(row.getElementsByTagName('td'));
                    let shouldShow = false;

                    if (category === 'all') {
                        shouldShow = cells.some(cell => 
                            cell.textContent.toLowerCase().includes(searchTerm)
                        );
                    } else {
                        const columnIndex = {
                            'date': 1,
                            'surname': 2,
                            'firstname': 3,
                            'blood_type': 4,
                            'donation_type': 5
                        }[category];

                        if (columnIndex !== undefined && cells[columnIndex]) {
                            const cellText = cells[columnIndex].textContent.toLowerCase();
                            shouldShow = cellText.includes(searchTerm);
                        }
                    }

                    row.style.display = shouldShow ? '' : 'none';
                });
            }

            // Update placeholder based on selected category
            if (searchCategory) {
                searchCategory.addEventListener('change', function() {
                    const category = this.value;
                    let placeholder = 'Search by ';
                    switch(category) {
                        case 'date': placeholder += 'date...'; break;
                        case 'surname': placeholder += 'surname...'; break;
                        case 'firstname': placeholder += 'first name...'; break;
                        case 'blood_type': placeholder += 'blood type...'; break;
                        case 'donation_type': placeholder += 'donation type...'; break;
                        default: placeholder = 'Search records...';
                    }
                    searchInput.placeholder = placeholder;
                    performSearch();
                });
            }

            // Debounce function
            function debounce(func, wait) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }

            // Apply debounced search
            const debouncedSearch = debounce(performSearch, 300);

            // Event listeners
            searchInput.addEventListener('input', debouncedSearch);
            if (searchCategory) {
                searchCategory.addEventListener('change', debouncedSearch);
            }

            // Respect server-side editability: do NOT override edit buttons for Approved MH
            // If a row is editable because needs_review === true or pending, keep it editable.
            function enforceApprovedReadOnlyButtons() {
                attachButtonClickHandlers();
            }

            // Run once on load
            enforceApprovedReadOnlyButtons();

            // Removed: allow screening modal to close normally (no guard)

            // When Screening or Medical History modals close via X, return to Donor Profile
            installReturnToProfileOnClose();

            // Install deep diagnostic hooks to find who closes/hides modals/overlays
            installModalCloseDebugHooks();

            // Defer modal will be initialized when opened
            
            // Initialize medical history approval functionality
            if (typeof initializeMedicalHistoryApproval === 'function') {
                initializeMedicalHistoryApproval();
            }
            // Ensure footer visibility matches remarks when arriving from other flows
            try { enforcePendingConfirmVisibility(); } catch(_) {}
        });

        // Reopen Donor Profile when certain modals are closed by the user
        function installReturnToProfileOnClose(){
            try {
                const reopenProfile = function(){
                    try {
                        if (window.__suppressReturnToProfile) { window.__suppressReturnToProfile = false; return; }
                        const dpEl = document.getElementById('donorProfileModal');
                        if (!dpEl) return;
                        const dp = bootstrap.Modal.getInstance(dpEl) || new bootstrap.Modal(dpEl);
                        dp.show();
                        // After showing, force-refresh the modal content so latest DB values are displayed
                        setTimeout(() => { try { refreshDonorProfileModal(); } catch(_) {} }, 100);
                    } catch(_) {}
                };
                // Screening modal (Bootstrap)
                const sEl = document.getElementById('screeningFormModal');
                if (sEl && !sEl.__returnHooked) {
                    sEl.__returnHooked = true;
                    sEl.addEventListener('hidden.bs.modal', function(){ reopenProfile(); });
                    // Also hook the top-right close button explicitly
                    const xBtn = sEl.querySelector('.btn-close');
                    if (xBtn && !xBtn.__returnHooked) {
                        xBtn.__returnHooked = true;
                        xBtn.addEventListener('click', function(){
                            setTimeout(reopenProfile, 200);
                        });
                    }
                }
                // Medical History modal (custom) – use our close workflow
                try {
                    const btn = document.querySelector('#medicalHistoryModal .medical-close-btn');
                    if (btn && !btn.__returnHooked) {
                        btn.__returnHooked = true;
                        btn.addEventListener('click', function(){
                            try { window.__suppressReturnToProfile = false; } catch(_) {}
                        });
                    }
                } catch(_) {}
            } catch(_) {}
        }

        // Diagnostic: Find exact sources that hide/remove modals and overlays
        function installModalCloseDebugHooks(){
            try {
                if (window.__modalDebugHooksInstalled) return; window.__modalDebugHooksInstalled = true;
                const isWatched = function(el){
                    try {
                        if (!el) return false;
                        if (el.id === 'screeningFormModal' || el.id === 'donorProfileModal' || el.id === 'medicalHistoryModal' || el.id === 'simpleCustomModal') return true;
                        const cls = el.classList ? Array.from(el.classList) : [];
                        if (cls.includes('modal-backdrop') || cls.includes('modal')) return true;
                    } catch(_) {}
                    return false;
                };
                const logStack = function(prefix, el){
                    try {
                        const desc = el ? (el.id ? ('#'+el.id) : (el.className || el.tagName)) : '(null)';
                        const stack = (new Error()).stack;
                        console.warn(prefix + ' → ' + desc, '\nStack:\n' + stack);
                    } catch(_) {}
                };
                // Wrap removeChild
                try {
                    const origRemoveChild = Node.prototype.removeChild;
                    Node.prototype.removeChild = function(child){
                        try { if (isWatched(child)) logStack('[ModalDebug] removeChild', child); } catch(_) {}
                        return origRemoveChild.apply(this, arguments);
                    };
                } catch(_) {}
                // Wrap remove()
                try {
                    const origRemove = Element.prototype.remove;
                    Element.prototype.remove = function(){
                        try { if (isWatched(this)) logStack('[ModalDebug] element.remove', this); } catch(_) {}
                        return origRemove.apply(this, arguments);
                    };
                } catch(_) {}
                // Wrap replaceChild
                try {
                    const origReplaceChild = Node.prototype.replaceChild;
                    Node.prototype.replaceChild = function(newChild, oldChild){
                        try { if (isWatched(oldChild)) logStack('[ModalDebug] replaceChild(old)', oldChild); } catch(_) {}
                        return origReplaceChild.apply(this, arguments);
                    };
                } catch(_) {}
                // MutationObserver for attribute changes that hide
                try {
                    const body = document.body;
                    const mo = new MutationObserver((muts) => {
                        try {
                            muts.forEach((m) => {
                                try {
                                    const t = m.target;
                                    if (!isWatched(t)) return;
                                    if (m.type === 'attributes' && (m.attributeName === 'class' || m.attributeName === 'style')) {
                                        const disp = (t.style && t.style.display) || '';
                                        const hidden = (disp === 'none') || (t.classList && !t.classList.contains('show') && (t.classList.contains('modal') || t.id === 'screeningFormModal'));
                                        if (hidden) logStack('[ModalDebug] attribute-hide', t);
                                    }
                                } catch(_) {}
                            });
                        } catch(_) {}
                    });
                    mo.observe(body, { attributes:true, childList:true, subtree:true, attributeFilter:['class','style'] });
                } catch(_) {}
            } catch(_) {}
        }

        // Removed guardScreeningApproveClose (allowed to close freely)

        // Controlled Approve handler for screening modal (no modal close)
        async function handleScreeningApproveClick(){
            try {
                const modal = document.getElementById('screeningFormModal');
                if (!modal) return;
                const donorIdInput = modal.querySelector('input[name="donor_id"]');
                const donorId = donorIdInput && donorIdInput.value ? donorIdInput.value : (window.currentDonorData && window.currentDonorData.donor_id) || '';
                if (!donorId) {
                    if (window.customInfo) window.customInfo('Unable to resolve donor. Please reopen.'); else alert('Unable to resolve donor.');
                    return;
                }
                const proceed = function(){
                    (async () => {
                        try {
                            try { showProcessingModal('Approving medical history...'); } catch(_) {}
                            const fd = new FormData();
                            fd.append('donor_id', donorId);
                            fd.append('medical_approval', 'Approved');
                            const res = await fetch('../../public/api/update-medical-approval.php', { method: 'POST', body: fd });
                            const json = await res.json().catch(() => ({ success:false }));
                            if (!json || !json.success) {
                                if (window.customInfo) window.customInfo('Failed to set Medical History to Approved.'); else alert('Failed to set Medical History to Approved.');
                                return;
                            }
                            // Update in-memory cache so UI doesn't flash Not Approved
                            try {
                                if (typeof window.medicalByDonor === 'object') {
                                    const key = donorId; const k2 = String(donorId);
                                    window.medicalByDonor[key] = window.medicalByDonor[key] || {};
                                    window.medicalByDonor[key].medical_approval = 'Approved';
                                    window.medicalByDonor[k2] = window.medicalByDonor[k2] || {};
                                    window.medicalByDonor[k2].medical_approval = 'Approved';
                                }
                            } catch(_) {}

                            // Prepare context for donor profile refresh
                            try { window.lastDonorProfileContext = { donorId: donorId, screeningData: { donor_form_id: donorId } }; } catch(_) {}
                            // Release guard so global hide.bs.modal does not block closing
                            try { window.__screeningApproveActive = false; } catch(_) {}
                            // Fade-close screening, then reopen Donor Profile (with a slight delay for DB commit)
                            try {
                                const inst = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
                                inst.hide();
                            } catch(_) { modal.classList.remove('show'); setTimeout(()=>{ modal.style.display='none'; }, 150); }
                            // Ensure backdrops/body state are cleaned to truly close the screening modal
                            setTimeout(function(){
                                try { forceCloseScreeningModal(); } catch(_) {}
                            }, 100);
                            setTimeout(function(){
                                const dpEl = document.getElementById('donorProfileModal');
                                if (dpEl) {
                                    const dp = bootstrap.Modal.getInstance(dpEl) || new bootstrap.Modal(dpEl);
                                    try { dp.show(); } catch(_) {}
                                    // Force-refresh donor profile to pull latest data
                                    try { refreshDonorProfileModal({ donorId, screeningData: { donor_form_id: donorId } }); } catch(_) {}
                                }
                                try { setTimeout(()=>hideProcessingModal(), 400); } catch(_) {}
                            }, 1200);
                        } catch(_) {
                            if (window.customInfo) window.customInfo('Network error approving Medical History.'); else alert('Network error.');
                        }
                    })();
                };
                if (window.customConfirm) {
                    window.customConfirm('Approve Medical History for this donor?', proceed);
                } else if (confirm('Approve Medical History for this donor?')) {
                    proceed();
                }
            } catch(_) {}
        }
        // Enforce footer Confirm visibility based on physical_examination.needs_review
        function enforcePendingConfirmVisibility() {
            try {
                const donorIdEl = document.querySelector('#donorProfileModal [data-donor-id], #donorProfileModal input[name="donor_id"], #donorProfileModal #dp-donor-id-flag');
                const donorId = donorIdEl ? (donorIdEl.getAttribute('data-donor-id') || donorIdEl.value) : (window.lastDonorProfileContext && window.lastDonorProfileContext.donorId);
                const footer = document.getElementById('donorProfileFooter');
                const proceedBtn = document.getElementById('proceedToPhysicalBtn');
                const peConfirmBtn = document.getElementById('physicalExamConfirmBtn');
                if (!donorId) {
                    if (footer) footer.style.display = 'none';
                    if (proceedBtn) proceedBtn.style.display = 'none';
                    if (peConfirmBtn) peConfirmBtn.style.display = 'none';
                    return;
                }
                // Prefer DOM flag from server-rendered modal to avoid async racing
                const flagEl = document.querySelector('#donorProfileModal #pe-needs-review-flag');
                if (flagEl) {
                    const needsReview = flagEl.value === '1';
                    const disp = needsReview ? '' : 'none';
                    if (footer) footer.style.display = disp;
                    if (proceedBtn) proceedBtn.style.display = disp;
                    if (peConfirmBtn) peConfirmBtn.style.display = disp;
                    return;
                }
                // Fallback: fetch latest physical examination and use needs_review flag
                (async () => {
                    try {
                        const d = await makeApiCall(`../api/get-physical-examination.php?donor_id=${donorId}`);
                        const v = d && (d.physical_exam ? d.physical_exam.needs_review : d.needs_review);
                        const needsReview = (v === true) || (v === 1) || (v === '1') || (typeof v === 'string' && ['true','t','yes','y'].includes(v.trim().toLowerCase()));
                        const disp = needsReview ? '' : 'none';
                        if (footer) footer.style.display = disp;
                        if (proceedBtn) proceedBtn.style.display = disp;
                        if (peConfirmBtn) peConfirmBtn.style.display = disp;
                    } catch(_) {
                        if (footer) footer.style.display = 'none';
                        if (proceedBtn) proceedBtn.style.display = 'none';
                        if (peConfirmBtn) peConfirmBtn.style.display = 'none';
                    }
                })();
            } catch(_) {}
        }



        // Donor Profile Modal Functions
        function openDonorProfileModal(screeningData) {
            console.log("Opening donor profile modal with data:", screeningData);
            
            // Prevent multiple instances
            if (window.isOpeningDonorProfile) {
                try {
                    const dpElChk = document.getElementById('donorProfileModal');
                    const visible = dpElChk && dpElChk.classList.contains('show') && dpElChk.style.display !== 'none';
                    const tooOld = window.__dpLastOpenAttempt && (Date.now() - window.__dpLastOpenAttempt > 1500);
                    if (!visible || tooOld) {
                        // Stale or failed open attempt; clear the flag and continue
                        window.isOpeningDonorProfile = false;
                        console.log('Cleared stale isOpeningDonorProfile flag and retrying open...');
                    } else {
                        console.log("Donor profile modal already opening, skipping...");
                        return;
                    }
                } catch(_) { /* continue */ }
            }
            window.isOpeningDonorProfile = true;
            try { window.__dpLastOpenAttempt = Date.now(); } catch(_) {}
            
            // Get donor ID from screening data
            const donorId = screeningData.donor_form_id;
            if (!donorId) {
                console.error("No donor ID found in screening data");
                alert("Error: No donor ID found. Please try again.");
                window.isOpeningDonorProfile = false;
                return;
            }
            
            // Only hide an existing visible modal when explicitly forcing a reopen
            try {
                const el = document.getElementById('donorProfileModal');
                const existingModal = el ? bootstrap.Modal.getInstance(el) : null;
                const isVisible = el && el.classList.contains('show');
                if (existingModal && isVisible && window.forceReopenDonorProfile === true) {
                    existingModal.hide();
                    setTimeout(() => { openDonorProfileModalInternal(screeningData, donorId); }, 100);
                    window.forceReopenDonorProfile = false;
                    return;
                }
            } catch (e) { /* noop */ }
            
            // Open modal directly if no existing instance
            openDonorProfileModalInternal(screeningData, donorId);
        }
        
        function openDonorProfileModalInternal(screeningData, donorId) {
            // Show loading state in modal content
            const modalContent = document.getElementById('donorProfileModalContent');
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading donor profile...</p>
                </div>
            `;
            
            // Show the modal
            const donorProfileModalEl = document.getElementById('donorProfileModal');
            const donorProfileModal = new bootstrap.Modal(donorProfileModalEl);
            donorProfileModalEl.addEventListener('hide.bs.modal', function(ev){
                try {
                    const allowHide = !!window.allowDonorProfileHide;
                    const successActive = !!(window.__mhSuccessActive || window.__peSuccessActive);
                    // Only prevent hide during success states unless explicitly allowed
                    if (successActive && !allowHide) {
                        ev.preventDefault();
                        return false;
                    }
                } catch(_) {}
                try { window.allowDonorProfileHide = false; } catch(_) {}
            }, { capture: true });
            // When actually shown, clear the opening flag
            try {
                donorProfileModalEl.addEventListener('shown.bs.modal', function(){
                    try { window.isOpeningDonorProfile = false; } catch(_) {}
                    // Ensure screening modal is fully closed when donor profile is shown
                    try {
                        const sEl = document.getElementById('screeningFormModal');
                        if (sEl) {
                            const sInst = bootstrap.Modal.getInstance(sEl) || new bootstrap.Modal(sEl);
                            try { sInst.hide(); } catch(_) {}
                            try { sEl.classList.remove('show'); sEl.style.display='none'; sEl.setAttribute('aria-hidden','true'); } catch(_) {}
                        }
                        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                        document.body.classList.add('modal-open');
                    } catch(_) {}
                });
            } catch(_) {}
            
            // Set up the close listener
            donorProfileModalEl.addEventListener('hidden.bs.modal', () => {
                // Skip cleanup if we intentionally hide to switch to another modal
                if (!window.skipDonorProfileCleanup) {
                    cleanupModalArtifacts();
                }
                window.isOpeningDonorProfile = false;
                window.skipDonorProfileCleanup = false;
            }, { once: true });
            
            // Show only if not already visible
            if (!donorProfileModalEl.classList.contains('show')) {
                donorProfileModal.show();
            }
            
            // Load donor profile content (with inline loader while open)
            loadDonorProfileContent(donorId, screeningData);
            
            // Bind proceed button functionality
            bindProceedToPhysicalButton(screeningData);
            
            // Verify modal integrity after a short delay
            setTimeout(() => {
                verifyDonorProfileModalIntegrity();
            }, 500);
        }
        
        // Function to verify and fix donor profile modal integrity
        function verifyDonorProfileModalIntegrity() {
            const modalEl = document.getElementById('donorProfileModal');
            if (!modalEl) return;
            
            // Check if modal has proper Bootstrap classes
            if (!modalEl.classList.contains('modal')) {
                console.log('Restoring missing Bootstrap modal classes...');
                modalEl.classList.add('modal', 'fade');
            }
            
            // Check if modal backdrop exists
            const backdrop = document.querySelector('.modal-backdrop');
            if (!backdrop && document.body.classList.contains('modal-open')) {
                console.log('Modal backdrop missing, resetting body state...');
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
            
            // Ensure proper z-index for the modal
            if (modalEl.style.zIndex === '') {
                modalEl.style.zIndex = '1055';
            }
            
            console.log('Donor profile modal integrity verified');
        }
        
        // Inline loader for donor profile modal content
        function setDonorProfileLoading(message){
            try {
                const modalContent = document.getElementById('donorProfileModalContent');
                if (!modalContent) return;
                modalContent.innerHTML = `
                    <div class="text-center" style="padding: 28px 0;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0">${message || 'Loading donor profile...'}</p>
                    </div>
                `;
            } catch(_) {}
        }

        function loadDonorProfileContent(donorId, screeningData) {
            // Fetch donor profile content directly into the modal
            setDonorProfileLoading('Loading donor profile...');
            const ts = Date.now();
            fetch(`../../src/views/forms/donor-profile-modal-content.php?donor_id=${donorId}&_=${ts}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    // Update modal content
                    const modalContent = document.getElementById('donorProfileModalContent');
                    modalContent.innerHTML = html;
                    
                    // Enforce visibility rules: Confirm only when Physical Examination needs_review is true
                    try { enforcePendingConfirmVisibility(); } catch(_) {}
                    
                    console.log('Donor profile content loaded successfully');
                    
                    // Adjust action buttons based on medical approval status
                    updateDonorProfileActions(donorId);

                    // Add event listeners for the confirm buttons
                    bindDonorProfileConfirmButtons(screeningData);
                })
                .catch(error => {
                    console.error('Error loading donor profile:', error);
                    const modalContent = document.getElementById('donorProfileModalContent');
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading donor profile. Please try again.
                        </div>
                    `;
                });
        }

        // Force-close the Initial Screening modal if it is open
        function forceCloseScreeningModal() {
            try {
                const sEl = document.getElementById('screeningFormModal');
                if (!sEl) return;
                // Best-effort: hide via Bootstrap API first
                try { (bootstrap.Modal.getInstance(sEl) || new bootstrap.Modal(sEl)).hide(); } catch(_) {}
                // Hard close styles/attributes
                try { sEl.classList.remove('show'); sEl.style.display = 'none'; sEl.setAttribute('aria-hidden','true'); } catch(_) {}
                // Remove stray backdrops and reset body
                try { document.querySelectorAll('.modal-backdrop').forEach(b => b.remove()); } catch(_) {}
                try { document.body.classList.remove('modal-open'); document.body.style.overflow=''; document.body.style.paddingRight=''; } catch(_) {}
            } catch(_) {}
        }

        // Helper: force-refresh donor profile modal content from server
        function refreshDonorProfileModal(forceContext) {
            try {
                const ctx = forceContext || window.lastDonorProfileContext || {};
                const donorId = ctx.donorId || (ctx.screeningData && ctx.screeningData.donor_form_id);
                if (!donorId) return;
                setDonorProfileLoading('Reloading donor profile...');
                loadDonorProfileContent(donorId, ctx.screeningData || { donor_form_id: donorId });
            } catch(_) {}
        }

        // When medical_approval is Approved, change Medical History action to a view (eye) button
        function updateDonorProfileActions(donorId) {
            try {
                const record = (medicalByDonor && (medicalByDonor[donorId] || medicalByDonor[String(donorId)])) || null;
                const isApproved = !!(record && record.medical_approval === 'Approved');
                const confirmBtn = document.getElementById('medicalHistoryConfirmBtn');
                if (isApproved && confirmBtn) {
                    const viewBtn = document.createElement('button');
                    viewBtn.type = 'button';
                    viewBtn.id = 'medicalHistoryViewBtn';
                    viewBtn.className = 'btn btn-info btn-sm';
                    viewBtn.innerHTML = '<i class="fas fa-eye"></i>';
                    viewBtn.title = 'View Medical History';
                    viewBtn.addEventListener('click', function() {
                        openMedicalHistoryModal(donorId);
                    });
                    confirmBtn.replaceWith(viewBtn);
                }
            } catch (e) {
                console.warn('updateDonorProfileActions error:', e);
            }
        }
        
        function bindDonorProfileConfirmButtons(screeningData) {
            // Add event listener for Medical History Confirm button
            const medicalHistoryBtn = document.getElementById('medicalHistoryConfirmBtn');
            if (medicalHistoryBtn) {
                medicalHistoryBtn.addEventListener('click', function() {
                    console.log('Medical History Confirm button clicked');
                    
                    // Get donor ID from screening data parameter
                    const donorId = screeningData?.donor_form_id;
                    if (!donorId) {
                        console.error('No donor ID available');
                        alert('Error: Unable to get donor information. Please try again.');
                        return;
                    }
                    
                    // Clear any conflicting success states
                    try {
                        window.__mhSuccessActive = false;
                        window.__peSuccessActive = false;
                    } catch(_) {}
                    
                    // Save context so we can return to the donor profile when closing the other modal
                    window.lastDonorProfileContext = { donorId: donorId, screeningData: screeningData };

                    // Always close donor profile modal before opening the next modal
                    try { window.allowDonorProfileHide = true; } catch(_) {}
                    try {
                        const dpEl = document.getElementById('donorProfileModal');
                        if (dpEl) {
                            const dp = bootstrap.Modal.getInstance(dpEl) || new bootstrap.Modal(dpEl);
                            // Disconnect guard observer to avoid re-show during intentional hide
                            try { (dpEl.__dpObserver || window.__dpObserver)?.disconnect?.(); } catch(_) {}
                            try { dp.hide(); } catch(_) {}
                            // Hard cleanup in case Bootstrap guard blocks
                            setTimeout(() => {
                                try { dpEl.classList.remove('show'); dpEl.style.display = 'none'; dpEl.setAttribute('aria-hidden','true'); } catch(_) {}
                                try { document.querySelectorAll('.modal-backdrop').forEach(b => b.remove()); } catch(_) {}
                                try { document.body.classList.remove('modal-open'); document.body.style.overflow = ''; document.body.style.paddingRight = ''; } catch(_) {}
                            }, 50);
                        }
                    } catch(_) {}

                    // Open medical history modal
                    setTimeout(() => openMedicalHistoryModal(donorId), 120);
                });
            }
            // Add event listener for Medical History View (read-only)
            const medicalHistoryViewBtn = document.getElementById('medicalHistoryViewBtn');
            if (medicalHistoryViewBtn) {
                medicalHistoryViewBtn.addEventListener('click', function() {
                    // Force readonly mode for medical history
                    window.forceMedicalReadonly = true;
                    const donorId = screeningData?.donor_form_id;
                    if (!donorId) return;
                    window.lastDonorProfileContext = { donorId: donorId, screeningData: screeningData };
                    openMedicalHistoryModal(donorId);
                });
            }
            
            // Add event listener for Physical Examination Confirm button
            const physicalExamBtn = document.getElementById('physicalExamConfirmBtn');
            if (physicalExamBtn) {
                // Show Confirm only when status != Accepted AND needs_review === true; else show eye (View)
                try {
                    const donorId = screeningData?.donor_form_id;
                    (async () => {
                        let needsReview = false;
                        let isAccepted = false;
                        try {
                            if (donorId) {
                                const data = await makeApiCall(`../api/get-physical-examination.php?donor_id=${donorId}`);
                                const pe = data && (data.physical_exam || data);
                                const val = pe ? pe.needs_review : null;
                                const remarks = pe ? (pe.remarks || pe.status || '') : '';
                                needsReview = (val === true) || (val === 1) || (val === '1') || (typeof val === 'string' && ['true','t','yes','y'].includes(val.trim().toLowerCase()));
                                isAccepted = String(remarks).trim().toLowerCase() === 'accepted';
                            }
                        } catch(_) { needsReview = false; isAccepted = false; }
                        if (isAccepted || !needsReview) {
                            const viewBtn = document.createElement('button');
                            viewBtn.type = 'button';
                            viewBtn.id = 'physicalExamViewBtn';
                            viewBtn.className = 'btn btn-info btn-sm';
                            viewBtn.title = 'View Physical Examination';
                            viewBtn.innerHTML = '<i class="fas fa-eye"></i>';
                            physicalExamBtn.replaceWith(viewBtn);
                            viewBtn.addEventListener('click', function(){
                                const donorId = screeningData?.donor_form_id;
                                if (!donorId) return;
                                window.lastDonorProfileContext = { donorId: donorId, screeningData: screeningData };
                                if (window.physicalExaminationModal) window.physicalExaminationModal.openModal(screeningData);
                            });
                        } else {
                            physicalExamBtn.style.display = '';
                        }
                    })();
                } catch(_) {}
                physicalExamBtn.addEventListener('click', function() {
                    console.log('Physical Examination Confirm button clicked');
                    
                    // Clear any conflicting success states
                    try {
                        window.__mhSuccessActive = false;
                        window.__peSuccessActive = false;
                    } catch(_) {}
                    
                    // Close the current donor profile modal
                    try { window.allowDonorProfileHide = true; } catch(_) {}
                    try {
                        const dpEl = document.getElementById('donorProfileModal');
                        if (dpEl) {
                            const donorProfileModal = bootstrap.Modal.getInstance(dpEl) || new bootstrap.Modal(dpEl);
                            // Disconnect guard observer to avoid re-show during intentional hide
                            try { (dpEl.__dpObserver || window.__dpObserver)?.disconnect?.(); } catch(_) {}
                            try { donorProfileModal.hide(); } catch(_) {}
                            setTimeout(() => {
                                try { dpEl.classList.remove('show'); dpEl.style.display = 'none'; dpEl.setAttribute('aria-hidden','true'); } catch(_) {}
                                try { document.querySelectorAll('.modal-backdrop').forEach(b => b.remove()); } catch(_) {}
                                try { document.body.classList.remove('modal-open'); document.body.style.overflow = ''; document.body.style.paddingRight = ''; } catch(_) {}
                            }, 50);
                        }
                    } catch(_) {}
                    
                    // Save context so we can return to donor profile when physical modal closes
                    if (screeningData?.donor_form_id) {
                        window.lastDonorProfileContext = { donorId: screeningData.donor_form_id, screeningData: screeningData };
                    }

                    // Open the physical examination modal (same as the bottom Confirm button)
                    setTimeout(() => proceedToPhysicalExamination(screeningData), 120);
                });
            }
            // Add event listener for Physical Examination View (read-only)
            const physicalExamViewBtn = document.getElementById('physicalExamViewBtn');
            if (physicalExamViewBtn) {
                physicalExamViewBtn.addEventListener('click', function() {
                    const donorId = screeningData?.donor_form_id;
                    if (!donorId) return;
                    // Force readonly mode for physical examination modal
                    window.forcePhysicalReadonly = true;
                    const donorProfileModal = bootstrap.Modal.getInstance(document.getElementById('donorProfileModal'));
                    if (donorProfileModal) donorProfileModal.hide();
                    if (window.physicalExaminationModal) {
                        window.lastDonorProfileContext = { donorId: donorId, screeningData: screeningData };
                        window.physicalExaminationModal.openModal(screeningData);
                        setTimeout(enforcePhysicalReadonly, 300);
                    }
                });
            }
        }
        
        function bindProceedToPhysicalButton(screeningData) {
            const proceedBtn = document.getElementById('proceedToPhysicalBtn');
            if (proceedBtn) {
                // Show footer only when physical_examination.needs_review === true
                try {
                    const donorId = screeningData?.donor_form_id;
                    (async () => {
                        let needsReview = false;
                        try {
                            if (donorId) {
                                const data = await makeApiCall(`../api/get-physical-examination.php?donor_id=${donorId}`);
                                const val = data && data.physical_exam ? data.physical_exam.needs_review : null;
                                needsReview = (val === true) || (val === 1) || (val === '1') || (typeof val === 'string' && ['true','t','yes','y'].includes(val.trim().toLowerCase()));
                            }
                        } catch(_) { needsReview = false; }
                        const footer = document.getElementById('donorProfileFooter');
                        if (needsReview) {
                            if (footer) footer.style.display = '';
                            proceedBtn.style.display = '';
                        } else {
                            if (footer) footer.style.display = 'none';
                            proceedBtn.style.display = 'none';
                        }
                    })();
                } catch(_) {}
                proceedBtn.onclick = async function() {
                    try {
                        const donorId = screeningData?.donor_form_id;
                        // Guard: if we cannot determine donor, block
                        if (!donorId) return proceedToPhysicalExamination(screeningData);

                        // Check medical approval from preloaded map
                        const mh = (typeof medicalByDonor === 'object' && medicalByDonor) ? (medicalByDonor[donorId] || medicalByDonor[String(donorId)]) : null;
                        const mhApprovalRaw = mh && mh.medical_approval ? String(mh.medical_approval).trim().toLowerCase() : '';
                        const mhApproved = (mhApprovalRaw === 'approved');

                        // Fetch latest physical examination to read status
                        let peAccepted = false;
                        try {
                            const json = await makeApiCall(`../../assets/php_func/fetch_physical_examination_info.php?donor_id=${donorId}`);
                            const status = (json && json.success && json.data && json.data.status) ? String(json.data.status).trim().toLowerCase() : '';
                            peAccepted = (status === 'accepted');
                        } catch (e) { peAccepted = false; }

                        // Trust embedded dropdown hint if present
                        const selectEl = document.getElementById('eligibilityStatus');
                        const enabledHint = !!(selectEl && (selectEl.getAttribute('data-enabled') === '1' || !selectEl.disabled));

                        // If requirements NOT met (and not explicitly enabled), show alert modal and DO NOT proceed
                        if (!(mhApproved && peAccepted) && !enabledHint) {
                            const msg = 'Eligibility Status can only be set when:\n\n' +
                                        '• Medical History is Approved, and\n' +
                                        '• Physical Examination status is Accepted.';
                            if (window.customInfo) {
                                window.customInfo(msg);
                            } else if (window.customConfirm) {
                                window.customConfirm(msg, function(){});
                            } else {
                                alert(msg);
                            }
                            return; // block navigation
                        }

                        // Requirements satisfied -> process based on selection.
                        const selection = (selectEl && selectEl.value) ? String(selectEl.value) : '';
                if (selection === 'approve') {
                    // Return to donor profile after confirm (physician flow)
                    try { window.allowDonorProfileHide = false; } catch(_) {}
                    const dpEl = document.getElementById('donorProfileModal');
                    if (dpEl) {
                        const dp = bootstrap.Modal.getInstance(dpEl) || new bootstrap.Modal(dpEl);
                        try { dp.show(); } catch(_) {}
                    }
                    return;
                }
                // Do nothing for other selections
                return;
                    } catch (e) {
                        // Fail-safe: do not proceed silently; show message
                        if (window.customInfo) {
                            window.customInfo('Unable to verify eligibility prerequisites. Please try again.');
                        } else if (window.customConfirm) {
                            window.customConfirm('Unable to verify eligibility prerequisites. Please try again.', function(){});
                        } else {
                            alert('Unable to verify eligibility prerequisites. Please try again.');
                        }
                    }
                };
            }
        }

        async function advanceToCollection(donorId) {
            // Show confirmation modal first
            if (typeof showFooterConfirmModal === 'function') {
                showFooterConfirmModal(async function() {
                    await performAdvanceToCollection(donorId);
                });
            } else {
                // Fallback to direct execution if modal function not available
                await performAdvanceToCollection(donorId);
            }
        }
        
        async function performAdvanceToCollection(donorId) {
            try {
                const json = await makeApiCall('../../assets/php_func/advance_to_collection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'donor_id=' + encodeURIComponent(donorId)
                });
                
                if (!json || !json.success) {
                    // Show failure modal
                    if (typeof showFooterActionFailureModal === 'function') {
                        const errorMessage = json && json.message ? json.message : 'Failed to advance to blood collection.';
                        showFooterActionFailureModal(errorMessage);
                    } else {
                        if (window.customInfo) window.customInfo('Failed to advance to blood collection.'); else alert('Failed to advance to blood collection.');
                    }
                    return;
                }
                
                // Show success modal
                if (typeof showFooterActionSuccessModal === 'function') {
                    showFooterActionSuccessModal();
                }
                
                // Close donor profile modal and reload after success modal
                setTimeout(() => {
                    const donorProfileModal = bootstrap.Modal.getInstance(document.getElementById('donorProfileModal'));
                    if (donorProfileModal) donorProfileModal.hide();
                    window.location.reload();
                }, 3500); // Wait for success modal to show
                
            } catch (err) {
                // Show failure modal for network errors
                if (typeof showFooterActionFailureModal === 'function') {
                    showFooterActionFailureModal('Network error while advancing to blood collection.');
                } else {
                    if (window.customInfo) window.customInfo('Network error while advancing to blood collection.'); else alert('Network error while advancing to blood collection.');
                }
            }
        }
        
        function proceedToPhysicalExamination(screeningData) {
            // Close donor profile modal
            const donorProfileModal = bootstrap.Modal.getInstance(document.getElementById('donorProfileModal'));
            donorProfileModal.hide();
            
            // Clear any existing reopen context to prevent conflicts
            window.lastDonorProfileContext = null;
            
            // Open physical examination modal
            console.log("Proceeding to physical examination with data:", screeningData);
            if (window.physicalExaminationModal) {
                window.physicalExaminationModal.openModal(screeningData);
                
                // Initialize defer button after modal opens
        if (typeof initializePhysicalExamDeferButton === 'function') {
                initializePhysicalExamDeferButton();
        }
        
        // Initialize medical history approval functionality
        if (typeof initializeMedicalHistoryApproval === 'function') {
            initializeMedicalHistoryApproval();
        }

                // Save context and patch close to reopen donor profile
                if (screeningData?.donor_form_id) {
                    window.lastDonorProfileContext = { donorId: screeningData.donor_form_id, screeningData: screeningData };
                }
                // Removed conflicting closeModal patch - physical examination modal handles its own reopening
            } else {
                console.error("Physical examination modal not initialized");
                alert("Error: Modal not properly initialized. Please refresh the page.");
            }
        }

        // REMOVED: tryReopenDonorProfile function - conflicts with medical history approval system
        // The medical history approval system handles modal reopening through showApprovedThenReturn
        // This prevents duplicate reopening attempts and conflicts
        
        // Function to completely reset medical history modal state
        function resetMedicalHistoryModalState() {
            console.log('Resetting medical history modal state...');
            const mhElement = document.getElementById('medicalHistoryModal');
            if (!mhElement) return;
            
            // Reset all attributes and classes
            mhElement.removeAttribute('style');
            mhElement.className = 'medical-history-modal';
            mhElement.removeAttribute('data-bs-backdrop');
            mhElement.removeAttribute('data-bs-keyboard');
            
            // Reset content to loading state
            const modalContent = document.getElementById('medicalHistoryModalContent');
            if (modalContent) {
                modalContent.innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading medical history...</p>
                    </div>
                `;
            }
            
            // Clear any global state variables
            window.currentDonorId = null;
            window.currentDonorApproved = false;
            
            console.log('Medical history modal state reset complete');
        }

        // Remove stale backdrops/classes when switching modals programmatically
        function cleanupModalArtifacts() {
            try {
                // Remove only stray Bootstrap backdrops that sometimes persist
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

                // Do NOT mutate or hide any .modal elements here. That can break
                // Bootstrap's internal state and lead to corrupted layouts when reopening.

                // If no Bootstrap modals are currently shown, ensure body is reset
                const anyOpen = document.querySelector('.modal.show');
                if (!anyOpen) {
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            } catch (e) {
                console.warn('Modal cleanup warning:', e);
            }
        }

        // Medical History Modal Functions
        function handleMedicalHistoryClick(e) {
            e.preventDefault();
            console.log('Medical history button clicked');
            
            // Get current donor ID from the physical examination modal
            const donorId = document.getElementById('physical-donor-id')?.value;
            
            if (!donorId) {
                showDeferToast('Error', 'Unable to get donor information. Please try again.', 'error');
                return;
            }
            
            console.log('Opening medical history modal for donor ID:', donorId);
            openMedicalHistoryModal(donorId);
        }
        
        function openMedicalHistoryModal(donorId) {
            // Prevent multiple instances
            if (window.isOpeningMedicalHistory) {
                console.log("Medical history modal already opening, skipping...");
                return;
            }
            window.isOpeningMedicalHistory = true;
            
            // Track approval status for modal behavior
            try {
                window.currentDonorId = donorId;
                // Handle numeric vs string keys
                const keyNum = donorId;
                const keyStr = String(donorId);
                const byDonor = (typeof medicalByDonor === 'object' && medicalByDonor) ? medicalByDonor : {};
                const rec = byDonor[keyNum] || byDonor[keyStr];
                window.currentDonorApproved = !!(rec && (rec.medical_approval === 'Approved'));
            } catch (e) { window.currentDonorApproved = false; }
            
            // Ensure Screening modal is fully closed before showing MH
            try {
                const sEl = document.getElementById('screeningFormModal');
                if (sEl) {
                    const sInst = bootstrap.Modal.getInstance(sEl) || new bootstrap.Modal(sEl);
                    try { sInst.hide(); } catch(_) {}
                    try { sEl.classList.remove('show'); sEl.style.display='none'; sEl.setAttribute('aria-hidden','true'); } catch(_) {}
                }
                // Remove any stale backdrops from other modals to prevent stacking issues
                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow='';
                document.body.style.paddingRight='';
            } catch(_) {}

            // Hide donor profile without cleanup, then show MH above it
            try {
                window.skipDonorProfileCleanup = true;
                const donorProfileEl = document.getElementById('donorProfileModal');
                const dpModal = bootstrap.Modal.getInstance(donorProfileEl) || new bootstrap.Modal(donorProfileEl);
                dpModal.hide();
            } catch (e) { /* noop */ }

            // Ensure the medical history modal starts with a clean state
            const mhElement = document.getElementById('medicalHistoryModal');
            
            // Reset any previous state
            mhElement.removeAttribute('style');
            mhElement.className = 'medical-history-modal';
            
            // Show the custom modal (like physical examination modal)
            mhElement.style.display = 'flex';
            setTimeout(() => mhElement.classList.add('show'), 10);
            
            // Show loading state in modal content
            const modalContent = document.getElementById('medicalHistoryModalContent');
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading medical history...</p>
                </div>
            `;
            
            // Load medical history content
            loadMedicalHistoryContent(donorId);
        }
        
        function closeMedicalHistoryModal() {
            console.log('Closing medical history modal...');
            const mhElement = document.getElementById('medicalHistoryModal');
            
            // First, hide the modal content
            mhElement.classList.remove('show');
            
            // Wait for the fade-out animation to complete
            setTimeout(() => {
                mhElement.style.display = 'none';
                window.isOpeningMedicalHistory = false;
                
                // Reset any form state or validation errors
                const form = mhElement.querySelector('form');
                if (form) {
                    form.reset();
                    // Remove any validation classes
                    form.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
                        el.classList.remove('is-invalid', 'is-valid');
                    });
                }
                
                // Clear any dynamic content that might be causing layout issues
                const modalContent = document.getElementById('medicalHistoryModalContent');
                if (modalContent) {
                    // Reset to loading state to clear any corrupted content
                    modalContent.innerHTML = `
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading medical history...</p>
                        </div>
                    `;
                }
                
                // Force a clean state by removing any lingering classes or styles
                mhElement.removeAttribute('style');
                mhElement.className = 'medical-history-modal';
                
                // Remove any dynamically added event listeners or data attributes
                mhElement.removeAttribute('data-bs-backdrop');
                mhElement.removeAttribute('data-bs-keyboard');
                
                console.log('Medical history modal closed');
                
                // Return to Donor Profile modal unless explicitly prevented (e.g., proceeding to Initial Screening)
                try {
                    const prevented = !!window.__preventReturnToProfile;
                    // Reset flag for next time
                    window.__preventReturnToProfile = false;
                    if (!prevented) {
                        const dpEl = document.getElementById('donorProfileModal');
                        if (dpEl) {
                            const dp = bootstrap.Modal.getInstance(dpEl) || new bootstrap.Modal(dpEl);
                            dp.show();
                        }
                    }
                } catch(_) {}
            }, 300);
        }
        
        function loadMedicalHistoryContent(donorId) {
            console.log('Loading medical history content for donor ID:', donorId);
            
            // Fetch medical history content from the physical dashboard specific file
            fetch(`../../src/views/forms/medical-history-physical-modal-content.php?donor_id=${donorId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    console.log('Medical history content loaded successfully');
                    
                    // Update modal content
                    const modalContent = document.getElementById('medicalHistoryModalContent');
                    modalContent.innerHTML = html;
                    // Remove any inline Approve buttons rendered inside the content; only use footer Approve
                    try {
                        setTimeout(() => {
                            const container = document.getElementById('medicalHistoryModalContent');
                            if (!container) return;
                            const nodes = container.querySelectorAll('button, a');
                            nodes.forEach(btn => {
                                const txt = (btn.textContent || '').trim().toLowerCase();
                                if (txt === 'approve' || txt === 'approve medical history') {
                                    if (btn.parentNode) btn.parentNode.removeChild(btn);
                                }
                            });
                        }, 50);
                    } catch(_) {}
                    
                    // Enforce buttons based on medical_approval status (Approved => view-only)
                    try {
                        const donorId = window.currentDonorId || (window.lastDonorProfileContext && window.lastDonorProfileContext.donorId) || null;
                        enforceMedicalApprovalButtons(donorId);
                    } catch(_) {}

                    // Debug: Check if donor_id field is present in the loaded form
                    const donorIdField = modalContent.querySelector('input[name="donor_id"]');
                    if (donorIdField) {
                        console.log('Donor ID field found with value:', donorIdField.value);
                    } else {
                        console.error('Donor ID field not found in loaded form!');
                    }
                    
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
                        // Enforce read-only if already approved (immediately and shortly after in case inner scripts render late)
                        enforceMedicalApprovedReadonly();
                        setTimeout(enforceMedicalApprovedReadonly, 150);
                        
                        // Reinitialize medical history approval functionality for dynamically loaded buttons
                        setTimeout(() => {
                            if (typeof initializeMedicalHistoryApproval === 'function') {
                                console.log('Reinitializing medical history approval for dynamically loaded content');
                                initializeMedicalHistoryApproval();
                            } else {
                                console.warn('initializeMedicalHistoryApproval function not available yet, retrying...');
                                // Retry after a longer delay
                                setTimeout(() => {
                                    if (typeof initializeMedicalHistoryApproval === 'function') {
                                        console.log('Retry: Reinitializing medical history approval for dynamically loaded content');
                                        initializeMedicalHistoryApproval();
                                    } else {
                                        console.error('initializeMedicalHistoryApproval function still not available');
                                    }
                                }, 1000);
                            }
                        }, 200);
                        
                        // Add direct fallback event handlers for decline and approve buttons
                        setTimeout(() => {
                            const declineButtons = document.querySelectorAll('.decline-medical-history-btn');
                            const approveButtons = document.querySelectorAll('.approve-medical-history-btn');
                            
                            console.log('Setting up direct fallback handlers for decline buttons:', declineButtons.length);
                            declineButtons.forEach((btn, index) => {
                                // Remove any existing handlers to prevent duplicates
                                btn.removeEventListener('click', handleDeclineClickFallback);
                                btn.addEventListener('click', handleDeclineClickFallback);
                                console.log(`Direct fallback handler attached to decline button ${index}`);
                            });
                            
                            console.log('Setting up direct fallback handlers for approve buttons:', approveButtons.length);
                            approveButtons.forEach((btn, index) => {
                                // Remove any existing handlers to prevent duplicates
                                btn.removeEventListener('click', handleApproveClickFallback);
                                btn.addEventListener('click', handleApproveClickFallback);
                                console.log(`Direct fallback handler attached to approve button ${index}`);
                            });
                        }, 300);
                })
                .catch(error => {
                    console.error('Error loading medical history:', error);
                    const modalContent = document.getElementById('medicalHistoryModalContent');
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading medical history. Please try again.
                        </div>
                    `;
                });
        }

        // Replace Approve/Decline with View when medical_approval == 'Approved'
        function enforceMedicalApprovalButtons(donorId){
            try {
                let approved = !!window.currentDonorApproved;
                if (!approved && donorId && medicalByDonor) {
                    const rec = medicalByDonor[donorId] || medicalByDonor[String(donorId)] || null;
                    approved = !!(rec && rec.medical_approval === 'Approved');
                }
                if (!approved) return;
                const container = document.getElementById('medicalHistoryModalContent') || document;
                // Remove decline and approve action buttons if present
                container.querySelectorAll('.decline-medical-history-btn, .approve-medical-history-btn').forEach(function(btn){
                    try { btn.remove(); } catch(_) {}
                });
                // Hide any generic Approve/Decline/Submit buttons from inline content
                const texts = ['approve','decline','submit'];
                container.querySelectorAll('button, a').forEach(function(el){
                    const t = (el.textContent||'').trim().toLowerCase();
                    if (texts.includes(t)) { try { el.style.display='none'; } catch(_) {} }
                });
                // Ensure any previously added view-only button is removed
                container.querySelectorAll('#mhViewOnlyBtn').forEach(function(b){ try { b.remove(); } catch(_) {} });
                // Enforce readonly visuals
                enforceMedicalApprovedReadonly();
            } catch(e) { console.warn('enforceMedicalApprovalButtons error', e); }
        }

        // Footer controls removed – inner modal provides its own navigation/buttons
        
        function generateMedicalHistoryHTML(data) {
            const donor = data.donor_info;
            const medical = data.medical_history;
            const hasMedical = data.has_medical_history;
            
            // Extract IDs for button data attributes
            const donorId = donor?.donor_id || 'no-donor-id';
            const screeningId = data.screening_id || 'no-screening-id';
            const medicalHistoryId = medical?.medical_history_id || 'no-medical-history-id';
            const physicalExamId = data.physical_exam_id || 'no-physical-exam-id';
            
            // Format date helper
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
            
            // Safe value helper
            const safe = (value) => value || 'N/A';
            
            return `
                <div class="medical-history-content">
                    <!-- Donor Information Header -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div style="color:#666; font-size:0.9rem; margin-bottom:5px;">
                                    Date: ${formatDate(medical?.created_at || medical?.updated_at)}
                                </div>
                                <div style="color:#333; font-weight:700; font-size:1.2rem; margin-bottom:5px;">
                                    ${safe(donor.full_name)}
                                </div>
                                <div style="color:#333; font-size:1rem;">
                                    ${safe(donor.age)}${donor.sex ? ', ' + donor.sex : ''}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="color:#666; font-size:0.9rem; margin-bottom:5px;">&nbsp;</div>
                                <div style="color:#333; font-weight:700; font-size:1.1rem; margin-bottom:5px;">
                                    Donor ID: ${safe(donor.donor_id)}
                                </div>
                                <div style="color:#333; font-size:1rem;">
                                    ${safe(medical?.blood_type || 'N/A')}
                                </div>
                            </div>
                        </div>
                        <hr style="margin: 15px 0;"/>
                    </div>
                    
                    <!-- Medical History Status -->
                    <div class="mb-4">
                        <h6 class="mb-3" style="color:#b22222; font-weight:700;">Medical History Status</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Status</label>
                                <input class="form-control" value="${hasMedical ? 'Completed' : 'Pending'}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Medical Approval</label>
                                <input class="form-control" value="${safe(medical?.medical_approval)}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Fitness Result</label>
                                <input class="form-control" value="${safe(medical?.fitness_result)}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Interviewer</label>
                                <input class="form-control" value="${safe(medical?.interviewer_name)}" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Donor Details -->
                    <div class="mb-4">
                        <h6 class="mb-3" style="color:#b22222; font-weight:700;">Donor Details</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Address</label>
                                <input class="form-control" value="${safe(donor.address)}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Mobile</label>
                                <input class="form-control" value="${safe(donor.mobile)}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Occupation</label>
                                <input class="form-control" value="${safe(donor.occupation)}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Nationality</label>
                                <input class="form-control" value="${safe(donor.nationality)}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Civil Status</label>
                                <input class="form-control" value="${safe(donor.civil_status)}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">Birthdate</label>
                                <input class="form-control" value="${safe(donor.birthdate)}" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical History Questions Summary -->
                    ${hasMedical ? `
                        <div class="mb-4">
                            <h6 class="mb-3" style="color:#b22222; font-weight:700;">Medical History Summary</h6>
                            <div class="alert alert-info">
                                <strong>Medical history completed on:</strong> ${formatDate(medical.created_at)}
                                <br><strong>Last updated:</strong> ${formatDate(medical.updated_at)}
                                <br><strong>Total questions answered:</strong> ${Object.keys(medical).filter(key => key.includes('q') && key.includes('_remarks')).length}
                            </div>
                        </div>
                    ` : `
                        <div class="mb-4">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>No medical history found</strong>
                                <br>This donor has not completed their medical history questionnaire yet.
                            </div>
                        </div>
                    `}
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                        ${hasMedical ? `
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-success approve-medical-history-btn" 
                                        data-donor-id="${donorId}" 
                                        data-screening-id="${screeningId || 'no-screening-id'}" 
                                        data-medical-history-id="${medicalHistoryId || 'no-medical-history-id'}" 
                                        data-physical-exam-id="${physicalExamId || 'no-physical-exam-id'}">
                                    <i class="fas fa-check me-2"></i>Approve Medical History
                                </button>
                                <button type="button" class="btn btn-danger decline-medical-history-btn" 
                                        data-donor-id="${donorId}" 
                                        data-screening-id="${screeningId || 'no-screening-id'}" 
                                        data-medical-history-id="${medicalHistoryId || 'no-medical-history-id'}" 
                                        data-physical-exam-id="${physicalExamId || 'no-physical-exam-id'}">
                                    <i class="fas fa-times me-2"></i>Decline Medical History
                                </button>
                            </div>
                        ` : `
                            <button type="button" class="btn btn-primary" disabled>
                                <i class="fas fa-info-circle me-2"></i>No Medical History to Approve
                            </button>
                        `}
                    </div>
                </div>
            `;
        }
        
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

        // Make the medical history modal read-only if already Approved
        function enforceMedicalApprovedReadonly() {
            try {
                // Determine approval either from preloaded map or embedded modal JSON
                let approved = !!window.currentDonorApproved;
                if (window.forceMedicalReadonly === true) approved = true;
                try {
                    if (!approved) {
                        const dataScript = document.getElementById('modalData');
                        if (dataScript && dataScript.textContent) {
                            const parsed = JSON.parse(dataScript.textContent);
                            const mh = parsed && parsed.medicalHistoryData;
                            if (mh && (mh.medical_approval === 'Approved')) approved = true;
                        }
                    }
                } catch (e) {}
                if (!approved) return;
                // Disable all inputs/selects inside the modal
                const container = document.getElementById('medicalHistoryModalContent');
                if (!container) return;
                container.querySelectorAll('input, select, textarea, button').forEach(el => {
                    // Keep navigation Previous/Close working
                    if (el.id === 'modalPrevButton') return;
                    // Hide Edit and Approve/Next buttons
                    if (el.id === 'modalApproveButton' || (el.textContent && el.textContent.trim().toLowerCase() === 'edit')) {
                        el.style.display = 'none';
                        return;
                    }
                    if (el.type === 'radio' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA' || el.tagName === 'INPUT') {
                        el.disabled = true;
                    }
                });

                // Keep Next for navigation; only convert to Close on final step
                const nextBtn = document.getElementById('modalNextButton');
                if (nextBtn) {
                    const convertIfFinal = () => {
                        const txt = (nextBtn.textContent || '').trim().toLowerCase();
                        if (txt.includes('approve')) {
                            nextBtn.textContent = 'Close';
                            nextBtn.onclick = function() {
                                const mh = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                                if (mh) mh.hide();
                            };
                        }
                    };
                    convertIfFinal();
                    const mo = new MutationObserver(convertIfFinal);
                    mo.observe(nextBtn, { childList: true, subtree: true, characterData: true });
                }
            } catch (e) {
                console.warn('Readonly enforcement error:', e);
            }
        }

        // Enforce physical examination modal read-only when viewing
        function enforcePhysicalReadonly() {
            try {
                if (!window.forcePhysicalReadonly) return;
                const modalRoot = document.getElementById('physicalExaminationModal');
                if (!modalRoot || modalRoot.style.display === 'none') return;
                // Disable all form controls
                modalRoot.querySelectorAll('input, select, textarea, button').forEach(el => {
                    // Keep close and prev/next for navigation but disable submit/defer/cancel
                    const isNav = el.classList.contains('physical-prev-btn') || el.classList.contains('physical-next-btn') || el.classList.contains('physical-close-btn');
                    const isSubmit = el.classList.contains('physical-submit-btn') || el.classList.contains('physical-defer-btn') || el.classList.contains('physical-cancel-btn');
                    if (isSubmit) {
                        el.style.display = 'none';
                    } else if (!isNav) {
                        if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
                            el.disabled = true;
                        }
                    }
                });
            } catch (e) {
                console.warn('Failed to enforce physical readonly:', e);
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
                    // Final step: hide Next completely; only Approve should remain
                    nextButton.style.display = 'none';

                    // Ensure a dedicated Approve button exists and is visible (submission happens only here)
                    let approveBtn = document.getElementById('modalApproveButton');
                    if (!approveBtn) {
                        approveBtn = document.createElement('button');
                        approveBtn.type = 'button';
                        approveBtn.id = 'modalApproveButton';
                        approveBtn.className = 'btn btn-success';
                        approveBtn.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Proceed to Initial Screening';
                        // Place the Approve button next to Next/Close button in the footer controls
                        if (nextButton && nextButton.parentNode) {
                            nextButton.parentNode.appendChild(approveBtn);
                        }
                    }
                    approveBtn.style.display = '';
                    // Use the dashboard's confirmation system
                    approveBtn.onclick = function() { 
                        // Reset approval flags
                        try { 
                            window.__mhApproveConfirmed = false; 
                            window.__mhApproveFromPrimary = true; 
                            window.__mhApprovePending = true;
                        } catch(_) {}
                        
                        window.customConfirm('Proceed to Initial Screening? (Medical History will NOT be submitted here)', function(){
                            try { window.__mhApproveConfirmed = true; } catch(_) {}
                            // Cache Medical History form data for later submission at Screening approve
                            try {
                                const mhForm = document.getElementById('modalMedicalHistoryForm');
                                if (mhForm) {
                                    const fd = new FormData(mhForm);
                                    const cache = {};
                                    fd.forEach((v, k) => { cache[k] = v; });
                                    // Ensure required approve fields are set
                                    cache['action'] = 'approve';
                                    cache['medical_approval'] = 'Approved';
                                    if (!cache['donor_id'] && window.currentDonorId) cache['donor_id'] = window.currentDonorId;
                                    window.pendingMedicalApprovalData = cache;
                                }
                            } catch(_) {}
                            // Close MH modal and open Initial Screening without submitting MH
                            try { window.__preventReturnToProfile = true; } catch(_) {}
                            try { if (typeof closeMedicalHistoryModal === 'function') closeMedicalHistoryModal(); } catch(_) {}
                            // Resolve donor id robustly from multiple sources
                            let donorId = null;
                            try {
                                const donorIdEl = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
                                if (donorIdEl && donorIdEl.value) donorId = donorIdEl.value;
                            } catch(_) {}
                            if (!donorId && window.currentDonorId) donorId = window.currentDonorId;
                            if (!donorId && window.lastDonorProfileContext && window.lastDonorProfileContext.donorId) donorId = window.lastDonorProfileContext.donorId;
                            if (!donorId && window.currentScreeningData && window.currentScreeningData.donor_form_id) donorId = window.currentScreeningData.donor_form_id;
                            if (!donorId) {
                                try {
                                    const hint = document.querySelector('#donorProfileModal [data-donor-id]');
                                    if (hint) donorId = hint.getAttribute('data-donor-id');
                                } catch(_) {}
                            }
                            if (!donorId) {
                                console.warn('Unable to resolve donor_id for Initial Screening');
                                try { window.customInfo && window.customInfo('Unable to resolve donor. Please reopen the donor and try again.'); } catch(_) {}
                                return;
                            }
                            setTimeout(function(){
                                try {
                                    if (typeof showScreeningFormModal === 'function' && donorId) {
                                        showScreeningFormModal(donorId);
                                    } else if (typeof window.openScreeningModal === 'function' && donorId) {
                                        window.openScreeningModal({ donor_id: donorId });
                                    }
                                } catch(_) {}
                            }, 200);
                        });
                    };
                } else {
                    nextButton.innerHTML = 'Next →';
                    nextButton.onclick = () => {
                        if (validateCurrentModalStep()) {
                            currentStep++;
                            updateStepDisplay();
                            errorMessage.style.display = 'none';
                        }
                    };
                    // Leave Approve button management to final step; do not remove here
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
        
        // Removed bindApproveMedicalHistoryButton - now using medical history approval system
        
                // Removed approveMedicalHistory function - now using medical history approval system
        
        // Function to handle modal form submission
        function submitModalForm(action) {
            let message = '';
            if (action === 'approve') {
                message = 'Are you sure you want to approve this donor\'s medical history?';
            } else if (action === 'decline') {
                message = 'Are you sure you want to decline this donor?';
            } else if (action === 'next') {
                message = 'Do you want to proceed to the declaration form?';
            }
            
            // Use custom confirmation instead of browser confirm
            if (window.customConfirm) {
                // Mark intent so MH hooks allow submission after user confirms
                if (action === 'approve') { try { window.__mhApproveConfirmed = false; window.__mhApproveFromPrimary = true; } catch(_) {} }
                window.customConfirm(message, function() {
                    if (action === 'approve') { try { window.__mhApproveConfirmed = true; } catch(_) {} }
                    processFormSubmission(action);
                });
            } else {
                // Fallback to browser confirm if custom confirm is not available
                if (confirm(message)) {
                    if (action === 'approve') { try { window.__mhApproveConfirmed = true; } catch(_) {} }
                    processFormSubmission(action);
                }
            }
        }
        
        // Separate function to handle the actual form submission
        function processFormSubmission(action) {
            console.log('processFormSubmission called with action:', action);
            
            // Find the action input element
            const actionElement = document.getElementById('modalSelectedAction');
            if (!actionElement) {
                console.error('modalSelectedAction element not found!');
                alert('Error: Form element not found. Please try again.');
                return;
            }
            
            actionElement.value = action;
            console.log('Action set to:', actionElement.value);
            
            // Submit the form via AJAX
            const form = document.getElementById('modalMedicalHistoryForm');
            if (!form) {
                console.error('modalMedicalHistoryForm not found!');
                alert('Error: Form not found. Please try again.');
                return;
            }
            
            const formData = new FormData(form);
            
            // Debug: Log form data to console
            console.log('Form submission data:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }
            
            // Ensure action is in FormData
            if (!formData.has('action')) {
                console.error('Action not found in FormData, adding it manually');
                formData.set('action', action);
            }
            
            // Ensure donor_id is present
            const donorIdInput = form.querySelector('input[name="donor_id"]');
            if (!donorIdInput || !donorIdInput.value) {
                console.error('Missing donor_id in form');
                alert('Error: Missing donor information. Please try again.');
                return;
            }
            
            // Ensure medical_approval is set for approve action
            if (action === 'approve') {
                formData.set('medical_approval', 'Approved');
            }
            
            makeApiCall('../../src/views/forms/medical-history-process.php', {
                method: 'POST',
                body: formData
            })
            .then(data => {
                if (data.success) {
                    if (action === 'approve') {
                        // Use ONLY the Medical History success flow to prevent duplicate calls
                        const donorIdInput = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
                        const donorId = donorIdInput ? donorIdInput.value : null;
                        
                        if (typeof window.showApprovedThenReturn === 'function') {
                            try { 
                                // Store context for success flow
                                window.lastDonorProfileContext = { donorId: donorId, screeningData: { donor_form_id: donorId } };
                                window.showApprovedThenReturn(donorId, { donor_form_id: donorId }); 
                                return; // CRITICAL: Return here to prevent duplicate calls
                            } catch (e) {
                                console.error('Error in showApprovedThenReturn:', e);
                            }
                        }
                        
                        // Fallback ONLY if showApprovedThenReturn fails
                        console.warn('showApprovedThenReturn not available, using fallback');
                        try {
                            if (typeof closeMedicalHistoryModal === 'function') {
                                closeMedicalHistoryModal();
                            } else {
                                const mhEl = document.getElementById('medicalHistoryModal');
                                if (mhEl) { mhEl.classList.remove('show'); setTimeout(()=>{ mhEl.style.display='none'; }, 150); }
                            }
                        } catch (e) { /* noop */ }
                        if (donorId && typeof openDonorProfileModal === 'function') {
                            setTimeout(() => { try { openDonorProfileModal({ donor_form_id: donorId }); } catch (e) { window.location.reload(); } }, 500);
                        } else {
                            setTimeout(() => window.location.reload(), 800);
                        }
                    } else if (action === 'next') {
                        // Move to next module as before
                        try {
                            if (typeof closeMedicalHistoryModal === 'function') {
                                closeMedicalHistoryModal();
                            } else {
                                const mhEl = document.getElementById('medicalHistoryModal');
                                if (mhEl) { mhEl.classList.remove('show'); setTimeout(()=>{ mhEl.style.display='none'; }, 150); }
                            }
                        } catch (e) { /* noop */ }
                        const donorIdInput = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
                        const donorId = donorIdInput ? donorIdInput.value : null;
                        if (donorId && typeof openDonorProfileModal === 'function') {
                            setTimeout(() => {
                                try { openDonorProfileModal({ donor_form_id: donorId }); } catch (e) { window.location.reload(); }
                            }, 500);
                        } else if (!donorId) {
                            alert('Error: Donor ID not found');
                        }
                    } else if (action === 'decline') {
                        // Close modal and refresh the main page for decline only
                        try {
                            if (typeof closeMedicalHistoryModal === 'function') {
                                closeMedicalHistoryModal();
                            } else {
                                const mhEl = document.getElementById('medicalHistoryModal');
                                if (mhEl) { mhEl.classList.remove('show'); setTimeout(()=>{ mhEl.style.display='none'; }, 150); }
                            }
                        } catch (e) { /* noop */ }
                        const donorIdInput = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
                        const donorId = donorIdInput ? donorIdInput.value : null;
                        if (donorId && typeof openDonorProfileModal === 'function') {
                            setTimeout(() => {
                                try { openDonorProfileModal({ donor_form_id: donorId }); } catch (e) { window.location.reload(); }
                            }, 500);
                        } else {
                            window.location.reload();
                        }
                    }
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the form.');
            });
        }
        
        // Fallback function to handle decline button clicks when medical history approval functions aren't loaded
        function handleDeclineClickFallback(e) {
            console.log('handleDeclineClickFallback called', e);
            e.preventDefault();
            e.stopPropagation();
            
            // Try to use the medical history approval function if available
            if (typeof showDeclineModal === 'function') {
                console.log('Using showDeclineModal function');
                showDeclineModal();
                return;
            }
            
            // Fallback: Direct modal opening
            console.log('Using direct modal opening fallback');
            const modalElement = document.getElementById('medicalHistoryDeclineModal');
            if (modalElement) {
                console.log('Opening decline modal directly');
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error('Decline modal not found');
                alert('Error: Decline modal not found. Please refresh the page.');
            }
            
            return false;
        }

        // Fallback function to handle approve button clicks when medical history approval functions aren't loaded
        function handleApproveClickFallback(e) {
            console.log('handleApproveClickFallback called', e);
            e.preventDefault();
            e.stopPropagation();
            
            // Try to use the medical history approval function if available
            if (typeof handleApproveClick === 'function') {
                console.log('Using handleApproveClick function');
                handleApproveClick(e);
                return;
            }
            
            // Fallback: Use the dashboard's approval system
            console.log('Using dashboard approval system fallback');
            try {
                window.__mhApproveConfirmed = false;
                window.__mhApproveFromPrimary = true;
                window.customConfirm('Are you sure you want to approve this donor\'s medical history?', function(){
                    window.__mhApproveConfirmed = true;
                    processFormSubmission('approve');
                });
            } catch (error) {
                console.error('Error in approve fallback:', error);
                alert('Error: Unable to process approval. Please refresh the page.');
            }
            
            return false;
        }

        // Function to show screening form modal
        function showScreeningFormModal(donorId) {
            console.log('Showing screening form modal for donor ID:', donorId);
            
            // Set donor data for the screening form
            window.currentDonorData = { donor_id: donorId };
            
            // Clean up any lingering Bootstrap backdrops and body state before opening
            try {
                document.querySelectorAll('.modal-backdrop').forEach(function(b){ b.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
                // Do not remove custom confirm/info overlays here; allow confirmation to remain visible
                // Ensure other Bootstrap modals are fully hidden
                document.querySelectorAll('.modal.show').forEach(function(m){
                    if (m.id !== 'screeningFormModal') {
                        m.classList.remove('show');
                        m.style.display = 'none';
                        m.setAttribute('aria-hidden','true');
                    }
                });
            } catch(_) {}
            
            // Debounce: avoid double-open if called rapidly from two sources
            try {
                const now = Date.now();
                if (window.__screeningOpenAt && (now - window.__screeningOpenAt) < 800) {
                    return;
                }
                window.__screeningOpenAt = now;
            } catch(_) {}

            // Show the screening form modal
            ModalManager.show('screeningFormModal');
            // Ensure proper stacking order so backdrop never overlays the dialog
            try {
                const modalEl = document.getElementById('screeningFormModal');
                if (modalEl) {
                    modalEl.style.zIndex = '1065';
                    const dlg = modalEl.querySelector('.modal-dialog');
                    if (dlg) dlg.style.zIndex = '1066';
                }
                const sweepBackdrops = () => {
                    const backs = document.querySelectorAll('.modal-backdrop');
                    // Remove ALL stale backdrops to prevent over-dimming
                    backs.forEach((b) => { try { b.remove(); } catch(_) {} });
                    document.body.classList.add('modal-open');
                    document.body.style.overflow = 'hidden';
                    document.body.style.paddingRight = '';
                };
                setTimeout(sweepBackdrops, 20);
                setTimeout(sweepBackdrops, 120);
                setTimeout(sweepBackdrops, 260);

                // Protect against any script that force-hides the screening modal for a short window
                if (modalEl) {
                    try {
                        const guardUntil = Date.now() + 6000;
                        const reassert = () => {
                            // If protection window elapsed, stop
                            if (Date.now() > guardUntil) return false;
                            // Reassert visibility and classes
                            modalEl.style.display = 'block';
                            if (!modalEl.classList.contains('show')) modalEl.classList.add('show');
                            document.body.classList.add('modal-open');
                            return true;
                        };
                        // Block Bootstrap hidden event
                        const onHidden = (ev) => {
                            if (ev && ev.target === modalEl && Date.now() <= guardUntil) {
                                console.warn('[Phys-Screening] Re-opening screening modal after hidden event');
                                ev.stopImmediatePropagation();
                                ev.preventDefault?.();
                                reassert();
                                try { (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl, { backdrop:'static', keyboard:false })).show(); } catch(_) {}
                            }
                        };
                        modalEl.addEventListener('hidden.bs.modal', onHidden, true);
                        // Observe class/style mutations that would hide it
                        const mo = new MutationObserver((muts) => {
                            if (Date.now() > guardUntil) { try { mo.disconnect(); } catch(_) {} return; }
                            const isHidden = (modalEl.style.display === 'none') || !modalEl.classList.contains('show');
                            if (isHidden) {
                                console.warn('[Phys-Screening] Prevented screening modal hide via mutation');
                                reassert();
                            }
                        });
                        mo.observe(modalEl, { attributes:true, attributeFilter:['class','style'] });
                        // Auto stop guard after window
                        setTimeout(() => { try { mo.disconnect(); modalEl.removeEventListener('hidden.bs.modal', onHidden, true); } catch(_) {} }, 6200);
                    } catch(_) {}
                }
            } catch(_) {}
            
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


        
        // Add loading functionality for data processing
        function showProcessingModal(message = 'Processing physical examination data...') {
            const loadingText = document.querySelector('#loadingModal p');
            if (loadingText) {
                loadingText.textContent = message;
            }
            ModalManager.show('loadingModal');
        }
        
        function hideProcessingModal() {
            ModalManager.hide('loadingModal');
        }
        
        // Make functions globally available
        window.showProcessingModal = showProcessingModal;
        window.hideProcessingModal = hideProcessingModal;
        
        // Show loading when physical examination forms are submitted
        document.addEventListener('submit', function(e) {
            if (e.target && (e.target.classList.contains('physical-form') || e.target.id.includes('physical'))) {
                showProcessingModal('Submitting physical examination data...');
            }
        });
        
        // Show loading for physical examination AJAX calls
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const url = args[0];
            if (typeof url === 'string' && (url.includes('physical') || url.includes('examination'))) {
                showProcessingModal('Processing physical examination...');
            }
            return originalFetch.apply(this, args).finally(() => {
                setTimeout(hideProcessingModal, 500);
            });
        };
        
        // Custom confirmation function to replace browser confirm
        if (!window.customConfirm) window.customConfirm = function(message, onConfirm) {
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
            
            // Prevent multiple confirms at the same time
            try { if (window.__confirmOverlayActive) return; } catch(_) {}
            // Remove any existing modal (stale)
            const existingModal = document.getElementById('simpleCustomModal');
            if (existingModal) {
                try { existingModal.remove(); } catch(_) {}
            }
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            try { window.__confirmOverlayActive = true; window.__confirmDismissed = false; } catch(_) {}
            // Guard the custom confirm overlay from being hidden/removed for a few seconds
            try {
                const overlay = document.getElementById('simpleCustomModal');
                const guardUntil = Date.now() + 6000;
                // Keep overlay visible and above everything
                const keepVisible = function(){
                    try {
                        if (!overlay) return;
                        overlay.style.display = 'flex';
                        overlay.style.zIndex = '99999';
                        document.body.classList.add('modal-open');
                    } catch(_) {}
                };
                keepVisible();
                // Prevent Escape from closing it
                const keyHandler = function(ev){
                    try {
                        if (Date.now() > guardUntil) { document.removeEventListener('keydown', keyHandler, true); return; }
                        if (ev.key === 'Escape' || ev.keyCode === 27) { ev.preventDefault(); ev.stopPropagation(); return false; }
                    } catch(_) {}
                };
                document.addEventListener('keydown', keyHandler, true);
                // Observe overlay for style/class mutations
                let moOverlay = new MutationObserver(() => {
                    if (Date.now() > guardUntil) { try { moOverlay.disconnect(); } catch(_) {} return; }
                    if (window.__confirmDismissed) { try { moOverlay.disconnect(); } catch(_) {} return; }
                    keepVisible();
                });
                if (overlay) moOverlay.observe(overlay, { attributes:true, attributeFilter:['class','style'] });
                // Observe body tree for accidental removal of overlay; if removed, re-append quickly
                let moBody = new MutationObserver(() => {
                    try {
                        if (Date.now() > guardUntil) { try { moBody.disconnect(); } catch(_) {} return; }
                        if (window.__confirmDismissed) { try { moBody.disconnect(); } catch(_) {} return; }
                        const exists = document.getElementById('simpleCustomModal');
                        if (!exists && overlay) {
                            console.warn('[Phys-Screening] Restoring confirmation overlay after external removal');
                            document.body.appendChild(overlay);
                            keepVisible();
                        }
                    } catch(_) {}
                });
                moBody.observe(document.body, { childList:true, subtree:true });
                // Stop guards after window
                setTimeout(() => { try { moOverlay.disconnect(); moBody.disconnect(); document.removeEventListener('keydown', keyHandler, true); } catch(_) {} }, 6200);
                // Store observers for cleanup on close
                try { overlay.__moOverlay = moOverlay; overlay.__moBody = moBody; } catch(_) {}
                // Swallow clicks outside dialog
                try {
                    overlay.addEventListener('click', function(ev){
                        try {
                            const dialog = overlay.firstElementChild;
                            if (dialog && !dialog.contains(ev.target)) { ev.preventDefault(); ev.stopPropagation(); }
                        } catch(_) {}
                    }, true);
                } catch(_) {}
            } catch(_) {}
            
            // Set up confirmation function
            window.confirmSimpleModal = function() {
                try { window.__confirmDismissed = true; } catch(_) {}
                closeSimpleModal();
                if (onConfirm) onConfirm();
            };
            
            window.closeSimpleModal = function() {
                try { window.__confirmDismissed = true; window.__confirmOverlayActive = false; } catch(_) {}
                const modal = document.getElementById('simpleCustomModal');
                if (modal) {
                    try { if (modal.__moOverlay) modal.__moOverlay.disconnect(); } catch(_) {}
                    try { if (modal.__moBody) modal.__moBody.disconnect(); } catch(_) {}
                    try { modal.remove(); } catch(_) {}
                }
            };
        };

        // Disable the Notice/Return info modal
        window.customInfo = function(message) { /* no-op per physician flow */ };
        
        // Remove risky overrides that may trigger Illegal invocation and unintended modal closes
        // Kept header styling but without mutating Element.prototype or forcing modal-open class changes
        (function(){
            try {
                const dashboardHeader = document.getElementById('physicianDashboardHeaderUnique');
                if (dashboardHeader) {
                    dashboardHeader.style.position = 'static';
                    dashboardHeader.style.left = '0px';
                    dashboardHeader.style.right = 'auto';
                    dashboardHeader.style.top = 'auto';
                    dashboardHeader.style.zIndex = 'auto';
                    dashboardHeader.style.margin = '0';
                    dashboardHeader.style.padding = '0.75rem 1rem';
                    dashboardHeader.style.width = '100%';
                    dashboardHeader.style.boxSizing = 'border-box';
                }
            } catch(_) {}
        })();

        // Link MH → Screening: after medical history approval completes, open Initial Screening
        (function(){
            try {
                if (window.__mhScreeningHooked) return; // idempotent
                window.__mhScreeningHooked = true;
                const install = function(){
                    if (typeof window.showApprovedThenReturn !== 'function') return false;
                    const __orig = window.showApprovedThenReturn;
                    // Disable automatic Screening open here to avoid double-opens; rely on MH module
                    window.showApprovedThenReturn = function(donorId, screeningData){
                        try { __orig.apply(this, arguments); } catch(_) {}
                        // Intentionally NOT opening Screening here; MH module triggers it.
                    };
                    return true;
                };
                // Try now, and again after a short delay if not yet available
                if (!install()) {
                    setTimeout(install, 500);
                    setTimeout(install, 1200);
                }

                // Remove any duplicate Approve buttons inside MH content if present
                try {
                    const container = document.getElementById('medicalHistoryModalContent') || document;
                    const approveBtns = container.querySelectorAll('button, a');
                    approveBtns.forEach(function(btn){
                        const txt = (btn.textContent || '').trim().toLowerCase();
                        if ((txt === 'approve' || txt === 'approve medical history') && btn.id !== 'modalApproveButton') {
                            if (btn.parentNode) btn.parentNode.removeChild(btn);
                        }
                    });
                } catch(_) {}
            } catch(_) {}
        })();
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>