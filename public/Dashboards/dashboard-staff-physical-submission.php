<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Debug logging to help troubleshoot
error_log("Starting dashboard-staff-physical-submission.php");

// 1. First, get all screening records
$ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=screening_id,created_at,blood_type,donation_type,donor_form_id,disapproval_reason&order=created_at.desc');

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

// Debug logging
error_log("Screening fetch response code: " . $http_code);
error_log("Screening response sample: " . substr($response, 0, 200) . "...");

$all_screenings = json_decode($response, true) ?: [];

// 2. Get all physical examination records to determine which donor_ids to exclude
$ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=donor_id,remarks');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug logging
error_log("Physical examination fetch response code: " . $http_code);
error_log("Physical examination response: " . $response);

$physical_exams = json_decode($response, true) ?: [];

// Create array of donor_ids that already have physical exams
$existing_physical_exam_donor_ids = [];
$pending_physical_exam_donor_ids = [];

foreach ($physical_exams as $exam) {
    if (isset($exam['donor_id'])) {
        // Check if this physical exam has "pending" remarks
        if (isset($exam['remarks']) && strtolower($exam['remarks']) === 'pending') {
            // Add to pending list
            $pending_physical_exam_donor_ids[] = $exam['donor_id'];
            error_log("Found pending physical examination for donor_id: " . $exam['donor_id']);
        } else {
            // Add to regular exclude list for completed exams
            $existing_physical_exam_donor_ids[] = $exam['donor_id'];
            error_log("Found completed physical examination for donor_id: " . $exam['donor_id']);
        }
    }
}

if (empty($existing_physical_exam_donor_ids) && empty($pending_physical_exam_donor_ids)) {
    error_log("WARNING: No physical examination records found with donor_ids. This could be normal if no exams exist yet.");
}

error_log("Found " . count($existing_physical_exam_donor_ids) . " donors that have completed physical exams");
error_log("Found " . count($pending_physical_exam_donor_ids) . " donors that have pending physical exams");
error_log("Found " . count($all_screenings) . " total screening records before filtering");

