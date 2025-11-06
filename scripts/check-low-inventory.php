<?php
/**
 * Command-line script to check and notify about low inventory
 * Usage: php check-low-inventory.php [threshold] [rate_limit_days]
 * 
 * Example:
 *   php check-low-inventory.php 25 1
 *   php check-low-inventory.php 30 1  (check at 30 units, 1 day rate limit)
 */

// Allow CLI or web access
if (php_sapi_name() !== 'cli') {
    // If accessed via web, redirect to API endpoint
    header('Location: /public/api/auto-notify-low-inventory.php');
    exit();
}

// Get script directory and include required files
$scriptDir = __DIR__;
$projectRoot = dirname($scriptDir);

// Change to project root
chdir($projectRoot);

// Include required files
require_once $projectRoot . '/assets/conn/db_conn.php';
require_once $projectRoot . '/assets/php_func/vapid_config.php';
require_once $projectRoot . '/assets/php_func/web_push_sender.php';
require_once $projectRoot . '/assets/php_func/email_sender.php';
require_once $projectRoot . '/public/Dashboards/module/optimized_functions.php';

// Get command line arguments
$threshold = isset($argv[1]) ? intval($argv[1]) : 25;
$rate_limit_days = isset($argv[2]) ? intval($argv[2]) : 1;

// Validate inputs
if ($threshold < 1) {
    echo "Error: Threshold must be at least 1 unit\n";
    exit(1);
}

if ($rate_limit_days < 1 || $rate_limit_days > 45) {
    echo "Error: Rate limit must be between 1 and 45 days\n";
    exit(1);
}

echo "=== Low Inventory Notification Check ===\n";
echo "Threshold: $threshold units\n";
echo "Rate Limit: $rate_limit_days days\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// Prepare JSON payload
$payload = json_encode([
    'threshold' => $threshold,
    'rate_limit_days' => $rate_limit_days
]);

// Make request directly by including the API file
// For CLI, we'll execute the logic directly instead of HTTP request
// This is more efficient and avoids URL/domain issues

// Include the API endpoint file to use its functions
$apiFile = $projectRoot . '/public/api/auto-notify-low-inventory.php';

// Read and execute the API logic (modified for CLI)
// Actually, better approach: call the API via HTTP but use file-based approach for CLI

// Option 1: Direct HTTP call (if server is running)
$apiUrl = 'http://localhost/RED-CROSS-THESIS/public/api/auto-notify-low-inventory.php';

// For local XAMPP, adjust path
if (strpos($projectRoot, 'Xampp') !== false || strpos($projectRoot, 'xampp') !== false) {
    // Detect XAMPP installation
    $apiUrl = 'http://localhost/RED-CROSS-THESIS/public/api/auto-notify-low-inventory.php';
} else {
    // Try to auto-detect or use relative path
    $apiUrl = 'http://localhost/public/api/auto-notify-low-inventory.php';
}

// Use curl to call the API
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($payload)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes

echo "Calling API: $apiUrl\n";
echo "Payload: $payload\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "ERROR: cURL error - $curlError\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "ERROR: HTTP $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

// Parse and display response
$result = json_decode($response, true);

if (!$result) {
    echo "ERROR: Invalid JSON response\n";
    echo "Response: $response\n";
    exit(1);
}

// Display results
if ($result['success']) {
    echo "✅ SUCCESS\n\n";
    
    echo "Inventory Status:\n";
    foreach ($result['inventory'] as $blood_type => $count) {
        $status = $count <= $threshold ? '⚠️  LOW' : '✅ OK';
        echo "  $blood_type: $count units $status\n";
    }
    
    echo "\nLow Inventory Types: " . implode(', ', $result['low_inventory_types'] ?: ['None']) . "\n";
    
    echo "\nNotification Summary:\n";
    $summary = $result['summary'];
    echo "  Push Sent: {$summary['push_sent']}\n";
    echo "  Push Failed: {$summary['push_failed']}\n";
    echo "  Push Skipped: {$summary['push_skipped']}\n";
    echo "  Email Sent: {$summary['email_sent']}\n";
    echo "  Email Failed: {$summary['email_failed']}\n";
    echo "  Email Skipped: {$summary['email_skipped']}\n";
    echo "  Total Notified: {$summary['total_notified']}\n";
    
    echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
    exit(0);
} else {
    echo "❌ ERROR\n";
    echo "Message: " . ($result['message'] ?? 'Unknown error') . "\n";
    exit(1);
}
?>

