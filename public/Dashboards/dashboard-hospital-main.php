<?php
session_start();
require_once '../../assets/conn/db_conn.php';
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check for correct role (example for admin dashboard)
$required_role = 2; // Hospital Role
if ($_SESSION['role_id'] !== $required_role) {
    header("Location: index.php");
    exit();
}

// Fetch user details from the users table
$user_id = $_SESSION['user_id'];

// Function to make Supabase request
function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $ch = curl_init($url);
    
    $headers = array(
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    );

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Fetch user data from Supabase
$users_response = supabaseRequest("users?user_id=eq.$user_id&select=surname,first_name");

if ($users_response && is_array($users_response) && count($users_response) > 0) {
    $user_data = $users_response[0];
    $_SESSION['user_surname'] = $user_data['surname'];
    $_SESSION['user_first_name'] = $user_data['first_name'];
}

// Remove PDO connection code and replace with Supabase REST API handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_blood_request'])) {
    try {
        // Set when_needed based on is_asap
        $whenNeeded = isset($_POST['is_asap']) && $_POST['is_asap'] === 'true' 
            ? date('Y-m-d H:i:s') 
            : $_POST['when_needed'];

        // Prepare the request data
        $requestData = array(
            'user_id' => $_SESSION['user_id'],
            'patient_name' => $_POST['patient_name'],
            'patient_age' => intval($_POST['patient_age']),
            'patient_gender' => $_POST['patient_gender'],
            'patient_diagnosis' => $_POST['patient_diagnosis'],
            'patient_blood_type' => $_POST['blood_type'],
            'rh_factor' => $_POST['rh_factor'],
            'component' => $_POST['component'],
            'units_requested' => intval($_POST['units_requested']),
            'is_asap' => isset($_POST['is_asap']) && $_POST['is_asap'] === 'true',
            'when_needed' => $whenNeeded,
            'physician_name' => $_SESSION['user_surname'],
            'physician_signature' => $_POST['physician_signature'],
            'hospital_admitted' => $_SESSION['user_first_name'],
            'status' => 'Pending'
        );

        // Make POST request to Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_requests');
        
        // Set headers
        $headers = array(
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        );

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if request was successful
        if ($httpCode >= 200 && $httpCode < 300) {
            $_SESSION['success_message'] = "Blood request submitted successfully!";
        } else {
            throw new Exception("Error submitting request. HTTP Code: " . $httpCode);
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error submitting request: " . $e->getMessage();
    }

    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
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
            left: 240px;
            width: calc(100% - 240px);
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
            width: 240px;
            background-color: #ffffff;
            border-right: 1px solid #ddd;
            padding: 20px;
            transition: width 0.3s ease;
        }
        .dashboard-home-sidebar a {
            text-decoration: none;
            color: #333;
            display: block;
            padding: 10px;
            border-radius: 5px;
        }
        .dashboard-home-sidebar a.active, 
        .dashboard-home-sidebar a:hover {
            background-color: #e9ecef;
            font-weight: bold;
        }
        /* Main Content Styling */
        .dashboard-home-main {
            margin-left: 240px;
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

    </style>
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
                <input type="text" class="form-control mb-3" placeholder="Search...">
                <a href="#" class="active"><i class="fas fa-home me-2"></i>Home</a>
                <a href="dashboard-hospital-requests.php"><i class="fas fa-tint me-2"></i>Your Requests</a>
                <a href="dashboard-hospital-history.php"><i class="fas fa-users me-2"></i>Blood History</a>
            </nav>

            <!-- Main Content -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="container-fluid p-4 custom-margin">
                        <h2 class="card-title mb-3">Bloodbanks</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <canvas id="bloodChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                 <!-- Urgent Alerts -->
                <div class="alert alert-danger mt-4 p-4 fw-bold fs-5 d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> 🚨 Blood shortage for O- type! Immediate donations needed.
                </div>
                <div class="alert alert-warning p-4 fw-bold fs-5 d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i> ⏳ Request REQ-003 is still pending approval.
                </div>
                <div class="alert alert-info p-4 fw-bold fs-5 d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i> 📢 New donation drive scheduled for March 20.
                </div>
                <h3 class="mt-4">Your Requests</h3>
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Request ID</th>
                            <th>Blood Type</th>
                            <th>Quantity</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Requested On</th>
                            <th>Expected Delivery</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>REQ-001</td>
                            <td>A+</td>
                            <td>5 Units</td>
                            <td class="text-danger fw-bold">High</td>
                            <td class="text-danger">Pending</td>
                            <td>2025-03-15</td>
                            <td>2025-03-16</td>
                            <td>
                                <button class="btn btn-sm btn-primary">Track</button>
                                <button class="btn btn-sm btn-secondary">Reorder</button>
                            </td>
                        </tr>
                        <tr>
                            <td>REQ-002</td>
                            <td>O+</td>
                            <td>3 Units</td>
                            <td class="text-warning fw-bold">Medium</td>
                            <td class="text-success">Completed</td>
                            <td>2025-03-14</td>
                            <td>2025-03-14</td>
                            <td>
                                <button class="btn btn-sm btn-secondary">Reorder</button>
                            </td>
                        </tr>
                        <tr>
                            <td>REQ-003</td>
                            <td>B-</td>
                            <td>2 Units</td>
                            <td class="text-danger fw-bold">High</td>
                            <td class="text-warning">Processing</td>
                            <td>2025-03-13</td>
                            <td>2025-03-15</td>
                            <td>
                                <button class="btn btn-sm btn-primary">Track</button>
                            </td>
                        </tr>
                        <tr>
                            <td>REQ-004</td>
                            <td>AB-</td>
                            <td>1 Unit</td>
                            <td class="text-success fw-bold">Low</td>
                            <td class="text-dark">Canceled</td>
                            <td>2025-03-12</td>
                            <td>-</td>
                            <td>
                                <button class="btn btn-sm btn-secondary">Reorder</button>
                            </td>
                        </tr>
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
                <form id="bloodRequestForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <!-- Add this hidden input for form identification -->
                    <input type="hidden" name="submit_blood_request" value="1">
                    <!-- Add hidden fields for is_asap and when_needed -->
                    <input type="hidden" name="is_asap" value="true">
                    <input type="hidden" name="when_needed" value="">
                    
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
                            <select class="form-select" name="blood_type" required>
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
                            <select class="form-select" name="component" required style="width: 105%;">
                                <option value="">Select Component</option>
                                <option value="Whole Blood">Whole Blood</option>
                                <option value="Platelet Concentrate">Platelet Concentrate</option>
                                <option value="Fresh Frozen Plasma">Fresh Frozen Plasma</option>
                                <option value="Packed Red Blood Cells">Packed Red Blood Cells</option>
                            </select>
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
                        <input type="text" class="form-control" name="hospital_admitted" value="<?php echo $_SESSION['user_first_name']; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Requesting Physician</label>
                        <input type="text" class="form-control" name="physician_name" value="<?php echo $_SESSION['user_surname']; ?>" readonly>
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
                        <button type="submit" class="btn btn-danger" id="submitRequest">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


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
    </script>
</body>
</html>