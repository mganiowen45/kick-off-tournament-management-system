<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Database;
use App\Services\TournamentService;

$pdo = Database::connection();
$service = new TournamentService($pdo);
$stmt = $pdo->query(
    "SELECT id FROM tournaments
     WHERE status = 'open'
       AND auto_start_at IS NOT NULL
       AND auto_start_at <= NOW()
       AND current_players >= max_players
     ORDER BY auto_start_at ASC
     LIMIT 20"
);

$started = 0;
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tournamentId) {
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
            $started++;
        }
    } catch (Throwable $exception) {
        error_log('[KICKOFF lifecycle] Tournament ' . $tournamentId . ': ' . $exception->getMessage());
    }
}

echo 'Started tournaments: ' . $started . PHP_EOL;
