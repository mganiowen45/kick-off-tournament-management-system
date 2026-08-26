<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requirePost();
$data = getPostData();
requireFields($data, ['identifier','password']);

$identifier = sanitize($data['identifier']);
$password   = $data['password'];

// --- RATE LIMITING (basic: limit per IP, 10 attempts per 15 min) ---
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$key = 'login_fail_' . md5($ip);

// Use PHP session-based rate limiting (replace with Redis/DB in production)
if (session_status() === PHP_SESSION_NONE) session_start();
$attempts = $_SESSION[$key] ?? ['count' => 0, 'first' => time()];
if (time() - $attempts['first'] > 900) {
    $attempts = ['count' => 0, 'first' => time()]; // reset window
}
if ($attempts['count'] >= 10) {
    http_response_code(429);
    jsonError('Too many login attempts. Please try again in 15 minutes.');
}

// --- LOOKUP USER ---
$stmt = db()->prepare("SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1");
$stmt->execute([':username' => $identifier, ':email' => $identifier]);
$user = $stmt->fetch();

// SECURITY: Always verify even if user not found to prevent timing attacks
$dummyHash = '$2y$12$invalidhashfortimingprotection000000000000000000000000';
$hash = $user['password_hash'] ?? $dummyHash;

if (!password_verify($password, $hash) || !$user) {
    $attempts['count']++;
    $_SESSION[$key] = $attempts;
    jsonError('Incorrect username or password.', 401);
}

// Reset rate limit on success
unset($_SESSION[$key]);

if ($user['status'] === 'banned') {
    jsonError('Your account has been suspended. Contact support.', 403);
}

// Update last login
db()->prepare('UPDATE users SET last_login=NOW() WHERE id=:id')->execute([':id' => $user['id']]);

setSession($user);
if (!empty($data['remember'])) {
    issueRememberToken($user);
}
function safeReturnToValue(string $value): string {
    $value = trim($value);
    if ($value === '' || strlen($value) > 300) return '';
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) || str_contains($value, '//') || str_contains($value, '\\')) return '';
    if (!preg_match('/^[A-Za-z0-9_.-]+\.html(?:\?[A-Za-z0-9%_.~=&+-]*)?$/', $value)) return '';
    if (str_starts_with($value, 'admin_')) return '';
    return $value;
}

$returnTo = safeReturnToValue((string) ($data['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = '';
}
$profileComplete = (int) ($user['profile_setup_completed'] ?? 0) === 1;
$redirect = $user['role'] === 'admin'
    ? 'admin_dashboard.html'
    : ($profileComplete ? ($returnTo ?: 'dashboard.html') : 'profile_setup.html' . ($returnTo !== '' ? '?return_to=' . rawurlencode($returnTo) : ''));
jsonSuccess([
    'user_id'  => $user['id'],
    'username' => $user['username'],
    'role'     => $user['role'],
    'redirect' => $redirect
], 'Login successful');
