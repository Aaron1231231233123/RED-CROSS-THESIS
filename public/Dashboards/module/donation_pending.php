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
    // OPTIMIZATION 1: Pull latest eligibility records to determine donor type (New vs Returning)
    $donorsWithEligibility = [];
    $eligibilityResponse = supabaseRequest("eligibility?select=donor_id,created_at&order=created_at.desc");
    if (isset($eligibilityResponse['data']) && is_array($eligibilityResponse['data'])) {
        foreach ($eligibilityResponse['data'] as $eligibility) {
            if (!empty($eligibility['donor_id'])) {
                $donorsWithEligibility[$eligibility['donor_id']] = true; // set for quick lookup
            }
        }
    }
    
    // OPTIMIZATION 2: Fetch minimal related process tables to compute current pending status
    // Include needs_review flags from each stage to drive pending status logic
    $screeningResponse = supabaseRequest("screening_form?select=screening_id,donor_form_id,needs_review,created_at");
    $medicalResponse   = supabaseRequest("medical_history?select=donor_id,needs_review,updated_at");
    $physicalResponse  = supabaseRequest("physical_examination?select=donor_id,needs_review,created_at");
    $collectionResponse = supabaseRequest("blood_collection?select=screening_id,needs_review,start_time");

    // Build lookup maps
    $screeningByDonorId = [];
    if (isset($screeningResponse['data']) && is_array($screeningResponse['data'])) {
        foreach ($screeningResponse['data'] as $row) {
            if (!empty($row['donor_form_id'])) {
                $screeningByDonorId[$row['donor_form_id']] = [
                    'screening_id' => $row['screening_id'] ?? null,
                    'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null
                ];
            }
        }
    }
    $physicalByDonorId = [];
    $medicalNeedsByDonorId = [];
    if (isset($medicalResponse['data']) && is_array($medicalResponse['data'])) {
        foreach ($medicalResponse['data'] as $row) {
            if (!empty($row['donor_id'])) {
                $medicalNeedsByDonorId[$row['donor_id']] = isset($row['needs_review']) ? (bool)$row['needs_review'] : null;
            }
        }
    }
    if (isset($physicalResponse['data']) && is_array($physicalResponse['data'])) {
        foreach ($physicalResponse['data'] as $row) {
            if (!empty($row['donor_id'])) {
                $physicalByDonorId[$row['donor_id']] = isset($row['needs_review']) ? (bool)$row['needs_review'] : null;
            }
        }
    }
    $collectionByScreeningId = [];
    if (isset($collectionResponse['data']) && is_array($collectionResponse['data'])) {
        foreach ($collectionResponse['data'] as $row) {
            if (!empty($row['screening_id'])) {
                $collectionByScreeningId[$row['screening_id']] = isset($row['needs_review']) ? (bool)$row['needs_review'] : null;
            }
        }
    }

    // OPTIMIZATION 3: Get donor_form data with limit/offset for pagination
    $limit = isset($GLOBALS['DONATION_LIMIT']) ? intval($GLOBALS['DONATION_LIMIT']) : 100;
    $offset = isset($GLOBALS['DONATION_OFFSET']) ? intval($GLOBALS['DONATION_OFFSET']) : 0;
    $donorResponse = supabaseRequest("donor_form?order=submitted_at.desc&limit={$limit}&offset={$offset}");
    
    if (isset($donorResponse['data']) && is_array($donorResponse['data'])) {
        // Log the first donor record to see all available fields
        if (!empty($donorResponse['data'])) {
            error_log("First donor record fields: " . print_r(array_keys($donorResponse['data'][0]), true));
        }
        
        // Process each donor
        foreach ($donorResponse['data'] as $donor) {
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
            
            // Determine donor type using presence of eligibility (aligns with staff dashboard logic)
            $donorId = $donor['donor_id'] ?? null;
            $donorType = isset($donorsWithEligibility[$donorId]) ? 'Returning' : 'New';

            // Compute current process status
            $statusLabel = 'Pending (Screening)';
            if ($donorId !== null) {
                // First, check medical history: if needs_review === true, treat as brand new entry
                $medNeeds = $medicalNeedsByDonorId[$donorId] ?? null;
                if ($medNeeds === true) {
                    $statusLabel = 'Pending (New)';
                } else {
                $screen = $screeningByDonorId[$donorId] ?? null;
                $screenNeeds = is_array($screen) ? ($screen['needs_review'] ?? null) : null;
                $screeningId = is_array($screen) ? ($screen['screening_id'] ?? null) : ($screen ?? null);

                if ($screen === null) {
                    // No screening yet, still pending at screening
                    $statusLabel = 'Pending (Screening)';
                } else if ($screenNeeds === true) {
                    // Screening requires review
                    $statusLabel = 'Pending (Screening)';
                } else {
                    // Screening is done (needs_review === false), check Physical
                    $physNeeds = $physicalByDonorId[$donorId] ?? null;
                    if ($physNeeds === null) {
                        // No physical record yet -> next step physical
                        $statusLabel = 'Pending (Physical Examination)';
                    } else if ($physNeeds === true) {
                        $statusLabel = 'Pending (Physical Examination)';
                    } else {
                        // Physical done, check collection via physical_exam_id or screening_id
                        $collNeeds = null;
                        
                        // First try to find blood collection by physical_exam_id
                        // Check for completed physical examination (remarks is not empty and not 'Pending')
                        $physicalExamCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,remarks&donor_id=eq.' . $donorId . '&remarks=not.is.null&remarks=neq.Pending&order=created_at.desc&limit=1');
                        curl_setopt($physicalExamCurl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($physicalExamCurl, CURLOPT_HTTPHEADER, [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json'
                        ]);
                        $physicalExamResponse = curl_exec($physicalExamCurl);
                        $physicalExamHttpCode = curl_getinfo($physicalExamCurl, CURLINFO_HTTP_CODE);
                        curl_close($physicalExamCurl);
                        
                        if ($physicalExamHttpCode === 200) {
                            $physicalExams = json_decode($physicalExamResponse, true) ?: [];
                            if (!empty($physicalExams)) {
                                $physicalExam = $physicalExams[0];
                                $physicalExamId = $physicalExam['physical_exam_id'];
                                $remarks = $physicalExam['remarks'] ?? '';
                                
                                // Only proceed if physical examination is completed (has valid remarks)
                                if (!empty($remarks) && $remarks !== 'Pending') {
                                    // Check blood collection by physical_exam_id
                                    $collectionCurl = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=needs_review&physical_exam_id=eq.' . $physicalExamId);
                                    curl_setopt($collectionCurl, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($collectionCurl, CURLOPT_HTTPHEADER, [
                                        'apikey: ' . SUPABASE_API_KEY,
                                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                                        'Content-Type: application/json'
                                    ]);
                                    $collectionResponse = curl_exec($collectionCurl);
                                    $collectionHttpCode = curl_getinfo($collectionCurl, CURLINFO_HTTP_CODE);
                                    curl_close($collectionCurl);
                                    
                                    if ($collectionHttpCode === 200) {
                                        $collections = json_decode($collectionResponse, true) ?: [];
                                        if (!empty($collections)) {
                                            $collNeeds = isset($collections[0]['needs_review']) ? (bool)$collections[0]['needs_review'] : null;
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Fallback to screening_id method if physical_exam_id method didn't work
                        if ($collNeeds === null && $screeningId && isset($collectionByScreeningId[$screeningId])) {
                            $collNeeds = $collectionByScreeningId[$screeningId];
                        }
                        
                        if ($collNeeds === null) {
                            $statusLabel = 'Pending (Collection)';
                        } else if ($collNeeds === true) {
                            $statusLabel = 'Pending (Collection)';
                        } else {
                            // All stages marked needs_review=false; collection completed
                            $statusLabel = 'Completed';
                        }
                    }
                }
            }
            }

            // Create a simplified record with ONLY the required fields for UI
            $pendingDonations[] = [
                'donor_id' => $donorId ?? '',
                'surname' => $donor['surname'] ?? '',
                'first_name' => $donor['first_name'] ?? '',
                'donor_type' => $donorType,
                'donor_number' => $donor['prc_donor_number'] ?? ($donorId ?? ''),
                'registration_source' => $donor['registration_channel'] ?? 'PRC System',
                'status_text' => $statusLabel,
                // Keep existing fields used elsewhere
                'birthdate' => $donor['birthdate'] ?? '',
                'sex' => $donor['sex'] ?? '',
                'date_submitted' => $dateSubmitted,
                'eligibility_id' => 'pending_' . ($donorId ?? '0'),
                'sort_ts' => !empty($donor['submitted_at']) ? strtotime($donor['submitted_at']) : (!empty($donor['created_at']) ? strtotime($donor['created_at']) : time())
            ];
        }

        // Enforce FIFO: oldest first
        usort($pendingDonations, function($a, $b) {
            $ta = $a['sort_ts'] ?? 0; $tb = $b['sort_ts'] ?? 0;
            if ($ta === $tb) return 0;
            return ($ta < $tb) ? -1 : 1;
        });
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