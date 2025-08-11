<?php
// check_duplicate_donor.php
// API endpoint to check for duplicate donors based on personal information

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set content type to JSON
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
    // Supabase Configuration - Direct connection
    define("SUPABASE_URL", "https://nwakbxwglhxcpunrzstf.supabase.co");
    define("SUPABASE_API_KEY", "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im53YWtieHdnbGh4Y3B1bnJ6c3RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDIyODA1NzIsImV4cCI6MjA1Nzg1NjU3Mn0.y4CIbDT2UQf2ieJTQukuJRRzspSZPehSgNKivBwpvc4");
    
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
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        throw new Exception("Invalid date format. Expected YYYY-MM-DD");
    }
    
    // Build the query to find matching donors who have eligibility records
    // Using Supabase PostgREST API syntax
    $query_conditions = [];
    
    // Exact match for surname, first_name, and birthdate
    $query_conditions[] = "surname=eq." . urlencode($surname);
    $query_conditions[] = "first_name=eq." . urlencode($first_name);
    $query_conditions[] = "birthdate=eq." . urlencode($birthdate);
    
    // Option 1: More efficient - Don't filter by middle name in SQL
    // We'll check middle name matching in PHP after getting results
    // This is more flexible and handles edge cases better
    
    $query_string = implode('&', $query_conditions);
    
    // First, get matching donors from donor_form table (using correct column names)
    $donors_url = SUPABASE_URL . "/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate,age,sex,mobile,email,submitted_at&" . $query_string;
    
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
    
    // Now check which of these donors have eligibility records
    $donors_with_eligibility = [];
    
    foreach ($matching_donors as $donor) {
        $donor_id = $donor['donor_id'];
        
        // Check if this donor has any eligibility records (include disapproval_reason)
        $eligibility_url = SUPABASE_URL . "/rest/v1/eligibility?select=eligibility_id,status,disapproval_reason,created_at,start_date,end_date&donor_id=eq." . $donor_id . "&order=created_at.desc&limit=1";
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $eligibility_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
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
            $eligibility_records = json_decode($eligibility_response, true);
            
            if (is_array($eligibility_records) && !empty($eligibility_records)) {
                $latest_eligibility = $eligibility_records[0];
                
                // Add eligibility information to donor data
                $donor['latest_eligibility'] = $latest_eligibility;
                $donors_with_eligibility[] = $donor;
            }
        }
    }
    
    // Prepare response
    if (!empty($donors_with_eligibility)) {
        // Found duplicate donor(s) with eligibility records
        $duplicate_data = $donors_with_eligibility[0]; // Get the first (most recent) match
        
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
        
        // Determine status message with intelligent suggestions
        $status_message = "Active donor";
        $alert_type = "warning";
        $suggestion = "Please verify donor information before proceeding.";
        $reason = "";
        $can_donate_today = false;
        
        if (isset($duplicate_data['latest_eligibility']['status'])) {
            $status = strtolower($duplicate_data['latest_eligibility']['status']);
            $disapproval_reason = $duplicate_data['latest_eligibility']['disapproval_reason'] ?? '';
            $end_date = $duplicate_data['latest_eligibility']['end_date'] ?? null;
            
            // Calculate days since last donation
            $days_since_donation = 0;
            if ($duplicate_data['latest_eligibility']['created_at']) {
                $last_donation = strtotime($duplicate_data['latest_eligibility']['created_at']);
                $days_since_donation = floor((time() - $last_donation) / (60 * 60 * 24));
            }
            
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
                        $suggestion = "Must wait $days_remaining more day(s) ($weeks_remaining week(s)) before next donation.";
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
                        $suggestion = "Wait $days_remaining more day(s) ($weeks_remaining week(s)) before next donation.";
                    }
                    break;
                    
                case 'deferred':
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
        
        echo json_encode([
            'status' => 'success',
            'duplicate_found' => true,
            'message' => 'Duplicate donor found',
            'data' => [
                'donor_id' => $duplicate_data['donor_id'],
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
                'total_matches' => count($donors_with_eligibility)
            ]
        ]);
    } else {
        // No duplicates with eligibility records found
        echo json_encode([
            'status' => 'success',
            'duplicate_found' => false,
            'message' => 'No existing donor with donation history found',
            'data' => null
        ]);
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Duplicate donor check error: " . $e->getMessage());
    
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