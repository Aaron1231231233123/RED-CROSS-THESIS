<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/check_account_hospital_modal.php';

// Function to fetch ALL blood requests from Supabase (no filtering)
function fetchBloodRequests($user_id) {
    $ch = curl_init();
    
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    
    // Fetch ALL requests for the user, ordered by requested_on descending
    $url = SUPABASE_URL . '/rest/v1/blood_requests?user_id=eq.' . $user_id . '&order=requested_on.desc&select=*';
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    
    $data = json_decode($response, true);
    
    // Debug: Log the actual data structure
    error_log("Debug - Dashboard fetchBloodRequests response: " . print_r($data, true));
    if (!empty($data) && is_array($data)) {
        error_log("Debug - First record when_needed: " . print_r($data[0]['when_needed'] ?? 'NOT_FOUND', true));
    }
    
    return $data;
}

// Function to calculate summary statistics
function calculateSummaryStats($blood_requests) {
    $stats = [
        'pending' => 0,
        'approved' => 0,
        'declined' => 0,
        'completed' => 0
    ];
    
    if (!empty($blood_requests)) {
        foreach ($blood_requests as $request) {
            $status = $request['status'];
            
            // Handle status mapping as per requirements
            if ($status === 'Pending') {
                $stats['pending']++;
            } elseif ($status === 'Approved' || $status === 'Printed') {
                $stats['approved']++;
            } elseif ($status === 'Declined') {
                $stats['declined']++;
            } elseif ($status === 'Completed') {
                $stats['completed']++;
            }
        }
    }
    
    return $stats;
}

// Fetch blood requests for the current user
$blood_requests = fetchBloodRequests($_SESSION['user_id']);

// Calculate summary statistics
$summary_stats = calculateSummaryStats($blood_requests);

