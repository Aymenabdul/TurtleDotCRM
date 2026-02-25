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
    if (!in_array('calendar', $teamTools)) {
        die("This tool is not enabled for this team.");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

startLayout("Calendar - " . $team['name'], $user);
?>

<!-- Flatpickr for a premium date/time picker experience -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
    /* ── Flatpickr Custom Theme ── */
    .flatpickr-calendar {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        border-radius: 16px;
        font-family: 'Inter', sans-serif;
    }

    .flatpickr-day.selected,
    .flatpickr-day.startRange,
    .flatpickr-day.endRange,
    .flatpickr-day.selected.prevMonthDay,
    .flatpickr-day.selected.nextMonthDay,
    .flatpickr-day.startRange.prevMonthDay,
    .flatpickr-day.startRange.nextMonthDay,
    .flatpickr-day.endRange.prevMonthDay,
    .flatpickr-day.endRange.nextMonthDay {
        background: var(--primary);
        border-color: var(--primary);
    }

    .flatpickr-day.today {
        border-color: var(--primary-light);
    }

    .flatpickr-day.today:hover {
        background: var(--primary-bg);
        border-color: var(--primary-light);
    }

    .flatpickr-day:hover {
        background: var(--primary-bg);
    }

    .flatpickr-months .flatpickr-month {
        background: var(--bg-main);
        color: var(--text-main);
        fill: var(--text-main);
    }

    .flatpickr-current-month .flatpickr-monthDropdown-months {
        font-weight: 700;
    }

    .flatpickr-time {
        border-top: 1px solid var(--border-color);
    }

    .flatpickr-time input:hover,
    .flatpickr-time .flatpickr-am-pm:hover,
    .flatpickr-time input:focus,
    .flatpickr-time .flatpickr-am-pm:focus {
        background: var(--bg-secondary);
    }

    .flatpickr-calendar.hasTime .flatpickr-time {
        height: 50px;
        line-height: 50px;
    }

    /* ═══════════════════ CALENDAR DESIGN SYSTEM ═══════════════════ */

    .cal-dashboard {
        font-family: 'Inter', sans-serif;
        padding-bottom: 3rem;
        animation: calFadeIn 0.5s ease-out;
    }

    @keyframes calFadeIn {
        from {
            opacity: 0;
            transform: translateY(12px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ── Hero Section ── */
    .cal-hero {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .cal-hero-left h1 {
        font-size: 2rem;
        font-weight: 800;
        color: var(--text-main);
        letter-spacing: -0.03em;
        margin: 0;
        line-height: 1.2;
    }

    .cal-hero-left p {
        color: var(--text-muted);
        margin: 0.4rem 0 0 0;
        font-size: 1rem;
    }

    .cal-hero-left .cal-breadcrumb {
        color: var(--text-muted);
        text-decoration: none;
        font-size: 0.88rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 0.5rem;
        transition: color 0.2s;
    }

    .cal-hero-left .cal-breadcrumb:hover {
        color: var(--primary);
    }

    .cal-add-btn {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.25s;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        position: relative;
        overflow: hidden;
    }

    .cal-add-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
    }

    .cal-add-btn::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: 0.5s;
    }

    .cal-add-btn:hover::after {
        left: 100%;
    }

    /* ── Stat Cards ── */
    .cal-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .cal-stat-card {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.25s;
        box-shadow: var(--shadow-sm);
    }

    .cal-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .cal-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .cal-stat-icon.green {
        background: var(--primary-bg);
        color: var(--primary);
    }

    .cal-stat-icon.blue {
        background: var(--info-bg);
        color: var(--info);
    }

    .cal-stat-icon.amber {
        background: var(--warning-bg);
        color: var(--warning);
    }

    .cal-stat-icon.red {
        background: var(--error-bg);
        color: var(--error);
    }

    .cal-stat-info {
        min-width: 0;
    }

    .cal-stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.2;
    }

    .cal-stat-label {
        font-size: 0.78rem;
        color: var(--text-muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    /* ── Controls Bar ── */
    .cal-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1.25rem;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-sm);
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .cal-nav {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cal-nav-btn {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-main);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        font-size: 0.85rem;
    }

    .cal-nav-btn:hover {
        background: var(--primary-bg);
        border-color: var(--primary);
        color: var(--primary);
    }

    .cal-month-label {
        min-width: 200px;
        text-align: center;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .cal-view-tabs {
        display: flex;
        gap: 4px;
        background: var(--bg-tertiary);
        padding: 4px;
        border-radius: 10px;
    }

    .cal-view-tab {
        padding: 6px 16px;
        border: none;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        background: transparent;
        transition: all 0.2s;
    }

    .cal-view-tab.active {
        background: var(--bg-main);
        color: var(--primary);
        box-shadow: var(--shadow-sm);
    }

    .cal-view-tab:hover:not(.active) {
        color: var(--text-main);
    }

    .cal-today-btn {
        padding: 6px 16px;
        border: 1px solid var(--primary);
        border-radius: 8px;
        background: transparent;
        color: var(--primary);
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .cal-today-btn:hover {
        background: var(--primary);
        color: white;
    }

    /* ── Calendar Grid ── */
    .cal-grid-container {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .cal-day-headers {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: linear-gradient(135deg, var(--primary-bg) 0%, #ecfdf5 100%);
        border-bottom: 2px solid var(--primary-bg-light);
    }

    .cal-day-header {
        padding: 0.85rem 0;
        text-align: center;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--primary-dark);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .cal-cells {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }

    .cal-cell {
        min-height: 120px;
        border-right: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
        padding: 0.5rem;
        background: var(--bg-main);
        transition: background 0.2s;
        position: relative;
        cursor: pointer;
    }

    .cal-cell:nth-child(7n) {
        border-right: none;
    }

    .cal-cell:hover {
        background: #f0fdf4;
    }

    .cal-cell.empty {
        background: var(--bg-secondary);
        cursor: default;
    }

    .cal-cell.empty:hover {
        background: var(--bg-secondary);
    }

    .cal-cell.today {
        background: #f0fdf4;
    }

    .cal-cell.today .cal-day-num {
        background: var(--primary);
        color: white;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);
    }

    .cal-day-num {
        display: inline-flex;
        width: 30px;
        height: 30px;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-main);
        margin-bottom: 4px;
        transition: all 0.2s;
    }

    .cal-cell:hover .cal-day-num:not(.today .cal-day-num) {
        background: var(--bg-tertiary);
    }

    .cal-cell.weekend .cal-day-num {
        color: var(--text-muted);
    }

    /* ── Event Chips ── */
    .cal-event-chip {
        font-size: 0.72rem;
        padding: 3px 8px;
        border-radius: 6px;
        margin-bottom: 3px;
        color: white;
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: transform 0.15s, filter 0.2s, box-shadow 0.2s;
        font-weight: 600;
        display: block;
        line-height: 1.4;
        position: relative;
    }

    .cal-event-chip:hover {
        transform: translateX(2px);
        filter: brightness(1.1);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        z-index: 2;
    }

    .cal-event-chip::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 3px;
        height: 100%;
        background: rgba(255, 255, 255, 0.4);
        border-radius: 6px 0 0 6px;
    }

    .cal-more-link {
        font-size: 0.72rem;
        color: var(--primary);
        font-weight: 600;
        padding: 2px 8px;
        cursor: pointer;
        transition: color 0.2s;
    }

    .cal-more-link:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }

    /* ── Event Modal (Add/Edit) ── */
    .cal-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        backdrop-filter: blur(8px);
        z-index: 1050;
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .cal-modal-backdrop.active {
        display: flex;
        opacity: 1;
    }

    .cal-modal {
        background: var(--bg-main);
        width: 780px;
        max-width: 95vw;
        max-height: 92vh;
        border-radius: 24px;
        box-shadow: 0 25px 80px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);
        animation: calModalSlide 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    @keyframes calModalSlide {
        from {
            transform: translateY(40px) scale(0.94);
            opacity: 0;
        }

        to {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
    }

    .cal-modal-inner {
        display: grid;
        grid-template-columns: 280px 1fr;
        flex: 1;
        overflow: hidden;
    }

    @media (max-width: 700px) {
        .cal-modal-inner {
            grid-template-columns: 1fr;
        }

        .cal-modal-preview {
            display: none !important;
        }
    }

    /* ── Left Preview Panel ── */
    .cal-modal-preview {
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 2.5rem 1.5rem;
        transition: background 0.5s;
    }

    .cal-modal-preview::before {
        content: '';
        position: absolute;
        inset: -50%;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.08'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        transform: rotate(15deg);
        animation: patternFloat 60s linear infinite;
        pointer-events: none;
    }

    @keyframes patternFloat {
        from {
            transform: rotate(15deg) translateY(0);
        }

        to {
            transform: rotate(15deg) translateY(-60px);
        }
    }

    .cal-preview-badge {
        position: absolute;
        top: 1.5rem;
        left: 1.5rem;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(8px);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 800;
        color: white;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        border: 1px solid rgba(255, 255, 255, 0.2);
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .cal-preview-badge::before {
        content: '';
        width: 6px;
        height: 6px;
        background: #fff;
        border-radius: 50%;
        box-shadow: 0 0 8px #fff;
        animation: pulseLive 2s infinite;
    }

    @keyframes pulseLive {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.5);
            opacity: 0.5;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .cal-preview-icon {
        width: 72px;
        height: 72px;
        border-radius: 22px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: white;
        margin-bottom: 2rem;
        position: relative;
        z-index: 1;
        border: 1px solid rgba(255, 255, 255, 0.25);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        animation: floatIcon 4s ease-in-out infinite;
    }

    @keyframes floatIcon {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-10px);
        }
    }

    .cal-preview-card {
        background: rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(14px);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 20px;
        padding: 1.5rem;
        width: 100%;
        position: relative;
        z-index: 1;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .cal-preview-title {
        font-size: 1.15rem;
        font-weight: 800;
        color: white;
        margin-bottom: 1rem;
        word-break: break-word;
        line-height: 1.25;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .cal-preview-meta {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .cal-preview-meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.85);
    }

    .cal-preview-meta-item i {
        width: 16px;
        text-align: center;
        font-size: 0.72rem;
        opacity: 0.7;
    }

    .cal-preview-label {
        font-size: 0.72rem;
        color: rgba(255, 255, 255, 0.5);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 600;
        margin-top: 1.5rem;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }

    .cal-preview-desc {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.7);
        line-height: 1.5;
        position: relative;
        z-index: 1;
        font-style: italic;
    }

    /* ── Right Form Panel ── */
    .cal-modal-form-panel {
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }

    .cal-modal-header {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-main);
        flex-shrink: 0;
    }

    .cal-modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .cal-modal-header h3 i {
        color: var(--primary);
        font-size: 1.1rem;
    }

    .cal-modal-close {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-muted);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: all 0.2s;
    }

    .cal-modal-close:hover {
        background: var(--error-bg);
        color: var(--error);
        border-color: var(--error-border);
        transform: rotate(90deg);
    }

    .cal-modal-body {
        padding: 2rem;
        flex: 1;
        overflow-y: auto;
    }

    .cal-form-section {
        margin-bottom: 2rem;
    }

    .cal-form-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 1.25rem;
    }

    .cal-form-section-title i {
        color: var(--primary);
        font-size: 0.8rem;
        width: 18px;
        text-align: center;
    }

    .cal-form-section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border-color);
        margin-left: 10px;
        opacity: 0.6;
    }

    .cal-form-group {
        margin-bottom: 1.5rem;
    }

    .cal-form-label {
        display: block;
        margin-bottom: 8px;
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text-main);
        opacity: 0.9;
    }

    .cal-form-input {
        width: 100%;
        padding: 0.65rem 0.9rem;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.9rem;
        color: var(--text-main);
        background: var(--bg-secondary);
        font-family: 'Inter', sans-serif;
        transition: all 0.2s;
    }

    .cal-form-input:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--bg-main);
        box-shadow: 0 0 0 4px var(--focus-ring), 0 4px 12px rgba(16, 185, 129, 0.1);
    }

    .cal-form-input::placeholder {
        color: var(--text-light);
    }

    .cal-input-with-icon {
        position: relative;
    }

    .cal-input-with-icon i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.9rem;
        pointer-events: none;
        opacity: 0.6;
    }

    .cal-input-with-icon .cal-form-input {
        padding-left: 2.5rem;
    }


    .cal-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }

    .cal-color-presets {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        padding: 4px 0;
    }

    .cal-color-swatch {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 2px solid transparent;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
    }

    .cal-color-swatch:hover {
        transform: scale(1.2);
    }

    .cal-color-swatch.active {
        border-color: var(--text-main);
        box-shadow: 0 0 0 2px var(--bg-main), 0 0 0 4px var(--text-main);
        transform: scale(1.1);
    }

    .cal-color-swatch.active::after {
        content: '✓';
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.65rem;
        font-weight: 700;
    }

    .cal-modal-footer {
        padding: 1.5rem 2rem;
        border-top: 1px solid var(--border-color);
        background: var(--bg-secondary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
    }

    .cal-btn-delete {
        background: transparent;
        border: 1px solid var(--error-border);
        color: var(--error);
        padding: 0.55rem 1rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: none;
    }

    .cal-btn-delete:hover {
        background: var(--error-bg);
    }

    .cal-btn-delete.show {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .cal-modal-actions {
        display: flex;
        gap: 0.5rem;
    }

    .cal-btn-cancel {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        padding: 0.55rem 1.15rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .cal-btn-cancel:hover {
        background: var(--border-color);
    }

    .cal-btn-save {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border: none;
        color: white;
        padding: 0.55rem 1.35rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.25);
        position: relative;
        overflow: hidden;
    }

    .cal-btn-save::after {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(to bottom right, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.13) 50%, rgba(255, 255, 255, 0) 100%);
        transform: rotate(45deg);
        transition: all 0.5s;
        pointer-events: none;
    }

    .cal-btn-save:hover::after {
        left: 50%;
    }

    .cal-btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
    }

    .cal-btn-save:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* ── Upcoming Events Sidebar ── */
    .cal-layout {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 1.5rem;
    }

    @media (max-width: 1100px) {
        .cal-layout {
            grid-template-columns: 1fr;
        }
    }

    .cal-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .cal-upcoming-card {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .cal-upcoming-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem;
        border-bottom: 1px solid var(--border-color);
    }

    .cal-upcoming-header>div {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .cal-upcoming-header h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--text-main);
    }

    .cal-test-notify {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .cal-test-notify:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .cal-notification-status {
        margin: 0 1.25rem 1.25rem;
        padding: 0.85rem 1rem;
        background: var(--bg-secondary);
        border: 1px dashed var(--border-color);
        border-radius: 14px;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cal-notification-status i {
        color: var(--primary);
        font-size: 0.85rem;
    }


    .cal-upcoming-header i {
        color: var(--primary);
    }

    .cal-upcoming-list {
        padding: 0.5rem 1.25rem;
        max-height: 500px;
        overflow-y: auto;
    }

    .cal-upcoming-item {
        display: flex;
        gap: 12px;
        padding: 0.75rem;
        border-radius: 10px;
        transition: background 0.2s;
        cursor: pointer;
        align-items: flex-start;
    }

    .cal-upcoming-item:hover {
        background: var(--bg-secondary);
    }

    .cal-upcoming-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-top: 3px;
        flex-shrink: 0;
        box-shadow: 0 0 0 3px var(--bg-main), 0 0 0 4px var(--border-color);
    }

    .cal-upcoming-info {
        flex: 1;
        min-width: 0;
    }

    .cal-upcoming-title {
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--text-main);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cal-upcoming-time {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .cal-upcoming-empty {
        text-align: center;
        padding: 2rem 1rem;
        color: var(--text-muted);
    }

    .cal-upcoming-empty i {
        font-size: 2rem;
        color: var(--border-color);
        margin-bottom: 0.75rem;
        display: block;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .cal-hero {
            flex-direction: column;
            align-items: flex-start;
        }

        .cal-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .cal-cell {
            min-height: 80px;
            padding: 0.25rem;
        }

        .cal-day-num {
            width: 24px;
            height: 24px;
            font-size: 0.75rem;
        }

        .cal-event-chip {
            font-size: 0.65rem;
            padding: 2px 5px;
        }

        .cal-month-label {
            font-size: 1rem;
            min-width: 160px;
        }

        .cal-controls {
            padding: 0.5rem 0.75rem;
        }
    }

    /* ── Custom Confirm Popup ── */
    .confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .confirm-overlay.open {
        opacity: 1;
        pointer-events: auto;
    }

    .confirm-box {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 20px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        width: 400px;
        max-width: 90vw;
        overflow: hidden;
        transform: scale(0.95) translateY(10px);
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .confirm-overlay.open .confirm-box {
        transform: scale(1) translateY(0);
    }

    .confirm-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 24px 24px 16px;
    }

    .confirm-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .confirm-icon.danger {
        background: #fee2e2;
        color: #ef4444;
    }

    .confirm-icon.info {
        background: #e0f2fe;
        color: #0ea5e9;
    }

    .confirm-title {
        font-size: 1.15rem;
        font-weight: 800;
        color: #1e293b;
        letter-spacing: -0.01em;
    }

    .confirm-msg {
        padding: 0 24px 24px;
        font-size: 0.95rem;
        color: #64748b;
        line-height: 1.6;
        font-weight: 500;
    }

    .confirm-actions {
        display: flex;
        gap: 12px;
        padding: 0 24px 24px;
        justify-content: flex-end;
    }

    .confirm-btn {
        padding: 10px 24px;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }

    .confirm-btn.cancel {
        background: #f1f5f9;
        color: #64748b;
    }

    .confirm-btn.cancel:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .confirm-btn.danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
    }

    .confirm-btn.danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(239, 68, 68, 0.35);
    }

    .confirm-btn.primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    }

    .confirm-btn.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(16, 185, 129, 0.35);
    }
