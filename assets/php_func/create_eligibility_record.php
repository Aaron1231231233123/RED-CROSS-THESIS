<?php
// Include database connection
include_once '../conn/db_conn.php';

// Set headers
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    
    $result = createEligibilityRecord($data);
    echo json_encode($result);
} else {
    echo json_encode(["success" => false, "error" => "Only POST method is allowed"]);
} 