<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\TournamentService;
use App\Support\AvatarCatalog;
use PDO;

final class TournamentController
{
    private PDO $pdo;
    private TournamentService $service;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->service = new TournamentService($this->pdo);
    }

    public function create(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        
        $result = $this->service->create((int) $user['id'], $request->data());
        $message = !empty($result['payment']['required'])
            ? 'Tournament created and payment request sent to your phone.'
            : 'Tournament created.';
        Response::success($result, $message, !empty($result['payment']['required']) ? 202 : 201);

    }

    public function list(Request $request): never
    {
        $request->requireMethod('GET');
        Response::success($this->service->list([
            'page' => $request->query('page', 1),
            'limit' => $request->query('limit', DEFAULT_PAGE_LIMIT),
            'format' => $request->query('format', ''),
            'status' => $request->query('status', ''),
            'search' => $request->query('search', ''),
            'game_id' => $request->query('game_id', 0),
            'platform_id' => $request->query('platform_id', 0),
            'payment' => $request->query('payment', ''),
            'available_slots' => $request->query('available_slots', ''),
            'mine' => $request->query('mine', ''),
        ], Auth::user()));
    }

    public function get(Request $request): never
    {
        $request->requireMethod('GET');
        $id = $request->queryInteger('id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        Response::success($this->service->get($id, Auth::user(), trim((string) $request->query('invite', ''))));
    }

    public function join(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        Response::success(
            $this->service->join((int) $user['id'], $id, trim((string) $request->input('invite_token', ''))),
            'Joined tournament successfully.'
        );
    }

    public function leave(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        $this->service->leave((int) $user['id'], $id);
        Response::success([], 'You left the tournament.');
    }

    public function edit(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        $this->service->edit($user, $id, $request->data());
        Response::success([], 'Tournament updated.');
    }

    public function start(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        $this->service->start($user, $id);
        Response::success([], 'Tournament started and matches generated.');
    }

    public function cancel(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        $this->service->cancel($user, $id);
        Response::success([], 'Tournament cancelled.');
    }

    public function requestCancellation(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        Response::success(
            $this->service->requestCancellation($user, $id, (string) $request->input('reason', '')),
            'Cancellation request sent.',
            201
        );
    }

    public function announce(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        Response::success($this->service->announce($user, $id, $request->data()), 'Announcement published.', 201);
    }

    public function bracket(Request $request): never
    {
        $request->requireMethod('GET');
        $id = $request->queryInteger('id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        $data = $this->service->get($id, Auth::user(), trim((string) $request->query('invite', '')));
        $rounds = [];
        foreach ($data['matches'] as $match) {
            $rounds[(int) $match['round_number']][] = $match;
        }
        $slotsStmt = $this->pdo->prepare(
            'SELECT s.*, u.username, u.avatar_url
             FROM tournament_bracket_slots s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.tournament_id = :id
             ORDER BY s.stage ASC, s.round_number ASC, s.bracket_position ASC, s.slot_number ASC'
        );
        $slotsStmt->execute([':id' => $id]);
        $slots = [];
        foreach ($slotsStmt->fetchAll(PDO::FETCH_ASSOC) as $slot) {
            $slot['avatar_url'] = AvatarCatalog::url($slot['avatar_url'] ?? null);
            $slots[(string) $slot['stage']][(int) $slot['round_number']][(int) $slot['bracket_position']][] = $slot;
        }

        Response::success([
            'tournament' => $data['tournament'],
            'rounds' => $rounds,
            'slots' => $slots,
            'total_matches' => count($data['matches']),
        ]);
    }

    public function invite(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        $action = trim((string) $request->input('action', 'regenerate'));
        if ($action === 'revoke') {
            $this->service->revokeInvite($user, $id);
            Response::success([], 'Invite link revoked.');
        }
        if ($action === 'regenerate') {
            Response::success($this->service->regenerateInvite($user, $id), 'Invite link regenerated.');
        }
        throw new HttpException('Invalid invite action.', 422);
    }

    public function checkIn(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireCompletedPlayerProfile();
        $id = $request->integer('tournament_id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        
        $this->service->checkIn($user, $id);
        Response::success([], 'You have successfully checked in.');
    }

    public function standings(Request $request): never
    {
        $request->requireMethod('GET');
        $id = $request->queryInteger('id');
        if ($id < 1) throw new HttpException('Tournament ID is required.', 422);
        $data = $this->service->get($id, Auth::user(), trim((string) $request->query('invite', '')));
        $groupsStmt = $this->pdo->prepare(
            "SELECT id, name, status FROM tournament_groups WHERE tournament_id = :id ORDER BY sort_order ASC, id ASC"
        );
        $groupsStmt->execute([':id' => $id]);
        $groups = [];
        $engine = new \App\Services\TournamentEngine($this->pdo);
        
        foreach ($groupsStmt->fetchAll(PDO::FETCH_ASSOC) as $group) {
            $standings = $engine->calculateStandings($id, (int) $group['id']);
            $groups[] = [
                'id' => (int) $group['id'],
                'name' => $group['name'],
                'status' => $group['status'],
                'standings' => array_map(static fn(array $row): array => \App\Support\AvatarCatalog::decorate($row), $standings),
            ];
        }

        if (!$groups) {
            // Group Knockout tournaments ALWAYS have groups.
            // But if there are no groups yet, we just return empty standings.
            $groups[] = [
                'id' => null,
                'name' => 'Standings',
                'status' => $data['tournament']['status'] ?? '',
                'standings' => [],
            ];
        }

        Response::success(['groups' => $groups, 'standings' => $groups[0]['standings'] ?? []]);
    }
}
