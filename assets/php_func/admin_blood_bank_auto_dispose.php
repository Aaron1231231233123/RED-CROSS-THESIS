<?php
/**
 * Admin Blood Bank Auto Dispose Function
 * 
 * Automatically marks expired blood bank units as "Disposed"
 * Units with expires_at <= today's date will be marked as Disposed
 * 
 * This function should be called when the blood bank dashboard loads
 */

// Include database connection
require_once __DIR__ . '/../conn/db_conn.php';

// Include optimized functions for supabaseRequest
require_once __DIR__ . '/../../public/Dashboards/module/optimized_functions.php';

/**
 * Auto-dispose expired blood bank units
 * 
 * @return array Result with success status, count of disposed units, and any errors
 */
function admin_blood_bank_auto_dispose() {
    $result = [
        'success' => false,
        'disposed_count' => 0,
        'errors' => [],
        'message' => ''
    ];
    
    try {
        // Get today's date in Y-m-d format (matches expires_at format)
        $today = date('Y-m-d');
        
        error_log("Admin Blood Bank Auto Dispose: Checking for expired units (expires_at <= {$today})");
        
        // Fetch all units that are expired (expires_at <= today) and should be disposed
        // We need to get units where:
        // 1. expires_at <= today (expired on or before today)
        // 2. status is NOT 'disposed' (to avoid re-processing already disposed units)
        // 3. status is NOT 'handed_over' (already handed over, don't change - final state)
        // 4. status is NOT 'used' (already used, don't change - final state)
        // Note: Units with status 'expired' or 'valid' will be updated to 'disposed'
        
        $endpoint = "blood_bank_units"
            . "?select=unit_id,expires_at,status"
            . "&expires_at=lte.{$today}"
            . "&status=not.in.(disposed,handed_over,used)"
            . "&order=expires_at.asc";
        
        $response = supabaseRequest($endpoint);
        
        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new Exception('Invalid response from Supabase API');
        }
        
        $expiredUnits = $response['data'];
        $disposedCount = 0;
        $errors = [];
        
        if (empty($expiredUnits)) {
            $result['success'] = true;
            $result['message'] = 'No expired units found to dispose';
            error_log("Admin Blood Bank Auto Dispose: No expired units found");
            return $result;
        }
        
        error_log("Admin Blood Bank Auto Dispose: Found " . count($expiredUnits) . " expired units to dispose");
        
        // Update each expired unit to "Disposed" status
        foreach ($expiredUnits as $unit) {
            $unit_id = $unit['unit_id'];
            $expires_at = $unit['expires_at'];
            $current_status = $unit['status'] ?? 'unknown';
            
            try {
                // Update the unit status to "Disposed"
                $update_data = [
                    'status' => 'disposed',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $updateResponse = supabaseRequest(
                    "blood_bank_units?unit_id=eq." . $unit_id,
                    'PATCH',
                    $update_data
                );
                
                // Check if update was successful (200 or 204)
                if (isset($updateResponse['code']) && ($updateResponse['code'] >= 200 && $updateResponse['code'] < 300)) {
                    $disposedCount++;
                    error_log("Admin Blood Bank Auto Dispose: Unit {$unit_id} (expires: {$expires_at}, was: {$current_status}) marked as Disposed");
                } else {
                    $errorMsg = "Failed to update unit {$unit_id}. HTTP Code: " . ($updateResponse['code'] ?? 'unknown');
                    $errors[] = $errorMsg;
                    error_log("Admin Blood Bank Auto Dispose Error: " . $errorMsg);
                }
                
            } catch (Exception $e) {
                $errorMsg = "Error updating unit {$unit_id}: " . $e->getMessage();
                $errors[] = $errorMsg;
                error_log("Admin Blood Bank Auto Dispose Exception: " . $errorMsg);
            }
        }
        
        // Set result
        $result['success'] = true;
        $result['disposed_count'] = $disposedCount;
        $result['errors'] = $errors;
        
        if ($disposedCount > 0) {
            $result['message'] = "Successfully disposed {$disposedCount} expired blood unit(s)";
            error_log("Admin Blood Bank Auto Dispose: Successfully disposed {$disposedCount} unit(s)");
        } else {
            $result['message'] = 'No units were disposed (all updates failed)';
        }
        
        if (!empty($errors)) {
            $result['message'] .= '. ' . count($errors) . ' error(s) occurred';
        }
        
    } catch (Exception $e) {
        $result['success'] = false;
        $result['errors'][] = $e->getMessage();
        $result['message'] = 'Error during auto-dispose process: ' . $e->getMessage();
        error_log("Admin Blood Bank Auto Dispose Fatal Error: " . $e->getMessage());
    }
    
    return $result;
}

// If called directly (for testing), execute and return JSON
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'] ?? '')) {
    header('Content-Type: application/json');
    $result = admin_blood_bank_auto_dispose();
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}
?>

