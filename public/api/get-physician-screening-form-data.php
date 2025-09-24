<?php
// Return screening_form data for physician read-only modal by donor_id.
// Maps donor_id to screening_form.donor_form_id and returns latest row.

header('Content-Type: application/json');

try {
    require_once '../../assets/conn/db_conn.php';

    $donorId = isset($_GET['donor_id']) ? trim($_GET['donor_id']) : '';
    if ($donorId === '') {
        echo json_encode(['success' => false, 'message' => 'Missing donor_id']);
        exit;
    }

    // Build Supabase REST query: donor_form_id == donorId, order by created_at desc limit 1
    $base = rtrim(SUPABASE_URL, '/') . '/rest/v1/screening_form';
    $query = http_build_query([
        'select' => 'screening_id,donor_form_id,medical_history_id,body_weight,specific_gravity,blood_type,mobile_organizer,mobile_location,donation_type,created_at,updated_at',
        'donor_form_id' => 'eq.' . $donorId,
        'order' => 'created_at.desc',
        'limit' => 1
    ]);
    $url = $base . '?' . $query;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
        echo json_encode(['success' => false, 'message' => 'Failed to query screening_form', 'http' => $code]);
        exit;
    }

    $data = json_decode($resp, true);
    if (empty($data)) {
        echo json_encode(['success' => true, 'screening_form' => null]);
        exit;
    }

    echo json_encode(['success' => true, 'screening_form' => $data[0]]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>



