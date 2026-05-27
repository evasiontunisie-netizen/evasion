<?php
// ============================================================
// ERP PRO - Auth Middleware
// ============================================================

class AuthMiddleware {
    public static array $user = [];

    public static function authenticate(): array {
        $token = self::extractToken();
        if (!$token) Response::unauthorized('Token manquant');

        try {
            $payload = JWT::decode($token);
            $user = Database::fetch(
                "SELECT u.*, r.slug as role_slug, r.name as role_name FROM users u 
                 JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1",
                [$payload['sub']]
            );
            if (!$user) Response::unauthorized('Utilisateur introuvable');

            self::$user = $user;
            return $user;
        } catch (\Exception $e) {
            Response::unauthorized($e->getMessage());
        }
    }

    public static function can(string $permission): bool {
        if (empty(self::$user)) return false;
        if (self::$user['role_slug'] === 'super_admin') return true;

        $perm = Database::fetch(
            "SELECT 1 FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.slug = ?",
            [self::$user['role_id'], $permission]
        );
        return (bool)$perm;
    }

    public static function requirePermission(string $permission): void {
        if (!self::can($permission)) Response::forbidden("Permission requise: $permission");
    }

    public static function requireRole(array $roles): void {
        if (!in_array(self::$user['role_slug'] ?? '', $roles)) {
            Response::forbidden('Rôle insuffisant');
        }
    }

    private static function extractToken(): ?string {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return $m[1];
        return $_GET['token'] ?? null;
    }
}
