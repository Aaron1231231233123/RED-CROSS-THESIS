<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Check if the user is logged in and has admin role (role_id = 1)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    // Redirect to login page or show error
    header("Location: ../../public/login.php");
    exit();
}

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/module/optimized_functions.php';

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// Fetch blood requests based on status from GET parameter
$status = isset($_GET['status']) ? strtolower($_GET['status']) : 'requests';

function fetchBloodRequestsByStatus($status) {
    if ($status === 'accepted') {
        $endpoint = "blood_requests?or=(status.eq.Accepted,status.eq.Printed)&order=is_asap.desc,requested_on.desc";
    } elseif ($status === 'handedover') {
        $endpoint = "blood_requests?status=eq.Confirmed&order=is_asap.desc,requested_on.desc";
    } elseif ($status === 'declined') {
        $endpoint = "blood_requests?status=eq.Declined&order=is_asap.desc,requested_on.desc";
    } else { // 'requests' or any other value defaults to pending + rescheduled + printed
        $endpoint = "blood_requests?or=(status.eq.Pending,status.eq.Rescheduled,status.eq.Printed)&order=is_asap.desc,requested_on.desc";
    }
    
    // OPTIMIZATION: Use enhanced API function with retry mechanism
    $response = supabaseRequest($endpoint);
    if (isset($response['data'])) {
        return $response['data'];
    } else {
        error_log("Error fetching blood requests: " . ($response['error'] ?? 'Unknown error'));
        return [];
    }
}

$blood_requests = fetchBloodRequestsByStatus($status);

