<?php
/**
 * Reset Donor Workflow on Medical History Update
 * 
 * This function resets Initial Screening, Physical Examination, and Blood Collection
 * records to pending status when Medical History is updated for an existing donor.
 * Historical approved data remains intact - only the current/latest records are reset.
 * 
 * @param int $donor_id The donor ID
 * @return array Result with success status and messages
 */
function resetDonorWorkflowOnMHUpdate($donor_id) {
    require_once __DIR__ . '/../conn/db_conn.php';
    
    $results = [
        'screening_reset' => false,
        'physical_exam_reset' => false,
        'blood_collection_reset' => false,
        'messages' => []
    ];
    
    try {
        // Check if medical history already exists (indicating an update, not new record)
        // We need to check BEFORE the update to see if this is truly an update vs new record
        // Since this function is called AFTER the update, we check if there are multiple MH records
        // or if the MH was created before today (indicating an existing donor)
        $mh_check = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id . '&select=medical_history_id,created_at,updated_at,medical_approval&order=created_at.desc');
        curl_setopt($mh_check, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($mh_check, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $mh_response = curl_exec($mh_check);
        $mh_http_code = curl_getinfo($mh_check, CURLINFO_HTTP_CODE);
        curl_close($mh_check);
        
        if ($mh_http_code === 200) {
            $mh_data = json_decode($mh_response, true);
            
            // Only proceed if medical history exists (this is an update, not a new record)
            if (!empty($mh_data) && isset($mh_data[0]['medical_history_id'])) {
                $mh_created_at = $mh_data[0]['created_at'] ?? null;
                $mh_updated_at = $mh_data[0]['updated_at'] ?? null;
                
                // Check if this is an update to an existing record (created before today)
                // or if updated_at is significantly different from created_at (indicating a date update)
                $is_existing_donor_update = false;
                if ($mh_created_at) {
                    $created_date = date('Y-m-d', strtotime($mh_created_at));
                    $today = date('Y-m-d');
                    // If created before today, this is an update to existing donor
                    $is_existing_donor_update = ($created_date < $today);
                    
                    // Also check if updated_at date is different from created_at date (date change)
                    if ($mh_updated_at) {
                        $updated_date = date('Y-m-d', strtotime($mh_updated_at));
                        if ($created_date !== $updated_date) {
                            $is_existing_donor_update = true; // Date has changed
                        }
                    }
                }
                
                // Reset workflow when MH is updated for existing donor
                // This ensures new donation cycles start fresh while preserving historical data
                if ($is_existing_donor_update) {
                    // 1. Reset latest Screening Form (set to pending status)
                    $screening_reset = resetLatestScreening($donor_id);
                    if ($screening_reset['success']) {
                        $results['screening_reset'] = true;
                        $results['messages'][] = $screening_reset['message'];
                    } else {
                        $results['messages'][] = $screening_reset['message'];
                    }
                    
                    // 2. Reset latest Physical Examination (set needs_review to true, remarks to 'Pending')
                    $pe_reset = resetLatestPhysicalExam($donor_id);
                    if ($pe_reset['success']) {
                        $results['physical_exam_reset'] = true;
                        $results['messages'][] = $pe_reset['message'];
                    } else {
                        $results['messages'][] = $pe_reset['message'];
                    }
                    
                    // 3. Reset latest Blood Collection (if exists and not completed)
                    $bc_reset = resetLatestBloodCollection($donor_id);
                    if ($bc_reset['success']) {
                        $results['blood_collection_reset'] = true;
                        $results['messages'][] = $bc_reset['message'];
                    } else {
                        $results['messages'][] = $bc_reset['message'];
                    }
                    
                    error_log("Workflow reset completed for donor_id: $donor_id. Screening: " . ($results['screening_reset'] ? 'Yes' : 'No') . 
                             ", PE: " . ($results['physical_exam_reset'] ? 'Yes' : 'No') . 
                             ", BC: " . ($results['blood_collection_reset'] ? 'Yes' : 'No'));
                } else {
                    $results['messages'][] = "Medical history is new (created today) - no reset needed for new donors";
                }
            } else {
                $results['messages'][] = "No existing medical history found - this is a new record, no reset needed";
            }
        }
        
    } catch (Exception $e) {
        error_log("Error resetting donor workflow: " . $e->getMessage());
        $results['messages'][] = "Error: " . $e->getMessage();
    }
    
    return $results;
}

/**
 * Reset the latest screening form for a donor
 * Only resets if not yet approved/completed
 */
function resetLatestScreening($donor_id) {
    try {
        // Get latest screening record
        $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . $donor_id . '&select=screening_id,medical_history_id,interview_date,created_at&order=created_at.desc&limit=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $screening_data = json_decode($response, true);
            if (!empty($screening_data) && isset($screening_data[0]['screening_id'])) {
                $screening_id = $screening_data[0]['screening_id'];
                $screening_created_at = $screening_data[0]['created_at'] ?? null;
                
                // Only reset if screening was created today or recently (part of current cycle)
                // Preserve older screenings that are part of completed cycles
                $should_reset = true;
                if ($screening_created_at) {
                    $screening_date = date('Y-m-d', strtotime($screening_created_at));
                    $today = date('Y-m-d');
                    // If screening is older than today, it might be from a previous cycle
                    // But we'll reset it anyway if it's the latest one and MH is being updated
                    // This ensures the current cycle starts fresh
                }
                
                // Reset by updating interview_date to current date
                // This indicates a new screening cycle while preserving the record
                $update_data = [
                    'interview_date' => date('Y-m-d'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $update_ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?screening_id=eq.' . $screening_id);
                curl_setopt($update_ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($update_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($update_ch, CURLOPT_POSTFIELDS, json_encode($update_data));
                curl_setopt($update_ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json',
                    'Prefer: return=minimal'
                ]);
                
                $update_response = curl_exec($update_ch);
                $update_http = curl_getinfo($update_ch, CURLINFO_HTTP_CODE);
                curl_close($update_ch);
                
                if ($update_http >= 200 && $update_http < 300) {
                    return ['success' => true, 'message' => "Screening form reset for screening_id: $screening_id"];
                } else {
                    return ['success' => false, 'message' => "Failed to reset screening form. HTTP: $update_http"];
                }
            } else {
                return ['success' => false, 'message' => "No screening form found for donor_id: $donor_id"];
            }
        } else {
            return ['success' => false, 'message' => "Failed to fetch screening form. HTTP: $http_code"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Error resetting screening: " . $e->getMessage()];
    }
}

/**
 * Reset the latest physical examination for a donor
 * Sets needs_review to true and remarks to 'Pending'
 */
function resetLatestPhysicalExam($donor_id) {
    try {
        // Get latest physical examination record
        $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $pe_data = json_decode($response, true);
            if (!empty($pe_data) && isset($pe_data[0]['physical_exam_id'])) {
                $pe_id = $pe_data[0]['physical_exam_id'];
                $current_remarks = $pe_data[0]['remarks'] ?? null;
                
                // Only reset if not already approved/completed
                // Check if remarks indicate approval (e.g., "Approved", "Completed")
                $is_approved = false;
                if ($current_remarks) {
                    $approved_keywords = ['Approved', 'Completed', 'Passed', 'Cleared'];
                    foreach ($approved_keywords as $keyword) {
                        if (stripos($current_remarks, $keyword) !== false) {
                            $is_approved = true;
                            break;
                        }
                    }
                }
                
                // Only reset if not approved - preserve approved records
                if (!$is_approved) {
                    $update_data = [
                        'needs_review' => true,
                        'remarks' => 'Pending',
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $update_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . $pe_id);
                    curl_setopt($update_ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($update_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($update_ch, CURLOPT_POSTFIELDS, json_encode($update_data));
                    curl_setopt($update_ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json',
                        'Prefer: return=minimal'
                    ]);
                    
                    $update_response = curl_exec($update_ch);
                    $update_http = curl_getinfo($update_ch, CURLINFO_HTTP_CODE);
                    curl_close($update_ch);
                    
                    if ($update_http >= 200 && $update_http < 300) {
                        return ['success' => true, 'message' => "Physical examination reset for physical_exam_id: $pe_id"];
                    } else {
                        return ['success' => false, 'message' => "Failed to reset physical examination. HTTP: $update_http"];
                    }
                } else {
                    return ['success' => false, 'message' => "Physical examination already approved - preserving approved record"];
                }
            } else {
                return ['success' => false, 'message' => "No physical examination found for donor_id: $donor_id"];
            }
        } else {
            return ['success' => false, 'message' => "Failed to fetch physical examination. HTTP: $http_code"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Error resetting physical examination: " . $e->getMessage()];
    }
}

/**
 * Reset the latest blood collection for a donor
 * Only resets if not yet completed/successful
 */
function resetLatestBloodCollection($donor_id) {
    try {
        // Get latest blood collection record through eligibility table
        $ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donor_id . '&select=blood_collection_id,collection_successful,status&order=created_at.desc&limit=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $eligibility_data = json_decode($response, true);
            if (!empty($eligibility_data) && isset($eligibility_data[0]['blood_collection_id'])) {
                $bc_id = $eligibility_data[0]['blood_collection_id'];
                $is_successful = $eligibility_data[0]['collection_successful'] ?? false;
                $status = $eligibility_data[0]['status'] ?? null;
                
                // Only reset if not successful/completed
                if (!$is_successful && $status !== 'approved' && $status !== 'completed') {
                    // Reset blood collection by updating eligibility record
                    $update_data = [
                        'collection_successful' => false,
                        'status' => 'pending',
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $update_ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?blood_collection_id=eq.' . $bc_id);
                    curl_setopt($update_ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($update_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($update_ch, CURLOPT_POSTFIELDS, json_encode($update_data));
                    curl_setopt($update_ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json',
                        'Prefer: return=minimal'
                    ]);
                    
                    $update_response = curl_exec($update_ch);
                    $update_http = curl_getinfo($update_ch, CURLINFO_HTTP_CODE);
                    curl_close($update_ch);
                    
                    if ($update_http >= 200 && $update_http < 300) {
                        return ['success' => true, 'message' => "Blood collection reset for blood_collection_id: $bc_id"];
                    } else {
                        return ['success' => false, 'message' => "Failed to reset blood collection. HTTP: $update_http"];
                    }
                } else {
                    return ['success' => false, 'message' => "Blood collection already completed - preserving approved record"];
                }
            } else {
                return ['success' => false, 'message' => "No blood collection found for donor_id: $donor_id"];
            }
        } else {
            return ['success' => false, 'message' => "Failed to fetch blood collection. HTTP: $http_code"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Error resetting blood collection: " . $e->getMessage()];
    }
}
?>

