<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /RED-CROSS-THESIS/public/login.php");
    exit();
}

// Check for correct role (staff with role_id 3)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../../public/unauthorized.php");
    exit();
}

// Redirect to medical history submissions dashboard
header("Location: dashboard-staff-medical-history-submissions.php");
exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Submission Dashboard - Redirecting</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f5f5f5;
        }
        .redirect-message {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #b22222;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="redirect-message">
        <h2>Redirecting...</h2>
        <div class="spinner"></div>
        <p>This dashboard has been moved to the Medical History Submissions page.</p>
        <p>You will be redirected automatically.</p>
    </div>
    
    <script>
        // Redirect after 3 seconds
        setTimeout(function() {
            window.location.href = 'dashboard-staff-medical-history-submissions.php';
        }, 3000);
    </script>
</body>
</html>