<?php
session_start();
// Feature flag for performance optimizations and unified cache
// perf_mode defaults to on; can disable via ?perf_mode=off
$perfMode = !((isset($_GET['perf_mode']) && $_GET['perf_mode'] === 'off'));
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
$perfCursorTs = isset($_GET['cursor_ts']) ? $_GET['cursor_ts'] : null;
$perfCursorId = isset($_GET['cursor_id']) ? intval($_GET['cursor_id']) : null;

// Derive unified cache directory when perf mode is enabled
$localCacheDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Dashboards' . DIRECTORY_SEPARATOR . 'cache';
if ($perfMode && !is_dir($localCacheDir)) {
	@mkdir($localCacheDir, 0777, true);
}

// OPTIMIZATION: Enhanced caching for pagination with LCP focus
$cacheKey = 'donations_api_' . $status . '_p' . $page;
$cacheFile = $perfMode
	? ($localCacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json')
	: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cacheKey . '.json');
$cacheAge = 0;

// OPTIMIZATION: Shorter cache TTL for better data freshness during pagination
$cacheTTL = ($status === 'pending') ? 180 : 300; // 3 min for pending, 5 min for others

// Check cache first
if (file_exists($cacheFile)) {
    $cacheAge = time() - filemtime($cacheFile);
    if ($cacheAge < $cacheTTL) {
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cachedData)) {
            header('X-Cache-Status: HIT');
            header('X-Cache-Age: ' . $cacheAge);
            header('X-Perf-Mode: ' . ($perfMode ? 'on' : 'off'));
            header('X-Pagination-Cache: 1');
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
    // When including modules, provide limit/offset or cursor similar to the dashboard
    $startIndex = ($page - 1) * $itemsPerPage;
    if ($status !== 'all') {
        if ($status === 'pending') {
            if ($perfMode && $perfCursorTs) {
                $GLOBALS['DONATION_LIMIT'] = $itemsPerPage;
                $GLOBALS['DONATION_OFFSET'] = 0;
                $_GET['cursor_ts'] = $perfCursorTs;
                if ($perfCursorId) { $_GET['cursor_id'] = $perfCursorId; }
            } else {
                // Offset fallback
                $GLOBALS['DONATION_LIMIT'] = 200;
                $GLOBALS['DONATION_OFFSET'] = 0;
                $GLOBALS['DONATION_DISPLAY_OFFSET'] = $startIndex;
            }
        } else {
            $GLOBALS['DONATION_LIMIT'] = $itemsPerPage;
            $GLOBALS['DONATION_OFFSET'] = $startIndex;
        }
    }

    switch ($status) {
        case 'pending':
            include_once '../Dashboards/module/donation_pending.php';
            $donations = $pendingDonations ?? [];
            break;
        case 'approved':
            include_once '../Dashboards/module/donation_approved.php';
            $donations = $approvedDonations ?? [];
            if ($perfMode) {
                $prevCursor = [
                    'cursor_ts' => $GLOBALS['APPROVED_PREV_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['APPROVED_PREV_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'prev'
                ];
                $nextCursor = [
                    'cursor_ts' => $GLOBALS['APPROVED_NEXT_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['APPROVED_NEXT_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'next'
                ];
            }
            break;
        case 'declined':
        case 'deferred':
            include_once '../Dashboards/module/donation_declined.php';
            $donations = $declinedDonations ?? [];
            if ($perfMode) {
                $prevCursor = [
                    'cursor_ts' => $GLOBALS['DECLINED_PREV_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['DECLINED_PREV_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'prev'
                ];
                $nextCursor = [
                    'cursor_ts' => $GLOBALS['DECLINED_NEXT_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['DECLINED_NEXT_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'next'
                ];
            }
            break;
        case 'all':
        default:
            if ($perfMode) {
                // Aggregated incremental load with per-stream cursors
                $allocApproved = 4; $allocDeclined = 4; $allocPending = 2; // total = 10
                $all = [];
                $savedGet = $_GET;

                // Approved slice
                $GLOBALS['DONATION_LIMIT'] = $allocApproved; $GLOBALS['DONATION_OFFSET'] = 0;
                $_GET['perf_mode'] = 'on';
                if (isset($_GET['approved_cursor_ts'])) { $_GET['cursor_ts'] = $_GET['approved_cursor_ts']; }
                if (isset($_GET['approved_cursor_id'])) { $_GET['cursor_id'] = $_GET['approved_cursor_id']; }
                if (isset($_GET['approved_cursor_dir'])) { $_GET['cursor_dir'] = $_GET['approved_cursor_dir']; }
                include '../Dashboards/module/donation_approved.php';
                if (isset($approvedDonations) && is_array($approvedDonations)) { $all = array_merge($all, $approvedDonations); }
                $approvedPrev = [
                    'cursor_ts' => $GLOBALS['APPROVED_PREV_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['APPROVED_PREV_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'prev'
                ];
                $approvedNext = [
                    'cursor_ts' => $GLOBALS['APPROVED_NEXT_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['APPROVED_NEXT_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'next'
                ];

                // Declined slice
                $_GET = $savedGet; $GLOBALS['DONATION_LIMIT'] = $allocDeclined; $GLOBALS['DONATION_OFFSET'] = 0;
                $_GET['perf_mode'] = 'on';
                if (isset($_GET['declined_cursor_ts'])) { $_GET['cursor_ts'] = $_GET['declined_cursor_ts']; }
                if (isset($_GET['declined_cursor_id'])) { $_GET['cursor_id'] = $_GET['declined_cursor_id']; }
                if (isset($_GET['declined_cursor_dir'])) { $_GET['cursor_dir'] = $_GET['declined_cursor_dir']; }
                include '../Dashboards/module/donation_declined.php';
                if (isset($declinedDonations) && is_array($declinedDonations)) { $all = array_merge($all, $declinedDonations); }
                $declinedPrev = [
                    'cursor_ts' => $GLOBALS['DECLINED_PREV_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['DECLINED_PREV_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'prev'
                ];
                $declinedNext = [
                    'cursor_ts' => $GLOBALS['DECLINED_NEXT_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['DECLINED_NEXT_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'next'
                ];

                // Pending slice (offset fallback, small allocation)
                $_GET = $savedGet; $GLOBALS['DONATION_LIMIT'] = $allocPending; $GLOBALS['DONATION_OFFSET'] = 0;
                $_GET['perf_mode'] = 'on';
                if (isset($_GET['pending_cursor_ts'])) { $_GET['cursor_ts'] = $_GET['pending_cursor_ts']; }
                if (isset($_GET['pending_cursor_id'])) { $_GET['cursor_id'] = $_GET['pending_cursor_id']; }
                if (isset($_GET['pending_cursor_dir'])) { $_GET['cursor_dir'] = $_GET['pending_cursor_dir']; }
                include '../Dashboards/module/donation_pending.php';
                if (isset($pendingDonations) && is_array($pendingDonations)) { $all = array_merge($all, $pendingDonations); }
                $pendingPrev = [
                    'cursor_ts' => $GLOBALS['PENDING_PREV_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['PENDING_PREV_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'prev'
                ];
                $pendingNext = [
                    'cursor_ts' => $GLOBALS['PENDING_NEXT_CURSOR_TS'] ?? null,
                    'cursor_id' => $GLOBALS['PENDING_NEXT_CURSOR_ID'] ?? null,
                    'cursor_dir' => 'next'
                ];

                // Merge newest-first
                usort($all, function($a, $b) {
                    $dateA = $a['date_submitted'] ?? $a['rejection_date'] ?? '';
                    $dateB = $b['date_submitted'] ?? $b['rejection_date'] ?? '';
                    return strtotime($dateB) - strtotime($dateA);
                });
                $donations = array_slice($all, 0, $itemsPerPage);

                // Attach cursors to globals for response assembly below
                $GLOBALS['ALL_APPROVED_PREV'] = $approvedPrev;
                $GLOBALS['ALL_APPROVED_NEXT'] = $approvedNext;
                $GLOBALS['ALL_DECLINED_PREV'] = $declinedPrev;
                $GLOBALS['ALL_DECLINED_NEXT'] = $declinedNext;
                $GLOBALS['ALL_PENDING_PREV'] = $pendingPrev;
                $GLOBALS['ALL_PENDING_NEXT'] = $pendingNext;
            } else {
                // Existing behavior
                $allDonations = [];
                include_once '../Dashboards/module/donation_pending.php';
                if (isset($pendingDonations) && is_array($pendingDonations)) {
                    $allDonations = array_merge($allDonations, $pendingDonations);
                }
                include_once '../Dashboards/module/donation_approved.php';
                if (isset($approvedDonations) && is_array($approvedDonations)) {
                    $allDonations = array_merge($allDonations, $approvedDonations);
                }
                include_once '../Dashboards/module/donation_declined.php';
                if (isset($declinedDonations) && is_array($declinedDonations)) {
                    $allDonations = array_merge($allDonations, $declinedDonations);
                }
                usort($allDonations, function($a, $b) {
                    $dateA = $a['date_submitted'] ?? $a['rejection_date'] ?? '';
                    $dateB = $b['date_submitted'] ?? $b['rejection_date'] ?? '';
                    return strtotime($dateB) - strtotime($dateA);
                });
                $donations = $allDonations;
            }
            break;
    }
    
    // Calculate pagination or cursor for response
    if ($perfMode && $status === 'pending' && (isset($GLOBALS['PENDING_NEXT_CURSOR_TS']) || isset($GLOBALS['PENDING_PREV_CURSOR_TS']))) {
        $prevCursor = null;
        $nextCursor = null;
        if (isset($GLOBALS['PENDING_PREV_CURSOR_TS']) && isset($GLOBALS['PENDING_PREV_CURSOR_ID'])) {
            $prevCursor = [
                'cursor_ts' => $GLOBALS['PENDING_PREV_CURSOR_TS'],
                'cursor_id' => $GLOBALS['PENDING_PREV_CURSOR_ID'],
                'cursor_dir' => 'prev'
            ];
        }
        if (isset($GLOBALS['PENDING_NEXT_CURSOR_TS']) && isset($GLOBALS['PENDING_NEXT_CURSOR_ID'])) {
            $nextCursor = [
                'cursor_ts' => $GLOBALS['PENDING_NEXT_CURSOR_TS'],
                'cursor_id' => $GLOBALS['PENDING_NEXT_CURSOR_ID'],
                'cursor_dir' => 'next'
            ];
        }
        $totalItems = is_array($donations) ? count($donations) : 0;
        $totalPages = null; // unknown with keyset
        $currentPageDonations = $donations; // already limited by module
    } else {
        $totalItems = is_array($donations) ? count($donations) : 0;
        $totalPages = ($itemsPerPage > 0) ? (int)ceil($totalItems / $itemsPerPage) : 0;
        $currentPageDonations = array_slice($donations, $startIndex, $itemsPerPage);
        $nextCursor = null;
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => $currentPageDonations,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $itemsPerPage,
            'nextCursor' => $nextCursor ?? null
        ],
        'status' => $status,
        'timestamp' => time()
    ];

    // For aggregated 'all' with perf mode, include per-stream cursors
    if ($perfMode && $status === 'all') {
        $response['streams'] = [
            'approved' => [
                'prev' => $GLOBALS['ALL_APPROVED_PREV'] ?? null,
                'next' => $GLOBALS['ALL_APPROVED_NEXT'] ?? null
            ],
            'declined' => [
                'prev' => $GLOBALS['ALL_DECLINED_PREV'] ?? null,
                'next' => $GLOBALS['ALL_DECLINED_NEXT'] ?? null
            ],
            'pending' => [
                'prev' => $GLOBALS['ALL_PENDING_PREV'] ?? null,
                'next' => $GLOBALS['ALL_PENDING_NEXT'] ?? null
            ]
        ];
    }
    
    // Cache the response with per-status TTL semantics (file age checked on read)
    file_put_contents($cacheFile, json_encode($response));
    
    header('X-Cache-Status: MISS');
    header('X-Perf-Mode: ' . ($perfMode ? 'on' : 'off'));
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load donations data: ' . $e->getMessage()
    ]);
}
?>
