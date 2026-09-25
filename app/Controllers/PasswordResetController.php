<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Core\Logger;
use App\Core\HttpException;
use App\Services\EmailService;

final class PasswordResetController
{
    private const TOKEN_TTL_MINUTES = 60;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function requestReset(array $data): void
    {
        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException('Please provide a valid email address.', 400);
        }

        // 1. Check if user exists
        $stmt = $this->pdo->prepare('SELECT id, username, email FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // ALWAYS return success to prevent email enumeration
        if (!$user) {
            Logger::info('PasswordResetController', 'Password reset requested for non-existent email', ['email' => $email]);
            return;
        }

        // 2. Generate secure token
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        // 3. Save token
        $insert = $this->pdo->prepare(
            "INSERT INTO password_reset_tokens (user_id, token_hash, created_at, expires_at) 
             VALUES (:user, :hash, NOW(), DATE_ADD(NOW(), INTERVAL :ttl MINUTE))"
        );
        $insert->execute([
            ':user' => $user['id'],
            ':hash' => $tokenHash,
            ':ttl'  => self::TOKEN_TTL_MINUTES
        ]);

        // 4. Send email
        $resetLink = rtrim(APP_URL, '/') . '/reset_password.html?token=' . urlencode($rawToken);
        
        $emailService = new EmailService();
        $sent = $emailService->sendPasswordReset((string) $user['email'], (string) $user['username'], $resetLink);

        if (!$sent) {
            // We just log it, we don't tell the user it failed to prevent enumeration/leakage
            Logger::error('PasswordResetController', 'Failed to send password reset email', ['user_id' => $user['id']]);
        }
        
        Logger::info('PasswordResetController', 'Password reset email triggered', ['user_id' => $user['id']]);
    }

    public function resetPassword(array $data): void
    {
        $rawToken = trim((string) ($data['token'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $confirm = (string) ($data['password_confirm'] ?? '');

        if ($rawToken === '') {
            throw new HttpException('Invalid or missing reset token.', 400);
        }
        if (strlen($password) < 8) {
            throw new HttpException('Password must be at least 8 characters long.', 400);
        }
        if ($password !== $confirm) {
            throw new HttpException('Passwords do not match.', 400);
        }

        $tokenHash = hash('sha256', $rawToken);

        $this->pdo->beginTransaction();
        try {
            // 1. Lock token row
            $stmt = $this->pdo->prepare(
                'SELECT * FROM password_reset_tokens WHERE token_hash = :hash FOR UPDATE'
            );
            $stmt->execute([':hash' => $tokenHash]);
            $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tokenRow) {
                throw new HttpException('Invalid reset token.', 400);
            }
            if ($tokenRow['used_at'] !== null) {
                throw new HttpException('This reset link has already been used.', 400);
            }
            if (strtotime((string) $tokenRow['expires_at']) < time()) {
                throw new HttpException('This reset link has expired.', 400);
            }

            // 2. Update user password and invalidate sessions
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateUser = $this->pdo->prepare(
                'UPDATE users SET password_hash = :hash, remember_token = NULL, remember_expires = NULL, updated_at = NOW() WHERE id = :id'
            );
            $updateUser->execute([':hash' => $hashedPassword, ':id' => $tokenRow['user_id']]);

            // 3. Mark token as used
            $updateToken = $this->pdo->prepare(
                'UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id'
            );
            $updateToken->execute([':id' => $tokenRow['id']]);

            $this->pdo->commit();

            Logger::info('PasswordResetController', 'Password successfully reset', ['user_id' => $tokenRow['user_id']]);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
