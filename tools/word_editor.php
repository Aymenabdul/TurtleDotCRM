<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/../src/layouts/base_layout.php';

$user = AuthMiddleware::requireAuth();
$teamId = $_GET['team_id'] ?? null;
$docId = $_GET['id'] ?? null;

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

startLayout($docId ? "Edit Document" : "New Document", $user, false);
?>

<style>
    .main-wrapper {
        margin-left: 0 !important;
    }

    .main-content {
        padding: 0 !important;
        max-width: 100% !important;
    }
</style>

<div class="workspace-wrapper fade-in">
    <!-- Workspace Nav -->
    <div class="workspace-nav" style="position: relative;">
        <div class="nav-left">
            <?php $is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin'; ?>
            <a href="<?php echo $is_admin ? '/admin_dashboard.php' : '/index.php'; ?>" class="btn-back"
                title="Back to Dashboard">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="doc-icon-header">
                <i class="fa-solid fa-file-word"></i>
            </div>
            <div class="doc-meta">
                <input type="text" id="docTitle" class="workspace-title-input" value="Untitled Document"
                    placeholder="Document Name">
                <div class="save-indicator" id="saveIndicator">
                    <span class="dot"></span>
                    <span class="text" id="saveStatus">All changes saved</span>
                </div>

            </div>
        </div>

        <!-- Centered Date Meta -->
        <div class="date-meta"
            style="display:none; position: absolute; left: 50%; transform: translateX(-50%); text-align: center; font-size: 0.7rem; color: var(--text-muted); white-space: nowrap;">
            <span id="createdAtMeta">Created: -</span> <span style="margin: 0 4px;">•</span> <span
                id="updatedAtMeta">Updated: -</span>
        </div>

        <div class="nav-actions">
            <button class="btn-workspace-primary shine-effect" onclick="saveDoc()" id="saveBtn">
                <i class="fa-solid fa-cloud-arrow-up"></i> Save
            </button>
        </div>
    </div>

    <!-- Contextual Toolbar -->
    <div class="workspace-toolbar">
        <!-- History -->
        <div class="toolbar-section">
            <button class="tool-btn" onclick="execCmd('undo')" title="Undo (Ctrl+Z)"><i
                    class="fa-solid fa-rotate-left"></i></button>
            <button class="tool-btn" onclick="execCmd('redo')" title="Redo (Ctrl+Y)"><i
                    class="fa-solid fa-rotate-right"></i></button>
        </div>
        <div class="toolbar-divider"></div>

        <!-- Typography -->
        <div class="toolbar-section">
            <div class="custom-dropdown" id="formatDropdown">
                <button class="custom-dropdown-trigger" onclick="toggleDropdown('formatDropdown')">
                    <span id="currentFormat">Normal Text</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                </button>
                <div class="custom-dropdown-menu">
                    <div class="dropdown-item" onclick="selectFormat('p')" data-value="p">Normal Text</div>
                    <div class="dropdown-item" onclick="selectFormat('h1')" data-value="h1">Heading 1</div>
                    <div class="dropdown-item" onclick="selectFormat('h2')" data-value="h2">Heading 2</div>
                    <div class="dropdown-item" onclick="selectFormat('h3')" data-value="h3">Heading 3</div>
                    <div class="dropdown-item" onclick="selectFormat('blockquote')" data-value="blockquote">Quote</div>
                    <div class="dropdown-item" onclick="selectFormat('pre')" data-value="pre">Code Block</div>
                </div>
            </div>
        </div>
        <div class="toolbar-divider"></div>

        <!-- Text Styling -->
        <div class="toolbar-section">
            <button class="tool-btn" onclick="execCmd('bold')" title="Bold (Ctrl+B)"><i
                    class="fa-solid fa-bold"></i></button>
            <button class="tool-btn" onclick="execCmd('italic')" title="Italic (Ctrl+I)"><i
                    class="fa-solid fa-italic"></i></button>
            <button class="tool-btn" onclick="execCmd('underline')" title="Underline (Ctrl+U)"><i
                    class="fa-solid fa-underline"></i></button>
            <button class="tool-btn" onclick="execCmd('strikeThrough')" title="Strikethrough"><i
                    class="fa-solid fa-strikethrough"></i></button>
        </div>
        <div class="toolbar-divider"></div>

        <!-- Colors -->
        <div class="toolbar-section">
            <div class="color-picker-wrapper" title="Text Color">
                <input type="color" id="foreColorPicker" onchange="execCmd('foreColor', this.value)" value="#000000">
                <label for="foreColorPicker" class="tool-btn"><i class="fa-solid fa-font"></i></label>
            </div>
            <div class="color-picker-wrapper" title="Highlighter">
                <input type="color" id="hiliteColorPicker" onchange="execCmd('hiliteColor', this.value)"
                    value="#ffff00">
                <label for="hiliteColorPicker" class="tool-btn"><i class="fa-solid fa-highlighter"></i></label>
            </div>
        </div>
        <div class="toolbar-divider"></div>

        <!-- Alignment -->
        <div class="toolbar-section">
            <button class="tool-btn" onclick="execCmd('justifyLeft')" title="Align Left"><i
                    class="fa-solid fa-align-left"></i></button>
            <button class="tool-btn" onclick="execCmd('justifyCenter')" title="Align Center"><i
                    class="fa-solid fa-align-center"></i></button>
            <button class="tool-btn" onclick="execCmd('justifyRight')" title="Align Right"><i
                    class="fa-solid fa-align-right"></i></button>
            <button class="tool-btn" onclick="execCmd('justifyFull')" title="Justify"><i
                    class="fa-solid fa-align-justify"></i></button>
        </div>
        <div class="toolbar-divider"></div>

        <!-- Lists & Indent -->
        <div class="toolbar-section">
            <button class="tool-btn" onclick="execCmd('insertUnorderedList')" title="Bullet List"><i
                    class="fa-solid fa-list-ul"></i></button>
            <button class="tool-btn" onclick="execCmd('insertOrderedList')" title="Numbered List"><i
                    class="fa-solid fa-list-ol"></i></button>
        </div>
        <div class="toolbar-divider"></div>

        <!-- Inserts -->
        <div class="toolbar-section">
            <button class="tool-btn" onclick="execCmd('insertHorizontalRule')" title="Insert Divider"><i
                    class="fa-solid fa-minus"></i></button>
            <div style="width: 1px; height: 20px; background: var(--border-color); margin: 0 6px;"></div>
            <button class="tool-btn-labeled" onclick="addPage()" title="Add New Page">
                <i class="fa-solid fa-plus"></i> Add Page
            </button>
        </div>
    </div>

    <!-- Document Canvas -->
    <div class="workspace-canvas">
        <div id="documentContainer" class="canvas-inner">
            <!-- Pages injected here -->
            <div class="canvas-page" contenteditable="true" data-page="1">
                <p><br></p>
            </div>
        </div>
    </div>
