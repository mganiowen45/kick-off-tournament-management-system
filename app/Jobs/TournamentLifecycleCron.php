<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Database;
use App\Services\TournamentService;

$pdo = Database::connection();
$service = new TournamentService($pdo);

(new \App\Core\JobRunner($pdo))->run('TournamentLifecycleCron', function($context) use ($pdo, $service) {
    $noShowStmt = $pdo->query(
        "SELECT id FROM tournaments
         WHERE status = 'open'
           AND check_in_closes_at IS NOT NULL
           AND check_in_closes_at <= NOW()
         ORDER BY check_in_closes_at ASC"
    );
    $tournaments = $noShowStmt->fetchAll(PDO::FETCH_COLUMN);
    $context->recordsProcessed += count($tournaments);
    
    foreach ($tournaments as $tournamentId) {
        try {
            $service->processNoShows((int) $tournamentId);
            $context->recordsSucceeded++;
        } catch (Throwable $exception) {
            $context->recordsFailed++;
            \App\Core\Logger::error('TournamentLifecycleCron', 'No-show processing failed for tournament ' . $tournamentId, ['error' => $exception->getMessage()]);
        }
    }

    $stmt = $pdo->query(
        "SELECT id FROM tournaments
         WHERE status = 'open'
           AND auto_start_at IS NOT NULL
           AND auto_start_at <= NOW()
         ORDER BY auto_start_at ASC
         LIMIT 20"
    );
    
    $startCandidates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $context->recordsProcessed += count($startCandidates);
    
    foreach ($startCandidates as $tournamentId) {
        $pending = $pdo->prepare(
            "SELECT COUNT(*) FROM tournament_players
             WHERE tournament_id = :id AND status != 'withdrawn' AND payment_status IN ('pending','failed')"
        );
        $pending->execute([':id' => $tournamentId]);
        if ((int) $pending->fetchColumn() > 0) {
            continue;
        }

        try {
            if ($service->autoStartDueTournament((int) $tournamentId)) {
                $context->recordsSucceeded++;
            }
        } catch (Throwable $exception) {
            $context->recordsFailed++;
            \App\Core\Logger::error('TournamentLifecycleCron', 'Auto-start failed for tournament ' . $tournamentId, ['error' => $exception->getMessage()]);
        }
    }
});
