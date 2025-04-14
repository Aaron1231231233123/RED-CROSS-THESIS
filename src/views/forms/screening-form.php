<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// Debug session data
error_log("Session data in screening-form.php: " . print_r($_SESSION, true));
error_log("Role ID type: " . gettype($_SESSION['role_id']) . ", Value: " . $_SESSION['role_id']);

// Debug POST and GET data
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../public/login.php");
    exit();
}

// Check if user is an interviewer or physician
$is_interviewer = false;
$is_physician = false;

if ($_SESSION['role_id'] == 3) {
    // Get the user's staff role from the database directly
    $user_id = $_SESSION['user_id'];
    
    // Prepare the API URL to fetch the user's role
    $url = SUPABASE_URL . "/rest/v1/user_roles?select=user_staff_roles&user_id=eq." . urlencode($user_id);
    
    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log the response for debugging
    error_log("Physician role check API response: " . $response);
    error_log("HTTP code: " . $http_code);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (is_array($data) && !empty($data)) {
            $user_staff_role = isset($data[0]['user_staff_roles']) ? strtolower($data[0]['user_staff_roles']) : '';
            
            // Check for specific roles (case-insensitive)
            $is_interviewer = ($user_staff_role === 'interviewer');
            $is_physician = ($user_staff_role === 'physician');
            
            // Log the detected role
            error_log("User staff role: " . $user_staff_role);
            error_log("Is interviewer: " . ($is_interviewer ? 'true' : 'false'));
            error_log("Is physician: " . ($is_physician ? 'true' : 'false'));
            
            // Store in session for use in other parts of the application
            $_SESSION['is_interviewer'] = $is_interviewer;
            $_SESSION['is_physician'] = $is_physician;
            $_SESSION['user_staff_roles'] = $data[0]['user_staff_roles'];
        }
    }
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if ($_SESSION['role_id'] == 1) {
    // Admin role - continue with admin specific logic
    if (!isset($_SESSION['donor_id'])) {
        error_log("Set donor_id to 46 for admin role");
    }
} elseif ($_SESSION['role_id'] == 3) {
    // Staff role - check for required session variables (except for physicians using POST/GET)
    if (!isset($_SESSION['donor_id']) && 
        !($is_physician && (isset($_POST['donor_id']) || isset($_GET['donor_id'])))) {
        
        // Check POST and GET for donor_id before redirecting
        if (isset($_POST['donor_id'])) {
            $_SESSION['donor_id'] = $_POST['donor_id'];
            error_log("Found donor_id in POST, setting in session: " . $_POST['donor_id']);
        } else if (isset($_GET['donor_id'])) {
            $_SESSION['donor_id'] = $_GET['donor_id'];
            error_log("Found donor_id in GET, setting in session: " . $_GET['donor_id']);
        } else {
            error_log("Missing donor_id everywhere - redirecting to dashboard-staff-main.php");
            header("Location: ../../../public/Dashboards/dashboard-staff-main.php");
            exit();
        }
    }
} else {
    // Any other role
    error_log("Invalid role_id: " . $_SESSION['role_id']);
    header("Location: ../../../public/unauthorized.php");
    exit();
}

// Get the donor_id from session or POST for physicians
// $donor_id = isset($_POST['donor_id']) && $is_physician ? $_POST['donor_id'] : $_SESSION['donor_id'];
// error_log("Processing donor_id: $donor_id");

// Get the donor_id - more robust approach
if (isset($_POST['donor_id']) && $is_physician) {
    $donor_id = $_POST['donor_id'];
    $_SESSION['donor_id'] = $donor_id; // Set it in session for consistency
    error_log("Using donor_id from POST: $donor_id (physician)");
} elseif (isset($_GET['donor_id']) && $is_physician) {
    $donor_id = $_GET['donor_id'];
    $_SESSION['donor_id'] = $donor_id; // Set it in session for consistency
    error_log("Using donor_id from GET: $donor_id (physician)");
} elseif (isset($_SESSION['donor_id'])) {
    $donor_id = $_SESSION['donor_id'];
    error_log("Using donor_id from SESSION: $donor_id");
} else {
    // This is a fallback, should rarely happen
    error_log("No donor_id found in POST, GET, or SESSION - using a fallback method");
    
    // If we have screening_id, try to retrieve donor_id from database
    if (isset($_POST['screening_id']) || isset($_GET['screening_id'])) {
        $screening_id = isset($_POST['screening_id']) ? $_POST['screening_id'] : $_GET['screening_id'];
        
        // Query database to get donor_id from screening_id
        $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=donor_form_id&screening_id=eq.' . $screening_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (is_array($data) && !empty($data) && isset($data[0]['donor_form_id'])) {
                $donor_id = $data[0]['donor_form_id'];
                $_SESSION['donor_id'] = $donor_id;
                error_log("Retrieved donor_id from database using screening_id: $donor_id");
            } else {
                error_log("Failed to get donor_id from database using screening_id: $screening_id");
            }
        } else {
            error_log("HTTP error when retrieving donor_id from database: $http_code");
        }
    }
    
    // If still no donor_id, redirect to dashboard
    if (empty($donor_id)) {
        error_log("Still no donor_id available - redirecting to dashboard");
        header("Location: ../../../public/Dashboards/dashboard-staff-main.php");
        exit();
    }
}

error_log("Final donor_id being used: $donor_id");

// Variables for role-based access
$body_wt_data = null; // For storing body weight data for physicians

