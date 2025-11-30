<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/check_account_hospital_modal.php';
require_once '../../assets/php_func/hospital_request_diagnosis_options.php';

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

// Function to fetch user image from users table
function fetchUserImage($user_id) {
    if (empty($user_id)) {
        return null;
    }
    
    $ch = curl_init();
    
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    
    $url = SUPABASE_URL . '/rest/v1/users?user_id=eq.' . $user_id . '&select=user_image';
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!empty($data) && is_array($data) && isset($data[0]['user_image']) && !empty($data[0]['user_image'])) {
        return $data[0]['user_image'];
    }
    
    return null;
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
$hospital_location = $_SESSION['hospital_location'] ?? ($_SESSION['hospital_name'] ?? ($_SESSION['user_first_name'] ?? ''));

// Fetch user image from users table
$user_image = fetchUserImage($_SESSION['user_id'] ?? '');
$header_logo_src = !empty($user_image) ? $user_image : '../../assets/image/PRC_Logo.png';
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
        
        /* Diagnosis Dropdown Styling */
        #bloodRequestModal #patient_diagnosis {
            border-radius: 12px;
            padding: 12px 16px;
            margin: 8px 0;
            border: 1px solid #dee2e6;
            background-color: #fff;
            transition: all 0.3s ease;
            outline: none !important;
        }
        
        #bloodRequestModal #patient_diagnosis:hover {
            border-color: #941022;
            background-color: #fff;
            outline: none !important;
            box-shadow: none;
        }
        
        #bloodRequestModal #patient_diagnosis:focus {
            border-color: #941022;
            box-shadow: 0 0 0 0.2rem rgba(148, 16, 34, 0.25);
            outline: none !important;
            background-color: #fff;
        }
        
        #bloodRequestModal #patient_diagnosis:active {
            border-color: #941022;
            outline: none !important;
            background-color: #fff;
        }
        
        #bloodRequestModal #patient_diagnosis option {
            padding: 12px 16px;
            margin: 4px 0;
            border-radius: 8px;
            background-color: #fff;
            transition: background-color 0.2s ease;
        }
        
        #bloodRequestModal #patient_diagnosis option:hover {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }
        
        #bloodRequestModal #patient_diagnosis option:checked,
        #bloodRequestModal #patient_diagnosis option:focus {
            background-color: #941022 !important;
            color: #fff !important;
            font-weight: 600;
        }
        
        /* Prevent browser default blue styling on hover */
        #bloodRequestModal #patient_diagnosis * {
            color: inherit !important;
        }
        
        #bloodRequestModal #patient_diagnosis optgroup {
            padding: 8px 0;
            font-weight: 600;
            color: #495057;
        }
        
        #bloodRequestModal #patient_diagnosis optgroup option {
            padding-left: 24px;
            font-weight: normal;
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

        /* Highlight missing required fields */
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6 .4.4.4-.4m0 4.8-.4-.4-.4.4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
            padding-right: calc(1.5em + 0.75rem);
        }
        
        .form-control.is-invalid:focus,
        .form-select.is-invalid:focus {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
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
            grid-template-columns: repeat(3, 1fr);
             gap: 20px;
             margin-bottom: 30px;
        }
        
        .summary-card {
             background: white;
             border-radius: 8px;
             padding: 32px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
             text-align: center;
             display: flex;
             flex-direction: column;
             align-items: center;
             justify-content: center;
             min-height: 150px;
             transition: all 0.3s ease;
        }
        
        .summary-card-title {
             color: #941022;
             font-size: 1.2rem;
             font-weight: 600;
             margin-bottom: 16px;
        }
        
        .summary-card-number {
             color: #941022;
             font-size: 3rem;
             font-weight: bold;
             margin: 0;
        }
        
        /* Highlight animation for recently changed status */
        .summary-card.highlight-approval {
            animation: highlightPulse 2s ease-in-out;
            border: 3px solid #28a745;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
        }
        
        .summary-card.highlight-increase {
            animation: highlightIncrease 2s ease-in-out infinite;
            border: 3px solid #dc3545;
            box-shadow: 0 0 20px rgba(220, 53, 69, 0.5);
            background-color: rgba(220, 53, 69, 0.05);
        }
        
        .summary-card-number.increased {
            animation: numberPulse 1s ease-in-out infinite;
            color: #dc3545 !important;
        }
        
        @keyframes highlightPulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 30px rgba(40, 167, 69, 0.8);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
            }
        }
        
        @keyframes highlightIncrease {
            0% {
                transform: scale(1);
                box-shadow: 0 0 20px rgba(220, 53, 69, 0.5);
                background-color: rgba(220, 53, 69, 0.05);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 30px rgba(220, 53, 69, 0.8);
                background-color: rgba(220, 53, 69, 0.1);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 20px rgba(220, 53, 69, 0.5);
                background-color: rgba(220, 53, 69, 0.05);
            }
        }
        
        @keyframes numberPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
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

/* Action column should not be sortable */
th:last-child {
    cursor: default !important;
}

