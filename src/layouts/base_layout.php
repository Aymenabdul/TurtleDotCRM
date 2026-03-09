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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($pageTitle); ?> | Turtledot CRM</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto+Serif:opsz,wght@8..144,300;400;500;600;700&display=swap"
            rel="stylesheet">

        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#ffffff">
        <link rel="apple-touch-icon" href="/assets/images/turtle_logo_192.png">

        <!-- Font Awesome -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

        <script>
            // Register Service Worker for PWA
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then(reg => console.log('Service Worker registered'))
                        .catch(err => console.log('Service Worker registration failed', err));
                });
            }
        </script>

        <style>
            /* ===== CSS Variables (Theme) ===== */
            :root {
                /* Primary Colors */
                --primary: #10b981;
                --primary-dark: #059669;
                --primary-light: #34d399;
                --primary-bg: #ecfdf5;
                --primary-bg-light: #d1fae5;

                /* Text Colors */
                --text-main: #1f2937;
                --text-muted: #6b7280;
                --text-light: #9ca3af;

                /* Background Colors */
                --bg-main: #ffffff;
                --bg-secondary: #f9fafb;
                --bg-tertiary: #f3f4f6;

                /* Input Colors */
                --input-bg: #f3f4f6;
                --input-border: #e5e7eb;
                --input-focus-border: var(--primary);
                --focus-ring: rgba(16, 185, 129, 0.2);

                /* Border & Shadow */
                --border-color: #e5e7eb;
                --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);

                /* Status Colors */
                --success: #10b981;
                --success-bg: #ecfdf5;
                --success-border: #a7f3d0;
                --error: #dc2626;
                --error-bg: #fef2f2;
                --error-border: #fecaca;
                --warning: #f59e0b;
                --warning-bg: #fffbeb;
                --warning-border: #fde68a;
                --info: #3b82f6;
                --info-bg: #eff6ff;
                --info-border: #bfdbfe;

                /* Spacing */
                --spacing-xs: 0.25rem;
                --spacing-sm: 0.5rem;
                --spacing-md: 1rem;
                --spacing-lg: 1.5rem;
                --spacing-xl: 2rem;
                --spacing-2xl: 3rem;

                /* Border Radius */
                --radius-sm: 6px;
                --radius-md: 10px;
                --radius-lg: 12px;
                --radius-xl: 16px;

                /* Transitions */
                --transition-fast: 0.15s ease;
                --transition-base: 0.2s ease;
                --transition-slow: 0.3s ease;

                /* Sidebar */
                --sidebar-width: 320px;
                --sidebar-collapsed-width: 80px;
            }

            /* ── Global Pulse Notification Toasts ── */
            #pulseNotificationStack {
                position: fixed;
                top: 24px;
                right: 24px;
                z-index: 99999;
                display: flex;
                flex-direction: column;
                gap: 12px;
                pointer-events: none;
            }

            .pulse-toast {
                width: 310px;
                max-width: calc(100vw - 32px);
                background: rgba(255, 255, 255, 0.75);
                backdrop-filter: blur(24px) saturate(200%);
                -webkit-backdrop-filter: blur(24px) saturate(200%);
                border: 1px solid rgba(255, 255, 255, 0.4);
                border-radius: 20px;
                box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.05), inset 0 0 0 1px rgba(255, 255, 255, 0.5);
                padding: 12px;
                display: flex;
                align-items: center;
                gap: 12px;
                pointer-events: auto;
                cursor: pointer;
                animation: toast-in 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                position: relative;
            }

            .pulse-toast:hover {
                transform: translateY(-4px) scale(1.02);
                background: rgba(255, 255, 255, 0.85);
                box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
                border-color: rgba(16, 185, 129, 0.4);
            }

            .pulse-toast::before {
                content: '';
                position: absolute;
                width: 3px;
                height: 24px;
                left: 0;
                top: 50%;
                transform: translateY(-50%);
                background: linear-gradient(to bottom, #10b981, #3b82f6);
                border-radius: 0 3px 3px 0;
            }

            .pulse-toast-avatar {
                width: 40px;
                height: 40px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 800;
                font-size: 1rem;
                flex-shrink: 0;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                background: linear-gradient(135deg, #10b981, #059669);
                border: 1.5px solid rgba(255, 255, 255, 0.2);
            }

            .pulse-toast-content {
                flex: 1;
                min-width: 0;
            }

            .pulse-toast-title {
                font-size: 0.85rem;
                font-weight: 700;
                color: #1a1c1e;
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 1px;
            }

            .pulse-toast-time {
                font-size: 0.65rem;
                color: #8e9196;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }

            .pulse-toast-body {
                font-size: 0.85rem;
                color: #44474e;
                line-height: 1.4;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-weight: 400;
            }

            .pulse-toast-close {
                position: absolute;
                top: 8px;
                right: 8px;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                background: rgba(0, 0, 0, 0.04);
                border: none;
                color: #666;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.7rem;
                cursor: pointer;
                opacity: 0;
                transition: all 0.2s;
            }

            .pulse-toast:hover .pulse-toast-close {
                opacity: 1;
            }

            @keyframes toast-in {
                from {
                    opacity: 0;
                    transform: translateX(40px) scale(0.95);
                    filter: blur(10px);
                }

                to {
                    opacity: 1;
                    transform: translateX(0) scale(1);
                    filter: blur(0);
                }
            }

            @keyframes toast-out {
                to {
                    opacity: 0;
                    transform: scale(0.9) translateY(-20px);
                }
            }

            /* ===== Global Reset ===== */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', sans-serif;
                background-color: #f8fafc;
                color: var(--text-main);
                min-height: 100vh;
                line-height: 1.6;
                display: flex;
            }

            /* ===== Sidebar ===== */
            .sidebar {
                width: var(--sidebar-width);
                height: calc(100vh - 2rem);
                position: fixed;
                left: 1rem;
                top: 1rem;
                display: flex;
                flex-direction: column;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 2000;
                overflow: visible !important;
                background: transparent;
            }

            .sidebar-glass {
                position: absolute;
                inset: 0;
                background: rgba(119, 241, 133, 0.31);
                backdrop-filter: blur(20px) saturate(180%);
                -webkit-backdrop-filter: blur(20px) saturate(180%);
                border: 1px solid rgba(255, 255, 255, 0.32);
                border-radius: 20px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
                z-index: -1;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                pointer-events: none;
            }

            .sidebar::before {
                display: none;
            }

            .sidebar.collapsed {
                width: var(--sidebar-collapsed-width);
                align-items: center;
            }

            .sidebar-no-transition,
            .sidebar-no-transition *,
            .sidebar-no-transition+.main-wrapper {
                transition: none !important;
            }

            /* Sidebar Header */
            .sidebar-header {
                padding: 1.5rem 2rem;
                display: flex;
                align-items: center;
                gap: 1.25rem;
                height: auto;
                flex-shrink: 0;
                justify-content: flex-start;
            }

            .sidebar-logo {
                width: 50px;
                height: auto;
                transition: transform var(--transition-base);
                filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.7));
            }

            .sidebar-title {
                opacity: 1;
                transition: opacity var(--transition-base), width var(--transition-base);
                height: 120px;
                object-fit: contain;
                margin-top: -10%;
                margin-bottom: -18%;
                margin-left: -6%;
                filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.7));
            }

            .sidebar.collapsed .sidebar-header {
                justify-content: center;
                padding: 1.5rem 0;
            }

            .sidebar.collapsed .sidebar-logo {
                width: 52px;
                margin: 0 !important;
                filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.7));
            }

            .sidebar.collapsed .sidebar-title {
                display: none;
            }

            /* Sidebar Navigation */
            .sidebar-nav {
                flex: 1;
                padding: 0.5rem 1rem;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 0.2rem;
            }

            .nav-item {
                display: flex !important;
                flex-direction: row !important;
                align-items: center;
                gap: 1rem;
                padding: 0.75rem 1rem;
                color: #64748b;
                text-decoration: none !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                white-space: nowrap;
                font-weight: 700;
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                position: relative;
                border-radius: 12px;
                margin: 4px 0;
                border: 1px solid transparent;
            }

            .nav-item:hover {
                background: rgba(255, 255, 255, 0.4);
                color: #1e293b;
                transform: translateX(4px);
            }

            .nav-item.active {
                background: #ffffff !important;
                color: var(--primary) !important;
                font-weight: 800;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
                border: 1px solid #ffffff;
            }

            .nav-item.active i,
            .nav-item.active span {
                color: var(--primary) !important;
                opacity: 1;
            }

            .nav-item.active::after {
                content: '';
                position: absolute;
                left: 0;
                top: 25%;
                height: 50%;
                width: 3px;
                background: var(--primary);
                border-radius: 0 4px 4px 0;
                box-shadow: 0 0 10px var(--primary);
            }

            .nav-item i {
                width: 24px;
                font-size: 1.1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
                color: inherit;
                opacity: 0.7;
            }

            .nav-item.active i {
                opacity: 1;
                transform: scale(1.1);
            }

            .team-indicator {
                position: relative;
                width: 34px;
                height: 34px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 10px;
                background: rgba(255, 255, 255, 0.6);
                border: 1px solid rgba(255, 255, 255, 0.8);
                font-weight: 800;
                font-size: 0.95rem;
                transition: all 0.3s ease;
                color: var(--primary);
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            }

            .team-indicator .status-indicator {
                position: absolute;
                top: -2px;
                right: -2px;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #10b981;
                border: 2px solid #ffffff;
                box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
                z-index: 2;
                transition: all 0.3s ease;
            }

            .team-indicator .status-indicator.decommissioned {
                background: #ef4444;
                box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
            }

            .nav-item.active .team-indicator {
                background: #ffffff;
                border-color: #ffffff;
                box-shadow: 0 4px 10px rgba(16, 185, 129, 0.1);
            }

            .sidebar.collapsed .nav-item-text {
                display: none;
            }

            .sidebar.collapsed .nav-item {
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0 !important;
                margin: 0.5rem auto;
                width: 54px;
                height: 54px;
                border-radius: 18px;
            }

            .sidebar.collapsed .nav-item i {
                margin: 0 !important;
                font-size: 1.3rem;
            }

            .sidebar.collapsed .nav-item .team-indicator {
                margin: 0;
                width: 44px;
                height: 44px;
                border-radius: 12px;
                font-size: 1.1rem;
                background: rgba(255, 255, 255, 0.6);
                border: 1px solid rgba(255, 255, 255, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .sidebar.collapsed .nav-item.active {
                background: rgba(16, 185, 129, 0.1);
                border: 1px solid rgba(16, 185, 129, 0.2);
            }

            .sidebar.collapsed .nav-item.active::before {
                left: 0;
                height: 32px;
                top: 50%;
                transform: translateY(-50%);
                border-radius: 0 6px 6px 0;
                width: 6px;
                background: var(--primary);
                box-shadow: 2px 0 15px rgba(16, 185, 129, 0.4);
            }

            .sidebar.collapsed .sidebar-nav {
                padding: 1.5rem 0;
                align-items: center;
                gap: 0.75rem;
            }

            .sidebar-footer {
                padding: 1.5rem 1.25rem;
                background: transparent;
                border-top: 1px solid rgba(0, 0, 0, 0.05);
            }

            .sidebar-user-wrapper {
                position: relative;
                width: 100%;
            }

            .sidebar-user {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding: 0.75rem;
                background: rgba(255, 255, 255, 0.4);
                border-radius: 18px;
                border: 1px solid rgba(255, 255, 255, 0.5);
                cursor: pointer;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                user-select: none;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            }

            .sidebar-user:hover {
                background: rgba(255, 255, 255, 0.8);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            }

            .sidebar.collapsed .sidebar-user-wrapper {
                margin-bottom: 1rem;
                display: flex;
                justify-content: center;
            }

            .sidebar.collapsed .sidebar-user {
                padding: 0;
                justify-content: center;
                width: 50px;
                height: 50px;
                display: flex;
                align-items: center;
                border-radius: 14px;
                background: rgba(255, 255, 255, 0.4);
                border: 1px solid rgba(255, 255, 255, 0.6);
            }

            .sidebar.collapsed .sidebar-user-info,
            .sidebar.collapsed .sidebar-user-role {
                display: none !important;
                visibility: hidden;
                opacity: 0;
                width: 0;
                height: 0;
                margin: 0;
                padding: 0;
            }

            .sidebar-user-avatar {
                width: 42px;
                height: 42px;
                border-radius: 12px;
                background: rgba(255, 255, 255, 0.8);
                backdrop-filter: blur(8px);
                border: 1px solid rgba(255, 255, 255, 0.9);
                color: var(--primary);
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                font-size: 1.1rem;
                flex-shrink: 0;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                transition: all 0.3s ease;
                position: relative;
            }

            .sidebar-user-avatar::before {
                content: '';
                position: absolute;
                inset: -2px;
                border: 2px solid rgba(255, 255, 255, 0.6);
                border-radius: 14px;
                opacity: 0.7;
            }

            .sidebar-user-avatar:hover {
                background: rgba(255, 255, 255, 0.8);
                transform: scale(1.05);
            }

            .sidebar-user-info {
                flex: 1;
                min-width: 0;
                transition: all 0.3s ease;
            }

            .sidebar-user-name {
                font-weight: 700;
                color: #1e293b;
                font-size: 0.85rem;
                line-height: 1.2;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }

            .sidebar-user-role {
                font-size: 0.65rem;
                font-weight: 700;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-top: 2px;
            }

            /* User Dropdown Menu */
            .user-dropdown-menu {
                position: absolute;
                bottom: calc(100% + 8px);
                left: 0;
                width: 100%;
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                border: 1px solid #e2e8f0;
                opacity: 0;
                visibility: hidden;
                transform: translateY(10px);
                transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
                z-index: 1050;
                padding: 0.4rem;
            }

            .sidebar-user-wrapper.active .user-dropdown-menu {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            .sidebar.collapsed .user-dropdown-menu {
                left: 50%;
                transform: translateX(-50%) translateY(10px);
                width: 50px;
                padding: 0.5rem 0;
            }

            .sidebar.collapsed .sidebar-user-wrapper.active .user-dropdown-menu {
                transform: translateX(-50%) translateY(0);
            }

            .sidebar.collapsed .dropdown-item {
                justify-content: center;
                padding: 0.75rem 0;
            }

            .sidebar.collapsed .dropdown-item span {
                display: none;
            }

            .sidebar.collapsed .dropdown-item i {
                margin: 0;
                font-size: 1.25rem;
            }

            .dropdown-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem 1rem;
                color: #334155;
                text-decoration: none;
                border-radius: 8px;
                transition: all var(--transition-base);
                font-weight: 700;
                font-size: 0.82rem;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                cursor: pointer;
                width: 100%;
                background: none;
                border: none;
                text-align: left;
            }

            .dropdown-item:hover {
                background: #f8fafc;
                color: #0f172a;
            }

            .dropdown-item i {
                font-size: 1.1rem;
                width: 20px;
                text-align: center;
                color: #64748b;
                transition: all 0.2s ease;
            }

            .dropdown-item:hover i {
                color: var(--primary);
                transform: scale(1.1);
            }

            .dropdown-item.danger {
                color: #ef4444;
            }

            .dropdown-item.danger i {
                color: #ef4444;
            }

            .dropdown-item.danger:hover {
                background: #fef2f2;
                color: #dc2626;
            }

            .sidebar-user-role {
                font-size: 0.65rem;
                font-weight: 700;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-top: 2px;
            }

            /* Center Edge Arrow Toggle Button */
            .sidebar-center-toggle {
                position: absolute;
                top: 50%;
                left: calc(100% - 13px);
                transform: translateY(-50%) translateZ(0);
                width: 26px;
                height: 26px;
                background: #ffffff;
                border: 2px solid #10b981;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 4px 10px rgba(16, 185, 129, 0.15);
                color: #10b981;
                z-index: 10000;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                overflow: hidden;
            }

            .sidebar-center-toggle::after {
                content: '';
                position: absolute;
                inset: 0;
                background: #10b981;
                transform: scale(0);
                border-radius: 50%;
                transition: transform 0.3s ease;
                z-index: -1;
            }

            .sidebar-center-toggle:hover {
                color: white;
                transform: translateY(-50%) scale(1.15);
                box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
            }

            .sidebar-center-toggle:hover::after {
                transform: scale(1);
            }

            .sidebar-center-toggle i {
                font-size: 0.65rem;
                font-weight: 900;
                transition: transform 0.6s cubic-bezier(0.68, -0.6, 0.32, 1.6);
                transform: translateX(-0.5px);
            }

            .sidebar.collapsed .sidebar-center-toggle i {
                transform: rotate(180deg) translateX(-0.5px);
            }

            .sidebar-section-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin: 1.5rem 1rem 0.5rem;
                transition: all 0.3s ease;
            }

            .sidebar-section-title {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                position: relative;
            }

            .sidebar-section-title i {
                font-size: 1rem;
                color: #94a3b8;
                opacity: 0.8;
            }

            .sidebar-section-title span {
                font-size: 0.65rem;
                font-weight: 800;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.15em;
            }

            .sidebar.collapsed .sidebar-section-header {
                justify-content: center;
                margin: 1.5rem 0 0.5rem;
            }

            .sidebar.collapsed .sidebar-section-title span {
                display: none;
            }

            /* Global Glass Modal System */
            .glass-modal {
                background: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
                display: none;
                position: fixed;
                inset: 0;
                z-index: 9999;
                align-items: center;
                justify-content: center;
                padding: 2rem;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .glass-modal.show {
                display: flex;
                opacity: 1;
            }

            @keyframes modalScaleIn {
                from {
                    transform: scale(0.9) translateY(20px);
                    opacity: 0;
                }

                to {
                    transform: scale(1) translateY(0);
                    opacity: 1;
                }
            }


            .sidebar-section-title {
                display: flex;
                align-items: center;
                gap: 0.85rem;
                transition: all 0.3s ease;
                position: relative;
            }


            .sidebar-section-title span {
                font-size: 0.65rem;
                font-weight: 900;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.2em;
                opacity: 0.8;
            }

            .sidebar.collapsed .sidebar-section-header {
                justify-content: center;
                margin: 2rem 0 1rem;
            }

            .sidebar.collapsed .sidebar-section-title span,
            .sidebar.collapsed .sidebar-section-header i:last-child {
                display: none !important;
            }

            .sidebar.collapsed .sidebar-section-title {
                justify-content: center;
                gap: 0;
            }

            /* ===== Main Content Area ===== */
            .main-wrapper {
                flex: 1;
                margin-left: calc(var(--sidebar-width) + 1rem);
                /* Space for floating island */
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            .sidebar.collapsed+.main-wrapper {
                margin-left: calc(var(--sidebar-collapsed-width) + 1rem);
            }

            .main-content {
                flex: 1;
                padding: var(--spacing-2xl);
                max-width: 1400px;
                width: 100%;
            }

            /* ===== Utility Classes ===== */

            /* Cards */
            .card {
                background: var(--bg-main);
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow-md);
                padding: var(--spacing-xl);
                border: 1px solid var(--border-color);
                transition: box-shadow var(--transition-base);
            }

            .card:hover {
                box-shadow: var(--shadow-lg);
            }

            .card-header {
                margin-bottom: var(--spacing-lg);
                padding-bottom: var(--spacing-lg);
                border-bottom: 2px solid var(--border-color);
            }

            .card-title {
                font-size: 1.5rem;
                font-weight: 700;
                color: var(--text-main);
                margin-bottom: var(--spacing-sm);
            }

            .card-subtitle {
                color: var(--text-muted);
                font-size: 0.95rem;
            }

            /* Buttons */
            .btn {
                padding: 0.75rem 1.5rem;
                border-radius: var(--radius-lg);
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all var(--transition-base);
                border: none;
                font-family: inherit;
                display: inline-flex;
                align-items: center;
                gap: var(--spacing-sm);
                text-decoration: none;
            }

            .btn-primary {
                background: var(--primary);
                color: white;
            }

            .btn-primary:hover {
                background: var(--primary-dark);
                transform: translateY(-1px);
                box-shadow: var(--shadow-md);
            }

            .btn-secondary {
                background: var(--bg-tertiary);
                color: var(--text-main);
            }

            .btn-secondary:hover {
                background: var(--input-border);
            }

            .btn-danger {
                background: var(--error);
                color: white;
            }

            .btn-danger:hover {
                background: #b91c1c;
                transform: translateY(-1px);
            }

            .btn-success {
                background: var(--success);
                color: white;
            }

            .btn-success:hover {
                background: var(--primary-dark);
                transform: translateY(-1px);
            }

            .btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none !important;
            }

            .btn-sm {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .btn-lg {
                padding: 1rem 2rem;
                font-size: 1.125rem;
            }

            /* Form Elements */
            .form-group {
                margin-bottom: var(--spacing-lg);
            }

            .form-label {
                display: block;
                margin-bottom: var(--spacing-sm);
                font-weight: 500;
                color: var(--text-main);
                font-size: 0.9rem;
            }

            .form-control {
                width: 100%;
                padding: 1rem 1.25rem;
                border: 2px solid transparent;
                background-color: var(--input-bg);
                border-radius: var(--radius-lg);
                font-size: 1rem;
                color: var(--text-main);
                transition: all var(--transition-base);
                font-family: inherit;
            }

            .form-control:focus {
                outline: none;
                background-color: var(--bg-main);
                border-color: var(--input-focus-border);
                box-shadow: 0 0 0 4px var(--focus-ring);
            }

            .form-control::placeholder {
                color: var(--text-light);
            }

            select.form-control {
                cursor: pointer;
            }

            textarea.form-control {
                resize: vertical;
                min-height: 120px;
            }

            /* Alerts */
            .alert {
                padding: var(--spacing-md) var(--spacing-lg);
                border-radius: var(--radius-md);
                margin-bottom: var(--spacing-lg);
                font-size: 0.95rem;
                display: flex;
                align-items: center;
                gap: var(--spacing-sm);
                border: 1px solid;
            }

            .alert-success {
                background-color: var(--success-bg);
                color: var(--success);
                border-color: var(--success-border);
            }

            .alert-error {
                background-color: var(--error-bg);
                color: var(--error);
                border-color: var(--error-border);
            }

            .alert-warning {
                background-color: var(--warning-bg);
                color: var(--warning);
                border-color: var(--warning-border);
            }

            .alert-info {
                background-color: var(--info-bg);
                color: var(--info);
                border-color: var(--info-border);
            }

            /* Tables */
            .table-container {
                overflow-x: auto;
                border-radius: var(--radius-lg);
                border: 1px solid var(--border-color);
            }

            table {
                width: 100%;
                border-collapse: collapse;
                background: var(--bg-main);
            }

            thead {
                background: var(--primary-bg);
            }

            th {
                padding: var(--spacing-md) var(--spacing-lg);
                text-align: left;
                font-weight: 600;
                color: var(--primary-dark);
                border-bottom: 2px solid var(--primary);
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            td {
                padding: var(--spacing-md) var(--spacing-lg);
                border-bottom: 1px solid var(--border-color);
                color: var(--text-main);
            }

            tr:last-child td {
                border-bottom: none;
            }

            tbody tr {
                transition: background-color var(--transition-fast);
            }

            tbody tr:hover {
                background-color: var(--bg-secondary);
            }

            /* Badges */
            .badge {
                display: inline-flex;
                align-items: center;
                padding: 0.25rem 0.75rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .badge-success {
                background: var(--success-bg);
                color: var(--success);
            }

            .badge-error {
                background: var(--error-bg);
                color: var(--error);
            }

            .badge-warning {
                background: var(--warning-bg);
                color: var(--warning);
            }

            .badge-info {
                background: var(--info-bg);
                color: var(--info);
            }

            /* Grid System */
            .grid {
                display: grid;
                gap: var(--spacing-xl);
            }

            .grid-2 {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }

            .grid-3 {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }

            .grid-4 {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            /* Flex Utilities */
            .flex {
                display: flex;
            }

            .flex-between {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .flex-center {
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .flex-gap {
                gap: var(--spacing-md);
            }

            /* Spacing Utilities */
            .mt-1 {
                margin-top: var(--spacing-sm);
            }

            .mt-2 {
                margin-top: var(--spacing-md);
            }

            .mt-3 {
                margin-top: var(--spacing-lg);
            }

            .mt-4 {
                margin-top: var(--spacing-xl);
            }

            .mb-1 {
                margin-bottom: var(--spacing-sm);
            }

            .mb-2 {
                margin-bottom: var(--spacing-md);
            }

            .mb-3 {
                margin-bottom: var(--spacing-lg);
            }

            .mb-4 {
                margin-bottom: var(--spacing-xl);
            }

            /* Text Utilities */
            .text-center {
                text-align: center;
            }

            .text-right {
                text-align: right;
            }

            .text-muted {
                color: var(--text-muted);
            }

            .text-primary {
                color: var(--primary);
            }

            .text-error {
                color: var(--error);
            }

            .text-success {
                color: var(--success);
            }

            /* Animations */
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .fade-in {
                animation: fadeIn var(--transition-slow) ease-out;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .sidebar {
                    width: var(--sidebar-collapsed-width);
                }

                .sidebar-title,
                .nav-item-text,
                .sidebar-user-info,
                .sidebar-toggle-text {
                    opacity: 0;
                    width: 0;
                }

                .nav-item {
                    padding: var(--spacing-md) var(--spacing-lg);
                    justify-content: center;
                }

                .main-wrapper {
                    margin-left: var(--sidebar-collapsed-width);
                }

                .navbar-container {
                    padding: 0 var(--spacing-md);
                }

                .navbar-title {
                    font-size: 1.2rem;
                }

                .navbar-logo {
                    width: 35px;
                }

                .main-content {
                    padding: var(--spacing-lg);
                }

                .card {
                    padding: var(--spacing-lg);
                }

                .grid-2,
                .grid-3,
                .grid-4 {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>


    <body>
        <script>
            // Immediate execution to prevent sidebar flicker
            (function () {
                const sidebarState = localStorage.getItem('sidebarCollapsed');
                if (sidebarState === 'true') {
                    // We add a temporary style to the head instead of document.write
                    const style = document.createElement('style');
                    style.innerHTML = '#sidebar { width: 80px !important; } .main-wrapper { margin-left: 80px !important; }';
                    document.head.appendChild(style);
                }
            })();
        </script>
        <?php
        if ($includeNavbar) {
            global $pdo;
            $currentPage = $GLOBALS['currentPage'] ?? '';
            $contextTeamId = $_GET['team_id'] ?? $_GET['id'] ?? $_SESSION['last_team_id'] ?? null;
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

            function showSystemNotification(title, message) {
                // OS-level notifications are handled in real-time by the Service Worker (sw.js).
                // The poller here strictly handles the in-app Pulse UI.
                showPulseToast(title, message);
            }

            function showPulseToast(title, body) {
                const stack = document.getElementById('pulseNotificationStack');
                if (!stack || !title) return;

                const toast = document.createElement('div');
                toast.className = 'pulse-toast';

                const initials = title.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
                const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });

                toast.innerHTML = `
                    <div class="pulse-toast-avatar">${initials}</div>
                    <div class="pulse-toast-content">
                        <div class="pulse-toast-title">
                            <span>${title}</span>
                            <span class="pulse-toast-time">${time}</span>
                        </div>
                        <div class="pulse-toast-body">${body}</div>
                    </div>
                    <button class="pulse-toast-close" onclick="event.stopPropagation(); this.closest('.pulse-toast').remove();"><i class="fa-solid fa-xmark"></i></button>
                `;

                toast.onclick = () => {
                    window.location.href = '/tools/chat.php';
                };

                stack.prepend(toast);
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.style.animation = 'toast-out 0.4s forwards';
                        setTimeout(() => toast.remove(), 400);
                    }
                }, 8000);
            }

            let isFirstCheck = true;

            async function checkGlobalUnreads() {
                try {
                    const res = await fetch('/api/global_status.php');
                    const json = await res.json();
                    if (!json.success) return;

                    let totalUnread = 0;
                    let shouldNotify = !isFirstCheck;

                    // Handle DMs
                    json.unread_dms.forEach(d => {
                        const key = `dm-${d.user_id}`;
                        totalUnread += (d.unread_count || 0);
                        // Only show toast if NOT on the chat page (chat.php handles its own toasts)
                        if (!isChatPage && shouldNotify && (d.unread_count || 0) > (lastUnreadState[key] || 0)) {
                            showSystemNotification(d.full_name || d.username, 'Sent you a message');
                        }
                        lastUnreadState[key] = (d.unread_count || 0);
                    });

                    // Handle Channels
                    json.unread_channels.forEach(c => {
                        const key = `chan-${c.name}`;
                        totalUnread += (c.unread_count || 0);
                        // Only show toast if NOT on the chat page
                        if (!isChatPage && shouldNotify && (c.unread_count || 0) > (lastUnreadState[key] || 0)) {
                            showSystemNotification(`#${c.name}`, 'New activity');
                        }
                        lastUnreadState[key] = (c.unread_count || 0);
                    });

                    // Update Sidebar Badge
                    const badge = document.getElementById('global-chat-badge');
                    if (badge) {
                        if (totalUnread > 0) {
                            badge.textContent = totalUnread;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }

                    isFirstCheck = false;
                } catch (e) {
                    console.error('Check unreads failed', e);
                }
            }

            async function syncPushSubscription() {
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
                try {
                    const reg = await navigator.serviceWorker.ready;
                    let sub = await reg.pushManager.getSubscription();
                    if (!sub) {
                        const key = 'BOT34D3Wld3Hw7tnAThhk6XfrY3t-PZ1hMMr6BJJNC6oA0Yx9s6bw4NGF1J9AOvohWXt5y-BSOWXtK9LUftWj7E';
                        sub = await reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: (() => {
                                const padding = '='.repeat((4 - key.length % 4) % 4);
                                const base64 = (key + padding).replace(/\-/g, '+').replace(/_/g, '/');
                                const raw = window.atob(base64);
                                return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
                            })()
                        });
                    }
                    await fetch('/api/push_subscription.php', {
                        method: 'POST',
                        body: JSON.stringify({ subscription: sub }),
                        headers: { 'Content-Type': 'application/json' }
                    });
                } catch (e) { console.warn('Global push sync failed'); }
            }

            // Start global background processes
            setInterval(checkGlobalUnreads, 5000);
            syncPushSubscription();

            // Handle browser notification permissions globally
            if ("Notification" in window && Notification.permission === "default") {
                Notification.requestPermission();
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

            function toggleSidebar() {
                if (!sidebar) return;
                const isNowCollapsed = sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', isNowCollapsed);
            }

            // Check and apply state on load
            window.addEventListener('DOMContentLoaded', () => {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed && sidebar) {
                    sidebar.classList.add('collapsed');
                }

                // Remove the temporary pre-load style if it exists
                document.querySelectorAll('head style').forEach(s => {
                    if (s.innerHTML.includes('#sidebar { width: 80px')) s.remove();
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