<?php
// ============================================================
//  KICKOFF — Auth Helpers
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('KICKOFFSESSID');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => SESSION_SECURE_COOKIE || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => SESSION_SAME_SITE,
    ]);
    session_start();

    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['_last_regen'])) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    } elseif (time() - $_SESSION['_last_regen'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }
}

function ensureRememberTokenColumn(): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        db()->query("ALTER TABLE users ADD COLUMN remember_token varchar(255) DEFAULT NULL, ADD COLUMN remember_expires timestamp NULL DEFAULT NULL");
    } catch (Throwable $e) {}
}

function tryRememberLogin(): void {
    if (isset($_SESSION['user_id']) || empty($_COOKIE['kickoff_remember_token'])) return;
    ensureRememberTokenColumn();
    [$selector, $validator] = array_pad(explode(':', $_COOKIE['kickoff_remember_token'], 2), 2, '');
    if (!$selector || !$validator) return;
    $stmt = db()->prepare("SELECT * FROM users WHERE remember_token IS NOT NULL AND remember_expires > NOW()");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $user) {
        $expected = hash('sha256', $selector . ':' . $validator);
        if (hash_equals($user['remember_token'], $expected) && $user['status'] === 'active') {
            setSession($user);
            return;
        }
    }
}

function issueRememberToken(array $user): void {
    ensureRememberTokenColumn();
    $selector = bin2hex(random_bytes(8));
    $validator = bin2hex(random_bytes(32));
    $hash = hash('sha256', $selector . ':' . $validator);
    db()->prepare("UPDATE users SET remember_token = ?, remember_expires = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?")
        ->execute([$hash, (int)$user['id']]);
    setcookie('kickoff_remember_token', $selector . ':' . $validator, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => !defined('DEBUG_MODE') || !DEBUG_MODE,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function clearRememberToken(): void {
    if (isset($_SESSION['user_id'])) {
        try { db()->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = ?")->execute([(int)$_SESSION['user_id']]); } catch (Throwable $e) {}
    }
    setcookie('kickoff_remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !defined('DEBUG_MODE') || !DEBUG_MODE,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

tryRememberLogin();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated. Please log in.']);
        exit();
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. Admin only.']);
        exit();
    }
}

function requireCompletedPlayerProfile(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'player') {
        return;
    }
    try {
        $stmt = db()->prepare('SELECT profile_setup_completed FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        if ((int) $stmt->fetchColumn() === 1) {
            return;
        }
    } catch (Throwable) {
        return;
    }
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Complete your player profile before continuing.',
        'code' => 'PROFILE_SETUP_REQUIRED',
        'redirect' => 'profile_setup.html',
    ]);
    exit();
}

function currentUserId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function currentUserRole(): ?string {
    return $_SESSION['role'] ?? null;
}

function setSession(array $user): void {
    // SECURITY FIX: Regenerate session ID on login to prevent session fixation
    session_regenerate_id(true);
    unset($_SESSION['_csrf_token'], $_SESSION['csrf_token']);
    $_SESSION['user_id']     = (int) $user['id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['status']      = $user['status'];
    $_SESSION['_last_regen'] = time();
    $_SESSION['_fingerprint'] = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    $_SESSION['_last_activity'] = time();
    $_SESSION['_last_regenerated'] = time();
}

function destroySession(): void {
    clearRememberToken();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── CSRF Token Helpers ────────────────────────────────────────
// Use these for any state-changing forms submitted via HTML forms.
// Fetch API calls are protected by SameSite=Strict cookie.
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}
