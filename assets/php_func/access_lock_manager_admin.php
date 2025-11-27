<?php
session_start();
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$action = isset($input['action']) ? strtolower(trim($input['action'])) : 'status';
$scopes = isset($input['scopes']) && is_array($input['scopes']) ? $input['scopes'] : [];

// Tables that participate in admin locking
$tableMap = [
    'medical_history'      => 'medical_history',
    'physical_examination' => 'physical_examination',
    'blood_collection'     => 'blood_collection',
    'blood_requests'       => 'blood_requests'
];

$normalizedScopes = [];
foreach ($scopes as $scope) {
    $key = strtolower(trim($scope));
    if (isset($tableMap[$key])) {
        $normalizedScopes[$key] = $tableMap[$key];
    }
}

// Normalize record-level filters
$records = [];
if (isset($input['records']) && is_array($input['records'])) {
    foreach ($input['records'] as $record) {
        if (!is_array($record)) {
            continue;
        }
        $scopeKey = strtolower(trim($record['scope'] ?? ''));
        if ($scopeKey === '' || !isset($tableMap[$scopeKey])) {
            continue;
        }
        $filters = [];
        if (isset($record['donor_id'])) {
            $filters['donor_id'] = (int)$record['donor_id'];
        }
        if (isset($record['filters']) && is_array($record['filters'])) {
            foreach ($record['filters'] as $col => $val) {
                $filters[$col] = $val;
            }
        }
        if (!empty($filters)) {
            $records[] = [
                'scope'   => $scopeKey,
                'table'   => $tableMap[$scopeKey],
                'filters' => $filters
            ];
        }
    }
}

if (empty($normalizedScopes) && empty($records)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No valid scopes provided']);
    exit;
}

function alm_admin_execute_request(string $url, string $method = 'GET', array $payload = null)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if (!is_null($payload)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Curl error: ' . $err);
    }

    return [$httpCode, $response];
}

function alm_admin_build_filter(array $filters): string
{
    if (empty($filters)) {
        return '';
    }
    $parts = [];
    foreach ($filters as $column => $value) {
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($safeColumn === '') {
            continue;
        }
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            $value = (string)$value;
        } else {
            $value = (string)$value;
        }
        $parts[] = $safeColumn . '=eq.' . rawurlencode($value);
    }
    return $parts ? '?' . implode('&', $parts) : '';
}

/**
 * Update access value (and, where applicable, is_admin flag) for a given table.
 */
function alm_admin_update_access(string $table, int $value, array $filters = [])
{
    $filter = alm_admin_build_filter($filters);
    if ($filter === '') {
        // Never allow a full-table update from the admin-specific endpoint
        error_log('[AccessLockManagerAdmin] ERROR: Refusing to update access without filters');
        throw new RuntimeException('Refusing to update access without filters on admin lock manager');
    }

    $url = SUPABASE_URL . "/rest/v1/{$table}{$filter}";

    $payload = ['access' => $value];

    // Only medical_history currently tracks is_admin; toggle it together with access
    if ($table === 'medical_history') {
        $payload['is_admin'] = $value === 2 ? 'True' : 'False';
    }

    error_log('[AccessLockManagerAdmin] Updating ' . $table . ' with URL: ' . $url);
    error_log('[AccessLockManagerAdmin] Payload: ' . json_encode($payload));

    [$code, $response] = alm_admin_execute_request($url, 'PATCH', $payload);

    if ($code < 200 || $code >= 300) {
        error_log('[AccessLockManagerAdmin] ERROR: Failed to update ' . $table . '. HTTP ' . $code . '. Response: ' . $response);
        throw new RuntimeException("Failed to update {$table} access. HTTP {$code}. Response: {$response}");
    }
    
    error_log('[AccessLockManagerAdmin] Successfully updated ' . $table . ' access to ' . $value);
}

function alm_admin_fetch_access(string $table, array $filters = [])
{
    $filter = alm_admin_build_filter($filters);
    $url = SUPABASE_URL . "/rest/v1/{$table}" . ($filter ?: '?select=access&limit=1');
    if (!$filter) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'select=access&limit=1';
    } elseif (strpos($url, 'select=') === false) {
        $url .= '&select=access&limit=1';
    }

    [$code, $response] = alm_admin_execute_request($url);
    if ($code !== 200) {
        throw new RuntimeException("Failed to fetch {$table} access. HTTP {$code}");
    }
    $data = json_decode($response, true);
    if (is_array($data) && isset($data[0]['access'])) {
        return (int)$data[0]['access'];
    }
    return 0;
}

/**
 * Resolve physical_exam_id for blood_collection when only donor_id is provided.
 */
function alm_admin_resolve_physical_exam_id(int $donor_id): ?string
{
    $url = SUPABASE_URL . "/rest/v1/physical_examination?select=physical_exam_id&donor_id=eq.{$donor_id}&order=created_at.desc&limit=1";
    [$code, $response] = alm_admin_execute_request($url);
    if ($code !== 200) {
        error_log("[AccessLockManagerAdmin] Failed to fetch physical_exam_id for donor {$donor_id}. HTTP {$code}");
        return null;
    }
    $data = json_decode($response, true);
    if (is_array($data) && !empty($data) && isset($data[0]['physical_exam_id'])) {
        return $data[0]['physical_exam_id'];
    }
    return null;
}

