<?php
// ============================================================
//  KICKOFF - Main Configuration
// ============================================================

$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function kickoff_env(string $key, mixed $default = null): mixed {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

function kickoff_bool(string $key, bool $default = false): bool {
    $value = kickoff_env($key, $default ? 'true' : 'false');
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

// --- Environment ---
define('APP_NAME', (string) kickoff_env('APP_NAME', 'KICKOFF'));
define('APP_ENV', (string) kickoff_env('APP_ENV', 'local'));
define('APP_DEBUG', kickoff_bool('APP_DEBUG', APP_ENV !== 'production'));
define('APP_URL', rtrim((string) kickoff_env('APP_URL', 'http://localhost/kickoff5'), '/'));
define('APP_KEY', (string) kickoff_env('APP_KEY', 'local-dev-change-me-32-characters-min'));
define('DEBUG_MODE', APP_DEBUG);

// --- Database ---
define('DB_HOST', (string) kickoff_env('DB_HOST', 'localhost'));
define('DB_PORT', (int) kickoff_env('DB_PORT', 3306));
define('DB_NAME', (string) kickoff_env('DB_NAME', 'kick_off'));
define('DB_USER', (string) kickoff_env('DB_USER', 'root'));
define('DB_PASS', (string) kickoff_env('DB_PASSWORD', kickoff_env('DB_PASS', '')));
define('DB_CHARSET', (string) kickoff_env('DB_CHARSET', 'utf8mb4'));

// --- Site ---
define('SITE_URL', APP_URL);
define('SITE_NAME', APP_NAME);

// --- Session ---
define('SESSION_LIFETIME', (int) kickoff_env('SESSION_LIFETIME', 3600 * 24 * 7));
define('SESSION_IDLE_TIMEOUT', (int) kickoff_env('SESSION_IDLE_TIMEOUT', 3600 * 6));
define('SESSION_REGENERATE_INTERVAL', (int) kickoff_env('SESSION_REGENERATE_INTERVAL', 900));
define('SESSION_SECURE_COOKIE', kickoff_bool('SESSION_SECURE_COOKIE', APP_ENV === 'production'));
define('SESSION_SAME_SITE', (string) kickoff_env('SESSION_SAME_SITE', 'Lax'));
define('COOKIE_PATH', (string) kickoff_env('COOKIE_PATH', '/'));

// --- Integrations ---
define('MAIL_HOST', (string) kickoff_env('MAIL_HOST', ''));
define('MAIL_PORT', (int) kickoff_env('MAIL_PORT', 587));
define('MAIL_USERNAME', (string) kickoff_env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', (string) kickoff_env('MAIL_PASSWORD', ''));
define('PAYMENT_MODE', (string) kickoff_env('PAYMENT_MODE', 'disabled'));
define('PAYMENT_PROVIDER', (string) kickoff_env('PAYMENT_PROVIDER', 'clickpesa'));
define('PAYMENT_API_KEY', (string) kickoff_env('PAYMENT_API_KEY', kickoff_env('CLICKPESA_API_KEY', '')));
define('PAYMENT_API_SECRET', (string) kickoff_env('PAYMENT_API_SECRET', kickoff_env('CLICKPESA_API_SECRET', '')));
define('PAYMENT_WEBHOOK_SECRET', (string) kickoff_env('PAYMENT_WEBHOOK_SECRET', kickoff_env('CLICKPESA_WEBHOOK_SECRET', '')));
define('CLICKPESA_API_KEY', PAYMENT_API_KEY);
define('CLICKPESA_WEBHOOK_SECRET', PAYMENT_WEBHOOK_SECRET);
define('DEFAULT_CURRENCY', (string) kickoff_env('DEFAULT_CURRENCY', 'TZS'));
define('PLATFORM_FEE_PERCENT', (float) kickoff_env('PLATFORM_FEE_PERCENT', 10));
define('RESERVATION_EXPIRY_MINUTES', (int) kickoff_env('RESERVATION_EXPIRY_MINUTES', 30));
define('AUTO_START_HOURS_AFTER_FILL', (int) kickoff_env('AUTO_START_HOURS_AFTER_FILL', 12));
define('CHECK_IN_WINDOW_MINUTES', (int) kickoff_env('CHECK_IN_WINDOW_MINUTES', 60));
define('CHECK_IN_CLOSE_MINUTES_BEFORE_AUTO_START', (int) kickoff_env('CHECK_IN_CLOSE_MINUTES_BEFORE_AUTO_START', 30));

// --- File Uploads ---
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('DEFAULT_AVATAR', (string) kickoff_env('DEFAULT_AVATAR', 'gamer-neon.svg'));
define('AVATAR_URL', SITE_URL . '/assets/avatars/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_EXTS', ['jpg', 'jpeg', 'png', 'webp']);

// --- Points System ---
define('POINTS_WIN', 100);
define('POINTS_DRAW', 40);
define('POINTS_LOSS', 10);

// --- Pagination ---
define('DEFAULT_PAGE_LIMIT', 20);

// --- Timezone ---
date_default_timezone_set((string) kickoff_env('APP_TIMEZONE', 'Africa/Dar_es_Salaam'));

// --- Error Reporting ---
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

$allowedOrigins = array_unique(['http://localhost', 'http://localhost/kickoff5', SITE_URL]);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (PHP_SAPI !== 'cli' && $origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
if (PHP_SAPI !== 'cli') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    if (str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}
