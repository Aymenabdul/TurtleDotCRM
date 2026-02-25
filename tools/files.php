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
    if (!in_array('files', $teamTools)) {
        die("This tool is not enabled for this team.");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

startLayout("File Manager - " . $team['name'], $user);
?>

<!-- Premium Files Dashboard -->
<div class="word-dashboard fade-in">

    <!-- Hero Section -->
    <div class="word-hero mb-4">
        <div class="flex-between align-end">
            <div>
                <?php $is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin'; ?>
                <a href="<?php echo $is_admin ? '/admin_dashboard.php' : '/index.php'; ?>" class="crumb-link mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <h1 class="page-title">File Manager</h1>
                <p class="page-subtitle">Centralized storage for <?php echo htmlspecialchars($team['name']); ?></p>
            </div>
            <div>
                <input type="file" id="fileInput" style="display: none;" onchange="handleFileUpload(event)">
                <button class="btn btn-primary btn-lg shine-effect"
                    onclick="document.getElementById('fileInput').click()">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Upload File</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="word-content-area">

        <!-- Controls Bar -->
        <div class="controls-bar card mb-4">
            <div class="flex-gap align-center" style="width: 100%;">
                <div class="search-wrapper" style="flex:1; max-width:400px;">
                    <i class="fa-solid fa-search search-icon"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search files..."
                        onkeyup="renderFiles()">
                </div>
                <!-- Optional Filters could go here -->
                <div style="flex:1;"></div>
                <div class="text-muted text-sm" id="storageStats">
                    <i class="fa-solid fa-hard-drive"></i> Storage Usage: Calculating...
                </div>
            </div>
        </div>

        <!-- Files Grid -->
        <div class="files-wrapper card" style="min-height: 500px;">
            <div class="file-grid" id="fileList">
                <!-- Files loaded via JS -->
            </div>
        </div>
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
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        pointer-events: none;
    }

    .search-input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.8rem;
        border: 1px solid var(--border-color);
        border-radius: 9999px;
        background: var(--bg-secondary);
        outline: none;
        transition: 0.2s;
    }

    .search-input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    /* File Grid */
    .files-wrapper {
        background: white;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        padding: 1.5rem;
    }

    .file-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1.5rem;
    }

    .file-card {
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        cursor: pointer;
    }

    .file-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-light);
    }

    .file-icon-wrapper {
        width: 80px;
        height: 80px;
        background: #f8fafc;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 2.5rem;
        color: var(--primary);
        transition: 0.2s;
    }

    .file-card:hover .file-icon-wrapper {
        background: #eff6ff;
        color: var(--primary-dark);
    }

    .file-name {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--text-main);
        margin-bottom: 0.25rem;
        width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .file-meta {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-bottom: 1rem;
    }

    .file-actions-overlay {
        position: absolute;
        top: 1rem;
        right: 1rem;
        z-index: 10;
    }

    .btn-icon-xs {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        background: white;
        border: 1px solid var(--border-color);
        transition: 0.2s;
        opacity: 0;
        pointer-events: none;
        cursor: pointer;
    }

    .file-card:hover .btn-icon-xs {
        opacity: 1;
        pointer-events: auto;
    }

    .btn-icon-xs:hover {
        color: var(--error);
        border-color: var(--error-light);
        background: #fef2f2;
    }

    .btn-download {
        background: var(--bg-secondary);
        color: var(--text-main);
        border: none;
        padding: 0.6rem 1rem;
        border-radius: 8px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        font-weight: 500;
        text-decoration: none;
        transition: 0.2s;
    }

    .btn-download:hover {
        background: var(--bg-tertiary);
        color: var(--primary);
    }
</style>

