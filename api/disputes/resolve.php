<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../tournaments/advance.php';

requireAdmin();
requirePost();

$data = getPostData();
$disputeId = (int)($data['dispute_id'] ?? 0);
$decision = sanitize($data['decision'] ?? ($data['outcome'] ?? ''));

if ($disputeId <= 0) {
    jsonError('Invalid dispute id.');
}
if (!in_array($decision, ['player1_wins', 'player2_wins', 'draw', 'replay'], true)) {
    jsonError('Invalid decision.');
}

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("SELECT * FROM disputes WHERE id = ? FOR UPDATE");
    $stmt->execute([$disputeId]);
    $dispute = $stmt->fetch();
    if (!$dispute) {
        throw new RuntimeException('Dispute not found.');
    }

    $stmt = $pdo->prepare("
        SELECT m.*, t.format
        FROM matches m
        JOIN tournaments t ON t.id = m.tournament_id
        WHERE m.id = ?
        FOR UPDATE
    ");
    $stmt->execute([(int)$dispute['match_id']]);
    $match = $stmt->fetch();
    if (!$match) {
        throw new RuntimeException('Match not found.');
    }
    $allowDraw = $match['format'] === 'group_knockout' && ($match['stage'] ?? '') === 'group';
    if ($decision === 'draw' && !$allowDraw) {
        throw new RuntimeException('This match requires a winner or replay.');
    }

    $winnerId = null;
    if ($decision === 'player1_wins') {
        $winnerId = (int)$match['player1_id'];
    } elseif ($decision === 'player2_wins') {
        $winnerId = (int)$match['player2_id'];
    }

    if ($decision === 'replay') {
        $pdo->prepare("
            UPDATE matches
            SET status = 'scheduled',
                player1_score = NULL,
                player2_score = NULL,
                winner_id = NULL
            WHERE id = ?
        ")->execute([(int)$match['id']]);

        $pdo->prepare("DELETE FROM match_results WHERE match_id = ?")
            ->execute([(int)$match['id']]);
    } elseif ($decision === 'draw') {
        $pdo->prepare("
            UPDATE matches
            SET status = 'confirmed',
                winner_id = NULL,
                is_draw = 1,
                player1_score = COALESCE(player1_score, 0),
                player2_score = COALESCE(player2_score, 0),
                played_at = COALESCE(played_at, NOW()),
                confirmed_at = NOW()
            WHERE id = ?
        ")->execute([(int)$match['id']]);
    } else {
        $pdo->prepare("
            UPDATE matches
            SET status = 'confirmed',
                winner_id = ?,
                is_draw = 0,
                played_at = COALESCE(played_at, NOW()),
                confirmed_at = NOW()
            WHERE id = ?
        ")->execute([$winnerId, (int)$match['id']]);

        if ($winnerId) {
            advanceWinnerToNextRound($pdo, (int)$match['id']);
        }
    }

    $pdo->prepare("UPDATE disputes SET status = 'resolved', outcome = ?, admin_note = ?, resolved_at = NOW() WHERE id = ?")
        ->execute([$decision, sanitize($data['admin_note'] ?? ''), $disputeId]);

    $pdo->commit();
    jsonSuccess([], 'Dispute resolved.');
} catch (Throwable $e) {
    $pdo->rollBack();
    jsonError(DEBUG_MODE ? $e->getMessage() : 'Unable to resolve dispute.', 500);
}