// 3. Process and filter the screenings
$filtered_screenings = [];
$pending_exam_screenings = [];
$skipped_count = 0;
foreach ($all_screenings as $screening) {
    // Skip if not valid array data
    if (!is_array($screening)) {
        error_log("Skipping invalid screening record (not an array)");
        continue;
    }
    
    // Skip records that have disapproval reasons
    if (!empty($screening['disapproval_reason'])) {
        error_log("Skipping screening ID " . ($screening['screening_id'] ?? 'unknown') . " - has disapproval reason: " . $screening['disapproval_reason']);
        $skipped_count++;
        continue;
    }
    
    // Get donor information for this screening record
    if (isset($screening['donor_form_id'])) {
        $donor_id = $screening['donor_form_id'];
        
        // Determine if this donor has a pending physical exam
        $has_pending_exam = in_array($donor_id, $pending_physical_exam_donor_ids);
        
        // Skip if donor already has a completed physical exam
        if (in_array($donor_id, $existing_physical_exam_donor_ids)) {
            error_log("Skipping donor ID " . $donor_id . " - already has completed physical exam");
            $skipped_count++;
            continue;
        }
        
        // Fetch donor data
        $ch_donor = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=surname,first_name,middle_name&donor_id=eq.' . $donor_id);
        curl_setopt($ch_donor, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_donor, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $donor_response = curl_exec($ch_donor);
        curl_close($ch_donor);
        
        $donor_data = json_decode($donor_response, true) ?: [];
        
        if (!empty($donor_data)) {
            $screening['donor_form'] = $donor_data[0];
            error_log("Added donor data for screening " . $screening['screening_id'] . ": " . json_encode($donor_data[0]));
        } else {
            error_log("No donor data found for ID " . $donor_id);
            $screening['donor_form'] = [
                'surname' => 'Unknown',
                'first_name' => 'Unknown',
                'middle_name' => ''
            ];
        }
        
        // Add status flag to mark records with pending exams
        $screening['has_pending_exam'] = $has_pending_exam;
        
        // Add the screening to the appropriate list
        if ($has_pending_exam) {
            $pending_exam_screenings[] = $screening;
        } else {
            $filtered_screenings[] = $screening;
        }
    }
}

error_log("Filtered to " . count($filtered_screenings) . " screenings without physical exams");
error_log("Skipped " . $skipped_count . " screening records that already have physical exams or are disapproved");

// Count stats for dashboard cards
$incoming_count = count($filtered_screenings); // Screenings without physical exams
$approved_count = 0;
$declined_count = 0;

// Count approved and declined based on physical exam remarks
foreach ($physical_exams as $exam) {
    if (isset($exam['remarks'])) {
        $remarks = strtolower($exam['remarks']);
        if ($remarks == 'accepted') {
            $approved_count++;
        } else if ($remarks == 'temporarily deferred' || $remarks == 'permanently deferred' || $remarks == 'refused') {
            $declined_count++;
        }
    }
}

error_log("Card stats - Incoming: $incoming_count, Approved: $approved_count, Declined: $declined_count");

// Handle pagination
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Handle status filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'incoming';
$show_pending = true;

// Create arrays for different status categories
$approved_donors = [];
$declined_donors = [];

// Filter physical exams by status
foreach ($physical_exams as $exam) {
    if (isset($exam['donor_id']) && isset($exam['remarks'])) {
        $remarks = strtolower($exam['remarks']);
        
        if ($remarks == 'accepted') {
            $approved_donors[] = $exam['donor_id'];
        } else if ($remarks == 'temporarily deferred' || $remarks == 'permanently deferred' || $remarks == 'refused') {
            $declined_donors[] = $exam['donor_id'];
        }
    }
}

// Initialize arrays based on the selected filter
switch ($status_filter) {
    case 'approved':
        // Show only approved physical exams
        $display_screenings = [];
        // Get screening records for approved donors
        foreach ($all_screenings as $screening) {
            if (isset($screening['donor_form_id']) && in_array($screening['donor_form_id'], $approved_donors)) {
                // Fetch donor info and add it to the screening record
                $ch_donor = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=surname,first_name,middle_name&donor_id=eq.' . $screening['donor_form_id']);
                curl_setopt($ch_donor, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_donor, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]);
                
                $donor_response = curl_exec($ch_donor);
                curl_close($ch_donor);
                
                $donor_data = json_decode($donor_response, true) ?: [];
                
                if (!empty($donor_data)) {
                    $screening['donor_form'] = $donor_data[0];
                } else {
                    $screening['donor_form'] = [
                        'surname' => 'Unknown',
                        'first_name' => 'Unknown',
                        'middle_name' => ''
                    ];
                }
                
                $display_screenings[] = $screening;
            }
        }
        break;
        
    case 'declined':
        // Show only declined physical exams
        $display_screenings = [];
        // Get screening records for declined donors
        foreach ($all_screenings as $screening) {
            if (isset($screening['donor_form_id']) && in_array($screening['donor_form_id'], $declined_donors)) {
                // Fetch donor info and add it to the screening record
                $ch_donor = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=surname,first_name,middle_name&donor_id=eq.' . $screening['donor_form_id']);
                curl_setopt($ch_donor, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_donor, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]);
                
                $donor_response = curl_exec($ch_donor);
                curl_close($ch_donor);
                
                $donor_data = json_decode($donor_response, true) ?: [];
                
                if (!empty($donor_data)) {
                    $screening['donor_form'] = $donor_data[0];
                } else {
                    $screening['donor_form'] = [
                        'surname' => 'Unknown',
                        'first_name' => 'Unknown',
                        'middle_name' => ''
                    ];
                }
                
                $display_screenings[] = $screening;
            }
        }
        break;
        
    case 'incoming':
    default:
        // Default to showing incoming records (no physical exams)
        $display_screenings = $filtered_screenings;
        break;
}

// Sort screenings by updated_at (FIFO: oldest first)
usort($display_screenings, function($a, $b) {
    $a_time = isset($a['updated_at']) ? strtotime($a['updated_at']) : (isset($a['created_at']) ? strtotime($a['created_at']) : 0);
    $b_time = isset($b['updated_at']) ? strtotime($b['updated_at']) : (isset($b['created_at']) ? strtotime($b['created_at']) : 0);
    return $a_time <=> $b_time;
});

$total_records = count($display_screenings);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Calculate the offset for this page
$offset = ($current_page - 1) * $records_per_page;

// Slice the array to get only the records for the current page
$screenings = array_slice($display_screenings, $offset, $records_per_page);

