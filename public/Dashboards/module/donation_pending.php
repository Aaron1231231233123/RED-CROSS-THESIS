<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Test debug logging
error_log("DEBUG - donation_pending.php module loaded at " . date('Y-m-d H:i:s'));

// Also write to a custom log file for easier debugging
file_put_contents('../../assets/logs/donation_pending_debug.log', "[" . date('Y-m-d H:i:s') . "] donation_pending.php module loaded\n", FILE_APPEND | LOCK_EX);

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
    
    // OPTIMIZATION 2: Fetch related process tables with status fields for accurate status determination
    // Include status fields from each stage to determine accurate pending status
    $screeningResponse = supabaseRequest("screening_form?select=screening_id,donor_form_id,needs_review,disapproval_reason,created_at");
    $medicalResponse   = supabaseRequest("medical_history?select=donor_id,needs_review,medical_approval,is_admin,updated_at");
    $physicalResponse  = supabaseRequest("physical_examination?select=physical_exam_id,donor_id,needs_review,remarks,created_at");
    
    // Fetch blood collection data with proper linking
    $collectionResponse = supabaseRequest("blood_collection?select=physical_exam_id,needs_review,status,start_time,created_at&order=created_at.desc");

    // Build lookup maps with status fields
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
    $physicalExamIdByDonorId = []; // Add mapping for physical_exam_id
    if (isset($physicalResponse['data']) && is_array($physicalResponse['data'])) {
        foreach ($physicalResponse['data'] as $row) {
            if (!empty($row['donor_id'])) {
                $physicalByDonorId[$row['donor_id']] = [
                    'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                    'remarks' => $row['remarks'] ?? null
                ];
                // Also store physical_exam_id for blood collection lookup
                if (!empty($row['physical_exam_id'])) {
                    $physicalExamIdByDonorId[$row['donor_id']] = $row['physical_exam_id'];
                }
            }
        }
        error_log("DEBUG - Fetched " . count($physicalExamIdByDonorId) . " physical exam records");
        // Log some sample physical exam records for debugging
        $sampleCount = 0;
        foreach ($physicalExamIdByDonorId as $donorId => $examId) {
            if ($sampleCount < 3) {
                error_log("DEBUG - Sample physical exam: donorId=$donorId, examId=$examId");
                $sampleCount++;
            }
        }
    } else {
        error_log("DEBUG - No physical exam data found or invalid response");
    }
    
    $collectionByPhysicalExamId = [];
    if (isset($collectionResponse['data']) && is_array($collectionResponse['data'])) {
        foreach ($collectionResponse['data'] as $row) {
            if (!empty($row['physical_exam_id'])) {
                // Store the most recent record for each physical_exam_id (since we ordered by created_at.desc)
                if (!isset($collectionByPhysicalExamId[$row['physical_exam_id']])) {
                    $collectionByPhysicalExamId[$row['physical_exam_id']] = [
                        'needs_review' => isset($row['needs_review']) ? (bool)$row['needs_review'] : null,
                        'status' => $row['status'] ?? null,
                        'created_at' => $row['created_at'] ?? null
                    ];
                }
            }
        }
        error_log("DEBUG - Fetched " . count($collectionByPhysicalExamId) . " blood collection records");
        // Log some sample blood collection records for debugging
        $sampleCount = 0;
        foreach ($collectionByPhysicalExamId as $examId => $data) {
            if ($sampleCount < 3) {
                error_log("DEBUG - Sample blood collection: examId=$examId, status=" . ($data['status'] ?? 'null') . ", needs_review=" . ($data['needs_review'] ? 'true' : 'false'));
                $sampleCount++;
            }
        }
    } else {
        error_log("DEBUG - No blood collection data found or invalid response");
    }

    // OPTIMIZATION 3: Get donor_form data with limit/offset for pagination
    $limit = isset($GLOBALS['DONATION_LIMIT']) ? intval($GLOBALS['DONATION_LIMIT']) : 100;
    $offset = isset($GLOBALS['DONATION_OFFSET']) ? intval($GLOBALS['DONATION_OFFSET']) : 0;
    error_log("DEBUG - Fetching donors with limit=$limit, offset=$offset");
    $donorResponse = supabaseRequest("donor_form?order=submitted_at.desc&limit={$limit}&offset={$offset}");
    
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
                // PRIORITY CHECK: Look for ANY decline/deferral status anywhere in the workflow
                $hasDeclineDeferStatus = false;
                $declineDeferType = '';
                
                // 1. Check eligibility table first (most authoritative)
                $eligibilityCurl = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donorId . '&order=created_at.desc&limit=1');
                curl_setopt($eligibilityCurl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($eligibilityCurl, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json'
                ]);
                $eligibilityResponse = curl_exec($eligibilityCurl);
                $eligibilityHttpCode = curl_getinfo($eligibilityCurl, CURLINFO_HTTP_CODE);
                curl_close($eligibilityCurl);
                
                if ($eligibilityHttpCode === 200) {
                    $eligibilityData = json_decode($eligibilityResponse, true) ?: [];
                    if (!empty($eligibilityData)) {
                        $eligibilityStatus = strtolower($eligibilityData[0]['status'] ?? '');

                        // Check for decline/deferral in eligibility table
                        if (in_array($eligibilityStatus, ['declined', 'refused'])) {
                            $hasDeclineDeferStatus = true;
                            $declineDeferType = 'Declined';
                        } elseif (in_array($eligibilityStatus, ['deferred', 'ineligible'])) {
                            $hasDeclineDeferStatus = true;
                            $declineDeferType = 'Deferred';
                        } elseif (in_array($eligibilityStatus, ['approved', 'eligible'])) {
                            // Donor has final approved status, should not appear in pending
                            if (in_array($donorId, [176, 169, 170, 144, 140, 142, 135, 120])) {
                                error_log("DEBUG - Donor $donorId: Skipped due to eligibility status: '$eligibilityStatus'");
                            }
                            continue;
                        }
                    }
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
                    }                    continue; // Skip this donor - they have a final decline/deferral status
                }                // Based on user specifications: Interviewer role as primary checking, needs_review as fallback
                
                // 0. Determine completion for Medical History and Initial Screening first.
                // New donors (no records yet) should remain "Pending (Screening)" by default.
                $medicalHistoryCompleted = false;
                $screeningCompleted = false;
                
                // Medical History completion: Approved or Not Approved AND does not need review
                if (isset($medicalByDonorId[$donorId])) {
                    $medRecordForCompletion = $medicalByDonorId[$donorId];
                    if (is_array($medRecordForCompletion)) {
                        $medicalApprovalVal = $medRecordForCompletion['medical_approval'] ?? '';
                        $medNeedsVal = $medRecordForCompletion['needs_review'] ?? null;
                        if (in_array($medicalApprovalVal, ['Approved', 'Not Approved'], true) && $medNeedsVal !== true) {
                            $medicalHistoryCompleted = true;
                        }
                    }
                }
                
                // Initial Screening completion: no needs_review and no disapproval_reason
                if (isset($screeningByDonorId[$donorId])) {
                    $screenForCompletion = $screeningByDonorId[$donorId];
                    if (is_array($screenForCompletion)) {
                        $screenNeedsVal = $screenForCompletion['needs_review'] ?? null;
                        $disapprovalReasonVal = $screenForCompletion['disapproval_reason'] ?? '';
                        if ($screenNeedsVal !== true && empty($disapprovalReasonVal)) {
                            $screeningCompleted = true;
                        }
                    }
                }
                
                // Gate downstream stage checks to only run when both early stages are completed
                $shouldCheckDownstreamStages = ($medicalHistoryCompleted && $screeningCompleted);
                
                // NEW WORKFLOW: Check interviewer phase completion first
                $isMedicalHistoryCompleted = false;
                $isScreeningPassed = false;
                $isPhysicalExamApproved = false;
                
                // Check Medical History completion
                if (isset($medicalByDonorId[$donorId])) {
                    $medRecord = $medicalByDonorId[$donorId];
                    if (is_array($medRecord)) {
                        $medNeeds = $medRecord['needs_review'] ?? null;
                        $isAdmin = $medRecord['is_admin'] ?? null;
                        // Medical History is completed if:
                        // 1. Admin side: is_admin is TRUE, OR
                        // 2. Staff side: needs_review is false (completed by staff)
                        $isMedicalHistoryCompleted = ($isAdmin === true || $isAdmin === 'true' || $isAdmin === 'True') || 
                                                    ($medNeeds === false);
                        
                        // Debug logging for donor 189
                        if ($donorId == 189) {
                            error_log("DEBUG - Donor 189 Medical History: needs_review = " . ($medNeeds ? 'true' : 'false') . 
                                     ", is_admin = " . ($isAdmin ? 'true' : 'false') . ", completed = " . ($isMedicalHistoryCompleted ? 'true' : 'false'));
                        }
                    }
                } else {
                    // Debug logging for donor 189
                    if ($donorId == 189) {
                        error_log("DEBUG - Donor 189: No medical history record found");
                    }
                }
                
                // Check Initial Screening completion
                if (isset($screeningByDonorId[$donorId])) {
                    $screen = $screeningByDonorId[$donorId];
                    if (is_array($screen)) {
                        $screenNeeds = $screen['needs_review'] ?? null;
                        $disapprovalReason = $screen['disapproval_reason'] ?? '';
                        // Screening is passed if it doesn't need review and has no disapproval reason
                        $isScreeningPassed = ($screenNeeds !== true) && empty($disapprovalReason);
                        
                        // Debug logging for donor 189
                        if ($donorId == 189) {
                            error_log("DEBUG - Donor 189 Screening: needs_review = " . ($screenNeeds ? 'true' : 'false') . 
                                     ", disapproval_reason = '$disapprovalReason', passed = " . ($isScreeningPassed ? 'true' : 'false'));
                        }
                    }
                } else {
                    // Debug logging for donor 189
                    if ($donorId == 189) {
                        error_log("DEBUG - Donor 189: No screening record found");
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
                
                // Debug logging for specific donor IDs
                if (in_array($donorId, [176, 169, 170, 144, 140, 142, 135, 120, 182, 189])) {
                    error_log("DEBUG - Donor $donorId: MH Completed = " . ($isMedicalHistoryCompleted ? 'true' : 'false') . 
                             ", Screening Passed = " . ($isScreeningPassed ? 'true' : 'false') . 
                             ", PE Approved = " . ($isPhysicalExamApproved ? 'true' : 'false') . 
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

        // Enforce FIFO: oldest first
        usort($pendingDonations, function($a, $b) {
            $ta = $a['sort_ts'] ?? 0; $tb = $b['sort_ts'] ?? 0;
            if ($ta === $tb) return 0;
            return ($ta < $tb) ? -1 : 1;
        });
        
        // Apply pagination for display (20 items per page)
        $displayLimit = 20;
        $displayOffset = isset($GLOBALS['DONATION_DISPLAY_OFFSET']) ? intval($GLOBALS['DONATION_DISPLAY_OFFSET']) : 0;
        $pendingDonations = array_slice($pendingDonations, $displayOffset, $displayLimit);
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

// OPTIMIZATION: Shared utilities already included at the top of the file 