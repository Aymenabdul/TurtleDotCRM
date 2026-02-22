<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_middleware.php';
require_once __DIR__ . '/../src/layouts/base_layout.php';

$user = AuthMiddleware::requireAuth();
$teamId = $_GET['team_id'] ?? null;
$sheetId = $_GET['id'] ?? null;

if (!$teamId) {
    header("Location: /manage-teams.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$teamId]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        header("Location: /manage-teams.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Layout Wrapper (No default sidebar/header to maximize space)
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spreadsheet - <?php echo htmlspecialchars($team['name']); ?></title>

    <!-- Fonts & Icons -->
    <link
        href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;600&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Base Reset & Variables */
        :root {
            --excel-green: #107c41;
            --excel-dark-green: #0c5e31;
            --excel-ribbon-bg: #f3f2f1;
            --excel-border: #e1dfdd;
            --grid-line: #e0e0e0;
            --header-bg: #f9f9f9;
            --header-text: #666;
            --selection-border: #107c41;
            --selection-bg: rgba(16, 124, 65, 0.1);
            --text-main: #323130;
            --hover-bg: #eaeaea;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            font-family: 'Segoe UI', 'Inter', sans-serif;
            background: white;
            color: var(--text-main);
            display: flex;
            flex-direction: column;
        }

        /* Top Bar: Premium Green Title Area */
        .excel-title-bar {
            background: var(--excel-green);
            height: 54px;
            /* Taller for better touch targets */
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 18px;
            color: white;
            flex-shrink: 0;
            z-index: 50;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .title-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .back-btn {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
        }

        .app-icon {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: white;
            color: var(--excel-green);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .file-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 2px;
        }

        .file-name-input {
            background: transparent;
            border: 1px solid transparent;
            color: white;
            font-size: 1.05rem;
            font-weight: 600;
            padding: 2px 6px;
            margin-left: -6px;
            border-radius: 4px;
            outline: none;
            width: 300px;
            transition: 0.2s;
            line-height: 1.2;
        }

        .file-name-input:focus,
        .file-name-input:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .save-indicator {
            font-size: 0.75rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.9);
        }

        .title-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }



        .user-profile {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: white;
            color: var(--excel-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 700;
            border: 2px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Ribbon Toolbar Refined */
        .ribbon-container {
            background: #f8fafc;
            border-bottom: 1px solid var(--excel-border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            padding: 6px 12px;
        }

        .ribbon-toolbar {
            display: flex;
            gap: 12px;
            align-items: center;
            height: 48px;
            padding: 0 4px;
        }

        .tool-group {
            display: flex;
            gap: 6px;
            padding-right: 12px;
            border-right: 1px solid #e2e8f0;
            align-items: center;
            height: 32px;
        }

        .tool-group:last-child {
            border-right: none;
        }

        .tool-btn {
            border: 1px solid transparent;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            transition: all 0.2s;
            position: relative;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .tool-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: var(--text-main);
            transform: translateY(-1px);
        }

        .tool-btn.active {
            background: #dcfce7;
            border-color: var(--excel-green);
            color: var(--excel-dark-green);
        }

        .btn-small {
            width: 32px;
            height: 32px;
            font-size: 0.95rem;
        }


        .btn-wide {
            padding: 0 12px;
            width: auto;
            gap: 8px;
            font-size: 0.85rem;
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #475569;
            font-weight: 600;
            height: 32px;
        }

        .btn-wide:hover {
            background: white;
            border-color: var(--excel-green);
            color: var(--excel-green);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .font-controls {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .select-wrapper select {
            border: 1px solid transparent;
            background: transparent;
            font-size: 0.85rem;
            padding: 2px 4px;
            border-radius: 3px;
            outline: none;
        }

        .select-wrapper select:hover {
            border-color: #d0d0d0;
            background: white;
        }

        /* Formula Bar */
        .formula-bar-container {
            display: flex;
            align-items: center;
            padding: 6px 12px;
            background: white;
            border-bottom: 1px solid var(--excel-border);
            gap: 10px;
            height: 36px;
            flex-shrink: 0;
        }

        .name-box {
            width: 70px;
            height: 24px;
            border: 1px solid #d0d0d0;
            display: flex;
            align-items: center;
            padding: 0 8px;
            font-size: 0.85rem;
            color: #333;
            border-radius: 2px;
            background: white;
        }

        .formula-actions {
            color: #999;
            font-size: 0.85rem;
            display: flex;
            gap: 8px;
            padding: 0 4px;
        }

        .formula-input-wrapper {
            flex: 1;
            height: 24px;
            border: 1px solid #d0d0d0;
            border-radius: 2px;
            display: flex;
            align-items: center;
            background: white;
            overflow: hidden;
        }

        .formula-input {
            width: 100%;
            border: none;
            outline: none;
            padding: 0 8px;
            font-size: 0.9rem;
            font-family: inherit;
        }

        .formula-input:focus {
            background: #fdfdfd;
        }

        /* Grid */
        .grid-wrapper {
            flex: 1;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .grid-scroll-area {
            flex: 1;
            overflow: auto;
            position: relative;
            background: #f0f0f0;
            /* Grid line color essentially */
        }

        /* CSS Grid Implementation */
        .excel-grid {
            display: grid;
            /* Columns defined in JS */
            /* Rows defined in JS */
            background: white;
            position: relative;
        }

        /* Headers */
        .col-header {
            background: var(--header-bg);
            border-right: 1px solid var(--excel-border);
            border-bottom: 1px solid var(--excel-border);
            text-align: center;
            font-size: 0.75rem;
            color: var(--header-text);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            position: sticky;
            top: 0;
            height: 24px;
            z-index: 10;
            user-select: none;
        }

        .col-header:hover {
            background: #eaeaea;
        }

        .col-header.active {
            background: #e0e0e0;
            color: var(--excel-green);
            border-bottom: 2px solid var(--excel-green);
        }

        .row-header {
            background: var(--header-bg);
            border-right: 1px solid var(--excel-border);
            border-bottom: 1px solid var(--excel-border);
            text-align: center;
            font-size: 0.75rem;
            color: var(--header-text);
            display: flex;
            align-items: center;
            justify-content: center;
            position: sticky;
            left: 0;
            width: 40px;
            z-index: 10;
            user-select: none;
        }

        .row-header:hover {
            background: #eaeaea;
        }

        .row-header.active {
            background: #e0e0e0;
            color: var(--excel-green);
            border-right: 2px solid var(--excel-green);
        }

        .select-all-btn {
            position: sticky;
            top: 0;
            left: 0;
            width: 40px;
            height: 24px;
            background: var(--header-bg);
            border-right: 1px solid var(--excel-border);
            border-bottom: 1px solid var(--excel-border);
            z-index: 20;
            cursor: pointer;
        }

        .select-all-btn::after {
            content: '';
            position: absolute;
            bottom: 4px;
            right: 4px;
            border-top: 6px solid transparent;
            border-left: 6px solid transparent;
            border-bottom: 6px solid #ccc;
        }

        /* Cells */
        .cell {
            background: white;
            border-right: 1px solid var(--grid-line);
            border-bottom: 1px solid var(--grid-line);
            padding: 0 4px;
            font-size: 13px;
            /* Standard excel font size */
            color: #000;
            outline: none;
            white-space: nowrap;
            overflow: hidden;
            display: flex;
            align-items: center;
            cursor: cell;
        }

        .cell.editing {
            cursor: text;
            background: white;
            z-index: 20;
            /* Above selection borders */
            outline: 2px solid var(--excel-green) !important;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        .cell.selected {
            outline: 2px solid var(--selection-border) !important;
            outline-offset: -2px;
            /* Inner outline */
            z-index: 5;
            position: relative;
        }

        /* Fill Handle Visual */
        .cell.selected::after {
            content: '';
            position: absolute;
            bottom: -4px;
            right: -4px;
            width: 7px;
            height: 7px;
            background: var(--excel-green);
            border: 1px solid white;
            z-index: 6;
            cursor: crosshair;
        }

        .cell.range-selected {
            background-color: var(--selection-bg);
            border: 1px double var(--selection-border) !important;
        }

        /* Status Bar (Bottom Sheet Tabs) */
        .status-bar {
            height: 32px;
            background: #f3f3f3;
            border-top: 1px solid var(--excel-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0;
            flex-shrink: 0;
            font-size: 0.8rem;
        }

        .sheet-tabs {
            display: flex;
            align-items: center;
            height: 100%;
            padding-left: 10px;
            gap: 2px;
            overflow-x: auto;
            white-space: nowrap;
            max-width: 100%;
            scrollbar-width: none;
            /* Hide scrollbar for cleaner look */
        }

        .sheet-tabs::-webkit-scrollbar {
            display: none;
        }

        .nav-arrows {
            display: flex;
            gap: 12px;
            color: #666;
            margin-right: 12px;
            font-size: 0.7rem;
            opacity: 0.5;
            /* Disabled look for now */
        }

        .sheet-tab {
            padding: 0 16px;
            height: 28px;
            display: flex;
            align-items: center;
            background: #e0e0e0;
            color: #666;
            font-weight: 500;
            border-radius: 4px 4px 0 0;
            margin-top: 4px;
            position: relative;
            cursor: pointer;
            border: 1px solid transparent;
            min-width: 60px;
            justify-content: center;
            transition: all 0.2s;
        }

        .sheet-tab:hover {
            background: #eaeaea;
        }

        .sheet-tab.active {
            background: white;
            color: var(--excel-green);
            font-weight: 700;
            box-shadow: 0 -1px 3px rgba(0, 0, 0, 0.1);
        }

        .sheet-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--excel-green);
        }

        .new-sheet-btn {
            width: 28px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            cursor: pointer;
            color: #666;
            font-size: 0.9rem;
            transition: 0.2s;
        }

        .new-sheet-btn:hover {
            background: #e0e0e0;
            color: black;
        }

        .status-right {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-right: 16px;
            color: #666;
        }

        .zoom-slider {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .zoom-track {
            width: 100px;
            height: 3px;
            background: #ccc;
            border-radius: 2px;
            position: relative;
        }

        .zoom-thumb {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #666;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        /* Loading Overlay */
        .loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            color: var(--excel-green);
            font-weight: 500;
            gap: 12px;
        }

        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--excel-green);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Custom Scrollbar for Grid */
        .grid-scroll-area::-webkit-scrollbar {
            width: 14px;
            height: 14px;
        }

        .grid-scroll-area::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .grid-scroll-area::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border: 3px solid #f1f1f1;
            border-radius: 8px;
        }

        .grid-scroll-area::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .grid-scroll-area::-webkit-scrollbar-corner {
            background: #f1f1f1;
        }

        /* Resize Handles */
        .resizer-col {
            position: absolute;
            top: 0;
            right: 0;
            width: 5px;
            bottom: 0;
            cursor: col-resize;
            z-index: 20;
        }

        .resizer-col:hover {
            background: var(--excel-green);
        }

        .resizer-row {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 5px;
            cursor: row-resize;
            z-index: 20;
        }

        .resizer-row:hover {
            background: var(--excel-green);
        }

        /* Multi-selection Borders */
        .cell.sel-border-top {
            border-top: 2px solid var(--excel-green) !important;
        }

        .cell.sel-border-bottom {
            border-bottom: 2px solid var(--excel-green) !important;
        }

        .cell.sel-border-left {
            border-left: 2px solid var(--excel-green) !important;
        }

        .cell.sel-border-right {
            border-right: 2px solid var(--excel-green) !important;
        }

        .cell.selected {
            outline: 2px solid var(--excel-green) !important;
            outline-offset: -2px;
            z-index: 10;
            background-color: white;
            /* Active cell usually white */
        }

        .cell.range-selected {
            background-color: var(--selection-bg);
            /* Borders handled by specific classes now */
        }

        .color-menu {
            position: absolute;
            top: 110%;
            left: 0;
            background: white;
            border: 1px solid #ccc;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 10px;
            z-index: 100;
            display: none;
            border-radius: 6px;
            width: 170px;
            flex-direction: column;
            gap: 8px;
        }

        .color-palette {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 4px;
            margin-bottom: 4px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }

        .color-swatch {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: transform 0.1s;
        }

        .color-swatch:hover {
            transform: scale(1.2);
            z-index: 2;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            border-color: #666;
        }

        .color-menu.show {
            display: flex;
        }

        .color-field {
            width: 100%;
            height: 32px;
            cursor: pointer;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0;
            background: none;
        }

        .apply-btn {
            background-color: var(--excel-green);
            color: white;
            border: none;
            padding: 6px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            width: 100%;
            font-weight: 500;
        }

        .apply-btn:hover {
            background-color: var(--excel-dark-green);
        }

        /* Context Menu */
        .context-menu {
            position: absolute;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 4px 8px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            display: none;
            flex-direction: column;
            width: 160px;
            border-radius: 12px;
            padding: 6px;
            animation: menuFadeIn 0.15s ease-out;
        }

        @keyframes menuFadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .context-menu-item {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 0.9rem;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 8px;
            transition: all 0.1s;
            font-weight: 500;
        }

        .context-menu-item:hover {
            background-color: #f3f4f6;
            color: #111;
        }

        .context-menu-item i {
            font-size: 1rem;
            color: #6b7280;
            width: 20px;
            text-align: center;
        }

        .context-menu-item:hover i {
            color: #4b5563;
        }

        .context-menu-item.danger {
            color: #ef4444;
        }

        .context-menu-item.danger i {
            color: #ef4444;
        }

        .context-menu-item.danger:hover {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .context-menu-divider {
            height: 1px;
            background: #eee;
            margin: 4px 0;
            width: 100%;
        }
    </style>
</head>

<body>

    <!-- Top Bar -->
    <div class="excel-title-bar">
        <div class="title-left">
            <a href="javascript:history.back()" class="back-btn" title="Back to Dashboard">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="app-icon"><i class="fa-solid fa-table"></i></div>
            <div class="file-info">
                <input type="text" id="sheetTitle" class="file-name-input" value="Untitled Spreadsheet">
                <div class="file-meta-row"
                    style="display:flex; align-items:center; gap:12px; font-size:0.75rem; color: rgba(255, 255, 255, 0.9); margin-top:2px;">
                    <div class="save-indicator" id="saveIndicator">
                        <i class="fa-regular fa-circle-check"></i> Saved
                    </div>
                    <div class="date-info" id="dateInfo"
                        style="display:flex; gap:12px; border-left:1px solid rgba(255, 255, 255, 0.3); padding-left:12px;">
                    </div>
                </div>
            </div>
        </div>
        <div class="title-right">

            <!-- User Profile -->
            <div class="user-profile" title="<?php echo htmlspecialchars($user['username']); ?>">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <!-- Toolbar (Simplified) -->
    <div class="ribbon-container">
        <!-- Removed Tabs -->
        <div class="ribbon-toolbar single-row">

            <!-- Font Size -->
            <div class="tool-group">
                <div class="select-wrapper">
                    <select id="fontSize" onchange="runCmd('fontSize', this.value + 'px')" title="Font Size"
                        style="width: 50px;">
                        <option value="10">10</option>
                        <option value="11" selected>11</option>
                        <option value="12">12</option>
                        <option value="14">14</option>
                        <option value="16">16</option>
                        <option value="18">18</option>
                        <option value="24">24</option>
                    </select>
                </div>
            </div>

            <!-- Alignments -->
            <div class="tool-group">
                <button class="tool-btn btn-small" onclick="runCmd('textAlign', 'left')" title="Align Left"><i
                        class="fa-solid fa-align-left"></i></button>
                <button class="tool-btn btn-small" onclick="runCmd('textAlign', 'center')" title="Center"><i
                        class="fa-solid fa-align-center"></i></button>
                <button class="tool-btn btn-small" onclick="runCmd('textAlign', 'right')" title="Align Right"><i
                        class="fa-solid fa-align-right"></i></button>
                <button class="tool-btn btn-small" onclick="runCmd('whiteSpace', 'wrap')" id="btnWrap"
                    title="Wrap Text"><i class="fa-solid fa-turn-down"
                        style="transform: rotate(90deg) scaleX(-1); font-size: 0.8rem;"></i></button>
            </div>

            <!-- Colors -->
            <div class="tool-group">
                <div style="position:relative;">
                    <button class="tool-btn btn-small" onclick="toggleColorMenu('TextColor')" title="Font Color">
                        <i class="fa-solid fa-font"></i>
                        <div id="indicatorColor"
                            style="position:absolute;bottom:3px;left:4px;right:4px;height:3px;background:red;"></div>
                    </button>
                    <div id="menuTextColor" class="color-menu">
                        <div class="color-palette" id="paletteTextColor"></div>
                        <input type="color" id="inputTextColor" class="color-field" value="#000000">
                        <button class="apply-btn" onclick="applyColor('color')">Apply</button>
                    </div>
                </div>

                <div style="position:relative;">
                    <button class="tool-btn btn-small" onclick="toggleColorMenu('FillColor')" title="Cell Background">
                        <i class="fa-solid fa-fill-drip"></i>
                        <div id="indicatorBg"
                            style="position:absolute;bottom:3px;left:4px;right:4px;height:3px;background:yellow;"></div>
                    </button>
                    <div id="menuFillColor" class="color-menu">
                        <div class="color-palette" id="paletteFillColor">
                            <div class="color-swatch"
                                style="background:#fff;border:1px solid #ccc;grid-column:span 6; width:100%; text-align:center; font-size:0.7rem; display:flex; align-items:center; justify-content:center;"
                                onclick="pickColor('backgroundColor', 'transparent')">No Fill</div>
                        </div>
                        <input type="color" id="inputFillColor" class="color-field" value="#ffffff">
                        <button class="apply-btn" onclick="applyColor('backgroundColor')">Apply</button>
                    </div>
                </div>
            </div>

            <!-- Autofit -->
            <div class="tool-group">
                <button class="tool-btn btn-wide" onclick="autoFit('col')" title="Autofit Column Width">
                    <i class="fa-solid fa-arrows-left-right-to-line"></i> <span>Autofit Col</span>
                </button>
                <button class="tool-btn btn-wide" onclick="autoFit('row')" title="Autofit Row Height">
                    <i class="fa-solid fa-arrows-up-down-left-right"></i> <span>Autofit Row</span>
                </button>
            </div>

            <!-- Save (Keep active save button for utility) -->
            <div class="tool-group" style="margin-left:auto; border:none;">
                <button class="tool-btn btn-small" onclick="saveSheet()" title="Save">
                    <i class="fa-solid fa-floppy-disk"></i>
                </button>
            </div>

        </div>
    </div>

    <!-- Formula Bar -->
    <div class="formula-bar-container">
        <div class="name-box" id="nameBox">A1</div>
        <div class="formula-actions">
            <i class="fa-solid fa-xmark"></i>
            <i class="fa-solid fa-check"></i>
            <i class="fa-solid fa-function"></i>
        </div>
        <div class="formula-input-wrapper">
            <input type="text" id="formulaInput" class="formula-input" spellcheck="false">
        </div>
    </div>

    <!-- Grid -->
    <div class="grid-wrapper">
        <div class="grid-scroll-area custom-scrollbar" id="gridScrollArea">
            <div id="gridContainer" class="excel-grid">
                <div class="select-all-btn"></div>
                <!-- Generated by JS -->
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
            <div>Loading Spreadsheet...</div>
        </div>
    </div>

    <!-- Status Bar / Sheet Tabs -->
    <div class="status-bar">
        <div class="sheet-tabs">
            <div class="nav-arrows">
                <i class="fa-solid fa-chevron-left"></i>
                <i class="fa-solid fa-chevron-right"></i>
            </div>
            <div class="sheet-tab active">Sheet1</div>
            <div class="new-sheet-btn" title="New Sheet"><i class="fa-solid fa-plus"></i></div>
        </div>
        <div class="status-right">
            <span>Ready</span>
            <div class="zoom-slider">
                <i class="fa-solid fa-minus" style="font-size:0.7rem; cursor:pointer;"></i>
                <div class="zoom-track">
                    <div class="zoom-thumb"></div>
                </div>
                <i class="fa-solid fa-plus" style="font-size:0.7rem; cursor:pointer;"></i>
                <span style="font-size:0.75rem;">100%</span>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../src/components/ui/glass-toast.php'; ?>
    <?php include __DIR__ . '/../src/components/ui/glass-confirm.php'; ?>

    <script>
        // --- Core Logic ---
        const teamId = <?php echo $teamId; ?>;
        const sheetId = <?php echo $sheetId ? $sheetId : 'null'; ?>;
        const CONFIG = {
            rows: 100,
            cols: 26, // A-Z
            colWidth: 100,
            rowHeight: 24
        };

        // State
        let sheetData = {}; // key: "r-c" -> { v: val, s: styleObj }
        let activeCell = { r: 0, c: 0 };
        let selectionStart = { r: 0, c: 0 };
        let selectionEnd = { r: 0, c: 0 };
        let isDragging = false;
        let isSaving = false;
        let isEditing = false;
        let currentAssignedTo = [];

        // Dynamic sizes
        let colWidths = {};
        let rowHeights = {};

        document.addEventListener('DOMContentLoaded', () => {
            initGrid();
            if (sheetId) loadData();
            else document.getElementById('loadingOverlay').style.display = 'none';
        });

        // --- Grid Generation ---
        function initGrid() {
            renderGridCSS();
            const container = document.getElementById('gridContainer');
            container.innerHTML = '<div class="select-all-btn"></div>'; // Reset

            // 1. Column Headers (A-Z)
            for (let c = 0; c < CONFIG.cols; c++) {
                const header = document.createElement('div');
                header.className = 'col-header';
                header.innerText = String.fromCharCode(65 + c);
                header.dataset.col = c;
                header.style.gridRow = '1';
                header.style.gridColumn = String(c + 2);

                // Resizer
                const resizer = document.createElement('div');
                resizer.className = 'resizer-col';
                resizer.dataset.col = c;
                header.appendChild(resizer);

                container.appendChild(header);
            }

            // 2. Row Headers & Cells
            for (let r = 0; r < CONFIG.rows; r++) {
                // Row Header
                const rh = document.createElement('div');
                rh.className = 'row-header';
                rh.innerText = r + 1;
                rh.dataset.row = r;
                rh.style.gridColumn = '1';
                rh.style.gridRow = String(r + 2);

                // Resizer
                const resizer = document.createElement('div');
                resizer.className = 'resizer-row';
                resizer.dataset.row = r;
                rh.appendChild(resizer);

                container.appendChild(rh);

                // ... inside initGrid loop
                // Cells
                for (let c = 0; c < CONFIG.cols; c++) {
                    const cell = document.createElement('div');
                    cell.className = 'cell';
                    cell.contentEditable = false; // Changed from true
                    cell.dataset.r = r;
                    cell.dataset.c = c;
                    cell.style.gridColumn = String(c + 2);
                    cell.style.gridRow = String(r + 2);

                    // Bind Events
                    cell.addEventListener('mousedown', (e) => onCellMouseDown(e, r, c));
                    cell.addEventListener('mouseenter', (e) => onCellMouseEnter(e, r, c));
                    cell.addEventListener('dblclick', (e) => startEditing(r, c)); // New
                    cell.addEventListener('input', (e) => updateValue(r, c, e.target.innerText));
                    // Keydown handled globally or on container now for navigation

                    container.appendChild(cell);
                }
            }

            initResizers();

            // Global key listener for navigation/shortcuts
            document.removeEventListener('keydown', handleGlobalKey);
            document.addEventListener('keydown', handleGlobalKey);

            // Global paste listener
            document.removeEventListener('paste', handlePaste);
            document.addEventListener('paste', handlePaste);
        }

        function initResizers() {
            let startX, startY, startWidth, startHeight, currentResizer;

            // Col Resize
            document.querySelectorAll('.resizer-col').forEach(r => {
                r.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    currentResizer = r;
                    startX = e.clientX;
                    const col = r.dataset.col;
                    startWidth = colWidths[col] || CONFIG.colWidth;

                    document.addEventListener('mousemove', onMouseMoveCol);
                    document.addEventListener('mouseup', onMouseUpCol);
                });
            });

            const onMouseMoveCol = (e) => {
                const diff = e.clientX - startX;
                const newW = Math.max(40, startWidth + diff);
                const col = currentResizer.dataset.col;
                colWidths[col] = newW;
                renderGridCSS();
            };

            const onMouseUpCol = () => {
                document.removeEventListener('mousemove', onMouseMoveCol);
                document.removeEventListener('mouseup', onMouseUpCol);
                setUnsaved();
            };

            // Row Resize
            document.querySelectorAll('.resizer-row').forEach(r => {
                r.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    currentResizer = r;
                    startY = e.clientY;
                    const row = r.dataset.row;
                    startHeight = rowHeights[row] || CONFIG.rowHeight;

                    document.addEventListener('mousemove', onMouseMoveRow);
                    document.addEventListener('mouseup', onMouseUpRow);
                });
            });

            const onMouseMoveRow = (e) => {
                const diff = e.clientY - startY;
                const newH = Math.max(20, startHeight + diff);
                const row = currentResizer.dataset.row;
                rowHeights[row] = newH;
                renderGridCSS();
            };

            const onMouseUpRow = () => {
                document.removeEventListener('mousemove', onMouseMoveRow);
                document.removeEventListener('mouseup', onMouseUpRow);
                setUnsaved();
            };
        }

        function renderGridCSS() {
            const container = document.getElementById('gridContainer');
            // Generate columns CSS
            let colsCss = '40px';
            for (let c = 0; c < CONFIG.cols; c++) {
                colsCss += ' ' + (colWidths[c] ? colWidths[c] + 'px' : CONFIG.colWidth + 'px');
            }
            container.style.gridTemplateColumns = colsCss;

            // Generate rows CSS
            let rowsCss = '24px'; // Header height
            for (let r = 0; r < CONFIG.rows; r++) {
                rowsCss += ' ' + (rowHeights[r] ? rowHeights[r] + 'px' : CONFIG.rowHeight + 'px');
            }
            container.style.gridTemplateRows = rowsCss;
        }

        // --- Data Handling ---

        // Global workbook state
        let workbook = [];
        let currentSheetIndex = 0;

        async function loadData() {
            try {
                const res = await fetch(`/api/spreadsheet.php?id=${sheetId}&team_id=${teamId}`);
                const json = await res.json();
                if (json.success && json.data) {
                    document.getElementById('sheetTitle').value = json.data.title;

                    // Populate Date Info
                    const dateInfo = document.getElementById('dateInfo');
                    if (json.data.created_at || json.data.updated_at) {
                        const formatOptions = { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
                        const created = json.data.created_at ? new Date(json.data.created_at).toLocaleString(undefined, formatOptions) : '-';
                        const updated = json.data.updated_at ? new Date(json.data.updated_at).toLocaleString(undefined, formatOptions) : '-';
                        dateInfo.innerHTML = `<span id="createdAtDisplay">Created: ${created}</span><span id="updatedAtDisplay">Updated: ${updated}</span>`;
                    }

                    try {
                        const content = json.data.content ? JSON.parse(json.data.content) : null;

                        if (content && content.sheets) {
                            workbook = content.sheets;
                            currentSheetIndex = content.currentSheetIndex || 0;
                        } else {
                            // Migration or Empty
                            let cells = {};
                            let cWidths = {};
                            let rHeights = {};

                            if (content) {
                                if (Array.isArray(content)) {
                                    content.forEach((row, r) => {
                                        row.forEach((v, c) => {
                                            if (v) cells[`${r}-${c}`] = { v: v };
                                        });
                                    });
                                } else {
                                    cells = content.cells || content;
                                    cWidths = content.colWidths || {};
                                    rHeights = content.rowHeights || {};
                                }
                            }
                            workbook = [{ name: 'Sheet1', cells, colWidths: cWidths, rowHeights: rHeights }];
                            currentSheetIndex = 0;
                        }
                    } catch (e) {
                        workbook = [{ name: 'Sheet1', cells: {}, colWidths: {}, rowHeights: {} }];
                        currentSheetIndex = 0;
                    }

                    if (json.data.assigned_to) {
                        try {
                            let parsed = json.data.assigned_to;
                            if (typeof parsed === 'string') {
                                parsed = JSON.parse(parsed);
                            }

                            if (Array.isArray(parsed)) {
                                currentAssignedTo = parsed.map(Number);
                            } else if (parsed) {
                                currentAssignedTo = [Number(parsed)];
                            }
                        } catch (e) {
                            currentAssignedTo = [Number(json.data.assigned_to)]; // Legacy single ID
                        }
                    } else {
                        currentAssignedTo = [];
                    }

                    loadSheetAtIndex(currentSheetIndex);
                }
            } catch (e) {
                console.error("Load Data Error:", e);
                if (window.Toast) Toast.error("Error", "Failed to load sheet data");
            } finally {
                const loader = document.getElementById('loadingOverlay');
                if (loader) loader.style.display = 'none';
            }
        }

        function loadSheetAtIndex(idx) {
            if (idx < 0 || idx >= workbook.length) idx = 0;
            currentSheetIndex = idx;
            const sheet = workbook[idx];
            sheetData = sheet.cells || {};
            colWidths = sheet.colWidths || {};
            rowHeights = sheet.rowHeights || {};

            // Reset interaction
            isEditing = false;
            // Always reset focus to top-left on sheet change for consistency
            // Or keep relative position if possible? No, safer to reset.
            activeCell = { r: 0, c: 0 };
            selectionStart = { r: 0, c: 0 };
            selectionEnd = { r: 0, c: 0 };

            initGrid();
            renderCells();
            renderTabs();

            // Force focus
            updateSelectionUI();
        }

        // ... existing renderTabs
        function renderTabs() {
            const container = document.querySelector('.sheet-tabs');
            let html = `
            <div class="nav-arrows">
                <i class="fa-solid fa-chevron-left"></i>
                <i class="fa-solid fa-chevron-right"></i>
            </div>`;

            workbook.forEach((sheet, i) => {
                const active = i === currentSheetIndex ? 'active' : '';
                // Added oncontextmenu
                html += `<div class="sheet-tab ${active}" onclick="switchSheet(${i})" ondblclick="renameSheet(event, ${i})" oncontextmenu="showSheetMenu(event, ${i})">${sheet.name}</div>`;
            });

            html += `<div class="new-sheet-btn" onclick="addSheet()" title="New Sheet"><i class="fa-solid fa-plus"></i></div>`;

            container.innerHTML = html;
        }

        // New Context Menu Logic
        let contextMenuTargetIndex = -1;

        function showSheetMenu(e, index) {
            e.preventDefault();
            contextMenuTargetIndex = index;

            const menu = document.getElementById('sheetContextMenu');
            menu.style.display = 'flex';

            // Positioning Logic
            const menuWidth = 160;
            const menuHeight = menu.offsetHeight || 120;
            let x = e.clientX;
            let y = e.clientY - menuHeight - 10;

            // Ensure within bounds
            if (x + menuWidth > window.innerWidth) x = window.innerWidth - menuWidth - 10;
            if (y < 0) y = e.clientY + 10;

            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
        }

        function deleteSheet() {
            if (contextMenuTargetIndex === -1) return;
            if (workbook.length <= 1) {
                if (window.Toast) Toast.error("Action Failed", "Cannot delete the last sheet.");
                return;
            }

            const sheetName = workbook[contextMenuTargetIndex].name;

            // Close menu first so it doesn't overlap/stay open
            closeSheetMenu();

            if (typeof Confirm !== 'undefined') {
                Confirm.show({
                    title: 'Delete Sheet?',
                    text: `Are you sure you want to delete "${sheetName}"? This cannot be undone.`,
                    confirmText: 'Delete',
                    type: 'danger',
                    onConfirm: () => {
                        performDeleteSheet();
                    }
                });
            } else {
                if (confirm(`Delete sheet "${sheetName}"?`)) {
                    performDeleteSheet();
                }
            }
        }

        function performDeleteSheet() {
            workbook.splice(contextMenuTargetIndex, 1);

            // Adjust current index
            if (currentSheetIndex >= workbook.length) {
                currentSheetIndex = workbook.length - 1;
            } else if (currentSheetIndex === contextMenuTargetIndex) {
                // If we deleted the active sheet, stay at current index (which is now next sheet) or go back
                if (currentSheetIndex >= workbook.length) currentSheetIndex = workbook.length - 1;
            } else if (currentSheetIndex > contextMenuTargetIndex) {
                currentSheetIndex--;
            }

            loadSheetAtIndex(currentSheetIndex);
            setUnsaved();
        }

        function duplicateSheet() {
            if (contextMenuTargetIndex === -1 || !workbook[contextMenuTargetIndex]) {
                if (window.Toast) Toast.error("Error", "No valid sheet selected to duplicate.");
                return;
            }

            try {
                // Save current state first to ensure latest edits are propagated
                saveCurrentSheetState();

                const srcSheet = workbook[contextMenuTargetIndex];

                // Generate unique name
                let baseName = srcSheet.name + " (Copy)";
                let counter = 1;
                // Ensure unique name if multiple copies exist
                while (workbook.some(s => s.name === baseName)) {
                    baseName = srcSheet.name + ` (Copy ${++counter})`;
                }

                // Deep copy data
                const newSheet = {
                    name: baseName,
                    cells: srcSheet.cells ? JSON.parse(JSON.stringify(srcSheet.cells)) : {},
                    colWidths: srcSheet.colWidths ? JSON.parse(JSON.stringify(srcSheet.colWidths)) : {},
                    rowHeights: srcSheet.rowHeights ? JSON.parse(JSON.stringify(srcSheet.rowHeights)) : {}
                };

                // Insert after current
                workbook.splice(contextMenuTargetIndex + 1, 0, newSheet);

                // Switch to new sheet
                loadSheetAtIndex(contextMenuTargetIndex + 1);
                setUnsaved();

                if (window.Toast) Toast.success("Success", "Sheet duplicated successfully.");
            } catch (err) {
                console.error(err);
                if (window.Toast) Toast.error("Error", "Failed to duplicate sheet: " + err.message);
            } finally {
                closeSheetMenu();
            }
        }

        function renameSheetFromMenu() {
            if (contextMenuTargetIndex === -1) return;

            // Find the specific tab element by index
            // We can't use querySelectorAll('.sheet-tab') reliably if we have other elements with that class elsewhere?
            // Since they are generated in order, querySelectorAll should match the workbook index.
            const tabs = document.querySelectorAll('.sheet-tab');
            const tab = tabs[contextMenuTargetIndex];

            if (tab) {
                // Manually trigger the rename logic
                // We recreate the event-like structure needed by renameSheet
                renameSheet({ target: tab }, contextMenuTargetIndex);
            }
            closeSheetMenu();
        }

        function closeSheetMenu() {
            document.getElementById('sheetContextMenu').style.display = 'none';
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#sheetContextMenu')) closeSheetMenu();
        });

        // ... existing switchSheet

        function switchSheet(i) {
            if (i === currentSheetIndex) return;
            // Save current state to workbook before switching
            saveCurrentSheetState();
            loadSheetAtIndex(i);
        }

        function saveCurrentSheetState() {
            workbook[currentSheetIndex].cells = sheetData;
            workbook[currentSheetIndex].colWidths = colWidths;
            workbook[currentSheetIndex].rowHeights = rowHeights;
        }

        function addSheet() {
            saveCurrentSheetState();
            const newName = `Sheet${workbook.length + 1}`;
            workbook.push({ name: newName, cells: {}, colWidths: {}, rowHeights: {} });
            loadSheetAtIndex(workbook.length - 1);
            setUnsaved();
        }

        function renameSheet(e, i) {
            const tab = e.target;
            // If already renaming (input exists), ignore
            if (tab.querySelector('input')) return;
            if (tab.tagName === 'INPUT') return; // clicked the input itself

            const originalName = workbook[i].name;

            const input = document.createElement('input');
            input.type = 'text';
            input.value = originalName;
            input.style.width = '80px';
            input.style.border = 'none';
            input.style.outline = 'none';
            input.style.background = 'transparent';
            input.style.fontWeight = 'bold';
            input.style.color = 'inherit'; // Use parent color (green/grey)
            input.style.fontFamily = 'inherit';
            input.style.fontSize = 'inherit';

            const finishRename = () => {
                const newName = input.value.trim() || originalName;
                workbook[i].name = newName;
                renderTabs();
                setUnsaved();
            };

            input.onblur = finishRename;

            input.onkeydown = (ev) => {
                if (ev.key === 'Enter') input.blur();
                // if esc, cancel?
            };

            tab.innerText = '';
            tab.appendChild(input);
            input.focus();
            // Select all text
            input.select();
        }

        function renderCells() {
            for (const key in sheetData) {
                const [r, c] = key.split('-').map(Number);
                const cell = document.querySelector(`.cell[data-r="${r}"][data-c="${c}"]`);
                if (cell) {
                    const d = sheetData[key];
                    if (d.v !== undefined) cell.innerText = d.v;
                    if (d.s) applyCSS(cell, d.s);
                }
            }
        }

        async function saveSheet() {
            if (!sheetId) return;
            isSaving = true;
            document.getElementById('saveIndicator').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            saveCurrentSheetState(); // Update workbook with current view

            const saveContent = {
                sheets: workbook,
                currentSheetIndex: currentSheetIndex
            };

            try {
                const res = await fetch('/api/spreadsheet.php', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: sheetId,
                        title: document.getElementById('sheetTitle').value,
                        content: JSON.stringify(saveContent)
                    })
                });
                const json = await res.json();
                if (json.success) {
                    document.getElementById('saveIndicator').innerHTML = '<i class="fa-regular fa-circle-check"></i> Saved';

                    // Update Updated At time
                    if (json.updated_at) {
                        const updated = new Date(json.updated_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                        const upDisplay = document.getElementById('updatedAtDisplay');
                        if (upDisplay) upDisplay.innerText = `Updated: ${updated}`;
                    }

                    if (window.Toast) Toast.success("Saved", "Spreadsheet saved");
                }
            } catch (e) {
                document.getElementById('saveIndicator').innerText = "Error";
                if (window.Toast) Toast.error("Error", "Failed to save");
            } finally {
                isSaving = false;
            }
        }

        // --- Interaction ---

        function onCellMouseDown(e, r, c) {
            isDragging = true;
            activeCell = { r, c };
            selectionStart = { r, c };
            selectionEnd = { r, c };

            updateSelectionUI();
            updateToolbarFromCell(r, c);
        }

        function onCellMouseEnter(e, r, c) {
            if (isDragging) {
                selectionEnd = { r, c };
                updateSelectionUI();
            }
        }

        function selectCell(r, c) {
            if (activeCell.r === r && activeCell.c === c) return;

            activeCell = { r, c };
            selectionStart = { r, c };
            selectionEnd = { r, c };

            updateSelectionUI();
            updateToolbarFromCell(r, c);

            const cell = document.querySelector(`.cell[data-r="${r}"][data-c="${c}"]`);
            if (cell) cell.focus();
        }

        function updateSelectionUI() {
            const rMin = Math.min(selectionStart.r, selectionEnd.r);
            const rMax = Math.max(selectionStart.r, selectionEnd.r);
            const cMin = Math.min(selectionStart.c, selectionEnd.c);
            const cMax = Math.max(selectionStart.c, selectionEnd.c);

            // Cleanup
            const classesToRemove = ['selected', 'range-selected', 'sel-border-top', 'sel-border-bottom', 'sel-border-left', 'sel-border-right'];
            classesToRemove.forEach(cls => {
                document.querySelectorAll('.' + cls).forEach(el => el.classList.remove(cls));
            });
            document.querySelectorAll('.col-header.active').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.row-header.active').forEach(el => el.classList.remove('active'));

            // Apply style
            for (let r = rMin; r <= rMax; r++) {
                document.querySelector(`.row-header[data-row="${r}"]`)?.classList.add('active');
                for (let c = cMin; c <= cMax; c++) {
                    const cell = document.querySelector(`.cell[data-r="${r}"][data-c="${c}"]`);
                    if (cell) {
                        if (r === activeCell.r && c === activeCell.c) {
                            cell.classList.add('selected');
                        } else {
                            cell.classList.add('range-selected');
                        }

                        // Borders
                        if (r === rMin) cell.classList.add('sel-border-top');
                        if (r === rMax) cell.classList.add('sel-border-bottom');
                        if (c === cMin) cell.classList.add('sel-border-left');
                        if (c === cMax) cell.classList.add('sel-border-right');
                    }
                }
            }
            for (let c = cMin; c <= cMax; c++) {
                document.querySelector(`.col-header[data-col="${c}"]`)?.classList.add('active');
            }

            // Name Box
            if (rMin === rMax && cMin === cMax) {
                document.getElementById('nameBox').innerText = String.fromCharCode(65 + cMin) + (rMin + 1);
                const val = sheetData[`${rMin}-${cMin}`]?.v || '';
                document.getElementById('formulaInput').value = val;
            } else {
                document.getElementById('nameBox').innerText =
                    `${String.fromCharCode(65 + cMin)}${rMin + 1}:${String.fromCharCode(65 + cMax)}${rMax + 1}`;
                document.getElementById('formulaInput').value = '';
            }
        }

        function updateToolbarFromCell(r, c) {
            const key = `${r}-${c}`;
            updateToolbarUI(sheetData[key]?.s);
        }

        function updateValue(r, c, val) {
            const key = `${r}-${c}`;
            if (!sheetData[key]) sheetData[key] = {};
            sheetData[key].v = val;

            if (activeCell.r === r && activeCell.c === c) {
                document.getElementById('formulaInput').value = val;
            }

            setUnsaved();
        }

        document.getElementById('formulaInput').addEventListener('input', (e) => {
            const { r, c } = activeCell;
            const cell = document.querySelector(`.cell[data-r="${r}"][data-c="${c}"]`);
            if (cell) {
                cell.innerText = e.target.value;
                updateValue(r, c, e.target.value);
            }
        });

        // --- Advanced Interaction ---

        function getCell(r, c) {
            return document.querySelector(`.cell[data-r="${r}"][data-c="${c}"]`);
        }

        function startEditing(r, c, clear = false) {
            if (isEditing) return;
            isEditing = true;

            const cell = getCell(r, c);
            if (!cell) return;

            cell.contentEditable = true;
            cell.focus();
            cell.classList.add('editing'); // Helps styling if needed

            if (clear) {
                cell.innerText = '';
                updateValue(r, c, '');
            } else {
                // Place cursor at end
                const range = document.createRange();
                const sel = window.getSelection();
                range.selectNodeContents(cell);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }

        function stopEditing() {
            if (!isEditing) return;
            isEditing = false;

            const { r, c } = activeCell;
            const cell = getCell(r, c);
            if (cell) {
                cell.contentEditable = false;
                cell.classList.remove('editing');
                updateValue(r, c, cell.innerText); // Ensure save
                // Refocus just the div for navigation to work? 
                // Actually div isn't focusable by default if contentEditable false unless tabindex set
                // But we handle global keys, so focus doesn't strictly matter as long as we know activeCell.
                // Let's keep focus there for now.
            }
            setUnsaved();
        }

        async function handleGlobalKey(e) {
            // If we are typing in formula bar, ignore grid nav
            if (document.activeElement.id === 'formulaInput' || document.activeElement.id === 'sheetTitle') return;

            // Editing Mode Logic
            if (isEditing) {
                const { r, c } = activeCell;
                if (e.key === 'Enter') {
                    e.preventDefault();
                    stopEditing();
                    if (e.shiftKey) moveFocus(r - 1, c);
                    else moveFocus(r + 1, c);
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    stopEditing();
                    if (e.shiftKey) moveFocus(r, c - 1);
                    else moveFocus(r, c + 1);
                }
                // Allow other keys (arrows) to work natively in contentEditable
                return;
            }

            // Navigation Mode Logic
            const { r, c } = activeCell;

            // Copy (Ctrl+C)
            if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                e.preventDefault();
                copySelection();
                return;
            }

            // Undo/Redo hook (future)

            // Arrows
            if (e.key.startsWith('Arrow')) {
                e.preventDefault();
                let nr = activeCell.r;
                let nc = activeCell.c;

                // If SHIFT is held, we are extending SELECTION only
                // If SHIFT is NOT held, we move ACTIVE CELL and reset selection

                if (e.shiftKey) {
                    // Extend selection from anchor? 
                    // Excel logic: Anchor is activeCell? No, anchor is fixed.
                    // Active cell stays fixed, selectionEnd moves? 
                    // Actually Excel moves the "active corner" of selection.
                    // Simplified: always extend `selectionEnd`.
                    let sr = selectionEnd.r;
                    let sc = selectionEnd.c;

                    if (e.key === 'ArrowUp') sr = Math.max(0, sr - 1);
                    if (e.key === 'ArrowDown') sr = Math.min(CONFIG.rows - 1, sr + 1);
                    if (e.key === 'ArrowLeft') sc = Math.max(0, sc - 1);
                    if (e.key === 'ArrowRight') sc = Math.min(CONFIG.cols - 1, sc + 1);

                    selectionEnd = { r: sr, c: sc };
                    updateSelectionUI();
                } else {
                    // Move Active Cell
                    if (e.key === 'ArrowUp') nr = Math.max(0, nr - 1);
                    if (e.key === 'ArrowDown') nr = Math.min(CONFIG.rows - 1, nr + 1);
                    if (e.key === 'ArrowLeft') nc = Math.max(0, nc - 1);
                    if (e.key === 'ArrowRight') nc = Math.min(CONFIG.cols - 1, nc + 1);

                    selectCell(nr, nc); // This resets selectionStart/End to activeCell
                }
                return;
            }

            // Enter / Tab
            if (e.key === 'Enter') {
                e.preventDefault();
                if (e.shiftKey) moveFocus(r - 1, c);
                else moveFocus(r + 1, c);
                return;
            }
            if (e.key === 'Tab') {
                e.preventDefault();
                if (e.shiftKey) moveFocus(r, c - 1);
                else moveFocus(r, c + 1);
                return;
            }

            // Delete / Backspace
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                clearSelection();
                return;
            }

            // Typing to start edit
            // Ignore Ctrl/Alt/Meta combos
            if (!e.ctrlKey && !e.altKey && !e.metaKey && e.key.length === 1) {
                // Start editing with this char
                startEditing(r, c, true);
                // The char will be lost unless we manually insert it or let it bubble?
                // Since we focused & cleared, just setting text content is easier.
                const cell = getCell(r, c);
                if (cell) {
                    cell.innerText = e.key;
                    // Set cursor to end
                    const range = document.createRange();
                    const sel = window.getSelection();
                    range.selectNodeContents(cell);
                    range.collapse(false);
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                e.preventDefault();
            }

            // F2 to edit
            if (e.key === 'F2') {
                e.preventDefault();
                startEditing(r, c, false);
            }
        }

        // --- Clipboard Ops ---

        async function copySelection() {
            const rMin = Math.min(selectionStart.r, selectionEnd.r);
            const rMax = Math.max(selectionStart.r, selectionEnd.r);
            const cMin = Math.min(selectionStart.c, selectionEnd.c);
            const cMax = Math.max(selectionStart.c, selectionEnd.c);

            let rows = [];
            for (let r = rMin; r <= rMax; r++) {
                let rowData = [];
                for (let c = cMin; c <= cMax; c++) {
                    const key = `${r}-${c}`;
                    const val = sheetData[key]?.v || '';
                    rowData.push(val);
                }
                rows.push(rowData.join('\t'));
            }
            const text = rows.join('\n');

            try {
                await navigator.clipboard.writeText(text);
                if (window.Toast) Toast.success("Copied", "Selection copied to clipboard");
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
        }

        async function handlePaste(e) {
            // Only handle if not editing text inside a cell
            if (isEditing && document.activeElement.classList.contains('cell')) return;
            if (document.activeElement.id === 'formulaInput') return;

            e.preventDefault();
            let text = (e.clipboardData || window.clipboardData).getData('text');
            if (!text) return;

            const rows = text.split(/\r\n|\n|\r/);
            let startR = activeCell.r;
            let startC = activeCell.c;

            rows.forEach((rowStr, i) => {
                if (!rowStr && i === rows.length - 1) return; // Skip trailing newline
                const cols = rowStr.split('\t');
                cols.forEach((val, j) => {
                    const r = startR + i;
                    const c = startC + j;
                    if (r < CONFIG.rows && c < CONFIG.cols) {
                        const key = `${r}-${c}`;
                        if (!sheetData[key]) sheetData[key] = {};
                        sheetData[key].v = val;

                        // Update visual
                        const cell = getCell(r, c);
                        if (cell) {
                            cell.innerText = val;
                            // Don't overwrite styles?
                        }
                    }
                });
            });

            setUnsaved();
            // Expand selection to cover pasted area?
            /*
            selectionStart = { r: startR, c: startC };
            selectionEnd = { r: startR + rows.length - 1, c: startC + rows[0].split('\t').length - 1 };
            updateSelectionUI();
            */
            if (window.Toast) Toast.success("Pasted", "Content pasted from clipboard");
        }

        function clearSelection() {
            const rMin = Math.min(selectionStart.r, selectionEnd.r);
            const rMax = Math.max(selectionStart.r, selectionEnd.r);
            const cMin = Math.min(selectionStart.c, selectionEnd.c);
            const cMax = Math.max(selectionStart.c, selectionEnd.c);

            for (let r = rMin; r <= rMax; r++) {
                for (let c = cMin; c <= cMax; c++) {
                    updateValue(r, c, '');
                    const cell = getCell(r, c);
                    if (cell) cell.innerText = '';
                }
            }
        }

        function handleKey(e) {
            // Deprecated, replaced by handleGlobalKey
        }

        function moveFocus(r, c) {
            if (r < 0 || c < 0 || r >= CONFIG.rows || c >= CONFIG.cols) return;
            selectCell(r, c);
        }

        // --- Styling Commands ---

        function runCmd(prop, val) {
            // Update color indicators
            if (prop === 'color') {
                const ind = document.getElementById('indicatorColor');
                if (ind) ind.style.background = val;
            }
            if (prop === 'backgroundColor') {
                const ind = document.getElementById('indicatorBg');
                if (ind) ind.style.background = val;
            }

            const rMin = Math.min(selectionStart.r, selectionEnd.r);
            const rMax = Math.max(selectionStart.r, selectionEnd.r);
            const cMin = Math.min(selectionStart.c, selectionEnd.c);
            const cMax = Math.max(selectionStart.c, selectionEnd.c);

            if (prop === 'whiteSpace') {
                // Toggle based on active cell
                const key = `${activeCell.r}-${activeCell.c}`;
                const cur = sheetData[key]?.s?.whiteSpace;
                val = (cur === 'normal') ? 'nowrap' : 'normal';
            }

            for (let r = rMin; r <= rMax; r++) {
                for (let c = cMin; c <= cMax; c++) {
                    const key = `${r}-${c}`;
                    if (!sheetData[key]) sheetData[key] = {};
                    if (!sheetData[key].s) sheetData[key].s = {};

                    sheetData[key].s[prop] = val;

                    // Special case for textAlign in flex container
                    if (prop === 'textAlign') {
                        let jc = val === 'left' ? 'flex-start' : (val === 'right' ? 'flex-end' : 'center');
                        sheetData[key].s['justifyContent'] = jc;
                    }

                    const cell = document.querySelector(`.cell[data-r="${r}"][data-c="${c}"]`);
                    if (cell) {
                        cell.style[prop] = val;
                        if (prop === 'textAlign') {
                            cell.style.justifyContent = val === 'left' ? 'flex-start' : (val === 'right' ? 'flex-end' : 'center');
                        }
                        if (prop === 'justifyContent') cell.style.justifyContent = val;
                        if (prop === 'whiteSpace') {
                            cell.style.whiteSpace = val;
                            cell.style.overflowWrap = val === 'normal' ? 'anywhere' : 'normal';
                            cell.style.wordBreak = val === 'normal' ? 'break-word' : 'normal';
                        }
                    }
                }
            }

            updateToolbarUI(sheetData[`${activeCell.r}-${activeCell.c}`]?.s);
            setUnsaved();
        }

        function autoFit(type) {
            const measureSpan = document.createElement('span');
            measureSpan.style.position = 'absolute';
            measureSpan.style.visibility = 'hidden';
            measureSpan.style.whiteSpace = 'nowrap';
            document.body.appendChild(measureSpan);

            const rMin = Math.min(selectionStart.r, selectionEnd.r);
            const rMax = Math.max(selectionStart.r, selectionEnd.r);
            const cMin = Math.min(selectionStart.c, selectionEnd.c);
            const cMax = Math.max(selectionStart.c, selectionEnd.c);

            // If single cell, autofit entire column/row based on all data
            // If range, autofit based on range?? No, standard behavior is usually autofit that column based on all data if double clicked.
            // But here we have "Autofit" buttons.
            // Let's autofit the COLUMNS/ROWS involved in the selection, considering ALL rows/cols in that selection.

            if (type === 'col') {
                for (let c = cMin; c <= cMax; c++) {
                    let maxW = 40;
                    // Iterate all rows for this col to find max width
                    for (let r = 0; r < CONFIG.rows; r++) {
                        const key = `${r}-${c}`;
                        const val = sheetData[key]?.v || '';
                        if (!val) continue;

                        const cell = document.querySelector(`.cell[data-r="${r}"][data-c="${c}"]`);
                        measureSpan.style.font = window.getComputedStyle(cell).font;
                        measureSpan.innerText = val;
                        const w = measureSpan.getBoundingClientRect().width;
                        if (w > maxW) maxW = w;
                    }
                    colWidths[c] = Math.ceil(maxW + 20);
                }
                renderGridCSS();
                if (window.Toast) Toast.success("Autofit", "Column width adjusted");
            } else if (type === 'row') {
                for (let r = rMin; r <= rMax; r++) {
                    let maxH = 20;
                    // Iterate all cols for this row
                    for (let c = 0; c < CONFIG.cols; c++) {
                        const key = `${r}-${c}`;
                        const val = sheetData[key]?.v || '';
                        if (!val) continue;

                        const cell = document.querySelector(`.cell[data-r="${r}"][data-c="${c}"]`);
                        const styles = sheetData[key].s || {};
                        if (styles.whiteSpace === 'normal') {
                            measureSpan.style.whiteSpace = 'normal';
                            measureSpan.style.width = (colWidths[c] || CONFIG.colWidth) + 'px';
                        } else {
                            measureSpan.style.whiteSpace = 'nowrap';
                            measureSpan.style.width = 'auto';
                        }

                        measureSpan.style.font = window.getComputedStyle(cell).font;
                        measureSpan.innerText = val;
                        const h = measureSpan.getBoundingClientRect().height;
                        if (h > maxH) maxH = h;
                    }
                    rowHeights[r] = Math.ceil(maxH + 10);
                }
                renderGridCSS();
                if (window.Toast) Toast.success("Autofit", "Row height adjusted");
            }

            document.body.removeChild(measureSpan);
            setUnsaved();
        }

        function applyCSS(el, styles) {
            for (const p in styles) el.style[p] = styles[p];
        }

        function updateToolbarUI(styles = {}) {
            document.getElementById('btnWrap').classList.toggle('active', styles.whiteSpace === 'normal');
            const fs = styles.fontSize ? parseInt(styles.fontSize) : 11;
            document.getElementById('fontSize').value = fs;
        }

        function setUnsaved() {
            document.getElementById('saveIndicator').innerText = "Unsaved changes";
        }

        setInterval(() => {
            if (document.getElementById('saveIndicator').innerText.includes("Unsaved")) {
                saveSheet();
            }
        }, 10000);

        // Add global mouseup listener if not added in initGrid (failsafe)
        document.addEventListener('mouseup', () => { isDragging = false; });

        // Initialize Color Palettes
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
            const createSwatch = (color, prop) => {
                const el = document.createElement('div');
                el.className = 'color-swatch';
                el.style.backgroundColor = color;
                el.title = color;
                el.onclick = () => pickColor(prop, color);
                return el;
            };

            const pText = document.getElementById('paletteTextColor');
            PALETTE_COLORS.forEach(c => pText.appendChild(createSwatch(c, 'color')));

            const pFill = document.getElementById('paletteFillColor');
            PALETTE_COLORS.forEach(c => pFill.appendChild(createSwatch(c, 'backgroundColor')));
        }

        // Call init
        initPalettes();

        function toggleColorMenu(type) {
            const menu = document.getElementById(`menu${type}`);
            if (!menu) return;

            // Check visibility BEFORE hiding others
            const wasVisible = menu.classList.contains('show');

            // Hide all menus
            document.querySelectorAll('.color-menu').forEach(el => el.classList.remove('show'));

            // Toggle desired menu
            if (!wasVisible) {
                menu.classList.add('show');
            }
        }

        // Override pickColor to be smarter
        function pickColor(prop, color) {
            if (color === 'transparent') {
                runCmd(prop, 'transparent');
                document.getElementById('indicatorBg').style.background = 'white'; // approximate
                document.querySelectorAll('.color-menu').forEach(el => el.classList.remove('show'));
                return;
            }

            let inputId = prop === 'color' ? 'inputTextColor' : 'inputFillColor';
            const input = document.getElementById(inputId);

            // Convert to hex if needed (though our array is hex)
            // If color is rgb, convert? Browsers handle color input differently.
            // Let's assume hex from our array.
            input.value = color;

            // Apply immediately? Or wait? 
            // "need a color pallet with apply button" implies the apply button is the main trigger.
            // BUT standard UX is click palette = apply.
            // Let's implement: Click Palette -> Updates Input (Preview?) -> User clicks Apply.
            // But updating input doesn't show preview on cells. 
            // It just updates the color picker box.
            // Let's make palette click APPLY IMMEDIATELY for better UX, and the Apply button is for the CUSTOM input below.

            applyColor(prop);
        }

        function applyColor(prop) {
            let inputId = prop === 'color' ? 'inputTextColor' : 'inputFillColor';
            let val = document.getElementById(inputId).value;

            // Handle "No Fill" case from palette interaction if we want to store state?
            // Actually, if they clicked "No Fill" swatch, we can't store "transparent" in the color input.
            // So we need a separate tracker or just apply immediately for No Fill?
            // Let's make "No Fill" apply immediately to avoid confusion.

            // For standard colors:
            // update indicator
            if (prop === 'color') document.getElementById('indicatorColor').style.background = val;
            if (prop === 'backgroundColor') document.getElementById('indicatorBg').style.background = val;

            runCmd(prop, val);

            // Hide menus
            document.querySelectorAll('.color-menu').forEach(el => el.classList.remove('show'));
        }

        // Close menus when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.color-menu') && !e.target.closest('button[onclick^="toggleColorMenu"]')) {
                document.querySelectorAll('.color-menu').forEach(el => el.classList.remove('show'));
            }
        });

    </script>



    <!-- Sheet Context Menu -->
    <div id="sheetContextMenu" class="context-menu">
        <div class="context-menu-item" onclick="renameSheetFromMenu()">
            <i class="fa-solid fa-pen"></i> Rename
        </div>
        <div class="context-menu-item" onclick="duplicateSheet()">
            <i class="fa-solid fa-copy"></i> Duplicate
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item danger" onclick="deleteSheet()">
            <i class="fa-solid fa-trash"></i> Delete
        </div>
    </div>

</body>

</html>