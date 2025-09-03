<?php
// Toggle flags to move a donor from physical_examination review to blood_collection
header('Content-Type: application/json');
require_once '../conn/db_conn.php';

try {
    $donor_id = isset($_POST['donor_id']) ? intval($_POST['donor_id']) : 0;
    if ($donor_id <= 0) {
        // Support JSON payload
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (isset($json['donor_id'])) $donor_id = intval($json['donor_id']);
    }
    if ($donor_id <= 0) throw new Exception('Invalid donor_id');

    $now = gmdate('Y-m-d H:i:s') . '+00';

    // 1) Get latest physical_examination for donor
    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,screening_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200) throw new Exception('Failed to fetch physical examination');
    $rows = json_decode($resp, true) ?: [];
    if (empty($rows)) throw new Exception('No physical examination found for donor');
    $pe = $rows[0];
    $physical_exam_id = $pe['physical_exam_id'];
    $screening_id = $pe['screening_id'] ?? null;

    // 2) Update physical_examination: status='Accepted', remarks='Accepted', needs_review=false, updated_at
    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . $physical_exam_id);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'status' => 'Accepted',
        'remarks' => 'Accepted',
        'needs_review' => false,
        'updated_at' => $now
    ]));
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http < 200 || $http >= 300) throw new Exception('Failed to update physical_examination');

    // 3) Upsert blood_collection: needs_review=true, updated_at
    $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id&physical_exam_id=eq.' . $physical_exam_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $existing = ($http === 200) ? (json_decode($resp, true) ?: []) : [];

    if (!empty($existing)) {
        $bc_id = $existing[0]['blood_collection_id'];
        $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?blood_collection_id=eq.' . $bc_id);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([ 'needs_review' => true, 'updated_at' => $now ]));
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http < 200 || $http >= 300) throw new Exception('Failed to update blood_collection');
    } else {
        $payload = [
            'physical_exam_id' => $physical_exam_id,
            'needs_review' => true,
            'status' => 'pending',
            'updated_at' => $now
        ];
        if ($screening_id) $payload['screening_id'] = $screening_id;
        $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http < 200 || $http >= 300) throw new Exception('Failed to create blood_collection');
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


