<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Support\AvatarCatalog;
use PDO;
use PDOException;

final class AuthService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function register(array $data): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $country = trim((string) ($data['country'] ?? ''));
        $game = trim((string) ($data['preferred_game'] ?? 'eFootball'));

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            throw new HttpException('Username must be 3-30 characters using letters, numbers, or underscores.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            throw new HttpException('Enter a valid email address.', 422);
        }
        if (strlen($password) < 8 || strlen($password) > 200) {
            throw new HttpException('Password must be between 8 and 200 characters.', 422);
        }
        if ($firstName === '' || strlen($firstName) > 50 || $lastName === '' || strlen($lastName) > 50) {
            throw new HttpException('First name and last name are required.', 422);
        }
        if ($country === '' || strlen($country) > 60 || $game === '' || strlen($game) > 80) {
            throw new HttpException('Country is required.', 422);
        }
        if (empty($data['terms'])) {
            throw new HttpException('You must agree to the Terms of Service.', 422);
        }

        $duplicate = $this->pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
        $duplicate->execute([':username' => $username, ':email' => $email]);
        if ($duplicate->fetchColumn()) {
            throw new HttpException('Username or email is already registered.', 409);
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users
                    (username, email, password_hash, first_name, last_name, country, preferred_game, avatar_url)
                 VALUES
                    (:username, :email, :password_hash, :first_name, :last_name, :country, :game, :avatar)'
            );
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':country' => $country,
                ':game' => $game,
                ':avatar' => DEFAULT_AVATAR,
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new HttpException('Username or email is already registered.', 409);
            }
            throw $exception;
        }

        $user = $this->findById((int) $this->pdo->lastInsertId());
        Auth::login($user);
        return AvatarCatalog::decorate($user);
    }

    public function login(array $data, string $ip): array
    {
        $identifier = trim((string) ($data['identifier'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($identifier === '' || $password === '') {
            throw new HttpException('Username/email and password are required.', 422);
        }

        $limiter = new \App\Core\RateLimiter($this->pdo);
        $attemptKey = 'login|' . $ip . '|' . strtolower($identifier);
        $limiter->limit($attemptKey, 10, 15);

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute([':username' => $identifier, ':email' => strtolower($identifier)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? $dummy))) {
            throw new HttpException('Incorrect username or password.', 401);
        }
        if ($user['status'] === 'banned') {
            throw new HttpException('Your account is suspended. Contact an administrator.', 403);
        }

        $limiter->clear($attemptKey);
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
        }
        $this->pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute([':id' => $user['id']]);
        Auth::login($user, !empty($data['remember']));
        return AvatarCatalog::decorate($user);
    }

    public function usernameAvailable(string $username): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            throw new HttpException('Invalid username format.', 422);
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        return !$stmt->fetchColumn();
    }

    private function findById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new HttpException('Account could not be loaded.', 500);
        }
        return $user;
    }


}
