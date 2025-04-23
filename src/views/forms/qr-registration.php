<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Create logs directory if it doesn't exist
$logsDir = '../../../assets/logs';
if (!file_exists($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Define the CSV file path
$csvFilePath = $logsDir . '/qr_code.csv';

// Create CSV file with headers if it doesn't exist
if (!file_exists($csvFilePath)) {
    $file = fopen($csvFilePath, 'w');
    fputcsv($file, ['reference_id', 'qr_code_image', 'date']);
    fclose($file);
}

// Process QR code reference saving
if(isset($_POST['action']) && $_POST['action'] == 'saveQRReference') {
    $referenceId = $_POST['referenceId'];
    $qrCodeImage = $_POST['qrCodeImage'];
    $date = date('Y-m-d H:i:s');
    
    // Append to CSV file
    $file = fopen($csvFilePath, 'a');
    fputcsv($file, [$referenceId, $qrCodeImage, $date]);
    fclose($file);
    
    echo json_encode(['success' => true]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Registration - Red Cross</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #9c0000;
            --secondary-color: #ff1a1a;
            --accent-color: #fff3f3;
            --text-color: #333333;
            --light-gray: #f5f5f5;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f5f5 0%, #ffffff 100%);
            color: var(--text-color);
            height: 100vh;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .page-container {
            width: 100%;
            max-width: 1200px;
            height: 100vh;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .header-section {
            text-align: center;
            padding: 1rem 0;
        }

        .logo-container {
            margin-bottom: 1rem;
        }

        .red-cross-logo {
            width: 80px;
            height: auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .main-title {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .subtitle {
            color: var(--text-color);
            font-size: 1rem;
            opacity: 0.8;
        }

        .content-grid {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin: 1rem 0;
            height: calc(100vh - 250px);
        }

        .qr-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 400px;
        }

        .qr-container {
            background: var(--white);
            border: 2px solid var(--primary-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem auto;
            width: 280px;
            height: 280px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .qr-placeholder {
            width: 230px;
            height: 230px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 10px;
        }

        .qr-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .generate-btn {
            background: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 2px 4px rgba(156, 0, 0, 0.2);
        }

        .generate-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .instructions-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
            width: 400px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .instructions-title {
            color: var(--primary-color);
            font-size: 1.25rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent-color);
        }

        .steps-list {
            list-style: none;
            counter-reset: steps;
        }

        .steps-list li {
            position: relative;
            padding: 0.75rem 0.75rem 0.75rem 2.5rem;
            margin-bottom: 0.5rem;
            background: var(--accent-color);
            border-radius: 8px;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .steps-list li:hover {
            transform: translateX(5px);
            background: #ffe6e6;
            cursor: pointer;
        }

        .steps-list li::before {
            counter-increment: steps;
            content: counter(steps);
            position: absolute;
            left: 0.75rem;
            color: var(--primary-color);
            font-weight: 600;
            width: 20px;
            height: 20px;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .footer {
            text-align: center;
            padding: 1rem;
            color: var(--text-color);
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .footer p {
            margin: 0.25rem 0;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading .generate-btn::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid var(--white);
            border-top: 2px solid transparent;
            border-radius: 50%;
            display: inline-block;
            margin-left: 8px;
            animation: spin 1s linear infinite;
            vertical-align: middle;
        }

        @media (max-width: 1024px) {
            .page-container {
                padding: 1rem;
            }
            
            .content-grid {
                gap: 1.5rem;
            }
        }

        @media (max-width: 850px) {
            .content-grid {
                flex-direction: column;
                align-items: center;
                height: auto;
            }

            .qr-section, .instructions-section {
                width: 100%;
                max-width: 400px;
            }

            body {
                height: auto;
                overflow-y: auto;
            }

            .page-container {
                height: auto;
                min-height: 100vh;
            }
        }

        @media (max-width: 480px) {
            .main-title {
                font-size: 1.5rem;
            }

            .qr-container {
                width: 240px;
                height: 240px;
            }

            .qr-placeholder {
                width: 200px;
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <header class="header-section">
            <div class="logo-container">
                <img src="../../../assets/image/PRC_Logo.png" alt="Red Cross Logo" class="red-cross-logo">
            </div>
            <h1 class="main-title">Donor Registration QR Code</h1>
            <p class="subtitle">Scan to begin your journey as a blood donor</p>
        </header>

        <main class="content-grid">
            <section class="qr-section">
                <div class="qr-container">
                    <div class="qr-placeholder">
                        <img src="../../../assets/image/qr-code-temp.png" alt="QR Code">
                    </div>
                </div>
                <button class="generate-btn" onclick="generateNewQR()">
                    Generate New QR Code
                </button>
                <div id="error-message" style="color: var(--primary-color); margin-top: 10px; display: none;"></div>
                <div id="success-message" style="color: green; margin-top: 10px; display: none;">QR Code generated successfully!</div>
                <a id="download-link" href="#" style="display: none; margin-top: 15px; color: var(--primary-color); text-decoration: underline;">Download QR Code</a>
            </section>

            <section class="instructions-section">
                <h2 class="instructions-title">How to Register</h2>
                <ul class="steps-list">
                    <li>Open your phone's camera or QR scanner app</li>
                    <li>Point your camera at the QR code to scan</li>
                    <li>Will redirect to the registration form</li>
                    <li>Complete the registration form with accurate information</li>
                    <li>Submit and wait for your name to be called for verification</li>
                </ul>
            </section>
        </main>

        <footer class="footer">
            <p>Philippine Red Cross - Blood Services</p>
            <p>For assistance, please contact our staff</p>
        </footer>
    </div>

    <script>
        /**
         * Generates a new QR code for donor registration
         * 
         * Process:
         * 1. Creates a unique ID for tracking
         * 2. Generates a QR code using goQR.me API pointing to donor-form-modal.php
         * 3. Saves the reference ID and QR code URL to a CSV file for logging
         * 4. Displays the QR code and provides a download option
         */
        function generateNewQR() {
            const button = document.querySelector('.generate-btn');
            const qrPlaceholder = document.querySelector('.qr-placeholder');
            const errorMessage = document.getElementById('error-message');
            const successMessage = document.getElementById('success-message');
            const downloadLink = document.getElementById('download-link');
            
            // Reset any previous state
            errorMessage.style.display = 'none';
            successMessage.style.display = 'none';
            downloadLink.style.display = 'none';
            button.parentElement.classList.add('loading');
            
            // Generate a unique identifier for this QR code
            const uniqueId = Date.now().toString(36) + Math.random().toString(36).substring(2);
            
            // Create registration URL - this should point to your registration form
            const registrationUrl = window.location.origin + "/REDCROSS/src/views/forms/donor-form-modal.php";
            
            // Generate QR code using goQR.me API
            const qrApiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=230x230&data=${encodeURIComponent(registrationUrl)}`;
            
            // Update the QR code image
            const img = new Image();
            img.alt = "Registration QR Code";
            img.onload = function() {
                qrPlaceholder.innerHTML = '';
                qrPlaceholder.appendChild(img);
                button.parentElement.classList.remove('loading');
                
                // Show success message
                successMessage.style.display = 'block';
                
                // Show download link
                downloadLink.href = qrApiUrl;
                downloadLink.download = `donor-registration-qr-${uniqueId}.png`;
                downloadLink.textContent = "Download QR Code";
                downloadLink.style.display = 'block';
                
                // Save the QR reference ID to the database
                saveQRReference(uniqueId, qrApiUrl);
            };
            
            img.onerror = function() {
                button.parentElement.classList.remove('loading');
                errorMessage.textContent = "Failed to generate QR code. Please try again.";
                errorMessage.style.display = 'block';
            };
            
            img.src = qrApiUrl;
            
            // If loading fails or takes too long
            setTimeout(() => {
                if (button.parentElement.classList.contains('loading')) {
                    button.parentElement.classList.remove('loading');
                    errorMessage.textContent = "QR code generation timed out. Please try again.";
                    errorMessage.style.display = 'block';
                }
            }, 5000);
        }
        
        function saveQRReference(referenceId, qrCodeImage) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=saveQRReference&referenceId=${referenceId}&qrCodeImage=${encodeURIComponent(qrCodeImage)}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Error saving QR reference:', data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html> 