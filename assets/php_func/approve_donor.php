<?php
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['donor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
    exit;
}

$donorId = $data['donor_id'];

try {
    // Update the donor's status to approved
    $query = "UPDATE donor_form SET status = 'approved' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $donorId);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Donor approved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve donor']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 