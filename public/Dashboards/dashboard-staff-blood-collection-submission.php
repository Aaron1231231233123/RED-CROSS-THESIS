<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';




// Add pagination settings
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Start timing for performance measurement
$start_time = microtime(true);
error_log("Starting blood collection submission query at " . date('H:i:s'));

// STEP 1: Get all blood collection records to identify physical exams that already have blood collection
$blood_collection_url = SUPABASE_URL . '/rest/v1/blood_collection?select=physical_exam_id';
$ch = curl_init($blood_collection_url);
$headers = array(
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY,
    'Accept: application/json'
);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check for errors in the blood collection query
if ($http_code !== 200) {
    error_log("Error fetching blood collections. HTTP code: " . $http_code);
    error_log("Response: " . $response);
    $blood_collections = [];
} else {
    $blood_collections = json_decode($response, true) ?: [];
}

// Create a lookup array of physical_exam_ids that already have blood collection
$collected_physical_exam_ids = [];
foreach ($blood_collections as $collection) {
    if (isset($collection['physical_exam_id']) && !empty($collection['physical_exam_id'])) {
        // Normalize the physical_exam_id to string format for consistent comparison
        $collected_physical_exam_ids[] = (string)$collection['physical_exam_id'];
        
        // Log the type and value for debugging
        error_log("Blood collection physical_exam_id: " . $collection['physical_exam_id'] . 
                  " (Type: " . gettype($collection['physical_exam_id']) . ")");
    }
}

// Dump the collected physical exam IDs for debugging
error_log("Physical exam IDs with blood collection: " . implode(", ", $collected_physical_exam_ids));
error_log("Found " . count($collected_physical_exam_ids) . " physical exams that already have blood collection");

