<?php
/**
 * Script to create missing blood_collection records for donors with completed physical_examination
 * 
 * This script finds donors who have completed physical_examination but don't have a blood_collection record,
 * and creates empty blood_collection records for them so they can move to "Pending (Collection)" status.
 */

// Start output buffering FIRST to catch any unexpected output
ob_start();

// Suppress error display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => $error['type']
        ], JSON_PRETTY_PRINT);
        ob_end_flush();
    }
});

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Clear any output that might have been generated before headers
ob_clean();

// Include database connection
$dbConnPath = __DIR__ . '/../../assets/conn/db_conn.php';
if (!file_exists($dbConnPath)) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database configuration file not found: ' . $dbConnPath
    ], JSON_PRETTY_PRINT);
    ob_end_flush();
    exit;
}

require_once $dbConnPath;

// Verify constants are defined
if (!defined('SUPABASE_URL') || !defined('SUPABASE_API_KEY')) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database configuration not found. SUPABASE_URL or SUPABASE_API_KEY not defined.'
    ], JSON_PRETTY_PRINT);
    ob_end_flush();
    exit;
}

try {
    $action = $_GET['action'] ?? 'find';
    $dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === 'true';
    $test = isset($_GET['test']) && $_GET['test'] === 'true';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000; // Default limit to prevent timeout
    
    // Test mode - just return basic info
    if ($test) {
        echo json_encode([
            'success' => true,
            'message' => 'Script is working',
            'constants_defined' => [
                'SUPABASE_URL' => defined('SUPABASE_URL'),
                'SUPABASE_API_KEY' => defined('SUPABASE_API_KEY')
            ],
            'action' => $action,
            'dry_run' => $dryRun
        ], JSON_PRETTY_PRINT);
        ob_end_flush();
        exit;
    }
    
    // OPTIMIZATION: Fetch all blood_collection physical_exam_ids in one query first
    // This way we can build a map and check against it instead of making individual queries
    $collectionCurl = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=physical_exam_id&physical_exam_id=not.is.null');
    curl_setopt($collectionCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($collectionCurl, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    $collectionResponse = curl_exec($collectionCurl);
    $collectionHttpCode = curl_getinfo($collectionCurl, CURLINFO_HTTP_CODE);
    curl_close($collectionCurl);
    
    // Build a map of physical_exam_ids that have blood_collection records
    $physicalExamIdsWithCollection = [];
    if ($collectionHttpCode === 200) {
        $collectionData = json_decode($collectionResponse, true) ?: [];
        foreach ($collectionData as $collection) {
            if (!empty($collection['physical_exam_id'])) {
                $physicalExamIdsWithCollection[$collection['physical_exam_id']] = true;
            }
        }
    }
    
    // Step 1: Find physical_examination records that have data (completed exams)
    // Use a filter to only get records where at least one field is not null/empty
    // Note: Supabase doesn't support complex OR filters easily, so we'll fetch and filter
    // Limit to prevent timeout - can be increased via ?limit parameter
    $physicalExamCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,donor_id,blood_pressure,pulse_rate,body_temp,gen_appearance,skin,heent,heart_and_lungs,remarks,screening_id&blood_pressure=not.is.null&order=created_at.desc&limit=' . $limit);
    curl_setopt($physicalExamCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($physicalExamCurl, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    $physicalExamResponse = curl_exec($physicalExamCurl);
    $physicalExamHttpCode = curl_getinfo($physicalExamCurl, CURLINFO_HTTP_CODE);
    curl_close($physicalExamCurl);
    
    if ($physicalExamHttpCode !== 200) {
        throw new Exception('Failed to fetch physical_examination records: HTTP ' . $physicalExamHttpCode . ' - ' . $physicalExamResponse);
    }
    
    $physicalExamData = json_decode($physicalExamResponse, true) ?: [];
    
    // Step 2: Filter to find completed physical examinations (have data, not just ID)
    $completedPhysicalExams = [];
    $checkFields = ['blood_pressure', 'pulse_rate', 'body_temp', 'gen_appearance', 'skin', 'heent', 'heart_and_lungs', 'remarks'];
    
    foreach ($physicalExamData as $exam) {
        $hasData = false;
        foreach ($checkFields as $field) {
            $value = $exam[$field] ?? null;
            if ($value !== null && $value !== '' && trim($value) !== '') {
                $hasData = true;
                break;
            }
        }
        
        if ($hasData) {
            $completedPhysicalExams[] = $exam;
        }
    }
    
    // Step 3: Check which ones don't have blood_collection records (using the map we built)
    $missingBloodCollection = [];
    
    foreach ($completedPhysicalExams as $exam) {
        $physicalExamId = $exam['physical_exam_id'] ?? null;
        if (!$physicalExamId) {
            continue;
        }
        
        // Check against our map instead of making individual API calls
        if (!isset($physicalExamIdsWithCollection[$physicalExamId])) {
            // No blood_collection record found - add to list
            $missingBloodCollection[] = [
                'physical_exam_id' => $physicalExamId,
                'donor_id' => $exam['donor_id'] ?? null,
                'screening_id' => $exam['screening_id'] ?? null
            ];
        }
    }
    
    $results = [
        'total_physical_exams_fetched' => count($physicalExamData),
        'total_completed_physical_exams' => count($completedPhysicalExams),
        'missing_blood_collection_count' => count($missingBloodCollection),
        'missing_blood_collection' => $missingBloodCollection,
        'limit_applied' => $limit,
        'note' => count($physicalExamData) >= $limit ? 'Results may be limited. Increase ?limit parameter to see more.' : null
    ];
    
    // Step 4: Create blood_collection records if not in dry run mode
    $created = [];
    $errors = [];
    
    if ($action === 'create' && !$dryRun) {
        foreach ($missingBloodCollection as $item) {
            $physicalExamId = $item['physical_exam_id'];
            $screeningId = $item['screening_id'];
            
            // Create empty blood_collection record
            $now = gmdate('c'); // ISO 8601 format
            $collectionData = [
                'physical_exam_id' => $physicalExamId,
                'status' => 'pending',
                'access' => '0',
                'needs_review' => false,
                'created_at' => $now,
                'updated_at' => $now
                // All other fields will be null/empty
            ];
            
            // Add screening_id if available
            if (!empty($screeningId)) {
                $collectionData['screening_id'] = $screeningId;
            }
            
            // Create the record
            $createCurl = curl_init(SUPABASE_URL . '/rest/v1/blood_collection');
            curl_setopt($createCurl, CURLOPT_POST, true);
            curl_setopt($createCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($createCurl, CURLOPT_POSTFIELDS, json_encode($collectionData));
            curl_setopt($createCurl, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]);
            
            $createResponse = curl_exec($createCurl);
            $createHttpCode = curl_getinfo($createCurl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($createCurl);
            curl_close($createCurl);
            
            if ($createHttpCode === 201 || $createHttpCode === 200) {
                $created[] = [
                    'physical_exam_id' => $physicalExamId,
                    'donor_id' => $item['donor_id'],
                    'response' => json_decode($createResponse, true)
                ];
            } else {
                // Check if record was actually created despite the error (might be a trigger issue)
                $verifyCurl = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?physical_exam_id=eq.' . $physicalExamId . '&select=blood_collection_id&limit=1');
                curl_setopt($verifyCurl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($verifyCurl, CURLOPT_HTTPHEADER, [
                    'apikey: ' . SUPABASE_API_KEY,
                    'Authorization: Bearer ' . SUPABASE_API_KEY,
                    'Content-Type: application/json'
                ]);
                $verifyResponse = curl_exec($verifyCurl);
                $verifyHttpCode = curl_getinfo($verifyCurl, CURLINFO_HTTP_CODE);
                curl_close($verifyCurl);
                
                if ($verifyHttpCode === 200) {
                    $verifyData = json_decode($verifyResponse, true) ?: [];
                    if (!empty($verifyData)) {
                        // Record was actually created despite the error - likely a trigger/other system issue
                        $created[] = [
                            'physical_exam_id' => $physicalExamId,
                            'donor_id' => $item['donor_id'],
                            'note' => 'Created successfully (despite initial error - may be from trigger/other system)',
                            'initial_error_code' => $createHttpCode,
                            'initial_error' => substr($createResponse ?: $curlError ?: 'Unknown error', 0, 200)
                        ];
                    } else {
                        // Record was not created - actual error
                        $errors[] = [
                            'physical_exam_id' => $physicalExamId,
                            'donor_id' => $item['donor_id'],
                            'error' => $createResponse ?: $curlError ?: 'Unknown error',
                            'http_code' => $createHttpCode
                        ];
                    }
                } else {
                    // Verification failed - treat as error
                    $errors[] = [
                        'physical_exam_id' => $physicalExamId,
                        'donor_id' => $item['donor_id'],
                        'error' => $createResponse ?: $curlError ?: 'Unknown error',
                        'http_code' => $createHttpCode
                    ];
                }
            }
        }
        
        $results['created_count'] = count($created);
        $results['created'] = $created;
        $results['errors_count'] = count($errors);
        $results['errors'] = $errors;
    } else if ($dryRun) {
        $results['message'] = 'DRY RUN MODE - No records were created';
    }
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'dry_run' => $dryRun,
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Clear any output before sending error
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
} catch (Error $e) {
    // Clear any output before sending error
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}

// End output buffering
ob_end_flush();
?>

