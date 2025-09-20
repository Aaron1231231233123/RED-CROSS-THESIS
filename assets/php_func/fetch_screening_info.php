<?php
session_start();
require_once '../conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if donor_id parameter exists
if (!isset($_GET['donor_id']) || empty($_GET['donor_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing donor ID parameter']);
    exit();
}

$donor_id = intval($_GET['donor_id']);

try {
    // Fetch screening information from screening_form table
    $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=screening_id,interview_date,body_weight,specific_gravity,blood_type,created_at&donor_form_id=eq.' . $donor_id . '&order=created_at.desc');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $screening_data = json_decode($response, true);
        
        if (is_array($screening_data) && !empty($screening_data)) {
            // Get the most recent screening record
            $latest_screening = $screening_data[0];
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'screening_id' => $latest_screening['screening_id'] ?? null,
                    'interview_date' => $latest_screening['interview_date'] ?? $latest_screening['created_at'] ?? null,
                    'body_weight' => $latest_screening['body_weight'] ?? null,
                    'specific_gravity' => $latest_screening['specific_gravity'] ?? null,
                    'blood_type' => $latest_screening['blood_type'] ?? null
                ]
            ]);
            exit();
        } else {
            // No screening records found
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No screening records found'
            ]);
            exit();
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching screening data',
            'http_code' => $http_code,
            'response' => $response
        ]);
        exit();
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
    exit();
}
?> 