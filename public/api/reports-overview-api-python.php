<?php
/**
 * Reports Overview API - Python Integration
 *
 * Provides donor, inventory, and hospital overview metrics for the
 * admin Reports dashboard, powered by the Python aggregator:
 * assets/reports-model/reports_dashboard_overview.py
 */

// Start output buffering so any PHP warnings/notices don't corrupt JSON
ob_start();
// Only start a session if one is not already active to avoid warnings that break JSON
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
// Prevent browser caching; we handle caching on the server side
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$pythonScript = __DIR__ . '/../../assets/reports-model/reports_dashboard_overview.py';

// Simple filesystem cache so the heavy overview script is not run on every page load
$cacheDir = __DIR__ . '/../../assets/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
// Single cache file for the overview payload
$cacheFile = $cacheDir . '/reports_overview_dashboard.json';
// Default TTL: 5 minutes (300 seconds)
$cacheTtlSeconds = 300;
// Any truthy refresh param forces a fresh run
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] !== '0';

if (!$forceRefresh && is_file($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age >= 0 && $age <= $cacheTtlSeconds) {
        $cached = file_get_contents($cacheFile);
        if ($cached !== false && trim($cached) !== '') {
            ob_clean();
            echo $cached;
            exit;
        }
    }
}

// Detect Python executable (Windows and Linux/Mac compatible)
$pythonExecutable = null;
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $pythonCommands = ['py', 'python', 'python3'];
} else {
    $pythonCommands = ['python3', 'python'];
}

foreach ($pythonCommands as $cmd) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $testCmd = "where $cmd 2>nul";
    } else {
        $testCmd = "which $cmd 2>/dev/null";
    }

    $result = shell_exec($testCmd);
    if (!empty($result) && trim($result) !== '') {
        $pythonExecutable = $cmd;
        break;
    }
}

// Fallback: try running --version
if ($pythonExecutable === null) {
    foreach ($pythonCommands as $cmd) {
        $testOutput = @shell_exec("$cmd --version 2>&1");
        if (!empty($testOutput) && strpos($testOutput, 'Python') !== false) {
            $pythonExecutable = $cmd;
            break;
        }
    }
}

if ($pythonExecutable === null) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Python executable not found on server',
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    if (!file_exists($pythonScript)) {
        throw new Exception('Python script not found: ' . $pythonScript);
    }

    $scriptDir = dirname($pythonScript);
    $originalDir = getcwd();
    chdir($scriptDir);

    $scriptPath = realpath($pythonScript);
    if (!$scriptPath) {
        throw new Exception('Python script path could not be resolved: ' . $pythonScript);
    }

    // Build command (capture stdout JSON only, discard stderr)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = escapeshellcmd($pythonExecutable) . ' ' . escapeshellarg($scriptPath) . ' 2>NUL';
    } else {
        $command = escapeshellcmd($pythonExecutable) . ' ' . escapeshellarg($scriptPath) . ' 2>/dev/null';
    }

    $output = shell_exec($command);
    chdir($originalDir);

    if ($output === null || trim($output) === '') {
        throw new Exception('No output received from overview Python script');
    }

    $result = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (preg_match('/\{.*\}/s', $output, $matches)) {
            $result = json_decode($matches[0], true);
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(
                'Invalid JSON response from overview script: ' .
                json_last_error_msg() .
                '. Output preview: ' . substr($output, 0, 200)
            );
        }
    }

    if (!isset($result['success'])) {
        $result['success'] = true;
    }

    $result['last_updated'] = date('Y-m-d H:i:s');
    $result['data_source'] = 'python_reports_overview';
    $result['cache'] = [
        'from_cache' => false,
        'ttl_seconds' => $cacheTtlSeconds,
    ];

    $json = json_encode($result, JSON_PRETTY_PRINT);
    if ($json !== false) {
        @file_put_contents($cacheFile, $json);
    }

    ob_clean();
    echo $json;
} catch (Exception $e) {
    error_log("Reports Overview API Python Exception: " . $e->getMessage());
    ob_clean();
    $payload = array(
        'success' => false,
        'error' => 'Failed to generate reports overview using Python',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    );
    echo json_encode($payload);
}
