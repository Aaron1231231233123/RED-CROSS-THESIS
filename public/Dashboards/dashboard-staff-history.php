<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Note: generateSecureToken and hashDonorId functions removed as they are unused
// These functions were defined but never called in this file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /RED-CROSS-THESIS/public/login.php");
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

// Get all donor IDs for reference
$all_donors_ch = curl_init();
curl_setopt_array($all_donors_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form?select=*',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$all_donors_response = curl_exec($all_donors_ch);

if ($all_donors_response !== false) {
    $donors = json_decode($all_donors_response, true) ?: [];
}
curl_close($all_donors_ch);

// Get screening forms with more details
$screening_ch = curl_init();
curl_setopt_array($screening_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=screening_id,donor_form_id,interviewer_id,blood_type,donation_type,body_weight,interview_date',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$screening_response = curl_exec($screening_ch);
$screening_data = [];

if ($screening_response !== false) {
    $screening_data = json_decode($screening_response, true) ?: [];
}
curl_close($screening_ch);

// Get medical histories with more details
$medical_ch = curl_init();
curl_setopt_array($medical_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/medical_history?select=*',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$medical_response = curl_exec($medical_ch);
$medical_data = [];

if ($medical_response !== false) {
    $medical_data = json_decode($medical_response, true) ?: [];
}
curl_close($medical_ch);

// Get physical examinations with more details
$physical_ch = curl_init();
curl_setopt_array($physical_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?select=*',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$physical_response = curl_exec($physical_ch);
$physical_data = [];

if ($physical_response !== false) {
    $physical_data = json_decode($physical_response, true) ?: [];
}
curl_close($physical_ch);

// Get blood collections with more details
$blood_ch = curl_init();
curl_setopt_array($blood_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?select=*',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$blood_response = curl_exec($blood_ch);
$blood_data = [];

if ($blood_response !== false) {
    $blood_data = json_decode($blood_response, true) ?: [];
}
curl_close($blood_ch);

// Add debugging after fetching data
error_log("Screening Data Count: " . count($screening_data));
error_log("Blood Collection Data Count: " . count($blood_data));

// Process donor history with donation counting and current stage
$donor_history = [];
$donor_counts = [];

// First count total donations per donor
foreach ($donors as $donor) {
    $key = $donor['surname'] . '|' . $donor['first_name'] . '|' . $donor['middle_name'];
    if (!isset($donor_counts[$key])) {
        $donor_counts[$key] = 1;
            } else {
        $donor_counts[$key]++;
    }
}

// Process each donor with their counts
$counter = 1;
foreach ($donors as $donor) {
    $key = $donor['surname'] . '|' . $donor['first_name'] . '|' . $donor['middle_name'];
    $donor_id = $donor['donor_id'];
    
    // Debug donor info
    error_log("Processing donor ID: " . $donor_id);
    
    // Initialize donor details
    $donor_details = [
        'screening_info' => null,
        'medical_info' => null,
        'physical_info' => null,
        'blood_info' => null
    ];
    
    // Get screening info
    $screening_entry = array_filter($screening_data, function($entry) use ($donor_id) {
        return isset($entry['donor_form_id']) && $entry['donor_form_id'] == $donor_id;
    });
    if (!empty($screening_entry)) {
        $donor_details['screening_info'] = reset($screening_entry);
    }
    
    // Get medical history info
    $medical_entry = array_filter($medical_data, function($entry) use ($donor_id) {
        return isset($entry['donor_id']) && $entry['donor_id'] == $donor_id;
    });
    if (!empty($medical_entry)) {
        $donor_details['medical_info'] = reset($medical_entry);
    }
    
    // Get physical exam info
    $physical_entry = array_filter($physical_data, function($entry) use ($donor_id) {
        return isset($entry['donor_id']) && $entry['donor_id'] == $donor_id;
    });
    if (!empty($physical_entry)) {
        $donor_details['physical_info'] = reset($physical_entry);
    }
    
    // Get blood collection info
    $blood_entry = array_filter($blood_data, function($entry) use ($donor_id, $screening_data) {
        if (!isset($entry['screening_id'])) return false;
        foreach ($screening_data as $screening) {
            if ($screening['screening_id'] == $entry['screening_id'] && 
                isset($screening['donor_form_id']) && 
                $screening['donor_form_id'] == $donor_id) {
                return true;
            }
        }
        return false;
    });
    if (!empty($blood_entry)) {
        $donor_details['blood_info'] = reset($blood_entry);
    }
    
    // Determine current stage based on available data
    $current_stage = 'Staff';
    
    if ($donor_details['blood_info']) {
        $current_stage = 'Phlebotomist';
    } elseif ($donor_details['physical_info']) {
        $current_stage = 'Physician';
    } elseif ($donor_details['medical_info']) {
        $current_stage = 'Reviewer';
    } elseif ($donor_details['screening_info']) {
        $current_stage = 'Interviewer';
    }
    
    error_log("Final stage determined for donor " . $donor_id . ": " . $current_stage);
    
    $history_entry = [
        'donation_number' => $counter,
        'last_donation' => $donor['submitted_at'],
        'surname' => $donor['surname'],
        'first_name' => $donor['first_name'],
        'middle_name' => $donor['middle_name'],
        'age' => $donor['age'],
        'current_stage' => $current_stage,
        'total_donations' => $donor_counts[$key],
        'donor_id' => $donor_id,
        'details' => $donor_details
    ];

    // Only add if not already added (to avoid duplicates)
    $exists = false;
    foreach ($donor_history as $existing) {
        if ($existing['surname'] === $donor['surname'] && 
            $existing['first_name'] === $donor['first_name'] && 
            $existing['middle_name'] === $donor['middle_name']) {
            $exists = true;
            break;
        }
    }

    if (!$exists) {
        $donor_history[] = $history_entry;
        $counter++;
    }
}

// Add debugging output at the end
error_log("Final donor history array: " . print_r($donor_history, true));

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
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

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

// Sort by latest submission date (newest first)
usort($donors, function($a, $b) {
    return $b['latest_submission'] <=> $a['latest_submission'];
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

        .search-container .row {
            margin-bottom: 0.5rem;
        }

        .search-container .col {
    padding: 0;
}

        .search-container .col .form-control {
    width: 100%;
        }

        .search-container .col .filter-group {
            position: relative;
            display: flex;
            align-items: center;
            margin: 0 8px;
        }

        .filter-clear {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            font-size: 18px;
            font-weight: normal;
            display: none;
            z-index: 2;
            width: 20px;
            height: 20px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            padding-bottom: 2px;
        }

        .filter-clear:hover {
            color: #b22222;
        }

        .filter-active .filter-clear {
            display: flex;
        }

        .filter-group .form-select {
            width: 100%;
            padding-right: 45px;
        }

        /* Add styles for the filter row */
        .search-container .row:last-child {
            margin: 0 -8px;
        }

        .search-container .col {
            padding: 0;
        }

        /* Add margin below search input */
        .search-container .row:first-child {
            margin-bottom: 1rem !important;
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

        /* Add styles for the total badge */
        .badge.bg-danger {
                font-size: 0.9rem;
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
        }

        /* Add styles for the badges */
        .badge {
                font-size: 0.9rem;
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
        }

        .badge.bg-danger {
            background-color: #dc3545;
        }

        .badge.bg-success {
            background-color: #28a745;
        }

        .badge.bg-primary {
            background-color: #007bff;
        }

        .badge.bg-info {
            background-color: #17a2b8;
        }

        .badge.bg-secondary {
            background-color: #6c757d;
        }

        .badge.bg-purple {
            background-color: #6f42c1;
        }

        /* Update form-select styles */
        .form-select {
            border-radius: 0;
            border-color: #ddd;
            padding-right: 45px;
        }

        .form-select:focus {
            border-color: #ddd;
            box-shadow: none;
        }

        .filter-group {
            position: relative;
            display: flex;
            align-items: center;
            margin: 0 8px;
        }

        .filter-clear {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            font-size: 18px;
            font-weight: normal;
            display: none;
            z-index: 2;
            width: 20px;
            height: 20px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            padding-bottom: 2px;
        }

        .filter-clear:hover {
            color: #b22222;
        }

        .filter-active .filter-clear {
            display: flex;
        }

        /* Remove filter-active styling */
        .filter-active .form-select {
            border-color: #ddd;
            background-color: #fff;
        }

        /* Search container spacing */
        .search-container .row:last-child {
            margin: 0 -8px;
        }

        .search-container .col {
            padding: 0;
        }

        .search-container .row:first-child {
            margin-bottom: 1rem !important;
        }

        /* Remove any active state styles */
        .form-select:active,
        .form-select.active {
            border-color: #ddd !important;
            background-color: #fff !important;
            box-shadow: none !important;
        }
    </style>
</head>

<body class="light-mode">
<div class="container-fluid p-0">
        <!-- Header -->
        <div class="dashboard-home-header">
            <h4 class="header-title">Staff Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
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
                            <a class="nav-link" href="dashboard-staff-blood-collection-submission.php">
                                Existing Donor
                            </a>
                        </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard-staff-history.php">Donor History</a>
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
                        <h2 class="welcome-title">Donor History Records</h2>
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="search-container mb-4">
                        <div class="row mb-3">
                            <div class="col-12">
                                <input type="text" 
                                    class="form-control" 
                                    id="searchInput" 
                                    placeholder="Search donors...">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="filter-group">
                                    <select class="form-select" id="stageFilter">
                                        <option value="">All Stages</option>
                                        <option value="Phlebotomist">Phlebotomist</option>
                                        <option value="Physician">Physician</option>
                                        <option value="Reviewer">Reviewer</option>
                                        <option value="Interviewer">Interviewer</option>
                                        <option value="Staff">Staff</option>
                                    </select>
                                    <span class="filter-clear" data-filter="stageFilter">&times;</span>
                    </div>
            </div>
                            <div class="col">
                                <div class="filter-group">
                                    <select class="form-select" id="statusFilter">
                                        <option value="">All Status</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Deferred">Deferred</option>
                                    </select>
                                    <span class="filter-clear" data-filter="statusFilter">&times;</span>
                            </div>
                        </div>
                            <div class="col">
                                <div class="filter-group">
                                    <select class="form-select" id="bloodTypeFilter">
                                        <option value="">All Blood Types</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                    </select>
                                    <span class="filter-clear" data-filter="bloodTypeFilter">&times;</span>
                    </div>
            </div>
                            <div class="col">
                                <div class="filter-group">
                                    <select class="form-select" id="ageFilter">
                                        <option value="">All Ages</option>
                                        <option value="18-25">18-25 years</option>
                                        <option value="26-35">26-35 years</option>
                                        <option value="36-45">36-45 years</option>
                                        <option value="46+">46+ years</option>
                                    </select>
                                    <span class="filter-clear" data-filter="ageFilter">&times;</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="red-separator">

                    <div class="table-responsive">
                        <table class="dashboard-staff-tables table-hover">
                            <thead>
                                <tr>
                                    <th>Donation #</th>
                                    <th>Last Donation</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Age</th>
                                    <th>Blood Type</th>
                                    <th>Donation Type</th>
                                    <th>Current Stage</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php foreach ($donor_history as $history): ?>
                                    <tr>
                                        <td><?php echo $history['donation_number']; ?></td>
                                        <td><?php echo date('F d, Y', strtotime($history['last_donation'])); ?></td>
                                        <td><?php echo htmlspecialchars($history['surname']); ?></td>
                                        <td><?php echo htmlspecialchars($history['first_name']); ?></td>
                                        <td><?php echo htmlspecialchars($history['middle_name']); ?></td>
                                        <td><?php echo $history['age']; ?></td>
                                        <td><?php 
                                            if ($history['details']['screening_info']) {
                                                echo $history['details']['screening_info']['blood_type'] ?? 'N/A';
                                        } else {
                                                echo 'N/A';
                                        }
                                        ?></td>
                                            <td><?php 
                                            if ($history['details']['screening_info']) {
                                                echo $history['details']['screening_info']['donation_type'] ?? 'N/A';
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                switch($history['current_stage']) {
                                                    case 'Phlebotomist':
                                                        echo 'bg-purple';
                                                        break;
                                                    case 'Physician':
                                                        echo 'bg-success';
                                                        break;
                                                    case 'Reviewer':
                                                        echo 'bg-primary';
                                                        break;
                                                    case 'Interviewer':
                                                        echo 'bg-info';
                                                        break;
                                                    default:
                                                        echo 'bg-secondary';
                                                }
                                            ?>">
                                                <?php echo $history['current_stage']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status = 'Pending';
                                            $statusClass = 'bg-warning';
                                            $deferralReason = '';
                                            
                                            // Check medical history approval
                                            if ($history['details']['medical_info'] && 
                                                isset($history['details']['medical_info']['medical_approval']) &&
                                                $history['details']['medical_info']['medical_approval'] === 'Declined') {
                                                $status = 'Deferred';
                                                $statusClass = 'bg-danger';
                                                $deferralReason = 'Medical History Declined';
                                            }
                                            
                                            // Check screening form disapproval
                                            if ($history['details']['screening_info'] && 
                                                !empty($history['details']['screening_info']['disapproval_reason'])) {
                                                $status = 'Deferred';
                                                $statusClass = 'bg-danger';
                                                $deferralReason = $history['details']['screening_info']['disapproval_reason'];
                                            }
                                            
                                            // Check physical examination disapproval
                                            if ($history['details']['physical_info']) {
                                                if (!empty($history['details']['physical_info']['disapproval_reason'])) {
                                                    $status = 'Deferred';
                                                    $statusClass = 'bg-danger';
                                                    $deferralReason = $history['details']['physical_info']['disapproval_reason'];
                                                }
                                                if ($history['details']['physical_info']['remarks'] === 'Permanently Deferred' || 
                                                    $history['details']['physical_info']['remarks'] === 'Temporarily Deferred') {
                                                    $status = $history['details']['physical_info']['remarks'];
                                                    $statusClass = 'bg-danger';
                                                    $deferralReason = $history['details']['physical_info']['remarks'];
                                                }
                                            }
                                            
                                            // Check blood collection success
                                            if ($history['details']['blood_info']) {
                                                if (isset($history['details']['blood_info']['is_successful'])) {
                                                    if ($history['details']['blood_info']['is_successful'] === true) {
                                                        $status = 'Completed';
                                                        $statusClass = 'bg-success';
                                                        $deferralReason = '';
                                                    } else {
                                                        $status = 'Deferred';
                                                        $statusClass = 'bg-danger';
                                                        $deferralReason = 'Blood Collection Failed';
                                                    }
                                                }
                                                if ($history['details']['blood_info']['status'] === 'completed') {
                                                    $status = 'Completed';
                                                    $statusClass = 'bg-success';
                                                    $deferralReason = '';
                                                } elseif ($history['details']['blood_info']['status'] === 'failed') {
                                                    $status = 'Deferred';
                                                    $statusClass = 'bg-danger';
                                                    $deferralReason = 'Blood Collection Failed';
                                                }
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>" 
                                                  <?php if ($deferralReason): ?>
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top" 
                                                  title="<?php echo htmlspecialchars($deferralReason); ?>"
                                                  <?php endif; ?>>
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td><?php
                                            // Get the most recent update timestamp
                                            $latest_timestamp = $history['last_donation'];
                                            
                                            // Check all related records for the most recent update
                                            if ($history['details']['screening_info']['updated_at'] ?? false) {
                                                $screening_time = strtotime($history['details']['screening_info']['updated_at']);
                                                if ($screening_time > strtotime($latest_timestamp)) {
                                                    $latest_timestamp = $history['details']['screening_info']['updated_at'];
                                                }
                                            }
                                            
                                            if ($history['details']['medical_info']['updated_at'] ?? false) {
                                                $medical_time = strtotime($history['details']['medical_info']['updated_at']);
                                                if ($medical_time > strtotime($latest_timestamp)) {
                                                    $latest_timestamp = $history['details']['medical_info']['updated_at'];
                                                }
                                            }
                                            
                                            if ($history['details']['physical_info']['updated_at'] ?? false) {
                                                $physical_time = strtotime($history['details']['physical_info']['updated_at']);
                                                if ($physical_time > strtotime($latest_timestamp)) {
                                                    $latest_timestamp = $history['details']['physical_info']['updated_at'];
                                                }
                                            }
                                            
                                            if ($history['details']['blood_info']['updated_at'] ?? false) {
                                                $blood_time = strtotime($history['details']['blood_info']['updated_at']);
                                                if ($blood_time > strtotime($latest_timestamp)) {
                                                    $latest_timestamp = $history['details']['blood_info']['updated_at'];
                                                }
                                            }
                                            
                                            // Format the timestamp
                                            echo date('M d, Y', strtotime($latest_timestamp));
                                        ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Donor history navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">Previous</a>
                                    </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a>
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
            
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const stageFilter = document.getElementById('stageFilter');
            const statusFilter = document.getElementById('statusFilter');
            const bloodTypeFilter = document.getElementById('bloodTypeFilter');
            const ageFilter = document.getElementById('ageFilter');
            const donorTableBody = document.getElementById('donorTableBody');
            
            // Store original rows for search reset
            const originalRows = Array.from(donorTableBody.getElementsByTagName('tr'));
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            function getAgeRange(age) {
                age = parseInt(age);
                if (age >= 18 && age <= 25) return '18-25';
                if (age >= 26 && age <= 35) return '26-35';
                if (age >= 36 && age <= 45) return '36-45';
                if (age >= 46) return '46+';
                return '';
            }
            
            function filterRows() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedStage = stageFilter.value;
                const selectedStatus = statusFilter.value;
                const selectedBloodType = bloodTypeFilter.value;
                const selectedAgeRange = ageFilter.value;
                
                // Update filter groups visibility
                [stageFilter, statusFilter, bloodTypeFilter, ageFilter].forEach(filter => {
                    const filterGroup = filter.closest('.filter-group');
                    if (filter.value) {
                        filterGroup.classList.add('filter-active');
                    } else {
                        filterGroup.classList.remove('filter-active');
                    }
                });

                originalRows.forEach(row => {
                    const cells = Array.from(row.getElementsByTagName('td'));
                    const text = cells.map(cell => cell.textContent.toLowerCase()).join(' ');
                    
                    const stage = cells[8].textContent.trim();
                    const status = cells[9].textContent.trim();
                    const bloodType = cells[6].textContent.trim();
                    const age = parseInt(cells[5].textContent.trim());
                    const ageRange = getAgeRange(age);
                    
                    const matchesSearch = text.includes(searchTerm);
                    const matchesStage = !selectedStage || stage === selectedStage;
                    const matchesStatus = !selectedStatus || status === selectedStatus;
                    const matchesBloodType = !selectedBloodType || bloodType === selectedBloodType;
                    const matchesAgeRange = !selectedAgeRange || ageRange === selectedAgeRange;
                    
                    if (matchesSearch && matchesStage && matchesStatus && matchesBloodType && matchesAgeRange) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            // Add event listeners
            searchInput.addEventListener('input', filterRows);
            stageFilter.addEventListener('change', filterRows);
            statusFilter.addEventListener('change', filterRows);
            bloodTypeFilter.addEventListener('change', filterRows);
            ageFilter.addEventListener('change', filterRows);
            
            // Add click handlers for clear buttons
            document.querySelectorAll('.filter-clear').forEach(button => {
                button.addEventListener('click', function() {
                    const filterId = this.getAttribute('data-filter');
                    document.getElementById(filterId).value = '';
                    filterRows();
                });
            });

            // Initial call to ensure proper state
            filterRows();
        });
    </script>
</body>
</html>