// Helper function to get compatible blood types based on recipient's blood type
function getCompatibleBloodTypes($blood_type, $rh_factor) {
    $is_positive = $rh_factor === 'Positive';
    $compatible_types = [];
    switch ($blood_type) {
        case 'O':
            if ($is_positive) {
                $compatible_types = [
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            } else {
                $compatible_types = [
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
        case 'A':
            if ($is_positive) {
                $compatible_types = [
                    ['type' => 'A', 'rh' => 'Positive', 'priority' => 4],
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            } else {
                $compatible_types = [
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
        case 'B':
            if ($is_positive) {
                $compatible_types = [
                    ['type' => 'B', 'rh' => 'Positive', 'priority' => 4],
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            } else {
                $compatible_types = [
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
        case 'AB':
            if ($is_positive) {
                $compatible_types = [
                    ['type' => 'AB', 'rh' => 'Positive', 'priority' => 8],
                    ['type' => 'AB', 'rh' => 'Negative', 'priority' => 7],
                    ['type' => 'A', 'rh' => 'Positive', 'priority' => 6],
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 5],
                    ['type' => 'B', 'rh' => 'Positive', 'priority' => 4],
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            } else {
                $compatible_types = [
                    ['type' => 'AB', 'rh' => 'Negative', 'priority' => 4],
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
    }
    usort($compatible_types, function($a, $b) {
        return $a['priority'] - $b['priority'];
    });
    return $compatible_types;
}

// OPTIMIZATION: Enhanced function to check if a blood request can be fulfilled
function canFulfillBloodRequest($request_id) {
    // OPTIMIZATION: Use enhanced API function for request data
    $requestResponse = supabaseRequest("blood_requests?request_id=eq." . $request_id);
    
    if (!isset($requestResponse['data']) || empty($requestResponse['data'])) {
        return [false, 'Request not found.'];
    }
    
    $request_data = $requestResponse['data'][0];
    $requested_blood_type = $request_data['patient_blood_type'];
    $requested_rh_factor = $request_data['rh_factor'];
    $units_requested = intval($request_data['units_requested']);
    $blood_type_full = $requested_blood_type . ($requested_rh_factor === 'Positive' ? '+' : '-');
    
    // OPTIMIZATION: Use enhanced querySQL function for eligibility data
    $eligibilityData = querySQL(
        'eligibility',
        'eligibility_id,donor_id,blood_type,donation_type,blood_bag_type,collection_successful,unit_serial_number,collection_start_time,start_date,end_date,status,blood_collection_id',
        ['collection_successful' => 'eq.true']
    );
    
    if (isset($eligibilityData['error'])) {
        return [false, 'Error checking inventory: ' . $eligibilityData['error']];
    }
    
    // Get compatible blood types
    $compatible_types = getCompatibleBloodTypes($requested_blood_type, $requested_rh_factor);
    
    // Check available units for each compatible type
    $available_units = 0;
    $available_by_type = [];
    
    foreach ($eligibilityData as $item) {
        if (empty($item['unit_serial_number'])) continue;
        
        $item_blood_type = $item['blood_type'];
        $item_rh = strpos($item_blood_type, '+') !== false ? 'Positive' : 'Negative';
        $item_type = str_replace(['+', '-'], '', $item_blood_type);
        
        // Check if this blood type is compatible
        foreach ($compatible_types as $compatible) {
            if ($compatible['type'] === $item_type && $compatible['rh'] === $item_rh) {
                // Check if not expired
                $collection_date = new DateTime($item['collection_start_time']);
                $expiration_date = clone $collection_date;
                $expiration_date->modify('+35 days');
                
                if (new DateTime() <= $expiration_date) {
                    $available_units++;
                    if (!isset($available_by_type[$item_blood_type])) {
                        $available_by_type[$item_blood_type] = 0;
                    }
                    $available_by_type[$item_blood_type]++;
                }
                break;
            }
        }
    }
    
    $can_fulfill = $available_units >= $units_requested;
    $message = $can_fulfill 
        ? "Available: $available_units units (Requested: $units_requested)"
        : "Insufficient: $available_units available, $units_requested requested";
    
    return [$can_fulfill, $message, $available_by_type];
}

// OPTIMIZATION: Performance logging and caching
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
addPerformanceHeaders($executionTime, count($blood_requests), "Hospital Requests Module - Status: {$status}");

// Get default sorting
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

    <?php if (!empty($modal_error_message)): ?>
    <!-- Insufficient Inventory Modal -->
    <div class="modal fade show" id="insufficientInventoryModal" tabindex="-1" aria-labelledby="insufficientInventoryModalLabel" aria-modal="true" style="display: block; background: rgba(0,0,0,0.5);">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="insufficientInventoryModalLabel">Insufficient Blood Inventory</h5>
            <button type="button" class="btn-close btn-close-white" onclick="window.location.href=window.location.href;"></button>
          </div>
          <div class="modal-body">
            <?php echo nl2br(htmlspecialchars($modal_error_message)); ?>
            <input type="hidden" id="insufficientRequestId" value="<?php echo htmlspecialchars($_POST['request_id'] ?? '', ENT_QUOTES); ?>">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="rescheduleRequestBtn">Reschedule</button>
          </div>
        </div>
      </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      var modal = new bootstrap.Modal(document.getElementById('insufficientInventoryModal'));
      modal.show();

      // Attach reschedule logic
      document.getElementById('rescheduleRequestBtn').onclick = function() {
        var requestId = document.getElementById('insufficientRequestId') ? document.getElementById('insufficientRequestId').value : null;
        if (!requestId) {
          alert('Request ID not found.');
          return;
        }
        // Send AJAX request to reschedule
        fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'reschedule_request=1&request_id=' + encodeURIComponent(requestId)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Request has been rescheduled for 3 days later.');
            window.location.reload();
          } else {
            alert('Failed to reschedule: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(err => {
          alert('Error: ' + err);
        });
      };
    });
    </script>
    <?php endif; ?>

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
                            <a href="Dashboard-Inventory-System-Handed-Over.php?status=accepted" class="nav-link<?php echo (isset($_GET['status']) && $_GET['status'] === 'accepted') ? ' active' : ''; ?>">Approved</a>
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
            
            <?php if (isset($_GET['handover_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                <strong>Success!</strong> The blood request has been marked as completed (handed over).
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
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" id="requestTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Hospital</th>
                                <th>Request Date</th>
                                <th>Required Date</th>
                                <th>Time Sent</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($blood_requests as $request): ?>
                            <?php 
                                $hospital_name = $request['hospital_admitted'] ? $request['hospital_admitted'] : 'Hospital';
                                $request_date = $request['requested_on'] ? date('Y-m-d', strtotime($request['requested_on'])) : '-';
                                $required_date = $request['when_needed'] ? date('Y-m-d', strtotime($request['when_needed'])) : '-';
                                $time_sent = $request['when_needed'] ? strtoupper(date('h:i a', strtotime($request['when_needed']))) : '-';
                                $blood_type_display = $request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-');
                                $component_display = 'Whole Blood';
                                $priority_display = $request['is_asap'] ? 'Urgent' : 'Routine';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($hospital_name); ?></td>
                                <td><?php echo htmlspecialchars($request_date); ?></td>
                                <td><?php echo htmlspecialchars($required_date); ?></td>
                                <td><?php echo htmlspecialchars($time_sent); ?></td>
                                <td>
                                    <?php
                                    $status_val = isset($request['status']) ? strtolower($request['status']) : '';
                                    if ($status_val === 'pending') {
                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                    } elseif ($status_val === 'rescheduled') {
                                        echo '<span class="badge bg-info text-dark">Rescheduled</span>';
                                    } elseif ($status_val === 'printed') {
                                        echo '<span class="badge bg-info text-dark">Printed</span>';
                                    } elseif ($status_val === 'accepted') {
                                        echo '<span class="badge bg-primary">Approved</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button 
                                        class="btn btn-info btn-sm view-btn"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#requestModal"
                                        title="View Details"
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
                                            '<?php echo htmlspecialchars($priority_display); ?>',
                                            '<?php echo htmlspecialchars($request['status']); ?>'
                                        )">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" id="declineRequest">
                                    <i class="fas fa-times-circle"></i> Decline Request
                                </button>
                                <button type="button" class="btn btn-success" id="modalAcceptButton">
                                    <i class="fas fa-check-circle"></i> Accept Request
                                </button>
                                <button type="button" class="btn btn-primary" id="handOverButton" style="display: none;">
                                    <i class="fas fa-truck"></i> Hand Over
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
    
    <!-- Decline Request Modal -->
    <div class="modal fade" id="declineRequestModal" tabindex="-1" aria-labelledby="declineRequestModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Reason for Declining</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="declineRequestForm" method="post">
            <div class="modal-body">
              <input type="hidden" name="request_id" id="declineRequestId">
              <input type="hidden" name="decline_request" value="1">
              <select class="form-select" name="decline_reason" id="declineReasonSelect" required>
                <option value="" selected disabled>Select a reason</option>
                <option value="Ineligible Requestor">Ineligible Requestor</option>
                <option value="Medical Restrictions">Medical Restrictions</option>
                <option value="Pending Verification">Pending Verification</option>
                <option value="Duplicate Request">Duplicate Request</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-danger">Confirm</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <!-- Decline Confirmation Modal -->
    <div class="modal fade" id="declineFinalConfirmModal" tabindex="-1" aria-labelledby="declineFinalConfirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="declineFinalConfirmModalLabel">Confirm Decline</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p id="declineFinalConfirmText"></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" id="declineFinalConfirmBtn" class="btn btn-danger">Yes, Decline</button>
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
    // Add this function to populate the modal fields (must be global for inline onclick)
    function loadRequestDetails(request_id, patientName, bloodType, component, rhFactor, unitsNeeded, diagnosis, hospital, physician, priority, status) {
        console.log('loadRequestDetails called with:', arguments);
        document.getElementById('modalRequestId').value = request_id;
        document.getElementById('patientName').value = patientName;
        document.getElementById('bloodType').value = bloodType;
        document.getElementById('component').value = component;
        document.getElementById('rhFactor').value = rhFactor;
        document.getElementById('unitsNeeded').value = unitsNeeded;
        document.getElementById('diagnosis').value = diagnosis;
        document.getElementById('hospital').value = hospital;
        document.getElementById('physician').value = physician;
        document.getElementById('priority').value = priority;
        
        // Handle button visibility based on status
        const acceptButton = document.getElementById('modalAcceptButton');
        const handOverButton = document.getElementById('handOverButton');
        
        if (acceptButton && handOverButton) {
            // Show Accept button for Pending and Rescheduled statuses
            if (['Pending', 'Rescheduled'].includes(status)) {
                acceptButton.style.display = 'inline-block';
                handOverButton.style.display = 'none';
            }
            // Show Hand Over button only for Printed status
            else if (status === 'Printed') {
                acceptButton.style.display = 'none';
                handOverButton.style.display = 'inline-block';
            }
            // Hide both buttons for Accepted status (Approved requests)
            else if (status === 'Accepted') {
                acceptButton.style.display = 'none';
                handOverButton.style.display = 'none';
            }
            // Hide both buttons for other statuses (Completed, Declined, etc.)
            else {
                acceptButton.style.display = 'none';
                handOverButton.style.display = 'none';
            }
        }
    }

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
                // Pass current page as source parameter for proper redirect back
                const currentPage = encodeURIComponent(window.location.pathname + window.location.search);
                window.location.href = '../../src/views/forms/donor-form-modal.php?source=' + currentPage;
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
                modalBodyText.innerHTML = `Are you sure you want to decline this request for the following reason? <br><strong>("${reason}")</strong>`
            });
        }

        // Accept Request button logic
        document.getElementById('modalAcceptButton').addEventListener('click', function() {
            // Get the request_id from the hidden field
            var requestId = document.getElementById('modalRequestId').value;
            // Create a hidden form and submit it
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'request_id';
            input.value = requestId;
            form.appendChild(input);
            var accept = document.createElement('input');
            accept.type = 'hidden';
            accept.name = 'accept_request';
            accept.value = '1';
            form.appendChild(accept);
            document.body.appendChild(form);
            form.submit();
        });

        // Hand Over button logic
        document.getElementById('handOverButton').addEventListener('click', function() {
            // Get the request_id from the hidden field
            var requestId = document.getElementById('modalRequestId').value;
            
            // Show confirmation dialog
            if (confirm('Are you sure you want to mark this request as completed (handed over)?')) {
                // Create a hidden form and submit it
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'request_id';
                input.value = requestId;
                form.appendChild(input);
                var handover = document.createElement('input');
                handover.type = 'hidden';
                handover.name = 'handover_request';
                handover.value = '1';
                form.appendChild(handover);
                document.body.appendChild(form);
                form.submit();
            }
        });

        // Decline button in the request details modal
        const declineBtn = document.getElementById('declineRequest');
        if (declineBtn) {
            declineBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // Set the request ID in the decline modal
                const reqId = document.getElementById('modalRequestId').value;
                document.getElementById('declineRequestId').value = reqId;
                // Reset the select
                document.getElementById('declineReasonSelect').selectedIndex = 0;
                // Hide the request details modal first
                var requestDetailsModal = bootstrap.Modal.getInstance(document.getElementById('requestModal'));
                if (requestDetailsModal) requestDetailsModal.hide();
                // Show the decline modal
                var declineModal = new bootstrap.Modal(document.getElementById('declineRequestModal'));
                setTimeout(function() { declineModal.show(); }, 300); // Wait for fade out
            });
        }

        // Step 2: Intercept the decline reason form submit
        const declineRequestForm = document.getElementById('declineRequestForm');
        const declineReasonSelect = document.getElementById('declineReasonSelect');
        const declineFinalConfirmModal = new bootstrap.Modal(document.getElementById('declineFinalConfirmModal'));
        const declineFinalConfirmText = document.getElementById('declineFinalConfirmText');
        const declineFinalConfirmBtn = document.getElementById('declineFinalConfirmBtn');

        let declineFormSubmitPending = false; // Prevent double submit

        if (declineRequestForm) {
            declineRequestForm.addEventListener('submit', function(e) {
                if (!declineFormSubmitPending) {
                    e.preventDefault();
                    const reason = declineReasonSelect.value;
                    if (!reason) {
                        alert('Please select a reason for declining.');
                        return;
                    }
                    // Show the confirmation modal with the selected reason
                    declineFinalConfirmText.innerHTML = `Are you sure you want to decline this request for the following reason?<br><strong>${reason}</strong>`;
                    declineFinalConfirmModal.show();
                }
            });
        }

        // Step 3: On confirm, submit the form
        if (declineFinalConfirmBtn) {
            declineFinalConfirmBtn.addEventListener('click', function() {
                declineFormSubmitPending = true;
                declineFinalConfirmModal.hide();
                // Submit the form after a short delay to allow modal to hide smoothly
                setTimeout(() => {
                    declineRequestForm.submit();
                }, 300);
            });
        }
    });
    </script>
</body>
</html>