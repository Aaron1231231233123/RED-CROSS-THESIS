<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set response headers
header('Content-Type: application/json');

/**
 * Process physical examination results and update eligibility status
 * This function will:
 * 1. Find or create an eligibility record for the donor
 * 2. Update the eligibility status based on physical examination remarks
 * 3. Store the rejection reason if applicable
 */
function processPhysicalExam($physicalExamId, $donorId, $remarks, $reason = '') {
    if (empty($physicalExamId) || empty($donorId)) {
        return ['success' => false, 'error' => 'Missing required parameters'];
    }
    
    try {
        // Determine eligibility status based on remarks Enum value
        $status = 'pending'; // Default status is pending
        
        // Process based on the Enum values from the dropdown
        if (!empty($remarks)) {
            switch ($remarks) {
                case 'Accepted':
                    $status = 'approved';
                    break;
                case 'Temporarily Deferred':
                case 'Permanently Deferred':
                case 'Refused':
                    $status = 'declined';
                    break;
                default:
                    // If not a known Enum value, keep as pending
                    $status = 'pending';
                    break;
            }
        }
        
        // Log the determined status for debugging
        error_log("Determined status based on remarks '$remarks': $status");
        
        // Fetch all necessary related IDs and data to include in the eligibility record
        
        // 1. Get screening ID, blood type, donation type
        $screeningCurl = curl_init();
        curl_setopt_array($screeningCurl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/screening_form?donor_form_id=eq." . $donorId . "&select=screening_id,blood_type,donation_type&order=created_at.desc&limit=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ],
        ]);
        
        $screeningResponse = curl_exec($screeningCurl);
        $screeningErr = curl_error($screeningCurl);
        curl_close($screeningCurl);
        
        $screeningId = null;
        $bloodType = null;
        $donationType = null;
        
        if (!$screeningErr && !empty($screeningResponse)) {
            $screeningData = json_decode($screeningResponse, true);
            if (is_array($screeningData) && !empty($screeningData)) {
                $screeningId = $screeningData[0]['screening_id'];
                $bloodType = $screeningData[0]['blood_type'] ?? null;
                $donationType = $screeningData[0]['donation_type'] ?? null;
                error_log("Found screening_id: $screeningId, blood_type: $bloodType, donation_type: $donationType");
            } else {
                error_log("No screening data found for donor ID $donorId");
            }
        } else {
            error_log("Error fetching screening data: $screeningErr");
        }
        
        // 2. Get medical history ID
        $medicalCurl = curl_init();
        curl_setopt_array($medicalCurl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/medical_history?donor_id=eq." . $donorId . "&select=medical_history_id&order=created_at.desc&limit=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "apikey: " . SUPABASE_API_KEY,
                "Authorization: Bearer " . SUPABASE_API_KEY,
                "Content-Type: application/json"
            ],
        ]);
        
        $medicalResponse = curl_exec($medicalCurl);
        $medicalErr = curl_error($medicalCurl);
        curl_close($medicalCurl);
        
        $medicalHistoryId = null;
        
        if (!$medicalErr && !empty($medicalResponse)) {
            $medicalData = json_decode($medicalResponse, true);
            if (is_array($medicalData) && !empty($medicalData)) {
                $medicalHistoryId = $medicalData[0]['medical_history_id'];
                error_log("Found medical_history_id: $medicalHistoryId");
            } else {
                error_log("No medical history data found for donor ID $donorId");
            }
        } else {
            error_log("Error fetching medical history data: $medicalErr");
        }
        
        // 3. Get blood collection ID and data if available
        $bloodCollectionId = null;
        $collectionSuccessful = false;
        $bloodBagType = null;
        $unitSerialNumber = null;
        
        if ($screeningId) {
            $collectionCurl = curl_init();
            curl_setopt_array($collectionCurl, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/blood_collection?screening_id=eq." . $screeningId . "&select=blood_collection_id,is_successful,blood_bag_type,unit_serial_number,amount_taken&order=created_at.desc&limit=1",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "apikey: " . SUPABASE_API_KEY,
                    "Authorization: Bearer " . SUPABASE_API_KEY,
                    "Content-Type: application/json"
                ],
            ]);
            
            $collectionResponse = curl_exec($collectionCurl);
            $collectionErr = curl_error($collectionCurl);
            curl_close($collectionCurl);
            
            if (!$collectionErr && !empty($collectionResponse)) {
                $collectionData = json_decode($collectionResponse, true);
                if (is_array($collectionData) && !empty($collectionData)) {
                    $bloodCollectionId = $collectionData[0]['blood_collection_id'];
                    $collectionSuccessful = $collectionData[0]['is_successful'] ?? false;
                    $bloodBagType = $collectionData[0]['blood_bag_type'] ?? null;
                    $unitSerialNumber = $collectionData[0]['unit_serial_number'] ?? null;
                    $amountTaken = $collectionData[0]['amount_taken'] ?? null;
                    error_log("Found blood_collection_id: $bloodCollectionId, successful: " . ($collectionSuccessful ? 'true' : 'false'));
                }
            }
        }
        
        // Calculate end_date based on status and reason
        $endDate = new DateTime();
        if ($status === 'approved') {
            $endDate->modify('+9 months'); // Standard waiting period for successful donations
        } elseif ($status === 'declined') {
            // For declined donors, set end date based on reason
            if (stripos($remarks, 'Permanently') !== false) {
                $endDate->modify('+100 years'); // Effectively permanent
            } elseif (stripos($remarks, 'Temporarily') !== false) {
                $endDate->modify('+6 months'); // Default for temporary deferrals
            } else {
                $endDate->modify('+3 months'); // Default for other declined reasons
            }
        } else {
            $endDate->modify('+3 days'); // Default for pending
        }
        
        // Format end date for database
        $endDateFormatted = $endDate->format('Y-m-d\TH:i:s.000\Z');
        
        // First, check if an eligibility record already exists for this donor
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?donor_id=eq." . $donorId . "&select=eligibility_id&order=created_at.desc&limit=1",
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
            return ['success' => false, 'error' => 'Error checking for existing eligibility: ' . $err];
        }
        
        $eligibilityData = json_decode($response, true);
        $timestamp = date('Y-m-d H:i:s');
        
        // If eligibility exists, update it
        if (is_array($eligibilityData) && !empty($eligibilityData)) {
            $eligibilityId = $eligibilityData[0]['eligibility_id'];
            
            // Prepare comprehensive update data with all form IDs and information
            $updateData = [
                'status' => $status,
                'physical_exam_id' => $physicalExamId,
                'updated_at' => $timestamp,
                'remarks' => $remarks,
                'end_date' => $endDateFormatted
            ];
            
            // Add all collected IDs and data
            if ($screeningId) $updateData['screening_id'] = $screeningId;
            if ($medicalHistoryId) $updateData['medical_history_id'] = $medicalHistoryId;
            if ($bloodCollectionId) $updateData['blood_collection_id'] = $bloodCollectionId;
            if ($bloodType) $updateData['blood_type'] = $bloodType;
            if ($donationType) $updateData['donation_type'] = $donationType;
            if ($bloodBagType) $updateData['blood_bag_type'] = $bloodBagType;
            if ($unitSerialNumber) $updateData['unit_serial_number'] = $unitSerialNumber;
            if (isset($amountTaken)) $updateData['amount_collected'] = $amountTaken;
            if (isset($collectionSuccessful)) $updateData['collection_successful'] = $collectionSuccessful;
            
            // Add disapproval reason if declined (renamed from rejection_reason to match schema)
            if ($status === 'declined' && !empty($reason)) {
                $updateData['disapproval_reason'] = $reason;
            }
            
            // Update eligibility record
            $updateCurl = curl_init();
            curl_setopt_array($updateCurl, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility?eligibility_id=eq." . $eligibilityId,
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
            
            $updateResponse = curl_exec($updateCurl);
            $updateHttpCode = curl_getinfo($updateCurl, CURLINFO_HTTP_CODE);
            $updateErr = curl_error($updateCurl);
            curl_close($updateCurl);
            
            if ($updateErr) {
                return ['success' => false, 'error' => 'Error updating eligibility: ' . $updateErr];
            }
            
            if ($updateHttpCode != 204) {
                return ['success' => false, 'error' => 'Failed to update eligibility. HTTP Code: ' . $updateHttpCode];
            }
            
            return [
                'success' => true, 
                'message' => 'Eligibility record updated successfully',
                'status' => $status,
                'remarks' => $remarks,
                'eligibility_id' => $eligibilityId
            ];
        } 
        // If no eligibility record exists, create one
        else {
            // Prepare comprehensive eligibility data with all form IDs and information
            $newEligibilityData = [ 
                'donor_id' => $donorId,
                'physical_exam_id' => $physicalExamId,
                'status' => $status,
                'remarks' => $remarks,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'start_date' => date('Y-m-d\TH:i:s.000\Z'),
                'end_date' => $endDateFormatted
            ];
            
            // Add all collected IDs and data
            if ($screeningId) $newEligibilityData['screening_id'] = $screeningId;
            if ($medicalHistoryId) $newEligibilityData['medical_history_id'] = $medicalHistoryId;
            if ($bloodCollectionId) $newEligibilityData['blood_collection_id'] = $bloodCollectionId;
            if ($bloodType) $newEligibilityData['blood_type'] = $bloodType;
            if ($donationType) $newEligibilityData['donation_type'] = $donationType;
            if ($bloodBagType) $newEligibilityData['blood_bag_type'] = $bloodBagType;
            if ($unitSerialNumber) $newEligibilityData['unit_serial_number'] = $unitSerialNumber;
            if (isset($amountTaken)) $newEligibilityData['amount_collected'] = $amountTaken;
            if (isset($collectionSuccessful)) $newEligibilityData['collection_successful'] = $collectionSuccessful;
            
            // Add disapproval reason if declined (renamed from rejection_reason to match schema)
            if ($status === 'declined' && !empty($reason)) {
                $newEligibilityData['disapproval_reason'] = $reason;
            }
            
            // Create eligibility record
            $createCurl = curl_init();
            curl_setopt_array($createCurl, [
                CURLOPT_URL => SUPABASE_URL . "/rest/v1/eligibility",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($newEligibilityData),
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
                return ['success' => false, 'error' => 'Error creating eligibility record: ' . $createErr];
            }
            
            $newEligibility = json_decode($createResponse, true);
            if (!is_array($newEligibility) || empty($newEligibility)) {
                return ['success' => false, 'error' => 'Failed to create eligibility record: ' . $createResponse];
            }
            
            return [
                'success' => true, 
                'message' => 'New eligibility record created successfully',
                'status' => $status,
                'remarks' => $remarks,
                'eligibility_id' => $newEligibility[0]['eligibility_id']
            ];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

// Process incoming request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Get JSON data from request body
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        exit;
    }
    
    // Log incoming data for debugging
    error_log('Received physical exam data: ' . json_encode($data));
    
    // Check required fields
    if (!isset($data['physical_exam_id']) || !isset($data['donor_id'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields: physical_exam_id, donor_id']);
        exit;
    }
    
    // Process the physical examination
    $result = processPhysicalExam(
        $data['physical_exam_id'], 
        $data['donor_id'], 
        $data['remarks'] ?? '', 
        $data['reason'] ?? $data['disapproval_reason'] ?? ''
    );
    
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed']);
}
?> 