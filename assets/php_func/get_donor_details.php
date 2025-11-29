<?php
header('Content-Type: application/json');
require_once '../conn/db_conn.php';

if (!isset($_GET['donor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
    exit();
}

$donor_id = $_GET['donor_id'];

try {
    // Fetch donor form data
    $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=*&donor_id=eq.' . $donor_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0])) {
            $donorData = $data[0];
            
            // Fetch blood type from eligibility table (latest record)
            $bloodType = null;
            $eligCh = curl_init(SUPABASE_URL . '/rest/v1/eligibility?select=blood_type&donor_id=eq.' . $donor_id . '&order=updated_at.desc,created_at.desc&limit=1');
            curl_setopt($eligCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($eligCh, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]);
            $eligResponse = curl_exec($eligCh);
            $eligHttpCode = curl_getinfo($eligCh, CURLINFO_HTTP_CODE);
            curl_close($eligCh);
            
            if ($eligHttpCode === 200) {
                $eligData = json_decode($eligResponse, true);
                if (!empty($eligData) && isset($eligData[0]['blood_type'])) {
                    $bloodType = $eligData[0]['blood_type'];
                }
            }
            
            // Fallback: fetch from screening_form if not found in eligibility
            if ($bloodType === null) {
                $scrCh = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=blood_type&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
                curl_setopt($scrCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($scrCh, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]);
                $scrResponse = curl_exec($scrCh);
                $scrHttpCode = curl_getinfo($scrCh, CURLINFO_HTTP_CODE);
                curl_close($scrCh);
                
                if ($scrHttpCode === 200) {
                    $scrData = json_decode($scrResponse, true);
                    if (!empty($scrData) && isset($scrData[0]['blood_type'])) {
                        $bloodType = $scrData[0]['blood_type'];
                    }
                }
            }
            
            // Add blood type to donor data if found
            if ($bloodType !== null) {
                $donorData['blood_type'] = $bloodType;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $donorData
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Donor not found']);
        }
    } else {
        throw new Exception('Failed to fetch donor data. HTTP Code: ' . $http_code);
    }

} catch (Exception $e) {
    error_log('Error in get_donor_details.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 