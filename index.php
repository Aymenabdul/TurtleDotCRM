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
    <div class="card mb-4" style="background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);">
        <div class="card-header" style="border-bottom: none; margin-bottom: 0;">
            <h1 class="card-title"
                style="font-size: 2.5rem; font-weight: 800; color: #1e293b; letter-spacing: -0.04em;">
                Welcome back, <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>!
            </h1>
            <p class="card-subtitle" style="font-size: 1.1rem; color: #64748b;">
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
                    <a href="<?php echo $tool['url']; ?>?team_id=<?php echo $team['id']; ?>" class="card"
                        style="text-decoration: none; transition: all 0.3s ease; border: 1px solid #e2e8f0;">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div
                                style="width: 50px; height: 50px; background: #ecfdf5; color: #10b981; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                <i class="fa-solid <?php echo $tool['icon']; ?>"></i>
                            </div>
                            <div>
                                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">
                                    <?php echo $tool['name']; ?>
                                </h3>
                                <p style="font-size: 0.9rem; color: #64748b; line-height: 1.5;"><?php echo $tool['desc']; ?></p>
                            </div>
                            <div
                                style="margin-top: auto; display: flex; align-items: center; gap: 0.5rem; color: #10b981; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                Launch Tool <i class="fa-solid fa-arrow-right"></i>
                            </div>
                        </div>
                    </a>
                <?php endif;
            endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem; border: 2px dashed #e2e8f0;">
            <div style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <h2 style="color: #475569; font-weight: 700;">No Unit Assigned</h2>
            <p style="color: #94a3b8; max-width: 400px; margin: 0 auto;">You haven't been assigned to a tactical unit yet.
                Please contact your system administrator for access to tools.</p>
        </div>
    <?php endif; ?>
</div>

<?php endLayout(); ?>