<?php
/**
 * Test Blood Drive Notification System
 * This will test the complete flow from dashboard to API
 */

echo "<h1>🧪 Blood Drive Notification Test</h1>";

// Test the API endpoint
$test_data = [
    'location' => 'Santa Barbara',
    'drive_date' => '2024-01-15',
    'drive_time' => '09:00',
    'latitude' => 10.7202,
    'longitude' => 122.5621,
    'radius_km' => 15,
    'custom_message' => '🩸 Blood Drive Alert! A blood drive is scheduled in Santa Barbara on 2024-01-15 at 09:00. Your blood type is urgently needed!'
];

echo "<h2>📡 Testing API Endpoint</h2>";
echo "<p><strong>URL:</strong> /RED-CROSS-THESIS/public/api/broadcast-blood-drive.php</p>";
echo "<p><strong>Method:</strong> POST</p>";
echo "<p><strong>Data:</strong></p>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

// Make the API call
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost/RED-CROSS-THESIS/public/api/broadcast-blood-drive.php',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($test_data),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h2>📊 API Response</h2>";
echo "<p><strong>HTTP Code:</strong> $http_code</p>";

if ($error) {
    echo "<p><strong>cURL Error:</strong> $error</p>";
}

if ($response) {
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "<p><strong>Response:</strong></p>";
        echo "<pre>" . json_encode($decoded, JSON_PRETTY_PRINT) . "</pre>";
        
        if ($decoded['success']) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; color: #155724;'>";
            echo "<h3>✅ Success!</h3>";
            echo "<p>Blood drive notification system is working correctly!</p>";
            echo "<ul>";
            echo "<li><strong>Blood Drive ID:</strong> " . $decoded['blood_drive_id'] . "</li>";
            echo "<li><strong>Total Donors Found:</strong> " . $decoded['total_donors_found'] . "</li>";
            echo "<li><strong>Total Subscriptions:</strong> " . $decoded['total_subscriptions'] . "</li>";
            echo "<li><strong>Notifications Sent:</strong> " . $decoded['results']['sent'] . "</li>";
            echo "<li><strong>Notifications Failed:</strong> " . $decoded['results']['failed'] . "</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
            echo "<h3>❌ Error</h3>";
            echo "<p>" . $decoded['message'] . "</p>";
            echo "</div>";
        }
    } else {
        echo "<p><strong>Raw Response:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}

echo "<h2>🎯 Next Steps</h2>";
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
echo "<ol>";
echo "<li><strong>Test the Dashboard:</strong> Go to your GIS dashboard and try clicking the 'Notify Donors' button</li>";
echo "<li><strong>Check Console:</strong> Open browser console (F12) to see debug messages</li>";
echo "<li><strong>Verify Location Clicks:</strong> Make sure clicking locations fills the 'Selected Location' field</li>";
echo "<li><strong>Test Button States:</strong> Buttons should become enabled when all fields are filled</li>";
echo "</ol>";
echo "</div>";

echo "<h2>🔧 If Issues Persist</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<p>If you still get the JSON parsing error:</p>";
echo "<ol>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Verify the API endpoint is accessible</li>";
echo "<li>Make sure all required fields are filled in the form</li>";
echo "<li>Check that the location coordinates are valid</li>";
echo "</ol>";
echo "</div>";
?>


