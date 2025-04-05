<?php
session_start();
include_once '../../assets/conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check for correct role (example for admin dashboard)
$required_role = 1; // Admin role
if ($_SESSION['role_id'] !== $required_role) {
    header("Location: unauthorized.php");
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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("API Error: " . $error);
        return [
            'code' => 0,
            'data' => null,
            'error' => "Connection error: $error"
        ];
    }
    
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $decoded = json_decode($response, true);
        return [
            'code' => $httpCode,
            'data' => $decoded
        ];
    } else {
        error_log("HTTP Error $httpCode: " . substr($response, 0, 500));
        return [
            'code' => $httpCode,
            'data' => null,
            'error' => "HTTP Error $httpCode: " . substr($response, 0, 500)
        ];
    }
}

// Function to query direct SQL
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
        error_log("Query SQL Error: " . $error);
        return ['error' => $error];
    }
    
    return json_decode($response, true);
}

// ----------------------------------------------------
// PART 1: GET HOSPITAL REQUESTS COUNT
// ----------------------------------------------------
$hospitalRequestsCount = 0;
$bloodRequestsResponse = supabaseRequest("blood_requests?status=eq.Pending&select=request_id");
if (isset($bloodRequestsResponse['data']) && is_array($bloodRequestsResponse['data'])) {
    $hospitalRequestsCount = count($bloodRequestsResponse['data']);
}

// ----------------------------------------------------
// PART 2: GET BLOOD RECEIVED COUNT
// ----------------------------------------------------
// This represents the number of approved donations (donors from the approved dropdown)
$bloodReceivedCount = 0;

// First, get all donors with non-accepted remarks in physical examination
$declinedDonorIdsForApproved = [];

// Query physical examination for non-accepted remarks
$physicalExamQueryApproved = curl_init();
curl_setopt_array($physicalExamQueryApproved, [
    CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?remarks=neq.Accepted&select=donor_id,donor_form_id",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Content-Type: application/json"
    ],
]);

$physicalExamResponseApproved = curl_exec($physicalExamQueryApproved);
curl_close($physicalExamQueryApproved);

if ($physicalExamResponseApproved) {
    $physicalExamRecordsApproved = json_decode($physicalExamResponseApproved, true);
    if (is_array($physicalExamRecordsApproved)) {
        foreach ($physicalExamRecordsApproved as $record) {
            if (!empty($record['donor_id'])) {
                $declinedDonorIdsForApproved[] = $record['donor_id'];
            }
            if (!empty($record['donor_form_id'])) {
                $declinedDonorIdsForApproved[] = $record['donor_form_id'];
            }
        }
    }
}

// Remove duplicates
$declinedDonorIdsForApproved = array_unique($declinedDonorIdsForApproved);

// Now get approved eligibility records
$approvedDonationsResponse = supabaseRequest("eligibility?status=eq.approved&select=eligibility_id,donor_id");
if (isset($approvedDonationsResponse['data']) && is_array($approvedDonationsResponse['data'])) {
    // Filter out donors with non-accepted physical examination remarks
    foreach ($approvedDonationsResponse['data'] as $donation) {
        if (!in_array($donation['donor_id'], $declinedDonorIdsForApproved)) {
            // Double-check physical examination remarks for this donor
            $physicalExamCheckApproved = curl_init();
            curl_setopt_array($physicalExamCheckApproved, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $donation['donor_id'] . "&select=remarks",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ],
            ]);
            
            $physicalExamCheckResponseApproved = curl_exec($physicalExamCheckApproved);
            curl_close($physicalExamCheckApproved);
            
            $skipDonorApproved = false;
            if ($physicalExamCheckResponseApproved) {
                $physicalExamCheckRecordsApproved = json_decode($physicalExamCheckResponseApproved, true);
                if (is_array($physicalExamCheckRecordsApproved) && !empty($physicalExamCheckRecordsApproved)) {
                    foreach ($physicalExamCheckRecordsApproved as $examRecord) {
                        $remarks = $examRecord['remarks'] ?? '';
                        if ($remarks !== 'Accepted' && !empty($remarks)) {
                            $skipDonorApproved = true;
                            break;
                        }
                    }
                }
            }
            
            if (!$skipDonorApproved) {
                $bloodReceivedCount++;
            }
        }
    }
}

// ----------------------------------------------------
// PART 3: GET BLOOD IN STOCK COUNT
// ----------------------------------------------------
// This represents the current total units in the blood bank
$bloodInStockCount = 0;
$today = date('Y-m-d');

