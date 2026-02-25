<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/../src/layouts/base_layout.php';

$user = AuthMiddleware::requireAuth();
$teamId = $_GET['team_id'] ?? null;

if (!$teamId) {
    header("Location: /manage_teams.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$teamId]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        header("Location: /manage_teams.php");
        exit;
    }

    $teamTools = json_decode($team['tools'] ?? '[]', true);
    if (!in_array('tasks', $teamTools)) {
        die("This tool is not enabled for this team.");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

startLayout("Task Manager - " . $team['name'], $user);
?>

<!-- Premium Tasks Dashboard -->
<div class="word-dashboard fade-in">

    <!-- Hero Section -->
    <div class="word-hero mb-4">
        <div class="flex-between align-end">
            <div>
                <?php $is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin'; ?>
                <a href="<?php echo $is_admin ? '/admin_dashboard.php' : '/index.php'; ?>" class="crumb-link mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <h1 class="page-title">Task Manager</h1>
                <p class="page-subtitle">Organize and track team tasks for
                    <?php echo htmlspecialchars($team['name']); ?>
                </p>
            </div>
            <div>
                <button class="btn btn-primary btn-lg shine-effect" onclick="openAddTaskModal()">
                    <i class="fa-solid fa-plus"></i>
                    <span>Create Task</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="word-content-area">

        <!-- Stats Grid (Optional for Tasks) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-blue-100 text-blue-600">
                    <i class="fa-solid fa-list-check"></i>
                </div>
                <div>
                    <div class="stat-value" id="statTotal">0</div>
                    <div class="stat-label">Total Tasks</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-amber-100 text-amber-600">
                    <i class="fa-solid fa-spinner"></i>
                </div>
                <div>
                    <div class="stat-value" id="statProgress">0</div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-emerald-100 text-emerald-600">
                    <i class="fa-solid fa-check-double"></i>
                </div>
                <div>
                    <div class="stat-value" id="statDone">0</div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>

        <?php
        $statuses = [
            'todo' => ['name' => 'To Do', 'icon' => 'fa-list-ul', 'color' => '#64748b', 'bg' => '#f1f5f9'],
            'in_progress' => ['name' => 'In Progress', 'icon' => 'fa-spinner', 'color' => '#0284c7', 'bg' => '#e0f2fe'],
            'review' => ['name' => 'Review', 'icon' => 'fa-eye', 'color' => '#d97706', 'bg' => '#fef3c7'],
            'done' => ['name' => 'Completed', 'icon' => 'fa-check-circle', 'color' => '#059669', 'bg' => '#d1fae5']
        ];
        ?>

        <!-- Kanban Board -->
        <div class="board-grid">
            <?php foreach ($statuses as $id => $status): ?>
                <div class="board-column" id="col-<?php echo $id; ?>">
                    <div class="column-header" style="border-top: 3px solid <?php echo $status['color']; ?>">
                        <div class="flex-gap align-center">
                            <div class="status-icon"
                                style="background:<?php echo $status['bg']; ?>; color:<?php echo $status['color']; ?>">
                                <i class="fa-solid <?php echo $status['icon']; ?>"></i>
                            </div>
                            <span><?php echo $status['name']; ?></span>
                        </div>
                        <span class="task-count" id="count-<?php echo $id; ?>">0</span>
                    </div>
                    <div class="task-list" id="list-<?php echo $id; ?>" ondrop="drop(event, '<?php echo $id; ?>')"
                        ondragover="allowDrop(event)">
                        <!-- Tasks loaded via JS -->
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="modal-backdrop" id="taskModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Create Task</h3>
            <button class="close-btn" onclick="closeTaskModal()">&times;</button>
        </div>
        <form id="taskForm" onsubmit="handleTaskSubmit(event)">
            <input type="hidden" name="id" id="taskId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"
                        style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Task
                        Title</label>
                    <input type="text" name="title" id="title" class="form-control"
                        style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;"
                        required placeholder="e.g. Design new landing page">
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label"
                        style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Description</label>
                    <textarea name="description" id="description" class="form-control"
                        style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; min-height:80px;"
                        placeholder="Provide more details..."></textarea>
                </div>
                <div class="grid grid-2"
                    style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem;">
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Priority</label>
                        <select name="priority" id="priority" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Due
                            Date</label>
                        <input type="date" name="due_date" id="due_date" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;">
                    </div>
                </div>
                <div class="grid grid-2"
                    style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem;">
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Assign
                            To</label>
                        <select name="assigned_to" id="assigned_to" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;">
                            <option value="">Unassigned</option>
                            <!-- JS populated -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Status</label>
                        <select name="status" id="taskStatus" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;">
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">Review</option>
                            <option value="done">Completed</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveTaskBtn">Save Task</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../src/components/ui/glass-confirm.php'; ?>
<?php include __DIR__ . '/../src/components/ui/glass-toast.php'; ?>

<style>
    /* Reuse Word Dashboard Styles */
    .word-dashboard {
        font-family: 'Inter', sans-serif;
        padding-bottom: 4rem;
    }

    .word-hero {
        margin-bottom: 2.5rem;
    }

    .crumb-link {
        color: var(--text-muted);
        text-decoration: none;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: 0.2s;
    }

    .crumb-link:hover {
        color: var(--primary);
    }

    .page-title {
        font-size: 2rem;
        font-weight: 800;
        color: var(--text-main);
        margin: 0;
        letter-spacing: -0.03em;
        line-height: 1.2;
    }

    .page-subtitle {
        color: var(--text-muted);
        margin: 0.5rem 0 0 0;
        font-size: 1.05rem;
    }

    .shine-effect {
        position: relative;
        overflow: hidden;
    }

    .shine-effect::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: 0.5s;
    }

    .shine-effect:hover::after {
        left: 100%;
    }

    /* Stats */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        transition: 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-main);
        line-height: 1;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .bg-blue-100 {
        background: #dbeafe;
    }

    .text-blue-600 {
        color: #2563eb;
    }

    .bg-emerald-100 {
        background: #d1fae5;
    }

    .text-emerald-600 {
        color: #059669;
    }

    .bg-amber-100 {
        background: #fef3c7;
    }

    .text-amber-600 {
        color: #d97706;
    }

    /* Kanban Board */
    .board-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        min-height: 70vh;
    }

    .board-column {
        background: #f8fafc;
        border-radius: var(--radius-lg);
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border-color);
        height: 100%;
    }

    .column-header {
        padding: 1rem;
        background: white;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 600;
        font-size: 0.95rem;
        border-bottom: 1px solid var(--border-color);
    }

    .status-icon {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        margin-right: 0.5rem;
    }

    .task-count {
        background: var(--bg-secondary);
        padding: 0.2rem 0.6rem;
        border-radius: 20px;
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: 700;
    }

    .task-list {
        flex: 1;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        min-height: 100px;
    }

    .task-card {
        background: white;
        padding: 1rem;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        cursor: grab;
        transition: all 0.2s ease;
    }

    .task-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border-color: var(--primary-light);
    }

    .task-card.dragging {
        opacity: 0.5;
        transform: scale(0.95);
    }

    .task-card-title {
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 0.75rem;
        color: var(--text-main);
        line-height: 1.4;
    }

    .task-card-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        font-size: 0.75rem;
    }

    .priority-badge {
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
    }

    .priority-low {
        background: #f3f4f6;
        color: #6b7280;
    }

    .priority-medium {
        background: #e0f2fe;
        color: #0369a1;
    }

    .priority-high {
        background: #ffedd5;
        color: #9a3412;
    }

    .priority-urgent {
        background: #fee2e2;
        color: #991b1b;
    }

    .task-card-footer {
        margin-top: 0.5rem;
        padding-top: 0.75rem;
        border-top: 1px dashed #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .task-user {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-muted);
        font-size: 0.8rem;
        font-weight: 500;
    }

    .user-avatar-sm {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #e0e7ff;
        color: #4338ca;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
    }

    @media (max-width: 1200px) {
        .board-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .board-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Modal */
    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 100;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }

    .modal-backdrop.active {
        display: flex;
    }

    .modal {
        background: white;
        width: 600px;
        border-radius: 16px;
        box-shadow: var(--shadow-xl);
        animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
        display: block;
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-secondary);
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: var(--text-main);
        font-weight: 700;
    }

    .modal-body {
        padding: 2rem 1.5rem;
    }

    .modal-footer {
        padding: 1.25rem 1.5rem;
        background: var(--bg-secondary);
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }
</style>

