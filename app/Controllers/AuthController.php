<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth\Jwt;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Security;
use App\Core\Validator;

final class AuthController extends Controller
{
    public function login(Request $request): void
    {
        $errors = Validator::validate($request->body, ['email' => ['required', 'email'], 'password' => ['required']]);
        if ($errors !== []) {
            $this->error('Validation failed', 422, $errors);
            return;
        }

        $statement = Database::pdo()->prepare('SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = :email AND u.status = "active" LIMIT 1');
        $statement->execute(['email' => $request->input('email')]);
        $user = $statement->fetch();
        if (!$user || !password_verify((string) $request->input('password'), (string) $user['password_hash'])) {
            Logger::activity(null, 'login_failed', ['email' => $request->input('email'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli']);
            $this->error('Invalid credentials', 401);
            return;
        }

        if ((int) ($user['two_factor_enabled'] ?? 0) === 1 && !Security::verifyTotp((string) $user['two_factor_secret'], (string) $request->input('otp', ''))) {
            $this->ok(['requires_2fa' => true], 202);
            return;
        }

        $token = Jwt::issue(['sub' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role_slug']]);
        Database::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
        Logger::activity((int) $user['id'], 'login_success');

        $this->ok(['token' => $token, 'user' => ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role_slug']]]);
    }

    public function registerAdmin(Request $request): void
    {
        $errors = Validator::validate($request->body, [
            'name' => ['required', 'min:2'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:10'],
        ]);
        if ($errors !== []) {
            $this->error('Validation failed', 422, $errors);
            return;
        }

        $count = (int) Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            $this->error('Initial admin already exists', 403);
            return;
        }

        $roleId = (int) Database::pdo()->query('SELECT id FROM roles WHERE slug = "super-admin" LIMIT 1')->fetchColumn();
        $statement = Database::pdo()->prepare('INSERT INTO users (role_id, name, email, password_hash, status) VALUES (:role_id, :name, :email, :password_hash, "active")');
        $statement->execute([
            'role_id' => $roleId,
            'name' => Security::cleanString((string) $request->input('name')),
            'email' => strtolower((string) $request->input('email')),
            'password_hash' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
        ]);

        $this->ok(['message' => 'Super admin created'], 201);
    }

    public function forgotPassword(Request $request): void
    {
        $statement = Database::pdo()->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $request->input('email')]);
        $user = $statement->fetch();
        if ($user) {
            $token = Security::randomToken();
            Database::pdo()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
                ->execute(['user_id' => $user['id'], 'token_hash' => hash('sha256', $token)]);
            Logger::activity((int) $user['id'], 'password_reset_requested');
        }

        $this->ok(['message' => 'If the email exists, reset instructions were generated']);
    }

    public function resetPassword(Request $request): void
    {
        $errors = Validator::validate($request->body, ['token' => ['required'], 'password' => ['required', 'min:10']]);
        if ($errors !== []) {
            $this->error('Validation failed', 422, $errors);
            return;
        }

        $statement = Database::pdo()->prepare('SELECT * FROM password_resets WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
        $statement->execute(['hash' => hash('sha256', (string) $request->input('token'))]);
        $reset = $statement->fetch();
        if (!$reset) {
            $this->error('Invalid reset token', 422);
            return;
        }

        Database::pdo()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute([
            'hash' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
            'id' => $reset['user_id'],
        ]);
        Database::pdo()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')->execute(['id' => $reset['id']]);
        $this->ok(['message' => 'Password updated']);
    }

    public function me(Request $request): void
    {
        $this->ok(['user' => $request->user]);
    }

    public function twoFactorSetup(Request $request): void
    {
        $secret = base64_encode(random_bytes(20));
        $userId = (int) ($request->user['sub'] ?? 0);
        Database::pdo()->prepare('UPDATE users SET two_factor_secret = :secret, two_factor_enabled = 0 WHERE id = :id')
            ->execute(['secret' => $secret, 'id' => $userId]);

        $email = rawurlencode((string) ($request->user['email'] ?? 'user'));
        $issuer = rawurlencode('Evasion ERP');
        $otpauth = "otpauth://totp/Evasion%20ERP:{$email}?secret=" . rawurlencode($secret) . "&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
        Logger::activity($userId, '2fa.setup_requested');

        $this->ok([
            'secret' => $secret,
            'otpauth_url' => $otpauth,
            'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($otpauth),
        ]);
    }

    public function twoFactorConfirm(Request $request): void
    {
        $userId = (int) ($request->user['sub'] ?? 0);
        $statement = Database::pdo()->prepare('SELECT two_factor_secret FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $secret = (string) $statement->fetchColumn();
        if ($secret === '' || !Security::verifyTotp($secret, (string) $request->input('otp'))) {
            $this->error('Invalid 2FA code', 422);
            return;
        }

        Database::pdo()->prepare('UPDATE users SET two_factor_enabled = 1 WHERE id = :id')->execute(['id' => $userId]);
        Logger::activity($userId, '2fa.enabled');
        $this->ok(['enabled' => true]);
    }

    public function twoFactorDisable(Request $request): void
    {
        $userId = (int) ($request->user['sub'] ?? 0);
        Database::pdo()->prepare('UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = :id')->execute(['id' => $userId]);
        Logger::activity($userId, '2fa.disabled');
        $this->ok(['enabled' => false]);
    }
}
