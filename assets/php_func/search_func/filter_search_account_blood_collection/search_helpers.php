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
    function sabc_filter_rows(array $filters, int $limit = 100, $sortOptions = null, string $statusFilter = 'all'): array {
        $limit = max(1, (int)$limit);
        $q = isset($filters['q']) ? trim((string)$filters['q']) : '';
        $statuses = isset($filters['status']) && is_array($filters['status']) ? $filters['status'] : [];
        $normalized = array_map(function($s){ return strtolower(trim((string)$s)); }, $statuses);
        $normalized = array_values(array_unique(array_filter($normalized)));

        $rows = [];

        if ($q !== '') {
            $rows = sabc_search_rows($q, max($limit, 150));
        } else {
            $bcRows = [];
            $chunkLimit = 1000;
            $offset = 0;
            $iterations = 0;
            $maxIterations = 10;
            do {
                $resp = sabc_supabase_get('/rest/v1/blood_collection?select=blood_collection_id,physical_exam_id,is_successful,needs_review,phlebotomist,created_at,updated_at&order=updated_at.desc&limit=' . $chunkLimit . '&offset=' . $offset);
                if ($resp['http_code'] !== 200) {
                    break;
                }
                $batch = json_decode($resp['body'], true) ?: [];
                if (empty($batch)) {
                    break;
                }
                $bcRows = array_merge($bcRows, $batch);
                if (count($batch) < $chunkLimit) {
                    break;
                }
                $offset += $chunkLimit;
                $iterations++;
            } while ($iterations < $maxIterations);

            if (empty($bcRows)) {
                return [];
            }

            $examIds = [];
            foreach ($bcRows as $bc) {
                if (!empty($bc['physical_exam_id'])) {
                    $examIds[] = (string)$bc['physical_exam_id'];
                }
            }
            $examIds = array_values(array_unique($examIds));
            if (empty($examIds)) {
                return [];
            }

            $collectionsByExam = [];
            foreach ($bcRows as $bc) {
                if (!empty($bc['physical_exam_id'])) {
                    $collectionsByExam[(string)$bc['physical_exam_id']] = $bc;
                }
            }

            $latestExamById = [];
            $chunks = array_chunk($examIds, 150);
            foreach ($chunks as $ids) {
                $in = implode(',', array_map(function($v){ return '"' . str_replace('"','""',$v) . '"'; }, $ids));
                $peResp = sabc_supabase_get('/rest/v1/physical_examination?select=physical_exam_id,donor_id,created_at&physical_exam_id=in.(' . $in . ')');
                if ($peResp['http_code'] !== 200) {
                    continue;
                }
                $peRows = json_decode($peResp['body'], true) ?: [];
                foreach ($peRows as $row) {
                    $latestExamById[(string)$row['physical_exam_id']] = $row;
                }
            }
            if (empty($latestExamById)) {
                return [];
            }

            $donorIds = [];
            foreach ($latestExamById as $pe) {
                if (!empty($pe['donor_id'])) {
                    $donorIds[] = (int)$pe['donor_id'];
                }
            }
            $donorIds = array_values(array_unique($donorIds));
            $donorById = [];
            $donorChunks = array_chunk($donorIds, 150);
            foreach ($donorChunks as $ids) {
                $in = implode(',', array_map('intval', $ids));
                $dResp = sabc_supabase_get('/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,age,prc_donor_number&donor_id=in.(' . $in . ')');
                if ($dResp['http_code'] !== 200) {
                    continue;
                }
                $dRows = json_decode($dResp['body'], true) ?: [];
                foreach ($dRows as $d) {
                    if (isset($d['donor_id'])) {
                        $donorById[(int)$d['donor_id']] = $d;
                    }
                }
            }

            $rows = [];
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

                $rows[] = [
                    'no' => $no++,
                    'display_id' => $displayId,
                    'surname' => $donor['surname'] ?? 'Unknown',
                    'first_name' => $donor['first_name'] ?? 'Unknown',
                    'status_html' => $statusHtml,
                    'phlebotomist' => isset($bc['phlebotomist']) && $bc['phlebotomist'] !== '' ? (string)$bc['phlebotomist'] : 'Assigned',
                    'needs_review' => $needsReview,
                    'status_bucket' => $statusBucket,
                    'created_at' => $bc['created_at'] ?? null,
                    'payload' => $payload
                ];

                if (count($rows) >= $limit * 2) {
                    break;
                }
            }

            if (!empty($rows) && count($rows) > $limit * 2) {
                $rows = array_slice($rows, 0, $limit * 2);
            }
        }

        if (!empty($normalized)) {
            $rows = array_values(array_filter($rows, function($row) use ($normalized){
                return in_array(strtolower($row['status_bucket'] ?? 'not started'), $normalized, true);
            }));
        }

        $statusFilterNormalized = strtolower(trim($statusFilter));
        if ($statusFilterNormalized === '') $statusFilterNormalized = 'all';
        $today = date('Y-m-d');
        if ($statusFilterNormalized !== 'all') {
            $rows = array_values(array_filter($rows, function($row) use ($statusFilterNormalized, $today){
                switch ($statusFilterNormalized) {
                    case 'incoming':
                        return !empty($row['needs_review']);
                    case 'active':
                        return strpos(strtolower(strip_tags($row['status_html'] ?? '')), 'completed') !== false;
                    case 'today':
                        $ts = isset($row['created_at']) ? strtotime($row['created_at']) : 0;
                        return $ts ? date('Y-m-d', $ts) === $today : false;
                    default:
                        return true;
                }
            }));
        }

        if ($sortOptions && is_array($sortOptions) && isset($sortOptions['column'], $sortOptions['direction'])) {
            $column = strtolower($sortOptions['column']);
            $direction = strtolower($sortOptions['direction']);
            if (in_array($direction, ['asc','desc'], true)) {
                $extractDonorNumeric = function($row){
                    $display = $row['display_id'] ?? '';
                    if ($display !== '' && is_numeric($display)) return (float)$display;
                    if ($display !== '') {
                        $digits = preg_replace('/\D+/', '', (string)$display);
                        if ($digits !== '') return (float)$digits;
                    }
                    return isset($row['payload']['donor_id']) ? (float)$row['payload']['donor_id'] : 0;
                };
                usort($rows, function($a, $b) use ($column, $direction, $extractDonorNumeric){
                    switch ($column) {
                        case 'date':
                            $aVal = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
                            $bVal = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
                            $cmp = $aVal <=> $bVal;
                            break;
                        case 'donor_id':
                            $cmp = $extractDonorNumeric($a) <=> $extractDonorNumeric($b);
                            break;
                        case 'surname':
                        case 'first_name':
                            $cmp = strcmp(strtolower($a[$column] ?? ''), strtolower($b[$column] ?? ''));
                            break;
                        case 'phlebotomist':
                            $normalize = function($v){
                                $val = strtolower(trim((string)$v));
                                if (in_array($val, ['assigned','pending','n/a',''], true)) return '';
                                return $val;
                            };
                            $cmp = strcmp($normalize($a['phlebotomist'] ?? ''), $normalize($b['phlebotomist'] ?? ''));
                            break;
                        case 'no':
                        default:
                            $cmp = ((int)($a['no'] ?? 0)) <=> ((int)($b['no'] ?? 0));
                            break;
                    }
                    if ($cmp === 0) {
                        $cmp = ($a['payload']['donor_id'] ?? 0) <=> ($b['payload']['donor_id'] ?? 0);
                    }
                    return $direction === 'asc' ? $cmp : -$cmp;
                });
            }
        }

        if ($limit > 0 && count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
        }

        $i = 1;
        foreach ($rows as &$row) {
            $row['no'] = $i++;
        }
        unset($row);

        return $rows;
    }
}
?>


