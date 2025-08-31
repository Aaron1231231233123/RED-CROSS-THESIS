<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require '../../../assets/php_func/user_roles_staff.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /RED-CROSS-THESIS/public/login.php");
    exit();
}
// Check for correct role (admin with role_id 3)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// Add pagination settings
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Initialize the donors array
$donors = [];

// Get donor history data by joining through eligibility table
$ch = curl_init();

// SIMPLIFIED APPROACH: Get all data in separate, simple queries then apply hierarchy logic
// Step 1: Get all blood collections
$blood_ch = curl_init();
curl_setopt_array($blood_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?select=*&order=start_time.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$blood_response = curl_exec($blood_ch);
$blood_collections = json_decode($blood_response, true) ?: [];
curl_close($blood_ch);

// Step 2: Get all physical exams
$physical_ch = curl_init();
curl_setopt_array($physical_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?select=*&order=created_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$physical_response = curl_exec($physical_ch);
$physical_exams = json_decode($physical_response, true) ?: [];
curl_close($physical_ch);

// Step 3: Get all screening forms
$screening_ch = curl_init();
curl_setopt_array($screening_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=*&order=created_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$screening_response = curl_exec($screening_ch);
$screening_forms = json_decode($screening_response, true) ?: [];
curl_close($screening_ch);

// Step 4: Get all donor forms
$donor_ch = curl_init();
curl_setopt_array($donor_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form?select=*&order=submitted_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$donor_response = curl_exec($donor_ch);
$donor_forms = json_decode($donor_response, true) ?: [];
curl_close($donor_ch);

// Create lookup arrays for faster processing
$donors_by_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_id[$donor['donor_id']] = $donor;
}

$screenings_by_donor = [];
foreach ($screening_forms as $screening) {
    $screenings_by_donor[$screening['donor_form_id']] = $screening;
}

$physicals_by_donor = [];
foreach ($physical_exams as $physical) {
    $physicals_by_donor[$physical['donor_id']] = $physical;
}

$blood_by_screening = [];
foreach ($blood_collections as $blood) {
    $blood_by_screening[$blood['screening_id']] = $blood;
}

// Create sets to track donors already processed at higher priority levels
$donors_with_blood = [];
$donors_with_physical = [];

error_log("Blood collections: " . count($blood_collections));
error_log("Physical exams: " . count($physical_exams));
error_log("Screening forms: " . count($screening_forms));
error_log("Donor forms: " . count($donor_forms));

// Process the donor history with HIERARCHY PRIORITY
$donor_history = [];
$counter = 1;

// FOR PHYSICAL HISTORY: Use SAME algorithm as donor history but NO physical exam details in modal
// Show blood collections and physical exams (same data) but modals won't show physical examination details

$donors_with_blood = [];
$donors_with_physical = [];

// PRIORITY 1: Process Blood Collections (Highest Priority)
foreach ($blood_collections as $blood_info) {
    $screening_id = $blood_info['screening_id'];
    
    // Find the screening form for this blood collection
    $screening_info = null;
    foreach ($screening_forms as $screening) {
        if ($screening['screening_id'] == $screening_id) {
            $screening_info = $screening;
            break;
        }
    }
    
    if (!$screening_info) {
        continue;
    }
    
    // Find the donor form for this screening
    $donor_info = $donors_by_id[$screening_info['donor_form_id']] ?? null;
    if (!$donor_info) {
        continue;
    }
    
    $donor_id = $donor_info['donor_id'];
    $donors_with_blood[$donor_id] = true; // Mark this donor as processed
    
    // Determine volume for blood collection - improved logic
    $volume = '1 unit'; // Default for any blood collection record
    if (isset($blood_info['amount_taken']) && !empty($blood_info['amount_taken']) && $blood_info['amount_taken'] > 0) {
        $units = $blood_info['amount_taken'];
        $volume = $units . ' unit' . ($units > 1 ? 's' : '');
    } elseif (isset($blood_info['is_successful']) && $blood_info['is_successful'] === false) {
        $volume = 'Collection Failed';
    } elseif (isset($blood_info['status']) && strtolower($blood_info['status']) === 'failed') {
        $volume = 'Collection Failed';
    }
    
    $history_entry = [
        'no' => $counter,
        'donation_date' => $blood_info['start_time'] ?? $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'gateway' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'PRC Portal',
        'volume' => $volume,
        'collection_id' => $blood_info['blood_collection_id'] ?? null,
        'donor_id' => $donor_id,
        'screening_id' => $screening_id,
        'stage' => 'blood_collection',
        // Blood collection details for modal
        'unit_serial_number' => $blood_info['unit_serial_number'] ?? 'N/A',
        'blood_bag_used' => (($blood_info['blood_bag_brand'] ?? '') . ' ' . ($blood_info['blood_bag_type'] ?? '')) ?: 'N/A',
        'amount_collected' => $blood_info['amount_taken'] ?? 'N/A',
        'donor_reaction' => $blood_info['donor_reaction'] ?? 'None',
        'management_done' => $blood_info['management_done'] ?? 'N/A',
        'collection_start_time' => $blood_info['start_time'] ?? 'N/A',
        'collection_end_time' => $blood_info['end_time'] ?? 'N/A'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// PRIORITY 2: Process Physical Exams (Medium Priority) - Only if not already in blood collection
foreach ($physical_exams as $physical_info) {
    $donor_id = $physical_info['donor_id'];
    
    // Skip if this donor already has blood collection (higher priority)
    if (isset($donors_with_blood[$donor_id])) {
        continue;
    }
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) {
        continue;
    }
    
    $donors_with_physical[$donor_id] = true; // Mark this donor as processed
    
    $history_entry = [
        'no' => $counter,
        'donation_date' => $physical_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s'),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'gateway' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'PRC Portal',
        'volume' => 'Ready for Collection',
        'collection_id' => null,
        'donor_id' => $donor_id,
        'physical_exam_id' => $physical_info['physical_exam_id'] ?? null,
        'stage' => 'physical_examination',
        // No blood collection details yet
        'unit_serial_number' => 'N/A',
        'blood_bag_used' => 'N/A',
        'amount_collected' => 'N/A',
        'donor_reaction' => 'None',
        'management_done' => 'N/A',
        'collection_start_time' => 'N/A',
        'collection_end_time' => 'N/A'
    ];
    
    $donor_history[] = $history_entry;
    $counter++;
}

// SKIP PRIORITY 3: Screening Forms (not history - these are still pending physical exam)

// Sort by donation date (most recent first)
usort($donor_history, function($a, $b) {
    return strtotime($b['donation_date']) - strtotime($a['donation_date']);
});

// Renumber after sorting
$counter = 1;
foreach ($donor_history as &$entry) {
    $entry['no'] = $counter++;
}

// If no blood collections found, get recent donor submissions instead
if (empty($donor_history)) {
    $donor_ch = curl_init();
    curl_setopt_array($donor_ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form?select=*&order=submitted_at.desc&limit=50',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $donor_response = curl_exec($donor_ch);
    $donors_data = [];
    
    if ($donor_response !== false) {
        $donors_data = json_decode($donor_response, true) ?: [];
    }
    curl_close($donor_ch);
    
    // Convert donor submissions to history format
    $counter = 1;
    foreach ($donors_data as $donor) {
        $history_entry = [
            'no' => $counter,
            'donation_date' => $donor['submitted_at'],
            'surname' => $donor['surname'] ?? 'N/A',
            'first_name' => $donor['first_name'] ?? 'N/A',
            'gateway' => $donor['registration_channel'] === 'Mobile' ? 'Mobile' : 'PRC Portal',
            'volume' => 'In Registration', // No volume yet as no blood collection
            'collection_id' => null,
            'donor_id' => $donor['donor_id'],
            'blood_type' => 'Pending',
            'donation_type' => 'Pending'
        ];
        
        $donor_history[] = $history_entry;
        $counter++;
    }
}

$total_records = count($donor_history);
$total_pages = ceil($total_records / $records_per_page);

// Slice the array to get only the records for the current page
$donor_history = array_slice($donor_history, $offset, $records_per_page);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Staff Dashboard - Physical History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .sidebar .nav-link:hover {
            background: var(--hover-bg);
            color: var(--active-color) !important;
            border-left-color: var(--active-color);
            border-radius: 4px !important;
        }

        .nav-link.active{
            background-color: var(--active-color);
            color: white !important;
            border-radius: 4px !important;
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
            cursor: pointer;
        }

        .dashboard-staff-tables tbody tr:nth-child(odd) {
            background-color: #f8f9fa;
        }

        .dashboard-staff-tables tbody tr:nth-child(even) {
            background-color: #ffffff;
        }

        .dashboard-staff-tables tbody tr:hover {
            background-color: #f0f0f0;
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

        /* Action button styling */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 4px;
        }

        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }

        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
        
        /* Section header */
        .section-header {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: #333;
        }

        /* Global Button Styling */
        .btn {
            border-radius: 4px !important;
        }
    </style>
</head>
<body class="light-mode">
    <div class="container-fluid p-0">
        <!-- Header -->
        <div class="dashboard-home-header">
            <h4 class="header-title">Physician Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
        </div>

        <div class="row g-0">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4>Physician</h4>
                <ul class="nav flex-column">
                    
                <?php if ($user_staff_roles === 'interviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard-staff-donor-submission.php">
                                New Donor
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
                        <a class="nav-link active" href="">
                            Donor History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../../assets/php_func/logout.php">
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <div class="content-wrapper">
                    <div class="welcome-section">
                        <h2 class="welcome-title">Physical History</h2>
                    </div>
                    
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
                                    <th>Gateway</th>
                                    <th>Volume</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php if($donor_history && is_array($donor_history)): ?>
                                    <?php foreach($donor_history as $history): ?>
                                        <tr class="clickable-row">
                                            <td><?php echo $history['no']; ?></td>
                                            <td><?php 
                                                $date_to_show = '';
                                                if (!empty($history['donation_date'])) {
                                                    $date_to_show = date('F j, Y', strtotime($history['donation_date']));
                                                } else {
                                                    $date_to_show = 'N/A';
                                                }
                                                echo $date_to_show;
                                            ?></td>
                                            <td><?php echo !empty($history['surname']) ? strtoupper(htmlspecialchars($history['surname'])) : 'N/A'; ?></td>
                                            <td><?php echo !empty($history['first_name']) ? htmlspecialchars($history['first_name']) : 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars($history['gateway']); ?></td>
                                            <td><?php echo htmlspecialchars($history['volume']); ?></td>
                                                                                         <td>
                                                <button type="button" class="btn btn-info btn-sm view-history-btn" 
                                                        data-donor-id="<?php echo $history['donor_id']; ?>" 
                                                        data-collection-id="<?php echo $history['collection_id']; ?>"
                                                        data-stage="<?php echo $history['stage']; ?>"
                                                        data-history='<?php echo json_encode($history, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No history records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Donor history navigation">
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
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Blood Collection Modal (Fixed Size Design) -->
    <div class="modal fade" id="bloodCollectionModal" tabindex="-1" aria-labelledby="bloodCollectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); height: 80vh; display: flex; flex-direction: column;">
                <div class="modal-header border-0" id="bloodCollectionHeader" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); border-radius: 12px 12px 0 0; padding: 1rem 1.5rem; flex-shrink: 0;">
                    <div class="d-flex align-items-center w-100">
                        <div class="status-icon me-2" style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-tint" style="font-size: 1.2rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="modal-title text-white mb-1" id="bloodCollectionModalLabel">Blood Collection Complete</h5>
                            <p class="text-white-50 mb-0 small" id="bloodCollectionSubtitle">Collection successfully processed</p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="filter: brightness(0) invert(1);"></button>
                    </div>
                </div>
                <div class="modal-body flex-grow-1" style="padding: 1.5rem; overflow: hidden;">
                    <!-- Progress Indicator -->
                    <div class="progress-section mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Donation Progress</span>
                            <span class="text-muted small" id="bloodProgressText">100% Complete</span>
                        </div>
                        <div class="progress" style="height: 6px; border-radius: 3px;">
                            <div class="progress-bar" id="bloodProgressBar" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>

                    <!-- Status Banner -->
                    <div class="status-banner mb-2 p-3" id="statusBanner" style="background: linear-gradient(90deg, #f8e6e6 0%, #f0d0d0 100%); border-radius: 8px; border-left: 4px solid #b22222;">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-check-circle me-2" style="font-size: 1.2rem; color: #b22222;" id="statusIcon"></i>
                                    <strong style="color: #b22222;" id="statusText">Collection Successful</strong>
                                </div>
                                <p class="text-muted mb-2" id="statusDescription">Blood collection completed without complications</p>
                                <div class="collection-summary">
                                    <strong class="text-dark">Result:</strong> 
                                    <span style="color: #b22222;" id="collectionResult">Successfully collected blood for donation</span>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="result-icon" style="width: 60px; height: 60px; background: rgba(178, 34, 34, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;" id="resultIcon">
                                    <i class="fas fa-tint" style="font-size: 1.5rem; color: #b22222;"></i>
                                </div>
                                <small class="text-muted mt-1 d-block" id="resultText">Collection Complete</small>
                            </div>
                        </div>
                    </div>

                    <!-- Collection Information -->
                    <div class="info-section">
                        <h6 class="section-title mb-2" style="color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px;">
                            <i class="fas fa-tint me-1" style="color: #6c757d;"></i>Collection Details
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-item mb-2" style="background: #f8f9fa; padding: 0.75rem; border-radius: 6px; border: 1px solid #e9ecef;">
                                    <label class="detail-label small">Collection Date & Time</label>
                                    <div class="detail-value small" id="bloodModalDateTime">July 4, 2025 at 05:34 AM - 05:40 AM</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item mb-2" style="background: #f8f9fa; padding: 0.75rem; border-radius: 6px; border: 1px solid #e9ecef;">
                                    <label class="detail-label small">Amount Collected</label>
                                    <div class="detail-value highlight small" id="bloodModalAmount" style="color: #b22222; font-weight: 600;">1 unit</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item mb-2" style="background: #f8f9fa; padding: 0.75rem; border-radius: 6px; border: 1px solid #e9ecef;">
                                    <label class="detail-label small">Unit Serial Number</label>
                                    <div class="detail-value monospace small" id="bloodModalSerial">BC-20250703-1025</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item mb-2" style="background: #f8f9fa; padding: 0.75rem; border-radius: 6px; border: 1px solid #e9ecef;">
                                    <label class="detail-label small">Blood Bag Used</label>
                                    <div class="detail-value small" id="bloodModalBag">KARMI S</div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Medical Notes Section -->
                    <div class="notes-section mt-2">
                        <h6 class="section-title mb-2" style="color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px;">
                            <i class="fas fa-notes-medical me-1" style="color: #6c757d;"></i>Medical Notes
                        </h6>
                        <div class="notes-content p-2" style="background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
                            <div id="bloodModalReactions" class="text-muted small">None reported</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0" style="padding: 1rem 2rem 2rem;">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" style="border-radius: 8px;">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .detail-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 0.25rem;
            display: block;
        }
        .detail-value {
            font-size: 1rem;
            color: #212529;
            font-weight: 500;
        }
        .detail-value.highlight {
            color: #28a745;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .detail-value.monospace {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        .detail-section {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .notes-content {
            min-height: 80px;
            font-style: italic;
        }
        
        /* Failed Collection Styles */
        .failed-collection .modal-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
        }
        .failed-collection .status-banner {
            background: linear-gradient(90deg, #f8d7da 0%, #f5c6cb 100%) !important;
            border-left-color: #dc3545 !important;
        }
        .failed-collection .status-banner .fas {
            color: #dc3545 !important;
        }
        .failed-collection .status-banner strong {
            color: #dc3545 !important;
        }
    </style>

    <!-- Physical Exam Modal (Fixed Size Design) -->
    <div class="modal fade" id="physicalExamModal" tabindex="-1" aria-labelledby="physicalExamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); height: 75vh; display: flex; flex-direction: column;">
                <div class="modal-header border-0" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); border-radius: 12px 12px 0 0; padding: 1rem 1.5rem; flex-shrink: 0;">
                    <div class="d-flex align-items-center w-100">
                        <div class="status-icon me-2" style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-stethoscope" style="font-size: 1.2rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="modal-title text-white mb-1">Physical Examination Complete</h5>
                            <p class="text-white-50 mb-0 small">Ready for blood collection</p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="filter: brightness(0) invert(1);"></button>
                    </div>
                </div>
                <div class="modal-body flex-grow-1" style="padding: 1.5rem; overflow: hidden;">
                    <!-- Progress Indicator -->
                    <div class="progress-section mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Donation Progress</span>
                            <span class="text-muted small">75% Complete</span>
                        </div>
                        <div class="progress" style="height: 8px; border-radius: 4px;">
                            <div class="progress-bar" role="progressbar" style="width: 75%; background-color: #b22222;"></div>
                        </div>
                    </div>

                    <!-- Current Status -->
                    <div class="status-banner mb-4 p-4" style="background: linear-gradient(90deg, #f8e6e6 0%, #f0d0d0 100%); border-radius: 12px; border-left: 5px solid #b22222;">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-check-circle me-2" style="font-size: 1.2rem; color: #b22222;"></i>
                                    <strong style="color: #b22222;">Physical Examination Complete</strong>
                                </div>
                                <p class="text-muted mb-2">This donor has successfully completed their physical examination and is now ready for blood collection.</p>
                                <div class="next-step">
                                    <strong class="text-dark">Next Step:</strong> 
                                    <span class="text-muted">Waiting for phlebotomist to process blood collection</span>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="next-icon" style="width: 80px; height: 80px; background: rgba(178, 34, 34, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <i class="fas fa-tint" style="font-size: 2rem; color: #b22222;"></i>
                                </div>
                                <small class="text-muted mt-2 d-block">Ready for Collection</small>
                            </div>
                        </div>
                    </div>

                    <!-- Donor Information -->
                    <div class="info-section">
                        <h6 class="section-title mb-3" style="color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px;">
                            <i class="fas fa-user me-2" style="color: #6c757d;"></i>Donor Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-item mb-3" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef;">
                                    <label class="detail-label">Physical Exam Date</label>
                                    <div class="detail-value" id="physicalModalDate">June 27, 2025</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item mb-3" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef;">
                                    <label class="detail-label">Donor Name</label>
                                    <div class="detail-value" id="physicalModalName">Eldrich Siy</div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
                <div class="modal-footer border-0" style="padding: 1rem 2rem 2rem;">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" style="border-radius: 8px;">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Screening Only Modal (Fixed Size Design) -->
    <div class="modal fade" id="screeningOnlyModal" tabindex="-1" aria-labelledby="screeningOnlyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); height: 70vh; display: flex; flex-direction: column;">
                <div class="modal-header border-0" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); border-radius: 12px 12px 0 0; padding: 1rem 1.5rem; flex-shrink: 0;">
                    <div class="d-flex align-items-center w-100">
                        <div class="status-icon me-2" style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-clipboard-check" style="font-size: 1.2rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="modal-title text-white mb-1">Initial Screening Complete</h5>
                            <p class="text-white-50 mb-0 small">Awaiting physician examination</p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="filter: brightness(0) invert(1);"></button>
                    </div>
                </div>
                <div class="modal-body flex-grow-1" style="padding: 1.5rem; overflow: hidden;">
                    <!-- Progress Indicator -->
                    <div class="progress-section mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Donation Progress</span>
                            <span class="text-muted small">50% Complete</span>
                        </div>
                        <div class="progress" style="height: 8px; border-radius: 4px;">
                            <div class="progress-bar" role="progressbar" style="width: 50%; background-color: #b22222;"></div>
                        </div>
                    </div>

                    <!-- Current Status -->
                    <div class="status-banner mb-4 p-4" style="background: linear-gradient(90deg, #f8e6e6 0%, #f0d0d0 100%); border-radius: 12px; border-left: 5px solid #b22222;">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-check-circle me-2" style="font-size: 1.2rem; color: #b22222;"></i>
                                    <strong style="color: #b22222;">Screening Phase Complete</strong>
                                </div>
                                <p class="text-muted mb-2">This donor has successfully completed their initial screening form and medical history review.</p>
                                <div class="next-step">
                                    <strong class="text-dark">Next Step:</strong> 
                                    <span class="text-muted">Physical examination by a physician</span>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="next-icon" style="width: 80px; height: 80px; background: rgba(178, 34, 34, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <i class="fas fa-user-md" style="font-size: 2rem; color: #b22222;"></i>
                                </div>
                                <small class="text-muted mt-2 d-block">Waiting for Physician</small>
                            </div>
                        </div>
                    </div>

                    <!-- Donor Information -->
                    <div class="info-section">
                        <h6 class="section-title mb-3" style="color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px;">
                            <i class="fas fa-user me-2" style="color: #6c757d;"></i>Donor Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-item mb-3" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef;">
                                    <label class="detail-label">Screening Completed</label>
                                    <div class="detail-value" id="screeningModalDate">August 12, 2025</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item mb-3" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef;">
                                    <label class="detail-label">Donor Name</label>
                                    <div class="detail-value" id="screeningModalName">Jam Rat</div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
                <div class="modal-footer border-0" style="padding: 1rem 2rem 2rem;">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" style="border-radius: 8px;">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById('searchInput');
            const donorTableBody = document.getElementById('donorTableBody');
            
            // Store the original table rows for reset
            const originalRows = Array.from(donorTableBody.getElementsByTagName('tr'));
            
            // Enhanced search functionality
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                
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

                    // Search in all columns
                    shouldShow = cells.some(cell => 
                        cell.textContent.toLowerCase().trim().includes(searchTerm)
                    );

                    row.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) visibleCount++;
                });

                // Handle no results message
                if (visibleCount === 0) {
                    showNoResultsMessage(searchTerm);
                } else {
                    removeNoResultsMessage();
                }
            }

            function showNoResultsMessage(searchTerm) {
                removeNoResultsMessage();
                const messageRow = document.createElement('tr');
                messageRow.className = 'no-results';
                messageRow.innerHTML = `<td colspan="7" class="text-center py-3">
                    No records found matching "${searchTerm}"
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

            // Handle view button click
            document.querySelectorAll('.view-history-btn').forEach(button => {
                button.addEventListener('click', function() {
                    try {
                        const historyDataStr = this.getAttribute('data-history');
                        const stage = this.getAttribute('data-stage');
                        const historyData = JSON.parse(historyDataStr);
                        
                        // Show different modals based on stage (same as donor history)
                        if (stage === 'blood_collection') {
                            populateBloodCollectionModal(historyData);
                            const modal = new bootstrap.Modal(document.getElementById('bloodCollectionModal'));
                            modal.show();
                        } else if (stage === 'physical_examination') {
                            populatePhysicalExamModal(historyData);
                            const modal = new bootstrap.Modal(document.getElementById('physicalExamModal'));
                            modal.show();
                        } else if (stage === 'screening_form') {
                            populateScreeningOnlyModal(historyData);
                            const modal = new bootstrap.Modal(document.getElementById('screeningOnlyModal'));
                            modal.show();
                        }
                        
                    } catch (error) {
                        console.error('Error parsing history data:', error);
                        alert('Error loading donor information. Please try again.');
                    }
                });
            });
            
            // Function to populate blood collection modal with success/failure details
            function populateBloodCollectionModal(data) {
                const modalContent = document.querySelector('#bloodCollectionModal .modal-content');
                const header = document.getElementById('bloodCollectionHeader');
                const isSuccessful = data.volume !== 'Collection Failed';
                
                // Set dynamic styling based on success/failure
                if (isSuccessful) {
                    modalContent.classList.remove('failed-collection');
                    header.style.background = 'linear-gradient(135deg, #b22222 0%, #8b0000 100%)';
                    document.getElementById('bloodCollectionModalLabel').textContent = 'Blood Collection Complete';
                    document.getElementById('bloodCollectionSubtitle').textContent = 'Collection successfully processed';
                } else {
                    modalContent.classList.add('failed-collection');
                    header.style.background = 'linear-gradient(135deg, #b22222 0%, #8b0000 100%)';
                    document.getElementById('bloodCollectionModalLabel').textContent = 'Blood Collection Failed';
                    document.getElementById('bloodCollectionSubtitle').textContent = 'Collection was unsuccessful';
                }
                
                // Update progress bar
                const progressBar = document.getElementById('bloodProgressBar');
                const progressText = document.getElementById('bloodProgressText');
                if (isSuccessful) {
                    progressBar.className = 'progress-bar';
                    progressBar.style.backgroundColor = '#b22222';
                    progressBar.style.width = '100%';
                    progressText.textContent = '100% Complete';
                } else {
                    progressBar.className = 'progress-bar';
                    progressBar.style.backgroundColor = '#b22222';
                    progressBar.style.width = '75%';
                    progressText.textContent = '75% Complete (Failed)';
                }

                // Update status banner
                const statusBanner = document.getElementById('statusBanner');
                const statusIcon = document.getElementById('statusIcon');
                const statusText = document.getElementById('statusText');
                const statusDescription = document.getElementById('statusDescription');
                const collectionResult = document.getElementById('collectionResult');
                const resultIcon = document.getElementById('resultIcon');
                const resultText = document.getElementById('resultText');
                
                if (isSuccessful) {
                    statusIcon.className = 'fas fa-check-circle me-2';
                    statusIcon.style.color = '#b22222';
                    statusText.textContent = 'Collection Successful';
                    statusText.style.color = '#b22222';
                    statusDescription.textContent = 'Blood collection completed without complications';
                    collectionResult.textContent = 'Successfully collected blood for donation';
                    collectionResult.style.color = '#b22222';
                    resultIcon.style.background = 'rgba(178, 34, 34, 0.1)';
                    resultIcon.querySelector('i').style.color = '#b22222';
                    resultText.textContent = 'Collection Complete';
                } else {
                    statusIcon.className = 'fas fa-exclamation-triangle me-2';
                    statusIcon.style.color = '#b22222';
                    statusText.textContent = 'Collection Failed';
                    statusText.style.color = '#b22222';
                    statusDescription.textContent = 'Blood collection was unsuccessful';
                    collectionResult.textContent = 'Collection process encountered complications';
                    collectionResult.style.color = '#b22222';
                    resultIcon.style.background = 'rgba(178, 34, 34, 0.1)';
                    resultIcon.querySelector('i').style.color = '#b22222';
                    resultText.textContent = 'Collection Failed';
                }
                
                // Format date and time
                let dateTimeText = 'Not recorded';
                if (data.donation_date) {
                    const date = new Date(data.donation_date);
                    dateTimeText = date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long', 
                        day: 'numeric'
                    });
                    
                    if (data.collection_start_time && data.collection_start_time !== 'N/A') {
                        dateTimeText += ' at ' + formatTime(data.collection_start_time);
                        if (data.collection_end_time && data.collection_end_time !== 'N/A') {
                            dateTimeText += ' - ' + formatTime(data.collection_end_time);
                        }
                    }
                }
                document.getElementById('bloodModalDateTime').textContent = dateTimeText;
                
                // Set amount with proper highlighting
                const amountElement = document.getElementById('bloodModalAmount');
                if (data.amount_collected && data.amount_collected !== 'N/A' && isSuccessful) {
                    amountElement.textContent = data.amount_collected + ' unit' + (data.amount_collected > 1 ? 's' : '');
                    amountElement.className = 'detail-value highlight';
                    amountElement.style.color = '#b22222';
                    amountElement.style.fontWeight = '600';
                } else {
                    amountElement.textContent = 'No units collected';
                    amountElement.className = 'detail-value';
                }
                
                // Set technical details
                document.getElementById('bloodModalSerial').textContent = data.unit_serial_number || 'Not assigned';
                document.getElementById('bloodModalBag').textContent = data.blood_bag_used || 'Not specified';
                
                // Set reactions/failure explanation
                let reactionText = 'None reported';
                if (data.donor_reaction && data.donor_reaction !== 'None' && data.donor_reaction !== 'N/A') {
                    reactionText = data.donor_reaction;
                } else if (!isSuccessful) {
                    reactionText = 'Collection failed. Reason not specified in records. This could be due to various factors such as difficulty accessing veins, donor discomfort, or medical complications during the procedure.';
                }
                document.getElementById('bloodModalReactions').textContent = reactionText;
            }
            
            // Function to populate physical exam modal
            function populatePhysicalExamModal(data) {
                if (data.donation_date) {
                    const date = new Date(data.donation_date);
                    document.getElementById('physicalModalDate').value = date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long', 
                        day: 'numeric'
                    });
                }
                
                const fullName = (data.first_name || '') + ' ' + (data.surname || '');
                document.getElementById('physicalModalName').value = fullName.trim() || 'N/A';
            }
            
            // Function to populate screening only modal
            function populateScreeningOnlyModal(data) {
                if (data.donation_date) {
                    const date = new Date(data.donation_date);
                    document.getElementById('screeningModalDate').value = date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long', 
                        day: 'numeric'
                    });
                }
                
                const fullName = (data.first_name || '') + ' ' + (data.surname || '');
                document.getElementById('screeningModalName').value = fullName.trim() || 'N/A';
            }
            
            // Helper function to format time
            function formatTime(timeStr) {
                if (!timeStr || timeStr === 'N/A') {
                    return 'N/A';
                }
                try {
                    const date = new Date(timeStr);
                    return date.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    });
                } catch (error) {
                    return timeStr;
                }
            }
        });
    </script>
</body>
</html>
