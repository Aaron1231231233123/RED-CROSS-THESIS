<?php
/**
 * Forecast Reports API - Python Integration
 * This API endpoint calls the Python forecast calculator
 * and returns the same JSON format as the original PHP API
 * 
 * To use this instead of the PHP API, change the FORECAST_API_URL in:
 * assets/js/dashboard-inventory-system-reports-admin.js
 * from: '../api/forecast-reports-api.php'
 * to: '../api/forecast-reports-api-python.php'
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
// Prevent caching to ensure real-time data from database
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Path to Python script
$pythonScript = __DIR__ . '/../../assets/reports-model/dashboard_inventory_system_reports_admin.py';

// Detect Python executable (Windows-compatible)
$pythonExecutable = null;
// On Windows, prioritize 'py' launcher, then try others
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $pythonCommands = ['py', 'python', 'python3']; // Windows: 'py' is most reliable
} else {
    $pythonCommands = ['python3', 'python']; // Linux/Mac
}

foreach ($pythonCommands as $cmd) {
    // Check if command exists
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: use 'where' command
        $testCmd = "where $cmd 2>nul";
    } else {
        // Linux/Mac: use 'which' command
        $testCmd = "which $cmd 2>/dev/null";
    }
    
    $result = shell_exec($testCmd);
    if (!empty($result) && trim($result) !== '') {
        $pythonExecutable = $cmd;
        break;
    }
}

// If still not found, try direct execution test
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
    // Fallback: Use original PHP API if Python is not available
    error_log("Python not found. Falling back to PHP API. Tried commands: " . implode(', ', $pythonCommands));
    include_once __DIR__ . '/forecast-reports-api.php';
    exit;
}

try {
    // Check if Python script exists
    if (!file_exists($pythonScript)) {
        throw new Exception('Python script not found: ' . $pythonScript);
    }
    
    // Change to the script directory to ensure imports work
    $scriptDir = dirname($pythonScript);
    $originalDir = getcwd();
    chdir($scriptDir);
    
    // Execute Python script and capture output
    // Use full path to script to avoid PATH issues
    $scriptPath = realpath($pythonScript);
    if (!$scriptPath) {
        throw new Exception('Python script path not found: ' . $pythonScript);
    }
    
    // Get year parameter from request (default to current year if not provided)
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Set environment variable for Python script (works on both Windows and Unix)
    putenv('FORECAST_YEAR=' . $year);
    
    // Build command with proper escaping for Windows
    // IMPORTANT: Only capture stdout (JSON output), redirect stderr to error log
    // This prevents debug messages from breaking JSON parsing
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: capture stdout only, redirect stderr to error log
        // Use 2>NUL to discard stderr, or 2>>error.log to log it
        $command = escapeshellcmd($pythonExecutable) . ' ' . escapeshellarg($scriptPath) . ' 2>NUL';
    } else {
        // Linux/Mac: capture stdout only, redirect stderr to /dev/null or error log
        $command = escapeshellcmd($pythonExecutable) . ' ' . escapeshellarg($scriptPath) . ' 2>/dev/null';
    }
    
    error_log("Executing Python command: $command");
    $output = shell_exec($command);
    
    // If we want to capture stderr for debugging, we can use proc_open instead
    // But for now, stderr goes to error log (or is discarded)
    
    // Restore original directory
    chdir($originalDir);
    
    if ($output === null || trim($output) === '') {
        throw new Exception('Failed to execute Python script or no output received');
    }
    
    // Parse JSON output from Python
    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If JSON parsing fails, try to extract JSON from output
        // (Python might print other messages before/after JSON)
        if (preg_match('/\{.*\}/s', $output, $matches)) {
            $result = json_decode($matches[0], true);
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Python output: " . substr($output, 0, 1000));
            throw new Exception('Invalid JSON response from Python script: ' . json_last_error_msg() . '. Output preview: ' . substr($output, 0, 200));
        }
    }
    
    // Ensure the response matches the expected format
    if (!isset($result['success'])) {
        $result['success'] = true;
    }
    
    // Add timestamp
    $result['last_updated'] = date('Y-m-d H:i:s');
    $result['data_source'] = 'python_calculator';
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Forecast API Python Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate forecasts using Python',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
} catch (Error $e) {
    error_log("Forecast API Python Fatal Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error in Python forecast generation',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>

