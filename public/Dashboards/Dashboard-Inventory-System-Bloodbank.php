<?php
// Start session FIRST before any headers or output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

function renderDangerIcon($count, $threshold, $typeLabelAttr = '')
{
    $isLow = $count <= $threshold;
    $classes = 'text-danger' . ($isLow ? '' : ' d-none');
    $dataAttr = $typeLabelAttr !== '' ? ' data-danger-icon="' . htmlspecialchars($typeLabelAttr, ENT_QUOTES, 'UTF-8') . '"' : '';
    return '<span class="' . $classes . '" title="Low availability" style="position:absolute; top:8px; right:10px;"' . $dataAttr . '><i class="fas fa-exclamation-triangle"></i></span>';
}

function formatBufferReserveText($count) {
    $plural = ($count === 1) ? '' : 's';
    $countText = "<strong>{$count} buffer unit{$plural}</strong>";
    return "{$countText} are held in reserve. They remain highlighted in the table and are excluded from the totals above.";
}

// Include auto-dispose function for expired blood units
require_once '../../assets/php_func/admin_blood_bank_auto_dispose.php';
require_once '../../assets/php_func/buffer_blood_manager.php';
require_once __DIR__ . '/module/blood_inventory_data.php';

// Auto-dispose expired blood bank units on page load
// This marks units with expires_at <= today as "Disposed"
try {
    $disposeResult = admin_blood_bank_auto_dispose();
    if ($disposeResult['success'] && $disposeResult['disposed_count'] > 0) {
        error_log("Blood Bank Dashboard: Auto-disposed {$disposeResult['disposed_count']} expired unit(s)");
    }
} catch (Exception $e) {
    error_log("Blood Bank Dashboard: Error during auto-dispose: " . $e->getMessage());
    // Continue loading page even if auto-dispose fails
}

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// OPTIMIZATION: Smart caching with intelligent change detection
$cacheKey = 'bloodbank_dashboard_v1_' . date('Y-m-d'); // Daily cache key
$cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.json';
$cacheMetaFile = sys_get_temp_dir() . '/' . $cacheKey . '_meta.json';

// Cache enabled with improved change detection (disabled for accuracy-first requirement)
$cacheEnabled = false;
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
if ($cacheEnabled && file_exists($cacheFile) && file_exists($cacheMetaFile)) {
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

// OPTIMIZATION 8: Efficient statistics calculation using array functions
$today = new DateTime();
$expiryLimit = (new DateTime())->modify('+7 days');

// Use array_reduce for single-pass statistics calculation
$stats = array_reduce($activeInventory, function($carry, $bag) use ($today, $expiryLimit) {
    // Count available (valid) bags; each unit represents 1 bag
    if ($bag['status'] === 'Valid') {
        $carry['totalBags'] += 1;
        $carry['validBags']++;
        // Track available blood types
        if (!in_array($bag['blood_type'], $carry['availableTypes'])) {
            $carry['availableTypes'][] = $bag['blood_type'];
        }
    }
    
    // Count expired bags - includes both 'Expired' and 'Disposed' status
    // Disposed units are expired units that have been marked as disposed
    if ($bag['status'] == 'Expired' || $bag['status'] == 'Disposed') {
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

if ($cacheEnabled) {
    file_put_contents($cacheFile, json_encode($cacheData));
    file_put_contents($cacheMetaFile, json_encode($cacheMeta));
}

// Cache loaded marker
cache_loaded:

// Blood type filter (server-side to load complete set by type)
$recognizedBloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
$activeBloodTypeFilter = null;
if (!empty($_GET['blood_type'])) {
    $requestedType = strtoupper($_GET['blood_type']);
    if (in_array($requestedType, $recognizedBloodTypes, true)) {
        $activeBloodTypeFilter = $requestedType;
        $bloodInventory = array_values(array_filter($bloodInventory, function ($bag) use ($activeBloodTypeFilter) {
            return isset($bag['blood_type']) && strtoupper($bag['blood_type']) === $activeBloodTypeFilter;
        }));
    }
}

// OPTIMIZATION: Implement pagination like donor management
$itemsPerPage = 10; // Show only 10 items per page
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($currentPage < 1) { $currentPage = 1; }

$totalItems = count($bloodInventory);
$totalPages = ceil($totalItems / $itemsPerPage);
if ($currentPage > $totalPages && $totalPages > 0) { $currentPage = $totalPages; }

$startIndex = ($currentPage - 1) * $itemsPerPage;
$currentPageInventory = array_slice($bloodInventory, $startIndex, $itemsPerPage);

$paginationStart = $totalItems > 0 ? $startIndex + 1 : 0;
$paginationEnd = $totalItems > 0 ? min($startIndex + $itemsPerPage, $totalItems) : 0;
$paginationInfoText = "Showing {$paginationStart} to {$paginationEnd} of {$totalItems} entries";

$qs = $_GET;
unset($qs['page']);
$baseQs = http_build_query($qs);
$makePageUrl = function ($page) use ($baseQs) {
    $page = max(1, (int)$page);
    return '?' . ($baseQs ? $baseQs . '&' : '') . 'page=' . $page;
};

ob_start();
if ($totalPages > 1) {
    ?>
    <div class="d-flex justify-content-center mt-4" data-pagination-wrapper>
        <nav aria-label="Blood Bank pagination">
            <ul class="pagination">
                <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl(max(1, $currentPage - 1))); ?>" aria-label="Previous" data-pagination-link="true" data-page="<?php echo max(1, $currentPage - 1); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php
                $startPage = max(1, $currentPage - 1);
                $endPage = min($totalPages, $currentPage + 2);
                if ($currentPage <= 2) {
                    $endPage = min($totalPages, 4);
                }
                if ($currentPage >= $totalPages - 1) {
                    $startPage = max(1, $totalPages - 3);
                }
                if ($startPage > 1) {
                    ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl(1)); ?>" data-pagination-link="true" data-page="1">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                <?php }
                for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl($i)); ?>" data-pagination-link="true" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor;
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) { ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php }
                    ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl($totalPages)); ?>" data-pagination-link="true" data-page="<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                    </li>
                <?php } ?>
                <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl(min($totalPages, $currentPage + 1))); ?>" aria-label="Next" data-pagination-link="true" data-page="<?php echo min($totalPages, $currentPage + 1); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php
}
$paginationHtml = ob_get_clean();

