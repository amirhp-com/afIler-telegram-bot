<?php
/**
 * Amirhp Filer Bot — Core Handler
 * Developed by AmirhpCom
 * https://amirhp.com/landing | https://github.com/amirhp-com
 */

class Bot {
    private array  $update;
    private int    $chatId;
    private int    $userId;
    private string $firstName;
    private string $username;

    public function __construct(array $update) {
        $this->update = $update;
    }

    public function handle(): void {
        if (isset($this->update['callback_query'])) {
            $this->handleCallback();
        } elseif (isset($this->update['message'])) {
            $this->handleMessage();
        }
    }

    // ─── MESSAGE HANDLER ─────────────────────────────────────────────────────

    private function handleMessage(): void {
        $msg  = $this->update['message'];
        $from = $msg['from'] ?? [];

        $this->chatId    = $msg['chat']['id'];
        $this->userId    = $from['id'] ?? 0;
        $this->firstName = $from['first_name'] ?? 'User';
        $this->username  = $from['username'] ?? '';

        // Upsert user
        DB::upsertUser([
            'user_id'    => $this->userId,
            'username'   => $this->username,
            'first_name' => $this->firstName,
            'last_name'  => $from['last_name'] ?? '',
            'is_allowed' => BOT_PUBLIC ? 1 : (in_array($this->userId, ALLOWED_USER_IDS) ? 1 : 0),
        ]);

        // Access check
        if (!$this->canAccess()) {
            TG::send($this->chatId, Msg::get('access_denied'));
            return;
        }

        // Rate limit check
        if (RATE_LIMIT_ENABLED && !$this->isAdmin()) {
            $count = DB::getRateCount($this->userId);
            if ($count >= RATE_LIMIT_MAX) {
                $remaining = RATE_LIMIT_WINDOW / 60;
                TG::send($this->chatId, Msg::get('rate_limited', ['minutes' => (int)$remaining]));
                return;
            }
        }

        $text = trim($msg['text'] ?? '');
        if (empty($text)) return;

        // Commands
        if (str_starts_with($text, '/')) {
            $this->handleCommand($text);
            return;
        }

        // URL handling
        if (FileHelper::isValidUrl($text)) {
            DB::addRateHit($this->userId);

            if (FileHelper::isBlacklisted($text)) {
                TG::send($this->chatId, Msg::get('error_blacklisted'));
                return;
            }

            try {
                if (FileHelper::isGitHubRepoUrl($text)) {
                    $this->handleGitHubRepo($text);
                } else {
                    $this->handleFileUrl($text);
                }
            } catch (Throwable $e) {
                Logger::error('URL handler error: ' . $e->getMessage());
                TG::send($this->chatId, Msg::get('error_generic'));
            }
            return;
        }

        TG::send($this->chatId, Msg::get('error_not_command'));
    }

    // ─── COMMANDS ────────────────────────────────────────────────────────────

