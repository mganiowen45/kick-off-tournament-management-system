<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function success(array $data = [], string $message = 'OK', int $status = 200): never
    {
        self::json(array_merge(['success' => true, 'message' => $message], $data), $status);
    }

    public static function error(string $message, int $status = 400, array $data = []): never
    {
        self::json(array_merge(['success' => false, 'error' => $message], $data), $status);
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function redirect(string $target, int $status = 302): never
    {
        header('Location: ' . $target, true, $status);
        exit;
    }

    private function __construct()
    {
    }
}

