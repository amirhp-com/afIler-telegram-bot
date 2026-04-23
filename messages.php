<?php
/**
 * Amirhp Filer Bot — Messages
 * Edit all bot messages here.
 * Supports: {var} placeholders replaced at runtime.
 */

return [

    // ── WELCOME ──────────────────────────────────────────────────────────────
    'welcome' => [
        'text' => "👋 <b>Welcome to Amirhp Filer Bot!</b>\n\n"
            . "I can:\n"
            . "📥 Download any direct file URL and send it to you\n"
            . "🎬 Send videos, 🖼 images, 🎵 audio as native Telegram media\n"
            . "🐙 Browse GitHub repo tags, releases, and download archives\n\n"
            . "📌 Just send me a URL and I'll handle the rest!\n\n"
            . "⚙️ Use /help to see all commands.\n\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "👨‍💻 Developed by <b>AmirhpCom</b>",
        'buttons' => [
            [
                ['text' => '👨‍💻 Developer', 'url' => 'https://amirhp.com/landing'],
                ['text' => '⭐ GitHub',    'url' => 'https://github.com/amirhp-com'],
            ],
        ],
    ],

    // ── HELP ─────────────────────────────────────────────────────────────────
    'help' => "📖 <b>Amirhp Filer Bot — Help</b>\n\n"
        . "<b>Commands:</b>\n"
        . "/start — Welcome message\n"
        . "/help — This help message\n"
        . "/stats — Your download statistics\n"
        . "/cancel — Cancel current operation\n\n"
        . "<b>Admin Commands:</b>\n"
        . "/admin_stats — Global bot statistics\n"
        . "/admin_logs — Recent activity logs\n"
        . "/setlimit {mb} — Change max file size limit\n"
        . "/setpublic {0|1} — Toggle public/private mode\n"
        . "/adduser {user_id} — Whitelist a user\n"
        . "/removeuser {user_id} — Remove user from whitelist\n"
        . "/clearqueue — Clear pending downloads\n\n"
        . "<b>How to use:</b>\n"
        . "• Send any direct file URL → I download and send it\n"
        . "• Send a GitHub repo URL → I list tags & releases\n\n"
        . "📦 Max file size: <b>{max_size}MB</b>",

    // ── ACCESS DENIED ────────────────────────────────────────────────────────
    'access_denied' => "🚫 <b>Access Denied</b>\n\nThis bot is private. Contact the admin to get access.",

    // ── RATE LIMIT ───────────────────────────────────────────────────────────
    'rate_limited' => "⏳ <b>Slow down!</b>\n\nYou've made too many requests. Try again in {minutes} minutes.",

    // ── URL PROCESSING ───────────────────────────────────────────────────────
    'fetching'         => "⏬ <b>Fetching file info...</b>\nURL: <code>{url}</code>",
    'downloading'      => "📥 <b>Downloading...</b>\n\n📄 File: <code>{filename}</code>\n📦 Size: <b>{size}</b>\n\nPlease wait...",
    'uploading'        => "📤 <b>Uploading to Telegram...</b>\n\n📄 <code>{filename}</code>",
    'sending_cached'   => "⚡ <b>Sending from cache...</b>\n📄 <code>{filename}</code>",

    // ── FILE TOO LARGE ───────────────────────────────────────────────────────
    'file_too_large' => "🚫 <b>File Too Large!</b>\n\n"
        . "📦 File size: <b>{size}</b>\n"
        . "⚠️ Max allowed: <b>{max_size}MB</b>\n\n"
        . "Telegram bots can only send files up to {max_size}MB.\n"
        . "This file cannot be delivered through this bot.",

    // ── FILE SIZE UNKNOWN ────────────────────────────────────────────────────
    'size_unknown_proceed' => "⚠️ <b>Unknown File Size</b>\n\n"
        . "Could not determine file size before downloading.\n"
        . "Proceeding with download — will abort if too large.",

    // ── SUCCESS ──────────────────────────────────────────────────────────────
    'success' => "✅ <b>Done!</b>\n\n📄 <code>{filename}</code>\n📦 Size: <b>{size}</b>",

    // ── ERRORS ───────────────────────────────────────────────────────────────
    'error_download'      => "❌ <b>Download Failed</b>\n\nCould not download the file.\nMake sure the URL is a direct download link.",
    'error_upload'        => "❌ <b>Upload Failed</b>\n\nFile downloaded but Telegram upload failed. Temp file removed.",
    'error_invalid_url'   => "❌ <b>Invalid URL</b>\n\nPlease send a valid direct download URL or GitHub repo URL.",
    'error_blacklisted'   => "🚫 <b>Blocked Domain</b>\n\nThis domain is not allowed.",
    'error_github_api'    => "❌ <b>GitHub API Error</b>\n\nCould not fetch repo data. Repo may not exist or API limit reached.",
    'error_no_tags'       => "📭 <b>No Tags Found</b>\n\nThis repo has no tags or releases.",
    'error_generic'       => "❌ <b>Something went wrong.</b>\n\nPlease try again later.",
    'error_not_command'   => "❓ Send me a URL to get started, or use /help.",

    // ── GITHUB REPO ──────────────────────────────────────────────────────────
    'github_detected'  => "🐙 <b>GitHub Repo Detected</b>\n\n<code>{owner}/{repo}</code>\n\nChoose an option:",
    'github_tags'      => "🏷 <b>Available Tags</b>\n\n<code>{owner}/{repo}</code>\n\nSelect a tag to see download options:",
    'github_tag_options' => "📦 <b>Tag: {tag}</b>\n\n<code>{owner}/{repo}</code>\n\nChoose archive format:",
    'github_releases'  => "🚀 <b>Releases</b>\n\n<code>{owner}/{repo}</code>\n\nSelect a release:",
    'github_release_assets' => "📎 <b>Release: {release}</b>\n\nAssets available:",
    'github_no_assets' => "📭 This release has no attached assets. Only source archives available.",
    'github_full_zip'  => "📥 <b>Downloading full repo ZIP...</b>\n\n<code>{owner}/{repo}</code>",

    // ── ADMIN ────────────────────────────────────────────────────────────────
    'admin_only'       => "🔒 This command is for admins only.",
    'admin_stats'      => "📊 <b>Bot Statistics</b>\n\n"
        . "👥 Total users: <b>{total_users}</b>\n"
        . "📥 Total downloads: <b>{total_downloads}</b>\n"
        . "⚡ Cached hits: <b>{cache_hits}</b>\n"
        . "💾 Data served: <b>{total_size}</b>\n"
        . "🕐 Uptime since: <b>{since}</b>",
    'user_stats'       => "📊 <b>Your Statistics</b>\n\n"
        . "📥 Downloads: <b>{downloads}</b>\n"
        . "⚡ Cache hits: <b>{cache_hits}</b>\n"
        . "📅 Member since: <b>{since}</b>",
    'limit_updated'    => "✅ Max file size updated to <b>{size}MB</b>",
    'public_updated'   => "✅ Bot mode set to: <b>{mode}</b>",
    'user_added'       => "✅ User <code>{user_id}</code> added to whitelist.",
    'user_removed'     => "✅ User <code>{user_id}</code> removed from whitelist.",
    'queue_cleared'    => "✅ Queue cleared. <b>{count}</b> pending item(s) removed.",
    'logs_header'      => "📋 <b>Recent Logs</b> (last {count}):\n\n",

];
