<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = SESSION_SECURE_COOKIE || self::isHttps();
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
        if (PHP_SAPI === 'cli') {
            $cliSessionPath = dirname(__DIR__, 2) . '/storage/cache';
            if (is_dir($cliSessionPath) && is_writable($cliSessionPath)) {
                ini_set('session.save_path', $cliSessionPath);
            }
        }

        session_name('KICKOFFSESSID');
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => COOKIE_PATH,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => SESSION_SAME_SITE,
        ]);
        if (!@session_start()) {
            return;
        }

        $now = time();
        $fingerprint = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
        if (isset($_SESSION['_fingerprint']) && !hash_equals((string) $_SESSION['_fingerprint'], $fingerprint)) {
            self::destroy();
            if (!@session_start()) {
                return;
            }
        }
        $_SESSION['_fingerprint'] = $fingerprint;

        if (isset($_SESSION['_last_activity']) && $now - (int) $_SESSION['_last_activity'] > SESSION_IDLE_TIMEOUT) {
            self::destroy();
            if (!@session_start()) {
                return;
            }
            $_SESSION['_fingerprint'] = $fingerprint;
        }
        $_SESSION['_last_activity'] = $now;

        if (!isset($_SESSION['_last_regenerated']) || $now - (int) $_SESSION['_last_regenerated'] > SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['_last_regenerated'] = $now;
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_last_regenerated'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    private function __construct()
    {
    }
}
