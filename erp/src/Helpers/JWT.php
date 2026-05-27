<?php
// ============================================================
// ERP PRO - JWT Helper
// ============================================================

class JWT {
    public static function encode(array $payload, int $expires = null): string {
        $expires = $expires ?? (time() + JWT_EXPIRES);
        $header  = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body    = self::base64url(json_encode([...$payload, 'iat' => time(), 'exp' => $expires]));
        $sig     = self::base64url(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
        return "$header.$body.$sig";
    }

    public static function decode(string $token): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new \Exception('Invalid token structure');

        [$header, $payload, $signature] = $parts;
        $expected = self::base64url(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

        if (!hash_equals($expected, $signature)) throw new \Exception('Invalid signature');

        $data = json_decode(self::base64urlDecode($payload), true);
        if (!$data) throw new \Exception('Invalid payload');
        if (isset($data['exp']) && $data['exp'] < time()) throw new \Exception('Token expired');

        return $data;
    }

    public static function createRefreshToken(): string {
        return bin2hex(random_bytes(64));
    }

    private static function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
