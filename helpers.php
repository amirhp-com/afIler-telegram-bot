<?php
/**
 * Amirhp Filer Bot — Helpers & Logger
 * Developed by AmirhpCom
 */

class Logger {
    private static string $logFile;

    public static function init(): void {
        self::$logFile = __DIR__ . '/logs/bot.log';
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
    }

    public static function info(string $msg): void  { self::write('INFO', $msg); }
    public static function error(string $msg): void { self::write('ERROR', $msg); }
    public static function debug(string $msg): void { self::write('DEBUG', $msg); }

    private static function write(string $level, string $msg): void {
        $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $msg . PHP_EOL;
        file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

// ─── MESSAGES HELPER ─────────────────────────────────────────────────────────

class Msg {
    private static array $messages = [];

    public static function init(): void {
        self::$messages = require __DIR__ . '/messages.php';
    }

    public static function get(string $key, array $vars = []): string {
        $text = self::$messages[$key] ?? "[$key]";
        if (is_array($text)) $text = $text['text'] ?? '';
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', $v, $text);
        }
        // Replace {max_size} globally
        $text = str_replace('{max_size}', self::maxSize(), $text);
        return $text;
    }

    public static function buttons(string $key): array {
        return self::$messages[$key]['buttons'] ?? [];
    }

    public static function maxSize(): string {
        return DB::getSetting('max_file_size_mb', MAX_FILE_SIZE_MB);
    }
}

// ─── URL & FILE HELPERS ──────────────────────────────────────────────────────

class FileHelper {

    public static function isValidUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('/^https?:\/\//i', $url);
    }

    public static function isBlacklisted(string $url): bool {
        $host = parse_url($url, PHP_URL_HOST);
        foreach (BLACKLISTED_DOMAINS as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.' . $blocked)) return true;
        }
        return false;
    }

    public static function isGitHubRepoUrl(string $url): bool {
        return (bool) preg_match('/^https?:\/\/github\.com\/([^\/]+)\/([^\/\?#]+)\/?$/i', $url);
    }

    public static function parseGitHubUrl(string $url): ?array {
        preg_match('/github\.com\/([^\/]+)\/([^\/\?#]+)/i', $url, $m);
        return isset($m[2]) ? ['owner' => $m[1], 'repo' => rtrim($m[2], '/')] : null;
    }

    public static function getRemoteFileInfo(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'AmirhpFilerBot/1.0',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
        ]);
        curl_exec($ch);
        $size        = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $mime        = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        $mime = explode(';', $mime)[0];
        return [
            'size'         => $size > 0 ? $size : null,
            'mime'         => trim($mime),
            'effective_url'=> $effectiveUrl,
        ];
    }

    public static function downloadFile(string $url, string $dest): bool {
        $fp = fopen($dest, 'wb');
        if (!$fp) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_USERAGENT      => 'AmirhpFilerBot/1.0',
        ]);
        $ok  = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $err) {
            @unlink($dest);
            Logger::error("Download failed: $err");
            return false;
        }
        return true;
    }

    public static function detectFileType(string $mime, string $filename): string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (str_starts_with($mime, 'image/'))    return 'photo';
        if (str_starts_with($mime, 'video/'))    return 'video';
        if (str_starts_with($mime, 'audio/'))    return 'audio';
        $videoExts = ['mp4','mkv','avi','mov','webm','flv','wmv','m4v'];
        $audioExts = ['mp3','ogg','wav','flac','aac','m4a','opus','wma'];
        $imageExts = ['jpg','jpeg','png','gif','webp','bmp','svg'];
        if (in_array($ext, $videoExts)) return 'video';
        if (in_array($ext, $audioExts)) return 'audio';
        if (in_array($ext, $imageExts)) return 'photo';
        return 'document';
    }

    public static function formatSize(int $bytes): string {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 2)    . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 2)       . ' KB';
        return $bytes . ' B';
    }

    public static function buildTempPath(string $filename): string {
        if (!is_dir(TEMP_DIR)) mkdir(TEMP_DIR, 0755, true);
        return TEMP_DIR . uniqid('dl_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    }

    public static function appendBotTag(string $filename): string {
        if (!APPEND_BOT_TAG) return $filename;
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        return $ext ? $base . BOT_FILE_TAG . '.' . $ext : $filename . BOT_FILE_TAG;
    }

    public static function filenameFromUrl(string $url): string {
        $path = parse_url($url, PHP_URL_PATH);
        $name = basename(urldecode($path));
        return $name ?: 'file_' . time();
    }

    public static function cleanTemp(string $path): void {
        if ($path && file_exists($path)) {
            @unlink($path);
            Logger::info("Temp removed: " . basename($path));
        }
    }
}

// ─── GITHUB API ──────────────────────────────────────────────────────────────

class GitHub {

    private static function fetch(string $endpoint): ?array {
        $ch = curl_init(GITHUB_API_BASE . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => GITHUB_USER_AGENT,
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github.v3+json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) return null;
        return json_decode($resp, true);
    }

    public static function getTags(string $owner, string $repo): array {
        $data = self::fetch("/repos/$owner/$repo/tags?per_page=30");
        return $data ?? [];
    }

    public static function getReleases(string $owner, string $repo): array {
        $data = self::fetch("/repos/$owner/$repo/releases?per_page=20");
        return $data ?? [];
    }

    public static function getRelease(string $owner, string $repo, string $tag): ?array {
        return self::fetch("/repos/$owner/$repo/releases/tags/$tag");
    }

    public static function archiveUrl(string $owner, string $repo, string $tag, string $format = 'zipball'): string {
        return "https://github.com/$owner/$repo/archive/refs/tags/$tag." . ($format === 'zipball' ? 'zip' : 'tar.gz');
    }

    public static function repoZipUrl(string $owner, string $repo): string {
        return "https://github.com/$owner/$repo/archive/refs/heads/main.zip";
    }
}

// ─── CALLBACK DATA HELPERS ───────────────────────────────────────────────────

class CB {
    // Encode callback data as compact JSON
    public static function encode(array $data): string {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function decode(string $data): array {
        return json_decode($data, true) ?? [];
    }
}
