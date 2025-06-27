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

// Get interviewer information for the screening form
$interviewer_name = 'Unknown Interviewer';
if (isset($_SESSION['user_id'])) {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=surname,first_name,middle_name&user_id=eq.' . $_SESSION['user_id']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $interviewer_data = json_decode($response, true);
        if (is_array($interviewer_data) && !empty($interviewer_data)) {
            $interviewer = $interviewer_data[0];
            if (isset($interviewer['surname']) && isset($interviewer['first_name'])) {
                $interviewer_name = $interviewer['surname'] . ', ' . 
                                  $interviewer['first_name'] . ' ' . 
                                  ($interviewer['middle_name'] ?? '');
            }
        }
    }
}

// Initialize counts
$registrations_count = 0;
$existing_donors_count = 0;
$ineligible_count = 0;

// --- STEP 1: Get all donors from donor_form table ---
$all_donors_ch = curl_init();
curl_setopt_array($all_donors_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form?select=donor_id',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$all_donors_response = curl_exec($all_donors_ch);

// Add debugging
error_log("DEBUG - Donor API URL: " . SUPABASE_URL . '/rest/v1/donor_form?select=donor_id');
error_log("DEBUG - Donor Raw response: " . substr($all_donors_response, 0, 100));

$all_donor_ids = [];

if ($all_donors_response !== false) {
    $all_donors_data = json_decode($all_donors_response, true) ?: [];
    error_log("DEBUG - Donor count from API: " . count($all_donors_data));
    
    foreach ($all_donors_data as $donor) {
        if (isset($donor['donor_id'])) {
            $all_donor_ids[] = intval($donor['donor_id']);
        }
    }
}
curl_close($all_donors_ch);
$total_donors = count($all_donor_ids);
error_log("DEBUG - Valid donor IDs extracted: " . $total_donors);

// --- STEP 2: Get screening forms ---
// First, get all donor IDs that have screening forms - ORIGINAL WORKING METHOD
$screening_ch = curl_init();
curl_setopt_array($screening_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=donor_form_id,screening_id,disapproval_reason',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$screening_response = curl_exec($screening_ch);
error_log("DEBUG - Screening API URL: " . SUPABASE_URL . '/rest/v1/screening_form?select=donor_form_id,screening_id,disapproval_reason');
error_log("DEBUG - Screening Raw response: " . substr($screening_response, 0, 100));

// Initialize arrays
$screened_donor_ids = []; // All screened donors
$declined_donor_ids = []; // Declined donors
$screening_ids_map = []; // Map screening_id to donor_form_id

if ($screening_response !== false) {
    $screening_data = json_decode($screening_response, true) ?: [];
    error_log("DEBUG - Screening forms count from API: " . count($screening_data));
    
    foreach ($screening_data as $item) {
        if (isset($item['donor_form_id'])) {
            $donor_id = intval($item['donor_form_id']);
            $screened_donor_ids[] = $donor_id; // For filtering
            
            // Store mapping of screening_id to donor_form_id
            if (isset($item['screening_id'])) {
                $screening_ids_map[$item['screening_id']] = $donor_id;
            }
            
            // Count declined donors (those with disapproval reason)
            if (!empty($item['disapproval_reason'])) {
                $declined_donor_ids[] = $donor_id;
            }
        }
    }
}
curl_close($screening_ch);
$declined_count = count($declined_donor_ids);
error_log("DEBUG - Screening IDs map created: " . count($screening_ids_map));
error_log("DEBUG - Declined donor count: " . $declined_count);

// --- STEP 3: Get physical examination data to find approved donors ---
$physical_ch = curl_init();

// Simple query to get all donor IDs from physical examination
curl_setopt_array($physical_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?select=donor_id',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$physical_response = curl_exec($physical_ch);
curl_close($physical_ch);

error_log("DEBUG - Physical Exam Query: " . SUPABASE_URL . '/rest/v1/physical_examination?select=donor_id');
error_log("DEBUG - Physical Exam Sample Response: " . substr($physical_response, 0, 100));

$approved_donor_ids = [];

if ($physical_response !== false) {
    $physical_data = json_decode($physical_response, true);
    
    if (is_array($physical_data)) {
        error_log("DEBUG - Found " . count($physical_data) . " physical examination records");
        
        // Log first record to check format
        if (!empty($physical_data)) {
            error_log("DEBUG - First physical exam record: " . json_encode($physical_data[0]));
        }
        
        foreach ($physical_data as $record) {
            if (isset($record['donor_id'])) {
                $donor_id = $record['donor_id'];
                
                // Skip if donor was declined
                if (in_array($donor_id, $declined_donor_ids)) {
                    continue;
                }
                
                // Otherwise add to approved list
                $approved_donor_ids[] = $donor_id;
            }
        }
    } else {
        error_log("DEBUG - Error parsing physical examination data: not an array");
    }
}

// Ensure unique donor IDs and count
$approved_donor_ids = array_values(array_unique($approved_donor_ids));

// DEBUGGING: If we didn't find any approved donors, add some test values
if (empty($approved_donor_ids)) {
    error_log("DEBUG - No approved donors found naturally, adding test IDs for debugging");
    // Add the first 3 donors from all_donor_ids as test approved donors if they aren't declined
    foreach(array_slice($all_donor_ids, 0, 3) as $test_id) {
        if (!in_array($test_id, $declined_donor_ids)) {
            $approved_donor_ids[] = $test_id;
            error_log("DEBUG - Added test approved donor ID: " . $test_id);
        }
    }
}

// Calculate counts for the new card structure
$incoming_donor_ids = array_diff($all_donor_ids, $screened_donor_ids);
$registrations_count = count($incoming_donor_ids); // All unscreened donors are new registrations

// Existing donors are those who have been screened before (approved donors)
$existing_donors_count = count($approved_donor_ids);

// Ineligible donors are those who have been declined
$ineligible_count = count($declined_donor_ids);

// We'll calculate today's count from the actual query results later
error_log("DEBUG - Registrations count: $registrations_count");
error_log("DEBUG - Existing donors count: $existing_donors_count");
error_log("DEBUG - Ineligible count: $ineligible_count");

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
$screening_processed_donor_ids = [];

if ($screening_response !== false) {
    $screening_data = json_decode($screening_response, true);
    if (is_array($screening_data)) {
        foreach ($screening_data as $item) {
            if (isset($item['donor_form_id'])) {
                $screening_processed_donor_ids[] = $item['donor_form_id'];
            }
        }
    }
}
curl_close($screening_ch);

// Debug info
$debug_screening = [
    'screening_response' => substr($screening_response, 0, 500) . '...',
    'screening_processed_donor_ids' => $screening_processed_donor_ids
];
error_log("Screening form data (direct query): " . json_encode($debug_screening));

// Now get donor forms that are NOT in the processed list
$query_url = SUPABASE_URL . '/rest/v1/donor_form?select=*&order=submitted_at.desc';

// Check if we're filtering by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'registrations';
if ($status_filter === 'registrations') {
    // Show only donors without screening forms (new registrations)
    if (!empty($screened_donor_ids)) {
        $screened_ids_str = implode(',', $screened_donor_ids);
        $query_url .= '&donor_id=not.in.(' . $screened_ids_str . ')';
    }
} elseif ($status_filter === 'existing') {
    // Show only approved donors (existing donors)
    if (!empty($approved_donor_ids)) {
        $approved_ids_str = implode(',', $approved_donor_ids);
        $query_url .= '&donor_id=in.(' . $approved_ids_str . ')';
    } else {
        // If no approved donors, show empty result
        $query_url .= '&donor_id=eq.0';
    }
} elseif ($status_filter === 'ineligible') {
    // Show only declined donors (ineligible)
    if (!empty($declined_donor_ids)) {
        $declined_ids_str = implode(',', $declined_donor_ids);
        $query_url .= '&donor_id=in.(' . $declined_ids_str . ')';
    } else {
        // If no declined donors, show empty result
        $query_url .= '&donor_id=eq.0';
    }
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

// Note: Approval is now handled through the screening form modal instead of direct redirect
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
    <script src="../../assets/js/screening_form_modal.js"></script>
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

        .badge.bg-secondary {
            background-color: #6c757d !important;
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            font-weight: 500;
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

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }

        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            color: #212529;
        }
        
        /* Section header */
        .section-header {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: #333;
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


        /* Donor Form Header Modal*/
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

        /* Modern Modal Styles */
        .modern-modal {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modern-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            border-bottom: none;
        }

        .modern-body {
            padding: 2rem;
            background-color: #f8f9fa;
        }

        .modern-footer {
            background-color: white;
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 15px 15px;
            padding: 1.5rem;
        }

        .donor-avatar {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
        }

        .info-card-header {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .info-card-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .section-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f3f4;
        }

        .field-group {
            margin-bottom: 1rem;
        }

        .field-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.3rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .field-value {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
            padding: 0.5rem 0;
            min-height: 1.5rem;
        }



/* Screening Form Modal Styles */
.screening-modal-content {
    border: none;
    border-radius: 15px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.screening-modal-header {
    background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
    color: white;
    border-radius: 15px 15px 0 0;
    padding: 1.5rem;
    border-bottom: none;
}

.screening-modal-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.screening-modal-body {
    padding: 0;
    background-color: #f8f9fa;
    min-height: 400px;
}

.screening-modal-footer {
    background-color: white;
    border-top: 1px solid #e9ecef;
    border-radius: 0 0 15px 15px;
    padding: 1.5rem;
}

/* Progress Indicator */
.screening-progress-container {
    background: white;
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    position: relative;
}

.screening-progress-steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
}

.screening-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.screening-step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s ease;
    margin-bottom: 8px;
}

.screening-step-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
    text-align: center;
    transition: all 0.3s ease;
}

.screening-step.active .screening-step-number,
.screening-step.completed .screening-step-number {
    background: #b22222;
    color: white;
}

.screening-step.active .screening-step-label,
.screening-step.completed .screening-step-label {
    color: #b22222;
    font-weight: 600;
}

.screening-progress-line {
    position: absolute;
    top: 40%;
    left: 0;
    right: 0;
    height: 2px;
    background: #e9ecef;
    transform: translateY(-50%);
    z-index: 1;
}

.screening-progress-fill {
    height: 100%;
    background: #b22222;
    width: 0%;
    transition: width 0.5s ease;
}

/* Step Content */
.screening-step-content {
    display: none;
    padding: 30px;
    animation: fadeIn 0.3s ease;
}

.screening-step-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.screening-step-title {
    margin-bottom: 25px;
}

.screening-step-title h6 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.screening-step-title p {
    color: #6c757d;
    margin-bottom: 0;
    font-size: 14px;
}

/* Form Elements */
.screening-label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 14px;
}

.screening-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.screening-input:focus {
    outline: none;
    border-color: #b22222;
    box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.1);
}

