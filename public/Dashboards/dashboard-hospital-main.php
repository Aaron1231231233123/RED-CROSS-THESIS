<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../../assets/php_func/check_account_hospital_modal.php';

// Function to query Supabase
function querySQL($table, $select = "*", $filters = null) {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Prefer: return=representation'
    ];
    
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?select=' . urlencode($select);
    
    if ($filters) {
        foreach ($filters as $key => $value) {
            $url .= '&' . $key . '=' . urlencode($value);
        }
    }
    
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

// Get blood inventory data
$bloodInventory = [];
$declinedDonorIds = [];

// Query physical examination for non-accepted remarks
$physicalExamQuery = curl_init();
curl_setopt_array($physicalExamQuery, [
    CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?remarks=neq.Accepted&select=donor_id,donor_form_id,remarks",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Content-Type: application/json"
    ],
]);

$physicalExamResponse = curl_exec($physicalExamQuery);
curl_close($physicalExamQuery);

if ($physicalExamResponse) {
    $physicalExamRecords = json_decode($physicalExamResponse, true);
    if (is_array($physicalExamRecords)) {
        foreach ($physicalExamRecords as $record) {
            if (!empty($record['donor_id'])) {
                $declinedDonorIds[] = $record['donor_id'];
            }
            if (!empty($record['donor_form_id'])) {
                $declinedDonorIds[] = $record['donor_form_id'];
            }
        }
    }
}

$declinedDonorIds = array_unique($declinedDonorIds);

// Query eligibility table for valid blood units
$eligibilityData = querySQL(
    'eligibility', 
    'eligibility_id,donor_id,blood_type,donation_type,blood_bag_type,collection_successful,unit_serial_number,collection_start_time,start_date,end_date,status,blood_collection_id',
    ['collection_successful' => 'eq.true']
);

// Initialize blood type counts
$bloodByType = [
    'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
    'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
];

$today = new DateTime();

if (is_array($eligibilityData) && !empty($eligibilityData)) {
    foreach ($eligibilityData as $item) {
        // Skip if donor is in declined list
        if (in_array($item['donor_id'], $declinedDonorIds)) {
            continue;
        }
        
        // Get blood collection data
        if (!empty($item['blood_collection_id'])) {
            $bloodCollectionData = querySQL('blood_collection', '*', ['blood_collection_id' => 'eq.' . $item['blood_collection_id']]);
            $bloodCollectionData = isset($bloodCollectionData[0]) ? $bloodCollectionData[0] : null;
        } else {
            continue;
        }
        
        // Calculate expiration date
        $collectionDate = new DateTime($item['collection_start_time']);
        $expirationDate = clone $collectionDate;
        $expirationDate->modify('+35 days');
        
        // Only count if not expired and has amount taken
        if ($today <= $expirationDate && 
            $bloodCollectionData && 
            isset($bloodCollectionData['amount_taken']) && 
            is_numeric($bloodCollectionData['amount_taken']) && 
            $bloodCollectionData['amount_taken'] > 0) {
            
            $bloodType = $item['blood_type'];
            if (isset($bloodByType[$bloodType])) {
                $bloodByType[$bloodType] += floatval($bloodCollectionData['amount_taken']);
            }
        }
    }
}

// Convert to integers
foreach ($bloodByType as $type => $count) {
    $bloodByType[$type] = (int)$count;
}

