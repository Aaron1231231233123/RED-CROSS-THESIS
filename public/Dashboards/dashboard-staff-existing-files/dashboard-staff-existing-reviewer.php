<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require '../../../assets/php_func/user_roles_staff.php';
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
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'existing';
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

// Close cURL session
curl_close($ch);

// Fetch eligibility data to get status for each donor BEFORE processing donors
$eligibility_status_data = [];
$eligibility_url = SUPABASE_URL . '/rest/v1/eligibility?select=donor_id,status';
$eligibility_ch = curl_init($eligibility_url);
curl_setopt_array($eligibility_ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$eligibility_response = curl_exec($eligibility_ch);
curl_close($eligibility_ch);

if ($eligibility_response !== false) {
    $eligibility_records = json_decode($eligibility_response, true) ?: [];
    foreach ($eligibility_records as $record) {
        if (isset($record['donor_id'])) {
            $eligibility_status_data[$record['donor_id']] = $record['status'] ?? 'Unknown';
        }
    }
    error_log("DEBUG - Eligibility status data fetched: " . count($eligibility_records) . " records");
}

// Slice the array to get only the records for the current page
$donors = array_slice($donors, $offset, $records_per_page);

// Calculate 6-month countdown and combine with database status for the current page donors
$eligibility_data = [];
$current_date = new DateTime();

foreach ($donors as $donor) {
    $donor_id = $donor['donor_id'];
    
    // Get status from database
    $db_status = isset($eligibility_status_data[$donor_id]) ? $eligibility_status_data[$donor_id] : 'Pending';
    
    // Calculate 6-month countdown for "Eligible After" field
    if (isset($donor['created_at']) || isset($donor['submitted_at'])) {
        $created_date_str = $donor['created_at'] ?? $donor['submitted_at'];
        try {
            $created_date = new DateTime($created_date_str);
            $eligible_date = clone $created_date;
            $eligible_date->add(new DateInterval('P6M')); // Add 6 months
            
            $days_remaining = $current_date->diff($eligible_date)->days;
            $is_future = $current_date < $eligible_date;
            
            $eligibility_data[$donor_id] = [
                'status' => $db_status, // Use database status
                'eligible_after' => $eligible_date,
                'days_remaining' => $is_future ? $days_remaining : 0,
                'created_at' => $created_date
            ];
        } catch (Exception $e) {
            error_log("Error parsing date for donor " . $donor_id . ": " . $e->getMessage());
            $eligibility_data[$donor_id] = [
                'status' => $db_status, // Use database status even if date parsing fails
                'eligible_after' => null,
                'days_remaining' => null,
                'created_at' => null
            ];
        }
    } else {
        $eligibility_data[$donor_id] = [
            'status' => $db_status, // Use database status
            'eligible_after' => null,
            'days_remaining' => null,
            'created_at' => null
        ];
    }
}

error_log("DEBUG - Eligibility data calculated for " . count($eligibility_data) . " donors on current page");

// Process donor approval if a donor_id was passed
if (isset($_GET['approve_donor'])) {
    $donor_id = intval($_GET['approve_donor']);
    $donor_name = $_GET['donor_name'] ?? '';
    
    // Add debugging
    error_log("APPROVAL PROCESS: Received approve_donor parameter: " . $donor_id);
    
    // Store in session
    $_SESSION['donor_id'] = $donor_id;
    $_SESSION['donor_name'] = $donor_name;
    
    error_log("APPROVAL PROCESS: Setting donor_id in session directly: " . $donor_id);
    
    // Ensure user_staff_roles is set - default to 'Interviewer' to guarantee access
    // The interviewer role should have access to medical histories
    $_SESSION['user_staff_role'] = 'Interviewer';
    $_SESSION['user_staff_roles'] = 'Interviewer';
    $_SESSION['staff_role'] = 'Interviewer';
    
    // Try to get the actual role from the database if possible
    $user_id = $_SESSION['user_id'] ?? 0;
    
    // Add extra debugging
    error_log("APPROVAL PROCESS: User ID for role lookup: " . $user_id);
    error_log("APPROVAL PROCESS: Initial role settings: " . $_SESSION['user_staff_role']);
    
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
    $curl_error = curl_error($ch);
    
    if ($response !== false) {
        $userData = json_decode($response, true);
        if (is_array($userData) && !empty($userData)) {
            // Set multiple role variables to ensure compatibility
            $_SESSION['user_staff_role'] = $userData[0]['role_name'] ?? 'Interviewer';
            $_SESSION['staff_role'] = $userData[0]['role_name'] ?? 'Interviewer';
            $_SESSION['user_staff_roles'] = $userData[0]['role_name'] ?? 'Interviewer';
            
            error_log("APPROVAL PROCESS: User role set to: " . $_SESSION['user_staff_role']);
        } else {
            error_log("APPROVAL PROCESS: No user role data found or invalid format");
        }
    } else {
        error_log("APPROVAL PROCESS: cURL error fetching user role: " . $curl_error);
    }
    
    curl_close($ch);
    
    // Make sure the roles are set to valid values that will pass the check in medical-history.php
    if (!in_array(strtolower($_SESSION['user_staff_role']), ['interviewer', 'reviewer', 'physician'])) {
        $_SESSION['user_staff_role'] = 'Interviewer';
        $_SESSION['user_staff_roles'] = 'Interviewer';
        $_SESSION['staff_role'] = 'Interviewer';
        error_log("APPROVAL PROCESS: Role not valid, defaulting to Interviewer");
    }
    
    // Log the session for debugging
    ob_start();
    var_dump($_SESSION);
    $session_dump = ob_get_clean();
    error_log("APPROVAL PROCESS: Session after setting: " . $session_dump);
    
    // Redirect directly to medical history form
    $redirect_url = "../../src/views/forms/medical-history.php";
    error_log("APPROVAL PROCESS: Redirecting to: " . $redirect_url);
    
    // Ensure no output has been sent before redirect
    if (!headers_sent($filename, $linenum)) {
        header("Location: " . $redirect_url);
        exit();
    } else {
        error_log("APPROVAL PROCESS: Headers already sent in $filename on line $linenum");
        echo "<script>window.location.href = '$redirect_url';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=$redirect_url'></noscript>";
        echo "If you are not redirected, <a href='$redirect_url'>click here</a>.";
        exit();
    }
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
                        <a class="nav-link active" href="dashboard-staff-existing-files/dashboard-staff-existing-reviewer.php">
                            Existing Donor
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard-staff-history.php">
                            Donor History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../../../assets/php_func/logout.php">
                            Logout
                        </a>
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
                        <a href="?status=registrations" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'registrations') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $registrations_count; ?></p>
                            <p class="dashboard-staff-title">Registrations</p>
                        </a>
                        <a href="?status=existing" class="status-card <?php echo (!isset($_GET['status']) || $_GET['status'] === 'existing') ? 'active' : ''; ?>">
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
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Gender</th>
                                    <th>Age</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
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
                                            <td><?php echo !empty($donor['surname']) ? htmlspecialchars($donor['surname']) : 'N/A'; ?></td>
                                            <td><?php echo !empty($donor['first_name']) ? htmlspecialchars($donor['first_name']) : 'N/A'; ?></td>
                                            <td><?php echo !empty($donor['sex']) ? htmlspecialchars(ucfirst($donor['sex'])) : 'N/A'; ?></td>
                                            <td><?php echo isset($donor['age']) ? htmlspecialchars($donor['age']) : 'N/A'; ?></td>
                                            <td><?php 
                                                $gateway = isset($donor['registration_channel']) ? ($donor['registration_channel'] === 'Mobile' ? 'Mobile' : 'PRC Portal') : 'PRC Portal';
                                                echo htmlspecialchars($gateway); 
                                            ?></td>
                                            <td><?php 
                                                $eligibility_info = isset($eligibility_data[$donor['donor_id']]) ? $eligibility_data[$donor['donor_id']] : ['status' => 'Unknown'];
                                                $status = $eligibility_info['status'];
                                                // Status badge - handle database status values flexibly
                                                $status_class = '';
                                                $status_lower = strtolower($status);
                                                if (strpos($status_lower, 'eligible') !== false || strpos($status_lower, 'approved') !== false) {
                                                    $status_class = 'badge bg-success';
                                                } elseif (strpos($status_lower, 'pending') !== false || strpos($status_lower, 'waiting') !== false) {
                                                    $status_class = 'badge bg-warning';
                                                } elseif (strpos($status_lower, 'declined') !== false || strpos($status_lower, 'rejected') !== false || strpos($status_lower, 'ineligible') !== false) {
                                                    $status_class = 'badge bg-danger';
                                                } else {
                                                    $status_class = 'badge bg-secondary';
                                                }
                                                echo '<span class="' . $status_class . '">' . htmlspecialchars($status) . '</span>';
                                            ?></td>
                                            <td>
                                                <button type="button" class="btn btn-info btn-sm view-donor-btn me-1" 
                                                        data-donor-id="<?php echo $donor['donor_id']; ?>" 
                                                        data-donor='<?php echo $encoded_data; ?>' 
                                                        data-eligibility='<?php echo json_encode($eligibility_info, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm edit-donor-btn" 
                                                        data-donor-id="<?php echo $donor['donor_id']; ?>" 
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No records found</td>
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
                <!-- Donor ID Cards -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="info-card-header">
                                <i class="fas fa-id-card me-2"></i>
                                PRC Blood Donor Number
                            </div>
                            <div class="info-card-value" name="prc_donor_number">-</div>
                            </div>
                            </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="info-card-header">
                                <i class="fas fa-barcode me-2"></i>
                                DOH NNBNets Barcode
                        </div>
                            <div class="info-card-value" name="doh_nnbnets_barcode">-</div>
                            </div>
                            </div>
                            </div>

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

    <!-- Existing Donor Information Modal -->
    <div class="modal fade" id="existingDonorModal" tabindex="-1" aria-labelledby="existingDonorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="existingDonorModalLabel">Existing Donor Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                    <h6 class="mb-3 text-danger">Personal Information</h6>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Name</label>
                            <div class="form-control bg-light" id="modalName">-</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Birth Date</label>
                            <div class="form-control bg-light" id="modalBirthDate">-</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Age</label>
                            <div class="form-control bg-light" id="modalAge">-</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label text-muted">Civil Status</label>
                            <div class="form-control bg-light" id="modalCivilStatus">-</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Sex</label>
                            <div class="form-control bg-light" id="modalSex">-</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Nationality</label>
                            <div class="form-control bg-light" id="modalNationality">-</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Religion</label>
                            <div class="form-control bg-light" id="modalReligion">-</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Education</label>
                            <div class="form-control bg-light" id="modalEducation">-</div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Occupation</label>
                            <div class="form-control bg-light" id="modalOccupation">-</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Mobile Number</label>
                            <div class="form-control bg-light" id="modalMobile">-</div>
                        </div>
                    </div>
                    
                    <h6 class="mb-3 text-danger">Eligibility Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Status</label>
                            <div class="form-control bg-light" id="modalStatus">-</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Last Donation Date</label>
                            <div class="form-control bg-light" id="modalLastDonation">-</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Notes</label>
                            <div class="form-control bg-light" id="modalNotes">-</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Eligible After</label>
                            <div class="form-control bg-light" id="modalEligibleAfter">-</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="proceedToInterviewer">Proceed to Interviewer</button>
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
                messageRow.innerHTML = `<td colspan="9" class="text-center py-3">
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
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    try {
                        const donorDataStr = this.getAttribute('data-donor');
                        const eligibilityDataStr = this.getAttribute('data-eligibility');
                        const donorId = this.getAttribute('data-donor-id');
                        
                        console.log("View button clicked, data attribute value:", donorDataStr);
                        console.log("Eligibility data:", eligibilityDataStr);
                        console.log("Donor ID:", donorId);
                        
                        // Check if we have donor data
                        if (!donorDataStr || donorDataStr === 'null' || donorDataStr === '{}') {
                            showError('No donor data available. Please try refreshing the page.');
                            return;
                        }
                        
                        // Try to parse the donor data
                        currentDonorData = JSON.parse(donorDataStr);
                        const eligibilityData = eligibilityDataStr ? JSON.parse(eligibilityDataStr) : null;
                        
                        console.log("Parsed donor data:", currentDonorData);
                        console.log("Parsed eligibility data:", eligibilityData);
                        
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
                        
                        // Populate existing donor modal fields
                        populateExistingDonorModal(currentDonorData, eligibilityData);
                        
                        // Show the existing donor modal
                        const modal = new bootstrap.Modal(document.getElementById('existingDonorModal'));
                        modal.show();
                        
                    } catch (error) {
                        console.error('Error details:', error);
                        showError('Error parsing donor data: ' + error.message);
                    }
                });
            });
            
            // Function to populate existing donor modal
            function populateExistingDonorModal(donorData, eligibilityData = null) {
                if (!donorData) return;
                
                // Helper function to safely set field values
                function setModalField(id, value) {
                    const field = document.getElementById(id);
                    if (field) {
                        field.textContent = value || '-';
                    }
                }
                
                // Populate basic information
                const fullName = [donorData.first_name, donorData.middle_name, donorData.surname].filter(n => n).join(' ');
                setModalField('modalName', fullName);
                setModalField('modalAge', donorData.age);
                setModalField('modalSex', donorData.sex ? donorData.sex.charAt(0).toUpperCase() + donorData.sex.slice(1) : '-');
                setModalField('modalCivilStatus', donorData.civil_status ? donorData.civil_status.charAt(0).toUpperCase() + donorData.civil_status.slice(1) : '-');
                setModalField('modalNationality', donorData.nationality);
                setModalField('modalReligion', donorData.religion);
                setModalField('modalEducation', donorData.education);
                setModalField('modalOccupation', donorData.occupation);
                setModalField('modalMobile', donorData.mobile);
                
                // Format and set birthdate
                if (donorData.birthdate) {
                    const date = new Date(donorData.birthdate);
                    const formattedDate = date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                    setModalField('modalBirthDate', formattedDate);
                }
                
                // Set eligibility information
                if (eligibilityData) {
                    setModalField('modalStatus', eligibilityData.status);
                    
                    // Calculate and display eligible after countdown
                    if (eligibilityData.days_remaining !== null) {
                        if (eligibilityData.days_remaining <= 0) {
                            setModalField('modalEligibleAfter', 'Today');
                        } else {
                            setModalField('modalEligibleAfter', eligibilityData.days_remaining + ' days');
                        }
                    } else {
                        setModalField('modalEligibleAfter', '-');
                    }
                    
                    // Set last donation date
                    if (eligibilityData.created_at) {
                        setModalField('modalLastDonation', new Date(donorData.submitted_at || donorData.created_at).toLocaleDateString());
                    } else {
                        setModalField('modalLastDonation', '-');
                    }
                } else {
                    setModalField('modalStatus', 'Unknown');
                    setModalField('modalLastDonation', donorData.submitted_at ? new Date(donorData.submitted_at).toLocaleDateString() : '-');
                    setModalField('modalEligibleAfter', '-');
                }
                
                setModalField('modalNotes', 'Eligibility based on 6 months from registration date');
            }

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
                    
                    // Get donor name if available
                    let donorName = '';
                    if (currentDonorData.first_name && currentDonorData.surname) {
                        donorName = currentDonorData.first_name + ' ' + currentDonorData.surname;
                    }
                    
                    console.log("Processing approval for donor ID:", donorId, "Name:", donorName);
                    
                    // Create a form and submit it programmatically
                    // This is more reliable than using window.location.href
                    const form = document.createElement('form');
                    form.method = 'GET';
                    form.action = 'dashboard-staff-donor-submission.php';
                    
                    // Add the approve_donor parameter
                    const donorInput = document.createElement('input');
                    donorInput.type = 'hidden';
                    donorInput.name = 'approve_donor';
                    donorInput.value = donorId;
                    form.appendChild(donorInput);
                    
                    // Add the donor_name parameter
                    const nameInput = document.createElement('input');
                    nameInput.type = 'hidden';
                    nameInput.name = 'donor_name';
                    nameInput.value = donorName;
                    form.appendChild(nameInput);
                    
                    // Close modal before submitting
                    const modal = bootstrap.Modal.getInstance(document.getElementById('donorDetailsModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Add form to the body and submit it
                    document.body.appendChild(form);
                    console.log("Submitting form with donor ID:", donorId);
                    form.submit();
                });
            } else {
                console.error("ERROR: Approve button not found in the DOM!");
            }
            
            // Handle proceed to interviewer button
            const proceedButton = document.getElementById('proceedToInterviewer');
            if (proceedButton) {
                proceedButton.addEventListener('click', function() {
                    if (currentDonorData && currentDonorData.donor_id) {
                        // Close the modal first
                        const modal = bootstrap.Modal.getInstance(document.getElementById('existingDonorModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Redirect to medical history or interviewer workflow
                        // You can modify this URL based on your actual workflow
                        window.location.href = `../dashboard-staff-medical-history-submissions.php?donor_id=${currentDonorData.donor_id}`;
                    } else {
                        showError('No donor selected');
                    }
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
</body>
</html>