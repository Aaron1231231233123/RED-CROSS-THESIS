<?php
// File: config/database.php

// Supabase API credentials
define('SUPABASE_URL', 'https://bsxbrvxhjslsaeizdxsr.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJzeGJydnhoanNsc2FlaXpkeHNyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Mzg0MTIzMzksImV4cCI6MjA1Mzk4ODMzOX0.Yy1FGkU6IVTjFbBPWobQ3S5XdMFtH2v28O57O7mZGSM');

// Function to create a cURL request to Supabase
function createSupabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . $endpoint;
    
    // Initialize cURL
    $curl = curl_init();
    
    // Set cURL options
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json'
        ],
    ]);
    
    // Add request body if data is provided
    if ($data !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    // Execute the request
    $response = curl_exec($curl);
    $error = curl_error($curl);
    
    // Close cURL
    curl_close($curl);
    
    // Handle errors
    if ($error) {
        throw new Exception("cURL Error: " . $error);
    }
    
    // Return the response
    return json_decode($response, true);
}

// Function to authenticate a user with Supabase
function authenticateUser($email, $password) {
    try {
        $data = [
            'email' => $email,
            'password' => $password
        ];
        
        // Use Supabase Auth API endpoint
        $response = createSupabaseRequest('/auth/v1/token?grant_type=password', 'POST', $data);
        
        // Check if authentication was successful (response contains access_token)
        if (isset($response['access_token'])) {
            return [
                'success' => true,
                'user' => $response['user'],
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token']
            ];
        } else {
            // Authentication failed
            return [
                'success' => false,
                'message' => isset($response['error_description']) 
                    ? $response['error_description'] 
                    : 'Authentication failed'
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}