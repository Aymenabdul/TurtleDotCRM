<?php
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/src/layouts/base_layout.php';
require_once __DIR__ . '/config.php';

// Require authentication
$user = AuthMiddleware::requireAuth();
$is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin';

if ($is_admin) {
    header('Location: /admin_dashboard.php');
    exit;
}

// Set active page
$GLOBALS['currentPage'] = 'dashboard';

startLayout('Dashboard', $user);
?>
<link rel="stylesheet" href="/css/user/dashboard.css">
<?php
// Fetch team details for regular users
$team = null;
if ($user['team_id']) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->execute([$user['team_id']]);
        $team = $stmt->fetch();
    } catch (PDOException $e) {
    }
}
?>

<div class="fade-in">
    <div class="card mb-4 user-welcome-card">
        <div class="card-header">
            <h1 class="card-title">
                Welcome back, <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>!
            </h1>
            <p class="card-subtitle">
                <?php echo $team ? "You are operating as part of <strong>" . htmlspecialchars($team['name']) . "</strong>." : "Your account is active. Contact your admin to be assigned to a tactical unit."; ?>
            </p>
        </div>
    </div>

    <?php if ($team): ?>
        <div class="grid grid-3">
            <?php
            $tools = [
                'tool_word' => ['name' => 'Word Engine', 'icon' => 'fa-file-word', 'url' => '/tools/word.php', 'desc' => 'Manage and collaborate on team documents.'],
                'tool_spreadsheet' => ['name' => 'Grid Processor', 'icon' => 'fa-file-excel', 'url' => '/tools/timesheet.php', 'desc' => 'Analyze data with powerful spreadsheets.'],
                'tool_calendar' => ['name' => 'Timeline Scheduler', 'icon' => 'fa-calendar-day', 'url' => '/tools/calendar.php', 'desc' => 'Track deadlines and team schedules.'],
                'tool_chat' => ['name' => 'Pulse Chat', 'icon' => 'fa-comments', 'url' => '/tools/chat.php', 'desc' => 'Instant communication with your team.'],
                'tool_filemanager' => ['name' => 'Archive Vault', 'icon' => 'fa-folder-open', 'url' => '/tools/files.php', 'desc' => 'Secure cloud storage for team assets.'],
                'tool_tasksheet' => ['name' => 'Task Logic', 'icon' => 'fa-list-check', 'url' => '/tools/tasks.php', 'desc' => 'Manage tasks and track project progress.'],
                'tool_leadrequirement' => ['name' => 'Lead Intake', 'icon' => 'fa-id-card-clip', 'url' => '/tools/leads.php', 'desc' => 'Manage incoming leads and requirements.']
            ];

            foreach ($tools as $key => $tool):
                if (isset($team[$key]) && $team[$key] == 1): ?>
                    <a href="<?php echo $tool['url']; ?>?team_id=<?php echo $team['id']; ?>" class="card tool-card">
                        <div class="tool-card-content">
                            <div class="tool-icon-wrapper">
                                <i class="fa-solid <?php echo $tool['icon']; ?>"></i>
                            </div>
                            <div>
                                <h3 class="tool-title">
                                    <?php echo $tool['name']; ?>
                                </h3>
                                <p class="tool-desc"><?php echo $tool['desc']; ?></p>
                            </div>
                            <div class="tool-launch-btn">
                                Launch Tool <i class="fa-solid fa-arrow-right"></i>
                            </div>
                        </div>
                    </a>
                <?php endif;
            endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card no-unit-assigned">
            <div>
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <h2>No Unit Assigned</h2>
            <p>You haven't been assigned to a tactical unit yet.
                Please contact your system administrator for access to tools.</p>
        </div>
    <?php endif; ?>
</div>

<?php endLayout(); ?>