// Fetch blood inventory data from eligibility table
$bloodInventory = [];

// First, get all donors with non-accepted remarks in physical examination
$declinedDonorIds = [];

// Query physical examination for non-accepted remarks (anything that's not "Accepted")
$physicalExamQuery = curl_init();
curl_setopt_array($physicalExamQuery, [
    CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?remarks=neq.Accepted&select=donor_id,donor_form_id,remarks",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Content-Type: application/json"
    ],
]);

$physicalExamResponse = curl_exec($physicalExamQuery);
curl_close($physicalExamQuery);

if ($physicalExamResponse) {
    $physicalExamRecords = json_decode($physicalExamResponse, true);
    if (is_array($physicalExamRecords)) {
        foreach ($physicalExamRecords as $record) {
            // Add both donor_id and donor_form_id to exclusion list for comprehensive matching
            if (!empty($record['donor_id'])) {
                $declinedDonorIds[] = $record['donor_id'];
            }
            if (!empty($record['donor_form_id'])) {
                $declinedDonorIds[] = $record['donor_form_id'];
            }
        }
    }
}

// Remove duplicates
$declinedDonorIds = array_unique($declinedDonorIds);

// Query eligibility table for valid blood units
$eligibilityData = querySQL(
    'eligibility', 
    'eligibility_id,donor_id,blood_type,donation_type,blood_bag_type,collection_successful,unit_serial_number,collection_start_time,start_date,end_date,status,blood_collection_id',
    ['collection_successful' => 'eq.true']
);

if (is_array($eligibilityData) && !empty($eligibilityData)) {
    foreach ($eligibilityData as $item) {
        // Skip if no serial number or if donor is in declined list
        if (empty($item['unit_serial_number']) || in_array($item['donor_id'], $declinedDonorIds)) {
            continue;
        }
        
        // Double-check physical examination remarks for this specific donor
        $physicalExamCheck = curl_init();
        curl_setopt_array($physicalExamCheck, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=eq." . $item['donor_id'] . "&select=remarks",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ],
        ]);
        
        $physicalExamCheckResponse = curl_exec($physicalExamCheck);
        curl_close($physicalExamCheck);
        
        // Skip this donor if they have non-accepted remarks
        $skipDonor = false;
        if ($physicalExamCheckResponse) {
            $physicalExamCheckRecords = json_decode($physicalExamCheckResponse, true);
            if (is_array($physicalExamCheckRecords) && !empty($physicalExamCheckRecords)) {
                foreach ($physicalExamCheckRecords as $examRecord) {
                    $remarks = $examRecord['remarks'] ?? '';
                    if ($remarks !== 'Accepted' && !empty($remarks)) {
                        $skipDonor = true;
                        break;
                    }
                }
            }
        }
        
        if ($skipDonor) {
            continue;
        }
        
        // Get blood collection data to get amount_taken
        $bloodCollectionData = null;
        if (!empty($item['blood_collection_id'])) {
            $bloodCollectionData = querySQL('blood_collection', '*', ['blood_collection_id' => 'eq.' . $item['blood_collection_id']]);
            $bloodCollectionData = isset($bloodCollectionData[0]) ? $bloodCollectionData[0] : null;
        }
        
        // Calculate expiration date (42 days from collection)
        $collectionDate = new DateTime($item['collection_start_time']);
        $expirationDate = clone $collectionDate;
        $expirationDate->modify('+42 days');
        
        // Create blood bag entry
        $bloodBag = [
            'eligibility_id' => $item['eligibility_id'],
            'donor_id' => $item['donor_id'],
            'serial_number' => $item['unit_serial_number'],
            'blood_type' => $item['blood_type'],
            'bags' => $bloodCollectionData && isset($bloodCollectionData['amount_taken']) ? $bloodCollectionData['amount_taken'] : '1',
            'bag_type' => $item['blood_bag_type'] ?: 'Standard',
            'collection_date' => $collectionDate->format('Y-m-d'),
            'expiration_date' => $expirationDate->format('Y-m-d'),
            'status' => (new DateTime() > $expirationDate) ? 'Expired' : 'Valid',
            'eligibility_status' => $item['status'],
            'eligibility_end_date' => $item['end_date'],
        ];
        
        $bloodInventory[] = $bloodBag;
    }
}

// Count total valid units (not expired)
foreach ($bloodInventory as $bag) {
    if ($bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
        $bloodInStockCount += floatval($bag['bags']);
    }
}

// Round to nearest integer
$bloodInStockCount = (int)$bloodInStockCount;

