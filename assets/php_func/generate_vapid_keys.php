<?php
/**
 * Generate VAPID keys for Web Push notifications
 * Run this once to generate keys, then store them securely
 */

// Generate VAPID key pair
function generateVapidKeys() {
    // Generate private key
    $privateKey = openssl_pkey_new([
        'digest_alg' => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1'
    ]);
    
    if (!$privateKey) {
        throw new Exception('Failed to generate private key');
    }
    
    // Extract private key
    openssl_pkey_export($privateKey, $privateKeyPem);
    
    // Get public key
    $keyDetails = openssl_pkey_get_details($privateKey);
    $publicKeyPem = $keyDetails['key'];
    
    // Convert to base64url format for VAPID
    $privateKeyRaw = base64url_encode(openssl_pkey_get_private($privateKeyPem));
    $publicKeyRaw = base64url_encode($keyDetails['ec']['x'] . $keyDetails['ec']['y']);
    
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

// Generate keys if this script is run directly
if (php_sapi_name() === 'cli' || isset($_GET['generate'])) {
    try {
        $keys = generateVapidKeys();
        
        echo "VAPID Keys Generated Successfully:\n";
        echo "================================\n";
        echo "Public Key (for client): " . $keys['publicKey'] . "\n";
        echo "Private Key (for server): " . $keys['privateKey'] . "\n";
        echo "\n";
        echo "Store these keys securely in your environment variables or config file.\n";
        echo "Add to your .env file:\n";
        echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . "\n";
        echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";
        
        // Save to file for easy copying
        file_put_contents('vapid_keys.txt', json_encode($keys, JSON_PRETTY_PRINT));
        echo "\nKeys also saved to vapid_keys.txt\n";
        
    } catch (Exception $e) {
        echo "Error generating keys: " . $e->getMessage() . "\n";
    }
}
?>




