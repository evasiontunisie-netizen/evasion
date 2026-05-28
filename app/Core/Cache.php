<?php

declare(strict_types=1);

namespace App\Core;

final class Cache
{
    public static function remember(string $key, int $seconds, callable $callback): mixed
    {
        $file = self::path($key);
        if (is_file($file)) {
            $payload = json_decode((string) file_get_contents($file), true);
            if (is_array($payload) && ($payload['expires_at'] ?? 0) > time()) {
                return $payload['value'];
            }
        }

        $value = $callback();
        self::put($key, $value, $seconds);

        return $value;
    }

    public static function put(string $key, mixed $value, int $seconds): void
    {
        $dir = BASE_PATH . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(self::path($key), json_encode([
            'expires_at' => time() + $seconds,
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function forget(string $key): void
    {
        $file = self::path($key);
        if (is_file($file)) {
            unlink($file);
        }
    }

    private static function path(string $key): string
    {
        return BASE_PATH . '/storage/cache/cache_' . sha1($key) . '.json';
    }
}
