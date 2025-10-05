<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include_once '../../assets/conn/db_conn.php';

// OPTIMIZATION: Include shared utilities first to prevent function redeclaration
include_once __DIR__ . '/shared_utilities.php';

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
    
    $declinedDonations = [];
    
    // STEP 1: Get declined/deferred donors from eligibility table (primary source)
    $limit = isset($GLOBALS['DONATION_LIMIT']) ? intval($GLOBALS['DONATION_LIMIT']) : 100;
    $offset = isset($GLOBALS['DONATION_OFFSET']) ? intval($GLOBALS['DONATION_OFFSET']) : 0;
    $eligibilityResponse = supabaseRequest("eligibility?or=(status.eq.declined,status.eq.deferred,status.eq.refused,status.eq.ineligible)&select=eligibility_id,donor_id,status,disapproval_reason,created_at&order=created_at.desc&limit={$limit}&offset={$offset}");
    
    if (isset($eligibilityResponse['data']) && is_array($eligibilityResponse['data'])) {
        debug_log("Found " . count($eligibilityResponse['data']) . " declined/deferred eligibility records");
        
        // Extract all donor IDs for batch processing
        $donorIds = array_column($eligibilityResponse['data'], 'donor_id');
        $donorIds = array_filter($donorIds); // Remove empty values
        
        if (!empty($donorIds)) {
            // Batch API call for donor information
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
            
            // Process eligibility records
            foreach ($eligibilityResponse['data'] as $eligibility) {
                $donorId = $eligibility['donor_id'] ?? null;
                if (!$donorId || !isset($donorLookup[$donorId])) {
                    debug_log("Skipping eligibility record with no donor_id or missing donor info: " . $donorId);
                    continue;
                }
                
                $donor = $donorLookup[$donorId];
                $status = strtolower($eligibility['status'] ?? '');
                $disapprovalReason = $eligibility['disapproval_reason'] ?? '';
                
                // Determine status text based on eligibility status
                $statusText = 'Declined';
                if (in_array($status, ['deferred', 'ineligible'])) {
                    $statusText = 'Deferred';
                } elseif ($status === 'refused') {
                    $statusText = 'Declined';
                }
                
                // OPTIMIZATION: Create standardized record with all required fields for UI
                $declinedDonations[] = [
                    'eligibility_id' => $eligibility['eligibility_id'],
                    'donor_id' => $donorId,
                    'surname' => $donor['surname'] ?? '',
                    'first_name' => $donor['first_name'] ?? '',
                    'middle_name' => $donor['middle_name'] ?? '',
                    'donor_type' => 'Returning', // Declined donors are typically returning
                    'donor_number' => $donor['prc_donor_number'] ?? $donorId,
                    'registration_source' => 'PRC System',
                    'registration_channel' => 'PRC System', // Add alias for compatibility
                    'age' => calculateAge($donor['birthdate'] ?? ''), // Add calculated age
                    'sex' => $donor['sex'] ?? '',
                    'birthdate' => $donor['birthdate'] ?? '', // Add missing field
                    'rejection_source' => 'Eligibility',
                    'rejection_reason' => $disapprovalReason,
                    'rejection_date' => date('M d, Y', strtotime($eligibility['created_at'] ?? 'now')),
                    'remarks_status' => ucfirst($status),
                    'status' => $status,
                    'status_text' => $statusText,
                    'status_class' => getStatusClass($statusText), // Add pre-calculated CSS class
                    'date_submitted' => date('M d, Y', strtotime($eligibility['created_at'] ?? 'now')), // Add for compatibility
                    'sort_ts' => strtotime($eligibility['created_at'] ?? 'now') // Add sorting timestamp
                ];
                
                debug_log("Added declined/deferred donor from eligibility: " . ($donor['surname'] ?? 'Unknown') . ", " . ($donor['first_name'] ?? 'Unknown') . " - " . $statusText);
            }
        }
    }
    
    // STEP 2: Get declined donors from screening_form table (disapproval_reason is not null) - backup
    $screeningResponse = supabaseRequest("screening_form?disapproval_reason=not.is.null&select=screening_id,donor_form_id,disapproval_reason,created_at&order=created_at.desc&limit={$limit}&offset={$offset}");
    
    if (isset($screeningResponse['data']) && is_array($screeningResponse['data'])) {
        debug_log("Found " . count($screeningResponse['data']) . " declined screening records");
        
        // Extract all donor IDs for batch processing
        $donorIds = array_column($screeningResponse['data'], 'donor_form_id');
        $donorIds = array_filter($donorIds); // Remove empty values
        
        if (!empty($donorIds)) {
            // Batch API call for donor information
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
            
            // Process declined screening records
            foreach ($screeningResponse['data'] as $screening) {
                $donorId = $screening['donor_form_id'] ?? null;
                if (!$donorId || !isset($donorLookup[$donorId])) {
                    debug_log("Skipping screening record with no donor_id or missing donor info: " . $donorId);
                    continue;
                }
                
                $donor = $donorLookup[$donorId];
                $disapprovalReason = $screening['disapproval_reason'] ?? '';
                
                // Skip if disapproval_reason is null or empty
                if (empty($disapprovalReason)) {
                    debug_log("Skipping screening record with empty disapproval_reason for donor_id: " . $donorId);
                    continue;
                }
                
                // OPTIMIZATION: Create standardized record for declined screening
                $declinedDonations[] = [
                    'eligibility_id' => 'declined_screening_' . $screening['screening_id'],
                    'donor_id' => $donorId,
                    'surname' => $donor['surname'] ?? '',
                    'first_name' => $donor['first_name'] ?? '',
                    'middle_name' => $donor['middle_name'] ?? '',
                    'donor_type' => 'Returning', // Declined donors are typically returning
                    'donor_number' => $donor['prc_donor_number'] ?? $donorId,
                    'registration_source' => 'PRC System',
                    'registration_channel' => 'PRC System', // Add alias for compatibility
                    'age' => calculateAge($donor['birthdate'] ?? ''), // Add calculated age
                    'sex' => $donor['sex'] ?? '',
                    'birthdate' => $donor['birthdate'] ?? '', // Add missing field
                    'rejection_source' => 'Screening',
                    'rejection_reason' => $disapprovalReason,
                    'rejection_date' => date('M d, Y', strtotime($screening['created_at'] ?? 'now')),
                    'remarks_status' => 'Declined',
                    'status' => 'declined',
                    'status_text' => 'Declined',
                    'status_class' => 'bg-danger', // Add pre-calculated CSS class
                    'date_submitted' => date('M d, Y', strtotime($screening['created_at'] ?? 'now')), // Add for compatibility
                    'sort_ts' => strtotime($screening['created_at'] ?? 'now') // Add sorting timestamp
                ];
                
                debug_log("Added declined donor from screening: " . ($donor['surname'] ?? 'Unknown') . ", " . ($donor['first_name'] ?? 'Unknown'));
            }
        }
    }
    
    // STEP 2: Get deferred donors from physical_examination table (Temporarily Deferred/Permanently Deferred)
    $physicalExamResponse = supabaseRequest("physical_examination?or=(remarks.eq.Temporarily%20Deferred,remarks.eq.Permanently%20Deferred)&select=physical_exam_id,donor_id,remarks,reason,created_at&order=created_at.desc&limit={$limit}&offset={$offset}");
    
    if (isset($physicalExamResponse['data']) && is_array($physicalExamResponse['data'])) {
        debug_log("Found " . count($physicalExamResponse['data']) . " deferred physical examination records");
        
        // Extract all donor IDs for batch processing
        $donorIds = array_column($physicalExamResponse['data'], 'donor_id');
        $donorIds = array_filter($donorIds); // Remove empty values
        
        if (!empty($donorIds)) {
            // Batch API call for donor information
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
            
            // Process deferred records
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
                
                // Use reason if available, otherwise use remarks
                $rejectionReason = !empty($exam['reason']) ? 
                    $exam['reason'] : $remarks;
                
                // Normalize deferred status to just "Deferred" regardless of type
                $statusText = 'Deferred';
                
                // OPTIMIZATION: Create standardized record for deferred physical examination
                $declinedDonations[] = [
                    'eligibility_id' => 'deferred_' . $exam['physical_exam_id'],
                    'donor_id' => $donorId,
                    'surname' => $donor['surname'] ?? '',
                    'first_name' => $donor['first_name'] ?? '',
                    'middle_name' => $donor['middle_name'] ?? '',
                    'donor_type' => 'Returning', // Deferred donors are typically returning
                    'donor_number' => $donor['prc_donor_number'] ?? $donorId,
                    'registration_source' => 'PRC System',
                    'registration_channel' => 'PRC System', // Add alias for compatibility
                    'age' => calculateAge($donor['birthdate'] ?? ''), // Add calculated age
                    'sex' => $donor['sex'] ?? '',
                    'birthdate' => $donor['birthdate'] ?? '', // Add missing field
                    'rejection_source' => 'Physical Examination',
                    'rejection_reason' => $rejectionReason,
                    'rejection_date' => date('M d, Y', strtotime($exam['created_at'] ?? 'now')),
                    'remarks_status' => $remarks,
                    'status' => 'deferred',
                    'status_text' => $statusText,
                    'status_class' => 'bg-warning', // Add pre-calculated CSS class
                    'date_submitted' => date('M d, Y', strtotime($exam['created_at'] ?? 'now')), // Add for compatibility
                    'sort_ts' => strtotime($exam['created_at'] ?? 'now') // Add sorting timestamp
                ];
                
                debug_log("Added deferred donor: " . ($donor['surname'] ?? 'Unknown') . ", " . ($donor['first_name'] ?? 'Unknown') . " - " . $statusText);
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

// OPTIMIZATION: Shared utilities already included at the top of the file