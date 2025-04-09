<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/check_account_hospital_modal.php';

// Function to fetch all blood requests from Supabase, ordered by status (approved first) and date
function fetchBloodRequests($user_id) {
    $ch = curl_init();
    
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    
    // Order by status (approved first) and then by requested_on in descending order
    $url = SUPABASE_URL . '/rest/v1/blood_requests?user_id=eq.' . $user_id . '&order=status.desc,requested_on.desc';
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    
    return json_decode($response, true);
}

// Function to update blood request status and deduct inventory
function updateBloodRequestAndInventory($request_id) {
    try {
        $ch = curl_init();
        
        // Log the start of the process
        error_log("Starting blood request confirmation process for request ID: " . $request_id);
        
        $headers = [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        // First, get the blood request details
        $request_url = SUPABASE_URL . '/rest/v1/blood_requests?request_id=eq.' . $request_id;
        error_log("Fetching blood request details from: " . $request_url);
        
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log("Blood request fetch response code: " . $http_code);
        error_log("Blood request fetch response: " . $response);
        
        if ($http_code !== 200) {
            throw new Exception("Failed to fetch blood request. HTTP Code: " . $http_code . ", Response: " . $response);
        }

        $request_data = json_decode($response, true);
        if (empty($request_data)) {
            throw new Exception("No blood request found with ID: " . $request_id);
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error for blood request: " . json_last_error_msg());
        }
        
        $request_data = $request_data[0];
        error_log("Blood request data: " . json_encode($request_data));

        // Get blood type and RH factor
        $requested_blood_type = $request_data['patient_blood_type'];
        $requested_rh_factor = $request_data['rh_factor'];
        $units_requested = $request_data['units_requested'];
        $blood_type_full = $requested_blood_type . ($requested_rh_factor === 'Positive' ? '+' : '-');

        error_log("Processing request for blood type: " . $blood_type_full . ", units: " . $units_requested);

        // Get blood collections
        $collections_url = SUPABASE_URL . '/rest/v1/blood_collection';
        $collections_url .= '?select=blood_collection_id,amount_taken,screening_id,unit_serial_number,blood_bag_brand,blood_bag_type,created_at,is_successful,screening_form(blood_type)';
        $collections_url .= '&is_successful=eq.true';
        $collections_url .= '&amount_taken=gt.0';
        $collections_url .= '&order=created_at.asc';
        
        error_log("Fetching blood collections from: " . $collections_url);
        
        curl_setopt($ch, CURLOPT_URL, $collections_url);
        $collections_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log("Blood collections fetch response code: " . $http_code);
        error_log("Blood collections fetch response: " . $collections_response);
        
        if ($http_code !== 200) {
            throw new Exception("Failed to fetch blood collections. HTTP Code: " . $http_code . ", Response: " . $collections_response);
        }

        $available_collections = json_decode($collections_response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error for collections: " . json_last_error_msg());
        }

        error_log("Found " . count($available_collections) . " total blood collections");

        // Get compatible blood types
        $compatible_types = getCompatibleBloodTypes($requested_blood_type, $requested_rh_factor);
        error_log("Compatible blood types: " . json_encode($compatible_types));

        // Filter collections by blood type compatibility and track units
        $matching_collections = [];
        $units_found = 0;
        $collections_to_update = [];
        $remaining_units = $units_requested;
        $deducted_by_type = []; // Track how many units we take from each blood type

        // First try exact matches
        foreach ($available_collections as $collection) {
            if ($remaining_units <= 0) break;

            error_log("Processing collection ID: " . $collection['blood_collection_id']);
            error_log("Collection data: " . json_encode($collection));
            
            // Get blood type from screening form
            $collection_blood_type = $collection['screening_form']['blood_type'];
            
            error_log("Comparing blood types - Collection: " . $collection_blood_type . " vs Requested: " . $blood_type_full);

            if ($collection_blood_type === $blood_type_full) {
                $available_units = floatval($collection['amount_taken']);
                error_log("Found matching collection {$collection['blood_collection_id']} with {$available_units} units available");

                if ($available_units > 0) {
                    $units_to_take = min($available_units, $remaining_units);
                    $units_found += $units_to_take;
                    $remaining_units -= $units_to_take;

                    // Track units deducted by blood type
                    if (!isset($deducted_by_type[$collection_blood_type])) {
                        $deducted_by_type[$collection_blood_type] = 0;
                    }
                    $deducted_by_type[$collection_blood_type] += $units_to_take;

                    // Store how many units we're taking from this collection
                    $collection['units_to_take'] = $units_to_take;
                    $collections_to_update[] = $collection;

                    error_log("Taking {$units_to_take} units from collection {$collection['blood_collection_id']}, remaining needed: {$remaining_units}");
                }
            }
        }

        // If we still need units, try compatible types in order of priority
        if ($remaining_units > 0) {
            error_log("Still need {$remaining_units} units, checking compatible types");
            
            foreach ($compatible_types as $compatible_type) {
                if ($remaining_units <= 0) break;
                
                $compatible_blood_type = $compatible_type['type'] . ($compatible_type['rh'] === 'Positive' ? '+' : '-');
                error_log("Checking compatible type: " . $compatible_blood_type . " (Priority: " . $compatible_type['priority'] . ")");
                
                foreach ($available_collections as $collection) {
                    if ($remaining_units <= 0) break;
                    
                    $collection_blood_type = $collection['screening_form']['blood_type'];
                    
                    if ($collection_blood_type === $compatible_blood_type && 
                        !in_array($collection['blood_collection_id'], array_column($collections_to_update, 'blood_collection_id'))) {
                        
                        $available_units = floatval($collection['amount_taken']);
                        
                        if ($available_units > 0) {
                            $units_to_take = min($available_units, $remaining_units);
                            $units_found += $units_to_take;
                            $remaining_units -= $units_to_take;

                            // Track units deducted by blood type
                            if (!isset($deducted_by_type[$collection_blood_type])) {
                                $deducted_by_type[$collection_blood_type] = 0;
                            }
                            $deducted_by_type[$collection_blood_type] += $units_to_take;

                            $collection['units_to_take'] = $units_to_take;
                            $collections_to_update[] = $collection;

                            error_log("Taking {$units_to_take} units from compatible collection {$collection['blood_collection_id']} ({$collection_blood_type}), remaining needed: {$remaining_units}");
                        }
                    }
                }
            }
        }

        error_log("Final units found: {$units_found}, remaining needed: {$remaining_units}");
        error_log("Units deducted by blood type: " . json_encode($deducted_by_type));

        if ($units_found < $units_requested) {
            // Create a detailed error message
            $shortage = $units_requested - $units_found;
            $error_message = "Unable to fulfill blood request due to insufficient inventory.\n\n";
            $error_message .= "Request Details:\n";
            $error_message .= "• Requested Blood Type: {$blood_type_full}\n";
            $error_message .= "• Units Requested: {$units_requested}\n";
            $error_message .= "• Units Available: {$units_found}\n";
            $error_message .= "• Shortage: {$shortage} units\n\n";

            // Add information about what blood types were found
            if (!empty($deducted_by_type)) {
                $error_message .= "Available Blood Types Found:\n";
                foreach ($deducted_by_type as $type => $amount) {
                    $error_message .= "• {$type}: {$amount} units\n";
                }
            }

            // Add compatible blood types information
            $error_message .= "\nCompatible Blood Types:\n";
            foreach ($compatible_types as $compatible) {
                $compatible_blood = $compatible['type'] . ($compatible['rh'] === 'Positive' ? '+' : '-');
                $error_message .= "• {$compatible_blood}\n";
            }

            // Add recommendations
            $error_message .= "\nRecommendations:\n";
            $error_message .= "1. Consider requesting a smaller quantity\n";
            $error_message .= "2. Check back later as blood inventory is updated regularly\n";
            $error_message .= "3. For urgent cases, contact the blood bank directly\n";

            return [
                'success' => false,
                'message' => $error_message,
                'error_type' => 'INSUFFICIENT_INVENTORY',
                'requested_blood_type' => $blood_type_full,
                'units_requested' => $units_requested,
                'units_found' => $units_found,
                'shortage' => $shortage,
                'deducted_by_type' => $deducted_by_type,
                'compatible_types' => array_map(function($type) {
                    return $type['type'] . ($type['rh'] === 'Positive' ? '+' : '-');
                }, $compatible_types)
            ];
        }

        // Update blood collections to deduct the used units
        foreach ($collections_to_update as $collection) {
            $update_data = json_encode([
                'amount_taken' => 1,
                'status' => 'picked_up',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            error_log("Marking collection {$collection['blood_collection_id']} as confirmed and setting amount to 1");
            
            $update_url = SUPABASE_URL . '/rest/v1/blood_collection';
            $update_url .= '?blood_collection_id=eq.' . $collection['blood_collection_id'];
            
            error_log("Update URL: " . $update_url);
            error_log("Update data: " . $update_data);
            
            curl_setopt($ch, CURLOPT_URL, $update_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $update_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            
            $update_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            error_log("Updated blood collection {$collection['blood_collection_id']}, HTTP code: {$http_code}");
            error_log("Update response: " . $update_response);
            
            if ($http_code !== 200 && $http_code !== 204) {
                throw new Exception("Failed to update blood collection {$collection['blood_collection_id']}. HTTP Code: {$http_code}, Response: {$update_response}");
            }
        }

        // Update request status to Picked up
        $request_update_data = json_encode([
            'status' => 'Confirmed',
            'last_updated' => date('Y-m-d H:i:s')
        ]);
        
        error_log("Updating request status with data: " . $request_update_data);
        
        $update_url = SUPABASE_URL . '/rest/v1/blood_requests';
        $update_url .= '?request_id=eq.' . $request_id;
        
        error_log("Update URL: " . $update_url);
        
        curl_setopt($ch, CURLOPT_URL, $update_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_update_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        
        $request_update = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log("Status update response code: " . $http_code);
        error_log("Status update response: " . $request_update);
        
        if ($http_code !== 200 && $http_code !== 204) {
            throw new Exception('Failed to update request status. HTTP Code: ' . $http_code . ', Response: ' . $request_update);
        }
        
        curl_close($ch);

        // Create detailed success message
        $detailed_message = "Successfully processed blood request #{$request_id}:\n";
        $detailed_message .= "- Requested blood type: {$blood_type_full}\n";
        $detailed_message .= "- Total units deducted: {$units_requested}\n\n";

        return [
            'success' => true,
            'message' => 'Blood request completed successfully',
            'detailed_message' => $detailed_message,
            'units_deducted' => $units_requested,
            'blood_type' => $blood_type_full,
            'collections_updated' => count($collections_to_update),
            'units_found' => $units_found,
            'deducted_by_type' => $deducted_by_type
        ];
    } catch (Exception $e) {
        error_log("Error in updateBloodRequestAndInventory: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "An error occurred: " . $e->getMessage()
        ];
    }
}

// Helper function to get compatible blood types based on recipient's blood type
// Returns an array of compatible blood types in order of priority
function getCompatibleBloodTypes($blood_type, $rh_factor) {
    $is_positive = $rh_factor === 'Positive';
    $compatible_types = [];
    
    // O- is universal donor and should be considered for all types, but with different priorities
    switch ($blood_type) {
        case 'O':
            if ($is_positive) {
                // O+ can receive from: O+, O-
                $compatible_types = [
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2], // First try O+
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]  // Then O- (universal donor)
                ];
            } else {
                // O- can only receive from O-
                $compatible_types = [
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
            
        case 'A':
            if ($is_positive) {
                // A+ can receive from: A+, A-, O+, O-
                $compatible_types = [
                    ['type' => 'A', 'rh' => 'Positive', 'priority' => 4], // First try A+
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 3], // Then A-
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2], // Then O+
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]  // Finally O-
                ];
            } else {
                // A- can receive from: A-, O-
                $compatible_types = [
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 2], // First try A-
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]  // Then O-
                ];
            }
            break;
            
        case 'B':
            if ($is_positive) {
                // B+ can receive from: B+, B-, O+, O-
                $compatible_types = [
                    ['type' => 'B', 'rh' => 'Positive', 'priority' => 4], // First try B+
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 3], // Then B-
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2], // Then O+
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]  // Finally O-
                ];
            } else {
                // B- can receive from: B-, O-
                $compatible_types = [
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 2], // First try B-
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]  // Then O-
                ];
            }
            break;
            
        case 'AB':
            if ($is_positive) {
                // AB+ can receive from anyone (universal recipient)
                $compatible_types = [
                    ['type' => 'AB', 'rh' => 'Positive', 'priority' => 8], // Try exact match first
                    ['type' => 'AB', 'rh' => 'Negative', 'priority' => 7],
                    ['type' => 'A', 'rh' => 'Positive', 'priority' => 6],
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 5],
                    ['type' => 'B', 'rh' => 'Positive', 'priority' => 4],
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            } else {
                // AB- can receive from all negative types
                $compatible_types = [
                    ['type' => 'AB', 'rh' => 'Negative', 'priority' => 4], // Try exact match first
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
    }
    
    // Sort by priority (lower number = higher priority)
    usort($compatible_types, function($a, $b) {
        return $a['priority'] - $b['priority'];
    });
    
    return $compatible_types;
}

