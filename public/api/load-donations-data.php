<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$itemsPerPage = 10;

// OPTIMIZATION: Use aggressive caching for API
$cacheKey = 'donations_api_' . $status . '_p' . $page;
$cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.json';
$cacheAge = 0;

// Check cache first
if (file_exists($cacheFile)) {
    $cacheAge = time() - filemtime($cacheFile);
    if ($cacheAge < 300) { // 5 minutes cache
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cachedData)) {
            header('X-Cache-Status: HIT');
            header('X-Cache-Age: ' . $cacheAge);
            echo json_encode($cachedData);
            exit();
        }
    }
}

// Load data based on status
$donations = [];
$totalItems = 0;
$totalPages = 0;

try {
    switch ($status) {
        case 'pending':
            include_once '../Dashboards/module/donation_pending.php';
            $donations = $pendingDonations ?? [];
            break;
        case 'approved':
            include_once '../Dashboards/module/donation_approved.php';
            $donations = $approvedDonations ?? [];
            break;
        case 'declined':
        case 'deferred':
            include_once '../Dashboards/module/donation_declined.php';
            $donations = $declinedDonations ?? [];
            break;
        case 'all':
        default:
            // Load all data
            $allDonations = [];
            
            // Pending
            include_once '../Dashboards/module/donation_pending.php';
            if (isset($pendingDonations) && is_array($pendingDonations)) {
                $allDonations = array_merge($allDonations, $pendingDonations);
            }
            
            // Approved
            include_once '../Dashboards/module/donation_approved.php';
            if (isset($approvedDonations) && is_array($approvedDonations)) {
                $allDonations = array_merge($allDonations, $approvedDonations);
            }
            
            // Declined
            include_once '../Dashboards/module/donation_declined.php';
            if (isset($declinedDonations) && is_array($declinedDonations)) {
                $allDonations = array_merge($allDonations, $declinedDonations);
            }
            
            // Sort by date
            usort($allDonations, function($a, $b) {
                $dateA = $a['date_submitted'] ?? $a['rejection_date'] ?? '';
                $dateB = $b['date_submitted'] ?? $b['rejection_date'] ?? '';
                return strtotime($dateB) - strtotime($dateA);
            });
            
            $donations = $allDonations;
            break;
    }
    
    // Calculate pagination
    $totalItems = count($donations);
    $totalPages = ceil($totalItems / $itemsPerPage);
    $startIndex = ($page - 1) * $itemsPerPage;
    $currentPageDonations = array_slice($donations, $startIndex, $itemsPerPage);
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => $currentPageDonations,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage
        ],
        'status' => $status,
        'timestamp' => time()
    ];
    
    // Cache the response
    file_put_contents($cacheFile, json_encode($response));
    
    header('X-Cache-Status: MISS');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load donations data: ' . $e->getMessage()
    ]);
}
?>
