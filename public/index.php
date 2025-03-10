<?php
// Start the session (optional, useful if you want to store user data later)
session_start();

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the submitted email and password
    $email = $_POST['email'];
    $password = $_POST['password'];

    // For now, we'll just print the submitted data (no validation or connection yet)
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Password: " . htmlspecialchars($password) . "<br>";

    // TODO: Add your authentication logic here (e.g., connect to Supabase or a database)
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
            background-color: #f9f9f9; /* Light gray background */
            display: flex;
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
            height: 100vh;
            margin: 0;
        }

        /* Form Container */
        .login-form {
            background-color: #ffffff; /* White background for the form */
            padding: 40px 30px; /* Increased padding for better spacing */
            border-radius: 12px; /* Slightly rounded corners */
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
            width: 100%;
            max-width: 400px; /* Fixed max-width for consistency */
            text-align: center; /* Center-align headings and buttons */
        }

        /* Logo and Title */
        .login-form h2:first-of-type {
            color: #d32f2f; /* Red Cross red */
            font-size: 24px; /* Larger font size for branding */
            margin-bottom: 10px; /* Reduced spacing between logo and subtitle */
        }

        .login-form hr {
            border: 0;
            height: 2px;
            background-color: #d32f2f; /* Red Cross red */
            margin: 10px auto 20px; /* Spacing above and below the line */
            width: 50px; /* Short horizontal line */
        }

        .login-form h2:last-of-type {
            color: #333333; /* Dark gray for the login title */
            font-size: 20px; /* Slightly smaller than the logo */
            margin-bottom: 25px; /* Space before the form fields */
        }

        /* Labels */
        .login-form label {
            color: #555555; /* Medium gray for labels */
            font-weight: bold;
            display: block; /* Ensure labels are stacked above inputs */
            margin-bottom: 8px; /* Space between label and input */
            text-align: left; /* Align labels to the left */
        }

        /* Input Fields */
        .login-form input[type="email"],
        .login-form input[type="password"] {
            width: 100%; /* Full width of the form */
            padding: 14px; /* Increased padding for better touch targets */
            margin-bottom: 15px; /* Space between inputs */
            border: 1px solid #d32f2f; /* Red Cross red border */
            border-radius: 8px; /* Rounded corners */
            font-size: 16px; /* Consistent font size */
            transition: border-color 0.3s ease; /* Smooth border color change */
        }

        .login-form input[type="email"]:focus,
        .login-form input[type="password"]:focus {
            border-color: #b71c1c; /* Darker red on focus */
            outline: none; /* Remove default focus outline */
        }

        /* Submit Button */
        .login-form input[type="submit"] {
            width: 100%; /* Full width of the form */
            padding: 14px; /* Match input field padding */
            background-color: #d32f2f; /* Red Cross red */
            border: none;
            color: white;
            font-size: 18px; /* Larger font size for emphasis */
            font-weight: bold;
            border-radius: 8px; /* Rounded corners */
            cursor: pointer;
            transition: background-color 0.3s ease; /* Smooth hover effect */
        }

        .login-form input[type="submit"]:hover {
            background-color: #b71c1c; /* Darker red on hover */
        }

        /* Forgot Password Link */
        .login-form a {
            display: inline-block;
            margin-top: 20px; /* Space above the link */
            color: #d32f2f; /* Red Cross red */
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease; /* Smooth hover effect */
        }

        .login-form a:hover {
            color: #b71c1c; /* Darker red on hover */
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-form">
        <h2>Red Cross</h2>
        <hr>
        <h2>Login</h2>
        <form method="POST" action="">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>

            <input type="submit" value="Login">

            <!-- Optional: Forgot Password Link -->
            <a href="#">Forgot Password?</a>
        </form>
    </div>

</body>
</html>