<?php
/**
 * Web Push Sender using cURL
 * Sends Web Push notifications without external dependencies
 */

require_once 'vapid_config.php';

class WebPushSender {
    private $vapidPublicKey;
    private $vapidPrivateKey;
    private $vapidSubject;
    
    public function __construct() {
        $this->vapidPublicKey = getVapidPublicKey();
        $this->vapidPrivateKey = getVapidPrivateKey();
        $this->vapidSubject = getVapidSubject();
    }
    
    /**
     * Send push notification to a subscription
     */
    public function sendNotification($subscription, $payload) {
        $endpoint = $subscription['endpoint'];
        
        // For testing purposes, we'll send a simple HTTP request
        // In production, you'd need proper Web Push encryption
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'TTL: 86400'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // For testing
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error
        ];
    }
    
    /**
     * Generate VAPID JWT token
     */
    private function generateVapidJWT($audience) {
        $header = [
            'typ' => 'JWT',
            'alg' => 'ES256'
        ];
        
        $now = time();
        $payload = [
            'aud' => $audience,
            'exp' => $now + 3600, // 1 hour
            'sub' => $this->vapidSubject
        ];
        
        // For simplicity, we'll use a basic JWT implementation
        // In production, use a proper JWT library
        $headerEncoded = $this->base64urlEncode(json_encode($header));
        $payloadEncoded = $this->base64urlEncode(json_encode($payload));
        
        $signature = $this->signJWT($headerEncoded . '.' . $payloadEncoded);
        $signatureEncoded = $this->base64urlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * Encrypt payload using ECDH
     */
    private function encryptPayload($payload, $p256dh, $auth) {
        // Convert base64url to binary
        $userPublicKey = $this->base64urlDecode($p256dh);
        $userAuth = $this->base64urlDecode($auth);
        
        // Generate ephemeral key pair
        $ephemeralKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 256,
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1'
        ]);
        
        $ephemeralDetails = openssl_pkey_get_details($ephemeralKey);
        $ephemeralPublic = $ephemeralDetails['ec']['x'] . $ephemeralDetails['ec']['y'];
        
        // Derive shared secret
        $sharedSecret = $this->deriveSharedSecret($ephemeralKey, $userPublicKey);
        
        // Generate encryption key
        $encryptionKey = $this->generateEncryptionKey($sharedSecret, $userAuth);
        
        // Encrypt payload
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($payload, 'aes-128-gcm', $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        
        // Combine ephemeral public key + IV + tag + encrypted data
        $result = $ephemeralPublic . $iv . $tag . $encrypted;
        
        return base64_encode($result);
    }
    
    /**
     * Send HTTP request to push service
     */
    private function sendHttpRequest($endpoint, $payload, $jwt) {
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $jwt,
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => base64_decode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error
        ];
    }
    
    /**
     * Base64 URL encode
     */
    private function base64urlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private function base64urlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    /**
     * Sign JWT (simplified implementation)
     */
    private function signJWT($data) {
        // This is a simplified implementation
        // In production, use proper ECDSA signing
        return hash('sha256', $data . $this->vapidPrivateKey, true);
    }
    
    /**
     * Derive shared secret (simplified)
     */
    private function deriveSharedSecret($privateKey, $publicKey) {
        // Simplified ECDH implementation
        // In production, use proper ECDH key agreement
        return hash('sha256', $publicKey, true);
    }
    
    /**
     * Generate encryption key
     */
    private function generateEncryptionKey($sharedSecret, $auth) {
        return hash('sha256', $sharedSecret . $auth, true);
    }
}
?>
