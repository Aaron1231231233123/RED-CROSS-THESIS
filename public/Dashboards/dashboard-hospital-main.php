<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/check_account_hospital_modal.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Signature Pad Library -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
    <style>
        /* General Body Styling */
        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        /* Red Cross Theme Colors */
        :root {
            --redcross-red: #941022;
            --redcross-dark: #7a0c1c;
            --redcross-light-red: #b31b2c;
            --redcross-gray: #6c757d;
            --redcross-light: #f8f9fa;
        }

        /* Modal Styling */
        .modal-content {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .modal-header {
            background-color: var(--redcross-red);
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 1.5rem;
        }

        .modal-header .btn-close {
            color: white;
            filter: brightness(0) invert(1);
        }

        .modal-body {
            padding: 2rem;
        }

        /* Section Headers */
        .modal-body h6.fw-bold {
            color: var(--redcross-red);
            font-size: 1.1rem;
            border-bottom: 2px solid var(--redcross-red);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }

        /* Form Controls */
        .form-control:focus, .form-select:focus {
            border-color: var(--redcross-red);
            box-shadow: 0 0 0 0.2rem rgba(148, 16, 34, 0.25);
        }

        /* Custom Radio Buttons */
        .form-check-input:checked {
            background-color: var(--redcross-red);
            border-color: var(--redcross-red);
        }

        /* Signature Pad */
        #signaturePad .border {
            border-color: var(--redcross-red) !important;
        }

        /* Submit Button */
        .btn-danger {
            background-color: var(--redcross-red);
            border-color: var(--redcross-red);
            color: white;
        }

        .btn-danger:hover {
            background-color: var(--redcross-dark);
            border-color: var(--redcross-dark);
            color: white;
        }

        /* Secondary Button */
        .btn-secondary {
            background-color: var(--redcross-gray);
            border-color: var(--redcross-gray);
        }

        /* Required Fields */
        .form-label::after {
            content: "*";
            color: var(--redcross-red);
            margin-left: 4px;
        }

        /* File Upload */
        .form-control[type="file"] {
            border-color: #dee2e6;
        }

        .form-control[type="file"]:hover {
            border-color: var(--redcross-red);
        }

        /* Small Text */
        .text-muted {
            color: var(--redcross-gray) !important;
        }

        /* Canvas Border */
        #physicianSignaturePad {
            border: 2px solid var(--redcross-red) !important;
            border-radius: 5px;
        }

        /* Signature Controls */
        .signature-controls {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        /* Read-only Inputs */
        input[readonly] {
            background-color: var(--redcross-light);
            border: 1px solid #dee2e6;
        }
        /* Reduce Left Margin for Main Content */
        main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
            margin-left: 280px !important;
        }
        /* Header */
        .dashboard-home-header {
            position: fixed;
            top: 0;
            left: 280px;
            width: calc(100% - 280px);
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            z-index: 1000;
            transition: left 0.3s ease, width 0.3s ease;
        }
        /* Sidebar Styling */
        .dashboard-home-sidebar {
            height: 100vh;
            overflow-y: auto;
            position: fixed;
            width: 280px;
            background-color: #ffffff;
            border-right: 1px solid #ddd;
            padding: 20px;
            transition: width 0.3s ease;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .dashboard-home-sidebar .nav-link {
            color: #333;
            padding: 12px 15px;
            margin: 4px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .dashboard-home-sidebar .nav-link:hover {
            background-color: #e9ecef;
            color: #333;
            transform: translateX(5px);
        }
        .dashboard-home-sidebar .nav-link.active {
            background-color: #941022;
            color: white;
        }
        .dashboard-home-sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }
        /* Search Box Styling */
        .search-box .input-group {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .search-box .input-group-text {
            border-right: none;
        }
        .search-box .form-control {
            border-left: none;
        }
        .search-box .form-control:focus {
            box-shadow: none;
            border-color: #dee2e6;
        }
        .text-danger {
            color: var(--redcross-red) !important;
            font-weight: bold;
        }
        /* Logo and Title Styling */
        .dashboard-home-sidebar img {
            transition: transform 0.3s ease;
        }
        .dashboard-home-sidebar img:hover {
            transform: scale(1.05);
        }
        .dashboard-home-sidebar h5 {
            font-weight: 600;
        }
        /* Scrollbar Styling */
        .dashboard-home-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .dashboard-home-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .dashboard-home-sidebar::-webkit-scrollbar-thumb {
            background: #941022;
            border-radius: 3px;
        }
        .dashboard-home-sidebar::-webkit-scrollbar-thumb:hover {
            background: #7a0c1c;
        }
        /* Main Content Styling */
        .dashboard-home-main {
            margin-left: 280px;
            margin-top: 70px;
            min-height: 100vh;
            overflow-x: hidden;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .custom-margin {
            margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin-top: 100px;
}
        .chart-container {
            width: 100%;
            height: 400px;
        }
        #scheduleDateTime {
    opacity: 0;
    transition: opacity 0.5s ease-in-out;
}
.card-title{
    color: var(--redcross-red) !important;
            font-weight: bold;
}
        .sticky-alerts {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            width: 350px;
        }
        .blood-alert {
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        /* Loading Spinner - Minimal Addition */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }
        
        .btn-loading .spinner-border {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 1rem;
            height: 1rem;
            display: inline-block;
        }
        
        .btn-loading .btn-text {
            opacity: 0;
        }
    </style>
    <!-- Add this before the closing </head> tag -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
            <h4 >Hospital Request Dashboard</h4>
            <!-- Request Blood Button -->
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#bloodRequestModal">Request Blood</button>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        <h3 class="text-danger mb-0"><?php echo $_SESSION['user_first_name']; ?></h3>
                        <small class="text-muted">Hospital Request Dashboard</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="fas fa-home me-2"></i>Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-hospital-requests.php">
                                <i class="fas fa-tint me-2"></i>Your Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-hospital-history.php">
                                <i class="fas fa-history me-2"></i>Request History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../../assets/php_func/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    
                    <div class="container-fluid p-4 custom-margin">
                    <h3 class="card-title mb-3">Progress Tracker</h3>
                    <div class="modal-body mt-4">

                    <!-- Progress Tracker -->
                    <div class="progress-tracker">
                        <div class="progress-steps">
                            <div class="progress-line">
                                <div class="progress-line-fill" style="width: 75%;"></div>
                            </div>
                            
                            <div class="step completed">
                                <div class="step-icon">
                                    <i class="fas fa-file-medical"></i>
                                </div>
                                <div class="step-label">Request Submitted</div>
                                <div class="step-time">10:30 AM</div>
                            </div>

                            <div class="step completed">
                                <div class="step-icon">
                                    <i class="fas fa-vial"></i>
                                </div>
                                <div class="step-label">Processing</div>
                                <div class="step-time">10:45 AM</div>
                            </div>

                            <div class="step completed">
                                <div class="step-icon">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="step-label">Request Approved</div>
                                <div class="step-time">11:00 AM</div>
                            </div>

                            <div class="step active">
                                <div class="step-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="step-label">In Transit</div>
                                <div class="step-time">In Progress</div>
                            </div>

                            <div class="step">
                                <div class="step-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="step-label">Delivered</div>
                                <div class="step-time">--:--</div>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Update progress line
                        document.addEventListener('DOMContentLoaded', () => {
                            // Update progress line (75% to 100% during delivery)
                            let progress = 75;
                            setInterval(() => {
                                if (progress < 100) {
                                    progress += 0.05;
                                    document.querySelector('.progress-line-fill').style.width = `${progress}%`;
                                }
                            }, 1000);
                        });
                    </script>

                        <?php
                        // Function to fetch pending requests
                        function fetchPendingRequests($user_id) {
                            $ch = curl_init();
                            
                            $headers = [
                                'apikey: ' . SUPABASE_API_KEY,
                                'Authorization: Bearer ' . SUPABASE_API_KEY
                            ];
                            
                            $url = SUPABASE_URL . '/rest/v1/blood_requests?user_id=eq.' . $user_id . '&status=eq.Pending&select=request_id,patient_blood_type,rh_factor,patient_name';
                            
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            
                            $response = curl_exec($ch);
                            curl_close($ch);
                            
                            return json_decode($response, true);
                        }

                        // Get pending requests
                        $pending_requests = fetchPendingRequests($_SESSION['user_id']);

                        // Function to mask sensitive information
                        function maskPatientInfo($name) {
                            if (!$name) return '****';
                            $parts = explode(' ', $name);
                            $maskedParts = array_map(function($part) {
                                if (strlen($part) <= 2) return $part;
                                return substr($part, 0, 1) . str_repeat('*', strlen($part) - 2) . substr($part, -1);
                            }, $parts);
                            return implode(' ', $maskedParts);
                        }
                        ?>

                        <div class="sticky-alerts">
                            <!-- Blood shortage alert -->
                            <div class="blood-alert alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Blood shortage for O- type! Immediate donations needed.
                            </div>

                            <!-- Consolidated pending requests alert -->
                            <?php if (!empty($pending_requests)): ?>
                                <div class="blood-alert alert alert-warning" role="alert">
                                    <i class="fas fa-clock me-2"></i>
                                    <strong>Pending Requests (<?php echo count($pending_requests); ?>)</strong>
                                </div>
                            <?php endif; ?>

                            <!-- Donation drive alert -->
                            <div class="blood-alert alert alert-info" role="alert">
                                <i class="fas fa-calendar me-2"></i>
                                New donation drive scheduled for March 20.
                            </div>
                        </div>

                        <h2 class="card-title mb-3 mt-3">Bloodbanks</h2>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Approved Blood Units</h5>
                                        <div class="chart-container">
                                            <canvas id="bloodChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Most Requested Blood Types</h5>
                                        <div class="chart-container">
                                            <canvas id="requestChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Function to fetch blood requests from Supabase
                        function fetchBloodRequests() {
                            $ch = curl_init();
                            
                            $headers = [
                                'apikey: ' . SUPABASE_API_KEY,
                                'Authorization: Bearer ' . SUPABASE_API_KEY
                            ];
                            
                            $url = SUPABASE_URL . '/rest/v1/blood_requests?select=patient_blood_type,rh_factor,units_requested,status';
                            
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            
                            $response = curl_exec($ch);
                            curl_close($ch);
                            
                            return json_decode($response, true);
                        }

                        // Get blood requests
                        $blood_requests = fetchBloodRequests();

                        // Initialize arrays for blood type counts
                        $available_units = [
                            'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
                            'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
                        ];
                        $requested_units = [
                            'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
                            'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
                        ];

                        // Process blood requests
                        foreach ($blood_requests as $request) {
                            $blood_type = $request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-');
                            
                            if ($request['status'] === 'Approved') {
                                $available_units[$blood_type] += $request['units_requested'];
                            }
                            $requested_units[$blood_type] += $request['units_requested'];
                        }
                        ?>

                        <script>
                            document.addEventListener("DOMContentLoaded", function () {
                                // Blood type specific colors
                                const bloodTypeColors = {
                                    'A+': '#FF4136',  // Bright Red
                                    'A-': '#FF851B',  // Orange
                                    'B+': '#39CCCC',  // Teal
                                    'B-': '#7FDBFF',  // Light Blue
                                    'O+': '#2ECC40',  // Green
                                    'O-': '#01FF70',  // Lime
                                    'AB+': '#B10DC9', // Purple
                                    'AB-': '#F012BE'  // Pink
                                };

                                // Bar Chart for Available Blood Units
                                const ctxBar = document.getElementById('bloodChart').getContext('2d');
                                new Chart(ctxBar, {
                                    type: 'bar',
                                    data: {
                                        labels: <?php echo json_encode(array_keys($available_units)); ?>,
                                        datasets: [{
                                            label: 'Approved Blood Units',
                                            data: <?php echo json_encode(array_values($available_units)); ?>,
                                            backgroundColor: Object.values(bloodTypeColors),
                                            hoverBackgroundColor: Object.values(bloodTypeColors).map(color => color + 'CC')
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                display: false
                                            },
                                            title: {
                                                display: true,
                                                text: 'Approved Blood Units by Type',
                                                font: {
                                                    size: 16
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                grid: {
                                                    color: '#e9ecef'
                                                }
                                            },
                                            x: {
                                                grid: {
                                                    display: false
                                                }
                                            }
                                        }
                                    }
                                });

                                // Pie Chart for Most Requested Blood Types
                                const ctxPie = document.getElementById('requestChart').getContext('2d');
                                new Chart(ctxPie, {
                                    type: 'pie',
                                    data: {
                                        labels: <?php echo json_encode(array_keys($requested_units)); ?>,
                                        datasets: [{
                                            data: <?php echo json_encode(array_values($requested_units)); ?>,
                                            backgroundColor: Object.values(bloodTypeColors)
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'right',
                                                labels: {
                                                    font: {
                                                        size: 12
                                                    }
                                                }
                                            },
                                            title: {
                                                display: true,
                                                text: 'Distribution of Requested Blood Types',
                                                font: {
                                                    size: 16
                                                }
                                            }
                                        }
                                    }
                                });
                            });
                        </script>

                <!-- Urgent Alerts -->
                <div class="alert alert-danger mt-4 p-4 fw-bold fs-5 d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> 🚨 Blood shortage for O- type! Immediate donations needed.
                </div>
                <div class="alert alert-warning p-4 fw-bold fs-5 d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i> ⏳ <?php echo count($pending_requests); ?> pending request(s).
                </div>
                <div class="alert alert-info p-4 fw-bold fs-5 d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i> 📢 New donation drive scheduled for March 20.
                </div>
                <h3 class="mt-4">Your Requests <a href="dashboard-hospital-requests.php" class="btn btn-sm btn-outline-danger ms-2">View All</a></h3>
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Patient Name</th>
                            <th>Blood Type</th>
                            <th>Quantity</th>
                            <th>Urgency</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $displayed_requests = array_slice($pending_requests, 0, 5); // Only show first 5
                        foreach ($displayed_requests as $request): 
                        ?>
                            <tr>
                                <td>
                                    <a href="dashboard-hospital-requests.php" class="text-danger text-decoration-none">
                                        <?php echo maskPatientInfo($request['patient_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-')); ?></td>
                                <td><?php echo htmlspecialchars($request['units_requested'] ?? ''); ?> Units</td>
                                <td class="text-danger fw-bold">High</td>
                                <td class="text-danger">Pending</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </main>
        </div>
    </div>

    <!-- Add this where you want to display messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

<!-- Blood Request Modal -->
<div class="modal fade" id="bloodRequestModal" tabindex="-1" aria-labelledby="bloodRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bloodRequestModalLabel">Blood Request Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bloodRequestForm">
                    <!-- Patient Information Section -->
                    <h6 class="mb-3 fw-bold">Patient Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control" name="patient_name" required>
                    </div>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control" name="patient_age" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="patient_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" class="form-control" name="patient_diagnosis" placeholder="e.g., T/E, FTE, Septic Shock" required>
                    </div>

                    <!-- Blood Request Details Section -->
                    <h6 class="mb-3 mt-4 fw-bold">Blood Request Details</h6>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Blood Type</label>
                            <select class="form-select" name="patient_blood_type" required>
                                <option value="">Select Type</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="O">O</option>
                                <option value="AB">AB</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label">RH Factor</label>
                            <select class="form-select" name="rh_factor" required>
                                <option value="">Select RH</option>
                                <option value="Positive">Positive</option>
                                <option value="Negative">Negative</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 row gx-3">
                        <div class="col-md-4">
                            <label class="form-label">Component</label>
                            <input type="hidden" name="blood_component" value="Whole Blood">
                            <input type="text" class="form-control" value="Whole Blood" readonly style="width: 105%;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Number of Units</label>
                            <input type="number" class="form-control" name="units_requested" min="1" required style="width: 105%;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">When Needed</label>
                            <select id="whenNeeded" class="form-select" name="when_needed" required style="width: 105%;">
                                <option value="ASAP">ASAP</option>
                                <option value="Scheduled">Scheduled</option>
                            </select>
                        </div>
                    </div>
                    <div id="scheduleDateTime" class="mb-3 d-none">
                        <label class="form-label">Scheduled Date & Time</label>
                        <input type="datetime-local" class="form-control" name="scheduled_datetime">
                    </div>

                    <!-- Additional Information Section -->
                    <h6 class="mb-3 mt-4 fw-bold">Additional Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Hospital Admitted</label>
                        <input type="text" class="form-control" name="hospital_admitted" value="<?php echo $_SESSION['user_first_name'] ?? ''; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Requesting Physician</label>
                        <input type="text" class="form-control" name="physician_name" value="<?php echo $_SESSION['user_surname'] ?? ''; ?>" readonly>
                    </div>

                    <!-- File Upload and Signature Section -->
                    <h6 class="mb-3 mt-4 fw-bold">Supporting Documents & Signature</h6>
                    <div class="mb-3">
                        <label class="form-label">Upload Supporting Documents (Images only)</label>
                        <input type="file" class="form-control" name="supporting_docs[]" accept="image/*" multiple>
                        <small class="text-muted">Accepted formats: .jpg, .jpeg, .png</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Physician's Signature</label>
                        <div class="signature-method-selector mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="signature_method" id="uploadSignature" value="upload" checked>
                                <label class="form-check-label" for="uploadSignature">Upload Signature</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="signature_method" id="drawSignature" value="draw">
                                <label class="form-check-label" for="drawSignature">Draw Signature</label>
                            </div>
                        </div>

                        <div id="signatureUpload" class="mb-3">
                            <input type="file" class="form-control" name="signature_file" accept="image/*">
                        </div>

                        <div id="signaturePad" class="d-none">
                            <div class="border rounded p-3 mb-2">
                                <canvas id="physicianSignaturePad" class="w-100" style="height: 200px; border: 1px solid #dee2e6;"></canvas>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" id="clearSignature">Clear</button>
                                <button type="button" class="btn btn-primary btn-sm" id="saveSignature">Save Signature</button>
                            </div>
                            <input type="hidden" name="signature_data" id="signatureData">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1" aria-labelledby="trackingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="trackingModalLabel">Blood Request Tracking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Progress Tracker -->
                <div class="progress-tracker">
                    <div class="progress-steps">
                        <div class="progress-line">
                            <div class="progress-line-fill"></div>
                        </div>
                        
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-file-medical"></i>
                            </div>
                            <div class="step-label">Request Submitted</div>
                            <div class="step-time"></div>
                        </div>
                        
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-vial"></i>
                            </div>
                            <div class="step-label">Processing</div>
                            <div class="step-time"></div>
                        </div>

                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="step-label">Request Approved</div>
                            <div class="step-time"></div>
                        </div>

                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="step-label">In Transit</div>
                            <div class="step-time"></div>
                        </div>

                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="step-label">Delivered</div>
                            <div class="step-time"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Progress Tracker Styles */
    .progress-tracker {
        margin-top: 30px;
        padding: 20px;
    }

    .progress-steps {
        position: relative;
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
    }

    .progress-line {
        position: absolute;
        top: 25px;
        left: 0;
        width: 100%;
        height: 3px;
        background-color: #e9ecef;
        z-index: 1;
    }

    .progress-line-fill {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        background-color: #941022;
        transition: width 0.5s ease;
        width: 0;
    }

    .step {
        position: relative;
        z-index: 2;
        text-align: center;
        width: 50px;
    }

    .step-icon {
        width: 50px;
        height: 50px;
        background-color: #fff;
        border: 3px solid #e9ecef;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        transition: all 0.3s ease;
    }

    .step-icon i {
        color: #6c757d;
        font-size: 20px;
    }

    .step-label {
        margin-top: 10px;
        font-size: 12px;
        color: #6c757d;
    }

    .step.active .step-icon {
        border-color: #941022;
        background-color: #941022;
    }

    .step.active .step-icon i {
        color: #fff;
    }

    .step.completed .step-icon {
        border-color: #198754;
        background-color: #198754;
    }

    .step.completed .step-icon i {
        color: #fff;
    }

    .step-time {
        font-size: 11px;
        color: #6c757d;
        margin-top: 5px;
    }
</style>

<script>
    // Track delivery progress
    let progressInterval;
    let deliveryDuration = 0;
    let progressValue = 75; // Start at 75% (3 steps complete)

    function showTrackingModal(requestId, originLat, originLon, destLat, destLon) {
        const trackingModal = new bootstrap.Modal(document.getElementById('trackingModal'));
        trackingModal.show();

        // First mark the first three steps as completed immediately
        const steps = document.querySelectorAll('#trackingModal .step');
        for(let i = 0; i < 3; i++) { // First 3 steps: Submitted, Processing, Approved
            steps[i].classList.add('completed');
            steps[i].classList.remove('active');
            steps[i].querySelector('.step-time').textContent = new Date().toLocaleTimeString();
        }
        
        // Set In Transit step as active
        steps[3].classList.add('active');
        steps[3].classList.remove('completed');
        steps[3].querySelector('.step-time').textContent = new Date().toLocaleTimeString();

        // Set initial progress (75% as first 3 steps are complete)
        document.querySelector('#trackingModal .progress-line-fill').style.width = '75%';

        // Calculate ETA using OpenRoute API
        fetch('track_delivery_progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'calculate_eta',
                origin_lat: originLat,
                origin_lon: originLon,
                dest_lat: destLat,
                dest_lon: destLon
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.error) {
                // Save delivery duration for progress calculation
                deliveryDuration = data.duration * 60; // Convert API minutes to seconds
                
                // Start the progress animation
                startProgressAnimation();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function startProgressAnimation() {
        // Clear any existing interval
        if (progressInterval) {
            clearInterval(progressInterval);
        }

        // Set up progress animation
        progressInterval = setInterval(() => {
            if (progressValue >= 100) {
                clearInterval(progressInterval);
                completeDelivery();
                return;
            }

            // Increment progress value
            progressValue += 0.1;
            document.querySelector('#trackingModal .progress-line-fill').style.width = `${progressValue}%`;
        }, 1000);

        // Auto-complete after the calculated duration (with a little buffer)
        setTimeout(() => {
            if (progressValue < 100) {
                clearInterval(progressInterval);
                completeDelivery();
            }
        }, deliveryDuration * 1000 + 5000); // Add 5 second buffer
    }

    function completeDelivery() {
        // Mark all steps as completed
        const steps = document.querySelectorAll('#trackingModal .step');
        steps.forEach(step => {
            step.classList.add('completed');
            step.classList.remove('active');
            step.querySelector('.step-time').textContent = new Date().toLocaleTimeString();
        });

        // Set progress to 100%
        document.querySelector('#trackingModal .progress-line-fill').style.width = '100%';

        // Trigger confetti animation
        triggerConfetti();
    }

    function triggerConfetti() {
        // First burst
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 }
        });

        // Second burst after a small delay
        setTimeout(() => {
            confetti({
                particleCount: 50,
                angle: 60,
                spread: 55,
                origin: { x: 0 }
            });
            confetti({
                particleCount: 50,
                angle: 120,
                spread: 55,
                origin: { x: 1 }
            });
        }, 250);

        // Final burst
        setTimeout(() => {
            confetti({
                particleCount: 150,
                spread: 100,
                origin: { y: 0.7 }
            });
        }, 500);
    }
</script>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Signature Pad Library -->
    <script src="/REDCROSS/assets/js/signature_pad.umd.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const ctx = document.getElementById('bloodChart').getContext('2d');
            const bloodChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'],
                    datasets: [{
                        label: 'Blood Units Available',
                        data: [120, 50, 90, 30, 200, 70, 60, 40], // Placeholder data
                        backgroundColor: '#941022',
                        hoverBackgroundColor: '#7a0c1c'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#e9ecef'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });
         
        document.getElementById('whenNeeded').addEventListener('change', function() {
    var scheduleDateTime = document.getElementById('scheduleDateTime');

    if (this.value === 'Scheduled') { // Ensure it matches exactly
        scheduleDateTime.classList.remove('d-none'); // Show the date picker
        scheduleDateTime.style.opacity = 0;
        setTimeout(() => scheduleDateTime.style.opacity = 1, 10); // Smooth fade-in
    } else {
        scheduleDateTime.style.opacity = 0;
        setTimeout(() => scheduleDateTime.classList.add('d-none'), 500); // Hide after fade-out
    }
});

        // Signature Pad Implementation
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('physicianSignaturePad');
            const signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)'
            });

            // Resize canvas for better resolution
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                signaturePad.clear();
            }

            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();

            // Clear signature
            document.getElementById('clearSignature').addEventListener('click', function() {
                signaturePad.clear();
            });

            // Save signature
            document.getElementById('saveSignature').addEventListener('click', function() {
                if (!signaturePad.isEmpty()) {
                    const signatureData = signaturePad.toDataURL();
                    document.getElementById('signatureData').value = signatureData;
                    alert('Signature saved successfully!');
                } else {
                    alert('Please provide a signature first.');
                }
            });

            // Toggle between upload and draw
            document.querySelectorAll('input[name="signature_method"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    const uploadDiv = document.getElementById('signatureUpload');
                    const padDiv = document.getElementById('signaturePad');
                    
                    if (this.value === 'upload') {
                        uploadDiv.classList.remove('d-none');
                        padDiv.classList.add('d-none');
                    } else {
                        uploadDiv.classList.add('d-none');
                        padDiv.classList.remove('d-none');
                        resizeCanvas();
                    }
                });
            });
        });

        document.getElementById('submitRequest').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get the signature data based on selected method
            let signatureData = '';
            const signatureMethod = document.querySelector('input[name="signature_method"]:checked').value;
            
            if (signatureMethod === 'draw') {
                const signaturePad = document.getElementById('physicianSignaturePad');
                signatureData = signaturePad.toDataURL();
            } else {
                const signatureFile = document.querySelector('input[name="signature_file"]').files[0];
                if (signatureFile) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        signatureData = e.target.result;
                        submitFormWithSignature(signatureData);
                    };
                    reader.readAsDataURL(signatureFile);
                    return;
                }
            }
            
            submitFormWithSignature(signatureData);
        });

        function submitFormWithSignature(signatureData) {
            const form = document.getElementById('bloodRequestForm');
            
            // Create hidden input for signature if it doesn't exist
            let signatureInput = document.querySelector('input[name="physician_signature"]');
            if (!signatureInput) {
                signatureInput = document.createElement('input');
                signatureInput.type = 'hidden';
                signatureInput.name = 'physician_signature';
                form.appendChild(signatureInput);
            }
            signatureInput.value = signatureData;
            
            // Handle when_needed field
            const whenNeededSelect = document.getElementById('whenNeeded');
            const scheduledDateTime = document.querySelector('input[name="scheduled_datetime"]');
            
            if (whenNeededSelect.value === 'ASAP') {
                const now = new Date();
                form.querySelector('input[name="when_needed"]').value = now.toISOString();
                form.querySelector('input[name="is_asap"]').value = 'true';
            } else {
                form.querySelector('input[name="when_needed"]').value = scheduledDateTime.value;
                form.querySelector('input[name="is_asap"]').value = 'false';
            }
            
            // Submit the form
            form.submit();
        }

        // Add loading state to sidebar links
        document.querySelectorAll('.dashboard-home-sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('active') && !this.getAttribute('href').includes('#')) {
                    const icon = this.querySelector('i');
                    const text = this.textContent.trim();
                    
                    // Save original content
                    this.setAttribute('data-original-content', this.innerHTML);
                    
                    // Add loading state
                    this.innerHTML = `
                        <div class="d-flex align-items-center">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            ${text}
                        </div>`;
                }
            });
        });
    </script>

    <!-- Blood Request Form JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add blood request form submission handler
        const bloodRequestForm = document.getElementById('bloodRequestForm');
        if (bloodRequestForm) {
            bloodRequestForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                
                // Create FormData object
                const formData = new FormData(this);
                
                // Add additional data
                formData.append('user_id', '<?php echo $_SESSION['user_id']; ?>');
                formData.append('status', 'Pending');
                formData.append('physician_name', '<?php echo $_SESSION['user_surname']; ?>');
                formData.append('requested_on', new Date().toISOString());
                
                // Handle "when needed" logic
                const whenNeeded = document.getElementById('whenNeeded').value;
                const isAsap = whenNeeded === 'ASAP';
                formData.append('is_asap', isAsap ? 'true' : 'false');
                
                // Always set when_needed as a timestamp
                if (isAsap) {
                    // For ASAP, use current date/time
                    formData.set('when_needed', new Date().toISOString());
                } else {
                    // For Scheduled, use the selected date/time
                    const scheduledDate = document.querySelector('#scheduleDateTime input').value;
                    if (scheduledDate) {
                        formData.set('when_needed', new Date(scheduledDate).toISOString());
                    } else {
                        // If no date selected for scheduled, default to current date
                        formData.set('when_needed', new Date().toISOString());
                    }
                }
                
                // Define exact fields from the database schema
                const validFields = [
                    'request_id', 'user_id', 'patient_name', 'patient_age', 'patient_gender', 
                    'patient_diagnosis', 'patient_blood_type', 'rh_factor', 'blood_component', 
                    'units_requested', 'when_needed', 'is_asap', 'hospital_admitted', 
                    'physician_name', 'requested_on', 'status'
                ];
                
                // Convert FormData to JSON object, only including valid fields
                const data = {};
                validFields.forEach(field => {
                    if (formData.has(field)) {
                        const value = formData.get(field);
                        
                        // Convert numeric values to numbers
                        if (field === 'patient_age' || field === 'units_requested') {
                            data[field] = parseInt(value, 10);
                        } 
                        // Convert boolean strings to actual booleans
                        else if (field === 'is_asap') {
                            data[field] = value === 'true';
                        }
                        // Format timestamps properly
                        else if (field === 'when_needed' || field === 'requested_on') {
                            try {
                                // Ensure we have a valid date
                                const dateObj = new Date(value);
                                if (isNaN(dateObj.getTime())) {
                                    throw new Error(`Invalid date for ${field}: ${value}`);
                                }
                                // Format as ISO string with timezone
                                data[field] = dateObj.toISOString();
                            } catch (err) {
                                console.error(`Error formatting date for ${field}:`, err);
                                // Default to current time if invalid
                                data[field] = new Date().toISOString();
                            }
                        }
                        // All other fields as strings
                        else {
                            data[field] = value;
                        }
                    }
                });
                
                console.log('Submitting request data:', data);
                console.log('Valid fields in database:', validFields);
                console.log('FormData keys:', Array.from(formData.keys()));
                console.log('when_needed value:', data.when_needed);
                console.log('requested_on value:', data.requested_on);
                console.log('is_asap value:', data.is_asap);
                
                // Send data to server
                fetch('<?php echo SUPABASE_URL; ?>/rest/v1/blood_requests', {
                    method: 'POST',
                    headers: {
                        'apikey': '<?php echo SUPABASE_API_KEY; ?>',
                        'Authorization': 'Bearer <?php echo SUPABASE_API_KEY; ?>',
                        'Content-Type': 'application/json',
                        'Prefer': 'return=minimal'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => {
                    console.log('Request response status:', response.status);
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Error response body:', text);
                            // Try to parse as JSON to extract more details
                            try {
                                const errorJson = JSON.parse(text);
                                throw new Error(`Error ${response.status}: ${errorJson.message || errorJson.error || text}`);
                            } catch (jsonError) {
                                // If can't parse as JSON, use the raw text
                                throw new Error(`Error ${response.status}: ${text}`);
                            }
                        });
                    }
                    return response.text();
                })
                .then(result => {
                    console.log('Request submitted successfully:', result);
                    
                    // Show success message
                    alert('Blood request submitted successfully!');
                    
                    // Reset form and close modal
                    bloodRequestForm.reset();
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bloodRequestModal'));
                    modal.hide();
                    
                    // Reload the page to show the new request
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error submitting request:', error);
                    alert('Error submitting request: ' + error.message);
                })
                .finally(() => {
                    // Restore button state
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
            });
        }
        
        // Handle when needed change
        const whenNeededSelect = document.getElementById('whenNeeded');
        const scheduleDateTimeDiv = document.getElementById('scheduleDateTime');
        
        if (whenNeededSelect && scheduleDateTimeDiv) {
            whenNeededSelect.addEventListener('change', function() {
                if (this.value === 'Scheduled') {
                    scheduleDateTimeDiv.classList.remove('d-none');
                    scheduleDateTimeDiv.style.opacity = 1;
                    scheduleDateTimeDiv.querySelector('input').required = true;
                } else {
                    scheduleDateTimeDiv.style.opacity = 0;
                    setTimeout(() => {
                        scheduleDateTimeDiv.classList.add('d-none');
                        scheduleDateTimeDiv.querySelector('input').required = false;
                    }, 500);
                }
            });
        }
        
        // Handle signature method toggle
        const uploadSignatureRadio = document.getElementById('uploadSignature');
        const drawSignatureRadio = document.getElementById('drawSignature');
        const signatureUploadDiv = document.getElementById('signatureUpload');
        const signaturePadDiv = document.getElementById('signaturePad');
        
        if (uploadSignatureRadio && drawSignatureRadio) {
            uploadSignatureRadio.addEventListener('change', function() {
                if (this.checked) {
                    signatureUploadDiv.classList.remove('d-none');
                    signaturePadDiv.classList.add('d-none');
                }
            });
            
            drawSignatureRadio.addEventListener('change', function() {
                if (this.checked) {
                    signatureUploadDiv.classList.add('d-none');
                    signaturePadDiv.classList.remove('d-none');
                    initSignaturePad();
                }
            });
        }
    });

    // Initialize signature pad
    function initSignaturePad() {
        const canvas = document.getElementById('physicianSignaturePad');
        if (!canvas) return;
        
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'white',
            penColor: 'black'
        });
        
        // Clear button
        document.getElementById('clearSignature').addEventListener('click', function() {
            signaturePad.clear();
        });
        
        // Save button
        document.getElementById('saveSignature').addEventListener('click', function() {
            if (signaturePad.isEmpty()) {
                alert('Please provide a signature first.');
                return;
            }
            
            const signatureData = signaturePad.toDataURL();
            document.getElementById('signatureData').value = signatureData;
            alert('Signature saved!');
        });
        
        // Resize canvas
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            signaturePad.clear(); // Clear the canvas
        }
        
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    }
    </script>
</body>
</html>