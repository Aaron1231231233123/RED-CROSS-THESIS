<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once 'module/optimized_functions.php';
// Send short-term caching headers for better performance on slow networks
header('Cache-Control: public, max-age=180');
header('Vary: Accept-Encoding');

// Check if the user is logged in and has admin role (role_id = 1)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    // Redirect to login page or show error
    header("Location: ../../public/login.php");
    exit();
}

// Handle POST requests for request processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept_request']) && isset($_POST['request_id'])) {
        // Handle approve request
        $request_id = intval($_POST['request_id']);
        
        try {
            // Update request status to Approved
            $update_data = [
                'status' => 'Approved',
                'last_updated' => date('Y-m-d H:i:s'),
                'approved_by' => $_SESSION['user_id'] ?? 'Admin',
                'approved_date' => date('Y-m-d H:i:s')
            ];
            
            $response = supabaseRequest(
                "blood_requests?request_id=eq." . $request_id,
                'PATCH',
                $update_data
            );
            
            if ($response['code'] >= 200 && $response['code'] < 300) {
                // Redirect with success message
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=accepted&success=1&message=" . urlencode("Request #$request_id has been approved successfully."));
                exit();
            } else {
                throw new Exception("Failed to update request status. HTTP Code: " . $response['code']);
            }
        } catch (Exception $e) {
            error_log("Error approving request: " . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode("Failed to approve request: " . $e->getMessage()));
            exit();
        }
    }
    
    if (isset($_POST['decline_request']) && isset($_POST['request_id'])) {
        // Handle decline request
        $request_id = intval($_POST['request_id']);
        $decline_reason = isset($_POST['decline_reason']) ? trim($_POST['decline_reason']) : 'No reason provided';
        
        try {
            // Update request status to Declined
            $update_data = [
                'status' => 'Declined',
                'last_updated' => date('Y-m-d H:i:s'),
                'decline_reason' => $decline_reason
            ];
            
            $response = supabaseRequest(
                "blood_requests?request_id=eq." . $request_id,
                'PATCH',
                $update_data
            );
            
            if ($response['code'] >= 200 && $response['code'] < 300) {
                // Redirect with success message
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=declined&success=1&message=" . urlencode("Request #$request_id has been declined."));
                exit();
            } else {
                throw new Exception("Failed to update request status. HTTP Code: " . $response['code']);
            }
        } catch (Exception $e) {
            error_log("Error declining request: " . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode("Failed to decline request: " . $e->getMessage()));
            exit();
        }
    }
    
    if (isset($_POST['handover_request']) && isset($_POST['request_id'])) {
        // Handle handover request
        $request_id = intval($_POST['request_id']);
        
        try {
            // Update request status to Completed (Handed Over)
            $update_data = [
                'status' => 'Completed',
                'last_updated' => date('Y-m-d H:i:s'),
                'handed_over_by' => $_SESSION['user_id'] ?? 'Admin',
                'handed_over_date' => date('Y-m-d H:i:s')
            ];
            
            $response = supabaseRequest(
                "blood_requests?request_id=eq." . $request_id,
                'PATCH',
                $update_data
            );
            
            if ($response['code'] >= 200 && $response['code'] < 300) {
                // Redirect with success message
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=handedover&success=1&message=" . urlencode("Request #$request_id has been marked as handed over."));
                exit();
            } else {
                throw new Exception("Failed to update request status. HTTP Code: " . $response['code']);
            }
        } catch (Exception $e) {
            error_log("Error processing handover: " . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode("Failed to process handover: " . $e->getMessage()));
            exit();
        }
    }
}

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/module/optimized_functions.php';

// Function to get admin name from user_id
function getAdminName($user_id) {
    try {
        $response = supabaseRequest("users?select=first_name,surname&user_id=eq." . $user_id);
        if ($response['code'] === 200 && !empty($response['data'])) {
            $user = $response['data'][0];
            $first_name = trim($user['first_name'] ?? '');
            $surname = trim($user['surname'] ?? '');
            
            if (!empty($first_name) && !empty($surname)) {
                return "Dr. $first_name $surname";
            } elseif (!empty($first_name)) {
                return "Dr. $first_name";
            } elseif (!empty($surname)) {
                return "Dr. $surname";
            }
        }
    } catch (Exception $e) {
        error_log("Error getting admin name: " . $e->getMessage());
    }
    return 'Dr. Admin';
}

// Get current admin name
$admin_name = getAdminName($_SESSION['user_id'] ?? '');

// Function to get admin name from handed_over_by user_id
function getHandedOverByAdminName($handed_over_by) {
    if (empty($handed_over_by)) {
        return 'Not handed over yet';
    }
    return getAdminName($handed_over_by);
}

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// Fetch blood requests based on status from GET parameter
// Unified view: ignore status filters for this page and show all requests
$status = 'all';

