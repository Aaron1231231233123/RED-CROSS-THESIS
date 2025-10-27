/**
 * PWA Service Worker for Web Push Notifications
 * Place this file in your PWA's root directory as 'sw.js'
 * Register this service worker in your PWA's main JavaScript file
 */

// Service Worker Version
const CACHE_NAME = 'red-cross-pwa-v1';
const VAPID_PUBLIC_KEY = 'BEl62iUYgUivxIkv69yViEuiBIa40HI8V8Vq8VjT5vLb3zTp5onPDLL-50FY9kFryPYajx0EIs9w9A1UGzkGiI'; // Replace with your actual VAPID public key

// Install event - cache essential files
self.addEventListener('install', (event) => {
    console.log('Service Worker installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll([
                    '/',
                    '/assets/image/PRC_Logo.png',
                    '/manifest.json'
                ]);
            })
    );
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('Service Worker activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Push event - handle incoming push notifications
self.addEventListener('push', (event) => {
    console.log('Push notification received:', event);
    
    let notificationData = {
        title: '🩸 Red Cross Blood Bank',
        body: 'You have a new notification',
        icon: '/assets/image/PRC_Logo.png',
        badge: '/assets/image/PRC_Logo.png',
        data: {
            url: '/',
            type: 'general'
        }
    };
    
    // Parse push data if available
    if (event.data) {
        try {
            const pushData = event.data.json();
            notificationData = {
                title: pushData.title || notificationData.title,
                body: pushData.body || notificationData.body,
                icon: pushData.icon || notificationData.icon,
                badge: pushData.badge || notificationData.badge,
                data: pushData.data || notificationData.data,
                actions: pushData.actions || [],
                requireInteraction: pushData.requireInteraction || false,
                tag: pushData.tag || 'red-cross-notification'
            };
        } catch (e) {
            console.error('Error parsing push data:', e);
        }
    }
    
    // Show notification
    event.waitUntil(
        self.registration.showNotification(notificationData.title, {
            body: notificationData.body,
            icon: notificationData.icon,
            badge: notificationData.badge,
            data: notificationData.data,
            actions: notificationData.actions,
            requireInteraction: notificationData.requireInteraction,
            tag: notificationData.tag,
            vibrate: [200, 100, 200],
            sound: '/assets/sounds/notification.mp3' // Optional notification sound
        })
    );
});

// Notification click event - handle user clicking on notification
self.addEventListener('notificationclick', (event) => {
    console.log('Notification clicked:', event);
    
    event.notification.close();
    
    const notificationData = event.notification.data || {};
    const action = event.action;
    
    let urlToOpen = notificationData.url || '/';
    
    // Handle specific actions
    if (action === 'rsvp') {
        urlToOpen = `/blood-drive-rsvp?id=${notificationData.blood_drive_id || ''}`;
    } else if (action === 'dismiss') {
        // Just close the notification, don't open anything
        return;
    }
    
    // Open or focus the app
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Check if app is already open
                for (const client of clientList) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.navigate(urlToOpen);
                        return client.focus();
                    }
                }
                
                // If app is not open, open it
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// Background sync for offline functionality
self.addEventListener('sync', (event) => {
    console.log('Background sync:', event.tag);
    
    if (event.tag === 'blood-drive-rsvp') {
        event.waitUntil(
            // Handle offline RSVP submissions
            handleOfflineRSVP()
        );
    }
});

// Handle offline RSVP submissions
async function handleOfflineRSVP() {
    try {
        // Get offline RSVP data from IndexedDB
        const offlineData = await getOfflineRSVPData();
        
        if (offlineData && offlineData.length > 0) {
            for (const rsvp of offlineData) {
                await submitRSVP(rsvp);
            }
            
            // Clear offline data after successful submission
            await clearOfflineRSVPData();
        }
    } catch (error) {
        console.error('Error handling offline RSVP:', error);
    }
}

// Helper function to get offline RSVP data (implement based on your IndexedDB setup)
async function getOfflineRSVPData() {
    // Implement IndexedDB retrieval logic
    return [];
}

// Helper function to submit RSVP
async function submitRSVP(rsvpData) {
    try {
        const response = await fetch('/api/submit-rsvp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(rsvpData)
        });
        
        if (!response.ok) {
            throw new Error('Failed to submit RSVP');
        }
        
        return await response.json();
    } catch (error) {
        console.error('Error submitting RSVP:', error);
        throw error;
    }
}

// Helper function to clear offline RSVP data
async function clearOfflineRSVPData() {
    // Implement IndexedDB cleanup logic
}

// Message event - handle messages from main thread
self.addEventListener('message', (event) => {
    console.log('Service Worker received message:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// Fetch event - handle network requests
self.addEventListener('fetch', (event) => {
    // Implement caching strategy for your PWA
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Return cached version or fetch from network
                return response || fetch(event.request);
            })
    );
});


