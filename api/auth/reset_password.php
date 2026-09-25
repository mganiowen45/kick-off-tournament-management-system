<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../app/Core/RateLimiter.php';

use App\Core\RateLimiter;
use App\Controllers\PasswordResetController;

requirePost();

// Rate limit: 5 requests per 15 minutes per IP
$limiter = new RateLimiter(db());
if (!$limiter->check('reset_password_ip:' . $_SERVER['REMOTE_ADDR'], 5, 900)) {
    jsonError('Too many password reset attempts. Please try again later.', 429);
}

try {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $controller = new PasswordResetController(db());
    $controller->resetPassword($data);
    
    jsonSuccess(['message' => 'Your password has been successfully reset. You may now log in.']);
} catch (\App\Core\HttpException $e) {
    jsonError($e->getMessage(), $e->getCode());
} catch (\Throwable $e) {
    \App\Core\Logger::error('API', 'Reset password error: ' . $e->getMessage());
    jsonError('An unexpected error occurred.', 500);
}
