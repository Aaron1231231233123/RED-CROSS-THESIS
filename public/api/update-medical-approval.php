<?php
// Update medical_history.medical_approval for a donor_id
header('Content-Type: application/json');

try {
    require_once '../../assets/conn/db_conn.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        exit;
    }

    $donorId = isset($_POST['donor_id']) ? trim($_POST['donor_id']) : '';
    $approval = isset($_POST['medical_approval']) ? trim($_POST['medical_approval']) : '';

    if ($donorId === '' || $approval === '') {
        echo json_encode(['success' => false, 'message' => 'Missing donor_id or medical_approval']);
        exit;
    }

    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/medical_history?donor_id=eq.' . urlencode($donorId);
    $payload = json_encode([
        'medical_approval' => $approval,
        'updated_at' => date('c')
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
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
        echo json_encode(['success' => false, 'message' => 'Failed to update medical_history', 'http' => $code]);
        exit;
    }

    $data = json_decode($resp, true);
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


