<?php
// Start output buffering to prevent any accidental output
ob_start();

// Suppress errors from being displayed (we'll handle them in JSON)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log that the file is being accessed
@file_put_contents('../../../assets/logs/debug.log', "[" . date('Y-m-d H:i:s') . "] admin-declaration-form-process.php accessed\n", FILE_APPEND | LOCK_EX);

session_start();
require_once '../../../assets/conn/db_conn.php';

// Clear any output that might have been generated
ob_clean();

// Set JSON response headers
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    ob_end_flush();
    exit();
}

// Check for correct roles (admin role_id 1 or staff role_id 3)
if (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    ob_end_flush();
    exit();
}

// Handle debug log requests (but don't exit if there's other data)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug_log']) && count($_POST) === 1) {
    // Only handle debug log requests if that's the only data
    $log_message = "[" . date('Y-m-d H:i:s') . "] JS DEBUG: " . $_POST['debug_log'] . "\n";
    @file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
    ob_clean();
    echo json_encode(['success' => true]);
    ob_end_flush();
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Log any debug log entries that come with other data
        if (isset($_POST['debug_log'])) {
            if (is_array($_POST['debug_log'])) {
                foreach ($_POST['debug_log'] as $log_entry) {
                    $log_message = "[" . date('Y-m-d H:i:s') . "] JS DEBUG: " . $log_entry . "\n";
                    file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                }
            } else {
                $log_message = "[" . date('Y-m-d H:i:s') . "] JS DEBUG: " . $_POST['debug_log'] . "\n";
                file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
            }
        }
        
        // Debug logging
        $log_message = "[" . date('Y-m-d H:i:s') . "] === ADMIN DECLARATION FORM PROCESS START ===\n";
        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
        
        $log_message = "[" . date('Y-m-d H:i:s') . "] POST data: " . print_r($_POST, true) . "\n";
        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
        
        $log_message = "[" . date('Y-m-d H:i:s') . "] Session data: " . print_r($_SESSION, true) . "\n";
        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
        
        $log_message = "[" . date('Y-m-d H:i:s') . "] REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
        
        $log_message = "[" . date('Y-m-d H:i:s') . "] Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set') . "\n";
        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
        
        $donor_id = isset($_POST['donor_id']) ? $_POST['donor_id'] : null;
        $action = isset($_POST['action']) ? $_POST['action'] : null;
        
        $log_message = "[" . date('Y-m-d H:i:s') . "] Donor ID: " . $donor_id . "\n";
        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
        
        $log_message = "[" . date('Y-m-d H:i:s') . "] Action: " . $action . "\n";
        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
        
        if (!$donor_id) {
            throw new Exception('Missing donor ID');
        }
        
        if ($action === 'complete') {
            $log_message = "[" . date('Y-m-d H:i:s') . "] Processing complete action\n";
            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
            
            // First, submit the screening data if it exists
            if (isset($_POST['screening_data'])) {
                $log_message = "[" . date('Y-m-d H:i:s') . "] Screening data found in POST\n";
                file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                
                $screening_data_json = $_POST['screening_data'];
                $log_message = "[" . date('Y-m-d H:i:s') . "] Screening data JSON: " . $screening_data_json . "\n";
                file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                
                $screening_data = json_decode($screening_data_json, true);
                $log_message = "[" . date('Y-m-d H:i:s') . "] Decoded screening data: " . print_r($screening_data, true) . "\n";
                file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                
                if ($screening_data) {
                    $log_message = "[" . date('Y-m-d H:i:s') . "] Processing screening data\n";
                    file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                    
                    // Get medical_history_id for this donor
                    $medical_history_id = null;
                    $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=medical_history_id&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY
                    ]);

                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($http_code === 200) {
                        $medical_data = json_decode($response, true);
                        if (!empty($medical_data)) {
                            $medical_history_id = $medical_data[0]['medical_history_id'];
                            $log_message = "[" . date('Y-m-d H:i:s') . "] Found medical_history_id: " . $medical_history_id . "\n";
                            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        }
                    }
                    
                    // Process and submit screening data
                    $screening_submission_data = [
                        'donor_form_id' => $donor_id,
                        'medical_history_id' => $medical_history_id, // Include the medical_history_id
                        'interviewer_id' => $_SESSION['user_id'],
                        'interview_date' => date('Y-m-d') // Use interview_date (date format) instead of screening_date
                    ];
                    
                    // Add screening form fields
                    if (isset($screening_data['donation-type'])) {
                        // Convert donation type to valid enum value
                        $input_type = $screening_data['donation-type'];
                        $donation_type = '';
                        
                        // Map common input values to valid enum values
                        switch (strtolower($input_type)) {
                            case 'walk-in':
                            case 'walk in':
                            case 'walkin':
                                $donation_type = 'walk-in';
                                break;
                            case 'in-house':
                            case 'in house':
                            case 'inhouse':
                                $donation_type = 'in-house';
                                break;
                            case 'replacement':
                                $donation_type = 'replacement';
                                break;
                            case 'patient-directed':
                            case 'patient directed':
                            case 'patientdirected':
                                $donation_type = 'patient-directed';
                                break;
                            case 'mobile':
                                $donation_type = 'mobile';
                                break;
                            case 'mobile-walk-in':
                            case 'mobile walk-in':
                            case 'mobile walk in':
                                $donation_type = 'mobile-walk-in';
                                break;
                            case 'mobile-replacement':
                            case 'mobile replacement':
                                $donation_type = 'mobile-replacement';
                                break;
                            case 'mobile-patient-directed':
                            case 'mobile patient-directed':
                            case 'mobile patient directed':
                                $donation_type = 'mobile-patient-directed';
                                break;
                            default:
                                // Fallback: convert to lowercase and replace spaces with hyphens
                                $donation_type = strtolower($input_type);
                                $donation_type = str_replace(' ', '-', $donation_type);
                                break;
                        }
                        
                        $screening_submission_data['donation_type'] = $donation_type;
                    }
                    if (isset($screening_data['mobile-place']) && !empty($screening_data['mobile-place'])) {
                        $screening_submission_data['mobile_location'] = $screening_data['mobile-place'];
                    }
                    if (isset($screening_data['mobile-organizer']) && !empty($screening_data['mobile-organizer'])) {
                        $screening_submission_data['mobile_organizer'] = $screening_data['mobile-organizer'];
                    }
                    if (isset($screening_data['patient-name']) && !empty($screening_data['patient-name'])) {
                        $screening_submission_data['patient_name'] = $screening_data['patient-name'];
                    }
                    if (isset($screening_data['hospital']) && !empty($screening_data['hospital'])) {
                        $screening_submission_data['hospital'] = $screening_data['hospital'];
                    }
                    if (isset($screening_data['patient-blood-type']) && !empty($screening_data['patient-blood-type'])) {
                        $screening_submission_data['patient_blood_type'] = $screening_data['patient-blood-type'];
                    }
                    if (isset($screening_data['component-type']) && !empty($screening_data['component-type'])) {
                        $screening_submission_data['component_type'] = $screening_data['component-type'];
                    }
                    if (isset($screening_data['no-units']) && !empty($screening_data['no-units'])) {
                        $screening_submission_data['units_needed'] = $screening_data['no-units'];
                    }
                    if (isset($screening_data['blood-type']) && !empty($screening_data['blood-type'])) {
                        $screening_submission_data['blood_type'] = $screening_data['blood-type'];
                    }
                    if (isset($screening_data['body-wt']) && !empty($screening_data['body-wt'])) {
                        $screening_submission_data['body_weight'] = $screening_data['body-wt'];
                    }
                    if (isset($screening_data['sp-gr']) && !empty($screening_data['sp-gr'])) {
                        $screening_submission_data['specific_gravity'] = $screening_data['sp-gr'];
                    }
                    if (isset($screening_data['interviewer']) && !empty($screening_data['interviewer'])) {
                        $screening_submission_data['staff'] = $screening_data['interviewer'];
                    }
                    
                    // Submit screening data directly to database
                    $screening_submission_data['updated_at'] = date('Y-m-d H:i:s');
                    // Note: created_at will only be added for INSERT operations, never for UPDATE
                    
                    $log_message = "[" . date('Y-m-d H:i:s') . "] Screening submission data: " . print_r($screening_submission_data, true) . "\n";
                    file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                    
                    // ALWAYS try UPDATE first for screening form (prioritize UPDATE over INSERT)
                    $log_message = "[" . date('Y-m-d H:i:s') . "] Attempting UPDATE first for screening form (UPDATE priority)\n";
                    file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                    
                    // First, try to UPDATE existing screening data (without created_at)
                    $screening_update_data = $screening_submission_data;
                    unset($screening_update_data['created_at']); // Remove created_at for UPDATE
                    
                    $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . $donor_id);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($screening_update_data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Content-Type: application/json',
                        'Prefer: return=representation'
                    ]);
                    
                    $screening_response = curl_exec($ch);
                    $screening_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    $log_message = "[" . date('Y-m-d H:i:s') . "] Screening form UPDATE attempt - HTTP Code: " . $screening_http_code . "\n";
                    file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                    
                    $log_message = "[" . date('Y-m-d H:i:s') . "] Screening form UPDATE attempt - Response: " . $screening_response . "\n";
                    file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                    
                    // If UPDATE failed (no existing record) OR returned empty result, try INSERT
                    if ($screening_http_code !== 200 || empty(json_decode($screening_response, true))) {
                        $log_message = "[" . date('Y-m-d H:i:s') . "] UPDATE failed, attempting INSERT for screening form\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        
                        // For INSERT, include created_at
                        $screening_insert_data = $screening_submission_data;
                        $screening_insert_data['created_at'] = date('Y-m-d H:i:s');
                        
                        $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($screening_insert_data));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json',
                            'Prefer: return=representation'
                        ]);
                        
                        $screening_response = curl_exec($ch);
                        $screening_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        $log_message = "[" . date('Y-m-d H:i:s') . "] Screening form INSERT attempt - HTTP Code: " . $screening_http_code . "\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        
                        $log_message = "[" . date('Y-m-d H:i:s') . "] Screening form INSERT attempt - Response: " . $screening_response . "\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                    }
                    
                    if ($screening_http_code === 201 || $screening_http_code === 200) {
                        $log_message = "[" . date('Y-m-d H:i:s') . "] Screening form data " . ($screening_http_code === 201 ? 'inserted' : 'updated') . " successfully (UPDATE priority)\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        
                        // Extract screening_id from response for physical examination
                        $screening_response_data = json_decode($screening_response, true);
                        $screening_id = null;
                        if (!empty($screening_response_data) && isset($screening_response_data[0]['screening_id'])) {
                            $screening_id = $screening_response_data[0]['screening_id'];
                            $log_message = "[" . date('Y-m-d H:i:s') . "] Extracted screening_id: " . $screening_id . "\n";
                            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        }
                        
                        // Update medical_history needs_review to false (staff has completed the medical history interview)
                        // The physician will review during the physical examination stage
                        // Note: medical_approval should remain null - not set to 'Not Approved' at this stage
                        $medical_update_data = [
                            'donor_id' => $donor_id, // Use donor_id for medical_history table
                            'needs_review' => false, // Set to false - staff has completed the interview
                            // medical_approval is intentionally NOT set - should remain null
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $mh_ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . $donor_id);
                        curl_setopt($mh_ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($mh_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                        curl_setopt($mh_ch, CURLOPT_POSTFIELDS, json_encode($medical_update_data));
                        curl_setopt($mh_ch, CURLOPT_HTTPHEADER, [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json',
                            'Prefer: return=representation'
                        ]);
                        
                        $mh_response = curl_exec($mh_ch);
                        $mh_http_code = curl_getinfo($mh_ch, CURLINFO_HTTP_CODE);
                        curl_close($mh_ch);
                        
                        $log_message = "[" . date('Y-m-d H:i:s') . "] Medical history update - HTTP Code: " . $mh_http_code . "\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        
                        $log_message = "[" . date('Y-m-d H:i:s') . "] Medical history update - Response: " . $mh_response . "\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        
                        if ($mh_http_code === 200) {
                            $log_message = "[" . date('Y-m-d H:i:s') . "] Medical history updated successfully - needs_review=false, medical_approval remains null (staff has completed the medical history interview, awaiting physician approval)\n";
                            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        } else {
                            $log_message = "[" . date('Y-m-d H:i:s') . "] Failed to update medical history: " . $mh_response . "\n";
                            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        }
                        
                        // Handle physical examination - ALWAYS try UPDATE first (prioritize UPDATE over INSERT)
                        $physical_exam_data = [
                            'donor_id' => $donor_id, // Use donor_id (not donor_form_id) for physical_examination table
                            'screening_id' => $screening_id, // Use the actual screening_id UUID from screening form response
                            'needs_review' => true,
                            'remarks' => 'Pending', // Set remarks to Pending after declaration form submission
                            'updated_at' => date('Y-m-d H:i:s') // Only updated_at for every transaction
                        ];
                        
                        $log_message = "[" . date('Y-m-d H:i:s') . "] Physical examination data: " . print_r($physical_exam_data, true) . "\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        
                        $log_message = "[" . date('Y-m-d H:i:s') . "] Attempting UPDATE first for physical examination (UPDATE priority)\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        
                        // First, try to UPDATE existing physical examination data (without created_at)
                        $physical_update_data = $physical_exam_data;
                        unset($physical_update_data['created_at']); // Remove created_at for UPDATE
                        
                        $pe_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . $donor_id);
                        curl_setopt($pe_ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($pe_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                        curl_setopt($pe_ch, CURLOPT_POSTFIELDS, json_encode($physical_update_data));
                        curl_setopt($pe_ch, CURLOPT_HTTPHEADER, [
                            'apikey: ' . SUPABASE_API_KEY,
                            'Authorization: Bearer ' . SUPABASE_API_KEY,
                            'Content-Type: application/json',
                            'Prefer: return=representation'
                        ]);
                        
                        $pe_response = curl_exec($pe_ch);
                        $pe_http_code = curl_getinfo($pe_ch, CURLINFO_HTTP_CODE);
                        curl_close($pe_ch);
                        
                        $log_message = "[" . date('Y-m-d H:i:s') . "] Physical examination UPDATE attempt - HTTP Code: " . $pe_http_code . "\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        
                        $log_message = "[" . date('Y-m-d H:i:s') . "] Physical examination UPDATE attempt - Response: " . $pe_response . "\n";
                        file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        
                        // If UPDATE failed (no existing record) OR returned empty result, try INSERT
                        if ($pe_http_code !== 200 || empty(json_decode($pe_response, true))) {
                            $log_message = "[" . date('Y-m-d H:i:s') . "] UPDATE failed, attempting INSERT for physical examination\n";
                            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                            
                            // For INSERT, include created_at
                            $physical_insert_data = $physical_exam_data;
                            $physical_insert_data['created_at'] = date('Y-m-d H:i:s');
                            
                            $pe_ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
                            curl_setopt($pe_ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($pe_ch, CURLOPT_POST, true);
                            curl_setopt($pe_ch, CURLOPT_POSTFIELDS, json_encode($physical_insert_data));
                            curl_setopt($pe_ch, CURLOPT_HTTPHEADER, [
                                'apikey: ' . SUPABASE_API_KEY,
                                'Authorization: Bearer ' . SUPABASE_API_KEY,
                                'Content-Type: application/json',
                                'Prefer: return=representation'
                            ]);
                            
                            $pe_response = curl_exec($pe_ch);
                            $pe_http_code = curl_getinfo($pe_ch, CURLINFO_HTTP_CODE);
                            curl_close($pe_ch);
                            
                            $log_message = "[" . date('Y-m-d H:i:s') . "] Physical examination INSERT attempt - HTTP Code: " . $pe_http_code . "\n";
                            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                            
                            $log_message = "[" . date('Y-m-d H:i:s') . "] Physical examination INSERT attempt - Response: " . $pe_response . "\n";
                            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        }
                        
                        if ($pe_http_code === 201 || $pe_http_code === 200) {
                            $log_message = "[" . date('Y-m-d H:i:s') . "] Physical examination data " . ($pe_http_code === 201 ? 'inserted' : 'updated') . " successfully - needs_review=true, remarks='Pending' (UPDATE priority)\n";
                            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        } else {
                            $log_message = "[" . date('Y-m-d H:i:s') . "] Failed to process physical examination: " . $pe_response . "\n";
                            file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                        }
                } else {
                    $log_message = "[" . date('Y-m-d H:i:s') . "] Screening form submission failed with HTTP code: " . $screening_http_code . " Response: " . $screening_response . "\n";
                    file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
                }
            } else {
                $log_message = "[" . date('Y-m-d H:i:s') . "] No screening data found in POST request\n";
                file_put_contents('../../../assets/logs/debug.log', $log_message, FILE_APPEND | LOCK_EX);
            }
            }
            
            // NEW: Transfer all data to declaration form including screening data
            $declaration_data = [
                'donor_id' => $donor_id,
                'declaration_date' => date('Y-m-d'),
                'interviewer_id' => $_SESSION['user_id'],
                'completed' => true
            ];
            
            // Get transferred screening data from session
            if (isset($_SESSION['transferred_screening_data'])) {
                $screening_data = $_SESSION['transferred_screening_data'];
                
                // Transfer screening fields to declaration
                $screening_fields_to_transfer = [
                    'screening_body_weight', 'screening_specific_gravity', 'screening_blood_type', 
                    'screening_donation_type', 'screening_mobile_location', 'screening_mobile_organizer',
                    'screening_patient_name', 'screening_hospital', 'screening_patient_blood_type',
                    'screening_component_type', 'screening_units_needed'
                ];
                
                foreach ($screening_fields_to_transfer as $field) {
                    if (isset($screening_data[$field])) {
                        $declaration_data[$field] = $screening_data[$field];
                    }
                }
                
                // Also transfer medical history approval status
                if (isset($screening_data['medical_approval'])) {
                    $declaration_data['medical_approval'] = $screening_data['medical_approval'];
                }
                
                error_log("Transferred screening data to declaration: " . print_r($declaration_data, true));
            }
            
            // Store declaration data in session for potential use
            $_SESSION['declaration_data'] = $declaration_data;
            
            // Store that we've completed the declaration form
            $_SESSION['declaration_completed'] = true;
            
            // Log successful registration
            error_log("Admin Donor registration completed successfully for donor ID: " . $donor_id);
            
            // Set a flag for registered donor in session
            $_SESSION['donor_registered'] = true;
            $_SESSION['donor_registered_id'] = $donor_id;
            
            // Fetch donor name for logging
            $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . $donor_id . '&select=first_name,surname');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $donorData = json_decode($response, true);
                if (!empty($donorData)) {
                    $donorName = $donorData[0]['first_name'] . ' ' . $donorData[0]['surname'];
                    $_SESSION['donor_registered_name'] = $donorName;
                    error_log("Admin Declaration form - Donor registration completed for: " . $donorName . " (ID: " . $donor_id . ")");
                }
            }
            
            // Clear any previous registration data from session to avoid conflicts
            unset($_SESSION['donor_form_data']);
            unset($_SESSION['donor_form_timestamp']);
            unset($_SESSION['donor_id']);
            unset($_SESSION['medical_history_id']);
            unset($_SESSION['screening_id']);
            unset($_SESSION['transferred_screening_data']);
            unset($_SESSION['medical_history_processed']);
            
            // Invalidate cache to ensure status updates immediately (admin dashboard only)
            try {
                // Check if we're in admin context by looking for the admin dashboard file
                $adminDashboardPath = __DIR__ . '/../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php';
                if (file_exists($adminDashboardPath)) {
                    // Include the proper cache invalidation function
                    require_once $adminDashboardPath;
                    
                    // Use the proper cache invalidation function if available
                    if (function_exists('invalidateCache')) {
                        invalidateCache();
                        error_log("Admin Declaration Form Process - Cache invalidated for donor: " . $donor_id);
                    }
                }
            } catch (Exception $cache_error) {
                error_log("Admin Declaration Form Process - Cache invalidation error: " . $cache_error->getMessage());
            }
            
            // Clear any output before sending success response
            ob_clean();
            echo json_encode([
                'success' => true, 
                'message' => 'Admin declaration form completed successfully',
                'donor_id' => $donor_id
            ]);
        } else {
            throw new Exception('Invalid action specified');
        }
        
    } catch (Exception $e) {
        // Clear any output before sending error response
        ob_clean();
        error_log("Error in admin declaration form processing: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    // Clear any output before sending error response
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// End output buffering and send response
ob_end_flush();
?>


