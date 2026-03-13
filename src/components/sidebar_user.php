<?php
// Expects $user array and optional $currentPage variable to be available
global $pdo;
$currentPage = $currentPage ?? '';
$contextTeamId = $user['team_id'] ?? null;

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
        <!-- User Navigation -->
        <a href="/index.php"
            class="nav-item <?php echo ($currentPage === 'index' || $currentPage === 'dashboard') ? 'active' : ''; ?>">
            <i class="fa-solid fa-grip"></i>
            <span class="nav-item-text">Dashboard</span>
        </a>

        <!-- Team Tools Section -->
        <?php if ($contextTeamId): ?>
            <?php
            $team = null;
            try {
                $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
                $stmt->execute([$contextTeamId]);
                $team = $stmt->fetch();
            } catch (PDOException $e) {
            }

            if ($team): ?>
                <div class="sidebar-section-header" style="margin-top: 2rem;">
                    <div class="sidebar-section-title">
                        <i class="fa-solid fa-bolt-lightning"></i>
                        <span>Active Tools</span>
                    </div>
                </div>
                <div class="sidebar-tools-list">
                    <?php
                    $tools = [
                        'tool_word' => ['name' => 'Word Engine', 'icon' => 'fa-file-word', 'url' => '/tools/word.php'],
                        'tool_spreadsheet' => ['name' => 'Grid Processor', 'icon' => 'fa-file-excel', 'url' => '/tools/timesheet.php'],
                        'tool_calendar' => ['name' => 'Timeline Scheduler', 'icon' => 'fa-calendar-day', 'url' => '/tools/calendar.php'],
                        'tool_chat' => ['name' => 'Pulse Chat', 'icon' => 'fa-comments', 'url' => '/tools/chat.php'],
                        'tool_filemanager' => ['name' => 'Archive Vault', 'icon' => 'fa-folder-open', 'url' => '/tools/files.php'],
                        'tool_tasksheet' => ['name' => 'Task Logic', 'icon' => 'fa-list-check', 'url' => '/tools/tasks.php'],
                        'tool_leadrequirement' => ['name' => 'Lead Intake', 'icon' => 'fa-id-card-clip', 'url' => '/tools/leads.php']
                    ];
                    $currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
                    foreach ($tools as $key => $toolData):
                        if (isset($team[$key]) && $team[$key] == 1):
                            $toolUrl = $toolData['url'] . '?team_id=' . $team['id'];
                            $toolPath = rtrim($toolData['url'], '/');
                            $isActive = ($currentPath === $toolPath);
                            ?>
                            <a href="<?php echo $toolUrl; ?>"
                               class="nav-item <?php echo $isActive ? 'active' : ''; ?>"
                               <?php echo ($key === 'tool_chat') ? 'id="sidebar-pulse-chat"' : ''; ?>>
                                 <i class="fa-solid <?php echo $toolData['icon']; ?>"></i>
                                 <span class="nav-item-text">
                                     <?php echo $toolData['name']; ?>
                                 </span>
                                 <?php if ($key === 'tool_chat'): ?>
                                     <span class="unread-badge" id="global-chat-badge" style="display:none; margin-left:auto;"></span>
                                 <?php endif; ?>
                            </a>
                            <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-wrapper" tabindex="0" onblur="setTimeout(() => this.classList.remove('active'), 150)"
            onclick="this.classList.toggle('active')">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">
                        <?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?>
                    </div>
                    <div class="sidebar-user-role">
                        <?php echo htmlspecialchars($user['role'] ?? 'user'); ?>
                    </div>
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