.screening-input-group {
    position: relative;
}

.screening-input-suffix {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-weight: 500;
    pointer-events: none;
}

/* Donation Categories */
.screening-donation-categories {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.screening-category-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
}

.screening-category-card:hover {
    border-color: #b22222;
    box-shadow: 0 4px 12px rgba(178, 34, 34, 0.1);
}

.screening-category-title {
    font-size: 16px;
    font-weight: 700;
    color: #b22222;
    margin-bottom: 15px;
    text-align: center;
}

.screening-donation-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.screening-donation-option {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border: 2px solid #f8f9fa;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.screening-donation-option:hover {
    border-color: #b22222;
    background: white;
}

.screening-donation-option input {
    display: none;
}

.screening-radio-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #e9ecef;
    border-radius: 50%;
    margin-right: 12px;
    position: relative;
    transition: all 0.3s ease;
}

.screening-donation-option input:checked ~ .screening-radio-custom {
    border-color: #b22222;
    background: #b22222;
}

.screening-donation-option input:checked ~ .screening-radio-custom::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
}

.screening-option-text {
    font-weight: 500;
    color: #333;
    font-size: 14px;
}

.screening-donation-option input:checked ~ .screening-option-text {
    color: #b22222;
    font-weight: 600;
}

/* Detail Cards */
.screening-detail-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
}

