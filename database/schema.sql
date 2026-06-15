-- ==================================================
-- CyberAI Platform — Database Schema
-- ==================================================

CREATE DATABASE IF NOT EXISTS cyber_ai_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cyber_ai_platform;

-- ==================================================
-- USERS TABLE
-- ==================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) NOT NULL UNIQUE,
    email       VARCHAR(255) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(150) NOT NULL,
    role        ENUM('admin','analyst','viewer') NOT NULL DEFAULT 'analyst',
    avatar      VARCHAR(255) DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    last_login  DATETIME DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username  (username),
    INDEX idx_email     (email),
    INDEX idx_role      (role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- LOGS TABLE
-- ==================================================
CREATE TABLE IF NOT EXISTS logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type        VARCHAR(50) NOT NULL DEFAULT 'system',
    action      VARCHAR(100) NOT NULL,
    message     TEXT NOT NULL,
    severity    ENUM('critical','high','medium','low','info') NOT NULL DEFAULT 'info',
    user_id     INT UNSIGNED DEFAULT NULL,
    ip_address  VARCHAR(45) DEFAULT NULL,
    user_agent  TEXT DEFAULT NULL,
    raw_data    JSON DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type       (type),
    INDEX idx_severity   (severity),
    INDEX idx_user_id    (user_id),
    INDEX idx_ip         (ip_address),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- ALERTS TABLE
-- ==================================================
CREATE TABLE IF NOT EXISTS alerts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255) NOT NULL,
    description  TEXT DEFAULT NULL,
    severity     ENUM('critical','high','medium','low','info') NOT NULL DEFAULT 'medium',
    status       ENUM('open','investigating','resolved','false_positive') NOT NULL DEFAULT 'open',
    attack_type  VARCHAR(100) DEFAULT NULL,
    source_ip    VARCHAR(45) DEFAULT NULL,
    target_ip    VARCHAR(45) DEFAULT NULL,
    source_port  INT UNSIGNED DEFAULT NULL,
    target_port  INT UNSIGNED DEFAULT NULL,
    protocol     VARCHAR(20) DEFAULT NULL,
    risk_score   TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100',
    ai_analysis  TEXT DEFAULT NULL,
    raw_log      TEXT DEFAULT NULL,
    user_id      INT UNSIGNED DEFAULT NULL,
    resolved_at  DATETIME DEFAULT NULL,
    resolved_by  INT UNSIGNED DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_severity   (severity),
    INDEX idx_status     (status),
    INDEX idx_source_ip  (source_ip),
    INDEX idx_attack_type(attack_type),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id    (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- AI REPORTS TABLE
-- ==================================================
CREATE TABLE IF NOT EXISTS ai_reports (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    content     LONGTEXT NOT NULL,
    report_type VARCHAR(50) DEFAULT 'manual',
    alert_id    INT UNSIGNED DEFAULT NULL,
    user_id     INT UNSIGNED DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_report_type (report_type),
    INDEX idx_user_id     (user_id),
    INDEX idx_created_at  (created_at),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE SET NULL,
    FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- BLOCKED IPs TABLE
-- ==================================================
CREATE TABLE IF NOT EXISTS blocked_ips (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address  VARCHAR(45) NOT NULL UNIQUE,
    reason      VARCHAR(255) DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    blocked_by  INT UNSIGNED DEFAULT NULL,
    blocked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME DEFAULT NULL,
    INDEX idx_ip_address (ip_address),
    INDEX idx_is_active  (is_active),
    INDEX idx_blocked_at (blocked_at),
    FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- SETTINGS TABLE
-- ==================================================
CREATE TABLE IF NOT EXISTS settings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================
-- ATTACK HISTORY TABLE
-- ==================================================
CREATE TABLE IF NOT EXISTS attack_history (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_ip   VARCHAR(45) NOT NULL,
    target_ip   VARCHAR(45) DEFAULT NULL,
    attack_type VARCHAR(100) NOT NULL,
    severity    ENUM('critical','high','medium','low','info') NOT NULL DEFAULT 'medium',
    protocol    VARCHAR(20) DEFAULT NULL,
    payload     TEXT DEFAULT NULL,
    risk_score  TINYINT UNSIGNED DEFAULT 0,
    is_blocked  TINYINT(1) NOT NULL DEFAULT 0,
    alert_id    INT UNSIGNED DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source_ip   (source_ip),
    INDEX idx_attack_type (attack_type),
    INDEX idx_severity    (severity),
    INDEX idx_created_at  (created_at),
    INDEX idx_is_blocked  (is_blocked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
