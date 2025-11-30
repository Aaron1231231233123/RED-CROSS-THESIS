<?php
// Prevent browser caching (server-side caching is handled below)
header('Vary: Accept-Encoding');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check for correct role (admin only)
$required_role = 1; // Admin role
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== $required_role) {
    header("Location: ../unauthorized.php");
    exit();
}

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/module/optimized_functions.php';

// OPTIMIZATION: Skeleton HTML generation for progressive loading
function generateSkeletonHTML() {
    return '
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="skeleton-header mb-4">
                    <div class="skeleton-title"></div>
                    <div class="skeleton-subtitle"></div>
                </div>
                <div class="skeleton-filters mb-4">
                    <div class="skeleton-filter"></div>
                    <div class="skeleton-filter"></div>
                </div>
                <div class="skeleton-table">
                    <div class="skeleton-row"></div>
                    <div class="skeleton-row"></div>
                    <div class="skeleton-row"></div>
                    <div class="skeleton-row"></div>
                    <div class="skeleton-row"></div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .skeleton-title { height: 32px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: loading 1.5s infinite; border-radius: 4px; margin-bottom: 8px; }
        .skeleton-subtitle { height: 20px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: loading 1.5s infinite; border-radius: 4px; width: 60%; }
        .skeleton-filter { height: 40px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: loading 1.5s infinite; border-radius: 4px; margin: 8px; display: inline-block; width: 200px; }
        .skeleton-row { height: 60px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: loading 1.5s infinite; border-radius: 4px; margin: 8px 0; }
        @keyframes loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        .skeleton-row { animation: pulse 1.5s ease-in-out infinite; }
        .skeleton-cell { height: 20px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: loading 1.5s infinite; border-radius: 4px; margin: 4px 0; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        
        /* OPTIMIZATION: Smooth pagination transitions */
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
        
        /* OPTIMIZATION: Smooth table transitions */
        #donationsTable tbody {
            transition: opacity 0.3s ease-in-out;
        }
        #donationsTable tbody.loading {
            opacity: 0.7;
        }
        
        /* OPTIMIZATION: Prevent backdrop stacking and ensure proper cleanup */
        .modal-backdrop {
            transition: opacity 0.15s linear;
        }
        .modal-backdrop.fade {
            opacity: 0;
        }
        .modal-backdrop.show {
            opacity: 0.5;
        }
        
        /* Ensure clean modal state */
        .modal {
            transition: opacity 0.15s linear;
        }
        .modal.fade {
            opacity: 0;
        }
        .modal.show {
            opacity: 1;
        }
        
        /* Prevent backdrop stacking - ensure only one backdrop exists */
        .modal-backdrop + .modal-backdrop {
            display: none !important;
        }
        
        /* Ensure proper z-index for modals */
        .modal {
            z-index: 1050;
        }
        .modal-backdrop {
            z-index: 1040;
        }
        /* Note: Z-index for confirmation modals is now handled dynamically via applyModalStacking() function
           This ensures proper stacking based on currently open modals rather than hardcoded values */
    </style>';
}

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// OPTIMIZATION: Manual cache refresh and debug options
if (isset($_GET['refresh']) && $_GET['refresh'] == '1') {
    // Force cache refresh - clear all cache layers
    if (function_exists('invalidateCache')) {
        invalidateCache('donations_list_*');
    }
    // Also clear session cache
    if (session_status() === PHP_SESSION_ACTIVE) {
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'donor_cache_') === 0) {
                unset($_SESSION[$key]);
            }
        }
    }
    error_log("CACHE: Manual refresh requested - all donation caches cleared");
}

if (isset($_GET['debug_cache']) && $_GET['debug_cache'] == '1') {
    // Debug mode - clear caches and enable detailed logging
    if (function_exists('invalidateCache')) {
        invalidateCache('donations_list_*');
    }
    error_log("CACHE: Debug mode enabled - donation caches cleared");
}

// Include database connection
include_once '../../assets/conn/db_conn.php';
// Light HTTP caching to improve TTFB on slow links (HTML only, app data still fresh)
header('Cache-Control: public, max-age=300, stale-while-revalidate=60');
header('Vary: Accept-Encoding');
// Get the status parameter from URL
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
// Default perf mode on; allow explicit off
$perfMode = !((isset($_GET['perf_mode']) && $_GET['perf_mode'] === 'off'));
$statusFilter = $status; // Preserve original status filter to avoid includes overwriting it
$donations = [];
$error = null;
$pageTitle = "All Donors";

// Initialize pagination early so modules receive correct LIMIT/OFFSET when filtered by status
$itemsPerPage = 10; // optimize initial render and navigation performance
// For pending filter, we need to fetch more records to ensure all pending donors are shown
$pendingItemsPerPage = 200; // Increased limit for pending filter to capture all pending donors
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($currentPage < 1) { $currentPage = 1; }
$startIndex = ($currentPage - 1) * $itemsPerPage;
// Configure pagination for ALL status modes (including 'all')
if ($status !== 'all') {
	// For specific status filters: fetch ALL donors to ensure all eligible donors are captured
	// We have 3000+ donors, so fetch enough to cover all of them
	// The module filters in PHP, so we need to fetch more than just the filtered count
	$GLOBALS['DONATION_LIMIT'] = 5000; // Fetch enough to cover all 3000+ donors and growth
	$GLOBALS['DONATION_OFFSET'] = 0; // Always start from beginning for filtering
	$GLOBALS['DONATION_DISPLAY_LIMIT'] = 0; // 0 = return all, dashboard handles pagination
	$GLOBALS['DONATION_DISPLAY_OFFSET'] = 0;
} else {
	// For 'all' status: fetch and return all records for aggregation
	$GLOBALS['DONATION_LIMIT'] = 5000; // Fetch all 3000+ donors for "all" status
	$GLOBALS['DONATION_OFFSET'] = 0;
	$GLOBALS['DONATION_DISPLAY_LIMIT'] = 0; // Return all for aggregation
	$GLOBALS['DONATION_DISPLAY_OFFSET'] = 0;
}
// OPTIMIZATION: Enhanced multi-layer caching system for maximum performance
// Layer 1: Memory cache (fastest, session-based)
// Layer 2: File cache (persistent, compressed)
// Layer 3: Database cache (long-term, with invalidation)
// OPTIMIZATION: AGGRESSIVE cache configuration for maximum performance
// Auto-invalidate cache when code changes
$codeHash = md5_file(__FILE__); // Hash of this file
$moduleHashes = [
    md5_file(__DIR__ . '/module/donation_pending.php'),
    md5_file(__DIR__ . '/module/donation_approved.php'),
    md5_file(__DIR__ . '/module/donation_declined.php')
];
$combinedHash = md5($codeHash . implode('', $moduleHashes));

// OPTIMIZATION: Enhanced cache strategy for pending filter LCP
if ($status === 'pending' && $perfMode) {
    // Use shorter cache TTL for pending filter to ensure fresh data
    $cacheTTL = 300; // 5 minutes for pending filter
    $cacheKey = "donations_list_pending_{$combinedHash}_v2";
} else {
    // Standard cache TTL for other filters
    $cacheTTL = 7200; // 2 hours for other filters
    $cacheKey = "donations_list_{$status}_{$combinedHash}_v2";
}

// INTELLIGENT CACHING: Fast hash detection that doesn't block LCP
function getDataChangeHash() {
    // Use a lightweight file-based cache to avoid blocking
    $cacheFile = sys_get_temp_dir() . '/dch_' . md5(SUPABASE_URL) . '.txt';
    
    // Try to use cached hash (updated in background)
    if (file_exists($cacheFile)) {
        $age = time() - filemtime($cacheFile);
        if ($age < 60) { // Use cached version if less than 1 minute old
            $cached = file_get_contents($cacheFile);
            if ($cached) return $cached;
        }
    }
    
    // If no cache or too old, return a time-based hash (background will update)
    return md5((int)(time() / 60)); // Changes every minute
}

function updateDataChangeHashInBackground() {
    try {
        // Query Supabase for the most recent timestamp from key tables
        $keyTables = ['eligibility', 'medical_history', 'screening_form', 'physical_examination', 'blood_collection'];
        $timestamps = [];
        
        foreach ($keyTables as $table) {
            // Use single query with union to get all timestamps at once
            $url = SUPABASE_URL . "/rest/v1/{$table}?select=updated_at,created_at&order=updated_at.desc,created_at.desc&limit=1";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Fast timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0.5);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data[0])) {
                    $ts = $data[0]['updated_at'] ?? $data[0]['created_at'] ?? null;
                    if ($ts) $timestamps[] = $ts;
                }
            }
        }
        
        $hash = md5(implode('|', $timestamps));
        $cacheFile = sys_get_temp_dir() . '/dch_' . md5(SUPABASE_URL) . '.txt';
        @file_put_contents($cacheFile, $hash);
    } catch (Exception $e) {
        error_log("Background hash update failed: " . $e->getMessage());
    }
}

// Get hash immediately (uses cache if available)
$dataChangeHash = getDataChangeHash();

// Update hash in background for next request
register_shutdown_function('updateDataChangeHashInBackground');

$cacheConfig = [
    'memory_ttl' => 3600,      // 1 hour memory cache (longer for stability)
    'file_ttl' => 3600,        // 1 hour file cache (smarter invalidation instead of longer cache)
    'db_ttl' => 7200,          // 2 hours database cache
    'compression' => true,     // Enable compression
    'warm_cache' => true,      // Enable cache warming
    'version' => 'v3.' . substr($combinedHash, 0, 8), // Code version
    'data_version' => substr($dataChangeHash, 0, 8), // Data change version
    'aggressive_mode' => true  // Enable aggressive optimizations
];
// Generate cache keys (filter-aware)
$statusKey = $status ?: 'all';
$filtersForKey = isset($_GET) && is_array($_GET) ? $_GET : [];
unset($filtersForKey['page']);
ksort($filtersForKey);
$filtersHash = md5(json_encode($filtersForKey));
$cacheKey = 'donations_list_' . $statusKey . '_p' . $currentPage . '_' . $filtersHash;

// Use a unified local cache directory when perf mode is enabled to align with invalidateCache/getCacheStats
$localCacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($localCacheDir)) {
	@mkdir($localCacheDir, 0777, true);
}
$cacheFile = $perfMode
	? ($localCacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json.gz')
	: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cacheKey . '.json.gz');
// Initialize cache state
$useCache = false;
$cacheSource = '';

// For pending status, disable caching to ensure fresh data
if ($status === 'pending') {
    $useCache = false;
    error_log("CACHE: Pending status detected - caching disabled for fresh data");
}

// Layer 1: Memory Cache (Session-based)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$memoryCacheKey = 'donor_cache_' . $cacheKey;
if (!$useCache && $status !== 'pending' && isset($_SESSION[$memoryCacheKey])) {
    $memoryData = $_SESSION[$memoryCacheKey];
    if (isset($memoryData['timestamp']) && (time() - $memoryData['timestamp']) < $cacheConfig['memory_ttl']) {
        $donations = $memoryData['data'];
        $useCache = true;
        $cacheSource = 'memory';
    }
}
// Layer 2: File Cache (if memory cache miss) - Enhanced validation
if (!$useCache && $status !== 'pending' && file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < $cacheConfig['file_ttl']) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            // Decompress if compressed
            if ($cacheConfig['compression'] && function_exists('gzdecode')) {
                $cached = gzdecode($cached);
            }
            $cachedData = json_decode($cached, true);
            
            // Enhanced cache validation (like main dashboard)
            if (is_array($cachedData)) {
                // Check cache version compatibility (code changes)
                $cacheVersion = $cachedData['version'] ?? 'v1';
                $cacheDataVersion = $cachedData['data_version'] ?? '';
                $currentDataVersion = $cacheConfig['data_version'] ?? '';
                
                // Invalidate if code changed OR data changed
                if ($cacheVersion !== $cacheConfig['version'] || $cacheDataVersion !== $currentDataVersion) {
                    if ($cacheVersion !== $cacheConfig['version']) {
                        error_log("CACHE: Code version mismatch ({$cacheVersion} vs {$cacheConfig['version']}) - invalidating");
                    } else {
                        error_log("CACHE: Data changed ({$cacheDataVersion} vs {$currentDataVersion}) - invalidating cache");
                    }
                    @unlink($cacheFile);
                } else {
                    // Unwrap cached envelope if present
                    if (isset($cachedData['data']) && is_array($cachedData['data'])) {
                        $donations = $cachedData['data'];
                    } else {
                        $donations = $cachedData;
                    }
                    
                    if (is_array($donations)) {
                        $useCache = true;
                        $cacheSource = 'file';
                        
                        // Store comprehensive data in memory cache
                        $_SESSION[$memoryCacheKey] = $cachedData;
                        
                        // Log cache hit with details
                        error_log("CACHE LOADED (v2) - Donations: " . count($donations) . " items (Age: " . round($age/60) . " mins)");
                    }
                }
            }
        }
    }
}
// OPTIMIZATION: Lazy loading for heavy modules (only load when needed)
try {
    if (!$useCache) {
        // Check if this is a lazy load request
        $isLazyLoad = isset($_GET['lazy']) && $_GET['lazy'] == '1';
        
        if ($isLazyLoad) {
            // For lazy loading, return minimal data and let JavaScript handle the rest
            $donations = [];
            $pageTitle = "Loading...";
            error_log("LAZY LOAD: Skipping heavy module loading for performance");
        } else {
            switch ($status) {
            case 'pending':
                $donations = []; // Clear donations array first
                include_once 'module/donation_pending.php';
                $donations = $pendingDonations ?? [];
                $pageTitle = "Pending Donations";
                break;
            case 'approved':
                $donations = []; // Clear donations array first
                include_once 'module/donation_approved.php';
                $donations = $approvedDonations ?? [];
                $pageTitle = "Approved Donations";
                break;
            case 'declined':
            case 'deferred':
                $donations = []; // Clear donations array first
                include_once 'module/donation_declined.php';
                $donations = $declinedDonations ?? [];
                $pageTitle = "Declined/Deferred Donations";
                break;
            case 'all':
            default:
                // OPTIMIZATION: For 'all' status, load data progressively from all modules
                $allDonations = [];
                $moduleErrors = [];
                
                // Load ALL modules and aggregate results
                ob_start();
                include_once 'module/donation_pending.php';
                $pendingOutput = ob_get_clean();
                if (isset($pendingDonations) && is_array($pendingDonations) && !isset($pendingDonations['error']) && !empty($pendingDonations)) {
                    $allDonations = array_merge($allDonations, $pendingDonations);
                } elseif (isset($pendingDonations['error'])) {
                    $moduleErrors[] = 'Pending: ' . $pendingDonations['error'];
                }
                
                ob_start();
                include_once 'module/donation_approved.php';
                $approvedOutput = ob_get_clean();
                if (isset($approvedDonations) && is_array($approvedDonations) && !isset($approvedDonations['error']) && !empty($approvedDonations)) {
                    $allDonations = array_merge($allDonations, $approvedDonations);
                } elseif (isset($approvedDonations['error'])) {
                    $moduleErrors[] = 'Approved: ' . $approvedDonations['error'];
                }
                
                ob_start();
                include_once 'module/donation_declined.php';
                $declinedOutput = ob_get_clean();
                if (isset($declinedDonations) && is_array($declinedDonations) && !isset($declinedDonations['error']) && !empty($declinedDonations)) {
                    $allDonations = array_merge($allDonations, $declinedDonations);
                } elseif (isset($declinedDonations['error'])) {
                    $moduleErrors[] = 'Declined: ' . $declinedDonations['error'];
                }
                
                // Sort all donations by date (newest first)
                if (count($allDonations) > 0) {
                    usort($allDonations, function($a, $b) {
                        $dateA = $a['date_submitted'] ?? $a['rejection_date'] ?? '';
                        $dateB = $b['date_submitted'] ?? $b['rejection_date'] ?? '';
                        if (empty($dateA) && empty($dateB)) return 0;
                        if (empty($dateA)) return 1;
                        if (empty($dateB)) return -1;
                        return strtotime($dateB) - strtotime($dateA);
                    });
                }
                
                $donations = $allDonations;
                $pageTitle = "All Donors";
                break;
            }
        }
        // Restore original status filter in case included modules overwrote $status
        $status = $statusFilter;
        // Cache data will be prepared after pagination calculations
    }
} catch (Exception $e) {
    $error = "Error loading module: " . $e->getMessage();
}
// Check if there's an error in fetching data
if (!$error && isset($donations['error'])) {
    $error = $donations['error'];
}
// Ensure $donations is always an array
if (!is_array($donations)) {
    $donations = [];
    if (!$error) {
        $error = "No data returned or invalid data format";
    }
}
// Data is ordered by created_at.desc in the API query to implement Last In, First Out (LIFO) order
// This ensures newest entries appear at the top of the table on the first page
// OPTIMIZATION: Add performance monitoring
$startTime = microtime(true);
// Derive pagination display variables - same logic for all status modes
$totalItems = count($donations);
$totalPages = ceil($totalItems / $itemsPerPage);
if ($currentPage > $totalPages && $totalPages > 0) { $currentPage = $totalPages; }
$startIndex = ($currentPage - 1) * $itemsPerPage;
$currentPageDonations = array_slice($donations, $startIndex, $itemsPerPage);

// OPTIMIZATION: Enhanced cache writing with compression and multi-layer storage (after pagination)
if (!$useCache && $status !== 'pending') {
    // Prepare comprehensive cache data (like main dashboard)
    $cacheData = [
        'data' => $donations,
        'timestamp' => time(),
        'status' => $statusKey,
        'page' => isset($_GET['page']) ? intval($_GET['page']) : 1,
        'count' => count($donations),
        'totalItems' => $totalItems,
        'totalPages' => $totalPages,
        'itemsPerPage' => $itemsPerPage,
        'version' => $cacheConfig['version'],
        'data_version' => $cacheConfig['data_version'],
        'filters' => $filtersForKey,
        'executionTime' => 0 // Will be updated after performance monitoring
    ];
    // Layer 1: Store in memory cache
    $_SESSION[$memoryCacheKey] = $cacheData;
    // Layer 2: Store in compressed file cache
    $jsonData = json_encode($cacheData);
    if ($cacheConfig['compression'] && function_exists('gzencode')) {
        $compressedData = gzencode($jsonData, 6); // Compression level 6 (good balance)
        @file_put_contents($cacheFile, $compressedData);
    } else {
        @file_put_contents($cacheFile, $jsonData);
    }
    // Layer 3: Store in database cache (if available)
    if (function_exists('storeDatabaseCache')) {
        storeDatabaseCache($cacheKey, $cacheData, $cacheConfig['db_ttl']);
    }
    // Cache warming: Pre-load related data (background process, non-blocking)
    if ($cacheConfig['warm_cache']) {
        // Use fastCGI/background process for cache warming to not slow down current request
        register_shutdown_function(function() use ($statusKey, $currentPage) {
            warmCache($statusKey);
            // Also pre-load other status filters for instant switching
            warmOtherFilters($statusKey, $currentPage);
        });
    }
}

// OPTIMIZATION: Enhanced performance monitoring (like main dashboard)
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

// Add comprehensive performance headers
header('X-Execution-Time: ' . $executionTime . 'ms');
header('X-Data-Count: ' . $totalItems);
header('X-Cache-Status: ' . ($useCache ? 'HIT' : 'MISS'));
header('X-Cache-Source: ' . $cacheSource);
header('X-Page-Info: ' . json_encode(['page' => $currentPage, 'totalPages' => $totalPages, 'itemsPerPage' => $itemsPerPage]));

// Cache performance metrics
$cacheStats = getCacheStats();
$cacheHitRate = $useCache ? 100 : 0;

// Enhanced logging with comprehensive metrics
error_log("PERFORMANCE - Donations Dashboard: {$executionTime}ms, {$totalItems} items, Page {$currentPage}/{$totalPages}, Cache: {$cacheSource}, Hit Rate: {$cacheHitRate}%");

// Add performance headers using optimized functions
addPerformanceHeaders($executionTime, $totalItems, "Donations - Page: {$currentPage}, Items: {$totalItems}, Cache: {$cacheSource}");
// Handle cache warming requests: skip rendering, just prime caches
if (isset($_GET['warm']) && $_GET['warm'] == '1') {
    if (!headers_sent()) {
        header('Cache-Primed: 1');
        http_response_code(204);
    }
    exit;
}

// OPTIMIZATION: Handle progressive loading requests
if (isset($_GET['progressive']) && $_GET['progressive'] == '1') {
    // Return skeleton HTML for progressive loading
    if (!headers_sent()) {
        header('Content-Type: text/html');
        header('X-Progressive-Load: 1');
    }
    echo generateSkeletonHTML();
    exit;
}

// OPTIMIZATION: Enhanced LCP optimization for pending filter
if ($status === 'pending' && $perfMode) {
    // Check for fast-load request (show skeleton immediately, load data via AJAX)
    if (isset($_GET['fast_load']) && $_GET['fast_load'] == '1') {
        if (!headers_sent()) {
            header('Content-Type: text/html');
            header('X-Fast-Load: 1');
            header('Cache-Control: public, max-age=60, stale-while-revalidate=30');
        }
        echo generateSkeletonHTML();
        exit;
    }
    
    // Pre-warm pending filter cache in background for faster subsequent loads
    if (!isset($_GET['no_warm'])) {
        register_shutdown_function(function() {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $path = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : $_SERVER['PHP_SELF'];
            
            $warmUrl = $scheme . '://' . $host . $path . '?status=pending&perf_mode=on&warm=1';
            
            // Fire-and-forget cache warming
            $ch = curl_init($warmUrl);
            curl_setopt($ch, CURLOPT_NOSIGNAL, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            @curl_exec($ch);
            @curl_close($ch);
        });
    }
}
// Add cache headers for browser caching and ETag for conditional requests
if (!headers_sent()) {
    if ($useCache) {
        // Cache hit - set longer cache headers
        header('Cache-Control: public, max-age=300, stale-while-revalidate=60');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 300));
        header('X-Cache-Status: HIT');
        header('X-Cache-Source: ' . $cacheSource);
    } else {
        // Cache miss - set shorter cache headers
        header('Cache-Control: public, max-age=60, stale-while-revalidate=30');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 60));
        header('X-Cache-Status: MISS');
    }
    // Add performance headers
    header('X-Response-Time: ' . $executionTime . 'ms');
    header('X-Total-Items: ' . $totalItems);
    header('X-Cache-Entries: ' . $cacheStats['file_entries']);
    // ETag to help slow connections avoid full reload when unchanged
    $etag = 'W/"' . md5($statusFilter . '|' . $currentPage . '|' . json_encode(array_column($currentPageDonations ?? [], 'eligibility_id'))) . '"';
    header('ETag: ' . $etag);
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }
}
// OPTIMIZATION: calculateAge() function is now in individual modules for independence
// This ensures modules work regardless of how they're included
// OPTIMIZATION: Helper functions are now in individual modules for independence
// This ensures modules work regardless of how they're included
// OPTIMIZATION: Enhanced caching utility functions
function storeDatabaseCache($key, $data, $ttl) {
    // Store cache in database for long-term persistence
    // This would require a cache table in the database
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=redcross_cache", "username", "password");
        $stmt = $pdo->prepare("INSERT INTO cache_store (cache_key, cache_data, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = VALUES(expires_at)");
        $stmt->execute([$key, json_encode($data), time() + $ttl]);
    } catch (Exception $e) {
        // Silently fail if database cache is not available
        error_log("Database cache unavailable: " . $e->getMessage());
    }
}
function getDatabaseCache($key) {
    // Retrieve cache from database
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=redcross_cache", "username", "password");
        $stmt = $pdo->prepare("SELECT cache_data FROM cache_store WHERE cache_key = ? AND expires_at > ?");
        $stmt->execute([$key, time()]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? json_decode($result['cache_data'], true) : null;
    } catch (Exception $e) {
        return null;
    }
}
function warmCache($statusKey) {
    // Pre-load related cache data for better performance (fire-and-forget self requests)
    $currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $pagesToWarm = [];
    if ($currentPage > 1) { $pagesToWarm[] = $currentPage - 1; }
    $pagesToWarm[] = $currentPage + 1;
    // Build base URL
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $path = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : $_SERVER['PHP_SELF'];
    // Preserve filters except page
    $qs = isset($_GET) && is_array($_GET) ? $_GET : [];
    unset($qs['page']);
    foreach ($pagesToWarm as $p) {
        $params = $qs;
        $params['status'] = $statusKey;
        $params['page'] = max(1, (int)$p);
        $params['warm'] = 1;
        $url = $scheme . '://' . $host . $path . '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        @curl_exec($ch);
        @curl_close($ch);
    }
}

function warmOtherFilters($currentStatus, $currentPage) {
    // Pre-load other status filters (page 1) for instant switching
    $otherFilters = ['all', 'pending', 'approved', 'declined'];
    $targetPage = min($currentPage, 1); // Only warm page 1 for other filters
    
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $path = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : $_SERVER['PHP_SELF'];
    
    foreach ($otherFilters as $filter) {
        if ($filter === $currentStatus) continue; // Skip current filter
        
        $params = ['status' => $filter, 'page' => $targetPage, 'warm' => 1];
        $url = $scheme . '://' . $host . $path . '?' . http_build_query($params);
        
        // Fire-and-forget request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        @curl_exec($ch);
        @curl_close($ch);
    }
    
    error_log("CACHE: Pre-warmed other filters for instant switching");
}
function invalidateCache($pattern = null) {
    // Invalidate cache entries matching pattern
    // Use a small project-local cache directory to avoid scanning large system temp dirs
    $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    $files = glob($cacheDir . DIRECTORY_SEPARATOR . 'donations_list_*.json.gz');
    foreach ($files as $file) {
        if ($pattern === null || strpos(basename($file), $pattern) !== false) {
            @unlink($file);
        }
    }
    // Clear memory cache
    if (session_status() === PHP_SESSION_ACTIVE) {
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'donor_cache_') === 0) {
                unset($_SESSION[$key]);
            }
        }
    }
}
function getCacheStats() {
    // Get cache statistics for monitoring
    $stats = [
        'memory_entries' => 0,
        'file_entries' => 0,
        'total_size' => 0,
        'hit_rate' => 0
    ];
    // Count memory cache entries
    if (session_status() === PHP_SESSION_ACTIVE) {
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'donor_cache_') === 0) {
                $stats['memory_entries']++;
            }
        }
    }
    // Count file cache entries
    // Use the same local cache directory as invalidateCache
    $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    // Limit glob cost; some environments have very slow temp dirs
    $files = glob($cacheDir . DIRECTORY_SEPARATOR . 'donations_list_*.json.gz', GLOB_NOSORT) ?: [];
    // Cap counting to a reasonable number to avoid timeouts
    if (is_array($files) && count($files) > 1000) {
        $files = array_slice($files, 0, 1000);
    }
    // Avoid expensive per-file stat calls on each request
    $stats['file_entries'] = is_array($files) ? count($files) : 0;
    return $stats;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Red Cross Blood Donor Management System - Admin Dashboard">
    <title>Dashboard</title>
    <!-- LCP OPTIMIZATION: Resource hints for faster DNS and connection -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <!-- Preload critical resources -->
    <link rel="preload" href="../../assets/image/PRC_Logo.png" as="image" fetchpriority="high">
    <!-- Immediate script to prevent loading text flash on hard refresh -->
    <script>
        // Run immediately - this executes as soon as the script tag is parsed
        (function() {
            // Function to hide loading indicators
            function hideLoadingIndicators() {
                const loadingIndicator = document.getElementById('loadingIndicator');
                if (loadingIndicator) {
                    loadingIndicator.style.display = 'none !important';
                    loadingIndicator.style.visibility = 'hidden';
                }
                
                // Clear modal content that might show loading text
                const modalContents = [
                    'medicalHistoryModalContent',
                    'medicalHistoryModalAdminContent',
                    'medicalHistoryApprovalContent'
                ];
                
                modalContents.forEach(contentId => {
                    const content = document.getElementById(contentId);
                    if (content) {
                        content.innerHTML = '';
                    }
                });
            }
            
            // Run immediately if DOM is already loaded
            if (document.readyState !== 'loading') {
                hideLoadingIndicators();
            }
            
            // Also run on DOMContentLoaded
            document.addEventListener('DOMContentLoaded', hideLoadingIndicators);
            
            // Do not run on window load nor on an interval; this was hiding dynamic content
        })();
    </script>
    <!-- LCP OPTIMIZATION: Critical CSS inlined for above-the-fold content -->
    <style>
        /* Critical above-the-fold styles - inlined for instant rendering */
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f8f9fa; }
        .dashboard-home-header { background-color: #f8f9fa; padding: 15px; border-bottom: 1px solid #dee2e6; position: sticky; top: 0; z-index: 1000; }
        .dashboard-home-sidebar { background-color: #fff; border-right: 1px solid #dee2e6; min-height: 100vh; }
        .container-fluid { padding: 0; }
        .card-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem; }
        .card-subtitle { font-size: 1.125rem; color: #6c757d; }
        .btn { display: inline-block; padding: 0.375rem 0.75rem; border-radius: 0.25rem; text-decoration: none; cursor: pointer; }
        .btn-danger { background-color: #dc3545; color: #fff; border: 1px solid #dc3545; }
        .btn-primary { background-color: #0d6efd; color: #fff; border: 1px solid #0d6efd; }
        .spinner-border { display: inline-block; width: 2rem; height: 2rem; border: 0.25em solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spinner-border 0.75s linear infinite; }
        @keyframes spinner-border { to { transform: rotate(360deg); } }
        /* Reduce work on first paint by skipping below-the-fold rendering */
        .table-responsive, #donationsTable { content-visibility: auto; contain-intrinsic-size: 800px 1200px; }
        /* Hide heavy sections until ready to avoid staggered row paints */
        .progressive-hide { visibility: hidden; }
        /* Font display optimization */
        @font-face { font-family: 'Font Awesome 6 Free'; font-display: swap; }
        /* Performance optimizations */
        * { box-sizing: border-box; }
        .dashboard-home-header, .dashboard-home-sidebar { will-change: transform; }
        /* Reduce repaints */
        .table-responsive { contain: layout style paint; }
    </style>
    
    <!-- Bootstrap 5.3 CSS (non-blocking preload pattern) -->
    <link id="bootstrap-css" rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet';this.dataset.loaded='1'">
    <noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></noscript>
    
    <!-- FontAwesome for Icons (non-blocking preload pattern) -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    
    <!-- LCP OPTIMIZATION: Non-critical CSS files loaded asynchronously -->
    <script>
        // Async CSS loader for non-critical stylesheets - optimized for slow connections
        (function() {
            var cssFiles = [
                '../../assets/css/dashboard-inventory-admin-main.css',
                '../../assets/css/dashboard-inventory-admin-modals.css',
                '../../assets/css/dashboard-inventory-admin-workflow.css',
                '../../assets/css/dashboard-inventory-admin-forms.css',
                '../../assets/css/dashboard-inventory-admin-donor-details.css',
                '../../assets/css/medical-history-approval-modals.css',
                '../../assets/css/defer-donor-modal.css',
                '../../assets/css/admin-screening-form-modal.css',
                '../../assets/css/enhanced-modal-styles.css',
                '../../assets/css/dashboard-inventory-admin-loading.css',
                '../../assets/css/dashboard-inventory-admin-print.css'
            ];
            
            function loadCSS(href) {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                link.media = 'print';
                link.onload = function() { this.media = 'all'; };
                link.onerror = function() { /* Silently fail for offline/slow connections */ };
                document.head.appendChild(link);
            }
            
            // Use requestIdleCallback for better performance, fallback to setTimeout
            var loadNonCriticalCSS = function() {
                // Load CSS files one by one to avoid overwhelming slow connections
                var index = 0;
                function loadNext() {
                    if (index < cssFiles.length) {
                        loadCSS(cssFiles[index]);
                        index++;
                        // Small delay between loads for slow connections
                        setTimeout(loadNext, 50);
                    }
                }
                loadNext();
            };
            
            if (window.requestIdleCallback) {
                requestIdleCallback(loadNonCriticalCSS, { timeout: 2000 });
            } else {
                // Fallback: Load after DOMContentLoaded with delay for slow connections
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(loadNonCriticalCSS, 200);
                    });
                } else {
                    setTimeout(loadNonCriticalCSS, 200);
                }
            }
        })();
    </script>

    <script>
    (function() {
        function reveal() {
            var els = document.querySelectorAll('.progressive-hide');
            for (var i = 0; i < els.length; i++) {
                els[i].style.visibility = 'visible';
                els[i].classList.remove('progressive-hide');
            }
        }
        function onReady() {
            var css = document.getElementById('bootstrap-css');
            if (css && !css.dataset.loaded) {
                css.addEventListener('load', reveal, { once: true });
                setTimeout(reveal, 1500);
            } else {
                reveal();
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', onReady);
        } else {
            onReady();
        }
    })();
    </script>
</head>
<body>
    <!-- Admin Donor Registration Modal (replaces old confirmation modal) -->
    <?php include '../../src/views/modals/admin-donor-registration-modal.php'; ?>
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
                <button type="button" class="btn btn-danger" onclick="showConfirmationModal()">
                    <i class="fas fa-plus me-2"></i>Register Donor
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <div class="sidebar-main-content">
                    <div class="d-flex align-items-center ps-1 mb-3 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" width="65" height="65" style="width: 65px; height: 65px; object-fit: contain;" fetchpriority="high" decoding="async">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                    <ul class="nav flex-column">
                        <a href="dashboard-Inventory-System.php" class="nav-link">
                            <span><i class="fas fa-home"></i>Home</span>
                        </a>
                        <a href="dashboard-Inventory-System-list-of-donations.php" class="nav-link active">
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
            <div class="container-fluid p-4 custom-margin">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="card-title mb-1">Welcome, Admin!</h2>
                                <h4 class="card-subtitle text-muted mb-0">Donor Management</h4>
                                <?php if ($status && $status !== 'all'): ?>
                                <p class="text-muted mb-0 mt-1">
                                    <i class="fas fa-filter me-1"></i>
                                    Showing: <strong><?php echo ($status === 'deferred') ? 'Declined/Deferred' : ucfirst($status); ?> Donors</strong>
                                </p>
                                <?php else: ?>
                                <p class="text-muted mb-0 mt-1">
                                    <i class="fas fa-users me-1"></i>
                                    Showing: <strong>All Donors</strong>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('l, F j, Y'); ?>
                                </small>
                            </div>
                        </div>
                        <!-- Status Filter Tabs -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2 mb-3 status-filter-buttons">
                                    <a href="dashboard-Inventory-System-list-of-donations.php?status=all"
                                       class="btn <?php echo ($status === 'all' || !$status) ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm">
                                        <i class="fas fa-list me-1"></i>All Donors
                                    </a>
                                    <a href="dashboard-Inventory-System-list-of-donations.php?status=pending"
                                       class="btn <?php echo $status === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?> btn-sm">
                                        <i class="fas fa-clock me-1"></i>Pending
                                    </a>
                                    <a href="dashboard-Inventory-System-list-of-donations.php?status=approved"
                                       class="btn <?php echo $status === 'approved' ? 'btn-success' : 'btn-outline-success'; ?> btn-sm">
                                        <i class="fas fa-check me-1"></i>Approved
                                    </a>
                                    <a href="dashboard-Inventory-System-list-of-donations.php?status=declined"
                                       class="btn <?php echo ($status === 'declined' || $status === 'deferred') ? 'btn-danger' : 'btn-outline-danger'; ?> btn-sm">
                                        <i class="fas fa-times me-1"></i>Declined/Deferred
                                    </a>
                                </div>
                                <div class="search-container">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <select class="form-select category-select" id="searchCategory" style="max-width: 150px;">
                                            <option value="all">All Fields</option>
                                            <option value="donor">Donor Name</option>
                                            <option value="donor_number">Donor Number</option>
                                            <option value="donor_type">Donor Type</option>
                                            <option value="registered_via">Registered Via</option>
                                            <option value="status">Status</option>
                                        </select>
                                        <div class="position-relative" style="flex: 1;">
                                            <input type="text"
                                                class="form-control"
                                                id="searchInput"
                                                placeholder="Search donors...">
                                            <div id="searchLoading" class="position-absolute" style="right:10px; top:50%; transform: translateY(-50%); display:none; z-index:10;">
                                                <div class="spinner-border spinner-border-sm text-secondary" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="searchInfo" class="mt-2 small text-muted" style="min-height: 20px;"></div>
                                </div>
                            </div>
                        </div>
                        <?php if ($error): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No pending donations found. New donor submissions will appear here.
                        </div>
                        <?php endif; ?>
                        <!-- OPTIMIZATION: Loading indicator for slow connections -->
                        <div id="loadingIndicator" class="text-center py-4" style="display: none !important;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading approved donations...</p>
                        </div>
                        <?php if (isset($_GET['processed']) && $_GET['processed'] === 'true'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php if ($status === 'approved'): ?>
                                Donor has been successfully processed and moved to the approved list.
                            <?php elseif ($status === 'declined'): ?>
                                Donor has been marked as declined and moved to the declined list.
                            <?php else: ?>
                                Donor has been processed successfully.
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($_GET['donor_registered']) && $_GET['donor_registered'] === 'true'): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            Donor has been successfully registered and the declaration form has been completed.
                            
                            <?php if (isset($_COOKIE['mobile_account_generated']) && $_COOKIE['mobile_account_generated'] === 'true'): ?>
                            <div class="mt-3 p-3 bg-light border rounded">
                                <h6 class="text-primary mb-2">
                                    <i class="fas fa-mobile-alt me-2"></i>Mobile App Account Generated
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Email:</strong> 
                                        <span class="text-muted"><?php echo htmlspecialchars($_COOKIE['mobile_email'] ?? ''); ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Password:</strong> 
                                        <span class="text-muted" id="mobilePassword"><?php echo htmlspecialchars($_COOKIE['mobile_password'] ?? ''); ?></span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="copyPassword()">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Share these credentials with the donor for mobile app access.
                                    </small>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        <!-- Divider Line -->
                <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
                        <!-- Donor Management Table -->
                        <?php if (!empty($donations)): ?>
						<style>
							.sortable .sort-trigger { display: inline-flex; align-items: center; gap: .35rem; background: none; border: none; color: inherit; padding: 0; font: inherit; cursor: pointer; }
							.sort-icon { font-size: .75rem; opacity: .8; }
							th[aria-sort="ascending"] .sort-icon, th[aria-sort="descending"] .sort-icon { opacity: 1; }
						</style>
                        <div class="table-responsive progressive-hide">
                            <table class="table table-striped table-hover" id="donationsTable">
                                <thead class="table-dark">
                                    <tr>
										<th class="sortable" data-sort-field="donor_id" aria-sort="none">
											<button type="button" class="sort-trigger" aria-label="Sort by Donor Number">
												Donor Number<i class="fas fa-sort sort-icon"></i>
											</button>
										</th>
										<th class="sortable" data-sort-field="surname" aria-sort="none">
											<button type="button" class="sort-trigger" aria-label="Sort by Surname">
												Surname<i class="fas fa-sort sort-icon"></i>
											</button>
										</th>
										<th class="sortable" data-sort-field="first_name" aria-sort="none">
											<button type="button" class="sort-trigger" aria-label="Sort by First Name">
												First Name<i class="fas fa-sort sort-icon"></i>
											</button>
										</th>
										<th class="sortable" data-sort-field="donor_type" aria-sort="none">
											<button type="button" class="sort-trigger" aria-label="Sort by Donor Type">
												Donor Type<i class="fas fa-sort sort-icon"></i>
											</button>
										</th>
										<th class="sortable" data-sort-field="registered_via" aria-sort="none">
											<button type="button" class="sort-trigger" aria-label="Sort by Registered Via">
												Registered Via<i class="fas fa-sort sort-icon"></i>
											</button>
										</th>
										<th class="sortable" data-sort-field="status" aria-sort="none">
											<button type="button" class="sort-trigger" aria-label="Sort by Status">
												Status<i class="fas fa-sort sort-icon"></i>
											</button>
										</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
								<tbody id="donationsTableBody">
                                    <?php foreach ($currentPageDonations as $donation): ?>
                                    <tr class="donor-row" data-donor-id="<?php echo htmlspecialchars($donation['donor_id'] ?? ''); ?>" data-eligibility-id="<?php echo htmlspecialchars($donation['eligibility_id'] ?? ''); ?>">
                                        <td><?php echo htmlspecialchars($donation['donor_id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($donation['surname'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($donation['first_name'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($donation['donor_type'] ?? '') === 'Returning' ? 'info' : 'primary'; ?>">
                                                <?php echo htmlspecialchars($donation['donor_type'] ?? 'New'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $regChannel = $donation['registration_source'] ?? $donation['registration_channel'] ?? 'PRC Portal';
                                            // Display logic for admin side only
                                            if ($regChannel === 'PRC Portal') {
                                                echo 'PRC System';
                                            } elseif ($regChannel === 'Mobile') {
                                                echo 'Mobile System';
                                            } else {
                                                echo htmlspecialchars($regChannel);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $donation['status_text'] ?? 'Pending (Screening)';
                                            $statusClass = '';
                                            $displayStatus = $status;
                                            // OPTIMIZATION: Use pre-fetched status data instead of making individual API calls
                                            // This eliminates the N+1 query problem that was causing severe performance issues
                                            // Simple status class determination based on pre-fetched status_text
                                            if (strpos($status, 'Approved') !== false || strpos($status, 'eligible') !== false) {
                                                $statusClass = 'bg-success';
                                                $displayStatus = 'Approved';
                                            } elseif (strpos($status, 'Declined') !== false || strpos($status, 'refused') !== false) {
                                                $statusClass = 'bg-danger';
                                                $displayStatus = 'Declined';
                                            } elseif (strpos($status, 'Deferred') !== false || strpos($status, 'ineligible') !== false) {
                                                $statusClass = 'bg-warning';
                                                $displayStatus = 'Deferred';
                                            } elseif (strpos($status, 'Pending (Examination)') !== false || strpos($status, 'Physical Examination') !== false) {
                                                $statusClass = 'bg-info';
                                                $displayStatus = 'Pending (Examination)';
                                            } elseif (strpos($status, 'Pending (Collection)') !== false) {
                                                $statusClass = 'bg-primary';
                                                $displayStatus = 'Pending (Collection)';
                                            } else {
                                                $statusClass = 'bg-warning';
                                                $displayStatus = 'Pending (Screening)';
                                            }
                                            // Skip the complex API logic - data is already processed by modules
                                            if (false) {
                                                // PRIORITY CHECK: Look for ANY decline/deferral status anywhere in the workflow
                                                // Use the same logic as the donor information modal for consistency
                                                $donorId = $donation['donor_id'] ?? null;
                                                if ($donorId) {
                                                $hasDeclineDeferStatus = false;
                                                $declineDeferType = '';
                                                // 1. Check eligibility table first (most authoritative)
                                                // ROOT CAUSE FIX: Include blood_collection_id in query to verify complete process
                                                $eligibilityCurl = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donorId . '&select=status,blood_collection_id&order=created_at.desc&limit=1');
                                                curl_setopt($eligibilityCurl, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($eligibilityCurl, CURLOPT_HTTPHEADER, [
                                                    'apikey: ' . SUPABASE_API_KEY,
                                                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                    'Content-Type: application/json'
                                                ]);
                                                $eligibilityResponse = curl_exec($eligibilityCurl);
                                                $eligibilityHttpCode = curl_getinfo($eligibilityCurl, CURLINFO_HTTP_CODE);
                                                curl_close($eligibilityCurl);
                                                if ($eligibilityHttpCode === 200) {
                                                    $eligibilityData = json_decode($eligibilityResponse, true) ?: [];
                                                    if (!empty($eligibilityData)) {
                                                        $eligibilityStatus = strtolower($eligibilityData[0]['status'] ?? '');
                                                        $hasBloodCollectionId = !empty($eligibilityData[0]['blood_collection_id'] ?? null);
                                                        // Check for decline/deferral in eligibility table
                                                        if (in_array($eligibilityStatus, ['declined', 'refused'])) {
                                                            $hasDeclineDeferStatus = true;
                                                            $declineDeferType = 'Declined';
                                                        } elseif (in_array($eligibilityStatus, ['deferred', 'ineligible'])) {
                                                            $hasDeclineDeferStatus = true;
                                                            $declineDeferType = 'Deferred';
                                                        } elseif (($eligibilityStatus === 'approved' || $eligibilityStatus === 'eligible') && $hasBloodCollectionId) {
                                                            // ROOT CAUSE FIX: Only mark as Approved if eligibility status is approved/eligible AND blood_collection_id is set
                                                            $status = 'Approved';
                                                        }
                                                    }
                                                }
                                                // 2. Check screening form for decline status
                                                if (!$hasDeclineDeferStatus) {
                                                    $screeningCurl = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . $donorId . '&disapproval_reason=not.is.null&select=disapproval_reason&order=created_at.desc&limit=1');
                                                    curl_setopt($screeningCurl, CURLOPT_RETURNTRANSFER, true);
                                                    curl_setopt($screeningCurl, CURLOPT_HTTPHEADER, [
                                                        'apikey: ' . SUPABASE_API_KEY,
                                                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                        'Content-Type: application/json'
                                                    ]);
                                                    $screeningResponse = curl_exec($screeningCurl);
                                                    $screeningHttpCode = curl_getinfo($screeningCurl, CURLINFO_HTTP_CODE);
                                                    curl_close($screeningCurl);
                                                    if ($screeningHttpCode === 200) {
                                                        $screeningData = json_decode($screeningResponse, true) ?: [];
                                                        if (!empty($screeningData)) {
                                                            $hasDeclineDeferStatus = true;
                                                            $declineDeferType = 'Declined';
                                                        }
                                                    }
                                                }
                                                // 3. Check physical examination for deferral/decline status
                                                if (!$hasDeclineDeferStatus) {
                                                    $physicalCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donorId . '&or=(remarks.eq.Temporarily%20Deferred,remarks.eq.Permanently%20Deferred,remarks.eq.Declined,remarks.eq.Refused)&select=remarks&order=created_at.desc&limit=1');
                                                    curl_setopt($physicalCurl, CURLOPT_RETURNTRANSFER, true);
                                                    curl_setopt($physicalCurl, CURLOPT_HTTPHEADER, [
                                                        'apikey: ' . SUPABASE_API_KEY,
                                                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                        'Content-Type: application/json'
                                                    ]);
                                                    $physicalResponse = curl_exec($physicalCurl);
                                                    $physicalHttpCode = curl_getinfo($physicalCurl, CURLINFO_HTTP_CODE);
                                                    curl_close($physicalCurl);
                                                    if ($physicalHttpCode === 200) {
                                                        $physicalData = json_decode($physicalResponse, true) ?: [];
                                                        if (!empty($physicalData)) {
                                                            $remarks = $physicalData[0]['remarks'] ?? '';
                                                            if (in_array($remarks, ['Temporarily Deferred', 'Permanently Deferred'])) {
                                                                $hasDeclineDeferStatus = true;
                                                                $declineDeferType = 'Deferred';
                                                            } elseif (in_array($remarks, ['Declined', 'Refused'])) {
                                                                $hasDeclineDeferStatus = true;
                                                                $declineDeferType = 'Declined';
                                                            }
                                                        }
                                                    }
                                                }
                                                // 4. Check medical history for decline status
                                                if (!$hasDeclineDeferStatus) {
                                                    $medicalCurl = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donorId . '&medical_approval=eq.Not%20Approved&select=medical_approval&order=created_at.desc&limit=1');
                                                    curl_setopt($medicalCurl, CURLOPT_RETURNTRANSFER, true);
                                                    curl_setopt($medicalCurl, CURLOPT_HTTPHEADER, [
                                                        'apikey: ' . SUPABASE_API_KEY,
                                                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                        'Content-Type: application/json'
                                                    ]);
                                                    $medicalResponse = curl_exec($medicalCurl);
                                                    $medicalHttpCode = curl_getinfo($medicalCurl, CURLINFO_HTTP_CODE);
                                                    curl_close($medicalCurl);
                                                    if ($medicalHttpCode === 200) {
                                                        $medicalData = json_decode($medicalResponse, true) ?: [];
                                                        if (!empty($medicalData)) {
                                                            $hasDeclineDeferStatus = true;
                                                            $declineDeferType = 'Declined';
                                                        }
                                                    }
                                                }
                                                // If donor has ANY decline/deferral status, set it immediately
                                                if ($hasDeclineDeferStatus) {
                                                    $status = $declineDeferType;
                                                } else {
                                                    // If no decline/deferral status found, determine based on section completion
                                                    // Get donor workflow data
                                                    $medicalCurl = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donorId . '&select=medical_approval,needs_review&order=created_at.desc&limit=1');
                                                    curl_setopt($medicalCurl, CURLOPT_RETURNTRANSFER, true);
                                                    curl_setopt($medicalCurl, CURLOPT_HTTPHEADER, [
                                                        'apikey: ' . SUPABASE_API_KEY,
                                                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                        'Content-Type: application/json'
                                                    ]);
                                                    $medicalResponse = curl_exec($medicalCurl);
                                                    $medicalHttpCode = curl_getinfo($medicalCurl, CURLINFO_HTTP_CODE);
                                                    curl_close($medicalCurl);
                                                    $screeningCurl = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . $donorId . '&select=needs_review,disapproval_reason&order=created_at.desc&limit=1');
                                                    curl_setopt($screeningCurl, CURLOPT_RETURNTRANSFER, true);
                                                    curl_setopt($screeningCurl, CURLOPT_HTTPHEADER, [
                                                        'apikey: ' . SUPABASE_API_KEY,
                                                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                        'Content-Type: application/json'
                                                    ]);
                                                    $screeningResponse = curl_exec($screeningCurl);
                                                    $screeningHttpCode = curl_getinfo($screeningCurl, CURLINFO_HTTP_CODE);
                                                    curl_close($screeningCurl);
                                                    $physicalCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donorId . '&select=needs_review,remarks&order=created_at.desc&limit=1');
                                                    curl_setopt($physicalCurl, CURLOPT_RETURNTRANSFER, true);
                                                    curl_setopt($physicalCurl, CURLOPT_HTTPHEADER, [
                                                        'apikey: ' . SUPABASE_API_KEY,
                                                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                        'Content-Type: application/json'
                                                    ]);
                                                    $physicalResponse = curl_exec($physicalCurl);
                                                    $physicalHttpCode = curl_getinfo($physicalCurl, CURLINFO_HTTP_CODE);
                                                    curl_close($physicalCurl);
                                                    // PENDING STATUS DETERMINATION LOGIC
                                                    // Based on user specifications for 3 pending statuses
                                                    // 1. PENDING (SCREENING) - Donor needs to have a medical history record
                                                    $hasMedicalHistory = false;
                                                    // Check if Medical History record exists
                                                    if ($medicalHttpCode === 200) {
                                                        $medicalData = json_decode($medicalResponse, true) ?: [];
                                                        if (!empty($medicalData)) {
                                                            $hasMedicalHistory = true;
                                                        }
                                                    }
                                                    
                                                    // If no medical history record -> Pending (Screening)
                                                    if (!$hasMedicalHistory) {
                                                        $status = 'Pending (Screening)';
                                                    } else {
                                                        // 2. PENDING (EXAMINATION) - Need initial screening completed AND physical_examination exists but is empty/null (except ID)
                                                        $hasInitialScreening = false;
                                                        $hasPhysicalExamRecord = false;
                                                        $physicalExamIsEmpty = true;
                                                        
                                                        // Check if Initial Screening (screening_form) record exists
                                                        if ($screeningHttpCode === 200) {
                                                            $screeningData = json_decode($screeningResponse, true) ?: [];
                                                            if (!empty($screeningData)) {
                                                                $hasInitialScreening = true;
                                                            }
                                                        }
                                                        
                                                        // Check Physical Examination record and if it's empty/null (except ID)
                                                        // Need to fetch more fields to check if it's empty
                                                        $physicalExamDetailCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donorId . '&select=physical_exam_id,blood_pressure,pulse_rate,body_temp,gen_appearance,skin,heent,heart_and_lungs,remarks,needs_review&order=created_at.desc&limit=1');
                                                        curl_setopt($physicalExamDetailCurl, CURLOPT_RETURNTRANSFER, true);
                                                        curl_setopt($physicalExamDetailCurl, CURLOPT_HTTPHEADER, [
                                                            'apikey: ' . SUPABASE_API_KEY,
                                                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                            'Content-Type: application/json'
                                                        ]);
                                                        $physicalExamDetailResponse = curl_exec($physicalExamDetailCurl);
                                                        $physicalExamDetailHttpCode = curl_getinfo($physicalExamDetailCurl, CURLINFO_HTTP_CODE);
                                                        curl_close($physicalExamDetailCurl);
                                                        
                                                        if ($physicalExamDetailHttpCode === 200) {
                                                            $physicalExamDetailData = json_decode($physicalExamDetailResponse, true) ?: [];
                                                            if (!empty($physicalExamDetailData)) {
                                                                $hasPhysicalExamRecord = true;
                                                                $physicalExamRecord = $physicalExamDetailData[0];
                                                                
                                                                // Check if physical examination is empty (all fields except ID are null/empty)
                                                                $hasData = false;
                                                                $checkFields = ['blood_pressure', 'pulse_rate', 'body_temp', 'gen_appearance', 'skin', 'heent', 'heart_and_lungs', 'remarks'];
                                                                foreach ($checkFields as $field) {
                                                                    $value = $physicalExamRecord[$field] ?? null;
                                                                    if ($value !== null && $value !== '' && trim($value) !== '') {
                                                                        $hasData = true;
                                                                        break;
                                                                    }
                                                                }
                                                                $physicalExamIsEmpty = !$hasData;
                                                            }
                                                        }
                                                        
                                                        // Determine status based on requirements
                                                        if (!$hasInitialScreening) {
                                                            // No initial screening yet -> Pending (Screening)
                                                            $status = 'Pending (Screening)';
                                                        } elseif ($hasInitialScreening && (!$hasPhysicalExamRecord || $physicalExamIsEmpty)) {
                                                            // Initial screening done, but physical exam not done or empty -> Pending (Examination)
                                                            $status = 'Pending (Examination)';
                                                        } else {
                                                            // Physical examination is completed (has data) -> Check for blood collection
                                                            // 3. PENDING (COLLECTION) - Need blood_collection record AND physical_examination is completed
                                                            $physicalExamId = $physicalExamRecord['physical_exam_id'] ?? null;
                                                            
                                                            if ($physicalExamId) {
                                                                // Check blood collection using physical_exam_id
                                                                $collectionCurl = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?physical_exam_id=eq.' . $physicalExamId . '&select=blood_collection_id,is_successful,needs_review,status&order=created_at.desc&limit=1');
                                                                curl_setopt($collectionCurl, CURLOPT_RETURNTRANSFER, true);
                                                                curl_setopt($collectionCurl, CURLOPT_HTTPHEADER, [
                                                                    'apikey: ' . SUPABASE_API_KEY,
                                                                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                                    'Content-Type: application/json'
                                                                ]);
                                                                $collectionResponse = curl_exec($collectionCurl);
                                                                $collectionHttpCode = curl_getinfo($collectionCurl, CURLINFO_HTTP_CODE);
                                                                curl_close($collectionCurl);
                                                                
                                                                if ($collectionHttpCode === 200) {
                                                                    $collectionData = json_decode($collectionResponse, true) ?: [];
                                                                    if (!empty($collectionData)) {
                                                                        // Blood collection record exists
                                                                        $isSuccessful = $collectionData[0]['is_successful'] ?? false;
                                                                        $collNeeds = $collectionData[0]['needs_review'] ?? null;
                                                                        $collectionStatus = $collectionData[0]['status'] ?? '';
                                                                        
                                                                        // Check if collection is successful: either is_successful is true OR status is 'Successful'
                                                                        if ($isSuccessful === true || $isSuccessful === 'true' || $isSuccessful === 1) {
                                                                            // Blood collection is successful -> Approved
                                                                            $status = 'Approved';
                                                                        } elseif ($collNeeds !== true && !empty($collectionStatus) &&
                                                                            !in_array($collectionStatus, ['pending', 'Incomplete', 'Failed', 'Yet to be collected']) &&
                                                                            strtolower(trim($collectionStatus)) === 'successful') {
                                                                            // Status field indicates successful -> Approved
                                                                            $status = 'Approved';
                                                                        } else {
                                                                            // Blood collection record exists but not successful -> Pending (Collection)
                                                                            $status = 'Pending (Collection)';
                                                                        }
                                                                    } else {
                                                                        // Physical exam completed but no blood collection record -> Pending (Collection)
                                                                        $status = 'Pending (Collection)';
                                                                    }
                                                                } else {
                                                                    // Physical exam completed but no blood collection record -> Pending (Collection)
                                                                    $status = 'Pending (Collection)';
                                                                }
                                                            } else {
                                                                // Physical exam completed but no physical_exam_id -> Pending (Collection)
                                                                $status = 'Pending (Collection)';
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            } else {
                                                // For specific modules (approved, declined, pending), use the status from the module
                                                // The status is already correctly set by the module
                                            }
                                            // Status determination is now handled above in the optimized code
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($displayStatus); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            // Use the same status that was determined above (including eligibility table check)
                                            $normalizedStatus = $status;
                                            if (in_array($status, ['Pending (Examination)', 'Pending (Physical Examination)'])) {
                                                $normalizedStatus = 'Pending (Examination)';
                                            } elseif (in_array($status, ['Temporarily Deferred', 'Permanently Deferred', 'Deferred'])) {
                                                $normalizedStatus = 'Deferred';
                                            }
                                            // Determine if status is pending (shows Edit) or completed (shows View)
                                            $isPending = in_array($normalizedStatus, ['Pending (Screening)', 'Pending (Examination)', 'Pending (Collection)']);
                                            if ($isPending) {
                                                // Show edit button for pending statuses
                                                echo '<button type="button" class="btn btn-warning btn-sm edit-donor" data-donor-id="' . htmlspecialchars($donation['donor_id'] ?? '') . '" data-eligibility-id="' . htmlspecialchars($donation['eligibility_id'] ?? '') . '">';
                                                echo '<i class="fas fa-edit"></i>';
                                                echo '</button>';
                                            } else {
                                                // Show view button for completed statuses (Approved, Declined, Deferred)
                                                echo '<button type="button" class="btn btn-info btn-sm view-donor" data-donor-id="' . htmlspecialchars($donation['donor_id'] ?? '') . '" data-eligibility-id="' . htmlspecialchars($donation['eligibility_id'] ?? '') . '">';
                                                echo '<i class="fas fa-eye"></i>';
                                                echo '</button>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
						<script>
						(function(){
							const table = document.getElementById('donationsTable');
							const tbody = document.getElementById('donationsTableBody');
							if (!table || !tbody) return;
							
							const headers = table.querySelectorAll('thead th.sortable');
							
							headers.forEach((th, index) => {
								const trigger = th.querySelector('.sort-trigger');
								if (!trigger) return;
								
								trigger.addEventListener('click', () => {
									const current = th.getAttribute('aria-sort') || 'none';
									const next = current === 'none' ? 'ascending' : current === 'ascending' ? 'descending' : 'none';
									
									headers.forEach(h => {
										if (h !== th) {
											h.setAttribute('aria-sort', 'none');
											const ic = h.querySelector('.sort-icon');
											if (ic) ic.className = 'fas fa-sort sort-icon';
										}
									});
									
									th.setAttribute('aria-sort', next);
									const icon = trigger.querySelector('.sort-icon');
									if (icon) {
										icon.className = 'fas ' + (next === 'ascending' ? 'fa-sort-up' : next === 'descending' ? 'fa-sort-down' : 'fa-sort') + ' sort-icon';
									}
									
									const rows = Array.from(tbody.querySelectorAll('tr'));
									if (next === 'none') {
										return; // keep current order
									}
									
									const field = th.getAttribute('data-sort-field') || '';
									const type = (field === 'donor_id') ? 'number' : 'text';
									
									rows.sort((rowA, rowB) => {
										let a = rowA.cells[index]?.textContent.trim() || '';
										let b = rowB.cells[index]?.textContent.trim() || '';
										
										if (type === 'number') {
											const na = parseInt(a, 10) || 0;
											const nb = parseInt(b, 10) || 0;
											return next === 'ascending' ? (na - nb) : (nb - na);
										}
										a = a.toLowerCase();
										b = b.toLowerCase();
										return next === 'ascending' ? a.localeCompare(b) : b.localeCompare(a);
									});
									
									const frag = document.createDocumentFragment();
									rows.forEach(r => frag.appendChild(r));
									tbody.appendChild(frag);
								});
							});
						})();
						</script>
                        </div>
                        <?php if ($status === 'all' && (isset($_GET['perf_mode']) && $_GET['perf_mode'] === 'on')): ?>
                        <div id="allCursorNav" class="d-flex align-items-center gap-2 mb-3">
                            <button type="button" id="allPrevBtn" class="btn btn-outline-secondary btn-sm" disabled>Prev</button>
                            <button type="button" id="allNextBtn" class="btn btn-outline-secondary btn-sm">Next</button>
                            <small id="allInfo" class="text-muted ms-2"></small>
                        </div>
                        <script>
                        (function(){
                            const tableBody = document.querySelector('#donationsTable tbody');
                            const prevBtn = document.getElementById('allPrevBtn');
                            const nextBtn = document.getElementById('allNextBtn');
                            const infoEl = document.getElementById('allInfo');
                            const apiBase = '../api/load-donations-data.php?status=all&perf_mode=on';
                            let cursors = { approved:{prev:null,next:null}, declined:{prev:null,next:null}, pending:{prev:null,next:null} };

                            async function fetchAll(dir){
                                // dir: 'next' | 'prev'
                                let url = apiBase;
                                const params = new URLSearchParams();
                                if (dir === 'next') {
                                    if (cursors.approved.next) {
                                        params.set('approved_cursor_ts', cursors.approved.next.cursor_ts || '');
                                        params.set('approved_cursor_id', cursors.approved.next.cursor_id || '');
                                        params.set('approved_cursor_dir', 'next');
                                    }
                                    if (cursors.declined.next) {
                                        params.set('declined_cursor_ts', cursors.declined.next.cursor_ts || '');
                                        params.set('declined_cursor_id', cursors.declined.next.cursor_id || '');
                                        params.set('declined_cursor_dir', 'next');
                                    }
                                    if (cursors.pending.next) {
                                        params.set('pending_cursor_ts', cursors.pending.next.cursor_ts || '');
                                        params.set('pending_cursor_id', cursors.pending.next.cursor_id || '');
                                        params.set('pending_cursor_dir', 'next');
                                    }
                                } else if (dir === 'prev') {
                                    if (cursors.approved.prev) {
                                        params.set('approved_cursor_ts', cursors.approved.prev.cursor_ts || '');
                                        params.set('approved_cursor_id', cursors.approved.prev.cursor_id || '');
                                        params.set('approved_cursor_dir', 'prev');
                                    }
                                    if (cursors.declined.prev) {
                                        params.set('declined_cursor_ts', cursors.declined.prev.cursor_ts || '');
                                        params.set('declined_cursor_id', cursors.declined.prev.cursor_id || '');
                                        params.set('declined_cursor_dir', 'prev');
                                    }
                                    if (cursors.pending.prev) {
                                        params.set('pending_cursor_ts', cursors.pending.prev.cursor_ts || '');
                                        params.set('pending_cursor_id', cursors.pending.prev.cursor_id || '');
                                        params.set('pending_cursor_dir', 'prev');
                                    }
                                }
                                if ([...params.keys()].length) {
                                    url += '&' + params.toString();
                                }
                                prevBtn.disabled = true; nextBtn.disabled = true; infoEl.textContent = 'Loading...';
                                const res = await fetch(url, { headers: { 'Accept':'application/json' }});
                                const json = await res.json();
                                const rows = Array.isArray(json.data) ? json.data : [];
                                // Update cursors
                                if (json.streams) {
                                    cursors.approved.prev = json.streams.approved ? json.streams.approved.prev : null;
                                    cursors.approved.next = json.streams.approved ? json.streams.approved.next : null;
                                    cursors.declined.prev = json.streams.declined ? json.streams.declined.prev : null;
                                    cursors.declined.next = json.streams.declined ? json.streams.declined.next : null;
                                    cursors.pending.prev = json.streams.pending ? json.streams.pending.prev : null;
                                    cursors.pending.next = json.streams.pending ? json.streams.pending.next : null;
                                }
                                // Render
                                const html = rows.map(d => {
                                    const donorId = (d.donor_id || '');
                                    const eligId = (d.eligibility_id || '');
                                    const donorType = (d.donor_type || 'New');
                                    const reg = (d.registration_source || d.registration_channel || 'PRC System');
                                    const statusText = (d.status_text || 'Pending (Screening)');
                                    const statusClass = (d.status_class || 'bg-warning');
                                    return `<tr class="donor-row" data-donor-id="${String(donorId)}" data-eligibility-id="${String(eligId)}">
                                        <td>${String(donorId)}</td>
                                        <td>${(d.surname||'')}</td>
                                        <td>${(d.first_name||'')}</td>
                                        <td><span class="badge ${donorType==='Returning'?'bg-info':'bg-primary'}">${donorType}</span></td>
                                        <td>${reg==='PRC Portal'?'PRC System':(reg==='Mobile'?'Mobile System':reg)}</td>
                                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                                        <td><button type="button" class="btn btn-sm ${statusText.startsWith('Pending')?'btn-warning':'btn-info'}">${statusText.startsWith('Pending')?'Edit':'View'}</button></td>
                                    </tr>`;
                                }).join('');
                                tableBody.innerHTML = html;
                                // Enable/disable nav
                                prevBtn.disabled = !(cursors.approved.prev || cursors.declined.prev || cursors.pending.prev);
                                nextBtn.disabled = !(cursors.approved.next || cursors.declined.next || cursors.pending.next);
                                infoEl.textContent = `Loaded ${rows.length} records`;
                            }

                            // Initialize by fetching to acquire cursors
                            fetchAll('next').catch(()=>{ infoEl.textContent='Failed to load'; });
                            prevBtn.addEventListener('click', function(){ fetchAll('prev'); });
                            nextBtn.addEventListener('click', function(){ fetchAll('next'); });
                        })();
                        </script>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No donors found. New donor submissions will appear here.
                        </div>
                        <?php endif; ?>
                        <!-- Pagination Controls -->
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-center">
                        <nav aria-label="Page navigation">
                                <?php
                                // Build query-string preserving all current filters except page
                                $qs = isset($_GET) && is_array($_GET) ? $_GET : [];
                                // Ensure status reflects the preserved filter
                                $qs['status'] = $statusFilter;
                                unset($qs['page']);
                                $baseQs = http_build_query($qs);
                                $makePageUrl = function ($page) use ($baseQs) {
                                    $page = max(1, (int)$page);
                                    return '?' . ($baseQs ? $baseQs . '&' : '') . 'page=' . $page;
                                };
                                ?>
                                <ul class="pagination">
                                    <!-- Previous button -->
                                    <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl(max(1, $currentPage - 1))); ?>" aria-label="Previous">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    <!-- Page numbers -->
                                    <?php
                                    // Show up to 4 page numbers around current page
                                    $startPage = max(1, $currentPage - 1);
                                    $endPage = min($totalPages, $currentPage + 2);
                                    // If we're near the beginning, show more pages at the end
                                    if ($currentPage <= 2) {
                                        $endPage = min($totalPages, 4);
                                    }
                                    // If we're near the end, show more pages at the beginning
                                    if ($currentPage >= $totalPages - 1) {
                                        $startPage = max(1, $totalPages - 3);
                                    }
                                    // Show first page if not in range
                                    if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl(1)); ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl($i)); ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <!-- Show last page if not in range -->
                                    <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl($totalPages)); ?>"><?php echo $totalPages; ?></a>
                                    </li>
                                    <?php endif; ?>
                                    <!-- Next button -->
                                    <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars($makePageUrl(min($totalPages, $currentPage + 1))); ?> " aria-label="Next">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                            </ul>
                        </nav>
                        </div>
                        <?php endif; ?>
                        <!-- Showing entries information -->
                        <div class="entries-info">
                            <p>
                                Showing <?php echo count($currentPageDonations); ?> of <?php echo $totalItems; ?> entries
                                <?php if ($totalPages > 1): ?>
                                (Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>)
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
<!-- Donor Details Modal (Legacy) -->
<div class="modal fade" id="donorModal" tabindex="-1" aria-labelledby="donorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header modern-header">
                <h4 class="modal-title w-100"><i class="fas fa-user me-2"></i></h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;"></button>
            </div>
            <!-- Modal Body -->
            <div class="modal-body">
                <div id="donorDetails">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading donor information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Admin Defer Donor Modal -->
<div class="modal fade" id="adminDeferDonorModal" tabindex="-1" aria-labelledby="adminDeferDonorModalLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="adminDeferDonorModalLabel">
                    <i class="fas fa-ban me-2"></i>
                    Defer Donor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <form id="adminDeferDonorForm">
                    <input type="hidden" id="admin-defer-donor-id" name="donor_id">
                    <input type="hidden" id="admin-defer-eligibility-id" name="eligibility_id">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Specify reason for deferral and duration.</label>
                    </div>
                    <!-- Deferral Type Selection -->
                    <div class="mb-4">
                        <label for="adminDeferralTypeSelect" class="form-label fw-semibold">Deferral Type *</label>
                        <select class="form-select" id="adminDeferralTypeSelect" name="deferral_type" required>
                            <option value="">Select deferral type...</option>
                            <option value="Temporary Deferral">Temporary Deferral</option>
                            <option value="Permanent Deferral">Permanent Deferral</option>
                            <option value="Refuse">Refuse for this session</option>
                        </select>
                    </div>
                    <!-- Duration Section (for Temporary Deferral) -->
                    <div id="adminDurationSection" style="display: none;">
                        <label class="form-label fw-semibold">Duration *</label>
                        <div class="duration-options-grid mb-3">
                            <div class="duration-option" data-days="1">
                                <div class="duration-number">1</div>
                                <div class="duration-unit">Day</div>
                            </div>
                            <div class="duration-option" data-days="7">
                                <div class="duration-number">1</div>
                                <div class="duration-unit">Week</div>
                            </div>
                            <div class="duration-option" data-days="30">
                                <div class="duration-number">1</div>
                                <div class="duration-unit">Month</div>
                            </div>
                            <div class="duration-option" data-days="90">
                                <div class="duration-number">3</div>
                                <div class="duration-unit">Months</div>
                            </div>
                            <div class="duration-option" data-days="180">
                                <div class="duration-number">6</div>
                                <div class="duration-unit">Months</div>
                            </div>
                            <div class="duration-option" data-days="365">
                                <div class="duration-number">1</div>
                                <div class="duration-unit">Year</div>
                            </div>
                            <div class="duration-option" data-days="custom">
                                <div class="duration-number"><i class="fas fa-edit"></i></div>
                                <div class="duration-unit">Custom</div>
                            </div>
                        </div>
                        <input type="hidden" id="adminDeferralDuration" name="deferral_duration">
                        <!-- Custom Duration Section -->
                        <div id="adminCustomDurationSection" style="display: none;">
                            <label for="adminCustomDuration" class="form-label">Custom Duration (Days)</label>
                            <input type="number" class="form-control" id="adminCustomDuration" name="custom_duration" min="1" max="3650" placeholder="Enter number of days">
                        </div>
                    </div>
                    <!-- Reason for Deferral -->
                    <div class="mb-4">
                        <label for="adminDisapprovalReason" class="form-label fw-semibold">Reason for Deferral *</label>
                        <textarea class="form-control" id="adminDisapprovalReason" name="disapproval_reason" rows="4"
                                  placeholder="Please provide a detailed reason for the deferral..." required maxlength="200"></textarea>
                        <div class="form-text">
                            <span id="adminDeferCharCount">0/200 characters</span>
                        </div>
                        <div class="invalid-feedback" id="adminDeferReasonError">Please provide at least 10 characters.</div>
                        <div class="valid-feedback" id="adminDeferReasonSuccess">Reason looks good!</div>
                    </div>
                    <!-- Summary Section -->
                    <div id="adminDurationSummary" style="display: none;" class="alert alert-info">
                        <strong>Summary:</strong> <span id="adminSummaryText"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="adminSubmitDeferral" disabled>
                    <i class="fas fa-ban me-2"></i>Submit Deferral
                </button>
            </div>
        </div>
    </div>
</div>
<!-- New Donor Processing Confirmation Modal -->
<div class="modal fade" id="newDonorProcessingModal" tabindex="-1" aria-labelledby="newDonorProcessingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="newDonorProcessingModalLabel">
                    <i class="fas fa-user-plus me-2"></i>
                    Process New Donor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-clipboard-list text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Ready to Process New Donor?</h5>
                    <p class="text-muted mb-4">
                        This will start the medical history review process for the selected donor.
                        You will be guided through each step of the donor evaluation workflow.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Steps:</strong> Medical History Review → Initial Screening → Physician Review → Physical Examination → Blood Collection
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="proceedToMedicalHistoryBtn">
                    <i class="fas fa-arrow-right me-2"></i>Proceed to Medical History
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Medical History Completion Confirmation Modal -->
<div class="modal fade" id="medicalHistoryCompletionModal" tabindex="-1" aria-labelledby="medicalHistoryCompletionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryCompletionModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Medical History Completed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Medical History Review Complete</h5>
                    <p class="text-muted mb-4">
                        The medical history has been successfully submitted and will be marked as completed.
                    </p>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-end">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Confirm
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Approve Donor for Donation Confirmation Modal -->
<div class="modal fade" id="approveDonorForDonationModal" tabindex="-1" aria-labelledby="approveDonorForDonationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="approveDonorForDonationModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Approve Donor for Donation?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-heart text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Confirm this donor is fit to donate blood?</h5>
                    <p class="text-muted mb-4">This will mark the donor as medically cleared for donation.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-success px-4" id="confirmApproveDonorBtn">
                    <i class="fas fa-check me-2"></i>Approve
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Donor Approved Success Modal -->
<div class="modal fade" id="donorApprovedModal" tabindex="-1" aria-labelledby="donorApprovedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="donorApprovedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Accepted
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">The donor is medically cleared for donation.</h5>
                    <p class="text-muted mb-4">The donor can now proceed to the next step in the evaluation process.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Initial Screening Completed Confirmation Modal -->
<div class="modal fade" id="initialScreeningCompletedModal" tabindex="-1" aria-labelledby="initialScreeningCompletedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="initialScreeningCompletedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Initial Screening Completed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-clipboard-check text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Initial Screening Successfully Completed</h5>
                    <p class="text-muted mb-4">
                        The donor has passed the initial screening process and is ready to proceed to the next step.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Step:</strong> The donor will now proceed to physician review for medical history and physical examination.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal" id="proceedToPhysicianReviewBtn">
                    <i class="fas fa-arrow-right me-2"></i>Proceed to Physician Review
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Physician Medical History Review Modal -->
<div class="modal fade" id="physicianMedicalHistoryModal" tabindex="-1" aria-labelledby="physicianMedicalHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="physicianMedicalHistoryModalLabel">
                    <i class="fas fa-user-md me-2"></i>
                    Physician Medical History Review
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div id="physicianMedicalHistoryContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading medical history for physician review...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-danger px-4" id="physicianDeclineMedicalBtn" style="display: none;">
                    <i class="fas fa-times me-2"></i>Decline Medical History
                </button>
                <button type="button" class="btn btn-success px-4" id="physicianApproveMedicalBtn" style="display: none;">
                    <i class="fas fa-check me-2"></i>Approve Medical History
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Physician Physical Examination Confirmation Modal -->
<div class="modal fade" id="physicianPhysicalExamModal" tabindex="-1" aria-labelledby="physicianPhysicalExamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="physicianPhysicalExamModalLabel">
                    <i class="fas fa-stethoscope me-2"></i>
                    Proceed to Physical Examination
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-heartbeat text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Medical History Approved</h5>
                    <p class="text-muted mb-4">
                        The donor's medical history has been approved. You can now proceed to conduct the physical examination.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Step:</strong> Complete the physical examination form to assess the donor's physical fitness for donation.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="proceedToPhysicalExamBtn">
                    <i class="fas fa-arrow-right me-2"></i>Proceed to Physical Examination
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Physical Examination Completed Confirmation Modal -->
<div class="modal fade" id="physicalExamCompletedModal" tabindex="-1" aria-labelledby="physicalExamCompletedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="physicalExamCompletedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Physical Examination Completed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-stethoscope text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Physical Examination Successfully Completed</h5>
                    <p class="text-muted mb-4">
                        The donor has passed the physical examination and is ready to proceed to blood collection.
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Step:</strong> The donor will now proceed to the phlebotomist for blood collection.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal" id="proceedToBloodCollectionBtn">
                    <i class="fas fa-arrow-right me-2"></i>Proceed to Blood Collection
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Blood Collection Completed Confirmation Modal -->
<div class="modal fade" id="bloodCollectionCompletedModal" tabindex="-1" aria-labelledby="bloodCollectionCompletedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="bloodCollectionCompletedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Donation Completed Successfully
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-tint text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Donation completed successfully!</h5>
                    <p class="text-muted mb-4">
                        Blood has been added to Blood Bank.
                    </p>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Collection Complete:</strong> The blood collection has been successfully processed and added to the blood bank inventory.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal" id="viewDonorDetailsBtn">
                    <i class="fas fa-eye me-2"></i>View Donor Details
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Include Admin Screening Form Modal -->
<?php include '../../src/views/forms/admin_donor_initial_screening_form_modal.php'; ?>
<!-- Medical History Modal (Staff Style) -->
<div class="medical-history-modal" id="medicalHistoryModal">
    <div class="medical-modal-content">
        <div class="medical-modal-header">
            <h3><i class="fas fa-file-medical me-2"></i>Medical History Review & Approval</h3>
            <button type="button" class="medical-close-btn" onclick="closeMedicalHistoryModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="medical-modal-body">
            <div id="medicalHistoryModalContent">
                <!-- Content will be loaded dynamically -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading medical history...</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Removed forced hide script for medical history modal; visibility is controlled by JS show/hide functions -->
<!-- Blood Collection Modal is included below from shared staff modal to avoid duplication/conflicts -->
<!-- Edit Donor Modal -->
<div class="modal fade" id="editDonorForm" tabindex="-1" aria-labelledby="editDonorFormLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header bg-dark text-white">
                <h4 class="modal-title w-100"><i class="fas fa-edit me-2"></i> Edit Donor Details</h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Modal Body -->
            <div class="modal-body">
                <div id="editDonorFormContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading donor information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>
        </div>
    </div>
    <!-- LCP OPTIMIZATION: Bootstrap JS loaded with defer for non-blocking -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="../../assets/js/admin-feedback-modal.js" defer></script>
    <script>
        // Immediate cleanup to prevent loading text flash on page refresh
        (function() {
            // Hide loading indicator immediately
            const loadingIndicator = document.getElementById('loadingIndicator');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
            
            // Clear any modal content that might show loading text
            const modalContents = [
                'medicalHistoryModalContent',
                'medicalHistoryModalAdminContent',
                'medicalHistoryApprovalContent'
            ];
            
            modalContents.forEach(contentId => {
                const content = document.getElementById(contentId);
                if (content) {
                    content.innerHTML = '';
                }
            });
        })();
        
        // OPTIMIZATION: Enhanced progressive loading for donations dashboard with LCP focus
        let isLoading = false;
        let currentStatus = '<?php echo $status; ?>';
        let currentPage = <?php echo $currentPage; ?>;
        let isLCPOptimized = false;
        let paginationCache = new Map(); // Cache for pagination data
        
        // LCP Optimization: Load critical content first for pending filter
        function optimizeLCP() {
            if (currentStatus === 'pending' && !isLCPOptimized) {
                // Show skeleton immediately for better LCP
                const tableBody = document.querySelector('#donationsTable tbody');
                if (tableBody && tableBody.children.length === 0) {
                    tableBody.innerHTML = generateSkeletonRows(10);
                }
                
                // Load data progressively after LCP
                setTimeout(() => {
                    loadDonationsProgressive(currentStatus, currentPage);
                }, 100);
                
                isLCPOptimized = true;
            }
        }
        
        // OPTIMIZATION: Enhanced pagination with LCP focus
        function handlePaginationClick(event) {
            // If progressive container is not present, allow normal navigation
            const contentAreaExists = !!document.getElementById('donationsContent');
            if (!contentAreaExists) {
                return; // do not preventDefault; let anchor navigate
            }
            event.preventDefault();
            
            const link = event.target.closest('a');
            if (!link || link.classList.contains('disabled')) return;
            
            const href = link.getAttribute('href');
            const urlParams = new URLSearchParams(href.split('?')[1]);
            const targetPage = parseInt(urlParams.get('page')) || 1;
            const targetStatus = urlParams.get('status') || currentStatus;
            
            // Show skeleton immediately for better perceived performance
            showPaginationSkeleton();
            
            // Load page data progressively
            loadPageProgressive(targetStatus, targetPage);
        }
        
        // Show skeleton during pagination
        function showPaginationSkeleton() {
            const tableBody = document.querySelector('#donationsTable tbody');
            if (tableBody) {
                tableBody.classList.add('loading');
                tableBody.innerHTML = generateSkeletonRows(10);
            }
            
            // Disable pagination buttons during loading
            document.querySelectorAll('.pagination .page-link').forEach(btn => {
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.6';
            });
        }
        
        // Progressive page loading
        function loadPageProgressive(status, page) {
            if (isLoading) return;
            
            isLoading = true;
            
            // Check cache first
            const cacheKey = `${status}_${page}`;
            if (paginationCache.has(cacheKey)) {
                const cachedData = paginationCache.get(cacheKey);
                renderDonationsData(cachedData);
                updatePaginationState(status, page);
                isLoading = false;
                return;
            }
            
            // Load data via API
            fetch(`../../public/api/load-donations-data.php?status=${status}&page=${page}&perf_mode=on`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Cache the data
                        paginationCache.set(cacheKey, data);
                        
                        // Render data
                        renderDonationsData(data);
                        updatePaginationState(status, page);
                        
                        // Update URL without page reload
                        updateURL(status, page);
                    } else {
                        showError('Failed to load page data: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error loading page:', error);
                    showError('Network error: ' + error.message);
                })
                .finally(() => {
                    isLoading = false;
                    
                    // Re-enable pagination buttons
                    document.querySelectorAll('.pagination .page-link').forEach(btn => {
                        btn.style.pointerEvents = '';
                        btn.style.opacity = '';
                    });
                });
        }
        
        // Update pagination state
        function updatePaginationState(status, page) {
            currentStatus = status;
            currentPage = page;
            
            // Update pagination info
            const paginationInfo = document.getElementById('paginationInfo');
            if (paginationInfo) {
                paginationInfo.textContent = `Page ${page} of ${totalPages || '?'} (${totalItems || '?'} total items)`;
            }
            
            // Update active page in pagination
            document.querySelectorAll('.pagination .page-item').forEach(item => {
                item.classList.remove('active');
                const link = item.querySelector('.page-link');
                if (link) {
                    const href = link.getAttribute('href');
                    const urlParams = new URLSearchParams(href.split('?')[1]);
                    const linkPage = parseInt(urlParams.get('page')) || 1;
                    if (linkPage === page) {
                        item.classList.add('active');
                    }
                }
            });
        }
        
        // Update URL without page reload
        function updateURL(status, page) {
            const newUrl = `?status=${status}&page=${page}&perf_mode=on`;
            window.history.pushState({status, page}, '', newUrl);
        }
        
        // Initialize progressive pagination
        function initializeProgressivePagination() {
            // Add event listeners to pagination links
            document.querySelectorAll('.pagination .page-link').forEach(link => {
                link.addEventListener('click', handlePaginationClick);
            });
            
            // Handle browser back/forward navigation
            window.addEventListener('popstate', function(event) {
                if (event.state) {
                    const {status, page} = event.state;
                    loadPageProgressive(status, page);
                }
            });
            
            // Pre-load adjacent pages for faster navigation
            preloadAdjacentPages();
        }
        
        // Pre-load adjacent pages for faster navigation
        function preloadAdjacentPages() {
            const adjacentPages = [currentPage - 1, currentPage + 1].filter(p => p > 0);
            
            adjacentPages.forEach(page => {
                const cacheKey = `${currentStatus}_${page}`;
                if (!paginationCache.has(cacheKey)) {
                    // Pre-load in background
                    fetch(`../../public/api/load-donations-data.php?status=${currentStatus}&page=${page}&perf_mode=on`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                paginationCache.set(cacheKey, data);
                            }
                        })
                        .catch(error => {
                            console.log('Pre-load failed for page', page, error);
                        });
                }
            });
        }
        
        // Generate skeleton rows for better perceived performance
        function generateSkeletonRows(count) {
            let html = '';
            for (let i = 0; i < count; i++) {
                html += `
                    <tr class="skeleton-row">
                        <td><div class="skeleton-cell"></div></td>
                        <td><div class="skeleton-cell"></div></td>
                        <td><div class="skeleton-cell"></div></td>
                        <td><div class="skeleton-cell"></div></td>
                        <td><div class="skeleton-cell"></div></td>
                        <td><div class="skeleton-cell"></div></td>
                        <td><div class="skeleton-cell"></div></td>
                    </tr>
                `;
            }
            return html;
        }
        
        // Progressive loading function
        function loadDonationsProgressive(status = 'all', page = 1) {
            if (isLoading) return;
            
            isLoading = true;
            const loadingIndicator = document.getElementById('loadingIndicator');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'block';
            }
            
            // Show skeleton screen
            const contentArea = document.getElementById('donationsContent');
            if (contentArea) {
                contentArea.innerHTML = generateSkeletonHTML();
            }
            
            // Load data via API
            fetch(`../../public/api/load-donations-data.php?status=${status}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderDonationsData(data);
                        currentStatus = status;
                        currentPage = page;
                    } else {
                        showError('Failed to load donations data: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error loading donations:', error);
                    showError('Network error: ' + error.message);
                })
                .finally(() => {
                    isLoading = false;
                    if (loadingIndicator) {
                        loadingIndicator.style.display = 'none';
                    }
                });
        }
        
        // Render donations data into current table and rebuild pagination
        function renderDonationsData(data) {
            // Update table body rows
            const tbody = document.getElementById('donationsTableBody') || document.querySelector('#donationsTable tbody');
            if (tbody) {
                tbody.classList.remove('loading');
                const rows = (data.data || []).map(d => {
                    const donorId = d.donor_id ?? d.id ?? '';
                    const surname = d.surname ?? d.last_name ?? d.last ?? '';
                    const firstName = d.first_name ?? d.name ?? d.first ?? '';
                    const donorType = d.donor_type ?? (d.is_returning ? 'Returning' : 'New');
                    const regChannelRaw = d.registration_source ?? d.registration_channel ?? '';
                    const regChannel = regChannelRaw === 'PRC Portal' ? 'PRC System' : (regChannelRaw === 'Mobile' ? 'Mobile System' : (regChannelRaw || 'PRC System'));
                    const statusText = d.status_text ?? d.status ?? 'Pending (Screening)';
                    let badgeClass = 'bg-warning';
                    const s = String(statusText).toLowerCase();
                    if (s.includes('approved') || s.includes('eligible')) badgeClass = 'bg-success';
                    else if (s.includes('declined') || s.includes('refused')) badgeClass = 'bg-danger';
                    else if (s.includes('deferred') || s.includes('ineligible')) badgeClass = 'bg-warning';
                    else if (s.includes('collection')) badgeClass = 'bg-primary';
                    else if (s.includes('examination')) badgeClass = 'bg-info';
                    
                    const isPending = /pending/i.test(statusText);
                    const actionBtn = isPending
                        ? `<button type="button" class="btn btn-warning btn-sm edit-donor" data-donor-id="${escapeHtml(donorId)}" data-eligibility-id="${escapeHtml(d.eligibility_id ?? '')}"><i class="fas fa-edit"></i></button>`
                        : `<button type="button" class="btn btn-info btn-sm view-donor" data-donor-id="${escapeHtml(donorId)}" data-eligibility-id="${escapeHtml(d.eligibility_id ?? '')}"><i class="fas fa-eye"></i></button>`;
                    
                    return `<tr class="donor-row" data-donor-id="${escapeHtml(donorId)}" data-eligibility-id="${escapeHtml(d.eligibility_id ?? '')}">
                        <td>${escapeHtml(donorId)}</td>
                        <td>${escapeHtml(surname)}</td>
                        <td>${escapeHtml(firstName)}</td>
                        <td><span class="badge ${badgeClass}">${escapeHtml(donorType || '')}</span></td>
                        <td>${escapeHtml(regChannel)}</td>
                        <td><span class="badge ${badgeClass}">${escapeHtml(normalizeStatus(statusText))}</span></td>
                        <td>${actionBtn}</td>
                    </tr>`;
                }).join('');
                tbody.innerHTML = rows || `<tr><td colspan="7" class="text-center">No records found.</td></tr>`;
            }
            
            // Update pagination info text if present
            const paginationInfo = document.getElementById('paginationInfo');
            if (paginationInfo && data.pagination) {
                const p = data.pagination;
                paginationInfo.textContent = `Page ${p.currentPage}${p.totalPages ? ` of ${p.totalPages}` : ''} (${p.totalItems ?? '?'} total items)`;
            }
            
            // Rebuild pagination controls
            if (data.pagination && typeof data.pagination.currentPage !== 'undefined') {
                rebuildPaginationUI(data.pagination, data.status || '<?php echo $status; ?>');
                // Re-bind listeners after DOM update
                initializeProgressivePagination();
            }
            
            // Re-bind row action buttons
            document.querySelectorAll('.edit-donor,.view-donor').forEach(btn => {
                btn.addEventListener('click', function(){
                    const donorId = this.getAttribute('data-donor-id') || '';
                    const eligibilityId = this.getAttribute('data-eligibility-id') || '';
                    if (this.classList.contains('edit-donor')) {
                        if (window.openDetails) {
                            openDetails(donorId, eligibilityId);
                        }
                    } else {
                        if (window.openDetails) {
                            openDetails(donorId, eligibilityId);
                        }
                    }
                });
            });
        }
        
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"']/g, s => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[s]));
        }
        
        function normalizeStatus(status) {
            const s = String(status || '').toLowerCase();
            if (s.includes('approved') || s.includes('eligible')) return 'Approved';
            if (s.includes('declined') || s.includes('refused')) return 'Declined';
            if (s.includes('deferred') || s.includes('ineligible')) return 'Deferred';
            if (s.includes('examination')) return 'Pending (Examination)';
            if (s.includes('collection')) return 'Pending (Collection)';
            return 'Pending (Screening)';
        }
        
        function rebuildPaginationUI(pagination, status) {
            const pagEl = document.querySelector('.pagination');
            if (!pagEl) return;
            const totalPages = pagination.totalPages || 0;
            const current = pagination.currentPage || 1;
            const makeUrl = (p) => {
                const base = new URL(window.location.href);
                base.searchParams.set('status', status || '<?php echo $status; ?>');
                base.searchParams.set('page', String(p));
                return base.pathname + '?' + base.searchParams.toString();
            };
            let html = '';
            // Prev
            html += `<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="${escapeHtml(makeUrl(Math.max(1, current-1)))}" aria-label="Previous">Previous</a></li>`;
            // Pages (limit to window of 5)
            const start = Math.max(1, current - 2);
            const end = totalPages ? Math.min(totalPages, current + 2) : current + 2;
            for (let p = start; p <= end; p++) {
                html += `<li class="page-item ${p === current ? 'active' : ''}"><a class="page-link" href="${escapeHtml(makeUrl(p))}">${p}</a></li>`;
            }
            // Next
            const nextTarget = totalPages ? Math.min(totalPages, current + 1) : current + 1;
            html += `<li class="page-item ${totalPages && current >= totalPages ? 'disabled' : ''}"><a class="page-link" href="${escapeHtml(makeUrl(nextTarget))}" aria-label="Next">Next</a></li>`;
            pagEl.innerHTML = html;
        }
        
        // Get status color for badges
        function getStatusColor(status) {
            switch(status.toLowerCase()) {
                case 'pending': return 'warning';
                case 'approved': return 'success';
                case 'declined': return 'danger';
                case 'deferred': return 'secondary';
                default: return 'primary';
            }
        }
        
        // Show error message
        function showError(message) {
            const contentArea = document.getElementById('donationsContent');
            if (contentArea) {
                contentArea.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        ${message}
                        <button class="btn btn-sm btn-outline-danger ms-2" onclick="loadDonationsProgressive(currentStatus, currentPage)">Retry</button>
                    </div>
                `;
            }
        }
        
        // Document ready event listener
        document.addEventListener('DOMContentLoaded', function() {
            // Clean up any lingering loading states and modal content on page load
            const loadingIndicator = document.getElementById('loadingIndicator');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
            
            // Initialize LCP optimization for pending filter
            optimizeLCP();
            
            // Initialize progressive pagination
            initializeProgressivePagination();
            
            // Reset all modal content to prevent loading text from showing
            const modalContents = [
                'medicalHistoryModalContent',
                'medicalHistoryModalAdminContent',
                'medicalHistoryApprovalContent'
            ];
            
            modalContents.forEach(contentId => {
                const content = document.getElementById(contentId);
                if (content) {
                    content.innerHTML = '';
                }
            });
            
            // Hide any visible modals that might be showing loading states
            const modals = [
                'medicalHistoryModal',
                'medicalHistoryModalAdmin',
                'medicalHistoryApprovalWorkflowModal',
                'physicianMedicalHistoryModal'
            ];
            
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('show');
                    modal.style.display = 'none !important';
                    modal.style.visibility = 'hidden';
                    modal.setAttribute('aria-hidden', 'true');
                }
            });
            
            // Removed backdrop interference loop; Bootstrap handles backdrops.
            
            // OPTIMIZATION: Show loading indicator for slow connections
            const tableContainer = document.querySelector('.table-responsive');
            
            // Ensure loading indicator is hidden initially
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none !important';
            }
            
            // Show loading indicator if page takes more than 1 second to load
            const loadingIndicatorEl = document.getElementById('loadingIndicator');
            const loadingTimeout = setTimeout(function() {
                if (loadingIndicatorEl && tableContainer) {
                    loadingIndicatorEl.style.display = 'block';
                    tableContainer.style.opacity = '0.5';
                }
            }, 1000);
            // Hide loading indicator when page is fully loaded
            window.addEventListener('load', function() {
                clearTimeout(loadingTimeout);
                if (loadingIndicatorEl && tableContainer) {
                    loadingIndicatorEl.style.display = 'none';
                    tableContainer.style.opacity = '1';
                }
                
                // Additional cleanup to ensure no loading states are visible
                const allLoadingElements = document.querySelectorAll('.spinner-border, .loading-text, [class*="loading"]');
                allLoadingElements.forEach(element => {
                    if (element.closest('.modal') === null) { // Only hide if not inside a modal
                        element.style.display = 'none';
                    }
                });
                
                // Ensure all modal content areas are clean
                const modalContentAreas = [
                    'medicalHistoryModalContent',
                    'medicalHistoryModalAdminContent', 
                    'medicalHistoryApprovalContent'
                ];
                
                modalContentAreas.forEach(contentId => {
                    const content = document.getElementById(contentId);
                    if (content && content.innerHTML.includes('Loading')) {
                        content.innerHTML = '';
                    }
                });
            });
            // Check if we need to refresh data (e.g. after processing a donor)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('processed')) {
                // Remove the processed parameter from URL to prevent showing the message on manual refresh
                const newUrl = window.location.pathname + '?' + urlParams.toString().replace(/&?processed=true/, '');
                window.history.replaceState({}, document.title, newUrl);
                // If we're on a tab that should show the newly processed donor, refresh after 5 seconds
                // to make sure all database operations have completed
                setTimeout(function() {
                    window.location.reload();
                }, 5000);
            }
            // Clean URL if donor_registered parameter is present
            if (urlParams.has('donor_registered')) {
                // Remove the donor_registered parameter from URL to prevent showing the message on manual refresh
                const newUrl = window.location.pathname + '?' + urlParams.toString().replace(/&?donor_registered=true/, '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            // Function to copy mobile app password
            function copyPassword() {
                const passwordElement = document.getElementById('mobilePassword');
                if (passwordElement) {
                    const password = passwordElement.textContent;
                    navigator.clipboard.writeText(password).then(function() {
                        // Show temporary success message
                        const button = event.target.closest('button');
                        const originalText = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        button.classList.remove('btn-outline-secondary');
                        button.classList.add('btn-success');
                        
                        setTimeout(function() {
                            button.innerHTML = originalText;
                            button.classList.remove('btn-success');
                            button.classList.add('btn-outline-secondary');
                        }, 2000);
                    }).catch(function(err) {
                        console.error('Failed to copy password: ', err);
                        if (window.adminModal && window.adminModal.alert) {
                            window.adminModal.alert('Failed to copy password. Please copy manually: ' + password);
                        } else {
                            console.error('Admin modal not available');
                        }
                    });
                }
            }
            
            // Make copyPassword function globally available
            window.copyPassword = copyPassword;
            // Global variables for tracking current donor
            var currentDonorId = null;
            var currentEligibilityId = null;
            // Initialize loading modal (kept)
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'), {
                backdrop: false,
                keyboard: false
            });
            // Function to show admin donor registration modal
            window.showConfirmationModal = function() {
                // Check if admin registration modal function is available
                if (typeof window.openAdminDonorRegistrationModal === 'function') {
                    window.openAdminDonorRegistrationModal();
                } else {
                    // Fallback to old behavior if modal not loaded
                    console.error('Admin donor registration modal not available');
                    if (window.adminModal && window.adminModal.alert) {
                        window.adminModal.alert('Registration modal is loading. Please try again in a moment.');
                    } else {
                        console.error('Admin modal not available');
                    }
                }
            };
            // Helper to open details using new modal with legacy fallback
            const baseOpenDetails = function(donorId, eligibilityId) {
                if (!donorId) { return; }
                // Legacy first: match behavior of the All status filter
                const legacyDetails = document.getElementById('donorDetails');
                const donorModal = document.getElementById('donorModal');
                
                // Set loading state if not already set
                if (legacyDetails) {
                    // Only set loading if it's not already showing loading or content
                    const currentContent = legacyDetails.innerHTML.trim();
                    if (!currentContent.includes('spinner-border') && !currentContent.includes('donor-header-wireframe')) {
                        legacyDetails.innerHTML = '<div class="text-center my-4"><div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading donor details...</p></div>';
                    }
                }
                
                // Show modal if not already shown
                if (donorModal) {
                    try {
                        const modalInstance = bootstrap.Modal.getInstance(donorModal) || new bootstrap.Modal(donorModal);
                        if (!donorModal.classList.contains('show')) {
                            modalInstance.show();
                        }
                    } catch(e) {
                        console.warn('Error showing modal:', e);
                    }
                }
                
                if (typeof window.fetchDonorDetails === 'function') {
                    window.fetchDonorDetails(donorId, eligibilityId || '');
                }
            };

            function openDetails(donorId, eligibilityId) {
                if (!donorId) { return; }
                // Ensure donorId is a number
                const numericDonorId = parseInt(donorId, 10);
                if (isNaN(numericDonorId)) {
                    console.error('[Admin] Invalid donor ID:', donorId);
                    return;
                }
                
                // Show modal immediately with loading state - don't wait for access checks
                const donorModal = document.getElementById('donorModal');
                const legacyDetails = document.getElementById('donorDetails');
                if (donorModal && legacyDetails) {
                    // Set loading state immediately
                    legacyDetails.innerHTML = '<div class="text-center my-4"><div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading donor details...</p></div>';
                    // Show modal immediately
                    try {
                        const modalInstance = bootstrap.Modal.getInstance(donorModal) || new bootstrap.Modal(donorModal);
                        modalInstance.show();
                    } catch(e) {
                        console.warn('Error showing modal:', e);
                    }
                }
                
                const proceed = () => {
                    window.currentAdminDonorId = numericDonorId;
                    console.log('[Admin] Opening donor details for ID:', numericDonorId);
                    if (window.AccessLockManagerAdmin) {
                        if (!window.AccessLockManagerAdmin.initialized) {
                            console.warn('[Admin] AccessLockManagerAdmin not initialized, initializing now...');
                            window.AccessLockManagerAdmin.init({
                                scopes: ['blood_collection', 'medical_history', 'physical_examination'],
                                guardSelectors: ['.view-donor', '.circular-btn', 'button[data-donor-id]', 'button[data-eligibility-id]'],
                                endpoint: '../../assets/php_func/access_lock_manager_admin.php',
                                autoClaim: false
                            });
                        }
                        window.AccessLockManagerAdmin.activate({ donor_id: numericDonorId });
                    } else {
                        console.warn('[Admin] AccessLockManagerAdmin not available');
                    }
                    baseOpenDetails(numericDonorId, eligibilityId);
                };
                if (window.AccessLockGuardAdmin) {
                    AccessLockGuardAdmin.ensureAccess({
                        scope: ['medical_history', 'physical_examination', 'blood_collection'],
                        donorId: numericDonorId,
                        lockValue: 2,
                        messages: {
                            medical_history: 'This donor is being processed in the Interviewer stage.',
                            physical_examination: 'This donor is being processed in the Interviewer stage.',
                            blood_collection: 'This donor is being processed in the Phlebotomist stage.'
                        },
                        onAllowed: proceed
                    });
                } else {
                    proceed();
                }
            }
            const baseOpenDetailsGuarded = openDetails;
            openDetails = function(donorId, eligibilityId) {
                if (!donorId) return;
                
                // Show modal immediately with loading state - don't wait for access checks
                const donorModal = document.getElementById('donorModal');
                const legacyDetails = document.getElementById('donorDetails');
                if (donorModal && legacyDetails) {
                    // Set loading state immediately
                    legacyDetails.innerHTML = '<div class="text-center my-4"><div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading donor details...</p></div>';
                    // Show modal immediately
                    try {
                        const modalInstance = bootstrap.Modal.getInstance(donorModal) || new bootstrap.Modal(donorModal);
                        modalInstance.show();
                    } catch(e) {
                        console.warn('Error showing modal:', e);
                    }
                }
                
                const proceed = () => baseOpenDetailsGuarded(donorId, eligibilityId);
                if (window.AccessLockGuardAdmin) {
                    AccessLockGuardAdmin.ensureAccess({
                        scope: ['blood_collection', 'medical_history', 'physical_examination'],
                        donorId,
                        lockValue: 2,
                        message: 'This donor is currently being processed by a staff account. Please try again later.',
                        onAllowed: proceed
                    });
                } else {
                    proceed();
                }
            };
            // Explicit listeners (replicates previous working approach)
            document.querySelectorAll('.view-donor').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    openDetails(this.getAttribute('data-donor-id') || '', this.getAttribute('data-eligibility-id') || '');
                });
            });
            document.querySelectorAll('.edit-donor').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    openDetails(this.getAttribute('data-donor-id') || '', this.getAttribute('data-eligibility-id') || '');
                });
            });
            document.querySelectorAll('tr.donor-row').forEach(function(row){
                row.addEventListener('click', function(){
                    openDetails(this.getAttribute('data-donor-id') || '', this.getAttribute('data-eligibility-id') || '');
                });
            });
            // Delegated listener on table container (ensures any status/pagination re-renders work)
            const donationsTableEl = document.getElementById('donationsTable');
            if (donationsTableEl) {
                donationsTableEl.addEventListener('click', function(e){
                    const actionBtn = e.target && (e.target.closest && e.target.closest('.view-donor, .edit-donor')) ? e.target.closest('.view-donor, .edit-donor') : null;
                    if (actionBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        openDetails(actionBtn.getAttribute('data-donor-id') || '', actionBtn.getAttribute('data-eligibility-id') || '');
                        return;
                    }
                    const row = e.target && (e.target.closest && e.target.closest('tr.donor-row')) ? e.target.closest('tr.donor-row') : null;
                    if (row) {
                        openDetails(row.getAttribute('data-donor-id') || '', row.getAttribute('data-eligibility-id') || '');
                    }
                });
            }
        });

        // OPTIMIZATION: Comprehensive backdrop cleanup for all modals
        (function comprehensiveBackdropCleanup(){
            // Enhanced cleanup function that removes ALL unnecessary backdrops
            function removeAllBackdrops(){
                try {
                    // Remove all modal backdrops (including stacked ones)
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => {
                        backdrop.remove();
                    });
                    
                    // Clean up body state
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    
                    // Remove any lingering modal classes from hidden modals
                    document.querySelectorAll('.modal').forEach(modal => {
                        if (!modal.classList.contains('show')) {
                            modal.style.display = 'none';
                            modal.setAttribute('aria-hidden', 'true');
                            modal.removeAttribute('aria-modal');
                        }
                    });
                    
                    console.log('Comprehensive backdrop cleanup completed');
                } catch(error) {
                    console.warn('Backdrop cleanup error:', error);
                }
            }
            
            // Cleanup function for individual modals
            function cleanupModal(modalId){
                try {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        // Remove backdrop
                        removeAllBackdrops();
                        
                        // Clean up modal state
                        modal.classList.remove('show');
                        modal.style.display = 'none';
                        modal.setAttribute('aria-hidden', 'true');
                        modal.removeAttribute('aria-modal');
                    }
                } catch(error) {
                    console.warn(`Cleanup error for modal ${modalId}:`, error);
                }
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                // List of all modals that need cleanup
                const modalIds = [
                    'donorDetailsModal', 'donorModal', 'medicalHistoryModal', 
                    'medicalHistoryModalAdmin', 'screeningFormModal', 
                    'physicalExaminationModalAdmin', 'bloodCollectionModalAdmin',
                    'medicalHistoryApprovalModal', 'medicalHistoryDeclinedModal',
                    'donorProfileModal'
                ];
                
                modalIds.forEach(function(modalId){
                    const modal = document.getElementById(modalId);
                    if (!modal) return;
                    
                    // Clean up on modal hide
                    modal.addEventListener('hide.bs.modal', function(){
                        setTimeout(removeAllBackdrops, 50);
                    });
                    
                    // Clean up on modal hidden
                    modal.addEventListener('hidden.bs.modal', function(){
                        removeAllBackdrops();
                    });
                    
                    // Clean up on close button click
                    const closeButtons = modal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close');
                    closeButtons.forEach(btn => {
                        btn.addEventListener('click', function(){
                            setTimeout(removeAllBackdrops, 100);
                        });
                    });
                });
                
                // Global cleanup on any modal close
                document.addEventListener('click', function(e){
                    if (e.target.matches('[data-bs-dismiss="modal"], .btn-close')) {
                        setTimeout(removeAllBackdrops, 150);
                    }
                });
                
                // Cleanup on escape key
                document.addEventListener('keydown', function(e){
                    if (e.key === 'Escape') {
                        setTimeout(removeAllBackdrops, 100);
                    }
                });
            });
            
            // Expose cleanup functions globally
            window.removeAllBackdrops = removeAllBackdrops;
            window.cleanupModal = cleanupModal;
            
            // Periodic cleanup to catch any missed backdrops (every 5 seconds)
            setInterval(function(){
                const backdrops = document.querySelectorAll('.modal-backdrop');
                const openModals = document.querySelectorAll('.modal.show');
                
                // If there are backdrops but no open modals, clean up
                if (backdrops.length > 0 && openModals.length === 0) {
                    console.log('Periodic cleanup: Removing orphaned backdrops');
                    removeAllBackdrops();
                }
                
                // If there are multiple backdrops, remove extras
                if (backdrops.length > 1) {
                    console.log('Periodic cleanup: Removing duplicate backdrops');
                    for (let i = 1; i < backdrops.length; i++) {
                        backdrops[i].remove();
                    }
                }
            }, 5000);
        })();
        // Function to fetch donor details - now using extracted admin module   
        function fetchDonorDetails(donorId, eligibilityId) {
            // Use the extracted AdminDonorModal module
            if (typeof AdminDonorModal !== 'undefined' && AdminDonorModal.fetchDonorDetails) {
                AdminDonorModal.fetchDonorDetails(donorId, eligibilityId);
                        } else {
                console.error('AdminDonorModal not loaded');
                document.getElementById('donorDetails').innerHTML = '<div class="alert alert-danger">Error: Admin module not loaded. Please refresh the page.</div>';
            }
        }
        // Function to load edit form
        function loadEditForm(donorId, eligibilityId) {
            fetch(`../../assets/php_func/donor_edit_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
                .then(response => response.json())
                .then(data => {
                    // Populate edit form with donor details
                    const editFormContainer = document.getElementById('editDonorFormContent');
                    if (data.error) {
                        editFormContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    const donor = data.donor;
                    const eligibility = data.eligibility;
                    // Create the edit form
                    let html = `<form id="updateEligibilityForm" method="post" action="../../assets/php_func/update_eligibility.php">
                        <input type="hidden" name="eligibility_id" value="${eligibility.eligibility_id}">
                        <input type="hidden" name="donor_id" value="${donor.donor_id}">
                        <div class="donor_form_container">
                            <div class="donor_form_grid grid-3">
                                <div>
                                    <label class="donor_form_label">Surname</label>
                                    <input type="text" class="donor_form_input" name="surname" value="${donor.surname || ''}" readonly>
                                </div>
                                <div>
                                    <label class="donor_form_label">First Name</label>
                                    <input type="text" class="donor_form_input" name="first_name" value="${donor.first_name || ''}" readonly>
                                </div>
                                <div>
                                    <label class="donor_form_label">Middle Name</label>
                                    <input type="text" class="donor_form_input" name="middle_name" value="${donor.middle_name || ''}" readonly>
                                </div>
                            </div>
                            <div class="donor_form_grid grid-3">
                                <div>
                                    <label class="donor_form_label">Blood Type</label>
                                    <input type="text" class="donor_form_input" name="blood_type" value="${eligibility.blood_type || ''}">
                                </div>
                                <div>
                                    <label class="donor_form_label">Donation Type</label>
                                    <select class="donor_form_input" name="donation_type">
                                        <option value="whole_blood" ${eligibility.donation_type === 'whole_blood' ? 'selected' : ''}>Whole Blood</option>
                                        <option value="plasma" ${eligibility.donation_type === 'plasma' ? 'selected' : ''}>Plasma</option>
                                        <option value="platelets" ${eligibility.donation_type === 'platelets' ? 'selected' : ''}>Platelets</option>
                                        <option value="double_red_cells" ${eligibility.donation_type === 'double_red_cells' ? 'selected' : ''}>Double Red Cells</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="donor_form_label">Status</label>
                                    <select class="donor_form_input" name="status">
                                        <option value="eligible" ${eligibility.status === 'eligible' ? 'selected' : ''}>Eligible</option>
                                        <option value="ineligible" ${eligibility.status === 'ineligible' ? 'selected' : ''}>Ineligible</option>
                                        <option value="failed_collection" ${eligibility.status === 'failed_collection' ? 'selected' : ''}>Failed Collection</option>
                                        <option value="disapproved" ${eligibility.status === 'disapproved' ? 'selected' : ''}>Disapproved</option>
                                    </select>
                                </div>
                            </div>
                            <div class="donor_form_grid grid-2">
                                <div>
                                    <label class="donor_form_label">Blood Bag Type</label>
                                    <input type="text" class="donor_form_input" name="blood_bag_type" value="${eligibility.blood_bag_type || ''}">
                                </div>
                                <div>
                                    <label class="donor_form_label">Blood Bag Brand</label>
                                    <input type="text" class="donor_form_input" name="blood_bag_brand" value="${eligibility.blood_bag_brand || ''}">
                                </div>
                            </div>
                            <div class="donor_form_grid grid-2">
                                <div>
                                    <label class="donor_form_label">Amount Collected (mL)</label>
                                    <input type="number" class="donor_form_input" name="amount_collected" value="${eligibility.amount_collected || ''}">
                                </div>
                                <div>
                                    <label class="donor_form_label">Donor Reaction</label>
                                    <input type="text" class="donor_form_input" name="donor_reaction" value="${eligibility.donor_reaction || ''}">
                                </div>
                            </div>
                            <div class="donor_form_grid grid-1">
                                <div>
                                    <label class="donor_form_label">Management Done</label>
                                    <textarea class="donor_form_input" name="management_done" rows="3">${eligibility.management_done || ''}</textarea>
                                </div>
                            </div>
                            <div class="donor_form_grid grid-1 mt-3">
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary">Update Donor Information</button>
                                </div>
                            </div>
                        </div>
                    </form>`;
                    editFormContainer.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading edit form:', error);
                    document.getElementById('editDonorFormContent').innerHTML = '<div class="alert alert-danger">Error loading donor data for editing. Please try again.</div>';
                });
        }
    </script>
    <?php
    // Include admin-specific modals
    // Medical History approval/decline modals
    include_once '../../src/views/modals/medical-history-approval-modals.php';
    include_once '../../src/views/modals/physical-examination-modal-admin.php'; // Admin-specific modal
    // Note: staff-physician-account-summary-modal.php is NOT included here - we use the compact physicianSectionModal instead
    // Interviewer confirmation modals
    include_once '../../src/views/modals/interviewer-confirmation-modals.php';
    // Admin screening modal (admin-specific)
    if (file_exists('../../src/views/forms/admin_donor_initial_screening_form_modal.php')) {
        include_once '../../src/views/forms/admin_donor_initial_screening_form_modal.php';
    }
    // Defer donor modal (shared with staff)
    if (file_exists('../../src/views/modals/defer-donor-modal.php')) {
        include_once '../../src/views/modals/defer-donor-modal.php';
    }
    // Blood collection modal (Admin version)
    if (file_exists('../../src/views/modals/blood-collection-modal-admin.php')) {
        include_once '../../src/views/modals/blood-collection-modal-admin.php';
    }
    // Blood collection view modal (Admin version)
    if (file_exists('../../src/views/modals/blood-collection-view-modal-admin.php')) {
        include_once '../../src/views/modals/blood-collection-view-modal-admin.php';
    }
    ?>
    <!-- Admin modal styles/scripts -->
    <link rel="stylesheet" href="../../assets/css/medical-history-approval-modals.css">
    <!-- Optional: physical exam modal CSS; load only if present -->
    <link rel="preload" as="style" href="../../assets/css/physical-examination-modal.css" onload="this.rel='stylesheet'" crossorigin>
    <noscript><link rel="stylesheet" href="../../assets/css/physical-examination-modal.css"></noscript>
     <!-- LCP OPTIMIZATION: Enhanced JavaScript files loaded with defer -->
    <script src="../../assets/js/enhanced-workflow-manager.js" defer></script>
    <script src="../../assets/js/enhanced-data-handler.js" defer></script>
    <script src="../../assets/js/enhanced-validation-system.js" defer></script>
    <script src="../../assets/js/unified-staff-workflow-system.js" defer></script>
    <!-- Project scripts that power these modals -->
    <script>
        // Load MH approval script only if MH modals are present (deferred)
        (function(){
            const hasMH = document.getElementById('medicalHistoryModal') || document.getElementById('medicalHistoryDeclineModal') || document.getElementById('medicalHistoryApprovalModal');
            if (!hasMH) return;
            const s = document.createElement('script');
            s.src = '../../assets/js/medical-history-approval.js';
            s.defer = true;
            document.currentScript.parentNode.insertBefore(s, document.currentScript.nextSibling);
            
            // Admin-specific override: Force close modal after approval
            s.onload = function() {
                // Override showApprovedThenReturn for admin dashboard only
                if (window.showApprovedThenReturn) {
                    const originalShowApproved = window.showApprovedThenReturn;
                    window.showApprovedThenReturn = function(donorId, screeningData) {
                        const isAdminDashboard = window.location.pathname.includes('dashboard-Inventory-System-list-of-donations') ||
                                                 window.location.pathname.includes('Dashboard-Inventory-System');
                        if (isAdminDashboard) {
                            // Admin-specific: Force close modal immediately and prevent reopening
                            const forceCloseAdminModal = () => {
                                const mhModal = document.getElementById('medicalHistoryModalAdmin') || document.getElementById('medicalHistoryModal');
                                if (mhModal) {
                                    mhModal.classList.remove('show', 'fade', 'in');
                                    mhModal.style.setProperty('display', 'none', 'important');
                                    mhModal.style.setProperty('visibility', 'hidden', 'important');
                                    mhModal.style.setProperty('opacity', '0', 'important');
                                    mhModal.style.setProperty('z-index', '-1', 'important');
                                    mhModal.style.setProperty('pointer-events', 'none', 'important');
                                    mhModal.setAttribute('aria-hidden', 'true');
                                    mhModal.removeAttribute('aria-modal');
                                    mhModal.removeAttribute('role');
                                    
                                    document.querySelectorAll('.modal-backdrop').forEach(b => {
                                        b.style.setProperty('display', 'none', 'important');
                                        b.remove();
                                    });
                                    
                                    document.body.classList.remove('modal-open');
                                    document.body.style.removeProperty('overflow');
                                    document.body.style.removeProperty('padding-right');
                                    
                                    try {
                                        const modalInstance = bootstrap.Modal.getInstance(mhModal);
                                        if (modalInstance) {
                                            modalInstance.hide();
                                        }
                                    } catch(e) {}
                                }
                            };
                            
                            // Close multiple times
                            forceCloseAdminModal();
                            setTimeout(forceCloseAdminModal, 10);
                            setTimeout(forceCloseAdminModal, 50);
                            setTimeout(forceCloseAdminModal, 100);
                            
                            // Watch for any attempts to reopen and prevent them
                            const observer = new MutationObserver((mutations) => {
                                const mhModal = document.getElementById('medicalHistoryModalAdmin') || document.getElementById('medicalHistoryModal');
                                if (mhModal && (mhModal.classList.contains('show') || mhModal.style.display !== 'none')) {
                                    forceCloseAdminModal();
                                }
                            });
                            
                            const mhModal = document.getElementById('medicalHistoryModalAdmin') || document.getElementById('medicalHistoryModal');
                            if (mhModal) {
                                observer.observe(mhModal, { attributes: true, attributeFilter: ['class', 'style'] });
                                // Stop observing after 2 seconds
                                setTimeout(() => observer.disconnect(), 2000);
                            }
                            
                            // Refresh donor details
                            if (donorId && typeof refreshDonorModalIfOpen === 'function') {
                                refreshDonorModalIfOpen(donorId);
                            }
                            return false;
                        }
                        // For non-admin, use original function
                        return originalShowApproved.apply(this, arguments);
                    };
                }
            };
        })();
    </script>
    <script src="../../assets/js/defer_donor_modal.js" defer></script>
    <script src="../../assets/js/initial-screening-defer-button.js" defer></script>
    <script src="../../assets/js/admin-screening-form-modal.js" defer></script>
    <!-- Admin-specific declaration form modal script -->
    <script src="../../assets/js/admin-declaration-form-modal.js" defer></script>
    <!-- Admin-specific physical examination modal script -->
    <script src="../../assets/js/physical_examination_modal_admin.js" defer></script>
     <!-- Admin-specific donor modal script -->
     <script src="../../assets/js/admin-donor-modal.js" defer></script>
    <!-- Admin Donor Registration Modal -->
    <script src="../../assets/js/admin-donor-registration-modal.js" defer></script>
    <!-- Admin-specific access lock manager and guard -->
    <script>
        window.ACCESS_LOCK_ENDPOINT_ADMIN = '../../assets/php_func/access_lock_manager_admin.php';
    </script>
    <script src="../../assets/js/access-lock-manager-admin.js" defer></script>
    <script src="../../assets/js/access-lock-guard-admin.js" defer></script>
     <script>
     // Safety shim: ensure makeApiCall exists for modules (physician PE handler uses it)
     if (typeof window.makeApiCall !== 'function') {
         window.makeApiCall = async function(url, options = {}) {
             try {
                 const isFormData = options.body instanceof FormData;
                 const fetchOptions = {
                     method: options.method || 'GET',
                     headers: {
                         'Content-Type': 'application/json',
                         ...(options.headers || {})
                     },
                     ...options
                 };
                 if (isFormData) { try { delete fetchOptions.headers['Content-Type']; } catch(_) {} }
                 const response = await fetch(url, fetchOptions);
                 const contentType = response.headers.get('content-type') || '';
                 const data = contentType.includes('application/json') ? (await response.json().catch(()=>null)) : (await response.text());
                 if (!response.ok) {
                     const errMsg = (data && data.message) ? data.message : `HTTP error! status: ${response.status}`;
                     throw new Error(errMsg);
                 }
                 return data;
             } catch (error) {
                 return { success: false, message: error?.message || 'Network error' };
             }
         };
     }
     </script>
    <script src="../../assets/js/blood_collection_modal_admin.js" defer></script>
    <script src="../../assets/js/blood_collection_view_modal_admin.js" defer></script>
    <script>
        // Load phlebotomist details modal only when its container exists
        (function(){
            if (!document.getElementById('phlebotomistBloodCollectionDetailsModal')) return;
            const s = document.createElement('script');
            s.src = '../../assets/js/phlebotomist_blood_collection_details_modal.js';
            document.currentScript.parentNode.insertBefore(s, document.currentScript.nextSibling);
        })();
    </script>
    <script>
        console.log('=== SCRIPT LOADING - JAVASCRIPT TEST ===');
        console.log('Script is loading and executing!');
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== DOM CONTENT LOADED - TESTING JAVASCRIPT ===');
            console.log('JavaScript is working!');
            try { if (typeof initializeMedicalHistoryApproval === 'function') initializeMedicalHistoryApproval(); } catch (e) {}
            // Initialize enhanced workflow system if available
            if (typeof UnifiedStaffWorkflowSystem !== 'undefined') {
                window.unifiedSystem = new UnifiedStaffWorkflowSystem();
                console.log('Unified Staff Workflow System initialized for dashboard');
            }
            // Initialize blood collection modal for admin
            if (typeof BloodCollectionModalAdmin !== 'undefined') {
                window.bloodCollectionModalAdmin = new BloodCollectionModalAdmin();
                console.log('Blood Collection Modal initialized for admin');
            }
            // Initialize physical examination modal for admin
            console.log('Checking for PhysicalExaminationModalAdmin class:', typeof PhysicalExaminationModalAdmin);
            if (typeof PhysicalExaminationModalAdmin !== 'undefined') {
                window.physicalExaminationModalAdmin = new PhysicalExaminationModalAdmin();
                console.log('Physical Examination Modal initialized for admin');
            } else {
                console.warn('PhysicalExaminationModalAdmin class not found - modal may not work properly');
            }
            
            // Check if modal element exists
            const modalElement = document.getElementById('physicalExaminationModalAdmin');
            console.log('Physical examination modal element found:', !!modalElement);
        });
        window.openInterviewerScreening = function(donor) {
            if (!donor) return;
            try {
                window.openScreeningModal({ donor_id: donor.donor_id });
            } catch (e) {
                window.location.href = `../../src/views/forms/screening-form.php?donor_id=${encodeURIComponent(donor.donor_id)}`;
            }
        };
        window.openPhysicianMedicalReview = function(donor) {
            try {
                // Seed global context for approval/decline handlers
                window.currentMedicalHistoryData = {
                    donor_id: donor?.donor_id || null,
                    screening_id: null,
                    medical_history_id: null,
                    physical_exam_id: null
                };
                if (typeof showApprovalModal === 'function') {
                    showApprovalModal();
                } else {
                    const el = document.getElementById('medicalHistoryApprovalModal');
                    if (el) new bootstrap.Modal(el).show();
                }
            } catch (e) { console.warn('Medical review modal open failed', e); }
        };
        window.openPhysicianCombinedWorkflow = function(donor) {
            console.log('openPhysicianCombinedWorkflow called with:', donor);
            try {
                const donorId = donor?.donor_id || null;
                if (!donorId) {
                    console.error('No donor ID provided for physician workflow');
                    if (window.adminModal && window.adminModal.alert) {
                        window.adminModal.alert('Error: No donor ID provided');
                    } else {
                        console.error('Admin modal not available');
                    }
                    return;
                }
                console.log('Opening physician workflow for donor ID:', donorId);
                // Seed global context for approval/decline handlers
                window.currentMedicalHistoryData = {
                    donor_id: donorId,
                    screening_id: null,
                    medical_history_id: null,
                    physical_exam_id: null
                };
                // First, open the medical history modal for review and approval
                openMedicalHistoryForApproval(donorId);
            } catch (e) {
                console.error('Error opening physician combined workflow:', e);
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Error opening physician workflow');
                } else {
                    console.error('Admin modal not available');
                }
            }
        };
        // Function to open medical history modal for approval (similar to staff dashboard)
        function openMedicalHistoryForApproval(donorId) {
            console.log('Opening medical history for approval for donor:', donorId);
            // Create a modal for medical history review and approval
            const modalHtml = `
                <div class="modal fade" id="medicalHistoryApprovalWorkflowModal" tabindex="-1" aria-labelledby="medicalHistoryApprovalWorkflowModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h4 class="modal-title w-100">
                                    <i class="fas fa-user-md me-2"></i> Medical History Review & Approval
                                </h4>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="medicalHistoryApprovalContent">
                                    <div class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p>Loading medical history...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Close
                                </button>
                                <button type="button" class="btn btn-success" id="approveMedicalHistoryBtn" style="display: none;">
                                    <i class="fas fa-check me-2"></i>Approve Medical History
                                </button>
                                <button type="button" class="btn btn-danger" id="declineMedicalHistoryBtn" style="display: none;">
                                    <i class="fas fa-ban me-2"></i>Decline Medical History
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            // Remove existing modal if any
            const existingModal = document.getElementById('medicalHistoryApprovalWorkflowModal');
            if (existingModal) {
                existingModal.remove();
            }
            // Add modal to document
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('medicalHistoryApprovalWorkflowModal'));
            modal.show();
            // Load medical history content
            loadMedicalHistoryForApproval(donorId);
            // Bind approval/decline handlers
            bindMedicalHistoryApprovalHandlers(donorId);
        }
        // Function to load medical history content for approval
        function loadMedicalHistoryForApproval(donorId) {
            const contentEl = document.getElementById('medicalHistoryApprovalContent');
            if (!contentEl) return;
            // Fetch medical history content from the standalone modal
            fetch(`../../src/views/forms/medical-history-modal.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to load medical history content');
                    }
                    return response.text();
                })
                .then(html => {
                    contentEl.innerHTML = html;
                    // Show approval/decline buttons
                    const approveBtn = document.getElementById('approveMedicalHistoryBtn');
                    const declineBtn = document.getElementById('declineMedicalHistoryBtn');
                    if (approveBtn) approveBtn.style.display = 'inline-block';
                    if (declineBtn) declineBtn.style.display = 'inline-block';
                })
                .catch(error => {
                    console.error('Error loading medical history content:', error);
                    contentEl.innerHTML = `<div class="alert alert-danger">Failed to load medical history content: ${error.message}</div>`;
                });
        }
        // Function to bind medical history approval handlers
        function bindMedicalHistoryApprovalHandlers(donorId) {
            // Hide the submit button and show approve/decline buttons instead
            const submitBtn = document.getElementById('nextButton');
            if (submitBtn) {
                submitBtn.style.display = 'none';
            }
            // The standalone modal doesn't have approve/decline buttons in the main modal
            // We need to add them to the modal footer or show them when the form is completed
            const modalFooter = document.querySelector('#medicalHistoryApprovalWorkflowModal .modal-footer');
            if (modalFooter) {
                // Add approve/decline buttons to the modal footer
                const approveBtn = document.createElement('button');
                approveBtn.className = 'btn btn-success me-2';
                approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve Medical History';
                approveBtn.id = 'approveMedicalHistoryBtn';
                const declineBtn = document.createElement('button');
                declineBtn.className = 'btn btn-danger';
                declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline Medical History';
                declineBtn.id = 'declineMedicalHistoryBtn';
                // Insert buttons before the close button
                const closeBtn = modalFooter.querySelector('button[data-bs-dismiss="modal"]');
                if (closeBtn) {
                    modalFooter.insertBefore(approveBtn, closeBtn);
                    modalFooter.insertBefore(declineBtn, closeBtn);
                } else {
                    modalFooter.appendChild(approveBtn);
                    modalFooter.appendChild(declineBtn);
                }
            }
            // Now bind the event handlers
            const approveBtn = document.getElementById('approveMedicalHistoryBtn');
            const declineBtn = document.getElementById('declineMedicalHistoryBtn');
            if (approveBtn) {
                approveBtn.addEventListener('click', function() {
                    // Use the dashboard's approval functionality
                    handleMedicalHistoryApproval(donorId, 'approve');
                });
            }
            if (declineBtn) {
                declineBtn.addEventListener('click', function() {
                    // Use the dashboard's decline functionality
                    handleMedicalHistoryApproval(donorId, 'decline');
                });
            }
        }
        /**
         * Modal Stacking Utility - Proper z-index management for nested modals
         * This function calculates the correct z-index based on currently open modals
         * and ensures proper stacking order without hardcoded values
         * 
         * ROOT CAUSE FIX: Instead of hardcoding z-index values, this dynamically calculates
         * the correct z-index based on what modals are actually open, ensuring proper stacking
         */
        window.getModalStackingZIndex = function(modalElement) {
            // Base z-index for Bootstrap modals
            const BASE_Z_INDEX = 1050;
            const BACKDROP_OFFSET = 10;
            
            // Find all currently open modals (Bootstrap and custom)
            const openBootstrapModals = document.querySelectorAll('.modal.show');
            const openCustomModals = document.querySelectorAll('.medical-history-modal.show');
            const allOpenModals = Array.from(openBootstrapModals).concat(Array.from(openCustomModals));
            
            // Get the highest z-index from currently open modals
            let maxZIndex = BASE_Z_INDEX;
            allOpenModals.forEach(modal => {
                if (modal === modalElement) return; // Skip self
                const computedStyle = window.getComputedStyle(modal);
                const zIndex = parseInt(computedStyle.zIndex) || 0;
                if (zIndex > maxZIndex) {
                    maxZIndex = zIndex;
                }
            });
            
            // Also check inline styles
            allOpenModals.forEach(modal => {
                if (modal === modalElement) return; // Skip self
                const inlineZIndex = parseInt(modal.style.zIndex) || 0;
                if (inlineZIndex > maxZIndex) {
                    maxZIndex = inlineZIndex;
                }
            });
            
            // Calculate new z-index: highest existing + 10 (Bootstrap's increment)
            const newZIndex = maxZIndex + 10;
            const backdropZIndex = newZIndex - 1;
            
            return {
                modal: newZIndex,
                dialog: newZIndex + 1,
                content: newZIndex + 2,
                backdrop: backdropZIndex
            };
        }
        
        /**
         * Apply proper z-index stacking to a Bootstrap modal
         * This is the main function to use when opening modals on top of others
         */
        window.applyModalStacking = function(modalElement) {
            if (!modalElement) return;
            
            const zIndexes = window.getModalStackingZIndex(modalElement);
            
            // Apply z-index to modal
            modalElement.style.zIndex = zIndexes.modal.toString();
            modalElement.style.position = 'fixed';
            
            // Apply z-index to dialog
            const dialog = modalElement.querySelector('.modal-dialog');
            if (dialog) {
                dialog.style.zIndex = zIndexes.dialog.toString();
            }
            
            // Apply z-index to content
            const content = modalElement.querySelector('.modal-content');
            if (content) {
                content.style.zIndex = zIndexes.content.toString();
            }
            
            // Update backdrop z-index after Bootstrap creates it
            const updateBackdrop = () => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops && backdrops.length > 0) {
                    // Set the last backdrop (for this modal) to be just below the modal
                    backdrops[backdrops.length - 1].style.zIndex = zIndexes.backdrop.toString();
                }
            };
            
            // Update immediately and after a short delay (Bootstrap creates backdrop asynchronously)
            setTimeout(updateBackdrop, 10);
            setTimeout(updateBackdrop, 100);
            
            // Re-apply after modal is fully shown (Bootstrap might reset z-index)
            const reapplyStacking = () => {
                // Recalculate in case other modals opened/closed
                const newZIndexes = window.getModalStackingZIndex(modalElement);
                modalElement.style.zIndex = newZIndexes.modal.toString();
                modalElement.style.position = 'fixed';
                if (dialog) dialog.style.zIndex = newZIndexes.dialog.toString();
                if (content) content.style.zIndex = newZIndexes.content.toString();
                updateBackdrop();
            };
            
            // Listen for shown event to reapply (Bootstrap might change z-index)
            modalElement.addEventListener('shown.bs.modal', reapplyStacking, { once: true });
            
            return zIndexes;
        }
        
        // Track if medical history was just approved to prevent modal from reopening
        let medicalHistoryJustApproved = false;
        
        // Global event listener to automatically apply stacking to any Bootstrap modal when shown
        // This ensures modals opened from anywhere get proper z-index
        document.addEventListener('show.bs.modal', function(event) {
            const modalElement = event.target;
            
            // Prevent Medical History modal from showing if it was just approved or force-closed
            if ((medicalHistoryJustApproved || modalElement.getAttribute('data-force-closed') === 'true') && 
                (modalElement.id === 'medicalHistoryModalAdmin' || modalElement.id === 'medicalHistoryModal')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                modalElement.classList.remove('show', 'fade', 'in');
                modalElement.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; z-index: -1 !important; pointer-events: none !important;';
                modalElement.setAttribute('aria-hidden', 'true');
                return false;
            }
            
            // Only apply if it's a confirmation/approval modal that should be on top
            const shouldStack = modalElement.id && (
                modalElement.id.includes('Approve') || 
                modalElement.id.includes('Decline') || 
                modalElement.id.includes('Confirm') ||
                modalElement.id === 'adminFeedbackModal'
            );
            if (shouldStack && typeof window.applyModalStacking === 'function') {
                window.applyModalStacking(modalElement);
            }
        });
        
        // Function to handle medical history approval/decline
        function handleMedicalHistoryApproval(donorId, action) {
            console.log(`Handling medical history ${action} for donor:`, donorId);
            if (action === 'approve') {
                // Show confirmation modal
                showMedicalHistoryApprovalConfirmation(donorId);
            } else if (action === 'decline') {
                // Show decline modal
                showMedicalHistoryDeclineModal(donorId);
            }
        }
        // Function to show medical history approval confirmation
        function showMedicalHistoryApprovalConfirmation(donorId) {
            // Use the medicalHistoryApproveConfirmModal (red gradient modal with "Please Confirm")
            const confirmModal = document.getElementById('medicalHistoryApproveConfirmModal');
            if (confirmModal) {
                // Create and show Bootstrap modal first
                const modal = bootstrap.Modal.getOrCreateInstance(confirmModal);
                
                // Apply proper modal stacking AFTER modal is shown (Bootstrap creates backdrop then)
                const applyStackingAfterShow = () => {
                    if (typeof applyModalStacking === 'function') {
                        applyModalStacking(confirmModal);
                    } else {
                        // Fallback: Calculate z-index based on open modals
                        const openModals = document.querySelectorAll('.modal.show');
                        let maxZIndex = 1050;
                        openModals.forEach(m => {
                            if (m === confirmModal) return;
                            const z = parseInt(window.getComputedStyle(m).zIndex) || parseInt(m.style.zIndex) || 0;
                            if (z > maxZIndex) maxZIndex = z;
                        });
                        const newZIndex = maxZIndex + 10;
                        confirmModal.style.zIndex = newZIndex.toString();
                        confirmModal.style.position = 'fixed';
                        const dialog = confirmModal.querySelector('.modal-dialog');
                        if (dialog) dialog.style.zIndex = (newZIndex + 1).toString();
                        const content = confirmModal.querySelector('.modal-content');
                        if (content) content.style.zIndex = (newZIndex + 2).toString();
                        
                        setTimeout(() => {
                            const backdrops = document.querySelectorAll('.modal-backdrop');
                            if (backdrops.length > 0) {
                                backdrops[backdrops.length - 1].style.zIndex = (newZIndex - 1).toString();
                            }
                        }, 10);
                    }
                };
                
                // Apply stacking when modal is shown
                confirmModal.addEventListener('shown.bs.modal', applyStackingAfterShow, { once: true });
                
                modal.show();
                
                // Bind confirmation handler - remove any existing handlers first
                const confirmBtn = document.getElementById('confirmApproveMedicalHistoryBtn');
                if (confirmBtn) {
                    // Remove existing event listeners by cloning
                    const newConfirmBtn = confirmBtn.cloneNode(true);
                    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                    
                    newConfirmBtn.addEventListener('click', function() {
                        // Set flag to prevent modal from reopening
                        medicalHistoryJustApproved = true;
                        
                        // Close confirmation modal first
                        modal.hide();
                        
                        // Immediately close Medical History modal before processing
                        const adminModal = document.getElementById('medicalHistoryModalAdmin');
                        const staffModal = document.getElementById('medicalHistoryModal');
                        const mhModal = adminModal || staffModal;
                        if (mhModal) {
                            // Mark as force-closed
                            mhModal.setAttribute('data-force-closed', 'true');
                            
                            // Remove all classes
                            mhModal.classList.remove('show', 'fade', 'in');
                            
                            // Force hide with !important
                            mhModal.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; z-index: -1 !important; pointer-events: none !important;';
                            
                            mhModal.setAttribute('aria-hidden', 'true');
                            mhModal.removeAttribute('aria-modal');
                            mhModal.removeAttribute('role');
                            
                            // Remove all backdrops
                            document.querySelectorAll('.modal-backdrop').forEach(b => {
                                b.style.cssText = 'display: none !important;';
                                b.remove();
                            });
                            
                            // Force body cleanup
                            document.body.classList.remove('modal-open');
                            document.body.style.cssText = document.body.style.cssText.replace(/overflow[^;]*;?/g, '').replace(/padding-right[^;]*;?/g, '');
                            
                            // Try Bootstrap hide
                            try {
                                const modalInstance = bootstrap.Modal.getInstance(mhModal);
                                if (modalInstance) {
                                    modalInstance.hide();
                                }
                            } catch(e) {}
                            
                            // Watch for any changes and force close again
                            const observer = new MutationObserver(() => {
                                if (mhModal.getAttribute('data-force-closed') === 'true') {
                                    mhModal.classList.remove('show', 'fade', 'in');
                                    mhModal.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; z-index: -1 !important; pointer-events: none !important;';
                                    mhModal.setAttribute('aria-hidden', 'true');
                                }
                            });
                            observer.observe(mhModal, { attributes: true, attributeFilter: ['class', 'style'] });
                            
                            // Stop observing after 3 seconds
                            setTimeout(() => {
                                observer.disconnect();
                                mhModal.removeAttribute('data-force-closed');
                            }, 3000);
                        }
                        
                        // Then process approval
                        processMedicalHistoryApproval(donorId);
                        
                        // Reset flag after 3 seconds
                        setTimeout(() => {
                            medicalHistoryJustApproved = false;
                        }, 3000);
                    });
                }
            } else {
                // Fallback: use adminModal.confirm
                const approvalMessage = 'Are you sure you want to approve this donor\'s medical history?';
                const approveAction = () => processMedicalHistoryApproval(donorId);
                if (window.adminModal && typeof window.adminModal.confirm === 'function') {
                    const fallbackModal = document.getElementById('adminFeedbackModal');
                    if (fallbackModal) {
                        applyModalStacking(fallbackModal);
                    }
                    window.adminModal.confirm(approvalMessage, approveAction, {
                        confirmText: 'Approve',
                        cancelText: 'Keep Reviewing'
                    });
                }
            }
        }
        // Function to show medical history decline modal
        function showMedicalHistoryDeclineModal(donorId) {
            // Use the existing medical history decline modal
            const declineModal = document.getElementById('declineMedicalHistoryModal');
            if (declineModal) {
                // Apply proper modal stacking BEFORE showing
                applyModalStacking(declineModal);
                
                // Create and show Bootstrap modal
                const modal = bootstrap.Modal.getOrCreateInstance(declineModal);
                modal.show();
                
                // Bind decline handler
                const submitBtn = document.getElementById('confirmDeclineBtn');
                if (submitBtn) {
                    // Remove existing event listeners by cloning
                    const newSubmitBtn = submitBtn.cloneNode(true);
                    submitBtn.parentNode.replaceChild(newSubmitBtn, submitBtn);
                    
                    newSubmitBtn.addEventListener('click', function() {
                        const reasonInput = document.getElementById('declineReason');
                        const reason = reasonInput ? reasonInput.value.trim() : '';
                        if (reason.length < 10) {
                            if (window.adminModal && window.adminModal.alert) {
                                window.adminModal.alert('Please provide a reason with at least 10 characters.');
                            } else {
                                console.error('Admin modal not available');
                            }
                            return;
                        }
                        processMedicalHistoryDecline(donorId, reason);
                        modal.hide();
                    });
                }
            } else {
                // Fallback: direct decline
                if (window.adminModal && window.adminModal.prompt) {
                    window.adminModal.prompt('Please provide a reason for declining this donor\'s medical history:', {
                        title: 'Decline Reason Required',
                        placeholder: 'Enter reason (minimum 10 characters)'
                    }).then(reason => {
                        if (reason && reason.trim()) {
                            processMedicalHistoryDecline(donorId, reason);
                        }
                    });
                } else {
                    console.error('Admin modal prompt not available');
                }
            }
        }
        // Helper function to refresh donor modal if it's open
        function refreshDonorModalIfOpen(donorId) {
            const donorModal = document.getElementById('donorModal');
            if (donorModal && donorModal.classList.contains('show')) {
                const eligibilityId = window.currentDetailsEligibilityId || window.currentEligibilityId || `pending_${donorId}`;
                if (typeof AdminDonorModal !== 'undefined' && AdminDonorModal && AdminDonorModal.fetchDonorDetails) {
                    setTimeout(() => {
                        AdminDonorModal.fetchDonorDetails(donorId, eligibilityId);
                    }, 500);
                } else if (typeof window.fetchDonorDetails === 'function') {
                    setTimeout(() => {
                        window.fetchDonorDetails(donorId, eligibilityId);
                    }, 500);
                }
            }
        }
        
        // Function to process medical history approval
        function processMedicalHistoryApproval(donorId) {
            console.log('Processing medical history approval for donor:', donorId);
            // Use admin-specific endpoint for medical history approval
            const formData = new FormData();
            formData.append('action', 'approve_medical_history');
            formData.append('donor_id', donorId);
            
            fetch(`../../assets/php_func/admin/process_medical_history_approval_admin.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Medical history approved successfully');
                    
                    // Close Medical History modal immediately - force close with multiple attempts
                    const closeModalForcefully = () => {
                        const adminModal = document.getElementById('medicalHistoryModalAdmin');
                        const staffModal = document.getElementById('medicalHistoryModal');
                        const modalToClose = adminModal || staffModal;
                        if (modalToClose) {
                            // Remove all classes that might keep it visible
                            modalToClose.classList.remove('show', 'fade', 'in');
                            // Force hide with !important
                            modalToClose.style.setProperty('display', 'none', 'important');
                            modalToClose.style.setProperty('visibility', 'hidden', 'important');
                            modalToClose.style.setProperty('opacity', '0', 'important');
                            modalToClose.style.setProperty('z-index', '-1', 'important');
                            modalToClose.style.setProperty('pointer-events', 'none', 'important');
                            modalToClose.setAttribute('aria-hidden', 'true');
                            modalToClose.removeAttribute('aria-modal');
                            modalToClose.removeAttribute('role');
                            
                            // Remove all backdrops
                            document.querySelectorAll('.modal-backdrop').forEach(b => {
                                b.style.setProperty('display', 'none', 'important');
                                b.remove();
                            });
                            
                            // Force body cleanup
                            document.body.classList.remove('modal-open');
                            document.body.style.removeProperty('overflow');
                            document.body.style.removeProperty('padding-right');
                            
                            // Also try Bootstrap hide
                            try {
                                const modalInstance = bootstrap.Modal.getInstance(modalToClose);
                                if (modalInstance) {
                                    modalInstance.hide();
                                }
                            } catch(e) {}
                        }
                    };
                    
                    // Close immediately
                    closeModalForcefully();
                    
                    // Close again after a tiny delay to catch any re-renders
                    setTimeout(closeModalForcefully, 10);
                    setTimeout(closeModalForcefully, 50);
                    setTimeout(closeModalForcefully, 100);
                    
                    // Refresh donor modal if it's open
                    refreshDonorModalIfOpen(donorId);
                    // Show success modal
                    showMedicalHistoryApprovalSuccess(donorId);
                } else {
                    console.error('Failed to approve medical history:', data.message);
                    if (window.adminModal && window.adminModal.alert) {
                        window.adminModal.alert('Failed to approve medical history: ' + data.message);
                    } else {
                        console.error('Admin modal not available');
                    }
                }
            })
            .catch(error => {
                console.error('Error approving medical history:', error);
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Error approving medical history: ' + error.message);
                } else {
                    console.error('Admin modal not available');
                }
            });
        }
        // Function to process medical history decline
        function processMedicalHistoryDecline(donorId, reason = null) {
            console.log('Processing medical history decline for donor:', donorId);
            // Get decline reason from form if not provided
            if (!reason) {
                const reasonInput = document.getElementById('declineReason');
                reason = reasonInput ? reasonInput.value : 'No reason provided';
            }
            // Use admin-specific endpoint for medical history decline
            const formData = new FormData();
            formData.append('action', 'decline_medical_history');
            formData.append('donor_id', donorId);
            formData.append('decline_reason', reason);
            
            fetch(`../../assets/php_func/admin/process_medical_history_approval_admin.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Medical history declined successfully');
                    // Refresh donor modal if it's open
                    refreshDonorModalIfOpen(donorId);
                    // Show decline confirmation modal
                    showMedicalHistoryDeclineSuccess(donorId);
                } else {
                    console.error('Failed to decline medical history:', data.message);
                    if (window.adminModal && window.adminModal.alert) {
                        window.adminModal.alert('Failed to decline medical history: ' + data.message);
                    } else {
                        console.error('Admin modal not available');
                    }
                }
            })
            .catch(error => {
                console.error('Error declining medical history:', error);
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Error declining medical history: ' + error.message);
                } else {
                    console.error('Admin modal not available');
                }
            });
        }
        // Function to show medical history approval success and proceed to physical examination
        function showMedicalHistoryApprovalSuccess(donorId) {
            // Force close modal with multiple attempts
            const closeModalForcefully = () => {
                const adminModal = document.getElementById('medicalHistoryModalAdmin');
                const staffModal = document.getElementById('medicalHistoryModal');
                const modalToClose = adminModal || staffModal;
                if (modalToClose) {
                    // Remove all classes
                    modalToClose.classList.remove('show', 'fade', 'in');
                    // Force hide with !important
                    modalToClose.style.setProperty('display', 'none', 'important');
                    modalToClose.style.setProperty('visibility', 'hidden', 'important');
                    modalToClose.style.setProperty('opacity', '0', 'important');
                    modalToClose.style.setProperty('z-index', '-1', 'important');
                    modalToClose.style.setProperty('pointer-events', 'none', 'important');
                    modalToClose.setAttribute('aria-hidden', 'true');
                    modalToClose.removeAttribute('aria-modal');
                    modalToClose.removeAttribute('role');
                    
                    // Remove all backdrops
                    document.querySelectorAll('.modal-backdrop').forEach(b => {
                        b.style.setProperty('display', 'none', 'important');
                        b.remove();
                    });
                    
                    // Force body cleanup
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.body.style.removeProperty('padding-right');
                    
                    // Try Bootstrap hide
                    try {
                        const modalInstance = bootstrap.Modal.getInstance(modalToClose);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    } catch(e) {}
                }
            };
            
            // Close multiple times to ensure it stays closed
            closeModalForcefully();
            setTimeout(closeModalForcefully, 10);
            setTimeout(closeModalForcefully, 50);
            setTimeout(closeModalForcefully, 100);
            requestAnimationFrame(closeModalForcefully);
            
            // Refresh donor details
            refreshDonorModalIfOpen(donorId);
        }
        // Function to show medical history decline success
        function showMedicalHistoryDeclineSuccess(donorId) {
            // Close the approval workflow modal
            const workflowModal = document.getElementById('medicalHistoryApprovalWorkflowModal');
            if (workflowModal) {
                const modal = bootstrap.Modal.getInstance(workflowModal);
                if (modal) {
                    modal.hide();
                }
            }
            // Show decline confirmation modal
            const declineModal = document.getElementById('medicalHistoryDeclinedModal');
            if (declineModal) {
                const modal = new bootstrap.Modal(declineModal);
                modal.show();
            }
        }
        // Function to proceed to physical examination modal (Admin version)
        function proceedToPhysicalExamination(donorId) {
            console.log('Proceeding to physical examination for donor:', donorId);
            
            try {
                // Try to open the physical examination modal if it exists
                if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                    const screeningData = {
                        donor_form_id: donorId,
                        screening_id: null // Will be fetched if needed
                    };
                    window.physicalExaminationModalAdmin.openModal(screeningData);
                    return;
                }
            } catch (e) { console.warn('physicalExaminationModalAdmin.openModal not available, falling back', e); }
            
            // Fallback: try to show the modal directly
            try {
                const modalElement = document.getElementById('physicalExaminationModalAdmin');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                    return;
                }
            } catch (e) { console.warn('Failed to show physical examination modal directly', e); }
            
            // Final fallback: redirect to admin form
            window.location.href = `../../src/views/forms/physical-examination-form-admin.php?donor_id=${encodeURIComponent(donorId)}`;
        }
        // Verify function is properly attached
        console.log('openPhysicianCombinedWorkflow function defined:', typeof window.openPhysicianCombinedWorkflow);
        console.log('=== SCRIPT BLOCK RUNNING - BEFORE FUNCTION DEFINITION ===');
        // Function to open medical history modal (staff style)
        console.log('=== DEFINING openMedicalHistoryModal FUNCTION ===');
        function openMedicalHistoryModal(donorId) {
            // Prevent multiple instances
            if (window.isOpeningMedicalHistory) {
                console.log("Medical history modal already opening, skipping...");
                return;
            }
            window.isOpeningMedicalHistory = true;
            // Track approval status for modal behavior
            try {
                window.currentDonorId = donorId;
                window.currentDonorApproved = false; // Default to false for admin
            } catch (e) {
                window.currentDonorApproved = false;
            }
            // Show the medical history modal
            const modal = document.getElementById('medicalHistoryModal');
            console.log('Medical history modal element:', modal);
            if (modal) {
                console.log('Showing medical history modal for donor:', donorId);
                // Clear any force-hidden inline styles
                try { modal.removeAttribute('style'); } catch(_) {}
                // Ensure visible above any remaining layers
                modal.style.display = 'flex';
                modal.classList.add('show');
                // Debug: Check modal state after showing
                setTimeout(() => {
                    console.log('Modal classes after show:', modal.className);
                    console.log('Modal style after show:', modal.style.display);
                    console.log('Modal computed style:', window.getComputedStyle(modal).display);
                    console.log('Modal computed opacity:', window.getComputedStyle(modal).opacity);
                }, 100);
                // Fetch the admin medical history content
                fetch(`../../src/views/forms/medical-history-modal-content-admin.php?donor_id=${donorId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.text();
                    })
                    .then(html => {
                        // Update modal content
                        const modalContent = document.getElementById('medicalHistoryModalContent');
                        modalContent.innerHTML = html;
                        // Execute any script tags in the loaded content
                        const scripts = modalContent.querySelectorAll('script');
                        scripts.forEach((script, index) => {
                            try {
                                console.log(`Executing script ${index + 1}:`, script.type || 'inline');
                                // Create a new script element and execute it
                                const newScript = document.createElement('script');
                                newScript.textContent = script.textContent;
                                newScript.type = script.type || 'text/javascript';
                                document.head.appendChild(newScript);
                                newScript.remove(); // Remove after execution
                                console.log(`Script ${index + 1} executed successfully`);
                            } catch (e) {
                                console.warn('Error executing script:', e);
                            }
                        });
                        
                        // Manually call known functions that might be needed
                        try {
                            if (typeof window.initializeMedicalHistoryApproval === 'function') {
                                window.initializeMedicalHistoryApproval();
                            }
                        } catch(e) {
                            console.warn('Could not execute initializeMedicalHistoryApproval:', e);
                        }
                        // Check if this is part of the interviewer workflow
                        if (window.currentInterviewerDonorId) {
                            // Add interviewer workflow buttons
                            addInterviewerWorkflowButtons(donorId);
                        }
                        // After loading content, call the admin generator
                        if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                            window.generateAdminMedicalHistoryQuestions();
                        }
                    })
                    .catch(error => {
                        console.error('Error loading medical history content:', error);
                        const modalContent = document.getElementById('medicalHistoryModalContent');
                        modalContent.innerHTML = '<div class="alert alert-danger">Error loading medical history. Please try again.</div>';
                    })
                    .finally(() => {
                        window.isOpeningMedicalHistory = false;
                    });
            } else {
                console.error('Medical history modal not found');
                window.isOpeningMedicalHistory = false;
            }
        }
        // Make function globally accessible
        console.log('=== ASSIGNING FUNCTION TO WINDOW ===');
        console.log('Function exists before assignment:', typeof openMedicalHistoryModal);
        window.openMedicalHistoryModal = openMedicalHistoryModal;
        console.log('openMedicalHistoryModal function assigned to window:', typeof window.openMedicalHistoryModal);
        console.log('=== FUNCTION ASSIGNMENT COMPLETE ===');
        // Function to close medical history modal
        function closeMedicalHistoryModal() {
            closeMedicalHistoryModalUnified();
        }
        // Function to show physical examination confirmation after medical history approval
        window.showPhysicianPhysicalExamConfirmation = function() {
            try {
                const modal = new bootstrap.Modal(document.getElementById('physicianPhysicalExamModal'));
                modal.show();
            } catch (e) {
                console.error('Error showing physical examination confirmation:', e);
            }
        };
        // Function to show approve donor confirmation (alternative)
        window.showApproveDonorConfirmation = function() {
            try {
                const modal = new bootstrap.Modal(document.getElementById('physicianPhysicalExamModal'));
                modal.show();
            } catch (e) {
                console.error('Error showing approve donor confirmation:', e);
            }
        };
        // Function to show physical examination completed modal
        window.showPhysicalExamCompleted = function() {
            try {
                const modal = new bootstrap.Modal(document.getElementById('physicalExamCompletedModal'));
                modal.show();
            } catch (e) {
                console.error('Error showing physical examination completed modal:', e);
            }
        };
        // Function to show blood collection completed modal
        window.showBloodCollectionCompleted = function() {
            try {
                const modal = new bootstrap.Modal(document.getElementById('bloodCollectionCompletedModal'));
                modal.show();
            } catch (e) {
                console.error('Error showing blood collection completed modal:', e);
            }
        };
        // Handle proceed to physical examination button
        document.addEventListener('DOMContentLoaded', function() {
            const proceedToPhysicalExamBtn = document.getElementById('proceedToPhysicalExamBtn');
            if (proceedToPhysicalExamBtn) {
                proceedToPhysicalExamBtn.addEventListener('click', function() {
                    // Get the current donor ID from the global context
                    const donorId = window.currentMedicalHistoryData?.donor_id;
                    if (donorId) {
                        // Close the confirmation modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('physicianPhysicalExamModal'));
                        if (modal) {
                            modal.hide();
                        }
                        // Try to open the physical examination modal
                        setTimeout(() => {
                            console.log('Attempting to open physical examination modal for donor:', donorId);
                            console.log('window.physicalExaminationModalAdmin:', window.physicalExaminationModalAdmin);
                            console.log('typeof window.physicalExaminationModalAdmin.openModal:', typeof window.physicalExaminationModalAdmin?.openModal);
                            
                            try {
                                // Try to open the physical examination modal if it exists
                                if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                                    console.log('Opening physical examination modal via class method');
                                    const screeningData = {
                                        donor_form_id: donorId,
                                        screening_id: null
                                    };
                                    window.physicalExaminationModalAdmin.openModal(screeningData);
                                    return;
                                }
                            } catch (e) { console.warn('physicalExaminationModalAdmin.openModal not available, falling back', e); }
                            
                            // Fallback: try to show the modal directly
                            try {
                                const modalElement = document.getElementById('physicalExaminationModalAdmin');
                                console.log('Modal element found:', !!modalElement);
                                if (modalElement) {
                                    console.log('Opening physical examination modal directly via Bootstrap');
                                    const modal = new bootstrap.Modal(modalElement);
                                    modal.show();
                                    return;
                                }
                            } catch (e) { console.warn('Failed to show physical examination modal directly', e); }
                            
                            // Final fallback: redirect to form page
                            console.log('All modal attempts failed, redirecting to form page');
                            window.location.href = `../../src/views/forms/physical-examination-form-admin.php?donor_id=${encodeURIComponent(donorId)}`;
                        }, 300);
                    } else {
                        console.error('No donor ID found for physical examination');
                        if (window.adminModal && window.adminModal.alert) {
                            window.adminModal.alert('Error: No donor ID found');
                        } else {
                            console.error('Admin modal not available');
                        }
                    }
                });
            }
            // Handle proceed to blood collection button
            const proceedToBloodCollectionBtn = document.getElementById('proceedToBloodCollectionBtn');
            if (proceedToBloodCollectionBtn) {
                proceedToBloodCollectionBtn.addEventListener('click', async function() {
                    // Get the current donor ID from the global context
                    const donorId = window.currentMedicalHistoryData?.donor_id;
                    if (donorId) {
                        // Close the confirmation modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('physicalExamCompletedModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Fetch physical_exam_id before redirecting
                        let physicalExamId = null;
                        try {
                            const resp = await fetch(`../../assets/php_func/admin/get_physical_exam_details_admin.php?donor_id=${encodeURIComponent(donorId)}`);
                            if (resp.ok) {
                                const data = await resp.json();
                                if (data?.success && data?.data?.physical_exam_id) {
                                    physicalExamId = data.data.physical_exam_id;
                                    console.log('Found physical_exam_id:', physicalExamId);
                                } else {
                                    console.warn('No physical_exam_id found for donor:', donorId);
                                }
                            }
                        } catch (e) {
                            console.warn('Failed to fetch physical_exam_id:', e);
                        }
                        
                        // Redirect to blood collection form with both donor_id and physical_exam_id
                        setTimeout(() => {
                            let url = `../../src/views/forms/blood-collection-form.php?donor_id=${encodeURIComponent(donorId)}`;
                            if (physicalExamId) {
                                url += `&physical_exam_id=${encodeURIComponent(physicalExamId)}`;
                            }
                            window.location.href = url;
                        }, 300);
                    } else {
                        console.error('No donor ID found for blood collection');
                        if (window.adminModal && window.adminModal.alert) {
                            window.adminModal.alert('Error: No donor ID found');
                        } else {
                            console.error('Admin modal not available');
                        }
                    }
                });
            }
            // Handle view donor details button
            const viewDonorDetailsBtn = document.getElementById('viewDonorDetailsBtn');
            if (viewDonorDetailsBtn) {
                viewDonorDetailsBtn.addEventListener('click', function() {
                    // Get the current donor ID from the global context
                    const donorId = window.currentMedicalHistoryData?.donor_id;
                    if (donorId) {
                        // Close the blood collection completed modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('bloodCollectionCompletedModal'));
                        if (modal) {
                            modal.hide();
                        }
                        // Open the donor details modal
                        setTimeout(() => {
                            if (typeof window.fetchDonorDetails === 'function') {
                                window.fetchDonorDetails(donorId);
                            } else {
                                console.error('fetchDonorDetails function not found');
                            }
                        }, 300);
                    } else {
                        console.error('No donor ID found for viewing details');
                        if (window.adminModal && window.adminModal.alert) {
                            window.adminModal.alert('Error: No donor ID found');
                        } else {
                            console.error('Admin modal not available');
                        }
                    }
                });
            }
        });
        window.openPhysicianPhysicalExam = function(context) {
            // ALWAYS use the compact Physical Examination Summary modal (matching Initial Screening Form style)
            // This is a VIEW-ONLY function - never use the edit modal with steps
            const donorId = context?.donor_id || context || '';
            if (!donorId) {
                console.error('No donor ID provided to openPhysicianPhysicalExam');
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Error: No donor ID provided');
                } else {
                    console.error('Admin modal not available');
                }
                return;
            }
            console.log('[VIEW] openPhysicianPhysicalExam called for donor:', donorId);
            console.log('[VIEW] Using viewPhysicianDetails to open physicianSectionModal (NOT edit modal)');
            // Use the compact view function - this opens physicianSectionModal, NOT physicalExaminationModalAdmin
            if (typeof window.viewPhysicianDetails === 'function') {
                window.viewPhysicianDetails(donorId);
            } else {
                console.error('[VIEW] viewPhysicianDetails function not found!');
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Error: View function not available. Please refresh the page.');
                } else {
                    console.error('Admin modal not available');
                }
            }
        };
        window.openPhlebotomistCollection = async function(context) {
            try {
                const donorId = context?.donor_id || '';
                
                if (window.bloodCollectionModalAdmin && typeof window.bloodCollectionModalAdmin.openModal === 'function') {
                    const modalData = {
                        donor_id: donorId
                    };
                    
                    // If physical_exam_id is not provided, try to resolve it
                    if (!context?.physical_exam_id) {
                        try {
                            const resp = await fetch(`../../assets/php_func/admin/get_physical_exam_details_admin.php?donor_id=${encodeURIComponent(donorId)}`);
                            if (resp.ok) {
                                const data = await resp.json();
                                if (data?.success && data?.data?.physical_exam_id) {
                                    modalData.physical_exam_id = data.data.physical_exam_id;
                                }
                            }
                        } catch (e) {
                            console.warn('Failed to resolve physical_exam_id:', e);
                        }
                    } else {
                        modalData.physical_exam_id = context.physical_exam_id;
                    }
                    
                    // Check if blood collection already exists for this donor
                    if (modalData.physical_exam_id) {
                        try {
                            const collectionResp = await fetch(`../../public/api/blood_collection.php?physical_exam_id=${encodeURIComponent(modalData.physical_exam_id)}`);
                            if (collectionResp.ok) {
                                const collectionData = await collectionResp.json();
                                if (collectionData && Array.isArray(collectionData) && collectionData.length > 0) {
                                    // Collection exists, show view modal
                                    if (window.bloodCollectionViewModalAdmin && typeof window.bloodCollectionViewModalAdmin.openModal === 'function') {
                                        console.log('[Admin] Opening blood collection view modal');
                                        await window.bloodCollectionViewModalAdmin.openModal(collectionData[0]);
                                        return;
                                    }
                                }
                            }
                        } catch (e) {
                            console.warn('Failed to check existing collection:', e);
                        }
                    }
                    
                    // No collection exists or error, open edit modal
                    console.log('[Admin] Opening blood collection edit modal');
                    await window.bloodCollectionModalAdmin.openModal(modalData);
                } else {
                    window.location.href = `dashboard-staff-blood-collection-submission.php?donor_id=${encodeURIComponent(donorId)}`;
                }
            } catch (e) {
                console.error('Error opening phlebotomist collection modal:', e);
                window.location.href = `dashboard-staff-blood-collection-submission.php?donor_id=${encodeURIComponent(context?.donor_id || '')}`;
            }
        };
        // Ensure admin context can open the step-based screening modal reused from staff
        window.openScreeningModal = function(context) {
            try {
                const donorId = context?.donor_id ? String(context.donor_id) : '';
                console.log(`🔄 Opening admin screening modal for donor: ${donorId}`);
                
                // Use admin-specific modal
                if (typeof window.openAdminScreeningModal === 'function') {
                    window.openAdminScreeningModal({ donor_id: donorId });
                } else {
                    console.error('openAdminScreeningModal function not found');
                }
            } catch (err) {
                console.error('Error opening admin screening modal:', err);
            }
        };
        // Admin Medical History step-based modal opener (renamed to openMedicalreviewapproval)
        window.openMedicalreviewapproval = function(context) {
            const donorId = context?.donor_id ? String(context.donor_id) : '';
            const modalEl = document.getElementById('medicalHistoryModalAdmin');
            const contentEl = document.getElementById('medicalHistoryModalAdminContent');
            if (!modalEl || !contentEl) return;
            contentEl.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            // Use getOrCreateInstance to ensure we get the same instance that Bootstrap maintains
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            // Store the instance on the element for easy retrieval
            modalEl._bootstrapModalInstance = bsModal;
            bsModal.show();
            console.log(`🔄 Loading medical history content for donor: ${donorId}`);
            // First, fetch donor details to check status
            fetch(`../../assets/php_func/comprehensive_donor_details_api.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(response => response.json())
                .then(donorData => {
                    if (donorData.error) {
                        throw new Error(donorData.error);
                    }
                    // Check medical history status to determine which buttons to show
                    const medicalHistory = donorData.medical_history || {};
                    const eligibility = donorData.eligibility || {};
                    const screeningForm = donorData.screening_form || {};
                    // Check if initial screening is completed
                    const hasScreeningRecord = screeningForm && Object.keys(screeningForm).length > 0 && screeningForm.screening_id;
                    // Determine if medical history needs approval or is already processed
                    // Get medical approval status (this is the key field used by staff dashboards)
                    const medicalApproval = medicalHistory.medical_approval || medicalHistory.status || screeningForm.medical_history_status || 'Pending';
                    // Only show approve/decline buttons if initial screening is completed AND medical history is not approved
                    const needsApproval = hasScreeningRecord && medicalApproval.toLowerCase() !== 'approved';
                    const isAlreadyApproved = medicalApproval.toLowerCase() === 'approved';
                    console.log(`📊 Medical Approval: ${medicalApproval}, Has Screening: ${hasScreeningRecord}, Needs Approval: ${needsApproval}, Already Approved: ${isAlreadyApproved}`);
                    // Load the admin medical history modal content
                    return fetch(`../../src/views/forms/medical-history-modal-content-admin.php?donor_id=${encodeURIComponent(donorId)}`)
                        .then(r => {
                            console.log(`📡 Medical history response status: ${r.status}`);
                            if (!r.ok) {
                                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                            }
                            return r.text();
                        })
                        .then(html => {
                            console.log(`✅ Medical history content loaded, length: ${html.length}`);
                            contentEl.innerHTML = html;
                            // Execute any script tags in the loaded content (like staff modal does)
                            const scripts = contentEl.querySelectorAll('script');
                            scripts.forEach((script, index) => {
                                try {
                                    console.log(`Executing script ${index + 1}:`, script.type || 'inline');
                                    // Create a new script element and execute it
                                    const newScript = document.createElement('script');
                                    newScript.textContent = script.textContent;
                                    newScript.type = script.type || 'text/javascript';
                                    document.head.appendChild(newScript);
                                    newScript.remove(); // Remove after execution
                                    console.log(`Script ${index + 1} executed successfully`);
                                } catch (e) {
                                    console.log('Script execution error:', e);
                                }
                            });
                            // Call the question generation function after content is loaded
                            setTimeout(() => {
                                console.log('🔍 Checking for generateAdminMedicalHistoryQuestions function...');
                                if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                                    console.log('✅ Function found, calling it...');
                                    window.generateAdminMedicalHistoryQuestions();
                                } else {
                                    console.error('❌ generateAdminMedicalHistoryQuestions function not found');
                                }
                            }, 100);
                            // Configure buttons based on donor status
                            setTimeout(() => {
                                const nextButton = document.getElementById('nextButton');
                                const prevButton = document.getElementById('prevButton');
                                if (needsApproval) {
                                    // For donors who need medical history approval - show approve/decline buttons
                                    if (nextButton) {
                                        nextButton.style.display = 'none';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'none';
                                    }
                                    // Add approve/decline buttons
                                    const modalFooter = document.querySelector('#medicalHistoryModalAdmin .modal-footer');
                                    if (modalFooter && !document.getElementById('approveMedicalHistoryBtn')) {
                                        const approveBtn = document.createElement('button');
                                        approveBtn.className = 'btn btn-success me-2';
                                        approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve Medical History';
                                        approveBtn.id = 'approveMedicalHistoryBtn';
                                        const declineBtn = document.createElement('button');
                                        declineBtn.className = 'btn btn-danger';
                                        declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline Medical History';
                                        declineBtn.id = 'declineMedicalHistoryBtn';
                                        // Insert buttons before the close button
                                        const closeBtn = modalFooter.querySelector('button[data-bs-dismiss="modal"]');
                                        if (closeBtn) {
                                            modalFooter.insertBefore(approveBtn, closeBtn);
                                            modalFooter.insertBefore(declineBtn, closeBtn);
                                        } else {
                                            modalFooter.appendChild(approveBtn);
                                            modalFooter.appendChild(declineBtn);
                                        }
                                        // Bind event handlers
                                        approveBtn.addEventListener('click', function() {
                                            handleMedicalHistoryApproval(donorId, 'approve');
                                        });
                                        declineBtn.addEventListener('click', function() {
                                            handleMedicalHistoryApproval(donorId, 'decline');
                                        });
                                    }
                                    console.log('✅ Showing approve/decline buttons for medical history approval');
                                } else if (isAlreadyApproved) {
                                    // For already approved medical history - show view-only mode
                                    if (nextButton) {
                                        nextButton.style.display = 'none';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'none';
                                    }
                                    console.log('👁️ Showing view-only mode for already approved medical history');
                                } else {
                                    // For new donors or other statuses - show submit button (normal flow)
                                    if (nextButton) {
                                        nextButton.style.display = 'inline-block';
                                        nextButton.textContent = 'Next →';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'inline-block';
                                    }
                                    console.log('📝 Showing submit button for new donor or normal flow');
                                }
                            }, 200);
                            bindAdminMedicalHistoryRefresh();
                        });
                })
                .catch(error => {
                    console.error('❌ Failed to load Medical History form:', error);
                    contentEl.innerHTML = `<div class="alert alert-danger">Failed to load Medical History form: ${error.message}</div>`;
                });
        };
        // Confirmation before processing Medical History (admin)
        window.confirmOpenAdminMedicalHistory = function(donorId) {
            const existing = document.getElementById('processMedicalHistoryConfirm');
            if (existing) existing.remove();
            const div = document.createElement('div');
            div.id = 'processMedicalHistoryConfirm';
            div.innerHTML = `
                <div class="modal fade" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header" style="background:#8b0000;color:#fff;">
                                <h5 class="modal-title">Process Medical History</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Proceed to process this donor's Medical History? You can review and save changes in the step-based form.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" id="confirmProcessMH">Proceed</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(div);
            const modal = new bootstrap.Modal(div.querySelector('.modal'));
            modal.show();
            div.querySelector('#confirmProcessMH').addEventListener('click', function() {
                modal.hide();
                setTimeout(() => openMedicalreviewapproval({ donor_id: donorId }), 150);
            }, { once: true });
            div.querySelector('.modal').addEventListener('hidden.bs.modal', () => div.remove(), { once: true });
        };
        function bindAdminMedicalHistoryRefresh() {
            try {
                const mhModalEl = document.getElementById('medicalHistoryModalAdmin');
                if (!mhModalEl) return;
                
                // Check if listener is already bound to prevent duplicates
                if (mhModalEl.hasAttribute('data-refresh-bound')) return;
                
                mhModalEl.addEventListener('hidden.bs.modal', function() {
                    if (window.currentDetailsDonorId && window.currentDetailsEligibilityId) {
                        fetchDonorDetails(window.currentDetailsDonorId, window.currentDetailsEligibilityId);
                    }
                }, { once: true });
                
                // Mark as bound to prevent duplicate listeners
                mhModalEl.setAttribute('data-refresh-bound', 'true');
            } catch (e) {}
        }
        function bindScreeningFormRefresh() {
            try {
                const screeningModalEl = document.getElementById('screeningFormModal');
                if (!screeningModalEl) return;
                screeningModalEl.addEventListener('hidden.bs.modal', function() {
                    if (window.currentDetailsDonorId && window.currentDetailsEligibilityId) {
                        fetchDonorDetails(window.currentDetailsDonorId, window.currentDetailsEligibilityId);
                    }
                }, { once: true });
            } catch (e) {}
        }
        // Admin Defer Functionality
        function initializeAdminDeferModal() {
            const deferralTypeSelect = document.getElementById('adminDeferralTypeSelect');
            const durationSection = document.getElementById('adminDurationSection');
            const customDurationSection = document.getElementById('adminCustomDurationSection');
            const durationSelect = document.getElementById('adminDeferralDuration');
            const customDurationInput = document.getElementById('adminCustomDuration');
            const submitBtn = document.getElementById('adminSubmitDeferral');
            const durationSummary = document.getElementById('adminDurationSummary');
            const summaryText = document.getElementById('adminSummaryText');
            const durationOptions = document.querySelectorAll('#adminDeferDonorModal .duration-option');
            // Validation elements
            const disapprovalReasonTextarea = document.getElementById('adminDisapprovalReason');
            const deferCharCountElement = document.getElementById('adminDeferCharCount');
            const deferReasonError = document.getElementById('adminDeferReasonError');
            const deferReasonSuccess = document.getElementById('adminDeferReasonSuccess');
            const MIN_LENGTH = 10;
            const MAX_LENGTH = 200;
            if (!deferralTypeSelect) return; // Modal not initialized yet
            // Update disapproval reason validation
            function updateAdminDeferValidation() {
                if (!disapprovalReasonTextarea) return;
                const currentLength = disapprovalReasonTextarea.value.length;
                // Update character count
                deferCharCountElement.textContent = `${currentLength}/${MAX_LENGTH} characters`;
                // Update character count color
                if (currentLength < MIN_LENGTH) {
                    deferCharCountElement.className = 'text-muted';
                } else if (currentLength > MAX_LENGTH) {
                    deferCharCountElement.className = 'text-danger';
                } else {
                    deferCharCountElement.className = 'text-success';
                }
                // Update validation feedback
                if (currentLength === 0) {
                    deferReasonError.style.display = 'none';
                    deferReasonSuccess.style.display = 'none';
                    disapprovalReasonTextarea.classList.remove('is-valid', 'is-invalid');
                } else if (currentLength < MIN_LENGTH) {
                    deferReasonError.style.display = 'block';
                    deferReasonSuccess.style.display = 'none';
                    disapprovalReasonTextarea.classList.add('is-invalid');
                    disapprovalReasonTextarea.classList.remove('is-valid');
                } else if (currentLength > MAX_LENGTH) {
                    deferReasonError.textContent = `Please keep the reason under ${MAX_LENGTH} characters.`;
                    deferReasonError.style.display = 'block';
                    deferReasonSuccess.style.display = 'none';
                    disapprovalReasonTextarea.classList.add('is-invalid');
                    disapprovalReasonTextarea.classList.remove('is-valid');
                } else {
                    deferReasonError.style.display = 'none';
                    deferReasonSuccess.style.display = 'block';
                    disapprovalReasonTextarea.classList.add('is-valid');
                    disapprovalReasonTextarea.classList.remove('is-invalid');
                }
                // Update submit button state
                updateAdminDeferSubmitButtonState();
            }
            // Update submit button state
            function updateAdminDeferSubmitButtonState() {
                if (!disapprovalReasonTextarea) return;
                const reasonValid = disapprovalReasonTextarea.value.length >= MIN_LENGTH && disapprovalReasonTextarea.value.length <= MAX_LENGTH;
                const deferralTypeValid = deferralTypeSelect.value !== '';
                // For temporary deferral, also check duration
                let durationValid = true;
                if (deferralTypeSelect.value === 'Temporary Deferral') {
                    durationValid = durationSelect.value !== '' || customDurationInput.value !== '';
                }
                const allValid = reasonValid && deferralTypeValid && durationValid;
                submitBtn.disabled = !allValid;
                if (allValid) {
                    submitBtn.style.backgroundColor = '#b22222';
                    submitBtn.style.borderColor = '#b22222';
                } else {
                    submitBtn.style.backgroundColor = '#6c757d';
                    submitBtn.style.borderColor = '#6c757d';
                }
            }
            // Handle deferral type change
            deferralTypeSelect.addEventListener('change', function() {
                if (this.value === 'Temporary Deferral') {
                    durationSection.style.display = 'block';
                    setTimeout(() => {
                        durationSection.classList.add('show');
                    }, 50);
                } else {
                    durationSection.classList.remove('show');
                    customDurationSection.classList.remove('show');
                    setTimeout(() => {
                        if (!durationSection.classList.contains('show')) {
                            durationSection.style.display = 'none';
                        }
                        if (!customDurationSection.classList.contains('show')) {
                            customDurationSection.style.display = 'none';
                        }
                    }, 400);
                    durationSummary.style.display = 'none';
                    // Clear duration selections
                    durationOptions.forEach(opt => opt.classList.remove('active'));
                    durationSelect.value = '';
                    customDurationInput.value = '';
                }
                updateAdminSummary();
            });
            // Handle duration option clicks
            durationOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove active class from all options
                    durationOptions.forEach(opt => opt.classList.remove('active'));
                    // Add active class to clicked option
                    this.classList.add('active');
                    const days = this.getAttribute('data-days');
                    if (days === 'custom') {
                        durationSelect.value = 'custom';
                        customDurationSection.style.display = 'block';
                        setTimeout(() => {
                            customDurationSection.classList.add('show');
                            customDurationInput.focus();
                        }, 50);
                    } else {
                        durationSelect.value = days;
                        customDurationSection.classList.remove('show');
                        setTimeout(() => {
                            if (!customDurationSection.classList.contains('show')) {
                                customDurationSection.style.display = 'none';
                            }
                        }, 300);
                        customDurationInput.value = '';
                    }
                    updateAdminSummary();
                });
            });
            // Handle custom duration input
            customDurationInput.addEventListener('input', function() {
                updateAdminSummary();
                // Update the custom option display
                const customOption = document.querySelector('#adminDeferDonorModal .duration-option[data-days="custom"]');
                if (customOption && this.value) {
                    const numberDiv = customOption.querySelector('.duration-number');
                    numberDiv.innerHTML = this.value;
                    const unitDiv = customOption.querySelector('.duration-unit');
                    unitDiv.textContent = this.value == 1 ? 'Day' : 'Days';
                } else if (customOption) {
                    const numberDiv = customOption.querySelector('.duration-number');
                    numberDiv.innerHTML = '<i class="fas fa-edit"></i>';
                    const unitDiv = customOption.querySelector('.duration-unit');
                    unitDiv.textContent = 'Custom';
                }
            });
            function updateAdminSummary() {
                const selectedType = deferralTypeSelect.value;
                const durationValue = durationSelect.value;
                const customDuration = customDurationInput.value;
                if (!selectedType) {
                    durationSummary.style.display = 'none';
                    return;
                }
                let summaryMessage = '';
                if (selectedType === 'Temporary Deferral') {
                    let days = 0;
                    if (durationValue && durationValue !== 'custom') {
                        days = parseInt(durationValue);
                    } else if (durationValue === 'custom' && customDuration) {
                        days = parseInt(customDuration);
                    }
                    if (days > 0) {
                        const endDate = new Date();
                        endDate.setDate(endDate.getDate() + days);
                        const dayText = days === 1 ? 'day' : 'days';
                        summaryMessage = `Donor will be deferred for ${days} ${dayText} until ${endDate.toLocaleDateString()}.`;
                    }
                } else if (selectedType === 'Permanent Deferral') {
                    summaryMessage = 'Donor will be permanently deferred from future donations.';
                } else if (selectedType === 'Refuse') {
                    summaryMessage = 'Donor donation will be refused for this session.';
                }
                if (summaryMessage) {
                    summaryText.textContent = summaryMessage;
                    durationSummary.style.display = 'block';
                } else {
                    durationSummary.style.display = 'none';
                }
                // Update submit button state when summary changes
                updateAdminDeferSubmitButtonState();
            }
            // Add validation event listeners
            if (disapprovalReasonTextarea) {
                disapprovalReasonTextarea.addEventListener('input', updateAdminDeferValidation);
                disapprovalReasonTextarea.addEventListener('paste', () => {
                    setTimeout(updateAdminDeferValidation, 10);
                });
            }
            // Update validation when deferral type changes
            deferralTypeSelect.addEventListener('change', updateAdminDeferSubmitButtonState);
            // Update validation when duration changes
            if (customDurationInput) {
                customDurationInput.addEventListener('input', updateAdminDeferSubmitButtonState);
            }
            // Initial validation
            updateAdminDeferValidation();
        }
        // Open admin defer modal
        window.openAdminDeferModal = function(donorId, eligibilityId) {
            // Set the hidden fields
            document.getElementById('admin-defer-donor-id').value = donorId || '';
            document.getElementById('admin-defer-eligibility-id').value = eligibilityId || '';
            // Reset form
            document.getElementById('adminDeferDonorForm').reset();
            // Hide conditional sections
            const durationSection = document.getElementById('adminDurationSection');
            const customDurationSection = document.getElementById('adminCustomDurationSection');
            durationSection.classList.remove('show');
            customDurationSection.classList.remove('show');
            durationSection.style.display = 'none';
            customDurationSection.style.display = 'none';
            document.getElementById('adminDurationSummary').style.display = 'none';
            // Reset all visual elements
            document.querySelectorAll('#adminDeferDonorModal .duration-option').forEach(option => {
                option.classList.remove('active');
            });
            // Reset custom duration display
            const customOption = document.querySelector('#adminDeferDonorModal .duration-option[data-days="custom"]');
            if (customOption) {
                const numberDiv = customOption.querySelector('.duration-number');
                numberDiv.innerHTML = '<i class="fas fa-edit"></i>';
                const unitDiv = customOption.querySelector('.duration-unit');
                unitDiv.textContent = 'Custom';
            }
            // Clear any validation states
            document.querySelectorAll('#adminDeferDonorModal .form-control').forEach(control => {
                control.classList.remove('is-invalid', 'is-valid');
            });
            // Show the modal
            const deferModal = new bootstrap.Modal(document.getElementById('adminDeferDonorModal'));
            deferModal.show();
            // Re-initialize defer modal functionality when it opens
            setTimeout(() => {
                initializeAdminDeferModal();
            }, 200);
        };
                const donorModal = bootstrap.Modal.getInstance(document.getElementById('donorModal'));
                if (donorModal) {
                    donorModal.hide();
                }
                // Open defer modal
                setTimeout(() => {
                    openAdminDeferModal(donorId, eligibilityId);
                }, 300);
            }
        });
        // Submit admin deferral
        async function submitAdminDeferral() {
            const form = document.getElementById('adminDeferDonorForm');
            const formData = new FormData(form);
            const submitBtn = document.getElementById('adminSubmitDeferral');
            const originalText = submitBtn.innerHTML;
            const donorId = formData.get('donor_id');
            const eligibilityId = formData.get('eligibility_id');
            const deferralType = document.getElementById('adminDeferralTypeSelect').value;
            const disapprovalReason = formData.get('disapproval_reason');
            // Calculate final duration
            let finalDuration = null;
            if (deferralType === 'Temporary Deferral') {
                const durationValue = document.getElementById('adminDeferralDuration').value;
                if (durationValue === 'custom') {
                    finalDuration = document.getElementById('adminCustomDuration').value;
                } else {
                    finalDuration = durationValue;
                }
            }
            console.log('Submitting admin deferral:', {
                donor_id: donorId,
                eligibility_id: eligibilityId,
                deferral_type: deferralType,
                disapproval_reason: disapprovalReason,
                duration: finalDuration
            });
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;
            try {
                // Prepare deferral data for create_eligibility.php
                const deferData = {
                    action: 'create_eligibility_defer',
                    donor_id: parseInt(donorId),
                    eligibility_id: eligibilityId || null,
                    deferral_type: deferralType,
                    disapproval_reason: disapprovalReason,
                    duration: finalDuration
                };
                console.log('Sending defer data to create_eligibility.php:', deferData);
                // Submit to create_eligibility.php endpoint
                const response = await fetch('../../assets/php_func/create_eligibility.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(deferData)
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
                if (result.success) {
                    console.log('Admin deferral recorded successfully:', result);
                    // Close the deferral modal
                    const deferModal = bootstrap.Modal.getInstance(document.getElementById('adminDeferDonorModal'));
                    if (deferModal) {
                        deferModal.hide();
                    }
                    // Show success message
                    setTimeout(() => {
                        showAdminDeferToast('Success', 'Donor has been successfully deferred.', 'success');
                        // Reload the page to refresh the donor list
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }, 300);
                } else {
                    console.error('Failed to record admin deferral:', result.error || result.message);
                    showAdminDeferToast('Error', result.message || result.error || 'Failed to record deferral. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Error processing admin deferral:', error);
                showAdminDeferToast('Error', 'An error occurred while processing the deferral.', 'error');
            } finally {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }
        // Show admin defer toast notification
        function showAdminDeferToast(title, message, type = 'success') {
            // Remove existing toasts
            document.querySelectorAll('.admin-defer-toast').forEach(toast => {
                toast.remove();
            });
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `admin-defer-toast admin-defer-toast-${type}`;
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 99999;
                min-width: 300px;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            toast.innerHTML = `
                <div style="display: flex; align-items: center;">
                    <i class="${icon}" style="margin-right: 10px; font-size: 18px;"></i>
                    <div>
                        <div style="font-weight: bold; margin-bottom: 5px;">${title}</div>
                        <div style="font-size: 14px;">${message}</div>
                    </div>
                </div>
            `;
            // Add to page
            document.body.appendChild(toast);
            // Show toast
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            // Auto-hide toast
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 4000);
        }
        // Handle admin defer submit button click
        document.addEventListener('click', function(e) {
            if (e.target.id === 'adminSubmitDeferral' || e.target.closest('#adminSubmitDeferral')) {
                e.preventDefault();
                submitAdminDeferral();
            }
        });
        // Initialize defer modal functionality on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Try to initialize defer modal when DOM is ready
            setTimeout(() => {
                try {
                    initializeAdminDeferModal();
                } catch (e) {
                    console.log('Admin defer modal not ready yet');
                }
            }, 1000);
            // Check for physical examination completion parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('physical_exam_completed') === '1') {
                // Show physical examination completed modal
                setTimeout(() => {
                    if (typeof window.showPhysicalExamCompleted === 'function') {
                        window.showPhysicalExamCompleted();
                    }
                }, 1000);
                // Clean up URL parameter
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
            // Check for blood collection completion parameter
            if (urlParams.get('success') === '1') {
                // Show blood collection completed modal
                setTimeout(() => {
                    if (typeof window.showBloodCollectionCompleted === 'function') {
                        window.showBloodCollectionCompleted();
                    }
                }, 1000);
                // Clean up URL parameter
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        });
        // Admin Medical History step-based modal opener (loads staff modal content)
        window.openAdminMedicalHistory = function(context) {
            const donorId = context?.donor_id ? String(context.donor_id) : '';
            const modalEl = document.getElementById('medicalHistoryModalAdmin');
            const contentEl = document.getElementById('medicalHistoryModalAdminContent');
            if (!modalEl || !contentEl) return;
            contentEl.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            // Use getOrCreateInstance to ensure we get the same instance that Bootstrap maintains
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            // Store the instance on the element for easy retrieval
            modalEl._bootstrapModalInstance = bsModal;
            bsModal.show();
            console.log(`🔄 Loading medical history content for donor: ${donorId}`);
            // First, fetch donor details to check status
            fetch(`../../assets/php_func/comprehensive_donor_details_api.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(response => response.json())
                .then(donorData => {
                    if (donorData.error) {
                        throw new Error(donorData.error);
                    }
                    // Check medical history status to determine which buttons to show
                    const medicalHistory = donorData.medical_history || {};
                    const eligibility = donorData.eligibility || {};
                    const screeningForm = donorData.screening_form || {};
                    // Check if initial screening is completed
                    const hasScreeningRecord = screeningForm && Object.keys(screeningForm).length > 0 && screeningForm.screening_id;
                    // Determine if medical history needs approval or is already processed
                    // Get medical approval status (this is the key field used by staff dashboards)
                    const medicalApproval = medicalHistory.medical_approval || medicalHistory.status || screeningForm.medical_history_status || 'Pending';
                    // Only show approve/decline buttons if initial screening is completed AND medical history is not approved
                    const needsApproval = hasScreeningRecord && medicalApproval.toLowerCase() !== 'approved';
                    const isAlreadyApproved = medicalApproval.toLowerCase() === 'approved';
                    console.log(`📊 Medical Approval: ${medicalApproval}, Has Screening: ${hasScreeningRecord}, Needs Approval: ${needsApproval}, Already Approved: ${isAlreadyApproved}`);
                    // Load the medical history modal
                    return fetch(`../../src/views/forms/medical-history-modal.php?donor_id=${encodeURIComponent(donorId)}`)
                        .then(r => {
                            console.log(`📡 Medical history response status: ${r.status}`);
                            if (!r.ok) {
                                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                            }
                            return r.text();
                        })
                        .then(html => {
                            console.log(`✅ Medical history content loaded, length: ${html.length}`);
                            contentEl.innerHTML = html;
                            // Execute any script tags in the loaded content (like staff modal does)
                            const scripts = contentEl.querySelectorAll('script');
                            scripts.forEach((script, index) => {
                                try {
                                    console.log(`Executing script ${index + 1}:`, script.type || 'inline');
                                    // Create a new script element and execute it
                                    const newScript = document.createElement('script');
                                    newScript.textContent = script.textContent;
                                    newScript.type = script.type || 'text/javascript';
                                    document.head.appendChild(newScript);
                                    newScript.remove(); // Remove after execution
                                    console.log(`Script ${index + 1} executed successfully`);
                                } catch (e) {
                                    console.log('Script execution error:', e);
                                }
                            });
                            // Call the question generation function after content is loaded
                            setTimeout(() => {
                                console.log('🔍 Checking for generateAdminMedicalHistoryQuestions function...');
                                if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                                    console.log('✅ Function found, calling it...');
                                    window.generateAdminMedicalHistoryQuestions();
                                } else {
                                    console.error('❌ generateAdminMedicalHistoryQuestions function not found');
                                }
                            }, 100);
                            // Configure buttons based on donor status
                            setTimeout(() => {
                                const nextButton = document.getElementById('nextButton');
                                const prevButton = document.getElementById('prevButton');
                                if (needsApproval) {
                                    // For donors who need medical history approval - show approve/decline buttons
                                    if (nextButton) {
                                        nextButton.style.display = 'none';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'none';
                                    }
                                    // Add approve/decline buttons
                                    const modalFooter = document.querySelector('#medicalHistoryModalAdmin .modal-footer');
                                    if (modalFooter && !document.getElementById('approveMedicalHistoryBtn')) {
                                        const approveBtn = document.createElement('button');
                                        approveBtn.className = 'btn btn-success me-2';
                                        approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve Medical History';
                                        approveBtn.id = 'approveMedicalHistoryBtn';
                                        const declineBtn = document.createElement('button');
                                        declineBtn.className = 'btn btn-danger';
                                        declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline Medical History';
                                        declineBtn.id = 'declineMedicalHistoryBtn';
                                        // Insert buttons before the close button
                                        const closeBtn = modalFooter.querySelector('button[data-bs-dismiss="modal"]');
                                        if (closeBtn) {
                                            modalFooter.insertBefore(approveBtn, closeBtn);
                                            modalFooter.insertBefore(declineBtn, closeBtn);
                                        } else {
                                            modalFooter.appendChild(approveBtn);
                                            modalFooter.appendChild(declineBtn);
                                        }
                                        // Bind event handlers
                                        approveBtn.addEventListener('click', function() {
                                            handleMedicalHistoryApproval(donorId, 'approve');
                                        });
                                        declineBtn.addEventListener('click', function() {
                                            handleMedicalHistoryApproval(donorId, 'decline');
                                        });
                                    }
                                    console.log('✅ Showing approve/decline buttons for medical history approval');
                                } else if (isAlreadyApproved) {
                                    // For already approved medical history - show view-only mode
                                    if (nextButton) {
                                        nextButton.style.display = 'none';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'none';
                                    }
                                    console.log('👁️ Showing view-only mode for already approved medical history');
                                } else {
                                    // For new donors or other statuses - show submit button (normal flow)
                                    if (nextButton) {
                                        nextButton.style.display = 'inline-block';
                                        nextButton.textContent = 'Next →';
                                    }
                                    if (prevButton) {
                                        prevButton.style.display = 'inline-block';
                                    }
                                    console.log('📝 Showing submit button for new donor or normal flow');
                                }
                            }, 200);
                        });
                })
                .catch(e => {
                    console.error('❌ Failed to load medical history:', e);
                    contentEl.innerHTML = `<div class="alert alert-danger">Failed to load medical history: ${e.message}</div>`;
                });
        };
    </script>
    </script>
    </script>
    </script>
    </script>
    </script>
    <script>
        // Test if JavaScript is running
        console.log('=== JAVASCRIPT LOADED ===');
        // Role-based edit functions for donor information modal
        window.editMedicalHistory = function(donorId) {
            console.log('=== INTERVIEWER WORKFLOW STARTED ===');
            console.log('Editing medical history for donor:', donorId);
            // Store the donor ID for the workflow
            window.currentInterviewerDonorId = donorId;
            console.log('Stored donor ID:', window.currentInterviewerDonorId);
            
            // Check if confirmation modal exists
            const confirmModalElement = document.getElementById('processMedicalHistoryConfirmModal');
            console.log('Confirmation modal element:', confirmModalElement);
            
            if (confirmModalElement) {
                console.log('Modal found, attempting to show...');
                try {
                // Show confirmation modal first
                const confirmModal = new bootstrap.Modal(confirmModalElement);
                    console.log('Bootstrap modal instance created:', confirmModal);
                confirmModal.show();
                    console.log('Modal show() called successfully');
                } catch (error) {
                    console.error('Error showing modal:', error);
                    // Fallback: try to show modal manually
                    confirmModalElement.style.display = 'block';
                    confirmModalElement.classList.add('show');
                    document.body.classList.add('modal-open');
                }
            } else {
                console.error('Confirmation modal not found! Available modals:');
                const allModals = document.querySelectorAll('.modal');
                allModals.forEach((modal, index) => {
                    console.log(`Modal ${index}:`, modal.id, modal);
                });
            }
        };
        // View medical history for approved donors (view-only mode)
        window.viewMedicalHistory = function(donorId) {
            console.log('=== VIEWING MEDICAL HISTORY (VIEW-ONLY) ===');
            console.log('Donor ID:', donorId);
            
            // Reset any previous state to allow reopening
            window.isOpeningMedicalHistory = false;
            
            // Clear any existing button fix interval
            if (window.mhButtonFixInterval) {
                clearInterval(window.mhButtonFixInterval);
                window.mhButtonFixInterval = null;
            }
            
            // Remove any existing approve/decline buttons from previous openings
            const existingApproveBtn = document.getElementById('viewMHApproveBtn');
            const existingDeclineBtn = document.getElementById('viewMHDeclineBtn');
            if (existingApproveBtn) {
                existingApproveBtn.remove();
            }
            if (existingDeclineBtn) {
                existingDeclineBtn.remove();
            }
            
            // Check if modal elements exist first
            const modalEl = document.getElementById('medicalHistoryModal');
            const modalContent = document.getElementById('medicalHistoryModalContent');
            
            if (!modalEl || !modalContent) {
                console.error('Medical history modal elements not found');
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Medical history modal not found. Please refresh the page.');
                } else {
                    console.error('Admin modal not available');
                }
                return;
            }
            
            // Dispose of any existing modal instance to ensure clean state
            const existingModal = bootstrap.Modal.getInstance(modalEl);
            if (existingModal) {
                existingModal.dispose();
            }
            
            // Show loading spinner immediately
            modalContent.innerHTML = '<div class="d-flex justify-content-center my-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            
            // Create a fresh modal instance
            const modal = new bootstrap.Modal(modalEl, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
            
            // Add event listener to reset state when modal is closed
            modalEl.addEventListener('hidden.bs.modal', function cleanup() {
                // Reset opening flag
                window.isOpeningMedicalHistory = false;
                
                // Clear button fix interval
                if (window.mhButtonFixInterval) {
                    clearInterval(window.mhButtonFixInterval);
                    window.mhButtonFixInterval = null;
                }
                
                // Remove approve/decline buttons
                const approveBtn = document.getElementById('viewMHApproveBtn');
                const declineBtn = document.getElementById('viewMHDeclineBtn');
                if (approveBtn) approveBtn.remove();
                if (declineBtn) declineBtn.remove();
                
                // Dispose of modal instance
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) {
                    modalInstance.dispose();
                }
                
                // Remove this listener to prevent duplicates
                modalEl.removeEventListener('hidden.bs.modal', cleanup);
            }, { once: true });
            
            // Show modal with proper error handling
            try {
                // Use Bootstrap's show method properly - ensure we have a valid modal instance
                if (modal && typeof modal.show === 'function') {
                    // Use setTimeout to ensure DOM is ready
                    setTimeout(() => {
                        try {
                            modal.show();
                        } catch (showError) {
                            console.warn('Modal.show() failed, trying fallback:', showError);
                            // Fallback to manual show
                            showModalManually(modalEl);
                        }
                    }, 50);
                } else {
                    // No valid modal instance, show manually
                    showModalManually(modalEl);
                }
            } catch (modalError) {
                console.error('Error showing modal:', modalError);
                // Try alternative method
                showModalManually(modalEl);
            }
            
            // Helper function to show modal manually
            function showModalManually(modalElement) {
                try {
                    modalElement.classList.add('show');
                    modalElement.style.display = 'block';
                    modalElement.setAttribute('aria-modal', 'true');
                    modalElement.setAttribute('aria-hidden', 'false');
                    modalElement.style.paddingRight = '0px';
                    document.body.classList.add('modal-open');
                    
                    // Add backdrop if it doesn't exist
                    if (!document.querySelector('.modal-backdrop')) {
                        const backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show';
                        document.body.appendChild(backdrop);
                    }
                } catch (manualError) {
                    console.error('Manual modal show also failed:', manualError);
                }
            }
            
            // First, check if donor is approved
            fetch(`../../assets/php_func/comprehensive_donor_details_api.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(donorData => {
                    if (donorData.error) {
                        console.error('Error fetching donor data:', donorData.error);
                        if (modalContent) {
                            modalContent.innerHTML = '<div class="alert alert-danger">Error loading donor details: ' + (donorData.error || 'Unknown error') + '</div>';
                        }
                        return;
                    }
                    
                    // Check if donor is approved
                    const eligibility = donorData.eligibility || {};
                    const eligibilityStatus = String(eligibility.status || '').toLowerCase();
                    const medicalHistory = donorData.medical_history || {};
                    const medicalApproval = String(medicalHistory.medical_approval || '').toLowerCase();
                    const screeningForm = donorData.screening_form || {};
                    const hasScreeningRecord = screeningForm && Object.keys(screeningForm).length > 0 && screeningForm.screening_id;
                    const isApproved = eligibilityStatus === 'approved' || eligibilityStatus === 'eligible' || 
                                     medicalApproval === 'approved';
                    
                    // Check if medical history is completed (has data but not approved)
                    const hasMedicalHistory = medicalHistory && Object.keys(medicalHistory).length > 0;
                    const isCompleted = hasMedicalHistory && medicalApproval !== 'approved' && medicalApproval !== 'declined';
                    
                    console.log('Donor approval status:', { eligibilityStatus, medicalApproval, isApproved, hasScreeningRecord, hasMedicalHistory, isCompleted });
                    
                    // Set view-only flag for approved donors
                    // For examination status: show form with navigation and approve/decline buttons
                    window.medicalHistoryViewOnly = isApproved;
                    // Store donor data for approve/decline buttons
                    window.currentViewMHDonorId = donorId;
                    window.currentViewMHScreeningRecord = hasScreeningRecord;
                    window.currentViewMHApproved = isApproved;
                    window.currentViewMHCompleted = isCompleted;
                    
                    // For examination status (has screening but not approved), use view_only=0 to show full form
                    // This allows navigation buttons and approve/decline buttons to work
                    // For completed status (has MH but not approved), also use view_only=0 to show approve/decline buttons
                    const viewOnlyParam = isApproved ? '1' : '0';
                    console.log('Loading MH form with view_only:', viewOnlyParam, 'isApproved:', isApproved, 'hasScreeningRecord:', hasScreeningRecord, 'isCompleted:', isCompleted);
                    
                    // Fetch the admin medical history content with view-only parameter
                    fetch(`../../src/views/forms/medical-history-modal-content-admin.php?donor_id=${encodeURIComponent(donorId)}&view_only=${viewOnlyParam}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.text();
                        })
                        .then(html => {
                            console.log('MH form HTML loaded, length:', html.length);
                            // Update modal content
                            if (modalContent) {
                                modalContent.innerHTML = html;
                                console.log('MH form content inserted into modal');
                                
                                // Execute any script tags in the loaded content
                                const scripts = modalContent.querySelectorAll('script');
                                console.log('Found', scripts.length, 'script tags in MH form');
                                scripts.forEach((script, index) => {
                                    try {
                                        // Skip empty scripts
                                        if (!script.textContent || script.textContent.trim() === '') {
                                            console.log(`Skipping empty script ${index + 1}`);
                                            return;
                                        }
                                        
                                        // Get script content
                                        const scriptContent = script.textContent.trim();
                                        
                                        // Create a new script element and execute it
                                        const newScript = document.createElement('script');
                                        newScript.type = script.type || 'text/javascript';
                                        
                                        // Use standard method to execute script
                                        try {
                                            newScript.textContent = scriptContent;
                                            // Append to body for execution
                                            const target = document.body || document.head;
                                            if (target) {
                                                target.appendChild(newScript);
                                                
                                                // Remove after execution
                                                setTimeout(() => {
                                                    try {
                                                        if (newScript.parentNode) {
                                                            newScript.parentNode.removeChild(newScript);
                                                        }
                                                    } catch (removeError) {
                                                        // Ignore removal errors
                                                    }
                                                }, 50);
                                                
                                                console.log(`Executed script ${index + 1} successfully`);
                                            } else {
                                                console.warn(`No target element found for script ${index + 1}`);
                                            }
                                        } catch (execError) {
                                            console.error(`Error executing script ${index + 1}:`, execError);
                                            // Skip this script and continue with others
                                        }
                                    } catch (e) {
                                        console.error(`Error processing script ${index + 1}:`, e);
                                        // Continue with other scripts even if one fails
                                    }
                                });
                                
                                // After loading, ensure view-only mode is applied only if approved
                                if (isApproved) {
                                    // Clear any existing interval (shouldn't exist for approved, but just in case)
                                    if (window.mhButtonFixInterval) {
                                        clearInterval(window.mhButtonFixInterval);
                                        window.mhButtonFixInterval = null;
                                    }
                                    
                                    setTimeout(() => {
                                        if (typeof window.mhApplyViewOnlyMode === 'function') {
                                            window.mhApplyViewOnlyMode();
                                        }
                                        
                                        // For approved donors, hide all action buttons and show only navigation
                                        const submitBtn = document.getElementById('modalSubmitButton');
                                        const approveBtn = document.getElementById('viewMHApproveBtn');
                                        const declineBtn = document.getElementById('viewMHDeclineBtn');
                                        
                                        if (submitBtn) {
                                            submitBtn.style.display = 'none';
                                            submitBtn.style.visibility = 'hidden';
                                        }
                                        if (approveBtn) {
                                            approveBtn.style.display = 'none';
                                            approveBtn.style.visibility = 'hidden';
                                        }
                                        if (declineBtn) {
                                            declineBtn.style.display = 'none';
                                            declineBtn.style.visibility = 'hidden';
                                        }
                                        
                                        // Ensure next button shows "Close" on last step for approved donors
                                        const nextBtn = document.getElementById('modalNextButton');
                                        const prevBtn = document.getElementById('modalPrevButton');
                                        if (nextBtn) {
                                            const form = document.getElementById('modalMedicalHistoryForm');
                                            const totalSteps = form ? form.querySelectorAll('.form-step').length : 6;
                                            const currentStep = form ? parseInt(form.querySelector('.form-step.active')?.getAttribute('data-step') || '1') : 1;
                                            
                                            // Always show navigation buttons
                                            nextBtn.style.display = 'inline-block';
                                            nextBtn.style.visibility = 'visible';
                                            
                                            if (currentStep === totalSteps) {
                                                nextBtn.innerHTML = '<i class="fas fa-times me-1"></i>Close';
                                                // Make it close the modal
                                                nextBtn.onclick = function() {
                                                    const modal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                                                    if (modal) {
                                                        modal.hide();
                                                    } else {
                                                        // Fallback: manual close
                                                        const modalEl = document.getElementById('medicalHistoryModal');
                                                        if (modalEl) {
                                                            modalEl.classList.remove('show');
                                                            modalEl.style.display = 'none';
                                                            document.body.classList.remove('modal-open');
                                                            const backdrop = document.querySelector('.modal-backdrop');
                                                            if (backdrop) backdrop.remove();
                                                        }
                                                    }
                                                };
                                            } else {
                                                nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                                            }
                                        }
                                        if (prevBtn) {
                                            prevBtn.style.display = 'inline-block';
                                            prevBtn.style.visibility = 'visible';
                                        }
                                        
                                        // Call updateStepDisplay to ensure view-only mode is applied
                                        if (window.updateStepDisplay) {
                                            window.updateStepDisplay();
                                        }
                                        
                                        console.log('✅ Applied view-only mode for approved donor');
                                    }, 300);
                                } else {
                                    // For non-approved donors, ensure form is interactive
                                    console.log('MH form loaded in interactive mode (not view-only)');
                                }
                                
                                // Add approve/decline buttons ONLY if initial screening is completed and donor is not approved
                                // Hide submit button and show approve/decline instead
                                // Keep next/prev buttons visible for traversal
                                if (hasScreeningRecord && !isApproved) {
                                    // Set flag to prevent next button from changing to "Submit"
                                    window.mhShowApproveDecline = true;
                                    
                                    // Function to ensure approve/decline buttons are created
                                    // COMPREHENSIVE DIAGNOSTIC AND FIX FUNCTION
                                    // Make it globally accessible
                                    window.ensureApproveDeclineButtons = () => {
                                        console.log('🔍 === COMPREHENSIVE BUTTON CREATION DIAGNOSTIC ===');
                                        
                                        // Step 1: Find the modal and content containers
                                        const modalEl = document.getElementById('medicalHistoryModal');
                                        const modalContent = document.getElementById('medicalHistoryModalContent');
                                        console.log('Modal element:', !!modalEl);
                                        console.log('Modal content element:', !!modalContent);
                                        
                                        if (!modalEl || !modalContent) {
                                            console.error('❌ Modal elements not found');
                                            return false;
                                        }
                                        
                                        // Step 2: Find modal footer - try all possible locations
                                        // CRITICAL: Must find the footer INSIDE the loaded content that has footer-left/footer-right
                                        let modalFooter = null;
                                        
                                        // First, try to find footer inside the loaded content that has footer-left/footer-right
                                        const allFootersInContent = modalContent.querySelectorAll('.modal-footer');
                                        console.log('Found', allFootersInContent.length, 'modal-footer elements in content');
                                        
                                        // Look for the footer that has footer-left or footer-right (the actual form footer)
                                        for (const footer of allFootersInContent) {
                                            // Skip confirmation modal footers (they have mhCustomConfirmYes button)
                                            if (footer.querySelector('#mhCustomConfirmYes')) {
                                                console.log('Skipping confirmation modal footer');
                                                continue;
                                            }
                                            // Check if this footer has footer-left or footer-right divs (the actual form footer)
                                            if (footer.querySelector('.footer-left') || footer.querySelector('.footer-right')) {
                                                modalFooter = footer;
                                                console.log('✅ Found medical history form footer with footer-left/footer-right');
                                                break;
                                            }
                                        }
                                        
                                        // If still not found, use the first footer that's not a confirmation modal
                                        if (!modalFooter && allFootersInContent.length > 0) {
                                            for (const footer of allFootersInContent) {
                                                if (!footer.querySelector('#mhCustomConfirmYes')) {
                                                    modalFooter = footer;
                                                    console.log('✅ Using first non-confirmation footer found in content');
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        if (modalFooter) {
                                            console.log('✅ Found modal footer');
                                        } else {
                                            console.error('❌ Could not find medical history form footer');
                                        }
                                        
                                        // Step 3: If footer doesn't exist, check the structure
                                        if (!modalFooter) {
                                            console.warn('⚠️ Modal footer not found, inspecting structure...');
                                            console.log('Modal content HTML length:', modalContent.innerHTML.length);
                                            console.log('Modal content contains "modal-footer":', modalContent.innerHTML.includes('modal-footer'));
                                            console.log('Modal content contains "footer-left":', modalContent.innerHTML.includes('footer-left'));
                                            
                                            // Check if footer is inside the content - look for the one with footer-left/footer-right
                                            const allFooters = modalContent.querySelectorAll('.modal-footer');
                                            console.log('Found', allFooters.length, 'modal-footer elements in content');
                                            
                                            // Find the footer that has footer-left or footer-right (the actual form footer)
                                            for (const footer of allFooters) {
                                                // Skip confirmation modal footers
                                                if (footer.querySelector('#mhCustomConfirmYes')) {
                                                    console.log('Skipping confirmation modal footer');
                                                    continue;
                                                }
                                                // Check if this footer has footer-left or footer-right (the actual form footer)
                                                if (footer.querySelector('.footer-left') || footer.querySelector('.footer-right')) {
                                                    modalFooter = footer;
                                                    console.log('✅ Found medical history form footer with footer-left/footer-right');
                                                    break;
                                                }
                                            }
                                            
                                            // If still not found, use the first one that's not a confirmation modal
                                            if (!modalFooter && allFooters.length > 0) {
                                                for (const footer of allFooters) {
                                                    if (!footer.querySelector('#mhCustomConfirmYes')) {
                                                        modalFooter = footer;
                                                        console.log('✅ Using first non-confirmation footer found in content');
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            if (!modalFooter) {
                                                // Footer doesn't exist - we need to check if the loaded HTML has it
                                                // The footer should be in the loaded HTML from medical-history-modal-content-admin.php
                                                console.error('❌ Modal footer not found in DOM. The loaded HTML may not include it.');
                                                return false;
                                            }
                                        }
                                        
                                        // Step 4: Inspect footer structure
                                        console.log('Modal footer element:', modalFooter);
                                        console.log('Modal footer classes:', modalFooter.className);
                                        console.log('Modal footer innerHTML length:', modalFooter.innerHTML.length);
                                        console.log('Modal footer innerHTML preview:', modalFooter.innerHTML.substring(0, 300));
                                        
                                        // Step 5: Find or create footer-left
                                        let footerLeft = modalFooter.querySelector('.footer-left');
                                        const footerRight = modalFooter.querySelector('.footer-right');
                                        
                                        console.log('Footer-left found:', !!footerLeft);
                                        console.log('Footer-right found:', !!footerRight);
                                        
                                        // If footer-left doesn't exist, create it
                                        if (!footerLeft) {
                                            console.log('⚠️ Footer-left not found, creating it...');
                                            footerLeft = document.createElement('div');
                                            footerLeft.className = 'footer-left';
                                            footerLeft.style.cssText = 'flex: 1; display: flex; gap: 10px; align-items: center;';
                                            
                                            // Insert at the beginning of modal-footer
                                            if (modalFooter.firstChild) {
                                                modalFooter.insertBefore(footerLeft, modalFooter.firstChild);
                                            } else {
                                                modalFooter.appendChild(footerLeft);
                                            }
                                            console.log('✅ Created footer-left div');
                                            console.log('Footer-left parent:', footerLeft.parentNode);
                                            console.log('Footer-left in DOM:', document.body.contains(footerLeft));
                                        }
                                        
                                        if (!footerLeft) {
                                            console.error('❌ Could not create footer-left div');
                                            return false;
                                        }
                                        
                                        // Step 6: Remove any existing buttons
                                        const existingApprove = document.getElementById('viewMHApproveBtn');
                                        const existingDecline = document.getElementById('viewMHDeclineBtn');
                                        console.log('Existing buttons - Approve:', !!existingApprove, 'Decline:', !!existingDecline);
                                        
                                        if (existingApprove) {
                                            console.log('Removing existing approve button from:', existingApprove.parentNode);
                                            existingApprove.remove();
                                        }
                                        if (existingDecline) {
                                            console.log('Removing existing decline button from:', existingDecline.parentNode);
                                            existingDecline.remove();
                                        }
                                        
                                        // Step 7: Create new buttons with full styling
                                        const approveBtn = document.createElement('button');
                                        approveBtn.type = 'button';
                                        approveBtn.className = 'btn btn-success me-2';
                                        approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve Medical History';
                                        approveBtn.id = 'viewMHApproveBtn';
                                        // Use inline styles with !important to override any CSS
                                        approveBtn.setAttribute('style', 'display: inline-block !important; visibility: visible !important; opacity: 1 !important; width: auto !important; height: auto !important; padding: 0.375rem 0.75rem !important;');
                                        
                                        const declineBtn = document.createElement('button');
                                        declineBtn.type = 'button';
                                        declineBtn.className = 'btn btn-danger me-2';
                                        declineBtn.innerHTML = '<i class="fas fa-times me-2"></i>Decline Medical History';
                                        declineBtn.id = 'viewMHDeclineBtn';
                                        // Use inline styles with !important to override any CSS
                                        declineBtn.setAttribute('style', 'display: inline-block !important; visibility: visible !important; opacity: 1 !important; width: auto !important; height: auto !important; padding: 0.375rem 0.75rem !important;');
                                        
                                        // Step 8: Ensure footer-left is visible and has proper layout
                                        const footerLeftComputed = window.getComputedStyle(footerLeft);
                                        if (footerLeftComputed.display === 'none' || footerLeft.offsetWidth === 0) {
                                            footerLeft.setAttribute('style', 'flex: 1; display: flex !important; gap: 10px; align-items: center; visibility: visible !important;');
                                            console.log('✅ Fixed footer-left visibility');
                                        }
                                        
                                        // Step 9: Insert buttons into footer-left
                                        footerLeft.appendChild(approveBtn);
                                        footerLeft.appendChild(declineBtn);
                                        console.log('✅ Inserted buttons into footer-left');
                                        
                                        // Step 10: Force a reflow to ensure buttons render
                                        void footerLeft.offsetHeight;
                                        
                                        // Step 11: Comprehensive verification
                                        const verifyApprove = document.getElementById('viewMHApproveBtn');
                                        const verifyDecline = document.getElementById('viewMHDeclineBtn');
                                        
                                        console.log('=== VERIFICATION ===');
                                        console.log('Approve button in DOM:', !!verifyApprove);
                                        console.log('Decline button in DOM:', !!verifyDecline);
                                        
                                        if (verifyApprove) {
                                            console.log('Approve button parent:', verifyApprove.parentNode);
                                            console.log('Approve button computed display:', window.getComputedStyle(verifyApprove).display);
                                            console.log('Approve button computed visibility:', window.getComputedStyle(verifyApprove).visibility);
                                            console.log('Approve button offsetWidth:', verifyApprove.offsetWidth);
                                            console.log('Approve button offsetHeight:', verifyApprove.offsetHeight);
                                            
                                            // If still zero width, try one more fix
                                            if (verifyApprove.offsetWidth === 0) {
                                                console.warn('⚠️ Approve button still has zero width, applying emergency fix...');
                                                verifyApprove.setAttribute('style', 'display: inline-block !important; visibility: visible !important; opacity: 1 !important; width: auto !important; min-width: 150px !important; height: auto !important; min-height: 38px !important; padding: 0.375rem 0.75rem !important; margin-right: 0.5rem !important;');
                                                void verifyApprove.offsetHeight; // Force reflow
                                            }
                                        }
                                        
                                        if (verifyDecline) {
                                            console.log('Decline button parent:', verifyDecline.parentNode);
                                            console.log('Decline button computed display:', window.getComputedStyle(verifyDecline).display);
                                            console.log('Decline button computed visibility:', window.getComputedStyle(verifyDecline).visibility);
                                            console.log('Decline button offsetWidth:', verifyDecline.offsetWidth);
                                            console.log('Decline button offsetHeight:', verifyDecline.offsetHeight);
                                            
                                            // If still zero width, try one more fix
                                            if (verifyDecline.offsetWidth === 0) {
                                                console.warn('⚠️ Decline button still has zero width, applying emergency fix...');
                                                verifyDecline.setAttribute('style', 'display: inline-block !important; visibility: visible !important; opacity: 1 !important; width: auto !important; min-width: 150px !important; height: auto !important; min-height: 38px !important; padding: 0.375rem 0.75rem !important; margin-right: 0.5rem !important;');
                                                void verifyDecline.offsetHeight; // Force reflow
                                            }
                                        }
                                        
                                        // Step 10: Bind event handlers
                                        approveBtn.addEventListener('click', function(e) {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            
                                            // Call the handler - it will handle confirmation, API call, and modal closing
                                            if (typeof handleMedicalHistoryApprovalFromInterviewer === 'function') {
                                                handleMedicalHistoryApprovalFromInterviewer(donorId, 'approve');
                                            } else {
                                                console.error('handleMedicalHistoryApprovalFromInterviewer function not found');
                                                if (window.adminModal && window.adminModal.alert) {
                                                    window.adminModal.alert('Error: Approval function not available. Please refresh the page.');
                                                } else {
                                                    console.error('Admin modal not available');
                                                }
                                            }
                                        });
                                        
                                        declineBtn.addEventListener('click', function(e) {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            
                                            // Call the handler - it will handle prompt, API call, and modal closing
                                            if (typeof handleMedicalHistoryApprovalFromInterviewer === 'function') {
                                                handleMedicalHistoryApprovalFromInterviewer(donorId, 'decline');
                                            } else {
                                                console.error('handleMedicalHistoryApprovalFromInterviewer function not found');
                                                if (window.adminModal && window.adminModal.alert) {
                                                    window.adminModal.alert('Error: Decline function not available. Please refresh the page.');
                                                } else {
                                                    console.error('Admin modal not available');
                                                }
                                            }
                                        });
                                        
                                        console.log('✅ === BUTTON CREATION COMPLETE ===');
                                        return true;
                                    };
                                    
                                    // Try to create buttons after form is loaded - use multiple retries
                                    let retryCount = 0;
                                    const maxRetries = 5;
                                    const tryCreateButtons = () => {
                                        retryCount++;
                                        console.log(`🔄 Attempt ${retryCount} to create approve/decline buttons...`);
                                        if (!ensureApproveDeclineButtons() && retryCount < maxRetries) {
                                            setTimeout(tryCreateButtons, 300);
                                        } else if (retryCount >= maxRetries) {
                                            console.error('❌ Failed to create approve/decline buttons after', maxRetries, 'attempts');
                                        }
                                    };
                                    
                                    // Start trying after form content is loaded
                                    setTimeout(() => {
                                        tryCreateButtons();
                                        
                                        const modalFooter = document.querySelector('#medicalHistoryModal .modal-footer');
                                        if (modalFooter) {
                                            // Hide submit button
                                            const submitButton = document.getElementById('modalSubmitButton');
                                            if (submitButton) {
                                                submitButton.style.display = 'none';
                                                submitButton.style.visibility = 'hidden';
                                                console.log('✅ Hid submit button');
                                            }
                                            
                                            // Override updateStepDisplay to keep next button as "Next" not "Submit"
                                            const originalUpdateStepDisplay = window.updateStepDisplay;
                                            if (originalUpdateStepDisplay) {
                                                window.updateStepDisplay = function() {
                                                    // Call original function first
                                                    originalUpdateStepDisplay();
                                                    
                                                    // Override: keep next button as "Next" and ensure approve/decline are visible
                                                    const nextBtn = document.getElementById('modalNextButton');
                                                    const prevBtn = document.getElementById('modalPrevButton');
                                                    
                                                    if (nextBtn) {
                                                        // Always keep as "Next" button, not "Submit" when approve/decline should show
                                                        const currentStep = document.querySelector('.form-step.active');
                                                        const totalSteps = document.querySelectorAll('.form-step').length;
                                                        const isLastStep = currentStep && parseInt(currentStep.getAttribute('data-step')) === totalSteps;
                                                        
                                                        if (isLastStep) {
                                                            // On last step, keep as "Next" (not "Submit") since we have approve/decline buttons
                                                            nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                                                        } else {
                                                            // On other steps, keep as "Next"
                                                            nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                                                        }
                                                        nextBtn.style.display = 'inline-block';
                                                        nextBtn.style.visibility = 'visible';
                                                    }
                                                    if (prevBtn) {
                                                        prevBtn.style.display = 'inline-block';
                                                        prevBtn.style.visibility = 'visible';
                                                    }
                                                    
                                                    // Ensure submit button stays hidden
                                                    const submitBtn = document.getElementById('modalSubmitButton');
                                                    if (submitBtn) {
                                                        submitBtn.style.display = 'none';
                                                        submitBtn.style.visibility = 'hidden';
                                                    }
                                                    
                                                    // Ensure approve/decline buttons are visible
                                                    const approveBtn = document.getElementById('viewMHApproveBtn');
                                                    const declineBtn = document.getElementById('viewMHDeclineBtn');
                                                    if (approveBtn) {
                                                        approveBtn.style.display = 'inline-block';
                                                        approveBtn.style.visibility = 'visible';
                                                    }
                                                    if (declineBtn) {
                                                        declineBtn.style.display = 'inline-block';
                                                        declineBtn.style.visibility = 'visible';
                                                    }
                                                };
                                                console.log('✅ Overrode updateStepDisplay to keep next button and show approve/decline');
                                            }
                                            
                                            
                                            // Keep next/prev buttons visible - don't hide them
                                            const nextButton = document.getElementById('modalNextButton');
                                            const prevButton = document.getElementById('modalPrevButton');
                                            // Ensure next/prev buttons are visible
                                            if (nextButton) {
                                                nextButton.style.display = 'inline-block';
                                                nextButton.style.visibility = 'visible';
                                                // Keep as "Next" button, not "Submit"
                                                nextButton.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                                            }
                                            if (prevButton) {
                                                prevButton.style.display = 'inline-block';
                                                prevButton.style.visibility = 'visible';
                                            }
                                            
                                            // Buttons are now created by ensureApproveDeclineButtons function above
                                            // Just ensure they're visible if they exist
                                            const approveBtn = document.getElementById('viewMHApproveBtn');
                                            const declineBtn = document.getElementById('viewMHDeclineBtn');
                                            if (approveBtn) {
                                                approveBtn.style.display = 'inline-block';
                                                approveBtn.style.visibility = 'visible';
                                            }
                                            if (declineBtn) {
                                                declineBtn.style.display = 'inline-block';
                                                declineBtn.style.visibility = 'visible';
                                            }
                                        }
                                    }, 500); // Increased delay to ensure form is fully loaded
                                } else {
                                    // Ensure next/prev buttons are visible even if no approve/decline needed
                                    setTimeout(() => {
                                        const nextButton = document.getElementById('modalNextButton');
                                        const prevButton = document.getElementById('modalPrevButton');
                                        if (nextButton) nextButton.style.display = 'inline-block';
                                        if (prevButton) prevButton.style.display = 'inline-block';
                                    }, 300);
                                }
                                
                                // Call the admin generator to initialize the form
                                if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                                    setTimeout(() => {
                                        console.log('Calling generateAdminMedicalHistoryQuestions...');
                                        try {
                                            window.generateAdminMedicalHistoryQuestions();
                                            console.log('generateAdminMedicalHistoryQuestions called successfully');
                                            
                                            // After form is generated, ensure buttons are correct
                                            setTimeout(() => {
                                                const form = document.getElementById('modalMedicalHistoryForm');
                                                const nextBtn = document.getElementById('modalNextButton');
                                                const prevBtn = document.getElementById('modalPrevButton');
                                                const submitBtn = document.getElementById('modalSubmitButton');
                                                
                                                if (hasScreeningRecord && !isApproved) {
                                                    // For non-approved with screening: hide submit, show approve/decline, keep next as "Next"
                                                    if (submitBtn) {
                                                        submitBtn.style.display = 'none';
                                                        submitBtn.style.visibility = 'hidden';
                                                    }
                                                    
                                                    // Force next button to stay as "Next" (not "Submit")
                                                    if (nextBtn) {
                                                        nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                                                        nextBtn.style.display = 'inline-block';
                                                        nextBtn.style.visibility = 'visible';
                                                    }
                                                    
                                                    // CRITICAL: Re-create approve/decline buttons after form generation
                                                    // Use the same comprehensive diagnostic function
                                                    console.log('🔄 Re-creating approve/decline buttons after form generation...');
                                                    // Reuse the same comprehensive function (now globally accessible)
                                                    if (window.ensureApproveDeclineButtons) {
                                                        if (!window.ensureApproveDeclineButtons()) {
                                                            setTimeout(() => {
                                                                if (window.ensureApproveDeclineButtons && !window.ensureApproveDeclineButtons()) {
                                                                    console.error('❌ Failed to create buttons after form generation');
                                                                }
                                                            }, 300);
                                                        }
                                                    } else {
                                                        console.error('❌ ensureApproveDeclineButtons function not available');
                                                    }
                                                    
                                                    // Also ensure buttons are visible if they already exist
                                                    const approveBtn = document.getElementById('viewMHApproveBtn');
                                                    const declineBtn = document.getElementById('viewMHDeclineBtn');
                                                    if (approveBtn) {
                                                        approveBtn.style.display = 'inline-block';
                                                        approveBtn.style.visibility = 'visible';
                                                    }
                                                    if (declineBtn) {
                                                        declineBtn.style.display = 'inline-block';
                                                        declineBtn.style.visibility = 'visible';
                                                    }
                                                    
                                                    // Set up interval to continuously check and fix buttons
                                                    // This ensures buttons stay correct even when updateStepDisplay is called
                                                    // ONLY for non-approved donors
                                                    if (!window.mhButtonFixInterval && hasScreeningRecord && !isApproved) {
                                                        window.mhButtonFixInterval = setInterval(() => {
                                                            const nextBtn = document.getElementById('modalNextButton');
                                                            const submitBtn = document.getElementById('modalSubmitButton');
                                                            const approveBtn = document.getElementById('viewMHApproveBtn');
                                                            const declineBtn = document.getElementById('viewMHDeclineBtn');
                                                            
                                                            // Only run for non-approved donors
                                                            if (hasScreeningRecord && !isApproved) {
                                                                // Keep next as "Next", not "Submit"
                                                                if (nextBtn && nextBtn.innerHTML.includes('Submit')) {
                                                                    nextBtn.innerHTML = 'Next <i class="fas fa-arrow-right ms-1"></i>';
                                                                }
                                                                // Keep submit hidden
                                                                if (submitBtn && submitBtn.style.display !== 'none') {
                                                                    submitBtn.style.display = 'none';
                                                                    submitBtn.style.visibility = 'hidden';
                                                                }
                                                                // CRITICAL: If buttons don't exist, recreate them
                                                                if (!approveBtn || !declineBtn) {
                                                                    console.warn('⚠️ Buttons missing in interval check, recreating...');
                                                                    if (window.ensureApproveDeclineButtons) {
                                                                        window.ensureApproveDeclineButtons();
                                                                    }
                                                                    return;
                                                                }
                                                                
                                                                // Keep approve/decline visible and ensure they're in the right place
                                                                const computedDisplayApprove = window.getComputedStyle(approveBtn).display;
                                                                const computedDisplayDecline = window.getComputedStyle(declineBtn).display;
                                                                
                                                                // Find the correct footer-left (exclude confirmation modals)
                                                                let footerLeft = null;
                                                                const allFooterLefts = document.querySelectorAll('.footer-left');
                                                                for (const fl of allFooterLefts) {
                                                                    // Check if this footer-left is in the medical history modal content
                                                                    const modalContent = document.getElementById('medicalHistoryModalContent');
                                                                    if (modalContent && (modalContent.contains(fl) || fl.closest('#medicalHistoryModalContent'))) {
                                                                        // Make sure it's not in a confirmation modal
                                                                        if (!fl.closest('#mhCustomConfirmModal')) {
                                                                            footerLeft = fl;
                                                                            break;
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                // If buttons have zero dimensions, they're likely in wrong place or hidden
                                                                const needsFix = (computedDisplayApprove === 'none' || approveBtn.offsetWidth === 0 || 
                                                                                computedDisplayDecline === 'none' || declineBtn.offsetWidth === 0);
                                                                
                                                                if (needsFix && footerLeft) {
                                                                    // Ensure buttons are in footer-left
                                                                    if (approveBtn.parentNode !== footerLeft) {
                                                                        footerLeft.appendChild(approveBtn);
                                                                    }
                                                                    if (declineBtn.parentNode !== footerLeft) {
                                                                        footerLeft.appendChild(declineBtn);
                                                                    }
                                                                    
                                                                    // Force visibility with inline styles
                                                                    approveBtn.setAttribute('style', 'display: inline-block !important; visibility: visible !important; opacity: 1 !important; width: auto !important; height: auto !important; padding: 0.375rem 0.75rem !important; margin-right: 0.5rem !important;');
                                                                    declineBtn.setAttribute('style', 'display: inline-block !important; visibility: visible !important; opacity: 1 !important; width: auto !important; height: auto !important; padding: 0.375rem 0.75rem !important; margin-right: 0.5rem !important;');
                                                                    
                                                                    // Ensure footer-left is visible and has proper layout
                                                                    const footerLeftStyle = window.getComputedStyle(footerLeft);
                                                                    if (footerLeftStyle.display === 'none' || footerLeft.offsetWidth === 0) {
                                                                        footerLeft.setAttribute('style', 'flex: 1; display: flex !important; gap: 10px; align-items: center; visibility: visible !important;');
                                                                    }
                                                                    
                                                                    // Only log once per fix attempt, not continuously
                                                                    if (!window._mhButtonsFixedLogged) {
                                                                        console.log('✅ Fixed approve/decline button visibility and positioning');
                                                                        window._mhButtonsFixedLogged = true;
                                                                        setTimeout(() => { window._mhButtonsFixedLogged = false; }, 2000);
                                                                    }
                                                                }
                                                            }
                                                        }, 200);
                                                        
                                                        // Clear interval when modal is closed
                                                        const modalEl = document.getElementById('medicalHistoryModal');
                                                        if (modalEl) {
                                                            modalEl.addEventListener('hidden.bs.modal', function() {
                                                                if (window.mhButtonFixInterval) {
                                                                    clearInterval(window.mhButtonFixInterval);
                                                                    window.mhButtonFixInterval = null;
                                                                }
                                                            }, { once: true });
                                                        }
                                                    }
                                                    
                                                    // Call updateStepDisplay to refresh, but our override will keep next as "Next"
                                                    if (window.updateStepDisplay) {
                                                        window.updateStepDisplay();
                                                    }
                                                    
                                                    console.log('✅ Ensured approve/decline buttons visible and submit hidden');
                                                } else if (isApproved) {
                                                    // For approved: hide all action buttons, show only navigation
                                                    if (submitBtn) submitBtn.style.display = 'none';
                                                    const approveBtn = document.getElementById('viewMHApproveBtn');
                                                    const declineBtn = document.getElementById('viewMHDeclineBtn');
                                                    if (approveBtn) approveBtn.style.display = 'none';
                                                    if (declineBtn) declineBtn.style.display = 'none';
                                                    
                                                    // On last step, show "Close" button
                                                    if (nextBtn && form) {
                                                        const totalSteps = form.querySelectorAll('.form-step').length;
                                                        const currentStep = parseInt(form.querySelector('.form-step.active')?.getAttribute('data-step') || '1');
                                                        if (currentStep === totalSteps) {
                                                            nextBtn.innerHTML = '<i class="fas fa-times me-1"></i>Close';
                                                            nextBtn.onclick = function() {
                                                                const modal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                                                                if (modal) modal.hide();
                                                            };
                                                        }
                                                    }
                                                    
                                                    // Call updateStepDisplay to refresh view-only mode
                                                    if (window.updateStepDisplay) {
                                                        window.updateStepDisplay();
                                                    }
                                                    
                                                    console.log('✅ Applied view-only mode - all action buttons hidden');
                                                }
                                                
                                                // Always ensure navigation buttons are visible
                                                if (nextBtn) {
                                                    nextBtn.style.display = 'inline-block';
                                                    nextBtn.style.visibility = 'visible';
                                                }
                                                if (prevBtn) {
                                                    prevBtn.style.display = 'inline-block';
                                                    prevBtn.style.visibility = 'visible';
                                                }
                                            }, 500);
                                        } catch (e) {
                                            console.error('Error calling generateAdminMedicalHistoryQuestions:', e);
                                        }
                                    }, 300);
                                } else {
                                    console.warn('generateAdminMedicalHistoryQuestions function not found');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error loading medical history content:', error);
                            if (modalContent) {
                                modalContent.innerHTML = '<div class="alert alert-danger">Error loading medical history. Please try again.</div>';
                            }
                        });
                })
                .catch(error => {
                    console.error('Error fetching donor details:', error);
                    const errorMessage = error && error.message ? error.message : (error && error.toString ? error.toString() : 'Unknown error');
                    console.error('Full error details:', error);
                    if (modalContent) {
                        modalContent.innerHTML = '<div class="alert alert-danger m-4">Error loading donor details: ' + errorMessage + '<br><small>Please try again or refresh the page.</small></div>';
                    } else {
                        if (window.adminModal && window.adminModal.alert) {
                            window.adminModal.alert('Error loading donor details: ' + errorMessage + '. Please try again.');
                        } else {
                            console.error('Admin modal not available');
                        }
                    }
                });
        };
        
        window.editInitialScreening = function(donorId) {
            console.log('Editing initial screening for donor:', donorId);
            // Store the donor ID for the workflow
            window.currentInterviewerDonorId = donorId;
            // Open the screening form directly
            openScreeningFormForInterviewer(donorId);
        };
        window.editPhysicianWorkflow = function(donorId) {
            console.log('Editing physician workflow for donor:', donorId);
            // Check if this is a new or returning donor
            // For now, we'll check if there's existing medical history data
            const isReturningDonor = checkIfReturningDonor(donorId);
            if (isReturningDonor) {
                // For returning donors, show approval status first
                showReturningDonorApprovalStatus(donorId);
            } else {
                // For new donors, show medical history approval first
                showMedicalHistoryApproval(donorId);
            }
        };
        // Open interviewer Medical History modal and rebind its footer to Approve/Decline flow
        window.openPhysicianMH = function(donorId) {
            try {
                if (!donorId) return;
                window.currentInterviewerDonorId = donorId;
                // Use existing interviewer entry to launch MH modal
                if (typeof window.editMedicalHistory === 'function') {
                    window.editMedicalHistory(donorId);
                } else {
                    // Fallback: open admin MH loader
                    if (typeof window.openMedicalreviewapproval === 'function') {
                        window.openMedicalreviewapproval({ donor_id: donorId });
                    }
                }
                // After content loads, hide submit/next and add decision buttons
                const rebind = function(){
                    try {
                        const container = document.getElementById('medicalHistoryModalAdminContent') || document.getElementById('medicalHistoryModalContent');
                        if (!container) { setTimeout(rebind, 200); return; }
                        const nextBtn = container.querySelector('#nextButton');
                        const prevBtn = container.querySelector('#prevButton');
                        if (nextBtn) nextBtn.style.display = 'none';
                        if (prevBtn) prevBtn.style.display = 'none';
                        let footerHost = container.querySelector('#physician-mh-actions');
                        if (!footerHost) {
                            footerHost = document.createElement('div');
                            footerHost.id = 'physician-mh-actions';
                            footerHost.className = 'd-flex justify-content-end gap-2 mt-3';
                            container.appendChild(footerHost);
                        }
                        footerHost.innerHTML = '';
                        const declineBtn = document.createElement('button');
                        declineBtn.type = 'button';
                        declineBtn.className = 'btn btn-danger';
                        declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline';
                        declineBtn.onclick = function(){ if (typeof showMedicalHistoryDeclineModal === 'function') showMedicalHistoryDeclineModal(donorId); };
                        const approveBtn = document.createElement('button');
                        approveBtn.type = 'button';
                        approveBtn.className = 'btn btn-success';
                        approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve';
                        approveBtn.onclick = function(){ physicianApproveMedicalHistory(donorId); };
                        footerHost.appendChild(declineBtn);
                        footerHost.appendChild(approveBtn);
                    } catch(_) {}
                };
                setTimeout(rebind, 400);
            } catch(_) {}
        };
        // Compact Medical History preview (physician) using the existing admin modal container
        window.openPhysicianMedicalPreview = function(donorId){
            try {
                if (!donorId) return;
                // Reuse the admin MH modal shell to avoid duplicate IDs and layout conflicts
                // Also hide the Donor Details/Profile modal first to prevent stacking/layout issues
                try { const dd = bootstrap.Modal.getInstance(document.getElementById('donorDetailsModal')); if (dd) dd.hide(); } catch(_) {}
                try {
                    const dp = document.getElementById('donorProfileModal');
                    if (dp) {
                        const inst = bootstrap.Modal.getInstance(dp) || new bootstrap.Modal(dp);
                        try { inst.hide(); } catch(_) {}
                        try { dp.classList.remove('show'); dp.style.display='none'; dp.setAttribute('aria-hidden','true'); } catch(_) {}
                    }
                } catch(_) {}
                // First, check current medical_approval to decide which UI to show
                (async () => {
                    // Default to unknown; only treat as Approved when explicitly returned
                    let status = '';
                    try {
                        // Use the correct API for fetching medical history info
                        const vr = await fetch(`../../assets/php_func/fetch_medical_history_info.php?donor_id=${encodeURIComponent(donorId)}`);
                        if (vr && vr.ok) {
                            const vj = await vr.json().catch(()=>null);
                            const mh = vj && (vj.medical_history || vj.data || vj);
                            const val = mh && (mh.medical_approval || mh.status || '');
                            status = String(val || '').trim().toLowerCase();
                        }
                    } catch(_) { status = ''; }
                    if (!status || status === 'pending' || status === '-') {
                        // Not yet decided -> show MH form preview for decision
                        return renderPhysicianMHPreview(donorId);
                    }
                    if (status === 'approved') {
                        // Already approved -> proceed directly without showing success modal
                        return proceedToPE(donorId);
                    }
                    // Declined or any other value (e.g., 'not approved', 'not approve', 'disapproved')
                    try { const declined = document.getElementById('medicalHistoryDeclinedModal'); if (declined) (new bootstrap.Modal(declined)).show(); } catch(_) {}
                    try { setTimeout(()=>window.location.reload(), 800); } catch(_) {}
                })();
                function renderPhysicianMHPreview(donorId){
                const modalEl = document.getElementById('medicalHistoryModalAdmin');
                const contentEl = document.getElementById('medicalHistoryModalAdminContent');
                if (!modalEl || !contentEl) return;
                // Clean up any existing modal state to avoid conflicts
                try {
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow='';
                    document.body.style.paddingRight='';
                } catch(_) {}
                // Apply proper modal stacking (dynamic z-index calculation)
                if (typeof window.applyModalStacking === 'function') {
                    window.applyModalStacking(modalEl);
                }
                const bs = new bootstrap.Modal(modalEl, { backdrop: false, keyboard: true, focus: true });
                bs.show();
                // Force visibility in case a global CSS sets .modal { visibility:hidden }
                try {
                    modalEl.style.visibility = 'visible';
                    modalEl.style.opacity = '1';
                    modalEl.removeAttribute('aria-hidden');
                    modalEl.classList.add('show');
                } catch(_) {}
                // Re-assert after Bootstrap transition
                setTimeout(() => { try { modalEl.style.visibility = 'visible'; modalEl.style.opacity = '1'; } catch(_) {} }, 50);
                // Rely on Bootstrap to manage backdrops.
                contentEl.innerHTML = '<div class="d-flex justify-content-center my-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                // Load admin MH form content
                fetch(`../../src/views/forms/medical-history-modal-content-admin.php?donor_id=${encodeURIComponent(donorId)}`)
                    .then(r => r.ok ? r.text() : Promise.reject(new Error(`HTTP ${r.status}`)))
                    .then(html => {
                        contentEl.innerHTML = html;
                        // Execute any script tags embedded in the loaded content so questions/data render
                        try {
                            const scripts = contentEl.querySelectorAll('script');
                            scripts.forEach((script, index) => {
                                try {
                                    console.log(`Executing script ${index + 1}:`, script.type || 'inline');
                                    // Create a new script element and execute it
                                    const newScript = document.createElement('script');
                                    newScript.textContent = script.textContent;
                                    newScript.type = script.type || 'text/javascript';
                                    document.head.appendChild(newScript);
                                    newScript.remove(); // Remove after execution
                                    console.log(`Script ${index + 1} executed successfully`);
                                } catch (e) {
                                    console.log('Script execution error:', e);
                                }
                            });
                        } catch(_) {}
                        // If the content relies on a generator, call it
                        try {
                            if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                                window.generateAdminMedicalHistoryQuestions();
                            } else if (typeof window.generateMedicalHistoryQuestions === 'function') {
                                window.generateMedicalHistoryQuestions();
                            }
                        } catch(_) {}
                        // Hide Edit/Next controls from interviewer UI for physician preview
                        try {
                            const nextBtn = contentEl.querySelector('#modalNextButton') || contentEl.querySelector('#nextButton');
                            const prevBtn = contentEl.querySelector('#modalPrevButton') || contentEl.querySelector('#prevButton');
                            if (nextBtn) nextBtn.style.display = 'none';
                            if (prevBtn) prevBtn.style.display = 'none';
                            contentEl.querySelectorAll('button').forEach(btn => {
                                const t = (btn.textContent || '').trim().toLowerCase();
                                if (t === 'edit' || t === 'next' || t.startsWith('next')) { btn.style.display = 'none'; }
                            });
                        } catch(_) {}
                        // Install decision buttons in the dedicated modal footer to avoid layout shifts
                        const footerHost = document.getElementById('medicalHistoryModalAdminFooter');
                        if (footerHost) footerHost.innerHTML = '';
                        const declineBtn = document.createElement('button');
                        declineBtn.type = 'button';
                        declineBtn.className = 'btn btn-danger';
                        declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline';
                        declineBtn.onclick = function(){
                            try {
                                const modal = document.getElementById('medicalHistoryDeclineModal');
                                if (modal) {
                                    const bm = new bootstrap.Modal(modal);
                                    bm.show();
                                    const submit = document.getElementById('submitDeclineBtn');
                                    if (submit) {
                                        const handler = function(){
                                            const reasonEl = document.getElementById('declineReason');
                                            const reason = reasonEl ? reasonEl.value : '';
                                            physicianDeclineMedicalHistory(donorId, reason);
                                            submit.removeEventListener('click', handler);
                                        };
                                        submit.addEventListener('click', handler);
                                    }
                                    return;
                                }
                            } catch(_) {}
                            physicianDeclineMedicalHistory(donorId);
                        };
                        const approveBtn = document.createElement('button');
                        approveBtn.type = 'button';
                        approveBtn.className = 'btn btn-success';
                        approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve';
                        approveBtn.onclick = function(){ physicianApproveMedicalHistory(donorId); };
                        if (footerHost) { footerHost.appendChild(declineBtn); footerHost.appendChild(approveBtn); }
                    })
                    .catch(err => {
                        contentEl.innerHTML = `<div class="alert alert-danger">Failed to load Medical History: ${err.message}</div>`;
                    });
                }
            } catch(_) {}
        };
        // Physician-specific API actions (clean, no extra confirmation)
        async function physicianApproveMedicalHistory(donorId){
            try {
                if (!donorId) return;
                const formData = new FormData();
                formData.append('action', 'approve_medical_history');
                formData.append('donor_id', donorId);
                const res = await fetch('../../assets/php_func/admin/process_medical_history_approval_admin.php', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json().catch(() => null);
                if (!json || !json.success) throw new Error((json && json.message) || 'Approval failed');
                // Verify DB state then show appropriate modal, after hiding MH preview
                let approved = true;
                try {
                    const verify = await fetch(`../../assets/php_func/get_medical_history.php?donor_id=${encodeURIComponent(donorId)}`);
                    if (verify && verify.ok) {
                        const vjson = await verify.json().catch(()=>null);
                        const mh = vjson && (vjson.medical_history || vjson.data || vjson);
                        const val = mh && (mh.medical_approval || mh.status);
                        approved = String(val || '').toLowerCase() === 'approved';
                    }
                } catch(_) { approved = true; }
                hideAdminMHModalThen(function(){
                    try {
                        if (approved) {
                            // Refresh donor details and proceed - no success modal
                            const donorModal = document.getElementById('donorModal');
                            if (donorModal && donorModal.classList.contains('show')) {
                                const eligibilityId = window.currentDetailsEligibilityId || window.currentEligibilityId || `pending_${donorId}`;
                                if (typeof AdminDonorModal !== 'undefined' && AdminDonorModal && AdminDonorModal.fetchDonorDetails) {
                                    setTimeout(() => {
                                        AdminDonorModal.fetchDonorDetails(donorId, eligibilityId);
                                    }, 500);
                                } else if (typeof window.fetchDonorDetails === 'function') {
                                    setTimeout(() => {
                                        window.fetchDonorDetails(donorId, eligibilityId);
                                    }, 500);
                                }
                            }
                            // Proceed directly without showing success modal
                            proceedToPE(donorId);
                        } else {
                            const declined = document.getElementById('medicalHistoryDeclinedModal');
                            if (declined) { (new bootstrap.Modal(declined)).show(); return; }
                        }
                    } catch(_) { proceedToPE(donorId); }
                });
            } catch(e) {
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Failed to approve medical history: ' + e.message);
                } else {
                    console.error('Admin modal not available');
                }
            }
        }
        async function physicianDeclineMedicalHistory(donorId){
            try {
                if (!donorId) return;
                if (!window.adminModal || !window.adminModal.prompt) {
                    console.error('Admin modal prompt not available');
                    return;
                }
                const reason = await window.adminModal.prompt('Enter reason for decline:', {
                    title: 'Decline Reason Required',
                    placeholder: 'Enter reason for decline'
                });
                if (!reason) return;
                const formData = new FormData();
                formData.append('action', 'decline_medical_history');
                formData.append('donor_id', donorId);
                formData.append('decline_reason', reason);
                const res = await fetch('../../assets/php_func/admin/process_medical_history_approval_admin.php', {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json().catch(() => null);
                if (!json || !json.success) throw new Error((json && json.message) || 'Decline failed');
                hideAdminMHModalThen(function(){
                    try {
                        const declined = document.getElementById('medicalHistoryDeclinedModal');
                        if (declined) { (new bootstrap.Modal(declined)).show(); return; }
                    } catch(_) {}
                    try { window.location.reload(); } catch(_) {}
                });
            } catch(e) {
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Failed to decline medical history: ' + e.message);
                } else {
                    console.error('Admin modal not available');
                }
            }
        }
        // UI helper: hide admin MH modal and then run a callback
        function hideAdminMHModalThen(cb){
            try {
                const el = document.getElementById('medicalHistoryModalAdmin');
                const inst = el ? bootstrap.Modal.getInstance(el) : null;
                if (inst) {
                    el.addEventListener('hidden.bs.modal', function h(){
                        try { document.body.classList.remove('modal-open'); document.body.style.overflow=''; document.body.style.paddingRight=''; } catch(_) {}
                        el.removeEventListener('hidden.bs.modal', h);
                        if (typeof cb === 'function') cb();
                    }, { once: true });
                    inst.hide();
                } else {
                    try { document.body.classList.remove('modal-open'); document.body.style.overflow=''; document.body.style.paddingRight=''; } catch(_) {}
                    if (typeof cb === 'function') cb();
                }
            } catch(_) { if (typeof cb === 'function') cb(); }
        }
        
        // Unified function to close medical history modals
        function closeMedicalHistoryModalUnified() {
            const staffModal = document.getElementById('medicalHistoryModal');
            const adminModal = document.getElementById('medicalHistoryModalAdmin');
            const modalElement = (adminModal && adminModal.classList.contains('show')) ? adminModal : 
                                (staffModal && staffModal.classList.contains('show')) ? staffModal : 
                                adminModal || staffModal;
            
            if (modalElement) {
                // Force close immediately first
                modalElement.classList.remove('show');
                modalElement.style.display = 'none';
                modalElement.setAttribute('aria-hidden', 'true');
                modalElement.removeAttribute('aria-modal');
                
                // Then call Bootstrap hide
                const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
                modalInstance.hide();
                
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                
                const otherModals = document.querySelectorAll('.modal.show, .medical-history-modal.show');
                if (otherModals.length === 0) {
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            }
        }
        
        // Listen for custom close event from MH modal content
        window.addEventListener('closeMedicalHistoryModal', function(event) {
            console.log('Received close event for MH modal:', event.detail);
            closeMedicalHistoryModalUnified();
        });
        
        // Proceed to Physical Examination (admin path)
        function proceedToPE(donorId){
            try {
                // Try to open the physical examination modal if it exists
                if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                    const screeningData = {
                        donor_form_id: donorId,
                        screening_id: null
                    };
                    window.physicalExaminationModalAdmin.openModal(screeningData);
                    return;
                }
            } catch (e) { console.warn('physicalExaminationModalAdmin.openModal not available, falling back', e); }
            
            // Fallback: try to show the modal directly
            try {
                const modalElement = document.getElementById('physicalExaminationModalAdmin');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                    return;
                }
            } catch (e) { console.warn('Failed to show physical examination modal directly', e); }
            
            // Final fallback: redirect to form page
            window.location.href = `../../src/views/forms/physical-examination-form-admin.php?donor_id=${encodeURIComponent(donorId)}`;
        }
        // Function to check if donor is returning
        function checkIfReturningDonor(donorId) {
            // This would typically check the database for existing medical history
            // For now, we'll use a simple check based on donor ID pattern
            // In real implementation, you'd make an API call
            return donorId && donorId.toString().length > 3; // Simple heuristic
        }
        // Function to show returning donor approval status
        function showReturningDonorApprovalStatus(donorId) {
            // Create a custom modal to show approval status
            const approvalStatusModal = document.createElement('div');
            approvalStatusModal.className = 'modal fade';
            approvalStatusModal.id = 'returningDonorApprovalModal';
            approvalStatusModal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-check-circle me-2"></i>
                                Donor Previously Approved
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-user-check text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="mb-3">Medical History Previously Approved</h5>
                            <p class="text-muted mb-4">
                                This returning donor has already been approved for medical history.
                                You can now proceed to the physical examination.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success" id="proceedToPhysicalExamReturning">
                                <i class="fas fa-arrow-right me-2"></i>Proceed to Physical Examination
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(approvalStatusModal);
            // Use safe modal show
            showModalSafely('returningDonorApprovalModal').then(() => {
                // Handle proceed to physical examination
                document.getElementById('proceedToPhysicalExamReturning').addEventListener('click', function() {
                    hideModalSafely('returningDonorApprovalModal').then(() => {
                        // Clean up the modal element
                        document.body.removeChild(approvalStatusModal);
                        // Show physical examination
                        showPhysicalExamination(donorId);
                    });
                });
            });
        }
        // Function to show medical history approval for new donors
        function showMedicalHistoryApproval(donorId) {
            showModalSafely('medicalHistoryApprovalModal').then(() => {
                // Set up event listener for when medical history is approved
                const handleApproval = function(event) {
                    if (event.detail && event.detail.donorId === donorId) {
                        // Close medical history modal safely
                        hideModalSafely('medicalHistoryApprovalModal').then(() => {
                            // Show physical examination modal after cleanup
                            showPhysicalExamination(donorId);
                        });
                        // Remove the event listener
                        document.removeEventListener('medicalHistoryApproved', handleApproval);
                    }
                };
                document.addEventListener('medicalHistoryApproved', handleApproval);
                // Also set up direct button handler for the medical history approval modal
                setupMedicalHistoryApprovalButtonHandler(donorId);
            });
        }
        // Function to set up button handler for medical history approval modal
        function setupMedicalHistoryApprovalButtonHandler(donorId) {
            // Wait for the modal to be fully loaded
            setTimeout(() => {
                const modal = document.getElementById('medicalHistoryApprovalModal');
                if (modal) {
                    // Find the "Proceed to Physical Examination" button
                    const proceedButton = modal.querySelector('button[data-action="proceed-to-physical"]') ||
                                       Array.from(modal.querySelectorAll('button')).find(btn =>
                                           btn.textContent.includes('Proceed to Physical Examination'));
                    if (proceedButton) {
                        console.log('Found proceed button in medical history approval modal');
                        proceedButton.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('Proceed to Physical Examination clicked');
                            // Close the medical history approval modal
                            const medicalModal = bootstrap.Modal.getInstance(modal);
                            if (medicalModal) {
                                medicalModal.hide();
                            }
                            // Show physical examination modal after a short delay
                            setTimeout(() => {
                                showPhysicalExamination(donorId);
                            }, 500);
                        });
                    } else {
                        console.log('Proceed button not found in medical history approval modal');
                    }
                }
            }, 500);
        }
        // Function to show physical examination
        function showPhysicalExamination(donorId) {
            const screeningData = {
                donor_form_id: donorId,
                screening_id: 'SCRN-' + donorId + '-001',
                has_pending_exam: true,
                type: 'screening'
            };
            // Store the donor ID for the modal
            window.currentDonorId = donorId;
            
            try {
                // Try to open the physical examination modal if it exists
                if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                    // Use the admin modal system
                    window.physicalExaminationModalAdmin.openModal(screeningData);
                    return;
                }
            } catch (e) { console.warn('physicalExaminationModalAdmin.openModal not available, falling back', e); }
            
            // Fallback: try to show the modal directly
            try {
                const modalElement = document.getElementById('physicalExaminationModalAdmin');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                    return;
                }
            } catch (e) { console.warn('Failed to show physical examination modal directly', e); }
            
            // Final fallback: redirect to form page
            window.location.href = `../../src/views/forms/physical-examination-form-admin.php?donor_id=${encodeURIComponent(donorId)}`;
        }
        // Function to initialize physical examination modal functionality
        function initializePhysicalExaminationModal(donorId, screeningData) {
            // Set up the form data
            const donorIdField = document.getElementById('physical-donor-id');
            const screeningIdField = document.getElementById('physical-screening-id');
            if (donorIdField) donorIdField.value = donorId;
            if (screeningIdField) screeningIdField.value = screeningData.screening_id;
            // Initialize step functionality
            let currentStep = 1;
            const totalSteps = 4;
            // Show only the first step
            document.querySelectorAll('.physical-step-content').forEach((step, index) => {
                step.classList.remove('active');
                if (index === 0) {
                    step.classList.add('active');
                }
            });
            // Update progress indicator
            updatePhysicalProgressIndicator(currentStep, totalSteps);
            // Set up navigation buttons
            setupPhysicalNavigationButtons(currentStep, totalSteps, donorId);
            // Set up form validation
            setupPhysicalFormValidation();
        }
        // Function to update progress indicator
        function updatePhysicalProgressIndicator(currentStep, totalSteps) {
            // Update step indicators
            document.querySelectorAll('.physical-step').forEach((step, index) => {
                step.classList.remove('active', 'completed');
                if (index + 1 < currentStep) {
                    step.classList.add('completed');
                } else if (index + 1 === currentStep) {
                    step.classList.add('active');
                }
            });
            // Update progress bar
            const progressFill = document.querySelector('.physical-progress-fill');
            if (progressFill) {
                const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
                progressFill.style.width = progress + '%';
            }
        }
        // Function to setup navigation buttons
        function setupPhysicalNavigationButtons(currentStep, totalSteps, donorId) {
            const prevBtn = document.querySelector('.physical-prev-btn');
            const nextBtn = document.querySelector('.physical-next-btn');
            const submitBtn = document.querySelector('.physical-submit-btn');
            const deferBtn = document.querySelector('.physical-defer-btn');
            // Show/hide buttons based on current step
            if (prevBtn) {
                prevBtn.style.display = currentStep > 1 ? 'inline-block' : 'none';
            }
            if (nextBtn) {
                nextBtn.style.display = currentStep < totalSteps ? 'inline-block' : 'none';
            }
            if (submitBtn) {
                submitBtn.style.display = currentStep === totalSteps ? 'inline-block' : 'none';
            }
            // Set up event listeners and data attributes
            if (nextBtn) {
                nextBtn.dataset.currentStep = currentStep;
                nextBtn.onclick = () => {
                    if (validateCurrentStep(currentStep)) {
                        goToNextStep(currentStep, totalSteps, donorId);
                    }
                };
            }
            if (prevBtn) {
                prevBtn.dataset.currentStep = currentStep;
                prevBtn.onclick = () => {
                    goToPreviousStep(currentStep, totalSteps, donorId);
                };
            }
            if (submitBtn) {
                submitBtn.onclick = () => {
                    submitPhysicalExamination(donorId);
                };
            }
            if (deferBtn) {
                deferBtn.onclick = () => {
                    deferPhysicalExamination(donorId);
                };
            }
        }
        // Function to validate current step
        function validateCurrentStep(step) {
            const currentStepElement = document.getElementById(`physical-step-${step}`);
            if (!currentStepElement) return true;
            const requiredFields = currentStepElement.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            return isValid;
        }
        // Function to go to next step
        function goToNextStep(currentStep, totalSteps, donorId) {
            if (currentStep < totalSteps) {
                const newStep = currentStep + 1;
                // Hide current step
                const currentStepElement = document.getElementById(`physical-step-${currentStep}`);
                if (currentStepElement) {
                    currentStepElement.classList.remove('active');
                }
                // Show next step
                const nextStepElement = document.getElementById(`physical-step-${newStep}`);
                if (nextStepElement) {
                    nextStepElement.classList.add('active');
                }
                // Update progress
                updatePhysicalProgressIndicator(newStep, totalSteps);
                setupPhysicalNavigationButtons(newStep, totalSteps, donorId);
                // Update summary if on review step
                if (newStep === totalSteps) {
                    updatePhysicalExaminationSummary();
                }
            }
        }
        // Function to go to previous step
        function goToPreviousStep(currentStep, totalSteps, donorId) {
            if (currentStep > 1) {
                const newStep = currentStep - 1;
                // Hide current step
                const currentStepElement = document.getElementById(`physical-step-${currentStep}`);
                if (currentStepElement) {
                    currentStepElement.classList.remove('active');
                }
                // Show previous step
                const prevStepElement = document.getElementById(`physical-step-${newStep}`);
                if (prevStepElement) {
                    prevStepElement.classList.add('active');
                }
                // Update progress
                updatePhysicalProgressIndicator(newStep, totalSteps);
                setupPhysicalNavigationButtons(newStep, totalSteps, donorId);
            }
        }
        // Function to update summary
        function updatePhysicalExaminationSummary() {
            // Update vital signs
            const bloodPressure = document.getElementById('physical-blood-pressure')?.value || '-';
            const pulseRate = document.getElementById('physical-pulse-rate')?.value || '-';
            const bodyTemp = document.getElementById('physical-body-temp')?.value || '-';
            document.getElementById('summary-blood-pressure').textContent = bloodPressure;
            document.getElementById('summary-pulse-rate').textContent = pulseRate;
            document.getElementById('summary-body-temp').textContent = bodyTemp;
            // Update examination findings
            const genAppearance = document.getElementById('physical-gen-appearance')?.value || '-';
            const skin = document.getElementById('physical-skin')?.value || '-';
            const heent = document.getElementById('physical-heent')?.value || '-';
            const heartLungs = document.getElementById('physical-heart-lungs')?.value || '-';
            document.getElementById('summary-gen-appearance').textContent = genAppearance;
            document.getElementById('summary-skin').textContent = skin;
            document.getElementById('summary-heent').textContent = heent;
            document.getElementById('summary-heart-lungs').textContent = heartLungs;
            // Update blood bag selection
            const bloodBagType = document.querySelector('input[name="blood_bag_type"]:checked')?.value || '-';
            document.getElementById('summary-blood-bag').textContent = bloodBagType;
        }
        // Function to submit physical examination
        function submitPhysicalExamination(donorId) {
            console.log('Submitting physical examination for donor via physician handler:', donorId);
            try {
                if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.submitForm === 'function') {
                    // Delegate to the admin submit flow
                    window.physicalExaminationModalAdmin.submitForm();
                    return;
                }
            } catch (e) { console.warn('Delegated submit failed, falling back:', e); }
            // Fallback: click the visible submit button in the modal
            try {
                const btn = document.querySelector('#physicalExaminationModalAdmin .physical-submit-btn-admin');
                if (btn) { btn.click(); return; }
            } catch (_) {}
            if (window.adminModal && window.adminModal.alert) {
                window.adminModal.alert('Submit handler not available. Please ensure physical_examination_modal_admin.js is loaded.');
            } else {
                console.error('Admin modal not available');
            }
        }
        // Function to defer physical examination
        function deferPhysicalExamination(donorId) {
            console.log('Deferring physical examination for donor:', donorId);
            // Show defer donor modal
            showModalSafely('deferDonorModal');
        }
        // Function to setup form validation
        function setupPhysicalFormValidation() {
            // Add real-time validation for required fields
            document.querySelectorAll('#physicalExaminationModalAdmin input[required], #physicalExaminationModalAdmin select[required], #physicalExaminationModalAdmin textarea[required]').forEach(field => {
                field.addEventListener('blur', function() {
                    if (this.value.trim()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                });
            });
        }
        // Global event listener for physical examination modal navigation
        document.addEventListener('click', function(event) {
            // Handle Next button clicks
            if (event.target.classList.contains('physical-next-btn')) {
                event.preventDefault();
                const currentStep = parseInt(event.target.dataset.currentStep) || 1;
                const totalSteps = 4;
                const donorId = window.currentDonorId;
                if (validateCurrentStep(currentStep)) {
                    goToNextStep(currentStep, totalSteps, donorId);
                }
            }
            // Handle Previous button clicks
            if (event.target.classList.contains('physical-prev-btn')) {
                event.preventDefault();
                const currentStep = parseInt(event.target.dataset.currentStep) || 1;
                const totalSteps = 4;
                const donorId = window.currentDonorId;
                goToPreviousStep(currentStep, totalSteps, donorId);
            }
            // Handle Submit button clicks
            if (event.target.classList.contains('physical-submit-btn')) {
                event.preventDefault();
                const donorId = window.currentDonorId;
                submitPhysicalExamination(donorId);
            }
            // Handle Defer button clicks
            if (event.target.classList.contains('physical-defer-btn')) {
                event.preventDefault();
                const donorId = window.currentDonorId;
                deferPhysicalExamination(donorId);
            }
        });
        // Function to trigger medical history approved event
        function triggerMedicalHistoryApproved(donorId) {
            const event = new CustomEvent('medicalHistoryApproved', {
                detail: { donorId: donorId }
            });
            document.dispatchEvent(event);
        }
        // Enhanced modal state cleanup function
        function cleanupModalState() {
            try {
                // Use comprehensive cleanup if available
                if (typeof window.removeAllBackdrops === 'function') {
                    window.removeAllBackdrops();
                    return;
                }
                
                // Fallback: If no Bootstrap modals are currently shown, clean up body state
                const openModals = document.querySelectorAll('.modal.show');
                if (openModals.length === 0) {
                    try { document.body.classList.remove('modal-open'); } catch(_) {}
                    try { document.body.style.removeProperty('overflow'); } catch(_) {}
                    try { document.body.style.removeProperty('paddingRight'); } catch(_) {}
                    
                    // Remove any lingering backdrops
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                }
            } catch(_) {}
        }
        // Expose globally for any inline/onload consumers
        try { window.cleanupModalState = cleanupModalState; } catch(_) {}
        // Function to safely show a modal with proper cleanup
        function showModalSafely(modalId, delay = 0) {
            return new Promise((resolve) => {
                setTimeout(() => {
                    // Clean up any existing modal state first
                    cleanupModalState();
                    // Show the modal
                    const modalElement = document.getElementById(modalId);
                    if (modalElement) {
                        const modal = new bootstrap.Modal(modalElement, {
                            backdrop: false,
                            keyboard: true,
                            focus: true
                        });
                        modal.show();
                        resolve(modal);
                    } else {
                        console.error('Modal not found:', modalId);
                        resolve(null);
                    }
                }, delay);
            });
        }
        // Function to safely hide a modal with proper cleanup
        function hideModalSafely(modalId) {
            return new Promise((resolve) => {
                const modalElement = document.getElementById(modalId);
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                        // Clean up after modal is hidden
                        modalElement.addEventListener('hidden.bs.modal', function cleanup() {
                            cleanupModalBackdrops();
                            modalElement.removeEventListener('hidden.bs.modal', cleanup);
                            resolve();
                        }, { once: true });
                    } else {
                        cleanupModalBackdrops();
                        resolve();
                    }
                } else {
                    cleanupModalBackdrops();
                    resolve();
                }
            });
        }
        // Add event listener for modal cleanup
        document.addEventListener('hidden.bs.modal', function(event) {
            // Skip cleanup for both medical history modals to avoid conflicts with our custom close event
            if (event.target && (event.target.id === 'medicalHistoryModalAdmin' || event.target.id === 'medicalHistoryModal')) {
                console.log('Skipping cleanup for medical history modal - handled by custom close event');
                return;
            }
            
            // Small delay to ensure proper cleanup
            setTimeout(() => {
                cleanupModalState();
            }, 100);
        });
        // Removed periodic backdrop tampering and visibility-based cleanup.
        // Add event listeners for medical history approval buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Listen for medical history approval button clicks
            document.addEventListener('click', function(event) {
                // Check if it's an approval button in the medical history modal
                if (event.target.matches('#medicalHistoryApprovalModal .btn-success, #medicalHistoryApprovalModal .btn-primary')) {
                    const donorId = window.currentDonorId || window.currentDetailsDonorId;
                    if (donorId) {
                        // Trigger the medical history approved event
                        setTimeout(() => {
                            triggerMedicalHistoryApproved(donorId);
                        }, 1000); // Give time for the approval animation to show
                    }
                }
                // Check if it's the "Proceed to Physical Examination" button in the medical history approval modal
                if (event.target.closest('#medicalHistoryApprovalModal') &&
                    (event.target.textContent.includes('Proceed to Physical Examination') ||
                     event.target.matches('[data-action="proceed-to-physical"]'))) {
                    const donorId = window.currentDonorId || window.currentDetailsDonorId;
                    if (donorId) {
                        console.log('Proceed to Physical Examination clicked in medical history approval modal');
                        event.preventDefault();
                        event.stopPropagation();
                        // Close the medical history approval modal
                        const medicalModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryApprovalModal'));
                        if (medicalModal) {
                            medicalModal.hide();
                        }
                        // Show physical examination modal after a short delay
                        setTimeout(() => {
                            showPhysicalExamination(donorId);
                        }, 500);
                    }
                }
            });
        });
        // Additional global event listener for any dynamically loaded modals
        document.addEventListener('click', function(event) {
            // Check for any button with "Proceed to Physical Examination" text
            if (event.target.textContent && event.target.textContent.includes('Proceed to Physical Examination')) {
                const modal = event.target.closest('.modal');
                if (modal && modal.id === 'medicalHistoryApprovalModal') {
                    const donorId = window.currentDonorId || window.currentDetailsDonorId;
                    if (donorId) {
                        console.log('Global handler: Proceed to Physical Examination clicked');
                        event.preventDefault();
                        event.stopPropagation();
                        // Close the medical history approval modal
                        const medicalModal = bootstrap.Modal.getInstance(modal);
                        if (medicalModal) {
                            medicalModal.hide();
                        }
                        // Show physical examination modal after a short delay
                        setTimeout(() => {
                            showPhysicalExamination(donorId);
                        }, 500);
                    }
                }
            }
        });
        // Function to open medical history approval modal (similar to staff dashboard)
        function openMedicalHistoryApprovalModal(donorId) {
            console.log('Opening medical history approval modal for donor:', donorId);
            // Prevent multiple instances
            if (window.isOpeningMedicalHistory) {
                console.log("Medical history modal already opening, skipping...");
                return;
            }
            window.isOpeningMedicalHistory = true;
            // Track current donor ID
            window.currentDonorId = donorId;
            // Ensure any existing Bootstrap modals are fully closed/hidden to avoid visibility conflicts
            try {
                const possibleIds = ['screeningFormModal', 'donorProfileModal', 'editDonorForm', 'medicalHistoryApprovalWorkflowModal'];
                possibleIds.forEach(function(id){
                    try {
                        const el = document.getElementById(id);
                        if (!el) return;
                        const inst = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                        try { inst.hide(); } catch(_) {}
                        try { el.classList.remove('show'); el.style.display = 'none'; el.setAttribute('aria-hidden','true'); } catch(_) {}
                    } catch(_) {}
                });
                // Normalize body state left by previous Bootstrap modals
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            } catch(_) {}
            // Ensure the medical history modal starts with a clean state
            const mhElement = document.getElementById('medicalHistoryModal');
            if (!mhElement) {
                console.error('Medical history modal not found');
                window.isOpeningMedicalHistory = false;
                return;
            }
            // Reset any previous state
            mhElement.removeAttribute('style');
            mhElement.className = 'medical-history-modal';
            // Show the custom modal (like physical examination modal)
            mhElement.style.display = 'flex';
            mhElement.style.visibility = 'visible';
            mhElement.style.opacity = '1';
            try { mhElement.style.zIndex = '2000'; } catch(_) {}
            setTimeout(() => mhElement.classList.add('show'), 10);
            // Show loading state in modal content
            const modalContent = document.getElementById('medicalHistoryModalContent');
            if (modalContent) {
                modalContent.innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading medical history...</p>
                    </div>
                `;
            }
            // Ensure clean modal state before loading content
            // Load medical history content
            loadMedicalHistoryContent(donorId);
            // If physician path, reconfigure injected admin content to show Approve/Decline and hide navigation
            (function configurePhysicianButtons(attempt){
                try {
                    if (!window.__physicianMHView) return; // only when physician flagged
                    const host = document.getElementById('medicalHistoryModalContent');
                    if (!host) { if ((attempt||0) < 30) return setTimeout(()=>configurePhysicianButtons((attempt||0)+1), 100); return; }
                    const prevBtn = host.querySelector('#modalPrevButton');
                    const nextBtn = host.querySelector('#modalNextButton');
                    const submitBtn = host.querySelector('#modalSubmitButton');
                    const approveBtn = host.querySelector('#modalApproveButton');
                    const declineBtn = host.querySelector('#modalDeclineButton');
                    if (!approveBtn || !declineBtn) { if ((attempt||0) < 30) return setTimeout(()=>configurePhysicianButtons((attempt||0)+1), 100); return; }
                    try { if (prevBtn) prevBtn.style.display = 'none'; } catch(_) {}
                    try { if (nextBtn) nextBtn.style.display = 'none'; } catch(_) {}
                    try { if (submitBtn) submitBtn.style.display = 'none'; } catch(_) {}
                    try { approveBtn.style.display = 'inline-block'; } catch(_) {}
                    try { declineBtn.style.display = 'inline-block'; } catch(_) {}
                    // Clear the flag so subsequent opens don't force this
                    try { window.__physicianMHView = false; } catch(_) {}
                } catch(_) {
                    if ((attempt||0) < 30) return setTimeout(()=>configurePhysicianButtons((attempt||0)+1), 100);
                }
            })(0);
        }
        // Make the existing function globally accessible
        window.openMedicalHistoryApprovalModal = openMedicalHistoryApprovalModal;
        // Back-compat wrapper used by physician action buttons; opens ADMIN MH approval modal
        window.openMedicalreviewapproval = function(context){
            try {
                var donorId = null;
                if (context && typeof context === 'object') {
                    donorId = context.donor_id || context.donor_form_id || null;
                } else if (context) {
                    donorId = context;
                }
                donorId = donorId != null ? String(donorId) : '';
                if (!donorId) {
                    console.error('openMedicalreviewapproval: No donor ID provided');
                    return;
                }
                // Mark that this open was triggered from the physician approval path
                try { window.__physicianMHView = true; } catch(_) {}
                if (typeof window.openMedicalHistoryApprovalModal === 'function') {
                    window.openMedicalHistoryApprovalModal(donorId);
                } else if (typeof window.openMedicalHistoryModal === 'function') {
                    // Fallback to generic MH modal if admin loader is unavailable
                    window.openMedicalHistoryModal(donorId);
                } else {
                    // Final fallback: navigate to page view
                    window.location.href = `../../src/views/forms/medical-history.php?donor_id=${encodeURIComponent(donorId)}`;
                }
            } catch (e) {
                console.error('openMedicalreviewapproval failed:', e);
            }
        };
        // Function to load medical history content for interviewer workflow
        function loadMedicalHistoryContentForInterviewer(donorId) {
            const modalContent = document.getElementById('medicalHistoryModalContent');
            if (!modalContent) {
                console.error('Medical history modal content element not found');
                return;
            }
            
            console.log('Loading medical history content for interviewer workflow, donor ID:', donorId);
            
            // Validate donor ID
            if (!donorId || donorId === 'undefined' || donorId === 'null') {
                console.error('Invalid donor ID provided:', donorId);
                modalContent.innerHTML = '<div class="alert alert-danger">Invalid donor ID provided</div>';
                return;
            }
            
            // Show loading state
            modalContent.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading medical history...</p>
                </div>
            `;
            
            // Create a simple, self-contained medical history form for interviewer workflow
            const createInterviewerMedicalHistoryForm = () => {
                const formHTML = `
                    <div class="medical-history-form-container">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-medical me-2"></i>
                                    Medical History Review - Donor ID: ${donorId}
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="modalMedicalHistoryForm" class="medical-history-form">
                                    <input type="hidden" name="donor_id" value="${donorId}">
                                    <input type="hidden" name="action" value="interviewer_review">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Blood Pressure</label>
                                            <input type="text" class="form-control" name="blood_pressure" placeholder="e.g., 120/80">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Heart Rate</label>
                                            <input type="text" class="form-control" name="heart_rate" placeholder="e.g., 72 bpm">
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Temperature</label>
                                            <input type="text" class="form-control" name="temperature" placeholder="e.g., 36.5°C">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Weight</label>
                                            <input type="text" class="form-control" name="weight" placeholder="e.g., 65 kg">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Medical History Notes</label>
                                        <textarea class="form-control" name="medical_notes" rows="4" placeholder="Enter any relevant medical history notes..."></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Interviewer Assessment</label>
                                        <select class="form-select" name="assessment" required>
                                            <option value="">Select assessment</option>
                                            <option value="approved">Approved for donation</option>
                                            <option value="deferred">Deferred - temporary</option>
                                            <option value="rejected">Rejected - permanent</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Additional Comments</label>
                                        <textarea class="form-control" name="comments" rows="3" placeholder="Any additional comments or observations..."></textarea>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                `;
                
                modalContent.innerHTML = formHTML;
                
                // Add interviewer workflow buttons
                addInterviewerWorkflowButtons(donorId);
                
                // Form submission is handled by the submitMedicalHistoryForm function
                // No need for additional form submit listeners
            };
            
            // Try to fetch external content first, but fallback to self-contained form
            const fetchUrl = `../../src/views/forms/medical-history-modal-content-admin.php?donor_id=${encodeURIComponent(donorId)}`;
            console.log('Fetching medical history content from:', fetchUrl);
            
            fetch(fetchUrl)
                .then(response => {
                    console.log('Medical history fetch response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    console.log('Medical history HTML received, length:', html.length);
                    console.log('HTML preview:', html.substring(0, 500) + '...');
                    
                    // Update modal content with fetched HTML
                    modalContent.innerHTML = html;
                    
                    // Execute any script tags in the loaded content
                    const scripts = modalContent.querySelectorAll('script');
                    console.log('Found', scripts.length, 'script tags to execute');
                    scripts.forEach((script, index) => {
                        try {
                            console.log(`Executing script ${index + 1}:`, script.type || 'inline');
                            // Create a new script element and execute it
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            newScript.type = script.type || 'text/javascript';
                            document.head.appendChild(newScript);
                            newScript.remove(); // Remove after execution
                            console.log(`Script ${index + 1} executed successfully`);
                        } catch (e) {
                            console.warn('Error executing script:', e);
                        }
                    });
                    
                    // Add interviewer workflow buttons
                    addInterviewerWorkflowButtons(donorId);
                    
                    // Try to initialize any existing functionality, but don't fail if it doesn't exist
                        try {
                        if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                                window.generateAdminMedicalHistoryQuestions();
                        }
                    } catch (error) {
                        console.log('Admin medical history generator not available, using fallback');
                    }
                })
                .catch(error => {
                    console.log('External medical history content not available, using self-contained form:', error.message);
                    // Fallback to self-contained form
                    createInterviewerMedicalHistoryForm();
                });
        }
        // Function to load medical history content
        function loadMedicalHistoryContent(donorId) {
            const modalContent = document.getElementById('medicalHistoryModalContent');
            if (!modalContent) return;
            // Fetch admin medical history content
            fetch(`../../src/views/forms/medical-history-modal-content-admin.php?donor_id=${encodeURIComponent(donorId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    // Update modal content
                    modalContent.innerHTML = html;
                    // Execute any script tags in the loaded content
                    const scripts = modalContent.querySelectorAll('script');
                    scripts.forEach((script, index) => {
                        try {
                            console.log(`Executing script ${index + 1}:`, script.type || 'inline');
                            // Create a new script element and execute it
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            newScript.type = script.type || 'text/javascript';
                            document.head.appendChild(newScript);
                            newScript.remove(); // Remove after execution
                            console.log(`Script ${index + 1} executed successfully`);
                        } catch (e) {
                            console.warn('Error executing script:', e);
                        }
                    });
                    // Check if this is part of the interviewer workflow
                    if (window.currentInterviewerDonorId) {
                        console.log('Adding interviewer workflow buttons to medical history modal');
                        // Add interviewer workflow buttons instead of approve/decline
                        addInterviewerWorkflowButtons(donorId);
                    } else {
                        // Initialize medical history approval functionality for staff workflow
                        if (typeof initializeMedicalHistoryApproval === 'function') {
                            initializeMedicalHistoryApproval();
                        }
                        // Hide the submit button and show approve/decline buttons instead
                        const submitBtn = document.getElementById('nextButton') || document.getElementById('modalNextButton');
                        if (submitBtn) {
                            submitBtn.style.display = 'none';
                        }
                        // Add approve/decline buttons to the modal footer (only for staff workflow)
                        const modalFooter = document.querySelector('#medicalHistoryModal .modal-footer') || document.querySelector('.modal-footer');
                        if (modalFooter) {
                            // Check if buttons already exist
                            if (!document.getElementById('approveMedicalHistoryBtn')) {
                            const approveBtn = document.createElement('button');
                            approveBtn.className = 'btn btn-success me-2';
                            approveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Approve Medical History';
                            approveBtn.id = 'approveMedicalHistoryBtn';
                            const declineBtn = document.createElement('button');
                            declineBtn.className = 'btn btn-danger';
                            declineBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline Medical History';
                            declineBtn.id = 'declineMedicalHistoryBtn';
                            // Insert buttons before the close button
                            const closeBtn = modalFooter.querySelector('button[data-bs-dismiss="modal"]');
                            if (closeBtn) {
                                modalFooter.insertBefore(approveBtn, closeBtn);
                                modalFooter.insertBefore(declineBtn, closeBtn);
                            } else {
                                modalFooter.appendChild(approveBtn);
                                modalFooter.appendChild(declineBtn);
                            }
                            // Bind event handlers
                            approveBtn.addEventListener('click', function() {
                                handleMedicalHistoryApproval(donorId, 'approve');
                            });
                            declineBtn.addEventListener('click', function() {
                                handleMedicalHistoryApproval(donorId, 'decline');
                            });
                        }
                    }
                    }
                    // Force admin flow initialization
                    setTimeout(() => {
                        if (typeof mhInitializeAdminFlow === 'function') {
                            console.log('Forcing admin flow initialization');
                            mhInitializeAdminFlow();
                        }
                    }, 100);
                    // If physician path was requested, hide navigation and show decision buttons once present
                    (function ensurePhysicianDecisionUI(attempt){
                        try {
                            if (!window.__physicianMHView) return; // only apply when flagged
                            const host = document.getElementById('medicalHistoryModalContent');
                            if (!host) { if ((attempt||0) < 30) return setTimeout(()=>ensurePhysicianDecisionUI((attempt||0)+1), 100); return; }
                            const prevBtn = host.querySelector('#modalPrevButton');
                            const nextBtn = host.querySelector('#modalNextButton');
                            const submitBtn2 = host.querySelector('#modalSubmitButton');
                            const approveBtn2 = host.querySelector('#modalApproveButton');
                            const declineBtn2 = host.querySelector('#modalDeclineButton');
                            if (!approveBtn2 || !declineBtn2) { if ((attempt||0) < 30) return setTimeout(()=>ensurePhysicianDecisionUI((attempt||0)+1), 100); return; }
                            try { if (prevBtn) prevBtn.style.display = 'none'; } catch(_) {}
                            try { if (nextBtn) nextBtn.style.display = 'none'; } catch(_) {}
                            try { if (submitBtn2) submitBtn2.style.display = 'none'; } catch(_) {}
                            try { approveBtn2.style.display = 'inline-block'; } catch(_) {}
                            try { declineBtn2.style.display = 'inline-block'; } catch(_) {}
                            try { window.__physicianMHView = false; } catch(_) {}
                        } catch(_) {
                            if ((attempt||0) < 30) return setTimeout(()=>ensurePhysicianDecisionUI((attempt||0)+1), 100);
                        }
                    })(0);
                    window.isOpeningMedicalHistory = false;
                })
                .catch(error => {
                    console.error('Error loading medical history content:', error);
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Failed to load medical history content: ${error.message}
                        </div>
                    `;
                    window.isOpeningMedicalHistory = false;
                });
        }
        // Function to close medical history modal
        window.closeMedicalHistoryModal = function() {
            closeMedicalHistoryModalUnified();
        };
        // Function to proceed to physical examination (called after medical history approval)
        window.proceedToPhysicalExamination = function(donorId) {
            console.log('Proceeding to physical examination for donor:', donorId);
            // Close medical history modal
            closeMedicalHistoryModal();
            
            // Try to open the physical examination modal
            setTimeout(() => {
                try {
                    // Try to open the physical examination modal if it exists
                    if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                        const screeningData = {
                            donor_form_id: donorId,
                            screening_id: null
                        };
                        window.physicalExaminationModalAdmin.openModal(screeningData);
                        return;
                    }
                } catch (e) { console.warn('physicalExaminationModalAdmin.openModal not available, falling back', e); }
                
                // Fallback: try to show the modal directly
                try {
                    const modalElement = document.getElementById('physicalExaminationModalAdmin');
                    if (modalElement) {
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                        return;
                    }
                } catch (e) { console.warn('Failed to show physical examination modal directly', e); }
                
                // Final fallback: redirect to form page
                window.location.href = `../../src/views/forms/physical-examination-form-admin.php?donor_id=${encodeURIComponent(donorId)}`;
            }, 300);
        };
        // Function to open physical examination modal
        function openPhysicalExaminationModal(donorId) {
            console.log('Opening physical examination modal for donor:', donorId);
            // Create screening data object for the physical examination modal
            const screeningData = {
                donor_form_id: donorId,
                donor_id: donorId
            };
            
            try {
                // Try to open the physical examination modal if it exists
                if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                    window.physicalExaminationModalAdmin.openModal(screeningData);
                    return;
                }
            } catch (e) { console.warn('physicalExaminationModalAdmin.openModal not available, falling back', e); }
            
            // Fallback: try to show the modal directly
            try {
                const modalElement = document.getElementById('physicalExaminationModalAdmin');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                    return;
                }
            } catch (e) { console.warn('Failed to show physical examination modal directly', e); }
            
            // Final fallback: redirect to form page
            console.log('Physical examination modal not available, redirecting to form');
            window.location.href = `../../src/views/forms/physical-examination-form-admin.php?donor_id=${encodeURIComponent(donorId)}`;
        }
        window.editMedicalHistoryReview = function(donorId) {
            console.log('Editing medical history review for donor:', donorId);
            // Open physician medical history review modal for editing
            if (typeof window.openPhysicianMedicalReview === 'function') {
                window.openPhysicianMedicalReview({ donor_id: donorId });
            } else {
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Medical history review editing not available');
                } else {
                    console.error('Admin modal not available');
                }
            }
        };
        // Diagnostic: Run deep checks for MH approval modal visibility/behavior
        window.runMHApprovalDiagnostics = async function(donorId){
            const diag = { when: new Date().toISOString(), donorId: donorId || null, steps: [] };
            const log = (name, data) => { try { console.log('[MH-DIAG]', name, data); } catch(_) {} diag.steps.push({ name, data }); };
            try {
                log('functions', {
                    openPhysicianMedicalPreview: typeof window.openPhysicianMedicalPreview,
                    openMedicalreviewapproval: typeof window.openMedicalreviewapproval,
                    openMedicalHistoryApprovalModal: typeof window.openMedicalHistoryApprovalModal,
                    openMedicalHistoryModal: typeof window.openMedicalHistoryModal,
                    loadMedicalHistoryContent: typeof window.loadMedicalHistoryContent,
                    initializeMedicalHistoryApproval: typeof window.initializeMedicalHistoryApproval,
                    generateAdminMedicalHistoryQuestions: typeof window.generateAdminMedicalHistoryQuestions
                });
                const ids = [
                    'medicalHistoryModalAdmin','medicalHistoryModal','medicalHistoryApprovalWorkflowModal',
                    'medicalHistoryApprovalModal','medicalHistoryDeclinedModal','donorProfileModal','screeningFormModal'
                ];
                const snap = {};
                ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) { snap[id] = { exists:false }; return; }
                    const cs = window.getComputedStyle(el);
                    const r = el.getBoundingClientRect();
                    snap[id] = {
                        exists:true,
                        classes: el.className,
                        display: cs.display,
                        visibility: cs.visibility,
                        opacity: cs.opacity,
                        zIndex: cs.zIndex,
                        rect: { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) }
                    };
                });
                log('elements.snapshot', snap);
                // Body state
                log('body', { modalOpen: document.body.classList.contains('modal-open'), overflow: document.body.style.overflow, pr: document.body.style.paddingRight });
                // Attempt a direct fetch of the admin content
                if (donorId) {
                    try {
                        const url = `../../src/views/forms/medical-history-modal-content-admin.php?donor_id=${encodeURIComponent(String(donorId))}`;
                        const res = await fetch(url, { cache:'no-store' });
                        const txt = await res.text();
                        log('fetch.adminContent', { ok: res.ok, status: res.status, length: txt.length, hasForm: /modalMedicalHistoryForm/.test(txt) });
                    } catch (e) { log('fetch.adminContent.error', String(e && e.message || e)); }
                }
                // Attach temporary modal show/hide listeners for admin MH
                try {
                    const mhEl = document.getElementById('medicalHistoryModalAdmin');
                    if (mhEl) {
                        mhEl.addEventListener('show.bs.modal', () => log('event.show.adminMH', { fired:true }), { once:true });
                        mhEl.addEventListener('shown.bs.modal', () => log('event.shown.adminMH', { fired:true }), { once:true });
                        mhEl.addEventListener('hide.bs.modal', () => log('event.hide.adminMH', { fired:true }), { once:true });
                    }
                } catch(_) {}
                // Try to open via the same handler used by the UI
                if (donorId) {
                    try { document.body.classList.remove('modal-open'); document.body.style.overflow=''; document.body.style.paddingRight=''; } catch(_) {}
                    if (typeof window.openPhysicianMedicalPreview === 'function') {
                        log('action.openPhysicianMedicalPreview', { donorId });
                        window.openPhysicianMedicalPreview(String(donorId));
                    } else if (typeof window.openMedicalreviewapproval === 'function') {
                        log('action.openMedicalreviewapproval', { donorId });
                        window.openMedicalreviewapproval({ donor_id: String(donorId) });
                    } else if (typeof window.openMedicalHistoryApprovalModal === 'function') {
                        log('action.openMedicalHistoryApprovalModal', { donorId });
                        window.openMedicalHistoryApprovalModal(String(donorId));
                    }
                    await new Promise(r => setTimeout(r, 800));
                }
                // Re-snapshot after attempting to open
                const post = {};
                ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) { post[id] = { exists:false }; return; }
                    const cs = window.getComputedStyle(el);
                    const r = el.getBoundingClientRect();
                    post[id] = {
                        exists:true,
                        hasShowClass: el.classList.contains('show'),
                        display: cs.display,
                        visibility: cs.visibility,
                        opacity: cs.opacity,
                        zIndex: cs.zIndex,
                        rect: { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) }
                    };
                });
                log('elements.afterOpen', post);
                // Look for potential overlays with higher z-index than admin MH modal
                try {
                    const mh = document.getElementById('medicalHistoryModalAdmin');
                    const mz = mh ? parseInt(getComputedStyle(mh).zIndex || '0', 10) || 0 : 0;
                    const offenders = Array.from(document.querySelectorAll('body *'))
                        .filter(n => n !== mh)
                        .map(n => ({ n, cs: getComputedStyle(n) }))
                        .filter(x => (x.cs.position === 'fixed' || x.cs.position === 'sticky') && (parseInt(x.cs.zIndex || '0', 10) || 0) >= mz)
                        .slice(0, 10)
                        .map(x => ({ tag: x.n.tagName, id: x.n.id, cls: x.n.className, z: x.cs.zIndex }));
                    log('overlays.possible', offenders);
                } catch(_) {}
            } catch (e) {
                log('error', String(e && e.message || e));
            }
            try { window.__mhDiagLast = diag; } catch(_) {}
            return diag;
        };
        window.editPhysicalExamination = function(donorId) {
            console.log('Editing physical examination for donor:', donorId);
            // Open physical examination form for editing
            // Set session variables for the form
            fetch('../../assets/php_func/set_donor_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    donor_id: donorId,
                    action: 'set_donor_session'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
            window.location.href = `../../src/views/forms/physical-examination-form-admin.php?donor_id=${encodeURIComponent(donorId)}`;
                } else {
                    console.error('Failed to set donor session:', data.message);
                    if (window.adminModal && window.adminModal.alert) {
                        window.adminModal.alert('Error: Failed to prepare physical examination form. Please try again.');
                    } else {
                        console.error('Admin modal not available');
                    }
                }
            })
            .catch(error => {
                console.error('Error setting donor session:', error);
                // Fallback to direct redirect
                window.location.href = `../../src/views/forms/physical-examination-form-admin.php?donor_id=${encodeURIComponent(donorId)}`;
            });
        };
        window.editBloodCollection = async function(donorId) {
            console.log('Editing blood collection for donor:', donorId);
            let physicalExamId = null;
            try {
                // Deterministically resolve physical_exam_id for this donor
                const resp = await fetch(`../../assets/php_func/admin/get_physical_exam_details_admin.php?donor_id=${encodeURIComponent(donorId)}`);
                if (resp.ok) {
                    const data = await resp.json();
                    if (data && data.success && data.data && data.data.physical_exam_id) {
                        physicalExamId = data.data.physical_exam_id;
                        console.log('Resolved physical_exam_id:', physicalExamId);
                    } else {
                        console.warn('No physical_exam_id found in response for donor:', donorId);
                    }
                } else {
                    console.warn('Failed to fetch physical_exam_id, HTTP status:', resp.status);
                }
            } catch (e) { 
                console.warn('Failed to resolve physical exam id', e); 
            }
            
            try {
                // Try to open the blood collection modal if it exists
                if (window.bloodCollectionModalAdmin && typeof window.bloodCollectionModalAdmin.openModal === 'function') {
                    const modalData = { 
                        donor_id: donorId
                    };
                    // Only include physical_exam_id if we successfully resolved it
                    if (physicalExamId) {
                        modalData.physical_exam_id = physicalExamId;
                    }
                    console.log('Opening modal with data:', modalData);
                    window.bloodCollectionModalAdmin.openModal(modalData);
                    return;
                }
            } catch (e) { console.warn('bloodCollectionModalAdmin.openModal not available, falling back', e); }
            
            // Fallback: try to show the modal directly
            try {
                const modalElement = document.getElementById('bloodCollectionModalAdmin');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                    return;
                }
            } catch (e) { console.warn('Failed to show blood collection modal directly', e); }
            
            // Final fallback: redirect to form page
            window.location.href = `dashboard-staff-blood-collection-submission.php?donor_id=${encodeURIComponent(donorId)}`;
        };
        // Provide a confirmation handler used by blood_collection_modal.js
        // In admin context we submit directly to avoid a missing confirmation modal
        if (typeof window.showCollectionCompleteModal !== 'function') {
            window.showCollectionCompleteModal = function() {
                try {
                    if (window.bloodCollectionModalAdmin && typeof window.bloodCollectionModalAdmin.submitForm === 'function') {
                        window.bloodCollectionModalAdmin.submitForm();
                    }
                } catch (e) { console.error('Submit handler not available', e); }
            };
        }
        // Align success handler name with the staff implementation
        if (typeof window.showDonationSuccessModal !== 'function' && typeof window.showBloodCollectionCompleted === 'function') {
            window.showDonationSuccessModal = window.showBloodCollectionCompleted;
        }
        window.viewInterviewerDetails = function(donorId) {
            console.log('=== VIEWING INTERVIEWER DETAILS (SCREENING SUMMARY) ===');
            console.log('Donor ID:', donorId);
            console.log('Type of donorId:', typeof donorId);
            
            if (!donorId) {
                console.error('❌ No donor ID provided to viewInterviewerDetails');
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Error: No donor ID provided');
                } else {
                    console.error('Admin modal not available');
                }
                return;
            }
            
            const eligibilityId = window.currentEligibilityId || 'pending_' + donorId;
            console.log('Eligibility ID:', eligibilityId);
            
            // Show the screening summary modal instead of donor details
            window.showScreeningSummary(donorId, eligibilityId);
        };
        
        // Function to show screening summary modal
        window.showScreeningSummary = function(donorId, eligibilityId) {
            console.log('=== SHOWING SCREENING SUMMARY ===');
            console.log('Donor ID:', donorId);
            console.log('Eligibility ID:', eligibilityId);
            
            const modalEl = document.getElementById('screeningSummaryModal');
            const contentEl = document.getElementById('screeningSummaryModalContent');
            
            if (!modalEl || !contentEl) {
                console.error('❌ Screening summary modal elements not found!');
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Error: Screening summary modal not found. Please refresh the page.');
                } else {
                    console.error('Admin modal not available');
                }
                return;
            }
            
            // Show loading spinner
            contentEl.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            
            // Show the modal
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
            
            // Fetch screening data
            fetch(`../../assets/php_func/donor_details_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        contentEl.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    const donor = data.donor || {};
                    const screeningForm = data.screening_form || {};
                    const eligibility = data.eligibility || {};
                    const safe = (v, fb = '-') => (v === null || v === undefined || v === '' ? fb : v);
                    
                    // Get donation type with proper fallback logic
                    let donationType = 'walk-in'; // default fallback
                    if (screeningForm.donation_type && screeningForm.donation_type !== 'Pending') {
                        donationType = screeningForm.donation_type;
                    } else if (eligibility.donation_type && eligibility.donation_type !== 'Pending') {
                        donationType = eligibility.donation_type;
                    }
                    
                    // Format donation type for display (convert kebab-case to title case)
                    const formatDonationType = (type) => {
                        if (!type || type === 'Pending') return 'walk-in';
                        return type.split('-').map(word => 
                            word.charAt(0).toUpperCase() + word.slice(1)
                        ).join(' ');
                    };
                    
                    // Create screening summary HTML
                    const screeningSummaryHTML = `
                        <div class="screening-summary-section">
                            <h6 class="screening-summary-title">
                                <i class="fas fa-check-circle"></i>
                                Screening Summary
                            </h6>
                            <p class="screening-summary-description">
                                Review the screening details for this donor
                            </p>
                            
                            <div class="screening-info-grid">
                                <!-- Donation Type -->
                                <div class="screening-category">
                                    <div class="screening-category-header">
                                        Donation Type
                                    </div>
                                    <div class="screening-category-content">
                                        <div class="screening-field">
                                            <span class="screening-field-label">Type:</span>
                                            <span class="screening-field-value">${formatDonationType(donationType)}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Basic Information -->
                                <div class="screening-category">
                                    <div class="screening-category-header">
                                        Basic Information
                                    </div>
                                    <div class="screening-category-content">
                                        <div class="screening-field">
                                            <span class="screening-field-label">Body Weight:</span>
                                            <span class="screening-field-value">${safe(screeningForm.body_weight || eligibility.body_weight, '59')} kg</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">Specific Gravity:</span>
                                            <span class="screening-field-value">${safe(screeningForm.specific_gravity || eligibility.specific_gravity, '15.9')}</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">Blood Type:</span>
                                            <span class="blood-type-plain">${safe(screeningForm.blood_type || donor.blood_type || eligibility.blood_type, 'A+')}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    contentEl.innerHTML = screeningSummaryHTML;
                })
                .catch(error => {
                    console.error('Error fetching screening summary:', error);
                    contentEl.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>Error Loading Screening Summary</h6>
                            <p>Failed to load screening information. Please try again.</p>
                            <small class="text-muted">Error: ${error.message}</small>
                        </div>
                    `;
                });
        };

        window.showPhysicianSection = function(donorId, eligibilityId) {
            console.log('=== SHOWING PHYSICIAN SECTION (VIEW MODE) ===');
            console.log('Donor ID:', donorId);
            console.log('Eligibility ID:', eligibilityId);
            console.log('Opening physicianSectionModal (standalone view modal, NOT edit modal with steps)');
            
            // Ensure the edit modal is NOT open
            const editModalEl = document.getElementById('physicalExaminationModalAdmin');
            if (editModalEl) {
                const editModal = bootstrap.Modal.getInstance(editModalEl);
                if (editModal && editModal._isShown) {
                    console.log('[VIEW] Closing edit modal that was incorrectly opened');
                    editModal.hide();
                }
            }
            
            const modalEl = document.getElementById('physicianSectionModal');
            const contentEl = document.getElementById('physicianSectionModalContent');
            
            if (!modalEl || !contentEl) {
                console.error('❌ Physician section modal elements not found!');
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Error: Physician section modal not found. Please refresh the page.');
                } else {
                    console.error('Admin modal not available');
                }
                return;
            }
            
            // Show loading spinner
            contentEl.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            
            // Show the modal - this is the standalone view modal, NOT the edit modal
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
            
            // Fetch physician data
            fetch(`../../assets/php_func/donor_details_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        contentEl.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    const donor = data.donor || {};
                    const eligibility = data.eligibility || {};
                    const physicalExam = data.physical_examination || {};
                    const screeningForm = data.screening_form || {};
                    const safe = (v, fb = '-') => (v === null || v === undefined || v === '' ? fb : v);
                    
                    // Create physical examination summary HTML (matching Initial Screening Form style exactly)
                    const physicianSectionHTML = `
                        <div class="screening-summary-section">
                            <div class="screening-info-grid">
                                <!-- Vital Signs -->
                                <div class="screening-category">
                                    <div class="screening-category-header">
                                        Vital Signs
                                    </div>
                                    <div class="screening-category-content">
                                        <div class="screening-field">
                                            <span class="screening-field-label">Blood Pressure:</span>
                                            <span class="screening-field-value">${safe(physicalExam.blood_pressure || eligibility.blood_pressure, '120/80')} mmHg</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">Pulse Rate:</span>
                                            <span class="screening-field-value">${safe(physicalExam.pulse_rate || eligibility.pulse_rate, '72')} bpm</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">Body Temperature:</span>
                                            <span class="screening-field-value">${safe(physicalExam.body_temp || eligibility.body_temp, '36.5')}°C</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">Body Weight:</span>
                                            <span class="screening-field-value">${safe(physicalExam.body_weight || eligibility.body_weight || screeningForm.body_weight, '65')} kg</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Examination Findings -->
                                <div class="screening-category">
                                    <div class="screening-category-header">
                                        Examination Findings
                                    </div>
                                    <div class="screening-category-content">
                                        <div class="screening-field">
                                            <span class="screening-field-label">General Appearance:</span>
                                            <span class="screening-field-value">${safe(physicalExam.gen_appearance || eligibility.gen_appearance, 'Okay')}</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">Skin:</span>
                                            <span class="screening-field-value">${safe(physicalExam.skin || eligibility.skin, 'Okay')}</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">HEENT:</span>
                                            <span class="screening-field-value">${safe(physicalExam.heent || eligibility.heent, 'Okay')}</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">Heart and Lungs:</span>
                                            <span class="screening-field-value">${safe(physicalExam.heart_and_lungs || eligibility.heart_and_lungs, 'Okay')}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Blood Information -->
                                <div class="screening-category">
                                    <div class="screening-category-header">
                                        Blood Information
                                    </div>
                                    <div class="screening-category-content">
                                        <div class="screening-field">
                                            <span class="screening-field-label">Blood Type:</span>
                                            <span class="blood-type-plain">${safe(physicalExam.blood_type || eligibility.blood_type || donor.blood_type || screeningForm.blood_type, 'A+')}</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">Blood Bag Type:</span>
                                            <span class="screening-field-value">${safe(physicalExam.blood_bag_type || eligibility.blood_bag_type, '450ml')}</span>
                                        </div>
                                        <div class="screening-field">
                                            <span class="screening-field-label">Specific Gravity:</span>
                                            <span class="screening-field-value">${safe(physicalExam.specific_gravity || eligibility.specific_gravity || screeningForm.specific_gravity, '1.050')}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    contentEl.innerHTML = physicianSectionHTML;
                })
                .catch(error => {
                    console.error('Error fetching physician section:', error);
                    contentEl.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>Error Loading Physical Examination</h6>
                            <p>Failed to load physical examination information. Please try again.</p>
                            <small class="text-muted">Error: ${error.message}</small>
                        </div>
                    `;
                });
        };

        window.viewPhysicianDetails = function(donorId) {
            console.log('=== VIEWING PHYSICIAN DETAILS (PHYSICAL EXAMINATION) ===');
            console.log('Donor ID:', donorId);
            console.log('Type of donorId:', typeof donorId);
            
            if (!donorId) {
                console.error('❌ No donor ID provided to viewPhysicianDetails');
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Error: No donor ID provided');
                } else {
                    console.error('Admin modal not available');
                }
                return;
            }
            
            const eligibilityId = window.currentEligibilityId || 'pending_' + donorId;
            console.log('Eligibility ID:', eligibilityId);
            
            // Show the physician section modal instead of donor details
            window.showPhysicianSection(donorId, eligibilityId);
        };
        window.viewPhlebotomistDetails = function(donorId) {
            console.log('Viewing phlebotomist details for donor:', donorId);
            const eligibilityId = window.currentEligibilityId || 'pending_' + donorId;
            // Use the comprehensive donor details modal
            window.openDonorDetails({
                donor_id: donorId,
                eligibility_id: eligibilityId
            });
        };
        // Function to fetch and populate donor details modal with proper layout
        window.fetchDonorDetailsModal = function(donorId, eligibilityId) {
            console.log(`Fetching details for donor: ${donorId}, eligibility: ${eligibilityId}`);
            fetch(`../../assets/php_func/donor_details_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const donorDetailsContainer = document.getElementById('donorDetailsModalContent');
                    if (data.error) {
                        donorDetailsContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    const donor = data.donor || {};
                    const eligibility = data.eligibility || {};
                    const safe = (v, fb = '-') => (v === null || v === undefined || v === '' ? fb : v);
                    // Determine legacy success from eligibility collection_status
                    const collectionStatus = (eligibility.collection_status || '') + '';
                    const legacySuccess = collectionStatus.toLowerCase().includes('success');
                    
                    // Debug logging
                    console.log('[Donor Details] Collection Status:', collectionStatus);
                    console.log('[Donor Details] Legacy Success:', legacySuccess);
                    console.log('[Donor Details] Eligibility data:', eligibility);
                    // Create legacy-style donor details HTML
                    const donorDetailsHTML = `
                        <div class="donor-details-container">
                            <!-- Overall Status Section -->
                            <div class="overall-status-section">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="status-badge">Pending</span>
                                        <span class="status-text">In Progress</span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-white-75">Eligibility ID: pending_${safe(donor.donor_id)}</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Donor Header - Fixed Layout -->
                            <div class="donor-header-wireframe">
                                <div class="donor-header-left">
                                    <h3 class="donor-name-wireframe">${safe(donor.first_name)} ${safe(donor.middle_name)} ${safe(donor.surname)}</h3>
                                    <div class="donor-age-gender">${safe(donor.age)}, ${safe(donor.sex)}</div>
                                </div>
                                <div class="donor-header-right">
                                    <div class="donor-id-wireframe">Donor ID ${safe(donor.donor_id)}</div>
                                    <div class="donor-blood-type">
                                        <div class="blood-type-display">
                                            <div class="blood-type-label">Blood Type</div>
                                            <div class="blood-type-value">${safe(donor.blood_type, 'Pending')}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Donor Information Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Donor Information</h3>
                                <div class="form-fields-grid">
                                    <div class="form-field">
                                        <label>Birthdate</label>
                                        <input type="text" value="${safe(donor.birthdate)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Address</label>
                                        <input type="text" value="${safe(donor.permanent_address || donor.office_address)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Mobile Number</label>
                                        <input type="text" value="${safe(donor.mobile || donor.mobile_number || donor.contact_number)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Civil Status</label>
                                        <input type="text" value="${safe(donor.civil_status)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Nationality</label>
                                        <input type="text" value="${safe(donor.nationality)}" readonly>
                                    </div>
                                    <div class="form-field">
                                        <label>Occupation</label>
                                        <input type="text" value="${safe(donor.occupation)}" readonly>
                                    </div>
                                </div>
                            </div>
                            <!-- Medical History Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Medical History</h3>
                                <table class="table-wireframe">
                                    <thead>
                                        <tr>
                                            <th>Medical History Result</th>
                                            <th>Interviewer Decision</th>
                                            <th>Physician Decision</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>${safe(eligibility.medical_history_status || 'Approved')}</td>
                                            <td>${safe(eligibility.interviewer_decision || '-')}</td>
                                            <td>${safe(eligibility.physician_decision || 'Approved')}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Medical History" onclick="openMedicalreviewapproval({ donor_id: '${safe(donor.donor_id || eligibility.donor_id,'')}' })">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Initial Screening Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Initial Screening</h3>
                                <table class="table-wireframe">
                                    <thead>
                                        <tr>
                                            <th>Body Weight</th>
                                            <th>Specific Gravity</th>
                                            <th>Blood Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>${safe(eligibility.body_weight || '57 kg')}</td>
                                            <td>${safe(eligibility.specific_gravity || '12.8 g/dL')}</td>
                                            <td>
                                                <div class="blood-type-display">
                                                    <div class="blood-type-label">Blood Type</div>
                                                    <div class="blood-type-value">${safe(donor.blood_type || 'A+')}</div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Physical Examination Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Physical Examination</h3>
                                <table class="table-wireframe">
                                    <thead>
                                        <tr>
                                            <th>Physical Examination Result</th>
                                            <th>Physician Decision</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>${safe(eligibility.physical_exam_result || 'Approved')}</td>
                                            <td>${safe(eligibility.physician_decision || 'Approved')}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Physical Examination" onclick="openPhysicianPhysicalExam({ donor_id: '${safe(donor.donor_id || eligibility.donor_id,'')}' })">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="physical-exam-extra">
                                    <div class="form-field-inline">
                                        <label>Type of Donation</label>
                                        <input type="text" value="${safe(eligibility.donation_type || '')}" readonly>
                                    </div>
                                    <div class="form-field-inline">
                                        <label>Eligibility Status</label>
                                        <button type="button" class="btn btn-success btn-sm eligibility-btn">Approve to Donate</button>
                                    </div>
                                </div>
                            </div>
                            <!-- Blood Collection Section -->
                            <div class="section-wireframe">
                                <h3 class="section-title">Blood Collection</h3>
                                <table class="table-wireframe">
                                    <thead>
                                        <tr>
                                            <th>Blood Collection Status</th>
                                            <th>Phlebotomist Note</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>${legacySuccess ? 'Successful' : (eligibility.collection_status ? eligibility.collection_status : 'Pending')}</td>
                                            <td>${legacySuccess ? safe(eligibility.phlebotomist_note, 'Successful') : safe(eligibility.phlebotomist_note, '-')}</td>
                                            <td>
                                                ${!legacySuccess && !eligibility.collection_status ? `<button type="button" class="btn btn-sm btn-outline-secondary circular-btn" title="View Not Available" disabled><i class="fas fa-eye"></i></button>` : `<button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Blood Collection" onclick="openPhlebotomistCollection({ donor_id: '${safe(donor.donor_id || eligibility.donor_id,'')}' })"><i class="fas fa-eye"></i></button>`}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Interviewer Section Button -->
                            <div class="text-center">
                                <button type="button" class="interviewer-section-btn" onclick="editMedicalHistory('${safe(donor.donor_id || eligibility.donor_id, '')}')">
                                    <i class="fas fa-user-md me-2"></i>Interviewer
                                </button>
                            </div>
                        </div>
                    `;
                    donorDetailsContainer.innerHTML = donorDetailsHTML;
                })
                .catch(error => {
                    console.error('Error fetching donor details:', error);
                    const donorDetailsContainer = document.getElementById('donorDetailsModalContent');
                    donorDetailsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>Error Loading Donor Details</h6>
                            <p>Failed to load donor information. Please try again.</p>
                            <small class="text-muted">Error: ${error.message}</small>
                        </div>
                    `;
                });
        };
        // Test function to verify Donor Details Modal functionality
        window.testDonorDetailsModal = function(donorId = '171') {
            console.log('Testing Donor Details Modal with donor ID:', donorId);
            window.viewInterviewerDetails(donorId);
        };
        
        // Debug function to check modal elements (legacy donorModal removed)
        window.debugModalElements = function() {
            console.log('=== DEBUGGING MODAL ELEMENTS ===');
            const donorDetailsModal = document.getElementById('donorDetailsModal');
            const donorDetailsModalContent = document.getElementById('donorDetailsModalContent');
            console.log('donorDetailsModal element:', donorDetailsModal);
            console.log('donorDetailsModalContent element:', donorDetailsModalContent);
            if (typeof bootstrap === 'undefined') {
                console.error('❌ Bootstrap is not loaded!');
            }
        };
        // Test function to directly test interviewer medical history workflow
        window.testOpenMedicalHistory = function(donorId = '123') {
            console.log('=== TESTING INTERVIEWER MEDICAL HISTORY WORKFLOW ===');
            console.log('Testing with donor ID:', donorId);
            console.log('loadMedicalHistoryContentForInterviewer type:', typeof loadMedicalHistoryContentForInterviewer);
            // Set the interviewer workflow flag
            window.currentInterviewerDonorId = donorId;
            // Check if we have the medical history modal element
            const medicalHistoryModal = document.getElementById('medicalHistoryModal');
            if (medicalHistoryModal) {
                console.log('Found medical history modal, opening it for interviewer workflow');
                // Reset modal state
                medicalHistoryModal.removeAttribute('style');
                medicalHistoryModal.className = 'medical-history-modal';
                // Show the modal
                medicalHistoryModal.style.display = 'flex';
                setTimeout(() => medicalHistoryModal.classList.add('show'), 10);
                // Load the medical history content for interviewer workflow
                loadMedicalHistoryContentForInterviewer(donorId);
            } else {
                console.error('Medical history modal not found!');
            }
        };
        // Test function for interviewer workflow
        window.testInterviewerWorkflow = function(donorId = '123') {
            console.log('=== TESTING INTERVIEWER WORKFLOW ===');
            console.log('Testing with donor ID:', donorId);
            // Test if modal exists
            const modal = document.getElementById('processMedicalHistoryConfirmModal');
            console.log('Confirmation modal:', modal);
            // Test if button exists
            const button = document.getElementById('interviewerProceedToMedicalHistoryBtn');
            console.log('Interviewer proceed button:', button);
            // Test if we can show the modal
            if (modal) {
                const bootstrapModal = new bootstrap.Modal(modal);
                bootstrapModal.show();
                console.log('Modal shown successfully');
                // Wait for modal to be fully shown, then test button
                setTimeout(() => {
                    const button = document.getElementById('interviewerProceedToMedicalHistoryBtn');
                    console.log('Button after modal shown:', button);
                    console.log('Button disabled:', button?.disabled);
                    console.log('Button style:', button?.style.display);
                    console.log('Button computed style:', button ? window.getComputedStyle(button).display : 'N/A');
                    if (button) {
                        // Check if button is actually visible and clickable
                        const rect = button.getBoundingClientRect();
                        console.log('Button position:', rect);
                        console.log('Button z-index:', window.getComputedStyle(button).zIndex);
                        console.log('Button pointer-events:', window.getComputedStyle(button).pointerEvents);
                        // Force make button clickable
                        button.style.pointerEvents = 'auto';
                        button.style.zIndex = '9999';
                        button.style.position = 'relative';
                        console.log('Forced button to be clickable');
                        // Check for overlapping elements
                        const elementsAtPoint = document.elementsFromPoint(rect.left + rect.width/2, rect.top + rect.height/2);
                        console.log('Elements at button center:', elementsAtPoint);
                        // Check for modal backdrops
                        const backdrops = document.querySelectorAll('.modal-backdrop');
                        console.log('Modal backdrops:', backdrops);
                        backdrops.forEach((backdrop, index) => {
                            console.log(`Backdrop ${index}:`, {
                                zIndex: window.getComputedStyle(backdrop).zIndex,
                                display: window.getComputedStyle(backdrop).display,
                                position: window.getComputedStyle(backdrop).position
                            });
                        });
                        // Test if we can trigger click programmatically
                        console.log('Testing programmatic click...');
                        button.click();
                    }
                }, 500);
            } else {
                console.error('Modal not found!');
            }
        };
        
        // Interviewer Workflow Functions
        console.log('About to set up DOM ready listener');
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== DOM CONTENT LOADED ===');
            console.log('DOM Content Loaded - Setting up interviewer workflow');
            // Test if button exists
            const proceedBtn = document.getElementById('interviewerProceedToMedicalHistoryBtn');
            console.log('Interviewer proceed button found:', proceedBtn);
            // Add a simple test event listener first
            if (proceedBtn) {
                console.log('Adding simple test listener to button');
                proceedBtn.addEventListener('click', function() {
                    console.log('SIMPLE TEST: Button clicked!');
                });
                // Also try mousedown and mouseup events
                proceedBtn.addEventListener('mousedown', function() {
                    console.log('MOUSEDOWN: Button mousedown!');
                });
                proceedBtn.addEventListener('mouseup', function() {
                    console.log('MOUSEUP: Button mouseup!');
                });
                // Check button properties
                console.log('Button properties:', {
                    disabled: proceedBtn.disabled,
                    style: proceedBtn.style.cssText,
                    className: proceedBtn.className,
                    offsetWidth: proceedBtn.offsetWidth,
                    offsetHeight: proceedBtn.offsetHeight,
                    clientWidth: proceedBtn.clientWidth,
                    clientHeight: proceedBtn.clientHeight
                });
            }
            // Handle Process Medical History Confirmation
            if (proceedBtn) {
                console.log('Adding event listener to proceed button');
                proceedBtn.addEventListener('click', function(event) {
                    console.log('=== PROCEED BUTTON CLICKED ===');
                    console.log('Event:', event);
                    console.log('Button element:', this);
                    try {
                        const donorId = window.currentInterviewerDonorId;
                        console.log('Proceed to medical history clicked, donor ID:', donorId);
                if (!donorId) {
                    console.error('No donor ID available for medical history processing');
                    return;
                }
                // Close confirmation modal and wait for full hide before showing custom MH modal
                const confirmEl = document.getElementById('processMedicalHistoryConfirmModal');
                const confirmModal = bootstrap.Modal.getInstance(confirmEl);
                const openInterviewerMH = function(){
                    console.log('Calling openMedicalHistoryModal with donor ID:', donorId);
                    // Test if modal element exists and can be shown
                    const testModal = document.getElementById('medicalHistoryModal');
                    console.log('Test modal element:', testModal);
                    if (testModal) {
                        console.log('Modal classes before:', testModal.className);
                        console.log('Modal style before:', testModal.style.display);
                    }
                    // For interviewer workflow, open the custom medical history form modal
                    console.log('Opening medical history form for interviewer workflow');
                    const medicalHistoryModal = document.getElementById('medicalHistoryModal');
                    if (medicalHistoryModal) {
                        console.log('Found medical history modal, opening it');
                        // Ensure clean modal state
                        try { cleanupModalState(); } catch(_) {}
                        
                        // Show the modal using CSS classes
                        medicalHistoryModal.classList.add('show');
                        
                        // Add modal-open class to body
                        document.body.classList.add('modal-open');
                        
                        console.log('Medical history modal opened successfully');
                        
                        // Load the medical history content for interviewer workflow
                        loadMedicalHistoryContentForInterviewer(donorId);
                    } else {
                        console.error('Medical history modal not found!');
                    }
                };
                if (confirmModal) {
                    // Wait for Bootstrap to finish removing the backdrop
                    confirmEl.addEventListener('hidden.bs.modal', function onHidden(){
                        confirmEl.removeEventListener('hidden.bs.modal', onHidden);
                        try { cleanupModalState(); } catch(_) {}
                        openInterviewerMH();
                    }, { once: true });
                    confirmModal.hide();
                } else {
                    try { cleanupModalState(); } catch(_) {}
                    openInterviewerMH();
                }
                    } catch (error) {
                        console.error('Error in proceed button handler:', error);
                    }
                });
            } else {
                console.error('Proceed button not found!');
            }
            // Medical History completion is now handled by the simple completion modal
            // No additional action needed - users can manually click the separate Screening button
            // Handle Print Declaration Form
            const printBtn = document.getElementById('printDeclarationFormBtn');
            console.log('Print button found:', printBtn);
            if (printBtn) {
                printBtn.addEventListener('click', function() {
                const donorId = window.currentInterviewerDonorId;
                if (!donorId) {
                    console.error('No donor ID available for declaration form');
                    return;
                }
                // Close success modal
                const successModal = bootstrap.Modal.getInstance(document.getElementById('screeningSubmittedSuccessModal'));
                successModal.hide();
                // Open declaration form modal
                openDeclarationFormForInterviewer(donorId);
                });
            } else {
                console.error('Print button not found!');
            }
        });
        // Function to add interviewer workflow buttons to medical history modal
        function addInterviewerWorkflowButtons(donorId) {
            const modalContent = document.getElementById('medicalHistoryModalContent');
            // Find the form or add buttons after the content
            const existingButtons = modalContent.querySelector('.modal-footer, .form-actions, .button-group');
            if (existingButtons) {
                // Add interviewer-specific buttons
                const interviewerButtons = document.createElement('div');
                interviewerButtons.className = 'interviewer-workflow-buttons mt-3';
                interviewerButtons.innerHTML = `
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" onclick="closeMedicalHistoryModal()">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-arrow-right me-1"></i>Proceed to Initial Screening
                        </button>
                    </div>
                `;
                existingButtons.appendChild(interviewerButtons);
            } else {
                // Add buttons at the end of the content
                const buttonsDiv = document.createElement('div');
                buttonsDiv.className = 'interviewer-workflow-buttons mt-3';
                buttonsDiv.innerHTML = `
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" onclick="closeMedicalHistoryModal()">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-arrow-right me-1"></i>Proceed to Initial Screening
                        </button>
                    </div>
                `;
                modalContent.appendChild(buttonsDiv);
            }
        }
        
        
        // Medical History completion now shows simple confirmation modal
        // Screening is handled separately by the Screening button
        // Function to open screening form for interviewer workflow
        function openScreeningFormForInterviewer(donorId) {
            console.log('Opening admin screening form for interviewer workflow:', donorId);
            
            // Use admin-specific modal
            if (typeof window.openAdminScreeningModal === 'function') {
                window.openAdminScreeningModal({ donor_id: donorId });
            } else {
                console.error('openAdminScreeningModal function not found');
            }
        }
        // Function to open admin declaration form for interviewer workflow
        function openDeclarationFormForInterviewer(donorId) {
            console.log('Opening admin declaration form for interviewer workflow:', donorId);
            const modal = document.getElementById('declarationFormModal');
            if (!modal) {
                console.error('Admin declaration form modal not found');
                return;
            }
            // Show the modal
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
            // Load admin declaration form content
            const modalContent = document.getElementById('declarationFormModalContent');
            modalContent.innerHTML = `
                <div class="d-flex justify-content-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            // Fetch admin declaration form content
            fetch(`../../src/views/forms/admin-declaration-form-modal-content.php?donor_id=${donorId}`)
                .then(response => response.text())
                .then(data => {
                    modalContent.innerHTML = data;
                    // Ensure print function is available
                    window.printAdminDeclaration = function() {
                        const printWindow = window.open('', '_blank');
                        const content = document.querySelector('.declaration-header')?.outerHTML +
                                       document.querySelector('.donor-info')?.outerHTML +
                                       document.querySelector('.declaration-content')?.outerHTML +
                                       document.querySelector('.signature-section')?.outerHTML;
                        printWindow.document.write(`
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <title>Admin Declaration Form</title>
                            </head>
                            <body>
                                ${content || 'Admin declaration form content not available'}
                            </body>
                            </html>
                        `);
                        printWindow.document.close();
                        printWindow.print();
                    };
                    // Ensure submit function is available globally since inline scripts won't execute on innerHTML
                    window.submitAdminDeclarationForm = function(event) {
                        try {
                            if (event && typeof event.preventDefault === 'function') {
                                event.preventDefault();
                            }
                            const form = document.getElementById('modalAdminDeclarationForm');
                            if (!form) {
                                alert('Form not found. Please try again.');
                                return;
                            }
                            const actionInput = document.getElementById('modalAdminDeclarationAction');
                            if (actionInput) {
                                actionInput.value = 'complete';
                            }
                            const formData = new FormData(form);
                            // Include screening data if available from previous step
                            if (window.currentScreeningData) {
                                try {
                                    formData.append('screening_data', JSON.stringify(window.currentScreeningData));
                                } catch (_) {}
                            }
                            fetch('../../src/views/forms/admin-declaration-form-process.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(function(response) {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok: ' + response.status);
                                }
                                return response.json().catch(function() { return {}; });
                            })
                            .then(function(data) {
                                if (data && data.success) {
                                    if (window.adminModal && window.adminModal.alert) {
                                        window.adminModal.alert('Registration completed successfully!').then(() => {
                                            window.location.reload();
                                        });
                                    } else {
                                        window.location.reload();
                                    }
                                } else {
                                    const message = (data && data.message) ? data.message : 'Unknown error occurred';
                                    if (window.adminModal && window.adminModal.alert) {
                                        window.adminModal.alert('Error: ' + message);
                                    } else {
                                        console.error('Admin modal not available');
                                    }
                                }
                            })
                            .catch(function(error) {
                                console.error('Admin declaration submit failed:', error);
                                if (window.adminModal && window.adminModal.alert) {
                                    window.adminModal.alert('Submission failed. Please try again.');
                                } else {
                                    console.error('Admin modal not available');
                                }
                            });
                        } catch (err) {
                            console.error('Unexpected error submitting admin declaration:', err);
                            if (window.adminModal && window.adminModal.alert) {
                                window.adminModal.alert('Unexpected error. Please try again.');
                            } else {
                                console.error('Admin modal not available');
                            }
                        }
                    };
                })
                .catch(error => {
                    console.error('Error loading admin declaration form:', error);
                    modalContent.innerHTML = '<div class="alert alert-danger">Error loading admin declaration form. Please try again.</div>';
                });
        }
        // Function to close medical history modal
        function closeMedicalHistoryModal() {
            closeMedicalHistoryModalUnified();
        }
    </script>
    <!-- Admin Medical History Modal Container (content fetched from staff modal content) -->
    <div class="modal fade" id="medicalHistoryModalAdmin" tabindex="-1" aria-labelledby="medicalHistoryModalAdminLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title" id="medicalHistoryModalAdminLabel"><i class="fas fa-clipboard-list me-2"></i>Medical History Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="medicalHistoryModalAdminContent">
                    <div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
                <div class="modal-footer border-0 justify-content-end" id="medicalHistoryModalAdminFooter"></div>
            </div>
        </div>
    </div>
    <!-- Admin Screening Form Modal Container (admin-specific modal) -->
    <div class="modal fade" id="adminScreeningFormModal" tabindex="-1" aria-labelledby="adminScreeningFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title" id="adminScreeningFormModalLabel"><i class="fas fa-clipboard-check me-2"></i>Initial Screening Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="adminScreeningFormModalContent">
                    <div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Screening Summary Modal for Physician Review -->
    <div class="modal fade" id="screeningSummaryModal" tabindex="-1" aria-labelledby="screeningSummaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" style="max-width: 600px;">
            <div class="modal-content" style="border: none; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #b22222 100%); color: white; border-radius: 15px 15px 0 0; padding: 1.5rem; border: none;">
                    <div>
                        <h5 class="modal-title mb-0" id="screeningSummaryModalLabel" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-clipboard-check"></i>
                            Initial Screening Form
                        </h5>
                        <small class="text-white-50" style="font-size: 0.875rem; opacity: 0.9; margin: 0.25rem 0 0 0;">To be filled up by the interviewer</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="background: none; border: none; color: white; font-size: 1.5rem; opacity: 0.8;"></button>
                </div>
                
                <div class="modal-body" id="screeningSummaryModalContent" style="padding: 2rem; background: white;">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Physician Section Modal for Physical Examination Review -->
    <div class="modal fade" id="physicianSectionModal" tabindex="-1" aria-labelledby="physicianSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" style="max-width: 600px;">
            <div class="modal-content" style="border: none; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #b22222 100%); color: white; border-radius: 15px 15px 0 0; padding: 1.5rem; border: none;">
                    <div>
                        <h5 class="modal-title mb-0" id="physicianSectionModalLabel" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-user-md"></i>
                            Physical Examination Summary
                        </h5>
                        <small class="text-white-50" style="font-size: 0.875rem; opacity: 0.9; margin: 0.25rem 0 0 0;">To be filled up by the physician</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="background: none; border: none; color: white; font-size: 1.5rem; opacity: 0.8;"></button>
                </div>
                
                <div class="modal-body" id="physicianSectionModalContent" style="padding: 1.5rem; background: white;">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../../assets/js/unified-search_admin.js" defer></script>
    <script>
        // Wait for DOM and UnifiedSearch to be ready
        function initializeSearch() {
            if (typeof UnifiedSearch === 'undefined') {
                console.log('UnifiedSearch not loaded yet, retrying...');
                setTimeout(initializeSearch, 100);
                return;
            }
            
            try {
                console.log('Initializing UnifiedSearch...');
                // Get current status from URL
                var currentStatus = '<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>';
                console.log('Current status filter:', currentStatus);
                
                // Verify search input exists
                var searchInput = document.getElementById('searchInput');
                var searchLoading = document.getElementById('searchLoading');
                var searchInfo = document.getElementById('searchInfo');
                console.log('Search input found:', !!searchInput);
                console.log('Search loading found:', !!searchLoading);
                console.log('Search info found:', !!searchInfo);
                
                if (!searchInput) {
                    console.error('CRITICAL: searchInput element not found!');
                    return;
                }
                
                var adminSearch = new UnifiedSearch({
                    inputId: 'searchInput',
                    categoryId: 'searchCategory',
                    tableId: 'donationsTable',
                    rowSelector: 'tbody tr:not(.no-results)',
                    mode: 'backend',  // Search entire database, not just current page
                    debounceMs: 500,  // Slightly longer delay since we're querying database
                    highlight: false,
                    autobind: true,  // Auto-bind to input events
                    columnsMapping: {
                        all: 'all',
                        donor: [1, 2],
                        donor_number: [0],
                        donor_type: [3],
                        registered_via: [4],
                        status: [5]
                    },
                    backend: {
                        url: '../api/unified-search_admin.php',
                        action: 'donors',
                        status: currentStatus,  // Pass current status filter
                        pageSize: 100  // Show up to 100 results from database
                    },
                    onClear: function() {
                        // This will be called when search is cleared (empty query detected)
                        console.log('UnifiedSearch: onClear callback triggered');
                        restoreOriginalTable();
                    },
                    renderResults: function(data) {
                        try {
                            console.log('renderResults called with data:', data);
                            var table = document.getElementById('donationsTable');
                            if (!table) {
                                console.error('Table not found');
                                return;
                            }
                            var tbody = table.querySelector('tbody');
                            if (!tbody) {
                                console.error('Table body not found');
                                return;
                            }
                            
                            // Clear existing rows completely
                            tbody.innerHTML = '';
                            
                            // Update search info immediately
                            var searchInfo = document.getElementById('searchInfo');
                            
                            if (!data || !data.success) {
                                console.error('Search failed or returned error:', data);
                                var errorRow = document.createElement('tr');
                                errorRow.className = 'no-results';
                                errorRow.innerHTML = '<td colspan="7" class="text-center"><div class="alert alert-warning m-2">Search error. Please try again.</div></td>';
                                tbody.appendChild(errorRow);
                                if (searchInfo) {
                                    searchInfo.textContent = 'Search error';
                                }
                                return;
                            }
                            
                            if (!Array.isArray(data.results) || data.results.length === 0) {
                                // No results - show message
                                var noResultsRow = document.createElement('tr');
                                noResultsRow.className = 'no-results';
                                noResultsRow.innerHTML = '<td colspan="7" class="text-center"><div class="alert alert-info m-2">No matching donors found <button class="btn btn-outline-primary btn-sm ms-2" onclick="clearSearch()">Clear Search</button></div></td>';
                                tbody.appendChild(noResultsRow);
                                
                                if (searchInfo) {
                                    searchInfo.textContent = 'No results found';
                                }
                                return;
                            }
                            
                            // Render results from API
                            for (var i = 0; i < data.results.length; i++) {
                                var r = data.results[i];
                                var tr = document.createElement('tr');
                                tr.className = 'donor-row';
                                tr.setAttribute('data-donor-id', r[0] || '');
                                tr.setAttribute('data-eligibility-id', r[6] || '');
                                
                                var donorType = String(r[3] || 'New').toLowerCase();
                                var isReturning = donorType === 'returning';
                                
                                var regChannel = String(r[4] || 'PRC Portal');
                                var regDisplay = regChannel === 'PRC Portal' ? 'PRC System' : (regChannel === 'Mobile' ? 'Mobile System' : regChannel);
                                
                                var status = r[5] || 'Pending (Screening)';
                                var statusClass = status.includes('Collection') ? 'bg-success' : 
                                                 status.includes('Examination') ? 'bg-info' : 
                                                 status.includes('Approved') ? 'bg-success' :
                                                 status.includes('Declined') ? 'bg-danger' : 'bg-warning';
                                
                                tr.innerHTML = '<td>' + escapeHtml(r[0] || '') + '</td>' +
                                               '<td>' + escapeHtml(r[1] || '') + '</td>' +
                                               '<td>' + escapeHtml(r[2] || '') + '</td>' +
                                               '<td><span class="badge ' + (isReturning ? 'bg-info' : 'bg-primary') + '">' + escapeHtml(r[3] || 'New') + '</span></td>' +
                                               '<td>' + escapeHtml(regDisplay) + '</td>' +
                                               '<td><span class="badge ' + statusClass + '">' + escapeHtml(status) + '</span></td>' +
                                               '<td><button type="button" class="btn btn-info btn-sm" data-donor-id="' + escapeHtml(r[0] || '') + '" data-eligibility-id="' + escapeHtml(r[6] || '') + '"><i class="fas fa-eye"></i></button></td>';
                                tbody.appendChild(tr);
                            }
                            
                            // Update search info with result count
                            if (searchInfo) {
                                var total = data.pagination && data.pagination.total ? data.pagination.total : data.results.length;
                                searchInfo.textContent = 'Found ' + data.results.length + ' result' + (data.results.length !== 1 ? 's' : '') + (total > data.results.length ? ' (showing ' + data.results.length + ' of ' + total + ')' : '');
                                searchInfo.style.display = 'block';
                            }
                            
                            // Re-bind row click handlers for new rows
                            setTimeout(function() {
                                document.querySelectorAll('tr.donor-row').forEach(function(row) {
                                    row.addEventListener('click', function() {
                                        var donorId = this.getAttribute('data-donor-id') || '';
                                        var eligibilityId = this.getAttribute('data-eligibility-id') || '';
                                        if (typeof openDetails === 'function') {
                                            openDetails(donorId, eligibilityId);
                                        }
                                    });
                                });
                            }, 100);
                        } catch (e) {
                            console.error('Error rendering search results:', e);
                        }
                    }
                });
                
                // Helper function to escape HTML
                function escapeHtml(text) {
                    if (!text) return '';
                    var div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                window.adminUnifiedSearch = adminSearch;
                
                // Store original table HTML for "clear search" functionality
                window.originalTableHTML = null;
                var table = document.getElementById('donationsTable');
                if (table) {
                    window.originalTableHTML = table.querySelector('tbody').innerHTML;
                }
                
                // Function to restore original table HTML
                function restoreOriginalTable() {
                    var tbody = document.querySelector('#donationsTable tbody');
                    if (window.originalTableHTML && tbody) {
                        tbody.innerHTML = window.originalTableHTML;
                        // Re-bind event handlers after restoring original HTML
                        setTimeout(function() {
                            document.querySelectorAll('tr.donor-row').forEach(function(row) {
                                row.addEventListener('click', function() {
                                    var donorId = this.getAttribute('data-donor-id') || '';
                                    var eligibilityId = this.getAttribute('data-eligibility-id') || '';
                                    if (typeof openDetails === 'function') {
                                        openDetails(donorId, eligibilityId);
                                    }
                                });
                            });
                        }, 100);
                    }
                    // Clear search info
                    var searchInfo = document.getElementById('searchInfo');
                    if (searchInfo) searchInfo.textContent = '';
                }
                
                // Clear search function - restore original table
                window.clearSearch = function() {
                    var searchInput = document.getElementById('searchInput');
                    
                    // Clear input value (this will trigger onInput)
                    if (searchInput) {
                        searchInput.value = '';
                        // Trigger input event to notify UnifiedSearch that search was cleared
                        var event = new Event('input', { bubbles: true });
                        searchInput.dispatchEvent(event);
                        // The onClear callback in UnifiedSearch will handle restoring the table
                    }
                };
                
                console.log('UnifiedSearch initialized successfully');
            } catch (e) {
                console.error('Error initializing search:', e);
            }
        }
        
        // Initialize when DOM and UnifiedSearch are ready
        function waitForUnifiedSearch() {
            if (typeof UnifiedSearch === 'undefined') {
                setTimeout(waitForUnifiedSearch, 50);
                return;
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeSearch);
            } else {
                // DOM already loaded, initialize immediately
                initializeSearch();
            }
        }
        
        waitForUnifiedSearch();
    </script>

    <?php
    // NOTE: Mobile credentials modal is NOT shown on dashboards
    // It should ONLY appear on the declaration form when registering a new donor
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.AccessLockManagerAdmin) {
                window.AccessLockManagerAdmin.init({
                    scopes: ['blood_collection', 'medical_history', 'physical_examination'],
                    guardSelectors: ['.view-donor', '.circular-btn', 'button[data-donor-id]', 'button[data-eligibility-id]'],
                    endpoint: '../../assets/php_func/access_lock_manager_admin.php',
                    autoClaim: false
                });
            }
            window.currentAdminDonorId = null;
            ['donorModal', 'donorDetailsModal'].forEach(function(modalId) {
                const modal = document.getElementById(modalId);
                if (!modal) return;
                modal.addEventListener('hidden.bs.modal', function () {
                    window.currentAdminDonorId = null;
                    if (window.AccessLockManagerAdmin) {
                        window.AccessLockManagerAdmin.deactivate();
                    }
                });
            });
        });
    </script>
</body>
</html>