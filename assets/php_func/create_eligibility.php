<?php
// Prevent any output before headers
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include database connection
include_once '../conn/db_conn.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Helper function to log errors
function logError($message) {
    error_log("Create Eligibility Error: " . $message);
    return ["success" => false, "error" => $message];
}

// Helper function to calculate temporary_deferred text based on deferral type and duration
function calculateTemporaryDeferredText($deferral_type, $duration) {
    if ($deferral_type === 'Temporary Deferral' && $duration) {
        $days = intval($duration);
        if ($days > 0) {
            // Calculate months and remaining days
            $months = floor($days / 30);
            $remainingDays = $days % 30;
            
            if ($months > 0 && $remainingDays > 0) {
                $monthText = $months > 1 ? 'months' : 'month';
                $dayText = $remainingDays > 1 ? 'days' : 'day';
                return "{$months} {$monthText} {$remainingDays} {$dayText}";
            } else if ($months > 0) {
                $monthText = $months > 1 ? 'months' : 'month';
                return "{$months} {$monthText}";
            } else {
                $dayText = $days > 1 ? 'days' : 'day';
                return "{$days} {$dayText}";
            }
        } else {
            return 'Immediate';
        }
    } else if ($deferral_type === 'Permanent Deferral') {
        return 'Ineligible/Indefinite';
    } else if ($deferral_type === 'Refuse') {
        return 'Session Refused';
    } else {
        return null; // No deferral
    }
}

/**
 * Create a new eligibility record in Supabase
 * Used when processing a donor from pending to approved status
 */
