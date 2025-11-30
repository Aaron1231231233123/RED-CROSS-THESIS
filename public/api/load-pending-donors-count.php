<?php
// AJAX endpoint to load pending donors count
// This reduces initial page load time by deferring this check

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Set count_only parameter to fetch ALL pending donors for accurate count
// This ensures we get the complete count, not just the first batch
$_GET['count_only'] = '1';

// Include the donation_pending module
include_once __DIR__ . '/../Dashboards/module/donation_pending.php';

$pendingDonorsCount = isset($pendingDonations) && is_array($pendingDonations) ? count($pendingDonations) : 0;

echo json_encode([
    'success' => true,
    'pendingDonorsCount' => $pendingDonorsCount,
    'pendingDonations' => $pendingDonations ?? []
]);



