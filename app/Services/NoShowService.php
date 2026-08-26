<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use PDO;

final class NoShowService
{
    private NotificationService $notifications;

    public function __construct(private readonly PDO $pdo)
    {
        $this->notifications = new NotificationService($pdo);
    }

    public function report(int $reporterId, int $matchId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 1000) {
            throw new HttpException('Describe why this is a no-show report.', 422);
        }

        return Database::transaction(function () use ($reporterId, $matchId, $reason): array {
            $stmt = $this->pdo->prepare(
                'SELECT m.*, t.name AS tournament_name
                 FROM matches m JOIN tournaments t ON t.id = m.tournament_id
                 WHERE m.id = :id FOR UPDATE'
            );
            $stmt->execute([':id' => $matchId]);
            $match = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$match) throw new HttpException('Match not found.', 404);
            if (!in_array($match['status'], ['scheduled', 'pending_result'], true)) {
                throw new HttpException('This match is not eligible for a no-show report.', 409);
            }
            if ((int) $match['player1_id'] !== $reporterId && (int) $match['player2_id'] !== $reporterId) {
                throw new HttpException('You may report a no-show only for your own match.', 403);
            }
            $accusedId = (int) $match['player1_id'] === $reporterId ? (int) $match['player2_id'] : (int) $match['player1_id'];

            $insert = $this->pdo->prepare(
                "INSERT INTO match_no_show_reports (match_id, reporter_id, accused_id, reason)
                 VALUES (:match, :reporter, :accused, :reason)"
            );
            try {
                $insert->execute([
                    ':match' => $matchId,
                    ':reporter' => $reporterId,
                    ':accused' => $accusedId,
                    ':reason' => $reason,
                ]);
            } catch (\PDOException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    throw new HttpException('You already submitted a no-show report for this match.', 409);
                }
                throw $exception;
            }

            $this->pdo->prepare("UPDATE matches SET no_show_status = 'reported' WHERE id = :id")
                ->execute([':id' => $matchId]);

            foreach ($this->adminIds() as $adminId) {
                $this->notifications->create(
                    $adminId,
                    'No-show report submitted',
                    "A no-show report in {$match['tournament_name']} needs review.",
                    'no_show_reported',
                    "admin_disputes.html",
                    $reporterId
                );
            }
            $this->notifications->create(
                $accusedId,
                'No-show report submitted',
                "Your opponent submitted a no-show report in {$match['tournament_name']}.",
                'no_show_reported',
                "tournament_detail.html?id={$match['tournament_id']}",
                $reporterId
            );

            return ['report_id' => (int) $this->pdo->lastInsertId()];
        });
    }

    private function adminIds(): array
    {
        return array_map('intval', $this->pdo->query(
            "SELECT id FROM users WHERE role = 'admin' AND status != 'banned'"
        )->fetchAll(PDO::FETCH_COLUMN));
    }
}
