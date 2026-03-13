importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

const SW_VERSION = 'v17.2';
console.log(`SW: Version ${SW_VERSION} Loading...`);

// Firebase Initialization
const firebaseConfig = {
    apiKey: "AIzaSyCOAXoBMLFK9ybpUsPsw_peIDQAWgOMR0A",
    authDomain: "turtledot-c67e2.firebaseapp.com",
    projectId: "turtledot-c67e2",
    storageBucket: "turtledot-c67e2.firebasestorage.app",
    messagingSenderId: "655773912574",
    appId: "1:655773912574:web:5a32a17aaa670a10eac4af"
};

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

/**
 * 🛠️ Lifecycle Listeners
 */
self.addEventListener('install', (event) => {
    console.log(`SW ${SW_VERSION}: Installing...`);
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log(`SW ${SW_VERSION}: Activating and claiming clients...`);
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            // Cleanup old caches if any
            caches.keys().then((keys) =>
                Promise.all(keys.filter((key) => key !== 'turtledot-cache-v5').map((key) => caches.delete(key)))
            )
        ])
    );
});

/**
 * 🛠️ Message Handling from UI
 */
const activeChannels = new Map();
let currentUserGlobal = null;

self.addEventListener('message', (event) => {
    if (event.data) {
        if (event.data.type === 'SET_ACTIVE_CHANNEL') {
            activeChannels.set(event.source.id, event.data.channel);
            if (event.data.user_id) currentUserGlobal = event.data.user_id;
            console.log(`SW: Active channel set to ${event.data.channel} for client ${event.source.id}`);
        }
    }
});

/**
 * 🔔 Background Push Notification Handler
 * Optimized for Safari/iOS Standalone compatibility.
 */
self.addEventListener('push', (event) => {
    console.log('SW: Push event received');
    
    let data;
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : 'New notification' };
    }

    // Normalize payload (Support both VAPID/Custom and FCM formats)
    const payload = data.data || data.notification || data;
    if (data.notification) {
        payload.title = data.notification.title || payload.title;
        payload.body = data.notification.body || payload.body;
    }

    const title = payload.title || 'New Alert';
    const body = payload.body || 'You have a new message.';
    const tag = payload.tag || 'pulse-default';
    const senderId = payload.sender_id || null;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // 1. Skip if sender is ME (prevents feedback loop notifications)
            if (senderId && currentUserGlobal && String(senderId) === String(currentUserGlobal)) {
                console.log('SW: Skipping self-notification');
                return;
            }

            // 2. Broadcast to all open tabs for real-time UI refresh
            clientList.forEach(client => {
                client.postMessage({
                    type: 'NEW_PUSH_MESSAGE',
                    channel: payload.channel,
                    sender_id: senderId,
                    msgType: payload.type || 'chat',
                    title: title,
                    body: body,
                    url: payload.url
                });
            });

            // 3. Decide whether to show OS notification
            // Safari/iOS requires a visible notification if no window is focused.
            const isWindowFocused = clientList.some(client => client.focused);
            if (isWindowFocused) {
                console.log('SW: Window is focused, skipping OS alert');
                // We don't return here because on Safari Standalone, even a focused app 
                // sometimes benefits from a showNotification if the push is marked as non-silent.
                // But for now, we follow the user's preference to skip if focused.
                return;
            }

            // 4. Show the notification
            // REMOVED duplicate getNotifications check. Safari handles 'tag' internally.
            // Repetitive silent pushes (failing to show a notification) lead to APNs Revocation.
            const options = {
                body: body,
                icon: payload.icon || (self.location.origin + '/assets/images/turtle_logo_512.png'),
                badge: self.location.origin + '/assets/images/turtle_logo_192.png',
                vibrate: [200, 100, 200],
                tag: tag,
                renotify: true,
                timestamp: Date.now(),
                data: {
                    url: payload.url || (payload.type === 'calendar' ? '/tools/calendar.php' : '/tools/chat.php'),
                    channel: payload.channel,
                    channel_id: payload.channel_id,
                    type: payload.type
                },
                actions: [
                    { action: 'open', title: '💬 Open' },
                    { action: 'mark_read', title: '✅ Mark Read' }
                ]
            };

            console.log('SW: Showing notification', title, tag);
            return self.registration.showNotification(title, options);
        })
    );
});

/**
 * 🛠️ Notification Interaction
 */
self.addEventListener('notificationclick', (event) => {
    const notification = event.notification;
    const action = event.action;

    notification.close();

    if (action === 'mark_read') {
        const promise = fetch('/api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_as_read',
                channel: notification.data.channel,
                channel_id: notification.data.channel_id
            })
        });
        event.waitUntil(promise);
        return;
    }

    const targetUrl = notification.data.url || '/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    // Try to find a matching tool window
                    if (notification.data.type === 'calendar' && client.url.includes('calendar.php')) return client.focus();
                    if (notification.data.type !== 'calendar' && client.url.includes('chat.php')) return client.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(targetUrl);
        })
    );
});

/**
 * 🛠️ Static Asset Fetching (Basic Cache Strategy)
 */
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    // Network-only for PHP and API calls
    if (url.pathname.endsWith('.php') || url.pathname.includes('/api/')) {
        return; 
    }
    // Cache-first for images/icons
    if (url.pathname.includes('/assets/images/')) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request))
        );
    }
});

