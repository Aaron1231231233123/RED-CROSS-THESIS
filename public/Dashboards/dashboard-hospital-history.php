<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/check_account_hospital_modal.php';

// Add this helper function for Supabase REST queries
function querySQL($table, $select = '*', $filters = []) {
    $url = SUPABASE_URL . "/rest/v1/$table?select=$select";
    foreach ($filters as $key => $value) {
        $url .= "&$key=$value";
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

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

// Add this function at the top with other functions
function testSupabaseConnection() {
    $ch = curl_init();
    
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    ];
    
    // Test URL - just try to get one record
    $test_url = SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id&limit=1';
    
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'success' => $http_code === 200,
        'http_code' => $http_code,
        'response' => $response,
        'error' => $curl_error
    ];
}

// Function to update blood request status and deduct inventory
function updateBloodRequestAndInventory($request_id) {
    try {
        // First test the connection
        $connection_test = testSupabaseConnection();
        if (!$connection_test['success']) {
            error_log("Supabase connection test failed. HTTP Code: " . $connection_test['http_code']);
            error_log("Response: " . $connection_test['response']);
            error_log("CURL Error: " . $connection_test['error']);
            
            throw new Exception("Unable to connect to the database. Please try again later.");
        }

        $ch = curl_init();
        
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
        
        if ($http_code !== 200) {
            throw new Exception("Failed to fetch blood request. HTTP Code: " . $http_code);
        }

        $request_data = json_decode($response, true);
        if (empty($request_data)) {
            throw new Exception("No blood request found with ID: " . $request_id);
        }
        
        $request_data = $request_data[0];
        $requested_blood_type = $request_data['patient_blood_type'];
        $requested_rh_factor = $request_data['rh_factor'];
        $units_requested = intval($request_data['units_requested']); // Convert to integer
        $blood_type_full = $requested_blood_type . ($requested_rh_factor === 'Positive' ? '+' : '-');

        // --- MATCH ADMIN DASHBOARD LOGIC FOR AVAILABLE BLOOD ---
        // 1. Query eligibility for all successful collections
        $eligibilityData = querySQL(
            'eligibility',
            'eligibility_id,donor_id,blood_type,donation_type,blood_bag_type,collection_successful,unit_serial_number,collection_start_time,start_date,end_date,status,blood_collection_id',
            ['collection_successful' => 'eq.true']
        );
        
        $available_bags = [];
        $today = new DateTime();
        foreach ($eligibilityData as $item) {
            // 2. For each eligibility, fetch related blood_collection
            if (!empty($item['blood_collection_id'])) {
                $bloodCollectionData = querySQL('blood_collection', '*', ['blood_collection_id' => 'eq.' . $item['blood_collection_id']]);
                $bloodCollectionData = isset($bloodCollectionData[0]) ? $bloodCollectionData[0] : null;
            } else {
                $bloodCollectionData = null;
            }
            // 3. Use collection_start_time for expiration
            $collectionDate = new DateTime($item['collection_start_time']);
            $expirationDate = clone $collectionDate;
            $expirationDate->modify('+35 days'); // or '+42 days'
            $isExpired = ($today > $expirationDate);
            // 4. Only count bags with amount_taken > 0 and not expired
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
        // --- END ADMIN LOGIC MATCH ---

        // Now use $available_bags for deduction logic
        $units_found = 0;
        $collections_to_update = [];
        $remaining_units = $units_requested;
        $deducted_by_type = [];

        // First try exact matches
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
        // If we still need units, try compatible types
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

            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error_message,
                'error_type' => 'INSUFFICIENT_FRESH_INVENTORY',
                'requested_blood_type' => $blood_type_full,
                'units_requested' => $units_requested,
                'units_found' => $units_found,
                'shortage' => $shortage,
                'deducted_by_type' => $deducted_by_type
            ]);
            exit;
        }

        // Update blood collections to deduct the used units
        foreach ($collections_to_update as $collection) {
            // Calculate new amount after deduction (as integer)
            $new_amount = intval($collection['amount_taken']) - intval($collection['units_to_take']);
            if ($new_amount < 0) $new_amount = 0; // Never negative
            $update_data = json_encode([
                'amount_taken' => $new_amount,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $update_url = SUPABASE_URL . '/rest/v1/blood_collection';
            $update_url .= '?blood_collection_id=eq.' . $collection['blood_collection_id'];
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
            if ($http_code !== 200 && $http_code !== 204) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => "Failed to update blood collection {$collection['blood_collection_id']}. HTTP Code: {$http_code}",
                    'error_type' => 'UPDATE_COLLECTION_FAILED',
                    'collection_id' => $collection['blood_collection_id']
                ]);
                exit;
            }
        }

        // Update request status
        $request_update_data = json_encode([
            'status' => 'Confirmed',
            'last_updated' => date('Y-m-d H:i:s')
        ]);
        
        $update_url = SUPABASE_URL . '/rest/v1/blood_requests';
        $update_url .= '?request_id=eq.' . $request_id;
        
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
        
        if ($http_code !== 200 && $http_code !== 204) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update request status. HTTP Code: ' . $http_code,
                'error_type' => 'UPDATE_REQUEST_FAILED',
                'request_id' => $request_id
            ]);
            exit;
        }
        
        curl_close($ch);

        // Create detailed success message
        $detailed_message = "Successfully processed blood request #{$request_id}:\n";
        $detailed_message .= "- Requested blood type: {$blood_type_full}\n";
        $detailed_message .= "- Total units deducted: {$units_requested}\n\n";
        $detailed_message .= "Deduction Details:\n";
        foreach ($deducted_by_type as $type => $amount) {
            $detailed_message .= "- {$type}: {$amount} units\n";
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'request_id' => $request_id,
            'message' => 'Blood request completed successfully',
            'detailed_message' => $detailed_message,
            'units_deducted' => $units_requested,
            'blood_type' => $blood_type_full,
            'collections_updated' => count($collections_to_update),
            'units_found' => $units_found,
            'deducted_by_type' => $deducted_by_type
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Error in updateBloodRequestAndInventory: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "An error occurred: " . $e->getMessage()
        ]);
        exit;
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

        .modal-header-red {
            background-color: #941022 !important;
            color: #fff !important;
            border-top-left-radius: 0.3rem;
            border-top-right-radius: 0.3rem;
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
                            <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard-hospital-main.php' ? ' active' : ''; ?>" href="dashboard-hospital-main.php">
                                <i class="fas fa-home me-2"></i>Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard-hospital-requests.php' ? ' active' : ''; ?>" href="dashboard-hospital-requests.php">
                                <i class="fas fa-tint me-2"></i>Active Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <?php
                            $historyPages = ['dashboard-hospital-request-history.php', 'dashboard-hospital-history.php'];
                            $isHistory = in_array(basename($_SERVER['PHP_SELF']), $historyPages);
                            $status = $_GET['status'] ?? '';
                            ?>
                            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#historyCollapse" role="button" aria-expanded="<?php echo $isHistory ? 'true' : 'false'; ?>" aria-controls="historyCollapse" id="historyCollapseBtn">
                                <span><i class="fas fa-history me-2"></i>Requests</span>
                                <i class="fas fa-chevron-down transition-arrow<?php echo $isHistory ? ' rotate' : ''; ?>" id="historyChevron"></i>
                            </a>
                            <div class="collapse<?php echo $isHistory ? ' show' : ''; ?>" id="historyCollapse">
                                <div class="collapse-menu">
                                    <a href="dashboard-hospital-history.php" class="nav-link<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard-hospital-history.php' ? ' active' : ''; ?>">Approved</a>
                                    <a href="dashboard-hospital-request-history.php?status=completed" class="nav-link<?php echo $isHistory && $status === 'completed' ? ' active' : ''; ?>">Completed</a>
                                    <a href="dashboard-hospital-request-history.php?status=declined" class="nav-link<?php echo $isHistory && $status === 'declined' ? ' active' : ''; ?>">Declined</a>
                                </div>
                            </div>
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
                        <h2 class="card-title mb-3">Print Requests</h2>
                            
                            <!-- Search Bar -->
                            <div class="search-box mb-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" 
                                        class="form-control border-start-0" 
                                        id="requestSearchBar" 
                                        placeholder="Search by patient name...">
                                </div>
                            </div>

                                <!-- Table for Request History -->
                                <table class="table table-bordered table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>No.</th>
                                            <th>Patient Name</th>
                                            <th>Age</th>
                                            <th>Gender</th>
                                            <th>Blood Type</th>
                                            <th>Quantity</th>
                                            <th>Physician</th>
                                            <th>Requested On</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $rowNum = 1; if (empty($blood_requests)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No blood requests found.</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($blood_requests as $request): ?>
                                                <?php if ($request['status'] === 'Approved' || $request['status'] === 'Accepted'): ?>
                                                <tr>
                                                    <td><?php echo $rowNum++; ?></td>
                                                    <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['patient_age']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['patient_gender']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-')); ?></td>
                                                    <td><?php echo htmlspecialchars($request['units_requested'] . ' Units'); ?></td>
                                                    <td><?php echo htmlspecialchars($request['physician_name']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($request['requested_on'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-success pickup-btn" 
                                                                data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                                                onclick="markAsConfirmed(<?php echo htmlspecialchars($request['request_id']); ?>)">
                                                            <i class="fas fa-print me-1"></i> Print
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
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

<!-- Insufficient Inventory Modal -->
<div class="modal fade" id="insufficientInventoryModal" tabindex="-1" aria-labelledby="insufficientInventoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="insufficientInventoryModalLabel">Insufficient Blood Inventory</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="insufficientInventoryModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Deduction Modal -->
<div class="modal fade" id="confirmDeductionModal" tabindex="-1" aria-labelledby="confirmDeductionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header modal-header-red">
        <h5 class="modal-title" id="confirmDeductionModalLabel">Confirm Blood Deduction</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmDeductionModalBody">
        Are you sure you want to mark this blood request as confirmed? This will deduct the units from inventory.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmDeductionBtn">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Blood Request Receipt Modal -->
<div class="modal fade" id="bloodRequestReceiptModal" tabindex="-1" aria-labelledby="bloodRequestReceiptModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background-color: #941022; color: #fff;">
        <h5 class="modal-title" id="bloodRequestReceiptModalLabel">Blood Request Receipt</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">This receipt must be presented to the Philippine Red Cross (PRC) to claim the patient's blood request.</p>
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
function showInsufficientInventoryModal(message) {
    // Try to parse and format the message as a bulleted list if it contains details
    let formatted = message;
    // If the message contains 'Request Details:' and bullets, format as HTML list
    if (message.includes('Request Details:')) {
        const parts = message.split('Request Details:');
        let mainMsg = parts[0].trim();
        let details = parts[1].trim();
        // Replace bullet points with <li> and wrap in <ul>
        let listItems = details
            .replace(/\n/g, '')
            .replace(/•/g, '\n•')
            .split('\n')
            .filter(line => line.trim().startsWith('•'))
            .map(line => `<li>${line.replace('•', '').trim()}</li>`) // Remove bullet and wrap
            .join('');
        formatted = `<div>${mainMsg}</div><ul>${listItems}</ul>`;
    }
    document.getElementById('insufficientInventoryModalBody').innerHTML = formatted;
    var modal = new bootstrap.Modal(document.getElementById('insufficientInventoryModal'));
    modal.show();
}

function showConfirmDeductionModal(onConfirm) {
    var modal = new bootstrap.Modal(document.getElementById('confirmDeductionModal'));
    document.getElementById('confirmDeductionBtn').onclick = function() {
        modal.hide();
        onConfirm();
    };
    modal.show();
}

// Replace alert() and confirm() in markAsConfirmed
function markAsConfirmed(requestId) {
    showConfirmDeductionModal(function() {
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
                                <button type="button" class="btn btn-success" id="printRequestBtn" data-request-id="${data.request_id}">Print</button>
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
            // After creating and showing the modal
            const printBtn = modalContainer.querySelector('#printRequestBtn');
            if (printBtn) {
                printBtn.addEventListener('click', function() {
                    // Redirect to print page with request ID
                    window.location.href = `print-blood-request.php?request_id=${data.request_id}`;
                });
            }
        } else {
                // Show error message in modal
                showInsufficientInventoryModal(data.message);
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
            showInsufficientInventoryModal('An error occurred. Please try again.');
        button.innerHTML = originalContent;
        button.disabled = false;
        });
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