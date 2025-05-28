<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Get the status parameter from URL
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$donations = [];
$error = null;
$pageTitle = "Loading...";

// Based on status, include the appropriate module
try {
    switch ($status) {
        case 'pending':
            include_once 'modules/donation_pending.php';
            $donations = $pendingDonations ?? [];
            $pageTitle = "Pending Donations";
            break;
        case 'approved':
            include_once 'modules/donation_approved.php';
            $donations = $approvedDonations ?? [];
            $pageTitle = "Approved Donations";
            break;
        case 'declined':
            include_once 'modules/donation_declined.php';
            $donations = $declinedDonations ?? [];
            $pageTitle = "Declined Donations";
            break;
        default:
            include_once 'modules/donation_pending.php';
            $donations = $pendingDonations ?? [];
            $pageTitle = "Pending Donations";
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
    color: #dc3545;
    background-color: transparent;
}

.dashboard-home-sidebar .collapse-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    border-radius: 4px;
    background-color: #f8f8f8;
}

.dashboard-home-sidebar .collapse-menu .nav-link {
    padding: 8px 15px 8px 40px;
    font-size: 0.85rem;
    margin: 0;
    border-radius: 0;
    color: #666;
}

.dashboard-home-sidebar .collapse-menu .nav-link:hover {
    background-color: #f0f0f0;
    color: #dc3545;
}

.dashboard-home-sidebar .collapse-menu .nav-link.active {
    color: #dc3545;
    font-weight: 600;
    background-color: #f0f0f0;
}

.dashboard-home-sidebar .nav-link[aria-expanded="true"] {
    color: #dc3545;
    background-color: transparent;
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

/* Blood Donations Section */
#bloodDonationsCollapse {
    margin-top: 2px;
    border: none;
}

#bloodDonationsCollapse .nav-link {
    color: #666;
    padding: 8px 15px 8px 40px;
}

#bloodDonationsCollapse .nav-link:hover {
    color: #dc3545;
    font-weight: 600;
    background-color: transparent;
}

#bloodDonationsCollapse .nav-link.active {
    color: #dc3545 !important;
    font-weight: 600;
    background-color: transparent;
}

.dashboard-home-sidebar .collapse-menu .nav-link.active {
    color: #dc3545 !important;
    font-weight: 600;
    background-color: transparent;
}

