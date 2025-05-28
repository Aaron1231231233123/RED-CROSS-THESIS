<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Check if the user is logged in and has admin role (role_id = 1)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    // Redirect to login page or show error
    header("Location: ../../public/login.php");
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
                $status_map = [
                    'Approved' => 'Accepted',  // Fix the status name!
                    'approved' => 'Accepted',
                    'Declined' => 'Declined',
                    'declined' => 'Declined',
                    'Pending' => 'Pending',
                    'pending' => 'Pending',
                    'Accepted' => 'Accepted',
                    'accepted' => 'Accepted',
                    'Delivering' => 'Delivering',
                    'delivering' => 'Delivering',
                    'Completed' => 'Completed',
                    'completed' => 'Completed'
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

// Fetch blood requests based on status from GET parameter
$status = isset($_GET['status']) ? strtolower($_GET['status']) : 'requests';

function fetchBloodRequestsByStatus($status) {
    if ($status === 'accepted') {
        $endpoint = "blood_requests?status=eq.Accepted&order=is_asap.desc,requested_on.desc";
    } elseif ($status === 'handedover') {
        $endpoint = "blood_requests?status=eq.Confirmed&order=is_asap.desc,requested_on.desc";
    } elseif ($status === 'declined') {
        $endpoint = "blood_requests?status=eq.Declined&order=is_asap.desc,requested_on.desc";
    } else { // 'requests' or any other value defaults to pending
        $endpoint = "blood_requests?status=eq.Pending&order=is_asap.desc,requested_on.desc";
    }
    $response = supabaseRequest($endpoint);
    if ($response['code'] >= 200 && $response['code'] < 300) {
        return $response['data'];
    } else {
        return [];
    }
}

$blood_requests = fetchBloodRequestsByStatus($status);

// Handle request acceptance
if (isset($_POST['accept_request'])) {
    $request_id = $_POST['request_id'];
    
    if (empty($request_id)) {
        $error_message = "Invalid request ID. Please try again.";
    } else {
        // First verify that the request exists and get all its data
        $verifyEndpoint = "blood_requests?request_id=eq.$request_id&select=*";
        $verifyResponse = supabaseRequest($verifyEndpoint);
        
        if ($verifyResponse['code'] >= 200 && $verifyResponse['code'] < 300) {
            if (empty($verifyResponse['data'])) {
                $error_message = "Request ID not found. Please try again.";
            } else {
                // Request exists, update status to 'Accepted'
                $updateEndpoint = "blood_requests?request_id=eq.$request_id";
                
                // Important: The status must match exactly what's in the enum
                $data = [
                    'status' => 'Accepted', // Must match the database enum values (Pending, Accepted, Declined) case-sensitive
                    'last_updated' => 'now' // The supabaseRequest function will format this correctly
                ];
                
                // Log the request for debugging
                error_log("Accepting request $request_id with status: Accepted (ID verified)");
                
                $response = supabaseRequest($updateEndpoint, 'PATCH', $data);
                
                if ($response['code'] >= 200 && $response['code'] < 300) {
                    // Success! Set a success message instead of redirecting
                    $success_message = "Request #$request_id has been successfully accepted. You can view it in the Handover section.";
                    // Refresh the list of blood requests
                    $blood_requests = fetchBloodRequestsByStatus($status);
                } else {
                    // Format a better error message for debugging
                    $error_message = "Failed to accept request. Error code: " . $response['code'];
                    if (isset($response['error'])) {
                        $error_message .= " - " . $response['error'];
                    }
                    error_log("Failed to accept request #$request_id: " . json_encode($response));
                }
            }
        } else {
            $error_message = "Could not verify request. Please try again. Error: ";
            if (isset($verifyResponse['error'])) {
                $error_message .= $verifyResponse['error'];
            }
        }
    }
}

// Handle request decline
if (isset($_POST['decline_request'])) {
    $request_id = $_POST['request_id'];
    $decline_reason = isset($_POST['decline_reason']) ? $_POST['decline_reason'] : '';
    
    if (empty($request_id)) {
        $error_message = "Invalid request ID. Please try again.";
    } else if (empty($decline_reason)) {
        $error_message = "Please select a reason for declining.";
    } else {
        // First verify that the request exists
        $verifyEndpoint = "blood_requests?request_id=eq.$request_id&select=*";
        $verifyResponse = supabaseRequest($verifyEndpoint);
        
        if ($verifyResponse['code'] >= 200 && $verifyResponse['code'] < 300) {
            if (empty($verifyResponse['data'])) {
                $error_message = "Request ID not found. Please try again.";
            } else {
                // Request exists, update its status to 'declined'
                $updateEndpoint = "blood_requests?request_id=eq.$request_id";
                
                $data = [
                    'status' => 'Declined',
                    'decline_reason' => $decline_reason,
                    'last_updated' => 'now' // The supabaseRequest function will format this correctly
                ];
                
                $response = supabaseRequest($updateEndpoint, 'PATCH', $data);
                
                if ($response['code'] >= 200 && $response['code'] < 300) {
                    // Success! Redirect to prevent form resubmission
                    header("Location: Dashboard-Inventory-System-Hospital-Request.php?decline_success=1");
                    exit();
                } else {
                    // Format a better error message for debugging
                    $error_message = "Failed to decline request. Error code: " . $response['code'];
                    if (isset($response['error'])) {
                        $error_message .= " - " . $response['error'];
                    }
                    error_log("Failed to decline request #$request_id: " . json_encode($response));
                }
            }
        } else {
            $error_message = "Could not verify request. Please try again. Error: ";
            if (isset($verifyResponse['error'])) {
                $error_message .= $verifyResponse['error'];
            }
        }
    }
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
.inventory-sidebar {
    height: 100vh;
    overflow-y: auto;
    position: fixed;
    width: 240px;
    background-color: #ffffff;
    border-right: 1px solid #ddd;
    padding: 15px;
    transition: width 0.3s ease;
}

.inventory-sidebar .nav-link {
    color: #333;
    padding: 10px 15px;
    margin: 2px 0;
    border-radius: 4px;
    transition: background-color 0.2s ease, color 0.2s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
}

.inventory-sidebar .nav-link i {
    margin-right: 10px;
    font-size: 0.9rem;
    width: 16px;
    text-align: center;
}

.inventory-sidebar .nav-link:hover {
    background-color: #f8f9fa;
    color: #dc3545;
}

.inventory-sidebar .nav-link.active {
    background-color: #dc3545;
    color: white;
}

.inventory-sidebar .collapse-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    background-color: transparent;
    border-radius: 4px;
}

.inventory-sidebar .collapse-menu .nav-link {
    padding: 8px 15px 8px 40px;
    font-size: 0.85rem;
    margin: 0;
    border-radius: 4px;
}

.inventory-sidebar .nav-link[aria-expanded="true"] {
    background-color: transparent;
    color: #333;
}

.inventory-sidebar .nav-link[aria-expanded="true"] i.fa-chevron-down {
    transform: rotate(180deg);
}

.inventory-sidebar i.fa-chevron-down {
    font-size: 0.8rem;
    transition: transform 0.2s ease;
}

.inventory-sidebar .form-control {
    font-size: 0.9rem;
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 10px;
}

/* Blood Donations Section */
#bloodDonationsCollapse {
    margin-top: 2px;
    border: none;
    background-color: transparent;
}

