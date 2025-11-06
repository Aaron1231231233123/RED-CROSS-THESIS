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


// STEP 1: Get all blood collection records using pagination to bypass 1000 record limit
$blood_collections = [];
$offset = 0;
$limit = 1000;
$has_more = true;
$max_iterations = 10;
$iteration = 0;

$headers = array(
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY,
    'Accept: application/json'
);

while ($has_more && $iteration < $max_iterations) {
    $blood_collection_url = SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id,physical_exam_id,is_successful,donor_reaction,management_done,status,blood_bag_type,blood_bag_brand,amount_taken,start_time,end_time,unit_serial_number,needs_review,phlebotomist,created_at,updated_at&limit=' . $limit . '&offset=' . $offset;
    
    $ch = curl_init($blood_collection_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Blood collection batch $iteration: fetched " . (($http_code === 200) ? count(json_decode($response, true) ?: []) : 0) . " records (offset: $offset)");
    
    if ($http_code !== 200) {
        error_log("Error fetching blood collections batch $iteration. HTTP code: " . $http_code);
        error_log("Response: " . $response);
        $has_more = false;
    } else {
        $batch = json_decode($response, true) ?: [];
        if (empty($batch)) {
            $has_more = false;
        } else {
            $blood_collections = array_merge($blood_collections, $batch);
            $offset += $limit;
            $iteration++;
            
            if (count($batch) < $limit) {
                $has_more = false;
            }
        }
    }
}

error_log("Total blood collections fetched: " . count($blood_collections));

// Fetch eligibility blood_collection_id list using pagination to detect returnees
$eligibility_rows = [];
$offset = 0;
$limit = 1000;
$has_more = true;
$max_iterations = 10;
$iteration = 0;

while ($has_more && $iteration < $max_iterations) {
    $eligibility_url = SUPABASE_URL . '/rest/v1/eligibility?select=blood_collection_id,donor_id&limit=' . $limit . '&offset=' . $offset;
    
    $ch = curl_init($eligibility_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $elig_response = curl_exec($ch);
    $elig_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Eligibility batch $iteration: fetched " . (($elig_http_code === 200) ? count(json_decode($elig_response, true) ?: []) : 0) . " records (offset: $offset)");
    
    if ($elig_http_code !== 200) {
        error_log("Error fetching eligibility batch $iteration. HTTP code: " . $elig_http_code);
        $has_more = false;
    } else {
        $batch = json_decode($elig_response, true) ?: [];
        if (empty($batch)) {
            $has_more = false;
        } else {
            $eligibility_rows = array_merge($eligibility_rows, $batch);
            $offset += $limit;
            $iteration++;
            
            if (count($batch) < $limit) {
                $has_more = false;
            }
        }
    }
}

error_log("Total eligibility records fetched: " . count($eligibility_rows));

$eligibility_collection_ids = [];
$eligibility_by_donor = [];
foreach ($eligibility_rows as $erow) {
    if (!empty($erow['blood_collection_id'])) {
        $eligibility_collection_ids[(string)$erow['blood_collection_id']] = true;
    }
    if (isset($erow['donor_id'])) {
        $eligibility_by_donor[(int)$erow['donor_id']] = true;
    }
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
        // Annotate if eligibility exists for this blood_collection_id
        $collection_id = isset($collection['blood_collection_id']) ? (string)$collection['blood_collection_id'] : null;
        if ($collection_id && isset($eligibility_collection_ids[$collection_id])) {
            $collection['has_eligibility'] = true;
        }
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

// STEP 2: Get ALL physical examinations using pagination (not just accepted ones)
$physical_exam_records = [];
$offset = 0;
$limit = 1000;
$has_more = true;
$max_iterations = 10;
$iteration = 0;

while ($has_more && $iteration < $max_iterations) {
    $physical_exam_url = SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,donor_id,remarks,blood_bag_type,created_at&order=created_at.desc&limit=' . $limit . '&offset=' . $offset;
    
    $ch = curl_init($physical_exam_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Physical exam batch $iteration: fetched " . (($http_code === 200) ? count(json_decode($response, true) ?: []) : 0) . " records (offset: $offset)");
    
    if ($http_code !== 200) {
        error_log("Error fetching physical examinations batch $iteration. HTTP code: " . $http_code);
        $has_more = false;
    } else {
        $batch = json_decode($response, true) ?: [];
        if (empty($batch)) {
            $has_more = false;
        } else {
            $physical_exam_records = array_merge($physical_exam_records, $batch);
            $offset += $limit;
            $iteration++;
            
            if (count($batch) < $limit) {
                $has_more = false;
            }
        }
    }
}

error_log("Total physical exam records fetched: " . count($physical_exam_records));

// Query 2: Get all donor_form records using pagination
$donor_records = [];
$offset = 0;
$limit = 1000;
$has_more = true;
$max_iterations = 10;
$iteration = 0;

while ($has_more && $iteration < $max_iterations) {
    $donor_form_url = SUPABASE_URL . '/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,age,prc_donor_number&limit=' . $limit . '&offset=' . $offset;
    
    $ch2 = curl_init($donor_form_url);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
    $donor_response = curl_exec($ch2);
    $donor_http_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    error_log("Donor form batch $iteration: fetched " . (($donor_http_code === 200) ? count(json_decode($donor_response, true) ?: []) : 0) . " records (offset: $offset)");
    
    if ($donor_http_code !== 200) {
        error_log("Error fetching donor forms batch $iteration. HTTP code: " . $donor_http_code);
        $has_more = false;
    } else {
        $batch = json_decode($donor_response, true) ?: [];
        if (empty($batch)) {
            $has_more = false;
        } else {
            $donor_records = array_merge($donor_records, $batch);
            $offset += $limit;
            $iteration++;
            
            if (count($batch) < $limit) {
                $has_more = false;
            }
        }
    }
}

error_log("Total donor records fetched: " . count($donor_records));

// Create donor lookup array
$donor_lookup = [];
foreach ($donor_records as $donor) {
    $donor_id = $donor['donor_id'];
    $donor_lookup[$donor_id] = $donor;
}

// Create unified data structure with both processed and unprocessed records
$all_records = [];
$donor_latest_exams = []; // Track the latest exam per donor

error_log("Blood Collection Dashboard - Starting record processing with " . count($physical_exam_records) . " physical exams and " . count($donor_records) . " donor records");

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

// Use only the latest exam per donor and create unified records
$physical_exams = array_values($donor_latest_exams);

foreach ($physical_exams as $exam) {
    $exam_id = (string)$exam['physical_exam_id'];
    $has_blood_collection = in_array($exam_id, $collected_physical_exam_ids);
    
    // Only create records for physical exams that have blood collection data
    if ($has_blood_collection && isset($blood_collection_data[$exam_id])) {
        // Create record with collection status
        $record = [
            'physical_exam_id' => $exam['physical_exam_id'],
            'donor_id' => $exam['donor_id'],
            'created_at' => $exam['created_at'],
            'remarks' => $exam['remarks'],
            'blood_bag_type' => $exam['blood_bag_type'],
            'donor_form' => $exam['donor_form'],
            'has_blood_collection' => $has_blood_collection,
            'blood_collection_data' => $blood_collection_data[$exam_id]
        ];
        
        $all_records[] = $record;
    }
}

error_log("Blood Collection Dashboard - Total records processed: " . count($all_records));



// Calculate statistics from unified records (only records with blood collection data)
$incoming_count = 0; // Records with needs_review = true
$approved_count = 0; // Successfully collected records
$today_count = 0; // Today's collections

$today = date('Y-m-d');

foreach ($all_records as $record) {
    $bc = $record['blood_collection_data'] ?? null;
    $needs_review_flag = ($bc && isset($bc['needs_review']) && ($bc['needs_review'] === true || $bc['needs_review'] === 1 || $bc['needs_review'] === '1'));
    
    // Count only records with needs_review = true
    if ($needs_review_flag) {
        $incoming_count++;
    }
    
    // Count successfully collected records
    if (isset($record['blood_collection_data']['is_successful']) && $record['blood_collection_data']['is_successful'] === true) {
        $approved_count++;
    }
    
    // Count today's collections
    if (isset($record['blood_collection_data']['created_at'])) {
        $collection_date = date('Y-m-d', strtotime($record['blood_collection_data']['created_at']));
        if ($collection_date === $today) {
            $today_count++;
        }
    }
}

// Handle status filtering with unified records
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Filter records based on status
$display_records = [];

switch ($status_filter) {
    case 'all':
        // Show all records (all have blood collection data now)
        $display_records = $all_records;
        break;
        
    case 'active':
        // Show only successfully collected records
        $display_records = array_filter($all_records, function($record) {
            return isset($record['blood_collection_data']['is_successful']) && 
                   $record['blood_collection_data']['is_successful'] === true;
        });
        break;
        
    case 'today':
        // Show only today's collections
        $display_records = array_filter($all_records, function($record) use ($today) {
            return isset($record['blood_collection_data']['created_at']) &&
                   date('Y-m-d', strtotime($record['blood_collection_data']['created_at'])) === $today;
        });
        break;
        
    case 'incoming':
    default:
        // Show only items with needs_review === true
        $display_records = array_filter($all_records, function($record) {
            $bc = $record['blood_collection_data'] ?? null;
            $needs_review_flag = ($bc && isset($bc['needs_review']) && ($bc['needs_review'] === true || $bc['needs_review'] === 1 || $bc['needs_review'] === '1'));
            return $needs_review_flag; // Only show records with needs_review = true
        });
        break;
}

// Sort records with priority:
// 1) needs_review === true first
// 2) FIFO by time (prefer blood_collection.updated_at, fallback created_at, else exam created_at)
usort($display_records, function($a, $b) {
    $a_needs_review = (!empty($a['blood_collection_data']) && isset($a['blood_collection_data']['needs_review']) && ($a['blood_collection_data']['needs_review'] === true || $a['blood_collection_data']['needs_review'] === 1 || $a['blood_collection_data']['needs_review'] === '1'));
    $b_needs_review = (!empty($b['blood_collection_data']) && isset($b['blood_collection_data']['needs_review']) && ($b['blood_collection_data']['needs_review'] === true || $b['blood_collection_data']['needs_review'] === 1 || $b['blood_collection_data']['needs_review'] === '1'));

    if ($a_needs_review !== $b_needs_review) {
        return $a_needs_review ? -1 : 1;
    }

    $normalizeTs = function($ts) {
        if (!$ts || !is_string($ts)) return 0;
        $s = preg_replace('/\.[0-9]{1,6}/', '', $ts);
        $s = str_replace('T', ' ', $s);
        $s = preg_replace('/(Z|[+-][0-9]{2}:[0-9]{2})$/', '', $s);
        return strtotime(trim($s)) ?: 0;
    };

    $resolveTime = function($rec) use ($normalizeTs) {
        if (!empty($rec['has_blood_collection']) && !empty($rec['blood_collection_data'])) {
            if (!empty($rec['blood_collection_data']['updated_at'])) return $normalizeTs($rec['blood_collection_data']['updated_at']);
            if (!empty($rec['blood_collection_data']['created_at'])) return strtotime($rec['blood_collection_data']['created_at']);
        }
        return isset($rec['created_at']) ? strtotime($rec['created_at']) : 0;
    };

    $a_time = $resolveTime($a);
    $b_time = $resolveTime($b);
    return $a_time <=> $b_time;
});

// Prepare pagination
$total_records = count($display_records);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Calculate the offset for this page
$offset = ($current_page - 1) * $records_per_page;

// Slice the array to get only the records for the current page
$display_records = array_slice($display_records, $offset, $records_per_page);

// Calculate execution time
$end_time = microtime(true);
$execution_time = ($end_time - $start_time);



// Calculate age for each record
foreach ($display_records as $index => $record) {
    if (isset($record['donor_form'])) {
        // Calculate age if not present but birthdate is available
        if (empty($record['donor_form']['age']) && !empty($record['donor_form']['birthdate'])) {
            $birthDate = new DateTime($record['donor_form']['birthdate']);
            $today = new DateTime();
            $display_records[$index]['donor_form']['age'] = $birthDate->diff($today)->y;
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
    <script src="../../assets/js/phlebotomist_blood_collection_details_modal.js"></script>
    <script src="../../assets/js/filter-loading-modal.js"></script>
    <script src="../../assets/js/search_func/search_account_blood_collection.js"></script>
    <script src="../../assets/js/search_func/filter_search_account_blood_collection.js"></script>
    <style>
        :root {
            --bg-color: #f5f5f5;
            --text-color: #000;
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
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-left {
            display: flex;
            align-items: center;
        }
        
        .header-title {
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            color: #333;
        }
        
        .header-date {
            color: #777;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            font-size: 0.9rem;
        }
        
        .logout-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.4rem 0.75rem;
            border-radius: 3px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background-color: var(--primary-dark);
            color: white;
            text-decoration: none;
        }

        /* Main Content */
        .main-content {
            padding: 1rem;
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
        
        .dashboard-staff-tables th:nth-child(7),
        .dashboard-staff-tables td:nth-child(7) {
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

        /* Enhanced Pagination Styles */
        .pagination-container {
            margin-top: 2rem;
            padding: 1rem 0;
        }

        .pagination {
            justify-content: center;
            margin: 0;
            flex-wrap: wrap;
        }

        .page-item {
            margin: 0 2px;
        }

        .page-link {
            color: #333;
            border-color: #dee2e6;
            padding: 0.5rem 0.75rem;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            min-width: 40px;
            text-align: center;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-link:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #f8f9fa;
            border-color: #dee2e6;
            cursor: not-allowed;
        }

        .page-item.disabled .page-link:hover {
            transform: none;
            box-shadow: none;
        }

        /* Ellipsis styling */
        .page-item.disabled .page-link {
            cursor: default;
        }

        /* Responsive pagination */
        @media (max-width: 768px) {
            .pagination {
                flex-wrap: wrap;
                gap: 2px;
            }
            
            .page-link {
                padding: 0.4rem 0.6rem;
                font-size: 0.85rem;
                min-width: 35px;
            }
            
            .page-link i {
                font-size: 0.8rem;
            }
        }
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
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
            z-index: 10001;
            border-radius: 10px;
            width: 500px;
            display: none;
            opacity: 0;
            overflow: hidden;
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

        /* Phlebotomist Modal Loading Spinner */
        .phlebotomist-modal-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10005;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .phlebotomist-spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid #b22222;
            border-radius: 50%;
            animation: phlebotomist-spin 1s linear infinite;
            margin-bottom: 20px;
        }

        .phlebotomist-loading-text {
            color: white;
            font-size: 16px;
            font-weight: 500;
        }

        @keyframes phlebotomist-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .modal-headers {
            background: #b22222;
            color: white;
            font-size: 18px;
            font-weight: bold;
            padding: 15px 20px;
            margin: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close-btn:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 25px 20px;
            margin: 0;
        }

        .modal-body p {
            margin: 0;
            font-size: 16px;
            color: #333;
            line-height: 1.5;
        }

        #modal-donor-id {
            font-weight: bold;
            color: #b22222;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            padding: 0 20px 20px 20px;
            margin: 0;
        }

        .modal-button {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .cancel-action {
            background: #6c757d;
            color: white;
            margin-right: 10px;
        }

        .cancel-action:hover {
            background: #5a6268;
        }

        .confirm-action {
            background: #b22222;
            color: white;
        }

        .confirm-action:hover {
            background: #8b0000;
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
            align-items: center;
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .blood-signature-line span {
            color: #495057;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .collector-name {
            color: #b22222 !important;
            font-weight: 600 !important;
            font-size: 1rem !important;
            border-bottom: 1px solid #6c757d;
            padding-bottom: 2px;
            min-width: 150px;
            text-align: center;
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
            <div class="header-left">
                <h4 class="header-title">Phlebotomist Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
            </div>
            <div class="header-right">
                <a href="../../assets/php_func/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="row g-0">
            <!-- Main Content -->
            <main class="col-12 main-content">
                <div class="content-wrapper">

                    
                    <div class="welcome-section">
                        <h2 class="welcome-title">Blood Collection Review Management</h2>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="dashboard-staff-status">
                        <a href="?status=all" class="status-card <?php echo (!isset($_GET['status']) || $_GET['status'] === 'all') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo count($all_records); ?></p>
                            <p class="dashboard-staff-title">All Blood Collections</p>
                        </a>
                        <a href="?status=incoming" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'incoming') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $incoming_count; ?></p>
                            <p class="dashboard-staff-title">Needs Review</p>
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
                                    <th>Donor ID</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Collection Status</th>
                                    <th>Phlebotomist</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="bloodCollectionTableBody">
                                <?php
                                
                                // Use unified records data
                                if (!empty($display_records)) {
                                    $counter = 1;
                                    
                                    foreach ($display_records as $record) {
                                        // Validate record data integrity
                                        if (empty($record['physical_exam_id']) || empty($record['donor_id'])) {
                                            continue; // Skip invalid records
                                        }
                                        
                                        // Get donor information with better null handling
                                        $donor_form = $record['donor_form'] ?? [];
                                        
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
                                        
                                        $physical_exam_id = $record['physical_exam_id'];
                                        $donor_id = $record['donor_id'];
                                        $created_at = $record['created_at'];
                                        $remarks = $record['remarks'];
                                        
                                        // Format the date
                                        $created_date = date('F d, Y', strtotime($created_at));
                                        
                                        // Determine status and result
                                        $bc = $record['blood_collection_data'] ?? null;
                                        // Robust needs_review parsing (handles true/false, 1/0, '1', 't', 'true', 'yes')
                                        $needs_review = false;
                                        if ($bc && array_key_exists('needs_review', $bc)) {
                                            $nr = $bc['needs_review'];
                                            if (is_bool($nr)) {
                                                $needs_review = $nr === true;
                                            } elseif (is_numeric($nr)) {
                                                $needs_review = intval($nr) === 1;
                                            } elseif (is_string($nr)) {
                                                $val = strtolower(trim($nr));
                                                $needs_review = in_array($val, ['true','t','1','yes','y','on'], true);
                                            }
                                        }
                                        $core_fields = ['is_successful','start_time','end_time','amount_taken','unit_serial_number'];
                                        $has_any_core = false;
                                        if ($bc && is_array($bc)) {
                                            foreach ($core_fields as $cf) {
                                                if (isset($bc[$cf]) && $bc[$cf] !== null && $bc[$cf] !== '') { $has_any_core = true; break; }
                                            }
                                        }

                                        // Compute eligibility presence by collection_id or donor fallback
                                        $has_elig = false;
                                        if ($bc && isset($bc['blood_collection_id'])) {
                                            $bcid = (string)$bc['blood_collection_id'];
                                            $has_elig = isset($eligibility_collection_ids[$bcid]);
                                        }
                                        if (!$has_elig && isset($record['donor_id']) && isset($eligibility_by_donor[(int)$record['donor_id']])) {
                                            $has_elig = true;
                                        }
                                        if ($has_elig && !$needs_review) {
                                            $status = '<span class="badge bg-success">Completed</span>';
                                            $result = '<span class="badge bg-success">Successful</span>';
                                        } elseif ($needs_review && $has_elig) {
                                            // Show "Not Started" instead of "Needs Review" in frontend
                                            $status = '<span class="badge bg-secondary">Not Started</span>';
                                            $result = '<span class="badge bg-secondary">Awaiting</span>';
                                        } else {
                                            // Has collection row; only mark failed if explicitly false
                                            if (isset($bc['is_successful']) && $bc['is_successful'] === true) {
                                                $status = '<span class="badge bg-success">Completed</span>';
                                                $result = '<span class="badge bg-success">Successful</span>';
                                            } elseif (isset($bc['is_successful']) && $bc['is_successful'] === false) {
                                                $status = '<span class="badge bg-danger">Failed</span>';
                                                $result = '<span class="badge bg-danger">Unsuccessful</span>';
                                            } else {
                                                // is_successful is null → treat as not started
                                                $status = '<span class="badge bg-secondary">Not Started</span>';
                                                $result = '<span class="badge bg-secondary">Awaiting</span>';
                                            }
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
                                            'age' => $donor_form['age'] ?? '',
                                            'prc_donor_number' => $donor_form['prc_donor_number'] ?? ''
                                        ];
                                        $data_exam = htmlspecialchars(json_encode($modal_data, JSON_UNESCAPED_UNICODE));
                                        
                                        // Conditional action buttons - Collect only when needs_review is true (backend logic unchanged)
                                        if ($needs_review) {
                                            $action_buttons = "
                                                <button type='button' class='btn btn-success btn-sm collect-btn' data-examination='{$data_exam}' title='Collect Blood'>
                                                    <i class='fas fa-tint'></i> Collect
                                                </button>";
                                        } else {
                                            $action_buttons = "
                                                <button type='button' class='btn btn-info btn-sm view-donor-btn' data-donor-id='{$donor_id}' title='View Blood Collection Details'>
                                                    <i class='fas fa-eye'></i>
                                                </button>";
                                        }
                                        
                                        // Determine collection status - simplified logic to match action buttons
                                        if ($needs_review) {
                                            // If needs_review is true, show "Not Started" (matches Collect button)
                                            $collection_status = '<span class="badge bg-secondary">Not Started</span>';
                                        } else {
                                            // If needs_review is false, check if collection was successful
                                            if (isset($bc['is_successful']) && $bc['is_successful'] === true) {
                                                $collection_status = '<span class="badge bg-success">Completed</span>';
                                            } elseif (isset($bc['is_successful']) && $bc['is_successful'] === false) {
                                                $collection_status = '<span class="badge bg-danger">Failed</span>';
                                            } else {
                                                $collection_status = '<span class="badge bg-secondary">Not Started</span>';
                                            }
                                        }
                                        
                                        // Phlebotomist from blood_collection when available; else Assigned
                                        if ($bc && !empty($bc['phlebotomist'])) {
                                            $phlebotomist = htmlspecialchars($bc['phlebotomist']);
                                        } else {
                                            $phlebotomist = 'Assigned';
                                        }
                                        
                                        // Extract only the number part from prc_donor_number (remove "PRC-" prefix)
                                        $prc_number = $donor_form['prc_donor_number'] ?? '';
                                        $display_number = $prc_number;
                                        if (!empty($prc_number) && strpos($prc_number, 'PRC-') === 0) {
                                            $display_number = substr($prc_number, 4); // Remove "PRC-" prefix
                                        } elseif (empty($prc_number)) {
                                            $display_number = $donor_id;
                                        }
                                        
                                        echo "<tr class='clickable-row' data-examination='{$data_exam}'>
                                            <td>{$counter}</td>
                                            <td>" . htmlspecialchars($display_number) . "</td>
                                            <td>{$surname}</td>
                                            <td>{$first_name}</td>
                                            <td>{$collection_status}</td>
                                            <td>{$phlebotomist}</td>
                                            <td style=\"text-align: center;\">
                                                {$action_buttons}
                                            </td>
                                        </tr>";
                                        $counter++;
                                    }
                                } else {
                                    echo '<tr><td colspan="7" class="text-center text-muted">No records found for current filter</td></tr>';
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
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>" <?php echo $current_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                            Previous
                                        </a>
                                    </li>
                                    
                                    <!-- Smart page numbers with ellipsis -->
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    // Always show first page
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1<?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Page numbers around current page -->
                                    <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <!-- Always show last page -->
                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>"><?php echo $total_pages; ?></a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Next button -->
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>" <?php echo $current_page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                            Next
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

<!-- Confirmation Modal -->
<div class="confirmation-modal" id="confirmationDialog">
                    <div class="modal-headers">
                        <span>Begin Collection?</span>
                        <button class="modal-close-btn" id="modalCloseBtn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Confirm you're starting blood collection for donor <span id="modal-donor-id">[ID]</span>.</p>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-button cancel-action" id="cancelButton">No</button>
                        <button class="modal-button confirm-action" id="confirmButton">Confirm</button>
                    </div>
                </div>

<!-- Collection Complete Confirmation Modal -->
<div class="confirmation-modal" id="collectionCompleteModal">
                    <div class="modal-headers">
                        <span>Collection Complete</span>
                        <button class="modal-close-btn" id="collectionCompleteCloseBtn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Confirm blood unit was successfully collected and labeled?</p>
                    </div>
                    <div class="modal-actions">
                        <button class="modal-button cancel-action" id="goBackButton">Go Back</button>
                        <button class="modal-button confirm-action" id="finalConfirmButton">Confirm</button>
                    </div>
                </div>

<!-- Donation Successful Modal -->
<div class="confirmation-modal" id="donationSuccessModal">
                    <div class="modal-headers">
                        <span>Donation Successful</span>
                    </div>
                    <div class="modal-body">
                        <p>Donation completed successfully! Blood has been added to Blood Bank.</p>
                    </div>
                </div>    

    <!-- Phlebotomist Blood Collection Details Modal -->
    <div class="phlebotomist-modal" id="phlebotomistBloodCollectionDetailsModal">
        <div class="phlebotomist-modal-content">
            <div class="phlebotomist-modal-header">
                <h3><i class="fas fa-tint me-2"></i>Blood Collection Details</h3>
                <button type="button" class="phlebotomist-close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="phlebotomist-modal-body">
                <!-- Donor Header -->
                <div class="phlebotomist-donor-header">
                    <div class="phlebotomist-donor-info">
                        <h4 class="phlebotomist-donor-name" id="phlebotomist-donor-name">Loading...</h4>
                        <p class="phlebotomist-donor-age-gender" id="phlebotomist-donor-age-gender">Loading...</p>
                    </div>
                    <div class="phlebotomist-donor-meta">
                        <p class="phlebotomist-donor-id" id="phlebotomist-donor-id">Loading...</p>
                        <p class="phlebotomist-blood-type" id="phlebotomist-blood-type">Loading...</p>
                    </div>
                </div>

                <h5 class="phlebotomist-section-title">Blood Collection Details</h5>
                <hr class="phlebotomist-title-line">

                <!-- Donation Details Table -->
                <h6 class="phlebotomist-section-subtitle">Donation Details</h6>
                <table class="phlebotomist-details-table">
                    <thead>
                        <tr>
                            <th>Collection Date</th>
                            <th>Bag Type</th>
                            <th>Unit Serial Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" id="phlebotomist-collection-date" placeholder="--/--/--" readonly></td>
                            <td><input type="text" id="phlebotomist-bag-type" placeholder="" readonly></td>
                            <td><input type="text" id="phlebotomist-unit-serial" placeholder="" readonly></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Procedure Details Table -->
                <h6 class="phlebotomist-section-subtitle">Procedure Details</h6>
                <table class="phlebotomist-details-table">
                    <thead>
                        <tr>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Donor Reaction</th>
                            <th>Expiration Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="position: relative;">
                                    <input type="text" id="phlebotomist-start-time" placeholder="--:--" readonly>
                                    <button type="button" class="phlebotomist-time-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #007bff; border: none; border-radius: 50%; width: 24px; height: 24px; color: white; font-size: 12px; cursor: pointer;">V</button>
                                </div>
                            </td>
                            <td>
                                <div style="position: relative;">
                                    <input type="text" id="phlebotomist-end-time" placeholder="--:--" readonly>
                                    <button type="button" class="phlebotomist-time-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #007bff; border: none; border-radius: 50%; width: 24px; height: 24px; color: white; font-size: 12px; cursor: pointer;">V</button>
                                </div>
                            </td>
                            <td><input type="text" id="phlebotomist-donor-reaction" placeholder="" readonly></td>
                            <td><input type="text" id="phlebotomist-expiration-date" placeholder="--/--/--" readonly></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Phlebotomist Section -->
                <div class="phlebotomist-phlebotomist-section">
                    <div class="phlebotomist-phlebotomist-label">Phlebotomist Name: -</div>
                </div>
            </div>
        </div>
    </div>
                
                <!-- Loading Spinner -->
                <div class="loading-spinner" id="loadingSpinner"></div>
                
                <!-- Phlebotomist Modal Loading Spinner -->
                <div class="phlebotomist-modal-loading" id="phlebotomistModalLoading">
                    <div class="phlebotomist-spinner"></div>
                    <div class="phlebotomist-loading-text">Loading donor details...</div>
                </div>
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
                                    <span id="blood-collection-date-display">Loading...</span>
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
                    
                    <!-- Hidden field for amount_taken - always 1 unit -->
                    <input type="hidden" name="amount_taken" value="1">
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

                    <!-- Hidden phlebotomist (auto-filled from logged-in user) -->
                    <input type="hidden" id="blood-phlebotomist" name="phlebotomist">

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
                                        <span class="blood-process-value">1</span>
                                        <span class="blood-process-unit">unit (450mL)</span>
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
                                    <span class="collector-name"><?php 
                                        // Get the logged-in user's name from the users table
                                        if (isset($_SESSION['user_id'])) {
                                            $user_id = $_SESSION['user_id'];
                                            $ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=first_name,surname&user_id=eq.' . $user_id);
                                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                                'apikey: ' . SUPABASE_API_KEY,
                                                'Authorization: Bearer ' . SUPABASE_API_KEY,
                                                'Accept: application/json'
                                            ]);
                                            $response = curl_exec($ch);
                                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                            curl_close($ch);
                                            
                                            if ($http_code === 200) {
                                                $user_data = json_decode($response, true);
                                                if (!empty($user_data)) {
                                                    $first_name = $user_data[0]['first_name'] ?? '';
                                                    $surname = $user_data[0]['surname'] ?? '';
                                                    $full_name = trim($first_name . ' ' . $surname);
                                                    if (!empty($full_name)) {
                                                        echo htmlspecialchars($full_name);
                                                    } else {
                                                        echo 'Current User';
                                                    }
                                                } else {
                                                    echo 'Current User';
                                                }
                                            } else {
                                                echo 'Current User';
                                            }
                                        } else {
                                            echo 'Current User';
                                        }
                                    ?></span>
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
        // Expose logged-in staff name for phlebotomist auto-fill
        <?php 
            $staff_first = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : '';
            $staff_last = isset($_SESSION['surname']) ? $_SESSION['surname'] : '';
            $staff_fullname = trim($staff_first . ' ' . $staff_last);
        ?>
        const LOGGED_PHLEBOTOMIST = <?php echo json_encode($staff_fullname); ?>;
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
            let modalCloseBtn = document.getElementById("modalCloseBtn");
            
            // New modal elements
            let collectionCompleteModal = document.getElementById("collectionCompleteModal");
            let donationSuccessModal = document.getElementById("donationSuccessModal");
            let goBackButton = document.getElementById("goBackButton");
            let finalConfirmButton = document.getElementById("finalConfirmButton");
            let collectionCompleteCloseBtn = document.getElementById("collectionCompleteCloseBtn");
            
            const searchInput = document.getElementById('searchInput');
            const bloodCollectionTableBody = document.getElementById('bloodCollectionTableBody');
            let currentCollectionData = null;

            // Check if elements exist before proceeding
            if (!confirmationDialog || !loadingSpinner || !cancelButton || !confirmButton || !modalCloseBtn || !searchInput || !bloodCollectionTableBody) {
                console.error('Required elements not found on page');
                return;
            }

            // FIXED: Don't cache rows - always use current table contents
            
            // Clear search box to ensure no filters are applied
            searchInput.value = '';
            
            // Attach click event to all rows and collect buttons
            function attachRowClickHandlers() {
                document.querySelectorAll(".clickable-row").forEach(row => {
                    row.addEventListener("click", function(e) {
                        // Don't trigger row click if clicking on a button or view button
                        if (e.target.closest('button') || e.target.closest('.view-donor-btn')) {
                            return;
                        }
                        
                        // Only show blood collection modal for rows that need review (have collect button)
                        const collectBtn = this.querySelector('.collect-btn');
                        if (!collectBtn) {
                            return; // Don't show modal for completed collections
                        }
                        
                        currentCollectionData = JSON.parse(this.dataset.examination);
                        
                        // Update modal with donor information
                        const modalDonorId = document.getElementById('modal-donor-id');
                        if (modalDonorId && currentCollectionData.prc_donor_number) {
                            // Remove "PRC-" prefix if present
                            const donorNumber = currentCollectionData.prc_donor_number.startsWith('PRC-') 
                                ? currentCollectionData.prc_donor_number.substring(4) 
                                : currentCollectionData.prc_donor_number;
                            modalDonorId.textContent = donorNumber;
                        } else if (modalDonorId) {
                            modalDonorId.textContent = currentCollectionData.donor_id || 'Unknown';
                        }
                        
                        confirmationDialog.classList.remove("hide");
                        confirmationDialog.classList.add("show");
                        confirmationDialog.style.display = "block";
                    });
                });
                
                // Attach click event to collect buttons
                document.querySelectorAll(".collect-btn").forEach(button => {
                    button.addEventListener("click", function(e) {
                        e.stopPropagation(); // Prevent row click
                        try {
                            currentCollectionData = JSON.parse(this.getAttribute('data-examination'));
                            console.log("Selected record for collection:", currentCollectionData);
                            
                            // Update modal with donor information
                            const modalDonorId = document.getElementById('modal-donor-id');
                            if (modalDonorId && currentCollectionData.prc_donor_number) {
                                // Remove "PRC-" prefix if present
                                const donorNumber = currentCollectionData.prc_donor_number.startsWith('PRC-') 
                                    ? currentCollectionData.prc_donor_number.substring(4) 
                                    : currentCollectionData.prc_donor_number;
                                modalDonorId.textContent = donorNumber;
                            } else if (modalDonorId) {
                                modalDonorId.textContent = currentCollectionData.donor_id || 'Unknown';
                            }
                            
                            confirmationDialog.classList.remove("hide");
                            confirmationDialog.classList.add("show");
                            confirmationDialog.style.display = "block";
                        } catch (e) {
                            console.error("Error parsing examination data:", e);
                            alert("Error selecting this record. Please try again.");
                        }
                    });
                });
            }

            attachRowClickHandlers();
            
            // Attach click event to view buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('.view-donor-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const donorId = e.target.closest('.view-donor-btn').getAttribute('data-donor-id');
                    if (donorId && window.phlebotomistBloodCollectionDetailsModal) {
                        window.phlebotomistBloodCollectionDetailsModal.openModal(donorId);
                    }
                }
            });

            // Close Modal Function
            function closeModal() {
                confirmationDialog.classList.remove("show");
                confirmationDialog.classList.add("hide");
                setTimeout(() => {
                    confirmationDialog.style.display = "none";
                }, 300);
            }
            
            // Close Collection Complete Modal Function
            function closeCollectionCompleteModal() {
                collectionCompleteModal.classList.remove("show");
                collectionCompleteModal.classList.add("hide");
                setTimeout(() => {
                    collectionCompleteModal.style.display = "none";
                }, 300);
            }
            
            // Close Donation Success Modal Function
            function closeDonationSuccessModal() {
                donationSuccessModal.classList.remove("show");
                donationSuccessModal.classList.add("hide");
                setTimeout(() => {
                    donationSuccessModal.style.display = "none";
                }, 300);
            }
            
            // Show Collection Complete Modal Function
            function showCollectionCompleteModal() {
                if (collectionCompleteModal) {
                    collectionCompleteModal.classList.remove("hide");
                    collectionCompleteModal.classList.add("show");
                    collectionCompleteModal.style.display = "block";
                }
            }
            
            // Show Donation Success Modal Function
            function showDonationSuccessModal() {
                donationSuccessModal.classList.remove("hide");
                donationSuccessModal.classList.add("show");
                donationSuccessModal.style.display = "block";
                
                // Auto-reload after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
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
                    // Auto-fill phlebotomist hidden field
                    const phField = document.getElementById('blood-phlebotomist');
                    if (phField && LOGGED_PHLEBOTOMIST) {
                        phField.value = LOGGED_PHLEBOTOMIST;
                    }
                } else {
                    console.error("Blood collection modal not initialized");
                    alert("Error: Modal not properly initialized. Please refresh the page.");
                }
            });

            // No Button (Closes Modal)
            cancelButton.addEventListener("click", closeModal);
            
            // Close Button (Closes Modal)
            modalCloseBtn.addEventListener("click", closeModal);
            
            // Collection Complete Modal Event Handlers
            if (goBackButton) {
                goBackButton.addEventListener("click", function() {
                    closeCollectionCompleteModal();
                });
            }
            
            if (finalConfirmButton) {
                finalConfirmButton.addEventListener("click", function() {
                    closeCollectionCompleteModal();
                    // Trigger the actual form submission
                    if (window.bloodCollectionModal) {
                        window.bloodCollectionModal.submitForm();
                    }
                });
            }
            
            if (collectionCompleteCloseBtn) {
                collectionCompleteCloseBtn.addEventListener("click", function() {
                    closeCollectionCompleteModal();
                });
            }
            

            // Client-side row filtering removed. Search is now server-backed via search_account_blood_collection.js
            
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
            window.showCollectionCompleteModal = showCollectionCompleteModal;
            window.showDonationSuccessModal = showDonationSuccessModal;
            
            
            // Show loading when blood collection form is submitted
            document.addEventListener('submit', function(e) {
                if (e.target && e.target.id === 'bloodCollectionForm') {
                    showProcessingModal('Submitting blood collection data...');
                }
            });
            
            // Show loading for any blood collection related AJAX calls (except phlebotomist modal)
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                const url = args[0];
                if (typeof url === 'string' && url.includes('blood_collection') && !url.includes('phlebotomist_blood_collection_details')) {
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