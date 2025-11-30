<?php
// Include database connection
include_once '../conn/db_conn.php';

// Check if required parameters are provided
if (!isset($_GET['donor_id'])) {
    echo json_encode(['error' => 'Missing donor_id parameter']);
    exit;
}

$donor_id = $_GET['donor_id'];
$eligibility_id = $_GET['eligibility_id'] ?? null;

// Debug log
error_log("Fetching donor details for donor_id: $donor_id, eligibility_id: $eligibility_id");

// Function to fetch donor information
function fetchDonorInfo($donorId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donorId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        error_log("cURL Error fetching donor info: " . $err);
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No donor data found for ID: $donorId");
        }
        return !empty($data) ? $data[0] : null;
    }
}

// Function to fetch medical history data
function fetchMedicalHistoryData($donorId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/medical_history?donor_id=eq." . $donorId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        error_log("cURL Error fetching medical history data: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        return !empty($data) ? $data[0] : null;
    }
}

// Function to fetch physical examination data (for declined donors)
function fetchPhysicalExamData($physicalExamId) {
    error_log("Fetching physical exam data for ID: $physicalExamId");
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/physical_examination?physical_exam_id=eq." . $physicalExamId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        error_log("cURL Error fetching physical exam data: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No physical exam data found for ID: $physicalExamId");
            return null;
        }
        error_log("Successfully retrieved physical exam data for ID: $physicalExamId");
        return !empty($data) ? $data[0] : null;
    }
}

// Function to fetch screening data by donor ID
function fetchScreeningDataByDonorId($donorId) {
    error_log("Fetching screening data for donor_id: $donorId");
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donorId . "&order=created_at.desc&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        error_log("cURL Error fetching screening data by donor ID: " . $err);
        return null;
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No screening data found for donor ID: $donorId");
            return null;
        }
        error_log("Successfully retrieved screening data for donor ID: $donorId");
        return !empty($data) ? $data[0] : null;
    }
}

