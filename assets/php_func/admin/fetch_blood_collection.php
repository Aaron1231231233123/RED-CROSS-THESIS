<?php
/**
 * Fetch Blood Collection Data by physical_exam_id
 * Admin-specific endpoint to fetch blood collection details
 */

// Suppress any HTML errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Correct path from admin folder to conn folder
require_once dirname(dirname(dirname(__FILE__))) . '/conn/db_conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$physical_exam_id = $_GET['physical_exam_id'] ?? null;

if (!$physical_exam_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'physical_exam_id is required']);
    exit;
}

try {
    // Fetch blood collection data from Supabase
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?physical_exam_id=eq.' . urlencode($physical_exam_id) . '&order=created_at.desc&limit=1',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ],
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        throw new Exception('cURL Error: ' . $error);
    }
    
    if ($http_code !== 200) {
        throw new Exception('HTTP Error: ' . $http_code);
    }
    
    $data = json_decode($response, true);
    
    if (empty($data) || !is_array($data)) {
        echo json_encode([]);
        exit;
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    error_log('Error fetching blood collection: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching blood collection data']);
}

