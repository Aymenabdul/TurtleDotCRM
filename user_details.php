<?php
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/src/layouts/base_layout.php';
require_once __DIR__ . '/config.php';

// Require authentication and ensure user is an admin
$adminUser = AuthMiddleware::requireAuth();
$is_admin = isset($adminUser['role']) && strtolower(trim($adminUser['role'])) === 'admin';

if (!$is_admin) {
    header('Location: /index.php');
    exit;
}

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header('Location: /admin_dashboard.php');
    exit;
}

// Fetch user info
try {
    $stmt = $pdo->prepare("SELECT u.*, t.name as team_name 
                            FROM users u 
                            LEFT JOIN teams t ON u.team_id = t.id 
                            WHERE u.id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if (!$targetUser) {
    header('Location: /admin_dashboard.php');
    exit;
}

// Fetch Assigned Projects
$projects = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE assigned_to = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* column may be missing on older installs */
}

// Fetch Assigned Tasks
$tasks = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE assigned_to = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* column may be missing on older installs */
}

// Fetch Assigned Documents (word editor docs)
$uniqueWordDocs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM word_documents WHERE assigned_to LIKE ? ORDER BY created_at DESC");
    $stmt->execute(['%' . $userId . '%']);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $wd) {
        $uniqueWordDocs[$wd['id']] = $wd;
    }
} catch (PDOException $e) { /* assigned_to column may not exist yet on live server */
}

// Fetch Assigned Spreadsheets
$uniqueSheets = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM spreadsheets WHERE assigned_to LIKE ? ORDER BY created_at DESC");
    $stmt->execute(['%' . $userId . '%']);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $uniqueSheets[$s['id']] = $s;
    }
} catch (PDOException $e) { /* column may be missing on older installs */
}


startLayout('Personnel Details - ' . ($targetUser['full_name'] ?: $targetUser['username']), $adminUser);
?>

<link rel="stylesheet" href="/css/admin/user_details.css">

