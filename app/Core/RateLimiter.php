<?php

declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    public static function check(string $key, int $maxAttempts = 80, int $decaySeconds = 60): bool
    {
        $dir = BASE_PATH . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file = $dir . '/rate_' . sha1($key) . '.json';
        $now = time();
        $state = ['reset' => $now + $decaySeconds, 'attempts' => 0];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded) && ($decoded['reset'] ?? 0) > $now) {
                $state = $decoded;
            }
        }

        if (($state['reset'] ?? 0) <= $now) {
            $state = ['reset' => $now + $decaySeconds, 'attempts' => 0];
        }

        $state['attempts']++;
        file_put_contents($file, json_encode($state));

        return $state['attempts'] <= $maxAttempts;
    }
}
