<?php
/**
 * Amirhp Filer Bot — Database Handler
 * Developed by AmirhpCom
 */

class DB {
    private static ?PDO $pdo = null;

    public static function conn(): PDO {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                    DB_USER, DB_PASS,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                Logger::error('DB connect failed: ' . $e->getMessage());
                die('Database connection failed.');
            }
        }
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function row(string $sql, array $params = []): ?array {
        $r = self::q($sql, $params)->fetch();
        return $r ?: null;
    }

    public static function all(string $sql, array $params = []): array {
        return self::q($sql, $params)->fetchAll();
    }

    public static function val(string $sql, array $params = []): mixed {
        $r = self::q($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    // ── USER ─────────────────────────────────────────────────────────────────

    public static function getUser(int $userId): ?array {
        return self::row('SELECT * FROM ' . DB_PREFIX . 'users WHERE user_id = ?', [$userId]);
    }

    public static function upsertUser(array $data): void {
        self::q(
            'INSERT INTO ' . DB_PREFIX . 'users (user_id, username, first_name, last_name, is_allowed, created_at, last_active)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               username=VALUES(username), first_name=VALUES(first_name),
               last_name=VALUES(last_name), last_active=NOW()',
            [$data['user_id'], $data['username'] ?? '', $data['first_name'] ?? '', $data['last_name'] ?? '', $data['is_allowed'] ?? 0]
        );
    }

    public static function setUserAllowed(int $userId, bool $allowed): void {
        self::q('UPDATE ' . DB_PREFIX . 'users SET is_allowed=? WHERE user_id=?', [(int)$allowed, $userId]);
    }

    public static function countUsers(): int {
        return (int) self::val('SELECT COUNT(*) FROM ' . DB_PREFIX . 'users');
    }

    // ── CACHE ────────────────────────────────────────────────────────────────

    public static function getCache(string $urlHash): ?array {
        return self::row(
            'SELECT * FROM ' . DB_PREFIX . 'cache WHERE url_hash = ? AND expires_at > NOW()',
            [$urlHash]
        );
    }

    public static function setCache(string $urlHash, string $url, string $fileId, string $fileType, string $filename, int $fileSize): void {
        self::q(
            'INSERT INTO ' . DB_PREFIX . 'cache (url_hash, url, file_id, file_type, filename, file_size, hit_count, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
             ON DUPLICATE KEY UPDATE
               file_id=VALUES(file_id), hit_count=hit_count+1, expires_at=DATE_ADD(NOW(), INTERVAL 30 DAY)',
            [$urlHash, $url, $fileId, $fileType, $filename, $fileSize]
        );
    }

    public static function incrementCacheHit(string $urlHash): void {
        self::q('UPDATE ' . DB_PREFIX . 'cache SET hit_count=hit_count+1 WHERE url_hash=?', [$urlHash]);
    }

    public static function totalCacheHits(): int {
        return (int) self::val('SELECT SUM(hit_count) FROM ' . DB_PREFIX . 'cache');
    }

    // ── LOGS ─────────────────────────────────────────────────────────────────

    public static function log(int $userId, string $action, string $detail = '', int $fileSize = 0, bool $fromCache = false): void {
        if (!LOGGING_ENABLED) return;
        self::q(
            'INSERT INTO ' . DB_PREFIX . 'logs (user_id, action, detail, file_size, from_cache, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$userId, $action, $detail, $fileSize, (int)$fromCache]
        );
        // Also increment user download count
        if ($action === 'download') {
            self::q(
                'UPDATE ' . DB_PREFIX . 'users SET download_count=download_count+1, total_bytes=total_bytes+? WHERE user_id=?',
                [$fileSize, $userId]
            );
        }
    }

    public static function recentLogs(int $limit = 20): array {
        return self::all(
            'SELECT l.*, u.username, u.first_name FROM ' . DB_PREFIX . 'logs l
             LEFT JOIN ' . DB_PREFIX . 'users u ON l.user_id = u.user_id
             ORDER BY l.created_at DESC LIMIT ?',
            [$limit]
        );
    }

    public static function totalDownloads(): int {
        return (int) self::val('SELECT SUM(download_count) FROM ' . DB_PREFIX . 'users');
    }

    public static function totalBytesServed(): int {
        return (int) self::val('SELECT SUM(total_bytes) FROM ' . DB_PREFIX . 'users');
    }

    // ── RATE LIMIT ───────────────────────────────────────────────────────────

    public static function getRateCount(int $userId): int {
        self::q(
            'DELETE FROM ' . DB_PREFIX . 'rate_limit WHERE user_id=? AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)',
            [$userId, RATE_LIMIT_WINDOW]
        );
        return (int) self::val('SELECT COUNT(*) FROM ' . DB_PREFIX . 'rate_limit WHERE user_id=?', [$userId]);
    }

    public static function addRateHit(int $userId): void {
        self::q('INSERT INTO ' . DB_PREFIX . 'rate_limit (user_id, created_at) VALUES (?, NOW())', [$userId]);
    }

    // ── SETTINGS ─────────────────────────────────────────────────────────────

    public static function getSetting(string $key, mixed $default = null): mixed {
        $val = self::val('SELECT val FROM ' . DB_PREFIX . 'settings WHERE `key`=?', [$key]);
        return $val !== null ? $val : $default;
    }

    public static function setSetting(string $key, mixed $val): void {
        self::q(
            'INSERT INTO ' . DB_PREFIX . 'settings (`key`, val) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE val=VALUES(val)',
            [$key, $val]
        );
    }
}
