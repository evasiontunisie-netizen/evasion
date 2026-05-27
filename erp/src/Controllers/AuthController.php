<?php
// ============================================================
// ERP PRO - Authentication Controller
// ============================================================

class AuthController {

    public function login(): void {
        RateLimitMiddleware::check('login_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 60);

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $v = Validator::make($input, [
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);
        if ($v->fails()) Response::error('Données invalides', 422, $v->errors());

        $email    = $v->get('email');
        $password = $v->get('password');

        $user = Database::fetch(
            "SELECT u.*, r.slug as role_slug, r.name as role_name FROM users u 
             JOIN roles r ON r.id = u.role_id WHERE u.email = ?",
            [$email]
        );

        if (!$user) Response::error('Email ou mot de passe incorrect', 401);

        // Check lockout
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            Response::error("Compte bloqué. Réessayez dans $mins minutes.", 423);
        }

        if (!password_verify($password, $user['password'])) {
            $attempts = $user['login_attempts'] + 1;
            $lockUntil = null;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
            }
            Database::update('users', [
                'login_attempts' => $attempts,
                'locked_until'   => $lockUntil,
            ], 'id = ?', [$user['id']]);
            Response::error('Email ou mot de passe incorrect', 401);
        }

        if (!$user['is_active']) Response::error('Compte désactivé', 403);

        // Reset attempts
        Database::update('users', [
            'login_attempts' => 0,
            'locked_until'   => null,
            'last_login'     => date('Y-m-d H:i:s'),
        ], 'id = ?', [$user['id']]);

        $accessToken  = JWT::encode(['sub' => $user['id'], 'role' => $user['role_slug']]);
        $refreshToken = JWT::createRefreshToken();

        Database::insert('refresh_tokens', [
            'user_id'    => $user['id'],
            'token'      => hash('sha256', $refreshToken),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRES),
        ]);

        Logger::activity($user['id'], 'login', 'auth', 'Connexion réussie');

        Response::success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => JWT_EXPIRES,
            'user'          => self::userPayload($user),
        ], 'Connexion réussie');
    }

    public function refresh(): void {
        $input        = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $refreshToken = $input['refresh_token'] ?? '';
        if (!$refreshToken) Response::error('Refresh token manquant', 400);

        $hashed = hash('sha256', $refreshToken);
        $token  = Database::fetch(
            "SELECT rt.*, u.is_active FROM refresh_tokens rt JOIN users u ON u.id = rt.user_id
             WHERE rt.token = ? AND rt.expires_at > NOW()",
            [$hashed]
        );
        if (!$token || !$token['is_active']) Response::unauthorized('Token invalide ou expiré');

        $user = Database::fetch(
            "SELECT u.*, r.slug as role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?",
            [$token['user_id']]
        );

        $newAccess  = JWT::encode(['sub' => $user['id'], 'role' => $user['role_slug']]);
        $newRefresh = JWT::createRefreshToken();

        Database::query("DELETE FROM refresh_tokens WHERE token = ?", [$hashed]);
        Database::insert('refresh_tokens', [
            'user_id'    => $user['id'],
            'token'      => hash('sha256', $newRefresh),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRES),
        ]);

        Response::success([
            'access_token'  => $newAccess,
            'refresh_token' => $newRefresh,
            'expires_in'    => JWT_EXPIRES,
        ]);
    }

    public function logout(): void {
        $user  = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!empty($input['refresh_token'])) {
            $hashed = hash('sha256', $input['refresh_token']);
            Database::query("DELETE FROM refresh_tokens WHERE token = ?", [$hashed]);
        } else {
            Database::query("DELETE FROM refresh_tokens WHERE user_id = ?", [$user['id']]);
        }
        Logger::activity($user['id'], 'logout', 'auth', 'Déconnexion');
        Response::success(null, 'Déconnecté avec succès');
    }

    public function me(): void {
        $user = AuthMiddleware::authenticate();
        $perms = Database::fetchAll(
            "SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?",
            [$user['role_id']]
        );
        Response::success([
            'user'        => self::userPayload($user),
            'permissions' => array_column($perms, 'slug'),
        ]);
    }

    public function forgotPassword(): void {
        RateLimitMiddleware::check('forgot_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 300);
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) Response::error('Email invalide', 422);

        $user = Database::fetch("SELECT id FROM users WHERE email = ? AND is_active = 1", [$email]);
        // Always return success (security)
        if ($user) {
            $token = bin2hex(random_bytes(32));
            Database::update('users', [
                'password_reset_token'   => hash('sha256', $token),
                'password_reset_expires' => date('Y-m-d H:i:s', time() + 3600),
            ], 'id = ?', [$user['id']]);
            // TODO: Send email with $token
        }
        Response::success(null, 'Si cet email existe, un lien de réinitialisation a été envoyé.');
    }

    public function resetPassword(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $token    = $input['token']    ?? '';
        $password = $input['password'] ?? '';

        if (!$token || strlen($password) < 8) Response::error('Données invalides', 422);

        $user = Database::fetch(
            "SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW()",
            [hash('sha256', $token)]
        );
        if (!$user) Response::error('Token invalide ou expiré', 400);

        Database::update('users', [
            'password'             => password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
            'password_reset_token' => null,
            'password_reset_expires'=> null,
            'login_attempts'       => 0,
            'locked_until'         => null,
        ], 'id = ?', [$user['id']]);

        Response::success(null, 'Mot de passe réinitialisé avec succès');
    }

    public function changePassword(): void {
        $user  = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $current = $input['current_password'] ?? '';
        $new     = $input['new_password'] ?? '';
        if (!$current || strlen($new) < 8) Response::error('Données invalides', 422);

        $dbUser = Database::fetch("SELECT password FROM users WHERE id = ?", [$user['id']]);
        if (!password_verify($current, $dbUser['password'])) Response::error('Mot de passe actuel incorrect', 401);

        Database::update('users', [
            'password' => password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
        ], 'id = ?', [$user['id']]);

        Logger::activity($user['id'], 'change_password', 'auth', 'Changement de mot de passe');
        Response::success(null, 'Mot de passe modifié');
    }

    private static function userPayload(array $user): array {
        return [
            'id'         => $user['id'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'email'      => $user['email'],
            'phone'      => $user['phone'],
            'avatar'     => $user['avatar'],
            'role'       => $user['role_slug'],
            'role_name'  => $user['role_name'],
            'language'   => $user['language'],
            'theme'      => $user['theme'],
            'last_login' => $user['last_login'],
        ];
    }
}
