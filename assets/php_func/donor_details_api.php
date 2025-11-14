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
    // Fetch data
    $donorInfo = fetchDonorInfo($donor_id);
    $medicalHistoryData = fetchMedicalHistoryData($donor_id);
    
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
    
    // Fetch eligibility info
    error_log("Fetching eligibility record with ID: $eligibility_id");
    $eligibilityInfo = fetchEligibilityRecord($eligibility_id);
    
    if (isset($eligibilityInfo['error'])) {
        error_log("Error fetching eligibility: " . $eligibilityInfo['error']);
    } else {
        error_log("Successfully fetched eligibility record: " . json_encode(['status' => $eligibilityInfo['status'] ?? 'unknown']));
    }
    
    // Derive interviewer/physician status fields for UI if missing
    $derived = $eligibilityInfo;
    $screeningLatest = null;
    // Try to determine screening status using donor_id if possible
    try {
        $screeningLatest = fetchScreeningDataByDonorId($donor_id);
    } catch (Exception $e) {
        $screeningLatest = null;
    }

    $statusLower = strtolower($eligibilityInfo['status'] ?? '');
    $hasScreening = !empty($eligibilityInfo['screening_id']) || !empty($screeningLatest);

    // Interviewer statuses
    if (!isset($derived['medical_history_status'])) {
        // Assume medical history completed if donor reached or passed screening/physical phases
        $derived['medical_history_status'] = ($statusLower === 'approved' || $statusLower === 'eligible' || $statusLower === 'declined' || $hasScreening)
            ? 'Completed' : 'Pending';
    }
    if (!isset($derived['screening_status'])) {
        // Check screening status based on disapproval_reason column
        $screeningStatus = 'Pending';
        if ($hasScreening && $screeningLatest) {
            // Check if there's a disapproval reason
            if (!empty($screeningLatest['disapproval_reason']) && trim($screeningLatest['disapproval_reason']) !== '') {
                $screeningStatus = 'Declined/Not Approved';
            } else {
                $screeningStatus = 'Passed';
            }
        }
        $derived['screening_status'] = $screeningStatus;
    }

    // Physician statuses - check if physical examination is completed based on remarks
    if (!isset($derived['review_status'])) {
        // Check if this is a deferred donor from the declined module
        if (isset($eligibilityInfo['status']) && $eligibilityInfo['status'] === 'deferred') {
            // For deferred donors, we need to get the actual remarks from the physical examination
            $physicalStatus = 'Pending';
            $hasPhysicalData = false;
            
            // Try to get the physical exam ID from the eligibility_id
            if (isset($eligibilityInfo['eligibility_id']) && strpos($eligibilityInfo['eligibility_id'], 'deferred_') === 0) {
                $physicalExamId = substr($eligibilityInfo['eligibility_id'], strlen('deferred_'));
                
                try {
                    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=remarks&physical_exam_id=eq.' . urlencode($physicalExamId));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Accept: application/json'
                    ]);
                    $resp = curl_exec($ch);
                    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($http === 200) {
                        $arr = json_decode($resp, true);
                        if (!empty($arr) && isset($arr[0]['remarks'])) {
                            $remarks = $arr[0]['remarks'];
                            $hasPhysicalData = true;
                            // Use the actual remarks value for deferred donors
                            $physicalStatus = $remarks;
                            error_log("Deferred donor physical status: " . $physicalStatus);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error checking deferred donor physical examination remarks: " . $e->getMessage());
                }
            }
            
            $derived['review_status'] = $physicalStatus;
            $derived['physical_status'] = $physicalStatus;
        } else {
            // Regular physical examination status checking
            $physicalStatus = 'Pending';
            $hasPhysicalData = false;
            
            // First try to find by physical_exam_id if available
        if (isset($derived['physical_exam_id']) && !empty($derived['physical_exam_id'])) {
            try {
                $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=remarks&physical_exam_id=eq.' . urlencode($derived['physical_exam_id']));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http === 200) {
                    $arr = json_decode($resp, true);
                    if (!empty($arr) && isset($arr[0]['remarks'])) {
                        $remarks = $arr[0]['remarks'];
                        $hasPhysicalData = true;
                        // Check specific enum values for physical examination status
                        if ($remarks === 'Accepted') {
                            $physicalStatus = 'Accepted';
                        } elseif ($remarks === 'Temporarily Deferred') {
                            $physicalStatus = 'Temporarily Deferred';
                        } elseif ($remarks === 'Permanently Deferred') {
                            $physicalStatus = 'Permanently Deferred';
                        } elseif ($remarks === 'Refused') {
                            $physicalStatus = 'Refused';
                        } elseif (!empty($remarks) && trim($remarks) !== '' && $remarks !== 'Pending') {
                            // Fallback for any other non-empty values
                            $physicalStatus = 'Declined/Not Approved';
                        } else {
                            $physicalStatus = 'Pending';
                        }
                        error_log("Physical examination check (review_status) - Physical Exam ID: " . $derived['physical_exam_id'] . ", Remarks: " . $remarks . ", Status: " . $physicalStatus);
                    }
                }
            } catch (Exception $e) {
                error_log("Error checking physical examination remarks (review_status): " . $e->getMessage());
            }
        }
        
        // If not found by physical_exam_id, try to find by donor_id
        if (!$hasPhysicalData) {
            try {
                $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=remarks&donor_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http === 200) {
                    $arr = json_decode($resp, true);
                    if (!empty($arr) && isset($arr[0]['remarks'])) {
                        $remarks = $arr[0]['remarks'];
                        $hasPhysicalData = true;
                        // Check specific enum values for physical examination status
                        if ($remarks === 'Accepted') {
                            $physicalStatus = 'Accepted';
                        } elseif ($remarks === 'Temporarily Deferred') {
                            $physicalStatus = 'Temporarily Deferred';
                        } elseif ($remarks === 'Permanently Deferred') {
                            $physicalStatus = 'Permanently Deferred';
                        } elseif ($remarks === 'Refused') {
                            $physicalStatus = 'Refused';
                        } elseif (!empty($remarks) && trim($remarks) !== '' && $remarks !== 'Pending') {
                            // Fallback for any other non-empty values
                            $physicalStatus = 'Declined/Not Approved';
                        } else {
                            $physicalStatus = 'Pending';
                        }
                        error_log("Physical examination check (review_status by donor_id) - Donor ID: " . $donor_id . ", Remarks: " . $remarks . ", Status: " . $physicalStatus);
                    }
                }
            } catch (Exception $e) {
                error_log("Error checking physical examination remarks by donor_id (review_status): " . $e->getMessage());
            }
        }
        
        $derived['review_status'] = $physicalStatus;
        }
    }
    if (!isset($derived['physical_status'])) {
        // Check physical examination status based on remarks column
        $physicalStatus = 'Pending';
        $hasPhysicalData = false;
        
        // First try to find by physical_exam_id if available
        if (isset($derived['physical_exam_id']) && !empty($derived['physical_exam_id'])) {
            try {
                $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=remarks&physical_exam_id=eq.' . urlencode($derived['physical_exam_id']));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http === 200) {
                    $arr = json_decode($resp, true);
                    if (!empty($arr) && isset($arr[0]['remarks'])) {
                        $remarks = $arr[0]['remarks'];
                        $hasPhysicalData = true;
                        // Check specific enum values for physical examination status
                        if ($remarks === 'Accepted') {
                            $physicalStatus = 'Accepted';
                        } elseif ($remarks === 'Temporarily Deferred') {
                            $physicalStatus = 'Temporarily Deferred';
                        } elseif ($remarks === 'Permanently Deferred') {
                            $physicalStatus = 'Permanently Deferred';
                        } elseif ($remarks === 'Refused') {
                            $physicalStatus = 'Refused';
                        } elseif (!empty($remarks) && trim($remarks) !== '' && $remarks !== 'Pending') {
                            // Fallback for any other non-empty values
                            $physicalStatus = 'Declined/Not Approved';
                        } else {
                            $physicalStatus = 'Pending';
                        }
                        error_log("Physical examination check (physical_status) - Physical Exam ID: " . $derived['physical_exam_id'] . ", Remarks: " . $remarks . ", Status: " . $physicalStatus);
                    }
                }
            } catch (Exception $e) {
                error_log("Error checking physical examination remarks (physical_status): " . $e->getMessage());
            }
        }
        
        // If not found by physical_exam_id, try to find by donor_id
        if (!$hasPhysicalData) {
            try {
                $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=remarks&donor_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http === 200) {
                    $arr = json_decode($resp, true);
                    if (!empty($arr) && isset($arr[0]['remarks'])) {
                        $remarks = $arr[0]['remarks'];
                        $hasPhysicalData = true;
                        // Check specific enum values for physical examination status
                        if ($remarks === 'Accepted') {
                            $physicalStatus = 'Accepted';
                        } elseif ($remarks === 'Temporarily Deferred') {
                            $physicalStatus = 'Temporarily Deferred';
                        } elseif ($remarks === 'Permanently Deferred') {
                            $physicalStatus = 'Permanently Deferred';
                        } elseif ($remarks === 'Refused') {
                            $physicalStatus = 'Refused';
                        } elseif (!empty($remarks) && trim($remarks) !== '' && $remarks !== 'Pending') {
                            // Fallback for any other non-empty values
                            $physicalStatus = 'Declined/Not Approved';
                        } else {
                            $physicalStatus = 'Pending';
                        }
                        error_log("Physical examination check (physical_status by donor_id) - Donor ID: " . $donor_id . ", Remarks: " . $remarks . ", Status: " . $physicalStatus);
                    }
                }
            } catch (Exception $e) {
                error_log("Error checking physical examination remarks by donor_id (physical_status): " . $e->getMessage());
            }
        }
        
        $derived['physical_status'] = $physicalStatus;
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

    if (!isset($derived['collection_status']) || $shouldRecalculateCollection) {
        $collection_status = 'Pending';
        $blood_collection_id = $derived['blood_collection_id'] ?? null;
        $screening_id = $derived['screening_id'] ?? null;
        $physical_exam_id = $derived['physical_exam_id'] ?? null;
        
        // If we don't have physical_exam_id from eligibility, try to get it from physical_examination table
        if (!$physical_exam_id) {
            try {
                $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Accept: application/json'
                ]);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http === 200) {
                    $arr = json_decode($resp, true);
                    if (!empty($arr) && isset($arr[0]['physical_exam_id'])) {
                        $physical_exam_id = $arr[0]['physical_exam_id'];
                        error_log("Donor Details API - Found physical_exam_id from physical_examination table for donor $donor_id: $physical_exam_id");
                    }
                }
            } catch (Exception $e) {
                error_log("Error fetching physical_exam_id by donor_id: " . $e->getMessage());
            }
        }
        
        try {
            $headers = [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ];
            
            error_log("Donor Details API - Checking blood collection for donor $donor_id - blood_collection_id: " . ($blood_collection_id ?? 'null') . ", physical_exam_id: " . ($physical_exam_id ?? 'null') . ", screening_id: " . ($screening_id ?? 'null'));
            
            // First try by blood_collection_id
            if ($blood_collection_id) {
                $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=is_successful,needs_review,status&blood_collection_id=eq.' . urlencode($blood_collection_id) . '&limit=1');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http === 200) {
                    $arr = json_decode($resp, true);
                    error_log("Donor Details API - Blood collection query by blood_collection_id response: " . json_encode($arr));
                    if (!empty($arr)) {
                        $collectionDetails = $arr[0];
                        if (isset($arr[0]['is_successful'])) {
                            // Check is_successful column - if true, it's successful
                            $collection_status = $arr[0]['is_successful'] ? 'Successful' : 'Declined/Not Approved';
                            error_log("Donor Details API - Collection status set to: $collection_status (from is_successful: " . ($arr[0]['is_successful'] ? 'true' : 'false') . ")");
                        } elseif (isset($arr[0]['needs_review']) && $arr[0]['needs_review'] === true) {
                            $collection_status = 'Pending';
                            error_log("Donor Details API - Collection status set to Pending (needs_review: true)");
                        } elseif (isset($arr[0]['status'])) {
                            // Check status field as fallback
                            $statusLower = strtolower(trim($arr[0]['status']));
                            if ($statusLower === 'successful') {
                                $collection_status = 'Successful';
                                error_log("Donor Details API - Collection status set to Successful (from status field)");
                            }
                        }
                    }
                }
            } 
            // Try by physical_exam_id (preferred method for workflow progression)
            elseif ($physical_exam_id) {
                $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=is_successful,needs_review,status&physical_exam_id=eq.' . urlencode($physical_exam_id) . '&order=created_at.desc&limit=1');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http === 200) {
                    $arr = json_decode($resp, true);
                    error_log("Donor Details API - Blood collection query by physical_exam_id response: " . json_encode($arr));
                    if (!empty($arr)) {
                        $collectionDetails = $arr[0];
                        if (isset($arr[0]['is_successful'])) {
                            // Check is_successful column - if true, it's successful
                            $collection_status = $arr[0]['is_successful'] ? 'Successful' : 'Declined/Not Approved';
                            error_log("Donor Details API - Collection status set to: $collection_status (from is_successful: " . ($arr[0]['is_successful'] ? 'true' : 'false') . ")");
                        } elseif (isset($arr[0]['needs_review']) && $arr[0]['needs_review'] === true) {
                            $collection_status = 'Pending';
                            error_log("Donor Details API - Collection status set to Pending (needs_review: true)");
                        } elseif (isset($arr[0]['status'])) {
                            // Check status field as fallback
                            $statusLower = strtolower(trim($arr[0]['status']));
                            if ($statusLower === 'successful') {
                                $collection_status = 'Successful';
                                error_log("Donor Details API - Collection status set to Successful (from status field)");
                            }
                        }
                    } else {
                        error_log("Donor Details API - No blood collection records found for physical_exam_id: $physical_exam_id");
                    }
                } else {
                    error_log("Donor Details API - HTTP error $http when querying blood_collection by physical_exam_id: $physical_exam_id");
                }
            }
            // Fallback to screening_id
            elseif ($screening_id) {
                $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=is_successful,needs_review,status&screening_id=eq.' . urlencode($screening_id) . '&order=created_at.desc&limit=1');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http === 200) {
                    $arr = json_decode($resp, true);
                    error_log("Donor Details API - Blood collection query by screening_id response: " . json_encode($arr));
                    if (!empty($arr)) {
                        $collectionDetails = $arr[0];
                        if (isset($arr[0]['is_successful'])) {
                            // Check is_successful column - if true, it's successful
                            $collection_status = $arr[0]['is_successful'] ? 'Successful' : 'Declined/Not Approved';
                            error_log("Donor Details API - Collection status set to: $collection_status (from is_successful: " . ($arr[0]['is_successful'] ? 'true' : 'false') . ")");
                        } elseif (isset($arr[0]['needs_review']) && $arr[0]['needs_review'] === true) {
                            $collection_status = 'Pending';
                            error_log("Donor Details API - Collection status set to Pending (needs_review: true)");
                        } elseif (isset($arr[0]['status'])) {
                            // Check status field as fallback
                            $statusLower = strtolower(trim($arr[0]['status']));
                            if ($statusLower === 'successful') {
                                $collection_status = 'Successful';
                                error_log("Donor Details API - Collection status set to Successful (from status field)");
                            }
                        }
                    }
                }
            } else {
                error_log("Donor Details API - No identifiers available to query blood_collection (donor_id: $donor_id)");
            }
        } catch (Exception $e) {
            error_log("Error fetching blood collection status: " . $e->getMessage());
        }
        $derived['collection_status'] = $collection_status;
        error_log("Donor Details API - Final collection_status for donor $donor_id: $collection_status");
    }

    // Fetch physical examination data for physician section
    $physicalExamData = null;
    if (isset($derived['physical_exam_id']) && !empty($derived['physical_exam_id'])) {
        $physicalExamData = fetchPhysicalExamData($derived['physical_exam_id']);
    } else {
        // Try to fetch by donor_id if no physical_exam_id
        try {
            $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . urlencode($donor_id) . '&order=created_at.desc&limit=1');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http === 200) {
                $arr = json_decode($resp, true);
                if (!empty($arr)) {
                    $physicalExamData = $arr[0];
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching physical examination data by donor_id: " . $e->getMessage());
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