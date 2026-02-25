<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/../src/layouts/base_layout.php';

$user = AuthMiddleware::requireAuth();
$teamId = $_GET['team_id'] ?? null;

if (!$teamId) {
    header("Location: /manage_teams.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$teamId]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        header("Location: /manage_teams.php");
        exit;
    }

    $teamTools = json_decode($team['tools'] ?? '[]', true);
    // Support both the JSON tools array and the legacy column flag
    $isEnabled = in_array('chat', $teamTools) || (isset($team['tool_chat']) && $team['tool_chat'] == 1);

    if (!$isEnabled) {
        die("This tool is not enabled for this team.");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

startLayout("Chat — " . htmlspecialchars($team['name']), $user, false);
?>

<style>
    /* ── Force full-screen for chat ── */
    .main-wrapper {
        margin-left: 0 !important;
    }

    .main-content {
        padding: 0 !important;
        height: 100vh;
        max-width: none !important;
        overflow: hidden;
    }

    /* ── Root layout ── */
    .chat-root {
        display: flex;
        height: 100vh;
        width: 100%;
        font-family: 'Inter', sans-serif;
        background: #f1f5f9;
        overflow: hidden;
    }

    /* ══════════════════ SIDEBAR ══════════════════ */
    .chat-sidebar {
        width: 280px;
        min-width: 280px;
        background: #1e2535;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
        border-right: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Workspace header */
    .ws-header {
        padding: 1.1rem 1.25rem 0.9rem;
        background: #161d2e;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-shrink: 0;
    }

    .ws-logo {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .ws-info {
        flex: 1;
        min-width: 0;
    }

    .ws-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: #ffffff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ws-sub {
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 1px;
    }

    .ws-back {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.07);
        border: none;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.15s;
        text-decoration: none;
        font-size: 0.85rem;
    }

    .ws-back:hover {
        background: rgba(255, 255, 255, 0.14);
        color: #fff;
    }

    /* Sidebar search */
    .sidebar-search {
        padding: 0.75rem 1rem;
        flex-shrink: 0;
    }

    .sidebar-search-inner {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        padding: 7px 12px;
    }

    .sidebar-search-inner i {
        color: #64748b;
        font-size: 0.8rem;
    }

    .sidebar-search-inner input {
        background: transparent;
        border: none;
        outline: none;
        color: #e2e8f0;
        font-size: 0.875rem;
        width: 100%;
    }

    .sidebar-search-inner input::placeholder {
        color: #4a5568;
    }

    /* Section group */
    .sidebar-section {
        flex-shrink: 0;
    }

    .sidebar-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.9rem 1rem 0.35rem;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .sidebar-add-btn {
        width: 20px;
        height: 20px;
        background: none;
        border: none;
        color: #64748b;
        cursor: pointer;
        border-radius: 4px;
        transition: 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
    }

    .sidebar-add-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }

    /* Channel items */
    .sidebar-item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 6px 1rem;
        border-radius: 6px;
        margin: 1px 8px;
        color: #94a3b8;
        cursor: pointer;
        transition: 0.12s;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .sidebar-item:hover {
        background: rgba(255, 255, 255, 0.07);
        color: #e2e8f0;
    }

    .sidebar-item.active {
        background: rgba(16, 185, 129, 0.2);
        color: #34d399;
    }

    .sidebar-item .item-icon {
        font-size: 0.85rem;
        width: 16px;
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-item .item-label {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Unread badge */
    .unread-badge {
        background: #ef4444;
        color: white;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .sidebar-item .unread-badge {
        margin-left: 4px;
    }

    /* Message status */
    .msg-status {
        font-size: 0.7rem;
        margin-left: 4px;
        color: #94a3b8;
    }

    .msg-status.read {
        color: #38bdf8;
    }

    /* Members list section */
    .members-section {
        flex: 1;
        overflow-y: auto;
        min-height: 0;
    }

    .members-section::-webkit-scrollbar {
        width: 4px;
    }

    .members-section::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
    }

    .member-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 1rem;
        border-radius: 6px;
        margin: 1px 8px;
        color: #94a3b8;
        cursor: pointer;
        transition: 0.12s;
        position: relative;
    }

    .member-item:hover {
        background: rgba(255, 255, 255, 0.07);
        color: #e2e8f0;
    }

    .member-delete-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        width: 24px;
        height: 24px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ef4444;
        opacity: 0;
        transition: all 0.2s;
        background: transparent;
        border: none;
        cursor: pointer;
    }

    .member-item:hover .member-delete-btn {
        opacity: 1;
    }

    .member-delete-btn:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
    }

    .member-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        flex-shrink: 0;
        position: relative;
        color: #fff;
    }

    .member-avatar-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #475569;
        border: 2px solid #1e2535;
        position: absolute;
        bottom: -1px;
        right: -1px;
    }

    .member-avatar-dot.online {
        background: #22c55e;
    }

    .member-info {
        min-width: 0;
    }

    .member-name-text {
        font-size: 0.875rem;
        font-weight: 500;
        color: inherit;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .member-role-text {
        font-size: 0.7rem;
        color: #4a5568;
    }

    .member-last-msg {
        font-size: 0.72rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 170px;
    }

    .dm-empty-hint {
        padding: 0.75rem 1rem;
        font-size: 0.78rem;
        color: #4a5568;
        text-align: center;
        line-height: 1.4;
    }

    .dm-empty-hint i {
        display: block;
        font-size: 1.2rem;
        color: #64748b;
        margin-bottom: 0.35rem;
    }

    /* Divider */
    .sidebar-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.06);
        margin: 0.5rem 1rem;
    }

    /* ══════════════════ MAIN CHAT AREA ══════════════════ */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #ffffff;
        min-width: 0;
        overflow: hidden;
    }

    /* Chat header */
    .chat-header {
        height: 64px;
        padding: 0 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        flex-shrink: 0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .chat-header-left {
        display: flex;
        align-items: center;
        gap: 0.875rem;
    }

    .channel-icon-wrap {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: #ecfdf5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #10b981;
        font-size: 1rem;
    }

    .channel-title {
        font-size: 1rem;
        font-weight: 700;
        color: #1f2937;
    }

    .channel-subtitle {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 1px;
    }

    .live-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        background: #ecfdf5;
        color: #059669;
        border-radius: 20px;
        border: 1px solid #d1fae5;
        font-size: 0.72rem;
        font-weight: 600;
    }

    .live-badge-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #22c55e;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.4;
        }
    }

    /* Messages area */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        background: #fafafa;
    }

    .chat-messages::-webkit-scrollbar {
        width: 6px;
    }

    .chat-messages::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }

    /* Message groups */
    .msg-group {
        display: flex;
        gap: 0.75rem;
        padding: 4px 0;
    }

    .msg-group.mine {
        flex-direction: row-reverse;
    }

    .msg-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
        align-self: flex-end;
    }

    .msg-body {
        display: flex;
        flex-direction: column;
        max-width: 65%;
    }

    .msg-group.mine .msg-body {
        align-items: flex-end;
    }

    .msg-group.others .msg-body {
        align-items: flex-start;
    }

    .msg-sender {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 3px;
        padding: 0 12px;
    }

    .msg-bubble {
        padding: 9px 16px;
        border-radius: 18px;
        font-size: 0.9rem;
        line-height: 1.55;
        word-break: break-word;
        position: relative;
        max-width: 100%;
    }

    .msg-group.others .msg-bubble {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        color: #1f2937;
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .msg-group.mine .msg-bubble {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #ffffff;
        border-bottom-right-radius: 4px;
        box-shadow: 0 3px 8px rgba(16, 185, 129, 0.3);
    }

    .msg-time {
        font-size: 0.67rem;
        color: #9ca3af;
        margin-top: 3px;
        padding: 0 4px;
    }

    /* Message hover actions */
    .msg-group {
        position: relative;
    }

    .msg-actions-btn {
        position: absolute;
        top: 0;
        opacity: 0;
        transition: opacity 0.15s;
        width: 26px;
        height: 26px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #6b7280;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        z-index: 10;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .msg-group.mine .msg-actions-btn {
        right: auto;
        left: 0;
    }

    .msg-group.others .msg-actions-btn {
        right: 0;
        left: auto;
    }

    .msg-group:hover .msg-actions-btn {
        opacity: 1;
    }

    /* Message action dropdown */
    .msg-action-menu {
        position: absolute;
        top: 28px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        border: 1px solid #e5e7eb;
        min-width: 160px;
        padding: 4px 0;
        z-index: 1000;
        animation: slideUp 0.12s ease-out;
        display: none;
    }

    .msg-action-menu.show {
        display: block;
    }

    .msg-group.mine .msg-action-menu {
        left: 0;
        right: auto;
    }

    .msg-group.others .msg-action-menu {
        right: 0;
        left: auto;
    }

    .msg-action-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        font-size: 0.8rem;
        font-weight: 500;
        color: #374151;
        cursor: pointer;
        transition: 0.1s;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }

    .msg-action-item:hover {
        background: #f1f5f9;
    }

    .msg-action-item.danger {
        color: #ef4444;
    }

    .msg-action-item.danger:hover {
        background: #fef2f2;
    }

    .msg-action-item i {
        width: 16px;
        text-align: center;
    }

    .msg-action-divider {
        height: 1px;
        background: #f1f5f9;
        margin: 4px 0;
    }

    /* Channel header dropdown */
    .channel-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 6px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        border: 1px solid #e5e7eb;
        min-width: 200px;
        padding: 4px 0;
        z-index: 1000;
        animation: slideUp 0.12s ease-out;
        display: none;
    }

    .channel-dropdown.show {
        display: block;
    }

    .channel-dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        font-size: 0.82rem;
        font-weight: 500;
        color: #374151;
        cursor: pointer;
        transition: 0.1s;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }

    .channel-dropdown-item:hover {
        background: #f1f5f9;
    }

    .channel-dropdown-item.danger {
        color: #ef4444;
    }

    .channel-dropdown-item.danger:hover {
        background: #fef2f2;
    }

    .channel-dropdown-item i {
        width: 18px;
        text-align: center;
        font-size: 0.85rem;
    }

    .channel-dropdown-divider {
        height: 1px;
        background: #f1f5f9;
        margin: 4px 0;
    }

    /* Edit message inline */
    .msg-edit-wrap {
        display: flex;
        gap: 6px;
        align-items: center;
        margin-top: 4px;
    }

    .msg-edit-input {
        flex: 1;
        padding: 6px 10px;
        border: 1.5px solid #10b981;
        border-radius: 8px;
        font-size: 0.85rem;
        outline: none;
        background: #fff;
        color: #1f2937;
    }

    .msg-edit-save,
    .msg-edit-cancel {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
    }

    .msg-edit-save {
        background: #10b981;
        color: #fff;
    }

    .msg-edit-cancel {
        background: #f1f5f9;
        color: #6b7280;
    }

    /* ── Custom Confirm Popup ── */
    .confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
    }

    .confirm-overlay.open {
        opacity: 1;
        pointer-events: auto;
    }

    .confirm-box {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        width: 380px;
        max-width: 90vw;
        overflow: hidden;
        transform: scale(0.9) translateY(10px);
        transition: transform 0.2s ease-out;
    }

    .confirm-overlay.open .confirm-box {
        transform: scale(1) translateY(0);
    }

    .confirm-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 20px 24px 12px;
    }

    .confirm-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .confirm-icon.danger {
        background: #fef2f2;
        color: #ef4444;
    }

    .confirm-icon.warning {
        background: #fffbeb;
        color: #f59e0b;
    }

    .confirm-icon.info {
        background: #ecfdf5;
        color: #10b981;
    }

    .confirm-title {
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
    }

    .confirm-msg {
        padding: 0 24px 20px;
        font-size: 0.85rem;
        color: #6b7280;
        line-height: 1.5;
    }

    .confirm-actions {
        display: flex;
        gap: 10px;
        padding: 0 24px 20px;
        justify-content: flex-end;
    }

    .confirm-btn {
        padding: 8px 20px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: 0.15s;
    }

    .confirm-btn.cancel {
        background: #f1f5f9;
        color: #64748b;
    }

    .confirm-btn.cancel:hover {
        background: #e2e8f0;
    }

    .confirm-btn.danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .confirm-btn.danger:hover {
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
        transform: translateY(-1px);
    }

    .confirm-btn.primary {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    .confirm-btn.primary:hover {
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
        transform: translateY(-1px);
    }

    /* Empty / loading state */
    .state-center {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        color: #9ca3af;
        text-align: center;
    }

    .state-center .state-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: #cbd5e1;
    }

    .state-center p {
        font-size: 0.9rem;
        color: #9ca3af;
        margin: 0;
    }

    .state-center small {
        font-size: 0.78rem;
        color: #cbd5e1;
    }

    /* Input */
    .chat-input-wrap {
        padding: 1rem 1.5rem 1.25rem;
        background: #ffffff;
        border-top: 1px solid #e5e7eb;
        flex-shrink: 0;
    }

    .chat-input-form {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1.5px solid #e5e7eb;
        border-radius: 14px;
        padding: 8px 8px 8px 14px;
        transition: 0.2s;
    }

    .chat-input-form:focus-within {
        border-color: #10b981;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    }

    .chat-input-form input {
        flex: 1;
        border: none;
        background: transparent;
        font-size: 0.92rem;
        color: #1f2937;
        outline: none;
        padding: 6px 0;
    }

    .chat-input-form input::placeholder {
        color: #aeb5c0;
    }

    .btn-attach,
    .btn-emoji {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: none;
        background: transparent;
        cursor: pointer;
        color: #9ca3af;
        font-size: 1rem;
        transition: 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-attach:hover,
    .btn-emoji:hover {
        background: #e5e7eb;
        color: #374151;
    }

    .btn-send {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: none;
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
        font-size: 0.95rem;
        cursor: pointer;
        transition: 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.35);
    }

    .btn-send:hover {
        transform: scale(1.07);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.45);
    }

    .btn-send:disabled {
        opacity: 0.5;
        transform: none;
        cursor: not-allowed;
    }

    /* ══════════════════ MODAL ══════════════════ */
    .cmodal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
        display: none;
        /* JS toggles to flex */
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .cmodal-overlay.open {
        display: flex;
    }

    .cmodal {
        background: #ffffff;
        border-radius: 24px;
        padding: 32px;
        width: 100%;
        max-width: 600px;
        box-shadow:
            0 25px 50px -12px rgba(0, 0, 0, 0.25),
            0 0 0 1px rgba(0, 0, 0, 0.02);
        animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.97);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .cmodal h3 {
        font-size: 1.75rem;
        font-weight: 800;
        color: #111827;
        margin: 0 0 0.75rem;
        letter-spacing: -0.025em;
        line-height: 1.2;
    }

    .cmodal p {
        font-size: 1rem;
        color: #4b5563;
        margin: 0 0 2rem;
        line-height: 1.6;
    }

    .cmodal-input {
        width: 100%;
        padding: 18px 20px;
        border: 2px solid #e5e7eb;
        border-radius: 16px;
        font-size: 1.05rem;
        color: #111827;
        outline: none;
        transition: all 0.2s ease;
        box-sizing: border-box;
        margin-bottom: 1.5rem;
        background: #fff;
        font-weight: 500;
    }

    .cmodal-input:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        background: #fff;
    }

    .cmodal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .cmodal-cancel {
        padding: 14px 24px;
        border-radius: 14px;
        border: 2px solid #e5e7eb;
        background: #fff;
        color: #374151;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
    }

    .cmodal-cancel:hover {
        background: #f1f5f9;
    }

    .cmodal-submit {
        padding: 14px 32px;
        border-radius: 14px;
        border: none;
        background: #10b981;
        color: white;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
        box-shadow: 0 4px 12px -2px rgba(16, 185, 129, 0.4);
    }

    .cmodal-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .cmodal-submit:disabled {
        opacity: 0.6;
        transform: none;
        cursor: not-allowed;
    }

    /* Message entry animation */
    @keyframes msgIn {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .msg-group {
        animation: msgIn 0.18s ease-out;
    }

    /* ══════════════════ CHANNEL SETTINGS BTN ══════════════════ */
    .chat-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-channel-settings {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: 1.5px solid #e5e7eb;
        background: #fff;
        color: #6b7280;
        cursor: pointer;
        transition: 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .btn-channel-settings:hover {
        background: #ecfdf5;
        border-color: #10b981;
        color: #10b981;
    }

    .subtitle-count {
        cursor: pointer;
        color: #6b7280;
        font-weight: 500;
        transition: color 0.15s;
    }

    .subtitle-count:hover {
        color: #111827;
        text-decoration: underline;
    }

    /* ══════════════════ MANAGE MEMBERS MODAL ══════════════════ */
    .mm-modal {
        background: #fff;
        border-radius: 20px;
        width: 100%;
        max-width: 600px;
        max-height: 85vh;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        animation: slideUp 0.25s ease-out;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .mm-header {
        padding: 24px 32px 16px;
        background: #fff;
    }

    .mm-header-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .mm-header h3 {
        font-size: 1.5rem;
        font-weight: 800;
        color: #111827;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: -0.025em;
    }

    .mm-header h3 .channel-badge {
        font-size: 0.85rem;
        background: #ecfdf5;
        color: #10b981;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 700;
        letter-spacing: 0;
    }

    .mm-close {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: #f3f4f6;
        color: #6b7280;
        cursor: pointer;
        transition: 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .mm-close:hover {
        background: #e5e7eb;
        color: #374151;
    }

    .mm-search {
        display: flex;
        align-items: center;
        gap: 12px;
        background: #fff;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 12px 16px;
        transition: 0.2s;
    }

    .mm-search:focus-within {
        border-color: #d1d5db;
        /* Subtle focus */
        box-shadow: 0 0 0 4px rgba(229, 231, 235, 0.4);
    }

    .mm-search i {
        color: #9ca3af;
        font-size: 1.1rem;
    }

    .mm-search input {
        flex: 1;
        border: none;
        background: transparent;
        outline: none;
        font-size: 1rem;
        color: #1f2937;
    }

    .mm-search input::placeholder {
        color: #9ca3af;
        font-weight: 500;
    }

    /* Tabs */
    .mm-tabs {
        display: flex;
        gap: 24px;
        padding: 0 32px;
        background: #fff;
        border-bottom: 1px solid #f3f4f6;
    }

    .mm-tab {
        padding: 0.75rem 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: #6b7280;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: 0.2s;
        background: none;
        border-top: none;
        border-left: none;
        border-right: none;
        position: relative;
        top: 1px;
    }

    .mm-tab:hover {
        color: #111827;
    }

    .mm-tab.active {
        color: #10b981;
        border-bottom-color: #10b981;
    }

    .mm-tab .tab-count {
        background: #f3f4f6;
        color: #6b7280;
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 12px;
        margin-left: 6px;
        font-weight: 700;
    }

    .mm-tab.active .tab-count {
        background: #ecfdf5;
        color: #10b981;
    }

    /* Member list body */
    .mm-body {
        flex: 1;
        overflow-y: auto;
        padding: 16px 0;
        min-height: 240px;
        max-height: 480px;
    }

    .mm-body::-webkit-scrollbar {
        width: 5px;
    }

    .mm-body::-webkit-scrollbar-thumb {
        background: #e5e7eb;
        border-radius: 3px;
    }

    .mm-user-row {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 12px 32px;
        transition: 0.1s;
    }

    .mm-user-row:hover {
        background: #f8fafc;
    }

    .mm-user-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 0.95rem;
        font-weight: 700;
        flex-shrink: 0;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .mm-user-info {
        flex: 1;
        min-width: 0;
    }

    .mm-user-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mm-user-meta {
        font-size: 0.8rem;
        color: #6b7280;
        font-weight: 500;
    }

    .mm-action-btn {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        border: 1px solid transparent;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 6px;
        background: transparent;
    }

    .mm-add-btn {
        color: #059669;
        border-color: #d1fae5;
    }

    .mm-add-btn:hover {
        background: #f0fdf4;
        border-color: #6ee7b7;
    }

    .mm-remove-btn {
        color: #dc2626;
        border-color: #fee2e2;
        background: #fff;
    }

    .mm-remove-btn:hover {
        background: #fef2f2;
        border-color: #fecaca;
    }

    .mm-action-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .mm-empty {
        text-align: center;
        padding: 2rem 1rem;
        color: #9ca3af;
        font-size: 0.85rem;
    }

    .mm-empty i {
        display: block;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: #d1d5db;
    }

    /* Create channel member selection */
    .create-member-section {
        margin-bottom: 1rem;
    }

    .create-member-section label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1rem;
        font-weight: 800;
        color: #111827;
        margin-bottom: 1rem;
    }

    .create-member-list {
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 12px;
        background: #fff;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .create-member-list::-webkit-scrollbar {
        width: 4px;
    }

    .create-member-list::-webkit-scrollbar-thumb {
        background: #e5e7eb;
        border-radius: 2px;
    }

    .create-member-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 16px;
        cursor: pointer;
        transition: all 0.2s ease;
        border-radius: 10px;
        margin-bottom: 4px;
        border: 1px solid transparent;
    }

    .create-member-item:hover {
        background: #f9fafb;
    }

    .create-member-item.selected {
        background: #ecfdf5;
        border-color: #d1fae5;
    }

    .create-member-item input[type="checkbox"] {
        accent-color: #10b981;
        width: 20px;
        height: 20px;
        border-radius: 6px;
        border: 2px solid #d1d5db;
        cursor: pointer;
    }

    .create-member-item .cmi-name {
        font-size: 0.875rem;
        color: #111827;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .cmi-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        color: white;
        font-weight: 700;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .cmi-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
    }

    .cmi-meta {
        font-size: 0.75rem;
        color: #6b7280;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 500;
    }

    .cmi-action {
        margin-left: auto;
    }

    .cmi-btn {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .cmi-btn.add {
        color: #059669;
        border-color: #d1fae5;
        background: #ecfdf5;
    }

    .cmi-btn.add:hover {
        background: #d1fae5;
    }

    .cmi-btn.remove {
        color: #dc2626;
        border-color: #fee2e2;
        background: #fff;
    }

    .cmi-btn.remove:hover {
        background: #fef2f2;
        border-color: #fecaca;
    }



    /* Responsive */
    @media (max-width: 700px) {
        .chat-sidebar {
            width: 64px;
            min-width: 64px;
        }

        .ws-info,
        .sidebar-section-header span,
        .item-label,
        .member-info {
            display: none;
        }

        .ws-header {
            justify-content: center;
        }

        .sidebar-item,
        .member-item {
            justify-content: center;
            padding: 8px 0;
        }

        .sidebar-add-btn {
            display: none;
        }
    }

    /* Emoji & Attachments */
    .emoji-picker {
        display: none;
        position: absolute;
        bottom: 70px;
        right: 20px;
        width: 320px;
        max-height: 250px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        overflow-y: auto;
        padding: 12px;
        grid-template-columns: repeat(8, 1fr);
        gap: 6px;
        z-index: 100;
    }

    .emoji-picker.show {
        display: grid;
    }

    .emoji-item {
        font-size: 1.25rem;
        cursor: pointer;
        text-align: center;
        padding: 6px;
        border-radius: 6px;
        transition: background 0.15s;
    }

    .emoji-item:hover {
        background: #f3f4f6;
    }

    .file-preview-area {
        position: absolute;
        bottom: 100%;
        /* Above input */
        left: 0;
        right: 0;
        padding: 8px 16px;
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        color: #374151;
        z-index: 20;
    }

    .remove-file-btn {
        background: none;
        border: none;
        color: #ef4444;
        cursor: pointer;
        padding: 4px;
        margin-left: auto;
    }

    .chat-attachment-img {
        max-width: 240px;
        max-height: 240px;
        border-radius: 8px;
        margin-top: 8px;
        border: 1px solid #e5e7eb;
        display: block;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .chat-attachment-img:hover {
        transform: scale(1.02);
    }

    .chat-attachment-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        background: #f3f4f6;
        border-radius: 8px;
        color: #2563eb;
        text-decoration: none;
        font-weight: 500;
        margin-top: 6px;
        border: 1px solid #e5e7eb;
        transition: background 0.2s;
    }

    .chat-attachment-link:hover {
        background: #e5e7eb;
    }

    /* Video/Audio */
    .chat-attachment-video {
        max-width: 100%;
        max-height: 240px;
        border-radius: 8px;
        margin-top: 8px;
        background: #000;
        display: block;
    }

    .chat-video-wrap {
        width: 320px;
        max-width: 100%;
    }

    .chat-audio-wrap {
        width: 320px;
        margin-top: 8px;
    }

    /* ── Pulse Premium Notification Toast ── */
    #pulseNotificationStack {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        pointer-events: none;
    }

    .pulse-toast {
        width: 380px;
        max-width: calc(100vw - 48px);
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(24px) saturate(200%);
        -webkit-backdrop-filter: blur(24px) saturate(200%);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 24px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1),
            0 2px 8px rgba(0, 0, 0, 0.05),
            inset 0 0 0 1px rgba(255, 255, 255, 0.5);
        padding: 18px;
        display: flex;
        align-items: center;
        gap: 16px;
        pointer-events: auto;
        cursor: pointer;
        animation: toast-in 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
    }

    .pulse-toast:hover {
        transform: translateY(-6px) scale(1.02);
        background: rgba(255, 255, 255, 0.85);
        box-shadow: 0 40px 100px rgba(0, 0, 0, 0.15);
        border-color: rgba(16, 185, 129, 0.4);
    }

    .pulse-toast::before {
        content: '';
        position: absolute;
        width: 4px;
        height: 40px;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        background: linear-gradient(to bottom, #10b981, #3b82f6);
        border-radius: 0 4px 4px 0;
    }

    .pulse-toast-avatar {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 1.2rem;
        flex-shrink: 0;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        background: linear-gradient(135deg, #10b981, #059669);
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .pulse-toast-content {
        flex: 1;
        min-width: 0;
    }

    .pulse-toast-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1a1c1e;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2px;
    }

    .pulse-toast-time {
        font-size: 0.72rem;
        color: #8e9196;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .pulse-toast-body {
        font-size: 0.9rem;
        color: #44474e;
        line-height: 1.5;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 400;
    }

    .pulse-toast-close {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.04);
        border: none;
        color: #666;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        cursor: pointer;
        opacity: 0;
        transition: all 0.2s;
    }

    .pulse-toast:hover .pulse-toast-close {
        opacity: 1;
    }

    .pulse-toast-close:hover {
        background: rgba(0, 0, 0, 0.1);
        color: #1a1c1e;
        transform: rotate(90deg);
    }

    @keyframes toast-in {
        from {
            opacity: 0;
            transform: translateX(40px) scale(0.95) skewY(1deg);
            filter: blur(10px);
        }

        to {
            opacity: 1;
            transform: translateX(0) scale(1) skewY(0deg);
            filter: blur(0);
        }
    }

    @keyframes toast-out {
        to {
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
            filter: blur(15px);
        }
    }

    .pulse-toast.hiding {
        animation: toast-out 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .chat-attachment-audio {
        width: 100%;
    }
</style>

<!-- ============ HTML LAYOUT ============ -->
<div class="chat-root">

    <!-- ── SIDEBAR ── -->
    <aside class="chat-sidebar">

        <!-- Workspace header -->
        <div class="ws-header">
            <div class="ws-logo"><?php echo strtoupper(substr($team['name'] ?? 'T', 0, 1)); ?></div>
            <div class="ws-info">
                <div class="ws-name"><?php echo htmlspecialchars($team['name'] ?? 'Turtle Workspace'); ?></div>
                <div class="ws-sub"><?php echo $team ? 'Team Workspace' : 'Global Workspace'; ?></div>
            </div>
            <?php $is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin'; ?>
            <a href="<?php echo $is_admin ? '/admin_dashboard.php' : '/index.php'; ?>" class="ws-back"
                title="Back to Dashboard">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
        </div>

        <!-- Search -->
        <div class="sidebar-search">
            <div class="sidebar-search-inner">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="memberSearch" placeholder="Find or start a conversation…"
                    oninput="handleSidebarSearch(this.value)">
            </div>
        </div>

        <!-- Channels -->
        <div class="sidebar-section" id="channelsSection">
            <div class="sidebar-section-header">
                <span>Channels</span>
                <?php if ($user['role'] === 'admin'): ?>
                    <button class="sidebar-add-btn" onclick="openModal()" title="Create channel">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                <?php endif; ?>
            </div>
            <div id="channelList">
                <!-- filled by JS -->
            </div>
        </div>

        <div class="sidebar-divider" id="sidebarDivider"></div>

        <!-- Direct Messages / Members -->
        <div class="sidebar-section-header" style="padding-top:0.5rem;" id="dmSectionHeader">
            <span>Direct Messages</span>
        </div>
        <div class="members-section">
            <div id="memberList">
                <!-- filled by JS -->
            </div>
        </div>

        <!-- Search results (hidden by default) -->
        <div class="members-section" id="searchResultsSection" style="display:none;">
            <div class="sidebar-section-header" style="padding-top:0.5rem;">
                <span>All Members</span>
            </div>
            <div id="searchResultsList">
                <!-- filled by JS -->
            </div>
        </div>

    </aside>

    <!-- ── MAIN CHAT ── -->
    <main class="chat-main">

        <!-- Header -->
        <div class="chat-header">
            <div class="chat-header-left">
                <div class="channel-icon-wrap" id="headerIcon"><i class="fa-solid fa-hashtag"></i></div>
                <div>
                    <div class="channel-title" id="headerChannelName">General</div>
                    <div class="channel-subtitle" id="headerSubtitle">Team channel</div>
                </div>
            </div>
            <div class="chat-header-actions" style="position:relative;">
                <div class="call-actions" id="callActions" style="display: none;">
                    <button class="btn-call voice" id="btnVoiceCall" onclick="startCall('voice')" title="Voice Call">
                        <i class="fa-solid fa-phone"></i>
                        <span class="call-tooltip">Voice Call</span>
                    </button>
                    <button class="btn-call video" id="btnVideoCall" onclick="startCall('video')" title="Video Call">
                        <i class="fa-solid fa-video"></i>
                        <span class="call-tooltip">Video Call</span>
                    </button>
                    <button class="btn-call screen" id="btnScreenShare" onclick="startCall('screen')"
                        title="Share Screen">
                        <i class="fa-solid fa-desktop"></i>
                        <span class="call-tooltip">Share Screen</span>
                    </button>
                </div>
                <div class="live-badge">
                    <div class="live-badge-dot"></div>
                    Live
                </div>
                <?php if ($user['role'] === 'admin'): ?>
                    <button class="btn-channel-settings" id="btnChannelSettings" onclick="openMembersModal()"
                        title="Manage channel members">
                        <i class="fa-solid fa-user-group"></i>
                    </button>
                <?php endif; ?>
                <button class="btn-channel-settings" id="btnChannelMenu" onclick="toggleChannelMenu(event)"
                    title="Channel options">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                </button>
                <div class="channel-dropdown" id="channelDropdown">
                    <button class="channel-dropdown-item" id="clearAllBtn" onclick="clearAllMessages()">
                        <i class="fa-solid fa-broom"></i> Clear All Messages
                    </button>
                    <div class="channel-dropdown-divider"></div>
                    <?php if ($user['role'] === 'admin'): ?>
                        <button class="channel-dropdown-item danger" id="deleteChannelBtn" onclick="deleteChannel()">
                            <i class="fa-solid fa-trash"></i> Delete Channel
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="state-center" id="loadingState">
                <div class="state-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
                <p>Loading conversation…</p>
            </div>
        </div>

        <!-- Input -->
        <!-- Input -->
        <div class="chat-input-wrap" style="position:relative;">
            <div id="filePreviewArea" class="file-preview-area" style="display:none;">
                <i class="fa-solid fa-file" style="color:#6b7280;"></i>
                <span id="fileName" style="font-weight:500;"></span>
                <button type="button" onclick="clearFile()" class="remove-file-btn" title="Remove file"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="emoji-picker" id="emojiPicker">
                <!-- Emojis injected via JS -->
            </div>

            <form class="chat-input-form" id="chatForm" onsubmit="sendMessage(event)">
                <input type="file" id="fileInput" name="attachment[]" multiple style="display:none"
                    onchange="handleFileSelect(this)">

                <button type="button" class="btn-attach" onclick="document.getElementById('fileInput').click()"
                    title="Attach files">
                    <i class="fa-solid fa-paperclip"></i>
                </button>

                <input type="text" id="messageInput" placeholder="Message #General…" autocomplete="off"
                    maxlength="2000">

                <button type="button" class="btn-emoji" onclick="toggleEmojiPicker(event)" title="Choose emoji">
                    <i class="fa-regular fa-face-smile"></i>
                </button>

                <button type="submit" class="btn-send" id="sendBtn" title="Send">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </div>

    </main>
</div>


<!-- ============ PULSE NOTIFICATIONS ============ -->
<div id="pulseNotificationStack"></div>

<!-- ============ CUSTOM CONFIRM POPUP ============ -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-header">
            <div class="confirm-icon danger" id="confirmIcon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="confirm-title" id="confirmTitle">Are you sure?</div>
        </div>
        <div class="confirm-msg" id="confirmMsg">This action cannot be undone.</div>
        <div class="confirm-actions">
            <button class="confirm-btn cancel" id="confirmCancelBtn">Cancel</button>
            <button class="confirm-btn danger" id="confirmOkBtn">Confirm</button>
        </div>
    </div>
</div>

<!-- ============ CREATE CHANNEL MODAL ============ -->
<div class="cmodal-overlay" id="createChanModal" onclick="handleOverlayClick(event)">
    <div class="cmodal" id="createChanCard">
        <h3>Create a Channel</h3>
        <p>Channels organise conversations around topics. Name it clearly.</p>
        <input type="text" id="newChanName" class="cmodal-input" placeholder="e.g. marketing-updates" maxlength="80"
            onkeydown="if(event.key==='Enter') submitChannel()">

        <div class="create-member-section">
            <label><i class="fa-solid fa-user-plus" style="margin-right:4px;"></i> Add Members</label>
            <div class="cmodal-search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="createMemberSearch" placeholder="Search members..."
                    oninput="renderCreateMemberList(this.value)">
            </div>
            <style>
                .cmodal-search-wrapper {
                    position: relative;
                    margin-bottom: 12px;
                }

                .cmodal-search-wrapper i {
                    position: absolute;
                    left: 14px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #9ca3af;
                    font-size: 0.9rem;
                }

                .cmodal-search-wrapper input {
                    width: 100%;
                    padding: 12px 12px 12px 40px;
                    border: 1px solid #e5e7eb;
                    border-radius: 12px;
                    font-size: 0.95rem;
                    outline: none;
                    transition: 0.2s;
                    box-sizing: border-box;
                    background: #f9fafb;
                }

                .cmodal-search-wrapper input:focus {
                    border-color: #10b981;
                    background: #fff;
                    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
                }
            </style>
            <div class="create-member-list" id="createMemberList">
                <!-- filled by JS -->
            </div>
        </div>

        <div class="cmodal-actions">
            <button class="cmodal-cancel" onclick="closeModal()">Cancel</button>
            <button class="cmodal-submit" id="createChanBtn" onclick="submitChannel()">Create Channel</button>
        </div>
    </div>
</div>

<!-- ============ MANAGE MEMBERS MODAL ============ -->
<div class="cmodal-overlay" id="manageMembersModal" onclick="if(event.target===this) closeMembersModal()">
    <div class="mm-modal">
        <div class="mm-header">
            <div class="mm-header-top">
                <h3>
                    <i class="fa-solid fa-user-group" style="color:#10b981;"></i>
                    Channel Members
                    <span class="channel-badge" id="mmChannelBadge">#General</span>
                </h3>
                <button class="mm-close" onclick="closeMembersModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="mm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="mmSearchInput" placeholder="Search team members…"
                    oninput="filterManagedMembers(this.value)">
            </div>
        </div>

        <div class="mm-tabs">
            <button class="mm-tab active" data-tab="current" onclick="switchMmTab('current')">
                Current <span class="tab-count" id="currentCount">0</span>
            </button>
            <button class="mm-tab" data-tab="add" onclick="switchMmTab('add')">
                Add New <span class="tab-count" id="addCount">0</span>
            </button>
        </div>

        <div class="mm-body" id="mmBody">
            <!-- filled by JS -->
        </div>
    </div>
</div>

<audio id="msgSound" src="https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3" preload="auto"></audio>

<script>
    /* ── Constants ── */
    const TEAM_ID = <?php echo $teamId ? (int) $teamId : 'null'; ?>;
    const CURRENT_USER = <?php echo (int) $user['user_id']; ?>;
    const CURRENT_USER_ROLE = '<?php echo strtolower(trim($user['role'])); ?>';
    const AVATAR_COLORS = ['#8b5cf6', '#3b82f6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#10b981'];

    /* ── State ── */
    let currentChannel = 'General';
    let currentChannelId = null;
    let isDmView = false;
    let lastMessageId = 0;
    let loadEpoch = 0; // Incremented on channel switch to discard stale responses
    let allMembers = [];
    let recentDms = [];
    let channels = [];
    let channelMembers = [];
    let pollTimer = null;
    let mmCurrentTab = 'current';
    let isSearching = false;
    let openMenuMsgId = null;


    /* ── Notification Helpers ── */
    function playNotificationSound() {
        const sound = document.getElementById('msgSound');
        if (sound) {
            sound.currentTime = 0;
            sound.play().catch(e => console.warn('Sound play failed:', e));
        }
    }

    function showSystemNotification(title, message) {
        // 1. Show custom in-app Pulse toast (always show if in app)
        showPulseToast(title, message);

        // 2. Show native browser notification (only if out of focus/hidden)
        if (document.visibilityState !== 'visible' || !document.hasFocus()) {
            if ("Notification" in window && Notification.permission === "granted") {
                const n = new Notification(title, {
                    body: message,
                    icon: '/assets/images/turtle_logo_192.png'
                });
                n.onclick = () => {
                    window.focus();
                    n.close();
                };
            }
        }
    }

    function showPulseToast(title, body) {
        const stack = document.getElementById('pulseNotificationStack');
        if (!stack) return;

        const toast = document.createElement('div');
        toast.className = 'pulse-toast';

        const color = avatarColor(title);
        const ini = initials(title);
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        toast.innerHTML = `
            <div class="pulse-toast-avatar" style="background:${color}">${ini}</div>
            <div class="pulse-toast-content">
                <div class="pulse-toast-title">
                    <span>${escHtml(title)}</span>
                    <span class="pulse-toast-time">${time}</span>
                </div>
                <div class="pulse-toast-body">${escHtml(body)}</div>
            </div>
            <button class="pulse-toast-close" onclick="this.parentElement.remove()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        toast.onclick = (e) => {
            if (e.target.closest('.pulse-toast-close')) return;
            window.focus();
            // Optional: try to find the user in recentDms to switch?
            // For now, just bring window to front
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 400);
        };

        // Auto-remove after 6 seconds
        const timer = setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 400);
        }, 6000);

        stack.appendChild(toast);

        // Max 3 toasts at once
        while (stack.children.length > 3) {
            stack.firstChild.remove();
        }
    }

    let lastUnreadState = {};

    function checkUnreadAlerts(dms, chans) {
        let hasNew = false;
        const newState = {};

        if (Array.isArray(dms)) {
            dms.forEach(d => {
                const key = `dm-${d.user_id}`;
                newState[key] = d.unread_count || 0;
                if (newState[key] > (lastUnreadState[key] || 0)) {
                    showSystemNotification(d.full_name || d.username, 'Sent you a message');
                    hasNew = true;
                }
            });
        }

        if (Array.isArray(chans)) {
            chans.forEach(c => {
                const key = `chan-${c.name}`;
                newState[key] = c.unread_count || 0;
                if (newState[key] > (lastUnreadState[key] || 0) && c.name !== currentChannel) {
                    showSystemNotification(`#${c.name}`, 'New activity in channel');
                    hasNew = true;
                }
            });
        }

        if (hasNew) playNotificationSound();
        lastUnreadState = newState;
    }

    /* ── Custom confirm popup helper ── */
    function showConfirm(title, message, type = 'danger') {
        return new Promise((resolve) => {
            const overlay = document.getElementById('confirmOverlay');
            const titleEl = document.getElementById('confirmTitle');
            const msgEl = document.getElementById('confirmMsg');
            const iconEl = document.getElementById('confirmIcon');
            const okBtn = document.getElementById('confirmOkBtn');
            const cancelBtn = document.getElementById('confirmCancelBtn');

            titleEl.textContent = title;
            msgEl.innerHTML = message;

            // Icon setup
            iconEl.className = 'confirm-icon ' + (type === 'danger' ? 'danger' : 'info');
            iconEl.innerHTML = type === 'danger'
                ? '<i class="fa-solid fa-triangle-exclamation"></i>'
                : '<i class="fa-solid fa-circle-info"></i>';

            // Button style
            okBtn.className = 'confirm-btn ' + (type === 'danger' ? 'danger' : 'primary');
            okBtn.textContent = type === 'danger' ? 'Delete' : 'Confirm';

            overlay.classList.add('open');

            function cleanup() {
                overlay.classList.remove('open');
                okBtn.onclick = null;
                cancelBtn.onclick = null;
                overlay.onclick = null;
            }

            okBtn.onclick = () => { cleanup(); resolve(true); };
            cancelBtn.onclick = () => { cleanup(); resolve(false); };
            overlay.onclick = (e) => { if (e.target === overlay) { cleanup(); resolve(false); } };
        });
    }

    /* ── Notify helpers (delegates to glass-toast.php's Toast object) ── */
    const Notify = {
        ok: (m) => { try { Toast.success('Success', m); } catch (e) { console.log(m); } },
        err: (m) => { try { Toast.error('Error', m); } catch (e) { console.error(m); } },
        info: (m) => { try { Toast.info('Notice', m); } catch (e) { console.log(m); } },
    };

    /* ── Avatar helper ── */
    function avatarColor(name) {
        let h = 0;
        for (let i = 0; i < name.length; i++) h = name.charCodeAt(i) + ((h << 5) - h);
        return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length];
    }
    function initials(name) {
        return (name || '?').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
    }

    /* ══════════════ CHANNELS ══════════════ */
    async function loadChannels() {
        try {
            const res = await fetch(`/api/chat.php?team_id=${TEAM_ID}&action=channels`);
            const json = await res.json();
            if (json.success && Array.isArray(json.data)) {
                channels = json.data;
                // Always ensure General is at the top
                if (!channels.find(c => c.name === 'General')) {
                    channels.unshift({ id: null, name: 'General' });
                }
            } else {
                channels = [{ id: null, name: 'General' }];
            }
            renderChannels();
            checkUnreadAlerts(recentDms, channels);
        } catch (e) {
            console.error('loadChannels error:', e);
            if (channels.length === 0) {
                channels = [{ id: null, name: 'General' }];
                renderChannels();
            }
        }
    }

    function renderChannels() {
        const el = document.getElementById('channelList');
        if (!el) return;
        el.innerHTML = channels.map(c => {
            const active = (!isDmView && c.name === currentChannel) ? 'active' : '';
            const unread = (c.unread_count > 0) ? `<span class="unread-badge">${c.unread_count}</span>` : '';
            return `<div class="sidebar-item ${active}" onclick="switchChannel('${escAttr(c.name)}', ${c.id || 'null'})">
                        <span class="item-icon"><i class="fa-solid fa-hashtag"></i></span>
                        <span class="item-label">${escHtml(c.name)}</span>
                        ${unread}
                    </div>`;
        }).join('');
    }

    function switchChannel(name, channelId) {
        if (currentChannel === name && !isDmView) return;
        currentChannel = name;
        currentChannelId = channelId;
        isDmView = false;
        lastMessageId = 0;
        loadEpoch++;

        // Stop polling during switch
        if (pollTimer) clearInterval(pollTimer);

        document.getElementById('headerChannelName').textContent = name;
        document.getElementById('headerSubtitle').textContent = 'Team channel';
        document.getElementById('headerIcon').innerHTML = '<i class="fa-solid fa-hashtag"></i>';
        document.getElementById('headerIcon').style.background = '#ecfdf5';
        document.getElementById('headerIcon').style.color = '#10b981';
        document.getElementById('messageInput').placeholder = `Message #${name}…`;

        const btnSettings = document.getElementById('btnChannelSettings');
        if (name.toLowerCase() === 'general') {
            if (btnSettings) btnSettings.style.display = 'none';
            const count = (allMembers || []).length;
            document.getElementById('headerSubtitle').innerHTML = `Team channel &middot; <span class="subtitle-count" onclick="openMembersModal()" title="View members">${count} Members</span>`;
        } else {
            if (btnSettings) btnSettings.style.display = '';
            document.getElementById('headerSubtitle').textContent = 'Team channel';
        }

        // Show delete channel only for admins and non-General
        const delBtn = document.getElementById('deleteChannelBtn');
        if (delBtn) delBtn.style.display = (name.toLowerCase() === 'general' || CURRENT_USER_ROLE !== 'admin') ? 'none' : '';

        renderChannels();

        const box = document.getElementById('chatMessages');
        box.innerHTML = `<div class="state-center">
            <div class="state-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
            <p>Loading #${escHtml(name)}…</p>
        </div>`;

        loadMessages();
        startPolling();
        markAsRead();

        // Save state
        localStorage.setItem('chat_active_view', JSON.stringify({
            type: 'channel',
            name: name,
            id: channelId
        }));
    }

    function switchToDM(userId, name) {
        if (!userId) return;
        const dmChannel = `dm-${Math.min(CURRENT_USER, userId)}-${Math.max(CURRENT_USER, userId)}`;

        // Update State
        isDmView = true;
        currentChannel = dmChannel;
        currentChannelId = null;
        lastMessageId = 0;
        loadEpoch++;

        if (pollTimer) clearInterval(pollTimer);

        // UI Updates
        const searchInput = document.getElementById('memberSearch');
        if (searchInput) searchInput.value = '';
        exitSearchMode();

        const hdrName = document.getElementById('headerChannelName');
        const hdrSub = document.getElementById('headerSubtitle');
        const hdrIcon = document.getElementById('headerIcon');
        const msgInp = document.getElementById('messageInput');
        const btnSet = document.getElementById('btnChannelSettings');
        const box = document.getElementById('chatMessages');

        if (hdrName) hdrName.textContent = name;
        if (hdrSub) hdrSub.textContent = 'Direct Message';
        if (msgInp) msgInp.placeholder = `Message ${name}…`;
        if (btnSet) btnSet.style.display = 'none';

        if (hdrIcon) {
            const color = avatarColor(name);
            hdrIcon.innerHTML = initials(name);
            hdrIcon.style.background = color;
            hdrIcon.style.color = '#fff';
        }

        // Deselect channels & Highlight DM
        renderChannels();
        renderRecentDms(userId);

        if (box) {
            box.scrollTop = 0;
            box.innerHTML = `<div class="state-center">
                <div class="state-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
                <p>Loading conversation with ${escHtml(name)}…</p>
            </div>`;
        }

        loadMessages();
        startPolling();
        markAsRead();

        // Save state
        localStorage.setItem('chat_active_view', JSON.stringify({
            type: 'dm',
            userId: userId,
            name: name
        }));
    }

    /* ══════════════ MEMBERS & DMs ══════════════ */
    async function loadMembers() {
        try {
            const res = await fetch(`/api/users.php`);
            const json = await res.json();
            if (json.success && Array.isArray(json.users)) {
                allMembers = json.users;
            }
        } catch (e) {
            console.error('loadMembers error:', e);
        }
    }

    async function loadRecentDms() {
        try {
            const res = await fetch(`/api/chat.php?team_id=${TEAM_ID}&action=recent_dms`);
            const json = await res.json();
            if (json.success && Array.isArray(json.dms)) {
                recentDms = json.dms;
            }
        } catch (e) {
            console.error('loadRecentDms error:', e);
        }
        renderRecentDms();
        checkUnreadAlerts(recentDms, channels);
    }

    function renderRecentDms(activeDmUserId) {
        const el = document.getElementById('memberList');
        if (!el) return;

        if (recentDms.length === 0) {
            el.innerHTML = `<div class="dm-empty-hint">
                <i class="fa-solid fa-comment-dots"></i>
                No conversations yet.<br>Use the search bar to start one!
            </div>`;
            return;
        }

        el.innerHTML = recentDms.map(dm => {
            const p = dm.partner;
            const name = p.full_name || p.username || 'Unknown';
            const ini = initials(name);
            const color = avatarColor(name);
            const online = p.is_active == 1;
            const isActive = activeDmUserId && p.id == activeDmUserId;

            // Truncate last message
            let preview = dm.last_message || '';
            // Replace signal messages with friendly labels
            if (preview.startsWith('__SIGNAL__')) {
                try {
                    const sig = JSON.parse(preview.replace('__SIGNAL__', ''));
                    const labels = {
                        'call-offer': '📞 Call',
                        'call-end': '📞 Call ended',
                        'call-answer': '📞 Call',
                        'call-decline': '📞 Call declined',
                        'call-recording': '🔴 Recording',
                        'call-recording-stop': '🔴 Recording ended',
                    };
                    preview = labels[sig.signalType] || '📞 Call';
                } catch (e) {
                    preview = '📞 Call';
                }
            }
            if (preview.length > 30) preview = preview.substring(0, 30) + '…';

            let statusIcon = '';
            if (dm.last_message_mine) {
                const read = dm.last_message_read ? ' read' : '';
                statusIcon = `<span class="msg-status${read}"><i class="fa-solid fa-check-double"></i></span>`;
                preview = 'You: ' + preview;
            }

            const unread = (dm.unread_count > 0) ? `<span class="unread-badge" style="margin-left:auto;">${dm.unread_count}</span>` : '';

            return `<div class="member-item${isActive ? ' active' : ''}" onclick="switchToDM(${p.id}, '${escAttr(name)}')" style="cursor:pointer;${isActive ? 'background:rgba(16,185,129,0.15);color:#34d399;' : ''}">
                <div class="member-avatar" style="background:${color}">
                    ${ini}
                    <div class="member-avatar-dot ${online ? 'online' : ''}"></div>
                </div>
                <div class="member-info" style="flex:1; min-width:0;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="member-name-text">${escHtml(name)}</div>
                        ${unread}
                    </div>
                    <div class="member-last-msg">${escHtml(preview)} ${statusIcon}</div>
                </div>
                <button class="member-delete-btn" onclick="event.stopPropagation(); deleteRecentChat(${p.id}, '${escAttr(name)}')" title="Delete Chat">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>`;
        }).join('');
    }

    async function deleteRecentChat(userId, name) {
        const ok = await showConfirm('Delete Conversation', `Permanently delete all messages with <strong>${escHtml(name)}</strong>? This action cannot be undone.`, 'danger');
        if (!ok) return;

        try {
            const dmChannel = `dm-${Math.min(CURRENT_USER, userId)}-${Math.max(CURRENT_USER, userId)}`;
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'delete_recent_dm',
                    team_id: TEAM_ID,
                    channel: dmChannel
                }),
                headers: { 'Content-Type': 'application/json' }
            });
            const json = await res.json();
            if (json.success) {
                Notify.ok('Conversation deleted');
                // If we are currently in this DM, switch back to General
                if (isDmView && currentChannel === dmChannel) {
                    switchChannel('General', null);
                }
                loadRecentDms();
            } else {
                Notify.err(json.message || 'Failed to delete conversation');
            }
        } catch (e) {
            console.error('deleteRecentChat error:', e);
            Notify.err('Server error');
        }
    }

    function renderSearchResults(list) {
        const el = document.getElementById('searchResultsList');
        if (!el) return;
        if (!list || list.length === 0) {
            el.innerHTML = `<div style="padding:0.5rem 1rem;font-size:0.8rem;color:#4a5568;">No members found</div>`;
            return;
        }
        el.innerHTML = list.filter(u => u.id != CURRENT_USER).map(u => {
            const name = u.full_name || u.username || 'Unknown';
            const ini = initials(name);
            const color = avatarColor(name);
            const online = u.is_active == 1;
            const uname = u.username || '';
            const role = u.role || 'member';

            return `<div class="member-item" onclick="switchToDM(${u.id}, '${escAttr(name)}')" style="cursor:pointer;">
                <div class="member-avatar" style="background:${color}">
                    ${ini}
                    <div class="member-avatar-dot ${online ? 'online' : ''}"></div>
                </div>
                <div class="member-info">
                    <div class="member-name-text">${escHtml(name)}</div>
                    <div class="member-role-text">${escHtml(role)} · @${escHtml(uname)}</div>
                </div>
            </div>`;
        }).join('');
    }

    /* ── Sidebar search ── */
    function handleSidebarSearch(query) {
        query = (query || '').trim();

        if (!query) {
            exitSearchMode();
            return;
        }

        enterSearchMode();
        const lower = query.toLowerCase();
        const filtered = allMembers.filter(u => {
            const name = (u.full_name || u.username || '').toLowerCase();
            return name.includes(lower);
        });
        renderSearchResults(filtered);
    }

    function enterSearchMode() {
        if (isSearching) return;
        isSearching = true;
        document.getElementById('channelsSection').style.display = 'none';
        document.getElementById('sidebarDivider').style.display = 'none';
        document.getElementById('dmSectionHeader').style.display = 'none';
        document.getElementById('memberList').parentElement.style.display = 'none';
        document.getElementById('searchResultsSection').style.display = '';
    }

    function exitSearchMode() {
        if (!isSearching) return;
        isSearching = false;
        document.getElementById('channelsSection').style.display = '';
        document.getElementById('sidebarDivider').style.display = '';
        document.getElementById('dmSectionHeader').style.display = '';
        document.getElementById('memberList').parentElement.style.display = '';
        document.getElementById('searchResultsSection').style.display = 'none';
    }

    /* ══════════════ MESSAGES ══════════════ */
    async function loadMessages() {
        const myEpoch = loadEpoch; // capture epoch at call time
        try {
            const res = await fetch(`/api/chat.php?team_id=${TEAM_ID}&last_id=${lastMessageId}&channel=${encodeURIComponent(currentChannel)}`);
            const json = await res.json();

            // Discard if channel was switched during fetch
            if (myEpoch !== loadEpoch) return;

            const box = document.getElementById('chatMessages');
            const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;

            if (json.force_reset) {
                lastMessageId = 0;
                if (box) box.innerHTML = '';
                loadMessages();
                return;
            }

            if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                // Remove loading/empty state on first load
                const stateEl = box.querySelector('.state-center');
                if (stateEl) stateEl.remove();

                json.data.forEach(msg => {
                    // Skip if already rendered
                    if (document.getElementById(`msg-${msg.id}`)) return;

                    const mine = msg.user_id == CURRENT_USER;
                    const name = msg.full_name || msg.username || 'Unknown';
                    const ini = initials(name);
                    const color = avatarColor(name);
                    const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    let statusHtml = '';
                    if (mine) {
                        const read = msg.is_read == 1 ? ' read' : '';
                        statusHtml = `<span class="msg-status${read}"><i class="fa-solid fa-check-double"></i></span>`;
                    }

                    // Parse attachments
                    let bodyHtml = escHtml(msg.message);
                    bodyHtml = bodyHtml.replace(/\[attachment:(.*?)\]/g, (match, url) => {
                        const ext = url.split('.').pop().toLowerCase();
                        const isImg = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
                        const isVid = ['mp4', 'webm', 'ogg', 'mov', 'm4v', 'avi', 'mkv', 'flv'].includes(ext);
                        const isAud = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'].includes(ext);

                        if (isImg) {
                            return `<a href="${url}" target="_blank"><img src="${url}" class="chat-attachment-img" alt="Image"></a>`;
                        } else if (isVid) {
                            return `<div class="chat-video-wrap"><video src="${url}" controls class="chat-attachment-video"></video></div>`;
                        } else if (isAud) {
                            return `<div class="chat-audio-wrap"><audio src="${url}" controls class="chat-attachment-audio"></audio></div>`;
                        } else {
                            return `<a href="${url}" target="_blank" class="chat-attachment-link"><i class="fa-solid fa-file"></i> ${url.split('/').pop()}</a>`;
                        }
                    });
                    // Convert newlines to breaks
                    bodyHtml = bodyHtml.replace(/\n/g, '<br>');

                    const g = document.createElement('div');
                    g.className = `msg-group ${mine ? 'mine' : 'others'}`;
                    g.id = `msg-${msg.id}`;
                    g.dataset.msgId = msg.id;
                    g.dataset.mine = mine ? '1' : '0';
                    g.dataset.text = msg.message; // Keep raw
                    g.innerHTML = `
                        <button class="msg-actions-btn" onclick="toggleMsgMenu(event, ${msg.id})" title="Actions">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div class="msg-action-menu" id="msg-menu-${msg.id}">
                            ${mine ? `<button class="msg-action-item" onclick="editMessage(${msg.id})"><i class="fa-solid fa-pen"></i> Edit</button>` : ''}
                            ${(mine || CURRENT_USER_ROLE === 'admin') ? `<button class="msg-action-item danger" onclick="deleteMessage(${msg.id})"><i class="fa-solid fa-trash"></i> Delete</button>` : ''}
                        </div>
                        <div class="msg-avatar" style="background:${color}">${ini}</div>
                        <div class="msg-body">
                            ${!mine ? `<div class="msg-sender">${escHtml(name)}</div>` : ''}
                            <div class="msg-bubble" id="msg-bubble-${msg.id}">${bodyHtml}</div>
                            <div class="msg-time">${time} ${statusHtml}</div>
                        </div>`;
                    box.appendChild(g);
                    lastMessageId = Math.max(lastMessageId, parseInt(msg.id));

                    // Notification & Read status logic
                    if (!mine && lastMessageId === msg.id) {
                        playNotificationSound();
                        if (document.visibilityState !== 'visible' || !document.hasFocus()) {
                            showSystemNotification(name, msg.message);
                        } else {
                            // If tab is visible and focused, and we just got a new message on this channel
                            markAsRead();
                        }
                    }
                });

                if (atBottom || lastMessageId === parseInt(json.data[json.data.length - 1].id)) {
                    box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
                }

            } else if (lastMessageId === 0 && json.data && json.data.length === 0) {
                if (isDmView) {
                    const dmName = document.getElementById('headerChannelName').textContent;
                    box.innerHTML = `<div class="state-center">
                        <div class="state-icon"><i class="fa-regular fa-comment-dots"></i></div>
                        <p>Chat with <strong>${escHtml(dmName)}</strong></p>
                        <small>Start your conversation! Say hello 👋</small>
                    </div>`;
                } else {
                    box.innerHTML = `<div class="state-center">
                        <div class="state-icon"><i class="fa-solid fa-hashtag"></i></div>
                        <p>Welcome to <strong>#${escHtml(currentChannel)}</strong>!</p>
                        <small>This is the very beginning of the channel. Say hello 👋</small>
                    </div>`;
                }
            }
        } catch (e) {
            if (myEpoch === loadEpoch) console.error('loadMessages error:', e);
        }
    }

    /* ══════════════ SEND MESSAGE ══════════════ */
    async function sendMessage(e) {
        e.preventDefault();
        const input = document.getElementById('messageInput');
        const fileInput = document.getElementById('fileInput');
        const sendBtn = document.getElementById('sendBtn');
        const text = input.value.trim();
        const files = fileInput.files;

        if (!text && files.length === 0) return;

        // Build data BEFORE clearing UI to ensure files are captured
        const formData = new FormData();
        formData.append('team_id', TEAM_ID);
        formData.append('channel', currentChannel);
        formData.append('message', text);

        if (files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                formData.append('attachment[]', files[i]);
            }
        }

        // Now clear UI
        input.value = '';
        input.focus();
        sendBtn.disabled = true;
        clearFile(); // Reset UI

        try {
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                body: formData
            });

            const rawText = await res.text();
            let json;
            try {
                json = JSON.parse(rawText);
            } catch (e) {
                console.error('Invalid JSON response:', rawText);
                const tmp = document.createElement("div");
                tmp.innerHTML = rawText;
                let errMsg = (tmp.innerText || tmp.textContent || 'Unknown server error').trim();

                // Friendly error for size limit
                if (errMsg.includes('POST Content-Length') || errMsg.includes('exceeds the limit')) {
                    errMsg = 'File too large (exceeds 1GB limit)';
                }

                if (errMsg.length > 100) errMsg = errMsg.substring(0, 100) + '...';

                Notify.err('Error: ' + errMsg);
                input.value = text;
                return;
            }

            if (!json.success) {
                Notify.err(json.message || 'Failed to send message');
                input.value = text;
            } else {
                loadMessages();
                if (isDmView) loadRecentDms();
            }
        } catch (err) {
            console.error('sendMessage error:', err);
            Notify.err('Failed to send message. Please try again.');
            input.value = text;
        } finally {
            sendBtn.disabled = false;
        }
    }

    /* ══════════════ ATTACHMENTS & EMOJI ══════════════ */
    function handleFileSelect(input) {
        const files = input.files;
        if (!files || files.length === 0) return;

        const name = files.length === 1 ? files[0].name : `${files.length} files selected`;
        document.getElementById('fileName').textContent = name;
        document.getElementById('filePreviewArea').style.display = 'flex';
        document.getElementById('messageInput').focus();
    }

    function clearFile() {
        const input = document.getElementById('fileInput');
        input.value = ''; // Reset
        document.getElementById('filePreviewArea').style.display = 'none';
        document.getElementById('fileName').textContent = '';
    }

    function toggleEmojiPicker(e) {
        e.stopPropagation();
        e.preventDefault();
        const p = document.getElementById('emojiPicker');
        p.classList.toggle('show');
        if (p.children.length === 0) renderEmojiPicker();
    }

    function renderEmojiPicker() {
        const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '👍', '👎', '👋', '👏', '🤝', '🤞', '✌️', '🤘', '👊', '🙏', '💪', '🧠', '👀', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💔', '🔥', '✨', '🌟', '💥', '💯', '💢', '💬', '💭'];
        const el = document.getElementById('emojiPicker');
        el.innerHTML = emojis.map(e => `<div class="emoji-item" onclick="insertEmoji('${e}')">${e}</div>`).join('');
    }

    function insertEmoji(e) {
        const input = document.getElementById('messageInput');
        input.value += e;
        input.focus();
        // Don't close picker immediately to allow multiple inserts?
        // document.getElementById('emojiPicker').classList.remove('show');
    }

    // Close emoji picker on click outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.emoji-picker') && !e.target.closest('.btn-emoji')) {
            const p = document.getElementById('emojiPicker');
            if (p) p.classList.remove('show');
        }
    });

    /* ══════════════ CREATE CHANNEL MODAL ══════════════ */
    let selectedCreateMemberIds = new Set();

    function openModal() {
        document.getElementById('createChanModal').classList.add('open');
        selectedCreateMemberIds = new Set();
        const searchInput = document.getElementById('createMemberSearch');
        if (searchInput) searchInput.value = '';
        renderCreateMemberList();
        setTimeout(() => document.getElementById('newChanName').focus(), 50);
    }

    function closeModal() {
        document.getElementById('createChanModal').classList.remove('open');
        document.getElementById('newChanName').value = '';
        document.getElementById('createChanBtn').disabled = false;
    }

    function handleOverlayClick(e) {
        if (e.target === document.getElementById('createChanModal')) closeModal();
    }

    function renderCreateMemberList(query = '') {
        const el = document.getElementById('createMemberList');
        if (!el) return;

        let filtered = allMembers.filter(u => u.id != CURRENT_USER);
        if (query) {
            query = query.toLowerCase();
            filtered = filtered.filter(u =>
                (u.full_name || '').toLowerCase().includes(query) ||
                (u.username || '').toLowerCase().includes(query)
            );
        }

        if (!filtered || filtered.length === 0) {
            el.innerHTML = `<div style="padding:0.75rem;font-size:0.8rem;color:#9ca3af;text-align:center;">No matching members found</div>`;
            return;
        }

        el.innerHTML = filtered.map(u => {
            let name = u.full_name || u.username || 'Unknown';
            let role = (u.role || 'member');
            // Capitalize role
            role = role.charAt(0).toUpperCase() + role.slice(1);
            let uname = u.username || '';
            let meta = `${role} · @${uname}`;

            const ini = initials(name);
            const color = avatarColor(name);
            const isSelected = selectedCreateMemberIds.has(u.id);

            return `<div class="create-member-item ${isSelected ? 'selected' : ''}" data-uid="${u.id}">
                <div class="cmi-avatar" style="background:${color}">${ini}</div>
                <div class="cmi-info">
                    <div class="cmi-name">${escHtml(name)}</div>
                    <div class="cmi-meta">${escHtml(meta)}</div>
                </div>
                <div class="cmi-action">
                    <button class="cmi-btn ${isSelected ? 'remove' : 'add'}" onclick="toggleCreateMember(this)">
                        ${isSelected ? 'Remove' : 'Add'}
                    </button>
                </div>
            </div>`;
        }).join('');
    }

    function toggleCreateMember(btn) {
        const item = btn.closest('.create-member-item');
        const uid = parseInt(item.dataset.uid);

        if (selectedCreateMemberIds.has(uid)) {
            selectedCreateMemberIds.delete(uid);
            item.classList.remove('selected');
            btn.textContent = 'Add';
            btn.className = 'cmi-btn add';
        } else {
            selectedCreateMemberIds.add(uid);
            item.classList.add('selected');
            btn.textContent = 'Remove';
            btn.className = 'cmi-btn remove';
        }
    }

    function getSelectedCreateMembers() {
        return Array.from(selectedCreateMemberIds);
    }

    async function submitChannel() {
        const input = document.getElementById('newChanName');
        const btn = document.getElementById('createChanBtn');
        let rawName = input.value.trim();

        if (!rawName) {
            input.focus();
            return;
        }

        // Sanitise: lowercase, spaces→dashes, strip non alphanumeric/dash
        const name = rawName.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
        if (!name) {
            Notify.err('Invalid channel name. Use letters, numbers and dashes only.');
            return;
        }

        const memberIds = getSelectedCreateMembers();

        btn.disabled = true;
        btn.textContent = 'Creating…';

        try {
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create_channel', team_id: TEAM_ID, name, member_ids: memberIds })
            });
            const json = await res.json();

            if (json.success) {
                Notify.ok(`Channel #${name} created!`);
                closeModal();
                await loadChannels();
                // Find the new channel's ID
                const newChan = channels.find(c => c.name === name);
                switchChannel(name, newChan ? newChan.id : null);
            } else {
                Notify.err(json.message || 'Could not create channel.');
                btn.disabled = false;
                btn.textContent = 'Create Channel';
            }
        } catch (err) {
            console.error('submitChannel error:', err);
            Notify.err('Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Create Channel';
        }
    }



    /* ══════════════ MANAGE MEMBERS MODAL ══════════════ */
    async function openMembersModal() {
        if (!currentChannelId) {
            Notify.err('Select a channel first.');
            return;
        }
        document.getElementById('mmChannelBadge').textContent = `#${currentChannel}`;
        document.getElementById('mmSearchInput').value = '';
        document.getElementById('manageMembersModal').classList.add('open');
        mmCurrentTab = 'current';

        const isGeneral = (currentChannel || '').toLowerCase() === 'general';
        const addTab = document.querySelector('.mm-tab[data-tab="add"]');
        if (addTab) addTab.style.display = isGeneral ? 'none' : '';

        updateMmTabs();
        await loadChannelMembers();
    }

    function closeMembersModal() {
        document.getElementById('manageMembersModal').classList.remove('open');
    }

    function switchMmTab(tab) {
        mmCurrentTab = tab;
        updateMmTabs();
        renderMmBody();
    }

    function updateMmTabs() {
        document.querySelectorAll('.mm-tab').forEach(t => {
            t.classList.toggle('active', t.dataset.tab === mmCurrentTab);
        });
    }

    async function loadChannelMembers() {
        try {
            const res = await fetch(`/api/chat.php?team_id=${TEAM_ID}&action=channel_members&channel_id=${currentChannelId}`);
            const json = await res.json();
            if (json.success && Array.isArray(json.members)) {
                channelMembers = json.members;
            }
        } catch (e) {
            console.error('loadChannelMembers error:', e);
        }
        renderMmBody();
    }

    function renderMmBody(filterQuery) {
        const body = document.getElementById('mmBody');
        const query = (filterQuery || document.getElementById('mmSearchInput').value || '').toLowerCase().trim();

        const memberIds = new Set(channelMembers.map(m => m.id));

        // Update tab counts
        const nonMembers = allMembers.filter(u => !memberIds.has(u.id));
        document.getElementById('currentCount').textContent = channelMembers.length;
        document.getElementById('addCount').textContent = nonMembers.length;

        if (mmCurrentTab === 'current') {
            let list = channelMembers;
            if (query) {
                list = list.filter(u => {
                    const n = (u.full_name || u.username || '').toLowerCase();
                    return n.includes(query);
                });
            }

            if (list.length === 0) {
                body.innerHTML = `<div class="mm-empty">
                    <i class="fa-solid fa-users-slash"></i>
                    ${query ? 'No matching members' : 'No members in this channel'}
                </div>`;
                return;
            }

            body.innerHTML = list.map(u => {
                const name = u.full_name || u.username || 'Unknown';
                const ini = initials(name);
                const color = avatarColor(name);
                const isSelf = u.id == CURRENT_USER;
                return `<div class="mm-user-row">
                    <div class="mm-user-avatar" style="background:${color}">${ini}</div>
                    <div class="mm-user-info">
                        <div class="mm-user-name">${escHtml(name)} ${isSelf ? '<span style="color:#10b981;font-size:0.7rem;">(you)</span>' : ''}</div>
                        <div class="mm-user-meta">${escHtml(u.role || 'member')} · @${escHtml(u.username || '')}</div>
                    </div>
                    ${!isSelf && (currentChannel || '').toLowerCase() !== 'general' && CURRENT_USER_ROLE === 'admin' ? `<button class="mm-action-btn mm-remove-btn" data-uid="${u.id}" data-name="${escAttr(name)}" onclick="removeMember(${u.id}, this.dataset.name)">
                        <i class="fa-solid fa-user-minus" style="margin-right:4px;"></i> Remove
                    </button>` : ''}
                </div>`;
            }).join('');

        } else {
            // "Add" tab: show team members who are NOT in the channel
            let list = nonMembers;
            if (query) {
                list = list.filter(u => {
                    const n = (u.full_name || u.username || '').toLowerCase();
                    return n.includes(query);
                });
            }

            if (list.length === 0) {
                body.innerHTML = `<div class="mm-empty">
                    <i class="fa-solid fa-user-check"></i>
                    ${query ? 'No matching members to add' : 'All team members are already in this channel'}
                </div>`;
                return;
            }

            body.innerHTML = list.map(u => {
                const name = u.full_name || u.username || 'Unknown';
                const ini = initials(name);
                const color = avatarColor(name);
                return `<div class="mm-user-row">
                    <div class="mm-user-avatar" style="background:${color}">${ini}</div>
                    <div class="mm-user-info">
                        <div class="mm-user-name">${escHtml(name)}</div>
                        <div class="mm-user-meta">${escHtml(u.role || 'member')} · @${escHtml(u.username || '')}</div>
                    </div>
                    ${CURRENT_USER_ROLE === 'admin' ? `<button class="mm-action-btn mm-add-btn" data-uid="${u.id}" data-name="${escAttr(name)}" onclick="addMember(${u.id}, this.dataset.name)">
                        <i class="fa-solid fa-user-plus" style="margin-right:4px;"></i> Add
                    </button>` : ''}
                </div>`;
            }).join('');
        }
    }

    function filterManagedMembers(query) {
        renderMmBody(query);
    }

    async function addMember(userId, name) {
        try {
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add_member', channel_id: currentChannelId, user_id: userId })
            });
            const json = await res.json();
            if (json.success) {
                Notify.ok(`${name} added to #${currentChannel}`);
                await loadChannelMembers();
            } else {
                Notify.err(json.message || 'Could not add member.');
            }
        } catch (err) {
            console.error('addMember error:', err);
            Notify.err('Network error.');
        }
    }

    async function removeMember(userId, name) {
        const ok = await showConfirm('Remove Member', `Remove <strong>${escHtml(name)}</strong> from <strong>#${escHtml(currentChannel)}</strong>?`, 'danger');
        if (!ok) return;
        try {
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'remove_member', channel_id: currentChannelId, user_id: userId })
            });
            const json = await res.json();
            if (json.success) {
                Notify.ok(`${name} removed from #${currentChannel}`);
                await loadChannelMembers();
            } else {
                Notify.err(json.message || 'Could not remove member.');
            }
        } catch (err) {
            console.error('removeMember error:', err);
            Notify.err('Network error.');
        }
    }

    /* ══════════════ MESSAGE ACTIONS ══════════════ */
    function toggleMsgMenu(e, msgId) {
        e.stopPropagation();
        closeAllMenus();
        const menu = document.getElementById(`msg-menu-${msgId}`);
        if (menu) menu.classList.toggle('show');
        openMenuMsgId = msgId;
    }

    function closeAllMenus() {
        document.querySelectorAll('.msg-action-menu.show').forEach(m => m.classList.remove('show'));
        const dd = document.getElementById('channelDropdown');
        if (dd) dd.classList.remove('show');
        openMenuMsgId = null;
    }

    // Close menus on any click outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.msg-actions-btn') && !e.target.closest('.msg-action-menu') &&
            !e.target.closest('#btnChannelMenu') && !e.target.closest('.channel-dropdown')) {
            closeAllMenus();
        }
    });

    async function deleteMessage(msgId) {
        closeAllMenus();
        const ok = await showConfirm('Delete Message', 'Are you sure you want to delete this message? This action cannot be undone.', 'danger');
        if (!ok) return;
        try {
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_message', message_id: msgId })
            });
            const json = await res.json();
            if (json.success) {
                const el = document.getElementById(`msg-${msgId}`);
                if (el) {
                    el.style.transition = 'opacity 0.2s, transform 0.2s';
                    el.style.opacity = '0';
                    el.style.transform = 'translateX(20px)';
                    setTimeout(() => el.remove(), 200);
                }
                Notify.ok('Message deleted');
            } else {
                Notify.err(json.message || 'Could not delete message.');
            }
        } catch (err) {
            console.error('deleteMessage error:', err);
            Notify.err('Network error.');
        }
    }

    function editMessage(msgId) {
        closeAllMenus();
        const el = document.getElementById(`msg-${msgId}`);
        const bubble = document.getElementById(`msg-bubble-${msgId}`);
        if (!el || !bubble) return;

        const oldText = el.dataset.text || bubble.textContent;
        bubble.innerHTML = `
            <div class="msg-edit-wrap">
                <input class="msg-edit-input" id="edit-input-${msgId}" value="${escAttr(oldText)}" onkeydown="if(event.key==='Enter') saveEdit(${msgId}); if(event.key==='Escape') cancelEdit(${msgId}, '${escAttr(oldText)}');">
                <button class="msg-edit-save" onclick="saveEdit(${msgId})">Save</button>
                <button class="msg-edit-cancel" onclick="cancelEdit(${msgId}, '${escAttr(oldText)}')">Cancel</button>
            </div>`;
        const inp = document.getElementById(`edit-input-${msgId}`);
        if (inp) { inp.focus(); inp.setSelectionRange(inp.value.length, inp.value.length); }
    }

    async function saveEdit(msgId) {
        const inp = document.getElementById(`edit-input-${msgId}`);
        if (!inp) return;
        const newText = inp.value.trim();
        if (!newText) { Notify.err('Message cannot be empty.'); return; }

        try {
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'edit_message', message_id: msgId, message: newText })
            });
            const json = await res.json();
            if (json.success) {
                const bubble = document.getElementById(`msg-bubble-${msgId}`);
                const el = document.getElementById(`msg-${msgId}`);
                if (bubble) bubble.textContent = newText;
                if (el) el.dataset.text = newText;
                Notify.ok('Message updated');
            } else {
                Notify.err(json.message || 'Could not edit message.');
            }
        } catch (err) {
            console.error('saveEdit error:', err);
            Notify.err('Network error.');
        }
    }

    function cancelEdit(msgId, originalText) {
        const bubble = document.getElementById(`msg-bubble-${msgId}`);
        if (bubble) bubble.textContent = originalText;
    }

    /* ══════════════ CHANNEL MENU ══════════════ */
    function toggleChannelMenu(e) {
        e.stopPropagation();
        closeAllMenus();
        const dd = document.getElementById('channelDropdown');
        if (dd) dd.classList.toggle('show');

        // Update delete button visibility
        const delBtn = document.getElementById('deleteChannelBtn');
        const clearBtn = document.getElementById('clearAllBtn');

        if (delBtn) {
            delBtn.style.display = (isDmView || currentChannel.toLowerCase() === 'general') ? 'none' : '';
        }
        if (clearBtn) {
            const rawRole = (CURRENT_USER_ROLE || '').toLowerCase().trim();
            const isAdmin = (rawRole === 'admin' || rawRole === 'administrator');
            // Show for DMs (everyone) OR General (admins only)
            const showClear = isDmView || (isAdmin && currentChannel === 'General');
            clearBtn.style.display = showClear ? '' : 'none';

            // Hide divider if both primary actions are hidden
            const divider = document.querySelector('.channel-dropdown-divider');
            if (divider) {
                const showDel = (delBtn && delBtn.style.display !== 'none');
                divider.style.display = (showDel || showClear) ? '' : 'none';
            }
        }
    }

    async function clearAllMessages() {
        closeAllMenus();
        const label = isDmView ? 'this conversation' : `#${currentChannel}`;
        const ok = await showConfirm('Clear All Messages', `Clear <strong>ALL</strong> messages in <strong>${escHtml(label)}</strong>? This action cannot be undone.`, 'danger');
        if (!ok) return;

        try {
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_messages', team_id: TEAM_ID, channel: currentChannel })
            });
            const json = await res.json();
            if (json.success) {
                lastMessageId = 0;
                loadEpoch++;
                Notify.ok(`Cleared ${json.deleted || 0} messages`);
                // Reload empty state
                const box = document.getElementById('chatMessages');
                box.innerHTML = '';
                loadMessages();
                if (isDmView) loadRecentDms();
            } else {
                Notify.err(json.message || 'Could not clear messages.');
            }
        } catch (err) {
            console.error('clearAllMessages error:', err);
            Notify.err('Network error.');
        }
    }

    async function deleteChannel() {
        closeAllMenus();
        if (!currentChannelId || isDmView) {
            Notify.err('Cannot delete this.');
            return;
        }
        if (currentChannel.toLowerCase() === 'general') {
            Notify.err('Cannot delete the General channel.');
            return;
        }
        const ok = await showConfirm('Delete Channel', `Delete <strong>#${escHtml(currentChannel)}</strong>? All messages and members will be permanently removed.`, 'danger');
        if (!ok) return;

        try {
            const res = await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_channel', channel_id: currentChannelId, team_id: TEAM_ID })
            });
            const json = await res.json();
            if (json.success) {
                Notify.ok(`#${currentChannel} deleted`);
                await loadChannels();
                // Switch to General
                const gen = channels.find(c => c.name.toLowerCase() === 'general') || channels[0];
                if (gen) switchChannel(gen.name, gen.id);
            } else {
                Notify.err(json.message || 'Could not delete channel.');
            }
        } catch (err) {
            console.error('deleteChannel error:', err);
            Notify.err('Network error.');
        }
    }

    /* ══════════════ ESCAPE HTML ══════════════ */
    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function escAttr(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /* ══════════════ READ STATUS & NOTIFICATIONS ══════════════ */
    async function markAsRead() {
        try {
            await fetch('/api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark_as_read',
                    channel: currentChannel,
                    channel_id: currentChannelId
                })
            });
            // Update sidebar unread counts
            loadChannels();
            loadRecentDms();
        } catch (e) {
            console.error('markAsRead error:', e);
        }
    }

    async function subscribeToPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

        try {
            const registration = await navigator.serviceWorker.ready;

            // Check if already subscribed
            const existingSub = await registration.pushManager.getSubscription();
            if (existingSub) return;

            // Public VAPID Key (Match with config.php)
            const publicVapidKey = 'BOT34D3Wld3Hw7tnAThhk6XfrY3t-PZ1hMMr6BJJNC6oA0Yx9s6bw4NGF1J9AOvohWXt5y-BSOWXtK9LUftWj7E';
            const convertedKey = urlBase64ToUint8Array(publicVapidKey);

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedKey
            });

            await fetch('/api/push_subscription.php', {
                method: 'POST',
                body: JSON.stringify({ subscription }),
                headers: { 'Content-Type': 'application/json' }
            });
            console.log('Background push notifications enabled');
        } catch (e) {
            console.warn('Push subscription failed:', e);
        }
    }

    /* ══════════════ POLLING ══════════════ */
    let pollCounter = 0;
    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            loadMessages();
            pollCounter++;
            // Refresh sidebar list every 2 polls (~7 seconds)
            if (pollCounter % 2 === 0) loadRecentDms();
        }, 3500);
    }

    /* ══════════════ BOOT ══════════════ */
    document.addEventListener('DOMContentLoaded', async () => {
        // Request notification permission
        if ("Notification" in window && Notification.permission !== "denied") {
            Notification.requestPermission();
        }

        await loadMembers();
        await loadRecentDms();
        await loadChannels();
        await subscribeToPush();

        const saved = localStorage.getItem('chat_active_view');
        let restored = false;

        if (saved) {
            try {
                const data = JSON.parse(saved);
                if (data.type === 'dm') {
                    switchToDM(data.userId, data.name);
                    restored = true;
                } else if (data.type === 'channel') {
                    const ch = channels.find(c => c.name === data.name);
                    if (ch) {
                        currentChannel = null; // force reload
                        switchChannel(ch.name, ch.id || null);
                        restored = true;
                    }
                }
            } catch (e) {
                console.error('Failed to restore chat state:', e);
            }
        }

        if (!restored) {
            if (channels.length > 0) {
                const firstChan = channels.find(c => c.name === 'General') || channels[0];
                currentChannel = null;
                switchChannel(firstChan.name, firstChan.id || null);
            } else {
                currentChannel = null;
                switchChannel('General', null);
            }
        }
    });
</script>




<?php include __DIR__ . '/../src/components/ui/glass-toast.php'; ?>

<?php endLayout(); ?>