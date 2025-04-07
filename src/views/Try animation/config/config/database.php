<?php
// File: config/database.php

// Supabase API credentials
define('SUPABASE_URL', 'https://nwakbxwglhxcpunrzstf.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im53YWtieHdnbGh4Y3B1bnJ6c3RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDIyODA1NzIsImV4cCI6MjA1Nzg1NjU3Mn0.y4CIbDT2UQf2ieJTQukuJRRzspSZPehSgNKivBwpvc4');

// Function to create a cURL request to Supabase
function createSupabaseRequest($endpoint, $method = 'GET', $data = null, $token = null) {
    $url = SUPABASE_URL . $endpoint;
    $curl = curl_init();
    $headers = ['Content-Type: application/json', 'apikey: ' . SUPABASE_KEY];
    
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    
    if ($data !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        throw new Exception("cURL Error: " . $error);
    }
    
    return json_decode($response, true);
}

// Function to fetch user role
function fetchUserRole($userId, $accessToken) {
    try {
        $endpoint = "/rest/v1/users?select=role_id&user_id=eq." . $userId;
        $response = createSupabaseRequest($endpoint, 'GET', null, $accessToken);
        
        error_log('Supabase Response: ' . print_r($response, true));
        
        if (!empty($response) && isset($response[0]['role_id'])) {
            return (int)$response[0]['role_id'];
        }
        
        return null;
    } catch (Exception $e) {
        error_log('fetchUserRole Error: ' . $e->getMessage());
        return null;
    }
}

// Function to authenticate user
function authenticateUser($email, $password) {
    try {
        $data = ['email' => $email, 'password' => $password];
        $response = createSupabaseRequest('/auth/v1/token?grant_type=password', 'POST', $data);
        
        if (isset($response['access_token'])) {
            $userId = $response['user']['id'];
            $accessToken = $response['access_token'];
            
            $roleId = fetchUserRole($userId, $accessToken);
            
            if ($roleId === null) {
                return ['success' => false, 'message' => 'Role information not found'];
            }
            
            return [
                'success' => true,
                'user' => $response['user'],
                'access_token' => $accessToken,
                'refresh_token' => $response['refresh_token'],
                'role_id' => $roleId
            ];
        }
        
        return ['success' => false, 'message' => $response['error_description'] ?? 'Authentication failed'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to redirect users
function redirectToDashboard($roleId) {
    $dashboards = [
        1 => 'dashboard-Inventory-System.php',
        2 => 'dashboard-staff-bootstrap.php',
        3 => 'dashboard-hospital-bootstrap.php',
        4 => '/donor/dashboard.php'
    ];
    
    header("Location: " . ($dashboards[$roleId] ?? 'index.php?error=invalid_role'));
    exit();
}
