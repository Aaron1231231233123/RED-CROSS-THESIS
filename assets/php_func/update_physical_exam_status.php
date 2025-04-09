<?php
// Include database connection
include_once '../conn/db_conn.php';

// Set response headers
header('Content-Type: application/json');

/**
 * This file serves as a wrapper for process_physical_exam.php
 * It handles form submissions from the physical examination form
 * and updates the eligibility status based on the Enum remarks field
 */

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Only POST method is allowed'
    ]);
    exit;
}

// Get form data
$formData = [];
if (isset($_POST['donor_id']) && !empty($_POST['donor_id'])) {
    $formData['donor_id'] = $_POST['donor_id'];
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Missing donor_id'
    ]);
    exit;
}

// Check if we have a physical exam ID
if (isset($_POST['physical_exam_id']) && !empty($_POST['physical_exam_id'])) {
    $formData['physical_exam_id'] = $_POST['physical_exam_id'];
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Missing physical_exam_id'
    ]);
    exit;
}

// Get remarks (Enum value)
if (isset($_POST['remarks'])) {
    $formData['remarks'] = $_POST['remarks'];
    
    // Validate that remarks is one of the allowed Enum values
    $allowedRemarks = ['Accepted', 'Temporarily Deferred', 'Permanently Deferred', 'Refused'];
    if (!in_array($formData['remarks'], $allowedRemarks)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid remarks value. Must be one of: ' . implode(', ', $allowedRemarks)
        ]);
        exit;
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Missing remarks field'
    ]);
    exit;
}

// Get reason for rejection if available
if (isset($_POST['reason'])) {
    $formData['reason'] = $_POST['reason'];
} else if (isset($_POST['disapproval_reason'])) {
    $formData['reason'] = $_POST['disapproval_reason'];
}

// Log the data we're about to process
error_log("Processing physical exam with data: " . json_encode($formData));

// Include the process_physical_exam.php file
include_once 'process_physical_exam.php';

// Use the function to process the physical examination and update eligibility
$result = processPhysicalExam(
    $formData['physical_exam_id'],
    $formData['donor_id'],
    $formData['remarks'],
    $formData['reason'] ?? ''
);

// For successful requests, add redirect information
if ($result['success']) {
    // Add redirect information based on the status
    if ($result['status'] === 'approved') {
        $result['redirect'] = '../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?status=approved';
    } else if ($result['status'] === 'declined') {
        $result['redirect'] = '../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?status=declined';
    } else {
        $result['redirect'] = '../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?status=pending';
    }
}

// Return the result
echo json_encode($result); 