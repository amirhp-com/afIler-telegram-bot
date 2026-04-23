<?php
/**
 * Amirhp Filer Bot — Setup Script
 * Run this ONCE to create tables and register webhook.
 * DELETE or PROTECT this file after setup!
 *
 * Developed by AmirhpCom
 * https://amirhp.com/landing
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram.php';

Logger::init();
TG::init();

$action = $_GET['action'] ?? 'info';
$secret = $_GET['secret'] ?? '';

// Protect setup script with a one-time secret
if ($secret !== 'CHANGE_THIS_SETUP_SECRET') {
    http_response_code(403);
    die('Forbidden. Set ?secret=CHANGE_THIS_SETUP_SECRET in URL after changing the value.');
}

header('Content-Type: text/html; charset=utf-8');

function section(string $title): void {
    echo "<h3 style='color:#2c3e50'>$title</h3>";
}
function ok(string $msg): void  { echo "<p style='color:green'>✅ $msg</p>"; }
function err(string $msg): void { echo "<p style='color:red'>❌ $msg</p>"; }
function info(string $msg): void { echo "<p style='color:#555'>ℹ️ $msg</p>"; }

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Bot Setup</title></head><body>';
echo '<h1>🤖 Amirhp Filer Bot — Setup</h1>';

// ── CREATE TABLES ────────────────────────────────────────────────────────────

section('1. Database Tables');

$tables = [
    DB_PREFIX . 'users' => "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "users` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`        BIGINT NOT NULL UNIQUE,
        `username`       VARCHAR(100) DEFAULT '',
        `first_name`     VARCHAR(100) DEFAULT '',
        `last_name`      VARCHAR(100) DEFAULT '',
        `is_allowed`     TINYINT(1) DEFAULT 0,
        `download_count` INT DEFAULT 0,
        `total_bytes`    BIGINT DEFAULT 0,
        `created_at`     DATETIME NOT NULL,
        `last_active`    DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    DB_PREFIX . 'cache' => "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cache` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `url_hash`    CHAR(32) NOT NULL UNIQUE,
        `url`         TEXT NOT NULL,
        `file_id`     VARCHAR(200) NOT NULL,
        `file_type`   VARCHAR(20) NOT NULL,
        `filename`    VARCHAR(300) NOT NULL,
        `file_size`   BIGINT DEFAULT 0,
        `hit_count`   INT DEFAULT 0,
        `created_at`  DATETIME NOT NULL,
        `expires_at`  DATETIME NOT NULL,
        INDEX (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    DB_PREFIX . 'logs' => "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "logs` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`     BIGINT NOT NULL,
        `action`      VARCHAR(50) NOT NULL,
        `detail`      TEXT DEFAULT '',
        `file_size`   BIGINT DEFAULT 0,
        `from_cache`  TINYINT(1) DEFAULT 0,
        `created_at`  DATETIME NOT NULL,
        INDEX (`user_id`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    DB_PREFIX . 'rate_limit' => "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "rate_limit` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`     BIGINT NOT NULL,
        `created_at`  DATETIME NOT NULL,
        INDEX (`user_id`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    DB_PREFIX . 'settings' => "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "settings` (
        `key`  VARCHAR(100) PRIMARY KEY,
        `val`  TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
];

foreach ($tables as $name => $sql) {
    try {
        DB::q($sql);
        ok("Table `$name` created/verified.");
    } catch (Exception $e) {
        err("Table `$name` failed: " . $e->getMessage());
    }
}

// ── DEFAULT SETTINGS ─────────────────────────────────────────────────────────

section('2. Default Settings');
try {
    DB::setSetting('max_file_size_mb', MAX_FILE_SIZE_MB);
    DB::setSetting('bot_public', (int) BOT_PUBLIC);
    ok('Default settings saved.');
} catch (Exception $e) {
    err('Settings failed: ' . $e->getMessage());
}

// ── TMP DIR ──────────────────────────────────────────────────────────────────

section('3. Temp Directory');
if (!is_dir(TEMP_DIR)) {
    if (mkdir(TEMP_DIR, 0755, true)) {
        ok('Temp dir created: ' . TEMP_DIR);
    } else {
        err('Could not create temp dir: ' . TEMP_DIR);
    }
} else {
    ok('Temp dir exists: ' . TEMP_DIR);
}

// .htaccess to block web access to tmp
$htaccess = TEMP_DIR . '.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Deny from all\n");
    ok('.htaccess created for tmp/ (no public access).');
}

// ── SET WEBHOOK ───────────────────────────────────────────────────────────────

section('4. Webhook Registration');
$webhookUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') . rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/index.php';
info("Registering webhook: <code>$webhookUrl</code>");

// Quick cURL test
$ch = curl_init('https://api.telegram.org/bot' . BOT_TOKEN . '/getMe');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$r = curl_exec($ch);
$e = curl_error($ch);
curl_close($ch);
echo '<pre>cURL test: ' . ($e ?: $r) . '</pre>';

$result = TG::setWebhook($webhookUrl, WEBHOOK_SECRET);
if ($result) {
    ok("Webhook set successfully! ✅");
    echo '<pre>' . json_encode($result, JSON_PRETTY_PRINT) . '</pre>';
} else {
    err("Webhook registration failed. Check BOT_TOKEN in config.php.");
}

// ── WEBHOOK INFO ──────────────────────────────────────────────────────────────

section('5. Webhook Info');
$info = TG::getWebhookInfo();
echo '<pre>' . json_encode($info, JSON_PRETTY_PRINT) . '</pre>';

// ── DONE ──────────────────────────────────────────────────────────────────────

echo '<hr>';
echo '<h2 style="color:green">✅ Setup Complete!</h2>';
echo '<p><strong>⚠️ IMPORTANT: Delete or rename <code>setup.php</code> after this!</strong></p>';
echo '<p>Or add a .htaccess rule to deny access to it.</p>';
echo '<hr><small>Developed by <a href="https://amirhp.com/landing">AmirhpCom</a></small>';
echo '</body></html>';
