<?php

function advanceWinnerToNextRound($pdo, $match_id) {
    // Get current match
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$match || !$match['winner_id']) return;

    $tournament_id = $match['tournament_id'];
    $next_round = $match['round_number'] + 1;
    $position = $match['match_number'];

    // Find next match
    $stmt = $pdo->prepare("
        SELECT * FROM matches 
        WHERE tournament_id = ? AND round_number = ?
        ORDER BY match_number ASC
    ");
    $stmt->execute([$tournament_id, $next_round]);
    $nextMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$nextMatches) return;

    // Determine which match to place into
    $targetIndex = floor(($position - 1) / 2);
    if (!isset($nextMatches[$targetIndex])) return;

    $nextMatch = $nextMatches[$targetIndex];

    // Assign to empty slot
    if (!$nextMatch['player1_id']) {
        $pdo->prepare("UPDATE matches SET player1_id = ? WHERE id = ?")
            ->execute([$match['winner_id'], $nextMatch['id']]);
    } elseif (!$nextMatch['player2_id']) {
        $pdo->prepare("UPDATE matches SET player2_id = ? WHERE id = ?")
            ->execute([$match['winner_id'], $nextMatch['id']]);
    }
}
?>
