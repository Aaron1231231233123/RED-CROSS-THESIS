<?php
/**
 * Deep Scan Script for Blood Drive Scheduling System
 * Comprehensive error detection and validation
 * Run in browser: http://localhost/RED-CROSS-THESIS/Debugging and test scripts/deep_scan_blood_drive.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);

// Get the base directory (one level up from this script)
$baseDir = dirname(__DIR__);
if (strpos($baseDir, 'Debugging and test scripts') !== false) {
    $baseDir = dirname($baseDir);
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Blood Drive Scheduling - Deep Error Scan</title>";
echo "<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; max-width: 1600px; margin: 0 auto; background: #f5f5f5; }
    .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    h1 { color: #d32f2f; border-bottom: 3px solid #d32f2f; padding-bottom: 10px; }
    h2 { color: #1976d2; margin-top: 30px; border-left: 4px solid #1976d2; padding-left: 15px; }
    h3 { color: #388e3c; margin-top: 20px; }
    .error { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 10px 0; border-radius: 4px; }
    .warning { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 10px 0; border-radius: 4px; }
    .success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 10px 0; border-radius: 4px; }
    .info { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 10px 0; border-radius: 4px; }
    pre { background: #263238; color: #aed581; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #1976d2; color: white; font-weight: bold; }
    .critical { background: #ffcdd2; font-weight: bold; }
    .line-number { color: #999; font-size: 11px; }
</style></head><body>";

echo "<h1>🔍 Blood Drive Scheduling System - Deep Error Scan</h1>";
echo "<p><strong>Scan Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

$errors = [];
$warnings = [];
$success = [];
$info = [];

function addError($message, $file = '', $line = 0, $details = '') {
    global $errors;
    $errors[] = ['message' => $message, 'file' => $file, 'line' => $line, 'details' => $details];
}

function addWarning($message, $file = '', $line = 0, $details = '') {
    global $warnings;
    $warnings[] = ['message' => $message, 'file' => $file, 'line' => $line, 'details' => $details];
}

function addSuccess($message, $details = '') {
    global $success;
    $success[] = ['message' => $message, 'details' => $details];
}

function addInfo($message, $details = '') {
    global $info;
    $info[] = ['message' => $message, 'details' => $details];
}

// ============================================
// 1. FILE EXISTENCE & READABILITY
// ============================================
echo "<div class='container'>";
echo "<h2>1. File System Validation</h2>";

$requiredFiles = [
    $baseDir . '/public/api/broadcast-blood-drive.php' => 'Main API endpoint',
    $baseDir . '/assets/php_func/email_sender.php' => 'Email notification handler',
    $baseDir . '/assets/php_func/web_push_sender.php' => 'Push notification handler',
    $baseDir . '/assets/php_func/vapid_config.php' => 'VAPID keys configuration',
    $baseDir . '/assets/conn/db_conn.php' => 'Database connection',
    $baseDir . '/public/Dashboards/module/optimized_functions.php' => 'Supabase helper functions',
    $baseDir . '/public/Dashboards/dashboard-Inventory-System.php' => 'Frontend dashboard',
    __DIR__ . '/Sqls/create_blood_drive_table.sql' => 'Database schema',
    __DIR__ . '/Sqls/create_notification_logs_table.sql' => 'Notification logs schema'
];

foreach ($requiredFiles as $file => $desc) {
    if (!file_exists($file)) {
        addError("Missing file: $file", $file, 0, $desc);
    } elseif (!is_readable($file)) {
        addError("File not readable: $file", $file, 0, $desc);
    } else {
        addSuccess("File exists: $file", $desc);
    }
}

echo "</div>";

// ============================================
// 2. PHP SYNTAX CHECK
// ============================================
echo "<div class='container'>";
echo "<h2>2. PHP Syntax Validation</h2>";

$phpFiles = [
    $baseDir . '/public/api/broadcast-blood-drive.php',
    $baseDir . '/assets/php_func/email_sender.php',
    $baseDir . '/assets/php_func/web_push_sender.php'
];

foreach ($phpFiles as $file) {
    if (!file_exists($file)) {
        continue;
    }
    
    // Try to check syntax using PHP CLI
    $output = [];
    $returnVar = 0;
    $phpPath = '';
    
    // Try to find PHP executable
    $possiblePaths = [
        'D:/Xampp/php/php.exe',
        'C:/xampp/php/php.exe',
        'php' // Try system PATH
    ];
    
    $phpPath = 'php'; // Default
    foreach ($possiblePaths as $path) {
        if ($path === 'php' || file_exists($path)) {
            $phpPath = $path;
            break;
        }
    }
    
    exec("\"$phpPath\" -l \"$file\" 2>&1", $output, $returnVar);
    
    if ($returnVar === 0) {
        addSuccess("Syntax valid: $file");
    } else {
        $errorMsg = implode("\n", $output);
        addError("Syntax error in: $file", $file, 0, $errorMsg);
    }
}

echo "</div>";

// ============================================
// 3. CODE ANALYSIS - API ENDPOINT
// ============================================
echo "<div class='container'>";
echo "<h2>3. API Endpoint Code Analysis</h2>";

$apiFile = $baseDir . '/public/api/broadcast-blood-drive.php';
if (file_exists($apiFile)) {
    $content = file_get_contents($apiFile);
    $lines = explode("\n", $content);
    
    // Check for required functions
    if (strpos($content, 'function calculateDistance') === false) {
        addError("Missing calculateDistance() function", $apiFile);
    } else {
        addSuccess("calculateDistance() function found");
    }
    
    if (strpos($content, 'function logNotification') === false) {
        addError("Missing logNotification() function", $apiFile);
    } else {
        addSuccess("logNotification() function found");
    }
    
    // Check for required classes
    if (strpos($content, 'new WebPushSender') === false) {
        addError("WebPushSender not instantiated", $apiFile);
    } else {
        addSuccess("WebPushSender instantiation found");
    }
    
    if (strpos($content, 'new EmailSender') === false) {
        addError("EmailSender not instantiated", $apiFile);
    } else {
        addSuccess("EmailSender instantiation found");
    }
    
    // Check for error handling
    $tryCount = substr_count($content, 'try {');
    $catchCount = substr_count($content, 'catch');
    
    if ($tryCount === 0) {
        addError("No try-catch blocks found", $apiFile);
    } elseif ($tryCount !== $catchCount) {
        addWarning("Mismatched try-catch blocks (try: $tryCount, catch: $catchCount)", $apiFile);
    } else {
        addSuccess("Error handling present ($tryCount try-catch blocks)");
    }
    
    // Check for input validation
    if (strpos($content, 'required_fields') === false) {
        addError("Input validation missing", $apiFile);
    } else {
        addSuccess("Input validation implemented");
    }
    
    // Check for JSON response
    if (strpos($content, 'json_encode') === false || strpos($content, 'Content-Type: application/json') === false) {
        addError("JSON response not properly configured", $apiFile);
    } else {
        addSuccess("JSON response configured");
    }
    
    // Check for potential issues
    // Check if donor_ids_param could be empty
    if (strpos($content, 'donor_ids_param = implode') !== false) {
        $implodeLine = 0;
        foreach ($lines as $num => $line) {
            if (strpos($line, 'donor_ids_param = implode') !== false) {
                $implodeLine = $num + 1;
                // Check if there's a check before this
                $hasCheck = false;
                for ($i = max(0, $num - 10); $i < $num; $i++) {
                    if (strpos($lines[$i], '!empty($donor_ids)') !== false || 
                        strpos($lines[$i], 'empty($donor_ids)') !== false) {
                        $hasCheck = true;
                        break;
                    }
                }
                if (!$hasCheck) {
                    addWarning("Potential empty array issue at line $implodeLine", $apiFile, $implodeLine, 
                        "donor_ids_param may be empty when imploding");
                }
                break;
            }
        }
    }
    
    // Check for SQL injection risks
    if (preg_match('/\$_(GET|POST|REQUEST)\[.*\]\s*\.\s*["\']\s*SELECT/i', $content)) {
        addError("Potential SQL injection risk detected", $apiFile);
    } else {
        addSuccess("No obvious SQL injection risks");
    }
    
    // Check for proper error logging
    $errorLogCount = substr_count($content, 'error_log');
    if ($errorLogCount < 3) {
        addWarning("Limited error logging ($errorLogCount calls)", $apiFile);
    } else {
        addSuccess("Error logging implemented ($errorLogCount calls)");
    }
    
    // Check for output buffering
    if (strpos($content, 'ob_start') === false) {
        addWarning("Output buffering not enabled", $apiFile);
    } else {
        addSuccess("Output buffering enabled");
    }
    
    // Check for execution time limit
    if (strpos($content, 'set_time_limit') === false) {
        addWarning("Execution time limit not set", $apiFile);
    } else {
        addSuccess("Execution time limit configured");
    }
    
    // Check for duplicate notification prevention
    if (strpos($content, 'notified_donors') === false) {
        addWarning("Duplicate notification prevention may be missing", $apiFile);
    } else {
        addSuccess("Duplicate notification prevention implemented");
    }
    
    // Check for batch processing
    if (strpos($content, 'array_chunk') === false && strpos($content, 'batch') === false) {
        addWarning("Batch processing not implemented - may timeout with large lists", $apiFile);
    } else {
        addSuccess("Batch processing implemented");
    }
    
} else {
    addError("API file not found", $apiFile);
}

echo "</div>";

// ============================================
// 4. CLASS & METHOD VALIDATION
// ============================================
echo "<div class='container'>";
echo "<h2>4. Class & Method Validation</h2>";

// Check EmailSender
if (file_exists($baseDir . '/assets/php_func/email_sender.php')) {
    try {
        require_once $baseDir . '/assets/php_func/email_sender.php';
        
        if (class_exists('EmailSender')) {
            addSuccess("EmailSender class exists");
            
            $emailSender = new EmailSender();
            $requiredMethods = ['sendEmailNotification'];
            
            foreach ($requiredMethods as $method) {
                if (method_exists($emailSender, $method)) {
                    addSuccess("EmailSender::$method() exists");
                } else {
                    addError("EmailSender::$method() missing", 'assets/php_func/email_sender.php');
                }
            }
        } else {
            addError("EmailSender class not found", 'assets/php_func/email_sender.php');
        }
    } catch (Exception $e) {
        addError("Error loading EmailSender: " . $e->getMessage(), 'assets/php_func/email_sender.php');
    }
} else {
    addError("EmailSender file not found", 'assets/php_func/email_sender.php');
}

// Check WebPushSender
if (file_exists($baseDir . '/assets/php_func/web_push_sender.php')) {
    try {
        require_once $baseDir . '/assets/php_func/web_push_sender.php';
        
        if (class_exists('WebPushSender')) {
            addSuccess("WebPushSender class exists");
            
            $webPush = new WebPushSender();
            if (method_exists($webPush, 'sendNotification')) {
                addSuccess("WebPushSender::sendNotification() exists");
            } else {
                addError("WebPushSender::sendNotification() missing", 'assets/php_func/web_push_sender.php');
            }
        } else {
            addError("WebPushSender class not found", 'assets/php_func/web_push_sender.php');
        }
    } catch (Exception $e) {
        addError("Error loading WebPushSender: " . $e->getMessage(), 'assets/php_func/web_push_sender.php');
    }
} else {
    addError("WebPushSender file not found", 'assets/php_func/web_push_sender.php');
}

// Check supabaseRequest function
if (file_exists($baseDir . '/public/Dashboards/module/optimized_functions.php')) {
    try {
        require_once $baseDir . '/public/Dashboards/module/optimized_functions.php';
        
        if (function_exists('supabaseRequest')) {
            addSuccess("supabaseRequest() function exists");
        } else {
            addWarning("supabaseRequest() function not found (may be defined differently)", 
                'public/Dashboards/module/optimized_functions.php');
        }
    } catch (Exception $e) {
        addError("Error loading optimized_functions: " . $e->getMessage(), 
            'public/Dashboards/module/optimized_functions.php');
    }
}

echo "</div>";

// ============================================
// 5. DATABASE CONNECTION & SCHEMA
// ============================================
echo "<div class='container'>";
echo "<h2>5. Database Connection & Schema</h2>";

if (file_exists($baseDir . '/assets/conn/db_conn.php')) {
    try {
        require_once $baseDir . '/assets/conn/db_conn.php';
        
        $hasSupabaseUrl = defined('SUPABASE_URL');
        $hasApiKey = defined('SUPABASE_API_KEY');
        $hasServiceKey = defined('SUPABASE_SERVICE_KEY');
        
        if ($hasSupabaseUrl) {
            addSuccess("SUPABASE_URL defined");
            addInfo("URL: " . SUPABASE_URL);
        } else {
            addError("SUPABASE_URL not defined", 'assets/conn/db_conn.php');
        }
        
        if ($hasApiKey) {
            addSuccess("SUPABASE_API_KEY defined");
        } else {
            addError("SUPABASE_API_KEY not defined", 'assets/conn/db_conn.php');
        }
        
        if ($hasServiceKey) {
            addSuccess("SUPABASE_SERVICE_KEY defined");
        } else {
            addWarning("SUPABASE_SERVICE_KEY not defined (may use API key instead)", 'assets/conn/db_conn.php');
        }
        
        // Test database connection
        if ($hasSupabaseUrl && $hasApiKey && file_exists($baseDir . '/public/Dashboards/module/optimized_functions.php')) {
            require_once $baseDir . '/public/Dashboards/module/optimized_functions.php';
            
            if (function_exists('supabaseRequest')) {
                try {
                    $testResponse = @supabaseRequest("blood_drive_notifications?limit=1");
                    
                    if (isset($testResponse['data'])) {
                        addSuccess("Database connection successful");
                    } elseif (isset($testResponse['error'])) {
                        $errorMsg = $testResponse['error'];
                        if (strpos($errorMsg, 'relation') !== false || strpos($errorMsg, 'does not exist') !== false) {
                            addWarning("Database connected but table may not exist: " . substr($errorMsg, 0, 100));
                        } else {
                            addError("Database connection error: " . substr($errorMsg, 0, 100));
                        }
                    } else {
                        addWarning("Database connection test returned unexpected response");
                    }
                } catch (Exception $e) {
                    addError("Database connection exception: " . $e->getMessage());
                }
            }
        }
        
    } catch (Exception $e) {
        addError("Error loading database connection: " . $e->getMessage(), 'assets/conn/db_conn.php');
    }
} else {
    addError("Database connection file not found", 'assets/conn/db_conn.php');
}

// Check SQL schema files
$sqlFile = __DIR__ . '/Sqls/create_blood_drive_table.sql';
if (file_exists($sqlFile)) {
    $sqlContent = file_get_contents($sqlFile);
    $requiredColumns = ['id', 'location', 'latitude', 'longitude', 'drive_date', 'drive_time', 'radius_km', 'status'];
    
    foreach ($requiredColumns as $column) {
        if (stripos($sqlContent, $column) !== false) {
            addSuccess("Schema column '$column' found");
        } else {
            addError("Schema column '$column' missing", 'create_blood_drive_table.sql');
        }
    }
    
    $indexCount = substr_count($sqlContent, 'CREATE INDEX');
    if ($indexCount >= 3) {
        addSuccess("Database indexes defined ($indexCount indexes)");
    } else {
        addWarning("Limited database indexes ($indexCount indexes)", 'create_blood_drive_table.sql');
    }
    
    if (stripos($sqlContent, 'ROW LEVEL SECURITY') !== false) {
        addSuccess("Row Level Security configured");
    } else {
        addWarning("Row Level Security not configured", 'create_blood_drive_table.sql');
    }
} else {
    addError("Blood drive table SQL file not found", 'create_blood_drive_table.sql');
}

echo "</div>";

// ============================================
// 6. FRONTEND INTEGRATION
// ============================================
echo "<div class='container'>";
echo "<h2>6. Frontend Integration Check</h2>";

$dashboardFile = $baseDir . '/public/Dashboards/dashboard-Inventory-System.php';
if (file_exists($dashboardFile)) {
    $content = file_get_contents($dashboardFile);
    
    if (strpos($content, 'bloodDriveForm') !== false || strpos($content, 'scheduleDriveBtn') !== false) {
        addSuccess("Blood drive form found");
    } else {
        addError("Blood drive form not found", $dashboardFile);
    }
    
    if (strpos($content, 'broadcast-blood-drive.php') !== false) {
        addSuccess("API endpoint referenced");
    } else {
        addError("API endpoint not found in frontend", $dashboardFile);
    }
    
    if (strpos($content, 'sendBloodDriveNotification') !== false) {
        addSuccess("sendBloodDriveNotification() function found");
    } else {
        addError("sendBloodDriveNotification() function missing", $dashboardFile);
    }
    
    if (strpos($content, 'catch') !== false || strpos($content, 'error') !== false) {
        addSuccess("Frontend error handling present");
    } else {
        addWarning("Limited frontend error handling", $dashboardFile);
    }
    
    if (strpos($content, 'showNotificationSuccess') !== false) {
        addSuccess("Success display handler found");
    } else {
        addWarning("Success display handler missing", $dashboardFile);
    }
    
} else {
    addError("Dashboard file not found", $dashboardFile);
}

echo "</div>";

// ============================================
// 7. LOGIC FLOW VALIDATION
// ============================================
echo "<div class='container'>";
echo "<h2>7. Logic Flow Validation</h2>";

if (file_exists($apiFile)) {
    $content = file_get_contents($apiFile);
    
    // Check for donor fetching
    if (strpos($content, 'donor_form') !== false) {
        addSuccess("Donor data fetching implemented");
    } else {
        addError("Donor data fetching missing", $apiFile);
    }
    
    // Check for distance calculation
    if (strpos($content, 'calculateDistance') !== false) {
        addSuccess("Distance calculation implemented");
    } else {
        addError("Distance calculation missing", $apiFile);
    }
    
    // Check for blood type filtering
    if (strpos($content, 'blood_types') !== false && strpos($content, 'screening_form') !== false) {
        addSuccess("Blood type filtering implemented");
    } else {
        addWarning("Blood type filtering may be incomplete", $apiFile);
    }
    
    // Check for push subscription query
    if (strpos($content, 'push_subscriptions') !== false) {
        addSuccess("Push subscription query implemented");
    } else {
        addError("Push subscription query missing", $apiFile);
    }
    
    // Check notification flow order
    $pushPos = strpos($content, 'Send push');
    $emailPos = strpos($content, 'Send email');
    
    if ($pushPos !== false && $emailPos !== false) {
        if ($pushPos < $emailPos) {
            addSuccess("Notification flow order correct (push then email)");
        } else {
            addWarning("Notification flow order may be incorrect", $apiFile);
        }
    }
    
    // Check for notification logging
    if (strpos($content, 'notification_logs') !== false || strpos($content, 'logNotification') !== false) {
        addSuccess("Notification logging implemented");
    } else {
        addWarning("Notification logging missing", $apiFile);
    }
}

echo "</div>";

// ============================================
// SUMMARY & RESULTS
// ============================================
echo "<div class='container'>";
echo "<h2>📊 Scan Summary</h2>";

$totalIssues = count($errors) + count($warnings);
$criticalIssues = count($errors);

echo "<div style='font-size: 18px; margin: 20px 0;'>";
echo "<p><strong>Total Errors:</strong> <span style='color: #f44336;'>" . count($errors) . "</span></p>";
echo "<p><strong>Total Warnings:</strong> <span style='color: #ff9800;'>" . count($warnings) . "</span></p>";
echo "<p><strong>Successful Checks:</strong> <span style='color: #4caf50;'>" . count($success) . "</span></p>";
echo "<p><strong>Info Messages:</strong> <span style='color: #2196f3;'>" . count($info) . "</span></p>";
echo "</div>";

if ($criticalIssues === 0 && count($warnings) === 0) {
    echo "<div class='success'><h3>✅ System Status: EXCELLENT</h3><p>No errors or warnings detected!</p></div>";
} elseif ($criticalIssues === 0) {
    echo "<div class='warning'><h3>⚠️ System Status: GOOD</h3><p>No critical errors, but some warnings should be reviewed.</p></div>";
} elseif ($criticalIssues < 5) {
    echo "<div class='warning'><h3>⚠️ System Status: NEEDS ATTENTION</h3><p>Some critical errors need to be fixed.</p></div>";
} else {
    echo "<div class='error'><h3>❌ System Status: CRITICAL</h3><p>Multiple critical errors detected. System may not function properly.</p></div>";
}

// Display Errors
if (!empty($errors)) {
    echo "<h3 style='color: #f44336;'>❌ Errors Found</h3>";
    foreach ($errors as $error) {
        echo "<div class='error'>";
        echo "<strong>" . htmlspecialchars($error['message']) . "</strong>";
        if (!empty($error['file'])) {
            echo "<br><span class='line-number'>File: " . htmlspecialchars($error['file']);
            if ($error['line'] > 0) {
                echo " (Line: " . $error['line'] . ")";
            }
            echo "</span>";
        }
        if (!empty($error['details'])) {
            echo "<pre>" . htmlspecialchars($error['details']) . "</pre>";
        }
        echo "</div>";
    }
}

// Display Warnings
if (!empty($warnings)) {
    echo "<h3 style='color: #ff9800;'>⚠️ Warnings</h3>";
    foreach ($warnings as $warning) {
        echo "<div class='warning'>";
        echo "<strong>" . htmlspecialchars($warning['message']) . "</strong>";
        if (!empty($warning['file'])) {
            echo "<br><span class='line-number'>File: " . htmlspecialchars($warning['file']);
            if ($warning['line'] > 0) {
                echo " (Line: " . $warning['line'] . ")";
            }
            echo "</span>";
        }
        if (!empty($warning['details'])) {
            echo "<pre>" . htmlspecialchars($warning['details']) . "</pre>";
        }
        echo "</div>";
    }
}

// Display Success
if (!empty($success)) {
    echo "<h3 style='color: #4caf50;'>✅ Successful Checks</h3>";
    echo "<table><tr><th>Check</th><th>Details</th></tr>";
    foreach ($success as $item) {
        echo "<tr><td>" . htmlspecialchars($item['message']) . "</td><td>" . htmlspecialchars($item['details']) . "</td></tr>";
    }
    echo "</table>";
}

// Display Info
if (!empty($info)) {
    echo "<h3 style='color: #2196f3;'>ℹ️ Information</h3>";
    foreach ($info as $item) {
        echo "<div class='info'>";
        echo "<strong>" . htmlspecialchars($item['message']) . "</strong>";
        if (!empty($item['details'])) {
            echo "<br>" . htmlspecialchars($item['details']);
        }
        echo "</div>";
    }
}

echo "</div>";

// ============================================
// RECOMMENDATIONS
// ============================================
echo "<div class='container'>";
echo "<h2>💡 Recommendations</h2>";
echo "<ul style='line-height: 1.8;'>";

if (!empty($errors)) {
    echo "<li><strong>Critical:</strong> Fix all errors before deploying to production.</li>";
}

if (!empty($warnings)) {
    echo "<li><strong>Warnings:</strong> Review and address warnings to improve system reliability.</li>";
}

echo "<li><strong>Database:</strong> Ensure all SQL schema files have been executed in Supabase.</li>";
echo "<li><strong>Configuration:</strong> Verify all API keys and configuration values are correct.</li>";
echo "<li><strong>Testing:</strong> Perform end-to-end testing with real data in a staging environment.</li>";
echo "<li><strong>Monitoring:</strong> Set up error logging and monitoring for production deployment.</li>";
echo "<li><strong>Documentation:</strong> Keep documentation updated as the system evolves.</li>";

echo "</ul>";
echo "</div>";

echo "</body></html>";
?>


