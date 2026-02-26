const CACHE_NAME = 'turtledot-cache-v1';
const ASSETS = [
    '/',
    '/manifest.json',
    '/assets/images/turtle_logo_192.png',
    '/assets/images/turtle_logo_512.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(caches.match(event.request).then((cachedResponse) => cachedResponse || fetch(event.request)));
});

const activeChannels = new Map();

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SET_ACTIVE_CHANNEL') {
        activeChannels.set(event.source.id, event.data.channel);
    }
});

/**
 * 🔔 Modern Push Notification Handler
 * Includes Grouping, Actions, and Custom Types (Chat/Calendar)
 */
self.addEventListener('push', (event) => {
    let data = {
        title: 'New Notification',
        body: 'You have a new alert',
        url: '/index.php',
        tag: 'pulse-default',
        type: 'general'
    };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Strictly show OS notification ONLY if the app is minimized (all windows hidden) or closed (no windows)
            const isAnyWindowVisible = clientList.some(client => client.visibilityState === 'visible');

            if (isAnyWindowVisible) {
                console.log('App window is visible/not-minimized, suppressing OS notification');
                return;
            }

            // Fallback: If any window is focused on the app in general, maybe suppress? 
            // But user specifically said "only get notification when they are not have a active tint"
            // So we'll allow notifications for OTHER channels even if window is focused.

            return self.registration.getNotifications({ tag: data.tag }).then((notifications) => {
                let primaryTitle = data.title;
                let primaryBody = data.body;
                let count = data.unread_count || 1;

                if (notifications && notifications.length > 0 && !data.unread_count) {
                    const existing = notifications[0];
                    if (existing.data && existing.data.count) {
                        count = existing.data.count + 1;
                    } else {
                        count = 2;
                    }
                }

                if (count > 1) {
                    if (data.tag && data.tag.startsWith('chat-')) {
                        primaryBody = `${count} new messages. Last: ${data.body}`;
                    }
                }

                // Define Actions based on Type
                let actions = [
                    { action: 'open', title: '💬 Open Pulse' },
                    { action: 'mark_read', title: '✅ Mark as Read' }
                ];

                if (data.type === 'calendar') {
                    actions = [
                        { action: 'open', title: '🗓️ View Agenda' },
                        { action: 'dismiss', title: '❌ Close' }
                    ];
                }

                const options = {
                    body: primaryBody,
                    icon: '/assets/images/turtle_logo_512.png',
                    badge: '/assets/images/turtle_logo_192.png',
                    vibrate: [200, 100, 200],
                    tag: data.tag,
                    renotify: true,
                    data: {
                        url: data.url || (data.type === 'calendar' ? '/tools/calendar.php' : '/tools/chat.php'),
                        count: count,
                        channel: data.channel,
                        channel_id: data.channel_id,
                        type: data.type
                    },
                    actions: actions
                };

                return self.registration.showNotification(primaryTitle, options);
            });
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    const notification = event.notification;
    const action = event.action;

    notification.close();

    if (action === 'mark_read') {
        event.waitUntil(
            fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark_as_read',
                    channel: notification.data.channel,
                    channel_id: notification.data.channel_id
                })
            })
        );
        return;
    }

    if (action === 'dismiss') return;

    // Default open behavior
    const targetUrl = notification.data.url || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Find appropriate open window
            for (const client of clientList) {
                if (notification.data.type === 'calendar' && client.url.includes('calendar.php')) {
                    return client.focus();
                }
                if (notification.data.type !== 'calendar' && client.url.includes('chat.php')) {
                    return client.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(targetUrl);
        })
    );
});