// ----------------------------------------------------
// PART 4: GET BLOOD AVAILABILITY BY TYPE
// ----------------------------------------------------
// Initialize blood type counts
$bloodByType = [
    'A+' => 0,
    'A-' => 0,
    'B+' => 0,
    'B-' => 0,
    'O+' => 0,
    'O-' => 0,
    'AB+' => 0,
    'AB-' => 0
];

// Calculate blood type counts from blood inventory
foreach ($bloodInventory as $bag) {
    $bloodType = $bag['blood_type'] ?? '';
    if (isset($bloodByType[$bloodType]) && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
        $bloodByType[$bloodType] += floatval($bag['bags']);
    }
}

// Convert to integers
foreach ($bloodByType as $type => $count) {
    $bloodByType[$type] = (int)$count;
}

// Verify blood in stock equals sum of blood type counts
$totalFromTypes = array_sum($bloodByType);
if ($totalFromTypes != $bloodInStockCount) {
    // If there's a discrepancy, use the sum of blood types
    $bloodInStockCount = $totalFromTypes;
}

// Debug log counts
error_log("Hospital Requests Count: " . $hospitalRequestsCount);
error_log("Blood Received Count: " . $bloodReceivedCount);
error_log("Blood In Stock Count: " . $bloodInStockCount);
error_log("Blood By Type: " . json_encode($bloodByType));

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
    margin-top: 30px !important;
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

/* Welcome Section */
.dashboard-welcome-text {
    display: block !important;  
    margin-top: 10vh !important; /* Reduced margin */
    padding: 10px 0;
    font-size: 24px;
    font-weight: bold;
    color: #333;
    text-align: left; /* Ensures it's left-aligned */
}

/* Card Styling */
.card {
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    min-height: 120px;
    flex-grow: 1;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 12px rgba(194, 194, 194, 0.7);
}
/* Last card to have a */
/* Add margin to the last column in the row */
#quick-insights{
    margin-bottom: 50px !important;
}
/* Progress Bar Styling */
.progress {
    height: 10px;
    border-radius: 5px;
}

.progress-bar {
    border-radius: 5px;
}

/* GIS Map Styling */
#map {
    height: 600px;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    border-radius: 10px;
    background-color: #f8f9fa;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 50px;
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

    .dashboard-welcome-text {
        margin-top: 5vh;
        font-size: 20px;
    }

    .card {
        min-height: 100px;
        font-size: 14px;
    }

    #map {
        height: 350px;
    }
}

