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

    // Quick stats
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN created_by = ? THEN 1 ELSE 0 END) as mine
        FROM spreadsheets 
        WHERE team_id = ?
    ");
    $statsStmt->execute([$user['user_id'], $teamId]);
    $stats = $statsStmt->fetch();
    if (!$stats)
        $stats = ['total' => 0, 'mine' => 0];
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

startLayout("Spreadsheets - " . $team['name'], $user);
?>

<!-- Premium Spreadsheet Dashboard -->
<div class="sheet-dashboard fade-in">

    <!-- Hero Section -->
    <div class="sheet-hero mb-4">
        <div class="flex-between align-end">
            <div>
                <?php $is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin'; ?>
                <a href="<?php echo $is_admin ? '/admin_dashboard.php' : '/index.php'; ?>" class="crumb-link mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <h1 class="page-title">Spreadsheets</h1>
                <p class="page-subtitle">Manage and collaborate on your team's spreadsheets.</p>
            </div>
            <div>
                <!-- Create via API and redirect, or link to editor with new flag -->
                <!-- For simplicity and consistency with existing tool buffer, we link to the editor 
                      which has a 'New Sheet' button, or we can implement a direct create action here later.
                      However, to match Word Dashboard, let's make it create a new sheet immediately. -->
                <button onclick="createNewSheet()" class="btn btn-primary btn-lg shine-effect">
                    <i class="fa-solid fa-plus"></i>
                    <span>New Spreadsheet</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="sheet-content-area">

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-green-100 text-green-600">
                    <i class="fa-solid fa-file-excel"></i>
                </div>
                <div>
                    <div class="stat-value" id="statTotalSheets"><?php echo (int) ($stats['total'] ?? 0); ?></div>
                    <div class="stat-label">Total Sheets</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-emerald-100 text-emerald-600">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div>
                    <div class="stat-value" id="statMySheets"><?php echo (int) ($stats['mine'] ?? 0); ?></div>
                    <div class="stat-label">My Sheets</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-amber-100 text-amber-600">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div>
                    <div class="stat-value">Active</div>
                    <div class="stat-label">System Status</div>
                </div>
            </div>
        </div>

        <!-- Controls Bar -->
        <div class="controls-bar card mb-4">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="docSearch" placeholder="Search spreadsheets..." oninput="handleSearch()">
            </div>
            <div class="flex-gap">
                <button class="btn btn-secondary icon-only" onclick="loadDocuments(1)" title="Refresh">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </button>
            </div>
        </div>

        <!-- Modern Table -->
        <div class="modern-table-container card">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th width="15%">Sheet Name</th>
                        <th width="15%">Author</th>
                        <th width="15%">Updated By</th>
                        <th width="15%">Assigned By</th>
                        <th width="20%">Assigned To</th>
                        <th width="20%">Actions</th>
                    </tr>
                </thead>
                <tbody id="docTableBody">
                    <!-- Content loaded via JS -->
                </tbody>
            </table>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state" style="display: none;">
                <div class="empty-illustration">
                    <i class="fa-regular fa-file-excel"></i>
                </div>
                <h3>No spreadsheets yet</h3>
                <p>Create your first spreadsheet to get started.</p>
                <button class="btn btn-primary" onclick="createNewSheet()">Create Spreadsheet</button>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="loading-state">
                <div class="spinner"></div>
            </div>
        </div>

        <!-- Pagination -->
        <div id="docPagination" class="pagination-container"></div>
    </div>
</div>

<?php include __DIR__ . '/../src/components/ui/glass-confirm.php'; ?>
<?php include __DIR__ . '/../src/components/ui/glass-toast.php'; ?>

