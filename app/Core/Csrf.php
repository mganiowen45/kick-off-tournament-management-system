<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf_token'];
    }

    public static function validate(string $token): bool
    {
        return $token !== ''
            && isset($_SESSION['_csrf_token'])
            && hash_equals((string) $_SESSION['_csrf_token'], $token);
    }

    public static function rotate(): string
    {
        unset($_SESSION['_csrf_token']);
        return self::token();
    }

    private function __construct()
    {
    }
}

