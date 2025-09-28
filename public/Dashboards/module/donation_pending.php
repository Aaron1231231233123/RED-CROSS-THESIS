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
                    continue; // Skip this donor - they have a final decline/deferral status
                }
                
                // ACCURATE STATUS DETERMINATION BASED ON SECTION COMPLETION
                // Use the same logic as the donor information modal for consistency
                
                // Check Interviewer Section (Medical History + Initial Screening)
                $interviewerSectionCompleted = false;
                $medicalHistoryCompleted = false;
                $screeningCompleted = false;
                
                // Check Medical History completion
                if (isset($medicalByDonorId[$donorId])) {
                    $medRecord = $medicalByDonorId[$donorId];
                    if (is_array($medRecord)) {
                        $medicalApproval = $medRecord['medical_approval'] ?? '';
                        $medNeeds = $medRecord['needs_review'] ?? null;
                        // Medical History is completed if it has approval status and doesn't need review
                        if (in_array($medicalApproval, ['Approved', 'Not Approved']) && $medNeeds !== true) {
                            $medicalHistoryCompleted = true;
                        }
                    }
                }
                
                // Check Initial Screening completion
                if (isset($screeningByDonorId[$donorId])) {
                    $screen = $screeningByDonorId[$donorId];
                    if (is_array($screen)) {
                        $screenNeeds = $screen['needs_review'] ?? null;
                        $disapprovalReason = $screen['disapproval_reason'] ?? '';
                        // Screening is completed if it doesn't need review and has no disapproval reason
                        if ($screenNeeds !== true && empty($disapprovalReason)) {
                            $screeningCompleted = true;
                        }
                    }
                }
                
                // Interviewer section is completed if both MH and Screening are completed
                $interviewerSectionCompleted = $medicalHistoryCompleted && $screeningCompleted;
                
                // Check Physician Section (Medical History + Physical Examination)
                $physicianSectionCompleted = false;
                $physicalExaminationCompleted = false;
                
                // Check Physical Examination completion
                if (isset($physicalByDonorId[$donorId])) {
                    $phys = $physicalByDonorId[$donorId];
                    if (is_array($phys)) {
                        $physNeeds = $phys['needs_review'] ?? null;
                        $remarks = $phys['remarks'] ?? '';
                        // Physical examination is completed if it doesn't need review and has valid remarks
                        if ($physNeeds !== true && !empty($remarks) && 
                            !in_array($remarks, ['Pending', 'Temporarily Deferred', 'Permanently Deferred', 'Declined', 'Refused'])) {
                            $physicalExaminationCompleted = true;
                        }
                    }
                }
                
                // Physician section is completed if both MH and Physical Examination are completed
                $physicianSectionCompleted = $medicalHistoryCompleted && $physicalExaminationCompleted;
                
                // Determine status based on section completion
                if (!$interviewerSectionCompleted) {
                    // Interviewer section not completed - MH and/or Initial Screening pending
                    $statusLabel = 'Pending (Screening)';
                } elseif (!$physicianSectionCompleted) {
                    // Interviewer section completed but Physician section not completed - MH and/or Physical Examination pending
                    $statusLabel = 'Pending (Examination)';
                } else {
                    // Both sections completed - ready for collection
                    $statusLabel = 'Pending (Collection)';
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