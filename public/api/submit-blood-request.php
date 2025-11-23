<?php
session_start();
require_once '../../assets/conn/db_conn.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get JSON input
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Generate request_reference: REQ-YEAR-RandomString-RequestDate-ID-FirstLetterOfUsername
function generateRequestReference($userId, $userName, $requestDate) {
    $year = date('Y', strtotime($requestDate));
    $dateStr = date('Ymd', strtotime($requestDate));
    $randomStr = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
    $firstLetter = strtoupper(substr($userName, 0, 1));
    $idStr = str_pad($userId, 4, '0', STR_PAD_LEFT);
    
    return "REQ-{$year}-{$randomStr}-{$dateStr}-{$idStr}-{$firstLetter}";
}

try {
    // Get user information
    $userId = $_SESSION['user_id'];
    $userName = $_SESSION['user_first_name'] ?? $_SESSION['user_surname'] ?? 'User';
    $requestDate = $data['requested_on'] ?? date('Y-m-d H:i:s');
    
    // Generate request_reference
    $requestReference = generateRequestReference($userId, $userName, $requestDate);
    
    // Add request_reference to data
    $data['request_reference'] = $requestReference;
    
    // Submit to Supabase
    $ch = curl_init();
    $url = SUPABASE_URL . '/rest/v1/blood_requests';
    
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation' // Return the created record so we can get the request_id
    ];
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('CURL Error: ' . $error);
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        echo json_encode([
            'success' => true,
            'message' => 'Blood request submitted successfully',
            'request_reference' => $requestReference,
            'data' => $result
        ]);
    } else {
        $errorMsg = 'Failed to submit request. HTTP Code: ' . $httpCode;
        $errorData = json_decode($response, true);
        if ($errorData && isset($errorData['message'])) {
            $errorMsg .= '. ' . $errorData['message'];
        } else {
            $errorMsg .= '. Response: ' . $response;
        }
        throw new Exception($errorMsg);
    }
    
} catch (Exception $e) {
    error_log('Error submitting blood request: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

