<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class MatchSchedulingService
{
    private NotificationService $notifications;
    private AuditService $audit;

    public function __construct(private readonly PDO $pdo)
    {
        $this->notifications = new NotificationService($pdo);
        $this->audit = new AuditService($pdo);
    }

    public function propose(int $userId, array $data): array
    {
        $matchId = (int) ($data['match_id'] ?? 0);
        $date = trim((string) ($data['date'] ?? ''));
        $time = trim((string) ($data['time'] ?? ''));
        $timezone = trim((string) ($data['timezone'] ?? 'Africa/Dar_es_Salaam'));
        $note = trim((string) ($data['note'] ?? ''));
        if ($matchId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new HttpException('Match, date, and time are required.', 422);
        }
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new HttpException('Select a valid timezone.', 422);
        }
        if (strlen($note) > 500) {
            throw new HttpException('Schedule note must be 500 characters or fewer.', 422);
        }
        $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", new DateTimeZone($timezone));
        if (!$local) {
            throw new HttpException('Enter a valid proposed match time.', 422);
        }
        $utc = $local->setTimezone(new DateTimeZone('UTC'));
        if ($utc <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new HttpException('Scheduled match time cannot be in the past.', 422);
        }

        return Database::transaction(function () use ($userId, $matchId, $timezone, $note, $utc): array {
            $match = $this->loadMatchForParticipant($matchId, $userId, true);
            $deadline = $this->deadlineUtc($match);
            if ($deadline !== null && $utc > $deadline) {
                throw new HttpException('Scheduled time cannot be after the match deadline.', 422);
            }
            $opponentId = $this->opponentId($match, $userId);

            $existing = $this->pdo->prepare('SELECT * FROM match_schedules WHERE match_id = :match FOR UPDATE');
            $existing->execute([':match' => $matchId]);
            $schedule = $existing->fetch(PDO::FETCH_ASSOC);
            if ($schedule && (int) $schedule['proposed_by'] === $userId && $schedule['status'] === 'proposed') {
                throw new HttpException('Wait for your opponent to respond before proposing another time.', 409);
            }
            if ($schedule) {
                $this->pdo->prepare(
                    "UPDATE match_schedules
                     SET status = 'proposed', proposed_by = :proposed_by, proposed_start_utc = :starts,
                         display_timezone = :tz, note = :note, proposed_at = NOW(), confirmed_by = NULL,
                         confirmed_at = NULL, rejected_by = NULL, rejected_at = NULL, rejection_reason = NULL,
                         reschedule_count = reschedule_count + 1
                     WHERE match_id = :match"
                )->execute([
                    ':proposed_by' => $userId,
                    ':starts' => $utc->format('Y-m-d H:i:s'),
                    ':tz' => $timezone,
                    ':note' => $note !== '' ? $note : null,
                    ':match' => $matchId,
                ]);
            } else {
                $this->pdo->prepare(
                    'INSERT INTO match_schedules (match_id, proposed_by, proposed_start_utc, display_timezone, note)
                     VALUES (:match, :proposed_by, :starts, :tz, :note)'
                )->execute([
                    ':match' => $matchId,
                    ':proposed_by' => $userId,
                    ':starts' => $utc->format('Y-m-d H:i:s'),
                    ':tz' => $timezone,
                    ':note' => $note !== '' ? $note : null,
                ]);
            }
            $this->notifications->create(
                $opponentId,
                'Match time proposed',
                "Your opponent proposed {$utc->format('M j, Y H:i')} UTC for {$match['tournament_name']}.",
                'match_time_proposed',
                "tournament_detail.html?id={$match['tournament_id']}",
                $userId
            );
            $this->audit->record($userId, 'match_time_proposed', 'match', $matchId, [
                'proposed_start_utc' => $utc->format('Y-m-d H:i:s'),
                'timezone' => $timezone,
            ]);
            return $this->get($matchId, $userId);
        });
    }

    public function confirm(int $userId, int $matchId): array
    {
        return Database::transaction(function () use ($userId, $matchId): array {
            $match = $this->loadMatchForParticipant($matchId, $userId, true);
            $schedule = $this->loadScheduleForUpdate($matchId);
            if (!$schedule || $schedule['status'] !== 'proposed') {
                throw new HttpException('No pending match time proposal exists.', 409);
            }
            if ((int) $schedule['proposed_by'] === $userId) {
                throw new HttpException('The opponent must confirm your proposed time.', 409);
            }
            $this->pdo->prepare(
                "UPDATE match_schedules SET status = 'confirmed', confirmed_by = :user, confirmed_at = NOW()
                 WHERE match_id = :match"
            )->execute([':user' => $userId, ':match' => $matchId]);
            $this->pdo->prepare('UPDATE matches SET scheduled_at = :starts WHERE id = :match')
                ->execute([':starts' => $schedule['proposed_start_utc'], ':match' => $matchId]);
            $this->notifications->create(
                (int) $schedule['proposed_by'],
                'Match time confirmed',
                "Your match time for {$match['tournament_name']} was confirmed.",
                'match_time_confirmed',
                "tournament_detail.html?id={$match['tournament_id']}",
                $userId
            );
            $this->audit->record($userId, 'match_time_confirmed', 'match', $matchId);
            return $this->get($matchId, $userId);
        });
    }

    public function reject(int $userId, array $data): array
    {
        $matchId = (int) ($data['match_id'] ?? 0);
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($matchId < 1) {
            throw new HttpException('Match ID is required.', 422);
        }
        if (strlen($reason) > 500) {
            throw new HttpException('Rejection reason must be 500 characters or fewer.', 422);
        }
        return Database::transaction(function () use ($userId, $matchId, $reason): array {
            $match = $this->loadMatchForParticipant($matchId, $userId, true);
            $schedule = $this->loadScheduleForUpdate($matchId);
            if (!$schedule || $schedule['status'] !== 'proposed') {
                throw new HttpException('No pending match time proposal exists.', 409);
            }
            if ((int) $schedule['proposed_by'] === $userId) {
                throw new HttpException('The opponent must reject your proposed time.', 409);
            }
            $this->pdo->prepare(
                "UPDATE match_schedules
                 SET status = 'rejected', rejected_by = :user, rejected_at = NOW(), rejection_reason = :reason
                 WHERE match_id = :match"
            )->execute([
                ':user' => $userId,
                ':reason' => $reason !== '' ? $reason : null,
                ':match' => $matchId,
            ]);
            $this->notifications->create(
                (int) $schedule['proposed_by'],
                'Match time rejected',
                "Your proposed time for {$match['tournament_name']} was rejected. Coordinate another time.",
                'match_time_rejected',
                "tournament_detail.html?id={$match['tournament_id']}",
                $userId
            );
            $this->audit->record($userId, 'match_time_rejected', 'match', $matchId);
            return $this->get($matchId, $userId);
        });
    }

    public function get(int $matchId, int $userId): array
    {
        $this->loadMatchForParticipant($matchId, $userId, false);
        $stmt = $this->pdo->prepare(
            "SELECT ms.*, proposer.username AS proposed_by_username, confirmer.username AS confirmed_by_username,
                    rejecter.username AS rejected_by_username
             FROM match_schedules ms
             JOIN users proposer ON proposer.id = ms.proposed_by
             LEFT JOIN users confirmer ON confirmer.id = ms.confirmed_by
             LEFT JOIN users rejecter ON rejecter.id = ms.rejected_by
             WHERE ms.match_id = :match LIMIT 1"
        );
        $stmt->execute([':match' => $matchId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['schedule' => $schedule ?: ['match_id' => $matchId, 'status' => 'not_scheduled']];
    }

    private function loadMatchForParticipant(int $matchId, int $userId, bool $lock): array
    {
        $sql = "SELECT m.*, t.name AS tournament_name, t.status AS tournament_status, t.start_date
                FROM matches m JOIN tournaments t ON t.id = m.tournament_id
                WHERE m.id = :id" . ($lock ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $matchId]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$match) {
            throw new HttpException('Match not found.', 404);
        }
        if ($userId !== (int) $match['player1_id'] && $userId !== (int) $match['player2_id']) {
            throw new HttpException('Only assigned players can schedule this match.', 403);
        }
        if (in_array((string) $match['status'], ['confirmed', 'cancelled', 'walkover'], true)) {
            throw new HttpException('This match is no longer open for scheduling.', 409);
        }
        return $match;
    }

    private function loadScheduleForUpdate(int $matchId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM match_schedules WHERE match_id = :match FOR UPDATE');
        $stmt->execute([':match' => $matchId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        return $schedule ?: null;
    }

    private function opponentId(array $match, int $userId): int
    {
        return $userId === (int) $match['player1_id'] ? (int) $match['player2_id'] : (int) $match['player1_id'];
    }

    private function deadlineUtc(array $match): ?DateTimeImmutable
    {
        $deadline = $match['scheduled_at'] ?: $match['start_date'];
        return $deadline ? new DateTimeImmutable((string) $deadline, new DateTimeZone('UTC')) : null;
    }
}
