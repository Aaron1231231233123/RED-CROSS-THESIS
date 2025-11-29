<?php
// Start output buffering to catch any unexpected output
ob_start();

// Suppress all error output to ensure clean response (matching staff behavior)
error_reporting(0);
ini_set('display_errors', 0);

// Include database connection
require_once '../../conn/db_conn.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Merge JSON body into $_POST if Content-Type is application/json
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== false && $rawBody !== '') {
        $jsonData = json_decode($rawBody, true);
        if (is_array($jsonData)) {
            // JSON fields take precedence but keep any existing form fields
            $_POST = $jsonData + $_POST;
        }
    }
}

/**
 * Check if all processes are complete and update/create eligibility record with 'approved' status
 */
function checkAndUpdateEligibilityToApproved($donor_id, $physical_exam_id, $bloodCollectionData) {
    error_log("Blood Collection Admin - Checking if all processes complete for donor: " . $donor_id);
    
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    
    // 1. Check Medical History - should be approved
    $medicalHistoryApproved = false;
    try {
        $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?donor_id=eq.' . urlencode((string)$donor_id) . '&select=medical_approval&order=created_at.desc&limit=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http === 200 && $resp) {
            $arr = json_decode($resp, true) ?: [];
            if (!empty($arr) && isset($arr[0]['medical_approval'])) {
                $approval = strtolower(trim((string)$arr[0]['medical_approval']));
                $medicalHistoryApproved = ($approval === 'approved');
            }
        }
    } catch (Exception $e) {
        error_log("Blood Collection Admin - Error checking medical history: " . $e->getMessage());
    }
    
    // 2. Check Screening - should be passed (no disapproval_reason)
    $screeningPassed = false;
    $screening_id = null;
    try {
        $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . urlencode((string)$donor_id) . '&select=screening_id,disapproval_reason&order=created_at.desc&limit=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http === 200 && $resp) {
            $arr = json_decode($resp, true) ?: [];
            if (!empty($arr)) {
                $screening_id = $arr[0]['screening_id'] ?? null;
                $disapproval = trim((string)($arr[0]['disapproval_reason'] ?? ''));
                $screeningPassed = (empty($disapproval));
            }
        }
    } catch (Exception $e) {
        error_log("Blood Collection Admin - Error checking screening: " . $e->getMessage());
    }
    
    // 3. Check Physical Examination - should be accepted
    $physicalExamAccepted = false;
    $medical_history_id = null;
    try {
        $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . urlencode((string)$physical_exam_id) . '&select=remarks,medical_history_id&limit=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http === 200 && $resp) {
            $arr = json_decode($resp, true) ?: [];
            if (!empty($arr)) {
                $remarks = strtolower(trim((string)($arr[0]['remarks'] ?? '')));
                $physicalExamAccepted = ($remarks === 'accepted');
                $medical_history_id = $arr[0]['medical_history_id'] ?? null;
            }
        }
    } catch (Exception $e) {
        error_log("Blood Collection Admin - Error checking physical exam: " . $e->getMessage());
    }
    
    // 4. Blood Collection - already successful (passed as parameter)
    $bloodCollectionSuccessful = true; // We're in this function because is_successful === true
    
    error_log("Blood Collection Admin - Process status check - Medical History: " . ($medicalHistoryApproved ? 'Approved' : 'Not Approved') . 
              ", Screening: " . ($screeningPassed ? 'Passed' : 'Not Passed') . 
              ", Physical Exam: " . ($physicalExamAccepted ? 'Accepted' : 'Not Accepted') . 
              ", Blood Collection: Successful");
    
    // If all processes are complete, create/update eligibility with 'approved' status
    if ($medicalHistoryApproved && $screeningPassed && $physicalExamAccepted && $bloodCollectionSuccessful) {
        error_log("Blood Collection Admin - All processes complete! Creating/updating eligibility with 'approved' status");
        
        $blood_collection_id = $bloodCollectionData['blood_collection_id'] ?? null;
        if (!$blood_collection_id && is_array($bloodCollectionData) && isset($bloodCollectionData[0]['blood_collection_id'])) {
            $blood_collection_id = $bloodCollectionData[0]['blood_collection_id'];
        }
        
        // Get additional data needed for eligibility
        $blood_type = null;
        $donation_type = null;
        if ($screening_id) {
            try {
                $ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?screening_id=eq.' . urlencode((string)$screening_id) . '&select=blood_type,donation_type&limit=1');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http === 200 && $resp) {
                    $arr = json_decode($resp, true) ?: [];
                    if (!empty($arr)) {
                        $blood_type = $arr[0]['blood_type'] ?? null;
                        $donation_type = $arr[0]['donation_type'] ?? null;
                    }
                }
            } catch (Exception $e) {
                error_log("Blood Collection Admin - Error fetching screening data: " . $e->getMessage());
            }
        }
        
        // Check if eligibility record already exists
        $existingEligibility = null;
        try {
            $ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . urlencode((string)$donor_id) . '&order=created_at.desc&limit=1');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http === 200 && $resp) {
                $arr = json_decode($resp, true) ?: [];
                if (!empty($arr)) {
                    $existingEligibility = $arr[0];
                }
            }
        } catch (Exception $e) {
            error_log("Blood Collection Admin - Error checking existing eligibility: " . $e->getMessage());
        }
        
        // Prepare eligibility data
        $eligibilityData = [
            'donor_id' => $donor_id,
            'medical_history_id' => $medical_history_id,
            'screening_id' => $screening_id,
            'physical_exam_id' => $physical_exam_id,
            'blood_collection_id' => $blood_collection_id,
            'status' => 'approved',
            'updated_at' => (new DateTime())->format('c')
        ];
        
        if ($blood_type) $eligibilityData['blood_type'] = $blood_type;
        if ($donation_type) $eligibilityData['donation_type'] = $donation_type;
        
        // Add blood collection details if available
        if (isset($bloodCollectionData['blood_bag_type'])) $eligibilityData['blood_bag_type'] = $bloodCollectionData['blood_bag_type'];
        if (isset($bloodCollectionData['amount_taken'])) $eligibilityData['amount_collected'] = $bloodCollectionData['amount_taken'];
        if (isset($bloodCollectionData['unit_serial_number'])) $eligibilityData['unit_serial_number'] = $bloodCollectionData['unit_serial_number'];
        if (isset($bloodCollectionData['start_time'])) $eligibilityData['collection_start_time'] = $bloodCollectionData['start_time'];
        if (isset($bloodCollectionData['end_time'])) $eligibilityData['collection_end_time'] = $bloodCollectionData['end_time'];
        
        // Set start_date and end_date (3 months from now)
        $startDate = new DateTime();
        $endDate = clone $startDate;
        $endDate->modify('+3 months');
        $eligibilityData['start_date'] = $startDate->format('Y-m-d\TH:i:s.000\Z');
        $eligibilityData['end_date'] = $endDate->format('Y-m-d\TH:i:s.000\Z');
        
        if ($existingEligibility && isset($existingEligibility['eligibility_id'])) {
            // Update existing eligibility record
            $eligibility_id = $existingEligibility['eligibility_id'];
            error_log("Blood Collection Admin - Updating existing eligibility record: " . $eligibility_id);
            
            $ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility?eligibility_id=eq.' . urlencode((string)$eligibility_id));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eligibilityData));
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http === 200 || $http === 204) {
                error_log("Blood Collection Admin - Successfully updated eligibility to 'approved' status");
            } else {
                error_log("Blood Collection Admin - Failed to update eligibility. HTTP: " . $http . ", Response: " . $resp);
            }
        } else {
            // Create new eligibility record
            error_log("Blood Collection Admin - Creating new eligibility record with 'approved' status");
            $eligibilityData['created_at'] = (new DateTime())->format('c');
            
            $ch = curl_init(SUPABASE_URL . '/rest/v1/eligibility');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eligibilityData));
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http === 201 || $http === 200) {
                error_log("Blood Collection Admin - Successfully created eligibility with 'approved' status");
            } else {
                error_log("Blood Collection Admin - Failed to create eligibility. HTTP: " . $http . ", Response: " . $resp);
            }
        }
    } else {
        error_log("Blood Collection Admin - Not all processes complete yet. Skipping eligibility update.");
    }
}

