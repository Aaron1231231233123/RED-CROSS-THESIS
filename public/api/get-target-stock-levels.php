<?php
/**
 * Target Stock Levels API
 * Executes the Python forecast script and extracts Target_Stock values
 * for each blood type from the projected_stock data.
 * 
 * PERFORMANCE: Uses file-based caching (1 hour TTL) to avoid running
 * Python script on every request. Forecasts don't change frequently.
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Cache configuration
$cacheTtl = 3600; // 1 hour cache (forecasts don't change frequently)
$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'target_stock_levels_cache.json';
$cacheMetaFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'target_stock_levels_cache_meta.json';

// Check cache first
$useCache = false;
if (file_exists($cacheFile) && file_exists($cacheMetaFile)) {
    $cacheMeta = @json_decode(file_get_contents($cacheMetaFile), true);
    $cacheTimestamp = isset($cacheMeta['timestamp']) ? (int)$cacheMeta['timestamp'] : 0;
    $cacheAge = time() - $cacheTimestamp;
    
    if ($cacheAge < $cacheTtl) {
        $useCache = true;
        $cachedData = @json_decode(file_get_contents($cacheFile), true);
        if (is_array($cachedData) && isset($cachedData['target_stock_levels'])) {
            // Set cache headers
            header('Cache-Control: public, max-age=' . ($cacheTtl - $cacheAge));
            header('X-Cache: HIT');
            header('X-Cache-Age: ' . $cacheAge);
            echo json_encode($cachedData, JSON_PRETTY_PRINT);
            exit;
        }
    }
}

// Cache miss or expired - need to regenerate
header('Cache-Control: no-cache');
header('X-Cache: MISS');

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
    error_log("Target Stock API: Python not found. Tried commands: " . implode(', ', $pythonCommands));
    echo json_encode([
        'success' => false,
        'error' => 'Python not found or not executable. Check server configuration.',
        'target_stock_levels' => []
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
        throw new Exception('Python script path not found: ' . $pythonScript);
    }
    
    // Execute Python script and capture JSON output
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = escapeshellcmd($pythonExecutable) . ' ' . escapeshellarg($scriptPath) . ' 2>NUL';
    } else {
        $command = escapeshellcmd($pythonExecutable) . ' ' . escapeshellarg($scriptPath) . ' 2>/dev/null';
    }
    
    $output = shell_exec($command);
    chdir($originalDir);
    
    if ($output === null || trim($output) === '') {
        throw new Exception('Failed to execute Python script or no output received. Command: ' . $command);
    }
    
    // Parse JSON output
    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Try to extract JSON from output if there's extra text
        if (preg_match('/\{.*\}/s', $output, $matches)) {
            $result = json_decode($matches[0], true);
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Python script: ' . json_last_error_msg() . '. Output preview: ' . substr($output, 0, 500));
        }
    }
    
    // Extract target stock levels from projected_stock
    $targetStockLevels = [];
    $projectedStock = $result['projected_stock'] ?? [];
    
    foreach ($projectedStock as $row) {
        $bloodType = $row['Blood_Type'] ?? '';
        $targetStock = intval($row['Target_Stock'] ?? 0);
        if ($bloodType) {
            $targetStockLevels[$bloodType] = $targetStock;
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
    $hasNonZero = false;
    foreach ($targetStockLevels as $value) {
        if ($value > 0) {
            $hasNonZero = true;
            break;
        }
    }
    
    if (!$hasNonZero) {
        error_log("Target Stock API WARNING: All target stock levels are 0. Projected stock data: " . json_encode($projectedStock));
    } else {
        error_log("Target Stock API: Successfully loaded target stock levels: " . json_encode($targetStockLevels));
    }
    
    $responseData = [
        'success' => true,
        'target_stock_levels' => $targetStockLevels,
        'generated_at' => date('Y-m-d H:i:s'),
        'debug' => [
            'projected_stock_count' => count($projectedStock),
            'has_non_zero' => $hasNonZero
        ]
    ];
    
    // Cache the result for future requests
    @file_put_contents($cacheFile, json_encode($responseData));
    @file_put_contents($cacheMetaFile, json_encode([
        'timestamp' => time(),
        'generated_at' => $responseData['generated_at']
    ]));
    
    echo json_encode($responseData, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Target Stock API Exception: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    
    // Try to return cached data even if there's an error (graceful degradation)
    if (file_exists($cacheFile)) {
        $cachedData = @json_decode(file_get_contents($cacheFile), true);
        if (is_array($cachedData) && isset($cachedData['target_stock_levels'])) {
            header('X-Cache: FALLBACK');
            header('X-Error: ' . $e->getMessage());
            echo json_encode($cachedData, JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'target_stock_levels' => []
    ], JSON_PRETTY_PRINT);
}
?>

