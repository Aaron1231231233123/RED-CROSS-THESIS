<?php
// Helpers for filtered search of Hospital Blood Requests

require_once __DIR__ . '/../../../conn/db_conn.php';

/**
 * Build filtered rows for hospital blood requests
 * @param array $filters - Array with 'status' key containing array of status values
 * @param int $user_id - User ID to filter requests
 * @param int $limit - Maximum number of records to return
 * @param string $q - Search query string
 * @return array - Array of filtered request rows
 */
function fbr_build_filtered_rows($filters, $user_id, $limit = 150, $q = '') {
    $statuses = isset($filters['status']) && is_array($filters['status']) ? $filters['status'] : [];
    
    // Build Supabase query
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    
    // Base URL with user filter
    $url = SUPABASE_URL . '/rest/v1/blood_requests?user_id=eq.' . $user_id . '&order=requested_on.desc';
    
    // Add status filter if provided
    if (!empty($statuses)) {
        // Map display statuses to database statuses
        $dbStatuses = [];
        foreach ($statuses as $status) {
            $statusLower = strtolower(trim($status));
            if ($statusLower === 'all status' || $statusLower === 'all') {
                // Don't filter by status
                continue;
            } elseif ($statusLower === 'approved') {
                // Include both Approved and Printed as "Approved"
                $dbStatuses[] = 'Approved';
                $dbStatuses[] = 'Printed';
            } else {
                $dbStatuses[] = ucfirst($statusLower);
            }
        }
        
        if (!empty($dbStatuses)) {
            // Remove duplicates
            $dbStatuses = array_unique($dbStatuses);
            // Build OR filter for multiple statuses
            $statusFilter = 'or=(' . implode(',', array_map(function($s) {
                return 'status.eq.' . $s;
            }, $dbStatuses)) . ')';
            $url .= '&' . $statusFilter;
        }
    }
    
    // Add limit
    $url .= '&limit=' . intval($limit);
    
    // Execute request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Error fetching filtered blood requests: " . $error);
        return [];
    }
    
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [];
    }
    
    // Apply search query filter if provided
    if (!empty($q) && is_string($q)) {
        $qLower = strtolower(trim($q));
        $data = array_filter($data, function($request) use ($qLower) {
            // Search in request_id, patient_name, patient_blood_type, hospital_admitted
            $requestId = strtolower($request['request_id'] ?? '');
            $patientName = strtolower($request['patient_name'] ?? '');
            $bloodType = strtolower($request['patient_blood_type'] ?? '');
            $hospital = strtolower($request['hospital_admitted'] ?? '');
            $diagnosis = strtolower($request['patient_diagnosis'] ?? '');
            
            return (
                strpos($requestId, $qLower) !== false ||
                strpos($patientName, $qLower) !== false ||
                strpos($bloodType, $qLower) !== false ||
                strpos($hospital, $qLower) !== false ||
                strpos($diagnosis, $qLower) !== false
            );
        });
        // Re-index array after filtering
        $data = array_values($data);
    }
    
    return $data;
}

/**
 * Format status for display (maps Printed to Approved for display)
 */
function fbr_format_status($status) {
    $status = trim($status);
    if ($status === 'Printed' || $status === 'Handed_over') {
        return 'Approved';
    }
    return $status;
}








