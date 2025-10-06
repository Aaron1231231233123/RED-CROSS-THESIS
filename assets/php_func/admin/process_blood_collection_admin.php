<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/debug.log');

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

try {
    // Log incoming request for debugging
    error_log("Blood Collection Admin - Incoming POST data: " . json_encode($_POST));
    
    // Expect a JSON payload from the modal with these key fields
    $physical_exam_id   = $_POST['physical_exam_id'] ?? null;
    $donor_id           = isset($_POST['donor_id']) ? (int)$_POST['donor_id'] : null;
    $blood_bag_type     = $_POST['blood_bag_type'] ?? null;
    $original_bag_type  = $_POST['blood_bag_type'] ?? null; // keep raw for brand inference
    $amount_taken       = isset($_POST['amount_taken']) ? (int)$_POST['amount_taken'] : 1;
    $is_successful_raw  = $_POST['is_successful'] ?? null;
    $is_successful      = null;
    if ($is_successful_raw !== null) {
        // Handle both boolean and string values from form, convert to proper boolean
        if (is_bool($is_successful_raw)) {
            $is_successful = $is_successful_raw;
        } elseif (is_string($is_successful_raw)) {
            $is_successful = strtoupper($is_successful_raw) === 'YES';
        } else {
            $is_successful = (bool)$is_successful_raw;
        }
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
        error_log("Blood Collection Admin - Missing physical_exam_id after resolution attempts");
        throw new Exception('Missing required parameters: physical_exam_id');
    }
    if (!$blood_bag_type) {
        error_log("Blood Collection Admin - Missing blood_bag_type");
        throw new Exception('Missing required parameters: blood_bag_type');
    }
    if ($is_successful === null) {
        error_log("Blood Collection Admin - Missing is_successful");
        throw new Exception('Missing required parameters: is_successful');
    }
    if (!$unit_serial_number) {
        error_log("Blood Collection Admin - Missing unit_serial_number");
        throw new Exception('Missing required parameters: unit_serial_number');
    }
    if (!$start_time_raw || !$end_time_raw) {
        error_log("Blood Collection Admin - Missing start_time/end_time");
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
    // Enforce brand to be one of allowed values (admin can allow empty brand)
    $allowedBrands = ['KARMI', 'TERUMO', 'SPECIAL BAG', 'APHERESIS'];
    if (!empty($blood_bag_brand) && !in_array($blood_bag_brand, $allowedBrands, true)) {
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
            $phlebotomist = $full;
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
                        $phlebotomist = $full;
                    }
                }
            }
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

    // Build payload for Supabase blood_collection
    $nowIso = (new DateTime())->format('c');
    // Status is controlled by downstream review/eligibility; keep DB happy with 'pending'
    $status = 'pending';

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
        'needs_review'       => (bool)$needs_review,
        'phlebotomist'       => $phlebotomist,
        'updated_at'         => $nowIso
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

        // Note: Eligibility records are automatically created by database triggers

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

        // Note: Eligibility records are automatically created by database triggers

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
    // Log the error for debugging
    error_log("Blood Collection Admin - Exception: " . $e->getMessage());
    error_log("Blood Collection Admin - Stack trace: " . $e->getTraceAsString());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>