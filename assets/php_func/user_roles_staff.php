
<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: /REDCROSS/public/login.php");
    exit();
}

// Check for correct role
$required_role = 3; // Staff Role
if ($_SESSION['role_id'] !== $required_role) {
    header("Location: index.php");
    exit();
}

// Get user role from Supabase
$user_id = $_SESSION['user_id'];
$url = SUPABASE_URL . "/rest/v1/user_roles?select=user_staff_roles&user_id=eq." . urlencode($user_id);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY,
    'Content-Type: application/json',
    'Prefer: return=minimal'
));

$response = curl_exec($ch);
curl_close($ch);

$user_staff_roles = '';
if ($response) {
    $data = json_decode($response, true);
    if (!empty($data) && isset($data[0]['user_staff_roles'])) {
        $user_staff_roles = strtolower(trim($data[0]['user_staff_roles']));
    }
}

?>