function createEligibilityRecord($data) {
    try {
        // Check required fields
        if (!isset($data['donor_id'])) {
            return logError("Missing required field: donor_id");
        }
        
        // Set defaults
        $timestamp = date('Y-m-d H:i:s');
        $data['status'] = $data['status'] ?? 'approved';
        $data['created_at'] = $data['created_at'] ?? $timestamp;
        $data['updated_at'] = $data['updated_at'] ?? $timestamp;
        
        // Blood type and donation type will be stored in the screening_form
        $bloodType = isset($data['blood_type']) ? $data['blood_type'] : null;
        $donationType = isset($data['donation_type']) ? $data['donation_type'] : null;
        
        // Remove blood_type and donation_type from eligibility data
        unset($data['blood_type']);
        unset($data['donation_type']);
        
        // First, check if a screening record already exists for this donor
        $screeningId = null;
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_id=eq." . $data['donor_id'] . "&select=screening_id",
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
            return logError("Error checking for existing screening: " . $err);
        }
        
        $screeningData = json_decode($response, true);
        
        // If screening record exists, use its ID
        if (is_array($screeningData) && !empty($screeningData)) {
            $screeningId = $screeningData[0]['screening_id'];
            error_log("Found existing screening_id: " . $screeningId);
            
            // Update the existing screening record with blood type and donation type
            if ($bloodType || $donationType) {
                $updateData = [];
                if ($bloodType) $updateData['blood_type'] = $bloodType;
                if ($donationType) $updateData['donation_type'] = $donationType;
                
                $updateCurl = curl_init();
                curl_setopt_array($updateCurl, [
                    CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?screening_id=eq." . $screeningId,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => "PATCH",
                    CURLOPT_POSTFIELDS => json_encode($updateData),
                    CURLOPT_HTTPHEADER => [
                        "apikey: " . SUPABASE_API_KEY,
                        "Authorization: Bearer " . SUPABASE_API_KEY,
                        "Content-Type: application/json",
                        "Prefer: return=minimal"
                    ],
                ]);
                
                curl_exec($updateCurl);
                curl_close($updateCurl);
                error_log("Updated screening record with blood type and donation type");
            }
        } 
        // If no screening record, create one
        else {
            error_log("No existing screening record found, creating new one");
            $screeningData = [
                "donor_id" => $data['donor_id'],
                "created_at" => $timestamp,
                "updated_at" => $timestamp
            ];
            
            // Add blood type and donation type if provided
            if ($bloodType) $screeningData['blood_type'] = $bloodType;
            if ($donationType) $screeningData['donation_type'] = $donationType;
            
            $createCurl = curl_init();
            curl_setopt_array($createCurl, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($screeningData),
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json",
                    "Prefer: return=representation"
                ],
            ]);
            
            $createResponse = curl_exec($createCurl);
            $createErr = curl_error($createCurl);
            curl_close($createCurl);
            
            if ($createErr) {
                return logError("Error creating screening record: " . $createErr);
            }
            
            // Log the full screening form creation response for debugging
            error_log("Screening form creation response: " . $createResponse);
            
            $newScreening = json_decode($createResponse, true);
            if (is_array($newScreening) && !empty($newScreening)) {
                $screeningId = $newScreening[0]['screening_id'];
                error_log("Created new screening_id: " . $screeningId);
            } else {
                error_log("Warning: Failed to create screening record: " . $createResponse);
            }
        }
        
        // Add screening_id to eligibility data if available
        if ($screeningId) {
            $data['screening_id'] = $screeningId;
        }
        
        // Check if eligibility record already exists for this donor
        $checkCurl = curl_init();
        curl_setopt_array($checkCurl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?donor_id=eq." . $data['donor_id'] . "&select=eligibility_id,status",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ],
        ]);
        
        $checkResponse = curl_exec($checkCurl);
        $checkErr = curl_error($checkCurl);
        curl_close($checkCurl);
        
        if (!$checkErr) {
            $existingEligibility = json_decode($checkResponse, true);
            if (is_array($existingEligibility) && !empty($existingEligibility)) {
                error_log("Eligibility record already exists for donor_id: " . $data['donor_id']);
                return [
                    "success" => false, 
                    "message" => "Eligibility record already exists for this donor",
                    "existing_eligibility_id" => $existingEligibility[0]['eligibility_id']
                ];
            }
        }
        
        // Now create the eligibility record
        $insertCurl = curl_init();
        curl_setopt_array($insertCurl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json",
                "Prefer: return=representation"
            ],
        ]);
        
        $insertResponse = curl_exec($insertCurl);
        $insertErr = curl_error($insertCurl);
        curl_close($insertCurl);
        
        if ($insertErr) {
            return logError("Error creating eligibility record: " . $insertErr);
        }
        
        $eligibilityData = json_decode($insertResponse, true);
        if (!is_array($eligibilityData) || empty($eligibilityData)) {
            return logError("Invalid response when creating eligibility record: " . $insertResponse);
        }
        
        error_log("Successfully created eligibility record: " . $insertResponse);
        return [
            "success" => true, 
            "message" => "Eligibility record created successfully",
            "data" => $eligibilityData
        ];
        
    } catch (Exception $e) {
        return logError("Exception: " . $e->getMessage());
    }
}

/**
 * Create eligibility record for defer action
 * Used when deferring a donor
 */
