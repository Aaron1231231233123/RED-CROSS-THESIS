<?php
// Shared helper for medical history account search
// Uses Supabase REST to perform a full scan across donor_form and related tables

require_once __DIR__ . '/../../../conn/db_conn.php';

function shm_http_get($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$http, $resp];
}

function shm_build_status_from_eligibility($eligRow) {
    if (!$eligRow || !isset($eligRow['status'])) return '-';
    $st = strtolower($eligRow['status']);
    if ($st === 'approved') return 'Eligible';
    if ($st === 'temporary deferred') return 'Deferred';
    if ($st === 'permanently deferred') return 'Ineligible';
    if ($st === 'refused') return 'Deferred';
    if ($st === 'eligible') return 'Eligible';
    return '-';
}

function shm_search_medical_history_rows($query, $limit = 50) {
    $q = trim($query);
    if ($q === '') return [];

    // 1) Find matching donors by name/id
    $encoded = rawurlencode('%' . $q . '%');
    $df_url = SUPABASE_URL . '/rest/v1/donor_form?or=(surname.ilike.' . $encoded . ',first_name.ilike.' . $encoded . ',prc_donor_number.ilike.' . $encoded . ')&select=donor_id,surname,first_name,submitted_at,registration_channel,prc_donor_number&order=submitted_at.desc&limit=' . intval($limit);
    list($df_http, $df_resp) = shm_http_get($df_url);
    if (!($df_http >= 200 && $df_http < 300)) return [];
    $df_rows = json_decode($df_resp, true) ?: [];
    if (empty($df_rows)) {
        // Fallback: search by interviewer name (users table) -> screening_form -> donor_form
        $u_q = rawurlencode('%' . $q . '%');
        $users_url = SUPABASE_URL . '/rest/v1/users?or=(surname.ilike.' . $u_q . ',first_name.ilike.' . $u_q . ',middle_name.ilike.' . $u_q . ')&select=user_id&limit=' . intval($limit);
        list($u_http, $u_resp) = shm_http_get($users_url);
        $user_ids = [];
        if ($u_http >= 200 && $u_http < 300) {
            $ud = json_decode($u_resp, true) ?: [];
            foreach ($ud as $row) { if (isset($row['user_id'])) $user_ids[] = intval($row['user_id']); }
        }
        if (!empty($user_ids)) {
            $ids_str_int = implode(',', $user_ids);
            $sf_url = SUPABASE_URL . '/rest/v1/screening_form?interviewer_id=in.(' . $ids_str_int . ')&select=donor_form_id,interviewer_id,created_at&order=created_at.desc&limit=' . intval($limit);
            list($sf_http, $sf_resp) = shm_http_get($sf_url);
            if ($sf_http >= 200 && $sf_http < 300) {
                $sfs = json_decode($sf_resp, true) ?: [];
                $did_set = [];
                foreach ($sfs as $row) { if (isset($row['donor_form_id'])) $did_set[intval($row['donor_form_id'])] = true; }
                if (!empty($did_set)) {
                    $did_list = implode(',', array_keys($did_set));
                    $df_by_id_url = SUPABASE_URL . '/rest/v1/donor_form?donor_id=in.(' . $did_list . ')&select=donor_id,surname,first_name,submitted_at,registration_channel,prc_donor_number&order=submitted_at.desc';
                    list($df2_http, $df2_resp) = shm_http_get($df_by_id_url);
                    if ($df2_http >= 200 && $df2_http < 300) {
                        $df_rows = json_decode($df2_resp, true) ?: [];
                    }
                }
            }
        }
        if (empty($df_rows)) return [];
    }

    $donor_ids = array_column($df_rows, 'donor_id');
    $ids_str = implode(',', array_map('intval', $donor_ids));

    // 2) Pull related data in parallel limited to these donors
    $multi = curl_multi_init();
    $chs = [];
    $qs = [
        'medical_histories' => '/rest/v1/medical_history?donor_id=in.(' . $ids_str . ')&select=donor_id,medical_history_id,medical_approval,needs_review,interviewer,created_at,updated_at',
        'screening_forms' => '/rest/v1/screening_form?donor_form_id=in.(' . $ids_str . ')&select=screening_id,donor_form_id,interviewer_id,needs_review,created_at',
        'physical_exams' => '/rest/v1/physical_examination?donor_id=in.(' . $ids_str . ')&select=donor_id,needs_review,created_at',
        'eligibility_records' => '/rest/v1/eligibility?donor_id=in.(' . $ids_str . ')&select=donor_id,status,created_at',
    ];
    foreach ($qs as $key => $qurl) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => SUPABASE_URL . $qurl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        $chs[$key] = $ch;
        curl_multi_add_handle($multi, $ch);
    }
    $running = null;
    do {
        curl_multi_exec($multi, $running);
        curl_multi_select($multi);
    } while ($running > 0);
    $medical_histories = json_decode(curl_multi_getcontent($chs['medical_histories']), true) ?: [];
    $screening_forms = json_decode(curl_multi_getcontent($chs['screening_forms']), true) ?: [];
    $physical_exams = json_decode(curl_multi_getcontent($chs['physical_exams']), true) ?: [];
    $eligibility_records = json_decode(curl_multi_getcontent($chs['eligibility_records']), true) ?: [];
    foreach ($chs as $ch) { curl_multi_remove_handle($multi, $ch); curl_close($ch); }
    curl_multi_close($multi);

    // 3) Build lookups
    $donors_by_id = array_column($df_rows, null, 'donor_id');
    $medical_by_donor = [];
    foreach ($medical_histories as $mh) { $medical_by_donor[$mh['donor_id']] = $mh; }
    $screenings_by_donor = array_column($screening_forms, null, 'donor_form_id');
    $physicals_by_donor = array_column($physical_exams, null, 'donor_id');
    $eligibility_by_donor = [];
    foreach ($eligibility_records as $row) {
        $did = $row['donor_id'] ?? null; if ($did === null) continue; if (!isset($eligibility_by_donor[$did])) $eligibility_by_donor[$did] = $row;
    }

    // Build interviewer names from users table
    $interviewer_ids = [];
    foreach ($screening_forms as $sf) { if (!empty($sf['interviewer_id'])) $interviewer_ids[] = (int)$sf['interviewer_id']; }
    $interviewer_ids = array_values(array_unique($interviewer_ids));
    $interviewer_names = [];
    if (!empty($interviewer_ids)) {
        $ids_str_u = implode(',', $interviewer_ids);
        list($u_http, $u_resp) = shm_http_get(SUPABASE_URL . "/rest/v1/users?user_id=in.($ids_str_u)&select=user_id,first_name,surname,middle_name");
        if ($u_http >= 200 && $u_http < 300) {
            $u_rows = json_decode($u_resp, true) ?: [];
            foreach ($u_rows as $u) {
                $uid = $u['user_id'];
                $first = $u['first_name'] ?? '';
                $sur = $u['surname'] ?? '';
                $mid = $u['middle_name'] ?? '';
                $interviewer_names[$uid] = trim($sur . ', ' . $first . ' ' . $mid);
            }
        }
    }
    $interviewer_by_donor = [];
    foreach ($screening_forms as $sf) {
        $did = $sf['donor_form_id'] ?? null; $iid = $sf['interviewer_id'] ?? null;
        if ($did && $iid && isset($interviewer_names[$iid])) {
            $interviewer_by_donor[$did] = $interviewer_names[$iid];
        }
    }

    // 4) Build rows similar to dashboard
    $rows = [];
    $counter = 1;
    foreach ($donor_ids as $did) {
        $donor_info = $donors_by_id[$did] ?? null; if (!$donor_info) continue;
        $medical_info = $medical_by_donor[$did] ?? null;
        $screening_info = $screenings_by_donor[$did] ?? null;
        $physical_info = $physicals_by_donor[$did] ?? null;
        $elig = $eligibility_by_donor[$did] ?? null;

        $status = shm_build_status_from_eligibility($elig);
        $stage = 'medical_review';
        if ($physical_info) $stage = 'physical_examination'; elseif ($screening_info) $stage = 'screening_form';
        $donor_type = isset($eligibility_by_donor[$did]) ? 'Returning' : 'New';

        $rows[] = [
            'no' => $counter++,
            'date' => ($medical_info['updated_at'] ?? $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
            'surname' => $donor_info['surname'] ?? 'N/A',
            'first_name' => $donor_info['first_name'] ?? 'N/A',
            'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
            'interviewer' => ($medical_info['interviewer'] ?? null) ?: ($interviewer_by_donor[$did] ?? 'N/A'),
            'donor_type' => $donor_type,
            'status' => $status,
            'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
            'donor_id' => $did,
            'stage' => $stage,
            'medical_history_id' => $medical_info['medical_history_id'] ?? null
        ];
    }

    return $rows;
}