    private function handleCommand(string $text): void {
        $parts   = explode(' ', $text, 2);
        $command = strtolower(explode('@', $parts[0])[0]);
        $arg     = trim($parts[1] ?? '');

        switch ($command) {
            case '/start':
                $buttons = Msg::buttons('welcome');
                TG::send($this->chatId, Msg::get('welcome'), $buttons);
                break;

            case '/help':
                TG::send($this->chatId, Msg::get('help'));
                break;

            case '/stats':
                $user = DB::getUser($this->userId);
                TG::send($this->chatId, Msg::get('user_stats', [
                    'downloads'  => $user['download_count'] ?? 0,
                    'cache_hits' => DB::totalCacheHits(),
                    'since'      => $user['created_at'] ?? '-',
                ]));
                break;

            case '/cancel':
                TG::send($this->chatId, '✅ No active operation to cancel.');
                break;

            // ── ADMIN COMMANDS ────────────────────────────────────────────

            case '/admin_stats':
                if (!$this->isAdmin()) { TG::send($this->chatId, Msg::get('admin_only')); return; }
                TG::send($this->chatId, Msg::get('admin_stats', [
                    'total_users'     => DB::countUsers(),
                    'total_downloads' => DB::totalDownloads(),
                    'cache_hits'      => DB::totalCacheHits(),
                    'total_size'      => FileHelper::formatSize(DB::totalBytesServed()),
                    'since'           => date('Y-m-d'),
                ]));
                break;

            case '/admin_logs':
                if (!$this->isAdmin()) { TG::send($this->chatId, Msg::get('admin_only')); return; }
                $logs    = DB::recentLogs(15);
                $text    = Msg::get('logs_header', ['count' => count($logs)]);
                foreach ($logs as $l) {
                    $name  = $l['first_name'] ?: $l['username'] ?: $l['user_id'];
                    $size  = $l['file_size'] ? ' [' . FileHelper::formatSize($l['file_size']) . ']' : '';
                    $cache = $l['from_cache'] ? ' ⚡' : '';
                    $text .= "• <b>{$l['action']}</b>{$cache} — {$name}{$size}\n  <code>" . htmlspecialchars(substr($l['detail'], 0, 60)) . "</code>\n  <i>{$l['created_at']}</i>\n\n";
                }
                TG::send($this->chatId, $text);
                break;

            case '/setlimit':
                if (!$this->isAdmin()) { TG::send($this->chatId, Msg::get('admin_only')); return; }
                $mb = (int) $arg;
                if ($mb < 1 || $mb > 50) {
                    TG::send($this->chatId, '❌ Value must be between 1 and 50 MB.');
                    return;
                }
                DB::setSetting('max_file_size_mb', $mb);
                TG::send($this->chatId, Msg::get('limit_updated', ['size' => $mb]));
                break;

            case '/setpublic':
                if (!$this->isAdmin()) { TG::send($this->chatId, Msg::get('admin_only')); return; }
                $val  = $arg === '1' ? 1 : 0;
                DB::setSetting('bot_public', $val);
                TG::send($this->chatId, Msg::get('public_updated', ['mode' => $val ? 'Public ✅' : 'Private 🔒']));
                break;

            case '/adduser':
                if (!$this->isAdmin()) { TG::send($this->chatId, Msg::get('admin_only')); return; }
                if (!$arg) { TG::send($this->chatId, '❌ Usage: /adduser {user_id}'); return; }
                DB::setUserAllowed((int)$arg, true);
                TG::send($this->chatId, Msg::get('user_added', ['user_id' => $arg]));
                break;

            case '/removeuser':
                if (!$this->isAdmin()) { TG::send($this->chatId, Msg::get('admin_only')); return; }
                if (!$arg) { TG::send($this->chatId, '❌ Usage: /removeuser {user_id}'); return; }
                DB::setUserAllowed((int)$arg, false);
                TG::send($this->chatId, Msg::get('user_removed', ['user_id' => $arg]));
                break;

            case '/clearqueue':
                if (!$this->isAdmin()) { TG::send($this->chatId, Msg::get('admin_only')); return; }
                // Clean temp dir
                $count = 0;
                foreach (glob(TEMP_DIR . 'dl_*') as $f) { @unlink($f); $count++; }
                TG::send($this->chatId, Msg::get('queue_cleared', ['count' => $count]));
                break;

            default:
                TG::send($this->chatId, Msg::get('error_not_command'));
        }
    }

    // ─── FILE URL HANDLER ────────────────────────────────────────────────────

