<?php
// Prevent browser caching (server-side caching is handled below)
header('Vary: Accept-Encoding');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
include_once '../../assets/conn/db_conn.php';
require_once 'module/optimized_functions.php';

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

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/module/optimized_functions.php';

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// OPTIMIZATION: Smart caching with intelligent change detection
$cacheKey = 'home_dashboard_v3_' . date('Y-m-d'); // Daily cache key (bumped to v3)
$cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.json';
$cacheMetaFile = sys_get_temp_dir() . '/' . $cacheKey . '_meta.json';

// Cache enabled with improved change detection
$useCache = false;
$needsFullRefresh = false;
$needsCountRefresh = false;

// TEMPORARY: Force cache refresh for GIS debugging
if (isset($_GET['debug_gis']) && $_GET['debug_gis'] == '1') {
    if (file_exists($cacheFile)) unlink($cacheFile);
    if (file_exists($cacheMetaFile)) unlink($cacheMetaFile);
    error_log("CACHE: GIS debug mode - cache files deleted");
}

// Manual cache refresh option
if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    // Force cache refresh
    if (file_exists($cacheFile)) unlink($cacheFile);
    if (file_exists($cacheMetaFile)) unlink($cacheMetaFile);
    error_log("CACHE: Manual refresh requested - cache files deleted");
}

// Check if cache exists and is valid
if (file_exists($cacheFile) && file_exists($cacheMetaFile)) {
    $cacheMeta = json_decode(file_get_contents($cacheMetaFile), true);
    $cacheAge = time() - $cacheMeta['timestamp'];
    
    // Simplified change detection - only check cache age
    $hasChanges = false;
    
    // PERFORMANCE FIX: Extended cache lifetime from 15 minutes to 2 hours
    if ($cacheAge > 7200) { // 2 hours (was 900 = 15 minutes)
        $hasChanges = true;
    }
    
    if (!$hasChanges) {
        // No changes detected - use full cache
        $useCache = true;
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cachedData)) {
            $hospitalRequestsCount = $cachedData['hospitalRequestsCount'] ?? 0;
            $bloodReceivedCount = $cachedData['bloodReceivedCount'] ?? 0;
            $bloodInStockCount = $cachedData['bloodInStockCount'] ?? 0;
            $bloodByType = $cachedData['bloodByType'] ?? [];
            $bloodInventory = $cachedData['bloodInventory'] ?? [];
            $totalDonorCount = $cachedData['totalDonorCount'] ?? 0;
            $cityDonorCounts = $cachedData['cityDonorCounts'] ?? [];
            $heatmapData = $cachedData['heatmapData'] ?? [];
            $donorLookup = $cachedData['donorLookup'] ?? [];
            
            // Debug cache loading
            error_log("CACHE LOADED (v3) - Total Donor Count: " . $totalDonorCount . " (Age: " . round($cacheAge/60) . " mins)");
            error_log("CACHE LOADED - City Donor Counts: " . json_encode($cityDonorCounts));
            error_log("CACHE LOADED - Heatmap Data Count: " . count($heatmapData));
            
            // Skip to the end of data processing
            goto cache_loaded;
        } else {
            // Cache data is invalid or empty - force refresh
            error_log("CACHE: Invalid or empty cache data - forcing refresh");
            $useCache = false;
            if (file_exists($cacheFile)) unlink($cacheFile);
            if (file_exists($cacheMetaFile)) unlink($cacheMetaFile);
        }
    }
}

// OPTIMIZED: Simplified data fetching using only necessary tables
// ----------------------------------------------------
// PART 1: GET HOSPITAL REQUESTS COUNT (OPTIMIZED)
// ----------------------------------------------------
$hospitalRequestsCount = 0;
$bloodRequestsResponse = supabaseRequest("blood_requests?status=eq.Pending&select=request_id");
if (isset($bloodRequestsResponse['data']) && is_array($bloodRequestsResponse['data'])) {
    $hospitalRequestsCount = count($bloodRequestsResponse['data']);
}

// ----------------------------------------------------
// PART 2-3: GET BLOOD RECEIVED, INVENTORY, AND BLOOD TYPE COUNTS IN ONE PASS
// ----------------------------------------------------
$bloodInventory = [];
$bloodInStockCount = 0;
$bloodReceivedCount = 0;
$seenDonorIds = [];
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

// Single query without ORDER BY for speed
$bloodBankUnitsResponse = supabaseRequest("blood_bank_units?select=unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,created_at,updated_at&unit_serial_number=not.is.null");
$bloodBankUnitsData = isset($bloodBankUnitsResponse['data']) ? $bloodBankUnitsResponse['data'] : [];

