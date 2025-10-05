<?php
// Helpers for filtered search of Physical Examination rows

require_once __DIR__ . '/../../../conn/db_conn.php';
// Load sibling helper reliably using one-level-up path
require_once __DIR__ . '/../search_accont_physical_exam/search_helpers.php';

function fpe_status_canonical($status, $needs_review){
    $s = strtolower(trim((string)$status));
    if ($needs_review || $s === '' || $s === 'pending') return 'pending';
    if (strpos($s, 'accept') !== false) return 'accepted';
    // Map both refused and temporarily deferred to Deferred
    if (strpos($s, 'defer') !== false || strpos($s, 'refus') !== false) return 'deferred';
    // Map permanently deferred to Ineligible
    if (strpos($s, 'permanent') !== false) return 'ineligible';
    if (strpos($s, 'reject') !== false || strpos($s, 'declin') !== false) return 'deferred';
    return $s;
}

function fpe_match_status($status, $needs_review, $allowed){
    if (empty($allowed)) return true;
    $canon = fpe_status_canonical($status, $needs_review);
    $norm = array_map(function($v){
        $t = strtolower(trim((string)$v));
        if (strpos($t,'accept')!==false) return 'accepted';
        if (strpos($t,'ineligible')!==false) return 'ineligible';
        if (strpos($t,'defer')!==false || strpos($t,'refus')!==false || strpos($t,'reject')!==false || strpos($t,'declin')!==false) return 'deferred';
        if ($t==='pending' || $t==='') return 'pending';
        return $t;
    }, $allowed);
    return in_array($canon, $norm, true);
}

function fpe_build_filtered_rows($filters, $limit = 150, $q = ''){
    $donorTypes = isset($filters['donor_type']) && is_array($filters['donor_type']) ? $filters['donor_type'] : [];
    $statuses = isset($filters['status']) && is_array($filters['status']) ? $filters['status'] : [];
    // No Needs Review flag in filters

    // Start from quick search base set when q given; else pull recent exams
    if (is_string($q) && trim($q) !== '') {
        $base = spe_search_rows($q, $limit);
    } else {
        // fallback: recent physical exams
        $physExams = spe_supabase_get('physical_examination', ['physical_exam_id','screening_id','donor_id','remarks','needs_review','updated_at','created_at','physician'], [ 'order' => 'updated_at.desc' ]);
        // donor cache
        $allIds = array_values(array_unique(array_map(function($e){ return (int)$e['donor_id']; }, $physExams)));
        $donorCache = [];
        if (!empty($allIds)) {
            $donors = spe_supabase_get('donor_form', ['donor_id','surname','first_name','middle_name'], [ 'donor_id' => 'in.(' . implode(',', $allIds) . ')' ]);
            foreach ($donors as $d) { if (isset($d['donor_id'])) $donorCache[(int)$d['donor_id']] = $d; }
        }
        $elig = spe_supabase_get('eligibility', ['donor_id']);
        $eligByDonor = [];
        foreach ($elig as $er) { if (isset($er['donor_id'])) $eligByDonor[(int)$er['donor_id']] = true; }
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
            // Do not enforce limit here; apply limit after filtering to avoid dropping matches
        }
        $base = spe_build_rows_from_records($records);
    }

    // Apply filters to base rows
    $out = [];
    foreach ($base as $row) {
        $dt = $row['donor_type'];
        if (!empty($donorTypes) && !in_array($dt, $donorTypes, true)) continue;
        $needsReview = isset($row['needs_review']) ? (bool)$row['needs_review'] : false;
        if (!fpe_match_status(isset($row['status']) ? $row['status'] : '', $needsReview, $statuses)) continue;
        $out[] = $row;
        if (count($out) >= $limit) break;
    }
    // Keep table numbering consistent (No. column)
    $i = 1;
    // Apply limit AFTER filtering to ensure filters are effective across the full dataset
    if (count($out) > $limit) {
        $out = array_slice($out, 0, $limit);
    }
    foreach ($out as &$r) { $r['no'] = $i++; }
    return $out;
}

?>


