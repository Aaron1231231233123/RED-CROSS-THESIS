<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';




// Add pagination settings
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Start timing for performance measurement
$start_time = microtime(true);
error_log("Starting blood collection submission query at " . date('H:i:s'));

// STEP 1: Get all blood collection records to identify physical exams that already have blood collection
$blood_collection_url = SUPABASE_URL . '/rest/v1/blood_collection?select=physical_exam_id,is_successful,donor_reaction,management_done,status,blood_bag_type,amount_taken,created_at';
$ch = curl_init($blood_collection_url);
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

// Check for errors in the blood collection query
if ($http_code !== 200) {
    error_log("Error fetching blood collections. HTTP code: " . $http_code);
    error_log("Response: " . $response);
    $blood_collections = [];
} else {
    $blood_collections = json_decode($response, true) ?: [];
}

// Create a lookup array of physical_exam_ids that already have blood collection
$collected_physical_exam_ids = [];
$approved_collections = [];
$declined_collections = [];
$blood_collection_data = []; // Store full blood collection data

foreach ($blood_collections as $collection) {
    if (isset($collection['physical_exam_id']) && !empty($collection['physical_exam_id'])) {
        // Normalize the physical_exam_id to string format for consistent comparison
        $exam_id = (string)$collection['physical_exam_id'];
        $collected_physical_exam_ids[] = $exam_id;
        
        // Store full collection data for results display
        $blood_collection_data[$exam_id] = $collection;
        
        // Track approved and declined collections based on is_successful
        if (isset($collection['is_successful'])) {
            if ($collection['is_successful'] === true) {
                $approved_collections[] = $exam_id;
            } else {
                $declined_collections[] = $exam_id;
            }
        }
        
        // Log the type and value for debugging
        error_log("Blood collection physical_exam_id: " . $collection['physical_exam_id'] . 
                  " (Type: " . gettype($collection['physical_exam_id']) . ")");
    }
}

// Dump the collected physical exam IDs for debugging
error_log("Physical exam IDs with blood collection: " . implode(", ", $collected_physical_exam_ids));
error_log("Found " . count($collected_physical_exam_ids) . " physical exams that already have blood collection");
error_log("Approved collections: " . count($approved_collections) . ", Declined collections: " . count($declined_collections));

