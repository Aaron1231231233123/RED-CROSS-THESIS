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

    // Fetch user from Supabase including role_id and is_active
    $query = "users?email=eq.$email&select=user_id,email,password_hash,role_id,is_active";
    $users = supabaseRequest($query, "GET");

    if (!empty($users)) {
        $user = $users[0]; // Fetch first matching user

        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // Check if account is active
            $is_active = isset($user['is_active']) ? (bool)$user['is_active'] : true;
            
            if (!$is_active) {
                // Account is deactivated - show modal
                $account_deactivated = true;
                $error_message = "This account has been deactivated. Please contact an administrator.";
            } else {
                // Account is active - proceed with login
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role_id'] = $user['role_id'];

                error_log("User logged in - Role ID: " . $_SESSION['role_id']); // Debug log

                // Redirect based on role
            switch ($_SESSION['role_id']) {
                case 1:
                    header("Location: Dashboards/dashboard-Inventory-System.php");
                    break;
                case 2:
                    header("Location: Dashboards/dashboard-hospital-final-request.php");
                    break;
                case 3:
                    header("Location: Dashboards/dashboard-staff-main.php");
                    break;
                default:
                    error_log("Invalid role ID: " . $_SESSION['role_id']); // Debug log
                    header("Location: hospital-request.php");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            background-color: #faf5f5;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M54 28h-8v-8h-4v8h-8v4h8v8h4v-8h8v-4zm-28 0h-8v-8h-4v8h-8v4h8v8h4v-8h8v-4z' fill='%23d32f2f' fill-opacity='0.03' fill-rule='evenodd'/%3E%3C/svg%3E");
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Main Container */
        .login-container {
            display: flex;
            background: white;
            border-radius: 24px;
            box-shadow: 0 15px 35px rgba(211, 47, 47, 0.08);
            width: 100%;
            max-width: 1000px;
            overflow: hidden;
        }

        /* Left Side - Form */
        .login-form {
            flex: 1;
            padding: 48px;
            background: white;
        }

        /* Right Side - Decoration */
        .login-decoration {
            flex: 1;
            background: linear-gradient(145deg, #e53935, #c62828);
            padding: 60px 48px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
        }

        /* Cross Symbol */
        .medical-cross {
            width: 100px;
            height: 100px;
            position: relative;
            margin-bottom: 40px;
            transform: rotate(0deg);
            transition: transform 0.3s ease;
        }

        .login-decoration:hover .medical-cross {
            transform: rotate(45deg);
        }

        .medical-cross::before,
        .medical-cross::after {
            content: '';
            position: absolute;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .medical-cross::before {
            width: 28px;
            height: 100px;
            left: 36px;
        }

        .medical-cross::after {
            height: 28px;
            width: 100px;
            top: 36px;
        }

        .login-decoration h1 {
            font-size: 32px;
            margin-top: 24px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .login-decoration p {
            margin-top: 16px;
            text-align: center;
            font-size: 16px;
            opacity: 0.95;
            letter-spacing: 0.5px;
        }

        /* Form Elements */
        .login-form h2 {
            color: #d32f2f;
            font-size: 28px;
            margin-bottom: 40px;
            text-align: center;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .form-group {
            margin-bottom: 28px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: #666;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .form-group:focus-within label {
            color: #d32f2f;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-group input:hover {
            border-color: #d1d1d1;
            background: #ffffff;
        }

        .form-group input:focus {
            border-color: #d32f2f;
            outline: none;
            box-shadow: 0 0 0 4px rgba(211, 47, 47, 0.1);
            background: #ffffff;
        }

        .form-group input::placeholder {
            color: #999;
            font-size: 14px;
        }

        /* Password Field */
        .password-field {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
            user-select: none;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: #d32f2f;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            background: #b71c1c;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(211, 47, 47, 0.2);
        }

        .submit-btn:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .forgot-password {
            display: block;
            text-align: center;
            color: #666;
            text-decoration: none;
            margin-top: 24px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .forgot-password:hover {
            color: #d32f2f;
            text-decoration: none;
        }

        /* Message Styling */
        .error-message,
        .success-message {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading State */
        .submit-btn.loading {
            background: #d32f2f;
            pointer-events: none;
        }

        .submit-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s linear infinite;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
        }

        @keyframes spin {
            to {
                transform: translateY(-50%) rotate(360deg);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
            }

            .login-decoration {
                padding: 40px 20px;
            }

            .medical-cross {
                width: 80px;
                height: 80px;
                margin-bottom: 24px;
            }

            .medical-cross::before {
                width: 20px;
                height: 80px;
                left: 30px;
            }

            .medical-cross::after {
                height: 20px;
                width: 80px;
                top: 30px;
            }

            .login-form {
                padding: 32px 24px;
            }

            .login-decoration h1 {
                font-size: 24px;
            }
        }

        /* Input Icons */
        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            display: flex;
            align-items: center;
        }

        .form-group input {
            padding-left: 46px;
        }

        /* Remember Me Checkbox */
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            user-select: none;
        }

        .remember-me input[type="checkbox"] {
            position: absolute;
            opacity: 0;
        }

        .remember-me label {
            position: relative;
            cursor: pointer;
            padding-left: 30px;
            color: #666;
            font-size: 14px;
        }

        .remember-me label:before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            border: 2px solid #e8e8e8;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .remember-me input[type="checkbox"]:checked + label:before {
            background: #d32f2f;
            border-color: #d32f2f;
        }

        .remember-me input[type="checkbox"]:checked + label:after {
            content: '';
            position: absolute;
            left: 6px;
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h2>Hospital Request Login</h2>
            
            <?php if (!empty($error_message) && !isset($account_deactivated)): ?>
                <div class="error-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($_SESSION['success_message'])): ?>
                <div class="success-message">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <?php echo $_SESSION['success_message']; ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </span>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            </span>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            <span class="password-toggle" onclick="togglePassword()">Show</span>
                        </div>
                    </div>
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">Login</button>
            </form>
        </div>
        
        <div class="login-decoration">
            <div class="medical-cross"></div>
            <h1>Hospital Request</h1>
            <p>Access your hospital account</p>
        </div>
    </div>

    <!-- Account Deactivated Modal -->
    <div class="modal fade" id="accountDeactivatedModal" tabindex="-1" aria-labelledby="accountDeactivatedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="accountDeactivatedModalLabel">
                        <i class="fas fa-user-times me-2"></i>
                        Account Deactivated
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Account Access Denied</h5>
                    <p class="mb-0">This account has been deactivated. Please contact an administrator to reactivate your account.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = 'Hide';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = 'Show';
            }
        }

        // Form validation and submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const form = this;
            
            if (form.checkValidity()) {
                submitBtn.classList.add('loading');
                submitBtn.textContent = 'Logging in...';
                
                // Save email if remember me is checked
                const rememberCheckbox = document.getElementById('remember');
                const emailInput = document.getElementById('email');
                
                if (rememberCheckbox.checked) {
                    localStorage.setItem('rememberedEmail', emailInput.value);
                } else {
                    localStorage.removeItem('rememberedEmail');
                }
            } else {
                e.preventDefault();
                form.reportValidity();
            }
        });

        // Load remembered email on page load
        window.addEventListener('load', function() {
            const rememberedEmail = localStorage.getItem('rememberedEmail');
            if (rememberedEmail) {
                document.getElementById('email').value = rememberedEmail;
                document.getElementById('remember').checked = true;
            }
            
            // Show account deactivated modal if needed
            <?php if (isset($account_deactivated) && $account_deactivated): ?>
            const modal = new bootstrap.Modal(document.getElementById('accountDeactivatedModal'));
            modal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>