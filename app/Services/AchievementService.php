<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class AchievementService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function evaluate(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT wins, total_matches, championships FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$stats) {
            return;
        }

        $codes = [];
        if ((int) $stats['total_matches'] >= 1) $codes[] = 'first_match';
        if ((int) $stats['wins'] >= 1) $codes[] = 'first_win';
        if ((int) $stats['wins'] >= 10) $codes[] = 'ten_wins';
        if ((int) $stats['total_matches'] >= 50) $codes[] = 'fifty_matches';
        if ((int) $stats['championships'] >= 1) $codes[] = 'champion';

        if (!$codes) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $insert = $this->pdo->prepare(
            "INSERT IGNORE INTO user_achievements (user_id, achievement_id)
             SELECT ?, id FROM achievements WHERE code IN ({$placeholders})"
        );
        $insert->execute(array_merge([$userId], $codes));
    }
}

