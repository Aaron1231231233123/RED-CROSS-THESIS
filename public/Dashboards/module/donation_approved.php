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
    // ROOT CAUSE FIX: Only show eligibility records with complete process (blood_collection_id must be set)
    // This ensures donors only appear as "Approved" when they have completed the full workflow
    $baseParams = [
        'select' => 'eligibility_id,donor_id,blood_type,donation_type,created_at,status,collection_successful,blood_collection_id',
        'or' => '(status.eq.approved,status.eq.eligible,collection_successful.eq.true)',
        'order' => 'created_at.desc,eligibility_id.desc',
        'limit' => $limit
    ];
    
    // CRITICAL: Add blood_collection_id filter - must be done separately due to Supabase query syntax
    // Build base URL with status filter
    if (!$perfMode || !$cursorTs) {
        // Offset fallback
        $baseParams['offset'] = $offset;
        $queryUrl = SUPABASE_URL . "/rest/v1/eligibility?" . http_build_query($baseParams) . "&blood_collection_id=not.is.null";
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
        $queryUrl = SUPABASE_URL . "/rest/v1/eligibility?" . http_build_query($baseParams) . "&or=" . rawurlencode($or) . "&blood_collection_id=not.is.null";
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
		
		// ROOT CAUSE FIX: Batch fetch physical exam records to check for deferral/decline status
		// This ensures donors with "Temporarily Deferred" or "Permanently Deferred" are excluded from approved list
		$physicalExamLookup = [];
		$declinedScreeningLookup = [];
		$declinedMedicalLookup = [];
		if (!empty($donorIds)) {
			$donorIdsParam = implode(',', $donorIds);
			
			// Fetch physical exam records with deferral/decline remarks
			$physicalCurl = curl_init();
			curl_setopt_array($physicalCurl, [
				CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?donor_id=in.(" . $donorIdsParam . ")&or=(remarks.eq.Temporarily%20Deferred,remarks.eq.Permanently%20Deferred,remarks.eq.Declined,remarks.eq.Refused)&select=donor_id,remarks&order=created_at.desc",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					"apikey: " . SUPABASE_API_KEY,
					"Authorization: Bearer " . SUPABASE_API_KEY,
					"Content-Type: application/json"
				],
				CURLOPT_TIMEOUT => 30,
				CURLOPT_CONNECTTIMEOUT => 10
			]);
			$physicalResponse = curl_exec($physicalCurl);
			$physicalHttpCode = curl_getinfo($physicalCurl, CURLINFO_HTTP_CODE);
			curl_close($physicalCurl);
			if ($physicalResponse !== false && $physicalHttpCode === 200) {
				$physicalData = json_decode($physicalResponse, true) ?: [];
				foreach ($physicalData as $exam) {
					$examDonorId = $exam['donor_id'] ?? null;
					if ($examDonorId && !isset($physicalExamLookup[$examDonorId])) {
						// Only keep the latest record per donor (already ordered by created_at desc)
						$physicalExamLookup[$examDonorId] = $exam['remarks'] ?? '';
					}
				}
			}
			
			// Fetch screening records with disapproval_reason
			// Note: screening_form uses donor_form_id, not donor_id
			$screeningCurl = curl_init();
			curl_setopt_array($screeningCurl, [
				CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=in.(" . $donorIdsParam . ")&disapproval_reason=not.is.null&select=donor_form_id,disapproval_reason&order=created_at.desc",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					"apikey: " . SUPABASE_API_KEY,
					"Authorization: Bearer " . SUPABASE_API_KEY,
					"Content-Type: application/json"
				],
				CURLOPT_TIMEOUT => 30,
				CURLOPT_CONNECTTIMEOUT => 10
			]);
			$screeningResponse = curl_exec($screeningCurl);
			$screeningHttpCode = curl_getinfo($screeningCurl, CURLINFO_HTTP_CODE);
			curl_close($screeningCurl);
			if ($screeningResponse !== false && $screeningHttpCode === 200) {
				$screeningData = json_decode($screeningResponse, true) ?: [];
				foreach ($screeningData as $screen) {
					$screenDonorId = $screen['donor_form_id'] ?? null;
					if ($screenDonorId && !empty($screen['disapproval_reason']) && !isset($declinedScreeningLookup[$screenDonorId])) {
						$declinedScreeningLookup[$screenDonorId] = true;
					}
				}
			}
			
			// Fetch medical history records with "Not Approved"
			$medicalCurl = curl_init();
			curl_setopt_array($medicalCurl, [
				CURLOPT_URL => SUPABASE_URL . "/rest/v1/medical_history?donor_id=in.(" . $donorIdsParam . ")&medical_approval=eq.Not%20Approved&select=donor_id,medical_approval&order=created_at.desc",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					"apikey: " . SUPABASE_API_KEY,
					"Authorization: Bearer " . SUPABASE_API_KEY,
					"Content-Type: application/json"
				],
				CURLOPT_TIMEOUT => 30,
				CURLOPT_CONNECTTIMEOUT => 10
			]);
			$medicalResponse = curl_exec($medicalCurl);
			$medicalHttpCode = curl_getinfo($medicalCurl, CURLINFO_HTTP_CODE);
			curl_close($medicalCurl);
			if ($medicalResponse !== false && $medicalHttpCode === 200) {
				$medicalData = json_decode($medicalResponse, true) ?: [];
				foreach ($medicalData as $med) {
					$medDonorId = $med['donor_id'] ?? null;
					if ($medDonorId && !isset($declinedMedicalLookup[$medDonorId])) {
						$declinedMedicalLookup[$medDonorId] = true;
					}
				}
			}
		}

        foreach ($eligibilityData as $eligibility) {
			$donorId = $eligibility['donor_id'] ?? null;
			if (!$donorId || !isset($donorLookup[$donorId])) { continue; }
			
			// ROOT CAUSE FIX: Validate that eligibility record has blood_collection_id (complete process)
			// This is a defensive check in case the query filter didn't work properly
			$hasBloodCollectionId = !empty($eligibility['blood_collection_id'] ?? null);
			if (!$hasBloodCollectionId) {
				// Skip incomplete eligibility records - they shouldn't appear as "Approved"
				error_log("Approved Donations: Skipping incomplete eligibility record (eligibility_id: " . ($eligibility['eligibility_id'] ?? 'N/A') . ", donor_id: $donorId) - missing blood_collection_id");
				continue;
			}
			
			// FALLBACK: Additional validation - check status is approved/eligible
			$eligStatus = strtolower(trim((string)($eligibility['status'] ?? '')));
			$isApprovedStatus = ($eligStatus === 'approved' || $eligStatus === 'eligible');
			$collectionSuccessful = isset($eligibility['collection_successful']) && 
			                        ($eligibility['collection_successful'] === true || 
			                         $eligibility['collection_successful'] === 'true' || 
			                         $eligibility['collection_successful'] === 1);
			
			// Only include if status is approved/eligible OR collection_successful is true
			if (!$isApprovedStatus && !$collectionSuccessful) {
				error_log("Approved Donations: Skipping eligibility record (eligibility_id: " . ($eligibility['eligibility_id'] ?? 'N/A') . ", donor_id: $donorId) - status not approved and collection not successful");
				continue;
			}
			
			// ROOT CAUSE FIX: Check for deferral/decline status in physical exam, screening, or medical history
			// Donors with "Temporarily Deferred", "Permanently Deferred", "Declined", or "Refused" should NOT appear in approved list
			$hasDeferralDeclineStatus = false;
			$deferralDeclineReason = '';
			
			// 1. Check physical exam remarks
			if (isset($physicalExamLookup[$donorId])) {
				$remarks = $physicalExamLookup[$donorId];
				if (in_array($remarks, ['Temporarily Deferred', 'Permanently Deferred', 'Declined', 'Refused'], true)) {
					$hasDeferralDeclineStatus = true;
					$deferralDeclineReason = "Physical Exam: $remarks";
					error_log("Approved Donations: Excluding donor $donorId from approved list - $deferralDeclineReason");
				}
			}
			
			// 2. Check screening form disapproval
			if (!$hasDeferralDeclineStatus && isset($declinedScreeningLookup[$donorId])) {
				$hasDeferralDeclineStatus = true;
				$deferralDeclineReason = "Screening: Disapproved";
				error_log("Approved Donations: Excluding donor $donorId from approved list - $deferralDeclineReason");
			}
			
			// 3. Check medical history not approved
			if (!$hasDeferralDeclineStatus && isset($declinedMedicalLookup[$donorId])) {
				$hasDeferralDeclineStatus = true;
				$deferralDeclineReason = "Medical History: Not Approved";
				error_log("Approved Donations: Excluding donor $donorId from approved list - $deferralDeclineReason");
			}
			
			// 4. Check eligibility status for declined/deferred (defensive check)
			if (!$hasDeferralDeclineStatus && in_array($eligStatus, ['declined', 'deferred', 'refused', 'ineligible'], true)) {
				$hasDeferralDeclineStatus = true;
				$deferralDeclineReason = "Eligibility Status: $eligStatus";
				error_log("Approved Donations: Excluding donor $donorId from approved list - $deferralDeclineReason");
			}
			
			// Skip donors with any deferral/decline status - they should be in declined/deferred list
			if ($hasDeferralDeclineStatus) {
				continue;
			}
			
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