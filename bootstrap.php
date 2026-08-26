<?php
declare(strict_types=1);

if (defined('KICKOFF_BOOTSTRAPPED')) {
    return;
}
define('KICKOFF_BOOTSTRAPPED', true);

require_once __DIR__ . '/config/config.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = __DIR__ . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use App\Core\Auth;
use App\Core\Session;

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

Session::start();
Auth::boot();

