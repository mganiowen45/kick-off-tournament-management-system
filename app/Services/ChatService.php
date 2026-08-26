<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Support\AvatarCatalog;
use PDO;

final class ChatService
{
    private NotificationService $notifications;

    public function __construct(private readonly PDO $pdo)
    {
        $this->notifications = new NotificationService($pdo);
    }

    public function send(int $userId, array $data): array
    {
        $type = trim((string) ($data['type'] ?? 'direct'));
        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 2000) {
            throw new HttpException('Message must contain 1 to 2000 characters.', 422);
        }

        if ($type === 'direct') {
            $receiverId = (int) ($data['receiver_id'] ?? 0);
            if ($receiverId < 1 || $receiverId === $userId) {
                throw new HttpException('Select a valid recipient.', 422);
            }
            $receiver = $this->pdo->prepare("SELECT id FROM users WHERE id = :id AND status != 'banned'");
            $receiver->execute([':id' => $receiverId]);
            if (!$receiver->fetchColumn()) throw new HttpException('Recipient not found.', 404);

            $insert = $this->pdo->prepare(
                "INSERT INTO messages (sender_id, receiver_id, type, content)
                 VALUES (:sender, :receiver, 'direct', :content)"
            );
            $insert->execute([':sender' => $userId, ':receiver' => $receiverId, ':content' => $content]);
            $sender = $this->pdo->prepare('SELECT username FROM users WHERE id = :id');
            $sender->execute([':id' => $userId]);
            $username = (string) $sender->fetchColumn();
            $this->notifications->create(
                $receiverId,
                "New message from {$username}",
                mb_substr($content, 0, 180),
                'new_message',
                "chat.html?with={$userId}",
                $userId
            );
            return ['message_id' => (int) $this->pdo->lastInsertId()];
        }

