<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class RateLimiter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function limit(string $key, int $maxAttempts = 10, int $decayMinutes = 15): void
    {
        $hashKey = hash('sha256', $key);
        
        $stmt = $this->pdo->prepare('SELECT attempts, first_attempt_at FROM rate_limits WHERE attempt_key = :key LIMIT 1');
        $stmt->execute([':key' => $hashKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && (int) $row['attempts'] >= $maxAttempts && strtotime((string) $row['first_attempt_at']) > time() - ($decayMinutes * 60)) {
            throw new HttpException('Too many requests. Please try again later.', 429);
        }

        $this->recordAttempt($hashKey, $decayMinutes);
    }

    public function clear(string $key): void
    {
        $hashKey = hash('sha256', $key);
        $this->pdo->prepare('DELETE FROM rate_limits WHERE attempt_key = :key')->execute([':key' => $hashKey]);
    }

    private function recordAttempt(string $hashKey, int $decayMinutes): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rate_limits (attempt_key, attempts, first_attempt_at, last_attempt_at)
             VALUES (:key, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               attempts = IF(first_attempt_at < DATE_SUB(NOW(), INTERVAL :decay MINUTE), 1, attempts + 1),
               first_attempt_at = IF(first_attempt_at < DATE_SUB(NOW(), INTERVAL :decay2 MINUTE), NOW(), first_attempt_at),
               last_attempt_at = NOW()"
        );
        $stmt->execute([':key' => $hashKey, ':decay' => $decayMinutes, ':decay2' => $decayMinutes]);
    }
}
