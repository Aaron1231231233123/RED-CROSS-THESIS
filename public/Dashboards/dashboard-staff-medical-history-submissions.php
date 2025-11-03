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
                    'updated_at' => $now_iso,
                    // Ensure remarks reflect review status
                    'remarks' => 'Pending'
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
                    'updated_at' => $now_iso,
                    // New record starts with Pending remarks
                    'remarks' => 'Pending'
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

            // 3) Set medical_history.needs_review=false
            // Update medical_history approval. Prefer targeting a specific row if provided
            $mh_target = null;
            if (!empty($payload['medical_history_id'])) {
                $mh_target = SUPABASE_URL . '/rest/v1/medical_history?medical_history_id=eq.' . urlencode($payload['medical_history_id']);
            } else {
                $mh_target = SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id;
            }
            $mh_ch = curl_init($mh_target);
            curl_setopt($mh_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($mh_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($mh_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            $mh_body = [
                'needs_review' => false
            ];
            curl_setopt($mh_ch, CURLOPT_POSTFIELDS, json_encode($mh_body));
            $mh_resp = curl_exec($mh_ch);
            $mh_http = curl_getinfo($mh_ch, CURLINFO_HTTP_CODE);
            curl_close($mh_ch);

            if (!($mh_http >= 200 && $mh_http < 300)) {
                echo json_encode(['success' => false, 'message' => 'Failed updating medical_history', 'http' => $mh_http]);
                exit;
            }

            // 4) Set screening_form.needs_review=false
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

            echo json_encode(['success' => true, 'message' => 'Transitioned to physical examination review - Medical history and screening form updated', 'physical_updated' => $pe_ok]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    
    // Upsert physical_examination from Screening Defer modal
    if (is_array($payload) && isset($payload['action']) && $payload['action'] === 'upsert_physical_from_defer') {
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

            // Accept either key from client payload
            $physical_examination_id = $payload['physical_examination_id'] ?? ($payload['physical_exam_id'] ?? null); // optional

            // Build body from provided payload, excluding control keys
            $control_keys = [
                'action', 'donor_id', 'physical_examination_id', 'physical_exam_id'
            ];
            $pe_body = [];
            foreach ($payload as $k => $v) {
                if (!in_array($k, $control_keys, true)) {
                    $pe_body[$k] = $v;
                }
            }

            // Ensure donor_id is present for inserts
            $now_iso = gmdate('c');
            $pe_body['updated_at'] = $now_iso;

            $ch = null;
            $method = 'PATCH';
            $expected_created = false;

            $attempted_patch_by_donor = false;
            if (!empty($physical_examination_id)) {
                // Update by explicit physical_examination_id
                $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . urlencode($physical_examination_id));
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            } else {
                // Check if record exists for donor_id
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

                // Treat 200 and 206 (partial) as success
                $existing = (($check_http === 200 || $check_http === 206) ? (json_decode($check_resp, true) ?: []) : []);
                $has_existing = is_array($existing) && !empty($existing);

                if ($has_existing) {
                    // Update existing by donor_id
                    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    $attempted_patch_by_donor = true;
                } else {
                    // Insert new
                    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
                    curl_setopt($ch, CURLOPT_POST, true);
                    $pe_body['donor_id'] = $donor_id;
                    $method = 'POST';
                    $expected_created = true;
                }
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $headers = [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                // Use representation to capture created/updated ids when needed
                'Prefer: return=representation'
            ];
            // If posting, enable merge duplicates by primary key to force update when conflicting id sent accidentally
            if ($method === 'POST' && !empty($physical_examination_id)) {
                // If client sent an id in body, let PostgREST upsert by conflict key
                $pe_body['physical_exam_id'] = $physical_examination_id;
                $headers[] = 'Prefer: resolution=merge-duplicates';
                // Also pass on_conflict to API query string when possible
                $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                if ($url) {
                    $url .= (strpos($url, '?') === false ? '?' : '&') . 'on_conflict=physical_exam_id';
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pe_body));
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Log attempt for debugging
            error_log("[DEFER_UPSERT] donor_id=$donor_id method=$method http=$http body=" . json_encode($pe_body) . " resp=$resp");

            $ok = ($expected_created ? ($http === 201) : ($http >= 200 && $http < 300));
            if (!$ok) {
                echo json_encode(['success' => false, 'message' => 'Upsert failed', 'http' => $http, 'response' => $resp]);
                exit;
            }

            $data = json_decode($resp, true);
            $returned = is_array($data) ? (isset($data[0]) ? $data[0] : $data) : null;

            // Fallback: If we PATCHed by donor_id but no rows were affected, perform an INSERT instead
            if ($method === 'PATCH' && $attempted_patch_by_donor && (empty($data) || (is_array($data) && isset($data['hint']) && strpos(strtolower($data['hint']), 'no rows') !== false))) {
                $ins = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
                curl_setopt($ins, CURLOPT_POST, true);
                $pe_body_ins = $pe_body;
                $pe_body_ins['donor_id'] = $donor_id;
                curl_setopt($ins, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ins, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ]);
                curl_setopt($ins, CURLOPT_POSTFIELDS, json_encode($pe_body_ins));
                $ins_resp = curl_exec($ins);
                $ins_http = curl_getinfo($ins, CURLINFO_HTTP_CODE);
                curl_close($ins);
                if ($ins_http === 201) {
                    $returned = (json_decode($ins_resp, true) ?: null);
                    if (is_array($returned) && isset($returned[0])) $returned = $returned[0];
                }
            }
            
            // After successful upsert, set medical_history.medical_approval to Not Approved
            // Prefer updating a specific row if medical_history_id is provided
            $mh_url = null;
            if (!empty($payload['medical_history_id'])) {
                $mh_url = SUPABASE_URL . '/rest/v1/medical_history?medical_history_id=eq.' . urlencode($payload['medical_history_id']);
            } else {
                $mh_url = SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id;
            }
            $mh_ch = curl_init($mh_url);
            curl_setopt($mh_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($mh_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($mh_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            $mh_body = [
                'medical_approval' => 'Not Approved',
                'needs_review' => true
            ];
            curl_setopt($mh_ch, CURLOPT_POSTFIELDS, json_encode($mh_body));
            $mh_resp = curl_exec($mh_ch);
            $mh_http = curl_getinfo($mh_ch, CURLINFO_HTTP_CODE);
            curl_close($mh_ch);

            $mh_ok = ($mh_http >= 200 && $mh_http < 300);
            if (!$mh_ok) {
                error_log('[DEFER_UPSERT] medical_history update failed donor_id=' . $donor_id . ' http=' . $mh_http . ' resp=' . $mh_resp);
            }

            echo json_encode(['success' => true, 'message' => 'Upsert successful', 'data' => $returned, 'medical_updated' => $mh_ok]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Note: generateSecureToken and hashDonorId functions removed as they are unused
// These functions were defined but never called in this file

// Helper function to determine donor type based on eligibility and table IDs
function getDonorType($donor_id, $medical_info, $eligibility_by_donor, $stage = 'medical_review', $screening_info = null, $physical_info = null) {
    // Check if donor has eligibility record (Returning donor)
    $has_eligibility = isset($eligibility_by_donor[$donor_id]);
    
    if ($has_eligibility) {
        // RETURNING DONOR LOGIC - Based on needs_review status
        $needs_review_stage = '';
        
        // Check needs_review status from different tables (highest priority first)
        if ($physical_info && isset($physical_info['needs_review']) && $physical_info['needs_review'] === true) {
            $needs_review_stage = 'Physical';
        }
        elseif ($screening_info && isset($screening_info['needs_review']) && $screening_info['needs_review'] === true) {
            $needs_review_stage = 'Screening';
        }
        elseif ($medical_info && isset($medical_info['needs_review']) && $medical_info['needs_review'] === true) {
            $needs_review_stage = 'Medical';
        }
        else {
            // If no needs_review is true, determine stage based on current stage
    switch ($stage) {
        case 'blood_collection':
                    $needs_review_stage = 'Collection';
            break;
        case 'physical_examination':
                    $needs_review_stage = 'Physical';
            break;
        case 'screening_form':
                    $needs_review_stage = 'Screening';
            break;
        case 'medical_review':
                    $needs_review_stage = 'Medical';
            break;
        default:
                    $needs_review_stage = 'Medical';
            }
    }
    
        return 'Returning';
    } else {
        // NEW DONOR LOGIC - Based on medical_approval and table IDs
        $stage_name = '';
        
        // Check which tables have IDs (highest priority first)
        if (isset($physical_info['physical_examination_id']) && $physical_info['physical_examination_id']) {
            $stage_name = 'Collection';
        }
        elseif (isset($screening_info['screening_id']) && $screening_info['screening_id']) {
            $stage_name = 'Physical';
        }
        elseif (isset($medical_info['medical_history_id']) && $medical_info['medical_history_id']) {
            // Check if medical_approval is "Approved"
            if (isset($medical_info['medical_approval']) && $medical_info['medical_approval'] === 'Approved') {
                $stage_name = 'Screening';
            } else {
                $stage_name = 'Medical';
            }
        }
        else {
            $stage_name = 'Medical'; // Default for new donors (not approved yet)
        }
        
        return 'New';
    }
}

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

// OPTIMIZED APPROACH: Parallel cURL requests and selective data fetching
$multi_handle = curl_multi_init();
$curl_handles = [];

// Define the queries with optimized field selection and pagination to bypass 1000 record limit
$queries = [
    'donor_forms' => '/rest/v1/donor_form?select=donor_id,surname,first_name,submitted_at,registration_channel,prc_donor_number&order=submitted_at.desc&limit=5000',
    // include needs_review flag and updated_at to prioritize and display review time
    'medical_histories' => '/rest/v1/medical_history?select=donor_id,medical_history_id,medical_approval,needs_review,created_at,updated_at&order=created_at.desc&limit=5000',
    'screening_forms' => '/rest/v1/screening_form?select=screening_id,donor_form_id,interviewer_id,needs_review,created_at&order=created_at.desc&limit=5000',
    'physical_exams' => '/rest/v1/physical_examination?select=donor_id,needs_review,created_at&order=created_at.desc&limit=5000',
    'blood_collections' => '/rest/v1/blood_collection?select=screening_id,start_time&order=start_time.desc&limit=5000',
    'eligibility_records' => '/rest/v1/eligibility?select=donor_id,status&order=created_at.desc&limit=5000'
];

// Create parallel cURL requests
foreach ($queries as $key => $query) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . $query,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    $curl_handles[$key] = $ch;
    curl_multi_add_handle($multi_handle, $ch);
}

// Execute all requests in parallel
$running = null;
do {
    curl_multi_exec($multi_handle, $running);
    curl_multi_select($multi_handle);
} while ($running > 0);

// Get results
$donor_forms = json_decode(curl_multi_getcontent($curl_handles['donor_forms']), true) ?: [];
$medical_histories = json_decode(curl_multi_getcontent($curl_handles['medical_histories']), true) ?: [];
$screening_forms = json_decode(curl_multi_getcontent($curl_handles['screening_forms']), true) ?: [];
$physical_exams = json_decode(curl_multi_getcontent($curl_handles['physical_exams']), true) ?: [];
$blood_collections = json_decode(curl_multi_getcontent($curl_handles['blood_collections']), true) ?: [];
$eligibility_records = json_decode(curl_multi_getcontent($curl_handles['eligibility_records']), true) ?: [];

// Debug: Log the number of records fetched
error_log("Medical History Dashboard - Records fetched:");
error_log("Donor forms: " . count($donor_forms));
error_log("Medical histories: " . count($medical_histories));
error_log("Screening forms: " . count($screening_forms));
error_log("Physical exams: " . count($physical_exams));
error_log("Blood collections: " . count($blood_collections));
error_log("Eligibility records: " . count($eligibility_records));

// Fetch interviewer information from users table
$interviewer_ids = [];
foreach ($screening_forms as $screening) {
    if (!empty($screening['interviewer_id'])) {
        $interviewer_ids[] = $screening['interviewer_id'];
    }
}

// Remove duplicates and fetch unique interviewer names
$interviewer_ids = array_unique($interviewer_ids);
$interviewer_names = [];

if (!empty($interviewer_ids)) {
    $interviewer_ids_str = implode(',', $interviewer_ids);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/users?user_id=in.($interviewer_ids_str)&select=user_id,first_name,surname,middle_name",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $interviewer_response = curl_exec($ch);
    $interviewer_err = curl_error($ch);
    curl_close($ch);
    
    if (!$interviewer_err) {
        $interviewer_data = json_decode($interviewer_response, true);
        if (is_array($interviewer_data)) {
            foreach ($interviewer_data as $interviewer) {
                $user_id = $interviewer['user_id'];
                $first_name = $interviewer['first_name'] ?? '';
                $surname = $interviewer['surname'] ?? '';
                $middle_name = $interviewer['middle_name'] ?? '';
                
                // Format name as "Surname, First Name Middle Name"
                $full_name = trim($surname . ', ' . $first_name . ' ' . $middle_name);
                $interviewer_names[$user_id] = $full_name;
            }
        }
    }
}

// Clean up
foreach ($curl_handles as $ch) {
    curl_multi_remove_handle($multi_handle, $ch);
    curl_close($ch);
}
curl_multi_close($multi_handle);

// Create optimized lookup arrays with pre-allocated size
$donors_by_id = array_column($donor_forms, null, 'donor_id');
$medical_by_donor = array_column($medical_histories, null, 'donor_id');
$screenings_by_donor = array_column($screening_forms, null, 'donor_form_id');
$physicals_by_donor = array_column($physical_exams, null, 'donor_id');
$blood_by_screening = array_column($blood_collections, null, 'screening_id');

// Create interviewer lookup array
$interviewer_by_donor = [];
foreach ($screening_forms as $screening) {
    $donor_id = $screening['donor_form_id'] ?? null;
    $interviewer_id = $screening['interviewer_id'] ?? null;
    
    if ($donor_id && $interviewer_id && isset($interviewer_names[$interviewer_id])) {
        $interviewer_by_donor[$donor_id] = $interviewer_names[$interviewer_id];
    }
}

// Fallback: For donors without screening forms, try to get interviewer from current session
if (isset($_SESSION['user_id'])) {
    // If current user is not in interviewer_names, fetch their info
    if (!isset($interviewer_names[$_SESSION['user_id']])) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/users?user_id=eq." . $_SESSION['user_id'] . "&select=user_id,first_name,surname,middle_name",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]
        ]);
        
        $user_response = curl_exec($ch);
        $user_err = curl_error($ch);
        curl_close($ch);
        
        if (!$user_err) {
            $user_data = json_decode($user_response, true);
            if (is_array($user_data) && !empty($user_data)) {
                $user = $user_data[0];
                $first_name = $user['first_name'] ?? '';
                $surname = $user['surname'] ?? '';
                $middle_name = $user['middle_name'] ?? '';
                $full_name = trim($surname . ', ' . $first_name . ' ' . $middle_name);
                $interviewer_names[$_SESSION['user_id']] = $full_name;
            }
        }
    }
    
    $current_user_name = $interviewer_names[$_SESSION['user_id']] ?? 'Current User';
    // For donors in medical review stage who don't have screening forms yet
    foreach ($donor_forms as $donor_info) {
        $donor_id = $donor_info['donor_id'];
        if (!isset($interviewer_by_donor[$donor_id])) {
            // Only set for donors in medical review stage (new donors)
            if (!isset($eligibility_by_donor[$donor_id])) {
                $interviewer_by_donor[$donor_id] = $current_user_name;
            }
        }
    }
}

// Debug: Log interviewer data
error_log("Interviewer names fetched: " . json_encode($interviewer_names));
error_log("Interviewer by donor mapping: " . json_encode($interviewer_by_donor));
error_log("Sample screening form: " . json_encode($screening_forms[0] ?? 'No screening forms'));
error_log("Sample donor form: " . json_encode($donor_forms[0] ?? 'No donor forms'));
// Build eligibility_by_donor using the most recent row per donor_id (records are ordered desc)
$eligibility_by_donor = [];
foreach ($eligibility_records as $row) {
    $did = $row['donor_id'] ?? null;
    if ($did === null) continue;
    // Because list is desc by created_at, keep the first seen (newest) and ignore older duplicates
    if (!isset($eligibility_by_donor[$did])) {
        $eligibility_by_donor[$did] = $row;
    }
}

// Optional debug hook: pass ?debug_donor_id=123 to verify eligibility presence for a specific donor
if (isset($_GET['debug_donor_id'])) {
    $dbg_id = intval($_GET['debug_donor_id']);
    $dbg_row = $eligibility_by_donor[$dbg_id] ?? null;
    error_log('[ELIG_DEBUG] donor_id=' . $dbg_id . ' present=' . ($dbg_row ? 'yes' : 'no') . ' row=' . json_encode($dbg_row));
}

// Create sets to track donors already processed at higher priority levels
$donors_with_blood = [];
$donors_with_physical = [];
$donors_with_screening = [];
$donors_with_review = [];

// Process the donor history with OPTIMIZED HIERARCHY PRIORITY
$donor_history = [];
$counter = 1;

// FILTER: Only process Medical Review stage donors (New Medical and Returning Medical)
// Skip Blood Collections, Physical Examinations, and Screening Forms for New donors

// ABSOLUTE PRIORITY: Any donor with needs_review=true must be shown, regardless of next-stage IDs
foreach ($medical_by_donor as $rev_donor_id => $rev_medical) {
    if (!isset($rev_medical['needs_review']) || $rev_medical['needs_review'] !== true) continue;
    $donor_info = $donors_by_id[$rev_donor_id] ?? null;
    if (!$donor_info) continue;
    $donors_with_review[$rev_donor_id] = true;
    // compute donor type based on eligibility presence
    $donor_type_label = getDonorType($rev_donor_id, $rev_medical, $eligibility_by_donor, 'medical_review');
    // Build status from eligibility (latest row)
    $status = '-';
    $elig = $eligibility_by_donor[$rev_donor_id] ?? null;
    if ($elig && isset($elig['status'])) {
        $st = strtolower($elig['status']);
        if ($st === 'approved') $status = 'Eligible';
        elseif ($st === 'temporary deferred') $status = 'Deferred';
        elseif ($st === 'permanently deferred') $status = 'Ineligible';
        elseif ($st === 'refused') $status = 'Deferred';
    }
    $donor_history[] = [
        'no' => $counter++,
        'date' => ($rev_medical['updated_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
        'interviewer' => $interviewer_by_donor[$rev_donor_id] ?? 'N/A',
        'donor_type' => $donor_type_label,
        'status' => $status,
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
        'donor_id' => $rev_donor_id,
        'stage' => 'medical_review',
        'medical_history_id' => $rev_medical['medical_history_id'] ?? null
    ];
}

// PRIORITY 1: Process Blood Collections (but skip those already marked needs_review)
foreach ($blood_collections as $blood_info) {
    $screening_id = $blood_info['screening_id'];
    $screening_info = $screenings_by_donor[$screening_id] ?? null;
    if (!$screening_info) continue;
    
    $donor_info = $donors_by_id[$screening_info['donor_form_id']] ?? null;
    if (!$donor_info) continue;
    
    $donor_id = $donor_info['donor_id'];
    if (isset($donors_with_review[$donor_id])) continue;
    $donors_with_blood[$donor_id] = true;
    
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    $donor_type_label = getDonorType($donor_id, $medical_info, $eligibility_by_donor, 'blood_collection');
    
    // Only include if it's a Returning donor (has eligibility record)
    if (isset($eligibility_by_donor[$donor_id])) {
            $elig = $eligibility_by_donor[$donor_id] ?? null;
            $status = '-';
            if ($elig && isset($elig['status'])) {
                $st = strtolower($elig['status']);
                if ($st === 'approved') $status = 'Eligible';
                elseif ($st === 'temporary deferred') $status = 'Deferred';
                elseif ($st === 'permanently deferred') $status = 'Ineligible';
                elseif ($st === 'refused') $status = 'Deferred';
            }
            $donor_history[] = [
        'no' => $counter++,
        'date' => ($medical_info['updated_at'] ?? null) ?: ($blood_info['start_time'] ?? $screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
        'surname' => $donor_info['surname'] ?? 'N/A',
        'first_name' => $donor_info['first_name'] ?? 'N/A',
        'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
        'interviewer' => $interviewer_by_donor[$donor_id] ?? 'N/A',
        'donor_type' => $donor_type_label,
        'status' => $status,
        'status' => $status,
        'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
        'donor_id' => $donor_id,
        'stage' => 'blood_collection',
        'medical_history_id' => $medical_info['medical_history_id'] ?? null
    ];
    }
}

// PRIORITY 2: Process Physical Examinations (Medium Priority) - Only for Returning donors
foreach ($physical_exams as $physical_info) {
    $donor_id = $physical_info['donor_id'];
    if (isset($donors_with_review[$donor_id]) || isset($donors_with_blood[$donor_id])) continue;
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) continue;
    
    $donors_with_physical[$donor_id] = true;
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    // compute donor_type_label safely
    $donor_type_label = getDonorType($donor_id, $medical_info, $eligibility_by_donor, 'physical_examination', null, $physical_info);
    
    // Only include if it's a Returning donor (has eligibility record)
    if (isset($eligibility_by_donor[$donor_id])) {
        $elig = $eligibility_by_donor[$donor_id] ?? null;
        $status = '-';
        if ($elig && isset($elig['status'])) {
            $st = strtolower($elig['status']);
            if ($st === 'approved') $status = 'Eligible';
            elseif ($st === 'temporary deferred') $status = 'Deferred';
            elseif ($st === 'permanently deferred') $status = 'Ineligible';
            elseif ($st === 'refused') $status = 'Deferred';
        }
        $donor_history[] = [
            'no' => $counter++,
            'date' => ($medical_info['updated_at'] ?? null) ?: ($physical_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
            'surname' => $donor_info['surname'] ?? 'N/A',
            'first_name' => $donor_info['first_name'] ?? 'N/A',
            'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
            'interviewer' => $interviewer_by_donor[$donor_id] ?? 'N/A',
            'donor_type' => $donor_type_label,
            'status' => $status,
            'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
            'donor_id' => $donor_id,
            'stage' => 'physical_examination',
            'medical_history_id' => $medical_info['medical_history_id'] ?? null
        ];
    }
}

// PRIORITY 3: Process Screening Forms (Low Priority) - Only for Returning donors
foreach ($screening_forms as $screening_info) {
    $donor_id = $screening_info['donor_form_id'];
    if (isset($donors_with_review[$donor_id]) || isset($donors_with_blood[$donor_id]) || isset($donors_with_physical[$donor_id])) continue;
    
    $donor_info = $donors_by_id[$donor_id] ?? null;
    if (!$donor_info) continue;
    
    $donors_with_screening[$donor_id] = true;
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    // compute donor_type_label safely
    $donor_type_label = getDonorType($donor_id, $medical_info, $eligibility_by_donor, 'screening_form', $screening_info);
    
    // Only include if it's a Returning donor (has eligibility record)
    if (isset($eligibility_by_donor[$donor_id])) {
        $elig = $eligibility_by_donor[$donor_id] ?? null;
        $status = '-';
        if ($elig && isset($elig['status'])) {
            $st = strtolower($elig['status']);
            if ($st === 'approved') $status = 'Eligible';
            elseif ($st === 'temporary deferred') $status = 'Deferred';
            elseif ($st === 'permanently deferred') $status = 'Ineligible';
            elseif ($st === 'refused') $status = 'Deferred';
        }
        $donor_history[] = [
            'no' => $counter++,
            'date' => ($medical_info['updated_at'] ?? null) ?: ($screening_info['created_at'] ?? $donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
            'surname' => $donor_info['surname'] ?? 'N/A',
            'first_name' => $donor_info['first_name'] ?? 'N/A',
            'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
            'interviewer' => $interviewer_by_donor[$donor_id] ?? 'N/A',
            'donor_type' => $donor_type_label,
            'status' => $status,
            'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
            'donor_id' => $donor_id,
            'stage' => 'screening_form',
            'medical_history_id' => $medical_info['medical_history_id'] ?? null
        ];
    }
}
// PRIORITY 4: Process Donor Forms with ONLY registration (Medical Review stage)
// Only include New donors in Medical stage and Returning donors in Medical stage
$all_processed_donors = $donors_with_blood + $donors_with_physical + $donors_with_screening;
foreach ($donor_forms as $donor_info) {
    $donor_id = $donor_info['donor_id'];
    if (isset($donors_with_review[$donor_id]) || isset($all_processed_donors[$donor_id])) continue;
    if (isset($screenings_by_donor[$donor_id])) continue;
    
    $medical_info = $medical_by_donor[$donor_id] ?? null;
    // compute donor_type_label safely
    $donor_type_label = getDonorType($donor_id, $medical_info, $eligibility_by_donor, 'medical_review');
    
    // Only include if it's a New donor (no eligibility record) or Returning donor in Medical stage
    $is_new_donor = !isset($eligibility_by_donor[$donor_id]);
    $is_returning_medical = isset($eligibility_by_donor[$donor_id]) && strpos($donor_type_label, 'Medical') !== false;
    
    if ($is_new_donor || $is_returning_medical) {
        $donor_history[] = [
            'no' => $counter++,
            'date' => ($medical_info['updated_at'] ?? null) ?: ($donor_info['submitted_at'] ?? date('Y-m-d H:i:s')),
            'surname' => $donor_info['surname'] ?? 'N/A',
            'first_name' => $donor_info['first_name'] ?? 'N/A',
            'donor_id_number' => $donor_info['prc_donor_number'] ?? 'N/A',
            'interviewer' => $interviewer_by_donor[$donor_id] ?? 'N/A',
            'donor_type' => $donor_type_label,
            'registered_via' => ($donor_info['registration_channel'] ?? '') === 'Mobile' ? 'Mobile' : 'System',
            'donor_id' => $donor_id,
            'stage' => 'medical_review',
            'medical_history_id' => $medical_info['medical_history_id'] ?? null
        ];
    }
}

// Prioritize donors with empty medical_approval OR needs_review=true, then others by oldest first
usort($donor_history, function($a, $b) use ($medical_by_donor) {
    // Get medical approval and needs_review status directly from database
    $a_medical_approval = !empty($a['donor_id']) && isset($medical_by_donor[$a['donor_id']]) ? ($medical_by_donor[$a['donor_id']]['medical_approval'] ?? null) : null;
    $b_medical_approval = !empty($b['donor_id']) && isset($medical_by_donor[$b['donor_id']]) ? ($medical_by_donor[$b['donor_id']]['medical_approval'] ?? null) : null;
    
    $a_needs_review = !empty($a['donor_id']) && isset($medical_by_donor[$a['donor_id']]) && ($medical_by_donor[$a['donor_id']]['needs_review'] === true);
    $b_needs_review = !empty($b['donor_id']) && isset($medical_by_donor[$b['donor_id']]) && ($medical_by_donor[$b['donor_id']]['needs_review'] === true);
    
    // Priority 1: Donors with empty approval OR needs_review=true
    $a_priority = (empty($a_medical_approval) || $a_needs_review);
    $b_priority = (empty($b_medical_approval) || $b_needs_review);
    
    if ($a_priority !== $b_priority) return $a_priority ? -1 : 1;
    
    // Priority 2: All donors by timestamp (oldest first - ascending)
    $toDt = function($s) {
        try { return new DateTime($s); } catch (Exception $e) { return new DateTime('@0'); }
    };
    $adt = $toDt($a['date'] ?? null);
    $bdt = $toDt($b['date'] ?? null);

    $a_timestamp = (float)$adt->format('U.u');
    $b_timestamp = (float)$bdt->format('U.u');
    
    if ($a_timestamp === $b_timestamp) return 0;
    return ($a_timestamp < $b_timestamp) ? -1 : 1;
});

// Calculate counts from already processed data
$new_count = 0;
$returning_count = 0;
$today_count = 0;
$today = date('Y-m-d');

// Count from donor_history (already processed and sorted)
foreach ($donor_history as $entry) {
    if (strpos($entry['donor_type'], 'New') === 0) {
        $new_count++;
    } elseif (strpos($entry['donor_type'], 'Returning') === 0) {
        $returning_count++;
    }
    
    // Count today's submissions
    if (isset($entry['date'])) {
        $submission_date = date('Y-m-d', strtotime($entry['date']));
        if ($submission_date === $today) {
            $today_count++;
        }
    }
}

$total_records = count($donor_history);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Optimized pagination with duplicate removal
$total_records = count($donor_history);
$total_pages = ceil($total_records / $records_per_page);

// Adjust current page if needed
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Remove duplicates and slice for pagination in one operation
$seen_donors = [];
$unique_donor_history = [];
$page_start = ($current_page - 1) * $records_per_page;
$page_end = $page_start + $records_per_page;
$current_count = 0;

foreach ($donor_history as $entry) {
    $donor_key = $entry['donor_id'] . '_' . $entry['stage'];
    if (!isset($seen_donors[$donor_key])) {
        $seen_donors[$donor_key] = true;
        if ($current_count >= $page_start && $current_count < $page_end) {
            $entry['no'] = $current_count - $page_start + 1;
            $unique_donor_history[] = $entry;
        }
        $current_count++;
    }
}

$donor_history = $unique_donor_history;

// Lightweight server-side search endpoint (filters already-processed donor_history)
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $q_l = strtolower($q);

    $filtered = [];
    if ($q_l !== '') {
        foreach ($donor_history as $entry) {
            $sn = strtolower($entry['surname'] ?? '');
            $fn = strtolower($entry['first_name'] ?? '');
            if (strpos($sn, $q_l) !== false || strpos($fn, $q_l) !== false) {
                $filtered[] = $entry;
            }
        }
    } else {
        // Empty search returns current page data
        $filtered = $donor_history;
    }

    // Render rows HTML using the same structure as the main table
    ob_start();
    if (!empty($filtered)) {
        foreach ($filtered as $entry) {
            // Calculate age if missing but birthdate is available
            if (empty($entry['age']) && !empty($entry['birthdate'])) {
                try {
                    $birthDate = new DateTime($entry['birthdate']);
                    $todayDt = new DateTime();
                    $entry['age'] = $birthDate->diff($todayDt)->y;
                } catch (Exception $e) {}
            }
            ?>
            <tr class="clickable-row" data-donor-id="<?php echo $entry['donor_id']; ?>" data-stage="<?php echo htmlspecialchars($entry['stage']); ?>" data-donor-type="<?php echo htmlspecialchars($entry['donor_type']); ?>">
                <td class="text-center"><?php echo $entry['no']; ?></td>
                <td class="text-center"><?php 
                    if (isset($entry['date'])) {
                        $date = new DateTime($entry['date']);
                        echo $date->format('F d, Y');
                    } else {
                        echo 'N/A';
                    }
                ?></td>
                <td class="text-center"><?php echo isset($entry['surname']) ? htmlspecialchars($entry['surname']) : ''; ?></td>
                <td class="text-center"><?php echo isset($entry['first_name']) ? htmlspecialchars($entry['first_name']) : ''; ?></td>
                <td class="text-center"><?php echo isset($entry['interviewer']) ? htmlspecialchars($entry['interviewer']) : 'N/A'; ?></td>
                <td class="text-center"><span class="<?php echo stripos($entry['donor_type'],'returning')===0 ? 'type-returning' : 'type-new'; ?>"><?php echo htmlspecialchars($entry['donor_type']); ?></span></td>
                <td class="text-center">
                    <span style="display: block; text-align: center; width: 100%;">
                        <?php 
                            $status = $entry['status'] ?? '-';
                            $lower = strtolower($status);
                            if ($status === '-') {
                                echo '-';
                            } else {
                                if ($lower === 'ineligible') {
                                    echo '<i class="fas fa-flag me-1" style="color:#dc3545"></i><strong>' . htmlspecialchars($status) . '</strong>';
                                } else {
                                    echo '<strong>' . htmlspecialchars($status) . '</strong>';
                                }
                            }
                        ?>
                    </span>
                </td>
                <td class="text-center"><span class="badge-tag badge-registered <?php echo strtolower($entry['registered_via'])==='mobile' ? 'badge-mobile' : 'badge-system'; ?>"><?php echo htmlspecialchars($entry['registered_via']); ?></span></td>
                <td class="text-center">
                    <button type="button" class="btn btn-info btn-sm view-donor-btn me-1" 
                            onclick="checkAndShowDonorStatus('<?php echo $entry['donor_id']; ?>')"
                            title="View Details"
                            style="width: 35px; height: 30px;">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
            <?php
        }
    } else {
        ?>
        <tr>
            <td colspan="9" class="text-center text-muted">Name can't be found</td>
        </tr>
        <?php
    }
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'count' => count($filtered), 'html' => $html]);
    exit;
}
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
    <link rel="stylesheet" href="../../assets/css/defer-donor-modal.css">
    <script src="../../assets/js/account_interviewer_feedback_modal.js"></script>
    <script src="../../assets/js/account_interviewer_error_modal.js"></script>
    <script src="../../assets/js/screening_form_modal.js"></script>
    <script src="../../assets/js/search_func/search_account_medical_history.js"></script>
    <script src="../../assets/js/search_func/filter_search_account_medical_history.js"></script>
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

        /* Center status column specifically */
        .dashboard-staff-tables tbody td.text-center {
            text-align: center !important;
            vertical-align: middle !important;
        }

        /* Ensure strong elements in status column are properly centered */
        .dashboard-staff-tables tbody td.text-center strong {
            text-align: center !important;
            display: inline !important;
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


        
        /* Section header */
        .section-header {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: #333;
        }

        /* Badge styles for Donor Type and Registered via */
        .badge-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
            line-height: 1;
            white-space: nowrap;
        }
        .badge-new { background-color: #2e7d32; }            /* green */
        .badge-returning { background-color: #1976d2; }      /* blue */
        .badge-mobile { background-color: #fb8c00; }         /* orange */
        .badge-system { background-color: #6a1b9a; }         /* purple */
        /* Extra padding for Registered via badges */
        .badge-registered { padding: 6px 16px; }
        /* Donor type colored text (no badge) */
        .type-new { color: #2e7d32; font-weight: 700; }
        .type-returning { color: #1976d2; font-weight: 700; }

        /* Donor Status modal header styling */
        #deferralStatusModal .modal-header {
            background-color: var(--table-header-bg);
            color: #fff;
        }
        #deferralStatusModal .modal-header .btn-close {
            filter: invert(1);
            opacity: 1;
        }

        /* Blur background when modal is open */
        .modal-backdrop.show {
            backdrop-filter: blur(4px);
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

        .screening-patient-table .form-control form-control-sm-sm {
            font-size: 13px;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }

        .screening-patient-table .form-control form-control-sm-sm:focus {
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

        /* Responsive adjustments for screening form */
        @media (max-width: 991.98px) {
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
        
        /* Physical Examination Modal Styles */
        #physicalExaminationModal .modal-dialog {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 1rem);
            max-height: 80vh;
            width: 90%;
            max-width: 1000px;
            margin: 0 auto;
            z-index: 1060;
        }
        
        #physicalExaminationModal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            width: 100%;
            max-height: 80vh;
            overflow: hidden;
            pointer-events: auto;
            margin: 0;
        }
        
        #physicalExaminationModal .modal-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        #physicalExaminationModal .modal-header .modal-title {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
        }
        
        #physicalExaminationModal .modal-body {
            padding: 1.5rem;
            background-color: #ffffff;
            max-height: calc(80vh - 120px);
            overflow-y: auto;
        }
        
        /* Main Modal Styles - Match Physical Examination Modal */
        #deferralStatusModal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        #deferralStatusModal .modal-body {
            padding: 1.5rem;
            background-color: #ffffff;
        }
        
        /* Force Red Solid Headers */
        #deferralStatusModal .table thead th {
            background: #b22222 !important;
            color: white !important;
            border: none !important;
            font-weight: 600 !important;
            text-align: center !important;
            vertical-align: middle !important;
            padding: 0.75rem !important;
            line-height: 1.2 !important;
        }
        
        #deferralStatusModal .table thead {
            background: #b22222 !important;
        }
        
        /* Center table body content */
        #deferralStatusModal .table tbody td {
            text-align: center !important;
            vertical-align: middle !important;
        }
        
        /* Main Modal Width - Match Physical Examination Results Modal */
        #deferralStatusModal .modal-dialog {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 1rem);
            max-height: 80vh;
            width: 90%;
            max-width: 1000px;
            margin: 0 auto;
            z-index: 1060;
        }
        
        /* Patient Information Header - Patient Name MASSIVE */
        #physicalExaminationModal .patient-info-header .patient-date {
            font-size: 0.7rem !important;
            color: #666666 !important;
            margin-bottom: 5px !important;
        }
        
        #physicalExaminationModal .patient-info-header .patient-name {
            font-size: 4.5rem !important;
            font-weight: 700 !important;
            color: #000000 !important;
            margin-bottom: 8px !important;
            line-height: 1.1 !important;
        }
        
        #physicalExaminationModal .patient-info-header .patient-details {
            font-size: 0.8rem !important;
            color: #333333 !important;
            margin-bottom: 5px !important;
        }
        
        #physicalExaminationModal .patient-info-header .patient-id {
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            color: #333333 !important;
            margin-bottom: 5px !important;
        }
        
        #physicalExaminationModal .patient-info-header .patient-blood-type {
            font-size: 0.8rem !important;
            font-weight: 700 !important;
            color: #8b0000 !important;
        }
        
        /* Section Headers - SMALLER */
        #physicalExaminationModal h6 {
            font-size: 1.2rem !important;
            font-weight: 700 !important;
            color: #000000 !important;
            margin-bottom: 1.5rem !important;
            margin-top: 2rem !important;
        }
        
        #physicalExaminationModal h6:first-child {
            margin-top: 0 !important;
        }
        
        /* Form Labels - SMALL */
        #physicalExaminationModal .form-label {
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            color: #333333 !important;
            margin-bottom: 0.5rem !important;
        }
        
        /* Form Controls - SMALL */
        #physicalExaminationModal .form-control {
            font-size: 0.9rem !important;
            padding: 0.75rem 1rem !important;
            border: 1px solid #e0e0e0 !important;
            border-radius: 6px !important;
            background-color: #f8f9fa !important;
            color: #333333 !important;
        }
        
        /* Table Styling - Clean and Modern */
        #physicalExaminationModal .table {
            border: none !important;
            margin-bottom: 0 !important;
        }
        
        #physicalExaminationModal .table thead th {
            background-color: #8b0000 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            font-size: 0.9rem !important;
            padding: 1rem 0.75rem !important;
            border: none !important;
            text-align: center !important;
        }
        
        #physicalExaminationModal .table tbody td {
            background-color: #ffffff !important;
            color: #333333 !important;
            font-size: 0.9rem !important;
            padding: 1rem 0.75rem !important;
            border: 1px solid #e0e0e0 !important;
            text-align: center !important;
            vertical-align: middle !important;
        }
        
        #physicalExaminationModal .table tbody td:first-child {
            text-align: left !important;
        }
        
        /* Remove input styling from table cells */
        #physicalExaminationModal .table .form-control {
            border: none !important;
            background: transparent !important;
            padding: 0 !important;
            font-size: 0.9rem !important;
            text-align: center !important;
        }
        
        #physicalExaminationModal .table .form-control:first-child {
            text-align: left !important;
        }
        
        /* Card Styling */
        #physicalExaminationModal .card {
            border: 1px solid #e0e0e0 !important;
            border-radius: 8px !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
            margin-bottom: 1.5rem !important;
        }
        
        #physicalExaminationModal .card-header {
            background-color: #f8f9fa !important;
            border-bottom: 1px solid #e0e0e0 !important;
            padding: 1rem 1.5rem !important;
        }
        
        #physicalExaminationModal .card-body {
            padding: 1.5rem !important;
        }
        
        /* Alert Styling */
        #physicalExaminationModal .alert {
            font-size: 0.9rem !important;
            border-radius: 6px !important;
            padding: 1rem !important;
        }
        
        #physicalExaminationModal .modal-footer {
            display: none !important;
        }
        
        #physicalExaminationModal .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        #physicalExaminationModal .card-header {
            border-radius: 12px 12px 0 0;
            border-bottom: none;
            padding: 1rem 1.5rem;
        }
        
        #physicalExaminationModal .card-body {
            padding: 1.5rem;
        }
        
        #physicalExaminationModal .form-control form-control-sm,
        #physicalExaminationModal .form-control form-control-sm:read-only {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 14px;
        }
        
        #physicalExaminationModal .form-control form-control-sm:read-only {
            color: #495057;
            font-weight: 500;
        }
        
        #physicalExaminationModal .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        #physicalExaminationModal .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
            border-radius: 8px;
        }
        
        #physicalExaminationModal textarea.form-control form-control-sm {
            resize: none;
            min-height: 80px;
        }
        
        /* Responsive adjustments for physical examination modal */
        @media (max-width: 767.98px) {
            #physicalExaminationModal .modal-dialog {
                width: 95% !important;
                max-height: 95vh !important;
            }
            
            #physicalExaminationModal .modal-content {
                max-height: 95vh;
            }
            
            #physicalExaminationModal .modal-body {
                padding: 1.5rem !important;
                overflow: visible;
                max-height: none;
            }
            
            /* Responsive adjustments for main modal */
            #deferralStatusModal .modal-dialog {
                width: 95% !important;
                max-height: 95vh !important;
            }
            
            #deferralStatusModal .modal-content {
                max-height: 95vh;
            }
            
            #deferralStatusModal .modal-body {
                padding: 1.5rem !important;
                overflow: visible;
                max-height: none;
            }
            
            #physicalExaminationModal .modal-header {
                padding: 1rem;
            }
            
            #physicalExaminationModal .modal-header .modal-title {
                font-size: 1.3rem !important;
            }
            
            #physicalExaminationModal .card-body {
                padding: 1rem;
            }
            
            #physicalExaminationModal .row.g-2 > .col-6,
            #physicalExaminationModal .row.g-2 > .col-md-4 {
                margin-bottom: 1rem;
            }
            
            #physicalExaminationModal .form-label {
                font-size: 0.7rem !important;
            }
            
            #physicalExaminationModal .form-control {
                font-size: 0.8rem !important;
            }
            
            #physicalExaminationModal h6 {
                font-size: 1.1rem !important;
            }
            
            #physicalExaminationModal .table thead th,
            #physicalExaminationModal .table tbody td {
                font-size: 0.8rem !important;
                padding: 0.75rem 0.5rem !important;
            }
            
            /* Mobile Patient Header - Patient Name STILL MASSIVE */
            #physicalExaminationModal .patient-info-header .patient-date {
                font-size: 0.6rem !important;
            }
            
            #physicalExaminationModal .patient-info-header .patient-name {
                font-size: 3.5rem !important;
            }
            
            #physicalExaminationModal .patient-info-header .patient-details {
                font-size: 0.7rem !important;
            }
            
            #physicalExaminationModal .patient-info-header .patient-id {
                font-size: 0.6rem !important;
            }
            
            #physicalExaminationModal .patient-info-header .patient-blood-type {
                font-size: 0.7rem !important;
            }
        }
    </style>
