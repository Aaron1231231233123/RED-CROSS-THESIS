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
        case 'walk-in':
            include_once 'modules/donation_walkin.php';
            $donations = $walkinDonations ?? [];
            $pageTitle = "Walk-in Donations";
            break;
        case 'donated':
            include_once 'modules/donation_donated.php';
            $donations = $donatedDonations ?? [];
            $pageTitle = "Completed Donations";
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
    border-radius: 0;
}

.dashboard-home-sidebar .nav-link[aria-expanded="true"] {
    background-color: #f8f9fa;
    color: #dc3545;
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
    <!-- Place modals at the root level -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to proceed to the donor form?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="proceedBtn">Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body text-center">
                    <div class="spinner-border text-danger" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Donor Confirmation Modal -->
    <div class="modal fade" id="viewDonorConfirmationModal" tabindex="-1" aria-labelledby="viewDonorConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Donor Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Would you like to view this donor's details or process this donor now?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" id="viewDonorDetailsBtn">View Details</button>
                    <button type="button" class="btn btn-primary" id="processDonorBtn">Process Donor</button>
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
                    <p>You are about to process this donor. This will redirect you to the medical history and physical examination forms.</p>
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
                <button type="button" class="btn btn-danger" id="addWalkInBtn">
                    <i class="fas fa-plus me-2"></i>Add Walk-in Donor
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <input type="text" class="form-control" placeholder="Search...">
                <a href="dashboard-Inventory-System.php" class="nav-link">
                    <span><i class="fas fa-home"></i>Home</span>
                </a>
                
                <a class="nav-link active" data-bs-toggle="collapse" href="#bloodDonationsCollapse" role="button" aria-expanded="true" aria-controls="bloodDonationsCollapse">
                    <span><i class="fas fa-tint"></i>Blood Donations</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse show" id="bloodDonationsCollapse">
                    <div class="collapse-menu">
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=pending" class="nav-link <?php echo $status === 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=approved" class="nav-link <?php echo $status === 'approved' ? 'active' : ''; ?>">Approved</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=walk-in" class="nav-link <?php echo $status === 'walk-in' ? 'active' : ''; ?>">Walk-in</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=donated" class="nav-link <?php echo $status === 'donated' ? 'active' : ''; ?>">Donated</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=declined" class="nav-link <?php echo $status === 'declined' ? 'active' : ''; ?>">Declined</a>
                    </div>
                </div>

                <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                    <span><i class="fas fa-tint"></i>Blood Bank</span>
                </a>
                <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link">
                    <span><i class="fas fa-list"></i>Requests</span>
                </a>
                <a href="Dashboard-Inventory-System-Handed-Over.php" class="nav-link">
                    <span><i class="fas fa-check"></i>Handover</span>
                </a>
                <a href="../../assets/php_func/logout.php" class="nav-link">
                        <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                </a>
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
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger">
                            Error loading data: <?php echo $error; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Divider Line -->
                <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
                        <!-- Responsive Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
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
                                            <th>Birthdate</th>
                                            <th>Sex</th>
                                            <th>Date Submitted</th>
                                            <th>Actions</th>
                                        <?php elseif ($status === 'declined'): ?>
                                            <th>Donor ID</th>
                                            <th>Surname</th>
                                            <th>First Name</th>
                                            <th>Rejection Source</th>
                                            <th>Reason for Rejection</th>
                                            <th>Rejection Date</th>
                                            <th>Actions</th>
                                        <?php else: ?>
                                            <th>Donor ID</th>
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
                                    <?php if (!$error && is_array($donations) && count($donations) > 0): ?>
                                        <?php foreach ($donations as $donation): ?>
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
                                                    <td><?php echo htmlspecialchars($donation['birthdate'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['sex'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['date_submitted'] ?? ''); ?></td>
                                                <?php elseif ($status === 'declined'): ?>
                                                    <td><?php echo htmlspecialchars($donation['donor_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['surname'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['first_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['rejection_source'] ?? 'Physical Examination'); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['rejection_reason'] ?? 'Unspecified'); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['rejection_date'] ?? date('M d, Y')); ?></td>
                                                <?php else: ?>
                                                    <td><?php echo htmlspecialchars($donation['donor_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['surname'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['first_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['middle_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['blood_type'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['donation_type'] ?? ''); ?></td>
                                                    <td>
                                                        <?php if ($status === 'walk-in'): ?>
                                                            <span class="badge bg-info">Walk-in</span>
                                                        <?php elseif ($status === 'donated'): ?>
                                                            <span class="badge bg-primary">Donated</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Declined</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                                
                                                <td onclick="event.stopPropagation();">
                                                    <button class="btn btn-sm btn-info view-donor" data-donor-id="<?php echo htmlspecialchars($donation['donor_id']); ?>" data-eligibility-id="<?php echo htmlspecialchars($donation['eligibility_id'] ?? ''); ?>" data-bs-toggle="modal" data-bs-target="#viewDonorConfirmationModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning edit-donor" data-donor-id="<?php echo htmlspecialchars($donation['donor_id']); ?>" data-eligibility-id="<?php echo htmlspecialchars($donation['eligibility_id'] ?? ''); ?>" data-bs-toggle="modal" data-bs-target="#editDonorForm">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger delete-donor" data-donor-id="<?php echo htmlspecialchars($donation['donor_id']); ?>" data-eligibility-id="<?php echo htmlspecialchars($donation['eligibility_id'] ?? ''); ?>" onclick="deleteDonor(<?php echo htmlspecialchars($donation['eligibility_id'] ?? '0'); ?>)">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo ($status === 'approved') ? '8' : (($status === 'pending') ? '6' : (($status === 'declined') ? '7' : '8')); ?>" class="text-center">No donation records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
            
                        <!-- Pagination -->
                        <nav>
                            <ul class="pagination justify-content-center mt-3">
                                <li class="page-item"><a class="page-link" href="#">&lt;</a></li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item"><a class="page-link" href="#">4</a></li>
                                <li class="page-item"><a class="page-link" href="#">5</a></li>
                                <li class="page-item"><a class="page-link" href="#">&gt;</a></li>
                            </ul>
                        </nav>
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
        // Wait for the DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing modals and buttons...');
            
            // Initialize modals
            let confirmationModal = null;
            let loadingModal = null;
            let viewDonorConfirmationModal = null;
            let processDonorConfirmationModal = null;
            let donorModal = null;
            
            // Get modal elements
            const confirmationModalEl = document.getElementById('confirmationModal');
            const loadingModalEl = document.getElementById('loadingModal');
            const viewDonorConfirmationModalEl = document.getElementById('viewDonorConfirmationModal');
            const processDonorConfirmationModalEl = document.getElementById('processDonorConfirmationModal');
            const donorModalEl = document.getElementById('donorModal');
            
            if (confirmationModalEl && loadingModalEl && viewDonorConfirmationModalEl && processDonorConfirmationModalEl && donorModalEl) {
                console.log('Modal elements found, initializing Bootstrap modals...');
                confirmationModal = new bootstrap.Modal(confirmationModalEl);
                loadingModal = new bootstrap.Modal(loadingModalEl);
                viewDonorConfirmationModal = new bootstrap.Modal(viewDonorConfirmationModalEl);
                processDonorConfirmationModal = new bootstrap.Modal(processDonorConfirmationModalEl);
                donorModal = new bootstrap.Modal(donorModalEl);
            } else {
                console.error('Some modal elements not found');
            }
            
            // Get button elements
            const addWalkInBtn = document.getElementById('addWalkInBtn');
            const proceedBtn = document.getElementById('proceedBtn');
            const viewDonorDetailsBtn = document.getElementById('viewDonorDetailsBtn');
            const processDonorBtn = document.getElementById('processDonorBtn');
            const confirmProcessDonorBtn = document.getElementById('confirmProcessDonorBtn');
            
            // Variables to store current donor info
            let currentDonorId = null;
            let currentEligibilityId = null;
            
            // Add Walk-in button click handler
            if (addWalkInBtn) {
                console.log('Add Walk-in button found, adding click handler...');
                addWalkInBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (confirmationModal) {
                        confirmationModal.show();
                    }
                });
            } else {
                console.error('Add Walk-in button not found');
            }
            
            // Proceed button click handler
            if (proceedBtn) {
                console.log('Proceed button found, adding click handler...');
                proceedBtn.addEventListener('click', function() {
                    if (confirmationModal && loadingModal) {
                        confirmationModal.hide();
                        loadingModal.show();
                        setTimeout(() => {
                            window.location.href = '../../src/views/forms/donor-form.php';
                        }, 1500);
                    }
                });
            } else {
                console.error('Proceed button not found');
            }
            
            // Make rows clickable
            document.querySelectorAll('.donor-row').forEach(row => {
                row.addEventListener('click', function() {
                    currentDonorId = this.getAttribute('data-donor-id');
                    currentEligibilityId = this.getAttribute('data-eligibility-id');
                    
                    if (viewDonorConfirmationModal) {
                        viewDonorConfirmationModal.show();
                    }
                });
            });
            
            // View donor details button click handler
            if (viewDonorDetailsBtn) {
                viewDonorDetailsBtn.addEventListener('click', function() {
                    if (viewDonorConfirmationModal && donorModal && currentDonorId && currentEligibilityId) {
                        viewDonorConfirmationModal.hide();
                        fetchDonorDetails(currentDonorId, currentEligibilityId);
                        donorModal.show();
                    }
                });
            }
            
            // Process donor button click handler
            if (processDonorBtn) {
                processDonorBtn.addEventListener('click', function() {
                    if (viewDonorConfirmationModal && processDonorConfirmationModal) {
                        viewDonorConfirmationModal.hide();
                        processDonorConfirmationModal.show();
                    }
                });
            }
            
            // Confirm process donor button click handler
            if (confirmProcessDonorBtn) {
                confirmProcessDonorBtn.addEventListener('click', function() {
                    if (!currentDonorId) {
                        console.error('No donor ID found for processing');
                        alert('Error: Unable to process donor. Donor ID is missing.');
                        return;
                    }
                    
                    if (processDonorConfirmationModal) {
                        processDonorConfirmationModal.hide();
                    }
                    
                    if (loadingModal) {
                        loadingModal.show();
                    }

                    console.log('Processing donor ID:', currentDonorId);
                    
                    // Store donor ID in session
                    fetch('set_donor_session.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            donor_id: currentDonorId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Session response:', data);
                        
                        // Redirect to medical history form regardless of eligibility creation
                        setTimeout(() => {
                            window.location.href = `../../src/views/forms/medical-history.php?donor_id=${currentDonorId}`;
                        }, 1000);
                    })
                    .catch(error => {
                        console.error('Error storing donor ID in session:', error);
                        // Redirect anyway as a fallback
                        setTimeout(() => {
                            window.location.href = `../../src/views/forms/medical-history.php?donor_id=${currentDonorId}`;
                        }, 1000);
                    });
                });
            }

            // View donor buttons (eye icon) click handler
            document.querySelectorAll('.view-donor').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent row click
                    currentDonorId = this.getAttribute('data-donor-id');
                    currentEligibilityId = this.getAttribute('data-eligibility-id');
                });
            });
            
            // NOTE: The View/Edit Donor Form and Process This Donor button handlers are now
            // added directly in the fetchDonorDetails function when the buttons are created
        });

        // Function to fetch donor details
        function fetchDonorDetails(donorId, eligibilityId) {
            console.log(`Fetching details for donor: ${donorId}, eligibility: ${eligibilityId}`);
            
            fetch(`donor_details_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
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
                    
                    // Format the details in the compact style from the image
                    let html = `
                    <div class="donor-details-container">
                        <div><strong>Surname:</strong> ${donor.surname || 'N/A'}</div>
                        <div><strong>Blood Type:</strong> ${eligibility.blood_type || 'Pending'}</div>
                        
                        <div><strong>First Name:</strong> ${donor.first_name || 'N/A'}</div>
                        <div><strong>Donation Type:</strong> ${eligibility.donation_type || 'Pending'}</div>
                        
                        <div><strong>Middle Name:</strong> ${donor.middle_name || 'N/A'}</div>
                        <div><strong>Status:</strong> ${eligibility.status || 'approved'}</div>
                        
                        <div><strong>Birthdate:</strong> ${donor.birthdate || 'N/A'}</div>
                        <div><strong>Donation Date:</strong> ${eligibility.start_date ? new Date(eligibility.start_date).toLocaleDateString() : '4/1/2025'}</div>
                        
                        <div><strong>Age:</strong> ${donor.age || 'N/A'}</div>
                        <div><strong>Eligibility End Date:</strong> ${eligibility.end_date ? new Date(eligibility.end_date).toLocaleDateString() : 'N/A'}</div>
                        
                        <div><strong>Sex:</strong> ${donor.sex || 'N/A'}</div>
                        <div><strong>Blood Bag Type:</strong> ${eligibility.blood_bag_type || 'Not specified'}</div>
                        
                        <div><strong>Civil Status:</strong> ${donor.civil_status || 'Single'}</div>
                        <div><strong>Amount Collected:</strong> ${eligibility.amount_collected || 'Not specified'}</div>
                        
                        <div><strong>Permanent Address:</strong> ${donor.permanent_address || 'a'}</div>
                        <div><strong>Donor Reaction:</strong> ${eligibility.donor_reaction || 'None'}</div>
                        
                        <div><strong>Office Address:</strong> ${donor.office_address || 'Not specified'}</div>
                        <div><strong>Management Done:</strong> ${eligibility.management_done || 'None'}</div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <button type="button" class="btn btn-primary" id="processThisDonorBtn" data-donor-id="${donor.donor_id}">
                            <i class="fas fa-list-check me-2"></i> Process This Donor
                        </button>
                        <button type="button" class="btn btn-success" id="viewEditDonorFormBtn" data-donor-id="${donor.donor_id}">
                            <i class="fas fa-edit me-2"></i> View/Edit Donor Form
                        </button>
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
                            padding: 4px 0;
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
                    document.getElementById('processThisDonorBtn').addEventListener('click', function() {
                        const donorId = this.getAttribute('data-donor-id');
                        
                        if (!donorId) {
                            console.error('No donor ID found for process button');
                            alert('Error: Donor ID not found. Please try again.');
                            return;
                        }
                        
                        console.log('Processing donor ID:', donorId);
                        
                        // Update current donor ID for the process modal
                        currentDonorId = donorId;
                        
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
                        fetch('set_donor_session.php', {
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
                })
                .catch(error => {
                    console.error('Error fetching donor details:', error);
                    document.getElementById('donorDetails').innerHTML = '<div class="alert alert-danger">Error loading donor details. Please try again.</div>';
                });
        }

        // Function to load edit form
        function loadEditForm(donorId, eligibilityId) {
            fetch(`donor_edit_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
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
                    let html = `<form id="updateEligibilityForm" method="post" action="update_eligibility.php">
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
                                    <label class="donor_form_label">Amount Collected</label>
                                    <input type="number" step="0.01" class="donor_form_input" name="amount_collected" value="${eligibility.amount_collected || ''}">
                                </div>
                                <div>
                                    <label class="donor_form_label">Collection Successful</label>
                                    <select class="donor_form_input" name="collection_successful">
                                        <option value="true" ${eligibility.collection_successful ? 'selected' : ''}>Yes</option>
                                        <option value="false" ${!eligibility.collection_successful ? 'selected' : ''}>No</option>
                                    </select>
                                </div>
                            </div>

                            <div class="donor_form_grid grid-1">
                                <div>
                                    <label class="donor_form_label">Donor Reaction</label>
                                    <textarea class="donor_form_input" name="donor_reaction" rows="2">${eligibility.donor_reaction || ''}</textarea>
                                </div>
                            </div>

                            <div class="donor_form_grid grid-1">
                                <div>
                                    <label class="donor_form_label">Management Done</label>
                                    <textarea class="donor_form_input" name="management_done" rows="2">${eligibility.management_done || ''}</textarea>
                                </div>
                            </div>

                            <div class="donor_form_grid grid-2">
                                <div>
                                    <label class="donor_form_label">Collection Start Time</label>
                                    <input type="datetime-local" class="donor_form_input" name="collection_start_time" value="${eligibility.collection_start_time ? new Date(eligibility.collection_start_time).toISOString().slice(0, 16) : ''}">
                                </div>
                                <div>
                                    <label class="donor_form_label">Collection End Time</label>
                                    <input type="datetime-local" class="donor_form_input" name="collection_end_time" value="${eligibility.collection_end_time ? new Date(eligibility.collection_end_time).toISOString().slice(0, 16) : ''}">
                                </div>
                            </div>

                            <div class="donor_form_grid grid-1">
                                <div>
                                    <label class="donor_form_label">Unit Serial Number</label>
                                    <input type="text" class="donor_form_input" name="unit_serial_number" value="${eligibility.unit_serial_number || ''}">
                                </div>
                            </div>

                            <div class="donor_form_grid grid-1">
                                <div>
                                    <label class="donor_form_label">Disapproval Reason</label>
                                    <textarea class="donor_form_input" name="disapproval_reason" rows="2">${eligibility.disapproval_reason || ''}</textarea>
                                </div>
                            </div>

                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">Save Changes</button>
                            </div>
                        </div>
                    </form>`;
                    
                    editFormContainer.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading edit form:', error);
                    document.getElementById('editDonorFormContent').innerHTML = '<div class="alert alert-danger">Error loading edit form. Please try again.</div>';
                });
        }

        // Function to delete donor record
        function deleteDonor(eligibilityId) {
            if (confirm("Are you sure you want to delete this donation record? This action cannot be undone.")) {
                fetch(`delete_donation.php?eligibility_id=${eligibilityId}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Donation record deleted successfully!");
                        location.reload(); // Refresh the page to see the changes
                    } else {
                        alert("Error deleting record: " + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert("An error occurred while deleting the record.");
                });
            }
        }

        // Event listeners for view and edit buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Edit donor details
            document.querySelectorAll('.edit-donor').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent row click
                    const donorId = this.getAttribute('data-donor-id');
                    const eligibilityId = this.getAttribute('data-eligibility-id');
                    loadEditForm(donorId, eligibilityId);
                });
            });

            // Sorting functionality
            const sortSelect = document.getElementById('sortSelect');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    const sortValue = this.value;
                    if (sortValue !== 'default') {
                        window.location.href = `?sort=${sortValue}`;
                    }
                });
            }

            // Search functionality
            function searchTable() {
                const searchInput = document.getElementById('searchInput').value.toLowerCase();
                const table = document.querySelector('table');
                const rows = table.getElementsByTagName('tr');

                for (let i = 1; i < rows.length; i++) { // Start from 1 to skip header
                    const row = rows[i];
                    const cells = row.getElementsByTagName('td');
                    let found = false;

                    for (let j = 0; j < cells.length; j++) {
                        const cellText = cells[j].textContent.toLowerCase();
                        if (cellText.includes(searchInput)) {
                            found = true;
                            break;
                        }
                    }

                    row.style.display = found ? '' : 'none';
                }
            }

            // Add event listener for real-time search
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', searchTable);
            }
        });
    </script>
</body>
</html>