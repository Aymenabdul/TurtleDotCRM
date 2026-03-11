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
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

startLayout("Archive Vault - " . $team['name'], $user);
?>

<link rel="stylesheet" href="/css/files.css">

<div class="word-dashboard fade-in">
    <div class="word-hero mb-5">
        <div class="hero-header">
            <div>
                <?php $is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin'; ?>
                <a href="<?php echo $is_admin ? '/admin_dashboard.php' : '/index.php'; ?>" class="crumb-link mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <h1 class="page-title">Archive Vault</h1>
                <p class="page-subtitle">Premium file engine for <?php echo htmlspecialchars($team['name']); ?></p>
            </div>
            <div class="header-actions">
                <button id="uploadBtn" class="btn btn-primary shine-effect" onclick="openUploadOptions()" style="display: none; border-radius: 16px; height: 56px; padding: 0 2rem; font-weight: 800; gap: 12px; font-size: 1rem; box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Upload & Organize</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="word-content-area">
        <div class="controls-bar">
            <div class="controls-wrapper">
                <div class="controls-row primary-row">
                    <button class="btn-refresh-vault" onclick="location.reload()" title="Refresh Vault">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </button>
                    <div class="search-wrapper">
                        <i class="fa-solid fa-search" style="position: absolute; left: 1.5rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Search entries..." onkeyup="renderExplorer()">
                    </div>
                </div>
                <div class="controls-row secondary-row">
                    <div id="breadcrumb" class="breadcrumb-nav">
                        <!-- Dynamic Breadcrumbs -->
                    </div>
                    <div id="storageStats" class="storage-stats-badge">
                        <i class="fa-solid fa-database"></i> Calculating...
                    </div>
                </div>
            </div>
        </div>

        <!-- File Explorer -->
        <div class="explorer-container" style="position: relative;">
            <div id="selectionBar" class="selection-bar">
                <div class="selection-info">
                    <span id="selectionCountDisplay">0 items selected</span>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-secondary btn-sm" onclick="clearSelection()" style="border-radius: 10px; font-weight: 700;">Deselect All</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteSelected()" style="background: #ef4444; color: white; border: none; border-radius: 10px; font-weight: 800; padding: 0.5rem 1.5rem;">
                        <i class="fa-regular fa-trash-can" style="margin-right: 6px;"></i> Wipe Selection
                    </button>
                </div>
            </div>
            <div id="explorerGrid" class="explorer-grid">
                <!-- Folders and Files loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Upload Options Modal -->
<div id="uploadOptionsModal" class="share-modal-overlay">
    <div class="share-modal modal-wide">
        <div class="share-header" style="background: white; border: none; padding: 2rem 2.5rem 1rem;">
            <div>
                <h3 style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin-bottom: 0.25rem;">Storage Selection</h3>
                <p style="color: #94a3b8; font-weight: 600; font-size: 0.95rem;">Choose how to synchronize your professional assets</p>
            </div>
            <button class="close-btn" onclick="closeUploadOptions()" style="width: 44px; height: 44px; border-radius: 14px;"><i class="fa-solid fa-xmark" style="font-size: 1.25rem;"></i></button>
        </div>
        <div class="share-body" style="padding: 2.5rem;">
            <div class="upload-options-grid">
                <div class="premium-upload-card" onclick="startNewFolderUpload()">
                    <div class="card-icon-box" style="--bg: #ecfdf5; --color: #10b981;">
                        <i class="fa-solid fa-folder-plus"></i>
                    </div>
                    <div class="card-content">
                        <h4>New Collection</h4>
                        <p>Establish a secure shared storage container</p>
                    </div>
                    <div class="card-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>

                <div class="premium-upload-card" onclick="triggerDirectUpload()">
                    <div class="card-icon-box" style="--bg: #eff6ff; --color: #3b82f6;">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <div class="card-content">
                        <h4>Quick Upload</h4>
                        <p>Direct transfer to your current location</p>
                    </div>
                    <div class="card-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>

                <div class="premium-upload-card" onclick="triggerFolderSelect()">
                    <div class="card-icon-box" style="--bg: #fff7ed; --color: #f97316;">
                        <i class="fa-solid fa-folder-tree"></i>
                    </div>
                    <div class="card-content">
                        <h4>Folder Sync</h4>
                        <p>Establish entire directory trees in one go</p>
                    </div>
                    <div class="card-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create Folder -->
<div id="createFolderModal" class="share-modal-overlay">
    <div class="share-modal modal-sm">
        <div class="share-header">
            <h3><i class="fa-solid fa-folder-plus" style="color: #3b82f6; background: #eff6ff; width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;"></i> New Collection</h3>
            <button class="close-btn" onclick="closeCreateFolder()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="share-body" style="padding: 2.5rem;">
            <div class="form-group">
                <label class="form-label" style="font-weight: 800; margin-bottom: 1rem; display: block; color: #1e293b; font-size: 0.95rem; letter-spacing: -0.01em;">ORGANIZATIONAL NAME</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-signature" style="position: absolute; right: 1.5rem; top: 50%; transform: translateY(-50%); color: #cbd5e1; font-size: 1.1rem;"></i>
                    <input type="text" id="newFolderName" class="premium-input" placeholder="e.g. Q1 Marketing Assets">
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-5 btn-premium shine-effect" onclick="confirmCreateFolder()" style="height: 60px; font-weight: 900; font-size: 1.05rem; border-radius: 18px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                Create Secure Container
            </button>
        </div>
    </div>
