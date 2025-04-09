<?php
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Donor ID is required']);
    exit;
}

$donorId = $_GET['id'];

try {
    $query = "SELECT * FROM donor_form WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $donorId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($donor = mysqli_fetch_assoc($result)) {
        echo json_encode($donor);
    } else {
        echo json_encode(['error' => 'Donor not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} 