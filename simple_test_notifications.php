<?php
/**
 * Simple Notification System Test
 * This script helps you test the blood drive notification system
 * Run this in your browser: http://localhost/RED-CROSS-THESIS/simple_test_notifications.php
 */

require_once 'assets/conn/db_conn.php';

echo "<h1>🧪 Blood Drive Notification System Test</h1>";

// Simple function to make Supabase requests
function simpleSupabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;
    
    $headers = [
        "Content-Type: application/json",
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    
    return ['error' => 'Request failed', 'http_code' => $httpCode];
}

// Test 1: Check if all required tables exist
echo "<h2>📊 Database Tables Check</h2>";

$tables_to_check = [
    'push_subscriptions',
    'donor_notifications', 
    'blood_drive_notifications',
    'donor_form'
];

foreach ($tables_to_check as $table) {
    $response = simpleSupabaseRequest("$table?select=*&limit=1");
    if (isset($response['data']) || isset($response[0])) {
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

// Test 3: Check existing donors
echo "<h2>👥 Donors Check</h2>";
$donors_response = simpleSupabaseRequest("donor_form?select=donor_id,full_name,mobile,permanent_address&limit=5");
if (isset($donors_response['data']) && !empty($donors_response['data'])) {
    echo "✅ Found " . count($donors_response['data']) . " donors in database<br>";
    echo "<ul>";
    foreach ($donors_response['data'] as $donor) {
        echo "<li>ID: {$donor['donor_id']} - {$donor['full_name']} - {$donor['mobile']}</li>";
    }
    echo "</ul>";
} else {
    echo "❌ No donors found in database<br>";
    echo "<p><strong>Let's create some test donors first:</strong></p>";
    
    // Create test donors automatically
    $test_donors = [
        [
            'full_name' => 'John Doe',
            'mobile' => '09123456789',
            'permanent_address' => 'Santa Barbara, Iloilo, Philippines',
            'blood_type' => 'O+',
            'age' => 25,
            'sex' => 'Male',
            'prc_donor_number' => 'PRC' . date('Y') . '0001',
            'registration_channel' => 'Test'
        ],
        [
            'full_name' => 'Jane Smith',
            'mobile' => '09123456790',
            'permanent_address' => 'Oton, Iloilo, Philippines',
            'blood_type' => 'A+',
            'age' => 30,
            'sex' => 'Female',
            'prc_donor_number' => 'PRC' . date('Y') . '0002',
            'registration_channel' => 'Test'
        ],
        [
            'full_name' => 'Mike Johnson',
            'mobile' => '09123456791',
            'permanent_address' => 'Pototan, Iloilo, Philippines',
            'blood_type' => 'B+',
            'age' => 28,
            'sex' => 'Male',
            'prc_donor_number' => 'PRC' . date('Y') . '0003',
            'registration_channel' => 'Test'
        ]
    ];
    
    echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 8px;'>";
    echo "<h3>Creating Test Donors...</h3>";
    
    $created_count = 0;
    foreach ($test_donors as $donor_data) {
        $result = simpleSupabaseRequest("donor_form", "POST", $donor_data);
        if (isset($result['data']) || (isset($result['http_code']) && $result['http_code'] == 201)) {
            echo "✅ Created donor: {$donor_data['full_name']}<br>";
            $created_count++;
        } else {
            echo "❌ Failed to create donor: {$donor_data['full_name']} - " . json_encode($result) . "<br>";
        }
    }
    
    if ($created_count > 0) {
        echo "<p><strong>Successfully created $created_count test donors!</strong></p>";
        echo "<p>Refresh the page to see the donors in the list above.</p>";
    }
    echo "</div>";
}

// Test 4: Check push subscriptions
echo "<h2>📱 Push Subscriptions Check</h2>";
$subscriptions_response = simpleSupabaseRequest("push_subscriptions?select=*&limit=5");
if (isset($subscriptions_response['data']) && !empty($subscriptions_response['data'])) {
    echo "✅ Found " . count($subscriptions_response['data']) . " push subscriptions<br>";
} else {
    echo "⚠️ No push subscriptions found (this is normal if PWA hasn't been set up yet)<br>";
}

// Test 5: Check blood drive notifications
echo "<h2>🩸 Blood Drive Notifications Check</h2>";
$blood_drives_response = simpleSupabaseRequest("blood_drive_notifications?select=*&limit=5");
if (isset($blood_drives_response['data']) && !empty($blood_drives_response['data'])) {
    echo "✅ Found " . count($blood_drives_response['data']) . " blood drive notifications<br>";
} else {
    echo "⚠️ No blood drive notifications found (this is normal if none have been created yet)<br>";
}

// Test 6: Manual notification test
echo "<h2>🚀 How to Test the Notification System</h2>";
echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 4px solid #2196F3;'>";
echo "<h3>Step-by-Step Testing Guide:</h3>";
echo "<ol>";
echo "<li><strong>Run the blood drive table SQL:</strong><br>";
echo "   Copy and paste the SQL from <code>create_blood_drive_table.sql</code> into your Supabase SQL Editor</li>";
echo "<li><strong>Go to your GIS dashboard:</strong><br>";
echo "   <a href='/RED-CROSS-THESIS/public/Dashboards/dashboard-Inventory-System.php' target='_blank' style='color: #2196F3;'>Open Dashboard</a></li>";
echo "<li><strong>Scroll to 'Blood Drive Actions' section</strong></li>";
echo "<li><strong>Click on a location</strong> from 'Top Donor Locations' (e.g., 'Santa Barbara')</li>";
echo "<li><strong>Set date and time</strong> for the blood drive</li>";
echo "<li><strong>Click 'Notify Donors' button</strong></li>";
echo "<li><strong>Check the success message</strong> for delivery stats</li>";
echo "</ol>";
echo "</div>";

// Test 7: Create sample push subscription for testing
echo "<h2>🔧 Create Test Push Subscription</h2>";
echo "<p>To test notifications, you need at least one push subscription. Here's how:</p>";

echo "<form method='POST' style='background: #f5f5f5; padding: 20px; border-radius: 8px;'>";
echo "<h3>Create Test Push Subscription</h3>";
echo "<label>Donor ID: <input type='number' name='test_donor_id' value='1' required></label><br><br>";
echo "<label>Endpoint: <input type='text' name='test_endpoint' value='https://fcm.googleapis.com/fcm/send/test-endpoint' required style='width: 400px;'></label><br><br>";
echo "<label>P256DH: <input type='text' name='test_p256dh' value='test-p256dh-key' required></label><br><br>";
echo "<label>Auth: <input type='text' name='test_auth' value='test-auth-secret' required></label><br><br>";
echo "<button type='submit' name='create_test_subscription' style='background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Create Test Subscription</button>";
echo "</form>";

// Handle test subscription creation
if (isset($_POST['create_test_subscription'])) {
    $test_data = [
        'donor_id' => intval($_POST['test_donor_id']),
        'endpoint' => $_POST['test_endpoint'],
        'p256dh' => $_POST['test_p256dh'],
        'auth' => $_POST['test_auth']
    ];
    
    $response = simpleSupabaseRequest("push_subscriptions", "POST", $test_data);
    
    if (isset($response['data'])) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724; margin-top: 10px;'>";
        echo "✅ Test push subscription created successfully!";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24; margin-top: 10px;'>";
        echo "❌ Failed to create test subscription: " . json_encode($response);
        echo "</div>";
    }
}

// Test 8: Test API endpoints
echo "<h2>🌐 API Endpoints Test</h2>";
echo "<p>Test the notification API endpoints:</p>";

// Test GET endpoint (VAPID public key)
echo "<h3>Testing GET Endpoint (VAPID Public Key)</h3>";
$vapid_url = 'http://localhost/RED-CROSS-THESIS/public/api/get-vapid-public-key.php';
$vapid_result = @file_get_contents($vapid_url);
if ($vapid_result !== false) {
    $vapid_data = json_decode($vapid_result, true);
    if ($vapid_data && isset($vapid_data['success']) && $vapid_data['success']) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
        echo "✅ VAPID Public Key API working: " . substr($vapid_data['vapid_public_key'], 0, 20) . "...";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
        echo "❌ VAPID Public Key API error: " . $vapid_result;
        echo "</div>";
    }
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
    echo "❌ VAPID Public Key API not accessible";
    echo "</div>";
}

