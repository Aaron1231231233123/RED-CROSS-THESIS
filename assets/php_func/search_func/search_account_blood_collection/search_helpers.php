<?php
// Blood Collection search helpers
// Uses Supabase REST API constants defined in assets/conn/db_conn.php

if (!defined('SABC_HELPERS_INCLUDED')) {
    define('SABC_HELPERS_INCLUDED', true);

    /**
     * Execute a GET request to Supabase REST API
     * @param string $path Relative path beginning with '/rest/v1/...'
     * @return array [http_code=>int, body=>string]
     */
    function sabc_supabase_get(string $path): array {
        $url = rtrim(SUPABASE_URL, '/') . $path;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['http_code' => (int)$code, 'body' => (string)$body];
    }

    /**
     * Normalize truthy values to boolean true/false
     */
    function sabc_to_bool($val): bool {
        if (is_bool($val)) return $val;
        if (is_numeric($val)) return (int)$val === 1;
        if (is_string($val)) {
            $v = strtolower(trim($val));
            return in_array($v, ['true','t','1','yes','y','on'], true);
        }
        return false;
    }

    /**
     * Search blood collection rows by donor name or PRC donor number.
     * Returns array of associative arrays representing table data needed for rendering.
     *
     * Fields used in dashboard table:
     *  - no, donor_id, surname, first_name, prc_donor_number, physical_exam_id
     *  - collection_status (derived), phlebotomist, payload (for modal/actions)
     */
    function sabc_search_rows(string $q, int $limit = 50): array {
        $q = trim($q);
        if ($q === '') return [];

        // 1) Find donors by name or PRC number
        $qEnc = rawurlencode('%' . $q . '%');
        $donorSelect = '/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,age,prc_donor_number'
            . '&or=(surname.ilike.' . $qEnc . ',first_name.ilike.' . $qEnc . ',prc_donor_number.ilike.' . $qEnc . ')'
            . '&limit=' . max(1, min(100, $limit));
        $resp = sabc_supabase_get($donorSelect);
        if ($resp['http_code'] !== 200) return [];
        $donors = json_decode($resp['body'], true) ?: [];
        if (empty($donors)) return [];

        // Index donors and build donor_id list
        $donorById = [];
        $donorIds = [];
        foreach ($donors as $d) {
            if (!isset($d['donor_id'])) continue;
            $donorById[(int)$d['donor_id']] = $d;
            $donorIds[] = (int)$d['donor_id'];
        }
        if (empty($donorIds)) return [];

        // 2) For these donors, fetch latest physical_examination per donor
        // Supabase lacks server-side distinct-on; fetch ordered and pick latest per donor in PHP
        $idsFilter = implode(',', array_map('intval', array_slice($donorIds, 0, 200)));
        $peSelect = '/rest/v1/physical_examination?select=physical_exam_id,donor_id,remarks,blood_bag_type,created_at'
            . '&donor_id=in.(' . $idsFilter . ')&order=created_at.desc&limit=5000';
        $peResp = sabc_supabase_get($peSelect);
        $peRows = ($peResp['http_code'] === 200) ? (json_decode($peResp['body'], true) ?: []) : [];
        $latestExamByDonor = [];
        foreach ($peRows as $row) {
            $did = isset($row['donor_id']) ? (int)$row['donor_id'] : null;
            if ($did === null) continue;
            if (!isset($latestExamByDonor[$did])) {
                $latestExamByDonor[$did] = $row; // ordered desc already
            }
        }
        if (empty($latestExamByDonor)) return [];

        // 3) Fetch blood_collection rows for these physical_exam_ids
        $examIds = [];
        foreach ($latestExamByDonor as $pe) {
            if (!empty($pe['physical_exam_id'])) $examIds[] = (string)$pe['physical_exam_id'];
        }
        if (empty($examIds)) return [];
        // Chunk examIds to avoid URL length issues
        $collectionsByExam = [];
        $chunk = array_chunk($examIds, 150);
        foreach ($chunk as $ids) {
            $in = implode(',', array_map(function($v){ return '"' . str_replace('"','""',$v) . '"'; }, $ids));
            $bcSelect = '/rest/v1/blood_collection?select=blood_collection_id,physical_exam_id,is_successful,donor_reaction,management_done,status,blood_bag_type,blood_bag_brand,amount_taken,start_time,end_time,unit_serial_number,needs_review,phlebotomist,created_at,updated_at'
                . '&physical_exam_id=in.(' . $in . ')';
            $bcResp = sabc_supabase_get($bcSelect);
            $bcRows = ($bcResp['http_code'] === 200) ? (json_decode($bcResp['body'], true) ?: []) : [];
            foreach ($bcRows as $bc) {
                if (!empty($bc['physical_exam_id'])) {
                    $collectionsByExam[(string)$bc['physical_exam_id']] = $bc;
                }
            }
        }

        // 4) Build rows for output
        $results = [];
        $no = 1;
        foreach ($latestExamByDonor as $donorId => $exam) {
            $donor = $donorById[$donorId] ?? null;
            if (!$donor) continue;
            $examId = (string)($exam['physical_exam_id'] ?? '');
            if ($examId === '' || !isset($collectionsByExam[$examId])) continue; // only show those with collection data
            $bc = $collectionsByExam[$examId];

            $needsReview = sabc_to_bool($bc['needs_review'] ?? false);
            $statusHtml = '<span class="badge bg-secondary">Not Started</span>';
            if (!$needsReview) {
                if (array_key_exists('is_successful', $bc)) {
                    if ($bc['is_successful'] === true) {
                        $statusHtml = '<span class="badge bg-success">Completed</span>';
                    } elseif ($bc['is_successful'] === false) {
                        $statusHtml = '<span class="badge bg-danger">Failed</span>';
                    }
                }
            }

            $prc = $donor['prc_donor_number'] ?? '';
            $displayId = $prc ? (strpos($prc, 'PRC-') === 0 ? substr($prc, 4) : $prc) : (string)$donorId;

            $payload = [
                'donor_id' => $donorId,
                'physical_exam_id' => $examId,
                'created_at' => $exam['created_at'] ?? null,
                'surname' => $donor['surname'] ?? 'Unknown',
                'first_name' => $donor['first_name'] ?? 'Unknown',
                'middle_name' => $donor['middle_name'] ?? '',
                'birthdate' => $donor['birthdate'] ?? '',
                'age' => $donor['age'] ?? '',
                'prc_donor_number' => $donor['prc_donor_number'] ?? ''
            ];

            $results[] = [
                'no' => $no++,
                'display_id' => $displayId,
                'surname' => $donor['surname'] ?? 'Unknown',
                'first_name' => $donor['first_name'] ?? 'Unknown',
                'status_html' => $statusHtml,
                'phlebotomist' => isset($bc['phlebotomist']) && $bc['phlebotomist'] !== '' ? (string)$bc['phlebotomist'] : 'Assigned',
                'needs_review' => $needsReview,
                'payload' => $payload
            ];
        }

        return $results;
    }
}
?>

