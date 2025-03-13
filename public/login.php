<?php
// Start the session
session_start();

// Include the Supabase configuration file
require_once '../src/config/database.php';

// Initialize variables for error messages
$error_message = '';
$success_message = '';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the submitted email and password
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Validate input (basic validation)
    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Attempt to authenticate the user
        $auth_result = authenticateUser($email, $password);
        
        if ($auth_result['success']) {
            // Authentication successful
            
            // Store user information in session
            $_SESSION['user_id'] = $auth_result['user']['id'];
            $_SESSION['user_email'] = $auth_result['user']['email'];
            $_SESSION['access_token'] = $auth_result['access_token'];
            
            // Set success message
            $success_message = "Login successful! Redirecting...";
            
            // Redirect to dashboard (you can change this to your desired page)
            header("Refresh: 2; URL=inventory-dashboard.php");
        } else {
            // Authentication failed
            $error_message = $auth_result['message'];
        }
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
        
        <form method="POST" action="">
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