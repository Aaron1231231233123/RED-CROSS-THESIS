<?php
session_start();
require_once '../conn/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check for correct role (staff with role_id 3)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_donor_details':
        getDonorDetails();
        break;
    case 'update_donor':
        updateDonor();
        break;
    case 'get_screening_data':
        getScreeningData();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getDonorDetails() {
    global $SUPABASE_URL, $SUPABASE_API_KEY;
    
    $donor_id = $_GET['donor_id'] ?? null;
    
    if (!$donor_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
        exit();
    }
    
    try {
        // Get donor details from donor_form table
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $SUPABASE_URL . '/rest/v1/donor_form?select=*&donor_id=eq.' . $donor_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $SUPABASE_API_KEY,
                'Authorization: Bearer ' . $SUPABASE_API_KEY
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $donor_data = json_decode($response, true);
            if (is_array($donor_data) && !empty($donor_data)) {
                $donor = $donor_data[0];
                
                // Calculate age if missing but birthdate is available
                if (empty($donor['age']) && !empty($donor['birthdate'])) {
                    $birthDate = new DateTime($donor['birthdate']);
                    $today = new DateTime();
                    $donor['age'] = $birthDate->diff($today)->y;
                }
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'data' => $donor]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Donor not found']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to fetch donor data']);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function updateDonor() {
    global $SUPABASE_URL, $SUPABASE_API_KEY;
    
    $donor_id = $_POST['donor_id'] ?? null;
    $update_data = $_POST['update_data'] ?? null;
    
    if (!$donor_id || !$update_data) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Donor ID and update data are required']);
        exit();
    }
    
    try {
        // Update donor in donor_form table
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . $donor_id,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $SUPABASE_API_KEY,
                'Authorization: Bearer ' . $SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ],
            CURLOPT_POSTFIELDS => json_encode($update_data)
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Donor updated successfully']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update donor']);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function getScreeningData() {
    global $SUPABASE_URL, $SUPABASE_API_KEY;
    
    $donor_id = $_GET['donor_id'] ?? null;
    
    if (!$donor_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Donor ID is required']);
        exit();
    }
    
    try {
        // Get screening data from screening_form table
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $SUPABASE_URL . '/rest/v1/screening_form?select=*&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $SUPABASE_API_KEY,
                'Authorization: Bearer ' . $SUPABASE_API_KEY
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $screening_data = json_decode($response, true);
            if (is_array($screening_data) && !empty($screening_data)) {
                $screening = $screening_data[0];
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'data' => $screening]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No screening data found']);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to fetch screening data']);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>
