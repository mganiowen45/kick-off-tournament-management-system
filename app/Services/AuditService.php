<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class AuditService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(?int $actorId, string $action, string $entityType, ?int $entityId = null, array $metadata = []): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, metadata, ip_address, user_agent)
                 VALUES (:actor_id, :action, :entity_type, :entity_id, :metadata, :ip, :user_agent)'
            );
            $stmt->execute([
                ':actor_id' => $actorId,
                ':action' => mb_substr($action, 0, 80),
                ':entity_type' => mb_substr($entityType, 0, 80),
                ':entity_id' => $entityId,
                ':metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            ]);
        } catch (Throwable $exception) {
            error_log('[KICKOFF audit] ' . $exception->getMessage());
        }
    }
}
