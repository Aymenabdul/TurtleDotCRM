<?php
/**
 * Base Layout Component
 * Provides consistent theme and structure across all application pages
 * 
 * Usage:
 * require_once __DIR__ . '/includes/base_layout.php';
 * startLayout('Page Title', $user);
 * // Your page content here
 * endLayout();
 */

require_once __DIR__ . '/../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function startLayout($pageTitle = 'Turtle Dot', $user = null, $includeNavbar = true)
{
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
            content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
        <title><?php echo htmlspecialchars($pageTitle); ?> | Turtledot CRM</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto+Serif:opsz,wght@8..144,300;400;500;600;700&display=swap"
            rel="stylesheet">

        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#10b981">

        <!-- PWA / iOS Tags -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Turtledot">
        <link rel="apple-touch-icon" href="/assets/images/turtle_logo_192.png">
        <link rel="apple-touch-icon" sizes="152x152" href="/assets/images/turtle_logo_192.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/turtle_logo_192.png">
        <link rel="apple-touch-icon" sizes="167x167" href="/assets/images/turtle_logo_192.png">

        <!-- Font Awesome -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

        <!-- Firebase SDK for FCM -->
        <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js"></script>


        <script>
            // Register Service Worker for PWA
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', async () => {
                    // Force Service Worker to update check
                    console.log('SW: checking for updates...');
                    try {
                        const reg = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
                        await reg.update();
                        console.log('SW: registration successful', reg);
                    } catch (err) {
                        console.log('SW: Service Worker registration failed', err);
                    }
                });

                // ── Listen for messages posted BY the Service Worker ──────────
                // The SW posts NEW_PUSH_MESSAGE when a push arrives while the app
                // is visible. We only use this to trigger loadMessages() for the
                // active channel. Toast/badge/sound are handled by messaging.onMessage()
                // below to avoid duplicates.
                navigator.serviceWorker.addEventListener('message', (event) => {
                    console.log('Chat: SW Message received:', event.data);
                    if (!event.data || event.data.type !== 'NEW_PUSH_MESSAGE') return;

                    const { channel, sender_id, msgType } = event.data;

                    // Skip own messages (already rendered optimistically on send)
                    if (sender_id && sender_id == GLOBAL_USER_ID) {
                        console.log('Chat: Skipping own message from SW');
                        return;
                    }

                    if (msgType === 'chat' || msgType === 'general') {
                        // Is this for the currently open channel?
                        const isActiveChannel = window.ChatState && (
                            (!window.ChatState.isDmView && window.ChatState.currentChannel === channel) ||
                            (window.ChatState.isDmView && channel && channel.includes(String(GLOBAL_USER_ID)) && channel.includes(String(window.ChatState.activeDmPartnerId)))
                        );

                        console.log('Chat: SW isActiveChannel check:', isActiveChannel, 'Channel:', channel, 'Current:', window.ChatState ? window.ChatState.currentChannel : 'null');

                        if (isActiveChannel) {
                            console.log('Chat: Refreshing messages for active channel');
                            if (typeof window.loadMessages === 'function') window.loadMessages();
                            if (typeof window.markChannelAsRead === 'function') window.markChannelAsRead();
                        }
                    }
                });
            }
        </script>

        <link rel="stylesheet" href="/css/base_layout.css">
    </head>


    <body>
        <script>
            // Immediate execution to prevent sidebar flicker
            (function () {
                const sidebarState = localStorage.getItem('sidebarCollapsed');
                if (sidebarState === 'true') {
                    // We add a temporary style to the head instead of document.write
                    const style = document.createElement('style');
                    style.innerHTML = '@media (min-width: 1025px) { #sidebar { width: var(--sidebar-collapsed-width, 80px) !important; } .main-wrapper { margin-left: var(--sidebar-collapsed-width, 80px) !important; } }';
                    document.head.appendChild(style);
                }
            })();
            const GLOBAL_USER_ID = <?php echo isset($user['user_id']) ? (int) $user['user_id'] : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 'null'); ?>;
            const GLOBAL_USER_NAME = "<?php echo addslashes(htmlspecialchars(($user['full_name'] ?? $user['username'] ?? $_SESSION['user_name'] ?? ''))); ?>";
            const VAPID_PUBLIC_KEY = "<?php echo defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : ''; ?>";
        </script>
        <?php
        if ($includeNavbar) {
            global $pdo;
            $currentPage = $GLOBALS['currentPage'] ?? '';
            $contextTeamId = $_GET['team_id'] ?? $_GET['id'] ?? $_SESSION['last_team_id'] ?? null;
            ?>
            <div class="mobile-header">
                <div class="mobile-toggle-btn" onclick="toggleMobileSidebar()">
                    <i class="fa-solid fa-bars-staggered"></i>
                </div>
                <div class="mobile-header-logos">
                    <img src="/assets/images/turtle_logo.png" alt="Turtle Symbol" class="mobile-header-icon">
                    <div class="mobile-header-text" aria-label="Turtle Dot"></div>
                </div>
            </div>
            <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileSidebar()"></div>
            <?php
            require __DIR__ . '/../components/sidebar.php';
        }
        ?>

        <!-- Main Wrapper -->
        <div class="main-wrapper">
            <main class="main-content">
                <?php
}

