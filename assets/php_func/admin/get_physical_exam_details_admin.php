<?php
header('Content-Type: application/json');
require_once '../../conn/db_conn.php';

// Supports either physical_exam_id or donor_id (use latest by donor if donor_id provided)
$physicalExamId = $_GET['physical_exam_id'] ?? null;
$donorId = $_GET['donor_id'] ?? null;

try {
    if ($physicalExamId) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . urlencode($physicalExamId) . '&select=*,screening_form!inner(*)',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]
        ]);
        $resp = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($code !== 200) {
            throw new Exception('Failed to fetch physical examination');
        }
        $arr = json_decode($resp, true);
        if (empty($arr)) {
            echo json_encode(['success' => false, 'message' => 'Physical examination not found']);
        } else {
            echo json_encode(['success' => true, 'data' => $arr[0]]);
        }
        exit;
    }

    if ($donorId) {
        // Prefer eligibility for body_weight; only use it as the source if it includes a valid physical_exam_id
        $eligCurl = curl_init();
        curl_setopt_array($eligCurl, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . urlencode($donorId) . '&select=physical_exam_id,body_weight&order=updated_at.desc,created_at.desc&limit=1',
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

        $eligRow = null;
        $physicalExamIdFromEligibility = null;
        if ($eligCode === 200 && $eligResp) {
            $eligArr = json_decode($eligResp, true);
            if (!empty($eligArr)) {
                $eligRow = $eligArr[0];
                // If eligibility has physical_exam_id, use it to fetch full PE record
                if (isset($eligRow['physical_exam_id']) && $eligRow['physical_exam_id']) {
                    $physicalExamIdFromEligibility = $eligRow['physical_exam_id'];
                }
            }
        }

        // Fetch full physical examination record by physical_exam_id if we have it from eligibility
        if ($physicalExamIdFromEligibility) {
            $peByIdCurl = curl_init();
            curl_setopt_array($peByIdCurl, [
                CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . urlencode($physicalExamIdFromEligibility) . '&select=*',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]
            ]);
            $peByIdResp = curl_exec($peByIdCurl);
            $peByIdCode = curl_getinfo($peByIdCurl, CURLINFO_HTTP_CODE);
            curl_close($peByIdCurl);
            
            if ($peByIdCode === 200 && $peByIdResp) {
                $peByIdArr = json_decode($peByIdResp, true);
                if (!empty($peByIdArr)) {
                    $peRow = $peByIdArr[0];
                    // If eligibility row had body_weight but PE row does not, prefer the eligibility weight
                    if ($eligRow && isset($eligRow['body_weight']) && !isset($peRow['body_weight'])) {
                        $peRow['body_weight'] = $eligRow['body_weight'];
                    }
                    echo json_encode(['success' => true, 'data' => $peRow]);
                    exit;
                }
            }
        }

        // Otherwise, fetch latest physical examination by donor with ALL fields
        $peCurl = curl_init();
        curl_setopt_array($peCurl, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . urlencode($donorId) . '&select=*&order=updated_at.desc,created_at.desc&limit=1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]
        ]);
        $peResp = curl_exec($peCurl);
        $peCode = curl_getinfo($peCurl, CURLINFO_HTTP_CODE);
        curl_close($peCurl);
        if ($peCode === 200 && $peResp) {
            $peArr = json_decode($peResp, true);
            if (!empty($peArr)) {
                $peRow = $peArr[0];
                // If eligibility row had body_weight but PE row does not, prefer the eligibility weight
                if ($eligRow && isset($eligRow['body_weight']) && !isset($peRow['body_weight'])) {
                    $peRow['body_weight'] = $eligRow['body_weight'];
                }
                echo json_encode(['success' => true, 'data' => $peRow]);
                exit;
            }
        }

        // If still not found, some datasets associate PE via screening_id. Resolve latest screening then PE by screening_id
        $sfCurl = curl_init();
        curl_setopt_array($sfCurl, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . urlencode($donorId) . '&select=screening_id&order=created_at.desc&limit=1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]
        ]);
        $sfResp = curl_exec($sfCurl);
        $sfCode = curl_getinfo($sfCurl, CURLINFO_HTTP_CODE);
        curl_close($sfCurl);
        if ($sfCode === 200 && $sfResp) {
            $sfArr = json_decode($sfResp, true) ?: [];
            if (!empty($sfArr) && isset($sfArr[0]['screening_id'])) {
                $screeningId = $sfArr[0]['screening_id'];
                $peBySfCurl = curl_init();
                curl_setopt_array($peBySfCurl, [
                    CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?screening_id=eq.' . urlencode($screeningId) . '&select=*&order=updated_at.desc,created_at.desc&limit=1',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Accept: application/json'
                    ]
                ]);
                $peBySfResp = curl_exec($peBySfCurl);
                $peBySfCode = curl_getinfo($peBySfCurl, CURLINFO_HTTP_CODE);
                curl_close($peBySfCurl);
                if ($peBySfCode === 200 && $peBySfResp) {
                    $peSfArr = json_decode($peBySfResp, true) ?: [];
                    if (!empty($peSfArr)) {
                        $peRow = $peSfArr[0];
                        if ($eligRow && isset($eligRow['body_weight']) && !isset($peRow['body_weight'])) {
                            $peRow['body_weight'] = $eligRow['body_weight'];
                        }
                        echo json_encode(['success' => true, 'data' => $peRow]);
                        exit;
                    }
                }
            }
        }

        // Nothing found
        echo json_encode(['success' => true, 'data' => null]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
} catch (Exception $e) {
    error_log('Error in get_physical_exam_details_admin.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


