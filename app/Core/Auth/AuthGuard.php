<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class AuthGuard
{
    public static function api(array $permissions = []): callable
    {
        return static function (Request $request, callable $next) use ($permissions): void {
            $token = $request->bearerToken();
            $claims = $token ? Jwt::verify($token) : null;
            if ($claims === null) {
                Response::json(['success' => false, 'error' => 'Unauthenticated'], 401);
                return;
            }

            $request->user = $claims;
            if ($permissions !== [] && !self::can((int) $claims['sub'], $permissions)) {
                Response::json(['success' => false, 'error' => 'Forbidden'], 403);
                return;
            }

            $next($request);
        };
    }

    public static function can(int $userId, array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }

        $granted = self::permissions($userId);

        return in_array('*', $granted, true) || count(array_intersect($permissions, $granted)) > 0;
    }

    public static function permissions(int $userId): array
    {
        $sql = "SELECT p.slug
                FROM users u
                JOIN roles r ON r.id = u.role_id
                JOIN role_permissions rp ON rp.role_id = r.id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE u.id = :id";
        $statement = Database::pdo()->prepare($sql);
        $statement->execute(['id' => $userId]);

        return array_values(array_unique(array_column($statement->fetchAll(), 'slug')));
    }
}