th:last-child .sort-indicator {
    display: none !important;
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
        
        .filter-dropdown {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 8px 12px;
            background: white;
        }
        
        .search-input-wrapper {
            flex: 1;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 8px 40px 8px 12px; /* Extra padding on right for spinner */
            background: white;
        }
        
        .search-loading-spinner {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
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
             <img src="<?php echo htmlspecialchars($header_logo_src); ?>" alt="PRC" class="header-logo">
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
            <div class="summary-card" id="pendingCard">
                <div class="summary-card-title">Pending Requests</div>
                <div class="summary-card-number"><?php echo $summary_stats['pending']; ?></div>
            </div>
            <div class="summary-card" id="approvedCard">
                <div class="summary-card-title">Approved Requests</div>
                <div class="summary-card-number"><?php echo $summary_stats['approved']; ?></div>
            </div>
            <div class="summary-card" id="completedCard">
                <div class="summary-card-title">Completed Requests</div>
                <div class="summary-card-number"><?php echo $summary_stats['completed']; ?></div>
            </div>
        </div>

        <!-- Filter and Search Bar -->
        <div class="filter-search-bar">
            <select class="filter-dropdown" id="statusFilterDropdown">
                <option value="All Status">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Declined">Declined</option>
                <option value="Completed">Completed</option>
            </select>
            <div class="search-input-wrapper">
                <input type="text" class="search-input" placeholder="Search requests..." id="requestSearchBar">
                <span class="search-loading-spinner" id="searchLoadingSpinner" style="display: none;">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="color: #941022;">
                        <span class="visually-hidden">Loading...</span>
                    </span>
                </span>
            </div>
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
                        <tr class="table-row" data-row-index="<?php echo $rowNum - 1; ?>">
                            <td><?php echo $rowNum++; ?></td>
                            <td><?php 
                                // Display 14 characters of request_reference, skipping "REQ-" prefix
                                $request_ref = $request['request_reference'] ?? '';
                                if (!empty($request_ref)) {
                                    // Skip "REQ-" (4 characters) and take next 14 characters
                                    $display_ref = substr($request_ref, 4, 14);
                                    echo htmlspecialchars($display_ref);
                                } else {
                                    echo htmlspecialchars($request['request_id']);
                                }
                            ?></td>
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
                                        data-declined-by="<?php echo htmlspecialchars($request['declined_by'] ?? ''); ?>"
                                        data-declined-date="<?php echo htmlspecialchars($request['last_updated'] ?? ''); ?>"
                                        data-decline-reason="<?php echo htmlspecialchars($request['decline_reason'] ?? ''); ?>"
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
                                        data-declined-by="<?php echo htmlspecialchars($request['declined_by'] ?? ''); ?>"
                                        data-declined-date="<?php echo htmlspecialchars($request['last_updated'] ?? ''); ?>"
                                        data-decline-reason="<?php echo htmlspecialchars($request['decline_reason'] ?? ''); ?>"
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
                                        data-declined-by="<?php echo htmlspecialchars($request['declined_by'] ?? ''); ?>"
                                        data-declined-date="<?php echo htmlspecialchars($request['last_updated'] ?? ''); ?>"
                                        data-decline-reason="<?php echo htmlspecialchars($request['decline_reason'] ?? ''); ?>"
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
                                        data-declined-by="<?php echo htmlspecialchars($request['declined_by'] ?? ''); ?>"
                                        data-declined-date="<?php echo htmlspecialchars($request['last_updated'] ?? ''); ?>"
                                        data-decline-reason="<?php echo htmlspecialchars($request['decline_reason'] ?? ''); ?>"
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
                                        data-declined-by="<?php echo htmlspecialchars($request['declined_by'] ?? ''); ?>"
                                        data-declined-date="<?php echo htmlspecialchars($request['last_updated'] ?? ''); ?>"
                                        data-decline-reason="<?php echo htmlspecialchars($request['decline_reason'] ?? ''); ?>"
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
                                        data-declined-by="<?php echo htmlspecialchars($request['declined_by'] ?? ''); ?>"
                                        data-declined-date="<?php echo htmlspecialchars($request['last_updated'] ?? ''); ?>"
                                        data-decline-reason="<?php echo htmlspecialchars($request['decline_reason'] ?? ''); ?>"
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
            <div class="pagination" id="pagination">
                <!-- Pagination buttons will be generated dynamically -->
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
                        <?php echo renderDiagnosisDropdown('', 'patient_diagnosis', 'patient_diagnosis', true); ?>
                    </div>
                    <!-- Other Diagnosis Input (shown when "Other" is selected) -->
                    <div class="mb-3 d-none" id="other_diagnosis_container">
                        <label class="form-label" for="other_diagnosis_input">Please specify:</label>
                        <input type="text" class="form-control" id="other_diagnosis_input" name="other_diagnosis" placeholder="Enter diagnosis details">
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
                    <input type="hidden" name="blood_component" value="Whole Blood">
                    <div class="mb-3 row gx-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Number of Units</label>
                            <input type="number" class="form-control" name="units_requested" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">When Needed</label>
                            <select id="whenNeeded" class="form-select" name="when_needed" required>
                                <option value="ASAP">ASAP</option>
                                <option value="Scheduled">Scheduled</option>
                            </select>
                        </div>
                    </div>
                    <!-- Location field hidden in form, shown only in review -->
                    <div class="mb-3 d-none">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($hospital_location); ?>" readonly>
                    </div>
                    <div id="scheduleDateTime" class="mb-3 d-none">
                        <label class="form-label">Scheduled Date & Time</label>
                        <input type="datetime-local" class="form-control" name="scheduled_datetime" id="scheduled_datetime">
                        <div class="invalid-feedback" id="datetime-error" style="display: none;">
                            The selected date and time cannot be in the past. Please select a future date and time.
                        </div>
                    </div>

                    <!-- Additional Information (hidden in step UI; shown in summary only) -->
                    <input type="hidden" name="hospital_admitted" value="<?php echo htmlspecialchars($hospital_location); ?>">
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
                                    <div class="list-group-item"><strong>Location:</strong> <span id="reviewHospital"></span></div>
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

                <!-- Decline Section (visible when status is Declined) -->
                <div class="mb-4" id="declineSection" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Declined by:</label>
                        <input type="text" class="form-control" id="modalDeclinedBy" placeholder="(Declined by Staff – Date and time)" readonly style="background-color: #f8f9fa;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason</label>
                        <textarea class="form-control" id="modalDeclineReason" rows="3" readonly style="background-color: #f8f9fa;"></textarea>
                    </div>
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
    <!-- Filter Loading Modal -->
    <script src="../../assets/js/filter-loading-modal.js"></script>
    <!-- Hospital Blood Requests Filter and Search -->
    <script src="../../assets/js/search_func/filter_search_hospital_blood_requests.js"></script>
    <script type="module" src="../../assets/js/handed-over-notify.js"></script>
    <!-- Hospital Request Diagnosis Handler -->
    <script src="../../assets/js/hospital-request-diagnosis-handler.js"></script>

    <script>
        // Configure one-time per-account notification for Handed_over (handled in external module)
        <?php
        // Ensure all variables are set to prevent PHP warnings
        $user_id_js = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest';
        $handed_over_ids_js = isset($handed_over_ids) ? $handed_over_ids : [];
        $recently_approved_js = isset($recently_approved) ? $recently_approved : false;
        $supabase_url_js = defined('SUPABASE_URL') ? SUPABASE_URL : '';
        $supabase_key_js = defined('SUPABASE_API_KEY') ? SUPABASE_API_KEY : '';
        ?>
        window.HandedOverNotifyConfig = {
            userId: <?php echo json_encode($user_id_js); ?>,
            handedOverIds: <?php echo json_encode($handed_over_ids_js); ?>,
            modalSelector: '#printSuccessModal',
            viewButtonSelector: '#printSuccessModal .btn-primary',
            buildViewUrl: (id) => `../../src/views/forms/print-blood-request.php?request_id=${id}`
        };
        // Define Supabase constants
        const SUPABASE_URL = <?php echo json_encode($supabase_url_js); ?>;
        const SUPABASE_KEY = <?php echo json_encode($supabase_key_js); ?>;
        
        // Check for recently approved requests and highlight
        const recentlyApproved = <?php echo json_encode($recently_approved_js); ?>;
        let lastCheckedApprovalTime = null;
        
        // Cache-based determiner using localStorage
        const CACHE_KEY = 'blood_requests_summary_cache_' + <?php echo json_encode($user_id_js); ?>;
        const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes cache duration
        
        // Initialize cache with current counts
        function initializeCache() {
            const currentCounts = {
                pending: <?php echo json_encode($summary_stats['pending'] ?? 0); ?>,
                approved: <?php echo json_encode($summary_stats['approved'] ?? 0); ?>,
                completed: <?php echo json_encode($summary_stats['completed'] ?? 0); ?>,
                lastCheck: Date.now(),
                lastTimestamps: {
                    latestPending: null,
                    latestApproved: null,
                    latestCompleted: null
                }
            };
            
            try {
                localStorage.setItem(CACHE_KEY, JSON.stringify(currentCounts));
            } catch (e) {
                console.warn('localStorage not available, using in-memory cache');
            }
            
            return currentCounts;
        }
        
        // Get cached data
        function getCachedData() {
            try {
                const cached = localStorage.getItem(CACHE_KEY);
                if (cached) {
                    const data = JSON.parse(cached);
                    // Check if cache is still valid
                    if (Date.now() - data.lastCheck < CACHE_DURATION) {
                        return data;
                    }
                }
            } catch (e) {
                console.warn('Error reading cache:', e);
            }
            return null;
        }
        
        // Update cache with new data
        function updateCache(counts, timestamps) {
            const cacheData = {
                pending: counts.pending,
                approved: counts.approved,
                completed: counts.completed,
                lastCheck: Date.now(),
                lastTimestamps: timestamps
            };
            
            try {
                localStorage.setItem(CACHE_KEY, JSON.stringify(cacheData));
            } catch (e) {
                console.warn('Error updating cache:', e);
            }
            
            return cacheData;
        }
        
        // Store initial counts for comparison
        let previousCounts = initializeCache();
        
        function checkForSummaryUpdates() {
            // Fetch current summary statistics with timestamp fields
            const userId = <?php echo json_encode($_SESSION['user_id'] ?? ''); ?>;
            fetch(SUPABASE_URL + '/rest/v1/blood_requests?user_id=eq.' + encodeURIComponent(userId) + '&select=status,requested_on,approved_date,handed_over_date,last_updated&order=last_updated.desc', {
                headers: {
                    'apikey': SUPABASE_KEY,
                    'Authorization': 'Bearer ' + SUPABASE_KEY
                }
            })
            .then(response => response.json())
            .then(data => {
                if (!data || !Array.isArray(data)) return;
                
                // Get cached data for comparison
                const cachedData = getCachedData() || previousCounts;
                
                // Calculate current counts and track latest timestamps
                const currentCounts = {
                    pending: 0,
                    approved: 0,
                    completed: 0
                };
                
                const latestTimestamps = {
                    latestPending: cachedData.lastTimestamps?.latestPending || null,
                    latestApproved: cachedData.lastTimestamps?.latestApproved || null,
                    latestCompleted: cachedData.lastTimestamps?.latestCompleted || null
                };
                
                // Track the most recent last_updated for any status change detection
                let latestLastUpdated = null;
                
                data.forEach(request => {
                    const status = request.status || '';
                    const requestedOn = request.requested_on || null;
                    const approvedDate = request.approved_date || null;
                    const handedOverDate = request.handed_over_date || null;
                    const lastUpdated = request.last_updated || null;
                    
                    // Track latest last_updated for any status change
                    if (lastUpdated && (!latestLastUpdated || lastUpdated > latestLastUpdated)) {
                        latestLastUpdated = lastUpdated;
                    }
                    
                    if (status === 'Pending') {
                        currentCounts.pending++;
                        // Track the most recent pending request timestamp (use last_updated as fallback)
                        const pendingTimestamp = requestedOn || lastUpdated;
                        if (pendingTimestamp && (!latestTimestamps.latestPending || pendingTimestamp > latestTimestamps.latestPending)) {
                            latestTimestamps.latestPending = pendingTimestamp;
                        }
                    } else if (status === 'Approved' || status === 'Printed' || status === 'Handed_over') {
                        currentCounts.approved++;
                        // Track the most recent approved request timestamp (use last_updated as fallback if approved_date is null)
                        const approvedTimestamp = approvedDate || lastUpdated;
                        if (approvedTimestamp && (!latestTimestamps.latestApproved || approvedTimestamp > latestTimestamps.latestApproved)) {
                            latestTimestamps.latestApproved = approvedTimestamp;
                        }
                    } else if (status === 'Completed') {
                        currentCounts.completed++;
                        // Track the most recent completed request timestamp (use last_updated as fallback)
                        const completedTimestamp = handedOverDate || lastUpdated;
                        if (completedTimestamp && (!latestTimestamps.latestCompleted || completedTimestamp > latestTimestamps.latestCompleted)) {
                            latestTimestamps.latestCompleted = completedTimestamp;
                        }
                    }
                });
                
                // Compare with cached data to detect NEW changes
                const now = Date.now();
                const recentThreshold = 10 * 60 * 1000; // 10 minutes threshold (more lenient)
                
                // Check for pending increases
                if (currentCounts.pending > cachedData.pending) {
                    // Check if the latest pending timestamp is new (not in cache)
                    const latestPendingTime = latestTimestamps.latestPending ? new Date(latestTimestamps.latestPending).getTime() : 0;
                    const cachedPendingTime = cachedData.lastTimestamps?.latestPending ? new Date(cachedData.lastTimestamps.latestPending).getTime() : 0;
                    
                    // Highlight if timestamp is newer OR if count increased (detect any new pending)
                    if (latestPendingTime > cachedPendingTime || latestPendingTime === 0) {
                        // Check if it's recent enough, or if it's a new timestamp we haven't seen
                        if ((latestPendingTime > cachedPendingTime && (now - latestPendingTime) <= recentThreshold) || 
                            (latestPendingTime === 0 && cachedPendingTime === 0)) {
                            highlightCardIncrease('pendingCard', currentCounts.pending);
                        } else {
                            updateCardCount('pendingCard', currentCounts.pending);
                        }
                    } else {
                        updateCardCount('pendingCard', currentCounts.pending);
                    }
                } else if (currentCounts.pending !== cachedData.pending) {
                    // Count changed but didn't increase (edge case)
                    updateCardCount('pendingCard', currentCounts.pending);
                }
                
                // Check for approved increases - this is the key one for status changes
                if (currentCounts.approved > cachedData.approved) {
                    const latestApprovedTime = latestTimestamps.latestApproved ? new Date(latestTimestamps.latestApproved).getTime() : 0;
                    const cachedApprovedTime = cachedData.lastTimestamps?.latestApproved ? new Date(cachedData.lastTimestamps.latestApproved).getTime() : 0;
                    
                    // Always highlight if count increased - this catches manual DB changes and normal approvals
                    // The count increase itself is the indicator that something changed
                    const timeSinceLastCheck = now - (cachedData.lastCheck || 0);
                    const isRecentCheck = timeSinceLastCheck <= recentThreshold;
                    
                    // Highlight if count increased and it's been checked recently (within threshold)
                    // OR if the timestamp is newer (catches new approvals with timestamps)
                    if (isRecentCheck || latestApprovedTime > cachedApprovedTime) {
                        highlightCardIncrease('approvedCard', currentCounts.approved);
                    } else {
                        // Still update the count even if not recent
                        updateCardCount('approvedCard', currentCounts.approved);
                    }
                } else if (currentCounts.approved < cachedData.approved) {
                    // Count decreased - remove highlight and return to normal
                    removeCardHighlight('approvedCard', currentCounts.approved);
                } else if (currentCounts.approved !== cachedData.approved) {
                    updateCardCount('approvedCard', currentCounts.approved);
                }
                
                // Check for completed increases
                if (currentCounts.completed > cachedData.completed) {
                    const latestCompletedTime = latestTimestamps.latestCompleted ? new Date(latestTimestamps.latestCompleted).getTime() : 0;
                    const cachedCompletedTime = cachedData.lastTimestamps?.latestCompleted ? new Date(cachedData.lastTimestamps.latestCompleted).getTime() : 0;
                    
                    // Always highlight if count increased - this catches status changes to Completed
                    // The count increase itself is the indicator that something changed
                    const timeSinceLastCheck = now - (cachedData.lastCheck || 0);
                    const isRecentCheck = timeSinceLastCheck <= recentThreshold;
                    
                    // Always highlight when count increased - catches all status changes to Completed
                    // This handles manual DB changes and normal status transitions
                    if (isRecentCheck || latestCompletedTime > cachedCompletedTime || currentCounts.completed > cachedData.completed) {
                        highlightCardIncrease('completedCard', currentCounts.completed);
                    } else {
                        // Still update the count even if not recent
                        updateCardCount('completedCard', currentCounts.completed);
                    }
                } else if (currentCounts.completed < cachedData.completed) {
                    // Count decreased - remove highlight
                    removeCardHighlight('completedCard', currentCounts.completed);
                } else if (currentCounts.completed === cachedData.completed) {
                    // Count reverted back to original - remove highlight if it exists
                    const completedCard = document.getElementById('completedCard');
                    if (completedCard && completedCard.classList.contains('highlight-increase')) {
                        removeCardHighlight('completedCard', currentCounts.completed);
                    }
                } else if (currentCounts.completed !== cachedData.completed) {
                    updateCardCount('completedCard', currentCounts.completed);
                }
                
                // Update cache with new data
                previousCounts = updateCache(currentCounts, latestTimestamps);
            })
            .catch(error => {
                console.error('Error checking for summary updates:', error);
            });
        }
        
        // Helper function to update card count without highlighting
        function updateCardCount(cardId, newCount) {
            const card = document.getElementById(cardId);
            if (card) {
                const numberElement = card.querySelector('.summary-card-number');
                if (numberElement) {
                    numberElement.textContent = newCount;
                }
            }
        }
        
        // Helper function to remove highlight when count decreases
        function removeCardHighlight(cardId, newCount) {
            const card = document.getElementById(cardId);
            if (card) {
                // Remove highlight classes
                card.classList.remove('highlight-increase');
                const numberElement = card.querySelector('.summary-card-number');
                if (numberElement) {
                    numberElement.classList.remove('increased');
                    numberElement.textContent = newCount;
                    // Reset color to original
                    numberElement.style.color = '';
                }
            }
        }
        
        function highlightCardIncrease(cardId, newCount) {
            const card = document.getElementById(cardId);
            if (!card) return;
            
            const numberElement = card.querySelector('.summary-card-number');
            if (!numberElement) return;
            
            // Remove any existing highlight first
            card.classList.remove('highlight-increase');
            numberElement.classList.remove('increased');
            
            // Force reflow to restart animation
            void card.offsetWidth;
            
            // Add highlight class
            card.classList.add('highlight-increase');
            
            // Add increased class to number
            numberElement.classList.add('increased');
            
            // Update the count
            numberElement.textContent = newCount;
            
            // Keep the highlight persistent - don't remove it automatically
            // The highlight will continue until the page is refreshed or manually cleared
        }
        
        function checkForStatusChanges() {
            // Fetch current requests to check for new approvals
            const userId = <?php echo json_encode($_SESSION['user_id'] ?? ''); ?>;
            fetch(SUPABASE_URL + '/rest/v1/blood_requests?user_id=eq.' + encodeURIComponent(userId) + '&status=eq.Approved&order=approved_date.desc&limit=1', {
                headers: {
                    'apikey': SUPABASE_KEY,
                    'Authorization': 'Bearer ' + SUPABASE_KEY
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    const latestApproval = data[0];
                    if (latestApproval.approved_date) {
                        const approvedTime = latestApproval.approved_date;
                        
                        // Only highlight if this is a new approval (different from last checked)
                        if (lastCheckedApprovalTime !== approvedTime) {
                            const approvedTimeStamp = new Date(approvedTime).getTime();
                            const now = new Date().getTime();
                            const timeDiff = (now - approvedTimeStamp) / 1000; // seconds
                            
                            // If approved within last 60 seconds
                            if (timeDiff <= 60) {
                                const approvedCard = document.getElementById('approvedCard');
                                if (approvedCard) {
                                    approvedCard.classList.add('highlight-approval');
                                    
                                    // Update the count if needed
                                    const currentCount = parseInt(approvedCard.querySelector('.summary-card-number').textContent) || 0;
                                    const newCount = <?php echo json_encode($summary_stats['approved'] ?? 0); ?>;
                                    if (newCount > currentCount) {
                                        approvedCard.querySelector('.summary-card-number').textContent = newCount;
                                    }
                                    
                                    // Remove highlight after animation
                                    setTimeout(() => {
                                        approvedCard.classList.remove('highlight-approval');
                                    }, 2000);
                                }
                                
                                lastCheckedApprovalTime = approvedTime;
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for status changes:', error);
            });
        }
        
        document.addEventListener("DOMContentLoaded", function () {
            // Highlight Approved card if a request was recently approved on page load
            if (recentlyApproved) {
                const approvedCard = document.getElementById('approvedCard');
                if (approvedCard) {
                    approvedCard.classList.add('highlight-approval');
                    
                    // Remove highlight after animation completes
                    setTimeout(() => {
                        approvedCard.classList.remove('highlight-approval');
                    }, 2000);
                }
            }
            
            // Check for status changes periodically (every 10 seconds)
            setInterval(function() {
                checkForStatusChanges();
            }, 10000);
            
            // Check for summary updates periodically (every 10 seconds for faster detection)
            setInterval(function() {
                checkForSummaryUpdates();
            }, 10000);
            
            // Initial checks after 2 seconds
            setTimeout(checkForStatusChanges, 2000);
            setTimeout(checkForSummaryUpdates, 2000);
            
            // Also add a manual refresh function that can be called
            window.refreshSummaryCards = function() {
                // Clear cache to force fresh check
                try {
                    localStorage.removeItem(CACHE_KEY);
                } catch (e) {
                    console.warn('Could not clear cache:', e);
                }
                previousCounts = initializeCache();
                checkForSummaryUpdates();
            };
            
            // Table sorting initialization
    let headers = document.querySelectorAll("th");
    
    if (!headers || headers.length === 0) return;

    headers.forEach((header, index) => {
                // Exclude Action column (last column, index 6)
                // Add sorting to: No., Request ID, Blood Type, Units Needed, Date Needed, Status
        const isActionColumn = index === headers.length - 1;
        
                // Remove any existing sort indicators from Action column
                if (isActionColumn && header) {
                    const existingIndicator = header.querySelector(".sort-indicator");
                    if (existingIndicator) {
                        existingIndicator.remove();
                    }
                    // Remove cursor pointer style
                    header.style.cursor = 'default';
                    return; // Skip Action column completely
                }
                
                if (!isActionColumn && header) {
                    // Remove any existing sort indicators first
                    const existingIndicator = header.querySelector(".sort-indicator");
                    if (existingIndicator) {
                        existingIndicator.remove();
                    }
                    
            // Create a single sorting indicator for each column
            let icon = document.createElement("span");
            icon.classList.add("sort-indicator");
            icon.textContent = " ▼"; // Default neutral state
            header.appendChild(icon);

            // Add click event listener to sort the column
                    header.addEventListener("click", function (e) {
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
    // Column indices: 0=No., 1=Request ID, 2=Blood Type, 3=Units Needed, 4=Date Needed, 5=Status, 6=Action
    let type = (columnIndex === 0 || columnIndex === 1) ? "number" : 
               (columnIndex === 4) ? "date" : "text";

    // Status order: Pending, Approved, Completed, Declined (and others)
    const statusOrder = {
        'Pending': 1,
        'Approved': 2,
        'Completed': 3,
        'Declined': 4,
        'Rescheduled': 5
    };

    // Sort the rows
    rows.sort((rowA, rowB) => {
        let cellA = rowA.cells[columnIndex].textContent.trim();
        let cellB = rowB.cells[columnIndex].textContent.trim();
        
        // For Status column, extract badge text and use custom order
        if (columnIndex === 5) {
            const badgeA = rowA.cells[columnIndex].querySelector('.badge');
            const badgeB = rowB.cells[columnIndex].querySelector('.badge');
            if (badgeA) cellA = badgeA.textContent.trim();
            if (badgeB) cellB = badgeB.textContent.trim();
            
            // Use custom status order
            const orderA = statusOrder[cellA] || 999;
            const orderB = statusOrder[cellB] || 999;
            
            if (orderA !== orderB) {
                return isAscending ? orderA - orderB : orderB - orderA;
            }
            // If same order, fall back to alphabetical
            return isAscending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        }

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
                    if (this.value === 'Scheduled' || this.value === 'ASAP') { // Show for both Scheduled and ASAP
                        scheduleDateTime.classList.remove('d-none'); // Show the date picker
                        scheduleDateTime.style.opacity = 0;
                        setTimeout(() => scheduleDateTime.style.opacity = 1, 10); // Smooth fade-in
                        const dateInput = scheduleDateTime.querySelector('input');
                        if (dateInput) {
                            dateInput.required = true;
                            // Set minimum to current date/time
                            const now = new Date();
                            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                            dateInput.min = now.toISOString().slice(0, 16);
                            
                            // Add validation on change
                            dateInput.addEventListener('change', function() {
                                const selectedDateTime = new Date(this.value);
                                const currentDateTime = new Date();
                                const errorDiv = document.getElementById('datetime-error');
                                
                                // Clear previous validation
                                this.classList.remove('is-invalid');
                                if (errorDiv) {
                                    errorDiv.style.display = 'none';
                                }
                                
                                // Check if selected time is in the past
                                if (selectedDateTime <= currentDateTime) {
                                    this.classList.add('is-invalid');
                                    if (errorDiv) {
                                        errorDiv.style.display = 'block';
                                    }
                                }
                            });
                        }
                    } else {
                        scheduleDateTime.style.opacity = 0;
                        setTimeout(() => scheduleDateTime.classList.add('d-none'), 500); // Hide after fade-out
                        const dateInput = scheduleDateTime.querySelector('input');
                        if (dateInput) {
                            dateInput.required = false;
                            dateInput.value = '';
                            dateInput.classList.remove('is-invalid');
                            const errorDiv = document.getElementById('datetime-error');
                            if (errorDiv) {
                                errorDiv.style.display = 'none';
                            }
                        }
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

        // Note: Search and filter functionality is now handled by 
        // filter_search_hospital_blood_requests.js (server-side filtering)
        // The old client-side search has been replaced with API-based filtering
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
            // Handle diagnosis - check if it's a select dropdown or input, and handle "Other"
            const diagnosisSelect = modalEl.querySelector('#patient_diagnosis');
            const otherDiagnosisInput = modalEl.querySelector('#other_diagnosis_input');
            let diagnosis = '';
            if (diagnosisSelect) {
                diagnosis = diagnosisSelect.value || '';
                if (diagnosis === 'Other' && otherDiagnosisInput && otherDiagnosisInput.value.trim()) {
                    diagnosis = `Other: ${otherDiagnosisInput.value.trim()}`;
                } else if (diagnosisSelect.options[diagnosisSelect.selectedIndex]) {
                    // Get the display label instead of value
                    diagnosis = diagnosisSelect.options[diagnosisSelect.selectedIndex].text || diagnosis;
                }
            } else {
                diagnosis = get('patient_diagnosis')?.value || '';
            }
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

        // Function to clear validation highlights
        function clearValidationHighlights() {
            modalEl.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            modalEl.querySelectorAll('.invalid-feedback').forEach(el => {
                el.style.display = 'none';
            });
        }

        nextBtn?.addEventListener('click', function() {
            // Clear previous validation highlights
            clearValidationHighlights();
            
            // Basic required validations per step
            if (current === 0) {
                const requiredFields = [
                    { sel: '[name="patient_first_name"]', label: 'First Name' },
                    { sel: '[name="patient_last_name"]', label: 'Surname' },
                    { sel: '[name="patient_age"]', label: 'Age' },
                    { sel: '[name="patient_gender"]', label: 'Gender' }
                ];
                const missingFields = [];
                
                for (const field of requiredFields) {
                    const el = modalEl.querySelector(field.sel);
                    if (el && !el.value.trim()) {
                        el.classList.add('is-invalid');
                        missingFields.push(field.label);
                    }
                }
                
                // Validate diagnosis dropdown
                const diagnosisSelect = modalEl.querySelector('#patient_diagnosis');
                if (!diagnosisSelect || !diagnosisSelect.value) {
                    diagnosisSelect.classList.add('is-invalid');
                    missingFields.push('Diagnosis');
                }
                
                // If "Other" is selected, validate the other diagnosis input
                if (diagnosisSelect && diagnosisSelect.value === 'Other') {
                    const otherInput = modalEl.querySelector('#other_diagnosis_input');
                    if (!otherInput || !otherInput.value.trim()) {
                        otherInput.classList.add('is-invalid');
                        missingFields.push('Diagnosis Details');
                    }
                }
                
                if (missingFields.length > 0) {
                        const msgModal = new bootstrap.Modal(document.getElementById('actionResultModal'));
                        const body = document.getElementById('actionResultBody');
                    if (body) { 
                        body.textContent = 'Please complete all required fields: ' + missingFields.join(', '); 
                    }
                        msgModal.show();
                    // Focus on first missing field
                    const firstMissing = modalEl.querySelector('.is-invalid');
                    if (firstMissing) firstMissing.focus();
                        return;
                }
            } else if (current === 1) {
                const requiredFields = [
                    { sel: '[name="patient_blood_type"]', label: 'Blood Type' },
                    { sel: '[name="rh_factor"]', label: 'RH Factor' },
                    { sel: '[name="units_requested"]', label: 'Number of Units' },
                    { sel: '#whenNeeded', label: 'When Needed' }
                ];
                const missingFields = [];
                
                for (const field of requiredFields) {
                    const el = modalEl.querySelector(field.sel);
                    if (el && (!el.value || !el.value.trim())) {
                        el.classList.add('is-invalid');
                        missingFields.push(field.label);
                    }
                }
                
                // Validate scheduled datetime if Scheduled is selected
                const whenNeeded = modalEl.querySelector('#whenNeeded');
                if (whenNeeded && whenNeeded.value === 'Scheduled') {
                    const scheduledInput = modalEl.querySelector('#scheduled_datetime');
                    if (scheduledInput) {
                        if (!scheduledInput.value) {
                            scheduledInput.classList.add('is-invalid');
                            missingFields.push('Scheduled Date & Time');
                        } else {
                            // Validate that the selected date/time is not in the past
                            const selectedDateTime = new Date(scheduledInput.value);
                            const now = new Date();
                            
                            if (selectedDateTime <= now) {
                                scheduledInput.classList.add('is-invalid');
                                const errorDiv = document.getElementById('datetime-error');
                                if (errorDiv) {
                                    errorDiv.style.display = 'block';
                                }
                        const msgModal = new bootstrap.Modal(document.getElementById('actionResultModal'));
                        const body = document.getElementById('actionResultBody');
                                if (body) { 
                                    body.textContent = 'The selected date and time cannot be in the past. Please select a future date and time.'; 
                                }
                        msgModal.show();
                                scheduledInput.focus();
                        return;
                    }
                        }
                    }
                }
                
                if (missingFields.length > 0) {
                    const msgModal = new bootstrap.Modal(document.getElementById('actionResultModal'));
                    const body = document.getElementById('actionResultBody');
                    if (body) { 
                        body.textContent = 'Please complete all required fields: ' + missingFields.join(', '); 
                    }
                    msgModal.show();
                    // Focus on first missing field
                    const firstMissing = modalEl.querySelector('.is-invalid');
                    if (firstMissing) firstMissing.focus();
                    return;
                }
            }
            if (current < steps.length - 1) { current++; updateUI(); }
        });
        prevBtn?.addEventListener('click', function() {
            if (current > 0) { current--; updateUI(); }
        });

        modalEl.addEventListener('shown.bs.modal', function(){ 
            current = 0; 
            updateUI(); 
            // Clear all validation highlights
            clearValidationHighlights();
            
            // Reset diagnosis handler state when modal opens
            const diagnosisSelect = modalEl.querySelector('#patient_diagnosis');
            const whenNeededSelect = modalEl.querySelector('#whenNeeded');
            const otherDiagnosisContainer = modalEl.querySelector('#other_diagnosis_container');
            const scheduleDateTimeDiv = modalEl.querySelector('#scheduleDateTime');
            if (diagnosisSelect) {
                diagnosisSelect.value = '';
                if (whenNeededSelect) {
                    whenNeededSelect.disabled = false;
                    whenNeededSelect.value = '';
                    whenNeededSelect.style.cursor = 'pointer';
                    whenNeededSelect.style.opacity = '1';
                }
                if (otherDiagnosisContainer) {
                    otherDiagnosisContainer.classList.add('d-none');
                    const otherInput = otherDiagnosisContainer.querySelector('input');
                    if (otherInput) {
                        otherInput.value = '';
                        otherInput.required = false;
                    }
                }
                if (scheduleDateTimeDiv) {
                    scheduleDateTimeDiv.classList.add('d-none');
                    scheduleDateTimeDiv.style.opacity = 0;
                    const dateInput = scheduleDateTimeDiv.querySelector('input');
                    if (dateInput) {
                        dateInput.value = '';
                        dateInput.required = false;
                        dateInput.classList.remove('is-invalid');
                        const errorDiv = document.getElementById('datetime-error');
                        if (errorDiv) {
                            errorDiv.style.display = 'none';
                        }
                    }
                }
            }
        });
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
                    formData.append('user_id', <?php echo json_encode($_SESSION['user_id'] ?? ''); ?>);
                    formData.append('status', 'Pending');
                    formData.append('physician_name', <?php echo json_encode($_SESSION['user_surname'] ?? ''); ?>);
                    formData.append('requested_on', new Date().toISOString());
                    // Compose patient_name from split fields for uniform DB storage
                    try {
                        const first = bloodRequestForm.querySelector('[name="patient_first_name"]').value?.trim() || '';
                        const mi = bloodRequestForm.querySelector('[name="patient_middle_initial"]').value?.trim() || '';
                        const last = bloodRequestForm.querySelector('[name="patient_last_name"]').value?.trim() || '';
                        const fullName = `${first}${mi ? ' ' + mi + '.' : ''} ${last}`.trim();
                        bloodRequestForm.querySelector('[name="patient_name"]').value = fullName;
                        formData.set('patient_name', fullName);
                    } catch (_) {}
                    
                    // Handle diagnosis - combine with "Other" specification if applicable
                    const diagnosisSelect = document.getElementById('patient_diagnosis');
                    const otherDiagnosisInput = document.getElementById('other_diagnosis_input');
                    if (diagnosisSelect && diagnosisSelect.value === 'Other' && otherDiagnosisInput) {
                        const otherValue = otherDiagnosisInput.value.trim();
                        if (otherValue) {
                            formData.set('patient_diagnosis', `Other: ${otherValue}`);
                        } else {
                            formData.set('patient_diagnosis', 'Other');
                        }
                    } else if (diagnosisSelect) {
                        formData.set('patient_diagnosis', diagnosisSelect.value);
                    }
                    
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
                    
                    // Send data to server via API endpoint (which will generate request_reference)
                    fetch('../api/submit-blood-request.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => {
                        return response.json().then(data => {
                        if (!response.ok) {
                                throw new Error(data.message || `Error ${response.status}: ${JSON.stringify(data)}`);
                            }
                            return data;
                        });
                    })
                    .then(result => {
                        // Check if result has success property (from our API)
                        if (!result.success) {
                            throw new Error(result.message || 'Failed to submit request');
                        }
                        
                        // Immediately highlight the pending card and update count
                        const pendingCard = document.getElementById('pendingCard');
                        if (pendingCard) {
                            const numberElement = pendingCard.querySelector('.summary-card-number');
                            if (numberElement) {
                                const currentCount = parseInt(numberElement.textContent) || 0;
                                const newCount = currentCount + 1;
                                highlightCardIncrease('pendingCard', newCount);
                                previousCounts.pending = newCount;
                            }
                        }
                        
                        // Close all open modals first (confirm modal and request modal)
                        const confirmModalInstance = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitModal'));
                        if (confirmModalInstance) {
                            confirmModalInstance.hide();
                        }
                        
                        const brm = bootstrap.Modal.getInstance(document.getElementById('bloodRequestModal'));
                        if (brm) {
                            // Wait for the modal to fully close before showing the result modal
                            const bloodRequestModalEl = document.getElementById('bloodRequestModal');
                            bloodRequestModalEl.addEventListener('hidden.bs.modal', function showResultModal() {
                                // Remove the listener to avoid multiple calls
                                bloodRequestModalEl.removeEventListener('hidden.bs.modal', showResultModal);
                                
                                // Now show the success modal
                                const msgModalEl = document.getElementById('actionResultModal');
                                if (msgModalEl) {
                                    const msgModal = new bootstrap.Modal(msgModalEl);
                                    const body = document.getElementById('actionResultBody');
                                    if (body) { 
                                        body.textContent = 'Blood request submitted successfully!'; 
                                    }
                                    msgModal.show();
                                    
                                    // Reload page after result modal is dismissed
                                    msgModalEl.addEventListener('hidden.bs.modal', () => {
                                        window.location.reload();
                                    }, { once: true });
                                } else {
                                    console.error('actionResultModal element not found!');
                                    // Fallback: reload immediately if modal doesn't exist
                                    window.location.reload();
                                }
                            }, { once: true });
                            
                            brm.hide();
                        } else {
                            // If request modal is not open, show result modal directly
                            const msgModalEl = document.getElementById('actionResultModal');
                            if (msgModalEl) {
                                const msgModal = new bootstrap.Modal(msgModalEl);
                                const body = document.getElementById('actionResultBody');
                                if (body) { 
                                    body.textContent = 'Blood request submitted successfully!'; 
                                }
                                msgModal.show();
                                
                                msgModalEl.addEventListener('hidden.bs.modal', () => {
                                    window.location.reload();
                                }, { once: true });
                            } else {
                                console.error('actionResultModal element not found!');
                                window.location.reload();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error submitting request:', error);
                        
                        // Close confirm modal if still open
                        const confirmModalInstance = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitModal'));
                        if (confirmModalInstance) {
                            confirmModalInstance.hide();
                        }
                        
                        // Close request modal and show error modal
                        const brm = bootstrap.Modal.getInstance(document.getElementById('bloodRequestModal'));
                        if (brm) {
                            const bloodRequestModalEl = document.getElementById('bloodRequestModal');
                            bloodRequestModalEl.addEventListener('hidden.bs.modal', function showErrorModal() {
                                bloodRequestModalEl.removeEventListener('hidden.bs.modal', showErrorModal);
                                
                                const msgModalEl = document.getElementById('actionResultModal');
                                if (msgModalEl) {
                                    const msgModal = new bootstrap.Modal(msgModalEl);
                                    const body = document.getElementById('actionResultBody');
                                    if (body) { 
                                        body.textContent = 'Error submitting request: ' + error.message; 
                                    }
                                    msgModal.show();
                                } else {
                                    alert('Error submitting request: ' + error.message);
                                }
                            }, { once: true });
                            
                            brm.hide();
                        } else {
                            // If request modal is not open, show error modal directly
                            const msgModalEl = document.getElementById('actionResultModal');
                            if (msgModalEl) {
                                const msgModal = new bootstrap.Modal(msgModalEl);
                                const body = document.getElementById('actionResultBody');
                                if (body) { 
                                    body.textContent = 'Error submitting request: ' + error.message; 
                                }
                                msgModal.show();
                            } else {
                                alert('Error submitting request: ' + error.message);
                            }
                        }
                    })
                    .finally(() => {
                        // Restore button state
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    });
                };
                // Wire confirm button - remove any existing listeners first
                const confirmBtn = document.getElementById('confirmSubmitBtn');
                if (confirmBtn) {
                    // Remove any existing click handlers
                    const newConfirmBtn = confirmBtn.cloneNode(true);
                    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                    
                    // Add new click handler
                    newConfirmBtn.onclick = function() {
                        const confirmModalInstance = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitModal'));
                        if (confirmModalInstance) {
                            confirmModalInstance.hide();
                        }
                        // Small delay to ensure confirm modal closes before starting submission
                        setTimeout(() => {
                            doSubmit();
                        }, 100);
                    };
                }
            });
        }
        
        // Handle when needed change
        const whenNeededSelect = document.getElementById('whenNeeded');
        const scheduleDateTimeDiv = document.getElementById('scheduleDateTime');
        
        if (whenNeededSelect && scheduleDateTimeDiv) {
            whenNeededSelect.addEventListener('change', function() {
                if (this.value === 'Scheduled' || this.value === 'ASAP') {
                    scheduleDateTimeDiv.classList.remove('d-none');
                    scheduleDateTimeDiv.style.opacity = 1;
                    const dateInput = scheduleDateTimeDiv.querySelector('input');
                    if (dateInput) {
                        dateInput.required = true;
                    }
                } else {
                    scheduleDateTimeDiv.style.opacity = 0;
                    setTimeout(() => {
                        scheduleDateTimeDiv.classList.add('d-none');
                        const dateInput = scheduleDateTimeDiv.querySelector('input');
                        if (dateInput) {
                            dateInput.required = false;
                            dateInput.value = '';
                        }
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

        // Use event delegation for dynamically loaded content
        // Attach handler to tbody so it works with filtered/search results
        const requestTable = document.getElementById('requestTable');
        const tableContainer = requestTable ? requestTable.closest('table') : null;
        
        function attachButtonHandlers() {
            // Remove old listeners by cloning (if needed) or use event delegation
            if (tableContainer) {
                // Use event delegation - attach once to table container
                tableContainer.addEventListener('click', function(e) {
                    // Skip if already handled by document handler
                    if (e._handledByDocument) {
                        return;
                    }
                    
                    // Check if clicked element is a view/print/handover button or inside one
                    let button = e.target.closest('.view-btn, .print-btn, .handover-btn');
                    
                    // If not found, check if target itself is a button
                    if (!button) {
                        if (e.target.classList.contains('view-btn') || e.target.classList.contains('print-btn') || e.target.classList.contains('handover-btn')) {
                            button = e.target;
                        }
                    }
                    
                    // Also check if clicking on icon inside button
                    if (!button && (e.target.tagName === 'I' || e.target.classList.contains('fa-eye') || e.target.classList.contains('fas'))) {
                        button = e.target.closest('button');
                    }
                    
                    if (!button) {
                        return;
                    }
                    
                    // Exclude modal buttons
                    if (button.id === 'printRequestBtn' || button.id === 'handoverRequestBtn') {
                        return;
                    }
                    
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Get all data attributes from button
                    const data = {};
                    Array.from(button.attributes).forEach(attr => {
                        if (attr.name.startsWith('data-')) {
                            const key = attr.name.replace('data-', '').replace(/-([a-z])/g, (g) => g[1].toUpperCase());
                            data[key] = attr.value;
                            data[attr.name.replace('data-', '')] = attr.value;
                        }
                    });
                    
                    // Also get from dataset for compatibility
                    Object.assign(data, button.dataset);
                    
                    const requestId = data.requestId || data['request-id'] || button.getAttribute('data-request-id');
                    const status = data.status || button.getAttribute('data-status');
                    
                    // Use the shared modal population function
                    populateAndOpenModal(data, requestId, status);
            });
            }
        }
        
        // Extract modal population logic to a reusable function
        function populateAndOpenModal(data, requestId, status) {
            try {
                currentRequestId = requestId;
                currentStatus = status;
                
                // Store request ID
                const editRequestId = document.getElementById('editRequestId');
                if (editRequestId) editRequestId.value = requestId;
                
                // Populate view modal with request data
                const modalCurrentDate = document.getElementById('modalCurrentDate');
                if (modalCurrentDate) {
                    modalCurrentDate.textContent = new Date().toLocaleDateString('en-US', { 
                        year: 'numeric', month: 'long', day: 'numeric' 
                    });
                }
                
                // Set patient information
                const modalPatientName = document.getElementById('modalPatientName');
                const modalPatientDetails = document.getElementById('modalPatientDetails');
                if (modalPatientName) modalPatientName.textContent = data.patientName || data['patient-name'] || '';
                if (modalPatientDetails) modalPatientDetails.textContent = `${data.patientAge || data['patient-age'] || ''}, ${data.patientGender || data['patient-gender'] || ''}`;
                
                // Set request details
                const modalDiagnosis = document.getElementById('modalDiagnosis');
                const modalBloodType = document.getElementById('modalBloodType');
                const modalRH = document.getElementById('modalRH');
                const modalUnits = document.getElementById('modalUnits');
                
                if (modalDiagnosis) modalDiagnosis.value = data.patientDiagnosis || data['patient-diagnosis'] || '';
                if (modalBloodType) modalBloodType.textContent = data.bloodType || data['blood-type'] || '';
                if (modalRH) modalRH.textContent = data.rhFactor || data['rh-factor'] || '';
                if (modalUnits) modalUnits.textContent = data.units || '';
                
                // Set when needed
                const whenNeededRaw = data.whenNeeded || data['when-needed'];
                const isAsapRaw = data.isAsap ?? data['is-asap'];
                const isAsapFlag = (isAsapRaw === true || isAsapRaw === 'true' || isAsapRaw === 1 || isAsapRaw === '1');
                const asapRadio = document.getElementById('modalAsap');
                const schedRadio = document.getElementById('modalScheduled');
                const schedInput = document.getElementById('modalScheduledDate');
                
                if (asapRadio) asapRadio.checked = false;
                if (schedRadio) schedRadio.checked = false;
                if (schedInput) schedInput.value = '';
                
                const toDatetimeLocal = (raw) => {
                    if (!raw) return '';
                    let d;
                    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                        d = new Date(raw + 'T00:00:00');
                    } else if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
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
                    const localVal = toDatetimeLocal(whenNeededRaw);
                    if (localVal && schedInput) schedInput.value = localVal;
                } else {
                    if (schedRadio) schedRadio.checked = true;
                    const localVal = toDatetimeLocal(whenNeededRaw);
                    if (localVal && schedInput) schedInput.value = localVal;
                }
                
                // Set hospital and physician
                const hospitalValue = <?php echo json_encode($_SESSION['user_first_name'] ?? ''); ?>;
                const modalHospital = document.getElementById('modalHospital');
                if (modalHospital) modalHospital.value = hospitalValue;
                
                const physicianValue = data.physicianName || data['physician-name'] || data['physician_name'] || <?php echo json_encode($_SESSION['user_surname'] ?? ''); ?>;
                const modalPhysician = document.getElementById('modalPhysician');
                if (modalPhysician) modalPhysician.value = physicianValue;
                
                // Set approval info
                const approvedBy = data.approvedBy || data['approved-by'] || data['approved_by'] || '';
                const approvedDateRaw = data.approvedDate || data['approved-date'] || data['approved_date'] || '';
                let approvedDateText = '';
                if (approvedDateRaw) {
                    const d = new Date(approvedDateRaw);
                    if (!isNaN(d.getTime())) {
                        approvedDateText = d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + ` at ` + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    }
                }
                const modalApprovedBy = document.getElementById('modalApprovedBy');
                if (modalApprovedBy) {
                    modalApprovedBy.value = approvedBy && approvedDateText ? `Approved by ${approvedBy} - ${approvedDateText}` : approvedBy || approvedDateText || '';
                }
                
                // Update status badge
                const statusBadge = document.getElementById('modalStatusBadge');
                if (statusBadge) {
                    statusBadge.classList.remove('bg-success', 'bg-primary', 'bg-warning', 'text-dark', 'bg-danger', 'bg-secondary');
                    let badgeText = status;
                    let badgeClass = 'bg-secondary';
                    
                    if (status === 'Pending') {
                        badgeText = 'Pending';
                        badgeClass = 'bg-warning text-dark';
                    } else if (status === 'Approved' || status === 'Accepted' || status === 'Confirmed') {
                        badgeText = 'Approved';
                        badgeClass = 'bg-primary';
                    } else if (status === 'Printed') {
                        badgeText = 'Approved';
                        badgeClass = 'bg-primary';
                    } else if (status === 'Handed_over') {
                        badgeText = 'Handed Over';
                        badgeClass = 'bg-primary';
                    } else if (status === 'Declined') {
                        badgeText = 'Declined';
                        badgeClass = 'bg-danger';
                    } else if (status === 'Completed') {
                        badgeText = 'Completed';
                        badgeClass = 'bg-success';
                    }
                    
                    statusBadge.textContent = badgeText;
                    badgeClass.split(' ').forEach(cls => statusBadge.classList.add(cls));
                }
                
                // Show/hide sections based on status
                const handoverInfo = document.getElementById('handoverInfo');
                const approvalInstructions = document.getElementById('approvalInstructions');
                const handoverInstructions = document.getElementById('handoverInstructions');
                const approvalSection = document.getElementById('approvalSection');
                const approvalSeparator = document.getElementById('approvalSeparator');
                const approvalText = approvalInstructions ? approvalInstructions.querySelector('p') : null;
                
                if (status === 'Handed_over' || status === 'Completed') {
                    if (handoverInfo) handoverInfo.style.display = 'block';
                    if (approvalInstructions) approvalInstructions.style.display = 'none';
                    if (handoverInstructions) handoverInstructions.style.display = 'block';
                    if (approvalSection) approvalSection.style.display = 'none';
                    if (approvalSeparator) approvalSeparator.style.display = 'none';
                    
                    const handedBy = data.handedOverBy || data['handed-over-by'] || data['handed_over_by'] || '';
                    const handedDateRaw = data.handedOverDate || data['handed-over-date'] || data['handed_over_date'] || '';
                    let handedDateText = '';
                    if (handedDateRaw) {
                        const hd = new Date(handedDateRaw);
                        if (!isNaN(hd.getTime())) {
                            handedDateText = hd.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                        }
                    }
                    let handedOverText = '';
                    if (handedBy || handedDateText) {
                        if (handedBy && handedDateText) {
                            handedOverText = `(${handedBy} - ${handedDateText})`;
                        } else if (handedBy) {
                            handedOverText = `(${handedBy})`;
                        } else if (handedDateText) {
                            handedOverText = `(${handedDateText})`;
                        }
                    }
                    const modalHandedOverBy = document.getElementById('modalHandedOverBy');
                    if (modalHandedOverBy) modalHandedOverBy.value = handedOverText;
                    
                    const receiver = data.physicianName || data['physician-name'] || data['physician_name'] || '';
                    let receivedByText = '';
                    if (receiver) {
                        receivedByText = handedDateText ? `(${receiver} - ${handedDateText})` : `(${receiver})`;
                    } else if (handedDateText) {
                        receivedByText = `(${handedDateText})`;
                    }
                    const modalReceivedBy = document.getElementById('modalReceivedBy');
                    if (modalReceivedBy) modalReceivedBy.value = receivedByText;
                } else if (status === 'Approved' || status === 'Accepted' || status === 'Confirmed' || status === 'Printed') {
                    if (handoverInfo) handoverInfo.style.display = 'none';
                    if (approvalInstructions) approvalInstructions.style.display = 'block';
                    if (approvalInstructions) {
                        approvalInstructions.classList.remove('alert-danger');
                        if (!approvalInstructions.classList.contains('alert-info')) {
                            approvalInstructions.classList.add('alert-info');
                        }
                    }
                    if (approvalText) {
                        if (status === 'Printed') {
                            approvalText.textContent = 'The request has been printed. Please wait while an administrator confirms the receipt and the blood is prepared for delivery to your hospital.';
                        } else {
                            approvalText.textContent = "The blood request has been approved. Please print the receipt and provide it to the patient's representative for claiming at PRC.";
                        }
                    }
                    if (handoverInstructions) handoverInstructions.style.display = 'none';
                    if (approvalSection) approvalSection.style.display = 'block';
                    if (approvalSeparator) approvalSeparator.style.display = 'block';
                    const declineSection = document.getElementById('declineSection');
                    if (declineSection) declineSection.style.display = 'none';
                } else if (status === 'Declined') {
                    if (handoverInfo) handoverInfo.style.display = 'none';
                    if (handoverInstructions) handoverInstructions.style.display = 'none';
                    if (approvalSection) approvalSection.style.display = 'none';
                    if (approvalSeparator) approvalSeparator.style.display = 'none';
                    const declineSection = document.getElementById('declineSection');
                    if (declineSection) declineSection.style.display = 'block';
                    if (approvalInstructions) {
                        approvalInstructions.classList.remove('alert-info');
                        approvalInstructions.classList.add('alert-danger');
                        if (approvalText) {
                            approvalText.textContent = "The blood request has been declined. Kindly inform the patient's representative to coordinate with PRC for assistance.";
                        }
                        approvalInstructions.style.display = 'block';
                    }
                    const trigger = document.querySelector(`button.view-btn[data-request-id="${requestId}"], button.print-btn[data-request-id="${requestId}"]`);
                    const declineReason = trigger ? (trigger.getAttribute('data-decline-reason') || '') : '';
                    const declinedBy = trigger ? (trigger.getAttribute('data-declined-by') || '') : '';
                    const declinedDateRaw = trigger ? (trigger.getAttribute('data-declined-date') || '') : '';
                    let declinedDateText = '';
                    if (declinedDateRaw) {
                        const dd = new Date(declinedDateRaw);
                        if (!isNaN(dd.getTime())) {
                            declinedDateText = dd.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + (dd.toLocaleTimeString ? ' at ' + dd.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '');
                        }
                    }
                    const declinedByField = document.getElementById('modalDeclinedBy');
                    const declineReasonField = document.getElementById('modalDeclineReason');
                    if (declinedByField) {
                        declinedByField.value = declinedBy && declinedDateText ? `Declined by ${declinedBy} - ${declinedDateText}` : (declinedBy || (declinedDateText ? `(${declinedDateText})` : ''));
                    }
                    if (declineReasonField) {
                        declineReasonField.value = declineReason;
                    }
                } else {
                    if (handoverInfo) handoverInfo.style.display = 'none';
                    if (approvalInstructions) approvalInstructions.style.display = (status === 'Pending') ? 'none' : 'block';
                    if (approvalInstructions) {
                        approvalInstructions.classList.remove('alert-danger');
                        if (!approvalInstructions.classList.contains('alert-info')) {
                            approvalInstructions.classList.add('alert-info');
                        }
                    }
                    if (approvalText && approvalInstructions && approvalInstructions.style.display === 'block') {
                        approvalText.textContent = "The blood request has been approved. Please print the receipt and provide it to the patient's representative for claiming at PRC.";
                    }
                    if (handoverInstructions) handoverInstructions.style.display = 'none';
                    if (approvalSection) approvalSection.style.display = (status === 'Pending') ? 'none' : 'block';
                    if (approvalSeparator) approvalSeparator.style.display = (status === 'Pending') ? 'none' : 'block';
                    const declineSection = document.getElementById('declineSection');
                    if (declineSection) declineSection.style.display = 'none';
                }
                
                // Show/hide buttons based on status
                const printBtn = document.getElementById('printRequestBtn');
                const handoverBtn = document.getElementById('handoverRequestBtn');
                const closeBtn = document.querySelector('#bloodReorderModal .modal-footer .btn-secondary[data-bs-dismiss="modal"]');
                
                if (printBtn && handoverBtn) {
                    printBtn.setAttribute('data-request-id', requestId);
                    handoverBtn.setAttribute('data-request-id', requestId);
                    
                    if (status === 'Approved' || status === 'Accepted' || status === 'Confirmed') {
                        printBtn.style.display = 'inline-block';
                        handoverBtn.style.display = 'none';
                        if (closeBtn) closeBtn.style.display = 'none';
                    } else if (status === 'Printed') {
                        printBtn.style.display = 'inline-block';
                        handoverBtn.style.display = 'none';
                        if (closeBtn) closeBtn.style.display = 'none';
                    } else if (status === 'Completed') {
                        printBtn.style.display = 'none';
                        handoverBtn.style.display = 'none';
                        if (closeBtn) closeBtn.style.display = 'none';
                    } else if (status === 'Handed_over') {
                        handoverBtn.style.display = 'inline-block';
                        printBtn.style.display = 'none';
                        if (closeBtn) closeBtn.style.display = 'inline-block';
                    } else {
                        printBtn.style.display = 'none';
                        handoverBtn.style.display = 'none';
                        if (closeBtn) closeBtn.style.display = 'inline-block';
                    }
                }
                
                // Open modal
                const modal = document.getElementById('bloodReorderModal');
                if (modal) {
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                    window.currentModalRequestId = requestId;
                }
            } catch (error) {
                // Error handled silently
            }
        }
        
        // Attach handlers on initial load
        attachButtonHandlers();
        
        // Use document-level event delegation (runs in capture phase to catch clicks first)
        document.addEventListener('click', function(e) {
            // Check if click is on a view button or icon inside a view button
            let button = e.target.closest('.view-btn');
            
            // If clicking on icon, find the button
            if (!button && (e.target.tagName === 'I' || e.target.classList.contains('fa-eye') || e.target.classList.contains('fas'))) {
                button = e.target.closest('button.view-btn');
            }
            
            // Only process if it's a table view button (no ID means it's a table button, not modal button)
            if (button && !button.id && button.classList.contains('view-btn')) {
                // Check if button is in the request table
                const isInTable = button.closest('#requestTable') || button.closest('table tbody');
                
                if (isInTable) {
                    // Prevent default and stop propagation to avoid double handling
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Get all data attributes from button
                    const data = {};
                    Array.from(button.attributes).forEach(attr => {
                        if (attr.name.startsWith('data-')) {
                            const key = attr.name.replace('data-', '').replace(/-([a-z])/g, (g) => g[1].toUpperCase());
                            data[key] = attr.value;
                            data[attr.name.replace('data-', '')] = attr.value;
                        }
                    });
                    
                    const requestId = data.requestId || data['request-id'] || button.getAttribute('data-request-id');
                    const status = data.status || button.getAttribute('data-status');
                    
                    // Call the modal population function
                    populateAndOpenModal(data, requestId, status);
                }
            }
        }, true); // Use capture phase to run before table handler
        
        // Re-attach handlers when table is updated (for filtered/search results)
        document.addEventListener('tableUpdated', function() {
            // Handlers are already attached via event delegation, so no need to re-attach
        });

        // Handle Print button click - use event delegation for dynamically loaded buttons
        document.addEventListener('click', function(e) {
            // Check if click is on the button or any of its children (like icons)
            // closest() will find the button even if clicking on child elements
            const printBtn = e.target.closest('#printRequestBtn');
            
            if (printBtn) {
                e.preventDefault();
                e.stopPropagation();
                
                // Get request ID from multiple sources
                let requestId = printBtn.getAttribute('data-request-id') || currentRequestId || window.currentModalRequestId;
                
                // If still no request ID, try to get it from the original button that opened the modal
                if (!requestId) {
                    const originalButton = document.querySelector('.print-btn[data-request-id]');
                    if (originalButton) {
                        requestId = originalButton.getAttribute('data-request-id');
                    }
                }
                
                if (!requestId) {
                    alert('No request selected for printing.');
                    return;
                }

                // Open print page in new tab - don't close the modal
                window.open(`../../src/views/forms/print-blood-request.php?request_id=${requestId}`, '_blank');
            }
        });

        // Handle Hand Over button click - use event delegation for dynamically loaded buttons
        document.addEventListener('click', function(e) {
            // Check if click is on the button or any of its children (like icons)
            // closest() will find the button even if clicking on child elements
            const handoverBtn = e.target.closest('#handoverRequestBtn');
            
            if (handoverBtn) {
                e.preventDefault();
                e.stopPropagation();
                
                // Get request ID from multiple sources
                let requestId = handoverBtn.getAttribute('data-request-id') || currentRequestId || window.currentModalRequestId;
                
                if (!requestId) {
                    alert('No request selected for handover.');
                    return;
                }

                // Show loading state
                const originalText = handoverBtn.innerHTML;
                handoverBtn.disabled = true;
                handoverBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';

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
                    handoverBtn.disabled = false;
                    handoverBtn.innerHTML = originalText;
                });
            }
        });

        // Listen for print completion messages from print page
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'print_completed') {
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
    
    // Pagination functionality
    (function() {
        const rowsPerPage = 15;
        let currentPage = 1;
        let totalRows = 0;
        let totalPages = 0;
        
        function initPagination() {
            const tableRows = document.querySelectorAll('#requestTable tr.table-row');
            totalRows = tableRows.length;
            
            // Don't paginate if there are no rows or only empty message
            if (totalRows === 0) {
                const paginationEl = document.getElementById('pagination');
                if (paginationEl) paginationEl.innerHTML = '';
                return;
            }
            
            totalPages = Math.ceil(totalRows / rowsPerPage);
            
            // Show first page by default
            showPage(1);
            renderPagination();
        }
        
        function showPage(page) {
            currentPage = page;
            const tableRows = document.querySelectorAll('#requestTable tr.table-row');
            const startIndex = (page - 1) * rowsPerPage;
            const endIndex = startIndex + rowsPerPage;
            
            tableRows.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update row numbers for visible rows
            let visibleRowNum = startIndex + 1;
            tableRows.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    const firstCell = row.querySelector('td:first-child');
                    if (firstCell) {
                        firstCell.textContent = visibleRowNum++;
                    }
                }
            });
            
            renderPagination();
        }
        
        function renderPagination() {
            const paginationContainer = document.getElementById('pagination');
            if (!paginationContainer) return;
            
            if (totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }
            
            let html = '';
            
            // Previous button
            if (currentPage > 1) {
                html += `<a href="#" class="pagination-btn" data-page="${currentPage - 1}">&lt;</a>`;
            } else {
                html += `<span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed;">&lt;</span>`;
            }
            
            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
            
            // Adjust start page if we're near the end
            if (endPage - startPage < maxVisiblePages - 1) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }
            
            // Show first page if not in range
            if (startPage > 1) {
                html += `<a href="#" class="pagination-btn" data-page="1">1</a>`;
                if (startPage > 2) {
                    html += `<span class="pagination-btn" style="border: none; background: transparent;">...</span>`;
                }
            }
            
            // Show page numbers
            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += `<span class="pagination-btn active">${i}</span>`;
                } else {
                    html += `<a href="#" class="pagination-btn" data-page="${i}">${i}</a>`;
                }
            }
            
            // Show last page if not in range
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="pagination-btn" style="border: none; background: transparent;">...</span>`;
                }
                html += `<a href="#" class="pagination-btn" data-page="${totalPages}">${totalPages}</a>`;
            }
            
            // Next button
            if (currentPage < totalPages) {
                html += `<a href="#" class="pagination-btn" data-page="${currentPage + 1}">&gt;</a>`;
            } else {
                html += `<span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed;">&gt;</span>`;
            }
            
            paginationContainer.innerHTML = html;
            
            // Attach click handlers
            paginationContainer.querySelectorAll('a[data-page]').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = parseInt(this.getAttribute('data-page'));
                    if (page && page !== currentPage) {
                        showPage(page);
                        // Scroll to top of table
                        const table = document.querySelector('table');
                        if (table) {
                            table.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                });
            });
        }
        
        // Initialize pagination when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPagination);
        } else {
            initPagination();
        }
        
        // Re-initialize pagination when table is updated (e.g., after search/filter)
        document.addEventListener('tableUpdated', function() {
            currentPage = 1;
            initPagination();
        });
    })();
    </script>
</body>
</html> 