if ($_SESSION['role_id'] == 3) {
    // Get the user's staff role from the database
    $user_id = $_SESSION['user_id'];
    
    // Initialize cURL
    $ch = curl_init(SUPABASE_URL . '/rest/v1/user_roles?select=user_staff_roles&user_id=eq.' . $user_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("Staff role check response: " . $response);
    error_log("HTTP code: " . $http_code);

    if ($http_code === 200) {
        $staff_data = json_decode($response, true);
        if (is_array($staff_data) && !empty($staff_data)) {
            $user_staff_roles = strtolower($staff_data[0]['user_staff_roles']);
            // Check for 'interviewer' role (lowercase)
            $is_interviewer = ($user_staff_roles === 'interviewer');
            // Check for 'physician' role (lowercase)
            $is_physician = ($user_staff_roles === 'physician');
            
            error_log("User staff role: " . $staff_data[0]['user_staff_roles']);
            error_log("Is interviewer: " . ($is_interviewer ? 'true' : 'false'));
            error_log("Is physician: " . ($is_physician ? 'true' : 'false'));
            
            // Special handling for physicians - Get body weight data from screening record
            if ($is_physician) {
                $found_body_wt = false;
                $donation_type = ''; // Initialize donation type
                
                // If we have a screening_id, use it to get the body weight and donation type
                if (isset($screening_id) && !empty($screening_id)) {
                    error_log("Physician: looking up body weight and donation type for screening_id: $screening_id");
                    
                    // Get screening data for this screening_id
                    $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=*&screening_id=eq.' . $screening_id);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json'
                    ]);
                    
                    $response = curl_exec($ch);
                    curl_close($ch);
                    
                    $screening_data = json_decode($response, true);
                    
                    // Extract Body WT and donation_type if data exists
                    if (is_array($screening_data) && !empty($screening_data)) {
                        $screening_data = $screening_data[0];
                        
                        if (isset($screening_data['body_weight'])) {
                            $body_wt_data = $screening_data['body_weight'];
                            $found_body_wt = true;
                            
                            // Log for debugging
                            error_log("Physician viewing screening form. Found Body WT from screening_id: " . $body_wt_data);
                        }
                        
                        // Get donation_type
                        if (isset($screening_data['donation_type'])) {
                            $donation_type = $screening_data['donation_type'];
                            error_log("Found donation type from screening_id: " . $donation_type);
                        }
                    }
                }
                
                // If we couldn't find body weight from screening_id, try by donor_id
                if (!$found_body_wt && isset($donor_id) && !empty($donor_id)) {
                    error_log("Physician: looking up body weight and donation_type for donor_id: $donor_id (fallback)");
                    
                    // Get the most recent screening for this donor
                    $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=*&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json'
                    ]);
                    
                    $response = curl_exec($ch);
                    curl_close($ch);
                    
                    $screening_data = json_decode($response, true);
                    
                    // Extract Body WT and donation_type if data exists
                    if (is_array($screening_data) && !empty($screening_data)) {
                        $screening_data = $screening_data[0];
                        
                        if (isset($screening_data['body_weight'])) {
                            $body_wt_data = $screening_data['body_weight'];
                            $found_body_wt = true;
                            
                            // Set screening_id if we found one
                            if (isset($screening_data['screening_id'])) {
                                $screening_id = $screening_data['screening_id'];
                                $_SESSION['screening_id'] = $screening_id;
                                error_log("Found screening_id from donor lookup: $screening_id");
                            }
                            
                            // Log for debugging
                            error_log("Physician viewing screening form. Found Body WT from donor_id fallback: " . $body_wt_data);
                        }
                        
                        // Get donation_type
                        if (isset($screening_data['donation_type'])) {
                            $donation_type = $screening_data['donation_type'];
                            error_log("Found donation type from donor_id fallback: " . $donation_type);
                        }
                    }
                }
                
                if (!$found_body_wt) {
                    error_log("WARNING: Could not find body weight data for physician view");
                }
            }
        }
    }
}

// ALWAYS search for medical_history_id in the database based on donor_id
$medical_history_id = null;

// First, check if medical_history_id exists for this donor
$ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

error_log("Medical history lookup for donor_id $donor_id: HTTP Code $http_code, Response: $response");

if ($http_code === 200) {
    $medical_history_data = json_decode($response, true);
    if (is_array($medical_history_data) && !empty($medical_history_data)) {
        $medical_history_id = $medical_history_data[0]['medical_history_id'];
        error_log("Found existing medical_history_id: $medical_history_id for donor_id: $donor_id");
    } else {
        // No record found - create a new medical_history entry
        error_log("No medical history record found for donor_id: $donor_id - creating one now");
        
        // Prepare minimal medical history data
        $medical_history_data = [
            'donor_id' => $donor_id,
            'feels_well' => true,  // Default values
            'previously_refused' => false,
            'testing_purpose_only' => false,
            'understands_transmission_risk' => true
        ];
        
        // Create the medical history record
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($medical_history_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("Create medical history response: HTTP Code $http_code, Response: $response");
        
        if ($http_code === 201) {
            $response_data = json_decode($response, true);
            if (is_array($response_data) && isset($response_data[0]['medical_history_id'])) {
                $medical_history_id = $response_data[0]['medical_history_id'];
                error_log("Created new medical_history_id: $medical_history_id for donor_id: $donor_id");
            } else {
                error_log("Failed to extract medical_history_id from creation response: " . print_r($response_data, true));
            }
        } else {
            error_log("Failed to create medical history record: HTTP Code $http_code, Response: $response");
        }
    }
} else {
    error_log("Failed to query medical history: HTTP Code $http_code, Response: $response");
}

// In case we still don't have a medical_history_id, make one final attempt
if (!$medical_history_id) {
    error_log("Making final attempt to get medical_history_id for donor_id: $donor_id");
    
    // Try one more time with a different query approach
    $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $medical_history_data = json_decode($response, true);
        if (is_array($medical_history_data) && !empty($medical_history_data)) {
            $medical_history_id = $medical_history_data[0]['medical_history_id'];
            error_log("Final attempt: Found medical_history_id: $medical_history_id");
        } else {
            error_log("Final attempt: Still no medical history found for donor_id: $donor_id");
        }
    }
}

// Debug log to check all session variables
error_log("All session variables in screening-form.php: " . print_r($_SESSION, true));
error_log("Using medical_history_id: " . ($medical_history_id ?? 'NOT FOUND') . " for donor_id: $donor_id");

// Get interviewer information from users table
$ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=surname,first_name,middle_name&user_id=eq.' . $_SESSION['user_id']);

$headers = array(
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY
);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$interviewer_data = json_decode($response, true);
curl_close($ch);

// Log the response for debugging
error_log("Supabase response code: " . $http_code);
error_log("Supabase response: " . $response);
error_log("Interviewer data: " . print_r($interviewer_data, true));

// Set default interviewer name
$interviewer_name = 'Unknown Interviewer';