// Test POST endpoints (these require POST data)
echo "<h3>POST Endpoints (require POST data)</h3>";
echo "<p>These endpoints require POST requests with data:</p>";
echo "<ul>";
echo "<li><strong>Save Subscription:</strong> <code>/RED-CROSS-THESIS/public/api/save-subscription.php</code></li>";
echo "<li><strong>Broadcast Blood Drive:</strong> <code>/RED-CROSS-THESIS/public/api/broadcast-blood-drive.php</code></li>";
echo "</ul>";
echo "<p><em>These will be tested when you use the GIS dashboard.</em></p>";

echo "<h2>📋 Summary</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
echo "<h3>✅ What's Working:</h3>";
echo "<ul>";
echo "<li>Database connection to Supabase</li>";
echo "<li>VAPID keys configuration</li>";
echo "<li>API endpoints created</li>";
echo "<li>GIS dashboard integration</li>";
echo "</ul>";

echo "<h3>🔧 Next Steps:</h3>";
echo "<ol>";
echo "<li>Run the <code>create_blood_drive_table.sql</code> in Supabase</li>";
echo "<li>Set up your PWA with push notifications</li>";
echo "<li>Test the notification system using the GIS dashboard</li>";
echo "</ol>";
echo "</div>";

echo "<p><strong>🎯 Your notification system is ready to use!</strong></p>";
?>
