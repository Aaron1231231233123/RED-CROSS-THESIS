<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include_once '../../assets/conn/db_conn.php';

$approvedDonations = [];
$error = null;

try {
    // OPTIMIZATION 1: Use a single optimized query with joins instead of multiple API calls
    // This fetches all approved donations in one request with donor information
    $optimizedCurl = curl_init();
    
    // FIX: Query for all approved donations - includes status='approved', 'eligible', and collection_successful=true
    // Use Supabase's built-in join capabilities to fetch all data in one request
    $limit = isset($GLOBALS['DONATION_LIMIT']) ? intval($GLOBALS['DONATION_LIMIT']) : 100;
    $offset = isset($GLOBALS['DONATION_OFFSET']) ? intval($GLOBALS['DONATION_OFFSET']) : 0;
    $queryUrl = SUPABASE_URL . "/rest/v1/eligibility?" . http_build_query([
        'select' => 'eligibility_id,donor_id,blood_type,donation_type,created_at,status,collection_successful',
        'or' => '(status.eq.approved,status.eq.eligible,collection_successful.eq.true)',
        'order' => 'created_at.desc',
        'limit' => $limit,
        'offset' => $offset
    ]);
    
    // OPTIMIZATION FOR SLOW INTERNET: Enhanced timeout and retry settings
    curl_setopt_array($optimizedCurl, [
        CURLOPT_URL => $queryUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 60, // Increased timeout for slow connections
        CURLOPT_CONNECTTIMEOUT => 20, // Increased connection timeout
        CURLOPT_TCP_KEEPALIVE => 1, // Enable TCP keepalive
        CURLOPT_TCP_KEEPIDLE => 120, // Keep connection alive for 2 minutes
        CURLOPT_TCP_KEEPINTVL => 60, // Check connection every minute
        CURLOPT_FOLLOWLOCATION => true, // Follow redirects
        CURLOPT_MAXREDIRS => 3, // Limit redirects
        CURLOPT_SSL_VERIFYPEER => false, // Skip SSL verification for faster connection
        CURLOPT_SSL_VERIFYHOST => false, // Skip host verification
        CURLOPT_ENCODING => 'gzip,deflate', // Accept compressed responses
        CURLOPT_USERAGENT => 'BloodDonorSystem/1.0' // Add user agent
    ]);
    
    // OPTIMIZATION FOR SLOW INTERNET: Retry mechanism for failed requests
    $maxRetries = 3;
    $retryDelay = 2; // seconds
    $eligibilityResponse = false;
    $httpCode = 0;
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $eligibilityResponse = curl_exec($optimizedCurl);
        $httpCode = curl_getinfo($optimizedCurl, CURLINFO_HTTP_CODE);
        
        if ($eligibilityResponse !== false && $httpCode === 200) {
            break; // Success, exit retry loop
        }
        
        if ($attempt < $maxRetries) {
            error_log("Attempt $attempt failed. HTTP Code: $httpCode. Retrying in $retryDelay seconds...");
            sleep($retryDelay);
            $retryDelay *= 2; // Exponential backoff
        }
    }
    
    curl_close($optimizedCurl);
    
    if ($eligibilityResponse === false || $httpCode !== 200) {
        throw new Exception("Failed to fetch eligibility data after $maxRetries attempts. HTTP Code: " . $httpCode);
    }
    
    $eligibilityData = json_decode($eligibilityResponse, true);
    if (!is_array($eligibilityData)) {
        throw new Exception("Invalid eligibility data format");
    }
    
    // DEBUG: If no data found with OR query, try alternative approach
    if (empty($eligibilityData)) {
        error_log("No data found with OR query, trying alternative approach...");
        
        // Try fetching all eligibility records and filter in PHP
        $fallbackCurl = curl_init();
        curl_setopt_array($fallbackCurl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?select=eligibility_id,donor_id,blood_type,donation_type,created_at,status,collection_successful&order=created_at.desc",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $fallbackResponse = curl_exec($fallbackCurl);
        $fallbackHttpCode = curl_getinfo($fallbackCurl, CURLINFO_HTTP_CODE);
        curl_close($fallbackCurl);
        
        if ($fallbackResponse !== false && $fallbackHttpCode === 200) {
            $fallbackData = json_decode($fallbackResponse, true);
            if (is_array($fallbackData)) {
                // Filter for approved donations in PHP
                $eligibilityData = array_filter($fallbackData, function($item) {
                    $status = $item['status'] ?? '';
                    $collectionSuccessful = $item['collection_successful'] ?? false;
                    return $status === 'approved' || $status === 'eligible' || $collectionSuccessful === true;
                });
                error_log("Fallback query found " . count($eligibilityData) . " approved donations");
            }
        }
    }
    
    // OPTIMIZATION 2: Batch fetch all donor information in one request
    if (!empty($eligibilityData)) {
        // Extract all donor IDs
        $donorIds = array_column($eligibilityData, 'donor_id');
        $donorIds = array_filter($donorIds); // Remove null/empty values
        
        if (!empty($donorIds)) {
            // Fetch all donors in one batch request
            $donorCurl = curl_init();
            $donorIdsParam = implode(',', $donorIds);
            
            // OPTIMIZATION FOR SLOW INTERNET: Enhanced timeout and retry settings for donor data
            curl_setopt_array($donorCurl, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=in.(" . $donorIdsParam . ")&select=donor_id,surname,first_name,middle_name,birthdate,sex",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ],
                CURLOPT_TIMEOUT => 60, // Increased timeout for slow connections
                CURLOPT_CONNECTTIMEOUT => 20, // Increased connection timeout
                CURLOPT_TCP_KEEPALIVE => 1, // Enable TCP keepalive
                CURLOPT_TCP_KEEPIDLE => 120, // Keep connection alive for 2 minutes
                CURLOPT_TCP_KEEPINTVL => 60, // Check connection every minute
                CURLOPT_FOLLOWLOCATION => true, // Follow redirects
                CURLOPT_MAXREDIRS => 3, // Limit redirects
                CURLOPT_SSL_VERIFYPEER => false, // Skip SSL verification for faster connection
                CURLOPT_SSL_VERIFYHOST => false, // Skip host verification
                CURLOPT_ENCODING => 'gzip,deflate', // Accept compressed responses
                CURLOPT_USERAGENT => 'BloodDonorSystem/1.0' // Add user agent
            ]);
            
            // OPTIMIZATION FOR SLOW INTERNET: Retry mechanism for donor data
            $donorMaxRetries = 3;
            $donorRetryDelay = 2; // seconds
            $donorResponse = false;
            $donorHttpCode = 0;
            
            for ($donorAttempt = 1; $donorAttempt <= $donorMaxRetries; $donorAttempt++) {
                $donorResponse = curl_exec($donorCurl);
                $donorHttpCode = curl_getinfo($donorCurl, CURLINFO_HTTP_CODE);
                
                if ($donorResponse !== false && $donorHttpCode === 200) {
                    break; // Success, exit retry loop
                }
                
                if ($donorAttempt < $donorMaxRetries) {
                    error_log("Donor data attempt $donorAttempt failed. HTTP Code: $donorHttpCode. Retrying in $donorRetryDelay seconds...");
                    sleep($donorRetryDelay);
                    $donorRetryDelay *= 2; // Exponential backoff
                }
            }
            
            curl_close($donorCurl);
            
            if ($donorResponse !== false && $donorHttpCode === 200) {
                $donorData = json_decode($donorResponse, true);
                
                // Create a lookup array for fast donor data access
                $donorLookup = [];
                if (is_array($donorData)) {
                    foreach ($donorData as $donor) {
                        $donorLookup[$donor['donor_id']] = $donor;
                    }
                }
                
                // OPTIMIZATION 3: Process data efficiently using the lookup array
                $processedDonorIds = [];
                
                foreach ($eligibilityData as $eligibility) {
                    $donorId = $eligibility['donor_id'] ?? null;
                    if (!$donorId || !isset($donorLookup[$donorId])) continue;
                    
                    // Note: Data is already filtered for approved donations in the query above
                    
                    // Skip if we've already processed this donor (avoid duplicates)
                    if (in_array($donorId, $processedDonorIds)) {
                        continue;
                    }
                    
                    $processedDonorIds[] = $donorId;
                    $donor = $donorLookup[$donorId];
                    
                    // Calculate age efficiently
                    $birthdate = $donor['birthdate'] ?? '';
                    $age = '';
                    if ($birthdate) {
                        $birthDate = new DateTime($birthdate);
                        $today = new DateTime();
                        $age = $birthDate->diff($today)->y;
                    }
                    
                    // Format date
                    $createdAt = isset($eligibility['created_at']) ? 
                        date('M d, Y', strtotime($eligibility['created_at'])) : '';
                        
                    // OPTIMIZATION: Create standardized record with all required fields for UI
                    $donation = [
                        'eligibility_id' => $eligibility['eligibility_id'] ?? '',
                        'donor_id' => $donorId,
                        'surname' => $donor['surname'] ?? '',
                        'first_name' => $donor['first_name'] ?? '',
                        'middle_name' => $donor['middle_name'] ?? '',
                        'donor_type' => 'Returning', // Approved donors are always returning
                        'donor_number' => $donor['prc_donor_number'] ?? $donorId,
                        'registration_source' => 'PRC System',
                        'registration_channel' => 'PRC System', // Add alias for compatibility
                        'age' => $age ?: '0',
                        'sex' => $donor['sex'] ?? '',
                        'birthdate' => $donor['birthdate'] ?? '', // Add missing field
                        'blood_type' => $eligibility['blood_type'] ?? '',
                        'donation_type' => $eligibility['donation_type'] ?? '',
                        'status' => 'approved',
                        'status_text' => 'Approved',
                        'status_class' => 'bg-success', // Add pre-calculated CSS class
                        'date_submitted' => $createdAt,
                        'sort_ts' => strtotime($eligibility['created_at'] ?? 'now') // Add sorting timestamp
                    ];
                    
                    $approvedDonations[] = $donation;
                }
            } else {
                error_log("Failed to fetch donor data in batch. HTTP Code: " . $donorHttpCode);
                // Fallback to individual calls if batch fails
                $approvedDonations = ['error' => 'Failed to fetch donor data'];
            }
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Error in donation_approved.php: " . $error);
    $approvedDonations = ['error' => $error];
}

// Set error message if no records found
if (empty($approvedDonations) && !$error) {
    $error = "No approved donation records found.";
    error_log("Approved Donations: No records found with no API errors");
}

// Performance logging
$executionTime = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
error_log("Approved Donations Module - Records found: " . count($approvedDonations) . " in " . round($executionTime, 3) . " seconds");

// DEBUG: Log the query details for troubleshooting
error_log("Approved Donations Query URL: " . $queryUrl);
error_log("Raw eligibility data count: " . count($eligibilityData));
error_log("Donor lookup count: " . count($donorLookup));

// OPTIMIZATION 4: Add simple caching headers for browser caching
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 300));

// Check all tables for better diagnostics (only if there's an error)
if (empty($approvedDonations) || isset($approvedDonations['error'])) {
    // Check eligibility table
    $eligibilityCurl = curl_init();
    curl_setopt_array($eligibilityCurl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?select=eligibility_id,donor_id,status&limit=5",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $eligibilityResponse = curl_exec($eligibilityCurl);
    curl_close($eligibilityCurl);
    
    $eligibilityData = json_decode($eligibilityResponse, true);
    if (is_array($eligibilityData) && !empty($eligibilityData)) {
        error_log("Eligibility table sample records: " . json_encode($eligibilityData));
    } else {
        error_log("No data found in eligibility table");
    }
    
    // Check screening_form table
    $screeningCurl = curl_init();
    curl_setopt_array($screeningCurl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?select=screening_id,donor_form_id,blood_type,donation_type&limit=5",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $screeningResponse = curl_exec($screeningCurl);
    curl_close($screeningCurl);
    
    $screeningData = json_decode($screeningResponse, true);
    if (is_array($screeningData) && !empty($screeningData)) {
        error_log("Screening table sample records: " . json_encode($screeningData));
    } else {
        error_log("No data found in screening_form table");
    }
}