#bloodDonationsCollapse .nav-link {
    color: #333;
    padding: 8px 15px 8px 40px;
}

#bloodDonationsCollapse .nav-link:hover {
    background-color: #dc3545;
    color: white;
}

/* Updated styles for the search bar */
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
    .inventory-sidebar {
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
    .inventory-sidebar {
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
    .custom-alert {
        opacity: 0;
        transform: translateY(-20px);
        transition: opacity 0.5s ease-in-out, transform 0.5s ease-in-out;
    }

    .show-alert {
        opacity: 1;
        transform: translateY(0);
    }

    /* Sidebar Collapsible Styling */

    #bloodDonationsCollapse .nav-link:hover {
        background-color: #f8f9fa;
        color: #dc3545;
    }

    /* Add these modal styles */
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1040;
    }

    .modal {
        z-index: 1050;
    }

    /* Make sure the accept request modal appears on top */
    #acceptRequestModal {
        z-index: 1060;
    }

    /* Make sure the loading modal appears on top of everything */
    #loadingModal {
        z-index: 1070;
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
    <!-- Move modals to top level, right after body tag -->
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

    <!-- Add this modal for Accept Request confirmation -->
    <div class="modal fade" id="acceptRequestModal" tabindex="-1" aria-labelledby="acceptRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="acceptRequestModalLabel">Confirm Acceptance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to accept this blood request?</p>
                    <p>This will move the request to the Handed Over section and mark it as accepted.</p>
                    <input type="hidden" id="accept-request-id" name="request_id" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmAcceptBtn" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Confirm Acceptance
                    </button>
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
                    <i class="fas fa-plus me-2"></i>Register Donor
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block inventory-sidebar">
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
                    <div class="collapse" id="bloodDonationsCollapse">
                        <div class="collapse-menu">
                            <a href="dashboard-Inventory-System-list-of-donations.php?status=pending" class="nav-link">Pending</a>
                            <a href="dashboard-Inventory-System-list-of-donations.php?status=approved" class="nav-link">Approved</a>
                            <a href="dashboard-Inventory-System-list-of-donations.php?status=declined" class="nav-link">Declined</a>
                        </div>
                    </div>
                    <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                        <span><i class="fas fa-tint"></i>Blood Bank</span>
                    </a>
                    <a class="nav-link" data-bs-toggle="collapse" href="#hospitalRequestsCollapse" role="button" aria-expanded="false" aria-controls="hospitalRequestsCollapse">
                        <span><i class="fas fa-list"></i>Hospital Requests</span>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <div class="collapse<?php echo (!isset($status) || in_array($status, ['requests', 'accepted', 'handedover', 'declined'])) ? ' show' : ''; ?>" id="hospitalRequestsCollapse">
                        <div class="collapse-menu">
                            <a href="Dashboard-Inventory-System-Hospital-Request.php?status=requests" class="nav-link<?php echo (!isset($_GET['status']) || $_GET['status'] === 'requests') ? ' active' : ''; ?>">Requests</a>
                            <a href="Dashboard-Inventory-System-Handed-Over.php?status=accepted" class="nav-link<?php echo (isset($_GET['status']) && $_GET['status'] === 'accepted') ? ' active' : ''; ?>">Accepted</a>
                            <a href="Dashboard-Inventory-System-Handed-Over.php?status=handedover" class="nav-link<?php echo (isset($_GET['status']) && $_GET['status'] === 'handedover') ? ' active' : ''; ?>">Handed Over</a>
                            <a href="Dashboard-Inventory-System-Handed-Over.php?status=declined" class="nav-link<?php echo (isset($_GET['status']) && $_GET['status'] === 'declined') ? ' active' : ''; ?>">Declined</a>
                        </div>
                    </div>
                    <a href="../../assets/php_func/logout.php" class="nav-link">
                            <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                    </a>
                </ul>
            </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mb-5">
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <strong>Error:</strong> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                <strong>Success!</strong> <?php echo $success_message; ?>
                <a href="Dashboard-Inventory-System-Handed-Over.php" class="btn btn-sm btn-primary ms-2">View in Handover</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['decline_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                <strong>Success!</strong> The blood request has been declined.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="container-fluid p-3 email-container">
                <h2 class="text-left">
                    <?php
                        if ($status === 'accepted') {
                            echo 'Accepted Hospital Requests';
                        } elseif ($status === 'handedover') {
                            echo 'Handed Over Hospital Requests';
                        } elseif ($status === 'declined') {
                            echo 'Declined Hospital Requests';
                        } else {
                            echo 'Hospital Requests';
                        }
                    ?>
                </h2>
                
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
                                    <option value="date">Request Date</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                                <input type="text" 
                                    class="form-control" 
                                    id="searchInput" 
                                    placeholder="Search requests...">
                            </div>
                            <div id="searchInfo" class="mt-2 small text-muted"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Divider Line -->
                <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
            
                <!-- Blood Request Items -->
                <?php if (empty($blood_requests)): ?>
                    <div class="alert alert-info text-center">
                        <?php
                            if ($status === 'accepted') {
                                echo 'No accepted blood requests found.';
                            } elseif ($status === 'handedover') {
                                echo 'No handed over blood requests found.';
                            } elseif ($status === 'declined') {
                                echo 'No declined blood requests found.';
                            } else {
                                echo 'No hospital blood requests found.';
                            }
                        ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($blood_requests as $request): ?>
                        <?php 
                            $blood_type_display = $request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-');
                            $component_display = "Whole Blood";
                            $priority_display = $request['is_asap'] ? 'Urgent' : 'Routine';
                            $hospital_name = $request['hospital_admitted'] ? $request['hospital_admitted'] : 'Hospital';
                        ?>
                        <div class="email-item d-flex justify-content-between align-items-center" 
                             data-bs-toggle="modal" 
                             data-bs-target="#requestModal" 
                             onclick="loadRequestDetails(
                                '<?php echo htmlspecialchars($request['request_id']); ?>', 
                                '<?php echo htmlspecialchars($request['patient_name']); ?>', 
                                '<?php echo htmlspecialchars($blood_type_display); ?>', 
                                '<?php echo htmlspecialchars($component_display); ?>', 
                                '<?php echo htmlspecialchars($request['rh_factor']); ?>', 
                                '<?php echo htmlspecialchars($request['units_requested']); ?>', 
                                '<?php echo htmlspecialchars($request['patient_diagnosis']); ?>', 
                                '<?php echo htmlspecialchars($hospital_name); ?>', 
                                '<?php echo htmlspecialchars($request['physician_name']); ?>', 
                                '<?php echo htmlspecialchars($priority_display); ?>'
                            )">
                            <div>
                                <span class="email-header text-decoration-underline">
                                    <?php echo htmlspecialchars($hospital_name); ?> - <?php echo $request['is_asap'] ? 'Urgent' : 'Routine'; ?> Request
                                </span><br>
                                <span class="email-subtext">
                                    Blood Type: <?php echo htmlspecialchars($blood_type_display); ?> | 
                                    <?php echo htmlspecialchars($request['units_requested']); ?> Units | 
                                    <?php echo htmlspecialchars($component_display); ?>
                                </span>
                            </div>
                            <div class="d-flex align-items-center">
                                <form method="POST" class="me-2" onsubmit="event.stopPropagation();">
                                    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['request_id']); ?>">
                                    <button type="button" name="accept_request" class="btn btn-success btn-sm p-2" 
                                            onclick="event.stopPropagation();">
                                        Accept Request
                                    </button>
                                </form>
                                <i class="fas fa-chevron-right text-muted"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        
        <!-- Modal for Full Request Details -->
        <div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
            
            <div class="modal-dialog modal-lg">
                <div id="alertContainer"></div>
                <div class="modal-content">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Blood Request Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="requestDetailsForm">
                            <input type="hidden" id="modalRequestId" name="request_id">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label>Patient Name:</label>
                                    <input type="text" class="form-control" id="patientName" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label>Blood Type:</label>
                                    <input type="text" class="form-control" id="bloodType" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label>RH Factor:</label>
                                    <input type="text" class="form-control" id="rhFactor" readonly>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label>Component:</label>
                                    <input type="text" class="form-control" id="component" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label>Units Needed:</label>
                                    <input type="number" class="form-control" id="unitsNeeded" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label>Diagnosis:</label>
                                <input type="text" class="form-control" id="diagnosis" readonly>
                            </div>
        
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label>Hospital:</label>
                                    <input type="text" class="form-control" id="hospital" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label>Requesting Physician:</label>
                                    <input type="text" class="form-control" id="physician" readonly>
                                </div>
                            </div>
        
                            <div class="mb-3">
                                <label>Priority:</label>
                                <input type="text" class="form-control" id="priority" readonly>
                            </div>
        
                            <div class="mb-3">
                                <label for="responseSelect">Reason for Declining:</label>
                                <select id="responseSelect" name="decline_reason" class="form-control">
                                    <option value="">-- Select a Reason --</option>
                                    <option value="Low Blood Supply">Low Blood Supply</option>
                                    <option value="Ineligible Requestor">Ineligible Requestor</option>
                                    <option value="Medical Restrictions">Medical Restrictions</option>
                                    <option value="Pending Verification">Pending Verification</option>
                                    <option value="Duplicate Request">Duplicate Request</option>
                                    <option value="other">Other (Specify Below)</option>
                                </select>
                            </div>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" id="declineRequest">
                                    <i class="fas fa-times-circle"></i> Decline Request
                                </button>
                                <button type="button" class="btn btn-success" id="modalAcceptButton">
                                    <i class="fas fa-check-circle"></i> Accept Request
                                </button>
                            </div>
                        </form>
                    </div>
               
            </div>
            
