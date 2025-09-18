<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Get the status parameter from URL
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$donations = [];
$error = null;
$pageTitle = "All Donors";

// Based on status, include the appropriate module
try {
    switch ($status) {
        case 'pending':
            include_once 'module/donation_pending.php';
            $donations = $pendingDonations ?? [];
            $pageTitle = "Pending Donations";
            break;
        case 'approved':
            include_once 'module/donation_approved.php';
            $donations = $approvedDonations ?? [];
            $pageTitle = "Approved Donations";
            break;
        case 'declined':
        case 'deferred':
            include_once 'module/donation_declined.php';
            $donations = $declinedDonations ?? [];
            $pageTitle = "Declined/Deferred Donations";
            break;
        case 'all':
        default:
            // Show all donors by combining all modules
            $allDonations = [];
            
            // Get pending donors
            include_once 'module/donation_pending.php';
            if (isset($pendingDonations) && is_array($pendingDonations)) {
                $allDonations = array_merge($allDonations, $pendingDonations);
            }
            
            // Get approved donors
            include_once 'module/donation_approved.php';
            if (isset($approvedDonations) && is_array($approvedDonations)) {
                $allDonations = array_merge($allDonations, $approvedDonations);
            }
            
            // Get declined/deferred donors
            include_once 'module/donation_declined.php';
            if (isset($declinedDonations) && is_array($declinedDonations)) {
                $allDonations = array_merge($allDonations, $declinedDonations);
            }
            
            // Sort all donations by date (newest first)
            usort($allDonations, function($a, $b) {
                $dateA = $a['date_submitted'] ?? $a['rejection_date'] ?? '';
                $dateB = $b['date_submitted'] ?? $b['rejection_date'] ?? '';
                
                if (empty($dateA) && empty($dateB)) return 0;
                if (empty($dateA)) return 1;
                if (empty($dateB)) return -1;
                
                return strtotime($dateB) - strtotime($dateA);
            });
            
            $donations = $allDonations;
            $pageTitle = "All Donors";
            break;
    }
} catch (Exception $e) {
    $error = "Error loading module: " . $e->getMessage();
}

// Check if there's an error in fetching data
if (!$error && isset($donations['error'])) {
    $error = $donations['error'];
}

// Ensure $donations is always an array
if (!is_array($donations)) {
    $donations = [];
    if (!$error) {
        $error = "No data returned or invalid data format";
    }
}

// Data is ordered by created_at.desc in the API query to implement First In, First Out (FIFO) order
// This ensures newest entries appear at the top of the table on the first page

// OPTIMIZATION: Add performance monitoring
$startTime = microtime(true);

// Pagination settings
$itemsPerPage = 10;
$totalItems = count($donations);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Ensure current page is valid
if ($currentPage < 1) {
    $currentPage = 1;
} elseif ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
}

// Calculate the starting index for the current page
$startIndex = ($currentPage - 1) * $itemsPerPage;

// Get the subset of donations for the current page
$currentPageDonations = array_slice($donations, $startIndex, $itemsPerPage);

// OPTIMIZATION: Performance logging
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
error_log("Dashboard page load time: {$executionTime}ms for {$totalItems} total items, showing page {$currentPage}");

// Calculate age from birthdate
function calculateAge($birthdate) {
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    $age = $birth->diff($today)->y;
    return $age;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
/* General Body Styling */
body {
    background-color: #f8f9fa;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}
/* Reduce Left Margin for Main Content */
main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
    margin-left: 280px !important; 
}
/* Header */
.dashboard-home-header {
    position: fixed;
    top: 0;
    left: 240px; /* Adjusted sidebar width */
    width: calc(100% - 240px);
    background-color: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    z-index: 1000;
    transition: left 0.3s ease, width 0.3s ease;
}

/* Sidebar Styling */
.dashboard-home-sidebar {
    height: 100vh;
    overflow-y: auto;
    position: fixed;
    width: 240px;
    background-color: #ffffff;
    border-right: 1px solid #ddd;
    padding: 15px;
    transition: width 0.3s ease;
    display: flex;
    flex-direction: column;
}

.sidebar-main-content {
    flex-grow: 1;
    padding-bottom: 80px; /* Space for logout button */
}

.logout-container {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px 15px;
    border-top: 1px solid #ddd;
    background-color: #ffffff;
}

.logout-link {
    color: #dc3545 !important;
}

.logout-link:hover {
    background-color: #dc3545 !important;
    color: white !important;
}

.dashboard-home-sidebar .nav-link {
    color: #333;
    padding: 10px 15px;
    margin: 2px 0;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
}

.dashboard-home-sidebar .nav-link i {
    margin-right: 10px;
    font-size: 0.9rem;
    width: 16px;
    text-align: center;
}

.dashboard-home-sidebar .nav-link:hover {
    background-color: #f8f9fa;
    color: #dc3545;
}

.dashboard-home-sidebar .nav-link.active {
    background-color: #dc3545;
    color: white;
}

.dashboard-home-sidebar .collapse-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.dashboard-home-sidebar .collapse-menu .nav-link {
    padding: 8px 15px 8px 40px;
    font-size: 0.85rem;
    margin: 0;
    border-radius: 4px;
}

.dashboard-home-sidebar .collapse-menu .nav-link:hover {
    background-color: #f8f9fa;
    color: #dc3545;
}

.dashboard-home-sidebar .collapse-menu .nav-link.active {
    background-color: #dc3545;
    color: white;
}

.dashboard-home-sidebar .nav-link[aria-expanded="true"] {
    background-color: transparent;
    color: #333;
}

.dashboard-home-sidebar .nav-link[aria-expanded="true"] i.fa-chevron-down {
    transform: rotate(180deg);
}

.dashboard-home-sidebar i.fa-chevron-down {
    font-size: 0.8rem;
    transition: transform 0.2s ease;
}

.dashboard-home-sidebar .form-control {
    font-size: 0.9rem;
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 10px;
}

/* Donor Management Section */
#donorManagementCollapse {
    margin-top: 2px;
    border: none;
}

#donorManagementCollapse .nav-link {
    color: #333;
    padding: 8px 15px 8px 40px;
}

#donorManagementCollapse .nav-link:hover {
    background-color: #f8f9fa;
    color: #dc3545;
}


#donorManagementCollapse .nav-link.active {
    background-color: #dc3545;
    color: white;
}

/* Hospital Requests Section */
#hospitalRequestsCollapse .nav-link {
    color: #333;
    padding: 8px 15px 8px 40px;
    font-size: 0.9rem;
}

#hospitalRequestsCollapse .nav-link:hover {
    color: #dc3545;
    font-weight: 600;
    background-color: transparent;
}

.dashboard-home-sidebar .nav-link[aria-expanded="true"] {
    background-color: #f8f9fa;
    color: #dc3545;
}

/* Main Content Styling */
.dashboard-home-main {
    margin-left: 240px; /* Matches sidebar */
    margin-top: 70px;
    min-height: 100vh;
    overflow-x: hidden;
    padding-bottom: 20px;
    padding-top: 20px;
    padding-left: 20px; /* Adjusted padding for balance */
    padding-right: 20px;
    transition: margin-left 0.3s ease;
}


