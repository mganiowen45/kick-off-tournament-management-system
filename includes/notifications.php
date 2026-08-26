<?php
// ============================================================
//  KICKOFF — Notification Helper
// ============================================================

/**
 * Insert a notification row for a user.
 */
function createNotification(
    int    $userId,
    string $title,
    string $body,
    string $type    = 'system',
    string $linkUrl = ''
): bool {
    try {
        $stmt = db()->prepare("
            INSERT INTO notifications (user_id, title, body, type, link_url)
            VALUES (:user_id, :title, :body, :type, :link_url)
        ");
        return $stmt->execute([
            ':user_id'  => $userId,
            ':title'    => $title,
            ':body'     => $body,
            ':type'     => $type,
            ':link_url' => $linkUrl,
        ]);
    } catch (PDOException $e) {
        // Non-fatal — log but don't crash the request
        error_log('Notification insert failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Notify both players in a match about a result event.
 */
function notifyMatchResult(int $matchId, string $event): void {
    $stmt = db()->prepare("
        SELECT m.player1_id, m.player2_id, t.name AS tournament_name
        FROM matches m
        JOIN tournaments t ON t.id = m.tournament_id
        WHERE m.id = :match_id
    ");
    $stmt->execute([':match_id' => $matchId]);
    $match = $stmt->fetch();
    if (!$match) return;

    $link = 'tournament_detail.html';

    switch ($event) {
        case 'confirmed':
            createNotification($match['player1_id'], 'Result confirmed',
                "Your match result in {$match['tournament_name']} has been confirmed.",
                'result_confirmed', $link);
            createNotification($match['player2_id'], 'Result confirmed',
                "Your match result in {$match['tournament_name']} has been confirmed.",
                'result_confirmed', $link);
            break;

        case 'disputed':
            createNotification($match['player1_id'], 'Result disputed',
                "A conflict was detected in your match. Admin will review.",
                'result_disputed', $link);
            createNotification($match['player2_id'], 'Result disputed',
                "A conflict was detected in your match. Admin will review.",
                'result_disputed', $link);
            break;

        case 'dispute_resolved':
            createNotification($match['player1_id'], 'Dispute resolved',
                "The admin has resolved the dispute for your match in {$match['tournament_name']}.",
                'dispute_resolved', 'admin_disputes.html');
            createNotification($match['player2_id'], 'Dispute resolved',
                "The admin has resolved the dispute for your match in {$match['tournament_name']}.",
                'dispute_resolved', 'admin_disputes.html');
            break;
    }
}
