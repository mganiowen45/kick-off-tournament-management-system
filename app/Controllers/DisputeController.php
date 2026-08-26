<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\ResultService;
use App\Support\AvatarCatalog;
use PDO;

final class DisputeController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function list(Request $request): never
    {
        $request->requireMethod('GET');
        Auth::requireAdmin();
        $openStmt = $this->pdo->query(
            "SELECT d.id AS dispute_id, d.status, d.outcome, d.reason, d.admin_note, d.created_at,
                    m.id AS match_id, m.tournament_id, m.round_number, m.stage, t.name AS tournament_name, t.format,
                    p1.id AS player1_id, p1.username AS player1_username, p1.avatar_url AS player1_avatar,
                    p2.id AS player2_id, p2.username AS player2_username, p2.avatar_url AS player2_avatar,
                    raiser.username AS raised_by_username
             FROM disputes d JOIN matches m ON m.id = d.match_id JOIN tournaments t ON t.id = m.tournament_id
             JOIN users p1 ON p1.id = m.player1_id JOIN users p2 ON p2.id = m.player2_id
             LEFT JOIN users raiser ON raiser.id = d.raised_by
             WHERE d.status IN ('open', 'under_review') ORDER BY d.created_at ASC"
        );
        $open = array_map([$this, 'decorateDispute'], $openStmt->fetchAll(PDO::FETCH_ASSOC));

        $resolvedStmt = $this->pdo->query(
            "SELECT d.id, d.status, d.outcome, d.reason, d.admin_note, d.resolved_at,
                    m.id AS match_id, m.tournament_id, m.stage, t.name AS tournament_name, t.format,
                    p1.id AS player1_id, p1.username AS player1_username, p1.avatar_url AS player1_avatar,
                    p2.id AS player2_id, p2.username AS player2_username, p2.avatar_url AS player2_avatar,
                    administrator.username AS resolved_by_username
             FROM disputes d JOIN matches m ON m.id = d.match_id JOIN tournaments t ON t.id = m.tournament_id
             JOIN users p1 ON p1.id = m.player1_id JOIN users p2 ON p2.id = m.player2_id
             LEFT JOIN users administrator ON administrator.id = d.resolved_by
             WHERE d.status = 'resolved' ORDER BY d.resolved_at DESC LIMIT 100"
        );
        $resolved = array_map([$this, 'decorateDispute'], $resolvedStmt->fetchAll(PDO::FETCH_ASSOC));
        Response::success(['open' => $open, 'resolved' => $resolved]);
    }

    public function resolve(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $admin = Auth::requireAdmin();
        $service = new ResultService($this->pdo);
        Response::success($service->resolveDispute((int) $admin['id'], $request->data()), 'Dispute resolved.');
    }

    private function decorateDispute(array $row): array
    {
        $row['player1_avatar_url'] = AvatarCatalog::url($row['player1_avatar'] ?? null);
        $row['player2_avatar_url'] = AvatarCatalog::url($row['player2_avatar'] ?? null);
        $row['allow_draw'] = ($row['format'] ?? '') === 'group_knockout' && ($row['stage'] ?? '') === 'group';
        unset($row['player1_avatar'], $row['player2_avatar']);
        return $row;
    }
}
