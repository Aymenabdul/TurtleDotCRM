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

<style>
    /* Scoped Styles for Sheet Dashboard */
    .sheet-dashboard {
        font-family: 'Inter', sans-serif;
        padding-bottom: 4rem;
    }

    /* Hero */
    .sheet-hero {
        margin-bottom: 2.5rem;
    }

    .crumb-link {
        color: var(--text-muted);
        text-decoration: none;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: color 0.2s;
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
        transition: 0.5s;
    }

    /* Stats Grid */
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
        transition: transform 0.2s, box-shadow 0.2s;
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

    /* Utility Helpers */
    .bg-green-100 {
        background: #dcfce7;
    }

    .text-green-600 {
        color: #166534;
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

    /* Controls Bar */
    .controls-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
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
        transition: all 0.2s;
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

    /* Modern Table */
    .modern-table-container {
        overflow: hidden;
        border: 1px solid var(--border-color);
        padding: 0;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
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

    .modern-table tbody tr {
        transition: background 0.1s ease;
    }

    .modern-table tbody tr:hover {
        background: #f8fafc;
    }

    .modern-table tr:last-child td {
        border-bottom: none;
    }

    /* Columns */
    .doc-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .doc-icon {
        width: 44px;
        height: 44px;
        background: var(--primary-bg);
        color: var(--primary);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
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

    .user-chip {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        background: #fdf2f8;
        /* default light pinkish/orange */
        color: #db2777;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: 0.2s;
        border: 1px solid transparent;
    }

    .user-chip:hover {
        background: #fce7f3;
        border-color: #fbcfe8;
        transform: translateY(-1px);
    }

    .user-pill {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #e0e7ff;
        color: #4338ca;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
        border: 2px solid white;
        box-shadow: 0 0 0 1px var(--border-color);
    }

    .user-text {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-main);
    }

    /* Actions */
    .actions-cell {
        display: flex;
        justify-content: flex-start;
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

    .empty-state h3 {
        font-size: 1.4rem;
        margin-bottom: 0.5rem;
        color: var(--text-main);
        font-weight: 700;
    }

    .empty-state p {
        color: var(--text-muted);
        margin-bottom: 2rem;
        font-size: 1rem;
    }

    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2.5rem;
    }

    .pg-btn {
        width: 40px;
        height: 40px;
        border: 1px solid var(--border-color);
        background: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-muted);
        transition: 0.2s;
        font-weight: 600;
    }

    .pg-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-bg);
    }

    .pg-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);
    }

    /* --- Modern Clean Theme (Screenshot Implementation) --- */
    .share-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        animation: fadeIn 0.3s ease-out;
    }

    .share-modal {
        background: #ffffff;
        width: 720px;
        /* Increased width */
        max-width: 95vw;
        border-radius: 32px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        font-family: 'Inter', sans-serif;
    }

    @keyframes scaleIn {
        from {
            transform: scale(0.95);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* Header */
    .share-header {
        padding: 32px 32px 20px;
        background: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .share-header h3 {
        margin: 0;
        font-size: 1.75rem;
        /* Larger, bolder */
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.03em;
    }

    .close-btn {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.2s;
        font-size: 1.1rem;
    }

    .close-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    /* Search Input */
    .search-container {
        position: relative;
        width: 100%;
        padding: 0 32px;
        margin-bottom: 20px;
    }

    .search-container input {
        width: 100%;
        padding: 14px 20px 14px 50px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        /* Rounded but slightly squared pill */
        font-size: 1rem;
        color: #334151;
        transition: 0.2s;
    }

    .search-container input:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        /* Soft glow */
        outline: none;
    }

    .search-container i {
        position: absolute;
        left: 52px;
        /* Adjusted for padding */
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1.1rem;
        pointer-events: none;
    }

    /* Tabs (Pill Style) */
    .team-tabs {
        display: flex;
        padding: 0 32px;
        gap: 24px;
        /* Increased gap */
        margin-bottom: 24px;
        overflow-x: auto;
        scrollbar-width: none;
        align-items: center;
        flex-wrap: nowrap;
        /* Ensure they stay in a row */
        border-bottom: 1px solid #e5e7eb;
    }

    .team-tabs::-webkit-scrollbar {
        display: none;
    }

    .team-tab {
        padding: 12px 4px;
        /* Vertical padding */
        font-size: 0.95rem;
        font-weight: 600;
        color: #64748b;
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        cursor: pointer;
        transition: 0.2s;
        white-space: nowrap;
        margin-bottom: -1px;
    }

    .team-tab:hover {
        color: #0f172a;
        background: transparent;
    }

    .team-tab.active {
        background: transparent;
        color: #10b981;
        /* Green text */
        border-bottom-color: #10b981;
        /* Green underline */
        box-shadow: none;
    }

    /* List Layout */
    /* List Layout */
    .assignee-list {
        display: grid;
        /* Changed to grid */
        grid-template-columns: 1fr 1fr;
        /* Two columns */
        gap: 12px;
        padding: 0 32px 24px;
        overflow-y: auto;
        max-height: 320px;
        /* Reduced height */
    }

    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* User Row (Card Style) */
    .user-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        /* Highly rounded cards */
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .user-row:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }

    /* Selected State - Outline Style */
    .user-row.selected {
        background: #f0fdf4;
        /* Very light green bg */
        border: 2px solid #10b981;
        /* Bold Green Border */
        border-color: #10b981;
        padding: 11px;
        /* Compensate for 2px border if normal is 1px */
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.1);
    }

    .user-checkbox {
        width: 20px;
        height: 20px;
        border-radius: 6px;
        background: #ffffff;
        border: 2px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0;
        transition: 0.2s;
        color: white;
        flex-shrink: 0;
        font-size: 0.8rem;
    }

    .user-row.selected .user-checkbox {
        background: #10b981;
        /* Solid Green */
        border-color: #10b981;
    }

    /* Avatar - Rounded Square */
    .user-avatar-small {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        /* Circle Avatar */
        background: #f1f5f9;
        color: #1f2937;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .user-row.selected .user-avatar-small {
        background: #ffffff;
        color: #15803d;
    }

    .user-info {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        /* Push role to the right */
        align-items: center;
        gap: 12px;
        flex: 1;
        overflow: hidden;
    }

    .user-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: #1f2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-row.selected .user-name {
        color: #064e3b;
    }

    .user-role {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 99px;
        /* Pill shape */
        background: #f1f5f9;
        white-space: nowrap;
    }

    .user-row.selected .user-role {
        background: #ffffff;
        color: #15803d;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        /* Subtle lift */
    }

    /* Footer */
    .share-footer {
        padding: 20px 32px;
        background: #fff;
        border-top: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .selection-count {
        font-size: 0.95rem;
        font-weight: 600;
        color: #64748b;
    }

    .share-footer .btn-primary {
        background: #0f172a;
        /* Premium Dark Navy/Black */
        color: white;
        width: auto;
        min-width: 160px;
        padding: 0 24px;
        height: 48px;
        /* Tall button */
        justify-content: center;
        font-size: 1rem;
        border-radius: 14px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
    }

    .share-footer .btn-primary:hover {
        background: #1e293b;
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
    }

    .share-footer .btn-primary:active {
        transform: scale(0.98);
    }

    /* View Assignments Styles */
    /* View Assignments Styles - Clean List */
    .view-user-list {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: 0 24px 24px;
        max-height: 450px;
        overflow-y: auto;
    }

    .view-user-card {
        display: flex;
        align-items: center;
        padding: 16px 8px;
        border-bottom: 1px solid #f1f5f9;
        background: transparent;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        gap: 16px;
    }

    .view-user-card:last-child {
        border-bottom: none;
    }

    .view-user-card:hover {
        background: #f8fafc;
        padding-left: 16px;
        padding-right: 16px;
        border-radius: 12px;
        border-bottom-color: transparent;
    }

    .view-card-avatar-ring {
        padding: 3px;
        border: 2px solid var(--role-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
    }

    .view-user-card:hover .view-card-avatar-ring {
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
</style>

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