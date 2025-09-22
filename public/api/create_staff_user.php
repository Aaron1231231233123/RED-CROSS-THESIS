<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

session_start();
require_once '../../assets/conn/db_conn.php';
@include_once __DIR__ . '/../Dashboards/module/optimized_functions.php';

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
        'is_active' => true
    ];
    $createResp = supabaseRequest('users', 'POST', $payload);

    if (!isset($createResp['data']) || empty($createResp['data'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
        exit();
    }

    // Create subrole entry for Staff users
    if ($role_id === 3 && !empty($subrole)) {
        $subrolePayload = [
            'user_id' => $user_id,
            'user_staff_roles' => $subrole
        ];
        $subroleResp = supabaseRequest('user_roles', 'POST', $subrolePayload);
        
        if (!isset($subroleResp['data']) || empty($subroleResp['data'])) {
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


