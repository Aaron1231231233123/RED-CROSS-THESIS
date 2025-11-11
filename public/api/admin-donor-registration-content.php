<?php
/**
 * API Endpoint: Get Admin Donor Registration Form Content
 * Returns form HTML for step 1 (Personal Data) or step 2 (Medical History)
 * Admin-only endpoint
 */

session_start();
require_once '../../assets/conn/db_conn.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin access required.']);
    exit();
}

// Get step parameter
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$donor_id = isset($_GET['donor_id']) ? $_GET['donor_id'] : null;

if ($step === 1) {
    // Return Personal Data form content
    ob_start();
    include '../../src/views/forms/admin-donor-personal-data-content.php';
    $content = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'step' => 1,
        'html' => $content
    ]);
} elseif ($step === 2) {
    // Return Medical History form content
    if (!$donor_id && !isset($_SESSION['donor_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Donor ID required for step 2']);
        exit();
    }
    
    // Use donor_id from parameter or session
    $donor_id = $donor_id ?: $_SESSION['donor_id'];
    $_SESSION['donor_id'] = $donor_id;
    
    ob_start();
    // We'll create a simplified version that loads the admin MH content
    include '../../src/views/forms/admin-donor-medical-history-content.php';
    $content = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'step' => 2,
        'html' => $content,
        'donor_id' => $donor_id
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid step']);
}
?>

