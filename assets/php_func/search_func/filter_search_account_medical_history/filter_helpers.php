<?php
// Helpers for checkbox-based filter search for Interviewer dashboard
require_once __DIR__ . '/../../../conn/db_conn.php';

function fsh_http_get($url) {
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

function fsh_status_from_eligibility($row) {
    if (!$row || !isset($row['status'])) return '-';
    $st = strtolower($row['status']);
    if ($st === 'approved' || $st === 'eligible') return 'Eligible';
    if ($st === 'temporary deferred' || $st === 'refused') return 'Deferred';
    if ($st === 'permanently deferred') return 'Ineligible';
    return '-';
}

// Build dataset like dashboard but filtered by donor_type/status/registered_via
function fsh_build_filtered_rows($filters, $limit = 150, $query = '', $sortOptions = null, $statusFilter = 'all') {
    $wantReturning = isset($filters['donor_type']) && in_array('Returning', $filters['donor_type'], true);
    $wantNew = isset($filters['donor_type']) && in_array('New', $filters['donor_type'], true);
    $wantStatuses = isset($filters['status']) ? array_map('strtolower', $filters['status']) : [];
    $wantVia = isset($filters['via']) ? array_map('strtolower', $filters['via']) : [];

    // Fetch recent donors (cap for responsiveness); if q provided, filter by name/id
    $baseDf = '/rest/v1/donor_form?select=donor_id,surname,first_name,submitted_at,registration_channel,prc_donor_number&order=submitted_at.desc&limit=' . intval($limit);
    $q = trim($query);
    if ($q !== '') {
        $encoded = rawurlencode('%' . $q . '%');
        $baseDf = '/rest/v1/donor_form?or=(surname.ilike.' . $encoded . ',first_name.ilike.' . $encoded . ',prc_donor_number.ilike.' . $encoded . ')&select=donor_id,surname,first_name,submitted_at,registration_channel,prc_donor_number&order=submitted_at.desc&limit=' . intval($limit);
    }
    list($df_http, $df_resp) = fsh_http_get(SUPABASE_URL . $baseDf);
    if (!($df_http >= 200 && $df_http < 300)) return [];
    $donor_forms = json_decode($df_resp, true) ?: [];
    if (empty($donor_forms)) return [];
    $donor_ids = array_column($donor_forms, 'donor_id');
    $ids_str = implode(',', array_map('intval', $donor_ids));

    $multi = curl_multi_init();
    $chs = [];
    $qs = [
        'medical_histories' => '/rest/v1/medical_history?donor_id=in.(' . $ids_str . ')&select=donor_id,medical_history_id,medical_approval,needs_review,interviewer,created_at,updated_at',
        'screening_forms' => '/rest/v1/screening_form?donor_form_id=in.(' . $ids_str . ')&select=screening_id,donor_form_id,interviewer_id,needs_review,created_at',
        'physical_exams' => '/rest/v1/physical_examination?donor_id=in.(' . $ids_str . ')&select=donor_id,needs_review,created_at',
        'eligibility' => '/rest/v1/eligibility?donor_id=in.(' . $ids_str . ')&select=donor_id,status,created_at'
    ];
    foreach ($qs as $k => $qurl) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => SUPABASE_URL . $qurl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        $chs[$k] = $ch;
        curl_multi_add_handle($multi, $ch);
    }
    $running = null;
    do { curl_multi_exec($multi, $running); curl_multi_select($multi); } while ($running > 0);
    $medical = json_decode(curl_multi_getcontent($chs['medical_histories']), true) ?: [];
    $screening = json_decode(curl_multi_getcontent($chs['screening_forms']), true) ?: [];
    $physical = json_decode(curl_multi_getcontent($chs['physical_exams']), true) ?: [];
    $elig = json_decode(curl_multi_getcontent($chs['eligibility']), true) ?: [];
    foreach ($chs as $ch) { curl_multi_remove_handle($multi, $ch); curl_close($ch); }
    curl_multi_close($multi);

    $donors_by_id = array_column($donor_forms, null, 'donor_id');
    $medical_by_donor = [];
    foreach ($medical as $row) { $medical_by_donor[$row['donor_id']] = $row; }
    $screen_by_donor = array_column($screening, null, 'donor_form_id');
    $physical_by_donor = array_column($physical, null, 'donor_id');
    $elig_by_donor = [];
    foreach ($elig as $row) { $did = $row['donor_id'] ?? null; if ($did !== null && !isset($elig_by_donor[$did])) $elig_by_donor[$did] = $row; }

    // Build interviewer names similar to dashboard
    $interviewer_ids = [];
    foreach ($screening as $sf) { if (!empty($sf['interviewer_id'])) $interviewer_ids[] = (int)$sf['interviewer_id']; }
    $interviewer_ids = array_values(array_unique($interviewer_ids));
    $interviewer_names = [];
    if (!empty($interviewer_ids)) {
        $ids_str_u = implode(',', $interviewer_ids);
        list($u_http, $u_resp) = fsh_http_get(SUPABASE_URL . "/rest/v1/users?user_id=in.($ids_str_u)&select=user_id,first_name,surname,middle_name");
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
    foreach ($screening as $sf) {
        $did = $sf['donor_form_id'] ?? null; $iid = $sf['interviewer_id'] ?? null;
        if ($did && $iid && isset($interviewer_names[$iid])) {
            $interviewer_by_donor[$did] = $interviewer_names[$iid];
        }
    }

    $rows = [];
    $counter = 1;
    foreach ($donor_ids as $did) {
        $df = $donors_by_id[$did] ?? null; if (!$df) continue;
        $mh = $medical_by_donor[$did] ?? null;
        $sf = $screen_by_donor[$did] ?? null;
        $pe = $physical_by_donor[$did] ?? null;
        $el = $elig_by_donor[$did] ?? null;

        // Derived fields
        $donor_type = isset($elig_by_donor[$did]) ? 'Returning' : 'New';
        $status = fsh_status_from_eligibility($el);
        $via = (strtolower($df['registration_channel'] ?? '') === 'mobile') ? 'Mobile' : 'System';

        // Apply filters
        if ($wantReturning || $wantNew) {
            if ($donor_type === 'Returning' && !$wantReturning) continue;
            if ($donor_type === 'New' && !$wantNew) continue;
        }
        if (!empty($wantStatuses)) {
            if (!in_array(strtolower($status), $wantStatuses, true)) continue;
        }
        if (!empty($wantVia)) {
            if (!in_array(strtolower($via), $wantVia, true)) continue;
        }

        $stage = 'medical_review';
        if ($pe) $stage = 'physical_examination'; elseif ($sf) $stage = 'screening_form';

        $physician = ($mh['interviewer'] ?? null) ?: ($interviewer_by_donor[$did] ?? 'N/A');

        $rows[] = [
            'no' => $counter++,
            'date' => ($mh['updated_at'] ?? $sf['created_at'] ?? $df['submitted_at'] ?? date('Y-m-d H:i:s')),
            'surname' => $df['surname'] ?? 'N/A',
            'first_name' => $df['first_name'] ?? 'N/A',
            'donor_id_number' => $df['prc_donor_number'] ?? 'N/A',
            'physician' => $physician,
            'interviewer' => $physician,
            'donor_type' => $donor_type,
            'status' => $status,
            'registered_via' => $via,
            'donor_id' => $did,
            'stage' => $stage,
            'medical_history_id' => $mh['medical_history_id'] ?? null
        ];
    }

    $statusFilter = strtolower(trim((string)$statusFilter));
    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $rows = array_values(array_filter($rows, function($row) use ($statusFilter) {
            $status = strtolower($row['status'] ?? '');
            switch ($statusFilter) {
                case 'incoming':
                    return $status === '-' || $status === '';
                case 'eligible':
                    return $status === 'eligible';
                case 'deferred':
                    return $status === 'deferred';
                case 'ineligible':
                    return $status === 'ineligible';
                default:
                    return true;
            }
        }));
    }

    if ($sortOptions && is_array($sortOptions) && isset($sortOptions['column'], $sortOptions['direction'])) {
        $column = strtolower($sortOptions['column']);
        $direction = strtolower($sortOptions['direction']);
        $allowedColumns = ['no', 'date', 'surname', 'first_name', 'physician'];
        $allowedDirections = ['asc', 'desc'];
        if (in_array($column, $allowedColumns, true) && in_array($direction, $allowedDirections, true)) {
            usort($rows, function($a, $b) use ($column, $direction) {
                switch ($column) {
                    case 'date':
                        $aVal = isset($a['date']) ? strtotime($a['date']) : 0;
                        $bVal = isset($b['date']) ? strtotime($b['date']) : 0;
                        $cmp = $aVal <=> $bVal;
                        break;
                    case 'surname':
                    case 'first_name':
                        $aVal = strtolower($a[$column] ?? '');
                        $bVal = strtolower($b[$column] ?? '');
                        $cmp = strcmp($aVal, $bVal);
                        break;
                    case 'physician':
                        $normalize = function($value) {
                            $val = strtolower(trim((string)$value));
                            return in_array($val, ['n/a', 'na', '-', ''], true) ? '' : $val;
                        };
                        $aVal = $normalize($a['physician'] ?? '');
                        $bVal = $normalize($b['physician'] ?? '');
                        $cmp = strcmp($aVal, $bVal);
                        break;
                    case 'no':
                    default:
                        $aVal = isset($a['no']) ? (int)$a['no'] : 0;
                        $bVal = isset($b['no']) ? (int)$b['no'] : 0;
                        $cmp = $aVal <=> $bVal;
                        break;
                }
                if ($cmp === 0) {
                    $cmp = ($a['donor_id'] ?? 0) <=> ($b['donor_id'] ?? 0);
                }
                return $direction === 'asc' ? $cmp : -$cmp;
            });
        }
    }

    if ($limit > 0 && count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
    }

    $i = 1;
    foreach ($rows as &$entry) {
        $entry['no'] = $i++;
    }
    unset($entry);

    return $rows;
}


