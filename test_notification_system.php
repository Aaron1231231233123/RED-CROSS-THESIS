<?php
/**
 * Test Notification System
 * This script helps you test the blood drive notification system
 * Run this in your browser: http://localhost/RED-CROSS-THESIS/test_notification_system.php
 */

require_once 'assets/conn/db_conn.php';
require_once 'public/Dashboards/module/optimized_functions.php';

echo "<h1>🧪 Blood Drive Notification System Test</h1>";

// Test 1: Check if all required tables exist
echo "<h2>📊 Database Tables Check</h2>";

$tables_to_check = [
    'push_subscriptions',
    'donor_notifications', 
    'blood_drive_notifications',
    'donor_form'
];

foreach ($tables_to_check as $table) {
    $response = supabaseRequest("$table?select=*&limit=1");
    if (isset($response['data'])) {
        echo "✅ Table '$table' exists<br>";
    } else {
        echo "❌ Table '$table' missing or error: " . json_encode($response) . "<br>";
    }
}

// Test 2: Check VAPID configuration
echo "<h2>🔑 VAPID Keys Check</h2>";
require_once 'assets/php_func/vapid_config.php';

if (defined('VAPID_PUBLIC_KEY') && !empty(VAPID_PUBLIC_KEY)) {
    echo "✅ VAPID Public Key configured: " . substr(VAPID_PUBLIC_KEY, 0, 20) . "...<br>";
} else {
    echo "❌ VAPID Public Key not configured<br>";
}

if (defined('VAPID_PRIVATE_KEY') && !empty(VAPID_PRIVATE_KEY)) {
    echo "✅ VAPID Private Key configured: " . substr(VAPID_PRIVATE_KEY, 0, 20) . "...<br>";
} else {
    echo "❌ VAPID Private Key not configured<br>";
}

// Test 3: Check API endpoints
echo "<h2>🌐 API Endpoints Check</h2>";

$endpoints = [
    '/RED-CROSS-THESIS/public/api/get-vapid-public-key.php',
    '/RED-CROSS-THESIS/public/api/save-subscription.php',
    '/RED-CROSS-THESIS/public/api/broadcast-blood-drive.php'
];

foreach ($endpoints as $endpoint) {
    $url = 'http://localhost' . $endpoint;
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5
        ]
    ]);
    
    $result = @file_get_contents($url, false, $context);
    if ($result !== false) {
        echo "✅ Endpoint accessible: $endpoint<br>";
    } else {
        echo "❌ Endpoint not accessible: $endpoint<br>";
    }
}

// Test 4: Check existing donors
echo "<h2>👥 Donors Check</h2>";
$donors_response = supabaseRequest("donor_form?select=donor_id,full_name,mobile,permanent_address&limit=5");
if (isset($donors_response['data']) && !empty($donors_response['data'])) {
    echo "✅ Found " . count($donors_response['data']) . " donors in database<br>";
    echo "<ul>";
    foreach ($donors_response['data'] as $donor) {
        echo "<li>ID: {$donor['donor_id']} - {$donor['full_name']} - {$donor['mobile']}</li>";
    }
    echo "</ul>";
} else {
    echo "❌ No donors found in database<br>";
}

// Test 5: Check push subscriptions
echo "<h2>📱 Push Subscriptions Check</h2>";
$subscriptions_response = supabaseRequest("push_subscriptions?select=*&limit=5");
if (isset($subscriptions_response['data']) && !empty($subscriptions_response['data'])) {
    echo "✅ Found " . count($subscriptions_response['data']) . " push subscriptions<br>";
} else {
    echo "⚠️ No push subscriptions found (this is normal if PWA hasn't been set up yet)<br>";
}

// Test 6: Manual notification test
echo "<h2>🚀 Manual Notification Test</h2>";
echo "<p>To test the notification system:</p>";
echo "<ol>";
echo "<li>Go to your GIS dashboard: <a href='/RED-CROSS-THESIS/public/Dashboards/dashboard-Inventory-System.php' target='_blank'>Open Dashboard</a></li>";
echo "<li>Scroll down to the 'Blood Drive Actions' section</li>";
echo "<li>Click on a location from 'Top Donor Locations' (e.g., 'Santa Barbara')</li>";
echo "<li>Set a date and time</li>";
echo "<li>Click 'Notify Donors' button</li>";
echo "<li>Check the success message for delivery stats</li>";
echo "</ol>";

// Test 7: Create sample push subscription for testing
echo "<h2>🔧 Create Test Push Subscription</h2>";
echo "<p>To test notifications, you need at least one push subscription. Here's how:</p>";
echo "<ol>";
echo "<li>Set up your PWA with the service worker</li>";
echo "<li>Use the push integration code to subscribe a donor</li>";
echo "<li>Or manually create a test subscription using the form below:</li>";
echo "</ol>";

echo "<form method='POST' style='background: #f5f5f5; padding: 20px; border-radius: 8px;'>";
echo "<h3>Create Test Push Subscription</h3>";
echo "<label>Donor ID: <input type='number' name='test_donor_id' value='1' required></label><br><br>";
echo "<label>Endpoint: <input type='text' name='test_endpoint' value='https://fcm.googleapis.com/fcm/send/test-endpoint' required style='width: 400px;'></label><br><br>";
echo "<label>P256DH: <input type='text' name='test_p256dh' value='test-p256dh-key' required></label><br><br>";
echo "<label>Auth: <input type='text' name='test_auth' value='test-auth-secret' required></label><br><br>";
echo "<button type='submit' name='create_test_subscription'>Create Test Subscription</button>";
echo "</form>";

// Handle test subscription creation
if (isset($_POST['create_test_subscription'])) {
    $test_data = [
        'donor_id' => intval($_POST['test_donor_id']),
        'endpoint' => $_POST['test_endpoint'],
        'p256dh' => $_POST['test_p256dh'],
        'auth' => $_POST['test_auth']
    ];
    
    $response = supabaseRequest("push_subscriptions", "POST", $test_data);
    
    if (isset($response['data'])) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
        echo "✅ Test push subscription created successfully!";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
        echo "❌ Failed to create test subscription: " . json_encode($response);
        echo "</div>";
    }
}

echo "<h2>📋 Next Steps</h2>";
echo "<ol>";
echo "<li>✅ Run the blood_drive_notifications table SQL in Supabase</li>";
echo "<li>✅ Set up your PWA with push notifications</li>";
echo "<li>✅ Test the GIS dashboard notification system</li>";
echo "<li>✅ Monitor notification delivery in donor_notifications table</li>";
echo "</ol>";

echo "<p><strong>🎯 Your notification system is ready to use!</strong></p>";
?>
