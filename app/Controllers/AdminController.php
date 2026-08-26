<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\NotificationService;
use App\Services\TournamentCatalogService;
use App\Services\TournamentService;
use App\Support\AvatarCatalog;
use PDO;

final class AdminController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function stats(Request $request): never
    {
        $request->requireMethod('GET');
        Auth::requireAdmin();
        $row = $this->pdo->query(
            "SELECT
              (SELECT COUNT(*) FROM users WHERE role = 'player') AS totalUsers,
              (SELECT COUNT(*) FROM tournaments WHERE status IN ('open', 'active')) AS activeTourn,
              (SELECT COUNT(*) FROM disputes WHERE status IN ('open', 'under_review')) AS openDisputes,
              (SELECT COUNT(*) FROM matches WHERE DATE(played_at) = CURDATE()) AS matchesToday,
              (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) AS newUsersToday,
              (SELECT COUNT(*) FROM users WHERE status = 'banned') AS bannedUsers,
              (SELECT COUNT(*) FROM matches WHERE played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS matchesThisWeek,
              (SELECT COUNT(*) FROM matches m WHERE m.status = 'confirmed'
                AND NOT EXISTS (SELECT 1 FROM disputes d WHERE d.match_id = m.id)) AS autoConfirmed"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach ($row as $key => $value) $row[$key] = (int) $value;

        $weekly = $this->pdo->query(
            'SELECT DATE(played_at) AS day, COUNT(*) AS count FROM matches
             WHERE played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND played_at IS NOT NULL
             GROUP BY DATE(played_at) ORDER BY day ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $activity = $this->pdo->query(
            "SELECT * FROM (
                SELECT 'match_confirmed' AS type, CONCAT('Match confirmed: #', id) AS detail, confirmed_at AS time
                  FROM matches WHERE status = 'confirmed'
                UNION ALL
                SELECT 'dispute_raised', CONCAT('Dispute raised: DISP-', id), created_at FROM disputes
                UNION ALL
                SELECT 'new_user', CONCAT('New user registered: ', username), created_at FROM users WHERE role = 'player'
             ) activity WHERE time IS NOT NULL ORDER BY time DESC LIMIT 15"
        )->fetchAll(PDO::FETCH_ASSOC);
        Response::success(array_merge($row, ['weekly' => $weekly, 'activity' => $activity]));
    }

    public function users(Request $request): never
    {
        $request->requireMethod('GET');
        Auth::requireAdmin();
        ['page' => $page, 'limit' => $limit, 'offset' => $offset] = $request->pagination();
        $where = ["role = 'player'"];
        $params = [];
        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            if (!in_array($status, ['active', 'warned', 'banned'], true)) throw new HttpException('Invalid account status.', 422);
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $term = '%' . addcslashes(mb_substr($search, 0, 80), '%_\\') . '%';
            $where[] = '(username LIKE :s1 OR email LIKE :s2 OR country LIKE :s3)';
            $params += [':s1' => $term, ':s2' => $term, ':s3' => $term];
        }
        $whereSql = implode(' AND ', $where);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, first_name, last_name, country, preferred_game, status, points,
                    wins, losses, draws, total_matches, championships, avatar_url, created_at, last_login
             FROM users WHERE {$whereSql} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = array_map(static fn(array $row): array => AvatarCatalog::decorate($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
        Response::success(['users' => $users, 'pagination' => $this->pagination($total, $page, $limit)]);
    }

    public function moderateUser(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $admin = Auth::requireAdmin();
        $userId = $request->integer('user_id');
        $action = trim((string) $request->input('action', ''));
        if (!in_array($action, ['ban', 'unban', 'warn'], true)) throw new HttpException('Invalid moderation action.', 422);
        $stmt = $this->pdo->prepare('SELECT id, username, role FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) throw new HttpException('User not found.', 404);
        if ($user['role'] === 'admin') throw new HttpException('Administrator accounts cannot be moderated here.', 403);
        $newStatus = ['ban' => 'banned', 'unban' => 'active', 'warn' => 'warned'][$action];
        $this->pdo->prepare(
            'UPDATE users SET status = :status, remember_token = IF(:is_ban = 1, NULL, remember_token),
                remember_expires = IF(:is_ban2 = 1, NULL, remember_expires) WHERE id = :id'
        )->execute([':status' => $newStatus, ':is_ban' => $action === 'ban' ? 1 : 0, ':is_ban2' => $action === 'ban' ? 1 : 0, ':id' => $userId]);
        $message = match ($action) {
            'ban' => 'Your account was suspended by an administrator.',
            'warn' => 'You received an official account warning.',
            default => 'Your account access was restored.',
        };
        (new NotificationService($this->pdo))->create(
            $userId, 'Account status update', $message, 'account_warning', 'profile.html', (int) $admin['id']
        );
        Response::success(['new_status' => $newStatus], 'User status updated.');
    }

    public function cancelTournament(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $admin = Auth::requireAdmin();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        (new TournamentService($this->pdo))->cancel($admin, $id);
        Response::success([], 'Tournament cancelled and participants notified.');
    }

    public function finance(Request $request): never
    {
        $request->requireMethod('GET');
        Auth::requireAdmin();
        $payments = $this->pdo->query(
            "SELECT p.*, u.username, t.name AS tournament_name
             FROM payments p
             JOIN users u ON u.id = p.user_id
             JOIN tournaments t ON t.id = p.tournament_id
             ORDER BY p.created_at DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
        $ledger = $this->pdo->query(
            "SELECT l.*, u.username, t.name AS tournament_name
             FROM financial_ledger l
             LEFT JOIN users u ON u.id = l.user_id
             LEFT JOIN tournaments t ON t.id = l.tournament_id
             ORDER BY l.created_at DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
        $refunds = $this->pdo->query(
            "SELECT r.*, p.order_reference, u.username, t.name AS tournament_name
             FROM refunds r
             JOIN payments p ON p.id = r.payment_id
             JOIN users u ON u.id = p.user_id
             JOIN tournaments t ON t.id = p.tournament_id
             ORDER BY r.created_at DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
        $payouts = $this->pdo->query(
            "SELECT po.*, u.username, t.name AS tournament_name
             FROM payouts po
             JOIN users u ON u.id = po.user_id
             JOIN tournaments t ON t.id = po.tournament_id
             ORDER BY po.created_at DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
        $webhooks = $this->pdo->query(
            "SELECT id, provider, event_id, event_type, order_reference, verified, processed_at, created_at
             FROM payment_webhook_events ORDER BY created_at DESC LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        $summary = $this->pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS paid_total,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) AS pending_total,
                COUNT(*) AS payment_count,
                SUM(status = 'failed') AS failed_count
             FROM payments"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        Response::success([
            'summary' => $summary,
            'payments' => $payments,
            'ledger' => $ledger,
            'refunds' => $refunds,
            'payouts' => $payouts,
            'webhooks' => $webhooks,
        ]);
    }

    public function covers(Request $request): never
    {
        $request->requireMethod('GET', 'POST');
        Auth::requireAdmin();
        $catalog = new TournamentCatalogService($this->pdo);
        if ($request->method() === 'GET') {
            Response::success(['covers' => $catalog->allCoversForAdmin()]);
        }

        $request->requireCsrf();
        $action = trim((string) $request->input('action', 'save'));
        if ($action === 'toggle') {
            $id = $request->integer('id');
            $active = !empty($request->input('is_active')) ? 1 : 0;
            $this->pdo->prepare('UPDATE tournament_cover_images SET is_active = :active WHERE id = :id')
                ->execute([':active' => $active, ':id' => $id]);
            Response::success([], 'Cover updated.');
        }

        $id = $request->integer('id');
        $name = trim((string) $request->input('name', ''));
        $category = trim((string) $request->input('category', 'Arena'));
        $file = trim((string) $request->input('file_path', ''));
        $sort = $request->integer('sort_order');
        if ($name === '' || strlen($name) > 100 || $category === '' || strlen($category) > 60) {
            throw new HttpException('Cover name and category are required.', 422);
        }
        $file = TournamentCatalogService::normalizeCoverPath($file);
        if ($file === '') {
            throw new HttpException('Use a static cover from assets/tournament-covers: SVG, PNG, JPG, JPEG, or WEBP.', 422);
        }
        if (!TournamentCatalogService::coverFileExists($file)) {
            throw new HttpException('That cover file was not found under assets/tournament-covers.', 422);
        }
        if ($id > 0) {
            $this->pdo->prepare(
                'UPDATE tournament_cover_images SET name = :name, category = :category, file_path = :file, sort_order = :sort WHERE id = :id'
            )->execute([':name' => $name, ':category' => $category, ':file' => $file, ':sort' => $sort, ':id' => $id]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO tournament_cover_images (name, category, file_path, sort_order) VALUES (:name, :category, :file, :sort)'
            )->execute([':name' => $name, ':category' => $category, ':file' => $file, ':sort' => $sort]);
        }
        Response::success([], 'Cover saved.');
    }

    public function avatars(Request $request): never
    {
        $request->requireMethod('GET', 'POST');
        Auth::requireAdmin();
        if ($request->method() === 'GET') {
            Response::success(['avatars' => $this->pdo->query(
                "SELECT * FROM system_avatars ORDER BY is_active DESC, FIELD(category, 'male_character','female_character','country_flag','club'), sort_order ASC, name ASC"
            )->fetchAll(PDO::FETCH_ASSOC)]);
        }

        $request->requireCsrf();
        $action = trim((string) $request->input('action', 'save'));
        if ($action === 'toggle') {
            $this->pdo->prepare('UPDATE system_avatars SET is_active = :active WHERE id = :id')
                ->execute([':active' => !empty($request->input('is_active')) ? 1 : 0, ':id' => $request->integer('id')]);
            Response::success([], 'Avatar updated.');
        }

        $id = $request->integer('id');
        $name = trim((string) $request->input('name', ''));
        $category = trim((string) $request->input('category', ''));
        $file = basename(trim((string) $request->input('file_path', '')));
        $sort = $request->integer('sort_order');
        if ($name === '' || strlen($name) > 80 || !in_array($category, ['male_character','female_character','country_flag','club'], true)) {
            throw new HttpException('Avatar name and one of the four approved categories are required.', 422);
        }
        if (!preg_match('/^[A-Za-z0-9._-]+\.svg$/', $file)) {
            throw new HttpException('Avatar file must be an SVG filename from assets/avatars.', 422);
        }
        if ($id > 0) {
            $this->pdo->prepare(
                'UPDATE system_avatars SET name = :name, category = :category, file_path = :file, sort_order = :sort WHERE id = :id'
            )->execute([':name' => $name, ':category' => $category, ':file' => $file, ':sort' => $sort, ':id' => $id]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO system_avatars (name, category, file_path, sort_order) VALUES (:name, :category, :file, :sort)'
            )->execute([':name' => $name, ':category' => $category, ':file' => $file, ':sort' => $sort]);
        }
        Response::success([], 'Avatar saved.');
    }

    public function cancellations(Request $request): never
    {
        $request->requireMethod('GET', 'POST');
        $admin = Auth::requireAdmin();
        if ($request->method() === 'GET') {
            Response::success(['requests' => $this->pdo->query(
                "SELECT cr.*, t.name AS tournament_name, t.status AS tournament_status, u.username AS requested_by_username,
                        reviewer.username AS reviewed_by_username
                 FROM tournament_cancellation_requests cr
                 JOIN tournaments t ON t.id = cr.tournament_id
                 JOIN users u ON u.id = cr.requested_by
                 LEFT JOIN users reviewer ON reviewer.id = cr.reviewed_by
                 ORDER BY FIELD(cr.status, 'pending','approved','rejected'), cr.created_at DESC LIMIT 100"
            )->fetchAll(PDO::FETCH_ASSOC)]);
        }

        $request->requireCsrf();
        $id = $request->integer('id');
        $decision = trim((string) $request->input('decision', ''));
        $note = trim((string) $request->input('admin_note', ''));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new HttpException('Choose approve or reject.', 422);
        }
        $stmt = $this->pdo->prepare(
            'SELECT cr.*, t.name AS tournament_name FROM tournament_cancellation_requests cr
             JOIN tournaments t ON t.id = cr.tournament_id WHERE cr.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new HttpException('Cancellation request not found.', 404);
        if ($row['status'] !== 'pending') throw new HttpException('This request has already been reviewed.', 409);

        $this->pdo->prepare(
            'UPDATE tournament_cancellation_requests
             SET status = :status, admin_note = :note, reviewed_by = :admin, reviewed_at = NOW()
             WHERE id = :id'
        )->execute([':status' => $decision, ':note' => $note !== '' ? $note : null, ':admin' => $admin['id'], ':id' => $id]);

        if ($decision === 'approved') {
            (new TournamentService($this->pdo))->cancel($admin, (int) $row['tournament_id']);
        }
        (new NotificationService($this->pdo))->create(
            (int) $row['requested_by'],
            'Cancellation request reviewed',
            "Your cancellation request for {$row['tournament_name']} was {$decision}.",
            'cancellation_decision',
            "tournament_detail.html?id={$row['tournament_id']}",
            (int) $admin['id']
        );
        Response::success([], 'Cancellation request reviewed.');
    }

    private function pagination(int $total, int $page, int $limit): array
    {
        return ['total' => $total, 'page' => $page, 'limit' => $limit,
            'total_pages' => (int) ceil($total / max(1, $limit)),
            'has_next' => $page * $limit < $total, 'has_prev' => $page > 1];
    }
}
