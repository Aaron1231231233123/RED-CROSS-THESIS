<?php
// Start the session to maintain state
session_start();
require_once '../../../assets/conn/db_conn.php';

// Store the referrer URL to use it for the return button
$referrer = '';

// Only set the referrer in the session if not already set
if (!isset($_SESSION['declaration_form_referrer'])) {
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referrer = $_SERVER['HTTP_REFERER'];
    }
    if (!$referrer || !stripos($referrer, 'dashboard')) {
        if (isset($_SESSION['donor_form_referrer'])) {
            $referrer = $_SESSION['donor_form_referrer'];
        }
    }
    if (!$referrer) {
        $referrer = '../../public/Dashboards/dashboard-Inventory-System.php';
    }
    $_SESSION['declaration_form_referrer'] = $referrer;
}
$referrer = $_SESSION['declaration_form_referrer'];

// Log all potential donor_id sources for debugging
error_log("Declaration form - donor_id sources: SESSION=" . ($_SESSION['donor_id'] ?? 'not set') . 
          ", GET=" . ($_GET['donor_id'] ?? 'not set') . 
          ", POST=" . ($_POST['donor_id'] ?? 'not set'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../public/login.php");
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// Check if we have donor_id in GET or POST parameters and set it in SESSION
if (isset($_GET['donor_id'])) {
    $_SESSION['donor_id'] = $_GET['donor_id'];
    error_log("Declaration form - Setting donor_id from GET: " . $_GET['donor_id']);
} elseif (isset($_POST['donor_id'])) {
    $_SESSION['donor_id'] = $_POST['donor_id'];
    error_log("Declaration form - Setting donor_id from POST: " . $_POST['donor_id']);
}

// Now check if we have donor_id in session
if (!isset($_SESSION['donor_id'])) {
    error_log("Missing donor_id in session for declaration form");
    header('Location: ../../../public/Dashboards/dashboard-Inventory-System.php?error=' . urlencode('Missing donor ID'));
    exit();
}

// Check if medical history is completed
// If medical_history_id is in GET/POST, save it to session
if (isset($_GET['medical_history_id'])) {
    $_SESSION['medical_history_id'] = $_GET['medical_history_id'];
} elseif (isset($_POST['medical_history_id'])) {
    $_SESSION['medical_history_id'] = $_POST['medical_history_id'];
}

// Now check if we have medical_history_id in session
if (!isset($_SESSION['medical_history_id'])) {
    error_log("Missing medical_history_id in session for declaration form");
    
    // Try to retrieve medical_history_id from database using donor_id
    $donor_id = $_SESSION['donor_id'];
    
    // Build the URL for retrieving medical history
    $url = SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . urlencode($donor_id);
    
    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Process the response
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (is_array($data) && !empty($data) && isset($data[0]['medical_history_id'])) {
            $_SESSION['medical_history_id'] = $data[0]['medical_history_id'];
            error_log("Retrieved medical_history_id from database: " . $_SESSION['medical_history_id']);
        } else {
            // Redirect to medical history form with donor_id
            error_log("No medical_history_id found in database - redirecting to medical history form");
            header('Location: medical-history-modal.php?donor_id=' . $donor_id);
            exit();
        }
    } else {
        // Redirect to medical history form with donor_id
        error_log("Error retrieving medical_history_id (HTTP $http_code) - redirecting to medical history form");
        header('Location: medical-history-modal.php?donor_id=' . $donor_id);
        exit();
    }
}

// Fetch donor information
$donor_id = $_SESSION['donor_id'];
$donorData = null;

try {
    // Log the donor ID we're fetching
    error_log("Declaration form - Fetching donor data for ID: " . $donor_id);
    
    $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . $donor_id);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    error_log("Declaration form - Donor fetch response: HTTP $http_code, Data: " . substr($response, 0, 500));
    
    if ($http_code === 200) {
        $donorData = json_decode($response, true);
        if (!is_array($donorData) || empty($donorData)) {
            error_log("Donor not found with ID: " . $donor_id);
            
            // If we have donor_form_data, we need to insert it now
            if (isset($_SESSION['donor_form_data'])) {
                error_log("Found donor_form_data in session. Inserting donor record.");
                
                // Prepare the data for insertion
                $formData = $_SESSION['donor_form_data'];
                
                // Log what we're about to insert
                error_log("Inserting donor data: " . json_encode($formData));
                
                // Initialize cURL for insertion
                $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form');
                
                // Set cURL options
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($formData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ]);
                
                // Execute the request
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                curl_close($ch);
                
                if ($http_code >= 200 && $http_code < 300) {
                    // Successfully created donor record
                    $insertedData = json_decode($response, true);
                    
                    if (is_array($insertedData) && !empty($insertedData)) {
                        // Update donor_id in session with the new one
                        $_SESSION['donor_id'] = $insertedData[0]['donor_id'];
                        $donor_id = $_SESSION['donor_id'];
                        
                        // Use the inserted data
                        $donorData = $insertedData;
                        error_log("Successfully inserted donor record with ID: " . $donor_id);
                        
                        // Clear the form data from session
                        unset($_SESSION['donor_form_data']);
                        unset($_SESSION['donor_form_timestamp']);
                    } else {
                        throw new Exception("Failed to parse inserted donor data.");
                    }
                } else {
                    throw new Exception("Failed to insert donor data. HTTP Code: " . $http_code);
                }
            } else {
                // No donor data available - redirect to dashboard
                error_log("No donor form data available. Redirecting to dashboard.");
                $_SESSION['error_message'] = "Donor data not found. Please try registering again.";
                header('Location: ' . $referrer);
                exit();
            }
        }
        $donorData = $donorData[0]; // Get the first (and should be only) result
        
        // Debug the donor data we retrieved
        error_log("Declaration form - Retrieved donor data: " . json_encode($donorData));
    } else {
        throw new Exception("Failed to fetch donor data. HTTP Code: " . $http_code);
    }
} catch (Exception $e) {
    error_log("Error fetching donor data: " . $e->getMessage());
    $_SESSION['error_message'] = "Error retrieving donor information. Please try again.";
    header('Location: ' . $referrer);
    exit();
}

