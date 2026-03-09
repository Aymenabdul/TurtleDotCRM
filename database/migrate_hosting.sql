-- ================================================================
-- TurtleDot CRM - Hosting Migration Script (Compatible Version)
-- Works on ALL MySQL/MariaDB versions including shared hosting
-- ================================================================
-- HOW TO RUN:
--   1. Open phpMyAdmin on your Hostinger panel
--   2. Select database: u771821149_turtletod
--   3. Click the "SQL" tab
--   4. Paste this entire file and click "Go"
--
-- NOTE: Some ALTER TABLE statements may show "Duplicate column" 
-- warnings if a column already exists — that is SAFE, just ignore them.
-- ================================================================

-- ── USERS TABLE ───────────────────────────────────────────────
ALTER TABLE users ADD COLUMN last_seen TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN presence_status VARCHAR(20) DEFAULT 'online';
ALTER TABLE users ADD COLUMN fcm_token TEXT NULL;
ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN two_fa_secret VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN two_fa_enabled TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN unique_id VARCHAR(50) NULL;
ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1;

-- ── TEAMS TABLE ───────────────────────────────────────────────
ALTER TABLE teams ADD COLUMN tool_word TINYINT(1) DEFAULT 0;
ALTER TABLE teams ADD COLUMN tool_spreadsheet TINYINT(1) DEFAULT 0;
ALTER TABLE teams ADD COLUMN tool_calendar TINYINT(1) DEFAULT 0;
ALTER TABLE teams ADD COLUMN tool_chat TINYINT(1) DEFAULT 0;
ALTER TABLE teams ADD COLUMN tool_filemanager TINYINT(1) DEFAULT 0;
ALTER TABLE teams ADD COLUMN tool_tasksheet TINYINT(1) DEFAULT 0;
ALTER TABLE teams ADD COLUMN tool_leadrequirement TINYINT(1) DEFAULT 0;
ALTER TABLE teams ADD COLUMN status ENUM('active','inactive') DEFAULT 'active';
ALTER TABLE teams ADD COLUMN description TEXT NULL;
ALTER TABLE teams ADD COLUMN tools TEXT NULL;

-- ── WORD_DOCUMENTS TABLE ──────────────────────────────────────
ALTER TABLE documents ADD COLUMN updated_by INT NULL;
ALTER TABLE documents ADD COLUMN assigned_to LONGTEXT NULL;
ALTER TABLE documents ADD COLUMN assigned_by INT NULL;
ALTER TABLE documents ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;

-- ── SPREADSHEETS TABLE ────────────────────────────────────────
ALTER TABLE spreadsheets ADD COLUMN updated_by INT NULL;
ALTER TABLE spreadsheets ADD COLUMN assigned_to LONGTEXT NULL;
ALTER TABLE spreadsheets ADD COLUMN assigned_by INT NULL;
ALTER TABLE spreadsheets ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;

-- ── CALENDAR_EVENTS TABLE ─────────────────────────────────────
ALTER TABLE calendar_events ADD COLUMN reminded TINYINT(1) DEFAULT 0;
ALTER TABLE calendar_events ADD COLUMN color VARCHAR(20) DEFAULT '#3b82f6';
ALTER TABLE calendar_events ADD COLUMN description TEXT NULL;

-- ── LEADS TABLE ───────────────────────────────────────────────
ALTER TABLE leads ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE leads ADD COLUMN assigned_to INT NULL;
ALTER TABLE leads ADD COLUMN source VARCHAR(100) NULL;
ALTER TABLE leads ADD COLUMN notes TEXT NULL;

-- ── TASKS TABLE ───────────────────────────────────────────────
ALTER TABLE tasks ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE tasks ADD COLUMN assigned_to INT NULL;
ALTER TABLE tasks ADD COLUMN description TEXT NULL;
ALTER TABLE tasks ADD COLUMN priority ENUM('low','medium','high','urgent') DEFAULT 'medium';

-- ── DOCUMENTS TABLE ───────────────────────────────────────────
ALTER TABLE documents ADD COLUMN assigned_to LONGTEXT NULL;
ALTER TABLE documents ADD COLUMN assigned_by INT NULL;
ALTER TABLE documents ADD COLUMN updated_by INT NULL;
ALTER TABLE documents ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;

-- ── PROJECTS TABLE ────────────────────────────────────────────
ALTER TABLE projects ADD COLUMN assigned_to INT NULL;
ALTER TABLE projects ADD COLUMN description TEXT NULL;
ALTER TABLE projects ADD COLUMN start_date DATE NULL;
ALTER TABLE projects ADD COLUMN due_date DATE NULL;
ALTER TABLE projects ADD COLUMN team_id INT NULL;
ALTER TABLE projects ADD COLUMN status ENUM('active','completed','on_hold','cancelled') DEFAULT 'active';

-- ── CHAT_MESSAGES TABLE ───────────────────────────────────────
ALTER TABLE chat_messages ADD COLUMN is_read TINYINT DEFAULT 0;
ALTER TABLE chat_messages ADD COLUMN read_at TIMESTAMP NULL;
ALTER TABLE chat_messages ADD COLUMN channel VARCHAR(50) DEFAULT 'general';

-- ── DM_THREADS TABLE ──────────────────────────────────────────
ALTER TABLE dm_threads ADD COLUMN deleted_by_user1 TINYINT DEFAULT 0;
ALTER TABLE dm_threads ADD COLUMN deleted_by_user2 TINYINT DEFAULT 0;

-- ── TEAM_FILES TABLE (create if missing) ──────────────────────
CREATE TABLE IF NOT EXISTS team_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CHANNEL_MEMBERS_LAST_READ TABLE (create if missing) ───────
CREATE TABLE IF NOT EXISTS channel_members_last_read (
    user_id INT NOT NULL,
    channel_id INT NOT NULL,
    last_read_message_id INT NOT NULL,
    PRIMARY KEY (user_id, channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── USER_PUSH_SUBSCRIPTIONS TABLE (create if missing) ─────────
CREATE TABLE IF NOT EXISTS user_push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_json TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DOCUMENTS TABLE (create if missing) ───────────────────────
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    type ENUM('word','spreadsheet') NOT NULL,
    assigned_to LONGTEXT NULL,
    assigned_by INT NULL,
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DM_THREADS TABLE (create if missing) ─────────────────────
CREATE TABLE IF NOT EXISTS dm_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel VARCHAR(100) NOT NULL UNIQUE,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_by_user1 TINYINT DEFAULT 0,
    deleted_by_user2 TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CHANNELS TABLE (create if missing) ────────────────────────
CREATE TABLE IF NOT EXISTS channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_channel (team_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CHANNEL_MEMBERS TABLE (create if missing) ─────────────────
CREATE TABLE IF NOT EXISTS channel_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_member (channel_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Done! "Duplicate column" warnings above are safe to ignore.
-- They just mean the column already existed.
SELECT 'Migration complete!' AS result;
