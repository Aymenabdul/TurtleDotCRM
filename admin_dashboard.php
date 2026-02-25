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
    } catch (PDOException $e) {
        // Table might not exist yet
    }

    // Projects, similar handling
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM projects");
        $stats['projects'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Table might not exist yet
    }
} catch (PDOException $e) {
    // Database connection error handling
}

// Start Layout
startLayout('Admin Dashboard', $user);
?>

<div class="card mb-4">
    <div class="card-header">
        <h2 class="card-title">Welcome Admin,
            <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>!
        </h2>
        <p class="card-subtitle">Overview of your TurtleDot application.</p>
    </div>
</div>

<div class="grid grid-4 mb-4">
    <!-- Quick Stats -->
    <div class="card">
        <div class="flex-between">
            <div>
                <div class="text-muted" style="margin-bottom: 0.25rem;">Total Users</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--text-main);">
                    <?php echo $stats['users']; ?>
                </div>
            </div>
            <div
                style="background: rgba(16, 185, 129, 0.1); width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-users text-primary" style="font-size: 1.25rem;"></i>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="flex-between">
            <div>
                <div class="text-muted" style="margin-bottom: 0.25rem;">Total Clients</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--text-main);">
                    <?php echo $stats['clients']; ?>
                </div>
            </div>
            <div
                style="background: rgba(59, 130, 246, 0.1); width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-briefcase text-info" style="font-size: 1.25rem;"></i>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="flex-between">
            <div>
                <div class="text-muted" style="margin-bottom: 0.25rem;">Active Teams</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--text-main);">
                    <?php echo $stats['teams']; ?>
                </div>
            </div>
            <div
                style="background: rgba(245, 158, 11, 0.1); width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-user-group text-warning" style="font-size: 1.25rem;"></i>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="flex-between">
            <div>
                <div class="text-muted" style="margin-bottom: 0.25rem;">Projects</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--text-main);">
                    <?php echo $stats['projects']; ?>
                </div>
            </div>
            <div
                style="background: rgba(239, 68, 68, 0.1); width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-folder-open text-error" style="font-size: 1.25rem;"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <!-- Admin Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
            <p class="card-subtitle">Manage users and system resources.</p>
        </div>
        <div class="grid grid-2" style="gap: var(--spacing-md)">
            <a href="/manage_teams.php" class="btn btn-primary" style="justify-content: center;">
                <i class="fa-solid fa-users-gear"></i> Manage Teams
            </a>
            <a href="/manage_teams.php" class="btn btn-secondary" style="justify-content: center;">
                <i class="fa-solid fa-user-plus"></i> Team Hub
            </a>
            <a href="#" class="btn btn-secondary" style="justify-content: center;"
                onclick="showToast('Feature coming soon', 'info')">
                <i class="fa-solid fa-gear"></i> System Settings
            </a>
            <a href="#" class="btn btn-secondary" style="justify-content: center;"
                onclick="showToast('Feature coming soon', 'info')">
                <i class="fa-solid fa-chart-line"></i> View Reports
            </a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">App Info</h3>
        </div>
        <div class="p-4 bg-tertiary rounded">
            <div style="margin-bottom: var(--spacing-md);">
                <span class="text-muted" style="display: inline-block; width: 120px;">Platform:</span>
                <span class="font-medium text-main">TurtleDot v2.0</span>
            </div>
            <div style="margin-bottom: var(--spacing-md);">
                <span class="text-muted" style="display: inline-block; width: 120px;">Role:</span>
                <span class="badge badge-error">Administrator</span>
            </div>
            <div style="margin-bottom: var(--spacing-md);">
                <span class="text-muted" style="display: inline-block; width: 120px;">Last Login:</span>
                <span class="font-medium text-main">
                    <?php echo date('Y-m-d H:i:A'); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
// End layout
endLayout();
?>