function alm_admin_resolve_filters(array $record): array
{
    $filters = [];
    if (isset($record['filters']) && is_array($record['filters'])) {
        foreach ($record['filters'] as $column => $value) {
            $filters[$column] = $value;
        }
    }
    if (isset($record['donor_id'])) {
        $donor_id = (int)$record['donor_id'];
        $scope = strtolower(trim($record['scope'] ?? ''));
        
        // Special handling: blood_collection doesn't have donor_id, it uses physical_exam_id
        if ($scope === 'blood_collection' && !isset($filters['physical_exam_id'])) {
            $physical_exam_id = alm_admin_resolve_physical_exam_id($donor_id);
            if ($physical_exam_id) {
                $filters['physical_exam_id'] = $physical_exam_id;
                error_log("[AccessLockManagerAdmin] Resolved physical_exam_id {$physical_exam_id} for donor {$donor_id} (blood_collection)");
            } else {
                error_log("[AccessLockManagerAdmin] WARNING: No physical_exam_id found for donor {$donor_id}, skipping blood_collection lock");
                // Return empty filters to skip this record
                return [];
            }
        } else {
            // For other tables, use donor_id directly
            $filters['donor_id'] = $donor_id;
        }
    }
    return $filters;
}

// Admin access value is always 2 on this endpoint
$accessValue = 2;
$roleId = $_SESSION['role_id'] ?? null;

try {
    if ($roleId !== 1) {
        throw new RuntimeException('Only admin accounts can use the admin access lock manager');
    }

    error_log('[AccessLockManagerAdmin] Request received: action=' . $action . ', records=' . json_encode($records) . ', scopes=' . json_encode($scopes));

    switch ($action) {
        case 'claim':
            if (empty($records)) {
                error_log('[AccessLockManagerAdmin] Claim failed: No records provided');
                throw new RuntimeException('Record-scoped claim required for admin lock manager. Records must be provided.');
            }
            error_log('[AccessLockManagerAdmin] Processing claim for ' . count($records) . ' record(s)');
            foreach ($records as $record) {
                $filters = alm_admin_resolve_filters($record);
                // Skip if filters are empty (e.g., no physical_exam_id found for blood_collection)
                if (empty($filters)) {
                    error_log('[AccessLockManagerAdmin] Skipping ' . $record['table'] . ' - no valid filters resolved');
                    continue;
                }
                error_log('[AccessLockManagerAdmin] Updating access for table=' . $record['table'] . ', filters=' . json_encode($filters));
                try {
                    alm_admin_update_access($record['table'], $accessValue, $filters);
                } catch (Exception $e) {
                    error_log('[AccessLockManagerAdmin] Failed to update ' . $record['table'] . ': ' . $e->getMessage());
                    // Continue with other records even if one fails
                }
            }
            error_log('[AccessLockManagerAdmin] Claim successful');
            echo json_encode(['success' => true, 'message' => 'Admin access claimed', 'access' => $accessValue]);
            break;

        case 'release':
            if (empty($records)) {
                throw new RuntimeException('Record-scoped release required for admin lock manager');
            }
            error_log('[AccessLockManagerAdmin] Processing release for ' . count($records) . ' record(s)');
            foreach ($records as $record) {
                $filters = alm_admin_resolve_filters($record);
                // Skip if filters are empty (e.g., no physical_exam_id found for blood_collection)
                if (empty($filters)) {
                    error_log('[AccessLockManagerAdmin] Skipping ' . $record['table'] . ' release - no valid filters resolved');
                    continue;
                }
                error_log('[AccessLockManagerAdmin] Releasing access for table=' . $record['table'] . ', filters=' . json_encode($filters));
                try {
                    alm_admin_update_access($record['table'], 0, $filters);
                } catch (Exception $e) {
                    error_log('[AccessLockManagerAdmin] Failed to release ' . $record['table'] . ': ' . $e->getMessage());
                    // Continue with other records even if one fails
                }
            }
            error_log('[AccessLockManagerAdmin] Release successful');
            echo json_encode(['success' => true, 'message' => 'Admin access released']);
            break;

        case 'status':
        default:
            $states = [];
            if (!empty($records)) {
                foreach ($records as $record) {
                    $filters = alm_admin_resolve_filters($record);
                    // Skip if filters are empty (e.g., no physical_exam_id found for blood_collection)
                    if (empty($filters)) {
                        $states[$record['scope']] = 0; // Default to 0 if no record found
                        continue;
                    }
                    try {
                        $states[$record['scope']] = alm_admin_fetch_access($record['table'], $filters);
                    } catch (Exception $e) {
                        error_log('[AccessLockManagerAdmin] Failed to fetch status for ' . $record['table'] . ': ' . $e->getMessage());
                        $states[$record['scope']] = 0; // Default to 0 on error
                    }
                }
            } else {
                foreach ($normalizedScopes as $scope => $table) {
                    try {
                        $states[$scope] = alm_admin_fetch_access($table);
                    } catch (Exception $e) {
                        error_log('[AccessLockManagerAdmin] Failed to fetch status for ' . $table . ': ' . $e->getMessage());
                        $states[$scope] = 0; // Default to 0 on error
                    }
                }
            }
            echo json_encode(['success' => true, 'states' => $states]);
            break;
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
}



