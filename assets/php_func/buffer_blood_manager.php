<?php
/**
 * Buffer Blood Manager
 *
 * Centralized helper for determining which blood units should be treated as
 * buffer/reserve stock plus shared utilities for logging and annotation.
 */

require_once __DIR__ . '/../conn/db_conn.php';

if (!function_exists('supabaseRequest')) {
    require_once __DIR__ . '/../../public/Dashboards/module/optimized_functions.php';
}

if (!defined('BUFFER_BLOOD_DEFAULT_LIMIT')) {
    define('BUFFER_BLOOD_DEFAULT_LIMIT', 10);
}

/**
 * Build buffer context either from an existing inventory array or by querying Supabase.
 *
 * @param int   $limit
 * @param array|null $inventory Optional pre-fetched inventory dataset to avoid extra queries.
 * @return array
 */
function getBufferBloodContext($limit = BUFFER_BLOOD_DEFAULT_LIMIT, array $inventory = null)
{
    $limit = max(1, (int) $limit);

    if ($inventory !== null) {
        $sourceUnits = $inventory;
    } else {
        $sourceUnits = bufferBloodFetchLatestUnits($limit * 3);
    }

    return bufferBloodBuildContext($sourceUnits, $limit);
}

/**
 * Tag inventory entries with buffer flag based on lookup map.
 *
 * @param array $inventory
 * @param array $bufferLookup
 * @return array
 */
function tagBufferBloodInInventory(array $inventory, array $bufferLookup)
{
    $byId = $bufferLookup['by_id'] ?? [];
    $bySerial = $bufferLookup['by_serial'] ?? [];

    foreach ($inventory as &$entry) {
        $unitId = isset($entry['unit_id']) ? (string) $entry['unit_id'] : null;
        $serial = null;
        if (isset($entry['serial_number'])) {
            $serial = (string) $entry['serial_number'];
        } elseif (isset($entry['unit_serial_number'])) {
            $serial = (string) $entry['unit_serial_number'];
        }

        $matched = false;
        if ($unitId && isset($byId[$unitId])) {
            $matched = true;
            $entry['buffer_source'] = $byId[$unitId];
        } elseif ($serial && isset($bySerial[$serial])) {
            $matched = true;
            $entry['buffer_source'] = $bySerial[$serial];
        }

        $entry['is_buffer'] = $matched;
    }

    return $inventory;
}

/**
 * Determine if a unit belongs to the buffer list using the lookup map.
 *
 * @param array $unit
 * @param array $bufferLookup
 * @return bool
 */
function isBufferUnitFromLookup(array $unit, array $bufferLookup)
{
    $byId = $bufferLookup['by_id'] ?? [];
    $bySerial = $bufferLookup['by_serial'] ?? [];

    $unitId = isset($unit['unit_id']) ? (string) $unit['unit_id'] : null;
    $serial = null;
    if (isset($unit['serial_number'])) {
        $serial = (string) $unit['serial_number'];
    } elseif (isset($unit['unit_serial_number'])) {
        $serial = (string) $unit['unit_serial_number'];
    }

    if ($unitId && isset($byId[$unitId])) {
        return true;
    }
    if ($serial && isset($bySerial[$serial])) {
        return true;
    }

    return false;
}

/**
 * Persist buffer usage activity for auditing.
 *
 * @param array       $units      Array of unit descriptors (unit_id, serial_number, blood_type).
 * @param int|null    $requestId
 * @param int|string|null $adminId
 * @param string      $phase      e.g., 'preview', 'handover'
 * @return void
 */
function logBufferUsageEvent(array $units, $requestId = null, $adminId = null, $phase = 'preview')
{
    if (empty($units)) {
        return;
    }

    $logPayload = [
        'timestamp' => gmdate('c'),
        'phase' => $phase,
        'request_id' => $requestId,
        'admin_id' => $adminId,
        'units' => array_values($units)
    ];

    $logPath = __DIR__ . '/../logs/buffer_blood_usage.log';
    @file_put_contents($logPath, json_encode($logPayload) . PHP_EOL, FILE_APPEND);
}

/**
 * Fetch recent blood bank units directly from Supabase.
 *
 * @param int $limit
 * @return array
 */
function bufferBloodFetchLatestUnits($limit)
{
    $endpoint = "blood_bank_units"
        . "?select=unit_id,unit_serial_number,blood_type,bag_type,bag_brand,collected_at,expires_at,status"
        . "&status=in.(Valid,valid)"
        . "&order=collected_at.desc"
        . "&limit=" . max(10, (int) $limit);

    $response = supabaseRequest($endpoint);
    if (isset($response['data']) && is_array($response['data'])) {
        return $response['data'];
    }

    return [];
}

