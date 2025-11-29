<?php
/**
 * API endpoint to get Target Stock Levels per blood type for the current month.
 * Target Stock = Forecast Demand + Target Buffer (10 units)
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Path to Python script
$pythonScript = __DIR__ . '/../../assets/reports-model/dashboard_inventory_system_reports_admin.py';

// Detect Python executable (Windows-compatible)
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
    echo json_encode([
        'success' => false,
        'error' => 'Python not found',
        'target_stock_levels' => []
    ]);
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
        throw new Exception('Python script path not found: ' . $pythonScript);
    }
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = escapeshellcmd($pythonExecutable) . ' ' . escapeshellarg($scriptPath) . ' 2>NUL';
    } else {
        $command = escapeshellcmd($pythonExecutable) . ' ' . escapeshellarg($scriptPath) . ' 2>/dev/null';
    }
    
    $output = shell_exec($command);
    chdir($originalDir);
    
    if ($output === null || trim($output) === '') {
        error_log("Target Stock API - Python script returned empty output. Command: $command");
        throw new Exception('Failed to execute Python script or no output received');
    }
    
    // Log first 500 chars of output for debugging
    error_log("Target Stock API - Python output preview: " . substr($output, 0, 500));
    
    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (preg_match('/\{.*\}/s', $output, $matches)) {
            $result = json_decode($matches[0], true);
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Target Stock API - JSON decode error: " . json_last_error_msg() . " | Output: " . substr($output, 0, 1000));
            throw new Exception('Invalid JSON response from Python script: ' . json_last_error_msg());
        }
    }
    
    // Verify we got valid data
    if (!isset($result['success']) || !$result['success']) {
        error_log("Target Stock API - Python script returned success=false: " . ($result['error'] ?? 'Unknown error'));
        throw new Exception('Python script returned error: ' . ($result['error'] ?? 'Unknown error'));
    }
    
    // Extract target stock levels from projected_stock data
    // Fallback: Calculate from forecast_demand + buffer if projected_stock is empty
    $targetStockLevels = [];
    $projectedStock = $result['projected_stock'] ?? [];
    $forecastDemand = $result['forecast_demand'] ?? [];
    $targetBuffer = 10; // From config.py TARGET_BUFFER_UNITS
    
    // First try to get from projected_stock
    if (!empty($projectedStock)) {
        foreach ($projectedStock as $row) {
            $bloodType = $row['Blood_Type'] ?? '';
            $targetStock = intval($row['Target_Stock'] ?? 0);
            if ($bloodType) {
                $targetStockLevels[$bloodType] = $targetStock;
            }
        }
    }
    
    // Fallback: Calculate from forecast_demand + buffer if projected_stock didn't work
    if (empty($targetStockLevels) && !empty($forecastDemand)) {
        foreach ($forecastDemand as $row) {
            $bloodType = $row['Blood_Type'] ?? '';
            $forecastDemandValue = intval($row['Forecast_Demand'] ?? 0);
            if ($bloodType) {
                $targetStockLevels[$bloodType] = $forecastDemandValue + $targetBuffer;
            }
        }
    }
    
    // Ensure all blood types are present (default to 0 if not in forecast)
    $allBloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    foreach ($allBloodTypes as $bt) {
        if (!isset($targetStockLevels[$bt])) {
            $targetStockLevels[$bt] = 0;
        }
    }
    
    // Log for debugging
    error_log("Target Stock API - projected_stock count: " . count($projectedStock));
    error_log("Target Stock API - forecast_demand count: " . count($forecastDemand));
    error_log("Target Stock API - extracted levels: " . json_encode($targetStockLevels));
    
    // Check if we actually got any non-zero target stock levels
    $hasValidData = false;
    foreach ($targetStockLevels as $bt => $value) {
        if ($value > 0) {
            $hasValidData = true;
            break;
        }
    }
    
    if (!$hasValidData) {
        error_log("Target Stock API WARNING: All target stock levels are 0. This might indicate:");
        error_log("  - Python script failed silently");
        error_log("  - No forecast data available");
        error_log("  - Forecast returned all zeros");
        error_log("  - Projected stock data structure mismatch");
    }
    
    echo json_encode([
        'success' => true,
        'target_stock_levels' => $targetStockLevels,
        'generated_at' => date('Y-m-d H:i:s'),
        'debug' => [
            'projected_stock_count' => count($projectedStock),
            'forecast_demand_count' => count($forecastDemand),
            'has_valid_data' => $hasValidData
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Target Stock API Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'target_stock_levels' => []
    ], JSON_PRETTY_PRINT);
}
?>

