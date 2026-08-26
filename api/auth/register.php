<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requirePost();
$data = getPostData();
requireFields($data, ['username','email','password','country']);

$username = sanitize($data['username']);
$email    = sanitize($data['email']);
$password = $data['password'];
$country  = sanitize($data['country']);
$game     = sanitize($data['preferred_game'] ?? 'eFootball');
$fname    = sanitize($data['first_name'] ?? '');
$lname    = sanitize($data['last_name']  ?? '');

if (!isValidUsername($username)) jsonError('Username must be 3-30 characters: letters, numbers, underscores only.');
if (!isValidEmail($email))       jsonError('Invalid email address.');
if (strlen($password) < 8)       jsonError('Password must be at least 8 characters.');

// Check uniqueness
$chk = db()->prepare('SELECT id FROM users WHERE username=:u OR email=:e LIMIT 1');
$chk->execute([':u' => $username, ':e' => $email]);
if ($chk->fetch()) jsonError('Username or email already taken.');

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$ins  = db()->prepare("
    INSERT INTO users (username,email,password_hash,first_name,last_name,country,preferred_game)
    VALUES (:u,:e,:h,:f,:l,:c,:g)
");
$ins->execute([':u'=>$username,':e'=>$email,':h'=>$hash,':f'=>$fname,':l'=>$lname,':c'=>$country,':g'=>$game]);
$id = (int) db()->lastInsertId();

$user = db()->prepare('SELECT id,username,role,status FROM users WHERE id=:id');
$user->execute([':id' => $id]);
setSession($user->fetch());

function safeReturnToValue(string $value): string {
    $value = trim($value);
    if ($value === '' || strlen($value) > 300) return '';
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) || str_contains($value, '//') || str_contains($value, '\\')) return '';
    if (!preg_match('/^[A-Za-z0-9_.-]+\.html(?:\?[A-Za-z0-9%_.~=&+-]*)?$/', $value)) return '';
    if (str_starts_with($value, 'admin_')) return '';
    return $value;
}

$returnTo = safeReturnToValue((string) ($data['return_to'] ?? ''));
$redirect = 'profile_setup.html' . ($returnTo !== '' ? '?return_to=' . rawurlencode($returnTo) : '');
jsonSuccess(['user_id' => $id, 'username' => $username, 'redirect' => $redirect], 'Account created successfully!');
