<?php
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include required functions
require_once 'module/optimized_functions.php';

// Include database connection
include_once '../../assets/conn/db_conn.php';

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/module/optimized_functions.php';

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// OPTIMIZATION: Smart caching with intelligent change detection
$cacheKey = 'bloodbank_dashboard_v1_' . date('Y-m-d'); // Daily cache key
$cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.json';
$cacheMetaFile = sys_get_temp_dir() . '/' . $cacheKey . '_meta.json';

// Cache enabled with improved change detection
$useCache = false;
$needsFullRefresh = false;

// Manual cache refresh option
if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    // Force cache refresh
    if (file_exists($cacheFile)) unlink($cacheFile);
    if (file_exists($cacheMetaFile)) unlink($cacheMetaFile);
    $useCache = false; // Force no cache usage
}

// Check if cache exists and is valid
if (file_exists($cacheFile) && file_exists($cacheMetaFile)) {
    $cacheMeta = json_decode(file_get_contents($cacheMetaFile), true);
    $cacheAge = time() - $cacheMeta['timestamp'];
    
    // SMART CHANGE DETECTION: Check for status changes that affect counts
    $hasChanges = false;
    
    // Only check for changes if cache is not too old (max 30 minutes)
    if ($cacheAge < 1800) { // 30 minutes max cache age
        // Quick check: Get recent status changes
        $statusCheckResponse = supabaseRequest("blood_bank_units?select=status,updated_at&order=updated_at.desc&limit=10");
        $currentStatusHash = md5(json_encode($statusCheckResponse ?: []));
        
        // Additional check: Specifically look for handed_over status changes
        $handedOverCheckResponse = supabaseRequest("blood_bank_units?select=unit_id,status&status=eq.handed_over");
        $currentHandedOverHash = md5(json_encode($handedOverCheckResponse ?: []));
        
        // Check if statuses have changed since last cache
        if (isset($cacheMeta['statusHash']) && $cacheMeta['statusHash'] !== $currentStatusHash) {
            $hasChanges = true;
        }
        
        // Check specifically for handed_over changes
        if (isset($cacheMeta['handedOverHash']) && $cacheMeta['handedOverHash'] !== $currentHandedOverHash) {
            $hasChanges = true;
        }
        
        // Also check cache age - if older than 5 minutes, consider it stale
        if ($cacheAge > 300) { // 5 minutes
            $hasChanges = true;
        }
        
        if (!$hasChanges) {
            // No changes detected - use cache for instant loading
            $useCache = true;
            $cachedData = json_decode(file_get_contents($cacheFile), true);
            if ($cachedData && !empty($cachedData['bloodInventory'])) {
                $bloodInventory = $cachedData['bloodInventory'];
                $totalBags = $cachedData['totalBags'] ?? 0;
                $validBags = $cachedData['validBags'] ?? 0;
                $expiredBags = $cachedData['expiredBags'] ?? 0;
                $expiringBags = $cachedData['expiringBags'] ?? 0;
                $availableTypes = $cachedData['availableTypes'] ?? [];
                
                // Skip to the end of data processing
                goto cache_loaded;
            } else {
                // Cache data is invalid or empty - force refresh
                $useCache = false;
                if (file_exists($cacheFile)) unlink($cacheFile);
                if (file_exists($cacheMetaFile)) unlink($cacheMetaFile);
            }
        }
    }
}

// OPTIMIZED: Fetch blood inventory data with enhanced performance
$bloodInventory = [];