        if ($type !== 'group') throw new HttpException('Invalid message type.', 422);
        $tournamentId = (int) ($data['tournament_id'] ?? 0);
        $this->requireMembership($tournamentId, $userId);
        $insert = $this->pdo->prepare(
            "INSERT INTO messages (sender_id, tournament_id, type, content)
             VALUES (:sender, :tournament, 'group', :content)"
        );
        $insert->execute([':sender' => $userId, ':tournament' => $tournamentId, ':content' => $content]);
        return ['message_id' => (int) $this->pdo->lastInsertId()];
    }

    public function conversation(int $userId, int $otherId, int $afterId = 0, int $limit = 50): array
    {
        if ($otherId < 1 || $otherId === $userId) throw new HttpException('Select a valid player.', 422);
        $otherStmt = $this->pdo->prepare(
            "SELECT id, username, country, status, avatar_url FROM users WHERE id = :id AND status != 'banned' LIMIT 1"
        );
        $otherStmt->execute([':id' => $otherId]);
        $other = $otherStmt->fetch(PDO::FETCH_ASSOC);
        if (!$other) throw new HttpException('Player not found.', 404);

        $limit = min(100, max(1, $limit));
        if ($afterId > 0) {
            $stmt = $this->pdo->prepare(
                "SELECT m.*, sender.username AS sender_username, sender.avatar_url AS sender_avatar
                 FROM messages m JOIN users sender ON sender.id = m.sender_id
                 WHERE m.type = 'direct' AND m.id > :after_id
                   AND ((m.sender_id = :user1 AND m.receiver_id = :other1)
                     OR (m.sender_id = :other2 AND m.receiver_id = :user2))
                 ORDER BY m.id ASC LIMIT :limit"
            );
            $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT recent.* FROM (
                    SELECT m.*, sender.username AS sender_username, sender.avatar_url AS sender_avatar
                    FROM messages m JOIN users sender ON sender.id = m.sender_id
                    WHERE m.type = 'direct'
                      AND ((m.sender_id = :user1 AND m.receiver_id = :other1)
                        OR (m.sender_id = :other2 AND m.receiver_id = :user2))
                    ORDER BY m.id DESC LIMIT :limit
                 ) recent ORDER BY recent.id ASC"
            );
        }
        $stmt->bindValue(':user1', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':other1', $otherId, PDO::PARAM_INT);
        $stmt->bindValue(':other2', $otherId, PDO::PARAM_INT);
        $stmt->bindValue(':user2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $messages = array_map([$this, 'decorateMessage'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        $this->pdo->prepare(
            "UPDATE messages SET is_read = 1
             WHERE type = 'direct' AND sender_id = :other AND receiver_id = :user AND is_read = 0"
        )->execute([':other' => $otherId, ':user' => $userId]);

        return ['messages' => $messages, 'other_user' => AvatarCatalog::decorate($other)];
    }

    public function group(int $userId, int $tournamentId, int $afterId = 0, int $limit = 50): array
    {
        $this->requireMembership($tournamentId, $userId);
        $tournamentStmt = $this->pdo->prepare('SELECT id, name, status, format FROM tournaments WHERE id = :id');
        $tournamentStmt->execute([':id' => $tournamentId]);
        $tournament = $tournamentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$tournament) throw new HttpException('Tournament not found.', 404);

        $limit = min(100, max(1, $limit));
        if ($afterId > 0) {
            $stmt = $this->pdo->prepare(
                "SELECT m.*, sender.username AS sender_username, sender.country, sender.avatar_url AS sender_avatar
                 FROM messages m JOIN users sender ON sender.id = m.sender_id
                 WHERE m.type = 'group' AND m.tournament_id = :tournament AND m.id > :after_id
                 ORDER BY m.id ASC LIMIT :limit"
            );
            $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT recent.* FROM (
                    SELECT m.*, sender.username AS sender_username, sender.country, sender.avatar_url AS sender_avatar
                    FROM messages m JOIN users sender ON sender.id = m.sender_id
                    WHERE m.type = 'group' AND m.tournament_id = :tournament
                    ORDER BY m.id DESC LIMIT :limit
                 ) recent ORDER BY recent.id ASC"
            );
        }
        $stmt->bindValue(':tournament', $tournamentId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $messages = array_map([$this, 'decorateMessage'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $lastMessageId = $messages ? max(array_map(static fn(array $m): int => (int) $m['id'], $messages)) : $afterId;
        $this->pdo->prepare(
            'INSERT INTO group_message_reads (user_id, tournament_id, last_message_id)
             VALUES (:user, :tournament, :last_id)
             ON DUPLICATE KEY UPDATE last_message_id = GREATEST(last_message_id, VALUES(last_message_id)), read_at = NOW()'
        )->execute([':user' => $userId, ':tournament' => $tournamentId, ':last_id' => $lastMessageId]);

        $membersStmt = $this->pdo->prepare(
            "SELECT u.id, u.username, u.country, u.avatar_url, tp.status
             FROM tournament_players tp JOIN users u ON u.id = tp.user_id
             WHERE tp.tournament_id = :id AND tp.status != 'withdrawn' ORDER BY tp.joined_at ASC"
        );
        $membersStmt->execute([':id' => $tournamentId]);
        $members = array_map(static fn(array $row): array => AvatarCatalog::decorate($row), $membersStmt->fetchAll(PDO::FETCH_ASSOC));
        return ['messages' => $messages, 'tournament' => $tournament, 'members' => $members];
    }

    public function inbox(int $userId): array
    {
        $direct = $this->pdo->prepare(
            "SELECT m.*, other_user.id AS other_user_id, other_user.username AS other_username,
                    other_user.avatar_url AS other_avatar,
                    (SELECT COUNT(*) FROM messages unread
                     WHERE unread.type = 'direct' AND unread.sender_id = other_user.id
                       AND unread.receiver_id = :unread_user AND unread.is_read = 0) AS unread_count
             FROM messages m
             JOIN users other_user ON other_user.id = CASE WHEN m.sender_id = :case_user THEN m.receiver_id ELSE m.sender_id END
             WHERE m.type = 'direct' AND (m.sender_id = :sender_user OR m.receiver_id = :receiver_user)
               AND m.id = (
                 SELECT MAX(latest.id) FROM messages latest
                 WHERE latest.type = 'direct'
                   AND ((latest.sender_id = :latest_user1 AND latest.receiver_id = other_user.id)
                     OR (latest.sender_id = other_user.id AND latest.receiver_id = :latest_user2))
               )
             ORDER BY m.id DESC"
        );
        $direct->execute([
            ':unread_user' => $userId, ':case_user' => $userId, ':sender_user' => $userId,
            ':receiver_user' => $userId, ':latest_user1' => $userId, ':latest_user2' => $userId,
        ]);
        $directRows = array_map(function (array $row): array {
            $row['other_avatar_url'] = AvatarCatalog::url($row['other_avatar'] ?? null);
            unset($row['other_avatar']);
            return $row;
        }, $direct->fetchAll(PDO::FETCH_ASSOC));

        $groups = $this->pdo->prepare(
            "SELECT t.id AS tournament_id, t.name AS tournament_name,
                    latest.id, latest.content, latest.sent_at, sender.username AS last_sender_username,
                    sender.avatar_url AS last_sender_avatar,
                    (SELECT COUNT(*) FROM messages unread
                     WHERE unread.type = 'group' AND unread.tournament_id = t.id
                       AND unread.sender_id != :unread_user
                       AND unread.id > COALESCE(read_state.last_message_id, 0)) AS unread_count
             FROM tournament_players tp JOIN tournaments t ON t.id = tp.tournament_id
             LEFT JOIN group_message_reads read_state ON read_state.user_id = :read_user AND read_state.tournament_id = t.id
             LEFT JOIN messages latest ON latest.id = (
                 SELECT MAX(group_latest.id) FROM messages group_latest
                 WHERE group_latest.type = 'group' AND group_latest.tournament_id = t.id
             )
             LEFT JOIN users sender ON sender.id = latest.sender_id
             WHERE tp.user_id = :member_user AND tp.status != 'withdrawn'
             ORDER BY COALESCE(latest.id, 0) DESC, t.created_at DESC"
        );
        $groups->execute([':unread_user' => $userId, ':read_user' => $userId, ':member_user' => $userId]);
        $groupRows = array_map(function (array $row): array {
            $row['last_sender_avatar_url'] = AvatarCatalog::url($row['last_sender_avatar'] ?? null);
            unset($row['last_sender_avatar']);
            return $row;
        }, $groups->fetchAll(PDO::FETCH_ASSOC));
        return ['direct' => $directRows, 'groups' => $groupRows];
    }

    public function markDirectRead(int $userId, int $senderId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE messages SET is_read = 1
             WHERE type = 'direct' AND sender_id = :sender AND receiver_id = :user AND is_read = 0"
        );
        $stmt->execute([':sender' => $senderId, ':user' => $userId]);
        return $stmt->rowCount();
    }

    private function requireMembership(int $tournamentId, int $userId): void
    {
        if ($tournamentId < 1) throw new HttpException('Tournament ID is required.', 422);
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM tournament_players
             WHERE tournament_id = :tournament AND user_id = :user AND status != 'withdrawn' LIMIT 1"
        );
        $stmt->execute([':tournament' => $tournamentId, ':user' => $userId]);
        if (!$stmt->fetchColumn()) throw new HttpException('You are not a member of this tournament.', 403);
    }

    private function decorateMessage(array $row): array
    {
        $row['sender_avatar_url'] = AvatarCatalog::url($row['sender_avatar'] ?? null);
        unset($row['sender_avatar']);
        return $row;
    }
}