function createDeferEligibilityRecord($data) {
    try {
        // Validate required fields
        if (!isset($data['donor_id']) || !isset($data['deferral_type']) || !isset($data['disapproval_reason'])) {
            return logError('Missing required fields for defer action');
        }
        
        $donor_id = $data['donor_id'];
        $screening_id = $data['screening_id'] ?? null;
        $deferral_type = $data['deferral_type'];
        $disapproval_reason = $data['disapproval_reason'];
        $duration = isset($data['duration']) ? (int)$data['duration'] : null;
        
        // Calculate end date for temporary deferrals
        $end_date = null;
        if ($deferral_type === 'Temporary Deferral' && $duration) {
            $end_date = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
        }
        
        // Determine status based on deferral type
        $status = 'temporary deferred';
        if ($deferral_type === 'Permanent Deferral') {
            $status = 'permanently deferred';
        } elseif ($deferral_type === 'Refuse') {
            $status = 'refused';
        }
        
        // Get or create screening information
        $screening_record = null;
        
        if ($screening_id) {
            // Try to fetch existing screening record
        $ch_screening = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=*&screening_id=eq.' . $screening_id);
        curl_setopt($ch_screening, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_screening, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $screening_response = curl_exec($ch_screening);
        $screening_http_code = curl_getinfo($ch_screening, CURLINFO_HTTP_CODE);
        curl_close($ch_screening);
        
            if ($screening_http_code === 200) {
                $screening_data = json_decode($screening_response, true);
                if (!empty($screening_data)) {
                    $screening_record = $screening_data[0];
                }
            }
        }
        
        // Get screening form data from the request
        $screening_form_data = $data['screening_form_data'] ?? [];
        error_log("Received screening form data: " . json_encode($screening_form_data));
        
        // Get interviewer_id from session (user_id from users table)
        $interviewer_id = $_SESSION['user_id'] ?? null;
        
        // Debug session data
        error_log("Session data: " . json_encode($_SESSION));
        error_log("Interviewer ID from session: " . ($interviewer_id ?? 'NULL'));
        
        if ($screening_record) {
            // Update existing screening record
            error_log("Screening record found, updating existing record for donor_id: $donor_id");
            
            $update_screening_data = [
                'body_weight' => !empty($screening_form_data['body_weight']) ? (float)$screening_form_data['body_weight'] : $screening_record['body_weight'],
                'specific_gravity' => !empty($screening_form_data['specific_gravity']) ? (float)$screening_form_data['specific_gravity'] : $screening_record['specific_gravity'],
                'blood_type' => !empty($screening_form_data['blood_type']) ? $screening_form_data['blood_type'] : $screening_record['blood_type'],
                'donation_type' => !empty($screening_form_data['donation_type']) ? 
                    strtolower(str_replace(' ', '-', $screening_form_data['donation_type'])) : $screening_record['donation_type'],
                'has_previous_donation' => isset($screening_form_data['has_previous_donation']) ? (bool)$screening_form_data['has_previous_donation'] : $screening_record['has_previous_donation'],
                'red_cross_donations' => isset($screening_form_data['red_cross_donations']) ? (int)$screening_form_data['red_cross_donations'] : $screening_record['red_cross_donations'],
                'hospital_donations' => isset($screening_form_data['hospital_donations']) ? (int)$screening_form_data['hospital_donations'] : $screening_record['hospital_donations'],
                'last_rc_donation_place' => !empty($screening_form_data['last_rc_donation_place']) ? $screening_form_data['last_rc_donation_place'] : $screening_record['last_rc_donation_place'],
                'last_hosp_donation_place' => !empty($screening_form_data['last_hosp_donation_place']) ? $screening_form_data['last_hosp_donation_place'] : $screening_record['last_hosp_donation_place'],
                'last_rc_donation_date' => !empty($screening_form_data['last_rc_donation_date']) ? $screening_form_data['last_rc_donation_date'] : $screening_record['last_rc_donation_date'],
                'last_hosp_donation_date' => !empty($screening_form_data['last_hosp_donation_date']) ? $screening_form_data['last_hosp_donation_date'] : $screening_record['last_hosp_donation_date'],
                'mobile_location' => !empty($screening_form_data['mobile_location']) ? $screening_form_data['mobile_location'] : $screening_record['mobile_location'],
                'mobile_organizer' => !empty($screening_form_data['mobile_organizer']) ? $screening_form_data['mobile_organizer'] : $screening_record['mobile_organizer'],
                'patient_name' => !empty($screening_form_data['patient_name']) ? $screening_form_data['patient_name'] : $screening_record['patient_name'],
                'hospital' => !empty($screening_form_data['hospital']) ? $screening_form_data['hospital'] : $screening_record['hospital'],
                'patient_blood_type' => !empty($screening_form_data['patient_blood_type']) ? $screening_form_data['patient_blood_type'] : $screening_record['patient_blood_type'],
                'component_type' => !empty($screening_form_data['component_type']) ? $screening_form_data['component_type'] : $screening_record['component_type'],
                'units_needed' => isset($screening_form_data['units_needed']) ? (int)$screening_form_data['units_needed'] : $screening_record['units_needed'],
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $ch_update_screening = curl_init(SUPABASE_URL . '/rest/v1/screening_form?screening_id=eq.' . $screening_record['screening_id']);
            curl_setopt($ch_update_screening, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_update_screening, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch_update_screening, CURLOPT_POSTFIELDS, json_encode($update_screening_data));
            curl_setopt($ch_update_screening, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            
            $update_screening_response = curl_exec($ch_update_screening);
            $update_screening_http_code = curl_getinfo($ch_update_screening, CURLINFO_HTTP_CODE);
            curl_close($ch_update_screening);
            
            if ($update_screening_http_code === 200 || $update_screening_http_code === 204) {
                error_log("Successfully updated existing screening record: " . $screening_record['screening_id']);
                $screening_id = $screening_record['screening_id'];
            } else {
                error_log("Failed to update screening record: HTTP $update_screening_http_code - $update_screening_response");
                return logError("Failed to update screening record for deferred donor");
            }
        } else {
            // Create new screening record
            error_log("No screening record found, creating one with screening form data for donor_id: $donor_id");
            
            // Get medical history ID first
        $medical_history_id = null;
            $ch_medical = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id);
        curl_setopt($ch_medical, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_medical, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $medical_response = curl_exec($ch_medical);
        $medical_http_code = curl_getinfo($ch_medical, CURLINFO_HTTP_CODE);
        curl_close($ch_medical);
        
        if ($medical_http_code === 200) {
            $medical_data = json_decode($medical_response, true);
            if (!empty($medical_data)) {
                $medical_history_id = $medical_data[0]['medical_history_id'] ?? null;
                    error_log("Found medical history ID for screening: $medical_history_id");
                }
            }
            
            $screening_data = [
                'donor_form_id' => (int)$donor_id, // Use donor_form_id as corrected
                'medical_history_id' => $medical_history_id, // Set the medical history ID
                'interviewer_id' => $interviewer_id, // Foreign key to users.user_id
                'body_weight' => !empty($screening_form_data['body_weight']) ? (float)$screening_form_data['body_weight'] : null,
                'specific_gravity' => !empty($screening_form_data['specific_gravity']) ? (float)$screening_form_data['specific_gravity'] : null,
                'blood_type' => !empty($screening_form_data['blood_type']) ? $screening_form_data['blood_type'] : null,
                 'donation_type' => !empty($screening_form_data['donation_type']) ? 
                     strtolower(str_replace(' ', '-', $screening_form_data['donation_type'])) : null,
                'has_previous_donation' => (bool)($screening_form_data['has_previous_donation'] ?? false),
                'red_cross_donations' => (int)($screening_form_data['red_cross_donations'] ?? 0),
                'hospital_donations' => (int)($screening_form_data['hospital_donations'] ?? 0),
                'last_rc_donation_place' => !empty($screening_form_data['last_rc_donation_place']) ? $screening_form_data['last_rc_donation_place'] : null,
                'last_hosp_donation_place' => !empty($screening_form_data['last_hosp_donation_place']) ? $screening_form_data['last_hosp_donation_place'] : null,
                'last_rc_donation_date' => !empty($screening_form_data['last_rc_donation_date']) ? $screening_form_data['last_rc_donation_date'] : null,
                'last_hosp_donation_date' => !empty($screening_form_data['last_hosp_donation_date']) ? $screening_form_data['last_hosp_donation_date'] : null,
                'mobile_location' => !empty($screening_form_data['mobile_location']) ? $screening_form_data['mobile_location'] : null,
                'mobile_organizer' => !empty($screening_form_data['mobile_organizer']) ? $screening_form_data['mobile_organizer'] : null,
                'patient_name' => !empty($screening_form_data['patient_name']) ? $screening_form_data['patient_name'] : null,
                'hospital' => !empty($screening_form_data['hospital']) ? $screening_form_data['hospital'] : null,
                'patient_blood_type' => !empty($screening_form_data['patient_blood_type']) ? $screening_form_data['patient_blood_type'] : null,
                'component_type' => !empty($screening_form_data['component_type']) ? $screening_form_data['component_type'] : null,
                 'units_needed' => !empty($screening_form_data['units_needed']) ? (int)$screening_form_data['units_needed'] : null,
                'created_at' => null, // Set to null as requested
                'updated_at' => null  // Set to null as requested
            ];
            
            $ch_create_screening = curl_init(SUPABASE_URL . '/rest/v1/screening_form');
            curl_setopt($ch_create_screening, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_create_screening, CURLOPT_POST, true);
            curl_setopt($ch_create_screening, CURLOPT_POSTFIELDS, json_encode($screening_data));
            curl_setopt($ch_create_screening, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]);
            
            $create_screening_response = curl_exec($ch_create_screening);
            $create_screening_http_code = curl_getinfo($ch_create_screening, CURLINFO_HTTP_CODE);
            curl_close($ch_create_screening);
            
             error_log("Screening creation attempt - HTTP Code: $create_screening_http_code");
             error_log("Screening creation response: $create_screening_response");
             error_log("Screening data being sent: " . json_encode($screening_data));
             error_log("Donation type being sent: " . ($screening_data['donation_type'] ?? 'NULL'));
            
            if ($create_screening_http_code === 201) {
                $created_screening = json_decode($create_screening_response, true);
                if (!empty($created_screening)) {
                    $screening_record = $created_screening[0];
                    $screening_id = $screening_record['screening_id'];
                    error_log("Created screening record with ID: $screening_id for deferred donor");
                }
            } else {
                error_log("Failed to create screening record: HTTP $create_screening_http_code - $create_screening_response");
                return logError('Failed to create screening record for deferred donor');
            }
        }
        
        // Medical history ID already retrieved above
        
        // Create or update physical examination record for ALL deferral types
        $physical_exam_id = null;
        
        // Set appropriate remarks based on deferral type
        $remarks = '';
        if ($deferral_type === 'Temporary Deferral') {
            $remarks = 'Temporarily Deferred';
        } elseif ($deferral_type === 'Permanent Deferral') {
            $remarks = 'Permanently Deferred';
        } elseif ($deferral_type === 'Refuse') {
            $remarks = 'Refused';
        }
        
        // Create a physical examination record with appropriate remarks
        $physical_exam_data = [
            'donor_id' => $donor_id,
            'blood_pressure' => null,
            'pulse_rate' => null,
            'body_temp' => null,
            'gen_appearance' => null,
            'skin' => null,
            'heart_and_lungs' => null,
            'remarks' => $remarks,
            'reason' => $disapproval_reason,
            'blood_bag_type' => null,
            'disapproval_reason' => $disapproval_reason,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Determine if we should update or insert
        $incoming_exam_id = $data['physical_exam_id'] ?? null;
        if ($incoming_exam_id) {
            // PATCH by physical_exam_id
            $ph_patch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . urlencode($incoming_exam_id));
            curl_setopt($ph_patch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ph_patch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ph_patch, CURLOPT_POSTFIELDS, json_encode($physical_exam_data));
            curl_setopt($ph_patch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]);
            $ph_resp = curl_exec($ph_patch);
            $ph_http = curl_getinfo($ph_patch, CURLINFO_HTTP_CODE);
            curl_close($ph_patch);
            if ($ph_http >= 200 && $ph_http < 300) {
                $updated = json_decode($ph_resp, true) ?: [];
                if (!empty($updated)) {
                    $physical_exam_id = $updated[0]['physical_exam_id'] ?? $incoming_exam_id;
                } else {
                    $physical_exam_id = $incoming_exam_id;
                }
                error_log("Updated physical examination record ID: $physical_exam_id for deferral");
            } else {
                error_log("Physical exam PATCH failed (id provided): HTTP $ph_http - $ph_resp. Falling back to insert.");
            }
        }

        if (!$physical_exam_id) {
            // If no id provided or update failed, try update-by-donor; else insert
            $check_ph = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.' . $donor_id . '&limit=1');
            curl_setopt($check_ph, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($check_ph, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]);
            $check_ph_resp = curl_exec($check_ph);
            $check_ph_http = curl_getinfo($check_ph, CURLINFO_HTTP_CODE);
            curl_close($check_ph);
            $existing_ph = ($check_ph_http === 200) ? (json_decode($check_ph_resp, true) ?: []) : [];
            if (!empty($existing_ph)) {
                $existing_id = $existing_ph[0]['physical_exam_id'];
                $ph_patch2 = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . urlencode($existing_id));
                curl_setopt($ph_patch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ph_patch2, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ph_patch2, CURLOPT_POSTFIELDS, json_encode($physical_exam_data));
                curl_setopt($ph_patch2, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ]);
                $ph_resp2 = curl_exec($ph_patch2);
                $ph_http2 = curl_getinfo($ph_patch2, CURLINFO_HTTP_CODE);
                curl_close($ph_patch2);
                if ($ph_http2 >= 200 && $ph_http2 < 300) {
                    $upd2 = json_decode($ph_resp2, true) ?: [];
                    $physical_exam_id = !empty($upd2) ? ($upd2[0]['physical_exam_id'] ?? $existing_id) : $existing_id;
                    error_log("Updated physical examination (by donor) ID: $physical_exam_id");
                }
            }
        }

        if (!$physical_exam_id) {
            // Insert physical examination record
            $ch_physical = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
            curl_setopt($ch_physical, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_physical, CURLOPT_POST, true);
            curl_setopt($ch_physical, CURLOPT_POSTFIELDS, json_encode($physical_exam_data));
            curl_setopt($ch_physical, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]);
            
            $physical_response = curl_exec($ch_physical);
            $physical_http_code = curl_getinfo($ch_physical, CURLINFO_HTTP_CODE);
            curl_close($ch_physical);
            
            if ($physical_http_code === 201) {
                $created_physical = json_decode($physical_response, true);
                if (!empty($created_physical)) {
                    $physical_exam_id = $created_physical[0]['physical_exam_id'] ?? null;
                    error_log("Created physical examination record with ID: $physical_exam_id for deferral type: $deferral_type");
                }
            } else {
                error_log("Failed to create physical examination record: HTTP $physical_http_code - $physical_response");
            }
        }

        // Ensure we have physical_exam_id for downstream linking

        // Check if eligibility record already exists for this donor
        $eligibility_check_ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donor_id . '&select=eligibility_id,status');
        curl_setopt($eligibility_check_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($eligibility_check_ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $eligibility_check_response = curl_exec($eligibility_check_ch);
        $eligibility_check_http_code = curl_getinfo($eligibility_check_ch, CURLINFO_HTTP_CODE);
        curl_close($eligibility_check_ch);
        
        // Always create new eligibility records for deferrals (don't update existing ones)
        error_log("Creating new eligibility record for donor_id: $donor_id with deferral type: $deferral_type");

        // Create eligibility record with screening form data
        $eligibility_data = [
            'donor_id' => $donor_id,
            'medical_history_id' => $medical_history_id, // Include medical history ID
            'screening_id' => $screening_id,
            'physical_exam_id' => $physical_exam_id, // Now set for all deferral types
            'blood_collection_id' => null,
            'blood_type' => $screening_record['blood_type'] ?? null,
            'donation_type' => $screening_record['donation_type'] ?? null,
            'blood_bag_type' => null,
            'amount_collected' => null,
            'collection_successful' => false,
            'donor_reaction' => null,
            'management_done' => null,
            'collection_start_time' => null,
            'collection_end_time' => null,
            'unit_serial_number' => null,
            'disapproval_reason' => $disapproval_reason,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => $end_date,
            'status' => $status,
            // Include screening form data that exists in eligibility table
            'body_weight' => $screening_record['body_weight'] ?? null,
            'blood_pressure' => null, // Not collected in screening form
            'pulse_rate' => null, // Not collected in screening form
            'body_temp' => null, // Not collected in screening form
            'gen_appearance' => null, // Not collected in screening form
            'skin' => null, // Not collected in screening form
            'heent' => null, // Not collected in screening form
            'heart_and_lungs' => null, // Not collected in screening form
            'temporary_deferred' => calculateTemporaryDeferredText($deferral_type, $duration),
            'interviewer' => 'Ray Jasper Suner', // From medical history data
            'physician' => null, // Not available
            'phlebotomist' => null, // Not available
            'registration_channel' => 'PRC Portal', // Default value
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Insert eligibility record
        $ch_insert = curl_init(SUPABASE_URL . '/rest/v1/eligibility');
        curl_setopt($ch_insert, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_insert, CURLOPT_POST, true);
        curl_setopt($ch_insert, CURLOPT_POSTFIELDS, json_encode($eligibility_data));
        curl_setopt($ch_insert, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        
        $insert_response = curl_exec($ch_insert);
        $insert_http_code = curl_getinfo($ch_insert, CURLINFO_HTTP_CODE);
        curl_close($ch_insert);
        
        if ($insert_http_code !== 201) {
            error_log("Eligibility insert failed: HTTP $insert_http_code - $insert_response");
            return logError('Failed to create eligibility record: ' . $insert_response);
        }
        
        $created_eligibility = json_decode($insert_response, true);
        
        // Update medical_history for a defer action: set medical_approval to 'Not Approve' and needs_review=true
        try {
            // Ensure we have the medical_history_id; if not, fetch by donor_id
            if (!$medical_history_id) {
                $mh_check = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id . '&limit=1');
                curl_setopt($mh_check, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($mh_check, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY
                ]);
                $mh_resp = curl_exec($mh_check);
                $mh_http = curl_getinfo($mh_check, CURLINFO_HTTP_CODE);
                curl_close($mh_check);
                if ($mh_http === 200) {
                    $mh_data = json_decode($mh_resp, true) ?: [];
                    if (!empty($mh_data)) {
                        $medical_history_id = $mh_data[0]['medical_history_id'] ?? null;
                    }
                }
            }

            $mh_target = $medical_history_id
                ? SUPABASE_URL . '/rest/v1/medical_history?medical_history_id=eq.' . $medical_history_id
                : SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id;

            $mh_body = [
                'medical_approval' => 'Not Approve',
                // Per request: mark as reviewed (boolean false) and stamp updated_at
                'needs_review' => false,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $mh_ch = curl_init($mh_target);
            curl_setopt($mh_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($mh_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($mh_ch, CURLOPT_POSTFIELDS, json_encode($mh_body));
            curl_setopt($mh_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            $mh_patch_resp = curl_exec($mh_ch);
            $mh_patch_http = curl_getinfo($mh_ch, CURLINFO_HTTP_CODE);
            curl_close($mh_ch);
            if (!($mh_patch_http >= 200 && $mh_patch_http < 300)) {
                error_log("Defer: medical_history update failed for donor_id=$donor_id http=$mh_patch_http resp=$mh_patch_resp");
            } else {
                error_log("Defer: medical_history updated to Not Approve for donor_id=$donor_id");
            }
        } catch (Exception $e) {
            error_log('Defer: Exception updating medical_history: ' . $e->getMessage());
        }
        
        // Update screening form with disapproval reason, deferral details, and screening form data
        $screening_form_data = $data['screening_form_data'] ?? [];
        
        $update_screening_data = [
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Add screening form data if provided
        if (!empty($screening_form_data)) {
            $update_screening_data = array_merge($update_screening_data, [
                'body_weight' => $screening_form_data['body_weight'] ?? null,
                'specific_gravity' => $screening_form_data['specific_gravity'] ?? null,
                'blood_type' => $screening_form_data['blood_type'] ?? null,
                'donation_type' => !empty($screening_form_data['donation_type']) ? 
                    strtolower(str_replace(' ', '-', $screening_form_data['donation_type'])) : null,
                'has_previous_donation' => $screening_form_data['has_previous_donation'] ?? false,
                'red_cross_donations' => $screening_form_data['red_cross_donations'] ?? 0,
                'hospital_donations' => $screening_form_data['hospital_donations'] ?? 0,
                'last_rc_donation_place' => $screening_form_data['last_rc_donation_place'] ?? null,
                'last_hosp_donation_place' => $screening_form_data['last_hosp_donation_place'] ?? null,
                'last_rc_donation_date' => $screening_form_data['last_rc_donation_date'] ?? null,
                'last_hosp_donation_date' => $screening_form_data['last_hosp_donation_date'] ?? null,
                'mobile_location' => $screening_form_data['mobile_location'] ?? null,
                'mobile_organizer' => $screening_form_data['mobile_organizer'] ?? null,
                'patient_name' => $screening_form_data['patient_name'] ?? null,
                'hospital' => $screening_form_data['hospital'] ?? null,
                'patient_blood_type' => $screening_form_data['patient_blood_type'] ?? null,
                'component_type' => $screening_form_data['component_type'] ?? null,
                'units_needed' => $screening_form_data['units_needed'] ?? null
            ]);
        }
        
        $ch_update = curl_init(SUPABASE_URL . '/rest/v1/screening_form?screening_id=eq.' . $screening_id);
        curl_setopt($ch_update, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_update, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch_update, CURLOPT_POSTFIELDS, json_encode($update_screening_data));
        curl_setopt($ch_update, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json'
        ]);
        
        $update_response = curl_exec($ch_update);
        $update_http_code = curl_getinfo($ch_update, CURLINFO_HTTP_CODE);
        curl_close($ch_update);
        
        if ($update_http_code !== 204) {
            error_log("Screening update failed: HTTP $update_http_code - $update_response");
        }
        
        // Log the successful operation
        error_log("Defer eligibility record created successfully for donor_id: $donor_id, type: $deferral_type, status: $status, duration: " . ($duration ?: 'N/A') . ", medical_history_id: " . ($medical_history_id ?: 'N/A') . ", physical_exam_id: " . ($physical_exam_id ?: 'N/A'));
        
        return [
            'success' => true,
            'message' => 'Deferral recorded successfully',
            'eligibility_id' => $created_eligibility[0]['eligibility_id'] ?? null,
            'end_date' => $end_date,
            'status' => $status
        ];
        
    } catch (Exception $e) {
        return logError("Exception in defer action: " . $e->getMessage());
    }
}

// Process incoming request
try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        // Get JSON data from request body
        $jsonData = file_get_contents('php://input');
        $data = json_decode($jsonData, true);
        
        if (!$data) {
            echo json_encode(["success" => false, "error" => "Invalid JSON data"]);
            exit;
        }
        
        // Debug log incoming data
        error_log("Received data: " . json_encode($data));
        error_log("Screening form data received: " . json_encode($data['screening_form_data'] ?? 'No screening form data'));
        
        // Check if this is a defer action
        if (isset($data['action']) && $data['action'] === 'create_eligibility_defer') {
            $result = createDeferEligibilityRecord($data);
        } else {
            // Default to the original eligibility creation
            $result = createEligibilityRecord($data);
        }
        
        echo json_encode($result);
    } else {
        echo json_encode(["success" => false, "error" => "Only POST method is allowed"]);
    }
} catch (Exception $e) {
    error_log("Create eligibility error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Internal server error: " . $e->getMessage()]);
} catch (Error $e) {
    error_log("Create eligibility fatal error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Internal server error"]);
}

// Flush output buffer
ob_end_flush(); 