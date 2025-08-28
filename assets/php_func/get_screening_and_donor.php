<?php
header('Content-Type: application/json');
require_once '../conn/db_conn.php';

try {
    if (!isset($_GET['donor_id'])) {
        echo json_encode(['success' => false, 'message' => 'donor_id is required']);
        exit;
    }

    $donor_id = intval($_GET['donor_id']);
    if ($donor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid donor_id']);
        exit;
    }

    // Fetch donor_form
    $chDonor = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=*&donor_id=eq.' . $donor_id . '&limit=1');
    curl_setopt($chDonor, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chDonor, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    $donorRes = curl_exec($chDonor);
    $donorCode = curl_getinfo($chDonor, CURLINFO_HTTP_CODE);
    curl_close($chDonor);

    if ($donorCode !== 200) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch donor', 'http' => $donorCode]);
        exit;
    }
    $donors = json_decode($donorRes, true) ?: [];
    $donor = !empty($donors) ? $donors[0] : null;

    // Fetch latest screening_form by donor_id (donor_form_id is a FK to donor_form.donor_id)
    $chScreen = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=*&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($chScreen, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chScreen, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    $screenRes = curl_exec($chScreen);
    $screenCode = curl_getinfo($chScreen, CURLINFO_HTTP_CODE);
    curl_close($chScreen);

    if ($screenCode !== 200) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch screening', 'http' => $screenCode]);
        exit;
    }
    $screens = json_decode($screenRes, true) ?: [];
    $screening = !empty($screens) ? $screens[0] : null;

    echo json_encode([
        'success' => true,
        'data' => [
            'donor' => $donor,
            'screening' => $screening
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


