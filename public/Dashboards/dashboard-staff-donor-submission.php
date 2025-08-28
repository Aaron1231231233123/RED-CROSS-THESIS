<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Lightweight endpoint to transition a donor from screening to physical examination review
// - If physical_examination record for donor_id exists: update needs_review=true and updated_at
// - If none exists: create with needs_review=true and updated_at
// - Always set screening_form.needs_review=false for the donor's screening
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $payload = json_decode($raw_input, true);
    // Support both JSON and form-encoded
    if (!$payload || !is_array($payload)) {
        $payload = $_POST;
    }
    if (is_array($payload) && isset($payload['action']) && $payload['action'] === 'transition_to_physical') {
        header('Content-Type: application/json');

        try {
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                exit;
            }
            if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $donor_id = intval($payload['donor_id'] ?? 0);
            if ($donor_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid donor_id']);
                exit;
            }

            // Get screening_id if provided (from screening form submission)
            $screening_id = $payload['screening_id'] ?? null;

            $now_iso = gmdate('c');

            // 1) Check if physical_examination exists
            $check_ch = curl_init();
            curl_setopt_array($check_ch, [
                CURLOPT_URL => SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $donor_id . '&limit=1',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]
            ]);
            $check_resp = curl_exec($check_ch);
            $check_http = curl_getinfo($check_ch, CURLINFO_HTTP_CODE);
            curl_close($check_ch);

            $existing_exam = ($check_http === 200) ? (json_decode($check_resp, true) ?: []) : [];
            $has_existing = is_array($existing_exam) && !empty($existing_exam);

            // 2) Insert or Update physical_examination
            if ($has_existing) {
                // UPDATE existing record - only update needs_review and updated_at
                $pe_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id);
                curl_setopt($pe_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                
                $pe_body = [
                    'needs_review' => true,
                    'updated_at' => $now_iso
                ];
                
                // Add screening_id if provided
                if ($screening_id) {
                    $pe_body['screening_id'] = $screening_id;
                }
            } else {
                // INSERT new record - include all required fields
                $pe_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
                curl_setopt($pe_ch, CURLOPT_POST, true);
                
            $pe_body = [
                'donor_id' => $donor_id,
                'needs_review' => true,
                'updated_at' => $now_iso
            ];
            
            // Add screening_id if provided
            if ($screening_id) {
                $pe_body['screening_id'] = $screening_id;
            }
            }
            curl_setopt($pe_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($pe_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            curl_setopt($pe_ch, CURLOPT_POSTFIELDS, json_encode($pe_body));
            $pe_resp = curl_exec($pe_ch);
            $pe_http = curl_getinfo($pe_ch, CURLINFO_HTTP_CODE);
            curl_close($pe_ch);

            // Log the physical examination update attempt
            error_log("Physical examination update - Donor ID: $donor_id, Has existing: " . ($has_existing ? 'true' : 'false') . ", HTTP: $pe_http, Response: $pe_resp, Body: " . json_encode($pe_body));

            // For inserts, some schemas require more fields; tolerate failure and continue as long as screening update proceeds
            $pe_ok = $has_existing ? ($pe_http >= 200 && $pe_http < 300) : ($pe_http === 201);

            // 3) Set screening_form.needs_review=false
            $sf_ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . $donor_id);
            curl_setopt($sf_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($sf_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($sf_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            $sf_body = [
                'needs_review' => false
            ];
            curl_setopt($sf_ch, CURLOPT_POSTFIELDS, json_encode($sf_body));
            $sf_resp = curl_exec($sf_ch);
            $sf_http = curl_getinfo($sf_ch, CURLINFO_HTTP_CODE);
            curl_close($sf_ch);

            if (!($sf_http >= 200 && $sf_http < 300)) {
                echo json_encode(['success' => false, 'message' => 'Failed updating screening_form', 'http' => $sf_http]);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Transitioned to physical examination review', 'physical_updated' => $pe_ok]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Set cURL timeout for faster responses
$curl_timeout = 10;
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
    curl_setopt($ch, CURLOPT_TIMEOUT, $curl_timeout);
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
    CURLOPT_TIMEOUT => $curl_timeout,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$all_donors_response = curl_exec($all_donors_ch);

$all_donor_ids = [];

if ($all_donors_response !== false) {
    $all_donors_data = json_decode($all_donors_response, true) ?: [];
    
    foreach ($all_donors_data as $donor) {
        if (isset($donor['donor_id'])) {
            $all_donor_ids[] = intval($donor['donor_id']);
        }
    }
}
curl_close($all_donors_ch);
$total_donors = count($all_donor_ids);

// --- STEP 2: Get screening forms ---
// First, get all donor IDs that have screening forms - ORIGINAL WORKING METHOD
$screening_ch = curl_init();
curl_setopt_array($screening_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=donor_form_id,screening_id,disapproval_reason,needs_review',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => $curl_timeout,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$screening_response = curl_exec($screening_ch);

// Initialize arrays
$screened_donor_ids = []; // All screened donors
$declined_donor_ids = []; // Declined donors
$screening_ids_map = []; // Map screening_id to donor_form_id
$screening_needs_review_ids = []; // Donors with screening needs_review = True

if ($screening_response !== false) {
    $screening_data = json_decode($screening_response, true) ?: [];
    
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
            
            // Track donors with screening needs_review = True
            if (isset($item['needs_review']) && $item['needs_review'] === true) {
                $screening_needs_review_ids[] = $donor_id;
            }
        }
    }
}
curl_close($screening_ch);
$declined_count = count($declined_donor_ids);

// --- STEP 3: STRICT FILTERING - Get ONLY explicitly approved medical history records ---
$medical_history_ch = curl_init();

// Ultra-strict query: ONLY get records where medical_approval is explicitly 'Approved' (excludes NULL automatically)
curl_setopt_array($medical_history_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/medical_history?select=donor_id,medical_approval&medical_approval=eq.Approved',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => $curl_timeout,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$medical_history_response = curl_exec($medical_history_ch);
curl_close($medical_history_ch);

$approved_donor_ids = [];

if ($medical_history_response !== false) {
    $medical_history_data = json_decode($medical_history_response, true);
    
    if (is_array($medical_history_data)) {
        
        foreach ($medical_history_data as $record) {
            if (isset($record['donor_id']) && isset($record['medical_approval'])) {
                // Triple-check: medical_approval must be exactly 'Approved' and not NULL
                if ($record['medical_approval'] === 'Approved' && $record['medical_approval'] !== null) {
                    $donor_id = intval($record['donor_id']);
                    
                    // Skip if donor was declined in screening
                    if (in_array($donor_id, $declined_donor_ids)) {
                        continue;
                    }
                    
                    // Add to approved list ONLY if all conditions are met
                    $approved_donor_ids[] = $donor_id;
                }
            }
        }
    }
}



// FINAL VERIFICATION: Remove duplicates and confirm approved donor list
if (!empty($approved_donor_ids)) {
    $approved_donor_ids = array_values(array_unique($approved_donor_ids));
} else {
    $approved_donor_ids = []; // Force empty array
}

// Calculate counts for the new card structure - excluding NULL medical_approval donors
$incoming_donor_ids = array_diff($all_donor_ids, $screened_donor_ids);

// Filter out donors with NULL medical_approval from registrations count
$filtered_incoming_donors = [];
foreach ($incoming_donor_ids as $donor_id) {
    // Check if this donor has NULL medical_approval
    $check_ch = curl_init();
    curl_setopt_array($check_ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/medical_history?select=medical_approval&donor_id=eq.' . $donor_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $curl_timeout,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $check_response = curl_exec($check_ch);
    curl_close($check_ch);
    
    if ($check_response !== false) {
        $medical_data = json_decode($check_response, true);
        
        // Only count if no medical history (new registration) OR if medical_approval is not NULL
        if (empty($medical_data)) {
            // No medical history = new registration, include it
            $filtered_incoming_donors[] = $donor_id;
        } elseif (!empty($medical_data)) {
            $medical_approval = $medical_data[0]['medical_approval'] ?? null;
            // Only include if medical_approval is not NULL
            if ($medical_approval !== null) {
                $filtered_incoming_donors[] = $donor_id;
            }
        }
    }
}

$registrations_count = count($filtered_incoming_donors);

// Existing donors are those who have approved medical history
$existing_donors_count = count($approved_donor_ids);

// Ineligible donors are those who have been declined in screening
$ineligible_count = count($declined_donor_ids);

// Screening review donors are those with needs_review = True in screening_form
$screening_review_count = count($screening_needs_review_ids);

// We'll calculate today's count from the actual query results later

// Add pagination settings
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Initialize the donors array
$donors = [];

// Initialize timestamp map for date display
$timestamp_map = [];

// FIFO (First In, First Out) Logic:
// - Registrations: Ordered by earliest timestamp from donor_form.submitted_at OR screening_form.updated_at OR medical_history.updated_at (mixed FIFO, includes screening exceptions)
// - Existing donors: Ordered by medical_history.updated_at.asc (oldest medical review first)
// - Ineligible donors: Ordered by medical_history.updated_at.asc (oldest medical review first)
// - Screening review: Ordered by earliest timestamp from screening_form.updated_at OR medical_history.updated_at (mixed FIFO)

// Modify your Supabase query to properly filter for unprocessed donors
$ch = curl_init();

// First, get all donor IDs that have screening forms
$screening_ch = curl_init();
curl_setopt_array($screening_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=donor_form_id',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => $curl_timeout,
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



// Now get donor forms with strict filtering based on status
// Use FIFO ordering: oldest first (submitted_at.asc) for proper queue management
$query_url = SUPABASE_URL . '/rest/v1/donor_form?select=*&order=submitted_at.asc';

// Check if we're filtering by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'registrations';
if ($status_filter === 'registrations') {
    // Show donors without screening forms (new registrations) PLUS donors with screening exceptions
    // These should be ordered by submitted_at.asc (FIFO - oldest first)
    $filtered_donor_ids = [];
    
    // Add donors without screening forms
    $donors_without_screening = array_diff($all_donor_ids, $screened_donor_ids);
    $filtered_donor_ids = array_merge($filtered_donor_ids, $donors_without_screening);
    
    // Add donors with screening exceptions (needs_review = True)
    if (!empty($screening_needs_review_ids)) {
        $filtered_donor_ids = array_merge($filtered_donor_ids, $screening_needs_review_ids);
    }
    
    // Remove duplicates
    $filtered_donor_ids = array_unique($filtered_donor_ids);
    
    // Exclude donors with medical_history where medical_approval exists and is not 'Approved'
    if (!empty($filtered_donor_ids)) {
        $filter_ids_str = implode(',', $filtered_donor_ids);
        $mh_filter_ch = curl_init();
        curl_setopt_array($mh_filter_ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/medical_history?select=donor_id,medical_approval&donor_id=in.(' . $filter_ids_str . ')',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $curl_timeout,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        $mh_filter_response = curl_exec($mh_filter_ch);
        curl_close($mh_filter_ch);
        if ($mh_filter_response !== false) {
            $mh_rows = json_decode($mh_filter_response, true) ?: [];
            $disallowed_ids = [];
            foreach ($mh_rows as $row) {
                if (!empty($row['donor_id'])) {
                    $approval = $row['medical_approval'] ?? null;
                    // Exclude if approval is anything other than 'Approved' (NULL or other values)
                    if ($approval !== 'Approved') {
                        $disallowed_ids[] = (int)$row['donor_id'];
                    }
                }
            }
            if (!empty($disallowed_ids)) {
                $filtered_donor_ids = array_values(array_diff($filtered_donor_ids, $disallowed_ids));
            }
        }
    }
    
    if (!empty($filtered_donor_ids)) {
        $filtered_ids_str = implode(',', $filtered_donor_ids);
        $query_url .= '&donor_id=in.(' . $filtered_ids_str . ')';
    } else {
        // If no donors match criteria, show empty result
        $query_url .= '&donor_id=eq.999999999';
    }
} elseif ($status_filter === 'existing') {
    // ULTIMATE STRICT FILTER: Show ONLY donors with explicitly approved medical history
    // For existing donors, order by medical_history updated_at timestamp (FIFO)
    if (!empty($approved_donor_ids)) {
        $approved_ids_str = implode(',', $approved_donor_ids);
        $query_url .= '&donor_id=in.(' . $approved_ids_str . ')';
    } else {
        // Absolutely no approved donors - force completely empty result
        $query_url .= '&donor_id=eq.999999999';
    }
} elseif ($status_filter === 'ineligible') {
    // Show only declined donors (ineligible)
    // For ineligible donors, order by medical_history updated_at timestamp (FIFO)
    if (!empty($declined_donor_ids)) {
        $declined_ids_str = implode(',', $declined_donor_ids);
        $query_url .= '&donor_id=in.(' . $declined_ids_str . ')';
    } else {
        // If no declined donors, show empty result
        $query_url .= '&donor_id=eq.999999999'; // Use extremely high number to ensure no matches
    }
} elseif ($status_filter === 'screening_review') {
    // Show only donors with screening needs_review = True
    // For screening review donors, order by screening form updated_at timestamp (FIFO)
    if (!empty($screening_needs_review_ids)) {
        $screening_review_ids_str = implode(',', $screening_needs_review_ids);
        $query_url .= '&donor_id=in.(' . $screening_review_ids_str . ')';
    } else {
        // If no donors need screening review, show empty result
        $query_url .= '&donor_id=eq.999999999';
    }
}

curl_setopt_array($ch, [
    CURLOPT_URL => $query_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => $curl_timeout,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$response = curl_exec($ch);



// Check if the response is valid JSON
if ($response === false || is_null(json_decode($response, true))) {
    error_log("Error fetching data from Supabase: " . curl_error($ch));
    $donors = [];
} else {
    $donors = json_decode($response, true) ?: [];
    
    // Apply FIFO ordering for registrations (including screening exceptions)
    if (!empty($donors) && $status_filter === 'registrations') {
        // Get both screening form and medical history timestamps for mixed FIFO ordering
        $donor_ids = array_column($donors, 'donor_id');
        $donor_ids_str = implode(',', $donor_ids);
        
        // Get screening form timestamps (only for donors with needs_review = True)
        $screening_timestamps_ch = curl_init();
        curl_setopt_array($screening_timestamps_ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=donor_form_id,updated_at,needs_review&donor_form_id=in.(' . $donor_ids_str . ')&needs_review=eq.true&order=updated_at.asc',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        
        $screening_timestamps_response = curl_exec($screening_timestamps_ch);
        curl_close($screening_timestamps_ch);
        
        // Get medical history timestamps
        $medical_timestamps_ch = curl_init();
        curl_setopt_array($medical_timestamps_ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/medical_history?select=donor_id,updated_at&donor_id=in.(' . $donor_ids_str . ')&order=updated_at.asc',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY
                    ]
                ]);
                
        $medical_timestamps_response = curl_exec($medical_timestamps_ch);
        curl_close($medical_timestamps_ch);
        
        if ($screening_timestamps_response !== false && $medical_timestamps_response !== false) {
            $screening_timestamps = json_decode($screening_timestamps_response, true) ?: [];
            $medical_timestamps = json_decode($medical_timestamps_response, true) ?: [];
            
            // Create a mapping of donor_id to the earliest timestamp from either table
            $timestamp_map = [];
            
            // Process screening form timestamps (only for needs_review = True)
            foreach ($screening_timestamps as $screening) {
                if (isset($screening['donor_form_id']) && isset($screening['updated_at']) && isset($screening['needs_review']) && $screening['needs_review'] === true) {
                    $donor_id = $screening['donor_form_id'];
                    $screening_timestamp = $screening['updated_at'];
                    
                    // If we don't have a timestamp for this donor yet, or if screening timestamp is earlier
                    if (!isset($timestamp_map[$donor_id]) || strtotime($screening_timestamp) < strtotime($timestamp_map[$donor_id])) {
                        $timestamp_map[$donor_id] = $screening_timestamp;
                    }
                }
            }
            
            // Process medical history timestamps
            foreach ($medical_timestamps as $medical) {
                if (isset($medical['donor_id']) && isset($medical['updated_at'])) {
                    $donor_id = $medical['donor_id'];
                    $medical_timestamp = $medical['updated_at'];
                    
                    // If we don't have a timestamp for this donor yet, or if medical timestamp is earlier
                    if (!isset($timestamp_map[$donor_id]) || strtotime($medical_timestamp) < strtotime($timestamp_map[$donor_id])) {
                        $timestamp_map[$donor_id] = $medical_timestamp;
                    }
                }
            }
            
            // Sort donors based on the earliest timestamp from either table (FIFO)
            usort($donors, function($a, $b) use ($timestamp_map) {
                $a_timestamp = $timestamp_map[$a['donor_id']] ?? $a['submitted_at'];
                $b_timestamp = $timestamp_map[$b['donor_id']] ?? $b['submitted_at'];
                return strtotime($a_timestamp) - strtotime($b_timestamp);
            });
            
        }
    }
    
    // Apply FIFO ordering based on medical_history updated_at timestamps for existing and ineligible donors
    if (!empty($donors) && ($status_filter === 'existing' || $status_filter === 'ineligible')) {
        // Get medical history timestamps for proper FIFO ordering
        $donor_ids = array_column($donors, 'donor_id');
        $donor_ids_str = implode(',', $donor_ids);
        
        $medical_timestamps_ch = curl_init();
        curl_setopt_array($medical_timestamps_ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/medical_history?select=donor_id,updated_at&donor_id=in.(' . $donor_ids_str . ')&order=updated_at.asc',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        
        $medical_timestamps_response = curl_exec($medical_timestamps_ch);
        curl_close($medical_timestamps_ch);
        
        if ($medical_timestamps_response !== false) {
            $medical_timestamps = json_decode($medical_timestamps_response, true) ?: [];
            
            // Create a mapping of donor_id to updated_at timestamp
            // Use global timestamp_map for date display
            foreach ($medical_timestamps as $medical) {
                if (isset($medical['donor_id']) && isset($medical['updated_at'])) {
                    $timestamp_map[$medical['donor_id']] = $medical['updated_at'];
                }
            }
            
            // Sort donors based on medical_history updated_at timestamp (FIFO)
            usort($donors, function($a, $b) use ($timestamp_map) {
                $a_timestamp = $timestamp_map[$a['donor_id']] ?? $a['submitted_at'];
                $b_timestamp = $timestamp_map[$b['donor_id']] ?? $b['submitted_at'];
                return strtotime($a_timestamp) - strtotime($b_timestamp);
            });
            
        }
    }
    
    // Apply FIFO ordering based on mixed timestamps (screening_form + medical_history) for screening review donors
    if (!empty($donors) && $status_filter === 'screening_review') {
        // Get both screening form and medical history timestamps for mixed FIFO ordering
        $donor_ids = array_column($donors, 'donor_id');
        $donor_ids_str = implode(',', $donor_ids);
        
        // Get screening form timestamps (only for donors with needs_review = True)
        $screening_timestamps_ch = curl_init();
        curl_setopt_array($screening_timestamps_ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=donor_form_id,updated_at,needs_review&donor_form_id=in.(' . $donor_ids_str . ')&needs_review=eq.true&order=updated_at.asc',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        
        $screening_timestamps_response = curl_exec($screening_timestamps_ch);
        curl_close($screening_timestamps_ch);
        
        // Get medical history timestamps
        $medical_timestamps_ch = curl_init();
        curl_setopt_array($medical_timestamps_ch, [
            CURLOPT_URL => SUPABASE_URL . '/rest/v1/medical_history?select=donor_id,updated_at&donor_id=in.(' . $donor_ids_str . ')&order=updated_at.asc',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        
        $medical_timestamps_response = curl_exec($medical_timestamps_ch);
        curl_close($medical_timestamps_ch);
        
        if ($screening_timestamps_response !== false && $medical_timestamps_response !== false) {
            $screening_timestamps = json_decode($screening_timestamps_response, true) ?: [];
            $medical_timestamps = json_decode($medical_timestamps_response, true) ?: [];
            
            // Create a mapping of donor_id to the earliest timestamp from either table
            $timestamp_map = [];
            
            // Process screening form timestamps (only for needs_review = True)
            foreach ($screening_timestamps as $screening) {
                if (isset($screening['donor_form_id']) && isset($screening['updated_at']) && isset($screening['needs_review']) && $screening['needs_review'] === true) {
                    $donor_id = $screening['donor_form_id'];
                    $screening_timestamp = $screening['updated_at'];
                    
                    // If we don't have a timestamp for this donor yet, or if screening timestamp is earlier
                    if (!isset($timestamp_map[$donor_id]) || strtotime($screening_timestamp) < strtotime($timestamp_map[$donor_id])) {
                        $timestamp_map[$donor_id] = $screening_timestamp;
                    }
                }
            }
            
            // Process medical history timestamps
            foreach ($medical_timestamps as $medical) {
                if (isset($medical['donor_id']) && isset($medical['updated_at'])) {
                    $donor_id = $medical['donor_id'];
                    $medical_timestamp = $medical['updated_at'];
                    
                    // If we don't have a timestamp for this donor yet, or if medical timestamp is earlier
                    if (!isset($timestamp_map[$donor_id]) || strtotime($medical_timestamp) < strtotime($timestamp_map[$donor_id])) {
                        $timestamp_map[$donor_id] = $medical_timestamp;
                    }
                }
            }
            
            // Sort donors based on the earliest timestamp from either table (FIFO)
            usort($donors, function($a, $b) use ($timestamp_map) {
                $a_timestamp = $timestamp_map[$a['donor_id']] ?? $a['submitted_at'];
                $b_timestamp = $timestamp_map[$b['donor_id']] ?? $b['submitted_at'];
                return strtotime($a_timestamp) - strtotime($b_timestamp);
            });
            
        }
    }
    

    
    // Note: Medical approval filtering is already handled in the query logic above
    // No need for additional filtering here as the data is already properly filtered
}

$total_records = count($donors);
$total_pages = ceil($total_records / $records_per_page);

// Slice the array to get only the records for the current page
// FIFO ordering is preserved through array_slice
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
    <script src="../../assets/js/staff_donor_modal.js"></script>
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

/* New Donation History Grid Layout */
.screening-history-grid {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
}

.screening-history-header {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    background: #b22222;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.screening-history-header-text {
    padding: 12px 16px;
    text-align: left;
}

.screening-history-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    border-bottom: 1px solid #e9ecef;
}

.screening-history-row:last-child {
    border-bottom: none;
}

.screening-history-label {
    background: #f8f9fa;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    font-weight: 500;
    color: #495057;
    border-right: 1px solid #e9ecef;
}

.screening-history-column {
    padding: 8px 16px;
    display: flex;
    align-items: center;
}

.screening-history-input {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.screening-history-input:focus {
    border-color: #b22222;
    box-shadow: 0 0 0 2px rgba(178, 34, 34, 0.1);
    outline: none;
}

.screening-history-input::placeholder {
    color: #6c757d;
    font-style: italic;
}

/* Ensure input groups work properly in the grid */
.screening-history-column .screening-input-group {
    width: 100%;
    position: relative;
}

.screening-history-column .screening-input-group .screening-input {
    padding-right: 35px;
}

.screening-history-column .screening-input-icon {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    pointer-events: none;
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

        /* Global Button Styling */
        .btn {
            border-radius: 4px !important;
        }

        /* Donor Modal Design - Matching Image Style */
        .donor-header-section {
            margin-bottom: 2rem;
        }

        .donor-header-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid #b22222;
            position: relative;
        }

        .donor-header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .donor-name-section {
            flex: 1;
        }

        .donor-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.75rem;
            margin: 0 0 0.75rem 0;
        }

        .donor-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .donor-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
            color: #495057;
        }

        .donor-badge.blood-badge {
            background: #b22222;
            color: white;
            border-color: #b22222;
        }

        .donor-date-section {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .donor-content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .donor-content-column {
            display: flex;
            flex-direction: column;
        }

        .donor-section-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            height: 100%;
        }

        .donor-section-header {
            display: flex;
            align-items: center;
            font-size: 1rem;
            font-weight: 700;
            color: #b22222;
            margin-bottom: 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f3f4;
        }

        .donor-section-header i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }

        .donor-field-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .donor-field-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f8f9fa;
        }

        .donor-field-item:last-child {
            border-bottom: none;
        }

        .donor-field-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .donor-field-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: #333;
            text-align: right;
        }

        .donor-address-field {
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            min-height: 60px;
            display: flex;
            align-items: center;
        }

        .donor-address-field .donor-field-value {
            text-align: left;
            width: 100%;
        }

        .donor-additional-info {
            transition: all 0.3s ease;
        }

        #toggleAdditionalInfo {
            border: 1px solid #dee2e6;
            background: white;
            color: #6c757d;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        #toggleAdditionalInfo:hover {
            background: #f8f9fa;
            border-color: #b22222;
            color: #b22222;
        }

        #toggleAdditionalInfo.expanded i {
            transform: rotate(180deg);
        }

        /* Responsive adjustments for donor modal */
        @media (max-width: 768px) {
            .donor-header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .donor-content-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .donor-badges {
                justify-content: flex-start;
            }

            .donor-field-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .donor-field-value {
                text-align: left;
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
                        <a class="nav-link" href="dashboard-staff-history/dashboard-staff-history.php">
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
                        <h2 class="welcome-title">Welcome, Staff!</h2>
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
                        <a href="?status=screening_review" class="status-card <?php echo (isset($_GET['status']) && $_GET['status'] === 'screening_review') ? 'active' : ''; ?>">
                            <p class="dashboard-staff-count"><?php echo $screening_review_count; ?></p>
                            <p class="dashboard-staff-title">Screening Review</p>
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
                                                // Get the most relevant date based on donor status and available timestamps
                                                $date_to_show = '';
                                                $donor_id = $donor['donor_id'];
                                                
                                                // Use the timestamp map from FIFO ordering if available
                                                if (isset($timestamp_map[$donor_id])) {
                                                    $date_to_show = date('F j, Y', strtotime($timestamp_map[$donor_id]));
                                                } else {
                                                    // Fallback to donor form submitted_at
                                                if (!empty($donor['submitted_at'])) {
                                                    $date_to_show = date('F j, Y', strtotime($donor['submitted_at']));
                                                } elseif (!empty($donor['created_at'])) {
                                                    $date_to_show = date('F j, Y', strtotime($donor['created_at']));
                                                } elseif (!empty($donor['start_date'])) {
                                                    $date_to_show = date('F j, Y', strtotime($donor['start_date']));
                                                } else {
                                                    $date_to_show = 'N/A';
                                                }
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
                                                <button type="button" class="btn btn-info btn-sm view-donor-btn" 
                                                        data-donor-id="<?php echo $donor['donor_id']; ?>" 
                                                        data-donor='<?php echo $encoded_data; ?>' 
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
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
    <!-- Include Donor Details Modal -->
    <?php include '../../src/views/forms/staff_donor_modal_content.php'; ?>

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
                    <p class="text-white mt-3 mb-0" style="font-size: 1.1rem;">Please wait...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Screening Form Modal -->
    <?php include '../../src/views/forms/staff_donor_initial_screening_form_modal.php'; ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            const donorTableBody = document.getElementById('donorTableBody');
            
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