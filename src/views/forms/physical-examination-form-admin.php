<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Admin-specific physical examination form
// This form is specifically designed for admin workflows

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../public/login.php");
    exit();
}

// Check for admin role only
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    error_log("Admin Physical Examination Form - Invalid role_id: " . ($_SESSION['role_id'] ?? 'not set'));
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// Get donor_id from POST or session
$donor_id = $_POST['donor_id'] ?? $_SESSION['donor_id'] ?? null;

if (!$donor_id) {
    error_log("Admin Physical Examination Form - Missing donor_id");
    header("Location: ../../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?error=missing_donor_id");
    exit();
}

// Store donor_id in session for admin workflow
$_SESSION['donor_id'] = $donor_id;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_physical_exam'])) {
    try {
        // Prepare data for submission
        $data = [
            'donor_id' => intval($donor_id),
            'blood_pressure' => strval($_POST['blood_pressure']),
            'pulse_rate' => intval($_POST['pulse_rate']),
            'body_temp' => number_format(floatval($_POST['body_temp']), 1),
            'gen_appearance' => strval(trim($_POST['gen_appearance'])),
            'skin' => strval(trim($_POST['skin'])),
            'heent' => strval(trim($_POST['heent'])),
            'heart_and_lungs' => strval(trim($_POST['heart_and_lungs'])),
            'remarks' => 'Accepted', // Admin always accepts
            'blood_bag_type' => 'Single' // Default for admin
        ];

        // Submit to admin handler
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, '../../../src/handlers/physical-examination-handler-admin.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                // Redirect to blood collection
                header('Location: ../../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php?physical_exam_completed=1&donor_id=' . $donor_id);
                exit();
            } else {
                throw new Exception($result['message'] ?? 'Unknown error occurred');
            }
        } else {
            throw new Exception("Failed to submit physical examination");
        }

    } catch (Exception $e) {
        error_log("Admin Physical Examination Form - Error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Physical Examination Form - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .physical-examination {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .form-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        .form-header h2 {
            margin: 0;
            font-weight: 600;
        }
        .physical-examination-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .physical-examination-table th {
            background: #b22222;
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            border: none;
        }
        .physical-examination-table td {
            padding: 15px;
            border: 1px solid #e9ecef;
            text-align: center;
        }
        .physical-examination-table input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        .physical-examination-table input:focus {
            border-color: #b22222;
            box-shadow: 0 0 0 0.2rem rgba(178, 34, 34, 0.25);
            outline: none;
        }
        .remarks-section {
            margin-top: 30px;
        }
        .remarks-section textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(178, 34, 34, 0.3);
        }
        .alert {
            border-radius: 6px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="physical-examination">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="form-header">
            <h2><i class="fas fa-stethoscope me-2"></i>Physical Examination Form - Admin</h2>
            <p class="mb-0">Donor ID: <?php echo htmlspecialchars($donor_id); ?></p>
        </div>
        
        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <input type="hidden" name="donor_id" value="<?php echo htmlspecialchars($donor_id); ?>">
            
            <h4>V. PHYSICAL EXAMINATION</h4>
            
            <table class="physical-examination-table">
                <thead>
                    <tr>
                        <th>Blood Pressure</th>
                        <th>Pulse Rate</th>
                        <th>Body Temp.</th>
                        <th>Gen. Appearance</th>
                        <th>Skin</th>
                        <th>HEENT</th>
                        <th>Heart and Lungs</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" name="blood_pressure" placeholder="e.g., 120/80" pattern="[0-9]{2,3}/[0-9]{2,3}" title="Format: systolic/diastolic e.g. 120/80" required>
                        </td>
                        <td>
                            <input type="number" name="pulse_rate" placeholder="BPM" min="40" max="200" required>
                        </td>
                        <td>
                            <input type="number" name="body_temp" placeholder="°C" step="0.1" min="35" max="42" required>
                        </td>
                        <td><input type="text" name="gen_appearance" placeholder="Enter observation" required></td>
                        <td><input type="text" name="skin" placeholder="Enter observation" required></td>
                        <td><input type="text" name="heent" placeholder="Enter observation" required></td>
                        <td><input type="text" name="heart_and_lungs" placeholder="Enter observation" required></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="remarks-section">
                <label for="remarks" class="form-label"><strong>Remarks:</strong></label>
                <textarea name="remarks" id="remarks" placeholder="Enter any additional observations or notes..." readonly>Accepted for Blood Collection</textarea>
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" name="submit_physical_exam" class="btn btn-submit">
                    <i class="fas fa-check me-2"></i>Complete Physical Examination
                </button>
                <a href="../../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