try {
    // Log incoming request for debugging
    error_log("Blood Collection Admin - Incoming POST data: " . json_encode($_POST));
    
    // Expect a JSON payload from the modal with these key fields
    $physical_exam_id   = $_POST['physical_exam_id'] ?? null;
    $donor_id           = isset($_POST['donor_id']) ? (int)$_POST['donor_id'] : null;
    $blood_bag_type     = $_POST['blood_bag_type'] ?? null;
    $original_bag_type  = $_POST['blood_bag_type'] ?? null; // keep raw for brand inference
    $amount_taken       = isset($_POST['amount_taken']) ? (int)$_POST['amount_taken'] : 1;
    // Admin workflow: handle is_successful like staff version (accepts 'YES' string or boolean)
    $is_successful_raw  = $_POST['is_successful'] ?? null;
    $is_successful      = null;
    if ($is_successful_raw !== null) {
        if (is_bool($is_successful_raw)) {
            $is_successful = $is_successful_raw;
        } elseif (is_numeric($is_successful_raw)) {
            $is_successful = ((int)$is_successful_raw) === 1;
        } elseif (is_string($is_successful_raw)) {
            $normalized = strtolower(trim($is_successful_raw));
            if (in_array($normalized, ['yes', 'true', '1', 'success', 'successful', 'y', 't'], true)) {
                $is_successful = true;
            } elseif (in_array($normalized, ['no', 'false', '0', 'fail', 'failed', 'unsuccessful', 'n', 'f'], true)) {
                $is_successful = false;
            }
        }
    }
    // Admin workflow: always treat collection as successful if not explicitly set
    if ($is_successful === null) {
        $is_successful = true;
    }
    $donor_reaction     = $_POST['donor_reaction'] ?? null;
    $management_done    = $_POST['management_done'] ?? null;
    $unit_serial_number = $_POST['unit_serial_number'] ?? null;
    $start_time_raw     = $_POST['start_time'] ?? null; // HH:MM from UI
    $end_time_raw       = $_POST['end_time'] ?? null;   // HH:MM from UI
    $phlebotomist       = $_POST['phlebotomist'] ?? null;
    $needs_review       = isset($_POST['needs_review']) ? (bool)$_POST['needs_review'] : false;
    $blood_bag_brand    = $_POST['blood_bag_brand'] ?? null;

    // Server-side resolution of physical_exam_id for admin flow (long-term robust behavior)
    // Only attempt when client didn't provide it but donor_id is present
    if (!$physical_exam_id && $donor_id) {
        error_log("Blood Collection Admin - Resolving physical_exam_id for donor_id: " . $donor_id);
        
        // 1) Try latest eligibility row carrying a physical_exam_id
        $elig = curl_init(SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . urlencode((string)$donor_id) . '&select=physical_exam_id&order=updated_at.desc,created_at.desc&limit=1');
        curl_setopt($elig, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($elig, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]);
        $eligResp = curl_exec($elig);
        $eligCode = curl_getinfo($elig, CURLINFO_HTTP_CODE);
        curl_close($elig);
        if ($eligCode === 200 && $eligResp) {
            $rows = json_decode($eligResp, true) ?: [];
            if (!empty($rows) && !empty($rows[0]['physical_exam_id'])) {
                $physical_exam_id = $rows[0]['physical_exam_id'];
                error_log("Blood Collection Admin - Found physical_exam_id from eligibility: " . $physical_exam_id);
            }
        }

        // 2) Fallback: latest physical_examination by donor_id
        if (!$physical_exam_id) {
            $pe = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?donor_id=eq.' . urlencode((string)$donor_id) . '&select=physical_exam_id&order=updated_at.desc,created_at.desc&limit=1');
            curl_setopt($pe, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($pe, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]);
            $peResp = curl_exec($pe);
            $peCode = curl_getinfo($pe, CURLINFO_HTTP_CODE);
            curl_close($pe);
            if ($peCode === 200 && $peResp) {
                $rows = json_decode($peResp, true) ?: [];
                if (!empty($rows) && !empty($rows[0]['physical_exam_id'])) {
                    $physical_exam_id = $rows[0]['physical_exam_id'];
                    error_log("Blood Collection Admin - Found physical_exam_id from physical_examination: " . $physical_exam_id);
                }
            }
        }

        // 3) Fallback: latest screening_form by donor then physical_examination by screening_id
        if (!$physical_exam_id) {
            $sf = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . urlencode((string)$donor_id) . '&select=screening_id&order=created_at.desc&limit=1');
            curl_setopt($sf, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($sf, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]);
            $sfResp = curl_exec($sf);
            $sfCode = curl_getinfo($sf, CURLINFO_HTTP_CODE);
            curl_close($sf);
            if ($sfCode === 200 && $sfResp) {
                $sRows = json_decode($sfResp, true) ?: [];
                if (!empty($sRows) && !empty($sRows[0]['screening_id'])) {
                    $screening_id_fallback = $sRows[0]['screening_id'];
                    $pe2 = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?screening_id=eq.' . urlencode((string)$screening_id_fallback) . '&select=physical_exam_id&order=updated_at.desc,created_at.desc&limit=1');
                    curl_setopt($pe2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($pe2, CURLOPT_HTTPHEADER, [
                        'apikey: ' . SUPABASE_API_KEY,
                        'Authorization: Bearer ' . SUPABASE_API_KEY,
                        'Accept: application/json'
                    ]);
                    $pe2Resp = curl_exec($pe2);
                    $pe2Code = curl_getinfo($pe2, CURLINFO_HTTP_CODE);
                    curl_close($pe2);
                    if ($pe2Code === 200 && $pe2Resp) {
                        $pRows = json_decode($pe2Resp, true) ?: [];
                        if (!empty($pRows) && !empty($pRows[0]['physical_exam_id'])) {
                            $physical_exam_id = $pRows[0]['physical_exam_id'];
                            error_log("Blood Collection Admin - Found physical_exam_id from screening_form->physical_examination: " . $physical_exam_id);
                        }
                    }
                }
            }
        }
    }

    if (!$physical_exam_id) {
        throw new Exception('Missing required parameters: physical_exam_id');
    }
    if (!$blood_bag_type) {
        throw new Exception('Missing required parameters: blood_bag_type');
    }
    if ($is_successful === null) {
        throw new Exception('Missing required parameters: is_successful');
    }
    if (!$unit_serial_number) {
        throw new Exception('Missing required parameters: unit_serial_number');
    }
    if (!$start_time_raw || !$end_time_raw) {
        throw new Exception('Missing required parameters: start_time/end_time');
    }

    // Normalize inputs and apply server-side fallbacks/inference
    if (is_string($blood_bag_type)) {
        $blood_bag_type = trim($blood_bag_type);
    }
    // 1) Infer blood_bag_brand from blood_bag_type if missing
    if (is_string($blood_bag_brand)) {
        $blood_bag_brand = strtoupper(trim($blood_bag_brand));
    }
    if (empty($blood_bag_brand)) {
        $t = strtoupper((string)$original_bag_type);
        if (strpos($t, 'KARMI') !== false) {
            $blood_bag_brand = 'KARMI';
        } elseif (strpos($t, 'TERUMO') !== false) {
            $blood_bag_brand = 'TERUMO';
        } elseif (strpos($t, 'SPECIAL') !== false) {
            $blood_bag_brand = 'SPECIAL BAG';
        } elseif (strpos($t, 'APHERESIS') !== false) {
            $blood_bag_brand = 'APHERESIS';
        }
    }
    // Enforce brand to be one of allowed values
    $allowedBrands = ['KARMI', 'TERUMO', 'SPECIAL BAG', 'APHERESIS'];
    if (empty($blood_bag_brand) || !in_array($blood_bag_brand, $allowedBrands, true)) {
        throw new Exception('Invalid blood_bag_brand. Allowed: ' . implode(', ', $allowedBrands) . '. Derived from type: ' . ($blood_bag_type ?? 'N/A'));
    }

    // Normalize/validate blood_bag_type against brand
    $normalizeType = function($brand, $type) {
        $t = strtoupper(trim($type));
        $b = strtoupper(trim($brand));
        // Normalize synonyms
        if (in_array($t, ['SINGLE','SING','SGL'], true)) $t = 'S';
        if (in_array($t, ['DOUBLE','DBL'], true)) $t = 'D';
        if (in_array($t, ['TRIPLE'], true)) $t = 'T';
        if (in_array($t, ['QUADRUPLE','QUAD'], true)) $t = 'Q';

        if (in_array($b, ['KARMI','TERUMO'], true)) {
            // DB expects only the code S/D/T/Q in blood_bag_type
            if (in_array($t, ['S','D','T','Q'], true)) return $t;
        }
        if ($b === 'SPECIAL BAG') {
            if ($t === 'FK' || $t === 'FK T&B') return 'FK T&B';
            if ($t === 'TRM' || $t === 'TRM T&B') return 'TRM T&B';
        }
        if ($b === 'APHERESIS') {
            // Allowed codes depend on kit vendor; DB lists these
            if (in_array($t, ['FRES','AMI','HAE','TRI'], true)) return $t;
        }
        return $t; // As-is, validation follows
    };

    // After brand inferred, normalize the type (strip suffix if provided like S-KARMI)
    if (strpos((string)$blood_bag_type, '-') !== false) {
        $blood_bag_type = trim(strtoupper(explode('-', (string)$blood_bag_type, 2)[0]));
    }
    // Always normalize to DB codes to satisfy database constraints
    if (!empty($blood_bag_brand)) {
        $blood_bag_type = $normalizeType($blood_bag_brand, $blood_bag_type);
    } else {
        // If no brand, still normalize common types to DB codes
        $t = strtoupper(trim($blood_bag_type));
        if (in_array($t, ['SINGLE','SING','SGL'], true)) $blood_bag_type = 'S';
        elseif (in_array($t, ['DOUBLE','DBL'], true)) $blood_bag_type = 'D';
        elseif (in_array($t, ['TRIPLE'], true)) $blood_bag_type = 'T';
        elseif (in_array($t, ['QUADRUPLE','QUAD'], true)) $blood_bag_type = 'Q';
        elseif (in_array($t, ['MULTIPLE','MULTI'], true)) $blood_bag_type = 'D'; // Multiple bags default to Double
        // For other types, pass through as-is
    }
    
    $allowedTypes = ['S','D','T','Q','FK T&B','TRM T&B','FRES','AMI','HAE','TRI'];
    if (!in_array($blood_bag_type, $allowedTypes, true)) {
        throw new Exception('Invalid blood_bag_type. Allowed types: ' . implode(', ', $allowedTypes) . ' | received: ' . $blood_bag_type);
    }

    // 2) Auto-fill phlebotomist from session if missing
    if (empty($phlebotomist)) {
        $sessionFirst = isset($_SESSION['first_name']) ? trim($_SESSION['first_name']) : '';
        $sessionLast  = isset($_SESSION['surname']) ? trim($_SESSION['surname']) : '';
        $full = trim($sessionFirst . ' ' . $sessionLast);
        if ($full !== '') {
            $phlebotomist = $full . ' - Staff';
        } elseif (isset($_SESSION['user_id'])) {
            $uid = $_SESSION['user_id'];
            $u = curl_init(SUPABASE_URL . '/rest/v1/users?select=first_name,surname&user_id=eq.' . urlencode((string)$uid) . '&limit=1');
            curl_setopt($u, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($u, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]);
            $uResp = curl_exec($u);
            $uCode = curl_getinfo($u, CURLINFO_HTTP_CODE);
            curl_close($u);
            if ($uCode === 200 && $uResp) {
                $rows = json_decode($uResp, true) ?: [];
                if (!empty($rows)) {
                    $full = trim(($rows[0]['first_name'] ?? '') . ' ' . ($rows[0]['surname'] ?? ''));
                    if ($full !== '') {
                        $phlebotomist = $full . ' - Staff';
                    }
                }
            }
        }
    } else {
        // If phlebotomist is already provided, ensure it has "- Staff" suffix
        if (strpos($phlebotomist, ' - Staff') === false) {
            $phlebotomist = $phlebotomist . ' - Staff';
        }
    }

    // 3) If successful, force needs_review to false
    if ($is_successful === true) {
        $needs_review = false;
    }

    // Helper to convert HH:MM to ISO timestamp today
    $toIsoTs = function($hhmm) {
        $tz = date_default_timezone_get();
        $today = date('Y-m-d');
        $dt = DateTime::createFromFormat('Y-m-d H:i', $today . ' ' . $hhmm, new DateTimeZone($tz));
        if (!$dt) {
            // Fallback: treat as already a timestamp string
            return $hhmm;
        }
        return $dt->format('c'); // ISO 8601 with timezone
    };

    $start_time_iso = $toIsoTs($start_time_raw);
    $end_time_iso   = $toIsoTs($end_time_raw);

    // Enforce unit_serial_number uniqueness across different exams
    if (!empty($unit_serial_number)) {
        $chk = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=physical_exam_id,unit_serial_number&unit_serial_number=eq.' . urlencode($unit_serial_number));
        curl_setopt($chk, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chk, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ]);
        $chkResp = curl_exec($chk);
        $chkCode = curl_getinfo($chk, CURLINFO_HTTP_CODE);
        curl_close($chk);
        if ($chkCode === 200 && $chkResp) {
            $rows = json_decode($chkResp, true) ?: [];
            foreach ($rows as $r) {
                if (isset($r['physical_exam_id']) && (string)$r['physical_exam_id'] !== (string)$physical_exam_id) {
                    throw new Exception('This unit serial number is already in use by a different record.');
                }
            }
        }
    }

    // 3) If successful, force needs_review to false (matching staff behavior)
    if ($is_successful === true) {
        $needs_review = false;
    }

    // Determine collection status to persist (respect DB constraint) - matching staff behavior
    $status = $_POST['status'] ?? '';
    if (is_string($status)) {
        $status = trim($status);
    }
    $statusLower = strtolower($status);
    $allowedStatuses = [
        'pending' => 'pending',
        'incomplete' => 'Incomplete',
        'failed' => 'Failed',
        'yet to be collected' => 'Yet to be collected'
    ];
    if (isset($allowedStatuses[$statusLower])) {
        $status = $allowedStatuses[$statusLower];
    } else {
        $status = 'pending';
    }

    // Build payload for Supabase blood_collection
    $nowIso = (new DateTime())->format('c');

    $payload = [
        'physical_exam_id'   => $physical_exam_id,
        'blood_bag_type'     => $blood_bag_type,
        'blood_bag_brand'    => $blood_bag_brand,
        'amount_taken'       => $amount_taken,
        'is_successful'      => $is_successful,
        'donor_reaction'     => $donor_reaction,
        'management_done'    => $management_done,
        'unit_serial_number' => $unit_serial_number,
        'start_time'         => $start_time_iso,
        'end_time'           => $end_time_iso,
        'status'             => $status,
        'phlebotomist'       => $phlebotomist,
        'updated_at'         => $nowIso
        // Note: access and needs_review are set separately after submission
    ];

    // Check if a collection already exists for this physical_exam_id
    error_log("Blood Collection Admin - Checking existing collection for physical_exam_id: " . $physical_exam_id);
    $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id&physical_exam_id=eq.' . urlencode($physical_exam_id));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    ]);
    $existingResponse = curl_exec($ch);
    $existingCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $existing = [];
    if ($existingCode === 200 && $existingResponse) {
        $existing = json_decode($existingResponse, true) ?: [];
        error_log("Blood Collection Admin - Existing collection check response: " . $existingResponse);
    } else {
        error_log("Blood Collection Admin - Existing collection check failed - Code: " . $existingCode . ", Response: " . $existingResponse);
    }

    if (!empty($existing)) {
        // Update existing row
        $bc_id = $existing[0]['blood_collection_id'] ?? null;
        if (!$bc_id) {
            throw new Exception('Existing record found without ID');
        }
        $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?blood_collection_id=eq.' . urlencode($bc_id));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
    curl_close($ch);
    
        error_log("Blood Collection Admin - Update response - Code: " . $code . ", Response: " . $resp);
        if ($code !== 200) {
            $detail = $resp ?: $curlErr ?: '';
            error_log("Blood Collection Admin - Update failed: " . $detail);
            throw new Exception('Failed to update blood collection' . ($detail ? (': ' . substr($detail, 0, 600)) : ''));
        }

        // Reset access='0' and needs_review=FALSE after successful admin submission
        $reset_ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?blood_collection_id=eq.' . urlencode($bc_id));
        curl_setopt($reset_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($reset_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($reset_ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        curl_setopt($reset_ch, CURLOPT_POSTFIELDS, json_encode([
            'access' => '0',
            'needs_review' => false,
            'updated_at' => $nowIso
        ]));
        $reset_resp = curl_exec($reset_ch);
        $reset_code = curl_getinfo($reset_ch, CURLINFO_HTTP_CODE);
        curl_close($reset_ch);
        
        if ($reset_code >= 200 && $reset_code < 300) {
            error_log("Blood Collection Admin - Access and needs_review reset to 0/FALSE after update");
        } else {
            error_log("Blood Collection Admin - Warning: Failed to reset access/needs_review. HTTP Code: $reset_code");
        }

        // Check if all processes are complete and update eligibility to approved
        if ($is_successful === true && $donor_id) {
            checkAndUpdateEligibilityToApproved($donor_id, $physical_exam_id, json_decode($resp, true));
        }

        echo json_encode([
            'success' => true,
            'message' => 'Blood collection updated',
            'data' => json_decode($resp, true)
        ]);
    } else {
        // Create new row
        $payload['created_at'] = $nowIso;

        // If donor_id is provided, fetch latest screening_id for this donor
        if ($donor_id) {
            $u = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=screening_id,created_at&donor_form_id=eq.' . urlencode((string)$donor_id) . '&order=created_at.desc&limit=1');
            curl_setopt($u, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($u, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json'
            ]);
            $uResp = curl_exec($u);
            $uCode = curl_getinfo($u, CURLINFO_HTTP_CODE);
            curl_close($u);
            if ($uCode === 200 && $uResp) {
                $sRows = json_decode($uResp, true) ?: [];
                if (!empty($sRows) && isset($sRows[0]['screening_id'])) {
                    $payload['screening_id'] = $sRows[0]['screening_id'];
                }
            }
        }

    $ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
    curl_close($ch);
    
        error_log("Blood Collection Admin - Create response - Code: " . $code . ", Response: " . $resp);
        if ($code !== 201 && $code !== 200) {
            $detail = $resp ?: $curlErr ?: '';
            error_log("Blood Collection Admin - Create failed: " . $detail);
            throw new Exception('Failed to create blood collection' . ($detail ? (': ' . substr($detail, 0, 600)) : ''));
        }

        // Reset access='0' and needs_review=FALSE after successful admin submission
        $createdData = json_decode($resp, true);
        $createdBcId = null;
        if (is_array($createdData) && !empty($createdData)) {
            $createdBcId = is_array($createdData[0] ?? null) ? ($createdData[0]['blood_collection_id'] ?? null) : ($createdData['blood_collection_id'] ?? null);
        }
        
        if ($createdBcId) {
            $reset_ch = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?blood_collection_id=eq.' . urlencode($createdBcId));
            curl_setopt($reset_ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($reset_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($reset_ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            curl_setopt($reset_ch, CURLOPT_POSTFIELDS, json_encode([
                'access' => '0',
                'needs_review' => false,
                'updated_at' => $nowIso
            ]));
            $reset_resp = curl_exec($reset_ch);
            $reset_code = curl_getinfo($reset_ch, CURLINFO_HTTP_CODE);
            curl_close($reset_ch);
            
            if ($reset_code >= 200 && $reset_code < 300) {
                error_log("Blood Collection Admin - Access and needs_review reset to 0/FALSE after create");
            } else {
                error_log("Blood Collection Admin - Warning: Failed to reset access/needs_review. HTTP Code: $reset_code");
            }
        }

        // Check if all processes are complete and update eligibility to approved
        if ($is_successful === true && $donor_id) {
            checkAndUpdateEligibilityToApproved($donor_id, $physical_exam_id, $createdData);
        }

        // Invalidate cache to ensure status updates immediately
        try {
            // Include the proper cache invalidation function
            require_once __DIR__ . '/../../public/Dashboards/dashboard-Inventory-System-list-of-donations.php';
            
            // Use the proper cache invalidation function
            invalidateCache();
            
            error_log("Blood Collection Admin - Cache invalidated for donor: " . $donor_id);
        } catch (Exception $cache_error) {
            error_log("Blood Collection Admin - Cache invalidation error: " . $cache_error->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Blood collection created',
            'data' => json_decode($resp, true)
        ]);
    }

} catch (Exception $e) {
    // Return error response (matching staff behavior)
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Flush buffered output (do not discard, or the client receives an empty body)
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}
?>