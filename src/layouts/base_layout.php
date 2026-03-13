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
            href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto+Serif:opsz,wght@8..144,300..700&display=swap"
            rel="stylesheet">

        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json?v=15">
        <meta name="theme-color" content="#10b981">

        <!-- PWA / iOS Tags -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Turtledot Workspace">
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
                    } else if (msgType === 'vault_sync') {
                        console.log('Vault: Real-time update triggered via SW');
                        if (typeof window.loadVault === 'function') window.loadVault();
                    }
                });
            }
        </script>

        <link rel="stylesheet" href="/css/base_layout.css">
    </head>


    <body class="<?php echo (isset($user['role']) && strtolower(trim($user['role'])) === 'admin') ? 'role-admin' : 'role-user'; ?>">
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
                    </div>
                `;

                toast.onclick = () => {
                    window.focus();
                    window.location.href = '/tools/chat.php';
                };

                stack.prepend(toast);
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.style.animation = 'ios-out 0.2s forwards';
                        setTimeout(() => toast.remove(), 200);
                    }
                }, 5000); // Reduced display time slightly too for snappiness
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

            let isRegisteringFCM = false;
            async function requestFCMToken(showToast = false) {
                if (isRegisteringFCM) return;
                console.log('Chat: Starting FCM registration...');
                isRegisteringFCM = true;
                try {
                    // Check environment
                    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone || document.referrer.includes('android-app://');
                    console.log('Chat: Environment IsStandalone:', isStandalone);
                    const reg = await navigator.serviceWorker.ready;

                    const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    // 1. Get FCM Token
                    const token = await messaging.getToken({
                        vapidKey: VAPID_PUBLIC_KEY || 'BMaKPqcVHa4nu58Vr41ychJtjt5fwzC3iAkr-pIQdUG_Veni0c57Kn45Gu8jdyuSEr-erUvyzo3hSSCaOMbR8kU',
                        serviceWorkerRegistration: reg
                    });

                    // 2. Handle Native Browser Push Subscription
                    // Always try to get existing first
                    let sub = await reg.pushManager.getSubscription();
                    console.log('Chat: Current push subscription:', sub ? 'Found' : 'Missing');
                    
                    // If we have an existing sub, we test it or refresh it
                    if (sub) {
                        try {
                            await sendSubscriptionToServer(sub, token);
                        } catch (e) {
                            console.warn('Chat: Stale subscription, unsubscribing and retrying');
                            await sub.unsubscribe();
                            sub = await reg.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: VAPID_PUBLIC_KEY
                            });
                            await sendSubscriptionToServer(sub, token);
                        }
                    } else {
                        console.log('Chat: Creating fresh push subscription');
                        sub = await reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: VAPID_PUBLIC_KEY
                        });
                        await sendSubscriptionToServer(sub, token);
                    }

                    // Mark as registered in this environment
                    localStorage.setItem('fcm_registered_v1', 'true');
                    console.log('Chat: FCM registration successful');

                    // Clean up modal if visible
                    const modal = document.getElementById('notif-permission-modal');
                    if (modal) modal.remove();
                    if (showToast && typeof Toast !== 'undefined') Toast.success('Success', 'Notifications enabled!');
                }
                } catch (e) {
                    console.error('Chat: FCM registration failed', e);
                } finally {
                    isRegisteringFCM = false;
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

            // 🔔 Mandatory Notification Permission Modal
            // Centered and premium-styled to ensure users enable alerts.
            function initPermissionBanner() {
                const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
                if (!('Notification' in window)) return;
                
                const isRegistered = localStorage.getItem('fcm_registered_v1');
                const permission = Notification.permission;
                
                // 1. If already granted, just ensure we're synced and exit
                if (permission === 'granted') {
                    if (!isRegistered) requestFCMToken(false);
                    return;
                }
                
                // 2. If denied, don't nag the user unless they were already registered and somehow lost permission
                if (permission === 'denied') return;

                // 3. Show modal if we haven't asked yet OR if we are in standalone mode and not yet registered
                // This ensures that once installed, the user is immediately prompted to enable notifications.
                if (permission === 'default' || (isStandalone && !isRegistered)) {
                    // SESSION GUARD: Don't show again in the same session if dismissed
                    if (sessionStorage.getItem('notif_modal_dismissed')) return;

                    setTimeout(() => {
                        if (document.getElementById('notif-permission-modal')) return;

                        const modalHtml = `
                        <div id="notif-permission-modal" style="
                            position: fixed; inset: 0; z-index: 20000;
                            background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px);
                            display: flex; align-items: center; justify-content: center;
                            padding: 20px; animation: pulse-fade-in 0.2s ease;
                        ">
                            <div style="
                                background: white; width: 100%; max-width: 360px;
                                border-radius: 28px; padding: 32px; text-align: center;
                                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                                animation: pulse-pop-in 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                            ">
                                <div style="
                                    width: 80px; height: 80px; background: #f0fdf4;
                                    border-radius: 24px; display: flex; align-items: center;
                                    justify-content: center; margin: 0 auto 24px;
                                ">
                                    <i class="fa-solid fa-bell-concierge" style="font-size: 32px; color: #10b981;"></i>
                                </div>
                                <h3 style="font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-bottom: 12px; font-family: 'Inter', sans-serif;">
                                    Enable Alerts
                                </h3>
                                <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6; margin-bottom: 32px; font-family: 'Inter', sans-serif;">
                                    Stay connected with your team. We'll send you real-time updates and chat notifications.
                                </p>
                                <div style="display: flex; flex-direction: column; gap: 12px;">
                                    <button onclick="document.getElementById('notif-permission-modal').remove(); requestFCMToken(true)" style="
                                        width: 100%; padding: 16px; border: none;
                                        background: #10b981; color: white; border-radius: 16px;
                                        font-weight: 700; font-size: 1rem; cursor: pointer;
                                        box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
                                        transition: transform 0.2s ease;
                                    ">Allow Notifications</button>
                                    <button onclick="sessionStorage.setItem('notif_modal_dismissed', 'true'); document.getElementById('notif-permission-modal').remove()" style="
                                        width: 100%; padding: 12px; border: none;
                                        background: transparent; color: #94a3b8; border-radius: 16px;
                                        font-weight: 600; font-size: 0.9rem; cursor: pointer;
                                    ">Maybe Later</button>
                                </div>
                            </div>
                        </div>
                        <style>
                            @keyframes pulse-fade-in { from { opacity: 0; } to { opacity: 1; } }
                            @keyframes pulse-pop-in { 
                                from { opacity: 0; transform: scale(0.9); } 
                                to { opacity: 1; transform: scale(1); } 
                            }
                        </style>`;
                        document.body.insertAdjacentHTML('beforeend', modalHtml);
                    }, 500);
                }
            }

            if (document.readyState === 'complete') {
                initPermissionBanner();
            } else {
                window.addEventListener('load', initPermissionBanner);
            }

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
                const msgType = payload.data ? payload.data.type : null;

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

                if (msgType === 'vault_sync') {
                    console.log('Vault: Real-time update triggered via FCM');
                    if (typeof window.loadVault === 'function') window.loadVault();
                    return;
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

            window.addEventListener('load', () => {
                const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent);
                const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
                const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
                const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

                if (isStandalone) return;

                const installBtns = document.querySelectorAll('.pwa-install-btn');
                
                // Show if iOS OR Mac Safari (since they don't fire beforeinstallprompt)
                if (isIos || (isMac && isSafari)) {
                    installBtns.forEach(btn => {
                        btn.style.display = 'flex';
                        btn.setAttribute('data-device', isIos ? 'ios' : 'mac-safari');
                    });
                }
            });

            // Android / Chrome: fires beforeinstallprompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                const installBtns = document.querySelectorAll('.pwa-install-btn');
                installBtns.forEach(btn => {
                    btn.style.display = 'flex';
                    btn.removeAttribute('data-device');
                });
            });

            window.addEventListener('appinstalled', () => {
                deferredPrompt = null;
                const installBtns = document.querySelectorAll('.pwa-install-btn');
                installBtns.forEach(btn => btn.style.display = 'none');
            });

            async function installPWA() {
                const btn = document.querySelector('.pwa-install-btn');
                const device = btn ? btn.getAttribute('data-device') : null;

                if (device === 'ios') {
                    showIosInstallGuide();
                    return;
                }

                if (device === 'mac-safari') {
                    showMacInstallGuide();
                    return;
                }

                if (!deferredPrompt) {
                    // Fallback Guide if nothing triggered
                    showMacInstallGuide();
                    return;
                }
                
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                const installBtns = document.querySelectorAll('.pwa-install-btn');
                installBtns.forEach(btn => btn.style.display = 'none');
            }

            function showIosInstallGuide() {
                const existing = document.getElementById('pwa-install-modal');
                if (existing) { existing.remove(); }

                const modal = document.createElement('div');
                modal.id = 'pwa-install-modal';
                modal.style.cssText = `
                    position: fixed; inset: 0; z-index: 99999;
                    background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px);
                    display: flex; align-items: center; justify-content: center;
                    padding: 1.5rem;
                `;
                modal.innerHTML = `
                    <div style="
                        background: white; border-radius: 32px; padding: 2.5rem;
                        max-width: 440px; width: 100%; text-align: center;
                        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
                        animation: pulse-pop-in 0.3s ease;
                    ">
                        <img src="/assets/images/turtle_logo_512.png" 
                             style="width: 80px; height: 80px; border-radius: 22px; margin-bottom: 1.5rem; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);">
                        <h3 style="font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-bottom: 0.75rem;">
                            Install Mobile App
                        </h3>
                        <p style="color: #64748b; font-size: 1rem; line-height: 1.6; margin-bottom: 2rem;">
                            Get a native experience with real-time notifications by adding Turtledot to your home screen.
                        </p>
                        <div style="background: #f8fafc; border-radius: 20px; padding: 1.5rem; text-align: left; margin-bottom: 2rem;">
                            <div style="display: flex; align-items: center; gap: 1.25rem; margin-bottom: 1.25rem;">
                                <div style="width: 32px; height: 32px; background: #e0f2fe; color: #0ea5e9; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700;">1</div>
                                <div style="font-size: 0.95rem; color: #334155;">Tap the <strong>Share</strong> button <i class="fa-solid fa-arrow-up-from-bracket" style="color: #0ea5e9; margin-left: 4px;"></i> at the bottom.</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1.25rem;">
                                <div style="width: 32px; height: 32px; background: #f0fdf4; color: #10b981; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700;">2</div>
                                <div style="font-size: 0.95rem; color: #334155;">Select <strong>"Add to Home Screen"</strong> from the menu.</div>
                            </div>
                        </div>
                        <button onclick="document.getElementById('pwa-install-modal').remove()" 
                            style="width: 100%; padding: 1rem; border: none; background: #10b981; color: white; border-radius: 16px; font-weight: 700; font-size: 1.1rem; cursor: pointer;">
                            Got it!
                        </button>
                    </div>
                `;
                document.body.appendChild(modal);
                modal.onclick = (e) => { if(e.target === modal) modal.remove(); };
            }

            function showMacInstallGuide() {
                const existing = document.getElementById('pwa-install-modal');
                if (existing) { existing.remove(); }

                const modal = document.createElement('div');
                modal.id = 'pwa-install-modal';
                modal.style.cssText = `
                    position: fixed; inset: 0; z-index: 99999;
                    background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px);
                    display: flex; align-items: center; justify-content: center;
                    padding: 1.5rem;
                `;
                modal.innerHTML = `
                    <div style="
                        background: white; border-radius: 32px; padding: 2.5rem;
                        max-width: 480px; width: 100%; text-align: center;
                        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
                        animation: pulse-pop-in 0.3s ease;
                    ">
                        <img src="/assets/images/turtle_logo_512.png" 
                             style="width: 80px; height: 80px; border-radius: 22px; margin-bottom: 1.5rem; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);">
                        <h3 style="font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-bottom: 0.75rem;">
                            Install Desktop Workstation
                        </h3>
                        <p style="color: #64748b; font-size: 1rem; line-height: 1.6; margin-bottom: 2rem;">
                            Turn Turtledot into a standalone Mac application for optimized performance and native notifications.
                        </p>
                        <div style="background: #f8fafc; border-radius: 20px; padding: 1.5rem; text-align: left; margin-bottom: 2rem;">
                            <div style="display: flex; align-items: center; gap: 1.25rem; margin-bottom: 1.25rem;">
                                <div style="width: 32px; height: 32px; background: #e0f2fe; color: #0ea5e9; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700;">1</div>
                                <div style="font-size: 0.95rem; color: #334155;">In your browser's top menu, go to <strong>File</strong>.</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1.25rem;">
                                <div style="width: 32px; height: 32px; background: #f0fdf4; color: #10b981; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700;">2</div>
                                <div style="font-size: 0.95rem; color: #334155;">Click <strong>"Add to Dock"</strong> or <strong>"Install Turtledot"</strong>.</div>
                            </div>
                        </div>
                        <button onclick="document.getElementById('pwa-install-modal').remove()" 
                            style="width: 100%; padding: 1rem; border: none; background: #10b981; color: white; border-radius: 16px; font-weight: 700; font-size: 1.1rem; cursor: pointer;">
                            Launch Desktop App
                        </button>
                    </div>
                `;
                document.body.appendChild(modal);
                modal.onclick = (e) => { if(e.target === modal) modal.remove(); };
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