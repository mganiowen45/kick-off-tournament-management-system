<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../tournaments/advance.php';

function verifyMatch(int $matchId, ?PDO $pdo = null): void {
    $pdo = $pdo ?: db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$match) {
            $pdo->commit();
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM match_results WHERE match_id = ? ORDER BY id ASC");
        $stmt->execute([$matchId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($results) < 2) {
            $pdo->commit();
            return;
        }

        $r1 = $results[0];
        $r2 = $results[1];

        if ((int)$r1['submitted_by'] === (int)$match['player1_id']) {
            $p1Score = (int)$r1['my_score'];
            $p2Score = (int)$r1['opponent_score'];
        } else {
            $p1Score = (int)$r1['opponent_score'];
            $p2Score = (int)$r1['my_score'];
        }

        $validClaim =
            ($r1['claimed_result'] === 'win' && $r2['claimed_result'] === 'loss') ||
            ($r1['claimed_result'] === 'loss' && $r2['claimed_result'] === 'win') ||
            ($r1['claimed_result'] === 'draw' && $r2['claimed_result'] === 'draw');

        $scoresMatch =
            (int)$r1['my_score'] === (int)$r2['opponent_score'] &&
            (int)$r1['opponent_score'] === (int)$r2['my_score'];

        if ($validClaim && $scoresMatch) {
            $winnerId = null;
            if ($p1Score > $p2Score) {
                $winnerId = (int)$match['player1_id'];
            } elseif ($p2Score > $p1Score) {
                $winnerId = (int)$match['player2_id'];
            }

            $pdo->prepare("
                UPDATE matches
                SET status = 'confirmed',
                    player1_score = ?,
                    player2_score = ?,
                    winner_id = ?,
                    is_draw = ?
                WHERE id = ?
            ")->execute([$p1Score, $p2Score, $winnerId, $winnerId ? 0 : 1, $matchId]);

            $pdo->prepare("UPDATE match_results SET verification_status = 'confirmed' WHERE match_id = ?")
                ->execute([$matchId]);

            if ($winnerId) {
                advanceWinnerToNextRound($pdo, $matchId);
            }
        } else {
            $pdo->prepare("
                INSERT IGNORE INTO disputes (match_id, reason, status)
                VALUES (?, 'Conflict in results', 'open')
            ")->execute([$matchId]);

            $pdo->prepare("UPDATE matches SET status = 'disputed' WHERE id = ?")
                ->execute([$matchId]);

            $pdo->prepare("UPDATE match_results SET verification_status = 'conflicted' WHERE match_id = ?")
                ->execute([$matchId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