</head>

<body class="light-mode">
    <div class="container-fluid p-0">
        <!-- Header -->
        <div class="dashboard-home-header">
            <div class="header-left">
                <h4 class="header-title">Interviewer Dashboard <span class="header-date"><?php echo date('l, M d, Y'); ?></span></h4>
            </div>
            <div class="header-right">
                <button class="register-btn" onclick="showConfirmationModal()">
                    Register Donor
                </button>
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
                        <h2 class="welcome-title">Welcome, Interviewer!</h2>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="dashboard-staff-status">
                        <a href="#" class="status-card active">
                            <p class="dashboard-staff-count"><?php echo $new_count; ?></p>
                            <p class="dashboard-staff-title">New Donors</p>
                        </a>
                        <a href="#" class="status-card">
                            <p class="dashboard-staff-count"><?php echo $returning_count; ?></p>
                            <p class="dashboard-staff-title">Eligible Donors</p>
                        </a>
                        <a href="#" class="status-card">
                            <p class="dashboard-staff-count"><?php echo $today_count; ?></p>
                            <p class="dashboard-staff-title">Today's Summary</p>
                        </a>
                    </div>
                    
                    <h5 class="section-header">Medical Stage Donors Only</h5>
                    
                    <!-- Search Bar -->
                    <div class="search-container">
                        <div class="position-relative">
                        <input type="text" 
                            class="form-control form-control-sm" 
                            id="searchInput" 
                                placeholder="Search donors by name...">
                            <div id="searchLoading" class="position-absolute" style="right:10px; top:50%; transform: translateY(-50%); display:none;">
                                <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="red-separator">
                    
                    <div class="table-responsive">
                        <table class="table dashboard-staff-tables table-hover">
                            <thead>
                                <tr>
                                    <th class="text-center">No.</th>
                                    <th class="text-center">Date</th>
                                    <th class="text-center">Surname</th>
                                    <th class="text-center">First Name</th>
                                    <th class="text-center">Interviewer</th>
                                    <th class="text-center">Donor Type</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Registered via</th>
                                    <th class="text-center">View</th>
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php if($donor_history && is_array($donor_history)): ?>
                                    <?php foreach($donor_history as $index => $entry): ?>
                                        <?php
                                        // Calculate age if missing but birthdate is available
                                        if (empty($entry['age']) && !empty($entry['birthdate'])) {
                                            $birthDate = new DateTime($entry['birthdate']);
                                            $today = new DateTime();
                                            $entry['age'] = $birthDate->diff($today)->y;
                                        }
                                        ?>
                                        <tr class="clickable-row" data-donor-id="<?php echo $entry['donor_id']; ?>" data-stage="<?php echo htmlspecialchars($entry['stage']); ?>" data-donor-type="<?php echo htmlspecialchars($entry['donor_type']); ?>">
                                            <td class="text-center"><?php echo $entry['no']; ?></td>
                                            <td class="text-center"><?php 
                                                if (isset($entry['date'])) {
                                                    $date = new DateTime($entry['date']);
                                                    echo $date->format('F d, Y');
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?></td>

                                            <td class="text-center"><?php echo isset($entry['surname']) ? htmlspecialchars($entry['surname']) : ''; ?></td>
                                            <td class="text-center"><?php echo isset($entry['first_name']) ? htmlspecialchars($entry['first_name']) : ''; ?></td>
                                            <td class="text-center"><?php echo isset($entry['interviewer']) ? htmlspecialchars($entry['interviewer']) : 'N/A'; ?></td>
                                            <td class="text-center"><span class="<?php echo stripos($entry['donor_type'],'returning')===0 ? 'type-returning' : 'type-new'; ?>"><?php echo htmlspecialchars($entry['donor_type']); ?></span></td>
                                            <td class="text-center">
                                                <span style="display: block; text-align: center; width: 100%;">
                                                    <?php 
                                                        $status = $entry['status'] ?? '-';
                                                        $lower = strtolower($status);
                                                        if ($status === '-') {
                                                            echo '-';
                                                        } else {
                                                            if ($lower === 'ineligible') {
                                                                echo '<i class="fas fa-flag me-1" style="color:#dc3545"></i><strong>' . htmlspecialchars($status) . '</strong>';
                                                            } else {
                                                                echo '<strong>' . htmlspecialchars($status) . '</strong>';
                                                            }
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><span class="badge-tag badge-registered <?php echo strtolower($entry['registered_via'])==='mobile' ? 'badge-mobile' : 'badge-system'; ?>"><?php echo htmlspecialchars($entry['registered_via']); ?></span></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-info btn-sm view-donor-btn me-1" 
                                                        onclick="checkAndShowDonorStatus('<?php echo $entry['donor_id']; ?>')"
                                                        title="View Details"
                                                        style="width: 35px; height: 30px;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No donor records found</td>
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
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" <?php echo $current_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
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
                                            <a class="page-link" href="?page=1">1</a>
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
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
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
                                            <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Next button -->
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" <?php echo $current_page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                            Next
                                        </a>
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
             <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                     <h5 class="modal-title" id="deferralStatusModalLabel" style="font-weight: 700;">
                         <i class="fas fa-user-md me-2"></i>Donor Status & Donation History
                     </h5>
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
            
                    
                        </div>
             <div class="modal-footer d-flex justify-content-end align-items-center" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6; border-radius: 0 0 15px 15px;">
                <button type="button" class="btn" id="proceedToMedicalHistory" style="background-color: #b22222; color: white; border: none;">
                    <i class="fas fa-clipboard-list me-1"></i>Proceed to Medical History
                </button>
            </div>
                            </div>
                            </div>
</div>

    <!-- Stage Notice Modal (Read-only for non-medical stages) -->
    <div class="modal fade" id="stageNoticeModal" tabindex="-1" aria-labelledby="stageNoticeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stageNoticeModalLabel">Notice: Different Processing Stage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="stageNoticeBody">
                    <!-- Filled dynamically -->
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="stageNoticeViewBtn">View Details</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Returning Donor Info Modal (friendly notice before viewing) -->
    <div class="modal fade" id="returningInfoModal" tabindex="-1" aria-labelledby="returningInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="returningInfoModalLabel">Returning Donor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="returningInfoBody">
                    This donor has previous donations. You can safely view details in read-only mode from here. Processing for new medical history occurs only under New (Medical).
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="returningInfoViewBtn">View Details</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Screening Details Modal -->
    <div class="modal fade" id="screeningDetailsModal" tabindex="-1" aria-labelledby="screeningDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="screeningDetailsLabel">Screening Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="screeningDetailsBody">
                    <!-- Populated dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn" id="markReturningReviewBtn" style="background-color: #ffc107; border: 1px solid #ffc107; color: #212529; font-weight: 600; border-radius: 6px; padding: 0.5rem 1rem; transition: all 0.3s ease;">
                        <i class="fas fa-flag me-2"></i>Mark for Medical Review
                    </button>
            </div>
        </div>
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

    <!-- Medical History Modal -->
    <div class="modal fade" id="medicalHistoryModal" tabindex="-1" aria-labelledby="medicalHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title" id="medicalHistoryModalLabel">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Medical History Form
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="medicalHistoryModalContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <input type="hidden" id="modalSelectedAction" name="modalSelectedAction" value="">
                </div>
            </div>
        </div>
    </div>

    <!-- Declaration Form Modal -->
    <div class="modal fade" id="declarationFormModal" tabindex="-1" aria-labelledby="declarationFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title" id="declarationFormModalLabel">Declaration Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="declarationFormModalContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Processing Confirmation Modal -->
    <div class="modal fade" id="dataProcessingConfirmModal" tabindex="-1" aria-labelledby="dataProcessingConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background:#8b0000;color:#fff;">
                    <h5 class="modal-title" id="dataProcessingConfirmLabel">Submit Medical Screening</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Donor approved for donation and forwarded to physician for physical examination. Proceed to print the donor declaration form and hand it to the donor.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="confirmProcessingBtn">Yes, proceed</button>
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

    <!-- Confirm Screening Mark Modal -->
    <div class="modal fade" id="confirmScreeningMarkModal" tabindex="-1" aria-labelledby="confirmScreeningMarkLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmScreeningMarkLabel">Mark Screening for Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Do you want to mark this donor's screening record for review?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmScreeningMarkBtn">Yes, mark it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Eligibility Alert Modal -->
    <div class="modal fade" id="eligibilityAlertModal" tabindex="-1" aria-labelledby="eligibilityAlertLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div id="eligibilityAlertTitle" class="modal-header" style="color: white;">
                    <h5 class="modal-title" id="eligibilityAlertLabel"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="eligibilityAlertContent" class="modal-body py-4">
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        #eligibilityAlertModal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        #eligibilityAlertModal .modal-header {
            border-radius: 15px 15px 0 0;
            border: none;
        }
        #eligibilityAlertModal .modal-body {
            font-size: 1.1rem;
            line-height: 1.5;
        }
        #eligibilityAlertModal .btn-close-white {
            opacity: 1;
        }
    </style>

    <!-- Review Confirmation Modal -->
    <div class="modal fade" id="reviewConfirmModal" tabindex="-1" aria-labelledby="reviewConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background:#8b0000;color:#fff;">
                    <h5 class="modal-title" id="reviewConfirmLabel">Proceed to Medical History</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    This will redirect you to the medical history the donor just submitted. Do you want to proceed?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="reviewConfirmProceedBtn">Proceed</button>
                </div>
            </div>
        </div>
    </div>



    <script>
        // Pass PHP data to JavaScript
        const medicalByDonor = <?php echo json_encode($medical_by_donor); ?>;
        const eligibilityDonorIds = <?php echo json_encode(array_keys($eligibility_by_donor)); ?>;
        const hasEligibility = (did) => Array.isArray(eligibilityDonorIds) && eligibilityDonorIds.includes(String(did)) || eligibilityDonorIds.includes(Number(did));
        
        function showConfirmationModal() {
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            confirmationModal.show();
        }

        function proceedToDonorForm() {
            // Hide confirmation modal
            const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            confirmationModal.hide();

            // Show loading modal briefly
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            loadingModal.show();

            // Redirect immediately - browser naturally shows transition
                window.location.href = '../../src/views/forms/donor-form-modal.php';
        }
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchLoading = document.getElementById('searchLoading');
            const searchCategory = document.getElementById('searchCategory');
            const donorTableBody = document.getElementById('donorTableBody');
            // Cache modal instances for better performance
            const deferralStatusModalEl = document.getElementById('deferralStatusModal');
            const deferralStatusModal = deferralStatusModalEl ? new bootstrap.Modal(deferralStatusModalEl) : null;
            const deferralStatusContent = document.getElementById('deferralStatusContent');
            const stageNoticeModalEl = document.getElementById('stageNoticeModal');
            const stageNoticeModal = stageNoticeModalEl ? new bootstrap.Modal(stageNoticeModalEl) : null;
            const stageNoticeBody = document.getElementById('stageNoticeBody');
            const stageNoticeViewBtn = document.getElementById('stageNoticeViewBtn');
            const returningInfoModalEl = document.getElementById('returningInfoModal');
            const returningInfoModal = returningInfoModalEl ? new bootstrap.Modal(returningInfoModalEl) : null;
            const returningInfoViewBtn = document.getElementById('returningInfoViewBtn');
            const markReturningReviewBtn = document.getElementById('markReturningReviewBtn');
            const markReviewFromMain = document.getElementById('markReviewFromMain');
            
            let currentDonorId = null;
            let allowProcessing = false;
            let modalContextType = 'new_medical'; // 'new_medical' | 'new_other_stage' | 'returning' | 'other'
            let currentStage = null; // 'medical_review' | 'screening_form' | 'physical_examination' | 'blood_collection'
            
            // Backdrop cleanup utility to prevent stuck overlays (expose globally)
            window.cleanupModalBackdrops = function() {
                try {
                    document.body.classList.remove('modal-open');
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(el => {
                        if (el && el.parentNode) el.parentNode.removeChild(el);
                    });
                } catch (e) {}
            }
            
            // Search functionality now handled by external JS files:
            // - search_account_medical_history.js
            // - filter_search_account_medical_history.js
            
            // Use event delegation for row clicks to work with dynamically loaded search results
            donorTableBody.addEventListener('click', function(e) {
                // Find the closest tr with clickable-row class
                const row = e.target.closest('tr.clickable-row');
                if (!row) return;
                
                const donorId = row.getAttribute('data-donor-id');
                const stageAttr = row.getAttribute('data-stage');
                const donorTypeLabel = row.getAttribute('data-donor-type');
                if (!donorId) return;
                    
                    // Set global variables for modal context
                    window.currentDonorId = donorId;
                    window.currentDonorType = donorTypeLabel || 'New';
                    window.currentDonorStage = stageAttr || 'Medical';
                    
                        currentDonorId = donorId;
                    const lowerType = (donorTypeLabel || '').toLowerCase();
                    const isNew = lowerType.startsWith('new');
                    const isReturning = lowerType.startsWith('returning');
                    // Derive stage from donor type text to avoid mismatches
                    const typeText = lowerType;
                    let stageFromType = 'unknown';
                    if (typeText.includes('medical')) stageFromType = 'medical_review';
                    else if (typeText.includes('screening')) stageFromType = 'screening_form';
                    else if (typeText.includes('physical')) stageFromType = 'physical_examination';
                    else if (typeText.includes('collection') || typeText.includes('completed')) stageFromType = 'blood_collection';
                    const effectiveStage = stageFromType !== 'unknown' ? stageFromType : stageAttr;
                    currentStage = effectiveStage;
                    // Allow processing for new donors in medical_review OR any donor with needs_review=true
                    allowProcessing = (isNew && (effectiveStage === 'medical_review')) || 
                                    (effectiveStage === 'medical_review' && currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review);
                    // Determine modal context type
                    if (allowProcessing) {
                        modalContextType = 'new_medical'; // Can process medical history
                    } else if (isNew) {
                        modalContextType = 'new_other_stage';
                    } else if (isReturning) {
                        modalContextType = 'returning';
                    } else {
                        modalContextType = 'other';
                    }
                    window.modalContextType = modalContextType;
                    
                    if (!allowProcessing && !isReturning) {
                        // Show read-only notice modal
                        const stageTitleMap = {
                            'screening_form': 'Screening Stage',
                            'physical_examination': 'Physical Examination Stage',
                            'blood_collection': 'Blood Collection Stage'
                        };
                        const friendlyStage = stageTitleMap[effectiveStage] || 'Different Stage';
                        const newOrReturningNote = isNew
                            ? `This record is <strong>New</strong> but not in the Medical stage (<strong>${friendlyStage}</strong>).`
                            : `This record is <strong>Returning</strong>. This page is dedicated to processing <strong>New (Medical)</strong> only.`;
                        stageNoticeBody.innerHTML = `
                            <p>${newOrReturningNote}</p>
                            <p><strong>Note:</strong> Medical history processing on this page is available only for <strong>New (Medical)</strong> records.</p>
                            <div class="alert alert-info mb-0">
                                <div><strong>Donor type:</strong> ${donorTypeLabel || ''}</div>
                                <div class="small text-muted">You can view read-only details for reference.</div>
                            </div>`;
                        if (stageNoticeModal) stageNoticeModal.show();
                        
                        // Bind view details to open the existing details modal without processing
                        if (stageNoticeViewBtn) {
                            stageNoticeViewBtn.onclick = () => {
                            if (stageNoticeModal) stageNoticeModal.hide();
                            // Prepare details modal in read-only mode
                            deferralStatusContent.innerHTML = `
                                <div class=\"d-flex justify-content-center\">\n                                    <div class=\"spinner-border text-primary\" role=\"status\">\n                                        <span class=\"visually-hidden\">Loading...</span>\n                                    </div>\n                                </div>`;
                            
                            // Hide proceed button in read-only mode
                            const proceedButton = getProceedButton();
                            if (proceedButton && proceedButton.style) {
                                proceedButton.style.display = 'none';
                                proceedButton.textContent = 'Proceed to Medical History';
                            }
                            if (deferralStatusModal) deferralStatusModal.show();
                            fetchDonorStatusInfo(donorId);
                        };
                        }
                        return;
                    }
                    
                    if (isReturning) {
                        // Check if returning donor has needs_review=true
                        const hasNeedsReview = currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review;
                        
                        if (effectiveStage === 'medical_review' || hasNeedsReview) {
                            // Returning (Medical) OR Returning with needs_review: go straight to details with Review available
                        deferralStatusContent.innerHTML = `
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                    </div>
                            </div>`;
                            const proceedButton = getProceedButton();
                            if (proceedButton && proceedButton.style) {
                                proceedButton.style.display = 'inline-block';
                                proceedButton.textContent = 'Proceed to Medical History';
                            }
                            if (markReviewFromMain) markReviewFromMain.style.display = 'none';
                        if (deferralStatusModal) deferralStatusModal.show();
                        fetchDonorStatusInfo(donorId);
                            return;
                        }
                        // Returning but not Medical and no needs_review: directly show donor modal
                        deferralStatusContent.innerHTML = `
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>`;
                        const proceedButton = getProceedButton();
                        if (proceedButton && proceedButton.style) proceedButton.style.display = 'none';
                        if (deferralStatusModal) deferralStatusModal.show();
                        fetchDonorStatusInfo(donorId);
                        // Mark for review handler
                        if (markReturningReviewBtn) {
                            markReturningReviewBtn.onclick = () => {
                                const confirmMsg = 'This action will mark the donor for Medical Review and move them back to the medical stage for reassessment. Do you want to proceed?';
                                if (window.customConfirm) {
                                    window.customConfirm(confirmMsg, function() {
                                        fetch('../../assets/php_func/update_needs_review.php', {
                                            method: 'POST',
                                            headers: { 'Accept': 'application/json' },
                                            body: new URLSearchParams({ donor_id: donorId })
                                        })
                                        .then(r => r.json())
                                        .then(res => {
                                            if (res && res.success) {
                                                returningInfoModal.hide();
                                                // Silent success + refresh without opening another modal
                                                // Show centered success modal (auto-closes, no buttons)
                                                try {
                                                    const existing = document.getElementById('successAutoModal');
                                                    if (existing) existing.remove();
                                                    const successHTML = `
                                                        <div id="successAutoModal" style="
                                                            position: fixed;
                                                            top: 0;
                                                            left: 0;
                                                            width: 100%;
                                                            height: 100%;
                                                            background: rgba(0,0,0,0.5);
                                                            z-index: 99999;
                                                            display: flex;
                                                            align-items: center;
                                                            justify-content: center;
                                                        ">
                                                            <div style="
                                                                background: #ffffff;
                                                                border-radius: 10px;
                                                                max-width: 520px;
                                                                width: 90%;
                                                                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                                                                overflow: hidden;
                                                            ">
                                                                <div style="
                                                                    background: #9c0000;
                                                                    color: white;
                                                                    padding: 14px 18px;
                                                                    font-weight: 700;
                                                                ">Marked</div>
                                                                <div style="padding: 22px;">
                                                                    <p style="margin: 0;">The donor is medically cleared for donation.</p>
                                                                </div>
                                                            </div>
                                                        </div>`;
                                                    document.body.insertAdjacentHTML('beforeend', successHTML);
                                                } catch(_) {}
                                                setTimeout(() => { 
                                                    const m = document.getElementById('successAutoModal');
                                                    if (m) m.remove();
                                                    window.location.href = window.location.pathname + '?page=1'; 
                                                }, 1800);
                                                const row = document.querySelector(`tr[data-donor-id="${donorId}"]`);
                                                if (row) {
                                                    const donorTypeCell = row.querySelector('td:nth-child(6)');
                                                    if (donorTypeCell && donorTypeCell.textContent.toLowerCase().includes('returning')) {
                                                        donorTypeCell.textContent = 'Returning (Medical)';
                                                        row.setAttribute('data-donor-type', 'Returning (Medical)');
                                                    }
                                                }
                                            } else {
                                                window.customConfirm('Failed to mark for review.', function() {});
                                            }
                                        })
                                        .catch(() => {
                                            window.customConfirm('Failed to mark for review.', function() {});
                                        });
                                    });
                                }
                            };
                        }
                        // Enable main modal mark button only for returning
                        if (markReviewFromMain) {
                            // Don't force display here - let button control logic handle it
                            markReviewFromMain.onclick = () => {
                                const confirmMsg = 'This action will mark the donor for Medical Review and return them to the medical stage for reassessment. Do you want to proceed?';
                                if (window.customConfirm) {
                                    window.customConfirm(confirmMsg, function() {
                                        fetch('../../assets/php_func/update_needs_review.php', {
                                            method: 'POST',
                                            headers: { 'Accept': 'application/json' },
                                            body: new URLSearchParams({ donor_id: donorId })
                                        })
                                        .then(r => r.json())
                                        .then(res => {
                                            if (res && res.success) {
                                                // Silent success + refresh without opening another modal
                                                // Show centered success modal (auto-closes, no buttons)
                                                try {
                                                    const existing = document.getElementById('successAutoModal');
                                                    if (existing) existing.remove();
                                                    const successHTML = `
                                                        <div id="successAutoModal" style="
                                                            position: fixed;
                                                            top: 0;
                                                            left: 0;
                                                            width: 100%;
                                                            height: 100%;
                                                            background: rgba(0,0,0,0.5);
                                                            z-index: 99999;
                                                            display: flex;
                                                            align-items: center;
                                                            justify-content: center;
                                                        ">
                                                            <div style="
                                                                background: #ffffff;
                                                                border-radius: 10px;
                                                                max-width: 520px;
                                                                width: 90%;
                                                                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                                                                overflow: hidden;
                                                            ">
                                                                <div style="
                                                                    background: #9c0000;
                                                                    color: white;
                                                                    padding: 14px 18px;
                                                                    font-weight: 700;
                                                                ">Marked</div>
                                                                <div style="padding: 22px;">
                                                                    <p style="margin: 0;">The donor is medically cleared for donation.</p>
                                                                </div>
                                                            </div>
                                                        </div>`;
                                                    document.body.insertAdjacentHTML('beforeend', successHTML);
                                                } catch(_) {}
                                                setTimeout(() => { 
                                                    const m = document.getElementById('successAutoModal');
                                                    if (m) m.remove();
                                                    window.location.href = window.location.pathname + '?page=1'; 
                                                }, 1800);
                                                const row = document.querySelector(`tr[data-donor-id="${donorId}"]`);
                                                if (row) {
                                                    const donorTypeCell = row.querySelector('td:nth-child(6)');
                                                    if (donorTypeCell && donorTypeCell.textContent.toLowerCase().includes('returning')) {
                                                        donorTypeCell.textContent = 'Returning (Medical)';
                                                        row.setAttribute('data-donor-type', 'Returning (Medical)');
                                                    }
                                                    const dateCell = row.querySelector('td:nth-child(2)');
                                                    if (dateCell && res.updated_at) {
                                                        const d = new Date(res.updated_at);
                                                        const options = { year: 'numeric', month: 'long', day: 'numeric' };
                                                        dateCell.textContent = d.toLocaleDateString('en-US', options);
                                                    }
                                                }
                                            } else {
                                                window.customConfirm('Failed to mark for review.', function() {});
                                            }
                                        })
                                        .catch(() => {
                                            window.customConfirm('Failed to mark for review.', function() {});
                                        });
                                    });
                                }
                            };
                        }
                        return;
                    }
                    
                    // Allow processing: show details and keep proceed button visible
                    deferralStatusContent.innerHTML = `
                        <div class="d-flex justify-content-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>`;
                    
                    const proceedButton = getProceedButton();
                    if (proceedButton && proceedButton.style) {
                        proceedButton.style.display = 'inline-block';
                        proceedButton.textContent = 'Proceed to Medical History';
                    }
                    // Hide mark button for non-returning/new-medical flow
                    if (markReviewFromMain) markReviewFromMain.style.display = 'none';
                    if (deferralStatusModal) deferralStatusModal.show();
                    fetchDonorStatusInfo(donorId);
            });
            
            // Function to fetch donor status information
            // OPTIMIZED: Single unified endpoint with parallel backend processing for instant data loading
            function fetchDonorStatusInfo(donorId) {
                // Fetch all data with one request - backend handles parallel API calls
                fetch('../../assets/php_func/fetch_donor_complete_info_staff-medical-history.php?donor_id=' + donorId)
                    .then(r => r.json())
                    .then(response => {
                        if (response && response.success && response.data) {
                            // Extract the data and deferral info from unified response
                            const donorData = { success: true, data: response.data };
                            const deferralData = response.deferral || null;
                    // Display immediately with all data ready
                                displayDonorInfo(donorData, deferralData);
                        } else {
                            deferralStatusContent.innerHTML = `<div class="alert alert-danger">Failed to load donor information</div>`;
                        }
                            })
                            .catch(error => {
                    deferralStatusContent.innerHTML = `<div class="alert alert-danger">Error loading donor information: ${error.message}</div>`;
                    });
            }
            
            // Function to display donor and deferral information (exposed globally)
            // Accepts either a full API response { success, data } or the donor object directly
            window.displayDonorInfo = function(donorData, deferralData) {
                let donorInfoHTML = '';
                const safe = (v) => v || 'N/A';
                
                // Helper function to get blood type from eligibility records
                const getBloodTypeFromEligibility = (donor) => {
                    if (!donor || !donor.eligibility) return null;
                    
                    const eligibilityRecords = Array.isArray(donor.eligibility) ? donor.eligibility : [donor.eligibility];
                    
                    // Get the most recent eligibility record with blood type
                    for (let i = eligibilityRecords.length - 1; i >= 0; i--) {
                        const record = eligibilityRecords[i];
                        if (record && record.blood_type) {
                            return record.blood_type;
                        }
                    }
                    
                    return null;
                };
                
                // Store donor data globally for eye button access
                window.currentDonorData = donorData && donorData.data ? donorData.data : donorData;
                
                // Debug logging
                
                // Check if we have donor data, regardless of success field
                const donor = (donorData && donorData.data) ? donorData.data : donorData;
                if (donor && (typeof donor === 'object')) {
                    const fullName = `${safe(donor.surname)}, ${safe(donor.first_name)} ${safe(donor.middle_name)}`.trim();
                    const currentStatus = (() => {
                        // Get donor type from the row data
                        const donorType = window.currentDonorType || 'New';
                        const donorStage = window.currentDonorStage || 'Medical';
                        
                        if (donorType === 'New') {
                            if (donorStage === 'Medical') return 'Medical';
                            if (donorStage === 'Screening') return 'Screening';
                            if (donorStage === 'Physical') return 'Physical';
                            if (donorStage === 'Collection') return 'Collection';
                            return 'Medical'; // Default
                        } else if (donorType === 'Returning') {
                            if (donorStage === 'Medical') return 'Medical';
                            if (donorStage === 'Screening') return 'Screening';
                            if (donorStage === 'Physical') return 'Physical';
                            if (donorStage === 'Collection') return 'Collection';
                            return 'Medical'; // Default
                        }
                        return 'Medical'; // Default fallback
                    })();
                    
                     // Donor Information Header (Clean Design - Match Physical Exam Modal)
                        donorInfoHTML += `
                        <div class="mb-3">
                             <div class="d-flex justify-content-between align-items-start">
                                 <div class="flex-grow-1">
                                     <div class="text-muted small mb-1">
                                         <i class="fas fa-calendar-alt me-1"></i>
                                         Current Status: ${currentStatus}
                                         ${(() => {
                                             if (donor.eligibility && donor.eligibility.length > 0) {
                                                 const latestEligibility = donor.eligibility[donor.eligibility.length - 1];
                                                 const status = String(latestEligibility.status || '').toLowerCase();
                                                 const startDate = latestEligibility.start_date ? new Date(latestEligibility.start_date) : null;
                                                 const endDate = latestEligibility.end_date ? new Date(latestEligibility.end_date) : null;
                                                 const today = new Date();
                                                 
                                                 function calculateRemainingDays() {
                                                     if (status === 'approved' && startDate) {
                                                         const threeMonthsLater = new Date(startDate);
                                                         threeMonthsLater.setMonth(threeMonthsLater.getMonth() + 3);
                                                         const endOfDay = new Date(threeMonthsLater);
                                                         endOfDay.setHours(23, 59, 59, 999);
                                                         return Math.ceil((endOfDay - today) / (1000 * 60 * 60 * 24));
                                                     } else if (endDate) {
                                                         const endOfDay = new Date(endDate);
                                                         endOfDay.setHours(23, 59, 59, 999);
                                                         return Math.ceil((endOfDay - today) / (1000 * 60 * 60 * 24));
                                                     }
                                                     return null;
                                                 }
                                                 
                                                 const remainingDays = calculateRemainingDays();
                                                 if (remainingDays !== null && remainingDays > 0) {
                                                     // Color based on eligibility status
                                                     let color = '#17a2b8'; // Default blue
                                                     if (status === 'refused') {
                                                         color = '#dc3545'; // Red
                                                     } else if (status === 'deferred' || status === 'temporary_deferred') {
                                                         color = '#ffc107'; // Yellow
                                                     } else if (status === 'approved' || status === 'eligible') {
                                                         color = '#28a745'; // Green
                                                     }
                                                     return ` • <span style="font-weight: bold; color: ${color};">${remainingDays} days left</span>`;
                                                 }
                                             }
                                             return '';
                                         })()}
                            </div>
                                     <h4 class="mb-1" style="color:#b22222; font-weight:700;">
                                         ${fullName}
                                     </h4>
                                     <div class="text-muted fw-medium">
                                         <i class="fas fa-user me-1"></i>
                                         ${safe(donor.age)}${donor.sex ? ', ' + donor.sex : ''}
                            </div>
                                 </div>
                                 <div class="text-end">
                                     <div class="mb-1">
                                         <div class="fw-bold text-dark mb-1">
                                             <i class="fas fa-id-card me-1"></i>
                                             Donor ID: ${safe(donor.prc_donor_number || 'N/A')}
                                         </div>
                                         <div class="blood-type-display" style="background-color: #8B0000; color: white; border-radius: 20px; padding: 12px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 100px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                             <div style="font-size: 0.8rem; font-weight: 500; line-height: 1; margin-bottom: 2px; opacity: 0.9;">Blood Type</div>
                                             <div style="font-size: 1.4rem; font-weight: 700; line-height: 1;">${safe(getBloodTypeFromEligibility(donor) || 'N/A')}</div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <hr class="my-2" style="border-color: #b22222; opacity: 0.3;"/>
                            </div>`;
                        
                     // Donor Information Section (Match Physical Exam Modal Style)
                            donorInfoHTML += `
                        <div class="mb-3">
                             <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">Donor Information</h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                     <label class="form-label fw-semibold">Birthdate</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.birthdate)}</div>
                                    </div>
                                <div class="col-md-6">
                                     <label class="form-label fw-semibold">Civil Status</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.civil_status)}</div>
                                            </div>
                                 <div class="col-md-12">
                                     <label class="form-label fw-semibold">Address</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.permanent_address)}</div>
                                        </div>
                                 <div class="col-md-4">
                                     <label class="form-label fw-semibold">Nationality</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.nationality)}</div>
                                            </div>
                                 <div class="col-md-4">
                                     <label class="form-label fw-semibold">Mobile Number</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.mobile || donor.telephone)}</div>
                                            </div>
                                 <div class="col-md-4">
                                     <label class="form-label fw-semibold">Occupation</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.occupation)}</div>
                                        </div>
                                    </div>
                                </div>`;
                    
                    
                    // Determine if donor is New; if so, hide history sections
                    const __elig = Array.isArray(donor.eligibility) ? donor.eligibility : (donor.eligibility ? [donor.eligibility] : []);
                    const isNewDonor = ((window.currentDonorType || '').toLowerCase().startsWith('new')) || __elig.length === 0;
                    if (!isNewDonor) {
                    // Physical Assessment Table (based on eligibility table - show all records) - FIRST
                    const eligibilityRecords = Array.isArray(donor.eligibility) ? donor.eligibility : (donor.eligibility ? [donor.eligibility] : []);
                    let assessmentRows = '';
                    
                    if (eligibilityRecords.length > 0) {
                        eligibilityRecords.forEach((eligibility, index) => {
                            // Map eligibility data to match the image format
                            const examDate = formatDate(eligibility.start_date || eligibility.created_at || donor.latest_submission);
                            
                            // Vital Signs - assess actual values against normal ranges
                            let vitalSigns = 'Normal';
                            if (eligibility.blood_pressure && eligibility.pulse_rate && eligibility.body_temp) {
                                // Check blood pressure (normal: 90-140/60-90)
                                const bp = eligibility.blood_pressure;
                                const bpMatch = bp.match(/(\d+)\/(\d+)/);
                                if (bpMatch) {
                                    const systolic = parseInt(bpMatch[1]);
                                    const diastolic = parseInt(bpMatch[2]);
                                    if (systolic < 90 || systolic > 140 || diastolic < 60 || diastolic > 90) {
                                        vitalSigns = 'Abnormal';
                                    }
                                }
                                
                                // Check pulse rate (normal: 60-100 bpm)
                                const pulse = parseInt(eligibility.pulse_rate);
                                if (pulse < 60 || pulse > 100) {
                                    vitalSigns = 'Abnormal';
                                }
                                
                                // Check temperature (normal: 36.1-37.2°C or 97-99°F)
                                const temp = parseFloat(eligibility.body_temp);
                                if (temp < 36.1 || temp > 37.2) {
                                    vitalSigns = 'Abnormal';
                                }
                            } else {
                                vitalSigns = 'Incomplete';
                            }
                            
                            // Hematology - assess based on collection success and medical reasons
                            let hematology = 'Pass';
                            if (eligibility.collection_successful === false) {
                                hematology = 'Fail';
                            } else if (eligibility.disapproval_reason) {
                                const reason = eligibility.disapproval_reason.toLowerCase();
                                if (reason.includes('hemoglobin') || reason.includes('hematocrit') || 
                                    reason.includes('blood count') || reason.includes('anemia') ||
                                    reason.includes('low iron') || reason.includes('blood disorder')) {
                                    hematology = 'Fail';
                                }
                            }
                            
                            // Fitness Result - map from eligibility status
                            let fitnessResult = 'Eligible';
                            if (eligibility.status === 'deferred' || eligibility.status === 'temporary_deferred') {
                                fitnessResult = 'Deferred';
                            } else if (eligibility.status === 'eligible') {
                                fitnessResult = 'Eligible';
                            }
                            
                            // Remarks - show Approved if both medical_history_id and screening_form_id exist
                            let remarks = 'Pending';
                            if (eligibility.medical_history_id && eligibility.screening_id) {
                                remarks = 'Approved';
                            }
                            
                            // Get physician name from physical_examination table
                            let physician = '-';
                            if (eligibility.physical_examination && eligibility.physical_examination.physician) {
                                physician = eligibility.physical_examination.physician;
                            }
                            
                             console.log('Eligibility record:', eligibility);
                            assessmentRows += `
                                 <tr>
                                     <td class="text-center">${safe(examDate)}</td>
                                     <td class="text-center">${safe(vitalSigns)}</td>
                                     <td class="text-center">${safe(hematology)}</td>
                                     <td class="text-center">${safe(physician)}</td>
                                     <td class="text-center">${safe(fitnessResult)}</td>
                                     <td class="text-center">${safe(remarks)}</td>
                                     <td class="text-center">
                                         <button type="button" class="btn btn-sm btn-outline-primary" onclick="showPhysicalExaminationModal('${eligibility.eligibility_id}')" title="View Physical Examination Results">
                                             <i class="fas fa-eye"></i>
                                         </button>
                                     </td>
                                </tr>`;
                            });
                    }
                    
                    if (!assessmentRows) {
                        assessmentRows = `<tr><td colspan="7" class="text-center text-muted">No physical assessment recorded</td></tr>`;
                    }
                            donorInfoHTML += `
                        <div class="mb-3">
                             <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">Physical Assessment</h6>
                                        <div class="table-responsive">
                                 <table class="table table-sm table-bordered mb-0" style="border-radius: 10px; overflow: hidden;">
                                     <thead style="background: #b22222 !important; color: white !important;">
                                         <tr>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Examination Date</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Vital Signs</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Hematology</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Physician</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Fitness Result</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Remarks</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Action</th>
                                                    </tr>
                                                </thead>
                                     <tbody id="returningAssessmentRows">${assessmentRows}</tbody>
                                            </table>
                                    </div>
                                </div>`;
                    
                    // Donation History Table (based on eligibility table - show all records) - SECOND
                    const donationEligibilityRecords = Array.isArray(donor.eligibility) ? donor.eligibility : (donor.eligibility ? [donor.eligibility] : []);
                    let donationRows = '';
                    
                    // Add a new empty row at the TOP if needs_review is true (for pending review)
                    if (donor.needs_review === true || donor.needs_review === 'true' || donor.needs_review === 1) {
                         donationRows += `
                             <tr>
                                 <td class="text-center">-</td>
                                 <td class="text-center">-</td>
                                 <td class="text-center">-</td>
                                 <td class="text-center"><span class="text-warning">Pending</span></td>
                            </tr>`;
                    }
                    
                    if (donationEligibilityRecords.length > 0) {
                        donationEligibilityRecords.forEach((el, index) => {
                            // Only show records that have actual donation data
                            if (el.start_date || el.created_at) {
                                // Determine medical history status with color coding
                                let medicalStatus = 'Pending';
                                let statusClass = 'text-warning';
                                
                                // If medical_history_id exists, show Successful
                                if (el.medical_history_id) {
                                    medicalStatus = 'Successful';
                                    statusClass = 'text-success';
                                }
                                // Default fallback
                                else {
                                    medicalStatus = 'Pending';
                                    statusClass = 'text-warning';
                                }
                                
                                 donationRows += `
                                     <tr>
                                         <td class="text-center">${safe(formatDate(el.start_date || el.created_at))}</td>
                                         <td class="text-center">${safe(el.registration_channel || 'System')}</td>
                                         <td class="text-center">${safe(formatDate(el.end_date) || '-')}</td>
                                         <td class="text-center"><span class="${statusClass}">${safe(medicalStatus)}</span></td>
                                     </tr>`;
                            }
                        });
                    }
                    
                    if (!donationRows) {
                        donationRows = `<tr><td colspan="4" class="text-center text-muted">No donation history available</td></tr>`;
                    }
                        donorInfoHTML += `
                         <div class="mb-3">
                             <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">Donation History</h6>
                            <div class="table-responsive">
                                 <table class="table table-sm table-bordered mb-0" style="border-radius: 10px; overflow: hidden;">
                                     <thead style="background: #b22222 !important; color: white !important;">
                                         <tr>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Last Donation Date</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Gateway</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Next Eligible Date</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Medical History</th>
                                        </tr>
                                    </thead>
                                     <tbody>${donationRows}</tbody>
                                </table>
                                </div>
                        </div>`;
                    }
                    
                    // If returning, enrich assessment section with eligibility details (all records)
                    if (modalContextType === 'returning' && donor.donor_id && donor.eligibility) {
                        const eligibilityRecords = Array.isArray(donor.eligibility) ? donor.eligibility : [donor.eligibility];
                        let allRowsHtml = '';
                        
                        eligibilityRecords.forEach((el, index) => {
                            // Build rows for all eligibility records
                            const examDate = formatDate(el.start_date || el.created_at || donor.latest_submission) || 'N/A';
                            // Vital Signs - assess actual values against normal ranges
                            let vitalSigns = 'Normal';
                            if (el.blood_pressure && el.pulse_rate && el.body_temp) {
                                // Check blood pressure (normal: 90-140/60-90)
                                const bp = el.blood_pressure;
                                const bpMatch = bp.match(/(\d+)\/(\d+)/);
                                if (bpMatch) {
                                    const systolic = parseInt(bpMatch[1]);
                                    const diastolic = parseInt(bpMatch[2]);
                                    if (systolic < 90 || systolic > 140 || diastolic < 60 || diastolic > 90) {
                                        vitalSigns = 'Abnormal';
                                    }
                                }
                                
                                // Check pulse rate (normal: 60-100 bpm)
                                const pulse = parseInt(el.pulse_rate);
                                if (pulse < 60 || pulse > 100) {
                                    vitalSigns = 'Abnormal';
                                }
                                
                                // Check temperature (normal: 36.1-37.2°C)
                                const temp = parseFloat(el.body_temp);
                                if (temp < 36.1 || temp > 37.2) {
                                    vitalSigns = 'Abnormal';
                                }
                            } else {
                                vitalSigns = 'Incomplete';
                            }
                            const hematology = el.collection_successful ? 'Pass' : (el.collection_successful === false ? 'Fail' : 'Pass');
                            const physician = '-'; // Default as shown in image
                            const fitnessResult = el.status === 'eligible' ? 'Eligible' : 
                                                el.status === 'deferred' ? 'Deferred' : 
                                                el.status === 'temporary_deferred' ? 'Temporary Deferred' : 'Eligible';
                            let remarks = 'Pending';
                            const parts = [];
                            if (el.disapproval_reason) parts.push(el.disapproval_reason);
                            if (el.donor_reaction) parts.push(el.donor_reaction);
                            const details = parts.join(' | ');
                            const success = el.collection_successful === true || el.status === 'eligible';
                            const fail = el.collection_successful === false || el.status === 'deferred' || el.status === 'temporary_deferred';
                            if (success) {
                                remarks = 'Successful';
                            } else if (fail) {
                                remarks = details ? `Failed - ${details}` : 'Failed';
                            }
                            
                            allRowsHtml += '<tr><td>' + examDate + '</td><td>' + vitalSigns + '</td><td>' + hematology + '</td><td>' + physician + '</td><td>' + fitnessResult + '</td><td>' + remarks + '</td><td><button type="button" class="btn btn-sm btn-outline-primary" onclick="showPhysicalExaminationModal(\'' + el.eligibility_id + '\')"><i class="fas fa-eye"></i></button></td></tr>';
                        });
                        
                        const tbody = document.getElementById('returningAssessmentRows');
                        if (tbody) tbody.innerHTML = allRowsHtml;
                    }
                } else {
                    // Fallback when no donor data is available
                    donorInfoHTML = `
                        <div class="alert alert-warning">
                            <h6>No Donor Data Available</h6>
                            <p>Unable to load donor information. Please try again or contact support if the problem persists.</p>
                            <div class="small text-muted">
                                <strong>Debug Info:</strong><br>
                                Donor Data: ${donorData ? 'Available' : 'Not available'}<br>
                                Success: ${donorData && typeof donorData === 'object' && 'success' in donorData ? donorData.success : 'N/A'}<br>
                                Has Data: ${donorData && donorData.data ? 'Yes' : 'No'}<br>
                                Error: ${donorData && donorData.error ? donorData.error : 'None'}<br>
                                <details>
                                    <summary>Raw Data (click to expand)</summary>
                                    <pre>${JSON.stringify(donorData, null, 2)}</pre>
                                </details>
                            </div>
                        </div>`;
                        
                }
                
                // Returning banner removed as requested
                
                deferralStatusContent.innerHTML = donorInfoHTML;
                
                // Ensure proceed button visibility reflects current stage capability
                try {
                    const proceedButton = getProceedButton();
                    if (proceedButton && proceedButton.style) {
                        // Show button for donors who can process OR have needs_review=true
                        const hasNeedsReview = currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review;
                        const showReview = allowProcessing || currentStage === 'medical_review' || hasNeedsReview;
                        proceedButton.style.display = showReview ? 'inline-block' : 'none';
                        proceedButton.textContent = 'Proceed to Medical History';
                    }
            // Hide Mark for Medical Review button when needs_review is already true
            try {
                const markBtn = document.getElementById('markReviewFromMain');
                const hasNeedsReviewFlag = currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review === true;
                if (markBtn && hasNeedsReviewFlag) {
                    markBtn.style.display = 'none';
                    markBtn.style.visibility = 'hidden';
                    markBtn.style.opacity = '0';
                }
            } catch (_) {}
                } catch (e) {}
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
            
            // Handle proceed button click with confirmation
            function openMedicalHistoryForCurrentDonor() {
                if (!currentDonorId) return;
                
                // Show confirmation modal before proceeding
                showProcessMedicalHistoryConfirmation();
            }
            
            // Show confirmation modal for processing medical history
            function showProcessMedicalHistoryConfirmation() {
                const message = 'This will redirect you to the medical history the donor just submitted. Do you want to proceed?';
                
                // Create confirmation modal matching the Submit Medical History design
                const modalHTML = `
                    <div id="processMedicalHistoryModal" style="
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.5);
                        z-index: 99999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">
                        <div style="
                            background: white;
                            border-radius: 10px;
                            max-width: 500px;
                            width: 90%;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                            overflow: hidden;
                        ">
                            <div style="
                                background: #9c0000;
                                color: white;
                                padding: 15px 20px;
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                            ">
                                <h5 style="margin: 0; font-weight: bold;">
                                    Process Medical History
                                </h5>
                                <button onclick="closeProcessMedicalHistoryModal()" style="
                                    background: none;
                                    border: none;
                                    color: white;
                                    font-size: 20px;
                                    cursor: pointer;
                                ">&times;</button>
                            </div>
                            <div style="padding: 20px;">
                                <p style="font-size: 14px; line-height: 1.5; margin-bottom: 20px; color: #333;">${message}</p>
                                <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                                    <button onclick="closeProcessMedicalHistoryModal()" style="
                                        background: #6c757d;
                                        color: white;
                                        border: none;
                                        padding: 8px 20px;
                                        border-radius: 4px;
                                        cursor: pointer;
                                        font-size: 14px;
                                    ">Cancel</button>
                                    <button onclick="confirmProcessMedicalHistory()" style="
                                        background: #9c0000;
                                        color: white;
                                        border: none;
                                        padding: 8px 20px;
                                        border-radius: 4px;
                                        cursor: pointer;
                                        font-size: 14px;
                                    ">Proceed</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Remove any existing modal
                const existingModal = document.getElementById('processMedicalHistoryModal');
                if (existingModal) {
                    existingModal.remove();
                }
                
                // Add modal to page
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                
                // Set up confirmation function
                window.confirmProcessMedicalHistory = function() {
                    closeProcessMedicalHistoryModal();
                    // Always proceed directly to Medical History (skip Physical Examination preview)
                    proceedToMedicalHistoryModal();
                };
                
                window.closeProcessMedicalHistoryModal = function() {
                    const modal = document.getElementById('processMedicalHistoryModal');
                    if (modal) {
                        modal.remove();
                    }
                };
            }
            
            
            // Deprecated duplicate renderer removed. Use the unified renderer defined later in the file.
            
            // Function to proceed to medical history modal
            function proceedToMedicalHistoryModal() {
                // Hide the deferral status modal first
                const modalEl = document.getElementById('deferralStatusModal');
                const modalInstance = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
                if (modalInstance) modalInstance.hide();
                
                // Reset initialization flags to ensure fresh initialization
                window.editFunctionalityInitialized = false;
                window.medicalHistoryQuestionsGenerated = false;
                // Allow MH modal script to re-initialize cleanly on reopen
                try { window.__mhEditInit = false; } catch (e) {}
                
                // Get or create the medical history modal instance - reuse for better performance
                const medicalHistoryModalEl = document.getElementById('medicalHistoryModal');
                let medicalHistoryModal = medicalHistoryModalEl ? bootstrap.Modal.getInstance(medicalHistoryModalEl) : null;
                if (!medicalHistoryModal) {
                    medicalHistoryModal = new bootstrap.Modal(medicalHistoryModalEl);
                }
                const modalContent = document.getElementById('medicalHistoryModalContent');
                
                // Reset modal content to loading state
                modalContent.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                // Show the modal
                medicalHistoryModal.show();
                
                // Load the medical history form content
                fetch('../../src/views/forms/medical-history-modal-content.php?donor_id=' + currentDonorId)
                    .then(response => response.text())
                    .then(data => {
                        modalContent.innerHTML = data;
                        
                        // Remove only executable script tags; keep JSON data such as <script type="application/json" id="modalData">
                        const scripts = modalContent.querySelectorAll('script');
                        scripts.forEach(script => {
                            try {
                                const type = (script.getAttribute('type') || '').toLowerCase();
                                if (type === 'application/json') return; // preserve data scripts needed by the renderer
                                script.remove();
                            } catch (e) {
                                console.warn('Could not process script tag:', e);
                            }
                        });
                        
                        // Manually call known functions that might be needed
                        try {
                            if (typeof window.initializeMedicalHistoryApproval === 'function') {
                                window.initializeMedicalHistoryApproval();
                            }
                        } catch(e) {
                            console.warn('Could not execute initializeMedicalHistoryApproval:', e);
                        }
                        
                        // Add form submission interceptor to prevent submissions without proper action
                        const form = document.getElementById('modalMedicalHistoryForm');
                        if (form) {
                            
                            // Remove any existing submit event listeners
                            const newForm = form.cloneNode(true);
                            form.parentNode.replaceChild(newForm, form);
                            
                            // Add our controlled submit handler
                            newForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                
                                // For form submissions, always use quiet save
                                saveFormDataQuietly();
                                
                                return false;
                            });
                        }
                        
                        // After loading content, generate the questions
                        generateMedicalHistoryQuestions();
                    })
                    .catch(error => {
                        modalContent.innerHTML = '<div class="alert alert-danger"><h6>Error Loading Form</h6><p>Unable to load the medical history form. Please try again.</p><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>';
                    });
            }

            // Helper function to get proceed button
            function getProceedButton() {
                return document.getElementById('proceedToMedicalHistory');
            }
            
            // Bind proceed button event listener immediately - no delay needed
                const proceedButton = getProceedButton();
                if (proceedButton && proceedButton.addEventListener) {
                    proceedButton.addEventListener('click', function() {
                        openMedicalHistoryForCurrentDonor();
                    });
                }
                
                // Bind physical examination modal proceed button
                // Remove duplicate proceed button from Physical Examination modal footer if present
                try {
                    const dupBtn = document.getElementById('proceedToMedicalHistoryFromPhysical');
                    if (dupBtn && dupBtn.parentNode) {
                        dupBtn.parentNode.removeChild(dupBtn);
                    }
                } catch (_) {}

            // Ensure backdrops are cleaned up when key modals are closed
            ['deferralStatusModal', 'eligibilityAlertModal', 'stageNoticeModal', 'returningInfoModal']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.addEventListener('hidden.bs.modal', cleanupModalBackdrops);
                    }
                });
            
            // performOptimizedSearch function removed - now handled by external JS files
            
            // Update placeholder based on selected category
            if (searchCategory && searchCategory.addEventListener) {
            searchCategory.addEventListener('change', function() {
                const category = this.value;
                let placeholder = 'Search by ';
                switch(category) {
                    case 'surname': placeholder += 'surname...'; break;
                    case 'firstname': placeholder += 'first name...'; break;
                    case 'age': placeholder += 'age...'; break;
                    default: placeholder = 'Search donors...';
                }
                    if (searchInput) {
                searchInput.placeholder = placeholder;
                    }
            });
            }

            // Handle Mark for Medical Review from main details modal
            // Do not bind a global handler; enabled per-row only for returning
        });



        // Function to generate medical history questions in the modal
        function generateMedicalHistoryQuestions() {
            
            // Prevent multiple initialization
            if (window.medicalHistoryQuestionsGenerated) {
                return;
            }
            window.medicalHistoryQuestionsGenerated = true;
            
            // Get data from the JSON script tag
            const modalDataScript = document.getElementById('modalData');
            if (!modalDataScript) {
                return;
            }
            
            let modalData;
            try {
                modalData = JSON.parse(modalDataScript.textContent);
            } catch (e) {
                return;
            }
            
            
            const modalMedicalHistoryData = modalData.medicalHistoryData;
            const modalDonorSex = modalData.donorSex;
            const modalUserRole = modalData.userRole;
            const modalIsMale = modalDonorSex === 'male';
            
            
            // Only make fields required for reviewers (who can edit)
            const modalIsReviewer = modalUserRole === 'reviewer';
            const modalRequiredAttr = modalIsReviewer ? 'required' : '';
            
            // Define questions by step
            const questionsByStep = {
                1: [
                    { q: 1, text: "Do you feel well and healthy today?" },
                    { q: 2, text: "Have you ever been refused as a blood donor or told not to donate blood for any reasons?" },
                    { q: 3, text: "Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?" },
                    { q: 4, text: "Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?" },
                    { q: 5, text: "Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?" },
                    { q: 6, text: "In the last 3 DAYS have you taken aspirin?" },
                    { q: 7, text: "In the past 4 WEEKS have you taken any medications and/or vaccinations?" },
                    { q: 8, text: "In the past 3 MONTHS have you donated whole blood, platelets or plasma?" }
                ],
                2: [
                    { q: 9, text: "Been to any places in the Philippines or countries infected with ZIKA Virus?" },
                    { q: 10, text: "Had sexual contact with a person who was confirmed to have ZIKA Virus Infection?" },
                    { q: 11, text: "Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?" }
                ],
                3: [
                    { q: 12, text: "Received blood, blood products and/or had tissue/organ transplant or graft?" },
                    { q: 13, text: "Had surgical operation or dental extraction?" },
                    { q: 14, text: "Had a tattoo applied, ear and body piercing, acupuncture, needle stick Injury or accidental contact with blood?" },
                    { q: 15, text: "Had sexual contact with high risks individuals or in exchange for material or monetary gain?" },
                    { q: 16, text: "Engaged in unprotected, unsafe or casual sex?" },
                    { q: 17, text: "Had jaundice/hepatitis/personal contact with person who had hepatitis?" },
                    { q: 18, text: "Been incarcerated, Jailed or imprisoned?" },
                    { q: 19, text: "Spent time or have relatives in the United Kingdom or Europe?" }
                ],
                4: [
                    { q: 20, text: "Travelled or lived outside of your place of residence or outside the Philippines?" },
                    { q: 21, text: "Taken prohibited drugs (orally, by nose, or by injection)?" },
                    { q: 22, text: "Used clotting factor concentrates?" },
                    { q: 23, text: "Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?" },
                    { q: 24, text: "Had Malaria or Hepatitis in the past?" },
                    { q: 25, text: "Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?" }
                ],
                5: [
                    { q: 26, text: "Cancer, blood disease or bleeding disorder (haemophilia)?" },
                    { q: 27, text: "Heart disease/surgery, rheumatic fever or chest pains?" },
                    { q: 28, text: "Lung disease, tuberculosis or asthma?" },
                    { q: 29, text: "Kidney disease, thyroid disease, diabetes, epilepsy?" },
                    { q: 30, text: "Chicken pox and/or cold sores?" },
                    { q: 31, text: "Any other chronic medical condition or surgical operations?" },
                    { q: 32, text: "Have you recently had rash and/or fever? Was/were this/these also associated with arthralgia or arthritis or conjunctivitis?" }
                ],
                6: [
                    { q: 33, text: "Are you currently pregnant or have you ever been pregnant?" },
                    { q: 34, text: "When was your last childbirth?" },
                    { q: 35, text: "In the past 1 YEAR, did you have a miscarriage or abortion?" },
                    { q: 36, text: "Are you currently breastfeeding?" },
                    { q: 37, text: "When was your last menstrual period?" }
                ]
            };
            
            // Define remarks options based on question type
            const modalRemarksOptions = {
                1: ["None", "Feeling Unwell", "Fatigue", "Fever", "Other Health Issues"],
                2: ["None", "Low Hemoglobin", "Medical Condition", "Recent Surgery", "Other Refusal Reason"],
                3: ["None", "HIV Test", "Hepatitis Test", "Other Test Purpose"],
                4: ["None", "Understood", "Needs More Information"],
                5: ["None", "Beer", "Wine", "Liquor", "Multiple Types"],
                6: ["None", "Pain Relief", "Fever", "Other Medication Purpose"],
                7: ["None", "Antibiotics", "Vitamins", "Vaccines", "Other Medications"],
                8: ["None", "Red Cross Donation", "Hospital Donation", "Other Donation Type"],
                9: ["None", "Local Travel", "International Travel", "Specific Location"],
                10: ["None", "Direct Contact", "Indirect Contact", "Suspected Case"],
                11: ["None", "Partner Travel History", "Unknown Exposure", "Other Risk"],
                12: ["None", "Blood Transfusion", "Organ Transplant", "Other Procedure"],
                13: ["None", "Major Surgery", "Minor Surgery", "Dental Work"],
                14: ["None", "Tattoo", "Piercing", "Acupuncture", "Blood Exposure"],
                15: ["None", "High Risk Contact", "Multiple Partners", "Other Risk Factors"],
                16: ["None", "Unprotected Sex", "Casual Contact", "Other Risk Behavior"],
                17: ["None", "Personal History", "Family Contact", "Other Exposure"],
                18: ["None", "Short Term", "Long Term", "Other Details"],
                19: ["None", "UK Stay", "Europe Stay", "Duration of Stay"],
                20: ["None", "Local Travel", "International Travel", "Duration"],
                21: ["None", "Recreational", "Medical", "Other Usage"],
                22: ["None", "Treatment History", "Current Use", "Other Details"],
                23: ["None", "HIV", "Hepatitis", "Syphilis", "Malaria"],
                24: ["None", "Past Infection", "Treatment History", "Other Details"],
                25: ["None", "Current Infection", "Past Treatment", "Other Details"],
                26: ["None", "Cancer Type", "Blood Disease", "Bleeding Disorder"],
                27: ["None", "Heart Disease", "Surgery History", "Current Treatment"],
                28: ["None", "Active TB", "Asthma", "Other Respiratory Issues"],
                29: ["None", "Kidney Disease", "Thyroid Issue", "Diabetes", "Epilepsy"],
                30: ["None", "Recent Infection", "Past Infection", "Other Details"],
                31: ["None", "Condition Type", "Treatment Status", "Other Details"],
                32: ["None", "Recent Fever", "Rash", "Joint Pain", "Eye Issues"],
                33: ["None", "Current Pregnancy", "Past Pregnancy", "Other Details"],
                34: ["None", "Less than 6 months", "6-12 months ago", "More than 1 year ago"],
                35: ["None", "Less than 3 months ago", "3-6 months ago", "6-12 months ago"],
                36: ["None", "Currently Breastfeeding", "Recently Stopped", "Other"],
                37: ["None", "Within last week", "1-2 weeks ago", "2-4 weeks ago", "More than 1 month ago"]
            };
            
            // Get the field name based on the data structure
            const getModalFieldName = (count) => {
                const fields = {
                    1: 'feels_well', 2: 'previously_refused', 3: 'testing_purpose_only', 4: 'understands_transmission_risk',
                    5: 'recent_alcohol_consumption', 6: 'recent_aspirin', 7: 'recent_medication', 8: 'recent_donation',
                    9: 'zika_travel', 10: 'zika_contact', 11: 'zika_sexual_contact', 12: 'blood_transfusion',
                    13: 'surgery_dental', 14: 'tattoo_piercing', 15: 'risky_sexual_contact', 16: 'unsafe_sex',
                    17: 'hepatitis_contact', 18: 'imprisonment', 19: 'uk_europe_stay', 20: 'foreign_travel',
                    21: 'drug_use', 22: 'clotting_factor', 23: 'positive_disease_test', 24: 'malaria_history',
                    25: 'std_history', 26: 'cancer_blood_disease', 27: 'heart_disease', 28: 'lung_disease',
                    29: 'kidney_disease', 30: 'chicken_pox', 31: 'chronic_illness', 32: 'recent_fever',
                    33: 'pregnancy_history', 34: 'last_childbirth', 35: 'recent_miscarriage', 36: 'breastfeeding',
                    37: 'last_menstruation'
                };
                return fields[count];
            };
            
                         // Generate questions for each step
             for (let step = 1; step <= 6; step++) {
                 // Skip step 6 for male donors
                 if (step === 6 && modalIsMale) {
                     continue;
                 }
                 
                 const stepContainer = document.querySelector(`[data-step-container="${step}"]`);
                 if (!stepContainer) {
                     //console.error(`Step container ${step} not found`);
                     continue;
                 }
                 
                 const stepQuestions = questionsByStep[step] || [];
                 
                 stepQuestions.forEach(questionData => {
                     const fieldName = getModalFieldName(questionData.q);
                     const value = modalMedicalHistoryData ? modalMedicalHistoryData[fieldName] : null;
                     const remarks = modalMedicalHistoryData ? modalMedicalHistoryData[fieldName + '_remarks'] : null;
                     
                     // Create a form group for each question
                     const questionRow = document.createElement('div');
                     questionRow.className = 'form-group';
                     questionRow.innerHTML = `
                         <div class="question-number">${questionData.q}</div>
                         <div class="question-text">${questionData.text}</div>
                         <div class="radio-cell">
                             <label class="radio-container">
                                 <input type="radio" name="q${questionData.q}" value="Yes" ${value === true ? 'checked' : ''} ${modalRequiredAttr}>
                                 <span class="checkmark"></span>
                             </label>
                         </div>
                         <div class="radio-cell">
                             <label class="radio-container">
                                 <input type="radio" name="q${questionData.q}" value="No" ${value === false ? 'checked' : ''} ${modalRequiredAttr}>
                                 <span class="checkmark"></span>
                             </label>
                         </div>
                         <div class="remarks-cell">
                             <select class="remarks-input" name="q${questionData.q}_remarks" ${modalRequiredAttr}>
                                 ${modalRemarksOptions[questionData.q].map(option => 
                                     `<option value="${option}" ${remarks === option ? 'selected' : ''}>${option}</option>`
                                 ).join('')}
                             </select>
                         </div>
                     `;
                     
                     stepContainer.appendChild(questionRow);
                 });
             }
            
            // Initialize step navigation
            dashboardInitializeModalStepNavigation(modalUserRole, modalIsMale);
            
            // Default behavior: make all fields read-only until Edit is pressed (for all roles)
            // Use requestAnimationFrame for instant DOM update without blocking
            requestAnimationFrame(() => {
                const radioButtons = document.querySelectorAll('#modalMedicalHistoryForm input[type="radio"]');
                const selectFields = document.querySelectorAll('#modalMedicalHistoryForm select.remarks-input');
                const textFields = document.querySelectorAll('#modalMedicalHistoryForm input[type="text"], #modalMedicalHistoryForm textarea');

                radioButtons.forEach(el => { el.disabled = true; el.setAttribute('data-originally-disabled', 'true'); });
                selectFields.forEach(el => { el.disabled = true; el.setAttribute('data-originally-disabled', 'true'); });
                textFields.forEach(el => { el.disabled = true; el.setAttribute('data-originally-disabled', 'true'); });

                // Initialize edit functionality after locking inputs
                dashboardInitializeEditFunctionality();
            });
        }
        
        // Single edit functionality function to avoid duplicates
        function dashboardInitializeEditFunctionality() {
            //console.log('Initializing edit functionality...');
            
            // Remove any existing event listeners to prevent duplicates
            if (window.editFunctionalityInitialized) {
                //console.log('Edit functionality already initialized, skipping...');
                return;
            }
            window.editFunctionalityInitialized = true;
            
            // Add event listener for edit buttons
            document.addEventListener('click', function(e) {
                if (e.target && (e.target.classList.contains('edit-button') || e.target.closest('.edit-button'))) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    //console.log('Edit button clicked - enabling form fields');
                    
                    // Enable all form fields
                    const form = document.getElementById('modalMedicalHistoryForm');
                    if (form) {
                        // Enable radio buttons
                        form.querySelectorAll('input[type="radio"]').forEach(radio => {
                            radio.disabled = false;
                            radio.removeAttribute('data-originally-disabled');
                        });
                        
                        // Enable select fields
                        form.querySelectorAll('select.remarks-input').forEach(select => {
                            select.disabled = false;
                            select.removeAttribute('data-originally-disabled');
                        });
                        
                        // Enable text inputs
                        form.querySelectorAll('input[type="text"]').forEach(input => {
                            input.readOnly = false;
                            input.removeAttribute('data-originally-readonly');
                        });
                        
                        //console.log('Form fields enabled for editing');
                        
                        // Hide edit button; do not expose Save in MH modal
                        const editButton = e.target.classList.contains('edit-button') ? e.target : e.target.closest('.edit-button');
                        if (editButton) {
                            // Keep layout space and dimensions to avoid shifting Next button
                            const rect = editButton.getBoundingClientRect();
                            editButton.style.width = rect.width + 'px';
                            editButton.style.height = rect.height + 'px';
                            editButton.style.visibility = 'hidden';
                            editButton.style.pointerEvents = 'none';
                        }
                        // Explicitly hide any legacy save button if present
                        const saveButton = form.querySelector('.save-button');
                        if (saveButton) {
                            saveButton.style.display = 'none';
                        }
                    }
                    
                    return false;
                }
            });
            
            // Remove save behavior in MH modal: swallow any Save button clicks if they exist
            document.addEventListener('click', function(e) {
                if (e.target && (e.target.classList.contains('save-button') || (typeof e.target.textContent === 'string' && e.target.textContent.trim() === 'Save'))) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        }
        
        // Initialize step navigation for the modal
        function dashboardInitializeModalStepNavigation(userRole, isMale) {
            let currentStep = 1;
            const totalSteps = isMale ? 5 : 6;
            
            const stepIndicators = document.querySelectorAll('#modalStepIndicators .step');
            const stepConnectors = document.querySelectorAll('#modalStepIndicators .step-connector');
            const formSteps = document.querySelectorAll('#modalMedicalHistoryForm .form-step');
            const prevButton = document.getElementById('modalPrevButton');
            const nextButton = document.getElementById('modalNextButton');
            const errorMessage = document.getElementById('modalValidationError');
            
            // Hide step 6 for male donors
            if (isMale) {
                const step6 = document.getElementById('modalStep6');
                const line56 = document.getElementById('modalLine5-6');
                if (step6) step6.style.display = 'none';
                if (line56) line56.style.display = 'none';
            }
            
            function updateStepDisplay() {
                // Hide all steps
                formSteps.forEach(step => {
                    step.classList.remove('active');
                });
                
                // Show current step
                const activeStep = document.querySelector(`#modalMedicalHistoryForm .form-step[data-step="${currentStep}"]`);
                if (activeStep) {
                    activeStep.classList.add('active');
                }
                
                // Update step indicators
                stepIndicators.forEach(indicator => {
                    const step = parseInt(indicator.getAttribute('data-step'));
                    
                    if (step < currentStep) {
                        indicator.classList.add('completed');
                        indicator.classList.add('active');
                    } else if (step === currentStep) {
                        indicator.classList.add('active');
                        indicator.classList.remove('completed');
                    } else {
                        indicator.classList.remove('active');
                        indicator.classList.remove('completed');
                    }
                });
                
                // Update step connectors
                stepConnectors.forEach((connector, index) => {
                    if (index + 1 < currentStep) {
                        connector.classList.add('active');
                    } else {
                        connector.classList.remove('active');
                    }
                });
                
                // Update buttons
                if (currentStep === 1) {
                    prevButton.style.display = 'none';
                } else {
                    prevButton.style.display = 'block';
                }
                
                if (currentStep === totalSteps) {
                    if (userRole === 'reviewer') {
                        nextButton.innerHTML = 'DECLINE';
                        nextButton.onclick = (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            submitModalForm('decline');
                            return false;
                        };
                        
                        // Add approve button
                        if (!document.getElementById('modalApproveButton')) {
                            const approveBtn = document.createElement('button');
                            approveBtn.className = 'next-button';
                            approveBtn.innerHTML = 'APPROVE';
                            approveBtn.id = 'modalApproveButton';
                            approveBtn.onclick = (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                submitModalForm('approve');
                                return false;
                            };
                            nextButton.parentNode.appendChild(approveBtn);
                        }
                    } else {
                        nextButton.innerHTML = 'NEXT';
                        nextButton.onclick = (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            submitModalForm('next');
                            return false;
                        };
                    }
                } else {
                    nextButton.innerHTML = 'Next →';
                    nextButton.onclick = (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        if (validateCurrentModalStep()) {
                            currentStep++;
                            updateStepDisplay();
                            errorMessage.style.display = 'none';
                        }
                        return false;
                    };
                    
                    // Remove approve button if it exists
                    const approveBtn = document.getElementById('modalApproveButton');
                    if (approveBtn) {
                        approveBtn.remove();
                    }
                }
            }
            
            function validateCurrentModalStep() {
                const currentStepElement = document.querySelector(`#modalMedicalHistoryForm .form-step[data-step="${currentStep}"]`);
                if (!currentStepElement) return false;
                
                const radioGroups = {};
                const radios = currentStepElement.querySelectorAll('input[type="radio"]');
                
                radios.forEach(radio => {
                    radioGroups[radio.name] = true;
                });
                
                let allAnswered = true;
                for (const groupName in radioGroups) {
                    const answered = document.querySelector(`input[name="${groupName}"]:checked`) !== null;
                    if (!answered) {
                        allAnswered = false;
                        break;
                    }
                }
                
                if (!allAnswered) {
                    errorMessage.style.display = 'block';
                    errorMessage.textContent = 'Please answer all questions before proceeding to the next step.';
                    return false;
                }
                
                return true;
            }
            
            // Bind event handlers
            prevButton.addEventListener('click', () => {
                if (currentStep > 1) {
                    currentStep--;
                    updateStepDisplay();
                    errorMessage.style.display = 'none';
                }
            });
            
            // Initialize display
            updateStepDisplay();
        }

        // Function to handle modal form submission
        function submitModalForm(action) {
            let message = '';
            if (action === 'approve') {
                message = 'Are you sure you want to approve this donor and proceed to the declaration form?';
            } else if (action === 'decline') {
                message = 'Are you sure you want to decline this donor?';
            } else if (action === 'next') {
                message = 'Please confirm if the donor is ready for the next step based on the medical history interview, and proceed with Initial Screening.';
            }
            
            // Use custom confirmation instead of browser confirm
            if (window.customConfirm) {
                window.customConfirm(message, function() {
                    // Mark that user already confirmed to avoid double confirmation later
                    try { window.__mhConfirmed = true; } catch (_) {}
                    processFormSubmission(action);
                });
            } else {
                // Fallback to browser confirm if custom confirm is not available
                if (confirm(message)) {
                    try { window.__mhConfirmed = true; } catch (_) {}
                    processFormSubmission(action);
                }
            }
        }
        
        // Quiet save function - just updates the database without any UI changes
        function saveFormDataQuietly() {
            //console.log('Saving edited data...');
            
            const form = document.getElementById('modalMedicalHistoryForm');
            if (!form) {
                //console.error('modalMedicalHistoryForm not found');
                return;
            }
            
            const formData = new FormData(form);
            
            // Set action to 'next' for saving without approval changes
            formData.set('action', 'next');
            formData.set('modalSelectedAction', 'next');
            
            // Make a quiet AJAX request to save the data
            fetch('../../src/views/forms/medical-history-process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    //console.log('Data saved successfully');
                    // Optionally show a small success indicator
                    showQuietSuccessMessage();
                } else {
                    //console.error('Save failed:', data.message);
                    // Show error message
                    showQuietErrorMessage(data.message || 'Save failed');
                }
            })
            .catch(error => {
                //console.error('Save error:', error);
                showQuietErrorMessage('Network error occurred');
            });
        }
        
        // Show a small, non-intrusive success message
        function showQuietSuccessMessage() {
            // Create a small toast-like message
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                font-size: 14px;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            toast.textContent = 'Changes saved';
            document.body.appendChild(toast);
            
            // Show and auto-hide
            setTimeout(() => toast.style.opacity = '1', 10);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 2000);
        }
        
        // Show a small, non-intrusive error message
        function showQuietErrorMessage(message) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #dc3545;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                font-size: 14px;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            toast.textContent = 'Error: ' + message;
            document.body.appendChild(toast);
            
            // Show and auto-hide
            setTimeout(() => toast.style.opacity = '1', 10);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 4000);
        }

        // Separate function to handle the actual form submission
        function processFormSubmission(action) {
                // Set the action - if no action provided, default to 'next' for saving
                const finalAction = action || 'next';
                
                //console.log('processFormSubmission called with action:', action, 'final action:', finalAction);
                
                // Try to set the modalSelectedAction if it exists
                const modalSelectedActionInput = document.getElementById('modalSelectedAction');
                if (modalSelectedActionInput) {
                    modalSelectedActionInput.value = finalAction;
                    //console.log('Set modalSelectedAction to:', finalAction);
                } else {
                    //console.log('modalSelectedAction input not found');
                }
                
                // Submit the form via AJAX
                const form = document.getElementById('modalMedicalHistoryForm');
                if (!form) {
                    //console.error('modalMedicalHistoryForm not found');
                    return;
                }
                
                const formData = new FormData(form);
                
                // Make sure the action is set in the form data - this is the key fix
                formData.set('action', finalAction);
                formData.set('modalSelectedAction', finalAction);
                
                //console.log('Form data being sent:');
                for (let [key, value] of formData.entries()) {
                    if (key.includes('action')) {
                        //console.log(key + ':', value);
                    }
                }
                
                fetch('../../src/views/forms/medical-history-process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (action === 'next' || action === 'approve') {
                            // Close medical history modal first
                            const medicalModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                            medicalModal.hide();
                            
                            // Resolve donor_id from the form
                            const donorIdInput = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
                            const donorId = donorIdInput ? donorIdInput.value : null;
                            
                            // If Returning (Medical), ask to mark screening for review
                            try {
                                if (donorId && window.currentStage === 'medical_review' && (window.modalContextType === 'returning' || document.querySelector(`tr[data-donor-id="${donorId}"]`)?.getAttribute('data-donor-type')?.toLowerCase().includes('returning'))) {
                                    const csm = new bootstrap.Modal(document.getElementById('confirmScreeningMarkModal'));
                                    const btn = document.getElementById('confirmScreeningMarkBtn');
                                    btn.onclick = () => {
                                        fetch('../../assets/php_func/update_screening_needs_review.php', {
                                            method: 'POST',
                                            headers: { 'Accept': 'application/json' },
                                            body: new URLSearchParams({ donor_id: donorId })
                                        }).then(r => r.json()).finally(() => { csm.hide(); });
                                    };
                                    csm.show();
                                }
                            } catch (e) {}
                            
                            // After saving MH, require explicit confirmation before opening Initial Screening
                            if (donorId) {
                                try {
                                    const confirmModalEl = document.getElementById('dataProcessingConfirmModal');
                                    const confirmBtn = document.getElementById('confirmProcessingBtn');
                                    // If the action already went through a confirmation, skip this second confirmation
                                    const alreadyConfirmed = !!window.__mhConfirmed;
                                    if (alreadyConfirmed) {
                                        // Reset the flag and open screening immediately
                                        try { window.__mhConfirmed = false; } catch (_) {}
                                        showScreeningFormModal(donorId);
                                    } else if (confirmModalEl && confirmBtn && window.bootstrap) {
                                        // Rebind click to avoid duplicate handlers
                                        const newBtn = confirmBtn.cloneNode(true);
                                        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
                                        newBtn.addEventListener('click', function() {
                                            const cm = window.bootstrap.Modal.getInstance(confirmModalEl) || new window.bootstrap.Modal(confirmModalEl);
                                            cm.hide();
                                            // Only now open the Initial Screening modal
                                            showScreeningFormModal(donorId);
                                        });
                                        const cm = window.bootstrap.Modal.getInstance(confirmModalEl) || new window.bootstrap.Modal(confirmModalEl);
                                        cm.show();
                                    } else {
                                        // Fallback directly to screening when modal not available
                                        showScreeningFormModal(donorId);
                                    }
                                } catch (_) {
                                    // Fallback on any error
                                    showScreeningFormModal(donorId);
                                }
                            } else {
                                // Use custom modal instead of browser alert
                                if (window.customConfirm) {
                                    window.customConfirm('Error: Donor ID not found', function() {
                                        // Just close the modal, no additional action needed
                                    });
                                } else {
                                    // Use custom modal instead of browser alert
                                    if (window.customConfirm) {
                                        window.customConfirm('Error: Donor ID not found', function() {
                                            // Just close the modal, no additional action needed
                                        });
                            } else {
                                alert('Error: Donor ID not found');
                                    }
                                }
                            }
                        } else if (action === 'decline') {
                            // Close modal and refresh the main page for decline only
                            const modal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                            modal.hide();
                            window.location.reload();
                        }
                    } else {
                        // Use custom modal instead of browser alert
                        if (window.customConfirm) {
                            window.customConfirm('Error: ' + (data.message || 'Unknown error occurred'), function() {
                                // Just close the modal, no additional action needed
                            });
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error occurred'));
                        }
                    }
                })
                .catch(error => {
                    //console.error('Error:', error);
                    // Use custom modal instead of browser alert
                    if (window.customConfirm) {
                        window.customConfirm('An error occurred while processing the form.', function() {
                            // Just close the modal, no additional action needed
                        });
                    } else {
                    alert('An error occurred while processing the form.');
                    }
                });
        }
        
        // Function to show screening form modal
        function showScreeningFormModal(donorId) {
            //console.log('Showing screening form modal for donor ID:', donorId);
            
            // Set donor data for the screening form
            window.currentDonorData = { donor_id: donorId };
            
            // Show the screening form modal with static backdrop
            const screeningModalElement = document.getElementById('screeningFormModal');
            const screeningModal = new bootstrap.Modal(screeningModalElement, {
                backdrop: 'static',
                keyboard: false
            });
            screeningModal.show();
            
            // Prevent modal from closing when clicking on content
            screeningModalElement.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Set the donor ID in the screening form
            const donorIdInput = document.querySelector('#screeningFormModal input[name="donor_id"]');
            if (donorIdInput) {
                donorIdInput.value = donorId;
            }
            
            // Initialize the screening form
            if (window.initializeScreeningForm) {
                window.initializeScreeningForm(donorId);
            }
        }
        
        // Function to show declaration form modal
        window.showDeclarationFormModal = function(donorId) {
            //console.log('Showing declaration form modal for donor ID:', donorId);
            
            // Show confirmation modal first
            const confirmationModalHtml = `
                <div class="modal fade" id="screeningToDeclarationConfirmationModal" tabindex="-1" aria-labelledby="screeningToDeclarationConfirmationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 0.375rem 0.375rem 0 0;">
                                <h5 class="modal-title" id="screeningToDeclarationConfirmationModalLabel">Screening Submitted Successfully</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">Screening submitted. Please proceed to the declaration form to complete the donor registration process.</p>
                            </div>
                            <div class="modal-footer border-0 justify-content-end">
                                <button type="button" class="btn" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;" onclick="proceedToDeclarationForm('${donorId}')">Proceed to Declaration Form</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('screeningToDeclarationConfirmationModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add the modal to the document
            document.body.insertAdjacentHTML('beforeend', confirmationModalHtml);
            
            // Show the confirmation modal
            const confirmationModal = new bootstrap.Modal(document.getElementById('screeningToDeclarationConfirmationModal'));
            confirmationModal.show();
            
            // Add event listener to remove modal from DOM after it's hidden
            document.getElementById('screeningToDeclarationConfirmationModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        };
        
        // Function to proceed to declaration form after confirmation
        window.proceedToDeclarationForm = function(donorId) {
            // Close confirmation modal
            const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('screeningToDeclarationConfirmationModal'));
            if (confirmationModal) {
                confirmationModal.hide();
            }
            
            const declarationModal = new bootstrap.Modal(document.getElementById('declarationFormModal'));
            const modalContent = document.getElementById('declarationFormModalContent');
            
            // Reset modal content to loading state
            modalContent.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="min-height: 200px;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 mb-0">Loading Declaration Form...</p>
                    </div>
                </div>`;
            
            // Show the modal
            declarationModal.show();
            
            // Load the declaration form content
            fetch('../../src/views/forms/declaration-form-modal-content.php?donor_id=' + donorId)
                .then(response => {
                    //console.log('Declaration form response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    //console.log('Declaration form content loaded successfully');
                    modalContent.innerHTML = data;
                    
                    // Ensure print function is available globally
                    window.printDeclaration = function() {
                        //console.log('Print function called');
                        const printWindow = window.open('', '_blank');
                        const content = document.querySelector('.declaration-header').outerHTML + 
                                       document.querySelector('.donor-info').outerHTML + 
                                       document.querySelector('.declaration-content').outerHTML + 
                                       document.querySelector('.signature-section').outerHTML;
                        
                        printWindow.document.write(`
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <title>Declaration Form - Philippine Red Cross</title>
                                <style>
                                    body { 
                                        font-family: Arial, sans-serif; 
                                        padding: 20px; 
                                        line-height: 1.5;
                                    }
                                    .declaration-header { 
                                        text-align: center; 
                                        margin-bottom: 30px;
                                        padding: 20px;
                                        background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
                                        color: white;
                                        padding-bottom: 20px;
                                    }
                                    .declaration-header h2, .declaration-header h3 { 
                                        color: white; 
                                        margin: 5px 0;
                                        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
                                        font-weight: bold;
                                    }
                                    .donor-info { 
                                        background-color: #f8f9fa; 
                                        padding: 20px; 
                                        margin: 20px 0; 
                                        border: 1px solid #ddd; 
                                        border-radius: 8px;
                                    }
                                    .donor-info-row { 
                                        display: flex; 
                                        margin-bottom: 15px; 
                                        gap: 20px; 
                                        flex-wrap: wrap;
                                    }
                                    .donor-info-item { 
                                        flex: 1; 
                                        min-width: 200px;
                                    }
                                    .donor-info-label { 
                                        font-weight: bold; 
                                        font-size: 14px; 
                                        color: #555; 
                                        margin-bottom: 5px;
                                    }
                                    .donor-info-value { 
                                        font-size: 16px; 
                                        color: #333; 
                                    }
                                    .declaration-content { 
                                        line-height: 1.8; 
                                        margin: 30px 0; 
                                        text-align: justify;
                                    }
                                    .declaration-content p { 
                                        margin-bottom: 20px; 
                                    }
                                    .bold { 
                                        font-weight: bold; 
                                        color: #9c0000; 
                                    }
                                    .signature-section { 
                                        margin-top: 40px; 
                                        display: flex; 
                                        justify-content: space-between; 
                                        page-break-inside: avoid;
                                    }
                                    .signature-box { 
                                        text-align: center; 
                                        padding: 15px 0; 
                                        border-top: 2px solid #333; 
                                        width: 250px; 
                                        font-weight: 500;
                                    }
                                    @media print {
                                        body { margin: 0; }
                                        .declaration-header { page-break-after: avoid; }
                                        .signature-section { page-break-before: avoid; }
                                    }
                                </style>
                            </head>
                            <body>${content}</body>
                            </html>
                        `);
                        printWindow.document.close();
                        printWindow.focus();
                        // Print immediately when ready
                            printWindow.print();
                    };
                    
                    // Re-introduce explicit confirmation: only proceed and close after user agrees
                    
                    // Ensure submit function is available globally
                    window.submitDeclarationForm = function(event) {
                        // Prevent default form submission immediately to keep modal open
                        if (event) {
                            event.preventDefault();
                        }
                        
                        const proceedSubmission = function() {
                            // Process the declaration form
                            const form = document.getElementById('modalDeclarationForm');
                            if (!form) {
                                if (window.customConfirm) {
                                    window.customConfirm('Form not found. Please try again.', function() {});
                                } else {
                                    alert('Form not found. Please try again.');
                                }
                                return;
                            }
                            
                            document.getElementById('modalDeclarationAction').value = 'complete';
                            
                            // Submit the form via AJAX
                            const formData = new FormData(form);
                            
                            // Include screening data if available
                            if (window.currentScreeningData) {
                                formData.append('screening_data', JSON.stringify(window.currentScreeningData));
                                formData.append('debug_log', 'Including screening data: ' + JSON.stringify(window.currentScreeningData));
                            } else {
                                formData.append('debug_log', 'No screening data available');
                            }
                            
                            formData.append('debug_log', 'Submitting form data...');
                            
                            // Debug: Log what we're sending
                            const debugFormData = new FormData();
                            debugFormData.append('debug_log', 'FormData contents:');
                            for (let [key, value] of formData.entries()) {
                                debugFormData.append('debug_log', '  ' + key + ': ' + value);
                            }
                            fetch('../../src/views/forms/declaration-form-process.php', {
                                method: 'POST',
                                body: debugFormData
                            }).catch(() => {});
                            
                            fetch('../../src/views/forms/declaration-form-process.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok: ' + response.status);
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    // Close declaration form modal ONLY after explicit confirmation (already given)
                                    const declarationModal = bootstrap.Modal.getInstance(document.getElementById('declarationFormModal'));
                                    if (declarationModal) {
                                        declarationModal.hide();
                                    }
                                    
                                    // Show success modal with requested copy and behavior
                                    if (window.showSuccessModal) {
                                        // Title should indicate forwarded to physician; content should not claim cleared
                                        showSuccessModal('Submitted', 'The donor has been forwarded to the physician for physical examination.', { autoCloseMs: 1600, reloadOnClose: true });
                                    } else {
                                        // Fallback
                                        alert('Submitted: The donor has been forwarded to the physician for physical examination.');
                                        window.location.reload();
                                    }
                                } else {
                                    // Show error modal (different styling)
                                    const msg = 'Failed to complete registration. ' + (data.message || 'Please try again.');
                                    if (window.showErrorModal) {
                                        showErrorModal('Submission Failed', msg, { autoCloseMs: null, reloadOnClose: false });
                                    } else {
                                        alert(msg);
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error submitting declaration form:', error);
                                
                                // Log error to server
                                const errorFormData = new FormData();
                                errorFormData.append('debug_log', 'JavaScript Error: ' + error.message);
                                fetch('../../src/views/forms/declaration-form-process.php', {
                                    method: 'POST',
                                    body: errorFormData
                                }).catch(() => {});
                                
                                const emsg = 'An error occurred while processing the form: ' + error.message;
                                if (window.showErrorModal) {
                                    showErrorModal('Submission Error', emsg, { autoCloseMs: null, reloadOnClose: false });
                                } else {
                                    alert(emsg);
                                }
                            });
                        };
                        
                        // Ask for explicit confirmation before proceeding
                        const message = 'Are you sure you want to complete the registration?';
                        if (window.customConfirm) {
                            window.customConfirm(message, proceedSubmission);
                        } else {
                            if (confirm(message)) {
                                proceedSubmission();
                            }
                        }
                    };
                })
                .catch(error => {
                    //console.error('Error loading declaration form:', error);
                    modalContent.innerHTML = `
                        <div class="alert alert-danger text-center" style="margin: 50px 20px;">
                            <h5 class="alert-heading">
                                <i class="fas fa-exclamation-triangle"></i> Error Loading Form
                            </h5>
                            <p>Unable to load the declaration form. Please try again.</p>
                            <hr>
                            <p class="mb-0">Error details: ' + error.message + '</p>
                            <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>`;
                });
        }
        
        // Add loading functionality for data processing
        function showProcessingModal(message = 'Processing medical history data...') {
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
        
        // Show loading when medical history forms are submitted
        document.addEventListener('submit', function(e) {
            if (e.target && (e.target.classList.contains('medical-form') || e.target.id.includes('medical'))) {
                showProcessingModal('Submitting medical history data...');
            }
        });
        
        // Removed risky global fetch override that caused duplicate loaders and race conditions
        
        // Custom confirmation function to replace browser confirm
        function customConfirm(message, onConfirm) {
            // Create a simple modal matching the Submit Medical History design
            const modalHTML = `
                <div id="simpleCustomModal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: white;
                        border-radius: 10px;
                        max-width: 500px;
                        width: 90%;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                        overflow: hidden;
                    ">
                        <div style="
                            background: #9c0000;
                            color: white;
                            padding: 15px 20px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        ">
                            <h5 style="margin: 0; font-weight: bold;">
                                Confirm Action
                            </h5>
                            <button onclick="closeSimpleModal()" style="
                                background: none;
                                border: none;
                                color: white;
                                font-size: 20px;
                                cursor: pointer;
                            ">&times;</button>
                        </div>
                        <div style="padding: 20px;">
                            <p style="font-size: 14px; line-height: 1.5; margin-bottom: 20px; color: #333;">${message}</p>
                            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                                <button onclick="closeSimpleModal()" style="
                                    background: #6c757d;
                                    color: white;
                                    border: none;
                                    padding: 8px 20px;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    font-size: 14px;">Cancel</button>
                                <button onclick="confirmSimpleModal()" style="
                                    background: #9c0000;
                                    color: white;
                                    border: none;
                                    padding: 8px 20px;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    font-size: 14px;
                                ">Yes, proceed</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing modal
            const existingModal = document.getElementById('simpleCustomModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Set up confirmation function
            window.confirmSimpleModal = function() {
                closeSimpleModal();
                if (onConfirm) onConfirm();
            };
            
            window.closeSimpleModal = function() {
                const modal = document.getElementById('simpleCustomModal');
                if (modal) {
                    modal.remove();
                }
            };
        }

        // Make customConfirm globally available
        window.customConfirm = customConfirm;
        
        

    </script>

    <!-- Custom Confirmation Modal -->
    <div class="modal fade" id="customConfirmModal" tabindex="-1" aria-labelledby="customConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: #9c0000; color: white;">
                    <h5 class="modal-title" id="customConfirmModalLabel">
                        <i class="fas fa-question-circle me-2"></i>
                        Confirm Action
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="customConfirmMessage">Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="customConfirmYes">Yes, Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Custom confirmation modal styling */
        #customConfirmModal .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        #customConfirmModal .modal-header {
            background: #9c0000; /* Red Cross themed */
            color: white;
            border-radius: 10px 10px 0 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        #customConfirmModal .modal-body {
            padding: 25px;
            font-size: 16px;
            line-height: 1.5;
        }
        
        #customConfirmModal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 20px 25px;
        }
        
        #customConfirmModal .btn {
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        #customConfirmModal .btn-primary {
            background-color: #9c0000;
            border-color: #9c0000;
        }
        
        #customConfirmModal .btn-primary:hover {
            background-color: #8b0000;
            border-color: #8b0000;
            transform: translateY(-1px);
        }
        
        /* Ensure modal appears above everything */
        #customConfirmModal {
            z-index: 99999 !important;
        }
        
        #customConfirmModal .modal-backdrop {
            z-index: 99998 !important;
        }
        
        /* Force modal to be visible */
        #customConfirmModal.show {
            display: block !important;
            opacity: 1 !important;
        }
        
        /* Ensure backdrop is visible */
        .modal-backdrop.show {
            opacity: 0.5 !important;
        }
        
        /* Defer button styling to match physical submission dashboard */
        #screeningDeferButton {
            border-color: #dc3545;
            color: #dc3545;
            background-color: white;
        }

        #screeningDeferButton:hover {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }

        #screeningDeferButton:focus {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        /* Mark for Medical Review button styling */
        #markReviewFromMain:hover,
        #markReturningReviewBtn:hover {
            background-color: #e0a800 !important;
            border-color: #d39e00 !important;
            color: #212529 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
        }
        
        #markReviewFromMain:focus,
        #markReturningReviewBtn:focus {
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
            outline: none;
        }
        
        #markReviewFromMain:active,
        #markReturningReviewBtn:active {
            background-color: #d39e00 !important;
            border-color: #c69500 !important;
            transform: translateY(0);
        }
        
        /* Fix for screening form modal backdrop issues */
        #screeningFormModal {
            z-index: 1055 !important;
        }
        
        #screeningFormModal .modal-dialog {
            position: relative;
            z-index: 1056 !important;
            pointer-events: auto;
        }
        
        #screeningFormModal .modal-content {
            position: relative;
            z-index: 1057 !important;
            pointer-events: auto;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        #screeningFormModal .modal-body {
            position: relative;
            z-index: 1058 !important;
            pointer-events: auto;
        }
        
        #screeningFormModal .modal-footer {
            position: relative;
            z-index: 1059 !important;
            pointer-events: auto;
        }
        
        /* Ensure all form elements are clickable */
        #screeningFormModal input,
        #screeningFormModal select,
        #screeningFormModal button,
        #screeningFormModal .form-control {
            position: relative;
            z-index: 1060 !important;
            pointer-events: auto;
        }
    </style>

    <!-- Include Screening Form Modal -->
    <?php include '../../src/views/forms/staff_donor_initial_screening_form_modal.php'; ?>
    
    <!-- Include Defer Donor Modal -->
    <?php include '../../src/views/modals/defer-donor-modal.php'; ?>

    <!-- Physical Examination Review Modal -->
    <div class="modal fade" id="physicalExaminationModal" tabindex="-1" aria-labelledby="physicalExaminationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title" id="physicalExaminationModalLabel">
                        <i class="fas fa-stethoscope me-2"></i>
                        Physical Examination Results
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="physicalExaminationModalContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6; border-radius: 0 0 15px 15px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                    <button type="button" class="btn" id="proceedToMedicalHistoryFromPhysical" style="background-color: #b22222; color: white; border: none;">
                        <i class="fas fa-clipboard-list me-1"></i>Proceed to Medical History
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Donor Information Medical Modal -->
    <div class="modal fade" id="donorInformationMedicalModal" tabindex="-1" aria-labelledby="donorInformationMedicalModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl" style="max-width: 1200px; width: 90%;">
            <div class="modal-content" style="border-radius: 15px; overflow: hidden;">
                <div id="donorMedicalModalContent">
                    <!-- Content will be dynamically loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- Include Defer Donor Modal JavaScript -->
    <script src="../../assets/js/initial-screening-defer-button.js"></script>
    
    <!-- Include Donor Information Medical Modal JavaScript -->
    <script src="../../assets/js/donor_information_medical.js"></script>
    
    <style>
        #donorInformationMedicalModal .modal-dialog {
            max-width: 1200px;
            width: 90%;
            margin: 1.75rem auto;
        }
        /* Ensure this modal appears above others */
        #donorInformationMedicalModal { z-index: 1070; }
        /* Stronger, blurred, darker backdrop when this modal is open */
        .modal-backdrop.donor-info { 
            z-index: 1069; 
            opacity: 0.4 !important; 
            background-color: rgba(0, 0, 0, 0.45) !important; 
            backdrop-filter: blur(3px);
        }
        
        #donorInformationMedicalModal .modal-content {
            border-radius: 15px;
            overflow: hidden;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 0 0 2px rgba(178,34,34,0.15);
            position: relative;
        }
        /* Smudge/Glow border to make the modal pop from anything behind it */
        #donorInformationMedicalModal .modal-content::after {
            content: '';
            position: absolute;
            inset: -8px; /* slightly smaller extension */
            border-radius: 18px;
            pointer-events: none;
            /* lighter layered glow */
            box-shadow:
                0 0 0 6px rgba(255,255,255,0.35),
                0 15px 40px rgba(0,0,0,0.25),
                0 0 20px rgba(178,34,34,0.15);
            filter: blur(1px);
        }
        
        #donorInformationMedicalModal .modal-header {
            border-bottom: none;
            padding: 1rem 1.5rem;
        }
        
        #donorInformationMedicalModal .modal-footer {
            border-top: none;
            padding: 1rem 1.5rem;
        }
        
        #donorInformationMedicalModal .modal-body {
            padding: 1rem 1.5rem;
        }
    </style>
    
    <script>
        // Defer button functionality is now handled within the screening form modal
        // No additional initialization needed here
        
        // Global function to show physical examination modal
        function showPhysicalExaminationModal(eligibilityId) {
            const physicalModal = new bootstrap.Modal(document.getElementById('physicalExaminationModal'));
            const modalContent = document.getElementById('physicalExaminationModalContent');
            
            // Reset modal content to loading state
            modalContent.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            
            // Show the modal
            physicalModal.show();
            
            // Fetch physical examination data from eligibility table joined with donor_form
            fetch('../../assets/php_func/fetch_eligibility_physical_examination.php?eligibility_id=' + eligibilityId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        // Show physical examination modal
                        displayPhysicalExaminationInfo(data.data, eligibilityId);
                    } else {
                        modalContent.innerHTML = `
                            <div class="alert alert-info">
                                <h6>No Physical Examination Data</h6>
                                <p>Unable to load physical examination information. Please try again.</p>
                            </div>`;
                    }
                })
                .catch(error => {
                    //console.error('Error fetching physical examination info:', error);
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>Error Loading Data</h6>
                            <p>Unable to load physical examination information. Please try again.</p>
                        </div>`;
                });
        }
        
        // Global function to display physical examination information
        function displayPhysicalExaminationInfo(physicalData, eligibilityId) {
            const modalContent = document.getElementById('physicalExaminationModalContent');
            
            // Format date
            const formatDate = (dateString) => {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            };
            
            // Calculate age from birthdate
            const calculateAge = (birthdate) => {
                if (!birthdate) return 'N/A';
                const birth = new Date(birthdate);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                const monthDiff = today.getMonth() - birth.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                    age--;
                }
                return age;
            };
            
            const safe = (value) => value || 'N/A';
            const fullName = `${safe(physicalData.surname)}, ${safe(physicalData.first_name)} ${safe(physicalData.middle_name)}`.trim();
            const age = calculateAge(physicalData.birthdate);
            const donorAgeGender = `${age}${physicalData.sex ? ', ' + physicalData.sex : ''}`;
            
            // Get assessments using the assessment functions
            const assessment = getPhysicalExaminationAssessment(physicalData);
            
            const physicalHTML = `
                <!-- Donor Information Header (Compact Design) -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="text-muted small mb-1">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Date Screened: ${formatDate(physicalData.screening_date || physicalData.created_at)}
                            </div>
                            <h5 class="mb-1" style="color:#b22222; font-weight:700;">
                                ${fullName}
                            </h5>
                            <div class="text-muted fw-medium">
                                <i class="fas fa-user me-1"></i>
                                ${donorAgeGender}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="mb-1">
                                <div class="fw-bold text-dark mb-1">
                                    <i class="fas fa-id-card me-1"></i>
                                    Donor ID: ${safe(physicalData.prc_donor_number || 'N/A')}
                                </div>
                                <div class="blood-type-display" style="background-color: #8B0000; color: white; border-radius: 20px; padding: 8px 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 80px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                    <div style="font-size: 0.7rem; font-weight: 500; line-height: 1; margin-bottom: 1px; opacity: 0.9;">Blood Type</div>
                                    <div style="font-size: 1.1rem; font-weight: 700; line-height: 1;">${safe(physicalData.blood_type || 'N/A')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="my-2" style="border-color: #b22222; opacity: 0.3;"/>
                </div>
                
                    <!-- Physical Examination Results Section -->
                    <div class="mb-3">
                        <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">Physical Examination Results</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Interviewer Remarks</label>
                                <div class="form-control-plaintext bg-light p-2 rounded">
                                    ${safe(physicalData.remarks || 'Pending')}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Physical Exam Notes (Skin, HEENT, Lungs, etc.)</label>
                                <div class="form-control-plaintext bg-light p-2 rounded">
                                    ${safe(assessment.physical_exam_notes)}
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Blood Type</label>
                                <div class="form-control-plaintext bg-light p-2 rounded">
    <div>${safe(physicalData.blood_type || 'N/A')}</div>
</div>  
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Type of Donation</label>
                                <div class="form-control-plaintext bg-light p-2 rounded">${safe(physicalData.donation_type || 'N/A')}</div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Vital Signs Section -->
                    <div class="mb-3">
                        <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">Vital Signs</h6>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Blood Pressure (BP)</label>
                                <div class="form-control-plaintext bg-light p-2 rounded text-center">
                                    ${safe(assessment.blood_pressure)}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Weight</label>
                                <div class="form-control-plaintext bg-light p-2 rounded text-center">
                                    ${safe(assessment.weight)}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Pulse</label>
                                <div class="form-control-plaintext bg-light p-2 rounded text-center">
                                    ${safe(assessment.pulse)}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Temperature</label>
                                <div class="form-control-plaintext bg-light p-2 rounded text-center">
                                    ${safe(assessment.temperature)}
                                </div>
                            </div>
                        </div>
                    </div>
                
                <!-- Final Assessment Section -->
                <div class="mb-3">
                    <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">Final Assessment</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="p-3 text-center" style="background-color:#f8f9fa; border-radius:8px; border: 2px solid ${physicalData.disapproval_reason ? '#dc3545' : '#28a745'};">
                                <div class="fw-semibold mb-2" style="color:#b22222;">Fitness to Donate</div>
                                <div class="fs-5 fw-bold" style="color: ${physicalData.disapproval_reason ? '#dc3545' : '#28a745'};">
                                    ${physicalData.disapproval_reason ? 'Deferred' : 'Accepted'}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 text-center" style="background-color:#f8f9fa; border-radius:8px; border: 2px solid ${physicalData.disapproval_reason ? '#dc3545' : '#28a745'};">
                                <div class="fw-semibold mb-2" style="color:#b22222;">Final Remarks</div>
                                <div class="fs-5 fw-bold" style="color: ${physicalData.disapproval_reason ? '#dc3545' : '#28a745'};">
                                    ${physicalData.disapproval_reason ? 'Deferred' : 'Accepted'}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            `;
            
            modalContent.innerHTML = physicalHTML;
            
            // Store eligibility ID for the proceed button
            modalContent.setAttribute('data-eligibility-id', eligibilityId);
        }
    </script>

    <script>
        // Add donor-info class to backdrop so this modal stands out
        document.addEventListener('DOMContentLoaded', function () {
            const donorInfoModalEl = document.getElementById('donorInformationMedicalModal');
            if (donorInfoModalEl) {
                donorInfoModalEl.addEventListener('shown.bs.modal', function () {
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.classList.add('donor-info');
                });
                donorInfoModalEl.addEventListener('hidden.bs.modal', function () {
                    const backdrop = document.querySelector('.modal-backdrop.donor-info');
                    if (backdrop) backdrop.classList.remove('donor-info');
                });
            }
        });
    </script>

    <script>
        // Global function to control button visibility
        function controlMarkReviewButton(donorId) {
            const markReviewButton = document.getElementById('markReviewFromMain');
            if (!markReviewButton) return;
            
            // Immediate hide when medical_history.needs_review is already true (from preloaded dataset)
            try {
                if (window.medicalByDonor && medicalByDonor[donorId] && medicalByDonor[donorId].needs_review === true) {
                    markReviewButton.style.display = 'none';
                    markReviewButton.style.visibility = 'hidden';
                    markReviewButton.style.opacity = '0';
                    return; // No further checks needed
                }
            } catch (_) {}
            
            // Get eligibility status from the API
            fetch('../../assets/php_func/get_donor_eligibility_status.php?donor_id=' + donorId)
                .then(response => response.json())
                .then(data => {
                    let shouldShowMarkButton = false;
                    
                    if (data.success && data.data) {
                        const eligibility = data.data;
                        const status = eligibility.status;
                        const startDate = new Date(eligibility.start_date);
                        const today = new Date();
                        
                        // Calculate 3 months waiting period
                        const threeMonthsLater = new Date(startDate);
                        threeMonthsLater.setMonth(threeMonthsLater.getMonth() + 3);
                        const hasRemainingDays = (threeMonthsLater - today) > 0;
                        
                        // Show button ONLY for "Deferred" status (refused donors) OR when waiting period is complete
                        if (status === 'Deferred') {
                            shouldShowMarkButton = true;
                        }
                        // Show button if approved status and waiting period is complete (no remaining days)
                        else if (status === 'approved' && !hasRemainingDays) {
                            shouldShowMarkButton = true;
                        }
                        // Hide button for all other cases
                        else {
                            shouldShowMarkButton = false;
                        }
                    } else {
                        // No eligibility records - show button (new donor)
                        shouldShowMarkButton = true;
                    }
                    
                    // Force hide the button if it shouldn't be shown
                    if (!shouldShowMarkButton) {
                        markReviewButton.style.display = 'none';
                        markReviewButton.style.visibility = 'hidden';
                        markReviewButton.style.opacity = '0';
                    } else {
                        markReviewButton.style.display = 'inline-block';
                        markReviewButton.style.visibility = 'visible';
                        markReviewButton.style.opacity = '1';
                    }
                })
                .catch(error => {
                    // On error, hide button to be safe
                    markReviewButton.style.display = 'none';
                    markReviewButton.style.visibility = 'hidden';
                    markReviewButton.style.opacity = '0';
                });
        }

        // Helper function for dynamically loaded search results
        function viewDonorFromRow(donorId, stage, donorTypeLabel) {
            if (!donorId) return;
            window.currentDonorId = donorId;
            window.currentDonorType = donorTypeLabel || 'New';
            window.currentDonorStage = stage || 'medical_review';
            checkAndShowDonorStatus(donorId);
        }

        // Function to check if donor is new (no eligibility record)
        function checkAndShowDonorStatus(donorId) {
            // Directly show the donor status modal
            showDonorStatusModal(donorId);
        }

        // Function to show donor status modal
        // OPTIMIZED: Removed 800ms artificial delay, uses parallel data fetching, reuses modal instances
        function showDonorStatusModal(donorId) {
            // Set current donor ID
            window.currentDonorId = donorId;

            // Get or create the donor status modal instance - reuse for better performance
            const deferralStatusModalEl = document.getElementById('deferralStatusModal');
            let deferralStatusModal = deferralStatusModalEl ? bootstrap.Modal.getInstance(deferralStatusModalEl) : null;
            if (!deferralStatusModal) {
                deferralStatusModal = new bootstrap.Modal(deferralStatusModalEl);
            }
            const deferralStatusContent = document.getElementById('deferralStatusContent');
            
            // Clear any previous content immediately and show loading
            deferralStatusContent.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="min-height: 300px;">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted fs-5">Loading donor information...</p>
                        <p class="text-muted small">Please wait while we fetch the latest data</p>
                    </div>
                </div>`;
            
            // Show modal immediately with loading state
            deferralStatusModal.show();
            
            // Control "Mark for Medical Review" button visibility
            controlMarkReviewButton(donorId);

            // Fetch and display donor info immediately - OPTIMIZED: Single unified endpoint with parallel backend processing
            fetch('../../assets/php_func/fetch_donor_complete_info_staff-medical-history.php?donor_id=' + donorId)
                .then(r => r.json())
                .then(response => {
                        // Double-check we're still showing the correct donor
                        if (window.currentDonorId === donorId) {
                        if (response && response.success && response.data) {
                            // Extract the data and deferral info from unified response
                            const donorData = { success: true, data: response.data };
                            const deferralData = response.deferral || null;
                        displayDonorInfo(donorData, deferralData);
                            } else {
                                deferralStatusContent.innerHTML = `
                                    <div class="alert alert-danger">
                                        Failed to load donor information
                                    </div>`;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching donor info:', error);
                        if (window.currentDonorId === donorId) {
                            deferralStatusContent.innerHTML = `
                                <div class="alert alert-danger">
                                    An error occurred while loading donor information
                                </div>`;
                        }
                    });
        }


    </script>

    <!-- Load eligibility alert script first -->
    <script src="../../assets/js/donor_eligibility_alert.js"></script>
    <!-- Load physical examination assessment script -->
    <script src="../../assets/js/assess_physical_exam_medical_history.js"></script>
</body>
</html>