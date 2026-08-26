<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class Auth
{
    private const REMEMBER_COOKIE = 'kickoff_remember';
    private static ?array $currentUser = null;
    private static bool $loaded = false;

    public static function boot(): void
    {
        if (!isset($_SESSION['user_id']) && !empty($_COOKIE[self::REMEMBER_COOKIE])) {
            self::restoreRememberedLogin();
        }
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$currentUser;
        }
        self::$loaded = true;

        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id < 1) {
            return self::$currentUser = null;
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT id, username, email, first_name, last_name, country, preferred_game,
                        profile_setup_completed, theme_preference, timezone,
                        whatsapp_country_code, whatsapp_number, whatsapp_verified_at, whatsapp_contact_opt_in,
                        role, status, points, wins, losses, draws, total_matches, championships,
                        avatar_url, bio, created_at, last_login
                 FROM users WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            // The championships column is introduced by the migration; retain a clean pre-migration error path.
            $stmt = Database::connection()->prepare(
                'SELECT id, username, email, first_name, last_name, country, preferred_game, role, status,
                        points, wins, losses, draws, total_matches, avatar_url, bio, created_at, last_login
                 FROM users WHERE id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $user['championships'] = 0;
                $user['profile_setup_completed'] = 1;
                $user['theme_preference'] = 'esport';
            }
        }

        if (!$user || $user['status'] === 'banned') {
            self::logout(false);
            return self::$currentUser = null;
        }

        $user['id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['status'] = $user['status'];
        return self::$currentUser = $user;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            throw new HttpException('Not authenticated. Please log in.', 401);
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'admin') {
            throw new HttpException('Access denied. Administrator only.', 403);
        }
        return $user;
    }

    public static function requireCompletedPlayerProfile(): array
    {
        $user = self::requireUser();
        if (($user['role'] ?? '') === 'player' && (int) ($user['profile_setup_completed'] ?? 0) !== 1) {
            throw new HttpException('Complete your player profile before continuing.', 403, [
                'code' => 'PROFILE_SETUP_REQUIRED',
                'redirect' => 'profile_setup.html',
            ]);
        }
        return $user;
    }

    public static function login(array $user, bool $remember = false): void
    {
        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['status'] = (string) $user['status'];
        self::$currentUser = $user;
        self::$loaded = true;
        Csrf::rotate();

        if ($remember) {
            self::issueRememberToken((int) $user['id']);
        }
    }

    public static function logout(bool $clearDatabaseToken = true): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($clearDatabaseToken && $userId > 0) {
            try {
                Database::connection()->prepare(
                    'UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = :id'
                )->execute([':id' => $userId]);
            } catch (Throwable) {
            }
        }

        self::forgetRememberCookie();
        Session::destroy();
        self::$currentUser = null;
        self::$loaded = true;
    }

    private static function issueRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $hash = hash_hmac('sha256', $validator, APP_KEY);
        $stored = $selector . ':' . $hash;

        Database::connection()->prepare(
            'UPDATE users SET remember_token = :token, remember_expires = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = :id'
        )->execute([':token' => $stored, ':id' => $userId]);

        setcookie(self::REMEMBER_COOKIE, $selector . ':' . $validator, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => COOKIE_PATH,
            'secure' => SESSION_SECURE_COOKIE || Session::isHttps(),
            'httponly' => true,
            'samesite' => SESSION_SAME_SITE,
        ]);
    }

    private static function restoreRememberedLogin(): void
    {
        [$selector, $validator] = array_pad(explode(':', (string) $_COOKIE[self::REMEMBER_COOKIE], 2), 2, '');
        if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            self::forgetRememberCookie();
            return;
        }

        try {
            $stmt = Database::connection()->prepare(
                "SELECT * FROM users
                 WHERE remember_token LIKE :selector
                   AND remember_expires > NOW()
                   AND status != 'banned'
                 LIMIT 1"
            );
            $stmt->execute([':selector' => $selector . ':%']);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                self::forgetRememberCookie();
                return;
            }

            [, $expected] = array_pad(explode(':', (string) $user['remember_token'], 2), 2, '');
            $actual = hash_hmac('sha256', $validator, APP_KEY);
            if (!hash_equals($expected, $actual)) {
                self::forgetRememberCookie();
                return;
            }

            self::login($user, true);
        } catch (Throwable $exception) {
            error_log('[KICKOFF remember login] ' . $exception->getMessage());
        }
    }

    private static function forgetRememberCookie(): void
    {
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => COOKIE_PATH,
            'secure' => SESSION_SECURE_COOKIE || Session::isHttps(),
            'httponly' => true,
            'samesite' => SESSION_SAME_SITE,
        ]);
        unset($_COOKIE[self::REMEMBER_COOKIE]);
    }

    private function __construct()
    {
    }
}
