<?php
/**
 * Update physical_examination needs_review and access when admin opens donor details modal
 * Sets needs_review = TRUE and access = '2' for admin access
 */

session_start();
require_once '../conn/db_conn.php';

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
    // Check if physical_examination record exists for this donor
    $check_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($check_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($check_ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    $check_resp = curl_exec($check_ch);
    $check_http = curl_getinfo($check_ch, CURLINFO_HTTP_CODE);
    curl_close($check_ch);

    $existing_exam = ($check_http === 200) ? (json_decode($check_resp, true) ?: []) : [];
    $has_existing = is_array($existing_exam) && !empty($existing_exam);

    if ($has_existing) {
        // UPDATE existing record - set needs_review=TRUE and access='2'
        $pe_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id);
        curl_setopt($pe_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        
        $pe_body = [
            'needs_review' => true,
            'access' => '2',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        curl_setopt($pe_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($pe_ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        curl_setopt($pe_ch, CURLOPT_POSTFIELDS, json_encode($pe_body));
        
        $pe_resp = curl_exec($pe_ch);
        $pe_http = curl_getinfo($pe_ch, CURLINFO_HTTP_CODE);
        curl_close($pe_ch);
        
        if ($pe_http >= 200 && $pe_http < 300) {
            error_log("Physical examination updated successfully for admin access - donor_id: $donor_id, needs_review=TRUE, access=2");
            echo json_encode([
                'success' => true,
                'message' => 'Physical examination updated successfully',
                'donor_id' => $donor_id
            ]);
        } else {
            error_log("Warning: Failed to update physical examination for donor_id: $donor_id. HTTP Code: $pe_http, Response: " . substr($pe_resp, 0, 500));
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update physical examination record'
            ]);
        }
    } else {
        // No existing record - this is okay, just return success
        // The record will be created when screening form is submitted
        error_log("No physical examination record found for donor_id: $donor_id - will be created on screening submission");
        echo json_encode([
            'success' => true,
            'message' => 'No physical examination record found (will be created on screening submission)',
            'donor_id' => $donor_id
        ]);
    }
} catch (Exception $e) {
    error_log("Error updating physical examination for admin access: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error updating physical examination: ' . $e->getMessage()
    ]);
}
?>

