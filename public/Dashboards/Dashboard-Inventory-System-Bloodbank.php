<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Function to execute a query
function executeQuery($query, $params = []) {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Prefer: return=representation'
    ];
    
    $url = SUPABASE_URL . '/rest/v1/rpc/' . $query;
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if (!empty($params)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    }
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    
    return json_decode($response, true);
}

// Function to query direct SQL (for non-RPC queries)
function querySQL($table, $select = "*", $filters = null) {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Prefer: return=representation'
    ];
    
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?select=' . urlencode($select);
    
    if ($filters) {
        foreach ($filters as $key => $value) {
            $url .= '&' . $key . '=' . urlencode($value);
        }
    }
    
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

// Fetch blood inventory data from eligibility table
$bloodInventory = [];

// Query eligibility table for valid blood units
$eligibilityData = querySQL(
    'eligibility', 
    'eligibility_id,donor_id,blood_type,donation_type,blood_bag_type,collection_successful,unit_serial_number,collection_start_time,start_date,end_date,status,blood_collection_id',
    ['collection_successful' => 'eq.true']
);

if (is_array($eligibilityData) && !empty($eligibilityData)) {
    foreach ($eligibilityData as $item) {
        // Skip if no serial number
        if (empty($item['unit_serial_number'])) {
            continue;
        }
        
        // Get donor information based on donor_id
        $donorData = querySQL('donor_form', '*', ['donor_id' => 'eq.' . $item['donor_id']]);
        $donor = isset($donorData[0]) ? $donorData[0] : null;
        
        // Get blood collection data to get amount_taken
        $bloodCollectionData = null;
        if (!empty($item['blood_collection_id'])) {
            $bloodCollectionData = querySQL('blood_collection', '*', ['blood_collection_id' => 'eq.' . $item['blood_collection_id']]);
            $bloodCollectionData = isset($bloodCollectionData[0]) ? $bloodCollectionData[0] : null;
        }
        
        // Calculate expiration date (35 days from collection)
        $collectionDate = new DateTime($item['collection_start_time']);
        $expirationDate = clone $collectionDate;
        $expirationDate->modify('+35 days');
        
        // Create blood bag entry
        $bloodBag = [
            'eligibility_id' => $item['eligibility_id'],
            'donor_id' => $item['donor_id'],
            'serial_number' => $item['unit_serial_number'],
            'blood_type' => $item['blood_type'],
            'bags' => $bloodCollectionData && isset($bloodCollectionData['amount_taken']) ? $bloodCollectionData['amount_taken'] : 'N/A',
            'bag_type' => $item['blood_bag_type'] ?: 'Standard',
            'collection_date' => $collectionDate->format('Y-m-d'),
            'expiration_date' => $expirationDate->format('Y-m-d'),
            'status' => (new DateTime() > $expirationDate) ? 'Expired' : 'Valid',
            'eligibility_status' => $item['status'],
            'eligibility_end_date' => $item['end_date'],
        ];
        
        // Add donor information if available
        if ($donor) {
            // Calculate age based on birthdate
            $age = '';
            if (!empty($donor['birthdate'])) {
                $birthdate = new DateTime($donor['birthdate']);
                $today = new DateTime();
                $age = $birthdate->diff($today)->y;
            }
            
            $bloodBag['donor'] = [
                'surname' => $donor['surname'] ?? '',
                'first_name' => $donor['first_name'] ?? '',
                'middle_name' => $donor['middle_name'] ?? '',
                'birthdate' => !empty($donor['birthdate']) ? date('d/m/Y', strtotime($donor['birthdate'])) : '',
                'age' => $age,
                'sex' => $donor['sex'] ?? '',
                'civil_status' => $donor['civil_status'] ?? ''
            ];
        } else {
            // Default empty donor info if not found
            $bloodBag['donor'] = [
                'surname' => 'Not Found',
                'first_name' => '',
                'middle_name' => '',
                'birthdate' => '',
                'age' => '',
                'sex' => '',
                'civil_status' => ''
            ];
        }
        
        $bloodInventory[] = $bloodBag;
    }
}


// If no records found, keep the bloodInventory array empty
if (empty($bloodInventory)) {
    $bloodInventory = [];
}

// Pagination settings
$itemsPerPage = 10;
$totalItems = count($bloodInventory);
$totalPages = ceil($totalItems / $itemsPerPage);
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Ensure current page is valid
if ($currentPage < 1) {
    $currentPage = 1;
} else if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
}

// Calculate the starting index for the current page
$startIndex = ($currentPage - 1) * $itemsPerPage;

// Get the subset of blood inventory for the current page
$currentPageInventory = array_slice($bloodInventory, $startIndex, $itemsPerPage);

