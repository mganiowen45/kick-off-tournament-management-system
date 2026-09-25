<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Core\Logger;

final class AlertService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function raise(string $component, string $message): void
    {
        // Prevent spamming the same open alert
        $stmt = $this->pdo->prepare("SELECT id FROM operational_alerts WHERE component = :component AND status = 'open' LIMIT 1");
        $stmt->execute([':component' => $component]);
        
        if (!$stmt->fetchColumn()) {
            $insert = $this->pdo->prepare(
                "INSERT INTO operational_alerts (component, message, status, created_at) VALUES (:component, :message, 'open', NOW())"
            );
            $insert->execute([':component' => $component, ':message' => $message]);
            
            Logger::critical($component, $message);
        } else {
            Logger::error($component, "Repeated alert: $message");
        }
    }
}