</style>

<!-- ═══════════════════ CALENDAR DASHBOARD ═══════════════════ -->
<div class="cal-dashboard">

    <!-- Hero -->
    <div class="cal-hero">
        <div class="cal-hero-left">
            <?php $is_admin = isset($user['role']) && strtolower(trim($user['role'])) === 'admin'; ?>
            <a href="<?php echo $is_admin ? '/admin_dashboard.php' : '/index.php'; ?>" class="cal-breadcrumb">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1><i class="fa-solid fa-calendar-days" style="color:var(--primary);"></i> Team Calendar</h1>
            <p>Schedule and manage events for <?php echo htmlspecialchars($team['name']); ?></p>
        </div>
        <button class="cal-add-btn" onclick="openEventModal()">
            <i class="fa-solid fa-plus"></i>
            <span>New Event</span>
        </button>
    </div>

    <!-- Stat Cards -->
    <div class="cal-stats">
        <div class="cal-stat-card">
            <div class="cal-stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="cal-stat-info">
                <div class="cal-stat-value" id="statTotal">0</div>
                <div class="cal-stat-label">Total Events</div>
            </div>
        </div>
        <div class="cal-stat-card">
            <div class="cal-stat-icon blue"><i class="fa-solid fa-clock"></i></div>
            <div class="cal-stat-info">
                <div class="cal-stat-value" id="statToday">0</div>
                <div class="cal-stat-label">Today</div>
            </div>
        </div>
        <div class="cal-stat-card">
            <div class="cal-stat-icon amber"><i class="fa-solid fa-forward"></i></div>
            <div class="cal-stat-info">
                <div class="cal-stat-value" id="statUpcoming">0</div>
                <div class="cal-stat-label">Upcoming</div>
            </div>
        </div>
        <div class="cal-stat-card">
            <div class="cal-stat-icon red"><i class="fa-solid fa-calendar-week"></i></div>
            <div class="cal-stat-info">
                <div class="cal-stat-value" id="statThisWeek">0</div>
                <div class="cal-stat-label">This Week</div>
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="cal-controls">
        <div class="cal-nav">
            <button class="cal-nav-btn" onclick="prevMonth()" title="Previous">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="cal-month-label" id="currentMonthYear"></div>
            <button class="cal-nav-btn" onclick="nextMonth()" title="Next">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <button class="cal-today-btn" onclick="goToday()">Today</button>
        </div>
    </div>

    <!-- Main Layout: Calendar + Upcoming Sidebar -->
    <div class="cal-layout">
        <!-- Calendar Grid -->
        <div class="cal-grid-container">
            <div class="cal-day-headers">
                <div class="cal-day-header">Sun</div>
                <div class="cal-day-header">Mon</div>
                <div class="cal-day-header">Tue</div>
                <div class="cal-day-header">Wed</div>
                <div class="cal-day-header">Thu</div>
                <div class="cal-day-header">Fri</div>
                <div class="cal-day-header">Sat</div>
            </div>
            <div class="cal-cells" id="calendarCells">
                <!-- JS generated -->
            </div>
        </div>

        <!-- Upcoming Events Sidebar -->
        <div class="cal-sidebar">
            <div class="cal-upcoming-card">
                <div class="cal-upcoming-header">
                    <div>
                        <i class="fa-solid fa-bolt"></i>
                        <h3>Upcoming Events</h3>
                    </div>
                    <button class="cal-test-notify" onclick="subscribeToPush()" title="Enable Notifications">
                        <i class="fa-solid fa-bell"></i>
                    </button>
                </div>
                <div class="cal-upcoming-list" id="upcomingList">
                    <div class="cal-upcoming-empty">
                        <i class="fa-regular fa-calendar-xmark"></i>
                        <div>No upcoming events</div>
                    </div>
                </div>
                <div class="cal-notification-status" id="notifStatus">
                    <i class="fa-solid fa-circle-check"></i> Monitoring Events...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════ EVENT MODAL ═══════════════════ -->
