<?php
// Expects $user array and optional $currentPage variable to be available
global $pdo;
$currentPage = $currentPage ?? '';
$contextTeamId = $user['team_id'] ?? null;
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-glass"></div>
    <button class="sidebar-center-toggle" onclick="toggleSidebar()">
        <i class="fa-solid fa-chevron-left"></i>
    </button>
    <div class="sidebar-header">
        <img src="/assets/images/turtle_logo.png" alt="Turtle Symbol" class="sidebar-logo">
        <img src="/assets/images/textlogo.png" alt="Turtle Dot" class="sidebar-title">
    </div>

    <div class="sidebar-nav">
        <!-- User Navigation -->
        <a href="/index.php"
            class="nav-item <?php echo ($currentPage === 'index' || $currentPage === 'dashboard') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i>
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
                <div class="sidebar-section-header">
                    <div class="sidebar-section-title">
                        <i class="fa-solid fa-toolbox" style="font-size: 0.9rem; color: #10b981;"></i>
                        <span>Team Tools</span>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px; padding: 0 0.65rem;">
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
                    foreach ($tools as $key => $toolData):
                        if (isset($team[$key]) && $team[$key] == 1):
                            $toolUrl = $toolData['url'] . '?team_id=' . $team['id'];
                            $isActive = (strpos($_SERVER['REQUEST_URI'], $toolData['url']) !== false);
                            ?>
                            <a href="<?php echo $toolUrl; ?>" class="nav-item <?php echo $isActive ? 'active' : ''; ?>">
                                <i class="fa-solid <?php echo $toolData['icon']; ?>"></i>
                                <span class="nav-item-text">
                                    <?php echo $toolData['name']; ?>
                                </span>
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
                <div class="sidebar-user-avatar" style="width: 40px; height: 40px; border-radius: 14px;">
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
                <div style="height: 1px; background: #e2e8f0; margin: 4px 0;"></div>
                <button onclick="logout()" class="dropdown-item danger">
                    <i class="fa-solid fa-sign-out-alt"></i>
                    <span>Log out</span>
                </button>
            </div>
        </div>
    </div>
</aside>