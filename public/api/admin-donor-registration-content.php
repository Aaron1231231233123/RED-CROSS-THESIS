<?php
/**
 * API Endpoint: Get Admin Donor Registration Form Content
 * Returns form HTML for step 1 (Personal Data) or step 2 (Medical History)
 * Admin-only endpoint
 */

session_start();
require_once '../../assets/conn/db_conn.php';

header('Content-Type: application/json');

// Check if user is logged in and authorized (admin or reviewer)
$roleId = $_SESSION['role_id'] ?? null;
$staffRole = $_SESSION['user_staff_roles'] ?? null;

$isReviewer = ($roleId == 3) && (is_string($staffRole) && strtolower(trim($staffRole)) === 'reviewer');
$isAdmin = ($roleId == 1);

if (!isset($_SESSION['user_id']) || !$isAdmin && !$isReviewer) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin or reviewer access required.']);
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
    
    // Enable error reporting for debugging (but don't display to user)
    $old_error_reporting = error_reporting(E_ALL);
    $old_display_errors = ini_get('display_errors');
    ini_set('display_errors', 0);
    
    try {
        ob_start();
        // We'll create a simplified version that loads the admin MH content
        $include_path = __DIR__ . '/../../src/views/forms/admin-donor-medical-history-content.php';
        if (!file_exists($include_path)) {
            throw new Exception('Medical history form file not found at: ' . $include_path);
        }
        include $include_path;
        $content = ob_get_clean();
        
        // Check if there were any errors in the output
        if (empty($content) || trim($content) === '') {
            throw new Exception('Medical history form content is empty');
        }
        
        // Restore error settings
        error_reporting($old_error_reporting);
        ini_set('display_errors', $old_display_errors);
        
        echo json_encode([
            'success' => true,
            'step' => 2,
            'html' => $content,
            'donor_id' => $donor_id
        ]);
    } catch (Exception $e) {
        // Restore error settings
        error_reporting($old_error_reporting);
        ini_set('display_errors', $old_display_errors);
        
        // Log the error
        error_log('Error loading medical history form: ' . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to load medical history form: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid step']);
}
?>