<div class="cal-modal-backdrop" id="eventModal">
    <div class="cal-modal">
        <div class="cal-modal-inner">

            <!-- Left: Live Preview Panel -->
            <div class="cal-modal-preview" id="previewPanel"
                style="background: linear-gradient(135deg, #10b981, #059669);">
                <div class="cal-preview-badge">Live Preview</div>
                <div class="cal-preview-icon">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <div class="cal-preview-card">
                    <div class="cal-preview-title" id="previewTitle">Untitled Event</div>
                    <div class="cal-preview-meta">
                        <div class="cal-preview-meta-item">
                            <i class="fa-regular fa-calendar"></i>
                            <span id="previewDate">Select a date</span>
                        </div>
                        <div class="cal-preview-meta-item">
                            <i class="fa-regular fa-clock"></i>
                            <span id="previewTime">Set time</span>
                        </div>
                    </div>
                </div>
                <div class="cal-preview-label" id="previewDescLabel" style="display:none;">NOTES</div>
                <div class="cal-preview-desc" id="previewDesc"></div>
            </div>

            <!-- Right: Form Panel -->
            <div class="cal-modal-form-panel">
                <div class="cal-modal-header">
                    <h3><i class="fa-solid fa-calendar-plus"></i> <span id="modalTitle">New Event</span></h3>
                    <button class="cal-modal-close" onclick="closeEventModal()">&times;</button>
                </div>
                <form id="eventForm" onsubmit="handleEventSubmit(event)">
                    <input type="hidden" name="id" id="eventId">
                    <div class="cal-modal-body">

                        <!-- Section: Details -->
                        <div class="cal-form-section">
                            <div class="cal-form-section-title">
                                <i class="fa-solid fa-pen"></i> Details
                            </div>
                            <div class="cal-form-group">
                                <label class="cal-form-label">Event Title</label>
                                <input type="text" name="title" id="eventTitle" class="cal-form-input" required
                                    placeholder="e.g. Team Standup, Client Meeting" oninput="updatePreview()">
                            </div>
                        </div>

                        <!-- Section: Schedule -->
                        <div class="cal-form-section">
                            <div class="cal-form-section-title">
                                <i class="fa-regular fa-clock"></i> Schedule
                            </div>
                            <div class="cal-form-row">
                                <div class="cal-form-group">
                                    <label class="cal-form-label">Start Date & Time</label>
                                    <div class="cal-input-with-icon">
                                        <i class="fa-regular fa-calendar-days"></i>
                                        <input type="text" name="start_time" id="startTime"
                                            class="cal-form-input cal-datepicker" required
                                            placeholder="Select start date & time">
                                    </div>
                                </div>
                                <div class="cal-form-group">
                                    <label class="cal-form-label">End Date & Time</label>
                                    <div class="cal-input-with-icon">
                                        <i class="fa-regular fa-clock"></i>
                                        <input type="text" name="end_time" id="endTime"
                                            class="cal-form-input cal-datepicker" required
                                            placeholder="Select end date & time">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Appearance -->
                        <div class="cal-form-section">
                            <div class="cal-form-section-title">
                                <i class="fa-solid fa-palette"></i> Appearance
                            </div>
                            <div class="cal-form-group">
                                <label class="cal-form-label">Event Color</label>
                                <input type="hidden" name="color" id="eventColor" value="#10b981">
                                <div class="cal-color-presets" id="colorPresets">
                                    <!-- Generated in JS -->
                                </div>
                            </div>
                        </div>

                        <!-- Section: Notes -->
                        <div class="cal-form-section" style="margin-bottom:0.5rem;">
                            <div class="cal-form-section-title">
                                <i class="fa-regular fa-note-sticky"></i> Notes
                            </div>
                            <div class="cal-form-group" style="margin-bottom:0;">
                                <textarea name="description" id="eventDescription" class="cal-form-input" rows="3"
                                    placeholder="Add any additional details..." oninput="updatePreview()"></textarea>
                            </div>
                        </div>

                    </div> <!-- End modal-body -->

                    <div class="cal-modal-footer">
                        <button type="button" class="cal-btn-delete" id="deleteEventBtn" onclick="deleteEvent()">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                        <div class="cal-modal-actions" style="margin-left: auto; display: flex; gap: 0.75rem;">
                            <button type="button" class="cal-btn-cancel" onclick="closeEventModal()">Cancel</button>
                            <button type="submit" class="cal-btn-save" id="saveEventBtn">
                                <i class="fa-solid fa-check"></i> Save Event
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============ CUSTOM CONFIRM POPUP ============ -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-header">
            <div class="confirm-icon danger" id="confirmIcon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="confirm-title" id="confirmTitle">Are you sure?</div>
        </div>
        <div class="confirm-msg" id="confirmMsg">This action cannot be undone.</div>
        <div class="confirm-actions">
            <button class="confirm-btn cancel" id="confirmCancelBtn">Cancel</button>
            <button class="confirm-btn danger" id="confirmOkBtn">Confirm</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../src/components/ui/glass-toast.php'; ?>

