<?php
// Filter helpers for Blood Collection search
require_once dirname(__DIR__) . '/search_account_blood_collection/search_helpers.php';

if (!defined('SABC_FILTER_HELPERS_INCLUDED')) {
    define('SABC_FILTER_HELPERS_INCLUDED', true);

    /**
     * Filter blood collection rows by collection status and optional search query
     * @param array $filters ['status'=>[...], 'q'=>string]
     * @param int $limit
     * @return array rows identical shape to sabc_search_rows output
     */
    function sabc_filter_rows(array $filters, int $limit = 100): array {
        $q = isset($filters['q']) ? trim((string)$filters['q']) : '';
        $statuses = isset($filters['status']) && is_array($filters['status']) ? $filters['status'] : [];
        // Normalize statuses
        $normalized = array_map(function($s){ return strtolower(trim((string)$s)); }, $statuses);
        $normalized = array_values(array_unique(array_filter($normalized)));

        // If nothing selected and no query, return empty to avoid loading everything
        if ($q === '' && empty($normalized)) return [];

        // If we have a query, reuse the search helper to build rows, then filter by status
        if ($q !== '') {
            $rows = sabc_search_rows($q, $limit);
            if (empty($normalized)) return $rows;
            $filtered = [];
            foreach ($rows as $r) {
                $statusBucket = 'not started';
                $html = isset($r['status_html']) ? strtolower(strip_tags($r['status_html'])) : '';
                if (strpos($html, 'completed') !== false || strpos($html, 'successful') !== false) {
                    $statusBucket = 'completed';
                } elseif (strpos($html, 'failed') !== false || strpos($html, 'unsuccessful') !== false) {
                    $statusBucket = 'failed';
                }
                if (in_array($statusBucket, $normalized, true)) {
                    $filtered[] = $r;
                }
            }
            return $filtered;
        }

        // No query, but statuses selected: fetch recent blood collections and build rows
        $bcResp = sabc_supabase_get('/rest/v1/blood_collection?select=blood_collection_id,physical_exam_id,is_successful,needs_review,phlebotomist,created_at,updated_at&order=updated_at.desc&limit=' . max(50, min(500, $limit*3)));
        if ($bcResp['http_code'] !== 200) return [];
        $bcRows = json_decode($bcResp['body'], true) ?: [];
        if (empty($bcRows)) return [];

        // Collect exam ids
        $examIds = [];
        foreach ($bcRows as $bc) { if (!empty($bc['physical_exam_id'])) $examIds[] = (string)$bc['physical_exam_id']; }
        $examIds = array_values(array_unique($examIds));
        if (empty($examIds)) return [];

        // Fetch physical_examination rows for donor_id mapping
        $collectionsByExam = [];
        foreach ($bcRows as $bc) { if (!empty($bc['physical_exam_id'])) $collectionsByExam[(string)$bc['physical_exam_id']] = $bc; }

        $latestExamById = [];
        $chunks = array_chunk($examIds, 150);
        foreach ($chunks as $ids) {
            $in = implode(',', array_map(function($v){ return '"' . str_replace('"','""',$v) . '"'; }, $ids));
            $peResp = sabc_supabase_get('/rest/v1/physical_examination?select=physical_exam_id,donor_id,created_at&physical_exam_id=in.(' . $in . ')');
            if ($peResp['http_code'] !== 200) continue;
            $peRows = json_decode($peResp['body'], true) ?: [];
            foreach ($peRows as $row) { $latestExamById[(string)$row['physical_exam_id']] = $row; }
        }
        if (empty($latestExamById)) return [];

        // Fetch donor_form for donor details
        $donorIds = [];
        foreach ($latestExamById as $pe) { if (!empty($pe['donor_id'])) $donorIds[] = (int)$pe['donor_id']; }
        $donorIds = array_values(array_unique($donorIds));
        $donorById = [];
        $donorChunks = array_chunk($donorIds, 150);
        foreach ($donorChunks as $ids) {
            $in = implode(',', array_map('intval', $ids));
            $dResp = sabc_supabase_get('/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,age,prc_donor_number&donor_id=in.(' . $in . ')');
            if ($dResp['http_code'] !== 200) continue;
            $dRows = json_decode($dResp['body'], true) ?: [];
            foreach ($dRows as $d) { if (isset($d['donor_id'])) $donorById[(int)$d['donor_id']] = $d; }
        }

        // Build rows and filter by status
        $results = [];
        $no = 1;
        foreach ($collectionsByExam as $examId => $bc) {
            $pe = $latestExamById[$examId] ?? null;
            if (!$pe) continue;
            $donorId = isset($pe['donor_id']) ? (int)$pe['donor_id'] : null;
            if ($donorId === null) continue;
            $donor = $donorById[$donorId] ?? null;
            if (!$donor) continue;

            $needsReview = sabc_to_bool($bc['needs_review'] ?? false);
            $statusBucket = 'not started';
            $statusHtml = '<span class="badge bg-secondary">Not Started</span>';
            if (!$needsReview) {
                if (array_key_exists('is_successful', $bc)) {
                    if ($bc['is_successful'] === true) {
                        $statusBucket = 'completed';
                        $statusHtml = '<span class="badge bg-success">Completed</span>';
                    } elseif ($bc['is_successful'] === false) {
                        $statusBucket = 'failed';
                        $statusHtml = '<span class="badge bg-danger">Failed</span>';
                    }
                }
            }

            if (!empty($normalized) && !in_array($statusBucket, $normalized, true)) continue;

            $prc = $donor['prc_donor_number'] ?? '';
            $displayId = $prc ? (strpos($prc, 'PRC-') === 0 ? substr($prc, 4) : $prc) : (string)$donorId;

            $payload = [
                'donor_id' => $donorId,
                'physical_exam_id' => $examId,
                'created_at' => $pe['created_at'] ?? null,
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

            if (count($results) >= $limit) break;
        }

        return $results;
    }
}
?>


