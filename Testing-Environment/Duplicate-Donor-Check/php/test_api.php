<?php
// test_api.php - Simple test for the duplicate check API

echo "<h2>Testing Duplicate Donor Check API</h2>";
echo "<p><strong>Database:</strong> Supabase PostgreSQL (Direct API Connection)</p>";

// Test data - Using exact data from your database (donor_id 119)
$testData = [
    'surname' => 'Ling',
    'first_name' => 'Ching', 
    'middle_name' => 'Chong',
    'birthdate' => '2001-05-05'
];

echo "<h3>Test Data:</h3>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Make a request to the API
$url = 'http://localhost/REDCROSS/assets/php_func/check_duplicate_donor.php';
$jsonData = json_encode($testData);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

echo "<h3>Making API Request...</h3>";
echo "URL: " . $url . "<br>";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "<h3>Results:</h3>";
echo "HTTP Code: " . $httpCode . "<br>";

if ($error) {
    echo "<strong>CURL Error:</strong> " . $error . "<br>";
} else {
    echo "<strong>Response:</strong><br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Try to decode JSON
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "<h4>Parsed Response:</h4>";
        echo "<pre>" . json_encode($decoded, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($decoded['duplicate_found'])) {
            if ($decoded['duplicate_found']) {
                echo "<div style='color: orange;'><strong>✓ DUPLICATE FOUND!</strong></div>";
                if (isset($decoded['data'])) {
                    echo "<p>Duplicate donor: " . htmlspecialchars($decoded['data']['full_name']) . "</p>";
                }
            } else {
                echo "<div style='color: green;'><strong>✓ NO DUPLICATE FOUND</strong></div>";
            }
        }
    } else {
        echo "<div style='color: red;'><strong>Failed to parse JSON response</strong></div>";
    }
}

echo "<hr>";
echo "<h3>Test Instructions:</h3>";
echo "<ol>";
echo "<li>Access this file at: <code>http://localhost/REDCROSS/test_api.php</code></li>";
echo "<li>Check if the API returns proper results</li>";
echo "<li>If successful, the duplicate checker should work in the form</li>";
echo "</ol>";
?> 