/* Container Fluid Fix */
.container-fluid {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ============================== */
/* Responsive Design Adjustments  */
/* ============================== */

@media (max-width: 992px) {
    /* Adjust sidebar and header for tablets */
    .dashboard-home-sidebar {
        width: 200px;
    }

    .dashboard-home-header {
        left: 200px;
        width: calc(100% - 200px);
    }

    .dashboard-home-main {
        margin-left: 200px;
    }
}

@media (max-width: 768px) {
    /* Collapse sidebar and expand content on smaller screens */
    .dashboard-home-sidebar {
        width: 0;
        padding: 0;
        overflow: hidden;
    }

    .dashboard-home-header {
        left: 0;
        width: 100%;
    }

    .dashboard-home-main {
        margin-left: 0;
        padding: 10px;
    }


    .card {
        min-height: 100px;
        font-size: 14px;
    }

    
}



/* Medium Screens (Tablets) */
@media (max-width: 991px) {
    main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
        margin-left: 240px !important; 
    }
}

/* Small Screens (Mobile) */
@media (max-width: 768px) {
    main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
        margin-left: 0 !important; 
    }
}

.custom-margin {
    margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin-top: 100px;
}

        .donor_form_container {
            padding: 20px;
            max-width: 1400px;
            width: 100%;
            font-size: 14px;
        }

        .donor_form_label {
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }

        .donor_form_input {
            width: 100%;
            padding: 6px;
            margin-bottom: 10px;
            border: 1px solid #000;
            border-radius: 3px;
            font-size: 14px;
            box-sizing: border-box;
            color: #757272;
        }

        .donor_form_grid {
            display: grid;
            gap: 5px;
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }
        
        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid-1 {
            grid-template-columns: 1fr;
        }
        .grid-6 {
    grid-template-columns: repeat(6, 1fr); 
}




        .modal-header {
            background: #000000;;
            color: white;
        }

/* Search Bar Styling */
.search-container {
    width: 100%;
    margin: 0 auto;
}

.input-group {
    width: 100%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-radius: 6px;
    margin-bottom: 1rem;
}

.input-group-text {
    background-color: #fff;
    border: 1px solid #ced4da;
    border-right: none;
    padding: 0.5rem 1rem;
}

.category-select {
    border: 1px solid #ced4da;
    border-right: none;
    border-left: none;
    background-color: #f8f9fa;
    cursor: pointer;
    min-width: 120px;
    height: 45px;
    font-size: 0.95rem;
}

.category-select:focus {
    box-shadow: none;
    border-color: #ced4da;
}

#searchInput {
    border: 1px solid #ced4da;
    border-left: none;
    padding: 0.5rem 1rem;
    font-size: 0.95rem;
    height: 45px;
    flex: 1;
}

#searchInput::placeholder {
    color: #adb5bd;
    font-size: 0.95rem;
}

#searchInput:focus {
    box-shadow: none;
    border-color: #ced4da;
}

.input-group:focus-within {
    box-shadow: 0 0 0 0.15rem rgba(0,123,255,.25);
}

.input-group-text i {
    font-size: 1.1rem;
    color: #6c757d;
}

/* Add these modal styles in your CSS */
.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9998;
}

.modal {
    z-index: 9999;
}

.modal-dialog {
    margin: 1.75rem auto;
}

.modal-content {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

/* Uniform Button Styles */
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease-in-out;
}

.btn-info {
    background-color: #0dcaf0;
    border-color: #0dcaf0;
    color: #000;
}

.btn-info:hover {
    background-color: #31d2f2;
    border-color: #25cff2;
    color: #000;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(13, 202, 240, 0.3);
}

.btn-info:active,
.btn-info.active {
    background-color: #0aa2c0;
    border-color: #0a96b0;
    color: #000;
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(13, 202, 240, 0.4);
}

.btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #000;
}

.btn-warning:hover {
    background-color: #ffcd39;
    border-color: #ffc720;
    color: #000;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
}

.btn-warning:active,
.btn-warning.active {
    background-color: #e0a800;
    border-color: #d39e00;
    color: #000;
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(255, 193, 7, 0.4);
}

.btn-success {
    background-color: #198754;
    border-color: #198754;
    color: #fff;
}

.btn-success:hover {
    background-color: #20c997;
    border-color: #1ab394;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3);
}

.btn-success:active,
.btn-success.active {
    background-color: #146c43;
    border-color: #13653f;
    color: #fff;
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(25, 135, 84, 0.4);
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
    color: #fff;
}

.btn-danger:hover {
    background-color: #e35d6a;
    border-color: #e04653;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.btn-danger:active,
.btn-danger.active {
    background-color: #b02a37;
    border-color: #a52834;
    color: #fff;
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.4);
}

.btn-secondary {
    background-color: #6c757d;
    border-color: #6c757d;
    color: #fff;
}

.btn-secondary:hover {
    background-color: #808a93;
    border-color: #7a8288;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
}

.btn-secondary:active,
.btn-secondary.active {
    background-color: #545b62;
    border-color: #4e555b;
    color: #fff;
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(108, 117, 125, 0.4);
}

.btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
}

.btn-primary:hover {
    background-color: #3d8bfd;
    border-color: #2680fd;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
}

.btn-primary:active,
.btn-primary.active {
    background-color: #0b5ed7;
    border-color: #0a58ca;
    color: #fff;
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(13, 110, 253, 0.4);
}

/* Status Filter Button Styles */
.status-filter-buttons .btn {
    transition: all 0.2s ease-in-out;
    font-weight: 500;
}