<!-- Share / Assignment Modal -->
<div id="shareModal" class="share-modal-overlay">
    <div class="share-modal">
        <div class="share-header">
            <h3>Share Spreadsheet</h3>
            <button class="close-btn" onclick="closeShareModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="share-body">
            <!-- Search -->
            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="assignSearch" placeholder="Search members..." oninput="filterAssignees()">
            </div>

            <!-- Tabs -->
            <div class="team-tabs" id="teamTabs">
                <!-- Populated via JS -->
            </div>

            <!-- User List -->
            <div class="assignee-list custom-scrollbar" id="assigneeList">
                <!-- Populated via JS -->
                <div class="loading-spinner"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</div>
            </div>
        </div>

        <div class="share-footer">
            <div id="selectionCount" class="selection-count">0 members selected</div>
            <button id="saveShareBtn" class="btn-primary" onclick="saveAssignment()">Save Changes</button>
        </div>
    </div>
</div>

<!-- View Assignments Modal (Read Only) -->
<div id="viewAssignmentsModal" class="share-modal-overlay">
    <div class="share-modal" style="width: 500px; max-width: 95vw;">
        <div class="share-header">
            <h3>Assigned Members</h3>
            <button class="close-btn" onclick="closeViewAssignmentsModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="share-body" style="min-height: 200px;">
            <div class="assignee-list" id="viewAssigneeList">
                <!-- User list goes here -->
            </div>
        </div>

        <!-- No footer needed for read-only -->
    </div>
</div>

<link rel="stylesheet" href="/css/timesheet.css">

