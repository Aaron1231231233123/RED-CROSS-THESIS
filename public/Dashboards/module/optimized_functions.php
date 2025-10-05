<?php
// OPTIMIZATION: Shared utility functions for dashboard modules
// This file contains optimized functions that can be used across multiple modules

// OPTIMIZATION: Enhanced function to make API requests to Supabase with retry mechanism
function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;

    $headers = [
        "Content-Type: application/json",
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Prefer: return=representation"
    ];

    // OPTIMIZATION FOR SLOW INTERNET: Enhanced timeout and retry settings
    $maxRetries = 3;
    $retryDelay = 2; // seconds
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for slow connections
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20); // Increased connection timeout
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1); // Enable TCP keepalive
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 120); // Keep connection alive for 2 minutes
        curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 60); // Check connection every minute
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3); // Limit redirects
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL verification for faster connection
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Skip host verification
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate'); // Accept compressed responses
        curl_setopt($ch, CURLOPT_USERAGENT, 'BloodDonorSystem/1.0'); // Add user agent
        
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            return [
                'code' => $httpCode,
                'data' => $decoded
            ];
        }
        
        // If this is not the last attempt, retry
        if ($attempt < $maxRetries) {
            error_log("API attempt $attempt failed for $endpoint. HTTP Code: $httpCode. Error: $error. Retrying in $retryDelay seconds...");
            sleep($retryDelay);
            $retryDelay *= 2; // Exponential backoff
        }
    }
    
    // All attempts failed
    error_log("All API attempts failed for $endpoint. HTTP Code: $httpCode. Error: $error");
    return [
        'code' => $httpCode,
        'data' => null,
        'error' => "Connection error after $maxRetries attempts: $error"
    ];
}

// OPTIMIZATION: Enhanced function to query direct SQL with retry mechanism
function querySQL($table, $select = "*", $filters = null) {
    $maxRetries = 3;
    $retryDelay = 2; // seconds
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Prefer: return=representation'
        ];
        
        $url = SUPABASE_URL . '/rest/v1/' . $table . '?select=' . urlencode($select);
        
        if ($filters) {
            foreach ($filters as $key => $value) {
                $url .= '&' . $key . '=' . urlencode($value);
            }
        }
        
        // OPTIMIZATION FOR SLOW INTERNET: Enhanced timeout and retry settings
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for slow connections
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20); // Increased connection timeout
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1); // Enable TCP keepalive
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 120); // Keep connection alive for 2 minutes
        curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 60); // Check connection every minute
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3); // Limit redirects
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL verification for faster connection
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Skip host verification
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate'); // Accept compressed responses
        curl_setopt($ch, CURLOPT_USERAGENT, 'BloodDonorSystem/1.0'); // Add user agent
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        // If this is not the last attempt, retry
        if ($attempt < $maxRetries) {
            error_log("QuerySQL attempt $attempt failed for $table. HTTP Code: $httpCode. Error: $error. Retrying in $retryDelay seconds...");
            sleep($retryDelay);
            $retryDelay *= 2; // Exponential backoff
        }
    }
    
    // All attempts failed
    error_log("All QuerySQL attempts failed for $table. HTTP Code: $httpCode. Error: $error");
    return ['error' => $error];
}

// OPTIMIZATION: Enhanced performance logging and caching headers
function addPerformanceHeaders($executionTime, $recordCount, $moduleName) {
    // Performance logging with cache metrics
    $cacheStatus = isset($GLOBALS['cache_source']) ? $GLOBALS['cache_source'] : 'none';
    error_log("$moduleName - Records found: $recordCount in " . round($executionTime, 3) . " seconds, cache: $cacheStatus");
    
    // Only add headers if they haven't been sent yet
    if (!headers_sent()) {
        // Dynamic cache headers based on data freshness
        $maxAge = $recordCount > 0 ? 300 : 60; // Longer cache for data, shorter for empty results
        header('Cache-Control: public, max-age=' . $maxAge . ', stale-while-revalidate=60');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $maxAge));
        header('X-Module-Name: ' . $moduleName);
        header('X-Record-Count: ' . $recordCount);
        header('X-Execution-Time: ' . round($executionTime, 3) . 's');
    }
}

// OPTIMIZATION: API request caching to reduce external calls
function cachedSupabaseRequest($endpoint, $method = 'GET', $data = null, $cacheTtl = 300) {
    $cacheKey = 'api_' . md5($endpoint . $method . serialize($data));
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    
    // Check cache first
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                $GLOBALS['cache_source'] = 'api_cache';
                return $data;
            }
        }
    }
    
    // Make API request
    $result = supabaseRequest($endpoint, $method, $data);
    
    // Cache successful responses
    if (isset($result['data']) && is_array($result['data'])) {
        @file_put_contents($cacheFile, json_encode($result));
        $GLOBALS['cache_source'] = 'api_fresh';
    }
    
    return $result;
}
?>