</div>

<!-- Share / Assignment Modal -->
<div id="shareModal" class="share-modal-overlay">
    <div class="share-modal" style="width: 450px;">
        <div class="share-header">
            <h3 id="shareModalTitle">Vault Permissions</h3>
            <button class="close-btn" onclick="closeShareModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="share-body" style="padding: 1.25rem 1.5rem;">
            <div class="search-container" style="margin-bottom: 1.5rem; position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" id="assignSearch" placeholder="Search team members..." oninput="renderAssigneeList()" 
                    style="width: 100%; padding: 0.85rem 1rem 0.85rem 3.25rem; border: 1.5px solid #e2e8f0; border-radius: 99px; background: #f8fafc; outline: none; focus: border-color: #3b82f6; transition: 0.2s;">
            </div>
            <div class="team-tabs" id="teamTabs"></div>
            <div class="assignee-list custom-scrollbar" id="assigneeList" style="max-height: 240px; overflow-y: auto; padding: 0.25rem;">
                <!-- Populated via JS -->
            </div>
        </div>
        <div class="share-footer" style="padding: 1.25rem 1.5rem 1.75rem;">
            <div id="selectionCount" class="selection-count" style="font-weight: 700; color: #64748b; font-size: 0.85rem;">0 members selected</div>
            <button id="saveShareBtn" class="btn btn-primary shine-effect" onclick="saveAssignment()" style="border-radius: 12px; font-weight: 800; padding: 0.7rem 1.5rem; font-size: 0.9rem;">
                Apply Access
            </button>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="share-modal-overlay">
    <div class="share-modal modal-full">
        <div class="share-header">
            <h3 id="previewTitle">Asset Preview</h3>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button id="editorEditBtn" class="btn btn-primary" style="display: none; border-radius: 10px; font-weight: 800; padding: 0.4rem 1rem; font-size: 0.8rem;" onclick="enterEditMode()">
                    <i class="fa-solid fa-pen-to-square" style="margin-right: 6px;"></i> <span>Modify Asset</span>
                </button>
                <button id="editorSaveBtn" class="btn btn-emerald" style="display: none; border-radius: 10px; font-weight: 800; padding: 0.4rem 1rem; font-size: 0.8rem; background: #10b981; border: none; color: white;" onclick="saveActiveFile()">
                    <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i> <span>Commit Changes</span>
                </button>
                <a id="previewDownload" href="#" download class="btn-action-sm" title="Download Asset"><i class="fa-solid fa-download"></i></a>
                <button class="close-btn" onclick="closePreview()"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div id="previewBody" class="share-body" style="flex: 1; padding: 0; background: #0f172a; overflow: auto; display: flex; flex-direction: column; position: relative;">
            <!-- Content loaded via JS -->
        </div>
    </div>
</div>

<!-- Intelligence Modal -->
<div id="intelModal" class="share-modal-overlay">
    <div class="share-modal modal-sm">
        <div class="share-header">
            <h3>Vault Intelligence</h3>
            <button class="close-btn" onclick="closeIntel()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="share-body" style="padding: 2rem;">
            <div id="intelContent">
                <!-- Content via JS -->
            </div>
            <button class="btn btn-secondary w-100 mt-4" onclick="closeIntel()" style="border-radius: 12px; font-weight: 700;">Dismiss Details</button>
        </div>
    </div>
</div>

<input type="file" id="fileInput" style="display: none;" onchange="handleFileUpload(event)">
<input type="file" id="folderInput" style="display: none;" webkitdirectory directory onchange="handleFolderSync(event)">

<?php include __DIR__ . '/../src/components/ui/glass-confirm.php'; ?>
<?php include __DIR__ . '/../src/components/ui/glass-toast.php'; ?>



