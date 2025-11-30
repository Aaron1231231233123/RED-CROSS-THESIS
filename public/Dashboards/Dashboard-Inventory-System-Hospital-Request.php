<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once 'module/optimized_functions.php';
require_once '../../assets/php_func/admin_hospital_request_priority_handler.php';
require_once '../../assets/php_func/buffer_blood_manager.php';
$all_blood_requests_cache = [];
// Send short-term caching headers for better performance on slow networks
header('Cache-Control: public, max-age=180, stale-while-revalidate=60');
header('Vary: Accept-Encoding');

// Check if the user is logged in and has admin role (role_id = 1)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    // Redirect to login page or show error
    header("Location: ../../public/login.php");
    exit();
}

// Handle POST requests for request processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept_request']) && isset($_POST['request_id'])) {
        // Handle approve request
        $request_id = intval($_POST['request_id']);
        
        try {
            // Update request status to Approved
            $approvedBy = 'Admin';
            if (!empty($_SESSION['user_id'])) {
                $approvedByName = getAdminName($_SESSION['user_id'], false);
                if (!empty($approvedByName)) {
                    $approvedBy = $approvedByName;
                }
            }

            $update_data = [
                'status' => 'Approved',
                'last_updated' => date('Y-m-d H:i:s'),
                'approved_by' => $approvedBy,
                'approved_date' => date('Y-m-d H:i:s')
            ];
            
            $response = supabaseRequest(
                "blood_requests?request_id=eq." . $request_id,
                'PATCH',
                $update_data
            );
            
            if ($response['code'] >= 200 && $response['code'] < 300) {
                // Redirect with success message
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=accepted&success=1&message=" . urlencode("Request #$request_id has been approved successfully."));
                exit();
            } else {
                throw new Exception("Failed to update request status. HTTP Code: " . $response['code']);
            }
        } catch (Exception $e) {
            error_log("Error approving request: " . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode("Failed to approve request: " . $e->getMessage()));
            exit();
        }
    }
    
    if (isset($_POST['decline_request']) && isset($_POST['request_id'])) {
        // Handle decline request
        $request_id = intval($_POST['request_id']);
        $decline_reason = isset($_POST['decline_reason']) ? trim($_POST['decline_reason']) : 'No reason provided';
        
        try {
            // Update request status to Declined
            $update_data = [
                'status' => 'Declined',
                'last_updated' => date('Y-m-d H:i:s'),
                'decline_reason' => $decline_reason
            ];
            
            $response = supabaseRequest(
                "blood_requests?request_id=eq." . $request_id,
                'PATCH',
                $update_data
            );
            
            if ($response['code'] >= 200 && $response['code'] < 300) {
                // Redirect with success message
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=declined&success=1&message=" . urlencode("Request #$request_id has been declined."));
                exit();
            } else {
                throw new Exception("Failed to update request status. HTTP Code: " . $response['code']);
            }
        } catch (Exception $e) {
            error_log("Error declining request: " . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode("Failed to decline request: " . $e->getMessage()));
            exit();
        }
    }
    
    if (isset($_POST['handover_request']) && isset($_POST['request_id'])) {
        // Handle handover request
        $request_id = intval($_POST['request_id']);
        
        try {
            // Update request status to Completed (Handed Over)
            $update_data = [
                'status' => 'Completed',
                'last_updated' => date('Y-m-d H:i:s'),
                'handed_over_by' => (!empty($_SESSION['user_id']) ? getAdminName($_SESSION['user_id'], false) : 'Admin'),
                'handed_over_date' => date('Y-m-d H:i:s')
            ];
            
            $response = supabaseRequest(
                "blood_requests?request_id=eq." . $request_id,
                'PATCH',
                $update_data
            );
            
            if ($response['code'] >= 200 && $response['code'] < 300) {
                // Redirect with success message
                header("Location: " . $_SERVER['PHP_SELF'] . "?status=handedover&success=1&message=" . urlencode("Request #$request_id has been marked as handed over."));
                exit();
            } else {
                throw new Exception("Failed to update request status. HTTP Code: " . $response['code']);
            }
        } catch (Exception $e) {
            error_log("Error processing handover: " . $e->getMessage());
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode("Failed to process handover: " . $e->getMessage()));
            exit();
        }
    }
}

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/module/optimized_functions.php';

