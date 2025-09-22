<?php
// Simple debug log viewer
$log_file = 'debug.log';
$log_path = __DIR__ . '/' . $log_file;

if (file_exists($log_path)) {
    $logs = file_get_contents($log_path);
    echo "<h2>Debug Log</h2>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 500px; overflow-y: auto;'>";
    echo htmlspecialchars($logs);
    echo "</pre>";
    
    echo "<p><a href='?clear=1'>Clear Log</a></p>";
    
    if (isset($_GET['clear'])) {
        file_put_contents($log_path, '');
        echo "<p>Log cleared!</p>";
        echo "<script>setTimeout(function(){ window.location.href = 'view_debug_log.php'; }, 1000);</script>";
    }
} else {
    echo "<h2>Debug Log</h2>";
    echo "<p>No debug log found. The log file will be created when you submit a form.</p>";
}
?>
