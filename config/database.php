<?php
// Supabase API configuration
define('SUPABASE_URL', 'https://your-project-url.supabase.co');  // Replace with your Supabase project URL
define('SUPABASE_API_KEY', 'your-api-key');  // Replace with your Supabase API key

// Function to make requests to Supabase
function supabaseRequest($endpoint, $method = "GET", $data = null) {
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;
    $ch = curl_init();

    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'  // Use return=representation if you need the response data
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === "POST" || $method === "PATCH") {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    // Enable error logging
    error_log("Supabase Request to: " . $url);
    error_log("Method: " . $method);
    if ($data) {
        error_log("Data: " . json_encode($data));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Log the response
    error_log("Supabase Response Code: " . $http_code);
    error_log("Supabase Response: " . $response);

    if (curl_errno($ch)) {
        error_log("Curl Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // For GET requests, decode the JSON response
    if ($method === "GET") {
        return json_decode($response, true);
    }

    // For POST/PATCH, return true if successful (2xx status code)
    return $http_code >= 200 && $http_code < 300;
} 