// STEP 2: Get physical examination records with "Accepted" remarks only
$physical_exam_url = SUPABASE_URL . '/rest/v1/physical_examination?remarks=eq.Accepted&select=physical_exam_id,donor_id,remarks,blood_bag_type,created_at,donor_form(surname,first_name,middle_name,birthdate,age)&order=created_at.desc';
$ch = curl_init($physical_exam_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check for errors in the physical examination query
if ($http_code !== 200) {
    error_log("Error fetching physical examinations. HTTP code: " . $http_code);
    error_log("Response: " . $response);
    $physical_exams = [];
} else {
    $physical_exams = json_decode($response, true) ?: [];
}

// Log all physical exam IDs for debugging
$all_physical_exam_ids = [];
foreach ($physical_exams as $exam) {
    if (isset($exam['physical_exam_id'])) {
        $all_physical_exam_ids[] = $exam['physical_exam_id'];
    }
}
error_log("All physical exam IDs: " . implode(", ", $all_physical_exam_ids));
error_log("Found " . count($physical_exams) . " physical exams with 'Accepted' status");

// STEP 3: Filter out physical exams that already have blood collection
$available_exams = [];
foreach ($physical_exams as $exam) {
    if (isset($exam['physical_exam_id'])) {
        // Log the type and value for debugging
        error_log("Physical exam ID: " . $exam['physical_exam_id'] . 
                  " (Type: " . gettype($exam['physical_exam_id']) . ")");
        
        // Normalize to string for comparison
        $exam_id_string = (string)$exam['physical_exam_id'];
        
        // Check if this physical_exam_id is in the collected list
        if (!in_array($exam_id_string, $collected_physical_exam_ids)) {
            $available_exams[] = $exam;
            error_log("Including exam ID " . $exam['physical_exam_id'] . " (not collected yet)");
        } else {
            error_log("Excluding exam ID " . $exam['physical_exam_id'] . " (already has blood collection)");
        }
    }
}

error_log("After filtering, " . count($available_exams) . " physical exams remain available for blood collection");

// Calculate incoming count (available exams that haven't been collected yet)
$incoming_count = count($available_exams);
$approved_count = count($approved_collections);

// Calculate today's summary count (blood collections submitted today)
$today = date('Y-m-d');
$today_count = 0;
foreach ($blood_collections as $collection) {
    if (isset($collection['created_at'])) {
        $collection_date = date('Y-m-d', strtotime($collection['created_at']));
        if ($collection_date === $today) {
            $today_count++;
        }
    }
}

error_log("Final counts - Incoming: $incoming_count, Approved: $approved_count, Today: $today_count");

// Handle status filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'incoming';

// Initialize filtered screenings based on status
switch ($status_filter) {
    case 'active':
        // Get physical exam IDs with approved blood collection
        $display_exams = [];
        
        // Get the full details of approved blood collections
        $approved_url = SUPABASE_URL . '/rest/v1/blood_collection?is_successful=eq.true&select=blood_collection_id,physical_exam_id,created_at,physical_examination(donor_id,remarks,blood_bag_type,donor_form(surname,first_name))';
        $ch = curl_init($approved_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $approved_data = json_decode($response, true) ?: [];
        error_log("Fetched " . count($approved_data) . " approved blood collections");
        
        // Format the data to match our expected structure
        foreach ($approved_data as $item) {
            if (isset($item['physical_examination']) && !empty($item['physical_examination'])) {
                $exam = $item['physical_examination'];
                $exam['physical_exam_id'] = $item['physical_exam_id'];
                $exam['created_at'] = $item['created_at'];
                
                // Add blood collection data if available
                $exam_id = (string)$item['physical_exam_id'];
                if (isset($blood_collection_data[$exam_id])) {
                    $exam['blood_collection'] = $blood_collection_data[$exam_id];
                }
                
                $display_exams[] = $exam;
            }
        }
        break;
        
    case 'today':
        // Get all blood collections submitted today
        $display_exams = [];
        
        // Get blood collections from today
        $today_url = SUPABASE_URL . '/rest/v1/blood_collection?created_at=gte.' . $today . 'T00:00:00&created_at=lt.' . date('Y-m-d', strtotime($today . ' +1 day')) . 'T00:00:00&select=blood_collection_id,physical_exam_id,created_at,physical_examination(donor_id,remarks,blood_bag_type,donor_form(surname,first_name))';
        $ch = curl_init($today_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $today_data = json_decode($response, true) ?: [];
        error_log("Fetched " . count($today_data) . " blood collections from today");
        
        // Format the data to match our expected structure
        foreach ($today_data as $item) {
            if (isset($item['physical_examination']) && !empty($item['physical_examination'])) {
                $exam = $item['physical_examination'];
                $exam['physical_exam_id'] = $item['physical_exam_id'];
                $exam['created_at'] = $item['created_at'];
                
                // Add blood collection data if available
                $exam_id = (string)$item['physical_exam_id'];
                if (isset($blood_collection_data[$exam_id])) {
                    $exam['blood_collection'] = $blood_collection_data[$exam_id];
                }
                
                $display_exams[] = $exam;
            }
        }
        break;
        
    case 'incoming':
    default:
        $display_exams = $available_exams;
        break;
}

// STEP 4: Sort display exams by created_at (FIFO: oldest first)
usort($display_exams, function($a, $b) {
    $a_time = isset($a['updated_at']) ? strtotime($a['updated_at']) : (isset($a['created_at']) ? strtotime($a['created_at']) : 0);
    $b_time = isset($b['updated_at']) ? strtotime($b['updated_at']) : (isset($b['created_at']) ? strtotime($b['created_at']) : 0);
    return $a_time <=> $b_time;
});

// Prepare pagination
$total_records = count($display_exams);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Calculate the offset for this page
$offset = ($current_page - 1) * $records_per_page;

// Slice the array to get only the records for the current page
$display_exams = array_slice($display_exams, $offset, $records_per_page);

// Log execution time
$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
error_log("Query execution time: " . number_format($execution_time, 4) . " seconds");

// Debug output (remove in production)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<pre>";
    echo "Found " . count($physical_exams) . " 'Accepted' physical exams<br>";
    echo "Found " . count($collected_physical_exam_ids) . " physical exams with blood collection<br>";
    echo "Available for collection: " . count($available_exams) . " physical exams<br>";
    echo "Showing " . count($examinations) . " records on this page<br>";
    echo "</pre>";
}

// Calculate age for each physical examination
foreach ($display_exams as &$exam) {
    if (isset($exam['donor_form'])) {
        // Calculate age if not present but birthdate is available
        if (empty($exam['donor_form']['age']) && !empty($exam['donor_form']['birthdate'])) {
            $birthDate = new DateTime($exam['donor_form']['birthdate']);
            $today = new DateTime();
            $exam['donor_form']['age'] = $birthDate->diff($today)->y;
        }
    }
}
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
        }

        .nav-link.active{
            background-color: var(--active-color);
            color: white !important;
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

        /* Table Styling */
        .dashboard-staff-tables {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 0;
            overflow: hidden;
            margin-bottom: 1rem;
            table-layout: fixed;
        }
        
        .dashboard-staff-tables th:nth-child(8),
        .dashboard-staff-tables td:nth-child(8) {
            width: 120px;
        }
        
        .dashboard-staff-tables th:nth-child(9),
        .dashboard-staff-tables td:nth-child(9) {
            width: 120px;
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
        
        .badge.bg-success {
            background-color: #28a745 !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            font-weight: 600;
            border-radius: 4px;
        }
        
        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            font-weight: 600;
            border-radius: 4px;
        }
        
        .badge.bg-secondary {
            background-color: #6c757d !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            font-weight: 600;
            border-radius: 4px;
        }
        
        .badge.bg-danger {
            background-color: #dc3545 !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            font-weight: 600;
            border-radius: 4px;
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

        /* Enhanced badge styling for blood collection results */
        .badge.bg-success {
            background-color: #28a745 !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-danger {
            background-color: #dc3545 !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-secondary {
            background-color: #6c757d !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body class="light-mode">
    <div class="container-fluid p-0">
        <!-- Header -->
        <div class="dashboard-home-header">
            <h4 class="header-title">Phlebotomist Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
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
                                Physical Exam Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'phlebotomist'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-blood-collection-submission.php">
                                Blood Collection 
                                Queue
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard-staff-history.php">Donor History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../assets/php_func/logout.php">Logout</a>
                    </li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <div class="content-wrapper">
                    <div class="welcome-section">
                        <h2 class="welcome-title">Welcome, Phlebotomist!</h2>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="dashboard-staff-status">
                        <a href="?status=incoming" class="status-card <?php echo (!isset($_GET['status']) || $_GET['status'] === 'incoming') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $incoming_count; ?></p>
                            <p class="dashboard-staff-title">Incoming Blood Collection</p>
                        </a>
                        <a href="?status=active" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'active') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $approved_count; ?></p>
                            <p class="dashboard-staff-title">Active Blood Collections</p>
                        </a>
                        <a href="?status=today" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'today') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $today_count; ?></p>
                            <p class="dashboard-staff-title">Today's Summary</p>
                        </a>
                    </div>
                    
                    <h5 class="section-header">Blood Collection Records</h5>
                    
                    <!-- Search Bar -->
                    <div class="search-container">
                        <input type="text" 
                            class="form-control" 
                            id="searchInput" 
                            placeholder="Search donors...">
                    </div>
                    
                    <hr class="red-separator">
                    
                   
                    <div class="table-responsive">
                        <?php if (!empty($debug_info)): ?>
                            <div class="alert alert-info">
                                <?php echo htmlspecialchars($debug_info); ?>
                            </div>
                        <?php endif; ?>
                        
                        <table class="dashboard-staff-tables table-hover">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Date</th>
                                    <th>SURNAME</th>
                                    <th>First Name</th>
                                    <th>Status</th>
                                    <th>Result</th>
                                    <th>Declaration</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="bloodCollectionTableBody">
                                <?php
                                if (!empty($display_exams)) {
                                    $counter = ($current_page - 1) * $records_per_page + 1; // Initialize counter with pagination
                                    foreach ($display_exams as $exam) {
                                        $donor = $exam['donor_form'] ?? [];
                                        $created_date = isset($exam['created_at']) ? date('F d, Y', strtotime($exam['created_at'])) : 'N/A';
                                    $surname = $donor['surname'] ?? '';
                                    $firstName = $donor['first_name'] ?? '';
                                    
                                    // Determine status and result based on blood collection data
                                    $status_badge = '';
                                    $result_badge = '';
                                    $declaration_badge = '<span class="badge bg-success">Success</span>'; // Always success
                                    
                                    // Check if blood collection data exists
                                    $exam_id = (string)($exam['physical_exam_id'] ?? '');
                                    $has_collection_data = isset($blood_collection_data[$exam_id]);
                                    
                                    if ($has_collection_data) {
                                        $collection = $blood_collection_data[$exam_id];
                                        
                                        // Status based on is_successful
                                        if (isset($collection['is_successful'])) {
                                            if ($collection['is_successful'] === true) {
                                                $status_badge = '<span class="badge bg-success">Success</span>';
                                            } else {
                                                $status_badge = '<span class="badge bg-danger">Failed</span>';
                                            }
                                        } else {
                                            $status_badge = '<span class="badge bg-warning">In Progress</span>';
                                        }
                                        
                                        // Result based on collection details - using database fields
                                        if (isset($collection['is_successful']) && $collection['is_successful'] === true) {
                                            // Use status field if available, otherwise default to "Collected"
                                            $result_text = !empty($collection['status']) ? ucfirst($collection['status']) : 'Collected';
                                            $result_badge = '<span class="badge bg-success">' . $result_text . '</span>';
                                        } elseif (isset($collection['is_successful']) && $collection['is_successful'] === false) {
                                            // Use donor_reaction if available, otherwise "Failed"
                                            $result_text = !empty($collection['donor_reaction']) ? ucfirst($collection['donor_reaction']) : 'Failed';
                                            $result_badge = '<span class="badge bg-danger">' . $result_text . '</span>';
                                        } else {
                                            // Use status field or default to "Processing"
                                            $result_text = !empty($collection['status']) ? ucfirst($collection['status']) : 'Processing';
                                            $result_badge = '<span class="badge bg-warning">' . $result_text . '</span>';
                                        }
                                    } else {
                                        // No collection data yet
                                        $status_badge = '<span class="badge bg-secondary">Pending</span>';
                                        $result_badge = '<span class="badge bg-secondary">N/A</span>';
                                    }
                                    
                                    $data_exam = htmlspecialchars(json_encode([
                                        'donor_id' => $exam['donor_id'] ?? '',
                                        'physical_exam_id' => $exam['physical_exam_id'] ?? '',
                                        'created_at' => $exam['created_at'] ?? '',
                                        'surname' => $surname,
                                        'first_name' => $firstName
                                    ]));
                                    
                                    echo "<tr class='clickable-row' data-examination='{$data_exam}'>
                                        <td>{$counter}</td>
                                        <td>{$created_date}</td>
                                        <td>" . htmlspecialchars($surname) . "</td>
                                        <td>" . htmlspecialchars($firstName) . "</td>
                                        <td>{$status_badge}</td>
                                        <td>{$result_badge}</td>
                                        <td>{$declaration_badge}</td>
                                        <td>
                                            <button type='button' class='btn btn-info btn-sm view-donor-btn me-1' data-donor-id='{$exam['donor_id']}' title='View Details'>
                                                <i class='fas fa-eye'></i>
                                            </button>
                                            <button type='button' class='btn btn-warning btn-sm edit-donor-btn' data-donor-id='{$exam['donor_id']}' title='Edit'>
                                                <i class='fas fa-edit'></i>
                                            </button>
                                        </td>
                                    </tr>";
                                    $counter++; // Increment counter for next row
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-center">No blood collection records found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Blood collection navigation">
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
                    <div class="modal-headers">Do you want to continue?</div>
                    <div class="modal-actions">
                        <button class="modal-button cancel-action" id="cancelButton">No</button>
                        <button class="modal-button confirm-action" id="confirmButton">Yes</button>
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
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border-radius: 15px 15px 0 0;">
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
                    <button type="button" class="btn btn-danger px-4" onclick="proceedToDonorForm()">Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
                <div class="modal-body text-center">
                    <div class="spinner-border text-danger" style="width: 3.5rem; height: 3.5rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-white mt-3 mb-0">Please wait...</p>
                </div>
            </div>
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
            const bloodCollectionTableBody = document.getElementById('bloodCollectionTableBody');
            let currentCollectionData = null;

            // Store original rows for search reset
            const originalRows = Array.from(bloodCollectionTableBody.getElementsByTagName('tr'));

            // Attach click event to all rows
            function attachRowClickHandlers() {
                document.querySelectorAll(".clickable-row").forEach(row => {
                    row.addEventListener("click", function() {
                        currentCollectionData = JSON.parse(this.dataset.examination);
                        console.log("Selected physical exam data:", currentCollectionData); // Debug log
                        confirmationDialog.classList.remove("hide");
                        confirmationDialog.classList.add("show");
                        confirmationDialog.style.display = "block";
                    });
                });
            }

            attachRowClickHandlers();

            // Close Modal Function
            function closeModal() {
                confirmationDialog.classList.remove("show");
                confirmationDialog.classList.add("hide");
                setTimeout(() => {
                    confirmationDialog.style.display = "none";
                }, 300);
            }

            // Yes Button (Triggers Loading Spinner & Redirects)
            confirmButton.addEventListener("click", function() {
                if (!currentCollectionData) {
                    console.error('No collection data available');
                    return;
                }

                closeModal();
                loadingSpinner.style.display = "block";
                
                // Create a form to POST donor_id and physical_exam_id to blood-collection-form.php
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../../src/views/forms/blood-collection-form.php';

                // Add donor_id and physical_exam_id as hidden inputs
                const donorIdInput = document.createElement('input');
                donorIdInput.type = 'hidden';
                donorIdInput.name = 'donor_id';
                donorIdInput.value = currentCollectionData.donor_id;

                const physicalExamIdInput = document.createElement('input');
                physicalExamIdInput.type = 'hidden';
                physicalExamIdInput.name = 'physical_exam_id';
                physicalExamIdInput.value = currentCollectionData.physical_exam_id;
                
                // Add role_id=3 (staff) as hidden input
                const roleIdInput = document.createElement('input');
                roleIdInput.type = 'hidden';
                roleIdInput.name = 'role_id';
                roleIdInput.value = '3';
                
                // Add a flag indicating we're coming from the staff dashboard
                const fromDashboardInput = document.createElement('input');
                fromDashboardInput.type = 'hidden';
                fromDashboardInput.name = 'from_dashboard';
                fromDashboardInput.value = 'staff_blood_collection';

                form.appendChild(donorIdInput);
                form.appendChild(physicalExamIdInput);
                form.appendChild(roleIdInput);
                form.appendChild(fromDashboardInput);
                document.body.appendChild(form);

                // Only submit this form to open the blood collection form for user input
                form.submit();
            });

            // No Button (Closes Modal)
            cancelButton.addEventListener("click", closeModal);

            // Search functionality
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const category = searchCategory.value;
                
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
                            'surname': 1,
                            'firstname': 2,
                            'remarks': 3,
                            'blood_bag_type': 4
                        }[category];

                        if (columnIndex !== undefined) {
                            const cellText = cells[columnIndex].textContent.toLowerCase();
                            shouldShow = cellText.includes(searchTerm);
                        }
                    }

                    row.style.display = shouldShow ? '' : 'none';
                });
            }

            // Update placeholder based on selected category
            searchCategory.addEventListener('change', function() {
                const category = this.value;
                let placeholder = 'Search by ';
                switch(category) {
                    case 'surname': placeholder += 'surname...'; break;
                    case 'firstname': placeholder += 'first name...'; break;
                    case 'remarks': placeholder += 'physical exam remarks...'; break;
                    case 'blood_bag_type': placeholder += 'blood bag type...'; break;
                    default: placeholder = 'Search records...';
                }
                searchInput.placeholder = placeholder;
                performSearch();
            });

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
            searchCategory.addEventListener('change', debouncedSearch);
        });
    </script>
</body>
</html>