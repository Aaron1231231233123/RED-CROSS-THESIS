<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require_once '../Dashboards/module/optimized_functions.php';
require_once '../../assets/php_func/buffer_blood_manager.php';
require_once '../Dashboards/module/blood_inventory_data.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $snapshot = loadBloodInventorySnapshot();
    $bufferContext = $snapshot['bufferContext'] ?? [
        'buffer_units' => [],
        'buffer_types' => []
    ];

    echo json_encode([
        'success' => true,
        'buffer_units' => $bufferContext['buffer_units'],
        'buffer_types' => $bufferContext['buffer_types'],
        'count' => $bufferContext['count'] ?? count($bufferContext['buffer_units']),
        'generated_at' => $bufferContext['generated_at'] ?? gmdate('c')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

