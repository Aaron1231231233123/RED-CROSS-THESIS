<?php
// AJAX endpoint to load GIS data for dashboard
// This reduces initial page load time by deferring GIS processing

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include_once '../../assets/conn/db_conn.php';

// Try to get optimized GIS data from PostGIS endpoint
try {
    $apiPath = 'http://' . $_SERVER['HTTP_HOST'] . '/RED-CROSS-THESIS/public/api/optimized-gis-data.php?t=' . time();
    $ctx = stream_context_create(['http' => ['timeout' => 3.0]]);
    $gisDataResponse = @file_get_contents($apiPath, false, $ctx);
    
    if ($gisDataResponse) {
        $gisData = json_decode($gisDataResponse, true);
        if ($gisData && !isset($gisData['error'])) {
            // Return the GIS data directly
            echo json_encode([
                'success' => true,
                'cityDonorCounts' => $gisData['cityDonorCounts'] ?? [],
                'heatmapData' => $gisData['heatmapData'] ?? [],
                'totalDonorCount' => $gisData['totalDonorCount'] ?? 0,
                'postgis_available' => $gisData['postgis_available'] ?? false
            ]);
            exit();
        }
    }
} catch (Exception $e) {
    error_log("AJAX GIS Load Error: " . $e->getMessage());
}

// Fallback: return empty data
echo json_encode([
    'success' => true,
    'cityDonorCounts' => [],
    'heatmapData' => [],
    'totalDonorCount' => 0,
    'postgis_available' => false
]);

