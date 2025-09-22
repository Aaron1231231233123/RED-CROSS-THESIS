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
                                                case 'Pending (New)':
                                                    $statusClass = 'bg-info';
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
                                            } elseif ($status === 'Pending (New)') {
                                                // Show edit button that opens donor information modal instead of starting process
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

<!-- Admin Defer Donor Modal -->
<div class="modal fade" id="adminDeferDonorModal" tabindex="-1" aria-labelledby="adminDeferDonorModalLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="adminDeferDonorModalLabel">
                    <i class="fas fa-ban me-2"></i>
                    Defer Donor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <form id="adminDeferDonorForm">
                    <input type="hidden" id="admin-defer-donor-id" name="donor_id">
                    <input type="hidden" id="admin-defer-eligibility-id" name="eligibility_id">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Specify reason for deferral and duration.</label>
                    </div>

                    <!-- Deferral Type Selection -->
                    <div class="mb-4">
                        <label for="adminDeferralTypeSelect" class="form-label fw-semibold">Deferral Type *</label>
                        <select class="form-select" id="adminDeferralTypeSelect" name="deferral_type" required>
                            <option value="">Select deferral type...</option>
                            <option value="Temporary Deferral">Temporary Deferral</option>
                            <option value="Permanent Deferral">Permanent Deferral</option>
                            <option value="Refuse">Refuse for this session</option>
                        </select>
                    </div>

                    <!-- Duration Section (for Temporary Deferral) -->
                    <div id="adminDurationSection" style="display: none;">
                        <label class="form-label fw-semibold">Duration *</label>
                        <div class="duration-options-grid mb-3">
                            <div class="duration-option" data-days="1">
                                <div class="duration-number">1</div>
                                <div class="duration-unit">Day</div>
                            </div>
                            <div class="duration-option" data-days="7">
                                <div class="duration-number">1</div>
                                <div class="duration-unit">Week</div>
                            </div>
                            <div class="duration-option" data-days="30">
                                <div class="duration-number">1</div>
                                <div class="duration-unit">Month</div>
                            </div>
                            <div class="duration-option" data-days="90">
                                <div class="duration-number">3</div>
                                <div class="duration-unit">Months</div>
                            </div>
                            <div class="duration-option" data-days="180">
                                <div class="duration-number">6</div>
                                <div class="duration-unit">Months</div>
                            </div>
                            <div class="duration-option" data-days="365">
                                <div class="duration-number">1</div>
                                <div class="duration-unit">Year</div>
                            </div>
                            <div class="duration-option" data-days="custom">
                                <div class="duration-number"><i class="fas fa-edit"></i></div>
                                <div class="duration-unit">Custom</div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="adminDeferralDuration" name="deferral_duration">
                        
                        <!-- Custom Duration Section -->
                        <div id="adminCustomDurationSection" style="display: none;">
                            <label for="adminCustomDuration" class="form-label">Custom Duration (Days)</label>
                            <input type="number" class="form-control" id="adminCustomDuration" name="custom_duration" min="1" max="3650" placeholder="Enter number of days">
                        </div>
                    </div>

                    <!-- Reason for Deferral -->
                    <div class="mb-4">
                        <label for="adminDisapprovalReason" class="form-label fw-semibold">Reason for Deferral *</label>
                        <textarea class="form-control" id="adminDisapprovalReason" name="disapproval_reason" rows="4" 
                                  placeholder="Please provide a detailed reason for the deferral..." required maxlength="200"></textarea>
                        <div class="form-text">
                            <span id="adminDeferCharCount">0/200 characters</span>
                        </div>
                        <div class="invalid-feedback" id="adminDeferReasonError">Please provide at least 10 characters.</div>
                        <div class="valid-feedback" id="adminDeferReasonSuccess">Reason looks good!</div>
                    </div>

                    <!-- Summary Section -->
                    <div id="adminDurationSummary" style="display: none;" class="alert alert-info">
                        <strong>Summary:</strong> <span id="adminSummaryText"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="adminSubmitDeferral" disabled>
                    <i class="fas fa-ban me-2"></i>Submit Deferral
                </button>
            </div>
        </div>
    </div>
</div>

<!-- New Donor Processing Confirmation Modal -->
<div class="modal fade" id="newDonorProcessingModal" tabindex="-1" aria-labelledby="newDonorProcessingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="newDonorProcessingModalLabel">
                    <i class="fas fa-user-plus me-2"></i>
                    Process New Donor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-clipboard-list text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Ready to Process New Donor?</h5>
                    <p class="text-muted mb-4">
                        This will start the medical history review process for the selected donor. 
                        You will be guided through each step of the donor evaluation workflow.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Steps:</strong> Medical History Review → Initial Screening → Physician Review → Physical Examination → Blood Collection
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="proceedToMedicalHistoryBtn">
                    <i class="fas fa-arrow-right me-2"></i>Proceed to Medical History
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Initial Screening Confirmation Modal -->
<div class="modal fade" id="initialScreeningConfirmationModal" tabindex="-1" aria-labelledby="initialScreeningConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="initialScreeningConfirmationModalLabel">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Submit Medical History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Medical History Review Complete</h5>
                    <p class="text-muted mb-4">
                        Please confirm if the donor is ready for the next step based on the medical history interview, 
                        and proceed with Initial Screening.
                    </p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="proceedToInitialScreeningBtn">
                    <i class="fas fa-arrow-right me-2"></i>Initial Screening
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Donor for Donation Confirmation Modal -->
<div class="modal fade" id="approveDonorForDonationModal" tabindex="-1" aria-labelledby="approveDonorForDonationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="approveDonorForDonationModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Approve Donor for Donation?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-heart text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Confirm this donor is fit to donate blood?</h5>
                    <p class="text-muted mb-4">This will mark the donor as medically cleared for donation.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-success px-4" id="confirmApproveDonorBtn">
                    <i class="fas fa-check me-2"></i>Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Donor Approved Success Modal -->
<div class="modal fade" id="donorApprovedModal" tabindex="-1" aria-labelledby="donorApprovedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="donorApprovedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Accepted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">The donor is medically cleared for donation.</h5>
                    <p class="text-muted mb-4">The donor can now proceed to the next step in the evaluation process.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Initial Screening Completed Confirmation Modal -->
<div class="modal fade" id="initialScreeningCompletedModal" tabindex="-1" aria-labelledby="initialScreeningCompletedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="initialScreeningCompletedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Initial Screening Completed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-clipboard-check text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Initial Screening Successfully Completed</h5>
                    <p class="text-muted mb-4">
                        The donor has passed the initial screening process and is ready to proceed to the next step.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Step:</strong> The donor will now proceed to physician review for medical history and physical examination.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal" id="proceedToPhysicianReviewBtn">
                    <i class="fas fa-arrow-right me-2"></i>Proceed to Physician Review
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Physician Medical History Review Modal -->
<div class="modal fade" id="physicianMedicalHistoryModal" tabindex="-1" aria-labelledby="physicianMedicalHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="physicianMedicalHistoryModalLabel">
                    <i class="fas fa-user-md me-2"></i>
                    Physician Medical History Review
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div id="physicianMedicalHistoryContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading medical history for physician review...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-danger px-4" id="physicianDeclineMedicalBtn" style="display: none;">
                    <i class="fas fa-times me-2"></i>Decline Medical History
                </button>
                <button type="button" class="btn btn-success px-4" id="physicianApproveMedicalBtn" style="display: none;">
                    <i class="fas fa-check me-2"></i>Approve Medical History
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Physician Physical Examination Confirmation Modal -->
<div class="modal fade" id="physicianPhysicalExamModal" tabindex="-1" aria-labelledby="physicianPhysicalExamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="physicianPhysicalExamModalLabel">
                    <i class="fas fa-stethoscope me-2"></i>
                    Proceed to Physical Examination
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-heartbeat text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Medical History Approved</h5>
                    <p class="text-muted mb-4">
                        The donor's medical history has been approved. You can now proceed to conduct the physical examination.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Step:</strong> Complete the physical examination form to assess the donor's physical fitness for donation.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="proceedToPhysicalExamBtn">
                    <i class="fas fa-arrow-right me-2"></i>Proceed to Physical Examination
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Physical Examination Completed Confirmation Modal -->
<div class="modal fade" id="physicalExamCompletedModal" tabindex="-1" aria-labelledby="physicalExamCompletedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="physicalExamCompletedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Physical Examination Completed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-stethoscope text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Physical Examination Successfully Completed</h5>
                    <p class="text-muted mb-4">
                        The donor has passed the physical examination and is ready to proceed to blood collection.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Step:</strong> The donor will now proceed to the phlebotomist for blood collection.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal" id="proceedToBloodCollectionBtn">
                    <i class="fas fa-arrow-right me-2"></i>Proceed to Blood Collection
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Blood Collection Completed Confirmation Modal -->
<div class="modal fade" id="bloodCollectionCompletedModal" tabindex="-1" aria-labelledby="bloodCollectionCompletedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="bloodCollectionCompletedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Blood Collection Completed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-tint text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Blood Collection Successfully Completed</h5>
                    <p class="text-muted mb-4">
                        The donor's blood has been successfully collected and the process is now complete.
                    </p>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Process Complete:</strong> The donor has successfully completed the entire donation process from registration to blood collection.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal" id="viewDonorDetailsBtn">
                    <i class="fas fa-eye me-2"></i>View Donor Details
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include Screening Form Modal -->
<?php include '../../src/views/forms/staff_donor_initial_screening_form_modal.php'; ?>

<!-- Medical History Modal (Staff Style) -->
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

<!-- Blood Collection Modal -->
<div class="modal fade" id="bloodCollectionModal" tabindex="-1" aria-labelledby="bloodCollectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header bg-danger text-white">
                <h4 class="modal-title w-100"><i class="fas fa-tint me-2"></i> Blood Collection</h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <div id="bloodCollectionContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading blood collection form...</p>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #e0e0e0;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-success" id="completeBloodCollectionBtn">
                    <i class="fas fa-check me-2"></i>Complete Collection
                </button>
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

                    // Debug logging for Donor Information Modal
                    console.log('Donor Information Modal - Status Values:', {
                        interviewerMedical,
                        interviewerScreening,
                        physicianMedical,
                        physicianPhysical,
                        phlebStatus,
                        eligibilityStatus,
                        eligibility: eligibility
                    });

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
                        
                        // Check donor status for appropriate action buttons
                        const isPendingNew = eligibilityStatus === 'pending' && 
                                           ((interviewerMedical.toLowerCase() === 'pending' || interviewerMedical === '' || interviewerMedical === '-') &&
                                           (interviewerScreening.toLowerCase() === 'pending' || interviewerScreening === '' || interviewerScreening === '-'));
                        
                        const isPendingScreening = eligibilityStatus === 'pending' && 
                                                 (interviewerMedical.toLowerCase() !== 'pending' && interviewerMedical !== '' && interviewerMedical !== '-') &&
                                                 (interviewerScreening.toLowerCase() === 'pending' || interviewerScreening === '' || interviewerScreening === '-');
                        
                        const isCompletedScreening = (interviewerMedical.toLowerCase() !== 'pending' && interviewerMedical !== '' && interviewerMedical !== '-') &&
                                                   (interviewerScreening.toLowerCase() !== 'pending' && interviewerScreening !== '' && interviewerScreening !== '-');
                        
                        // Determine action button based on status
                        let actionButton = '';
                        if (isPendingNew) {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"Edit Medical History\" onclick=\"editMedicalHistory('${safe(donor.donor_id,'')}')\"><i class=\"fas fa-pen\"></i></button>`;
                        } else if (isPendingScreening) {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"Edit Initial Screening\" onclick=\"editInitialScreening('${safe(donor.donor_id,'')}')\"><i class=\"fas fa-pen\"></i></button>`;
                        } else if (isCompletedScreening) {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-success circular-btn\" title=\"View Interviewer Details\" onclick=\"viewInterviewerDetails('${safe(donor.donor_id,'')}')\"><i class=\"fas fa-eye\"></i></button>`;
                        } else {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"View Details\" onclick=\"viewInterviewerDetails('${safe(donor.donor_id,'')}')\"><i class=\"fas fa-eye\"></i></button>`;
                        }
                        
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
                                    <td class="text-center">${badge(interviewerMedical)}</td>
                                    <td class="text-center">${badge(interviewerScreening)}</td>
                                    <td class="text-center">
                                        ${actionButton}
                                    </td>
                                </tr>
                            </tbody>
                        </table>`;
                    })();

                    const physicianRows = (() => {
                        const baseUrl = '../../src/views/forms/';
                        const donorId = encodeURIComponent(safe(donor.donor_id, ''));
                        const medReviewUrl = `${baseUrl}medical-history.php?donor_id=${donorId}`;
                        
                        // Check if interviewer phase is completed
                        const interviewerCompleted = (interviewerMedical.toLowerCase() !== 'pending' && interviewerMedical !== '' && interviewerMedical !== '-') &&
                                                  (interviewerScreening.toLowerCase() !== 'pending' && interviewerScreening !== '' && interviewerScreening !== '-');
                        
                        // Check physician phase status - combined workflow
                        const isPendingPhysicianWork = interviewerCompleted && 
                                                      ((physicianMedical.toLowerCase() === 'pending' || physicianMedical === '' || physicianMedical === '-') ||
                                                       (physicianPhysical.toLowerCase() === 'pending' || physicianPhysical === '' || physicianPhysical === '-'));
                        
                        const isCompletedPhysicianWork = interviewerCompleted && 
                                                        (physicianMedical.toLowerCase() !== 'pending' && physicianMedical !== '' && physicianMedical !== '-') &&
                                                        (physicianPhysical.toLowerCase() !== 'pending' && physicianPhysical !== '' && physicianPhysical !== '-');
                        
                        // Determine action button based on status - single button for combined physician workflow
                        let actionButton = '';
                        if (!interviewerCompleted) {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-secondary circular-btn\" title=\"Complete Interviewer Phase First\" disabled><i class=\"fas fa-lock\"></i></button>`;
                        } else if (isPendingPhysicianWork) {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"Edit Physician Review\" onclick=\"editPhysicianWorkflow('${donor.donor_id || ''}')\"><i class=\"fas fa-pen\"></i></button>`;
                        } else if (isCompletedPhysicianWork) {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-success circular-btn\" title=\"View Physician Details\" onclick=\"viewPhysicianDetails('${donor.donor_id || ''}')\"><i class=\"fas fa-eye\"></i></button>`;
                        } else {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"View Details\" onclick=\"viewPhysicianDetails('${donor.donor_id || ''}')\"><i class=\"fas fa-eye\"></i></button>`;
                        }
                        
                        return `
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="bg-danger text-white">
                                    <th class="text-start">Medical History Approval</th>
                                    <th class="text-center">Physical Examination</th>
                                    <th class="text-center" style="width:90px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center">${badge(physicianMedical)}</td>
                                    <td class="text-center">${badge(physicianPhysical)}</td>
                                    <td class="text-center">
                                        ${actionButton}
                                    </td>
                                </tr>
                            </tbody>
                        </table>`;
                    })();

                    const phlebRows = (() => {
                        // Debug logging for status values
                        console.log('Blood Collection Status Debug:', {
                            interviewerMedical,
                            interviewerScreening,
                            physicianMedical,
                            physicianPhysical,
                            phlebStatus
                        });
                        
                        // Check if physician phase is completed
                        // Status values can be: 'Completed', 'Passed', 'Approved', 'Pending', etc.
                        const physicianCompleted = (interviewerMedical.toLowerCase() !== 'pending' && interviewerMedical !== '' && interviewerMedical !== '-') &&
                                                (interviewerScreening.toLowerCase() !== 'pending' && interviewerScreening !== '' && interviewerScreening !== '-') &&
                                                (physicianMedical.toLowerCase() !== 'pending' && physicianMedical !== '' && physicianMedical !== '-') &&
                                                (physicianPhysical.toLowerCase() !== 'pending' && physicianPhysical !== '' && physicianPhysical !== '-');
                        
                        console.log('Physician Completed:', physicianCompleted);
                        
                        // Check phlebotomist phase status
                        const isPendingBloodCollection = physicianCompleted && 
                                                       (phlebStatus.toLowerCase() === 'pending' || phlebStatus === '' || phlebStatus === '-');
                        
                        const isCompletedBloodCollection = physicianCompleted && 
                                                         phlebStatus.toLowerCase() !== 'pending' && phlebStatus !== '' && phlebStatus !== '-';
                        
                        // Determine action button based on status
                        let actionButton = '';
                        if (!physicianCompleted) {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-secondary circular-btn\" title=\"Complete Physician Phase First\" disabled><i class=\"fas fa-lock\"></i></button>`;
                        } else if (isPendingBloodCollection) {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"Edit Blood Collection\" onclick=\"editBloodCollection('${donor.donor_id || ''}')\"><i class=\"fas fa-pen\"></i></button>`;
                        } else if (isCompletedBloodCollection) {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-success circular-btn\" title=\"View Phlebotomist Details\" onclick=\"viewPhlebotomistDetails('${donor.donor_id || ''}')\"><i class=\"fas fa-eye\"></i></button>`;
                        } else {
                            actionButton = `<button type=\"button\" class=\"btn btn-sm btn-outline-primary circular-btn\" title=\"View Details\" onclick=\"viewPhlebotomistDetails('${donor.donor_id || ''}')\"><i class=\"fas fa-eye\"></i></button>`;
                        }
                        
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
                                    <td class="text-center">${badge(phlebStatus)}</td>
                                    <td class="text-center">
                                        ${actionButton}
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
                    
                    // Store current donor info for admin actions
                    window.currentDonorId = donorId;
                    window.currentEligibilityId = eligibilityId;
                    
                    
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
    include_once '../../src/views/modals/physical-examination-modal.php';
    // Screening modal (staff compact modal)
    if (file_exists('../../src/views/forms/staff_donor_initial_screening_form_modal.php')) {
        include_once '../../src/views/forms/staff_donor_initial_screening_form_modal.php';
    }
    // Defer donor modal (shared with staff)
    if (file_exists('../../src/views/modals/defer-donor-modal.php')) {
        include_once '../../src/views/modals/defer-donor-modal.php';
    }
    ?>

    <!-- Staff modal styles/scripts for admin context -->
    <link rel="stylesheet" href="../../assets/css/medical-history-approval-modals.css">
    <script src="../../assets/js/medical-history-approval.js"></script>
    <script src="../../assets/js/physical_examination_modal.js"></script>
    <script src="../../assets/js/initial-screening-defer-button.js"></script>
    <script src="../../assets/js/defer_donor_modal.js"></script>
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

        window.openPhysicianCombinedWorkflow = function(donor) {
            console.log('openPhysicianCombinedWorkflow called with:', donor);
            try {
                const donorId = donor?.donor_id || null;
                if (!donorId) {
                    console.error('No donor ID provided for physician workflow');
                    alert('Error: No donor ID provided');
                    return;
                }

                console.log('Opening physician workflow for donor ID:', donorId);

                // Seed global context for approval/decline handlers
                window.currentMedicalHistoryData = {
                    donor_id: donorId,
                    screening_id: null,
                    medical_history_id: null,
                    physical_exam_id: null
                };

                // First, open the medical history modal for review and approval
                openMedicalHistoryForApproval(donorId);
            } catch (e) { 
                console.error('Error opening physician combined workflow:', e);
                alert('Error opening physician workflow');
            }
        };

        // Function to open medical history modal for approval (similar to staff dashboard)
        function openMedicalHistoryForApproval(donorId) {
            console.log('Opening medical history for approval for donor:', donorId);
            
            // Create a modal for medical history review and approval
            const modalHtml = `
                <div class="modal fade" id="medicalHistoryApprovalWorkflowModal" tabindex="-1" aria-labelledby="medicalHistoryApprovalWorkflowModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h4 class="modal-title w-100">
                                    <i class="fas fa-user-md me-2"></i> Medical History Review & Approval
                                </h4>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="medicalHistoryApprovalContent">
                                    <div class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p>Loading medical history...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Close
                                </button>
                                <button type="button" class="btn btn-success" id="approveMedicalHistoryBtn" style="display: none;">
                                    <i class="fas fa-check me-2"></i>Approve Medical History
                                </button>
                                <button type="button" class="btn btn-danger" id="declineMedicalHistoryBtn" style="display: none;">
                                    <i class="fas fa-ban me-2"></i>Decline Medical History
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            const existingModal = document.getElementById('medicalHistoryApprovalWorkflowModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to document
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('medicalHistoryApprovalWorkflowModal'));
            modal.show();

            // Load medical history content
            loadMedicalHistoryForApproval(donorId);

            // Bind approval/decline handlers
            bindMedicalHistoryApprovalHandlers(donorId);
        }

        // Function to load medical history content for approval
        function loadMedicalHistoryForApproval(donorId) {
            const contentEl = document.getElementById('medicalHistoryApprovalContent');
            if (!contentEl) return;

            // Fetch medical history content from the standalone modal
            fetch(`../../src/views/forms/medical-history-modal.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to load medical history content');
                    }
                    return response.text();
                })
                .then(html => {
                    contentEl.innerHTML = html;
                    
                    // Show approval/decline buttons
                    const approveBtn = document.getElementById('approveMedicalHistoryBtn');
                    const declineBtn = document.getElementById('declineMedicalHistoryBtn');
                    if (approveBtn) approveBtn.style.display = 'inline-block';
                    if (declineBtn) declineBtn.style.display = 'inline-block';
                })
                .catch(error => {
                    console.error('Error loading medical history content:', error);
                    contentEl.innerHTML = `<div class="alert alert-danger">Failed to load medical history content: ${error.message}</div>`;
                });
        }

        // Function to bind medical history approval handlers
        function bindMedicalHistoryApprovalHandlers(donorId) {
            // Hide the submit button and show approve/decline buttons instead
            const submitBtn = document.getElementById('nextButton');
            if (submitBtn) {
                submitBtn.style.display = 'none';
            }
            
            // The standalone modal doesn't have approve/decline buttons in the main modal
            // We need to add them to the modal footer or show them when the form is completed
            const modalFooter = document.querySelector('#medicalHistoryApprovalWorkflowModal .modal-footer');
            if (modalFooter) {
                // Add approve/decline buttons to the modal footer
                const approveBtn = document.createElement('button');
                approveBtn.className = 'btn btn-success me-2';
                approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve Medical History';
                approveBtn.id = 'approveMedicalHistoryBtn';
                
                const declineBtn = document.createElement('button');
                declineBtn.className = 'btn btn-danger';
                declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline Medical History';
                declineBtn.id = 'declineMedicalHistoryBtn';
                
                // Insert buttons before the close button
                const closeBtn = modalFooter.querySelector('button[data-bs-dismiss="modal"]');
                if (closeBtn) {
                    modalFooter.insertBefore(approveBtn, closeBtn);
                    modalFooter.insertBefore(declineBtn, closeBtn);
                } else {
                    modalFooter.appendChild(approveBtn);
                    modalFooter.appendChild(declineBtn);
                }
            }

            // Now bind the event handlers
            const approveBtn = document.getElementById('approveMedicalHistoryBtn');
            const declineBtn = document.getElementById('declineMedicalHistoryBtn');

            if (approveBtn) {
                approveBtn.addEventListener('click', function() {
                    // Use the dashboard's approval functionality
                    handleMedicalHistoryApproval(donorId, 'approve');
                });
            }

            if (declineBtn) {
                declineBtn.addEventListener('click', function() {
                    // Use the dashboard's decline functionality
                    handleMedicalHistoryApproval(donorId, 'decline');
                });
            }
        }

        // Function to handle medical history approval/decline
        function handleMedicalHistoryApproval(donorId, action) {
            console.log(`Handling medical history ${action} for donor:`, donorId);

            if (action === 'approve') {
                // Show confirmation modal
                showMedicalHistoryApprovalConfirmation(donorId);
            } else if (action === 'decline') {
                // Show decline modal
                showMedicalHistoryDeclineModal(donorId);
            }
        }

        // Function to show medical history approval confirmation
        function showMedicalHistoryApprovalConfirmation(donorId) {
            // Use the existing medical history approval modal
            const confirmModal = document.getElementById('medicalHistoryApproveConfirmModal');
            if (confirmModal) {
                const modal = new bootstrap.Modal(confirmModal);
                modal.show();

                // Bind confirmation handler
                const confirmBtn = document.getElementById('confirmApproveMedicalHistoryBtn');
                if (confirmBtn) {
                    confirmBtn.onclick = function() {
                        processMedicalHistoryApproval(donorId);
                        modal.hide();
                    };
                }
            } else {
                // Fallback: direct approval
                if (confirm('Are you sure you want to approve this donor\'s medical history?')) {
                    processMedicalHistoryApproval(donorId);
                }
            }
        }

        // Function to show medical history decline modal
        function showMedicalHistoryDeclineModal(donorId) {
            // Use the existing medical history decline modal
            const declineModal = document.getElementById('medicalHistoryDeclineModal');
            if (declineModal) {
                const modal = new bootstrap.Modal(declineModal);
                modal.show();

                // Bind decline handler
                const submitBtn = document.getElementById('submitDeclineBtn');
                if (submitBtn) {
                    submitBtn.onclick = function() {
                        processMedicalHistoryDecline(donorId);
                        modal.hide();
                    };
                }
            } else {
                // Fallback: direct decline
                const reason = prompt('Please provide a reason for declining this donor\'s medical history:');
                if (reason && reason.trim()) {
                    processMedicalHistoryDecline(donorId, reason);
                }
            }
        }

        // Function to process medical history approval
        function processMedicalHistoryApproval(donorId) {
            console.log('Processing medical history approval for donor:', donorId);

            // Update medical history status to approved
            const updateData = {
                medical_approval: 'Approved',
                updated_at: new Date().toISOString()
            };

            fetch(`../../assets/php_func/update_medical_history.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    donor_id: donorId,
                    ...updateData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Medical history approved successfully');
                    
                    // Show success modal
                    showMedicalHistoryApprovalSuccess(donorId);
                } else {
                    console.error('Failed to approve medical history:', data.message);
                    alert('Failed to approve medical history: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error approving medical history:', error);
                alert('Error approving medical history: ' + error.message);
            });
        }

        // Function to process medical history decline
        function processMedicalHistoryDecline(donorId, reason = null) {
            console.log('Processing medical history decline for donor:', donorId);

            // Get decline reason from form if not provided
            if (!reason) {
                const reasonInput = document.getElementById('declineReason');
                reason = reasonInput ? reasonInput.value : 'No reason provided';
            }

            const updateData = {
                medical_approval: 'Declined',
                disapproval_reason: reason,
                updated_at: new Date().toISOString()
            };

            fetch(`../../assets/php_func/update_medical_history.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    donor_id: donorId,
                    ...updateData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Medical history declined successfully');
                    
                    // Show decline confirmation modal
                    showMedicalHistoryDeclineSuccess(donorId);
                } else {
                    console.error('Failed to decline medical history:', data.message);
                    alert('Failed to decline medical history: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error declining medical history:', error);
                alert('Error declining medical history: ' + error.message);
            });
        }

        // Function to show medical history approval success and proceed to physical examination
        function showMedicalHistoryApprovalSuccess(donorId) {
            // Close the approval workflow modal
            const workflowModal = document.getElementById('medicalHistoryApprovalWorkflowModal');
            if (workflowModal) {
                const modal = bootstrap.Modal.getInstance(workflowModal);
                if (modal) {
                    modal.hide();
                }
            }

            // Show approval success modal
            const successModal = document.getElementById('medicalHistoryApprovalModal');
            if (successModal) {
                const modal = new bootstrap.Modal(successModal);
                modal.show();

                // When success modal is closed, proceed to physical examination
                successModal.addEventListener('hidden.bs.modal', function() {
                    proceedToPhysicalExamination(donorId);
                }, { once: true });
            } else {
                // Fallback: proceed directly to physical examination
                proceedToPhysicalExamination(donorId);
            }
        }

        // Function to show medical history decline success
        function showMedicalHistoryDeclineSuccess(donorId) {
            // Close the approval workflow modal
            const workflowModal = document.getElementById('medicalHistoryApprovalWorkflowModal');
            if (workflowModal) {
                const modal = bootstrap.Modal.getInstance(workflowModal);
                if (modal) {
                    modal.hide();
                }
            }

            // Show decline confirmation modal
            const declineModal = document.getElementById('medicalHistoryDeclinedModal');
            if (declineModal) {
                const modal = new bootstrap.Modal(declineModal);
                modal.show();
            }
        }

        // Function to proceed to physical examination modal
        function proceedToPhysicalExamination(donorId) {
            console.log('Proceeding to physical examination for donor:', donorId);

            // Create and show physical examination modal
            const physicalExamModalHtml = `
                <div class="modal fade" id="physicalExaminationWorkflowModal" tabindex="-1" aria-labelledby="physicalExaminationWorkflowModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h4 class="modal-title w-100">
                                    <i class="fas fa-stethoscope me-2"></i> Physical Examination
                                </h4>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="physicalExaminationContent">
                                    <div class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p>Loading physical examination form...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            const existingModal = document.getElementById('physicalExaminationWorkflowModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to document
            document.body.insertAdjacentHTML('beforeend', physicalExamModalHtml);

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('physicalExaminationWorkflowModal'));
            modal.show();

            // Load physical examination content
            loadPhysicalExaminationContent(donorId);
        }

        // Function to load physical examination content
        function loadPhysicalExaminationContent(donorId) {
            const contentEl = document.getElementById('physicalExaminationContent');
            if (!contentEl) return;

            // For now, redirect to the physical examination form
            // In the future, this could load the modal content directly
            setTimeout(() => {
                window.location.href = `../../src/views/forms/physical-examination-form.php?donor_id=${encodeURIComponent(donorId)}`;
            }, 1000);
        }

        // Verify function is properly attached
        console.log('openPhysicianCombinedWorkflow function defined:', typeof window.openPhysicianCombinedWorkflow);

        // Function to open medical history modal (staff style)
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
                window.currentDonorApproved = false; // Default to false for admin
            } catch (e) { 
                window.currentDonorApproved = false; 
            }
            
            // Show the medical history modal
            const modal = document.getElementById('medicalHistoryModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.classList.add('show');
                
                // Fetch medical history content from the physical dashboard specific file
                fetch(`../../src/views/forms/medical-history-physical-modal-content.php?donor_id=${donorId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.text();
                    })
                    .then(html => {
                        // Update modal content
                        const modalContent = document.getElementById('medicalHistoryModalContent');
                        modalContent.innerHTML = html;
                        
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
                        if (typeof generateMedicalHistoryQuestions === 'function') {
                            generateMedicalHistoryQuestions();
                        }
                    })
                    .catch(error => {
                        console.error('Error loading medical history content:', error);
                        const modalContent = document.getElementById('medicalHistoryModalContent');
                        modalContent.innerHTML = '<div class="alert alert-danger">Error loading medical history. Please try again.</div>';
                    })
                    .finally(() => {
                        window.isOpeningMedicalHistory = false;
                    });
            } else {
                console.error('Medical history modal not found');
                window.isOpeningMedicalHistory = false;
            }
        }

        // Function to close medical history modal
        function closeMedicalHistoryModal() {
            const modal = document.getElementById('medicalHistoryModal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }

        // Function to show physical examination confirmation after medical history approval
        window.showPhysicianPhysicalExamConfirmation = function() {
            try {
                const modal = new bootstrap.Modal(document.getElementById('physicianPhysicalExamModal'));
                modal.show();
            } catch (e) {
                console.error('Error showing physical examination confirmation:', e);
            }
        };

        // Function to show approve donor confirmation (alternative)
        window.showApproveDonorConfirmation = function() {
            try {
                const modal = new bootstrap.Modal(document.getElementById('physicianPhysicalExamModal'));
                modal.show();
            } catch (e) {
                console.error('Error showing approve donor confirmation:', e);
            }
        };

        // Function to show physical examination completed modal
        window.showPhysicalExamCompleted = function() {
            try {
                const modal = new bootstrap.Modal(document.getElementById('physicalExamCompletedModal'));
                modal.show();
            } catch (e) {
                console.error('Error showing physical examination completed modal:', e);
            }
        };

        // Function to show blood collection completed modal
        window.showBloodCollectionCompleted = function() {
            try {
                const modal = new bootstrap.Modal(document.getElementById('bloodCollectionCompletedModal'));
                modal.show();
            } catch (e) {
                console.error('Error showing blood collection completed modal:', e);
            }
        };

        // Handle proceed to physical examination button
        document.addEventListener('DOMContentLoaded', function() {
            const proceedToPhysicalExamBtn = document.getElementById('proceedToPhysicalExamBtn');
            if (proceedToPhysicalExamBtn) {
                proceedToPhysicalExamBtn.addEventListener('click', function() {
                    // Get the current donor ID from the global context
                    const donorId = window.currentMedicalHistoryData?.donor_id;
                    if (donorId) {
                        // Close the confirmation modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('physicianPhysicalExamModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Open the physical examination modal (staff style)
                        setTimeout(() => {
                            if (window.physicalExaminationModal && typeof window.physicalExaminationModal.openModal === 'function') {
                                window.physicalExaminationModal.openModal({
                                    donor_id: donorId
                                });
                            } else {
                                // Fallback to redirect if modal not available
                                window.location.href = `../../src/views/forms/physical-examination-form.php?donor_id=${encodeURIComponent(donorId)}`;
                            }
                        }, 300);
                    } else {
                        console.error('No donor ID found for physical examination');
                        alert('Error: No donor ID found');
                    }
                });
            }

            // Handle proceed to blood collection button
            const proceedToBloodCollectionBtn = document.getElementById('proceedToBloodCollectionBtn');
            if (proceedToBloodCollectionBtn) {
                proceedToBloodCollectionBtn.addEventListener('click', function() {
                    // Get the current donor ID from the global context
                    const donorId = window.currentMedicalHistoryData?.donor_id;
                    if (donorId) {
                        // Close the confirmation modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('physicalExamCompletedModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Redirect to blood collection form
                        setTimeout(() => {
                            window.location.href = `../../src/views/forms/blood-collection-form.php?donor_id=${encodeURIComponent(donorId)}`;
                        }, 300);
                    } else {
                        console.error('No donor ID found for blood collection');
                        alert('Error: No donor ID found');
                    }
                });
            }

            // Handle view donor details button
            const viewDonorDetailsBtn = document.getElementById('viewDonorDetailsBtn');
            if (viewDonorDetailsBtn) {
                viewDonorDetailsBtn.addEventListener('click', function() {
                    // Get the current donor ID from the global context
                    const donorId = window.currentMedicalHistoryData?.donor_id;
                    if (donorId) {
                        // Close the blood collection completed modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('bloodCollectionCompletedModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Open the donor details modal
                        setTimeout(() => {
                            if (typeof window.fetchDonorDetails === 'function') {
                                window.fetchDonorDetails(donorId);
                            } else {
                                console.error('fetchDonorDetails function not found');
                            }
                        }, 300);
                    } else {
                        console.error('No donor ID found for viewing details');
                        alert('Error: No donor ID found');
                    }
                });
            }
        });

        window.openPhysicianPhysicalExam = function(context) {
            // Redirect to physical examination form
            window.location.href = `../../src/views/forms/physical-examination-form.php?donor_id=${encodeURIComponent(context?.donor_id || '')}`;
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
                const contentEl = document.getElementById('screeningFormModalContent');
                if (!modalEl || !contentEl) return;
                
                contentEl.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                const bsModal = new bootstrap.Modal(modalEl);
                bsModal.show();
                
                console.log(`🔄 Loading screening form content for donor: ${donorId}`);
                fetch(`../../src/views/forms/staff_donor_initial_screening_form_modal.php?donor_id=${encodeURIComponent(donorId)}`)
                    .then(r => {
                        console.log(`📡 Screening form response status: ${r.status}`);
                        if (!r.ok) {
                            throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                        }
                        return r.text();
                    })
                    .then(html => {
                        console.log(`✅ Screening form content loaded successfully`);
                        contentEl.innerHTML = html;
                        
                        // Bind refresh handler for when modal closes
                        bindScreeningFormRefresh();
                    })
                    .catch(err => {
                        console.error(`❌ Error loading screening form:`, err);
                        contentEl.innerHTML = `
                            <div class="alert alert-danger">
                                <h6>Error Loading Screening Form</h6>
                                <p>Failed to load the screening form content. Please try again.</p>
                                <small class="text-muted">Error: ${err.message}</small>
                            </div>
                        `;
                    });
            } catch (err) {
                console.error('Error opening screening modal:', err);
            }
        };

        // Donor Details modal opener - shows comprehensive donor information
        window.openDonorDetails = function(context) {
            const donorId = context?.donor_id ? String(context.donor_id) : '';
            const modalEl = document.getElementById('donorDetailsModal');
            const contentEl = document.getElementById('donorDetailsModalContent');
            if (!modalEl || !contentEl) return;
            
            contentEl.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
            
            // Fetch comprehensive donor details from specific tables
            console.log(`Fetching donor details for ID: ${donorId}, eligibility: ${context?.eligibility_id || ''}`);
            
            // Try comprehensive API first, fallback to original if it fails
            const apiUrl = `../../assets/php_func/comprehensive_donor_details_api.php?donor_id=${encodeURIComponent(donorId)}&eligibility_id=${encodeURIComponent(context?.eligibility_id || '')}`;
            const fallbackUrl = `../../assets/php_func/donor_details_api.php?donor_id=${encodeURIComponent(donorId)}&eligibility_id=${encodeURIComponent(context?.eligibility_id || '')}`;
            
            fetch(apiUrl)
                .then(response => {
                    console.log(`API Response status: ${response.status}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API Response data:', data);
                    if (data.error) {
                        console.error('Comprehensive API Error:', data.error);
                        console.log('Trying fallback API...');
                        // Try fallback API
                        return fetch(fallbackUrl)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`Fallback HTTP error! Status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(fallbackData => {
                                console.log('Fallback API Response:', fallbackData);
                                if (fallbackData.error) {
                                    throw new Error(fallbackData.error);
                                }
                                // Convert fallback data to comprehensive format
                                return {
                                    donor_form: fallbackData.donor || {},
                                    screening_form: {},
                                    medical_history: {},
                                    physical_examination: {},
                                    eligibility: fallbackData.eligibility || {},
                                    blood_collection: {},
                                    completion_status: {
                                        donor_form: !!(fallbackData.donor && Object.keys(fallbackData.donor).length > 0),
                                        screening_form: false,
                                        medical_history: false,
                                        physical_examination: false,
                                        eligibility: !!(fallbackData.eligibility && Object.keys(fallbackData.eligibility).length > 0),
                                        blood_collection: false
                                    }
                                };
                            });
                    }
                    return data;
                })
                .then(data => {
                    if (data.error) {
                        console.error('API Error:', data.error);
                        contentEl.innerHTML = `<div class="alert alert-danger">
                            <h6>Error Loading Donor Details</h6>
                            <p>${data.error}</p>
                            <small>Donor ID: ${donorId}</small>
                        </div>`;
                        return;
                    }
                    
                    const donorForm = data.donor_form || {};
                    const screeningForm = data.screening_form || {};
                    const medicalHistory = data.medical_history || {};
                    const physicalExamination = data.physical_examination || {};
                    const eligibility = data.eligibility || {};
                    const bloodCollection = data.blood_collection || {};
                    const completionStatus = data.completion_status || {};
                    
                    const safe = (v, fb = '-') => (v === null || v === undefined || v === '' ? fb : v);
                    
                    // Determine if donor is fully approved
                    const isFullyApproved = eligibility.status === 'approved' || eligibility.status === 'eligible';
                    
                    // Create wireframe-matching donor details HTML
                    const html = `
                        <div class="donor-details-wireframe">
                            <!-- Donor Header - matches wireframe exactly -->
                            <div class="donor-header-wireframe">
                                <div class="donor-header-left">
                                    <h3 class="donor-name-wireframe">${safe(donorForm.surname)}, ${safe(donorForm.first_name)} ${safe(donorForm.middle_name)}</h3>
                                    <div class="donor-age-gender">${safe(donorForm.age)}, ${safe(donorForm.sex)}</div>
                                            </div>
                                <div class="donor-header-right">
                                    <div class="donor-id-wireframe">Donor ID ${safe(donorForm.donor_id)}</div>
                                    <div class="donor-blood-type">${safe(screeningForm.blood_type || donorForm.blood_type)}</div>
                                </div>
                            </div>

                            <!-- Donor Information Section -->
                            <div class="section-wireframe">
                                <h6 class="section-title">Donor Information:</h6>
                                <div class="form-fields-grid">
                                    <div class="form-field">
                                        <label>Birthdate</label>
                                                <input type="text" class="form-control form-control-sm" value="${safe(donorForm.birthdate)}" disabled>
                                            </div>
                                    <div class="form-field">
                                        <label>Address</label>
                                                <input type="text" class="form-control form-control-sm" value="${safe(donorForm.permanent_address || donorForm.office_address)}" disabled>
                                            </div>
                                    <div class="form-field">
                                        <label>Mobile Number</label>
                                                <input type="text" class="form-control form-control-sm" value="${safe(donorForm.mobile || donorForm.mobile_number || donorForm.contact_number)}" disabled>
                                            </div>
                                    <div class="form-field">
                                        <label>Civil Status</label>
                                                <input type="text" class="form-control form-control-sm" value="${safe(donorForm.civil_status)}" disabled>
                                            </div>
                                    <div class="form-field">
                                        <label>Nationality</label>
                                                <input type="text" class="form-control form-control-sm" value="${safe(donorForm.nationality)}" disabled>
                                            </div>
                                    <div class="form-field">
                                        <label>Occupation</label>
                                                <input type="text" class="form-control form-control-sm" value="${safe(donorForm.occupation)}" disabled>
                                    </div>
                                </div>
                            </div>

                            <!-- Medical History Section -->
                            <div class="section-wireframe">
                                <h6 class="section-title">Medical History:</h6>
                                <table class="table-wireframe">
                                        <thead>
                                        <tr>
                                            <th>Medical History Result</th>
                                            <th>Interviewer Decision</th>
                                            <th>Physician Decision</th>
                                            <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <td>${safe(medicalHistory.status || screeningForm.medical_history_status, 'Approved')}</td>
                                            <td>-</td>
                                            <td>${safe(physicalExamination.medical_approval || medicalHistory.physician_decision, 'Approved')}</td>
                                            <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Medical History" onclick="openAdminMedicalHistory({ donor_id: '${safe(donorForm.donor_id,'')}' })">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                            </div>

                            <!-- Initial Screening Section -->
                            <div class="section-wireframe">
                                <h6 class="section-title">Initial Screening:</h6>
                                <table class="table-wireframe">
                                        <thead>
                                        <tr>
                                            <th>Body Weight</th>
                                            <th>Specific Gravity</th>
                                            <th>Blood Type</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <td>${safe(screeningForm.body_weight)}</td>
                                            <td>${safe(screeningForm.specific_gravity)}</td>
                                            <td>${safe(screeningForm.blood_type)}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                            </div>

                            <!-- Physical Examination Section -->
                            <div class="section-wireframe">
                                <h6 class="section-title">Physical Examination:</h6>
                                <table class="table-wireframe">
                                        <thead>
                                        <tr>
                                            <th>Physical Examination Result</th>
                                            <th>Physician Decision</th>
                                            <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <td>${safe(physicalExamination.physical_exam_status || physicalExamination.status, 'Approved')}</td>
                                            <td>${safe(physicalExamination.physical_approval || physicalExamination.physician_decision, 'Approved')}</td>
                                            <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Physical Examination">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                <div class="donation-type-section">
                                    <div class="form-field">
                                        <label>Type of Donation</label>
                                        <div class="field-value">${safe(eligibility.donation_type, 'Walk-In')}</div>
                                </div>
                                    <div class="eligibility-status">
                                        <label>Eligibility Status</label>
                                        <div class="field-value">${safe(eligibility.status, 'Eligible')}</div>
                            </div>
                                </div>
                            </div>

                            <!-- Blood Collection Section -->
                            <div class="section-wireframe">
                                <h6 class="section-title">Blood Collection:</h6>
                                <table class="table-wireframe">
                                        <thead>
                                        <tr>
                                            <th>Blood Collection Status</th>
                                            <th>Phlebotomist Note</th>
                                            <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <td>${safe(bloodCollection.is_successful ? 'TRUE' : 'Successful', 'Unsuccessful')}</td>
                                            <td>${safe(bloodCollection.phlebotomist_note, 'Successful')}</td>
                                            <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Blood Collection">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                            </div>
                        </div>
                    `;
                    
                    contentEl.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error fetching donor details:', error);
                    contentEl.innerHTML = `<div class="alert alert-danger">
                        <h6>Network Error</h6>
                        <p>Failed to load donor details. Please check your connection and try again.</p>
                        <small>Error: ${error.message}</small>
                        <small>Donor ID: ${donorId}</small>
                    </div>`;
                });
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
            console.log(`🔄 Loading medical history content for donor: ${donorId}`);
            
            // First, fetch donor details to check status
            fetch(`../../assets/php_func/comprehensive_donor_details_api.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(response => response.json())
                .then(donorData => {
                    if (donorData.error) {
                        throw new Error(donorData.error);
                    }
                    
                    // Check medical history status to determine which buttons to show
                    const medicalHistory = donorData.medical_history || {};
                    const eligibility = donorData.eligibility || {};
                    const screeningForm = donorData.screening_form || {};
                    
                    // Determine if medical history needs approval or is already processed
                    // Get medical approval status (this is the key field used by staff dashboards)
                    const medicalApproval = medicalHistory.medical_approval || medicalHistory.status || screeningForm.medical_history_status || 'Pending';
                    
                    // Based on staff dashboard logic: if medical_approval is not 'Approved', show approve/decline buttons
                    const needsApproval = medicalApproval.toLowerCase() !== 'approved';
                    const isAlreadyApproved = medicalApproval.toLowerCase() === 'approved';
                    
                    console.log(`📊 Medical Approval: ${medicalApproval}, Needs Approval: ${needsApproval}, Already Approved: ${isAlreadyApproved}`);
                    
                    // Load the medical history modal
                    return fetch(`../../src/views/forms/medical-history-modal.php?donor_id=${encodeURIComponent(donorId)}`)
                        .then(r => {
                            console.log(`📡 Medical history response status: ${r.status}`);
                            if (!r.ok) {
                                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                            }
                            return r.text();
                        })
                        .then(html => { 
                            console.log(`✅ Medical history content loaded, length: ${html.length}`);
                            contentEl.innerHTML = html; 
                            
                            // Execute any script tags in the loaded content (like staff modal does)
                            const scripts = contentEl.querySelectorAll('script');
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
                            
                            // Call the question generation function after content is loaded
                            setTimeout(() => {
                                console.log('🔍 Checking for generateAdminMedicalHistoryQuestions function...');
                                if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                                    console.log('✅ Function found, calling it...');
                                    window.generateAdminMedicalHistoryQuestions();
                                } else {
                                    console.error('❌ generateAdminMedicalHistoryQuestions function not found');
                                }
                            }, 100);
                            
                            // Configure buttons based on donor status
                            setTimeout(() => {
                                const nextButton = document.getElementById('nextButton');
                                const prevButton = document.getElementById('prevButton');
                                
                                if (needsApproval) {
                                    // For donors who need medical history approval - show approve/decline buttons
                                    if (nextButton) {
                                        nextButton.style.display = 'none';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'none';
                                    }
                                    
                                    // Add approve/decline buttons
                                    const modalFooter = document.querySelector('#medicalHistoryModalAdmin .modal-footer');
                                    if (modalFooter && !document.getElementById('approveMedicalHistoryBtn')) {
                                        const approveBtn = document.createElement('button');
                                        approveBtn.className = 'btn btn-success me-2';
                                        approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve Medical History';
                                        approveBtn.id = 'approveMedicalHistoryBtn';
                                        
                                        const declineBtn = document.createElement('button');
                                        declineBtn.className = 'btn btn-danger';
                                        declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline Medical History';
                                        declineBtn.id = 'declineMedicalHistoryBtn';
                                        
                                        // Insert buttons before the close button
                                        const closeBtn = modalFooter.querySelector('button[data-bs-dismiss="modal"]');
                                        if (closeBtn) {
                                            modalFooter.insertBefore(approveBtn, closeBtn);
                                            modalFooter.insertBefore(declineBtn, closeBtn);
                                        } else {
                                            modalFooter.appendChild(approveBtn);
                                            modalFooter.appendChild(declineBtn);
                                        }
                                        
                                        // Bind event handlers
                                        approveBtn.addEventListener('click', function() {
                                            handleMedicalHistoryApproval(donorId, 'approve');
                                        });
                                        
                                        declineBtn.addEventListener('click', function() {
                                            handleMedicalHistoryApproval(donorId, 'decline');
                                        });
                                    }
                                    console.log('✅ Showing approve/decline buttons for medical history approval');
                                } else if (isAlreadyApproved) {
                                    // For already approved medical history - show view-only mode
                                    if (nextButton) {
                                        nextButton.style.display = 'none';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'none';
                                    }
                                    console.log('👁️ Showing view-only mode for already approved medical history');
                                } else {
                                    // For new donors or other statuses - show submit button (normal flow)
                                    if (nextButton) {
                                        nextButton.style.display = 'inline-block';
                                        nextButton.textContent = 'Next →';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'inline-block';
                                    }
                                    console.log('📝 Showing submit button for new donor or normal flow');
                                }
                            }, 200);
                            
                            bindAdminMedicalHistoryRefresh(); 
                        });
                })
                .catch(error => { 
                    console.error('❌ Failed to load Medical History form:', error);
                    contentEl.innerHTML = `<div class="alert alert-danger">Failed to load Medical History form: ${error.message}</div>`; 
                });
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

        function bindScreeningFormRefresh() {
            try {
                const screeningModalEl = document.getElementById('screeningFormModal');
                if (!screeningModalEl) return;
                screeningModalEl.addEventListener('hidden.bs.modal', function() {
                    if (window.currentDetailsDonorId && window.currentDetailsEligibilityId) {
                        fetchDonorDetails(window.currentDetailsDonorId, window.currentDetailsEligibilityId);
                    }
                }, { once: true });
            } catch (e) {}
        }

        // Admin Defer Functionality
        function initializeAdminDeferModal() {
            const deferralTypeSelect = document.getElementById('adminDeferralTypeSelect');
            const durationSection = document.getElementById('adminDurationSection');
            const customDurationSection = document.getElementById('adminCustomDurationSection');
            const durationSelect = document.getElementById('adminDeferralDuration');
            const customDurationInput = document.getElementById('adminCustomDuration');
            const submitBtn = document.getElementById('adminSubmitDeferral');
            const durationSummary = document.getElementById('adminDurationSummary');
            const summaryText = document.getElementById('adminSummaryText');
            const durationOptions = document.querySelectorAll('#adminDeferDonorModal .duration-option');
            
            // Validation elements
            const disapprovalReasonTextarea = document.getElementById('adminDisapprovalReason');
            const deferCharCountElement = document.getElementById('adminDeferCharCount');
            const deferReasonError = document.getElementById('adminDeferReasonError');
            const deferReasonSuccess = document.getElementById('adminDeferReasonSuccess');
            
            const MIN_LENGTH = 10;
            const MAX_LENGTH = 200;

            if (!deferralTypeSelect) return; // Modal not initialized yet

            // Update disapproval reason validation
            function updateAdminDeferValidation() {
                if (!disapprovalReasonTextarea) return;
                
                const currentLength = disapprovalReasonTextarea.value.length;
                
                // Update character count
                deferCharCountElement.textContent = `${currentLength}/${MAX_LENGTH} characters`;
                
                // Update character count color
                if (currentLength < MIN_LENGTH) {
                    deferCharCountElement.className = 'text-muted';
                } else if (currentLength > MAX_LENGTH) {
                    deferCharCountElement.className = 'text-danger';
                } else {
                    deferCharCountElement.className = 'text-success';
                }
                
                // Update validation feedback
                if (currentLength === 0) {
                    deferReasonError.style.display = 'none';
                    deferReasonSuccess.style.display = 'none';
                    disapprovalReasonTextarea.classList.remove('is-valid', 'is-invalid');
                } else if (currentLength < MIN_LENGTH) {
                    deferReasonError.style.display = 'block';
                    deferReasonSuccess.style.display = 'none';
                    disapprovalReasonTextarea.classList.add('is-invalid');
                    disapprovalReasonTextarea.classList.remove('is-valid');
                } else if (currentLength > MAX_LENGTH) {
                    deferReasonError.textContent = `Please keep the reason under ${MAX_LENGTH} characters.`;
                    deferReasonError.style.display = 'block';
                    deferReasonSuccess.style.display = 'none';
                    disapprovalReasonTextarea.classList.add('is-invalid');
                    disapprovalReasonTextarea.classList.remove('is-valid');
                } else {
                    deferReasonError.style.display = 'none';
                    deferReasonSuccess.style.display = 'block';
                    disapprovalReasonTextarea.classList.add('is-valid');
                    disapprovalReasonTextarea.classList.remove('is-invalid');
                }
                
                // Update submit button state
                updateAdminDeferSubmitButtonState();
            }
            
            // Update submit button state
            function updateAdminDeferSubmitButtonState() {
                if (!disapprovalReasonTextarea) return;
                
                const reasonValid = disapprovalReasonTextarea.value.length >= MIN_LENGTH && disapprovalReasonTextarea.value.length <= MAX_LENGTH;
                const deferralTypeValid = deferralTypeSelect.value !== '';
                
                // For temporary deferral, also check duration
                let durationValid = true;
                if (deferralTypeSelect.value === 'Temporary Deferral') {
                    durationValid = durationSelect.value !== '' || customDurationInput.value !== '';
                }
                
                const allValid = reasonValid && deferralTypeValid && durationValid;
                
                submitBtn.disabled = !allValid;
                
                if (allValid) {
                    submitBtn.style.backgroundColor = '#b22222';
                    submitBtn.style.borderColor = '#b22222';
                } else {
                    submitBtn.style.backgroundColor = '#6c757d';
                    submitBtn.style.borderColor = '#6c757d';
                }
            }

            // Handle deferral type change
            deferralTypeSelect.addEventListener('change', function() {
                if (this.value === 'Temporary Deferral') {
                    durationSection.style.display = 'block';
                    setTimeout(() => {
                        durationSection.classList.add('show');
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
                updateAdminSummary();
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
                    
                    updateAdminSummary();
                });
            });

            // Handle custom duration input
            customDurationInput.addEventListener('input', function() {
                updateAdminSummary();
                
                // Update the custom option display
                const customOption = document.querySelector('#adminDeferDonorModal .duration-option[data-days="custom"]');
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

            function updateAdminSummary() {
                const selectedType = deferralTypeSelect.value;
                const durationValue = durationSelect.value;
                const customDuration = customDurationInput.value;
                
                if (!selectedType) {
                    durationSummary.style.display = 'none';
                    return;
                }

                let summaryMessage = '';
                
                if (selectedType === 'Temporary Deferral') {
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
                } else if (selectedType === 'Permanent Deferral') {
                    summaryMessage = 'Donor will be permanently deferred from future donations.';
                } else if (selectedType === 'Refuse') {
                    summaryMessage = 'Donor donation will be refused for this session.';
                }

                if (summaryMessage) {
                    summaryText.textContent = summaryMessage;
                    durationSummary.style.display = 'block';
                } else {
                    durationSummary.style.display = 'none';
                }
                
                // Update submit button state when summary changes
                updateAdminDeferSubmitButtonState();
            }
            
            // Add validation event listeners
            if (disapprovalReasonTextarea) {
                disapprovalReasonTextarea.addEventListener('input', updateAdminDeferValidation);
                disapprovalReasonTextarea.addEventListener('paste', () => {
                    setTimeout(updateAdminDeferValidation, 10);
                });
            }
            
            // Update validation when deferral type changes
            deferralTypeSelect.addEventListener('change', updateAdminDeferSubmitButtonState);
            
            // Update validation when duration changes
            if (customDurationInput) {
                customDurationInput.addEventListener('input', updateAdminDeferSubmitButtonState);
            }
            
            // Initial validation
            updateAdminDeferValidation();
        }

        // Open admin defer modal
        window.openAdminDeferModal = function(donorId, eligibilityId) {
            // Set the hidden fields
            document.getElementById('admin-defer-donor-id').value = donorId || '';
            document.getElementById('admin-defer-eligibility-id').value = eligibilityId || '';
            
            // Reset form
            document.getElementById('adminDeferDonorForm').reset();
            
            // Hide conditional sections
            const durationSection = document.getElementById('adminDurationSection');
            const customDurationSection = document.getElementById('adminCustomDurationSection');
            
            durationSection.classList.remove('show');
            customDurationSection.classList.remove('show');
            durationSection.style.display = 'none';
            customDurationSection.style.display = 'none';
            document.getElementById('adminDurationSummary').style.display = 'none';
            
            // Reset all visual elements
            document.querySelectorAll('#adminDeferDonorModal .duration-option').forEach(option => {
                option.classList.remove('active');
            });
            
            // Reset custom duration display
            const customOption = document.querySelector('#adminDeferDonorModal .duration-option[data-days="custom"]');
            if (customOption) {
                const numberDiv = customOption.querySelector('.duration-number');
                numberDiv.innerHTML = '<i class="fas fa-edit"></i>';
                const unitDiv = customOption.querySelector('.duration-unit');
                unitDiv.textContent = 'Custom';
            }
            
            // Clear any validation states
            document.querySelectorAll('#adminDeferDonorModal .form-control').forEach(control => {
                control.classList.remove('is-invalid', 'is-valid');
            });
            
            // Show the modal
            const deferModal = new bootstrap.Modal(document.getElementById('adminDeferDonorModal'));
            deferModal.show();
            
            // Re-initialize defer modal functionality when it opens
            setTimeout(() => {
                initializeAdminDeferModal();
            }, 200);
        };

                const donorModal = bootstrap.Modal.getInstance(document.getElementById('donorModal'));
                if (donorModal) {
                    donorModal.hide();
                }
                
                // Open defer modal
                setTimeout(() => {
                    openAdminDeferModal(donorId, eligibilityId);
                }, 300);
            }
        });

        // Submit admin deferral
        async function submitAdminDeferral() {
            const form = document.getElementById('adminDeferDonorForm');
            const formData = new FormData(form);
            const submitBtn = document.getElementById('adminSubmitDeferral');
            const originalText = submitBtn.innerHTML;
            
            const donorId = formData.get('donor_id');
            const eligibilityId = formData.get('eligibility_id');
            const deferralType = document.getElementById('adminDeferralTypeSelect').value;
            const disapprovalReason = formData.get('disapproval_reason');
            
            // Calculate final duration
            let finalDuration = null;
            if (deferralType === 'Temporary Deferral') {
                const durationValue = document.getElementById('adminDeferralDuration').value;
                if (durationValue === 'custom') {
                    finalDuration = document.getElementById('adminCustomDuration').value;
                } else {
                    finalDuration = durationValue;
                }
            }

            console.log('Submitting admin deferral:', {
                donor_id: donorId,
                eligibility_id: eligibilityId,
                deferral_type: deferralType,
                disapproval_reason: disapprovalReason,
                duration: finalDuration
            });

            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;

            try {
                // Prepare deferral data for create_eligibility.php
                const deferData = {
                    action: 'create_eligibility_defer',
                    donor_id: parseInt(donorId),
                    eligibility_id: eligibilityId || null,
                    deferral_type: deferralType,
                    disapproval_reason: disapprovalReason,
                    duration: finalDuration
                };
                
                console.log('Sending defer data to create_eligibility.php:', deferData);
                
                // Submit to create_eligibility.php endpoint
                const response = await fetch('../../assets/php_func/create_eligibility.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(deferData)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
                
                if (result.success) {
                    console.log('Admin deferral recorded successfully:', result);
                    
                    // Close the deferral modal
                    const deferModal = bootstrap.Modal.getInstance(document.getElementById('adminDeferDonorModal'));
                    if (deferModal) {
                        deferModal.hide();
                    }
                    
                    // Show success message
                    setTimeout(() => {
                        showAdminDeferToast('Success', 'Donor has been successfully deferred.', 'success');
                        // Reload the page to refresh the donor list
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }, 300);
                } else {
                    console.error('Failed to record admin deferral:', result.error || result.message);
                    showAdminDeferToast('Error', result.message || result.error || 'Failed to record deferral. Please try again.', 'error');
                }
                
            } catch (error) {
                console.error('Error processing admin deferral:', error);
                showAdminDeferToast('Error', 'An error occurred while processing the deferral.', 'error');
            } finally {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }

        // Show admin defer toast notification
        function showAdminDeferToast(title, message, type = 'success') {
            // Remove existing toasts
            document.querySelectorAll('.admin-defer-toast').forEach(toast => {
                toast.remove();
            });

            // Create toast element
            const toast = document.createElement('div');
            toast.className = `admin-defer-toast admin-defer-toast-${type}`;
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 99999;
                min-width: 300px;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.innerHTML = `
                <div style="display: flex; align-items: center;">
                    <i class="${icon}" style="margin-right: 10px; font-size: 18px;"></i>
                    <div>
                        <div style="font-weight: bold; margin-bottom: 5px;">${title}</div>
                        <div style="font-size: 14px;">${message}</div>
                    </div>
                </div>
            `;

            // Add to page
            document.body.appendChild(toast);

            // Show toast
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);

            // Auto-hide toast
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 4000);
        }

        // Handle admin defer submit button click
        document.addEventListener('click', function(e) {
            if (e.target.id === 'adminSubmitDeferral' || e.target.closest('#adminSubmitDeferral')) {
                e.preventDefault();
                submitAdminDeferral();
            }
        });

        // Initialize defer modal functionality on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Try to initialize defer modal when DOM is ready
            setTimeout(() => {
                try {
                    initializeAdminDeferModal();
                } catch (e) {
                    console.log('Admin defer modal not ready yet');
                }
            }, 1000);
            
            // Check for physical examination completion parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('physical_exam_completed') === '1') {
                // Show physical examination completed modal
                setTimeout(() => {
                    if (typeof window.showPhysicalExamCompleted === 'function') {
                        window.showPhysicalExamCompleted();
                    }
                }, 1000);
                
                // Clean up URL parameter
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
            
            // Check for blood collection completion parameter
            if (urlParams.get('success') === '1') {
                // Show blood collection completed modal
                setTimeout(() => {
                    if (typeof window.showBloodCollectionCompleted === 'function') {
                        window.showBloodCollectionCompleted();
                    }
                }, 1000);
                
                // Clean up URL parameter
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        });

        // Admin Medical History step-based modal opener (loads staff modal content)
        window.openAdminMedicalHistory = function(context) {
            const donorId = context?.donor_id ? String(context.donor_id) : '';
            const modalEl = document.getElementById('medicalHistoryModalAdmin');
            const contentEl = document.getElementById('medicalHistoryModalAdminContent');
            if (!modalEl || !contentEl) return;
            contentEl.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
            console.log(`🔄 Loading medical history content for donor: ${donorId}`);
            
            // First, fetch donor details to check status
            fetch(`../../assets/php_func/comprehensive_donor_details_api.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(response => response.json())
                .then(donorData => {
                    if (donorData.error) {
                        throw new Error(donorData.error);
                    }
                    
                    // Check medical history status to determine which buttons to show
                    const medicalHistory = donorData.medical_history || {};
                    const eligibility = donorData.eligibility || {};
                    const screeningForm = donorData.screening_form || {};
                    
                    // Determine if medical history needs approval or is already processed
                    // Get medical approval status (this is the key field used by staff dashboards)
                    const medicalApproval = medicalHistory.medical_approval || medicalHistory.status || screeningForm.medical_history_status || 'Pending';
                    
                    // Based on staff dashboard logic: if medical_approval is not 'Approved', show approve/decline buttons
                    const needsApproval = medicalApproval.toLowerCase() !== 'approved';
                    const isAlreadyApproved = medicalApproval.toLowerCase() === 'approved';
                    
                    console.log(`📊 Medical Approval: ${medicalApproval}, Needs Approval: ${needsApproval}, Already Approved: ${isAlreadyApproved}`);
                    
                    // Load the medical history modal
                    return fetch(`../../src/views/forms/medical-history-modal.php?donor_id=${encodeURIComponent(donorId)}`)
                        .then(r => {
                            console.log(`📡 Medical history response status: ${r.status}`);
                            if (!r.ok) {
                                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                            }
                            return r.text();
                        })
                        .then(html => { 
                            console.log(`✅ Medical history content loaded, length: ${html.length}`);
                            contentEl.innerHTML = html; 
                            
                            // Execute any script tags in the loaded content (like staff modal does)
                            const scripts = contentEl.querySelectorAll('script');
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
                            
                            // Call the question generation function after content is loaded
                            setTimeout(() => {
                                console.log('🔍 Checking for generateAdminMedicalHistoryQuestions function...');
                                if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                                    console.log('✅ Function found, calling it...');
                                    window.generateAdminMedicalHistoryQuestions();
                                } else {
                                    console.error('❌ generateAdminMedicalHistoryQuestions function not found');
                                }
                            }, 100);
                            
                            // Configure buttons based on donor status
                            setTimeout(() => {
                                const nextButton = document.getElementById('nextButton');
                                const prevButton = document.getElementById('prevButton');
                                
                                if (needsApproval) {
                                    // For donors who need medical history approval - show approve/decline buttons
                                    if (nextButton) {
                                        nextButton.style.display = 'none';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'none';
                                    }
                                    
                                    // Add approve/decline buttons
                                    const modalFooter = document.querySelector('#medicalHistoryModalAdmin .modal-footer');
                                    if (modalFooter && !document.getElementById('approveMedicalHistoryBtn')) {
                                        const approveBtn = document.createElement('button');
                                        approveBtn.className = 'btn btn-success me-2';
                                        approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve Medical History';
                                        approveBtn.id = 'approveMedicalHistoryBtn';
                                        
                                        const declineBtn = document.createElement('button');
                                        declineBtn.className = 'btn btn-danger';
                                        declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline Medical History';
                                        declineBtn.id = 'declineMedicalHistoryBtn';
                                        
                                        // Insert buttons before the close button
                                        const closeBtn = modalFooter.querySelector('button[data-bs-dismiss="modal"]');
                                        if (closeBtn) {
                                            modalFooter.insertBefore(approveBtn, closeBtn);
                                            modalFooter.insertBefore(declineBtn, closeBtn);
                                        } else {
                                            modalFooter.appendChild(approveBtn);
                                            modalFooter.appendChild(declineBtn);
                                        }
                                        
                                        // Bind event handlers
                                        approveBtn.addEventListener('click', function() {
                                            handleMedicalHistoryApproval(donorId, 'approve');
                                        });
                                        
                                        declineBtn.addEventListener('click', function() {
                                            handleMedicalHistoryApproval(donorId, 'decline');
                                        });
                                    }
                                    console.log('✅ Showing approve/decline buttons for medical history approval');
                                } else if (isAlreadyApproved) {
                                    // For already approved medical history - show view-only mode
                                    if (nextButton) {
                                        nextButton.style.display = 'none';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'none';
                                    }
                                    console.log('👁️ Showing view-only mode for already approved medical history');
                                } else {
                                    // For new donors or other statuses - show submit button (normal flow)
                                    if (nextButton) {
                                        nextButton.style.display = 'inline-block';
                                        nextButton.textContent = 'Next →';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'inline-block';
                                    }
                                    console.log('📝 Showing submit button for new donor or normal flow');
                                }
                            }, 200);
                        });
                })
                .catch(e => {
                    console.error('❌ Failed to load medical history:', e);
                    contentEl.innerHTML = `<div class="alert alert-danger">Failed to load medical history: ${e.message}</div>`;
                });
        };

    </script>

    </script>

    </script>
    </script>

    </script>

    </script>

    <script>
        // Role-based edit functions for donor information modal
        window.editMedicalHistory = function(donorId) {
            console.log('Editing medical history for donor:', donorId);
            // Open medical history modal for editing
            if (typeof window.openAdminMedicalHistory === 'function') {
                window.openAdminMedicalHistory({ donor_id: donorId });
            } else {
                alert('Medical history editing not available');
            }
        };

        window.editInitialScreening = function(donorId) {
            console.log('Editing initial screening for donor:', donorId);
            // Open screening modal for editing
            if (typeof window.openScreeningModal === 'function') {
                window.openScreeningModal({ donor_id: donorId });
            } else {
                alert('Initial screening editing not available');
            }
        };

        window.editPhysicianWorkflow = function(donorId) {
            console.log('Editing physician workflow for donor:', donorId);
            
            // Open medical history approval modal directly (like staff dashboard)
            // Don't close the Donor Information modal - show medical history modal on top
            openMedicalHistoryApprovalModal(donorId);
        };

        // Function to open medical history approval modal (similar to staff dashboard)
        function openMedicalHistoryApprovalModal(donorId) {
            console.log('Opening medical history approval modal for donor:', donorId);
            
            // Prevent multiple instances
            if (window.isOpeningMedicalHistory) {
                console.log("Medical history modal already opening, skipping...");
                return;
            }
            window.isOpeningMedicalHistory = true;
            
            // Track current donor ID
            window.currentDonorId = donorId;
            
            // Ensure the medical history modal starts with a clean state
            const mhElement = document.getElementById('medicalHistoryModal');
            if (!mhElement) {
                console.error('Medical history modal not found');
                window.isOpeningMedicalHistory = false;
                return;
            }
            
            // Reset any previous state
            mhElement.removeAttribute('style');
            mhElement.className = 'medical-history-modal';
            
            // Show the custom modal (like physical examination modal)
            mhElement.style.display = 'flex';
            setTimeout(() => mhElement.classList.add('show'), 10);
            
            // Show loading state in modal content
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
            
            // Load medical history content
            loadMedicalHistoryContent(donorId);
        }

        // Function to load medical history content
        function loadMedicalHistoryContent(donorId) {
            const modalContent = document.getElementById('medicalHistoryModalContent');
            if (!modalContent) return;

            // Fetch medical history content from the standalone modal
            fetch(`../../src/views/forms/medical-history-modal.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    // Update modal content
                    modalContent.innerHTML = html;
                    
                    // Execute any script tags in the loaded content
                    const scripts = modalContent.querySelectorAll('script');
                    scripts.forEach(script => {
                        try {
                            const newScript = document.createElement('script');
                            if (script.type) newScript.type = script.type;
                            if (script.src) {
                                newScript.src = script.src;
                            } else {
                                newScript.textContent = script.textContent;
                            }
                            document.head.appendChild(newScript);
                            document.head.removeChild(newScript);
                        } catch (e) {
                            console.warn('Error executing script:', e);
                        }
                    });
                    
                    // Initialize medical history approval functionality
                    if (typeof initializeMedicalHistoryApproval === 'function') {
                        initializeMedicalHistoryApproval();
                    }
                    
                    // Hide the submit button and show approve/decline buttons instead
                    const submitBtn = document.getElementById('nextButton');
                    if (submitBtn) {
                        submitBtn.style.display = 'none';
                    }
                    
                    // Add approve/decline buttons to the modal footer
                    const modalFooter = document.querySelector('#medicalHistoryModal .modal-footer');
                    if (modalFooter) {
                        // Check if buttons already exist
                        if (!document.getElementById('approveMedicalHistoryBtn')) {
                            const approveBtn = document.createElement('button');
                            approveBtn.className = 'btn btn-success me-2';
                            approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve Medical History';
                            approveBtn.id = 'approveMedicalHistoryBtn';
                            
                            const declineBtn = document.createElement('button');
                            declineBtn.className = 'btn btn-danger';
                            declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline Medical History';
                            declineBtn.id = 'declineMedicalHistoryBtn';
                            
                            // Insert buttons before the close button
                            const closeBtn = modalFooter.querySelector('button[data-bs-dismiss="modal"]');
                            if (closeBtn) {
                                modalFooter.insertBefore(approveBtn, closeBtn);
                                modalFooter.insertBefore(declineBtn, closeBtn);
                            } else {
                                modalFooter.appendChild(approveBtn);
                                modalFooter.appendChild(declineBtn);
                            }
                            
                            // Bind event handlers
                            approveBtn.addEventListener('click', function() {
                                handleMedicalHistoryApproval(donorId, 'approve');
                            });
                            
                            declineBtn.addEventListener('click', function() {
                                handleMedicalHistoryApproval(donorId, 'decline');
                            });
                        }
                    }
                    
                    // Force admin flow initialization
                    setTimeout(() => {
                        if (typeof mhInitializeAdminFlow === 'function') {
                            console.log('Forcing admin flow initialization');
                            mhInitializeAdminFlow();
                        }
                    }, 100);
                    
                    window.isOpeningMedicalHistory = false;
                })
                .catch(error => {
                    console.error('Error loading medical history content:', error);
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Failed to load medical history content: ${error.message}
                        </div>
                    `;
                    window.isOpeningMedicalHistory = false;
                });
        }

        // Function to close medical history modal
        window.closeMedicalHistoryModal = function() {
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
                
                console.log('Medical history modal closed');
            }, 300);
        };

        // Function to proceed to physical examination (called after medical history approval)
        window.proceedToPhysicalExamination = function(donorId) {
            console.log('Proceeding to physical examination for donor:', donorId);
            
            // Close medical history modal
            closeMedicalHistoryModal();
            
            // Open physical examination modal (like staff dashboard)
            setTimeout(() => {
                openPhysicalExaminationModal(donorId);
            }, 300);
        };

        // Function to open physical examination modal
        function openPhysicalExaminationModal(donorId) {
            console.log('Opening physical examination modal for donor:', donorId);
            
            // Create screening data object for the physical examination modal
            const screeningData = {
                donor_form_id: donorId,
                donor_id: donorId
            };
            
            // Open physical examination modal using the same approach as staff dashboard
            if (window.physicalExaminationModal) {
                window.physicalExaminationModal.openModal(screeningData);
            } else {
                // Fallback: redirect to physical examination form
                console.log('Physical examination modal not available, redirecting to form');
                window.location.href = `../../src/views/forms/physical-examination-form.php?donor_id=${encodeURIComponent(donorId)}`;
            }
        }

        window.editMedicalHistoryReview = function(donorId) {
            console.log('Editing medical history review for donor:', donorId);
            // Open physician medical history review modal for editing
            if (typeof window.openPhysicianMedicalReview === 'function') {
                window.openPhysicianMedicalReview({ donor_id: donorId });
            } else {
                alert('Medical history review editing not available');
            }
        };

        window.editPhysicalExamination = function(donorId) {
            console.log('Editing physical examination for donor:', donorId);
            // Open physical examination form for editing
            // Set session variables for the form
            fetch('../../assets/php_func/set_donor_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    donor_id: donorId,
                    action: 'set_donor_session'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
            window.location.href = `../../src/views/forms/physical-examination-form.php?donor_id=${encodeURIComponent(donorId)}`;
                } else {
                    console.error('Failed to set donor session:', data.message);
                    alert('Error: Failed to prepare physical examination form. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error setting donor session:', error);
                // Fallback to direct redirect
                window.location.href = `../../src/views/forms/physical-examination-form.php?donor_id=${encodeURIComponent(donorId)}`;
            });
        };

        window.editBloodCollection = function(donorId) {
            console.log('Editing blood collection for donor:', donorId);
            // Open blood collection form for editing
            // Set session variables for the form
            fetch('../../assets/php_func/set_donor_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    donor_id: donorId,
                    action: 'set_donor_session'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
            window.location.href = `../../src/views/forms/blood-collection-form.php?donor_id=${encodeURIComponent(donorId)}`;
                } else {
                    console.error('Failed to set donor session:', data.message);
                    alert('Error: Failed to prepare blood collection form. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error setting donor session:', error);
                // Fallback to direct redirect
                window.location.href = `../../src/views/forms/blood-collection-form.php?donor_id=${encodeURIComponent(donorId)}`;
            });
        };

        window.viewInterviewerDetails = function(donorId) {
            console.log('Viewing interviewer details for donor:', donorId);
            const eligibilityId = window.currentEligibilityId || 'pending_' + donorId;
            
            // Use the comprehensive donor details modal
            window.openDonorDetails({
                donor_id: donorId,
                eligibility_id: eligibilityId
            });
        };

        window.viewPhysicianDetails = function(donorId) {
            console.log('Viewing physician details for donor:', donorId);
            const eligibilityId = window.currentEligibilityId || 'pending_' + donorId;
            
            // Use the comprehensive donor details modal
            window.openDonorDetails({
                donor_id: donorId,
                eligibility_id: eligibilityId
            });
        };

        window.viewPhlebotomistDetails = function(donorId) {
            console.log('Viewing phlebotomist details for donor:', donorId);
            const eligibilityId = window.currentEligibilityId || 'pending_' + donorId;
            
            // Use the comprehensive donor details modal
            window.openDonorDetails({
                donor_id: donorId,
                eligibility_id: eligibilityId
            });
        };

        // Function to fetch and populate donor details modal with proper layout
        window.fetchDonorDetailsModal = function(donorId, eligibilityId) {
            console.log(`Fetching details for donor: ${donorId}, eligibility: ${eligibilityId}`);
            
            fetch(`../../assets/php_func/donor_details_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const donorDetailsContainer = document.getElementById('donorDetailsModalContent');
                    
                    if (data.error) {
                        donorDetailsContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    const donor = data.donor || {};
                    const eligibility = data.eligibility || {};
                    
                    const safe = (v, fb = '-') => (v === null || v === undefined || v === '' ? fb : v);
                    
                    // Create exact wireframe-matching donor details HTML
                    const donorDetailsHTML = `
                        <div class="donor-details-wireframe">
                            <!-- Donor Header - matches wireframe exactly -->
                            <div class="donor-header-wireframe">
                                <div class="donor-header-left">
                                    <h3 class="donor-name-wireframe">${safe(donor.first_name)} ${safe(donor.middle_name)} ${safe(donor.surname)}</h3>
                                    <div class="donor-age-gender">${safe(donor.age)}, ${safe(donor.sex)}</div>
                                </div>
                                <div class="donor-header-right">
                                    <div class="donor-id-wireframe">Donor ID ${safe(donor.donor_id)}</div>
                                    <div class="donor-blood-type">${safe(donor.blood_type)}</div>
                                </div>
                            </div>

                            <!-- Donor Information Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Donor Information</h3>
                                <div class="form-fields-grid">
                                    <div class="form-field">
                                        <label>Birthdate</label>
                                        <input type="text" value="${safe(donor.birthdate)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Address</label>
                                        <input type="text" value="${safe(donor.permanent_address || donor.office_address)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Mobile Number</label>
                                        <input type="text" value="${safe(donor.mobile || donor.mobile_number || donor.contact_number)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Civil Status</label>
                                        <input type="text" value="${safe(donor.civil_status)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Nationality</label>
                                        <input type="text" value="${safe(donor.nationality)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Occupation</label>
                                        <input type="text" value="${safe(donor.occupation)}" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Medical History Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Medical History</h3>
                                <table class="table-wireframe">
                                    <thead>
                                        <tr>
                                            <th>Medical History Result</th>
                                            <th>Interviewer Decision</th>
                                            <th>Physician Decision</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>${safe(eligibility.medical_history_status || 'Approved')}</td>
                                            <td>${safe(eligibility.interviewer_decision || '-')}</td>
                                            <td>${safe(eligibility.physician_decision || 'Approved')}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Medical History" onclick="viewMedicalHistory('${safe(donor.donor_id,'')}')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Initial Screening Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Initial Screening</h3>
                                <table class="table-wireframe">
                                    <thead>
                                        <tr>
                                            <th>Body Weight</th>
                                            <th>Specific Gravity</th>
                                            <th>Blood Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>${safe(eligibility.body_weight || '57 kg')}</td>
                                            <td>${safe(eligibility.specific_gravity || '12.8 g/dL')}</td>
                                            <td>${safe(donor.blood_type || 'A+')}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Physical Examination Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Physical Examination</h3>
                                <table class="table-wireframe">
                                    <thead>
                                        <tr>
                                            <th>Physical Examination Result</th>
                                            <th>Physician Decision</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>${safe(eligibility.physical_exam_result || 'Approved')}</td>
                                            <td>${safe(eligibility.physician_decision || 'Approved')}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Physical Examination" onclick="viewPhysicalExamination('${safe(donor.donor_id,'')}')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="physical-exam-extra">
                                    <div class="form-field-inline">
                                        <label>Type of Donation</label>
                                        <input type="text" value="${safe(eligibility.donation_type || '')}" readonly>
                                    </div>
                                    <div class="form-field-inline">
                                        <label>Eligibility Status</label>
                                        <button type="button" class="btn btn-success btn-sm eligibility-btn">Approve to Donate</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Blood Collection Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Blood Collection</h3>
                                <table class="table-wireframe">
                                    <thead>
                                        <tr>
                                            <th>Blood Collection Status</th>
                                            <th>Phlebotomist Note</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>${safe(eligibility.collection_status || 'Successful')}</td>
                                            <td>${safe(eligibility.phlebotomist_note || 'Successful')}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Blood Collection" onclick="viewBloodCollection('${safe(donor.donor_id,'')}')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    
                    donorDetailsContainer.innerHTML = donorDetailsHTML;
                })
                .catch(error => {
                    console.error('Error fetching donor details:', error);
                    const donorDetailsContainer = document.getElementById('donorDetailsModalContent');
                    donorDetailsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>Error Loading Donor Details</h6>
                            <p>Failed to load donor information. Please try again.</p>
                            <small class="text-muted">Error: ${error.message}</small>
                        </div>
                    `;
                });
        };

        // Test function to verify Donor Details Modal functionality
        window.testDonorDetailsModal = function(donorId = '171') {
            console.log('Testing Donor Details Modal with donor ID:', donorId);
            window.viewInterviewerDetails(donorId);
        };

    // View functions for wireframe action buttons
    window.viewMedicalHistory = function(donorId) {
        console.log('Viewing medical history for donor:', donorId);
        // Open medical history modal
        const medicalHistoryModal = document.getElementById('medicalHistoryModal');
        if (medicalHistoryModal) {
            const modal = new bootstrap.Modal(medicalHistoryModal);
            modal.show();
                } else {
            alert('Medical history modal not available');
        }
    };

    window.viewPhysicalExamination = function(donorId) {
        console.log('Viewing physical examination for donor:', donorId);
        // Open physical examination modal
        const physicalExamModal = document.getElementById('physicalExaminationModal');
        if (physicalExamModal) {
            const modal = new bootstrap.Modal(physicalExamModal);
            modal.show();
        } else {
            alert('Physical examination modal not available');
        }
    };

    window.viewBloodCollection = function(donorId) {
        console.log('Viewing blood collection for donor:', donorId);
        // Open blood collection modal
        const bloodCollectionModal = document.getElementById('bloodCollectionModal');
        if (bloodCollectionModal) {
            const modal = new bootstrap.Modal(bloodCollectionModal);
            modal.show();
        } else {
            alert('Blood collection modal not available');
        }
    };

    // Donor Details modal opener - shows comprehensive donor information
    window.openDonorDetails = function(context) {
        const donorId = context?.donor_id ? String(context.donor_id) : '';
        const modalEl = document.getElementById('donorDetailsModal');
        const contentEl = document.getElementById('donorDetailsModalContent');
        if (!modalEl || !contentEl) return;
        
        contentEl.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
        
        // Fetch comprehensive donor details from specific tables
        console.log(`Fetching donor details for ID: ${donorId}, eligibility: ${context?.eligibility_id || ''}`);
        
        // Try comprehensive API first, fallback to original if it fails
        const apiUrl = `../../assets/php_func/comprehensive_donor_details_api.php?donor_id=${encodeURIComponent(donorId)}&eligibility_id=${encodeURIComponent(context?.eligibility_id || '')}`;
        const fallbackUrl = `../../assets/php_func/donor_details_api.php?donor_id=${encodeURIComponent(donorId)}&eligibility_id=${encodeURIComponent(context?.eligibility_id || '')}`;
        
        fetch(apiUrl)
            .then(response => {
                console.log(`API Response status: ${response.status}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('API Response data:', data);
                if (data.error) {
                    console.error('Comprehensive API Error:', data.error);
                    console.log('Trying fallback API...');
                    // Try fallback API
                    return fetch(fallbackUrl)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`Fallback HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(fallbackData => {
                            console.log('Fallback API Response:', fallbackData);
                            if (fallbackData.error) {
                                throw new Error(fallbackData.error);
                            }
                            // Convert fallback data to comprehensive format
                            return {
                                donor_form: fallbackData.donor || {},
                                screening_form: {},
                                medical_history: {},
                                physical_examination: {},
                                eligibility: fallbackData.eligibility || {},
                                blood_collection: {},
                                completion_status: {
                                    donor_form: !!(fallbackData.donor && Object.keys(fallbackData.donor).length > 0),
                                    screening_form: false,
                                    medical_history: false,
                                    physical_examination: false,
                                    eligibility: !!(fallbackData.eligibility && Object.keys(fallbackData.eligibility).length > 0),
                                    blood_collection: false
                                }
                            };
                        });
                }
                return data;
            })
            .then(data => {
                if (data.error) {
                    console.error('API Error:', data.error);
                    contentEl.innerHTML = `<div class="alert alert-danger">
                        <h6>Error Loading Donor Details</h6>
                        <p>${data.error}</p>
                        <small>Donor ID: ${donorId}</small>
                    </div>`;
                    return;
                }
                
                const donorForm = data.donor_form || {};
                const screeningForm = data.screening_form || {};
                const medicalHistory = data.medical_history || {};
                const physicalExamination = data.physical_examination || {};
                const eligibility = data.eligibility || {};
                const bloodCollection = data.blood_collection || {};
                const completionStatus = data.completion_status || {};
                
                const safe = (v, fb = '-') => (v === null || v === undefined || v === '' ? fb : v);
                
                // Determine if donor is fully approved
                const isFullyApproved = eligibility.status === 'approved' || eligibility.status === 'eligible';
                
                // Create wireframe-matching donor details HTML
                const html = `
                    <div class="donor-details-wireframe">
                        <!-- Donor Header - matches wireframe exactly -->
                        <div class="donor-header-wireframe">
                            <div class="donor-header-left">
                                <h3 class="donor-name-wireframe">${safe(donorForm.surname)}, ${safe(donorForm.first_name)} ${safe(donorForm.middle_name)}</h3>
                                <div class="donor-age-gender">${safe(donorForm.age)}, ${safe(donorForm.sex)}</div>
                            </div>
                            <div class="donor-header-right">
                                <div class="donor-id-wireframe">Donor ID ${safe(donorForm.donor_id)}</div>
                                <div class="donor-blood-type">${safe(screeningForm.blood_type || donorForm.blood_type)}</div>
                            </div>
                        </div>

                        <!-- Donor Information Section -->
                        <div class="section-wireframe">
                            <h6 class="section-title">Donor Information:</h6>
                            <div class="form-fields-grid">
                                <div class="form-field">
                                    <label>Birthdate</label>
                                    <input type="text" class="form-control form-control-sm" value="${safe(donorForm.birthdate)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Address</label>
                                    <input type="text" class="form-control form-control-sm" value="${safe(donorForm.permanent_address || donorForm.office_address)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Mobile Number</label>
                                    <input type="text" class="form-control form-control-sm" value="${safe(donorForm.mobile || donorForm.mobile_number || donorForm.contact_number)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Civil Status</label>
                                    <input type="text" class="form-control form-control-sm" value="${safe(donorForm.civil_status)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Nationality</label>
                                    <input type="text" class="form-control form-control-sm" value="${safe(donorForm.nationality)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Occupation</label>
                                    <input type="text" class="form-control form-control-sm" value="${safe(donorForm.occupation)}" disabled>
                                </div>
                            </div>
                        </div>

                        <!-- Medical History Section -->
                        <div class="section-wireframe">
                            <h6 class="section-title">Medical History:</h6>
                            <table class="table-wireframe">
                                <thead>
                                    <tr>
                                        <th>Medical History Result</th>
                                        <th>Interviewer Decision</th>
                                        <th>Physician Decision</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>${safe(medicalHistory.status || screeningForm.medical_history_status, 'Approved')}</td>
                                        <td>-</td>
                                        <td>${safe(physicalExamination.medical_approval || medicalHistory.physician_decision, 'Approved')}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Medical History" onclick="openAdminMedicalHistory({ donor_id: '${safe(donorForm.donor_id,'')}' })">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Initial Screening Section -->
                        <div class="section-wireframe">
                            <h6 class="section-title">Initial Screening:</h6>
                            <table class="table-wireframe">
                                <thead>
                                    <tr>
                                        <th>Body Weight</th>
                                        <th>Specific Gravity</th>
                                        <th>Blood Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>${safe(screeningForm.body_weight)}</td>
                                        <td>${safe(screeningForm.specific_gravity)}</td>
                                        <td>${safe(screeningForm.blood_type)}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Physical Examination Section -->
                        <div class="section-wireframe">
                            <h6 class="section-title">Physical Examination:</h6>
                            <table class="table-wireframe">
                                <thead>
                                    <tr>
                                        <th>Physical Examination Result</th>
                                        <th>Physician Decision</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>${safe(physicalExamination.physical_exam_status || physicalExamination.status, 'Approved')}</td>
                                        <td>${safe(physicalExamination.physical_approval || physicalExamination.physician_decision, 'Approved')}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Physical Examination">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="donation-type-section">
                                <div class="form-field">
                                    <label>Type of Donation</label>
                                    <div class="field-value">${safe(eligibility.donation_type, 'Walk-In')}</div>
                                </div>
                                <div class="eligibility-status">
                                    <label>Eligibility Status</label>
                                    <div class="field-value">${safe(eligibility.status, 'Eligible')}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Blood Collection Section -->
                        <div class="section-wireframe">
                            <h6 class="section-title">Blood Collection:</h6>
                            <table class="table-wireframe">
                                <thead>
                                    <tr>
                                        <th>Blood Collection Status</th>
                                        <th>Phlebotomist Note</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>${safe(bloodCollection.is_successful ? 'TRUE' : 'Successful', 'Unsuccessful')}</td>
                                        <td>${safe(bloodCollection.phlebotomist_note, 'Successful')}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Blood Collection">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                
                contentEl.innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching donor details:', error);
                contentEl.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>Error Loading Donor Details</h6>
                        <p>Failed to load donor information. Please try again.</p>
                        <small class="text-muted">Error: ${error.message}</small>
                    </div>
                `;
            });
    };

    </script>

    <!-- Donor Details Modal -->
    <div class="modal fade" id="donorDetailsModal" tabindex="-1" aria-labelledby="donorDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xxl">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-header">
                    <div class="d-flex align-items-center">
                        <div class="donor-avatar me-3">
                            <i class="fas fa-user-circle fa-2x text-white"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0" id="donorDetailsModalLabel">Donor Information</h5>
                            <small class="text-white-50">Complete donor profile and submission details</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modern-body" id="donorDetailsModalContent">
                    <div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Admin Screening Form Modal Container (content fetched from staff modal content) -->
    <div class="modal fade" id="screeningFormModal" tabindex="-1" aria-labelledby="screeningFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title" id="screeningFormModalLabel"><i class="fas fa-clipboard-check me-2"></i>Initial Screening Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="screeningFormModalContent">
                    <div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
            </div>
        </div>
    </div>
    <style>
        /* Wireframe-matching Donor Details Modal Styles */
        #donorDetailsModal .modal-dialog { 
            max-width: 1000px; 
        }
        
        /* Admin Defer Modal Styles */
        .duration-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .duration-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }
        
        .duration-option:hover {
            border-color: #b22222;
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(178, 34, 34, 0.1);
        }
        
        .duration-option.active {
            border-color: #b22222;
            background-color: #b22222;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(178, 34, 34, 0.3);
        }
        
        .duration-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .duration-unit {
            font-size: 14px;
            font-weight: 500;
        }
        
        #adminDurationSection {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        
        #adminDurationSection.show {
            opacity: 1;
            max-height: 500px;
        }
        
        #adminCustomDurationSection {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        
        #adminCustomDurationSection.show {
            opacity: 1;
            max-height: 200px;
        }
        #donorDetailsModal .modal-body { 
            padding: 20px; 
        }
        
        /* Modern Modal Styles */
        .modern-modal {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modern-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            border-radius: 15px 15px 0 0;
            padding: 20px;
            border: none;
        }
        
        .modern-body {
            padding: 30px;
            background: #f8f9fa;
        }
        
        .modern-footer {
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
            padding: 20px;
            border: none;
        }
        
        .donor-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Donor Header Section */
        .donor-header-section {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border-radius: 12px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .donor-header-card {
            background: transparent;
        }
        
        .donor-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .donor-name-section {
            flex: 1;
        }
        
        .donor-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
        }
        
        .donor-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .donor-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .donor-date-section {
            display: flex;
            align-items: center;
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        /* Content Grid */
        .donor-content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .donor-content-column {
            display: flex;
            flex-direction: column;
        }
        
        /* Section Cards */
        .donor-section-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .donor-section-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
            color: #495057;
            font-size: 1.1rem;
        }
        
        .donor-section-header i {
            color: #b22222;
            font-size: 1.2rem;
        }
        
        /* Field Lists */
        .donor-field-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .donor-field-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .donor-field-item:last-child {
            border-bottom: none;
        }
        
        .donor-field-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .donor-field-value {
            font-weight: 600;
            color: #495057;
            text-align: right;
            max-width: 60%;
            word-wrap: break-word;
        }
        
        /* Address Field */
        .donor-address-field {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .donor-address-field .donor-field-value {
            text-align: left;
            max-width: 100%;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        /* Additional Info */
        .donor-additional-info {
            transition: all 0.3s ease;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .donor-content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .donor-header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .donor-badges {
                justify-content: flex-start;
            }
            
            .modern-body {
                padding: 20px;
            }
        }
        
        /* Wireframe Header Styles */
        .donor-header-wireframe {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding: 0;
        }
        
        .donor-header-left {
            flex: 1;
        }
        
        .donor-name-wireframe {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .donor-age-gender {
            font-size: 1rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .donor-header-right {
            text-align: right;
        }
        
        .donor-id-wireframe {
            font-size: 1rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }
        
        .donor-blood-type {
            font-size: 1.2rem;
            font-weight: 600;
            color: #dc3545;
        }
        
        /* Section Styles */
        .section-wireframe {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-weight: 700;
            color: #212529;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        /* Form Fields Grid */
        .form-fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
        }
        
        .form-field label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-field input {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 0.9rem;
        }
        
        /* Table Styles */
        .table-wireframe {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .table-wireframe th {
            background-color: #dc3545;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #dc3545;
        }
        
        .table-wireframe td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .table-wireframe tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .table-wireframe tr:hover {
            background-color: #e9ecef;
        }
        
        /* Circular Button Styles */
        .circular-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 1px solid #007bff;
            background: white;
            color: #007bff;
            transition: all 0.3s ease;
        }
        
        .circular-btn:hover {
            background: #007bff;
            color: white;
            transform: scale(1.1);
        }
        
        .circular-btn i {
            font-size: 0.8rem;
        }
        
        /* Physical Examination Extra Fields */
        .physical-exam-extra {
            margin-top: 15px;
            display: flex;
            gap: 20px;
            align-items: end;
        }
        
        .form-field-inline {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }
        
        .form-field-inline label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-field-inline input {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 0.9rem;
            background-color: #f8f9fa;
        }
        
        .eligibility-btn {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 4px;
            border: 1px solid #28a745;
        }
        
        .eligibility-btn:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        /* Wireframe-matching Donor Details Modal Styles */
        #donorDetailsModal .modal-dialog { 
            max-width: 1000px; 
        }
        #donorDetailsModal .modal-body { 
            padding: 20px; 
        }
        
        /* Wireframe Header Styles */
        .donor-header-wireframe {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding: 0;
        }
        
        .donor-header-left {
            flex: 1;
        }
        
        .donor-name-wireframe {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .donor-age-gender {
            font-size: 1rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .donor-header-right {
            text-align: right;
        }
        
        .donor-id-wireframe {
            font-size: 1rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }
        
        .donor-blood-type {
            font-size: 1.2rem;
            font-weight: 600;
            color: #dc3545;
        }
        
        /* Section Styles */
        .section-wireframe {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-weight: 700;
            color: #212529;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        /* Form Fields Grid */
        .form-fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
        }
        
        .form-field label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-field input {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 0.9rem;
        }
        
        /* Table Styles */
        .table-wireframe {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .table-wireframe th {
            background-color: #dc3545;
            color: white;
            font-weight: 600;
            padding: 12px 15px;
            text-align: left;
            border: none;
            font-size: 0.9rem;
        }
        
        .table-wireframe th:first-child {
            text-align: left;
        }
        
        .table-wireframe th:not(:first-child) {
            text-align: center;
        }
        
        .table-wireframe td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .table-wireframe td:first-child {
            text-align: left;
        }
        
        .table-wireframe tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Donation Type Section */
        .donation-type-section {
            display: flex;
            gap: 20px;
            align-items: end;
            margin-top: 15px;
        }
        
        .donation-type-section .form-field {
            flex: 1;
        }
        
        .eligibility-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .eligibility-status label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .eligibility-status .btn {
            min-width: 140px;
        }
        
        /* Field Value Styling */
        .field-value {
            font-weight: 700;
            color: #000;
            font-size: 1rem;
            margin-top: 5px;
            padding: 8px 0;
        }
        
        /* Button Styles */
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
            color: #007bff;
        }
        
        .circular-btn:hover {
            background-color: #007bff;
            color: white;
        }
        
        .circular-btn i {
            font-size: 12px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-fields-grid {
                grid-template-columns: 1fr;
            }
            
            .donation-type-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .eligibility-status {
                align-items: flex-start;
            }
        }
        
        .table-wireframe th:first-child {
            text-align: left;
        }
        
        .table-wireframe th:not(:first-child) {
            text-align: center;
        }
        
        .table-wireframe td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .table-wireframe td:first-child {
            text-align: left;
        }
        
        .table-wireframe tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Donation Type Section */
        .donation-type-section {
            display: flex;
            gap: 20px;
            align-items: end;
            margin-top: 15px;
        }
        
        .donation-type-section .form-field {
            flex: 1;
        }
        
        .eligibility-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .eligibility-status label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .eligibility-status .btn {
            min-width: 140px;
        }
        
        /* Field Value Styling */
        .field-value {
            font-weight: 700;
            color: #000;
            font-size: 1rem;
            margin-top: 5px;
            padding: 8px 0;
        }
        
        /* Button Styles */
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
            color: #007bff;
        }
        
        .circular-btn:hover {
            background-color: #007bff;
            color: white;
        }
        
        .circular-btn i {
            font-size: 12px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-fields-grid {
                grid-template-columns: 1fr;
            }
            
            .donation-type-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .eligibility-status {
                align-items: flex-start;
            }
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
            max-height: calc(90vh - 120px);
            overflow-y: auto;
        }

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