<script>
    const teamId = <?php echo $teamId; ?>;
    let files = [];

    // Toast Shim
    const Toast = {
        success: (title, msg) => showAlert(msg, 'success'),
        error: (title, msg) => showAlert(msg, 'error')
    };

    async function loadFiles() {
        try {
            const response = await fetch(`/api/files.php?team_id=${teamId}`);
            const result = await response.json();
            if (result.success) {
                files = result.data;
                renderFiles();
                updateStats();
            }
        } catch (ignore) { }
    }

    function updateStats() {
        const totalBytes = files.reduce((acc, f) => acc + parseInt(f.file_size || 0), 0);
        const statsEl = document.getElementById('storageStats');
        if (statsEl) statsEl.innerHTML = `<i class="fa-solid fa-hard-drive"></i> Storage Usage: ${formatSize(totalBytes)}`;
    }

    function getFileIcon(type) {
        if (!type) return 'fa-file';
        if (type.includes('pdf')) return 'fa-file-pdf';
        if (type.includes('image')) return 'fa-file-image';
        if (type.includes('word')) return 'fa-file-word';
        if (type.includes('excel') || type.includes('sheet')) return 'fa-file-excel';
        if (type.includes('zip') || type.includes('rar')) return 'fa-file-zipper';
        return 'fa-file';
    }

    function getFileColor(type) {
        if (!type) return '#64748b';
        if (type.includes('pdf')) return '#ef4444';
        if (type.includes('image')) return '#8b5cf6';
        if (type.includes('word')) return '#3b82f6';
        if (type.includes('excel') || type.includes('sheet')) return '#22c55e';
        if (type.includes('zip') || type.includes('rar')) return '#eab308';
        return '#64748b';
    }

    function formatSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function renderFiles() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const filtered = files.filter(f => f.file_name.toLowerCase().includes(query));

        const list = document.getElementById('fileList');

        if (filtered.length === 0) {
            list.innerHTML = `
                <div style="grid-column: 1/-1; display:flex; flex-direction:column; align-items:center; padding:3rem; color:var(--text-muted);">
                    <div style="width:64px; height:64px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:1rem;">
                        <i class="fa-solid fa-folder-open" style="font-size:1.5rem;"></i>
                    </div>
                    <p>No files found.</p>
                </div>`;
            return;
        }

        list.innerHTML = filtered.map(file => {
            const iconClass = getFileIcon(file.file_type);
            const iconColor = getFileColor(file.file_type);

            return `
            <div class="file-card">
                <div class="file-actions-overlay">
                    <button class="btn-icon-xs" onclick="deleteFile(${file.id})" title="Delete File">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
                
                <div class="file-icon-wrapper" style="color: ${iconColor}">
                    <i class="fa-solid ${iconClass}"></i>
                </div>
                
                <div class="file-name" title="${file.file_name}">${file.file_name}</div>
                <div class="file-meta">${formatSize(file.file_size)} • ${new Date(file.created_at).toLocaleDateString()}</div>
                
                <a href="/${file.file_path}" download="${file.file_name}" class="btn-download">
                    <i class="fa-solid fa-download"></i> Download
                </a>
            </div>
        `}).join('');
    }

    async function handleFileUpload(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Visual feedback
        const btn = document.querySelector('.btn-primary');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span><i class="fa-solid fa-spinner fa-spin"></i> Uploading...</span>';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('team_id', teamId);

        try {
            const response = await fetch('/api/files.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                Toast.success('Uploaded', 'File uploaded successfully');
                loadFiles();
            } else {
                Toast.error('Upload Failed', result.message);
            }
        } catch (error) {
            Toast.error('Error', 'System error during upload');
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    }

    async function deleteFile(id) {
        Confirm.show({
            title: 'Delete File?',
            message: 'This will permanently remove the file. Are you sure?',
            type: 'danger',
            confirmText: 'Delete Forever',
            onConfirm: async () => {
                try {
                    const response = await fetch('/api/files.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    const result = await response.json();
                    if (result.success) {
                        Toast.success('Deleted', 'File removed permanently');
                        loadFiles();
                    }
                } catch (ignore) { }
            }
        });
    }

    loadFiles();
</script>

<?php endLayout(); ?>