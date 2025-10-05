<?php
header('Content-Type: application/json');
require_once '../../conn/db_conn.php';

if (!isset($_GET['donor_id']) || $_GET['donor_id'] === '') {
    echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
    exit();
}

$donorId = $_GET['donor_id'];

try {
    // Fetch donor basic info from donor_form
    $donorCurl = curl_init();
    curl_setopt_array($donorCurl, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . urlencode($donorId) . '&select=donor_id,surname,first_name,middle_name,birthdate,sex&limit=1',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]
    ]);
    $donorResp = curl_exec($donorCurl);
    $donorCode = curl_getinfo($donorCurl, CURLINFO_HTTP_CODE);
    curl_close($donorCurl);

    if ($donorCode !== 200) {
        throw new Exception('Failed to fetch donor info');
    }
    $donorArr = json_decode($donorResp, true);
    if (empty($donorArr)) {
        echo json_encode(['success' => false, 'message' => 'Donor not found']);
        exit();
    }
    $donor = $donorArr[0];

    // Fetch latest eligibility to get blood_type and body_weight
    $eligCurl = curl_init();
    curl_setopt_array($eligCurl, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . urlencode($donorId) . '&select=blood_type,body_weight&order=updated_at.desc,created_at.desc&limit=1',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]
    ]);
    $eligResp = curl_exec($eligCurl);
    $eligCode = curl_getinfo($eligCurl, CURLINFO_HTTP_CODE);
    curl_close($eligCurl);

    $bloodType = null;
    if ($eligCode === 200) {
        $eligArr = json_decode($eligResp, true);
        if (!empty($eligArr)) {
            if (isset($eligArr[0]['blood_type'])) {
                $bloodType = $eligArr[0]['blood_type'];
            }
            if (isset($eligArr[0]['body_weight'])) {
                $donor['body_weight'] = $eligArr[0]['body_weight'];
            }
        }
    }

    // Fallback: derive values from latest screening_form (blood_type and body_weight)
    if ($bloodType === null || !isset($donor['body_weight'])) {
        // Try by donor_form_id first
        $scrCurl = curl_init();
        curl_setopt_array($scrCurl, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . urlencode($donorId) . '&select=blood_type,body_weight&order=created_at.desc&limit=1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]
        ]);
        $scrResp = curl_exec($scrCurl);
        $scrCode = curl_getinfo($scrCurl, CURLINFO_HTTP_CODE);
        curl_close($scrCurl);
        $scrArr = [];
        if ($scrCode === 200) {
            $scrArr = json_decode($scrResp, true);
        }
        // If not found via donor_form_id, try donor_id
        if (empty($scrArr)) {
            $scrCurl2 = curl_init();
            curl_setopt_array($scrCurl2, [
                CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?donor_id=eq.' . urlencode($donorId) . '&select=blood_type,body_weight&order=created_at.desc&limit=1',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]
            ]);
            $scrResp2 = curl_exec($scrCurl2);
            $scrCode2 = curl_getinfo($scrCurl2, CURLINFO_HTTP_CODE);
            curl_close($scrCurl2);
            if ($scrCode2 === 200) {
                $scrArr = json_decode($scrResp2, true);
            }
        }
        if (!empty($scrArr)) {
            if ($bloodType === null && isset($scrArr[0]['blood_type'])) {
                $bloodType = $scrArr[0]['blood_type'];
            }
            if (!isset($donor['body_weight']) && isset($scrArr[0]['body_weight'])) {
                $donor['body_weight'] = $scrArr[0]['body_weight'];
            }
        }
    }

    $payload = $donor;
    if ($bloodType !== null) { $payload['blood_type'] = $bloodType; }
    if (isset($donor['body_weight'])) { $payload['body_weight'] = $donor['body_weight']; }

    echo json_encode(['success' => true, 'data' => $payload]);
} catch (Exception $e) {
    error_log('Error in get_donor_details_admin.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