    private function handleFileUrl(string $url): void {
        $urlHash = md5($url);

        // Check cache first
        $cached = DB::getCache($urlHash);
        if ($cached) {
            $statusMsg = TG::send($this->chatId, Msg::get('sending_cached', ['filename' => $cached['filename']]));
            $result    = TG::sendCachedFile($this->chatId, $cached['file_id'], $cached['file_type']);
            if ($statusMsg) TG::deleteMsg($this->chatId, $statusMsg['message_id']);
            if ($result) {
                DB::incrementCacheHit($urlHash);
                DB::log($this->userId, 'download', $url, $cached['file_size'], true);
            } else {
                TG::send($this->chatId, Msg::get('error_upload'));
            }
            return;
        }

        // Fetch file info
        $statusMsg = TG::send($this->chatId, Msg::get('fetching', ['url' => htmlspecialchars($url)]));
        $info      = FileHelper::getRemoteFileInfo($url);
        $maxBytes  = (int) DB::getSetting('max_file_size_mb', MAX_FILE_SIZE_MB) * 1024 * 1024;

        // Size check
        if ($info['size'] !== null && $info['size'] > $maxBytes) {
            if ($statusMsg) TG::deleteMsg($this->chatId, $statusMsg['message_id']);
            TG::send($this->chatId, Msg::get('file_too_large', [
                'size'     => FileHelper::formatSize($info['size']),
                'max_size' => DB::getSetting('max_file_size_mb', MAX_FILE_SIZE_MB),
            ]));
            return;
        }

        // Prepare filename
        $rawFilename = FileHelper::filenameFromUrl($info['effective_url'] ?: $url);
        $filename    = FileHelper::appendBotTag($rawFilename);
        $tempPath    = FileHelper::buildTempPath($filename);

        // Update status
        if ($statusMsg) {
            TG::edit($this->chatId, $statusMsg['message_id'], Msg::get('downloading', [
                'filename' => htmlspecialchars($filename),
                'size'     => $info['size'] ? FileHelper::formatSize($info['size']) : 'Unknown',
            ]));
        }

        // Download
        if (!FileHelper::downloadFile($url, $tempPath)) {
            if ($statusMsg) TG::deleteMsg($this->chatId, $statusMsg['message_id']);
            TG::send($this->chatId, Msg::get('error_download'));
            FileHelper::cleanTemp($tempPath);
            return;
        }

        // Post-download size check (if size was unknown)
        $actualSize = filesize($tempPath);
        if ($actualSize > $maxBytes) {
            if ($statusMsg) TG::deleteMsg($this->chatId, $statusMsg['message_id']);
            TG::send($this->chatId, Msg::get('file_too_large', [
                'size'     => FileHelper::formatSize($actualSize),
                'max_size' => DB::getSetting('max_file_size_mb', MAX_FILE_SIZE_MB),
            ]));
            FileHelper::cleanTemp($tempPath);
            return;
        }

        // Detect type
        $mime     = mime_content_type($tempPath) ?: $info['mime'];
        $fileType = FileHelper::detectFileType($mime, $filename);

        // Update status
        if ($statusMsg) {
            TG::edit($this->chatId, $statusMsg['message_id'], Msg::get('uploading', [
                'filename' => htmlspecialchars($filename),
            ]));
        }

        // Send to Telegram
        $result = match($fileType) {
            'video'  => TG::sendVideo($this->chatId, $tempPath, '', $filename),
            'audio'  => TG::sendAudio($this->chatId, $tempPath, '', $filename),
            'photo'  => TG::sendPhoto($this->chatId, $tempPath),
            default  => TG::sendDocument($this->chatId, $tempPath, '', $filename),
        };

        // Cleanup temp ALWAYS
        FileHelper::cleanTemp($tempPath);

        if ($statusMsg) TG::deleteMsg($this->chatId, $statusMsg['message_id']);

        if ($result) {
            // Extract file_id from result
            $fileId = $result['document']['file_id']
                ?? $result['video']['file_id']
                ?? $result['audio']['file_id']
                ?? $result['photo'][count($result['photo'] ?? [])-1]['file_id']
                ?? null;

            if ($fileId) {
                DB::setCache($urlHash, $url, $fileId, $fileType, $filename, $actualSize);
            }
            DB::log($this->userId, 'download', $url, $actualSize, false);
        } else {
            TG::send($this->chatId, Msg::get('error_upload'));
        }
    }

    // ─── GITHUB HANDLER ──────────────────────────────────────────────────────

