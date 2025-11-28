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
            
            // Fetch blood type from screening_form (try donor_form_id first, then donor_id)
            $blood_type = null;
            
            // Try to get blood type from screening_form using donor_form_id
            if (isset($donorData['donor_id'])) {
                $screening_url = SUPABASE_URL . '/rest/v1/screening_form?select=blood_type&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1';
                $ch_screening = curl_init($screening_url);
                curl_setopt($ch_screening, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_screening, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]);
                $screening_response = curl_exec($ch_screening);
                $screening_http_code = curl_getinfo($ch_screening, CURLINFO_HTTP_CODE);
                curl_close($ch_screening);
                
                if ($screening_http_code === 200) {
                    $screening_data = json_decode($screening_response, true);
                    if (!empty($screening_data) && !empty($screening_data[0]['blood_type'])) {
                        $blood_type = $screening_data[0]['blood_type'];
                    }
                }
                
                // If not found via donor_form_id, try donor_id
                if (empty($blood_type)) {
                    $screening_url2 = SUPABASE_URL . '/rest/v1/screening_form?select=blood_type&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1';
                    $ch_screening2 = curl_init($screening_url2);
                    curl_setopt($ch_screening2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_screening2, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Accept: application/json'
                    ]);
                    $screening_response2 = curl_exec($ch_screening2);
                    $screening_http_code2 = curl_getinfo($ch_screening2, CURLINFO_HTTP_CODE);
                    curl_close($ch_screening2);
                    
                    if ($screening_http_code2 === 200) {
                        $screening_data2 = json_decode($screening_response2, true);
                        if (!empty($screening_data2) && !empty($screening_data2[0]['blood_type'])) {
                            $blood_type = $screening_data2[0]['blood_type'];
                        }
                    }
                }
            }
            
            // Add blood_type to donor data
            if ($blood_type) {
                $donorData['blood_type'] = $blood_type;
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