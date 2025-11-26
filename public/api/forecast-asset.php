<?php
declare(strict_types=1);

/**
 * Secure asset proxy for forecast dashboard.
 * Serves generated charts and interactive HTML files that live outside /public.
 */

session_start();

header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');

$root = realpath(__DIR__ . '/../../assets/reports-model');
$chartsDir = $root ? $root . DIRECTORY_SEPARATOR . 'charts' : null;

$assets = [
    'supply' => [
        'path' => $chartsDir ? $chartsDir . DIRECTORY_SEPARATOR . 'supply_forecast.png' : null,
        'mime' => 'image/png',
        'label' => 'Forecasted Blood Supply chart',
    ],
    'demand' => [
        'path' => $chartsDir ? $chartsDir . DIRECTORY_SEPARATOR . 'demand_forecast.png' : null,
        'mime' => 'image/png',
        'label' => 'Forecasted Hospital Demand chart',
    ],
    'comparison' => [
        'path' => $chartsDir ? $chartsDir . DIRECTORY_SEPARATOR . 'supply_vs_demand.png' : null,
        'mime' => 'image/png',
        'label' => 'Supply vs Demand chart',
    ],
    'projected_stock' => [
        'path' => $chartsDir ? $chartsDir . DIRECTORY_SEPARATOR . 'projected_stock.png' : null,
        'mime' => 'image/png',
        'label' => 'Projected Stock chart',
    ],
    'interactive_supply' => [
        'path' => $root ? $root . DIRECTORY_SEPARATOR . 'interactive_supply.html' : null,
        'mime' => 'text/html; charset=UTF-8',
        'label' => 'Interactive Supply Trend',
    ],
    'interactive_demand' => [
        'path' => $root ? $root . DIRECTORY_SEPARATOR . 'interactive_demand.html' : null,
        'mime' => 'text/html; charset=UTF-8',
        'label' => 'Interactive Demand Trend',
    ],
    'interactive_combined' => [
        'path' => $root ? $root . DIRECTORY_SEPARATOR . 'interactive_combined.html' : null,
        'mime' => 'text/html; charset=UTF-8',
        'label' => 'Interactive Supply vs Demand view',
    ],
    'projected_stock_html' => [
        'path' => $root ? $root . DIRECTORY_SEPARATOR . 'projected_stock.html' : null,
        'mime' => 'text/html; charset=UTF-8',
        'label' => 'Projected Stock Status view',
    ],
];

$key = $_GET['asset'] ?? '';

if (!isset($assets[$key])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Invalid asset requested.";
    exit;
}

$asset = $assets[$key];
$path = $asset['path'];

if (!$path || !file_exists($path)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;padding:16px;">';
    echo '<h3 style="color:#941022;margin-top:0;">' . htmlspecialchars($asset['label']) . '</h3>';
    echo '<p style="margin-bottom:0;">This asset has not been generated yet. Please click "Refresh Data" in the dashboard to regenerate forecasts.</p>';
    echo '</body></html>';
    exit;
}

$mime = $asset['mime'];
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));

$stream = fopen($path, 'rb');
if ($stream !== false) {
    fpassthru($stream);
    fclose($stream);
} else {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to read asset.';
}