<script>
    const teamId = <?php echo (int)$teamId; ?>;
    const currentUserId = <?php echo (int)$user['user_id']; ?>;
    const isAdmin = <?php echo json_encode($is_admin); ?>;
    
    // Persistent Folder State logic
    const urlParams = new URLSearchParams(window.location.search);
    let currentFolderId = urlParams.get('folder_id') || null;

    let allFiles = [];
    let allFolders = [];
    let usersData = [];
    let teamsData = [];
    let currentAssignedTo = {};
    let activeAssignId = null;
    let activeAssignType = 'folder';
    let currentTeamTab = null;
    let activePreviewId = null;

    let selectedFolders = new Set();
    let selectedFiles = new Set();

    async function fetchData() {
        console.log("Vault: Synchronizing data...");
        try {
            const fRes = await fetch(`/api/folders.php?team_id=${teamId}&v=${Date.now()}`);
            const dRes = await fetch(`/api/files.php?team_id=${teamId}&v=${Date.now()}`);
            
            if (!fRes.ok || !dRes.ok) throw new Error("Vault API error");
            
            const foldersResult = await fRes.json();
            const filesResult = await dRes.json();

            if (foldersResult.success) allFolders = foldersResult.data;
            if (filesResult.success) allFiles = filesResult.data;

            updateStats();
            renderExplorer();
            renderBreadcrumb();
        } catch (e) {
            console.error(e);
            if (window.Toast) Toast.error("Vault Disconnected", "Failed to sync with secure storage.");
        }
    }
    window.loadVault = fetchData; // Global exposure for real-time sync

    function renderExplorer() {
        const grid = document.getElementById('explorerGrid');
        const query = document.getElementById('searchInput').value.toLowerCase();
        console.log(`Vault: Rendering explorer. Total folders: ${allFolders.length}, Total files: ${allFiles.length}`);
        
        const filteredFolders = allFolders.filter(f => {
            if (query) return f.name.toLowerCase().includes(query);
            return f.parent_id == currentFolderId; 
        });

        const filteredFiles = allFiles.filter(f => {
            if (query) return f.file_name.toLowerCase().includes(query);
            return f.folder_id == currentFolderId;
        });

        const currentFolder = allFolders.find(f => f.id == currentFolderId);
        const currentRole = isAdmin ? 'admin' : (currentFolder ? currentFolder.effective_role : 'owner');
        const canUpload = isAdmin || (currentRole === 'owner' || currentRole === 'edit');
        
        document.getElementById('uploadBtn').style.display = canUpload ? 'flex' : 'none';

        if (filteredFolders.length === 0 && filteredFiles.length === 0) {
            grid.innerHTML = `<div style="grid-column:1/-1; padding:8rem 4rem; text-align:center; color:#94a3b8;"><i class="fa-solid fa-folder-open" style="font-size:4rem; margin-bottom:1.5rem; opacity:0.1;"></i><p style="font-weight:700; font-size:1.25rem; color:#64748b;">Vault is empty or no content found.</p></div>`;
            return;
        }

        let html = '';
        filteredFolders.forEach(folder => {
            const isSelected = selectedFolders.has(Number(folder.id));
            const role = folder.effective_role;
            const canManage = isAdmin || role === 'owner';
            
            let assignedCount = 0;
            try {
                const parsed = JSON.parse(folder.assigned_to);
                if (parsed) {
                    if (Array.isArray(parsed)) assignedCount = parsed.length;
                    else assignedCount = Object.keys(parsed).length;
                }
            } catch(e) {}

            const badgeHtml = assignedCount > 0 ? `<div class="item-badge">${assignedCount}</div>` : '';
            const assignBtn = (!currentFolderId && canManage) ? `
                <button class="btn-action-sm" title="Permissions" onclick="openShareModal(${folder.id}, 'folder')">
                    ${badgeHtml}
                    <i class="fa-solid fa-user-plus"></i>
                </button>` : '';

            const downloadBtn = `<a href="/api/download_folder.php?id=${folder.id}" class="btn-action-sm" title="Download Collection"><i class="fa-solid fa-download"></i></a>`;
            const intelBtn = `<button class="btn-action-sm" title="Folder Intelligence" onclick="showIntel(${folder.id}, 'folder')"><i class="fa-solid fa-circle-info"></i></button>`;

            const canEdit = isAdmin || role === 'owner' || role === 'edit';

            const deleteBtn = canEdit ? `
                <button class="btn-action-sm delete" title="Wipe Folder Recursively" onclick="deleteFolder(${folder.id})"><i class="fa-regular fa-trash-can"></i></button>
            ` : '';

            html += `
                <div class="item-card folder ${isSelected ? 'selected' : ''}" onclick="enterFolder(${folder.id})">
                    ${canEdit ? `
                    <div class="select-toggle" onclick="toggleSelection(event, ${folder.id}, 'folder')">
                        <i class="fa-solid fa-check"></i>
                    </div>` : ''}
                    <div class="item-actions" onclick="event.stopPropagation()">
                        ${intelBtn}
                        ${assignBtn}
                        ${downloadBtn}
                        ${deleteBtn}
                    </div>
                    <div class="item-icon-wrapper">
                        <i class="fa-solid fa-folder"></i>
                    </div>
                    <div class="item-name">${folder.name}</div>
                    <div class="item-meta">Storage Container</div>
                </div>
            `;
        });

        filteredFiles.forEach(file => {
            const isSelected = selectedFiles.has(Number(file.id));
            const role = file.effective_role;
            const canEdit = isAdmin || role === 'owner' || role === 'edit';
            const canDelete = canEdit;
            const canSelect = canEdit;

            html += `
                <div class="item-card file ${isSelected ? 'selected' : ''}" onclick="previewFile(${file.id})">
                    ${canSelect ? `
                    <div class="select-toggle" onclick="toggleSelection(event, ${file.id}, 'file')">
                        <i class="fa-solid fa-check"></i>
                    </div>` : ''}
                    <div class="item-actions" onclick="event.stopPropagation()">
                        ${(!currentFolderId && canManage) ? `
                        <button class="btn-action-sm" title="Permissions" onclick="openShareModal(${file.id}, 'file')">
                            <i class="fa-solid fa-user-plus"></i>
                        </button>` : ''}
                        <button class="btn-action-sm" title="Asset Intelligence" onclick="showIntel(${file.id}, 'file')"><i class="fa-solid fa-circle-info"></i></button>
                        <a href="/${file.file_path}" download="${file.file_name}" class="btn-action-sm" title="Download Asset"><i class="fa-solid fa-download"></i></a>
                        ${canDelete ? `
                            <button class="btn-action-sm delete" title="Wipe Asset" onclick="deleteFile(${file.id})"><i class="fa-regular fa-trash-can"></i></button>
                        ` : ''}
                    </div>
                    <div class="item-icon-wrapper">
                        <i class="fa-solid ${getFileIcon(file.file_type)}"></i>
                    </div>
                    <div class="item-name" title="${file.file_name}">${file.file_name}</div>
                    <div class="item-meta">${formatSize(file.file_size)} • ${new Date(file.created_at).toLocaleDateString()}</div>
                </div>
            `;
        });
        grid.innerHTML = html;
        updateSelectionBar();
    }

    function toggleSelection(event, id, type) {
        event.stopPropagation();
        const nid = Number(id);
        if (type === 'folder') {
            if (selectedFolders.has(nid)) selectedFolders.delete(nid);
            else selectedFolders.add(nid);
        } else {
            if (selectedFiles.has(nid)) selectedFiles.delete(nid);
            else selectedFiles.add(nid);
        }
        renderExplorer();
    }

    function updateSelectionBar() {
        const total = selectedFolders.size + selectedFiles.size;
        const bar = document.getElementById('selectionBar');
        const countDisplay = document.getElementById('selectionCountDisplay');
        
        if (total > 0) {
            bar.classList.add('active');
            countDisplay.textContent = `${total} item${total > 1 ? 's' : ''} selected`;
        } else {
            bar.classList.remove('active');
        }
    }

    function clearSelection() {
        selectedFolders.clear();
        selectedFiles.clear();
        renderExplorer();
    }

    async function deleteSelected() {
        const folderIds = Array.from(selectedFolders);
        const fileIds = Array.from(selectedFiles);
        const total = folderIds.length + fileIds.length;

        custompopup({
            title: `Wipe ${total} items?`,
            message: `You are about to recursively delete ${total} selected assets and folders. This action is irreversible and will remove all nested content within folders.`,
            type: 'danger',
            confirmText: 'Verify Mass Wipe',
            onConfirm: async () => {
                Toast.info("Deep Cleanup", "Recursively purging assets from vault...");
                try {
                    let success = true;
                    if (folderIds.length > 0) {
                        const res = await fetch('/api/folders.php', {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ ids: folderIds })
                        });
                        const result = await res.json();
                        if (!result.success) {
                            success = false;
                            Toast.warn("Hierarchy Restriction", result.message || "You don't have permission to wipe some selected assets.");
                        }
                    }
                    if (fileIds.length > 0) {
                        const res = await fetch('/api/files.php', {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ ids: fileIds })
                        });
                        const result = await res.json();
                        if (!result.success) {
                            success = false;
                            Toast.warn("Hierarchy Restriction", result.message || "One or more files have inherited protection.");
                        }
                    }

                    if (success) {
                        Toast.success("Vault Cleansed", "Selected items and their children were successfully removed.");
                        clearSelection();
                        setTimeout(fetchData, 200);
                    }
                } catch (e) { Toast.error("Sync Error", "Mass wipe command failed."); }
            }
        });
    }

    function enterFolder(id, pushState = true) { 
        currentFolderId = id; 
        
        if (pushState) {
            const url = new URL(window.location);
            if (id) url.searchParams.set('folder_id', id);
            else url.searchParams.delete('folder_id');
            window.history.pushState({}, '', url);
        }

        renderExplorer(); 
        renderBreadcrumb(); 
    }

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        currentFolderId = params.get('folder_id') || null;
        renderExplorer();
        renderBreadcrumb();
    });
    
    /* --- File Preview --- */
    function previewFile(id) {
        const file = allFiles.find(f => f.id == id);
        if(!file) return;

        const modal = document.getElementById('previewModal');
        const body = document.getElementById('previewBody');
        const title = document.getElementById('previewTitle');
        const dl = document.getElementById('previewDownload');
        const saveBtn = document.getElementById('editorSaveBtn');
        const editBtn = document.getElementById('editorEditBtn');

        activePreviewId = id;
        title.textContent = file.file_name;
        dl.href = '/' + file.file_path;
        dl.download = file.file_name;
        
        // Check permissions for editing
        const canEdit = isAdmin || (file.effective_role === 'owner' || file.effective_role === 'edit');
        saveBtn.style.display = 'none'; 
        editBtn.style.display = 'none';

        body.innerHTML = '<div style="color:white; font-weight:700;"><i class="fa-solid fa-circle-notch fa-spin"></i> Initializing Environment...</div>';
        modal.classList.add('active');

        const type = (file.file_type || '').toLowerCase();
        const url = '/' + file.file_path;

        if (type.includes('image')) {
            body.style.justifyContent = 'center';
            body.style.alignItems = 'center';
            body.innerHTML = `<img src="${url}" style="max-width:100%; max-height:100%; object-fit:contain; animation: zoomIn 0.4s ease;">`;
        } else if (type.includes('pdf')) {
            body.style.display = 'block';
            body.innerHTML = `<iframe src="${url}" style="width:100%; height:100%; border:none;"></iframe>`;
        } else if (type.includes('video')) {
            body.style.justifyContent = 'center';
            body.style.alignItems = 'center';
            body.innerHTML = `<video controls autoplay style="max-width:100%; max-height:100%;"><source src="${url}" type="${file.file_type}"></video>`;
        } else if (type.includes('text') || type.includes('json') || type.includes('php') || type.includes('js') || file.file_name.endsWith('.php') || file.file_name.endsWith('.js') || file.file_name.endsWith('.css') || file.file_name.endsWith('.html')) {
            body.style.justifyContent = 'flex-start';
            body.style.alignItems = 'stretch';
            if (canEdit) editBtn.style.display = 'flex';
            
            fetch(`/api/files.php?read=1&id=${id}&v=${Date.now()}`).then(r => r.text()).then(txt => {
                const lines = txt.split('\n');
                const lineNums = lines.map((_, i) => i + 1).join('<br>');
                
                body.innerHTML = `
                    <div class="editor-wrapper">
                        <div class="editor-gutter" id="editorLineNums">${lineNums}</div>
                        <div class="editor-main" contenteditable="false" id="editorInput" spellcheck="false" oninput="updateLineNumbers()">${escapeHtml(txt)}</div>
                    </div>
                `;
            });
        } else {
            body.style.justifyContent = 'center';
            body.style.alignItems = 'center';
            body.innerHTML = `
                <div style="text-align:center; color:white;">
                    <i class="fa-solid fa-file-circle-exclamation" style="font-size:4rem; margin-bottom:1rem; opacity:0.5;"></i>
                    <p style="font-weight:700; font-size:1.2rem;">Preview Unavailable</p>
                    <p style="color:#94a3b8; margin-bottom:2rem;">This file type cannot be rendered directly in the vault.</p>
                    <a href="${url}" download class="btn btn-primary" style="padding:0.75rem 2rem; border-radius:12px; font-weight:800;">Download Asset</a>
                </div>
            `;
        }
    }

    function closePreview() { 
        document.getElementById('previewModal').classList.remove('active'); 
        document.getElementById('previewBody').innerHTML = '';
        document.getElementById('editorSaveBtn').style.display = 'none';
        document.getElementById('editorEditBtn').style.display = 'none';
        activePreviewId = null;
    }

    function enterEditMode() {
        const input = document.getElementById('editorInput');
        if (!input) return;
        input.contentEditable = "true";
        input.focus();
        document.getElementById('editorEditBtn').style.display = 'none';
        document.getElementById('editorSaveBtn').style.display = 'flex';
        Toast.info("Editor Unlocked", "You are now in live edit mode.");
    }

    function updateLineNumbers() {
        const input = document.getElementById('editorInput');
        const gutter = document.getElementById('editorLineNums');
        if (!input || !gutter) return;
        const lines = input.innerText.split('\n');
        gutter.innerHTML = lines.map((_, i) => i + 1).join('<br>');
    }

    async function saveActiveFile() {
        const input = document.getElementById('editorInput');
        if (!input || !activePreviewId) return;

        const content = input.innerText;
        const btn = document.getElementById('editorSaveBtn');
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...';
        btn.disabled = true;

        try {
            const res = await fetch('/api/files.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: activePreviewId, content: content })
            });
            const result = await res.json();
            if (result.success) {
                Toast.success("Asset Committed", "Changes synchronized to the vault successfully.");
                document.getElementById('editorSaveBtn').style.display = 'none';
                document.getElementById('editorEditBtn').style.display = 'flex';
                document.getElementById('editorInput').contentEditable = "false";
            } else {
                Toast.error("Update Blocked", result.message || "Could not save changes.");
            }
        } catch (e) {
            Toast.error("Sync Error", "Failed to reach vault server.");
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /* --- Intel System --- */
    function showIntel(id, type) {
        const content = document.getElementById('intelContent');
        let html = '';

        if (type === 'file') {
            const file = allFiles.find(f => f.id == id);
            if (!file) return;
            html = `
                <div class="intel-box">
                    <div class="intel-item">
                        <span class="intel-label">Asset Name</span>
                        <span class="intel-value">${file.file_name}</span>
                    </div>
                    <div class="intel-item">
                        <span class="intel-label">Uploaded By</span>
                        <span class="intel-value" style="color:#3b82f6;">${file.uploader_name || 'System'}</span>
                    </div>
                    <div class="intel-item">
                        <span class="intel-label">Stored On</span>
                        <span class="intel-value">${new Date(file.created_at).toLocaleString()}</span>
                    </div>
                    <div class="intel-item">
                        <span class="intel-label">Original Size</span>
                        <span class="intel-value">${formatSize(file.file_size)}</span>
                    </div>
                </div>
            `;
        } else {
            const folder = allFolders.find(f => f.id == id);
            if (!folder) return;
            
            // Find last activity in folder
            const folderFiles = allFiles.filter(f => f.folder_id == id).sort((a,b) => new Date(b.created_at) - new Date(a.created_at));
            const lastFile = folderFiles[0];

            html = `
                <div class="intel-box">
                    <div class="intel-item">
                        <span class="intel-label">Collection</span>
                        <span class="intel-value">${folder.name}</span>
                    </div>
                    <div class="intel-item">
                        <span class="intel-label">Established By</span>
                        <span class="intel-value" style="color:#f59e0b;">${folder.creator_name || 'System'}</span>
                    </div>
                    <div class="intel-item">
                        <span class="intel-label">Created At</span>
                        <span class="intel-value">${new Date(folder.created_at).toLocaleString()}</span>
                    </div>
                    <hr style="border:none; border-top:1px solid #f1f5f9; margin:1rem 0;">
                    <div class="intel-item">
                        <span class="intel-label">Last Synchronization</span>
                        <span class="intel-value">${lastFile ? new Date(lastFile.created_at).toLocaleString() : 'No Assets Stored'}</span>
                    </div>
                    ${lastFile ? `
                    <div class="intel-item">
                        <span class="intel-label">Last Asset Added</span>
                        <span class="intel-value" style="font-weight:800; color:#1e293b;">${lastFile.file_name}</span>
                    </div>
                    <div class="intel-item">
                        <span class="intel-label">Added By</span>
                        <span class="intel-value">${lastFile.uploader_name || 'System'}</span>
                    </div>
                    ` : ''}
                </div>
            `;
        }

        content.innerHTML = html;
        document.getElementById('intelModal').classList.add('active');
    }

    function closeIntel() { document.getElementById('intelModal').classList.remove('active'); }

    function renderBreadcrumb() {
        const nav = document.getElementById('breadcrumb');
        let html = `<span class="breadcrumb-item ${!currentFolderId?'active':''}" onclick="enterFolder(null)"><i class="fa-solid fa-database" style="color: #64748b;"></i> Vault Root</span>`;
        
        if (currentFolderId) {
            let path = [];
            let curr = allFolders.find(x => x.id == currentFolderId);
            while(curr) {
                path.unshift(curr);
                curr = allFolders.find(x => x.id == curr.parent_id);
            }
            path.forEach((f, idx) => {
                html += ` <i class="fa-solid fa-chevron-right" style="font-size:0.7rem; opacity:0.3; margin: 0 2px;"></i> `;
                const isActive = idx === path.length - 1;
                html += `<span class="breadcrumb-item ${isActive?'active':''}" onclick="enterFolder(${f.id})">${f.name}</span>`;
            });
        }
        nav.innerHTML = html;
    }

    function getFileIcon(t) {
        if (!t) return 'fa-file';
        t = t.toLowerCase();
        if (t.includes('image')) return 'fa-file-image';
        if (t.includes('pdf')) return 'fa-file-pdf';
        if (t.includes('video')) return 'fa-file-video';
        if (t.includes('audio')) return 'fa-file-audio';
        if (t.includes('word')) return 'fa-file-word';
        if (t.includes('excel') || t.includes('sheet')) return 'fa-file-excel';
        if (t.includes('zip') || t.includes('rar')) return 'fa-file-zipper';
        return 'fa-file';
    }

    function formatSize(b) { if(!b) return '0 B'; const k=1024, s=['B','KB','MB','GB']; const i=Math.floor(Math.log(b)/Math.log(k)); return parseFloat((b/Math.pow(k, i)).toFixed(1)) + ' ' + s[i]; }

    function updateStats() {
        const total = allFiles.reduce((a, f)=> a + parseInt(f.file_size||0), 0);
        document.getElementById('storageStats').innerHTML = `<i class="fa-solid fa-database" style="margin-right:8px;"></i> ${formatSize(total)} Utilized`;
    }

    /* --- Actions --- */
    function openUploadOptions() { document.getElementById('uploadOptionsModal').classList.add('active'); }
    function closeUploadOptions() { document.getElementById('uploadOptionsModal').classList.remove('active'); }
    function startNewFolderUpload() { closeUploadOptions(); document.getElementById('createFolderModal').classList.add('active'); }
    function closeCreateFolder() { document.getElementById('createFolderModal').classList.remove('active'); }

    async function confirmCreateFolder() {
        const name = document.getElementById('newFolderName').value.trim();
        if(!name) return Toast.error("Invalid Name", "Please name your collection.");
        try {
            const res = await fetch('/api/folders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ team_id: teamId, name: name, parent_id: currentFolderId })
            });
            const result = await res.json();
            if (result.success) { 
                Toast.success("Collection Created", "Your new secure container is ready.");
                document.getElementById('newFolderName').value = ''; // Reset input
                closeCreateFolder(); 
                setTimeout(fetchData, 200); // Tiny delay to ensure DB commit is visible
            }
        } catch (e) { Toast.error("Sync Error", "Failed to reach vault server."); }
    }

    function triggerDirectUpload() { closeUploadOptions(); document.getElementById('fileInput').click(); }

    async function handleFileUpload(e) {
        const file = e.target.files[0];
        if(!file) return;
        const formData = new FormData();
        formData.append('file', file);
        formData.append('team_id', teamId);
        if(currentFolderId) formData.append('folder_id', currentFolderId);
        
        Toast.info("Vault Sync", "Transferring asset to secure storage...");

        try {
            const res = await fetch('/api/files.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
                Toast.success("Upload Verified", "File encrypted and stored safely.");
                setTimeout(fetchData, 200);
            }
        } catch (e) { Toast.error("Transfer Failed", "Network interrupt during upload."); }
    }

    function triggerFolderSelect() { 
        closeUploadOptions(); 
        custompopup({
            title: 'Prepare Collection Sync',
            message: 'To establish a new vault collection, select the root directory of your local assets. Your browser will ask for technical permission to transfer the directory contents recursively.',
            type: 'primary',
            icon: 'fa-folder-tree',
            confirmText: 'Select & Sync',
            onConfirm: () => {
                document.getElementById('folderInput').click();
            }
        });
    }

    async function handleFolderSync(e) {
        const files = Array.from(e.target.files);
        if(!files || files.length === 0) return;

        // Determine folder name from relative path
        const firstPath = files[0].webkitRelativePath;
        const localDirectoryName = firstPath ? firstPath.split('/')[0] : 'Uploaded Archive';

        Toast.info("Vault Sync", `Transferring ${files.length.toLocaleString()} assets to '${localDirectoryName}'...`);

        try {
            // Always create a new folder, preserving the structure
            const fRes = await fetch('/api/folders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    team_id: teamId, 
                    name: localDirectoryName, 
                    parent_id: currentFolderId 
                })
            });
            const fResult = await fRes.json();
            if (!fResult.success) throw new Error("Could not initialize container.");
            const targetFolderId = fResult.id;

            // Upload all files to the target folder
            let uploaded = 0;
            for (let file of files) {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('team_id', teamId);
                formData.append('folder_id', targetFolderId);
                
                const upRes = await fetch('/api/files.php', { method: 'POST', body: formData });
                const upResult = await upRes.json();
                if (upResult.success) uploaded++;
            }

            Toast.success("Sync Complete", `${uploaded} assets successfully transferred to the vault.`);
            setTimeout(fetchData, 200);
        } catch (e) { 
            console.error(e);
            Toast.error("Transfer Error", "Vault sync interrupted.");
        }
    }

    async function deleteFile(id) {
        custompopup({
            title: 'Wipe Asset?',
            message: 'This will physically remove the encrypted file from the vault. This action is irreversible.',
            type: 'danger',
            confirmText: 'Verify Wipe',
            onConfirm: async () => {
                try {
                    const res = await fetch('/api/files.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    if((await res.json()).success) {
                        Toast.success("Asset Wiped", "File removed from storage.");
                        setTimeout(fetchData, 200);
                    }
                } catch (e) { Toast.error("Error", "Wipe command failed."); }
            }
        });
    }

    async function deleteFolder(id) {
        custompopup({
            title: 'Delete Collection?',
            message: 'Caution: This will recursively remove the folder and EVERYTHING inside it (files and subfolders) from the secure vault. This action is final.',
            type: 'danger',
            confirmText: 'Verify Deep Delete',
            onConfirm: async () => {
                try {
                    const res = await fetch('/api/folders.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    if((await res.json()).success) {
                        Toast.success("Collection Purged", "Folder and all its contents have been wiped.");
                        if (id == currentFolderId) enterFolder(null);
                        setTimeout(fetchData, 200);
                    }
                } catch (e) { Toast.error("Error", "Command failed."); }
            }
        });
    }

    /* --- Assignment Modal Logic --- */
    function openShareModal(id, type) {
        activeAssignId = id;
        activeAssignType = type;
        
        const item = type === 'folder' 
            ? allFolders.find(f => f.id == id) 
            : allFiles.find(f => f.id == id);
            
        if (!item) return;
            
        document.getElementById('shareModalTitle').textContent = `Manage Permissions: ${type === 'folder' ? item.name : item.file_name}`;
        
        currentAssignedTo = {}; // Format: { userId: role }
        const raw = item.assigned_to;
        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                if (typeof parsed === 'object' && !Array.isArray(parsed)) {
                    currentAssignedTo = parsed;
                } else if (Array.isArray(parsed)) {
                    parsed.forEach(id => { currentAssignedTo[id] = 'view'; });
                }
            } catch (e) { if (!isNaN(raw)) currentAssignedTo[raw] = 'view'; }
        }

        document.getElementById('shareModal').classList.add('active');
        fetchAssignmentData();
    }

    function closeShareModal() { document.getElementById('shareModal').classList.remove('active'); }

    async function fetchAssignmentData() {
        if (usersData.length > 0) { renderAssigneeList(); return; }
        try {
            const [uRes, tRes] = await Promise.all([ fetch('/api/users.php'), fetch('/api/teams.php') ]);
            const u = await uRes.json();
            const t = await tRes.json();
            if (u.success) usersData = u.users;
            if (t.success) teamsData = t.teams;
            if (teamsData.length > 0) currentTeamTab = teamId; // Default to current team
            renderAssignmentTabs();
            renderAssigneeList();
        } catch (e) { Toast.error("Directory Error", "Failed to load team members."); }
    }

    function renderAssignmentTabs() {
        const tabs = document.getElementById('teamTabs');
        tabs.innerHTML = teamsData.map(t => `
            <div class="team-tab ${t.id == currentTeamTab ? 'active' : ''}" onclick="switchTeamTab(${t.id})">
                ${t.name}
            </div>
        `).join('');
    }

    function switchTeamTab(tid) {
        currentTeamTab = tid;
        renderAssignmentTabs();
        renderAssigneeList();
    }

    function renderAssigneeList() {
        const list = document.getElementById('assigneeList');
        const query = document.getElementById('assignSearch').value.toLowerCase();
        
        let filtered = usersData.filter(u => u.is_active == 1);
        
        // Filter by Team Tab
        if (currentTeamTab) {
            filtered = filtered.filter(u => u.team_id == currentTeamTab);
        }

        // Filter by Search Query
        if (query) {
            filtered = filtered.filter(u => (u.full_name || u.username).toLowerCase().includes(query));
        }

        if (filtered.length === 0) {
            list.innerHTML = `<div style="padding:3rem; text-align:center; color:#94a3b8; font-weight:600;">No members match your search.</div>`;
            return;
        }

        list.innerHTML = filtered.map(u => {
            const role = currentAssignedTo[u.id];
            const isSelected = !!role;
            return `
                <div class="user-row ${isSelected ? 'selected' : ''}" onclick="toggleUser(${u.id})">
                    <div class="user-avatar-small">${(u.full_name || u.username).charAt(0).toUpperCase()}</div>
                    <div style="flex:1;">
                        <div style="font-weight:800; font-size:1rem; color:#1e293b;">${u.full_name || u.username}</div>
                        <div style="font-size:0.75rem; color:#94a3b8; font-weight:600;">@${u.username}</div>
                    </div>
                    ${isSelected ? `
                    <div class="role-toggle" onclick="event.stopPropagation()">
                        <button class="role-btn ${role === 'view' ? 'active' : ''}" onclick="setRole(${u.id}, 'view')">View</button>
                        <button class="role-btn ${role === 'edit' ? 'active' : ''}" onclick="setRole(${u.id}, 'edit')">Edit</button>
                    </div>
                    ` : `
                    <div style="width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#f1f5f9; color:transparent;">
                        <i class="fa-solid fa-check" style="font-size:0.75rem;"></i>
                    </div>
                    `}
                </div>
            `;
        }).join('');
        document.getElementById('selectionCount').textContent = `${Object.keys(currentAssignedTo).length} members selected`;
    }

    function toggleUser(id) {
        if (currentAssignedTo[id]) delete currentAssignedTo[id];
        else currentAssignedTo[id] = 'view';
        renderAssigneeList();
    }

    function setRole(id, role) {
        currentAssignedTo[id] = role;
        renderAssigneeList();
    }

    async function saveAssignment() {
        const btn = document.getElementById('saveShareBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Syncing...';
        btn.disabled = true;

        const endpoint = activeAssignType === 'folder' ? '/api/folders.php' : '/api/files.php';
        try {
            const res = await fetch(endpoint, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: activeAssignId, assigned_to: currentAssignedTo })
            });
            if((await res.json()).success) { 
                Toast.success("Permissions Synced", "Vault access has been updated.");
                closeShareModal(); 
                setTimeout(fetchData, 200); 
            }
        } catch (e) { Toast.error("Sync Failed", "Network error while saving permissions."); }
        finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    fetchData();

    // Secondary Reliability Sync - Periodic refresh in case of FCM jitter
    setInterval(fetchData, 10000); 
</script>

<?php endLayout(); ?>