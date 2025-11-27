<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require_once '../../../assets/php_func/admin_hospital_request_priority_handler.php';
require_once '../../../assets/php_func/search_func/filter_search_hospital_blood_requests/filter_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
while (ob_get_level()) { ob_end_clean(); }

/**
 * Ensure fatal errors still return JSON so the caller isn't left with an empty body.
 */
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log('Admin hospital search fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Search failed: ' . $error['message'],
            'line' => $error['line']
        ]);
    }
});

// Ensure only authenticated admins can use this endpoint
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || intval($_SESSION['role_id']) !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = [];
}

$query = isset($payload['q']) ? trim($payload['q']) : '';
$category = isset($payload['category']) ? trim(strtolower($payload['category'])) : 'all';
$statusFilters = (isset($payload['status']) && is_array($payload['status'])) ? $payload['status'] : [];
$limit = isset($payload['limit']) ? max(10, intval($payload['limit'])) : 200;

try {
    $requests = fetch_all_hospital_requests($statusFilters, $limit);
    if (!empty($query)) {
        $requests = array_values(array_filter($requests, function ($request) use ($query, $category) {
            return admin_request_matches_query($request, $query, $category);
        }));
    }

    $handedOverMap = build_handed_over_name_map($requests);

    ob_start();
    if (empty($requests)) {
        echo '<tr><td colspan="8" class="text-center text-muted">No matching requests found.</td></tr>';
    } else {
        foreach ($requests as $index => $request) {
            render_admin_request_row($request, $index + 1, $handedOverMap);
        }
    }
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'count' => count($requests),
        'html' => $html
    ]);
    exit;
} catch (Exception $e) {
    error_log('Admin hospital search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Search failed: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Fetch all blood requests with optional status filters.
 */
function fetch_all_hospital_requests(array $statuses, int $limit) {
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];

    $select = 'request_id,request_reference,hospital_admitted,patient_blood_type,rh_factor,units_requested,requested_on,status,patient_name,patient_age,patient_gender,is_asap,patient_diagnosis,physician_name,when_needed,handed_over_by,handed_over_date,approved_by,approved_date,declined_by,last_updated,decline_reason';
    $url = SUPABASE_URL . '/rest/v1/blood_requests?select=' . urlencode($select) . '&order=requested_on.desc&limit=' . $limit;

    if (!empty($statuses)) {
        $dbStatuses = [];
        foreach ($statuses as $status) {
            $statusLower = strtolower(trim($status));
            if ($statusLower === 'approved') {
                $dbStatuses[] = 'Approved';
                $dbStatuses[] = 'Printed';
                $dbStatuses[] = 'Handed_over';
            } elseif ($statusLower !== 'all' && $statusLower !== 'all status') {
                $dbStatuses[] = ucfirst($statusLower);
            }
        }
        if (!empty($dbStatuses)) {
            $dbStatuses = array_unique($dbStatuses);
            $statusFilter = 'or=(' . implode(',', array_map(function ($s) {
                return 'status.eq.' . $s;
            }, $dbStatuses)) . ')';
            $url .= '&' . $statusFilter;
        }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Supabase error: ' . $error);
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new Exception('Invalid Supabase response');
    }

    return $data;
}

/**
 * Determine if a request matches the provided search parameters.
 */
function admin_request_matches_query(array $request, string $query, string $category) {
    $queryNormalized = fbr_normalize_text($query);
    $queryCollapsed = fbr_collapse_text($queryNormalized);
    if ($queryNormalized === '' && $queryCollapsed === '') {
        return true;
    }

    $fields = [];
    switch ($category) {
        case 'hospital':
            $fields[] = fbr_normalize_text($request['hospital_admitted'] ?? '');
            break;
        case 'blood_type':
            $fields[] = fbr_normalize_text($request['patient_blood_type'] ?? '');
            $fields[] = fbr_normalize_text($request['rh_factor'] ?? '');
            break;
        case 'date':
            $requestedOn = isset($request['requested_on']) ? date('Y-m-d', strtotime($request['requested_on'])) : '';
            $fields[] = fbr_normalize_text($requestedOn);
            break;
        case 'urgent':
            $priority = !empty($request['is_asap']) ? 'urgent' : 'routine';
            $fields[] = fbr_normalize_text($priority);
            break;
        default:
            $fields = [
                fbr_normalize_text($request['request_id'] ?? ''),
                fbr_normalize_text($request['request_reference'] ?? ''),
                fbr_normalize_text($request['hospital_admitted'] ?? ''),
                fbr_normalize_text($request['patient_blood_type'] ?? ''),
                fbr_normalize_text($request['patient_diagnosis'] ?? ''),
                fbr_normalize_text($request['physician_name'] ?? ''),
            ];
            break;
    }

    foreach ($fields as $field) {
        if ($field !== '' && fbr_variant_matches_query($field, $queryNormalized, $queryCollapsed)) {
            return true;
        }
    }

    // Allow name-based searches regardless of category
    $nameVariants = fbr_build_patient_name_variants($request['patient_name'] ?? '');
    foreach ($nameVariants as $variant) {
        if (fbr_variant_matches_query($variant, $queryNormalized, $queryCollapsed)) {
            return true;
        }
    }

    return false;
}

/**
 * Build handed over name lookups to display who completed the handover.
 */
function build_handed_over_name_map(array $requests) {
    $ids = [];
    foreach ($requests as $req) {
        if (!empty($req['handed_over_by'])) {
            $ids[] = intval($req['handed_over_by']);
        }
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if (empty($ids)) {
        return [];
    }

    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    $idList = '(' . implode(',', $ids) . ')';
    $url = SUPABASE_URL . '/rest/v1/users?select=user_id,first_name,surname&user_id=in.' . $idList;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('Failed to fetch handed-over names: ' . $error);
        return [];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [];
    }

    $map = [];
    foreach ($data as $user) {
        $first = trim($user['first_name'] ?? '');
        $last = trim($user['surname'] ?? '');
        $full = trim($first . ' ' . $last);
        if (!empty($user['user_id']) && $full !== '') {
            $map[$user['user_id']] = $full;
        }
    }

    return $map;
}

/**
 * Render a single admin request row.
 */
function render_admin_request_row(array $request, int $rowNum, array $handedOverMap) {
    $requestId = htmlspecialchars($request['request_id'] ?? '');
    $requestRef = $request['request_reference'] ?? '';
    $displayRef = $requestId;
    if (!empty($requestRef)) {
        $displayRef = htmlspecialchars(substr($requestRef, 4, 14));
    }

    $hospital = htmlspecialchars($request['hospital_admitted'] ?? 'Hospital');
    $bloodType = htmlspecialchars($request['patient_blood_type'] ?? '');
    $rhFactor = htmlspecialchars($request['rh_factor'] ?? '');
    $units = intval($request['units_requested'] ?? 0);
    $requestedOn = !empty($request['requested_on']) ? date('Y-m-d', strtotime($request['requested_on'])) : '-';
    $status = strtolower($request['status'] ?? 'pending');
    $priorityDisplay = !empty($request['is_asap']) ? 'Urgent' : 'Routine';
    $handedOverBy = $request['handed_over_by'] ?? '';
    $handedOverName = $handedOverBy && isset($handedOverMap[$handedOverBy]) ? $handedOverMap[$handedOverBy] : '';

    $priorityData = calculateHospitalRequestPriority(
        !empty($request['is_asap']),
        $request['when_needed'] ?? null,
        $request['status'] ?? 'pending'
    );

    $statusBadge = '<span class="badge bg-secondary">N/A</span>';
    if ($status === 'pending' || $status === 'rescheduled') {
        $statusBadge = '<span class="badge bg-warning text-dark">' . ucfirst($status) . '</span>';
    } elseif ($status === 'approved' || $status === 'printed') {
        $statusBadge = '<span class="badge bg-primary">Approved</span>';
    } elseif ($status === 'completed') {
        $statusBadge = '<span class="badge bg-success">Completed</span>';
    } elseif ($status === 'declined') {
        $statusBadge = '<span class="badge bg-danger">Declined</span>';
    }

    $buttonClass = 'view-btn';
    $buttonIcon = '<i class="fas fa-eye"></i>';
    if (in_array($request['status'] ?? '', ['Approved', 'Accepted', 'Confirmed'], true)) {
        $buttonClass = 'print-btn';
        $buttonIcon = '<i class="fas fa-print"></i>';
    } elseif (($request['status'] ?? '') === 'Handed_over') {
        $buttonClass = 'handover-btn';
        $buttonIcon = '<i class="fas fa-check"></i>';
    }

    ?>
    <tr
        data-is-asap="<?php echo !empty($request['is_asap']) ? 'true' : 'false'; ?>"
        data-when-needed="<?php echo htmlspecialchars($request['when_needed'] ?? ''); ?>"
        data-status="<?php echo htmlspecialchars($request['status'] ?? 'Pending'); ?>"
        data-priority-class="<?php echo htmlspecialchars($priorityData['urgency_class']); ?>"
        data-is-urgent="<?php echo $priorityData['is_urgent'] ? 'true' : 'false'; ?>"
        data-is-critical="<?php echo $priorityData['is_critical'] ? 'true' : 'false'; ?>"
        data-time-remaining="<?php echo htmlspecialchars($priorityData['time_remaining']); ?>"
        data-priority-level="<?php echo $priorityData['priority_level']; ?>"
        data-request-id="<?php echo $requestId; ?>"
        data-hospital-name="<?php echo $hospital; ?>"
    >
        <td><?php echo $rowNum; ?></td>
        <td><?php echo $displayRef; ?></td>
        <td><?php echo $hospital; ?></td>
        <td><?php echo $bloodType . ($rhFactor === 'Positive' ? '+' : '-'); ?></td>
        <td><?php echo htmlspecialchars($units); ?></td>
        <td><?php echo htmlspecialchars($requestedOn); ?></td>
        <td><?php echo $statusBadge; ?></td>
        <td>
            <button
                class="btn btn-info btn-sm <?php echo $buttonClass; ?>"
                data-request-id="<?php echo $requestId; ?>"
                data-patient-name="<?php echo htmlspecialchars($request['patient_name'] ?? ''); ?>"
                data-patient-age="<?php echo htmlspecialchars($request['patient_age'] ?? ''); ?>"
                data-patient-gender="<?php echo htmlspecialchars($request['patient_gender'] ?? ''); ?>"
                data-patient-diagnosis="<?php echo htmlspecialchars($request['patient_diagnosis'] ?? ''); ?>"
                data-blood-type="<?php echo $bloodType; ?>"
                data-rh-factor="<?php echo $rhFactor; ?>"
                data-component="Whole Blood"
                data-units="<?php echo htmlspecialchars($units); ?>"
                data-when-needed="<?php echo htmlspecialchars($request['when_needed'] ?? ''); ?>"
                data-is-asap="<?php echo !empty($request['is_asap']) ? 'true' : 'false'; ?>"
                data-approved-by="<?php echo htmlspecialchars($request['approved_by'] ?? ''); ?>"
                data-approved-date="<?php echo htmlspecialchars($request['approved_date'] ?? ''); ?>"
                data-declined-by="<?php echo htmlspecialchars($request['declined_by'] ?? ''); ?>"
                data-declined-date="<?php echo htmlspecialchars($request['last_updated'] ?? ''); ?>"
                data-decline-reason="<?php echo htmlspecialchars($request['decline_reason'] ?? ''); ?>"
                data-handed-over-by="<?php echo htmlspecialchars($handedOverName); ?>"
                data-handed-over-date="<?php echo htmlspecialchars($request['handed_over_date'] ?? ''); ?>"
                data-physician-name="<?php echo htmlspecialchars($request['physician_name'] ?? ''); ?>"
                data-status="<?php echo htmlspecialchars($request['status'] ?? 'Pending'); ?>"
            >
                <?php echo $buttonIcon; ?>
            </button>
        </td>
    </tr>
    <?php
}

