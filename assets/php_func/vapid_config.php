<?php
/**
 * VAPID Keys Configuration for Web Push Notifications
 * Store these keys securely in production
 */

// VAPID Keys - Real keys for Web Push notifications
// In production, store these in environment variables or secure config
define('VAPID_PUBLIC_KEY', 'BEl62iUYgUivxIkv69yViEuiBIa40HI8V8Vq8VjT5vLb3zTp5onPDLL-50FY9kFryPYajx0EIs9w9A1UGzkGiI');
define('VAPID_PRIVATE_KEY', 'K0X2DYE1M-lJXZitDBuZ5rm3QuwDloFoymfT89pXzVc');
define('VAPID_SUBJECT', 'mailto:admin@redcross.ph'); // Your contact email

/**
 * Get VAPID public key for client-side subscription
 */
function getVapidPublicKey() {
    return VAPID_PUBLIC_KEY;
}

/**
 * Get VAPID private key for server-side sending
 */
function getVapidPrivateKey() {
    return VAPID_PRIVATE_KEY;
}

/**
 * Get VAPID subject
 */
function getVapidSubject() {
    return VAPID_SUBJECT;
}
?>
