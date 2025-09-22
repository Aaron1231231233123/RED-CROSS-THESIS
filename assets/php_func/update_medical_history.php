<?php
session_start();
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['donor_id'])) {
        $donor_id = $input['donor_id'];
        $updateData = [];
        
        // Build update data from input
        if (isset($input['medical_approval'])) {
            $updateData['medical_approval'] = $input['medical_approval'];
        }
        if (isset($input['disapproval_reason'])) {
            $updateData['disapproval_reason'] = $input['disapproval_reason'];
        }
        if (isset($input['updated_at'])) {
            $updateData['updated_at'] = $input['updated_at'];
        }
        
        if (!empty($updateData)) {
            try {
                // Check if medical history record exists
                $checkCurl = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id);
                curl_setopt($checkCurl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($checkCurl, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json'
                ]);
                
                $checkResponse = curl_exec($checkCurl);
                $checkHttpCode = curl_getinfo($checkCurl, CURLINFO_HTTP_CODE);
                curl_close($checkCurl);
                
                if ($checkHttpCode === 200) {
                    $existingRecords = json_decode($checkResponse, true) ?: [];
                    
                    if (!empty($existingRecords)) {
                        // Update existing record
                        $medical_history_id = $existingRecords[0]['medical_history_id'];
                        
                        $updateCurl = curl_init(SUPABASE_URL . '/rest/v1/medical_history?medical_history_id=eq.' . $medical_history_id);
                        curl_setopt($updateCurl, CURLOPT_CUSTOMREQUEST, 'PATCH');
                        curl_setopt($updateCurl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($updateCurl, CURLOPT_HTTPHEADER, [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json',
                            'Prefer: return=minimal'
                        ]);
                        curl_setopt($updateCurl, CURLOPT_POSTFIELDS, json_encode($updateData));
                        
                        $updateResponse = curl_exec($updateCurl);
                        $updateHttpCode = curl_getinfo($updateCurl, CURLINFO_HTTP_CODE);
                        curl_close($updateCurl);
                        
                        if ($updateHttpCode >= 200 && $updateHttpCode < 300) {
                            $response = ['success' => true, 'message' => 'Medical history updated successfully'];
                        } else {
                            $response = ['success' => false, 'message' => 'Failed to update medical history. HTTP Code: ' . $updateHttpCode];
                        }
                    } else {
                        // Create new record if none exists
                        $createData = array_merge([
                            'donor_id' => $donor_id,
                            'created_at' => gmdate('c')
                        ], $updateData);
                        
                        $createCurl = curl_init(SUPABASE_URL . '/rest/v1/medical_history');
                        curl_setopt($createCurl, CURLOPT_POST, true);
                        curl_setopt($createCurl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($createCurl, CURLOPT_HTTPHEADER, [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json',
                            'Prefer: return=minimal'
                        ]);
                        curl_setopt($createCurl, CURLOPT_POSTFIELDS, json_encode($createData));
                        
                        $createResponse = curl_exec($createCurl);
                        $createHttpCode = curl_getinfo($createCurl, CURLINFO_HTTP_CODE);
                        curl_close($createCurl);
                        
                        if ($createHttpCode >= 200 && $createHttpCode < 300) {
                            $response = ['success' => true, 'message' => 'Medical history created successfully'];
                        } else {
                            $response = ['success' => false, 'message' => 'Failed to create medical history. HTTP Code: ' . $createHttpCode];
                        }
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Failed to check existing medical history. HTTP Code: ' . $checkHttpCode];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'No update data provided'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Donor ID is required'];
    }
} else {
    $response = ['success' => false, 'message' => 'Invalid request method'];
}

echo json_encode($response);
?>
