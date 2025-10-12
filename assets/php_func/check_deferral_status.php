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

// Enable output compression for faster transfer
if (!ob_start('ob_gzhandler')) ob_start();

try {
    // OPTIMIZATION: Limit fields and add limit to query - only get what we need
    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=temporarily_deferred,permanently_deferred,refuse,temp_deferral_reason,perm_deferral_reason,refuse_reason,remarks,created_at&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Add timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Connection timeout
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $physical_exam_data = json_decode($response, true);
        
        if (is_array($physical_exam_data) && !empty($physical_exam_data)) {
            // Get the most recent physical examination record
            $latest_exam = $physical_exam_data[0];
            
            $isDeferred = false;
            $isRefused = false;
            $deferralType = null;
            $reason = null;
            
            // OPTIMIZATION: Simplified logic with early returns
            // Check for permanent deferral first (highest priority)
            if (isset($latest_exam['permanently_deferred']) && $latest_exam['permanently_deferred'] === true) {
                $isDeferred = true;
                $deferralType = 'permanently_deferred';
                $reason = $latest_exam['perm_deferral_reason'] ?? null;
            }
            // Check for temporary deferral
            else if (isset($latest_exam['temporarily_deferred']) && $latest_exam['temporarily_deferred'] === true) {
                $isDeferred = true;
                $deferralType = 'temporarily_deferred';
                $reason = $latest_exam['temp_deferral_reason'] ?? null;
            }
            // Check for refusal
            else if (isset($latest_exam['refuse']) && $latest_exam['refuse'] === true) {
                $isRefused = true;
                $reason = $latest_exam['refuse_reason'] ?? null;
            }
            // If not deferred or refused by boolean flags, check the remarks field
            else if (isset($latest_exam['remarks'])) {
                $remarks = $latest_exam['remarks'];
                
                // Use switch for cleaner code
                switch ($remarks) {
                    case 'Permanently Deferred':
                        $isDeferred = true;
                        $deferralType = 'permanently_deferred';
                        $reason = $reason ?? 'Based on physician remarks';
                        break;
                    case 'Temporarily Deferred':
                        $isDeferred = true;
                        $deferralType = 'temporarily_deferred';
                        $reason = $reason ?? 'Based on physician remarks';
                        break;
                    case 'Refused':
                        $isRefused = true;
                        $reason = $reason ?? 'Based on physician remarks';
                        break;
                }
            }
            
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            echo json_encode([
                'success' => true,
                'isDeferred' => $isDeferred,
                'isRefused' => $isRefused,
                'deferralType' => $deferralType,
                'reason' => $reason,
                'examDate' => $latest_exam['created_at'] ?? null,
                'remarks' => $latest_exam['remarks'] ?? null
            ]);
            ob_end_flush();
            exit();
        } else {
            // No physical examination records found
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'isDeferred' => false,
                'isRefused' => false,
                'message' => 'No physical examination records found'
            ]);
            ob_end_flush();
            exit();
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching physical examination data',
            'http_code' => $http_code
        ]);
        ob_end_flush();
        exit();
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit();
}
?> 