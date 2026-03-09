<?php
session_start();
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

// Set active page
$GLOBALS['currentPage'] = 'teams';

// Handle Team Actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'create') {
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $status = $_POST['status'] ?? 'active';

                $tool_word = isset($_POST['tool_word']) ? 1 : 0;
                $tool_spreadsheet = isset($_POST['tool_spreadsheet']) ? 1 : 0;
                $tool_calendar = isset($_POST['tool_calendar']) ? 1 : 0;
                $tool_chat = isset($_POST['tool_chat']) ? 1 : 0;
                $tool_filemanager = isset($_POST['tool_filemanager']) ? 1 : 0;
                $tool_tasksheet = isset($_POST['tool_tasksheet']) ? 1 : 0;
                $tool_leadrequirement = isset($_POST['tool_leadrequirement']) ? 1 : 0;

                $tools = [];
                if ($tool_word)
                    $tools[] = 'word';
                if ($tool_spreadsheet)
                    $tools[] = 'spreadsheet';
                if ($tool_calendar)
                    $tools[] = 'calendar';
                if ($tool_chat)
                    $tools[] = 'chat';
                if ($tool_filemanager)
                    $tools[] = 'files';
                if ($tool_tasksheet)
                    $tools[] = 'tasks';
                if ($tool_leadrequirement)
                    $tools[] = 'leads';
                $tools_json = json_encode($tools);

                $stmt = $pdo->prepare("INSERT INTO teams (name, description, status, tool_word, tool_spreadsheet, tool_calendar, tool_chat, tool_filemanager, tool_tasksheet, tool_leadrequirement, tools) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name,
                    $description,
                    $status,
                    $tool_word,
                    $tool_spreadsheet,
                    $tool_calendar,
                    $tool_chat,
                    $tool_filemanager,
                    $tool_tasksheet,
                    $tool_leadrequirement,
                    $tools_json
                ]);
                $_SESSION['flash_msg'] = "New squadron established successfully!";
            } elseif ($_POST['action'] === 'update') {
                $id = $_POST['id'];
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $status = $_POST['status'] ?? 'active';

                $tool_word = isset($_POST['tool_word']) ? 1 : 0;
                $tool_spreadsheet = isset($_POST['tool_spreadsheet']) ? 1 : 0;
                $tool_calendar = isset($_POST['tool_calendar']) ? 1 : 0;
                $tool_chat = isset($_POST['tool_chat']) ? 1 : 0;
                $tool_filemanager = isset($_POST['tool_filemanager']) ? 1 : 0;
                $tool_tasksheet = isset($_POST['tool_tasksheet']) ? 1 : 0;
                $tool_leadrequirement = isset($_POST['tool_leadrequirement']) ? 1 : 0;

                $tools = [];
                if ($tool_word)
                    $tools[] = 'word';
                if ($tool_spreadsheet)
                    $tools[] = 'spreadsheet';
                if ($tool_calendar)
                    $tools[] = 'calendar';
                if ($tool_chat)
                    $tools[] = 'chat';
                if ($tool_filemanager)
                    $tools[] = 'files';
                if ($tool_tasksheet)
                    $tools[] = 'tasks';
                if ($tool_leadrequirement)
                    $tools[] = 'leads';
                $tools_json = json_encode($tools);

                $stmt = $pdo->prepare("UPDATE teams SET name = ?, description = ?, status = ?, tool_word = ?, tool_spreadsheet = ?, tool_calendar = ?, tool_chat = ?, tool_filemanager = ?, tool_tasksheet = ?, tool_leadrequirement = ?, tools = ? WHERE id = ?");
                $stmt->execute([
                    $name,
                    $description,
                    $status,
                    $tool_word,
                    $tool_spreadsheet,
                    $tool_calendar,
                    $tool_chat,
                    $tool_filemanager,
                    $tool_tasksheet,
                    $tool_leadrequirement,
                    $tools_json,
                    $id
                ]);
                $_SESSION['flash_msg'] = "Configuration synced for the team!";
            } elseif ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                try {
                    $pdo->beginTransaction();

                    // First delete all users in this team
                    $stmtUsers = $pdo->prepare("DELETE FROM users WHERE team_id = ?");
                    $stmtUsers->execute([$id]);

                    // Then delete the team itself
                    $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
                    $stmt->execute([$id]);

                    $pdo->commit();
                    $_SESSION['flash_msg'] = "Team and all its members removed from system.";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } elseif ($_POST['action'] === 'toggle_status') {
                $id = $_POST['id'];
                $status = $_POST['status'];
                $newStatus = ($status === 'active') ? 'inactive' : 'active';
                $stmt = $pdo->prepare("UPDATE teams SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $id]);
                $_SESSION['flash_msg'] = "Team status updated to " . ucfirst($newStatus) . ".";
            }

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;

        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "System error: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Retrieve flash messages
$message = $_SESSION['flash_msg'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_error']);

// Fetch stats for the header
$activeTeamsCount = 0;
$inactiveTeamsCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'active'");
    $activeTeamsCount = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM teams WHERE status = 'inactive'");
    $inactiveTeamsCount = $stmt->fetchColumn();
} catch (PDOException $e) {
}

// Fetch all teams
$teams = [];
try {
    $stmt = $pdo->query("SELECT * FROM teams ORDER BY created_at DESC");
    $teams = $stmt->fetchAll();
} catch (PDOException $e) {
}

startLayout('Team Command Center', $user);
?>

<link rel="stylesheet" href="/css/admin/manage_teams.css">

<div class="dashboard-header">
    <div class="header-info">
        <h1>Command Center</h1>
        <p>Operational overview of specialized units.</p>
    </div>
    <div class="header-stats">
        <div class="stat-item">
            <span class="stat-value"><?php echo $activeTeamsCount; ?></span>
            <span class="stat-label">Active Units</span>
        </div>
        <div class="stat-item" style="border-right: 2px solid #f1f5f9; padding-right: 2rem;">
            <span class="stat-value" style="color: #94a3b8;"><?php echo $inactiveTeamsCount; ?></span>
            <span class="stat-label">Inactive Team</span>
        </div>
        <div>
            <button class="btn-lux" onclick="openCreateModal()">
                <i class="fa-solid fa-plus"></i> New Squad
            </button>
        </div>
    </div>
</div>



<div class="bento-grid">
    <?php if (empty($teams)): ?>
        <div
            style="grid-column: 1 / -1; background: #fff; padding: 6rem; border-radius: 40px; text-align: center; border: 2px dashed #f1f5f9;">
            <div style="font-size: 4rem; color: #f1f5f9; margin-bottom: 2rem;"><i class="fa-solid fa-box-open"></i></div>
            <h2 style="font-weight: 800; color: #64748b;">No Squadrons Defined</h2>
            <p style="color: #94a3b8; font-size: 1.1rem; margin-bottom: 2.5rem;">The command system is ready. Establish your
                first specialized team.</p>
            <button class="btn-lux" onclick="openCreateModal()">Initialize System</button>
        </div>
    <?php endif; ?>

    <?php foreach ($teams as $team):
        $isHighlighted = isset($_GET['highlight']) && $_GET['highlight'] == $team['id'];
        ?>
        <div class="bento-card <?php echo $isHighlighted ? 'highlighted-card' : ''; ?>"
            id="team-<?php echo $team['id']; ?>">
            <div class="card-top">
                <?php
                $colors = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#f43f5e', '#6366f1'];
                $colorIndex = abs(crc32($team['name'])) % count($colors);
                $teamColor = $colors[$colorIndex];
                ?>
                <div class="team-avatar"
                    style="background: linear-gradient(135deg, <?php echo $teamColor; ?> 0%, <?php echo $teamColor; ?>dd 100%); box-shadow: 0 8px 16px <?php echo $teamColor; ?>40;">
                    <?php echo strtoupper(substr($team['name'], 0, 1)); ?>
                </div>
                <div class="card-status-pill" style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                        <?php if ($team['status'] === 'active'): ?>
                            <span
                                style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);"></span>
                            <span
                                style="font-size: 0.7rem; font-weight: 800; color: #10b981; text-transform: uppercase;">Ready</span>
                        <?php else: ?>
                            <span
                                style="width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; box-shadow: 0 0 10px rgba(148, 163, 184, 0.4);"></span>
                            <span
                                style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <button class="status-toggle-btn"
                        onclick="confirmAction('toggle', <?php echo $team['id']; ?>, '<?php echo $team['status']; ?>', '<?php echo htmlspecialchars($team['name']); ?>')"
                        style="background: <?php echo $team['status'] === 'active' ? '#fff1f2' : '#f0fdf4'; ?>; 
                               color: <?php echo $team['status'] === 'active' ? '#ef4444' : '#10b981'; ?>;">
                        <i class="fa-solid <?php echo $team['status'] === 'active' ? 'fa-power-off' : 'fa-bolt'; ?>"></i>
                        <?php echo $team['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                    </button>
                </div>
            </div>

            <div class="team-details">
                <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                <p><?php echo htmlspecialchars($team['description'] ?: 'Equipped with custom neural toolsets for maximum efficiency.'); ?>
                </p>
            </div>

            <div>
                <span class="stat-label" style="display: block; margin-bottom: 0.75rem;">Assigned Toolset</span>
                <div class="tool-swatch-container">
                    <?php
                    $tools = [
                        'word' => ['i' => 'fa-file-word', 't' => 'Document Engine', 'p' => '/tools/word.php'],
                        'spreadsheet' => ['i' => 'fa-file-excel', 't' => 'Grid Processor', 'p' => '/tools/timesheet.php'],
                        'calendar' => ['i' => 'fa-calendar-day', 't' => 'Timeline Scheduler', 'p' => '/tools/calendar.php'],
                        'chat' => ['i' => 'fa-comments', 't' => 'Pulse Chat', 'p' => '/tools/chat.php'],
                        'filemanager' => ['i' => 'fa-folder-open', 't' => 'Archive Vault', 'p' => '/tools/files.php'],
                        'tasksheet' => ['i' => 'fa-list-check', 't' => 'Task Logic', 'p' => '/tools/tasks.php'],
                        'leadrequirement' => ['i' => 'fa-id-card-clip', 't' => 'Lead Intake', 'p' => '/tools/leads.php']
                    ];
                    foreach ($tools as $key => $meta):
                        $isActive = (isset($team['tool_' . $key]) && $team['tool_' . $key] == 1);
                        ?>
                        <a href="<?php echo $meta['p']; ?>?team_id=<?php echo $team['id']; ?>"
                            class="tool-swatch <?php echo $isActive ? 'active' : ''; ?>" data-title="<?php echo $meta['t']; ?>"
                            style="text-decoration: none;">
                            <i class="fa-solid <?php echo $meta['i']; ?>"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-actions">
                <div class="action-btn-group">
                    <span class="action-link" onclick='openEditModal(<?php echo json_encode($team); ?>)'>Configure</span>

                    <span class="action-link delete"
                        onclick="confirmAction('delete', <?php echo $team['id']; ?>, null, '<?php echo htmlspecialchars($team['name']); ?>')"
                        style="color: #ef4444; background: #fef2f2; border-color: rgba(239, 68, 68, 0.1);">Decommission</span>
                </div>

                <div class="est-badge" style="margin-left: auto; text-align: right; line-height: 1.1;">
                    <span
                        style="display: block; font-size: 0.6rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">EST.</span>
                    <span
                        style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 900; white-space: nowrap;">
                        <?php echo strtoupper(date('M d, y', strtotime($team['created_at']))); ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Creative Modal -->
<div id="teamModal" class="glass-modal">
    <div class="modal-box">
        <div class="flex-between mb-8" style="align-items: flex-start; gap: 1rem;">
            <div style="flex: 1; min-width: 0;">
                <h2 id="modalTitle" class="modal-title-custom">
                    Initialize Squad</h2>
                <p style="color: #64748b; font-weight: 500; font-size: 0.9rem;">Define the purpose and toolset of the
                    new unit.</p>
            </div>
            <button onclick="closeModal()" class="modal-close-btn">&times;</button>
        </div>

        <form method="POST" class="modern-form">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="teamId">

            <div class="modal-form-grid">
                <div>
                    <label>Squad Identification</label>
                    <input type="text" name="name" id="teamName" class="modern-input" placeholder="e.g. Omega Team"
                        required>
                </div>
                <div>
                    <label>Squad Status</label>
                    <select name="status" id="teamStatus" class="modern-input">
                        <option value="active">Operational (Active)</option>
                        <option value="inactive">Standby (Inactive)</option>
                    </select>
                </div>
            </div>

            <label>Strategic Objective</label>
            <textarea name="description" id="teamDesc" class="modern-input"
                style="height: 70px; resize: none; margin-bottom: 1rem;"
                placeholder="Briefly define the team mission..."></textarea>

            <label style="margin-bottom: 1rem;">Deploy Productivity Neural Modules</label>
            <div class="tool-tile-grid">
                <?php
                $formTools = [
                    'word' => ['i' => 'fa-file-word', 'l' => 'Word Engine'],
                    'spreadsheet' => ['i' => 'fa-file-excel', 'l' => 'Spreadsheet'],
                    'calendar' => ['i' => 'fa-calendar-day', 'l' => 'Calendar'],
                    'chat' => ['i' => 'fa-comments', 'l' => 'Chat Sys'],
                    'filemanager' => ['i' => 'fa-folder-open', 'l' => 'Files'],
                    'tasksheet' => ['i' => 'fa-list-check', 'l' => 'Task Sheet'],
                    'leadrequirement' => ['i' => 'fa-id-card-clip', 'l' => 'Lead Req']
                ];
                foreach ($formTools as $key => $data): ?>
                    <label class="tool-tile">
                        <input type="checkbox" name="tool_<?php echo $key; ?>" id="check_<?php echo $key; ?>">
                        <div class="tool-content">
                            <i class="fa-solid <?php echo $data['i']; ?>"></i>
                            <span><?php echo $data['l']; ?></span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 0.5rem; align-items: center;">
                <button type="button" class="action-link"
                    style="border:none; background:none; padding: 0; font-size: 0.75rem;" onclick="closeModal()">Abort
                    Mission</button>
                <button type="submit" class="btn-lux" id="submitBtn">Deploy Unit</button>
            </div>
        </form>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="glass-modal">
    <div class="modal-box">
        <div class="confirm-content-grid">
            <div id="confirmIcon" class="confirm-icon-hex"></div>
            <div class="confirm-info-zone">
                <h2 id="confirmTitle"></h2>
                <p id="confirmText"></p>
            </div>
        </div>

        <form id="confirmForm" method="POST">
            <input type="hidden" name="action" id="confirmFormAction">
            <input type="hidden" name="id" id="confirmFormId">
            <input type="hidden" name="status" id="confirmFormStatus">

            <div class="confirm-form-footer">
                <button type="button" class="stealth-btn cancel" onclick="closeConfirmModal()">Abort</button>
                <button type="submit" id="confirmSubmit" class="stealth-btn primary"></button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        document.getElementById('modalTitle').innerText = 'Initialize Squad';
        document.getElementById('formAction').value = 'create';
        document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-rocket"></i> Deploy Unit';
        document.getElementById('teamId').value = '';
        document.getElementById('teamName').value = '';
        document.getElementById('teamDesc').value = '';
        document.getElementById('teamStatus').value = 'active';
        document.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
        document.getElementById('teamModal').classList.add('show');
    }

    function openEditModal(team) {
        document.getElementById('modalTitle').innerText = 'Reconfigure Squad';
        document.getElementById('formAction').value = 'update';
        document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-sync"></i> Update Configuration';
        document.getElementById('teamId').value = team.id;
        document.getElementById('teamName').value = team.name;
        document.getElementById('teamDesc').value = team.description;
        document.getElementById('teamStatus').value = team.status;

        ['word', 'spreadsheet', 'calendar', 'chat', 'filemanager', 'tasksheet', 'leadrequirement'].forEach(k => {
            const cb = document.getElementById('check_' + k);
            if (cb) cb.checked = (team['tool_' + k] == 1);
        });

        document.getElementById('teamModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('teamModal').classList.remove('show');
    }

    // Confirmation Logic
    function confirmAction(type, id, status, name) {
        const modal = document.getElementById('confirmModal');
        const icon = document.getElementById('confirmIcon');
        const title = document.getElementById('confirmTitle');
        const text = document.getElementById('confirmText');
        const submitBtn = document.getElementById('confirmSubmit');
        const formAction = document.getElementById('confirmFormAction');
        const formId = document.getElementById('confirmFormId');
        const formStatus = document.getElementById('confirmFormStatus');

        // Reset classes
        icon.className = 'confirm-icon-hex ' + type;
        submitBtn.className = 'stealth-btn primary ' + type;

        if (type === 'delete') {
            title.innerText = 'Unit Decommission';
            text.innerText = `Protocol engaged. Are you certain you want to remove ${name} from the active grid? This will also delete all users associated with this team. This action is permanent.`;
            icon.innerHTML = '<i class="fa-solid fa-radiation"></i>';
            submitBtn.innerText = 'Execute Removal';
            formAction.value = 'delete';
        } else {
            const isDeactivating = (status === 'active');
            title.innerText = isDeactivating ? 'Offline Standby' : 'Online Restore';
            text.innerText = isDeactivating ?
                `Directing ${name} to standby mode. Tactical toolsets will be offlined until further notice.` :
                `Restoring full operational capacity to ${name}. All neural modules are re-engaging.`;
            icon.innerHTML = isDeactivating ? '<i class="fa-solid fa-power-off"></i>' : '<i class="fa-solid fa-satellite-dish"></i>';
            submitBtn.innerText = isDeactivating ? 'Offline Unit' : 'Restore Online';
            formAction.value = 'toggle_status';
            formStatus.value = status;
        }

        formId.value = id;
        modal.classList.add('show');
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('show');
    }

    // Modal close on backdrop click
    window.onclick = function (e) {
        const teamModal = document.getElementById('teamModal');
        const confirmModal = document.getElementById('confirmModal');
        if (e.target == teamModal) closeModal();
        if (e.target == confirmModal) closeConfirmModal();
    };
</script>

<?php endLayout(); ?>