// Handle AJAX request for blood confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm') {
    header('Content-Type: application/json');
    
    try {
        $request_id = $_POST['request_id'] ?? null;
        
        if (!$request_id) {
            throw new Exception('Request ID is required');
        }
        
        $result = updateBloodRequestAndInventory($request_id);
        echo json_encode($result);
        
    } catch (Exception $e) {
        error_log("Error processing confirmation request: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Fetch blood requests for the current user
$blood_requests = fetchBloodRequests($_SESSION['user_id']);

// Sort the blood requests to match the desired order
usort($blood_requests, function($a, $b) {
    // Priority: Approved/Accepted first
    if (($a['status'] === 'Approved' || $a['status'] === 'Accepted') && 
        ($b['status'] !== 'Approved' && $b['status'] !== 'Accepted')) {
        return -1;
    }
    if (($a['status'] !== 'Approved' && $a['status'] !== 'Accepted') && 
        ($b['status'] === 'Approved' || $b['status'] === 'Accepted')) {
        return 1;
    }
    
    // Priority: Pending second
    if ($a['status'] === 'Pending' && $b['status'] !== 'Pending' && 
        $b['status'] !== 'Approved' && $b['status'] !== 'Accepted') {
        return -1;
    }
    if ($a['status'] !== 'Pending' && $a['status'] !== 'Approved' && 
        $a['status'] !== 'Accepted' && $b['status'] === 'Pending') {
        return 1;
    }
    
    // Priority: Declined last
    if ($a['status'] === 'Declined' && $b['status'] !== 'Declined') {
        return 1;
    }
    if ($a['status'] !== 'Declined' && $b['status'] === 'Declined') {
        return -1;
    }
    
    // For same status, sort by date (newest first)
    return strtotime($b['requested_on']) - strtotime($a['requested_on']);
});

// Calculate summary statistics
$total_units = 0;
$total_picked_up = 0;
$blood_type_counts = [];
$completed_requests = [];

if (!empty($blood_requests)) {
    foreach ($blood_requests as $request) {
        $total_units += $request['units_requested'];
        
        // Count picked up units
        if ($request['status'] === 'Confirmed') {
            $total_picked_up += $request['units_requested'];
        }
        
        // Count blood types
        $blood_type = $request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-');
        $blood_type_counts[$blood_type] = ($blood_type_counts[$blood_type] ?? 0) + 1;
        
        // Track completed requests
        if ($request['status'] === 'Completed') {
            $completed_requests[] = $request;
        }
    }
}

// Find most requested blood type
$most_requested_type = !empty($blood_type_counts) ? array_search(max($blood_type_counts), $blood_type_counts) : 'N/A';

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
    <!-- Signature Pad Library -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
    <style>
        /* General Body Styling */
        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        /* Red Cross Theme Colors */
        :root {
            --redcross-red: #941022;
            --redcross-dark: #7a0c1c;
            --redcross-light-red: #b31b2c;
            --redcross-gray: #6c757d;
            --redcross-light: #f8f9fa;
        }

        /* Card Styling */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            height: 100%;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-title {
            color: var(--redcross-red);
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .card-text {
            color: var(--redcross-dark);
        }

        .card-body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 1.5rem;
        }

        .card .fs-3 {
            font-weight: bold;
            margin: 0.5rem 0;
        }


        /* Button Styling */
        .btn-danger {
            background-color: var(--redcross-red);
            border-color: var(--redcross-red);
            color: white;
        }

        .btn-danger:hover {
            background-color: var(--redcross-dark);
            border-color: var(--redcross-dark);
            color: white;
        }

        /* Table Styling */
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(148, 16, 34, 0.05);
        }

        .table thead th {
            background-color: var(--redcross-red);
            color: white;
            border-bottom: none;
            font-size: inherit;
        }

        .table td {
            font-size: inherit;
        }

        /* Status Colors */
        .text-approved {
            color: #006400 !important;
            font-weight: bold;
        }

        .text-danger {
            color: var(--redcross-red) !important;
            font-weight: bold;
        }

        .text-success {
            color: #198754 !important;
            font-weight: bold;
        }
        
        .text-warning {
            color: #FFC107 !important;
            font-weight: bold;
        }

        /* Sidebar Active State */
        .dashboard-home-sidebar a.active, 
        .dashboard-home-sidebar a:hover {
            background-color: #e9ecef;
            color: #333;
            font-weight: bold;
        }

        /* Search Bar */
        .form-control:focus {
            border-color: var(--redcross-red);
            box-shadow: 0 0 0 0.2rem rgba(148, 16, 34, 0.25);
        }

        /* Header Title */
        .card-title.mb-3 {
            color: var(--redcross-red);
            font-weight: bold;
            border-bottom: 2px solid var(--redcross-red);
            padding-bottom: 0.5rem;
        }

        /* Reduce Left Margin for Main Content */
        main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
            margin-left: 280px !important;
        }
       /* Header */
       .dashboard-home-header {
            position: fixed;
            top: 0;
            left: 280px;
            width: calc(100% - 280px);
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
            width: 280px;
            background-color: #ffffff;
            border-right: 1px solid #ddd;
            padding: 20px;
            transition: width 0.3s ease;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .dashboard-home-sidebar .nav-link {
            color: #333;
            padding: 12px 15px;
            margin: 4px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .dashboard-home-sidebar .nav-link:hover {
            background-color: #e9ecef;
            color: #333;
            transform: translateX(5px);
        }
        .dashboard-home-sidebar .nav-link.active {
            background-color: #941022;
            color: white;
        }
        .dashboard-home-sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }
        /* Search Box Styling */
        .search-box .input-group-text,
        .search-box .form-control {
            padding-top: 15px;    /* Increased vertical padding */
            padding-bottom: 15px; /* Increased vertical padding */
            height: auto;
        }

        .search-box .input-group {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .search-box .input-group-text {
            border-right: none;
            padding-left: 15px;  /* Maintain horizontal padding */
            padding-right: 15px; /* Maintain horizontal padding */
        }

        .search-box .form-control {
            border-left: none;
            padding-left: 0;     /* Keep the left padding at 0 for alignment */
            padding-right: 15px; /* Maintain right padding */
        }

        .search-box .form-control:focus {
            box-shadow: none;
            border-color: #dee2e6;
        }
        /* Logo and Title Styling */
        .dashboard-home-sidebar img {
            transition: transform 0.3s ease;
        }
        .dashboard-home-sidebar img:hover {
            transform: scale(1.05);
        }
        .dashboard-home-sidebar h5 {
            font-weight: 600;
        }
        /* Scrollbar Styling */
        .dashboard-home-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .dashboard-home-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .dashboard-home-sidebar::-webkit-scrollbar-thumb {
            background: #941022;
            border-radius: 3px;
        }
        .dashboard-home-sidebar::-webkit-scrollbar-thumb:hover {
            background: #7a0c1c;
        }
        /* Main Content Styling */
        .dashboard-home-main {
            margin-left: 280px;
            margin-top: 70px;
            min-height: 100vh;
            overflow-x: hidden;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .custom-margin {
            margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin-top: 100px;
}
        .chart-container {
            width: 100%;
            height: 400px;
        }
        #scheduleDateTime {
    opacity: 0;
    transition: opacity 0.5s ease-in-out;
}
.sort-indicator{
    cursor: pointer;
}

        /* Loading Spinner - Minimal Addition */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }
        
        .btn-loading .spinner-border {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 1rem;
            height: 1rem;
            display: inline-block;
        }
        
        .btn-loading .btn-text {
            opacity: 0;
        }

        /* Search bar styling */
        #requestSearchBar {
            background-color: #ffffff;
            color: #333333;
            transition: all 0.3s ease;
        }


        #requestSearchBar::placeholder {
            color: #6c757d;
        }

    </style>
