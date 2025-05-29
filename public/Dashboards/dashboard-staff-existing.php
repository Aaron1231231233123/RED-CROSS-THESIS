<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

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
    $donors = json_decode($response, true) ?: [];
    error_log("Decoded donors count: " . count($donors));
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
    $all_donor_ids = array_column($donors, 'donor_id');
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
}

// Group donors by unique identity (surname, first_name, middle_name, birthdate)
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
                            <a class="nav-link" href="dashboard-staff-donor-submission.php">
                                System Registration
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_staff_roles === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-staff-medical-history-submissions.php">
                                New Donor
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'physician'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-staff-physical-submission.php">
                                Physical Exam Submissions
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
                            <a class="nav-link  active" href="dashboard-staff-existing.php">
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
                                    <th>Donation Date</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Gender</th>
                                    <th>Age</th>
                                    <th>Gateway</th>
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
                                        $gateway = isset($donor['registration_channel']) ? ($donor['registration_channel'] === 'Mobile' ? 'Mobile' : 'PRC Portal') : 'PRC Portal';
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
                                            <td><?php echo htmlspecialchars($gateway); ?></td>
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
                window.location.href = '../../src/views/forms/donor-form-modal.php';
            }, 800);
        }
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            const donorTableBody = document.getElementById('donorTableBody');
            const deferralStatusModal = new bootstrap.Modal(document.getElementById('deferralStatusModal'));
            const deferralStatusContent = document.getElementById('deferralStatusContent');
            const screeningInfo = document.getElementById('screeningInfo');
            const interviewDateSpan = document.getElementById('interviewDate');
            const bodyWeightSpan = document.getElementById('bodyWeight');
            const proceedButton = document.getElementById('proceedToMedicalHistory');
            
            let currentDonorId = null;
            
            // Store original rows for search reset
            const originalRows = Array.from(donorTableBody.getElementsByTagName('tr'));
            
            // Add click event to all clickable rows
            document.querySelectorAll('.clickable-row').forEach(row => {
                row.addEventListener('click', function() {
                    const donorId = this.getAttribute('data-donor-id');
                    if (donorId) {
                        currentDonorId = donorId;
                        
                        // Show the modal with loading state
                        deferralStatusContent.innerHTML = `
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                    </div>
                            </div>`;
                        screeningInfo.style.display = 'none';
                        deferralStatusModal.show();
                        
                        // Fetch donor info and deferral status
                        fetchDonorStatusInfo(donorId);
                    }
                });
            });
            
            // Function to fetch donor status information
            function fetchDonorStatusInfo(donorId) {
                // First, fetch donor information
                fetch(`../../assets/php_func/fetch_donor_info.php?donor_id=${donorId}`)
                    .then(response => response.json())
                    .then(donorData => {
                        // Next, check physical examination table for deferral status
                        fetch(`../../assets/php_func/check_deferral_status.php?donor_id=${donorId}`)
                            .then(response => response.json())
                            .then(deferralData => {
                                displayDonorInfo(donorData, deferralData);
                                
                                // After getting deferral info, fetch screening info
                                fetch(`../../assets/php_func/fetch_screening_info.php?donor_id=${donorId}`)
                                    .then(response => response.json())
                                    .then(screeningData => {
                                        if (screeningData.success && screeningData.data) {
                                            screeningInfo.style.display = 'block';
                                            interviewDateSpan.textContent = screeningData.data.interview_date || '-';
                                            bodyWeightSpan.textContent = screeningData.data.body_weight ? `${screeningData.data.body_weight} kg` : '-';
                    } else {
                                            screeningInfo.style.display = 'none';
                                        }
                                    })
                                    .catch(error => {
                                        console.error("Error fetching screening info:", error);
                                        screeningInfo.style.display = 'none';
                                    });
                            })
                            .catch(error => {
                                console.error("Error checking deferral status:", error);
                                deferralStatusContent.innerHTML = `<div class="alert alert-danger">Error checking deferral status: ${error.message}</div>`;
                            });
                    })
                    .catch(error => {
                        console.error("Error fetching donor info:", error);
                        deferralStatusContent.innerHTML = `<div class="alert alert-danger">Error fetching donor information: ${error.message}</div>`;
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
            
            // Handle proceed button click
            proceedButton.addEventListener('click', function() {
                if (currentDonorId) {
                    // Get hashed version of donor ID
                    fetch(`../../assets/php_func/hash_donor_id.php?donor_id=${currentDonorId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.hash) {
                                window.location.href = `../../src/views/forms/medical-history.php?hid=${data.hash}`;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                }
            });
            
            // Update placeholder based on selected category
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
                performSearch();
            });
        });
    </script>
</body>
</html>