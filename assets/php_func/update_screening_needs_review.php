<?php
session_start();
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['donor_id']) || empty($_POST['donor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing donor_id']);
    exit();
}

$donor_id = intval($_POST['donor_id']);

try {
    $url = SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . $donor_id;
    $now = gmdate('c');
    $payload = json_encode([
        'needs_review' => true,
        'updated_at' => $now
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        echo json_encode(['success' => true, 'updated_at' => $now]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Supabase error', 'http_code' => $http_code, 'response' => $response]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

