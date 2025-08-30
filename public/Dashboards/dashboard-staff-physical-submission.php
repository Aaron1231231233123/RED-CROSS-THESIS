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
    header("Location: /REDCROSS/public/login.php");
    exit();
}

require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Debug logging to help troubleshoot
error_log("Starting dashboard-staff-physical-submission.php");

// 1. First, get all screening records
try {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=screening_id,created_at,blood_type,donation_type,donor_form_id,disapproval_reason,needs_review&order=created_at.desc');

    $headers = array(
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log("CURL error in screening form fetch");
        $all_screenings = [];
    } else {
        $all_screenings = json_decode($response, true) ?: [];
    }
} catch (Exception $e) {
    error_log("Error fetching screening records: " . $e->getMessage());
    $all_screenings = [];
}

// 2b. Get eligibility records to classify donors as New or Returning
try {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?select=donor_id');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        error_log("CURL error in eligibility fetch");
        $eligibility_records = [];
    } else {
        $eligibility_records = json_decode($response, true) ?: [];
    }
} catch (Exception $e) {
    error_log("Error fetching eligibility records: " . $e->getMessage());
    $eligibility_records = [];
}
$eligibility_by_donor = [];
foreach ($eligibility_records as $er) {
    if (isset($er['donor_id'])) {
        $eligibility_by_donor[(int)$er['donor_id']] = true;
    }
}