// Function to fetch eligibility record
function fetchEligibilityRecord($eligibilityId) {
    global $donor_id;
    error_log("Processing eligibility record: $eligibilityId");

    // If eligibility_id starts with "pending_", check if there's an approved eligibility record first
    if ($eligibilityId && strpos($eligibilityId, 'pending_') === 0) {
        error_log("Handling pending eligibility record - checking for approved eligibility first");
        
        // First, check if there's an approved eligibility record for this donor
        try {
            $ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . urlencode((string)$donor_id) . '&status=eq.approved&order=created_at.desc&limit=1');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http === 200 && $resp) {
                $arr = json_decode($resp, true) ?: [];
                if (!empty($arr) && isset($arr[0]['eligibility_id'])) {
                    // Found an approved eligibility record - use it instead of pending
                    error_log("Found approved eligibility record: " . $arr[0]['eligibility_id'] . " - using it instead of pending");
                    $eligibilityId = $arr[0]['eligibility_id'];
                    // Continue to fetch the actual eligibility record below
                } else {
                    // No approved record found, continue with pending logic
                    error_log("No approved eligibility record found, using pending status");
                }
            }
        } catch (Exception $e) {
            error_log("Error checking for approved eligibility: " . $e->getMessage());
            // Continue with pending logic on error
        }
        
        // If still pending (no approved record found), return pending status
        if (strpos($eligibilityId, 'pending_') === 0) {
            error_log("Handling pending eligibility record");
            // Try to derive blood type from latest screening form
            $derivedBloodType = 'Pending';
            try {
                $screeningData = fetchScreeningDataByDonorId($donor_id);
                if (!empty($screeningData['blood_type'])) {
                    $derivedBloodType = $screeningData['blood_type'];
                }
            } catch (Exception $e) {
                // ignore, keep Pending
            }
            return [
                'eligibility_id' => $eligibilityId,
                'status' => 'pending',
                'blood_type' => $derivedBloodType,
                'donation_type' => 'Pending',
                'start_date' => date('Y-m-d'),
                'end_date' => null,
                'is_pending' => true,
                // Explicitly set these fields to null for pending donors
                'blood_bag_type' => null,
                'amount_collected' => null, 
                'donor_reaction' => null,
                'management_done' => null
            ];
        }
    }
    
    // If eligibility_id starts with "declined_", this is a declined donor from physical examination
    if ($eligibilityId && strpos($eligibilityId, 'declined_') === 0) {
        error_log("Handling declined eligibility record");
        
        // Extract the physical exam ID from the format "declined_[physical_exam_id]"
        $physicalExamId = substr($eligibilityId, strlen('declined_'));
        error_log("Extracted physical exam ID: $physicalExamId");
        
        $bloodType = "Unknown";
        $donationType = "Unknown";
        
        // First try to get the physical exam record to get more details
        if (is_numeric($physicalExamId)) {
            $physicalExamData = fetchPhysicalExamData($physicalExamId);
            
            // If we have physical exam data, extract what we can
            if ($physicalExamData) {
                error_log("Found physical exam data for ID: $physicalExamId");
                $remarks = $physicalExamData['remarks'] ?? '';
                $disapprovalReason = $physicalExamData['disapproval_reason'] ?? '';
                
                // Some physical exam records might have blood type or donation type
                if (!empty($physicalExamData['blood_type'])) {
                    $bloodType = $physicalExamData['blood_type'];
                    error_log("Found blood type from physical exam: $bloodType");
                }
                
                if (!empty($physicalExamData['donation_type'])) {
                    $donationType = $physicalExamData['donation_type'];
                    error_log("Found donation type from physical exam: $donationType");
                }
            }
        }
        
        // If we still don't have blood type or donation type, try looking in the screening form
        if ($bloodType === "Unknown" || $donationType === "Unknown") {
            $screeningData = fetchScreeningDataByDonorId($donor_id);
            
            if ($screeningData) {
                error_log("Found screening data for donor ID: $donor_id");
                
                if (!empty($screeningData['blood_type']) && $bloodType === "Unknown") {
                    $bloodType = $screeningData['blood_type'];
                    error_log("Found blood type from screening: $bloodType");
                }
                
                if (!empty($screeningData['donation_type']) && $donationType === "Unknown") {
                    $donationType = $screeningData['donation_type'];
                    error_log("Found donation type from screening: $donationType");
                }
            }
        }
        
        // Return the declined eligibility record with all available information
        error_log("Returning declined eligibility record with blood_type: $bloodType, donation_type: $donationType");
        return [
            'eligibility_id' => $eligibilityId,
            'status' => 'declined',
            'blood_type' => $bloodType,
            'donation_type' => $donationType,
            'start_date' => date('Y-m-d'),
            'end_date' => null,
            // Explicitly set these fields to null for declined donors
            'blood_bag_type' => null,
            'amount_collected' => null,
            'donor_reaction' => null,
            'management_done' => null
        ];
    }
    
    // If eligibility_id starts with "deferred_", this is a deferred donor from physical examination
    if ($eligibilityId && strpos($eligibilityId, 'deferred_') === 0) {
        error_log("Handling deferred eligibility record");
        
        // Extract the physical exam ID from the format "deferred_[physical_exam_id]"
        $physicalExamId = substr($eligibilityId, strlen('deferred_'));
        error_log("Extracted physical exam ID: $physicalExamId");
        
        $bloodType = "Unknown";
        $donationType = "Unknown";
        
        // First try to get the physical exam record to get more details
        if (is_numeric($physicalExamId)) {
            $physicalExamData = fetchPhysicalExamData($physicalExamId);
            
            // If we have physical exam data, extract what we can
            if ($physicalExamData) {
                error_log("Found physical exam data for ID: $physicalExamId");
                $remarks = $physicalExamData['remarks'] ?? '';
                $disapprovalReason = $physicalExamData['disapproval_reason'] ?? '';
                
                // Some physical exam records might have blood type or donation type
                if (!empty($physicalExamData['blood_type'])) {
                    $bloodType = $physicalExamData['blood_type'];
                    error_log("Found blood type from physical exam: $bloodType");
                }
                
                if (!empty($physicalExamData['donation_type'])) {
                    $donationType = $physicalExamData['donation_type'];
                    error_log("Found donation type from physical exam: $donationType");
                }
            }
        }
        
        // If we still don't have blood type or donation type, try looking in the screening form
        if ($bloodType === "Unknown" || $donationType === "Unknown") {
            $screeningData = fetchScreeningDataByDonorId($donor_id);
            
            if ($screeningData) {
                error_log("Found screening data for donor ID: $donor_id");
                
                if (!empty($screeningData['blood_type']) && $bloodType === "Unknown") {
                    $bloodType = $screeningData['blood_type'];
                    error_log("Found blood type from screening: $bloodType");
                }
                
                if (!empty($screeningData['donation_type']) && $donationType === "Unknown") {
                    $donationType = $screeningData['donation_type'];
                    error_log("Found donation type from screening: $donationType");
                }
            }
        }
        
        // Return the deferred eligibility record with all available information
        error_log("Returning deferred eligibility record with blood_type: $bloodType, donation_type: $donationType");
        return [
            'eligibility_id' => $eligibilityId,
            'status' => 'deferred',
            'blood_type' => $bloodType,
            'donation_type' => $donationType,
            'start_date' => date('Y-m-d'),
            'end_date' => null,
            // Explicitly set these fields to null for deferred donors
            'blood_bag_type' => null,
            'amount_collected' => null,
            'donor_reaction' => null,
            'management_done' => null
        ];
    }
    
    // Otherwise, fetch from the eligibility table
    error_log("Fetching from eligibility table for ID: $eligibilityId");
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?eligibility_id=eq." . $eligibilityId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        error_log("cURL Error fetching eligibility: " . $err);
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No eligibility data found for ID: $eligibilityId");
            // Return a default eligibility object for pending donors
            // Try to derive blood type from latest screening form
            $derivedBloodType = 'Pending';
            try {
                $screeningData = fetchScreeningDataByDonorId($donor_id);
                if (!empty($screeningData['blood_type'])) {
                    $derivedBloodType = $screeningData['blood_type'];
                }
            } catch (Exception $e) {
                // ignore, keep Pending
            }
            return [
                'eligibility_id' => $eligibilityId ?? 'pending_' . $donor_id,
                'status' => 'pending',
                'blood_type' => $derivedBloodType,
                'donation_type' => 'Pending',
                'start_date' => date('Y-m-d'),
                'end_date' => null,
                'is_pending' => true,
                // Explicitly set these fields to null for pending donors
                'blood_bag_type' => null,
                'amount_collected' => null, 
                'donor_reaction' => null,
                'management_done' => null
            ];
        }
        
        $eligibilityRecord = (!empty($data) && is_array($data) && isset($data[0])) ? $data[0] : null;
        
        // If there's a screening_id, fetch blood_type and donation_type from screening_form
        if (!empty($eligibilityRecord['screening_id'])) {
            $screeningData = fetchScreeningData($eligibilityRecord['screening_id']);
            
            if ($screeningData && !isset($screeningData['error'])) {
                // Override blood_type and donation_type with data from screening form if available
                if (!empty($screeningData['blood_type'])) {
                    $eligibilityRecord['blood_type'] = $screeningData['blood_type'];
                }
                
                if (!empty($screeningData['donation_type'])) {
                    $eligibilityRecord['donation_type'] = $screeningData['donation_type'];
                }
            }
        }
        
        return $eligibilityRecord;
    }
}