// Handle form submission - just print the declaration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['print_declaration'])) {
    // Store that we've completed the declaration form
    $_SESSION['declaration_completed'] = true;
    
    // Get the referrer URL for redirection
    $redirect_url = isset($_SESSION['declaration_form_referrer']) ? 
                    $_SESSION['declaration_form_referrer'] : 
                    '../../public/Dashboards/dashboard-Inventory-System.php';
    
    // Add a success parameter to show a success message on the dashboard
    if (strpos($redirect_url, '?') !== false) {
        // URL already has parameters
        $redirect_url .= '&donor_registered=true';
    } else {
        // URL has no parameters yet
        $redirect_url .= '?donor_registered=true';
    }
    
    // Log successful registration
    error_log("Donor registration completed successfully for donor ID: " . $donor_id);
    
    // Set a flag for registered donor in session
    $_SESSION['donor_registered'] = true;
    $_SESSION['donor_registered_id'] = $donor_id;
    $_SESSION['donor_registered_name'] = $donorData['first_name'] . ' ' . $donorData['surname'];
    error_log("Declaration form - Donor registration completed for: " . $_SESSION['donor_registered_name'] . " (ID: " . $donor_id . ")");
    
    // Now create a temporary cookie to identify this registration success
    // We'll use this instead of session data to show success message
    $expiry = time() + 60*5; // 5 minutes
    setcookie('donor_registered', 'true', $expiry, '/');
    setcookie('donor_name', $donorData['first_name'] . ' ' . $donorData['surname'], $expiry, '/');
    
    // Clear any previous registration data from session to avoid conflicts
    unset($_SESSION['donor_form_data']);
    unset($_SESSION['donor_form_timestamp']);
    unset($_SESSION['donor_id']);
    unset($_SESSION['medical_history_id']);
    unset($_SESSION['screening_id']);
    
    // Redirect back to the dashboard
    header('Location: ' . $redirect_url);
    exit();
}

// Calculate age from birthdate if not in donor data
if (!isset($donorData['age']) && isset($donorData['birthdate'])) {
    $birthdate = new DateTime($donorData['birthdate']);
    $today = new DateTime();
    $donorData['age'] = $birthdate->diff($today)->y;
}

