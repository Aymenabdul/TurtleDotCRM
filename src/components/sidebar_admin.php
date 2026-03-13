<?php
// Expects $user array and optional $currentPage variable to be available
global $pdo;
$currentPage = $currentPage ?? '';
$isTeamPage = strpos($_SERVER['REQUEST_URI'], 'team_members.php') !== false;

// Fetch available teams for context defaulting
$sidebarTeams = [];
try {
    $stmt = $pdo->query("SELECT id, name, status FROM teams ORDER BY name ASC");
    $sidebarTeams = $stmt->fetchAll();
} catch (PDOException $e) { }

// Determine tactical context: URL > Session > First Team
$contextTeamId = $_GET['team_id'] ?? $_GET['id'] ?? $_SESSION['last_team_id'] ?? null;
if (!$contextTeamId && !empty($sidebarTeams)) {
    $contextTeamId = $sidebarTeams[0]['id'];
}

?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-glass"></div>
    <div class="sidebar-center-toggle" onclick="toggleSidebar()">
        <i class="fa-solid fa-chevron-left"></i>
    </div>
    <div class="sidebar-header">
        <img src="/assets/images/turtle_logo.png" alt="Turtle Symbol" class="sidebar-logo">
        <img src="/assets/images/textlogo.png" alt="Turtle Dot" class="sidebar-title">
    </div>

    <div class="sidebar-nav">
        <!-- Admin Navigation -->
        <a href="/admin_dashboard.php"
            class="nav-item <?php echo ($currentPage === 'dashboard' || $currentPage === 'index') ? 'active' : ''; ?>">
            <i class="fa-solid fa-grip"></i>
            <span class="nav-item-text">Dashboard</span>
        </a>
        <a href="/manage_teams.php" class="nav-item <?php echo $currentPage === 'teams' ? 'active' : ''; ?>">
            <i class="fa-solid fa-user-group"></i>
            <span class="nav-item-text">Teams Manager</span>
        </a>


        <!-- Tactical Units Section -->
        <?php
        if (!empty($sidebarTeams)): ?>
            <div class="sidebar-section-header">
                <div class="sidebar-section-title">
                    <i class="fa-solid fa-network-wired"></i>
                    <span>Teams</span>
                </div>
            </div>
            <div class="sidebar-teams-list">
                <?php foreach ($sidebarTeams as $sTeam):
                    $isActive = $sTeam['status'] === 'active';
                    $isCurrentTeam = ($isTeamPage && $contextTeamId == $sTeam['id']);
                    $teamLetter = strtoupper(substr($sTeam['name'], 0, 1));
                    $colors = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#f43f5e', '#6366f1'];
                    $colorIndex = abs(crc32($sTeam['name'])) % count($colors);
                    $teamColor = $colors[$colorIndex];
                    ?>
                    <a href="/team_members.php?id=<?php echo $sTeam['id']; ?>"
                        class="nav-item team-item <?php echo $isCurrentTeam ? 'active' : ''; ?>">
                        <span class="team-indicator" style="color: <?php echo !$isActive ? '#ef4444' : 'var(--primary)'; ?>">
                            <?php echo $teamLetter; ?>
                        </span>
                        <span class="nav-item-text">
                            <?php echo htmlspecialchars($sTeam['name']); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- System Tools Section -->
        <div class="sidebar-section-header" style="margin-top: 2rem;">
            <div class="sidebar-section-title">
                <i class="fa-solid fa-bolt-lightning"></i>
                <span>Active Tools</span>
            </div>
        </div>
        <div class="sidebar-tools-list">
            <?php
            $adminTools = [
                ['key' => 'tool_word', 'name' => 'Word Engine', 'icon' => 'fa-file-word', 'url' => '/tools/word.php'],
                ['key' => 'tool_spreadsheet', 'name' => 'Grid Processor', 'icon' => 'fa-file-excel', 'url' => '/tools/timesheet.php'],
                ['key' => 'tool_calendar', 'name' => 'Timeline Scheduler', 'icon' => 'fa-calendar-day', 'url' => '/tools/calendar.php'],
                ['key' => 'tool_chat', 'name' => 'Pulse Chat', 'icon' => 'fa-comments', 'url' => '/tools/chat.php'],
                ['key' => 'tool_filemanager' ,'name' => 'Archive Vault', 'icon' => 'fa-folder-open', 'url' => '/tools/files.php'],
                ['key' => 'tool_tasksheet', 'name' => 'Task Logic', 'icon' => 'fa-list-check', 'url' => '/tools/tasks.php'],
                ['key' => 'tool_leadrequirement', 'name' => 'Lead Intake', 'icon' => 'fa-id-card-clip', 'url' => '/tools/leads.php']
            ];
            $currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            foreach ($adminTools as $t):
                $toolPath = rtrim($t['url'], '/');
                $isActive = ($currentPath === $toolPath);
                $toolUrl = $t['url'];
                if ($contextTeamId) {
                    $toolUrl .= '?team_id=' . $contextTeamId;
                }
                ?>
                <a href="<?php echo $toolUrl; ?>" 
                   class="nav-item <?php echo $isActive ? 'active' : ''; ?>"
                   <?php echo ($t['key'] === 'tool_chat') ? 'id="sidebar-pulse-chat"' : ''; ?>>
                    <i class="fa-solid <?php echo $t['icon']; ?>"></i>
                    <span class="nav-item-text"><?php echo $t['name']; ?></span>
                    <?php if ($t['key'] === 'tool_chat'): ?>
                        <span class="unread-badge" id="global-chat-badge" style="display:none; margin-left:auto;"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-wrapper" tabindex="0" onblur="setTimeout(() => this.classList.remove('active'), 150)"
            onclick="this.classList.toggle('active')">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php echo strtoupper(substr($user['username'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">
                        <?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Admin'); ?>
                    </div>
                    <div class="sidebar-user-role">Administrator</div>
                </div>
            </div>

            <div class="user-dropdown-menu">
                <a href="#" class="dropdown-item">
                    <i class="fa-solid fa-user"></i>
                    <span>Profile Settings</span>
                </a>

                <!-- 🚀 Persistent Desktop Install Button (PWA) -->
                <button class="dropdown-item pwa-install-btn" onclick="installPWA()" style="display: none;">
                    <i class="fa-solid fa-download"></i>
                    <span>Install App</span>
                </button>

                <div style="height: 1px; background: #e2e8f0; margin: 4px 0;"></div>
                <button onclick="logout()" class="dropdown-item danger">
                    <i class="fa-solid fa-sign-out-alt"></i>
                    <span>Log out</span>
                </button>
            </div>
        </div>

        <style>
            @media (max-width: 1024px) {
                .pc-app-download { display: none !important; }
            }
        </style>
    </div>
</aside>