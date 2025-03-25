<?php 
session_start(); 
require '../assets/conn/db_conn.php';

function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;

    $headers = [
        "Content-Type: application/json",
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Prefer: return=representation"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Fetch user from Supabase (only necessary fields)
    $query = "users?email=eq.$email&select=user_id,email,password_hash";
    $users = supabaseRequest($query, "GET");

    if (!empty($users)) {
        $user = $users[0]; // Fetch first matching user

        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];

            // Fetch role_id separately (if needed)
            $role_query = "user_roles?user_id=eq.{$user['user_id']}&select=role_id";
            $roles = supabaseRequest($role_query, "GET");

            if (!empty($roles)) {
                $_SESSION['role_id'] = $roles[0]['role_id'];

                // Redirect based on role
                switch ($roles[0]['role_id']) {
                    case 1:
                        header("Location: Dashboards/dashboard-Inventory-System.php");
                        break;
                    case 2:
                        header("Location: Dashboards/dashboard-hospital-bootstrap.php");
                        break;
                    case 3:
                        header("Location: Dashboards/dashboard-staff-main.php");
                        break;
                    default:
                        header("Location: ../error.php");
                }
                exit();
            }
        } else {
            $error_message = "Invalid email or password.";
        }
    } else {
        $error_message = "User not found.";
    }
}

?>








<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Login</title>
    <style>
        /* General Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Body Styling */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f9f9f9;
            display: flex;
            justify-content: center; 
            align-items: center; 
            height: 100vh;
            margin: 0;
        }

        /* Form Container */
        .login-form {
            background-color: #ffffff; 
            padding: 40px 30px; 
            border-radius: 12px; 
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); 
            width: 100%;
            max-width: 400px; 
            text-align: center; 
        }

        /* Logo and Title */
        .login-form h2:first-of-type {
            color: #d32f2f; 
            font-size: 24px;
            margin-bottom: 10px; 
        }

        .login-form hr {
            border: 0;
            height: 2px;
            background-color: #d32f2f; 
            margin: 10px auto 20px;
            width: 50px; 
        }

        .login-form h2:last-of-type {
            color: #333333; 
            font-size: 20px;
            margin-bottom: 25px; 
        }

        /* Labels */
        .login-form label {
            color: #555555; 
            font-weight: bold;
            display: block; 
            margin-bottom: 8px; 
            text-align: left; 
        }

        /* Input Fields */
        .login-form input[type="email"],
        .login-form input[type="password"] {
            width: 100%; 
            padding: 14px; 
            margin-bottom: 15px; 
            border: 1px solid #d32f2f; 
            border-radius: 8px; 
            font-size: 16px; 
            transition: border-color 0.3s ease;
        }

        .login-form input[type="email"]:focus,
        .login-form input[type="password"]:focus {
            border-color: #b71c1c; 
            outline: none; 
        }

        /* Submit Button */
        .login-form input[type="submit"] {
            width: 100%; 
            padding: 14px; 
            background-color: #d32f2f; 
            border: none;
            color: white;
            font-size: 18px; 
            font-weight: bold;
            border-radius: 8px; 
            cursor: pointer;
            transition: background-color 0.3s ease; 
        }

        .login-form input[type="submit"]:hover {
            background-color: #b71c1c; 
        }

        /* Forgot Password Link */
        .login-form a {
            display: inline-block;
            margin-top: 20px; 
            color: #d32f2f; 
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease; 
        }

        .login-form a:hover {
            color: #b71c1c; 
            text-decoration: underline;
        }
        
        /* Message styling */
        .error-message {
            color: #d32f2f;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: left;
        }
        
        .success-message {
            color: #4CAF50;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: left;
        }
    </style>
</head>
<body>


    <div class="login-form">
        <h2>Red Cross</h2>
        <hr>
        <h2>Login</h2>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>

            <input type="submit" value="Login">

            <!-- Optional: Forgot Password Link -->
            <a href="#">Forgot Password?</a>
        </form>
    </div>

</body>
</html>