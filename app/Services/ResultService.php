<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Support\AvatarCatalog;
use App\Support\ImageUploader;
use PDO;
use Throwable;

final class ResultService
{
    private ImageUploader $uploader;
    private NotificationService $notifications;
    private AchievementService $achievements;
    private TournamentEngine $engine;

    public function __construct(private readonly PDO $pdo)
    {
        $this->uploader = new ImageUploader();
        $this->notifications = new NotificationService($pdo);
        $this->achievements = new AchievementService($pdo);
        $this->engine = new TournamentEngine($pdo);
    }

    public function submit(int $userId, array $data, array $files): array
    {
        $matchId = (int) ($data['match_id'] ?? 0);
        $myScore = (int) ($data['my_score'] ?? -1);
        $opponentScore = (int) ($data['opponent_score'] ?? -1);
        $claim = trim((string) ($data['claimed_result'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $this->validateSubmission($matchId, $myScore, $opponentScore, $claim, $notes);

        $check = $this->pdo->prepare(
            "SELECT m.*, t.format FROM matches m JOIN tournaments t ON t.id = m.tournament_id WHERE m.id = :id"
        );
        $check->execute([':id' => $matchId]);
        $match = $check->fetch(PDO::FETCH_ASSOC);
        if (!$match) throw new HttpException('Match not found.', 404);
        if ((int) $match['player1_id'] !== $userId && (int) $match['player2_id'] !== $userId) {
            throw new HttpException('You may submit a result only for your own match.', 403);
        }
        if (!in_array($match['status'], ['scheduled', 'pending_result'], true)) {
            throw new HttpException('This match is not accepting result submissions.', 409);
        }

        $upload = $this->uploader->uploadResultProof(
            $files['screenshot'] ?? [],
            "match{$matchId}_user{$userId}"
        );

        try {
            return Database::transaction(function () use ($userId, $matchId, $myScore, $opponentScore, $claim, $notes, $upload): array {
                $matchStmt = $this->pdo->prepare(
                    'SELECT m.*, t.format, t.name AS tournament_name
                     FROM matches m JOIN tournaments t ON t.id = m.tournament_id
                     WHERE m.id = :id FOR UPDATE'
                );
                $matchStmt->execute([':id' => $matchId]);
                $match = $matchStmt->fetch(PDO::FETCH_ASSOC);
                if (!$match || !in_array($match['status'], ['scheduled', 'pending_result'], true)) {
                    throw new HttpException('This match is no longer accepting submissions.', 409);
                }
                if ((int) $match['player1_id'] !== $userId && (int) $match['player2_id'] !== $userId) {
                    throw new HttpException('This is not your match.', 403);
                }

                $duplicate = $this->pdo->prepare(
                    'SELECT 1 FROM match_results WHERE match_id = :match AND submitted_by = :user LIMIT 1'
                );
                $duplicate->execute([':match' => $matchId, ':user' => $userId]);
                if ($duplicate->fetchColumn()) {
                    throw new HttpException('You already submitted this match result.', 409);
                }

                $insert = $this->pdo->prepare(
                    'INSERT INTO match_results
                        (match_id, submitted_by, claimed_result, my_score, opponent_score, screenshot_url, notes)
                     VALUES (:match, :user, :claim, :my_score, :opponent_score, :screenshot, :notes)'
                );
                $insert->execute([
                    ':match' => $matchId, ':user' => $userId, ':claim' => $claim,
                    ':my_score' => $myScore, ':opponent_score' => $opponentScore,
                    ':screenshot' => $upload['url'], ':notes' => $notes !== '' ? $notes : null,
                ]);
                $this->pdo->prepare(
                    "UPDATE matches SET status = 'pending_result', played_at = COALESCE(played_at, NOW()) WHERE id = :id"
                )->execute([':id' => $matchId]);

                return $this->verifyLocked($match);
            });
        } catch (Throwable $exception) {
            $this->uploader->deleteByUrl($upload['url']);
            throw $exception;
        }
    }

    public function get(int $matchId, array $actor): array
    {
        $matchStmt = $this->pdo->prepare(
            'SELECT m.*, t.creator_id, t.name AS tournament_name,
                    p1.username AS player1_name, p1.avatar_url AS player1_avatar,
                    p2.username AS player2_name, p2.avatar_url AS player2_avatar
             FROM matches m JOIN tournaments t ON t.id = m.tournament_id
             JOIN users p1 ON p1.id = m.player1_id JOIN users p2 ON p2.id = m.player2_id
             WHERE m.id = :id LIMIT 1'
        );
        $matchStmt->execute([':id' => $matchId]);
        $match = $matchStmt->fetch(PDO::FETCH_ASSOC);
        if (!$match) throw new HttpException('Match not found.', 404);
        $actorId = (int) $actor['id'];
        $authorized = ($actor['role'] ?? '') === 'admin'
            || $actorId === (int) $match['creator_id']
            || $actorId === (int) $match['player1_id']
            || $actorId === (int) $match['player2_id'];
        if (!$authorized) throw new HttpException('You cannot view these private result submissions.', 403);

        $match['player1_avatar_url'] = AvatarCatalog::url($match['player1_avatar'] ?? null);
        $match['player2_avatar_url'] = AvatarCatalog::url($match['player2_avatar'] ?? null);
        unset($match['player1_avatar'], $match['player2_avatar']);

        $resultsStmt = $this->pdo->prepare(
            'SELECT mr.*, u.username AS submitted_by_username, u.avatar_url AS submitted_by_avatar
             FROM match_results mr JOIN users u ON u.id = mr.submitted_by
             WHERE mr.match_id = :id ORDER BY mr.id ASC'
        );
        $resultsStmt->execute([':id' => $matchId]);
        $results = array_map(function (array $row): array {
            $row['submitted_by_avatar_url'] = AvatarCatalog::url($row['submitted_by_avatar'] ?? null);
            unset($row['submitted_by_avatar']);
            return $row;
        }, $resultsStmt->fetchAll(PDO::FETCH_ASSOC));
        return ['match' => $match, 'results' => $results];
    }

    public function resolveDispute(int $adminId, array $data): array
    {
        $disputeId = (int) ($data['dispute_id'] ?? 0);
        $decision = trim((string) ($data['decision'] ?? $data['outcome'] ?? ''));
        $adminNote = trim((string) ($data['admin_note'] ?? ''));
        if ($disputeId < 1) throw new HttpException('Dispute ID is required.', 422);
        if (!in_array($decision, ['player1_wins', 'player2_wins', 'draw', 'replay'], true)) {
            throw new HttpException('Invalid dispute decision.', 422);
        }
        if (strlen($adminNote) > 2000) throw new HttpException('Admin note is too long.', 422);

        $deleteUrls = [];
        $result = Database::transaction(function () use ($adminId, $disputeId, $decision, $adminNote, &$deleteUrls): array {
            $stmt = $this->pdo->prepare(
                'SELECT d.*, m.tournament_id, m.player1_id, m.player2_id, m.status AS match_status, m.stage,
                        t.format, t.name AS tournament_name
                 FROM disputes d JOIN matches m ON m.id = d.match_id JOIN tournaments t ON t.id = m.tournament_id
                 WHERE d.id = :id FOR UPDATE'
            );
            $stmt->execute([':id' => $disputeId]);
            $dispute = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$dispute) throw new HttpException('Dispute not found.', 404);
            if ($dispute['status'] === 'resolved') throw new HttpException('This dispute is already resolved.', 409);
            if ($decision === 'draw' && (
                in_array($dispute['format'], ['1v1', 'full_knockout'], true)
                || (($dispute['stage'] ?? '') === 'knockout')
            )) {
                throw new HttpException('This match requires a winner or replay.', 422);
            }

            $resultsStmt = $this->pdo->prepare('SELECT * FROM match_results WHERE match_id = :id ORDER BY id ASC');
            $resultsStmt->execute([':id' => $dispute['match_id']]);
            $submissions = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($decision === 'replay') {
                $deleteUrls = array_values(array_filter(array_column($submissions, 'screenshot_url')));
                $this->pdo->prepare('DELETE FROM match_results WHERE match_id = :id')->execute([':id' => $dispute['match_id']]);
                $this->pdo->prepare(
                    "UPDATE matches SET status = 'scheduled', player1_score = NULL, player2_score = NULL,
                        winner_id = NULL, is_draw = 0, played_at = NULL, confirmed_at = NULL
                     WHERE id = :id"
                )->execute([':id' => $dispute['match_id']]);
            } else {
                [$player1Score, $player2Score, $winnerId, $isDraw] = $this->decisionScore($decision, $dispute, $submissions, $data);
                $this->pdo->prepare(
                    "UPDATE matches SET status = 'confirmed', player1_score = :p1, player2_score = :p2,
                        winner_id = :winner, is_draw = :draw, played_at = COALESCE(played_at, NOW()), confirmed_at = NOW()
                     WHERE id = :id"
                )->execute([
                    ':p1' => $player1Score, ':p2' => $player2Score, ':winner' => $winnerId,
                    ':draw' => $isDraw, ':id' => $dispute['match_id'],
                ]);
                $this->pdo->prepare(
                    "UPDATE match_results SET verification_status = 'confirmed' WHERE match_id = :id"
                )->execute([':id' => $dispute['match_id']]);
                $match = array_merge($dispute, [
                    'id' => (int) $dispute['match_id'],
                    'player1_score' => $player1Score,
                    'player2_score' => $player2Score,
                    'winner_id' => $winnerId,
                    'is_draw' => $isDraw,
                ]);
                $this->applyStatistics($match);
                $this->engine->progressAfterConfirmedMatch((int) $dispute['match_id']);
            }

            $this->pdo->prepare(
                "UPDATE disputes SET status = 'resolved', outcome = :outcome, admin_note = :note,
                    resolved_by = :admin, resolved_at = NOW() WHERE id = :id"
            )->execute([
                ':outcome' => $decision, ':note' => $adminNote !== '' ? $adminNote : null,
                ':admin' => $adminId, ':id' => $disputeId,
            ]);
            foreach ([(int) $dispute['player1_id'], (int) $dispute['player2_id']] as $playerId) {
                $this->notifications->create(
                    $playerId,
                    'Dispute resolved',
                    "An administrator resolved your result in {$dispute['tournament_name']}: " . str_replace('_', ' ', $decision) . '.',
                    'dispute_resolved',
                    "tournament_detail.html?id={$dispute['tournament_id']}",
                    $adminId
                );
            }
            return ['decision' => $decision, 'match_id' => (int) $dispute['match_id']];
        });
        foreach ($deleteUrls as $url) $this->uploader->deleteByUrl((string) $url);
        return $result;
    }

    private function verifyLocked(array $match): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM match_results WHERE match_id = :id ORDER BY id ASC');
        $stmt->execute([':id' => $match['id']]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($results) < 2) {
            return ['verification' => ['status' => 'pending', 'message' => 'Waiting for your opponent to submit.']];
        }

        $byUser = [];
        foreach ($results as $result) $byUser[(int) $result['submitted_by']] = $result;
        $player1Result = $byUser[(int) $match['player1_id']] ?? null;
        $player2Result = $byUser[(int) $match['player2_id']] ?? null;
        $scoresMatch = $player1Result && $player2Result
            && (int) $player1Result['my_score'] === (int) $player2Result['opponent_score']
            && (int) $player1Result['opponent_score'] === (int) $player2Result['my_score'];
        $claimsMatch = $player1Result && $player2Result && (
            ($player1Result['claimed_result'] === 'win' && $player2Result['claimed_result'] === 'loss')
            || ($player1Result['claimed_result'] === 'loss' && $player2Result['claimed_result'] === 'win')
            || ($player1Result['claimed_result'] === 'draw' && $player2Result['claimed_result'] === 'draw')
        );
        $drawNotAllowed = (
            in_array($match['format'], ['1v1', 'full_knockout'], true)
            || ($match['format'] === 'group_knockout' && ($match['stage'] ?? '') === 'knockout')
        )
            && $player1Result
            && (int) $player1Result['my_score'] === (int) $player1Result['opponent_score'];

        if (!$scoresMatch || !$claimsMatch || $drawNotAllowed) {
            $reason = $drawNotAllowed ? 'This match requires a winner or replay' : 'Conflicting result submissions';
            $this->pdo->prepare("UPDATE matches SET status = 'disputed' WHERE id = :id")->execute([':id' => $match['id']]);
            $this->pdo->prepare("UPDATE match_results SET verification_status = 'conflicted' WHERE match_id = :id")
                ->execute([':id' => $match['id']]);
            $this->pdo->prepare(
                "INSERT IGNORE INTO disputes (match_id, raised_by, reason, status)
                 VALUES (:match, :raised_by, :reason, 'open')"
            )->execute([':match' => $match['id'], ':raised_by' => $results[1]['submitted_by'], ':reason' => $reason]);
            foreach ([(int) $match['player1_id'], (int) $match['player2_id']] as $playerId) {
                $this->notifications->create(
                    $playerId, 'Result requires review', $reason . '.', 'result_disputed',
                    "tournament_detail.html?id={$match['tournament_id']}"
                );
            }
            $admins = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND status != 'banned'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                $this->notifications->create(
                    (int) $adminId, 'New result dispute',
                    "A match in {$match['tournament_name']} needs review.",
                    'result_disputed', 'admin_disputes.html'
                );
            }
            return ['verification' => ['status' => 'conflicted', 'message' => 'The submissions conflict and were sent to an administrator.']];
        }

        $player1Score = (int) $player1Result['my_score'];
        $player2Score = (int) $player1Result['opponent_score'];
        $winnerId = $player1Score === $player2Score ? null
            : ($player1Score > $player2Score ? (int) $match['player1_id'] : (int) $match['player2_id']);
        $isDraw = $winnerId === null ? 1 : 0;
        $this->pdo->prepare(
            "UPDATE matches SET status = 'confirmed', player1_score = :p1, player2_score = :p2,
                winner_id = :winner, is_draw = :draw, confirmed_at = NOW() WHERE id = :id"
        )->execute([':p1' => $player1Score, ':p2' => $player2Score, ':winner' => $winnerId, ':draw' => $isDraw, ':id' => $match['id']]);
        $this->pdo->prepare("UPDATE match_results SET verification_status = 'confirmed' WHERE match_id = :id")
            ->execute([':id' => $match['id']]);

        $confirmed = array_merge($match, [
            'player1_score' => $player1Score, 'player2_score' => $player2Score,
            'winner_id' => $winnerId, 'is_draw' => $isDraw,
        ]);
        $this->applyStatistics($confirmed);
        $this->engine->progressAfterConfirmedMatch((int) $match['id']);
        foreach ([(int) $match['player1_id'], (int) $match['player2_id']] as $playerId) {
            $this->notifications->create(
                $playerId, 'Result confirmed',
                "Your result in {$match['tournament_name']} was confirmed.",
                'result_confirmed', "tournament_detail.html?id={$match['tournament_id']}"
            );
        }
        return ['verification' => ['status' => 'confirmed', 'message' => 'Both submissions match. Result confirmed.']];
    }

