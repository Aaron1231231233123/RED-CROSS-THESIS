<?php
/**
 * Generate Real VAPID Keys
 * Run this in your browser: http://localhost/RED-CROSS-THESIS/generate_real_vapid_keys.php
 */

// Generate VAPID key pair using OpenSSL
function generateRealVapidKeys() {
    // Create EC key pair
    $config = [
        'digest_alg' => 'sha256',
        'private_key_bits' => 256,
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1'
    ];
    
    $privateKey = openssl_pkey_new($config);
    if (!$privateKey) {
        throw new Exception('Failed to generate private key');
    }
    
    // Export private key
    openssl_pkey_export($privateKey, $privateKeyPem);
    
    // Get public key details
    $keyDetails = openssl_pkey_get_details($privateKey);
    $publicKeyPem = $keyDetails['key'];
    
    // Extract raw coordinates
    $x = $keyDetails['ec']['x'];
    $y = $keyDetails['ec']['y'];
    
    // Convert to base64url format
    $publicKeyRaw = base64url_encode($x . $y);
    $privateKeyRaw = base64url_encode($privateKeyPem);
    
    return [
        'publicKey' => $publicKeyRaw,
        'privateKey' => $privateKeyRaw,
        'publicKeyPem' => $publicKeyPem,
        'privateKeyPem' => $privateKeyPem
    ];
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Generate keys
try {
    $keys = generateRealVapidKeys();
    
    echo "<h2>🔑 VAPID Keys Generated Successfully!</h2>";
    echo "<div style='background: #f5f5f5; padding: 20px; border-radius: 8px; font-family: monospace;'>";
    echo "<h3>Public Key (for client):</h3>";
    echo "<textarea style='width: 100%; height: 60px; font-family: monospace; font-size: 12px;'>" . $keys['publicKey'] . "</textarea>";
    echo "<br><br>";
    echo "<h3>Private Key (for server):</h3>";
    echo "<textarea style='width: 100%; height: 60px; font-family: monospace; font-size: 12px;'>" . $keys['privateKey'] . "</textarea>";
    echo "</div>";
    
    echo "<h3>📋 Instructions:</h3>";
    echo "<ol>";
    echo "<li>Copy the <strong>Public Key</strong> above</li>";
    echo "<li>Copy the <strong>Private Key</strong> above</li>";
    echo "<li>Replace the keys in <code>assets/php_func/vapid_config.php</code></li>";
    echo "<li>Update the VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY constants</li>";
    echo "</ol>";
    
    echo "<h3>🔧 Updated vapid_config.php should look like:</h3>";
    echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
    echo "define('VAPID_PUBLIC_KEY', '" . $keys['publicKey'] . "');\n";
    echo "define('VAPID_PRIVATE_KEY', '" . $keys['privateKey'] . "');";
    echo "</pre>";
    
    // Save to file for easy copying
    $configContent = "<?php\n";
    $configContent .= "// VAPID Keys - Generated on " . date('Y-m-d H:i:s') . "\n";
    $configContent .= "define('VAPID_PUBLIC_KEY', '" . $keys['publicKey'] . "');\n";
    $configContent .= "define('VAPID_PRIVATE_KEY', '" . $keys['privateKey'] . "');\n";
    $configContent .= "define('VAPID_SUBJECT', 'mailto:admin@redcross.ph');\n";
    $configContent .= "?>";
    
    file_put_contents('vapid_config_generated.php', $configContent);
    echo "<p>✅ Keys also saved to <code>vapid_config_generated.php</code> for easy copying!</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error generating keys:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p>Make sure OpenSSL is enabled in your PHP installation.</p>";
}
?>