<script>
    const teamId = <?php echo $teamId; ?>;
    let tasks = [];

    // Toast Shim using base_layout's showAlert
    const Toast = {
        success: (title, msg) => showAlert(msg, 'success'),
        error: (title, msg) => showAlert(msg, 'error')
    };

    async function loadTasks() {
        try {
            const response = await fetch(`/api/tasks.php?team_id=${teamId}`);
            const result = await response.json();
            if (result.success) {
                tasks = result.data;
                updateStats();
                renderTasks();
            }
        } catch (error) {
            Toast.error('System Error', 'Failed to load tasks');
        }
    }

    function updateStats() {
        document.getElementById('statTotal').innerText = tasks.length;
        document.getElementById('statProgress').innerText = tasks.filter(t => t.status === 'in_progress').length;
        document.getElementById('statDone').innerText = tasks.filter(t => t.status === 'done').length;
    }

    async function loadTeamUsers() {
        try {
            const response = await fetch(`/api/users.php?team_id=${teamId}`);
            const result = await response.json();
            if (result.success) {
                const select = document.getElementById('assigned_to');
                select.innerHTML = '<option value="">Unassigned</option>';
                result.data.forEach(user => {
                    select.innerHTML += `<option value="${user.id}">${user.full_name}</option>`;
                });
            }
        } catch (ignore) { }
    }

    function renderTasks() {
        ['todo', 'in_progress', 'review', 'done'].forEach(status => {
            const list = document.getElementById(`list-${status}`);
            const count = document.getElementById(`count-${status}`);
            const filtered = tasks.filter(t => t.status === status);

            count.textContent = filtered.length;
            list.innerHTML = '';

            filtered.forEach(task => {
                const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : 'No date';
                const assigneeInitial = task.assigned_name ? task.assigned_name.charAt(0).toUpperCase() : '?';

                list.innerHTML += `
                <div class="task-card" draggable="true" ondragstart="drag(event, ${task.id})" onclick="editTask(${task.id})">
                    <div class="task-card-meta">
                        <span class="priority-badge priority-${task.priority}">${task.priority}</span>
                        ${task.due_date ? `<span class="text-muted" style="font-size:0.7rem"><i class="fa-regular fa-clock"></i> ${dueDate}</span>` : ''}
                    </div>
                    <div class="task-card-title">${task.title}</div>
                    
                    <div class="task-card-footer">
                        <div class="task-user">
                             ${task.assigned_name ?
                        `<div class="user-avatar-sm">${assigneeInitial}</div> <span>${task.assigned_name}</span>` :
                        `<span class="text-muted" style="font-size:0.75rem; font-style:italic">Unassigned</span>`}
                        </div>
                        <button class="btn-action delete" style="width:28px; height:28px;" onclick="event.stopPropagation(); deleteTask(${task.id})">
                            <i class="fa-solid fa-trash" style="font-size:0.8rem;"></i>
                        </button>
                    </div>
                </div>
            `;
            });
        });
    }

    // Drag & Drop
    function allowDrop(ev) { ev.preventDefault(); }
    function drag(ev, id) { ev.dataTransfer.setData("text", id); }
    async function drop(ev, status) {
        ev.preventDefault();
        const id = ev.dataTransfer.getData("text");
        const task = tasks.find(t => t.id == id);
        if (task && task.status !== status) {
            // Optimistic update
            const oldStatus = task.status;
            task.status = status;
            renderTasks();
            updateStats();

            // Update on server
            try {
                await fetch('/api/tasks.php', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(task)
                });
            } catch (error) {
                Toast.error('System Error', 'Failed to move task');
                task.status = oldStatus; // Revert
                renderTasks();
                updateStats();
            }
        }
    }

    function openAddTaskModal() {
        document.getElementById('modalTitle').textContent = 'Create Task';
        document.getElementById('taskForm').reset();
        document.getElementById('taskId').value = '';
        document.getElementById('taskStatus').value = 'todo';
        document.getElementById('taskModal').classList.add('active');
    }

    function closeTaskModal() {
        document.getElementById('taskModal').classList.remove('active');
    }

    function editTask(id) {
        const task = tasks.find(t => t.id == id);
        if (!task) return;

        document.getElementById('modalTitle').textContent = 'Edit Task';
        document.getElementById('taskId').value = task.id;
        document.getElementById('title').value = task.title;
        document.getElementById('description').value = task.description;
        document.getElementById('priority').value = task.priority;
        document.getElementById('due_date').value = task.due_date;
        document.getElementById('assigned_to').value = task.assigned_to || '';
        document.getElementById('taskStatus').value = task.status;

        document.getElementById('taskModal').classList.add('active');
    }

    async function handleTaskSubmit(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        data.team_id = teamId;

        const isEdit = !!data.id;
        const method = isEdit ? 'PATCH' : 'POST';

        const btn = document.getElementById('saveTaskBtn');
        btn.innerHTML = 'Saving...';
        btn.disabled = true;

        try {
            const response = await fetch('/api/tasks.php', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                Toast.success(isEdit ? 'Task Updated' : 'Task Created', result.message);
                closeTaskModal();
                loadTasks();
            }
        } catch (error) {
            Toast.error('System Error', 'Failed to save task');
        } finally {
            btn.innerHTML = 'Save Task';
            btn.disabled = false;
        }
    }

    async function deleteTask(id) {
        Confirm.show({
            title: 'Delete Task?',
            message: 'Are you sure you want to delete this task?',
            type: 'danger',
            confirmText: 'Delete Task',
            onConfirm: async () => {
                try {
                    const response = await fetch('/api/tasks.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    const result = await response.json();
                    if (result.success) {
                        Toast.success('Task Deleted');
                        loadTasks();
                    }
                } catch (error) {
                    Toast.error('System Error', 'Failed to delete task');
                }
            }
        });
    }

    loadTasks();
    loadTeamUsers();
</script>

<?php endLayout(); ?>