<?php
// Helpers for filtered search of Hospital Blood Requests

require_once __DIR__ . '/../../../conn/db_conn.php';

/**
 * Normalize any free-form text for fuzzy comparisons.
 */
function fbr_normalize_text($value) {
    if ($value === null) {
        return '';
    }
    $normalized = strtolower((string)$value);
    $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized);
    $normalized = trim(preg_replace('/\s+/', ' ', $normalized));
    return $normalized;
}

/**
 * Collapse spaces in a normalized string to allow comparisons that
 * ignore whitespace (useful for "firstname lastname" vs "firstnamelastname").
 */
function fbr_collapse_text($normalizedValue) {
    return str_replace(' ', '', $normalizedValue);
}

/**
 * Check whether a normalized variant matches the provided query.
 */
function fbr_variant_matches_query($normalizedVariant, $queryNormalized, $queryCollapsed) {
    if ($normalizedVariant === '' || ($queryNormalized === '' && $queryCollapsed === '')) {
        return false;
    }
    $variantCollapsed = fbr_collapse_text($normalizedVariant);
    if ($queryNormalized !== '' && strpos($normalizedVariant, $queryNormalized) !== false) {
        return true;
    }
    if ($queryCollapsed !== '' && strpos($variantCollapsed, $queryCollapsed) !== false) {
        return true;
    }
    return false;
}

/**
 * Build a set of normalized patient-name variants so that searches such as
 * "First Last", "First M.", "First M Last" or just "First" all succeed.
 */
function fbr_build_patient_name_variants($rawName) {
    $normalized = fbr_normalize_text($rawName);
    if ($normalized === '') {
        return [];
    }

    $variants = [$normalized];
    $tokens = array_values(array_filter(explode(' ', $normalized)));
    if (empty($tokens)) {
        return $variants;
    }

    $first = $tokens[0];
    $last = count($tokens) > 1 ? $tokens[count($tokens) - 1] : '';
    $middle = count($tokens) > 2 ? $tokens[1] : '';
    $middleInitial = $middle !== '' ? substr($middle, 0, 1) : '';

    if ($first) {
        $variants[] = $first;
    }
    if ($last) {
        $variants[] = $last;
    }
    if ($first && $last) {
        $variants[] = trim($first . ' ' . $last);
    }
    if ($first && $middle) {
        $variants[] = trim($first . ' ' . $middle);
    }
    if ($first && $middleInitial) {
        $variants[] = trim($first . ' ' . $middleInitial);
    }
    if ($first && $middleInitial && $last) {
        $variants[] = trim($first . ' ' . $middleInitial . ' ' . $last);
    }
    if ($first && $middle && $last) {
        $variants[] = trim($first . ' ' . $middle . ' ' . $last);
    }

    return array_values(array_unique(array_filter($variants)));
}

/**
 * Build filtered rows for hospital blood requests
 * @param array $filters - Array with 'status' key containing array of status values
 * @param int $user_id - User ID to filter requests
 * @param int $limit - Maximum number of records to return
 * @param string $q - Search query string
 * @return array - Array of filtered request rows
 */
function fbr_build_filtered_rows($filters, $user_id = null, $limit = 150, $q = '') {
    $statuses = isset($filters['status']) && is_array($filters['status']) ? $filters['status'] : [];
    
    // Build Supabase query
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    
    // Base URL
    $url = SUPABASE_URL . '/rest/v1/blood_requests?order=requested_on.desc';
    
    // Add user filter if not fetching all records
    if ($user_id !== null) {
        $url .= '&user_id=eq.' . intval($user_id);
    }
    
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
                // Include Approved, Printed, and Handed_over as "Approved"
                $dbStatuses[] = 'Approved';
                $dbStatuses[] = 'Printed';
                $dbStatuses[] = 'Handed_over';
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
        $queryNormalized = fbr_normalize_text($q);
        $queryCollapsed = fbr_collapse_text($queryNormalized);

        if ($queryNormalized === '' && $queryCollapsed === '') {
            return $data;
        }

        $data = array_filter($data, function($request) use ($queryNormalized, $queryCollapsed) {
            // Build normalized variants for different fields
            $fields = [
                fbr_normalize_text($request['request_id'] ?? ''),
                fbr_normalize_text($request['request_reference'] ?? ''),
                fbr_normalize_text($request['patient_blood_type'] ?? ''),
                fbr_normalize_text($request['hospital_admitted'] ?? ''),
                fbr_normalize_text($request['patient_diagnosis'] ?? ''),
                fbr_normalize_text($request['physician_name'] ?? ''),
            ];

            foreach ($fields as $field) {
                if ($field !== '' && fbr_variant_matches_query($field, $queryNormalized, $queryCollapsed)) {
                    return true;
                }
            }

            // Handle patient name variants (first name only, first + last, etc.)
            $nameVariants = fbr_build_patient_name_variants($request['patient_name'] ?? '');
            foreach ($nameVariants as $variant) {
                if (fbr_variant_matches_query($variant, $queryNormalized, $queryCollapsed)) {
                    return true;
                }
            }

            return false;
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








