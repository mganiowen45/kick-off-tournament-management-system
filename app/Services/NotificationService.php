<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class NotificationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(
        int $userId,
        string $title,
        string $body,
        string $type = 'system',
        string $linkUrl = '',
        ?int $actorId = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO notifications (user_id, actor_id, title, body, type, link_url)
                 VALUES (:user_id, :actor_id, :title, :body, :type, :link_url)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':actor_id' => $actorId,
                ':title' => mb_substr($title, 0, 120),
                ':body' => $body,
                ':type' => $type,
                ':link_url' => $linkUrl,
            ]);
        } catch (Throwable $exception) {
            error_log('[KICKOFF notification] ' . $exception->getMessage());
        }
    }

    public function tournamentMembers(
        int $tournamentId,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        ?int $actorId = null,
        ?int $exceptUserId = null
    ): void {
        $stmt = $this->pdo->prepare(
            "SELECT user_id FROM tournament_players
             WHERE tournament_id = :id AND status != 'withdrawn'"
        );
        $stmt->execute([':id' => $tournamentId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
            if ($exceptUserId !== null && (int) $userId === $exceptUserId) {
                continue;
            }
            $this->create((int) $userId, $title, $body, $type, $linkUrl, $actorId);
        }
    }
}

