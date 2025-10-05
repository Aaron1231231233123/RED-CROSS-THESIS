<?php
// Helpers for quick search of Physical Examination rows for Physician Dashboard
// Builds rows compatible with dashboard-staff-physical-submission table rendering

require_once __DIR__ . '/../../../conn/db_conn.php';

function spe_supabase_get($endpoint, $select = [], $filters = []){
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $params = [];
    if (!empty($select)) $params[] = 'select=' . implode(',', $select);
    foreach ($filters as $k => $v) { $params[] = $k . '=' . urlencode($v); }
    if (!empty($params)) $url .= '?' . implode('&', $params);
    $ch = curl_init($url);
    $headers = [ 'apikey: ' . SUPABASE_API_KEY, 'Authorization: Bearer ' . SUPABASE_API_KEY, 'Accept: application/json' ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return is_array($data) ? $data : [];
}

function spe_normalize_bool($v){
    if ($v === true || $v === 1 || $v === '1') return true;
    if (is_string($v)) { $s = strtolower(trim($v)); return in_array($s, ['true','t','yes','y'], true); }
    return false;
}

function spe_build_rows_from_records($records){
    $rows = [];
    $i = 1;
    foreach ($records as $rec) {
        $exam = isset($rec['physical_exam']) ? $rec['physical_exam'] : [];
        $donor = isset($rec['donor_form']) ? $rec['donor_form'] : [];
        $status = 'Pending';
        $needs_review = spe_normalize_bool(isset($rec['needs_review']) ? $rec['needs_review'] : (isset($exam['needs_review']) ? $exam['needs_review'] : false));
        $remarksVal = '';
        if (!empty($exam['remarks'])) $remarksVal = $exam['remarks'];
        else if (!empty($exam['remark'])) $remarksVal = $exam['remark'];
        else if (!empty($exam['remarks_status'])) $remarksVal = $exam['remarks_status'];
        if ($remarksVal !== '') { $status = ucfirst($remarksVal); }
        // Normalize to new display terms
        $sl = strtolower($status);
        if (strpos($sl, 'refus') !== false || strpos($sl, 'temporary') !== false || strpos($sl, 'defer') !== false) {
            $status = 'Deferred';
        }
        if (strpos($sl, 'permanent') !== false) {
            $status = 'Ineligible';
        }
        // If needs_review is true, treat as Pending for status purposes (align with dashboard rules)
        if ($needs_review) { $status = 'Pending'; }
        $rows[] = [
            'no' => $i++,
            'date' => (!empty($rec['updated_at']) ? $rec['updated_at'] : ($rec['created_at'] ?? '')),
            'surname' => isset($donor['surname']) ? $donor['surname'] : '',
            'first_name' => isset($donor['first_name']) ? $donor['first_name'] : '',
            'donor_type' => isset($rec['donor_type']) ? $rec['donor_type'] : 'New',
            'status' => $status,
            'physician' => (!empty($exam['physician']) ? $exam['physician'] : ((strtolower($status)==='pending') ? 'Pending' : 'N/A')),
            'payload' => [
                'physical_exam_id' => $rec['physical_exam_id'] ?? ($exam['physical_exam_id'] ?? ''),
                'screening_id' => $exam['screening_id'] ?? ($rec['screening_id'] ?? ''),
                'donor_form_id' => $rec['donor_id'] ?? '',
                'has_pending_exam' => isset($rec['has_pending_exam']) ? !!$rec['has_pending_exam'] : (strtolower($status)==='pending'),
                'type' => 'physical_exam'
            ],
            'is_editable' => (strtolower($status)==='pending') || $needs_review,
            'needs_review' => $needs_review
        ];
    }
    return $rows;
}

function spe_search_rows($q, $limit = 50){
    $q = trim($q);
    if ($q === '') return [];

    // 1) Find donors matching name (Supabase: or=(...,...), ilike.*q*)
    $donors = spe_supabase_get('donor_form', ['donor_id','surname','first_name','middle_name'], [
        'or' => '(surname.ilike.*' . $q . '*,first_name.ilike.*' . $q . '*)'
    ]);
    $donorIds = array_values(array_unique(array_map(function($d){ return (int)$d['donor_id']; }, $donors)));

    // Also allow searching by physician name directly in physical_examination
    $physExams = [];
    if (!empty($donorIds)) {
        $physExams = spe_supabase_get('physical_examination', ['physical_exam_id','screening_id','donor_id','remarks','needs_review','updated_at','created_at','physician'], [
            'donor_id' => 'in.(' . implode(',', $donorIds) . ')'
        ]);
    } else {
        // fallback to physician match only
        $physExams = spe_supabase_get('physical_examination', ['physical_exam_id','screening_id','donor_id','remarks','needs_review','updated_at','created_at','physician'], [
            'physician' => 'ilike.*' . $q . '*'
        ]);
    }

    if (empty($physExams)) return [];

    // Build donor cache for names
    $allDonorIds = array_values(array_unique(array_map(function($e){ return (int)$e['donor_id']; }, $physExams)));
    $donorCache = [];
    if (!empty($allDonorIds)) {
        $donors2 = spe_supabase_get('donor_form', ['donor_id','surname','first_name','middle_name'], [ 'donor_id' => 'in.(' . implode(',', $allDonorIds) . ')' ]);
        foreach ($donors2 as $d) { if (isset($d['donor_id'])) $donorCache[(int)$d['donor_id']] = $d; }
    }

    // Eligibility for donor type
    $elig = spe_supabase_get('eligibility', ['donor_id']);
    $eligByDonor = [];
    foreach ($elig as $er) { if (isset($er['donor_id'])) $eligByDonor[(int)$er['donor_id']] = true; }

    // Assemble records in same schema as dashboard builder
    $records = [];
    foreach ($physExams as $exam) {
        $did = isset($exam['donor_id']) ? (int)$exam['donor_id'] : null;
        if (!$did) continue;
        $records[] = [
            'type' => 'physical_exam',
            'physical_exam_id' => $exam['physical_exam_id'] ?? null,
            'donor_id' => $did,
            'created_at' => $exam['created_at'] ?? null,
            'updated_at' => $exam['updated_at'] ?? null,
            'physical_exam' => $exam,
            'donor_form' => isset($donorCache[$did]) ? $donorCache[$did] : ['surname'=>'Unknown','first_name'=>'Unknown','middle_name'=>''],
            'has_pending_exam' => (isset($exam['remarks']) && strtolower($exam['remarks']) === 'pending'),
            'needs_review' => spe_normalize_bool($exam['needs_review'] ?? false),
            'donor_type' => isset($eligByDonor[$did]) ? 'Returning' : 'New'
        ];
        if (count($records) >= $limit) break;
    }

    return spe_build_rows_from_records($records);
}

?>


