<?php
session_start(); // Start session to store user data

// Supabase API credentials
$api_url = "https://cjxxpajcelpqkbcvixmk.supabase.co/auth/v1/token?grant_type=password";
$api_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImNqeHhwYWpjZWxwcWtiY3ZpeG1rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDI4MTI5NzMsImV4cCI6MjA1ODM4ODk3M30.FlVNNjl5MK8pFBvVJup2FESsoS7lqH4kcTNldDDgAjM";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    // Prepare the JSON payload for authentication
    $data = json_encode([
        "email" => $email,
        "password" => $password
    ]);

    // Initialize cURL request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "apikey: $api_key",
        "Authorization: Bearer $api_key"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    // Check if authentication was successful
    if (isset($result["access_token"])) {
        $_SESSION["user_token"] = $result["access_token"]; // Store token in session
        $_SESSION["user_id"] = $result["user"]["id"]; // Store user ID

        // Fetch user details from database
        $user_details = getUserDetails($_SESSION["user_id"], $_SESSION["user_token"], $api_key);

        if ($user_details) {
            $_SESSION["full_name"] = $user_details["full_name"];
            $_SESSION["role_id"] = $user_details["role_id"] ?? 0; // Ensure role_id is set

            // Redirect based on role
            switch ($_SESSION["role_id"]) {
                case 1:
                    header("Location: staff-volunteer-dashboard.php");
                    exit;
                case 2:
                    header("Location: hospital_dashboard.php");
                    exit;
                case 3:
                    header("Location: admin_dashboard.php");
                    exit;
                default:
                    echo "Invalid role!";
                    exit;
            }
        } else {
            echo "User role not found! Please contact support.";
        }
    } else {
        // Display login error
        $error_message = isset($result["error_description"]) ? $result["error_description"] : "Invalid email or password.";
        echo "Login failed: " . $error_message;
    }
}

/**
 * Fetch user details from the database
 */
function getUserDetails($user_id, $token, $api_key) {
    $user_url = "https://cjxxpajcelpqkbcvixmk.supabase.co/rest/v1/user_details?user_id=eq.$user_id";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $user_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "apikey: $api_key",
        "Authorization: Bearer " . $token
    ]);

    $user_response = curl_exec($ch);
    curl_close($ch);
    $user_data = json_decode($user_response, true);

    if (empty($user_data)) {
        return null; // Return null if no user details found
    }

    return $user_data[0] ?? null;
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