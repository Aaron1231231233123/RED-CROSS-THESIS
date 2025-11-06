<?php
/**
 * Diagnose Database Structure
 * This script checks the exact structure of your Supabase tables
 */

require_once 'assets/conn/db_conn.php';

echo "<h1>🔍 Database Structure Diagnosis</h1>";

// Enhanced function to make Supabase requests with better error handling
function diagnosticSupabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;
    
    $headers = [
        "Content-Type: application/json",
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Prefer: return=representation"
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error,
        'decoded' => json_decode($response, true)
    ];
}

// Test 1: Check table structure
echo "<h2>📊 Table Structure Analysis</h2>";

$tables_to_check = [
    'donor_form',
    'push_subscriptions',
    'donor_notifications',
    'blood_drive_notifications'
];

foreach ($tables_to_check as $table) {
    echo "<h3>Table: $table</h3>";
    
    // Try to get table info
    $result = diagnosticSupabaseRequest("$table?select=*&limit=1");
    
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>HTTP Code:</strong> " . $result['http_code'] . "<br>";
    
    if ($result['http_code'] == 200) {
        echo "<span style='color: green;'>✅ Table accessible</span><br>";
        if (isset($result['decoded']) && !empty($result['decoded'])) {
            echo "<strong>Sample record fields:</strong><br>";
            $sample_record = $result['decoded'][0];
            foreach (array_keys($sample_record) as $field) {
                echo "• $field<br>";
            }
        } else {
            echo "<strong>Table is empty</strong><br>";
        }
    } else {
        echo "<span style='color: red;'>❌ Table access issue</span><br>";
        echo "<strong>Error:</strong> " . $result['response'] . "<br>";
    }
    echo "</div>";
}

// Test 2: Try to get table schema information
echo "<h2>🔍 Table Schema Information</h2>";

// Try to get information about donor_form table structure
$schema_result = diagnosticSupabaseRequest("donor_form?select=*&limit=0");
echo "<h3>Donor Form Table Schema</h3>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 8px;'>";
echo "<strong>Response:</strong> " . $schema_result['response'] . "<br>";
echo "<strong>HTTP Code:</strong> " . $schema_result['http_code'] . "<br>";
echo "</div>";

// Test 3: Try minimal donor creation
echo "<h2>🧪 Minimal Donor Creation Test</h2>";

$minimal_donor = [
    'full_name' => 'Test User',
    'mobile' => '09123456789',
    'permanent_address' => 'Test Address',
    'blood_type' => 'O+',
    'age' => 25,
    'sex' => 'Male'
];

echo "<h3>Testing with minimal required fields:</h3>";
echo "<pre>" . json_encode($minimal_donor, JSON_PRETTY_PRINT) . "</pre>";

$create_result = diagnosticSupabaseRequest("donor_form", "POST", $minimal_donor);

echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>HTTP Code:</strong> " . $create_result['http_code'] . "<br>";
echo "<strong>Response:</strong> " . $create_result['response'] . "<br>";
if ($create_result['error']) {
    echo "<strong>cURL Error:</strong> " . $create_result['error'] . "<br>";
}
echo "</div>";

// Test 4: Check if there are any existing donors with different structure
echo "<h2>🔍 Check Existing Data Structure</h2>";

$existing_result = diagnosticSupabaseRequest("donor_form?select=*&limit=5");
if ($existing_result['http_code'] == 200 && isset($existing_result['decoded']) && !empty($existing_result['decoded'])) {
    echo "<h3>Existing Donor Structure:</h3>";
    $first_donor = $existing_result['decoded'][0];
    echo "<pre>" . json_encode($first_donor, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<p>No existing donors found to analyze structure.</p>";
}

// Test 5: Try different field combinations
echo "<h2>🧪 Field Combination Tests</h2>";

$test_combinations = [
    [
        'name' => 'Basic fields only',
        'data' => [
            'full_name' => 'Test User 1',
            'mobile' => '09123456789'
        ]
    ],
    [
        'name' => 'With address',
        'data' => [
            'full_name' => 'Test User 2',
            'mobile' => '09123456790',
            'permanent_address' => 'Test Address'
        ]
    ],
    [
        'name' => 'With blood type',
        'data' => [
            'full_name' => 'Test User 3',
            'mobile' => '09123456791',
            'permanent_address' => 'Test Address',
            'blood_type' => 'O+'
        ]
    ]
];

foreach ($test_combinations as $test) {
    echo "<h3>Test: {$test['name']}</h3>";
    echo "<pre>" . json_encode($test['data'], JSON_PRETTY_PRINT) . "</pre>";
    
    $result = diagnosticSupabaseRequest("donor_form", "POST", $test['data']);
    
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>HTTP Code:</strong> " . $result['http_code'] . "<br>";
    echo "<strong>Response:</strong> " . $result['response'] . "<br>";
    
    if ($result['http_code'] == 201 || $result['http_code'] == 200) {
        echo "<span style='color: green;'>✅ Success!</span><br>";
    } else {
        echo "<span style='color: red;'>❌ Failed</span><br>";
    }
    echo "</div>";
}

echo "<h2>📋 Recommendations</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
echo "<h3>Based on the test results above:</h3>";
echo "<ol>";
echo "<li><strong>Check the exact field names</strong> in your Supabase donor_form table</li>";
echo "<li><strong>Verify required fields</strong> - some fields might be mandatory</li>";
echo "<li><strong>Check for triggers or constraints</strong> that might be blocking inserts</li>";
echo "<li><strong>Use the working field combination</strong> from the tests above</li>";
echo "</ol>";
echo "</div>";

echo "<h2>🔧 Quick Fix</h2>";
echo "<p>If you can see the exact field names from the tests above, update the donor creation code to match your table structure.</p>";
?>