<div class="fade-in">
    <div class="mb-4">
        <a href="/admin_dashboard.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="profile-header">
        <div class="header-pattern"></div>
        <div class="header-vector vector-1"></div>
        <div class="header-vector vector-2"></div>

        <!-- Decorative Vector Blobs -->
        <svg class="decorative-svg-left" width="240" height="240" viewBox="0 0 200 200"
            xmlns="http://www.w3.org/2000/svg">
            <path fill="var(--primary)"
                d="M44.7,-76.4C58.1,-69.2,69.2,-58.1,77.3,-44.7C85.4,-31.3,90.5,-15.7,91.8,1.3C93.1,18.3,90.6,36.6,82.5,52.2C74.4,67.8,60.7,80.7,44.9,87.1C29.1,93.5,11.2,93.4,-5.2,93.9C-21.6,94.5,-36.5,95.7,-49.2,89.3C-61.9,82.9,-72.4,68.9,-79.9,53.3C-87.4,37.7,-91.9,20.5,-91.1,3.4C-90.3,-13.7,-84.2,-30.7,-74.6,-45C-65,-59.3,-51.9,-70.9,-37.6,-77.6C-23.3,-84.3,-7.8,-86.1,7.2,-86.3C22.2,-86.5,31.3,-83.6,44.7,-76.4Z"
                transform="translate(100 100)" />
        </svg>

        <svg class="decorative-svg" width="300" height="300" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
            <path fill="var(--primary)"
                d="M44.7,-76.4C58.1,-69.2,69.2,-58.1,77.3,-44.7C85.4,-31.3,90.5,-15.7,91.8,1.3C93.1,18.3,90.6,36.6,82.5,52.2C74.4,67.8,60.7,80.7,44.9,87.1C29.1,93.5,11.2,93.4,-5.2,93.9C-21.6,94.5,-36.5,95.7,-49.2,89.3C-61.9,82.9,-72.4,68.9,-79.9,53.3C-87.4,37.7,-91.9,20.5,-91.1,3.4C-90.3,-13.7,-84.2,-30.7,-74.6,-45C-65,-59.3,-51.9,-70.9,-37.6,-77.6C-23.3,-84.3,-7.8,-86.1,7.2,-86.3C22.2,-86.5,31.3,-83.6,44.7,-76.4Z"
                transform="translate(100 100)" />
        </svg>

        <div class="profile-avatar-large">
            <?php echo strtoupper(substr($targetUser['full_name'] ?: $targetUser['username'], 0, 1)); ?>
        </div>
        <div class="profile-info">
            <h1 style="line-height: 1;">
                <?php echo htmlspecialchars($targetUser['full_name'] ?: $targetUser['username']); ?>
            </h1>
            <?php if ($targetUser['full_name']): ?>
                <div style="font-size: 0.9rem; font-weight: 600; color: #94a3b8; margin-top: 0.25rem;">
                    @<?php echo htmlspecialchars($targetUser['username']); ?></div>
            <?php endif; ?>
            <div style="display: flex; gap: 0.4rem; align-items: center; margin-top: 0.5rem;">
                <div
                    style="display: flex; align-items: center; gap: 0.35rem; background: rgba(0,0,0,0.03); padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid rgba(0,0,0,0.05);">
                    <div class="status-dot status-<?php echo strtolower($targetUser['presence_status'] ?? 'offline'); ?>"
                        style="width: 7px; height: 7px;">
                    </div>
                    <span
                        style="font-size: 0.625rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php echo ucfirst($targetUser['presence_status'] ?? 'offline'); ?>
                    </span>
                </div>

                <?php if ($targetUser['team_name'] && strtolower($targetUser['team_name']) !== strtolower($targetUser['role'])): ?>
                    <div class="squad-tag" style="margin-top: 0;">
                        <i class="fa-solid fa-people-group"></i>
                        <?php echo htmlspecialchars($targetUser['team_name']); ?>
                    </div>
                <?php endif; ?>

                <div class="squad-tag"
                    style="margin-top: 0; background: rgba(16, 185, 129, 0.1); color: var(--primary);">
                    <i class="fa-solid fa-shield-halved"></i>
                    <?php echo ucfirst($targetUser['role']); ?>
                </div>

                <div class="squad-tag"
                    style="margin-top: 0; background: <?php echo $targetUser['is_active'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; color: <?php echo $targetUser['is_active'] ? 'var(--primary)' : '#ef4444'; ?>;">
                    <i class="fa-solid <?php echo $targetUser['is_active'] ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                    Account <?php echo $targetUser['is_active'] ? 'Active' : 'Deactivated'; ?>
                </div>
            </div>
        </div>
        <div class="profile-activity" style="margin-left: auto; text-align: right;">
            <div
                style="font-size: 0.75rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em;">
                Last Activity</div>
            <div style="font-weight: 700; color: var(--text-main);">
                <?php echo $targetUser['last_login'] ? date('M j, Y H:i', strtotime($targetUser['last_login'])) : 'Never'; ?>
            </div>
        </div>
    </div>

    <div class="details-grid">
        <div class="main-details">
            <!-- Active Projects -->
            <div class="assignment-section">
                <div class="assignment-section-title">
                    <i class="fa-solid fa-diagram-project text-primary"></i>
                    Assigned Projects
                </div>
                <?php if (empty($projects)): ?>
                    <div class="empty-assignments">No projects have been explicitly assigned to this operative.</div>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                        <div class="assignment-card">
                            <div class="assign-meta">
                                <h4>
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </h4>
                                <p>
                                    <?php echo htmlspecialchars($project['description'] ?: 'No description provided.'); ?>
                                </p>
                            </div>
                            <span class="status-pill pill-<?php echo $project['status']; ?>">
                                <?php echo $project['status']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Assigned Tasks -->
            <div class="assignment-section">
                <div class="assignment-section-title">
                    <i class="fa-solid fa-list-check text-info"></i>
                    Operational Tasks
                </div>
                <?php if (empty($tasks)): ?>
                    <div class="empty-assignments">No active tasks found for this user.</div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="assignment-card">
                            <div class="assign-meta">
                                <h4>
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </h4>
                                <p>Due:
                                    <?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No date'; ?>
                                </p>
                            </div>
                            <span class="status-pill pill-todo">
                                <?php echo str_replace('_', ' ', $task['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="side-details">
            <!-- Documents Access -->
            <div class="assignment-section">
                <div class="assignment-section-title">
                    <i class="fa-solid fa-file-invoice text-warning"></i>
                    Document Assets
                </div>
                <div class="card" style="padding: 1.5rem;">
                    <h5
                        style="font-size: 0.85rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 1rem;">
                        Shared Files</h5>

                    <?php if (empty($uniqueWordDocs) && empty($uniqueSheets)): ?>
                        <p style="font-size: 0.9rem; color: var(--text-muted); text-align: center; margin: 2rem 0;">No
                            direct document assignments.</p>
                    <?php else: ?>
                        <?php foreach ($uniqueWordDocs as $doc): ?>
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                                <i class="fa-solid fa-file-word text-primary" style="font-size: 1.25rem;"></i>
                                <div style="font-size: 0.9rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($doc['title']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($uniqueSheets as $sheet): ?>
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                                <i class="fa-solid fa-file-excel text-success" style="font-size: 1.25rem;"></i>
                                <div style="font-size: 0.9rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($sheet['title']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endLayout(); ?>