.status-filter-buttons .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.status-filter-buttons .btn.active {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

/* Pagination Styles */
.pagination {
    margin: 20px 0;
    justify-content: center;
    gap: 4px;
}

.pagination .page-item {
    margin: 0;
}

.pagination .page-link {
    border: 1px solid #d1d5db;
    color: #374151;
    background-color: #fff;
    padding: 8px 12px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 6px;
    transition: all 0.2s ease;
    min-width: 40px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pagination .page-link:hover {
    background-color: #f3f4f6;
    border-color: #9ca3af;
    color: #111827;
    text-decoration: none;
}

.pagination .page-item.active .page-link {
    background-color: #3b82f6;
    border-color: #3b82f6;
    color: #fff;
    font-weight: 600;
}

.pagination .page-item.active .page-link:hover {
    background-color: #2563eb;
    border-color: #2563eb;
}

.pagination .page-item.disabled .page-link {
    background-color: #f9fafb;
    border-color: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
}

.pagination .page-item.disabled .page-link:hover {
    background-color: #f9fafb;
    border-color: #e5e7eb;
    color: #9ca3af;
}

.pagination .page-link i {
    font-size: 12px;
    font-weight: 600;
}

/* Entries Information */
.entries-info {
    text-align: center;
    margin-top: 15px;
    color: #6c757d;
    font-size: 14px;
}
    </style>
</head>
<body>
    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to proceed to the donor form?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="proceedToDonorForm()">Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
                <div class="modal-body text-center">
                    <div class="spinner-border text-danger" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Process Donor Confirmation Modal -->
    <div class="modal fade" id="processDonorConfirmationModal" tabindex="-1" aria-labelledby="processDonorConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Process Donor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to process this donor. This will redirect you to the screening form.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmProcessDonorBtn">Yes, Process Donor</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <button type="button" class="btn btn-danger" onclick="showConfirmationModal()">
                    <i class="fas fa-plus me-2"></i>Register Donor
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <div class="sidebar-main-content">
                    <div class="d-flex align-items-center ps-1 mb-3 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" style="width: 65px; height: 65px; object-fit: contain;">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                    <ul class="nav flex-column">
                        <a href="dashboard-Inventory-System.php" class="nav-link">
                            <span><i class="fas fa-home"></i>Home</span>
                        </a>
                        <a href="dashboard-Inventory-System-list-of-donations.php" class="nav-link active">
                            <span><i class="fas fa-users"></i>Donor Management</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                            <span><i class="fas fa-tint"></i>Blood Bank</span>
                        </a>
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#hospitalRequestsCollapse" role="button" aria-expanded="false" aria-controls="hospitalRequestsCollapse" onclick="event.preventDefault();">
                            <span><i class="fas fa-list"></i>Hospital Requests</span>
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <div class="collapse" id="hospitalRequestsCollapse">
                            <div class="collapse-menu">
                                <a href="Dashboard-Inventory-System-Hospital-Request.php?status=requests" class="nav-link">Requests</a>
                                <a href="Dashboard-Inventory-System-Handed-Over.php?status=accepted" class="nav-link">Approved</a>
                                <a href="Dashboard-Inventory-System-Handed-Over.php?status=handedover" class="nav-link">Handed Over</a>
                                <a href="Dashboard-Inventory-System-Handed-Over.php?status=declined" class="nav-link">Declined</a>
                            </div>
                        </div>
                        <a href="#" class="nav-link">
                            <span><i class="fas fa-chart-line"></i>Forecast Reports</span>
                        </a>
                        <a href="#" class="nav-link">
                            <span><i class="fas fa-user-cog"></i>Manage Users</span>
                        </a>
                    </ul>
                </div>
                
                <div class="logout-container">
                    <a href="../../assets/php_func/logout.php" class="nav-link logout-link">
                        <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                    </a>
                </div>
            </nav>
           <!-- Main Content -->
           <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="container-fluid p-4 custom-margin">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="card-title mb-1">Welcome, Admin!</h2>
                                <h4 class="card-subtitle text-muted mb-0">Donor Management</h4>
                                <?php if ($status && $status !== 'all'): ?>
                                <p class="text-muted mb-0 mt-1">
                                    <i class="fas fa-filter me-1"></i>
                                    Showing: <strong><?php echo ($status === 'deferred') ? 'Declined/Deferred' : ucfirst($status); ?> Donors</strong>
                                </p>
                                <?php else: ?>
                                <p class="text-muted mb-0 mt-1">
                                    <i class="fas fa-users me-1"></i>
                                    Showing: <strong>All Donors</strong>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('l, F j, Y'); ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Status Filter Tabs -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2 mb-3 status-filter-buttons">
                                    <a href="dashboard-Inventory-System-list-of-donations.php?status=all" 
                                       class="btn <?php echo ($status === 'all' || !$status) ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm">
                                        <i class="fas fa-list me-1"></i>All Donors
                                    </a>
                                    <a href="dashboard-Inventory-System-list-of-donations.php?status=pending" 
                                       class="btn <?php echo $status === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?> btn-sm">
                                        <i class="fas fa-clock me-1"></i>Pending
                                    </a>
                                    <a href="dashboard-Inventory-System-list-of-donations.php?status=approved" 
                                       class="btn <?php echo $status === 'approved' ? 'btn-success' : 'btn-outline-success'; ?> btn-sm">
                                        <i class="fas fa-check me-1"></i>Approved
                                    </a>
                                    <a href="dashboard-Inventory-System-list-of-donations.php?status=declined" 
                                       class="btn <?php echo ($status === 'declined' || $status === 'deferred') ? 'btn-danger' : 'btn-outline-danger'; ?> btn-sm">
                                        <i class="fas fa-times me-1"></i>Declined/Deferred
                                    </a>
                                </div>
                                
                                <div class="search-container">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <select class="form-select category-select" id="searchCategory" style="max-width: 150px;">
                                            <option value="all">All Fields</option>
                                            <option value="donor">Donor Name</option>
                                            <option value="donor_number">Donor Number</option>
                                            <option value="donor_type">Donor Type</option>
                                            <option value="registered_via">Registered Via</option>
                                            <option value="status">Status</option>
                                        </select>
                                        <input type="text" 
                                            class="form-control" 
                                            id="searchInput" 
                                            placeholder="Search donors...">
                                    </div>
                                    <div id="searchInfo" class="mt-2 small text-muted"></div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No pending donations found. New donor submissions will appear here.
                        </div>
                        <?php endif; ?>

                        <!-- OPTIMIZATION: Loading indicator for slow connections -->
                        <div id="loadingIndicator" class="text-center py-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading approved donations...</p>
                        </div>

                        <?php if (isset($_GET['processed']) && $_GET['processed'] === 'true'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php if ($status === 'approved'): ?>
                                Donor has been successfully processed and moved to the approved list.
                            <?php elseif ($status === 'declined'): ?>
                                Donor has been marked as declined and moved to the declined list.
                            <?php else: ?>
                                Donor has been processed successfully.
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['donor_registered']) && $_GET['donor_registered'] === 'true'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            Donor has been successfully registered and the declaration form has been completed.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Divider Line -->
                <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
                        
                        <!-- Donor Management Table -->
                        <?php if (!empty($donations)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="donationsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Donor Number</th>
                                        <th>Surname</th>
                                        <th>First Name</th>
                                        <th>Donor Type</th>
                                        <th>Registered Via</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($currentPageDonations as $donation): ?>
                                    <tr class="donor-row" data-donor-id="<?php echo htmlspecialchars($donation['donor_id'] ?? ''); ?>" data-eligibility-id="<?php echo htmlspecialchars($donation['eligibility_id'] ?? ''); ?>">
                                        <td><?php echo htmlspecialchars($donation['donor_id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($donation['surname'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($donation['first_name'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($donation['donor_type'] ?? '') === 'Returning' ? 'info' : 'primary'; ?>">
                                                <?php echo htmlspecialchars($donation['donor_type'] ?? 'New'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $regChannel = $donation['registration_source'] ?? $donation['registration_channel'] ?? 'PRC Portal';
                                            echo $regChannel === 'PRC Portal' ? 'System' : htmlspecialchars($regChannel);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $donation['status_text'] ?? 'Pending (Screening)';
                                            $statusClass = '';
                                            $displayStatus = $status;
                                            
                                            // Handle different status types
                                            switch($status) {
                                                case 'Pending (Screening)':
                                                    $statusClass = 'bg-warning';
                                                    break;
                                                case 'Pending (Examination)':
                                                case 'Pending (Physical Examination)':
                                                    $statusClass = 'bg-info';
                                                    break;
                                                case 'Pending (Collection)':
                                                    $statusClass = 'bg-primary';
                                                    break;
                                                case 'Approved':
                                                    $statusClass = 'bg-success';
                                                    break;
                                                case 'Declined':
                                                    $statusClass = 'bg-danger';
                                                    break;
                                                case 'Temporarily Deferred':
                                                    $statusClass = 'bg-warning';
                                                    break;
                                                case 'Permanently Deferred':
                                                    $statusClass = 'bg-danger';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-warning';
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($displayStatus); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $donation['status_text'] ?? 'Pending (Screening)';
                                            if (in_array($status, ['Pending (Screening)', 'Pending (Examination)', 'Pending (Physical Examination)', 'Pending (Collection)'])) {
                                                // Show edit button for pending statuses
                                                echo '<button type="button" class="btn btn-warning btn-sm edit-donor" data-donor-id="' . htmlspecialchars($donation['donor_id'] ?? '') . '" data-eligibility-id="' . htmlspecialchars($donation['eligibility_id'] ?? '') . '">';
                                                echo '<i class="fas fa-edit"></i>';
                                                echo '</button>';
                                            } else {
                                                // Show view button for completed statuses (Approved, Declined, Temporarily Deferred, Permanently Deferred)
                                                echo '<button type="button" class="btn btn-info btn-sm view-donor" data-donor-id="' . htmlspecialchars($donation['donor_id'] ?? '') . '" data-eligibility-id="' . htmlspecialchars($donation['eligibility_id'] ?? '') . '">';
                                                echo '<i class="fas fa-eye"></i>';
                                                echo '</button>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No donors found. New donor submissions will appear here.
                        </div>
                        <?php endif; ?>
                        
                        <!-- Pagination Controls -->
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-center">
                        <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <!-- Previous button -->
                                    <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?status=<?php echo $status; ?>&page=<?php echo max(1, $currentPage - 1); ?>" aria-label="Previous">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <!-- Page numbers -->
                                    <?php
                                    // Show up to 4 page numbers around current page
                                    $startPage = max(1, $currentPage - 1);
                                    $endPage = min($totalPages, $currentPage + 2);
                                    
                                    // If we're near the beginning, show more pages at the end
                                    if ($currentPage <= 2) {
                                        $endPage = min($totalPages, 4);
                                    }
                                    
                                    // If we're near the end, show more pages at the beginning
                                    if ($currentPage >= $totalPages - 1) {
                                        $startPage = max(1, $totalPages - 3);
                                    }
                                    
                                    // Show first page if not in range
                                    if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?status=<?php echo $status; ?>&page=1">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                        <a class="page-link" href="?status=<?php echo $status; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <!-- Show last page if not in range -->
                                    <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?status=<?php echo $status; ?>&page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <!-- Next button -->
                                    <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?status=<?php echo $status; ?>&page=<?php echo min($totalPages, $currentPage + 1); ?>" aria-label="Next">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                            </ul>
                        </nav>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Showing entries information -->
                        <div class="entries-info">
                            <p>
                                Showing <?php echo count($currentPageDonations); ?> of <?php echo $totalItems; ?> entries
                                <?php if ($totalPages > 1): ?>
                                (Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>)
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
            
<!-- Donor Details Modal -->
<div class="modal fade" id="donorModal" tabindex="-1" aria-labelledby="donorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header bg-danger text-white">
                <h4 class="modal-title w-100"><i class="fas fa-user me-2"></i> Donor Information</h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <div id="donorDetails">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading donor information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Donor Modal -->
<div class="modal fade" id="editDonorForm" tabindex="-1" aria-labelledby="editDonorFormLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header bg-dark text-white">
                <h4 class="modal-title w-100"><i class="fas fa-edit me-2"></i> Edit Donor Details</h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <div id="editDonorFormContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading donor information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    
</main>
            
        </div>
    </div>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Document ready event listener
        document.addEventListener('DOMContentLoaded', function() {
            // OPTIMIZATION: Show loading indicator for slow connections
            const loadingIndicator = document.getElementById('loadingIndicator');
            const tableContainer = document.querySelector('.table-responsive');
            
            // Show loading indicator if page takes more than 1 second to load
            const loadingTimeout = setTimeout(function() {
                if (loadingIndicator && tableContainer) {
                    loadingIndicator.style.display = 'block';
                    tableContainer.style.opacity = '0.5';
                }
            }, 1000);
            
            // Hide loading indicator when page is fully loaded
            window.addEventListener('load', function() {
                clearTimeout(loadingTimeout);
                if (loadingIndicator && tableContainer) {
                    loadingIndicator.style.display = 'none';
                    tableContainer.style.opacity = '1';
                }
            });
            
            // Check if we need to refresh data (e.g. after processing a donor)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('processed')) {
                // Remove the processed parameter from URL to prevent showing the message on manual refresh
                const newUrl = window.location.pathname + '?' + urlParams.toString().replace(/&?processed=true/, '');
                window.history.replaceState({}, document.title, newUrl);
                
                // If we're on a tab that should show the newly processed donor, refresh after 5 seconds
                // to make sure all database operations have completed
                setTimeout(function() {
                    window.location.reload();
                }, 5000);
            }
            
            // Clean URL if donor_registered parameter is present
            if (urlParams.has('donor_registered')) {
                // Remove the donor_registered parameter from URL to prevent showing the message on manual refresh
                const newUrl = window.location.pathname + '?' + urlParams.toString().replace(/&?donor_registered=true/, '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            // Global variables for tracking current donor
            var currentDonorId = null;
            var currentEligibilityId = null;
            
            // OPTIMIZATION: Debounced search function for better performance
            let searchTimeout;
            
            // Search function for the donations table
            function searchDonations() {
                const searchInput = document.getElementById('searchInput');
                const searchCategory = document.getElementById('searchCategory');
                const searchInfo = document.getElementById('searchInfo');
                const table = document.getElementById('donationsTable');
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr:not(.no-results)'));
                
                // Update search info based on visible rows
                function updateSearchInfo() {
                    const visibleCount = rows.filter(row => row.style.display !== 'none').length;
                    const totalCount = rows.length;
                    if (searchInfo) {
                        searchInfo.textContent = `Showing ${visibleCount} of ${totalCount} entries`;
                    }
                }
                
                // Clear search and reset display
                window.clearSearch = function() {
                    if (searchInput) searchInput.value = '';
                    if (searchCategory) searchCategory.value = 'all';
                    
                    rows.forEach(row => {
                        row.style.display = '';
                    });
                    
                    // Remove any existing "no results" message
                    const existingNoResults = tbody.querySelector('.no-results');
                    if (existingNoResults) {
                        existingNoResults.remove();
                    }
                    
                    updateSearchInfo();
                };
                
                // OPTIMIZATION: Perform search filtering with early exit for better performance
                function performSearch() {
                    const value = searchInput.value.toLowerCase().trim();
                    const category = searchCategory.value;
                    
                    // Early exit if search is empty
                    if (!value) {
                        rows.forEach(row => row.style.display = '');
                        updateSearchInfo();
                        return;
                    }
                    
                    // Remove any existing "no results" message
                    const existingNoResults = tbody.querySelector('.no-results');
                    if (existingNoResults) {
                        existingNoResults.remove();
                    }
                    
                    let visibleCount = 0;
                    
                    // OPTIMIZATION: Use more efficient filtering with early exit
                    for (let i = 0; i < rows.length; i++) {
                        const row = rows[i];
                        let found = false;
                        
                        if (category === 'all') {
                            // Search all cells with early exit
                            const cells = row.querySelectorAll('td');
                            for (let j = 0; j < cells.length; j++) {
                                if (cells[j].textContent.toLowerCase().includes(value)) {
                                    found = true;
                                    break; // Early exit once found
                                }
                            }
                        } else if (category === 'donor') {
                            // Search donor name (columns 1 and 2 for surname and first name)
                            const nameColumns = [row.cells[1], row.cells[2]];
                            
                            for (let j = 0; j < nameColumns.length; j++) {
                                if (nameColumns[j] && nameColumns[j].textContent.toLowerCase().includes(value)) {
                                    found = true;
                                    break; // Early exit once found
                                }
                            }
                        } else if (category === 'donor_number') {
                            // Search donor number (column 0)
                            if (row.cells[0] && row.cells[0].textContent.toLowerCase().includes(value)) {
                                found = true;
                            }
                        } else if (category === 'donor_type') {
                            // Search donor type (column 3)
                            if (row.cells[3] && row.cells[3].textContent.toLowerCase().includes(value)) {
                                found = true;
                            }
                        } else if (category === 'registered_via') {
                            // Search registered via (column 4)
                            if (row.cells[4] && row.cells[4].textContent.toLowerCase().includes(value)) {
                                        found = true;
                            }
                        } else if (category === 'status') {
                            // Search for status badge (column 5)
                            if (row.cells[5] && row.cells[5].textContent.toLowerCase().includes(value)) {
                                found = true;
                            }
                            // Also search for related status terms
                            if (row.cells[5] && (
                                (value.toLowerCase().includes('declined') && row.cells[5].textContent.toLowerCase().includes('declined')) ||
                                (value.toLowerCase().includes('deferred') && row.cells[5].textContent.toLowerCase().includes('deferred')) ||
                                (value.toLowerCase().includes('temporarily') && row.cells[5].textContent.toLowerCase().includes('temporarily')) ||
                                (value.toLowerCase().includes('permanently') && row.cells[5].textContent.toLowerCase().includes('permanently'))
                            )) {
                                found = true;
                            }
                        }
                        
                        // Show/hide row based on search result
                        if (found) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    }
                    
                    // Show "no results" message if needed
                    if (visibleCount === 0 && rows.length > 0) {
                        const noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results';
                        const colspan = table.querySelector('thead th:last-child') ? 
                                      table.querySelector('thead th:last-child').cellIndex + 1 : 6;
                        
                        noResultsRow.innerHTML = `
                            <td colspan="${colspan}" class="text-center">
                                <div class="alert alert-info m-2">
                                    No matching donors found
                                    <button class="btn btn-outline-primary btn-sm ms-2" onclick="clearSearch()">
                                        Clear Search
                                    </button>
                                </div>
                            </td>
                        `;
                        
                        tbody.appendChild(noResultsRow);
                    }
                    
                    updateSearchInfo();
                }
                
                // Initialize
                if (searchInput && searchCategory) {
                    // Add input event for real-time filtering
                    searchInput.addEventListener('input', performSearch);
                    searchCategory.addEventListener('change', performSearch);
                    
                    // Initial update
                    updateSearchInfo();
                }
            }
            
            // Initialize search when page loads
            console.log('DOM loaded, initializing modals and buttons...');
            
            // Initialize search
            searchDonations();
            
            // OPTIMIZATION: Debounced search for better performance
            document.getElementById('searchInput').addEventListener('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(searchDonations, 300); // Wait 300ms after user stops typing
            });
            document.getElementById('searchCategory').addEventListener('change', searchDonations);
            
            // Initialize all modals
            const donorModal = new bootstrap.Modal(document.getElementById('donorModal'));
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'), {
                backdrop: 'static',
                keyboard: false
            });
            const processDonorConfirmationModal = new bootstrap.Modal(document.getElementById('processDonorConfirmationModal'));
            
            // Function to show confirmation modal
            window.showConfirmationModal = function() {
                const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
                confirmationModal.show();
            };

            // Function to handle form submission
            window.proceedToDonorForm = function() {
                const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
                const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                
                confirmationModal.hide();
                loadingModal.show();
                
                setTimeout(() => {
                    // Pass current page as source parameter for proper redirect back
                    const currentPage = encodeURIComponent(window.location.pathname + window.location.search);
                    window.location.href = '../../src/views/forms/donor-form-modal.php?source=' + currentPage;
                }, 1500);
            };
            
            // View buttons
            const viewButtons = document.querySelectorAll('.view-donor');
            viewButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    const donorId = this.getAttribute('data-donor-id');
                    const eligibilityId = this.getAttribute('data-eligibility-id');
                    
                    if (!donorId) {
                        console.error('No donor ID found for view button');
                        return;
                    }
                    
                    console.log(`Viewing donor ID: ${donorId}, eligibility ID: ${eligibilityId}`);
                    
                    // Show loading state in modal
                    document.getElementById('donorDetails').innerHTML = `
                        <div class="text-center my-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading donor details...</p>
                        </div>
                    `;
                    
                    donorModal.show();
                    
                    // Fetch and display donor details
                    fetchDonorDetails(donorId, eligibilityId);
                });
            });
            
            // Make entire row clickable for donor details
            const donorRows = document.querySelectorAll('.donor-row');
            donorRows.forEach(function(row) {
                row.addEventListener('click', function() {
                    const donorId = this.getAttribute('data-donor-id');
                    const eligibilityId = this.getAttribute('data-eligibility-id');
                    
                    if (!donorId) {
                        console.error('No donor ID found for row');
                        return;
                    }
                    
                    console.log(`Row click - donor ID: ${donorId}, eligibility ID: ${eligibilityId}`);
                    
                    // Show loading state in modal
                    document.getElementById('donorDetails').innerHTML = `
                        <div class="text-center my-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading donor details...</p>
                        </div>
                    `;
                    
                    donorModal.show();
                    
                    // Fetch and display donor details
                    fetchDonorDetails(donorId, eligibilityId);
                });
            });
            
            // Edit buttons
            const editButtons = document.querySelectorAll('.edit-donor');
            editButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const donorId = this.getAttribute('data-donor-id');
                    const eligibilityId = this.getAttribute('data-eligibility-id');
                    if (!donorId) {
                        console.error('No donor ID found for edit button');
                        return;
                    }
                    // Show donor details modal (same as view button)
                    document.getElementById('donorDetails').innerHTML = `
                        <div class="text-center my-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading donor details...</p>
                        </div>
                    `;
                    donorModal.show();
                    fetchDonorDetails(donorId, eligibilityId);
                });
            });
            
            // Confirm process donor button click handler
            const confirmProcessDonorBtn = document.getElementById('confirmProcessDonorBtn');
            if (confirmProcessDonorBtn) {
                confirmProcessDonorBtn.addEventListener('click', function() {
                    if (!window.currentDonorId) {
                        console.error('No donor ID found for processing');
                        alert('Error: Unable to process donor. Donor ID is missing.');
                        return;
                    }
                    
                    // Close the confirmation modal first
                    const processDonorConfirmationModal = bootstrap.Modal.getInstance(document.getElementById('processDonorConfirmationModal'));
                    if (processDonorConfirmationModal) {
                        processDonorConfirmationModal.hide();
                    }
                    
                    // Then show loading modal
                    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                    if (loadingModal) {
                        loadingModal.show();
                    }

                    console.log('Processing donor ID:', window.currentDonorId);
                    
                    // Store donor ID in session
                    fetch('../../assets/php_func/set_donor_session.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            donor_id: window.currentDonorId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Session response:', data);
                        
                        // Redirect to screening form instead of medical history
                        setTimeout(() => {
                            window.location.href = `../../src/views/forms/screening-form.php?donor_id=${window.currentDonorId}`;
                        }, 1000);
                    })
                    .catch(error => {
                        console.error('Error storing donor ID in session:', error);
                        // Redirect anyway as a fallback
                        setTimeout(() => {
                            window.location.href = `../../src/views/forms/screening-form.php?donor_id=${window.currentDonorId}`;
                        }, 1000);
                    });
                });
            }
        });

        // Function to fetch donor details
        function fetchDonorDetails(donorId, eligibilityId) {
            console.log(`Fetching details for donor: ${donorId}, eligibility: ${eligibilityId}`);
            
            fetch(`../../assets/php_func/donor_details_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Track current donor/eligibility shown in the modal for refreshes
                    try {
                        window.currentDetailsDonorId = donorId;
                        window.currentDetailsEligibilityId = eligibilityId;
                    } catch (e) {}
                    // Populate modal with compact staged layout (Interviewer, Physician, Phlebotomist)
                    const donorDetailsContainer = document.getElementById('donorDetails');
                    
                    if (data.error) {
                        donorDetailsContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    const donor = data.donor || {};
                    const eligibility = data.eligibility || {};

                    const safe = (v, fb = '-') => (v === null || v === undefined || v === '' ? fb : v);
                    const badge = (text) => {
                        const t = String(text || '').toLowerCase();
                        let cls = 'bg-secondary';
                        if (t.includes('pending')) cls = 'bg-warning text-dark';
                        else if (t.includes('approved') || t.includes('eligible') || t.includes('success')) cls = 'bg-success';
                        else if (t.includes('declined') || t.includes('defer') || t.includes('fail') || t.includes('ineligible')) cls = 'bg-danger';
                        else if (t.includes('review') || t.includes('medical') || t.includes('physical')) cls = 'bg-info text-dark';
                        return `<span class="badge ${cls}">${safe(text)}</span>`;
                    };

                    const interviewerMedical = safe(eligibility.medical_history_status);
                    const interviewerScreening = safe(eligibility.screening_status);
                    const physicianMedical = safe(eligibility.review_status);
                    const physicianPhysical = safe(eligibility.physical_status);
                    const phlebStatus = safe(eligibility.collection_status);
                    const eligibilityStatus = String(safe(eligibility.status, '')).toLowerCase();
                    const isFullyApproved = eligibilityStatus === 'approved' || eligibilityStatus === 'eligible';

                    const header = `
                        <div class="donor-header-section mb-4">
                            <div class="donor-header-card">
                                <div class="donor-header-content">
                                    <div class="donor-name-section">
                                        <h3 class="donor-name">${safe(donor.surname)}, ${safe(donor.first_name)} ${safe(donor.middle_name)}</h3>
                                        <div class="donor-badges">
                                            <span class="donor-badge age-badge">${safe(donor.age)} years</span>
                                            <span class="donor-badge gender-badge">${safe(donor.sex)}</span>
                                            <span class="donor-badge blood-badge">${safe(donor.blood_type)}</span>
                                        </div>
                                    </div>
                                    <div class="donor-id-section">
                                        <div class="donor-id-label">Donor ID: ${safe(donor.donor_id)}</div>
                                    </div>
                                </div>
                            </div>
                        </div>`;

                    const section = (title, rows) => `
                        <div class="card mb-3 shadow-sm" style="border:none">
                            <div class="card-header bg-light py-2 px-3" style="border:none">
                                <h6 class="mb-0" style="font-weight:600;">${title}</h6>
                            </div>
                            <div class="card-body py-2 px-3">
                                ${rows}
                            </div>
                        </div>`;

                    const interviewerRows = (() => {
                        const baseUrl = '../../src/views/forms/';
                        const donorId = encodeURIComponent(safe(donor.donor_id, ''));
                        const medHistoryUrl = `${baseUrl}medical-history.php?donor_id=${donorId}`;
                        return `
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="bg-danger text-white">
                                    <th class="text-start">Medical History</th>
                                    <th class="text-center">Initial Screening</th>
                                    <th class="text-center" style="width:90px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center">Completed</td>
                                    <td class="text-center">Passed</td>
                                    <td class="text-center">
                                        ${isFullyApproved
                                            ? `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"View Medical History\" onclick=\"openAdminMedicalHistory({ donor_id: '${safe(donor.donor_id,'')}' })\"><i class=\"fas fa-eye\"></i></button>`
                                            : `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"Process Medical History\" onclick=\"confirmOpenAdminMedicalHistory('${safe(donor.donor_id,'')}')\"><i class=\"fas fa-pen\"></i></button>`}
                                    </td>
                                </tr>
                            </tbody>
                        </table>`;
                    })();

                    const physicianRows = (() => {
                        const baseUrl = '../../src/views/forms/';
                        const donorId = encodeURIComponent(safe(donor.donor_id, ''));
                        const medReviewUrl = `${baseUrl}medical-history.php?donor_id=${donorId}`;
                        return `
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="bg-danger text-white">
                                    <th class="text-start">Medical History</th>
                                    <th class="text-center">Physical Examination</th>
                                    <th class="text-center" style="width:90px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center">${safe(physicianMedical, '-')}</td>
                                    <td class="text-center">${safe(physicianPhysical, '-')}</td>
                                    <td class="text-center">
                                        ${isFullyApproved
                                            ? `<button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Medical Review" onclick="openPhysicianMedicalReview({ donor_id: '${donor.donor_id || ''}' })"><i class=\"fas fa-eye\"></i></button>`
                                            : `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"Process Medical Review / Physical Exam\" onclick=\"openPhysicianMedicalReview({ donor_id: '${donor.donor_id || ''}' })\"><i class=\"fas fa-pen\"></i></button>`}
                                    </td>
                                </tr>
                            </tbody>
                        </table>`;
                    })();

                    const phlebRows = (() => {
                        return `
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="bg-danger text-white">
                                    <th class="text-start">Blood Collection Status</th>
                                    <th class="text-center" style="width:90px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center">${safe(phlebStatus, '-')}</td>
                                    <td class="text-center">
                                        ${isFullyApproved
                                            ? `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"View Blood Collection\" onclick=\"openPhlebotomistCollection({ donor_id: '${donor.donor_id || ''}' })\"><i class=\"fas fa-eye\"></i></button>`
                                            : `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"Process Blood Collection\" onclick=\"openPhlebotomistCollection({ donor_id: '${donor.donor_id || ''}' })\"><i class=\"fas fa-pen\"></i></button>`}
                                    </td>
                                </tr>
                            </tbody>
                        </table>`;
                    })();

                    const cta = '';

                    const donorInfoSection = `
                        <div class="card mb-3" style="border:none">
                            <div class="card-body" style="padding: 8px 12px;">
                                <h6 class="mb-3" style="font-weight:700; color:#212529;">Donor Information</h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Birthdate</small>
                                            <input type="text" class="form-control form-control-sm" value="${safe(donor.birthdate)}" disabled>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Address</small>
                                            <input type="text" class="form-control form-control-sm" value="${safe(donor.permanent_address || donor.office_address)}" disabled>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Mobile Number</small>
                                            <input type="text" class="form-control form-control-sm" value="${safe(donor.mobile || donor.mobile_number || donor.contact_number)}" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Civil Status</small>
                                            <input type="text" class="form-control form-control-sm" value="${safe(donor.civil_status)}" disabled>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Nationality</small>
                                            <input type="text" class="form-control form-control-sm" value="${safe(donor.nationality)}" disabled>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Occupation</small>
                                            <input type="text" class="form-control form-control-sm" value="${safe(donor.occupation)}" disabled>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3" />
                    `;

                    const html = `
                        ${header}
                        ${donorInfoSection}
                        ${section('Interviewer', interviewerRows)}
                        ${section('Physician', physicianRows)}
                        ${section('Phlebotomist', phlebRows)}
                        ${cta}
                    `;
                    
                    donorDetailsContainer.innerHTML = html;
                    // Hide approve CTA in footer when fully approved (view-only state)
                    try {
                        const approveBtn = document.getElementById('Approve');
                        if (approveBtn) approveBtn.style.display = isFullyApproved ? 'none' : '';
                    } catch (_) {}
                    
                    // Wireframe-aligned styles
                    const styleEl = document.createElement('style');
                    styleEl.textContent = `
                        #donorModal .modal-dialog { max-width: 1000px; }
                        #donorModal .modal-body { padding: 20px; }
                        #donorModal .card-header { padding: 8px 12px !important; }
                        #donorModal .card-body { padding: 12px !important; }
                        #donorModal .table td { padding: 8px 12px; }
                        
                        .donor-header-section {
                            background: linear-gradient(135deg, #dc3545, #c82333);
                            border-radius: 8px;
                            padding: 20px;
                            color: white;
                            margin-bottom: 20px;
                        }
                        
                        .donor-header-card {
                            background: transparent;
                        }
                        
                        .donor-header-content {
                            display: flex;
                            justify-content: space-between;
                            align-items: flex-start;
                        }
                        
                        .donor-name-section {
                            flex: 1;
                        }
                        
                        .donor-name {
                            font-size: 1.4rem;
                            font-weight: 600;
                            margin: 0 0 8px 0;
                            color: white;
                        }
                        
                        .donor-badges {
                            display: flex;
                            gap: 6px;
                            flex-wrap: wrap;
                        }
                        
                        .donor-badge {
                            background: rgba(255, 255, 255, 0.25);
                            padding: 3px 8px;
                            border-radius: 10px;
                            font-size: 0.75rem;
                            font-weight: 500;
                            color: white;
                        }
                        
                        .donor-id-section {
                            text-align: right;
                            margin-top: 5px;
                        }
                        
                        .donor-id-label {
                            font-size: 0.9rem;
                            font-weight: 500;
                            color: white;
                        }
                        
                        .card {
                            border: 1px solid #e9ecef;
                            border-radius: 8px;
                        }
                        
                        .card-header {
                            background-color: #f8f9fa;
                            border-bottom: 1px solid #e9ecef;
                        }
                        
                        .table thead th {
                            background-color: #dc3545;
                            color: white;
                            font-weight: 600;
                            border: none;
                        }
                        
                        .btn-outline-primary {
                            border-color: #007bff;
                            color: #007bff;
                        }
                        
                        .btn-outline-primary:hover {
                            background-color: #007bff;
                            border-color: #007bff;
                            color: white;
                        }
                        
                        .circular-btn {
                            width: 32px;
                            height: 32px;
                            border-radius: 50%;
                            padding: 0;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            border: 2px solid #007bff;
                            background-color: #e3f2fd;
                        }
                        
                        .circular-btn:hover {
                            background-color: #007bff;
                            color: white;
                        }
                        
                        .circular-btn i {
                            font-size: 12px;
                        }
                    `;
                    document.head.appendChild(styleEl);

                    // Admin modal does not include a process action
                })
                .catch(error => {
                    console.error('Error fetching donor details:', error);
                    document.getElementById('donorDetails').innerHTML = '<div class="alert alert-danger">Error loading donor details. Please try again.</div>';
                });
        }

        // Function to load edit form
        function loadEditForm(donorId, eligibilityId) {
            fetch(`../../assets/php_func/donor_edit_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
                .then(response => response.json())
                .then(data => {
                    // Populate edit form with donor details
                    const editFormContainer = document.getElementById('editDonorFormContent');
                    
                    if (data.error) {
                        editFormContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    const donor = data.donor;
                    const eligibility = data.eligibility;
                    
                    // Create the edit form
                    let html = `<form id="updateEligibilityForm" method="post" action="../../assets/php_func/update_eligibility.php">
                        <input type="hidden" name="eligibility_id" value="${eligibility.eligibility_id}">
                        <input type="hidden" name="donor_id" value="${donor.donor_id}">
                        
                        <div class="donor_form_container">
                            <div class="donor_form_grid grid-3">
                                <div>
                                    <label class="donor_form_label">Surname</label>
                                    <input type="text" class="donor_form_input" name="surname" value="${donor.surname || ''}" readonly>
                                </div>
                                <div>
                                    <label class="donor_form_label">First Name</label>
                                    <input type="text" class="donor_form_input" name="first_name" value="${donor.first_name || ''}" readonly>
                                </div>
                                <div>
                                    <label class="donor_form_label">Middle Name</label>
                                    <input type="text" class="donor_form_input" name="middle_name" value="${donor.middle_name || ''}" readonly>
                                </div>
                            </div>

                            <div class="donor_form_grid grid-3">
                                <div>
                                    <label class="donor_form_label">Blood Type</label>
                                    <input type="text" class="donor_form_input" name="blood_type" value="${eligibility.blood_type || ''}">
                                </div>
                                <div>
                                    <label class="donor_form_label">Donation Type</label>
                                    <select class="donor_form_input" name="donation_type">
                                        <option value="whole_blood" ${eligibility.donation_type === 'whole_blood' ? 'selected' : ''}>Whole Blood</option>
                                        <option value="plasma" ${eligibility.donation_type === 'plasma' ? 'selected' : ''}>Plasma</option>
                                        <option value="platelets" ${eligibility.donation_type === 'platelets' ? 'selected' : ''}>Platelets</option>
                                        <option value="double_red_cells" ${eligibility.donation_type === 'double_red_cells' ? 'selected' : ''}>Double Red Cells</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="donor_form_label">Status</label>
                                    <select class="donor_form_input" name="status">
                                        <option value="eligible" ${eligibility.status === 'eligible' ? 'selected' : ''}>Eligible</option>
                                        <option value="ineligible" ${eligibility.status === 'ineligible' ? 'selected' : ''}>Ineligible</option>
                                        <option value="failed_collection" ${eligibility.status === 'failed_collection' ? 'selected' : ''}>Failed Collection</option>
                                        <option value="disapproved" ${eligibility.status === 'disapproved' ? 'selected' : ''}>Disapproved</option>
                                    </select>
                                </div>
                            </div>

                            <div class="donor_form_grid grid-2">
                                <div>
                                    <label class="donor_form_label">Blood Bag Type</label>
                                    <input type="text" class="donor_form_input" name="blood_bag_type" value="${eligibility.blood_bag_type || ''}">
                                </div>
                                <div>
                                    <label class="donor_form_label">Blood Bag Brand</label>
                                    <input type="text" class="donor_form_input" name="blood_bag_brand" value="${eligibility.blood_bag_brand || ''}">
                                </div>
                            </div>

                            <div class="donor_form_grid grid-2">
                                <div>
                                    <label class="donor_form_label">Amount Collected (mL)</label>
                                    <input type="number" class="donor_form_input" name="amount_collected" value="${eligibility.amount_collected || ''}">
                                </div>
                                <div>
                                    <label class="donor_form_label">Donor Reaction</label>
                                    <input type="text" class="donor_form_input" name="donor_reaction" value="${eligibility.donor_reaction || ''}">
                                </div>
                            </div>

                            <div class="donor_form_grid grid-1">
                                <div>
                                    <label class="donor_form_label">Management Done</label>
                                    <textarea class="donor_form_input" name="management_done" rows="3">${eligibility.management_done || ''}</textarea>
                                </div>
                            </div>

                            <div class="donor_form_grid grid-1 mt-3">
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary">Update Donor Information</button>
                                </div>
                            </div>
                        </div>
                    </form>`;
                    
                    editFormContainer.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading edit form:', error);
                    document.getElementById('editDonorFormContent').innerHTML = '<div class="alert alert-danger">Error loading donor data for editing. Please try again.</div>';
                });
        }
    </script>
    <?php
    // Include shared staff modals so admin can launch them
    // Medical History approval/decline modals
    include_once '../../src/views/modals/medical-history-approval-modals.php';
    // Screening modal (staff compact modal)
    if (file_exists('../../src/views/forms/staff_donor_initial_screening_form_modal.php')) {
        include_once '../../src/views/forms/staff_donor_initial_screening_form_modal.php';
    }
    // Defer donor modal (shared with staff)
    if (file_exists('../../src/views/modals/defer-donor-modal.php')) {
        include_once '../../src/views/modals/defer-donor-modal.php';
    }
    // Physical Examination modal
    if (file_exists('../../src/views/modals/physical-examination-modal.php')) {
        include_once '../../src/views/modals/physical-examination-modal.php';
    }
    ?>

    <!-- Staff modal styles/scripts for admin context -->
    <link rel="stylesheet" href="../../assets/css/medical-history-approval-modals.css">
    <script src="../../assets/js/medical-history-approval.js"></script>
    <script src="../../assets/js/screening_form_modal.js"></script>
    <script src="../../assets/js/initial-screening-defer-button.js"></script>
    <script src="../../assets/js/defer_donor_modal.js"></script>
    <script src="../../assets/js/physical_examination_modal.js"></script>
    <script src="../../assets/js/blood_collection_modal.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try { if (typeof initializeMedicalHistoryApproval === 'function') initializeMedicalHistoryApproval(); } catch (e) {}
        });

        window.openInterviewerScreening = function(donor) {
            if (!donor) return;
            try {
                window.openScreeningModal({ donor_id: donor.donor_id });
            } catch (e) {
                window.location.href = `../../src/views/forms/screening-form.php?donor_id=${encodeURIComponent(donor.donor_id)}`;
            }
        };

        window.openPhysicianMedicalReview = function(donor) {
            try {
                // Seed global context for approval/decline handlers
                window.currentMedicalHistoryData = {
                    donor_id: donor?.donor_id || null,
                    screening_id: null,
                    medical_history_id: null,
                    physical_exam_id: null
                };
                if (typeof showApprovalModal === 'function') {
                    showApprovalModal();
                } else {
                    const el = document.getElementById('medicalHistoryApprovalModal');
                    if (el) new bootstrap.Modal(el).show();
                }
            } catch (e) { console.warn('Medical review modal open failed', e); }
        };

        window.openPhysicianPhysicalExam = function(context) {
            try {
                if (window.physicalExaminationModal && typeof window.physicalExaminationModal.openModal === 'function') {
                    window.physicalExaminationModal.openModal({
                        screening_id: context?.screening_id || null,
                        donor_form_id: context?.donor_id || null
                    });
                } else {
                    window.location.href = `../../src/views/forms/physical-examination-form.php?donor_id=${encodeURIComponent(context?.donor_id || '')}`;
                }
            } catch (e) {
                window.location.href = `../../src/views/forms/physical-examination-form.php?donor_id=${encodeURIComponent(context?.donor_id || '')}`;
            }
        };

        window.openPhlebotomistCollection = function(context) {
            try {
                if (window.bloodCollectionModal && typeof window.bloodCollectionModal.openModal === 'function') {
                    window.bloodCollectionModal.openModal({
                        donor_id: context?.donor_id || '',
                        physical_exam_id: context?.physical_exam_id || ''
                    });
                } else {
                    window.location.href = `dashboard-staff-blood-collection-submission.php?donor_id=${encodeURIComponent(context?.donor_id || '')}`;
                }
            } catch (e) {
                window.location.href = `dashboard-staff-blood-collection-submission.php?donor_id=${encodeURIComponent(context?.donor_id || '')}`;
            }
        };

        // Ensure admin context can open the step-based screening modal reused from staff
        window.openScreeningModal = function(context) {
            try {
                const donorId = context?.donor_id ? String(context.donor_id) : '';
                const modalEl = document.getElementById('screeningFormModal');
                if (!modalEl) return;
                const donorHidden = modalEl.querySelector('input[name="donor_id"]');
                if (donorHidden) donorHidden.value = donorId;
                const bsModal = new bootstrap.Modal(modalEl);
                bsModal.show();
            } catch (_) {}
        };

        // Admin Medical History step-based modal opener (loads staff modal content)
        window.openAdminMedicalHistory = function(context) {
            const donorId = context?.donor_id ? String(context.donor_id) : '';
            const modalEl = document.getElementById('medicalHistoryModalAdmin');
            const contentEl = document.getElementById('medicalHistoryModalAdminContent');
            if (!modalEl || !contentEl) return;
            contentEl.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
            fetch(`../../src/views/forms/medical-history-modal-content.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(r => r.text())
                .then(html => { contentEl.innerHTML = html; bindAdminMedicalHistoryRefresh(); })
                .catch(() => { contentEl.innerHTML = '<div class="alert alert-danger">Failed to load Medical History form.</div>'; });
        };

        // Confirmation before processing Medical History (admin)
        window.confirmOpenAdminMedicalHistory = function(donorId) {
            const existing = document.getElementById('processMedicalHistoryConfirm');
            if (existing) existing.remove();
            const div = document.createElement('div');
            div.id = 'processMedicalHistoryConfirm';
            div.innerHTML = `
                <div class="modal fade" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header" style="background:#8b0000;color:#fff;">
                                <h5 class="modal-title">Process Medical History</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Proceed to process this donor's Medical History? You can review and save changes in the step-based form.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" id="confirmProcessMH">Proceed</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(div);
            const modal = new bootstrap.Modal(div.querySelector('.modal'));
            modal.show();
            div.querySelector('#confirmProcessMH').addEventListener('click', function() {
                modal.hide();
                setTimeout(() => openAdminMedicalHistory({ donor_id: donorId }), 150);
            }, { once: true });
            div.querySelector('.modal').addEventListener('hidden.bs.modal', () => div.remove(), { once: true });
        };

        function bindAdminMedicalHistoryRefresh() {
            try {
                const mhModalEl = document.getElementById('medicalHistoryModalAdmin');
                if (!mhModalEl) return;
                mhModalEl.addEventListener('hidden.bs.modal', function() {
                    if (window.currentDetailsDonorId && window.currentDetailsEligibilityId) {
                        fetchDonorDetails(window.currentDetailsDonorId, window.currentDetailsEligibilityId);
                    }
                }, { once: true });
            } catch (e) {}
        }
    </script>

    <!-- Admin Medical History Modal Container (content fetched from staff modal content) -->
    <div class="modal fade" id="medicalHistoryModalAdmin" tabindex="-1" aria-labelledby="medicalHistoryModalAdminLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title" id="medicalHistoryModalAdminLabel"><i class="fas fa-clipboard-list me-2"></i>Medical History Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="medicalHistoryModalAdminContent">
                    <div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
            </div>
        </div>
    </div>
    <style>
        /* Minimal styles to ensure screening modal layout in admin */
        .screening-modal-header { background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: #fff; }
        .screening-detail-card { background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; }
        .screening-label { font-weight: 600; color: #343a40; margin-bottom: 6px; display: block; }
        .screening-input { width: 100%; border: 1px solid #dee2e6; border-radius: 6px; padding: 8px 10px; }
        .screening-input-group { position: relative; }
        .screening-input-suffix { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6c757d; }
        .screening-progress-container { padding: 10px 16px; background: #fff; border-bottom: 1px solid #eee; }
        .screening-progress-steps { display: flex; gap: 16px; align-items: center; }
        .screening-step { display: flex; flex-direction: column; align-items: center; opacity: .6; }
        .screening-step.active, .screening-step.completed { opacity: 1; }
        .screening-step-number { width: 28px; height: 28px; border-radius: 50%; background: #dc3545; color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; }
        .screening-step-label { font-size: 0.8rem; color: #b22222; margin-top: 6px; }
        .screening-progress-line { height: 4px; background: #f1f3f5; border-radius: 2px; margin-top: 6px; position: relative; }
        .screening-progress-fill { height: 4px; background: #dc3545; border-radius: 2px; width: 0; transition: width .3s ease; }
        .screening-step-title h6 { margin-bottom: 4px; font-weight: 700; color: #b22222; }
        .screening-review-card { border: 1px solid #eee; border-radius: 8px; padding: 12px; background: #fff; }
    </style>
</body>
</html>