<?php
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/src/layouts/base_layout.php';
require_once __DIR__ . '/config.php';

// Require authentication and ensure user is an admin
$user = AuthMiddleware::requireAuth();
$is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin';

if (!$is_admin) {
    header('Location: /index.php');
    exit;
}

// Set the current page flag for the sidebar navigation
$GLOBALS['currentPage'] = 'dashboard';

// Optionally get some stats using the DB
$stats = [
    'users' => 0,
    'clients' => 0,
    'projects' => 0,
    'teams' => 0
];

try {
    // Number of Users (non-admins)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'");
    $stats['users'] = $stmt->fetchColumn();

    // Total Users metric (since is_client doesn't exist yet)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['clients'] = $stmt->fetchColumn();

    // Check if teams exist, if not set gracefully, if there is a teams table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM teams");
        $stats['teams'] = $stmt->fetchColumn();
    } catch (PDOException $e) { /* Table might not exist yet */
    }

    // Projects, similar handling
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM projects");
        $stats['projects'] = $stmt->fetchColumn();
    } catch (PDOException $e) { /* Table might not exist yet */
    }
} catch (PDOException $e) { /* Database connection error handling */
}

// Team Performance Stats
$teamStats = [];
try {
    $stmt = $pdo->query("SELECT 
        t.id, 
        t.name,
        (SELECT COUNT(*) FROM users u WHERE u.team_id = t.id) as member_count,
        (SELECT COUNT(*) FROM leads l WHERE l.team_id = t.id) as lead_count,
        (SELECT COUNT(*) FROM tasks tk JOIN users u ON tk.assigned_to = u.id WHERE u.team_id = t.id) as task_count,
        (SELECT COUNT(*) FROM tasks tk JOIN users u ON tk.assigned_to = u.id WHERE u.team_id = t.id AND tk.status = 'done') as completed_tasks
    FROM teams t
    WHERE t.status = 'active'
    ORDER BY t.name ASC");
    $teamStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silent error or handle as needed
}

// Fetch Members for all active teams
$teamMembers = [];
try {
    $stmt = $pdo->query("SELECT u.id, u.full_name, u.username, u.team_id, u.presence_status, u.role 
                        FROM users u 
                        JOIN teams t ON u.team_id = t.id 
                        WHERE t.status = 'active'
                        ORDER BY u.full_name ASC");
    $allMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allMembers as $m) {
        $teamMembers[$m['team_id']][] = $m;
    }
} catch (PDOException $e) {
}

// Start Layout
startLayout('Admin Dashboard', $user);
?>

<link rel="stylesheet" href="/css/admin/dashboard.css">

<div class="card welcome-card mb-4">
    <div class="card-header" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
        <h2 class="card-title">Welcome back, Admin!</h2>
        <p class="card-subtitle">Here's what's happening in TurtleDot today.</p>
    </div>
</div>

<div class="grid grid-3 mb-4">
    <!-- Active Teams -->
    <div class="card stat-card">
        <div class="flex-between">
            <div>
                <div class="text-muted"
                    style="font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
                    Active Teams</div>
                <div class="stat-value"><?php echo number_format($stats['teams']); ?></div>
            </div>
            <div class="stat-icon-wrapper bg-info-light">
                <i class="fa-solid fa-user-group text-info"></i>
            </div>
        </div>
        <div class="stat-trend text-info">
            <i class="fa-solid fa-circle-check"></i> <span>System Synchronized</span>
        </div>
    </div>

    <!-- Total Users -->
    <div class="card stat-card">
        <div class="flex-between">
            <div>
                <div class="text-muted"
                    style="font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
                    Total Users</div>
                <div class="stat-value"><?php echo number_format($stats['users']); ?></div>
            </div>
            <div class="stat-icon-wrapper bg-success-light">
                <i class="fa-solid fa-user-shield text-success"></i>
            </div>
        </div>
        <div class="stat-trend text-success">
            <i class="fa-solid fa-arrow-trend-up"></i> <span>Verified Platform Access</span>
        </div>
    </div>

    <!-- Projects -->
    <div class="card stat-card">
        <div class="flex-between">
            <div>
                <div class="text-muted"
                    style="font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">
                    Total Projects</div>
                <div class="stat-value"><?php echo number_format($stats['projects']); ?></div>
            </div>
            <div class="stat-icon-wrapper bg-warning-light">
                <i class="fa-solid fa-diagram-project text-warning"></i>
            </div>
        </div>
        <div class="stat-trend text-warning">
            <i class="fa-solid fa-clock-rotate-left"></i> <span>Ongoing Initiatives</span>
        </div>
    </div>
</div>

<!-- Squadron Personnel Command Center -->
<div class="card mb-4" style="border: 1px solid rgba(16, 185, 129, 0.1);">
    <div class="card-header" style="border-bottom: none; margin-bottom: 1rem;">
        <h3 class="card-title" style="font-size: 1.25rem;">Squadron Personnel</h3>
        <p class="card-subtitle">Select a squad to view their active personnel and performance metrics.</p>
    </div>

    <div class="tabs-container">
        <?php foreach ($teamStats as $index => $team): ?>
            <button class="tab-trigger <?php echo $index === 0 ? 'active' : ''; ?>"
                onclick="switchTab(this, 'team-<?php echo $team['id']; ?>')">
                <i class="fa-solid fa-people-group"></i>
                <?php echo htmlspecialchars($team['name']); ?>
                <span class="badge"
                    style="background: rgba(0,0,0,0.05); color: inherit; margin-left: 0.25rem;"><?php echo $team['member_count']; ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($teamStats as $index => $team):
        $rate = $team['task_count'] > 0 ? round(($team['completed_tasks'] / $team['task_count']) * 100) : 0;
        $color = $rate >= 75 ? 'var(--success)' : ($rate >= 40 ? 'var(--warning)' : 'var(--error)');
        ?>
        <div id="team-<?php echo $team['id']; ?>" class="tab-content <?php echo $index === 0 ? 'active' : ''; ?>">
            <div class="squad-stats-container">
                <div class="squad-metrics-wrapper">
                    <div class="squad-metric-item">
                        <div class="squad-metric-label">Capability Rate</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: <?php echo $color; ?>;">
                            <?php echo $rate; ?>%
                        </div>
                    </div>
                    <div class="squad-metric-item">
                        <div class="squad-metric-label">Total Leads</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">
                            <?php echo $team['lead_count']; ?>
                        </div>
                    </div>
                    <div class="squad-metric-item">
                        <div class="squad-metric-label">Completed Tasks</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-muted);">
                            <?php echo $team['completed_tasks']; ?> / <?php echo $team['task_count']; ?>
                        </div>
                    </div>
                </div>
                <div class="squad-stats-action">
                    <a href="/manage_teams.php" class="btn btn-sm btn-secondary">
                        <i class="fa-solid fa-sliders"></i> Squad Config
                    </a>
                </div>
            </div>

            <div class="member-grid">
                <?php
                $members = $teamMembers[$team['id']] ?? [];
                if (empty($members)): ?>
                    <div
                        style="grid-column: 1/-1; padding: 3rem; text-align: center; color: var(--text-muted); background: var(--bg-tertiary); border-radius: 12px; border: 1px dashed var(--border-color);">
                        No personnel assigned to this squadron yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($members as $mem):
                        $initials = strtoupper(substr($mem['full_name'] ?? $mem['username'], 0, 1));
                        ?>
                        <a href="/user_details.php?id=<?php echo $mem['id']; ?>" class="member-card" style="text-decoration: none;">
                            <div class="member-avatar">
                                <?php echo $initials; ?>
                                <div
                                    class="status-indicator status-<?php echo strtolower($mem['presence_status'] ?? 'offline'); ?>">
                                </div>
                            </div>
                            <div class="member-info">
                                <h4><?php echo htmlspecialchars($mem['full_name'] ?: $mem['username']); ?></h4>
                                <p><?php echo ucfirst($mem['role']); ?></p>
                            </div>
                            <div style="margin-left: auto;">
                                <i class="fa-solid fa-chevron-right" style="color: var(--border-color); font-size: 0.8rem;"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function switchTab(btn, contentId) {
        // Deactivate all
        document.querySelectorAll('.tab-trigger').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        // Activate current
        btn.classList.add('active');
        document.getElementById(contentId).classList.add('active');
    }
</script>

<?php
// End layout
endLayout();
?>