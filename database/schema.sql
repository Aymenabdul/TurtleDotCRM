-- ============================================================
-- TurtleDot CRM - Complete Database Schema
-- Verified against local DB on 2026-03-05
-- Run this to initialize a fresh hosting environment
-- ============================================================

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_id VARCHAR(50) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role VARCHAR(50) DEFAULT 'user',
    team_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    two_fa_secret VARCHAR(255) NULL,
    two_fa_enabled TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    presence_status VARCHAR(20) DEFAULT 'online',
    fcm_token TEXT NULL,
    last_seen TIMESTAMP NULL,
    INDEX (team_id),
    INDEX (unique_id),
    INDEX (presence_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teams Table
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    tools TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tool_word TINYINT(1) DEFAULT 0,
    tool_spreadsheet TINYINT(1) DEFAULT 0,
    tool_calendar TINYINT(1) DEFAULT 0,
    tool_chat TINYINT(1) DEFAULT 0,
    tool_filemanager TINYINT(1) DEFAULT 0,
    tool_tasksheet TINYINT(1) DEFAULT 0,
    tool_leadrequirement TINYINT(1) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Word Documents Table (Word Editor)
CREATE TABLE IF NOT EXISTS word_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    assigned_to LONGTEXT,
    assigned_by INT,
    INDEX (team_id),
    INDEX (created_by),
    INDEX (updated_by),
    INDEX (assigned_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Spreadsheets Table
CREATE TABLE IF NOT EXISTS spreadsheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    assigned_to LONGTEXT,
    assigned_by INT,
    INDEX (team_id),
    INDEX (created_by),
    INDEX (updated_by),
    INDEX (assigned_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calendar Events Table
CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    color VARCHAR(20) DEFAULT '#3b82f6',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reminded TINYINT(1) DEFAULT 0,
    INDEX (team_id),
    INDEX (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leads Table
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    status ENUM('new','contacted','qualified','lost','won') DEFAULT 'new',
    source VARCHAR(100),
    assigned_to INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (team_id),
    INDEX (assigned_to),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tasks Table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('todo','in_progress','review','done') DEFAULT 'todo',
    due_date DATE,
    assigned_to INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX (team_id),
    INDEX (assigned_to),
    INDEX (created_by),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Projects Table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    team_id INT,
    assigned_to INT,
    status ENUM('active','completed','on_hold','cancelled') DEFAULT 'active',
    start_date DATE,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (team_id),
    INDEX (assigned_to),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team Folders Table
CREATE TABLE IF NOT EXISTS team_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    created_by INT NOT NULL,
    assigned_to LONGTEXT,
    assigned_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (team_id),
    INDEX (parent_id),
    INDEX (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team Files Table (Archive Vault)
CREATE TABLE IF NOT EXISTS team_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    folder_id INT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    assigned_to LONGTEXT,
    assigned_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (team_id),
    INDEX (folder_id),
    INDEX (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Push Notification Subscriptions
CREATE TABLE IF NOT EXISTS user_push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_json TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat Channels
CREATE TABLE IF NOT EXISTS channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_channel (team_id, name),
    INDEX (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat Channel Members
CREATE TABLE IF NOT EXISTS channel_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_member (channel_id, user_id),
    INDEX (channel_id),
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat Messages
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NULL,
    channel VARCHAR(50) DEFAULT 'general',
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT DEFAULT 0,
    read_at TIMESTAMP NULL,
    INDEX (team_id),
    INDEX (channel),
    INDEX (user_id),
    INDEX (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DM Threads Tracking
CREATE TABLE IF NOT EXISTS dm_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel VARCHAR(100) NOT NULL UNIQUE,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_by_user1 TINYINT DEFAULT 0,
    deleted_by_user2 TINYINT DEFAULT 0,
    INDEX (channel),
    INDEX (user1_id),
    INDEX (user2_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Channel Last-Read Tracking
CREATE TABLE IF NOT EXISTS channel_members_last_read (
    user_id INT NOT NULL,
    channel_id INT NOT NULL,
    last_read_message_id INT NOT NULL,
    PRIMARY KEY (user_id, channel_id),
    INDEX (user_id),
    INDEX (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
