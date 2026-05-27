<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Config;

final class Jwt
{
    public static function issue(array $claims, int $ttl = 28800): string
    {
        $now = time();
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = $claims + ['iat' => $now, 'exp' => $now + $ttl, 'iss' => Config::get('APP_URL', 'http://localhost')];
        $segments = [self::base64Url(json_encode($header)), self::base64Url(json_encode($payload))];
        $segments[] = self::sign(implode('.', $segments));

        return implode('.', $segments);
    }

    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        if (!hash_equals(self::sign($header . '.' . $payload), $signature)) {
            return null;
        }

        $claims = json_decode((string) self::base64UrlDecode($payload), true);
        if (!is_array($claims) || ($claims['exp'] ?? 0) < time()) {
            return null;
        }

        return $claims;
    }

    private static function sign(string $payload): string
    {
        return self::base64Url(hash_hmac('sha256', $payload, (string) Config::get('JWT_SECRET', 'change-me'), true));
    }

    private static function base64Url(string|false $value): string
    {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4));
    }
}
