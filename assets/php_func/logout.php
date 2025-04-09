<?php
// Start the session
session_start();

// Store role_id before clearing session
$role_id = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : null;

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect based on role
if ($role_id === 2) {
    header("Location: /REDCROSS/public/hospital-request.php");
} else {
    header("Location: /REDCROSS/public/login.php");
}
exit();
?> 