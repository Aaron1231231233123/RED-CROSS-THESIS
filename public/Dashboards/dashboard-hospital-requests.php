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

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard-hospital-main.php' ? ' active' : ''; ?>" href="dashboard-hospital-main.php">
                                <i class="fas fa-home me-2"></i>Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard-hospital-requests.php' ? ' active' : ''; ?>" href="dashboard-hospital-requests.php">
                                <i class="fas fa-tint me-2"></i>Active Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <?php
                            $historyPages = ['dashboard-hospital-request-history.php', 'dashboard-hospital-history.php'];
                            $isHistory = in_array(basename($_SERVER['PHP_SELF']), $historyPages);
                            $status = $_GET['status'] ?? '';
                            ?>
                            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#historyCollapse" role="button" aria-expanded="<?php echo $isHistory ? 'true' : 'false'; ?>" aria-controls="historyCollapse" id="historyCollapseBtn">
                                <span><i class="fas fa-history me-2"></i>Requests</span>
                                <i class="fas fa-chevron-down transition-arrow<?php echo $isHistory ? ' rotate' : ''; ?>" id="historyChevron"></i>
                            </a>
                            <div class="collapse<?php echo $isHistory ? ' show' : ''; ?>" id="historyCollapse">
                                <div class="collapse-menu">
                                    <a href="dashboard-hospital-history.php" class="nav-link<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard-hospital-history.php' ? ' active' : ''; ?>">Approved</a>
                                    <a href="dashboard-hospital-request-history.php?status=completed" class="nav-link<?php echo $isHistory && $status === 'completed' ? ' active' : ''; ?>">Completed</a>
                                    <a href="dashboard-hospital-request-history.php?status=declined" class="nav-link<?php echo $isHistory && $status === 'declined' ? ' active' : ''; ?>">Declined</a>
                                </div>
                            </div>
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
                                       placeholder="Search by patient name..." 
                                       style="background-color: #ffffff; color: #333333;">
                            </div>
                        </div>

                        <!-- Requests Table -->
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>No.</th>
                                    <th>Patient Name</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Blood Type</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Physician</th>
                                    <th>Requested On</th>
                                    <th>Pick Up Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="requestTable">
                                <?php $rowNum = 1; if (empty($blood_requests)): ?>
                                <tr>
                                    <td colspan="11" class="text-center">No pending blood requests found.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($blood_requests as $request): ?>
                                        <tr>
                                            <td><?php echo $rowNum++; ?></td>
                                            <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['patient_age']); ?></td>
                                            <td><?php echo htmlspecialchars($request['patient_gender']); ?></td>
                                            <td><?php echo htmlspecialchars($request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-')); ?></td>
                                            <td><?php echo htmlspecialchars($request['units_requested'] . ' Units'); ?></td>
                                            <td>
                                                <?php 
                                                $status = $request['status'];
                                                if ($status === 'Approved' || $status === 'Accepted') {
                                                    echo '<span class="badge bg-primary">Approved</span>';
                                                } elseif ($status === 'Completed' || $status === 'Confirmed' || $status === 'Printed') {
                                                    echo '<span class="badge bg-info text-dark">Printed</span>';
                                                } elseif ($status === 'Pending') {
                                                    echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                } elseif ($status === 'Declined') {
                                                    echo '<span class="badge bg-danger">Declined</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">No Action</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($request['physician_name'] ?? ''); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($request['requested_on'])); ?></td>
                                            <td><?php echo !empty($request['last_updated']) ? date('Y-m-d', strtotime($request['last_updated'])) : '-'; ?></td>
                                            <td>
                                                <?php if ($request['status'] !== 'Completed' && $request['status'] !== 'Rejected'): ?>
                                                    <button class="btn btn-sm btn-primary view-btn" 
                                                        data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                                        data-patient-name="<?php echo htmlspecialchars($request['patient_name']); ?>"
                                                        data-patient-age="<?php echo htmlspecialchars($request['patient_age']); ?>"
                                                        data-patient-gender="<?php echo htmlspecialchars($request['patient_gender']); ?>"
                                                        data-patient-diagnosis="<?php echo htmlspecialchars($request['patient_diagnosis']); ?>"
                                                        data-blood-type="<?php echo htmlspecialchars($request['patient_blood_type']); ?>"
                                                        data-rh-factor="<?php echo htmlspecialchars($request['rh_factor']); ?>"
                                                        data-component="Whole Blood"
                                                        data-units="<?php echo htmlspecialchars($request['units_requested']); ?>"
                                                        data-when-needed="<?php echo htmlspecialchars($request['when_needed']); ?>">
                                                        <i class="fas fa-eye"></i> View
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
<!-- View Modal -->
<div class="modal fade" id="bloodReorderModal" tabindex="-1" aria-labelledby="bloodViewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bloodViewModalLabel">View Blood Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="viewRequestForm">
                    <input type="hidden" id="editRequestId">
                    <!-- Patient Name -->
                    <div class="mb-3">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control" id="reorderPatientName" readonly>
                    </div>

                    <!-- Age and Gender -->
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control" id="reorderAge" readonly>
                        </div>
                        <div class="col">
                            <label class="form-label">Gender</label>
                            <input type="text" class="form-control" id="reorderGender" readonly>
                        </div>
                    </div>

                    <!-- Diagnosis -->
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" class="form-control" id="reorderDiagnosis" readonly>
                    </div>

                    <!-- Blood Type and RH -->
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Blood Type</label>
                            <input type="text" class="form-control" id="reorderBloodType" readonly>
                        </div>
                        <div class="col">
                            <label class="form-label">RH</label>
                            <input type="text" class="form-control" id="reorderRH" readonly>
                        </div>
                    </div>

                    <!-- Component -->
                    <div class="mb-3">
                        <label class="form-label">Component</label>
                        <input type="text" class="form-control" id="reorderComponent" readonly>
                    </div>

                    <!-- Number of Units -->
                    <div class="mb-3">
                        <label class="form-label">Number of Units</label>
                        <input type="number" class="form-control" id="reorderUnits" readonly>
                    </div>

                    <!-- When Needed -->
                    <div class="mb-3">
                        <label class="form-label">When Needed</label>
                        <input type="text" class="form-control" id="reorderWhenNeeded" readonly>
                    </div>

                    <!-- Scheduled Date & Time -->
                    <div id="reorderScheduleDateTime" class="mb-3 d-none">
                        <label class="form-label">Scheduled Date & Time</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="reorderScheduledDateTime" readonly>
                            <span class="input-group-text bg-light scheduled-label"></span>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="#" class="btn btn-primary" id="printButton">
                            <i class="fas fa-print me-1"></i> Print Request
                        </a>
                    </div>
                </form>
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
            modal.querySelector("#reorderComponent").value = "Whole Blood"; // Replace with actual component if available
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

            // Handle view button clicks
            document.querySelectorAll('.view-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Get data from button attributes
                    const data = this.dataset;
                    
                    // Store request ID for printing
                    document.getElementById('editRequestId').value = data.requestId;
                    
                    // Populate view modal with request data
                    document.getElementById('reorderPatientName').value = data.patientName;
                    document.getElementById('reorderAge').value = data.patientAge;
                    document.getElementById('reorderGender').value = data.patientGender;
                    document.getElementById('reorderDiagnosis').value = data.patientDiagnosis;
                    document.getElementById('reorderBloodType').value = data.bloodType;
                    document.getElementById('reorderRH').value = data.rhFactor;
                    document.getElementById('reorderComponent').value = data.component;
                    document.getElementById('reorderUnits').value = data.units;
                    document.getElementById('reorderWhenNeeded').value = data.whenNeeded === 'ASAP' ? 'ASAP' : 'Scheduled';

                    // Handle scheduled date & time
                    const whenNeeded = new Date(data.whenNeeded);
                    const now = new Date();
                    if (whenNeeded > now) {
                        document.getElementById('reorderScheduleDateTime').classList.remove('d-none');
                        document.getElementById('reorderScheduledDateTime').value = formatDate(whenNeeded);
                    } else {
                        document.getElementById('reorderScheduleDateTime').classList.add('d-none');
                    }

                    // Update print button href
                    document.getElementById('printButton').href = `print-blood-requests.php?request_id=${data.requestId}`;

                    // Show the view modal
                    new bootstrap.Modal(document.getElementById('bloodReorderModal')).show();
                });
            });

            // Format date helper function
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
            // Search functionality - handles both search bars
            const requestSearchBar = document.getElementById('requestSearchBar');
            const sidebarSearchBar = document.getElementById('sidebarSearchBar');
            
            // Function to perform search
            function performSearch(searchText) {
                const table = document.getElementById('requestTable');
                const rows = table.getElementsByTagName('tr');

                for (let row of rows) {
                    // Skip header row
                    if (row.querySelector('th')) continue;
                    
                    // Get the patient name (2nd column)
                    const patientNameCell = row.querySelector('td:nth-child(2)');
                    
                    if (patientNameCell) {
                        const patientName = patientNameCell.textContent.toLowerCase();
                        
                        // Show row if patient name contains search text, hide otherwise
                        if (patientName.includes(searchText.toLowerCase())) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                }
            }
            
            // Event listeners for main search bar
            if (requestSearchBar) {
                requestSearchBar.addEventListener('keyup', function() {
                    performSearch(this.value);
                    // Sync with sidebar search
                    if (sidebarSearchBar) sidebarSearchBar.value = this.value;
                });
                
                requestSearchBar.addEventListener('focus', function() {
                    this.style.boxShadow = '0 0 0 0.2rem rgba(148, 16, 34, 0.25)';
                });

                requestSearchBar.addEventListener('blur', function() {
                    this.style.boxShadow = 'none';
                });
            }
            
            // Event listeners for sidebar search bar
            if (sidebarSearchBar) {
                sidebarSearchBar.addEventListener('keyup', function() {
                    performSearch(this.value);
                    // Sync with main search
                    if (requestSearchBar) requestSearchBar.value = this.value;
                });
                
                sidebarSearchBar.addEventListener('focus', function() {
                    this.style.boxShadow = '0 0 0 0.2rem rgba(148, 16, 34, 0.25)';
                });

                sidebarSearchBar.addEventListener('blur', function() {
                    this.style.boxShadow = 'none';
                });
            }
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