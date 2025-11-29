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

$tableMap = [
    'medical_history' => 'medical_history',
    'physical_examination' => 'physical_examination',
    'blood_collection' => 'blood_collection',
    'blood_requests' => 'blood_requests'
];

$normalizedScopes = [];
foreach ($scopes as $scope) {
    $key = strtolower(trim($scope));
    if (isset($tableMap[$key])) {
        $normalizedScopes[$key] = $tableMap[$key];
    }
}

$records = [];
if (isset($input['records']) && is_array($input['records'])) {
    foreach ($input['records'] as $record) {
        if (!is_array($record)) {
            continue;
        }
        $scopeKey = strtolower(trim($record['scope'] ?? ''));
        if (empty($scopeKey) || !isset($tableMap[$scopeKey])) {
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
                'scope' => $scopeKey,
                'table' => $tableMap[$scopeKey],
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

function executeSupabaseRequest(string $url, string $method = 'GET', array $payload = null)
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

function buildFilterQuery(array $filters): string
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

function updateAccessValue(string $table, int $value, array $filters = [])
{
    // CRITICAL: Never allow full-table updates - require filters to prevent overriding admin locks
    if (empty($filters)) {
        error_log("[AccessLockManagerInterviewer] ERROR: Refusing to update access without filters for table {$table}");
        throw new RuntimeException('Refusing to update access without filters. Records with donor_id must be provided.');
    }

    $filter = buildFilterQuery($filters);
    if (empty($filter)) {
        error_log("[AccessLockManagerInterviewer] ERROR: Failed to build filter query for table {$table}");
        throw new RuntimeException('Failed to build filter query. Records with donor_id must be provided.');
    }

    // Before updating, check if any records already have access=2 (admin lock)
    // This prevents interviewer from overriding admin locks
    $checkUrl = SUPABASE_URL . "/rest/v1/{$table}{$filter}";
    // Add select parameter correctly (use & if filter already has ?, otherwise use ?)
    $checkUrl .= (strpos($filter, '?') !== false ? '&' : '?') . 'select=access,is_admin&limit=1';
    [$checkCode, $checkResponse] = executeSupabaseRequest($checkUrl);
    if ($checkCode === 200) {
        $checkData = json_decode($checkResponse, true);
        if (is_array($checkData) && !empty($checkData)) {
            $existingAccess = isset($checkData[0]['access']) ? (int)$checkData[0]['access'] : 0;
            $isAdmin = isset($checkData[0]['is_admin']) ? 
                ($checkData[0]['is_admin'] === true || $checkData[0]['is_admin'] === 'true' || $checkData[0]['is_admin'] === 'True') : 
                false;
            
            // If admin has locked this record (access=2 or is_admin=True), prevent interviewer from claiming
            if ($existingAccess === 2 || $isAdmin) {
                error_log("[AccessLockManagerInterviewer] BLOCKED: Cannot claim access - record is locked by admin (access={$existingAccess}, is_admin=" . ($isAdmin ? 'true' : 'false') . ")");
                throw new RuntimeException('Cannot claim access: This record is currently locked by an admin account.');
            }
        }
    }

    $url = SUPABASE_URL . "/rest/v1/{$table}{$filter}";
    [$code, $response] = executeSupabaseRequest($url, 'PATCH', ['access' => $value]);

    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("Failed to update {$table} access. HTTP {$code}. Response: {$response}");
    }
}

function fetchAccessValue(string $table, array $filters = [])
{
    $filter = buildFilterQuery($filters);
    $url = SUPABASE_URL . "/rest/v1/{$table}" . ($filter ?: '?select=access&limit=1');
    if (!$filter) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'select=access&limit=1';
    } elseif (strpos($url, 'select=') === false) {
        $url .= '&select=access&limit=1';
    }
    [$code, $response] = executeSupabaseRequest($url);
    if ($code !== 200) {
        throw new RuntimeException("Failed to fetch {$table} access. HTTP {$code}");
    }
    $data = json_decode($response, true);
    if (is_array($data) && isset($data[0]['access'])) {
        return (int)$data[0]['access'];
    }
    return 0;
}

function resolveRecordFilters(array $record): array
{
    $filters = [];
    if (isset($record['filters']) && is_array($record['filters'])) {
        foreach ($record['filters'] as $column => $value) {
            $filters[$column] = $value;
        }
    }
    if (isset($record['donor_id'])) {
        $filters['donor_id'] = (int)$record['donor_id'];
    }
    return $filters;
}

$accessValue = isset($input['access']) ? (int)$input['access'] : null;
$roleId = $_SESSION['role_id'] ?? null;

try {
    switch ($action) {
        case 'claim':
            if (!in_array($accessValue, [0, 1, 2], true)) {
                throw new InvalidArgumentException('Invalid access value');
            }
            if ($accessValue === 2 && $roleId !== 1) {
                throw new RuntimeException('Only admin accounts can claim admin access');
            }
            // CRITICAL: Require records for all claim operations to prevent full-table updates
            if (empty($records)) {
                error_log('[AccessLockManagerInterviewer] ERROR: Claim operation requires records with donor_id');
                throw new RuntimeException('Claim operation requires records with donor_id. Cannot perform full-table updates.');
            }
            foreach ($records as $record) {
                updateAccessValue($record['table'], $accessValue, resolveRecordFilters($record));
            }
            echo json_encode(['success' => true, 'message' => 'Access claimed', 'access' => $accessValue]);
            break;

        case 'release':
            // Require records for release operations to prevent full-table updates
            if (empty($records)) {
                error_log('[AccessLockManagerInterviewer] ERROR: Release operation requires records with donor_id');
                throw new RuntimeException('Release operation requires records with donor_id. Cannot perform full-table updates.');
            }
            foreach ($records as $record) {
                // Only release if we own the lock (access=1), don't release admin locks (access=2)
                $filters = resolveRecordFilters($record);
                $currentAccess = fetchAccessValue($record['table'], $filters);
                if ($currentAccess === 1) {
                    // Only release our own locks, not admin locks
                    updateAccessValue($record['table'], 0, $filters);
                } else {
                    error_log("[AccessLockManagerInterviewer] Skipping release - record has access={$currentAccess} (not our lock)");
                }
            }
            echo json_encode(['success' => true, 'message' => 'Access released']);
            break;

        case 'status':
        default:
            $states = [];
            if (!empty($records)) {
                foreach ($records as $record) {
                    $states[$record['scope']] = fetchAccessValue($record['table'], resolveRecordFilters($record));
                }
            } else {
                foreach ($normalizedScopes as $scope => $table) {
                    $states[$scope] = fetchAccessValue($table);
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