// STEP 2: Get physical examination records with "Accepted" remarks only
$physical_exam_url = SUPABASE_URL . '/rest/v1/physical_examination?remarks=eq.Accepted&select=physical_exam_id,donor_id,remarks,blood_bag_type,donor_form(surname,first_name)&order=created_at.desc';
$ch = curl_init($physical_exam_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check for errors in the physical examination query
if ($http_code !== 200) {
    error_log("Error fetching physical examinations. HTTP code: " . $http_code);
    error_log("Response: " . $response);
    $physical_exams = [];
} else {
    $physical_exams = json_decode($response, true) ?: [];
}

// Log all physical exam IDs for debugging
$all_physical_exam_ids = [];
foreach ($physical_exams as $exam) {
    if (isset($exam['physical_exam_id'])) {
        $all_physical_exam_ids[] = $exam['physical_exam_id'];
    }
}
error_log("All physical exam IDs: " . implode(", ", $all_physical_exam_ids));
error_log("Found " . count($physical_exams) . " physical exams with 'Accepted' status");

// STEP 3: Filter out physical exams that already have blood collection
$available_exams = [];
foreach ($physical_exams as $exam) {
    if (isset($exam['physical_exam_id'])) {
        // Log the type and value for debugging
        error_log("Physical exam ID: " . $exam['physical_exam_id'] . 
                  " (Type: " . gettype($exam['physical_exam_id']) . ")");
        
        // Normalize to string for comparison
        $exam_id_string = (string)$exam['physical_exam_id'];
        
        // Check if this physical_exam_id is in the collected list
        if (!in_array($exam_id_string, $collected_physical_exam_ids)) {
            $available_exams[] = $exam;
            error_log("Including exam ID " . $exam['physical_exam_id'] . " (not collected yet)");
        } else {
            error_log("Excluding exam ID " . $exam['physical_exam_id'] . " (already has blood collection)");
        }
    }
}

error_log("After filtering, " . count($available_exams) . " physical exams remain available for blood collection");

// STEP 4: Prepare pagination
$total_records = count($available_exams);
$total_pages = ceil($total_records / $records_per_page);
$examinations = array_slice($available_exams, $offset, $records_per_page);

// Log execution time
$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
error_log("Query execution time: " . number_format($execution_time, 4) . " seconds");

// Debug output (remove in production)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<pre>";
    echo "Found " . count($physical_exams) . " 'Accepted' physical exams<br>";
    echo "Found " . count($collected_physical_exam_ids) . " physical exams with blood collection<br>";
    echo "Available for collection: " . count($available_exams) . " physical exams<br>";
    echo "Showing " . count($examinations) . " records on this page<br>";
    echo "</pre>";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --bg-color: #f8f9fa;
            --text-color: #000;
            --sidebar-bg: #ffffff;
            --hover-bg: #dee2e6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Sidebar Styles */
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

        /* Main Content */
        .main-content {
            padding: 1.5rem;
            margin-left: 16.66666667%;
        }

        /* Responsive Design */
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

        .card {
            background: var(--card-bg);
            color: var(--text-color);
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: scale(1.03);
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

        .custom-margin {
                    margin: 30px auto;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
                    width: 100%;
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
        /* Loader Animation -- Modal Design */
        .loading-spinner {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 8px solid #ddd;
            border-top: 8px solid #d9534f;
            animation: rotateSpinner 1s linear infinite;
            display: none;
            z-index: 10000;
            transform: translate(-50%, -50%);
        }

        @keyframes rotateSpinner {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        /* Confirmation Modal */
        .confirmation-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 25px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            text-align: center;
            z-index: 9999;
            border-radius: 10px;
            width: 300px;
            display: none;
            opacity: 0;
        }

        /* Fade-in and Fade-out Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translate(-50%, -55%); }
            to { opacity: 1; transform: translate(-50%, -50%); }
        }

        @keyframes fadeOut {
            from { opacity: 1; transform: translate(-50%, -50%); }
            to { opacity: 0; transform: translate(-50%, -55%); }
        }

        .confirmation-modal.show {
            display: block;
            animation: fadeIn 0.3s ease-in-out forwards;
        }

        .confirmation-modal.hide {
            animation: fadeOut 0.3s ease-in-out forwards;
        }

        .modal-headers {
            font-size: 18px;
            font-weight: bold;
            color: #d9534f;
            margin-bottom: 15px;
        }

        .modal-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .modal-button {
            width: 45%;
            padding: 10px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        .cancel-action {
            background: #aaa;
            color: white;
        }

        .cancel-action:hover {
            background: #888;
        }

        .confirm-action {
            background: #d9534f;
            color: white;
        }

        .confirm-action:hover {
            background: #c9302c;
        }

        /* Clickable Table Row */
        .clickable-row {
            cursor: pointer;
        }

        .clickable-row:hover {
            background-color: #f5f5f5;
        }

        /* Search Bar Styling */
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

        #searchInput {
            border: 2px solid #ced4da;
            border-left: none;
            padding: 1.5rem;
            font-size: 1.2rem;
            flex: 1;
        }

        /* Pagination Styles */
        .pagination-container {
            margin-top: 1.5rem;
        }

        .pagination {
            justify-content: center;
        }

        .page-link {
            color: #000;
            border-color: #000;
            padding: 0.5rem 1rem;
        }

        .page-link:hover {
            background-color: #000;
            color: #fff;
            border-color: #000;
        }

        .page-item.active .page-link {
            background-color: #000;
            border-color: #000;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            border-color: #dee2e6;
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
                <div class="container-fluid p-4 custom-margin">
                    <h2 class="mt-1 mb-4">Blood Collection</h2>
                    
                    <!-- Note about filtered records -->
                    <div class="alert alert-info">
                        <strong>Note:</strong> Showing only donors with "Accepted" physical examination status that have not yet had blood collected.
                    </div>
                    
                    <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                    <div class="alert alert-warning">
                        <h5>Debug Information:</h5>
                        <p><strong>Query Execution Time:</strong> <?php echo number_format($execution_time, 4); ?> seconds</p>
                        <p><strong>Total "Accepted" Physical Exams:</strong> <?php echo count($physical_exams); ?></p>
                        <p><strong>Total Blood Collections:</strong> <?php echo count($blood_collections); ?></p>
                        <p><strong>Filtered Out (Already Collected):</strong> <?php echo count($collected_physical_exam_ids); ?></p>
                        <p><strong>Records Available for Collection:</strong> <?php echo count($available_exams); ?></p>
                        <p><strong>Records Shown (Current Page):</strong> <?php echo count($examinations); ?></p>
                        <p><strong>Current Page / Total Pages:</strong> <?php echo $current_page; ?> / <?php echo $total_pages; ?></p>
                        
                        <hr>
                        <h6>Physical Exam IDs with Blood Collection (Excluded):</h6>
                        <pre><?php 
                        if (empty($collected_physical_exam_ids)) {
                            echo "None found";
                        } else {
                            echo implode(", ", $collected_physical_exam_ids);
                        }
                        ?></pre>
                        
                        <h6>Available Physical Exam IDs (Shown in table):</h6>
                        <pre><?php 
                        $available_ids = array_column($available_exams, 'physical_exam_id');
                        if (empty($available_ids)) {
                            echo "None found";
                        } else {
                            echo implode(", ", $available_ids);
                        }
                        ?></pre>
                        
                        <h6>All Physical Exam IDs with "Accepted" Status:</h6>
                        <pre><?php 
                        if (empty($all_physical_exam_ids)) {
                            echo "None found";
                        } else {
                            echo implode(", ", $all_physical_exam_ids);
                        }
                        ?></pre>
                        
                        <h6>Direct Blood Collection Check:</h6>
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Blood Collection ID</th>
                                    <th>Physical Exam ID</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            // Make a direct query to get all blood collections with their physical_exam_id
                            $direct_query_url = SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id,physical_exam_id';
                            $ch = curl_init($direct_query_url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            $response = curl_exec($ch);
                            $direct_blood_collections = json_decode($response, true) ?: [];
                            curl_close($ch);
                            
                            if (empty($direct_blood_collections)) {
                                echo "<tr><td colspan='2'>No blood collection records found</td></tr>";
                            } else {
                                foreach ($direct_blood_collections as $collection) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($collection['blood_collection_id'] ?? 'N/A') . "</td>";
                                    echo "<td>" . htmlspecialchars($collection['physical_exam_id'] ?? 'N/A') . "</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
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
                                        <option value="surname">Surname</option>
                                        <option value="firstname">First Name</option>
                                        <option value="remarks">Physical Exam Remarks</option>
                                        <option value="blood_bag_type">Blood Bag Type</option>
                                    </select>
                                    <input type="text" 
                                        class="form-control form-control-lg" 
                                        id="searchInput" 
                                        placeholder="Search records..."
                                        style="height: 60px; font-size: 1.2rem;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <?php if (!empty($debug_info)): ?>
                            <div class="alert alert-info">
                                <?php echo htmlspecialchars($debug_info); ?>
                            </div>
                        <?php endif; ?>
                        
                        <table class="dashboard-staff-tables table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Physical Exam Remarks</th>
                                    <th>Blood Bag Type</th>
                                </tr>
                            </thead>
                            <tbody id="bloodCollectionTableBody">
                                <?php 
                                if (is_array($examinations) && !empty($examinations)) {
                                    $counter = 1; // Initialize counter
                                    foreach ($examinations as $examination) {
                                        if (!is_array($examination)) continue;
                                        ?>
                                        <tr class="clickable-row" data-examination='<?php echo htmlspecialchars(json_encode([
                                            'donor_id' => $examination['donor_id'] ?? '',
                                            'physical_exam_id' => $examination['physical_exam_id'] ?? ''
                                        ]), JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo isset($examination['donor_form']['surname']) ? htmlspecialchars($examination['donor_form']['surname']) : 'N/A'; ?></td>
                                            <td><?php echo isset($examination['donor_form']['first_name']) ? htmlspecialchars($examination['donor_form']['first_name']) : 'N/A'; ?></td>
                                            <td><?php echo isset($examination['remarks']) ? htmlspecialchars($examination['remarks']) : 'N/A'; ?></td>
                                            <td><?php echo isset($examination['blood_bag_type']) ? htmlspecialchars($examination['blood_bag_type']) : 'N/A'; ?></td>
                                        </tr>
                                <?php 
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center">No approved records found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Add Pagination Controls -->
                        <div class="pagination-container">
                            <nav aria-label="Blood collection submissions navigation">
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


<!-- Confirmation Modal -->
<div class="confirmation-modal" id="confirmationDialog">
                    <div class="modal-headers">Do you want to continue?</div>
                    <div class="modal-actions">
                        <button class="modal-button cancel-action" id="cancelButton">No</button>
                        <button class="modal-button confirm-action" id="confirmButton">Yes</button>
                    </div>
                </div>    
                
                <!-- Loading Spinner -->
                <div class="loading-spinner" id="loadingSpinner"></div>
            </main>
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
                    <p class="text-white mt-3 mb-0">Please wait...</p>
                </div>
            </div>
        </div>
    </div>
    <script>
        function showConfirmationModal() {
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            confirmationModal.show();
        }

        function proceedToDonorForm() {
            // Hide confirmation modal
            const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            confirmationModal.hide();

            // Show loading modal
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            loadingModal.show();

            // Redirect after a short delay to show loading animation
            setTimeout(() => {
                window.location.href = '../forms/donor-form-modal.php';
            }, 800);
        }
        document.addEventListener('DOMContentLoaded', function() {
            let confirmationDialog = document.getElementById("confirmationDialog");
            let loadingSpinner = document.getElementById("loadingSpinner");
            let cancelButton = document.getElementById("cancelButton");
            let confirmButton = document.getElementById("confirmButton");
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            const bloodCollectionTableBody = document.getElementById('bloodCollectionTableBody');
            let currentCollectionData = null;

            // Store original rows for search reset
            const originalRows = Array.from(bloodCollectionTableBody.getElementsByTagName('tr'));

            // Attach click event to all rows
            function attachRowClickHandlers() {
                document.querySelectorAll(".clickable-row").forEach(row => {
                    row.addEventListener("click", function() {
                        currentCollectionData = JSON.parse(this.dataset.examination);
                        console.log("Selected physical exam data:", currentCollectionData); // Debug log
                        confirmationDialog.classList.remove("hide");
                        confirmationDialog.classList.add("show");
                        confirmationDialog.style.display = "block";
                    });
                });
            }

            attachRowClickHandlers();

            // Close Modal Function
            function closeModal() {
                confirmationDialog.classList.remove("show");
                confirmationDialog.classList.add("hide");
                setTimeout(() => {
                    confirmationDialog.style.display = "none";
                }, 300);
            }

            // Yes Button (Triggers Loading Spinner & Redirects)
            confirmButton.addEventListener("click", function() {
                if (!currentCollectionData) {
                    console.error('No collection data available');
                    return;
                }

                closeModal();
                loadingSpinner.style.display = "block";
                
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../../src/views/forms/blood-collection-form.php';

                // Add donor_id and physical_exam_id as hidden inputs
                const donorIdInput = document.createElement('input');
                donorIdInput.type = 'hidden';
                donorIdInput.name = 'donor_id';
                donorIdInput.value = currentCollectionData.donor_id;

                const physicalExamIdInput = document.createElement('input');
                physicalExamIdInput.type = 'hidden';
                physicalExamIdInput.name = 'physical_exam_id';
                physicalExamIdInput.value = currentCollectionData.physical_exam_id;
                
                // Add role_id=3 (staff) as hidden input
                const roleIdInput = document.createElement('input');
                roleIdInput.type = 'hidden';
                roleIdInput.name = 'role_id';
                roleIdInput.value = '3';
                
                // Add a flag indicating we're coming from the staff dashboard
                const fromDashboardInput = document.createElement('input');
                fromDashboardInput.type = 'hidden';
                fromDashboardInput.name = 'from_dashboard';
                fromDashboardInput.value = 'staff_blood_collection';

                form.appendChild(donorIdInput);
                form.appendChild(physicalExamIdInput);
                form.appendChild(roleIdInput);
                form.appendChild(fromDashboardInput);
                document.body.appendChild(form);

                console.log("Submitting form with donor_id: " + currentCollectionData.donor_id + 
                          ", physical_exam_id: " + currentCollectionData.physical_exam_id +
                          ", role_id: 3");
                
                setTimeout(() => {
                    loadingSpinner.style.display = "none";
                    form.submit();
                }, 2000);
            });

            // No Button (Closes Modal)
            cancelButton.addEventListener("click", closeModal);

            // Search functionality
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const category = searchCategory.value;
                
                if (!searchTerm) {
                    originalRows.forEach(row => row.style.display = '');
                    return;
                }

                originalRows.forEach(row => {
                    const cells = Array.from(row.getElementsByTagName('td'));
                    let shouldShow = false;

                    if (category === 'all') {
                        shouldShow = cells.some(cell => 
                            cell.textContent.toLowerCase().includes(searchTerm)
                        );
                    } else {
                        const columnIndex = {
                            'surname': 1,
                            'firstname': 2,
                            'remarks': 3,
                            'blood_bag_type': 4
                        }[category];

                        if (columnIndex !== undefined) {
                            const cellText = cells[columnIndex].textContent.toLowerCase();
                            shouldShow = cellText.includes(searchTerm);
                        }
                    }

                    row.style.display = shouldShow ? '' : 'none';
                });
            }

            // Update placeholder based on selected category
            searchCategory.addEventListener('change', function() {
                const category = this.value;
                let placeholder = 'Search by ';
                switch(category) {
                    case 'surname': placeholder += 'surname...'; break;
                    case 'firstname': placeholder += 'first name...'; break;
                    case 'remarks': placeholder += 'physical exam remarks...'; break;
                    case 'blood_bag_type': placeholder += 'blood bag type...'; break;
                    default: placeholder = 'Search records...';
                }
                searchInput.placeholder = placeholder;
                performSearch();
            });

            // Debounce function
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
        });
    </script>
</body>
</html>