</head>
<body>
<div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
            <h4 >Hospital Request Dashboard</h4>
            <!-- Request Blood Button -->
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#bloodRequestModal">Request Blood</button>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        <h3 class="text-danger mb-0"><?php echo $_SESSION['user_first_name']; ?></h3>
                        <small class="text-muted">Hospital Request Dashboard</small>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-hospital-main.php">
                                <i class="fas fa-home me-2"></i>Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-hospital-requests.php">
                                <i class="fas fa-tint me-2"></i>Your Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-hospital-history.php">
                                <i class="fas fa-history me-2"></i>Request History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../../assets/php_func/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>


            <!-- Main Content -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="container-fluid p-4 custom-margin">
                        <h2 class="card-title mb-3">Request History</h2>
                            

                                            <!-- Add search bar -->
                                            <div class="search-box mb-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="requestSearchBar" class="form-control border-start-0 ps-0" 
                                       placeholder="Search by patient name..." 
                                       style="background-color: #ffffff; color: #333333;">
                            </div>
                        </div>
                            <!-- Summary Cards -->
                            <div class="row mb-4 g-3">
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Total Units Requested</h5>
                                            <p class="card-text fs-3"><?php echo $total_units; ?> Units</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Most Requested Blood Type</h5>
                                            <p class="card-text fs-3"><?php echo $most_requested_type; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Total Requests</h5>
                                            <p class="card-text fs-3"><?php echo count($blood_requests); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Total Units Picked-Up</h5>
                                            <p class="card-text fs-3"><?php echo $total_picked_up; ?> Units</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                <!-- Table for Request History -->
                                <table class="table table-bordered table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Patient Name</th>
                                            <th>Age</th>
                                            <th>Gender</th>
                                            <th>Blood Type</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Physician</th>
                                            <th>Requested On</th>
                                            <th>Last Updated</th>
                                        <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="requestTable">
                                        <?php if (empty($blood_requests)): ?>
                                        <tr>
                                        <td colspan="11" class="text-center">No blood requests found.</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($blood_requests as $request): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['patient_age']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['patient_gender']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-')); ?></td>
                                                    <td><?php echo htmlspecialchars($request['units_requested'] . ' Units'); ?></td>
                                                    <td class="<?php 
                                                        if ($request['status'] === 'Approved') {
                                                            echo 'text-approved';
                                                        } elseif ($request['status'] === 'Completed' || $request['status'] === '{Confirmed}') {
                                                            echo 'text-success';
                                                        } elseif ($request['status'] === 'Pending') {
                                                            echo 'text-warning';
                                                        } elseif ($request['status'] === 'Declined') {
                                                            echo 'text-danger';
                                                        } else {
                                                            echo 'text-success';
                                                        }
                                                    ?>">
                                                        <?php echo htmlspecialchars($request['status']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($request['physician_name']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($request['requested_on'])); ?></td>
                                                    <td><?php echo $request['last_updated'] ? date('Y-m-d', strtotime($request['last_updated'])) : '-'; ?></td>
                                                <td>
                                                    <?php if ($request['status'] === 'Approved' || $request['status'] === 'Accepted'): ?>
                                                        <button class="btn btn-success pickup-btn" 
                                                                data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                                                onclick="markAsConfirmed(<?php echo htmlspecialchars($request['request_id']); ?>)">
                                                            <i class="fas fa-check me-1"></i> Print
                                                        </button>
                                                    <?php elseif ($request['status'] === 'Completed'): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                    <?php elseif ($request['status'] === 'Pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($request['status'] === 'Declined'): ?>
                                                        <span class="badge bg-danger decline-reason-badge" style="cursor: pointer;" 
                                                              data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                                              data-bs-toggle="modal" data-bs-target="#declineReasonModal"
                                                              onclick="showDeclineReason(<?php echo htmlspecialchars($request['request_id']); ?>, '<?php echo htmlspecialchars($request['decline_reason'] ?? 'No reason provided'); ?>')">
                                                            Declined
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Printed</span>
                                                    <?php endif; ?>
                                                </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                </main>
            </div>
        </div>



<!-- Blood Request Modal -->
<div class="modal fade" id="bloodRequestModal" tabindex="-1" aria-labelledby="bloodRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bloodRequestModalLabel">Blood Request Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bloodRequestForm">
                    <!-- Patient Information Section -->
                    <h6 class="mb-3 fw-bold">Patient Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control" name="patient_name" required>
                    </div>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control" name="patient_age" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="patient_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" class="form-control" name="patient_diagnosis" placeholder="e.g., T/E, FTE, Septic Shock" required>
                    </div>

                    <!-- Blood Request Details Section -->
                    <h6 class="mb-3 mt-4 fw-bold">Blood Request Details</h6>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Blood Type</label>
                            <select class="form-select" name="patient_blood_type" required>
                                <option value="">Select Type</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="O">O</option>
                                <option value="AB">AB</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label">RH Factor</label>
                            <select class="form-select" name="rh_factor" required>
                                <option value="">Select RH</option>
                                <option value="Positive">Positive</option>
                                <option value="Negative">Negative</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 row gx-3">
                        <div class="col-md-4">
                            <label class="form-label">Component</label>
                            <input type="hidden" name="blood_component" value="Whole Blood">
                            <input type="text" class="form-control" value="Whole Blood" readonly style="width: 105%;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Number of Units</label>
                            <input type="number" class="form-control" name="units_requested" min="1" required style="width: 105%;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">When Needed</label>
                            <select id="whenNeeded" class="form-select" name="when_needed" required style="width: 105%;">
                                <option value="ASAP">ASAP</option>
                                <option value="Scheduled">Scheduled</option>
                            </select>
                        </div>
                    </div>
                    <div id="scheduleDateTime" class="mb-3 d-none">
                        <label class="form-label">Scheduled Date & Time</label>
                        <input type="datetime-local" class="form-control" name="scheduled_datetime">
                    </div>

                    <!-- Additional Information Section -->
                    <h6 class="mb-3 mt-4 fw-bold">Additional Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Hospital Admitted</label>
                        <input type="text" class="form-control" name="hospital_admitted" value="<?php echo $_SESSION['user_first_name'] ?? ''; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Requesting Physician</label>
                        <input type="text" class="form-control" name="physician_name" value="<?php echo $_SESSION['user_surname'] ?? ''; ?>" readonly>
                    </div>

                    <!-- File Upload and Signature Section -->
                    <h6 class="mb-3 mt-4 fw-bold">Supporting Documents & Signature</h6>
                    <div class="mb-3">
                        <label class="form-label">Upload Supporting Documents (Images only)</label>
                        <input type="file" class="form-control" name="supporting_docs[]" accept="image/*" multiple>
                        <small class="text-muted">Accepted formats: .jpg, .jpeg, .png</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Physician's Signature</label>
                        <div class="signature-method-selector mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="signature_method" id="uploadSignature" value="upload" checked>
                                <label class="form-check-label" for="uploadSignature">Upload Signature</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="signature_method" id="drawSignature" value="draw">
                                <label class="form-check-label" for="drawSignature">Draw Signature</label>
                            </div>
                        </div>

                        <div id="signatureUpload" class="mb-3">
                            <input type="file" class="form-control" name="signature_file" accept="image/*">
                        </div>

                        <div id="signaturePad" class="d-none">
                            <div class="border rounded p-3 mb-2">
                                <canvas id="physicianSignaturePad" class="w-100" style="height: 200px; border: 1px solid #dee2e6;"></canvas>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" id="clearSignature">Clear</button>
                                <button type="button" class="btn btn-primary btn-sm" id="saveSignature">Save Signature</button>
                            </div>
                            <input type="hidden" name="signature_data" id="signatureData">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Decline Reason Modal -->
<div class="modal fade" id="declineReasonModal" tabindex="-1" aria-labelledby="declineReasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="declineReasonModalLabel">Request Declined</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 class="fw-bold">Reason for Decline:</h6>
                    <div id="declineReasonText" class="p-3 bg-light rounded">
                        <!-- Decline reason will be inserted here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

     <script>
      document.addEventListener('DOMContentLoaded', function() {
          // Search functionality - focus on patient names
          const searchBar = document.getElementById('requestSearchBar');
          searchBar.addEventListener('keyup', function() {
              const searchText = this.value.toLowerCase();
              const table = document.getElementById('requestTable');
              const rows = table.getElementsByTagName('tr');

              for (let row of rows) {
                  // Get the patient name (2nd column)
                  const patientNameCell = row.querySelector('td:nth-child(2)');
                  
                  if (patientNameCell) {
                      const patientName = patientNameCell.textContent.toLowerCase();
                      
                      // Show row if patient name contains search text, hide otherwise
                      if (patientName.includes(searchText)) {
                          row.style.display = '';
                      } else {
                          row.style.display = 'none';
                      }
                  }
              }
          });

          // Add focus styles for search bar
          searchBar.addEventListener('focus', function() {
              this.style.boxShadow = '0 0 0 0.2rem rgba(148, 16, 34, 0.25)';
          });

          searchBar.addEventListener('blur', function() {
              this.style.boxShadow = 'none';
          });
      });
     </script>

<script>
function markAsConfirmed(requestId) {
    if (!confirm('Are you sure you want to mark this blood request as confirmed? This will deduct the units from inventory.')) {
        return;
    }
    
    // Find and update the button state
    const button = document.querySelector(`button[data-request-id="${requestId}"]`);
    if (!button) return;

    // Store original button content and disable it
    const originalContent = button.innerHTML;
    button.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`;
    button.disabled = true;

    // Create form data
    const formData = new FormData();
    formData.append('action', 'confirm');
    formData.append('request_id', requestId);

    // Make the request
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show detailed success message
            const modalContent = `
                <div class="modal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title">Request Confirmed Successfully</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <h6>Request has been confirmed and units have been deducted from inventory.</h6>
                                <p class="mb-0 mt-3"><strong>Details:</strong></p>
                                <pre class="bg-light p-3 mt-2 rounded">${data.detailed_message}</pre>
                                
                                <div class="mt-3 mb-2"><strong>Units Deducted:</strong></div>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        ${data.blood_type}
                                        <span class="badge bg-danger rounded-pill">${data.units_deducted} units</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-success" onclick="window.location.reload()">Reload Page</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Create and show the modal
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalContent;
            document.body.appendChild(modalContainer);
            
            const modal = new bootstrap.Modal(modalContainer.querySelector('.modal'));
            modal.show();
            
            // Update the button
            button.innerHTML = 'Confirmed <i class="bi bi-check-circle-fill"></i>';
            button.classList.remove('btn-success');
            button.classList.add('btn-secondary');
            button.disabled = true;
            
            // Listen for modal hidden event to clean up
            modalContainer.querySelector('.modal').addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modalContainer);
                window.location.reload(); // Reload after modal is closed
            });
        } else {
            // Show error message
            alert('Error: ' + data.message);
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

function showDeclineReason(requestId, reason) {
    // Get the decline reason element
    const reasonElement = document.getElementById('declineReasonText');
    
    // Set reason text
    reasonElement.textContent = reason || 'No specific details provided';
    
    // Fetch additional details if needed (optional enhancement)
    fetch('<?php echo SUPABASE_URL; ?>/rest/v1/blood_requests?request_id=eq.' + requestId, {
        headers: {
            'apikey': '<?php echo SUPABASE_API_KEY; ?>',
            'Authorization': 'Bearer <?php echo SUPABASE_API_KEY; ?>'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data && data.length > 0 && data[0].decline_reason) {
            // Update with the most current reason from the database
            reasonElement.textContent = data[0].decline_reason || 'No specific details provided';
        }
    })
    .catch(error => {
        console.error('Error fetching decline reason:', error);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Initial sort to put "No Action" at bottom
    sortTableByStatus();

    // Search functionality
    const searchBar = document.getElementById('requestSearchBar');
    searchBar.addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const table = document.getElementById('requestTable');
        const rows = table.getElementsByTagName('tr');

        for (let row of rows) {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        }
    });
    
    // Add blood request form submission handler
    const bloodRequestForm = document.getElementById('bloodRequestForm');
    if (bloodRequestForm) {
        bloodRequestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            // Create FormData object
            const formData = new FormData(this);
            
            // Add additional data
            formData.append('user_id', '<?php echo $_SESSION['user_id']; ?>');
            formData.append('status', 'Pending');
            formData.append('physician_name', '<?php echo $_SESSION['user_surname']; ?>');
            formData.append('requested_on', new Date().toISOString());
            
            // Handle "when needed" logic
            const whenNeeded = document.getElementById('whenNeeded').value;
            const isAsap = whenNeeded === 'ASAP';
            formData.append('is_asap', isAsap ? 'true' : 'false');
            
            // Always set when_needed as a timestamp
            if (isAsap) {
                // For ASAP, use current date/time
                formData.set('when_needed', new Date().toISOString());
                } else {
                // For Scheduled, use the selected date/time
                const scheduledDate = document.querySelector('#scheduleDateTime input').value;
                if (scheduledDate) {
                    formData.set('when_needed', new Date(scheduledDate).toISOString());
                } else {
                    // If no date selected for scheduled, default to current date
                    formData.set('when_needed', new Date().toISOString());
                }
            }
            
            // Define exact fields from the database schema
            const validFields = [
                'request_id', 'user_id', 'patient_name', 'patient_age', 'patient_gender', 
                'patient_diagnosis', 'patient_blood_type', 'rh_factor', 'blood_component', 
                'units_requested', 'when_needed', 'is_asap', 'hospital_admitted', 
                'physician_name', 'requested_on', 'status'
            ];
            
            // Convert FormData to JSON object, only including valid fields
            const data = {};
            validFields.forEach(field => {
                if (formData.has(field)) {
                    const value = formData.get(field);
                    
                    // Convert numeric values to numbers
                    if (field === 'patient_age' || field === 'units_requested') {
                        data[field] = parseInt(value, 10);
                    } 
                    // Convert boolean strings to actual booleans
                    else if (field === 'is_asap') {
                        data[field] = value === 'true';
                    }
                    // Format timestamps properly
                    else if (field === 'when_needed' || field === 'requested_on') {
                        try {
                            // Ensure we have a valid date
                            const dateObj = new Date(value);
                            if (isNaN(dateObj.getTime())) {
                                throw new Error(`Invalid date for ${field}: ${value}`);
                            }
                            // Format as ISO string with timezone
                            data[field] = dateObj.toISOString();
                        } catch (err) {
                            console.error(`Error formatting date for ${field}:`, err);
                            // Default to current time if invalid
                            data[field] = new Date().toISOString();
                        }
                    }
                    // All other fields as strings
                    else {
                        data[field] = value;
                    }
                }
            });
            
            console.log('Submitting request data:', data);
            console.log('Valid fields in database:', validFields);
            console.log('FormData keys:', Array.from(formData.keys()));
            console.log('when_needed value:', data.when_needed);
            console.log('requested_on value:', data.requested_on);
            console.log('is_asap value:', data.is_asap);
            
            // Send data to server
            fetch('<?php echo SUPABASE_URL; ?>/rest/v1/blood_requests', {
                method: 'POST',
                headers: {
                    'apikey': '<?php echo SUPABASE_API_KEY; ?>',
                    'Authorization': 'Bearer <?php echo SUPABASE_API_KEY; ?>',
                    'Content-Type': 'application/json',
                    'Prefer': 'return=minimal'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                console.log('Request response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Error response body:', text);
                        // Try to parse as JSON to extract more details
                        try {
                            const errorJson = JSON.parse(text);
                            throw new Error(`Error ${response.status}: ${errorJson.message || errorJson.error || text}`);
                        } catch (jsonError) {
                            // If can't parse as JSON, use the raw text
                            throw new Error(`Error ${response.status}: ${text}`);
                        }
                    });
                }
                return response.text();
            })
            .then(result => {
                console.log('Request submitted successfully:', result);
                
                // Show success message
                alert('Blood request submitted successfully!');
                
                // Reset form and close modal
                bloodRequestForm.reset();
                const modal = bootstrap.Modal.getInstance(document.getElementById('bloodRequestModal'));
                modal.hide();
                
                // Reload the page to show the new request
                window.location.reload();
            })
            .catch(error => {
                console.error('Error submitting request:', error);
                alert('Error submitting request: ' + error.message);
            })
            .finally(() => {
                // Restore button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
    });
});
    }
    
    // Handle when needed change
    const whenNeededSelect = document.getElementById('whenNeeded');
    const scheduleDateTimeDiv = document.getElementById('scheduleDateTime');
    
    if (whenNeededSelect && scheduleDateTimeDiv) {
        whenNeededSelect.addEventListener('change', function() {
            if (this.value === 'Scheduled') {
                scheduleDateTimeDiv.classList.remove('d-none');
                scheduleDateTimeDiv.style.opacity = 1;
                scheduleDateTimeDiv.querySelector('input').required = true;
            } else {
                scheduleDateTimeDiv.style.opacity = 0;
                setTimeout(() => {
                    scheduleDateTimeDiv.classList.add('d-none');
                    scheduleDateTimeDiv.querySelector('input').required = false;
                }, 500);
            }
        });
    }
    
    // Handle signature method toggle
    const uploadSignatureRadio = document.getElementById('uploadSignature');
    const drawSignatureRadio = document.getElementById('drawSignature');
    const signatureUploadDiv = document.getElementById('signatureUpload');
    const signaturePadDiv = document.getElementById('signaturePad');
    
    if (uploadSignatureRadio && drawSignatureRadio) {
        uploadSignatureRadio.addEventListener('change', function() {
            if (this.checked) {
                signatureUploadDiv.classList.remove('d-none');
                signaturePadDiv.classList.add('d-none');
            }
        });
        
        drawSignatureRadio.addEventListener('change', function() {
            if (this.checked) {
                signatureUploadDiv.classList.add('d-none');
                signaturePadDiv.classList.remove('d-none');
                initSignaturePad();
            }
        });
    }
});