// Format today's date
$today = date('F d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Declaration Form</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        
        .declaration-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .declaration-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .declaration-header h2 {
            color: #b22222;
            font-weight: bold;
        }
        
        .declaration-content {
            text-align: justify;
            line-height: 1.6;
        }
        
        .declaration-content p {
            margin-bottom: 15px;
        }
        
        .bold {
            font-weight: bold;
            color: #b22222;
        }
        
        .donor-info {
            background-color: #f8f8f8;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .donor-info-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 10px;
            gap: 20px;
        }
        
        .donor-info-item {
            flex: 1;
            min-width: 200px;
        }
        
        .donor-info-label {
            font-weight: bold;
            font-size: 14px;
            color: #777;
        }
        
        .donor-info-value {
            font-size: 16px;
        }
        
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            border-top: 1px solid #000;
            width: 200px;
            text-align: center;
            padding-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            
            .declaration-container {
                box-shadow: none;
                padding: 0;
            }
            
            .action-buttons {
                display: none;
            }
            
            .btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="declaration-container">
        <div class="declaration-header">
            <h2>PHILIPPINE RED CROSS</h2>
            <h3>BLOOD DONOR DECLARATION FORM</h3>
        </div>
        
        <div class="donor-info">
            <div class="donor-info-row">
                <div class="donor-info-item">
                    <div class="donor-info-label">Donor Name:</div>
                    <div class="donor-info-value">
                        <?php 
                        // Create full name from components with proper validation
                        $surname = isset($donorData['surname']) ? htmlspecialchars(trim($donorData['surname'])) : '';
                        $firstName = isset($donorData['first_name']) ? htmlspecialchars(trim($donorData['first_name'])) : '';
                        $middleName = isset($donorData['middle_name']) ? htmlspecialchars(trim($donorData['middle_name'])) : '';
                        
                        // Build the full name with proper formatting
                        $fullName = $surname;
                        if (!empty($firstName)) {
                            $fullName .= ', ' . $firstName;
                        }
                        if (!empty($middleName)) {
                            $fullName .= ' ' . $middleName;
                        }
                        
                        echo $fullName;
                        ?>
                    </div>
                </div>
                <div class="donor-info-item">
                    <div class="donor-info-label">Age:</div>
                    <div class="donor-info-value"><?php echo isset($donorData['age']) ? htmlspecialchars($donorData['age']) : ''; ?></div>
                </div>
                <div class="donor-info-item">
                    <div class="donor-info-label">Sex:</div>
                    <div class="donor-info-value"><?php echo isset($donorData['sex']) ? htmlspecialchars($donorData['sex']) : ''; ?></div>
                </div>
            </div>
            <div class="donor-info-row">
                <div class="donor-info-item">
                    <div class="donor-info-label">Address:</div>
                    <div class="donor-info-value"><?php echo isset($donorData['permanent_address']) ? htmlspecialchars($donorData['permanent_address']) : ''; ?></div>
                </div>
            </div>
        </div>
        
        <div class="declaration-content">
            <p>I hereby voluntarily donate my blood to the Philippine Red Cross, which is authorized to withdraw my blood and utilize it in any way they deem advisable. I understand this is a voluntary donation and I will receive no payment.</p>
            
            <p>I have been <span class="bold">properly advised</span> on the blood donation procedure, including possible discomfort (needle insertion) and risks (temporary dizziness, fainting, bruising, or rarely, infection at the needle puncture site).</p>
            
            <p>I confirm that I have given <span class="bold">truthful answers</span> to all questions during the medical interview and donor screening process. I understand the significance of providing accurate information for my safety and the safety of potential recipients.</p>
            
            <p>I understand that my blood will be <span class="bold">tested for infectious diseases</span> including HIV, Hepatitis B, Hepatitis C, Syphilis, and Malaria. I consent to these tests being performed, and if any test is reactive, my blood donation will be discarded and I will be notified.</p>
            
            <p>I <span class="bold">authorize the Philippine Red Cross</span> to contact me for notification of any test results requiring medical attention and for future blood donation campaigns.</p>
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                Donor's Signature
            </div>
            <div class="signature-box">
                Date: <?php echo $today; ?>
            </div>
        </div>
        
        <div class="action-buttons">
            <button type="button" class="btn btn-secondary" onclick="returnToDashboard()">
                <i class="fas fa-arrow-left"></i> Return to Dashboard
            </button>
            
            <div>
                <button type="button" class="btn btn-danger me-2" onclick="printDeclaration()">
                    <i class="fas fa-print"></i> Print Declaration
                </button>
                
                <form method="post" class="d-inline">
                    <button type="submit" name="print_declaration" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Complete and Return
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printDeclaration() {
            window.print();
        }
        
        function returnToDashboard() {
            // Clean up session data
            fetch('cancel_registration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'cancel_from_declaration' })
            })
            .then(response => response.json())
            .then(data => {
                // Get the referrer URL for redirection
                let redirect_url = '<?php echo $referrer; ?>';
                window.location.href = redirect_url;
            })
            .catch(error => {
                console.error('Error cleaning up session:', error);
                // Fallback redirect
                let redirect_url = '<?php echo $referrer; ?>';
                window.location.href = redirect_url;
            });
        }
    </script>
</body>
</html> 