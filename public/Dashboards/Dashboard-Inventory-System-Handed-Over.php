<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check for correct role (admin only)
$required_role = 1; // Admin role
if ($_SESSION['role_id'] !== $required_role) {
    header("Location: ../unauthorized.php");
    exit();
}

// Function to make API requests to Supabase
function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;

    $headers = [
        "Content-Type: application/json",
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Prefer: return=representation"
    ];

    // Debug log the endpoint and method
    error_log("supabaseRequest: $method $url");
    if ($data) {
        error_log("Request data: " . json_encode($data));
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only if needed for local development
    
    // Set the appropriate HTTP method
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            // CRITICAL FIX: Ensure enum values are using the correct values
            // This fixes the "invalid input value for enum request_status" error
            if (isset($data['status'])) {
                // Map status values to allowed enum values
                $status_map = [// Fix the status name!
                    'Declined' => 'Declined',
                    'Pending' => 'Pending',
                    'Accepted' => 'Accepted',
                ];
                
                // If the status is in our map, use the correct value
                if (array_key_exists($data['status'], $status_map)) {
                    $data['status'] = $status_map[$data['status']];
                    error_log("Status mapped to valid enum value: " . $data['status']);
                } else {
                    error_log("WARNING: Unknown status value: " . $data['status']);
                }
            }
            
            // Handle timestamp format for Supabase's PostgreSQL
            if (isset($data['last_updated'])) {
                // Format: "2023-05-30T15:30:45+00:00" (SQLite and PostgreSQL compatible)
                $data['last_updated'] = gmdate('Y-m-d\TH:i:s\+00:00');
            }
            
            // Convert data to JSON
            $json_data = json_encode($data);
            if ($json_data === false) {
                error_log("JSON encode error: " . json_last_error_msg());
                return [
                    'code' => 0,
                    'data' => null,
                    'error' => "JSON encode error: " . json_last_error_msg()
                ];
            }
            
            // Log request for debugging
            error_log("Supabase request to $url: $method with data: $json_data");
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        }
    }

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if ($response === false) {
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        
        error_log("cURL Error ($errno): $error in request to $url");
        return [
            'code' => 0,
            'data' => null,
            'error' => "Connection error: $error"
        ];
    }
    
    curl_close($ch);

    // Log response
    error_log("Supabase response from $url: Code $httpCode, Response: " . substr($response, 0, 500));
    
    // Handle the response
    if ($httpCode >= 200 && $httpCode < 300) {
        if (empty($response)) {
            return [
                'code' => $httpCode,
                'data' => []
            ];
        }
        
        $decoded = json_decode($response, true);
        if ($decoded === null && $response !== 'null' && $response !== '') {
            error_log("JSON decode error: " . json_last_error_msg() . " - Raw response: " . substr($response, 0, 500));
            return [
                'code' => $httpCode,
                'data' => null,
                'error' => "JSON decode error: " . json_last_error_msg()
            ];
        }
        
        return [
            'code' => $httpCode,
            'data' => $decoded
        ];
    } else {
        error_log("HTTP Error $httpCode: $response");
        return [
            'code' => $httpCode,
            'data' => null,
            'error' => "HTTP Error $httpCode: " . substr($response, 0, 500)
        ];
    }
}

// Fetch handover requests
function fetchHandoverRequests() {
    // Get requests with status Accepted OR Picked up OR Declined using explicit OR conditions
    $endpoint = "blood_requests?or=(status.eq.Accepted,status.eq.Confirmed,status.eq.Declined)&order=request_id.desc";
    $response = supabaseRequest($endpoint);
    
    // Debug log the response
    error_log("Handover requests response: " . json_encode($response));
    
    if ($response['code'] >= 200 && $response['code'] < 300) {
        return $response['data'];
    } else {
        error_log("Failed to fetch handover requests. Status code: " . $response['code']);
        return [];
    }
}

// Handle status updates
$success_message = '';
$error_message = '';

// Check if redirected after accepting a request
if (isset($_GET['accepted'])) {
    $accepted_id = $_GET['accepted'];
    
    // Fetch the details of the accepted request to verify it was updated correctly
    $verifyEndpoint = "blood_requests?request_id=eq.$accepted_id&select=*";
    $verifyResponse = supabaseRequest($verifyEndpoint);
    
    if ($verifyResponse['code'] >= 200 && $verifyResponse['code'] < 300 && !empty($verifyResponse['data'])) {
        $request = $verifyResponse['data'][0];
        $status = isset($request['status']) ? $request['status'] : 'Unknown';
        
        $success_message = "Request #$accepted_id has been accepted and moved to handover successfully. Status: $status";
        error_log("Successfully accepted request #$accepted_id with status: $status");
    } else {
        $success_message = "Request #$accepted_id has been accepted and moved to handover.";
        error_log("Verification incomplete for request #$accepted_id. Code: " . $verifyResponse['code']);
    }
}

