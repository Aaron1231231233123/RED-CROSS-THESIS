<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Test debug logging (gated by ?debug=1)
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    error_log("DEBUG - donation_pending.php module loaded at " . date('Y-m-d H:i:s'));
}

// Include database connection
include_once '../../assets/conn/db_conn.php';

// OPTIMIZATION: Include shared utilities first to prevent function redeclaration
include_once __DIR__ . '/shared_utilities.php';

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/optimized_functions.php';

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// Array to hold donor data
$pendingDonations = [];
$error = null;

// Feature flag to enable batched, scoped queries
$perfMode = (isset($_GET['perf_mode']) && $_GET['perf_mode'] === 'on');
// Optional keyset cursor params (perf mode only)
$cursorTs = isset($_GET['cursor_ts']) ? $_GET['cursor_ts'] : null;
$cursorId = isset($_GET['cursor_id']) ? intval($_GET['cursor_id']) : null;
$cursorDir = isset($_GET['cursor_dir']) ? $_GET['cursor_dir'] : 'next'; // next|prev

try {
    // OPTIMIZATION: For LCP improvement, reduce initial fetch size for pending filter
    // This significantly improves LCP by reducing the amount of data processed initially
    // EXCEPTION: For count-only queries, fetch ALL donors to get accurate count
    $isCountOnly = isset($_GET['count_only']) && $_GET['count_only'] == '1';
    
    // For count queries, we need to fetch all donors in batches to get accurate count
    // For regular queries, use pagination limits
    if ($isCountOnly) {
        // Fetch all donors in batches for accurate count
        $allDonorIds = [];
        $batchLimit = 1000;
        $batchOffset = 0;
        $maxBatches = 50; // Safety limit: max 50,000 donors
        
        do {
            $batchResponse = supabaseRequest("donor_form?order=submitted_at.desc&limit={$batchLimit}&offset={$batchOffset}&select=donor_id");
            if (isset($batchResponse['data']) && is_array($batchResponse['data'])) {
                $batchIds = array_column($batchResponse['data'], 'donor_id');
                $allDonorIds = array_merge($allDonorIds, $batchIds);
                $batchOffset += $batchLimit;
                
                // If we got fewer results than the limit, we've reached the end
                if (count($batchResponse['data']) < $batchLimit) {
                    break;
                }
            } else {
                break;
            }
        } while (count($allDonorIds) < ($maxBatches * $batchLimit));
        
        // Now fetch full donor data for all collected IDs
        if (!empty($allDonorIds)) {
            // Split into chunks of 1000 for the IN query (Supabase limit)
            $donorChunks = array_chunk($allDonorIds, 1000);
            $allDonorData = [];
            
            foreach ($donorChunks as $chunk) {
                $chunkResponse = supabaseRequest("donor_form?donor_id=in.(" . implode(',', $chunk) . ")&select=*");
                if (isset($chunkResponse['data']) && is_array($chunkResponse['data'])) {
                    $allDonorData = array_merge($allDonorData, $chunkResponse['data']);
                }
            }
            
            $donorResponse = ['data' => $allDonorData];
        } else {
            $donorResponse = ['data' => []];
        }
    } else {
        // Regular pagination behavior
        $initialLimit = $perfMode ? 100 : 5000;
        $offset = 0;
        
        // Check if this is a continuation request (for pagination)
        $isContinuation = isset($_GET['continuation']) && $_GET['continuation'] == '1';
        $continuationOffset = isset($_GET['continuation_offset']) ? intval($_GET['continuation_offset']) : 0;
        
        if ($isContinuation && $continuationOffset > 0) {
            $offset = $continuationOffset;
            $initialLimit = 200; // Larger batches for continuation requests
        }
        
        // Fetch donors (authoritative list to scope downstream queries)
        $donorResponse = supabaseRequest("donor_form?order=submitted_at.desc&limit={$initialLimit}&offset={$offset}");
    }
    
    if (!isset($donorResponse['data']) || !is_array($donorResponse['data'])) {
        throw new Exception("Failed to fetch donors: " . ($donorResponse['error'] ?? 'Unknown error'));
    }

    // Build donor id list
    $donorIds = array_values(array_filter(array_column($donorResponse['data'], 'donor_id')));

    // Short-circuit if no donors
    if (empty($donorIds)) {
        $pendingDonations = [];
    } else {
        // OPTIMIZATION 1: Pull latest eligibility records for only current donor ids
        // Include blood_collection_id to check if blood collection was completed
        $donorsWithEligibility = [];
        $eligibilityBatch = supabaseRequest("eligibility?donor_id=in.(" . implode(',', $donorIds) . ")&select=donor_id,status,blood_collection_id,created_at&order=created_at.desc");
        if (isset($eligibilityBatch['data']) && is_array($eligibilityBatch['data'])) {
            foreach ($eligibilityBatch['data'] as $elig) {
                if (!empty($elig['donor_id'])) {
                    $donorsWithEligibility[$elig['donor_id']] = true;
                }
            }
        }

        if ($perfMode) {
            // OPTIMIZATION 2 (perf mode): Fetch related tables scoped by donor ids using in() filters
            // ROOT CAUSE FIX: Order by created_at DESC and include screening_id to link to current donation cycle
            $screeningResponse = supabaseRequest("screening_form?donor_form_id=in.(" . implode(',', $donorIds) . ")&select=screening_id,donor_form_id,needs_review,disapproval_reason,created_at&order=created_at.desc");
            $medicalResponse   = supabaseRequest("medical_history?donor_id=in.(" . implode(',', $donorIds) . ")&select=donor_id,needs_review,medical_approval,is_admin,updated_at&order=updated_at.desc");
            $physicalResponse  = supabaseRequest("physical_examination?donor_id=in.(" . implode(',', $donorIds) . ")&select=physical_exam_id,donor_id,screening_id,needs_review,remarks,created_at&order=created_at.desc");
        } else {
            // Fallback to previous broader fetches when perf mode is off
            // ROOT CAUSE FIX: Order by created_at DESC and include screening_id to link to current donation cycle
            $screeningResponse = supabaseRequest("screening_form?select=screening_id,donor_form_id,needs_review,disapproval_reason,created_at&order=created_at.desc");
            $medicalResponse   = supabaseRequest("medical_history?select=donor_id,needs_review,medical_approval,is_admin,updated_at&order=updated_at.desc");
            $physicalResponse  = supabaseRequest("physical_examination?select=physical_exam_id,donor_id,screening_id,needs_review,remarks,created_at&order=created_at.desc");
        }

        // Build lookup maps
        // ROOT CAUSE FIX: Build screening lookup ensuring we only keep the LATEST record per donor
        $screeningByDonorId = [];
        if (isset($screeningResponse['data']) && is_array($screeningResponse['data'])) {
            foreach ($screeningResponse['data'] as $row) {
                if (!empty($row['donor_form_id'])) {
                    $donorFormId = $row['donor_form_id'];
                    // Only set if not already set (first record = latest due to DESC ordering)
                    if (!isset($screeningByDonorId[$donorFormId])) {
                        $screeningByDonorId[$donorFormId] = [
                            'screening_id' => $row['screening_id'] ?? null,
                            'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                            'disapproval_reason' => $row['disapproval_reason'] ?? null,
                            'created_at' => $row['created_at'] ?? null
                        ];
                    }
                }
            }
        }

        // ROOT CAUSE FIX: Build medical history lookup ensuring we only keep the LATEST record per donor
        $medicalByDonorId = [];
        if (isset($medicalResponse['data']) && is_array($medicalResponse['data'])) {
            foreach ($medicalResponse['data'] as $row) {
                if (!empty($row['donor_id'])) {
                    $donorIdKey = $row['donor_id'];
                    // Only set if not already set (first record = latest due to DESC ordering)
                    if (!isset($medicalByDonorId[$donorIdKey])) {
                        $medicalByDonorId[$donorIdKey] = [
                            'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                            'medical_approval' => $row['medical_approval'] ?? null,
                            'is_admin' => $row['is_admin'] ?? null,
                            'updated_at' => $row['updated_at'] ?? null
                        ];
                    }
                }
            }
        }

        // ROOT CAUSE FIX: Build physical exam lookup ensuring we only keep the LATEST record per donor
        // For returning donors, we need the physical exam from the CURRENT donation cycle
        // Since queries are ordered by created_at DESC, first record per donor is the latest
        $physicalByDonorId = [];
        $physicalExamIdByDonorId = [];
        $screeningIdByDonorId = []; // Track screening_id for each donor to link physical exams
        if (isset($physicalResponse['data']) && is_array($physicalResponse['data'])) {
            foreach ($physicalResponse['data'] as $row) {
                if (!empty($row['donor_id'])) {
                    $donorIdKey = $row['donor_id'];
                    // Only set if not already set (first record = latest due to DESC ordering)
                    if (!isset($physicalByDonorId[$donorIdKey])) {
                        $physicalByDonorId[$donorIdKey] = [
                            'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                            'remarks' => $row['remarks'] ?? null,
                            'created_at' => $row['created_at'] ?? null,
                            'screening_id' => $row['screening_id'] ?? null
                        ];
                        if (!empty($row['physical_exam_id'])) {
                            $physicalExamIdByDonorId[$donorIdKey] = $row['physical_exam_id'];
                        }
                        if (!empty($row['screening_id'])) {
                            $screeningIdByDonorId[$donorIdKey] = $row['screening_id'];
                        }
                    }
                }
            }
        }

        // Blood collection: fetch only for relevant physical_exam_ids (if any)
        $collectionByPhysicalExamId = [];
        if (!empty($physicalExamIdByDonorId)) {
            $examIds = array_values(array_filter($physicalExamIdByDonorId));
            if (!empty($examIds)) {
                if ($perfMode) {
                    $collectionResponse = supabaseRequest("blood_collection?physical_exam_id=in.(" . implode(',', $examIds) . ")&select=physical_exam_id,needs_review,status,is_successful,created_at&order=created_at.desc");
                } else {
                    $collectionResponse = supabaseRequest("blood_collection?select=physical_exam_id,needs_review,status,is_successful,start_time,created_at&order=created_at.desc");
                }
                if (isset($collectionResponse['data']) && is_array($collectionResponse['data'])) {
                    foreach ($collectionResponse['data'] as $row) {
                        if (!empty($row['physical_exam_id']) && !isset($collectionByPhysicalExamId[$row['physical_exam_id']])) {
                            $collectionByPhysicalExamId[$row['physical_exam_id']] = [
                                'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                                'status' => $row['status'] ?? null,
                                'is_successful' => isset($row['is_successful']) ? (bool)$row['is_successful'] : null,
                                'created_at' => $row['created_at'] ?? null
                            ];
                        }
                    }
                }
            }
        }

    // (Removed duplicate lookup map rebuilding to avoid redefinition and extra work)

    // OPTIMIZATION 3: Get donor_form data with limit/offset for pagination
    if (isset($donorResponse['data']) && is_array($donorResponse['data'])) {
        // Log the first donor record to see all available fields
        if (!empty($donorResponse['data'])) {
            error_log("First donor record fields: " . print_r(array_keys($donorResponse['data'][0]), true));
        }
        
        // Log all donor IDs being processed (only in debug mode)
        $donorIds = array_column($donorResponse['data'], 'donor_id');
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            error_log("DEBUG - Processing " . count($donorIds) . " donors. Donor IDs: " . implode(', ', $donorIds));
            
            // Check if our specific donor IDs are in the fetched data
            $targetDonorIds = [176, 169, 170, 144, 140, 142, 135, 120, 189];
            $foundTargetIds = array_intersect($targetDonorIds, $donorIds);
            if (!empty($foundTargetIds)) {
                error_log("DEBUG - Found target donor IDs in fetched data: " . implode(', ', $foundTargetIds));
            } else {
                error_log("DEBUG - Target donor IDs NOT found in fetched data. They may be outside the limit/offset range.");
            }
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
            
            // Debug logging for specific donor IDs
            if (in_array($donorId, [176, 169, 170, 144, 140, 142, 135, 120, 189])) {
                error_log("DEBUG - Processing donor $donorId: donorType = '$donorType'");
            }

            // Compute current process status
            $statusLabel = 'Pending (Screening)';
            if ($donorId !== null) {
                // PRIORITY CHECK: Look for ANY decline/deferral status anywhere in the workflow using batched maps
                $hasDeclineDeferStatus = false;
                $declineDeferType = '';
                // Eligibility
                if (isset($donorsWithEligibility[$donorId])) {
                    // We only know the donor has eligibility; if needed, perf_mode could enrich with latest status
                    // For now, assume presence of eligibility indicates returning donor; status decision remains via other tables
                }
                
                // 2. Check screening form for decline status
                if (!$hasDeclineDeferStatus && isset($screeningByDonorId[$donorId])) {
                    $screen = $screeningByDonorId[$donorId];
                    if (is_array($screen) && !empty($screen['disapproval_reason'])) {
                        $hasDeclineDeferStatus = true;
                        $declineDeferType = 'Declined';
                    }
                }
                
                // 3. Check physical examination for deferral/decline status
                if (!$hasDeclineDeferStatus && isset($physicalByDonorId[$donorId])) {
                    $phys = $physicalByDonorId[$donorId];
                    if (is_array($phys) && isset($phys['remarks'])) {
                        $remarks = $phys['remarks'];
                        if (in_array($remarks, ['Temporarily Deferred', 'Permanently Deferred'])) {
                            $hasDeclineDeferStatus = true;
                            $declineDeferType = 'Deferred';
                        } elseif (in_array($remarks, ['Declined', 'Refused'])) {
                            $hasDeclineDeferStatus = true;
                            $declineDeferType = 'Declined';
                        }
                    }
                }
                
                // 4. Check medical history for decline status
                if (!$hasDeclineDeferStatus && isset($medicalByDonorId[$donorId])) {
                    $medRecord = $medicalByDonorId[$donorId];
                    if (is_array($medRecord)) {
                        $medicalApproval = $medRecord['medical_approval'] ?? '';
                        if ($medicalApproval === 'Not Approved') {
                            $hasDeclineDeferStatus = true;
                            $declineDeferType = 'Declined';
                        }
                    }
                }
                
                // If donor has ANY decline/deferral status, they should not appear in pending
                if ($hasDeclineDeferStatus) {
                    if (in_array($donorId, [176, 169, 170, 144, 140, 142, 135, 120])) {
                        error_log("DEBUG - Donor $donorId: Skipped due to decline/deferral status: '$declineDeferType'");
                    }
                    continue; // Skip this donor - they have a final decline/deferral status
                }
                
                // WORKFLOW: Check interviewer phase completion
                $isMedicalHistoryCompleted = false;
                $isScreeningPassed = false;
                $isPhysicalExamApproved = false;
                
                // Check Medical History completion - SKIP if no medical history record exists
                if (!isset($medicalByDonorId[$donorId])) {
                    // Skip donors without medical history - they haven't been processed yet
                    // Debug logging for donor 3205
                    if ($donorId == 3205) {
                        error_log("DEBUG - Donor 3205: No medical history record found, skipping (should not appear in pending)");
                    }
                    continue;
                }
                
                // Medical history record exists - check if completed
                $medRecord = $medicalByDonorId[$donorId];
                if (is_array($medRecord)) {
                    $medNeeds = $medRecord['needs_review'] ?? null;
                    $isAdmin = $medRecord['is_admin'] ?? null;
                    $medicalApproval = $medRecord['medical_approval'] ?? '';
                    
                    // Medical History is completed if:
                    // 1. Admin side: is_admin is TRUE (string or boolean)
                    // 2. OR needs_review is false/null
                    $isMedicalHistoryCompleted = ($isAdmin === true || $isAdmin === 'true' || $isAdmin === 'True') || 
                                                ($medNeeds === false || $medNeeds === null || $medNeeds === 0);
                    
                    // Debug logging for specific donors
                    if (in_array($donorId, [195, 200, 211, 1887, 2514, 3203, 3204, 3205, 3208])) {
                        error_log("DEBUG - Donor $donorId Medical History: is_admin = " . var_export($isAdmin, true) . 
                                 ", needs_review = " . var_export($medNeeds, true) . 
                                 ", medical_approval = '$medicalApproval' completed = " . var_export($isMedicalHistoryCompleted, true));
                    }
                }
                
                // Check Initial Screening completion
                if (isset($screeningByDonorId[$donorId])) {
                    $screen = $screeningByDonorId[$donorId];
                    if (is_array($screen)) {
                        $screenNeeds = $screen['needs_review'] ?? null;
                        $disapprovalReason = $screen['disapproval_reason'] ?? '';
                        
                        // Screening is passed if needs_review is false/null/0 AND no disapproval reason
                        $isScreeningPassed = ($screenNeeds === false || $screenNeeds === null || $screenNeeds === 0) && empty($disapprovalReason);
                        
                        // Debug logging for specific donors
                        if (in_array($donorId, [195, 200, 211, 1887, 2514, 3203, 3204, 3205, 3208])) {
                            error_log("DEBUG - Donor $donorId Screening: needs_review = " . var_export($screenNeeds, true) . 
                                     ", disapproval_reason = '$disapprovalReason', passed = " . var_export($isScreeningPassed, true));
                        }
                    }
                }
                
                // Check Physical Examination approval
                // ROOT CAUSE FIX: Match physical exam to current screening cycle for returning donors
                // For returning donors, we need to ensure we're checking the physical exam from the CURRENT donation cycle
                $currentScreeningId = null;
                if (isset($screeningByDonorId[$donorId]) && !empty($screeningByDonorId[$donorId]['screening_id'])) {
                    $currentScreeningId = $screeningByDonorId[$donorId]['screening_id'];
                }
                
                // ROOT CAUSE FIX: Robust approval logic with explicit 'Accepted' check and fallbacks
                if (isset($physicalByDonorId[$donorId])) {
                    $phys = $physicalByDonorId[$donorId];
                    if (is_array($phys)) {
                        // ROOT CAUSE FIX: For returning donors, prefer physical exam linked to current screening
                        // If current screening_id exists and physical exam has a different screening_id, 
                        // it might be from a previous donation cycle - but we'll still check it as fallback
                        $physScreeningId = $phys['screening_id'] ?? null;
                        $isFromCurrentCycle = ($currentScreeningId && $physScreeningId && $currentScreeningId === $physScreeningId);
                        
                        $physNeeds = $phys['needs_review'] ?? null;
                        // ROOT CAUSE FIX: Handle NULL remarks properly - enum can be NULL if not set
                        $remarksRaw = $phys['remarks'] ?? null;
                        $remarks = ($remarksRaw !== null && $remarksRaw !== '') ? trim((string)$remarksRaw) : '';
                        
                        // Normalize needs_review check: false, null, 0, or string 'false' all mean "doesn't need review"
                        $needsReviewIsFalse = ($physNeeds === false || $physNeeds === null || $physNeeds === 0 || 
                                             $physNeeds === 'false' || $physNeeds === 'False' || $physNeeds === 'FALSE');
                        
                        // PRIMARY CHECK: Explicitly check for 'Accepted' (the valid enum value from admin handler)
                        // Use case-insensitive comparison to handle data inconsistencies
                        $isAccepted = !empty($remarks) && (strcasecmp($remarks, 'Accepted') === 0);
                        
                        // FALLBACK 1: Check for other approved variations (handle legacy data or typos)
                        $approvedKeywords = ['Approved', 'Cleared', 'Passed', 'Completed'];
                        $isApprovedKeyword = false;
                        if (!empty($remarks)) {
                            foreach ($approvedKeywords as $keyword) {
                                if (strcasecmp($remarks, $keyword) === 0) {
                                    $isApprovedKeyword = true;
                                    break;
                                }
                            }
                        }
                        
                        // FALLBACK 2: Defensive check - ensure it's NOT in the negative list
                        $negativeRemarks = ['Pending', 'Temporarily Deferred', 'Permanently Deferred', 'Declined', 'Refused'];
                        $isNegative = false;
                        if (!empty($remarks)) {
                            // Exact match check
                            if (in_array($remarks, $negativeRemarks, true)) {
                                $isNegative = true;
                            } else {
                                // Case-insensitive check for data inconsistencies
                                $remarksLower = strtolower($remarks);
                                $negativeLower = array_map('strtolower', $negativeRemarks);
                                if (in_array($remarksLower, $negativeLower, true)) {
                                    $isNegative = true;
                                }
                            }
                        }
                        
                        // APPROVAL LOGIC: Physical exam is approved if:
                        // 1. needs_review is false/null/0 (doesn't need review)
                        // 2. AND remarks is 'Accepted' (primary) OR approved keyword (fallback)
                        // 3. AND remarks is NOT negative (defensive)
                        // ROOT CAUSE FIX: For returning donors, prioritize physical exam from current cycle,
                        // but also accept latest physical exam if it's approved (handles cases where screening_id link is missing)
                        $isPhysicalExamApproved = $needsReviewIsFalse && 
                            !empty($remarks) && 
                            !$isNegative && 
                            ($isAccepted || $isApprovedKeyword);
                        
                        // Debug logging for specific donors (including 3298 for troubleshooting)
                        if (in_array($donorId, [3298, 195, 200, 211, 1887, 2514, 3203, 3204, 3205, 3208, 781, 3282, 3278])) {
                            error_log("DEBUG - Donor $donorId Physical Exam: currentScreeningId = " . var_export($currentScreeningId, true) . 
                                     ", physScreeningId = " . var_export($physScreeningId, true) . 
                                     ", isFromCurrentCycle = " . var_export($isFromCurrentCycle, true) . 
                                     ", needs_review = " . var_export($physNeeds, true) . 
                                     ", remarks_raw = " . var_export($remarksRaw, true) . 
                                     ", remarks = '$remarks', needsReviewIsFalse = " . var_export($needsReviewIsFalse, true) . 
                                     ", isAccepted = " . var_export($isAccepted, true) . 
                                     ", isApprovedKeyword = " . var_export($isApprovedKeyword, true) . 
                                     ", isNegative = " . var_export($isNegative, true) . 
                                     ", approved = " . var_export($isPhysicalExamApproved, true));
                        }
                    }
                } else {
                    // ROOT CAUSE FIX: If no physical exam record exists, explicitly set to false
                    // This ensures donors without physical exams show as "Pending (Examination)"
                    $isPhysicalExamApproved = false;
                    
                    // Debug logging for missing physical exam
                    if (in_array($donorId, [3298, 3282, 3278])) {
                        error_log("DEBUG - Donor $donorId: No physical exam record found in physicalByDonorId map");
                    }
                }
                
                // Check if blood collection exists and if it's successful
                $isBloodCollectionSuccessful = false;
                $hasBloodCollectionRecord = false;
                $physicalExamId = $physicalExamIdByDonorId[$donorId] ?? null;
                if ($physicalExamId && isset($collectionByPhysicalExamId[$physicalExamId])) {
                    $hasBloodCollectionRecord = true;
                    $collection = $collectionByPhysicalExamId[$physicalExamId];
                    // Check if collection is successful - prioritize is_successful field, fallback to status field
                    if (isset($collection['is_successful']) && ($collection['is_successful'] === true || $collection['is_successful'] === 'true' || $collection['is_successful'] === 1)) {
                        $isBloodCollectionSuccessful = true;
                    } else {
                        $collectionStatus = strtolower(trim((string)($collection['status'] ?? '')));
                        $isBloodCollectionSuccessful = ($collectionStatus === 'successful' || $collectionStatus === 'approved');
                    }
                }
                
                // Check if donor has approved eligibility record WITH completed blood collection
                // A donor should only be considered "approved" (excluded from pending) if:
                // 1. They have approved eligibility status AND
                // 2. They have blood_collection_id set in eligibility (meaning blood collection was completed)
                // OR blood collection is successful
                $hasApprovedEligibility = false;
                if (isset($eligibilityBatch['data']) && is_array($eligibilityBatch['data'])) {
                    foreach ($eligibilityBatch['data'] as $elig) {
                        if (!empty($elig['donor_id']) && $elig['donor_id'] == $donorId) {
                            $eligStatus = strtolower(trim((string)($elig['status'] ?? '')));
                            // Only consider as "approved" if status is approved/eligible AND blood_collection_id is set
                            // This ensures that eligibility records created after physical exam (without blood_collection_id)
                            // don't exclude donors from pending list
                            $hasBloodCollectionId = !empty($elig['blood_collection_id'] ?? null);
                            if (($eligStatus === 'approved' || $eligStatus === 'eligible') && $hasBloodCollectionId) {
                                $hasApprovedEligibility = true;
                                break;
                            }
                        }
                    }
                }
                
                // If blood collection is successful OR donor has approved eligibility WITH completed blood collection, skip this donor (they should be in approved list)
                if ($isBloodCollectionSuccessful || $hasApprovedEligibility) {
                    if (in_array($donorId, [195, 200, 211, 1887, 2514, 3203, 3204, 3205, 3208, 781])) {
                        error_log("DEBUG - Donor $donorId: Skipped from pending - Blood Collection Successful: " . var_export($isBloodCollectionSuccessful, true) . 
                                 ", Has Approved Eligibility: " . var_export($hasApprovedEligibility, true));
                    }
                    continue; // Skip this donor - they should be in approved list, not pending
                }
                
                // ROOT CAUSE FIX: Status determination logic - ensure correct workflow progression
                // Workflow: Screening -> Examination -> Collection -> Approved
                // Status is determined by the LAST completed phase:
                // - If all three phases complete (MH + Screening + PE) -> Pending (Collection)
                // - If interviewer phase complete (MH + Screening) but PE not approved -> Pending (Examination)
                // - If interviewer phase not complete -> Pending (Screening)
                
                // FALLBACK: If physical exam record exists but approval check failed, 
                // check if it's because of data inconsistencies (e.g., needs_review=true but remarks='Accepted')
                $hasPhysicalExamRecord = isset($physicalByDonorId[$donorId]);
                if ($hasPhysicalExamRecord && !$isPhysicalExamApproved) {
                    $phys = $physicalByDonorId[$donorId];
                    $remarksRaw = $phys['remarks'] ?? null;
                    $remarks = ($remarksRaw !== null && $remarksRaw !== '') ? trim((string)$remarksRaw) : '';
                    $physNeeds = $phys['needs_review'] ?? null;
                    
                    // FALLBACK CHECK: If remarks is 'Accepted' but needs_review is true, 
                    // still consider it approved (data inconsistency fix)
                    if (!empty($remarks) && strcasecmp($remarks, 'Accepted') === 0) {
                        // If remarks is 'Accepted', override needs_review check
                        // This handles cases where needs_review wasn't properly set to false
                        $isPhysicalExamApproved = true;
                        
                        if (in_array($donorId, [3298, 3282, 3278])) {
                            error_log("DEBUG - Donor $donorId: FALLBACK - Physical exam has 'Accepted' remarks but needs_review = " . var_export($physNeeds, true) . 
                                     " - Overriding to approved");
                        }
                    }
                }
                
                // Apply workflow logic
                if ($isMedicalHistoryCompleted && $isScreeningPassed && $isPhysicalExamApproved) {
                    // All phases complete -> Pending (Collection)
                    // This includes cases where blood_collection record exists but is_successful is false/null
                    $statusLabel = 'Pending (Collection)';
                } else if ($isMedicalHistoryCompleted && $isScreeningPassed) {
                    // Interviewer phase complete, physician phase pending -> Pending (Examination)
                    $statusLabel = 'Pending (Examination)';
                } else {
                    // Interviewer phase pending -> Pending (Screening)
                    $statusLabel = 'Pending (Screening)';
                }
                
                // Debug logging for specific donor IDs - ALL VISIBLE DONORS (including 3298 for troubleshooting)
                if (in_array($donorId, [3298, 195, 200, 211, 1887, 2514, 3203, 3204, 3205, 3208, 781])) {
                    error_log("DEBUG - Donor $donorId: MH Completed = " . var_export($isMedicalHistoryCompleted, true) . 
                             ", Screening Passed = " . var_export($isScreeningPassed, true) . 
                             ", PE Approved = " . var_export($isPhysicalExamApproved, true) . 
                             ", Has BC Record = " . var_export($hasBloodCollectionRecord, true) . 
                             ", BC Successful = " . var_export($isBloodCollectionSuccessful, true) . 
                             ", Has Approved Eligibility = " . var_export($hasApprovedEligibility, true) . 
                             ", Status = $statusLabel");
                }
                
                // Note: Old downstream stage checking logic removed - now using simplified workflow above
            }

            // OPTIMIZATION: Create standardized record with all required fields for UI
            // This ensures consistent data structure across all modules
            $pendingDonations[] = [
                'donor_id' => $donorId ?? '',
                'surname' => $donor['surname'] ?? '',
                'first_name' => $donor['first_name'] ?? '',
                'middle_name' => $donor['middle_name'] ?? '', // Add missing field
                'donor_type' => $donorType,
                'donor_number' => $donor['prc_donor_number'] ?? ($donorId ?? ''),
                'registration_source' => $donor['registration_channel'] ?? 'PRC System',
                'registration_channel' => $donor['registration_channel'] ?? 'PRC System', // Add alias for compatibility
                'status_text' => $statusLabel,
                'status' => strtolower(str_replace([' ', '(', ')'], ['_', '', ''], $statusLabel)), // Add normalized status
                'status_class' => getStatusClass($statusLabel), // Add pre-calculated CSS class
                'birthdate' => $donor['birthdate'] ?? '',
                'sex' => $donor['sex'] ?? '',
                'age' => calculateAge($donor['birthdate'] ?? ''), // Add calculated age
                'date_submitted' => $dateSubmitted,
                'eligibility_id' => 'pending_' . ($donorId ?? '0'),
                'sort_ts' => !empty($donor['submitted_at']) ? strtotime($donor['submitted_at']) : (!empty($donor['created_at']) ? strtotime($donor['created_at']) : time())
            ];
            
            // Debug logging for specific donor IDs
            if (in_array($donorId, [176, 169, 170, 144, 140, 142, 135, 120])) {
                error_log("DEBUG - Donor $donorId: Added to pendingDonations array with status: '$statusLabel'");
            }        }

        // Sort by newest first (LIFO: Last In First Out)
        usort($pendingDonations, function($a, $b) {
            $ta = $a['sort_ts'] ?? 0; $tb = $b['sort_ts'] ?? 0;
            if ($ta === $tb) return 0;
            return ($ta > $tb) ? -1 : 1; // Descending order: newest first
        });
        
        // Do NOT slice here - return ALL filtered results
        // Dashboard handles pagination for specific status filters
        // For "all" status, return all for aggregation
    } else {
        // Handle API errors
        if (isset($donorResponse['error'])) {
            $error = "API Error: " . $donorResponse['error'];
        } else {
            $error = "API Error: HTTP Code " . ($donorResponse['code'] ?? 'Unknown');
        }
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

// OPTIMIZATION: Shared utilities already included at the top of the file 