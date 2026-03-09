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

$teamId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($teamId) {
    $_SESSION['last_team_id'] = $teamId;
}

if (!$teamId) {
    header('Location: /manage_teams.php');
    exit;
}

// Fetch team details
$stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
$stmt->execute([$teamId]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: /manage_teams.php');
    exit;
}

// Handle Member Actions
$message = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_member') {
        $fullName = $_POST['full_name'];
        $email = $_POST['email'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $team['name'];

        try {
            $prefix = strtoupper(substr($team['name'], 0, 3));
            $stmt = $pdo->prepare("SELECT unique_id FROM users WHERE unique_id LIKE ? AND unique_id REGEXP ? ORDER BY unique_id DESC LIMIT 1");
            $pattern = "^" . $prefix . "[0-9]{3}$";
            $stmt->execute([$prefix . '%', $pattern]);
            $lastId = $stmt->fetchColumn();

            if ($lastId) {
                $numStr = substr($lastId, 3);
                $number = intval($numStr) + 1;
            } else {
                $number = 1;
            }
            $uniqueId = $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO users (unique_id, username, email, password, full_name, role, team_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$uniqueId, $username, $email, $password, $fullName, $role, $teamId]);
            $_SESSION['success_message'] = "Operative deployed successfully with ID: " . $uniqueId;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'reset_password') {
        $targetId = intval($_POST['user_id']);
        $newPasswordRaw = $_POST['new_password'];
        $newPassword = password_hash($newPasswordRaw, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND team_id = ?");
        if ($stmt->execute([$newPassword, $targetId, $teamId])) {
            $_SESSION['success_message'] = "Tactical encryption key updated successfully.";
        }
    } elseif ($_POST['action'] === 'toggle_status') {
        $targetId = intval($_POST['user_id']);
        $currentStatus = intval($_POST['current_status']);
        $newStatus = $currentStatus ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND team_id = ?");
        if ($stmt->execute([$newStatus, $targetId, $teamId])) {
            $_SESSION['success_message'] = $newStatus ? "Operative reactivated." : "Operative deactivated.";
        }
    } elseif ($_POST['action'] === 'delete_member') {
        $targetId = intval($_POST['user_id']);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND team_id = ?");
        if ($stmt->execute([$targetId, $teamId])) {
            $_SESSION['success_message'] = "Operative purged from system.";
        }
    }

    // Redirect to prevent form resubmission
    $redirectUrl = "team_members.php?id=" . $teamId;
    if (isset($_GET['p']))
        $redirectUrl .= "&p=" . $_GET['p'];
    if (isset($_GET['q']))
        $redirectUrl .= "&q=" . urlencode($_GET['q']);
    header("Location: " . $redirectUrl);
    exit;
}

// Search Filter Logic
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$whereClause = "WHERE team_id = ?";
$params = [$teamId];

if ($search !== '') {
    $whereClause .= " AND (full_name LIKE ? OR username LIKE ? OR unique_id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Pagination Logic
$limit = 5;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

// Fetch total count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users $whereClause");
$stmtCount->execute($params);
$totalMembers = $stmtCount->fetchColumn();
$totalPages = ceil($totalMembers / $limit);

// Fetch team members with limit (Ascending Order)
$stmt = $pdo->prepare("SELECT * FROM users $whereClause ORDER BY created_at ASC LIMIT ? OFFSET ?");
foreach ($params as $k => $v) {
    $stmt->bindValue($k + 1, $v);
}
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$members = $stmt->fetchAll();

// Dynamic Branding (Premium Light Gradients)
$gradientSchemes = [
    ['#34d399', '#10b981'], // Emerald
    ['#60a5fa', '#3b82f6'], // blue
    ['#a78bfa', '#8b5cf6'], // violet
    ['#fbbf24', '#f59e0b'], // amber
    ['#fb7185', '#f43f5e'], // rose
    ['#22d3ee', '#06b6d4'], // cyan
    ['#f472b6', '#ec4899'], // pink
    ['#818cf8', '#6366f1'], // indigo
    ['#fb923c', '#f97316']  // orange
];
$schemeIndex = abs(crc32($team['name'])) % count($gradientSchemes);
$teamColorPrimary = $gradientSchemes[$schemeIndex][0];
$teamColorSecondary = $gradientSchemes[$schemeIndex][1];


startLayout($team['name'] . ' Hub', $user);
?>

<style>
    :root {
        --team-color:
            <?php echo $teamColorPrimary; ?>
        ;
        --team-color-secondary:
            <?php echo $teamColorSecondary; ?>
        ;
        --team-gradient: linear-gradient(135deg,
                <?php echo $teamColorPrimary; ?>
                0%,
                <?php echo $teamColorSecondary; ?>
                100%);
        --team-color-light:
            <?php echo $teamColorPrimary; ?>
            10;
        --team-color-border:
            <?php echo $teamColorPrimary; ?>
            25;
        --team-color-shadow: 0 15px 35px
            <?php echo $teamColorPrimary; ?>
            40;
        --team-color-shadow-hover: 0 25px 50px
            <?php echo $teamColorPrimary; ?>
            60;
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.5);
    }
