<?php
/**
 * Test Supabase API Keys
 * This script tests your Supabase API keys to see which one works
 */

require_once 'assets/conn/db_conn.php';

echo "<h1>🔑 Supabase API Keys Test</h1>";

// Test function with different keys
function testSupabaseKey($key, $keyType) {
    $url = SUPABASE_URL . "/rest/v1/donor_form?select=*&limit=1";
    
    $headers = [
        "Content-Type: application/json",
        "apikey: " . $key,
        "Authorization: Bearer " . $key
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'key_type' => $keyType,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

echo "<h2>🧪 Testing API Keys</h2>";

// Test anon key
echo "<h3>Testing ANON Key</h3>";
$anonResult = testSupabaseKey(SUPABASE_API_KEY, 'anon');
echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>Key Type:</strong> " . $anonResult['key_type'] . "<br>";
echo "<strong>HTTP Code:</strong> " . $anonResult['http_code'] . "<br>";
echo "<strong>Response:</strong> " . substr($anonResult['response'], 0, 200) . "...<br>";
if ($anonResult['http_code'] == 200) {
    echo "<span style='color: green;'>✅ ANON key works!</span><br>";
} else {
    echo "<span style='color: red;'>❌ ANON key failed</span><br>";
}
echo "</div>";

// Test service role key
echo "<h3>Testing SERVICE_ROLE Key</h3>";
$serviceResult = testSupabaseKey(SUPABASE_SERVICE_KEY, 'service_role');
echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>Key Type:</strong> " . $serviceResult['key_type'] . "<br>";
echo "<strong>HTTP Code:</strong> " . $serviceResult['http_code'] . "<br>";
echo "<strong>Response:</strong> " . substr($serviceResult['response'], 0, 200) . "...<br>";
if ($serviceResult['http_code'] == 200) {
    echo "<span style='color: green;'>✅ SERVICE_ROLE key works!</span><br>";
} else {
    echo "<span style='color: red;'>❌ SERVICE_ROLE key failed</span><br>";
}
echo "</div>";

// Test project info
echo "<h3>Testing Project Info</h3>";
$projectUrl = SUPABASE_URL . "/rest/v1/";
$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_API_KEY,
    "Authorization: Bearer " . SUPABASE_API_KEY
];

$ch = curl_init($projectUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>Project URL:</strong> " . SUPABASE_URL . "<br>";
echo "<strong>HTTP Code:</strong> $httpCode<br>";
echo "<strong>Response:</strong> " . substr($response, 0, 200) . "...<br>";
if ($httpCode == 200) {
    echo "<span style='color: green;'>✅ Project is accessible!</span><br>";
} else {
    echo "<span style='color: red;'>❌ Project access failed</span><br>";
}
echo "</div>";

echo "<h2>🔧 How to Fix API Key Issues</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
echo "<h3>If both keys are failing:</h3>";
echo "<ol>";
echo "<li><strong>Check your Supabase project:</strong><br>";
echo "   Go to <a href='https://supabase.com/dashboard' target='_blank'>Supabase Dashboard</a></li>";
echo "<li><strong>Verify project URL:</strong><br>";
echo "   Make sure the URL matches: " . SUPABASE_URL . "</li>";
echo "<li><strong>Get new API keys:</strong><br>";
echo "   Go to Settings → API → Copy the new keys</li>";
echo "<li><strong>Check project status:</strong><br>";
echo "   Make sure your project is not paused or deleted</li>";
echo "</ol>";
echo "</div>";

echo "<h2>🔄 Update API Keys</h2>";
echo "<p>If you need to update your API keys, replace them in <code>assets/conn/db_conn.php</code>:</p>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px;'>";
echo "// Supabase Configuration<br>";
echo "define(\"SUPABASE_URL\", \"https://your-project.supabase.co\");<br>";
echo "define(\"SUPABASE_API_KEY\", \"your-new-anon-key\");<br>";
echo "define(\"SUPABASE_SERVICE_KEY\", \"your-new-service-role-key\");<br>";
echo "</div>";

echo "<h2>📋 Next Steps</h2>";
echo "<ol>";
echo "<li>Check which key (if any) works from the test above</li>";
echo "<li>If both fail, get new keys from Supabase Dashboard</li>";
echo "<li>Update the keys in <code>db_conn.php</code></li>";
echo "<li>Test again with the notification system</li>";
echo "</ol>";
?>