// Check if we have valid data
if ($http_code === 200 && is_array($interviewer_data) && !empty($interviewer_data)) {
    $interviewer = $interviewer_data[0];
    if (isset($interviewer['surname']) && isset($interviewer['first_name'])) {
        $interviewer_name = $interviewer['surname'] . ', ' . 
                          $interviewer['first_name'] . ' ' . 
                          ($interviewer['middle_name'] ?? '');
        error_log("Set interviewer name to: " . $interviewer_name);
    } else {
        error_log("Missing required fields in interviewer data");
    }
} else {
    error_log("Failed to get interviewer data. HTTP Code: " . $http_code);
}

// Log session state for debugging
error_log("Session state in screening-form.php: " . print_r($_SESSION, true));

// Get the screening_id from various sources (for physicians especially)
if (isset($_POST['screening_id'])) {
    $screening_id = $_POST['screening_id'];
    $_SESSION['screening_id'] = $screening_id;
    error_log("Using screening_id from POST: $screening_id");
} elseif (isset($_GET['screening_id'])) {
    $screening_id = $_GET['screening_id'];
    $_SESSION['screening_id'] = $screening_id;
    error_log("Using screening_id from GET: $screening_id");
} elseif (isset($_SESSION['screening_id'])) {
    $screening_id = $_SESSION['screening_id'];
    error_log("Using screening_id from SESSION: $screening_id");
} else {
    $screening_id = null;
    error_log("No screening_id found in POST, GET, or SESSION");
}

