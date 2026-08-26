<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\AvatarCatalog;
use PDO;

final class NotificationController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function list(Request $request): never
    {
        $request->requireMethod('GET');
        $user = Auth::requireUser();
        $unreadOnly = (string) $request->query('unread_only', '') === '1';
        $stmt = $this->pdo->prepare(
            'SELECT n.*, actor.username AS actor_username, actor.avatar_url AS actor_avatar
             FROM notifications n LEFT JOIN users actor ON actor.id = n.actor_id
             WHERE n.user_id = :user ' . ($unreadOnly ? 'AND n.is_read = 0 ' : '') .
            'ORDER BY n.created_at DESC, n.id DESC LIMIT 100'
        );
        $stmt->execute([':user' => $user['id']]);
        $notifications = array_map(function (array $row): array {
            $row['actor_avatar_url'] = $row['actor_id'] ? AvatarCatalog::url($row['actor_avatar'] ?? null) : null;
            unset($row['actor_avatar']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user AND is_read = 0');
        $count->execute([':user' => $user['id']]);
        Response::success(['notifications' => $notifications, 'unread_count' => (int) $count->fetchColumn()]);
    }

    public function markRead(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireUser();
        $id = $request->input('id', 'all');
        if ($id === 'all') {
            $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user AND is_read = 0');
            $stmt->execute([':user' => $user['id']]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user');
            $stmt->execute([':id' => (int) $id, ':user' => $user['id']]);
        }
        Response::success(['updated' => $stmt->rowCount()], 'Notifications updated.');
    }
}

