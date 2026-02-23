const CACHE_NAME = 'turtledot-cache-v1';
const ASSETS = [
    '/',
    '/manifest.json',
    '/assets/images/turtle_logo_192.png',
    '/assets/images/turtle_logo_512.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'
];

// Install Event
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS);
        })
    );
});

// Activate Event
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        })
    );
});

// Fetch Event
self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            return cachedResponse || fetch(event.request);
        })
    );
});

// Push Notification Event
self.addEventListener('push', (event) => {
    let data = { title: 'New Message', body: 'You have a new message in Turtledot CRM', url: '/tools/chat.php' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: '/assets/images/turtle_logo_192.png',
        badge: '/assets/images/turtle_logo_192.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/tools/chat.php'
        },
        actions: [
            { action: 'open', title: 'Open Chat' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification Click Event
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // 1. Try to find a window that EXACTLY matches the URL
            for (const client of clientList) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            // 2. Try to find any window on the same tool (chat or calendar)
            for (const client of clientList) {
                const isMatchingTool = (targetUrl.includes('calendar.php') && client.url.includes('calendar.php')) ||
                    (targetUrl.includes('chat.php') && client.url.includes('chat.php'));

                if (isMatchingTool && 'focus' in client) {
                    return client.focus();
                }
            }
            // 3. Otherwise, open a new window
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
