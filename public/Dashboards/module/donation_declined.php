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

// Array to hold declined donations
$declinedDonations = [];
$error = null;

// Debug flag - set to true for additional logging
$DEBUG = true;

// Log if debug is enabled
function debug_log($message) {
    global $DEBUG;
    if ($DEBUG) {
        error_log("[DEBUG] " . $message);
    }
}

try {
    debug_log("Starting donation_declined.php");
    
    // OPTIMIZATION 1: Use enhanced API function with retry mechanism for physical examination data
    // Get all physical examination records with non-accepted remarks in one optimized query
    $physicalExamResponse = supabaseRequest("physical_examination?or=(remarks.eq.Temporarily%20Deferred,remarks.eq.Permanently%20Deferred,remarks.eq.Refused)&select=physical_exam_id,donor_id,remarks,disapproval_reason,reason,created_at&order=created_at.desc");
    
    $declinedDonations = [];
    
    if (isset($physicalExamResponse['data']) && is_array($physicalExamResponse['data'])) {
        debug_log("Found " . count($physicalExamResponse['data']) . " declined physical examination records");
        
        // Extract all donor IDs for batch processing
        $donorIds = array_column($physicalExamResponse['data'], 'donor_id');
        $donorIds = array_filter($donorIds); // Remove empty values
        
        if (!empty($donorIds)) {
            // OPTIMIZATION 2: Batch API call for donor information
            $donorIdsParam = implode(',', $donorIds);
            $donorResponse = supabaseRequest("donor_form?donor_id=in.(" . $donorIdsParam . ")&select=donor_id,surname,first_name,middle_name,birthdate,age,sex");
            
            // Create lookup array for donor data
            $donorLookup = [];
            if (isset($donorResponse['data']) && is_array($donorResponse['data'])) {
                foreach ($donorResponse['data'] as $donor) {
                    $donorLookup[$donor['donor_id']] = $donor;
                }
                debug_log("Created donor lookup with " . count($donorLookup) . " donors");
            }
            
            // Process declined records
            foreach ($physicalExamResponse['data'] as $exam) {
                $donorId = $exam['donor_id'] ?? null;
                if (!$donorId || !isset($donorLookup[$donorId])) {
                    debug_log("Skipping record with no donor_id or missing donor info: " . $donorId);
                    continue;
                }
                
                $donor = $donorLookup[$donorId];
                $remarks = $exam['remarks'] ?? '';
                
                // Skip if remarks is null or empty
                if (empty($remarks)) {
                    debug_log("Skipping record with empty remarks for donor_id: " . $donorId);
                    continue;
                }
                
                // Use disapproval_reason if available, otherwise fall back to reason field
                $rejectionReason = !empty($exam['disapproval_reason']) ? 
                    $exam['disapproval_reason'] : ($exam['reason'] ?? 'Unspecified reason');
                
                // Create record with all required fields
                $declinedDonations[] = [
                    'eligibility_id' => 'declined_' . $exam['physical_exam_id'],
                    'donor_id' => $donorId,
                    'surname' => $donor['surname'] ?? '',
                    'first_name' => $donor['first_name'] ?? '',
                    'middle_name' => $donor['middle_name'] ?? '',
                    'rejection_source' => $remarks,
                    'rejection_reason' => $rejectionReason,
                    'rejection_date' => date('M d, Y', strtotime($exam['created_at'] ?? 'now')),
                    'remarks_status' => $remarks,
                    'status' => 'declined'
                ];
                
                debug_log("Added declined donor: " . ($donor['surname'] ?? 'Unknown') . ", " . ($donor['first_name'] ?? 'Unknown'));
            }
        }
    }
    
    // OPTIMIZATION 3: Fallback to eligibility table if no physical examination records found
    if (empty($declinedDonations)) {
        debug_log("No records found in physical_examination, trying eligibility table as fallback");
        
        // Get eligibility records with status 'declined'
        $eligibilityResponse = supabaseRequest("eligibility?status=eq.declined&select=eligibility_id,donor_id,rejection_reason,created_at&order=created_at.desc");
        
        if (isset($eligibilityResponse['data']) && is_array($eligibilityResponse['data'])) {
            debug_log("Found " . count($eligibilityResponse['data']) . " declined eligibility records");
            
            // Extract all donor IDs for batch processing
            $eligibilityDonorIds = array_column($eligibilityResponse['data'], 'donor_id');
            $eligibilityDonorIds = array_filter($eligibilityDonorIds); // Remove empty values
            
            if (!empty($eligibilityDonorIds)) {
                // Batch API call for donor information
                $eligibilityDonorIdsParam = implode(',', $eligibilityDonorIds);
                $eligibilityDonorResponse = supabaseRequest("donor_form?donor_id=in.(" . $eligibilityDonorIdsParam . ")&select=donor_id,surname,first_name,middle_name,birthdate,age,sex");
                
                // Create lookup array for donor data
                $eligibilityDonorLookup = [];
                if (isset($eligibilityDonorResponse['data']) && is_array($eligibilityDonorResponse['data'])) {
                    foreach ($eligibilityDonorResponse['data'] as $donor) {
                        $eligibilityDonorLookup[$donor['donor_id']] = $donor;
                    }
                }
                
                // Process each eligibility record
                foreach ($eligibilityResponse['data'] as $eligibility) {
                    $donorId = $eligibility['donor_id'] ?? null;
                    
                    if (empty($donorId) || !isset($eligibilityDonorLookup[$donorId])) {
                        debug_log("Skipping eligibility record with no donor_id or missing donor info: " . $donorId);
                        continue;
                    }
                    
                    $donor = $eligibilityDonorLookup[$donorId];
                    
                    // Format the rejection date
                    $rejectionDate = isset($eligibility['created_at']) ? 
                        date('M d, Y', strtotime($eligibility['created_at'])) : date('M d, Y');
                    
                    // Create basic donation record from eligibility
                    $declinedDonations[] = [
                        'eligibility_id' => $eligibility['eligibility_id'] ?? ('declined_' . $donorId),
                        'donor_id' => $donorId,
                        'surname' => $donor['surname'] ?? '',
                        'first_name' => $donor['first_name'] ?? '',
                        'middle_name' => $donor['middle_name'] ?? '',
                        'rejection_source' => 'Eligibility',
                        'rejection_reason' => $eligibility['rejection_reason'] ?? 'Declined in eligibility check',
                        'rejection_date' => $rejectionDate,
                        'remarks_status' => $eligibility['remarks'] ?? 'Unknown',
                        'status' => 'declined'
                    ];
                    
                    debug_log("Added declined donor from eligibility: " . ($donor['surname'] ?? 'Unknown') . ", " . ($donor['first_name'] ?? 'Unknown'));
                }
            }
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    debug_log("Error in donation_declined.php: " . $error);
}

// Set error message if no records found
if (empty($declinedDonations) && !$error) {
    $error = "No declined donation records found.";
    debug_log("No declined donations found, setting error message");
}

// OPTIMIZATION: Performance logging and caching
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
addPerformanceHeaders($executionTime, count($declinedDonations), "Declined Donations Module");

// Log diagnostic information
debug_log("Declined Donations Module - Records found: " . count($declinedDonations) . " in " . round($executionTime, 3) . " seconds");
?>