.dashboard-home-sidebar .nav-link[aria-expanded="true"] {
    color: #dc3545;
    background-color: transparent;
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
            <div class="position-sticky">
                    <div class="d-flex align-items-center ps-1 mb-3 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" style="width: 65px; height: 65px; object-fit: contain;">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                <ul class="nav flex-column">
                    <a href="dashboard-Inventory-System.php" class="nav-link">
                        <span><i class="fas fa-home"></i>Home</span>
                    </a>
                    <a class="nav-link" data-bs-toggle="collapse" href="#bloodDonationsCollapse" role="button" aria-expanded="false" aria-controls="bloodDonationsCollapse">
                        <span><i class="fas fa-tint"></i>Blood Donations</span>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <div class="collapse<?php echo (!isset($status) || in_array($status, ['pending', 'approved', 'declined'])) ? ' show' : ''; ?>" id="bloodDonationsCollapse">
                        <div class="collapse-menu">
                            <a href="dashboard-Inventory-System-list-of-donations.php?status=pending" class="nav-link<?php echo ($status === 'pending' || !$status) ? ' active' : ''; ?>">Pending</a>
                            <a href="dashboard-Inventory-System-list-of-donations.php?status=approved" class="nav-link<?php echo $status === 'approved' ? ' active' : ''; ?>">Approved</a>
                            <a href="dashboard-Inventory-System-list-of-donations.php?status=declined" class="nav-link<?php echo $status === 'declined' ? ' active' : ''; ?>">Declined</a>
                        </div>
                    </div>
                    <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                        <span><i class="fas fa-tint"></i>Blood Bank</span>
                    </a>
                    <a class="nav-link" data-bs-toggle="collapse" href="#hospitalRequestsCollapse" role="button" aria-expanded="false" aria-controls="hospitalRequestsCollapse">
                        <span><i class="fas fa-list"></i>Hospital Requests</span>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <div class="collapse" id="hospitalRequestsCollapse">
                        <div class="collapse-menu">
                            <a href="Dashboard-Inventory-System-Hospital-Request.php?status=requests" class="nav-link">Requests</a>
                            <a href="Dashboard-Inventory-System-Handed-Over.php?status=accepted" class="nav-link">Accepted</a>
                            <a href="Dashboard-Inventory-System-Handed-Over.php?status=handedover" class="nav-link">Handed Over</a>
                            <a href="Dashboard-Inventory-System-Handed-Over.php?status=declined" class="nav-link">Declined</a>
                        </div>
                    </div>
                    <a href="../../assets/php_func/logout.php" class="nav-link">
                            <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                    </a>
                </ul>
            </div>
           </nav>
           <!-- Main Content -->
           <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="container-fluid p-4 custom-margin">
                        <h2 class="card-title"><?php echo $pageTitle; ?></h2>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="search-container">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <select class="form-select category-select" id="searchCategory" style="max-width: 150px;">
                                            <option value="all">All Fields</option>
                                            <option value="donor">Donor Name</option>
                                            <option value="status">Status</option>
                                            <option value="date">Date</option>
                                        </select>
                                        <input type="text" 
                                            class="form-control" 
                                            id="searchInput" 
                                            placeholder="Search donations...">
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
                        <!-- Responsive Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="donationsTable" data-start-index="<?php echo $startIndex; ?>" data-items-per-page="<?php echo $itemsPerPage; ?>" data-total-items="<?php echo $totalItems; ?>" data-current-page="<?php echo $currentPage; ?>" data-status="<?php echo $status; ?>">
                                <thead class="table-dark">
                                    <tr>
                                        <?php if ($status === 'approved'): ?>
                                        <th>Surname</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Age</th>
                                        <th>Sex</th>
                                            <th>Blood Type</th>
                                            <th>Donation Type</th>
                                            <th>Actions</th>
                                        <?php elseif ($status === 'pending'): ?>
                                            <th>Surname</th>
                                            <th>First Name</th>
                                            <th>Age</th>
                                            <th>Sex</th>
                                            <th>Date Submitted</th>
                                            <th>Gateway</th>
                                            <th>Actions</th>
                                        <?php elseif ($status === 'declined'): ?>
                                            <th>Surname</th>
                                            <th>First Name</th>
                                            <th>Remarks</th>
                                            <th>Reason for Rejection</th>
                                            <th>Rejection Date</th>
                                            <th>Actions</th>
                                        <?php else: ?>
                                            <th>Surname</th>
                                            <th>First Name</th>
                                            <th>Middle Name</th>
                                        <th>Blood Type</th>
                                        <th>Donation Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$error && is_array($currentPageDonations) && count($currentPageDonations) > 0): ?>
                                        <?php foreach ($currentPageDonations as $donation): ?>
                                            <tr class="donor-row" data-donor-id="<?php echo htmlspecialchars($donation['donor_id']); ?>" data-eligibility-id="<?php echo htmlspecialchars($donation['eligibility_id'] ?? ''); ?>" style="cursor: pointer;">
                                                <?php if ($status === 'approved'): ?>
                                                    <td><?php echo htmlspecialchars($donation['surname'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['first_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['middle_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['age'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['sex'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['blood_type'] ?? 'Unknown'); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['donation_type'] ?? 'Unknown'); ?></td>
                                                <?php elseif ($status === 'pending'): ?>
                                                    <td><?php echo htmlspecialchars($donation['surname'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['first_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['age'] ?? calculateAge($donation['birthdate'])); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['sex'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['date_submitted'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['registration_source'] ?? 'PRC System'); ?></td>
                                                <?php elseif ($status === 'declined'): ?>
                                                    <td><?php echo htmlspecialchars($donation['surname'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['first_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['rejection_source'] ?? 'Physical Examination'); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['rejection_reason'] ?? 'Unspecified'); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['rejection_date'] ?? date('M d, Y')); ?></td>
                                                <?php else: ?>
                                                    <td><?php echo htmlspecialchars($donation['surname'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['first_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['middle_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['blood_type'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['donation_type'] ?? ''); ?></td>
                                                    <td>
                                                        <?php if ($status === 'pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                        <?php elseif ($status === 'approved'): ?>
                                                        <span class="badge bg-success">Approved</span>
                                                        <?php elseif ($status === 'declined'): ?>
                                                        <span class="badge bg-danger">Declined</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-secondary"><?php echo ucfirst($status); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                                
                                                <td onclick="event.stopPropagation();">
                                                    <button class="btn btn-sm btn-info view-donor" data-donor-id="<?php echo htmlspecialchars($donation['donor_id']); ?>" data-eligibility-id="<?php echo htmlspecialchars($donation['eligibility_id'] ?? ''); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($status !== 'declined' && $status !== 'approved' && $status !== 'pending'): ?>
                                                    <button class="btn btn-sm btn-warning edit-donor" data-donor-id="<?php echo htmlspecialchars($donation['donor_id']); ?>" data-eligibility-id="<?php echo htmlspecialchars($donation['eligibility_id'] ?? ''); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo ($status === 'approved') ? '8' : (($status === 'pending' || $status === 'declined') ? '6' : '7'); ?>" class="text-center">
                                                No <?php echo $status; ?> donations found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
            
                        <!-- Pagination Controls -->
                        <?php if (!$error && $totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($currentPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="dashboard-Inventory-System-list-of-donations.php?status=<?php echo $status; ?>&page=<?php echo $currentPage - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                // Display limited number of page links
                                $maxPagesToShow = 5;
                                $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
                                $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                                
                                // Adjust start page if we're near the end
                                if ($endPage - $startPage + 1 < $maxPagesToShow && $startPage > 1) {
                                    $startPage = max(1, $endPage - $maxPagesToShow + 1);
                                }
                                
                                // Show first page with ellipsis if needed
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="dashboard-Inventory-System-list-of-donations.php?status=' . $status . '&page=1">1</a></li>';
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                // Show page links
                                for ($i = $startPage; $i <= $endPage; $i++): 
                                ?>
                                    <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                        <a class="page-link" href="dashboard-Inventory-System-list-of-donations.php?status=<?php echo $status; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; 
                                
                                // Show last page with ellipsis if needed
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="dashboard-Inventory-System-list-of-donations.php?status=' . $status . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                                }
                                ?>

                                <?php if ($currentPage < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="dashboard-Inventory-System-list-of-donations.php?status=<?php echo $status; ?>&page=<?php echo $currentPage + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <!-- Showing entries information -->
                        <?php if (!$error && $totalItems > 0): ?>
                        <div class="text-center mt-2 mb-4">
                            <p class="text-muted">
                                Showing <?php echo min($totalItems, $startIndex + 1); ?> to <?php echo min($totalItems, $startIndex + $itemsPerPage); ?> of <?php echo $totalItems; ?> entries
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
            
<!-- Donor Details Modal -->
<div class="modal fade" id="donorModal" tabindex="-1" aria-labelledby="donorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header bg-dark text-white">
                <h4 class="modal-title w-100"><i class="fas fa-user me-2"></i> Donor Details</h4>
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
                
                // Perform search filtering
                function performSearch() {
                    const value = searchInput.value.toLowerCase().trim();
                    const category = searchCategory.value;
                    
                    // Remove any existing "no results" message
                    const existingNoResults = tbody.querySelector('.no-results');
                    if (existingNoResults) {
                        existingNoResults.remove();
                    }
                    
                    let visibleCount = 0;
                    
                    // Filter rows based on search criteria
                    rows.forEach(row => {
                        let found = false;
                        
                        if (category === 'all') {
                            // Search all cells
                            const cells = Array.from(row.querySelectorAll('td'));
                            cells.forEach(cell => {
                                if (cell.textContent.toLowerCase().includes(value)) {
                                    found = true;
                                }
                            });
                        } else if (category === 'donor') {
                            // Search donor name (columns 0 and 1 for surname and first name)
                            const nameColumns = [row.cells[0], row.cells[1]];
                            if (row.cells[2] && !row.cells[2].querySelector('.badge')) {
                                nameColumns.push(row.cells[2]); // Middle name if it exists
                            }
                            
                            nameColumns.forEach(cell => {
                                if (cell && cell.textContent.toLowerCase().includes(value)) {
                                    found = true;
                                }
                            });
                        } else if (category === 'status') {
                            // Search for status badge or status column
                            const statusBadge = row.querySelector('.badge');
                            if (statusBadge && statusBadge.textContent.toLowerCase().includes(value)) {
                                found = true;
                            } else if (row.cells[5] && row.cells[5].textContent.toLowerCase().includes(value)) {
                                // Assuming status is in column 5 in some views
                                found = true;
                            }
                        } else if (category === 'date') {
                            // Search for date in any cell
                            const cells = Array.from(row.querySelectorAll('td'));
                            cells.forEach(cell => {
                                if (cell.textContent.toLowerCase().includes(value)) {
                                    // Simple check for date patterns
                                    if (/\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}|\d{4}-\d{2}-\d{2}|[a-z]{3}\s\d{1,2},\s\d{4}/i.test(cell.textContent)) {
                                        found = true;
                                    }
                                }
                            });
                        }
                        
                        // Show/hide row based on search result
                        if (found) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Show "no results" message if needed
                    if (visibleCount === 0 && rows.length > 0) {
                        const noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results';
                        const colspan = table.querySelector('thead th:last-child') ? 
                                      table.querySelector('thead th:last-child').cellIndex + 1 : 6;
                        
                        noResultsRow.innerHTML = `
                            <td colspan="${colspan}" class="text-center">
                                <div class="alert alert-info m-2">
                                    No matching donations found
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
            
            // Add event listeners for search
            document.getElementById('searchInput').addEventListener('keyup', searchDonations);
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
                    window.location.href = '../../src/views/forms/donor-form-modal.php';
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
                    // Populate modal with donor details
                    const donorDetailsContainer = document.getElementById('donorDetails');
                    
                    if (data.error) {
                        donorDetailsContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    const donor = data.donor;
                    const eligibility = data.eligibility;
                    
                    // Check if the donor is approved
                    const isApproved = eligibility.status === 'approved';
                    
                    // Format the details in the compact style from the image
                    let html = `
                    <div class="donor-details-container">
                        <div><strong>Surname:</strong> ${donor.surname || 'N/A'}</div>
                        <div><strong>Age:</strong> ${donor.age || 'N/A'}</div>
                        
                        <div><strong>First Name:</strong> ${donor.first_name || 'N/A'}</div>
                        <div><strong>Sex:</strong> ${donor.sex || 'N/A'}</div>
                        
                        <div><strong>Middle Name:</strong> ${donor.middle_name || 'N/A'}</div>
                        <div><strong>Civil Status:</strong> ${donor.civil_status || 'Single'}</div>
                        
                        <div><strong>Birthdate:</strong> ${donor.birthdate || 'N/A'}</div>
                        <div><strong>Donation Date:</strong> ${eligibility.start_date ? new Date(eligibility.start_date).toLocaleDateString() : '4/1/2025'}</div>
                        
                        <div><strong>Permanent Address:</strong> ${donor.permanent_address || 'N/A'}</div>
                        ${isApproved ? `<div><strong>Eligibility End Date:</strong> ${eligibility.end_date ? new Date(eligibility.end_date).toLocaleDateString() : 'N/A'}</div>` : ''}
                        
                        ${isApproved ? `<div><strong>Blood Bag Type:</strong> ${eligibility.blood_bag_type || 'Not specified'}</div>` : ''}
                        ${isApproved ? `<div><strong>Amount Collected:</strong> ${eligibility.amount_collected || 'Not specified'}</div>` : ''}
                        
                        ${isApproved ? `<div><strong>Donor Reaction:</strong> ${eligibility.donor_reaction || 'None'}</div>` : ''}
                        ${isApproved ? `<div><strong>Management Done:</strong> ${eligibility.management_done || 'None'}</div>` : ''}
                        
                        ${isApproved ? `<div><strong>Office Address:</strong> ${donor.office_address || 'Not specified'}</div>` : ''}
                        </div>
                    
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        ${eligibility.status === 'pending' ? `
                        <button type="button" class="btn btn-primary" id="processThisDonorBtn" data-donor-id="${donor.donor_id}">
                            <i class="fas fa-list-check me-2"></i> Process This Donor
                        </button>
                        <button type="button" class="btn btn-success" id="viewEditDonorFormBtn" data-donor-id="${donor.donor_id}">
                            <i class="fas fa-edit me-2"></i> View/Edit Donor Form
                        </button>
                        ` : eligibility.status === 'declined' ? `
                        <button type="button" class="btn btn-secondary" disabled>
                            <i class="fas fa-info-circle me-2"></i> Donor is Declined
                        </button>
                        ` : eligibility.status === 'approved' ? `
                        ` : `
                        <button type="button" class="btn btn-primary" id="processThisDonorBtn" data-donor-id="${donor.donor_id}">
                            <i class="fas fa-list-check me-2"></i> Process This Donor
                        </button>
                        <button type="button" class="btn btn-success" id="viewEditDonorFormBtn" data-donor-id="${donor.donor_id}">
                            <i class="fas fa-edit me-2"></i> View/Edit Donor Form
                        </button>
                        `}
                    </div>`;
                    
                    donorDetailsContainer.innerHTML = html;
                    
                    // Add some styling to match the compact format in the image
                    const styleElement = document.createElement('style');
                    styleElement.textContent = `
                        .donor-details-container {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 8px 16px;
                            margin-bottom: 20px;
                        }
                        .donor-details-container > div {
                            padding: 8px 0;
                            border-bottom: 1px solid #eee;
                        }
                        /* Ensure the grid maintains even columns */
                        .donor-details-container > div:nth-child(odd) {
                            grid-column: 1;
                        }
                        .donor-details-container > div:nth-child(even) {
                            grid-column: 2;
                        }
                        /* Fix for approved donors with extra fields */
                        .donor-details-container > div:only-child {
                            grid-column: 1 / span 2;
                        }
                        .modal-body {
                            padding: 20px 25px;
                        }
                        #processThisDonorBtn, #viewEditDonorFormBtn {
                            padding: 8px 16px;
                            font-size: 14px;
                        }
                    `;
                    donorDetailsContainer.appendChild(styleElement);
                    
                    // Add click handlers for the newly created buttons
                    if (eligibility.status !== 'declined' && eligibility.status !== 'approved') {
                        // Only add event listeners for buttons that exist for eligible donors
                        document.getElementById('processThisDonorBtn').addEventListener('click', function() {
                            const donorId = this.getAttribute('data-donor-id');
                            
                            if (!donorId) {
                                console.error('No donor ID found for process button');
                                alert('Error: Donor ID not found. Please try again.');
                                return;
                            }
                            
                            console.log('Processing donor ID:', donorId);
                            
                            // Set global variable
                            window.currentDonorId = donorId;
                            
                            // Close donor details modal and show process confirmation modal
                            const donorModal = bootstrap.Modal.getInstance(document.getElementById('donorModal'));
                            const processDonorConfirmationModal = new bootstrap.Modal(document.getElementById('processDonorConfirmationModal'));
                            
                            if (donorModal) donorModal.hide();
                            if (processDonorConfirmationModal) processDonorConfirmationModal.show();
                        });
                        
                        document.getElementById('viewEditDonorFormBtn').addEventListener('click', function() {
                            const donorId = this.getAttribute('data-donor-id');
                            
                            if (!donorId) {
                                console.error('No donor ID found for view/edit button');
                                alert('Error: Donor ID not found. Please try again.');
                                return;
                            }
                            
                            console.log('Viewing/editing donor ID:', donorId);
                            
                            // Close donor modal and show loading modal
                            const donorModal = bootstrap.Modal.getInstance(document.getElementById('donorModal'));
                            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                            
                            if (donorModal) donorModal.hide();
                            loadingModal.show();
                            
                            // Store the donor_id in the session then redirect
                            fetch('../../assets/php_func/set_donor_session.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    donor_id: donorId,
                                    view_mode: true // Flag to indicate this is for viewing/editing
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    console.log('Donor ID stored in session for viewing/editing');
                                    setTimeout(() => {
                                        window.location.href = '../../src/views/forms/donor-form.php?mode=edit&donor_id=' + donorId;
                                    }, 1000);
                                } else {
                                    console.error('Failed to store donor ID in session:', data.error);
                                    alert('Error: Failed to prepare donor form. Please try again.');
                                    loadingModal.hide();
                                }
                            })
                            .catch(error => {
                                console.error('Error preparing donor form:', error);
                                alert('Error: Failed to prepare donor form. Please try again.');
                                loadingModal.hide();
                            });
                        });
                    } else if (eligibility.status === 'approved') {
                        // For approved donors, no buttons to handle
                    }
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
</body>
</html>