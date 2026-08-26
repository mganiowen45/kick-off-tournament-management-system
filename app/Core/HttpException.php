<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 400, private readonly array $data = [])
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function data(): array
    {
        return $this->data;
    }
}