// Blood type counts used across full render + partial responses
$bloodTypeCounts = array_reduce($activeInventory, function($carry, $bag) {
    if ($bag['status'] == 'Valid' && isset($carry[$bag['blood_type']])) {
        $carry[$bag['blood_type']] += 1;
    }
    return $carry;
}, [
    'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
    'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
]);

$lowThreshold = 25;
$bufferReserveText = formatBufferReserveText($bufferReserveCount);

if (isset($_GET['partial']) && $_GET['partial'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'currentPageInventory' => $currentPageInventory,
        'pagination' => [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'infoText' => $paginationInfoText,
            'start' => $paginationStart,
            'end' => $paginationEnd,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
            'html' => $paginationHtml
        ],
        'stats' => [
            'totalBags' => $totalBags,
            'availableTypes' => count($availableTypes),
            'expiringBags' => $expiringBags,
            'expiredBags' => $expiredBags
        ],
        'bloodTypeCounts' => $bloodTypeCounts,
        'bufferReserveCount' => $bufferReserveCount,
        'bufferReserveText' => $bufferReserveText,
        'bufferContext' => $bufferContext,
        'activeBloodTypeFilter' => $activeBloodTypeFilter,
        'lowThreshold' => $lowThreshold
    ]);
    exit;
}

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

.buffer-banner {
    background: #fff8e1;
    border: 1px solid #f7d774;
    color: #8a5300;
    border-radius: 6px;
    padding: 12px 15px;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

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

.buffer-row {
    background: #fffdf0 !important;
    position: relative;
}

.buffer-row td {
    border-top: 1px solid #fde9a5 !important;
}

.buffer-type-card {
    border-color: #f7d774;
    box-shadow: 0 0 0 1px rgba(247, 215, 116, 0.4);
    background: linear-gradient(135deg, #fffdf0 0%, #fff6d5 100%);
}

.buffer-drawer {
    border: 1px solid #f7d774;
    border-radius: 8px;
    padding: 15px;
    background: #fffef6;
    display: none;
}

.buffer-drawer.open {
    display: block;
}

.buffer-drawer table {
    font-size: 0.9rem;
}

.inventory-stat-number {
    font-size: 64px;
    font-weight: bold;
    color: #dc3545;
}

.blood-type-card {
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.blood-type-card:focus,
.blood-type-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.12);
    outline: none;
}

.blood-type-card.active {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220,53,69,0.25);
}

.blood-type-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
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

/* OPTIMIZATION: Smooth pagination transitions (same as donor management) */
.pagination .page-link {
    transition: all 0.2s ease-in-out;
}

.pagination .page-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.pagination .page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.pagination .page-link:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
    </style>
