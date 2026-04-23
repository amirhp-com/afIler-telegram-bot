# 🤖 Amirhp Filer Bot

A powerful Telegram bot that downloads files from URLs and sends them natively — as video, audio, image, or document. Also supports full GitHub repository browsing: tags, releases, assets, and archive downloads.

Built for shared cPanel hosting with PHP 8.1. No Docker, no CLI tools, no dependencies beyond PHP and MySQL.

---

## ✨ Features

- **📥 Direct URL Download** — Send any direct file URL, bot downloads and sends it back
- **🎬 Smart Media Detection** — Videos sent as video, audio as music, images as photos, everything else as document
- **⚡ File Cache** — Same URL sent twice? Bot skips re-download and uses cached Telegram `file_id`
- **🐙 GitHub Integration** — Paste any GitHub repo URL to:
  - Browse and select tags
  - Browse releases (stable + pre-release)
  - Download ZIP or TAR.GZ archives per tag
  - Download release assets
  - Download the full repo as ZIP
- **🔒 Access Control** — Toggle between public and private mode; whitelist specific users
- **📊 Logging** — All activity logged to MySQL with per-user stats
- **⚙️ Admin Commands** — Manage users, view stats, adjust limits, clear queue
- **🛡 Webhook Security** — Secret token verification on every request
- **🚦 Rate Limiting** — Configurable max requests per user per hour
- **🏷 File Tagging** — Optional bot name appended to downloaded filenames
- **🧹 Auto Cleanup** — Temp files always deleted after sending

---

## 📋 Requirements

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- cURL extension enabled
- `file_get_contents` allowed
- HTTPS domain (required by Telegram for webhooks)
- Telegram Bot Token from [@BotFather](https://t.me/BotFather)

---

## 🚀 Installation

### Step 1 — Get a Bot Token

1. Open Telegram, start a chat with [@BotFather](https://t.me/BotFather)
2. Send `/newbot`
3. Follow the prompts, set name as `Amirhp Filer Bot` and username as `amirhp_filerbot`
4. Copy the token

### Step 2 — Upload Files

Upload all bot files to your cPanel hosting. Recommended path:

```
public_html/tgbot/
```

Make sure the directory is accessible via HTTPS.

### Step 3 — Configure

Edit `config.php`:

```php
define('BOT_TOKEN',      'YOUR_BOT_TOKEN_HERE');
define('WEBHOOK_SECRET', 'any_random_string_here');  // use a long random string

define('ADMIN_IDS',      [YOUR_TELEGRAM_USER_ID]);   // get from @userinfobot

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

Key settings:

| Setting | Default | Description |
|---|---|---|
| `BOT_PUBLIC` | `false` | `true` = anyone can use, `false` = whitelist only |
| `MAX_FILE_SIZE_MB` | `50` | Max file size (Telegram hard limit = 50MB) |
| `RATE_LIMIT_MAX` | `10` | Max requests per user per window |
| `RATE_LIMIT_WINDOW` | `3600` | Rate limit window in seconds |
| `APPEND_BOT_TAG` | `true` | Append `_AmirhpFilerBot` to filenames |
| `LOGGING_ENABLED` | `true` | Log all activity to DB |

### Step 4 — Run Setup

Edit `setup.php` — change the secret string on this line:

```php
if ($secret !== 'CHANGE_THIS_SETUP_SECRET') {
```

Then visit in browser:

```
https://yourdomain.com/tgbot/setup.php?secret=CHANGE_THIS_SETUP_SECRET
```

This will:
- Create all MySQL tables
- Create the `tmp/` directory with proper `.htaccess`
- Register your webhook with Telegram automatically

### Step 5 — Secure Setup File

After setup, **delete or restrict `setup.php`**:

```bash
# Via cPanel File Manager: delete setup.php
# Or add to .htaccess:
<Files "setup.php">
    Deny from all
</Files>
```

---

## 📁 File Structure

```
tgbot/
├── index.php       ← Webhook entry point (only public-facing PHP)
├── setup.php       ← One-time setup (delete after use)
├── config.php      ← All configuration
├── messages.php    ← All bot messages (edit text here)
├── bot.php         ← Core bot logic
├── db.php          ← Database handler
├── telegram.php    ← Telegram API wrapper
├── helpers.php     ← Utilities, GitHub API, Logger
├── .htaccess       ← Security rules
├── sql/
│   └── schema.sql  ← Manual DB import (alternative to setup.php)
├── tmp/            ← Temp downloads (auto-created, auto-cleaned)
└── logs/
    └── bot.log     ← Runtime logs
```

---

## 🤖 Bot Commands

### User Commands

| Command | Description |
|---|---|
| `/start` | Welcome message with developer links |
| `/help` | Full command list and usage |
| `/stats` | Your personal download statistics |
| `/cancel` | Cancel current operation |

### Admin Commands

| Command | Description |
|---|---|
| `/admin_stats` | Global bot stats (users, downloads, cache hits) |
| `/admin_logs` | Last 15 activity log entries |
| `/setlimit 25` | Set max file size to 25MB |
| `/setpublic 1` | Make bot public (`0` = private) |
| `/adduser 123456789` | Add user to whitelist |
| `/removeuser 123456789` | Remove user from whitelist |
| `/clearqueue` | Delete all pending temp files |

---

## 🐙 GitHub URL Usage

Just paste a GitHub repo URL:

```
https://github.com/owner/reponame
```

The bot will show buttons:
- **Browse Tags** → pick a tag → download ZIP or TAR.GZ (+ release assets if available)
- **Releases** → pick a release → same options
- **Download Full ZIP** → downloads default branch as ZIP

---

## ✏️ Editing Bot Messages

All user-facing text is in `messages.php`. Edit that file to change any message, button text, or format. Supports `{placeholders}` which are replaced at runtime.

---

## ⚠️ File Size Limit

Telegram Bot API hard limit = **50MB**. This cannot be increased on shared cPanel hosting.

If a file exceeds the limit, the bot will:
1. Detect the size via HTTP HEAD request before downloading
2. Send a clear error message with the file size
3. Not download the file at all (no wasted bandwidth)

For files with unknown size, the bot downloads first, checks size, and aborts if too large — cleaning up temp file immediately.

> **Note:** Larger file support (up to 2GB) is possible via a self-hosted Telegram Bot API server on a VPS. A future version may support this.

---

## 🛡 Security

- Webhook secret token prevents unauthorized calls
- `.htaccess` blocks direct access to all PHP files except `index.php`
- Temp directory is web-inaccessible
- Domain blacklist configurable in `config.php`
- Rate limiting per user

---

## 🗄 Database Tables

| Table | Purpose |
|---|---|
| `filerbot_users` | User registry, access control, stats |
| `filerbot_cache` | URL → Telegram file_id cache (30-day TTL) |
| `filerbot_logs` | Activity log |
| `filerbot_rate_limit` | Per-user request tracking |
| `filerbot_settings` | Runtime-editable settings |

---

## 👨‍💻 Developer

**AmirhpCom**

- 🌐 Website: [amirhp.com/landing](https://amirhp.com/landing)
- 💻 GitHub: [github.com/amirhp-com](https://github.com/amirhp-com)

---

## 📄 License

MIT License — free to use, modify, and distribute.