error_log("Final screening_id being used: " . ($screening_id ?? 'null'));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug log the raw POST data
        error_log("Raw POST data: " . print_r($_POST, true));
        
        // If we still don't have medical_history_id, create it now as a last resort
        if (!$medical_history_id) {
            error_log("Creating medical_history as last resort during form submission");
            
            // Create a minimal medical history record
            $medical_history_data = [
                'donor_id' => $donor_id,
                'feels_well' => true,
                'previously_refused' => false,
                'testing_purpose_only' => false,
                'understands_transmission_risk' => true
            ];
            
            $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($medical_history_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 201) {
                $response_data = json_decode($response, true);
                if (is_array($response_data) && isset($response_data[0]['medical_history_id'])) {
                    $medical_history_id = $response_data[0]['medical_history_id'];
                    error_log("Last resort: Created medical_history_id: $medical_history_id");
                }
            }
        }

        // Prepare the base data for insertion
        $screening_data = [
            'donor_form_id' => $_SESSION['donor_id'],
            'medical_history_id' => $medical_history_id,
            'interviewer_id' => $_SESSION['user_id'],
            'body_weight' => floatval($_POST['body-wt']),
            'specific_gravity' => $_POST['sp-gr'] ?: "",
            'blood_type' => $_POST['blood-type'],
            'donation_type' => $_POST['donation-type'],
            'has_previous_donation' => isset($_POST['history']) && $_POST['history'] === 'yes',
            'interview_date' => date('Y-m-d'),
            'red_cross_donations' => isset($_POST['history']) && $_POST['history'] === 'yes' ? intval($_POST['red-cross']) : 0,
            'hospital_donations' => isset($_POST['history']) && $_POST['history'] === 'yes' ? intval($_POST['hospital-history']) : 0,
            'last_rc_donation_place' => isset($_POST['history']) && $_POST['history'] === 'yes' ? ($_POST['last-rc-donation-place'] ?: "") : "",
            'last_hosp_donation_place' => isset($_POST['history']) && $_POST['history'] === 'yes' ? ($_POST['last-hosp-donation-place'] ?: "") : "",
            'last_rc_donation_date' => isset($_POST['history']) && $_POST['history'] === 'yes' && !empty($_POST['last-rc-donation-date']) ? $_POST['last-rc-donation-date'] : '0001-01-01',
            'last_hosp_donation_date' => isset($_POST['history']) && $_POST['history'] === 'yes' && !empty($_POST['last-hosp-donation-date']) ? $_POST['last-hosp-donation-date'] : '0001-01-01',
            'mobile_location' => $_POST['donation-type'] === 'mobile' ? ($_POST['mobile-place'] ?: "") : "",
            'mobile_organizer' => $_POST['donation-type'] === 'mobile' ? ($_POST['mobile-organizer'] ?: "") : "",
            'patient_name' => $_POST['donation-type'] === 'mobile' ? ($_POST['patient-name'] ?: "") : "",
            'hospital' => $_POST['donation-type'] === 'mobile' ? ($_POST['hospital'] ?: "") : "",
            'patient_blood_type' => $_POST['donation-type'] === 'mobile' ? ($_POST['blood-type-patient'] ?: "") : "",
            'component_type' => $_POST['donation-type'] === 'mobile' ? ($_POST['wb-component'] ?: "") : "",
            'units_needed' => $_POST['donation-type'] === 'mobile' && !empty($_POST['no-units']) ? intval($_POST['no-units']) : 0
        ];

        // For interviewers, only include Body WT
        // For all other roles, include all fields
        if (!$is_interviewer) {
            $screening_data = array_merge($screening_data, [
                'specific_gravity' => $_POST['sp-gr'] ?: "",
                'blood_type' => $_POST['blood-type'],
                'donation_type' => $_POST['donation-type'],
                'has_previous_donation' => isset($_POST['history']) && $_POST['history'] === 'yes',
                'red_cross_donations' => isset($_POST['history']) && $_POST['history'] === 'yes' ? intval($_POST['red-cross']) : 0,
                'hospital_donations' => isset($_POST['history']) && $_POST['history'] === 'yes' ? intval($_POST['hospital-history']) : 0,
                'last_rc_donation_place' => isset($_POST['history']) && $_POST['history'] === 'yes' ? ($_POST['last-rc-donation-place'] ?: "") : "",
                'last_hosp_donation_place' => isset($_POST['history']) && $_POST['history'] === 'yes' ? ($_POST['last-hosp-donation-place'] ?: "") : "",
                'last_rc_donation_date' => isset($_POST['history']) && $_POST['history'] === 'yes' && !empty($_POST['last-rc-donation-date']) ? $_POST['last-rc-donation-date'] : '0001-01-01',
                'last_hosp_donation_date' => isset($_POST['history']) && $_POST['history'] === 'yes' && !empty($_POST['last-hosp-donation-date']) ? $_POST['last-hosp-donation-date'] : '0001-01-01',
                'mobile_location' => isset($_POST['mobile-place']) ? ($_POST['mobile-place'] ?: "") : "",
                'mobile_organizer' => isset($_POST['mobile-organizer']) ? ($_POST['mobile-organizer'] ?: "") : "",
                'patient_name' => isset($_POST['patient-name']) ? ($_POST['patient-name'] ?: "") : "",
                'hospital' => isset($_POST['hospital']) ? ($_POST['hospital'] ?: "") : "",
                'patient_blood_type' => isset($_POST['blood-type-patient']) ? ($_POST['blood-type-patient'] ?: "") : "",
                'component_type' => isset($_POST['wb-component']) ? ($_POST['wb-component'] ?: "") : "",
                'units_needed' => isset($_POST['no-units']) && !empty($_POST['no-units']) ? intval($_POST['no-units']) : 0
            ]);
        }

        // Debug log the prepared data
        error_log("Prepared screening data: " . print_r($screening_data, true));

        // Check if there's an existing screening record to update for this donor
        $should_update = false;
        $existing_screening_id = null;

        // Only check for existing records if we have a screening_id or donor_id
        if (isset($screening_id) && !empty($screening_id)) {
            $existing_screening_id = $screening_id;
            $should_update = true;
            error_log("Will update existing screening record with ID: $existing_screening_id");
        } elseif (isset($donor_id) && !empty($donor_id) && $is_physician) {
            // For physicians, check if there's an existing record for this donor
            $ch_check = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=screening_id&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
            curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_check, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ]);
            
            $check_response = curl_exec($ch_check);
            $check_http_code = curl_getinfo($ch_check, CURLINFO_HTTP_CODE);
            curl_close($ch_check);
            
            error_log("Check for existing screening record response: " . $check_response);
            
            if ($check_http_code === 200) {
                $existing_records = json_decode($check_response, true);
                if (is_array($existing_records) && !empty($existing_records)) {
                    $existing_screening_id = $existing_records[0]['screening_id'];
                    $should_update = true;
                    error_log("Found existing screening record with ID: $existing_screening_id for donor ID: $donor_id - will UPDATE");
                }
            }
        }

        if ($should_update && $existing_screening_id) {
            // UPDATE existing record
            $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?screening_id=eq.' . $existing_screening_id);
            
            // Set the headers
            $headers = array(
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            );
            
            // Set cURL options for PATCH (update)
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($screening_data));
            
            // Execute the request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Debug log
            error_log("Supabase UPDATE response code: " . $http_code);
            error_log("Supabase UPDATE response: " . $response);
            
            curl_close($ch);
            
            if ($http_code >= 200 && $http_code < 300) {
                // Record updated successfully
                error_log("Screening form updated successfully");
                $_SESSION['screening_id'] = $existing_screening_id;
                
                // Different redirections based on role
                if ($_SESSION['role_id'] == 1) {
                    // Admin (role_id 1) - Direct to physical examination
                    error_log("Admin role: Redirecting to physical examination form");
                    header('Location: physical-examination-form.php');
                    exit();
                } else if ($_SESSION['role_id'] == 3 && $is_interviewer) {
                    // Interviewer (role_id 3 + user_staff_roles=Interviewer) - Also redirect to physical examination
                    error_log("Interviewer role: Redirecting to physical examination form");
                    header('Location: physical-examination-form.php');
                    exit();
                } else if ($_SESSION['role_id'] == 3 && $is_physician) {
                    // Physician (role_id 3 + user_staff_roles=physician) - Redirect to physical examination
                    error_log("Physician role: Redirecting to physical examination form");
                    header('Location: physical-examination-form.php');
                    exit();
                } else {
                    // Other staff roles - Return JSON response for AJAX handling
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'screening_id' => $existing_screening_id,
                        'message' => 'Screening form updated successfully'
                    ]);
                    exit();
                }
            } else {
                throw new Exception("Failed to update screening form. HTTP Code: " . $http_code . ", Response: " . $response);
            }
        } else {
            // INSERT new record (original code)
            // Initialize cURL session for Supabase
            $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form');

            // Set the headers
            $headers = array(
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            );

            // Set cURL options
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($screening_data));

            // Execute the request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Debug log
            error_log("Supabase response code: " . $http_code);
            error_log("Supabase response: " . $response);
            
            curl_close($ch);

            if ($http_code === 201) {
                // Parse the response
                $response_data = json_decode($response, true);
                
                if (is_array($response_data) && isset($response_data[0]['screening_id'])) {
                    $_SESSION['screening_id'] = $response_data[0]['screening_id'];
                    
                    // Different redirections based on role
                    if ($_SESSION['role_id'] == 1) {
                        // Admin (role_id 1) - Direct to physical examination
                        error_log("Admin role: Redirecting to physical examination form");
                        header('Location: physical-examination-form.php');
                        exit();
                    } else if ($_SESSION['role_id'] == 3 && $is_interviewer) {
                        // Interviewer (role_id 3 + user_staff_roles=Interviewer) - Also redirect to physical examination
                        error_log("Interviewer role: Redirecting to physical examination form");
                        header('Location: physical-examination-form.php');
                        exit();
                    } else if ($_SESSION['role_id'] == 3 && $is_physician) {
                        // Physician (role_id 3 + user_staff_roles=physician) - Redirect to physical examination
                        error_log("Physician role: Redirecting to physical examination form");
                        header('Location: physical-examination-form.php');
                        exit();
                    } else {
                        // Other staff roles - Return JSON response for AJAX handling
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'screening_id' => $response_data[0]['screening_id'],
                            'message' => 'Screening form submitted successfully'
                        ]);
                        exit();
                    }
                } else {
                    throw new Exception("Invalid response format");
                }
            } else {
                throw new Exception("Failed to submit screening form. HTTP Code: " . $http_code . ", Response: " . $response);
            }
        }
    } catch (Exception $e) {
        error_log("Error in screening form submission: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Screening Form</title>
    <style>
       /* General Styling */
body {
    font-family: Arial, sans-serif;
    background-color: #f9f9f9;
    margin: 0;
    padding: 20px;
}

/* Screening Form Container */
.screening-form {
    background: #fff;
    padding: 2%;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 900px;
    margin: auto;
}

/* Title Styling */
.screening-form-title {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 20px;
}

/* Tables Styling */
.screening-form-table, 
.screening-form-patient, 
.screening-form-history-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.screening-form-table th,
.screening-form-table td,
.screening-form-patient th,
.screening-form-patient td,
.screening-form-history-table th,
.screening-form-history-table td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: center;
}

.screening-form-table th,
.screening-form-patient th,
.screening-form-history-table th {
    background-color: #d9534f;
    color: white;
    font-weight: bold;
}

/* Input Fields inside Tables */
.screening-form-table input,
.screening-form-patient input,
.screening-form-history-table input {
    width: 95%;
    padding: 5px 2px 5px 2px;
    border: 1px solid #bbb;
    border-radius: 4px;
}

/* Donation Section Styling */
.screening-form-donation {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    border: 1px solid #ddd;
}

/* Donation Title Styling */
.screening-form-donation p {
    font-weight: bold;
    color: #721c24;
    margin-bottom: 10px;
    font-size: 18px;
}

/* Donation Type Options */
.donation-options {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.donation-option {
    display: flex;
    align-items: center;
    font-weight: bold;
    color: #721c24;
    gap: 8px;
    background: #fff;
    padding: 10px 15px;
    border-radius: 6px;
    border: 1px solid #ddd;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.donation-option:hover {
    background-color: #f0f0f0;
    transform: translateY(-2px);
}

/* Custom Checkbox Styling */
.donation-option input {
    opacity: 0;
    position: absolute;
}

.checkmark {
    width: 18px;
    height: 18px;
    background-color: #fff;
    border: 2px solid #721c24;
    border-radius: 4px;
    display: inline-block;
    position: relative;
    transition: background-color 0.3s ease;
}

.donation-option input:checked ~ .checkmark {
    background-color: #721c24;
}

.checkmark::after {
    content: "";
    position: absolute;
    display: none;
    left: 5px;
    top: 1px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.donation-option input:checked ~ .checkmark::after {
    display: block;
}

/* Mobile Donation Section */
.mobile-donation-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: #fff;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.mobile-donation-label {
    display: flex;
    align-items: center;
    font-weight: bold;
    color: #721c24;
    gap: 8px;
    cursor: pointer;
}

.mobile-donation-fields {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 10px;
}

.mobile-donation-fields label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-weight: bold;
    color: #721c24;
}

.mobile-donation-fields input[type="text"] {
    width: 100%;
    max-width: 400px;
    padding: 8px;
    border: 1px solid #bbb;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.mobile-donation-fields input[type="text"]:focus {
    border-color: #721c24;
    outline: none;
}

/* Placeholder Styling */
.mobile-donation-fields input::placeholder {
    color: #999;
    font-style: italic;
}

/* History Section */
.screening-form-history {
    background: #d1ecf1;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.screening-form-history p {
    font-weight: bold;
    color: #0c5460;
}

/* Footer Styling */
.screening-form-footer {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin-top: 20px;
}

.screening-form-footer input {
    border: none;
    border-bottom: 1px solid #000;
    padding: 3px;
    width: 50%;
    text-align: center;
}
/* Submit Button Section */
.submit-section {
    text-align: right;
    margin-top: 20px;
}

.submit-button {
    background-color: #d9534f;
    color: white;
    font-weight: bold;
    padding: 12px 22px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    font-size: 15px;
}

.submit-button:hover {
    background-color: #c9302c;
    transform: translateY(-2px);
}

.submit-button:active {
    transform: translateY(0);
}
 /* Submit Button Section */
 .submit-section {
            text-align: right;
            margin-top: 20px;
        }

        .submit-button {
            background-color: #d9534f;
            color: white;
            font-weight: bold;
            padding: 12px 22px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-size: 15px;
        }

        .submit-button:hover {
            background-color: #c9302c;
            transform: translateY(-2px);
        }

        .submit-button:active {
            transform: translateY(0);
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
            border-top: 8px solid #a82020;
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
            from {
                opacity: 0;
                transform: translate(-50%, -55%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
            to {
                opacity: 0;
                transform: translate(-50%, -55%);
            }
        }

        .confirmation-modal.show {
            display: block;
            animation: fadeIn 0.3s ease-in-out forwards;
        }

        .confirmation-modal.hide {
            animation: fadeOut 0.3s ease-in-out forwards;
        }

        .modal-header {
            font-size: 18px;
            font-weight: bold;
            color: #d50000;
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
            background: #c9302c;
            color: white;
        }

        .confirm-action:hover {
            background: #691b19;
        }
/* Responsive Adjustments */
@media (max-width: 600px) {
    .donation-options {
        flex-direction: column;
    }

    .mobile-donation-fields input[type="text"] {
        max-width: 100%;
    }

    .screening-form-footer input {
        width: 80%;
    }

    .screening-form-table th,
    .screening-form-table td,
    .screening-form-patient th,
    .screening-form-patient td,
    .screening-form-history-table th,
    .screening-form-history-table td {
        padding: 6px;
        font-size: 14px;
    }

    .screening-form-donation p {
        font-size: 16px;
    }

    .screening-form-title {
        font-size: 20px;
    }
}

.disapprove-button {
    background-color: #dc3545;
    color: white;
    font-weight: bold;
    padding: 12px 22px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    font-size: 15px;
    margin-left: 10px;
}

.disapprove-button:hover {
    background-color: #c82333;
    transform: translateY(-2px);
}

.disapprove-action {
    background: #dc3545;
    color: white;
}

.disapprove-action:hover {
    background: #c82333;
}

.modal-body {
    margin: 15px 0;
}

.modal-body textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    resize: vertical;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
}

.history-table th, .history-table td {
    padding: 10px;
    border: 1px solid #ddd;
}

.history-table th {
    background-color: #d9534f;
    color: white;
    text-align: left;
}

.history-table td input {
    width: 100%;
    padding: 5px;
    border: 1px solid #ccc;
}

.history-table tr:first-child th {
    text-align: center;
}

select {
    width: 95%;
    padding: 5px 2px 5px 2px;
    border: 1px solid #bbb;
    border-radius: 4px;
    background-color: white;
}

select:focus {
    outline: none;
    border-color: #721c24;
}

    </style>
</head>
<body>
    <form method="POST" action="" id="screeningForm">
        <?php 
        // Add hidden fields for screening_id and donor_id
        if (isset($screening_id)): 
        ?>
        <input type="hidden" name="screening_id" value="<?php echo htmlspecialchars($screening_id); ?>">
        <?php endif; ?>
        
        <?php if (isset($donor_id)): ?>
        <input type="hidden" name="donor_id" value="<?php echo htmlspecialchars($donor_id); ?>">
        <?php endif; ?>
        
        <div class="screening-form">
            <h2 class="screening-form-title">IV. INITIAL SCREENING <span>(To be filled up by the interviewer)</span></h2>
            
            <?php if ($is_physician): ?>
            <!-- Special message for physicians -->
            <div class="physician-notice" style="background-color: #d1ecf1; padding: 15px; margin-bottom: 20px; border-radius: 5px; border-left: 5px solid #0c5460;">
                <h4 style="color: #0c5460; margin-top: 0;">Physician View</h4>
                <p style="margin-bottom: 5px;">You are viewing this form as a physician. The Body Weight data shown below was submitted by the interviewer and is read-only.</p>
                <p style="margin-bottom: 5px;"><strong>Important:</strong> You must fill out all other required fields before proceeding.</p>
                <p style="margin-bottom: 0;">After completing the form, click the "Submit and Proceed" button to continue to the physical examination form.</p>
            </div>
            <?php endif; ?>
            
            <table class="screening-form-table">
                <tr>
                    <th>BODY WT</th>
                    <th>SP. GR</th>
                    <th>BLOOD TYPE</th>
                </tr>
                <tr>
                    <td>
                        <?php if ($is_physician && $body_wt_data !== null): ?>
                            <!-- For physicians: Display the body weight in read-only format -->
                            <div style="font-weight: bold; font-size: 1.2em; color: #721c24; background-color: #f8d7da; padding: 8px; border-radius: 4px;">
                                <?php echo htmlspecialchars($body_wt_data); ?>
                            </div>
                            <input type="hidden" name="body-wt" value="<?php echo htmlspecialchars($body_wt_data); ?>">
                        <?php else: ?>
                            <!-- Regular editable field for others -->
                            <input type="number" step="0.01" name="body-wt" value="<?php echo isset($_POST['body-wt']) ? htmlspecialchars($_POST['body-wt']) : ''; ?>" required>
                        <?php endif; ?>
                    </td>
                    <td><input type="text" name="sp-gr" value="<?php echo isset($_POST['sp-gr']) ? htmlspecialchars($_POST['sp-gr']) : ''; ?>" required></td>
                    <td>
                        <select name="blood-type" required>
                            <option value="" disabled <?php echo !isset($_POST['blood-type']) ? 'selected' : ''; ?>>Select Blood Type</option>
                            <?php
                            $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                            foreach ($bloodTypes as $type) {
                                $selected = (isset($_POST['blood-type']) && $_POST['blood-type'] === $type) ? 'selected' : '';
                                echo "<option value=\"$type\" $selected>$type</option>";
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>

            <div class="screening-form-donation">
                <p>TYPE OF DONATION (Donor's Choice):</p>
                <div class="donation-options">
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="in-house" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'in-house') || (isset($donation_type) && $donation_type === 'in-house') ? 'checked' : ''; ?> required> 
                        <span class="checkmark"></span>
                        IN-HOUSE
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="walk-in" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'walk-in') || (isset($donation_type) && $donation_type === 'walk-in') ? 'checked' : (isset($_SESSION['role_id']) && $_SESSION['role_id'] === 1 && empty($donation_type) ? 'checked' : ''); ?> required> 
                        <span class="checkmark"></span>
                        WALK-IN/VOLUNTARY
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="replacement" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'replacement') || (isset($donation_type) && $donation_type === 'replacement') ? 'checked' : ''; ?> required> 
                        <span class="checkmark"></span>
                        REPLACEMENT
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="patient-directed" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'patient-directed') || (isset($donation_type) && $donation_type === 'patient-directed') ? 'checked' : ''; ?> required> 
                        <span class="checkmark"></span>
                        PATIENT-DIRECTED
                    </label>
                    <label class="donation-option">
                        <input type="radio" name="donation-type" value="mobile" <?php echo (isset($_POST['donation-type']) && $_POST['donation-type'] === 'mobile') || (isset($donation_type) && $donation_type === 'mobile') ? 'checked' : ''; ?> required> 
                        <span class="checkmark"></span>
                        Mobile Blood Donation
                    </label>
                </div>
                
                <div class="mobile-donation-section" id="mobileDonationSection" style="display: none;">
                    <div class="mobile-donation-fields">
                        <label>
                            PLACE: 
                            <input type="text" name="mobile-place" value="<?php echo isset($_POST['mobile-place']) ? htmlspecialchars($_POST['mobile-place']) : ''; ?>">
                        </label>
                        <label>
                            ORGANIZER: 
                            <input type="text" name="mobile-organizer" value="<?php echo isset($_POST['mobile-organizer']) ? htmlspecialchars($_POST['mobile-organizer']) : ''; ?>">
                        </label>
                    </div>
                </div>
            </div>
            

            <table class="screening-form-patient" id="patientDetailsTable" style="display: none;">
                <tr>
                    <th>Patient Name</th>
                    <th>Hospital</th>
                    <th>Blood Type</th>
                    <th>WB/Component</th>
                    <th>No. of units</th>
                </tr>
                <tr>
                    <td><input type="text" name="patient-name" value="<?php echo isset($_POST['patient-name']) ? htmlspecialchars($_POST['patient-name']) : ''; ?>" <?php echo $is_physician ? 'readonly' : ''; ?>></td>
                    <td><input type="text" name="hospital" value="<?php echo isset($_POST['hospital']) ? htmlspecialchars($_POST['hospital']) : ''; ?>" <?php echo $is_physician ? 'readonly' : ''; ?>></td>
                    <td>
                        <select name="blood-type-patient">
                            <option value="" disabled <?php echo !isset($_POST['blood-type-patient']) ? 'selected' : ''; ?>>Select Blood Type</option>
                            <?php
                            foreach ($bloodTypes as $type) {
                                $selected = (isset($_POST['blood-type-patient']) && $_POST['blood-type-patient'] === $type) ? 'selected' : '';
                                echo "<option value=\"$type\" $selected>$type</option>";
                            }
                            ?>
                        </select>
                    </td>
                    <td><input type="text" name="wb-component" value="<?php echo isset($_POST['wb-component']) ? htmlspecialchars($_POST['wb-component']) : ''; ?>"></td>
                    <td><input type="number" name="no-units" value="<?php echo isset($_POST['no-units']) ? htmlspecialchars($_POST['no-units']) : ''; ?>"></td>
                </tr>
            </table>

            <div class="screening-form-history">
                <p>History of previous donation? (Donor's Opinion)</p>
                <label><input type="radio" name="history" value="yes" required> YES</label>
                <label><input type="radio" name="history" value="no" required> NO</label>
            </div>

            <table class="screening-form-history-table">
                <tr>
                    <th></th>
                    <th>Red Cross</th>
                    <th>Hospital</th>
                </tr>
                <tr>
                    <th>No. of times</th>
                    <td><input type="number" name="red-cross" min="0" value="<?php echo isset($_POST['red-cross']) ? htmlspecialchars($_POST['red-cross']) : '0'; ?>"></td>
                    <td><input type="number" name="hospital-history" min="0" value="<?php echo isset($_POST['hospital-history']) ? htmlspecialchars($_POST['hospital-history']) : '0'; ?>"></td>
                </tr>
                <tr>
                    <th>Date of last donation</th>
                    <td><input type="date" name="last-rc-donation-date" value="<?php echo isset($_POST['last-rc-donation-date']) ? htmlspecialchars($_POST['last-rc-donation-date']) : ''; ?>"></td>
                    <td><input type="date" name="last-hosp-donation-date" value="<?php echo isset($_POST['last-hosp-donation-date']) ? htmlspecialchars($_POST['last-hosp-donation-date']) : ''; ?>"></td>
                </tr>
                <tr>
                    <th>Place of last donation</th>
                    <td><input type="text" name="last-rc-donation-place" value="<?php echo isset($_POST['last-rc-donation-place']) ? htmlspecialchars($_POST['last-rc-donation-place']) : ''; ?>"></td>
                    <td><input type="text" name="last-hosp-donation-place" value="<?php echo isset($_POST['last-hosp-donation-place']) ? htmlspecialchars($_POST['last-hosp-donation-place']) : ''; ?>"></td>
                </tr>
            </table>

            <div class="screening-form-footer">
                <label>INTERVIEWER (print name & sign): <input type="text" name="interviewer" value="<?php echo htmlspecialchars($interviewer_name); ?>" readonly></label>
                <label>PRC Office</label>
                <p>Date: <?php echo date('m/d/Y'); ?></p>
            </div>
            
            <?php if ($is_physician): ?>
            <!-- Additional note for physicians -->
            <div style="margin: 20px 0; padding: 10px; background-color: #fff3cd; border-left: 5px solid #856404; border-radius: 5px;">
                <p style="color: #856404; margin: 0;"><strong>Note:</strong> As a physician, you can edit all fields except body weight. Please complete this form with the necessary information and then click the button below to proceed to the physical examination form.</p>
            </div>
            <?php endif; ?>
            
            <div class="submit-section">
                <?php if ($is_physician): ?>
                <!-- For physicians: Provide a direct button to the physical examination form -->
                <button type="button" class="submit-button" id="physicianProceedButton" style="background-color: #0c5460;">
                    Submit and Proceed to Physical Examination
                </button>
                <?php else: ?>
                <!-- Regular submit button for other roles -->
                <button type="button" class="submit-button" id="triggerModalButton">Submit</button>
                <?php endif; ?>
            </div>
        </div>
    </form>
    <!-- Existing Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationDialog">
        <div class="modal-header">Do you want to continue?</div>
        <div class="modal-actions">
            <button class="modal-button cancel-action" id="cancelButton">No</button>
            <button class="modal-button confirm-action" id="confirmButton">Yes</button>
        </div>
    </div>    

    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner"></div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let confirmationDialog = document.getElementById("confirmationDialog");
            let loadingSpinner = document.getElementById("loadingSpinner");
            let triggerModalButton = document.getElementById("triggerModalButton");
            let cancelButton = document.getElementById("cancelButton");
            let confirmButton = document.getElementById("confirmButton");
            let form = document.getElementById("screeningForm");
            
            <?php if ($is_physician): ?>
            // Special handling for physician workflow
            const physicianProceedButton = document.getElementById("physicianProceedButton");
            if (physicianProceedButton) {
                physicianProceedButton.addEventListener("click", function() {
                    // Validate form fields first
                    if (!form.checkValidity()) {
                        alert("Please fill in all required fields before proceeding.");
                        return;
                    }
                    
                    loadingSpinner.style.display = "block";
                    
                    // Get current URL parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    const screeningId = urlParams.get('screening_id');
                    const donorId = urlParams.get('donor_id');
                    
                    console.log("Current URL parameters:", window.location.search);
                    console.log("Screening ID from URL:", screeningId);
                    console.log("Donor ID from URL:", donorId);
                    
                    // Debug session data
                    console.log("Submitting form with data:");
                    const formData = new FormData(form);
                    for (let [key, value] of formData.entries()) {
                        console.log(`${key}: ${value}`);
                    }
                    
                    // Add screening_id and donor_id from URL if available
                    if (screeningId) {
                        formData.append('screening_id', screeningId);
                    }
                    if (donorId) {
                        formData.append('donor_id', donorId);
                    }
                    
                    // Submit form
                    console.log("Submitting form...");
                    form.submit();
                });
            }
            <?php else: ?>
            // Open Submit Modal
            triggerModalButton.addEventListener("click", function() {
                <?php if ($is_interviewer): ?>
                // For interviewers, only validate Body WT
                const bodyWt = document.querySelector('input[name="body-wt"]');
                if (!bodyWt.value || isNaN(bodyWt.value) || parseFloat(bodyWt.value) <= 0) {
                    alert("Please enter a valid Body Weight value.");
                    return;
                }
                <?php else: ?>
                // For other roles, validate all required fields
                if (!form.checkValidity()) {
                    alert("Please fill in all required fields before proceeding.");
                    return;
                }
                <?php endif; ?>

                confirmationDialog.classList.remove("hide");
                confirmationDialog.classList.add("show");
                confirmationDialog.style.display = "block";
                triggerModalButton.disabled = true;
            });
            <?php endif; ?>

            // Close Submit Modal
            function closeModal() {
                confirmationDialog.classList.remove("show");
                confirmationDialog.classList.add("hide");
                setTimeout(() => {
                    confirmationDialog.style.display = "none";
                    triggerModalButton.disabled = false;
                }, 300);
            }

            // Add radio button change handler for mobile donation section and patient details
            const donationTypeRadios = document.querySelectorAll('input[name="donation-type"]');
            const mobileDonationSection = document.getElementById('mobileDonationSection');
            const patientDetailsTable = document.getElementById('patientDetailsTable');

            donationTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    updateDonationTypeSections(this.value);
                });
            });
            
            // Function to update the display of sections based on donation type
            function updateDonationTypeSections(donationType) {
                if (donationType === 'mobile') {
                    // Show mobile donation section with only Place and Organizer
                    mobileDonationSection.style.display = 'block';
                    // Hide patient details table
                    patientDetailsTable.style.display = 'none';
                } else if (donationType === 'patient-directed') {
                    // Show patient details table
                    patientDetailsTable.style.display = 'table';
                    // Hide mobile donation section
                    mobileDonationSection.style.display = 'none';
                } else {
                    // Hide both sections for other options
                    mobileDonationSection.style.display = 'none';
                    patientDetailsTable.style.display = 'none';
                }
            }

            // Check initial state for donation type
            const selectedDonationType = document.querySelector('input[name="donation-type"]:checked');
            if (selectedDonationType) {
                updateDonationTypeSections(selectedDonationType.value);
            }
            
            <?php if (isset($donation_type) && !empty($donation_type)): ?>
            // Initialize display based on the donation type from database
            updateDonationTypeSections('<?php echo $donation_type; ?>');
            console.log("Initializing sections based on donation type from database: <?php echo $donation_type; ?>");
            <?php endif; ?>

            // Handle Submit Confirmation
            confirmButton.addEventListener("click", function() {
                // Validate numeric fields
                const numericFields = {
                    'body-wt': 'Body Weight',
                    'no-units': 'Number of Units'
                };

                for (const [fieldName, label] of Object.entries(numericFields)) {
                    const field = document.querySelector(`input[name="${fieldName}"]`);
                    if (field && field.value && isNaN(field.value)) {
                        alert(`${label} must be a valid number`);
                        return;
                    }
                }

                closeModal();
                loadingSpinner.style.display = "block";
                
                // Get all form data
                const formData = new FormData(form);
                
                // Submit the form
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text(); // Get the raw text first
                })
                .then(text => {
                    try {
                        // Try to parse as JSON
                        if (text.trim() === '') {
                            // Empty response - likely a redirect
                            console.log("Empty response, assuming redirect");
                            window.location.href = 'physical-examination-form.php';
                            return null;
                        }

                        // Check if it starts with a proper JSON character
                        if (text.trim().startsWith('{') || text.trim().startsWith('[')) {
                            return JSON.parse(text);
                        } else {
                            // Not valid JSON - could be HTML or redirect
                            console.log("Response is not valid JSON:", text.substring(0, 100));
                            console.log("Redirecting to physical examination form");
                            window.location.href = 'physical-examination-form.php';
                            return null;
                        }
                    } catch (e) {
                        console.error("JSON parse error:", e);
                        console.log("First 100 chars of response:", text.substring(0, 100));
                        // Redirect on parsing error
                        window.location.href = 'physical-examination-form.php';
                        return null;
                    }
                })
                .then(data => {
                    loadingSpinner.style.display = "none";
                    if (data === null) {
                        // Already redirected for non-JSON response
                        return;
                    }
                    if (data.success) {
                        window.location.href = "../../../public/Dashboards/dashboard-staff-donor-submission.php";
                    } else {
                        throw new Error(data.error || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    loadingSpinner.style.display = "none";
                    alert(error.message || "Error submitting form. Please try again.");
                });
            });

            // Cancel Submit
            cancelButton.addEventListener("click", closeModal);

            // Add handler for donation history radio buttons
            const historyRadios = document.querySelectorAll('input[name="history"]');
            const historyTable = document.querySelector('.screening-form-history-table');

            historyRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    const historyInputs = historyTable.querySelectorAll('input');
                    
                    if (this.value === 'yes') {
                        historyInputs.forEach(input => {
                            input.removeAttribute('disabled');
                            if (input.type === 'number' && !input.value) {
                                input.value = '0';
                            }
                        });
                    } else {
                        historyInputs.forEach(input => {
                            input.setAttribute('disabled', 'disabled');
                            if (input.type === 'number') {
                                input.value = '0';
                            } else {
                                input.value = '';
                            }
                        });
                    }
                });
            });

            // Check initial state of history radio
            const selectedHistory = document.querySelector('input[name="history"]:checked');
            if (selectedHistory) {
                selectedHistory.dispatchEvent(new Event('change'));
            }

            // Restrict only certain fields for interviewers
            <?php if ($is_interviewer): ?>
            console.log("Interviewer role detected - restricting fields");
            
            // Only disable specific fields in the top section
            const restrictedFields = document.querySelectorAll('input[name="sp-gr"], input[name="blood-type"], select[name="blood-type"]');
            
            restrictedFields.forEach(field => {
                field.disabled = true;
                field.style.backgroundColor = '#f5f5f5';
                field.style.cursor = 'not-allowed';
                if (field.hasAttribute('required')) {
                    field.removeAttribute('required');
                }
            });
            
            // Make sure Body WT is enabled
            const bodyWeight = document.querySelector('input[name="body-wt"]');
            if (bodyWeight) {
                bodyWeight.disabled = false;
                bodyWeight.style.backgroundColor = '#ffffff';
                bodyWeight.style.cursor = 'text';
                bodyWeight.required = true;
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>