<?php
// config.php
// MySQL Database Configuration

// Database credentials
$db_host = 'localhost';
$db_name = 'u771821149_turtletod';
$db_user = 'u771821149_turtle';
$db_pass = 'tu&rt$D@tCr(0M';
$db_charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET names utf8mb4 COLLATE utf8mb4_unicode_ci");

    // ═══════════════════════════════════════════════════════════
    // 🕊️ AUTO DATABASE SETUP
    // Always checks for missing tables & columns.
    // Uses fast SHOW TABLES / SHOW COLUMNS — skips if all exists.
    // ═══════════════════════════════════════════════════════════

    // ── STEP 1: Create any completely missing tables ──────────
    $criticalTables = [
        'users',
        'teams',
        'word_documents',
        'spreadsheets',
        'calendar_events',
        'leads',
        'tasks',
        'projects',
        'team_files',
        'user_push_subscriptions',
        'channels',
        'channel_members',
        'chat_messages',
        'dm_threads',
        'channel_members_last_read'
    ];
    $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $missingTables = array_diff($criticalTables, $existingTables);

    if (!empty($missingTables)) {
        $schemaPath = __DIR__ . '/database/schema.sql';
        if (file_exists($schemaPath)) {
            $schema = file_get_contents($schemaPath);
            $queries = array_filter(array_map('trim', explode(';', $schema)));
            foreach ($queries as $q) {
                try {
                    $pdo->exec($q);
                } catch (PDOException $e) { /* skip existing */
                }
            }
        }
    }

    // ── STEP 2: Add any missing columns to existing tables ────
    $columnMigrations = [
        'users' => [
            'last_seen' => 'TIMESTAMP NULL',
            'presence_status' => "VARCHAR(20) DEFAULT 'online'",
            'fcm_token' => 'TEXT NULL',
            'last_login' => 'TIMESTAMP NULL',
            'two_fa_secret' => 'VARCHAR(255) NULL',
            'two_fa_enabled' => 'TINYINT(1) DEFAULT 0',
            'unique_id' => 'VARCHAR(50) NULL',
            'is_active' => 'TINYINT(1) DEFAULT 1',
        ],
        'teams' => [
            'tool_word' => 'TINYINT(1) DEFAULT 0',
            'tool_spreadsheet' => 'TINYINT(1) DEFAULT 0',
            'tool_calendar' => 'TINYINT(1) DEFAULT 0',
            'tool_chat' => 'TINYINT(1) DEFAULT 0',
            'tool_filemanager' => 'TINYINT(1) DEFAULT 0',
            'tool_tasksheet' => 'TINYINT(1) DEFAULT 0',
            'tool_leadrequirement' => 'TINYINT(1) DEFAULT 0',
            'status' => "ENUM('active','inactive') DEFAULT 'active'",
            'description' => 'TEXT NULL',
        ],
        'word_documents' => [
            'updated_by' => 'INT NULL',
            'assigned_to' => 'LONGTEXT NULL',
            'assigned_by' => 'INT NULL',
            'updated_at' => 'TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP',
        ],
        'spreadsheets' => [
            'updated_by' => 'INT NULL',
            'assigned_to' => 'LONGTEXT NULL',
            'assigned_by' => 'INT NULL',
            'updated_at' => 'TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP',
        ],
        'calendar_events' => [
            'reminded' => 'TINYINT(1) DEFAULT 0',
            'color' => "VARCHAR(20) DEFAULT '#3b82f6'",
            'description' => 'TEXT NULL',
        ],
        'leads' => [
            'updated_at' => 'TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP',
            'assigned_to' => 'INT NULL',
            'source' => 'VARCHAR(100) NULL',
            'notes' => 'TEXT NULL',
        ],
        'tasks' => [
            'updated_at' => 'TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP',
            'description' => 'TEXT NULL',
        ],
        'chat_messages' => [
            'is_read' => 'TINYINT DEFAULT 0',
            'read_at' => 'TIMESTAMP NULL',
            'channel' => "VARCHAR(50) DEFAULT 'general'",
        ],
        'dm_threads' => [
            'deleted_by_user1' => 'TINYINT DEFAULT 0',
            'deleted_by_user2' => 'TINYINT DEFAULT 0',
        ],
    ];

    foreach ($columnMigrations as $table => $columns) {
        try {
            $existingCols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($columns as $col => $definition) {
                if (!in_array($col, $existingCols)) {
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
                }
            }
        } catch (PDOException $e) { /* table may not exist yet — skip */
        }
    }


    // ── DEFAULT ADMIN (checked every load but very fast) ──────
    // Ensures at least one admin exists when DB is freshly set up
    try {
        $checkUser = $pdo->query("SELECT COUNT(*) FROM users");
        if ($checkUser->fetchColumn() == 0) {
            $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, unique_id, is_active) VALUES (?, ?, ?, ?, 'admin', ?, 1)");
            $stmt->execute(['admin', 'admin@turtledot.com', $adminPass, 'System Administrator', 'ADMIN001']);
        }
    } catch (PDOException $e) { /* users table not ready yet */
    }

} catch (PDOException $e) {
    die("Connection Failed: " . $e->getMessage() . " (Check credentials in config.php)");
}

// VAPID Keys for Push Notifications
define('VAPID_PUBLIC_KEY', 'BMaKPqcVHa4nu58Vr41ychJtjt5fwzC3iAkr-pIQdUG_Veni0c57Kn45Gu8jdyuSEr-erUvyzo3hSSCaOMbR8kU');
define('VAPID_PRIVATE_KEY', 'ZFl1nPh1dS9IEdk6sN6FbNVRDUGFs9WiX4T2xykFgJc');
define('VAPID_SUBJECT', 'mailto:turtledottech@gmail.com');