// Function to get admin name from user_id
function getAdminName($user_id, $includePrefix = true) {
    try {
        $response = supabaseRequest("users?select=first_name,surname&user_id=eq." . $user_id);
        if ($response['code'] === 200 && !empty($response['data'])) {
            $user = $response['data'][0];
            $first_name = trim($user['first_name'] ?? '');
            $surname = trim($user['surname'] ?? '');
            $fullName = trim($first_name . ' ' . $surname);
            
            if (!empty($fullName)) {
                if ($includePrefix) {
                    return "Dr. $fullName";
                }
                return $fullName;
            }

            if (!empty($first_name)) {
                return $includePrefix ? "Dr. $first_name" : $first_name;
            }

            if (!empty($surname)) {
                return $includePrefix ? "Dr. $surname" : $surname;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting admin name: " . $e->getMessage());
    }
    return $includePrefix ? 'Dr. Admin' : 'Admin';
}

// Get current admin name (cache in session to avoid extra API calls each page load)
if (!empty($_SESSION['user_id'])) {
    if (empty($_SESSION['admin_full_name'])) {
        $_SESSION['admin_full_name'] = getAdminName($_SESSION['user_id']);
    }
    $admin_name = $_SESSION['admin_full_name'];
} else {
    $admin_name = 'Dr. Admin';
}

// Function to get admin name from handed_over_by user_id
function getHandedOverByAdminName($handed_over_by) {
    if (empty($handed_over_by)) {
        return 'Not handed over yet';
    }
    return getAdminName($handed_over_by);
}

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// Fetch blood requests based on status from GET parameter
// Unified view: ignore status filters for this page and show all requests
$status = 'all';

function fetchAllBloodRequests($limit = 50, $offset = 0, &$totalCount = null) {
    global $all_blood_requests_cache;
    // First, get total count for pagination
    $countResponse = supabaseRequest("blood_requests?select=request_id&limit=1");
    // Get total count by fetching all (with reasonable limit)
    $countEndpoint = "blood_requests?select=request_id";
    $countResponse = supabaseRequest($countEndpoint);
    $totalCount = isset($countResponse['data']) ? count($countResponse['data']) : 0;
    
    // If we need more accurate count, fetch in batches
    if ($totalCount >= 1000) {
        // Supabase has 1000 record limit, so we need to count differently
        // For now, fetch all we can and estimate
        $fetchLimit = 1000;
    } else {
        $fetchLimit = $totalCount;
    }
    
    // Narrow columns - fetch all records for proper sorting
    $select = "request_id,request_reference,hospital_admitted,patient_blood_type,rh_factor,units_requested,is_asap,requested_on,status,patient_name,patient_age,patient_gender,patient_diagnosis,physician_name,when_needed,handed_over_by,handed_over_date";
    $endpoint = "blood_requests?select=" . urlencode($select) . "&order=requested_on.desc&limit={$fetchLimit}&offset=0";
    $response = supabaseRequest($endpoint);
    if (!isset($response['data']) || empty($response['data'])) {
        error_log("Error fetching all blood requests: " . ($response['error'] ?? 'Unknown error'));
        $totalCount = 0;
        return [];
    }
    
    $allRequests = $response['data'];
    $all_blood_requests_cache = $allRequests;
    $totalCount = count($allRequests); // Update with actual fetched count
    
    // Sort requests: Pending first (by when_needed deadline), then others
    usort($allRequests, function($a, $b) {
        $statusA = strtolower($a['status'] ?? '');
        $statusB = strtolower($b['status'] ?? '');
        $isPendingA = ($statusA === 'pending');
        $isPendingB = ($statusB === 'pending');
        
        // Pending requests come first
        if ($isPendingA && !$isPendingB) {
            return -1;
        }
        if (!$isPendingA && $isPendingB) {
            return 1;
        }
        
        // If both are pending, sort by when_needed deadline (earliest first)
        if ($isPendingA && $isPendingB) {
            $whenNeededA = $a['when_needed'] ?? null;
            $whenNeededB = $b['when_needed'] ?? null;
            
            // Handle null values - put nulls at the end
            if ($whenNeededA === null && $whenNeededB === null) {
                // If both null, sort by is_asap (ASAP first), then by requested_on
                $asapA = !empty($a['is_asap']);
                $asapB = !empty($b['is_asap']);
                if ($asapA && !$asapB) return -1;
                if (!$asapA && $asapB) return 1;
                // Both same ASAP status, sort by requested_on (newest first)
                $reqA = strtotime($a['requested_on'] ?? '1970-01-01');
                $reqB = strtotime($b['requested_on'] ?? '1970-01-01');
                return $reqB - $reqA;
            }
            if ($whenNeededA === null) return 1;
            if ($whenNeededB === null) return -1;
            
            // Parse timestamps
            $timeA = strtotime($whenNeededA);
            $timeB = strtotime($whenNeededB);
            
            if ($timeA === false) return 1;
            if ($timeB === false) return -1;
            
            // Primary sort: by when_needed (earliest deadline first)
            if ($timeA !== $timeB) {
                return $timeA - $timeB;
            }
            
            // If same deadline, prioritize ASAP requests
            $asapA = !empty($a['is_asap']);
            $asapB = !empty($b['is_asap']);
            if ($asapA && !$asapB) return -1;
            if (!$asapA && $asapB) return 1;
            
            // If same deadline and same ASAP status, sort by requested_on (newest first)
            $reqA = strtotime($a['requested_on'] ?? '1970-01-01');
            $reqB = strtotime($b['requested_on'] ?? '1970-01-01');
            return $reqB - $reqA;
        }
        
        // For non-pending requests, sort by requested_on (newest first)
        $reqA = strtotime($a['requested_on'] ?? '1970-01-01');
        $reqB = strtotime($b['requested_on'] ?? '1970-01-01');
        return $reqB - $reqA;
    });
    
    // Apply pagination after sorting
    return array_slice($allRequests, $offset, $limit);
}

function buildHospitalRequestCardMetrics($requests) {
    $counts = [];
    $pending = 0;
    $approved = 0;
    $completed = 0;
    $critical = 0;
    $today = 0;
    $now = time();
    $todayStr = gmdate('Y-m-d');

    foreach ($requests as $req) {
        $bloodType = trim($req['patient_blood_type'] ?? '');
        $rhFactor = trim($req['rh_factor'] ?? '');
        if ($bloodType !== '' && $rhFactor !== '') {
            $key = $bloodType . '|' . $rhFactor;
            if (!isset($counts[$key])) {
                $counts[$key] = 0;
            }
            $counts[$key]++;
        }

        $status = strtolower($req['status'] ?? '');
        if (in_array($status, ['pending', 'rescheduled'])) {
            $pending++;
        } elseif (in_array($status, ['approved', 'printed'])) {
            $approved++;
        } elseif ($status === 'completed') {
            $completed++;
        }

        $requestedOn = isset($req['requested_on']) ? strtotime($req['requested_on']) : false;
        if ($requestedOn && gmdate('Y-m-d', $requestedOn) === $todayStr) {
            $today++;
        }

        $whenNeededTs = isset($req['when_needed']) ? strtotime($req['when_needed']) : null;
        $isAsap = !empty($req['is_asap']);
        $withinOneDay = $whenNeededTs ? ($whenNeededTs - $now <= 86400 && $whenNeededTs >= $now) : false;
        if ($isAsap || $withinOneDay) {
            if (in_array($status, ['pending', 'rescheduled', 'approved', 'printed'])) {
                $critical++;
            }
        }
    }

    arsort($counts);
    $topType = reset($counts);
    $topKey = key($counts);
    $topDisplay = 'No data';
    $topSubtitle = 'No request history yet';
    if ($topType && $topKey !== null) {
        list($bt, $rh) = explode('|', $topKey);
        $symbol = $rh === 'Negative' ? '-' : '+';
        $topDisplay = $bt . $symbol;
        $topSubtitle = $topType . ' request' . ($topType > 1 ? 's' : '');
    }

    return [
        'most_requested' => [
            'label' => 'Most Requested Blood Type',
            'value' => $topDisplay,
            'subtitle' => $topSubtitle
        ],
        'pending' => [
            'label' => 'Active Hospital Requests',
            'value' => $pending,
            'subtitle' => ($pending + $approved + $completed) > 0
                ? round(($pending / max(1, $pending + $approved + $completed)) * 100) . '% of total'
                : 'Awaiting new requests'
        ],
        'critical' => [
            'label' => 'Critical / ASAP',
            'value' => $critical,
            'subtitle' => $critical > 0 ? 'Needs action in <24h' : 'All urgent cases addressed'
        ],
        'today' => [
            'label' => 'New Requests Today',
            'value' => $today,
            'subtitle' => $today > 0 ? 'Fresh cases logged' : 'No new cases yet'
        ]
    ];
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$page_size = 12;
$offset = ($page - 1) * $page_size;
$total_count = 0;
$blood_requests = fetchAllBloodRequests($page_size, $offset, $total_count);
$total_pages = $total_count > 0 ? ceil($total_count / $page_size) : 1;

$bufferContext = getBufferBloodContext();
$bufferReserveCount = $bufferContext['count'];
$dashboardCardMetrics = buildHospitalRequestCardMetrics($all_blood_requests_cache ?? []);

// Batch fetch handed-over user names to avoid N+1 queries
$handed_over_name_map = [];
if (!empty($blood_requests)) {
    $ids = [];
    foreach ($blood_requests as $req) {
        if (!empty($req['handed_over_by'])) {
            $ids[] = $req['handed_over_by'];
        }
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if (!empty($ids)) {
        // Build an "in" filter: in.(id1,id2,...) - ensure values are URL safe
        $inList = '(' . implode(',', array_map('intval', $ids)) . ')';
        try {
            $resp = supabaseRequest("users?select=user_id,first_name,surname&user_id=in.$inList");
            if (isset($resp['code']) && $resp['code'] === 200 && !empty($resp['data'])) {
                foreach ($resp['data'] as $u) {
                    $fname = trim($u['first_name'] ?? '');
                    $sname = trim($u['surname'] ?? '');
                    $full = trim($fname . ' ' . $sname);
                    if (!empty($u['user_id']) && !empty($full)) {
                        $handed_over_name_map[$u['user_id']] = $full;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Batch fetch user names failed: " . $e->getMessage());
        }
    }
}

// Handle success/error messages
$success_message = '';
$error_message = '';
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['message'])) {
    $success_message = urldecode($_GET['message']);
}
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

// Function to fetch blood units for a specific request
function fetchBloodUnitsForRequest($request_id) {
    global $bufferContext;

    try {
        // First, get the request details to know what blood type is needed
        $requestResponse = supabaseRequest("blood_requests?request_id=eq." . $request_id);
        if (!isset($requestResponse['data']) || empty($requestResponse['data'])) {
            return ['units' => [], 'buffer_usage' => ['used' => false, 'message' => '', 'units' => []]];
        }
        
        $request = $requestResponse['data'][0];
        $needed_blood_type = $request['patient_blood_type'];
        $needed_rh_factor = $request['rh_factor'];
        $needed_units = intval($request['units_requested']);
        
        // Get compatible blood types
        $compatible_types = [];
        $is_positive = $needed_rh_factor === 'Positive';
        
        // O+ can receive O+ and O-
        // O- can only receive O-
        // A+ can receive A+, A-, O+, O-
        // A- can receive A-, O-
        // B+ can receive B+, B-, O+, O-
        // B- can receive B-, O-
        // AB+ can receive all types
        // AB- can receive AB-, A-, B-, O-
        
        switch ($needed_blood_type) {
            case 'O':
                if ($is_positive) {
                    $compatible_types = ['O+', 'O-'];
                } else {
                    $compatible_types = ['O-'];
                }
                break;
            case 'A':
                if ($is_positive) {
                    $compatible_types = ['A+', 'A-', 'O+', 'O-'];
                } else {
                    $compatible_types = ['A-', 'O-'];
                }
                break;
            case 'B':
                if ($is_positive) {
                    $compatible_types = ['B+', 'B-', 'O+', 'O-'];
                } else {
                    $compatible_types = ['B-', 'O-'];
                }
                break;
            case 'AB':
                if ($is_positive) {
                    $compatible_types = ['AB+', 'AB-', 'A+', 'A-', 'B+', 'B-', 'O+', 'O-'];
                } else {
                    $compatible_types = ['AB-', 'A-', 'B-', 'O-'];
                }
                break;
        }
        
        // Convert to database format (with + and - instead of Positive/Negative)
        $db_compatible_types = [];
        foreach ($compatible_types as $type) {
            $blood_type = substr($type, 0, -1);
            $rh_factor = substr($type, -1) === '+' ? 'Positive' : 'Negative';
            $db_compatible_types[] = $blood_type . '|' . $rh_factor;
        }
        
        // Build the query to get available blood units
        $blood_type_conditions = [];
        foreach ($db_compatible_types as $type) {
            list($bt, $rh) = explode('|', $type);
            $blood_type_conditions[] = "(blood_type.eq.{$bt}&rh_factor.eq.{$rh})";
        }
        
        $blood_type_filter = implode(',', $blood_type_conditions);
        
        // Only include units that are NOT expired (expiration date >= today)
        $today = gmdate('Y-m-d\TH:i:s\Z');
        
        // Fetch more rows than needed so we can deprioritize buffer units where possible.
        $fetchLimit = max($needed_units * 4, 40);
        
        $endpoint = "blood_bank_units"
            . "?select=unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,hospital_request_id,is_check"
            . "&or=({$blood_type_filter})"
            . "&status=in.(Valid,valid,Buffer,buffer)&hospital_request_id=is.null"
            . "&expires_at=gte." . rawurlencode($today)
            . "&order=expires_at.asc&limit=" . $fetchLimit;
        
        $response = supabaseRequest($endpoint);
        if (!isset($response['data']) || empty($response['data'])) {
            return ['units' => [], 'buffer_usage' => ['used' => false, 'message' => '', 'units' => []]];
        }
        
        if (empty($bufferContext)) {
            $bufferContext = getBufferBloodContext();
        }
        $bufferLookup = $bufferContext['buffer_lookup'] ?? ['by_id' => [], 'by_serial' => []];
        
        $nonBufferQueue = [];
        $bufferQueue = [];
        foreach ($response['data'] as $unit) {
            if (!empty($unit['is_check'])) {
                continue;
            }
            if (isBufferUnitFromLookup($unit, $bufferLookup)) {
                $bufferQueue[] = $unit;
            } else {
                $nonBufferQueue[] = $unit;
            }
        }
        
        $orderedUnits = array_merge($nonBufferQueue, $bufferQueue);
        $selectedUnits = array_slice($orderedUnits, 0, $needed_units);
        
        $bufferUnitsUsed = array_values(array_filter($selectedUnits, function ($unit) use ($bufferLookup) {
            return isBufferUnitFromLookup($unit, $bufferLookup);
        }));
        
        $message = '';
        if (!empty($bufferUnitsUsed)) {
            $serials = array_column($bufferUnitsUsed, 'unit_serial_number');
            $message = sprintf(
                'Emergency buffer in use: %d unit%s (%s) will be allocated because regular stock was insufficient.',
                count($bufferUnitsUsed),
                count($bufferUnitsUsed) > 1 ? 's' : '',
                implode(', ', $serials)
            );
        }
        
        return [
            'units' => $selectedUnits,
            'buffer_usage' => [
                'used' => !empty($bufferUnitsUsed),
                'units' => $bufferUnitsUsed,
                'message' => $message
            ]
        ];
    } catch (Exception $e) {
        error_log("Error fetching blood units for request: " . $e->getMessage());
        return ['units' => [], 'buffer_usage' => ['used' => false, 'message' => '', 'units' => []]];
    }
}

// Helper function to get compatible blood types based on recipient's blood type
function getCompatibleBloodTypes($blood_type, $rh_factor) {
    $is_positive = $rh_factor === 'Positive';
    $compatible_types = [];
    switch ($blood_type) {
        case 'O':
            if ($is_positive) {
                $compatible_types = [
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            } else {
                $compatible_types = [
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
        case 'A':
            if ($is_positive) {
                $compatible_types = [
                    ['type' => 'A', 'rh' => 'Positive', 'priority' => 4],
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            } else {
                $compatible_types = [
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
        case 'B':
            if ($is_positive) {
                $compatible_types = [
                    ['type' => 'B', 'rh' => 'Positive', 'priority' => 4],
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            } else {
                $compatible_types = [
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
        case 'AB':
            if ($is_positive) {
                $compatible_types = [
                    ['type' => 'AB', 'rh' => 'Positive', 'priority' => 8],
                    ['type' => 'AB', 'rh' => 'Negative', 'priority' => 7],
                    ['type' => 'A', 'rh' => 'Positive', 'priority' => 6],
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 5],
                    ['type' => 'B', 'rh' => 'Positive', 'priority' => 4],
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            } else {
                $compatible_types = [
                    ['type' => 'AB', 'rh' => 'Negative', 'priority' => 4],
                    ['type' => 'A', 'rh' => 'Negative', 'priority' => 3],
                    ['type' => 'B', 'rh' => 'Negative', 'priority' => 2],
                    ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                ];
            }
            break;
    }
    usort($compatible_types, function($a, $b) {
        return $a['priority'] - $b['priority'];
    });
    return $compatible_types;
}

// OPTIMIZATION: Enhanced function to check if a blood request can be fulfilled
function canFulfillBloodRequest($request_id) {
    // Fetch request basics first
    $requestResponse = supabaseRequest("blood_requests?select=request_id,patient_blood_type,rh_factor,units_requested&request_id=eq." . $request_id);
    if (!isset($requestResponse['data']) || empty($requestResponse['data'])) {
        return [false, 'Request not found.'];
    }

    $request_data = $requestResponse['data'][0];
    $requested_blood_type = $request_data['patient_blood_type'];
    $requested_rh_factor = $request_data['rh_factor'];
    $units_requested = max(0, intval($request_data['units_requested']));

    // Reuse the already filtered Supabase query builder
    $result = fetchBloodUnitsForRequest($request_id);
    $units = $result['units'] ?? [];
    $bufferUsage = $result['buffer_usage'] ?? ['used' => false, 'message' => ''];

    // Count available by type and total
    $available_units = 0;
    $available_by_type = [];
    foreach ($units as $u) {
        $bt = $u['blood_type'] ?? '';
        if (!empty($bt)) {
            if (!isset($available_by_type[$bt])) {
                $available_by_type[$bt] = 0;
            }
            $available_by_type[$bt]++;
        }
        $available_units++;
        if ($available_units >= $units_requested) {
            // Short-circuit once we have enough
            break;
        }
    }

    $can_fulfill = $available_units >= $units_requested;
    $message = $can_fulfill
        ? "Available: $available_units units (Requested: $units_requested)"
        : "Insufficient: $available_units available, $units_requested requested";

    if ($bufferUsage['used'] ?? false) {
        $message .= ' Buffer reserve will be tapped if you proceed.';
    }

    return [$can_fulfill, $message, $available_by_type, $bufferUsage];
}

// OPTIMIZATION: Performance logging and caching
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
addPerformanceHeaders($executionTime, count($blood_requests), "Hospital Requests Module - Status: {$status}");

// Get default sorting
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <?php
        // Preconnect to Supabase host to speed up API calls
        if (defined('SUPABASE_URL')) {
            $___scheme = parse_url(SUPABASE_URL, PHP_URL_SCHEME);
            $___host = parse_url(SUPABASE_URL, PHP_URL_HOST);
            if ($___scheme && $___host) {
                $___sup_preconnect = htmlspecialchars($___scheme . '://' . $___host);
                echo '<link rel="preconnect" href="' . $___sup_preconnect . '" crossorigin>' . "\n";
            }
        }
    ?>
    <!-- Preload small header logo to stabilize header and improve LCP -->
    <link rel="preload" as="image" href="../../assets/image/PRC_Logo.png" imagesrcset="../../assets/image/PRC_Logo.png 1x" imagesizes="65px">
    <!-- Bootstrap 5.3 CSS (non-blocking preload pattern) -->
    <link id="bootstrap-css" rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet';this.dataset.loaded='1'">
    <noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></noscript>
    <!-- FontAwesome for Icons (non-blocking preload pattern) -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <style>
/* General Body Styling */
body {
    background-color: #f8f9fa;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}
.buffer-row {
    background-color: #fffdf0 !important;
}
.buffer-row td {
    border-top: 1px solid #fde9a5 !important;
}
.buffer-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 6px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
    background: #f7d774;
    color: #7a4a00;
}
/* Reduce Left Margin for Main Content */
main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
    margin-left: 280px !important; 
}
/* Header */
.dashboard-home-header {
    position: fixed;
    top: 0;
    left: 240px; /* Adjusted sidebar width */
    width: calc(100% - 240px);
    background-color: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    z-index: 1000;
    transition: left 0.3s ease, width 0.3s ease;
}

/* Sidebar Styling */
.inventory-sidebar {
    height: 100vh;
    overflow-y: auto;
    position: fixed;
    width: 240px;
    background-color: #ffffff;
    border-right: 1px solid #ddd;
    padding: 15px;
    transition: width 0.3s ease;
    display: flex;
    flex-direction: column;
}

.sidebar-main-content {
    flex-grow: 1;
    padding-bottom: 80px; /* Space for logout button */
}

.logout-container {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px 15px;
    border-top: 1px solid #ddd;
    background-color: #ffffff;
}

.logout-link {
    color: #dc3545 !important;
}

.logout-link:hover {
    background-color: #dc3545 !important;
    color: white !important;
}

.inventory-sidebar .nav-link {
    color: #333;
    padding: 10px 15px;
    margin: 2px 0;
    border-radius: 4px;
    transition: background-color 0.2s ease, color 0.2s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
}

.inventory-sidebar .nav-link i {
    margin-right: 10px;
    font-size: 0.9rem;
    width: 16px;
    text-align: center;
}

.inventory-sidebar .nav-link:hover {
    background-color: #f8f9fa;
    color: #dc3545;
}

.inventory-sidebar .nav-link.active {
    background-color: #dc3545;
    color: white;
}

.inventory-sidebar .collapse-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    background-color: transparent;
    border-radius: 4px;
}

.inventory-sidebar .collapse-menu .nav-link {
    padding: 8px 15px 8px 40px;
    font-size: 0.85rem;
    margin: 0;
    border-radius: 4px;
}

.inventory-sidebar .nav-link[aria-expanded="true"] {
    background-color: transparent;
    color: #333;
}

.inventory-sidebar .nav-link[aria-expanded="true"] i.fa-chevron-down {
    transform: rotate(180deg);
}

.inventory-sidebar i.fa-chevron-down {
    font-size: 0.8rem;
    transition: transform 0.2s ease;
}

.inventory-sidebar .form-control {
    font-size: 0.9rem;
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 10px;
}

/* Donor Management Section */
#donorManagementCollapse {
    margin-top: 2px;
    border: none;
    background-color: transparent;
}

#donorManagementCollapse .nav-link {
    color: #333;
    padding: 8px 15px 8px 40px;
}

#donorManagementCollapse .nav-link:hover {
    background-color: #dc3545;
    color: white;
}

/* Hospital Requests Section */
#hospitalRequestsCollapse .nav-link {
    color: #333;
    padding: 8px 15px 8px 40px;
    font-size: 0.9rem;
}

#hospitalRequestsCollapse .nav-link:hover {
    color: #dc3545;
    font-weight: 600;
    background-color: transparent;
}

/* Updated styles for the search bar */
.search-container {
    width: 100%;
    margin: 0 auto;
}

.input-group {
    width: 100%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-radius: 6px;
    margin-bottom: 1rem;
}

.input-group-text {
    background-color: #fff;
    border: 1px solid #ced4da;
    border-right: none;
    padding: 0.5rem 1rem;
}

.category-select {
    border: 1px solid #ced4da;
    border-right: none;
    border-left: none;
    background-color: #f8f9fa;
    cursor: pointer;
    min-width: 120px;
    height: 45px;
    font-size: 0.95rem;
}

.category-select:focus {
    box-shadow: none;
    border-color: #ced4da;
}

#searchInput {
    border: 1px solid #ced4da;
    border-left: none;
    padding: 0.5rem 1rem;
    font-size: 0.95rem;
    height: 45px;
    flex: 1;
}

#searchInput::placeholder {
    color: #adb5bd;
    font-size: 0.95rem;
}

#searchInput:focus {
    box-shadow: none;
    border-color: #ced4da;
}

.input-group:focus-within {
    box-shadow: 0 0 0 0.15rem rgba(0,123,255,.25);
}

.input-group-text i {
    font-size: 1.1rem;
    color: #6c757d;
}

/* Shared filter/search bar styling (mirrors hospital dashboard) */
.filter-search-bar {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.filter-dropdown {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 8px 12px;
    background: white;
}

.search-input-wrapper {
    flex: 1;
    position: relative;
}

.search-input {
    width: 100%;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 8px 40px 8px 12px;
    background: white;
}

.search-loading-spinner {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}
.request-metrics-grid .metric-card {
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background-color: #ffe6e6;
    border: 1px solid #f8c8c8;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.request-metrics-grid .metric-label {
    font-size: 1rem;
    font-weight: 600;
    color: #9a4a4a;
}
.request-metrics-grid .metric-value {
    font-size: 48px;
    font-weight: 700;
    color: #dc3545;
    margin: 10px 0;
}
.request-metrics-grid .metric-subtitle {
    font-size: 0.9rem;
    color: #b55b5b;
}

/* Main Content Styling */
.dashboard-home-main {
    margin-left: 240px; /* Matches sidebar */
    margin-top: 70px;
    min-height: 100vh;
    overflow-x: hidden;
    padding-bottom: 20px;
    padding-top: 20px;
    padding-left: 20px; /* Adjusted padding for balance */
    padding-right: 20px;
    transition: margin-left 0.3s ease;
}


/* Container Fluid Fix */
.container-fluid {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ============================== */
/* Responsive Design Adjustments  */
/* ============================== */

@media (max-width: 992px) {
    /* Adjust sidebar and header for tablets */
    .inventory-sidebar {
        width: 200px;
    }

    .dashboard-home-header {
        left: 200px;
        width: calc(100% - 200px);
    }

    .dashboard-home-main {
        margin-left: 200px;
    }
}

@media (max-width: 768px) {
    /* Collapse sidebar and expand content on smaller screens */
    .inventory-sidebar {
        width: 0;
        padding: 0;
        overflow: hidden;
    }

    .dashboard-home-header {
        left: 0;
        width: 100%;
    }

    .dashboard-home-main {
        margin-left: 0;
        padding: 10px;
    }


    .card {
        min-height: 100px;
        font-size: 14px;
    }

    
}



/* Medium Screens (Tablets) */
@media (max-width: 991px) {
    main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
        margin-left: 240px !important; 
    }
}

/* Small Screens (Mobile) */
@media (max-width: 768px) {
    main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
        margin-left: 0 !important; 
    }
}

.custom-margin {
    margin-top: 80px;
}

        .donor_form_container {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 1400px;
            width: 100%;
            font-size: 14px;
        }

        .donor_form_label {
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }

        .donor_form_input {
            width: 100%;
            padding: 6px;
            margin-bottom: 10px;
            border: 1px solid #000;
            border-radius: 3px;
            font-size: 14px;
            box-sizing: border-box;
            color: #757272;
        }

        .donor_form_grid {
            display: grid;
            gap: 5px;
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .grid-1 {
            grid-template-columns: 1fr;
        }
        .grid-6 {
    grid-template-columns: repeat(6, 1fr); 
}
.email-container {
            margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin-top: 100px;
        }

        .email-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            transition: background 0.3s;
        }

        .email-item:hover {
            background: #f1f1f1;
        }

        .email-header {
            position: left;
            font-weight: bold;
            color: #000000;
        }

        .email-subtext {
            font-size: 14px;
            color: gray;
        }

        .modal-header {
            background: #000000;;
            color: white;
        }

        .modal-body label {
            font-weight: bold;
        }
    .custom-alert {
        opacity: 0;
        transform: translateY(-20px);
        transition: opacity 0.5s ease-in-out, transform 0.5s ease-in-out;
    }

    .show-alert {
        opacity: 1;
        transform: translateY(0);
    }

    /* Sidebar Collapsible Styling */

    #donorManagementCollapse .nav-link:hover {
        background-color: #f8f9fa;
        color: #dc3545;
    }

    /* Add these modal styles */
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1040;
    }

    .modal {
        z-index: 1050;
    }

    /* Make sure the accept request modal appears on top */
    #acceptRequestModal {
        z-index: 1060;
    }

    .modal-dialog {
        margin: 1.75rem auto;
    }

    .modal-content {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    /* Uniform Button Styles */
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        border-radius: 0.25rem;
        transition: all 0.2s ease-in-out;
    }

    .btn-info {
        background-color: #0dcaf0;
        border-color: #0dcaf0;
        color: #000;
    }

    .btn-info:hover {
        background-color: #31d2f2;
        border-color: #25cff2;
        color: #000;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(13, 202, 240, 0.3);
    }

    .btn-info:active,
    .btn-info.active {
        background-color: #0aa2c0;
        border-color: #0a96b0;
        color: #000;
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(13, 202, 240, 0.4);
    }

    .btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #000;
    }

    .btn-warning:hover {
        background-color: #ffcd39;
        border-color: #ffc720;
        color: #000;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
    }

    .btn-warning:active,
    .btn-warning.active {
        background-color: #e0a800;
        border-color: #d39e00;
        color: #000;
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(255, 193, 7, 0.4);
    }

    .btn-success {
        background-color: #198754;
        border-color: #198754;
        color: #fff;
    }

    .btn-success:hover {
        background-color: #20c997;
        border-color: #1ab394;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3);
    }

    .btn-success:active,
    .btn-success.active {
        background-color: #146c43;
        border-color: #13653f;
        color: #fff;
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(25, 135, 84, 0.4);
    }

    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        color: #fff;
    }

    .btn-danger:hover {
        background-color: #e35d6a;
        border-color: #e04653;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
    }

    .btn-danger:active,
    .btn-danger.active {
        background-color: #b02a37;
        border-color: #a52834;
        color: #fff;
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.4);
    }

    .btn-secondary {
        background-color: #6c757d;
        border-color: #6c757d;
        color: #fff;
    }

    .btn-secondary:hover {
        background-color: #808a93;
        border-color: #7a8288;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
    }

    .btn-secondary:active,
    .btn-secondary.active {
        background-color: #545b62;
        border-color: #4e555b;
        color: #fff;
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(108, 117, 125, 0.4);
    }

    .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    .btn-primary:hover {
        background-color: #3d8bfd;
        border-color: #2680fd;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
    }

    .btn-primary:active,
    .btn-primary.active {
        background-color: #0b5ed7;
        border-color: #0a58ca;
        color: #fff;
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(13, 110, 253, 0.4);
    }

    /* Reduce rendering cost on large sections */
    .table-responsive, #requestTable { content-visibility: auto; contain-intrinsic-size: 800px 1200px; }

    /* Hide heavy sections until ready to avoid staggered row paints */
    .progressive-hide { visibility: hidden; }
    /* Lightweight skeleton for request table */
    .skeleton-list { margin: 8px 0 16px 0; }
    .skeleton-row {
        height: 52px;
        margin: 8px 0;
        border-radius: 6px;
        background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 37%, #f3f4f6 63%);
        background-size: 400% 100%;
        animation: shimmer 1.2s ease-in-out infinite;
    }
    @keyframes shimmer {
        0% { background-position: 100% 0; }
        100% { background-position: -100% 0; }
    }

    /* Priority Styling for Hospital Requests - Whole Row Highlighting */
    .priority-asap-urgent {
        background-color: rgba(220, 53, 69, 0.12) !important;
        border-left: 4px solid #dc3545 !important;
    }

    .priority-urgent {
        background-color: rgba(220, 53, 69, 0.12) !important;
        border-left: 4px solid #dc3545 !important;
    }

    .priority-normal {
        background-color: rgba(13, 110, 253, 0.08) !important;
        border-left: 4px solid #0d6efd !important;
    }
    
    /* Ensure all cells in the row have the background */
    #requestTable tbody tr.priority-asap-urgent td,
    #requestTable tbody tr.priority-urgent td,
    #requestTable tbody tr.priority-normal td {
        background-color: transparent !important;
    }

    /* Pulse animation for critical items */
    .priority-pulse {
        animation: priorityPulse 2s ease-in-out infinite;
    }

    @keyframes priorityPulse {
        0%, 100% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
        }
        50% {
            box-shadow: 0 0 0 4px rgba(220, 53, 69, 0);
        }
    }

    /* Ensure text readability on colored backgrounds */
    #requestTable tbody tr.priority-asap-urgent,
    #requestTable tbody tr.priority-urgent,
    #requestTable tbody tr.priority-normal {
        color: #212529 !important;
    }

    #requestTable tbody tr.priority-asap-urgent:hover,
    #requestTable tbody tr.priority-urgent:hover {
        background-color: rgba(220, 53, 69, 0.18) !important;
    }

    #requestTable tbody tr.priority-normal:hover {
        background-color: rgba(13, 110, 253, 0.12) !important;
    }

    /* Priority badge styling */
    .priority-badge-cell {
        vertical-align: middle;
    }

    /* Alert Container on Right Side */
    #deadlineAlertContainer {
        position: fixed;
        right: 20px;
        top: 100px;
        width: 320px;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
        z-index: 9999 !important; /* High z-index to ensure it's above everything */
        pointer-events: none;
    }

    /* Notification Badge */
    #deadlineNotificationBadge {
        position: fixed;
        right: 20px;
        top: 100px;
        width: 50px;
        height: 50px;
        background: #dc3545;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        z-index: 10000 !important;
        transition: transform 0.2s, box-shadow 0.2s;
        pointer-events: auto;
    }
    

    #deadlineNotificationBadge:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(220, 53, 69, 0.6);
    }

    #deadlineNotificationBadge i {
        color: white;
        font-size: 1.5rem;
    }

    #deadlineNotificationBadge .badge-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ffc107;
        color: #000;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
        border: 2px solid #fff;
    }

    .deadline-alert {
        pointer-events: auto;
        background: #fff;
        border-left: 4px solid #dc3545;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        margin-bottom: 12px;
        padding: 12px 16px;
        animation: slideInRight 0.3s ease-out;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        z-index: 10000 !important; /* Ensure alerts are above everything */
        position: relative;
    }

    .deadline-alert:hover {
        transform: translateX(-4px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }

    .deadline-alert-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .deadline-alert-title {
        font-weight: bold;
        color: #dc3545;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .deadline-alert-title i {
        font-size: 1.1rem;
    }

    .deadline-alert-close {
        background: none;
        border: none;
        color: #6c757d;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .deadline-alert-close:hover {
        color: #dc3545;
    }

    .deadline-alert-body {
        font-size: 0.85rem;
        color: #495057;
        line-height: 1.4;
    }

    .deadline-alert-body strong {
        color: #212529;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @media (max-width: 768px) {
        #deadlineAlertContainer {
            width: 280px;
            right: 10px;
            top: 80px;
        }
    }

    </style>
    <script>
    (function() {
        function reveal() {
            var sk = document.getElementById('skeletonRequests');
            if (sk) { sk.style.display = 'none'; }
            var els = document.querySelectorAll('.progressive-hide');
            for (var i = 0; i < els.length; i++) {
                els[i].style.visibility = 'visible';
                els[i].classList.remove('progressive-hide');
            }
        }
        function onReady() {
            var css = document.getElementById('bootstrap-css');
            if (css && !css.dataset.loaded) {
                css.addEventListener('load', reveal, { once: true });
                setTimeout(reveal, 600); // tighter fallback to reduce LCP on slow links
            } else {
                reveal();
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', onReady);
        } else {
            onReady();
        }
    })();
    </script>
</head>
<body>
    <?php include '../../src/views/modals/admin-donor-registration-modal.php'; ?>
    <!-- Add this modal for Accept Request confirmation -->
    <div class="modal fade" id="acceptRequestModal" tabindex="-1" aria-labelledby="acceptRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="acceptRequestModalLabel">Confirm Acceptance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to accept this blood request?</p>
                    <p>This will move the request to the Handed Over section and mark it as accepted.</p>
                    <input type="hidden" id="accept-request-id" name="request_id" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmAcceptBtn" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Confirm Acceptance
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($modal_error_message)): ?>
    <!-- Insufficient Inventory Modal -->
    <div class="modal fade show" id="insufficientInventoryModal" tabindex="-1" aria-labelledby="insufficientInventoryModalLabel" aria-modal="true" style="display: block; background: rgba(0,0,0,0.5);">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="insufficientInventoryModalLabel">Insufficient Blood Inventory</h5>
            <button type="button" class="btn-close btn-close-white" onclick="window.location.href=window.location.href;"></button>
          </div>
          <div class="modal-body">
            <?php echo nl2br(htmlspecialchars($modal_error_message)); ?>
            <input type="hidden" id="insufficientRequestId" value="<?php echo htmlspecialchars($_POST['request_id'] ?? '', ENT_QUOTES); ?>">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="rescheduleRequestBtn">Reschedule</button>
          </div>
        </div>
      </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      var modal = new bootstrap.Modal(document.getElementById('insufficientInventoryModal'));
      modal.show();

      // Attach reschedule logic
      document.getElementById('rescheduleRequestBtn').onclick = function() {
        var requestId = document.getElementById('insufficientRequestId') ? document.getElementById('insufficientRequestId').value : null;
        if (!requestId) {
          if (window.adminModal && window.adminModal.alert) {
            window.adminModal.alert('Request ID not found.');
          } else {
            console.error('Admin modal not available');
          }
          return;
        }
        // Send AJAX request to reschedule
        fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'reschedule_request=1&request_id=' + encodeURIComponent(requestId)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            if (window.adminModal && window.adminModal.alert) {
              window.adminModal.alert('Request has been rescheduled for 3 days later.').then(() => {
                window.location.reload();
              });
            } else {
              window.location.reload();
            }
          } else {
            if (window.adminModal && window.adminModal.alert) {
              window.adminModal.alert('Failed to reschedule: ' + (data.error || 'Unknown error'));
            } else {
              console.error('Admin modal not available');
            }
          }
        })
        .catch(err => {
          if (window.adminModal && window.adminModal.alert) {
            window.adminModal.alert('Error: ' + err);
          } else {
            console.error('Admin modal not available');
          }
        });
      };
    });
    </script>
    <?php endif; ?>

    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <button class="btn btn-danger" onclick="showConfirmationModal()">
                    <i class="fas fa-plus me-2"></i>Register Donor
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block inventory-sidebar">
                <div class="sidebar-main-content">
                    <div class="d-flex align-items-center ps-1 mb-3 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" width="65" height="65" style="width: 65px; height: 65px; object-fit: contain;" fetchpriority="high" decoding="async">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                    <ul class="nav flex-column">
                        <a href="dashboard-Inventory-System.php" class="nav-link">
                            <span><i class="fas fa-home"></i>Home</span>
                        </a>
                        <a href="dashboard-Inventory-System-list-of-donations.php" class="nav-link">
                            <span><i class="fas fa-users"></i>Donor Management</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                            <span><i class="fas fa-tint"></i>Blood Bank</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link active">
                            <span><i class="fas fa-list"></i>Hospital Requests</span>
                        </a>
                        <a href="dashboard-Inventory-System-Reports-reports-admin.php" class="nav-link">
                            <span><i class="fas fa-chart-line"></i>Forecast Reports</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Users.php" class="nav-link">
                            <span><i class="fas fa-user-cog"></i>Manage Users</span>
                        </a>
                    </ul>
                </div>
                
                <div class="logout-container">
                    <a href="../../assets/php_func/logout.php" class="nav-link logout-link">
                        <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                    </a>
                </div>
            </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mb-5">
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <strong>Error:</strong> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Deadline Alert Container (Right Side) -->
            <div id="deadlineAlertContainer"></div>
            <!-- Deadline Notification Badge -->
            <div id="deadlineNotificationBadge" title="Click to view dismissed deadline alerts">
                <i class="fas fa-bell"></i>
                <span class="badge-count" id="deadlineNotificationCount">0</span>
            </div>
            
            <div class="container-fluid p-3 email-container">
                <h2 class="text-left">
                    <?php
                        if ($status === 'accepted') {
                            echo 'Accepted Hospital Requests';
                        } elseif ($status === 'handedover') {
                            echo 'Handed Over Hospital Requests';
                        } elseif ($status === 'declined') {
                            echo 'Declined Hospital Requests';
                        } else {
                            echo 'Hospital Requests';
                        }
                    ?>
                </h2>
                <div class="row g-3 request-metrics-grid mb-4">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card">
                            <div class="metric-label"><?php echo htmlspecialchars($dashboardCardMetrics['most_requested']['label']); ?></div>
                            <div class="metric-value"><?php echo htmlspecialchars($dashboardCardMetrics['most_requested']['value']); ?></div>
                            <div class="metric-subtitle"><?php echo htmlspecialchars($dashboardCardMetrics['most_requested']['subtitle']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card">
                            <div class="metric-label"><?php echo htmlspecialchars($dashboardCardMetrics['pending']['label']); ?></div>
                            <div class="metric-value"><?php echo htmlspecialchars($dashboardCardMetrics['pending']['value']); ?></div>
                            <div class="metric-subtitle"><?php echo htmlspecialchars($dashboardCardMetrics['pending']['subtitle']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card">
                            <div class="metric-label"><?php echo htmlspecialchars($dashboardCardMetrics['critical']['label']); ?></div>
                            <div class="metric-value"><?php echo htmlspecialchars($dashboardCardMetrics['critical']['value']); ?></div>
                            <div class="metric-subtitle"><?php echo htmlspecialchars($dashboardCardMetrics['critical']['subtitle']); ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="metric-card">
                            <div class="metric-label"><?php echo htmlspecialchars($dashboardCardMetrics['today']['label']); ?></div>
                            <div class="metric-value"><?php echo htmlspecialchars($dashboardCardMetrics['today']['value']); ?></div>
                            <div class="metric-subtitle"><?php echo htmlspecialchars($dashboardCardMetrics['today']['subtitle']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="filter-search-bar">
                    <select class="filter-dropdown" id="statusFilterDropdown">
                        <option value="All Status">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Declined">Declined</option>
                        <option value="Completed">Completed</option>
                    </select>
                    <div class="search-input-wrapper">
                        <input type="text" class="search-input" placeholder="Search requests..." id="requestSearchBar">
                        <span class="search-loading-spinner" id="searchLoadingSpinner" style="display: none;">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="color: #941022;">
                                <span class="visually-hidden">Loading...</span>
                            </span>
                        </span>
                    </div>
                </div>
                
                <!-- Divider Line -->
                <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
            
                <!-- Blood Request Items -->
                <?php if (empty($blood_requests)): ?>
                    <div class="alert alert-info text-center">
                        <?php
                            if ($status === 'accepted') {
                                echo 'No accepted blood requests found.';
                            } elseif ($status === 'handedover') {
                                echo 'No handed over blood requests found.';
                            } elseif ($status === 'declined') {
                                echo 'No declined blood requests found.';
                            } else {
                                echo 'No hospital blood requests found.';
                            }
                        ?>
                    </div>
                <?php else: ?>
                <!-- Skeleton placeholder shown until CSS+table reveal -->
                <div id="skeletonRequests" class="skeleton-list">
                    <div class="skeleton-row"></div>
                    <div class="skeleton-row"></div>
                    <div class="skeleton-row"></div>
                    <div class="skeleton-row"></div>
                    <div class="skeleton-row"></div>
                </div>
                <div class="table-responsive progressive-hide">
                    <table class="table table-striped table-hover" id="requestTable">
                        <thead class="table-dark">
                            <tr>
                                <th>No.</th>
                                <th>Request ID</th>
                                <th>Blood Type</th>
                                <th>Quantity</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $rowNum=1; foreach ($blood_requests as $request): ?>
                            <?php 
                                $hospital_name = $request['hospital_admitted'] ? $request['hospital_admitted'] : 'Hospital';
                                $blood_type_display = $request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-');
                                $priority_display = $request['is_asap'] ? 'Urgent' : 'Routine';
                                $requested_on = $request['requested_on'] ? date('m/d/Y', strtotime($request['requested_on'])) : '-';
                                $is_asap = !empty($request['is_asap']);
                                
                                // Calculate priority for this request
                                $priority_data = calculateHospitalRequestPriority(
                                    $is_asap,
                                    $request['when_needed'] ?? null,
                                    $request['status'] ?? 'pending'
                                );
                            ?>
                            <tr class="table-row" data-row-index="<?php echo $rowNum - 1; ?>"
                                data-is-asap="<?php echo $is_asap ? 'true' : 'false'; ?>"
                                data-when-needed="<?php echo htmlspecialchars($request['when_needed'] ?? ''); ?>"
                                data-status="<?php echo htmlspecialchars($request['status'] ?? 'pending'); ?>"
                                data-priority-class="<?php echo htmlspecialchars($priority_data['urgency_class']); ?>"
                                data-is-urgent="<?php echo $priority_data['is_urgent'] ? 'true' : 'false'; ?>"
                                data-is-critical="<?php echo $priority_data['is_critical'] ? 'true' : 'false'; ?>"
                                data-time-remaining="<?php echo htmlspecialchars($priority_data['time_remaining']); ?>"
                                data-priority-level="<?php echo $priority_data['priority_level']; ?>"
                                data-is-one-day-before="<?php echo isset($priority_data['is_one_day_before']) && $priority_data['is_one_day_before'] ? 'true' : 'false'; ?>"
                                data-request-id="<?php echo htmlspecialchars($request['request_id']); ?>"
                                data-hospital-name="<?php echo htmlspecialchars($hospital_name); ?>">
                                <td><?php echo $rowNum++; ?></td>
                                <td><?php 
                                    // Display 14 characters of request_reference, skipping "REQ-" prefix
                                    $request_ref = $request['request_reference'] ?? '';
                                    if (!empty($request_ref)) {
                                        // Skip "REQ-" (4 characters) and take next 14 characters
                                        $display_ref = substr($request_ref, 4, 14);
                                        echo htmlspecialchars($display_ref);
                                    } else {
                                        echo htmlspecialchars($request['request_id']);
                                    }
                                ?></td>
                                <td><?php echo htmlspecialchars($blood_type_display); ?></td>
                                <td><?php echo htmlspecialchars($request['units_requested'] . ' Bags'); ?></td>
                                <td><?php echo htmlspecialchars($requested_on); ?></td>
                                <td>
                                    <?php
                                    $status_val = isset($request['status']) ? strtolower($request['status']) : '';
                                    if ($status_val === 'pending') {
                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                    } elseif ($status_val === 'rescheduled') {
                                        echo '<span class="badge bg-warning text-dark">Rescheduled</span>';
                                    } elseif ($status_val === 'approved') {
                                        echo '<span class="badge bg-primary">Approved</span>';
                                    } elseif ($status_val === 'printed') {
                                        echo '<span class="badge bg-primary">Approved</span>';
                                    } elseif ($status_val === 'completed') {
                                        echo '<span class="badge bg-success">Completed</span>';
                                    } elseif ($status_val === 'declined') {
                                        echo '<span class="badge bg-danger">Declined</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">'.htmlspecialchars($request['status'] ?? 'N/A').'</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button 
                                        class="btn btn-info btn-sm view-btn"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#requestModal"
                                        title="View Details"
                                        onclick="console.log('PHP Status being passed:', '<?php echo addslashes($request['status']); ?>'); loadRequestDetails(
                                            '<?php echo htmlspecialchars($request['request_id']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_name']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_blood_type']); ?>',
                                            'Whole Blood',
                                            '<?php echo htmlspecialchars($request['rh_factor']); ?>',
                                            '<?php echo htmlspecialchars($request['units_requested']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_diagnosis']); ?>',
                                            '<?php echo htmlspecialchars($hospital_name); ?>',
                                            '<?php echo htmlspecialchars($request['physician_name']); ?>',
                                            '<?php echo htmlspecialchars($priority_display); ?>',
                                            '<?php echo htmlspecialchars($request['status']); ?>',
                                            '<?php echo htmlspecialchars($request['requested_on']); ?>',
                                            '<?php echo htmlspecialchars($request['when_needed']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_age']); ?>',
                                            '<?php echo htmlspecialchars($request['patient_gender']); ?>',
                                            '<?php echo htmlspecialchars($request['handed_over_by'] ?? ''); ?>',
                                            '<?php echo htmlspecialchars($request['handed_over_date'] ?? ''); ?>',
                                                '<?php echo htmlspecialchars(($handed_over_name_map[$request['handed_over_by']] ?? '') ?: ''); ?>'
                                        )"
                                        >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <!-- Pagination -->
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&laquo;&laquo;</span>
                                </li>
                                <li class="page-item disabled">
                                    <span class="page-link">&laquo;</span>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            // Calculate page range to show
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            // Adjust if we're near the start
                            if ($page <= 3) {
                                $end_page = min($total_pages, 5);
                            }
                            // Adjust if we're near the end
                            if ($page >= $total_pages - 2) {
                                $start_page = max(1, $total_pages - 4);
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link">&raquo;</span>
                                </li>
                                <li class="page-item disabled">
                                    <span class="page-link">&raquo;&raquo;</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <div class="text-center text-muted small mt-2">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $page_size, $total_count); ?> of <?php echo $total_count; ?> requests
                        </div>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
            
        
        <!-- Referral Blood Shipment Record Modal -->
        <div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div id="alertContainer"></div>
                <div class="modal-content" style="border-radius: 10px; border: none;">
                    <!-- Modal Header -->
                    <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0; padding: 20px;">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 5px;">
                                    Date: <span id="modalRequestDate">-</span>
                    </div>
                                <h4 class="modal-title mb-0" style="font-weight: bold; font-size: 1.5rem;">
                                    Referral Blood Shipment Record
                                </h4>
                            </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span id="modalRequestStatus" class="badge" style="background: #ffc107; padding: 8px 12px; font-size: 0.9rem;">Pending</span>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="margin-left: 10px;"></button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal Body -->
                    <div class="modal-body" style="padding: 30px;">
                        <form id="requestDetailsForm">
                            <input type="hidden" id="modalRequestId" name="request_id">
                            
                            <!-- Patient Information -->
                            <div class="mb-4">
                                <h5 style="font-weight: bold; color: #333; margin-bottom: 15px;">Patient Information</h5>
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4 style="font-weight: bold; color: #000; margin-bottom: 5px;" id="modalPatientName">-</h4>
                                        <p style="color: #666; margin: 0; font-size: 1.05rem;" id="modalPatientDetails">-</p>
                                        <div class="d-flex gap-4 mt-2" style="color:#444;">
                                            <div><span class="fw-bold">Age:</span> <span id="modalPatientAge">-</span></div>
                                            <div><span class="fw-bold"> Gender:</span> <span id="modalPatientGender">-</span></div>
                                </div>
                                </div>
                                </div>
                            </div>
                            
                            <hr style="border-color: #ddd; margin: 20px 0;">
                            
                            <!-- Request Details -->
                            <div class="mb-4">
                                <h5 style="font-weight: bold; color: #333; margin-bottom: 20px;">Request Details</h5>
                                
                                <!-- Diagnosis -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Diagnosis:</label>
                                    <input type="text" class="form-control" id="modalDiagnosis" readonly style="border: 1px solid #ddd; padding: 10px;">
                                </div>
                            
                                <!-- Blood Type Table -->
                                <div class="mb-3">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" style="margin: 0;">
                                            <thead style="background: #dc3545; color: white;">
                                                <tr>
                                                    <th style="padding: 12px; text-align: center;">Blood Type</th>
                                                    <th style="padding: 12px; text-align: center;">RH</th>
                                                    <th style="padding: 12px; text-align: center;">Number of Units</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td style="padding: 12px; text-align: center;">
                                                        <input type="text" class="form-control text-center" id="modalBloodType" readonly style="border: none; background: transparent;">
                                                    </td>
                                                    <td style="padding: 12px; text-align: center;">
                                                        <input type="text" class="form-control text-center" id="modalRhFactor" readonly style="border: none; background: transparent;">
                                                    </td>
                                                    <td style="padding: 12px; text-align: center;">
                                                        <input type="text" class="form-control text-center" id="modalUnitsNeeded" readonly style="border: none; background: transparent;">
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                </div>
                            </div>
                            
                                <!-- When Needed -->
                            <div class="mb-3">
                                    <label class="form-label fw-bold">When Needed:</label>
                                    <div class="d-flex align-items-center gap-4" style="border:1px solid #ddd; border-radius:6px; padding:10px 12px;">
                                            <div class="form-check d-flex align-items-center gap-2 me-3">
                                                <input class="form-check-input" type="radio" name="whenNeededOption" id="asapRadio" value="asap" disabled>
                                            <label class="form-check-label fw-bold" for="asapRadio">ASAP</label>
                                        </div>
                                            <div class="form-check d-flex align-items-center gap-2">
                                                <input class="form-check-input" type="radio" name="whenNeededOption" id="scheduledRadio" value="scheduled" disabled>
                                            <label class="form-check-label fw-bold" for="scheduledRadio">Scheduled</label>
                                            <input type="text" class="form-control" id="modalScheduledDisplay" style="width: 240px; margin-left: 10px;" readonly>
                                        </div>
                                    </div>
                            </div>
        
                                <!-- Hospital and Physician -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                        <label class="form-label fw-bold">Hospital Admitted:</label>
                                        <input type="text" class="form-control" id="modalHospital" readonly style="border: 1px solid #ddd; padding: 10px;">
                                </div>
                                <div class="col-md-6">
                                        <label class="form-label fw-bold">Requesting Physician:</label>
                                        <input type="text" class="form-control" id="modalPhysician" readonly style="border: 1px solid #ddd; padding: 10px;">
                                </div>
                            </div>
        
                                <!-- Approval Information (shown when approved) -->
                                <div id="approvalSection" style="display: none;">
                                    <hr style="border-color: #ddd; margin: 20px 0;">
                            <div class="mb-3">
                                        <label class="form-label fw-bold">Approved by:</label>
                                        <input type="text" class="form-control" id="modalApprovedBy" readonly style="border: 1px solid #ddd; padding: 10px; background: #f8f9fa;">
                                    </div>
                            </div>
                            
                                <!-- Handover Information (shown when handed over) -->
                                <div id="handoverSection" style="display: none;">
                                    <hr style="border-color: #ddd; margin: 20px 0;">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Handed Over by:</label>
                                        <input type="text" class="form-control" id="modalHandedOverBy" readonly style="border: 1px solid #ddd; padding: 10px; background: #f8f9fa;">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Modal Footer -->
                    <div class="modal-footer" style="padding: 20px 30px; border-top: 1px solid #ddd; background: #f8f9fa; border-radius: 0 0 10px 10px;">
                        <div class="d-flex gap-2 w-100 justify-content-end">
                            <!-- Approve Button -->
                            <button type="button" class="btn btn-success" id="modalAcceptButton" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; display: none;">
                                <i class="fas fa-check-circle me-2"></i>Approve Request
                                </button>
                            
                            <!-- Hand Over Button -->
                            <button type="button" class="btn btn-primary" id="handOverButton" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; display: none;">
                                <i class="fas fa-truck me-2"></i>Hand Over
                                </button>
                            </div>
                    </div>
                </div>
            </div>
            </div>
            