if (is_array($bloodBankUnitsData) && !empty($bloodBankUnitsData)) {
    foreach ($bloodBankUnitsData as $item) {
        // Count unique donors (successful donations)
        $donorId = $item['donor_id'];
        if (!isset($seenDonorIds[$donorId])) {
            $seenDonorIds[$donorId] = true;
            $bloodReceivedCount++;
        }

        // Parse dates once
        $collectionDate = new DateTime($item['collected_at']);
        $expirationDate = new DateTime($item['expires_at']);
        $today = new DateTime();

        // Skip expired or handed over units for inventory/availability
        $isExpired = ($today > $expirationDate);
        $isHandedOver = ($item['status'] === 'handed_over');
        if ($isExpired || $isHandedOver) {
            continue;
        }

        // Count available inventory
        $bloodInStockCount += 1;

        // Update blood type availability
        $bt = $item['blood_type'] ?? '';
        if (isset($bloodByType[$bt])) {
            $bloodByType[$bt] += 1;
        }

        // Build simplified inventory list used by UI
        $bloodInventory[] = [
            'unit_id' => $item['unit_id'],
            'donor_id' => $donorId,
            'serial_number' => $item['unit_serial_number'],
            'blood_type' => $bt,
            'bags' => 1,
            'bag_type' => $item['bag_type'] ?: 'Standard',
            'bag_brand' => $item['bag_brand'] ?: 'N/A',
            'collection_date' => $collectionDate->format('Y-m-d'),
            'expiration_date' => $expirationDate->format('Y-m-d'),
            'status' => 'Valid',
            'unit_status' => $item['status'],
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
        ];
    }
}

// ----------------------------------------------------
// PART 5: PROCESS GIS MAPPING DATA (DEFERRED FOR PERFORMANCE)
// ----------------------------------------------------
// PERFORMANCE OPTIMIZATION: GIS data will be loaded via AJAX after page load
// This significantly reduces initial page load time
$cityDonorCounts = [];
$heatmapData = [];
$totalDonorCount = count($seenDonorIds); // Quick count from already-loaded data
$postgisAvailable = false;

// OPTIMIZATION: Performance logging and caching
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
addPerformanceHeaders($executionTime, $bloodReceivedCount, "Dashboard - Hospital Requests: {$hospitalRequestsCount}, Blood In Stock: {$bloodInStockCount}");

// BLOOD AVAILABILITY BY TYPE is already computed in the single pass above ($bloodByType)

// OPTIMIZATION: Save to cache with metadata
$cacheData = [
    'hospitalRequestsCount' => $hospitalRequestsCount,
    'bloodReceivedCount' => $bloodReceivedCount,
    'bloodInStockCount' => $bloodInStockCount,
    'bloodByType' => $bloodByType,
    'bloodInventory' => $bloodInventory,
    'totalDonorCount' => $totalDonorCount,
    'cityDonorCounts' => [],  // Will be loaded via AJAX
    'heatmapData' => [],      // Will be loaded via AJAX
    'donorLookup' => [],
    'timestamp' => time()
];

// Simplified cache metadata (no expensive API calls for change detection)
$cacheMeta = [
    'timestamp' => time(),
    'version' => 'v1'
];

file_put_contents($cacheFile, json_encode($cacheData));
file_put_contents($cacheMetaFile, json_encode($cacheMeta));

// Cache loaded marker
cache_loaded:

// --- Pending Donors Alert Setup (DEFERRED FOR PERFORMANCE) ---
// PERFORMANCE OPTIMIZATION: Pending donors will be loaded via AJAX
$pendingDonorsCount = 0;

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
    <!-- Iconify for custom icons (deferred) -->
    <script defer src="https://code.iconify.design/3/3.1.0/iconify.min.js"></script>
