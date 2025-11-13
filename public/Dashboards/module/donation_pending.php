<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Test debug logging (gated by ?debug=1)
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    error_log("DEBUG - donation_pending.php module loaded at " . date('Y-m-d H:i:s'));
}

// Also write to a custom log file for easier debugging when debug flag is set
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    file_put_contents('../../assets/logs/donation_pending_debug.log', "[" . date('Y-m-d H:i:s') . "] donation_pending.php module loaded\n", FILE_APPEND | LOCK_EX);
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
    $initialLimit = $perfMode ? 100 : 5000; // Start with smaller batch for faster LCP
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
        $donorsWithEligibility = [];
        $eligibilityBatch = supabaseRequest("eligibility?donor_id=in.(" . implode(',', $donorIds) . ")&select=donor_id,status,created_at&order=created_at.desc");
        if (isset($eligibilityBatch['data']) && is_array($eligibilityBatch['data'])) {
            foreach ($eligibilityBatch['data'] as $elig) {
                if (!empty($elig['donor_id'])) {
                    $donorsWithEligibility[$elig['donor_id']] = true;
                }
            }
        }

        if ($perfMode) {
            // OPTIMIZATION 2 (perf mode): Fetch related tables scoped by donor ids using in() filters
            $screeningResponse = supabaseRequest("screening_form?donor_form_id=in.(" . implode(',', $donorIds) . ")&select=screening_id,donor_form_id,needs_review,disapproval_reason,created_at");
            $medicalResponse   = supabaseRequest("medical_history?donor_id=in.(" . implode(',', $donorIds) . ")&select=donor_id,needs_review,medical_approval,is_admin,updated_at");
            $physicalResponse  = supabaseRequest("physical_examination?donor_id=in.(" . implode(',', $donorIds) . ")&select=physical_exam_id,donor_id,needs_review,remarks,created_at");
        } else {
            // Fallback to previous broader fetches when perf mode is off
            $screeningResponse = supabaseRequest("screening_form?select=screening_id,donor_form_id,needs_review,disapproval_reason,created_at");
            $medicalResponse   = supabaseRequest("medical_history?select=donor_id,needs_review,medical_approval,is_admin,updated_at");
            $physicalResponse  = supabaseRequest("physical_examination?select=physical_exam_id,donor_id,needs_review,remarks,created_at");
        }

        // Build lookup maps
        $screeningByDonorId = [];
        if (isset($screeningResponse['data']) && is_array($screeningResponse['data'])) {
            foreach ($screeningResponse['data'] as $row) {
                if (!empty($row['donor_form_id'])) {
                    $screeningByDonorId[$row['donor_form_id']] = [
                        'screening_id' => $row['screening_id'] ?? null,
                        'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                        'disapproval_reason' => $row['disapproval_reason'] ?? null
                    ];
                }
            }
        }

        $medicalByDonorId = [];
        if (isset($medicalResponse['data']) && is_array($medicalResponse['data'])) {
            foreach ($medicalResponse['data'] as $row) {
                if (!empty($row['donor_id'])) {
                    $medicalByDonorId[$row['donor_id']] = [
                        'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                        'medical_approval' => $row['medical_approval'] ?? null,
                        'is_admin' => $row['is_admin'] ?? null
                    ];
                }
            }
        }

        $physicalByDonorId = [];
        $physicalExamIdByDonorId = [];
        if (isset($physicalResponse['data']) && is_array($physicalResponse['data'])) {
            foreach ($physicalResponse['data'] as $row) {
                if (!empty($row['donor_id'])) {
                    $physicalByDonorId[$row['donor_id']] = [
                        'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                        'remarks' => $row['remarks'] ?? null
                    ];
                    if (!empty($row['physical_exam_id'])) {
                        $physicalExamIdByDonorId[$row['donor_id']] = $row['physical_exam_id'];
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
                    $collectionResponse = supabaseRequest("blood_collection?physical_exam_id=in.(" . implode(',', $examIds) . ")&select=physical_exam_id,needs_review,status,created_at&order=created_at.desc");
                } else {
                    $collectionResponse = supabaseRequest("blood_collection?select=physical_exam_id,needs_review,status,start_time,created_at&order=created_at.desc");
                }
                if (isset($collectionResponse['data']) && is_array($collectionResponse['data'])) {
                    foreach ($collectionResponse['data'] as $row) {
                        if (!empty($row['physical_exam_id']) && !isset($collectionByPhysicalExamId[$row['physical_exam_id']])) {
                            $collectionByPhysicalExamId[$row['physical_exam_id']] = [
                                'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                                'status' => $row['status'] ?? null,
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
        
        // Log all donor IDs being processed
        $donorIds = array_column($donorResponse['data'], 'donor_id');
        error_log("DEBUG - Processing " . count($donorIds) . " donors. Donor IDs: " . implode(', ', $donorIds));
        file_put_contents('../../assets/logs/donation_pending_debug.log', "[" . date('Y-m-d H:i:s') . "] Processing " . count($donorIds) . " donors. Donor IDs: " . implode(', ', $donorIds) . "\n", FILE_APPEND | LOCK_EX);
        
        // Check if our specific donor IDs are in the fetched data
        $targetDonorIds = [176, 169, 170, 144, 140, 142, 135, 120, 189];
        $foundTargetIds = array_intersect($targetDonorIds, $donorIds);
        if (!empty($foundTargetIds)) {
            error_log("DEBUG - Found target donor IDs in fetched data: " . implode(', ', $foundTargetIds));
            file_put_contents('../../assets/logs/donation_pending_debug.log', "[" . date('Y-m-d H:i:s') . "] Found target donor IDs in fetched data: " . implode(', ', $foundTargetIds) . "\n", FILE_APPEND | LOCK_EX);
        } else {
            error_log("DEBUG - Target donor IDs NOT found in fetched data. They may be outside the limit/offset range.");
            file_put_contents('../../assets/logs/donation_pending_debug.log', "[" . date('Y-m-d H:i:s') . "] Target donor IDs NOT found in fetched data. They may be outside the limit/offset range.\n", FILE_APPEND | LOCK_EX);
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
                if (isset($physicalByDonorId[$donorId])) {
                    $phys = $physicalByDonorId[$donorId];
                    if (is_array($phys)) {
                        $physNeeds = $phys['needs_review'] ?? null;
                        $remarks = $phys['remarks'] ?? '';
                        // Physical exam is approved if it doesn't need review and has positive remarks
                        $isPhysicalExamApproved = ($physNeeds !== true) && 
                            !empty($remarks) && 
                            !in_array($remarks, ['Pending', 'Temporarily Deferred', 'Permanently Deferred', 'Declined', 'Refused']);
                    }
                }
                
                // Apply new workflow logic
                if ($isMedicalHistoryCompleted && $isScreeningPassed && $isPhysicalExamApproved) {
                    // All phases complete -> Pending (Collection)
                    $statusLabel = 'Pending (Collection)';
                } else if ($isMedicalHistoryCompleted && $isScreeningPassed) {
                    // Interviewer phase complete, physician phase pending -> Pending (Examination)
                    $statusLabel = 'Pending (Examination)';
                } else {
                    // Interviewer phase pending -> Pending (Screening)
                    $statusLabel = 'Pending (Screening)';
                }
                
                // Debug logging for specific donor IDs - ALL VISIBLE DONORS
                if (in_array($donorId, [195, 200, 211, 1887, 2514, 3203, 3204, 3205, 3208, 781])) {
                    error_log("DEBUG - Donor $donorId: MH Completed = " . var_export($isMedicalHistoryCompleted, true) . 
                             ", Screening Passed = " . var_export($isScreeningPassed, true) . 
                             ", PE Approved = " . var_export($isPhysicalExamApproved, true) . 
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