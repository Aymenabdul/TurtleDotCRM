<?php
// Expects $user array and optional $currentPage variable to be available
global $pdo;
$currentPage = $currentPage ?? '';
$contextTeamId = $_GET['team_id'] ?? $_GET['id'] ?? null;
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
        <!-- Admin Navigation -->
        <a href="/admin_dashboard.php"
            class="nav-item <?php echo ($currentPage === 'dashboard' || $currentPage === 'index') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-line"></i>
            <span class="nav-item-text">Dashboard</span>
        </a>
        <a href="/manage_teams.php" class="nav-item <?php echo $currentPage === 'teams' ? 'active' : ''; ?>">
            <i class="fa-solid fa-users-gear"></i>
            <span class="nav-item-text">Manage Teams</span>
        </a>

        <!-- Tactical Units Section -->
        <?php
        $sidebarTeams = [];
        try {
            $stmt = $pdo->query("SELECT id, name, status FROM teams ORDER BY name ASC");
            $sidebarTeams = $stmt->fetchAll();
        } catch (PDOException $e) {
        }

        if (!empty($sidebarTeams)): ?>
            <div class="sidebar-section-header">
                <div class="sidebar-section-title">
                    <i class="fa-solid fa-layer-group" style="font-size: 0.9rem; color: #10b981;"></i>
                    <span>Tactical Units</span>
                </div>
            </div>
            <div class="sidebar-teams-list" style="display: flex; flex-direction: column; gap: 4px; padding: 0 0.65rem;">
                <?php foreach ($sidebarTeams as $sTeam):
                    $isActive = $sTeam['status'] === 'active';
                    $isCurrentTeam = $contextTeamId == $sTeam['id'];
                    $teamLetter = strtoupper(substr($sTeam['name'], 0, 1));
                    $colors = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#f43f5e', '#6366f1'];
                    $colorIndex = abs(crc32($sTeam['name'])) % count($colors);
                    $teamColor = $colors[$colorIndex];
                    ?>
                    <a href="/team_members.php?id=<?php echo $sTeam['id']; ?>"
                        class="nav-item team-item <?php echo $isCurrentTeam ? 'active' : ''; ?>"
                        style="padding: 0.8rem 1rem; opacity: <?php echo $isActive ? '1' : '0.8'; ?>;">
                        <span class="team-indicator"
                            style="position: relative; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-right: 0.85rem; background: <?php echo $isActive ? $teamColor . '15' : '#f1f5f9'; ?>; border-radius: 10px; border: 1.5px solid <?php echo $isActive ? $teamColor . '30' : '#e2e8f0'; ?>; transition: all 0.3s ease;">
                            <span
                                style="font-size: 0.9rem; font-weight: 700; color: <?php echo $isActive ? $teamColor : '#94a3b8'; ?>;">
                                <?php echo $teamLetter; ?>
                            </span>
                            <span
                                style="position: absolute; top: -3px; right: -3px; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $isActive ? '#10b981' : '#94a3b8'; ?>; border: 2.5px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></span>
                        </span>
                        <span class="nav-item-text"
                            style="font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            <?php echo htmlspecialchars($sTeam['name']); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-wrapper" tabindex="0" onblur="setTimeout(() => this.classList.remove('active'), 150)"
            onclick="this.classList.toggle('active')">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar" style="width: 40px; height: 40px; border-radius: 14px;">
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
                <div style="height: 1px; background: #e2e8f0; margin: 4px 0;"></div>
                <button onclick="logout()" class="dropdown-item danger">
                    <i class="fa-solid fa-sign-out-alt"></i>
                    <span>Log out</span>
                </button>
            </div>
        </div>
    </div>
</aside>