function endLayout()
{
    ?>
            </main>
        </div>

        <!-- Global Notifications Container -->
        <div id="pulseNotificationStack"></div>

        <script>
            /* ══════════════════════════════════════════════════════════
               🔔 GLOBAL PULSE NOTIFICATION SYSTEM
               Allows notifications to work on every page of the app.
               ══════════════════════════════════════════════════════════ */

            let lastUnreadState = {};
            const isChatPage = window.location.pathname.includes('chat.php');

            const chime = new Audio('/assets/images/mixkit-doorbell-tone-2864.wav');

            function showSystemNotification(title, message) {
                // Play notification sound
                chime.play().catch(e => console.warn('Audio play blocked or unavailable'));

                // We have disabled the native 'new Notification' browser alert 
                // as requested, to avoid the generic Chrome/localhost popup.
                // The app will now exclusively use our custom iOS-styled UI.

                showPulseToast(title, message);
            }

            function showPulseToast(title, body) {
                const stack = document.getElementById('pulseNotificationStack');
                if (!stack || !title) return;

                // DEDUPLICATION: Don't show if the exact same message is already visible
                const existing = Array.from(stack.children).find(t =>
                    t.querySelector('.pulse-toast-title')?.textContent === title &&
                    t.querySelector('.pulse-toast-body')?.textContent === body
                );
                if (existing) return;

                const toast = document.createElement('div');
                toast.className = 'pulse-toast';

                const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                toast.innerHTML = `
                    <div class="pulse-toast-icon">
                        <img src="/assets/images/turtle_logo_512.png" alt="App Icon">
                    </div>
                    <div class="pulse-toast-main">
                        <div class="pulse-toast-top">
                            <span class="pulse-toast-title">${title}</span>
                            <span class="pulse-toast-time">${time}</span>
                        </div>
                        <div class="pulse-toast-body">${body}</div>
                        <div class="pulse-toast-footer">Turtledot Workspace</div>
                    </div>
                `;

                toast.onclick = () => {
                    window.focus();
                    window.location.href = '/tools/chat.php';
                };

                stack.prepend(toast);
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.style.animation = 'ios-out 0.4s forwards';
                        setTimeout(() => toast.remove(), 400);
                    }
                }, 6000);
            }

            async function updateBadgeCount() {
                try {
                    const res = await fetch('/api/global_status.php');
                    const json = await res.json();
                    if (!json.success) return;

                    let totalUnread = 0;
                    json.unread_dms.forEach(d => totalUnread += (d.unread_count || 0));
                    json.unread_channels.forEach(c => totalUnread += (c.unread_count || 0));

                    // Update Sidebar Badge
                    const badge = document.getElementById('global-chat-badge');
                    if (badge) {
                        badge.textContent = totalUnread;
                        badge.style.display = totalUnread > 0 ? 'inline-block' : 'none';
                    }

                    // Update App Badge (Native Dock Count)
                    if ('setAppBadge' in navigator) {
                        if (totalUnread > 0) {
                            navigator.setAppBadge(totalUnread).catch(e => { });
                        } else {
                            navigator.clearAppBadge().catch(e => { });
                        }
                    }
                } catch (e) {
                    console.warn('Badge update failed', e);
                }
            }

            // Initial badge load
            updateBadgeCount();

            // ── Firebase Cloud Messaging (FCM) ──
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

            async function requestFCMToken() {
                try {
                    // Wait for the service worker to be ready
                    const reg = await navigator.serviceWorker.ready;

                    const permission = await Notification.requestPermission();
                    if (permission === 'granted') {
                        // 1. Get FCM Token (used for targeted FCM logic if needed)
                        const token = await messaging.getToken({
                            vapidKey: VAPID_PUBLIC_KEY || 'BMaKPqcVHa4nu58Vr41ychJtjt5fwzC3iAkr-pIQdUG_Veni0c57Kn45Gu8jdyuSEr-erUvyzo3hSSCaOMbR8kU',
                            serviceWorkerRegistration: reg
                        });
                        console.log('FCM Token:', token);

                        // 2. Handle Native Browser Push Subscription
                        // Check for existing subscription first to avoid VAPID key mismatch errors
                        let existingSub = await reg.pushManager.getSubscription();

                        // We must explicitly compare keys - Firebase doesn't always handle this for us
                        if (existingSub && VAPID_PUBLIC_KEY) {
                            console.log('Chat: Verifying existing push subscription key...');

                            // If keys don't match, or if we want to ensure freshness, unsubscribe
                            try {
                                const subscription = await reg.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: VAPID_PUBLIC_KEY
                                });
                                await sendSubscriptionToServer(subscription, token);
                            } catch (subErr) {
                                // Mismatch error usually looks like InvalidStateError
                                if (subErr.name === 'InvalidStateError' || subErr.message.includes('applicationServerKey')) {
                                    console.warn('Chat: VAPID Key mismatch or stale subscription. Unsubscribing...');
                                    await existingSub.unsubscribe();
                                    const newSub = await reg.pushManager.subscribe({
                                        userVisibleOnly: true,
                                        applicationServerKey: VAPID_PUBLIC_KEY
                                    });
                                    await sendSubscriptionToServer(newSub, token);
                                } else {
                                    throw subErr;
                                }
                            }
                        } else {
                            console.log('Chat: Fresh subscription required');
                            const subscription = await reg.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: VAPID_PUBLIC_KEY
                            });
                            await sendSubscriptionToServer(subscription, token);
                        }
                    }
                } catch (e) {
                    console.error('Chat: FCM registration failed', e);
                }
            }

            async function sendSubscriptionToServer(subscription, token) {
                // Store full subscription (VAPID)
                await fetch('/api/push_subscription.php', {
                    method: 'POST',
                    body: JSON.stringify({ subscription: subscription }),
                    headers: { 'Content-Type': 'application/json' }
                });

                // Store simple token (FCM)
                await fetch('/api/store_token.php', {
                    method: 'POST',
                    body: JSON.stringify({ token: token }),
                    headers: { 'Content-Type': 'application/json' }
                });
            }

            // Replacing old manual push sync with FCM
            requestFCMToken();

            // Handle messages when app is in foreground
            messaging.onMessage((payload) => {
                console.log('Chat: FCM Foreground message received:', payload);

                // Extract Title and Body
                let title = 'New Message';
                let body = '';
                if (payload.notification) {
                    title = payload.notification.title || title;
                    body = payload.notification.body || body;
                } else if (payload.data) {
                    title = payload.data.title || title;
                    body = payload.data.body || body;
                }

                const msgChannel = payload.data ? payload.data.channel : null;
                const senderId = payload.data ? payload.data.sender_id : null;

                // Skip if it's from me
                if (senderId && senderId == GLOBAL_USER_ID) {
                    console.log('Chat: Skipping own message from FCM');
                    return;
                }

                const isActiveChannel = window.ChatState && (
                    (!window.ChatState.isDmView && window.ChatState.currentChannel === msgChannel) ||
                    (window.ChatState.isDmView && msgChannel && msgChannel.includes(String(GLOBAL_USER_ID)) && msgChannel.includes(String(window.ChatState.activeDmPartnerId)))
                );

                console.log('Chat: isActiveChannel check (FCM):', isActiveChannel, 'Channel:', msgChannel);

                if (isActiveChannel) {
                    // 2. MESSAGE FOR ACTIVE CHANNEL — refresh inline
                    console.log('Chat: Refreshing messages via FCM');
                    if (typeof window.loadMessages === 'function') window.loadMessages();
                    if (typeof window.markChannelAsRead === 'function') window.markChannelAsRead();
                    return; // Done!
                }

                // 3. MESSAGE FOR BACKGROUND CHANNEL — show toast + sound
                console.log('Chat: Background message. Showing toast.');
                if (typeof showPulseToast === 'function') {
                    showPulseToast(title, body, payload.data ? payload.data.url : null);
                }
                if (typeof updateBadgeCount === 'function') updateBadgeCount();

                // Play notification sound
                const chime = new Audio('/assets/images/mixkit-doorbell-tone-2864.wav');
                chime.play().catch(e => console.warn('Sound play blocked'));
            });

            let deferredPrompt;

            // ── Detect iOS ──────────────────────────────────────────────
            const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
            const isInStandaloneMode = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone;

            // iOS: Never fires beforeinstallprompt — show the button manually
            if (isIos && !isInStandaloneMode) {
                const installBtns = document.querySelectorAll('.pwa-install-btn');
                installBtns.forEach(btn => {
                    btn.style.display = 'flex';
                    btn.setAttribute('data-ios', 'true');
                });
            }

            // Android / Chrome: fires beforeinstallprompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                const installBtns = document.querySelectorAll('.pwa-install-btn');
                installBtns.forEach(btn => {
                    btn.style.display = 'flex';
                    btn.removeAttribute('data-ios');
                });
            });

            window.addEventListener('appinstalled', () => {
                deferredPrompt = null;
                const installBtns = document.querySelectorAll('.pwa-install-btn');
                installBtns.forEach(btn => btn.style.display = 'none');
            });

            async function installPWA() {
                const btn = document.querySelector('.pwa-install-btn');
                const isIosBtn = btn && btn.getAttribute('data-ios') === 'true';

                if (isIosBtn) {
                    // Show iOS-specific guide modal
                    showIosInstallGuide();
                    return;
                }

                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                const installBtns = document.querySelectorAll('.pwa-install-btn');
                installBtns.forEach(btn => btn.style.display = 'none');
            }

            function showIosInstallGuide() {
                const existing = document.getElementById('ios-install-modal');
                if (existing) { existing.style.display = 'flex'; return; }

                const modal = document.createElement('div');
                modal.id = 'ios-install-modal';
                modal.style.cssText = `
                    position: fixed; inset: 0; z-index: 99999;
                    background: rgba(0,0,0,0.6); backdrop-filter: blur(8px);
                    display: flex; align-items: flex-end; justify-content: center;
                    padding: 1rem;
                `;
                modal.innerHTML = `
                    <div style="
                        background: white; border-radius: 24px; padding: 2rem;
                        max-width: 420px; width: 100%; text-align: center;
                        animation: slideUp 0.3s ease;
                    ">
                        <img src="/assets/images/turtle_logo_192.png"
                             style="width: 72px; height: 72px; border-radius: 18px; margin-bottom: 1rem;">
                        <h3 style="font-size: 1.2rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">
                            Install Turtledot
                        </h3>
                        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">
                            Add this app to your Home Screen for the best experience.
                        </p>
                        <div style="background: #f8fafc; border-radius: 16px; padding: 1.25rem; text-align: left; margin-bottom: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                <div style="font-size: 1.5rem;">1️⃣</div>
                                <div>Tap the <strong>Share</strong> button
                                    <span style="display: inline-block; background: #e2e8f0; border-radius: 6px; padding: 2px 8px; font-size: 0.85rem;">⬆</span>
                                    at the bottom of Safari
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="font-size: 1.5rem;">2️⃣</div>
                                <div>Scroll down and tap <strong>"Add to Home Screen"</strong></div>
                            </div>
                        </div>
                        <button onclick="document.getElementById('ios-install-modal').style.display='none''"
                            style="
                                width: 100%; padding: 0.9rem; border: none;
                                background: #10b981; color: white; border-radius: 14px;
                                font-weight: 700; font-size: 1rem; cursor: pointer;
                            ">Got it!</button>
                    </div>
                `;
                document.body.appendChild(modal);
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) modal.style.display = 'none';
                });
            }

            async function logout() {
                try {
                    await fetch('/api/logout.php');
                    window.location.href = '/login.php';
                } catch (error) {
                    console.error('Logout error:', error);
                    window.location.href = '/login.php';
                }
            }

            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                if (!sidebar) return;
                const isNowCollapsed = sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', isNowCollapsed);
            }

            function toggleMobileSidebar() {
                if (!sidebar || !overlay) return;
                const isVisible = sidebar.classList.toggle('sidebar-visible');
                overlay.classList.toggle('active');
                document.body.style.overflow = isVisible ? 'hidden' : '';
            }

            // Check and apply state on load
            window.addEventListener('DOMContentLoaded', () => {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed && sidebar) {
                    sidebar.classList.add('collapsed');
                }

                // Remove the temporary pre-load style if it exists
                document.querySelectorAll('head style').forEach(s => {
                    if (s.innerHTML.includes('#sidebar { width: var(--sidebar-collapsed-width')) s.remove();
                });

                // Small delay to re-enable transitions after initial state check
                setTimeout(() => {
                    if (sidebar) sidebar.classList.remove('sidebar-no-transition');
                }, 100);
            });

            // Global alert helper
            function showAlert(message, type = 'info') {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} fade-in`;
                alertDiv.innerHTML = `
                <i class="fa-solid fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;

                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.insertBefore(alertDiv, mainContent.firstChild);

                    // Auto remove after 5 seconds
                    setTimeout(() => {
                        alertDiv.style.opacity = '0';
                        alertDiv.style.transform = 'translateY(-10px)';
                        setTimeout(() => alertDiv.remove(), 300);
                    }, 5000);
                }
            }
        </script>
    </body>

    </html>
    <?php
}
?>