try {
    // OPTIMIZATION 1: Fetch ALL blood_bank_units records using pagination to bypass 1000 record limit
    $bloodBankUnitsData = [];
    $offset = 0;
    $limit = 1000;
    $hasMore = true;
    $maxIterations = 10; // Safety limit to prevent infinite loops
    $iteration = 0;
    
    while ($hasMore && $iteration < $maxIterations) {
        $endpoint = "blood_bank_units?select=unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,hospital_request_id,created_at,updated_at&unit_serial_number=not.is.null&order=collected_at.desc&limit={$limit}&offset={$offset}";
        $bloodBankUnitsResponse = supabaseRequest($endpoint);
        $batchData = isset($bloodBankUnitsResponse['data']) ? $bloodBankUnitsResponse['data'] : [];
        
        if (empty($batchData)) {
            $hasMore = false;
        } else {
            $bloodBankUnitsData = array_merge($bloodBankUnitsData, $batchData);
            $offset += $limit;
            $iteration++;
            
            // If we got less than the limit, we've reached the end
            if (count($batchData) < $limit) {
                $hasMore = false;
            }
        }
    }
    
    error_log("Blood Bank Dashboard: Fetched " . count($bloodBankUnitsData) . " total records in " . $iteration . " batches");
    
    // OPTIMIZATION 2: Get donor data only for units we actually have (reduces data transfer)
    if (!empty($bloodBankUnitsData)) {
        $donorIds = array_unique(array_column($bloodBankUnitsData, 'donor_id'));
        $donorIdsFilter = implode(',', array_map('intval', $donorIds));
        
        $donorResponse = supabaseRequest("donor_form?select=donor_id,surname,first_name,middle_name,birthdate,sex,civil_status&donor_id=in.({$donorIdsFilter})");
        $donorData = isset($donorResponse['data']) ? $donorResponse['data'] : [];
        
        // Create lookup array for donor data
        $donorLookup = [];
        foreach ($donorData as $donor) {
            $donorLookup[$donor['donor_id']] = $donor;
        }
    } else {
        $donorLookup = [];
    }
    
    // No need to check declined donors - blood_bank_units already contains only successful donations
    
    // OPTIMIZATION 3: Batch process with array functions instead of loops
    if (is_array($bloodBankUnitsData) && !empty($bloodBankUnitsData)) {
        // Filter out empty serial numbers in one pass
        $filteredUnits = array_filter($bloodBankUnitsData, function($item) {
            return !empty($item['unit_serial_number']);
        });
        
        // OPTIMIZATION 7: Use array_map for parallel processing
        $bloodInventory = array_map(function($item) use ($donorLookup) {
            // Parse dates once
            $collectionDate = new DateTime($item['collected_at']);
            $expirationDate = new DateTime($item['expires_at']);
            $today = new DateTime();
            
            // Determine status efficiently
            $status = 'Valid';
            if ($today > $expirationDate) {
                $status = 'Expired';
            } elseif ($item['status'] === 'handed_over') {
                $status = 'Handed Over';
            }
            
            // Get donor info efficiently
            $donor = $donorLookup[$item['donor_id']] ?? null;
            $donorInfo = [
                'surname' => 'Not Found',
                'first_name' => '',
                'middle_name' => '',
                'birthdate' => '',
                'age' => '',
                'sex' => '',
                'civil_status' => ''
            ];
            
            if ($donor) {
                $age = '';
                if (!empty($donor['birthdate'])) {
                    $birthdate = new DateTime($donor['birthdate']);
                    $age = $birthdate->diff($today)->y;
                }
                
                $donorInfo = [
                    'surname' => $donor['surname'] ?? '',
                    'first_name' => $donor['first_name'] ?? '',
                    'middle_name' => $donor['middle_name'] ?? '',
                    'birthdate' => !empty($donor['birthdate']) ? date('d/m/Y', strtotime($donor['birthdate'])) : '',
                    'age' => $age,
                    'sex' => $donor['sex'] ?? '',
                    'civil_status' => $donor['civil_status'] ?? ''
                ];
            }
            
            return [
                'unit_id' => $item['unit_id'],
                'donor_id' => $item['donor_id'],
                'serial_number' => $item['unit_serial_number'],
                'blood_type' => $item['blood_type'],
                'bags' => 1,
                'bag_type' => $item['bag_type'] ?: 'Standard',
                'bag_brand' => $item['bag_brand'] ?: 'N/A',
                'collection_date' => $collectionDate->format('Y-m-d'),
                'expiration_date' => $expirationDate->format('Y-m-d'),
                'status' => $status,
                'unit_status' => $item['status'],
                'hospital_request_id' => $item['hospital_request_id'] ?? null,
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
                'donor' => $donorInfo
            ];
        }, $filteredUnits);
        
        // Convert to indexed array
        $bloodInventory = array_values($bloodInventory);
    }
    
} catch (Exception $e) {
    error_log("Error in Blood Bank data fetching: " . $e->getMessage());
    $bloodInventory = [];
}