// Update to delivering status
if (isset($_POST['update_delivering'])) {
    $request_id = $_POST['request_id'];
    
    // Update the request status to 'Picked up'
    $endpoint = "blood_requests?request_id=eq.".$request_id;
    
    $data = [
        'status' => 'Confirmed',
        'last_updated' => 'now'
    ];
    
    $response = supabaseRequest($endpoint, 'PATCH', $data);
    
    if ($response['code'] >= 200 && $response['code'] < 300) {
        $success_message = "Request #$request_id has been updated to Confirmed status.";
    } else {
        $error_message = "Failed to update request status. Error code: " . $response['code'];
        if (isset($response['error'])) {
            $error_message .= " - " . $response['error'];
        }
        error_log("Failed to update request #$request_id status: " . json_encode($response));
    }
}

// Update to completed status
if (isset($_POST['update_completed'])) {
    $request_id = $_POST['request_id'];
    
    // Update the request status to 'Declined' since Completed is not a valid enum
    $endpoint = "blood_requests?request_id=eq.".$request_id;
    
    $data = [
        'status' => 'Declined', // Changed from Completed to Declined
        'decline_reason' => 'Other', // Using Other from our enum instead of Completed
        'last_updated' => 'now' // The supabaseRequest function will format this correctly
    ];
    
    $response = supabaseRequest($endpoint, 'PATCH', $data);
    
    if ($response['code'] >= 200 && $response['code'] < 300) {
        $success_message = "Request #$request_id has been marked as Completed.";
    } else {
        $error_message = "Failed to update request to Completed. Error code: " . $response['code'];
        if (isset($response['error'])) {
            $error_message .= " - " . $response['error'];
        }
        error_log("Failed to update request #$request_id to Completed: " . json_encode($response));
    }
}

// Fetch blood requests
$handover_requests = fetchHandoverRequests();
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
    font-weight: 600;
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
    margin-top: 80px;
}

        .donor_form_container {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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

        .grid-1 {
            grid-template-columns: 1fr;
        }
        .grid-6 {
    grid-template-columns: repeat(6, 1fr); 
}
.email-container {
    margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin-top: 100px;
        }

        .email-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            transition: background 0.3s;
        }

        .email-item:hover {
            background: #f1f1f1;
        }

        .email-header {
            position: left;
            font-weight: bold;
            color: #000000;
        }

        .email-subtext {
            font-size: 14px;
            color: gray;
        }

        .modal-header {
            background: #000000;;
            color: white;
        }

        .modal-body label {
            font-weight: bold;
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
    z-index: 1040;
}

