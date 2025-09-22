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

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/module/optimized_functions.php';

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// OPTIMIZATION: Enhanced function to make API requests to Supabase with retry mechanism
// This replaces the old supabaseRequest function with the optimized version
function supabaseRequestOptimized($endpoint, $method = 'GET', $data = null) {
    return supabaseRequest($endpoint, $method, $data);
}

// OPTIMIZATION: Enhanced fetch handover requests with better error handling
function fetchHandoverRequests() {
    // Get requests with status Accepted OR Printed OR Confirmed OR Declined using explicit OR conditions
    $endpoint = "blood_requests?or=(status.eq.Accepted,status.eq.Printed,status.eq.Confirmed,status.eq.Declined)&order=request_id.desc";
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
    
    // OPTIMIZATION: Use enhanced API function for verification
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

// OPTIMIZATION: Enhanced testSupabaseConnection with retry mechanism
function testSupabaseConnection() {
    $test_url = SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id&limit=1';
    
    // Use the optimized supabaseRequest function
    $response = supabaseRequest('blood_collection?select=blood_collection_id&limit=1');
    
    return [
        'success' => $response['code'] === 200,
        'http_code' => $response['code'],
        'response' => json_encode($response['data']),
        'error' => isset($response['error']) ? $response['error'] : null
    ];
}

// OPTIMIZATION: Enhanced getCompatibleBloodTypes (no changes needed, already optimized)
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

// OPTIMIZATION: Enhanced updateBloodRequestAndInventory with better error handling and performance
function updateBloodRequestAndInventory($request_id) {
    try {
        // OPTIMIZATION: Use enhanced connection test
        $connection_test = testSupabaseConnection();
        if (!$connection_test['success']) {
            throw new Exception("Unable to connect to the database. Please try again later.");
        }
        
        // OPTIMIZATION: Use enhanced API function for fetching request data
        $requestResponse = supabaseRequest("blood_requests?request_id=eq.$request_id");
        if ($requestResponse['code'] !== 200 || empty($requestResponse['data'])) {
            throw new Exception("Failed to fetch blood request. HTTP Code: " . $requestResponse['code']);
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
            throw new Exception("Failed to fetch eligibility data: " . $eligibilityData['error']);
        }
        
        $available_bags = [];
        $today = new DateTime();
        
        foreach ($eligibilityData as $item) {
            if (!empty($item['blood_collection_id'])) {
                // OPTIMIZATION: Use enhanced querySQL for blood collection data
                $bloodCollectionData = querySQL('blood_collection', '*', ['blood_collection_id' => 'eq.' . $item['blood_collection_id']]);
                if (isset($bloodCollectionData['error'])) {
                    error_log("Failed to fetch blood collection data for ID " . $item['blood_collection_id'] . ": " . $bloodCollectionData['error']);
                    continue;
                }
                $bloodCollectionData = isset($bloodCollectionData[0]) ? $bloodCollectionData[0] : null;
            } else {
                $bloodCollectionData = null;
            }
            
            $collectionDate = new DateTime($item['collection_start_time']);
            $expirationDate = clone $collectionDate;
            $expirationDate->modify('+35 days');
            $isExpired = ($today > $expirationDate);
            $amount_taken = $bloodCollectionData && isset($bloodCollectionData['amount_taken']) ? intval($bloodCollectionData['amount_taken']) : 0;
            
            if ($amount_taken > 0 && !$isExpired) {
                $available_bags[] = [
                    'eligibility_id' => $item['eligibility_id'],
                    'blood_collection_id' => $item['blood_collection_id'],
                    'blood_type' => $item['blood_type'],
                    'amount_taken' => $amount_taken,
                    'collection_start_time' => $item['collection_start_time'],
                    'expiration_date' => $expirationDate->format('Y-m-d'),
                ];
            }
        }
        
        $units_found = 0;
        $collections_to_update = [];
        $remaining_units = $units_requested;
        $deducted_by_type = [];
        
        foreach ($available_bags as $bag) {
            if ($remaining_units <= 0) break;
            if ($bag['blood_type'] === $blood_type_full) {
                $available_units = $bag['amount_taken'];
                if ($available_units > 0) {
                    $units_to_take = min($available_units, $remaining_units);
                    $units_found += $units_to_take;
                    $remaining_units -= $units_to_take;
                    if (!isset($deducted_by_type[$bag['blood_type']])) {
                        $deducted_by_type[$bag['blood_type']] = 0;
                    }
                    $deducted_by_type[$bag['blood_type']] += $units_to_take;
                    $bag['units_to_take'] = $units_to_take;
                    $collections_to_update[] = $bag;
                }
            }
        }
        
        if ($remaining_units > 0) {
            $compatible_types = getCompatibleBloodTypes($requested_blood_type, $requested_rh_factor);
            foreach ($compatible_types as $compatible_type) {
                if ($remaining_units <= 0) break;
                $compatible_blood_type = $compatible_type['type'] . ($compatible_type['rh'] === 'Positive' ? '+' : '-');
                foreach ($available_bags as $bag) {
                    if ($remaining_units <= 0) break;
                    if ($bag['blood_type'] === $compatible_blood_type &&
                        !in_array($bag['blood_collection_id'], array_column($collections_to_update, 'blood_collection_id'))) {
                        $available_units = $bag['amount_taken'];
                        if ($available_units > 0) {
                            $units_to_take = min($available_units, $remaining_units);
                            $units_found += $units_to_take;
                            $remaining_units -= $units_to_take;
                            if (!isset($deducted_by_type[$bag['blood_type']])) {
                                $deducted_by_type[$bag['blood_type']] = 0;
                            }
                            $deducted_by_type[$bag['blood_type']] += $units_to_take;
                            $bag['units_to_take'] = $units_to_take;
                            $collections_to_update[] = $bag;
                        }
                    }
                }
            }
        }
        
        if ($units_found < $units_requested) {
            $shortage = $units_requested - $units_found;
            $error_message = "Unable to fulfill blood request due to insufficient fresh blood inventory.\n\n";
            $error_message .= "Request Details:\n";
            $error_message .= "• Requested Blood Type: {$blood_type_full}\n";
            $error_message .= "• Units Requested: {$units_requested}\n";
            $error_message .= "• Fresh Units Available: {$units_found}\n";
            $error_message .= "• Shortage: {$shortage} units\n\n";
            if (!empty($deducted_by_type)) {
                $error_message .= "Available Fresh Blood Types:\n";
                foreach ($deducted_by_type as $type => $amount) {
                    $error_message .= "• {$type}: {$amount} units\n";
                }
            }
            throw new Exception($error_message);
        }
        
        // OPTIMIZATION: Use enhanced API function for batch updates
        foreach ($collections_to_update as $collection) {
            $new_amount = intval($collection['amount_taken']) - intval($collection['units_to_take']);
            if ($new_amount < 0) $new_amount = 0;
            
            $update_data = [
                'amount_taken' => $new_amount,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $updateResponse = supabaseRequest(
                "blood_collection?blood_collection_id=eq." . $collection['blood_collection_id'],
                'PATCH',
                $update_data
            );
            
            if ($updateResponse['code'] !== 200 && $updateResponse['code'] !== 204) {
                throw new Exception("Failed to update blood collection {$collection['blood_collection_id']}. HTTP Code: {$updateResponse['code']}");
            }
        }
        
        // OPTIMIZATION: Use enhanced API function for request status update
        $request_update_data = [
            'status' => 'Confirmed',
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        $requestUpdateResponse = supabaseRequest(
            "blood_requests?request_id=eq.$request_id",
            'PATCH',
            $request_update_data
        );
        
        if ($requestUpdateResponse['code'] !== 200 && $requestUpdateResponse['code'] !== 204) {
            throw new Exception('Failed to update request status. HTTP Code: ' . $requestUpdateResponse['code']);
        }
        
        $detailed_message = "Successfully processed blood request #{$request_id}:\n";
        $detailed_message .= "- Requested blood type: {$blood_type_full}\n";
        $detailed_message .= "- Total units deducted: {$units_requested}\n\n";
        $detailed_message .= "Deduction Details:\n";
        foreach ($deducted_by_type as $type => $amount) {
            $detailed_message .= "- {$type}: {$amount} units\n";
        }
        
        return [
            'success' => true,
            'request_id' => $request_id,
            'message' => 'Blood request completed successfully',
            'detailed_message' => $detailed_message,
            'units_deducted' => $units_requested,
            'blood_type' => $blood_type_full,
            'collections_updated' => count($collections_to_update),
            'units_found' => $units_found,
            'deducted_by_type' => $deducted_by_type
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// --- Replace the update_delivering POST handler to use deduction logic ---
if (isset($_POST['update_delivering'])) {
    $request_id = $_POST['request_id'];
    $result = updateBloodRequestAndInventory($request_id);
    if ($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
}

// Update to completed status
if (isset($_POST['update_completed'])) {
    $request_id = $_POST['request_id'];
    
    // OPTIMIZATION: Use enhanced API function for status update
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

// OPTIMIZATION: Fetch blood requests with enhanced performance
$handover_requests = fetchHandoverRequests();

// Filter by status if GET parameter is set
$filter_status = isset($_GET['status']) ? strtolower($_GET['status']) : '';
if ($filter_status === 'accepted') {
    $handover_requests = array_filter($handover_requests, function($req) {
        return isset($req['status']) && ($req['status'] === 'Accepted' || $req['status'] === 'Printed');
    });
} elseif ($filter_status === 'handedover') {
    $handover_requests = array_filter($handover_requests, function($req) {
        return isset($req['status']) && $req['status'] === 'Confirmed';
    });
} elseif ($filter_status === 'declined') {
    $handover_requests = array_filter($handover_requests, function($req) {
        return isset($req['status']) && $req['status'] === 'Declined';
    });
}

// OPTIMIZATION: Performance logging and caching headers
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
addPerformanceHeaders($executionTime, count($handover_requests), "Handed Over - Filter: $filter_status");
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

/* Donor Management Section */
#donorManagementCollapse {
    margin-top: 2px;
    border: none;
}

#donorManagementCollapse .nav-link {
    color: #666;
    padding: 8px 15px 8px 40px;
}

#donorManagementCollapse .nav-link:hover {
    color: #dc3545;
    font-weight: 600;
    background-color: transparent;
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
                    <i class="fas fa-plus me-2"></i>Register Donor
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <div class="sidebar-main-content">
                    <div class="d-flex align-items-center ps-1 mb-4 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" style="width: 65px; height: 65px; object-fit: contain;">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                    <ul class="nav flex-column">
                        <a href="dashboard-Inventory-System.php" class="nav-link">
                            <span><i class="fas fa-home me-2"></i>Home</span>
                        </a>
                        
                        <a href="dashboard-Inventory-System-list-of-donations.php" class="nav-link">
                            <span><i class="fas fa-users me-2"></i>Donor Management</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                            <span><i class="fas fa-tint me-2"></i>Blood Bank</span>
                        </a>
                        
                        <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link">
                            <span><i class="fas fa-list"></i>Hospital Requests</span>
                        </a>
                        
                        <a href="#" class="nav-link">
                            <span><i class="fas fa-chart-line me-2"></i>Forecast Reports</span>
                        </a>
                        <a href="#" class="nav-link">
                            <span><i class="fas fa-user-cog me-2"></i>Manage Users</span>
                        </a>
                    </ul>
                </div>
                
                <div class="logout-container">
                    <a href="../../assets/php_func/logout.php" class="nav-link logout-link">
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

            <div class="container-fluid p-3 email-container">
                    <h2 class="text-left">
                        <?php
                            if ($filter_status === 'accepted') {
                                echo 'Approved Requests';
                            } elseif ($filter_status === 'handedover') {
                                echo 'Handed Over Requests';
                            } elseif ($filter_status === 'declined') {
                                echo 'Declined Requests';
                            } else {
                                echo 'List of Handed Over Requests';
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
                                        <option value="date">Date</option>
                                    </select>
                                    <input type="text" 
                                        class="form-control" 
                                        id="searchInput" 
                                        placeholder="Search handovers...">
                                </div>
                                <div id="searchInfo" class="mt-2 small text-muted"></div>
                            </div>
                        </div>
                    </div>
                     <!-- Divider Line -->
                <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>No.</th>
                                    <th>Patient Name</th>
                                    <th>Blood Type</th>
                                    <th>Units</th>
                                    <th>Hospital</th>
                                    <?php if ($filter_status !== 'declined'): ?>
                                        <?php if ($filter_status !== 'accepted' && $filter_status !== 'handedover'): ?>
                                            <th>Doctor</th>
                                            <th>Reason</th>
                                        <?php elseif ($filter_status === 'accepted' || $filter_status === 'handedover'): ?>
                                            <!-- No Doctor/Reason columns for approved/handed over -->
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <th>Reason for Declining</th>
                                    <?php endif; ?>
                                    <?php if ($filter_status === 'handedover'): ?>
                                        <th>Pickup Date</th>
                                    <?php endif; ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($handover_requests)): ?>
                                <tr>
                                    <td colspan="<?php 
                                        $colspan = 6; // No., Patient Name, Blood Type, Units, Hospital, Actions
                                        if ($filter_status === 'handedover') {
                                            $colspan += 1; // + Pickup Date column
                                        } elseif ($filter_status === 'declined') {
                                            $colspan += 1; // + Reason column
                                        } else {
                                            $colspan += 2; // + Doctor, Reason columns
                                        }
                                        echo $colspan;
                                    ?>" class="text-center">No handover requests found</td>
                                </tr>
                                <?php else: ?>
                                    <?php $rowNum = 1; foreach ($handover_requests as $request): ?>
                                    <?php $status = isset($request['status']) ? $request['status'] : ''; ?>
                                    <tr>
                                        <td><?php echo $rowNum++; ?></td>
                                        <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['patient_blood_type']) . (strtolower($request['rh_factor']) == 'positive' ? '+' : '-'); ?></td>
                                        <td><?php echo $request['units_requested']; ?></td>
                                        <td><?php echo htmlspecialchars($request['hospital_admitted']); ?></td>
                                        <?php if ($filter_status !== 'declined'): ?>
                                            <?php if ($filter_status !== 'accepted' && $filter_status !== 'handedover'): ?>
                                                <td><?php echo htmlspecialchars($request['physician_name']); ?></td>
                                                <td><?php echo isset($request['decline_reason']) ? htmlspecialchars($request['decline_reason']) : '-'; ?></td>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <td><?php echo isset($request['decline_reason']) ? htmlspecialchars($request['decline_reason']) : '-'; ?></td>
                                        <?php endif; ?>
                                        <?php if ($filter_status === 'handedover'): ?>
                                            <td><?php echo isset($request['last_updated']) ? date('Y-m-d', strtotime($request['last_updated'])) : '-'; ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info view-details" data-request='<?php echo json_encode($request); ?>' title="Request Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php 
                                            // Show appropriate action buttons based on status
                                            if ($status === 'Printed'): 
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
            
            <!-- Referral Blood Shipment Record Modal (Unified Details View) -->
            <div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div id="alertContainer"></div>
                    <div class="modal-content" style="border-radius: 10px; border: none;">
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
                                    <span id="modalRequestStatus" class="badge" style="background: #28a745; padding: 8px 12px; font-size: 0.9rem;">Pending</span>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="margin-left: 10px;"></button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-body" style="padding: 30px;">
                            <form id="requestDetailsForm">
                                <input type="hidden" id="modalRequestId" name="request_id">
                                <div class="mb-4">
                                    <h5 style="font-weight: bold; color: #333; margin-bottom: 15px;">Patient Information</h5>
                            <div class="row">
                                        <div class="col-md-8">
                                            <h4 style="font-weight: bold; color: #000; margin-bottom: 5px;" id="modalPatientName">-</h4>
                                            <p style="color: #666; margin: 0; font-size: 1.05rem;" id="modalPatientDetails">-</p>
                                            <div class="d-flex gap-4 mt-2" style="color:#444;">
                                                <div><span class="fw-bold">Age:</span> <span id="modalPatientAge">-</span></div>
                                                <div><span class="fw-bold">Sex/Gender:</span> <span id="modalPatientGender">-</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr style="border-color: #ddd; margin: 20px 0;">
                                <div class="mb-4">
                                    <h5 style="font-weight: bold; color: #333; margin-bottom: 20px;">Request Details</h5>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Diagnosis:</label>
                                        <input type="text" class="form-control" id="modalDiagnosis" readonly style="border: 1px solid #ddd; padding: 10px;">
                                    </div>
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
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">When Needed:</label>
                                        <div class="d-flex align-items-center gap-4" style="border:1px solid #ddd; border-radius:6px; padding:10px 12px;">
                                            <div class="form-check d-flex align-items-center gap-2 me-3">
                                                <input class="form-check-input" type="radio" name="whenNeededOption" id="asapRadio" value="asap">
                                                <label class="form-check-label fw-bold" for="asapRadio">ASAP</label>
                                            </div>
                                            <div class="form-check d-flex align-items-center gap-2">
                                                <input class="form-check-input" type="radio" name="whenNeededOption" id="scheduledRadio" value="scheduled">
                                                <label class="form-check-label fw-bold" for="scheduledRadio">Scheduled</label>
                                                <input type="text" class="form-control" id="modalScheduledDisplay" style="width: 240px; margin-left: 10px;" readonly>
                                            </div>
                                        </div>
                                    </div>
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
                                    <div id="approvalSection" style="display: none;">
                                        <hr style="border-color: #ddd; margin: 20px 0;">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Approved by:</label>
                                            <input type="text" class="form-control" id="modalApprovedBy" readonly style="border: 1px solid #ddd; padding: 10px; background: #f8f9fa;">
                                </div>
                            </div>
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
                        <div class="modal-footer" style="padding: 20px 30px; border-top: 1px solid #ddd; background: #f8f9fa; border-radius: 0 0 10px 10px;">
                            <div class="d-flex gap-2 w-100 justify-content-end">
                                <button type="button" class="btn btn-danger" id="declineRequest" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; display: none;">
                                    <i class="fas fa-times-circle me-2"></i>Decline Request
                                </button>
                                <button type="button" class="btn btn-success" id="modalAcceptButton" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; display: none;">
                                    <i class="fas fa-check-circle me-2"></i>Approve Request
                                </button>
                                <button type="button" class="btn btn-primary" id="handOverButton" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; display: none;">
                                    <i class="fas fa-truck me-2"></i>Handed Over
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Update Status Modal for Approved Collapsable -->
            <?php if ($filter_status === 'accepted'): ?>
            <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header" style="background-color: #a80000; color: #fff;">
                            <h5 class="modal-title" id="updateStatusModalLabel">Confirm Blood Release</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <p>The blood request will be <strong>marked as picked up</strong>. This action will deduct the issued blood unit(s) from the inventory.</p>
                                <p>Please <strong>confirm</strong> that the blood has been successfully handed over to the recipient or their representative.</p>
                                <input type="hidden" id="update-request-id" name="request_id">
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="update_delivering" class="btn btn-warning text-white" style="background-color: #ffc107; color: #fff;">Confirm Pickup</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
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

            <?php if (!empty($error_message) && strpos($error_message, 'insufficient') !== false): ?>
            <!-- Insufficient Inventory Modal -->
            <div class="modal fade show" id="insufficientInventoryModal" tabindex="-1" aria-labelledby="insufficientInventoryModalLabel" aria-modal="true" style="display: block; background: rgba(0,0,0,0.5);">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="insufficientInventoryModalLabel">Insufficient Blood Inventory</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="window.location.href=window.location.href;"></button>
                  </div>
                  <div class="modal-body"><?php echo nl2br(htmlspecialchars($error_message)); ?></div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="window.location.href=window.location.href;">Close</button>
                  </div>
                </div>
              </div>
            </div>w2
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('insufficientInventoryModal'));
                modal.show();
              });
            </script>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function loadRequestDetails(request_id, patientName, bloodType, component, rhFactor, unitsNeeded, diagnosis, hospital, physician, priority, status, requestDate, whenNeeded) {
        document.getElementById('modalRequestId').value = request_id;
        document.getElementById('modalRequestDate').textContent = requestDate ? new Date(requestDate).toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'}) : '-';
        document.getElementById('modalPatientName').textContent = patientName || '-';
        document.getElementById('modalPatientDetails').textContent = `${unitsNeeded || '-'}, ${priority || '-'}`;
        document.getElementById('modalDiagnosis').value = diagnosis || '';
        document.getElementById('modalBloodType').value = bloodType || '';
        document.getElementById('modalRhFactor').value = rhFactor || '';
        document.getElementById('modalUnitsNeeded').value = unitsNeeded || '';
        document.getElementById('modalHospital').value = hospital || '';
        document.getElementById('modalPhysician').value = physician || '';
        const asapRadio = document.getElementById('asapRadio');
        const scheduledRadio = document.getElementById('scheduledRadio');
        const scheduledDisplay = document.getElementById('modalScheduledDisplay');
        const isAsap = (priority === 'Urgent' || priority === 'ASAP');
        if (asapRadio && scheduledRadio) { asapRadio.checked = !!isAsap; scheduledRadio.checked = !isAsap; }
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
        const statusBadge = document.getElementById('modalRequestStatus');
        const displayStatus = (status === 'Confirmed') ? 'Handed-Over' : (status || 'Pending');
        statusBadge.textContent = displayStatus;
        switch(displayStatus){
            case 'Pending':
            case 'Rescheduled': statusBadge.style.background = '#ffc107'; statusBadge.style.color = '#000'; break;
            case 'Accepted':
            case 'Approved': statusBadge.style.background = '#28a745'; statusBadge.style.color = '#fff'; break;
            case 'Declined': statusBadge.style.background = '#dc3545'; statusBadge.style.color = '#fff'; break;
            case 'Handed-Over': case 'Handed Over': case 'Confirmed': statusBadge.style.background = '#ffc107'; statusBadge.style.color = '#000'; break;
            default: statusBadge.style.background = '#6c757d'; statusBadge.style.color = '#fff';
        }
        const acceptButton = document.getElementById('modalAcceptButton');
        const declineButton = document.getElementById('declineRequest');
        const handOverButton = document.getElementById('handOverButton');
        const approvalSection = document.getElementById('approvalSection');
        const handoverSection = document.getElementById('handoverSection');
        if (approvalSection) approvalSection.style.display = 'none';
        if (handoverSection) handoverSection.style.display = 'none';
        if (['Pending','Rescheduled'].includes(displayStatus)) {
            if (acceptButton) acceptButton.style.display = 'inline-block';
            if (declineButton) declineButton.style.display = 'inline-block';
            if (handOverButton) handOverButton.style.display = 'none';
        } else if (['Accepted','Approved'].includes(displayStatus)) {
            if (acceptButton) acceptButton.style.display = 'none';
            if (declineButton) declineButton.style.display = 'none';
            if (handOverButton) handOverButton.style.display = 'inline-block';
            if (approvalSection) { approvalSection.style.display = 'block'; document.getElementById('modalApprovedBy').value = `Approved by ${physician || 'Staff'}`; }
        } else if (['Handed-Over','Handed Over','Confirmed'].includes(displayStatus)) {
            if (acceptButton) acceptButton.style.display = 'none';
            if (declineButton) declineButton.style.display = 'none';
            if (handOverButton) handOverButton.style.display = 'none';
            if (handoverSection) { handoverSection.style.display = 'block'; document.getElementById('modalHandedOverBy').value = `Handed over by ${physician || 'Staff'}`; }
                    } else {
            if (acceptButton) acceptButton.style.display = 'none';
            if (declineButton) declineButton.style.display = 'none';
            if (handOverButton) handOverButton.style.display = 'none';
        }
        new bootstrap.Modal(document.getElementById('requestModal')).show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const table = document.querySelector('table');
        if (table) {
            table.addEventListener('click', function(e) {
                const btn = e.target.closest('.view-details');
                if (!btn) return;
                let r = {};
                try { r = JSON.parse(btn.getAttribute('data-request')); } catch(_) {}
                const component = 'Whole Blood';
                // Store current request for age/gender population in loader
                window.__currentRequest = r;
                const priority = r.is_asap ? 'Urgent' : 'Routine';
                loadRequestDetails(
                    String(r.request_id || ''),
                    r.patient_name || '-',
                    r.patient_blood_type || '',
                    component,
                    r.rh_factor || '',
                    String(r.units_requested || ''),
                    r.patient_diagnosis || '',
                    r.hospital_admitted || '',
                    r.physician_name || '',
                    priority,
                    r.status || '',
                    r.requested_on || '',
                    r.when_needed || '',
                    r.patient_age || '',
                    r.patient_gender || ''
                );
            });
        }
        document.querySelectorAll('.update-status').forEach(function(btn){
            btn.addEventListener('click', function(){
                var requestId = btn.getAttribute('data-request-id');
                var input = document.getElementById('update-request-id');
                if (input) input.value = requestId;
                var modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
                modal.show();
            });
        });
    });
    </script>
</body>
</html>