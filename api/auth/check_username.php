<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireGet();
$username = sanitize($_GET['username'] ?? '');

if (!isValidUsername($username)) {
    jsonSuccess([
        'available' => false,
        'invalid' => true,
        'message' => 'Invalid format'
    ]);
}
$stmt = db()->prepare('SELECT id FROM users WHERE username=:u LIMIT 1');
$stmt->execute([':u' => $username]);
$taken = (bool) $stmt->fetch();
jsonSuccess(['available' => !$taken, 'message' => $taken ? 'Username taken' : 'Username available']);
