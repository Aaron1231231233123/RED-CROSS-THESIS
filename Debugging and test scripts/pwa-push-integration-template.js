/**
 * PWA Push Notification Integration
 * Add this code to your PWA's main JavaScript file
 * This handles push subscription and notification display
 */

class PushNotificationManager {
    constructor() {
        this.vapidPublicKey = null;
        this.registration = null;
        this.donorId = null;
    }
    
    // Initialize push notifications
    async initialize(donorId) {
        this.donorId = donorId;
        
        try {
            // Check if service worker is supported
            if (!('serviceWorker' in navigator)) {
                console.log('Service Worker not supported');
                return false;
            }
            
            // Check if push messaging is supported
            if (!('PushManager' in window)) {
                console.log('Push messaging not supported');
                return false;
            }
            
            // Register service worker
            this.registration = await navigator.serviceWorker.register('/sw.js');
            console.log('Service Worker registered:', this.registration);
            
            // Get VAPID public key
            await this.getVapidPublicKey();
            
            // Check current subscription
            const subscription = await this.registration.pushManager.getSubscription();
            
            if (subscription) {
                console.log('Already subscribed to push notifications');
                return true;
            } else {
                // Request permission and subscribe
                return await this.subscribeToPush();
            }
            
        } catch (error) {
            console.error('Error initializing push notifications:', error);
            return false;
        }
    }
    
    // Get VAPID public key from server
    async getVapidPublicKey() {
        try {
            const response = await fetch('/RED-CROSS-THESIS/public/api/get-vapid-public-key.php');
            const data = await response.json();
            
            if (data.success) {
                this.vapidPublicKey = data.vapid_public_key;
                console.log('VAPID public key received');
            } else {
                throw new Error('Failed to get VAPID public key');
            }
        } catch (error) {
            console.error('Error getting VAPID public key:', error);
            throw error;
        }
    }
    
    // Subscribe to push notifications
    async subscribeToPush() {
        try {
            // Request permission
            const permission = await Notification.requestPermission();
            
            if (permission !== 'granted') {
                console.log('Notification permission denied');
                return false;
            }
            
            // Convert VAPID key
            const applicationServerKey = this.urlBase64ToUint8Array(this.vapidPublicKey);
            
            // Subscribe to push manager
            const subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            });
            
            console.log('Push subscription created:', subscription);
            
            // Send subscription to server
            await this.sendSubscriptionToServer(subscription);
            
            return true;
            
        } catch (error) {
            console.error('Error subscribing to push:', error);
            return false;
        }
    }
    
    // Send subscription to server
    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('/RED-CROSS-THESIS/public/api/save-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    donor_id: this.donorId,
                    endpoint: subscription.endpoint,
                    p256dh: this.arrayBufferToBase64(subscription.getKey('p256dh')),
                    auth: this.arrayBufferToBase64(subscription.getKey('auth')),
                    expires_at: subscription.expirationTime ? new Date(subscription.expirationTime).toISOString() : null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('Subscription saved to server');
                this.showNotificationPermissionSuccess();
            } else {
                throw new Error(data.message || 'Failed to save subscription');
            }
            
        } catch (error) {
            console.error('Error saving subscription:', error);
            this.showNotificationPermissionError();
        }
    }
    
    // Unsubscribe from push notifications
    async unsubscribeFromPush() {
        try {
            const subscription = await this.registration.pushManager.getSubscription();
            
            if (subscription) {
                await subscription.unsubscribe();
                console.log('Unsubscribed from push notifications');
                
                // Notify server to remove subscription
                await this.removeSubscriptionFromServer();
                
                return true;
            }
            
            return false;
            
        } catch (error) {
            console.error('Error unsubscribing from push:', error);
            return false;
        }
    }
    
    // Remove subscription from server
    async removeSubscriptionFromServer() {
        try {
            const response = await fetch('/RED-CROSS-THESIS/public/api/remove-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    donor_id: this.donorId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('Subscription removed from server');
            } else {
                console.error('Failed to remove subscription from server');
            }
            
        } catch (error) {
            console.error('Error removing subscription:', error);
        }
    }
    
    // Check if notifications are supported and enabled
    isNotificationSupported() {
        return 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window;
    }
    
    // Check current permission status
    getPermissionStatus() {
        if (!('Notification' in window)) {
            return 'not-supported';
        }
        
        return Notification.permission;
    }
    
    // Show notification permission success message
    showNotificationPermissionSuccess() {
        this.showToast('✅ Push notifications enabled! You will receive blood drive alerts.', 'success');
    }
    
    // Show notification permission error message
    showNotificationPermissionError() {
        this.showToast('❌ Failed to enable push notifications. Please try again.', 'error');
    }
    
    // Show toast notification
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.textContent = message;
        
        // Style the toast
        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '12px 20px',
            borderRadius: '8px',
            color: 'white',
            fontSize: '14px',
            fontWeight: '500',
            zIndex: '10000',
            maxWidth: '300px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease'
        });
        
        // Set background color based on type
        switch (type) {
            case 'success':
                toast.style.backgroundColor = '#10b981';
                break;
            case 'error':
                toast.style.backgroundColor = '#ef4444';
                break;
            case 'warning':
                toast.style.backgroundColor = '#f59e0b';
                break;
            default:
                toast.style.backgroundColor = '#3b82f6';
        }
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }
    
    // Utility: Convert base64url to Uint8Array
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
    
    // Utility: Convert ArrayBuffer to base64
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }
}

// Initialize push notifications when PWA loads
document.addEventListener('DOMContentLoaded', async () => {
    // Get donor ID from your authentication system
    const donorId = getDonorIdFromAuth(); // Implement this function based on your auth system
    
    if (donorId) {
        const pushManager = new PushNotificationManager();
        
        // Check if notifications are supported
        if (pushManager.isNotificationSupported()) {
            console.log('Push notifications are supported');
            
            // Initialize push notifications
            const success = await pushManager.initialize(donorId);
            
            if (success) {
                console.log('Push notifications initialized successfully');
            } else {
                console.log('Failed to initialize push notifications');
            }
        } else {
            console.log('Push notifications are not supported in this browser');
        }
    }
});

// Function to get donor ID from authentication (implement based on your system)
function getDonorIdFromAuth() {
    // This should return the current donor's ID
    // Example implementations:
    
    // From session storage
    // return sessionStorage.getItem('donor_id');
    
    // From URL parameter
    // const urlParams = new URLSearchParams(window.location.search);
    // return urlParams.get('donor_id');
    
    // From global variable
    // return window.currentDonorId;
    
    // For now, return null - implement based on your auth system
    return null;
}

// Export for use in other modules
window.PushNotificationManager = PushNotificationManager;



