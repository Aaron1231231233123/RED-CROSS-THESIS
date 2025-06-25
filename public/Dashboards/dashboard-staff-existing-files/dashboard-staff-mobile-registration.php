<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require '../../../assets/php_func/user_roles_staff.php';

// Add this function at the top with other PHP code
function generateSecureToken($donor_id) {
    // Create a unique token using donor_id and a random component
    $random = bin2hex(random_bytes(16));
    $timestamp = time();
    $token = hash('sha256', $donor_id . $random . $timestamp);
    
    // Store the token mapping in the session
    if (!isset($_SESSION['donor_tokens'])) {
        $_SESSION['donor_tokens'] = [];
    }
    $_SESSION['donor_tokens'][$token] = [
        'donor_id' => $donor_id,
        'expires' => time() + 3600 // Token expires in 1 hour
    ];
    
    return $token;
}

// Add this function near the top after session_start()
function hashDonorId($donor_id) {
    $salt = "RedCross2024"; // Adding a salt for extra security
    return hash('sha256', $donor_id . $salt);
}

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

// Get all donor records
$query_url = SUPABASE_URL . '/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,age,sex,registration_channel,submitted_at&order=submitted_at.desc';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $query_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

// Log the final query URL and raw response for debugging
error_log("Final query URL: " . $query_url);
error_log("Supabase raw response: " . substr($response, 0, 500) . '...');

// Check if the response is valid JSON
if ($response === false || is_null(json_decode($response, true))) {
    error_log("Error fetching data from Supabase: " . curl_error($ch));
    $donors = [];
} else {
    $all_donors = json_decode($response, true) ?: [];
    error_log("Decoded all donors count: " . count($all_donors));
    
    // Filter to only show Mobile registration channel donors
    $donors = [];
    foreach ($all_donors as $donor) {
        if (isset($donor['registration_channel']) && $donor['registration_channel'] === 'Mobile') {
            $donors[] = $donor;
        }
    }
    error_log("Filtered mobile donors count: " . count($donors));
}

// Group donors by unique identity (surname, first_name, middle_name, birthdate) FIRST
$donorGroups = [];
foreach ($donors as $donor) {
    $key = ($donor['surname'] ?? '') . '|' . 
           ($donor['first_name'] ?? '') . '|' . 
           ($donor['middle_name'] ?? '') . '|' . 
           ($donor['birthdate'] ?? '');
    
    if (!isset($donorGroups[$key])) {
        $donorGroups[$key] = [
            'info' => $donor,
            'count' => 1,
            'latest_submission' => $donor['submitted_at'] ?? null
        ];
    } else {
        $donorGroups[$key]['count']++;
        
        // Keep track of the latest submission
        if (isset($donor['submitted_at']) && 
            (!isset($donorGroups[$key]['latest_submission']) || 
            $donor['submitted_at'] > $donorGroups[$key]['latest_submission'])) {
            $donorGroups[$key]['latest_submission'] = $donor['submitted_at'];
            $donorGroups[$key]['info'] = $donor;
        }
    }
}

// Convert back to array for pagination
$donors = [];
foreach ($donorGroups as $group) {
    $donor = $group['info'];
    $donor['donation_count'] = $group['count'];
    $donor['latest_submission'] = $group['latest_submission'];
    $donors[] = $donor;
}