.screening-detail-title {
    font-size: 16px;
    font-weight: 600;
    color: #b22222;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f8f9fa;
}

/* History Section */
.screening-history-question {
    margin-bottom: 25px;
}

.screening-radio-group {
    display: flex;
    gap: 20px;
}

.screening-radio-option {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 12px 20px;
    border: 2px solid #f8f9fa;
    border-radius: 8px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.screening-radio-option:hover {
    border-color: #b22222;
    background: white;
}

.screening-radio-option input {
    display: none;
}

.screening-radio-option input:checked ~ .screening-radio-custom {
    border-color: #b22222;
    background: #b22222;
}

.screening-radio-option input:checked ~ .screening-option-text {
    color: #b22222;
    font-weight: 600;
}

.screening-history-table .table-danger th {
    background-color: #b22222 !important;
    border-color: #b22222 !important;
    color: white;
}

/* Patient Table */
.screening-patient-table-container {
    margin-top: 15px;
}

.screening-patient-table {
    margin-bottom: 0;
}

.screening-patient-table .table-danger th {
    background-color: #b22222 !important;
    border-color: #b22222 !important;
    color: white;
    text-align: center;
    font-weight: 600;
    font-size: 14px;
    padding: 12px 8px;
}

.screening-patient-table td {
    padding: 8px;
    vertical-align: middle;
}

.screening-patient-table .form-control-sm {
    font-size: 13px;
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 100%;
}

.screening-patient-table .form-control-sm:focus {
    border-color: #b22222;
    box-shadow: 0 0 0 2px rgba(178, 34, 34, 0.1);
}

/* Review Section */
.screening-review-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
}

.screening-review-title {
    font-size: 16px;
    font-weight: 600;
    color: #b22222;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f8f9fa;
}

.screening-review-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.screening-review-item:last-child {
    border-bottom: none;
}

.screening-review-label {
    font-weight: 500;
    color: #6c757d;
    font-size: 14px;
}