function fetchAllBloodRequests($limit = 50, $offset = 0) {
    // Narrow columns and paginate (urgent first, newest first)
    $select = "request_id,hospital_admitted,patient_blood_type,rh_factor,units_requested,is_asap,requested_on,status,patient_name,patient_age,patient_gender,patient_diagnosis,physician_name,when_needed,handed_over_by,handed_over_date";
    $endpoint = "blood_requests?select=" . urlencode($select) . "&order=is_asap.desc,requested_on.desc&limit={$limit}&offset={$offset}";
    $response = supabaseRequest($endpoint);
    if (isset($response['data'])) {
        return $response['data'];
    }
    error_log("Error fetching all blood requests: " . ($response['error'] ?? 'Unknown error'));
    return [];
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$page_size = 50;
$offset = ($page - 1) * $page_size;
$blood_requests = fetchAllBloodRequests($page_size, $offset);

// Handle success/error messages
$success_message = '';
$error_message = '';
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['message'])) {
    $success_message = urldecode($_GET['message']);
}
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

// Function to fetch blood units for a specific request
function fetchBloodUnitsForRequest($request_id) {
    try {
        // First, get the request details to know what blood type is needed
        $requestResponse = supabaseRequest("blood_requests?request_id=eq." . $request_id);
        if (!isset($requestResponse['data']) || empty($requestResponse['data'])) {
            return [];
        }
        
        $request = $requestResponse['data'][0];
        $needed_blood_type = $request['patient_blood_type'];
        $needed_rh_factor = $request['rh_factor'];
        $needed_units = intval($request['units_requested']);
        
        // Get compatible blood types
        $compatible_types = [];
        $is_positive = $needed_rh_factor === 'Positive';
        
        // O+ can receive O+ and O-
        // O- can only receive O-
        // A+ can receive A+, A-, O+, O-
        // A- can receive A-, O-
        // B+ can receive B+, B-, O+, O-
        // B- can receive B-, O-
        // AB+ can receive all types
        // AB- can receive AB-, A-, B-, O-
        
        switch ($needed_blood_type) {
            case 'O':
                if ($is_positive) {
                    $compatible_types = ['O+', 'O-'];
                } else {
                    $compatible_types = ['O-'];
                }
                break;
            case 'A':
                if ($is_positive) {
                    $compatible_types = ['A+', 'A-', 'O+', 'O-'];
                } else {
                    $compatible_types = ['A-', 'O-'];
                }
                break;
            case 'B':
                if ($is_positive) {
                    $compatible_types = ['B+', 'B-', 'O+', 'O-'];
                } else {
                    $compatible_types = ['B-', 'O-'];
                }
                break;
            case 'AB':
                if ($is_positive) {
                    $compatible_types = ['AB+', 'AB-', 'A+', 'A-', 'B+', 'B-', 'O+', 'O-'];
                } else {
                    $compatible_types = ['AB-', 'A-', 'B-', 'O-'];
                }
                break;
        }
        
        // Convert to database format (with + and - instead of Positive/Negative)
        $db_compatible_types = [];
        foreach ($compatible_types as $type) {
            $blood_type = substr($type, 0, -1);
            $rh_factor = substr($type, -1) === '+' ? 'Positive' : 'Negative';
            $db_compatible_types[] = $blood_type . '|' . $rh_factor;
        }
        
        // Build the query to get available blood units
        $blood_type_conditions = [];
        foreach ($db_compatible_types as $type) {
            list($bt, $rh) = explode('|', $type);
            $blood_type_conditions[] = "(blood_type.eq.{$bt}&rh_factor.eq.{$rh})";
        }
        
        $blood_type_filter = implode(',', $blood_type_conditions);
        $endpoint = "blood_bank_units?select=unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,hospital_request_id&or=({$blood_type_filter})&status=eq.Valid&hospital_request_id=is.null&order=collected_at.asc&limit=" . $needed_units;
        
    $response = supabaseRequest($endpoint);
    if (isset($response['data'])) {
        return $response['data'];
        }
        
        return [];
    } catch (Exception $e) {
        error_log("Error fetching blood units for request: " . $e->getMessage());
        return [];
    }
}

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
    
    // OPTIMIZATION: Use enhanced querySQL function for blood_bank_units data
    $bloodBankUnitsData = querySQL(
        'blood_bank_units',
        'unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,created_at,updated_at',
        []
    );
    
    if (isset($bloodBankUnitsData['error'])) {
        return [false, 'Error checking inventory: ' . $bloodBankUnitsData['error']];
    }
    
    // Get compatible blood types
    $compatible_types = getCompatibleBloodTypes($requested_blood_type, $requested_rh_factor);
    
    // Check available units for each compatible type
    $available_units = 0;
    $available_by_type = [];
    
    foreach ($bloodBankUnitsData as $item) {
        if (empty($item['unit_serial_number'])) continue;
        
        $item_blood_type = $item['blood_type'];
        $item_rh = strpos($item_blood_type, '+') !== false ? 'Positive' : 'Negative';
        $item_type = str_replace(['+', '-'], '', $item_blood_type);
        
        // Check if this blood type is compatible
        foreach ($compatible_types as $compatible) {
            if ($compatible['type'] === $item_type && $compatible['rh'] === $item_rh) {
                // Check if not expired and not already handed over
                $expiration_date = new DateTime($item['expires_at']);
                $current_status = $item['status'] ?? 'available';
                
                if (new DateTime() <= $expiration_date && $current_status !== 'handed_over') {
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

/* Donor Management Section */
#donorManagementCollapse {
    margin-top: 2px;
    border: none;
    background-color: transparent;
}

#donorManagementCollapse .nav-link {
    color: #333;
    padding: 8px 15px 8px 40px;
}

#donorManagementCollapse .nav-link:hover {
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

    #donorManagementCollapse .nav-link:hover {
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
                <div class="sidebar-main-content">
                    <div class="d-flex align-items-center ps-1 mb-3 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" style="width: 65px; height: 65px; object-fit: contain;">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                    <ul class="nav flex-column">
                        <a href="dashboard-Inventory-System.php" class="nav-link">
                            <span><i class="fas fa-home"></i>Home</span>
                        </a>
                        <a href="dashboard-Inventory-System-list-of-donations.php" class="nav-link">
                            <span><i class="fas fa-users"></i>Donor Management</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                            <span><i class="fas fa-tint"></i>Blood Bank</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link active">
                            <span><i class="fas fa-list"></i>Hospital Requests</span>
                        </a>
                        <a href="#" class="nav-link">
                            <span><i class="fas fa-chart-line"></i>Forecast Reports</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Users.php" class="nav-link">
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
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mb-5">
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <strong>Error:</strong> <?php echo $error_message; ?>
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
                    <table class="table table-striped table-hover" id="requestTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:32px;"></th>
                                <th>No.</th>
                                <th>Request ID</th>
                                <th>Hospital</th>
                                <th>Blood Type</th>
                                <th>Quantity</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $rowNum=1; foreach ($blood_requests as $request): ?>
                            <?php 
                                $hospital_name = $request['hospital_admitted'] ? $request['hospital_admitted'] : 'Hospital';
                                $blood_type_display = $request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-');
                                $priority_display = $request['is_asap'] ? 'Urgent' : 'Routine';
                                $requested_on = $request['requested_on'] ? date('Y-m-d', strtotime($request['requested_on'])) : '-';
                                $is_asap = !empty($request['is_asap']);
                            ?>
                            <tr>
                                <td style="text-align:center;">
                                    <?php if ($is_asap && strtolower($request['status']) === 'pending'): ?>
                                        <img src="../assets/img/icons8-warning-96.png" alt="Urgent" style="height:20px; width:20px;" loading="lazy" />
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $rowNum++; ?></td>
                                <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                <td><?php echo htmlspecialchars($hospital_name); ?></td>
                                <td><?php echo htmlspecialchars($blood_type_display); ?></td>
                                <td><?php echo htmlspecialchars($request['units_requested']); ?></td>
                                <td><?php echo htmlspecialchars($requested_on); ?></td>
                                <td>
                                    <?php
                                    $status_val = isset($request['status']) ? strtolower($request['status']) : '';
                                    if ($status_val === 'pending') {
                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                    } elseif ($status_val === 'rescheduled') {
                                        echo '<span class="badge bg-warning text-dark">Rescheduled</span>';
                                    } elseif ($status_val === 'approved') {
                                        echo '<span class="badge bg-success">Approved</span>';
                                    } elseif ($status_val === 'printed') {
                                        echo '<span class="badge bg-info text-dark">Printed</span>';
                                    } elseif ($status_val === 'completed') {
                                        echo '<span class="badge bg-success">Completed</span>';
                                    } elseif ($status_val === 'declined') {
                                        echo '<span class="badge bg-danger">Declined</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">'.htmlspecialchars($request['status'] ?? 'N/A').'</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button 
                                        class="btn btn-info btn-sm view-btn"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#requestModal"
                                        title="View Details"
                                        onclick="console.log('PHP Status being passed:', '<?php echo addslashes($request['status']); ?>'); loadRequestDetails(
                                            '<?php echo htmlspecialchars($request['request_id']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_name']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_blood_type']); ?>',
                                            'Whole Blood',
                                            '<?php echo htmlspecialchars($request['rh_factor']); ?>',
                                            '<?php echo htmlspecialchars($request['units_requested']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_diagnosis']); ?>',
                                            '<?php echo htmlspecialchars($hospital_name); ?>',
                                            '<?php echo htmlspecialchars($request['physician_name']); ?>',
                                            '<?php echo htmlspecialchars($priority_display); ?>',
                                            '<?php echo htmlspecialchars($request['status']); ?>',
                                            '<?php echo htmlspecialchars($request['requested_on']); ?>',
                                            '<?php echo htmlspecialchars($request['when_needed']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_age']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_gender']); ?>',
                                            '<?php echo htmlspecialchars($request['handed_over_by'] ?? ''); ?>',
                                            '<?php echo htmlspecialchars($request['handed_over_date'] ?? ''); ?>',
                                            '<?php echo htmlspecialchars(getHandedOverByAdminName($request['handed_over_by'] ?? '')); ?>'
                                        )"
                                        >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="?page=<?php echo $page-1; ?>">Prev</a>
                        <?php endif; ?>
                        <?php if (count($blood_requests) === $page_size): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="?page=<?php echo $page+1; ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        
        <!-- Referral Blood Shipment Record Modal -->
        <div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div id="alertContainer"></div>
                <div class="modal-content" style="border-radius: 10px; border: none;">
                    <!-- Modal Header -->
                    <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0; padding: 20px;">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 5px;">
                                    Date: <span id="modalRequestDate">-</span>
                    </div>
                                <h4 class="modal-title mb-0" style="font-weight: bold; font-size: 1.5rem;">
                                    Referral Blood Shipment Record
                                </h4>
                            </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span id="modalRequestStatus" class="badge" style="background: #ffc107; padding: 8px 12px; font-size: 0.9rem;">Pending</span>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="margin-left: 10px;"></button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="modal-body" style="padding: 30px;">
                        <form id="requestDetailsForm">
                            <input type="hidden" id="modalRequestId" name="request_id">
                            
                            <!-- Patient Information -->
                            <div class="mb-4">
                                <h5 style="font-weight: bold; color: #333; margin-bottom: 15px;">Patient Information</h5>
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4 style="font-weight: bold; color: #000; margin-bottom: 5px;" id="modalPatientName">-</h4>
                                        <p style="color: #666; margin: 0; font-size: 1.05rem;" id="modalPatientDetails">-</p>
                                        <div class="d-flex gap-4 mt-2" style="color:#444;">
                                            <div><span class="fw-bold">Age:</span> <span id="modalPatientAge">-</span></div>
                                            <div><span class="fw-bold"> Gender:</span> <span id="modalPatientGender">-</span></div>
                                </div>
                                </div>
                                </div>
                            </div>
                            
                            <hr style="border-color: #ddd; margin: 20px 0;">
                            
                            <!-- Request Details -->
                            <div class="mb-4">
                                <h5 style="font-weight: bold; color: #333; margin-bottom: 20px;">Request Details</h5>
                                
                                <!-- Diagnosis -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Diagnosis:</label>
                                    <input type="text" class="form-control" id="modalDiagnosis" readonly style="border: 1px solid #ddd; padding: 10px;">
                                </div>
                            
                                <!-- Blood Type Table -->
                                <div class="mb-3">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" style="margin: 0;">
                                            <thead style="background: #dc3545; color: white;">
                                                <tr>
                                                    <th style="padding: 12px; text-align: center;">Blood Type</th>
                                                    <th style="padding: 12px; text-align: center;">RH</th>
                                                    <th style="padding: 12px; text-align: center;">Number of Units</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td style="padding: 12px; text-align: center;">
                                                        <input type="text" class="form-control text-center" id="modalBloodType" readonly style="border: none; background: transparent;">
                                                    </td>
                                                    <td style="padding: 12px; text-align: center;">
                                                        <input type="text" class="form-control text-center" id="modalRhFactor" readonly style="border: none; background: transparent;">
                                                    </td>
                                                    <td style="padding: 12px; text-align: center;">
                                                        <input type="text" class="form-control text-center" id="modalUnitsNeeded" readonly style="border: none; background: transparent;">
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                </div>
                            </div>
                            
                                <!-- When Needed -->
                            <div class="mb-3">
                                    <label class="form-label fw-bold">When Needed:</label>
                                    <div class="d-flex align-items-center gap-4" style="border:1px solid #ddd; border-radius:6px; padding:10px 12px;">
                                            <div class="form-check d-flex align-items-center gap-2 me-3">
                                                <input class="form-check-input" type="radio" name="whenNeededOption" id="asapRadio" value="asap" disabled>
                                            <label class="form-check-label fw-bold" for="asapRadio">ASAP</label>
                                        </div>
                                            <div class="form-check d-flex align-items-center gap-2">
                                                <input class="form-check-input" type="radio" name="whenNeededOption" id="scheduledRadio" value="scheduled" disabled>
                                            <label class="form-check-label fw-bold" for="scheduledRadio">Scheduled</label>
                                            <input type="text" class="form-control" id="modalScheduledDisplay" style="width: 240px; margin-left: 10px;" readonly>
                                        </div>
                                    </div>
                            </div>
        
                                <!-- Hospital and Physician -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                        <label class="form-label fw-bold">Hospital Admitted:</label>
                                        <input type="text" class="form-control" id="modalHospital" readonly style="border: 1px solid #ddd; padding: 10px;">
                                </div>
                                <div class="col-md-6">
                                        <label class="form-label fw-bold">Requesting Physician:</label>
                                        <input type="text" class="form-control" id="modalPhysician" readonly style="border: 1px solid #ddd; padding: 10px;">
                                </div>
                            </div>
        
                                <!-- Approval Information (shown when approved) -->
                                <div id="approvalSection" style="display: none;">
                                    <hr style="border-color: #ddd; margin: 20px 0;">
                            <div class="mb-3">
                                        <label class="form-label fw-bold">Approved by:</label>
                                        <input type="text" class="form-control" id="modalApprovedBy" readonly style="border: 1px solid #ddd; padding: 10px; background: #f8f9fa;">
                                    </div>
                            </div>
                            
                                <!-- Handover Information (shown when handed over) -->
                                <div id="handoverSection" style="display: none;">
                                    <hr style="border-color: #ddd; margin: 20px 0;">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Handed Over by:</label>
                                        <input type="text" class="form-control" id="modalHandedOverBy" readonly style="border: 1px solid #ddd; padding: 10px; background: #f8f9fa;">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="modal-footer" style="padding: 20px 30px; border-top: 1px solid #ddd; background: #f8f9fa; border-radius: 0 0 10px 10px;">
                        <div class="d-flex gap-2 w-100 justify-content-end">
                            <!-- Decline Button -->
                            <button type="button" class="btn btn-danger" id="declineRequest" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; display: none;">
                                <i class="fas fa-times-circle me-2"></i>Decline Request
                                </button>
                            
                            <!-- Approve Button -->
                            <button type="button" class="btn btn-success" id="modalAcceptButton" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; display: none;">
                                <i class="fas fa-check-circle me-2"></i>Approve Request
                                </button>
                            
                            <!-- Hand Over Button -->
                            <button type="button" class="btn btn-primary" id="handOverButton" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; display: none;">
                                <i class="fas fa-truck me-2"></i>Handed Over
                                </button>
                            </div>
                    </div>
                </div>
            </div>
            </div>
            
