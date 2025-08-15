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


// STEP 1: Get all blood collection records to identify physical exams that already have blood collection
$blood_collection_url = SUPABASE_URL . '/rest/v1/blood_collection?select=physical_exam_id,is_successful,donor_reaction,management_done,status,blood_bag_type,amount_taken,created_at';
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
$approved_collections = [];
$declined_collections = [];
$blood_collection_data = []; // Store full blood collection data

foreach ($blood_collections as $collection) {
    if (isset($collection['physical_exam_id']) && !empty($collection['physical_exam_id'])) {
        // Normalize the physical_exam_id to string format for consistent comparison
        $exam_id = (string)$collection['physical_exam_id'];
        $collected_physical_exam_ids[] = $exam_id;
        
        // Store full collection data for results display
        $blood_collection_data[$exam_id] = $collection;
        
        // Track approved and declined collections based on is_successful
        if (isset($collection['is_successful'])) {
            if ($collection['is_successful'] === true) {
                $approved_collections[] = $exam_id;
            } else {
                $declined_collections[] = $exam_id;
            }
        }
        
    }
}

// STEP 2: Separate queries to avoid JOIN issues
// Query 1: Get physical examinations
$physical_exam_url = SUPABASE_URL . '/rest/v1/physical_examination?remarks=eq.Accepted&select=physical_exam_id,donor_id,remarks,blood_bag_type,created_at&order=created_at.desc';

$ch = curl_init($physical_exam_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    error_log("Error fetching physical examinations. HTTP code: " . $http_code);
    $physical_exam_records = [];
} else {
    $physical_exam_records = json_decode($response, true) ?: [];
}

// Query 2: Get all donor_form records
$donor_form_url = SUPABASE_URL . '/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,age';

