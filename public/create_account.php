<?php
session_start();
require '../assets/conn/db_conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role_type = trim($_POST['role_type']); // 1 for inventory, 2 for hospital
    
    // Basic validation
    if (empty($email) || empty($password) || empty($role_type)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif (!in_array($role_type, ['1', '2'])) {
        $error_message = "Invalid account type selected.";
    } else {
        // Generate UUID for user_id
        $user_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Prepare user data
        $data = [
            'user_id' => $user_id,
            'email' => $email,
            'password_hash' => $hashed_password,
            'role_id' => intval($role_type),
            'surname' => $role_type === '2' ? 
                        (isset($_POST['physician_name']) ? "Dr. " . $_POST['physician_name'] : "") : 
                        (isset($_POST['branch_name']) ? $_POST['branch_name'] : ""),
            'first_name' => $role_type === '2' ? 
                          (isset($_POST['hospital_name']) ? $_POST['hospital_name'] : "") : 
                          "",
            'middle_name' => "",
            'suffix' => "",
            'phone_number' => "",
            'telephone_number' => "",
            'date_of_birth' => date('Y-m-d'),
            'gender' => "",
            'permanent_address' => isset($_POST['location']) ? $_POST['location'] : "",
            'office_address' => ""
        ];

        // Send request to Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/users');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error_message = "❌ Account creation failed: " . curl_error($ch);
        } else if ($http_code >= 200 && $http_code < 300) {
            $success_message = "✅ Account created successfully!";
        } else {
            $response_data = json_decode($response, true);
            error_log("Account creation error: " . $response);
            
            // Handle specific error cases
            if (isset($response_data['code'])) {
                switch ($response_data['code']) {
                    case '23505': // Unique constraint violation
                        $error_message = "❌ This email address is already registered.";
                        break;
                    default:
                        $error_message = "❌ Account creation failed. Please try again.";
                }
            } else {
                $error_message = "❌ Account creation failed. Please try again.";
            }
        }
        
        curl_close($ch);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Create New Account</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password:</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="role_type" class="form-label">Account Type:</label>
                                <select id="role_type" name="role_type" class="form-control" required>
                                    <option value="">-- Select Account Type --</option>
                                    <option value="1">[1] Inventory Manager</option>
                                    <option value="2">[2] Hospital Account</option>
                                </select>
                            </div>

                            <!-- Hospital Account Fields -->
                            <div id="hospitalFields" style="display: none;">
                                <div class="mb-3">
                                    <label for="physician_name" class="form-label">Physician Name (Surname):</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Dr.</span>
                                        <input type="text" id="physician_name" name="physician_name" class="form-control">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="hospital_name" class="form-label">Hospital Name:</label>
                                    <input type="text" id="hospital_name" name="hospital_name" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label for="location" class="form-label">Location:</label>
                                    <textarea id="location" name="location" class="form-control" rows="2"></textarea>
                                </div>
                            </div>

                            <!-- Inventory Manager Fields -->
                            <div id="inventoryFields" style="display: none;">
                                <div class="mb-3">
                                    <label for="branch_name" class="form-label">Red Cross Branch Name:</label>
                                    <input type="text" id="branch_name" name="branch_name" class="form-control">
                                </div>

                                <div class="mb-3">
                                    <label for="branch_location" class="form-label">Branch Location:</label>
                                    <textarea id="branch_location" name="location" class="form-control" rows="2"></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Create Account</button>
                        </form>

                        <script>
                            document.getElementById('role_type').addEventListener('change', function() {
                                const hospitalFields = document.getElementById('hospitalFields');
                                const inventoryFields = document.getElementById('inventoryFields');
                                
                                // Hide both sections first
                                hospitalFields.style.display = 'none';
                                inventoryFields.style.display = 'none';
                                
                                // Reset required fields
                                document.getElementById('physician_name').required = false;
                                document.getElementById('hospital_name').required = false;
                                document.getElementById('branch_name').required = false;
                                document.querySelectorAll('[name="location"]').forEach(el => el.required = false);

                                if (this.value === '2') {
                                    // Show hospital fields
                                    hospitalFields.style.display = 'block';
                                    document.getElementById('physician_name').required = true;
                                    document.getElementById('hospital_name').required = true;
                                    document.getElementById('location').required = true;
                                } else if (this.value === '1') {
                                    // Show inventory fields
                                    inventoryFields.style.display = 'block';
                                    document.getElementById('branch_name').required = true;
                                    document.getElementById('branch_location').required = true;
                                }
                            });
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 