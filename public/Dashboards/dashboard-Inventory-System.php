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
// Track counted donor_ids for blood received
$seenApprovedDonorIds = [];
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
                // Only count each donor_id once
                if (!in_array($donation['donor_id'], $seenApprovedDonorIds)) {
                    $bloodReceivedCount++;
                    $seenApprovedDonorIds[] = $donation['donor_id'];
                }
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

// Track counted donor_ids for blood inventory
$seenInventoryDonorIds = [];

if (is_array($eligibilityData) && !empty($eligibilityData)) {
    foreach ($eligibilityData as $item) {
        // Skip if no serial number or if donor is in declined list
        if (empty($item['unit_serial_number']) || in_array($item['donor_id'], $declinedDonorIds)) {
            continue;
        }
        // Skip if donor_id already counted
        if (in_array($item['donor_id'], $seenInventoryDonorIds)) {
            continue;
        }
        $seenInventoryDonorIds[] = $item['donor_id'];
        
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
        
        // Calculate expiration date (35 days from collection)
        $collectionDate = new DateTime($item['collection_start_time']);
        $expirationDate = clone $collectionDate;
        $expirationDate->modify('+35 days');
        
        // Only include bags with amount_taken > 0 and not expired
        $amount_taken = $bloodCollectionData && isset($bloodCollectionData['amount_taken']) ? intval($bloodCollectionData['amount_taken']) : 0;
        $isExpired = (new DateTime() > $expirationDate);
        
        // Create blood bag entry
        $bloodBag = [
            'eligibility_id' => $item['eligibility_id'],
            'donor_id' => $item['donor_id'],
            'serial_number' => $item['unit_serial_number'],
            'blood_type' => $item['blood_type'],
            'bags' => $amount_taken,
            'bag_type' => $item['blood_bag_type'] ?: 'Standard',
            'collection_date' => $collectionDate->format('Y-m-d'),
            'expiration_date' => $expirationDate->format('Y-m-d'),
            'status' => $isExpired ? 'Expired' : 'Valid',
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
    if ($bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
        $bloodType = $bag['blood_type'] ?? '';
        if (isset($bloodByType[$bloodType])) {
            $bloodByType[$bloodType] += floatval($bag['bags']);
        }
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

// --- Pending Donors Alert Setup ---
include_once __DIR__ . '/module/donation_pending.php';
$pendingDonorsCount = isset($pendingDonations) && is_array($pendingDonations) ? count($pendingDonations) : 0;

$maxCapacity = 800;
$totalUnits = $bloodInStockCount;
$totalPercentage = $maxCapacity > 0 ? min(($totalUnits / $maxCapacity) * 100, 100) : 0;
$statusClass = $totalPercentage < 30 ? 'critical' : ($totalPercentage < 50 ? 'warning' : 'healthy');
$statusText = $totalPercentage < 30 ? 'CRITICAL LOW' : ($totalPercentage < 50 ? 'WARNING' : 'HEALTHY');
$statusColor = $statusClass === 'critical' ? '#dc2626' : ($statusClass === 'warning' ? '#d97706' : '#16a34a');

// Notification logic
$notifications = [];
if ($pendingDonorsCount > 0) {
    $notifications[] = [
        'type' => 'pending',
        'icon' => 'fa-clock',
        'title' => 'Pending Donors',
        'message' => "There are <b>$pendingDonorsCount</b> donor(s) pending approval.",
        'color' => '#b38b00',
        'bg' => '#fffbe6',
    ];
}
$notifications[] = [
    'type' => 'inventory',
    'icon' => $statusClass === 'critical' ? 'fa-exclamation-triangle' : ($statusClass === 'warning' ? 'fa-exclamation-circle' : 'fa-check-circle'),
    'title' => 'Inventory Status',
    'message' => "Blood bank inventory is at <b>$statusText</b> (" . round($totalPercentage) . '%)',
    'color' => $statusColor,
    'bg' => $statusClass === 'critical' ? '#ffeaea' : ($statusClass === 'warning' ? '#fff7e6' : '#e6fff2'),
];
$notifCount = count($notifications);
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
    height: 800px;
    min-height: 400px;
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
    font-size: 1.5rem;
    font-weight: 500;
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

/* --- Sticky Alerts & Blood Alert Styles (copied from hospital dashboard for consistency) --- */
.sticky-alerts {
    position: fixed;
    top: 150px;
    right: 32px;
    z-index: 1000;
    width: 370px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.sticky-alerts.hidden {
    animation: slideOutRight 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
.sticky-alerts:not(.hidden) {
    animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}
.blood-alert {
    position: relative;
    margin: 0;
    box-shadow: 0 4px 16px rgba(148,16,34,0.08), 0 1.5px 4px rgba(0,0,0,0.04);
    border-radius: 14px;
    border-left: 6px solid #b38b00;
    background: #fffbe6;
    padding: 20px 24px 20px 20px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    color: #b38b00;
    cursor: pointer;
    min-width: 320px;
    max-width: 370px;
    transition: all 0.3s ease;
    animation: slideInRight 0.3s ease;
}
.blood-alert.fade-out {
    animation: slideOutRight 0.3s ease forwards;
}
.blood-alert .notif-icon {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4em;
    background: #fffbe6;
    color: #b38b00;
    box-shadow: 0 1px 4px rgba(148,16,34,0.08);
    margin-right: 6px;
}
.blood-alert .notif-content { flex: 1; }
.blood-alert .notif-title {
    font-weight: 700;
    font-size: 1.08em;
    margin-bottom: 2px;
}
.blood-alert .notif-close {
    position: absolute;
    top: 10px;
    right: 14px;
    font-size: 1.1em;
    color: #aaa;
    background: none;
    border: none;
    cursor: pointer;
    transition: color 0.2s;
}
.blood-alert .notif-close:hover { color: #941022; }
.blood-alert.fade-out { opacity: 0; transform: translateX(60px); }
@keyframes slideInRight {
    from { opacity: 0; transform: translateX(60px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes slideOutRight {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(60px); }
}
.total-overview-card {
    background: #fff;
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.inventory-header {
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(0,0,0,0.08);
}
.inventory-title {
    color: #2c3e50;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
}
.percentage-display {
    margin-top: 1rem;
}
.percentage-display .h2 {
    font-size: 2.5rem;
    font-weight: 700;
}
.critical-text { color: #dc2626; }
.warning-text { color: #d97706; }
.healthy-text { color: #16a34a; }
.status-badge {
    padding: 10px 20px;
    border-radius: 30px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background: #fff;
    transition: box-shadow 0.3s, background 0.3s, color 0.3s;
}
.status-badge.status-critical {
    color: #dc2626;
    background: #ffeaea;
    animation: pulseCritical 1.2s infinite alternate;
}
.status-badge.status-warning {
    color: #d97706;
    background: #fff7e6;
    animation: pulseWarning 1.2s infinite alternate;
}
.status-badge.status-healthy {
    color: #16a34a;
    background: #e6fff2;
    animation: pulseHealthy 1.2s infinite alternate;
}
.status-badge i {
    font-size: 1.2rem;
}
@keyframes pulseCritical {
    0% { box-shadow: 0 0 0 0 #dc262680; }
    100% { box-shadow: 0 0 16px 4px #dc262640; }
}
@keyframes pulseWarning {
    0% { box-shadow: 0 0 0 0 #d9770680; }
    100% { box-shadow: 0 0 16px 4px #d9770640; }
}
@keyframes pulseHealthy {
    0% { box-shadow: 0 0 0 0 #16a34a80; }
    100% { box-shadow: 0 0 16px 4px #16a34a40; }
}
.inventory-progress-wrapper {
    margin-top: 1rem;
}
.inventory-progress {
    width: 100%;
    background: #f1f5f9;
    border-radius: 12px;
    height: 24px;
    position: relative;
}
.progress-fill.bg-critical {
    background: #dc2626;
}
.progress-fill.bg-warning {
    background: #d97706;
}
.progress-fill.bg-healthy {
    background: #16a34a;
}
.compact-progress-card {
    border-radius: 18px;
    box-shadow: 0 8px 32px 0 rgba(220,38,38,0.10), 0 1.5px 4px rgba(0,0,0,0.04);
    margin-bottom: 0.5rem;
}
.compact-progress-card .card-body {
    padding: 0.75rem 1.5rem !important;
}
.percentage-display .h4 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 0;
}
.status-badge {
    font-size: 1.05rem;
    padding: 8px 20px;
}
.inventory-progress {
    height: 18px !important;
    border-radius: 10px !important;
}
.progress-fill.bg-critical {
    background: #dc2626;
}
.progress-fill.bg-warning {
    background: #d97706;
}
.progress-fill.bg-healthy {
    background: #16a34a;
}
.alert-danger {
    background: #ffeaea !important;
    color: #dc2626 !important;
    border: none !important;
    border-radius: 10px !important;
    box-shadow: 0 2px 8px rgba(220,38,38,0.06);
    font-size: 1.08rem;
}
.progress-thresholds {
    height: 32px;
    position: relative;
}
.threshold-marker {
    z-index: 2;
}
.threshold-label {
    display: inline-block;
    min-width: 38px;
    text-align: center;
    background: #fff;
    color: #888;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 12px;
    padding: 2px 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.threshold-line {
    width: 2px;
    height: 18px;
    background: #e0e0e0;
    margin: 2px auto 0 auto;
    border-radius: 2px;
}
    </style>
    <!-- Iconify for custom icons -->
    <script src="https://code.iconify.design/3/3.1.0/iconify.min.js"></script>
</head>
<body>
    <!-- Notification Bell and Alerts -->
    <?php if ($notifCount > 0): ?>
    <button class="notifications-toggle" id="notificationsToggle" style="position: fixed; top: 100px; right: 32px; z-index: 1100; display: none; background: none; border: none; outline: none; align-items: center; justify-content: center; padding: 0; width: 56px; height: 56px; border-radius: 50%; box-shadow: 0 2px 8px rgba(148,16,34,0.08); background: #fff; transition: box-shadow 0.2s;">
        <i class="fas fa-bell" style="font-size: 2em; color: #941022; position: relative;"></i>
        <span class="badge rounded-pill" id="notifBadge" style="position: absolute; top: 10px; right: 10px; background: #dc3545; color: #fff; border: 2px solid #fff; font-size: 1em; font-weight: 700; padding: 2px 7px; border-radius: 12px; box-shadow: 0 1px 4px rgba(220,53,69,0.12); display:none; animation: pulseBadge 1.2s infinite; min-width: 24px; text-align: center;">0</span>
    </button>
    <div class="sticky-alerts" id="stickyAlerts">
        <?php foreach ($notifications as $notif): ?>
        <div class="blood-alert alert" role="alert" data-notif-id="<?php echo $notif['type']; ?>" style="background: <?php echo $notif['bg']; ?>; color: <?php echo $notif['color']; ?>; border-left: 6px solid <?php echo $notif['color']; ?>;">
            <span class="notif-icon" style="background: #fff; color: <?php echo $notif['color']; ?>;"><i class="fas <?php echo $notif['icon']; ?>"></i></span>
            <div class="notif-content">
                <div class="notif-title"><?php echo $notif['title']; ?></div>
                <div><?php echo $notif['message']; ?></div>
            </div>
            <button class="notif-close" title="Dismiss" aria-label="Dismiss">&times;</button>
        </div>
        <?php endforeach; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const stickyAlerts = document.getElementById('stickyAlerts');
        const notificationsToggle = document.getElementById('notificationsToggle');
        const notifBadge = document.getElementById('notifBadge');
        let autoHideTimeout;
        let dismissedNotifs = [];
        function getAlerts() {
            return Array.from(document.querySelectorAll('.blood-alert'));
        }
        function showBell() {
            notificationsToggle.style.display = 'flex';
            setTimeout(() => notificationsToggle.classList.add('show'), 10);
            notifBadge.textContent = '<?php echo $notifCount; ?>';
            notifBadge.style.display = 'inline-block';
        }
        function hideBell() {
            notificationsToggle.classList.remove('show');
            notificationsToggle.style.display = 'none';
            notifBadge.style.display = 'none';
        }
        function hideAlert(alert) {
            alert.classList.add('fade-out');
            setTimeout(() => { alert.style.display = 'none';
                if (getAlerts().every(a => a.style.display === 'none')) {
                    showBell();
                }
            }, 500);
            dismissedNotifs.push(alert.getAttribute('data-notif-id'));
        }
        getAlerts().forEach(alert => {
            alert.querySelector('.notif-close').addEventListener('click', function(e) {
                e.stopPropagation();
                hideAlert(alert);
            });
            alert.addEventListener('click', function(e) {
                if (e.target.classList.contains('notif-close')) return;
                hideAlert(alert);
            });
            alert.style.cursor = 'pointer';
        });
        notificationsToggle.addEventListener('click', function() {
            getAlerts().forEach(alert => {
                if (dismissedNotifs.includes(alert.getAttribute('data-notif-id'))) {
                    alert.style.display = '';
                    alert.classList.remove('fade-out');
                }
            });
            dismissedNotifs = [];
            stickyAlerts.style.display = '';
            hideBell();
            clearTimeout(autoHideTimeout);
            autoHideTimeout = setTimeout(() => {
                getAlerts().forEach(alert => hideAlert(alert));
            }, 7000);
        });
        autoHideTimeout = setTimeout(() => {
            getAlerts().forEach(alert => hideAlert(alert));
        }, 7000);
    });
    </script>
    <?php endif; ?>

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
                <div class="position-sticky">
                    <div class="d-flex align-items-center ps-1 mb-4 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" style="width: 65px; height: 65px; object-fit: contain;">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                    
                    <ul class="nav flex-column">
                        <a href="dashboard-Inventory-System.php" class="nav-link active">
                            <span><i class="fas fa-home"></i>Home</span>
                        </a>
                        
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#bloodDonationsCollapse" role="button" aria-expanded="false" aria-controls="bloodDonationsCollapse" onclick="event.preventDefault();">
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
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#hospitalRequestsCollapse" role="button" aria-expanded="false" aria-controls="hospitalRequestsCollapse" onclick="event.preventDefault();">
                            <span><i class="fas fa-list"></i>Hospital Requests</span>
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <div class="collapse" id="hospitalRequestsCollapse">
                            <div class="collapse-menu">
                                <a href="Dashboard-Inventory-System-Hospital-Request.php?status=requests" class="nav-link">Requests</a>
                                <a href="Dashboard-Inventory-System-Handed-Over.php?status=accepted" class="nav-link">Approved</a>
                                <a href="Dashboard-Inventory-System-Handed-Over.php?status=handedover" class="nav-link">Handed Over</a>
                                <a href="Dashboard-Inventory-System-Handed-Over.php?status=declined" class="nav-link">Declined</a>
                            </div>
                        </div>
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
                        <p class="text-dark mb-0">
                        <strong><?php echo date('d F Y'); ?></strong> <i class="fas fa-calendar-alt ms-2"></i>
</p>
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
                        <span class="inventory-system-stats-label">Blood Inventory</span>
                        <span class="inventory-system-stats-value"><?php echo $bloodInStockCount; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card inventory-system-stats-card">
                <div class="card-body inventory-system-stats-body">
                    <div class="inventory-system-stats-container">
                        <span class="inventory-system-stats-label">Blood Donors</span>
                        <span class="inventory-system-stats-value"><?php echo $bloodReceivedCount; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Compact Blood Bank Inventory Status Progress Bar (Full Width, with Enhanced Thresholds and Bar) -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card total-overview-card compact-progress-card mb-2" style="box-shadow: 0 8px 32px 0 rgba(220,38,38,0.10), 0 1.5px 4px rgba(0,0,0,0.04); border-radius: 18px;">
                <div class="card-body py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="percentage-display d-flex align-items-end">
                            <span class="h4 mb-0 fw-bold <?php echo $statusClass; ?>-text" style="font-size: 2.2rem; line-height: 1;"><?php echo round($totalPercentage); ?>%</span>
                        </div>
                        <div class="status-indicator">
                            <div class="status-badge status-<?php echo $statusClass; ?> animate-badge" style="font-size: 1.05rem; padding: 8px 20px;">
                                <i class="fas <?php echo $totalPercentage < 30 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                                <span><?php echo $statusText; ?></span>
                            </div>
                        </div>
                    </div>
                    <!-- Threshold Markers and Lines -->
                    <div class="progress-thresholds position-relative mb-1" style="height: 48px;">
                        <!-- 30% Marker and Line -->
                        <div class="threshold-marker" style="position: absolute; left: 30%; top: 0; text-align: center; width: 60px; z-index: 2;">
                            <span class="threshold-label">30%</span>
                            <div class="threshold-line-extended"></div>
                        </div>
                        <!-- 50% Marker and Line -->
                        <div class="threshold-marker" style="position: absolute; left: 50%; top: 0; text-align: center; width: 60px; transform: translateX(-50%); z-index: 2;">
                            <span class="threshold-label">50%</span>
                            <div class="threshold-line-extended"></div>
                        </div>
                    </div>
                    <div class="inventory-progress-wrapper" style="margin-top: 0.5rem;">
                        <div class="inventory-progress position-relative mb-0 enhanced-progress-bar">
                            <div class="progress-track" style="width: 100%; height: 100%; position: absolute;">
                                <div class="progress-fill bg-<?php echo $statusClass; ?>" style="width: <?php echo $totalPercentage; ?>%; height: 100%; border-radius: 24px; transition: width 0.6s;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<style>
.compact-progress-card {
    border-radius: 18px;
    box-shadow: 0 8px 32px 0 rgba(220,38,38,0.10), 0 1.5px 4px rgba(0,0,0,0.04);
    margin-bottom: 0.5rem;
}
.compact-progress-card .card-body {
    padding: 0.75rem 1.5rem !important;
}
.percentage-display .h4 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 0;
}
.status-badge {
    font-size: 1.05rem;
    padding: 8px 20px;
}
.enhanced-progress-bar {
    height: 28px !important;
    border-radius: 8px;
    background-color: #f8f9fa;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    position: relative;
}
.inventory-progress {
    height: 28px !important;
    border-radius: 24px !important;
    overflow: hidden;
}
.progress-fill.bg-critical {
    border-radius: 8px !important;
    transition: width 0.6s;
    background: #dc2626;
}
.progress-fill.bg-warning {
    border-radius: 8px !important;
    transition: width 0.6s;
    background: #d97706;
}
.progress-fill.bg-healthy {
    border-radius: 8px !important;
    transition: width 0.6s;
    background: #16a34a;
}
.alert-danger {
    background: #ffeaea !important;
    color: #dc2626 !important;
    border: none !important;
    border-radius: 10px !important;
    box-shadow: 0 2px 8px rgba(220,38,38,0.06);
    font-size: 1.08rem;
}
.progress-thresholds {
    height: 48px;
    position: relative;
}
.threshold-marker {
    z-index: 2;
}
.threshold-label {
    display: inline-block;
    min-width: 38px;
    text-align: center;
    background: #fff;
    color: #888;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 12px;
    padding: 2px 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.threshold-line-extended {
    width: 4px;
    height: 44px;
    background: linear-gradient(to bottom, #e0e0e0 80%, #f1f5f9 100%);
    margin: 2px auto 0 auto;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
</style>
                    <!-- Available Blood per Unit Section -->
                    <div class="mb-5">
                        <h5 class="mb-4" style="font-weight: 600;">Available Blood per Unit</h5>
                        <div class="row g-4">
                            <!-- Blood Type Cards -->
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type O+</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'O+' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type A+</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'A+' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type B+</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'B+' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type AB+</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'AB+' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Second row: O-, A-, B-, AB- -->
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type O-</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'O-' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type A-</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'A-' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type B-</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'B-' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type AB-</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php 
                                            $count = 0;
                                            foreach ($bloodInventory as $bag) {
                                                if ($bag['blood_type'] == 'AB-' && $bag['status'] == 'Valid' && is_numeric($bag['bags'])) {
                                                    $count += floatval($bag['bags']);
                                                }
                                            }
                                            echo (int)$count;
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
// Show critical alert if status is critical
if ($statusClass === 'critical') {
    // List all blood types that are critically low
    $criticalTypes = [];
    foreach ($bloodByType as $type => $count) {
        if ($count < 30) $criticalTypes[] = $type;
    }
    if (!empty($criticalTypes)) {
        echo '<div class="alert alert-danger mt-3 mb-0 p-3 d-flex align-items-center" style="background: #ffeaea; color: #dc2626; border-radius: 10px; border: none; box-shadow: 0 2px 8px rgba(220,38,38,0.06);">';
        echo '<i class="fas fa-exclamation-circle me-3" style="font-size: 1.5rem;"></i>';
        echo '<div><strong>Critical Alert</strong><br>';
        echo implode(', ', $criticalTypes) . ' blood types require immediate attention!';
        echo '</div></div>';
    }
}
?>
                    <!-- GIS Mapping Section -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3" style="margin-top: 30px;">
                            <div class="d-flex align-items-center">
                                <span class="iconify me-2" data-icon="mdi:map-marker" style="font-size: 1.5rem; color: #941022; vertical-align: middle;"></span>
                                <h5 class="mb-0" style="font-weight: 600;">GIS Mapping</h5>
                            </div>
                            <div class="filters d-flex gap-3">
                                <select id="bloodTypeFilter" class="form-select form-select-sm">
                                    <option value="all">All Blood Types</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-9">
                                <div id="map" class="bg-light rounded-3" style="height: 677px; width: 100%; max-width: 100%; margin: 0 auto; border: 1px solid #eee;"></div>
                            </div>
                            <div class="col-md-3 dashboard-gis-sidebar">
                                <div class="card mb-3">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#summaryCollapse">
                                        <h6 class="card-title mb-0">Summary</h6>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div id="summaryCollapse" class="collapse show">
                                        <div class="card-body">
                                            <div class="summary-item" id="totalDonors">Total Donors: 0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-3" >
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center" style="cursor: pointer; " data-bs-toggle="collapse" data-bs-target="#locationsCollapse">
                                        <h6 class="card-title mb-0">Top Donor Locations</h6>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <div id="locationsCollapse" class="collapse show">
                                        <div class="card-body">
                                            <ul class="location-list list-unstyled" id="locationList" >
                                                <!-- Dynamically filled -->
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card" id="locationActionsCard">
                                    <div class="card-body p-3">
                                        <h6 class="mb-3">Blood Drive Actions</h6>
                                        <form id="bloodDriveForm">
                                            <div class="mb-2">
                                                <label for="selectedLocation" class="form-label">Selected Location</label>
                                                <input type="text" class="form-control" id="selectedLocation" name="selectedLocation" readonly placeholder="Select a location from Top Donor Locations">
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <label for="driveDate" class="form-label">Date</label>
                                                    <input type="date" class="form-control" id="driveDate" name="driveDate">
                                                </div>
                                                <div class="col-6">
                                                    <label for="driveTime" class="form-label">Time</label>
                                                    <input type="time" class="form-control" id="driveTime" name="driveTime">
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-danger w-50" id="notifyDonorsBtn" disabled>Notify Donors</button>
                                                <button type="button" class="btn btn-danger w-50" id="scheduleDriveBtn" disabled>Schedule Blood Drive</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add required CSS -->
                    <style>
                        /* Hide markers visually but keep their functionality */
                        .leaflet-marker-icon,
                        .leaflet-marker-shadow {
                            display: none !important;
                        }
                        
                        /* Existing styles */
                        .location-list {
                            max-height: 144px;
                            overflow-y: auto;
                            scrollbar-width: thin;
                            scrollbar-color: #dc3545 #f8f9fa;
                            padding-right: 5px;
                        }
                        .location-list::-webkit-scrollbar {
                            width: 6px;
                        }
                        .location-list::-webkit-scrollbar-track {
                            background: #f8f9fa;
                            border-radius: 3px;
                        }
                        .location-list::-webkit-scrollbar-thumb {
                            background-color: #dc3545;
                            border-radius: 3px;
                        }
                        .location-list li {
                            height: 40px;
                            padding: 8px 12px;
                            border-bottom: 1px solid #eee;
                            transition: background 0.2s;
                            cursor: pointer;
                            margin-bottom: 8px;
                            display: flex;
                            align-items: center;
                        }
                        .location-list li:last-child {
                            margin-bottom: 0;
                        }
                        .location-list li:hover {
                            background: #ffeaea;
                        }
                        .location-list li .d-flex {
                            width: 100%;
                        }
                        .summary-item {
                            margin-bottom: 8px;
                            font-size: 14px;
                        }
                        .card-header .fa-chevron-down {
                            transition: transform 0.3s;
                        }
                        .card-header[aria-expanded="true"] .fa-chevron-down {
                            transform: rotate(180deg);
                        }
                    </style>

                    <!-- Add Leaflet CSS and JS -->
                    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

                    <?php
                    // STEP 1: Get ALL successful collections (no time filter)
                    $eligibilityResponse = supabaseRequest("eligibility?select=eligibility_id,donor_id,blood_type,collection_successful&collection_successful=eq.true");
                    
                    $eligibilityData = [];
                    if (isset($eligibilityResponse['data'])) {
                        $eligibilityData = $eligibilityResponse['data'];
                    }

                    // Count ALL successful collections
                    $totalDonorCount = count($eligibilityData);

                    // STEP 2: Track two separate sets of data:
                    // 1. City counts for Top Donors - using ALL eligibility data
                    // 2. Full address data for heatmap - using filtered data if needed
                    $cityDonorCounts = []; // For Top Donors list - NO TIME FILTER
                    $heatmapData = []; // For the heatmap

                    // Process Top Donor Locations first - using ALL eligibility data
                    foreach ($eligibilityData as $eligibility) {
                        // Get donor's address data for city counting
                        $donorFormResponse = supabaseRequest(
                            'donor_form?select=permanent_address,office_address&donor_id=eq.' . $eligibility['donor_id']
                        );

                        if (isset($donorFormResponse['data']) && !empty($donorFormResponse['data'])) {
                            $donorForm = $donorFormResponse['data'][0];
                            
                            // Get the address (office first, then permanent)
                            $address = !empty($donorForm['office_address']) ? $donorForm['office_address'] : $donorForm['permanent_address'];
                            
                            // For Top Donors: Extract city name
                            $iloiloCities = [
                                'Oton', 'Pavia', 'Leganes', 'Santa Barbara', 'San Miguel', 
                                'Cabatuan', 'Maasin', 'Janiuay', 'Pototan', 'Dumangas',
                                'Zarraga', 'New Lucena', 'Alimodian', 'Leon', 'Tubungan',
                                'Iloilo City'
                            ];

                            $cityFound = false;
                            foreach ($iloiloCities as $cityName) {
                                if (stripos($address, $cityName) !== false) {
                                    if (!isset($cityDonorCounts[$cityName])) {
                                        $cityDonorCounts[$cityName] = 0;
                                    }
                                    $cityDonorCounts[$cityName]++;
                                    $cityFound = true;
                                    break;
                                }
                            }

                            // If no city was found in the address, count it as unidentified
                            if (!$cityFound) {
                                if (!isset($cityDonorCounts['Unidentified Location'])) {
                                    $cityDonorCounts['Unidentified Location'] = 0;
                                }
                                $cityDonorCounts['Unidentified Location']++;
                            }
                        }
                    }

                    // Function to clean and standardize address
                    function standardizeAddress($address) {
                        // List of known municipalities/cities in Iloilo
                        $municipalities = [
                            'Pototan', 'Oton', 'Pavia', 'Leganes', 'Santa Barbara', 'San Miguel',
                            'Cabatuan', 'Maasin', 'Janiuay', 'Dumangas', 'Zarraga', 'New Lucena',
                            'Alimodian', 'Leon', 'Tubungan', 'Iloilo City'
                        ];

                        // Clean the address
                        $address = trim($address);
                        
                        // Check for municipality conflicts
                        $foundMunicipalities = [];
                        foreach ($municipalities as $muni) {
                            if (stripos($address, $muni) !== false) {
                                $foundMunicipalities[] = $muni;
                            }
                        }

                        // If we found multiple municipalities, use the first one
                        if (count($foundMunicipalities) > 1) {
                            $primaryLocation = $foundMunicipalities[0];
                            // Remove other municipalities from address
                            foreach (array_slice($foundMunicipalities, 1) as $muni) {
                                $address = str_ireplace($muni, '', $address);
                            }
                            // Ensure primary location is at the end
                            $address = str_ireplace($primaryLocation, '', $address);
                            $address = trim($address, ' ,.') . ', ' . $primaryLocation;
                        }

                        // Add province and country if not present
                        if (stripos($address, 'Iloilo') === false) {
                            $address .= ', Iloilo';
                        }
                        if (stripos($address, 'Philippines') === false) {
                            $address .= ', Philippines';
                        }

                        // Clean up multiple commas and spaces
                        $address = preg_replace('/\s+/', ' ', $address);
                        $address = preg_replace('/,+/', ',', $address);
                        $address = trim($address, ' ,');

                        return $address;
                    }

                    // Now process heatmap data separately
                    foreach ($eligibilityData as $eligibility) {
                        $donorFormResponse = supabaseRequest(
                            'donor_form?select=permanent_address&donor_id=eq.' . $eligibility['donor_id']
                        );

                        if (isset($donorFormResponse['data']) && !empty($donorFormResponse['data'])) {
                            $donorForm = $donorFormResponse['data'][0];
                            
                            // For Heatmap: Use permanent address
                            if (!empty($donorForm['permanent_address'])) {
                                $standardizedAddress = standardizeAddress($donorForm['permanent_address']);
                                $heatmapData[] = [
                                    'original_address' => $donorForm['permanent_address'],
                                    'address' => $standardizedAddress
                                ];
                            }
                        }
                    }

                    // Sort cities by donor count
                    arsort($cityDonorCounts);
                    ?>

                    <script>
                    // Initialize map centered on Iloilo
                    const map = L.map('map').setView([10.7202, 122.5621], 11); // Centered on Iloilo City

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 18,
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);

                    let heatLayer = null;
                    let markers = L.layerGroup().addTo(map);
                    const points = [];

                    // Elements for filters
                    const bloodTypeFilter = document.getElementById('bloodTypeFilter');

                    // Summary fields
                    const totalDonorsEl = document.getElementById('totalDonors');
                    const locationListEl = document.getElementById('locationList');

                    // Separate data for Top Donors and Heatmap
                    const cityDonorCounts = <?php echo json_encode($cityDonorCounts); ?>;
                    const heatmapData = <?php echo json_encode($heatmapData); ?>;

                    // Function to geocode address using Nominatim with fallback attempts
                    async function geocodeAddress(location) {
                        const addresses = [
                            location.address,
                            // Try without specific landmarks
                            location.address.replace(/,([^,]*Hospital[^,]*),/, ','),
                            // Try just the municipality and province
                            location.address.split(',').slice(-3).join(',')
                        ];

                        for (const address of addresses) {
                            try {
                                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&countrycodes=ph`);
                                const data = await response.json();
                                
                                if (data && data.length > 0) {
                                    // Filter results to prioritize Iloilo locations
                                    const iloiloResults = data.filter(result => 
                                        result.display_name.toLowerCase().includes('iloilo')
                                    );
                                    
                                    const result = iloiloResults.length > 0 ? iloiloResults[0] : data[0];
                                    
                                    return {
                                        lat: parseFloat(result.lat),
                                        lng: parseFloat(result.lon),
                                        display_name: result.display_name
                                    };
                                }
                            } catch (error) {
                                console.error('Geocoding error:', error);
                                continue;
                            }
                            // Add delay between attempts
                            await delay(1000);
                        }
                        return null;
                    }

                    // Function to add delay between geocoding requests
                    function delay(ms) {
                        return new Promise(resolve => setTimeout(resolve, ms));
                    }

                    // Process all addresses and update map
                    async function processAddresses() {
                        markers.clearLayers();
                        if (heatLayer) {
                            map.removeLayer(heatLayer);
                        }

                        const points = [];
                        for (const location of heatmapData) {
                            // Add delay to respect Nominatim's usage policy
                            await delay(1000);
                            
                            const coords = await geocodeAddress(location);
                            if (coords) {
                                points.push([coords.lat, coords.lng, 0.8]);
                                
                                // Add marker with popup showing both original and geocoded address
                                const marker = L.marker([coords.lat, coords.lng])
                                    .bindPopup(`
                                        <strong>Original Address:</strong><br>
                                        ${location.original_address}<br><br>
                                        <strong>Geocoded Address:</strong><br>
                                        ${coords.display_name}
                                    `);
                                markers.addLayer(marker);
                            } else {
                                console.warn('Failed to geocode address:', location.address);
                            }
                        }

                        if (points.length > 0) {
                            heatLayer = L.heatLayer(points, {
                                radius: 35,
                                blur: 20,
                                maxZoom: 13,
                                minOpacity: 0.4,
                                gradient: {
                                    0.2: 'blue',
                                    0.4: 'lime',
                                    0.6: 'orange',
                                    0.8: 'red'
                                }
                            }).addTo(map);
                        }
                    }

                    function updateTopDonorLocations() {
                        // Update Top Donors list - this is independent of filters
                        locationListEl.innerHTML = '';
                        if (Object.keys(cityDonorCounts).length === 0) {
                            locationListEl.innerHTML = '<li class="p-2">No locations found</li>';
                        } else {
                            // Separate identified and unidentified locations
                            const identifiedLocations = [];
                            const unidentifiedLocations = [];

                            Object.entries(cityDonorCounts)
                                .sort((a, b) => b[1] - a[1])
                                .forEach(([city, count]) => {
                                    const li = document.createElement('li');
                                    li.className = 'p-2 mb-2 border-bottom';
                                    li.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>${city}</strong>
                                            <span class="badge bg-danger">${count}</span>
                                        </div>`;
                                    
                                    // Check if location is identified (has a known city name)
                                    if (city.toLowerCase() === 'unidentified location') {
                                        unidentifiedLocations.push(li);
                                    } else {
                                        identifiedLocations.push(li);
                                    }
                                });

                            // First add all identified locations
                            identifiedLocations.forEach(li => locationListEl.appendChild(li));

                            // Then add unidentified locations if any exist
                            if (unidentifiedLocations.length > 0) {
                                unidentifiedLocations.forEach(li => locationListEl.appendChild(li));
                            }
                        }
                    }

                    function updateDisplay() {
                        // Update total count
                        totalDonorsEl.textContent = 'Total Donors: <?php echo $totalDonorCount; ?>';
                        
                        // Update Top Donor Locations (independent of filters)
                        updateTopDonorLocations();
                        
                        // Process addresses and update heatmap
                        processAddresses();
                    }

                    // Add event listeners - these only affect the heatmap
                    bloodTypeFilter.addEventListener('change', processAddresses);

                    // Initialize
                    updateDisplay();
                    </script>
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
                
                // Get current URL to pass as source for later return
                const currentUrl = window.location.href;
                
                // Clean up any previous donor registration session data
                fetch('../../src/views/forms/clean_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'clean_before_new_registration' })
                }).then(() => {
                    setTimeout(() => {
                        // Redirect to donor form with current page as source
                        window.location.href = '../../src/views/forms/donor-form-modal.php?source=' + encodeURIComponent(currentUrl);
                    }, 1500);
                }).catch(() => {
                    // If there's an error, continue anyway
                setTimeout(() => {
                        window.location.href = '../../src/views/forms/donor-form-modal.php?source=' + encodeURIComponent(currentUrl);
                }, 1500);
                });
            };
        });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Make Top Donor Locations pressable and connect to the form
        const locationListEl = document.getElementById('locationList');
        const selectedLocationInput = document.getElementById('selectedLocation');
        const notifyBtn = document.getElementById('notifyDonorsBtn');
        const scheduleBtn = document.getElementById('scheduleDriveBtn');
        const driveDate = document.getElementById('driveDate');
        const driveTime = document.getElementById('driveTime');

        // Add click event to each location
        Array.from(locationListEl.querySelectorAll('li')).forEach(li => {
            li.style.cursor = 'pointer';
            li.addEventListener('click', function() {
                // Remove highlight from all
                Array.from(locationListEl.querySelectorAll('li')).forEach(l => l.classList.remove('bg-light', 'fw-bold'));
                // Highlight selected
                this.classList.add('bg-light', 'fw-bold');
                // Set location input
                selectedLocationInput.value = this.querySelector('strong').textContent;
                // Enable buttons if date and time are set
                checkEnableButtons();
            });
        });
        // Enable buttons only if location, date, and time are set
        function checkEnableButtons() {
            const hasLocation = selectedLocationInput.value.trim() !== '';
            const hasDate = driveDate.value.trim() !== '';
            const hasTime = driveTime.value.trim() !== '';
            notifyBtn.disabled = !(hasLocation && hasDate && hasTime);
            scheduleBtn.disabled = !(hasLocation && hasDate && hasTime);
        }
        driveDate.addEventListener('input', checkEnableButtons);
        driveTime.addEventListener('input', checkEnableButtons);
        // Placeholder actions
        notifyBtn.addEventListener('click', function() {
            alert('Notify donors in ' + selectedLocationInput.value + ' on ' + driveDate.value + ' at ' + driveTime.value);
        });
        scheduleBtn.addEventListener('click', function() {
            alert('Schedule blood drive in ' + selectedLocationInput.value + ' on ' + driveDate.value + ' at ' + driveTime.value);
        });
    });
    </script>
</body>
</html>