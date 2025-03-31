<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Function to fetch eligibility data from Supabase
function fetchEligibilityData() {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?select=eligibility_id,donor_id,blood_type,donation_type,collection_successful,donor_reaction,start_date,end_date,status,blood_collection_id&order=created_at.desc",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json",
            "Prefer: count=exact"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ["error" => "cURL Error #:" . $err];
    } else {
        return json_decode($response, true);
    }
}

// Function to fetch donor information by donor_id
function fetchDonorInfo($donorId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId . "&select=donor_id,surname,first_name,middle_name,birthdate,age,sex",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
}

// Function to get donor eligibility status
function getDonorEligibilityStatus($donorId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/rpc/get_eligibility_status",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(["p_donor_id" => $donorId])
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ["error" => "cURL Error #:" . $err];
    } else {
        return json_decode($response, true);
    }
}

// Fetch eligibility data
$eligibilityData = fetchEligibilityData();

// Check if there's an error in fetching data
$error = null;
if (isset($eligibilityData['error'])) {
    $error = $eligibilityData['error'];
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
                        <h2 class="card-title">List of Donations</h2>
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
                                        <th>Donor ID</th>
                                        <th>Surname</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Birthdate</th>
                                        <th>Age</th>
                                        <th>Sex</th>
                                        <th>Blood Type</th>
                                        <th>Donation Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$error && is_array($eligibilityData) && count($eligibilityData) > 0): ?>
                                        <?php foreach ($eligibilityData as $eligibility): ?>
                                            <?php 
                                            // Fetch donor information for each eligibility record
                                            $donorInfo = fetchDonorInfo($eligibility['donor_id']);
                                            $statusInfo = getDonorEligibilityStatus($eligibility['donor_id']);
                                            
                                            if (!$donorInfo) continue; // Skip if no donor info found
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($eligibility['donor_id']); ?></td>
                                                <td><?php echo htmlspecialchars($donorInfo['surname']); ?></td>
                                                <td><?php echo htmlspecialchars($donorInfo['first_name']); ?></td>
                                                <td><?php echo htmlspecialchars($donorInfo['middle_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($donorInfo['birthdate']); ?></td>
                                                <td><?php echo htmlspecialchars($donorInfo['age'] ?? calculateAge($donorInfo['birthdate'])); ?></td>
                                                <td><?php echo htmlspecialchars($donorInfo['sex']); ?></td>
                                                <td><?php echo htmlspecialchars($eligibility['blood_type']); ?></td>
                                                <td><?php echo htmlspecialchars($eligibility['donation_type']); ?></td>
                                                <td>
                                                    <?php if ($statusInfo && isset($statusInfo['is_eligible'])): ?>
                                                        <?php if ($statusInfo['is_eligible']): ?>
                                                            <span class="badge bg-success">Eligible</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Not Eligible</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if ($eligibility['status'] === 'eligible'): ?>
                                                            <span class="badge bg-success">Eligible</span>
                                                        <?php elseif ($eligibility['status'] === 'disapproved'): ?>
                                                            <span class="badge bg-danger">Disapproved</span>
                                                        <?php elseif ($eligibility['status'] === 'failed_collection'): ?>
                                                            <span class="badge bg-warning text-dark">Failed Collection</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Ineligible</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info view-donor" data-donor-id="<?php echo htmlspecialchars($eligibility['donor_id']); ?>" data-eligibility-id="<?php echo htmlspecialchars($eligibility['eligibility_id']); ?>" data-bs-toggle="modal" data-bs-target="#donorModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning edit-donor" data-donor-id="<?php echo htmlspecialchars($eligibility['donor_id']); ?>" data-eligibility-id="<?php echo htmlspecialchars($eligibility['eligibility_id']); ?>" data-bs-toggle="modal" data-bs-target="#editDonorForm">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger delete-donor" data-donor-id="<?php echo htmlspecialchars($eligibility['donor_id']); ?>" data-eligibility-id="<?php echo htmlspecialchars($eligibility['eligibility_id']); ?>" onclick="deleteDonor(<?php echo htmlspecialchars($eligibility['eligibility_id']); ?>)">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="11" class="text-center">No donation records found</td>
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
            
            // Get modal elements
            const confirmationModalEl = document.getElementById('confirmationModal');
            const loadingModalEl = document.getElementById('loadingModal');
            
            if (confirmationModalEl && loadingModalEl) {
                console.log('Modal elements found, initializing Bootstrap modals...');
                confirmationModal = new bootstrap.Modal(confirmationModalEl);
                loadingModal = new bootstrap.Modal(loadingModalEl);
            } else {
                console.error('Modal elements not found');
            }
            
            // Get button elements
            const addWalkInBtn = document.getElementById('addWalkInBtn');
            const proceedBtn = document.getElementById('proceedBtn');
            
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
            
            // Rest of your existing event listeners...
        });

        // Function to fetch donor details
        function fetchDonorDetails(donorId, eligibilityId) {
            fetch(`donor_details_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
                .then(response => response.json())
                .then(data => {
                    // Populate modal with donor details
                    const donorDetailsContainer = document.getElementById('donorDetails');
                    
                    if (data.error) {
                        donorDetailsContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    const donor = data.donor;
                    const eligibility = data.eligibility;
                    
                    let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <p class="fs-5"><strong>Surname:</strong> ${donor.surname}</p>
                            <p class="fs-5"><strong>First Name:</strong> ${donor.first_name}</p>
                            <p class="fs-5"><strong>Middle Name:</strong> ${donor.middle_name || ''}</p>
                            <p class="fs-5"><strong>Birthdate:</strong> ${donor.birthdate}</p>
                            <p class="fs-5"><strong>Age:</strong> ${donor.age}</p>
                            <p class="fs-5"><strong>Sex:</strong> ${donor.sex}</p>
                            <p class="fs-5"><strong>Civil Status:</strong> ${donor.civil_status || 'Not specified'}</p>
                            <p class="fs-5"><strong>Permanent Address:</strong> ${donor.permanent_address || 'Not specified'}</p>
                            <p class="fs-5"><strong>Office Address:</strong> ${donor.office_address || 'Not specified'}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="fs-5"><strong>Blood Type:</strong> ${eligibility.blood_type}</p>
                            <p class="fs-5"><strong>Donation Type:</strong> ${eligibility.donation_type}</p>
                            <p class="fs-5"><strong>Status:</strong> ${eligibility.status}</p>
                            <p class="fs-5"><strong>Donation Date:</strong> ${new Date(eligibility.start_date).toLocaleDateString()}</p>
                            <p class="fs-5"><strong>Eligibility End Date:</strong> ${eligibility.end_date ? new Date(eligibility.end_date).toLocaleDateString() : 'N/A'}</p>
                            <p class="fs-5"><strong>Blood Bag Type:</strong> ${eligibility.blood_bag_type || 'Not specified'}</p>
                            <p class="fs-5"><strong>Amount Collected:</strong> ${eligibility.amount_collected || 'Not specified'}</p>
                            <p class="fs-5"><strong>Donor Reaction:</strong> ${eligibility.donor_reaction || 'None'}</p>
                            <p class="fs-5"><strong>Management Done:</strong> ${eligibility.management_done || 'None'}</p>
                        </div>
                    </div>`;
                    
                    donorDetailsContainer.innerHTML = html;
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
            // View donor details
            document.querySelectorAll('.view-donor').forEach(button => {
                button.addEventListener('click', function() {
                    const donorId = this.getAttribute('data-donor-id');
                    const eligibilityId = this.getAttribute('data-eligibility-id');
                    fetchDonorDetails(donorId, eligibilityId);
                });
            });

            // Edit donor details
            document.querySelectorAll('.edit-donor').forEach(button => {
                button.addEventListener('click', function() {
                    const donorId = this.getAttribute('data-donor-id');
                    const eligibilityId = this.getAttribute('data-eligibility-id');
                    loadEditForm(donorId, eligibilityId);
                });
            });

            // Sorting functionality
            document.getElementById('sortSelect').addEventListener('change', function() {
                const sortValue = this.value;
                if (sortValue !== 'default') {
                    window.location.href = `?sort=${sortValue}`;
                }
            });

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
            document.getElementById('searchInput').addEventListener('keyup', searchTable);
        });
    </script>
</body>
</html>