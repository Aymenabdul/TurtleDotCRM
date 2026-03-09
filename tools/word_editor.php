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
            <a href="/tools/word.php?team_id=<?php echo htmlspecialchars($teamId); ?>" class="btn-back"
                title="Back to Documents">
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
        <div class="date-meta" id="dateMeta" style="display:none;">
            <span id="createdAtMeta">Created: -</span> <span style="margin: 0 4px;">•</span> <span
                id="updatedAtMeta">Updated: -</span>
        </div>

        <div class="nav-actions">
            <button class="btn-workspace-primary shine-effect" onclick="downloadDoc()" title="Download as DOCX"
                style="background: #ffffff; color: #1e293b; border: 1.5px solid #e2e8f0; box-shadow: none;">
                <i class="fa-solid fa-download"></i> Download
            </button>
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
            <div class="color-picker-wrapper" style="position:relative;">
                <button class="tool-btn" onmousedown="event.preventDefault(); toggleColorMenu('TextColor')"
                    title="Text Color">
                    <i class="fa-solid fa-font"></i>
                    <div id="indicatorColor"
                        style="position:absolute;bottom:4px;left:6px;right:6px;height:3px;background:#000;"></div>
                </button>
                <div id="menuTextColor" class="color-menu">
                    <div class="color-palette" id="paletteTextColor"></div>
                    <div class="color-footer">
                        <input type="color" id="inputTextColor" class="color-field" value="#000000"
                            oninput="pickColor('foreColor', this.value)">
                        <button class="apply-btn"
                            onmousedown="event.preventDefault(); applyColor('foreColor')">Apply</button>
                    </div>
                </div>
            </div>

            <div class="color-picker-wrapper" style="position:relative;">
                <button class="tool-btn" onmousedown="event.preventDefault(); toggleColorMenu('HiliteColor')"
                    title="Highlight Color">
                    <i class="fa-solid fa-highlighter"></i>
                    <div id="indicatorHilite"
                        style="position:absolute;bottom:4px;left:6px;right:6px;height:3px;background:#ffff00;"></div>
                </button>
                <div id="menuHiliteColor" class="color-menu">
                    <div class="color-palette" id="paletteHiliteColor"></div>
                    <div class="color-footer">
                        <input type="color" id="inputHiliteColor" class="color-field" value="#ffff00"
                            oninput="pickColor('hiliteColor', this.value)">
                        <button class="apply-btn"
                            onmousedown="event.preventDefault(); applyColor('hiliteColor')">Apply</button>
                    </div>
                </div>
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
    <div class="workspace-canvas"
        onclick="if(event.target === this || event.target.id === 'documentContainer') { restoreSelection(); }">
        <div id="documentContainer" class="canvas-inner">
            <!-- Pages injected here -->
            <div class="canvas-page" contenteditable="true" data-page="1">
                <p><br></p>
            </div>
        </div>
    </div>
</div>

<!-- Custom Confirmation Modal -->


