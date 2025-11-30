# Auto-Notify Low Inventory Scheduled Task Script
# This script is designed to be run by Windows Task Scheduler
# It calls the auto-notify API and logs the results

$apiUrl = "http://localhost/RED-CROSS-THESIS/public/api/auto-notify-low-inventory.php"
$logDir = "D:\Xampp\htdocs\RED-CROSS-THESIS\logs"
$logFile = Join-Path $logDir "auto-notify-$(Get-Date -Format 'yyyy-MM-dd').log"

# Create logs directory if it doesn't exist
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

try {
    Write-Host "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - Starting auto-notify check..."
    
    $response = Invoke-RestMethod -Uri $apiUrl -Method Post -ContentType "application/json" -Body '{}' -TimeoutSec 300
    
    if ($response.success) {
        $lowTypes = if ($response.low_inventory_types) { $response.low_inventory_types -join ', ' } else { 'None' }
        $sent = $response.summary.total_notified
        $skipped = $response.summary.push_skipped
        $failed = $response.summary.push_failed
        
        $logMessage = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - SUCCESS | Low types: $lowTypes | Sent: $sent | Skipped: $skipped | Failed: $failed"
        Add-Content -Path $logFile -Value $logMessage
        Write-Host $logMessage
        
        # Also log target stock levels for reference
        if ($response.target_stock_levels) {
            $targets = ($response.target_stock_levels.GetEnumerator() | ForEach-Object { "$($_.Key):$($_.Value)" }) -join ', '
            $targetLog = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - Target stock levels: $targets"
            Add-Content -Path $logFile -Value $targetLog
        }
    } else {
        $errorMsg = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - FAILED: $($response.message)"
        Add-Content -Path $logFile -Value $errorMsg
        Write-Error $errorMsg
    }
} catch {
    $errorMessage = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - ERROR: $($_.Exception.Message)"
    Add-Content -Path $logFile -Value $errorMessage
    Write-Error $errorMessage
    exit 1
}

Write-Host "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') - Auto-notify check completed."
exit 0

