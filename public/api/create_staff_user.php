<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

session_start();
require_once '../../assets/conn/db_conn.php';
@include_once __DIR__ . '/../Dashboards/module/optimized_functions.php';

// Prefer service role for server-side writes if available (env or constant)
function get_supabase_write_key() {
    $envKey = getenv('SUPABASE_SERVICE_KEY');
    if (!empty($envKey)) { return $envKey; }
    if (defined('SUPABASE_SERVICE_KEY') && !empty(SUPABASE_SERVICE_KEY)) { return SUPABASE_SERVICE_KEY; }
    // Fallback to anon key (may fail due to RLS)
    return defined('SUPABASE_API_KEY') ? SUPABASE_API_KEY : '';
}

/**
 * Minimal write helper that always uses the service key for non-GET requests.
 * Returns [code => int, data => mixed, raw => string]
 */
function supabaseWrite($endpoint, $method = 'POST', $payload = null) {
    $url = SUPABASE_URL . '/rest/v1/' . ltrim($endpoint, '/');
    $apiKey = get_supabase_write_key();
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Prefer: return=representation'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $decoded = null;
    if ($response !== false) {
        $decoded = json_decode($response, true);
    }

    if ($response === false) {
        return [ 'code' => $httpCode ?: 0, 'data' => null, 'raw' => $error ?: 'Connection error' ];
    }

    return [ 'code' => $httpCode, 'data' => $decoded, 'raw' => $response ];
}

// Admin only
$required_role = 1;
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== $required_role) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$first_name = isset($input['first_name']) ? trim($input['first_name']) : '';
$surname = isset($input['surname']) ? trim($input['surname']) : '';
$middle_name = isset($input['middle_name']) ? trim($input['middle_name']) : '';
$suffix = isset($input['suffix']) ? trim($input['suffix']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? (string)$input['password'] : '';
$role_id = isset($input['role_id']) ? (int)$input['role_id'] : 0;
$subrole = isset($input['subrole']) ? trim($input['subrole']) : '';
// Optional profile fields (some tables may require NOT NULL like phone_number)
$phone_number = isset($input['phone_number']) ? trim($input['phone_number']) : '';
$telephone_number = isset($input['telephone_number']) ? trim($input['telephone_number']) : null;
$date_of_birth = isset($input['date_of_birth']) ? trim($input['date_of_birth']) : null;
$gender = isset($input['gender']) ? trim($input['gender']) : null;
$permanent_address = isset($input['permanent_address']) ? trim($input['permanent_address']) : null;
$office_address = isset($input['office_address']) ? trim($input['office_address']) : null;

if (empty($first_name) || empty($surname) || empty($email) || empty($password) || empty($role_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'First name, surname, email, password and role are required']);
    exit();
}

// Validate subrole for Staff role
if ($role_id === 3 && empty($subrole)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Staff subrole is required']);
    exit();
}

// Generate UUID v4 in PHP
function generate_uuid_v4() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$user_id = generate_uuid_v4();
$password_hash = password_hash($password, PASSWORD_BCRYPT);

try {
    // Create user
    $payload = [
        'user_id' => $user_id,
        'first_name' => $first_name,
        'surname' => $surname,
        'middle_name' => $middle_name,
        'suffix' => $suffix,
        'email' => $email,
        'password_hash' => $password_hash,
        'role_id' => $role_id,
        'is_active' => true,
        // Include optional profile fields; provide empty string for phone_number to satisfy NOT NULL
        'phone_number' => $phone_number !== '' ? $phone_number : '',
        'telephone_number' => $telephone_number,
        'date_of_birth' => ($date_of_birth !== '' ? $date_of_birth : null),
        'gender' => $gender,
        'permanent_address' => $permanent_address,
        'office_address' => $office_address
    ];
    // Use service-role for write to avoid RLS issues
    $createResp = supabaseWrite('users', 'POST', $payload);
    if ($createResp['code'] < 200 || $createResp['code'] >= 300 || empty($createResp['data'])) {
        // Try to provide a clearer error
        $msg = 'Failed to create user';
        if (!empty($createResp['raw'])) {
            // Check for common constraint messages (e.g., duplicate email)
            if (stripos($createResp['raw'], 'duplicate') !== false || $createResp['code'] === 409) {
                $msg = 'Email already exists';
            } else if ($createResp['code'] === 401 || $createResp['code'] === 403) {
                $msg = 'Insufficient permissions to create user (check RLS/policy)';
            } else if ($createResp['code'] >= 400) {
                $msg = trim($createResp['raw']);
            }
        }
        http_response_code($createResp['code'] ?: 500);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit();
    }

    // Create subrole entry for Staff users
    if ($role_id === 3 && !empty($subrole)) {
        $subrolePayload = [
            'user_id' => $user_id,
            'user_staff_roles' => $subrole
        ];
        $subroleResp = supabaseWrite('user_roles', 'POST', $subrolePayload);
        
        if ($subroleResp['code'] < 200 || $subroleResp['code'] >= 300 || empty($subroleResp['data'])) {
            error_log('Warning: User created but subrole failed to save');
        }
    }

    echo json_encode(['success' => true, 'user_id' => $user_id]);
} catch (Exception $e) {
    error_log('Create staff user error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error creating user']);
}

?>