</main>
            
        </div>
    </div>
    
    <!-- Approve Request Confirmation Modal -->
    <div class="modal fade" id="approveConfirmModal" tabindex="-1" aria-labelledby="approveConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="approveConfirmLabel" style="font-weight: bold;">Approve Request?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p id="approveConfirmText" style="margin: 0 0 20px 0; font-size: 1rem; color: #333; line-height: 1.6;">
                        Are you sure you want to approve this blood request? The requested units will be prepared for handover.
                    </p>
                    
                    <!-- Blood Type Information - Smooth integrated design -->
                    <div id="approveBloodTypeInfo" style="display: none; margin-bottom: 15px;">
                        <div style="background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%); border-left: 3px solid #2196f3; padding: 12px 16px; border-radius: 6px; margin-bottom: 0;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <i class="fas fa-tint" style="color: #2196f3; font-size: 1rem;"></i>
                                <span style="font-weight: 600; color: #1976d2; font-size: 0.95rem;">Blood Type Required:</span>
                                <span id="approveBloodTypeNeeded" style="font-weight: bold; color: #1565c0; font-size: 1rem;">-</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                <i class="fas fa-boxes" style="color: #2196f3; font-size: 0.9rem;"></i>
                                <span style="font-weight: 600; color: #1976d2; font-size: 0.95rem;">Available Units:</span>
                                <span id="approveAvailableUnits" style="font-weight: bold; color: #1565c0;">-</span>
                                <span style="color: #666; font-size: 0.9rem;">/</span>
                                <span id="approveRequiredUnits" style="font-weight: bold; color: #1565c0;">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- No Blood Available Alert - Smooth integrated design -->
                    <div id="approveNoBloodAlert" style="display: none; margin-bottom: 15px;">
                        <div style="background: linear-gradient(135deg, #ffebee 0%, #fce4ec 100%); border-left: 3px solid #f44336; padding: 12px 16px; border-radius: 6px; margin-bottom: 0;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <i class="fas fa-exclamation-triangle" style="color: #f44336; font-size: 1rem;"></i>
                                <span style="font-weight: 600; color: #c62828; font-size: 0.95rem;">Insufficient Blood Supply</span>
                            </div>
                            <p id="approveNoBloodMessage" style="margin: 4px 0 0 0; color: #b71c1c; font-size: 0.9rem; line-height: 1.5;"></p>
                        </div>
                    </div>
                    
                    <!-- Buffer Usage Alert -->
                    <div id="approveBufferUsageAlert" style="display: none; margin-bottom: 15px;">
                        <div style="background: linear-gradient(135deg, #fff8e1 0%, #fff3c4 100%); border-left: 3px solid #f7d774; padding: 12px 16px; border-radius: 6px; margin-bottom: 0;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <i class="fas fa-shield-alt" style="color: #a06a00; font-size: 1rem;"></i>
                                <span style="font-weight: 600; color: #a06a00; font-size: 0.95rem;">Buffer Reserve Notice</span>
                            </div>
                            <p id="approveBufferUsageMessage" style="margin: 4px 0 0 0; color: #8a5300; font-size: 0.9rem; line-height: 1.5;"></p>
                        </div>
                    </div>
                    
                    <!-- Loading State -->
                    <div id="approveLoadingState" class="text-center" style="padding: 20px;">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 text-muted" style="font-size: 0.9rem;">Checking blood availability...</p>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmApproveBtn" style="padding: 8px 20px; font-weight: bold; background: #007bff;">Accept</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Decline Request Modal -->
    <div class="modal fade" id="declineRequestModal" tabindex="-1" aria-labelledby="declineRequestModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="declineRequestModalLabel" style="font-weight: bold;">Decline Request?</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="declineRequestForm" method="post">
                    <div class="modal-body" style="padding: 25px;">
              <input type="hidden" name="request_id" id="declineRequestId">
              <input type="hidden" name="decline_request" value="1">
                        <p style="margin-bottom: 20px; font-size: 1rem; color: #333;">
                            Are you sure you want to decline this request for <strong id="declineRequestIdText">Request ID</strong>?
                        </p>
                        <p style="margin-bottom: 20px; font-size: 0.9rem; color: #dc3545; font-weight: bold;">
                            This action cannot be undone.
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-bold" style="color: #333;">Reason for Declining</label>
                            <select class="form-select" name="decline_reason" id="declineReasonText" style="border: 1px solid #ddd; padding: 10px;" required>
                                <option value="" selected disabled>Select reason</option>
                                <option value="Low Blood Supply">Low Blood Supply</option>
                                <option value="Ineligible Requestor">Ineligible Requestor</option>
                                <option value="Medical Restrictions">Medical Restrictions</option>
                                <option value="Pending Verification">Pending Verification</option>
                                <option value="Duplicate Request">Duplicate Request</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">Cancel</button>
                        <button type="submit" class="btn btn-danger" style="padding: 8px 20px; font-weight: bold;">Confirm</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <!-- Request Declined Success Modal -->
    <div class="modal fade" id="requestDeclinedModal" tabindex="-1" aria-labelledby="requestDeclinedLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="requestDeclinedLabel" style="font-weight: bold;">Request Declined</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin: 0; font-size: 1rem; color: #333;">
                        Request <strong id="declinedRequestId">HRQ-00125</strong> has been declined.
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 0.9rem; color: #333;">
                        Reason: <strong id="declinedReason">Insufficient stock of O+</strong>
                    </p>
          </div>
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Request Approved Success Modal -->
    <div class="modal fade" id="requestApprovedModal" tabindex="-1" aria-labelledby="requestApprovedLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="requestApprovedLabel" style="font-weight: bold;">Request Approved</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin: 0; font-size: 1rem; color: #333;">
                        The blood units have been allocated to this request.
                    </p>
                </div>
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Handover Confirmation Modal -->
    <div class="modal fade" id="handoverConfirmModal" tabindex="-1" aria-labelledby="handoverConfirmLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0; padding: 20px;">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <div style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 5px;">
                                Date: <span id="handoverModalDate">-</span>
                            </div>
                            <h4 class="modal-title mb-0" style="font-weight: bold; font-size: 1.5rem;">
                                Handover Blood Units
                            </h4>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="margin-left: 10px;"></button>
                    </div>
                </div>
                
                <div class="modal-body" style="padding: 30px;">
                    <!-- Hospital Information -->
                    <div class="mb-4">
                        <h5 style="font-weight: bold; color: #333; margin-bottom: 15px;">Hospital Information</h5>
                        <div class="row">
                            <div class="col-md-8">
                                <h4 style="font-weight: bold; color: #000; margin-bottom: 5px;" id="handoverHospitalName">-</h4>
                                <p style="color: #666; margin: 0; font-size: 1.1rem;" id="handoverHospitalDetails">-</p>
                            </div>
                        </div>
                    </div>
                    
                    <hr style="border-color: #ddd; margin: 20px 0;">
                    
                    <!-- Blood Units Table -->
                    <div class="mb-4">
                        <h5 style="font-weight: bold; color: #333; margin-bottom: 20px;">Blood Units to Hand Over</h5>
                        <div class="alert alert-warning d-none" id="handoverBufferWarning" role="alert" style="border-radius: 6px;">
                            <i class="fas fa-shield-alt me-2"></i>
                            <span id="handoverBufferWarningText">Buffer units will be used for this handover.</span>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered" style="margin: 0;">
                                <thead style="background: #dc3545; color: white;">
                                    <tr>
                                        <th style="padding: 12px; text-align: center;">Unit Serial Number</th>
                                        <th style="padding: 12px; text-align: center;">Blood Type</th>
                                        <th style="padding: 12px; text-align: center;">Bag Type</th>
                                        <th style="padding: 12px; text-align: center;">Expiration Date</th>
                                        <th style="padding: 12px; text-align: center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="handoverUnitsTableBody">
                                    <!-- Blood units will be populated here -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Summary Information -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card" style="border: 1px solid #ddd; border-radius: 5px;">
                                    <div class="card-body" style="padding: 15px;">
                                        <h6 style="font-weight: bold; margin-bottom: 10px; color: #333;">Request Summary</h6>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Request ID:</strong> <span id="handoverRequestId">-</span></p>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Patient:</strong> <span id="handoverPatientName">-</span></p>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Total Units:</strong> <span id="handoverTotalUnits">-</span></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card" style="border: 1px solid #ddd; border-radius: 5px;">
                                    <div class="card-body" style="padding: 15px;">
                                        <h6 style="font-weight: bold; margin-bottom: 10px; color: #333;">Handover Details</h6>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Handed Over by:</strong> <span id="handoverStaffName">-</span></p>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Date & Time:</strong> <span id="handoverDateTime">-</span></p>
                                        <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Status:</strong> <span class="badge bg-success">Ready for Handover</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: 20px 30px; border-top: 1px solid #ddd; background: #f8f9fa; border-radius: 0 0 10px 10px;">
                    <div class="d-flex gap-2 w-100 justify-content-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 10px 20px; font-weight: bold; border-radius: 5px;">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmHandoverBtn" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; background: #007bff;">
                            <i class="fas fa-truck me-2"></i>Confirm Handover
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Handover Success Modal -->
    <div class="modal fade" id="handoverSuccessModal" tabindex="-1" aria-labelledby="handoverSuccessLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px; border: none;">
                <div class="modal-header" style="background: #dc3545; color: white; border-radius: 10px 10px 0 0;">
                    <h5 class="modal-title" id="handoverSuccessLabel" style="font-weight: bold;">Handover Successful</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 25px;">
                    <p style="margin: 0; font-size: 1rem; color: #333;">
                        Requested blood units have been successfully handed over and marked as completed.
                    </p>
                </div>
                <div class="modal-footer" style="padding: 20px 25px; border-top: 1px solid #ddd;">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="padding: 8px 20px; font-weight: bold;">OK</button>
          </div>
        </div>
      </div>
    </div>
    
    <?php
        $bufferJsPayload = [
            'count' => $bufferReserveCount,
            'unit_ids' => array_keys($bufferContext['buffer_lookup']['by_id']),
            'unit_serials' => array_keys($bufferContext['buffer_lookup']['by_serial']),
            'types' => $bufferContext['buffer_types'],
            'page' => 'hospital-requests'
        ];
    ?>
    <script>
        window.BUFFER_BLOOD_CONTEXT = <?php echo json_encode($bufferJsPayload); ?>;
    </script>
    <script src="../../assets/js/buffer-blood-manager.js"></script>
    <script src="../../assets/js/filter-loading-modal.js"></script>
    <script src="../../assets/js/search_func/filter_search_hospital_blood_requests.js"></script>
    <!-- jQuery first (if needed) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin-feedback-modal.js"></script>

    <script>
    // Get admin name from PHP
    const adminName = '<?php echo addslashes($admin_name); ?>';
    const adminNamePlain = '<?php echo addslashes(getAdminName($_SESSION['user_id'] ?? '', false)); ?>';
    
    // Enhanced function to populate the modal fields based on wireframe design
    function loadRequestDetails(request_id, patientName, bloodType, component, rhFactor, unitsNeeded, diagnosis, hospital, physician, priority, status, requestDate, whenNeeded, patientAge, patientGender, handedOverBy, handedOverDate, handedOverByAdminName) {
        console.log('=== loadRequestDetails DEBUG ===');
        console.log('All arguments:', arguments);
        console.log('Status parameter (11th argument):', status);
        console.log('Status type:', typeof status);
        console.log('Status value:', JSON.stringify(status));
        console.log('Arguments length:', arguments.length);
        
        // Set basic request info
        document.getElementById('modalRequestId').value = request_id;
        document.getElementById('modalRequestDate').textContent = requestDate ? new Date(requestDate).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        }) : '-';
        
        // Set patient information
        document.getElementById('modalPatientName').textContent = patientName || '-';
        // Remove units/priority line under name per request
        document.getElementById('modalPatientDetails').textContent = '';
        // Age and Gender fields (prefer arguments; fallback to __currentRequest)
        const ageVal = (patientAge !== undefined && patientAge !== null && patientAge !== '') ? patientAge : (window.__currentRequest && window.__currentRequest.patient_age);
        const genderVal = (patientGender !== undefined && patientGender !== null && patientGender !== '') ? patientGender : (window.__currentRequest && window.__currentRequest.patient_gender);
        document.getElementById('modalPatientAge').textContent = (ageVal !== undefined && ageVal !== null && ageVal !== '') ? ageVal : '-';
        document.getElementById('modalPatientGender').textContent = (genderVal !== undefined && genderVal !== null && genderVal !== '') ? genderVal : '-';
        
        // Set request details
        document.getElementById('modalDiagnosis').value = diagnosis || '';
        document.getElementById('modalBloodType').value = bloodType || '';
        document.getElementById('modalRhFactor').value = rhFactor || '';
        document.getElementById('modalUnitsNeeded').value = unitsNeeded || '';
        document.getElementById('modalHospital').value = hospital || '';
        document.getElementById('modalPhysician').value = physician || '';
        
        // Handle When Needed (ASAP checkbox + scheduled formatted string)
        const asapRadio = document.getElementById('asapRadio');
        const scheduledRadio = document.getElementById('scheduledRadio');
        const scheduledDisplay = document.getElementById('modalScheduledDisplay');
        const isAsap = (priority === 'Urgent' || priority === 'ASAP');
        if (asapRadio && scheduledRadio) {
            asapRadio.checked = !!isAsap;
            scheduledRadio.checked = !isAsap;
        }
        if (scheduledDisplay) {
            if (whenNeeded) {
                const d = new Date(whenNeeded);
                const pad = (n) => n.toString().padStart(2,'0');
                const day = pad(d.getDate());
                const month = pad(d.getMonth()+1);
                const year = d.getFullYear();
                let hours = d.getHours();
                const minutes = pad(d.getMinutes());
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12; if (hours === 0) hours = 12;
                const time = `${hours}:${minutes} ${ampm}`;
                scheduledDisplay.value = `${day}/${month}/${year} ${time}`;
            } else {
                scheduledDisplay.value = '';
            }
        }
        
        // Note: Event listeners removed since modal fields are readonly
        
        // Set status badge with proper mapping for new flow
        const statusBadge = document.getElementById('modalRequestStatus');
        let displayStatus = status || 'Pending';
        
        console.log('=== STATUS MAPPING DEBUG ===');
        console.log('Original status from parameter:', status);
        console.log('Status badge element:', statusBadge);
        console.log('Status badge current text before update:', statusBadge ? statusBadge.textContent : 'Element not found');
        
        // Map database statuses to display statuses for Referral Blood Shipment Record modal
        switch(status.toLowerCase()) {
            case 'pending':
                displayStatus = 'Pending';
                break;
            case 'approved':
                displayStatus = 'Approved';
                break;
            case 'printed':
                displayStatus = 'Approved'; // Show Printed status as Approved
                break;
            case 'completed':
                displayStatus = 'Handed-Over';
                break;
            case 'declined':
                displayStatus = 'Declined';
                break;
            case 'rescheduled':
                displayStatus = 'Rescheduled';
                break;
            default:
                displayStatus = status || 'Pending';
        }
        
        console.log('Final displayStatus after mapping:', displayStatus);
        console.log('About to update status badge text to:', displayStatus);
        statusBadge.textContent = displayStatus;
        console.log('Status badge text after update:', statusBadge.textContent);
        console.log('=== END STATUS MAPPING DEBUG ===');
        
        // Set status badge color based on display status (matching new flow)
        switch(displayStatus) {
            case 'Pending':
            case 'Rescheduled':
                statusBadge.style.background = '#ffc107';
                statusBadge.style.color = '#000';
                break;
            case 'Approved':
                statusBadge.style.background = '#0d6efd';
                statusBadge.style.color = '#fff';
                break;
            case 'Handed-Over':
                statusBadge.style.background = '#28a745';
                statusBadge.style.color = '#fff';
                break;
            case 'Declined':
                statusBadge.style.background = '#dc3545';
                statusBadge.style.color = '#fff';
                break;
            default:
                statusBadge.style.background = '#6c757d';
                statusBadge.style.color = '#fff';
        }
        
        // Handle button visibility and sections based on status
        const acceptButton = document.getElementById('modalAcceptButton');
        const handOverButton = document.getElementById('handOverButton');
        const approvalSection = document.getElementById('approvalSection');
        const handoverSection = document.getElementById('handoverSection');
        
        // Reset all sections
        if (approvalSection) approvalSection.style.display = 'none';
        if (handoverSection) handoverSection.style.display = 'none';
        
        if (acceptButton && handOverButton) {
            // Use displayStatus to determine controls
            if (['Pending', 'Rescheduled'].includes(displayStatus)) {
                acceptButton.style.display = 'inline-block';
                handOverButton.style.display = 'none';
            }
            // Show approval info for Approved status (includes both 'approved' and 'printed' database statuses)
            else if (['Approved'].includes(displayStatus)) {
                acceptButton.style.display = 'none';
                // Show Hand Over button for Printed status (ready for handover), hide for Approved
                if (status.toLowerCase() === 'printed') {
                    handOverButton.style.display = 'inline-block';
                } else {
                    handOverButton.style.display = 'none';
                }
                if (approvalSection) {
                    approvalSection.style.display = 'block';
                    document.getElementById('modalApprovedBy').value = `Approved by ${adminName} - ${new Date().toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })} at ${new Date().toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    })}`;
                }
            }
            // Show handover info for Handed-Over status (completed)
            else if (['Handed-Over'].includes(displayStatus)) {
                acceptButton.style.display = 'none';
                handOverButton.style.display = 'none';
                if (handoverSection) {
                    handoverSection.style.display = 'block';
                    
                    // Use actual handed over date if available, otherwise use current date
                    let handedOverDisplayDate = new Date();
                    if (handedOverDate) {
                        const parsedDate = new Date(handedOverDate);
                        if (!isNaN(parsedDate.getTime())) {
                            handedOverDisplayDate = parsedDate;
                        }
                    }
                    
                    // Use the actual admin name passed from PHP
                    let handedOverByAdmin = handedOverByAdminName || 'Admin';
                    
                    document.getElementById('modalHandedOverBy').value = `Handed Over by ${handedOverByAdmin} - ${handedOverDisplayDate.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })} at ${handedOverDisplayDate.toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    })}`;
                }
            }
            // Hide all buttons for other statuses (Declined, etc.)
            else {
                acceptButton.style.display = 'none';
                handOverButton.style.display = 'none';
            }
        }
    }

    // Function to populate handover modal with blood units data
    function populateHandoverModal(data) {
        // Set basic information
        document.getElementById('handoverModalDate').textContent = new Date().toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        document.getElementById('handoverHospitalName').textContent = data.request.hospital_admitted || 'Hospital';
        document.getElementById('handoverHospitalDetails').textContent = `Request ID: ${data.request.request_id} | Patient: ${data.request.patient_name}`;
        
        const bufferWarning = document.getElementById('handoverBufferWarning');
        const bufferWarningText = document.getElementById('handoverBufferWarningText');
        if (bufferWarning) {
            if (data.buffer_usage && data.buffer_usage.used) {
                bufferWarning.classList.remove('d-none');
                if (bufferWarningText) {
                    bufferWarningText.textContent = data.buffer_usage.message;
                }
                if (window.BufferBloodToolkit && typeof window.BufferBloodToolkit.showToast === 'function') {
                    window.BufferBloodToolkit.showToast(data.buffer_usage.message);
                }
            } else {
                bufferWarning.classList.add('d-none');
            }
        }
        
        // Set summary information
        document.getElementById('handoverRequestId').textContent = data.request.request_id;
        document.getElementById('handoverPatientName').textContent = data.request.patient_name;
        document.getElementById('handoverTotalUnits').textContent = data.total_units;
        
        // Set handover details
        document.getElementById('handoverStaffName').textContent = adminNamePlain || 'Staff Member';
        document.getElementById('handoverDateTime').textContent = new Date().toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Populate blood units table
        const tableBody = document.getElementById('handoverUnitsTableBody');
        tableBody.innerHTML = '';
        
        if (data.blood_units && data.blood_units.length > 0) {
            data.blood_units.forEach((unit, index) => {
                const row = document.createElement('tr');
                if (unit.is_buffer) {
                    row.classList.add('buffer-row');
                }
                const statusClass = unit.is_buffer ? 'bg-warning text-dark' : 'bg-success';
                const bufferLabel = unit.is_buffer ? '<span class="buffer-badge ms-1">Buffer</span>' : '';
                row.innerHTML = `
                    <td style="padding: 12px; text-align: center; font-weight: bold;">${unit.serial_number || '-'} ${bufferLabel}</td>
                    <td style="padding: 12px; text-align: center;">${unit.blood_type}</td>
                    <td style="padding: 12px; text-align: center;">${unit.bag_type}</td>
                    <td style="padding: 12px; text-align: center;">${unit.expiration_date}</td>
                    <td style="padding: 12px; text-align: center;">
                        <span class="badge ${statusClass}">${unit.status}</span>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        } else {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="5" style="padding: 20px; text-align: center; color: #666;">
                    No compatible blood units available
                </td>
            `;
            tableBody.appendChild(row);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        const requestDetailsModal = new bootstrap.Modal(document.getElementById('requestModal'));
        const approveConfirmModal = new bootstrap.Modal(document.getElementById('approveConfirmModal'));
        const requestApprovedModal = new bootstrap.Modal(document.getElementById('requestApprovedModal'));
        const handoverConfirmModal = new bootstrap.Modal(document.getElementById('handoverConfirmModal'));
        const handoverSuccessModal = new bootstrap.Modal(document.getElementById('handoverSuccessModal'));
        const approveBufferUsageAlert = document.getElementById('approveBufferUsageAlert');
        const approveBufferUsageMessage = document.getElementById('approveBufferUsageMessage');
        let pendingApprovalUnits = [];
        let pendingApprovalRequestId = null;
        
        // Function to show donor registration modal
        window.showConfirmationModal = function() {
            if (typeof window.openAdminDonorRegistrationModal === 'function') {
                window.openAdminDonorRegistrationModal();
            } else {
                console.error('Admin donor registration modal not available yet');
                if (window.adminModal && window.adminModal.alert) {
                    window.adminModal.alert('Registration modal is still loading. Please try again in a moment.');
                } else {
                    console.error('Admin modal not available');
                }
            }
        };

        // Get elements for request handling
        const responseSelect = document.getElementById("responseSelect");
        const alertContainer = document.getElementById("alertContainer");
        const modalBodyText = document.getElementById("modalBodyText");
        const confirmAcceptBtn = document.getElementById("confirmAcceptBtn");
        const requestDetailsForm = document.getElementById("requestDetailsForm");

        // Function to show alert messages
        function showAlert(type, message) {
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show mt-2" role="alert">
                    <strong>${message}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;

            setTimeout(() => {
                let alertBox = alertContainer.querySelector(".alert");
                if (alertBox) {
                    alertBox.classList.remove("show");
                    setTimeout(() => alertBox.remove(), 500);
                }
            }, 5000);
        }

        // Approve Request button logic
        document.getElementById('modalAcceptButton').addEventListener('click', function() {
            // Get request ID
            var requestId = document.getElementById('modalRequestId').value;
            
            // Show loading state in approve modal
            document.getElementById('approveLoadingState').style.display = 'block';
            document.getElementById('approveBloodTypeInfo').style.display = 'none';
            document.getElementById('approveNoBloodAlert').style.display = 'none';
            document.getElementById('confirmApproveBtn').disabled = true;
            if (approveBufferUsageAlert) {
                approveBufferUsageAlert.style.display = 'none';
            }
            if (approveBufferUsageMessage) {
                approveBufferUsageMessage.textContent = '';
            }
            
            // Hide the main modal and show approval confirmation
            requestDetailsModal.hide();
            setTimeout(() => {
                approveConfirmModal.show();
                
                // Fetch blood units for this request (same as handover)
                fetchJson(`../api/get-blood-units-for-request.php?request_id=${encodeURIComponent(requestId)}&t=${Date.now()}`)
                    .then(data => {
                        // Hide loading
                        document.getElementById('approveLoadingState').style.display = 'none';
                        
                        if (data.success) {
                            pendingApprovalUnits = (data.blood_units || [])
                                .map(unit => unit.unit_id)
                                .filter(id => !!id);
                            pendingApprovalRequestId = requestId;
                            const bloodTypeNeeded = data.request.blood_type_needed || '-';
                            const availableUnits = data.total_units || 0;
                            const requiredUnits = data.request.units_requested || 0;
                            
                            // Show blood type info
                            document.getElementById('approveBloodTypeNeeded').textContent = bloodTypeNeeded;
                            document.getElementById('approveAvailableUnits').textContent = availableUnits;
                            document.getElementById('approveRequiredUnits').textContent = requiredUnits;
                            document.getElementById('approveBloodTypeInfo').style.display = 'block';
                            
                            // Check if sufficient blood available
                            if (availableUnits >= requiredUnits) {
                                // Sufficient blood - enable approve button
                                document.getElementById('confirmApproveBtn').disabled = false;
                                document.getElementById('approveNoBloodAlert').style.display = 'none';
                                if (approveBufferUsageAlert && approveBufferUsageMessage) {
                                    if (data.buffer_usage && data.buffer_usage.used) {
                                        approveBufferUsageMessage.textContent = data.buffer_usage.message || 'Buffer reserve will be tapped to fulfill this request.';
                                        approveBufferUsageAlert.style.display = 'block';
                                    } else {
                                        approveBufferUsageAlert.style.display = 'none';
                                        approveBufferUsageMessage.textContent = '';
                                    }
                                }
                            } else {
                                // Insufficient blood - show alert and disable button
                                document.getElementById('approveNoBloodMessage').textContent = 
                                    `Cannot approve Request #${requestId}. Required: ${requiredUnits} units of ${bloodTypeNeeded}, but only ${availableUnits} unit(s) available.`;
                                document.getElementById('approveNoBloodAlert').style.display = 'block';
                                document.getElementById('confirmApproveBtn').disabled = true;
                                if (approveBufferUsageAlert && approveBufferUsageMessage) {
                                    approveBufferUsageAlert.style.display = 'none';
                                    approveBufferUsageMessage.textContent = '';
                                }
                            }
                        } else {
                            pendingApprovalUnits = [];
                            pendingApprovalRequestId = null;
                            // Error fetching blood units
                            document.getElementById('approveNoBloodMessage').textContent = 
                                'Unable to check blood availability. Please try again.';
                            document.getElementById('approveNoBloodAlert').style.display = 'block';
                            document.getElementById('confirmApproveBtn').disabled = true;
                        }
                    })
                    .catch(error => {
                        pendingApprovalUnits = [];
                        pendingApprovalRequestId = null;
                        console.error('Error:', error);
                        // Hide loading
                        document.getElementById('approveLoadingState').style.display = 'none';
                        document.getElementById('approveNoBloodMessage').textContent = 
                            'Error checking blood availability: ' + (error.message || 'Unknown error');
                        document.getElementById('approveNoBloodAlert').style.display = 'block';
                        document.getElementById('confirmApproveBtn').disabled = true;
                    });
            }, 300);
        });

        // Confirm Approve button logic
        const confirmApproveBtn = document.getElementById('confirmApproveBtn');
        confirmApproveBtn.addEventListener('click', function() {
            const requestId = parseInt(document.getElementById('modalRequestId').value, 10);
            const button = this;
            const originalText = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Reserving...';

            reserveUnitsForRequest(requestId)
                .then(() => {
                    submitApprovalForm(requestId);
                })
                .catch(error => {
                    console.error('Reserve error:', error);
                    if (window.adminModal && window.adminModal.alert) {
                        window.adminModal.alert(error.message || 'Unable to reserve blood units. Please try again.');
                    } else {
                        console.error('Admin modal not available');
                    }
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
        });

        function submitApprovalForm(requestId) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'request_id';
            input.value = requestId;
            form.appendChild(input);
            var accept = document.createElement('input');
            accept.type = 'hidden';
            accept.name = 'accept_request';
            accept.value = '1';
            form.appendChild(accept);
            document.body.appendChild(form);
            form.submit();
        }

        function reserveUnitsForRequest(requestId) {
            if (!requestId) {
                return Promise.reject(new Error('Invalid request identifier.'));
            }

            const ensureUnitsPromise = (pendingApprovalRequestId === requestId && pendingApprovalUnits.length)
                ? Promise.resolve(pendingApprovalUnits)
                : refreshApprovalUnits(requestId);

            return ensureUnitsPromise.then(units => {
                const unitIds = (units || []).filter(id => !!id);
                if (!unitIds.length) {
                    throw new Error('No available blood units to reserve for this request.');
                }

                return fetch('../api/mark-blood-units-check.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        unit_ids: unitIds,
                        is_check: true
                    })
                })
                    .then(res => {
                        if (!res.ok) {
                            throw new Error('Reservation request failed.');
                        }
                        return res.json();
                    })
                    .then(body => {
                        if (!body?.success) {
                            throw new Error(body?.error || 'Unable to reserve blood units.');
                        }
                        pendingApprovalUnits = [];
                        pendingApprovalRequestId = null;
                        return body;
                    });
            });
        }

        function refreshApprovalUnits(requestId) {
            return fetchJson(`../api/get-blood-units-for-request.php?request_id=${encodeURIComponent(requestId)}&t=${Date.now()}`)
                .then(data => {
                    if (!data?.success) {
                        throw new Error(data?.error || 'Unable to reload approval data.');
                    }
                    pendingApprovalUnits = (data.blood_units || [])
                        .map(unit => unit.unit_id)
                        .filter(id => !!id);
                    pendingApprovalRequestId = requestId;
                    return pendingApprovalUnits;
                });
        }

        // Helper: safe JSON fetch with graceful fallback to text for debugging
        async function fetchJson(url, options) {
            const res = await fetch(url, options);
            const ct = res.headers.get('content-type') || '';
            if (!res.ok) {
                const errText = await res.text().catch(() => '');
                throw new Error(`HTTP ${res.status} ${res.statusText}: ${errText}`);
            }
            if (ct.includes('application/json')) {
                return res.json();
            }
            // Try to parse JSON anyway; otherwise return detailed error
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Expected JSON but received:\n${text.substring(0, 500)}`);
            }
        }

        // Hand Over button logic
        document.getElementById('handOverButton').addEventListener('click', function() {
            // Get request ID
            var requestId = document.getElementById('modalRequestId').value;
            
            // Show loading state
            var handoverBtn = this;
            var originalText = handoverBtn.innerHTML;
            handoverBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
            handoverBtn.disabled = true;
            
            // Fetch blood units for this request
            fetchJson(`../api/get-blood-units-for-request.php?request_id=${encodeURIComponent(requestId)}&t=${Date.now()}`)
                .then(data => {
                    if (data.success) {
                        // Populate handover modal
                        populateHandoverModal(data);
                        
                        // Hide the main modal and show handover confirmation
                        requestDetailsModal.hide();
                        setTimeout(() => {
                            handoverConfirmModal.show();
                        }, 300);
                    } else {
                        if (window.adminModal && window.adminModal.alert) {
                            window.adminModal.alert('Error: ' + (data.error || 'Failed to fetch blood units'));
                        } else {
                            console.error('Admin modal not available');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (window.adminModal && window.adminModal.alert) {
                        window.adminModal.alert('Error fetching blood units:\n' + (error && error.message ? error.message : String(error)));
                    } else {
                        console.error('Admin modal not available');
                    }
                })
                .finally(() => {
                    // Reset button state
                    handoverBtn.innerHTML = originalText;
                    handoverBtn.disabled = false;
                });
        });

        // Confirm Handover button logic
        document.getElementById('confirmHandoverBtn').addEventListener('click', function() {
            // Get the request_id from the hidden field
            var requestId = document.getElementById('modalRequestId').value;
            
            // Show loading state
            var confirmBtn = this;
            var originalText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            confirmBtn.disabled = true;
            
            // First, update the blood units to assign them to this request
            fetchJson(`../api/get-blood-units-for-request.php?request_id=${encodeURIComponent(requestId)}&t=${Date.now()}`)
                .then(async (data) => {
                    if (!data.success || !data.blood_units || data.blood_units.length === 0) {
                        throw new Error('No blood units available for handover');
                    }

                    // Update each blood unit (mark as handed over) and ensure success
                    const updatePromises = data.blood_units.map((unit) =>
                        fetchJson('../api/update-blood-unit-request.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                unit_id: unit.unit_id,
                                request_id: requestId
                            })
                        })
                    );

                    const results = await Promise.all(updatePromises);
                    const failed = results.find(r => !r || r.success !== true);
                    if (failed) {
                        throw new Error('Failed to update one or more blood units');
                    }
                    return true;
                })
                .then(() => {
                    // Now submit the handover form
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'request_id';
                input.value = requestId;
                form.appendChild(input);
                var handover = document.createElement('input');
                handover.type = 'hidden';
                handover.name = 'handover_request';
                handover.value = '1';
                form.appendChild(handover);
                document.body.appendChild(form);
                form.submit();
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (window.adminModal && window.adminModal.alert) {
                        window.adminModal.alert('Error processing handover:\n' + (error && error.message ? error.message : String(error)));
                    } else {
                        console.error('Admin modal not available');
                    }
                    // Reset button state
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                });
        });

    });
    </script>
    <script src="../../assets/js/admin-donor-registration-modal.js"></script>
    <script src="../../assets/js/admin-screening-form-modal.js"></script>
    <script src="../../assets/js/admin-hospital-request-priority-handler.js"></script>
</body>
</html>