<script>
    const TEAM_ID = <?php echo $teamId; ?>;
    let events = [];
    // Use localStorage for persistence across reloads/sessions
    let rawNotified = JSON.parse(localStorage.getItem('notified_events') || '[]');
    let notifiedEvents = new Set(rawNotified.map(id => String(id)));
    let currentDate = new Date();

    // Support for background push notifications
    let pushSubscription = null;

    // Premium Sound Alert - Using local asset with absolute-style web path
    const NOTIFY_SOUND = new Audio('/assets/images/mixkit-doorbell-tone-2864.wav');
    NOTIFY_SOUND.volume = 0.7;
    NOTIFY_SOUND.preload = 'auto';
    let audioUnlocked = false;

    // Aggressive audio priming on any user interaction
    function unlockAudio() {
        if (audioUnlocked) return;
        NOTIFY_SOUND.play().then(() => {
            NOTIFY_SOUND.pause();
            NOTIFY_SOUND.currentTime = 0;
            audioUnlocked = true;
            console.log("✅ Notification system audio unlocked");
        }).catch(e => {
            // This is expected if they haven't interacted yet
            console.warn("⏳ Audio unlock pending user gesture...");
        });
    }

    // Listen for common interactions to unlock
    ['click', 'touchstart', 'keydown'].forEach(evt => {
        document.addEventListener(evt, unlockAudio, { once: true });
    });

    const COLOR_PRESETS = [
        '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b',
        '#ef4444', '#ec4899', '#06b6d4', '#f97316',
        '#6366f1', '#14b8a6', '#84cc16', '#64748b'
    ];

    // ── Init color swatches ──
    function initColorPresets() {
        const container = document.getElementById('colorPresets');
        container.innerHTML = COLOR_PRESETS.map(c =>
            `<div class="cal-color-swatch${c === '#10b981' ? ' active' : ''}"
                style="background:${c}"
                data-color="${c}"
                onclick="selectColor('${c}')"></div>`
        ).join('');
    }

    function selectColor(color) {
        document.getElementById('eventColor').value = color;
        document.querySelectorAll('.cal-color-swatch').forEach(s => {
            s.classList.toggle('active', s.dataset.color === color);
        });
        updatePreview();
    }

    // ── Load Events ──
    async function loadEvents() {
        try {
            const res = await fetch(`/api/calendar.php?team_id=${TEAM_ID}`);
            const json = await res.json();
            if (json.success) {
                events = json.data;
                renderCalendar();
                renderUpcoming();
                updateStats();
                checkEventNotifications(); // Check immediately after load
            }
        } catch (e) {
            console.error('Load events error:', e);
        }
    }

    // ── Render Calendar ──
    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        document.getElementById('currentMonthYear').textContent =
            new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(currentDate);

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();
        const container = document.getElementById('calendarCells');
        container.innerHTML = '';

        // Empty cells
        for (let i = 0; i < firstDay; i++) {
            container.innerHTML += '<div class="cal-cell empty"></div>';
        }

        // Day cells
        for (let day = 1; day <= daysInMonth; day++) {
            const isToday = today.getDate() === day && today.getMonth() === month && today.getFullYear() === year;
            const isWeekend = (firstDay + day - 1) % 7 === 0 || (firstDay + day - 1) % 7 === 6;
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

            const dayEvents = events.filter(e => e.start_time && e.start_time.startsWith(dateStr));

            let eventsHtml = '';
            dayEvents.slice(0, 3).forEach(e => {
                const time = formatTime(e.start_time);
                eventsHtml += `<div class="cal-event-chip" style="background:${e.color || '#10b981'}"
                    onclick="event.stopPropagation(); editEvent(${e.id})"
                    title="${escHtml(e.title)} — ${time}">${escHtml(e.title)}</div>`;
            });

            if (dayEvents.length > 3) {
                eventsHtml += `<div class="cal-more-link" onclick="event.stopPropagation();">+${dayEvents.length - 3} more</div>`;
            }

            container.innerHTML += `
                <div class="cal-cell ${isToday ? 'today' : ''} ${isWeekend ? 'weekend' : ''}"
                    onclick="openEventModalForDate('${dateStr}')">
                    <span class="cal-day-num">${day}</span>
                    <div class="cal-events-wrap">${eventsHtml}</div>
                </div>`;
        }
    }

    // ── Upcoming Events ──
    function renderUpcoming() {
        const now = new Date();
        const upcoming = events
            .filter(e => new Date(e.start_time) >= now)
            .sort((a, b) => new Date(a.start_time) - new Date(b.start_time))
            .slice(0, 10);

        const container = document.getElementById('upcomingList');

        if (upcoming.length === 0) {
            container.innerHTML = `
                <div class="cal-upcoming-empty">
                    <i class="fa-regular fa-calendar-xmark"></i>
                    <div>No upcoming events</div>
                </div>`;
            return;
        }

        container.innerHTML = upcoming.map(e => {
            const dt = new Date(e.start_time);
            const dayStr = dt.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
            const timeStr = formatTime(e.start_time);
            return `
                <div class="cal-upcoming-item" onclick="editEvent(${e.id})">
                    <div class="cal-upcoming-dot" style="background:${e.color || '#10b981'}"></div>
                    <div class="cal-upcoming-info">
                        <div class="cal-upcoming-title">${escHtml(e.title)}</div>
                        <div class="cal-upcoming-time">${dayStr} · ${timeStr}</div>
                    </div>
                </div>`;
        }).join('');
    }

    // ── Stats ──
    function updateStats() {
        const now = new Date();
        const todayStr = now.toISOString().slice(0, 10);
        const weekEnd = new Date(now);
        weekEnd.setDate(weekEnd.getDate() + 7);

        const todayCount = events.filter(e => e.start_time && e.start_time.startsWith(todayStr)).length;
        const upcomingCount = events.filter(e => new Date(e.start_time) >= now).length;
        const weekCount = events.filter(e => {
            const d = new Date(e.start_time);
            return d >= now && d <= weekEnd;
        }).length;

        animateNumber('statTotal', events.length);
        animateNumber('statToday', todayCount);
        animateNumber('statUpcoming', upcomingCount);
        animateNumber('statThisWeek', weekCount);
    }

    function animateNumber(id, target) {
        const el = document.getElementById(id);
        const current = parseInt(el.textContent) || 0;
        if (current === target) return;

        let start = current;
        const step = target > current ? 1 : -1;
        const interval = setInterval(() => {
            start += step;
            el.textContent = start;
            if (start === target) clearInterval(interval);
        }, 50);
    }

    // ── Navigation ──
    function prevMonth() { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); }
    function nextMonth() { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); }
    function goToday() { currentDate = new Date(); renderCalendar(); }

    // ── Modal ──
    function openEventModal(eventData = null) {
        const isEdit = !!eventData;
        document.getElementById('modalTitle').textContent = isEdit ? 'Edit Event' : 'New Event';
        document.getElementById('eventForm').reset();
        document.getElementById('eventId').value = '';
        document.getElementById('deleteEventBtn').classList.toggle('show', isEdit);

        // Reset color to default
        selectColor('#10b981');

        if (isEdit) {
            document.getElementById('eventId').value = eventData.id;
            document.getElementById('eventTitle').value = eventData.title;
            if (startPicker) startPicker.setDate(eventData.start_time);
            if (endPicker) endPicker.setDate(eventData.end_time);
            document.getElementById('eventDescription').value = eventData.description || '';
            selectColor(eventData.color || '#10b981');
        } else {
            // New event - clear pickers
            if (startPicker) startPicker.clear();
            if (endPicker) endPicker.clear();
        }

        document.getElementById('eventModal').classList.add('active');
        updatePreview();
    }

    function openEventModalForDate(dateStr) {
        openEventModal();
        if (startPicker) startPicker.setDate(dateStr + ' 09:00');
        if (endPicker) endPicker.setDate(dateStr + ' 10:00');
        updatePreview();
    }

    function closeEventModal() {
        document.getElementById('eventModal').classList.remove('active');
    }

    function editEvent(id) {
        const ev = events.find(e => e.id == id);
        if (ev) openEventModal(ev);
    }

    // ── Save Event ──
    async function handleEventSubmit(e) {
        e.preventDefault();
        const form = new FormData(e.target);
        const data = Object.fromEntries(form.entries());
        data.team_id = TEAM_ID;

        const isEdit = !!data.id;
        const btn = document.getElementById('saveEventBtn');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        try {
            const res = await fetch('/api/calendar.php', {
                method: isEdit ? 'PATCH' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();

            if (json.success) {
                Toast.success(isEdit ? 'Updated' : 'Created', isEdit ? 'Event updated successfully.' : 'Event added to the calendar.');

                // If new event, we can't easily trigger background push from frontend here 
                // because we want the SERVER to send push to others.
                // The API should handle sending push to team members.

                if (isEdit) {
                    const idStr = String(data.id);
                    if (notifiedEvents.has(idStr)) {
                        console.log(`[Notif] Resetting notification status for edited event: ${idStr}`);
                        notifiedEvents.delete(idStr);
                        localStorage.setItem('notified_events', JSON.stringify([...notifiedEvents]));
                    }
                }

                closeEventModal();
                loadEvents();
            } else {
                Toast.error('Error', json.message || 'Something went wrong.');
            }
        } catch (err) {
            Toast.error('Error', 'Failed to save event.');
        } finally {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Save Event';
            btn.disabled = false;
        }
    }

    // ── Delete Event ──
    async function deleteEvent() {
        const id = document.getElementById('eventId').value;
        if (!id) return;

        const confirmed = await showConfirm(
            'Delete Event?',
            'Are you sure you want to remove this event from the calendar? This action cannot be undone.',
            'danger'
        );

        if (!confirmed) return;

        try {
            await fetch('/api/calendar.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            Toast.success('Deleted', 'Event removed from the calendar.');
            closeEventModal();
            loadEvents();
        } catch (err) {
            Toast.error('Error', 'Failed to delete event.');
        }
    }

    // ── Helpers ──
    function formatTime(datetime) {
        if (!datetime) return '';
        const d = new Date(datetime.replace(' ', 'T'));
        return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // ── Live Preview ──
    function updatePreview() {
        const title = document.getElementById('eventTitle').value || 'Untitled Event';
        const start = document.getElementById('startTime').value;
        const end = document.getElementById('endTime').value;
        const color = document.getElementById('eventColor').value;
        const desc = document.getElementById('eventDescription').value;

        // Title
        document.getElementById('previewTitle').textContent = title;

        // Date & Time
        if (start) {
            const dt = new Date(start);
            document.getElementById('previewDate').textContent = dt.toLocaleDateString('en-US', {
                weekday: 'long', month: 'long', day: 'numeric'
            });
            let timeStr = formatTime(start);
            if (end) timeStr += ' — ' + formatTime(end);
            document.getElementById('previewTime').textContent = timeStr;
        } else {
            document.getElementById('previewDate').textContent = 'Select a date';
            document.getElementById('previewTime').textContent = 'Set time';
        }

        // Description
        const descEl = document.getElementById('previewDesc');
        const descLabel = document.getElementById('previewDescLabel');
        if (desc.trim()) {
            descEl.textContent = desc;
            descEl.style.display = 'block';
            descLabel.style.display = 'block';
        } else {
            descEl.style.display = 'none';
            descLabel.style.display = 'none';
        }

        // Background Gradient
        const panel = document.getElementById('previewPanel');
        // Generate a slightly darker version of the color for the gradient
        const darker = adjustColor(color, -20);
        panel.style.background = `linear-gradient(135deg, ${color}, ${darker})`;
    }

    function adjustColor(hex, percent) {
        let r = parseInt(hex.slice(1, 3), 16);
        let g = parseInt(hex.slice(3, 5), 16);
        let b = parseInt(hex.slice(5, 7), 16);

        r = Math.floor(r * (100 + percent) / 100);
        g = Math.floor(g * (100 + percent) / 100);
        b = Math.floor(b * (100 + percent) / 100);

        r = Math.min(255, Math.max(0, r));
        g = Math.min(255, Math.max(0, g));
        b = Math.min(255, Math.max(0, b));

        const rr = r.toString(16).padStart(2, '0');
        const gg = g.toString(16).padStart(2, '0');
        const bb = b.toString(16).padStart(2, '0');

        return `#${rr}${gg}${bb}`;
    }

    // ── Init Flatpickr ──
    let startPicker, endPicker;

    function initPickers() {
        const commonConfig = {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: false,
            altInput: true,
            altFormat: "F j, Y - h:i K",
            disableMobile: "true",
            onChange: function () {
                updatePreview();
            }
        };

        startPicker = flatpickr("#startTime", commonConfig);
        endPicker = flatpickr("#endTime", commonConfig);
    }

    // ── Notifications ──
    const CHECK_INTERVAL = 15000; // 15 seconds

    function checkEventNotifications() {
        const now = new Date();
        const nowUtc = now.getTime();

        const statusEl = document.getElementById('notifStatus');
        if (statusEl) {
            statusEl.innerHTML = `
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="width:8px; height:8px; background:#10b981; border-radius:50%; box-shadow:0 0 8px #10b981; animation: pulse 2s infinite;"></span>
                    <span>Monitoring ${events.length} Events (Live)</span>
                    <button onclick="testOSNotification()" style="margin-left:auto; background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:10px; text-decoration:underline;">Test OS Alert</button>
                </div>
            `;
        }

        events.forEach(event => {
            const idStr = String(event.id);
            if (!event.start_time) return;

            if (notifiedEvents.has(idStr)) {
                return;
            }

            // Robust and consistent parsing
            let eventTime;
            try {
                // Replacing - with / and T with space is safest for cross-browser local parsing
                const cleanDate = event.start_time.replace(/-/g, '/').replace('T', ' ');
                eventTime = new Date(cleanDate).getTime();
            } catch (e) {
                console.error("[Notif] Parse error for event:", event.title, event.start_time);
                return;
            }

            if (isNaN(eventTime)) return;

            const diff = eventTime - nowUtc;
            const diffMinutes = Math.floor(diff / 60000);


            // Trigger Window: 2 minutes (120,000ms) before the event starts
            // and up to 5 minutes after it started (in case they just opened the tab)
            if (diff <= 120000 && diff >= -300000) {
                showEventAlert(event);
                notifiedEvents.add(idStr);
                localStorage.setItem('notified_events', JSON.stringify([...notifiedEvents]));
            }

            // --- AUTO DELETE PASSED EVENTS ---
            // If the event has ended (passed its end_time), delete it automatically
            const endTimeStr = event.end_time || event.start_time; // Fallback to start_time if no end
            const cleanEndDate = endTimeStr.replace(/-/g, '/').replace('T', ' ');
            const endTime = new Date(cleanEndDate).getTime();

            // Delete if current time is past end time (with a 10-second grace period)
            if (nowUtc > (endTime + 10000)) {
                autoDeleteEvent(event.id);
            }
        });
    }

    async function autoDeleteEvent(id) {
        try {
            await fetch('/api/calendar.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            // Reload silently after auto-delete
            events = events.filter(e => e.id != id);
            renderCalendar();
            renderUpcoming();
            updateStats();
        } catch (err) {
            // Silently fail auto-delete
        }
    }

    function showEventAlert(event) {
        const time = formatTime(event.start_time);

        // 1. Play Doorbell Sound
        NOTIFY_SOUND.play().catch(e => { });

        // 2. Custom Glass Toast (Minimalist - Logo Focused)
        try {
            Toast.show(null, `
                <div style="display:flex; align-items:center; gap:16px; padding:2px 0;">
                    <div style="position:relative;">
                        <img src="/assets/images/turtle_logo_192.png" style="width:42px; height:42px; border-radius:12px; box-shadow:0 6px 20px rgba(16,185,129,0.3);">
                        <span style="position:absolute; top:-2px; right:-2px; width:11px; height:11px; background:#ef4444; border:2px solid #fff; border-radius:50%; animation: pulse 1s infinite;"></span>
                    </div>
                    <div>
                        <div style="font-weight:900; color:#1e293b; font-size:1.05rem; line-height:1.2; letter-spacing:0.02em; text-transform: uppercase;">${escHtml(event.title)}</div>
                        <div style="font-size:0.8rem; color:#64748b; font-weight:700; margin-top:4px; display:flex; align-items:center; gap:5px;">
                            <i class="fa-regular fa-clock" style="color:var(--primary); font-size:0.85rem;"></i> Starts at ${time}
                        </div>
                    </div>
                </div>
            `, 'minimal', 20000);
        } catch (e) { }

        // 3. OS LEVEL - Native Notification (Force Visible)
        if (Notification.permission === "granted") {
            const notifOptions = {
                body: `Starts at ${time}`,
                icon: '/assets/images/turtle_logo_192.png',
                badge: '/assets/images/turtle_logo_192.png',
                vibrate: [500, 110, 500, 110, 500, 110, 500],
                tag: 'event-' + String(event.id),
                renotify: true,
                requireInteraction: true,
                silent: false,
                data: { url: window.location.href }
            };

            const title = "Meeting: " + event.title;

            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                navigator.serviceWorker.ready.then(reg => {
                    reg.showNotification(title, notifOptions);
                }).catch(err => {
                    new Notification(title, notifOptions);
                });
            } else {
                new Notification(title, notifOptions);
            }
        }
    }

    function testOSNotification() {
        if (!("Notification" in window)) {
            Toast.error("Error", "OS notifications not supported.");
            return;
        }

        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                const options = {
                    body: "Test notification from TurtleDot. OS alerts are active!",
                    icon: '/assets/images/turtle_logo_192.png',
                    badge: '/assets/images/turtle_logo_192.png',
                    requireInteraction: true,
                    silent: false,
                    tag: 'test-ping'
                };

                // Try both methods
                try {
                    new Notification("TurtleDot Alert 🐢", options);
                } catch (e) { }

                if (navigator.serviceWorker) {
                    navigator.serviceWorker.ready.then(reg => {
                        reg.showNotification("TurtleDot Alert 🐢", options);
                    });
                }

                Toast.success("Test Sent", "Check your notification center.");
            } else {
                Toast.error("Blocked", "Notifications are disabled.");
            }
        });
    }

    async function subscribeToPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return;
        }

        try {
            const registration = await navigator.serviceWorker.ready;

            // Get VAPID key from server if needed, or use a constant
            // For this app, it should be in config.php but we need it here.
            // We'll fetch it from a small helper if needed, or assume it's global.
            // Since we don't have it globally here, let's just request permission first.

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                Toast.error("Permission Denied", "Notifications were blocked.");
                return;
            }

            // If already subscribed, just play sound to confirm it works
            if (localStorage.getItem('push_subscribed') === 'true') {
                NOTIFY_SOUND.play().catch(unlockAudio);
                Toast.success("Subscribed", "Notifications are active. You should have heard the chime!");
                return;
            }

            // Fetch VAPID Key
            const keyRes = await fetch('/api/push_subscription.php?get_key=1');
            const keyData = await keyRes.json();

            if (!keyData.publicKey) throw new Error("VAPID Key not found");

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: keyData.publicKey
            });

            await fetch('/api/push_subscription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subscription)
            });

            localStorage.setItem('push_subscribed', 'true');
            Toast.success("Notifications Enabled", "You will now receive alerts even when the tab is closed.");

            // Play sound as confirmation
            NOTIFY_SOUND.play().catch(e => console.warn("Sound blocked despite click"));

            // Update UI status
            const statusEl = document.getElementById('notifStatus');
            if (statusEl) {
                statusEl.innerHTML = `
                    <i class="fa-solid fa-bell-check text-emerald-500"></i> 
                    <span>Push Notifications Active</span>
                    <button onclick="NOTIFY_SOUND.play()" style="margin-left:auto; background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:10px; text-decoration:underline;">Test Sound</button>
                `;
            }

        } catch (err) {
            console.error('Push subscription failed:', err);
            Toast.error("Notification Error", "Could not enable background push.");
        }
    }

    function testNotification() {
        NOTIFY_SOUND.play().catch(async (e) => {
            await showConfirm(
                'Unlock Audio',
                'Browsers blocks sound until you interact with the page. Please click "Unlock" to enable alerts.',
                'info'
            );
            unlockAudio();
        });
        Toast.success('Test Alert', 'Sound and notifications are working correctly.');
        if ("Notification" in window) {
            if (Notification.permission === "granted") {
                new Notification("Test Notification", { body: "Browser notifications are enabled!" });
            } else {
                Notification.requestPermission();
            }
        }
    }

    /* ── Custom confirm popup helper ── */
    function showConfirm(title, message, type = 'danger') {
        return new Promise((resolve) => {
            const overlay = document.getElementById('confirmOverlay');
            const titleEl = document.getElementById('confirmTitle');
            const msgEl = document.getElementById('confirmMsg');
            const iconEl = document.getElementById('confirmIcon');
            const okBtn = document.getElementById('confirmOkBtn');
            const cancelBtn = document.getElementById('confirmCancelBtn');

            titleEl.textContent = title;
            msgEl.innerHTML = message;

            // Icon setup
            iconEl.className = 'confirm-icon ' + (type === 'danger' ? 'danger' : 'info');
            iconEl.innerHTML = type === 'danger'
                ? '<i class="fa-solid fa-triangle-exclamation"></i>'
                : '<i class="fa-solid fa-circle-info"></i>';

            // Button style
            okBtn.className = 'confirm-btn ' + (type === 'danger' ? 'danger' : 'primary');
            okBtn.textContent = type === 'danger' ? 'Delete' : 'Confirm';

            overlay.classList.add('open');

            function cleanup() {
                overlay.classList.remove('open');
                okBtn.onclick = null;
                cancelBtn.onclick = null;
                overlay.onclick = null;
            }

            okBtn.onclick = () => { cleanup(); resolve(true); };
            cancelBtn.onclick = () => { cleanup(); resolve(false); };
            overlay.onclick = (e) => { if (e.target === overlay) { cleanup(); resolve(false); } };
        });
    }

    function requestNotificationPermission() {
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }
    }

    // ── Init ──
    initColorPresets();
    loadEvents();
    initPickers();
    requestNotificationPermission();
    setInterval(checkEventNotifications, CHECK_INTERVAL);
</script>

<?php endLayout(); ?>