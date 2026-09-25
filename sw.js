// sw.js

// Service Worker Installation
self.addEventListener('install', function(event) {
    console.log('Service Worker installing.');
    // Force the waiting service worker to become the active service worker
    self.skipWaiting();
});

// Service Worker Activation
self.addEventListener('activate', function(event) {
    console.log('Service Worker activated.');
    // Claim any open clients immediately so the SW can handle notifications right away
    event.waitUntil(self.clients.claim());
});

// Handle Notification Click Event
self.addEventListener('notificationclick', function(event) {
    console.log('Notification clicked:', event.notification);
    
    // Close the notification popup
    event.notification.close();

    // Extract the URL from the notification data
    const targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

    // Focus or open the window with the target URL
    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(function(clientList) {
            // Check if a window/tab with the target URL is already open
            for (let i = 0; i < clientList.length; i++) {
                let client = clientList[i];
                // If found, focus it
                if (client.url.includes(targetUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            // If not found, open a new window/tab
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

// Handle Push Events (Future-ready for Server Push Notifications)
self.addEventListener('push', function(event) {
    console.log('Push received:', event);
    
    let data = { title: 'নতুন সংবাদ', body: 'একটি নতুন সংবাদ প্রকাশিত হয়েছে!', url: '/' };
    
    try {
        if (event.data) {
            data = event.data.json();
        }
    } catch (e) {
        console.error('Error parsing push data', e);
    }

    const options = {
        body: data.body,
        icon: data.icon || 'https://via.placeholder.com/150/B71C1C/FFFFFF?text=News',
        badge: 'https://via.placeholder.com/150/B71C1C/FFFFFF?text=News',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/'
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});