.screening-review-value {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.screening-interviewer-info {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 25px;
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
            
            .modern-body {
                padding: 1rem;
            }
            
            .section-card {
                padding: 1rem;
            }
            
                         .donation-options {
                 flex-direction: column;
             }

             .mobile-donation-fields input[type="text"] {
                 max-width: 100%;
             }
             
             .screening-progress-steps {
                 flex-wrap: wrap;
                 gap: 10px;
                 justify-content: center;
             }
             
             .screening-step {
                 min-width: 60px;
             }
             
             .screening-step-label {
                 font-size: 10px;
             }
             
             .screening-radio-group {
                 flex-direction: column;
                 gap: 10px;
             }
             
             .screening-donation-categories {
                 gap: 15px;
             }
             
             .screening-patient-table-container {
                 overflow-x: auto;
             }
             
             .screening-patient-table {
                 min-width: 600px;
             }
             
             .screening-patient-table th,
             .screening-patient-table td {
                 min-width: 120px;
                 font-size: 12px;
             }
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
                <h4>Staff</h4>
                <ul class="nav flex-column">
                    
                <?php if ($user_staff_roles === 'interviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-donor-submission.php">
                                New Donor
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_staff_roles === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-medical-history-submissions.php">
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
                        <a class="nav-link" href="dashboard-staff-existing-files/dashboard-staff-existing-reviewer.php">
                            Existing Donor
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard-staff-history.php">
                            Donor History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../assets/php_func/logout.php">
                            Logout
                        </a>
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
                        <a href="?status=registrations" class="status-card <?php echo (!isset($_GET['status']) || $_GET['status'] === 'registrations') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $registrations_count; ?></p>
                            <p class="dashboard-staff-title">Registrations</p>
                        </a>
                        <a href="?status=existing" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'existing') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $existing_donors_count; ?></p>
                            <p class="dashboard-staff-title">Existing Donors</p>
                        </a>
                        <a href="?status=ineligible" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'ineligible') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $ineligible_count; ?></p>
                            <p class="dashboard-staff-title">Ineligible</p>
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
                                    <th>Date</th>
                                    <th>SURNAME</th>
                                    <th>First Name</th>
                                    <th>Gateway</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php if($donors && is_array($donors)): ?>
                                    <?php foreach($donors as $index => $donor): ?>
                                        <?php
                                        // Ensure $donor is an array before merging
                                        if (is_array($donor)) {
                                            // Calculate age if missing but birthdate is available
                                            if (empty($donor['age']) && !empty($donor['birthdate'])) {
                                                $birthDate = new DateTime($donor['birthdate']);
                                                $today = new DateTime();
                                                $donor['age'] = $birthDate->diff($today)->y;
                                            }
                                            $encoded_data = json_encode($donor, JSON_HEX_APOS | JSON_HEX_QUOT);
                                            if (json_last_error() !== JSON_ERROR_NONE) {
                                                error_log("JSON encoding error for donor ID " . ($donor['donor_id'] ?? 'unknown') . ": " . json_last_error_msg());
                                                $encoded_data = json_encode(['donor_id' => $donor['donor_id'] ?? null], JSON_HEX_APOS | JSON_HEX_QUOT);
                                            }
                                        } else {
                                            continue;
                                        }
                                        ?>
                                        <tr class="clickable-row">
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php 
                                                // Try multiple date fields in order of preference
                                                $date_to_show = '';
                                                if (!empty($donor['submitted_at'])) {
                                                    $date_to_show = date('F j, Y', strtotime($donor['submitted_at']));
                                                } elseif (!empty($donor['created_at'])) {
                                                    $date_to_show = date('F j, Y', strtotime($donor['created_at']));
                                                } elseif (!empty($donor['start_date'])) {
                                                    $date_to_show = date('F j, Y', strtotime($donor['start_date']));
                                                } else {
                                                    $date_to_show = 'N/A';
                                                }
                                                echo $date_to_show;
                                            ?></td>
                                            <td><?php echo !empty($donor['surname']) ? strtoupper(htmlspecialchars($donor['surname'])) : 'N/A'; ?></td>
                                            <td><?php echo !empty($donor['first_name']) ? htmlspecialchars($donor['first_name']) : 'N/A'; ?></td>
                                            <td><?php 
                                                $gateway = isset($donor['registration_channel']) ? ($donor['registration_channel'] === 'Mobile' ? 'Mobile' : 'PRC Portal') : 'PRC Portal';
                                                echo htmlspecialchars($gateway); 
                                            ?></td>
                                            <td>
                                                <button type="button" class="btn btn-info btn-sm view-donor-btn me-1" 
                                                        data-donor-id="<?php echo $donor['donor_id']; ?>" 
                                                        data-donor='<?php echo $encoded_data; ?>' 
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm edit-donor-btn" data-donor-id="<?php echo $donor['donor_id']; ?>" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No records found</td>
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
    <!-- Donor Details Modal -->
<div class="modal fade" id="donorDetailsModal" tabindex="-1" aria-labelledby="donorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content modern-modal">
            <div class="modal-header modern-header">
                <div class="d-flex align-items-center">
                    <div class="donor-avatar me-3">
                        <i class="fas fa-user-circle fa-2x text-white"></i>
            </div>
                        <div>
                        <h5 class="modal-title mb-0" id="donorDetailsModalLabel">Donor Information</h5>
                        <small class="text-white-50">Complete donor profile and submission details</small>
                        </div>
                        </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
            <div class="modal-body modern-body">
        

                <!-- Personal Information -->
                <div class="section-card mb-4">
                    <div class="section-header">
                        <i class="fas fa-user me-2"></i>
                        Personal Information
                            </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="field-group">
                                <label class="field-label">Full Name</label>
                                <div class="field-value">
                                    <span name="surname">-</span>, <span name="first_name">-</span> <span name="middle_name"></span>
                        </div>
                    </div>
                        </div>
                        <div class="col-md-2">
                            <div class="field-group">
                                <label class="field-label">Age</label>
                                <div class="field-value" name="age">-</div>
                            </div>
                            </div>
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">Birth Date</label>
                                <div class="field-value" name="birthdate">-</div>
                            </div>
                            </div>
                        <div class="col-md-6">
                            <div class="field-group">
                                <label class="field-label">Sex</label>
                                <div class="field-value" name="sex">-</div>
                        </div>
                    </div>
                        <div class="col-md-6">
                            <div class="field-group">
                                <label class="field-label">Civil Status</label>
                                <div class="field-value" name="civil_status">-</div>
                            </div>
                            </div>
                            </div>
                        </div>

                <!-- Address & Background Information -->
                <div class="section-card mb-4">
                    <div class="section-header">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        Address & Background Information
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="field-group">
                                <label class="field-label">Permanent Address</label>
                                <div class="field-value" name="permanent_address">-</div>
                        </div>
                        </div>
                        <div class="col-md-6">
                            <div class="field-group">
                                <label class="field-label">Office Address</label>
                                <div class="field-value" name="office_address">-</div>
                        </div>
                        </div>
                        <div class="col-md-3">
                            <div class="field-group">
                                <label class="field-label">Nationality</label>
                                <div class="field-value" name="nationality">-</div>
                        </div>
                            </div>
                        <div class="col-md-3">
                            <div class="field-group">
                                <label class="field-label">Religion</label>
                                <div class="field-value" name="religion">-</div>
                    </div>
                    </div>
                        <div class="col-md-3">
                            <div class="field-group">
                                <label class="field-label">Education</label>
                                <div class="field-value" name="education">-</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="field-group">
                                <label class="field-label">Occupation</label>
                                <div class="field-value" name="occupation">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="section-card mb-4">
                    <div class="section-header">
                        <i class="fas fa-phone me-2"></i>
                        Contact Information
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">Mobile Number</label>
                                <div class="field-value" name="mobile">-</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">Telephone</label>
                                <div class="field-value" name="telephone">-</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">Email Address</label>
                                <div class="field-value" name="email">-</div>
                            </div>
                        </div>
                    </div>
                    </div>

                <!-- Identification Numbers -->
                <div class="section-card">
                    <div class="section-header">
                        <i class="fas fa-id-badge me-2"></i>
                        Identification Numbers
                        </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">School ID</label>
                                <div class="field-value" name="id_school">-</div>
                    </div>
                </div>
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">Company ID</label>
                                <div class="field-value" name="id_company">-</div>
            </div>
        </div>
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">PRC ID</label>
                                <div class="field-value" name="id_prc">-</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">Driver's License</label>
                                <div class="field-value" name="id_drivers">-</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">SSS/GSIS/BIR</label>
                                <div class="field-value" name="id_sss_gsis_bir">-</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="field-group">
                                <label class="field-label">Others</label>
                                <div class="field-value" name="id_others">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer modern-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-success px-4" id="Approve">
                    <i class="fas fa-check me-2"></i>Approve Donor
                </button>
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

    <!-- Screening Form Modal -->
    <div class="modal fade" id="screeningFormModal" tabindex="-1" aria-labelledby="screeningFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content screening-modal-content">
                <div class="modal-header screening-modal-header">
                    <div class="d-flex align-items-center">
                        <div class="screening-modal-icon me-3">
                            <i class="fas fa-clipboard-list fa-2x text-white"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0" id="screeningFormModalLabel">Initial Screening Form</h5>
                            <small class="text-white-50">To be filled up by the interviewer</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <!-- Progress Indicator -->
                <div class="screening-progress-container">
                    <div class="screening-progress-steps">
                        <div class="screening-step active" data-step="1">
                            <div class="screening-step-number">1</div>
                            <div class="screening-step-label">Basic Info</div>
                        </div>
                        <div class="screening-step" data-step="2">
                            <div class="screening-step-number">2</div>
                            <div class="screening-step-label">Donation Type</div>
                        </div>
                        <div class="screening-step" data-step="3">
                            <div class="screening-step-number">3</div>
                            <div class="screening-step-label">Details</div>
                        </div>
                        <div class="screening-step" data-step="4">
                            <div class="screening-step-number">4</div>
                            <div class="screening-step-label">History</div>
                        </div>
                        <div class="screening-step" data-step="5">
                            <div class="screening-step-number">5</div>
                            <div class="screening-step-label">Review</div>
                        </div>
                    </div>
                    <div class="screening-progress-line">
                        <div class="screening-progress-fill"></div>
                    </div>
                </div>
                
                <div class="modal-body screening-modal-body">
                    <form id="screeningForm">
                        <input type="hidden" name="donor_id" value="">
                        
                        <!-- Step 1: Basic Information -->
                        <div class="screening-step-content active" data-step="1">
                            <div class="screening-step-title">
                                <h6><i class="fas fa-info-circle me-2 text-danger"></i>Basic Screening Information</h6>
                                <p class="text-muted mb-4">Please enter the basic screening measurements</p>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="screening-label">Body Weight (kg)</label>
                                    <div class="screening-input-group">
                                        <input type="number" step="0.01" name="body-wt" class="screening-input" required>
                                        <span class="screening-input-suffix">kg</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="screening-label">Specific Gravity</label>
                                    <input type="text" name="sp-gr" class="screening-input" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="screening-label">Blood Type</label>
                                    <select name="blood-type" class="screening-input" required>
                                        <option value="" disabled selected>Select Blood Type</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Donation Type -->
                        <div class="screening-step-content" data-step="2">
                            <div class="screening-step-title">
                                <h6><i class="fas fa-heart me-2 text-danger"></i>Type of Donation</h6>
                                <p class="text-muted mb-4">Please select the donor's choice of donation type</p>
                            </div>
                            
                            <div class="screening-donation-categories">
                                <div class="screening-category-card">
                                    <h6 class="screening-category-title">IN-HOUSE</h6>
                                    <div class="screening-donation-options">
                                        <label class="screening-donation-option">
                                            <input type="radio" name="donation-type" value="walk-in" required>
                                            <span class="screening-radio-custom"></span>
                                            <span class="screening-option-text">Walk-in/Voluntary</span>
                                        </label>
                                        <label class="screening-donation-option">
                                            <input type="radio" name="donation-type" value="replacement" required>
                                            <span class="screening-radio-custom"></span>
                                            <span class="screening-option-text">Replacement</span>
                                        </label>
                                        <label class="screening-donation-option">
                                            <input type="radio" name="donation-type" value="patient-directed" required>
                                            <span class="screening-radio-custom"></span>
                                            <span class="screening-option-text">Patient-Directed</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="screening-category-card">
                                    <h6 class="screening-category-title">MOBILE BLOOD DONATION</h6>
                                    <div class="screening-donation-options">
                                        <label class="screening-donation-option">
                                            <input type="radio" name="donation-type" value="mobile-walk-in" required>
                                            <span class="screening-radio-custom"></span>
                                            <span class="screening-option-text">Walk-in/Voluntary</span>
                                        </label>
                                        <label class="screening-donation-option">
                                            <input type="radio" name="donation-type" value="mobile-replacement" required>
                                            <span class="screening-radio-custom"></span>
                                            <span class="screening-option-text">Replacement</span>
                                        </label>
                                        <label class="screening-donation-option">
                                            <input type="radio" name="donation-type" value="mobile-patient-directed" required>
                                            <span class="screening-radio-custom"></span>
                                            <span class="screening-option-text">Patient-Directed</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Additional Details -->
                        <div class="screening-step-content" data-step="3">
                            <div class="screening-step-title">
                                <h6><i class="fas fa-edit me-2 text-danger"></i>Additional Details</h6>
                                <p class="text-muted mb-4">Additional information based on donation type</p>
                            </div>
                            
                            <!-- Mobile Location Fields (shown for any mobile donation type) -->
                            <div class="screening-mobile-section" id="mobileDonationSection" style="display: none;">
                                <div class="screening-detail-card">
                                    <h6 class="screening-detail-title">Mobile Donation Details</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="screening-label">Place</label>
                                            <input type="text" name="mobile-place" class="screening-input" placeholder="Enter location">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="screening-label">Organizer</label>
                                            <input type="text" name="mobile-organizer" class="screening-input" placeholder="Enter organizer">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Patient Details Table (shown for patient-directed donations) -->
                            <div class="screening-patient-section" id="patientDetailsSection" style="display: none;">
                                <div class="screening-detail-card">
                                    <h6 class="screening-detail-title">Patient Information</h6>
                                    <div class="screening-patient-table-container">
                                        <table class="table table-bordered screening-patient-table">
                                            <thead>
                                                <tr class="table-danger">
                                                    <th>Patient Name</th>
                                                    <th>Hospital</th>
                                                    <th>Blood Type</th>
                                                    <th>WB/Component</th>
                                                    <th>No. of units</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>
                                                        <input type="text" name="patient-name" class="form-control form-control-sm" placeholder="Enter patient name">
                                                    </td>
                                                    <td>
                                                        <input type="text" name="hospital" class="form-control form-control-sm" placeholder="Enter hospital">
                                                    </td>
                                                    <td>
                                                        <select name="blood-type-patient" class="form-control form-control-sm">
                                                            <option value="" disabled selected>Select Blood Type</option>
                                                            <option value="A+">A+</option>
                                                            <option value="A-">A-</option>
                                                            <option value="B+">B+</option>
                                                            <option value="B-">B-</option>
                                                            <option value="O+">O+</option>
                                                            <option value="O-">O-</option>
                                                            <option value="AB+">AB+</option>
                                                            <option value="AB-">AB-</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="wb-component" class="form-control form-control-sm" placeholder="Enter component">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="no-units" class="form-control form-control-sm" placeholder="0" min="0">
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- No Additional Details Message (shown for walk-in/replacement) -->
                            <div class="screening-no-details" id="noAdditionalDetails">
                                <div class="screening-detail-card text-center">
                                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                    <h6>No Additional Details Required</h6>
                                    <p class="text-muted mb-0">This donation type doesn't require additional information.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Donation History -->
                        <div class="screening-step-content" data-step="4">
                            <div class="screening-step-title">
                                <h6><i class="fas fa-history me-2 text-danger"></i>Donation History</h6>
                                <p class="text-muted mb-4">Previous donation information (Donor's Opinion)</p>
                            </div>
                            
                            <div class="screening-history-question">
                                <label class="screening-label mb-3">Has the donor donated blood before?</label>
                                <div class="screening-radio-group">
                                    <label class="screening-radio-option">
                                        <input type="radio" name="history" value="yes" required>
                                        <span class="screening-radio-custom"></span>
                                        <span class="screening-option-text">Yes</span>
                                    </label>
                                    <label class="screening-radio-option">
                                        <input type="radio" name="history" value="no" required>
                                        <span class="screening-radio-custom"></span>
                                        <span class="screening-option-text">No</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="screening-history-details" id="historyDetails" style="display: none;">
                                <div class="screening-detail-card">
                                    <h6 class="screening-detail-title">Donation History Details</h6>
                                    <div class="screening-history-table">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr class="table-danger">
                                                    <th></th>
                                                    <th>Red Cross</th>
                                                    <th>Hospital</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <th class="table-light">No. of times</th>
                                                    <td><input type="number" name="red-cross" min="0" value="0" class="form-control form-control-sm"></td>
                                                    <td><input type="number" name="hospital-history" min="0" value="0" class="form-control form-control-sm"></td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light">Date of last donation</th>
                                                    <td><input type="date" name="last-rc-donation-date" class="form-control form-control-sm"></td>
                                                    <td><input type="date" name="last-hosp-donation-date" class="form-control form-control-sm"></td>
                                                </tr>
                                                <tr>
                                                    <th class="table-light">Place of last donation</th>
                                                    <td><input type="text" name="last-rc-donation-place" class="form-control form-control-sm" placeholder="Enter location"></td>
                                                    <td><input type="text" name="last-hosp-donation-place" class="form-control form-control-sm" placeholder="Enter location"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 5: Review -->
                        <div class="screening-step-content" data-step="5">
                            <div class="screening-step-title">
                                <h6><i class="fas fa-check-double me-2 text-danger"></i>Review & Submit</h6>
                                <p class="text-muted mb-4">Please review all information before submission</p>
                            </div>
                            
                            <div class="screening-review-section">
                                <div class="screening-review-card">
                                    <h6 class="screening-review-title">Screening Summary</h6>
                                    <div class="screening-review-content" id="reviewContent">
                                        <!-- Content will be populated by JavaScript -->
                                    </div>
                                </div>
                                
                                <div class="screening-interviewer-info">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-6">
                                            <label class="screening-label">Interviewer</label>
                                            <input type="text" name="interviewer" value="<?php echo htmlspecialchars($interviewer_name ?? 'Interviewer'); ?>" class="screening-input" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="screening-label">Office</label>
                                            <input type="text" value="PRC Office" class="screening-input" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="screening-label">Date</label>
                                            <input type="text" value="<?php echo date('m/d/Y'); ?>" class="screening-input" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer screening-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="screeningCancelButton">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="screeningPrevButton" style="display: none;">
                        <i class="fas fa-arrow-left me-2"></i>Previous
                    </button>
                    <button type="button" class="btn btn-danger" id="screeningNextButton">
                        <i class="fas fa-arrow-right me-2"></i>Next
                    </button>
                    <button type="button" class="btn btn-success" id="screeningSubmitButton" style="display: none;">
                        <i class="fas fa-check me-2"></i>Submit Screening
                    </button>
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
            const approveButton = document.getElementById('Approve');
            
            // Initialize current donor data at the top scope
            let currentDonorData = null;
            
            // Direct debug of button existence
            console.log("Approve button found:", approveButton);
            
            // Store the original table rows for reset
            const originalRows = Array.from(donorTableBody.getElementsByTagName('tr'));
            
            // Update placeholder based on selected category
            if (searchCategory) {
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
            }

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
                messageRow.innerHTML = `<td colspan="6" class="text-center py-3">
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
            if (searchCategory) {
                searchCategory.addEventListener('change', debouncedSearch);
            }

            function showError(message) {
                alert(message);
                console.error("ERROR: " + message);
            }

            // Function to populate modal fields with donor data
            function populateModalFields(donorData) {
                if (!donorData) return;
                
                // Helper function to safely set field values
                function setFieldValue(name, value) {
                    const field = document.querySelector(`[name="${name}"]`);
                    if (field) {
                        if (field.tagName === 'DIV' || field.tagName === 'SPAN') {
                            field.textContent = value || '-';
                        } else {
                            field.value = value || '';
                        }
                    }
                }
                
                // Populate ID cards
                setFieldValue('prc_donor_number', donorData.prc_donor_number);
                setFieldValue('doh_nnbnets_barcode', donorData.doh_nnbnets_barcode);
                
                // Populate personal information
                setFieldValue('surname', donorData.surname);
                setFieldValue('first_name', donorData.first_name);
                setFieldValue('middle_name', donorData.middle_name);
                setFieldValue('age', donorData.age);
                setFieldValue('sex', donorData.sex ? donorData.sex.charAt(0).toUpperCase() + donorData.sex.slice(1) : '-');
                setFieldValue('civil_status', donorData.civil_status ? donorData.civil_status.charAt(0).toUpperCase() + donorData.civil_status.slice(1) : '-');
                
                // Format and set birthdate
                if (donorData.birthdate) {
                    const date = new Date(donorData.birthdate);
                    const formattedDate = date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                    setFieldValue('birthdate', formattedDate);
                }
                
                // Populate address information
                setFieldValue('permanent_address', donorData.permanent_address);
                setFieldValue('office_address', donorData.office_address);
                
                // Populate background information
                setFieldValue('nationality', donorData.nationality);
                setFieldValue('religion', donorData.religion);
                setFieldValue('education', donorData.education);
                setFieldValue('occupation', donorData.occupation);
                
                // Populate contact information
                setFieldValue('mobile', donorData.mobile);
                setFieldValue('telephone', donorData.telephone);
                setFieldValue('email', donorData.email);
                
                // Populate identification numbers
                setFieldValue('id_school', donorData.id_school);
                setFieldValue('id_company', donorData.id_company);
                setFieldValue('id_prc', donorData.id_prc);
                setFieldValue('id_drivers', donorData.id_drivers);
                setFieldValue('id_sss_gsis_bir', donorData.id_sss_gsis_bir);
                setFieldValue('id_others', donorData.id_others);
            }

            // Handle view button click to populate modal
            document.querySelectorAll('.view-donor-btn').forEach(button => {
                button.addEventListener('click', function() {
                    try {
                        const donorDataStr = this.getAttribute('data-donor');
                        const donorId = this.getAttribute('data-donor-id');
                        
                        console.log("View button clicked, data attribute value:", donorDataStr);
                        console.log("Donor ID:", donorId);
                        
                        // Check if we have donor data
                        if (!donorDataStr || donorDataStr === 'null' || donorDataStr === '{}') {
                            showError('No donor data available. Please try refreshing the page.');
                            return;
                        }
                        
                        // Try to parse the donor data
                        currentDonorData = JSON.parse(donorDataStr);
                        console.log("Parsed donor data:", currentDonorData);
                        
                        // Check for donor_id
                        if (!currentDonorData || !currentDonorData.donor_id) {
                            // Fallback to using the donor_id from the attribute
                            if (donorId) {
                                currentDonorData = { donor_id: donorId };
                                console.log("Using fallback donor_id:", donorId);
                            } else {
                            showError('Missing donor_id in parsed data. This will cause issues with approval.');
                                return;
                            }
                        }
                        
                        // Populate modal fields with donor data
                        populateModalFields(currentDonorData);
                        
                        // Show the modal
                        const modal = new bootstrap.Modal(document.getElementById('donorDetailsModal'));
                        modal.show();
                        
                    } catch (error) {
                        console.error('Error details:', error);
                        showError('Error parsing donor data: ' + error.message);
                    }
                });
            });

            // Handle edit button click
            document.querySelectorAll('.edit-donor-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const donorId = this.getAttribute('data-donor-id');
                    console.log('Edit button clicked for donor ID:', donorId);
                    // Add your edit functionality here
                    alert('Edit functionality will be implemented for donor ID: ' + donorId);
                });
            });

            // Approve button click handler
            if (approveButton) {
                approveButton.addEventListener('click', function() {
                    console.log("Approve button clicked");
                    
                    if (!currentDonorData) {
                        showError('Error: No donor selected');
                        console.error("No donor data available. Cannot proceed.");
                        return;
                    }
                    
                    console.log("Current donor data:", currentDonorData);
                    
                    // Get the donor_id from the data
                    const donorId = currentDonorData.donor_id;
                    if (!donorId) {
                        showError('Error: Could not process approval - missing donor ID');
                        console.error("Missing donor_id in data");
                        return;
                    }
                    
                    console.log("Opening screening modal for donor ID:", donorId);
                    
                    // Close the donor details modal first
                    const donorModal = bootstrap.Modal.getInstance(document.getElementById('donorDetailsModal'));
                    if (donorModal) {
                        donorModal.hide();
                    }
                    
                    // Wait a moment for the modal to close, then open the screening modal
                    setTimeout(function() {
                        // Open the screening form modal
                        openScreeningModal(currentDonorData);
                    }, 300);
                });
            } else {
                console.error("ERROR: Approve button not found in the DOM!");
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
</body>
</html>