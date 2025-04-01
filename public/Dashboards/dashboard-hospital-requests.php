<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/check_account_hospital_modal.php';

// Function to fetch blood requests from Supabase
function fetchBloodRequests($user_id) {
    $ch = curl_init();
    
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    
    $url = SUPABASE_URL . '/rest/v1/blood_requests?user_id=eq.' . $user_id . '&status=eq.Pending&order=requested_on.desc';
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    
    return json_decode($response, true);
}

// Fetch blood requests for the current user
$blood_requests = fetchBloodRequests($_SESSION['user_id']);
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

        /* Button Styling */
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
        .table thead th {
            background-color: var(--redcross-red);
            color: white;
            border-bottom: none;
        }
        /* Table Styling */
        .table-dark {
            background-color: var(--redcross-red);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(148, 16, 34, 0.05);
        }

        /* Search Bar */
        #searchRequest:focus {
            border-color: var(--redcross-red);
            box-shadow: 0 0 0 0.2rem rgba(148, 16, 34, 0.25);
        }

        /* Timeline Styling */
        .timeline-step[data-status="active"] {
            border-color: var(--redcross-red);
            color: var(--redcross-red);
            font-weight: bold;
        }

        .timeline-step[data-status="completed"] {
            border-color: var(--redcross-red);
            background-color: var(--redcross-red);
            color: white;
        }

        /* Alert Styling */
        .alert-danger {
            background-color: var(--redcross-light-red);
            border-color: var(--redcross-red);
            color: white;
        }

        /* Modal Styling */
        .modal-header {
            background-color: var(--redcross-red);
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Form Controls */
        .form-control:focus {
            box-shadow: none !important;
            border-color: #dee2e6;
        }

        /* Sidebar Active State */
        .dashboard-home-sidebar a.active, 
        .dashboard-home-sidebar a:hover {
            background-color: #e9ecef;
            color: #333;
            font-weight: bold;
        }

        /* Status Colors */
        .text-danger {
            color: var(--redcross-red) !important;
            font-weight: bold;
        }

        /* Sort Indicators */
        .sort-indicator.active {
            color: var(--redcross-red);
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
            box-shadow: none;
            border: 1px solid #dee2e6;
        }

        .search-box .input-group-text {
            background: transparent;
            border: none;
            padding-left: 15px;
            padding-right: 15px;
        }

        .search-box .form-control {
            border: none;
            padding-left: 0;
            padding-right: 15px;
            background: transparent;
        }

        .search-box .form-control:focus {
            box-shadow: none !important;
            outline: none;
            border: none;
        }

        .search-box .input-group:focus-within {
            border: 1px solid #dee2e6;
            box-shadow: none !important;
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
.sort-indicator{
    cursor: pointer;
}
th {
    cursor: pointer;
    position: relative;
}

.sort-indicator {
    margin-left: 5px;
    font-size: 0.8em;
    color: #fffefe;
}

.asc .sort-indicator {
    color: #ffffff;
}

.desc .sort-indicator {
    color: #ffffff;
}
/* Timeline Styling */
.timeline {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 20px 0;
}

.timeline::before {
    content: "";
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background-color: #ddd;
    z-index: 1;
}

.timeline-step {
    position: relative;
    z-index: 2;
    background-color: #fff;
    padding: 5px 10px;
    border: 2px solid #ddd;
    border-radius: 20px;
    text-align: center;
    font-size: 14px;
    color: #666;
}

.timeline-step[data-status="active"] {
    border-color: #0d6efd;
    color: #0d6efd;
    font-weight: bold;
}

.timeline-step[data-status="completed"] {
    border-color: #198754;
    background-color: #198754;
    color: #fff;
}

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

/* Countdown Timer Styles */
.countdown-container {
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.countdown-timer {
    font-family: 'Arial', sans-serif;
}

.time-block {
    text-align: center;
    min-width: 80px;
}

.time-value {
    font-size: 48px;
    font-weight: bold;
    line-height: 1;
}

.time-label {
    font-size: 14px;
    text-transform: uppercase;
    margin-top: 5px;
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

/* Search bar styling */
.search-box .input-group-text,
.search-box .form-control {
    padding-top: 15px;    /* Increased vertical padding */
    padding-bottom: 15px; /* Increased vertical padding */
    height: auto;
}

.search-box .input-group {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: none;
}

.search-box .input-group-text {
    border-right: none;
}

.search-box .form-control {
    border-left: none;
}

.search-box .form-control:focus {
    box-shadow: none !important;
    outline: none;
    border: none;
}

#requestSearchBar:focus {
    box-shadow: none !important;
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
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        <h3 class="text-danger mb-0"><?php echo $_SESSION['user_first_name']; ?></h3>
                        <small class="text-muted">Hospital Request Dashboard</small>
                    </div>
                    
                    <div class="search-box mb-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" placeholder="Search...">
                        </div>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-hospital-main.php">
                                <i class="fas fa-home me-2"></i>Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-hospital-requests.php">
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
                        <h2 style="color: #941022; border-bottom: 2px solid #941022; padding-bottom: 18px; margin-bottom: 25px;">Your Blood Requests</h2>
                    
                        <!-- Add search bar -->
                        <div class="search-box mb-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="requestSearchBar" class="form-control border-start-0 ps-0" 
                                       placeholder="Search requests..." 
                                       style="background-color: #ffffff; color: #333333;">
                            </div>
                        </div>

                        <!-- Requests Table -->
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Request ID</th>
                                    <th>Patient Name</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Blood Type</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Requested On</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="requestTable">
                                <?php if (empty($blood_requests)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No pending blood requests found.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($blood_requests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                            <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['patient_age']); ?></td>
                                            <td><?php echo htmlspecialchars($request['patient_gender']); ?></td>
                                            <td><?php echo htmlspecialchars($request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-')); ?></td>
                                            <td><?php echo htmlspecialchars($request['units_requested'] . ' Units'); ?></td>
                                            <td><?php echo htmlspecialchars($request['status']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($request['requested_on'])); ?></td>
                                            <td>
                                                <?php if ($request['status'] !== 'Completed' && $request['status'] !== 'Rejected'): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="showTrackingModal('<?php echo htmlspecialchars($request['request_id']); ?>')">
                                                        <i class="fas fa-map-marker-alt"></i> Track
                                                    </button>
                                                    <button class="btn btn-sm btn-warning edit-btn" 
                                                        data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                                        data-patient-age="<?php echo htmlspecialchars($request['patient_age']); ?>"
                                                        data-patient-gender="<?php echo htmlspecialchars($request['patient_gender']); ?>"
                                                        data-patient-diagnosis="<?php echo htmlspecialchars($request['patient_diagnosis']); ?>"
                                                        data-blood-type="<?php echo htmlspecialchars($request['patient_blood_type']); ?>"
                                                        data-rh-factor="<?php echo htmlspecialchars($request['rh_factor']); ?>"
                                                        data-component="<?php echo htmlspecialchars($request['component']); ?>"
                                                        data-units="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                                        data-when-needed="<?php echo htmlspecialchars($request['when_needed']); ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </main>
            </div>
        </div>


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
                            <input type="number" class="form-control" name="age" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" class="form-control" name="diagnosis" placeholder="e.g., T/E, FTE, Septic Shock" required>
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
                            <input type="number" class="form-control" name="units" min="1" required style="width: 105%;">
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
                        <input type="text" class="form-control" name="hospital" value="<?php echo $_SESSION['user_first_name'] ?? ''; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Requesting Physician</label>
                        <input type="text" class="form-control" name="physician" value="<?php echo $_SESSION['user_surname'] ?? ''; ?>" readonly>
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
<!-- Edit Modal -->
<div class="modal fade" id="bloodReorderModal" tabindex="-1" aria-labelledby="bloodEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bloodEditModalLabel">Edit Blood Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editRequestForm">
                    <input type="hidden" id="editRequestId">
                    <!-- Patient Name -->
                    <div class="mb-3">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control" id="reorderPatientName">
                    </div>

                    <!-- Age and Gender -->
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control" id="reorderAge">
                        </div>
                        <div class="col">
                            <label class="form-label">Gender</label>
                            <select class="form-select" id="reorderGender">
                                <option>Male</option>
                                <option>Female</option>
                            </select>
                        </div>
                    </div>

                    <!-- Diagnosis -->
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" class="form-control" id="reorderDiagnosis">
                    </div>

                    <!-- Blood Type and RH -->
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Blood Type</label>
                            <select class="form-select" id="reorderBloodType">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="O">O</option>
                                <option value="AB">AB</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label">RH</label>
                            <select class="form-select" id="reorderRH">
                                <option value="Positive">Positive</option>
                                <option value="Negative">Negative</option>
                            </select>
                        </div>
                    </div>

                    <!-- Component -->
                    <div class="mb-3">
                        <label class="form-label">Component</label>
                        <select class="form-select" id="reorderComponent">
                            <option value="Whole Blood">Whole Blood</option>
                            <option value="Platelet Concentrate">Platelet Concentrate</option>
                            <option value="Fresh Frozen Plasma">Fresh Frozen Plasma</option>
                            <option value="Packed Red Blood Cells">Packed Red Blood Cells</option>
                        </select>
                    </div>

                    <!-- Number of Units -->
                    <div class="mb-3">
                        <label class="form-label">Number of Units</label>
                        <input type="number" class="form-control" id="reorderUnits" min="1">
                    </div>

                    <!-- When Needed -->
                    <div class="mb-3">
                        <label class="form-label">When Needed</label>
                        <select id="reorderWhenNeeded" class="form-select">
                            <option value="ASAP">ASAP</option>
                            <option value="Scheduled">Scheduled</option>
                        </select>
                    </div>

                    <!-- Scheduled Date & Time -->
                    <div id="reorderScheduleDateTime" class="mb-3 d-none">
                        <label class="form-label">Scheduled Date & Time</label>
                        <div class="input-group">
                            <input type="datetime-local" class="form-control" id="reorderScheduledDateTime">
                            <span class="input-group-text bg-light scheduled-label"></span>
                        </div>
                        <small class="form-text text-muted mt-1">
                            Select your preferred date and time for blood delivery
                        </small>
                    </div>

                    <!-- Buttons -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Track Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1" aria-labelledby="trackingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="trackingModalLabel">Blood Request Tracking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Countdown Timer -->
                <div class="countdown-container text-center mb-4">
                    <h3 class="text-danger mb-2">Estimated Time Remaining</h3>
                    <div class="countdown-timer d-flex justify-content-center gap-3">
                        <div class="time-block">
                            <div id="hours" class="time-value display-4 fw-bold text-danger">--</div>
                            <div class="time-label text-muted">HOURS</div>
                        </div>
                        <div class="time-block">
                            <div class="display-4 fw-bold text-danger">:</div>
                        </div>
                        <div class="time-block">
                            <div id="minutes" class="time-value display-4 fw-bold text-danger">--</div>
                            <div class="time-label text-muted">MINUTES</div>
                        </div>
                        <div class="time-block">
                            <div class="display-4 fw-bold text-danger">:</div>
                        </div>
                        <div class="time-block">
                            <div id="seconds" class="time-value display-4 fw-bold text-danger">--</div>
                            <div class="time-label text-muted">SECONDS</div>
                        </div>
                    </div>
                </div>
                
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

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Define Supabase constants
        const SUPABASE_URL = '<?php echo SUPABASE_URL; ?>';
        const SUPABASE_KEY = '<?php echo SUPABASE_API_KEY; ?>';
        
        document.addEventListener("DOMContentLoaded", function () {
    let headers = document.querySelectorAll("th");

    headers.forEach((header, index) => {
        if (index < headers.length - 1) { // Ignore the last column (Action)
            // Create a single sorting indicator for each column
            let icon = document.createElement("span");
            icon.classList.add("sort-indicator");
            icon.textContent = " ▼"; // Default neutral state
            header.appendChild(icon);

            // Add click event listener to sort the column
            header.addEventListener("click", function () {
                sortTable(index, header);
            });
        }
    });
});

function sortTable(columnIndex, header) {
    let table = document.querySelector("table");
    let tbody = table.querySelector("tbody");
    let rows = Array.from(tbody.rows);

    // Determine the current sorting order
    let isAscending = header.classList.contains("asc");
    header.classList.toggle("asc", !isAscending);
    header.classList.toggle("desc", isAscending);

    // Determine the data type of the column
    let type = (columnIndex === 2) ? "number" : 
               (columnIndex === 5 || columnIndex === 6) ? "date" : "text";

    // Sort the rows
    rows.sort((rowA, rowB) => {
        let cellA = rowA.cells[columnIndex].textContent.trim();
        let cellB = rowB.cells[columnIndex].textContent.trim();

        if (type === 'number') {
            return isAscending ? parseInt(cellA) - parseInt(cellB) : parseInt(cellB) - parseInt(cellA);
        } else if (type === 'date') {
            return isAscending ? new Date(cellA) - new Date(cellB) : new Date(cellB) - new Date(cellA);
        } else {
            return isAscending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        }
    });

    // Append sorted rows back to the table
    tbody.append(...rows);

    // Update the indicator for the active sorted column
    let sortIcon = header.querySelector(".sort-indicator");
    sortIcon.textContent = isAscending ? " ▲" : " ▼"; // Toggle between ▲ and ▼
}
//Placeholder Static Data
document.addEventListener("DOMContentLoaded", function () {
    // Add click event to all Reorder buttons
    document.querySelectorAll(".btn-secondary").forEach(button => {
        button.addEventListener("click", function (event) {
            event.stopPropagation(); // Prevent row click event from firing

            // Get the row data
            let row = this.closest("tr");
            let cells = row.querySelectorAll("td");

            // Extract data from the row
            let requestId = cells[0].textContent;
            let bloodType = cells[2].textContent;
            let quantity = cells[3].textContent.replace(" Units", ""); // Remove " Units"
            let urgency = cells[4].textContent;
            let status = cells[5].textContent;
            let requestedOn = cells[6].textContent;
            let expectedDelivery = cells[7].textContent;

            // Pre-fill the reorder modal form
            let modal = document.getElementById("bloodReorderModal");
            modal.querySelector("#reorderPatientName").value = "Patient Name"; // Replace with actual patient name if available
            modal.querySelector("#reorderAge").value = 30; // Replace with actual age if available
            modal.querySelector("#reorderGender").value = "Male"; // Replace with actual gender if available
            modal.querySelector("#reorderDiagnosis").value = "Diagnosis"; // Replace with actual diagnosis if available
            modal.querySelector("#reorderBloodType").value = bloodType.split("+")[0]; // Extract blood type (e.g., "A" from "A+")
            modal.querySelector("#reorderRH").value = bloodType.includes("+") ? "Positive" : "Negative"; // Extract RH
            modal.querySelector("#reorderComponent").value = "Component"; // Replace with actual component if available
            modal.querySelector("#reorderUnits").value = quantity; // Pre-fill quantity
            modal.querySelector("#reorderWhenNeeded").value = "ASAP"; // Default to ASAP
            modal.querySelector("#reorderScheduledDateTime").value = expectedDelivery; // Pre-fill expected delivery

            // Show/hide scheduled date & time based on "When Needed"
            let whenNeeded = modal.querySelector("#reorderWhenNeeded");
            let scheduleDateTime = modal.querySelector("#reorderScheduleDateTime");
            whenNeeded.addEventListener("change", function () {
                if (this.value === "Scheduled") {
                    scheduleDateTime.classList.remove("d-none");
                } else {
                    scheduleDateTime.classList.add("d-none");
                }
            });

            // Open the reorder modal
            new bootstrap.Modal(modal).show();
        });
    });
});
//Track modal
document.addEventListener("DOMContentLoaded", function () {
    // Add click event to all Track buttons
    document.querySelectorAll(".btn-primary").forEach(button => {
        button.addEventListener("click", function (event) {
            event.stopPropagation(); // Prevent row click event from firing

            // Get the row data
            let row = this.closest("tr");
            let cells = row.querySelectorAll("td");

            // Extract data from the row
            let requestId = cells[0].textContent;
            let status = cells[5].textContent.trim(); // Current status (e.g., "Pending", "In Transit", "Delivered")

            // Pre-fill the track modal with data
            let modal = document.getElementById("trackingModal");
            let timelineSteps = modal.querySelectorAll(".timeline-step");

            // Update the current status
            modal.querySelector("#trackStatus").textContent = `Your blood is ${status.toLowerCase()}.`;

            // Update the timeline based on the current status
            timelineSteps.forEach(step => step.removeAttribute("data-status"));

            switch (status) {
                case "Being Processed":
                    timelineSteps[0].setAttribute("data-status", "active");
                    break;
                case "In Storage":
                    timelineSteps[0].setAttribute("data-status", "completed");
                    timelineSteps[1].setAttribute("data-status", "active");
                    break;
                case "Being Distributed":
                    timelineSteps[0].setAttribute("data-status", "completed");
                    timelineSteps[1].setAttribute("data-status", "completed");
                    timelineSteps[2].setAttribute("data-status", "active");
                    break;
                case "Delivered":
                    timelineSteps.forEach(step => step.setAttribute("data-status", "completed"));
                    break;
                default:
                    timelineSteps[0].setAttribute("data-status", "active");
            }

            // Open the track modal
            new bootstrap.Modal(modal).show();
        });
    });
});
         //Shows the date and time if the Scheduled is selected
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

        document.addEventListener("DOMContentLoaded", function () {
            // Function to format date for display
            function formatDate(date) {
                const options = { 
                    weekday: 'short', 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                return new Date(date).toLocaleDateString('en-US', options);
            }

            // Function to update scheduled label
            function updateScheduledLabel() {
                const dateInput = document.getElementById('reorderScheduledDateTime');
                const label = document.querySelector('.scheduled-label');
                if (dateInput.value) {
                    const formattedDate = formatDate(dateInput.value);
                    label.textContent = `Scheduled for: ${formattedDate}`;
                } else {
                    label.textContent = '';
                }
            }

            // Handle when needed change in edit modal
            document.getElementById('reorderWhenNeeded').addEventListener('change', function() {
                const scheduleDateTimeDiv = document.getElementById('reorderScheduleDateTime');
                if (this.value === 'Scheduled') {
                    scheduleDateTimeDiv.classList.remove('d-none');
                    // Set default date to tomorrow
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    tomorrow.setHours(9, 0, 0, 0); // Set to 9 AM
                    document.getElementById('reorderScheduledDateTime').value = tomorrow.toISOString().slice(0, 16);
                    updateScheduledLabel();
                } else {
                    scheduleDateTimeDiv.classList.add('d-none');
                }
            });

            // Update label when date changes
            document.getElementById('reorderScheduledDateTime').addEventListener('change', updateScheduledLabel);

            // Handle edit button clicks
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Get data from button attributes
                    const data = this.dataset;
                    
                    // Store request ID for update
                    document.getElementById('editRequestId').value = data.requestId;
                    
                    // Populate edit modal with request data
                    document.getElementById('reorderPatientName').value = data.patientName;
                    document.getElementById('reorderAge').value = data.patientAge;
                    document.getElementById('reorderGender').value = data.patientGender;
                    document.getElementById('reorderDiagnosis').value = data.patientDiagnosis;
                    document.getElementById('reorderBloodType').value = data.bloodType;
                    document.getElementById('reorderRH').value = data.rhFactor;
                    document.getElementById('reorderComponent').value = data.component;
                    document.getElementById('reorderUnits').value = data.units;

                    // Handle when needed
                    const whenNeeded = new Date(data.whenNeeded);
                    const now = new Date();
                    if (whenNeeded > now) {
                        document.getElementById('reorderWhenNeeded').value = 'Scheduled';
                        document.getElementById('reorderScheduleDateTime').classList.remove('d-none');
                        document.getElementById('reorderScheduledDateTime').value = data.whenNeeded.slice(0, 16);
                        updateScheduledLabel();
                    } else {
                        document.getElementById('reorderWhenNeeded').value = 'ASAP';
                        document.getElementById('reorderScheduleDateTime').classList.add('d-none');
                    }

                    // Show the edit modal
                    new bootstrap.Modal(document.getElementById('bloodReorderModal')).show();
                });
            });

            // Handle edit form submission
            document.getElementById('editRequestForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const requestId = document.getElementById('editRequestId').value;
                const whenNeeded = document.getElementById('reorderWhenNeeded').value;
                const scheduledDateTime = document.getElementById('reorderScheduledDateTime').value;
                
                // Add loading state to button
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

                try {
                    // Get form data
                    const formData = {
                        patient_name: document.getElementById('reorderPatientName').value,
                        patient_age: parseInt(document.getElementById('reorderAge').value),
                        patient_gender: document.getElementById('reorderGender').value,
                        patient_diagnosis: document.getElementById('reorderDiagnosis').value,
                        patient_blood_type: document.getElementById('reorderBloodType').value,
                        rh_factor: document.getElementById('reorderRH').value,
                        component: document.getElementById('reorderComponent').value,
                        units_requested: parseInt(document.getElementById('reorderUnits').value),
                        is_asap: whenNeeded === 'ASAP',
                        when_needed: whenNeeded === 'ASAP' ? new Date().toISOString() : scheduledDateTime
                    };

                    // Log the request details for debugging
                    console.log('Updating request:', requestId);
                    console.log('Update data:', formData);

                    // Make the update request
                    const response = await fetch(`<?php echo SUPABASE_URL; ?>/rest/v1/blood_requests?request_id=eq.${requestId}`, {
                        method: 'PATCH',
                        headers: {
                            'apikey': '<?php echo SUPABASE_API_KEY; ?>',
                            'Authorization': 'Bearer <?php echo SUPABASE_API_KEY; ?>',
                            'Content-Type': 'application/json',
                            'Prefer': 'return=representation'
                        },
                        body: JSON.stringify(formData)
                    });

                    // Log the response for debugging
                    console.log('Response status:', response.status);
                    const responseText = await response.text();
                    console.log('Response body:', responseText);

                    if (!response.ok) {
                        throw new Error(`Update failed with status ${response.status}: ${responseText}`);
                    }

                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success alert-dismissible fade show';
                    successAlert.innerHTML = `
                        Request updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.querySelector('.modal-body').insertBefore(successAlert, document.querySelector('.modal-body').firstChild);

                    // Reload page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);

                } catch (error) {
                    console.error('Error updating request:', error);
                    
                    // Show error message in modal
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                    errorAlert.innerHTML = `
                        Failed to update request: ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.querySelector('.modal-body').insertBefore(errorAlert, document.querySelector('.modal-body').firstChild);
                    
                } finally {
                    // Restore button state
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fas fa-save me-1"></i> Save Changes';
                }
            });
        });

        // Static countdown timer simulation
        function updateStaticTimer() {
            let seconds = parseInt(document.getElementById('staticSeconds').textContent);
            let minutes = parseInt(document.getElementById('staticMinutes').textContent);
            let hours = parseInt(document.getElementById('staticHours').textContent);

            seconds--;
            if (seconds < 0) {
                seconds = 59;
                minutes--;
                if (minutes < 0) {
                    minutes = 59;
                    hours--;
                    if (hours < 0) {
                        hours = 0;
                        minutes = 0;
                        seconds = 0;
                    }
                }
            }

            document.getElementById('staticHours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('staticMinutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('staticSeconds').textContent = seconds.toString().padStart(2, '0');
        }

        // Update timer every second
        setInterval(updateStaticTimer, 1000);

        // Function to show tracking modal
        function showTrackingModal(requestId) {
            const trackingModal = new bootstrap.Modal(document.getElementById('trackingModal'));
            
            // Get all request IDs from the table
            const requestIds = Array.from(document.querySelectorAll('table tbody tr')).map(row => {
                return row.cells[0].textContent.trim();
            }).sort();

            // Check if this is the lowest request ID
            const isLowestId = requestId === requestIds[0];

            // Get modal elements
            const countdownContainer = document.querySelector('.countdown-container h3');
            const hours = document.getElementById('hours');
            const minutes = document.getElementById('minutes');
            const seconds = document.getElementById('seconds');
            const steps = document.querySelectorAll('.step');
            const progressLine = document.querySelector('.progress-line-fill');

            if (isLowestId) {
                // Being Delivered state
                countdownContainer.textContent = 'Estimated Time Remaining';
                hours.textContent = '00';
                minutes.textContent = '20';
                seconds.textContent = '00';

                // Mark first three steps as completed
                for (let i = 0; i < 3; i++) {
                    steps[i].classList.add('completed');
                    steps[i].classList.remove('active');
                    steps[i].querySelector('.step-time').textContent = new Date().toLocaleTimeString();
                }

                // Set "In Transit" as active
                steps[3].classList.add('active');
                steps[3].classList.remove('completed');
                steps[3].querySelector('.step-time').textContent = 'In Progress';
                steps[4].classList.remove('completed', 'active');
                steps[4].querySelector('.step-time').textContent = '--:--';

                // Set progress to 75%
                progressLine.style.width = '75%';

                // Start countdown
                startCountdown();
            } else {
                // Processing state
                countdownContainer.textContent = 'Processing Request';
                hours.textContent = '--';
                minutes.textContent = '--';
                seconds.textContent = '--';

                // Mark first two steps as completed
                for (let i = 0; i < 2; i++) {
                    steps[i].classList.add('completed');
                    steps[i].classList.remove('active');
                    steps[i].querySelector('.step-time').textContent = new Date().toLocaleTimeString();
                }

                // Set "Request Approved" as active
                steps[2].classList.add('active');
                steps[2].classList.remove('completed');
                steps[2].querySelector('.step-time').textContent = 'In Progress';

                // Reset remaining steps
                for (let i = 3; i < steps.length; i++) {
                    steps[i].classList.remove('completed', 'active');
                    steps[i].querySelector('.step-time').textContent = '--:--';
                }

                // Set progress to 50%
                progressLine.style.width = '50%';
            }

            trackingModal.show();
        }

        let countdownInterval;

        function startCountdown() {
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }

            let totalSeconds = 20 * 60; // 20 minutes in seconds

            countdownInterval = setInterval(() => {
                if (totalSeconds <= 0) {
                    clearInterval(countdownInterval);
                    return;
                }

                totalSeconds--;
                const hours = Math.floor(totalSeconds / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const seconds = totalSeconds % 60;

                document.getElementById('hours').textContent = String(hours).padStart(2, '0');
                document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
                document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');

                // Update progress line (75% to 100% during delivery)
                const progress = 75 + (25 * (1 - totalSeconds / (20 * 60)));
                document.querySelector('.progress-line-fill').style.width = `${progress}%`;
            }, 1000);
        }

        // Clean up interval when modal is hidden
        document.getElementById('trackingModal').addEventListener('hidden.bs.modal', () => {
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
        });

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

        // Add loading state to Save Changes button in edit form
        document.getElementById('editRequestForm').addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                // Save original content
                const originalContent = submitButton.innerHTML;
                
                // Add loading state
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
                
                // Restore button state after request completes
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalContent;
                }, 2000);
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchBar = document.getElementById('requestSearchBar');
            searchBar.addEventListener('keyup', function() {
                const searchText = this.value.toLowerCase();
                const table = document.getElementById('requestTable');
                const rows = table.getElementsByTagName('tr');

                for (let row of rows) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchText) ? '' : 'none';
                }
            });

            // Add focus styles for search bar
            searchBar.addEventListener('focus', function() {
                this.style.boxShadow = '0 0 0 0.2rem rgba(148, 16, 34, 0.25)';
            });

            searchBar.addEventListener('blur', function() {
                this.style.boxShadow = 'none';
            });
        });
    </script>
</body>
</html>