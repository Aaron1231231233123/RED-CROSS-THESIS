<?php
/**
 * Fix Database Access Issues
 * This script helps diagnose and fix database access problems
 */

require_once 'assets/conn/db_conn.php';

echo "<h1>🔧 Database Access Fix</h1>";

// Test with service role (bypasses RLS)
function testSupabaseWithServiceRole($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;
    
    // Use service role key for admin access
    $headers = [
        "Content-Type: application/json",
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "apikey: " . SUPABASE_API_KEY  // Add service role key if you have one
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
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error,
        'decoded' => json_decode($response, true)
    ];
}

echo "<h2>🔍 Detailed Table Access Test</h2>";

$tables_to_test = [
    'push_subscriptions',
    'donor_notifications', 
    'blood_drive_notifications',
    'donor_form'
];

foreach ($tables_to_test as $table) {
    echo "<h3>Testing table: $table</h3>";
    
    $result = testSupabaseWithServiceRole("$table?select=*&limit=1");
    
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>HTTP Code:</strong> " . $result['http_code'] . "<br>";
    echo "<strong>Response:</strong> " . substr($result['response'], 0, 200) . "...<br>";
    
    if ($result['http_code'] == 200) {
        echo "<span style='color: green;'>✅ Table accessible</span><br>";
    } else {
        echo "<span style='color: red;'>❌ Table access issue</span><br>";
        if ($result['error']) {
            echo "<strong>cURL Error:</strong> " . $result['error'] . "<br>";
        }
    }
    echo "</div>";
}

echo "<h2>👥 Create Test Donors</h2>";
echo "<p>Since you have no donors, let's create some test donors:</p>";

echo "<form method='POST' style='background: #e7f3ff; padding: 20px; border-radius: 8px;'>";
echo "<h3>Create Test Donor</h3>";
echo "<label>Full Name: <input type='text' name='full_name' value='John Doe' required></label><br><br>";
echo "<label>Mobile: <input type='text' name='mobile' value='09123456789' required></label><br><br>";
echo "<label>Address: <input type='text' name='address' value='Santa Barbara, Iloilo, Philippines' required style='width: 300px;'></label><br><br>";
echo "<label>Blood Type: <select name='blood_type' required>";
echo "<option value='O+'>O+</option>";
echo "<option value='A+'>A+</option>";
echo "<option value='B+'>B+</option>";
echo "<option value='AB+'>AB+</option>";
echo "<option value='O-'>O-</option>";
echo "<option value='A-'>A-</option>";
echo "<option value='B-'>B-</option>";
echo "<option value='AB-'>AB-</option>";
echo "</select></label><br><br>";
echo "<label>Age: <input type='number' name='age' value='25' required></label><br><br>";
echo "<label>Gender: <select name='gender' required>";
echo "<option value='Male'>Male</option>";
echo "<option value='Female'>Female</option>";
echo "</select></label><br><br>";
echo "<button type='submit' name='create_donor' style='background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Create Test Donor</button>";
echo "</form>";

// Handle donor creation
if (isset($_POST['create_donor'])) {
    $donor_data = [
        'full_name' => $_POST['full_name'],
        'mobile' => $_POST['mobile'],
        'permanent_address' => $_POST['address'],
        'blood_type' => $_POST['blood_type'],
        'age' => intval($_POST['age']),
        'sex' => $_POST['gender'],
        'prc_donor_number' => 'PRC' . date('Y') . rand(1000, 9999),
        'registration_channel' => 'Test'
    ];
    
    $result = testSupabaseWithServiceRole("donor_form", "POST", $donor_data);
    
    if ($result['http_code'] == 201 || $result['http_code'] == 200) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724; margin-top: 10px;'>";
        echo "✅ Test donor created successfully!";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24; margin-top: 10px;'>";
        echo "❌ Failed to create donor: " . json_encode($result);
        echo "</div>";
    }
}

echo "<h2>🔧 Fix RLS Policies</h2>";
echo "<p>If you're getting 400 errors, you might need to adjust RLS policies. Here's the SQL to run in Supabase:</p>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px;'>";
echo "-- Temporarily disable RLS for testing<br>";
echo "ALTER TABLE push_subscriptions DISABLE ROW LEVEL SECURITY;<br>";
echo "ALTER TABLE donor_notifications DISABLE ROW LEVEL SECURITY;<br>";
echo "ALTER TABLE blood_drive_notifications DISABLE ROW LEVEL SECURITY;<br><br>";
echo "-- Or create permissive policies<br>";
echo "CREATE POLICY \"Allow all operations\" ON push_subscriptions FOR ALL USING (true);<br>";
echo "CREATE POLICY \"Allow all operations\" ON donor_notifications FOR ALL USING (true);<br>";
echo "CREATE POLICY \"Allow all operations\" ON blood_drive_notifications FOR ALL USING (true);<br>";
echo "</div>";

echo "<h2>📋 Next Steps</h2>";
echo "<ol>";
echo "<li>Create some test donors using the form above</li>";
echo "<li>If still getting 400 errors, run the RLS fix SQL in Supabase</li>";
echo "<li>Test the notification system again</li>";
echo "</ol>";
?>


