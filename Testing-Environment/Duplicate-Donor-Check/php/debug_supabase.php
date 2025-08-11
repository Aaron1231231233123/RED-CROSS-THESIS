<?php
// debug_supabase.php - Test Supabase API connectivity

// Supabase Configuration
define("SUPABASE_URL", "https://nwakbxwglhxcpunrzstf.supabase.co");
define("SUPABASE_API_KEY", "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im53YWtieHdnbGh4Y3B1bnJ6c3RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDIyODA1NzIsImV4cCI6MjA1Nzg1NjU3Mn0.y4CIbDT2UQf2ieJTQukuJRRzspSZPehSgNKivBwpvc4");

echo "<h2>Supabase API Debugging</h2>";

// Test 1: Simple query to get all donors (limit 5)
echo "<h3>Test 1: Basic donor_form query</h3>";
$url1 = SUPABASE_URL . "/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate&limit=5";
echo "URL: " . $url1 . "<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: " . SUPABASE_API_KEY,
    "Authorization: Bearer " . SUPABASE_API_KEY,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response1 = curl_exec($ch);
$http_code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $http_code1 . "<br>";
echo "Response: <pre>" . htmlspecialchars($response1) . "</pre>";

if ($http_code1 === 200) {
    $data1 = json_decode($response1, true);
    if ($data1) {
        echo "<strong>✓ SUCCESS!</strong> Found " . count($data1) . " donors<br>";
        if (!empty($data1)) {
            echo "Sample donor: " . $data1[0]['surname'] . ", " . $data1[0]['first_name'] . "<br>";
        }
    }
} else {
    echo "<strong>✗ FAILED!</strong><br>";
}

echo "<hr>";

// Test 2: Search query with filters
echo "<h3>Test 2: Search for specific donor</h3>";
$surname = "Noca";
$first_name = "Eldrich";
$birthdate = "2005-01-06";

$url2 = SUPABASE_URL . "/rest/v1/donor_form?select=donor_id,surname,first_name,middle_name,birthdate&surname=eq." . urlencode($surname) . "&first_name=eq." . urlencode($first_name) . "&birthdate=eq." . urlencode($birthdate);
echo "URL: " . $url2 . "<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url2);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: " . SUPABASE_API_KEY,
    "Authorization: Bearer " . SUPABASE_API_KEY,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response2 = curl_exec($ch);
$http_code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $http_code2 . "<br>";
echo "Response: <pre>" . htmlspecialchars($response2) . "</pre>";

if ($http_code2 === 200) {
    $data2 = json_decode($response2, true);
    if ($data2) {
        echo "<strong>✓ SUCCESS!</strong> Found " . count($data2) . " matching donors<br>";
        if (!empty($data2)) {
            foreach ($data2 as $donor) {
                echo "Found: " . $donor['surname'] . ", " . $donor['first_name'] . " (" . $donor['birthdate'] . ")<br>";
            }
        }
    }
} else {
    echo "<strong>✗ FAILED!</strong><br>";
}

echo "<hr>";

// Test 3: Check eligibility table
echo "<h3>Test 3: Check eligibility table</h3>";
$url3 = SUPABASE_URL . "/rest/v1/eligibility?select=eligibility_id,donor_id,status&limit=5";
echo "URL: " . $url3 . "<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url3);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: " . SUPABASE_API_KEY,
    "Authorization: Bearer " . SUPABASE_API_KEY,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response3 = curl_exec($ch);
$http_code3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $http_code3 . "<br>";
echo "Response: <pre>" . htmlspecialchars($response3) . "</pre>";

if ($http_code3 === 200) {
    $data3 = json_decode($response3, true);
    if ($data3) {
        echo "<strong>✓ SUCCESS!</strong> Found " . count($data3) . " eligibility records<br>";
    }
} else {
    echo "<strong>✗ FAILED!</strong><br>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If Test 1 succeeds: Basic API connectivity works</li>";
echo "<li>If Test 2 succeeds: Query filtering works</li>";
echo "<li>If Test 3 succeeds: Eligibility table access works</li>";
echo "<li>If all succeed: The duplicate checker should work properly</li>";
echo "</ul>";
?> 