<script>
    const teamId = <?php echo $teamId; ?>;
    const userId = <?php echo $user['user_id']; ?>;
    let currentPage = 1;
    let searchQuery = '';

    function showLoading(show) {
        document.getElementById('loadingState').style.display = show ? 'flex' : 'none';
        document.getElementById('docTableBody').style.opacity = show ? '0.3' : '1';
    }

    async function loadDocuments(page = 1) {
        currentPage = page;
        showLoading(true);
        document.getElementById('emptyState').style.display = 'none';

        try {
            const response = await fetch(`/api/spreadsheet.php?team_id=${teamId}&page=${page}&limit=10&search=${encodeURIComponent(searchQuery)}`);
            const result = await response.json();

            showLoading(false);

            if (result.success) {
                renderTable(result.data);
                renderPagination(result.pagination);
                updateStats(result.stats);
            }
        } catch (err) {
            console.error(err);
            showLoading(false);
            if (window.Toast) Toast.error("Error", "Failed to load spreadsheets.");
        }
    }

    function updateStats(stats) {
        if (!stats) return;
        const totalEl = document.getElementById('statTotalSheets');
        const mineEl = document.getElementById('statMySheets');
        if (totalEl) totalEl.textContent = stats.total;
        if (mineEl) mineEl.textContent = stats.mine;
    }

    function renderTable(docs) {
        window.currentDocs = docs; // Store for access
        const tbody = document.getElementById('docTableBody');
        tbody.innerHTML = '';

        if (docs.length === 0) {
            document.getElementById('emptyState').style.display = 'block';
            return;
        }

        tbody.innerHTML = docs.map((doc, index) => `
            <tr onclick="window.location.href='/tools/spreadsheet_editor.php?team_id=${teamId}&id=${doc.id}'" style="cursor: pointer;">
                <td>
                    <div class="doc-info">
                        <div class="doc-icon">
                            <i class="fa-solid fa-file-excel"></i>
                        </div>
                        <div class="doc-meta">
                            <h4>${doc.title}</h4>
                            <span>Spreadsheet</span>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="user-pill">
                        <span class="user-text">${doc.author_name || 'Unknown'}</span>
                    </div>
                </td>
                <td>
                     <div class="user-pill">
                        <span class="user-text">${doc.updated_by_name || '—'}</span>
                    </div>
                </td>
                <td>
                     <div class="user-pill">
                        <span class="user-text">${doc.assigned_by_name || '—'}</span>
                    </div>
                </td>
                <td>
                    <div class="user-chip" onclick="viewAssignments(event, ${index})">
                        <i class="fa-solid fa-users" style="margin-right:6px; font-size:0.75rem;"></i>
                        <span class="user-text" style="color:inherit;">
                             ${(doc.assigned_users && doc.assigned_users.length > 0)
                ? doc.assigned_users.length + (doc.assigned_users.length === 1 ? ' Member' : ' Members')
                : 'Unassigned'}
                        </span>
                    </div>
                </td>
                <td>
                    <div class="actions-cell" onclick="event.stopPropagation()">
                        <button class="btn-action" onclick="triggerShare(event, ${index})" title="Assign / Share">
                            <i class="fa-solid fa-user-plus"></i>
                        </button>
                        <button class="btn-action" onclick="window.location.href='/tools/spreadsheet_editor.php?team_id=${teamId}&id=${doc.id}'" title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn-action delete" onclick="confirmDelete(event, ${doc.id})" title="Delete">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }
    // ...


    function renderPagination(pg) {
        const container = document.getElementById('docPagination');
        if (pg.pages <= 1) { container.innerHTML = ''; return; }

        let html = '';
        if (pg.page > 1) html += `<button class="pg-btn" onclick="loadDocuments(${pg.page - 1})"><i class="fa-solid fa-chevron-left"></i></button>`;
        for (let i = 1; i <= pg.pages; i++) {
            html += `<button class="pg-btn ${i === pg.page ? 'active' : ''}" onclick="loadDocuments(${i})">${i}</button>`;
        }
        if (pg.page < pg.pages) html += `<button class="pg-btn" onclick="loadDocuments(${pg.page + 1})"><i class="fa-solid fa-chevron-right"></i></button>`;
        container.innerHTML = html;
    }

    let searchTimer;
    function handleSearch() {
        searchQuery = document.getElementById('docSearch').value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadDocuments(1), 300);
    }

    async function createNewSheet() {
        try {
            const response = await fetch('/api/spreadsheet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    team_id: teamId,
                    title: 'New Spreadsheet',
                    content: JSON.stringify(Array(10).fill(Array(10).fill('')))
                })
            });
            const result = await response.json();
            if (result.success) {
                // Redirect to editor
                window.location.href = `/tools/spreadsheet_editor.php?team_id=${teamId}&id=${result.id}`;
            } else {
                if (window.Toast) Toast.error("Error", "Failed to create spreadsheet.");
            }
        } catch (e) {
            console.error(e);
            if (window.Toast) Toast.error("Error", "Network error.");
        }
    }

    function triggerShare(e, index) {
        e.preventDefault();
        e.stopPropagation();
        if (window.currentDocs && window.currentDocs[index]) {
            const doc = window.currentDocs[index];
            openShareModal(doc.id, doc.assigned_to);
        }
    }

    function confirmDelete(e, id) {
        e.preventDefault();
        e.stopPropagation();

        Confirm.show({
            title: 'Delete Spreadsheet',
            message: 'Are you sure you want to delete this spreadsheet? This action cannot be undone.',
            confirmText: 'Delete Forever',
            type: 'danger',
            onConfirm: async () => {
                try {
                    const response = await fetch('/api/spreadsheet.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, team_id: teamId })
                    });
                    const res = await response.json();

                    if (res.success) {
                        Toast.success('Deleted', 'Spreadsheet deleted successfully');
                        loadDocuments(currentPage);
                    } else {
                        Toast.error('Error', res.message);
                    }
                } catch (e) {
                    Toast.error('Error', 'Network error occurred');
                }
            }
        });
    }

    // Initial Load
    document.addEventListener('DOMContentLoaded', () => {
        loadDocuments();
    });

    // Detect when user returns via Back button and force refresh
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            loadDocuments(currentPage);
        }
    });
    // Share / Assignment Logic
    let teamsData = [];
    let usersData = [];
    let currentAssignedTo = []; // List of User IDs
    let activeTeamTab = null;
    let currentSheetId = null;

    async function openShareModal(id, assignedToRaw) {
        currentSheetId = id;

        // Parse assigned_to
        // Parse assigned_to
        currentAssignedTo = [];
        if (assignedToRaw) {
            try {
                if (Array.isArray(assignedToRaw)) {
                    currentAssignedTo = assignedToRaw.map(Number);
                } else if (typeof assignedToRaw === 'string') {
                    // Start with checking if it is a simple number
                    if (assignedToRaw.match(/^\d+$/)) {
                        currentAssignedTo = [Number(assignedToRaw)];
                    } else {
                        // Try JSON parse
                        try {
                            const parsed = JSON.parse(assignedToRaw);
                            if (Array.isArray(parsed)) {
                                currentAssignedTo = parsed.map(Number);
                            } else if (parsed) {
                                currentAssignedTo = [Number(parsed)];
                            }
                        } catch (e) {
                            console.warn("Error parsing assignment JSON", e);
                        }
                    }
                } else if (typeof assignedToRaw === 'number') {
                    currentAssignedTo = [assignedToRaw];
                }
            } catch (e) {
                console.error("Error setting assignments", e);
                currentAssignedTo = [];
            }
        }

        document.getElementById('shareModal').style.display = 'flex';

        // Fetch Data if empty
        if (teamsData.length === 0 || usersData.length === 0) {
            await fetchShareData();
        } else {
            renderShareModal();
        }
    }

    function closeShareModal() {
        document.getElementById('shareModal').style.display = 'none';
        currentSheetId = null;
    }

    async function fetchShareData() {
        try {
            // Parallel fetch
            const [teamsRes, usersRes] = await Promise.all([
                fetch('/api/teams.php'),
                fetch('/api/users.php')
            ]);

            const teamsJson = await teamsRes.json();
            const usersJson = await usersRes.json();

            if (teamsJson.success) teamsData = teamsJson.teams;
            if (usersJson.success) usersData = usersJson.users;

            // Sort users by name
            usersData.sort((a, b) => (a.full_name || a.username).localeCompare(b.full_name || b.username));

            // Determine active tab (current team if possible)
            if (teamsData.length > 0) {
                const currentTeam = teamsData.find(t => t.id == teamId);
                activeTeamTab = currentTeam ? currentTeam.id : teamsData[0].id;
            }

            renderShareModal();

        } catch (e) {
            console.error("Failed to load share data", e);
            if (window.Toast) Toast.error("Error", "Failed to load team data");
        }
    }

    function renderShareModal() {
        // Render Tabs
        const tabsContainer = document.getElementById('teamTabs');
        if (teamsData.length === 0) {
            tabsContainer.innerHTML = '<div class="team-tab active">All Users</div>';
            activeTeamTab = 'all';
        } else {
            tabsContainer.innerHTML = teamsData.map(team => `
                <div class="team-tab ${team.id == activeTeamTab ? 'active' : ''}" 
                        onclick="switchTeamTab(${team.id})">
                    ${team.name}
                </div>
            `).join('');
        }



        removeInactiveAssignments(); // Remove inactive users from selection

        renderAssigneeList();
        updateSelectionCount();
    }

    function switchTeamTab(tId) {
        activeTeamTab = tId;
        renderShareModal(); // Re-render tabs and list
    }

    function removeInactiveAssignments() {
        if (!usersData || usersData.length === 0) return;

        // Allow only IDs that belong to existing, active users
        const validActiveIds = usersData
            .filter(u => u.is_active == 1)
            .map(u => parseInt(u.id));

        // Filter currentAssignedTo to keep only valid IDs
        const initialCount = currentAssignedTo.length;
        currentAssignedTo = currentAssignedTo.filter(id => validActiveIds.includes(id));

        if (currentAssignedTo.length !== initialCount) {
            console.log("Removed invalid/inactive users from selection");
        }
    }

    function renderAssigneeList() {
        const listContainer = document.getElementById('assigneeList');
        const search = document.getElementById('assignSearch').value.toLowerCase();

        // Filter users by team and search
        const filteredUsers = usersData.filter(u => {
            // Team Filter
            if (activeTeamTab !== 'all' && u.team_id != activeTeamTab) return false;

            // Search Filter
            const name = (u.full_name || u.username).toLowerCase();
            const email = (u.email || '').toLowerCase();
            if (search && !name.includes(search) && !email.includes(search)) return false;

            return true;
        });

        if (filteredUsers.length === 0) {
            listContainer.innerHTML = `
                <div style="padding:40px 20px; text-align:center; color:#9ca3af; display:flex; flex-direction:column; align-items:center;">
                    <i class="fa-solid fa-user-slash" style="font-size:2rem; margin-bottom:12px; opacity:0.5;"></i>
                    <span style="font-size:0.95rem; font-weight:500;">No members found</span>
                </div>
            `;
            return;
        }

        listContainer.innerHTML = filteredUsers.map(u => {
            const isSelected = currentAssignedTo.includes(parseInt(u.id)); // Ensure numeric comparison
            const isActive = u.is_active == 1;
            const rowStyle = isActive ? '' : 'opacity: 0.6; cursor: not-allowed; background: #f9fafb;';

            return `
                <div class="user-row ${isSelected ? 'selected' : ''}" style="${rowStyle}" onclick="toggleAssignee(${u.id}, ${isActive})">
                    <div style="margin-right: -4px;">
                        ${isSelected ?
                    '<i class="fa-solid fa-circle-check" style="color:#10b981; font-size: 1.1rem;"></i>' :
                    '<i class="fa-regular fa-circle" style="color:#cbd5e1; font-size: 1.1rem;"></i>'}
                    </div>
                    <div class="user-avatar-small">
                        ${(u.full_name || u.username).charAt(0).toUpperCase()}
                    </div>
                    <div class="user-info">
                        <span class="user-name">${u.full_name || u.username}</span>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <div style="width:6px; height:6px; background:${isActive ? '#10b981' : '#ef4444'}; border-radius:50%;"></div>
                            <span style="font-size:0.75rem; font-weight:600; color:#64748b;">${isActive ? 'Active' : 'Inactive'}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function filterAssignees() {
        renderAssigneeList();
    }

    function toggleAssignee(uid, isActive) {
        if (!isActive) {
            if (window.Toast) Toast.error("Unable to Assign", "Inactive users cannot be assigned.");
            return;
        }

        const id = Number(uid);
        const index = currentAssignedTo.indexOf(id);
        if (index > -1) {
            currentAssignedTo.splice(index, 1);
        } else {
            currentAssignedTo.push(id);
        }
        renderAssigneeList(); // Re-render to update checkboxes
        updateSelectionCount();
    }

    function updateSelectionCount() {
        const count = currentAssignedTo.length;
        const countEl = document.getElementById('selectionCount');
        if (countEl) {
            countEl.textContent = `${count} member${count !== 1 ? 's' : ''} selected`;
        }
    }

    async function saveAssignment() {
        if (!currentSheetId) return;

        try {
            const btn = document.getElementById('saveShareBtn');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';
            btn.disabled = true;

            const response = await fetch('/api/spreadsheet.php', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: currentSheetId,
                    assigned_to: currentAssignedTo,
                    assigned_by: userId
                })
            });

            const res = await response.json();

            if (res.success) {
                if (window.Toast) Toast.success("Updated", "Assignments updated successfully");
                closeShareModal();
                loadDocuments(currentPage); // Refresh table
            } else {
                if (window.Toast) Toast.error("Error", res.message);
            }

            btn.innerHTML = originalContent;
            btn.disabled = false;

        } catch (e) {
            console.error(e);
            if (window.Toast) Toast.error("Error", "Network error");
        }
    }
    /* --- View Only Logic --- */

    async function viewAssignments(e, index) {
        e.preventDefault();
        e.stopPropagation();

        if (!window.currentDocs || !window.currentDocs[index]) return;
        const doc = window.currentDocs[index];
        const assignedToRaw = doc.assigned_to;

        // Ensure users are loaded
        if (usersData.length === 0) {
            document.body.style.cursor = 'wait';
            await fetchShareData();
            document.body.style.cursor = 'default';
        }

        // Parse Assignments
        let assignedIds = [];
        try {
            if (typeof assignedToRaw === 'object' && assignedToRaw !== null) {
                assignedIds = Array.isArray(assignedToRaw) ? assignedToRaw.map(Number) : [Number(assignedToRaw)];
            } else if (assignedToRaw) {
                const parsed = JSON.parse(assignedToRaw);
                if (Array.isArray(parsed)) {
                    assignedIds = parsed.map(Number);
                } else {
                    assignedIds = [Number(parsed)];
                }
            }
        } catch (e) {
            if (typeof assignedToRaw === 'number' || (typeof assignedToRaw === 'string' && assignedToRaw.match(/^\d+$/))) {
                assignedIds = [Number(assignedToRaw)];
            }
        }

        // Filter users (Active Only)
        const assignedUsers = usersData.filter(u => assignedIds.includes(parseInt(u.id)) && u.is_active == 1);
        // Render
        const listDiv = document.getElementById('viewAssigneeList');
        if (assignedUsers.length === 0) {
            listDiv.className = ''; // Reset layout for center message
            listDiv.innerHTML = `
                <div style="padding:40px 20px; text-align:center; color:#9ca3af; display:flex; flex-direction:column; align-items:center;">
                    <i class="fa-solid fa-user-slash" style="font-size:2rem; margin-bottom:12px; opacity:0.5;"></i>
                    <span style="font-size:0.95rem; font-weight:500;">No members assigned</span>
                </div>
                `;
        } else {
            listDiv.className = 'view-user-list'; // Apply Grid Layout
            listDiv.innerHTML = assignedUsers.map(u => `
                <div class="view-user-card" style="--role-color: ${getRoleSolidColor(u.role)}">
                    <div class="view-card-avatar-ring" style="margin-right: 8px;">
                        <div class="user-avatar-small" style="width:36px; height:36px; font-size:0.9rem; background:#f1f5f9; color:#334155; border:none;">
                            ${(u.full_name || u.username).charAt(0).toUpperCase()}
                        </div>
                    </div>
                    
                    <div style="flex:1; min-width: 0; padding-right: 8px;">
                        <span class="user-name" style="font-size:0.9rem; font-weight:600; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;">
                            ${u.full_name || u.username}
                        </span>
                    </div>

                    <div style="flex:1; display:flex; justify-content:center; min-width: 0;">
                         <span style="font-size:0.7rem; font-weight:700; color:var(--role-color); text-transform:uppercase; letter-spacing:0.05em; background: #f8fafc; padding: 4px 8px; border-radius: 6px;">
                            ${u.role || 'Member'}
                         </span>
                    </div>
                    
                    <div style="flex:1; text-align:right; min-width: 0;">
                         <span style="font-size:0.85rem; color:#94a3b8; font-weight:500;">@${u.username}</span>
                    </div>
                </div>
                `).join('');
        }

        document.getElementById('viewAssignmentsModal').style.display = 'flex';
    }


    function closeViewAssignmentsModal() {
        document.getElementById('viewAssignmentsModal').style.display = 'none';
    }
    function getRoleColor(role) {
        if (!role) return '#f3f4f6';
        role = role.toLowerCase();
        if (role.includes('admin')) return '#fee2e2';
        if (role.includes('manager')) return '#fef3c7';
        if (role.includes('development')) return '#dbeafe';
        if (role.includes('design')) return '#fce7f3';
        return '#f1f5f9';
    }
    function getRoleSolidColor(role) {
        if (!role) return '#94a3b8'; // gray-400
        role = role.toLowerCase();
        if (role.includes('admin')) return '#ef4444'; // red-500
        if (role.includes('manager')) return '#f59e0b'; // amber-500
        if (role.includes('development')) return '#3b82f6'; // blue-500
        if (role.includes('design')) return '#ec4899'; // pink-500
        return '#64748b'; // slate-500
    }
</script>

<?php endLayout(); ?>