</div>

<!-- Custom Confirmation Modal -->


<style>
    :root {
        --workspace-nav-h: 64px;
        --workspace-toolbar-h: 52px;
        --accent: #2563eb;
        --accent-hover: #1d4ed8;
        --bg-darker: #f3f4f6;
        --border-color: #e5e7eb;
        --text-main: #1f2937;
        --text-muted: #6b7280;
    }

    .workspace-wrapper {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--bg-darker);
        z-index: 1001;
        display: flex;
        flex-direction: column;
        color: var(--text-main);
    }

    /* Modern Nav Bar */
    .workspace-nav {
        height: var(--workspace-nav-h);
        background: white;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.5rem;
        z-index: 10;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .nav-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .btn-back {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        text-decoration: none;
        border: 1px solid transparent;
    }

    .btn-back:hover {
        background: var(--bg-darker);
        color: var(--text-main);
        border-color: var(--border-color);
    }

    .doc-icon-header {
        width: 36px;
        height: 36px;
        background: #eff6ff;
        color: var(--accent);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .doc-meta {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    /* Custom Dropdown */
    .custom-dropdown {
        position: relative;
        display: inline-block;
    }

    .custom-dropdown-trigger {
        height: 34px;
        padding: 0 10px;
        border-radius: 6px;
        border: 1px solid transparent;
        /* Invisible border unless active/hover */
        background: transparent;
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        color: var(--text-main);
        font-weight: 500;
        transition: all 0.2s;
        min-width: 140px;
        justify-content: space-between;
    }

    .custom-dropdown-trigger:hover {
        background: var(--bg-darker);
    }

    .custom-dropdown-trigger.active {
        background: #eff6ff;
        color: var(--accent);
        border-color: #dbeafe;
    }

    .custom-dropdown-menu {
        position: absolute;
        top: 100%;
        left: 0;
        margin-top: 4px;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        padding: 4px;
        min-width: 180px;
        z-index: 100;
        display: none;
        flex-direction: column;
        gap: 2px;
    }

    .custom-dropdown-menu.show {
        display: flex;
        animation: fadeIn 0.1s ease-out;
    }

    .dropdown-item {
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        font-size: 0.9rem;
        color: var(--text-main);
        transition: background 0.1s;
    }

    .dropdown-item:hover {
        background: var(--bg-darker);
    }

    .dropdown-item.active {
        background: #eff6ff;
        color: var(--accent);
    }

    /* Styling for the visual representation in the dropdown */
    .dropdown-item[data-value="p"] {
        font-weight: 400;
    }

    .dropdown-item[data-value="h1"] {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .dropdown-item[data-value="h2"] {
        font-size: 1rem;
        font-weight: 700;
    }

    .dropdown-item[data-value="h3"] {
        font-size: 0.9rem;
        font-weight: 700;
    }

    .dropdown-item[data-value="blockquote"] {
        font-style: italic;
        color: var(--text-muted);
        border-left: 2px solid var(--border-color);
        padding-left: 8px;
    }

    .dropdown-item[data-value="pre"] {
        font-family: monospace;
        font-size: 0.85rem;
        background: #f3f4f6;
    }

    /* Arrow Icon */
    .dropdown-arrow {
        font-size: 0.7rem;
        color: var(--text-muted);
    }

    .workspace-title-input {
        background: transparent;
        border: 1px solid transparent;
        color: var(--text-main);
        font-size: 1.1rem;
        font-weight: 600;
        outline: none;
        padding: 2px 6px;
        width: 300px;
        border-radius: 4px;
        transition: 0.2s;
    }

    .workspace-title-input:hover {
        border-color: var(--border-color);
    }

    .workspace-title-input:focus {
        border-color: var(--accent);
        background: white;
    }

    .save-indicator {
        display: flex;
        align-items: center;
        gap: 6px;
        padding-left: 6px;
    }

    .save-indicator .dot {
        width: 6px;
        height: 6px;
        background: #10b981;
        border-radius: 50%;
    }

    .save-indicator.unsaved .dot {
        background: #f59e0b;
    }

    .save-indicator .text {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .nav-actions {
        display: flex;
        gap: 0.75rem;
    }

    .btn-workspace-primary {
        background: var(--accent);
        color: white;
        border: none;
        padding: 0.5rem 1.25rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .btn-workspace-primary:hover:not(:disabled) {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
    }

    /* Floating Toolbar */
    .workspace-toolbar {
        height: var(--workspace-toolbar-h);
        background: white;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0 1rem;
        z-index: 5;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .toolbar-section {
        display: flex;
        gap: 2px;
        align-items: center;
    }

    .toolbar-divider {
        width: 1px;
        height: 20px;
        background: var(--border-color);
        margin: 0 6px;
    }

    .tool-btn {
        width: 34px;
        height: 34px;
        border: none;
        background: transparent;
        border-radius: 6px;
        color: var(--text-muted);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .tool-btn:hover {
        background: var(--bg-darker);
        color: var(--text-main);
    }

    .tool-btn-labeled {
        height: 34px;
        border: none;
        background: #f0fdf4;
        border-radius: 6px;
        color: #166534;
        cursor: pointer;
        display: flex;
        align-items: center;
        padding: 0 1rem;
        gap: 0.5rem;
        transition: all 0.2s;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .tool-btn-labeled:hover {
        background: #dcfce7;
    }

    .tool-btn.active {
        background: #eff6ff;
        color: var(--accent);
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        /* Added inset shadow for depth */
        border: 1px solid #dbeafe;
        /* Added border */
    }

    /* Color Picker */
    .color-picker-wrapper {
        position: relative;
        width: 34px;
        height: 34px;
    }

    .color-picker-wrapper input[type="color"] {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        z-index: 2;
    }

    /* Scrollable Canvas */
    .workspace-canvas {
        flex: 1;
        overflow-y: auto;
        padding: 2rem;
        display: flex;
        justify-content: center;
        background: #f8fafc;
    }

    .canvas-inner {
        width: 100%;
        max-width: 816px;
        /* A4 width approx */
        display: flex;
        flex-direction: column;
        gap: 2rem;
        padding-bottom: 4rem;
    }

    .canvas-page {
        background: white;
        width: 100%;
        min-height: 1056px;
        /* A4 height approx */
        padding: 4rem 3rem;
        /* Standard margins */
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        outline: none;
        font-size: 11pt;
        font-family: 'Calibri', 'Arial', sans-serif;
        line-height: 1.6;
        color: #000;
        position: relative;
    }

    /* Page Controls Overlay */
    /* Delete Button - Top Right */
    .page-delete {
        position: absolute;
        top: 1rem;
        right: 1rem;
        opacity: 0;
        transition: opacity 0.2s;
        pointer-events: none;
    }

    .canvas-page:hover .page-delete {
        opacity: 1;
        pointer-events: auto;
    }

    /* Page Number - Bottom Center */
    .page-number-container {
        position: absolute;
        bottom: 1rem;
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        pointer-events: none;
    }

    .page-number {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        background: rgba(255, 255, 255, 0.9);
        padding: 4px 12px;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-color);
    }

    .btn-remove-page {
        background: white;
        color: #ef4444;
        border: 1px solid #fee2e2;
        cursor: pointer;
        font-size: 0.9rem;
        padding: 6px;
        border-radius: 6px;
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .btn-remove-page:hover {
        background: #fee2e2;
        transform: translateY(-1px);
    }

    .canvas-page h1 {
        font-size: 2em;
        margin-bottom: 0.5em;
    }

    .canvas-page h2 {
        font-size: 1.5em;
        margin-bottom: 0.5em;
    }

    .canvas-page h3 {
        font-size: 1.17em;
        margin-bottom: 0.5em;
    }

    .canvas-page pre {
        background: #f5f5f5;
        padding: 1rem;
        border-radius: 4px;
        font-family: monospace;
        white-space: pre-wrap;
    }

    .canvas-page blockquote {
        border-left: 4px solid var(--accent);
        margin: 1em 0;
        padding-left: 1em;
        color: var(--text-muted);
    }

    .canvas-page hr {
        border: 0;
        height: 1px;
        background: #e5e7eb;
        margin: 1.5rem 0;
    }

    /* Print media query for actual printing */
    @media print {

        .workspace-nav,
        .workspace-toolbar,
        .btn-back,
        .page-controls {
            display: none !important;
        }

        .workspace-wrapper {
            position: static;
            height: auto;
            background: white;
        }

        .workspace-canvas {
            padding: 0;
            overflow: visible;
        }

        .canvas-page {
            box-shadow: none;
            margin: 0;
            page-break-after: always;
            min-height: auto;
            padding: 0;
        }
    }
</style>

<script>
    const teamId = <?php echo $teamId; ?>;
    const docId = <?php echo $docId ? $docId : 'null'; ?>;
    let isSaving = false;
    let pageToDelete = null;

    let lastFocusedPage = null;
    let selectedRange = null;

    // Track active page focus
    document.addEventListener('focusin', (e) => {
        if (e.target.classList.contains('canvas-page')) {
            lastFocusedPage = e.target;
        }
    }, true);

    // Save Selection
    function saveSelection() {
        const sel = window.getSelection();
        if (sel.rangeCount > 0) {
            const range = sel.getRangeAt(0);
            // Only save if within our editor
            if (range.commonAncestorContainer.closest('.canvas-page')) {
                selectedRange = range;
            }
        }
    }

    // Restore Selection
    function restoreSelection() {
        if (selectedRange) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(selectedRange);
            // Also update lastFocusedPage if possible
            const page = selectedRange.commonAncestorContainer.closest('.canvas-page');
            if (page) lastFocusedPage = page;
        } else if (lastFocusedPage) {
            lastFocusedPage.focus();
        } else {
            const page = document.querySelector('.canvas-page');
            if (page) page.focus();
        }
    }

    // Bind selection saving
    document.addEventListener('mouseup', saveSelection);
    document.addEventListener('keyup', saveSelection);
    document.addEventListener('selectionchange', () => {
        // Debounce slightly or check active element
        if (document.activeElement && document.activeElement.classList.contains('canvas-page')) {
            saveSelection();
        }
    });

    function execCmd(cmd, value = null) {
        restoreSelection();
        document.execCommand(cmd, false, value);
        // Save new selection state after command
        saveSelection();
        updateToolbarState();
    }

    // Custom Dropdown Logic
    function toggleDropdown(id) {
        // Prevent default focus loss behavior if possible, or just rely on restoreSelection
        const dropdown = document.getElementById(id).querySelector('.custom-dropdown-menu');
        const trigger = document.getElementById(id).querySelector('.custom-dropdown-trigger');

        // If opening, save current selection first to be safe
        if (!dropdown.classList.contains('show')) {
            saveSelection();
        }

        // Close others ... existing code ...
        document.querySelectorAll('.custom-dropdown-menu').forEach(menu => {
            if (menu !== dropdown) menu.classList.remove('show');
        });
        document.querySelectorAll('.custom-dropdown-trigger').forEach(btn => {
            if (btn !== trigger) btn.classList.remove('active');
        });

        dropdown.classList.toggle('show');
        trigger.classList.toggle('active');
    }

    function selectFormat(tag) {
        // Restore before executing
        restoreSelection();

        const formatMap = {
            'p': 'Normal Text',
            'h1': 'Heading 1',
            'h2': 'Heading 2',
            'h3': 'Heading 3',
            'blockquote': 'Quote',
            'pre': 'Code Block'
        };

        // Update UI
        document.getElementById('currentFormat').textContent = formatMap[tag];
        const dropdown = document.getElementById('formatDropdown');
        dropdown.querySelector('.custom-dropdown-menu').classList.remove('show');
        dropdown.querySelector('.custom-dropdown-trigger').classList.remove('active');

        // Execute Command
        // For block formatting, sometimes we need to ensure the selection is valid
        execCmd('formatBlock', tag);
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.custom-dropdown')) {
            document.querySelectorAll('.custom-dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
            document.querySelectorAll('.custom-dropdown-trigger').forEach(btn => {
                btn.classList.remove('active');
            });
        }
    });

    // Simplified remove logic using glass-confirm
    function requestRemovePage(btn) {
        const container = document.getElementById('documentContainer');
        if (container.children.length <= 1) {
            Confirm.show({
                title: 'Cannot Delete',
                message: 'You cannot delete the last page of the document.',
                type: 'info',
                confirmText: 'OK',
                // No onConfirm needed as it's just info
            });
            return;
        }

        pageToDelete = btn.closest('.canvas-page');

        Confirm.show({
            title: 'Delete Page',
            message: 'Are you sure you want to delete this page? All content on this page will be permanently lost.',
            confirmText: 'Delete Page',
            type: 'danger',
            onConfirm: confirmRemovePage
        });
    }

    function confirmRemovePage() {
        if (pageToDelete) {
            pageToDelete.remove();
            pageToDelete = null;
            updatePageNumbers();
            handleInput();
            // Toast.success('Page Deleted'); // Optional
        }
    }

    // Add Page
    function addPage() {
        const container = document.getElementById('documentContainer');
        const pageNum = container.children.length + 1;
        const page = document.createElement('div');
        page.className = 'canvas-page';
        page.contentEditable = true;
        page.innerHTML = `
            <div class="page-delete" contenteditable="false">
                 <button class="btn-remove-page" onclick="requestRemovePage(this)" title="Remove Page"><i class="fa-solid fa-trash"></i></button>
            </div>
            <p><br></p>
            <div class="page-number-container" contenteditable="false">
                <span class="page-number">Page ${pageNum}</span>
            </div>
        `;
        container.appendChild(page);

        page.focus();
        page.scrollIntoView({ behavior: 'smooth', block: 'start' });

        page.addEventListener('input', handleInput);
        // Add listeners for toolbar state
        page.addEventListener('mouseup', updateToolbarState);
        page.addEventListener('keyup', updateToolbarState);
        page.addEventListener('click', updateToolbarState);

        updatePageNumbers();
    }



    function updatePageNumbers() {
        const pages = document.querySelectorAll('.canvas-page');
        pages.forEach((page, index) => {
            const numDisplay = page.querySelector('.page-number');
            if (numDisplay) {
                numDisplay.textContent = `Page ${index + 1}`;
            }
            page.setAttribute('data-page', index + 1);
        });
    }

    // Update Toolbar State
    function updateToolbarState() {
        const cmds = ['bold', 'italic', 'underline', 'strikeThrough',
            'justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull',
            'insertUnorderedList', 'insertOrderedList'];

        cmds.forEach(cmd => {
            const state = document.queryCommandState(cmd);
            const btn = document.querySelector(`button[onclick="execCmd('${cmd}')"]`);
            if (btn) {
                if (state) btn.classList.add('active');
                else btn.classList.remove('active');
            }
        });

        // Font Format (Block)
        const formatBlock = document.queryCommandValue('formatBlock');
        const currentFormatSpan = document.getElementById('currentFormat');

        if (formatBlock && currentFormatSpan) {
            const formatMap = {
                'p': 'Normal Text',
                'h1': 'Heading 1',
                'h2': 'Heading 2',
                'h3': 'Heading 3',
                'blockquote': 'Quote',
                'pre': 'Code Block',
                'div': 'Normal Text' // fallback
            };

            // formatBlock returns tags like 'h1', 'p', etc. sometimes wrapped.
            let val = formatBlock.toLowerCase();
            // Handle different browser returns
            if (['div', 'body'].includes(val)) val = 'p';

            if (formatMap[val]) {
                currentFormatSpan.textContent = formatMap[val];
            }

            // Update active state in dropdown
            document.querySelectorAll('.dropdown-item').forEach(item => {
                if (item.dataset.value === val) item.classList.add('active');
                else item.classList.remove('active');
            });
        }
    }

    // Download Doc
    function downloadDoc() {
        const title = document.getElementById('docTitle').value || 'document';
        const pages = document.querySelectorAll('.canvas-page');
        let fullContent = '';

        // Combine all pages content, stripping out controls
        pages.forEach(p => {
            // Clone to manipulate
            const clone = p.cloneNode(true);
            // Remove UI controls from download content
            clone.querySelectorAll('.page-delete, .page-number-container').forEach(el => el.remove());
            fullContent += `<div style="page-break-after: always; margin-bottom: 2rem;">${clone.innerHTML}</div>`;
        });

        const header = `
            <html xmlns:o='urn:schemas-microsoft-com:office:office' 
                  xmlns:w='urn:schemas-microsoft-com:office:word' 
                  xmlns='http://www.w3.org/TR/REC-html40'>
            <head>
                <meta charset='utf-8'>
                <title>${title}</title>
                <style>
                    body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; line-height: 1.6; }
                </style>
            </head>
            <body>`;
        const footer = "</body></html>";
        const sourceHTML = header + fullContent + footer;

        const source = 'data:application/vnd.ms-word;charset=utf-8,' + encodeURIComponent(sourceHTML);
        const fileDownload = document.createElement("a");
        document.body.appendChild(fileDownload);
        fileDownload.href = source;
        fileDownload.download = `${title}.doc`;
        document.body.removeChild(fileDownload);
    }

    async function loadDocument() {
        if (!docId) return;
        try {
            const response = await fetch(`/api/word.php?id=${docId}`);
            const result = await response.json();
            if (result.success) {
                document.getElementById('docTitle').value = result.data.title;

                // Display Dates
                if (result.data.created_at) {
                    const createdDate = new Date(result.data.created_at).toLocaleDateString(undefined, {
                        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                    });
                    document.getElementById('createdAtMeta').textContent = `Created: ${createdDate}`;
                }

                if (result.data.updated_at) {
                    const updatedDate = new Date(result.data.updated_at).toLocaleDateString(undefined, {
                        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                    });
                    document.getElementById('updatedAtMeta').textContent = `Updated: ${updatedDate}`;
                }
                document.querySelector('.date-meta').style.display = 'block';

                if (result.data.content) {
                    document.getElementById('documentContainer').innerHTML = result.data.content;

                    document.querySelectorAll('.canvas-page').forEach(p => {
                        p.contentEditable = true;
                        p.addEventListener('input', handleInput);
                        p.addEventListener('mouseup', updateToolbarState);
                        p.addEventListener('keyup', updateToolbarState);
                        p.addEventListener('click', updateToolbarState);

                        // Fix for content missing new controls structure
                        if (!p.querySelector('.page-delete')) {
                            // Prepend Delete
                            const delDiv = document.createElement('div');
                            delDiv.className = 'page-delete';
                            delDiv.contentEditable = false;
                            delDiv.innerHTML = `<button class="btn-remove-page" onclick="requestRemovePage(this)" title="Remove Page"><i class="fa-solid fa-trash"></i></button>`;
                            p.insertBefore(delDiv, p.firstChild);
                        }

                        if (!p.querySelector('.page-number-container')) {
                            // Append Bottom Page Number
                            const numDiv = document.createElement('div');
                            numDiv.className = 'page-number-container';
                            numDiv.contentEditable = false;
                            numDiv.innerHTML = `<span class="page-number">Page</span>`;
                            p.appendChild(numDiv);
                        }
                    });
                    updatePageNumbers();
                }
            }
        } catch (ignore) { }
    }

    async function saveDoc() {
        if (isSaving) return;
        const title = document.getElementById('docTitle').value.trim() || 'Untitled Document';
        const content = document.getElementById('documentContainer').innerHTML;
        const btn = document.getElementById('saveBtn');
        const indicator = document.getElementById('saveIndicator');
        const status = document.getElementById('saveStatus');

        isSaving = true;
        btn.disabled = true;
        status.textContent = 'Saving...';

        try {
            const method = docId ? 'PATCH' : 'POST';
            const response = await fetch('/api/word.php', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: docId, team_id: teamId, title: title, content: content })
            });
            const result = await response.json();
            if (result.success) {
                status.textContent = 'All changes saved';
                indicator.classList.remove('unsaved');
                if (!docId) window.location.href = `/tools/word_editor.php?team_id=${teamId}&id=${result.id}`;
            } else {
                status.textContent = 'Save failed';
            }
        } catch (error) {
            status.textContent = 'Save failed';
            indicator.classList.add('unsaved');
        } finally {
            isSaving = false;
            btn.disabled = false;
        }
    }

    function handleInput() {
        document.getElementById('saveStatus').textContent = 'Unsaved changes';
        document.getElementById('saveIndicator').classList.add('unsaved');
        clearTimeout(window.saveTimer);
        window.saveTimer = setTimeout(saveDoc, 2000);
    }

    document.getElementById('docTitle').addEventListener('input', () => {
        document.getElementById('saveIndicator').classList.add('unsaved');
        document.getElementById('saveStatus').textContent = 'Unsaved title changes';
    });

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveDoc();
        }
    });

    // Add listener to initial page
    document.querySelectorAll('.canvas-page').forEach(p => p.addEventListener('input', handleInput));

    loadDocument();
</script>

<?php include __DIR__ . '/../src/components/ui/glass-confirm.php'; ?>
<?php include __DIR__ . '/../src/components/ui/glass-toast.php'; ?>
<?php endLayout(); ?>