<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class MatchContactService
{
    private AuditService $audit;

    public function __construct(private readonly PDO $pdo)
    {
        $this->audit = new AuditService($pdo);
    }

    public function availability(int $matchId, int $userId): array
    {
        $data = $this->loadAuthorizedMatch($matchId, $userId, false);
        $opponent = $data['opponent'];
        $available = (int) ($opponent['whatsapp_contact_opt_in'] ?? 0) === 1
            && $this->validE164((string) ($opponent['whatsapp_number'] ?? ''))
            && !$this->isBlocked($userId, (int) $opponent['id']);

        return [
            'available' => $available,
            'reason' => $available ? null : 'Opponent has not enabled WhatsApp contact.',
            'prepared_message' => $this->message($data['match'], $data['tournament'], $opponent),
        ];
    }

    public function redirectToOpponent(int $matchId, int $userId): never
    {
        $data = $this->loadAuthorizedMatch($matchId, $userId, true);
        $opponent = $data['opponent'];
        $number = (string) $opponent['whatsapp_number'];
        $message = $this->message($data['match'], $data['tournament'], $opponent);

        Database::transaction(function () use ($matchId, $userId, $opponent): void {
            $this->pdo->prepare(
                'INSERT INTO match_contact_events (match_id, requester_id, opponent_id)
                 VALUES (:match, :requester, :opponent)'
            )->execute([
                ':match' => $matchId,
                ':requester' => $userId,
                ':opponent' => (int) $opponent['id'],
            ]);
            $this->audit->record($userId, 'match_contact_whatsapp_opened', 'match', $matchId, [
                'opponent_id' => (int) $opponent['id'],
            ]);
        });

        $target = 'https://wa.me/' . ltrim($number, '+') . '?text=' . rawurlencode($message);
        header('Location: ' . $target, true, 302);
        exit;
    }

    public function report(int $reporterId, array $data): array
    {
        $matchId = (int) ($data['match_id'] ?? 0);
        $reportedId = (int) ($data['reported_user_id'] ?? 0);
        $reason = trim((string) ($data['reason'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($matchId < 1 || $reportedId < 1 || $reason === '' || strlen($reason) > 120) {
            throw new HttpException('Match, reported user, and reason are required.', 422);
        }
        $this->loadAuthorizedMatch($matchId, $reporterId, false);
        $this->pdo->prepare(
            'INSERT INTO contact_reports (reporter_id, reported_user_id, match_id, reason, description)
             VALUES (:reporter, :reported, :match, :reason, :description)'
        )->execute([
            ':reporter' => $reporterId,
            ':reported' => $reportedId,
            ':match' => $matchId,
            ':reason' => $reason,
            ':description' => $description !== '' ? mb_substr($description, 0, 5000) : null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->audit->record($reporterId, 'contact_abuse_reported', 'contact_report', $id, [
            'match_id' => $matchId,
            'reported_user_id' => $reportedId,
        ]);
        return ['report_id' => $id];
    }

    private function loadAuthorizedMatch(int $matchId, int $userId, bool $requireContact): array
    {
        if ($matchId < 1) {
            throw new HttpException('Match ID is required.', 422);
        }
        $stmt = $this->pdo->prepare(
            "SELECT m.*, t.name AS tournament_name, t.status AS tournament_status, t.start_date,
                    p1.id AS p1_id, p1.username AS p1_username, p1.whatsapp_number AS p1_whatsapp_number,
                    p1.whatsapp_contact_opt_in AS p1_whatsapp_contact_opt_in, p1.timezone AS p1_timezone,
                    p2.id AS p2_id, p2.username AS p2_username, p2.whatsapp_number AS p2_whatsapp_number,
                    p2.whatsapp_contact_opt_in AS p2_whatsapp_contact_opt_in, p2.timezone AS p2_timezone
             FROM matches m
             JOIN tournaments t ON t.id = m.tournament_id
             JOIN users p1 ON p1.id = m.player1_id
             JOIN users p2 ON p2.id = m.player2_id
             WHERE m.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $matchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new HttpException('Match not found.', 404);
        }
        if ($userId !== (int) $row['player1_id'] && $userId !== (int) $row['player2_id']) {
            throw new HttpException('Only assigned players can contact an opponent.', 403);
        }
        if (!in_array((string) $row['tournament_status'], ['active', 'in_progress'], true)) {
            throw new HttpException('Opponent contact opens after the tournament is in progress.', 409);
        }
        if (in_array((string) $row['status'], ['confirmed', 'cancelled', 'walkover'], true)) {
            throw new HttpException('This match is no longer open for coordination.', 409);
        }

        $opponentSide = $userId === (int) $row['player1_id'] ? 'p2' : 'p1';
        $opponent = [
            'id' => (int) $row[$opponentSide . '_id'],
            'username' => (string) $row[$opponentSide . '_username'],
            'whatsapp_number' => $row[$opponentSide . '_whatsapp_number'],
            'whatsapp_contact_opt_in' => $row[$opponentSide . '_whatsapp_contact_opt_in'],
            'timezone' => $row[$opponentSide . '_timezone'] ?: 'Africa/Dar_es_Salaam',
        ];
        if ($requireContact) {
            if ((int) $opponent['whatsapp_contact_opt_in'] !== 1 || !$this->validE164((string) $opponent['whatsapp_number'])) {
                throw new HttpException('Opponent has not enabled WhatsApp contact.', 409);
            }
            if ($this->isBlocked($userId, (int) $opponent['id'])) {
                throw new HttpException('WhatsApp contact is unavailable for this opponent.', 403);
            }
        }

        return [
            'match' => $row,
            'tournament' => ['name' => (string) $row['tournament_name']],
            'opponent' => $opponent,
        ];
    }

    private function message(array $match, array $tournament, array $opponent): string
    {
        $deadline = $match['scheduled_at'] ?: $match['start_date'];
        $timezone = new DateTimeZone((string) ($opponent['timezone'] ?: 'Africa/Dar_es_Salaam'));
        $formatted = $deadline
            ? (new DateTimeImmutable((string) $deadline))->setTimezone($timezone)->format('M j, Y H:i T')
            : 'the tournament deadline';
        return "Hi {$opponent['username']}, we are matched in \"{$tournament['name']}\" on KICKOFF for Round {$match['round_number']}. Our deadline is {$formatted}. When can we play?";
    }

    private function isBlocked(int $requesterId, int $opponentId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM user_blocks
             WHERE (blocker_id = :requester AND blocked_id = :opponent)
                OR (blocker_id = :opponent2 AND blocked_id = :requester2)
             LIMIT 1'
        );
        $stmt->execute([
            ':requester' => $requesterId,
            ':opponent' => $opponentId,
            ':opponent2' => $opponentId,
            ':requester2' => $requesterId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    private function validE164(string $number): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $number);
    }
}
