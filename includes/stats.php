<?php
// ============================================================
//  KICKOFF — Player Stats Updater
// ============================================================

/**
 * Update global user stats (wins/losses/draws/points/total_matches)
 * and per-tournament stats after a confirmed match result.
 */
function updatePlayerStats(int $matchId): void {
    $db = db();

    // Load match details
    $stmt = $db->prepare("
        SELECT m.*, t.format
        FROM matches m
        JOIN tournaments t ON t.id = m.tournament_id
        WHERE m.id = :id
    ");
    $stmt->execute([':id' => $matchId]);
    $match = $stmt->fetch();

    if (!$match || $match['status'] !== 'confirmed') return;

    $p1   = (int) $match['player1_id'];
    $p2   = (int) $match['player2_id'];
    $win  = (int) ($match['winner_id'] ?? 0);
    $draw = (bool) $match['is_draw'];
    $tid  = (int) $match['tournament_id'];
    $fmt  = $match['format'];

    // ── Update global users table ──────────────────────────
    if ($draw) {
        _updateGlobalStats($p1, 'draw');
        _updateGlobalStats($p2, 'draw');
    } elseif ($win === $p1) {
        _updateGlobalStats($p1, 'win');
        _updateGlobalStats($p2, 'loss');
    } else {
        _updateGlobalStats($p2, 'win');
        _updateGlobalStats($p1, 'loss');
    }

    // ── Update tournament_players league stats ─────────────
    if ($fmt === 'group_knockout' && ($match['stage'] ?? '') === 'group') {
        $goalsP1 = (int) $match['player1_score'];
        $goalsP2 = (int) $match['player2_score'];

        if ($draw) {
            _updateLeagueStats($tid, $p1, 'draw', $goalsP1, $goalsP2);
            _updateLeagueStats($tid, $p2, 'draw', $goalsP2, $goalsP1);
        } elseif ($win === $p1) {
            _updateLeagueStats($tid, $p1, 'win',  $goalsP1, $goalsP2);
            _updateLeagueStats($tid, $p2, 'loss', $goalsP2, $goalsP1);
        } else {
            _updateLeagueStats($tid, $p2, 'win',  $goalsP2, $goalsP1);
            _updateLeagueStats($tid, $p1, 'loss', $goalsP1, $goalsP2);
        }
    }

    // ── Knockout: mark loser as eliminated ────────────────
    if (in_array($fmt, ['full_knockout', 'group_knockout'], true) && ($match['stage'] ?? 'knockout') === 'knockout' && !$draw) {
        $loserId = ($win === $p1) ? $p2 : $p1;
        $elim = $db->prepare("
            UPDATE tournament_players
            SET status = 'eliminated', is_eliminated = 1
            WHERE tournament_id = :tid AND user_id = :uid
        ");
        $elim->execute([':tid' => $tid, ':uid' => $loserId]);
    }
}

function _updateGlobalStats(int $userId, string $result): void {
    $points = match($result) {
        'win'  => POINTS_WIN,
        'draw' => POINTS_DRAW,
        'loss' => POINTS_LOSS,
    };
    $col = $result === 'win' ? 'wins' : ($result === 'loss' ? 'losses' : 'draws');

    $stmt = db()->prepare("
        UPDATE users
        SET {$col}        = {$col} + 1,
            total_matches = total_matches + 1,
            points        = points + :pts
        WHERE id = :id
    ");
    $stmt->execute([':pts' => $points, ':id' => $userId]);
}

function _updateLeagueStats(int $tid, int $uid, string $result,
                             int $gf, int $ga): void {
    $leaguePts = match($result) {
        'win'  => 3,
        'draw' => 1,
        'loss' => 0,
    };
    $col = $result === 'win' ? 'wins' : ($result === 'loss' ? 'losses' : 'draws');

    $stmt = db()->prepare("
        UPDATE tournament_players
        SET {$col}         = {$col} + 1,
            league_points  = league_points + :lp,
            goals_for      = goals_for + :gf,
            goals_against  = goals_against + :ga
        WHERE tournament_id = :tid AND user_id = :uid
    ");
    $stmt->execute([
        ':lp' => $leaguePts, ':gf' => $gf,
        ':ga' => $ga, ':tid' => $tid, ':uid' => $uid,
    ]);
}
