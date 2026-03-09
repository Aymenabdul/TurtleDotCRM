importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');
console.log('SW: Version v4 Loading...');

firebase.initializeApp({
    apiKey: "AIzaSyCOAXoBMLFK9ybpUsPsw_peIDQAWgOMR0A",
    authDomain: "turtledot-c67e2.firebaseapp.com",
    projectId: "turtledot-c67e2",
    storageBucket: "turtledot-c67e2.firebasestorage.app",
    messagingSenderId: "655773912574",
    appId: "1:655773912574:web:5a32a17aaa670a10eac4af"
});

const messaging = firebase.messaging();

/**
 * 🛠️ Background Message Handling
 * FCM triggers either 'push' or 'onBackgroundMessage'.
 * We consolidate logic into 'push' but let FCM SDK initialize.
 */
// messaging.onBackgroundMessage((payload) => {
//    We let the 'push' event below handle everything for consistency
// });

const CACHE_NAME = 'turtledot-cache-v4';
const STATIC_ASSETS = [
    '/assets/images/turtle_logo_192.png',
    '/assets/images/turtle_logo_512.png',
    '/manifest.json'
];

self.addEventListener('install', (event) => {
    // Cache assets individually — if one fails, the SW still installs
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            for (const asset of STATIC_ASSETS) {
                try {
                    await cache.add(asset);
                } catch (e) {
                    console.warn('SW: Failed to cache', asset, e);
                }
            }
        })
    );
    self.skipWaiting(); // Activate immediately, don't wait
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
        )
    );
    self.clients.claim(); // Take control of all pages immediately
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Network-first for PHP pages and API calls — NEVER serve these from cache
    // This prevents ERR_FAILED on first load
    if (url.pathname.endsWith('.php') || url.pathname.includes('/api/') || url.search.includes('v=') || url.search.includes('t=')) {
        // ALWAYS Network-first for dynamic content - essential for PWA update reliability
        event.respondWith(
            fetch(event.request)
                .then(response => response)
                .catch(() => caches.match(event.request))
        );
        return;
    }

    // Cache-first for static assets (images, icons, manifest)
    event.respondWith(
        caches.match(event.request).then((cached) => cached || fetch(event.request))
    );
});

const activeChannels = new Map();
var currentUserGlobal = null;

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SET_ACTIVE_CHANNEL') {
        activeChannels.set(event.source.id, event.data.channel);
        if (event.data.user_id) currentUserGlobal = event.data.user_id;
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
            // 1. APP IS VISIBLE — post to page so it can update chat in real-time
            //    (We can't call loadMessages() directly from SW, so we message the page)
            const isAnyWindowVisible = clientList.some(client => client.visibilityState === 'visible');
            if (isAnyWindowVisible) {
                // Skip self-notifications (sender_id check)
                if (data.sender_id && currentUserGlobal && data.sender_id == currentUserGlobal) {
                    console.log('SW: Skipping self-notification');
                    return;
                }
                // Tell the visible page to load new messages
                clientList.forEach(client => {
                    if (client.visibilityState === 'visible') {
                        client.postMessage({
                            type: 'NEW_PUSH_MESSAGE',
                            channel: data.channel,
                            sender_id: data.sender_id,
                            msgType: data.type || 'chat',
                            title: data.title,
                            body: data.body
                        });
                    }
                });
                return;
            }

            // 2. SKIP IF SENDER IS ME (Cross-device sync)
            if (data.sender_id && currentUserGlobal && data.sender_id == currentUserGlobal) {
                console.log('FCM SW: Skipping self-notification');
                return;
            }

            // 3. SHOW NATIVE NOTIFICATION (App is backgrounded/minimized/closed)
            return self.registration.getNotifications({ tag: data.tag }).then((notifications) => {
                const primaryTitle = data.title;
                const primaryBody = data.body;

                // Define Actions based on Type
                let actions = [
                    { action: 'open', title: '💬 Open Chat' },
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
                    timestamp: Date.now(),
                    data: {
                        url: data.url || (data.type === 'calendar' ? '/tools/calendar.php' : '/tools/chat.php'),
                        channel: data.channel,
                        channel_id: data.channel_id,
                        type: data.type
                    },
                    actions: actions
                };

                return self.registration.showNotification(primaryTitle || 'Turtledot Workspace', options);
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