</head>
<body>
    <!-- Notification Bell and Alerts -->
    <button class="notifications-toggle" id="notificationsToggle" style="position: fixed; top: 100px; right: 32px; z-index: 1100; display: none; background: none; border: none; outline: none; align-items: center; justify-content: center; padding: 0; width: 56px; height: 56px; border-radius: 50%; box-shadow: 0 2px 8px rgba(148,16,34,0.08); background: #fff; transition: box-shadow 0.2s;">
        <i class="fas fa-bell" style="font-size: 2em; color: #941022; position: relative;"></i>
        <span class="badge rounded-pill" id="notifBadge" style="position: absolute; top: 10px; right: 10px; background: #dc3545; color: #fff; border: 2px solid #fff; font-size: 1em; font-weight: 700; padding: 2px 7px; border-radius: 12px; box-shadow: 0 1px 4px rgba(220,53,69,0.12); display:none; animation: pulseBadge 1.2s infinite; min-width: 24px; text-align: center;">0</span>
    </button>
    <div class="sticky-alerts" id="stickyAlerts">
        <!-- PERFORMANCE OPTIMIZATION: Pending donors notification loaded via AJAX -->
    </div>
    <script>
    // PERFORMANCE OPTIMIZATION: Load notifications asynchronously
    fetch('/RED-CROSS-THESIS/public/api/load-pending-donors-count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.pendingDonorsCount > 0) {
                // Create pending donors notification
                const alertDiv = document.createElement('div');
                alertDiv.className = 'blood-alert alert';
                alertDiv.setAttribute('data-notif-id', 'pending');
                alertDiv.style.cssText = 'background: #fffbe6; color: #b38b00; border-left: 6px solid #b38b00;';
                alertDiv.innerHTML = `
                    <span class="notif-icon" style="background: #fff; color: #b38b00;"><i class="fas fa-clock"></i></span>
                    <div class="notif-content">
                        <div class="notif-title">Pending Donors</div>
                        <div>There are <b>${data.pendingDonorsCount}</b> donor(s) pending approval.</div>
                    </div>
                    <button class="notif-close" title="Dismiss" aria-label="Dismiss">&times;</button>
                `;
                document.getElementById('stickyAlerts').appendChild(alertDiv);
                
                // Update badge
                document.getElementById('notifBadge').textContent = data.pendingDonorsCount;
                
                // Initialize notification handlers
                initNotifications();
            } else {
                // Still show inventory notification even if no pending donors
                initNotifications();
            }
        })
        .catch(error => {
            console.log('⚠️ Pending donors check failed:', error);
            initNotifications();
        });
    
    function initNotifications() {
        // Add inventory notification
        const inventoryAlert = document.createElement('div');
        inventoryAlert.className = 'blood-alert alert';
        inventoryAlert.setAttribute('data-notif-id', 'inventory');
        
        const statusClass = '<?php echo $statusClass; ?>';
        const statusText = '<?php echo $statusText; ?>';
        const totalPercentage = <?php echo round($totalPercentage); ?>;
        const statusColor = '<?php echo $statusColor; ?>';
        const statusBg = statusClass === 'critical' ? '#ffeaea' : (statusClass === 'warning' ? '#fff7e6' : '#e6fff2');
        const iconClass = statusClass === 'critical' ? 'fa-exclamation-triangle' : (statusClass === 'warning' ? 'fa-exclamation-circle' : 'fa-check-circle');
        
        inventoryAlert.style.cssText = `background: ${statusBg}; color: ${statusColor}; border-left: 6px solid ${statusColor};`;
        inventoryAlert.innerHTML = `
            <span class="notif-icon" style="background: #fff; color: ${statusColor};"><i class="fas ${iconClass}"></i></span>
            <div class="notif-content">
                <div class="notif-title">Inventory Status</div>
                <div>Blood bank inventory is at <b>${statusText}</b> (${totalPercentage}%)</div>
            </div>
            <button class="notif-close" title="Dismiss" aria-label="Dismiss">&times;</button>
        `;
        document.getElementById('stickyAlerts').appendChild(inventoryAlert);
        
        const stickyAlerts = document.getElementById('stickyAlerts');
        const notificationsToggle = document.getElementById('notificationsToggle');
        const notifBadge = document.getElementById('notifBadge');
        let autoHideTimeout;
        let dismissedNotifs = [];
        
        // Count total notifications
        const totalNotifs = document.querySelectorAll('.blood-alert').length;
        if (totalNotifs > 0) {
            notifBadge.textContent = totalNotifs;
            notifBadge.style.display = 'inline-block';
        }
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
    }
    </script>

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
                        <a href="dashboard-Inventory-System.php" class="nav-link active">
                            <span><i class="fas fa-home"></i>Home</span>
                        </a>
                        
                        <a href="dashboard-Inventory-System-list-of-donations.php" class="nav-link">
                            <span><i class="fas fa-users"></i>Donor Management</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
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
                            <?php
                            // Uniform calculation with Bloodbank page: count 1 per valid unit
                            $bloodTypeCounts = array_reduce($bloodInventory, function($carry, $bag) {
                                if ($bag['status'] == 'Valid' && isset($carry[$bag['blood_type']])) {
                                    $carry[$bag['blood_type']] += 1; // Each unit = 1 bag
                                }
                                return $carry;
                            }, [
                                'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
                                'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
                            ]);
                            ?>
                            
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type O+</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php echo (int)$bloodTypeCounts['O+']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type A+</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php echo (int)$bloodTypeCounts['A+']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type B+</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php echo (int)$bloodTypeCounts['B+']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-pos">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type AB+</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php echo (int)$bloodTypeCounts['AB+']; ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Second row: O-, A-, B-, AB- -->
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type O-</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php echo (int)$bloodTypeCounts['O-']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type A-</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php echo (int)$bloodTypeCounts['A-']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type B-</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php echo (int)$bloodTypeCounts['B-']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-neg">
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type AB-</h5>
                                        <p class="inventory-system-blood-availability">Availability: <?php echo (int)$bloodTypeCounts['AB-']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
