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

    // Fetch user status from DB for persistence
    $uStmt = $pdo->prepare("SELECT presence_status FROM users WHERE id = ?");
    $uStmt->execute([$user['user_id']]);
    $userStatus = $uStmt->fetchColumn() ?: 'online';
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

startLayout("Chat — " . htmlspecialchars($team['name']), $user, false);
?>

<link rel="stylesheet" href="/css/chat.css">

<!-- ============ HTML LAYOUT ============ -->
<div class="chat-root">
    <div class="chat-sidebar-backdrop" id="sidebarBackdrop" onclick="toggleChatSidebar()"></div>

    <!-- ── SIDEBAR ── -->
    <aside class="chat-sidebar">

        <!-- Workspace header -->
        <div class="ws-header">
            <div class="ws-logo">
                <img src="/assets/images/turtle_logo.png" alt="Turtledot">
            </div>
            <div class="ws-info">
                <div class="ws-name">Turtledot</div>
                <div class="ws-sub">Workspace</div>
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

        <!-- Active Status Bar -->
        <div id="activeStatusBar" class="active-status-bar">
            <!-- filled by JS -->
        </div>

        <!-- Direct Messages / Members -->
        <div class="sidebar-section-header" style="padding-top:0.5rem; margin-top: 4px;" id="dmSectionHeader">
            <span>Recent Chats</span>
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
                <button class="mobile-nav-toggle" onclick="toggleChatSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </button>
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
                <div class="presence-container">
                    <?php
                    $statusClass = $userStatus;
                    $statusLabel = ucfirst($userStatus);
                    $iconHtml = '<i class="fa-solid fa-circle" style="animation: pulse 2s infinite;"></i>';
                    if ($userStatus === 'away')
                        $iconHtml = '<i class="fa-solid fa-clock"></i>';
                    if ($userStatus === 'sleep')
                        $iconHtml = '<i class="fa-solid fa-moon"></i>';
                    if ($userStatus === 'offline')
                        $iconHtml = '<i class="fa-regular fa-circle"></i>';
                    ?>
                    <div class="live-badge <?php echo $statusClass; ?>" id="userStatusBadge"
                        onclick="toggleStatusMenu(event)">
                        <div id="userStatusIconWrap" style="display:flex; align-items:center; justify-content:center;">
                            <?php echo $iconHtml; ?>
                        </div>
                        <span id="userStatusLabel"><?php echo $statusLabel; ?></span>
                    </div>

                    <div class="status-dropdown" id="statusDropdown">
                        <div class="status-item status-online" onclick="setUserStatus('online')">
                            <span class="status-icon-box"><i class="fa-solid fa-circle"></i></span>
                            Online
                        </div>
                        <div class="status-item status-away" onclick="setUserStatus('away')">
                            <span class="status-icon-box"><i class="fa-solid fa-clock"></i></span>
                            Away
                        </div>
                        <div class="status-item status-sleep" onclick="setUserStatus('sleep')">
                            <span class="status-icon-box"><i class="fa-solid fa-moon"></i></span>
                            Sleep
                        </div>
                        <div class="status-item status-offline" onclick="setUserStatus('offline')">
                            <span class="status-icon-box"><i class="fa-regular fa-circle"></i></span>
                            Offline
                        </div>
                    </div>
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
    const CURRENT_USER_NAME = '<?php echo addslashes(htmlspecialchars($user['full_name'] ?: $user['username'])); ?>';
    const CURRENT_USER_ROLE = '<?php echo strtolower(trim($user['role'])); ?>';
    const AVATAR_COLORS = ['#8b5cf6', '#3b82f6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#10b981'];

    /* ── State ── */
    var currentChannel = 'General';
    var currentChannelId = null;
    var isDmView = false;
    var lastMessageId = 0;
    var loadEpoch = 0;
    var allMembers = [];
    var recentDms = [];
    var channels = [];
    var channelMembers = [];
    var mmCurrentTab = 'current';
    var isSearching = false;
    var openMenuMsgId = null;
    var isChatFirstCheck = true;
    var chatLastUnreadState = {};
    var activeDmPartnerId = null;



    window.ChatState = {
        currentChannel: 'General',
        currentChannelId: null,
        isDmView: false,
        activeDmPartnerId: null
    };

    updateGlobalChatState();
    function updateGlobalChatState() {
        window.ChatState.currentChannel = currentChannel;
        window.ChatState.currentChannelId = currentChannelId;
        window.ChatState.isDmView = isDmView;
        window.ChatState.activeDmPartnerId = activeDmPartnerId;
    }

    // Expose key functions globally so FCM handler in base_layout.php
    // can trigger a real-time message load when a push arrives.
    // NOTE: Assigned as direct references next to each function definition
    // to avoid scope resolution issues.


    /* ── Notification Helpers ── */
    function playNotificationSound() {
        const sound = document.getElementById('msgSound');
        if (sound) {
            sound.currentTime = 0;
            sound.play().catch(e => console.warn('Sound play failed:', e));
        }
    }

    function syncActiveChannelWithSW() {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            let activeChan = currentChannel;
            if (isDmView && activeDmPartnerId) {
                activeChan = (CURRENT_USER < activeDmPartnerId)
                    ? `dm-${CURRENT_USER}-${activeDmPartnerId}`
                    : `dm-${activeDmPartnerId}-${CURRENT_USER}`;
            }

            navigator.serviceWorker.controller.postMessage({
                type: 'SET_ACTIVE_CHANNEL',
                channel: activeChan,
                user_id: CURRENT_USER
            });
        }
    }
    function checkUnreadAlerts(dms, chans) {
        // Notifications are now handled exclusively by FCM in base_layout.php
        // We only use this to sync internal state now
        const shouldNotify = !isChatFirstCheck;

        if (Array.isArray(dms)) {
            dms.forEach(d => {
                const p = d.partner;
                if (!p) return;
                const key = `dm-${p.id}`;
                chatLastUnreadState[key] = d.unread_count || 0;
            });
        }

        if (Array.isArray(chans)) {
            chans.forEach(c => {
                const key = `chan-${c.name}`;
                chatLastUnreadState[key] = c.unread_count || 0;
            });
        }
        isChatFirstCheck = false;
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

    function getPresenceIconHTML(status) {
        const s = (status || 'online').toLowerCase();
        if (s === 'online') return `<i class="fa-solid fa-circle" style="animation: pulse 2s infinite;"></i>`;
        if (s === 'away') return `<i class="fa-solid fa-clock"></i>`;
        if (s === 'sleep') return `<i class="fa-solid fa-moon"></i>`;
        return `<i class="fa-regular fa-circle"></i>`; // offline
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
            // CRITICAL: Force unread count to 0 if this is the active channel and window is focused
            let displayUnreadCount = c.unread_count || 0;
            if (active && document.hasFocus()) {
                displayUnreadCount = 0;
            }

            const unread = (displayUnreadCount > 0) ? `<span class="unread-badge">${displayUnreadCount}</span>` : '';
            return `<div class="sidebar-item ${active}" onclick="switchChannel('${escAttr(c.name)}', ${c.id || 'null'})">
                        <span class="item-icon"><i class="fa-solid fa-hashtag"></i></span>
                        <span class="item-label">${escHtml(c.name)}</span>
                        ${unread}
                    </div>`;
        }).join('');
    }

    function switchChannel(name, channelId) {
        document.querySelector('.chat-root').classList.remove('sidebar-open');
        if (currentChannel === name && !isDmView) return;
        currentChannel = name;
        currentChannelId = channelId;
        isDmView = false;
        lastMessageId = 0;
        loadEpoch++;
        activeDmPartnerId = null;
        updateGlobalChatState();

        document.getElementById('headerChannelName').textContent = name;
        document.getElementById('headerSubtitle').textContent = 'Team channel';
        document.getElementById('headerIcon').innerHTML = '<i class="fa-solid fa-hashtag"></i>';
        document.getElementById('headerIcon').style.background = 'rgba(255, 255, 255, 0.03)';
        document.getElementById('headerIcon').style.borderColor = 'rgba(16, 185, 129, 0.4)';
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
        syncActiveChannelWithSW();

        const box = document.getElementById('chatMessages');
        box.innerHTML = `<div class="state-center">
            <div class="state-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
            <p>Loading #${escHtml(name)}…</p>
        </div>`;

        loadMessages();
        markAsRead();

        // Save state
        localStorage.setItem('chat_active_view', JSON.stringify({
            type: 'channel',
            name: name,
            id: channelId
        }));
    }

    function switchToDM(userId, name) {
        document.querySelector('.chat-root').classList.remove('sidebar-open');
        if (!userId) return;
        const dmChannel = `dm-${Math.min(CURRENT_USER, userId)}-${Math.max(CURRENT_USER, userId)}`;

        // Update State
        isDmView = true;
        currentChannel = dmChannel;
        currentChannelId = null;
        lastMessageId = 0;
        loadEpoch++;
        activeDmPartnerId = userId;
        updateGlobalChatState();

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

        const partner = allMembers.find(m => m.id == userId);
        const presence = partner ? (partner.presence_status || 'online') : 'online';

        if (hdrName) hdrName.textContent = name;
        if (partner) updateHeaderStatus(partner);
        if (msgInp) msgInp.placeholder = `Message ${name}…`;
        if (btnSet) btnSet.style.display = 'none';

        if (hdrIcon) {
            const color = avatarColor(name);
            hdrIcon.innerHTML = initials(name);
            hdrIcon.style.background = 'rgba(255, 255, 255, 0.03)';
            hdrIcon.style.borderColor = color + '66';
            hdrIcon.style.color = color;
        }

        // Deselect channels & Highlight DM
        renderChannels();
        renderRecentDms(userId);
        syncActiveChannelWithSW();

        if (box) {
            box.scrollTop = 0;
            box.innerHTML = `<div class="state-center">
                <div class="state-icon"><i class="fa-solid fa-spinner fa-spin"></i></div>
                <p>Loading conversation with ${escHtml(name)}…</p>
            </div>`;
        }

        loadMessages();
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
                // Force UI updates when members (and their presence) change
                renderRecentDms();
                renderActiveStatusBar();
                updateMessageAvatars();
                if (isDmView && activeDmPartnerId) {
                    const partner = allMembers.find(m => m.id == activeDmPartnerId);
                    if (partner) updateHeaderStatus(partner);
                }
            }
        } catch (e) {
            console.error('loadMembers error:', e);
        }
    }

    function updateMessageAvatars() {
        const bubbles = document.querySelectorAll('.msg-group[data-uid]');
        bubbles.forEach(b => {
            const uid = b.dataset.uid;
            const sender = allMembers.find(m => m.id == uid);
            if (sender) {
                const presence = sender.presence_status || 'online';
                const dot = b.querySelector('.member-avatar-dot');
                if (dot && !dot.classList.contains(presence)) {
                    dot.className = `member-avatar-dot ${presence}`;
                    dot.innerHTML = getPresenceIconHTML(presence);
                }
            }
        });
    }

    function updateHeaderStatus(partner) {
        const hdrSub = document.getElementById('headerSubtitle');
        if (!hdrSub) return;
        const presence = partner.presence_status || 'online';
        let statusText = 'Direct Message';
        let statusColor = '#94a3b8';
        if (presence === 'online') { statusText = 'Active now'; statusColor = '#10b981'; }
        else if (presence === 'away') { statusText = 'Away'; statusColor = '#f59e0b'; }
        else if (presence === 'sleep') { statusText = 'Sleeping'; statusColor = '#6366f1'; }
        else if (presence === 'offline') { statusText = 'Offline'; statusColor = '#94a3b8'; }

        let iconHtml = getPresenceIconHTML(presence);
        hdrSub.innerHTML = `<span style="color:${statusColor}; font-weight:600;"><span style="font-size:8px; margin-right:4px; display:inline-flex; align-items:center;">${iconHtml}</span> ${statusText}</span>`;
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
        renderRecentDms(activeDmPartnerId);
        checkUnreadAlerts(recentDms, channels);
    }

    function renderRecentDms(activeDmUserId = activeDmPartnerId) {
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

            // Sync status with allMembers for consistency
            const latestInfo = allMembers.find(m => m.id == p.id);
            const presence = latestInfo ? (latestInfo.presence_status || 'online') : (p.presence_status || 'online');

            const isActive = activeDmUserId && p.id == activeDmUserId;
            // For the tint we consider anything except offline as "online"
            const showPresenceTint = (presence !== 'offline');

            // CRITICAL: If this is the active chat and the window is focused, force unread count to 0 in UI
            let displayUnreadCount = dm.unread_count || 0;
            if (isActive && document.hasFocus() && isDmView) {
                displayUnreadCount = 0;
            }

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

            const unread = (displayUnreadCount > 0) ? `<span class="unread-badge">${displayUnreadCount}</span>` : '';

            return `<div class="member-item${isActive ? ' active' : ''}${showPresenceTint ? ' online-tint' : ''}" onclick="switchToDM(${p.id}, '${escAttr(name)}')" style="cursor:pointer;${isActive ? 'background:rgba(16,185,129,0.18);color:#34d399;' : ''}">
                <div class="member-avatar" style="border-color:${color}66; color:${color}; position:relative; overflow:visible;">
                    ${ini}
                    <div class="member-avatar-dot ${presence}">
                        ${getPresenceIconHTML(presence)}
                    </div>
                </div>
                <div class="member-info" style="flex:1; min-width:0;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="member-name-text">${escHtml(name)}</div>
                        <div style="display:flex; align-items:center;">
                            ${unread}
                            ${(displayUnreadCount === 0) ? `
                            <button class="member-delete-btn" onclick="event.stopPropagation(); deleteRecentChat(${p.id}, '${escAttr(name)}')" title="Delete Chat">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                    <div class="member-last-msg" style="${displayUnreadCount > 0 ? 'font-weight:600; color:white;' : ''}">${escHtml(preview)} ${statusIcon}</div>
                </div>
            </div>`;
        }).join('');

        renderActiveStatusBar();
    }

    function renderActiveStatusBar() {
        const el = document.getElementById('activeStatusBar');
        if (!el) return;

        // Filter actively online members, excluding current user
        const onlineMembers = allMembers.filter(m => (m.presence_status || 'online') !== 'offline' && m.id != CURRENT_USER);

        if (onlineMembers.length === 0) {
            el.innerHTML = '';
            el.style.display = 'none';
            return;
        }

        el.style.display = 'flex';
        el.innerHTML = onlineMembers.map(m => {
            const name = m.full_name || m.username || 'Unknown';
            const firstName = name.split(' ')[0];
            const ini = initials(name);
            const color = avatarColor(name);
            const isActive = isDmView && activeDmPartnerId == m.id;
            const presence = m.presence_status || 'online';

            return `
                <div class="status-item ${isActive ? 'active' : ''}" onclick="switchToDM(${m.id}, '${escAttr(name)}')" title="${escAttr(name)} is ${presence}">
                    <div class="status-avatar-wrap">
                        <div class="status-avatar-inner" style="border-color:${color}55; color:${color}">${ini}</div>
                        <div class="status-dot ${presence}">
                            ${getPresenceIconHTML(presence)}
                        </div>
                    </div>
                    <div class="status-name">${escHtml(firstName)}</div>
                </div>
            `;
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
            const presence = u.presence_status || 'online';
            const uname = u.username || '';
            const role = u.role || 'member';

            return `<div class="member-item" onclick="switchToDM(${u.id}, '${escAttr(name)}')" style="cursor:pointer;">
                <div class="member-avatar" style="border-color:${color}66; color:${color}">
                    ${ini}
                    <div class="member-avatar-dot ${presence}">
                        ${getPresenceIconHTML(presence)}
                    </div>
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
    /* ══════════════ REAL-TIME UPDATES ══════════════ */
    if (typeof messaging !== 'undefined') {
        messaging.onMessage((payload) => {
            // When a new message arrives, refresh the view
            loadMessages();
            loadRecentDms();
            loadChannels();
            loadMembers();
        });
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

            if (json.success && Array.isArray(json.data)) {
                const stateEl = box.querySelector('.state-center');
                if (stateEl) stateEl.remove();

                if (json.data.length > 0) {
                    const oldLastId = lastMessageId;

                    json.data.forEach(msg => {
                        // Skip if already rendered
                        if (document.getElementById(`msg-${msg.id}`)) return;

                        // Clear matching optimistic ghosts when real message arrives
                        if (msg.user_id == CURRENT_USER) {
                            const ghosts = box.querySelectorAll('.msg-group.optimistic');
                            ghosts.forEach(ghost => {
                                if (ghost.dataset.text === msg.message) {
                                    ghost.remove();
                                }
                            });
                        }

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
                        g.dataset.uid = msg.user_id;

                        const sender = allMembers.find(m => m.id == msg.user_id);
                        const presence = sender ? (sender.presence_status || 'online') : (msg.presence_status || 'online');

                        g.innerHTML = `
                        <button class="msg-actions-btn" onclick="toggleMsgMenu(event, ${msg.id})" title="Actions">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                        <div class="msg-action-menu" id="msg-menu-${msg.id}">
                            ${msg.message.includes('[attachment:') ?
                                `<button class="msg-action-item" onclick="downloadAttachmentFromMsg('${msg.message}')"><i class="fa-solid fa-download"></i> Download</button>`
                                : ''
                            }
                            ${mine ? `<button class="msg-action-item" onclick="editMessage(${msg.id})"><i class="fa-solid fa-pen"></i> Edit</button>` : ''}
                            ${(mine || CURRENT_USER_ROLE === 'admin') ? `<button class="msg-action-item danger" onclick="deleteMessage(${msg.id})"><i class="fa-solid fa-trash"></i> Delete</button>` : ''}
                        </div>
                        <div class="msg-avatar" style="border-color:${color}66; color:${color}; position:relative; overflow:visible;">
                            ${ini}
                            <div class="member-avatar-dot ${presence}">
                                ${getPresenceIconHTML(presence)}
                            </div>
                        </div>
                        <div class="msg-body">
                            ${!mine ? `<div class="msg-sender">${escHtml(name)}</div>` : ''}
                            <div class="msg-bubble" id="msg-bubble-${msg.id}">${bodyHtml}</div>
                            <div class="msg-time">${time} ${statusHtml}</div>
                        </div>`;
                        box.appendChild(g);
                        lastMessageId = Math.max(lastMessageId, parseInt(msg.id));

                    });

                    // Notification & Read status logic:
                    // Only notify if we already had a known lastMessageId and user is NOT looking at the chat
                    const latest = json.data[json.data.length - 1];
                    if (oldLastId > 0 && latest && latest.user_id != CURRENT_USER && parseInt(latest.id) > oldLastId) {
                        const isFocused = document.hasFocus();
                        if (!isFocused) {
                            // Silent sync
                        }
                        markAsRead();
                        // Someone sent a message! Speed up the poll so the conversation feels live
                        // No polling trigger here now, relying solely on FCM/WS
                    }

                    if (atBottom || (json.data.length > 0 && lastMessageId === parseInt(json.data[json.data.length - 1].id))) {
                        box.scrollTop = box.scrollHeight;
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
            }
        } catch (e) {
            if (myEpoch === loadEpoch) console.error('loadMessages error:', e);
        }
    }
    window.loadMessages = loadMessages;

    /* ══════════════ SEND MESSAGE ══════════════ */
    async function sendMessage(e) {
        e.preventDefault();
        const input = document.getElementById('messageInput');
        const fileInput = document.getElementById('fileInput');
        const sendBtn = document.getElementById('sendBtn');
        const text = input.value.trim();
        const files = fileInput.files;

        if (!text && files.length === 0) return;

        // 🚀 Optimistic UI: Render the message immediately
        const tempId = 'temp-' + Date.now();
        if (!files.length) {
            appendOptimisticMessage(tempId, text);
        }

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

            // Trigger fast polling to see the "real" message faster
            // Removed triggerFastPoll, relying on FCM

            const rawText = await res.text();
            let json;
            try {
                json = JSON.parse(rawText);
            } catch (e) {
                console.error('Invalid JSON response:', rawText);
                const tmp = document.createElement("div");
                tmp.innerHTML = rawText;
                let errMsg = (tmp.innerText || tmp.textContent || 'Unknown server error').trim();

                // Remove optimistic message on failure
                const optMsg = document.getElementById(`msg-${tempId}`);
                if (optMsg) optMsg.remove();

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
                // Remove optimistic message on failure
                const optMsg = document.getElementById(`msg-${tempId}`);
                if (optMsg) optMsg.remove();

                Notify.err(json.message || 'Failed to send message');
                input.value = text;
            } else {
                // Remove optimistic message once real one arrives or is about to
                const optMsg = document.getElementById(`msg-${tempId}`);
                if (optMsg) optMsg.classList.add('sent-success');

                // Instantly sync the sent message
                loadMessages();
                loadRecentDms();

                if (isDmView) loadRecentDms();
            }
        } catch (err) {
            console.error('sendMessage error:', err);
            // Remove optimistic message on failure
            const optMsg = document.getElementById(`msg-${tempId}`);
            if (optMsg) optMsg.remove();

            Notify.err('Failed to send message. Please try again.');
            input.value = text;
        } finally {
            sendBtn.disabled = false;
        }
    }

    function appendOptimisticMessage(tempId, text) {
        const box = document.getElementById('chatMessages');
        if (!box) return;

        // Use current user info
        const name = CURRENT_USER_NAME;
        const ini = initials(name);
        const color = avatarColor(name);
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        const g = document.createElement('div');
        g.className = 'msg-group mine optimistic';
        g.id = `msg-${tempId}`;
        g.dataset.text = text;

        let bodyHtml = escHtml(text).replace(/\n/g, '<br>');

        g.innerHTML = `
            <div class="msg-avatar" style="border-color:${color}66; color:${color}; position:relative; overflow:visible;">
                ${ini}
                <div class="member-avatar-dot online">
                    ${getPresenceIconHTML('online')}
                </div>
            </div>
            <div class="msg-body">
                <div class="msg-bubble">${bodyHtml}</div>
                <div class="msg-time">${time} <span class="msg-status"><i class="fa-solid fa-clock-rotate-left"></i></span></div>
            </div>`;

        box.appendChild(g);
        box.scrollTop = box.scrollHeight;

        // Remove empty state if present
        const stateEl = box.querySelector('.state-center');
        if (stateEl) stateEl.remove();
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
    function downloadAttachmentFromMsg(text) {
        const match = text.match(/\[attachment:(.*?)\]/);
        if (match && match[1]) {
            const url = match[1];
            const name = url.split('/').pop();
            const a = document.createElement('a');
            a.href = url;
            a.download = name;
            a.target = '_blank';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }

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

        const sdd = document.getElementById('statusDropdown');
        if (sdd) sdd.classList.remove('show');

        openMenuMsgId = null;
    }

    // Close menus on any click outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.msg-actions-btn') && !e.target.closest('.msg-action-menu') &&
            !e.target.closest('#btnChannelMenu') && !e.target.closest('.channel-dropdown') &&
            !e.target.closest('#userStatusBadge') && !e.target.closest('.status-dropdown')) {
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
    function toggleStatusMenu(e) {
        e.stopPropagation();
        const dd = document.getElementById('statusDropdown');
        const isShow = dd.classList.contains('show');
        closeAllMenus();
        if (!isShow) dd.classList.add('show');
    }

    function setUserStatus(status) {
        const badge = document.getElementById('userStatusBadge');
        const label = document.getElementById('userStatusLabel');
        const iconWrap = document.getElementById('userStatusIconWrap');

        badge.className = 'live-badge ' + status;

        let labelText = status.charAt(0).toUpperCase() + status.slice(1);
        let iconHtml = getPresenceIconHTML(status);

        iconWrap.innerHTML = iconHtml;
        label.textContent = labelText;
        closeAllMenus();
        // Persist to DB
        fetch('/api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_status', status: status })
        }).catch(err => console.error('Failed to update status:', err));
    }

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
        if (!currentChannel) return;

        // Optimistically clear local counts to avoid race conditions with poller
        if (isDmView && activeDmPartnerId) {
            const dm = recentDms.find(d => d.partner.id == activeDmPartnerId);
            if (dm) dm.unread_count = 0;
            chatLastUnreadState[`dm-${activeDmPartnerId}`] = 0;
        } else {
            const ch = channels.find(c => c.name === currentChannel);
            if (ch) ch.unread_count = 0;
            chatLastUnreadState[`chan-${currentChannel}`] = 0;
        }
        renderRecentDms();
        renderChannels();

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
        } catch (e) {
        }
    }
    window.markChannelAsRead = markAsRead;


    /* ══════════════ BOOT ══════════════ */
    document.addEventListener('DOMContentLoaded', async () => {
        await loadMembers();
        await loadRecentDms();
        await loadChannels();

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

        // Auto-mark as read when window regains focus
        window.addEventListener('focus', () => {
            if (currentChannel) markAsRead();
        });
    });

    function toggleChatSidebar() {
        document.querySelector('.chat-root').classList.toggle('sidebar-open');
    }
</script>




<?php include __DIR__ . '/../src/components/ui/glass-toast.php'; ?><?php endLayout(); ?>