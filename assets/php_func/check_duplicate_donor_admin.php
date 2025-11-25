<?php
// check_duplicate_donor_admin.php
// API endpoint to check for duplicate donors based on personal information (Admin-specific version)
// This is a copy of check_duplicate_donor.php with admin-specific naming to avoid conflicts

// Enable error reporting for debugging (but don't display errors to avoid JSON corruption)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors - they corrupt JSON
ini_set('log_errors', 1);

// Set content type to JSON FIRST (before any output)
header('Content-Type: application/json');

// Handle CORS if needed
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

header('Access-Control-Allow-Origin: *');

try {
    // Include database connection configuration
    require_once __DIR__ . '/../conn/db_conn.php';
    
    // Check if Supabase constants are properly set
    if (empty(SUPABASE_URL) || empty(SUPABASE_API_KEY)) {
        throw new Exception("Supabase configuration is missing");
    }
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed");
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }
    
    // Validate required fields
    $required_fields = ['surname', 'first_name', 'birthdate'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitize input data
    $surname = trim($input['surname']);
    $first_name = trim($input['first_name']);
    $middle_name = trim($input['middle_name'] ?? '');
    $birthdate = trim($input['birthdate']);
    $email = trim($input['email'] ?? '');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        throw new Exception("Invalid date format. Expected YYYY-MM-DD");
    }
    
    // First, check for duplicate email if email is provided
    if (!empty($email)) {
        $email_check_url = SUPABASE_URL . "/rest/v1/donor_form?select=donor_id,prc_donor_number,surname,first_name,middle_name,birthdate,age,sex,mobile,email,submitted_at&email=eq." . urlencode($email);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $email_check_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ],
        ]);
        
        $email_response = curl_exec($curl);
        $email_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($email_http_code === 200) {
            $email_matches = json_decode($email_response, true);
            if (is_array($email_matches) && !empty($email_matches)) {
                // Found duplicate email - return this as a duplicate
                $duplicate_data = $email_matches[0];
                
                // Format name for display
                $full_name = $duplicate_data['surname'] . ", " . $duplicate_data['first_name'];
                if (!empty($duplicate_data['middle_name'])) {
                    $full_name .= " " . $duplicate_data['middle_name'];
                }
                
                // Calculate time description
                $submitted_at = $duplicate_data['submitted_at'] ?? null;
                $time_description = "Previously registered";
                if ($submitted_at) {
                    $created_at = strtotime($submitted_at);
                    $time_diff = time() - $created_at;
                    $days_ago = floor($time_diff / (60 * 60 * 24));
                    $hours_ago = floor($time_diff / (60 * 60));
                    
                    if ($days_ago > 0) {
                        $time_description = $days_ago . " day" . ($days_ago > 1 ? "s" : "") . " ago";
                    } elseif ($hours_ago > 0) {
                        $time_description = $hours_ago . " hour" . ($hours_ago > 1 ? "s" : "") . " ago";
                    } else {
                        $time_description = "less than an hour ago";
                    }
                }
                
                echo json_encode([
                    'status' => 'success',
                    'duplicate_found' => true,
                    'message' => 'Duplicate email found',
                    'duplicate_type' => 'email',
                    'data' => [
                        'donor_id' => $duplicate_data['donor_id'],
                        'prc_donor_number' => $duplicate_data['prc_donor_number'] ?? null,
                        'full_name' => $full_name,
                        'birthdate' => $duplicate_data['birthdate'],
                        'age' => $duplicate_data['age'],
                        'sex' => $duplicate_data['sex'],
                        'mobile' => $duplicate_data['mobile'],
                        'email' => $duplicate_data['email'],
                        'registration_date' => $duplicate_data['submitted_at'],
                        'time_description' => $time_description,
                        'status_message' => 'Email already registered',
                        'alert_type' => 'warning',
                        'reason' => 'This email address is already registered to another donor.',
                        'suggestion' => 'Please verify if this is the same person or use a different email address.',
                        'can_donate_today' => false,
                        'total_matches' => count($email_matches),
                        'has_eligibility_history' => false,
                        'donation_stage' => null,
                        'total_donations' => 0,
                        'total_eligibility_records' => 0
                    ]
                ]);
                exit;
            }
        }
    }
    
    // Build the query to find matching donors who have eligibility records
    // Using Supabase PostgREST API syntax
    $query_conditions = [];
    
    // Exact match for surname, first_name, and birthdate
    $query_conditions[] = "surname=eq." . urlencode($surname);
    $query_conditions[] = "first_name=eq." . urlencode($first_name);
    $query_conditions[] = "birthdate=eq." . urlencode($birthdate);
    
    $query_string = implode('&', $query_conditions);
    
    // First, get matching donors from donor_form table (using correct column names)
    $donors_url = SUPABASE_URL . "/rest/v1/donor_form?select=donor_id,prc_donor_number,surname,first_name,middle_name,birthdate,age,sex,mobile,email,submitted_at&" . $query_string;
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $donors_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($curl_error) {
        throw new Exception("Database query error: " . $curl_error);
    }
    
    if ($http_code !== 200) {
        error_log("Supabase API Error - HTTP Code: $http_code");
        error_log("Query URL: $donors_url");
        error_log("Response: $response");
        throw new Exception("Database query failed with HTTP code: " . $http_code . " - Response: " . substr($response, 0, 200));
    }
    
    $matching_donors = json_decode($response, true);
    
    if (!is_array($matching_donors)) {
        throw new Exception("Invalid response from database");
    }
    
    // If no matching donors found, return immediately
    if (empty($matching_donors)) {
        echo json_encode([
            'status' => 'success',
            'duplicate_found' => false,
            'message' => 'No existing donor found',
            'data' => null
        ]);
        exit;
    }
    
    // Filter results for exact middle name matching
    $filtered_donors = [];
    foreach ($matching_donors as $donor) {
        $donor_middle = trim($donor['middle_name'] ?? '');
        $search_middle = trim($middle_name);
        
        // Check if middle names match exactly (both empty or both same)
        if (empty($search_middle) && empty($donor_middle)) {
            // Both are empty - this is a match
            $filtered_donors[] = $donor;
        } elseif (!empty($search_middle) && !empty($donor_middle) && 
                  strtolower($search_middle) === strtolower($donor_middle)) {
            // Both have values and they match (case insensitive)
            $filtered_donors[] = $donor;
        }
        // If one is empty and the other isn't, it's not a match
    }
    
    $matching_donors = $filtered_donors;
    
    // Check if we have any matches after middle name filtering
    if (empty($matching_donors)) {
        echo json_encode([
            'status' => 'success',
            'duplicate_found' => false,
            'message' => 'No existing donor found with matching criteria',
            'data' => null
        ]);
        exit;
    }
    
    // Now check eligibility records for each matching donor
    // We'll process ALL matching donors, whether they have eligibility or not
    $processed_donors = [];
    
    foreach ($matching_donors as $donor) {
        $donor_id = $donor['donor_id'];
        
        // Initialize donor data
        $donor['total_donations'] = 0;
        $donor['total_eligibility_records'] = 0;
        $donor['latest_eligibility'] = null;
        $donor['all_eligibility_records'] = [];
        $donor['has_eligibility'] = false;
        
        // Get ALL eligibility records to count total donations
        $all_eligibility_url = SUPABASE_URL . "/rest/v1/eligibility?select=eligibility_id,status,disapproval_reason,created_at,start_date,end_date,temporary_deferred,collection_successful&donor_id=eq." . $donor_id . "&order=created_at.desc";
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $all_eligibility_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ],
        ]);
        
        $eligibility_response = curl_exec($curl);
        $eligibility_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($eligibility_http_code === 200) {
            $all_eligibility_records = json_decode($eligibility_response, true);
            
            if (is_array($all_eligibility_records) && !empty($all_eligibility_records)) {
                // Count total donations (successful collections)
                $total_donations = 0;
                foreach ($all_eligibility_records as $record) {
                    if (isset($record['collection_successful']) && $record['collection_successful'] === true) {
                        $total_donations++;
                    }
                }
                
                // Get the latest eligibility record
                $latest_eligibility = $all_eligibility_records[0];
                
                // Add all eligibility information to donor data
                $donor['latest_eligibility'] = $latest_eligibility;
                $donor['all_eligibility_records'] = $all_eligibility_records;
                $donor['total_donations'] = $total_donations;
                $donor['total_eligibility_records'] = count($all_eligibility_records);
                $donor['has_eligibility'] = true;
            }
        }
        
        // Add donor to processed list (whether they have eligibility or not)
        $processed_donors[] = $donor;
    }
    
    // Sort donors: those with eligibility first, then by submitted_at
    usort($processed_donors, function($a, $b) {
        // First priority: donors with eligibility records
        if ($a['has_eligibility'] && !$b['has_eligibility']) {
            return -1;
        }
        if (!$a['has_eligibility'] && $b['has_eligibility']) {
            return 1;
        }
        // Second priority: most recently submitted
        $a_time = strtotime($a['submitted_at'] ?? '1970-01-01');
        $b_time = strtotime($b['submitted_at'] ?? '1970-01-01');
        return $b_time - $a_time;
    });
    
    // Prepare response - use the first (most relevant) donor
    if (!empty($processed_donors)) {
        // Found duplicate donor(s) - use the first one (prioritizes those with eligibility)
        $duplicate_data = $processed_donors[0];
        
        // Calculate how long ago the donor was registered using submitted_at
        $submitted_at = $duplicate_data['submitted_at'] ?? null;
        $time_description = "Previously registered";
        
        if ($submitted_at) {
            $created_at = strtotime($submitted_at);
            $time_diff = time() - $created_at;
            $days_ago = floor($time_diff / (60 * 60 * 24));
            $hours_ago = floor($time_diff / (60 * 60));
            
            if ($days_ago > 0) {
                $time_description = $days_ago . " day" . ($days_ago > 1 ? "s" : "") . " ago";
            } elseif ($hours_ago > 0) {
                $time_description = $hours_ago . " hour" . ($hours_ago > 1 ? "s" : "") . " ago";
            } else {
                $time_description = "less than an hour ago";
            }
        }
        
        // Format name for display
        $full_name = $duplicate_data['surname'] . ", " . $duplicate_data['first_name'];
        if (!empty($duplicate_data['middle_name'])) {
            $full_name .= " " . $duplicate_data['middle_name'];
        }
        
        // Get donation statistics
        $total_donations = $duplicate_data['total_donations'] ?? 0;
        $total_eligibility_records = $duplicate_data['total_eligibility_records'] ?? 0;
        $has_eligibility = $duplicate_data['has_eligibility'] ?? false;
        
        // Check for temporary_deferred in latest eligibility (only if eligibility exists)
        $temporary_deferred = null;
        $temporary_deferred_days_remaining = null;
        $temporary_deferred_text = null;
        
        if ($has_eligibility && isset($duplicate_data['latest_eligibility'])) {
            $temporary_deferred = $duplicate_data['latest_eligibility']['temporary_deferred'] ?? null;
        }
        
        // Parse temporary_deferred if it exists
        if (!empty($temporary_deferred)) {
            $temporary_deferred_text = $temporary_deferred;
            
            // Calculate days remaining from end_date if available
            $end_date = $duplicate_data['latest_eligibility']['end_date'] ?? null;
            if ($end_date) {
                $end_timestamp = strtotime($end_date);
                $current_timestamp = time();
                $days_remaining = ceil(($end_timestamp - $current_timestamp) / (60 * 60 * 24));
                
                if ($days_remaining > 0) {
                    $temporary_deferred_days_remaining = $days_remaining;
                } else {
                    $temporary_deferred_days_remaining = 0; // Deferral period has ended
                }
            } else {
                // Try to parse from temporary_deferred text (format: "X months Y days" or "X days")
                if (preg_match('/(\d+)\s*(?:month|months)/', $temporary_deferred, $month_matches)) {
                    $months = intval($month_matches[1]);
                }
                if (preg_match('/(\d+)\s*(?:day|days)/', $temporary_deferred, $day_matches)) {
                    $days = intval($day_matches[1]);
                }
                
                // If we have the created_at date, calculate end date
                if (isset($duplicate_data['latest_eligibility']['created_at'])) {
                    $created_at = strtotime($duplicate_data['latest_eligibility']['created_at']);
                    $total_days = (isset($months) ? $months * 30 : 0) + (isset($days) ? $days : 0);
                    $end_timestamp = $created_at + ($total_days * 24 * 60 * 60);
                    $current_timestamp = time();
                    $days_remaining = ceil(($end_timestamp - $current_timestamp) / (60 * 60 * 24));
                    
                    if ($days_remaining > 0) {
                        $temporary_deferred_days_remaining = $days_remaining;
                    } else {
                        $temporary_deferred_days_remaining = 0;
                    }
                }
            }
        }
        
        // Determine status message with intelligent suggestions
        $status_message = "Active donor";
        $alert_type = "warning";
        $suggestion = "Please verify donor information before proceeding.";
        $reason = "";
        $can_donate_today = false;
        
        // If donor has no eligibility records, check their progress stage
        $donation_stage = null;
        if (!$has_eligibility) {
            $donor_id = $duplicate_data['donor_id'];
            
            // Check if donor has physical_examination record
            $physical_exam_url = SUPABASE_URL . "/rest/v1/physical_examination?select=physical_exam_id,created_at&donor_id=eq." . $donor_id . "&order=created_at.desc&limit=1";
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $physical_exam_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ],
            ]);
            
            $physical_response = curl_exec($curl);
            $physical_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($physical_http_code === 200) {
                $physical_records = json_decode($physical_response, true);
                
                if (is_array($physical_records) && !empty($physical_records)) {
                    $physical_exam = $physical_records[0];
                    $physical_exam_id = $physical_exam['physical_exam_id'];
                    
                    // Check if this physical_exam has a blood_collection record
                    $blood_collection_url = SUPABASE_URL . "/rest/v1/blood_collection?select=blood_collection_id,created_at&physical_exam_id=eq." . urlencode($physical_exam_id) . "&order=created_at.desc&limit=1";
                    
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => $blood_collection_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 5,
                        CURLOPT_HTTPHEADER => [
                            "apikey: " . SUPABASE_API_KEY,
                            "Authorization: Bearer " . SUPABASE_API_KEY,
                            "Content-Type: application/json"
                        ],
                    ]);
                    
                    $blood_response = curl_exec($curl);
                    $blood_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    curl_close($curl);
                    
                    if ($blood_http_code === 200) {
                        $blood_records = json_decode($blood_response, true);
                        
                        if (is_array($blood_records) && !empty($blood_records)) {
                            // Donor has reached blood collection stage
                            $donation_stage = "Collection of Blood";
                            $status_message = "Registered Donor - Blood Collection Stage";
                            $alert_type = "info";
                            $suggestion = "This donor was in the Collection of Blood stage.";
                            $can_donate_today = true;
                            $reason = "This data was in the Collection of Blood stage.";
                        } else {
                            // Donor has physical exam but no blood collection
                            $donation_stage = "Physical Examination";
                            $status_message = "Registered Donor - Physical Examination Stage";
                            $alert_type = "info";
                            $suggestion = "This donor was in the Physical Examination stage.";
                            $can_donate_today = true;
                            $reason = "This data was in the Physical Examination stage.";
                        }
                    } else {
                        // Error checking blood collection, but we know they have physical exam
                        $donation_stage = "Physical Examination";
                        $status_message = "Registered Donor - Physical Examination Stage";
                        $alert_type = "info";
                        $suggestion = "This donor was in the Physical Examination stage. They may proceed with donation screening.";
                        $can_donate_today = true;
                        $reason = "This data was in the Physical Examination stage.";
                    }
                } else {
                    // No physical examination - donor is in interview stage
                    $donation_stage = "Interview";
                    $status_message = "Registered Donor - Interview Stage";
                    $alert_type = "info";
                    $suggestion = "This donor was in the Interview stage.";
                    $can_donate_today = true;
                    $reason = "This data was in the Interview Stage.";
                }
            } else {
                // Error checking physical exam - assume interview stage
                $donation_stage = "Interview";
                $status_message = "Registered Donor - Interview Stage";
                $alert_type = "info";
                $suggestion = "This donor was in the Interview stage. They may proceed with donation screening.";
                $can_donate_today = true;
                $reason = "This data was in the Interview Stage.";
            }
            
            // Fallback if stage wasn't determined
            if ($donation_stage === null) {
                $donation_stage = "Interview";
                $status_message = "Registered Donor (No Donation History)";
                $alert_type = "info";
                $suggestion = "This donor is registered but has no donation history.";
                $can_donate_today = true;
                $reason = "Donor registered but has not yet completed any donation process.";
            }
        } else if (isset($duplicate_data['latest_eligibility']['status'])) {
            $status = strtolower($duplicate_data['latest_eligibility']['status']);
            $disapproval_reason = $duplicate_data['latest_eligibility']['disapproval_reason'] ?? '';
            $end_date = $duplicate_data['latest_eligibility']['end_date'] ?? null;
            
            // Calculate days since last donation
            $days_since_donation = 0;
            if ($duplicate_data['latest_eligibility']['created_at']) {
                $last_donation = strtotime($duplicate_data['latest_eligibility']['created_at']);
                $days_since_donation = floor((time() - $last_donation) / (60 * 60 * 24));
            }
            
            // Check if temporary_deferred exists (this takes priority)
            if (!empty($temporary_deferred)) {
                if ($temporary_deferred_days_remaining !== null && $temporary_deferred_days_remaining > 0) {
                    $status_message = "Temporarily Deferred";
                    $alert_type = "warning";
                    $reason = "Temporary deferral: " . $temporary_deferred_text;
                    $can_donate_today = false;
                    
                    $suggestion = "Donor is temporarily deferred. Deferral period: {$temporary_deferred_text}. {$temporary_deferred_days_remaining} day(s) remaining before re-evaluation.";
                } else if ($temporary_deferred_days_remaining === 0) {
                    $status_message = "Deferral Period Ended";
                    $alert_type = "info";
                    $reason = "Temporary deferral period has ended: " . $temporary_deferred_text;
                    $can_donate_today = false; // Still needs medical re-evaluation
                    $suggestion = "Temporary deferral period has ended. Donor may be eligible for re-evaluation. Contact medical staff.";
                } else {
                    $status_message = "Temporarily Deferred";
                    $alert_type = "warning";
                    $reason = "Temporary deferral: " . $temporary_deferred_text;
                    $can_donate_today = false;
                    $suggestion = "Donor is temporarily deferred: {$temporary_deferred_text}. Contact medical staff for re-evaluation.";
                }
            }
            
            // Only process status switch if temporary_deferred is not set (to avoid overriding)
            if (empty($temporary_deferred)) {
                switch ($status) {
                    case 'eligible':
                        $status_message = "Ready to donate";
                        $alert_type = "success";
                        $can_donate_today = true;
                        $suggestion = "This donor is cleared and ready to donate today.";
                        break;
                        
                    case 'ineligible':
                        $status_message = "Recently donated";
                        $alert_type = "warning";
                        $reason = $disapproval_reason ?: "Recent donation within waiting period";
                        
                        if ($days_since_donation >= 56) { // 8 weeks
                            $suggestion = "Waiting period completed. Donor may be eligible now.";
                            $can_donate_today = true;
                        } else {
                            $weeks_remaining = ceil((56 - $days_since_donation) / 7);
                            $days_remaining = 56 - $days_since_donation;
                            $suggestion = "Donor recently donated. Not eligible to donate for $days_remaining day(s).";
                        }
                        break;
                        
                    case 'approved':
                        // This means they passed physical exam but need to check donation timing
                        if ($days_since_donation >= 56) {
                            $status_message = "Approved & ready";
                            $alert_type = "success";
                            $can_donate_today = true;
                            $suggestion = "Donor passed medical screening and is ready to donate.";
                        } else {
                            $status_message = "Approved but waiting";
                            $alert_type = "warning";
                            $weeks_remaining = ceil((56 - $days_since_donation) / 7);
                            $days_remaining = 56 - $days_since_donation;
                            $reason = "Passed medical exam but recently donated";
                            $suggestion = "Donor recently donated. Not eligible to donate for $days_remaining day(s).";
                        }
                        break;
                        
                    case 'deferred':
                    case 'temporary deferred':
                    case 'temporarily deferred':
                        $status_message = "Temporarily deferred";
                        $alert_type = "warning";
                        $reason = $disapproval_reason ?: "Medical deferral";
                        
                        if ($end_date && strtotime($end_date) <= time()) {
                            $suggestion = "Deferral period ended. Contact medical staff for re-evaluation.";
                            $can_donate_today = false; // Still needs medical clearance
                        } else if ($end_date) {
                            $days_remaining = ceil((strtotime($end_date) - time()) / (60 * 60 * 24));
                            $suggestion = "Deferral ends in $days_remaining day(s). Medical re-evaluation required.";
                        } else {
                            $suggestion = "Contact medical staff for deferral review.";
                        }
                        break;
                        
                    case 'refused':
                        $status_message = "Donation refused";
                        $alert_type = "danger";
                        $reason = $disapproval_reason ?: "Medical refusal";
                        $suggestion = "Contact medical director before proceeding.";
                        break;
                        
                    default:
                        $status_message = "Registered donor";
                        $alert_type = "info";
                        $suggestion = "Review complete donor history before proceeding.";
                        $reason = "Unknown status - requires review";
                }
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'duplicate_found' => true,
            'message' => 'Duplicate donor found',
            'data' => [
                'donor_id' => $duplicate_data['donor_id'],
                'prc_donor_number' => $duplicate_data['prc_donor_number'] ?? null,
                'full_name' => $full_name,
                'birthdate' => $duplicate_data['birthdate'],
                'age' => $duplicate_data['age'],
                'sex' => $duplicate_data['sex'],
                'mobile' => $duplicate_data['mobile'],
                'email' => $duplicate_data['email'],
                'registration_date' => $duplicate_data['submitted_at'],
                'time_description' => $time_description,
                'eligibility_status' => $duplicate_data['latest_eligibility']['status'] ?? 'unknown',
                'status_message' => $status_message,
                'alert_type' => $alert_type,
                'reason' => $reason,
                'suggestion' => $suggestion,
                'can_donate_today' => $can_donate_today,
                'total_matches' => count($processed_donors),
                'has_eligibility_history' => $has_eligibility,
                'donation_stage' => $donation_stage ?? null,
                // New donation statistics
                'total_donations' => $total_donations,
                'total_eligibility_records' => $total_eligibility_records,
                'temporary_deferred' => $temporary_deferred,
                'temporary_deferred_text' => $temporary_deferred_text,
                'temporary_deferred_days_remaining' => $temporary_deferred_days_remaining,
                'deferral_end_date' => $duplicate_data['latest_eligibility']['end_date'] ?? null,
                'latest_donation_date' => $duplicate_data['latest_eligibility']['created_at'] ?? null
            ]
        ]);
    } else {
        // This should not happen since we already checked for matching donors above
        // But keeping as fallback
        echo json_encode([
            'status' => 'success',
            'duplicate_found' => false,
            'message' => 'No existing donor found',
            'data' => null
        ]);
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Duplicate donor check error (admin): " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'duplicate_found' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}
?>