<link rel="stylesheet" href="/css/word_editor.css">
<script>
    const teamId = "<?php echo htmlspecialchars($teamId); ?>";
    const docId = <?php echo $docId ? '"' . htmlspecialchars($docId) . '"' : 'null'; ?>;
    let isSaving = false;
    let pageToDelete = null;

    let lastFocusedPage = null;
    let selectedRange = null;

    // Track active page focus
    document.addEventListener('focusin', (e) => {
        if (e && e.target && e.target.classList && e.target.classList.contains('canvas-page')) {
            lastFocusedPage = e.target;
        }
    }, true);

    // Save Selection
    function saveSelection() {
        try {
            const sel = window.getSelection();
            if (sel && sel.rangeCount > 0) {
                const range = sel.getRangeAt(0);
                // Only save if within our editor
                const node = range.commonAncestorContainer;
                if (node) {
                    const container = node.nodeType === 3 ? node.parentElement : node;
                    if (container && container.closest && container.closest('.canvas-page')) {
                        selectedRange = range.cloneRange();
                    }
                }
            }
        } catch (e) {
            console.error('Selection save failed:', e);
        }
    }

    // Restore Selection
    function restoreSelection() {
        if (selectedRange) {
            try {
                // Determine correctly which page to focus
                const node = selectedRange.commonAncestorContainer;
                const container = node.nodeType === 3 ? node.parentElement : node;
                const page = container ? container.closest('.canvas-page') : null;
                if (page) {
                    lastFocusedPage = page;
                    page.focus();
                }

                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(selectedRange);
            } catch (e) {
                console.warn('Selection restore failed', e);
                if (lastFocusedPage) lastFocusedPage.focus();
            }
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
    // Removed selectionchange to avoid conflicts with extensions like QuillBot

    function execCmd(cmd, value = null) {
        restoreSelection();
        const res = document.execCommand(cmd, false, value);
        // Save new selection state after command
        saveSelection();
        updateToolbarState();
        return res;
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
        if (!e.target.closest('.custom-dropdown') && !e.target.closest('.color-picker-wrapper')) {
            document.querySelectorAll('.custom-dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
            document.querySelectorAll('.custom-dropdown-trigger').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.color-menu').forEach(menu => {
                menu.classList.remove('show');
            });
            document.querySelector('.workspace-toolbar').classList.remove('menu-open');
        }
    });

    // --- Color Palette Logic (Spreadsheet style) ---
    const PALETTE_COLORS = [
        '#000000', '#434343', '#666666', '#999999', '#cccccc', '#ffffff',
        '#980000', '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff',
        '#4a86e8', '#0000ff', '#9900ff', '#ff00ff', '#e6b8af', '#f4cccc',
        '#fce5cd', '#fff2cc', '#d9ead3', '#d0e0e3', '#c9daf8', '#cfe2f3',
        '#d9d2e9', '#ead1dc', '#dd7e6b', '#ea9999', '#f9cb9c', '#ffe599',
        '#b6d7a8', '#a2c4c9', '#a4c2f4', '#9fc5e8', '#b4a7d6', '#d5a6bd',
        '#cc4125', '#e06666', '#f6b26b', '#ffd966', '#93c47d', '#76a5af',
        '#6d9eeb', '#6fa8dc', '#8e7cc3', '#c27ba0', '#a61c00', '#cc0000',
        '#e69138', '#f1c232', '#6aa84f', '#45818e', '#3c78d8', '#3d85c6',
        '#674ea7', '#a64d79'
    ];

    function initPalettes() {
        const createSwatch = (color, cmd) => {
            const el = document.createElement('div');
            el.className = 'color-swatch';
            el.style.backgroundColor = color;
            el.title = color;
            el.onmousedown = (e) => {
                e.preventDefault(); // Prevents focus loss
                pickColor(cmd, color);
            };
            return el;
        };

        const pText = document.getElementById('paletteTextColor');
        const pHilite = document.getElementById('paletteHiliteColor');

        if (pText) PALETTE_COLORS.forEach(c => pText.appendChild(createSwatch(c, 'foreColor')));
        if (pHilite) PALETTE_COLORS.forEach(c => pHilite.appendChild(createSwatch(c, 'hiliteColor')));
    }

    function toggleColorMenu(type) {
        // Close others
        document.querySelectorAll('.color-menu').forEach(m => {
            if (m.id !== `menu${type}`) m.classList.remove('show');
        });
        document.querySelectorAll('.custom-dropdown-menu').forEach(m => m.classList.remove('show'));

        const menu = document.getElementById(`menu${type}`);
        if (menu) {
            const isOpening = !menu.classList.contains('show');
            if (isOpening) saveSelection();
            menu.classList.toggle('show');

            // Handle toolbar overflow state
            const toolbar = document.querySelector('.workspace-toolbar');
            if (isOpening) toolbar.classList.add('menu-open');
            else toolbar.classList.remove('menu-open');
        }
    }

    function pickColor(cmd, color) {
        const inputId = (cmd === 'foreColor') ? 'inputTextColor' : 'inputHiliteColor';
        const indicatorId = (cmd === 'foreColor') ? 'indicatorColor' : 'indicatorHilite';

        document.getElementById(inputId).value = color;
        document.getElementById(indicatorId).style.background = color;

        // Try to apply the command. Browsers can be picky about hiliteColor vs backColor
        if (cmd === 'hiliteColor') {
            if (!execCmd('hiliteColor', color)) {
                execCmd('backColor', color);
            }
        } else {
            execCmd(cmd, color);
        }
    }

    function applyColor(cmd) {
        document.querySelectorAll('.color-menu').forEach(m => m.classList.remove('show'));
        document.querySelector('.workspace-toolbar').classList.remove('menu-open');
    }

    // Call init
    initPalettes();

    // Enable CSS-based formatting for reliable color application
    try {
        document.execCommand('styleWithCSS', false, true);
    } catch (e) {
        console.warn('styleWithCSS not supported');
    }

    // Override toggleDropdown to handle toolbar scroll
    const originalToggleDropdown = toggleDropdown;
    toggleDropdown = function (id) {
        originalToggleDropdown(id);
        const container = document.getElementById(id);
        if (!container) return;
        const menu = container.querySelector('.custom-dropdown-menu');
        const toolbar = document.querySelector('.workspace-toolbar');
        if (menu && menu.classList.contains('show')) {
            toolbar.classList.add('menu-open');
        } else {
            toolbar.classList.remove('menu-open');
        }
    };

    // Simplified remove logic using glass-confirm
    function requestRemovePage(btn) {
        const container = document.getElementById('documentContainer');
        if (container.children.length <= 1) {
            Confirm.show({
                title: 'Cannot Delete',
                text: 'You cannot delete the last page of the document.',
                type: 'info',
                confirmText: 'OK'
            });
            return;
        }

        pageToDelete = btn.closest('.canvas-page');

        Confirm.show({
            title: 'Delete Page',
            text: 'Are you sure you want to delete this page? All content on this page will be permanently lost.',
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
                    const container = document.getElementById('documentContainer');
                    container.innerHTML = result.data.content;

                    // Ensure structure: if no canvas-page exists, wrap everything in one
                    if (!container.querySelector('.canvas-page')) {
                        const content = container.innerHTML;
                        container.innerHTML = `<div class="canvas-page" contenteditable="true" data-page="1">${content}</div>`;
                    }

                    document.querySelectorAll('.canvas-page').forEach((p, idx) => {
                        const pageNum = idx + 1;
                        p.contentEditable = true;
                        p.setAttribute('data-page', pageNum);

                        // Ensure it has listeners
                        p.addEventListener('input', handleInput);
                        p.addEventListener('mouseup', updateToolbarState);
                        p.addEventListener('keyup', updateToolbarState);

                        // Ensure clickable
                        if (!p.innerHTML.trim().replace(/<div[^>]*>.*<\/div>/g, '')) {
                            const pTag = document.createElement('p');
                            pTag.innerHTML = '<br>';
                            p.appendChild(pTag);
                        }

                        // Fix structures
                        if (!p.querySelector('.page-delete')) {
                            const delDiv = document.createElement('div');
                            delDiv.className = 'page-delete';
                            delDiv.contentEditable = false;
                            delDiv.innerHTML = `<button class="btn-remove-page" onclick="requestRemovePage(this)" title="Remove Page"><i class="fa-solid fa-trash"></i></button>`;
                            p.prepend(delDiv);
                        }

                        if (!p.querySelector('.page-number-container')) {
                            const numDiv = document.createElement('div');
                            numDiv.className = 'page-number-container';
                            numDiv.contentEditable = false;
                            numDiv.innerHTML = `<span class="page-number">Page ${pageNum}</span>`;
                            p.appendChild(numDiv);
                        } else {
                            const numSpan = p.querySelector('.page-number');
                            if (numSpan) numSpan.textContent = `Page ${pageNum}`;
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
    document.querySelectorAll('.canvas-page').forEach(p => {
        if (p) p.addEventListener('input', handleInput);
    });

    if (typeof loadDocument === 'function') {
        loadDocument();
    }
</script>

<?php include __DIR__ . '/../src/components/ui/glass-confirm.php'; ?>
<?php include __DIR__ . '/../src/components/ui/glass-toast.php'; ?>
<?php endLayout(); ?>