// Show critical alert if status is critical
if ($statusClass === 'critical') {
    // List all blood types that are critically low (use uniform counts)
    $criticalTypes = [];
    foreach ($bloodTypeCounts as $type => $count) {
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

// Ensure $postgisAvailable is always defined before HTML output
if (!isset($postgisAvailable)) {
    $postgisAvailable = false;
}

// Final check: if we have PostGIS data, ensure the flag is set correctly
if (($totalDonorCount > 0 || !empty($heatmapData)) && !$postgisAvailable) {
    $postgisAvailable = true;
    if (isset($_GET['debug_gis'])) {
        echo "<!-- GIS Debug: Final PostGIS availability correction -->\n";
    }
}
?>
                    <!-- GIS Mapping Section -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3" style="margin-top: 30px;">
                            <div class="d-flex align-items-center">
                                <span class="iconify me-2" data-icon="mdi:map-marker" style="font-size: 1.5rem; color: #941022; vertical-align: middle;"></span>
                                <h5 class="mb-0" style="font-weight: 600;">GIS Mapping</h5>
                                <?php 
                                // Safety check for $postgisAvailable variable
                                if (!isset($postgisAvailable)) {
                                    $postgisAvailable = false;
                                }
                                
                                // Debug output
                                if (isset($_GET['debug_gis'])) {
                                    echo "<!-- Debug: postgisAvailable = " . ($postgisAvailable ? 'true' : 'false') . " -->\n";
                                }
                                
                                if ($postgisAvailable): ?>
                                    <span class="badge bg-success ms-2" title="PostGIS spatial indexing enabled">
                                        <i class="fas fa-database"></i> PostGIS
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning ms-2" title="PostGIS not available - using fallback method">
                                        <i class="fas fa-exclamation-triangle"></i> Fallback
                                    </span>
                                <?php endif; ?>
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
                                                <button type="button" class="btn btn-danger w-100" id="scheduleDriveBtn" disabled>Schedule Blood Drive</button>
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

                    <!-- Add Leaflet CSS and JS (OFFLINE) -->
                    <link rel="stylesheet" href="../../assets/css/node_modules/leaflet/dist/leaflet.css" />
                    <script src="../../assets/css/node_modules/leaflet/dist/leaflet.js"></script>
                    <script src="../../assets/css/node_modules/leaflet.heat/dist/leaflet-heat.js"></script>

                    <?php
                    // GIS data is now processed in the main data processing section above
                    // This ensures it's properly cached and available for the map
                    ?>

                    <script>
                    // PERFORMANCE FIX: Lazy load map initialization
                    let mapInitialized = false;
                    let map = null;
                    
                    // HILBERT CURVE SPATIAL SORTING ALGORITHM
                    function hilbertCurve(lat, lng, order = 16) {
                        // Normalize coordinates to [0, 1] range
                        const x = (lng + 180) / 360; // Longitude: -180 to 180 -> 0 to 1
                        const y = (lat + 90) / 180;  // Latitude: -90 to 90 -> 0 to 1
                        
                        // Convert to integer coordinates
                        const xi = Math.floor(x * (1 << order));
                        const yi = Math.floor(y * (1 << order));
                        
                        // Hilbert curve encoding
                        let d = 0;
                        for (let s = (1 << (order - 1)); s > 0; s >>= 1) {
                            const rx = (xi & s) > 0;
                            const ry = (yi & s) > 0;
                            d += s * s * ((3 * rx) ^ ry);
                            
                            if (ry === 0) {
                                if (rx === 1) {
                                    xi = (1 << order) - 1 - xi;
                                    yi = (1 << order) - 1 - yi;
                                }
                                [xi, yi] = [yi, xi];
                            }
                        }
                        return d;
                    }
                    
                    // SPATIAL SORTING FUNCTION
                    function spatialSort(locations) {
                        return locations.sort((a, b) => {
                            const keyA = hilbertCurve(a.lat, a.lng);
                            const keyB = hilbertCurve(b.lat, b.lng);
                            return keyA - keyB;
                        });
                    }

                    // Function to initialize map (called when user scrolls to map section)
                    function initializeMap() {
                        if (mapInitialized) return;
                        mapInitialized = true;
                        
                        console.log('🗺️ Initializing map...');
                        
                        // Initialize map centered on Iloilo
                        map = L.map('map').setView([10.7202, 122.5621], 11); // Centered on Iloilo City

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 18,
                            attribution: '&copy; OpenStreetMap contributors'
                        }).addTo(map);
                        
                        // Start processing addresses after map is initialized
                        updateDisplay();
                    }

                    let heatLayer = null;
                    let markers = null; // Will be initialized when map loads

                    // Elements for filters
                    const bloodTypeFilter = document.getElementById('bloodTypeFilter');

                    // Summary fields
                    const totalDonorsEl = document.getElementById('totalDonors');
                    const locationListEl = document.getElementById('locationList');
                    
                    // Coordinate usage counters
                    let databaseCount = 0;
                    let predefinedCount = 0;
                    let geocodedCount = 0;

                    // Separate data for Top Donors and Heatmap
                    let cityDonorCounts = {};
                    let heatmapData = [];
                    
                    // PERFORMANCE OPTIMIZATION: Load GIS data via AJAX after page loads
                    console.log('🚀 Loading GIS data in background...');
                    fetch('/RED-CROSS-THESIS/public/api/load-gis-data-dashboard.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                cityDonorCounts = data.cityDonorCounts || {};
                                heatmapData = data.heatmapData || [];
                                console.log('✅ GIS data loaded:', data.totalDonorCount, 'donors');
                                
                                // Update summary immediately
                                totalDonorsEl.textContent = 'Total Donors: ' + data.totalDonorCount;
                                updateTopDonorLocations();
                                
                                // If map is already initialized, update it
                                if (mapInitialized && map) {
                                    processAddresses();
                                }
                            }
                        })
                        .catch(error => {
                            console.log('⚠️ GIS data loading failed (will show empty map):', error);
                        });
                    
                    // Debug logging
                    console.log('Initial City Donor Counts:', cityDonorCounts);
                    console.log('Initial Heatmap Data:', heatmapData);
                    console.log('Initial Total Donor Count:', <?php echo $totalDonorCount; ?>);

                                         // OPTIMIZED: Pre-defined coordinates for common Iloilo locations
                     const locationCoordinates = {
                         'Iloilo City': { lat: 10.7202, lng: 122.5621 },
                         'Oton': { lat: 10.6933, lng: 122.4733 },
                         'Pavia': { lat: 10.7750, lng: 122.5444 },
                         'Leganes': { lat: 10.7833, lng: 122.5833 },
                         'Santa Barbara': { lat: 10.8167, lng: 122.5333 },
                         'San Miguel': { lat: 10.7833, lng: 122.4667 },
                         'Cabatuan': { lat: 10.8833, lng: 122.4833 },
                         'Maasin': { lat: 10.8833, lng: 122.4333 },
                         'Janiuay': { lat: 10.9500, lng: 122.5000 },
                         'Pototan': { lat: 10.9500, lng: 122.6333 },
                         'Dumangas': { lat: 10.8333, lng: 122.7167 },
                         'Zarraga': { lat: 10.8167, lng: 122.6000 },
                         'New Lucena': { lat: 10.8833, lng: 122.6000 },
                         'Alimodian': { lat: 10.8167, lng: 122.4333 },
                         'Leon': { lat: 10.7833, lng: 122.3833 },
                         'Tubungan': { lat: 10.7833, lng: 122.3333 },
                         'Passi': { lat: 11.1167, lng: 122.6333 },
                         'Lemery': { lat: 11.2167, lng: 122.9167 },
                         'Roxas': { lat: 11.1833, lng: 122.8833 },
                         'Mina': { lat: 10.9333, lng: 122.5833 },
                         'Barotac Nuevo': { lat: 10.9000, lng: 122.7000 },
                         'Barotac Viejo': { lat: 11.0500, lng: 122.8500 },
                         'Bingawan': { lat: 11.2333, lng: 122.5667 },
                         'Calinog': { lat: 11.1167, lng: 122.5000 },
                         'Carles': { lat: 11.5667, lng: 123.1333 },
                         'Concepcion': { lat: 11.2167, lng: 123.1167 },
                         'Dingle': { lat: 11.0000, lng: 122.6667 },
                         'Dueñas': { lat: 11.0667, lng: 122.6167 },
                         'Estancia': { lat: 11.4500, lng: 123.1500 },
                         'Guimbal': { lat: 10.6667, lng: 122.3167 },
                         'Igbaras': { lat: 10.7167, lng: 122.2667 },
                         'Javier': { lat: 11.0833, lng: 122.5667 },
                         'Lambunao': { lat: 11.0500, lng: 122.4667 },
                         'Miagao': { lat: 10.6333, lng: 122.2333 },
                         'Pilar': { lat: 11.4833, lng: 123.0000 },
                         'San Dionisio': { lat: 11.2667, lng: 123.0833 },
                         'San Enrique': { lat: 11.1000, lng: 122.6667 },
                         'San Joaquin': { lat: 10.5833, lng: 122.1333 },
                         'San Rafael': { lat: 11.1833, lng: 122.8333 },
                         'Santa Rita': { lat: 11.4500, lng: 122.9833 },
                         'Sara': { lat: 11.2500, lng: 123.0167 },
                         'Tigbauan': { lat: 10.6833, lng: 122.3667 }
                     };

                     // OPTIMIZED: Function to get coordinates with database priority
                     async function getCoordinates(location) {
                         // First priority: Use coordinates from database (PostGIS)
                         if (location.latitude && location.longitude && location.has_coordinates) {
                             return {
                                 lat: parseFloat(location.latitude),
                                 lng: parseFloat(location.longitude),
                                 display_name: location.address,
                                 source: 'database',
                                 accuracy: 'high'
                             };
                         }
                         
                         // Second priority: Try to extract city name from address for predefined coordinates
                         const address = location.address.toLowerCase();
                         
                         // Check if we have pre-defined coordinates for this location
                         for (const [city, coords] of Object.entries(locationCoordinates)) {
                             if (address.includes(city.toLowerCase())) {
                                 return {
                                     lat: coords.lat,
                                     lng: coords.lng,
                                     display_name: `${city}, Iloilo, Philippines`,
                                     source: 'predefined',
                                     accuracy: 'medium'
                                 };
                             }
                         }

                         // Last resort: Use geocoding as fallback (only for addresses without coordinates)
                         console.log(`⚠️ No coordinates found for: ${location.address}, using geocoding fallback`);
                         return await geocodeAddress(location);
                     }

                     // OPTIMIZED: Function to geocode address using server-side endpoint (no CORS issues)
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
                                 const response = await fetch('/RED-CROSS-THESIS/public/api/improved-geocode-address.php', {
                                     method: 'POST',
                                     headers: {
                                         'Content-Type': 'application/json'
                                     },
                                     body: JSON.stringify({ 
                                         address: address,
                                         donor_id: location.donor_id || null // Pass donor ID for database storage
                                     })
                                 });
                                 
                                 if (response.ok) {
                                     const data = await response.json();
                                     if (data && data.lat && data.lng) {
                                     return {
                                             lat: data.lat,
                                             lng: data.lng,
                                             display_name: data.display_name,
                                             source: data.source
                                         };
                                     }
                                 }
                            } catch (error) {
                                console.error('Geocoding error:', error);
                                continue;
                            }
                            // PERFORMANCE FIX: Reduced delay from 200ms to 100ms for faster processing
                            await delay(100);
                        }
                        return null;
                    }

                     // Function to add delay between geocoding requests
                     function delay(ms) {
                         return new Promise(resolve => setTimeout(resolve, ms));
                     }

                     // OPTIMIZED: Process addresses with automatic geocoding and spatial sorting
                     const coordinateCache = new Map();
                     let geocodingInProgress = false;
                     
                     async function processAddresses() {
                         // PERFORMANCE FIX: Don't process if map isn't initialized yet
                         if (!mapInitialized || !map) {
                             console.log('⚠️ Map not initialized yet, skipping address processing');
                             return;
                         }
                         
                         // Initialize markers layer if not exists
                         if (!markers) {
                             markers = L.layerGroup().addTo(map);
                         }
                         
                         markers.clearLayers();
                         if (heatLayer) {
                             map.removeLayer(heatLayer);
                         }

                         const points = [];
                         const batchSize = 25; // PERFORMANCE FIX: Increased from 10 to 25 for faster processing
                         const totalLocations = heatmapData.length;
                         
                         // Show loading indicator with geocoding status
                         const loadingDiv = document.createElement('div');
                         loadingDiv.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading map data...<br><small>Auto-geocoding missing coordinates...</small></div>';
                         document.getElementById('map').appendChild(loadingDiv);

                         // SPATIAL SORTING: Sort locations by Hilbert curve for better clustering
                         const sortedLocations = [...heatmapData];
                         let geocodedCount = 0;
                         let cachedCount = 0;

                         for (let i = 0; i < totalLocations; i += batchSize) {
                             const batch = sortedLocations.slice(i, i + batchSize);
                             
                             // Process batch with automatic geocoding
                             const batchPromises = batch.map(async (location) => {
                                 // Check cache first
                                 const cacheKey = location.address.toLowerCase();
                                 if (coordinateCache.has(cacheKey)) {
                                     cachedCount++;
                                     return {
                                         coords: coordinateCache.get(cacheKey),
                                         location: location
                                     };
                                 }
                                 
                                 const coords = await getCoordinates(location);
                                 if (coords) {
                                     // Cache the result
                                     coordinateCache.set(cacheKey, coords);
                                     
                                     // If this was a new geocoding (not from database), store it
                                     if (coords.source === 'geocoded' || coords.source === 'predefined') {
                                         geocodedCount++;
                                         // Store coordinates in database for future use
                                         try {
                                             await fetch('/RED-CROSS-THESIS/public/api/improved-geocode-address.php', {
                                                 method: 'POST',
                                                 headers: { 'Content-Type': 'application/json' },
                                                 body: JSON.stringify({ 
                                                     address: location.address,
                                                     donor_id: location.donor_id
                                                 })
                                             });
                                         } catch (e) {
                                             console.log('Background geocoding storage failed:', e);
                                         }
                                     }
                                     
                                     return {
                                         coords: coords,
                                         location: location
                                     };
                                 }
                                 return null;
                             });

                             const batchResults = await Promise.all(batchPromises);
                             
                             // Add results to points array and count coordinate sources
                             batchResults.forEach(result => {
                                 if (result) {
                                     points.push([result.coords.lat, result.coords.lng, 0.8]);
                                     
                                     // Count coordinate sources
                                     if (result.coords.source === 'database') {
                                         databaseCount++;
                                     } else if (result.coords.source === 'predefined') {
                                         predefinedCount++;
                                     } else {
                                         geocodedCount++;
                                     }
                                     
                                     // Add marker with popup
                                     const marker = L.marker([result.coords.lat, result.coords.lng])
                                         .bindPopup(`
                                             <strong>Original Address:</strong><br>
                                             ${result.location.original_address}<br><br>
                                             <strong>Geocoded Address:</strong><br>
                                             ${result.coords.display_name}<br><br>
                                             <small><em>Source: ${result.coords.source} (${result.coords.accuracy || 'unknown'} accuracy)</em></small>
                                         `);
                                     markers.addLayer(marker);
                                 }
                             });

                             // Update progress with coordinate usage stats
                             const progress = Math.min(((i + batchSize) / totalLocations) * 100, 100);
                             
                             loadingDiv.innerHTML = `
                                 <div class="text-center p-3">
                                     <i class="fas fa-spinner fa-spin"></i> Loading map data... ${Math.round(progress)}%<br>
                                     <small>Database: ${databaseCount} | Predefined: ${predefinedCount} | Geocoded: ${geocodedCount}</small>
                                 </div>
                             `;

                            // PERFORMANCE FIX: Reduced delay from 1000ms to 300ms for faster loading
                            if (i + batchSize < totalLocations) {
                                await delay(300); // 0.3 second delay (reduced from 1 second)
                            }
                        }

                         // Remove loading indicator
                         if (loadingDiv.parentNode) {
                             loadingDiv.parentNode.removeChild(loadingDiv);
                         }

                         // SPATIAL SORTING: Sort points by Hilbert curve before creating heatmap
                         const sortedPoints = spatialSort(points.map(p => ({ lat: p[0], lng: p[1], intensity: p[2] })));
                         const finalPoints = sortedPoints.map(p => [p.lat, p.lng, p.intensity]);

                         if (finalPoints.length > 0) {
                             heatLayer = L.heatLayer(finalPoints, {
                                 radius: 40,
                                 blur: 25,
                                 maxZoom: 13,
                                 minOpacity: 0.3,
                                 gradient: {
                                     0.2: 'blue',
                                     0.4: 'lime',
                                     0.6: 'orange',
                                     0.8: 'red'
                                 }
                             }).addTo(map);
                         }
                         
                         // Show completion message
                         if (geocodedCount > 0) {
                             console.log(`✅ Auto-geocoded ${geocodedCount} new addresses and cached them for future use!`);
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
                            
                            // Attach click event listeners to the newly created location items
                            attachLocationClickListeners();
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

                    // PERFORMANCE FIX: Lazy load map when user scrolls to it
                    const mapElement = document.getElementById('map');
                    const mapObserver = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting && !mapInitialized) {
                                console.log('🗺️ Map section visible, initializing...');
                                initializeMap();
                                mapObserver.disconnect(); // Stop observing after initialization
                            }
                        });
                    }, {
                        root: null,
                        rootMargin: '100px', // Start loading 100px before map is visible
                        threshold: 0.1
                    });
                    
                    if (mapElement) {
                        mapObserver.observe(mapElement);
                    }

                    // Initialize summary data (without map)
                    totalDonorsEl.textContent = 'Total Donors: <?php echo $totalDonorCount; ?>';
                    updateTopDonorLocations();
                    
                    // PERFORMANCE FIX: Disabled automatic background geocoding on page load
                    // This was causing significant performance issues during login
                    // Geocoding now happens on-demand when viewing the map
                    console.log('🚀 Dashboard loaded successfully (auto-geocoding disabled for performance)');
                    console.log('💡 Map will initialize when you scroll to it');
                    
                    // Optional: Uncomment below to enable manual geocoding with a button
                    // To manually trigger geocoding, add ?geocode=1 to the URL
                    if (window.location.search.includes('geocode=1')) {
                        console.log('🔍 Manual geocoding triggered...');
                        setTimeout(async () => {
                            try {
                                const response = await fetch('/RED-CROSS-THESIS/public/api/auto-geocode-missing.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ action: 'auto_geocode' })
                                });
                                
                                if (response.ok) {
                                    const result = await response.json();
                                    console.log('📊 Geocoding result:', result);
                                    
                                    if (result.successful > 0) {
                                        console.log(`✅ Successfully geocoded ${result.successful} missing addresses`);
                                        setTimeout(() => updateDisplay(), 1000);
                                    }
                                }
                            } catch (e) {
                                console.log('Background geocoding failed:', e);
                            }
                        }, 1000);
                    }

                    // Function to attach click event listeners to location items
                    function attachLocationClickListeners() {
                        const locationListEl = document.getElementById('locationList');
                        const selectedLocationInput = document.getElementById('selectedLocation');
                        const scheduleBtn = document.getElementById('scheduleDriveBtn');
                        const driveDate = document.getElementById('driveDate');
                        const driveTime = document.getElementById('driveTime');
                        
                        if (!locationListEl || !selectedLocationInput) {
                            console.log('Location elements not found');
                            return;
                        }
                        
                        Array.from(locationListEl.querySelectorAll('li')).forEach(li => {
                            // Remove any existing event listeners to avoid duplicates
                            li.replaceWith(li.cloneNode(true));
                        });
                        
                        // Re-query after cloning to get fresh elements
                        Array.from(locationListEl.querySelectorAll('li')).forEach(li => {
                            li.style.cursor = 'pointer';
                            li.addEventListener('click', function() {
                                console.log('Location clicked:', this.querySelector('strong').textContent);
                                
                                // Remove highlight from all
                                Array.from(locationListEl.querySelectorAll('li')).forEach(l => l.classList.remove('bg-light', 'fw-bold'));
                                
                                // Highlight selected
                                this.classList.add('bg-light', 'fw-bold');
                                
                                // Set location input
                                selectedLocationInput.value = this.querySelector('strong').textContent;
                                console.log('Selected location set to:', selectedLocationInput.value);
                                
                                // Enable buttons if date and time are set
                                checkEnableButtons();
                            });
                        });
                    }
                    
                    // Function to check if buttons should be enabled
                    function checkEnableButtons() {
                        const selectedLocationInput = document.getElementById('selectedLocation');
                        const scheduleBtn = document.getElementById('scheduleDriveBtn');
                        const driveDate = document.getElementById('driveDate');
                        const driveTime = document.getElementById('driveTime');
                        
                        if (!selectedLocationInput || !scheduleBtn || !driveDate || !driveTime) {
                            return;
                        }
                        
                        const hasLocation = selectedLocationInput.value.trim() !== '';
                        const hasDate = driveDate.value.trim() !== '';
                        const hasTime = driveTime.value.trim() !== '';
                        
                        const allFieldsSet = hasLocation && hasDate && hasTime;
                        
                        console.log('Button check:', {
                            hasLocation,
                            hasDate,
                            hasTime,
                            allFieldsSet,
                            locationValue: selectedLocationInput.value,
                            dateValue: driveDate.value,
                            timeValue: driveTime.value
                        });
                        
                        scheduleBtn.disabled = !allFieldsSet;
                    }
                    </script>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize modals and add button functionality
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
                    // Pass current page as source parameter for proper redirect back
                    const currentPage = encodeURIComponent(window.location.pathname + window.location.search);
                    window.location.href = '../../src/views/forms/donor-form-modal.php?source=' + currentPage;
                }, 1500);
            };
        });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Connect form inputs to button state checking
        const driveDate = document.getElementById('driveDate');
        const driveTime = document.getElementById('driveTime');
        
        if (driveDate && driveTime) {
            driveDate.addEventListener('input', checkEnableButtons);
            driveTime.addEventListener('input', checkEnableButtons);
        }
        // Real notification actions
        const scheduleBtn = document.getElementById('scheduleDriveBtn');
        
        if (scheduleBtn) {
            scheduleBtn.addEventListener('click', function() {
                sendBloodDriveNotification();
            });
        }
        
        // Function to send blood drive notifications
        async function sendBloodDriveNotification() {
            const selectedLocationInput = document.getElementById('selectedLocation');
            const driveDate = document.getElementById('driveDate');
            const driveTime = document.getElementById('driveTime');
            
            const location = selectedLocationInput.value;
            const date = driveDate.value;
            const time = driveTime.value;
            
            if (!location || !date || !time) {
                alert('Please fill in all fields: Location, Date, and Time');
                return;
            }
            
            // Get coordinates for the selected location
            const coords = getLocationCoordinates(location);
            if (!coords) {
                alert('Could not find coordinates for the selected location');
                return;
            }
            
            // Show loading state
            const scheduleBtn = document.getElementById('scheduleDriveBtn');
            
            scheduleBtn.disabled = true;
            scheduleBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            try {
                const response = await fetch('/RED-CROSS-THESIS/public/api/broadcast-blood-drive.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        location: location,
                        drive_date: date,
                        drive_time: time,
                        latitude: coords.lat,
                        longitude: coords.lng,
                        radius_km: 15, // 15km radius
                        blood_types: [], // Empty array = all blood types
                        custom_message: `🩸 Blood Drive Alert! A blood drive is scheduled in ${location} on ${date} at ${time}. Your blood type is urgently needed! Please consider donating.`
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    showNotificationSuccess(result);
                    
                    // Reset form
                    selectedLocationInput.value = '';
                    driveDate.value = '';
                    driveTime.value = '';
                    checkEnableButtons();
                    
                    // Remove highlights from location list
                    const locationListEl = document.getElementById('locationList');
                    Array.from(locationListEl.querySelectorAll('li')).forEach(l => l.classList.remove('bg-light', 'fw-bold'));
                    
                } else {
                    throw new Error(result.message || 'Failed to send notifications');
                }
                
            } catch (error) {
                console.error('Notification error:', error);
                alert('Failed to send notifications: ' + error.message);
            } finally {
                // Reset button states
                const scheduleBtn = document.getElementById('scheduleDriveBtn');
                
                scheduleBtn.disabled = false;
                scheduleBtn.innerHTML = 'Schedule Blood Drive';
                checkEnableButtons();
            }
        }
        
        // Function to get coordinates for a location
        function getLocationCoordinates(locationName) {
            // Check if location exists in our predefined coordinates
            for (const [city, coords] of Object.entries(locationCoordinates)) {
                if (locationName.toLowerCase().includes(city.toLowerCase())) {
                    return coords;
                }
            }
            
            // If not found in predefined, return Iloilo City as default
            return { lat: 10.7202, lng: 122.5621 };
        }
        
        // Function to show success notification
        function showNotificationSuccess(result) {
            const successDiv = document.createElement('div');
            successDiv.className = 'alert alert-success alert-dismissible fade show';
            successDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 400px;';
            successDiv.innerHTML = `
                <h6><i class="fas fa-check-circle"></i> Blood Drive Notifications Sent!</h6>
                <p class="mb-1"><strong>Location:</strong> ${result.results ? 'Multiple locations' : 'Selected location'}</p>
                <p class="mb-1"><strong>Donors Found:</strong> ${result.total_donors_found || 0}</p>
                <p class="mb-1"><strong>Notifications Sent:</strong> ${result.results?.sent || 0}</p>
                <p class="mb-1"><strong>Failed:</strong> ${result.results?.failed || 0}</p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(successDiv);
            
            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (successDiv.parentNode) {
                    successDiv.parentNode.removeChild(successDiv);
                }
            }, 10000);
        }
    });
    </script>
</body>
</html>