</head>
<body>
    <?php include '../../src/views/modals/admin-donor-registration-modal.php'; ?>
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
                                    <h5 class="card-title text-dark fw-bold">Total Blood Units (Active)</h5>
                                    <h1 class="inventory-stat-number my-3" id="totalBagsCount"><?php echo $totalBags; ?></h1>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Available Types -->
                        <div class="col-md-3">
                            <div class="card text-center p-3 h-100 inventory-stat-card">
                                <div class="card-body">
                                    <h5 class="card-title text-dark fw-bold">Available Types</h5>
                                    <h1 class="inventory-stat-number my-3" id="availableTypesCount"><?php echo count($availableTypes); ?></h1>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Units Expiring Soon -->
                        <div class="col-md-3">
                            <div class="card text-center p-3 h-100 inventory-stat-card">
                                <div class="card-body">
                                    <h5 class="card-title text-dark fw-bold">Units Expiring Soon</h5>
                                    <h1 class="inventory-stat-number my-3" id="expiringBagsCount"><?php echo $expiringBags; ?></h1>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Expired Units -->
                        <div class="col-md-3">
                            <div class="card text-center p-3 h-100 inventory-stat-card">
                                <div class="card-body">
                                    <h5 class="card-title text-dark fw-bold">Expired Units</h5>
                                    <h1 class="inventory-stat-number my-3" id="expiredBagsCount"><?php echo $expiredBags; ?></h1>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="buffer-banner mb-3" data-buffer-banner>
                        <i class="fas fa-shield-alt fa-lg"></i>
                        <div data-buffer-reserve-text><?php echo $bufferReserveText; ?></div>
                        <button type="button" class="btn btn-outline-warning btn-sm" data-buffer-toggle>
                            <i class="fas fa-list me-1"></i>View Buffer List
                        </button>
                    </div>
                    <div class="buffer-drawer mb-4" data-buffer-drawer>
                        <?php if ($bufferReserveCount > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Serial</th>
                                            <th>Blood Type</th>
                                            <th>Bag</th>
                                            <th>Collected</th>
                                            <th>Expires</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bufferContext['buffer_units'] as $bufferUnit): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($bufferUnit['serial_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($bufferUnit['blood_type'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($bufferUnit['bag_type'] ?? 'Standard'); ?></td>
                                                <td><?php echo $bufferUnit['collected_at'] ? date('M d, Y', strtotime($bufferUnit['collected_at'])) : '—'; ?></td>
                                                <td><?php echo $bufferUnit['expires_at'] ? date('M d, Y', strtotime($bufferUnit['expires_at'])) : '—'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No buffer units identified at the moment.</p>
                        <?php endif; ?>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Available Blood per Unit</h5>
                    
                    
                    <div class="row mb-4">
                        <!-- Blood Type Cards -->
                        <?php
                            $bloodTypeOrder = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                            foreach ($bloodTypeOrder as $typeLabel):
                                $availableCount = (int)($bloodTypeCounts[$typeLabel] ?? 0);
                                $bufferTypeCount = (int)($bufferContext['buffer_types'][$typeLabel] ?? 0);
                                $cardClasses = 'card p-3 h-100 blood-type-card position-relative';
                                if ($bufferTypeCount > 0) {
                                    $cardClasses .= ' buffer-type-card';
                                }
                                if ($activeBloodTypeFilter === $typeLabel) {
                                    $cardClasses .= ' active';
                                }
                                $typeLabelAttr = htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8');
                                $isActiveType = $activeBloodTypeFilter === $typeLabel ? 'true' : 'false';
                        ?>
                        <div class="col-md-3 mb-3">
                            <div class="<?php echo $cardClasses; ?>"
                                data-blood-type-card="<?php echo $typeLabelAttr; ?>"
                                role="button"
                                tabindex="0"
                                aria-pressed="<?php echo $isActiveType; ?>"
                                onclick="applyBloodTypeFilter('<?php echo $typeLabelAttr; ?>')"
                                onkeydown="handleBloodTypeCardKey(event, '<?php echo $typeLabelAttr; ?>')">
                                <?php echo renderDangerIcon($availableCount, $lowThreshold, $typeLabelAttr); ?>
                                <div class="d-flex justify-content-end mb-1">
                                    <?php
                                        $bufferPillClasses = 'buffer-pill';
                                        if ($bufferTypeCount <= 0) {
                                            $bufferPillClasses .= ' d-none';
                                        }
                                    ?>
                                    <span class="<?php echo $bufferPillClasses; ?>" data-buffer-highlight data-buffer-type="<?php echo $typeLabelAttr; ?>"><?php echo $bufferTypeCount; ?> in buffer</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Blood Type: <?php echo $typeLabel; ?></h5>
                                    <p class="card-text">Availability:
                                        <span class="fw-bold" data-blood-type-count="<?php echo $typeLabelAttr; ?>"><?php echo $availableCount; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php
                        $bloodTypeFilterInfoClasses = 'alert alert-warning mt-2 py-2 px-3';
                        if (!$activeBloodTypeFilter) {
                            $bloodTypeFilterInfoClasses .= ' d-none';
                        }
                    ?>
                    <div id="bloodTypeFilterInfo" class="<?php echo $bloodTypeFilterInfoClasses; ?>">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <span class="blood-type-filter-pill mb-0">
                                <i class="fas fa-filter me-1"></i>
                                <span id="bloodTypeFilterLabel">
                                    <?php if ($activeBloodTypeFilter): ?>
                                        Filtering by <strong><?php echo htmlspecialchars($activeBloodTypeFilter); ?></strong>
                                    <?php else: ?>
                                        Select a blood type to filter inventory
                                    <?php endif; ?>
                                </span>
                            </span>
                            <div class="d-flex gap-2">
                                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-sm btn-danger" onclick="event.preventDefault(); clearBloodTypeFilter();">
                                    Clear Filter
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Blood Bank Table -->
                    <div class="table-responsive mt-4">
                        <div id="inventoryLoadingIndicator" class="alert alert-light border d-flex align-items-center gap-2 py-2 px-3 mb-3 d-none">
                            <div class="spinner-border spinner-border-sm text-danger" role="status" aria-hidden="true"></div>
                            <span class="fw-semibold text-muted">Loading the latest inventory...</span>
                        </div>
                        <!-- Pagination Info -->
                        <div class="row mb-3">
                            <div class="col-12 text-center">
                                <span class="text-muted" data-pagination-info><?php echo $paginationInfoText; ?></span>
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
                                <?php
                                    $unitId = $bag['unit_id'] ?? null;
                                    $serialNumber = $bag['serial_number'] ?? '';
                                    $isBufferRow = !empty($bag['is_buffer']);
                                    $rowClasses = $isBufferRow ? 'buffer-row' : '';
                                ?>
                                <?php
                                    $unitIdentifier = $unitId ?? $serialNumber ?? '';
                                ?>
                                <tr class="<?php echo $rowClasses; ?>"
                                    data-unit-id="<?php echo htmlspecialchars($unitId ?? $serialNumber ?? ''); ?>"
                                    data-unit-serial="<?php echo htmlspecialchars($serialNumber); ?>"
                                    data-blood-type="<?php echo htmlspecialchars($bag['blood_type']); ?>">
                                    <td>
                                        <?php echo htmlspecialchars($serialNumber); ?>
                                        <?php if ($isBufferRow): ?>
                                            <span class="buffer-pill ms-2" data-buffer-unit>Buffer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($bag['blood_type']); ?></td>
                                    <td><?php echo htmlspecialchars($bag['collection_date']); ?></td>
                                    <td><?php echo htmlspecialchars($bag['expiration_date']); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-primary btn-sm"
                                            data-unit-trigger
                                            data-unit-id="<?php echo htmlspecialchars($unitIdentifier); ?>">
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
                        
                        <!-- Pagination Controls -->
                        <div data-pagination-container>
                            <?php echo $paginationHtml; ?>
                        </div>
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

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
        $bufferJsPayload = [
            'count' => $bufferReserveCount,
            'unit_ids' => array_keys($bufferContext['buffer_lookup']['by_id']),
            'unit_serials' => array_keys($bufferContext['buffer_lookup']['by_serial']),
            'types' => $bufferContext['buffer_types'],
            'page' => 'bloodbank'
        ];
    ?>
    <script>
        window.BUFFER_BLOOD_CONTEXT = <?php echo json_encode($bufferJsPayload); ?>;
    </script>
    <script src="../../assets/js/buffer-blood-manager.js"></script>
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
        let currentPageInventoryData = <?php echo json_encode($currentPageInventory); ?>;
        let currentPageNumber = <?php echo $currentPage; ?>;
        const itemsPerPage = <?php echo $itemsPerPage; ?>;
        let activeBloodTypeFilter = <?php echo $activeBloodTypeFilter ? json_encode($activeBloodTypeFilter) : 'null'; ?>;
        let bloodTypeCountsData = <?php echo json_encode($bloodTypeCounts); ?>;
        let bufferReserveCountData = <?php echo $bufferReserveCount; ?>;
        let bufferReserveTextData = <?php echo json_encode($bufferReserveText); ?>;
        let bufferContextData = <?php echo json_encode($bufferContext); ?>;
        let paginationInfoText = <?php echo json_encode($paginationInfoText); ?>;
        let paginationHtmlCache = <?php echo json_encode($paginationHtml); ?>;
        let inventoryAbortController = null;
        const lowThreshold = <?php echo $lowThreshold; ?>;
        const inventoryCache = new Map();
        const CACHE_TTL = 60000; // 60 seconds
        const unitFallbackMap = new Map();
        
        let statsElements = {};
        let paginationInfoEl = null;
        let paginationContainer = null;
        let bloodTypeFilterInfo = null;
        let bloodTypeFilterLabel = null;
        let loadingIndicator = null;
        let tableElement = null;
        let searchInputElement = null;
        let searchCategoryElement = null;
        let searchInfoElement = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            window.showConfirmationModal = function() {
                if (typeof window.openAdminDonorRegistrationModal === 'function') {
                    window.openAdminDonorRegistrationModal();
                } else {
                    console.error('Admin donor registration modal not available yet');
                    alert('Registration modal is still loading. Please try again in a moment.');
                }
            };
            
            statsElements = {
                totalBags: document.getElementById('totalBagsCount'),
                availableTypes: document.getElementById('availableTypesCount'),
                expiring: document.getElementById('expiringBagsCount'),
                expired: document.getElementById('expiredBagsCount')
            };
            paginationInfoEl = document.querySelector('[data-pagination-info]');
            paginationContainer = document.querySelector('[data-pagination-container]');
            bloodTypeFilterInfo = document.getElementById('bloodTypeFilterInfo');
            bloodTypeFilterLabel = document.getElementById('bloodTypeFilterLabel');
            loadingIndicator = document.getElementById('inventoryLoadingIndicator');
            tableElement = document.getElementById('bloodInventoryTable');
            searchInputElement = document.getElementById('searchInput');
            searchCategoryElement = document.getElementById('searchCategory');
            
            if (searchInputElement && searchCategoryElement && tableElement) {
                let searchTimeout;
                const triggerSearch = () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(runTableSearch, 150);
                };
                searchInputElement.addEventListener('input', triggerSearch);
                searchCategoryElement.addEventListener('change', triggerSearch);
            }
            
            document.addEventListener('click', function(event) {
                const detailBtn = event.target.closest('[data-unit-trigger]');
                if (detailBtn) {
                    event.preventDefault();
                    const unitId = detailBtn.getAttribute('data-unit-id');
                    showDonorDetails(unitId);
                    return;
                }
                
                const paginationLink = event.target.closest('[data-pagination-link]');
                if (paginationLink) {
                    const parentItem = paginationLink.closest('.page-item');
                    if (parentItem && parentItem.classList.contains('disabled')) {
                        event.preventDefault();
                        return;
                    }
                    const nextPage = parseInt(paginationLink.getAttribute('data-page'), 10);
                    if (Number.isNaN(nextPage) || nextPage === currentPageNumber) {
                        event.preventDefault();
                        return;
                    }
                    event.preventDefault();
                    fetchInventoryData({ bloodType: activeBloodTypeFilter, page: nextPage });
                }
            });
            
            window.clearSearch = function() {
                if (searchInputElement) searchInputElement.value = '';
                if (searchCategoryElement) searchCategoryElement.value = 'all';
                runTableSearch();
            };
            
            rebuildFallbackMap(currentPageInventoryData);
            updateBloodTypeFilterUI();
            updatePaginationUI();
            runTableSearch();
        });
        
        window.addEventListener('popstate', function() {
            const url = new URL(window.location.href);
            const bloodType = url.searchParams.get('blood_type') || null;
            const page = parseInt(url.searchParams.get('page'), 10) || 1;
            fetchInventoryData({ bloodType, page }, false);
        });
        
        function showDonorDetails(unitIdentifier) {
            const unitId = (unitIdentifier ?? '').toString().trim();
            if (!unitId) {
                console.warn('Missing unit identifier for donor details.');
                return;
            }
            const fallbackBag = unitFallbackMap.get(unitId) || null;
            document.getElementById('modal-loading').style.display = 'block';
            const modal = new bootstrap.Modal(document.getElementById('bloodBankUnitDetailsModal'));
            modal.show();
            
            fetch(`../../assets/php_func/blood_bank_unit_details_api.php?unit_id=${encodeURIComponent(unitId)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modal-loading').style.display = 'none';
                    if (data.error) {
                        console.error('Error fetching unit details:', data.error);
                        if (fallbackBag) {
                            populateBasicData(fallbackBag);
                        }
                    } else {
                        populateDetailedData(data);
                    }
                })
                .catch(error => {
                    document.getElementById('modal-loading').style.display = 'none';
                    console.error('Error fetching unit details:', error);
                    if (fallbackBag) {
                        populateBasicData(fallbackBag);
                    }
                });
        }
        
        function populateDetailedData(data) {
            document.getElementById('modal-unit-serial').textContent = data.unit_serial_number || 'N/A';
            document.getElementById('modal-blood-type-display').textContent = data.blood_type || 'N/A';
            document.getElementById('modal-collection-date').value = data.collection_date || 'N/A';
            document.getElementById('modal-expiration-date').value = data.expiration_date || 'N/A';
            document.getElementById('modal-collected-from').value = data.collected_from || 'Blood Bank';
            document.getElementById('modal-recipient-hospital').value = data.recipient_hospital || 'Not Assigned';
            document.getElementById('modal-phlebotomist-name').value = data.phlebotomist_name || 'Not Available';
            document.getElementById('modal-blood-status').value = data.blood_status || 'Available';
        }
        
        function populateBasicData(bag) {
            document.getElementById('modal-unit-serial').textContent = bag.serial_number || 'N/A';
            document.getElementById('modal-blood-type-display').textContent = bag.blood_type || 'N/A';
            document.getElementById('modal-collection-date').value = bag.collection_date || 'N/A';
            document.getElementById('modal-expiration-date').value = bag.expiration_date || 'N/A';
            document.getElementById('modal-collected-from').value = 'Blood Bank';
            document.getElementById('modal-recipient-hospital').value = 'Not Assigned';
            document.getElementById('modal-phlebotomist-name').value = 'Not Available';
            document.getElementById('modal-blood-status').value = bag.status || 'Available';
        }
        
        function fetchInventoryData({ bloodType = null, page = 1 } = {}, pushHistory = true) {
            const normalizedType = bloodType ? bloodType.toUpperCase() : null;
            const targetUrl = buildUrlWithParams(normalizedType, page);
            const cacheKey = `${normalizedType || 'ALL'}::${page}`;
            const cachedEntry = inventoryCache.get(cacheKey);
            const now = Date.now();
            if (cachedEntry && (now - cachedEntry.timestamp) < CACHE_TTL) {
                applyInventoryPayload(cachedEntry.payload);
                if (pushHistory) {
                    const relativeUrl = targetUrl.pathname + (targetUrl.search ? targetUrl.search : '');
                    window.history.pushState({ bloodType: normalizedType, page }, '', relativeUrl);
                }
                return;
            }
            const fetchUrl = new URL(targetUrl.toString());
            fetchUrl.searchParams.set('partial', '1');
            
            showInventoryLoading(true);
            if (inventoryAbortController) {
                inventoryAbortController.abort();
            }
            inventoryAbortController = new AbortController();
            
            fetch(fetchUrl.toString(), { signal: inventoryAbortController.signal })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to load inventory data.');
                    }
                    return response.json();
                })
                .then(payload => {
                    if (!payload?.success) {
                        throw new Error(payload?.message || 'Unexpected server response.');
                    }
                    applyInventoryPayload(payload);
                    inventoryCache.set(cacheKey, { payload, timestamp: Date.now() });
                    if (inventoryCache.size > 12) {
                        const oldestKey = inventoryCache.keys().next().value;
                        inventoryCache.delete(oldestKey);
                    }
                    if (pushHistory) {
                        const relativeUrl = targetUrl.pathname + (targetUrl.search ? targetUrl.search : '');
                        window.history.pushState({ bloodType: normalizedType, page }, '', relativeUrl);
                    }
                })
                .catch(error => {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    console.error('Inventory refresh failed:', error);
                    alert('Unable to load the latest inventory. Please try again.');
                })
                .finally(() => {
                    showInventoryLoading(false);
                });
        }
        
        function applyInventoryPayload(payload) {
            currentPageInventoryData = payload.currentPageInventory || [];
            currentPageNumber = payload.pagination?.currentPage || 1;
            activeBloodTypeFilter = payload.activeBloodTypeFilter || null;
            bloodTypeCountsData = payload.bloodTypeCounts || bloodTypeCountsData;
            bufferReserveCountData = payload.bufferReserveCount ?? bufferReserveCountData;
            bufferReserveTextData = payload.bufferReserveText ?? bufferReserveTextData;
            bufferContextData = payload.bufferContext || bufferContextData;
            paginationInfoText = payload.pagination?.infoText || paginationInfoText;
            paginationHtmlCache = payload.pagination?.html || '';
            
            updateStats(payload.stats);
            updateBloodTypeCards();
            updateBloodTypeFilterUI();
            updateBufferBanner();
            updatePaginationUI(payload.pagination);
            updateTableRows(currentPageInventoryData);
            runTableSearch();
            refreshBufferContextForScripts();
        }
        
        function updateStats(stats = {}) {
            if (statsElements.totalBags) {
                statsElements.totalBags.textContent = stats.totalBags ?? statsElements.totalBags.textContent;
            }
            if (statsElements.availableTypes) {
                statsElements.availableTypes.textContent = stats.availableTypes ?? statsElements.availableTypes.textContent;
            }
            if (statsElements.expiring) {
                statsElements.expiring.textContent = stats.expiringBags ?? statsElements.expiring.textContent;
            }
            if (statsElements.expired) {
                statsElements.expired.textContent = stats.expiredBags ?? statsElements.expired.textContent;
            }
        }
        
        function updateBloodTypeCards() {
            const cards = document.querySelectorAll('[data-blood-type-card]');
            cards.forEach(card => {
                const type = card.getAttribute('data-blood-type-card');
                const isActive = activeBloodTypeFilter && type === activeBloodTypeFilter;
                card.classList.toggle('active', Boolean(isActive));
                card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                
                const countValue = bloodTypeCountsData?.[type] ?? 0;
                const countEl = card.querySelector('[data-blood-type-count]');
                if (countEl) {
                    countEl.textContent = countValue;
                }
                
                const dangerIcon = card.querySelector('[data-danger-icon]');
                if (dangerIcon) {
                    if (countValue <= lowThreshold) {
                        dangerIcon.classList.remove('d-none');
                    } else {
                        dangerIcon.classList.add('d-none');
                    }
                }
                
                const bufferBadge = card.querySelector('[data-buffer-type]');
                if (bufferBadge) {
                    const bufferCount = bufferContextData?.buffer_types?.[type] ?? 0;
                    bufferBadge.textContent = `${bufferCount} in buffer`;
                    bufferBadge.classList.toggle('d-none', bufferCount <= 0);
                }
            });
        }
        
        function updateBloodTypeFilterUI() {
            if (!bloodTypeFilterInfo || !bloodTypeFilterLabel) return;
            if (activeBloodTypeFilter) {
                bloodTypeFilterInfo.classList.remove('d-none');
                bloodTypeFilterLabel.innerHTML = `Filtering by <strong>${escapeHtml(activeBloodTypeFilter)}</strong>`;
            } else {
                bloodTypeFilterInfo.classList.add('d-none');
                bloodTypeFilterLabel.textContent = 'Select a blood type to filter inventory';
            }
        }
        
        function updateBufferBanner() {
            const bannerText = document.querySelector('[data-buffer-reserve-text]');
            if (bannerText) {
                bannerText.innerHTML = bufferReserveTextData;
            }
        }
        
        function updatePaginationUI(pagination = null) {
            if (paginationInfoEl && paginationInfoText) {
                paginationInfoEl.textContent = paginationInfoText;
            }
            if (paginationContainer) {
                paginationContainer.innerHTML = pagination?.html || paginationHtmlCache || '';
            }
        }
        
        function updateTableRows(rows) {
            if (!tableElement) return;
            const tbody = tableElement.querySelector('tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            rebuildFallbackMap(rows);
            
            if (!rows || rows.length === 0) {
                const emptyRow = document.createElement('tr');
                emptyRow.innerHTML = '<td colspan="5" class="text-center">No blood inventory records found. Please wait for an administrator to add data.</td>';
                tbody.appendChild(emptyRow);
                return;
            }
            
            rows.forEach((bag) => {
                const unitKey = getUnitKey(bag);
                const row = document.createElement('tr');
                if (bag.is_buffer) {
                    row.classList.add('buffer-row');
                }
                row.setAttribute('data-unit-id', escapeHtml(unitKey));
                row.setAttribute('data-unit-serial', escapeHtml(bag.serial_number ?? ''));
                row.setAttribute('data-blood-type', escapeHtml(bag.blood_type ?? ''));
                row.innerHTML = `
                    <td>
                        ${escapeHtml(bag.serial_number ?? '')}
                        ${bag.is_buffer ? '<span class="buffer-pill ms-2" data-buffer-unit>Buffer</span>' : ''}
                    </td>
                    <td>${escapeHtml(bag.blood_type ?? '')}</td>
                    <td>${escapeHtml(bag.collection_date ?? '')}</td>
                    <td>${escapeHtml(bag.expiration_date ?? '')}</td>
                    <td class="text-center">
                        <button class="btn btn-primary btn-sm" data-unit-trigger data-unit-id="${escapeHtml(unitKey)}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        function runTableSearch() {
            if (!tableElement) return;
            const rows = tableElement.querySelectorAll('tbody tr');
            const searchValue = (searchInputElement?.value || '').toLowerCase().trim();
            const categoryValue = searchCategoryElement?.value || 'all';
            
            let visibleRows = 0;
            let totalRows = 0;
            let noResultsRow = document.getElementById('noResultsRow');
            if (noResultsRow) {
                noResultsRow.remove();
            }
            
            rows.forEach(row => {
                if (row.id === 'noResultsRow') {
                    return;
                }
                const cells = row.querySelectorAll('td');
                if (cells.length === 0) {
                    return;
                }
                totalRows++;
                let shouldShow = true;
                if (searchValue) {
                    shouldShow = rowMatchesSearch(cells, categoryValue, searchValue);
                }
                row.style.display = shouldShow ? '' : 'none';
                if (shouldShow) {
                    visibleRows++;
                }
            });
            
            if (visibleRows === 0 && totalRows > 0) {
                const tbody = tableElement.querySelector('tbody');
                const colspan = tableElement.querySelector('thead tr').children.length;
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
            
            updateSearchInfo(visibleRows, totalRows, searchValue);
        }
        
        function rowMatchesSearch(cells, category, searchValue) {
            const haystack = text => text.toLowerCase().includes(searchValue);
            switch (category) {
                case 'serial':
                    return haystack(cells[0].textContent);
                case 'blood_type':
                    return haystack(cells[1].textContent);
                case 'component':
                    return haystack(cells[3].textContent);
                case 'date': {
                    const collectionDate = cells[2].textContent.toLowerCase();
                    const expirationDate = cells[3].textContent.toLowerCase();
                    const escapeRegExp = searchValue.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    const datePattern = new RegExp(escapeRegExp, 'i');
                    return datePattern.test(collectionDate) || datePattern.test(expirationDate);
                }
                case 'all':
                default:
                    for (let j = 0; j < cells.length; j++) {
                        if (haystack(cells[j].textContent)) {
                            return true;
                        }
                    }
                    return false;
            }
        }
        
        function updateSearchInfo(visibleRows, totalRows, searchValue) {
            const searchContainer = document.querySelector('.search-container');
            if (!searchContainer) return;
            if (!searchInfoElement) {
                searchInfoElement = document.createElement('div');
                searchInfoElement.id = 'searchInfo';
                searchInfoElement.classList.add('text-muted', 'mt-2', 'small');
                searchContainer.appendChild(searchInfoElement);
            }
            if (!searchValue) {
                searchInfoElement.textContent = '';
                return;
            }
            searchInfoElement.textContent = `Showing ${visibleRows} of ${totalRows} entries`;
        }
        
        function showInventoryLoading(isLoading) {
            if (!loadingIndicator) return;
            loadingIndicator.classList.toggle('d-none', !isLoading);
        }
        
        function buildUrlWithParams(bloodType, page) {
            const url = new URL(window.location.href);
            if (bloodType) {
                url.searchParams.set('blood_type', bloodType);
            } else {
                url.searchParams.delete('blood_type');
            }
            if (page && page > 1) {
                url.searchParams.set('page', page);
            } else {
                url.searchParams.delete('page');
            }
            url.searchParams.delete('partial');
            return url;
        }
        
        function rebuildFallbackMap(rows) {
            unitFallbackMap.clear();
            if (!Array.isArray(rows)) {
                return;
            }
            rows.forEach(bag => {
                const key = getUnitKey(bag);
                if (key) {
                    unitFallbackMap.set(key, bag);
                }
            });
        }
        
        function getUnitKey(bag) {
            if (!bag) return '';
            return bag.unit_id || bag.serial_number || '';
        }
        
        function refreshBufferContextForScripts() {
            if (!window.BUFFER_BLOOD_CONTEXT) return;
            window.BUFFER_BLOOD_CONTEXT.count = bufferReserveCountData;
            if (bufferContextData?.buffer_lookup) {
                window.BUFFER_BLOOD_CONTEXT.unit_ids = Object.keys(bufferContextData.buffer_lookup.by_id || {});
                window.BUFFER_BLOOD_CONTEXT.unit_serials = Object.keys(bufferContextData.buffer_lookup.by_serial || {});
            }
            if (bufferContextData?.buffer_types) {
                window.BUFFER_BLOOD_CONTEXT.types = bufferContextData.buffer_types;
            }
        }
        
        function escapeHtml(value) {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        
        function applyBloodTypeFilter(typeLabel) {
            const normalized = typeLabel ? typeLabel.toUpperCase() : null;
            const nextFilter = activeBloodTypeFilter === normalized ? null : normalized;
            fetchInventoryData({ bloodType: nextFilter, page: 1 });
        }
        
        function clearBloodTypeFilter() {
            fetchInventoryData({ bloodType: null, page: 1 });
        }
        
        function handleBloodTypeCardKey(event, typeLabel) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                applyBloodTypeFilter(typeLabel);
            }
        }
    </script>
    <script src="../../assets/js/admin-donor-registration-modal.js"></script>
    <script src="../../assets/js/admin-screening-form-modal.js"></script>
</body>
</html>