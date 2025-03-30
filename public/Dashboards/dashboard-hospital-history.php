<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/check_account_hospital_modal.php';

// Function to fetch all blood requests from Supabase, ordered by status (approved first) and date
function fetchBloodRequests($user_id) {
    $ch = curl_init();
    
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    
    // Order by status (approved first) and then by requested_on in descending order
    $url = SUPABASE_URL . '/rest/v1/blood_requests?user_id=eq.' . $user_id . '&order=status.desc,requested_on.desc';
    
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

// Calculate summary statistics
$total_units = 0;
$blood_type_counts = [];
$completed_requests = [];

if (!empty($blood_requests)) {
    foreach ($blood_requests as $request) {
        $total_units += $request['units_requested'];
        
        // Count blood types
        $blood_type = $request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-');
        $blood_type_counts[$blood_type] = ($blood_type_counts[$blood_type] ?? 0) + 1;
        
        // Track completed requests
        if ($request['status'] === 'Completed') {
            $completed_requests[] = $request;
        }
    }
}

// Find most requested blood type
$most_requested_type = !empty($blood_type_counts) ? array_search(max($blood_type_counts), $blood_type_counts) : 'N/A';

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

        /* Card Styling */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            height: 100%;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-title {
            color: var(--redcross-red);
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .card-text {
            color: var(--redcross-dark);
        }

        .card-body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 1.5rem;
        }

        .card .fs-3 {
            font-weight: bold;
            margin: 0.5rem 0;
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

        /* Table Styling */
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(148, 16, 34, 0.05);
        }

        .table thead th {
            background-color: var(--redcross-red);
            color: white;
            border-bottom: none;
            font-size: inherit;
        }

        .table td {
            font-size: inherit;
        }

        /* Status Colors */
        .text-approved {
            color: #006400 !important;
            font-weight: bold;
        }

        .text-danger {
            color: var(--redcross-red) !important;
            font-weight: bold;
        }

        .text-success {
            color: #198754 !important;
            font-weight: bold;
        }

        /* Sidebar Active State */
        .dashboard-home-sidebar a.active, 
        .dashboard-home-sidebar a:hover {
            background-color: #e9ecef;
            color: #333;
            font-weight: bold;
        }

        /* Search Bar */
        .form-control:focus {
            border-color: var(--redcross-red);
            box-shadow: 0 0 0 0.2rem rgba(148, 16, 34, 0.25);
        }

        /* Header Title */
        .card-title.mb-3 {
            color: var(--redcross-red);
            font-weight: bold;
            border-bottom: 2px solid var(--redcross-red);
            padding-bottom: 0.5rem;
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
                            <a class="nav-link" href="dashboard-hospital-requests.php">
                                <i class="fas fa-tint me-2"></i>Your Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-hospital-history.php">
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
                        <h2 class="card-title mb-3">Request History</h2>
                            
                            <!-- Summary Cards -->
                            <div class="row mb-4 g-3">
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Total Units Requested</h5>
                                            <p class="card-text fs-3"><?php echo $total_units; ?> Units</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Most Requested Blood Type</h5>
                                            <p class="card-text fs-3"><?php echo $most_requested_type; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Total Requests</h5>
                                            <p class="card-text fs-3"><?php echo count($blood_requests); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Average Delivery Time</h5>
                                            <p class="card-text fs-3">0 mins</p>
                                            <small class="text-muted">Estimated from tracking data</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                <!-- Table for Request History -->
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
                                            <th>Physician</th>
                                            <th>Requested On</th>
                                            <th>Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody id="requestTable">
                                        <?php if (empty($blood_requests)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center">No blood requests found.</td>
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
                                                    <td class="<?php 
                                                        if ($request['status'] === 'Approved') {
                                                            echo 'text-approved';
                                                        } elseif ($request['status'] === 'Completed') {
                                                            echo 'text-success';
                                                        } elseif ($request['status'] === 'Pending') {
                                                            echo 'text-danger';
                                                        } else {
                                                            echo 'text-warning';
                                                        }
                                                    ?>">
                                                        <?php echo htmlspecialchars($request['status']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($request['physician_name']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($request['requested_on'])); ?></td>
                                                    <td><?php echo $request['last_updated'] ? date('Y-m-d', strtotime($request['last_updated'])) : '-'; ?></td>
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
    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

     <script>
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
            let bloodType = cells[1].textContent;
            let quantity = cells[2].textContent.replace(" Units", ""); // Remove " Units"
            let urgency = cells[3].textContent;
            let status = cells[4].textContent;
            let requestedOn = cells[5].textContent;
            let expectedDelivery = cells[6].textContent;

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
</body>
</html>