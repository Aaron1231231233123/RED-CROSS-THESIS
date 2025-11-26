<?php
// Prevent browser caching (server-side caching is handled below)
header('Vary: Accept-Encoding');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
include_once '../../assets/conn/db_conn.php';
require_once 'module/optimized_functions.php';
require_once '../../assets/php_func/buffer_blood_manager.php';
require_once '../../assets/php_func/admin_blood_bank_auto_dispose.php';
require_once __DIR__ . '/module/blood_inventory_data.php';

// Auto-dispose expired blood bank units on page load to keep counts aligned
try {
    $disposeResult = admin_blood_bank_auto_dispose();
    if ($disposeResult['success'] && $disposeResult['disposed_count'] > 0) {
        error_log("Home Dashboard: Auto-disposed {$disposeResult['disposed_count']} expired unit(s)");
    }
} catch (Exception $e) {
    error_log("Home Dashboard: Error during auto-dispose: " . $e->getMessage());
    // Continue loading page even if auto-dispose fails
}

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
$cacheKey = 'home_dashboard_v5_' . date('Y-m-d'); // Daily cache key (bumped to v5)
$cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.json';
$cacheMetaFile = sys_get_temp_dir() . '/' . $cacheKey . '_meta.json';

// Cache disabled to keep counts perfectly in sync with Blood Bank dashboard
$cacheEnabled = false;
$useCache = false;
$needsFullRefresh = false;
$needsCountRefresh = false;
$maxCacheAge = 0;

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
if ($cacheEnabled && file_exists($cacheFile) && file_exists($cacheMetaFile)) {
    $cacheMeta = json_decode(file_get_contents($cacheMetaFile), true);
    $cacheTimestamp = isset($cacheMeta['timestamp']) ? (int)$cacheMeta['timestamp'] : 0;
    $cacheAge = time() - $cacheTimestamp;
    
    $hasChanges = false;
    if ($cacheAge > $maxCacheAge) {
        $hasChanges = true;
    } else {
        $statusCheckResponse = supabaseRequest("blood_bank_units?select=status,updated_at&order=updated_at.desc&limit=10");
        $currentStatusHash = md5(json_encode($statusCheckResponse ?: []));
        $handedOverCheckResponse = supabaseRequest("blood_bank_units?select=unit_id,status&status=eq.handed_over");
        $currentHandedOverHash = md5(json_encode($handedOverCheckResponse ?: []));
        
        if (($cacheMeta['statusHash'] ?? null) !== $currentStatusHash) {
            $hasChanges = true;
        }
        if (($cacheMeta['handedOverHash'] ?? null) !== $currentHandedOverHash) {
            $hasChanges = true;
        }
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
            $bufferContext = $cachedData['bufferContext'] ?? null;
            $bufferReserveCount = $cachedData['bufferReserveCount'] ?? 0;
            $bufferTypes = $cachedData['bufferTypes'] ?? [];
            $totalDonorCount = $cachedData['totalDonorCount'] ?? 0;
            $cityDonorCounts = $cachedData['cityDonorCounts'] ?? [];
            $heatmapData = $cachedData['heatmapData'] ?? [];
            $donorLookup = $cachedData['donorLookup'] ?? [];
            $activeInventory = array_values(array_filter($bloodInventory, function($unit) {
                return empty($unit['is_buffer']);
            }));
            
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
    } else {
        // Cache stale or data changed - delete to rebuild
        if (file_exists($cacheFile)) unlink($cacheFile);
        if (file_exists($cacheMetaFile)) unlink($cacheMetaFile);
    }
}

$hospitalRequestsCount = 0;
$bloodRequestsResponse = supabaseRequest("blood_requests?status=eq.Pending&select=request_id");
if (isset($bloodRequestsResponse['data']) && is_array($bloodRequestsResponse['data'])) {
    $hospitalRequestsCount = count($bloodRequestsResponse['data']);
}

// Shared inventory snapshot (identical data source as Blood Bank dashboard)
$inventorySnapshot = loadBloodInventorySnapshot();
$bloodInventory = $inventorySnapshot['bloodInventory'];
$activeInventory = $inventorySnapshot['activeInventory'];
$bufferOnlyInventory = $inventorySnapshot['bufferOnlyInventory'];
$bufferContext = $inventorySnapshot['bufferContext'];
$bufferReserveCount = $inventorySnapshot['bufferReserveCount'];
$bufferTypes = $inventorySnapshot['bufferTypes'];
$bloodInStockCount = $inventorySnapshot['bloodInStockCount'];
$bloodByType = $inventorySnapshot['bloodByType'];
$bloodReceivedCount = $inventorySnapshot['bloodReceivedCount'];
$totalDonorCount = $inventorySnapshot['totalDonorCount'];

// ----------------------------------------------------
// PART 5: PROCESS GIS MAPPING DATA (DEFERRED FOR PERFORMANCE)
// ----------------------------------------------------
// PERFORMANCE OPTIMIZATION: GIS data will be loaded via AJAX after page load
// This significantly reduces initial page load time
$cityDonorCounts = [];
$heatmapData = [];
$totalDonorCount = $inventorySnapshot['totalDonorCount'];
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
    'bufferContext' => $bufferContext,
    'bufferReserveCount' => $bufferReserveCount,
    'bufferTypes' => $bufferTypes,
    'totalDonorCount' => $totalDonorCount,
    'cityDonorCounts' => [],  // Will be loaded via AJAX
    'heatmapData' => [],      // Will be loaded via AJAX
    'donorLookup' => [],
    'timestamp' => time()
];

if ($cacheEnabled) {
    $statusCheckResponse = supabaseRequest("blood_bank_units?select=status,updated_at&order=updated_at.desc&limit=10");
    $currentStatusHash = md5(json_encode($statusCheckResponse ?: []));
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
}

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
    <!-- Admin Screening / Defer Modal CSS -->
    <link href="../../assets/css/admin-screening-form-modal.css" rel="stylesheet">
    <link href="../../assets/css/defer-donor-modal.css" rel="stylesheet">
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