</main>
            
        </div>
    </div>
    
    <!-- Approve Request Confirmation Modal -->
    <div class="modal fade" id="approveConfirmModal" tabindex="-1" aria-labelledby="approveConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="approveConfirmLabel" style="font-weight: bold;">Approve Request?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p id="approveConfirmText" style="margin: 0; font-size: 1rem; color: #333;">
                        Are you sure you want to approve this blood request? The requested units will be prepared for handover.
                    </p>
                </div>
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmApproveBtn" style="padding: 8px 20px; font-weight: bold; background: #007bff;">Accept</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Decline Request Modal -->
    <div class="modal fade" id="declineRequestModal" tabindex="-1" aria-labelledby="declineRequestModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="declineRequestModalLabel" style="font-weight: bold;">Decline Request?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="declineRequestForm" method="post">
                    <div class="modal-body" style="padding: 25px;">
              <input type="hidden" name="request_id" id="declineRequestId">
              <input type="hidden" name="decline_request" value="1">
                        <p style="margin-bottom: 20px; font-size: 1rem; color: #333;">
                            Are you sure you want to decline this request for <strong id="declineRequestIdText">Request ID</strong>?
                        </p>
                        <p style="margin-bottom: 20px; font-size: 0.9rem; color: #dc3545; font-weight: bold;">
                            This action cannot be undone.
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-bold" style="color: #333;">Reason for Declining</label>
                            <textarea class="form-control" name="decline_reason" id="declineReasonText" rows="3" 
                                placeholder="Please provide a reason for declining this request..." 
                                style="border: 1px solid #ddd; padding: 10px;" required></textarea>
            </div>
                    </div>
                    <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">Cancel</button>
                        <button type="submit" class="btn btn-danger" style="padding: 8px 20px; font-weight: bold;">Confirm</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <!-- Request Declined Success Modal -->
    <div class="modal fade" id="requestDeclinedModal" tabindex="-1" aria-labelledby="requestDeclinedLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="requestDeclinedLabel" style="font-weight: bold;">Request Declined</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin: 0; font-size: 1rem; color: #333;">
                        Request <strong id="declinedRequestId">HRQ-00125</strong> has been declined.
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 0.9rem; color: #333;">
                        Reason: <strong id="declinedReason">Insufficient stock of O+</strong>
                    </p>
          </div>
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Request Approved Success Modal -->
    <div class="modal fade" id="requestApprovedModal" tabindex="-1" aria-labelledby="requestApprovedLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="requestApprovedLabel" style="font-weight: bold;">Request Approved</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin: 0; font-size: 1rem; color: #333;">
                        The blood units have been allocated to this request.
                    </p>
                </div>
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Handover Confirmation Modal -->
    <div class="modal fade" id="handoverConfirmModal" tabindex="-1" aria-labelledby="handoverConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0; padding: 20px;">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 5px;">
                                Date: <span id="handoverModalDate">-</span>
                            </div>
                            <h4 class="modal-title mb-0" style="font-weight: bold; font-size: 1.5rem;">
                                Handover Blood Units
                            </h4>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="margin-left: 10px;"></button>
                    </div>
                </div>
                
                <div class="modal-body" style="padding: 30px;">
                    <!-- Hospital Information -->
                    <div class="mb-4">
                        <h5 style="font-weight: bold; color: #333; margin-bottom: 15px;">Hospital Information</h5>
                        <div class="row">
                            <div class="col-md-8">
                                <h4 style="font-weight: bold; color: #000; margin-bottom: 5px;" id="handoverHospitalName">-</h4>
                                <p style="color: #666; margin: 0; font-size: 1.1rem;" id="handoverHospitalDetails">-</p>
                            </div>
                        </div>
                    </div>
                    
                    <hr style="border-color: #ddd; margin: 20px 0;">
                    
                    <!-- Blood Units Table -->
                    <div class="mb-4">
                        <h5 style="font-weight: bold; color: #333; margin-bottom: 20px;">Blood Units to Hand Over</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered" style="margin: 0;">
                                <thead style="background: #dc3545; color: white;">
                                    <tr>
                                        <th style="padding: 12px; text-align: center;">Unit Serial Number</th>
                                        <th style="padding: 12px; text-align: center;">Blood Type</th>
                                        <th style="padding: 12px; text-align: center;">Bag Type</th>
                                        <th style="padding: 12px; text-align: center;">Expiration Date</th>
                                        <th style="padding: 12px; text-align: center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="handoverUnitsTableBody">
                                    <!-- Blood units will be populated here -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Summary Information -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card" style="border: 1px solid #ddd; border-radius: 5px;">
                                    <div class="card-body" style="padding: 15px;">
                                        <h6 style="font-weight: bold; margin-bottom: 10px; color: #333;">Request Summary</h6>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Request ID:</strong> <span id="handoverRequestId">-</span></p>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Patient:</strong> <span id="handoverPatientName">-</span></p>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Total Units:</strong> <span id="handoverTotalUnits">-</span></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card" style="border: 1px solid #ddd; border-radius: 5px;">
                                    <div class="card-body" style="padding: 15px;">
                                        <h6 style="font-weight: bold; margin-bottom: 10px; color: #333;">Handover Details</h6>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Handed Over by:</strong> <span id="handoverStaffName">-</span></p>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Date & Time:</strong> <span id="handoverDateTime">-</span></p>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Status:</strong> <span class="badge bg-success">Ready for Handover</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 20px 30px; border-top: 1px solid #ddd; background: #f8f9fa; border-radius: 0 0 10px 10px;">
                    <div class="d-flex gap-2 w-100 justify-content-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 10px 20px; font-weight: bold; border-radius: 5px;">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmHandoverBtn" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; background: #007bff;">
                            <i class="fas fa-truck me-2"></i>Confirm Handover
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Handover Success Modal -->
    <div class="modal fade" id="handoverSuccessModal" tabindex="-1" aria-labelledby="handoverSuccessLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="handoverSuccessLabel" style="font-weight: bold;">Handover Successful</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin: 0; font-size: 1rem; color: #333;">
                        Requested blood units have been successfully handed over and marked as completed.
                    </p>
                </div>
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">OK</button>
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
    // Get admin name from PHP
    const adminName = '<?php echo addslashes($admin_name); ?>';
    
    // Enhanced function to populate the modal fields based on wireframe design
    function loadRequestDetails(request_id, patientName, bloodType, component, rhFactor, unitsNeeded, diagnosis, hospital, physician, priority, status, requestDate, whenNeeded, patientAge, patientGender, handedOverBy, handedOverDate, handedOverByAdminName) {
        console.log('=== loadRequestDetails DEBUG ===');
        console.log('All arguments:', arguments);
        console.log('Status parameter (11th argument):', status);
        console.log('Status type:', typeof status);
        console.log('Status value:', JSON.stringify(status));
        console.log('Arguments length:', arguments.length);
        
        // Set basic request info
        document.getElementById('modalRequestId').value = request_id;
        document.getElementById('modalRequestDate').textContent = requestDate ? new Date(requestDate).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        }) : '-';
        
        // Set patient information
        document.getElementById('modalPatientName').textContent = patientName || '-';
        // Remove units/priority line under name per request
        document.getElementById('modalPatientDetails').textContent = '';
        // Age and Gender fields (prefer arguments; fallback to __currentRequest)
        const ageVal = (patientAge !== undefined && patientAge !== null && patientAge !== '') ? patientAge : (window.__currentRequest && window.__currentRequest.patient_age);
        const genderVal = (patientGender !== undefined && patientGender !== null && patientGender !== '') ? patientGender : (window.__currentRequest && window.__currentRequest.patient_gender);
        document.getElementById('modalPatientAge').textContent = (ageVal !== undefined && ageVal !== null && ageVal !== '') ? ageVal : '-';
        document.getElementById('modalPatientGender').textContent = (genderVal !== undefined && genderVal !== null && genderVal !== '') ? genderVal : '-';
        
        // Set request details
        document.getElementById('modalDiagnosis').value = diagnosis || '';
        document.getElementById('modalBloodType').value = bloodType || '';
        document.getElementById('modalRhFactor').value = rhFactor || '';
        document.getElementById('modalUnitsNeeded').value = unitsNeeded || '';
        document.getElementById('modalHospital').value = hospital || '';
        document.getElementById('modalPhysician').value = physician || '';
        
        // Handle When Needed (ASAP checkbox + scheduled formatted string)
        const asapRadio = document.getElementById('asapRadio');
        const scheduledRadio = document.getElementById('scheduledRadio');
        const scheduledDisplay = document.getElementById('modalScheduledDisplay');
        const isAsap = (priority === 'Urgent' || priority === 'ASAP');
        if (asapRadio && scheduledRadio) {
            asapRadio.checked = !!isAsap;
            scheduledRadio.checked = !isAsap;
        }
        if (scheduledDisplay) {
            if (whenNeeded) {
                const d = new Date(whenNeeded);
                const pad = (n) => n.toString().padStart(2,'0');
                const day = pad(d.getDate());
                const month = pad(d.getMonth()+1);
                const year = d.getFullYear();
                let hours = d.getHours();
                const minutes = pad(d.getMinutes());
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12; if (hours === 0) hours = 12;
                const time = `${hours}:${minutes} ${ampm}`;
                scheduledDisplay.value = `${day}/${month}/${year} ${time}`;
            } else {
                scheduledDisplay.value = '';
            }
        }
        
        // Note: Event listeners removed since modal fields are readonly
        
        // Set status badge with proper mapping for new flow
        const statusBadge = document.getElementById('modalRequestStatus');
        let displayStatus = status || 'Pending';
        
        console.log('=== STATUS MAPPING DEBUG ===');
        console.log('Original status from parameter:', status);
        console.log('Status badge element:', statusBadge);
        console.log('Status badge current text before update:', statusBadge ? statusBadge.textContent : 'Element not found');
        
        // Map database statuses to display statuses for Referral Blood Shipment Record modal
        switch(status.toLowerCase()) {
            case 'pending':
                displayStatus = 'Pending';
                break;
            case 'approved':
                displayStatus = 'Approved';
                break;
            case 'printed':
                displayStatus = 'Printing';
                break;
            case 'completed':
                displayStatus = 'Handed-Over';
                break;
            case 'declined':
                displayStatus = 'Declined';
                break;
            case 'rescheduled':
                displayStatus = 'Rescheduled';
                break;
            default:
                displayStatus = status || 'Pending';
        }
        
        console.log('Final displayStatus after mapping:', displayStatus);
        console.log('About to update status badge text to:', displayStatus);
        statusBadge.textContent = displayStatus;
        console.log('Status badge text after update:', statusBadge.textContent);
        console.log('=== END STATUS MAPPING DEBUG ===');
        
        // Set status badge color based on display status (matching new flow)
        switch(displayStatus) {
            case 'Pending':
            case 'Rescheduled':
                statusBadge.style.background = '#ffc107';
                statusBadge.style.color = '#000';
                break;
            case 'Approved':
                statusBadge.style.background = '#28a745';
                statusBadge.style.color = '#fff';
                break;
            case 'Printing':
                statusBadge.style.background = '#17a2b8';
                statusBadge.style.color = '#fff';
                break;
            case 'Handed-Over':
                statusBadge.style.background = '#28a745';
                statusBadge.style.color = '#fff';
                break;
            case 'Declined':
                statusBadge.style.background = '#dc3545';
                statusBadge.style.color = '#fff';
                break;
            default:
                statusBadge.style.background = '#6c757d';
                statusBadge.style.color = '#fff';
        }
        
        // Handle button visibility and sections based on status
        const acceptButton = document.getElementById('modalAcceptButton');
        const declineButton = document.getElementById('declineRequest');
        const handOverButton = document.getElementById('handOverButton');
        const approvalSection = document.getElementById('approvalSection');
        const handoverSection = document.getElementById('handoverSection');
        
        // Reset all sections
        if (approvalSection) approvalSection.style.display = 'none';
        if (handoverSection) handoverSection.style.display = 'none';
        
        if (acceptButton && declineButton && handOverButton) {
            // Use displayStatus to determine controls
            if (['Pending', 'Rescheduled'].includes(displayStatus)) {
                acceptButton.style.display = 'inline-block';
                declineButton.style.display = 'inline-block';
                handOverButton.style.display = 'none';
            }
            // Show approval info for Approved status (no action buttons)
            else if (['Approved'].includes(displayStatus)) {
                acceptButton.style.display = 'none';
                declineButton.style.display = 'none';
                handOverButton.style.display = 'none';
                if (approvalSection) {
                    approvalSection.style.display = 'block';
                    document.getElementById('modalApprovedBy').value = `Approved by ${adminName} - ${new Date().toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })} at ${new Date().toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    })}`;
                }
            }
            // Show Hand Over button for Printed status (ready for handover)
            else if (['Printing'].includes(displayStatus)) {
                acceptButton.style.display = 'none';
                declineButton.style.display = 'none';
                handOverButton.style.display = 'inline-block';
                if (approvalSection) {
                    approvalSection.style.display = 'block';
                    document.getElementById('modalApprovedBy').value = `Approved by ${adminName} - ${new Date().toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })} at ${new Date().toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    })}`;
                }
            }
            // Show handover info for Handed-Over status (completed)
            else if (['Handed-Over'].includes(displayStatus)) {
                acceptButton.style.display = 'none';
                declineButton.style.display = 'none';
                handOverButton.style.display = 'none';
                if (handoverSection) {
                    handoverSection.style.display = 'block';
                    
                    // Use actual handed over date if available, otherwise use current date
                    let handedOverDisplayDate = new Date();
                    if (handedOverDate) {
                        const parsedDate = new Date(handedOverDate);
                        if (!isNaN(parsedDate.getTime())) {
                            handedOverDisplayDate = parsedDate;
                        }
                    }
                    
                    // Use the actual admin name passed from PHP
                    let handedOverByAdmin = handedOverByAdminName || 'Admin';
                    
                    document.getElementById('modalHandedOverBy').value = `Handed Over by ${handedOverByAdmin} - ${handedOverDisplayDate.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })} at ${handedOverDisplayDate.toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    })}`;
                }
            }
            // Hide all buttons for other statuses (Declined, etc.)
            else {
                acceptButton.style.display = 'none';
                declineButton.style.display = 'none';
                handOverButton.style.display = 'none';
            }
        }
    }

    // Function to populate handover modal with blood units data
    function populateHandoverModal(data) {
        // Set basic information
        document.getElementById('handoverModalDate').textContent = new Date().toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        document.getElementById('handoverHospitalName').textContent = data.request.hospital_admitted || 'Hospital';
        document.getElementById('handoverHospitalDetails').textContent = `Request ID: ${data.request.request_id} | Patient: ${data.request.patient_name}`;
        
        // Set summary information
        document.getElementById('handoverRequestId').textContent = data.request.request_id;
        document.getElementById('handoverPatientName').textContent = data.request.patient_name;
        document.getElementById('handoverTotalUnits').textContent = data.total_units;
        
        // Set handover details
        document.getElementById('handoverStaffName').textContent = 'Staff Member'; // You can get this from session
        document.getElementById('handoverDateTime').textContent = new Date().toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Populate blood units table
        const tableBody = document.getElementById('handoverUnitsTableBody');
        tableBody.innerHTML = '';
        
        if (data.blood_units && data.blood_units.length > 0) {
            data.blood_units.forEach((unit, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="padding: 12px; text-align: center; font-weight: bold;">${unit.serial_number}</td>
                    <td style="padding: 12px; text-align: center;">${unit.blood_type}</td>
                    <td style="padding: 12px; text-align: center;">${unit.bag_type}</td>
                    <td style="padding: 12px; text-align: center;">${unit.expiration_date}</td>
                    <td style="padding: 12px; text-align: center;">
                        <span class="badge bg-success">${unit.status}</span>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        } else {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="5" style="padding: 20px; text-align: center; color: #666;">
                    No compatible blood units available
                </td>
            `;
            tableBody.appendChild(row);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        const requestDetailsModal = new bootstrap.Modal(document.getElementById('requestModal'));
        const approveConfirmModal = new bootstrap.Modal(document.getElementById('approveConfirmModal'));
        const declineRequestModal = new bootstrap.Modal(document.getElementById('declineRequestModal'));
        const requestDeclinedModal = new bootstrap.Modal(document.getElementById('requestDeclinedModal'));
        const requestApprovedModal = new bootstrap.Modal(document.getElementById('requestApprovedModal'));
        const handoverConfirmModal = new bootstrap.Modal(document.getElementById('handoverConfirmModal'));
        const handoverSuccessModal = new bootstrap.Modal(document.getElementById('handoverSuccessModal'));
        
        // Initialize confirmation and loading modals for donor registration
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'), {
            backdrop: true,
            keyboard: true
        });
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'), {
            backdrop: false,
            keyboard: false
        });

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

        // Approve Request button logic
        document.getElementById('modalAcceptButton').addEventListener('click', function() {
            // Hide the main modal and show approval confirmation
            requestDetailsModal.hide();
            setTimeout(() => {
                approveConfirmModal.show();
            }, 300);
        });

        // Confirm Approve button logic
        document.getElementById('confirmApproveBtn').addEventListener('click', function() {
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
            // Get request ID
            var requestId = document.getElementById('modalRequestId').value;
            
            // Show loading state
            var handoverBtn = this;
            var originalText = handoverBtn.innerHTML;
            handoverBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
            handoverBtn.disabled = true;
            
            // Fetch blood units for this request
            fetch(`../../public/api/get-blood-units-for-request.php?request_id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate handover modal
                        populateHandoverModal(data);
                        
                        // Hide the main modal and show handover confirmation
                        requestDetailsModal.hide();
                        setTimeout(() => {
                            handoverConfirmModal.show();
                        }, 300);
                    } else {
                        alert('Error: ' + (data.error || 'Failed to fetch blood units'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching blood units. Please try again.');
                })
                .finally(() => {
                    // Reset button state
                    handoverBtn.innerHTML = originalText;
                    handoverBtn.disabled = false;
                });
        });

        // Confirm Handover button logic
        document.getElementById('confirmHandoverBtn').addEventListener('click', function() {
            // Get the request_id from the hidden field
            var requestId = document.getElementById('modalRequestId').value;
            
            // Show loading state
            var confirmBtn = this;
            var originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            confirmBtn.disabled = true;
            
            // First, update the blood units to assign them to this request
            fetch(`../../public/api/get-blood-units-for-request.php?request_id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.blood_units.length > 0) {
                        // Update each blood unit with the hospital_request_id
                        const updatePromises = data.blood_units.map(unit => {
                            return fetch('../../public/api/update-blood-unit-request.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    unit_id: unit.unit_id,
                                    hospital_request_id: requestId
                                })
                            });
                        });
                        
                        return Promise.all(updatePromises);
                    } else {
                        throw new Error('No blood units available for handover');
                    }
                })
                .then(() => {
                    // Now submit the handover form
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
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error processing handover: ' + error.message);
                    // Reset button state
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                });
        });

        // Decline button in the request details modal
        const declineBtn = document.getElementById('declineRequest');
        if (declineBtn) {
            declineBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // Set the request ID in the decline modal
                const reqId = document.getElementById('modalRequestId').value;
                document.getElementById('declineRequestId').value = reqId;
                document.getElementById('declineRequestIdText').textContent = reqId;
                // Reset the textarea
                document.getElementById('declineReasonText').value = '';
                // Hide the request details modal first
                requestDetailsModal.hide();
                // Show the decline modal
                setTimeout(function() { 
                    declineRequestModal.show(); 
                }, 300); // Wait for fade out
            });
        }

        // Handle decline form submission
        const declineRequestForm = document.getElementById('declineRequestForm');
        const declineReasonText = document.getElementById('declineReasonText');

        if (declineRequestForm) {
            declineRequestForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                const reason = declineReasonText.value.trim();
                    if (!reason) {
                    alert('Please provide a reason for declining.');
                        return;
                }
                
                // Submit the form directly
                this.submit();
            });
        }
    });
    </script>
</body>
</html>