.modal {
    z-index: 1050;
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

    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <button class="btn btn-danger" onclick="showConfirmationModal()">
                    <i class="fas fa-plus me-2"></i>Add Walk-in Donor
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
            <div class="position-sticky">
                    <div class="d-flex align-items-center ps-1 mb-4 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" style="width: 65px; height: 65px; object-fit: contain;">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                <a href="dashboard-Inventory-System.php" class="nav-link">
                    
                    <span><i class="fas fa-home me-2"></i>Home</span>
                </a>
                
                <a class="nav-link" data-bs-toggle="collapse" href="#bloodDonationsCollapse" role="button" aria-expanded="false" aria-controls="bloodDonationsCollapse">
                    <span><i class="fas fa-tint me-2"></i>Blood Donations</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="bloodDonationsCollapse">
                    <div class="collapse-menu">
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=pending" class="nav-link">Pending</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=approved" class="nav-link">Approved</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=declined" class="nav-link">Declined</a>
                    </div>
                </div>

                <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                    <span><i class="fas fa-tint me-2"></i>Blood Bank</span>
                </a>
                <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link">
                    <span><i class="fas fa-list me-2"></i>Requests</span>
                </a>
                <a href="Dashboard-Inventory-System-Handed-Over.php" class="nav-link active">
                    <span><i class="fas fa-check me-2"></i>Handover</span>
                </a>
                <a href="../../assets/php_func/logout.php" class="nav-link">
                    <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                </a>
            </div>
           </nav>
        </div>
           <!-- Main Content -->
           <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="container-fluid p-3 email-container">
                    <h2 class="text-left">List of Handed Over Requests</h2>
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="search-container">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <select class="form-select category-select" id="searchCategory" style="max-width: 150px;">
                                        <option value="all">All Fields</option>
                                        <option value="hospital">Hospital</option>
                                        <option value="blood_type">Blood Type</option>
                                        <option value="date">Date</option>
                                    </select>
                                    <input type="text" 
                                        class="form-control" 
                                        id="searchInput" 
                                        placeholder="Search handovers...">
                                </div>
                            </div>
                        </div>
                    </div>
                     <!-- Divider Line -->
                <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Request ID</th>
                                    <th>Patient Name</th>
                                    <th>Blood Type</th>
                                    <th>Units</th>
                                    <th>Component</th>
                                    <th>Hospital</th>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                    <th>Reason (if Declined)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($handover_requests)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No handover requests found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($handover_requests as $request): ?>
                                    <tr>
                                        <td><?php echo $request['request_id']; ?></td>
                                        <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['patient_blood_type']) . (strtolower($request['rh_factor']) == 'positive' ? '+' : '-'); ?></td>
                                        <td><?php echo $request['units_requested']; ?></td>
                                        <td><?php echo htmlspecialchars($request['component'] ?? 'Whole Blood'); ?></td>
                                        <td><?php echo htmlspecialchars($request['hospital_admitted']); ?></td>
                                        <td><?php echo htmlspecialchars($request['physician_name']); ?></td>
                                        <td>
                                            <?php 
                                            $status = isset($request['status']) ? $request['status'] : '';
                                            
                                            if ($status === 'Confirmed'): ?>
                                                <span class="badge bg-success">Confirmed</span>
                                            <?php elseif ($status === 'Accepted'): ?>
                                                <span class="badge bg-warning">Accepted</span>
                                            <?php elseif ($status === 'Declined'): ?>
                                                <span class="badge bg-danger">Declined</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo isset($request['decline_reason']) ? htmlspecialchars($request['decline_reason']) : '-'; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info view-details" data-request='<?php echo json_encode($request); ?>' title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php 
                                            // Show appropriate action buttons based on status
                                            if ($status === 'Accepted'): 
                                            ?>
                                            <button type="button" class="btn btn-sm btn-primary update-status" data-request-id="<?php echo $request['request_id']; ?>" title="Update to Delivering">
                                                <i class="fas fa-truck"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
            
            <!-- Request Details Modal -->
            <div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title" id="requestDetailsModalLabel">Request Details</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Request ID:</strong> <span id="detail-request-id"></span></p>
                                    <p><strong>Patient Name:</strong> <span id="detail-patient-name"></span></p>
                                    <p><strong>Patient Age:</strong> <span id="detail-patient-age"></span></p>
                                    <p><strong>Patient Gender:</strong> <span id="detail-patient-gender"></span></p>
                                    <p><strong>Blood Type:</strong> <span id="detail-blood-type"></span></p>
                                    <p><strong>Component:</strong> <span id="detail-component"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Units Requested:</strong> <span id="detail-units"></span></p>
                                    <p><strong>Urgent:</strong> <span id="detail-urgent"></span></p>
                                    <p><strong>Hospital:</strong> <span id="detail-hospital"></span></p>
                                    <p><strong>Doctor:</strong> <span id="detail-doctor"></span></p>
                                    <p><strong>Requested On:</strong> <span id="detail-requested-on"></span></p>
                                    <p><strong>Status:</strong> <span id="detail-status"></span></p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6>Diagnosis/Reason for Request:</h6>
                                    <p id="detail-diagnosis" class="border p-2 rounded bg-light"></p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Update Status Modal -->
            <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title" id="updateStatusModalLabel">Update Request Status</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <p>Are you sure you want to update this request to <strong>Delivering</strong> status?</p>
                                <p>This indicates that the blood units are currently being transported to the requesting hospital.</p>
                                <input type="hidden" id="update-request-id" name="request_id">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_delivering" class="btn btn-primary">Update to Delivering</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Complete Request Modal -->
            <div class="modal fade" id="completeRequestModal" tabindex="-1" aria-labelledby="completeRequestModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title" id="completeRequestModalLabel">Complete Request</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <p>Are you sure you want to mark this request as <strong>Completed</strong>?</p>
                                <p>This indicates that the blood units have been successfully delivered to the requesting hospital.</p>
                                <input type="hidden" id="complete-request-id" name="request_id">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_completed" class="btn btn-success">Mark as Completed</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function searchTable() {
        const searchInput = document.getElementById('searchInput').value.toLowerCase();
        const searchCategory = document.getElementById('searchCategory').value;
        const table = document.querySelector('table');
        const rows = table.getElementsByTagName('tr');

        for (let i = 1; i < rows.length; i++) { // Start from 1 to skip header
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            let found = false;

            // If no cells (like in "No requests found" row), skip
            if (cells.length <= 1) continue;

            if (searchCategory === 'all') {
                // Search all columns
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent.toLowerCase();
                    if (cellText.includes(searchInput)) {
                        found = true;
                        break;
                    }
                }
            } else if (searchCategory === 'hospital') {
                // Search hospital column (5)
                const cellText = cells[5].textContent.toLowerCase();
                found = cellText.includes(searchInput);
            } else if (searchCategory === 'blood_type') {
                // Search blood type column (2)
                const cellText = cells[2].textContent.toLowerCase();
                found = cellText.includes(searchInput);
            } else if (searchCategory === 'date') {
                // For date, we would need to display and search on a date column
                // Since we don't have it visible in the table, we'll search all columns
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent.toLowerCase();
                    if (cellText.includes(searchInput)) {
                        found = true;
                        break;
                    }
                }
            }

            row.style.display = found ? '' : 'none';
        }
    }

    // Add event listener for real-time search
    document.getElementById('searchInput').addEventListener('keyup', searchTable);
    document.getElementById('searchCategory').addEventListener('change', searchTable);

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'), {
            backdrop: false,
            keyboard: false
        });
        const requestDetailsModal = new bootstrap.Modal(document.getElementById('requestDetailsModal'));
        const updateStatusModal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
        const completeRequestModal = new bootstrap.Modal(document.getElementById('completeRequestModal'));

        // Function to show confirmation modal
        window.showConfirmationModal = function() {
            confirmationModal.show();
        };

        // Function to handle form submission
        window.proceedToDonorForm = function() {
            confirmationModal.hide();
            loadingModal.show();
            
            setTimeout(() => {
                window.location.href = '../../src/views/forms/donor-form.php';
            }, 1500);
        };

        // Handle view details button clicks
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', function() {
                const requestData = JSON.parse(this.getAttribute('data-request'));
                
                // Populate the modal with request details
                document.getElementById('detail-request-id').textContent = '#' + requestData.request_id;
                document.getElementById('detail-patient-name').textContent = requestData.patient_name;
                document.getElementById('detail-patient-age').textContent = requestData.patient_age || 'N/A';
                document.getElementById('detail-patient-gender').textContent = requestData.patient_gender || 'N/A';
                document.getElementById('detail-blood-type').textContent = requestData.patient_blood_type + 
                    (requestData.rh_factor && requestData.rh_factor.toLowerCase() === 'positive' ? '+' : '-');
                document.getElementById('detail-component').textContent = requestData.component || 'Whole Blood';
                document.getElementById('detail-units').textContent = requestData.units_requested;
                document.getElementById('detail-urgent').textContent = requestData.is_asap ? 'Yes (ASAP)' : 'No';
                document.getElementById('detail-hospital').textContent = requestData.hospital_admitted;
                document.getElementById('detail-doctor').textContent = requestData.physician_name;
                
                // Format date if it exists
                if (requestData.requested_on) {
                    const requestDate = new Date(requestData.requested_on);
                    document.getElementById('detail-requested-on').textContent = requestDate.toLocaleString();
                } else {
                    document.getElementById('detail-requested-on').textContent = 'N/A';
                }
                
                // Set status with appropriate styling
                let statusHTML = '';
                const statusValue = requestData.status || '';
                
                if (statusValue === 'Accepted') {
                    statusHTML = '<span class="badge bg-success">Accepted</span>';
                } else if (statusValue === 'Declined' || statusValue === 'declined') {
                    statusHTML = '<span class="badge bg-danger">Declined</span>';
                } else if (!statusValue) {
                    statusHTML = '<span class="badge bg-secondary">Not Set</span>';
                } else {
                    statusHTML = `<span class="badge bg-secondary">${statusValue}</span>`;
                }
                
                document.getElementById('detail-status').innerHTML = statusHTML;
                
                // Set diagnosis
                document.getElementById('detail-diagnosis').textContent = requestData.patient_diagnosis || 'No diagnosis provided';
                
                // Show the modal
                requestDetailsModal.show();
            });
        });
        
        // Handle update status button clicks
        document.querySelectorAll('.update-status').forEach(button => {
            button.addEventListener('click', function() {
                const requestId = this.getAttribute('data-request-id');
                document.getElementById('update-request-id').value = requestId;
                updateStatusModal.show();
            });
        });
        
        // Handle complete request button clicks
        document.querySelectorAll('.update-completed').forEach(button => {
            button.addEventListener('click', function() {
                const requestId = this.getAttribute('data-request-id');
                document.getElementById('complete-request-id').value = requestId;
                completeRequestModal.show();
            });
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });
    </script>
</body>
</html>