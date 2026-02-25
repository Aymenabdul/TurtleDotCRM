<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/../src/layouts/base_layout.php';

// Ensure user is authenticated
$user = AuthMiddleware::requireAuth();

// Get Team ID
$teamId = $_GET['team_id'] ?? null;
if (!$teamId) {
    header("Location: /manage_teams.php");
    exit;
}

// Fetch Team Details to verify existence and access
try {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$teamId]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        header("Location: /manage_teams.php");
        exit;
    }

    // Check if tool is enabled for this team
    $teamTools = json_decode($team['tools'] ?? '[]', true);
    if (!in_array('leads', $teamTools)) {
        die("This tool is not enabled for this team.");
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

startLayout("Lead Tracker - " . $team['name'], $user);
?>

<!-- Premium Leads Dashboard -->
<div class="word-dashboard fade-in">

    <!-- Hero Section -->
    <div class="word-hero mb-4">
        <div class="flex-between align-end">
            <div>
                <?php $is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin'; ?>
                <a href="<?php echo $is_admin ? '/admin_dashboard.php' : '/index.php'; ?>" class="crumb-link mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <h1 class="page-title">Lead Tracker</h1>
                <p class="page-subtitle">Manage and track customer leads for
                    <?php echo htmlspecialchars($team['name']); ?>
                </p>
            </div>
            <div>
                <button class="btn btn-primary btn-lg shine-effect" onclick="openAddLeadModal()">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add New Lead</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="word-content-area">

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-blue-100 text-blue-600">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="stat-value" id="statTotal">0</div>
                    <div class="stat-label">Total Leads</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-amber-100 text-amber-600">
                    <i class="fa-solid fa-star"></i>
                </div>
                <div>
                    <div class="stat-value" id="statNew">0</div>
                    <div class="stat-label">New Leads</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-emerald-100 text-emerald-600">
                    <i class="fa-solid fa-trophy"></i>
                </div>
                <div>
                    <div class="stat-value" id="statWon">0</div>
                    <div class="stat-label">Won Leads</div>
                </div>
            </div>
        </div>

        <!-- Controls Bar -->
        <div class="controls-bar card mb-4">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="leadSearch" placeholder="Search leads by name or email..."
                    oninput="handleSearch()">
            </div>
            <div class="flex-gap">
                <button class="btn btn-secondary icon-only" onclick="loadLeads()" title="Refresh">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </button>
            </div>
        </div>

        <!-- Modern Table -->
        <div class="modern-table-container card">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th width="30%">Lead Details</th>
                        <th width="15%">Status</th>
                        <th width="15%">Contact</th>
                        <th width="15%">Assigned To</th>
                        <th width="15%">Source</th>
                        <th width="10%" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="leadsList">
                    <!-- Loaded via JS -->
                </tbody>
            </table>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state" style="display: none;">
                <div class="empty-illustration">
                    <i class="fa-regular fa-address-book"></i>
                </div>
                <h3>No leads found</h3>
                <p>Add your first lead to get started.</p>
                <button class="btn btn-primary" onclick="openAddLeadModal()">Add Lead</button>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="loading-state">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Lead Modal -->
<div class="modal-backdrop" id="leadModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Lead</h3>
            <button class="close-btn" onclick="closeLeadModal()">&times;</button>
        </div>
        <form id="leadForm" onsubmit="handleLeadSubmit(event)">
            <input type="hidden" name="id" id="leadId">
            <div class="modal-body">
                <div class="grid grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Full
                            Name</label>
                        <input type="text" name="full_name" id="full_name" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Email
                            Address</label>
                        <input type="email" name="email" id="email" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;">
                    </div>
                </div>
                <div class="grid grid-2"
                    style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem;">
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Phone
                            Number</label>
                        <input type="text" name="phone" id="phone" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Status</label>
                        <select name="status" id="status" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;">
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="qualified">Qualified</option>
                            <option value="lost">Lost</option>
                            <option value="won">Won</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2"
                    style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem;">
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Source</label>
                        <input type="text" name="source" id="source" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;"
                            placeholder="e.g. Website">
                    </div>
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Assign
                            To</label>
                        <select name="assigned_to" id="assigned_to" class="form-control"
                            style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px;">
                            <option value="">Unassigned</option>
                            <!-- Users loaded via JS -->
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label"
                        style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem;">Notes</label>
                    <textarea name="notes" id="notes" class="form-control"
                        style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; min-height:80px;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeLeadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveLeadBtn">Save Lead</button>
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

    /* Controls */
    .controls-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
    }

    .search-wrapper {
        position: relative;
        flex: 1;
        max-width: 400px;
    }

    .search-wrapper i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
    }

    .search-wrapper input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.75rem;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        font-size: 0.95rem;
        background: var(--bg-secondary);
        transition: 0.2s;
    }

    .search-wrapper input:focus {
        outline: none;
        background: white;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--focus-ring);
    }

    .icon-only {
        width: 42px;
        height: 42px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-md);
    }

    /* Table */
    .modern-table-container {
        overflow: hidden;
        border: 1px solid var(--border-color);
        padding: 0;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        background: white;
    }

    .modern-table {
        width: 100%;
        border-collapse: collapse;
    }

    .modern-table thead {
        background: var(--bg-secondary);
        border-bottom: 1px solid var(--border-color);
    }

    .modern-table th {
        padding: 1rem 1.5rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .modern-table td {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-main);
        vertical-align: middle;
    }

    .modern-table tbody tr:hover {
        background: #f8fafc;
    }

    .modern-table tr:last-child td {
        border-bottom: none;
    }

    /* Helpers */
    .text-right {
        text-align: right;
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

    .actions-cell {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .btn-action {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        transition: 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-action:hover {
        background: white;
        border-color: var(--border-color);
        color: var(--text-main);
        box-shadow: var(--shadow-sm);
    }

    .btn-action.delete:hover {
        background: var(--error-bg);
        color: var(--error);
        border-color: var(--error-border);
    }

    /* Item Styles */
    .doc-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .doc-icon {
        width: 44px;
        height: 44px;
        background: #e0e7ff;
        color: #4338ca;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .doc-meta h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-main);
    }

    .doc-meta span {
        font-size: 0.75rem;
        color: var(--text-muted);
        display: block;
        margin-top: 2px;
        font-weight: 500;
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .status-new {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-contacted {
        background: #ffedd5;
        color: #9a3412;
    }

    .status-qualified {
        background: #d1fae5;
        color: #065f46;
    }

    .status-lost {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-won {
        background: #dcfce7;
        color: #166534;
    }

    /* States */
    .loading-state {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10;
        backdrop-filter: blur(1px);
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid var(--border-color);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .empty-state {
        padding: 5rem 2rem;
        text-align: center;
    }

    .empty-illustration {
        font-size: 4rem;
        color: var(--border-color);
        margin-bottom: 1.5rem;
        opacity: 0.5;
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
    let leads = [];
    let searchQuery = '';

    // Toast Shim using base_layout's showAlert
    const Toast = {
        success: (title, msg) => showAlert(msg, 'success'),
        error: (title, msg) => showAlert(msg, 'error'),
        info: (title, msg) => showAlert(msg, 'info'),
        warning: (title, msg) => showAlert(msg, 'warning')
    };

    function showLoading(show) {
        document.getElementById('loadingState').style.display = show ? 'flex' : 'none';
        const tbody = document.getElementById('leadsList');
        if (tbody) tbody.style.opacity = show ? '0.3' : '1';
    }

    async function loadLeads() {
        showLoading(true);
        document.getElementById('emptyState').style.display = 'none';

        try {
            const response = await fetch(`/api/leads.php?team_id=${teamId}`);
            const result = await response.json();

            showLoading(false);

            if (result.success) {
                leads = result.data;
                updateStats();
                renderLeads();
            }
        } catch (error) {
            console.error(error);
            showLoading(false);
            Toast.error('System Error', 'Failed to load leads');
        }
    }

    function updateStats() {
        const total = leads.length;
        const won = leads.filter(l => l.status === 'won').length;
        const newLeads = leads.filter(l => l.status === 'new').length;

        document.getElementById('statTotal').innerText = total;
        document.getElementById('statWon').innerText = won;
        document.getElementById('statNew').innerText = newLeads;
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

    function renderLeads() {
        const tbody = document.getElementById('leadsList');
        tbody.innerHTML = '';

        // Filter
        const filtered = leads.filter(l =>
            l.full_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            (l.email && l.email.toLowerCase().includes(searchQuery.toLowerCase()))
        );

        if (filtered.length === 0) {
            if (searchQuery) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No matching leads found.</td></tr>';
            } else {
                document.getElementById('emptyState').style.display = 'block';
            }
            return;
        } else {
            document.getElementById('emptyState').style.display = 'none';
        }

        tbody.innerHTML = filtered.map(lead => {
            const date = new Date(lead.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
            return `
            <tr>
                <td>
                    <div class="doc-info">
                        <div class="doc-icon">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="doc-meta">
                            <h4>${lead.full_name}</h4>
                            <span>${lead.email || 'No email'}</span>
                        </div>
                    </div>
                </td>
                <td><span class="status-badge status-${lead.status}">${lead.status}</span></td>
                <td>
                    <div style="font-size:0.9rem; color:var(--text-main);">${lead.phone || '-'}</div>
                </td>
                <td>
                    <div style="font-size:0.9rem; font-weight:500;">
                        ${lead.assigned_name || '<span class="text-muted" style="font-style:italic">Unassigned</span>'}
                    </div>
                </td>
                <td><span class="text-muted" style="font-size:0.9rem">${lead.source || '-'}</span></td>
                <td class="text-right">
                    <div class="actions-cell">
                        <button class="btn-action" onclick="editLead(${lead.id})" title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn-action delete" onclick="deleteLead(${lead.id})" title="Delete">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `}).join('');
    }

    function handleSearch() {
        searchQuery = document.getElementById('leadSearch').value;
        renderLeads();
    }

    function openAddLeadModal() {
        document.getElementById('modalTitle').textContent = 'Add New Lead';
        document.getElementById('leadForm').reset();
        document.getElementById('leadId').value = '';
        document.getElementById('leadModal').classList.add('active');
    }

    function closeLeadModal() {
        document.getElementById('leadModal').classList.remove('active');
    }

    function editLead(id) {
        const lead = leads.find(l => l.id == id);
        if (!lead) return;

        document.getElementById('modalTitle').textContent = 'Edit Lead';
        document.getElementById('leadId').value = lead.id;
        document.getElementById('full_name').value = lead.full_name;
        document.getElementById('email').value = lead.email;
        document.getElementById('phone').value = lead.phone;
        document.getElementById('status').value = lead.status;
        document.getElementById('source').value = lead.source;
        document.getElementById('assigned_to').value = lead.assigned_to || '';
        document.getElementById('notes').value = lead.notes;

        document.getElementById('leadModal').classList.add('active');
    }

    async function handleLeadSubmit(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        data.team_id = teamId;

        const isEdit = !!data.id;
        const method = isEdit ? 'PATCH' : 'POST';

        const btn = document.getElementById('saveLeadBtn');
        btn.disabled = true;
        btn.textContent = 'Saving...';

        try {
            const response = await fetch('/api/leads.php', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                Toast.success(isEdit ? 'Lead Updated' : 'Lead Added', result.message);
                closeLeadModal();
                loadLeads();
            } else {
                Toast.error('Error', result.message);
            }
        } catch (error) {
            Toast.error('System Error', 'Failed to save lead');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save Lead';
        }
    }

    async function deleteLead(id) {
        Confirm.show({
            title: 'Delete Lead?',
            message: 'Are you sure you want to permanently delete this lead?',
            type: 'danger',
            confirmText: 'Delete Forever',
            onConfirm: async () => {
                try {
                    const response = await fetch('/api/leads.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    const result = await response.json();
                    if (result.success) {
                        Toast.success('Lead Deleted', result.message);
                        loadLeads();
                    } else {
                        Toast.error('Error', result.message);
                    }
                } catch (error) {
                    Toast.error('System Error', 'Failed to delete lead');
                }
            }
        });
    }

    // Initial Load
    loadLeads();
    loadTeamUsers();
</script>

<?php endLayout(); ?>