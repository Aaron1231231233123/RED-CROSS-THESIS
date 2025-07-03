<?php
// Include database connection
include_once '../conn/db_conn.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Helper function to log errors
function logError($message) {
    error_log("Create Eligibility Error: " . $message);
    return ["success" => false, "error" => $message];
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
        if (!isset($data['donor_id']) || !isset($data['screening_id']) || 
            !isset($data['deferral_type']) || !isset($data['disapproval_reason'])) {
            return logError('Missing required fields for defer action');
        }
        
        $donor_id = $data['donor_id'];
        $screening_id = $data['screening_id'];
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
        
        // Get screening information
        $ch_screening = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=*&screening_id=eq.' . $screening_id);
        curl_setopt($ch_screening, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_screening, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $screening_response = curl_exec($ch_screening);
        $screening_http_code = curl_getinfo($ch_screening, CURLINFO_HTTP_CODE);
        curl_close($ch_screening);
        
        if ($screening_http_code !== 200) {
            return logError('Failed to fetch screening information');
        }
        
        $screening_data = json_decode($screening_response, true);
        if (empty($screening_data)) {
            return logError('Screening record not found');
        }
        
        $screening_record = $screening_data[0];
        
        // Get medical history ID if exists
        $medical_history_id = null;
        $ch_medical = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_form_id=eq.' . $donor_id);
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
                error_log("Found medical history ID: $medical_history_id");
            }
        }
        
        // Create physical examination record for ALL deferral types
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
        
        if ($eligibility_check_http_code === 200) {
            $existing_eligibility = json_decode($eligibility_check_response, true);
            if (!empty($existing_eligibility)) {
                error_log("Eligibility record already exists for donor_id: $donor_id");
                return [
                    'success' => false,
                    'message' => 'Eligibility record already exists for this donor',
                    'existing_eligibility_id' => $existing_eligibility[0]['eligibility_id']
                ];
            }
        }

        // Create eligibility record
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
        
        // Update screening form with disapproval reason
        $update_screening_data = [
            'disapproval_reason' => $disapproval_reason,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
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