/**
 * Construct buffer metadata from arbitrary unit dataset.
 *
 * @param array $units
 * @param int   $limit
 * @return array
 */
function bufferBloodBuildContext(array $units, $limit)
{
    $normalized = [];
    foreach ($units as $unit) {
        $normalizedUnit = bufferBloodNormalizeUnit($unit);
        if (!$normalizedUnit['unit_id'] && !$normalizedUnit['serial_number']) {
            continue;
        }
        $normalized[] = $normalizedUnit;
    }

    usort($normalized, function ($a, $b) {
        if ($a['collected_ts'] === $b['collected_ts']) {
            return $b['expires_ts'] <=> $a['expires_ts'];
        }
        return $b['collected_ts'] <=> $a['collected_ts'];
    });

    $bufferUnits = [];
    $byId = [];
    $bySerial = [];

    foreach ($normalized as $unit) {
        if (count($bufferUnits) >= $limit) {
            break;
        }

        $idKey = $unit['unit_id'] ?: ($unit['serial_number'] ?? null);
        if (!$idKey) {
            continue;
        }

        if ($unit['unit_id'] && isset($byId[$unit['unit_id']])) {
            continue;
        }
        if ($unit['serial_number'] && isset($bySerial[$unit['serial_number']])) {
            continue;
        }

        $unit['is_buffer'] = true;
        $bufferUnits[] = $unit;
        if ($unit['unit_id']) {
            $byId[$unit['unit_id']] = $unit;
        }
        if ($unit['serial_number']) {
            $bySerial[$unit['serial_number']] = $unit;
        }
    }

    $bufferTypes = [];
    foreach ($bufferUnits as $unit) {
        $type = $unit['blood_type'] ?: 'Unknown';
        $bufferTypes[$type] = ($bufferTypes[$type] ?? 0) + 1;
    }

    return [
        'limit' => $limit,
        'count' => count($bufferUnits),
        'buffer_units' => $bufferUnits,
        'buffer_lookup' => [
            'by_id' => $byId,
            'by_serial' => $bySerial
        ],
        'buffer_types' => $bufferTypes,
        'generated_at' => gmdate('c')
    ];
}

/**
 * Normalize varying unit payload shapes into a consistent structure for buffer logic.
 *
 * @param array $unit
 * @return array
 */
function bufferBloodNormalizeUnit(array $unit)
{
    $unitId = null;
    if (isset($unit['unit_id'])) {
        $unitId = (string) $unit['unit_id'];
    } elseif (isset($unit['blood_collection_id'])) {
        $unitId = (string) $unit['blood_collection_id'];
    }

    if (!empty($unitId)) {
        $unitId = trim($unitId);
    }

    $serial = null;
    if (isset($unit['unit_serial_number'])) {
        $serial = (string) $unit['unit_serial_number'];
    } elseif (isset($unit['serial_number'])) {
        $serial = (string) $unit['serial_number'];
    }
    if ($serial !== null) {
        $serial = trim($serial);
    }

    $collectedRaw = $unit['collected_at']
        ?? $unit['collection_date']
        ?? $unit['collection_start_time']
        ?? $unit['created_at']
        ?? null;
    $collectedTs = bufferBloodParseTimestamp($collectedRaw);

    $expiresRaw = $unit['expires_at']
        ?? $unit['expiration_date']
        ?? $unit['expires_on']
        ?? null;
    $expiresTs = bufferBloodParseTimestamp($expiresRaw);

    return [
        'unit_id' => $unitId,
        'serial_number' => $serial,
        'blood_type' => $unit['blood_type'] ?? ($unit['bloodType'] ?? 'Unknown'),
        'bag_type' => $unit['bag_type'] ?? ($unit['bagType'] ?? 'Standard'),
        'bag_brand' => $unit['bag_brand'] ?? ($unit['bagBrand'] ?? 'N/A'),
        'status' => $unit['status'] ?? 'Valid',
        'collected_at' => $collectedTs ? date('Y-m-d H:i:s', $collectedTs) : null,
        'collected_ts' => $collectedTs,
        'expires_at' => $expiresTs ? date('Y-m-d H:i:s', $expiresTs) : null,
        'expires_ts' => $expiresTs,
        'raw' => $unit
    ];
}

/**
 * Parse various timestamp formats into a unix timestamp.
 *
 * @param string|null $value
 * @return int
 */
function bufferBloodParseTimestamp($value)
{
    if (empty($value)) {
        return 0;
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return $ts;
    }

    // Attempt to parse numeric date strings (e.g., 20231123)
    if (preg_match('/^\d{8}$/', $value)) {
        $ts = strtotime(substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2));
        if ($ts !== false) {
            return $ts;
        }
    }

    return 0;
}