// If no records found, keep the bloodInventory array empty
if (empty($bloodInventory)) {
    $bloodInventory = [];
}

// OPTIMIZATION 8: Efficient statistics calculation using array functions
$today = new DateTime();
$expiryLimit = (new DateTime())->modify('+7 days');

// Use array_reduce for single-pass statistics calculation
$stats = array_reduce($bloodInventory, function($carry, $bag) use ($today, $expiryLimit) {
    // Count total bags (exclude expired and handed_over units)
    // Each unit in blood_bank_units represents 1 bag
    if ($bag['status'] != 'Expired' && $bag['status'] != 'Handed Over') {
        $carry['totalBags'] += 1; // Each unit = 1 bag
    }
    
    // Count valid bags
    if ($bag['status'] == 'Valid') {
        $carry['validBags']++;
        // Track available blood types
        if (!in_array($bag['blood_type'], $carry['availableTypes'])) {
            $carry['availableTypes'][] = $bag['blood_type'];
        }
    }
    
    // Count expired bags
    if ($bag['status'] == 'Expired') {
        $carry['expiredBags']++;
    }
    
    // Count expiring soon (valid units expiring within 7 days)
    if ($bag['status'] == 'Valid') {
        $expirationDate = new DateTime($bag['expiration_date']);
        if ($expirationDate <= $expiryLimit && $today <= $expirationDate) {
            $carry['expiringBags']++;
        }
    }
    
    return $carry;
}, [
    'totalBags' => 0,
    'validBags' => 0,
    'expiredBags' => 0,
    'expiringBags' => 0,
    'availableTypes' => []
]);

// Extract statistics
$totalBags = $stats['totalBags'];
$validBags = $stats['validBags'];
$expiredBags = $stats['expiredBags'];
$expiringBags = $stats['expiringBags'];
$availableTypes = $stats['availableTypes'];

// Convert to integer for display
$totalBags = (int)$totalBags;


// OPTIMIZATION: Save to cache with smart change detection
$cacheData = [
    'bloodInventory' => $bloodInventory,
    'totalBags' => $totalBags,
    'validBags' => $validBags,
    'expiredBags' => $expiredBags,
    'expiringBags' => $expiringBags,
    'availableTypes' => $availableTypes,
    'timestamp' => time()
];

// Get current status hash for change detection
$statusCheckResponse = supabaseRequest("blood_bank_units?select=status,updated_at&order=updated_at.desc&limit=10");
$currentStatusHash = md5(json_encode($statusCheckResponse ?: []));

// Get handed_over status hash for specific change detection
$handedOverCheckResponse = supabaseRequest("blood_bank_units?select=unit_id,status&status=eq.handed_over");
$currentHandedOverHash = md5(json_encode($handedOverCheckResponse ?: []));

$cacheMeta = [
    'timestamp' => time(),
    'statusHash' => $currentStatusHash,
    'handedOverHash' => $currentHandedOverHash,
    'version' => 'v1'
];

file_put_contents($cacheFile, json_encode($cacheData));
file_put_contents($cacheMetaFile, json_encode($cacheMeta));

// Cache loaded marker
cache_loaded:

// Display all records without pagination
$totalItems = count($bloodInventory);
$currentPageInventory = $bloodInventory; // Show all records

// OPTIMIZATION: Performance logging and caching
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
addPerformanceHeaders($executionTime, count($bloodInventory), "Blood Bank Module - Total Bags: {$totalBags}, Valid: {$validBags}, Expired: {$expiredBags}");