$ch2 = curl_init($donor_form_url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
$donor_response = curl_exec($ch2);
$donor_http_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($donor_http_code !== 200) {
    error_log("Error fetching donor forms. HTTP code: " . $donor_http_code);
    $donor_records = [];
} else {
    $donor_records = json_decode($donor_response, true) ?: [];
}

// Create donor lookup array
$donor_lookup = [];
foreach ($donor_records as $donor) {
    $donor_id = $donor['donor_id'];
    $donor_lookup[$donor_id] = $donor;
}

// Manually combine physical_examination with correct donor_form data and deduplicate by donor
$all_physical_exams = [];
$donor_latest_exams = []; // Track the latest exam per donor

foreach ($physical_exam_records as $exam) {
    $donor_id = $exam['donor_id'];
    
    // Skip if donor_id is empty or null
    if (empty($donor_id)) {
        continue;
    }
    
    if (isset($donor_lookup[$donor_id])) {
        $exam['donor_form'] = $donor_lookup[$donor_id];
    } else {
        $exam['donor_form'] = null;
    }
    
    // Check if this is the latest exam for this donor
    if (!isset($donor_latest_exams[$donor_id]) || 
        strtotime($exam['created_at']) > strtotime($donor_latest_exams[$donor_id]['created_at'])) {
        $donor_latest_exams[$donor_id] = $exam;
    }   
}



// Use only the latest exam per donor
$physical_exams = array_values($donor_latest_exams);

// Filter out physical exams that already have blood collection
$available_exams = [];
foreach ($physical_exams as $exam) {
    if (!in_array($exam['physical_exam_id'], $collected_physical_exam_ids)) {
        $available_exams[] = $exam;
    }
}



// Calculate incoming count (available exams that haven't been collected yet)
$incoming_count = count($available_exams);
$approved_count = count($approved_collections);

// Calculate today's summary count (blood collections submitted today)
$today = date('Y-m-d');
$today_count = 0;
foreach ($blood_collections as $collection) {
    if (isset($collection['created_at'])) {
        $collection_date = date('Y-m-d', strtotime($collection['created_at']));
        if ($collection_date === $today) {
            $today_count++;
        }
    }
}

// Handle status filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'incoming';

// Initialize filtered screenings based on status
switch ($status_filter) {
    case 'active':
        // Get approved blood collections with physical_exam_id list
        $approved_collections_url = SUPABASE_URL . '/rest/v1/blood_collection?is_successful=eq.true&select=physical_exam_id,created_at&order=created_at.desc';
        $ch = curl_init($approved_collections_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $approved_collections = json_decode($response, true) ?: [];
        
        // Get unique physical_exam_ids that were successfully collected
        $approved_exam_ids = [];
        foreach ($approved_collections as $collection) {
            if (!empty($collection['physical_exam_id'])) {
                $approved_exam_ids[] = $collection['physical_exam_id'];
            }
        }
        
        // Filter from the main physical exams data to maintain consistency
        $display_exams = [];
        
        foreach ($physical_exams as $exam) {
            // Only include exams that have successful blood collections
            if (in_array($exam['physical_exam_id'], $approved_exam_ids)) {
                $display_exams[] = $exam;
            }
        }
        break;
        
    case 'today':
        // Get today's blood collections with physical_exam_id list
        $today_collections_url = SUPABASE_URL . '/rest/v1/blood_collection?created_at=gte.' . $today . 'T00:00:00&created_at=lt.' . date('Y-m-d', strtotime($today . ' +1 day')) . 'T00:00:00&select=physical_exam_id,created_at&order=created_at.desc';
        $ch = curl_init($today_collections_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $today_collections = json_decode($response, true) ?: [];
        
        // Get unique physical_exam_ids collected today
        $today_exam_ids = [];
        foreach ($today_collections as $collection) {
            if (!empty($collection['physical_exam_id'])) {
                $today_exam_ids[] = $collection['physical_exam_id'];
            }
        }
        
        // Filter from the main physical exams data to maintain consistency
        $display_exams = [];
        
        foreach ($physical_exams as $exam) {
            // Only include exams that have blood collections today
            if (in_array($exam['physical_exam_id'], $today_exam_ids)) {
                $display_exams[] = $exam;
            }
        }
        break;
        
    case 'incoming':
    default:
        // Use available exams directly since deduplication is already done at source
        $display_exams = $available_exams;
        break;
}

// Sort display exams by created_at (FIFO: oldest first)
usort($display_exams, function($a, $b) {
    $a_time = isset($a['updated_at']) ? strtotime($a['updated_at']) : (isset($a['created_at']) ? strtotime($a['created_at']) : 0);
    $b_time = isset($b['updated_at']) ? strtotime($b['updated_at']) : (isset($b['created_at']) ? strtotime($b['created_at']) : 0);
    return $a_time <=> $b_time;
});

// Prepare pagination
$total_records = count($display_exams);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Calculate the offset for this page
$offset = ($current_page - 1) * $records_per_page;

// Slice the array to get only the records for the current page
$display_exams = array_slice($display_exams, $offset, $records_per_page);

// Calculate execution time
$end_time = microtime(true);
$execution_time = ($end_time - $start_time);



// Calculate age for each physical examination
foreach ($display_exams as $index => $exam) {
    if (isset($exam['donor_form'])) {
        // Calculate age if not present but birthdate is available
        if (empty($exam['donor_form']['age']) && !empty($exam['donor_form']['birthdate'])) {
            $birthDate = new DateTime($exam['donor_form']['birthdate']);
            $today = new DateTime();
            $display_exams[$index]['donor_form']['age'] = $birthDate->diff($today)->y;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="../../assets/js/blood_collection_modal.js"></script>
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
            table-layout: fixed;
        }
        
        .dashboard-staff-tables th:nth-child(8),
        .dashboard-staff-tables td:nth-child(8) {
            width: 120px;
        }
        
        .dashboard-staff-tables th:nth-child(9),
        .dashboard-staff-tables td:nth-child(9) {
            width: 120px;
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
        
        .badge.bg-success {
            background-color: #28a745 !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            font-weight: 600;
            border-radius: 4px;
        }
        
        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            font-weight: 600;
            border-radius: 4px;
        }
        
        .badge.bg-secondary {
            background-color: #6c757d !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            font-weight: 600;
            border-radius: 4px;
        }
        
        .badge.bg-danger {
            background-color: #dc3545 !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
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

        /* Enhanced badge styling for blood collection results */
        .badge.bg-success {
            background-color: #28a745 !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-danger {
            background-color: #dc3545 !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-secondary {
            background-color: #6c757d !important;
            color: white !important;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 600;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Blood Collection Modal Styles */
        .blood-collection-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .blood-collection-modal.show {
            opacity: 1;
        }

        .blood-modal-content {
            background: white;
            border-radius: 15px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .blood-collection-modal.show .blood-modal-content {
            transform: translateY(0);
        }

        .blood-modal-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .blood-modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .blood-close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }

        .blood-close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Progress Indicator - Fixed Containment */
        .blood-progress-container {
            background: white !important;
            padding: 20px !important;
            border-bottom: 1px solid #e9ecef !important;
            position: relative !important;
            display: block !important;
            visibility: visible !important;
        }

        .blood-progress-steps {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            position: relative !important;
            z-index: 2 !important;
            visibility: visible !important;
            max-width: 100% !important;
            margin: 0 auto !important;
        }

        .blood-step {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }

        .blood-step-number {
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            background: #e9ecef !important;
            color: #6c757d !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: bold !important;
            font-size: 16px !important;
            transition: all 0.3s ease !important;
            margin-bottom: 8px !important;
            border: none !important;
            box-sizing: border-box !important;
        }

        .blood-step-label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
        }

        .blood-step.active .blood-step-number,
        .blood-step.completed .blood-step-number {
            background: #b22222 !important;
            color: white !important;
        }

        .blood-step.active .blood-step-label,
        .blood-step.completed .blood-step-label {
            color: #b22222 !important;
            font-weight: 600 !important;
        }

        .blood-progress-line {
            position: absolute;
            top: 40%;
            left: 20px;
            right: 20px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: 1;
        }

        .blood-progress-fill {
            height: 100%;
            background: #b22222;
            width: 0%;
            transition: width 0.5s ease;
        }

        /* Modal Form */
        .blood-modal-form {
            padding: 30px;
        }

        .blood-step-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .blood-step-content.active {
            display: block;
        }

        .blood-step-content h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .blood-step-content p.text-muted {
            margin-bottom: 25px;
        }

        /* Collection Overview Styles */
        .collection-overview {
            padding: 15px 25px;
        }

        .donor-section {
            margin-bottom: 20px;
        }

        .donor-info-row {
            padding: 25px 0;
            border-bottom: 2px solid #f1f3f4;
        }

        .donor-main-info {
            width: 100%;
        }

        .donor-name {
            color: #721c24;
            font-weight: 700;
            margin: 0 0 8px 0;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .donor-metadata {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .collection-date {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }

        .unit-serial-info {
            color: #721c24;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            font-family: 'Courier New', monospace;
        }



        .ready-indicator {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px 25px;
            background: #f8fff9;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }

        .ready-icon {
            color: #28a745;
            font-size: 1.8rem;
        }

        .ready-text {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .ready-title {
            color: #28a745;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .ready-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Modern Blood Collection Form Styles */
        .modern-form-container {
            padding: 25px;
            background: white;
            border-radius: 12px;
        }

        .form-group-modern {
            margin-bottom: 30px;
        }

        .form-label-modern {
            display: flex;
            align-items: center;
            font-weight: 600;
            color: #721c24;
            margin-bottom: 12px;
            font-size: 1rem;
        }

        .form-label-modern i {
            color: #b22222;
        }

        .form-row-modern {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .form-input-modern {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input-modern:focus {
            border-color: #b22222;
            box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.1);
            outline: none;
        }

        .readonly-input {
            background-color: #f8f9fa !important;
            color: #6c757d;
            cursor: not-allowed;
        }

        .form-textarea-modern {
            width: 100%;
            min-height: 100px;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            resize: vertical;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-textarea-modern:focus {
            border-color: #b22222;
            box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.1);
            outline: none;
        }

        /* Blood Bag Grid */
        .blood-bag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .bag-brand-section {
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            padding: 20px;
            background: #fafafa;
        }

        .brand-title {
            color: #721c24;
            font-weight: 700;
            text-align: center;
            margin-bottom: 15px;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .bag-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .bag-option {
            display: block;
            cursor: pointer;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            background: white;
            transition: all 0.3s ease;
            position: relative;
        }

        .bag-option:hover {
            border-color: #b22222;
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(178, 34, 34, 0.15);
        }

        .bag-option input[type="radio"] {
            display: none;
        }

        .bag-option input[type="radio"]:checked + .option-content {
            color: #b22222;
        }

        .bag-option:has(input[type="radio"]:checked) {
            border-color: #b22222;
            background: #fff5f5;
            box-shadow: 0 0 0 2px rgba(178, 34, 34, 0.2);
        }

        .option-content {
            text-align: center;
            transition: color 0.3s ease;
        }

        .option-code {
            display: block;
            font-weight: 700;
            font-size: 1.1rem;
            color: #721c24;
            margin-bottom: 2px;
        }

        .option-name {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }

        /* Blood Status Options - Compact Style */
        .blood-status-options {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .blood-status-card {
            flex: 1;
            display: block;
            cursor: pointer;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 16px;
            background: white;
            transition: all 0.3s ease;
            text-align: center;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .blood-status-card:hover {
            border-color: #b22222;
            box-shadow: 0 2px 8px rgba(178, 34, 34, 0.15);
            transform: translateY(-1px);
        }

        .blood-status-card input[type="radio"] {
            display: none;
        }

        .blood-status-card input[type="radio"]:checked + .blood-status-content {
            color: #b22222;
        }

        .blood-status-card:has(input[type="radio"]:checked) {
            border-color: #b22222 !important;
            border-width: 2px;
            background: white;
            box-shadow: none;
            transform: none;
        }

        .blood-status-content {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 15px;
        }

        /* Fallback for browsers that don't support :has() */
        .blood-status-card.selected {
            border-color: #b22222 !important;
            border-width: 2px;
            background: white;
            box-shadow: none;
            transform: none;
        }

        /* Blood Step Content - Match Physical Exam Style */
        .blood-step-content h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .blood-step-content p.text-muted {
            margin-bottom: 15px;
        }

        /* Blood Collection Report Styles */
        .blood-collection-report {
            background: #ffffff;
            border-radius: 8px;
            padding: 30px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Report Header */
        .blood-report-header {
            border-bottom: 2px solid #b22222;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .blood-report-title h5 {
            color: #b22222;
            font-weight: 700;
            margin: 0 0 10px 0;
            font-size: 1.3rem;
        }
        
        .blood-report-meta {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .blood-report-phlebotomist {
            font-weight: 500;
        }
        
        .blood-report-date {
            font-style: italic;
        }
        
        /* Report Sections */
        .blood-report-section {
            margin-bottom: 30px;
        }
        
        .blood-section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #495057;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .blood-section-header i {
            color: #b22222;
            font-size: 1.2rem;
        }
        
        .blood-section-content {
            padding-left: 25px;
        }
        
        /* Process Grid */
        .blood-process-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .blood-process-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .blood-process-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .blood-process-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #212529;
        }
        
        .blood-process-unit {
            color: #6c757d;
            font-size: 0.85rem;
            font-style: italic;
        }
        
        /* Time Grid */
        .blood-time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .blood-time-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .blood-time-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .blood-time-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #212529;
            font-family: 'Courier New', monospace;
        }
        
        /* Results Content */
        .blood-results-content {
            display: grid;
            gap: 15px;
        }
        
        .blood-result-item {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .blood-result-item:last-child {
            border-bottom: none;
        }
        
        .blood-result-label {
            color: #495057;
            font-weight: 500;
        }
        
        .blood-result-value {
            color: #212529;
            font-weight: 600;
        }
        
        /* Signature Section */
        .blood-report-signature {
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid #dee2e6;
        }
        
        .blood-signature-line {
            display: flex;
            justify-content: space-between;
            align-items: end;
            margin-bottom: 15px;
        }
        
        .blood-signature-line span {
            color: #495057;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .blood-signature-space {
            flex: 1;
            border-bottom: 1px solid #6c757d;
            margin: 0 20px 5px 20px;
            max-width: 300px;
        }
        
        .blood-signature-note {
            color: #6c757d;
            font-size: 0.8rem;
            font-style: italic;
            text-align: center;
            line-height: 1.4;
        }

        /* Helper Text */
        .form-text {
            margin-top: 5px;
            font-size: 0.85rem;
            color: #6c757d;
            font-style: italic;
        }

        /* Progress Line Improvements */
        .blood-progress-steps {
            position: relative;
            z-index: 2;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .blood-bag-grid {
                grid-template-columns: 1fr;
            }

            .form-row-modern {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .bag-options {
                grid-template-columns: 1fr;
            }

            .blood-status-options {
                flex-direction: column;
                gap: 10px;
            }

            .blood-progress-steps {
                flex-wrap: wrap;
                gap: 5px;
                justify-content: center;
            }

            .blood-step-label {
                font-size: 10px;
            }

            /* Blood Collection Report Mobile */
            .blood-collection-report {
                padding: 20px;
            }
            
            .blood-report-meta {
                flex-direction: column;
                gap: 5px;
                align-items: flex-start;
            }
            
            .blood-process-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .blood-time-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .blood-result-item {
                grid-template-columns: 1fr;
                gap: 5px;
            }
            
            .blood-result-label {
                font-weight: 600;
                color: #b22222;
            }
            
            .blood-signature-line {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .blood-signature-space {
                width: 200px;
                margin: 0;
            }
        }

        /* Modal Footer */
        .blood-modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
            background-color: white;
            border-radius: 0 0 15px 15px;
        }

        .blood-nav-buttons {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
        }

        .blood-nav-buttons .btn {
            padding: 12px 25px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .blood-cancel-btn {
            margin-right: auto;
        }

        /* Toast Messages */
        .blood-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            border-left: 4px solid #b22222;
        }

        .blood-toast.show {
            transform: translateX(0);
        }

        .blood-toast-success {
            border-left-color: #28a745;
        }

        .blood-toast-error {
            border-left-color: #dc3545;
        }

        .blood-toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .blood-toast-content i {
            font-size: 1.2rem;
        }

        .blood-toast-success i {
            color: #28a745;
        }

        .blood-toast-error i {
            color: #dc3545;
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
            <h4 class="header-title">Phlebotomist Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
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
                                System Registration
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($user_staff_roles === 'reviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard-staff-medical-history-submissions.php">
                                New Donor
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
                                Blood Collection 
                                Queue
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard-staff-history/dashboard-blood-collection-history.php">Blood Collection History</a>
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
                        <h2 class="welcome-title">Blood Collection Management</h2>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="dashboard-staff-status">
                        <a href="?status=incoming" class="status-card <?php echo (!isset($_GET['status']) || $_GET['status'] === 'incoming') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $incoming_count; ?></p>
                            <p class="dashboard-staff-title">Incoming Blood Collection</p>
                        </a>
                        <a href="?status=active" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'active') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $approved_count; ?></p>
                            <p class="dashboard-staff-title">Active Blood Collections</p>
                        </a>
                        <a href="?status=today" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'today') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $today_count; ?></p>
                            <p class="dashboard-staff-title">Today's Summary</p>
                        </a>
                    </div>
                    
                    <h5 class="section-header">Blood Collection Records</h5>
                    
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
                                    <th>Status</th>
                                    <th>Result</th>
                                    <th>Declaration</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="bloodCollectionTableBody">
                                <?php
                                
                                // Use actual database data from $display_exams
                                if (!empty($display_exams)) {
                                    $counter = 1;
                                    $displayed_donors = []; // Final safety check for duplicates
                                    
                                    foreach ($display_exams as $exam) {
                                        // Validate exam data integrity
                                        if (empty($exam['physical_exam_id']) || empty($exam['donor_id'])) {
                                            continue; // Skip invalid records
                                        }
                                        
                                        // Final duplicate check based on donor_id
                                        if (isset($displayed_donors[$exam['donor_id']])) {
                                            continue; // Skip if this donor is already displayed
                                        }
                                        $displayed_donors[$exam['donor_id']] = true;
                                        
                                        // Get donor information with better null handling
                                        $donor_form = $exam['donor_form'] ?? [];
                                        
                                        // Validate donor form data
                                        if (empty($donor_form) || !is_array($donor_form)) {
                                            continue; // Skip if donor form data is missing or invalid
                                        }
                                        
                                        $surname = htmlspecialchars(trim($donor_form['surname'] ?? 'Unknown'));
                                        $first_name = htmlspecialchars(trim($donor_form['first_name'] ?? 'Unknown'));
                                        
                                        // Skip if both names are "Unknown" (indicating missing data)
                                        if ($surname === 'Unknown' && $first_name === 'Unknown') {
                                            continue;
                                        }
                                        
                                        $physical_exam_id = $exam['physical_exam_id'];
                                        $donor_id = $exam['donor_id'];
                                        $created_at = $exam['created_at'];
                                        
                                        // Format the date
                                        $created_date = date('F d, Y', strtotime($created_at));
                                        
                                        // Determine status and result based on current filter and blood collection data
                                        switch($status_filter) {
                                            case 'incoming':
                                                $status = '<span class="badge bg-warning">Pending Collection</span>';
                                                $result = '<span class="badge bg-secondary">Awaiting</span>';
                                                break;
                                                
                                            case 'active':
                                                $status = '<span class="badge bg-success">Collected</span>';
                                                $result = '<span class="badge bg-success">Successful</span>';
                                                break;
                                                
                                            case 'today':
                                                $status = '<span class="badge bg-success">Collected Today</span>';
                                                $result = '<span class="badge bg-success">Successful</span>';
                                                break;
                                                
                                            default:
                                                $status = '<span class="badge bg-secondary">Unknown</span>';
                                                $result = '<span class="badge bg-secondary">N/A</span>';
                                        }
                                        
                                        // Create data for modal with consistent structure
                                        $modal_data = [
                                            'donor_id' => $donor_id,
                                            'physical_exam_id' => $physical_exam_id,
                                            'created_at' => $created_at,
                                            'surname' => $donor_form['surname'] ?? 'Unknown',
                                            'first_name' => $donor_form['first_name'] ?? 'Unknown',
                                            'middle_name' => $donor_form['middle_name'] ?? '',
                                            'birthdate' => $donor_form['birthdate'] ?? '',
                                            'age' => $donor_form['age'] ?? ''
                                        ];
                                        $data_exam = htmlspecialchars(json_encode($modal_data, JSON_UNESCAPED_UNICODE));
                                        
                                        echo "<tr class='clickable-row' data-examination='{$data_exam}'>
                                            <td>{$counter}</td>
                                            <td>{$created_date}</td>
                                            <td>{$surname}</td>
                                            <td>{$first_name}</td>
                                            <td>{$status}</td>
                                            <td>{$result}</td>
                                            <td><span class=\"badge bg-success\">Accepted</span></td>
                                            <td>
                                                <button type='button' class='btn btn-info btn-sm view-donor-btn me-1' data-donor-id='{$donor_id}' title='View Details'>
                                                    <i class='fas fa-eye'></i>
                                                </button>
                                                <button type='button' class='btn btn-warning btn-sm edit-donor-btn' data-donor-id='{$donor_id}' title='Edit'>
                                                    <i class='fas fa-edit'></i>
                                                </button>
                                            </td>
                                        </tr>";
                                        $counter++;
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-center text-muted">No records found for current filter</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Blood collection navigation">
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
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
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
                    <button type="button" class="btn px-4" style="background-color: #b22222; border-color: #b22222; color: white;" onclick="proceedToDonorForm()">Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
                <div class="modal-body text-center">
                    <div class="spinner-border" style="width: 3.5rem; height: 3.5rem; color: #b22222;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-white mt-3 mb-0">Please wait...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Blood Collection Modal -->
    <div class="blood-collection-modal" id="bloodCollectionModal">
        <div class="blood-modal-content">
            <div class="blood-modal-header">
                <h3><i class="fas fa-tint me-2"></i>Blood Collection Form</h3>
                <button type="button" class="blood-close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Progress Indicator -->
            <div class="blood-progress-container">
                <div class="blood-progress-steps">
                    <div class="blood-step active" data-step="1">
                        <div class="blood-step-number">1</div>
                        <div class="blood-step-label">Collection Details</div>
                    </div>
                    <div class="blood-step" data-step="2">
                        <div class="blood-step-number">2</div>
                        <div class="blood-step-label">Blood Bag</div>
                    </div>
                    <div class="blood-step" data-step="3">
                        <div class="blood-step-number">3</div>
                        <div class="blood-step-label">Collection Process</div>
                    </div>
                    <div class="blood-step" data-step="4">
                        <div class="blood-step-number">4</div>
                        <div class="blood-step-label">Results</div>
                    </div>
                    <div class="blood-step" data-step="5">
                        <div class="blood-step-number">5</div>
                        <div class="blood-step-label">Review & Submit</div>
                    </div>
                </div>
                <div class="blood-progress-line">
                    <div class="blood-progress-fill"></div>
                </div>
            </div>

            <form id="bloodCollectionForm" class="blood-modal-form">
                <!-- Step 1: Collection Details -->
                <div class="blood-step-content active" id="blood-step-1">
                    <h4>Step 1: Collection Details</h4>
                    <p class="text-muted">Donor information and readiness verification</p>
                    
                    <div class="collection-overview">
                        <!-- Donor Information Section -->
                        <div class="donor-section">
                            <div class="donor-info-row">
                                <div class="donor-main-info">
                                    <h4 class="donor-name" id="blood-donor-name-display">Loading...</h4>
                                                                <div class="donor-metadata">
                                <span class="collection-date">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <span id="blood-collection-date-display">Today's Date</span>
                                </span>
                                <span class="unit-serial-info">
                                    <i class="fas fa-barcode me-2"></i>
                                    <span>Serial: </span>
                                    <span id="blood-unit-serial-display">Generating...</span>
                                </span>
                            </div>
                                </div>

                            </div>
                        </div>

                        <!-- Status Section -->
                        <div class="ready-indicator">
                            <div class="ready-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="ready-text">
                                <span class="ready-title">Ready for Blood Collection</span>
                                <span class="ready-subtitle">Physical examination completed successfully</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Blood Bag Selection -->
                <div class="blood-step-content" id="blood-step-2">
                    <h4>Step 2: Blood Bag Selection</h4>
                    <p class="text-muted">Select the appropriate blood bag type</p>
                    
                    <div class="modern-form-container">
                        <!-- Blood Bag Selection -->
                        <div class="form-group-modern">
                            <label class="form-label-modern">
                                <i class="fas fa-vial me-2"></i>
                                Blood Bag Type
                            </label>
                            <div class="blood-bag-grid">
                                <!-- KARMI Options -->
                                <div class="bag-brand-section">
                                    <h6 class="brand-title">KARMI</h6>
                                    <div class="bag-options">
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="S-KARMI" required>
                                            <div class="option-content">
                                                <span class="option-code">S</span>
                                                <span class="option-name">Single</span>
                                            </div>
                                        </label>
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="D-KARMI" required>
                                            <div class="option-content">
                                                <span class="option-code">D</span>
                                                <span class="option-name">Double</span>
                                            </div>
                                        </label>
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="T-KARMI" required>
                                            <div class="option-content">
                                                <span class="option-code">T</span>
                                                <span class="option-name">Triple</span>
                                            </div>
                                        </label>
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="Q-KARMI" required>
                                            <div class="option-content">
                                                <span class="option-code">Q</span>
                                                <span class="option-name">Quadruple</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- TERUMO Options -->
                                <div class="bag-brand-section">
                                    <h6 class="brand-title">TERUMO</h6>
                                    <div class="bag-options">
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="S-TERUMO" required>
                                            <div class="option-content">
                                                <span class="option-code">S</span>
                                                <span class="option-name">Single</span>
                                            </div>
                                        </label>
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="D-TERUMO" required>
                                            <div class="option-content">
                                                <span class="option-code">D</span>
                                                <span class="option-name">Double</span>
                                            </div>
                                        </label>
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="T-TERUMO" required>
                                            <div class="option-content">
                                                <span class="option-code">T</span>
                                                <span class="option-name">Triple</span>
                                            </div>
                                        </label>
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="Q-TERUMO" required>
                                            <div class="option-content">
                                                <span class="option-code">Q</span>
                                                <span class="option-name">Quadruple</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- SPECIAL BAG Options -->
                                <div class="bag-brand-section">
                                    <h6 class="brand-title">SPECIAL BAG</h6>
                                    <div class="bag-options">
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="FK T&B-SPECIAL BAG" required>
                                            <div class="option-content">
                                                <span class="option-code">FK</span>
                                                <span class="option-name">T&B</span>
                                            </div>
                                        </label>
                                        <label class="bag-option">
                                            <input type="radio" name="blood_bag_type" value="TRM T&B-SPECIAL BAG" required>
                                            <div class="option-content">
                                                <span class="option-code">TRM</span>
                                                <span class="option-name">T&B</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Collection Process -->
                <div class="blood-step-content" id="blood-step-3">
                    <h4>Step 3: Collection Process</h4>
                    <p class="text-muted">Record collection details and timing</p>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="blood-amount-taken" class="form-label">Amount Collected (Units) *</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="blood-amount-taken" 
                                   name="amount_taken" 
                                   min="1" 
                                   max="10" 
                                   step="1" 
                                   placeholder="Enter units" 
                                   required>
                            <div class="form-text">Standard: 1 unit (450mL)</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="blood-start-time" class="form-label">Start Time *</label>
                            <input type="time" 
                                   class="form-control" 
                                   id="blood-start-time" 
                                   name="start_time" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="blood-end-time" class="form-label">End Time *</label>
                            <input type="time" 
                                   class="form-control" 
                                   id="blood-end-time" 
                                   name="end_time" 
                                   required>
                            <div class="form-text">Must be at least 5 minutes after start time</div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Collection Results -->
                <div class="blood-step-content" id="blood-step-4">
                    <h4>Step 4: Collection Results</h4>
                    <p class="text-muted">Indicate collection outcome</p>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Collection Status *</label>
                            <div class="blood-status-options">
                                <label class="blood-status-card">
                                    <input type="radio" name="is_successful" value="YES" required>
                                    <div class="blood-status-content">
                                        <span>Successful</span>
                                    </div>
                                </label>
                                <label class="blood-status-card">
                                    <input type="radio" name="is_successful" value="NO" required>
                                    <div class="blood-status-content">
                                        <span>Failed</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden unit serial number for form submission -->
                    <input type="hidden" id="blood-unit-serial" name="unit_serial_number">

                    <!-- Reaction Section (Show only if failed) -->
                    <div class="blood-reaction-section" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="blood-donor-reaction" class="form-label">Donor Reaction</label>
                                <textarea class="form-control" 
                                          id="blood-donor-reaction" 
                                          name="donor_reaction" 
                                          rows="4" 
                                          placeholder="Describe any reactions observed"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="blood-management-done" class="form-label">Management Done</label>
                                <textarea class="form-control" 
                                          id="blood-management-done" 
                                          name="management_done" 
                                          rows="4" 
                                          placeholder="Describe procedures performed"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Review & Submit -->
                <div class="blood-step-content" id="blood-step-5">
                    <h4>Step 5: Review & Submit</h4>
                    <p class="text-muted">Review all information before submitting</p>
                    
                    <div class="blood-collection-report">
                        <!-- Report Header -->
                        <div class="blood-report-header">
                            <div class="blood-report-title">
                                <h5>Blood Collection Report</h5>
                                <div class="blood-report-meta">
                                    <span class="blood-report-date"><?php echo date('F j, Y'); ?></span>
                                    <span class="blood-report-phlebotomist">Phlebotomist: <span id="summary-phlebotomist">Current User</span></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Collection Process Section -->
                        <div class="blood-report-section">
                            <div class="blood-section-header">
                                <i class="fas fa-vial"></i>
                                <span>Collection Process</span>
                            </div>
                            <div class="blood-section-content">
                                <div class="blood-process-grid">
                                    <div class="blood-process-item">
                                        <span class="blood-process-label">Blood Bag Type</span>
                                        <span class="blood-process-value" id="summary-blood-bag">-</span>
                                    </div>
                                    <div class="blood-process-item">
                                        <span class="blood-process-label">Amount Collected</span>
                                        <span class="blood-process-value" id="summary-amount">-</span>
                                        <span class="blood-process-unit">units</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Time Records Section -->
                        <div class="blood-report-section">
                            <div class="blood-section-header">
                                <i class="fas fa-clock"></i>
                                <span>Time Records</span>
                            </div>
                            <div class="blood-section-content">
                                <div class="blood-time-grid">
                                    <div class="blood-time-item">
                                        <span class="blood-time-label">Start Time</span>
                                        <span class="blood-time-value" id="summary-start-time">-</span>
                                    </div>
                                    <div class="blood-time-item">
                                        <span class="blood-time-label">End Time</span>
                                        <span class="blood-time-value" id="summary-end-time">-</span>
                                    </div>
                                    <div class="blood-time-item">
                                        <span class="blood-time-label">Duration</span>
                                        <span class="blood-time-value" id="summary-duration">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Collection Results Section -->
                        <div class="blood-report-section">
                            <div class="blood-section-header">
                                <i class="fas fa-clipboard-check"></i>
                                <span>Collection Results</span>
                            </div>
                            <div class="blood-section-content">
                                <div class="blood-results-content">
                                    <div class="blood-result-item">
                                        <span class="blood-result-label">Collection Status:</span>
                                        <span class="blood-result-value" id="summary-successful">-</span>
                                    </div>
                                    <div class="blood-result-item">
                                        <span class="blood-result-label">Unit Serial Number:</span>
                                        <span class="blood-result-value" id="summary-serial-number">-</span>
                                    </div>
                                    <div class="blood-result-item" id="summary-reaction-section" style="display: none;">
                                        <span class="blood-result-label">Donor Reaction:</span>
                                        <span class="blood-result-value" id="summary-reaction">-</span>
                                    </div>
                                    <div class="blood-result-item" id="summary-management-section" style="display: none;">
                                        <span class="blood-result-label">Management Done:</span>
                                        <span class="blood-result-value" id="summary-management">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Signature Section -->
                        <div class="blood-report-signature">
                            <div class="blood-signature-content">
                                <div class="blood-signature-line">
                                    <span>Collected by</span>
                                    <div class="blood-signature-space"></div>
                                </div>
                                <div class="blood-signature-note">
                                    This collection was performed in accordance with Philippine Red Cross standards and protocols.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Navigation -->
                <div class="blood-modal-footer">
                    <div class="blood-nav-buttons">
                        <button type="button" class="btn btn-outline-secondary blood-cancel-btn">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-outline-danger blood-prev-btn" style="display: none;">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </button>
                        <button type="button" class="btn btn-danger blood-next-btn">
                            <i class="fas fa-arrow-right me-2"></i>Next
                        </button>
                        <button type="button" class="btn btn-success blood-submit-btn" style="display: none;">
                            <i class="fas fa-check me-2"></i>Submit Blood Collection
                        </button>
                    </div>
                </div>
            </form>
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
            let confirmationDialog = document.getElementById("confirmationDialog");
            let loadingSpinner = document.getElementById("loadingSpinner");
            let cancelButton = document.getElementById("cancelButton");
            let confirmButton = document.getElementById("confirmButton");
            const searchInput = document.getElementById('searchInput');
            const bloodCollectionTableBody = document.getElementById('bloodCollectionTableBody');
            let currentCollectionData = null;

            // Check if elements exist before proceeding
            if (!confirmationDialog || !loadingSpinner || !cancelButton || !confirmButton || !searchInput || !bloodCollectionTableBody) {
                console.error('Required elements not found on page');
                return;
            }

            // FIXED: Don't cache rows - always use current table contents
            
            // Clear search box to ensure no filters are applied
            searchInput.value = '';
            
            // Attach click event to all rows
            function attachRowClickHandlers() {
                document.querySelectorAll(".clickable-row").forEach(row => {
                    row.addEventListener("click", function() {
                        currentCollectionData = JSON.parse(this.dataset.examination);
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

            // Yes Button (Opens Blood Collection Modal)
            confirmButton.addEventListener("click", function() {
                if (!currentCollectionData) {
                    console.error('No collection data available');
                    return;
                }

                closeModal();
                
                // Open the Blood Collection modal
                if (window.bloodCollectionModal) {
                    window.bloodCollectionModal.openModal(currentCollectionData);
                } else {
                    console.error("Blood collection modal not initialized");
                    alert("Error: Modal not properly initialized. Please refresh the page.");
                }
            });

            // No Button (Closes Modal)
            cancelButton.addEventListener("click", closeModal);

            // FIXED: Search functionality - always use current table rows
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                
                // Get current table rows (not cached ones)
                const currentRows = Array.from(bloodCollectionTableBody.getElementsByTagName('tr'));
                
                if (!searchTerm) {
                    currentRows.forEach(row => row.style.display = '');
                    return;
                }

                currentRows.forEach(row => {
                    const cells = Array.from(row.getElementsByTagName('td'));
                    const shouldShow = cells.some(cell => 
                        cell.textContent.toLowerCase().includes(searchTerm)
                    );
                    row.style.display = shouldShow ? '' : 'none';
                });
            }

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
            
            // Add loading functionality for data processing
            function showProcessingModal(message = 'Processing blood collection data...') {
                const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                const loadingText = document.querySelector('#loadingModal p');
                if (loadingText) {
                    loadingText.textContent = message;
                }
                loadingModal.show();
            }
            
            function hideProcessingModal() {
                const loadingModal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
                if (loadingModal) {
                    loadingModal.hide();
                }
            }
            
            // Make functions globally available
            window.showProcessingModal = showProcessingModal;
            window.hideProcessingModal = hideProcessingModal;
            
            // Show loading when blood collection form is submitted
            document.addEventListener('submit', function(e) {
                if (e.target && e.target.id === 'bloodCollectionForm') {
                    showProcessingModal('Submitting blood collection data...');
                }
            });
            
            // Show loading for any blood collection related AJAX calls
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                const url = args[0];
                if (typeof url === 'string' && url.includes('blood_collection')) {
                    showProcessingModal('Processing blood collection...');
                }
                return originalFetch.apply(this, args).finally(() => {
                    setTimeout(hideProcessingModal, 500); // Small delay for user feedback
                });
            };
        });
    </script>
</body>
</html>