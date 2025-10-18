<?php
/**
 * Mobile Account Generation Service
 * 
 * This service handles the automatic generation of mobile web app accounts
 * for donors registered through the PRC admin system.
 * 
 * Features:
 * - Generates password using surname + birth year format
 * - Creates user account in Supabase Auth
 * - Links donor record to mobile app user
 * - Handles error cases and rollback
 */

require_once __DIR__ . '/../conn/db_conn.php';

class MobileAccountGenerator {
    
    private $supabase_url;
    private $supabase_key;
    
    public function __construct() {
        $this->supabase_url = SUPABASE_URL;
        $this->supabase_key = SUPABASE_API_KEY;
    }
    
    /**
     * Generate mobile app account for a donor
     * 
     * @param array $donor_data Donor information from registration form
     * @return array Result with success status and generated credentials
     */
    public function generateMobileAccount($donor_data) {
        try {
            // Validate required donor data
            if (!$this->validateDonorData($donor_data)) {
                return [
                    'success' => false,
                    'error' => 'Invalid donor data provided'
                ];
            }
            
            // Generate password using surname + birth year format
            $generated_password = $this->generatePassword($donor_data);
            
            // Create user in Supabase Auth
            $auth_result = $this->createAuthUser($donor_data['email'], $generated_password);
            
            if (!$auth_result['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to create auth user: ' . $auth_result['error']
                ];
            }
            
            $user_id = $auth_result['user_id'];
            
            // Update existing donor_form record with mobile account info
            $update_result = $this->updateDonorFormWithMobileAccount($donor_data['donor_id'], $donor_data['email'], $generated_password);
            
            if (!$update_result['success']) {
                // Rollback: Delete auth user if donor form update fails
                $this->deleteAuthUser($user_id);
                return [
                    'success' => false,
                    'error' => 'Failed to update donor form: ' . $update_result['error']
                ];
            }
            
            // Create verified email verification record for PWA compatibility
            $verification_result = $this->createVerifiedEmailRecord($donor_data['email'], $user_id);
            
            if (!$verification_result['success']) {
                // Rollback: Delete auth user if verification record creation fails
                $this->deleteAuthUser($user_id);
                return [
                    'success' => false,
                    'error' => 'Failed to create verification record: ' . $verification_result['error']
                ];
            }
            
            return [
                'success' => true,
                'user_id' => $user_id,
                'email' => $donor_data['email'],
                'password' => $generated_password,
                'donor_id' => $donor_data['donor_id']
            ];
            
        } catch (Exception $e) {
            error_log("Mobile Account Generation Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate donor data
     */
    private function validateDonorData($donor_data) {
        $required_fields = ['email', 'surname', 'birthdate', 'donor_id'];
        
        foreach ($required_fields as $field) {
            if (empty($donor_data[$field])) {
                error_log("Missing required field: $field");
                return false;
            }
        }
        
        // Validate email format
        if (!filter_var($donor_data['email'], FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email format: " . $donor_data['email']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate password using surname + birth year format
     * Example: "Dela Cruz" + "1995" = "DelaCruz1995"
     */
    private function generatePassword($donor_data) {
        $surname = $donor_data['surname'];
        $birth_year = date('Y', strtotime($donor_data['birthdate']));
        
        // Clean surname: remove spaces, special characters, convert to title case
        $clean_surname = preg_replace('/[^a-zA-Z]/', '', $surname);
        $clean_surname = ucfirst(strtolower($clean_surname));
        
        $password = $clean_surname . $birth_year;
        
        error_log("Generated password for {$donor_data['email']}: {$password}");
        
        return $password;
    }
    
    /**
     * Create user in Supabase Auth using the same approach as PWA
     */
    private function createAuthUser($email, $password) {
        $data = [
            'email' => $email,
            'password' => $password,
            'email_confirm' => true // Skip email verification for admin-registered donors
        ];
        
        $response = $this->makeSupabaseRequest('auth/v1/admin/users', 'POST', $data, true);
        
        // Log the response for debugging
        error_log("Auth user creation response: " . json_encode($response));
        
        if ($response['success'] && isset($response['data']['id'])) {
            return [
                'success' => true,
                'user_id' => $response['data']['id']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to create auth user: ' . ($response['error'] ?? 'Unknown error') . ' | Status: ' . $response['status_code'] . ' | Response: ' . json_encode($response['data'])
        ];
    }
    
    /**
     * Create donor_form record for mobile app (same as PWA)
     */
    private function createDonorForm($donor_data) {
        $donor_form_data = [
            'surname' => $donor_data['surname'] ?? '',
            'first_name' => $donor_data['first_name'] ?? '',
            'middle_name' => $donor_data['middle_name'] ?? null,
            'birthdate' => $donor_data['birthdate'] ?? null,
            'age' => $donor_data['age'] ?? null,
            'sex' => $donor_data['sex'] ?? '',
            'civil_status' => $donor_data['civil_status'] ?? 'Single',
            'permanent_address' => $donor_data['permanent_address'] ?? '',
            'nationality' => $donor_data['nationality'] ?? '',
            'religion' => $donor_data['religion'] ?? null,
            'education' => $donor_data['education'] ?? null,
            'occupation' => $donor_data['occupation'] ?? '',
            'mobile' => $donor_data['mobile'] ?? '',
            'email' => $donor_data['email'],
            'prc_donor_number' => $this->generateDonorNumber(),
            'doh_nnbnets_barcode' => $this->generateNNBNetBarcode(),
            'registration_channel' => 'Mobile' // Use 'Mobile' like PWA, not 'Admin'
        ];
        
        // Remove null values (same as PWA)
        foreach ($donor_form_data as $key => $value) {
            if ($value === null) {
                unset($donor_form_data[$key]);
            }
        }
        
        $headers = [
            'Prefer: return=representation',
            'Content-Profile: public'
        ];
        
        $response = $this->makeSupabaseRequest('rest/v1/donor_form', 'POST', $donor_form_data, true, $headers);
        
        // Log the response for debugging
        error_log("Donor form creation response: " . json_encode($response));
        
        if ($response['success'] && isset($response['data'][0]['id'])) {
            return [
                'success' => true,
                'donor_form_id' => $response['data'][0]['id']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to create donor_form record: ' . ($response['error'] ?? 'Unknown error') . ' | Status: ' . $response['status_code'] . ' | Response: ' . json_encode($response['data'])
        ];
    }
    
    /**
     * Generate PRC donor number (same as PWA)
     */
    private function generateDonorNumber() {
        $year = date('Y');
        $randomNumber = mt_rand(10000, 99999); // 5-digit random number
        return "PRC-$year-$randomNumber";
    }
    
    /**
     * Generate DOH NNBNETS barcode (same as PWA)
     */
    private function generateNNBNetBarcode() {
        $year = date('Y');
        $randomNumber = mt_rand(1000, 9999); // 4-digit random number
        return "DOH-$year$randomNumber";
    }
    
    /**
     * Create verified email verification record for PWA compatibility
     */
    private function createVerifiedEmailRecord($email, $user_id) {
        $verification_data = [
            'email' => $email,
            'verification_code' => '000000', // Dummy code since it's already verified
            'user_id' => $user_id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')), // Set far future expiry
            'verified' => true,
            'verified_at' => date('Y-m-d H:i:s')
        ];
        
        $response = $this->makeSupabaseRequest('rest/v1/email_verifications', 'POST', $verification_data, true);
        
        // Log the response for debugging
        error_log("Email verification record creation response: " . json_encode($response));
        
        if ($response['success']) {
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to create email verification record: ' . ($response['error'] ?? 'Unknown error') . ' | Status: ' . $response['status_code'] . ' | Response: ' . json_encode($response['data'])
        ];
    }
    
    /**
     * Update existing donor_form record with mobile account information
     */
    private function updateDonorFormWithMobileAccount($donor_id, $email, $password) {
        // Update email field and mark as verified for PWA compatibility
        $update_data = [
            'email' => $email,
            'email_verified' => true,
            'email_verified_at' => date('Y-m-d H:i:s')
        ];
        
        $response = $this->makeSupabaseRequest("rest/v1/donor_form?donor_id=eq.{$donor_id}", 'PATCH', $update_data, true);
        
        // Log the response for debugging
        error_log("Donor form update response: " . json_encode($response));
        
        if ($response['success']) {
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to update donor_form record: ' . ($response['error'] ?? 'Unknown error') . ' | Status: ' . $response['status_code'] . ' | Response: ' . json_encode($response['data'])
        ];
    }
    
    /**
     * Update donor_form table with mobile account information
     */
    private function updateDonorForm($donor_id, $email, $password) {
        $update_data = [
            'email' => $email,
            'mobile_account_created' => true,
            'mobile_password' => $password,
            'mobile_account_created_at' => date('Y-m-d H:i:s')
        ];
        
        $response = $this->makeSupabaseRequest("rest/v1/donor_form?id=eq.{$donor_id}", 'PATCH', $update_data);
        
        return [
            'success' => $response['success'],
            'error' => $response['error'] ?? null
        ];
    }
    
    /**
     * Delete auth user (for rollback)
     */
    private function deleteAuthUser($user_id) {
        $response = $this->makeSupabaseRequest("auth/v1/admin/users/{$user_id}", 'DELETE', null, true);
        return $response['success'];
    }
    
    /**
     * Make Supabase API request
     */
    private function makeSupabaseRequest($endpoint, $method = 'GET', $data = null, $use_service_role = false, $additional_headers = []) {
        $url = $this->supabase_url . '/' . $endpoint;
        
        // Use service role key for auth operations
        $api_key = $use_service_role ? SUPABASE_SERVICE_KEY : $this->supabase_key;
        
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $api_key,
            'Authorization: Bearer ' . $api_key
        ];
        
        // Merge additional headers
        $headers = array_merge($headers, $additional_headers);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'status_code' => $status_code
            ];
        }
        
        $decoded_response = json_decode($response, true);
        
        return [
            'success' => $status_code >= 200 && $status_code < 300,
            'data' => $decoded_response,
            'status_code' => $status_code,
            'raw_response' => $response,
            'error' => $status_code >= 400 ? ($decoded_response['message'] ?? 'HTTP Error') : null
        ];
    }
}

// Test function for standalone testing
if (isset($_GET['test'])) {
    $generator = new MobileAccountGenerator();
    
    $test_donor_data = [
        'donor_id' => 999,
        'email' => 'test@gmail.com',
        'surname' => 'Dela Cruz',
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'birthdate' => '1995-05-15',
        'age' => 28,
        'sex' => 'Male',
        'mobile' => '09123456789',
        'permanent_address' => 'Test Address'
    ];
    
    $result = $generator->generateMobileAccount($test_donor_data);
    
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}
?>
