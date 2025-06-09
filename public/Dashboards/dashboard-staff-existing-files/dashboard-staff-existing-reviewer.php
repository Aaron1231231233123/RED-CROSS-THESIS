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

// First get eligibility data
$query_url = SUPABASE_URL . '/rest/v1/eligibility?select=*&order=start_date.desc';

$ch = curl_init($query_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

if ($response === false || is_null(json_decode($response, true))) {
    error_log("Error fetching eligibility data from Supabase: " . curl_error($ch));
    $eligibility_records = [];
} else {
    $eligibility_records = json_decode($response, true) ?: [];
    
    // Get unique donor IDs
    $donor_ids = array_unique(array_column($eligibility_records, 'donor_id'));
    
    // Now fetch donor_form data for these donor IDs
    $donor_query_url = SUPABASE_URL . '/rest/v1/donor_form?select=*&donor_id=in.(' . implode(',', $donor_ids) . ')';
    
    $ch = curl_init($donor_query_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $donor_response = curl_exec($ch);
    curl_close($ch);
    
    if ($donor_response !== false && !is_null(json_decode($donor_response, true))) {
        $donor_records = json_decode($donor_response, true) ?: [];
        
        // Create a lookup array for donor information
        $donor_lookup = [];
        foreach ($donor_records as $donor) {
            if (isset($donor['donor_id'])) {
                $donor_lookup[$donor['donor_id']] = $donor;
            }
        }
        
        // Attach donor information to eligibility records
        foreach ($eligibility_records as &$record) {
            if (isset($record['donor_id']) && isset($donor_lookup[$record['donor_id']])) {
                $record['donor_form'] = $donor_lookup[$record['donor_id']];
            }
        }
    }
    
    // Filter out duplicate donor_ids, keeping only the latest record
    $unique_donors = [];
    foreach ($eligibility_records as $record) {
        if (isset($record['donor_id']) && isset($record['donor_form'])) {
            $donor_id = $record['donor_id'];
            if (!isset($unique_donors[$donor_id]) || 
                (isset($record['start_date']) && isset($unique_donors[$donor_id]['start_date']) &&
                strtotime($record['start_date']) > strtotime($unique_donors[$donor_id]['start_date']))) {
                $unique_donors[$donor_id] = $record;
            }
        }
    }
    $eligibility_records = array_values($unique_donors);
}

// Fetch medical history records to calculate status counts
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

// Initialize counters
$incoming_count = 0;
$approved_count = 0;
$declined_count = 0;

// Arrays to store donor IDs by status
$donor_with_medical_history = [];
$donor_with_approved_medical_history = [];
$donor_with_declined_medical_history = [];

if ($response === false || is_null(json_decode($response, true))) {
    error_log("Error fetching medical history data from Supabase");
} else {
    $medical_histories = json_decode($response, true) ?: [];
    error_log("Decoded medical histories count: " . count($medical_histories));
    
    // Process medical histories to get counts
    $incoming_with_null_approval = [];

    foreach ($medical_histories as $history) {
        if (isset($history['donor_id'])) {
            if (isset($history['medical_approval'])) {
                if ($history['medical_approval'] === 'Approved') {
                    $approved_count++;
                    $donor_with_approved_medical_history[] = $history['donor_id'];
                    $donor_with_medical_history[] = $history['donor_id'];
                } else if ($history['medical_approval'] === null) {
                    // If medical_approval is null, treat it as incoming
                    $incoming_with_null_approval[] = $history['donor_id'];
                } else {
                    $declined_count++;
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
    
    // Calculate incoming count (donors without any medical history or with null approval)
    $all_donor_ids = array_column($eligibility_records, 'donor_id');
    $processed_donors = array_merge($donor_with_approved_medical_history, $donor_with_declined_medical_history);
    $incoming_donors = array_diff($all_donor_ids, $processed_donors);
    $incoming_count = count($incoming_donors);
    
    // Update counters to reflect unique donors
    $approved_count = count($donor_with_approved_medical_history);
    $declined_count = count($donor_with_declined_medical_history);
    
    // Log the detailed counts for debugging
    error_log("Medical History Counts - Total donors: " . count($all_donor_ids));
    error_log("Medical History Counts - Approved: $approved_count, Declined: $declined_count, Incoming: $incoming_count");
    error_log("Medical History Counts - Donors with null approval: " . count($incoming_with_null_approval));
    error_log("Medical History Counts - Processed donors: " . count($processed_donors));
    error_log("Incoming count: $incoming_count, Approved count: $approved_count, Declined count: $declined_count");
}

// Handle status filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'incoming';

// Filter donors based on status if needed
$filtered_records = [];
if ($status_filter === 'incoming') {
    foreach ($eligibility_records as $record) {
        if (isset($record['donor_id'])) {
            $filtered_records[] = $record;
        }
    }
    $eligibility_records = $filtered_records;
} elseif ($status_filter === 'approved') {
    foreach ($eligibility_records as $record) {
        if (isset($record['collection_successful']) && $record['collection_successful'] === true) {
            $filtered_records[] = $record;
        }
    }
    $eligibility_records = $filtered_records;
} elseif ($status_filter === 'declined') {
    foreach ($eligibility_records as $record) {
        if (isset($record['collection_successful']) && $record['collection_successful'] === false) {
            $filtered_records[] = $record;
        }
    }
    $eligibility_records = $filtered_records;
}

// Group donors by unique identity (surname, first_name, middle_name, birthdate)
$donorGroups = [];
foreach ($eligibility_records as $record) {
    $key = ($record['donor_form']['surname'] ?? '') . '|' . 
           ($record['donor_form']['first_name'] ?? '') . '|' . 
           ($record['donor_form']['middle_name'] ?? '') . '|' . 
           ($record['donor_form']['birthdate'] ?? '');
    
    if (!isset($donorGroups[$key])) {
        $donorGroups[$key] = [
            'info' => $record['donor_form'],
            'count' => 1,
            'latest_submission' => $record['start_date'] ?? null
        ];
    } else {
        $donorGroups[$key]['count']++;
        
        // Keep track of the latest submission
        if (isset($record['start_date']) && 
            (!isset($donorGroups[$key]['latest_submission']) || 
            strtotime($record['start_date']) > strtotime($donorGroups[$key]['latest_submission']))) {
            $donorGroups[$key]['latest_submission'] = $record['start_date'];
            $donorGroups[$key]['info'] = $record['donor_form'];
        }
    }
}

// Convert back to array for pagination
$donors = [];
foreach ($donorGroups as $group) {
    $donor_info = $group['info'];
    $donor_info['donation_count'] = $group['count'];
    $donor_info['latest_submission'] = $group['latest_submission'];
    $donors[] = $donor_info;
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
            <h4 class="header-title">Staff Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
            <button class="register-btn" onclick="showConfirmationModal()">
                Register Donor
            </button>
        </div>

        <div class="row g-0">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4>Staff</h4>
                <ul class="nav flex-column">
                    
                <?php if ($user_staff_roles === 'interviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard-staff-donor-submission.php">
                                System Registration
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_staff_roles === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard-staff-medical-history-submissions.php">
                                New Donor
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'physician'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard-staff-physical-submission.php">
                                Physical Exam Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'phlebotomist'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard-staff-blood-collection-submission.php">
                                Blood Collection Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                            <a class="nav-link  active" href="dashboard-staff-existing-reviewer.php">
                                Existing Donor
                            </a>
                        </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard-staff-history.php">Donor History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../assets/php_func/logout.php">Logout</a>
                    </li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <div class="content-wrapper">
                    <div class="welcome-section">
                        <h2 class="welcome-title">Welcome, Staff!</h2>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="dashboard-staff-status">
                        <a href="?status=incoming" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'incoming' ? 'active' : ''); ?>">
                            <p class="dashboard-staff-count"><?php echo $incoming_count; ?></p>
                            <p class="dashboard-staff-title">Incoming Registrations</p>
                        </a>
                        <a href="?status=approved" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'approved' ? 'active' : ''); ?>">
                            <p class="dashboard-staff-count"><?php echo $approved_count; ?></p>
                            <p class="dashboard-staff-title">Approved</p>
                        </a>
                        <a href="?status=declined" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'declined' ? 'active' : ''); ?>">
                            <p class="dashboard-staff-count"><?php echo $declined_count; ?></p>
                            <p class="dashboard-staff-title">Declined</p>
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
                                    <th>No.</th>
                                    <th>Donation Date</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Gender</th>
                                    <th>Age</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php if (!empty($eligibility_records)): ?>
                                    <?php foreach($eligibility_records as $index => $record): ?>
                                        <?php
                                        // Calculate remaining days for the table
                                        $remainingDays = 0;
                                        if (!empty($record['start_date'])) {
                                            $donationDate = new DateTime($record['start_date']);
                                            $today = new DateTime();
                                            $eligibleDate = clone $donationDate;
                                            $eligibleDate->modify('+90 days');
                                            
                                            if ($today < $eligibleDate) {
                                                $remainingDays = $today->diff($eligibleDate)->days;
                                            }
                                        }
                                        ?>
                                        <tr class="clickable-row">
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo !empty($record['start_date']) ? date('F j, Y', strtotime($record['start_date'])) : 'N/A'; ?></td>
                                            <td><?php echo !empty($record['donor_form']['surname']) ? htmlspecialchars($record['donor_form']['surname']) : 'N/A'; ?></td>
                                            <td><?php echo !empty($record['donor_form']['first_name']) ? htmlspecialchars($record['donor_form']['first_name']) : 'N/A'; ?></td>
                                            <td><?php echo !empty($record['donor_form']['sex']) ? htmlspecialchars(ucfirst($record['donor_form']['sex'])) : 'N/A'; ?></td>
                                            <td><?php echo !empty($record['donor_form']['age']) ? htmlspecialchars($record['donor_form']['age']) : 'N/A'; ?></td>
                                            <td><?php echo $remainingDays > 0 ? 'Ineligible' : 'Eligible'; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-info btn-sm view-donor-btn" data-donor-id="<?php echo $record['donor_id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Existing Donor Information Modal -->
    <div class="modal fade" id="donorModal" tabindex="-1" aria-labelledby="donorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="donorModalLabel">Existing Donor Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="deferralStatusContent">
                        <!-- Content will be dynamically loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" onclick="proceedToInterviewer()">Proceed to Interviewer</button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Make these functions available in the global scope
    window.calculateRemainingDays = function(startDate) {
        if (!startDate) return 0;
        
        const donationDate = new Date(startDate);
        const today = new Date();
        
        // Add 90 days to donation date
        const eligibleDate = new Date(donationDate);
        eligibleDate.setDate(eligibleDate.getDate() + 90);
        
        // Calculate remaining days
        if (today < eligibleDate) {
            const diffTime = eligibleDate - today;
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        }
        
        return 0;
    };

    window.displayDonorInfo = function(donor, eligibility) {
        // Calculate remaining days
        const remainingDays = calculateRemainingDays(eligibility.start_date);
        
        // Format the last donation date
        const lastDonationDate = eligibility.start_date ? new Date(eligibility.start_date).toLocaleDateString() : 'N/A';
        
        const donorInfoHTML = `
            <div class="mb-4">
                <h6 class="text-danger mb-3">Donor Information</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" value="${donor.surname || ''}, ${donor.first_name || ''}" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Birth Date</label>
                            <input type="text" class="form-control" value="${donor.birthdate ? new Date(donor.birthdate).toLocaleDateString() : ''}" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Age</label>
                            <input type="text" class="form-control" value="${donor.age || ''}" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Civil Status</label>
                            <input type="text" class="form-control" value="${donor.civil_status || ''}" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Sex</label>
                            <input type="text" class="form-control" value="${donor.sex || ''}" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Nationality</label>
                            <input type="text" class="form-control" value="${donor.nationality || ''}" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h6 class="text-danger mb-3">Eligibility Information</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-control" value="${remainingDays > 0 ? 'Ineligible' : 'Eligible'}" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Last Donation Date</label>
                            <input type="text" class="form-control" value="${lastDonationDate}" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" value="${remainingDays > 0 ? 'Temporarily Deferred - Donation Interval' : 'None'}" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Eligible After</label>
                            <input type="text" class="form-control" value="${remainingDays > 0 ? remainingDays + ' days' : 'Eligible Now'}" readonly>
                        </div>
                    </div>
                </div>
            </div>`;

        document.getElementById('deferralStatusContent').innerHTML = donorInfoHTML;
    };

    window.fetchDonorInfo = function(donorId) {
        // Show loading state
        document.getElementById('deferralStatusContent').innerHTML = '<div class="text-center"><div class="spinner-border text-danger" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        // Show the modal immediately
        const donorModal = new bootstrap.Modal(document.getElementById('donorModal'));
        donorModal.show();

        // Fetch donor information
        fetch(`/REDCROSS/public/api/get_donor_info.php?donor_id=${encodeURIComponent(donorId)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.donor && data.eligibility) {
                    displayDonorInfo(data.donor, data.eligibility);
                } else {
                    throw new Error(data.message || 'No donor information available');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('deferralStatusContent').innerHTML = `
                    <div class="alert alert-danger">
                        Error fetching donor information: ${error.message}
                    </div>`;
            });
    };

    window.proceedToInterviewer = function() {
        // Add your logic here to proceed to interviewer
        console.log('Proceeding to interviewer...');
    };

    // Add event listeners after DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Add click handlers to all view buttons
        document.querySelectorAll('.view-donor-btn').forEach(button => {
            button.addEventListener('click', function() {
                const donorId = this.getAttribute('data-donor-id');
                fetchDonorInfo(donorId);
            });
        });
    });
    </script>
</body>
</html>