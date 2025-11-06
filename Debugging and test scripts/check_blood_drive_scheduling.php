<?php
/**
 * Comprehensive Blood Drive Scheduling System Diagnostic Script
 * This script performs a deep scan of the blood drive scheduling system
 * Run this in your browser: http://localhost/RED-CROSS-THESIS/check_blood_drive_scheduling.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Blood Drive Scheduling System - Deep Diagnostic</title>";
echo "<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; max-width: 1400px; margin: 0 auto; background: #f5f5f5; }
    .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    h1 { color: #d32f2f; border-bottom: 3px solid #d32f2f; padding-bottom: 10px; }
    h2 { color: #1976d2; margin-top: 30px; border-left: 4px solid #1976d2; padding-left: 15px; }
    h3 { color: #388e3c; margin-top: 20px; }
    .test-item { padding: 10px; margin: 5px 0; border-radius: 4px; background: #fafafa; }
    .pass { background: #e8f5e9; border-left: 4px solid #4caf50; }
    .fail { background: #ffebee; border-left: 4px solid #f44336; }
    .warning { background: #fff3e0; border-left: 4px solid #ff9800; }
    .info { background: #e3f2fd; border-left: 4px solid #2196f3; }
    .status { font-weight: bold; font-size: 14px; }
    .pass .status { color: #2e7d32; }
    .fail .status { color: #c62828; }
    .warning .status { color: #e65100; }
    .info .status { color: #1565c0; }
    pre { background: #263238; color: #aed581; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #1976d2; color: white; font-weight: bold; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .summary-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
    .summary-box h3 { color: white; margin-top: 0; }
    .error-details { background: #ffebee; padding: 10px; border-radius: 4px; margin-top: 10px; }
    .success-rate { font-size: 24px; font-weight: bold; margin: 10px 0; }
    .progress-bar { width: 100%; height: 30px; background: #e0e0e0; border-radius: 15px; overflow: hidden; margin: 10px 0; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #4caf50, #8bc34a); transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
</style></head><body>";

echo "<h1>🩸 Blood Drive Scheduling System - Deep Diagnostic Scan</h1>";
echo "<p><strong>Scan Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test results tracker
$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'info' => 0,
    'tests' => []
];

function addTest($name, $status, $message = '', $details = '', $category = 'general') {
    global $results;
    $results['total']++;
    
    $statusMap = [
        'pass' => ['count' => 'passed', 'class' => 'pass', 'icon' => '✅'],
        'fail' => ['count' => 'failed', 'class' => 'fail', 'icon' => '❌'],
        'warning' => ['count' => 'warnings', 'class' => 'warning', 'icon' => '⚠️'],
        'info' => ['count' => 'info', 'class' => 'info', 'icon' => 'ℹ️']
    ];
    
    $statusInfo = $statusMap[$status] ?? $statusMap['info'];
    $results[$statusInfo['count']]++;
    
    $results['tests'][] = [
        'name' => $name,
        'status' => $status,
        'class' => $statusInfo['class'],
        'icon' => $statusInfo['icon'],
        'message' => $message,
        'details' => $details,
        'category' => $category
    ];
}

// ============================================
// TEST 1: File System Check
// ============================================
echo "<div class='container'>";
echo "<h2>1. File System & Dependencies Check</h2>";

$requiredFiles = [
    'public/api/broadcast-blood-drive.php' => 'Main API endpoint',
    'assets/php_func/email_sender.php' => 'Email notification handler',
    'assets/php_func/web_push_sender.php' => 'Push notification handler',
    'assets/php_func/vapid_config.php' => 'VAPID keys configuration',
    'assets/conn/db_conn.php' => 'Database connection',
    'public/Dashboards/module/optimized_functions.php' => 'Supabase helper functions',
    'public/Dashboards/dashboard-Inventory-System.php' => 'Frontend dashboard',
    'create_blood_drive_table.sql' => 'Database schema',
    'create_notification_logs_table.sql' => 'Notification logs schema'
];

foreach ($requiredFiles as $file => $description) {
    $exists = file_exists($file);
    $size = $exists ? filesize($file) : 0;
    $readable = $exists ? is_readable($file) : false;
    
    if ($exists && $readable) {
        addTest("File: $file", 'pass', "$description - Found ($size bytes)", $file, 'filesystem');
    } elseif ($exists && !$readable) {
        addTest("File: $file", 'warning', "$description - Exists but not readable", $file, 'filesystem');
    } else {
        addTest("File: $file", 'fail', "$description - MISSING", $file, 'filesystem');
    }
}

echo "</div>";

// ============================================
// TEST 2: PHP Syntax & Code Quality
// ============================================
echo "<div class='container'>";
echo "<h2>2. PHP Syntax & Code Quality Analysis</h2>";

$phpFiles = [
    'public/api/broadcast-blood-drive.php',
    'assets/php_func/email_sender.php',
    'assets/php_func/web_push_sender.php'
];

foreach ($phpFiles as $file) {
    if (!file_exists($file)) {
        addTest("Syntax: $file", 'fail', "File not found", $file, 'syntax');
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check for PHP opening tag
    $hasPhpTag = strpos($content, '<?php') !== false;
    addTest("PHP Tag: $file", $hasPhpTag ? 'pass' : 'fail', 
        $hasPhpTag ? "Has PHP opening tag" : "Missing PHP opening tag", $file, 'syntax');
    
    // Check for closing tag (should not have one for included files)
    $hasClosingTag = strpos($content, '?>') !== false;
    if ($hasClosingTag && $file !== 'public/api/broadcast-blood-drive.php') {
        addTest("Closing Tag: $file", 'warning', 
            "Has closing tag (not recommended for included files)", $file, 'syntax');
    }
    
    // Check for common errors
    $hasTryCatch = substr_count($content, 'try {') > 0;
    addTest("Error Handling: $file", $hasTryCatch ? 'pass' : 'warning', 
        $hasTryCatch ? "Has try-catch blocks" : "Limited error handling", $file, 'syntax');
    
    // Check for SQL injection vulnerabilities
    $hasDirectSql = preg_match('/\$_(GET|POST|REQUEST)\[.*\]\s*\.\s*["\']\s*SELECT/i', $content);
    addTest("SQL Injection Risk: $file", !$hasDirectSql ? 'pass' : 'fail', 
        !$hasDirectSql ? "No obvious SQL injection risks" : "Potential SQL injection risk detected", $file, 'security');
}

echo "</div>";

// ============================================
// TEST 3: Database Connection & Schema
// ============================================
echo "<div class='container'>";
echo "<h2>3. Database Connection & Schema Validation</h2>";

// Load database connection
if (file_exists('assets/conn/db_conn.php')) {
    require_once 'assets/conn/db_conn.php';
    
    // Check if constants are defined
    $hasSupabaseUrl = defined('SUPABASE_URL');
    $hasApiKey = defined('SUPABASE_API_KEY');
    $hasServiceKey = defined('SUPABASE_SERVICE_KEY');
    
    addTest("SUPABASE_URL defined", $hasSupabaseUrl ? 'pass' : 'fail', 
        $hasSupabaseUrl ? "URL: " . SUPABASE_URL : "Not defined", '', 'database');
    addTest("SUPABASE_API_KEY defined", $hasApiKey ? 'pass' : 'fail', 
        $hasApiKey ? "API key present" : "Not defined", '', 'database');
    addTest("SUPABASE_SERVICE_KEY defined", $hasServiceKey ? 'pass' : 'fail', 
        $hasServiceKey ? "Service key present" : "Not defined", '', 'database');
    
    // Test database connectivity
    if ($hasSupabaseUrl && $hasApiKey) {
        // Load supabaseRequest function
        if (file_exists('public/Dashboards/module/optimized_functions.php')) {
            require_once 'public/Dashboards/module/optimized_functions.php';
            
            // Test connection by querying a simple endpoint
            try {
                $testResponse = @supabaseRequest("blood_drive_notifications?limit=1");
                
                if (isset($testResponse['data']) || (isset($testResponse['error']) && strpos($testResponse['error'], 'relation') === false)) {
                    addTest("Database Connection", 'pass', 
                        "Successfully connected to Supabase", '', 'database');
                } else {
                    $errorMsg = $testResponse['error'] ?? 'Unknown error';
                    if (strpos($errorMsg, 'relation') !== false || strpos($errorMsg, 'does not exist') !== false) {
                        addTest("Database Connection", 'warning', 
                            "Connected but table may not exist: " . substr($errorMsg, 0, 100), '', 'database');
                    } else {
                        addTest("Database Connection", 'fail', 
                            "Connection failed: " . substr($errorMsg, 0, 100), '', 'database');
                    }
                }
            } catch (Exception $e) {
                addTest("Database Connection", 'fail', 
                    "Exception: " . $e->getMessage(), '', 'database');
            }
        } else {
            addTest("Database Connection", 'warning', 
                "Cannot test - optimized_functions.php not found", '', 'database');
        }
    }
    
    // Check SQL schema files
    if (file_exists('create_blood_drive_table.sql')) {
        $sqlContent = file_get_contents('create_blood_drive_table.sql');
        $requiredColumns = ['id', 'location', 'latitude', 'longitude', 'drive_date', 'drive_time', 'radius_km', 'status'];
        
        foreach ($requiredColumns as $column) {
            $hasColumn = stripos($sqlContent, $column) !== false;
            addTest("Schema Column: $column", $hasColumn ? 'pass' : 'fail', 
                $hasColumn ? "Column defined" : "Column missing", '', 'database');
        }
        
        // Check for indexes
        $indexCount = substr_count($sqlContent, 'CREATE INDEX');
        addTest("Database Indexes", $indexCount >= 3 ? 'pass' : 'warning', 
            "Found $indexCount indexes", '', 'database');
        
        // Check for RLS
        $hasRLS = stripos($sqlContent, 'ROW LEVEL SECURITY') !== false;
        addTest("Row Level Security", $hasRLS ? 'pass' : 'warning', 
            $hasRLS ? "RLS enabled" : "RLS not configured", '', 'database');
    }
}

echo "</div>";

// ============================================
// TEST 4: API Endpoint Analysis
// ============================================
echo "<div class='container'>";
echo "<h2>4. API Endpoint Deep Analysis</h2>";

$apiFile = 'public/api/broadcast-blood-drive.php';
if (file_exists($apiFile)) {
    $apiContent = file_get_contents($apiFile);
    
    // Check required functions
    $requiredFunctions = [
        'calculateDistance' => 'Haversine distance calculation',
        'logNotification' => 'Notification logging',
        'supabaseRequest' => 'Database requests'
    ];
    
    foreach ($requiredFunctions as $func => $desc) {
        $hasFunction = strpos($apiContent, "function $func") !== false || 
                      strpos($apiContent, "$func(") !== false;
        addTest("Function: $func", $hasFunction ? 'pass' : 'fail', 
            $hasFunction ? "$desc - Found" : "$desc - Missing", '', 'api');
    }
    
    // Check input validation
    $hasInputValidation = strpos($apiContent, 'required_fields') !== false && 
                         strpos($apiContent, 'foreach') !== false;
    addTest("Input Validation", $hasInputValidation ? 'pass' : 'fail', 
        $hasInputValidation ? "Input validation implemented" : "Input validation missing", '', 'api');
    
    // Check error handling
    $tryCatchCount = substr_count($apiContent, 'try {');
    $catchCount = substr_count($apiContent, 'catch');
    addTest("Error Handling", ($tryCatchCount > 0 && $catchCount > 0) ? 'pass' : 'warning', 
        "Found $tryCatchCount try blocks and $catchCount catch blocks", '', 'api');
    
    // Check JSON response
    $hasJsonResponse = strpos($apiContent, 'json_encode') !== false && 
                      strpos($apiContent, 'Content-Type: application/json') !== false;
    addTest("JSON Response", $hasJsonResponse ? 'pass' : 'fail', 
        $hasJsonResponse ? "JSON response configured" : "JSON response missing", '', 'api');
    
    // Check for required classes
    $requiredClasses = ['WebPushSender', 'EmailSender'];
    foreach ($requiredClasses as $class) {
        $hasClass = strpos($apiContent, "new $class") !== false;
        addTest("Class Usage: $class", $hasClass ? 'pass' : 'fail', 
            $hasClass ? "Class instantiated" : "Class not used", '', 'api');
    }
    
    // Check notification flow
    $hasPushFlow = strpos($apiContent, 'Send push') !== false || 
                  strpos($apiContent, 'pushSender') !== false;
    $hasEmailFlow = strpos($apiContent, 'Send email') !== false || 
                   strpos($apiContent, 'emailSender') !== false;
    
    addTest("Push Notification Flow", $hasPushFlow ? 'pass' : 'fail', 
        $hasPushFlow ? "Push flow implemented" : "Push flow missing", '', 'api');
    addTest("Email Notification Flow", $hasEmailFlow ? 'pass' : 'fail', 
        $hasEmailFlow ? "Email flow implemented" : "Email flow missing", '', 'api');
    
    // Check for duplicate prevention
    $hasDuplicateCheck = strpos($apiContent, 'notified_donors') !== false;
    addTest("Duplicate Prevention", $hasDuplicateCheck ? 'pass' : 'warning', 
        $hasDuplicateCheck ? "Duplicate prevention implemented" : "May send duplicate notifications", '', 'api');
    
    // Check batch processing
    $hasBatchProcessing = strpos($apiContent, 'array_chunk') !== false || 
                         strpos($apiContent, 'batch') !== false;
    addTest("Batch Processing", $hasBatchProcessing ? 'pass' : 'warning', 
        $hasBatchProcessing ? "Batch processing implemented" : "May timeout with large lists", '', 'api');
    
    // Check response structure
    $responseKeys = ['success', 'message', 'blood_drive_id', 'summary', 'total_donors_found'];
    foreach ($responseKeys as $key) {
        $hasKey = strpos($apiContent, "'$key'") !== false || 
                 strpos($apiContent, "\"$key\"") !== false;
        addTest("Response Key: $key", $hasKey ? 'pass' : 'fail', 
            $hasKey ? "Key present" : "Key missing", '', 'api');
    }
    
} else {
    addTest("API File", 'fail', "API file not found", '', 'api');
}

echo "</div>";

// ============================================
// TEST 5: Frontend Integration
// ============================================
echo "<div class='container'>";
echo "<h2>5. Frontend Integration Check</h2>";

$dashboardFile = 'public/Dashboards/dashboard-Inventory-System.php';
if (file_exists($dashboardFile)) {
    $dashboardContent = file_get_contents($dashboardFile);
    
    // Check for blood drive form
    $hasForm = strpos($dashboardContent, 'bloodDriveForm') !== false || 
              strpos($dashboardContent, 'scheduleDriveBtn') !== false;
    addTest("Blood Drive Form", $hasForm ? 'pass' : 'fail', 
        $hasForm ? "Form found" : "Form missing", '', 'frontend');
    
    // Check for API call
    $hasApiCall = strpos($dashboardContent, 'broadcast-blood-drive.php') !== false;
    addTest("API Integration", $hasApiCall ? 'pass' : 'fail', 
        $hasApiCall ? "API endpoint referenced" : "API endpoint not found", '', 'frontend');
    
    // Check for error handling
    $hasErrorHandling = strpos($dashboardContent, 'catch') !== false || 
                       strpos($dashboardContent, 'error') !== false;
    addTest("Frontend Error Handling", $hasErrorHandling ? 'pass' : 'warning', 
        $hasErrorHandling ? "Error handling present" : "Limited error handling", '', 'frontend');
    
    // Check for success display
    $hasSuccessDisplay = strpos($dashboardContent, 'showNotificationSuccess') !== false || 
                        strpos($dashboardContent, 'success') !== false;
    addTest("Success Display", $hasSuccessDisplay ? 'pass' : 'warning', 
        $hasSuccessDisplay ? "Success handler found" : "Success handler missing", '', 'frontend');
    
} else {
    addTest("Dashboard File", 'fail', "Dashboard file not found", '', 'frontend');
}

echo "</div>";

// ============================================
// TEST 6: Class & Method Validation
// ============================================
echo "<div class='container'>";
echo "<h2>6. Class & Method Validation</h2>";

// Check EmailSender
if (file_exists('assets/php_func/email_sender.php')) {
    require_once 'assets/php_func/email_sender.php';
    
    $emailSenderExists = class_exists('EmailSender');
    addTest("EmailSender Class", $emailSenderExists ? 'pass' : 'fail', 
        $emailSenderExists ? "Class loaded" : "Class not found", '', 'classes');
    
    if ($emailSenderExists) {
        $emailSender = new EmailSender();
        $requiredMethods = ['sendEmailNotification'];
        foreach ($requiredMethods as $method) {
            $methodExists = method_exists($emailSender, $method);
            addTest("EmailSender::$method()", $methodExists ? 'pass' : 'fail', 
                $methodExists ? "Method exists" : "Method missing", '', 'classes');
        }
    }
}

// Check WebPushSender
if (file_exists('assets/php_func/web_push_sender.php')) {
    require_once 'assets/php_func/web_push_sender.php';
    
    $webPushExists = class_exists('WebPushSender');
    addTest("WebPushSender Class", $webPushExists ? 'pass' : 'fail', 
        $webPushExists ? "Class loaded" : "Class not found", '', 'classes');
    
    if ($webPushExists) {
        $webPush = new WebPushSender();
        $requiredMethods = ['sendNotification'];
        foreach ($requiredMethods as $method) {
            $methodExists = method_exists($webPush, $method);
            addTest("WebPushSender::$method()", $methodExists ? 'pass' : 'fail', 
                $methodExists ? "Method exists" : "Method missing", '', 'classes');
        }
    }
}

echo "</div>";

// ============================================
// TEST 7: Security & Best Practices
// ============================================
echo "<div class='container'>";
echo "<h2>7. Security & Best Practices Check</h2>";

if (file_exists($apiFile)) {
    $apiContent = file_get_contents($apiFile);
    
    // Check for output buffering
    $hasOutputBuffering = strpos($apiContent, 'ob_start') !== false;
    addTest("Output Buffering", $hasOutputBuffering ? 'pass' : 'warning', 
        $hasOutputBuffering ? "Output buffering enabled" : "May leak output", '', 'security');
    
    // Check for session security
    $hasSessionStart = strpos($apiContent, 'session_start') !== false;
    addTest("Session Management", $hasSessionStart ? 'pass' : 'info', 
        $hasSessionStart ? "Session started" : "No session (may be intentional)", '', 'security');
    
    // Check for CORS headers
    $hasCORS = strpos($apiContent, 'Access-Control-Allow') !== false;
    addTest("CORS Headers", $hasCORS ? 'pass' : 'warning', 
        $hasCORS ? "CORS configured" : "CORS not configured", '', 'security');
    
    // Check for input sanitization
    $hasSanitization = strpos($apiContent, 'floatval') !== false || 
                      strpos($apiContent, 'intval') !== false ||
                      strpos($apiContent, 'htmlspecialchars') !== false;
    addTest("Input Sanitization", $hasSanitization ? 'pass' : 'warning', 
        $hasSanitization ? "Input sanitization present" : "Limited sanitization", '', 'security');
    
    // Check for error logging
    $errorLogCount = substr_count($apiContent, 'error_log');
    addTest("Error Logging", $errorLogCount >= 3 ? 'pass' : 'warning', 
        "Found $errorLogCount error_log calls", '', 'security');
    
    // Check for time limits
    $hasTimeLimit = strpos($apiContent, 'set_time_limit') !== false;
    addTest("Execution Time Limit", $hasTimeLimit ? 'pass' : 'warning', 
        $hasTimeLimit ? "Time limit set" : "No time limit (may timeout)", '', 'security');
}

echo "</div>";

// ============================================
// TEST 8: Data Flow Validation
// ============================================
echo "<div class='container'>";
echo "<h2>8. Data Flow & Logic Validation</h2>";

if (file_exists($apiFile)) {
    $apiContent = file_get_contents($apiFile);
    
    // Check for donor fetching
    $hasDonorFetch = strpos($apiContent, 'donor_form') !== false;
    addTest("Donor Data Fetching", $hasDonorFetch ? 'pass' : 'fail', 
        $hasDonorFetch ? "Donor query present" : "Donor query missing", '', 'logic');
    
    // Check for distance calculation
    $hasDistanceCalc = strpos($apiContent, 'calculateDistance') !== false;
    addTest("Distance Calculation", $hasDistanceCalc ? 'pass' : 'fail', 
        $hasDistanceCalc ? "Distance calculation implemented" : "Distance calculation missing", '', 'logic');
    
    // Check for blood type filtering
    $hasBloodTypeFilter = strpos($apiContent, 'blood_types') !== false && 
                         strpos($apiContent, 'screening_form') !== false;
    addTest("Blood Type Filtering", $hasBloodTypeFilter ? 'pass' : 'warning', 
        $hasBloodTypeFilter ? "Blood type filtering implemented" : "Blood type filtering missing", '', 'logic');
    
    // Check for push subscription query
    $hasPushQuery = strpos($apiContent, 'push_subscriptions') !== false;
    addTest("Push Subscription Query", $hasPushQuery ? 'pass' : 'fail', 
        $hasPushQuery ? "Push subscription query present" : "Push subscription query missing", '', 'logic');
    
    // Check for notification logging
    $hasNotificationLog = strpos($apiContent, 'notification_logs') !== false || 
                        strpos($apiContent, 'logNotification') !== false;
    addTest("Notification Logging", $hasNotificationLog ? 'pass' : 'warning', 
        $hasNotificationLog ? "Notification logging implemented" : "Notification logging missing", '', 'logic');
}

echo "</div>";

// ============================================
// SUMMARY
// ============================================
echo "<div class='container summary-box'>";
echo "<h2>📊 Diagnostic Summary</h2>";

$passRate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100, 2) : 0;

echo "<div class='success-rate'>Overall Pass Rate: $passRate%</div>";
echo "<div class='progress-bar'><div class='progress-fill' style='width: $passRate%'>$passRate%</div></div>";

echo "<table>";
echo "<tr><th>Category</th><th>Total</th><th>Passed</th><th>Failed</th><th>Warnings</th><th>Info</th></tr>";

$categories = [];
foreach ($results['tests'] as $test) {
    $cat = $test['category'];
    if (!isset($categories[$cat])) {
        $categories[$cat] = ['total' => 0, 'passed' => 0, 'failed' => 0, 'warnings' => 0, 'info' => 0];
    }
    $categories[$cat]['total']++;
    $categories[$cat][$test['status']]++;
}

foreach ($categories as $cat => $stats) {
    echo "<tr>";
    echo "<td><strong>" . ucfirst($cat) . "</strong></td>";
    echo "<td>{$stats['total']}</td>";
    echo "<td style='color: #2e7d32;'>{$stats['passed']}</td>";
    echo "<td style='color: #c62828;'>{$stats['failed']}</td>";
    echo "<td style='color: #e65100;'>{$stats['warnings']}</td>";
    echo "<td style='color: #1565c0;'>{$stats['info']}</td>";
    echo "</tr>";
}

echo "</table>";

if ($passRate >= 90) {
    echo "<p style='font-size: 18px; margin-top: 20px;'><strong>✅ System Status: EXCELLENT</strong></p>";
    echo "<p>The blood drive scheduling system appears to be properly implemented with minimal issues.</p>";
} elseif ($passRate >= 75) {
    echo "<p style='font-size: 18px; margin-top: 20px;'><strong>⚠️ System Status: GOOD</strong></p>";
    echo "<p>The system is mostly functional but has some issues that should be addressed.</p>";
} elseif ($passRate >= 50) {
    echo "<p style='font-size: 18px; margin-top: 20px;'><strong>⚠️ System Status: NEEDS ATTENTION</strong></p>";
    echo "<p>The system has significant issues that need to be fixed before production use.</p>";
} else {
    echo "<p style='font-size: 18px; margin-top: 20px;'><strong>❌ System Status: CRITICAL</strong></p>";
    echo "<p>The system has critical issues that must be resolved immediately.</p>";
}

echo "</div>";

// ============================================
// DETAILED TEST RESULTS
// ============================================
echo "<div class='container'>";
echo "<h2>📋 Detailed Test Results</h2>";

$currentCategory = '';
foreach ($results['tests'] as $test) {
    if ($currentCategory !== $test['category']) {
        if ($currentCategory !== '') {
            echo "</div>";
        }
        echo "<h3>" . ucfirst($test['category']) . " Tests</h3>";
        $currentCategory = $test['category'];
    }
    
    echo "<div class='test-item {$test['class']}'>";
    echo "<div class='status'>{$test['icon']} {$test['name']}</div>";
    if (!empty($test['message'])) {
        echo "<div style='margin-top: 5px;'>{$test['message']}</div>";
    }
    if (!empty($test['details'])) {
        echo "<div class='error-details' style='margin-top: 5px; font-size: 12px;'><code>{$test['details']}</code></div>";
    }
    echo "</div>";
}
if ($currentCategory !== '') {
    echo "</div>";
}

echo "</div>";

// ============================================
// RECOMMENDATIONS
// ============================================
echo "<div class='container'>";
echo "<h2>💡 Recommendations</h2>";
echo "<ul style='line-height: 1.8;'>";

$hasFailures = $results['failed'] > 0;
$hasWarnings = $results['warnings'] > 0;

if ($hasFailures) {
    echo "<li><strong>Critical Issues:</strong> Address all failed tests before deploying to production.</li>";
}

if ($hasWarnings) {
    echo "<li><strong>Warnings:</strong> Review and address warnings to improve system reliability.</li>";
}

echo "<li><strong>Database Setup:</strong> Ensure all SQL schema files have been executed in Supabase.</li>";
echo "<li><strong>Configuration:</strong> Verify all API keys and configuration values are correct.</li>";
echo "<li><strong>Testing:</strong> Perform end-to-end testing with real data in a staging environment.</li>";
echo "<li><strong>Monitoring:</strong> Set up error logging and monitoring for production deployment.</li>";
echo "<li><strong>Documentation:</strong> Keep documentation updated as the system evolves.</li>";

echo "</ul>";
echo "</div>";

echo "</body></html>";
?>