// Initialize signature pad
function initSignaturePad() {
    const canvas = document.getElementById('physicianSignaturePad');
    if (!canvas) return;
    
    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'white',
        penColor: 'black'
    });
    
    // Clear button
    document.getElementById('clearSignature').addEventListener('click', function() {
        signaturePad.clear();
    });
    
    // Save button
    document.getElementById('saveSignature').addEventListener('click', function() {
        if (signaturePad.isEmpty()) {
            alert('Please provide a signature first.');
            return;
        }
        
        const signatureData = signaturePad.toDataURL();
        document.getElementById('signatureData').value = signatureData;
        alert('Signature saved!');
    });
    
    // Resize canvas
    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        signaturePad.clear(); // Clear the canvas
    }
    
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
}

function sortTableByStatus() {
    const table = document.getElementById('requestTable');
    const rows = Array.from(table.getElementsByTagName('tr'));
    
    // Sort function that puts rows in order: 
    // 1. Approved/Accepted (with button) first
    // 2. Pending
    // 3. Confirmed (badged)
    // 4. Declined at the very end
    rows.sort((a, b) => {
        const statusA = a.querySelector('td:nth-child(7)')?.textContent.trim();
        const statusB = b.querySelector('td:nth-child(7)')?.textContent.trim();
        const hasButtonA = a.querySelector('button.pickup-btn') !== null;
        const hasButtonB = b.querySelector('button.pickup-btn') !== null;
        
        if (!statusA || !statusB) return 0;
        
        // Priority: buttons first (Approved/Accepted)
        if (hasButtonA && !hasButtonB) return -1;
        if (!hasButtonA && hasButtonB) return 1;
        
        // If neither has buttons, check status
        if (statusA === 'Declined' && statusB !== 'Declined') return 1; // Declined last
        if (statusA !== 'Declined' && statusB === 'Declined') return -1;
        
        if (statusA === 'Confirmed' && statusB === 'Pending') return 1; // Pending before Confirmed
        if (statusA === 'Pending' && statusB === 'Confirmed') return -1;
        
        return 0;
    });
    
    // Re-append rows in new order
    rows.forEach(row => table.appendChild(row));
}
     </script>
</body>
</html>