</main>
            
        </div>
    </div>
    
    <div class="modal fade" id="confirmDeclineModal" tabindex="-1" aria-labelledby="confirmDeclineLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Decline</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modalBodyText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>

                    <button type="button" class="btn btn-danger" id="confirmDeclineBtn">Yes</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery first (if needed) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Consolidate all JavaScript into one script tag
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'), {
            backdrop: false,
            keyboard: false
        });
        const requestDetailsModal = new bootstrap.Modal(document.getElementById('requestModal'));
        const confirmDeclineModal = new bootstrap.Modal(document.getElementById('confirmDeclineModal'));
        const acceptRequestModal = new bootstrap.Modal(document.getElementById('acceptRequestModal'));

        // Initialize search functionality
        const searchInput = document.getElementById('searchInput');
        const searchCategory = document.getElementById('searchCategory');
        
        if (searchInput && searchCategory) {
            // Add event listeners for real-time search
            searchInput.addEventListener('keyup', searchRequests);
            searchCategory.addEventListener('change', searchRequests);
        }
        
        // Function to search blood requests
        function searchRequests() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const searchCategory = document.getElementById('searchCategory').value;
            const requestItems = document.querySelectorAll('.email-item');
            
            // Check if no results message already exists
            let noResultsMsg = document.getElementById('noResultsMsg');
            if (noResultsMsg) {
                noResultsMsg.remove();
            }
            
            let visibleItems = 0;
            const totalItems = requestItems.length;
            
            requestItems.forEach(item => {
                let shouldShow = false;
                if (searchInput.trim() === '') {
                    shouldShow = true;
                } else {
                    const itemText = item.textContent.toLowerCase();
                    
                    if (searchCategory === 'all') {
                        // Search all text in the item
                        shouldShow = itemText.includes(searchInput);
                    } else if (searchCategory === 'priority') {
                        // Search only priority (Urgent/Routine)
                        const priorityText = item.querySelector('.email-header').textContent.toLowerCase();
                        shouldShow = priorityText.includes(searchInput);
                    } else if (searchCategory === 'hospital') {
                        // Search hospital name
                        const hospitalName = item.querySelector('.email-header').textContent.toLowerCase();
                        shouldShow = hospitalName.includes(searchInput);
                    } else if (searchCategory === 'date') {
                        // For date, we'd need to check if it's available in the item
                        // Since it might not be directly visible, we do a general search
                        // Create a regex for flexible date matching
                        const datePattern = new RegExp(searchInput.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
                        shouldShow = datePattern.test(itemText);
                    }
                }
                
                item.style.display = shouldShow ? '' : 'none';
                if (shouldShow) visibleItems++;
            });
            
            // Show "No results" message if no matches
            if (visibleItems === 0 && totalItems > 0) {
                const container = document.querySelector('.email-container');
                
                noResultsMsg = document.createElement('div');
                noResultsMsg.id = 'noResultsMsg';
                noResultsMsg.className = 'alert alert-info m-3 text-center';
                noResultsMsg.innerHTML = `
                    No matching results found. 
                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="clearSearch()">
                        Clear Search
                    </button>
                `;
                container.appendChild(noResultsMsg);
            }
            
            // Update search results info
            updateSearchInfo(visibleItems, totalItems);
        }
        
        // Function to update search results info
        function updateSearchInfo(visibleItems, totalItems) {
            const searchContainer = document.querySelector('.search-container');
            let searchInfo = document.getElementById('searchInfo');
            
            if (!searchInfo) {
                searchInfo = document.createElement('div');
                searchInfo.id = 'searchInfo';
                searchInfo.classList.add('text-muted', 'mt-2', 'small');
                searchContainer.appendChild(searchInfo);
            }
            
            const searchInput = document.getElementById('searchInput').value.trim();
            if (searchInput === '') {
                searchInfo.textContent = '';
                return;
            }
            
            searchInfo.textContent = `Showing ${visibleItems} of ${totalItems} entries`;
        }
        
        // Function to clear search
        window.clearSearch = function() {
            document.getElementById('searchInput').value = '';
            document.getElementById('searchCategory').value = 'all';
            searchRequests();
        };

        // Function to show confirmation modal
        window.showConfirmationModal = function() {
            confirmationModal.show();
        };

        // Function to handle form submission
        window.proceedToDonorForm = function() {
            confirmationModal.hide();
            loadingModal.show();
            
            setTimeout(() => {
                window.location.href = '../../src/views/forms/donor-form-modal.php';
            }, 1500);
        };

        // Get elements for declining requests
        const declineButton = document.getElementById("declineRequest");
        const responseSelect = document.getElementById("responseSelect");
        const alertContainer = document.getElementById("alertContainer");
        const modalBodyText = document.getElementById("modalBodyText");
        const confirmDeclineBtn = document.getElementById("confirmDeclineBtn");
        const confirmAcceptBtn = document.getElementById("confirmAcceptBtn");
        const requestDetailsForm = document.getElementById("requestDetailsForm");

        // Function to show alert messages
        function showAlert(type, message) {
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show mt-2" role="alert">
                    <strong>${message}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;

            setTimeout(() => {
                let alertBox = alertContainer.querySelector(".alert");
                if (alertBox) {
                    alertBox.classList.remove("show");
                    setTimeout(() => alertBox.remove(), 500);
                }
            }, 5000);
        }

        // Handle decline button click
        if (declineButton) {
            declineButton.addEventListener("click", function (e) {
                e.preventDefault();
                let reason = responseSelect.value;

                // Show alert if no reason is selected
                if (!reason || reason === "") {
                    showAlert("danger", "⚠️ Please select a valid reason for declining.");
                    return;
                }

                // Show confirmation modal with selected reason
                modalBodyText.innerHTML = `Are you sure you want to decline this request for the following reason? <br><strong>("${reason}")</strong>`;
                confirmDeclineModal.show();
            });
        }

        // Handle confirm decline button click
        if (confirmDeclineBtn) {
            confirmDeclineBtn.addEventListener("click", function() {
                // Create a form to submit via POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'Dashboard-Inventory-System-Hospital-Request.php';
                
                // Add request_id field
                const requestIdInput = document.createElement('input');
                requestIdInput.type = 'hidden';
                requestIdInput.name = 'request_id';
                requestIdInput.value = document.getElementById('modalRequestId').value;
                form.appendChild(requestIdInput);
                
                // Add decline_request field
                const declineRequestInput = document.createElement('input');
                declineRequestInput.type = 'hidden';
                declineRequestInput.name = 'decline_request';
                declineRequestInput.value = '1';
                form.appendChild(declineRequestInput);
                
                // Add decline_reason field
                const declineReasonInput = document.createElement('input');
                declineReasonInput.type = 'hidden';
                declineReasonInput.name = 'decline_reason';
                declineReasonInput.value = responseSelect.value;
                form.appendChild(declineReasonInput);
                
                // Hide the confirmation modal
                confirmDeclineModal.hide();
                
                // Append form to body, submit it, then remove it
                document.body.appendChild(form);
                form.submit();
            });
        }

        // Function to load request details into modal
        window.loadRequestDetails = function(requestId, name, bloodType, component, rhFactor, units, diagnosis, hospital, physician, priority) {
            // Set request ID in the hidden field
            document.getElementById('modalRequestId').value = requestId;
            
            // Fill in the other fields
            document.getElementById('patientName').value = name;
            document.getElementById('bloodType').value = bloodType;
            document.getElementById('component').value = component;
            document.getElementById('rhFactor').value = rhFactor;
            document.getElementById('unitsNeeded').value = units;
            document.getElementById('diagnosis').value = diagnosis;
            document.getElementById('hospital').value = hospital;
            document.getElementById('physician').value = physician;
            document.getElementById('priority').value = priority;
            
            // Clear any previous decline reason
            document.getElementById('responseSelect').value = '';
            
            // Reset the Accept Request button's text if it was changed
            const acceptButton = document.getElementById('modalAcceptButton');
            if (acceptButton) {
                acceptButton.innerHTML = '<i class="fas fa-check-circle"></i> Accept Request';
                acceptButton.disabled = false;
            }
            
            // Set the request ID in the accept modal as well
            document.getElementById('accept-request-id').value = requestId;
        };

        // Add event listener for the modal's Accept Request button
        const modalAcceptButton = document.getElementById('modalAcceptButton');
        if (modalAcceptButton) {
            modalAcceptButton.addEventListener('click', function(e) {
                // Check if a decline reason is selected
                const declineReason = document.getElementById('responseSelect').value;
                if (declineReason && declineReason !== '') {
                    // Show an alert if a decline reason is selected
                    showAlert("warning", "You've selected a reason for declining. Please clear the reason if you want to accept this request.");
                    return;
                }
                
                // Get the request ID from the hidden field
                const requestId = document.getElementById('modalRequestId').value;
                
                // Set the request ID in the confirmation modal
                document.getElementById('accept-request-id').value = requestId;
                
                // Close the request details modal and show the confirmation modal
                requestDetailsModal.hide();
                acceptRequestModal.show();
            });
        }

        // Add event listeners for the main list Accept Request buttons
        const listAcceptButtons = document.querySelectorAll('.accept-request-btn');
        if (listAcceptButtons.length > 0) {
            listAcceptButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Prevent the click from bubbling up to the email-item
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Get request ID and row data
                    const requestId = this.getAttribute('data-request-id');
                    const row = this.closest('.email-item');
                    
                    // Manually trigger the click on the row to show the details modal first
                    row.click();
                });
            });
        }
        
        // Add event listener for the Confirm Acceptance button in the accept modal
        if (confirmAcceptBtn) {
            confirmAcceptBtn.addEventListener('click', function() {
                // Create a form to submit via POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'Dashboard-Inventory-System-Hospital-Request.php';
                
                // Add request_id field
                const requestIdInput = document.createElement('input');
                requestIdInput.type = 'hidden';
                requestIdInput.name = 'request_id';
                requestIdInput.value = document.getElementById('accept-request-id').value;
                form.appendChild(requestIdInput);
                
                // Add accept_request field
                const acceptRequestInput = document.createElement('input');
                acceptRequestInput.type = 'hidden';
                acceptRequestInput.name = 'accept_request';
                acceptRequestInput.value = '1';
                form.appendChild(acceptRequestInput);
                
                // Hide the confirmation modal
                acceptRequestModal.hide();
                
                // Show loading modal
                loadingModal.show();
                
                // Append form to body, submit it, then remove it
                document.body.appendChild(form);
                form.submit();
            });
        }
    });
    </script>
</body>
</html>