// Get critical blood types (less than 30 units)
$criticalTypes = [];
foreach ($bloodByType as $type => $count) {
    if ($count < 30) {
        $criticalTypes[] = $type;
    }
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
        .text-danger {
            color: var(--redcross-red) !important;
            font-weight: bold;
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
.card-title{
    color: var(--redcross-red) !important;
            font-weight: bold;
}
        /* Animation keyframes for sliding */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(30px);
            }
        }

        .notifications-toggle {
            position: fixed;
            top: 85px;
            right: 32px;
            transform: none;
            background: #fff;
            color: #941022;
            border: none;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            box-shadow: 0 4px 16px rgba(148,16,34,0.12), 0 1.5px 4px rgba(0,0,0,0.04);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            animation: slideInRight 0.2s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .notifications-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(148,16,34,0.2);
        }

        .notifications-toggle.hide-anim {
            animation: slideOutRight 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .sticky-alerts {
            position: fixed;
            top: 150px;
            right: 32px;
            z-index: 1000;
            width: 370px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sticky-alerts.hidden {
            animation: slideOutRight 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .sticky-alerts:not(.hidden) {
            animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .blood-alert {
            position: relative;
            margin: 0;
            box-shadow: 0 4px 16px rgba(148,16,34,0.08), 0 1.5px 4px rgba(0,0,0,0.04);
            border-radius: 14px;
            border-left: 6px solid #941022;
            background: #fff6f7;
            padding: 20px 24px 20px 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            color: #941022;
            cursor: pointer;
            min-width: 320px;
            max-width: 370px;
            transition: all 0.3s ease;
            animation: slideInRight 0.3s ease;
        }

        .blood-alert.fade-out {
            animation: slideOutRight 0.3s ease forwards;
        }

        .blood-alert[data-notif-id="pending"] {
            background: #fffbe6;
            color: #b38b00;
            border-left: 6px solid #b38b00;
        }
        .blood-alert[data-notif-id="event"] {
            background: #f0f8ff;
            color: #0d6efd;
            border-left: 6px solid #0d6efd;
        }
        .blood-alert .notif-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4em;
            background: #fff;
            box-shadow: 0 1px 4px rgba(148,16,34,0.08);
            margin-right: 6px;
        }
        .blood-alert[data-notif-id="pending"] .notif-icon {
            background: #fffbe6;
            color: #b38b00;
        }
        .blood-alert[data-notif-id="event"] .notif-icon {
            background: #f0f8ff;
            color: #0d6efd;
        }
        .blood-alert .notif-content {
            flex: 1;
        }
        .blood-alert .notif-title {
            font-weight: 700;
            font-size: 1.08em;
            margin-bottom: 2px;
        }
        .blood-alert .notif-close {
            position: absolute;
            top: 10px;
            right: 14px;
            font-size: 1.1em;
            color: #aaa;
            background: none;
            border: none;
            cursor: pointer;
            transition: color 0.2s;
        }
        .blood-alert .notif-close:hover {
            color: #941022;
        }
        .blood-alert.fade-out {
                opacity: 0;
            transform: translateX(60px);
        }
        @keyframes fadeInSlide {
            from { opacity: 0; transform: translateX(60px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .notifications-toggle .badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #dc3545;
            color: #fff;
            border: 2px solid #fff;
            font-size: 0.95em;
            font-weight: 700;
            padding: 3px 7px;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(220,53,69,0.12);
            animation: pulseBadge 1.2s infinite;
        }
        @keyframes pulseBadge {
            0% { box-shadow: 0 0 0 0 #dc354580; }
            70% { box-shadow: 0 0 0 8px #dc354500; }
            100% { box-shadow: 0 0 0 0 #dc354500; }
        }
        .notification-container {
            position: fixed;
            top: 32px;
            right: 32px;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 370px;
            max-width: 100vw;
        }
        .sticky-alerts {
            margin-top: 0;
            transition: margin-top 0.4s cubic-bezier(.4,1.4,.6,1), opacity 0.5s, transform 0.5s;
        }
        .sticky-alerts.bell-visible {
            margin-top: 0; /* Remove margin adjustment since we're using fixed positioning */
        }
        .sticky-alerts.bell-hidden {
            top: 85px; /* Move up when bell is hidden */
        }
        .notifications-toggle.show {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
            margin-bottom: 16px;
        }
        .notifications-toggle.hide-anim {
            opacity: 0;
            pointer-events: none;
            transform: translateY(-30px) scale(0.95);
        }
        .blood-alert {
            transition: opacity 0.5s, transform 0.5s, margin 0.4s cubic-bezier(.4,1.4,.6,1);
        }
        .blood-alert.fade-out {
            opacity: 0;
            transform: translateX(60px) scale(0.95);
            margin-bottom: -60px;
        }

        .notifications-toggle i {
            font-size: 1.6em;
        }

        .notifications-toggle .fa-bell {
            font-size: 1.6em;
        }

        /* Add this to your existing styles */
        <style>
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .blood-inventory-title {
            color: #941022;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 1.2em;
        }
        </style>
    </style>
    <!-- Add this before the closing </head> tag -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
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
                                <i class="fas fa-tint me-2"></i>Your Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard-hospital-history.php' ? ' active' : ''; ?>" href="dashboard-hospital-history.php">
                                <i class="fas fa-print me-2"></i>Print Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <?php
                            $historyPages = ['dashboard-hospital-request-history.php'];
                            $isHistory = in_array(basename($_SERVER['PHP_SELF']), $historyPages);
                            $status = $_GET['status'] ?? '';
                            ?>
                            <a class="nav-link d-flex justify-content-between align-items-center<?php echo $isHistory ? ' active' : ''; ?>" data-bs-toggle="collapse" href="#historyCollapse" role="button" aria-expanded="<?php echo $isHistory ? 'true' : 'false'; ?>" aria-controls="historyCollapse" id="historyCollapseBtn">
                                <span><i class="fas fa-history me-2"></i>History</span>
                                <i class="fas fa-chevron-down transition-arrow<?php echo $isHistory ? ' rotate' : ''; ?>" id="historyChevron"></i>
                            </a>
                            <div class="collapse<?php echo $isHistory ? ' show' : ''; ?>" id="historyCollapse">
                                <div class="collapse-menu">
                                    <a href="dashboard-hospital-request-history.php?status=accepted" class="nav-link<?php echo $isHistory && $status === 'accepted' ? ' active' : ''; ?>">Accepted</a>
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
                        <!-- Enhanced Total Blood Inventory Overview -->
                        <div class="card mb-4 total-overview-card">
                            <div class="card-body">
                                <?php
                                $totalUnits = array_sum($bloodByType);
                                $maxCapacity = 800;
                                $totalPercentage = min(($totalUnits / $maxCapacity) * 100, 100);
                                $statusClass = $totalPercentage < 30 ? 'critical' : 
                                             ($totalPercentage < 50 ? 'warning' : 'healthy');
                                $statusText = $totalPercentage < 30 ? 'CRITICAL LOW' : 
                                            ($totalPercentage < 50 ? 'WARNING' : 'HEALTHY');
                                ?>
                                
                                <div class="inventory-header d-flex justify-content-between align-items-start mb-4">
                                    <div class="title-section">
                                        <h5 class="inventory-title mb-2">Blood Bank Inventory Status</h5>
                                        <div class="inventory-percentage d-flex align-items-center">
                                            <div class="percentage-display">
                                                <span class="h2 mb-0 fw-bold <?php echo $statusClass; ?>-text"><?php echo round($totalPercentage); ?>%</span>
                                                <span class="text-muted ms-2">Capacity</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="status-indicator">
                                        <div class="status-badge status-<?php echo $statusClass; ?>">
                                            <i class="fas <?php echo $totalPercentage < 30 ? 'fa-exclamation-triangle' : 'fa-check-circle'; ?>"></i>
                                            <span><?php echo $statusText; ?></span>
                                        </div>
                                    </div>
                            </div>
                            
                                <div class="inventory-progress-wrapper">
                                    <div class="inventory-progress position-relative mb-4">
                                        <div class="progress-track">
                                            <div class="progress-fill bg-<?php echo $statusClass; ?>" 
                                                 style="width: <?php echo $totalPercentage; ?>%">
                                </div>
                            </div>

                                        <!-- Threshold Indicators -->
                                        <div class="threshold-indicators">
                                            <div class="threshold critical" style="left: 30%">
                                                <div class="threshold-line"></div>
                                                <span class="threshold-label">30%</span>
                                </div>
                                            <div class="threshold warning" style="left: 50%">
                                                <div class="threshold-line"></div>
                                                <span class="threshold-label">50%</span>
                                            </div>
                                        </div>
                            </div>

                                    <!-- Quick Stats Grid -->
                                    <div class="inventory-stats-grid">
                                        <div class="stat-box <?php echo count($criticalTypes) > 0 ? 'critical' : 'normal'; ?>">
                                            <div class="stat-icon-wrapper">
                                                <i class="fas <?php echo count($criticalTypes) > 0 ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                                </div>
                                            <div class="stat-info">
                                                <?php if (count($criticalTypes) > 0): ?>
                                                    <div class="stat-number"><?php echo count($criticalTypes); ?></div>
                                                    <div class="stat-label">Critical Types</div>
                                                <?php else: ?>
                                                    <div class="stat-number">Normal</div>
                                                    <div class="stat-label">All Blood Types</div>
                                                <?php endif; ?>
                                            </div>
                            </div>

                                        <div class="stat-box">
                                            <div class="stat-icon-wrapper">
                                                <i class="fas fa-clock"></i>
                                </div>
                                            <div class="stat-info">
                                                <div class="stat-number"><?php echo date('h:i A'); ?></div>
                                                <div class="stat-label">Current Time</div>
                                            </div>
                            </div>

                                        <div class="stat-box">
                                            <div class="stat-icon-wrapper">
                                                <i class="fas fa-chart-line"></i>
                                </div>
                                            <div class="stat-info">
                                                <div class="stat-number"><?php echo round($totalPercentage); ?>%</div>
                                                <div class="stat-label">Current Level</div>
                            </div>
                        </div>
                    </div>

                                    <?php if (count($criticalTypes) > 0): ?>
                                    <div class="critical-alert-banner">
                                        <div class="alert-icon">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </div>
                                        <div class="alert-content">
                                            <h6 class="alert-title mb-1">Critical Alert</h6>
                                            <p class="alert-message mb-0">
                                                <?php echo implode(', ', $criticalTypes); ?> blood types require immediate attention!
                                            </p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <style>
                        /* Enhanced Blood Inventory Status Styles */
                        .total-overview-card {
                            background: #fff;
                            border: none;
                            border-radius: 15px;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                        }

                        .inventory-header {
                            padding-bottom: 1rem;
                            border-bottom: 1px solid rgba(0,0,0,0.08);
                        }

                        .inventory-title {
                            color: #2c3e50;
                            font-size: 1.5rem;
                            font-weight: 600;
                            margin: 0;
                        }

                        .percentage-display {
                            margin-top: 1rem;
                        }

                        .percentage-display .h2 {
                            font-size: 2.5rem;
                            font-weight: 700;
                        }

                        .critical-text { color: #dc2626; }
                        .warning-text { color: #d97706; }
                        .healthy-text { color: #16a34a; }

                        .status-badge {
                            padding: 10px 20px;
                            border-radius: 30px;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            font-weight: 600;
                            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        }

                        .status-badge i {
                            font-size: 1.2rem;
                        }

                        .status-critical {
                            background: #fee2e2;
                            color: #dc2626;
                            animation: pulse 2s infinite;
                        }

                        .status-warning {
                            background: #fef3c7;
                            color: #d97706;
                        }

                        .status-healthy {
                            background: #dcfce7;
                            color: #16a34a;
                        }

                        .inventory-progress-wrapper {
                            margin: 2rem 0;
                        }

                        .progress-track {
                            height: 35px;
                            background: #f1f5f9;
                            border-radius: 17.5px;
                            position: relative;
                            overflow: hidden;
                            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
                        }

                        .progress-fill {
                            height: 100%;
                            border-radius: 17.5px;
                            position: relative;
                            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
                        }

                        .threshold-indicators {
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            pointer-events: none;
                        }

                        .threshold {
                            position: absolute;
                            height: 100%;
                        }

                        .threshold-line {
                            position: absolute;
                            top: -10px;
                            left: 50%;
                            height: calc(100% + 20px);
                            width: 2px;
                            background: rgba(0,0,0,0.2);
                        }

                        .threshold-label {
                            position: absolute;
                            top: -30px;
                            left: 50%;
                            transform: translateX(-50%);
                            font-size: 1rem;
                            font-weight: 600;
                            color: #64748b;
                            background: white;
                            padding: 4px 12px;
                            border-radius: 20px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        }

                        .inventory-stats-grid {
                            display: grid;
                            grid-template-columns: repeat(3, 1fr);
                            gap: 1rem;
                            margin: 2rem 0;
                        }

                        .stat-box {
                            display: flex;
                            align-items: center;
                            gap: 1rem;
                            padding: 1.5rem;
                            background: #ffffff;
                            border-radius: 12px;
                            transition: all 0.2s ease;
                            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
                        }

                        .stat-box.critical {
                            background: #fff2f2;
                            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.08);
                        }

                        .stat-box.normal {
                            background: #f0fdf4;
                            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.08);
                        }

                        .stat-box:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
                        }

                        .stat-box.critical:hover {
                            box-shadow: 0 8px 16px rgba(220, 38, 38, 0.12);
                        }

                        .stat-box.normal:hover {
                            box-shadow: 0 8px 16px rgba(22, 163, 74, 0.12);
                        }

                        .stat-icon-wrapper {
                            width: 40px;
                            height: 40px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            border-radius: 50%;
                            background: white;
                            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                        }

                        .stat-box.critical .stat-icon-wrapper {
                            color: #dc2626;
                            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.15);
                        }

                        .stat-box.normal .stat-icon-wrapper {
                            color: #16a34a;
                            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15);
                        }

                        .stat-box .fas {
                            font-size: 1.2rem;
                            color: #941022;
                        }

                        .stat-box.critical .fas {
                            color: #dc2626;
                        }

                        .stat-box.normal .fas {
                            color: #16a34a;
                        }

                        .stat-info {
                            flex: 1;
                        }

                        .stat-number {
                            font-size: 1.75rem;
                            font-weight: 700;
                            color: #1e293b;
                            line-height: 1;
                            margin-bottom: 0.25rem;
                        }

                        .stat-box.critical .stat-number {
                            color: #dc2626;
                        }

                        .stat-box.normal .stat-number {
                            color: #16a34a;
                        }

                        .stat-label {
                            font-size: 0.875rem;
                            color: #64748b;
                            font-weight: 500;
                        }

                        .critical-alert-banner {
                            margin-top: 2rem;
                            padding: 1.25rem;
                            background: #fee2e2;
                            border-left: 4px solid #dc2626;
                            border-radius: 8px;
                            display: flex;
                            align-items: center;
                            gap: 1rem;
                        }

                        .alert-icon {
                            font-size: 1.5rem;
                            color: #dc2626;
                        }

                        .alert-title {
                            color: #dc2626;
                            font-weight: 600;
                        }

                        .alert-message {
                            color: #991b1b;
                        }

                        @keyframes pulse {
                            0% { transform: scale(1); }
                            50% { transform: scale(1.02); }
                            100% { transform: scale(1); }
                        }

                        /* Responsive adjustments */
                        @media (max-width: 992px) {
                            .inventory-stats-grid {
                                grid-template-columns: repeat(3, 1fr);
                                gap: 1rem;
                            }
                        }

                        @media (max-width: 768px) {
                            .inventory-stats-grid {
                                grid-template-columns: repeat(2, 1fr);
                            }
                        }

                        @media (max-width: 576px) {
                            .inventory-stats-grid {
                                grid-template-columns: 1fr;
                            }
                            
                            .stat-box {
                                padding: 1.25rem;
                            }
                        }
                        </style>

                        <?php
                        // Function to fetch pending requests
                        function fetchPendingRequests($user_id) {
                            $ch = curl_init();
                            
                            $headers = [
                                'apikey: ' . SUPABASE_API_KEY,
                                'Authorization: Bearer ' . SUPABASE_API_KEY
                            ];
                            
                            $url = SUPABASE_URL . '/rest/v1/blood_requests?user_id=eq.' . $user_id . '&status=eq.Pending&select=request_id,patient_blood_type,rh_factor,patient_name';
                            
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            
                            $response = curl_exec($ch);
                            curl_close($ch);
                            
                            return json_decode($response, true);
                        }

                        // Get pending requests
                        $pending_requests = fetchPendingRequests($_SESSION['user_id']);

                        // Function to mask sensitive information
                        function maskPatientInfo($name) {
                            if (!$name) return '****';
                            $parts = explode(' ', $name);
                            $maskedParts = array_map(function($part) {
                                if (strlen($part) <= 2) return $part;
                                return substr($part, 0, 1) . str_repeat('*', strlen($part) - 2) . substr($part, -1);
                            }, $parts);
                            return implode(' ', $maskedParts);
                        }
                        ?>

                        <!-- Notification container: bell above notifications, left-aligned -->
                        <div class="notification-container" style="position:fixed;top:32px;right:32px;z-index:1001;display:flex;flex-direction:column;align-items:flex-start;width:370px;max-width:100vw;">
                            <button class="notifications-toggle" id="notificationsToggle">
                                <i class="fas fa-bell"></i>
                                <span class="badge rounded-pill" id="notifBadge" style="display:none;">0</span>
                            </button>
                            <div class="sticky-alerts bell-hidden" id="stickyAlerts">
                            <!-- Blood shortage alert -->
                                <?php if (!empty($criticalTypes)): ?>
                                <div class="blood-alert alert" role="alert" data-notif-id="critical">
                                    <span class="notif-icon" style="background:#fff6f7;color:#941022;"><i class="fas fa-exclamation-triangle"></i></span>
                                    <div class="notif-content">
                                        <div class="notif-title">Critical Blood Shortage</div>
                                        <div><?php echo implode(', ', $criticalTypes); ?> levels are critically low!</div>
                            </div>
                                    <button class="notif-close" title="Dismiss" aria-label="Dismiss">&times;</button>
                                </div>
                                <?php endif; ?>
                            <!-- Consolidated pending requests alert -->
                            <?php if (!empty($pending_requests)): ?>
                                    <div class="blood-alert alert" role="alert" data-notif-id="pending">
                                        <span class="notif-icon"><i class="fas fa-clock"></i></span>
                                        <div class="notif-content">
                                            <div class="notif-title">Pending Requests</div>
                                            <div>You have <?php echo count($pending_requests); ?> pending blood request(s)</div>
                                        </div>
                                        <button class="notif-close" title="Dismiss" aria-label="Dismiss">&times;</button>
                                </div>
                            <?php endif; ?>
                            <!-- Donation drive alert -->
                                <div class="blood-alert alert" role="alert" data-notif-id="event">
                                    <span class="notif-icon"><i class="fas fa-calendar"></i></span>
                                    <div class="notif-content">
                                        <div class="notif-title">Upcoming Event</div>
                                        <div>New donation drive scheduled for March 20</div>
                                    </div>
                                    <button class="notif-close" title="Dismiss" aria-label="Dismiss">&times;</button>
                                </div>
                            </div>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const stickyAlerts = document.getElementById('stickyAlerts');
                            const notificationsToggle = document.getElementById('notificationsToggle');
                            const notifBadge = document.getElementById('notifBadge');
                            let dismissedNotifs = [];
                            let autoHideTimeout;

                            function getAlerts() {
                                return Array.from(document.querySelectorAll('.blood-alert'));
                            }

                            // Show notifications, hide bell
                            function showNotifications() {
                                stickyAlerts.classList.remove('hidden');
                                notificationsToggle.classList.remove('show');
                                notificationsToggle.classList.add('hide-anim');
                                setTimeout(() => {
                                    notificationsToggle.style.display = 'none';
                                    notificationsToggle.classList.remove('hide-anim');
                                }, 400);
                                notifBadge.style.display = 'none';
                                dismissedNotifs = [];
                                getAlerts().forEach(alert => {
                                    alert.style.display = '';
                                    alert.classList.remove('fade-out');
                                });
                                stickyAlerts.classList.remove('bell-visible');
                                stickyAlerts.classList.add('bell-hidden');
                                clearTimeout(autoHideTimeout);
                                autoHideTimeout = setTimeout(autoHideNotification, 5000);
                            }

                            // Hide notifications, show bell if any dismissed
                            function hideNotifications() {
                                stickyAlerts.classList.add('hidden');
                                if (dismissedNotifs.length > 0) {
                                    notificationsToggle.style.display = 'flex';
                                    setTimeout(() => {
                                        notificationsToggle.classList.add('show');
                                    }, 10);
                                    notifBadge.textContent = dismissedNotifs.length;
                                    notifBadge.style.display = 'inline-block';
                                    stickyAlerts.classList.remove('bell-hidden');
                                    stickyAlerts.classList.add('bell-visible');
                                } else {
                                    notificationsToggle.classList.remove('show');
                                    notifBadge.style.display = 'none';
                                    stickyAlerts.classList.remove('bell-visible');
                                    stickyAlerts.classList.add('bell-hidden');
                                }
                            }

                            // Auto-hide a notification after timeout and count it in the bell
                            function autoHideNotification() {
                                // Find the first visible alert
                                const alert = getAlerts().find(a => a.style.display !== 'none');
                                if (alert) {
                                    const notifId = alert.getAttribute('data-notif-id');
                                    alert.classList.add('fade-out');
                                    setTimeout(() => {
                                        alert.style.display = 'none';
                                        if (!dismissedNotifs.includes(notifId)) {
                                            dismissedNotifs.push(notifId);
                                        }
                                        if (getAlerts().every(a => a.style.display === 'none')) {
                                            hideNotifications();
                                        } else {
                                            notificationsToggle.style.display = 'flex';
                                            setTimeout(() => {
                                                notificationsToggle.classList.add('show');
                                            }, 10);
                                            notifBadge.textContent = dismissedNotifs.length;
                                            notifBadge.style.display = 'inline-block';
                                            stickyAlerts.classList.remove('bell-hidden');
                                            stickyAlerts.classList.add('bell-visible');
                                        }
                                        // If there are still visible alerts, set another timeout
                                        if (getAlerts().some(a => a.style.display !== 'none')) {
                                            autoHideTimeout = setTimeout(autoHideNotification, 5000);
                                        }
                                    }, 500);
                                }
                            }

                            notificationsToggle.addEventListener('click', function() {
                                if (dismissedNotifs.length > 0) {
                                    dismissedNotifs.forEach(id => {
                                        const alert = document.querySelector('.blood-alert[data-notif-id="' + id + '"]');
                                        if (alert) {
                                            alert.style.display = '';
                                            alert.classList.remove('fade-out');
                                        }
                                    });
                                    dismissedNotifs = [];
                                }
                                showNotifications();
                            });

                            // Dismiss individual alerts (track which are dismissed)
                            getAlerts().forEach(alert => {
                                // Dismiss on close button
                                alert.querySelector('.notif-close').addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    const notifId = alert.getAttribute('data-notif-id');
                                    alert.classList.add('fade-out');
                                    setTimeout(() => {
                                        alert.style.display = 'none';
                                        if (!dismissedNotifs.includes(notifId)) {
                                            dismissedNotifs.push(notifId);
                                        }
                                        if (getAlerts().every(a => a.style.display === 'none')) {
                                            hideNotifications();
                                        } else {
                                            notificationsToggle.style.display = 'flex';
                                            setTimeout(() => {
                                                notificationsToggle.classList.add('show');
                                            }, 10);
                                            notifBadge.textContent = dismissedNotifs.length;
                                            notifBadge.style.display = 'inline-block';
                                            stickyAlerts.classList.remove('bell-hidden');
                                            stickyAlerts.classList.add('bell-visible');
                                        }
                                    }, 500);
                                });
                                // Dismiss on card click (except close button)
                                alert.addEventListener('click', function(e) {
                                    if (e.target.classList.contains('notif-close')) return;
                                    const notifId = alert.getAttribute('data-notif-id');
                                    alert.classList.add('fade-out');
                                    setTimeout(() => {
                                        alert.style.display = 'none';
                                        if (!dismissedNotifs.includes(notifId)) {
                                            dismissedNotifs.push(notifId);
                                        }
                                        if (getAlerts().every(a => a.style.display === 'none')) {
                                            hideNotifications();
                                        } else {
                                            notificationsToggle.style.display = 'flex';
                                            setTimeout(() => {
                                                notificationsToggle.classList.add('show');
                                            }, 10);
                                            notifBadge.textContent = dismissedNotifs.length;
                                            notifBadge.style.display = 'inline-block';
                                            stickyAlerts.classList.remove('bell-hidden');
                                            stickyAlerts.classList.add('bell-visible');
                                        }
                                    }, 500);
                                });
                                alert.style.cursor = 'pointer';
                            });

                            // Start the auto-hide timer for the first notification
                            autoHideTimeout = setTimeout(autoHideNotification, 5000);
                        });
                        </script>

                        <h2 class="card-title mb-3 mt-3">Bloodbanks</h2>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Blood Inventory Levels</h5>
                                        <div class="chart-container">
                                            <canvas id="bloodChart"></canvas>
                        
                                            
                            
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Most Requested Blood Types</h5>
                                        <div class="chart-container">
                                            <canvas id="requestChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <style>
                        /* Updated styles */
                        .card {
                            border-radius: 8px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                            margin-bottom: 20px;
                        }

                        .chart-container {
                            position: relative;
                            height: 300px;
                            width: 100%;
                        }

                        .blood-legend {
                            padding: 10px;
                            font-size: 0.9rem;
                            background: #fff;
                            border-radius: 6px;
                        }

                        .legend-item {
                            display: flex;
                            align-items: center;
                            gap: 8px;
                        }

                        .legend-dot {
                            width: 12px;
                            height: 12px;
                            border-radius: 50%;
                        }

                        .total-blood-container {
                            padding: 15px;
                            border-top: 1px solid #eee;
                            background: #fff;
                        }

                        .progress {
                            border-radius: 8px;
                            background-color: #f8f9fa;
                            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
                        }

                        .progress-bar {
                            font-weight: 600;
                            font-size: 0.95rem;
                            text-shadow: 1px 1px 1px rgba(0,0,0,0.1);
                            transition: width 0.6s ease;
                        }

                        .bg-danger { background-color: rgba(220, 53, 69, 0.85); }
                        .bg-warning { background-color: rgba(255, 193, 7, 0.85); }
                        .bg-success { background-color: rgba(40, 167, 69, 0.85); }

                        .card-title {
                            color: #941022;
                            font-weight: bold;
                            margin-bottom: 1.5rem;
                        }
                        </style>

                        <?php
                        // Function to fetch blood requests from Supabase
                        function fetchBloodRequests() {
                            $ch = curl_init();
                            
                            $headers = [
                                'apikey: ' . SUPABASE_API_KEY,
                                'Authorization: Bearer ' . SUPABASE_API_KEY
                            ];
                            
                            $url = SUPABASE_URL . '/rest/v1/blood_requests?select=patient_blood_type,rh_factor,units_requested,status';
                            
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            
                            $response = curl_exec($ch);
                            curl_close($ch);
                            
                            return json_decode($response, true);
                        }

                        // Get blood requests
                        $blood_requests = fetchBloodRequests();

                        // Initialize arrays for blood type counts
                        $available_units = [
                            'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
                            'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
                        ];
                        $requested_units = [
                            'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
                            'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
                        ];

                        // Process blood requests
                        foreach ($blood_requests as $request) {
                            $blood_type = $request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-');
                            
                            if ($request['status'] === 'Approved') {
                                $available_units[$blood_type] += $request['units_requested'];
                            }
                            $requested_units[$blood_type] += $request['units_requested'];
                        }
                        ?>

                        <script>
                            document.addEventListener("DOMContentLoaded", function () {
                                // Blood type specific colors
                                const bloodTypeColors = {
                                    'A+': '#FF4136',  // Bright Red
                                    'A-': '#FF851B',  // Orange
                                    'B+': '#39CCCC',  // Teal
                                    'B-': '#7FDBFF',  // Light Blue
                                    'O+': '#2ECC40',  // Green
                                    'O-': '#01FF70',  // Lime
                                    'AB+': '#B10DC9', // Purple
                                    'AB-': '#F012BE'  // Pink
                                };

                                // Get blood inventory data from PHP
                                const bloodData = <?php echo json_encode(array_values($bloodByType)); ?>;
                                const bloodLabels = <?php echo json_encode(array_keys($bloodByType)); ?>;
                                const criticalLevel = 30;

                                // Set colors based on critical level
                                const barColors = bloodData.map(val => val < criticalLevel ? '#FF4136' : '#2ECC40');

                                // Bar Chart for Blood Inventory
                                const ctxBar = document.getElementById('bloodChart').getContext('2d');
                                new Chart(ctxBar, {
                                    type: 'bar',
                                    data: {
                                        labels: bloodLabels,
                                        datasets: [{
                                            label: 'Available Units',
                                            data: bloodData,
                                            backgroundColor: barColors,
                                            borderColor: barColors,
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                display: false
                                            },
                                            title: {
                                                display: true,
                                                text: 'Blood Inventory Levels',
                                                font: {
                                                    size: 16
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                grid: {
                                                    color: '#e9ecef'
                                                },
                                                ticks: {
                                                    stepSize: 10
                                                }
                                            },
                                            x: {
                                                grid: {
                                                    display: false
                                                }
                                            }
                                        },
                                        animation: {
                                            duration: 1000
                                        }
                                    }
                                });

                                // Pie Chart for Most Requested Blood Types
                                const ctxPie = document.getElementById('requestChart').getContext('2d');
                                new Chart(ctxPie, {
                                    type: 'pie',
                                    data: {
                                        labels: <?php echo json_encode(array_keys($requested_units)); ?>,
                                        datasets: [{
                                            data: <?php echo json_encode(array_values($requested_units)); ?>,
                                            backgroundColor: Object.values(bloodTypeColors)
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'right',
                                                labels: {
                                                    font: {
                                                        size: 12
                                                    }
                                                }
                                            },
                                            title: {
                                                display: true,
                                                text: 'Distribution of Requested Blood Types',
                                                font: {
                                                    size: 16
                                                }
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                <h3 class="mt-4">Your Requests <a href="dashboard-hospital-requests.php" class="btn btn-sm btn-outline-danger ms-2">View All</a></h3>
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Number</th>
                            <th>Patient Name</th>
                            <th>Blood Type</th>
                            <th>Quantity</th>
                            <th>Urgency</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowNum = 1; foreach ($pending_requests ?? [] as $request): ?>
                            <tr>
                                <td><?php echo $rowNum++; ?></td>
                                <td>
                                    <a href="dashboard-hospital-requests.php" class="text-danger text-decoration-none">
                                        <?php echo maskPatientInfo($request['patient_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-')); ?></td>
                                <td><?php echo htmlspecialchars($request['units_requested'] ?? ''); ?> Units</td>
                                <td class="text-danger fw-bold">High</td>
                                <td class="text-danger">Pending</td>
                            </tr>
                        <?php endforeach; ?>
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

<!-- Add Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1" aria-labelledby="trackingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="trackingModalLabel">Blood Request Tracking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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

<style>
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
        border-colo r: #941022;
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
</style>

<script>
    // Track delivery progress
    let progressInterval;
    let deliveryDuration = 0;
    let progressValue = 75; // Start at 75% (3 steps complete)

    function showTrackingModal(requestId, originLat, originLon, destLat, destLon) {
        const trackingModal = new bootstrap.Modal(document.getElementById('trackingModal'));
        trackingModal.show();

        // First mark the first three steps as completed immediately
        const steps = document.querySelectorAll('#trackingModal .step');
        for(let i = 0; i < 3; i++) { // First 3 steps: Submitted, Processing, Approved
            steps[i].classList.add('completed');
            steps[i].classList.remove('active');
            steps[i].querySelector('.step-time').textContent = new Date().toLocaleTimeString();
        }
        
        // Set In Transit step as active
        steps[3].classList.add('active');
        steps[3].classList.remove('completed');
        steps[3].querySelector('.step-time').textContent = new Date().toLocaleTimeString();

        // Set initial progress (75% as first 3 steps are complete)
        document.querySelector('#trackingModal .progress-line-fill').style.width = '75%';

        // Calculate ETA using OpenRoute API
        fetch('track_delivery_progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'calculate_eta',
                origin_lat: originLat,
                origin_lon: originLon,
                dest_lat: destLat,
                dest_lon: destLon
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.error) {
                // Save delivery duration for progress calculation
                deliveryDuration = data.duration * 60; // Convert API minutes to seconds
                
                // Start the progress animation
                startProgressAnimation();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function startProgressAnimation() {
        // Clear any existing interval
        if (progressInterval) {
            clearInterval(progressInterval);
        }

        // Set up progress animation
        progressInterval = setInterval(() => {
            if (progressValue >= 100) {
                clearInterval(progressInterval);
                completeDelivery();
                return;
            }

            // Increment progress value
            progressValue += 0.1;
            document.querySelector('#trackingModal .progress-line-fill').style.width = `${progressValue}%`;
        }, 1000);

        // Auto-complete after the calculated duration (with a little buffer)
        setTimeout(() => {
            if (progressValue < 100) {
                clearInterval(progressInterval);
                completeDelivery();
            }
        }, deliveryDuration * 1000 + 5000); // Add 5 second buffer
    }

    function completeDelivery() {
        // Mark all steps as completed
        const steps = document.querySelectorAll('#trackingModal .step');
        steps.forEach(step => {
            step.classList.add('completed');
            step.classList.remove('active');
            step.querySelector('.step-time').textContent = new Date().toLocaleTimeString();
        });

        // Set progress to 100%
        document.querySelector('#trackingModal .progress-line-fill').style.width = '100%';

        // Trigger confetti animation
        triggerConfetti();
    }

    function triggerConfetti() {
        // First burst
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 }
        });

        // Second burst after a small delay
        setTimeout(() => {
            confetti({
                particleCount: 50,
                angle: 60,
                spread: 55,
                origin: { x: 0 }
            });
            confetti({
                particleCount: 50,
                angle: 120,
                spread: 55,
                origin: { x: 1 }
            });
        }, 250);

        // Final burst
        setTimeout(() => {
            confetti({
                particleCount: 150,
                spread: 100,
                origin: { y: 0.7 }
            });
        }, 500);
    }
</script>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Signature Pad Library -->
    <script src="/REDCROSS/assets/js/signature_pad.umd.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const ctx = document.getElementById('bloodChart').getContext('2d');
            
            // Define thresholds
            const criticalThreshold = 30;
            const warningThreshold = 50;
            
            // Get blood inventory data
            const bloodData = <?php echo json_encode(array_values($bloodByType)); ?>;
            const bloodLabels = <?php echo json_encode(array_keys($bloodByType)); ?>;

            // Calculate colors based on thresholds
            const backgroundColors = bloodData.map(value => {
                if (value < criticalThreshold) {
                    return 'rgba(220, 53, 69, 0.85)'; // Red for critical
                } else if (value < warningThreshold) {
                    return 'rgba(255, 193, 7, 0.85)'; // Yellow for warning
                }
                return 'rgba(40, 167, 69, 0.85)'; // Green for normal
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: bloodLabels,
                    datasets: [{
                        data: bloodData,
                        backgroundColor: backgroundColors,
                        borderColor: backgroundColors,
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    let status = value < criticalThreshold ? 'CRITICAL' :
                                               value < warningThreshold ? 'LOW' : 'NORMAL';
                                    return `${value} units - ${status}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Units Available'
                            }
                        }
                    }
                }
            });
        });
    </script>

    <style>

    /* New styles for total blood bar */
    .total-blood-container {
        padding: 15px;
        border-top: 1px solid #eee;
    }
    .progress {
        border-radius: 8px;
        background-color: #f8f9fa;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    }
    .progress-bar {
        font-weight: 600;
        font-size: 0.95rem;
        text-shadow: 1px 1px 1px rgba(0,0,0,0.1);
        transition: width 0.6s ease;
    }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const ctx = document.getElementById('bloodChart').getContext('2d');
        
        // Define thresholds as percentages
        const criticalThreshold = 30; // 30%
        const warningThreshold = 50; // 50%
        const maxCapacity = 100; // 100 units as reference for percentage
        
        // Get blood inventory data from PHP and convert to percentages
        const bloodData = <?php echo json_encode(array_values($bloodByType)); ?>.map(value => 
            Math.min((value / maxCapacity) * 100, 100) // Convert to percentage, cap at 100%
        );
        const bloodLabels = <?php echo json_encode(array_keys($bloodByType)); ?>;

        // Calculate background colors based on percentage levels
        const backgroundColors = bloodData.map(percentage => {
            if (percentage <= criticalThreshold) {
                return 'rgba(220, 53, 69, 0.85)'; // Red for critical
            } else if (percentage <= warningThreshold) {
                return 'rgba(255, 193, 7, 0.85)'; // Yellow for warning
            }
            return 'rgba(40, 167, 69, 0.85)'; // Green for good
        });

        const borderColors = backgroundColors.map(color => color.replace('0.85', '1'));

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: bloodLabels,
                datasets: [
                    {
                        label: 'Blood Inventory Level',
                        data: bloodData,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 2,
                        borderRadius: 6,
                        barThickness: 35,
                        categoryPercentage: 0.8,
                        barPercentage: 0.9
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const percentage = Math.round(context.raw);
                                let status = '';
                                if (percentage <= criticalThreshold) {
                                    status = '❗ CRITICAL LEVEL';
                                } else if (percentage <= warningThreshold) {
                                    status = '⚠️ WARNING LEVEL';
                                } else {
                                    status = '✅ SUFFICIENT';
                                }
                                return `Capacity: ${percentage}% - ${status}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                            grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 20,
                            font: {
                                weight: '500'
                            },
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        title: {
                            display: true,
                            text: 'Inventory Level (%)',
                            font: {
                                weight: 'bold',
                                size: 14
                            },
                            padding: {top: 10, bottom: 10}
                            }
                        },
                        x: {
                            grid: {
                                display: false
                        },
                        ticks: {
                            font: {
                                weight: '600'
                            }
                        }
                    }
                },
                animation: {
                    duration: 1500,
                    easing: 'easeInOutQuart'
                    }
                }
            });

        // Add threshold lines
        const horizonalLinePlugin = {
            id: 'horizontalLines',
            beforeDraw: (chart) => {
                const {ctx, chartArea: {left, right, top, bottom}, scales: {y}} = chart;
                
                // Critical threshold line
                ctx.beginPath();
                ctx.strokeStyle = 'rgba(220, 53, 69, 0.5)';
                ctx.setLineDash([5, 5]);
                ctx.moveTo(left, y.getPixelForValue(criticalThreshold));
                ctx.lineTo(right, y.getPixelForValue(criticalThreshold));
                ctx.stroke();
                ctx.setLineDash([]);
                
                // Warning threshold line
                ctx.beginPath();
                ctx.strokeStyle = 'rgba(255, 193, 7, 0.5)';
                ctx.setLineDash([5, 5]);
                ctx.moveTo(left, y.getPixelForValue(warningThreshold));
                ctx.lineTo(right, y.getPixelForValue(warningThreshold));
                ctx.stroke();
                ctx.setLineDash([]);
                
                // Add labels for threshold lines
                ctx.font = '12px Arial';
                ctx.textAlign = 'right';
                ctx.fillStyle = 'rgba(220, 53, 69, 0.8)';
                ctx.fillText('Critical (30%)', right - 10, y.getPixelForValue(criticalThreshold) - 5);
                ctx.fillStyle = 'rgba(255, 193, 7, 0.8)';
                ctx.fillText('Warning (50%)', right - 10, y.getPixelForValue(warningThreshold) - 5);
            }
        };
        
        Chart.register(horizonalLinePlugin);
         
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var historyCollapse = document.getElementById('historyCollapse');
        var chevron = document.getElementById('historyChevron');
        var btn = document.getElementById('historyCollapseBtn');
        if (historyCollapse && chevron && btn) {
            historyCollapse.addEventListener('show.bs.collapse', function () {
                chevron.classList.add('rotate');
            });
            historyCollapse.addEventListener('hide.bs.collapse', function () {
                chevron.classList.remove('rotate');
            });
        }
    });
    </script>
</body>
</html>