// Count stats for display
$validBags = 0;
$expiredBags = 0;
$totalBags = 0; // Initialize to 0 instead of count($bloodInventory)

// Calculate inventory statistics
$availableTypes = [];
$expiringBags = 0;

$today = new DateTime();
$expiryLimit = (new DateTime())->modify('+7 days');

foreach ($bloodInventory as $bag) {
    // Add to total bags (sum of amount_taken) only if valid
    if (is_numeric($bag['bags']) && $bag['status'] == 'Valid') {
        $totalBags += floatval($bag['bags']); // Sum the amount values
        $validBags++;
    }
    
    // Track unique blood types
    if (!in_array($bag['blood_type'], $availableTypes)) {
        $availableTypes[] = $bag['blood_type'];
    }
    
    // Calculate expiration status
    $expirationDate = new DateTime($bag['expiration_date']);
    if ($today > $expirationDate) {
        $expiredBags++;
    } elseif ($expirationDate <= $expiryLimit) {
        $expiringBags++;
    }
}

// Convert to integer for display
$totalBags = (int)$totalBags;

// Get default sorting
$sortBy = "Default (Latest)";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Inventory</title>
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
}

.logout-container {
    padding-top: 20px;
    border-top: 1px solid #ddd;
    margin-top: auto;
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
                    <i class="fas fa-plus me-2"></i>Add Walk-in Donor
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

                    <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link active">
                        <span><i class="fas fa-tint"></i>Blood Bank</span>
                    </a>
                    <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link">
                        <span><i class="fas fa-list"></i>Requests</span>
                    </a>
                    <a href="Dashboard-Inventory-System-Handed-Over.php" class="nav-link">
                        <span><i class="fas fa-check"></i>Handover</span>
                    </a>
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
                        <p class="text-dark mb-0">
                            <strong><?php echo date('d F Y'); ?></strong> <i class="fas fa-calendar-alt ms-2"></i>
                        </p>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="search-container">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <select class="form-select category-select" id="searchCategory" style="max-width: 150px;">
                                        <option value="all">All Fields</option>
                                        <option value="blood_type">Blood Type</option>
                                        <option value="component">Component</option>
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
                    
                    <div class="row mb-4">
                        <!-- Blood Type Cards -->
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: A+</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'A+' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: A-</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'A-' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: B+</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'B+' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: B-</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'B-' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: O+</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'O+' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: O-</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'O-' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: AB+</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'AB+' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card p-3 h-100 blood-type-card">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: AB-</h5>
                                    <p class="card-text">Availability: 
                                        <span class="fw-bold"><?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'AB-' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></span>
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
                                    <th>Units</th>
                                    <th>Bag Type</th>
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
                                    <td><?php echo $bag['bags']; ?></td>
                                    <td><?php echo $bag['bag_type']; ?></td>
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
                                    <td colspan="7" class="text-center">No blood inventory records found. Please wait for an administrator to add data.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="Dashboard-Inventory-System-Bloodbank.php?page=<?php echo $currentPage - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            // Display limited number of page links
                            $maxPagesToShow = 5;
                            $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
                            $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                            
                            // Adjust start page if we're near the end
                            if ($endPage - $startPage + 1 < $maxPagesToShow && $startPage > 1) {
                                $startPage = max(1, $endPage - $maxPagesToShow + 1);
                            }
                            
                            // Show first page with ellipsis if needed
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="Dashboard-Inventory-System-Bloodbank.php?page=1">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            // Show page links
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                    <a class="page-link" href="Dashboard-Inventory-System-Bloodbank.php?page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; 
                            
                            // Show last page with ellipsis if needed
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="Dashboard-Inventory-System-Bloodbank.php?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                            }
                            ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="Dashboard-Inventory-System-Bloodbank.php?page=<?php echo $currentPage + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    
                    <!-- Showing entries information -->
                    <div class="text-center mt-2 mb-4">
                        <p class="text-muted">
                            Showing <?php echo min($totalItems, $startIndex + 1); ?> to <?php echo min($totalItems, $startIndex + $itemsPerPage); ?> of <?php echo $totalItems; ?> entries
                        </p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Donor Details Modal -->
    <div class="modal fade donor-details-modal" id="donorDetailsModal" tabindex="-1" aria-labelledby="donorDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="donorDetailsModalLabel">
                        <i class="fas fa-eye me-2"></i> View Blood Unit & Donor Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Blood Unit Information -->
                    <div class="donor-details-section">
                        <h3>Blood Unit Information</h3>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Serial Number</label>
                                    <input type="text" class="donor-details-form-control" id="modal-serial-number" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Blood Type</label>
                                    <input type="text" class="donor-details-form-control" id="modal-blood-type" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Amount (ml)</label>
                                    <input type="text" class="donor-details-form-control" id="modal-bags" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Collection Date</label>
                                    <input type="text" class="donor-details-form-control" id="modal-collection-date" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Expiration Date</label>
                                    <input type="text" class="donor-details-form-control" id="modal-expiration-date" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Donor Information -->
                    <div class="donor-details-section">
                        <h3>Donor Information</h3>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Surname</label>
                                    <input type="text" class="donor-details-form-control" id="modal-surname" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">First Name</label>
                                    <input type="text" class="donor-details-form-control" id="modal-first-name" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Middle Name</label>
                                    <input type="text" class="donor-details-form-control" id="modal-middle-name" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Birthdate</label>
                                    <input type="text" class="donor-details-form-control" id="modal-birthdate" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Age</label>
                                    <input type="text" class="donor-details-form-control" id="modal-age" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Sex</label>
                                    <input type="text" class="donor-details-form-control" id="modal-sex" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Civil Status</label>
                                    <input type="text" class="donor-details-form-control" id="modal-civil-status" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Donor Eligibility Status -->
                    <div class="donor-details-section">
                        <h3>Donor Eligibility Status</h3>
                        <div>
                            <span class="eligibility-status" id="modal-eligibility-status">INELIGIBLE</span>
                            <span id="modal-eligibility-message">Unknown status</span>
                        </div>
                        <div class="mt-2 text-muted" id="modal-eligibility-note">
                            Donor can donate blood now
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
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
        // Data for the current page
        const bloodInventory = <?php echo json_encode($bloodInventory); ?>;
        const currentPageInventory = <?php echo json_encode($currentPageInventory); ?>;
        const startIndex = <?php echo $startIndex; ?>;
        
        // Function to display donor details in modal
        function showDonorDetails(index) {
            // We need to use the real index from the full inventory array
            const realIndex = startIndex + index;
            const bag = bloodInventory[realIndex];
            const donor = bag.donor;
            
            // Blood Unit Information
            document.getElementById('modal-serial-number').value = bag.serial_number;
            document.getElementById('modal-blood-type').value = bag.blood_type;
            document.getElementById('modal-bags').value = bag.bags;
            document.getElementById('modal-collection-date').value = bag.collection_date;
            document.getElementById('modal-expiration-date').value = bag.expiration_date;
            
            // Donor Information
            document.getElementById('modal-surname').value = donor.surname;
            document.getElementById('modal-first-name').value = donor.first_name;
            document.getElementById('modal-middle-name').value = donor.middle_name;
            document.getElementById('modal-birthdate').value = donor.birthdate;
            document.getElementById('modal-age').value = donor.age;
            document.getElementById('modal-sex').value = donor.sex;
            document.getElementById('modal-civil-status').value = donor.civil_status;
            
            // Check eligibility status via AJAX
            fetchEligibilityStatus(bag.donor_id);
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('donorDetailsModal'));
            modal.show();
        }
        
        // Function to fetch eligibility status for a donor
        function fetchEligibilityStatus(donorId) {
            if (!donorId) {
                updateEligibilityStatus({
                    is_eligible: false,
                    status_message: 'Unknown status',
                    remaining_days: 0
                });
                return;
            }
            
            // AJAX call to get eligibility status
            fetch(`get_donor_eligibility.php?donor_id=${donorId}`)
                .then(response => response.json())
                .then(data => {
                    updateEligibilityStatus(data);
                })
                .catch(error => {
                    console.error('Error fetching eligibility:', error);
                    updateEligibilityStatus({
                        is_eligible: false,
                        status_message: 'Error retrieving status',
                        remaining_days: 0
                    });
                });
        }
        
        // Update the eligibility status in the modal
        function updateEligibilityStatus(data) {
            const statusElem = document.getElementById('modal-eligibility-status');
            const messageElem = document.getElementById('modal-eligibility-message');
            const noteElem = document.getElementById('modal-eligibility-note');
            
            if (data.is_eligible) {
                statusElem.textContent = 'ELIGIBLE';
                statusElem.style.backgroundColor = '#198754'; // green
            } else {
                statusElem.textContent = 'INELIGIBLE';
                statusElem.style.backgroundColor = '#dc3545'; // red
            }
            
            messageElem.textContent = data.status_message;
            
            if (data.remaining_days > 0) {
                noteElem.textContent = `Donor can donate after ${data.remaining_days} days`;
            } else {
                noteElem.textContent = 'Donor can donate blood now';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
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
                    window.location.href = '../../src/views/forms/donor-form.php';
                }, 1500);
            };
        });
    </script>
</body>
</html>