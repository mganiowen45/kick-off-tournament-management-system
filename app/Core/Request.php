<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private ?array $data = null;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function requireMethod(string ...$allowed): void
    {
        $allowed = array_map('strtoupper', $allowed);
        if (!in_array($this->method(), $allowed, true)) {
            throw new HttpException('Method not allowed.', 405);
        }
    }

    public function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            if ($raw === '') {
                return $this->data = [];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new HttpException('Invalid JSON request body.', 400);
            }
            return $this->data = $decoded;
        }

        return $this->data = $_POST;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data()[$key] ?? $default;
    }

    public function integer(string $key, int $default = 0): int
    {
        return (int) ($this->input($key, $default));
    }

    public function queryInteger(string $key, int $default = 0): int
    {
        return (int) ($this->query($key, $default));
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function requireCsrf(): void
    {
        if (!Csrf::validate($this->header('X-CSRF-Token') ?? (string) $this->input('_csrf', ''))) {
            throw new HttpException('Your session token expired. Refresh the page and try again.', 419);
        }
    }

    public function pagination(): array
    {
        $page = max(1, (int) $this->query('page', 1));
        $limit = min(100, max(1, (int) $this->query('limit', DEFAULT_PAGE_LIMIT)));
        return ['page' => $page, 'limit' => $limit, 'offset' => ($page - 1) * $limit];
    }
}

