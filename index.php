<?php
/**
 * Amirhp Filer Bot — Webhook Entry Point
 * Developed by AmirhpCom
 * https://amirhp.com/landing | https://github.com/amirhp-com
 *
 * Place this file at your webhook URL path.
 * Set webhook: https://yourdomain.com/YOUR_PATH/index.php
 */

declare(strict_types=1);

// ── BOOTSTRAP ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/bot.php';

// Init
Logger::init();
Msg::init();
TG::init();

// ── WEBHOOK SECRET VERIFICATION ──────────────────────────────────────────────

if (WEBHOOK_SECRET !== '') {
    $incoming = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if ($incoming !== WEBHOOK_SECRET) {
        http_response_code(403);
        Logger::error('Invalid webhook secret from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        exit('Forbidden');
    }
}

// ── ONLY ACCEPT POST ─────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(200);
    echo '<!-- Amirhp Filer Bot - Developed by AmirhpCom -->';
    exit;
}

// ── READ UPDATE ──────────────────────────────────────────────────────────────

$input  = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update || !is_array($update)) {
    http_response_code(200);
    exit;
}

// ── HANDLE ───────────────────────────────────────────────────────────────────

try {
    $bot = new Bot($update);
    $bot->handle();
} catch (Throwable $e) {
    Logger::error('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

http_response_code(200);
echo 'OK';