</style>
<link rel="stylesheet" href="/css/admin/team_members.css">


<div class="tactical-bg-blob"></div>

<div class="hub-header fade-in">
    <?php
    $toolsList = [
        'word' => ['icon' => 'fa-file-word', 'path' => '/tools/word.php'],
        'spreadsheet' => ['icon' => 'fa-file-excel', 'path' => '/tools/timesheet.php'],
        'calendar' => ['icon' => 'fa-calendar-day', 'path' => '/tools/calendar.php'],
        'chat' => ['icon' => 'fa-comments', 'path' => '/tools/chat.php'],
        'filemanager' => ['icon' => 'fa-folder-open', 'path' => '/tools/files.php'],
        'tasksheet' => ['icon' => 'fa-list-check', 'path' => '/tools/tasks.php'],
        'leadrequirement' => ['icon' => 'fa-id-card-clip', 'path' => '/tools/leads.php']
    ];
    ?>
    <div class="hub-header-left">
        <div class="hub-avatar">
            <?php echo strtoupper(substr($team['name'], 0, 1)); ?>
        </div>
        <div class="hub-title">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
                <span
                    style="font-size: 0.65rem; font-weight: 900; color: var(--team-color); background: var(--team-color-light); padding: 0.15rem 0.6rem; border-radius: 100px; letter-spacing: 0.1em;">TACTICAL
                    UNIT</span>
                <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; letter-spacing: 0.05em;">ID:
                    #<?php echo str_pad($team['id'], 3, '0', STR_PAD_LEFT); ?></span>
            </div>
            <h1 style="font-size: 1.8rem; letter-spacing: -0.04em;"><?php echo htmlspecialchars($team['name']); ?></h1>
            <p
                style="color: #64748b; font-size: 1rem; font-weight: 500; margin: 0.4rem 0 1rem 0; opacity: 0.8; max-width: 450px; line-height: 1.4;">
                <?php echo htmlspecialchars($team['description'] ?: 'Commanding the strategic frontiers of user engagement.'); ?>
            </p>
            <div class="header-tools" style="display: flex; gap: 0.65rem;">
                <?php
                foreach ($toolsList as $toolKey => $data):
                    if (isset($team['tool_' . $toolKey]) && $team['tool_' . $toolKey] == 1):
                        ?>
                        <div class="tool-badge-mini active <?php echo $toolKey; ?>"
                            style="width: 38px; height: 38px; border-radius: 11px; font-size: 0.95rem;"
                            title="<?php echo ucfirst($toolKey); ?>">
                            <i class="fa-solid <?php echo $data['icon']; ?>"></i>
                        </div>
                        <?php
                    endif;
                endforeach;
                ?>
            </div>
        </div>
    </div>
    <div class="hub-header-right">
        <div style="margin-bottom: 1.25rem;">
            <div class="status-badge"
                style="display: inline-flex; padding: 0.4rem 1rem; border-radius: 100px; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.1);">
                <div class="pulse" style="width: 7px; height: 7px;"></div>
                <span
                    style="font-size: 0.65rem; font-weight: 900; color: #10b981; letter-spacing: 0.1em; padding-left: 0.4rem;">SYSTEM
                    NOMINAL</span>
            </div>
        </div>
        <div
            style="background: white; padding: 1rem 1.5rem; border-radius: 20px; border: 1px solid rgba(0,0,0,0.03); box-shadow: 0 8px 15px rgba(0,0,0,0.02);">
            <div style="font-size: 2.25rem; font-weight: 950; color: #1e293b; line-height: 1; letter-spacing: -0.05em;">
                <?php echo $totalMembers; ?>
            </div>
            <div
                style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.15em; margin-top: 0.35rem;">
                OPERATIVES</div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<?php if ($message || $error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($message): ?>
                showToast('Mission Successful', '<?php echo $message; ?>', 'success');
            <?php endif; ?>
            <?php if ($error): ?>
                showToast('System Oversight', '<?php echo $error; ?>', 'error');
            <?php endif; ?>
        });
    </script>
<?php endif; ?>

