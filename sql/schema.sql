-- Amirhp Filer Bot — Database Schema
-- Developed by AmirhpCom | https://amirhp.com/landing
-- Import this into your MySQL database via phpMyAdmin if needed
-- Default prefix: filerbot_ (change to match config.php DB_PREFIX)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── USERS ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `filerbot_users` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`        BIGINT NOT NULL,
    `username`       VARCHAR(100) DEFAULT '',
    `first_name`     VARCHAR(100) DEFAULT '',
    `last_name`      VARCHAR(100) DEFAULT '',
    `is_allowed`     TINYINT(1)   DEFAULT 0,
    `download_count` INT          DEFAULT 0,
    `total_bytes`    BIGINT       DEFAULT 0,
    `created_at`     DATETIME     NOT NULL,
    `last_active`    DATETIME     NOT NULL,
    UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CACHE ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `filerbot_cache` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `url_hash`    CHAR(32)       NOT NULL,
    `url`         TEXT           NOT NULL,
    `file_id`     VARCHAR(200)   NOT NULL,
    `file_type`   VARCHAR(20)    NOT NULL,
    `filename`    VARCHAR(300)   NOT NULL,
    `file_size`   BIGINT         DEFAULT 0,
    `hit_count`   INT            DEFAULT 0,
    `created_at`  DATETIME       NOT NULL,
    `expires_at`  DATETIME       NOT NULL,
    UNIQUE KEY `url_hash` (`url_hash`),
    KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── LOGS ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `filerbot_logs` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     BIGINT       NOT NULL,
    `action`      VARCHAR(50)  NOT NULL,
    `detail`      TEXT         DEFAULT '',
    `file_size`   BIGINT       DEFAULT 0,
    `from_cache`  TINYINT(1)   DEFAULT 0,
    `created_at`  DATETIME     NOT NULL,
    KEY `user_id`    (`user_id`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── RATE LIMIT ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `filerbot_rate_limit` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     BIGINT   NOT NULL,
    `created_at`  DATETIME NOT NULL,
    KEY `user_id`    (`user_id`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SETTINGS ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `filerbot_settings` (
    `key`  VARCHAR(100) NOT NULL,
    `val`  TEXT         NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT IGNORE INTO `filerbot_settings` (`key`, `val`) VALUES
    ('max_file_size_mb', '50'),
    ('bot_public',       '0');

SET FOREIGN_KEY_CHECKS = 1;