    private function handleGitHubRepo(string $url): void {
        $repo = FileHelper::parseGitHubUrl($url);
        if (!$repo) { TG::send($this->chatId, Msg::get('error_invalid_url')); return; }

        $owner = $repo['owner'];
        $name  = $repo['repo'];

        TG::send($this->chatId, Msg::get('github_detected', ['owner' => $owner, 'repo' => $name]), [
            [
                ['text' => '🏷 Browse Tags',     'callback_data' => CB::encode(['a' => 'gh_tags',    'o' => $owner, 'r' => $name])],
                ['text' => '🚀 Releases',         'callback_data' => CB::encode(['a' => 'gh_rels',    'o' => $owner, 'r' => $name])],
            ],
            [
                ['text' => '📦 Download Full ZIP','callback_data' => CB::encode(['a' => 'gh_zip',     'o' => $owner, 'r' => $name])],
            ],
        ]);
    }

    // ─── CALLBACK HANDLER ────────────────────────────────────────────────────

    private function handleCallback(): void {
        $cb   = $this->update['callback_query'];
        $from = $cb['from'];
        $msg  = $cb['message'];

        $this->chatId    = $msg['chat']['id'];
        $this->userId    = $from['id'];
        $this->firstName = $from['first_name'] ?? '';
        $this->username  = $from['username'] ?? '';

        TG::answerCallback($cb['id']);

        if (!$this->canAccess()) return;

        $data   = CB::decode($cb['data'] ?? '{}');
        $action = $data['a'] ?? '';
        $owner  = $data['o'] ?? '';
        $repo   = $data['r'] ?? '';
        $tag    = $data['t'] ?? '';
        $msgId  = $msg['message_id'];

        try {

        switch ($action) {

            case 'gh_tags':
                $tags = GitHub::getTags($owner, $repo);
                if (empty($tags)) {
                    TG::edit($this->chatId, $msgId, Msg::get('error_no_tags'));
                    return;
                }
                $buttons = [];
                foreach (array_slice($tags, 0, 20) as $t) {
                    $buttons[] = [['text' => '🏷 ' . $t['name'], 'callback_data' => CB::encode(['a' => 'gh_tag_opt', 'o' => $owner, 'r' => $repo, 't' => $t['name']])]];
                }
                $buttons[] = [['text' => '⬅️ Back', 'callback_data' => CB::encode(['a' => 'gh_back', 'o' => $owner, 'r' => $repo])]];
                TG::edit($this->chatId, $msgId, Msg::get('github_tags', ['owner' => $owner, 'repo' => $repo]), $buttons);
                break;

            case 'gh_tag_opt':
                $buttons = [
                    [['text' => '📦 Download ZIP',    'callback_data' => CB::encode(['a' => 'gh_dl_zip', 'o' => $owner, 'r' => $repo, 't' => $tag])]],
                    [['text' => '🗜 Download TAR.GZ',  'callback_data' => CB::encode(['a' => 'gh_dl_tar', 'o' => $owner, 'r' => $repo, 't' => $tag])]],
                    [['text' => '⬅️ Back to Tags',    'callback_data' => CB::encode(['a' => 'gh_tags',   'o' => $owner, 'r' => $repo])]],
                ];
                // Check for release assets too
                $release = GitHub::getRelease($owner, $repo, $tag);
                if ($release && !empty($release['assets'])) {
                    array_splice($buttons, 2, 0, [[['text' => '📎 Release Assets (' . count($release['assets']) . ')', 'callback_data' => CB::encode(['a' => 'gh_assets', 'o' => $owner, 'r' => $repo, 't' => $tag])]]]);
                }
                TG::edit($this->chatId, $msgId, Msg::get('github_tag_options', ['tag' => $tag, 'owner' => $owner, 'repo' => $repo]), $buttons);
                break;

            case 'gh_rels':
                $releases = GitHub::getReleases($owner, $repo);
                if (empty($releases)) {
                    TG::edit($this->chatId, $msgId, Msg::get('error_no_tags'));
                    return;
                }
                $buttons = [];
                foreach (array_slice($releases, 0, 15) as $rel) {
                    $label = ($rel['prerelease'] ? '🧪 ' : '🚀 ') . $rel['name'];
                    $buttons[] = [['text' => $label, 'callback_data' => CB::encode(['a' => 'gh_tag_opt', 'o' => $owner, 'r' => $repo, 't' => $rel['tag_name']])]];
                }
                $buttons[] = [['text' => '⬅️ Back', 'callback_data' => CB::encode(['a' => 'gh_back', 'o' => $owner, 'r' => $repo])]];
                TG::edit($this->chatId, $msgId, Msg::get('github_releases', ['owner' => $owner, 'repo' => $repo]), $buttons);
                break;

            case 'gh_assets':
                $release = GitHub::getRelease($owner, $repo, $tag);
                if (!$release || empty($release['assets'])) {
                    TG::edit($this->chatId, $msgId, Msg::get('github_no_assets'));
                    return;
                }
                $buttons = [];
                foreach ($release['assets'] as $asset) {
                    $size = FileHelper::formatSize($asset['size']);
                    $uk   = DB::storeUrl($asset['browser_download_url']);
                    $buttons[] = [['text' => "📎 {$asset['name']} ({$size})", 'callback_data' => CB::encode(['a' => 'gh_dl_asset', 'k' => $uk])]];
                }
                $buttons[] = [['text' => '⬅️ Back', 'callback_data' => CB::encode(['a' => 'gh_tag_opt', 'o' => $owner, 'r' => $repo, 't' => $tag])]];
                TG::edit($this->chatId, $msgId, Msg::get('github_release_assets', ['release' => $release['name']]), $buttons);
                break;

            case 'gh_dl_zip':
                $url = GitHub::archiveUrl($owner, $repo, $tag, 'zipball');
                TG::edit($this->chatId, $msgId, "⏬ Preparing ZIP for <b>$tag</b>...");
                $this->handleFileUrl($url);
                break;

            case 'gh_dl_tar':
                $url = GitHub::archiveUrl($owner, $repo, $tag, 'tarball');
                TG::edit($this->chatId, $msgId, "⏬ Preparing TAR.GZ for <b>$tag</b>...");
                $this->handleFileUrl($url);
                break;

            case 'gh_dl_asset':
                $assetUrl = DB::fetchUrl($data['k'] ?? '');
                if ($assetUrl) {
                    TG::edit($this->chatId, $msgId, "⏬ Downloading asset...");
                    $this->handleFileUrl($assetUrl);
                } else {
                    TG::edit($this->chatId, $msgId, Msg::get('error_generic'));
                }
                break;

            case 'gh_zip':
                $url = GitHub::repoZipUrl($owner, $repo);
                TG::edit($this->chatId, $msgId, Msg::get('github_full_zip', ['owner' => $owner, 'repo' => $repo]));
                $this->handleFileUrl($url);
                break;

            case 'gh_back':
                TG::edit($this->chatId, $msgId, Msg::get('github_detected', ['owner' => $owner, 'repo' => $repo]), [
                    [
                        ['text' => '🏷 Browse Tags',      'callback_data' => CB::encode(['a' => 'gh_tags',  'o' => $owner, 'r' => $repo])],
                        ['text' => '🚀 Releases',          'callback_data' => CB::encode(['a' => 'gh_rels',  'o' => $owner, 'r' => $repo])],
                    ],
                    [
                        ['text' => '📦 Download Full ZIP', 'callback_data' => CB::encode(['a' => 'gh_zip',   'o' => $owner, 'r' => $repo])],
                    ],
                ]);
                break;
        }

        } catch (Throwable $e) {
            Logger::error('Callback handler error: ' . $e->getMessage());
            TG::send($this->chatId, Msg::get('error_generic'));
        }
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    private function isAdmin(): bool {
        return in_array($this->userId, ADMIN_IDS);
    }

    private function canAccess(): bool {
        if ($this->isAdmin()) return true;
        $isPublic = (bool)(int) DB::getSetting('bot_public', (int) BOT_PUBLIC);
        if ($isPublic) return true;
        $user = DB::getUser($this->userId);
        return $user && $user['is_allowed'];
    }
}
