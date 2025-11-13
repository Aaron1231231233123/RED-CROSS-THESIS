<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include_once '../../assets/conn/db_conn.php';

// Include shared utilities and optimized functions
include_once __DIR__ . '/shared_utilities.php';
include_once __DIR__ . '/optimized_functions.php';

$approvedDonations = [];
$error = null;
$eligibilityData = [];
$donorLookup = [];

$perfMode = (isset($_GET['perf_mode']) && $_GET['perf_mode'] === 'on');
// Keyset cursor params (perf mode)
$cursorTs = isset($_GET['cursor_ts']) ? $_GET['cursor_ts'] : null;
$cursorId = isset($_GET['cursor_id']) ? $_GET['cursor_id'] : null;
$cursorDir = isset($_GET['cursor_dir']) ? $_GET['cursor_dir'] : 'next'; // next|prev

try {
	$limit = isset($GLOBALS['DONATION_LIMIT']) ? intval($GLOBALS['DONATION_LIMIT']) : 100;
	$offset = isset($GLOBALS['DONATION_OFFSET']) ? intval($GLOBALS['DONATION_OFFSET']) : 0;

    // Base eligibility query with keyset support when perf mode + cursor provided
    $baseParams = [
        'select' => 'eligibility_id,donor_id,blood_type,donation_type,created_at,status,collection_successful',
        'or' => '(status.eq.approved,status.eq.eligible,collection_successful.eq.true)',
        'order' => 'created_at.desc,eligibility_id.desc',
        'limit' => $limit
    ];
    if (!$perfMode || !$cursorTs) {
        // Offset fallback
        $baseParams['offset'] = $offset;
        $queryUrl = SUPABASE_URL . "/rest/v1/eligibility?" . http_build_query($baseParams);
    } else {
        // Keyset: build OR condition based on cursor direction
        $ts = rawurlencode($cursorTs);
        $id = rawurlencode((string)$cursorId);
        if ($cursorDir === 'prev') {
            // newer page (go back): created_at.gt.ts OR (created_at.eq.ts AND eligibility_id.gt.id)
            $or = "(created_at.gt.{$ts},and(created_at.eq.{$ts},eligibility_id.gt.{$id}))";
        } else {
            // older page (go forward): created_at.lt.ts OR (created_at.eq.ts AND eligibility_id.lt.id)
            $or = "(created_at.lt.{$ts},and(created_at.eq.{$ts},eligibility_id.lt.{$id}))";
        }
        // http_build_query URL-encodes values automatically
        $baseParams['or'] = $baseParams['or']; // keep status filter
        $queryUrl = SUPABASE_URL . "/rest/v1/eligibility?" . http_build_query($baseParams) . "&or=" . rawurlencode($or);
    }

	$optimizedCurl = curl_init();
	curl_setopt_array($optimizedCurl, [
		CURLOPT_URL => $queryUrl,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => [
			"apikey: " . SUPABASE_API_KEY,
			"Authorization: Bearer " . SUPABASE_API_KEY,
			"Content-Type: application/json"
		],
		CURLOPT_TIMEOUT => 60,
		CURLOPT_CONNECTTIMEOUT => 20,
		CURLOPT_TCP_KEEPALIVE => 1,
		CURLOPT_TCP_KEEPIDLE => 120,
		CURLOPT_TCP_KEEPINTVL => 60,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 3,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_ENCODING => 'gzip,deflate',
		CURLOPT_USERAGENT => 'BloodDonorSystem/1.0'
	]);

	$maxRetries = 3; $retryDelay = 2; $eligibilityResponse = false; $httpCode = 0;
	for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
		$eligibilityResponse = curl_exec($optimizedCurl);
		$httpCode = curl_getinfo($optimizedCurl, CURLINFO_HTTP_CODE);
		if ($eligibilityResponse !== false && $httpCode === 200) { break; }
		if ($attempt < $maxRetries) { sleep($retryDelay); $retryDelay *= 2; }
	}
	curl_close($optimizedCurl);
	if ($eligibilityResponse === false || $httpCode !== 200) {
		throw new Exception("Failed to fetch eligibility data after $maxRetries attempts. HTTP Code: " . $httpCode);
	}

	$eligibilityData = json_decode($eligibilityResponse, true);
	if (!is_array($eligibilityData)) { throw new Exception("Invalid eligibility data format"); }

    if (!empty($eligibilityData)) {
		$donorIds = array_values(array_filter(array_column($eligibilityData, 'donor_id')));
		$donorLookup = [];
		if (!empty($donorIds)) {
			$donorIdsParam = implode(',', $donorIds);
			$donorCurl = curl_init();
			curl_setopt_array($donorCurl, [
				CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=in.(" . $donorIdsParam . ")&select=donor_id,surname,first_name,middle_name,birthdate,sex",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					"apikey: " . SUPABASE_API_KEY,
					"Authorization: Bearer " . SUPABASE_API_KEY,
					"Content-Type: application/json"
				],
				CURLOPT_TIMEOUT => 60,
				CURLOPT_CONNECTTIMEOUT => 20,
				CURLOPT_TCP_KEEPALIVE => 1,
				CURLOPT_TCP_KEEPIDLE => 120,
				CURLOPT_TCP_KEEPINTVL => 60,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 3,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_ENCODING => 'gzip,deflate',
				CURLOPT_USERAGENT => 'BloodDonorSystem/1.0'
			]);
			$donorResponse = curl_exec($donorCurl);
			$donorHttpCode = curl_getinfo($donorCurl, CURLINFO_HTTP_CODE);
			curl_close($donorCurl);
			if ($donorResponse !== false && $donorHttpCode === 200) {
				$donorData = json_decode($donorResponse, true) ?: [];
				foreach ($donorData as $donor) { $donorLookup[$donor['donor_id']] = $donor; }
			}
		}

        foreach ($eligibilityData as $eligibility) {
			$donorId = $eligibility['donor_id'] ?? null;
			if (!$donorId || !isset($donorLookup[$donorId])) { continue; }
			$donor = $donorLookup[$donorId];
			$birthdate = $donor['birthdate'] ?? '';
			$age = '';
			if ($birthdate) { $birthDate = new DateTime($birthdate); $today = new DateTime(); $age = $birthDate->diff($today)->y; }
			$createdAt = isset($eligibility['created_at']) ? date('M d, Y', strtotime($eligibility['created_at'])) : '';
			$approvedDonations[] = [
				'eligibility_id' => $eligibility['eligibility_id'] ?? '',
				'donor_id' => $donorId,
				'surname' => $donor['surname'] ?? '',
				'first_name' => $donor['first_name'] ?? '',
				'middle_name' => $donor['middle_name'] ?? '',
				'donor_type' => 'Returning',
				'donor_number' => $donor['prc_donor_number'] ?? $donorId,
				'registration_source' => 'PRC System',
				'registration_channel' => 'PRC System',
				'age' => $age ?: '0',
				'sex' => $donor['sex'] ?? '',
				'birthdate' => $donor['birthdate'] ?? '',
				'blood_type' => $eligibility['blood_type'] ?? '',
				'donation_type' => $eligibility['donation_type'] ?? '',
				'status' => 'approved',
				'status_text' => 'Approved',
				'status_class' => 'bg-success',
				'date_submitted' => $createdAt,
				'sort_ts' => strtotime($eligibility['created_at'] ?? 'now')
			];
		}

        // Sort by newest first (LIFO: Last In First Out)
        if (!empty($approvedDonations) && !isset($approvedDonations['error'])) {
            usort($approvedDonations, function($a, $b) {
                $ta = $a['sort_ts'] ?? 0; $tb = $b['sort_ts'] ?? 0;
                if ($ta === $tb) return 0;
                return ($ta > $tb) ? -1 : 1; // Descending order: newest first
            });
        }

        // Expose next/prev cursors for API when perf mode
        if ($perfMode && !empty($eligibilityData)) {
            // Data is ordered desc; first is newest, last is oldest
            $first = $eligibilityData[0];
            $last = $eligibilityData[count($eligibilityData) - 1];
            $GLOBALS['APPROVED_PREV_CURSOR_TS'] = $first['created_at'] ?? null;
            $GLOBALS['APPROVED_PREV_CURSOR_ID'] = $first['eligibility_id'] ?? null;
            $GLOBALS['APPROVED_NEXT_CURSOR_TS'] = $last['created_at'] ?? null;
            $GLOBALS['APPROVED_NEXT_CURSOR_ID'] = $last['eligibility_id'] ?? null;
        }
	}

} catch (Exception $e) {
	$error = $e->getMessage();
	$approvedDonations = ['error' => $error];
}

// Simple cache headers; further caching controlled by the caller
header('Cache-Control: public, max-age=300');
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 300));

// Set error message if no records found
if (empty($approvedDonations) && !$error) {
    $error = "No approved donation records found.";
    error_log("Approved Donations: No records found with no API errors");
}

// Performance logging (gated by ?debug=1)
$executionTime = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    error_log("Approved Donations Module - Records found: " . count($approvedDonations) . " in " . round($executionTime, 3) . " seconds");
}

// DEBUG: Log the query details for troubleshooting
error_log("Approved Donations Query URL: " . $queryUrl);
error_log("Raw eligibility data count: " . (is_array($eligibilityData) ? count($eligibilityData) : 0));
error_log("Donor lookup count: " . (is_array($donorLookup) ? count($donorLookup) : 0));

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