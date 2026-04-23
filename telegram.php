<?php

/**
 * Amirhp Filer Bot — Telegram API
 * Developed by AmirhpCom
 */

class TG {
    private static string $base;

    public static function init(): void {
        self::$base = 'https://api.telegram.org/bot' . BOT_TOKEN . '/';
    }

    // ── CORE REQUEST ─────────────────────────────────────────────────────────

    private static function request(string $method, array $params = [], bool $multipart = false): ?array {
        $url = self::$base . $method;
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_POST           => true,
        ]);

        if ($multipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            Logger::error("cURL error [$method]: $err");
            return null;
        }
        $data = json_decode($resp, true);
        if (!$data || !$data['ok']) {
            Logger::error("TG API error [$method]: " . ($data['description'] ?? $resp));
            return null;
        }
        return $data['result'];
    }

    // ── SEND MESSAGE ─────────────────────────────────────────────────────────

    public static function send(int|string $chatId, string $text, array $keyboard = [], string $parseMode = 'HTML'): ?array {
        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ];
        if (!empty($keyboard)) {
            $params['reply_markup'] = ['inline_keyboard' => $keyboard];
        }
        return self::request('sendMessage', $params);
    }

    public static function edit(int|string $chatId, int $msgId, string $text, array $keyboard = [], string $parseMode = 'HTML'): ?array {
        $params = [
            'chat_id'    => $chatId,
            'message_id' => $msgId,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ];
        if (!empty($keyboard)) {
            $params['reply_markup'] = ['inline_keyboard' => $keyboard];
        } else {
            $params['reply_markup'] = ['inline_keyboard' => []];
        }
        return self::request('editMessageText', $params);
    }

    public static function deleteMsg(int|string $chatId, int $msgId): void {
        self::request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $msgId]);
    }

    public static function answerCallback(string $callbackId, string $text = '', bool $alert = false): void {
        self::request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text'              => $text,
            'show_alert'        => $alert,
        ]);
    }

    // ── SEND FILES ───────────────────────────────────────────────────────────

    public static function sendDocument(int $chatId, string $filePath, string $caption = '', string $filename = ''): ?array {
        $fields = [
            'chat_id'    => $chatId,
            'document'   => new CURLFile($filePath, mime_content_type($filePath) ?: 'application/octet-stream', $filename ?: basename($filePath)),
        ];
        if ($caption) {
            $fields['caption'] = $caption;
            $fields['parse_mode'] = 'HTML';
        }
        return self::request('sendDocument', $fields, true);
    }

    public static function sendVideo(int $chatId, string $filePath, string $caption = '', string $filename = ''): ?array {
        $fields = [
            'chat_id'    => $chatId,
            'video'      => new CURLFile($filePath, 'video/' . pathinfo($filePath, PATHINFO_EXTENSION), $filename ?: basename($filePath)),
            'supports_streaming' => 'true',
        ];
        if ($caption) {
            $fields['caption'] = $caption;
            $fields['parse_mode'] = 'HTML';
        }
        return self::request('sendVideo', $fields, true);
    }

    public static function sendAudio(int $chatId, string $filePath, string $caption = '', string $filename = ''): ?array {
        $fields = [
            'chat_id'    => $chatId,
            'audio'      => new CURLFile($filePath, 'audio/' . pathinfo($filePath, PATHINFO_EXTENSION), $filename ?: basename($filePath)),
        ];
        if ($caption) {
            $fields['caption'] = $caption;
            $fields['parse_mode'] = 'HTML';
        }
        return self::request('sendAudio', $fields, true);
    }

    public static function sendPhoto(int $chatId, string $filePath, string $caption = ''): ?array {
        $fields = [
            'chat_id' => $chatId,
            'photo'   => new CURLFile($filePath, mime_content_type($filePath) ?: 'image/jpeg', basename($filePath)),
        ];
        if ($caption) {
            $fields['caption'] = $caption;
            $fields['parse_mode'] = 'HTML';
        }
        return self::request('sendPhoto', $fields, true);
    }

    // Send by file_id (cached)
    public static function sendCachedFile(int $chatId, string $fileId, string $type, string $caption = ''): ?array {
        $methodMap = [
            'video'    => 'sendVideo',
            'audio'    => 'sendAudio',
            'photo'    => 'sendPhoto',
            'document' => 'sendDocument',
        ];
        $fieldMap = [
            'video'    => 'video',
            'audio'    => 'audio',
            'photo'    => 'photo',
            'document' => 'document',
        ];
        $method = $methodMap[$type] ?? 'sendDocument';
        $field  = $fieldMap[$type]  ?? 'document';
        $params = ['chat_id' => $chatId, $field => $fileId];
        if ($caption) {
            $params['caption'] = $caption;
            $params['parse_mode'] = 'HTML';
        }
        return self::request($method, $params);
    }

    // ── WEBHOOK ──────────────────────────────────────────────────────────────

    public static function setWebhook(string $url, string $secret = ''): ?array {
        $params = ['url' => $url, 'allowed_updates' => ['message', 'callback_query']];
        if ($secret) $params['secret_token'] = $secret;

        // DEBUG
        $ch = curl_init(self::$base . 'setWebhook');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        echo '<pre>setWebhook raw: ' . ($err ?: $resp) . '</pre>';

        return self::request('setWebhook', $params);
    }

    public static function deleteWebhook(): ?array {
        return self::request('deleteWebhook');
    }

    public static function getWebhookInfo(): ?array {
        return self::request('getWebhookInfo');
    }
}
