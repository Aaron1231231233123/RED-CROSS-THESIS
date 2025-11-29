<?php
/**
 * Update blood_collection needs_review and access when admin opens donor details modal
 * Sets needs_review = TRUE and access = '2' for admin access
 */

session_start();
require_once '../../conn/db_conn.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin access required.']);
    exit();
}

// Get donor_id from request
$donor_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $donor_id = isset($input['donor_id']) ? intval($input['donor_id']) : null;
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $donor_id = isset($_GET['donor_id']) ? intval($_GET['donor_id']) : null;
}

if (!$donor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing donor_id parameter']);
    exit();
}

try {
    // First, get physical_exam_id for this donor
    $pe_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($pe_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($pe_ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    ]);
    $pe_resp = curl_exec($pe_ch);
    $pe_http = curl_getinfo($pe_ch, CURLINFO_HTTP_CODE);
    curl_close($pe_ch);

    if ($pe_http !== 200 || !$pe_resp) {
        error_log("No physical examination found for donor_id: $donor_id");
        echo json_encode([
            'success' => true,
            'message' => 'No physical examination record found (blood collection will be created when physical exam is submitted)',
            'donor_id' => $donor_id
        ]);
        exit();
    }

    $pe_data = json_decode($pe_resp, true) ?: [];
    if (empty($pe_data) || !isset($pe_data[0]['physical_exam_id'])) {
        error_log("No physical_exam_id found for donor_id: $donor_id");
        echo json_encode([
            'success' => true,
            'message' => 'No physical examination record found (blood collection will be created when physical exam is submitted)',
            'donor_id' => $donor_id
        ]);
        exit();
    }

    $physical_exam_id = $pe_data[0]['physical_exam_id'];

    // Check if blood_collection record exists for this physical_exam_id
    $check_ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id&physical_exam_id=eq.' . urlencode($physical_exam_id) . '&limit=1');
    curl_setopt($check_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($check_ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    ]);
    $check_resp = curl_exec($check_ch);
    $check_http = curl_getinfo($check_ch, CURLINFO_HTTP_CODE);
    curl_close($check_ch);

    $existing_bc = ($check_http === 200) ? (json_decode($check_resp, true) ?: []) : [];
    $has_existing = is_array($existing_bc) && !empty($existing_bc);

    if ($has_existing) {
        // UPDATE existing record - set needs_review=TRUE and access='2'
        $bc_id = $existing_bc[0]['blood_collection_id'];
        $bc_ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?blood_collection_id=eq.' . urlencode($bc_id));
        curl_setopt($bc_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        
        $nowIso = (new DateTime())->format('c');
        $bc_body = [
            'needs_review' => true,
            'access' => '2',
            'updated_at' => $nowIso
        ];
        
        curl_setopt($bc_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($bc_ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        curl_setopt($bc_ch, CURLOPT_POSTFIELDS, json_encode($bc_body));
        
        $bc_resp = curl_exec($bc_ch);
        $bc_http = curl_getinfo($bc_ch, CURLINFO_HTTP_CODE);
        curl_close($bc_ch);
        
        if ($bc_http >= 200 && $bc_http < 300) {
            error_log("Blood collection updated successfully for admin access - donor_id: $donor_id, physical_exam_id: $physical_exam_id, needs_review=TRUE, access=2");
            echo json_encode([
                'success' => true,
                'message' => 'Blood collection updated successfully',
                'donor_id' => $donor_id,
                'physical_exam_id' => $physical_exam_id
            ]);
        } else {
            error_log("Warning: Failed to update blood collection for donor_id: $donor_id. HTTP Code: $bc_http, Response: " . substr($bc_resp, 0, 500));
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update blood collection record'
            ]);
        }
    } else {
        // No existing record - this is okay, just return success
        // The record will be created when physical examination is submitted
        error_log("No blood collection record found for donor_id: $donor_id, physical_exam_id: $physical_exam_id - will be created on physical exam submission");
        echo json_encode([
            'success' => true,
            'message' => 'No blood collection record found (will be created on physical exam submission)',
            'donor_id' => $donor_id,
            'physical_exam_id' => $physical_exam_id
        ]);
    }
} catch (Exception $e) {
    error_log("Error updating blood collection for admin access: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error updating blood collection: ' . $e->getMessage()
    ]);
}
?>

