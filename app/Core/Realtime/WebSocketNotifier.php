<?php

declare(strict_types=1);

namespace App\Core\Realtime;

use App\Core\Config;

final class WebSocketNotifier
{
    public static function publish(string $channel, array $payload): void
    {
        $event = json_encode([
            'channel' => $channel,
            'payload' => $payload,
            'time' => date(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $dir = BASE_PATH . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($dir . '/realtime.jsonl', $event . PHP_EOL, FILE_APPEND | LOCK_EX);
        // In production, point WS_PUSH_URL to a socket gateway that fans out this same payload.
        if (Config::get('WS_PUSH_URL')) {
            $context = stream_context_create(['http' => ['method' => 'POST', 'content' => $event, 'header' => "Content-Type: application/json\r\n"]]);
            @file_get_contents((string) Config::get('WS_PUSH_URL'), false, $context);
        }
    }
}
