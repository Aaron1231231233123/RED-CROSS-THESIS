<?php
/**
 * API Endpoint: Handle Admin Donor Registration Form Submissions
 * Handles step 1 (Personal Data) and step 2 (Medical History) submissions
 * Admin-only endpoint
 */

session_start();
require_once '../../assets/conn/db_conn.php';

// Utility functions for donor number and barcode generation
if (!function_exists('generateDonorNumber')) {
    function generateDonorNumber() {
        $year = date('Y');
        $randomNumber = mt_rand(10000, 99999); // 5-digit random number
        return "PRC-$year-$randomNumber";
    }
}

if (!function_exists('generateNNBNetBarcode')) {
    function generateNNBNetBarcode() {
        $year = date('Y');
        $randomNumber = mt_rand(1000, 9999); // 4-digit random number
        return "DOH-$year$randomNumber";
    }
}

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin access required.']);
    exit();
}

// Handle POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$step = isset($_POST['step']) ? intval($_POST['step']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    if ($step === 1) {
        // Handle Personal Data submission
        // Validate required fields (excluding permanent_address - it will be built from components)
        $requiredFields = ['surname', 'first_name', 'birthdate', 'age', 'sex', 'civil_status', 'nationality', 'religion', 'education', 'occupation', 'mobile', 'email'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                $missingFields[] = $field;
            }
        }
        
        // Validate required address components
        $requiredAddressFields = ['barangay', 'town_municipality', 'province_city'];
        foreach ($requiredAddressFields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            throw new Exception("Missing required fields: " . implode(', ', $missingFields));
        }
        
        // Build permanent address from components (same order as original form)
        // Order: House/Unit No., Street, Barangay, Town/Municipality, Province/City, ZIP Code
        $addressParts = [];
        
        if (!empty($_POST['address_no'])) {
            $addressParts[] = trim($_POST['address_no']);
        }
        if (!empty($_POST['street'])) {
            $addressParts[] = trim($_POST['street']);
        }
        if (!empty($_POST['barangay'])) {
            $addressParts[] = trim($_POST['barangay']);
        }
        if (!empty($_POST['town_municipality'])) {
            $addressParts[] = trim($_POST['town_municipality']);
        }
        if (!empty($_POST['province_city'])) {
            $addressParts[] = trim($_POST['province_city']);
        }
        if (!empty($_POST['zip_code'])) {
            $addressParts[] = trim($_POST['zip_code']);
        }
        
        // Join all address parts with commas
        $permanent_address = implode(', ', $addressParts);
        
        // Ensure permanent_address is not empty after building
        if (empty($permanent_address)) {
            throw new Exception("Permanent address could not be constructed from address components");
        }
        
        // Prepare data
        $formData = [
            'surname' => $_POST['surname'] ?? '',
            'first_name' => $_POST['first_name'] ?? '',
            'middle_name' => $_POST['middle_name'] ?? '',
            'birthdate' => $_POST['birthdate'] ?? '',
            'age' => !empty($_POST['age']) ? intval($_POST['age']) : null,
            'sex' => $_POST['sex'] ?? '',
            'civil_status' => $_POST['civil_status'] ?? '',
            'permanent_address' => $permanent_address,
            'nationality' => $_POST['nationality'] ?? 'Filipino',
            'religion' => $_POST['religion'] ?? '',
            'education' => $_POST['education'] ?? '',
            'occupation' => $_POST['occupation'] ?? '',
            'mobile' => $_POST['mobile'] ?? '',
            'email' => $_POST['email'] ?? '',
            'prc_donor_number' => generateDonorNumber(),
            'doh_nnbnets_barcode' => generateNNBNetBarcode(),
            'registration_channel' => 'PRC Portal'
        ];
        
        // Store in session
        $_SESSION['donor_form_data'] = $formData;
        $_SESSION['donor_form_timestamp'] = time();
        
        // Insert donor record immediately for admin flow
        $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($formData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($response, true);
            if (is_array($responseData) && !empty($responseData) && isset($responseData[0]['donor_id'])) {
                $donor_id = $responseData[0]['donor_id'];
                $_SESSION['donor_id'] = $donor_id;
                
                // Generate mobile app account if email is provided
                if (!empty($formData['email'])) {
                    require_once '../../assets/php_func/mobile-account-generator.php';
                    $mobileGenerator = new MobileAccountGenerator();
                    $formData['donor_id'] = $donor_id;
                    $mobileResult = $mobileGenerator->generateMobileAccount($formData);
                    
                    if ($mobileResult['success']) {
                        $_SESSION['mobile_account_generated'] = true;
                        $_SESSION['mobile_credentials'] = [
                            'email' => $mobileResult['email'],
                            'password' => $mobileResult['password']
                        ];
                        $_SESSION['mobile_account_generated_time'] = time();
                        $_SESSION['donor_registered_name'] = $formData['first_name'] . ' ' . $formData['surname'];
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Personal data saved successfully',
                    'donor_id' => $donor_id,
                    'next_step' => 2
                ]);
            } else {
                throw new Exception("Failed to create donor record. Invalid response format.");
            }
        } else {
            throw new Exception("Failed to create donor record. HTTP Code: $httpCode. Response: $response");
        }
        
    } elseif ($step === 2) {
        // Handle Medical History submission
        if (!isset($_SESSION['donor_id'])) {
            throw new Exception("Donor ID not found in session");
        }
        
        $donor_id = $_SESSION['donor_id'];
        $action = isset($_POST['action']) ? $_POST['action'] : 'admin_complete';
        
        // Get interviewer name (admin user)
        $user_id = $_SESSION['user_id'];
        $interviewer_name = 'Admin';
        
        $supabaseFnsPath = realpath(__DIR__ . '/../../assets/php_func/supabase_functions.php');
        if ($supabaseFnsPath && file_exists($supabaseFnsPath)) {
            try {
                require_once $supabaseFnsPath;
                if (function_exists('supabaseRequest')) {
                    $user_data = supabaseRequest("users?user_id=eq.$user_id&select=first_name,surname");
                    if (!empty($user_data) && is_array($user_data)) {
                        $user = $user_data[0];
                        $interviewer_name = trim($user['first_name'] . ' ' . $user['surname']);
                    }
                }
            } catch (Exception $e) {
                error_log("Error fetching user name via supabase_functions.php: " . $e->getMessage());
            }
        }
        
        // Check if medical history record exists
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id . '&select=medical_history_id');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        $checkResponse = curl_exec($ch);
        $checkHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $medicalHistoryExists = false;
        if ($checkHttpCode === 200) {
            $checkData = json_decode($checkResponse, true);
            $medicalHistoryExists = !empty($checkData) && isset($checkData[0]['medical_history_id']);
        }
        
        // Prepare medical history data
        require_once '../../assets/php_func/medical_history_utils.php';
        
        $medical_history_data = [
            'donor_id' => $donor_id,
            'interviewer' => $interviewer_name
        ];
        
        // Process all question responses
        for ($i = 1; $i <= 37; $i++) {
            if (isset($_POST["q$i"])) {
                $fieldName = getMedicalHistoryFieldName($i);
                if ($fieldName) {
                    $medical_history_data[$fieldName] = $_POST["q$i"] === 'Yes';
                    if (isset($_POST["q{$i}_remarks"]) && $_POST["q{$i}_remarks"] !== 'None') {
                        $medical_history_data[$fieldName . '_remarks'] = $_POST["q{$i}_remarks"];
                    }
                }
            }
        }
        
        // For admin registration flow, set is_admin and mark as completed
        if ($action === 'admin_complete') {
            $medical_history_data['is_admin'] = 'True';
            $medical_history_data['needs_review'] = false;
            // Note: We don't set medical_approval here - it will be set by the workflow
        }
        
        // Insert or update medical history
        if ($medicalHistoryExists) {
            // Update existing record
            $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($medical_history_data));
        } else {
            // Insert new record
            $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($medical_history_data));
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            echo json_encode([
                'success' => true,
                'message' => 'Medical history saved successfully',
                'donor_id' => $donor_id,
                'next_step' => 'credentials', // Show credentials modal
                'credentials' => $_SESSION['mobile_credentials'] ?? null,
                'mobile_account_generated' => $_SESSION['mobile_account_generated'] ?? false,
                'donor_name' => $_SESSION['donor_registered_name'] ?? null
            ]);
        } else {
            throw new Exception("Failed to save medical history. HTTP Code: $httpCode. Response: $response");
        }
        
    } else {
        throw new Exception("Invalid step: $step");
    }
    
} catch (Exception $e) {
    error_log("Admin donor registration error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