// Collect handed over request IDs for notification logic
$handed_over_ids = [];
if (!empty($blood_requests) && is_array($blood_requests)) {
    foreach ($blood_requests as $req) {
        if (($req['status'] ?? '') === 'Handed_over') {
            $handed_over_ids[] = $req['request_id'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Request Dashboard</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Signature Pad Library -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
    <style>
        /* General Body Styling */
        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        /* Red Cross Theme Colors */
        :root {
            --redcross-red: #941022;
            --redcross-dark: #7a0c1c;
            --redcross-light-red: #b31b2c;
            --redcross-gray: #6c757d;
            --redcross-light: #f8f9fa;
        }

         /* Button Styling */
        .btn-danger {
            background-color: var(--redcross-red);
            border-color: var(--redcross-red);
            color: white;
        }

        .btn-danger:hover {
            background-color: var(--redcross-dark);
            border-color: var(--redcross-dark);
            color: white;
        }
        /* Themed success (for submit button on request form) */
        .btn-success {
            background-color: #198754;
            border-color: #198754;
            color: #fff;
        }
        .btn-success:hover {
            background-color: #146c43;
            border-color: #146c43;
            color: #fff;
        }

        /* Request form theming */
        #bloodRequestModal .modal-body label.form-label {
            font-weight: 600;
            color: #2c3e50;
        }
        #bloodRequestModal .form-control, #bloodRequestModal .form-select {
            border-radius: 8px;
        }
        #bloodRequestModal .table thead th {
            background-color: #941022;
            color: #fff;
        }
        #bloodRequestModal .modal-content {
            border-radius: 12px;
        }

        /* Stepper */
        #bloodRequestModal .stepper {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 15px;
        }
        #bloodRequestModal .step {
            flex: 1;
            text-align: center;
            padding: 8px 10px;
            border-radius: 20px;
            background: #f1f3f5;
            color: #6c757d;
            font-weight: 600;
            font-size: .85rem;
        }
        #bloodRequestModal .step.active { background: #941022; color: #fff; }
        #bloodRequestModal .form-step { display: none; }
        #bloodRequestModal .form-step.active { display: block; }

        /* View Modal (Referral Blood Shipment Record) spacing and layout */
        #bloodReorderModal .modal-dialog { max-width: 900px; }
        #bloodReorderModal .modal-body { padding: 28px; }
        #bloodReorderModal h5, #bloodReorderModal h6 { margin-bottom: 10px; }
        #bloodReorderModal .mb-4 { margin-bottom: 1.25rem !important; }
        #bloodReorderModal .mb-3 { margin-bottom: 0.9rem !important; }
        #bloodReorderModal .form-label { margin-bottom: 6px; }
        #bloodReorderModal .table { margin-bottom: 12px; }
        #bloodReorderModal .table td, #bloodReorderModal .table th { vertical-align: middle; }
        #bloodReorderModal .form-control[readonly] { background-color: #f8f9fa; }
        #bloodReorderModal .form-check { margin-right: 16px; }
        #bloodReorderModal hr { margin: 16px 0; }
        .table thead th {
            background-color: var(--redcross-red);
            color: white;
            border-bottom: none;
        }
        /* Table Styling */
        .table-dark {
            background-color: var(--redcross-red);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(148, 16, 34, 0.05);
        }

        /* Search Bar */
        #searchRequest:focus {
            border-color: var(--redcross-red);
            box-shadow: 0 0 0 0.2rem rgba(148, 16, 34, 0.25);
        }

        /* Timeline Styling */
        .timeline-step[data-status="active"] {
            border-color: var(--redcross-red);
            color: var(--redcross-red);
            font-weight: bold;
        }

        .timeline-step[data-status="completed"] {
            border-color: var(--redcross-red);
            background-color: var(--redcross-red);
            color: white;
        }

        /* Alert Styling */
        .alert-danger {
            background-color: var(--redcross-light-red);
            border-color: var(--redcross-red);
            color: white;
        }

        /* Modal Styling */
        .modal-header {
            background-color: var(--redcross-red);
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Form Controls */
        .form-control:focus {
            box-shadow: none !important;
            border-color: #dee2e6;
        }

        /* Sidebar Active State */
        .dashboard-home-sidebar a.active, 
        .dashboard-home-sidebar a:hover {
            background-color: #e9ecef;
            color: #333;
            font-weight: bold;
        }

        /* Status Colors */
        .text-danger {
            color: var(--redcross-red) !important;
            font-weight: bold;
        }

        /* Sort Indicators */
        .sort-indicator.active {
            color: var(--redcross-red);
        }

        /* Main Content - Full Width */
        .main-content {
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        /* Header Styling */
         .dashboard-header {
             background-color: #f5f7fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
         .header-left {
             display: flex;
             align-items: center;
             gap: 12px;
         }
        
         .header-logo { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        
         .header-title {
             font-size: 1.25rem;
             font-weight: 800;
             color: #0b2a5b;
            margin: 0;
        }
        
         .header-date { color: #8a8f98; font-size: 0.85rem; }

         .title-row { display: flex; align-items: baseline; gap: 14px; }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
         .exit-btn {
             background-color: #941022;
             border: none;
             border-radius: 20px;
             color: white;
             display: inline-flex;
             align-items: center;
             gap: 8px;
             padding: 6px 14px;
             font-weight: 600;
             box-shadow: 0 2px 6px rgba(0,0,0,.12);
         }
        /* Search Box Styling */
        .search-box .input-group {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: none;
            border: 1px solid #dee2e6;
        }

        .search-box .input-group-text {
            background: transparent;
            border: none;
            padding-left: 15px;
            padding-right: 15px;
        }

        .search-box .form-control {
            border: none;
            padding-left: 0;
            padding-right: 15px;
            background: transparent;
        }

        .search-box .form-control:focus {
            box-shadow: none !important;
            outline: none;
            border: none;
        }

        .search-box .input-group:focus-within {
            border: 1px solid #dee2e6;
            box-shadow: none !important;
        }
        /* Logo and Title Styling */
        .dashboard-home-sidebar img {
            transition: transform 0.3s ease;
        }
        .dashboard-home-sidebar img:hover {
            transform: scale(1.05);
        }
        .dashboard-home-sidebar h5 {
            font-weight: 600;
        }
        /* Scrollbar Styling */
        .dashboard-home-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .dashboard-home-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .dashboard-home-sidebar::-webkit-scrollbar-thumb {
            background: #941022;
            border-radius: 3px;
        }
        .dashboard-home-sidebar::-webkit-scrollbar-thumb:hover {
            background: #7a0c1c;
        }
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
             gap: 20px;
             margin-bottom: 30px;
        }
        
        .summary-card {
             background: white;
             border-radius: 8px;
             padding: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
             text-align: center;
             display: flex;
             flex-direction: column;
             align-items: center;
             justify-content: center;
             min-height: 120px;
        }
        
        .summary-card-title {
             color: #941022;
             font-size: 0.95rem;
             font-weight: 600;
             margin-bottom: 12px;
        }
        
        .summary-card-number {
             color: #941022;
             font-size: 2rem;
             font-weight: bold;
             margin: 0;
        }
        
        /* Content Section */
        .content-section {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #941022;
            font-size: 1.8rem;
            font-weight: bold;
            margin: 0;
        }
        
        .request-btn {
            background-color: #941022;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .request-btn:hover {
            background-color: #7a0c1c;
            color: white;
        }
        .chart-container {
            width: 100%;
            height: 400px;
        }
        #scheduleDateTime {
    opacity: 0;
    transition: opacity 0.5s ease-in-out;
}
.sort-indicator{
    cursor: pointer;
}
th {
    cursor: pointer;
    position: relative;
}

.sort-indicator {
    margin-left: 5px;
    font-size: 0.8em;
    color: #fffefe;
}

.asc .sort-indicator {
    color: #ffffff;
}

.desc .sort-indicator {
    color: #ffffff;
}


/* Loading Spinner - Minimal Addition */
.btn-loading {
    position: relative;
    pointer-events: none;
}

.btn-loading .spinner-border {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 1rem;
    height: 1rem;
    display: inline-block;
}

.btn-loading .btn-text {
    opacity: 0;
}

/* Search bar styling */
.search-box .input-group-text,
.search-box .form-control {
    padding-top: 15px;    /* Increased vertical padding */
    padding-bottom: 15px; /* Increased vertical padding */
    height: auto;
}

.search-box .input-group {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: none;
}

.search-box .input-group-text {
    border-right: none;
}

.search-box .form-control {
    border-left: none;
}

.search-box .form-control:focus {
    box-shadow: none !important;
    outline: none;
    border: none;
}

#requestSearchBar:focus {
    box-shadow: none !important;
}

        /* Filter and Search Bar */
        .filter-search-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .filter-icon {
            color: #6c757d;
            font-size: 1.2rem;
        }
        
        .filter-dropdown {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 8px 12px;
            background: white;
        }
        
        .search-input {
            flex: 1;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 8px 12px;
            background: white;
        }
        
        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            background: white;
            color: #6c757d;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .pagination-btn:hover {
            background: #f8f9fa;
            color: #495057;
        }
        
        .pagination-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination-btn:first-child,
        .pagination-btn:last-child {
            font-weight: bold;
        }
        
    </style>
</head>
<body>
<div class="main-content">
    <!-- Header -->
    <div class="dashboard-header">
         <div class="header-left">
             <img src="../../assets/image/PRC_Logo.png" alt="PRC" class="header-logo">
             <div class="title-row">
                 <h1 class="header-title">Hospital Request Dashboard</h1>
                 <div class="header-date"><?php echo date('l, F j, Y'); ?></div>
             </div>
         </div>
         <div class="header-right">
             <button class="exit-btn" onclick="window.location.href='../../assets/php_func/logout.php'">
                 <i class="fas fa-sign-out-alt"></i>
                 Logout
             </button>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="content-section">
        <div class="section-header">
            <h2 class="section-title">Blood Requests</h2>
            <button type="button" class="request-btn" data-bs-toggle="modal" data-bs-target="#bloodRequestModal">Request Blood</button>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-card-title">Pending Requests</div>
                <div class="summary-card-number"><?php echo $summary_stats['pending']; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Approved Requests</div>
                <div class="summary-card-number"><?php echo $summary_stats['approved']; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Declined Requests</div>
                <div class="summary-card-number"><?php echo $summary_stats['declined']; ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Completed Requests</div>
                <div class="summary-card-number"><?php echo $summary_stats['completed']; ?></div>
            </div>
        </div>

        <!-- Filter and Search Bar -->
        <div class="filter-search-bar">
            <i class="fas fa-filter filter-icon"></i>
            <select class="filter-dropdown">
                <option>All Status</option>
                <option>Pending</option>
                <option>Approved</option>
                <option>Declined</option>
                <option>Completed</option>
            </select>
            <input type="text" class="search-input" placeholder="Search requests..." id="requestSearchBar">
        </div>

        <!-- Requests Table -->
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>No.</th>
                    <th>Request ID</th>
                    <th>Blood Type</th>
                    <th>Units Needed</th>
                    <th>Date Needed</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="requestTable">
                <?php $rowNum = 1; if (empty($blood_requests)): ?>
                <tr>
                    <td colspan="7" class="text-center">No blood requests found.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($blood_requests as $request): ?>
                        <?php 
                        // Debug: Log when_needed for each request
                        error_log("Debug - Request " . $request['request_id'] . " when_needed: " . print_r($request['when_needed'], true));
                        ?>
                        <tr>
                            <td><?php echo $rowNum++; ?></td>
                            <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                            <td><?php echo htmlspecialchars($request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-')); ?></td>
                            <td><?php echo htmlspecialchars($request['units_requested'] . ' Bags'); ?></td>
                            <td><?php 
                                // Debug: Check what's in when_needed
                                error_log("Debug - Table when_needed for request " . $request['request_id'] . ": " . print_r($request['when_needed'], true));
                                if (!empty($request['when_needed'])) {
                                    echo date('m/d/Y', strtotime($request['when_needed']));
                                } else {
                                    echo 'EMPTY_FIELD';
                                }
                            ?></td>
                            <td>
                                <?php 
                                $status = $request['status'];
                                // Handle status display according to requirements
                                if ($status === 'Pending') {
                                    echo '<span class="badge bg-warning text-dark">Pending</span>';
                                } elseif ($status === 'Approved') {
                                    echo '<span class="badge bg-primary">Approved</span>';
                                } elseif ($status === 'Printed') {
                                    echo '<span class="badge bg-primary">Approved</span>';
                                } elseif ($status === 'Completed') {
                                    echo '<span class="badge bg-success">Completed</span>';
                                } elseif ($status === 'Handed_over') {
                                    // Display Handed_over as Approved in the table
                                    echo '<span class="badge bg-primary">Approved</span>';
                                } elseif ($status === 'Declined') {
                                    echo '<span class="badge bg-danger">Declined</span>';
                                } elseif ($status === 'Rescheduled') {
                                    echo '<span class="badge bg-info text-dark">Rescheduled</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $status = $request['status'];
                                // Show different action buttons based on status
                                if ($status === 'Pending'): ?>
                                    <button class="btn btn-sm btn-primary view-btn" 
                                        data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                        data-patient-age="<?php echo htmlspecialchars($request['patient_age']); ?>"
                                        data-patient-gender="<?php echo htmlspecialchars($request['patient_gender']); ?>"
                                        data-patient-diagnosis="<?php echo htmlspecialchars($request['patient_diagnosis']); ?>"
                                        data-blood-type="<?php echo htmlspecialchars($request['patient_blood_type']); ?>"
                                        data-rh-factor="<?php echo htmlspecialchars($request['rh_factor']); ?>"
                                        data-component="Whole Blood"
                                        data-units="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                        data-when-needed="<?php echo htmlspecialchars($request['when_needed']); ?>"
                                        data-debug-when-needed="<?php echo htmlspecialchars($request['when_needed'] ?? 'NULL'); ?>"
                                        data-is-asap="<?php echo isset($request['is_asap']) && ($request['is_asap'] === true || $request['is_asap'] === 'true' || $request['is_asap'] === 1 || $request['is_asap'] === '1') ? 'true' : 'false'; ?>"
                                        data-approved-by="<?php echo htmlspecialchars($request['approved_by'] ?? ''); ?>"
                                        data-approved-date="<?php echo htmlspecialchars($request['approved_date'] ?? ''); ?>"
                                        data-handed-over-by="<?php echo htmlspecialchars($request['handed_over_by'] ?? ''); ?>"
                                        data-handed-over-date="<?php echo htmlspecialchars($request['handed_over_date'] ?? ''); ?>"
                                        data-physician-name="<?php echo htmlspecialchars($request['physician_name'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($request['status']); ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php elseif ($status === 'Approved' || $status === 'Accepted' || $status === 'Confirmed'): ?>
                                    <button class="btn btn-sm btn-info print-btn" 
                                        data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                        data-patient-age="<?php echo htmlspecialchars($request['patient_age']); ?>"
                                        data-patient-gender="<?php echo htmlspecialchars($request['patient_gender']); ?>"
                                        data-patient-diagnosis="<?php echo htmlspecialchars($request['patient_diagnosis']); ?>"
                                        data-blood-type="<?php echo htmlspecialchars($request['patient_blood_type']); ?>"
                                        data-rh-factor="<?php echo htmlspecialchars($request['rh_factor']); ?>"
                                        data-component="Whole Blood"
                                        data-units="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                        data-when-needed="<?php echo htmlspecialchars($request['when_needed']); ?>"
                                        data-debug-when-needed="<?php echo htmlspecialchars($request['when_needed'] ?? 'NULL'); ?>"
                                        data-is-asap="<?php echo isset($request['is_asap']) && ($request['is_asap'] === true || $request['is_asap'] === 'true' || $request['is_asap'] === 1 || $request['is_asap'] === '1') ? 'true' : 'false'; ?>"
                                        data-approved-by="<?php echo htmlspecialchars($request['approved_by'] ?? ''); ?>"
                                        data-approved-date="<?php echo htmlspecialchars($request['approved_date'] ?? ''); ?>"
                                        data-handed-over-by="<?php echo htmlspecialchars($request['handed_over_by'] ?? ''); ?>"
                                        data-handed-over-date="<?php echo htmlspecialchars($request['handed_over_date'] ?? ''); ?>"
                                        data-physician-name="<?php echo htmlspecialchars($request['physician_name'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($request['status']); ?>">
                                        <i class="fas fa-print"></i>
                                    </button>
                                <?php elseif ($status === 'Printed'): ?>
                                    <button class="btn btn-sm btn-primary view-btn" 
                                        data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                        data-patient-age="<?php echo htmlspecialchars($request['patient_age']); ?>"
                                        data-patient-gender="<?php echo htmlspecialchars($request['patient_gender']); ?>"
                                        data-patient-diagnosis="<?php echo htmlspecialchars($request['patient_diagnosis']); ?>"
                                        data-blood-type="<?php echo htmlspecialchars($request['patient_blood_type']); ?>"
                                        data-rh-factor="<?php echo htmlspecialchars($request['rh_factor']); ?>"
                                        data-component="Whole Blood"
                                        data-units="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                        data-when-needed="<?php echo htmlspecialchars($request['when_needed']); ?>"
                                        data-debug-when-needed="<?php echo htmlspecialchars($request['when_needed'] ?? 'NULL'); ?>"
                                        data-is-asap="<?php echo isset($request['is_asap']) && ($request['is_asap'] === true || $request['is_asap'] === 'true' || $request['is_asap'] === 1 || $request['is_asap'] === '1') ? 'true' : 'false'; ?>"
                                        data-approved-by="<?php echo htmlspecialchars($request['approved_by'] ?? ''); ?>"
                                        data-approved-date="<?php echo htmlspecialchars($request['approved_date'] ?? ''); ?>"
                                        data-handed-over-by="<?php echo htmlspecialchars($request['handed_over_by'] ?? ''); ?>"
                                        data-handed-over-date="<?php echo htmlspecialchars($request['handed_over_date'] ?? ''); ?>"
                                        data-physician-name="<?php echo htmlspecialchars($request['physician_name'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($request['status']); ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php elseif ($status === 'Handed_over'): ?>
                                    <button class="btn btn-sm btn-success handover-btn" 
                                        title="Confirm Arrival"
                                        data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                        data-patient-age="<?php echo htmlspecialchars($request['patient_age']); ?>"
                                        data-patient-gender="<?php echo htmlspecialchars($request['patient_gender']); ?>"
                                        data-patient-diagnosis="<?php echo htmlspecialchars($request['patient_diagnosis']); ?>"
                                        data-blood-type="<?php echo htmlspecialchars($request['patient_blood_type']); ?>"
                                        data-rh-factor="<?php echo htmlspecialchars($request['rh_factor']); ?>"
                                        data-component="Whole Blood"
                                        data-units="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                        data-when-needed="<?php echo htmlspecialchars($request['when_needed']); ?>"
                                        data-debug-when-needed="<?php echo htmlspecialchars($request['when_needed'] ?? 'NULL'); ?>"
                                        data-is-asap="<?php echo isset($request['is_asap']) && ($request['is_asap'] === true || $request['is_asap'] === 'true' || $request['is_asap'] === 1 || $request['is_asap'] === '1') ? 'true' : 'false'; ?>"
                                        data-approved-by="<?php echo htmlspecialchars($request['approved_by'] ?? ''); ?>"
                                        data-approved-date="<?php echo htmlspecialchars($request['approved_date'] ?? ''); ?>"
                                        data-handed-over-by="<?php echo htmlspecialchars($request['handed_over_by'] ?? ''); ?>"
                                        data-handed-over-date="<?php echo htmlspecialchars($request['handed_over_date'] ?? ''); ?>"
                                        data-physician-name="<?php echo htmlspecialchars($request['physician_name'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($request['status']); ?>">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php elseif ($status === 'Completed' || $status === 'Declined'): ?>
                                    <button class="btn btn-sm btn-primary view-btn" 
                                        data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                        data-patient-age="<?php echo htmlspecialchars($request['patient_age']); ?>"
                                        data-patient-gender="<?php echo htmlspecialchars($request['patient_gender']); ?>"
                                        data-patient-diagnosis="<?php echo htmlspecialchars($request['patient_diagnosis']); ?>"
                                        data-blood-type="<?php echo htmlspecialchars($request['patient_blood_type']); ?>"
                                        data-rh-factor="<?php echo htmlspecialchars($request['rh_factor']); ?>"
                                        data-component="Whole Blood"
                                        data-units="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                        data-when-needed="<?php echo htmlspecialchars($request['when_needed']); ?>"
                                        data-debug-when-needed="<?php echo htmlspecialchars($request['when_needed'] ?? 'NULL'); ?>"
                                        data-is-asap="<?php echo isset($request['is_asap']) && ($request['is_asap'] === true || $request['is_asap'] === 'true' || $request['is_asap'] === 1 || $request['is_asap'] === '1') ? 'true' : 'false'; ?>"
                                        data-approved-by="<?php echo htmlspecialchars($request['approved_by'] ?? ''); ?>"
                                        data-approved-date="<?php echo htmlspecialchars($request['approved_date'] ?? ''); ?>"
                                        data-handed-over-by="<?php echo htmlspecialchars($request['handed_over_by'] ?? ''); ?>"
                                        data-handed-over-date="<?php echo htmlspecialchars($request['handed_over_date'] ?? ''); ?>"
                                        data-physician-name="<?php echo htmlspecialchars($request['physician_name'] ?? ''); ?>"
                                        data-status="<?php echo htmlspecialchars($request['status']); ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php else: // Other statuses ?>
                                    <button class="btn btn-sm btn-primary view-btn" 
                                        data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                        data-patient-age="<?php echo htmlspecialchars($request['patient_age']); ?>"
                                        data-patient-gender="<?php echo htmlspecialchars($request['patient_gender']); ?>"
                                        data-patient-diagnosis="<?php echo htmlspecialchars($request['patient_diagnosis']); ?>"
                                        data-blood-type="<?php echo htmlspecialchars($request['patient_blood_type']); ?>"
                                        data-rh-factor="<?php echo htmlspecialchars($request['rh_factor']); ?>"
                                        data-component="Whole Blood"
                                        data-units="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                        data-when-needed="<?php echo htmlspecialchars($request['when_needed']); ?>"
                                        data-debug-when-needed="<?php echo htmlspecialchars($request['when_needed'] ?? 'NULL'); ?>"
                                        data-is-asap="<?php echo isset($request['is_asap']) && ($request['is_asap'] === true || $request['is_asap'] === 'true' || $request['is_asap'] === 1 || $request['is_asap'] === '1') ? 'true' : 'false'; ?>"
                                        data-status="<?php echo htmlspecialchars($request['status']); ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination-container">
            <div class="pagination">
                <a href="#" class="pagination-btn">&lt;</a>
                <a href="#" class="pagination-btn active">1</a>
                <a href="#" class="pagination-btn">2</a>
                <a href="#" class="pagination-btn">3</a>
                <a href="#" class="pagination-btn">4</a>
                <a href="#" class="pagination-btn">5</a>
                <a href="#" class="pagination-btn">&gt;</a>
            </div>
        </div>
    </div>
</div>


<!-- Blood Request Modal -->
<div class="modal fade" id="bloodRequestModal" tabindex="-1" aria-labelledby="bloodRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bloodRequestModalLabel">Referral Blood Shipment Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bloodRequestForm">
                    <!-- Stepper -->
                    <div class="stepper">
                        <div class="step active" data-step="1">Patient</div>
                        <div class="step" data-step="2">Request Details</div>
                        <div class="step" data-step="3">Review</div>
                    </div>

                    <!-- Step 1: Patient Information -->
                    <div class="form-step active" data-step="1">
                    <div class="mb-3">
                        <label class="form-label">Patient Name</label>
                        <div class="row g-3 align-items-start">
                            <div class="col-md-5">
                                <label class="form-label mb-1">First Name</label>
                                <input type="text" class="form-control" name="patient_first_name" placeholder="e.g., Juan" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">M.I.</label>
                                <input type="text" class="form-control text-center" name="patient_middle_initial" maxlength="1" placeholder="A" aria-label="Middle Initial">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label mb-1">Surname</label>
                                <input type="text" class="form-control" name="patient_last_name" placeholder="e.g., Dela Cruz" required>
                            </div>
                        </div>
                        <input type="hidden" name="patient_name">
                    </div>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control" name="patient_age" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="patient_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" class="form-control" name="patient_diagnosis" placeholder="e.g., T/E, FTE, Septic Shock" required>
                    </div>

                    </div>

                    <!-- Step 2: Blood Request Details -->
                    <div class="form-step" data-step="2">
                    <h6 class="mb-3 mt-4 fw-bold">Blood Request Details</h6>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Blood Type</label>
                            <select class="form-select" name="patient_blood_type" required>
                                <option value="">Select Type</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="O">O</option>
                                <option value="AB">AB</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label">RH Factor</label>
                            <select class="form-select" name="rh_factor" required>
                                <option value="">Select RH</option>
                                <option value="Positive">Positive</option>
                                <option value="Negative">Negative</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 row gx-3">
                        <div class="col-md-4">
                            <label class="form-label">Component</label>
                            <input type="hidden" name="blood_component" value="Whole Blood">
                            <input type="text" class="form-control" value="Whole Blood" readonly style="width: 105%;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Number of Units</label>
                            <input type="number" class="form-control" name="units_requested" min="1" required style="width: 105%;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">When Needed</label>
                            <select id="whenNeeded" class="form-select" name="when_needed" required style="width: 105%;">
                                <option value="ASAP">ASAP</option>
                                <option value="Scheduled">Scheduled</option>
                            </select>
                        </div>
                    </div>
                    <div id="scheduleDateTime" class="mb-3 d-none">
                        <label class="form-label">Scheduled Date & Time</label>
                        <input type="datetime-local" class="form-control" name="scheduled_datetime">
                    </div>

                    <!-- Additional Information (hidden in step UI; shown in summary only) -->
                    <input type="hidden" name="hospital_admitted" value="<?php echo $_SESSION['user_first_name'] ?? ''; ?>">
                    <input type="hidden" name="physician_name" value="<?php echo $_SESSION['user_surname'] ?? ''; ?>">
                    </div>

                    <!-- Step 3: Review -->
                    <div class="form-step" data-step="3">
                        <h6 class="mb-3 mt-4 fw-bold">Review & Confirm</h6>
                        <p class="text-muted">Please review the information before submitting your request.</p>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="list-group">
                                    <div class="list-group-item"><strong>Patient:</strong> <span id="reviewPatientName"></span></div>
                                    <div class="list-group-item"><strong>Age/Gender:</strong> <span id="reviewAgeGender"></span></div>
                                    <div class="list-group-item"><strong>Diagnosis:</strong> <span id="reviewDiagnosis"></span></div>
                                    <div class="list-group-item"><strong>Blood Type:</strong> <span id="reviewBlood"></span></div>
                                    <div class="list-group-item"><strong>Units/When Needed:</strong> <span id="reviewUnitsWhen"></span></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <h6 class="fw-bold mt-2">Additional Information</h6>
                                <div class="list-group">
                                    <div class="list-group-item"><strong>Hospital Admitted:</strong> <span id="reviewHospital"></span></div>
                                    <div class="list-group-item"><strong>Requesting Physician:</strong> <span id="reviewPhysician"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer d-flex justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-secondary" id="prevStepBtn">Back</button>
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger" id="nextStepBtn">Next</button>
                            <button type="submit" class="btn btn-success d-none" id="submitRequestBtn">Submit Request</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- View Modal -->
<div class="modal fade" id="bloodReorderModal" tabindex="-1" aria-labelledby="bloodViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #941022; color: white;">
                <h5 class="modal-title" id="bloodViewModalLabel">Referral Blood Shipment Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <!-- Header Section -->
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <div class="text-muted mb-2">Date: <span id="modalCurrentDate"></span></div>
                    </div>
                    <div>
                        <span class="badge bg-success fs-6 px-3 py-2" id="modalStatusBadge">Approved</span>
                    </div>
                </div>

                <!-- Patient Information -->
                <div class="mb-4">
                    <h5 class="mb-2" id="modalPatientName" style="color: #941022; font-weight: bold;">Patient Name</h5>
                    <p class="mb-1" id="modalPatientDetails">Age, Gender</p>
                </div>

                <hr class="my-4" id="approvalSeparator">

                <!-- Request Details Section -->
                <div class="mb-4">
                    <h6 class="mb-3" style="color: #333; font-weight: bold;">Request Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Diagnosis:</label>
                        <input type="text" class="form-control" id="modalDiagnosis" readonly style="background-color: #f8f9fa;">
                    </div>

                    <!-- Blood Request Table -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Blood Request:</label>
                        <table class="table table-bordered">
                            <thead style="background-color: #941022; color: white;">
                                <tr>
                                    <th>Blood Type</th>
                                    <th>RH</th>
                                    <th>Number of Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="modalBloodType">-</td>
                                    <td id="modalRH">-</td>
                                    <td id="modalUnits">-</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">When Needed:</label>
                        <div class="d-flex align-items-center">
                            <div class="form-check me-3">
                                <input class="form-check-input" type="radio" name="modalWhenNeeded" id="modalAsap" value="ASAP" disabled>
                                <label class="form-check-label" for="modalAsap">ASAP</label>
                            </div>
                            <div class="form-check me-3">
                                <input class="form-check-input" type="radio" name="modalWhenNeeded" id="modalScheduled" value="Scheduled" disabled>
                                <label class="form-check-label" for="modalScheduled">Scheduled</label>
                            </div>
                            <input type="date" class="form-control" id="modalScheduledDate" style="width: 200px;" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Hospital Admitted:</label>
                        <input type="text" class="form-control" id="modalHospital" readonly style="background-color: #f8f9fa;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Requesting Physician:</label>
                        <input type="text" class="form-control" id="modalPhysician" readonly style="background-color: #f8f9fa;">
                    </div>
                </div>

                <hr class="my-4">

                <!-- Approval Section -->
                <div class="mb-4" id="approvalSection">
                    <label class="form-label fw-bold">Approved by:</label>
                    <input type="text" class="form-control" id="modalApprovedBy" placeholder="(e.g., &quot;Approved by Dr. Reyes - June 18, 2025 at 9:42 AM&quot;)" readonly style="background-color: #f8f9fa;">
                </div>

                <!-- Handover Information (for Printed status) -->
                <div class="mb-4" id="handoverInfo" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Handed Over by:</label>
                        <input type="text" class="form-control" id="modalHandedOverBy" placeholder="(Handed over by Staff John D. - June 19, 2025)" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Received By:</label>
                        <input type="text" class="form-control" id="modalReceivedBy" placeholder="(Received By hospital personnel - June 19, 2025)" readonly style="background-color: #f8f9fa;">
                    </div>
                </div>

                <!-- Instructions -->
                <div class="alert alert-info" id="approvalInstructions">
                    <p class="mb-0">The blood request has been approved. Please print the receipt and provide it to the patient's representative for claiming at PRC.</p>
                </div>

                <!-- Handover Instructions (for Printed status) -->
                <div class="alert alert-success" id="handoverInstructions" style="display: none;">
                    <p class="mb-0">The blood has been successfully handed over to the hospital representative.</p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning print-btn" id="printRequestBtn" style="display: none;" data-request-id="">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button type="button" class="btn btn-success handover-btn" id="handoverRequestBtn" style="display: none;" data-request-id="">
                        <i class="fas fa-check"></i> Confirm Arrival
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="printSuccessModal" tabindex="-1" aria-labelledby="printSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #dc3545; color: white;">
                <h5 class="modal-title" id="printSuccessModalLabel">Blood Request Completed</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Blood handover to the patient's representative has been completed successfully.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">View</button>
            </div>
        </div>
    </div>
</div>

<!-- Action Result Modal -->
<div class="modal fade" id="actionResultModal" tabindex="-1" aria-labelledby="actionResultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #dc3545; color: white;">
                <h5 class="modal-title" id="actionResultModalLabel">Request Update</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="actionResultBody">
                <!-- Filled dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
    </div>

    <!-- Confirm Submit Modal -->
    <div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-labelledby="confirmSubmitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #941022; color: white;">
                    <h5 class="modal-title" id="confirmSubmitModalLabel">Confirm Blood Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Please review the request details before submitting. Proceed with creating this blood request?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmSubmitBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="module" src="../../assets/js/handed-over-notify.js"></script>

    <script>
        // Configure one-time per-account notification for Handed_over (handled in external module)
        window.HandedOverNotifyConfig = {
            userId: <?php echo json_encode($_SESSION['user_id'] ?? 'guest'); ?>,
            handedOverIds: <?php echo json_encode($handed_over_ids); ?>,
            modalSelector: '#printSuccessModal',
            viewButtonSelector: '#printSuccessModal .btn-primary',
            buildViewUrl: (id) => `../../src/views/forms/print-blood-request.php?request_id=${id}`
        };
        // Define Supabase constants
        const SUPABASE_URL = '<?php echo SUPABASE_URL; ?>';
        const SUPABASE_KEY = '<?php echo SUPABASE_API_KEY; ?>';
        
        document.addEventListener("DOMContentLoaded", function () {
    let headers = document.querySelectorAll("th");
    
    if (!headers || headers.length === 0) return;

    headers.forEach((header, index) => {
        if (index < headers.length - 1 && header) { // Ignore the last column (Action) and check if header exists
            // Create a single sorting indicator for each column
            let icon = document.createElement("span");
            icon.classList.add("sort-indicator");
            icon.textContent = " ▼"; // Default neutral state
            header.appendChild(icon);

            // Add click event listener to sort the column
            header.addEventListener("click", function () {
                sortTable(index, header);
            });
        }
    });
});

function sortTable(columnIndex, header) {
    let table = document.querySelector("table");
    if (!table) return;
    
    let tbody = table.querySelector("tbody");
    if (!tbody) return;
    
    let rows = Array.from(tbody.rows);

    // Determine the current sorting order
    let isAscending = header.classList.contains("asc");
    header.classList.toggle("asc", !isAscending);
    header.classList.toggle("desc", isAscending);

    // Determine the data type of the column
    let type = (columnIndex === 2) ? "number" : 
               (columnIndex === 5 || columnIndex === 6) ? "date" : "text";

    // Sort the rows
    rows.sort((rowA, rowB) => {
        let cellA = rowA.cells[columnIndex].textContent.trim();
        let cellB = rowB.cells[columnIndex].textContent.trim();

        if (type === 'number') {
            return isAscending ? parseInt(cellA) - parseInt(cellB) : parseInt(cellB) - parseInt(cellA);
        } else if (type === 'date') {
            return isAscending ? new Date(cellA) - new Date(cellB) : new Date(cellB) - new Date(cellA);
        } else {
            return isAscending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        }
    });

    // Append sorted rows back to the table
    tbody.append(...rows);

    // Update the indicator for the active sorted column
    let sortIcon = header.querySelector(".sort-indicator");
    sortIcon.textContent = isAscending ? " ▲" : " ▼"; // Toggle between ▲ and ▼
}
//Placeholder Static Data
document.addEventListener("DOMContentLoaded", function () {
    // Add click event to all Reorder buttons
    document.querySelectorAll(".btn-secondary").forEach(button => {
        button.addEventListener("click", function (event) {
            event.stopPropagation(); // Prevent row click event from firing

            // Get the row data
            let row = this.closest("tr");
            if (!row) return;
            let cells = row.querySelectorAll("td");

            // Extract data from the row
            let requestId = cells[0].textContent;
            let bloodType = cells[2].textContent;
            let quantity = cells[3].textContent.replace(" Units", ""); // Remove " Units"
            let urgency = cells[4].textContent;
            let status = cells[5].textContent;
            let requestedOn = cells[6].textContent;
            let expectedDelivery = cells[7].textContent;

            // Open the reorder modal (this is actually the view modal, not a reorder form)
            let modal = document.getElementById("bloodReorderModal");
            new bootstrap.Modal(modal).show();
        });
    });
});
//Track modal
document.addEventListener("DOMContentLoaded", function () {
    // Add click event to all Track buttons
    document.querySelectorAll(".btn-primary").forEach(button => {
        button.addEventListener("click", function (event) {
            event.stopPropagation(); // Prevent row click event from firing

            // Get the row data
            let row = this.closest("tr");
            if (!row) return;
            let cells = row.querySelectorAll("td");

            // Extract data from the row
            let requestId = cells[0].textContent;
            let status = cells[5].textContent.trim(); // Current status (e.g., "Pending", "In Transit", "Delivered")

            // Pre-fill the track modal with data
            let modal = document.getElementById("trackingModal");
            if (!modal) return;
            
            let timelineSteps = modal.querySelectorAll(".timeline-step");

            // Update the current status
            const trackStatus = modal.querySelector("#trackStatus");
            if (trackStatus) {
                trackStatus.textContent = `Your blood is ${status.toLowerCase()}.`;
            }

            // Update the timeline based on the current status
            timelineSteps.forEach(step => step.removeAttribute("data-status"));

            switch (status) {
                case "Being Processed":
                    timelineSteps[0].setAttribute("data-status", "active");
                    break;
                case "In Storage":
                    timelineSteps[0].setAttribute("data-status", "completed");
                    timelineSteps[1].setAttribute("data-status", "active");
                    break;
                case "Being Distributed":
                    timelineSteps[0].setAttribute("data-status", "completed");
                    timelineSteps[1].setAttribute("data-status", "completed");
                    timelineSteps[2].setAttribute("data-status", "active");
                    break;
                case "Delivered":
                    timelineSteps.forEach(step => step.setAttribute("data-status", "completed"));
                    break;
                default:
                    timelineSteps[0].setAttribute("data-status", "active");
            }

            // Open the track modal
            new bootstrap.Modal(modal).show();
        });
    });
});
         //Shows the date and time if the Scheduled is selected
         const whenNeededElement = document.getElementById('whenNeeded');
         if (whenNeededElement) {
            whenNeededElement.addEventListener('change', function() {
                var scheduleDateTime = document.getElementById('scheduleDateTime');
                if (scheduleDateTime) {
                    if (this.value === 'Scheduled') { // Ensure it matches exactly
                        scheduleDateTime.classList.remove('d-none'); // Show the date picker
                        scheduleDateTime.style.opacity = 0;
                        setTimeout(() => scheduleDateTime.style.opacity = 1, 10); // Smooth fade-in
                    } else {
                        scheduleDateTime.style.opacity = 0;
                        setTimeout(() => scheduleDateTime.classList.add('d-none'), 500); // Hide after fade-out
                    }
                }
            });
         }





        // Add loading state to sidebar links
        document.querySelectorAll('.dashboard-home-sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('active') && !this.getAttribute('href').includes('#')) {
                    const icon = this.querySelector('i');
                    const text = this.textContent.trim();
                    
                    // Save original content
                    this.setAttribute('data-original-content', this.innerHTML);
                    
                    // Add loading state
                    this.innerHTML = `
                        <div class="d-flex align-items-center">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            ${text}
                        </div>`;
                }
            });
        });

        // Add loading state to Save Changes button in edit form
        const editRequestForm = document.getElementById('editRequestForm');
        if (editRequestForm) {
            editRequestForm.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    // Save original content
                    const originalContent = submitButton.innerHTML;
                    
                    // Add loading state
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
                    
                    // Restore button state after request completes
                    setTimeout(() => {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalContent;
                    }, 2000);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const requestSearchBar = document.getElementById('requestSearchBar');
            
            // Function to perform search
            function performSearch(searchText) {
                const table = document.getElementById('requestTable');
                const rows = table.getElementsByTagName('tr');

                for (let row of rows) {
                    // Skip header row
                    if (row.querySelector('th')) continue;
                    
                    // Get the request ID (2nd column) for search
                    const requestIdCell = row.querySelector('td:nth-child(2)');
                    
                    if (requestIdCell) {
                        const requestId = requestIdCell.textContent.toLowerCase();
                        
                        // Show row if request ID contains search text, hide otherwise
                        if (requestId.includes(searchText.toLowerCase())) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                }
            }
            
            // Event listeners for search bar
            if (requestSearchBar) {
                requestSearchBar.addEventListener('keyup', function() {
                    performSearch(this.value);
                });
                
                requestSearchBar.addEventListener('focus', function() {
                    this.style.boxShadow = '0 0 0 0.2rem rgba(148, 16, 34, 0.25)';
                });

                requestSearchBar.addEventListener('blur', function() {
                    this.style.boxShadow = 'none';
                });
            }
        });
    </script>

    <!-- Blood Request Form JavaScript -->
    <script>
    // Stepper logic for blood request modal
    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('bloodRequestModal');
        if (!modalEl) return;
        const steps = Array.from(modalEl.querySelectorAll('.form-step'));
        const stepChips = Array.from(modalEl.querySelectorAll('.stepper .step'));
        const nextBtn = modalEl.querySelector('#nextStepBtn');
        const prevBtn = modalEl.querySelector('#prevStepBtn');
        const submitBtn = modalEl.querySelector('#submitRequestBtn');
        let current = 0;

        function updateUI() {
            steps.forEach((s, i) => s.classList.toggle('active', i === current));
            stepChips.forEach((c, i) => c.classList.toggle('active', i === current));
            prevBtn.style.visibility = current === 0 ? 'hidden' : 'visible';
            nextBtn.classList.toggle('d-none', current === steps.length - 1);
            submitBtn.classList.toggle('d-none', current !== steps.length - 1);
            if (current === steps.length - 1) fillReview();
        }

        function fillReview() {
            const get = (name) => modalEl.querySelector(`[name="${name}"]`);
            // Compose review patient name from split fields if present
            const first = get('patient_first_name')?.value || '';
            const mi = get('patient_middle_initial')?.value || '';
            const last = get('patient_last_name')?.value || '';
            const patient = (first || mi || last) ? `${first}${mi ? ' ' + mi + '.' : ''} ${last}`.trim() : (get('patient_name')?.value || '');
            const age = get('patient_age')?.value || '';
            const gender = get('patient_gender')?.value || '';
            const diagnosis = get('patient_diagnosis')?.value || '';
            const bt = get('patient_blood_type')?.value || '';
            const rh = get('rh_factor')?.value || '';
            const units = get('units_requested')?.value || '';
            const when = get('when_needed')?.value || '';
            const hosp = get('hospital_admitted')?.value || '';
            const phys = get('physician_name')?.value || '';
            modalEl.querySelector('#reviewPatientName').textContent = patient;
            modalEl.querySelector('#reviewAgeGender').textContent = `${age} / ${gender}`;
            modalEl.querySelector('#reviewDiagnosis').textContent = diagnosis;
            modalEl.querySelector('#reviewBlood').textContent = `${bt} ${rh}`.trim();
            modalEl.querySelector('#reviewUnitsWhen').textContent = `${units} unit(s) / ${when}`;
            modalEl.querySelector('#reviewHospital').textContent = hosp;
            modalEl.querySelector('#reviewPhysician').textContent = phys;
        }

        nextBtn?.addEventListener('click', function() {
            // Basic required validations per step
            if (current === 0) {
                const requiredFields = [
                    '[name="patient_first_name"]',
                    '[name="patient_last_name"]',
                    '[name="patient_age"]',
                    '[name="patient_gender"]',
                    '[name="patient_diagnosis"]'
                ];
                for (const sel of requiredFields) {
                    const el = modalEl.querySelector(sel);
                    if (el && !el.value.trim()) {
                        const msgModal = new bootstrap.Modal(document.getElementById('actionResultModal'));
                        const body = document.getElementById('actionResultBody');
                        if (body) { body.textContent = 'Please complete all required fields on this step before continuing.'; }
                        msgModal.show();
                        el.focus();
                        return;
                    }
                }
            } else if (current === 1) {
                const requiredFields = [
                    '[name="patient_blood_type"]',
                    '[name="rh_factor"]',
                    '[name="units_requested"]',
                    '#whenNeeded'
                ];
                for (const sel of requiredFields) {
                    const el = modalEl.querySelector(sel);
                    if (el && (!el.value || !el.value.trim())) {
                        const msgModal = new bootstrap.Modal(document.getElementById('actionResultModal'));
                        const body = document.getElementById('actionResultBody');
                        if (body) { body.textContent = 'Please complete all required fields on this step before continuing.'; }
                        msgModal.show();
                        el.focus();
                        return;
                    }
                }
            }
            if (current < steps.length - 1) { current++; updateUI(); }
        });
        prevBtn?.addEventListener('click', function() {
            if (current > 0) { current--; updateUI(); }
        });

        modalEl.addEventListener('shown.bs.modal', function(){ current = 0; updateUI(); });
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Add blood request form submission handler
        const bloodRequestForm = document.getElementById('bloodRequestForm');
        if (bloodRequestForm) {
            bloodRequestForm.addEventListener('submit', function(e) {
                e.preventDefault();
                // Show confirm modal before actual submission
                const confirmModal = new bootstrap.Modal(document.getElementById('confirmSubmitModal'));
                confirmModal.show();
                
                const doSubmit = () => {
                // Show loading state
                const submitBtn = bloodRequestForm.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                
                // Create FormData object
                const formData = new FormData(bloodRequestForm);
                
                // Add additional data
                formData.append('user_id', '<?php echo $_SESSION['user_id']; ?>');
                formData.append('status', 'Pending');
                formData.append('physician_name', '<?php echo $_SESSION['user_surname']; ?>');
                formData.append('requested_on', new Date().toISOString());
                // Compose patient_name from split fields for uniform DB storage
                try {
                    const first = this.querySelector('[name="patient_first_name"]').value?.trim() || '';
                    const mi = this.querySelector('[name="patient_middle_initial"]').value?.trim() || '';
                    const last = this.querySelector('[name="patient_last_name"]').value?.trim() || '';
                    const fullName = `${first}${mi ? ' ' + mi + '.' : ''} ${last}`.trim();
                    this.querySelector('[name="patient_name"]').value = fullName;
                    formData.set('patient_name', fullName);
                } catch (_) {}
                
                // Handle "when needed" logic
                const whenNeeded = document.getElementById('whenNeeded').value;
                const isAsap = whenNeeded === 'ASAP';
                formData.append('is_asap', isAsap ? 'true' : 'false');
                
                // Always set when_needed as a timestamp
                if (isAsap) {
                    // For ASAP, use current date/time
                    formData.set('when_needed', new Date().toISOString());
                } else {
                    // For Scheduled, use the selected date/time
                    const scheduledDate = document.querySelector('#scheduleDateTime input').value;
                    if (scheduledDate) {
                        formData.set('when_needed', new Date(scheduledDate).toISOString());
                    } else {
                        // If no date selected for scheduled, default to current date
                        formData.set('when_needed', new Date().toISOString());
                    }
                }
                
                // Define exact fields from the database schema
                const validFields = [
                    'request_id', 'user_id', 'patient_name', 'patient_age', 'patient_gender', 
                    'patient_diagnosis', 'patient_blood_type', 'rh_factor', 'blood_component', 
                    'units_requested', 'when_needed', 'is_asap', 'hospital_admitted', 
                    'physician_name', 'requested_on', 'status'
                ];
                
                // Convert FormData to JSON object, only including valid fields
                const data = {};
                validFields.forEach(field => {
                    if (formData.has(field)) {
                        const value = formData.get(field);
                        
                        // Convert numeric values to numbers
                        if (field === 'patient_age' || field === 'units_requested') {
                            data[field] = parseInt(value, 10);
                        } 
                        // Convert boolean strings to actual booleans
                        else if (field === 'is_asap') {
                            data[field] = value === 'true';
                        }
                        // Format timestamps properly
                        else if (field === 'when_needed' || field === 'requested_on') {
                            try {
                                // Ensure we have a valid date
                                const dateObj = new Date(value);
                                if (isNaN(dateObj.getTime())) {
                                    throw new Error(`Invalid date for ${field}: ${value}`);
                                }
                                // Format as ISO string with timezone
                                data[field] = dateObj.toISOString();
                            } catch (err) {
                                console.error(`Error formatting date for ${field}:`, err);
                                // Default to current time if invalid
                                data[field] = new Date().toISOString();
                            }
                        }
                        // All other fields as strings
                        else {
                            data[field] = value;
                        }
                    }
                });
                
                console.log('Submitting request data:', data);
                console.log('Valid fields in database:', validFields);
                console.log('FormData keys:', Array.from(formData.keys()));
                console.log('when_needed value:', data.when_needed);
                console.log('requested_on value:', data.requested_on);
                console.log('is_asap value:', data.is_asap);
                
                // Send data to server
                fetch('<?php echo SUPABASE_URL; ?>/rest/v1/blood_requests', {
                    method: 'POST',
                    headers: {
                        'apikey': '<?php echo SUPABASE_API_KEY; ?>',
                        'Authorization': 'Bearer <?php echo SUPABASE_API_KEY; ?>',
                        'Content-Type': 'application/json',
                        'Prefer': 'return=minimal'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => {
                    console.log('Request response status:', response.status);
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Error response body:', text);
                            // Try to parse as JSON to extract more details
                            try {
                                const errorJson = JSON.parse(text);
                                throw new Error(`Error ${response.status}: ${errorJson.message || errorJson.error || text}`);
                            } catch (jsonError) {
                                // If can't parse as JSON, use the raw text
                                throw new Error(`Error ${response.status}: ${text}`);
                            }
                        });
                    }
                    return response.text();
                })
                .then(result => {
                    console.log('Request submitted successfully:', result);
                    // Show success modal instead of alert
                    const msgModal = new bootstrap.Modal(document.getElementById('actionResultModal'));
                    const body = document.getElementById('actionResultBody');
                    if (body) { body.textContent = 'Blood request submitted successfully!'; }
                    msgModal.show();
                    // Reset and close after dismiss
                    const brm = bootstrap.Modal.getInstance(document.getElementById('bloodRequestModal'));
                    if (brm) brm.hide();
                    document.getElementById('actionResultModal').addEventListener('hidden.bs.modal', () => window.location.reload(), { once: true });
                })
                .catch(error => {
                    console.error('Error submitting request:', error);
                    const msgModal = new bootstrap.Modal(document.getElementById('actionResultModal'));
                    const body = document.getElementById('actionResultBody');
                    if (body) { body.textContent = 'Error submitting request: ' + error.message; }
                    msgModal.show();
                })
                .finally(() => {
                    // Restore button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
                };
                // Wire confirm button once
                const confirmBtn = document.getElementById('confirmSubmitBtn');
                confirmBtn.onclick = function() {
                    confirmModal.hide();
                    doSubmit();
                };
            });
        }
        
        // Handle when needed change
        const whenNeededSelect = document.getElementById('whenNeeded');
        const scheduleDateTimeDiv = document.getElementById('scheduleDateTime');
        
        if (whenNeededSelect && scheduleDateTimeDiv) {
            whenNeededSelect.addEventListener('change', function() {
                if (this.value === 'Scheduled') {
                    scheduleDateTimeDiv.classList.remove('d-none');
                    scheduleDateTimeDiv.style.opacity = 1;
                    scheduleDateTimeDiv.querySelector('input').required = true;
                } else {
                    scheduleDateTimeDiv.style.opacity = 0;
                    setTimeout(() => {
                        scheduleDateTimeDiv.classList.add('d-none');
                        scheduleDateTimeDiv.querySelector('input').required = false;
                    }, 500);
                }
            });
        }
        
        // Handle signature method toggle
        const uploadSignatureRadio = document.getElementById('uploadSignature');
        const drawSignatureRadio = document.getElementById('drawSignature');
        const signatureUploadDiv = document.getElementById('signatureUpload');
        const signaturePadDiv = document.getElementById('signaturePad');
        
        if (uploadSignatureRadio && drawSignatureRadio) {
            uploadSignatureRadio.addEventListener('change', function() {
                if (this.checked) {
                    signatureUploadDiv.classList.remove('d-none');
                    signaturePadDiv.classList.add('d-none');
                }
            });
            
            drawSignatureRadio.addEventListener('change', function() {
                if (this.checked) {
                    signatureUploadDiv.classList.add('d-none');
                    signaturePadDiv.classList.remove('d-none');
                    initSignaturePad();
                }
            });
        }
    });

    // Initialize signature pad
    function initSignaturePad() {
        const canvas = document.getElementById('physicianSignaturePad');
        if (!canvas) return;
        
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'white',
            penColor: 'black'
        });
        
        // Clear button
        const clearSignatureBtn = document.getElementById('clearSignature');
        if (clearSignatureBtn) {
            clearSignatureBtn.addEventListener('click', function() {
                signaturePad.clear();
            });
        }
        
        // Save button
        const saveSignatureBtn = document.getElementById('saveSignature');
        if (saveSignatureBtn) {
            saveSignatureBtn.addEventListener('click', function() {
                if (signaturePad.isEmpty()) {
                    alert('Please provide a signature first.');
                    return;
                }
                
                const signatureData = signaturePad.toDataURL();
                const signatureDataInput = document.getElementById('signatureData');
                if (signatureDataInput) {
                    signatureDataInput.value = signatureData;
                }
                alert('Signature saved!');
            });
        }
        
        // Resize canvas
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            signaturePad.clear(); // Clear the canvas
        }
        
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    }

    // Handle Print and Handover functionality
    document.addEventListener('DOMContentLoaded', function() {
        let currentRequestId = null;
        let currentStatus = null;

        // Update the button click handlers to show appropriate buttons (exclude modal buttons)
        document.querySelectorAll('.view-btn, .print-btn:not(#printRequestBtn), .handover-btn:not(#handoverRequestBtn)').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get data from button attributes
                const data = this.dataset;
                currentRequestId = data.requestId || data['request-id'];
                currentStatus = data.status;
                
                // Debug logging
                console.log('Button clicked - Data attributes:', data);
                console.log('Current Request ID:', currentRequestId);
                console.log('Current Status:', currentStatus);
                console.log('When needed from data:', data['when-needed']);
                console.log('All data attributes:', Object.keys(data));
                console.log('Debug when needed:', data['debug-when-needed']);
                
                try {
                    // Store request ID
                    const editRequestId = document.getElementById('editRequestId');
                    if (editRequestId) editRequestId.value = currentRequestId;
                    
                    // Populate view modal with request data
                    // Set current date
                    document.getElementById('modalCurrentDate').textContent = new Date().toLocaleDateString('en-US', { 
                        year: 'numeric', month: 'long', day: 'numeric' 
                    });
                    
                    // Set patient information
                    document.getElementById('modalPatientName').textContent = data.patientName || data['patient-name'] || '';
                    document.getElementById('modalPatientDetails').textContent = `${data.patientAge || data['patient-age'] || ''}, ${data.patientGender || data['patient-gender'] || ''}`;
                    
                    // Set request details
                    document.getElementById('modalDiagnosis').value = data.patientDiagnosis || data['patient-diagnosis'] || '';
                    document.getElementById('modalBloodType').textContent = data.bloodType || data['blood-type'] || '';
                    document.getElementById('modalRH').textContent = data.rhFactor || data['rh-factor'] || '';
                    document.getElementById('modalUnits').textContent = data.units || '';
                    
                    // Set when needed from dataset flags/values (dataset converts to camelCase)
                    const whenNeededRaw = data.whenNeeded || data['when-needed'];
                    const isAsapRaw = data.isAsap ?? data['is-asap'];
                    const isAsapFlag = (isAsapRaw === true || isAsapRaw === 'true' || isAsapRaw === 1 || isAsapRaw === '1');
                    console.log('Modal when_needed data:', whenNeededRaw, 'is_asap raw:', isAsapRaw, 'flag:', isAsapFlag);
                    const asapRadio = document.getElementById('modalAsap');
                    const schedRadio = document.getElementById('modalScheduled');
                    const schedInput = document.getElementById('modalScheduledDate');

                    // Reset controls first to avoid stale state from previous row
                    if (asapRadio) asapRadio.checked = false;
                    if (schedRadio) schedRadio.checked = false;
                    if (schedInput) schedInput.value = '';

                    // Helper: robustly parse various date formats into date input value (YYYY-MM-DD)
                    const toDatetimeLocal = (raw) => {
                        if (!raw) return '';
                        let d;
                        // If raw is only a date (YYYY-MM-DD), create local midnight
                        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                            d = new Date(raw + 'T00:00:00');
                        } else if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) { // mm/dd/yyyy
                            const [m, v, y] = raw.split('/');
                            d = new Date(parseInt(y,10), parseInt(m,10)-1, parseInt(v,10), 0, 0, 0);
                        } else {
                            d = new Date(raw);
                        }
                        if (isNaN(d.getTime())) return '';
                        const pad = (n) => String(n).padStart(2, '0');
                        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                    };

                    if (isAsapFlag) {
                        if (asapRadio) asapRadio.checked = true;
                        // Even if ASAP, still display the stored when_needed from DB if provided
                        const localVal = toDatetimeLocal(whenNeededRaw);
                        if (localVal && schedInput) schedInput.value = localVal;
                    } else {
                        // Not ASAP: mark scheduled and populate if available
                        if (schedRadio) schedRadio.checked = true;
                        const localVal = toDatetimeLocal(whenNeededRaw);
                        if (localVal && schedInput) schedInput.value = localVal;
                    }
                    
                    // Set hospital and physician
                    document.getElementById('modalHospital').value = '<?php echo $_SESSION['user_first_name'] ?? ''; ?>';
                    document.getElementById('modalPhysician').value = data.physicianName || data['physician-name'] || data['physician_name'] || '<?php echo $_SESSION['user_surname'] ?? ''; ?>';
                    
                    // Set approval info from DB columns (approved_by, approved_date)
                    (function(){
                        const approvedBy = data.approvedBy || data['approved-by'] || data['approved_by'] || '';
                        const approvedDateRaw = data.approvedDate || data['approved-date'] || data['approved_date'] || '';
                        let approvedDateText = '';
                        if (approvedDateRaw) {
                            const d = new Date(approvedDateRaw);
                            if (!isNaN(d.getTime())) {
                                approvedDateText = d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + ` at ` + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                            }
                        }
                        document.getElementById('modalApprovedBy').value = approvedBy && approvedDateText ? `Approved by ${approvedBy} - ${approvedDateText}` : approvedBy || approvedDateText || '';
                    })();

                    // Update modal status badge text/color based on DB status (modal-only rules)
                    const statusBadge = document.getElementById('modalStatusBadge');
                    if (statusBadge) {
                        // reset base classes and keep size/padding
                        statusBadge.classList.remove('bg-success', 'bg-primary', 'bg-warning', 'text-dark', 'bg-danger', 'bg-secondary');
                        let badgeText = currentStatus;
                        let badgeClass = 'bg-secondary';

                        if (currentStatus === 'Pending') {
                            badgeText = 'Pending';
                            badgeClass = 'bg-warning text-dark';
                        } else if (
                            currentStatus === 'Approved' ||
                            currentStatus === 'Accepted' ||
                            currentStatus === 'Confirmed'
                        ) {
                            // Approved-like: show blue Approved
                            badgeText = 'Approved';
                            badgeClass = 'bg-primary';
                        } else if (currentStatus === 'Printed') {
                            // Show actual word "Printed" in modal
                            badgeText = 'Printed';
                            badgeClass = 'bg-primary';
                        } else if (currentStatus === 'Handed_over') {
                            // Show actual word for handover
                            badgeText = 'Handed Over';
                            badgeClass = 'bg-primary';
                        } else if (currentStatus === 'Declined') {
                            badgeText = 'Declined';
                            badgeClass = 'bg-danger';
                        } else if (currentStatus === 'Completed') {
                            badgeText = 'Completed';
                            badgeClass = 'bg-success';
                        }

                        statusBadge.textContent = badgeText;
                        // preserve fs-6 px-3 py-2, add computed color classes
                        badgeClass.split(' ').forEach(cls => statusBadge.classList.add(cls));
                    }

                    // Show/hide handover and approval sections based on status
                    const handoverInfo = document.getElementById('handoverInfo');
                    const approvalInstructions = document.getElementById('approvalInstructions');
                    const handoverInstructions = document.getElementById('handoverInstructions');
                    const approvalSection = document.getElementById('approvalSection');
                    const approvalSeparator = document.getElementById('approvalSeparator');
                    const approvalText = approvalInstructions ? approvalInstructions.querySelector('p') : null;
                    
                    if (currentStatus === 'Handed_over' || currentStatus === 'Completed') {
                        // Handed over or Completed: show handover details & success notice, hide approval section
                        handoverInfo.style.display = 'block';
                        approvalInstructions.style.display = 'none';
                        handoverInstructions.style.display = 'block';
                        if (approvalSection) approvalSection.style.display = 'none';
                        if (approvalSeparator) approvalSeparator.style.display = 'none';

                        // Populate handover information
                        // Handover fields from DB (handed_over_by, handed_over_date) and physician_name for received by
                        const handedBy = data.handedOverBy || data['handed-over-by'] || data['handed_over_by'] || '';
                        const handedDateRaw = data.handedOverDate || data['handed-over-date'] || data['handed_over_date'] || '';
                        let handedDateText = '';
                        if (handedDateRaw) {
                            const hd = new Date(handedDateRaw);
                            if (!isNaN(hd.getTime())) {
                                handedDateText = hd.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                            }
                        }
                        document.getElementById('modalHandedOverBy').value = handedBy || handedDateText ? `(${handedBy}${handedBy && handedDateText ? ' - ' : (handedDateText ? '' : '')}${handedDateText})` : '';
                        const receiver = data.physicianName || data['physician-name'] || data['physician_name'] || '';
                        document.getElementById('modalReceivedBy').value = receiver ? `(${receiver}${handedDateText ? ' - ' + handedDateText : ''})` : (handedDateText ? `(${handedDateText})` : '');
                    } else if (
                        currentStatus === 'Approved' ||
                        currentStatus === 'Accepted' ||
                        currentStatus === 'Confirmed' ||
                        currentStatus === 'Printed'
                    ) {
                        // Approved-like states: show only approval section, hide handover
                        handoverInfo.style.display = 'none';
                        approvalInstructions.style.display = 'block';
                        if (approvalText) {
                            if (currentStatus === 'Printed') {
                                approvalText.textContent = 'The request has been printed. Please wait while an administrator confirms the receipt and the blood is prepared for delivery to your hospital.';
                            } else {
                                approvalText.textContent = "The blood request has been approved. Please print the receipt and provide it to the patient's representative for claiming at PRC.";
                            }
                        }
                        handoverInstructions.style.display = 'none';
                        if (approvalSection) approvalSection.style.display = 'block';
                        if (approvalSeparator) approvalSeparator.style.display = 'block';
                    } else {
                        // Other statuses: default behavior
                        handoverInfo.style.display = 'none';
                        approvalInstructions.style.display = (currentStatus === 'Pending') ? 'none' : 'block';
                        if (approvalText && approvalInstructions.style.display === 'block') {
                            approvalText.textContent = "The blood request has been approved. Please print the receipt and provide it to the patient's representative for claiming at PRC.";
                        }
                        handoverInstructions.style.display = 'none';
                        if (approvalSection) approvalSection.style.display = (currentStatus === 'Pending') ? 'none' : 'block';
                        if (approvalSeparator) approvalSeparator.style.display = (currentStatus === 'Pending') ? 'none' : 'block';
                    }

                    // Show/hide buttons based on current status
                    const printBtn = document.getElementById('printRequestBtn');
                    const handoverBtn = document.getElementById('handoverRequestBtn');
                    
                    console.log('Button visibility check - Current Status:', currentStatus);
                    console.log('Print button element:', printBtn);
                    console.log('Handover button element:', handoverBtn);
                    
                    if (printBtn && handoverBtn) {
                        // Set request ID in both buttons
                        printBtn.setAttribute('data-request-id', currentRequestId);
                        handoverBtn.setAttribute('data-request-id', currentRequestId);
                        
                        // Show print button for Approved, Accepted, or Confirmed status
                        if (currentStatus === 'Approved' || currentStatus === 'Accepted' || currentStatus === 'Confirmed') {
                            console.log('Showing print button for status:', currentStatus);
                            printBtn.style.display = 'inline-block';
                            handoverBtn.style.display = 'none';
                        } else if (currentStatus === 'Printed') {
                            // Printed: view only, no action buttons
                            printBtn.style.display = 'none';
                            handoverBtn.style.display = 'none';
                        } else if (currentStatus === 'Handed_over') {
                            // Handed_over: allow confirm arrival
                            handoverBtn.style.display = 'inline-block';
                            printBtn.style.display = 'none';
                        } else {
                            // Hide both buttons for other statuses
                            console.log('Hiding both buttons for status:', currentStatus);
                            printBtn.style.display = 'none';
                            handoverBtn.style.display = 'none';
                        }
                    } else {
                        console.error('Print or handover button not found!');
                    }

                    // Show the view modal
                    const modal = document.getElementById('bloodReorderModal');
                    if (modal) {
                        const bsModal = new bootstrap.Modal(modal);
                        bsModal.show();
                        
                        // Store the request ID globally for debugging
                        window.currentModalRequestId = currentRequestId;
                        console.log('Modal opened with request ID:', currentRequestId);
                        
                        // Double-check button visibility after modal is shown
                        setTimeout(() => {
                            const printBtn = document.getElementById('printRequestBtn');
                            const handoverBtn = document.getElementById('handoverRequestBtn');
                            console.log('Post-modal button check - Print:', printBtn?.style.display, 'Handover:', handoverBtn?.style.display);
                        }, 100);
                    }
                } catch (error) {
                    console.error('Error displaying view modal:', error);
                }
            });
        });

        // Handle Print button click
        document.getElementById('printRequestBtn').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Print button clicked - Current Request ID:', currentRequestId);
            
            // Get request ID from multiple sources
            let requestId = currentRequestId || this.getAttribute('data-request-id') || window.currentModalRequestId;
            
            // If still no request ID, try to get it from the original button that opened the modal
            if (!requestId) {
                const originalButton = document.querySelector('.print-btn[data-request-id]');
                if (originalButton) {
                    requestId = originalButton.getAttribute('data-request-id');
                }
            }
            
            if (!requestId) {
                console.error('No request ID available for printing');
                alert('No request selected for printing.');
                return;
            }

            console.log('Opening print page with request ID:', requestId);
            // Open print page in new tab - don't close the modal
            window.open(`../../src/views/forms/print-blood-request.php?request_id=${requestId}`, '_blank');
        });

        // Handle Hand Over button click
        document.getElementById('handoverRequestBtn').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get request ID from multiple sources
            let requestId = currentRequestId || this.getAttribute('data-request-id') || window.currentModalRequestId;
            
            if (!requestId) {
                alert('No request selected for handover.');
                return;
            }

            // Show loading state
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';

            // Update status to 'Completed'
            fetch(`${SUPABASE_URL}/rest/v1/blood_requests?request_id=eq.${requestId}`, {
                method: 'PATCH',
                headers: {
                    'apikey': SUPABASE_KEY,
                    'Authorization': `Bearer ${SUPABASE_KEY}`,
                    'Content-Type': 'application/json',
                    'Prefer': 'return=minimal'
                },
                body: JSON.stringify({
                    status: 'Completed'
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`Error ${response.status}: ${text}`);
                    });
                }
                return response.text();
            })
            .then(result => {
                console.log('Request handed over successfully:', result);
                // Show proper modal instead of alert
                const msgModal = new bootstrap.Modal(document.getElementById('actionResultModal'));
                const body = document.getElementById('actionResultBody');
                if (body) {
                    body.textContent = 'Request has been marked as completed (handed over) successfully!';
                }
                msgModal.show();
                
                // Close view modal and refresh after dismissal
                const currentView = bootstrap.Modal.getInstance(document.getElementById('bloodReorderModal'));
                if (currentView) currentView.hide();
                const modalEl = document.getElementById('actionResultModal');
                modalEl.addEventListener('hidden.bs.modal', () => window.location.reload(), { once: true });
            })
            .catch(error => {
                console.error('Error handing over request:', error);
                const msgModal = new bootstrap.Modal(document.getElementById('actionResultModal'));
                const body = document.getElementById('actionResultBody');
                if (body) {
                    body.textContent = 'Error handing over request: ' + error.message;
                }
                msgModal.show();
            })
            .finally(() => {
                // Restore button state
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });

        // Listen for print completion messages from print page
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'print_completed') {
                console.log('Print completed for request ID:', event.data.requestId);
                
                // Reload the page to update the data
                window.location.reload();
                
                // Show success modal after a short delay
                setTimeout(() => {
                    const successModal = new bootstrap.Modal(document.getElementById('printSuccessModal'));
                    successModal.show();
                }, 500);
            }
        });
    });
    </script>
</body>
</html> 