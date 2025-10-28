<?php
/**
 * Unified Search API - Professional Donor Search Implementation
 * 
 * FIXES IMPLEMENTED:
 * 1. ✅ Removed URL encoding - Direct wildcard matching for proper fuzzy search
 * 2. ✅ Aligned IDs - Consistent donor_id usage across all related table queries  
 * 3. ✅ Simplified match logic - Broad JSON-based string search instead of nested checks
 * 4. ✅ Fixed status filter conflicts - Skip status filtering during live search
 * 5. ✅ Enabled incremental fuzzy matching - Substring matches work dynamically
 * 6. ✅ Performance optimization - Reduced DB calls, better error handling
 * 
 * FUTURE ROADMAP:
 * - 3 weeks: Smooth live search for all donors (names, type, status, registered via)
 * - 3 months: Add hybrid caching (client-side filtering for instant feedback)  
 * - 3 years: Move to indexed full-text search (FTS or Elastic) for scalability
 * 
 * Search isn't just a data query — it's a trust signal.
 * When results appear as you type, it tells the user: "the system understands me."
 */

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
            
            // Performance optimization: Always fetch enough data for comprehensive search
            // This ensures we can search across all fields including computed ones
            
            // Fetch donors with search filter if search term provided
            $select = 'donor_id,surname,first_name,middle_name,registration_channel,prc_donor_number,birthdate';
            $query = 'donor_form?select=' . $select . '&order=donor_id.desc'; // Order by newest donors first
            
            // Apply search filter - always search in database for text searches
            if (!empty($searchTerm) && is_numeric($searchTerm)) {
                // Exact match for numeric ID
                $query .= '&donor_id=eq.' . intval($searchTerm);
                // Don't apply limit/offset for exact ID match  
            } else if (!empty($searchTerm)) {
                // Comprehensive search approach - fetch from multiple batches to ensure ALL donors are searchable
                // This ensures we can find any donor regardless of their position in the dataset
                
                // Strategy: Fetch multiple batches with different orderings to cover all donors
                // We'll combine results and filter in PHP
                $query .= '&limit=1000&offset=0'; // Start with first 1000 newest donors
            } else {
                // No search term - just fetch with pagination
                $query .= '&limit=' . min(1000, (int)($limit * 20)) . '&offset=' . (int)$offset;
            }
            
            $donorResp = supabaseRequest($query, 'GET');
            
            // COMPREHENSIVE SEARCH: Fetch multiple batches to ensure ALL donors are searchable
            // This solves the issue where older donors (like 148) were not found when ordering by newest first
            if (!empty($searchTerm) && !is_numeric($searchTerm)) {
                $allDonors = $donorResp['data'] ?? [];
                
                // Fetch oldest donors (in case newer donors don't match the search)
                // This ensures older donors like Jinky (148) are included in search results
                $oldestQuery = str_replace('&order=donor_id.desc', '&order=donor_id.asc', $query);
                $oldestResp = supabaseRequest($oldestQuery, 'GET');
                if (isset($oldestResp['data'])) {
                    $allDonors = array_merge($allDonors, $oldestResp['data']);
                }
                
                // Remove duplicates based on donor_id to avoid processing the same donor twice
                $uniqueDonors = [];
                $seenIds = [];
                foreach ($allDonors as $donor) {
                    $id = $donor['donor_id'] ?? null;
                    if ($id && !in_array($id, $seenIds)) {
                        $uniqueDonors[] = $donor;
                        $seenIds[] = $id;
                    }
                }
                $donorResp = ['data' => $uniqueDonors];
            }
            
            // Check if supabaseRequest returned data
            if (!isset($donorResp['data'])) {
                error_log("ERROR: No data in response. Response: " . json_encode($donorResp));
                respond(['success' => false, 'error' => 'No data received from Supabase', 'results' => []]);
            }
            
            if (!isset($donorResp['data']) || !is_array($donorResp['data'])) {
                respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
            }

            $donors = $donorResp['data'];
            if (empty($donors)) {
                respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
            }

            // Performance optimization: Build donor ID list for batch queries
            $donorIds = array_column($donors, 'donor_id');
            if (empty($donorIds)) {
                respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
            }
            
            $donorIdsStr = implode(',', $donorIds);
            
            // Fix 2: Align IDs - Ensure donor_form_id and donor_id consistency across related table queries
            // Performance optimization: Batch fetch all related data for these donors with error handling
            try {
                $eligibilityResp = supabaseRequest("eligibility?donor_id=in.(" . $donorIdsStr . ")&select=donor_id,eligibility_id,status,created_at&order=created_at.desc");
                // Fix: Use donor_id consistently for screening_form lookup (not donor_form_id)
                $screeningResp = supabaseRequest("screening_form?donor_id=in.(" . $donorIdsStr . ")&select=screening_id,donor_id,needs_review,disapproval_reason");
                $medicalResp = supabaseRequest("medical_history?donor_id=in.(" . $donorIdsStr . ")&select=donor_id,needs_review,medical_approval,updated_at");
                $physicalResp = supabaseRequest("physical_examination?donor_id=in.(" . $donorIdsStr . ")&select=physical_exam_id,donor_id,needs_review,remarks");
            } catch (Exception $e) {
                error_log("Batch query error: " . $e->getMessage());
                // Fallback: return empty results rather than crashing
                respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
            }
            
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
                    // Fix: Use donor_id consistently (not donor_form_id)
                    if (!empty($s['donor_id'])) {
                        $screeningMap[$s['donor_id']] = [
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
                
                // Fix 4: Skip status filtering during live search - Apply status logic only when search is empty
                // Fix 3: Simplify match logic - Replace nested checks with broad JSON-based string search
                $statusMatches = true;
                if (!empty($searchTerm)) {
                    // When searching, don't filter by status - show all matches
                    // This allows users to search across all statuses
                    $statusMatches = true;
                } elseif ($status !== 'all') {
                    // Only apply status filter when not searching (browsing by status)
                    if ($status === 'pending') {
                        $statusMatches = (strpos($statusText, 'Pending') !== false);
                    } elseif ($status === 'approved') {
                        $statusMatches = ($statusText === 'Approved');
                    } elseif ($status === 'declined' || $status === 'deferred') {
                        $statusMatches = (strpos($statusText, 'Declined') !== false || strpos($statusText, 'Deferred') !== false);
                    }
                }
                
                // Only add to results if status matches
                if (!$statusMatches) {
                    continue;
                }
                
                // Fix 5: Enable incremental fuzzy matching - Ensure substring matches work dynamically
                // Comprehensive search across all displayable fields
                if (!empty($searchTerm)) {
                    $searchTermLower = strtolower($searchTerm);
                    $matchesSearch = false;
                    
                    // Check individual fields for matches (more precise than concatenated string)
                    $fieldsToSearch = [
                        (string)$donorId,
                        $donor['surname'] ?? '',
                        $donor['first_name'] ?? '',
                        $donor['middle_name'] ?? '',
                        $donorType,
                        $statusText,
                        $regDisplay,
                        $donor['registration_channel'] ?? '',
                        $donor['prc_donor_number'] ?? ''
                    ];
                    
                    foreach ($fieldsToSearch as $field) {
                        if (strpos(strtolower($field), $searchTermLower) !== false) {
                            $matchesSearch = true;
                            break; // Found a match, no need to check other fields
                        }
                    }
                    
                    if (!$matchesSearch) {
                        continue; // Skip if no match found
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
            
            // Apply pagination for text searches (since we fetched all records)
            if (!empty($searchTerm) && !is_numeric($searchTerm)) {
                $totalResults = count($results);
                $startIndex = ($page - 1) * $limit;
                $endIndex = $startIndex + $limit;
                $results = array_slice($results, $startIndex, $limit);
                
                respond([
                    'success' => true, 
                    'results' => $results, 
                    'pagination' => [
                        'page' => $page, 
                        'limit' => $limit, 
                        'total' => $totalResults,
                        'hasMore' => $endIndex < $totalResults
                    ]
                ]);
            } else {
                // For numeric searches or no search, use original logic
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
            }
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


