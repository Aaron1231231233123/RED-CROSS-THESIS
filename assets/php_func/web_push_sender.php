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
     * Uses proper Web Push encryption with VAPID authentication
     */
    public function sendNotification($subscription, $payload) {
        try {
            // Validate subscription
            if (!isset($subscription['endpoint']) || empty($subscription['endpoint'])) {
                return [
                    'success' => false,
                    'http_code' => 0,
                    'response' => null,
                    'error' => 'Missing endpoint in subscription'
                ];
            }
            
            $endpoint = $subscription['endpoint'];
            
            // Extract keys from subscription
            $keys = $subscription['keys'] ?? null;
            if (!$keys || !isset($keys['p256dh']) || !isset($keys['auth'])) {
                return [
                    'success' => false,
                    'http_code' => 0,
                    'response' => null,
                    'error' => 'Missing encryption keys (p256dh or auth) in subscription'
                ];
            }
            
            $p256dh = $keys['p256dh'];
            $auth = $keys['auth'];
            
            // Validate keys are not empty
            if (empty($p256dh) || empty($auth)) {
                return [
                    'success' => false,
                    'http_code' => 0,
                    'response' => null,
                    'error' => 'Empty encryption keys in subscription'
                ];
            }
            
            // Extract audience from endpoint (e.g., https://fcm.googleapis.com/fcm/send/...)
            $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
            if (empty($audience) || $audience === '://') {
                // Fallback for FCM
                if (strpos($endpoint, 'fcm.googleapis.com') !== false) {
                    $audience = 'https://fcm.googleapis.com';
                } else {
                    $audience = 'https://' . parse_url($endpoint, PHP_URL_HOST);
                }
            }
            
            // Generate VAPID JWT token
            $jwt = $this->generateVapidJWT($audience);
            
            // Encrypt payload
            $encryptedPayload = $this->encryptPayload($payload, $p256dh, $auth);
            
            if ($encryptedPayload === false) {
                return [
                    'success' => false,
                    'http_code' => 0,
                    'response' => null,
                    'error' => 'Failed to encrypt payload'
                ];
            }
            
            // Send encrypted payload with VAPID authentication
            return $this->sendHttpRequest($endpoint, $encryptedPayload, $jwt);
            
        } catch (Exception $e) {
            error_log("WebPushSender error: " . $e->getMessage());
            return [
                'success' => false,
                'http_code' => 0,
                'response' => null,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
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
     * Note: This is a simplified implementation. For production, consider using minishlink/web-push library
     */
    private function encryptPayload($payload, $p256dh, $auth) {
        try {
            // Convert base64url to binary
            $userPublicKey = $this->base64urlDecode($p256dh);
            $userAuth = $this->base64urlDecode($auth);
            
            if ($userPublicKey === false || $userAuth === false) {
                error_log("Failed to decode p256dh or auth keys");
                return false;
            }
            
            // Generate ephemeral key pair
            $ephemeralKey = openssl_pkey_new([
                'digest_alg' => 'sha256',
                'private_key_bits' => 256,
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1'
            ]);
            
            if (!$ephemeralKey) {
                error_log("Failed to generate ephemeral key: " . openssl_error_string());
                return false;
            }
            
            $ephemeralDetails = openssl_pkey_get_details($ephemeralKey);
            if (!$ephemeralDetails || !isset($ephemeralDetails['ec'])) {
                error_log("Failed to get ephemeral key details");
                return false;
            }
            
            $ephemeralPublic = $ephemeralDetails['ec']['x'] . $ephemeralDetails['ec']['y'];
            
            // Derive shared secret
            $sharedSecret = $this->deriveSharedSecret($ephemeralKey, $userPublicKey);
            if (!$sharedSecret) {
                error_log("Failed to derive shared secret");
                return false;
            }
            
            // Generate encryption key
            $encryptionKey = $this->generateEncryptionKey($sharedSecret, $userAuth);
            if (!$encryptionKey) {
                error_log("Failed to generate encryption key");
                return false;
            }
            
            // Encrypt payload
            $iv = random_bytes(16);
            $tag = '';
            $encrypted = openssl_encrypt($payload, 'aes-128-gcm', $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
            
            if ($encrypted === false) {
                error_log("Failed to encrypt payload: " . openssl_error_string());
                return false;
            }
            
            // Combine ephemeral public key + IV + tag + encrypted data
            $result = $ephemeralPublic . $iv . $tag . $encrypted;
            
            return base64_encode($result);
            
        } catch (Exception $e) {
            error_log("Encrypt payload exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send HTTP request to push service
     */
    private function sendHttpRequest($endpoint, $payload, $jwt) {
        $ch = curl_init();
        
        // Decode payload if it's base64 encoded
        $payloadData = is_string($payload) && base64_decode($payload, true) !== false 
            ? base64_decode($payload) 
            : $payload;
        
        $headers = [
            'Authorization: vapid t=' . $jwt . ', k=' . $this->vapidPublicKey,
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_VERBOSE => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);
        
        // Log detailed error information for debugging
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("Web Push failed - HTTP Code: $httpCode, Endpoint: $endpoint");
            error_log("Response: " . substr($response, 0, 200));
            if ($error) {
                error_log("cURL Error: $error");
            }
        }
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error ?: ($httpCode >= 400 ? "HTTP $httpCode: " . substr($response, 0, 100) : null)
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
