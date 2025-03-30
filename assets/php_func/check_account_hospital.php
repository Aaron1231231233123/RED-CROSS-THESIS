<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check for correct role (example for admin dashboard)
$required_role = 2; // Hospital Role
if ($_SESSION['role_id'] !== $required_role) {
    header("Location: index.php");
    exit();
}

// Fetch user details from the users table
$user_id = $_SESSION['user_id'];

// Function to make Supabase request
function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $ch = curl_init($url);
    
    $headers = array(
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Fetch user data from Supabase
$users_response = supabaseRequest("users?user_id=eq.$user_id&select=surname,first_name");

if ($users_response && is_array($users_response) && count($users_response) > 0) {
    $user_data = $users_response[0];
    $_SESSION['user_surname'] = $user_data['surname'];
    $_SESSION['user_first_name'] = $user_data['first_name'];
}
?>