// Function to fetch screening form data
function fetchScreeningData($screeningId) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?screening_id=eq." . $screeningId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_API_KEY,
            "Authorization: Bearer " . SUPABASE_API_KEY,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        error_log("cURL Error fetching screening data: " . $err);
        return ["error" => "cURL Error #:" . $err];
    } else {
        $data = json_decode($response, true);
        if (empty($data)) {
            error_log("No screening data found for ID: $screeningId");
            return null;
        }
        return !empty($data) ? $data[0] : null;
    }
}

try {
    // OPTIMIZATION: Fetch donor info, medical history, and screening data in parallel using cURL multi-handle
    $mh = curl_multi_init();
    $handles = [];
    $results = [];
    
    // Create parallel requests for independent data
    $headers = [
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Content-Type: application/json"
    ];
    
    // Request 1: Donor info
    $ch1 = curl_init();
    curl_setopt_array($ch1, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=eq." . $donor_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    curl_multi_add_handle($mh, $ch1);
    $handles['donor'] = $ch1;
    
    // Request 2: Medical history
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/medical_history?donor_id=eq." . $donor_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    curl_multi_add_handle($mh, $ch2);
    $handles['medical'] = $ch2;
    
    // Request 3: Screening data
    $ch3 = curl_init();
    curl_setopt_array($ch3, [
        CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donor_id . "&order=created_at.desc&limit=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    curl_multi_add_handle($mh, $ch3);
    $handles['screening'] = $ch3;
    
    // Execute all requests in parallel
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);
    
    // Get results
    $donorInfo = null;
    $medicalHistoryData = null;
    $screeningLatest = null;
    
    foreach ($handles as $key => $ch) {
        $response = curl_multi_getcontent($ch);
        $err = curl_error($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        
        if ($err) {
            error_log("cURL Error for $key: " . $err);
            continue;
        }
        
        $data = json_decode($response, true);
        if ($key === 'donor') {
            $donorInfo = !empty($data) ? $data[0] : null;
        } elseif ($key === 'medical') {
            $medicalHistoryData = !empty($data) ? $data[0] : null;
        } elseif ($key === 'screening') {
            $screeningLatest = !empty($data) ? $data[0] : null;
        }
    }
    curl_multi_close($mh);
    
    if (!$donorInfo) {
        error_log("No donor information found for ID: $donor_id");
        echo json_encode(['error' => 'Donor information not found']);
        exit;
    }
    
    // Calculate age if birthdate is available
    if (!empty($donorInfo['birthdate'])) {
        $birthdate = new DateTime($donorInfo['birthdate']);
        $today = new DateTime();
        $donorInfo['age'] = $birthdate->diff($today)->y;
    } else {
        $donorInfo['age'] = 'N/A';
    }
    
    // Fetch eligibility info (this may need to be sequential due to complex logic)
    error_log("Fetching eligibility record with ID: $eligibility_id");
    $eligibilityInfo = fetchEligibilityRecord($eligibility_id);
    
    if (isset($eligibilityInfo['error'])) {
        error_log("Error fetching eligibility: " . $eligibilityInfo['error']);
    } else {
        error_log("Successfully fetched eligibility record: " . json_encode(['status' => $eligibilityInfo['status'] ?? 'unknown']));
    }
    
    // Derive interviewer/physician status fields for UI if missing
    $derived = $eligibilityInfo;

    $statusLower = strtolower($eligibilityInfo['status'] ?? '');
    $hasScreening = !empty($eligibilityInfo['screening_id']) || !empty($screeningLatest);

    // ROOT CAUSE FIX: Interviewer statuses - properly detect decline/deferral at each stage
    // Medical History Status - check medical_history_data directly for accurate status 
    if (!isset($derived['medical_history_status'])) {
        $medicalHistoryStatus = 'Pending';
        // FALLBACK: Also check medical_history_data if available (more reliable than eligibility status)
        if ($medicalHistoryData) {
            $medicalApproval = isset($medicalHistoryData['medical_approval']) ? trim((string)$medicalHistoryData['medical_approval']) : '';
            if (!empty($medicalApproval)) {
                if (strcasecmp($medicalApproval, 'Approved') === 0) {
                    $medicalHistoryStatus = 'Completed';
                } elseif (strcasecmp($medicalApproval, 'Not Approved') === 0) {
                    // ROOT CAUSE FIX: Show decline status for medical history
                    $medicalHistoryStatus = 'Declined/Not Approved';
                } else {
                    $medicalHistoryStatus = 'Completed'; // Completed but not yet approved
                }
            } else {
                // Medical history exists but no approval status yet - check if it's completed
                $medicalHistoryStatus = ($statusLower === 'approved' || $statusLower === 'eligible' || $statusLower === 'declined' || $hasScreening)
                    ? 'Completed' : 'Pending';
            }
        } else {
            // No medical history record
            $medicalHistoryStatus = 'Pending';
        }
        $derived['medical_history_status'] = $medicalHistoryStatus;
    } else {
        // FALLBACK: If medical_history_status is set but medical_history_data shows "Not Approved", override it
        if ($medicalHistoryData) {
            $medicalApproval = isset($medicalHistoryData['medical_approval']) ? trim((string)$medicalHistoryData['medical_approval']) : '';
            if (strcasecmp($medicalApproval, 'Not Approved') === 0) {
                $derived['medical_history_status'] = 'Declined/Not Approved';
            }
        }
    }
    
    // Initial Screening Status
    if (!isset($derived['screening_status'])) {
        $screeningStatus = 'Pending';
        if ($hasScreening && $screeningLatest) {
            // ROOT CAUSE FIX: Check if there's a disapproval reason - this indicates decline
            if (!empty($screeningLatest['disapproval_reason']) && trim($screeningLatest['disapproval_reason']) !== '') {
                $screeningStatus = 'Declined/Not Approved';
            } else {
                $screeningStatus = 'Passed';
            }
        }
        $derived['screening_status'] = $screeningStatus;
    }

    // OPTIMIZATION: Helper function to fetch physical examination remarks (consolidates duplicate code)
    $fetchPhysicalRemarks = function($physicalExamId = null, $useDonorId = false) use ($donor_id) {
        $headers = [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ];
        
        $url = $useDonorId 
            ? SUPABASE_URL . '/rest/v1/physical_examination?select=remarks&donor_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1'
            : SUPABASE_URL . '/rest/v1/physical_examination?select=remarks&physical_exam_id=eq.' . urlencode($physicalExamId);
        
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http === 200) {
                $arr = json_decode($resp, true);
                if (!empty($arr) && isset($arr[0]['remarks'])) {
                    return $arr[0]['remarks'];
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching physical examination remarks: " . $e->getMessage());
        }
        return null;
    };
    
    // Helper function to convert remarks to status
    $remarksToStatus = function($remarks) {
        if (empty($remarks) || trim($remarks) === '' || $remarks === 'Pending') {
            return 'Pending';
        }
        if ($remarks === 'Accepted') return 'Accepted';
        if ($remarks === 'Temporarily Deferred') return 'Temporarily Deferred';
        if ($remarks === 'Permanently Deferred') return 'Permanently Deferred';
        if ($remarks === 'Refused') return 'Refused';
        return 'Declined/Not Approved';
    };
    
    // Physician statuses - check if physical examination is completed based on remarks
    if (!isset($derived['review_status']) || !isset($derived['physical_status'])) {
        $physicalStatus = 'Pending';
        $remarks = null;
        
        // Check if this is a deferred donor from the declined module
        if (isset($eligibilityInfo['status']) && $eligibilityInfo['status'] === 'deferred') {
            // For deferred donors, get the physical exam ID from the eligibility_id
            if (isset($eligibilityInfo['eligibility_id']) && strpos($eligibilityInfo['eligibility_id'], 'deferred_') === 0) {
                $physicalExamId = substr($eligibilityInfo['eligibility_id'], strlen('deferred_'));
                $remarks = $fetchPhysicalRemarks($physicalExamId);
                if ($remarks) {
                    $physicalStatus = $remarks; // Use actual remarks for deferred
                    error_log("Deferred donor physical status: " . $physicalStatus);
                }
            }
        } else {
            // Regular physical examination status checking
            // First try by physical_exam_id if available
            if (isset($derived['physical_exam_id']) && !empty($derived['physical_exam_id'])) {
                $remarks = $fetchPhysicalRemarks($derived['physical_exam_id']);
                if ($remarks) {
                    $physicalStatus = $remarksToStatus($remarks);
                    error_log("Physical examination check - Physical Exam ID: " . $derived['physical_exam_id'] . ", Remarks: " . $remarks . ", Status: " . $physicalStatus);
                }
            }
            
            // If not found, try by donor_id
            if (!$remarks) {
                $remarks = $fetchPhysicalRemarks(null, true);
                if ($remarks) {
                    $physicalStatus = $remarksToStatus($remarks);
                    error_log("Physical examination check by donor_id - Donor ID: " . $donor_id . ", Remarks: " . $remarks . ", Status: " . $physicalStatus);
                }
            }
        }
        
        if (!isset($derived['review_status'])) {
            $derived['review_status'] = $physicalStatus;
        }
        if (!isset($derived['physical_status'])) {
            $derived['physical_status'] = $physicalStatus;
        }
    }

    // Phlebotomist status: attempt to compute from blood_collection
    $collectionStatusRaw = isset($derived['collection_status']) ? trim((string)$derived['collection_status']) : '';
    $shouldRecalculateCollection = (
        $collectionStatusRaw === '' ||
        $collectionStatusRaw === '-' ||
        strcasecmp($collectionStatusRaw, 'pending') === 0 ||
        strcasecmp($collectionStatusRaw, 'pending (collection)') === 0 ||
        strcasecmp($collectionStatusRaw, 'yet to be collected') === 0 ||
        strcasecmp($collectionStatusRaw, 'pending blood collection') === 0
    );

    $collectionDetails = null;
    $collection_status = 'Pending'; // Initialize default

    // OPTIMIZATION: Fetch physical examination data and blood collection in parallel (if needed)
    $physicalExamData = null;
    $needsPhysicalExamData = true;
    $needsBloodCollection = (!isset($derived['collection_status']) || $shouldRecalculateCollection) && !$collectionDetails;
    
    // If we already have physical_exam_id, we can fetch physical exam data in parallel with blood collection
    if ($needsPhysicalExamData || $needsBloodCollection) {
        $mh2 = curl_multi_init();
        $handles2 = [];
        
        // Fetch physical examination data
        if ($needsPhysicalExamData) {
            if (isset($derived['physical_exam_id']) && !empty($derived['physical_exam_id'])) {
                $ch_pe = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . urlencode($derived['physical_exam_id']));
            } else {
                $ch_pe = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1');
            }
            curl_setopt($ch_pe, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_pe, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]);
            curl_multi_add_handle($mh2, $ch_pe);
            $handles2['physical'] = $ch_pe;
        }
        
        // Fetch blood collection if needed and we have identifiers
        if ($needsBloodCollection && !$collectionDetails) {
            $blood_collection_id = $derived['blood_collection_id'] ?? null;
            $screening_id = $derived['screening_id'] ?? null;
            $physical_exam_id = $derived['physical_exam_id'] ?? null;
            
            // If we don't have physical_exam_id from eligibility, fetch it first (quick query)
            if (!$physical_exam_id) {
                try {
                    $ch_pe_id = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1');
                    curl_setopt($ch_pe_id, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_pe_id, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Accept: application/json'
                    ]);
                    $resp_pe_id = curl_exec($ch_pe_id);
                    $http_pe_id = curl_getinfo($ch_pe_id, CURLINFO_HTTP_CODE);
                    curl_close($ch_pe_id);
                    if ($http_pe_id === 200) {
                        $arr_pe_id = json_decode($resp_pe_id, true);
                        if (!empty($arr_pe_id) && isset($arr_pe_id[0]['physical_exam_id'])) {
                            $physical_exam_id = $arr_pe_id[0]['physical_exam_id'];
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error fetching physical_exam_id by donor_id: " . $e->getMessage());
                }
            }
            
            // Create blood collection request based on available identifiers
            if ($blood_collection_id) {
                $ch_bc = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=is_successful,needs_review,status&blood_collection_id=eq.' . urlencode($blood_collection_id) . '&limit=1');
            } elseif ($physical_exam_id) {
                $ch_bc = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=is_successful,needs_review,status&physical_exam_id=eq.' . urlencode($physical_exam_id) . '&order=created_at.desc&limit=1');
            } elseif ($screening_id) {
                $ch_bc = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=is_successful,needs_review,status&screening_id=eq.' . urlencode($screening_id) . '&order=created_at.desc&limit=1');
            } else {
                $ch_bc = null;
            }
            
            if ($ch_bc) {
                curl_setopt($ch_bc, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_bc, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]);
                curl_multi_add_handle($mh2, $ch_bc);
                $handles2['blood_collection'] = $ch_bc;
            }
        }
        
        // Execute parallel requests
        if (!empty($handles2)) {
            $running2 = null;
            do {
                curl_multi_exec($mh2, $running2);
                curl_multi_select($mh2);
            } while ($running2 > 0);
            
            // Get results
            foreach ($handles2 as $key => $ch) {
                $response = curl_multi_getcontent($ch);
                $err = curl_error($ch);
                curl_multi_remove_handle($mh2, $ch);
                curl_close($ch);
                
                if ($err) {
                    error_log("cURL Error for $key: " . $err);
                    continue;
                }
                
                $data = json_decode($response, true);
                if ($key === 'physical' && !empty($data)) {
                    $physicalExamData = $data[0];
                } elseif ($key === 'blood_collection' && !empty($data)) {
                    $collectionDetails = $data[0];
                    if (isset($data[0]['is_successful'])) {
                        $collection_status = $data[0]['is_successful'] ? 'Successful' : 'Declined/Not Approved';
                        error_log("Donor Details API - Collection status set to: $collection_status (from is_successful: " . ($data[0]['is_successful'] ? 'true' : 'false') . ")");
                    } elseif (isset($data[0]['needs_review']) && $data[0]['needs_review'] === true) {
                        $collection_status = 'Pending';
                        error_log("Donor Details API - Collection status set to Pending (needs_review: true)");
                    } elseif (isset($data[0]['status'])) {
                        $statusLower = strtolower(trim($data[0]['status']));
                        if ($statusLower === 'successful') {
                            $collection_status = 'Successful';
                            error_log("Donor Details API - Collection status set to Successful (from status field)");
                        }
                    }
                    if (!isset($derived['collection_status']) || $shouldRecalculateCollection) {
                        $derived['collection_status'] = $collection_status ?? 'Pending';
                        error_log("Donor Details API - Final collection_status for donor $donor_id: " . $derived['collection_status']);
                    }
                }
            }
            curl_multi_close($mh2);
        }
    }

    // Return data as JSON
    echo json_encode([
        'donor' => $donorInfo,
        'eligibility' => $derived,
        'medical_history' => $medicalHistoryData,
        'physical_examination' => $physicalExamData,
        'screening_form' => $screeningLatest,
        'blood_collection' => $collectionDetails
    ]);

} catch (Exception $e) {
    error_log("Error in donor_details_api.php: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while processing your request: ' . $e->getMessage()]);
} 