// Debug info message
$debug_info = "";
if (empty($filtered_screenings)) {
    $debug_info = "No records without physical examinations found.";
} else {
    $debug_info = "Showing " . count($filtered_screenings) . " screenings that require physical examinations.";
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
    </style>
</head>
<body class="light-mode">
    <div class="container-fluid p-0">
        <!-- Header -->
        <div class="dashboard-home-header">
            <h4 class="header-title">Staff Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
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
                                Donor Interviews Submissions
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_staff_roles === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-medical-history-submissions.php">
                                Donor Medical Interview Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'physician'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-physical-submission.php">
                                Physical Exams Submissions
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
                        <h2 class="welcome-title">Welcome, Blood Bank Reviewer!</h2>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="dashboard-staff-status">
                        <a href="?status=incoming" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'incoming') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $incoming_count; ?></p>
                            <p class="dashboard-staff-title">Incoming Registrations</p>
                        </a>
                        <a href="?status=approved" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'approved') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $approved_count; ?></p>
                            <p class="dashboard-staff-title">Approved</p>
                        </a>
                        <a href="?status=declined" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'declined') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $declined_count; ?></p>
                            <p class="dashboard-staff-title">Declined</p>
                        </a>
                    </div>
                    
                    <h5 class="section-header">Donation Records</h5>
                    
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
                                    <th>#</th>
                                    <th>Donation Date</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Blood Type</th>
                                </tr>
                            </thead>
                            <tbody id="screeningTableBody">
                                <?php 
                                if (!empty($screenings)) {
                                    $counter = ($current_page - 1) * $records_per_page + 1; // Initialize counter with pagination
                                    foreach ($screenings as $screening) {
                                        // Format the full name
                                        $surname = isset($screening['donor_form']['surname']) ? $screening['donor_form']['surname'] : '';
                                        $firstName = isset($screening['donor_form']['first_name']) ? $screening['donor_form']['first_name'] : '';
                                        $middleName = isset($screening['donor_form']['middle_name']) ? $screening['donor_form']['middle_name'] : '';
                                        
                                        // Format the date
                                        $date = isset($screening['created_at']) ? date('Y-m-d', strtotime($screening['created_at'])) : 'Unknown';
                                        
                                        // Blood type and donation type
                                        $bloodType = isset($screening['blood_type']) ? $screening['blood_type'] : 'Unknown';
                                        
                                        // Use the regular clickable-row class instead of pending-exam
                                        ?>
                                        <tr class="clickable-row" data-screening='<?php echo htmlspecialchars(json_encode([
                                            'screening_id' => $screening['screening_id'] ?? '',
                                            'donor_form_id' => $screening['donor_form_id'] ?? '',
                                            'has_pending_exam' => $screening['has_pending_exam'] ?? false
                                        ]), JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($date); ?></td>
                                            <td><?php echo htmlspecialchars($surname); ?></td>
                                            <td><?php echo htmlspecialchars($firstName); ?></td>
                                            <td><?php echo htmlspecialchars($bloodType); ?></td>
                                        </tr>
                                <?php 
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">No pending physical exams found</td></tr>';
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
            const screeningTableBody = document.getElementById('screeningTableBody');
            let currentScreeningData = null;

            // Store original rows for search reset
            const originalRows = Array.from(screeningTableBody.getElementsByTagName('tr'));

            // Attach click event to all rows
            function attachRowClickHandlers() {
                document.querySelectorAll(".clickable-row").forEach(row => {
                    row.addEventListener("click", function() {
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
                closeModal();
                loadingSpinner.style.display = "block";
                
                if (!currentScreeningData || !currentScreeningData.screening_id || !currentScreeningData.donor_form_id) {
                    console.error("Missing required screening data");
                    alert("Error: Missing required screening data. Please try again.");
                    loadingSpinner.style.display = "none";
                    return;
                }
                
                // Use direct redirection instead of form submission
                setTimeout(() => {
                    loadingSpinner.style.display = "none";
                    
                    console.log("Redirecting to screening page");
                    // Direct navigation using window.location with query parameters
                    window.location.href = '../../src/views/forms/medical-history.php' + 
                        '?screening_id=' + encodeURIComponent(currentScreeningData.screening_id) + 
                        '&donor_id=' + encodeURIComponent(currentScreeningData.donor_form_id) + 
                        '&t=' + new Date().getTime(); // Add timestamp to prevent caching
                }, 1000);
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