    private function applyStatistics(array $match): void
    {
        $player1 = (int) $match['player1_id'];
        $player2 = (int) $match['player2_id'];
        $winner = $match['winner_id'] !== null ? (int) $match['winner_id'] : null;
        $draw = (bool) $match['is_draw'];
        $result1 = $draw ? 'draw' : ($winner === $player1 ? 'win' : 'loss');
        $result2 = $draw ? 'draw' : ($winner === $player2 ? 'win' : 'loss');
        $this->updateUserStats($player1, $result1);
        $this->updateUserStats($player2, $result2);
        $this->updateTournamentStats((int) $match['tournament_id'], $player1, $result1, (int) $match['player1_score'], (int) $match['player2_score'], $match['format'], (string) ($match['stage'] ?? ''));
        $this->updateTournamentStats((int) $match['tournament_id'], $player2, $result2, (int) $match['player2_score'], (int) $match['player1_score'], $match['format'], (string) ($match['stage'] ?? ''));
        $this->achievements->evaluate($player1);
        $this->achievements->evaluate($player2);
    }

    private function updateUserStats(int $userId, string $result): void
    {
        $column = match ($result) {'win' => 'wins', 'loss' => 'losses', default => 'draws'};
        $points = match ($result) {'win' => POINTS_WIN, 'loss' => POINTS_LOSS, default => POINTS_DRAW};
        $this->pdo->prepare(
            "UPDATE users SET {$column} = {$column} + 1, total_matches = total_matches + 1, points = points + :points WHERE id = :id"
        )->execute([':points' => $points, ':id' => $userId]);
    }

