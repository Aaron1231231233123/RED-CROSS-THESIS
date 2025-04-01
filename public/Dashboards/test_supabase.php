<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

echo "<h1>Supabase Connection Test</h1>";

// Display Supabase configuration (partial key for security)
echo "<h2>Configuration:</h2>";
echo "<p>Supabase URL: " . SUPABASE_URL . "</p>";
echo "<p>API Key (first 10 chars): " . substr(SUPABASE_API_KEY, 0, 10) . "...</p>";

// Test 1: Simple test with just a count query
echo "<h2>Test 1: Simple Count Query</h2>";
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?select=count",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY
    ],
]);
$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);
curl_close($curl);

echo "<p>HTTP Code: " . $http_code . "</p>";
if ($err) {
    echo "<p>Error: " . $err . "</p>";
} else {
    echo "<p>Response: " . htmlspecialchars($response) . "</p>";
}

// Test 2: Try to get table structure
echo "<h2>Test 2: Table Structure</h2>";
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?limit=0",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY
    ],
]);
$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);
curl_close($curl);

echo "<p>HTTP Code: " . $http_code . "</p>";
if ($err) {
    echo "<p>Error: " . $err . "</p>";
} else {
    echo "<p>Response: " . htmlspecialchars(substr($response, 0, 200)) . "...</p>";
}

// Test 3: Try without the select parameter
echo "<h2>Test 3: Basic Query Without Select</h2>";
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?limit=1",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY
    ],
]);
$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);
curl_close($curl);

echo "<p>HTTP Code: " . $http_code . "</p>";
if ($err) {
    echo "<p>Error: " . $err . "</p>";
} else {
    echo "<p>Response: " . htmlspecialchars(substr($response, 0, 200)) . "...</p>";
}

// Test 4: Check if table exists by listing all tables
echo "<h2>Test 4: List All Tables</h2>";
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => SUPABASE_URL . "/rest/v1/",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY
    ],
]);
$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);
curl_close($curl);

echo "<p>HTTP Code: " . $http_code . "</p>";
if ($err) {
    echo "<p>Error: " . $err . "</p>";
} else {
    echo "<p>Response: " . htmlspecialchars(substr($response, 0, 500)) . "...</p>";
}

echo "<h2>Now try this corrected URL:</h2>";
echo "<p>Go to your dashboard and look at the 'donation_pending.php' file and check the logs to see errors.</p>";
echo "<p>Then update the file to use the correct table name.</p>";
?> 