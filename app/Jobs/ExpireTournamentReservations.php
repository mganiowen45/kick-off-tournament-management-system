<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$stmt = $pdo->query(
    "SELECT tournament_id, COUNT(*) AS expired_count
     FROM tournament_players
     WHERE payment_status = 'pending'
       AND reservation_expires_at IS NOT NULL
       AND reservation_expires_at < NOW()
     GROUP BY tournament_id"
);
$expired = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pdo->beginTransaction();
try {
    $pdo->exec(
        "UPDATE tournament_players
         SET status = 'withdrawn', payment_status = 'failed'
         WHERE payment_status = 'pending'
           AND reservation_expires_at IS NOT NULL
           AND reservation_expires_at < NOW()"
    );
    $update = $pdo->prepare(
        "UPDATE tournaments
         SET current_players = GREATEST(0, current_players - :count),
             check_in_opens_at = NULL,
             check_in_closes_at = NULL,
             auto_start_at = NULL,
             lifecycle_note = 'Pending payment reservations expired.'
         WHERE id = :id AND status = 'open'"
    );
    foreach ($expired as $tournamentId => $count) {
        $update->execute([':count' => (int) $count, ':id' => (int) $tournamentId]);
    }
    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}

echo 'Expired reservations: ' . array_sum(array_map('intval', $expired)) . PHP_EOL;
