<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include_once '../../assets/conn/db_conn.php';

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/optimized_functions.php';

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// Array to hold donor data
$pendingDonations = [];
$error = null;

try {
    // OPTIMIZATION 1: Use enhanced API function with retry mechanism for eligibility data
    // Get all eligibility records to filter out donors with any eligibility data
    $donorsWithEligibility = [];
    
    $eligibilityResponse = supabaseRequest("eligibility?select=donor_id,created_at&order=created_at.desc");
    
    if (isset($eligibilityResponse['data']) && is_array($eligibilityResponse['data'])) {
        // Track processed donor IDs to avoid duplicates
        $processedDonorIds = [];
        
        foreach ($eligibilityResponse['data'] as $eligibility) {
            if (isset($eligibility['donor_id'])) {
                $donorId = $eligibility['donor_id'];
                
                // Only add donor ID if we haven't processed it yet
                if (!in_array($donorId, $processedDonorIds)) {
                    $donorsWithEligibility[] = $donorId;
                    $processedDonorIds[] = $donorId;
                }
            }
        }
    }
    
    // OPTIMIZATION 2: Use enhanced API function for donor form data
    // Direct connection to get donor_form data with optimized settings
    $donorResponse = supabaseRequest("donor_form?limit=100&order=submitted_at.desc");
    
    if (isset($donorResponse['data']) && is_array($donorResponse['data'])) {
        // Log the first donor record to see all available fields
        if (!empty($donorResponse['data'])) {
            error_log("First donor record fields: " . print_r(array_keys($donorResponse['data'][0]), true));
        }
        
        // Process each donor
        foreach ($donorResponse['data'] as $donor) {
            // Skip donors who have any eligibility record
            if (in_array($donor['donor_id'], $donorsWithEligibility)) {
                continue;
            }
            
            // Try multiple possible date fields
            $dateSubmitted = '';
            
            // Check each possible date field and use the first one available
            if (!empty($donor['created_at'])) {
                $dateSubmitted = date('M d, Y', strtotime($donor['created_at']));
            } elseif (!empty($donor['submitted_at'])) {
                $dateSubmitted = date('M d, Y', strtotime($donor['submitted_at']));
            } elseif (!empty($donor['date_submitted'])) {
                $dateSubmitted = date('M d, Y', strtotime($donor['date_submitted']));
            } else {
                // If no date field is found, use today's date
                $dateSubmitted = date('M d, Y');
            }
            
            // Create a simplified record with ONLY the required fields
            $pendingDonations[] = [
                'donor_id' => $donor['donor_id'] ?? '',
                'surname' => $donor['surname'] ?? '',
                'first_name' => $donor['first_name'] ?? '',
                'birthdate' => $donor['birthdate'] ?? '',
                'sex' => $donor['sex'] ?? '',
                'date_submitted' => $dateSubmitted,
                'eligibility_id' => 'pending_' . ($donor['donor_id'] ?? '0'),
                'registration_source' => $donor['registration_channel'] ?? 'PRC System' // Default to PRC System if not specified
            ];
        }
    } else {
        // Handle API errors
        if (isset($donorResponse['error'])) {
            $error = "API Error: " . $donorResponse['error'];
        } else {
            $error = "API Error: HTTP Code " . ($donorResponse['code'] ?? 'Unknown');
        }
    }
    
} catch (Exception $e) {
    $error = "Exception: " . $e->getMessage();
}

// Set error message if no records found
if (empty($pendingDonations) && !$error) {
    $error = "No pending donors found in the donor_form table.";
}

// OPTIMIZATION: Performance logging and caching
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
addPerformanceHeaders($executionTime, count($pendingDonations), "Pending Donations Module");

// Debug: Show exact values being used
error_log("SUPABASE_URL: " . SUPABASE_URL);
error_log("API Key Length: " . strlen(SUPABASE_API_KEY));
error_log("Filtered out " . count($donorsWithEligibility) . " donors with eligibility data");
error_log("Found " . count($pendingDonations) . " pending donors");
?> 