@media (max-width: 480px) {
    /* Optimize layout for mobile */
    .dashboard-welcome-text {
        margin-top: 3vh;
        font-size: 18px;
    }

    #map {
        height: 250px;
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

/* Add these styles to your existing CSS */
.card {
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.bg-danger.bg-opacity-10 {
    background-color: rgba(220, 53, 69, 0.1) !important;
}

.text-danger {
    color: #dc3545 !important;
}

h6 {
    color: #333;
    font-weight: 600;
}

.card-subtitle {
    font-size: 0.9rem;
}

.text-muted {
    color: #6c757d !important;
}

#map {
    border: 1px solid #dee2e6;
}

.content-wrapper {
    background: #fff;
    box-shadow: 0 0 15px rgba(0,0,0,0.05);
    margin-top: 80px;
    border-radius: 12px;
    padding: 30px;
}

.bg-danger.bg-opacity-10 {
    background-color: #FFE9E9 !important;
}

.text-danger {
    color: #941022 !important;
}

.card {
    border-radius: 8px;
}

.shadow-sm {
    box-shadow: 0 2px 5px rgba(0,0,0,0.05) !important;
}

/* Add new styles for statistics cards */
.statistics-card {
    transition: transform 0.2s;
}

.statistics-card:hover {
    transform: translateY(-2px);
}

/* Statistics Cards Styling */
.inventory-system-stats-container {
    display: flex;
    flex-direction: column;
}

.inventory-system-stats-card {
    background-color: #FFE9E9;
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.inventory-system-stats-body {
    padding: 1.5rem;
}

.inventory-system-stats-label {
    color: #941022;
    font-size: 1.875rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.inventory-system-stats-value {
    color: #941022;
    font-size: 5rem;
    font-weight: 600;
    line-height: 1;
    margin-top: 10px;
    text-align: right;
    margin-right: 18px;
}

/* Add these styles in the style section */
.inventory-system-blood-card {
    background-color: #ffffff;  /* pure white background */
    border-radius: 8px;
    border: none;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin: 5px 0;
    position: relative;
    overflow: hidden;
}

.inventory-system-blood-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.inventory-system-blood-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 0.5rem;
}

.inventory-system-blood-availability {
    font-size: 2rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0;
}

/* Blood type specific colors - thicker left border */
.blood-type-a-pos { border-left: 5px solid #8B0000; }  /* dark red */
.blood-type-a-neg { border-left: 5px solid #8B0000; }  /* dark red */
.blood-type-b-pos { border-left: 5px solid #FF8C00; }  /* orange */
.blood-type-b-neg { border-left: 5px solid #FF8C00; }  /* orange */
.blood-type-o-pos { border-left: 5px solid #00008B; }  /* dark blue */
.blood-type-o-neg { border-left: 5px solid #00008B; }  /* dark blue */
.blood-type-ab-pos { border-left: 5px solid #9D94FF; } /* purple */
.blood-type-ab-neg { border-left: 5px solid #8A82E8; } /* darker purple */

.welcome-heading {
    font-weight: 700;
    font-size: 2rem;
    margin-bottom: 1rem;
}

    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <a href="../../src/views/forms/donor-form.php" class="btn btn-danger">
                    <i class="fas fa-plus me-2"></i>Register Donor
                </a>
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
                    
                    <ul class="nav flex-column">
                        <a href="dashboard-Inventory-System.php" class="nav-link active">
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
                        <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link">
                            <span><i class="fas fa-list"></i>Requests</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Handed-Over.php" class="nav-link">
                            <span><i class="fas fa-check"></i>Handover</span>
                        </a>
                        <a href="../../assets/php_func/logout.php" class="nav-link">
                                <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                        </a>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="welcome-heading mb-0" style="font-weight: 700; font-size: 2rem;">Welcome back!</h2>
                        <span class="text-muted"><?php echo date('d F Y'); ?> ■</span>
                    </div>

                    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card inventory-system-stats-card">
                <div class="card-body inventory-system-stats-body">
                    <div class="inventory-system-stats-container">
                        <span class="inventory-system-stats-label">Hospital Requests</span>
                        <span class="inventory-system-stats-value"><?php echo $hospitalRequestsCount; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card inventory-system-stats-card">
                <div class="card-body inventory-system-stats-body">
                    <div class="inventory-system-stats-container">
                        <span class="inventory-system-stats-label">Blood Received</span>
                        <span class="inventory-system-stats-value"><?php echo $bloodReceivedCount; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card inventory-system-stats-card">
                <div class="card-body inventory-system-stats-body">
                    <div class="inventory-system-stats-container">
                        <span class="inventory-system-stats-label">In Stock</span>
                        <span class="inventory-system-stats-value"><?php echo $bloodInStockCount; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

                    <!-- Available Blood per Unit Section -->
                    <div class="mb-5">
                        <h5 class="mb-4" style="font-weight: 600;">Available Blood per Unit</h5>
                        <div class="row g-4">
                            <!-- First row: A+, A-, B+, B- -->
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type A+</h5>
                                        <p class="inventory-system-blood-availability"><?php echo $bloodByType['A+'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type A-</h5>
                                        <p class="inventory-system-blood-availability"><?php echo $bloodByType['A-'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type B+</h5>
                                        <p class="inventory-system-blood-availability"><?php echo $bloodByType['B+'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type B-</h5>
                                        <p class="inventory-system-blood-availability"><?php echo $bloodByType['B-'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Second row: O+, O-, AB+, AB- -->
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type O+</h5>
                                        <p class="inventory-system-blood-availability"><?php echo $bloodByType['O+'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type O-</h5>
                                        <p class="inventory-system-blood-availability"><?php echo $bloodByType['O-'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type AB+</h5>
                                        <p class="inventory-system-blood-availability"><?php echo $bloodByType['AB+'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type AB-</h5>
                                        <p class="inventory-system-blood-availability"><?php echo $bloodByType['AB-'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- GIS Mapping Section -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <span class="me-2" style="display: inline-block; width: 12px; height: 12px; background-color: #333; margin-right: 8px;"></span>
                            <h5 class="mb-0" style="font-weight: 600;">GIS Mapping</h5>
                        </div>
                        <div id="map" class="bg-light rounded-3" style="height: 600px; width: 100%; max-width: 100%; margin: 0 auto; border: 1px solid #eee;">
                            <!-- Map will be loaded here -->
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize modals and add button functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'), {
                backdrop: false,
                keyboard: false
            });
        });
    </script>
</body>
</html>