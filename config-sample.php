<?php
/**
 * Amirhp Filer Bot — Configuration
 * Developed by AmirhpCom
 * https://amirhp.com/landing | https://github.com/amirhp-com
 */

// ─── BOT SETTINGS ────────────────────────────────────────────────────────────
define('BOT_TOKEN',          'YOUR_BOT_TOKEN_HERE');
define('BOT_USERNAME',       'amirhp_filerbot');
define('BOT_NAME',           'Amirhp Filer Bot');
define('WEBHOOK_SECRET',     'YOUR_RANDOM_SECRET_STRING_HERE'); // random string, set same in setWebhook

// ─── ACCESS CONTROL ──────────────────────────────────────────────────────────
define('BOT_PUBLIC',         false);          // true = anyone, false = whitelist only
define('ADMIN_IDS',          [123456789]);    // your Telegram user ID(s)
define('ALLOWED_USER_IDS',   [123456789]);    // if BOT_PUBLIC=false, who can use bot

// ─── FILE SETTINGS ───────────────────────────────────────────────────────────
define('MAX_FILE_SIZE_MB',   50);             // max file size in MB (Telegram bot limit = 50)
define('TEMP_DIR',           __DIR__ . '/tmp/');
define('APPEND_BOT_TAG',     true);           // append bot name to downloaded files
define('BOT_FILE_TAG',       '_AmirhpFilerBot'); // tag appended to filename before extension

// ─── RATE LIMITING ───────────────────────────────────────────────────────────
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_MAX',     10);             // max requests per user
define('RATE_LIMIT_WINDOW',  3600);           // time window in seconds (1 hour)

// ─── MYSQL DATABASE ──────────────────────────────────────────────────────────
define('DB_HOST',            'localhost');
define('DB_NAME',            'your_db_name');
define('DB_USER',            'your_db_user');
define('DB_PASS',            'your_db_pass');
define('DB_PREFIX',          'filerbot_');

// ─── GITHUB ──────────────────────────────────────────────────────────────────
define('GITHUB_API_BASE',    'https://api.github.com');
define('GITHUB_USER_AGENT',  'AmirhpFilerBot/1.0');

// ─── DOMAIN BLACKLIST ────────────────────────────────────────────────────────
define('BLACKLISTED_DOMAINS', [
    // 'malware.com',
    // 'blocked-site.net',
]);

// ─── LOGGING ─────────────────────────────────────────────────────────────────
define('LOGGING_ENABLED',    true);

// ─── DEVELOPER INFO ──────────────────────────────────────────────────────────
define('DEV_NAME',           'AmirhpCom');
define('DEV_GITHUB',         'https://github.com/amirhp-com');
define('DEV_WEBSITE',        'https://amirhp.com/landing');