// Get default sorting
$sortBy = "Default (Latest)";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Inventory</title>
    
    <!-- Prevent browser caching -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
    margin: 30px auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
    width: 100%;
    margin-top: 100px;
}

/* Inventory Dashboard Cards */
.inventory-card {
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    text-align: center;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background-color: #ffe6e6; /* Light pink background */
}

.inventory-card h2 {
    font-size: 64px;
    font-weight: bold;
    margin: 10px 0;
    color: #dc3545; /* Red color for numbers */
}

.inventory-stat-card {
    background-color: #ffe6e6;
    border-radius: 8px;
    border: none;
}

.inventory-stat-number {
    font-size: 64px;
    font-weight: bold;
    color: #dc3545;
}

.blood-type-card {
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.blood-inventory-heading {
    font-size: 1.75rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
}

.date-display {
    font-size: 0.9rem;
    font-weight: normal;
    color: #666;
    float: right;
}

/* Action buttons */
.action-btn {
    font-size: 1.1rem;
    color: white;
    background-color: #0d6efd;
    border: none;
    border-radius: 6px;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.action-btn:hover {
    background-color: #0b5ed7;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
}

/* Modal styling */
.donor-details-modal .modal-header {
    background-color: #212529;
    color: white;
}

.donor-details-modal .close-btn {
    color: white;
    background: none;
    border: none;
    font-size: 1.5rem;
}

.donor-details-section {
    margin-bottom: 20px;
}

.donor-details-section h3 {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 15px;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 5px;
}

.donor-details-form-group {
    margin-bottom: 15px;
}

.donor-details-form-label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}

.donor-details-form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background-color: #f8f9fa;
}

.eligibility-status {
    display: inline-block;
    color: white;
    background-color: #dc3545;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
    margin-right: 10px;
}

/* Search and Sort Container */
.search-sort-container {
    width: 100%;
    margin-bottom: 20px;
    position: relative;
}

.search-sort-container .form-control {
    width: 100%;
    padding-left: 120px;
    height: 50px;
    border: 2px solid #ced4da;
    border-left: none;
    font-size: 1rem;
    box-shadow: 0 3px 6px rgba(0,0,0,0.1);
    border-radius: 8px;
}

.search-sort-container .sort-select {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    padding: 0 35px 0 15px;
    background-color: #f8f9fa;
    border: 2px solid #ced4da;
    border-right: none;
    border-radius: 8px 0 0 8px;
    cursor: pointer;
    color: #6c757d;
    font-size: 1rem;
    z-index: 2;
    min-width: 120px;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 12px;
}

.search-sort-container .sort-select:focus {
    box-shadow: none;
    outline: none;
    border-color: #ced4da;
}

.search-sort-container .form-control:focus {
    box-shadow: none;
    border-color: #ced4da;
}

.search-sort-container::after {
    content: '';
    position: absolute;
    left: 120px;
    top: 0;
    height: 100%;
    width: 1px;
    background-color: #ced4da;
    z-index: 3;
}