<div class="bento-hub">
    <!-- Main Deployment Grid (Full Width) -->
    <div class="bento-card fade-in">
        <div class="card-header-lux">
            <div class="header-lux-container">
                <!-- Left Sector: Search -->
                <div class="lux-sector search-sector">
                    <form method="GET" class="filter-wrapper" action="team_members.php" id="searchForm">
                        <input type="hidden" name="id" value="<?php echo $teamId; ?>">
                        <i class="fa-solid fa-magnifying-glass filter-icon"></i>
                        <input type="text" name="q" class="filter-input" placeholder="Search operatives..."
                            value="<?php echo htmlspecialchars($search); ?>" oninput="debounceSearch()">
                        <?php if ($search): ?>
                            <a href="?id=<?php echo $teamId; ?>" class="clear-search" title="Clear Search">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Center Sector: Pagination -->
                <div class="lux-sector pagination-sector">
                    <?php if ($totalPages > 1): ?>
                        <div class="hub-pagination">
                            <?php
                            $paginationParams = "&id=" . $teamId . ($search ? "&q=" . urlencode($search) : "");
                            ?>
                            <a href="?p=<?php echo $page - 1; ?><?php echo $paginationParams; ?>"
                                class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>"><i
                                    class="fa-solid fa-chevron-left"></i></a>
                            <div style="padding: 0 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                                <span
                                    style="font-size: 0.75rem; font-weight: 950; color: var(--team-color);"><?php echo $page; ?></span>
                                <span style="font-size: 0.7rem; font-weight: 800; color: #cbd5e1;">/</span>
                                <span
                                    style="font-size: 0.75rem; font-weight: 800; color: #94a3b8;"><?php echo $totalPages; ?></span>
                            </div>
                            <a href="?p=<?php echo $page + 1; ?><?php echo $paginationParams; ?>"
                                class="page-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>"><i
                                    class="fa-solid fa-chevron-right"></i></a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Sector: Deployment -->
                <div class="lux-sector deployment-sector">
                    <button class="btn-add-op" onclick="toggleModal('recruitmentModal', true)">
                        <i class="fa-solid fa-user-plus"></i>
                        DEPLOY NEW OPERATIVE
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="tactical-table">
                <thead>
                    <tr>
                        <th>Operative Identity</th>
                        <th>Tactical Handle</th>
                        <th>Relay Channel</th>
                        <th>Tactical ID</th>
                        <th style="text-align: center;">Deployment Status</th>
                        <th style="text-align: right;">Authorization</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 4rem 2rem;">
                                <div style="opacity: 0.1; font-size: 4rem; margin-bottom: 1rem;"><i
                                        class="fa-solid fa-radar"></i></div>
                                <h3 style="font-size: 1.5rem; font-weight: 900; color: #94a3b8; margin: 0;">Scanning... No
                                    Operatives Found</h3>
                                <p style="color: #cbd5e1; font-weight: 600; margin-top: 0.5rem;">Deploy your first unit to
                                    begin
                                    operations.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td>
                                    <div class="op-profile">
                                        <div class="op-avatar"
                                            style="background: <?php echo $m['is_active'] ? 'white' : '#f1f5f9'; ?>; opacity: <?php echo $m['is_active'] ? '1' : '0.5'; ?>;">
                                            <?php echo strtoupper(substr($m['full_name'], 0, 1)); ?>
                                        </div>
                                        <div style="opacity: <?php echo $m['is_active'] ? '1' : '0.6'; ?>;">
                                            <div style="font-weight: 900; color: #1e293b; font-size: 1.1rem;">
                                                <?php echo htmlspecialchars($m['full_name']); ?>
                                            </div>
                                            <div
                                                style="font-size: 0.75rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">
                                                <?php echo htmlspecialchars($m['role']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span
                                        style="font-weight: 800; color: #475569;">@<?php echo htmlspecialchars($m['username']); ?></span>
                                </td>
                                <td><span
                                        style="font-weight: 700; color: #64748b; font-size: 0.9rem;"><?php echo htmlspecialchars($m['email']); ?></span>
                                </td>
                                <td><span class="op-id"><?php echo htmlspecialchars($m['unique_id']); ?></span></td>
                                <td style="text-align: center;">
                                    <div
                                        style="display: inline-flex; align-items: center; gap: 0.5rem; background: <?php echo $m['is_active'] ? 'rgba(16, 185, 129, 0.08)' : 'rgba(244, 63, 94, 0.08)'; ?>; padding: 0.4rem 0.8rem; border-radius: 12px; border: 1.5px solid <?php echo $m['is_active'] ? 'rgba(16, 185, 129, 0.15)' : 'rgba(244, 63, 94, 0.15)'; ?>;">
                                        <div
                                            style="width: 7px; height: 7px; border-radius: 50%; background: <?php echo $m['is_active'] ? '#10b981' : '#f43f5e'; ?>; box-shadow: 0 0 10px <?php echo $m['is_active'] ? 'rgba(16, 185, 129, 0.4)' : 'rgba(244, 63, 94, 0.4)'; ?>;">
                                        </div>
                                        <span
                                            style="font-size: 0.7rem; font-weight: 900; color: <?php echo $m['is_active'] ? '#059669' : '#e11d48'; ?>; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo $m['is_active'] ? 'Active' : 'Offline'; ?></span>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <button type="button" class="action-btn reset" title="Reset Encryption Key"
                                            onclick="openResetModal('<?php echo $m['id']; ?>', '<?php echo htmlspecialchars($m['full_name']); ?>')">
                                            <i class="fa-solid fa-key"></i>
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $m['is_active']; ?>">
                                            <button type="submit" class="action-btn toggle"
                                                title="<?php echo $m['is_active'] ? 'Deactivate' : 'Activate'; ?>"><i
                                                    class="fa-solid <?php echo $m['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i></button>
                                        </form>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('Purge this operative from the tactical system?')">
                                            <input type="hidden" name="action" value="delete_member">
                                            <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                            <button type="submit" class="action-btn delete" title="Purge Operative"><i
                                                    class="fa-solid fa-trash-can"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Modal Recruitment Station -->
<div class="glass-modal" id="recruitmentModal">
    <div class="modal-box">
        <button onclick="toggleModal('recruitmentModal', false)"
            style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; color: #94a3b8; font-size: 1.25rem; cursor: pointer; transition: all 0.2s;"
            onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#94a3b8'">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div style="margin-bottom: 2.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <h2 style="font-size: 2.25rem; font-weight: 950; color: #0f172a; margin: 0; letter-spacing: -0.04em;">
                    Recruitment</h2>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="add_member">

            <div class="recruitment-form-grid">
                <div>
                    <label class="label-lux">Identity Information</label>
                    <input type="text" name="full_name" class="input-premium" placeholder="Full Legal Name" required>
                </div>
                <div>
                    <label class="label-lux">Tactical Handle</label>
                    <input type="text" name="username" class="input-premium" placeholder="Username" required>
                </div>
                <div>
                    <label class="label-lux">Relay Channel</label>
                    <input type="email" name="email" class="input-premium" placeholder="Email Address" required>
                </div>
                <div>
                    <label class="label-lux">Encryption Key</label>
                    <input type="password" name="password" class="input-premium" placeholder="••••••••••••" required>
                </div>
            </div>

            <div class="recruitment-submit-grid">
                <div class="recruitment-info-box">
                    <div
                        style="width: 40px; height: 40px; border-radius: 12px; background: var(--team-color-light); color: var(--team-color); display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <p style="font-size: 0.75rem; color: #64748b; font-weight: 600; margin: 0; line-height: 1.4;">
                        New operative credentials and system access will be initialized upon deployment.</p>
                </div>

                <button type="submit" class="btn-tactical" style="padding: 1rem;">
                    <i class="fa-solid fa-shuttle-space"></i>
                    DEPLOY OPERATIVE
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="glass-modal">
    <div class="modal-box" style="max-width: 500px;">
        <div class="card-header-lux" style="padding: 0 0 1.5rem 0; margin-bottom: 2rem;">
            <div>
                <h2 style="font-size: 1.5rem;">Update Encryption Key</h2>
                <p style="color: #94a3b8; font-size: 0.8rem; font-weight: 600; margin-top: 0.25rem;">Re-initializing
                    access for <span id="resetOpName" style="color: var(--team-color);"></span></p>
            </div>
            <button onclick="toggleModal('resetModal', false)"
                style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.25rem;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">

            <div style="margin-bottom: 2rem;">
                <label class="label-lux">New Master Key</label>
                <input type="password" name="new_password" class="input-premium" placeholder="••••••••••••" required>
            </div>

            <button type="submit" class="btn-tactical">
                <i class="fa-solid fa-shield-halved"></i>
                ENGAGE NEW PROTOCOL
            </button>
        </form>
    </div>
</div>

<script>
    function toggleModal(id, show) {
        const modal = document.getElementById(id);
        if (show) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        } else {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    // Close modal on outside click
    window.onclick = function (event) {
        if (event.target.classList.contains('glass-modal')) {
            event.target.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    function openResetModal(userId, fullName) {
        document.getElementById('resetUserId').value = userId;
        document.getElementById('resetOpName').textContent = fullName;
        toggleModal('resetModal', true);
    }

    function showToast(title, message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icon = type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation';

        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fa-solid ${icon}"></i>
            </div>
            <div class="toast-content">
                <h4>${title}</h4>
                <p>${message}</p>
            </div>
        `;

        container.appendChild(toast);

        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 100);

        // Auto-remove
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 600);
        }, 5000);
    }

    let searchTimer;
    function debounceSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            document.getElementById('searchForm').submit();
        }, 500);
    }
</script>

<?php endLayout(); ?>