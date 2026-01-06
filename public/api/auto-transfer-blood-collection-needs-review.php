<?php
/**
 * API endpoint to trigger auto-transfer of stale blood_collection.needs_review
 * flags into medical_history.needs_review.
 *
 * This is primarily intended as a maintenance / background endpoint.
 * The same logic is also invoked server-side from the phlebotomist dashboard.
 */

header('Content-Type: application/json');

try {
    session_start();
    require_once '../../assets/conn/db_conn.php';
    require_once '../../assets/php_func/auto_transfer_blood_collection_needs_review.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST.'
        ]);
        exit;
    }

    // Optional: enforce that only logged-in staff can trigger this
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit;
    }

    $days = isset($_POST['days']) ? (int)$_POST['days'] : 1;
    if ($days < 1) {
        $days = 1;
    }

    $result = autoTransferBloodCollectionNeedsReview($days);

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
} catch (Throwable $e) {
    error_log('Error in auto-transfer-blood-collection-needs-review API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}


