<?php
header('Content-Type: application/json; charset=UTF-8');
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

require_once '../../assets/conn/db_conn.php';
@include_once __DIR__ . '/../Dashboards/module/optimized_functions.php';

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function get_param($key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

$action = get_param('action');
$q = get_param('q');
$category = get_param('category', 'all');
$status = get_param('status', 'all');  // Get status filter
$page = max(1, (int) get_param('page', '1'));
$limit = max(1, min(200, (int) get_param('limit', '50')));
$offset = ($page - 1) * $limit;

// NOTE: This is a scaffold. Replace with real dataset-specific queries.
// It returns empty results by default to avoid breaking pages.
switch ($action) {
    case 'donors':
        // Professional donor search that matches what's actually displayed in the table
        try {
            // Log the search request
            error_log("SEARCH API CALLED - action: $action, q: $q, category: $category, status: $status");
            
            if (!function_exists('supabaseRequest')) {
                error_log("ERROR: supabaseRequest function not found");
                respond(['success' => false, 'error' => 'supabaseRequest function not found', 'results' => []], 500);
            }

            $searchTerm = trim($q);
            error_log("Search term: '$searchTerm', status filter: '$status'");
            
            // For search on computed fields (donor_type, status), we need to fetch more data
            // and filter in PHP. For simple text searches, we can filter in the database.
            $fetchMoreForComputedFields = !empty($searchTerm) && (
                stripos($searchTerm, 'new') !== false ||
                stripos($searchTerm, 'returning') !== false ||
                stripos($searchTerm, 'pending') !== false ||
                stripos($searchTerm, 'approved') !== false ||
                stripos($searchTerm, 'declined') !== false ||
                stripos($searchTerm, 'deferred') !== false ||
                stripos($searchTerm, 'screening') !== false ||
                stripos($searchTerm, 'examination') !== false ||
                stripos($searchTerm, 'collection') !== false
            );
            
            // Fetch donors with search filter if search term provided
            $select = 'donor_id,surname,first_name,middle_name,registration_channel,prc_donor_number,birthdate';
            $query = 'donor_form?select=' . $select;
            
            // Apply search filter - always search in database for text searches
            if (!empty($searchTerm) && is_numeric($searchTerm)) {
                // Exact match for numeric ID
                $query .= '&donor_id=eq.' . intval($searchTerm);
                // Don't apply limit/offset for exact ID match
            } else if (!empty($searchTerm)) {
                // Text search - always use database ILIKE for efficiency
                $encoded = rawurlencode('%' . $searchTerm . '%');
                $query .= '&or=(donor_id.ilike.' . $encoded . ',surname.ilike.' . $encoded . ',first_name.ilike.' . $encoded . ',middle_name.ilike.' . $encoded . ',registration_channel.ilike.' . $encoded . ')';
                // Fetch more records to ensure we get enough results for status filtering after computing statuses
                $query .= '&limit=' . (int)($limit * 10) . '&offset=' . (int)$offset;
            } else {
                // No search term - just fetch with pagination
                $query .= '&limit=' . (int)($limit * 10) . '&offset=' . (int)$offset;
            }
            
            error_log("Search query: " . $query);
            $donorResp = supabaseRequest($query, 'GET');
            error_log("Search response: " . json_encode($donorResp));
            
            if (!isset($donorResp['data']) || !is_array($donorResp['data'])) {
                respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
            }

            $donors = $donorResp['data'];
            if (empty($donors)) {
                respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
            }

            // Build donor ID list for batch queries
            $donorIds = array_column($donors, 'donor_id');
            $donorIdsStr = implode(',', $donorIds);
            
            // OPTIMIZATION: Batch fetch all related data for these donors
            $eligibilityResp = supabaseRequest("eligibility?donor_id=in.(" . $donorIdsStr . ")&select=donor_id,eligibility_id,status,created_at&order=created_at.desc");
            $screeningResp = supabaseRequest("screening_form?donor_form_id=in.(" . $donorIdsStr . ")&select=screening_id,donor_form_id,needs_review,disapproval_reason");
            $medicalResp = supabaseRequest("medical_history?donor_id=in.(" . $donorIdsStr . ")&select=donor_id,needs_review,medical_approval,updated_at");
            $physicalResp = supabaseRequest("physical_examination?donor_id=in.(" . $donorIdsStr . ")&select=physical_exam_id,donor_id,needs_review,remarks");
            
            // Build lookup maps
            $eligibilityMap = [];
            $approvedDonorsMap = []; // Track approved donors separately
            if (isset($eligibilityResp['data']) && is_array($eligibilityResp['data'])) {
                foreach ($eligibilityResp['data'] as $e) {
                    if (!empty($e['donor_id'])) {
                        $eligibilityMap[$e['donor_id']] = true; // Track presence for "Returning" type
                        // Track approved donors separately
                        if (!empty($e['status']) && strtolower($e['status']) === 'approved') {
                            $approvedDonorsMap[$e['donor_id']] = [
                                'eligibility_id' => $e['eligibility_id'] ?? '',
                                'status' => $e['status']
                            ];
                        }
                    }
                }
            }
            
            $screeningMap = [];
            if (isset($screeningResp['data']) && is_array($screeningResp['data'])) {
                foreach ($screeningResp['data'] as $s) {
                    if (!empty($s['donor_form_id'])) {
                        $screeningMap[$s['donor_form_id']] = [
                            'needs_review' => $s['needs_review'] ?? false,
                            'disapproval_reason' => $s['disapproval_reason'] ?? null
                        ];
                    }
                }
            }
            
            $medicalMap = [];
            if (isset($medicalResp['data']) && is_array($medicalResp['data'])) {
                foreach ($medicalResp['data'] as $m) {
                    if (!empty($m['donor_id'])) {
                        $medicalMap[$m['donor_id']] = [
                            'needs_review' => $m['needs_review'] ?? false,
                            'medical_approval' => $m['medical_approval'] ?? null
                        ];
                    }
                }
            }
            
            $physicalMap = [];
            if (isset($physicalResp['data']) && is_array($physicalResp['data'])) {
                foreach ($physicalResp['data'] as $p) {
                    if (!empty($p['donor_id'])) {
                        $physicalMap[$p['donor_id']] = [
                            'needs_review' => $p['needs_review'] ?? false,
                            'remarks' => $p['remarks'] ?? null
                        ];
                    }
                }
            }
            
            // Process each donor to determine status (same logic as module)
            $results = [];
            foreach ($donors as $donor) {
                $donorId = $donor['donor_id'] ?? null;
                if (!$donorId) continue;
                
                // Determine donor type
                $donorType = isset($eligibilityMap[$donorId]) ? 'Returning' : 'New';
                
                // Check if this donor is approved
                $isApproved = isset($approvedDonorsMap[$donorId]);
                
                // Determine status
                if ($isApproved) {
                    // Override status for approved donors
                    $statusText = 'Approved';
                    $eligibilityId = $approvedDonorsMap[$donorId]['eligibility_id'] ?? '';
                } else {
                    // Determine pending status
                    $statusText = 'Pending (Screening)';
                    $screening = $screeningMap[$donorId] ?? null;
                    $medical = $medicalMap[$donorId] ?? null;
                    $physical = $physicalMap[$donorId] ?? null;
                    
                    $isMedicalHistoryCompleted = $medical !== null;
                    $isScreeningPassed = isset($screening) && empty($screening['disapproval_reason']);
                    $isPhysicalExamApproved = isset($physical) && !empty($physical['remarks']) && !in_array($physical['remarks'], ['Temporarily Deferred', 'Permanently Deferred', 'Declined', 'Refused']);
                    
                    if ($isMedicalHistoryCompleted && $isScreeningPassed && $isPhysicalExamApproved) {
                        $statusText = 'Pending (Collection)';
                    } else if ($isMedicalHistoryCompleted && $isScreeningPassed) {
                        $statusText = 'Pending (Examination)';
                    } else {
                        $statusText = 'Pending (Screening)';
                    }
                    
                    // Determine eligibility_id for pending donors
                    $eligibilityId = 'pending_' . $donorId;
                }
                
                // Handle registration channel display
                $regChannel = $donor['registration_channel'] ?? 'PRC Portal';
                $regDisplay = ($regChannel === 'PRC Portal') ? 'PRC System' : (($regChannel === 'Mobile') ? 'Mobile System' : $regChannel);
                
                // Apply status filter if specified AND no search term is provided
                // When searching, we want to show results from all statuses
                $statusMatches = true;
                if (!empty($searchTerm)) {
                    // When searching, don't filter by status - show all matches
                    $statusMatches = true;
                } elseif ($status !== 'all') {
                    // Only apply status filter when not searching (browsing by status)
                    if ($status === 'pending') {
                        // Pending statuses include: Pending (Screening), Pending (Examination), Pending (Collection)
                        $statusMatches = (strpos($statusText, 'Pending') !== false);
                    } elseif ($status === 'approved') {
                        // Approved status (exact match)
                        $statusMatches = ($statusText === 'Approved');
                    } elseif ($status === 'declined' || $status === 'deferred') {
                        // Declined/Deferred status
                        $statusMatches = (strpos($statusText, 'Declined') !== false || strpos($statusText, 'Deferred') !== false);
                    }
                }
                
                // Only add to results if status matches
                if (!$statusMatches) {
                    continue;
                }
                
                // Filter by search term if searching computed fields OR if we fetched many records
                // This ensures name searches work across all statuses
                if (!empty($searchTerm)) {
                    $matchesSearch = false;
                    
                    // Check if search term matches any of the displayable fields
                    if (stripos($donorType, $searchTerm) !== false ||
                        stripos($statusText, $searchTerm) !== false ||
                        stripos($regDisplay, $searchTerm) !== false ||
                        stripos($donor['surname'] ?? '', $searchTerm) !== false ||
                        stripos($donor['first_name'] ?? '', $searchTerm) !== false ||
                        stripos((string)$donorId, $searchTerm) !== false) {
                        $matchesSearch = true;
                    }
                    
                    if (!$matchesSearch) {
                        continue; // Skip this result if it doesn't match the search
                    }
                }
                
                // Map to array structure matching table columns
                $results[] = [
                    (string)$donorId,                              // Donor Number (0)
                    $donor['surname'] ?? '',                       // Surname (1)
                    $donor['first_name'] ?? '',                   // First Name (2)
                    $donorType,                                    // Donor Type (3)
                    $regDisplay,                                   // Registered Via (4)
                    $statusText,                                   // Status (5)
                    $eligibilityId                                 // Eligibility ID (6) - for data attributes
                ];
            }
            
            respond([
                'success' => true, 
                'results' => $results, 
                'pagination' => [
                    'page' => $page, 
                    'limit' => $limit, 
                    'total' => count($results),
                    'hasMore' => count($results) === $limit
                ]
            ]);
        } catch (Exception $e) {
            error_log("Search error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            respond([
                'success' => false, 
                'error' => 'Search failed: ' . $e->getMessage(), 
                'results' => [], 
                'pagination' => [
                    'page' => $page, 
                    'limit' => $limit, 
                    'total' => 0
                ]
            ], 500);
        } catch (Error $e) {
            error_log("Search fatal error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            respond([
                'success' => false, 
                'error' => 'Critical error in search', 
                'results' => [], 
                'pagination' => [
                    'page' => $page, 
                    'limit' => $limit, 
                    'total' => 0
                ]
            ], 500);
        }
        break;
    case 'blood_inventory':
        respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
        break;
    case 'hospital_requests':
        respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
        break;
    case 'users':
        respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
        break;
    default:
        respond(['success' => false, 'message' => 'Invalid action'], 400);
}
?>