// NOW fetch medical history records to calculate status counts (after grouping)
$medical_history_url = SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id,donor_id,medical_approval';
$ch = curl_init($medical_history_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$response = curl_exec($ch);
curl_close($ch);

// Get Mobile donor IDs from GROUPED donors
$mobile_donor_ids = array_column($donors, 'donor_id');

// Initialize counters
$incoming_count = 0;
$approved_count = 0;
$declined_count = 0;

// Arrays to store donor IDs by status (only for Mobile registration donors)
$donor_with_medical_history = [];
$donor_with_approved_medical_history = [];
$donor_with_declined_medical_history = [];

if ($response === false || is_null(json_decode($response, true))) {
    error_log("Error fetching medical history data from Supabase");
} else {
    $medical_histories = json_decode($response, true) ?: [];
    error_log("Decoded medical histories count: " . count($medical_histories));
    
    // Process medical histories to get counts - ONLY for GROUPED Mobile registration donors
    $incoming_with_null_approval = [];

    foreach ($medical_histories as $history) {
        if (isset($history['donor_id']) && in_array($history['donor_id'], $mobile_donor_ids)) {
            // Only process medical history for Mobile registration donors
            if (isset($history['medical_approval'])) {
                if ($history['medical_approval'] === 'Approved') {
                    $donor_with_approved_medical_history[] = $history['donor_id'];
                    $donor_with_medical_history[] = $history['donor_id'];
                } else if ($history['medical_approval'] === null) {
                    // If medical_approval is null, treat it as incoming
                    $incoming_with_null_approval[] = $history['donor_id'];
                } else {
                    $donor_with_declined_medical_history[] = $history['donor_id'];
                    $donor_with_medical_history[] = $history['donor_id'];
                }
            } else {
                // If medical_approval field is missing, also treat as incoming
                $incoming_with_null_approval[] = $history['donor_id'];
            }
        }
    }
    
    // Remove duplicates
    $donor_with_medical_history = array_unique($donor_with_medical_history);
    $donor_with_approved_medical_history = array_unique($donor_with_approved_medical_history);
    $donor_with_declined_medical_history = array_unique($donor_with_declined_medical_history);
    $incoming_with_null_approval = array_unique($incoming_with_null_approval);
    
    // Calculate incoming count (GROUPED Mobile donors without any medical history or with null approval)
    $processed_donors = array_merge($donor_with_approved_medical_history, $donor_with_declined_medical_history);
    $incoming_donors = array_diff($mobile_donor_ids, $processed_donors);
    $incoming_count = count($incoming_donors);
    
    // Update counters to reflect unique GROUPED Mobile registration donors only
    $approved_count = count($donor_with_approved_medical_history);
    $declined_count = count($donor_with_declined_medical_history);
    
    // Log the detailed counts for debugging
    error_log("GROUPED Mobile Registration Medical History Counts - Total GROUPED Mobile donors: " . count($mobile_donor_ids));
    error_log("GROUPED Mobile Registration Medical History Counts - Approved: $approved_count, Declined: $declined_count, Incoming: $incoming_count");
    error_log("GROUPED Mobile Registration Medical History Counts - Donors with null approval: " . count($incoming_with_null_approval));
    error_log("GROUPED Mobile Registration Medical History Counts - Processed donors: " . count($processed_donors));
    error_log("GROUPED Mobile Registration Incoming count: $incoming_count, Approved count: $approved_count, Declined count: $declined_count");
}

// Calculate today's summary count from GROUPED donors
$today = date('Y-m-d');
$today_count = 0;
foreach ($donors as $donor) {
    if (isset($donor['latest_submission'])) {
        $submission_date = date('Y-m-d', strtotime($donor['latest_submission']));
        if ($submission_date === $today) {
            $today_count++;
        }
    }
}

// Handle status filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'incoming';

// Filter donors based on status if needed
$filtered_donors = [];
if ($status_filter === 'incoming') {
    foreach ($donors as $donor) {
        if (in_array($donor['donor_id'], $incoming_donors)) {
            $filtered_donors[] = $donor;
        }
    }
    $donors = $filtered_donors;
} elseif ($status_filter === 'approved') {
    foreach ($donors as $donor) {
        if (in_array($donor['donor_id'], $donor_with_approved_medical_history)) {
            $filtered_donors[] = $donor;
        }
    }
    $donors = $filtered_donors;
} elseif ($status_filter === 'declined') {
    foreach ($donors as $donor) {
        if (in_array($donor['donor_id'], $donor_with_declined_medical_history)) {
            $filtered_donors[] = $donor;
        }
    }
    $donors = $filtered_donors;
} elseif ($status_filter === 'today') {
    foreach ($donors as $donor) {
        if (isset($donor['latest_submission'])) {
            $submission_date = date('Y-m-d', strtotime($donor['latest_submission']));
            if ($submission_date === $today) {
                $filtered_donors[] = $donor;
            }
        }
    }
    $donors = $filtered_donors;
}

// Sort by updated_at (FIFO: oldest first)
usort($donors, function($a, $b) {
    $a_time = isset($a['updated_at']) ? strtotime($a['updated_at']) : (isset($a['latest_submission']) ? strtotime($a['latest_submission']) : (isset($a['submitted_at']) ? strtotime($a['submitted_at']) : 0));
    $b_time = isset($b['updated_at']) ? strtotime($b['updated_at']) : (isset($b['latest_submission']) ? strtotime($b['latest_submission']) : (isset($b['submitted_at']) ? strtotime($b['submitted_at']) : 0));
    return $a_time <=> $b_time;
});

$total_records = count($donors);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Slice the array to get only the records for the current page
$donors = array_slice($donors, $offset, $records_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Medical History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-color: #f5f5f5;
            --text-color: #000;
            --sidebar-bg: #ffffff;
            --hover-bg: #f0f0f0;
            --primary-color: #b22222; /* Red Cross red */
            --primary-dark: #8b0000; /* Darker red for hover and separator */
            --active-color: #b22222;
            --table-header-bg: #b22222;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Header styling */
        .dashboard-home-header {
            margin-left: 16.66666667%;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
        }
        
        .header-title {
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            flex-grow: 1;
        }
        
        .header-date {
            color: #777;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .register-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.4rem 0.75rem;
            border-radius: 3px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-left: auto;
            font-size: 0.9rem;
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
            border-right: 1px solid #e0e0e0;
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
            border-radius: 0;
            transition: all 0.3s ease;
            color: #000 !important;
            text-decoration: none;
            border-left: 5px solid transparent;
        }

        .sidebar .nav-link:hover,
        .sidebar  {
            background: var(--hover-bg);
            color: var(--active-color) !important;
            border-left-color: var(--active-color);
        }

        .nav-link.active{
            background-color: var(--active-color);
            color: white !important;
        }

        /* Main Content */
        .main-content {
            padding: 1rem;
            margin-left: 16.66666667%;
            background-color: var(--bg-color);
        }
        
        .content-wrapper {
            background-color: white;
            border-radius: 0px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eaeaea;
        }

        .dashboard-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
        }

        .dashboard-date {
            color: #777;
            font-size: 0.9rem;
        }

        /* Status Cards */
        .dashboard-staff-status {
            display: flex;
            justify-content: space-between;
            gap: 1rem; 
            margin-bottom: 1.5rem;
        }
        
        .status-card {
            flex: 1;
            border-radius: 0;
            background-color: white;
            border: 1px solid #e0e0e0;
            padding: 1rem;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            display: block;
            transition: all 0.2s ease-in-out;
        }
        
        .status-card:hover {
            text-decoration: none;
            color: #333;
            background-color: #f8f8f8;
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .status-card.active {
            border-top: 3px solid var(--primary-dark);
            background-color: #f8f8f8;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        
        .dashboard-staff-count {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #333;
        }
        
        .dashboard-staff-title {
            font-weight: bold;
            font-size: 0.95rem;
            margin-bottom: 0;
            color: #555;
        }
        
        .welcome-section {
            margin-bottom: 1.5rem;
        }
        
        .welcome-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0;
            color: #333;
        }

        /* Red line separator */
        .red-separator {
            height: 4px;
            background-color: #8b0000;
            border: none;
            margin: 1.5rem 0;
            width: 100%;
            opacity: 1;
        }

        /* Table Styling */
        .dashboard-staff-tables {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 0;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .dashboard-staff-tables thead th {
            background-color: var(--table-header-bg);
            color: white;
            padding: 10px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 0;
        }

        .dashboard-staff-tables tbody td {
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.95rem;
        }

        .dashboard-staff-tables tbody tr:nth-child(odd) {
            background-color: #f8f9fa;
        }

        .dashboard-staff-tables tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        .dashboard-staff-tables tbody tr:hover {
            background-color: #f0f0f0;
        }

        .dashboard-staff-tables tbody tr{
            cursor: pointer;
        }

        /* Search bar */
        .search-container {
            margin-bottom: 1.5rem;
        }

        #searchInput {
            border-radius: 0;
            height: 45px;
            border-color: #ddd;
            font-size: 1rem;
            padding: 0.5rem 0.75rem;
        }

        /* Pagination Styles */
        .pagination-container {
            margin-top: 2rem;
        }

        .pagination {
            justify-content: center;
        }

        .page-link {
            color: #333;
            border-color: #dee2e6;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .page-link:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
            border-color: #dee2e6;
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #fff;
            border-color: #dee2e6;
        }

        /* Badge styling */
        .badge.bg-primary {
            background-color: var(--primary-color) !important;
            font-size: 0.95rem;
            padding: 0.3rem 0.6rem;
            font-weight: 600;
            border-radius: 4px;
        }
        
        /* Section header */
        .section-header {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: #333;
        }
    </style>
</head>

<body class="light-mode">
    <div class="container-fluid p-0">
        <!-- Header -->
        <div class="dashboard-home-header">
            <h4 class="header-title">Interviewer Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
            <button class="register-btn" onclick="showConfirmationModal()">
                Register Donor
            </button>
        </div>

        <div class="row g-0">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4>Interviewer</h4>
                <ul class="nav flex-column">
                    
                    <?php if ($user_staff_roles === 'interviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-donor-submission.php">
                                System Registration
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_staff_roles === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard-staff-medical-history-submissions.php">
                                Initial Screening Queue 
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'physician'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-physical-submission.php">
                                Physical Exam Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'phlebotomist'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-blood-collection-submission.php">
                                Blood Collection Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                            <a class="nav-link active" href="*">
                                Mobile Registration
                            </a>
                        </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard-staff-history.php">Donor History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../../assets/php_func/logout.php">Logout</a>
                    </li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <div class="content-wrapper">
                    <div class="welcome-section">
                        <h2 class="welcome-title">Welcome, Interviewer!</h2>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="dashboard-staff-status">
                        <a href="?status=<?php echo $status_filter; ?>" class="status-card active">
                            <p class="dashboard-staff-count"><?php 
                                // Show the count based on current filter
                                if ($status_filter === 'incoming') {
                                    echo $incoming_count;
                                } elseif ($status_filter === 'approved') {
                                    echo $approved_count;
                                } elseif ($status_filter === 'declined') {
                                    echo $declined_count;
                                } elseif ($status_filter === 'today') {
                                    echo $today_count;
                                } else {
                                    echo $total_records;
                                }
                            ?></p>
                            <p class="dashboard-staff-title">Pending</p>
                        </a>
                        <a href="?status=today" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'today' ? 'active' : ''); ?>">
                            <p class="dashboard-staff-count"><?php echo $today_count; ?></p>
                            <p class="dashboard-staff-title">Today's Summary</p>
                        </a>
                    </div>
                    
                    <h5 class="section-header">Donation Records</h5>
                    
                    <!-- Search Bar -->
                    <div class="search-container">
                        <input type="text" 
                            class="form-control" 
                            id="searchInput" 
                            placeholder="Search donors...">
                    </div>
                    
                    <hr class="red-separator">
                    
                    <div class="table-responsive">
                        <table class="dashboard-staff-tables table-hover">
                            <thead>
                                <tr>
                                    <th>Donation Date</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Gender</th>
                                    <th>Age</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php if($donors && is_array($donors)): ?>
                                    <?php foreach($donors as $index => $donor): ?>
                                        <?php
                                        // Calculate age if missing but birthdate is available
                                        if (empty($donor['age']) && !empty($donor['birthdate'])) {
                                            $birthDate = new DateTime($donor['birthdate']);
                                            $today = new DateTime();
                                            $donor['age'] = $birthDate->diff($today)->y;
                                        }
                                        ?>
                                        <tr class="clickable-row" data-donor-id="<?php echo $donor['donor_id']; ?>">
                                            <td><?php 
                                                if (isset($donor['latest_submission'])) {
                                                    $date = new DateTime($donor['latest_submission']);
                                                    echo $date->format('F d, Y');
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?></td>
                                            <td><?php echo isset($donor['surname']) ? htmlspecialchars($donor['surname']) : ''; ?></td>
                                            <td><?php echo isset($donor['first_name']) ? htmlspecialchars($donor['first_name']) : ''; ?></td>
                                            <td><?php echo !empty($donor['sex']) ? htmlspecialchars(ucfirst($donor['sex'])) : 'N/A'; ?></td>
                                            <td><?php echo isset($donor['age']) ? htmlspecialchars($donor['age']) : ''; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-info btn-sm view-donor-btn me-1" 
                                                        data-donor-id="<?php echo $donor['donor_id']; ?>" 
                                                        title="View Details"
                                                        style="width: 35px; height: 30px;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm edit-donor-btn" 
                                                        data-donor-id="<?php echo $donor['donor_id']; ?>" 
                                                        title="Edit"
                                                        style="width: 35px; height: 30px;">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No donor records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Donor medical history navigation">
                                <ul class="pagination justify-content-center">
                                    <!-- Previous button -->
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>" <?php echo $current_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Previous</a>
                                    </li>
                                    
                                    <!-- Page numbers -->
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <!-- Next button -->
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>" <?php echo $current_page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    </div>
    
    <!-- Medical History Modal -->
    <div class="modal fade" id="medicalHistoryModal" tabindex="-1" aria-labelledby="medicalHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="medicalHistoryModalLabel">Medical History Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="medicalHistoryModalContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Declaration Form Modal -->
    <div class="modal fade" id="declarationFormModal" tabindex="-1" aria-labelledby="declarationFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="declarationFormModalLabel">Declaration Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="declarationFormModalContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Deferral Status Modal -->
    <div class="modal fade" id="deferralStatusModal" tabindex="-1" aria-labelledby="deferralStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                    <h5 class="modal-title" id="deferralStatusModalLabel">Donor Status & Donation History</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                    <div id="deferralStatusContent">
                        <div class="d-flex justify-content-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
            
                    <div id="screeningInfo" class="mt-4" style="display: none;">
                        <h6 class="border-bottom pb-2 mb-3">Screening Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Interview Date:</strong> <span id="interviewDate">-</span></p>
                        </div>
                            <div class="col-md-6">
                                <p><strong>Body Weight:</strong> <span id="bodyWeight">-</span></p>
                            </div>
                            </div>
                            </div>
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="proceedToMedicalHistory">Proceed to Medical History</button>
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
                <div class="modal-footer border-0" style="padding: 1.5rem; background: #f8f9fa;">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 25px;">Cancel</button>
                    <button type="button" class="btn btn-danger px-4" onclick="proceedToDonorForm()" style="border-radius: 25px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border: none;">Proceed</button>
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
                window.location.href = '../../../src/views/forms/donor-form-modal.php';
            }, 800);
        }
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const donorTableBody = document.getElementById('donorTableBody');
            const deferralStatusModal = new bootstrap.Modal(document.getElementById('deferralStatusModal'));
            const deferralStatusContent = document.getElementById('deferralStatusContent');
            const screeningInfo = document.getElementById('screeningInfo');
            const interviewDateSpan = document.getElementById('interviewDate');
            const bodyWeightSpan = document.getElementById('bodyWeight');
            const proceedButton = document.getElementById('proceedToMedicalHistory');
            
            let currentDonorId = null;
            
            // Check if required elements exist
            if (!searchInput) {
                console.error('searchInput element not found');
            }
            if (!donorTableBody) {
                console.error('donorTableBody element not found');
            }
            if (!proceedButton) {
                console.error('proceedToMedicalHistory button not found');
            }
            
            // Store original rows for search reset (only if table body exists)
            let originalRows = [];
            if (donorTableBody) {
                originalRows = Array.from(donorTableBody.getElementsByTagName('tr'));
            }
            
            // Add click event to all clickable rows (only if they exist)
            const clickableRows = document.querySelectorAll('.clickable-row');
            if (clickableRows.length > 0) {
                clickableRows.forEach(row => {
                    row.addEventListener('click', function() {
                        const donorId = this.getAttribute('data-donor-id');
                        if (donorId) {
                            currentDonorId = donorId;
                            
                            // Show the modal with loading state
                            if (deferralStatusContent) {
                                deferralStatusContent.innerHTML = `
                                    <div class="d-flex justify-content-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>`;
                            }
                            if (screeningInfo) {
                                screeningInfo.style.display = 'none';
                            }
                            deferralStatusModal.show();
                            
                            // Fetch donor info and deferral status
                            fetchDonorStatusInfo(donorId);
                        }
                    });
                });
            } else {
                console.log('No clickable rows found');
            }
            
            // Function to fetch donor status information
            function fetchDonorStatusInfo(donorId) {
                console.log('Fetching donor status info for:', donorId);
                
                // First, fetch donor information (corrected path for subdirectory)
                fetch(`../../../assets/php_func/fetch_donor_info.php?donor_id=${donorId}`)
                    .then(response => {
                        console.log('Donor info response status:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(donorData => {
                        console.log('Donor data received:', donorData);
                        
                        // Next, check physical examination table for deferral status
                        fetch(`../../../assets/php_func/check_deferral_status.php?donor_id=${donorId}`)
                            .then(response => {
                                console.log('Deferral status response status:', response.status);
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(deferralData => {
                                console.log('Deferral data received:', deferralData);
                                if (typeof displayDonorInfo === 'function') {
                                    displayDonorInfo(donorData, deferralData);
                                } else {
                                    console.error('displayDonorInfo function not found - creating simple display');
                                    createSimpleDisplayDonorInfo(donorData, deferralData);
                                }
                                
                                // After getting deferral info, fetch screening info
                                fetch(`../../../assets/php_func/fetch_screening_info.php?donor_id=${donorId}`)
                                    .then(response => {
                                        console.log('Screening info response status:', response.status);
                                        if (!response.ok) {
                                            throw new Error(`HTTP error! status: ${response.status}`);
                                        }
                                        return response.json();
                                    })
                                    .then(screeningData => {
                                        console.log('Screening data received:', screeningData);
                                        if (screeningData.success && screeningData.data && screeningInfo) {
                                            screeningInfo.style.display = 'block';
                                            if (interviewDateSpan) {
                                                interviewDateSpan.textContent = screeningData.data.interview_date || '-';
                                            }
                                            if (bodyWeightSpan) {
                                                bodyWeightSpan.textContent = screeningData.data.body_weight ? `${screeningData.data.body_weight} kg` : '-';
                                            }
                                        } else {
                                            if (screeningInfo) {
                                                screeningInfo.style.display = 'none';
                                            }
                                        }
                                    })
                                    .catch(error => {
                                        console.error("Error fetching screening info:", error);
                                        if (screeningInfo) {
                                            screeningInfo.style.display = 'none';
                                        }
                                    });
                            })
                            .catch(error => {
                                console.error("Error checking deferral status:", error);
                                if (deferralStatusContent) {
                                    deferralStatusContent.innerHTML = `<div class="alert alert-danger">Error checking deferral status: ${error.message}</div>`;
                                }
                            });
                    })
                    .catch(error => {
                        console.error("Error fetching donor info:", error);
                        if (deferralStatusContent) {
                            deferralStatusContent.innerHTML = `<div class="alert alert-danger">Error fetching donor information: ${error.message}</div>`;
                        }
                    });
            }
            
            // Function to display donor and deferral information
            function displayDonorInfo(donorData, deferralData) {
                let donorInfoHTML = '';
                
                if (donorData && donorData.success) {
                    const donor = donorData.data;
                    
                    // Display basic donor info
                    donorInfoHTML += `
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Donor Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6"><strong>Name:</strong> ${donor.surname || ''}, ${donor.first_name || ''} ${donor.middle_name || ''}</div>
                                    <div class="col-md-3"><strong>Birthdate:</strong> ${donor.birthdate || '-'}</div>
                                    <div class="col-md-3"><strong>Age:</strong> ${donor.age || '-'}</div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6"><strong>Donation Count:</strong> <span class="badge bg-primary">${donor.donation_count || '1'}</span></div>
                                    <div class="col-md-6"><strong>Last Donation:</strong> ${formatDate(donor.latest_submission) || 'N/A'}</div>
                                </div>
                            </div>
                        </div>`;
                    
                    // Display latest donation details
                    if (donor.donation_history && donor.donation_history.length > 0) {
                        const latestDonation = donor.donation_history[0];
                        donorInfoHTML += `
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Latest Donation Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6"><strong>Date:</strong> ${formatDate(latestDonation.date) || 'N/A'}</div>
                                        <div class="col-md-6"><strong>Donation Type:</strong> ${latestDonation.donation_type || 'Unknown'}</div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-6"><strong>Blood Type:</strong> ${latestDonation.blood_type || 'Unknown'}</div>
                                        <div class="col-md-6"><strong>Contact:</strong> ${latestDonation.contact || 'Not provided'}</div>
                                    </div>
                                </div>
                            </div>`;
                        
                        // Display medical history if available
                        if (donor.medical_history) {
                            const medical = donor.medical_history;
                            donorInfoHTML += `
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Previous Medical History</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12">
                                                <p class="mb-1"><strong>Medical Conditions:</strong> 
                                                    ${medical.asthma ? '<span class="badge bg-warning me-1">Asthma</span>' : ''}
                                                    ${medical.allergies ? '<span class="badge bg-warning me-1">Allergies</span>' : ''}
                                                    ${medical.heart_disease ? '<span class="badge bg-warning me-1">Heart Disease</span>' : ''}
                                                    ${medical.hepatitis ? '<span class="badge bg-warning me-1">Hepatitis</span>' : ''}
                                                    ${medical.diabetes ? '<span class="badge bg-warning me-1">Diabetes</span>' : ''}
                                                    ${medical.kidney_disease ? '<span class="badge bg-warning me-1">Kidney Disease</span>' : ''}
                                                    ${medical.malaria ? '<span class="badge bg-warning me-1">Malaria</span>' : ''}
                                                    ${medical.thyroid_disease ? '<span class="badge bg-warning me-1">Thyroid Disease</span>' : ''}
                                                    ${!medical.asthma && !medical.allergies && !medical.heart_disease && !medical.hepatitis && 
                                                      !medical.diabetes && !medical.kidney_disease && !medical.malaria && !medical.thyroid_disease ? 
                                                      'None reported' : ''}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Blood Transfusion:</strong> ${medical.blood_transfusion ? 'Yes' : 'No'}</p>
                                                <p class="mb-1"><strong>Surgery History:</strong> ${medical.surgery ? 'Yes' : 'No'}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Tattoo:</strong> ${medical.tattoo ? 'Yes' : 'No'}</p>
                                                <p class="mb-1"><strong>Medication:</strong> ${medical.medicine ? 'Yes' : 'No'}</p>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <p class="mb-0"><strong>Notes:</strong> ${medical.notes || 'No additional notes'}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
                        }
                        
                        // Display donation history table
                        if (donor.donation_history.length > 1) {
                            let historyRows = '';
                            donor.donation_history.slice(1).forEach((donation, index) => {
                                historyRows += `
                                <tr>
                                    <td>${index + 2}</td>
                                    <td>${formatDate(donation.date) || 'N/A'}</td>
                                    <td>${donation.donation_type || 'Unknown'}</td>
                                    <td>${donation.blood_type || 'Unknown'}</td>
                                </tr>`;
                            });
                            
                            donorInfoHTML += `
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Previous Donations</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped mb-0">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Date</th>
                                                        <th>Type</th>
                                                        <th>Blood Type</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${historyRows}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>`;
                        }
                    }
                    
                    // Display eligibility information if available
                    if (donor.eligibility) {
                        const eligibility = donor.eligibility;
                        const endDate = eligibility.end_date ? new Date(eligibility.end_date) : null;
                        const now = new Date();
                        
                        donorInfoHTML += `
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Eligibility Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6"><strong>Status:</strong> 
                                            ${(() => {
                                                const endDate = eligibility.end_date ? new Date(eligibility.end_date) : null;
                                                const now = new Date();
                                                
                                                // Reset time components to compare just the dates
                                                if (endDate) {
                                                    endDate.setHours(0, 0, 0, 0);
                                                    now.setHours(0, 0, 0, 0);
                                                }
                                                
                                                if (endDate && now < endDate) {
                                                    // Not eligible yet - show warning
                                                    return `<span class="badge bg-warning" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">Temporarily Deferred</span>`;
                                                } else if (endDate && now.getTime() === endDate.getTime()) {
                                                    // Today is the eligibility date - they are now eligible
                                                    return `<span class="badge bg-success" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">Eligible for Donation Today</span>`;
                                                } else if (eligibility.status === 'approved') {
                                                    return `<span class="badge bg-success" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">Eligible for Donation</span>`;
                                                } else {
                                                    return `<span class="badge bg-danger" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">Not Eligible for Donation</span>`;
                                                }
                                            })()}
                                        </div>
                                        <div class="col-md-6"><strong>Eligible After:</strong> ${eligibility.end_date ? formatDate(eligibility.end_date) : 'N/A'}</div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <p class="mb-0"><strong>Notes:</strong> ${eligibility.remarks || 'No additional notes'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                    }
                }
                
                // Display deferral status if available
                if (deferralData && deferralData.success) {
                    if (deferralData.isDeferred) {
                        const deferralType = deferralData.deferralType || 'Unknown';
                        const deferralReason = deferralData.reason || 'No reason provided';
                        const remarks = deferralData.remarks || '';
                        
                        let alertClass = 'alert-warning';
                        let deferralTitle = '';
                        
                        // Determine alert class and title based on deferral type
                        if (deferralType === 'permanently_deferred' || remarks === 'Permanently Deferred') {
                            alertClass = 'alert-danger';
                            deferralTitle = 'Permanently Deferred';
                        } else if (deferralType === 'temporarily_deferred' || remarks === 'Temporarily Deferred') {
                            alertClass = 'alert-warning';
                            deferralTitle = 'Temporarily Deferred';
                        } else {
                            deferralTitle = 'Deferred';
                        }
                        
                        donorInfoHTML += `
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Donor Status</h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert ${alertClass} mb-0">
                                        <h6 class="alert-heading mb-2">${deferralTitle} - Not Eligible for Donation</h6>
                                        <p class="mb-0"><strong>Reason:</strong> ${deferralReason}</p>
                                        ${remarks ? `<p class="mb-0"><strong>Remarks:</strong> ${remarks}</p>` : ''}
                                    </div>
                                </div>
                            </div>`;
                    } else if (deferralData.isRefused || deferralData.remarks === 'Refused') {
                        donorInfoHTML += `
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Donor Status</h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-danger mb-0">
                                        <h6 class="alert-heading mb-2">Not Eligible - Donor Refused</h6>
                                        <p class="mb-0"><strong>Reason:</strong> ${deferralData.reason || 'No reason provided'}</p>
                                        ${deferralData.remarks ? `<p class="mb-0"><strong>Remarks:</strong> ${deferralData.remarks}</p>` : ''}
                                    </div>
                                </div>
                            </div>`;
                    } else {
                        donorInfoHTML += `
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Donor Status</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-0"><strong>Status:</strong> ${deferralData.remarks || 'Accepted'}</p>
                                    <p class="mb-0">No deferral or refusal records found for this donor.</p>
                                </div>
                            </div>`;
                    }
                } else {
                    donorInfoHTML += `
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Donor Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-0">
                                    <h6 class="alert-heading mb-2">Status Unknown</h6>
                                    <p class="mb-0">No physical examination records found for this donor.</p>
                                    <p class="mb-0">Eligibility status cannot be determined without examination.</p>
                                </div>
                            </div>
                        </div>`;
                }
                
                // Always add eligibility information section, even if no data
                if (!donorData || !donorData.success || !donorData.data || !donorData.data.eligibility) {
                    donorInfoHTML += `
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Eligibility Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6"><strong>Status:</strong> 
                                        <span class="badge bg-secondary" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">No Data Available</span>
                                    </div>
                                    <div class="col-md-6"><strong>Eligible After:</strong> N/A</div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <p class="mb-0"><strong>Notes:</strong> No eligibility information available</p>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                }
                
                deferralStatusContent.innerHTML = donorInfoHTML;
            }
            
            // Helper function to capitalize first letter
            function ucfirst(string) {
                if (!string) return '';
                return string.charAt(0).toUpperCase() + string.slice(1);
            }
            
            // Format date helper function
            function formatDate(dateString) {
                if (!dateString) return null;
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                });
            }
            
            // Simple fallback function to display donor info
            function createSimpleDisplayDonorInfo(donorData, deferralData) {
                console.log('Using simple display function');
                
                if (!deferralStatusContent) {
                    console.error('deferralStatusContent element not found');
                    return;
                }
                
                let content = '<div class="alert alert-info">Loading donor information...</div>';
                
                if (donorData && donorData.success && donorData.data) {
                    const donor = donorData.data;
                    content = `
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Donor Information</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Name:</strong> ${donor.surname || ''}, ${donor.first_name || ''} ${donor.middle_name || ''}</p>
                                <p><strong>Age:</strong> ${donor.age || 'N/A'}</p>
                                <p><strong>Gender:</strong> ${donor.sex || 'N/A'}</p>
                                <p><strong>Registration Channel:</strong> Mobile</p>
                            </div>
                        </div>
                    `;
                } else {
                    content = '<div class="alert alert-warning">Unable to load donor information.</div>';
                }
                
                deferralStatusContent.innerHTML = content;
            }

            
            // Update placeholder based on selected category (only if element exists)
            const searchCategory = document.getElementById('searchCategory');
            if (searchCategory && searchInput) {
                searchCategory.addEventListener('change', function() {
                    const category = this.value;
                    let placeholder = 'Search by ';
                    switch(category) {
                        case 'surname': placeholder += 'surname...'; break;
                        case 'firstname': placeholder += 'first name...'; break;
                        case 'age': placeholder += 'age...'; break;
                        default: placeholder = 'Search donors...';
                    }
                    searchInput.placeholder = placeholder;
                    if (typeof performSearch === 'function') {
                        performSearch();
                    }
                });
            }
            
            // Handle proceed button click - only add listener if button exists
            if (proceedButton) {
                proceedButton.addEventListener('click', function() {
                if (currentDonorId) {
                    console.log('Proceed button clicked for donor:', currentDonorId);
                    
                    // Hide the deferral status modal first
                    deferralStatusModal.hide();
                    
                    // Show the medical history modal
                    setTimeout(() => {
                        const medicalHistoryModal = new bootstrap.Modal(document.getElementById('medicalHistoryModal'));
                        const modalContent = document.getElementById('medicalHistoryModalContent');
                        
                        console.log('Medical history modal element:', document.getElementById('medicalHistoryModal'));
                        console.log('Modal content element:', modalContent);
                        
                        // Reset modal content to loading state
                        modalContent.innerHTML = `
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>`;
                        
                        // Show the modal
                        medicalHistoryModal.show();
                        
                        // Load the medical history form content (note the additional ../ for subdirectory)
                        console.log('Fetching medical history for donor:', currentDonorId);
                        fetch(`../../../src/views/forms/medical-history-modal-content.php?donor_id=${currentDonorId}`)
                            .then(response => {
                                console.log('Response status:', response.status);
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.text();
                            })
                            .then(data => {
                                console.log('Response received, length:', data.length);
                                console.log('First 200 chars:', data.substring(0, 200));
                                
                                // Check if the response is JSON (error) or HTML (success)
                                try {
                                    const parsedData = JSON.parse(data);
                                    console.error('Server returned error:', parsedData);
                                    modalContent.innerHTML = `
                                        <div class="alert alert-danger">
                                            <h6>Error from Server</h6>
                                            <p>${parsedData.error || 'Unknown error occurred'}</p>
                                            <p><small>Donor ID: ${currentDonorId}</small></p>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>`;
                                    return;
                                } catch (e) {
                                    // Not JSON, continue with HTML processing
                                    console.log('Response is HTML, proceeding...');
                                }
                                
                                modalContent.innerHTML = data;
                                
                                // After loading content, populate the questions
                                setTimeout(() => {
                                    console.log('Starting to populate questions...');
                                    
                                    // Get data from the JSON script tag
                                    const modalDataScript = document.getElementById('modalData');
                                    if (!modalDataScript) {
                                        console.error("Modal data script not found");
                                        return;
                                    }
                                    
                                    let modalData;
                                    try {
                                        console.log("Raw modal data script content:", modalDataScript.textContent);
                                        modalData = JSON.parse(modalDataScript.textContent);
                                        console.log("Modal data parsed successfully:", modalData);
                                        console.log("Medical history data:", modalData.medicalHistoryData);
                                        console.log("Donor sex:", modalData.donorSex);
                                        console.log("User role:", modalData.userRole);
                                    } catch (e) {
                                        console.error("Error parsing modal data:", e);
                                        console.error("Raw content that failed to parse:", modalDataScript.textContent);
                                        return;
                                    }
                                    
                                    // Check if we have the required elements
                                    const existingForm = document.getElementById('modalMedicalHistoryForm');
                                    const existingQuestions = document.querySelectorAll('[data-step-container]');
                                    
                                    if (existingForm && existingQuestions.length > 0) {
                                        console.log(`Found form and ${existingQuestions.length} step containers`);
                                        
                                        // Populate the questions using the data
                                        populateModalQuestions(modalData);
                                        
                                        // Initialize the navigation
                                        initializeModalStepNavigation(modalData.userRole, modalData.donorSex === 'male');
                                    } else {
                                        console.error('Required form elements not found');
                                        console.log('Form found:', !!existingForm);
                                        console.log('Step containers found:', existingQuestions.length);
                                    }
                                }, 100);
                            })
                            .catch(error => {
                                console.error('Error loading medical history form:', error);
                                modalContent.innerHTML = `
                                    <div class="alert alert-danger">
                                        <h6>Error Loading Form</h6>
                                        <p>Unable to load the medical history form. Please try again.</p>
                                        <p><small>Error: ${error.message}</small></p>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>`;
                            });
                    }, 500); // Add delay to ensure modal transition
                }
            });
            }

         

         
         // Function to populate questions into the modal containers
         function populateModalQuestions(modalData) {
             console.log('Populating modal questions with data:', modalData);
             
             const modalMedicalHistoryData = modalData.medicalHistoryData;
             const modalDonorSex = modalData.donorSex;
             const modalUserRole = modalData.userRole;
             const modalIsMale = modalDonorSex === 'male';
             
             console.log('Medical history data structure:', modalMedicalHistoryData);
             console.log('Is medical history data null?', modalMedicalHistoryData === null);
             console.log('Medical history data type:', typeof modalMedicalHistoryData);
             
             // Only make fields required for reviewers (who can edit)
             const modalIsReviewer = modalUserRole === 'reviewer';
             const modalRequiredAttr = modalIsReviewer ? 'required' : '';
             
             // Hide step 6 for male donors
             if (modalIsMale) {
                 const step6 = document.getElementById('modalStep6');
                 const line56 = document.getElementById('modalLine5-6');
                 if (step6) step6.style.display = 'none';
                 if (line56) line56.style.display = 'none';
             }
             
             // Define questions by step (updated to match working version)
             const questionsByStep = {
                 1: [
                     { q: 1, text: "Do you feel well and healthy today?" },
                     { q: 2, text: "Have you ever been refused as a blood donor or told not to donate blood for any reasons?" },
                     { q: 3, text: "Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?" },
                     { q: 4, text: "Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?" },
                     { q: 5, text: "Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?" },
                     { q: 6, text: "In the last 3 DAYS have you taken aspirin?" },
                     { q: 7, text: "In the past 4 WEEKS have you taken any medications and/or vaccinations?" },
                     { q: 8, text: "In the past 3 MONTHS have you donated whole blood, platelets or plasma?" }
                 ],
                 2: [
                     { q: 9, text: "Been to any places in the Philippines or countries infected with ZIKA Virus?" },
                     { q: 10, text: "Had sexual contact with a person who was confirmed to have ZIKA Virus Infection?" },
                     { q: 11, text: "Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?" }
                 ],
                 3: [
                     { q: 12, text: "Received blood, blood products and/or had tissue/organ transplant or graft?" },
                     { q: 13, text: "Had surgical operation or dental extraction?" },
                     { q: 14, text: "Had a tattoo applied, ear and body piercing, acupuncture, needle stick Injury or accidental contact with blood?" },
                     { q: 15, text: "Had sexual contact with high risks individuals or in exchange for material or monetary gain?" },
                     { q: 16, text: "Engaged in unprotected, unsafe or casual sex?" },
                     { q: 17, text: "Had jaundice/hepatitis/personal contact with person who had hepatitis?" },
                     { q: 18, text: "Been incarcerated, Jailed or imprisoned?" },
                     { q: 19, text: "Spent time or have relatives in the United Kingdom or Europe?" }
                 ],
                 4: [
                     { q: 20, text: "Travelled or lived outside of your place of residence or outside the Philippines?" },
                     { q: 21, text: "Taken prohibited drugs (orally, by nose, or by injection)?" },
                     { q: 22, text: "Used clotting factor concentrates?" },
                     { q: 23, text: "Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?" },
                     { q: 24, text: "Had Malaria or Hepatitis in the past?" },
                     { q: 25, text: "Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?" }
                 ],
                 5: [
                     { q: 26, text: "Cancer, blood disease or bleeding disorder (haemophilia)?" },
                     { q: 27, text: "Heart disease/surgery, rheumatic fever or chest pains?" },
                     { q: 28, text: "Lung disease, tuberculosis or asthma?" },
                     { q: 29, text: "Kidney disease, thyroid disease, diabetes, epilepsy?" },
                     { q: 30, text: "Chicken pox and/or cold sores?" },
                     { q: 31, text: "Any other chronic medical condition or surgical operations?" },
                     { q: 32, text: "Have you recently had rash and/or fever? Was/were this/these also associated with arthralgia or arthritis or conjunctivitis?" }
                 ],
                 6: [
                     { q: 33, text: "Are you currently pregnant or have you ever been pregnant?" },
                     { q: 34, text: "When was your last childbirth?" },
                     { q: 35, text: "In the past 1 YEAR, did you have a miscarriage or abortion?" },
                     { q: 36, text: "Are you currently breastfeeding?" },
                     { q: 37, text: "When was your last menstrual period?" }
                 ]
             };
             
             // Define remarks options based on question type
             const modalRemarksOptions = {
                 1: ["None", "Feeling Unwell", "Fatigue", "Fever", "Other Health Issues"],
                 2: ["None", "Low Hemoglobin", "Medical Condition", "Recent Surgery", "Other Refusal Reason"],
                 3: ["None", "HIV Test", "Hepatitis Test", "Other Test Purpose"],
                 4: ["None", "Understood", "Needs More Information"],
                 5: ["None", "Beer", "Wine", "Liquor", "Multiple Types"],
                 6: ["None", "Pain Relief", "Fever", "Other Medication Purpose"],
                 7: ["None", "Antibiotics", "Vitamins", "Vaccines", "Other Medications"],
                 8: ["None", "Red Cross Donation", "Hospital Donation", "Other Donation Type"],
                 9: ["None", "Local Travel", "International Travel", "Specific Location"],
                 10: ["None", "Direct Contact", "Indirect Contact", "Suspected Case"],
                 11: ["None", "Partner Travel History", "Unknown Exposure", "Other Risk"],
                 12: ["None", "Blood Transfusion", "Organ Transplant", "Other Procedure"],
                 13: ["None", "Major Surgery", "Minor Surgery", "Dental Work"],
                 14: ["None", "Tattoo", "Piercing", "Acupuncture", "Blood Exposure"],
                 15: ["None", "High Risk Contact", "Multiple Partners", "Other Risk Factors"],
                 16: ["None", "Unprotected Sex", "Casual Contact", "Other Risk Behavior"],
                 17: ["None", "Personal History", "Family Contact", "Other Exposure"],
                 18: ["None", "Short Term", "Long Term", "Other Details"],
                 19: ["None", "UK Stay", "Europe Stay", "Duration of Stay"],
                 20: ["None", "Local Travel", "International Travel", "Duration"],
                 21: ["None", "Recreational", "Medical", "Other Usage"],
                 22: ["None", "Treatment History", "Current Use", "Other Details"],
                 23: ["None", "HIV", "Hepatitis", "Syphilis", "Malaria"],
                 24: ["None", "Past Infection", "Treatment History", "Other Details"],
                 25: ["None", "Current Infection", "Past Treatment", "Other Details"],
                 26: ["None", "Cancer Type", "Blood Disease", "Bleeding Disorder"],
                 27: ["None", "Heart Disease", "Surgery History", "Current Treatment"],
                 28: ["None", "Active TB", "Asthma", "Other Respiratory Issues"],
                 29: ["None", "Kidney Disease", "Thyroid Issue", "Diabetes", "Epilepsy"],
                 30: ["None", "Recent Infection", "Past Infection", "Other Details"],
                 31: ["None", "Condition Type", "Treatment Status", "Other Details"],
                 32: ["None", "Recent Fever", "Rash", "Joint Pain", "Eye Issues"],
                 33: ["None", "Current Pregnancy", "Past Pregnancy", "Other Details"],
                 34: ["None", "Less than 6 months", "6-12 months ago", "More than 1 year ago"],
                 35: ["None", "Less than 3 months ago", "3-6 months ago", "6-12 months ago"],
                 36: ["None", "Currently Breastfeeding", "Recently Stopped", "Other"],
                 37: ["None", "Within last week", "1-2 weeks ago", "2-4 weeks ago", "More than 1 month ago"]
             };
             
             // Get the field name based on the data structure
             const getModalFieldName = (count) => {
                 const fields = {
                     1: 'feels_well', 2: 'previously_refused', 3: 'testing_purpose_only', 4: 'understands_transmission_risk',
                     5: 'recent_alcohol_consumption', 6: 'recent_aspirin', 7: 'recent_medication', 8: 'recent_donation',
                     9: 'zika_travel', 10: 'zika_contact', 11: 'zika_sexual_contact', 12: 'blood_transfusion',
                     13: 'surgery_dental', 14: 'tattoo_piercing', 15: 'risky_sexual_contact', 16: 'unsafe_sex',
                     17: 'hepatitis_contact', 18: 'imprisonment', 19: 'uk_europe_stay', 20: 'foreign_travel',
                     21: 'drug_use', 22: 'clotting_factor', 23: 'positive_disease_test', 24: 'malaria_history',
                     25: 'std_history', 26: 'cancer_blood_disease', 27: 'heart_disease', 28: 'lung_disease',
                     29: 'kidney_disease', 30: 'chicken_pox', 31: 'chronic_illness', 32: 'recent_fever',
                     33: 'pregnancy_history', 34: 'last_childbirth', 35: 'recent_miscarriage', 36: 'breastfeeding',
                     37: 'last_menstruation'
                 };
                 return fields[count];
             };
             
             // Generate questions for each step
             for (let step = 1; step <= 6; step++) {
                 // Skip step 6 for male donors
                 if (step === 6 && modalIsMale) {
                     continue;
                 }
                 
                 const stepContainer = document.querySelector(`[data-step-container="${step}"]`);
                 if (!stepContainer) {
                     console.error(`Step container ${step} not found`);
                     continue;
                 }
                 
                 const stepQuestions = questionsByStep[step] || [];
                 
                 stepQuestions.forEach(questionData => {
                     const fieldName = getModalFieldName(questionData.q);
                     const value = modalMedicalHistoryData ? modalMedicalHistoryData[fieldName] : null;
                     const remarks = modalMedicalHistoryData ? modalMedicalHistoryData[fieldName + '_remarks'] : null;
                     
                     console.log(`Question ${questionData.q}: fieldName=${fieldName}, value=${value} (type: ${typeof value}), remarks=${remarks}`);
                     if (modalMedicalHistoryData) {
                         console.log(`Available fields in medical history data:`, Object.keys(modalMedicalHistoryData));
                         console.log(`Looking for field: ${fieldName}, found: ${fieldName in modalMedicalHistoryData}`);
                     }
                     
                     // Create a form group for each question
                     const questionRow = document.createElement('div');
                     questionRow.className = 'form-group';
                     questionRow.innerHTML = `
                         <div class="question-number">${questionData.q}</div>
                         <div class="question-text">${questionData.text}</div>
                         <div class="radio-cell">
                             <label class="radio-container">
                                 <input type="radio" name="q${questionData.q}" value="Yes" ${value === true ? 'checked' : ''} ${modalRequiredAttr}>
                                 <span class="checkmark"></span>
                             </label>
                         </div>
                         <div class="radio-cell">
                             <label class="radio-container">
                                 <input type="radio" name="q${questionData.q}" value="No" ${value === false ? 'checked' : ''} ${modalRequiredAttr}>
                                 <span class="checkmark"></span>
                             </label>
                         </div>
                         <div class="remarks-cell">
                             <select class="remarks-input" name="q${questionData.q}_remarks" ${modalRequiredAttr}>
                                 ${modalRemarksOptions[questionData.q].map(option => 
                                     `<option value="${option}" ${remarks === option ? 'selected' : ''}>${option}</option>`
                                 ).join('')}
                             </select>
                         </div>
                     `;
                     
                     stepContainer.appendChild(questionRow);
                 });
             }
             
             // Initialize step navigation
             initializeModalStepNavigation(modalUserRole, modalIsMale);
             
             // Make form fields read-only for interviewers and physicians
             if (modalUserRole === 'interviewer' || modalUserRole === 'physician') {
                 setTimeout(() => {
                     const radioButtons = document.querySelectorAll('#modalMedicalHistoryForm input[type="radio"]');
                     const selectFields = document.querySelectorAll('#modalMedicalHistoryForm select.remarks-input');
                     
                     radioButtons.forEach(radio => {
                         radio.disabled = true;
                     });
                     
                     selectFields.forEach(select => {
                         select.disabled = true;
                     });
                     
                     console.log("Made form fields read-only for role:", modalUserRole);
                 }, 100);
             }
         }

         
         // Initialize step navigation for the modal
         function initializeModalStepNavigation(userRole, isMale) {
             let currentStep = 1;
             const totalSteps = isMale ? 5 : 6;
             
             const stepIndicators = document.querySelectorAll('#modalStepIndicators .step');
             const stepConnectors = document.querySelectorAll('#modalStepIndicators .step-connector');
             const formSteps = document.querySelectorAll('#modalMedicalHistoryForm .form-step');
             const prevButton = document.getElementById('modalPrevButton');
             const nextButton = document.getElementById('modalNextButton');
             const errorMessage = document.getElementById('modalValidationError');
             
             // Hide step 6 for male donors
             if (isMale) {
                 const step6 = document.getElementById('modalStep6');
                 const line56 = document.getElementById('modalLine5-6');
                 if (step6) step6.style.display = 'none';
                 if (line56) line56.style.display = 'none';
             }
             
             function updateStepDisplay() {
                 // Hide all steps
                 formSteps.forEach(step => {
                     step.classList.remove('active');
                 });
                 
                 // Show current step
                 const activeStep = document.querySelector(`#modalMedicalHistoryForm .form-step[data-step="${currentStep}"]`);
                 if (activeStep) {
                     activeStep.classList.add('active');
                 }
                 
                 // Update step indicators
                 stepIndicators.forEach(indicator => {
                     const step = parseInt(indicator.getAttribute('data-step'));
                     
                     if (step < currentStep) {
                         indicator.classList.add('completed');
                         indicator.classList.add('active');
                     } else if (step === currentStep) {
                         indicator.classList.add('active');
                         indicator.classList.remove('completed');
                     } else {
                         indicator.classList.remove('active');
                         indicator.classList.remove('completed');
                     }
                 });
                 
                 // Update step connectors
                 stepConnectors.forEach((connector, index) => {
                     if (index + 1 < currentStep) {
                         connector.classList.add('active');
                     } else {
                         connector.classList.remove('active');
                     }
                 });
                 
                 // Update buttons
                 if (currentStep === 1) {
                     if (prevButton) prevButton.style.display = 'none';
                 } else {
                     if (prevButton) prevButton.style.display = 'block';
                 }
                 
                 if (currentStep === totalSteps && nextButton) {
                     if (userRole === 'reviewer') {
                         nextButton.innerHTML = 'DECLINE';
                         nextButton.onclick = () => submitModalForm('decline');
                         
                         // Add approve button
                         if (!document.getElementById('modalApproveButton')) {
                             const approveBtn = document.createElement('button');
                             approveBtn.className = 'next-button';
                             approveBtn.innerHTML = 'APPROVE';
                             approveBtn.id = 'modalApproveButton';
                             approveBtn.onclick = () => submitModalForm('approve');
                             nextButton.parentNode.appendChild(approveBtn);
                         }
                     } else {
                         nextButton.innerHTML = 'NEXT';
                         nextButton.onclick = () => submitModalForm('next');
                     }
                 } else if (nextButton) {
                     nextButton.innerHTML = 'Next →';
                     nextButton.onclick = () => {
                         if (validateCurrentModalStep()) {
                             currentStep++;
                             updateStepDisplay();
                             if (errorMessage) errorMessage.style.display = 'none';
                         }
                     };
                     
                     // Remove approve button if it exists
                     const approveBtn = document.getElementById('modalApproveButton');
                     if (approveBtn) {
                         approveBtn.remove();
                     }
                 }
             }
             
             function validateCurrentModalStep() {
                 const currentStepElement = document.querySelector(`#modalMedicalHistoryForm .form-step[data-step="${currentStep}"]`);
                 if (!currentStepElement) return false;
                 
                 const radioGroups = {};
                 const radios = currentStepElement.querySelectorAll('input[type="radio"]');
                 
                 radios.forEach(radio => {
                     radioGroups[radio.name] = true;
                 });
                 
                 let allAnswered = true;
                 for (const groupName in radioGroups) {
                     const answered = document.querySelector(`input[name="${groupName}"]:checked`) !== null;
                     if (!answered) {
                         allAnswered = false;
                         break;
                     }
                 }
                 
                 if (!allAnswered && errorMessage) {
                     errorMessage.style.display = 'block';
                     errorMessage.textContent = 'Please answer all questions before proceeding to the next step.';
                     return false;
                 }
                 
                 return true;
             }
             
             // Bind event handlers
             if (prevButton) {
                 prevButton.addEventListener('click', () => {
                     if (currentStep > 1) {
                         currentStep--;
                         updateStepDisplay();
                         if (errorMessage) errorMessage.style.display = 'none';
                     }
                 });
             }
             
             // Initialize display
             updateStepDisplay();
         }

         // Function to handle modal form submission
         function submitModalForm(action) {
             let message = '';
             if (action === 'approve') {
                 message = 'Are you sure you want to approve this donor and proceed to the declaration form?';
             } else if (action === 'decline') {
                 message = 'Are you sure you want to decline this donor?';
             } else if (action === 'next') {
                 message = 'Do you want to proceed to the declaration form?';
             }
             
             if (confirm(message)) {
                 const actionInput = document.getElementById('modalSelectedAction');
                 if (actionInput) actionInput.value = action;
                 
                 // Submit the form via AJAX
                 const form = document.getElementById('modalMedicalHistoryForm');
                 const formData = new FormData(form);
                 
                 fetch('../../../src/views/forms/medical-history-process.php', {
                     method: 'POST',
                     body: formData
                 })
                 .then(response => response.json())
                 .then(data => {
                     if (data.success) {
                         if (action === 'next' || action === 'approve') {
                             // Close medical history modal and open declaration form modal
                             const medicalModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                             medicalModal.hide();
                             
                             // Get the current donor_id
                             const donorIdInput = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
                             const donorId = donorIdInput ? donorIdInput.value : null;
                             
                             if (donorId) {
                                 showDeclarationFormModal(donorId);
                             } else {
                                 alert('Error: Donor ID not found');
                             }
                         } else if (action === 'decline') {
                             // Close modal and refresh the main page for decline only
                             const modal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                             modal.hide();
                             window.location.reload();
                         }
                     } else {
                         alert('Error: ' + (data.message || 'Unknown error occurred'));
                     }
                 })
                 .catch(error => {
                     console.error('Error:', error);
                     alert('An error occurred while processing the form.');
                 });
             }
         }
         
         // Function to show declaration form modal
         function showDeclarationFormModal(donorId) {
             console.log('Showing declaration form modal for donor ID:', donorId);
             
             const declarationModal = new bootstrap.Modal(document.getElementById('declarationFormModal'));
             const modalContent = document.getElementById('declarationFormModalContent');
             
             // Reset modal content to loading state
             modalContent.innerHTML = `
                 <div class="d-flex justify-content-center align-items-center" style="min-height: 200px;">
                     <div class="text-center">
                         <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                             <span class="visually-hidden">Loading...</span>
                         </div>
                         <p class="mt-3 mb-0">Loading Declaration Form...</p>
                     </div>
                 </div>`;
             
             // Show the modal
             declarationModal.show();
             
             // Load the declaration form content
             fetch(`../../../src/views/forms/declaration-form-modal-content.php?donor_id=${donorId}`)
                 .then(response => {
                     console.log('Declaration form response status:', response.status);
                     if (!response.ok) {
                         throw new Error(`HTTP error! status: ${response.status}`);
                     }
                     return response.text();
                 })
                 .then(data => {
                     console.log('Declaration form content loaded successfully');
                     modalContent.innerHTML = data;
                     
                                         // Ensure print function is available globally
                    window.printDeclaration = function() {
                        console.log('Print function called');
                        const printWindow = window.open('', '_blank');
                        const content = document.querySelector('.declaration-header').outerHTML + 
                                       document.querySelector('.donor-info').outerHTML + 
                                       document.querySelector('.declaration-content').outerHTML + 
                                       document.querySelector('.signature-section').outerHTML;
                        
                        printWindow.document.write(`
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <title>Declaration Form - Philippine Red Cross</title>
                                <style>
                                    body { 
                                        font-family: Arial, sans-serif; 
                                        padding: 20px; 
                                        line-height: 1.5;
                                    }
                                    .declaration-header { 
                                        text-align: center; 
                                        margin-bottom: 30px; 
                                        border-bottom: 2px solid #9c0000;
                                        padding-bottom: 20px;
                                    }
                                    .declaration-header h2, .declaration-header h3 { 
                                        color: #9c0000; 
                                        margin: 5px 0; 
                                        font-weight: bold;
                                    }
                                    .donor-info { 
                                        background-color: #f8f9fa; 
                                        padding: 20px; 
                                        margin: 20px 0; 
                                        border: 1px solid #ddd; 
                                        border-radius: 8px;
                                    }
                                    .donor-info-row { 
                                        display: flex; 
                                        margin-bottom: 15px; 
                                        gap: 20px; 
                                        flex-wrap: wrap;
                                    }
                                    .donor-info-item { 
                                        flex: 1; 
                                        min-width: 200px;
                                    }
                                    .donor-info-label { 
                                        font-weight: bold; 
                                        font-size: 14px; 
                                        color: #555; 
                                        margin-bottom: 5px;
                                    }
                                    .donor-info-value { 
                                        font-size: 16px; 
                                        color: #333; 
                                    }
                                    .declaration-content { 
                                        line-height: 1.8; 
                                        margin: 30px 0; 
                                        text-align: justify;
                                    }
                                    .declaration-content p { 
                                        margin-bottom: 20px; 
                                    }
                                    .bold { 
                                        font-weight: bold; 
                                        color: #9c0000; 
                                    }
                                    .signature-section { 
                                        margin-top: 40px; 
                                        display: flex; 
                                        justify-content: space-between; 
                                        page-break-inside: avoid;
                                    }
                                    .signature-box { 
                                        text-align: center; 
                                        padding: 15px 0; 
                                        border-top: 2px solid #333; 
                                        width: 250px; 
                                        font-weight: 500;
                                    }
                                    @media print {
                                        body { margin: 0; }
                                        .declaration-header { page-break-after: avoid; }
                                        .signature-section { page-break-before: avoid; }
                                    }
                                </style>
                            </head>
                            <body>${content}</body>
                            </html>
                        `);
                        printWindow.document.close();
                        printWindow.focus();
                        setTimeout(() => {
                            printWindow.print();
                        }, 500);
                    };
                    
                    // Ensure submit function is available globally
                    window.submitDeclarationForm = function() {
                        console.log('Submit declaration form called');
                        if (confirm('Are you sure you want to complete the donor registration?')) {
                            const form = document.getElementById('modalDeclarationForm');
                            if (!form) {
                                alert('Form not found. Please try again.');
                                return;
                            }
                            
                            document.getElementById('modalDeclarationAction').value = 'complete';
                            
                            // Submit the form via AJAX
                            const formData = new FormData(form);
                            
                            fetch('../../../src/views/forms/declaration-form-process.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Donor registration completed successfully!');
                                    console.log('Registration complete, reloading page...');
                                    
                                    // Force complete page reload
                                    window.location.href = window.location.href;
                                } else {
                                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while processing the form.');
                            });
                        }
                    };
                 })
                 .catch(error => {
                     console.error('Error loading declaration form:', error);
                     modalContent.innerHTML = `
                         <div class="alert alert-danger text-center" style="margin: 50px 20px;">
                             <h5 class="alert-heading">
                                 <i class="fas fa-exclamation-triangle"></i> Error Loading Form
                             </h5>
                             <p>Unable to load the declaration form. Please try again.</p>
                             <hr>
                             <p class="mb-0">Error details: ${error.message}</p>
                             <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">
                                 <i class="fas fa-times"></i> Close
                             </button>
                         </div>`;
                 });
         }

        });
    </script>
</body>
</html>