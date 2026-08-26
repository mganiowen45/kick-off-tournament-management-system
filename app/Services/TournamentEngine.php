<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use PDO;

final class TournamentEngine
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function start(int $tournamentId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $tournamentId]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tournament) {
            throw new HttpException('Tournament not found.', 404);
        }
        if (!in_array($tournament['status'], ['open', 'draft'], true)) {
            throw new HttpException('Only an open tournament can be started.', 409);
        }

        $existing = $this->pdo->prepare('SELECT COUNT(*) FROM matches WHERE tournament_id = :id');
        $existing->execute([':id' => $tournamentId]);
        if ((int) $existing->fetchColumn() > 0) {
            throw new HttpException('This tournament already has fixtures.', 409);
        }

        $players = $this->activePlayerIds($tournamentId, true);
        if (count($players) < 2) {
            throw new HttpException('At least two players are required to start.', 409);
        }
        if ($tournament['format'] === '1v1' && count($players) !== 2) {
            throw new HttpException('A 1V1 tournament requires exactly two players.', 409);
        }

        $this->pdo->prepare("UPDATE tournaments SET status = 'active', current_round = 1 WHERE id = :id")
            ->execute([':id' => $tournamentId]);
        $this->pdo->prepare(
            "UPDATE tournament_players
             SET status = 'active', is_eliminated = 0, current_round = 1
             WHERE tournament_id = :id AND status != 'withdrawn'"
        )->execute([':id' => $tournamentId]);

        match ((string) $tournament['format']) {
            '1v1' => $this->generateSeries($tournament, $players),
            'full_knockout' => $this->generateKnockoutBracket($tournament, $players, 'knockout'),
            'group_knockout' => $this->generateGroups($tournament, $players),
            default => throw new HttpException('Unsupported tournament format.', 422),
        };
    }

    public function progressAfterConfirmedMatch(int $matchId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, t.format, t.max_players, t.status AS tournament_status
             FROM matches m JOIN tournaments t ON t.id = m.tournament_id
             WHERE m.id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $matchId]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$match || $match['status'] !== 'confirmed' || $match['tournament_status'] === 'completed') {
            return;
        }

        if ($match['format'] === '1v1') {
            $this->progressSeries($match);
            return;
        }

        if ($match['stage'] === 'group') {
            $this->completeGroupsIfReady((int) $match['tournament_id']);
            return;
        }

        $this->progressKnockout($match);
    }

    private function generateSeries(array $tournament, array $players): void
    {
        $this->pdo->prepare(
            "INSERT INTO tournament_series (tournament_id, player1_id, player2_id, best_of)
             VALUES (:tournament, :p1, :p2, 3)"
        )->execute([':tournament' => $tournament['id'], ':p1' => $players[0], ':p2' => $players[1]]);
        $seriesId = (int) $this->pdo->lastInsertId();
        $matchId = $this->insertMatch($tournament, $players[0], $players[1], 1, 1, 'series', null, $seriesId, 1);
        $this->pdo->prepare(
            "INSERT INTO series_games (series_id, match_id, game_number)
             VALUES (:series, :match, 1)"
        )->execute([':series' => $seriesId, ':match' => $matchId]);
    }

    private function progressSeries(array $match): void
    {
        $seriesId = (int) ($match['series_id'] ?? 0);
        if ($seriesId < 1 || !$match['winner_id']) {
            return;
        }

        $seriesStmt = $this->pdo->prepare('SELECT * FROM tournament_series WHERE id = :id FOR UPDATE');
        $seriesStmt->execute([':id' => $seriesId]);
        $series = $seriesStmt->fetch(PDO::FETCH_ASSOC);
        if (!$series || $series['status'] === 'completed') {
            return;
        }

        $p1Win = (int) $match['winner_id'] === (int) $series['player1_id'] ? 1 : 0;
        $p2Win = $p1Win ? 0 : 1;
        $this->pdo->prepare(
            'UPDATE tournament_series SET player1_wins = player1_wins + :p1, player2_wins = player2_wins + :p2 WHERE id = :id'
        )->execute([':p1' => $p1Win, ':p2' => $p2Win, ':id' => $seriesId]);

        $seriesStmt->execute([':id' => $seriesId]);
        $series = $seriesStmt->fetch(PDO::FETCH_ASSOC);
        $winnerId = null;
        if ((int) $series['player1_wins'] >= 2) {
            $winnerId = (int) $series['player1_id'];
        } elseif ((int) $series['player2_wins'] >= 2) {
            $winnerId = (int) $series['player2_id'];
        }

        if ($winnerId !== null) {
            $this->pdo->prepare(
                "UPDATE tournament_series SET status = 'completed', winner_id = :winner, completed_at = NOW() WHERE id = :id"
            )->execute([':winner' => $winnerId, ':id' => $seriesId]);
            $this->complete((int) $match['tournament_id'], $winnerId);
            return;
        }

        $nextGame = (int) $match['series_game_number'] + 1;
        if ($nextGame <= 3) {
            $tournamentStmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id');
            $tournamentStmt->execute([':id' => $match['tournament_id']]);
            $tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);
            $nextMatchId = $this->insertMatch(
                $tournament,
                (int) $series['player1_id'],
                (int) $series['player2_id'],
                $nextGame,
                $nextGame,
                'series',
                null,
                $seriesId,
                $nextGame
            );
            $this->pdo->prepare(
                "INSERT INTO series_games (series_id, match_id, game_number)
                 VALUES (:series, :match, :game)"
            )->execute([':series' => $seriesId, ':match' => $nextMatchId, ':game' => $nextGame]);
        }
    }

    private function generateKnockoutBracket(array $tournament, array $players, string $stage): void
    {
        $size = $this->nextPowerOfTwo(count($players));
        if ((string) $tournament['format'] === 'full_knockout' && count($players) !== $size) {
            throw new HttpException('Full Knockout requires a supported full bracket size.', 409);
        }

        shuffle($players);
        while (count($players) < $size) {
            $players[] = null;
        }

        $rounds = (int) log($size, 2);
        for ($round = 1; $round <= $rounds; $round++) {
            $positions = (int) ($size / (2 ** $round));
            for ($position = 1; $position <= $positions; $position++) {
                $this->insertBracketSlot((int) $tournament['id'], $stage, $round, $position, 1, null);
                $this->insertBracketSlot((int) $tournament['id'], $stage, $round, $position, 2, null);
            }
        }

        for ($index = 0, $position = 1; $index < $size; $index += 2, $position++) {
            $p1 = $players[$index];
            $p2 = $players[$index + 1];
            $this->setBracketSlot((int) $tournament['id'], $stage, 1, $position, 1, $p1, $p2 === null);
            $this->setBracketSlot((int) $tournament['id'], $stage, 1, $position, 2, $p2, $p1 === null);
            if ($p1 !== null && $p2 !== null) {
                $this->insertMatch($tournament, (int) $p1, (int) $p2, 1, $position, $stage, null);
            } else {
                $winner = $p1 ?? $p2;
                if ($winner !== null) {
                    $this->advanceWinner((int) $tournament['id'], $stage, 1, $position, (int) $winner, null);
                }
            }
        }
    }

    private function progressKnockout(array $match): void
    {
        if (!$match['winner_id']) {
            return;
        }

        $loser = (int) $match['winner_id'] === (int) $match['player1_id']
            ? (int) $match['player2_id']
            : (int) $match['player1_id'];
        $this->pdo->prepare(
            "UPDATE tournament_players SET status = 'eliminated', is_eliminated = 1
             WHERE tournament_id = :tournament AND user_id = :user"
        )->execute([':tournament' => $match['tournament_id'], ':user' => $loser]);

        $this->advanceWinner(
            (int) $match['tournament_id'],
            (string) $match['stage'],
            (int) $match['round_number'],
            (int) $match['bracket_position'],
            (int) $match['winner_id'],
            (int) $match['id']
        );
    }

    private function advanceWinner(int $tournamentId, string $stage, int $round, int $position, int $winnerId, ?int $sourceMatchId): void
    {
        $nextRound = $round + 1;
        $nextPosition = (int) ceil($position / 2);
        $nextSlot = $position % 2 === 1 ? 1 : 2;

        $exists = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tournament_bracket_slots WHERE tournament_id = :tournament AND stage = :stage AND round_number = :round'
        );
        $exists->execute([':tournament' => $tournamentId, ':stage' => $stage, ':round' => $nextRound]);
        if ((int) $exists->fetchColumn() === 0) {
            $this->complete($tournamentId, $winnerId);
            return;
        }

        $this->setBracketSlot($tournamentId, $stage, $nextRound, $nextPosition, $nextSlot, $winnerId, false, $sourceMatchId);

        $slots = $this->pdo->prepare(
            "SELECT slot_number, user_id FROM tournament_bracket_slots
             WHERE tournament_id = :tournament AND stage = :stage AND round_number = :round AND bracket_position = :position
             ORDER BY slot_number ASC"
        );
        $slots->execute([':tournament' => $tournamentId, ':stage' => $stage, ':round' => $nextRound, ':position' => $nextPosition]);
        $rows = $slots->fetchAll(PDO::FETCH_KEY_PAIR);
        $p1 = isset($rows[1]) ? (int) $rows[1] : 0;
        $p2 = isset($rows[2]) ? (int) $rows[2] : 0;
        if ($p1 < 1 || $p2 < 1) {
            return;
        }

        $matchExists = $this->pdo->prepare(
            "SELECT COUNT(*) FROM matches
             WHERE tournament_id = :tournament AND stage = :stage AND round_number = :round AND bracket_position = :position"
        );
        $matchExists->execute([':tournament' => $tournamentId, ':stage' => $stage, ':round' => $nextRound, ':position' => $nextPosition]);
        if ((int) $matchExists->fetchColumn() > 0) {
            return;
        }

        $tournamentStmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id');
        $tournamentStmt->execute([':id' => $tournamentId]);
        $tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);
        $this->pdo->prepare('UPDATE tournaments SET current_round = GREATEST(current_round, :round) WHERE id = :id')
            ->execute([':round' => $nextRound, ':id' => $tournamentId]);
        $this->insertMatch($tournament, $p1, $p2, $nextRound, $nextPosition, $stage, null);
    }

    private function generateGroups(array $tournament, array $players): void
    {
        if (!in_array(count($players), [8, 16, 32], true)) {
            throw new HttpException('Group Stage + Knockout requires 8, 16, or 32 players.', 409);
        }

        shuffle($players);
        $groupCount = count($players) / 4;
        $groups = array_chunk($players, 4);
        foreach ($groups as $index => $members) {
            $name = chr(65 + $index);
            $this->pdo->prepare(
                "INSERT INTO tournament_groups (tournament_id, name, sort_order, status)
                 VALUES (:tournament, :name, :sort, 'active')"
            )->execute([':tournament' => $tournament['id'], ':name' => 'Group ' . $name, ':sort' => $index + 1]);
            $groupId = (int) $this->pdo->lastInsertId();
            foreach ($members as $member) {
                $this->pdo->prepare(
                    "INSERT INTO tournament_group_members (group_id, tournament_id, user_id)
                     VALUES (:group_id, :tournament, :user)"
                )->execute([':group_id' => $groupId, ':tournament' => $tournament['id'], ':user' => $member]);
                $this->pdo->prepare('UPDATE tournament_players SET group_id = :group WHERE tournament_id = :tournament AND user_id = :user')
                    ->execute([':group' => $groupId, ':tournament' => $tournament['id'], ':user' => $member]);
            }
            $this->generateRoundRobin($tournament, $members, $groupId);
        }
    }

    private function generateRoundRobin(array $tournament, array $players, int $groupId): void
    {
        if (count($players) !== 4) {
            throw new HttpException('Group fixtures require groups of four.', 409);
        }
        $fixtures = [
            [[0, 1], [2, 3]],
            [[0, 2], [1, 3]],
            [[0, 3], [1, 2]],
        ];
        foreach ($fixtures as $matchday => $pairs) {
            foreach ($pairs as $position => [$a, $b]) {
                $this->insertMatch($tournament, (int) $players[$a], (int) $players[$b], $matchday + 1, $position + 1, 'group', $groupId);
            }
        }
    }

    private function completeGroupsIfReady(int $tournamentId): void
    {
        if ($this->remainingMatches($tournamentId, 'group') > 0) {
            return;
        }
        $exists = $this->pdo->prepare("SELECT COUNT(*) FROM matches WHERE tournament_id = :id AND stage = 'knockout'");
        $exists->execute([':id' => $tournamentId]);
        if ((int) $exists->fetchColumn() > 0) {
            return;
        }

        $qualifiersByGroup = [];
        $groups = $this->pdo->prepare('SELECT id, sort_order FROM tournament_groups WHERE tournament_id = :id ORDER BY sort_order ASC');
        $groups->execute([':id' => $tournamentId]);
        foreach ($groups->fetchAll(PDO::FETCH_ASSOC) as $group) {
            $groupId = (int) $group['id'];
            $rows = $this->pdo->prepare(
                "SELECT tp.user_id, tp.league_points, tp.goals_for, tp.goals_against, tp.wins, tp.draws, tp.losses
                 FROM tournament_group_members gm
                 JOIN tournament_players tp ON tp.tournament_id = gm.tournament_id AND tp.user_id = gm.user_id
                 WHERE gm.group_id = :group_id
                 ORDER BY tp.user_id ASC"
            );
            $rows->execute([':group_id' => $groupId]);
            $ranked = $this->rankGroup($tournamentId, $groupId, $rows->fetchAll(PDO::FETCH_ASSOC));
            $qualifiersByGroup[] = array_slice(array_column($ranked, 'user_id'), 0, 2);
            foreach ($ranked as $rank => $row) {
                $this->pdo->prepare(
                    'UPDATE tournament_group_members SET rank_position = :rank, qualified_at = :qualified_at WHERE group_id = :group AND user_id = :user'
                )->execute([
                    ':rank' => $rank + 1,
                    ':qualified_at' => $rank < 2 ? date('Y-m-d H:i:s') : null,
                    ':group' => $groupId,
                    ':user' => (int) $row['user_id'],
                ]);
            }
        }

        if (count($qualifiersByGroup) < 1) {
            return;
        }
        $seeded = $this->seedGroupQualifiers($qualifiersByGroup);

        $tournamentStmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id');
        $tournamentStmt->execute([':id' => $tournamentId]);
        $tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);
        $this->pdo->prepare("UPDATE tournament_groups SET status = 'completed' WHERE tournament_id = :id")
            ->execute([':id' => $tournamentId]);
        $this->pdo->prepare("UPDATE tournaments SET current_round = 1, lifecycle_note = 'Knockout stage generated from group qualifiers.' WHERE id = :id")
            ->execute([':id' => $tournamentId]);
        $this->generateKnockoutBracket($tournament, $seeded, 'knockout');
    }

    private function rankGroup(int $tournamentId, int $groupId, array $rows): array
    {
        usort($rows, function (array $a, array $b) use ($tournamentId, $groupId): int {
            foreach ([
                (int) $b['league_points'] <=> (int) $a['league_points'],
                ((int) $b['goals_for'] - (int) $b['goals_against']) <=> ((int) $a['goals_for'] - (int) $a['goals_against']),
                (int) $b['goals_for'] <=> (int) $a['goals_for'],
                $this->headToHeadPoints($tournamentId, $groupId, (int) $b['user_id'], (int) $a['user_id']),
                (int) $a['user_id'] <=> (int) $b['user_id'],
            ] as $result) {
                if ($result !== 0) return $result;
            }
            return 0;
        });
        return $rows;
    }

    private function headToHeadPoints(int $tournamentId, int $groupId, int $leftUserId, int $rightUserId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT player1_id, player2_id, winner_id, is_draw
             FROM matches
             WHERE tournament_id = :tournament AND group_id = :group
               AND status = 'confirmed'
               AND ((player1_id = :left_user AND player2_id = :right_user)
                    OR (player1_id = :right_user2 AND player2_id = :left_user2))
             LIMIT 1"
        );
        $stmt->execute([
            ':tournament' => $tournamentId,
            ':group' => $groupId,
            ':left_user' => $leftUserId,
            ':right_user' => $rightUserId,
            ':right_user2' => $rightUserId,
            ':left_user2' => $leftUserId,
        ]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$match) return 0;
        if ((int) $match['is_draw'] === 1) return 0;
        return (int) $match['winner_id'] === $leftUserId ? 1 : -1;
    }

    private function seedGroupQualifiers(array $groups): array
    {
        if (count($groups) === 2) {
            return [(int) $groups[0][0], (int) $groups[1][1], (int) $groups[1][0], (int) $groups[0][1]];
        }
        $winners = array_map(static fn(array $group): int => (int) $group[0], $groups);
        $runnersUp = array_map(static fn(array $group): int => (int) $group[1], $groups);
        $seeded = [];
        $count = count($groups);
        for ($i = 0; $i < $count; $i++) {
            $seeded[] = $winners[$i];
            $seeded[] = $runnersUp[($i + 1) % $count];
        }
        return $seeded;
    }

    private function insertMatch(
        array $tournament,
        int $player1,
        int $player2,
        int $round,
        int $position,
        string $stage,
        ?int $groupId = null,
        ?int $seriesId = null,
        ?int $seriesGameNumber = null
    ): int {
        $effectiveStart = !empty($tournament['auto_start_at']) ? (string) $tournament['auto_start_at'] : (string) $tournament['start_date'];
        $startDate = date('Y-m-d', strtotime($effectiveStart) ?: time());
        $days = max(0, $round - 1);
        $hour = 18 + ($position % 3);
        $scheduled = date('Y-m-d H:i:s', strtotime("{$startDate} +{$days} days {$hour}:00:00"));
        $stmt = $this->pdo->prepare(
            "INSERT INTO matches
                (tournament_id, stage, group_id, player1_id, player2_id, round_number,
                 match_number, bracket_position, series_id, series_game_number, status, scheduled_at)
             VALUES
                (:tournament, :stage, :group_id, :player1, :player2, :round,
                 :match_number, :position, :series, :series_game, 'scheduled', :scheduled)"
        );
        $stmt->execute([
            ':tournament' => $tournament['id'],
            ':stage' => $stage,
            ':group_id' => $groupId,
            ':player1' => $player1,
            ':player2' => $player2,
            ':round' => $round,
            ':match_number' => $position,
            ':position' => $position,
            ':series' => $seriesId,
            ':series_game' => $seriesGameNumber,
            ':scheduled' => $scheduled,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function activePlayerIds(int $tournamentId, bool $forUpdate = false): array
    {
        $sql = "SELECT user_id FROM tournament_players
                WHERE tournament_id = :id AND status != 'withdrawn'
                  AND payment_status IN ('not_required','paid')
                ORDER BY joined_at ASC, id ASC";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $playersStmt = $this->pdo->prepare($sql);
        $playersStmt->execute([':id' => $tournamentId]);
        return array_map('intval', $playersStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function insertBracketSlot(int $tournamentId, string $stage, int $round, int $position, int $slot, ?int $userId): void
    {
        $this->pdo->prepare(
            "INSERT IGNORE INTO tournament_bracket_slots
                (tournament_id, stage, round_number, bracket_position, slot_number, user_id)
             VALUES (:tournament, :stage, :round, :position, :slot, :user)"
        )->execute([
            ':tournament' => $tournamentId,
            ':stage' => $stage,
            ':round' => $round,
            ':position' => $position,
            ':slot' => $slot,
            ':user' => $userId,
        ]);
    }

    private function setBracketSlot(
        int $tournamentId,
        string $stage,
        int $round,
        int $position,
        int $slot,
        ?int $userId,
        bool $isBye,
        ?int $sourceMatchId = null
    ): void {
        $this->pdo->prepare(
            "INSERT INTO tournament_bracket_slots
                (tournament_id, stage, round_number, bracket_position, slot_number, user_id, is_bye, source_match_id)
             VALUES (:tournament, :stage, :round, :position, :slot, :user, :bye, :source)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), is_bye = VALUES(is_bye), source_match_id = VALUES(source_match_id)"
        )->execute([
            ':tournament' => $tournamentId,
            ':stage' => $stage,
            ':round' => $round,
            ':position' => $position,
            ':slot' => $slot,
            ':user' => $userId,
            ':bye' => $isBye ? 1 : 0,
            ':source' => $sourceMatchId,
        ]);
    }

    private function remainingMatches(int $tournamentId, ?string $stage = null): int
    {
        $sql = "SELECT COUNT(*) FROM matches WHERE tournament_id = :id
                AND status NOT IN ('confirmed', 'walkover', 'cancelled')";
        $params = [':id' => $tournamentId];
        if ($stage !== null) {
            $sql .= ' AND stage = :stage';
            $params[':stage'] = $stage;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function nextPowerOfTwo(int $value): int
    {
        $power = 1;
        while ($power < $value) {
            $power *= 2;
        }
        return $power;
    }

    private function complete(int $tournamentId, ?int $winnerId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tournaments
             SET status = 'completed', winner_id = :winner_id, completed_at = NOW()
             WHERE id = :id AND status != 'completed'"
        );
        $stmt->execute([':winner_id' => $winnerId, ':id' => $tournamentId]);
        if ($stmt->rowCount() > 0 && $winnerId !== null) {
            $this->pdo->prepare('UPDATE users SET championships = championships + 1 WHERE id = :id')
                ->execute([':id' => $winnerId]);
            (new AchievementService($this->pdo))->evaluate($winnerId);
        }
    }
}