.search-sort-container:focus-within {
    box-shadow: 0 0 0 0.25rem rgba(0,123,255,.25);
    border-radius: 8px;
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
                        <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link active">
                            <span><i class="fas fa-tint"></i>Blood Bank</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link">
                            <span><i class="fas fa-list"></i>Hospital Requests</span>
                        </a>
                        <a href="dashboard-Inventory-System-Reports-reports-admin.php" class="nav-link">
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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="container-fluid p-4 custom-margin">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="blood-inventory-heading mb-0">Blood Bank Inventory</h2>
                        <div class="d-flex align-items-center">
                            <p class="text-dark mb-0 me-3">
                            <strong><?php echo date('d F Y'); ?></strong> <i class="fas fa-calendar-alt ms-2"></i>
                        </p>
                        </div>
                    </div>
                    
                    <!-- Search Container -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="search-container">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <select class="form-select category-select" id="searchCategory" style="max-width: 150px;">
                                        <option value="all">All Fields</option>
                                        <option value="serial">Serial Number</option>
                                        <option value="blood_type">Blood Type</option>
                                        <option value="component">Bag Type</option>
                                        <option value="date">Date</option>
                                    </select>
                                    <input type="text" 
                                        class="form-control" 
                                        id="searchInput" 
                                        placeholder="Search blood inventory...">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <!-- Total Blood Units -->
                        <div class="col-md-3">
                            <div class="card text-center p-3 h-100 inventory-stat-card">
                                <div class="card-body">
                                    <h5 class="card-title text-dark fw-bold">Total Blood Units</h5>
                                    <h1 class="inventory-stat-number my-3"><?php echo $totalBags; ?></h1>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Available Types -->
                        <div class="col-md-3">
                            <div class="card text-center p-3 h-100 inventory-stat-card">
                                <div class="card-body">
                                    <h5 class="card-title text-dark fw-bold">Available Types</h5>
                                    <h1 class="inventory-stat-number my-3"><?php echo count($availableTypes); ?></h1>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Units Expiring Soon -->
                        <div class="col-md-3">
                            <div class="card text-center p-3 h-100 inventory-stat-card">
                                <div class="card-body">
                                    <h5 class="card-title text-dark fw-bold">Units Expiring Soon</h5>
                                    <h1 class="inventory-stat-number my-3"><?php echo $expiringBags; ?></h1>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Expired Units -->
                        <div class="col-md-3">
                            <div class="card text-center p-3 h-100 inventory-stat-card">
                                <div class="card-body">
                                    <h5 class="card-title text-dark fw-bold">Expired Units</h5>
                                    <h1 class="inventory-stat-number my-3"><?php echo $expiredBags; ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Available Blood per Unit</h5>
                    
                    <?php
                    // OPTIMIZATION 9: Calculate blood type counts using array_reduce for efficiency
                    $bloodTypeCounts = array_reduce($bloodInventory, function($carry, $bag) {
                        if ($bag['status'] == 'Valid' && isset($carry[$bag['blood_type']])) {
                            $carry[$bag['blood_type']] += 1; // Each unit = 1 bag
                        }
                        return $carry;
                    }, [
                        'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
                        'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
                    ]);

                    // Threshold for low availability indicator (danger)
                    $lowThreshold = 25;
                    // Helper to render a small danger icon in the card corner when under threshold
                    function renderDangerIcon($count, $threshold) {
                        if ($count <= $threshold) {
                            return '<span class="text-danger" title="Low availability" style="position:absolute; top:8px; right:10px;"><i class="fas fa-exclamation-triangle"></i></span>';
                        }
                        return '';
                    }
                    ?>
                    
                    <div class="row mb-4">
                        <!-- Blood Type Cards -->
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card" style="position: relative;">
                                <?php echo renderDangerIcon((int)$bloodTypeCounts['A+'], $lowThreshold); ?>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: A+</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php echo (int)$bloodTypeCounts['A+']; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card" style="position: relative;">
                                <?php echo renderDangerIcon((int)$bloodTypeCounts['A-'], $lowThreshold); ?>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: A-</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php echo (int)$bloodTypeCounts['A-']; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card" style="position: relative;">
                                <?php echo renderDangerIcon((int)$bloodTypeCounts['B+'], $lowThreshold); ?>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: B+</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php echo (int)$bloodTypeCounts['B+']; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card" style="position: relative;">
                                <?php echo renderDangerIcon((int)$bloodTypeCounts['B-'], $lowThreshold); ?>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: B-</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php echo (int)$bloodTypeCounts['B-']; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card" style="position: relative;">
                                <?php echo renderDangerIcon((int)$bloodTypeCounts['O+'], $lowThreshold); ?>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: O+</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php echo (int)$bloodTypeCounts['O+']; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card" style="position: relative;">
                                <?php echo renderDangerIcon((int)$bloodTypeCounts['O-'], $lowThreshold); ?>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: O-</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php echo (int)$bloodTypeCounts['O-']; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card" style="position: relative;">
                                <?php echo renderDangerIcon((int)$bloodTypeCounts['AB+'], $lowThreshold); ?>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: AB+</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php echo (int)$bloodTypeCounts['AB+']; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card" style="position: relative;">
                                <?php echo renderDangerIcon((int)$bloodTypeCounts['AB-'], $lowThreshold); ?>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: AB-</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php echo (int)$bloodTypeCounts['AB-']; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Blood Bank Table -->
                    <div class="table-responsive mt-4">
                        <!-- Total Records Display -->
                        <div class="row mb-3">
                            <div class="col-12 text-center">
                                <span class="text-muted">Total records: <?php echo $totalItems; ?></span>
                            </div>
                        </div>
                        
                        <table class="table table-bordered table-hover" id="bloodInventoryTable">
                            <thead>
                                <tr class="bg-danger text-white">
                                    <th>Serial Number</th>
                                    <th>Blood Type</th>
                                    <th>Collection Date</th>
                                    <th>Expiration Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentPageInventory as $index => $bag): ?>
                                <tr>
                                    <td><?php echo $bag['serial_number']; ?></td>
                                    <td><?php echo $bag['blood_type']; ?></td>
                                    <td><?php echo $bag['collection_date']; ?></td>
                                    <td><?php echo $bag['expiration_date']; ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-primary btn-sm" onclick="showDonorDetails(<?php echo $index; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($currentPageInventory)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No blood inventory records found. Please wait for an administrator to add data.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Showing all entries information -->
                    <div class="text-center mt-2 mb-4">
                        <p class="text-muted">
                            Showing all <?php echo $totalItems; ?> blood bank units
                        </p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Blood Bank Unit Details Modal -->
    <div class="modal fade" id="bloodBankUnitDetailsModal" tabindex="-1" aria-labelledby="bloodBankUnitDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #dc3545; color: white;">
                    <h5 class="modal-title" id="bloodBankUnitDetailsModalLabel" style="font-weight: bold; font-size: 1.5rem;">
                        Blood Bank
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 30px;">
                    <!-- Unit Serial Number and Blood Type Header -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="text-center">
                                <div style="font-size: 2rem; font-weight: bold; color: #000; margin-bottom: 5px;" id="modal-unit-serial">20230715-001-123</div>
                                <div style="font-size: 0.9rem; color: #666;">Unit Serial Number</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center">
                                <div style="font-size: 2rem; font-weight: bold; color: #000; margin-bottom: 5px;" id="modal-blood-type-display">A+</div>
                                <div style="font-size: 0.9rem; color: #666;">Blood Type</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Fields -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal-collection-date" class="form-label" style="font-weight: 500; color: #333;">Collection Date</label>
                                <input type="text" class="form-control" id="modal-collection-date" readonly style="border: 1px solid #ced4da; border-radius: 4px; padding: 8px;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal-expiration-date" class="form-label" style="font-weight: 500; color: #333;">Expiration Date</label>
                                <input type="text" class="form-control" id="modal-expiration-date" readonly style="border: 1px solid #ced4da; border-radius: 4px; padding: 8px;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal-collected-from" class="form-label" style="font-weight: 500; color: #333;">Collected From</label>
                                <input type="text" class="form-control" id="modal-collected-from" readonly style="border: 1px solid #ced4da; border-radius: 4px; padding: 8px;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal-recipient-hospital" class="form-label" style="font-weight: 500; color: #333;">Recipient Hospital</label>
                                <input type="text" class="form-control" id="modal-recipient-hospital" readonly style="border: 1px solid #ced4da; border-radius: 4px; padding: 8px;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal-phlebotomist-name" class="form-label" style="font-weight: 500; color: #333;">Phlebotomist Name</label>
                                <input type="text" class="form-control" id="modal-phlebotomist-name" readonly style="border: 1px solid #ced4da; border-radius: 4px; padding: 8px;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal-blood-status" class="form-label" style="font-weight: 500; color: #333;">Blood Status</label>
                                <input type="text" class="form-control" id="modal-blood-status" readonly style="border: 1px solid #ced4da; border-radius: 4px; padding: 8px; font-style: italic; color: #6c757d;" placeholder="(Available, Reserved, Expired, Used)">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading indicator -->
                    <div id="modal-loading" class="text-center" style="display: none;">
                        <div class="spinner-border text-danger" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading blood bank unit details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple cache prevention - only refresh if page was loaded from cache
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was loaded from cache, force refresh
                window.location.reload();
            }
        });
    </script>
    <script>
        // Data for all records
        const bloodInventory = <?php echo json_encode($bloodInventory); ?>;
        const currentPageInventory = <?php echo json_encode($currentPageInventory); ?>;
        
        // Function to display blood bank unit details in modal
        function showDonorDetails(index) {
            // Since we're showing all records, the index is the same
            const bag = bloodInventory[index];
            
            // Show loading indicator
            document.getElementById('modal-loading').style.display = 'block';
            
            // Show the modal first
            const modal = new bootstrap.Modal(document.getElementById('bloodBankUnitDetailsModal'));
            modal.show();
            
            // Fetch detailed information from API
            fetch(`../../assets/php_func/blood_bank_unit_details_api.php?unit_id=${bag.unit_id}`)
                .then(response => response.json())
                .then(data => {
                    // Hide loading indicator
                    document.getElementById('modal-loading').style.display = 'none';
                    
                    if (data.error) {
                        console.error('Error fetching unit details:', data.error);
                        // Fallback to basic data
                        populateBasicData(bag);
                    } else {
                        // Populate modal with detailed data
                        populateDetailedData(data);
                    }
                })
                .catch(error => {
                    console.error('Error fetching unit details:', error);
                    // Hide loading indicator
                    document.getElementById('modal-loading').style.display = 'none';
                    // Fallback to basic data
                    populateBasicData(bag);
                });
        }
        
        // Function to populate modal with detailed data from API
        function populateDetailedData(data) {
            // Unit Serial Number and Blood Type (large display)
            document.getElementById('modal-unit-serial').textContent = data.unit_serial_number || 'N/A';
            document.getElementById('modal-blood-type-display').textContent = data.blood_type || 'N/A';
            
            // Form fields
            document.getElementById('modal-collection-date').value = data.collection_date || 'N/A';
            document.getElementById('modal-expiration-date').value = data.expiration_date || 'N/A';
            document.getElementById('modal-collected-from').value = data.collected_from || 'Blood Bank';
            document.getElementById('modal-recipient-hospital').value = data.recipient_hospital || 'Not Assigned';
            document.getElementById('modal-phlebotomist-name').value = data.phlebotomist_name || 'Not Available';
            document.getElementById('modal-blood-status').value = data.blood_status || 'Available';
        }
        
        // Function to populate modal with basic data (fallback)
        function populateBasicData(bag) {
            // Unit Serial Number and Blood Type (large display)
            document.getElementById('modal-unit-serial').textContent = bag.serial_number || 'N/A';
            document.getElementById('modal-blood-type-display').textContent = bag.blood_type || 'N/A';
            
            // Form fields
            document.getElementById('modal-collection-date').value = bag.collection_date || 'N/A';
            document.getElementById('modal-expiration-date').value = bag.expiration_date || 'N/A';
            document.getElementById('modal-collected-from').value = 'Blood Bank';
            document.getElementById('modal-recipient-hospital').value = 'Not Assigned';
            document.getElementById('modal-phlebotomist-name').value = 'Not Available';
            document.getElementById('modal-blood-status').value = bag.status || 'Available';
        }
        

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'), {
                backdrop: true,
                keyboard: true
            });
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'), {
                backdrop: false,
                keyboard: false
            });

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
            
            // ==========================================
            // SEARCH FUNCTIONALITY
            // ==========================================
            // Get search elements
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            
            if (searchInput && searchCategory) {
                // Add event listeners for real-time search
                searchInput.addEventListener('keyup', searchTable);
                searchCategory.addEventListener('change', searchTable);
            }
            
            // OPTIMIZED: Function to search the blood inventory table with debouncing
            let searchTimeout;
            function searchTable() {
                const searchInput = document.getElementById('searchInput').value.toLowerCase();
                const searchCategory = document.getElementById('searchCategory').value;
                const table = document.getElementById('bloodInventoryTable');
                const rows = table.querySelectorAll('tbody tr');
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                // Debounce search for better performance
                searchTimeout = setTimeout(() => {
                    // Check if no results message already exists
                    let noResultsRow = document.getElementById('noResultsRow');
                    if (noResultsRow) {
                        noResultsRow.remove();
                    }
                    
                    let visibleRows = 0;
                    const totalRows = rows.length;
                    
                    // OPTIMIZED: Use more efficient iteration
                    for (let i = 0; i < rows.length; i++) {
                        const row = rows[i];
                        let shouldShow = false;
                        
                        if (searchInput.trim() === '') {
                            shouldShow = true;
                        } else {
                            const cells = row.querySelectorAll('td');
                            if (cells.length === 0) continue;
                            
                            if (searchCategory === 'all') {
                                // Search all columns
                                for (let j = 0; j < cells.length; j++) {
                                    if (cells[j].textContent.toLowerCase().includes(searchInput)) {
                                        shouldShow = true;
                                        break;
                                    }
                                }
                            } else if (searchCategory === 'serial') {
                                // Search serial number column
                                if (cells[0].textContent.toLowerCase().includes(searchInput)) {
                                    shouldShow = true;
                                }
                            } else if (searchCategory === 'blood_type') {
                                // Search blood type column
                                if (cells[1].textContent.toLowerCase().includes(searchInput)) {
                                    shouldShow = true;
                                }
                            } else if (searchCategory === 'component') {
                                // Search bag type column
                                if (cells[3].textContent.toLowerCase().includes(searchInput)) {
                                    shouldShow = true;
                                }
                            } else if (searchCategory === 'date') {
                                // Search date columns
                                const collectionDate = cells[2].textContent.toLowerCase();
                                const expirationDate = cells[3].textContent.toLowerCase();
                                // Create a regex for flexible date matching
                                const datePattern = new RegExp(searchInput.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
                                if (datePattern.test(collectionDate) || datePattern.test(expirationDate)) {
                                    shouldShow = true;
                                }
                            }
                        }
                        
                        row.style.display = shouldShow ? '' : 'none';
                        if (shouldShow) visibleRows++;
                    }
                    
                    // Show "No results" message if no matches
                    if (visibleRows === 0 && totalRows > 0) {
                        const tbody = table.querySelector('tbody');
                        const colspan = table.querySelector('thead tr').children.length;
                        
                        noResultsRow = document.createElement('tr');
                        noResultsRow.id = 'noResultsRow';
                        noResultsRow.innerHTML = `
                            <td colspan="${colspan}" class="text-center">
                                <div class="alert alert-info m-2">
                                    No matching inventory items found
                                    <button class="btn btn-outline-primary btn-sm ms-2" onclick="clearSearch()">
                                        Clear Search
                                    </button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(noResultsRow);
                    }
                    
                    // Update search results info
                    updateSearchInfo(visibleRows, totalRows);
                }, 150); // 150ms debounce delay
            }
            
            // Function to update search results info
            function updateSearchInfo(visibleRows, totalRows) {
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
                
                searchInfo.textContent = `Showing ${visibleRows} of ${totalRows} entries`;
            }
            
            // Function to clear search
            window.clearSearch = function() {
                document.getElementById('searchInput').value = '';
                document.getElementById('searchCategory').value = 'all';
                searchTable();
            }
        });
    </script>
</body>
</html>