/* Buffer badge (shared visual language with Blood Bank, but kept subtle for summary cards) */
.buffer-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f7d774;
    color: #7a4a00;
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
    <?php include '../../src/views/modals/admin-donor-registration-modal.php'; ?>
    <?php
        $screeningModalPath = '../../src/views/forms/admin_donor_initial_screening_form_modal.php';
        if (file_exists($screeningModalPath)) {
            include $screeningModalPath;
        }

        $deferModalPath = '../../src/views/modals/defer-donor-modal.php';
        if (file_exists($deferModalPath)) {
            include $deferModalPath;
        }

        $interviewerModalsPath = '../../src/views/modals/interviewer-confirmation-modals.php';
        if (file_exists($interviewerModalsPath)) {
            include $interviewerModalsPath;
        }
    ?>
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

    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-danger" onclick="openQRRegistration()" title="Generate QR Code for Donor Registration">
                        <i class="fas fa-qrcode me-2"></i>Generate QR Code
                    </button>
                    <button class="btn btn-danger" onclick="showConfirmationModal()">
                        <i class="fas fa-plus me-2"></i>Register Donor
                    </button>
                </div>
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
                    <!-- Available Blood per Unit Section -->
                    <div class="mb-5">
                        <h5 class="mb-4" style="font-weight: 600;">Available Blood per Unit</h5>
                        <div class="row g-4">
                            <!-- Blood Type Cards (retain home dashboard UI; add danger icon) -->
                            <?php
                            // Calculate per-type counts using ACTIVE inventory only (exclude buffer units)
                            $activeInventoryForCounts = isset($activeInventory) ? $activeInventory : $bloodInventory;
                            $bloodTypeCounts = array_reduce($activeInventoryForCounts, function($carry, $bag) {
                                if ($bag['status'] == 'Valid' && isset($carry[$bag['blood_type']])) {
                                    $carry[$bag['blood_type']] += 1; // Each unit = 1 bag
                                }
                                return $carry;
                            }, [
                                'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
                                'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
                            ]);
                            // Threshold for low availability indicator
                            $lowThreshold = 25;
                            // Inline helper to render a small danger icon when low
                            function renderDangerIconHome($count, $threshold) {
                                if ($count <= $threshold) {
                                    return '<span class="text-danger" title="Low availability" style="position:absolute; top:8px; right:10px;"><i class="fas fa-exclamation-triangle"></i></span>';
                                }
                                return '';
                            }
                            // Helper to render small buffer badge when buffer units exist for a type
                            function renderBufferPillHome($type, $bufferTypes) {
                                $count = isset($bufferTypes[$type]) ? (int)$bufferTypes[$type] : 0;
                                if ($count > 0) {
                                    return '<span class="buffer-pill ms-2" title="Units held in buffer reserve">' . $count . ' in buffer</span>';
                                }
                                return '';
                            }
                            ?>
                            
                            <!-- Row 1: O+, A+, B+, AB+ (retain original order/UI) -->
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-pos" style="position: relative;">
                                    <?php echo renderDangerIconHome((int)$bloodTypeCounts['O+'], $lowThreshold); ?>
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type O+</h5>
                                        <p class="inventory-system-blood-availability">
                                            Availability: <?php echo (int)$bloodTypeCounts['O+']; ?>
                                            <?php echo renderBufferPillHome('O+', $bufferTypes); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-pos" style="position: relative;">
                                    <?php echo renderDangerIconHome((int)$bloodTypeCounts['A+'], $lowThreshold); ?>
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type A+</h5>
                                        <p class="inventory-system-blood-availability">
                                            Availability: <?php echo (int)$bloodTypeCounts['A+']; ?>
                                            <?php echo renderBufferPillHome('A+', $bufferTypes); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-pos" style="position: relative;">
                                    <?php echo renderDangerIconHome((int)$bloodTypeCounts['B+'], $lowThreshold); ?>
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type B+</h5>
                                        <p class="inventory-system-blood-availability">
                                            Availability: <?php echo (int)$bloodTypeCounts['B+']; ?>
                                            <?php echo renderBufferPillHome('B+', $bufferTypes); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-pos" style="position: relative;">
                                    <?php echo renderDangerIconHome((int)$bloodTypeCounts['AB+'], $lowThreshold); ?>
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type AB+</h5>
                                        <p class="inventory-system-blood-availability">
                                            Availability: <?php echo (int)$bloodTypeCounts['AB+']; ?>
                                            <?php echo renderBufferPillHome('AB+', $bufferTypes); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Row 2: O-, A-, B-, AB- -->
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-neg" style="position: relative;">
                                    <?php echo renderDangerIconHome((int)$bloodTypeCounts['O-'], $lowThreshold); ?>
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type O-</h5>
                                        <p class="inventory-system-blood-availability">
                                            Availability: <?php echo (int)$bloodTypeCounts['O-']; ?>
                                            <?php echo renderBufferPillHome('O-', $bufferTypes); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-neg" style="position: relative;">
                                    <?php echo renderDangerIconHome((int)$bloodTypeCounts['A-'], $lowThreshold); ?>
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type A-</h5>
                                        <p class="inventory-system-blood-availability">
                                            Availability: <?php echo (int)$bloodTypeCounts['A-']; ?>
                                            <?php echo renderBufferPillHome('A-', $bufferTypes); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-neg" style="position: relative;">
                                    <?php echo renderDangerIconHome((int)$bloodTypeCounts['B-'], $lowThreshold); ?>
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type B-</h5>
                                        <p class="inventory-system-blood-availability">
                                            Availability: <?php echo (int)$bloodTypeCounts['B-']; ?>
                                            <?php echo renderBufferPillHome('B-', $bufferTypes); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-neg" style="position: relative;">
                                    <?php echo renderDangerIconHome((int)$bloodTypeCounts['AB-'], $lowThreshold); ?>
                                    <div class="card-body p-4">
                                        <h5 class="inventory-system-blood-title">Blood Type AB-</h5>
                                        <p class="inventory-system-blood-availability">
                                            Availability: <?php echo (int)$bloodTypeCounts['AB-']; ?>
                                            <?php echo renderBufferPillHome('AB-', $bufferTypes); ?>
                                        </p>
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
                                <div id="mapBoundaryAlert" class="alert alert-warning d-none py-2 px-3 mb-3" role="alert" style="font-size: 0.95rem;">
                                    <i class="fas fa-triangle-exclamation me-2"></i>
                                    <span><strong id="mapBoundaryAlertCount">0</strong> donor location(s) were excluded because they fall outside the supported GIS boundary.</span>
                                </div>
                                <div id="map" class="bg-light rounded-3" style="height: 677px; width: 100%; max-width: 100%; margin: 0 auto; border: 1px solid #eee;"></div>
                            </div>
                            <div class="col-md-3 dashboard-gis-sidebar">
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
                            max-height: 280px;
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
                    
                    function isCityAllowed(city) {
                        if (!city) return false;
                        const normalized = normalizeCityName(city);
                        return normalized ? allowedIloiloCities.has(normalized) : false;
                    }

                    function inferCityFromAddress(address) {
                        if (!address || typeof address !== 'string') {
                            return null;
                        }
                        const normalized = normalizeCityToken(address);
                        for (const [token, canonical] of Object.entries(cityAliasMap)) {
                            if (normalized.includes(token)) {
                                return canonical;
                            }
                        }
                        return null;
                    }
                    
                    function getCityForItem(item) {
                        if (!item) return null;
                        if (item.city) {
                            const normalizedCity = normalizeCityName(item.city);
                            if (normalizedCity) {
                                item.city = normalizedCity;
                                return normalizedCity;
                            }
                        }
                        const inferred = inferCityFromAddress(item.address || item.original_address || '');
                        if (inferred) {
                            item.city = inferred;
                            return inferred;
                        }
                        item.city = null;
                        return null;
                    }
                    // --- GIS Boundary Configuration (client-side guardrail) ---
                    const allowedHeatmapBounds = [
                        L.latLngBounds([9.3000, 121.3000], [12.0000, 123.8000])
                    ];
                    
                    const restrictedWaterZones = [
                        // Guimaras Strait (between Panay and Guimaras) - slightly relaxed near the coast
                        { minLat: 10.52, maxLat: 10.76, minLng: 122.52, maxLng: 122.74 },
                        // Iloilo Strait (between Guimaras and Negros Occidental)
                        { minLat: 10.44, maxLat: 10.73, minLng: 122.72, maxLng: 123.03 },
                        // Panay Gulf - southern corridor (west of Iloilo/Antique)
                        { minLat: 10.24, maxLat: 10.68, minLng: 121.54, maxLng: 121.92 },
                        // Panay Gulf - northern Antique coastline
                        { minLat: 10.69, maxLat: 11.31, minLng: 121.54, maxLng: 121.92 },
                        // Visayan Sea (north of Panay)
                        { minLat: 11.02, maxLat: 11.58, minLng: 122.24, maxLng: 123.31 },
                        // Sulu Sea (south of Negros Occidental)
                        { minLat: 9.74, maxLat: 10.36, minLng: 122.44, maxLng: 123.16 }
                    ];
                    const WATER_DISTANCE_THRESHOLD_KM = 0.45;
                    const COASTAL_GRACE_KM = 0.60;
                    
                    const locationCoordinates = {
                        'Ajuy': { lat: 11.1710, lng: 123.0150 },
                        'Alimodian': { lat: 10.8167, lng: 122.4333 },
                        'Anilao': { lat: 11.0006, lng: 122.7214 },
                        'Banate': { lat: 11.0033, lng: 122.8071 },
                        'Barotac Nuevo': { lat: 10.9000, lng: 122.7000 },
                        'Barotac Viejo': { lat: 11.0500, lng: 122.8500 },
                        'Batad': { lat: 11.2873, lng: 123.0455 },
                        'Bingawan': { lat: 11.2333, lng: 122.5667 },
                        'Cabatuan': { lat: 10.8833, lng: 122.4833 },
                        'Calinog': { lat: 11.1167, lng: 122.5000 },
                        'Carles': { lat: 11.5667, lng: 123.1333 },
                        'Concepcion': { lat: 11.2167, lng: 123.1167 },
                        'Dingle': { lat: 11.0000, lng: 122.6667 },
                        'Dueñas': { lat: 11.0667, lng: 122.6167 },
                        'Dumangas': { lat: 10.8333, lng: 122.7167 },
                        'Estancia': { lat: 11.4500, lng: 123.1500 },
                        'Guimbal': { lat: 10.6667, lng: 122.3167 },
                        'Igbaras': { lat: 10.7167, lng: 122.2667 },
                        'Janiuay': { lat: 10.9500, lng: 122.5000 },
                        'Lambunao': { lat: 11.0500, lng: 122.4667 },
                        'Leganes': { lat: 10.7833, lng: 122.5833 },
                        'Leon': { lat: 10.7833, lng: 122.3833 },
                        'Maasin': { lat: 10.8833, lng: 122.4333 },
                        'Mina': { lat: 10.9333, lng: 122.5833 },
                        'Miagao': { lat: 10.6333, lng: 122.2333 },
                        'New Lucena': { lat: 10.8833, lng: 122.6000 },
                        'Oton': { lat: 10.6933, lng: 122.4733 },
                        'Passi': { lat: 11.1167, lng: 122.6333 },
                        'Pavia': { lat: 10.7750, lng: 122.5444 },
                        'Pototan': { lat: 10.9500, lng: 122.6333 },
                        'San Dionisio': { lat: 11.2667, lng: 123.0833 },
                        'San Enrique': { lat: 11.1000, lng: 122.6667 },
                        'San Joaquin': { lat: 10.5920, lng: 122.0950 },
                        'San Miguel': { lat: 10.7833, lng: 122.4667 },
                        'Santa Barbara': { lat: 10.8167, lng: 122.5333 },
                        'Sara': { lat: 11.2500, lng: 123.0167 },
                        'Tigbauan': { lat: 10.6833, lng: 122.3667 },
                        'Tubungan': { lat: 10.7833, lng: 122.3333 },
                        'Zarraga': { lat: 10.8167, lng: 122.6000 }
                    };

                    function normalizeCityToken(text) {
                        if (!text) return '';
                        return text
                            .toString()
                            .normalize('NFD')
                            .replace(/[\u0300-\u036f]/g, '')
                            .toLowerCase()
                            .replace(/[^a-z0-9\s]/g, ' ')
                            .replace(/\s+/g, ' ')
                            .trim();
                    }

                    const cityAliasMap = {};
                    Object.keys(locationCoordinates).forEach(city => {
                        cityAliasMap[normalizeCityToken(city)] = city;
                    });
                    [
                        ['Passi City', 'Passi'],
                        ['City of Passi', 'Passi'],
                        ['Municipality of Passi', 'Passi'],
                        ['Municipality of San Miguel', 'San Miguel'],
                        ['San Miguel Iloilo', 'San Miguel'],
                        ['Municipality of Oton', 'Oton'],
                        ['San Enrique Iloilo', 'San Enrique']
                    ].forEach(([alias, canonical]) => {
                        cityAliasMap[normalizeCityToken(alias)] = canonical;
                    });

                    function normalizeCityName(rawName) {
                        if (!rawName) return null;
                        const token = normalizeCityToken(rawName);
                        return cityAliasMap[token] || null;
                    }

                    const allowedIloiloCities = new Set(Object.keys(locationCoordinates));
                    const westCoastCities = new Set(['San Joaquin', 'Miagao', 'Guimbal', 'Tigbauan']);
                    const northCoastCities = new Set(['Leganes', 'Zarraga', 'Dumangas', 'Barotac Nuevo', 'Barotac Viejo']);
                    
                    // Approximate outline of Iloilo City mainland (for stricter checks)
                    const strictCityPolygons = [
                        [
                            [10.7765, 122.4712],
                            [10.7588, 122.4546],
                            [10.7256, 122.4578],
                            [10.6984, 122.4744],
                            [10.6763, 122.4865],
                            [10.6569, 122.5063],
                            [10.6467, 122.5254],
                            [10.6429, 122.5452],
                            [10.6451, 122.5667],
                            [10.6549, 122.5845],
                            [10.6691, 122.5978],
                            [10.6885, 122.6091],
                            [10.7077, 122.6181],
                            [10.7324, 122.6129],
                            [10.7528, 122.6028],
                            [10.7705, 122.5920],
                            [10.7886, 122.5774],
                            [10.8021, 122.5585],
                            [10.8078, 122.5383],
                            [10.8052, 122.5172],
                            [10.7943, 122.4975],
                            [10.7819, 122.4818],
                            [10.7765, 122.4712]
                        ]
                    ];
                    
                    const allowedProvinceKeywords = [
                        'iloilo',
                        'iloilo city',
                        'iloilo province',
                        'province of iloilo',
                        'western visayas',
                        'region vi'
                    ];
                    
                    function hashString(str) {
                        let hash = 0;
                        if (!str) return hash;
                        for (let i = 0; i < str.length; i++) {
                            hash = ((hash << 5) - hash) + str.charCodeAt(i);
                            hash |= 0;
                        }
                        return Math.abs(hash);
                    }
                    
                    function jitterCoordinate(lat, lng, key, maxMeters = 300, minMeters = 40) {
                        const seed = hashString(key);
                        const angle = ((seed % 360) * Math.PI) / 180;
                        const distanceMeters = minMeters + (seed % (maxMeters - minMeters));
                        const offsetLat = (distanceMeters * Math.cos(angle)) / 111320;
                        const offsetLng = (distanceMeters * Math.sin(angle)) / (111320 * Math.cos(lat * Math.PI / 180));
                        return {
                            lat: lat + offsetLat,
                            lng: lng + offsetLng
                        };
                    }
                    
                    function maybeApplyJitter(lat, lng, result, existingCheck = true) {
                        const source = result.coords.source || 'unknown';
                        const accuracy = result.coords.accuracy || 'medium';
                        if (source === 'database' || accuracy === 'high') {
                            return { lat, lng };
                        }
                        const key = String(result.location?.donor_id ?? result.location?.address ?? result.coords.display_name ?? `${lat},${lng}`);
                        const jittered = jitterCoordinate(lat, lng, key);
                        const cityHint = result.location ? getCityForItem(result.location) : null;
                        if (!existingCheck) {
                            const safeDirect = ensureLandCoordinate(jittered.lat, jittered.lng, cityHint);
                            if (safeDirect) {
                                return safeDirect;
                            }
                            const safeOriginal = ensureLandCoordinate(lat, lng, cityHint);
                            return safeOriginal || { lat, lng };
                        }
                        if (isWithinAllowedGISArea(jittered.lat, jittered.lng) && !isPointInWater(jittered.lat, jittered.lng)) {
                            const safeJittered = ensureLandCoordinate(jittered.lat, jittered.lng, cityHint);
                            if (safeJittered) {
                                return safeJittered;
                            }
                        }
                        const safeFallback = ensureLandCoordinate(lat, lng, cityHint);
                        return safeFallback || { lat, lng };
                    }

                    function spreadDuplicatePoints(points) {
                        const groups = new Map();
                        points.forEach((point, index) => {
                            const baseLat = typeof point.baseLat === 'number' ? point.baseLat : point.lat;
                            const baseLng = typeof point.baseLng === 'number' ? point.baseLng : point.lng;
                            point.baseLat = baseLat;
                            point.baseLng = baseLng;
                            const baseKey = `${Number(baseLat).toFixed(6)},${Number(baseLng).toFixed(6)}`;
                            if (!groups.has(baseKey)) {
                                groups.set(baseKey, []);
                            }
                            groups.get(baseKey).push({ point, index });
                        });

                        groups.forEach((entries, baseKey) => {
                            if (entries.length <= 1) {
                                return;
                            }
                            console.log(`🌐 Detected ${entries.length} duplicate coordinate(s) at ${baseKey} – applying micro-jitter to separate markers.`);
                            entries.forEach(({ point }, offset) => {
                                const seedKey = `${point.location?.donor_id || baseKey}:${offset}`;
                                const jittered = jitterCoordinate(point.baseLat, point.baseLng, seedKey, 120, 30);
                                const cityHint = getCityForItem(point.location);
                                const safe = ensureLandCoordinate(jittered.lat, jittered.lng, cityHint);
                                point.lat = safe ? safe.lat : jittered.lat;
                                point.lng = safe ? safe.lng : jittered.lng;
                            });
                        });
                    }
                    
                    function cleanAddressString(address) {
                        if (!address || typeof address !== 'string') {
                            return '';
                        }
                        const segments = address
                            .split(',')
                            .map(seg => seg.trim())
                            .filter(Boolean);
                        const seen = new Set();
                        const cleaned = [];
                        segments.forEach(seg => {
                            const normalized = seg.toLowerCase().replace(/\s+/g, ' ');
                            if (seen.has(normalized)) {
                                return;
                            }
                            seen.add(normalized);
                            cleaned.push(seg);
                        });
                        return cleaned.join(', ');
                    }
                    
                    function isPointInWater(lat, lng) {
                        if (lat === null || lng === null || Number.isNaN(lat) || Number.isNaN(lng)) {
                            return false;
                        }
                        const centroidEntries = (typeof locationCoordinates !== 'undefined' && locationCoordinates)
                            ? Object.values(locationCoordinates)
                            : [];
                        let nearestDist = Infinity;
                        centroidEntries.forEach(c => {
                            const d = haversineKm(lat, lng, c.lat, c.lng);
                            if (d < nearestDist) {
                                nearestDist = d;
                            }
                        });
                        const inRestrictedZone = restrictedWaterZones.some(zone =>
                            lat >= zone.minLat && lat <= zone.maxLat &&
                            lng >= zone.minLng && lng <= zone.maxLng
                        );
                        if (inRestrictedZone) {
                            if (nearestDist <= COASTAL_GRACE_KM) {
                                return false;
                            }
                            return true;
                        }
                        // Treat as water when beyond the safety threshold from any municipality centroid
                        return nearestDist > WATER_DISTANCE_THRESHOLD_KM;
                    }
                    
                    function isPointInPolygon(lat, lng, polygon) {
                        let inside = false;
                        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
                            const xi = polygon[i][1];
                            const yi = polygon[i][0];
                            const xj = polygon[j][1];
                            const yj = polygon[j][0];
                            const intersect = ((yi > lat) !== (yj > lat)) &&
                                (lng < ((xj - xi) * (lat - yi)) / ((yj - yi) || Number.EPSILON) + xi);
                            if (intersect) inside = !inside;
                        }
                        return inside;
                    }
                    
                    function isAddressAllowed(address) {
                        if (!address || typeof address !== 'string') {
                            return true; // allow if unknown, other checks will guard
                        }
                        const normalized = cleanAddressString(address).toLowerCase();
                        return allowedProvinceKeywords.some(keyword => normalized.includes(keyword));
                    }
                    
                    function isWithinAllowedGISArea(lat, lng) {
                        if (lat === null || lng === null || Number.isNaN(lat) || Number.isNaN(lng)) {
                            return false;
                        }
                        if (isPointInWater(lat, lng)) {
                            return false;
                        }
                        const point = L.latLng(lat, lng);
                        const insideBounds = allowedHeatmapBounds.some(bounds => bounds.contains(point));
                        if (!insideBounds) {
                            return false;
                        }
                        if (strictCityPolygons.length === 0) {
                            return true;
                        }
                        // Allow either inside strict polygon OR within 35km of any known municipality centroid
                        if (strictCityPolygons.some(polygon => isPointInPolygon(lat, lng, polygon))) {
                            return true;
                        }
                        const centroidEntries = (typeof locationCoordinates !== 'undefined' && locationCoordinates)
                            ? Object.values(locationCoordinates)
                            : [];
                        for (const coords of centroidEntries) {
                            if (haversineKm(lat, lng, coords.lat, coords.lng) <= 35) {
                                return true;
                            }
                        }
                        return false;
                    }
                    
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

                    // Haversine distance calculation (km)
                    function haversineKm(lat1, lon1, lat2, lon2) {
                        const R = 6371; // Earth radius in km
                        const dLat = (lat2 - lat1) * Math.PI / 180;
                        const dLon = (lon2 - lon1) * Math.PI / 180;
                        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLon/2)**2;
                        return 2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                    }

                    function findNearestMunicipality(lat, lng) {
                        let nearest = null;
                        let minDist = Infinity;
                        for (const [city, coords] of Object.entries(locationCoordinates)) {
                            const dist = haversineKm(lat, lng, coords.lat, coords.lng);
                            if (dist < minDist) {
                                minDist = dist;
                                nearest = { city, lat: coords.lat, lng: coords.lng, distance: dist };
                            }
                        }
                        return nearest;
                    }

                    function nudgeCoordinateTowardsLand(lat, lng, cityHint = null) {
                        const targets = [];
                        if (cityHint && locationCoordinates[cityHint]) {
                            targets.push({ city: cityHint, lat: locationCoordinates[cityHint].lat, lng: locationCoordinates[cityHint].lng });
                        }
                        const nearest = findNearestMunicipality(lat, lng);
                        if (nearest && (!targets.length || targets[0].city !== nearest.city)) {
                            targets.push(nearest);
                        }

                        const factors = [0.35, 0.55, 0.7, 0.85, 1.0];
                        for (const target of targets) {
                            for (const factor of factors) {
                                const candidateLat = lat + (target.lat - lat) * factor;
                                const candidateLng = lng + (target.lng - lng) * factor;
                                if (isWithinAllowedGISArea(candidateLat, candidateLng) && !isPointInWater(candidateLat, candidateLng)) {
                                    const biased = applyCoastalInlandBias(candidateLat, candidateLng, cityHint || target.city);
                                    if (!isPointInWater(biased.lat, biased.lng)) {
                                        return { lat: biased.lat, lng: biased.lng, city: target.city };
                                    }
                                    return { lat: candidateLat, lng: candidateLng, city: target.city };
                                }
                            }
                        }

                        const offsets = [
                            { dLat: 0, dLng: 0.01 },
                            { dLat: 0.006, dLng: 0.012 },
                            { dLat: -0.006, dLng: 0.012 },
                            { dLat: 0, dLng: 0.018 }
                        ];
                        for (const offset of offsets) {
                            const candidateLat = lat + offset.dLat;
                            const candidateLng = lng + offset.dLng;
                            if (isWithinAllowedGISArea(candidateLat, candidateLng) && !isPointInWater(candidateLat, candidateLng)) {
                                const biased = applyCoastalInlandBias(candidateLat, candidateLng, cityHint || nearest?.city || null);
                                if (!isPointInWater(biased.lat, biased.lng)) {
                                    return { lat: biased.lat, lng: biased.lng, city: cityHint || nearest?.city || null };
                                }
                                return { lat: candidateLat, lng: candidateLng, city: cityHint || nearest?.city || null };
                            }
                        }
                        return null;
                    }

                    function applyCoastalInlandBias(lat, lng, cityHint = null) {
                        let targetCity = null;
                        if (cityHint && locationCoordinates[cityHint]) {
                            targetCity = cityHint;
                        }
                        if (!targetCity) {
                            const nearest = findNearestMunicipality(lat, lng);
                            if (nearest && nearest.city && locationCoordinates[nearest.city]) {
                                targetCity = nearest.city;
                            }
                        }
                        if (!targetCity || !locationCoordinates[targetCity]) {
                            return { lat, lng };
                        }

                        const cityCoords = locationCoordinates[targetCity];
                        let adjustedLat = lat;
                        let adjustedLng = lng;

                        // Western Panay / Antique coastline - push eastward inland
                        const isWestCoast = westCoastCities.has(targetCity);
                        const westPrimaryBoost = targetCity === 'San Joaquin' ? 0.032 : 0.026;
                        const westSecondaryBoost = targetCity === 'San Joaquin' ? 0.028 : 0.018;
                        const westMinLngBoost = targetCity === 'San Joaquin' ? 0.035 : 0.020;
                        const westLatBoost = targetCity === 'San Joaquin' ? 0.012 : 0.006;
                        const westLatFloorBoost = targetCity === 'San Joaquin' ? 0.012 : 0.004;

                        if (cityCoords.lng <= 122.05 && adjustedLng <= cityCoords.lng) {
                            adjustedLng = Math.max(adjustedLng, cityCoords.lng + westPrimaryBoost);
                            adjustedLat = adjustedLat + 0.004;
                        }

                        if (isWestCoast && adjustedLng <= cityCoords.lng - 0.004) {
                            adjustedLng = Math.max(adjustedLng, cityCoords.lng + westPrimaryBoost);
                            adjustedLat = adjustedLat + westLatBoost;
                        }

                        // Eastern Iloilo / Guimaras coastline - push westward inland
                        if (cityCoords.lng >= 122.75 && adjustedLng >= cityCoords.lng) {
                            adjustedLng = cityCoords.lng - 0.012;
                        }

                        // Northern Aklan coastline - push southward inland
                        if (cityCoords.lat >= 11.25 && adjustedLat >= cityCoords.lat) {
                            adjustedLat = cityCoords.lat - 0.012;
                        }

                        // Southern Guimaras / Iloilo coastline - push northward inland
                        if (cityCoords.lat <= 10.55 && adjustedLat <= cityCoords.lat) {
                            adjustedLat = cityCoords.lat + 0.012;
                        }

                        if (northCoastCities.has(targetCity) && adjustedLat >= cityCoords.lat) {
                            adjustedLat = cityCoords.lat - 0.008;
                        }

                        if (isWestCoast && adjustedLng <= cityCoords.lng) {
                            adjustedLng = Math.max(adjustedLng, cityCoords.lng + westSecondaryBoost);
                            adjustedLat = Math.max(adjustedLat, cityCoords.lat + 0.002);
                        }

                        if (isWestCoast) {
                            const minSafeLng = cityCoords.lng + westMinLngBoost;
                            if (adjustedLng < minSafeLng) {
                                adjustedLng = minSafeLng;
                            }
                            const minSafeLat = cityCoords.lat + westLatFloorBoost;
                            if (adjustedLat < minSafeLat) {
                                adjustedLat = minSafeLat;
                            }
                        }

                        return { lat: adjustedLat, lng: adjustedLng };
                    }

                    function ensureLandCoordinate(lat, lng, cityHint = null) {
                        let candidateLat = lat;
                        let candidateLng = lng;

                        if (isPointInWater(candidateLat, candidateLng)) {
                            const adjusted = nudgeCoordinateTowardsLand(candidateLat, candidateLng, cityHint);
                            if (!adjusted) {
                                return null;
                            }
                            candidateLat = adjusted.lat;
                            candidateLng = adjusted.lng;
                        }

                        const biased = applyCoastalInlandBias(candidateLat, candidateLng, cityHint);
                        if (!isPointInWater(biased.lat, biased.lng)) {
                            candidateLat = biased.lat;
                            candidateLng = biased.lng;
                        }

                        if (isPointInWater(candidateLat, candidateLng)) {
                            const retry = nudgeCoordinateTowardsLand(candidateLat, candidateLng, cityHint);
                            if (retry && !isPointInWater(retry.lat, retry.lng)) {
                                candidateLat = retry.lat;
                                candidateLng = retry.lng;
                            } else if (retry) {
                                const retryBiased = applyCoastalInlandBias(retry.lat, retry.lng, cityHint);
                                if (!isPointInWater(retryBiased.lat, retryBiased.lng)) {
                                    candidateLat = retryBiased.lat;
                                    candidateLng = retryBiased.lng;
                                } else {
                                    return null;
                                }
                            } else {
                                return null;
                            }
                        }

                        return { lat: candidateLat, lng: candidateLng };
                    }

                    // Comprehensive coordinate validation - ensure coordinates are on land
                    function sanitizeCoordinates(coords, address) {
                        if (!coords || typeof coords.lat !== 'number' || typeof coords.lng !== 'number') return null;
                        
                        const normalizedAddress = (address || '').toLowerCase();
                        if (!coords.accuracy) {
                            if (coords.source === 'database') {
                                coords.accuracy = 'high';
                            } else if (coords.source === 'geocoded') {
                                coords.accuracy = 'medium';
                            } else if (coords.source === 'predefined') {
                                coords.accuracy = 'medium';
                            } else if (coords.source === 'snapped' || coords.source === 'fallback') {
                                coords.accuracy = 'low';
                            } else {
                                coords.accuracy = 'medium';
                            }
                        }
                        
                        const nearest = findNearestMunicipality(coords.lat, coords.lng);
                        const nearestDist = nearest ? nearest.distance : Infinity;
                        const cityHint = nearest?.city || null;
                        const isOffshore = isPointInWater(coords.lat, coords.lng);
                        
                        if (!isOffshore && nearestDist <= 20) {
                            const safeOriginal = ensureLandCoordinate(coords.lat, coords.lng, cityHint);
                            if (safeOriginal) {
                                coords.lat = safeOriginal.lat;
                                coords.lng = safeOriginal.lng;
                                return coords;
                            }
                        }
                        
                        const attemptSnap = () => {
                            const candidates = [];
                            const seenCities = new Set();
                            
                            if (normalizedAddress) {
                                for (const [city, c] of Object.entries(locationCoordinates)) {
                                    if (normalizedAddress.includes(city.toLowerCase())) {
                                        if (!seenCities.has(city)) {
                                            candidates.push({ city, lat: c.lat, lng: c.lng });
                                            seenCities.add(city);
                                        }
                                    }
                                }
                            }
                            
                            if (nearest && !seenCities.has(nearest.city)) {
                                candidates.push({ city: nearest.city, lat: nearest.lat, lng: nearest.lng });
                                seenCities.add(nearest.city);
                            }
                            
                            for (const candidate of candidates) {
                                const safe = ensureLandCoordinate(candidate.lat, candidate.lng, candidate.city);
                                if (safe) {
                                    return {
                                        lat: safe.lat,
                                        lng: safe.lng,
                                        display_name: `${candidate.city}, Philippines`,
                                        source: 'snapped',
                                        accuracy: 'low',
                                        snappedCity: candidate.city
                                    };
                                }
                            }
                            return null;
                        };
                        
                        const snapped = attemptSnap();
                        if (snapped) {
                            return snapped;
                        }
                        
                        const nudged = nudgeCoordinateTowardsLand(coords.lat, coords.lng, cityHint);
                        if (nudged) {
                            coords.lat = nudged.lat;
                            coords.lng = nudged.lng;
                            coords.source = coords.source || 'nudged';
                            coords.accuracy = 'low';
                            return coords;
                        }
                        
                        // No reliable land coordinate -> discard
                        return null;
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
                    const locationListEl = document.getElementById('locationList');
                    const mapBoundaryAlert = document.getElementById('mapBoundaryAlert');
                    const mapBoundaryAlertCount = document.getElementById('mapBoundaryAlertCount');
                    
                    // Coordinate usage counters
                    let databaseCount = 0;
                    let predefinedCount = 0;
                    let geocodedCount = 0;
                    let flaggedOutsidePoints = [];
                    let currentCityFilter = 'all';

                    // Separate data for Top Donors and Heatmap
                    let cityDonorCounts = {};
                    let heatmapData = [];
                    
                    // Function to load GIS data with blood type filter
                    function loadGISData(bloodType = 'all') {
                        console.log('🚀 Loading GIS data in background...', bloodType !== 'all' ? `(blood filter: ${bloodType})` : '');
                        flaggedOutsidePoints = [];
                        const url = '/RED-CROSS-THESIS/public/api/load-gis-data-dashboard.php?blood_type=' + encodeURIComponent(bloodType);
                        fetch(url)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const rawCounts = data.cityDonorCounts || {};
                                    const filteredCounts = {};
                                    Object.entries(rawCounts).forEach(([city, count]) => {
                                        const normalizedCity = normalizeCityName(city);
                                        if (normalizedCity && allowedIloiloCities.has(normalizedCity)) {
                                            filteredCounts[normalizedCity] = (filteredCounts[normalizedCity] || 0) + count;
                                        }
                                    });
                                    cityDonorCounts = filteredCounts;

                                    const filteredHeatmap = [];
                                    (data.heatmapData || []).forEach(item => {
                                        if (!item) {
                                            return;
                                        }
                                        const providedCity = normalizeCityName(item.city);
                                        if (providedCity) {
                                            item.city = providedCity;
                                        }
                                        const cityName = item.city ? item.city : getCityForItem(item);
                                        if (!cityName || !allowedIloiloCities.has(cityName)) {
                                            return;
                                        }
                                        filteredHeatmap.push({ ...item, city: cityName });
                                    });
                                    heatmapData = filteredHeatmap;

                                    console.log('✅ GIS data loaded (Iloilo district):', heatmapData.length, 'donors in allowed area');
                                    if (currentCityFilter !== 'all') {
                                        const availableCities = Object.keys(cityDonorCounts).map(c => c.toLowerCase());
                                        if (!availableCities.includes(currentCityFilter.toLowerCase())) {
                                            currentCityFilter = 'all';
                                        }
                                    }
                                    
                                    // Update top donor locations
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
                    }
                    
                    // Load initial GIS data
                    loadGISData('all');
                    
                    // Debug logging
                    console.log('Initial City Donor Counts:', cityDonorCounts);
                    console.log('Initial Heatmap Data:', heatmapData);
                    console.log('Initial Total Donor Count:', <?php echo $totalDonorCount; ?>);

                     // OPTIMIZED: Function to get coordinates with database priority
                     async function getCoordinates(location) {
                         const sourceAddress = cleanAddressString(location.address || location.original_address || '');
                         
                         // First priority: Use coordinates from database (PostGIS)
                         if (location.latitude && location.longitude && location.has_coordinates) {
                             const raw = {
                                 lat: parseFloat(location.latitude),
                                 lng: parseFloat(location.longitude),
                                 display_name: sourceAddress || location.address,
                                 source: 'database',
                                 accuracy: 'high'
                             };
                             return sanitizeCoordinates(raw, sourceAddress);
                         }
                         
                         // Second priority: Try to extract city name from address for predefined coordinates
                         const address = sourceAddress.toLowerCase();
                         
                         // Check if we have pre-defined coordinates for this location
                         for (const [city, coords] of Object.entries(locationCoordinates)) {
                             if (address.includes(city.toLowerCase())) {
                                 return sanitizeCoordinates({
                                     lat: coords.lat,
                                     lng: coords.lng,
                                     display_name: `${city}, Philippines`,
                                     source: 'predefined',
                                     accuracy: 'medium'
                                 }, sourceAddress);
                             }
                         }

                         // Last resort: Use geocoding as fallback (only for addresses without coordinates)
                         console.log(`⚠️ No coordinates found for: ${sourceAddress || location.address}, using geocoding fallback`);
                         const geocoded = await geocodeAddress({ ...location, address: sourceAddress || location.address });
                         return sanitizeCoordinates(geocoded, sourceAddress);
                     }

                     // OPTIMIZED: Function to geocode address using server-side endpoint (no CORS issues)
                     async function geocodeAddress(location) {
                         const primaryAddress = cleanAddressString(location.address || location.original_address || '');
                         const addresses = [];
                         
                         if (primaryAddress) {
                             addresses.push(primaryAddress);
                             
                             // Try without specific landmarks (e.g., hospital names)
                             const withoutHospital = primaryAddress.replace(/,([^,]*Hospital[^,]*),/i, ',');
                             if (withoutHospital && withoutHospital !== primaryAddress) {
                                 addresses.push(cleanAddressString(withoutHospital));
                             }
                             
                             // Try just the municipality and province
                             const tail = primaryAddress
                                 .split(',')
                                 .map(seg => seg.trim())
                                 .filter(Boolean)
                                 .slice(-3)
                                 .join(', ');
                             if (tail && tail !== primaryAddress) {
                                 addresses.push(cleanAddressString(tail));
                             }
                         }
                         
                         // Always ensure we have at least an empty string to avoid errors
                         if (addresses.length === 0) {
                             addresses.push('');
                         }

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
                        
                        // Reset counters for this render
                        databaseCount = 0;
                        predefinedCount = 0;
                        geocodedCount = 0;
                        const localFlagged = [];
                         
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
                        const activeData = currentCityFilter === 'all'
                            ? heatmapData
                            : heatmapData.filter(item => {
                                const cityName = getCityForItem(item);
                                return isCityAllowed(cityName) && cityName.toLowerCase() === currentCityFilter.toLowerCase();
                            });
                        const totalLocations = activeData.length;
                         
                         // Show loading indicator with geocoding status
                         const loadingDiv = document.createElement('div');
                         loadingDiv.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading map data...<br><small>Auto-geocoding missing coordinates...</small></div>';
                         document.getElementById('map').appendChild(loadingDiv);

                        // SPATIAL SORTING: Sort locations by Hilbert curve for better clustering
                        const sortedLocations = [...activeData];
                        console.log(`📍 Processing ${sortedLocations.length} locations for heatmap...`, currentCityFilter !== 'all' ? `(city filter: ${currentCityFilter})` : '');
                        let cachedCount = 0;
                        let skippedCount = 0;

                         for (let i = 0; i < totalLocations; i += batchSize) {
                             const batch = sortedLocations.slice(i, i + batchSize);
                             
                             // Process batch with automatic geocoding
                             const batchPromises = batch.map(async (location) => {
                                 const rawAddress = (location.original_address || location.address || '').trim();
                                 const cleanedAddress = cleanAddressString(rawAddress);
                                 const originalAddress = cleanedAddress || rawAddress;
                                 const cacheKey = originalAddress.toLowerCase();
                                 const addressAllowed = isAddressAllowed(originalAddress);
                                 const locationWithAddress = { ...location, address: originalAddress };
                                 
                                 const rejectLocation = (reason, coords = null, source = 'validation') => {
                                     skippedCount++;
                                     localFlagged.push({
                                         donorId: location.donor_id,
                                         address: originalAddress,
                                         reason,
                                         coords,
                                         source
                                     });
                                     console.warn(`⛔ GIS boundary filter skipped donor ${location.donor_id || ''}: ${reason}`, {
                                         address: originalAddress,
                                         coords
                                     });
                                     return null;
                                 };
                                 
                                 if (!addressAllowed) {
                                     console.warn(`🌐 Address outside primary region, attempting fallback geocoding: ${originalAddress}`);
                                 }
                                 
                                 // Check cache first
                                 if (coordinateCache.has(cacheKey)) {
                                     const cachedCoords = coordinateCache.get(cacheKey);
                                     if (!cachedCoords || !isWithinAllowedGISArea(cachedCoords.lat, cachedCoords.lng)) {
                                         return rejectLocation('outside-allowed-area', cachedCoords, 'cache');
                                     }
                                     cachedCount++;
                                     return {
                                         coords: cachedCoords,
                                         location: location
                                     };
                                 }
                                 
                                 let coords = await getCoordinates(locationWithAddress);
                                 if (!coords && (locationWithAddress.fallback_latitude || locationWithAddress.fallback_longitude || locationWithAddress.fallbackLatitude || locationWithAddress.fallbackLongitude)) {
                                     const fallbackLat = locationWithAddress.fallback_latitude ?? locationWithAddress.fallbackLatitude;
                                     const fallbackLng = locationWithAddress.fallback_longitude ?? locationWithAddress.fallbackLongitude;
                                     if (typeof fallbackLat === 'number' && typeof fallbackLng === 'number') {
                                         coords = {
                                             lat: fallbackLat,
                                             lng: fallbackLng,
                                             display_name: (locationWithAddress.city ? `${locationWithAddress.city}, Philippines` : (originalAddress || 'Fallback Location')),
                                             source: 'fallback',
                                             accuracy: 'low'
                                         };
                                     }
                                 }
                                 if (coords) {
                                     // Cache the result for subsequent batches
                                     coordinateCache.set(cacheKey, coords);
                                     
                                     if (!isWithinAllowedGISArea(coords.lat, coords.lng)) {
                                         return rejectLocation('outside-allowed-area', coords, coords.source || 'unknown');
                                     }
                                     
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
                                 } else {
                                     return rejectLocation('no-valid-coordinate', null, 'geocode');
                                 }
                             });

                             const batchResults = await Promise.all(batchPromises);
                             
                             // Add results to points array and count coordinate sources
                            batchResults.forEach(result => {
                                 if (result) {
                                    const cityHint = getCityForItem(result.location);
                                    const jitteredCoords = maybeApplyJitter(result.coords.lat, result.coords.lng, { coords: result.coords, location: result.location });
                                    const safeCoords = ensureLandCoordinate(jitteredCoords.lat, jitteredCoords.lng, cityHint);
                                     points.push({
                                        lat: safeCoords.lat,
                                        lng: safeCoords.lng,
                                        baseLat: result.coords.lat,
                                        baseLng: result.coords.lng,
                                         intensity: 0.8,
                                         location: result.location,
                                         coordsSource: result.coords.source
                                     });
                                     
                                     // Count coordinate sources
                                     if (result.coords.source === 'database') {
                                         databaseCount++;
                                     } else if (result.coords.source === 'predefined') {
                                         predefinedCount++;
                                     } else {
                                         geocodedCount++;
                                     }
                                     
                                     // Add marker with popup
                                     const marker = L.marker([jitteredCoords.lat, jitteredCoords.lng])
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

                        // Apply duplicate spreading to avoid stacked hotspots
                        spreadDuplicatePoints(points);

                        // SPATIAL SORTING: Sort points by Hilbert curve before creating heatmap
                        const sortedPoints = spatialSort(points.map(p => ({ ...p })));
                        const waterFiltered = [];
                        const filteredPoints = sortedPoints.filter(p => {
                            if (isPointInWater(p.lat, p.lng)) {
                                waterFiltered.push(p);
                                localFlagged.push({
                                    donorId: p.location?.donor_id ?? null,
                                    address: p.location?.original_address || p.location?.address || 'Unknown address',
                                    reason: 'water-filter',
                                    coords: { lat: p.lat, lng: p.lng },
                                    source: p.coordsSource || 'post-filter'
                                });
                                return false;
                            }
                            return true;
                        });
                        const finalPoints = filteredPoints.map(p => [p.lat, p.lng, p.intensity]);
                        skippedCount += waterFiltered.length;

                        flaggedOutsidePoints = localFlagged;
                        if (mapBoundaryAlert && mapBoundaryAlertCount) {
                            if (flaggedOutsidePoints.length > 0) {
                                mapBoundaryAlertCount.textContent = flaggedOutsidePoints.length;
                                mapBoundaryAlert.classList.remove('d-none');
                            } else {
                                mapBoundaryAlert.classList.add('d-none');
                            }
                        }

                        console.log(`📊 Heatmap stats: ${points.length} points, ${databaseCount} from DB, ${predefinedCount} predefined, ${geocodedCount} geocoded, ${skippedCount} skipped, ${flaggedOutsidePoints.length} filtered outside bounds`);
                         
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
                             console.log(`✅ Heatmap created with ${finalPoints.length} points`);
                        } else {
                            console.log(`⚠️ No valid points for heatmap - all coordinates may have been filtered out`);
                            if (mapBoundaryAlert && !mapBoundaryAlert.classList.contains('d-none')) {
                                mapBoundaryAlert.classList.remove('d-none');
                            }
                        }
                         
                         // Show completion message
                         if (geocodedCount > 0) {
                             console.log(`✅ Auto-geocoded ${geocodedCount} new addresses and cached them for future use!`);
                         }
                     }

                    function updateTopDonorLocations() {
                        // Update Top Donors list - this is independent of filters
                        locationListEl.innerHTML = '';
                        const allowedCities = Object.entries(cityDonorCounts)
                            .filter(([city, count]) => isCityAllowed(city) && count > 0);
                        
                        if (allowedCities.length === 0) {
                            locationListEl.innerHTML = '<li class="p-2">No locations found</li>';
                        } else {
                            const allLi = document.createElement('li');
                            allLi.className = 'p-2 mb-2 border-bottom';
                            allLi.dataset.city = 'all';
                            allLi.innerHTML = `
                                <div class="d-flex justify-content-start align-items-center">
                                    <strong>All Locations</strong>
                                </div>`;
                            locationListEl.appendChild(allLi);
                            
                            allowedCities
                                .sort((a, b) => b[1] - a[1])
                                .forEach(([city, count]) => {
                                    const li = document.createElement('li');
                                    li.className = 'p-2 mb-2 border-bottom';
                                    li.dataset.city = city;
                                    li.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>${city}</strong>
                                            <span class="badge bg-danger">${count}</span>
                                        </div>`;
                                    locationListEl.appendChild(li);
                                });
                            
                            // Highlight current filter if present
                            const toHighlight = Array.from(locationListEl.querySelectorAll('li')).find(li => {
                                const city = (li.dataset.city || '').toLowerCase();
                                if (currentCityFilter === 'all') {
                                    return city === 'all';
                                }
                                return city === currentCityFilter.toLowerCase();
                            });
                            if (toHighlight) {
                                toHighlight.classList.add('bg-light', 'fw-bold');
                            }
                            
                            // Attach click event listeners to the newly created location items
                            attachLocationClickListeners();

                            const selectedLocationInput = document.getElementById('selectedLocation');
                            if (selectedLocationInput) {
                                if (currentCityFilter === 'all') {
                                    selectedLocationInput.value = '';
                                } else {
                                    selectedLocationInput.value = currentCityFilter;
                                }
                            }
                            checkEnableButtons();
                        }
                    }

                    function updateDisplay() {
                        // Update Top Donor Locations (independent of filters)
                        updateTopDonorLocations();
                        
                        // Process addresses and update heatmap
                        processAddresses();
                    }

                    // Add event listeners - reload GIS data when blood type filter changes
                    bloodTypeFilter.addEventListener('change', function() {
                        const selectedBloodType = this.value;
                        console.log('🩸 Blood type filter changed to:', selectedBloodType);
                        // Reload GIS data with the selected blood type filter
                        loadGISData(selectedBloodType);
                    });

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

                    // Initialize top donor locations (without map)
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
                                const clickedCity = (this.dataset.city || '').trim();
                                const displayName = this.querySelector('strong') ? this.querySelector('strong').textContent : clickedCity;
                                console.log('Location clicked:', displayName || 'Unknown');
                                
                                // Remove highlight from all
                                Array.from(locationListEl.querySelectorAll('li')).forEach(l => l.classList.remove('bg-light', 'fw-bold'));
                                
                                // Highlight selected
                                this.classList.add('bg-light', 'fw-bold');
                                
                                if (clickedCity === 'all') {
                                    currentCityFilter = 'all';
                                    selectedLocationInput.value = '';
                                } else {
                                    currentCityFilter = displayName;
                                    selectedLocationInput.value = displayName;
                                }
                                console.log('Selected location set to:', selectedLocationInput.value || 'All Locations');
                                
                                // Enable buttons if date and time are set
                                checkEnableButtons();

                                // Update heatmap based on selected city filter
                                if (mapInitialized) {
                                    processAddresses();
                                }
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
        document.addEventListener('DOMContentLoaded', function() {
            window.showConfirmationModal = function() {
                if (typeof window.openAdminDonorRegistrationModal === 'function') {
                    window.openAdminDonorRegistrationModal();
                } else {
                    console.error('Admin donor registration modal not available yet');
                    alert('Registration modal is still loading. Please try again in a moment.');
                }
            };
            
            // Function to open QR Registration page
            window.openQRRegistration = function() {
                const qrRegistrationUrl = '../../src/views/forms/qr-registration.php';
                // Open in a new window/tab
                window.open(qrRegistrationUrl, 'QRRegistration', 'width=1200,height=800,scrollbars=yes,resizable=yes');
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
    <script src="../../assets/js/admin-donor-registration-modal.js"></script>
    <script src="../../assets/js/defer_donor_modal.js"></script>
    <script src="../../assets/js/initial-screening-defer-button.js"></script>
    <script src="../../assets/js/admin-screening-form-modal.js"></script>
    <script src="../../assets/js/admin-declaration-form-modal.js"></script>
</body>
</html>
</body>
</html>