    private function updateTournamentStats(int $tournamentId, int $userId, string $result, int $goalsFor, int $goalsAgainst, string $format, string $stage): void
    {
        $column = match ($result) {'win' => 'wins', 'loss' => 'losses', default => 'draws'};
        $leaguePoints = ($format === 'group_knockout' && $stage === 'group') ? match ($result) {'win' => 3, 'draw' => 1, default => 0} : 0;
        $this->pdo->prepare(
            "UPDATE tournament_players SET {$column} = {$column} + 1,
                league_points = league_points + :league_points, goals_for = goals_for + :goals_for,
                goals_against = goals_against + :goals_against
             WHERE tournament_id = :tournament AND user_id = :user"
        )->execute([
            ':league_points' => $leaguePoints, ':goals_for' => $goalsFor, ':goals_against' => $goalsAgainst,
            ':tournament' => $tournamentId, ':user' => $userId,
        ]);
    }

    private function decisionScore(string $decision, array $match, array $submissions, array $data): array
    {
        $requestedP1 = isset($data['player1_score']) ? (int) $data['player1_score'] : null;
        $requestedP2 = isset($data['player2_score']) ? (int) $data['player2_score'] : null;
        if ($requestedP1 !== null && ($requestedP1 < 0 || $requestedP1 > 99 || $requestedP2 === null || $requestedP2 < 0 || $requestedP2 > 99)) {
            throw new HttpException('Administrator scores must be between 0 and 99.', 422);
        }
        $p1 = $requestedP1;
        $p2 = $requestedP2;
        if ($p1 === null) {
            foreach ($submissions as $submission) {
                if ((int) $submission['submitted_by'] === (int) $match['player1_id']) {
                    $p1 = (int) $submission['my_score'];
                    $p2 = (int) $submission['opponent_score'];
                    break;
                }
            }
        }
        $p1 ??= 0;
        $p2 ??= 0;
        if ($decision === 'draw') {
            $score = min($p1, $p2);
            return [$score, $score, null, 1];
        }
        if ($decision === 'player1_wins') {
            if ($p1 <= $p2) $p1 = $p2 + 1;
            return [$p1, $p2, (int) $match['player1_id'], 0];
        }
        if ($p2 <= $p1) $p2 = $p1 + 1;
        return [$p1, $p2, (int) $match['player2_id'], 0];
    }

    private function validateSubmission(int $matchId, int $myScore, int $opponentScore, string $claim, string $notes): void
    {
        if ($matchId < 1) throw new HttpException('Select a valid match.', 422);
        if ($myScore < 0 || $myScore > 99 || $opponentScore < 0 || $opponentScore > 99) {
            throw new HttpException('Scores must be between 0 and 99.', 422);
        }
        if (!in_array($claim, ['win', 'loss', 'draw'], true)) throw new HttpException('Select win, loss, or draw.', 422);
        $expected = $myScore === $opponentScore ? 'draw' : ($myScore > $opponentScore ? 'win' : 'loss');
        if ($claim !== $expected) throw new HttpException('Selected result does not match the entered score.', 422);
        if (strlen($notes) > 1000) throw new HttpException('Notes must be 1000 characters or fewer.', 422);
    }
}
