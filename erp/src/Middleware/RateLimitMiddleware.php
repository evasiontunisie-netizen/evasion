<?php
// ============================================================
// ERP PRO - Rate Limiter Middleware
// ============================================================

class RateLimitMiddleware {
    public static function check(string $key = null, int $max = null, int $window = null): void {
        $max    = $max    ?? RATE_LIMIT_MAX;
        $window = $window ?? RATE_LIMIT_WINDOW;
        $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key    = $key ?? "rate_$ip";

        if (!is_dir(CACHE_PATH)) mkdir(CACHE_PATH, 0755, true);
        $file = CACHE_PATH . '/' . md5($key) . '.rate';

        $data = ['count' => 0, 'reset_at' => time() + $window];
        if (file_exists($file)) {
            $saved = json_decode(file_get_contents($file), true);
            if ($saved && $saved['reset_at'] > time()) {
                $data = $saved;
            }
        }

        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);

        header('X-RateLimit-Limit: '     . $max);
        header('X-RateLimit-Remaining: ' . max(0, $max - $data['count']));
        header('X-RateLimit-Reset: '     . $data['reset_at']);

        if ($data['count'] > $max) {
            http_response_code(429);
            die(json_encode(['success' => false, 'message' => 'Trop de requêtes. Veuillez réessayer.']));
        }
    }
}