// 2. Get all physical examination records with full details
try {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,screening_id,donor_id,remarks,disapproval_reason,gen_appearance,heart_and_lungs,skin,reason,blood_pressure,pulse_rate,body_temp,blood_bag_type,created_at,updated_at,needs_review,physician&order=created_at.desc');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        error_log("CURL error in physical examination fetch");
        $physical_exams = [];
    } else {
        $physical_exams = json_decode($response, true) ?: [];
    }
} catch (Exception $e) {
    error_log("Error fetching physical examination records: " . $e->getMessage());
    $physical_exams = [];
}

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
    try {
        $donor_ids_str = implode(',', $all_donor_ids);
        $ch_donors = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name&donor_id=in.(' . $donor_ids_str . ')');
        curl_setopt($ch_donors, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_donors, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $donors_response = curl_exec($ch_donors);
        curl_close($ch_donors);
        
        if ($donors_response === false) {
            error_log("CURL error in donor data fetch");
            $donors_data = [];
        } else {
            $donors_data = json_decode($donors_response, true) ?: [];
        }
        
        // Create a cache for quick donor lookup
        foreach ($donors_data as $donor) {
            if (isset($donor['donor_id'])) {
                $donor_data_cache[$donor['donor_id']] = $donor;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching donor data: " . $e->getMessage());
        $donors_data = [];
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
    <script src="../../assets/js/physical_examination_modal.js"></script>
    <style>
:root {
            --bg-color: #f5f5f5;
            --text-color: #000;
            --sidebar-bg: #ffffff;
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
            margin-left: 16.66666667%;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
        }
        
        .header-title {
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            flex-grow: 1;
        }
        
        .header-date {
            color: #777;
            font-size: 0.8rem;
            margin-left: 0.5rem;
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
            margin-left: auto;
            font-size: 0.9rem;
        }

        /* Sidebar Styles */
        .sidebar {
            background: var(--sidebar-bg);
            height: 100vh;
            padding: 1rem;
            position: fixed;
            top: 0;
            left: 0;
            width: 16.66666667%;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            border-right: 1px solid #e0e0e0;
        }

        .sidebar h4 {
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #dee2e6;
            color: #000;
            font-weight: bold;
        }

        .sidebar .nav-link {
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0;
            transition: all 0.3s ease;
            color: #000 !important;
            text-decoration: none;
            border-left: 5px solid transparent;
        }

        .sidebar .nav-link:hover,
        .sidebar  {
            background: var(--hover-bg);
            color: var(--active-color) !important;
            border-left-color: var(--active-color);
            border-radius: 4px !important;
        }

        .nav-link.active{
            background-color: var(--active-color);
            color: white !important;
            border-radius: 4px !important;
        }

        /* Main Content */
        .main-content {
            padding: 1rem;
            margin-left: 16.66666667%;
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
        /* Confirmation Modal */
        .confirmation-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            text-align: center;
            z-index: 9999;
            border-radius: 10px;
            width: 300px;
            display: none;
            opacity: 0;
        }

        /* Fade-in and Fade-out Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translate(-50%, -55%); }
            to { opacity: 1; transform: translate(-50%, -50%); }
        }

        @keyframes fadeOut {
            from { opacity: 1; transform: translate(-50%, -50%); }
            to { opacity: 0; transform: translate(-50%, -55%); }
        }

        .confirmation-modal.show {
            display: block;
            animation: fadeIn 0.3s ease-in-out forwards;
        }

        .confirmation-modal.hide {
            animation: fadeOut 0.3s ease-in-out forwards;
        }

        .modal-headers {
            font-size: 18px;
            font-weight: bold;
            color: #d9534f;
            margin-bottom: 15px;
        }

        .modal-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .modal-button {
            width: 45%;
            padding: 10px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        .cancel-action {
            background: #aaa;
            color: white;
        }

        .cancel-action:hover {
            background: #888;
        }

        .confirm-action {
            background: #d9534f;
            color: white;
        }

        .confirm-action:hover {
            background: #c9302c;
        }

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

        /* Defer Modal Styling */
        .deferral-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .deferral-card {
            position: relative;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: #ffffff;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .deferral-card:hover {
            border-color: #dc3545;
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.15);
        }

        .deferral-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .deferral-label {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            cursor: pointer;
            width: 100%;
            margin: 0;
            transition: all 0.3s ease;
        }

        .deferral-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(220, 53, 69, 0.1);
            margin-right: 16px;
            font-size: 1.4rem;
            transition: all 0.3s ease;
        }

        .deferral-content {
            flex: 1;
        }

        .deferral-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 4px;
        }

        .deferral-desc {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.3;
        }

        .deferral-card:has(input:checked) {
            border-color: #dc3545;
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.2);
        }

        .deferral-card:has(input:checked) .deferral-icon {
            background: #dc3545;
            color: white;
            transform: scale(1.1);
        }

        .deferral-card:has(input:checked) .deferral-title {
            color: #dc3545;
        }

        /* Duration Options Styling */
        .duration-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }

        .duration-quick-options {
            margin-bottom: 16px;
        }

        .duration-option {
            background: #ffffff;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .duration-option:hover {
            border-color: #007bff;
            background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.15);
        }

        .duration-option.active {
            border-color: #007bff;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
        }

        .duration-number {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .duration-unit {
            font-size: 0.85rem;
            font-weight: 500;
            opacity: 0.8;
        }

        .custom-option {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            border-color: #6c757d;
        }

        .custom-option:hover {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
            border-color: #495057;
        }

        .custom-option.active {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            border-color: #28a745;
        }

        /* Custom Duration Input Styling */
        .custom-duration-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-left: 4px solid #28a745;
        }

        .custom-duration-container .input-group {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .custom-duration-container .form-control {
            border-radius: 8px 0 0 8px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .custom-duration-container .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .custom-duration-container .input-group-text {
            background: #28a745;
            color: white;
            border: 2px solid #28a745;
            border-radius: 0 8px 8px 0;
            font-weight: 600;
        }

        /* Custom styling for duration section */
        #durationSection {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease;
        }

        #durationSection.show {
            opacity: 1;
            max-height: 500px;
        }

        #customDurationSection {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        #customDurationSection.show {
            opacity: 1;
            max-height: 200px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Duration summary styling */
        #durationSummary {
            border-left: 4px solid #17a2b8;
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            animation: fadeIn 0.4s ease;
        }

        #durationSummary #summaryText {
            font-weight: 600;
            color: #0c5460;
        }

        /* Modal form controls */
        .modal-body .form-control, .modal-body .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            padding: 12px 16px;
        }

        .modal-body .form-control:focus, .modal-body .form-select:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        /* Toast notification for defer actions */
        .defer-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.4s ease;
            border-left: 5px solid #dc3545;
            min-width: 300px;
        }

        .defer-toast.show {
            transform: translateX(0);
        }

        .defer-toast-success {
            border-left-color: #28a745;
        }

        .defer-toast-error {
            border-left-color: #dc3545;
        }

        .defer-toast-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .defer-toast-content i {
            font-size: 1.3rem;
        }

        .defer-toast-success i {
            color: #28a745;
        }

        .defer-toast-error i {
            color: #dc3545;
        }

        .defer-toast-text {
            flex: 1;
        }

        .defer-toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }

        .defer-toast-message {
            font-size: 0.85rem;
            color: #6c757d;
            line-height: 1.3;
        }

        /* Ensure defer modal appears above physical examination modal */
        #deferDonorModal {
            z-index: 10050 !important;
        }

        #deferDonorModal .modal-backdrop {
            z-index: 10049 !important;
        }

        .modal-backdrop.show {
            z-index: 10049 !important;
        }

        /* Mobile Responsive Styles for Defer Modal */
        @media (max-width: 768px) {
            .deferral-card {
                margin-bottom: 8px;
            }
            
            .deferral-label {
                padding: 12px 16px;
            }
            
            .deferral-icon {
                width: 40px;
                height: 40px;
                margin-right: 12px;
                font-size: 1.2rem;
            }
            
            .deferral-title {
                font-size: 1rem;
            }
            
            .deferral-desc {
                font-size: 0.85rem;
            }
            
            .duration-container {
                padding: 16px;
            }
            
            .duration-option {
                height: 70px;
                padding: 12px 8px;
            }
            
            .duration-number {
                font-size: 1.2rem;
            }
            
            .duration-unit {
                font-size: 0.8rem;
            }
            
            .custom-duration-container {
                padding: 16px;
            }
            
            .custom-duration-container .form-control {
                font-size: 1rem;
                padding: 10px 12px;
            }
        }

        @media (max-width: 576px) {
            #deferDonorModal .modal-dialog {
                margin: 0.5rem;
            }
            
            #deferDonorModal .modal-content {
                border-radius: 12px;
            }
            
            .duration-quick-options .row {
                margin: 0 -4px;
            }
            
            .duration-quick-options .col-6 {
                padding: 0 4px;
                margin-bottom: 8px;
            }
            
            .deferral-label {
                flex-direction: column;
                text-align: center;
                padding: 16px 12px;
            }
            
            .deferral-icon {
                margin-right: 0;
                margin-bottom: 8px;
            }
        }

        /* Global Button Styling */
        .btn {
            border-radius: 4px !important;
        }
        
        /* Donor type colored text (no badge) */
        .type-new { color: #2e7d32; font-weight: 700; }
        .type-returning { color: #1976d2; font-weight: 700; }
    </style>
</head>
<body class="light-mode">
    <div class="container-fluid p-0">
        <!-- Header -->
        <div class="dashboard-home-header">
            <h4 class="header-title">Physician Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
            <button class="register-btn" onclick="showConfirmationModal()">
                Register Donor
            </button>
        </div>

        <div class="row g-0">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4>Staff</h4>
                <ul class="nav flex-column">
                    
                <?php if ($user_staff_roles === 'interviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-donor-submission.php">
                                System Registration
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_staff_roles === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-medical-history-submissions.php">
                                New Donor
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'physician'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-physical-submission.php">
                                Physical Examination Queue
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'phlebotomist'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-blood-collection-submission.php">
                                Blood Collection Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../../assets/php_func/logout.php">Logout</a>
                    </li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
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
                                                $is_editable = $is_pending || $needs_review_flag;
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
                <!-- Confirmation Modal -->
                <div class="confirmation-modal" id="confirmationDialog">
                    <div class="modal-headers">Continue with pending physical examination?</div>
                    <div class="modal-actions">
                        <button class="modal-button cancel-action" id="cancelButton">Cancel</button>
                        <button class="modal-button confirm-action" id="confirmButton">Proceed</button>
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

    <!-- Defer Donor Modal -->
    <div class="modal fade" id="deferDonorModal" tabindex="-1" aria-labelledby="deferDonorModalLabel" aria-hidden="true" style="z-index: 10050;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title" id="deferDonorModalLabel">
                        <i class="fas fa-ban me-2"></i>
                        Defer Donor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <form id="deferDonorForm">
                        <input type="hidden" id="defer-donor-id" name="donor_id">
                        <input type="hidden" id="defer-screening-id" name="screening_id">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Specify reason for deferral and duration.</label>
                        </div>

                        <!-- Deferral Type Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold mb-3">Deferral Type *</label>
                            <div class="deferral-options">
                                <div class="deferral-card" data-type="temporary">
                                    <input class="form-check-input" type="radio" name="deferral_type" id="tempDefer" value="Temporary Deferral" required>
                                    <label class="deferral-label" for="tempDefer">
                                        <div class="deferral-icon text-primary">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="deferral-content">
                                            <div class="deferral-title">Temporary Deferral</div>
                                            <div class="deferral-desc">Donor can donate after specified period</div>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="deferral-card" data-type="permanent">
                                    <input class="form-check-input" type="radio" name="deferral_type" id="permDefer" value="Permanent Deferral" required>
                                    <label class="deferral-label" for="permDefer">
                                        <div class="deferral-icon text-danger">
                                            <i class="fas fa-ban"></i>
                                        </div>
                                        <div class="deferral-content">
                                            <div class="deferral-title">Permanent Deferral</div>
                                            <div class="deferral-desc">Donor cannot donate in the future</div>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="deferral-card" data-type="refuse">
                                    <input class="form-check-input" type="radio" name="deferral_type" id="refuse" value="Refuse" required>
                                    <label class="deferral-label" for="refuse">
                                        <div class="deferral-icon text-warning">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="deferral-content">
                                            <div class="deferral-title">Refuse</div>
                                            <div class="deferral-desc">Reject donation for this session only</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Duration Selection (only for Temporary Deferral) -->
                        <div class="mb-4 duration-container" id="durationSection" style="display: none;">
                            <label class="form-label fw-semibold mb-3">
                                <i class="fas fa-calendar-alt me-2 text-primary"></i>Deferral Duration *
                            </label>
                            
                            <!-- Quick Duration Options -->
                            <div class="duration-quick-options mb-3">
                                <div class="row g-2">
                                    <div class="col-6 col-md-3">
                                        <div class="duration-option" data-days="7">
                                            <div class="duration-number">7</div>
                                            <div class="duration-unit">Days</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="duration-option" data-days="14">
                                            <div class="duration-number">14</div>
                                            <div class="duration-unit">Days</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="duration-option" data-days="30">
                                            <div class="duration-number">1</div>
                                            <div class="duration-unit">Month</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="duration-option" data-days="90">
                                            <div class="duration-number">3</div>
                                            <div class="duration-unit">Months</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="duration-option" data-days="180">
                                            <div class="duration-number">6</div>
                                            <div class="duration-unit">Months</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="duration-option" data-days="365">
                                            <div class="duration-number">1</div>
                                            <div class="duration-unit">Year</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="duration-option custom-option" data-days="custom">
                                            <div class="duration-number"><i class="fas fa-edit"></i></div>
                                            <div class="duration-unit">Custom</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hidden select for form submission -->
                            <select class="form-select d-none" id="deferralDuration" name="duration">
                                <option value="">Select duration...</option>
                                <option value="7">7 days</option>
                                <option value="14">14 days</option>
                                <option value="21">21 days</option>
                                <option value="30">1 month (30 days)</option>
                                <option value="60">2 months (60 days)</option>
                                <option value="90">3 months (90 days)</option>
                                <option value="180">6 months (180 days)</option>
                                <option value="365">1 year (365 days)</option>
                                <option value="custom">Custom duration...</option>
                            </select>
                        </div>

                        <!-- Custom Duration Input -->
                        <div class="mb-4 custom-duration-container" id="customDurationSection" style="display: none;">
                            <label for="customDuration" class="form-label fw-semibold">
                                <i class="fas fa-keyboard me-2 text-primary"></i>Custom Duration (days) *
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="customDuration" name="custom_duration" min="1" max="3650" placeholder="Enter number of days">
                                <span class="input-group-text">days</span>
                            </div>
                            <div class="form-text">Enter duration between 1 and 3650 days (approximately 10 years)</div>
                        </div>

                        <!-- Disapproval Reason -->
                        <div class="mb-4">
                            <label for="disapprovalReason" class="form-label fw-semibold">Disapproval Reason *</label>
                            <textarea class="form-control" id="disapprovalReason" name="disapproval_reason" rows="4" 
                                    placeholder="Please provide detailed reason for deferral..." required></textarea>
                        </div>

                        <!-- Duration Summary Display -->
                        <div class="alert alert-info" id="durationSummary" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Deferral Summary:</strong> <span id="summaryText"></span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn px-4" style="background-color: #b22222; border-color: #b22222; color: white;" id="submitDeferral">
                        <i class="fas fa-ban me-2"></i>Submit Deferral
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Physical Examination Modal -->
    <div class="physical-examination-modal" id="physicalExaminationModal">
        <div class="physical-modal-content">
            <div class="physical-modal-header">
                <h3><i class="fas fa-stethoscope me-2"></i>Physical Examination Form</h3>
                <button type="button" class="physical-close-btn" onclick="physicalExaminationModal.closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Progress Indicator -->
            <div class="physical-progress-container">
                <div class="physical-progress-steps">
                    <div class="physical-step active" data-step="1">
                        <div class="physical-step-number">1</div>
                        <div class="physical-step-label">Initial Screening</div>
                    </div>
                    <div class="physical-step" data-step="2">
                        <div class="physical-step-number">2</div>
                        <div class="physical-step-label">Vital Signs</div>
                    </div>
                    <div class="physical-step" data-step="3">
                        <div class="physical-step-number">3</div>
                        <div class="physical-step-label">Examination</div>
                    </div>
                    <div class="physical-step" data-step="4">
                        <div class="physical-step-number">4</div>
                        <div class="physical-step-label">Blood Bag</div>
                    </div>
                    <div class="physical-step" data-step="5">
                        <div class="physical-step-number">5</div>
                        <div class="physical-step-label">Review</div>
                    </div>
                </div>
                <div class="physical-progress-line">
                    <div class="physical-progress-fill"></div>
                </div>
            </div>

            <form id="physicalExaminationForm" class="physical-modal-form">
                <input type="hidden" id="physical-donor-id" name="donor_id">
                <input type="hidden" id="physical-screening-id" name="screening_id">

                <!-- Step 1: Initial Screening Summary -->
                <div class="physical-step-content active" id="physical-step-1">
                    <div class="physical-step-inner">
                        <h4>Step 1: Initial Screening Summary</h4>
                        <p class="text-muted">Review of screening information and donor details</p>
                        
                        <div class="initial-screening-container">
                            <!-- Header Section with Key Info -->
                                                          <div class="screening-header-info">
                                  <div class="row g-3">
                                     <div class="col-md-8">
                                          <div class="donor-info-primary">
                                              <div class="d-flex align-items-center mb-2">
                                                  <h5 class="donor-name-display me-3" id="donor-name">Loading...</h5>
                                              </div>
                                              <div class="donor-basic-details mb-2">
                                                  <span class="info-badge" id="donor-age">-</span>
                                                  <span class="info-badge" id="donor-sex">-</span>
                                                  <span class="info-badge blood-type-badge" id="donor-blood-type">-</span>
                                              </div>
                                          </div>
                                      </div>
                                                                              <div class="col-md-4">
                                            <div class="donor-info-right">
                                                <div class="screening-date-badge mb-2">
                                                    <i class="fas fa-calendar-alt me-2"></i>
                                                    <span class="date-label">Date Screened:</span>
                                                    <span id="screening-date">-</span>
                                                </div>
                                                <div class="donor-id-display">
                                                    <strong>Donor ID:</strong> <span id="donor-id">-</span>
                                                </div>
                                            </div>
                                        </div>
                                  </div>
                            </div>
                            
                            <!-- Main Information Grid -->
                            <div class="screening-info-grid">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="info-section">
                                            <div class="section-title">
                                                <i class="fas fa-vial me-2"></i>
                                                Screening Results
                                            </div>
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="label">Donation Type</span>
                                                    <span class="value" id="donation-type">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="label">Body Weight</span>
                                                    <span class="value" id="body-weight">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="label">Specific Gravity</span>
                                                    <span class="value" id="specific-gravity">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="info-section info-section-right">
                                            <div class="section-title">
                                                <i class="fas fa-address-book me-2"></i>
                                                Contact & Background
                                            </div>
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="label">Civil Status</span>
                                                    <span class="value" id="donor-civil-status">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="label">Mobile</span>
                                                    <span class="value" id="donor-mobile">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="label">Occupation</span>
                                                    <span class="value" id="donor-occupation">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Address Section -->
                                <div class="address-section">
                                    <div class="section-title">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        Address
                                    </div>
                                    <div class="address-display" id="donor-address">-</div>
                                </div>
                                
                                <!-- Ready Status -->
                                <div class="exam-status-section">
                                    <div class="status-verification">
                                        <i class="fas fa-check-circle status-icon"></i>
                                        <span class="status-text">Ready for Physical Examination</span>
                                        <span class="status-note">Screening completed successfully. Proceed with vital signs and physical examination.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Vital Signs -->
                <div class="physical-step-content" id="physical-step-2">
                    <div class="physical-step-inner">
                        <h4>Step 2: Vital Signs</h4>
                        <p class="text-muted">Please enter the patient's vital signs</p>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="physical-blood-pressure" class="form-label">Blood Pressure *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="physical-blood-pressure" 
                                       name="blood_pressure" 
                                       placeholder="e.g., 120/80" 
                                       pattern="[0-9]{2,3}/[0-9]{2,3}" 
                                       title="Format: systolic/diastolic e.g. 120/80" 
                                       required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="physical-pulse-rate" class="form-label">Pulse Rate (BPM) *</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="physical-pulse-rate" 
                                       name="pulse_rate" 
                                       placeholder="BPM" 
                                       min="40" 
                                       max="200" 
                                       required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="physical-body-temp" class="form-label">Body Temperature (°C) *</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="physical-body-temp" 
                                       name="body_temp" 
                                       placeholder="°C" 
                                       step="0.1" 
                                       min="35" 
                                       max="42" 
                                       required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Physical Examination -->
                <div class="physical-step-content" id="physical-step-3">
                    <div class="physical-step-inner">
                        <h4>Step 3: Physical Examination</h4>
                        <p class="text-muted">Please enter examination findings</p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="physical-gen-appearance" class="form-label">General Appearance *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="physical-gen-appearance" 
                                       name="gen_appearance" 
                                       placeholder="Enter observation" 
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="physical-skin" class="form-label">Skin *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="physical-skin" 
                                       name="skin" 
                                       placeholder="Enter observation" 
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="physical-heent" class="form-label">HEENT *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="physical-heent" 
                                       name="heent" 
                                       placeholder="Enter observation" 
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="physical-heart-lungs" class="form-label">Heart and Lungs *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="physical-heart-lungs" 
                                       name="heart_and_lungs" 
                                       placeholder="Enter observation" 
                                       required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Blood Bag Selection -->
                <div class="physical-step-content" id="physical-step-4">
                    <div class="physical-step-inner">
                        <h4>Step 4: Blood Bag Selection</h4>
                        <p class="text-muted">Please select the appropriate blood bag type</p>
                        
                        <div class="physical-blood-bag-section">
                            <div class="physical-blood-bag-options">
                                <label class="physical-option-card">
                                    <input type="radio" name="blood_bag_type" value="Single">
                                    <div class="physical-option-content">
                                        <i class="fas fa-square"></i>
                                        <span>Single</span>
                                    </div>
                                </label>
                                <label class="physical-option-card">
                                    <input type="radio" name="blood_bag_type" value="Multiple">
                                    <div class="physical-option-content">
                                        <i class="fas fa-th"></i>
                                        <span>Multiple</span>
                                    </div>
                                </label>
                                <label class="physical-option-card">
                                    <input type="radio" name="blood_bag_type" value="Top & Bottom">
                                    <div class="physical-option-content">
                                        <i class="fas fa-align-justify"></i>
                                        <span>Top & Bottom</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Review and Submit -->
                <div class="physical-step-content" id="physical-step-5">
                    <div class="physical-step-inner">
                        <h4>Step 5: Review & Submit</h4>
                        <p class="text-muted">Please review all information before submitting</p>
                        
                        <div class="examination-report">
                            <!-- Header Section -->
                            <div class="report-header">
                                <div class="report-title">
                                    <h5>Physical Examination Report</h5>
                                    <div class="report-meta">
                                        <span class="report-date"><?php echo date('F j, Y'); ?></span>
                                        <span class="report-physician">Physician: <span id="summary-interviewer">-</span></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Vital Signs Section -->
                            <div class="report-section">
                                <div class="section-header">
                                    <i class="fas fa-heartbeat"></i>
                                    <span>Vital Signs</span>
                                </div>
                                <div class="section-content">
                                    <div class="vital-signs-grid">
                                        <div class="vital-item">
                                            <span class="vital-label">Blood Pressure:</span>
                                            <span class="vital-value" id="summary-blood-pressure">-</span>
                                        </div>
                                        <div class="vital-item">
                                            <span class="vital-label">Pulse Rate:</span>
                                            <span class="vital-value" id="summary-pulse-rate">-</span>
                                            <span class="vital-unit">BPM</span>
                                        </div>
                                        <div class="vital-item">
                                            <span class="vital-label">Temperature:</span>
                                            <span class="vital-value" id="summary-body-temp">-</span>
                                            <span class="vital-unit">°C</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Physical Examination Section -->
                            <div class="report-section">
                                <div class="section-header">
                                    <i class="fas fa-user-md"></i>
                                    <span>Physical Examination Findings</span>
                                </div>
                                <div class="section-content">
                                    <div class="examination-findings">
                                        <div class="finding-row">
                                            <span class="finding-label">General Appearance:</span>
                                            <span class="finding-value" id="summary-gen-appearance">-</span>
                                        </div>
                                        <div class="finding-row">
                                            <span class="finding-label">Skin:</span>
                                            <span class="finding-value" id="summary-skin">-</span>
                                        </div>
                                        <div class="finding-row">
                                            <span class="finding-label">HEENT:</span>
                                            <span class="finding-value" id="summary-heent">-</span>
                                        </div>
                                        <div class="finding-row">
                                            <span class="finding-label">Heart and Lungs:</span>
                                            <span class="finding-value" id="summary-heart-lungs">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Assessment & Conclusion -->
                            <div class="report-section">
                                <div class="section-header">
                                    <i class="fas fa-clipboard-check"></i>
                                    <span>Assessment & Conclusion</span>
                                </div>
                                <div class="section-content">
                                    <div class="assessment-content">
                                        <div class="assessment-result">
                                            <span class="result-label">Medical Assessment:</span>
                                            <span class="result-value">Accepted for Blood Collection</span>
                                        </div>
                                        <div class="assessment-collection">
                                            <span class="collection-label">Blood Collection:</span>
                                            <span class="collection-value" id="summary-blood-bag">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Signature Section -->
                            <div class="report-signature">
                                <div class="signature-content">
                                    <div class="signature-line">
                                        <span>Examining Physician</span>
                                        <span class="physician-name"><?php 
                                            // Get the logged-in user's name from the users table
                                            if (isset($_SESSION['user_id'])) {
                                                $user_id = $_SESSION['user_id'];
                                                $ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=first_name,surname&user_id=eq.' . $user_id);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                                    'apikey: ' . SUPABASE_API_KEY,
                                                    'Authorization: Bearer ' . SUPABASE_API_KEY
                                                ]);
                                                
                                                $response = curl_exec($ch);
                                                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                                curl_close($ch);
                                                
                                                if ($http_code === 200) {
                                                    $user_data = json_decode($response, true);
                                                    if (is_array($user_data) && !empty($user_data)) {
                                                        $user = $user_data[0];
                                                        $first_name = isset($user['first_name']) ? htmlspecialchars($user['first_name']) : '';
                                                        $surname = isset($user['surname']) ? htmlspecialchars($user['surname']) : '';
                                                        
                                                        if ($first_name && $surname) {
                                                            echo $first_name . ' ' . $surname;
                                                        } elseif ($first_name) {
                                                            echo $first_name;
                                                        } elseif ($surname) {
                                                            echo $surname;
                                                        } else {
                                                            echo 'Physician';
                                                        }
                                                    } else {
                                                        echo 'Physician';
                                                    }
                                                } else {
                                                    echo 'Physician';
                                                }
                                            } else {
                                                echo 'Physician';
                                            }
                                        ?></span>
                                    </div>
                                    <div class="signature-note">
                                        This examination was conducted in accordance with Philippine Red Cross standards and protocols.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Navigation -->
                <div class="physical-modal-footer">
                    <div class="physical-nav-buttons">
                        <button type="button" class="btn btn-outline-secondary physical-cancel-btn">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-outline-danger physical-defer-btn">
                            <i class="fas fa-ban me-2"></i>Defer Donor
                        </button>
                        <button type="button" class="btn btn-outline-danger physical-prev-btn" style="display: none;">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </button>
                        <button type="button" class="btn physical-next-btn" style="background-color: #b22222; border-color: #b22222; color: white;">
                            <i class="fas fa-arrow-right me-2"></i>Next
                        </button>
                        <button type="button" class="btn btn-success physical-submit-btn" style="display: none;">
                            <i class="fas fa-check me-2"></i>Submit Physical Examination
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <script>
        
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
            let confirmationDialog = document.getElementById("confirmationDialog");
            let loadingSpinner = document.getElementById("loadingSpinner");
            let cancelButton = document.getElementById("cancelButton");
            let confirmButton = document.getElementById("confirmButton");
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            const screeningTableBody = document.getElementById('screeningTableBody');
            let currentScreeningData = null;

            // Store original rows for search reset
            const originalRows = Array.from(screeningTableBody.getElementsByTagName('tr'));

            // Attach click event to view buttons
            function attachButtonClickHandlers() {
                document.querySelectorAll(".view-btn").forEach(button => {
                    button.addEventListener("click", function(e) {
                        e.stopPropagation(); // Prevent row click
                        try {
                            currentScreeningData = JSON.parse(this.getAttribute('data-screening'));
                            console.log("Selected screening:", currentScreeningData);
                            
                            confirmationDialog.classList.remove("hide");
                            confirmationDialog.classList.add("show");
                            confirmationDialog.style.display = "block";
                        } catch (e) {
                            console.error("Error parsing screening data:", e);
                            alert("Error selecting this record. Please try again.");
                        }
                    });
                });

                // Attach click event to edit buttons
                document.querySelectorAll(".edit-btn").forEach(button => {
                    button.addEventListener("click", function(e) {
                        e.stopPropagation(); // Prevent row click
                        try {
                            currentScreeningData = JSON.parse(this.getAttribute('data-screening'));
                            console.log("Selected record for editing:", currentScreeningData);
                            
                            confirmationDialog.classList.remove("hide");
                            confirmationDialog.classList.add("show");
                            confirmationDialog.style.display = "block";
                        } catch (e) {
                            console.error("Error parsing screening data:", e);
                            alert("Error selecting this record. Please try again.");
                        }
                    });
                });


            }

            attachButtonClickHandlers();

            // Close Modal Function
            function closeModal() {
                confirmationDialog.classList.remove("show");
                confirmationDialog.classList.add("hide");
                setTimeout(() => {
                    confirmationDialog.style.display = "none";
                }, 300);
            }

            // Yes Button (Triggers Physical Examination Modal)
            confirmButton.addEventListener("click", function() {
                closeModal();
                
                // Allow proceeding if we have donor_form_id and either screening_id or physical_exam_id
                if (!currentScreeningData || !currentScreeningData.donor_form_id || (!currentScreeningData.screening_id && !currentScreeningData.physical_exam_id)) {
                    console.error("Missing required screening data");
                    alert("Error: Missing required screening data. Please try again.");
                    return;
                }
                
                // Open the Physical Examination modal
                console.log("Opening Physical Examination modal with data:", currentScreeningData);
                if (window.physicalExaminationModal) {
                    window.physicalExaminationModal.openModal(currentScreeningData);
                    
                    // Initialize defer button after modal opens
                    initializePhysicalExamDeferButton();
                } else {
                    console.error("Physical examination modal not initialized");
                    alert("Error: Modal not properly initialized. Please refresh the page.");
                }
            });

            // No Button (Closes Modal)
            cancelButton.addEventListener("click", closeModal);

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

            // Defer modal will be initialized when opened
        });

        // Physical Examination Modal Defer Functionality
        function initializePhysicalExamDeferButton() {
            // Use a timeout to ensure the modal is fully loaded
            setTimeout(() => {
                const physicalDeferBtn = document.querySelector('.physical-defer-btn');
                console.log('Looking for defer button:', physicalDeferBtn);
                
                if (physicalDeferBtn) {
                    // Remove any existing event listeners
                    physicalDeferBtn.removeEventListener('click', handleDeferClick);
                    
                    // Add the event listener
                    physicalDeferBtn.addEventListener('click', handleDeferClick);
                    console.log('Defer button initialized successfully');
                } else {
                    console.error('Physical defer button not found in DOM');
                }
            }, 500);
        }

        function handleDeferClick(e) {
            e.preventDefault();
            console.log('Defer button clicked');
            
            // Get current screening data from the physical examination modal
            const donorId = document.getElementById('physical-donor-id')?.value;
            const screeningId = document.getElementById('physical-screening-id')?.value;
            
            console.log('Donor ID:', donorId, 'Screening ID:', screeningId);
            
            if (!donorId || !screeningId) {
                showDeferToast('Error', 'Unable to get donor information. Please try again.', 'error');
                return;
            }
            
            const screeningData = {
                donor_form_id: donorId,
                screening_id: screeningId
            };
            
            openDeferModal(screeningData);
        }

        // Defer Modal Functions
        function openDeferModal(screeningData) {
            // Set the hidden fields
            document.getElementById('defer-donor-id').value = screeningData.donor_form_id || '';
            document.getElementById('defer-screening-id').value = screeningData.screening_id || '';
            
            // Reset form
            document.getElementById('deferDonorForm').reset();
            
            // Hide conditional sections
            const durationSection = document.getElementById('durationSection');
            const customDurationSection = document.getElementById('customDurationSection');
            
            durationSection.classList.remove('show');
            customDurationSection.classList.remove('show');
            durationSection.style.display = 'none';
            customDurationSection.style.display = 'none';
            document.getElementById('durationSummary').style.display = 'none';
            
            // Reset all visual elements
            document.querySelectorAll('.deferral-card').forEach(card => {
                card.classList.remove('active');
            });
            
            document.querySelectorAll('.duration-option').forEach(option => {
                option.classList.remove('active');
            });
            
            // Reset custom duration display
            const customOption = document.querySelector('.duration-option[data-days="custom"]');
            if (customOption) {
                const numberDiv = customOption.querySelector('.duration-number');
                numberDiv.innerHTML = '<i class="fas fa-edit"></i>';
                const unitDiv = customOption.querySelector('.duration-unit');
                unitDiv.textContent = 'Custom';
            }
            
            // Clear any validation states
            document.querySelectorAll('.form-control').forEach(control => {
                control.classList.remove('is-invalid', 'is-valid');
            });
            
            // Show the modal
            const deferModal = new bootstrap.Modal(document.getElementById('deferDonorModal'));
            deferModal.show();
            
            // Re-initialize defer modal functionality when it opens
            setTimeout(() => {
                initializeDeferModal();
            }, 200);
        }

        function initializeDeferModal() {
            const deferralTypeRadios = document.querySelectorAll('input[name="deferral_type"]');
            const durationSection = document.getElementById('durationSection');
            const customDurationSection = document.getElementById('customDurationSection');
            const durationSelect = document.getElementById('deferralDuration');
            const customDurationInput = document.getElementById('customDuration');
            const submitBtn = document.getElementById('submitDeferral');
            const durationSummary = document.getElementById('durationSummary');
            const summaryText = document.getElementById('summaryText');
            const durationOptions = document.querySelectorAll('.duration-option');

            // Handle deferral type change
            deferralTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    console.log('Deferral type changed to:', this.value);
                    if (this.value === 'Temporary Deferral') {
                        durationSection.style.display = 'block';
                        setTimeout(() => {
                            durationSection.classList.add('show');
                            durationSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }, 50);
                    } else {
                        durationSection.classList.remove('show');
                        customDurationSection.classList.remove('show');
                        setTimeout(() => {
                            if (!durationSection.classList.contains('show')) {
                                durationSection.style.display = 'none';
                            }
                            if (!customDurationSection.classList.contains('show')) {
                                customDurationSection.style.display = 'none';
                            }
                        }, 400);
                        durationSummary.style.display = 'none';
                        // Clear duration selections
                        durationOptions.forEach(opt => opt.classList.remove('active'));
                        durationSelect.value = '';
                        customDurationInput.value = '';
                    }
                    updateSummary();
                });
            });

            // Also handle clicks on the deferral cards directly
            document.querySelectorAll('.deferral-card').forEach(card => {
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        // Trigger the change event
                        radio.dispatchEvent(new Event('change'));
                    }
                });
            });

            // Handle duration option clicks
            durationOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove active class from all options
                    durationOptions.forEach(opt => opt.classList.remove('active'));
                    
                    // Add active class to clicked option
                    this.classList.add('active');
                    
                    const days = this.getAttribute('data-days');
                    
                    if (days === 'custom') {
                        durationSelect.value = 'custom';
                        customDurationSection.style.display = 'block';
                        setTimeout(() => {
                            customDurationSection.classList.add('show');
                            customDurationSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            customDurationInput.focus();
                        }, 50);
                    } else {
                        durationSelect.value = days;
                        customDurationSection.classList.remove('show');
                        setTimeout(() => {
                            if (!customDurationSection.classList.contains('show')) {
                                customDurationSection.style.display = 'none';
                            }
                        }, 300);
                        customDurationInput.value = '';
                    }
                    
                    updateSummary();
                });
            });

            // Handle custom duration input
            customDurationInput.addEventListener('input', function() {
                updateSummary();
                
                // Update the custom option to show the entered value
                const customOption = document.querySelector('.duration-option[data-days="custom"]');
                if (customOption && this.value) {
                    const numberDiv = customOption.querySelector('.duration-number');
                    numberDiv.innerHTML = this.value;
                    const unitDiv = customOption.querySelector('.duration-unit');
                    unitDiv.textContent = this.value == 1 ? 'Day' : 'Days';
                } else if (customOption) {
                    const numberDiv = customOption.querySelector('.duration-number');
                    numberDiv.innerHTML = '<i class="fas fa-edit"></i>';
                    const unitDiv = customOption.querySelector('.duration-unit');
                    unitDiv.textContent = 'Custom';
                }
            });

            // Handle form submission
            submitBtn.addEventListener('click', function() {
                if (validateDeferForm()) {
                    submitDeferral();
                }
            });

            function updateSummary() {
                const selectedType = document.querySelector('input[name="deferral_type"]:checked');
                const durationValue = durationSelect.value;
                const customDuration = customDurationInput.value;
                
                if (!selectedType) {
                    durationSummary.style.display = 'none';
                    return;
                }

                let summaryMessage = '';
                
                if (selectedType.value === 'Temporary Deferral') {
                    let days = 0;
                    if (durationValue && durationValue !== 'custom') {
                        days = parseInt(durationValue);
                    } else if (durationValue === 'custom' && customDuration) {
                        days = parseInt(customDuration);
                    }
                    
                    if (days > 0) {
                        const endDate = new Date();
                        endDate.setDate(endDate.getDate() + days);
                        
                        const dayText = days === 1 ? 'day' : 'days';
                        summaryMessage = `Donor will be deferred for ${days} ${dayText} until ${endDate.toLocaleDateString()}.`;
                    }
                } else if (selectedType.value === 'Permanent Deferral') {
                    summaryMessage = 'Donor will be permanently deferred from future donations.';
                } else if (selectedType.value === 'Refuse') {
                    summaryMessage = 'Donor donation will be refused for this session.';
                }

                if (summaryMessage) {
                    summaryText.textContent = summaryMessage;
                    durationSummary.style.display = 'block';
                } else {
                    durationSummary.style.display = 'none';
                }
            }
        }

        function validateDeferForm() {
            const selectedType = document.querySelector('input[name="deferral_type"]:checked');
            const durationValue = document.getElementById('deferralDuration').value;
            const customDuration = document.getElementById('customDuration').value;
            const disapprovalReason = document.getElementById('disapprovalReason').value.trim();

            if (!selectedType) {
                showDeferToast('Validation Error', 'Please select a deferral type.', 'error');
                // Scroll to deferral type section
                document.querySelector('.deferral-options').scrollIntoView({ behavior: 'smooth' });
                return false;
            }

            if (selectedType.value === 'Temporary Deferral') {
                if (!durationValue) {
                    showDeferToast('Validation Error', 'Please select a duration for temporary deferral.', 'error');
                    document.getElementById('durationSection').scrollIntoView({ behavior: 'smooth' });
                    return false;
                }
                
                if (durationValue === 'custom' && (!customDuration || customDuration < 1)) {
                    showDeferToast('Validation Error', 'Please enter a valid custom duration (minimum 1 day).', 'error');
                    document.getElementById('customDuration').focus();
                    return false;
                }

                if (durationValue === 'custom' && customDuration > 3650) {
                    showDeferToast('Validation Error', 'Custom duration cannot exceed 3650 days (10 years).', 'error');
                    document.getElementById('customDuration').focus();
                    return false;
                }
            }

            if (!disapprovalReason) {
                showDeferToast('Validation Error', 'Please provide a reason for the deferral.', 'error');
                document.getElementById('disapprovalReason').scrollIntoView({ behavior: 'smooth' });
                document.getElementById('disapprovalReason').focus();
                return false;
            }

            if (disapprovalReason.length < 10) {
                showDeferToast('Validation Error', 'Please provide a more detailed reason (minimum 10 characters).', 'error');
                document.getElementById('disapprovalReason').focus();
                return false;
            }

            return true;
        }

        function submitDeferral() {
            const formData = new FormData(document.getElementById('deferDonorForm'));
            
            // Calculate final duration
            const selectedType = document.querySelector('input[name="deferral_type"]:checked').value;
            let finalDuration = null;
            
            if (selectedType === 'Temporary Deferral') {
                const durationValue = document.getElementById('deferralDuration').value;
                if (durationValue === 'custom') {
                    finalDuration = document.getElementById('customDuration').value;
                } else {
                    finalDuration = durationValue;
                }
            }

            // Prepare data for submission
            const submitData = {
                donor_id: formData.get('donor_id'),
                screening_id: formData.get('screening_id'),
                deferral_type: selectedType,
                duration: finalDuration,
                disapproval_reason: formData.get('disapproval_reason'),
                action: 'create_eligibility_defer'
            };

            // Show loading state
            const submitBtn = document.getElementById('submitDeferral');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;

            // Submit to backend
            fetch('../../assets/php_func/create_eligibility.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(submitData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    showDeferToast('Success', 'Deferral has been successfully recorded.', 'success');
                    
                    // Close defer modal
                    const deferModal = bootstrap.Modal.getInstance(document.getElementById('deferDonorModal'));
                    deferModal.hide();
                    
                    // Close physical examination modal if it's open
                    const physicalModal = document.getElementById('physicalExaminationModal');
                    if (physicalModal && physicalModal.style.display !== 'none') {
                        if (window.physicalExaminationModal) {
                            window.physicalExaminationModal.closeModal();
                        }
                    }
                    
                    // Refresh the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showDeferToast('Error', data.message || 'Failed to record deferral.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showDeferToast('Error', 'An error occurred while processing the deferral.', 'error');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        function showDeferToast(title, message, type = 'success') {
            // Remove existing toasts
            document.querySelectorAll('.defer-toast').forEach(toast => {
                toast.remove();
            });

            // Create toast element
            const toast = document.createElement('div');
            toast.className = `defer-toast defer-toast-${type}`;
            
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.innerHTML = `
                <div class="defer-toast-content">
                    <i class="${icon}"></i>
                    <div class="defer-toast-text">
                        <div class="defer-toast-title">${title}</div>
                        <div class="defer-toast-message">${message}</div>
                    </div>
                </div>
            `;

            // Add to page
            document.body.appendChild(toast);

            // Show toast
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            // Auto-hide toast
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 400);
            }, 4000);
        }
        
        // Add loading functionality for data processing
        function showProcessingModal(message = 'Processing physical examination data...') {
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
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>