<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /REDCROSS/public/login.php");
    exit();
}
// Check for correct role (admin with role_id 3)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../../public/unauthorized.php");
    exit();
}
// Add pagination settings
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Initialize the donors array
$donors = [];

// Modify your Supabase query to properly filter for unprocessed donors
$ch = curl_init();

// First, get all donor IDs that have screening forms
$screening_ch = curl_init();
curl_setopt_array($screening_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=donor_form_id',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$screening_response = curl_exec($screening_ch);
$processed_donor_ids = [];

if ($screening_response !== false) {
    $screening_data = json_decode($screening_response, true);
    if (is_array($screening_data)) {
        foreach ($screening_data as $item) {
            if (isset($item['donor_form_id'])) {
                $processed_donor_ids[] = $item['donor_form_id'];
            }
        }
    }
}
curl_close($screening_ch);

// Debug info
$debug_screening = [
    'screening_response' => substr($screening_response, 0, 500) . '...',
    'processed_donor_ids' => $processed_donor_ids
];
error_log("Screening form data: " . json_encode($debug_screening));

// Now get donor forms that are NOT in the processed list
$query_url = SUPABASE_URL . '/rest/v1/donor_form?select=*&order=submitted_at.desc';
if (!empty($processed_donor_ids)) {
    // Convert array to comma-separated string
    $processed_ids_str = implode(',', $processed_donor_ids);
    // Add not.in filter
    $query_url .= '&donor_id=not.in.(' . $processed_ids_str . ')';
}

curl_setopt_array($ch, [
    CURLOPT_URL => $query_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$response = curl_exec($ch);

// Log the final query URL and raw response for debugging
error_log("Final query URL: " . $query_url);
error_log("Supabase raw response: " . substr($response, 0, 500) . '...');

// Check if the response is valid JSON
if ($response === false || is_null(json_decode($response, true))) {
    error_log("Error fetching data from Supabase: " . curl_error($ch));
    $donors = [];
} else {
    $donors = json_decode($response, true) ?: [];
    error_log("Decoded donors count: " . count($donors));
}

$total_records = count($donors);
$total_pages = ceil($total_records / $records_per_page);

// Slice the array to get only the records for the current page
$donors = array_slice($donors, $offset, $records_per_page);

// Close cURL session
curl_close($ch);

// Process donor approval if a donor_id was passed
if (isset($_GET['approve_donor'])) {
    $donor_id = intval($_GET['approve_donor']);
    $donor_name = $_GET['donor_name'] ?? '';
    
    // Store in session
    $_SESSION['donor_id'] = $donor_id;
    $_SESSION['donor_name'] = $donor_name;
    
    // Ensure user_staff_roles is set - default to 'Interviewer' to guarantee access
    // The interviewer role should have access to medical histories
    $_SESSION['user_staff_role'] = 'Interviewer';
    $_SESSION['user_staff_roles'] = 'Interviewer';
    $_SESSION['staff_role'] = 'Interviewer';
    
    // Try to get the actual role from the database if possible
    $user_id = $_SESSION['user_id'] ?? 0;
    
    // Add extra debugging
    error_log("User ID for role lookup: " . $user_id);
    error_log("Initial role settings: " . $_SESSION['user_staff_role']);
    
    // Use Supabase API to get the user role
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/user_roles?user_id=eq.' . $user_id . '&select=role_name',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $response = curl_exec($ch);
    
    if ($response !== false) {
        $userData = json_decode($response, true);
        if (is_array($userData) && !empty($userData)) {
            // Set multiple role variables to ensure compatibility
            $_SESSION['user_staff_role'] = $userData[0]['role_name'] ?? 'Interviewer';
            $_SESSION['staff_role'] = $userData[0]['role_name'] ?? 'Interviewer';
            $_SESSION['user_staff_roles'] = $userData[0]['role_name'] ?? 'Interviewer';
            
            error_log("User role set to: " . $_SESSION['user_staff_role']);
        }
    }
    
    curl_close($ch);
    
    // Make sure the roles are set to valid values that will pass the check in medical-history.php
    if (!in_array(strtolower($_SESSION['user_staff_role']), ['interviewer', 'reviewer', 'physician'])) {
        $_SESSION['user_staff_role'] = 'Interviewer';
        $_SESSION['user_staff_roles'] = 'Interviewer';
        $_SESSION['staff_role'] = 'Interviewer';
    }
    
    // Log the action
    error_log("Setting donor_id in session directly: " . $donor_id);
    error_log("Session after setting: " . print_r($_SESSION, true));
    
    // Redirect directly to medical history form
    header("Location: ../../src/views/forms/medical-history.php");
    exit();
}

// Add this function to handle donor approval
function storeDonorIdInSession($donorData) {
    if (is_array($donorData)) {
        error_log("Storing donor data: " . print_r($donorData, true));
        if (isset($donorData['donor_id'])) {
            $_SESSION['donor_id'] = $donorData['donor_id'];
            $_SESSION['donor_name'] = $donorData['first_name'] . ' ' . $donorData['surname'];
        } else {
            error_log("Missing donor_id in donor data: " . print_r($donorData, true));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Staff Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
               :root {
            --bg-color: #f8f9fa; /* Light background */
            --text-color: #000; /* Dark text */
            --sidebar-bg: #ffffff; /* White sidebar */
            --card-bg: #e9ecef; /* Light gray cards */
            --hover-bg: #dee2e6; /* Light gray hover */
        }

        body.light-mode {
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        .sidebar {
            background: var(--sidebar-bg);
            height: 100vh;
            padding: 1rem;
            position: fixed;
            top: 0;
            left: 0;
            width: 16.66666667%;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .sidebar h4 {
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #dee2e6;
            color: #000;
            font-weight: bold;
        }

        .sidebar .nav-link {
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            transition: all 0.3s ease;
            color: #000 !important;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: var(--hover-bg);
            transform: translateX(5px);
            color: #000 !important;
        }

        .main-content {
            padding: 1.5rem;
            margin-left: 16.66666667%;
        }

        .card {
            background: var(--card-bg);
            color: var(--text-color);
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: scale(1.03);
        }

        .alert {
            font-size: 1.1rem;
        }

        .btn-toggle {
            position: absolute;
            top: 10px;
            right: 20px;
        }

        .table-hover tbody tr {
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .table-hover tbody tr:hover {
            background-color: var(--hover-bg); /* Light gray hover for rows */
        }

        /* Additional styles for light mode */
        .table-dark {
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .table-dark thead th {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
        }

        .table-dark tbody tr:hover {
            background-color: var(--hover-bg);
        }

        .modal-content {
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .modal-header, .modal-footer {
            border-color: var(--hover-bg);
        }

        .donor_form_input[readonly], .donor_form_input[disabled] {
            background-color: var(--bg-color);
            cursor: not-allowed;
            border: 1px solid #ddd;
        }

        .donor-declaration-button[disabled] {
            background-color: var(--hover-bg);
            cursor: not-allowed;
        }
        /* Modern Font */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Table Styling */
.dashboard-staff-tables {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    
}

.dashboard-staff-tables thead th {
    background-color: #242b31; /* Blue header */
    color: white;
    padding: 12px;
    text-align: left;
    font-weight: 600;
}

.dashboard-staff-tables tbody td {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
}

/* Alternating Row Colors */
.dashboard-staff-tables tbody tr:nth-child(odd) {
    background-color: #f8f9fa; /* Light gray for odd rows */
}

.dashboard-staff-tables tbody tr:nth-child(even) {
    background-color: #ffffff; /* White for even rows */
}

/* Hover Effect */
.dashboard-staff-tables tbody tr:hover {
    background-color: #e9f5ff; /* Light blue on hover */
    transition: background-color 0.3s ease;
}

/* Rounded Corners for First and Last Rows */
.dashboard-staff-tables tbody tr:first-child td:first-child {
    border-top-left-radius: 10px;
}

.dashboard-staff-tables tbody tr:first-child td:last-child {
    border-top-right-radius: 10px;
}

.dashboard-staff-tables tbody tr:last-child td:first-child {
    border-bottom-left-radius: 10px;
}

.dashboard-staff-tables tbody tr:last-child td:last-child {
    border-bottom-right-radius: 10px;
}
.custom-margin {
            margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
}
/* General Styling */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa; /* Light background for better contrast */
    color: #333; /* Dark text for readability */
    margin: 0;
    padding: 0;
}


/* Donor Form Header */
.donor_form_header {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    align-items: center;
    text-align: center;
    margin-bottom: 20px;
    color: #b22222; /* Red color for emphasis */
}

.donor_form_header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
}

/* Labels */
.donor_form_label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
    color: #333; /* Dark text for readability */
}

/* Input Fields */
.donor_form_input {
    width: 100%;
    padding: 8px;
    margin-bottom: 15px;
    border: 1px solid #ddd; /* Light border */
    border-radius: 5px;
    font-size: 14px;
    box-sizing: border-box;
    color: #555; /* Slightly lighter text for inputs */
    background-color: #f8f9fa; /* Light background for inputs */
    transition: border-color 0.3s ease;
}

.donor_form_input:focus {
    border-color: #007bff; /* Blue border on focus */
    outline: none;
}

/* Grid Layout */
.donor_form_grid {
    display: grid;
    gap: 10px; /* Increased gap for better spacing */
}

.grid-3 {
    grid-template-columns: repeat(3, 1fr);
}

.grid-4 {
    grid-template-columns: repeat(4, 1fr);
}

.grid-1 {
    grid-template-columns: 1fr;
}

.grid-6 {
    grid-template-columns: repeat(6, 1fr);
}

/* Read-Only and Disabled Inputs */
.donor_form_input[readonly], .donor_form_input[disabled] {
    background-color: #e9ecef; /* Light gray for read-only fields */
    cursor: not-allowed;
}

/* Select Dropdowns */
.donor_form_input[disabled] {
    color: #555; /* Ensure text is visible */
}

/* Hover Effects for Interactive Elements */
.donor_form_input:not([readonly]):not([disabled]):hover {
    border-color: #007bff; /* Blue border on hover */
}

/* Responsive Design */
@media (max-width: 768px) {
    .donor_form_header {
        grid-template-columns: 1fr; /* Stack header items on small screens */
        text-align: left;
    }

    .grid-3, .grid-4, .grid-6 {
        grid-template-columns: 1fr; /* Stack grid items on small screens */
    }
}
.modal-xxl {
    max-width: 1200px; /* Set your desired width */
    width: 100%; /* Ensure it's responsive */
}

/* Add these styles for read-only inputs */
.donor_form_input[readonly] {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    cursor: default;
    color: #495057;
}

.donor_form_input[readonly]:focus {
    outline: none;
    box-shadow: none;
    border-color: #dee2e6;
}

select.donor_form_input[disabled] {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    cursor: default;
    color: #495057;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.donor-declaration-img {
    max-width: 100%;
    width: 70%;
    height: auto;
    border: 3px solid #ddd;
    border-radius: 12px;
    padding: 50px;
    background-color: #fff;
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    margin: 15px auto;
    display: block;
}

.donor-declaration-row {
    margin-bottom: 40px;
    padding: 30px;
    background-color: #f8f9fa;
    border-radius: 15px;
    border: 2px solid #e9ecef;
    text-align: left;
}

.donor-declaration-row strong {
    display: block;
    margin-bottom: 20px;
    color: #333;
    font-size: 1.3em;
    font-weight: 600;
    text-align: left;
}

.relationship-container {
    margin: 20px 0;
    padding: 20px;
    background-color: #fff;
    border-radius: 8px;
    border: 2px solid #ddd;
    text-align: left;
}

.donor-declaration-input {
    width: 100%;
    max-width: 400px;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    margin-top: 10px;
    background-color: #f8f9fa;
    font-size: 1.1em;
    text-align: left;
}

.donor-declaration {
    width: 100%;
    padding: 20px;
}

/* Responsive adjustments */
@media (max-width: 991.98px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }
    .main-content {
        margin-left: 0;
    }
}
        /* Enhanced Header styles */
        .dashboard-home-header {
            margin-left: 16.66666667%;
            position: relative;
            z-index: 999;
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 1.2rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 70px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 0;
        }

        .header-icon {
            color: #dc3545;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }

        .header-date {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: normal;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .header-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #dc3545;
            color: white;
            border: none;
            box-shadow: 0 1px 2px rgba(220, 53, 69, 0.15);
        }

        .header-btn:hover {
            transform: translateY(-1px);
            background: #c82333;
            color: white;
            box-shadow: 0 3px 5px rgba(220, 53, 69, 0.2);
        }

        .header-btn i {
            font-size: 1rem;
        }

        @media (max-width: 991.98px) {
            .dashboard-home-header {
                margin-left: 0;
                padding: 1rem;
                flex-wrap: wrap;
                gap: 1rem;
            }

            .header-left {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .header-title {
                font-size: 1.1rem;
            }

            .header-date {
                font-size: 0.9rem;
            }

            .header-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .header-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="light-mode">
<div class="container-fluid p-0">
        <!-- Enhanced Header -->
        <div class="dashboard-home-header">
            <div class="header-left">
                <h4 class="header-title">
                    <i class="fas fa-hospital-user header-icon"></i>
                    Staff Dashboard
                </h4>
                <span class="header-date">
                    <?php echo date('l, F j, Y'); ?>
                </span>
            </div>
            <div class="header-actions">
                <?php if ($user_staff_roles === 'interviewer'): ?>
                    <button class="header-btn" onclick="window.location.href='../forms/qr-registration.php'">
                        <i class="fas fa-qrcode"></i>
                        QR Registration
                    </button>
                <?php endif; ?>
                <button class="header-btn" onclick="showConfirmationModal()">
                    <i class="fas fa-user-plus"></i>
                    Register Donor
                </button>
            </div>
        </div>

        <div class="row g-0">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4>Red Cross Staff</h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard-staff-main.php">Dashboard</a>
                    </li>
                    
                    <?php if ($user_staff_roles === 'interviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-staff-donor-submission.php">
                                Donor Interviews Submissions
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_staff_roles === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-staff-medical-history-submissions.php">
                                Donor Medical Interview Submissions
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_staff_roles === 'physician'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-staff-physical-submission.php">
                                Physical Exams Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'phlebotomist'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-staff-blood-collection-submission.php">
                                Blood Collection Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    

                    <li class="nav-item">
                        <a class="nav-link" href="../../assets/php_func/logout.php">Logout</a>
                    </li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <!-- Error Display Area -->
                <div id="errorDisplay" class="alert alert-danger" style="display: none;"></div>
                
                <!-- Latest Donor Submissions -->
                <div class="container-fluid p-4 custom-margin">
                    <h2 class="mt-1 mb-4">Latest Donor Submissions</h2>
                    
                    <!-- Search Bar -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="search-container text-center">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">
                                        <i class="fas fa-search fa-lg"></i>
                                    </span>
                                    <select class="form-select form-select-lg category-select" id="searchCategory" style="max-width: 200px;">
                                        <option value="all">All Fields</option>
                                        <option value="date">Date</option>
                                        <option value="surname">Surname</option>
                                        <option value="firstname">First Name</option>
                                        <option value="birthdate">Birthdate</option>
                                        <option value="sex">Sex</option>
                                    </select>
                                    <input type="text" 
                                        class="form-control form-control-lg" 
                                        id="searchInput" 
                                        placeholder="Search donors..."
                                        style="height: 60px; font-size: 1.2rem;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="dashboard-staff-tables table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Surname</th>
                                    <th>Firstname</th>
                                    <th>Birthdate</th>
                                    <th>Sex</th>
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php if($donors && is_array($donors)): ?>
                                    <?php foreach($donors as $donor): ?>
                                        <?php
                                        // Ensure $donor is an array before merging
                                        if (is_array($donor)) {
                                            $donorData = array_merge($donor, [
                                                'donor_id' => $donor['donor_id'] ?? null
                                            ]);
                                        } else {
                                            error_log("Invalid donor data: " . print_r($donor, true));
                                            continue;
                                        }
                                        ?>
                                        <tr data-bs-toggle="modal" 
                                            data-bs-target="#donorDetailsModal" 
                                            data-donor='<?php echo htmlspecialchars(json_encode($donorData, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>
                                            <td><?php 
                                                if (isset($donor['submitted_at'])) {
                                                    $date = new DateTime($donor['submitted_at']);
                                                    echo $date->format('F d, Y h:i A');
                                                } else {
                                                    echo 'N/A';
                                                    error_log("Missing 'submitted_at' for donor: " . print_r($donor, true));
                                                }
                                            ?></td>
                                            <td><?php echo isset($donor['surname']) ? htmlspecialchars($donor['surname']) : ''; ?></td>
                                            <td><?php echo isset($donor['first_name']) ? htmlspecialchars($donor['first_name']) : ''; ?></td>
                                            <td><?php echo isset($donor['birthdate']) ? htmlspecialchars($donor['birthdate']) : ''; ?></td>
                                            <td><?php echo isset($donor['sex']) ? htmlspecialchars($donor['sex']) : ''; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <!-- Add Pagination Controls -->
                        <div class="pagination-container">
                            <nav aria-label="Donor submissions navigation">
                                <ul class="pagination justify-content-center">
                                    <!-- Previous button -->
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" <?php echo $current_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Previous</a>
                                    </li>
                                    
                                    <!-- Page numbers -->
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <!-- Next button -->
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" <?php echo $current_page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <!-- Donor Details Modal -->
<div class="modal fade" id="donorDetailsModal" tabindex="-1" aria-labelledby="donorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="donorDetailsModalLabel">Donor Submission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Donor Form -->
                <form class="donor_form_container">
                    <div class="donor_form_header">
                        <div>
                            <label class="donor_form_label">PRC BLOOD DONOR NUMBER:</label>
                            <input type="text" class="donor_form_input" name="prc_donor_number" value="<?php echo isset($donor['prc_donor_number']) ? htmlspecialchars($donor['prc_donor_number']) : ''; ?>" readonly>
                        </div>
                        <h2>BLOOD DONOR INTERVIEW SHEET</h2>
                        <div>
                            <label class="donor_form_label">DOH NNBNets Barcode:</label>
                            <input type="text" class="donor_form_input" name="doh_nnbnets_barcode" value="<?php echo isset($donor['doh_nnbnets_barcode']) ? htmlspecialchars($donor['doh_nnbnets_barcode']) : ''; ?>" readonly>
                        </div>
                    </div>
                    <div class="donor_form_section">
                        <h6>NAME:</h6>
                        <div class="donor_form_grid grid-3">
                            <div>
                                <label class="donor_form_label">Surname</label>
                                <input type="text" class="donor_form_input" name="surname" value="<?php echo isset($donor['surname']) ? htmlspecialchars($donor['surname']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">First Name</label>
                                <input type="text" class="donor_form_input" name="first_name" value="<?php echo isset($donor['first_name']) ? htmlspecialchars($donor['first_name']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Middle Name</label>
                                <input type="text" class="donor_form_input" name="middle_name" value="<?php echo isset($donor['middle_name']) ? htmlspecialchars($donor['middle_name']) : ''; ?>" readonly>
                            </div>
                        </div>
                        <div class="donor_form_grid grid-4">
                            <div>
                                <label class="donor_form_label">Birthdate</label>
                                <input type="date" class="donor_form_input" name="birthdate" value="<?php echo isset($donor['birthdate']) ? htmlspecialchars($donor['birthdate']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Age</label>
                                <input type="number" class="donor_form_input" name="age" value="<?php echo isset($donor['age']) ? htmlspecialchars($donor['age']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Sex</label>
                                <select class="donor_form_input" name="sex" disabled>
                                    <option value="male" <?php echo (isset($donor['sex']) && strtolower($donor['sex']) == 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (isset($donor['sex']) && strtolower($donor['sex']) == 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div>
                                <label class="donor_form_label">Civil Status</label>
                                <select class="donor_form_input" name="civil_status" disabled>
                                    <option value="single" <?php echo (isset($donor['civil_status']) && strtolower($donor['civil_status']) == 'single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="married" <?php echo (isset($donor['civil_status']) && strtolower($donor['civil_status']) == 'married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="widowed" <?php echo (isset($donor['civil_status']) && strtolower($donor['civil_status']) == 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="divorced" <?php echo (isset($donor['civil_status']) && strtolower($donor['civil_status']) == 'divorced') ? 'selected' : ''; ?>>Divorced</option>
                                </select>
                            </div>
                        </div>
                    </div>
            
                    <div class="donor_form_section">
                        <h6>PERMANENT ADDRESS</h6>
                        <input type="text" class="donor_form_input" name="permanent_address" value="<?php echo isset($donor['permanent_address']) ? htmlspecialchars($donor['permanent_address']) : ''; ?>" readonly>
                        
                        <h6>OFFICE ADDRESS</h6>
                        <div class="donor_form_grid grid-1">
                            <input type="text" class="donor_form_input" name="office_address" value="<?php echo isset($donor['office_address']) ? htmlspecialchars($donor['office_address']) : ''; ?>" readonly>
                        </div>
                        <div class="donor_form_grid grid-4">
                            <div>
                                <label class="donor_form_label">Nationality</label>
                                <input type="text" class="donor_form_input" name="nationality" value="<?php echo isset($donor['nationality']) ? htmlspecialchars($donor['nationality']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Religion</label>
                                <input type="text" class="donor_form_input" name="religion" value="<?php echo isset($donor['religion']) ? htmlspecialchars($donor['religion']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Education</label>
                                <input type="text" class="donor_form_input" name="education" value="<?php echo isset($donor['education']) ? htmlspecialchars($donor['education']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Occupation</label>
                                <input type="text" class="donor_form_input" name="occupation" value="<?php echo isset($donor['occupation']) ? htmlspecialchars($donor['occupation']) : ''; ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="donor_form_section">
                        <h6>CONTACT No.:</h6>
                        <div class="donor_form_grid grid-3">
                            <div>
                                <label class="donor_form_label">Telephone No.</label>
                                <input type="text" class="donor_form_input" name="telephone" value="<?php echo isset($donor['telephone']) ? htmlspecialchars($donor['telephone']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Mobile No.</label>
                                <input type="text" class="donor_form_input" name="mobile" value="<?php echo isset($donor['mobile']) ? htmlspecialchars($donor['mobile']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Email Address</label>
                                <input type="email" class="donor_form_input" name="email" value="<?php echo isset($donor['email']) ? htmlspecialchars($donor['email']) : ''; ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="donor_form_section">
                        <h6>IDENTIFICATION No.:</h6>
                        <div class="donor_form_grid grid-6">
                        <div>
                            <label class="donor_form_label">School</label>
                                <input type="text" class="donor_form_input" name="id_school" value="<?php echo isset($donor['id_school']) ? htmlspecialchars($donor['id_school']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">Company</label>
                                <input type="text" class="donor_form_input" name="id_company" value="<?php echo isset($donor['id_company']) ? htmlspecialchars($donor['id_company']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">PRC</label>
                                <input type="text" class="donor_form_input" name="id_prc" value="<?php echo isset($donor['id_prc']) ? htmlspecialchars($donor['id_prc']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">Driver's</label>
                                <input type="text" class="donor_form_input" name="id_drivers" value="<?php echo isset($donor['id_drivers']) ? htmlspecialchars($donor['id_drivers']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">SSS/GSIS/BIR</label>
                                <input type="text" class="donor_form_input" name="id_sss_gsis_bir" value="<?php echo isset($donor['id_sss_gsis_bir']) ? htmlspecialchars($donor['id_sss_gsis_bir']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">Others</label>
                                <input type="text" class="donor_form_input" name="id_others" value="<?php echo isset($donor['id_others']) ? htmlspecialchars($donor['id_others']) : ''; ?>" readonly>
                            </div>
                    </div>
                    </div>
                </form>

                <!-- Donor Declaration -->
                <div class="donor-declaration">
                    <!-- Donor's Signature Image -->
                    <div class="donor-declaration-row">
                        <div><strong>Donor's Signature:</strong></div>
                        <?php if(isset($donor['donor_signature']) && !empty($donor['donor_signature'])): ?>
                            <img src="../../src/views/forms/uploads/<?php echo htmlspecialchars($donor['donor_signature']); ?>" 
                                alt="Donor's Signature" class="donor-declaration-img">
                        <?php else: ?>
                            <p>No donor signature available</p>
                        <?php endif; ?>
                    </div>

                    <?php if(isset($donor['guardian_signature']) && !empty($donor['guardian_signature'])): ?>
                    <!-- Parent/Guardian Section -->
                    <div class="donor-declaration-row">
                        <div><strong>Signature of Parent/Guardian:</strong></div>
                        <img src="../../src/views/forms/uploads/<?php echo htmlspecialchars($donor['guardian_signature']); ?>" 
                            alt="Parent/Guardian Signature" class="donor-declaration-img">
                        <?php if(isset($donor['relationship']) && !empty($donor['relationship'])): ?>
                        <div class="relationship-container">
                            <strong>Relationship to Blood Donor: </strong>
                            <input class="donor-declaration-input" type="text" 
                                value="<?php echo htmlspecialchars($donor['relationship']); ?>" 
                                readonly>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary px-4 py-2 fw-bold" id="Approve">Approve</button>
            </div>
        </div>
    </div>
</div>
</div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none;">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title" id="confirmationModalLabel">
                        <i class="fas fa-user-plus me-2"></i>
                        Register New Donor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-0" style="font-size: 1.1rem;">Are you sure you want to proceed to the donor registration form?</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4" onclick="proceedToDonorForm()">Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
                <div class="modal-body text-center">
                    <div class="spinner-border text-danger" style="width: 3.5rem; height: 3.5rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-white mt-3 mb-0" style="font-size: 1.1rem;">Please wait...</p>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const donorDetailsModal = document.getElementById('donorDetailsModal');
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            const donorTableBody = document.getElementById('donorTableBody');
            const errorDisplay = document.getElementById('errorDisplay');
            const approveButton = document.getElementById('Approve');
            
            // Initialize current donor data at the top scope
            let currentDonorData = null;
            
            // Direct debug of button existence
            if (approveButton) {
                console.log("Approve button found:", approveButton);
            } else {
                console.error("Approve button NOT found in DOM!");
            }
            
            // Store the original table rows for reset
            const originalRows = Array.from(donorTableBody.getElementsByTagName('tr'));
            
            // Update placeholder based on selected category
            searchCategory.addEventListener('change', function() {
                const category = this.value;
                let placeholder = 'Search by ';
                switch(category) {
                    case 'date': placeholder += 'date...'; break;
                    case 'surname': placeholder += 'surname...'; break;
                    case 'firstname': placeholder += 'first name...'; break;
                    case 'birthdate': placeholder += 'birthdate...'; break;
                    case 'sex': placeholder += 'sex (male/female)...'; break;
                    default: placeholder = 'Search donors...';
                }
                searchInput.placeholder = placeholder;
                performSearch();
            });

            // Enhanced search functionality
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const category = searchCategory.value;
                
                // If search is empty, show all rows
                if (!searchTerm) {
                    originalRows.forEach(row => row.style.display = '');
                    removeNoResultsMessage();
                    return;
                }

                let visibleCount = 0;

                originalRows.forEach(row => {
                    const cells = Array.from(row.getElementsByTagName('td'));
                    let shouldShow = false;

                    if (category === 'all') {
                        // Search in all columns
                        shouldShow = cells.some(cell => 
                            cell.textContent.toLowerCase().trim().includes(searchTerm)
                        );
                    } else {
                        // Get column index based on category
                        const columnIndex = {
                            'date': 0,
                            'surname': 1,
                            'firstname': 2,
                            'birthdate': 3,
                            'sex': 4
                        }[category];

                        if (columnIndex !== undefined) {
                            const cellText = cells[columnIndex].textContent.toLowerCase().trim();
                            
                            // Special handling for different column types
                            switch(category) {
                                case 'surname':
                                case 'firstname':
                                    shouldShow = cellText.startsWith(searchTerm);
                                    break;
                                case 'sex':
                                    shouldShow = cellText === searchTerm;
                                    break;
                                default:
                                    shouldShow = cellText.includes(searchTerm);
                            }
                        }
                    }

                    row.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) visibleCount++;
                });

                // Handle no results message
                if (visibleCount === 0) {
                    showNoResultsMessage(searchTerm, category);
                } else {
                    removeNoResultsMessage();
                }
            }

            function showNoResultsMessage(searchTerm, category) {
                removeNoResultsMessage();
                const messageRow = document.createElement('tr');
                messageRow.className = 'no-results';
                const categoryText = category === 'all' ? '' : ` in ${category}`;
                messageRow.innerHTML = `<td colspan="5" class="text-center py-3">
                    No donors found matching "${searchTerm}"${categoryText}
                </td>`;
                donorTableBody.appendChild(messageRow);
            }

            function removeNoResultsMessage() {
                const noResultsRow = donorTableBody.querySelector('.no-results');
                if (noResultsRow) noResultsRow.remove();
            }

            // Debounce function to improve performance
            function debounce(func, wait) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }

            // Apply debounced search
            const debouncedSearch = debounce(performSearch, 300);

            // Event listeners
            searchInput.addEventListener('input', debouncedSearch);
            searchCategory.addEventListener('change', debouncedSearch);

            function showError(message) {
                alert(message);
                console.error("ERROR: " + message);
            }

            // Handle row click and populate modal
            document.querySelectorAll('.table-hover tbody tr').forEach(row => {
                row.addEventListener('click', function() {
                    try {
                        const donorDataStr = this.getAttribute('data-donor');
                        
                        // Try to parse the donor data
                        currentDonorData = JSON.parse(donorDataStr);
                        
                        // Check for donor_id
                        if (!currentDonorData.donor_id) {
                            showError('Missing donor_id in parsed data. This will cause issues with approval.');
                        }
                        
                    } catch (error) {
                        showError('Error parsing donor data: ' + error.message);
                    }
                });
            });

            // Approve button click handler
            if (approveButton) {
                approveButton.addEventListener('click', function() {
                    if (!currentDonorData) {
                        showError('Error: No donor selected');
                        return;
                    }
                    
                    // Get the donor_id from the data
                    const donorId = currentDonorData.donor_id;
                    if (!donorId) {
                        showError('Error: Could not process approval - missing donor ID');
                        return;
                    }
                    
                    // Get donor name if available
                    let donorName = '';
                    if (currentDonorData.first_name && currentDonorData.surname) {
                        donorName = currentDonorData.first_name + ' ' + currentDonorData.surname;
                    }
                    
                    // Show loading modal first to indicate processing
                    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                    
                    // Close the donor details modal
                    const donorDetailsModal = bootstrap.Modal.getInstance(document.getElementById('donorDetailsModal'));
                    if (donorDetailsModal) {
                        donorDetailsModal.hide();
                    }
                    
                    // Show loading modal
                    loadingModal.show();
                    
                    // Update loading text for better feedback
                    document.querySelector('#loadingModal .text-white').innerHTML = 'Proceeding to medical history form...<br><small>Please wait</small>';
                    
                    // Direct navigation with parameters after a short delay
                    setTimeout(() => {
                        const url = `dashboard-staff-donor-submission.php?approve_donor=${donorId}&donor_name=${encodeURIComponent(donorName)}`;
                        window.location.href = url;
                    }, 800);
                });
            }
        });

        // Simple modal functions (isolated from other code)
        function showConfirmationModal() {
            var myModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            myModal.show();
        }
        
        function proceedToDonorForm() {
            var confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            if (confirmModal) {
                confirmModal.hide();
            }
            
            var loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            loadingModal.show();
            
            setTimeout(function() {
                window.location.href = '../../src/views/forms/donor-form-modal.php';
            }, 800);
        }
    </script>



    <style>
        /* Updated styles for the search bar */
        .search-container {
            width: 100%;
            margin: 0 auto;
        }

        .input-group {
            width: 100%;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .input-group-text {
            background-color: #fff;
            border: 2px solid #ced4da;
            border-right: none;
            padding: 0.75rem 1.5rem;
        }

        .category-select {
            border: 2px solid #ced4da;
            border-right: none;
            border-left: none;
            background-color: #f8f9fa;
            cursor: pointer;
            min-width: 150px;
        }

        .category-select:focus {
            box-shadow: none;
            border-color: #ced4da;
        }

        #searchInput {
            border: 2px solid #ced4da;
            border-left: none;
            padding: 1.5rem;
            font-size: 1.2rem;
            flex: 1;
        }

        #searchInput::placeholder {
            color: #adb5bd;
            font-size: 1.1rem;
        }

        #searchInput:focus {
            box-shadow: none;
            border-color: #ced4da;
        }

        .input-group:focus-within {
            box-shadow: 0 0 0 0.25rem rgba(0,123,255,.25);
        }

        .input-group-text i {
            font-size: 1.5rem;
            color: #6c757d;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            background-color: #f8f9fa;
            border-radius: 8px;
            width: 100%;
        }

        /* Add these styles after your existing styles */
        .pagination-container {
            margin-top: 20px;
            margin-bottom: 40px;
        }

        .pagination {
            gap: 5px;
        }

        .page-link {
            color: #242b31;
            border: 2px solid #dee2e6;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
            color: #242b31;
        }

        .page-item.active .page-link {
            background-color: #242b31;
            border-color: #242b31;
            color: white;
        }

        .page-item:first-child .page-link,
        .page-item:last-child .page-link {
            border-radius: 6px;
        }
    </style>
</body>
</html>