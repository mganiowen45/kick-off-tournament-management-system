<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Support\AvatarCatalog;
use App\Support\CoverImage;
use DateTimeImmutable;
use PDO;
use PDOException;

final class TournamentService
{
    private NotificationService $notifications;
    private TournamentEngine $engine;
    private TournamentCatalogService $catalog;
    private TournamentFinanceService $finance;
    private PaymentService $payments;

    public function __construct(private readonly PDO $pdo)
    {
        $this->notifications = new NotificationService($pdo);
        $this->engine = new TournamentEngine($pdo);
        $this->catalog = new TournamentCatalogService($pdo);
        $this->finance = new TournamentFinanceService();
        $this->payments = new PaymentService($pdo);
    }

    public function create(int $userId, array $data): array
    {
        $values = $this->validateTournament($data, $this->isAdminUser($userId));
        $token = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $token);

        return Database::transaction(function () use ($userId, $values, $token, $tokenHash): array {
            $stmt = $this->pdo->prepare(
                "INSERT INTO tournaments
                    (creator_id, name, description, game, game_id, platform_id, cover_image_id,
                     format, status, visibility, share_token, share_token_hash,
                     max_players, current_players, prize_pool, funding_model, currency,
                     entry_fee_amount, prize_pool_amount, kickoff_contribution_amount, prize_template,
                     platform_fee_percent, match_legs, start_date, registration_deadline, registration_opens_at)
                 VALUES
                    (:creator_id, :name, :description, :game, :game_id, :platform_id, :cover_image_id,
                     :format, 'open', :visibility, :share_token, :share_token_hash,
                     :max_players, 1, :prize_pool, :funding_model, :currency,
                     :entry_fee, :prize_amount, :kickoff_contribution, :prize_template,
                     :platform_fee_percent, :match_legs, :start_date, :deadline, NOW())"
            );
            $stmt->execute([
                ':creator_id' => $userId,
                ':name' => $values['name'],
                ':description' => $values['description'],
                ':game' => $values['game'],
                ':game_id' => $values['game_id'],
                ':platform_id' => $values['platform_id'],
                ':cover_image_id' => $values['cover_image_id'],
                ':format' => $values['format'],
                ':visibility' => $values['visibility'],
                ':share_token' => $token,
                ':share_token_hash' => $tokenHash,
                ':max_players' => $values['max_players'],
                ':prize_pool' => $values['prize_pool'],
                ':funding_model' => $values['funding_model'],
                ':currency' => $values['currency'],
                ':entry_fee' => $values['entry_fee_amount'],
                ':prize_amount' => $values['prize_pool_amount'],
                ':kickoff_contribution' => $values['kickoff_contribution_amount'],
                ':prize_template' => $values['prize_template'],
                ':platform_fee_percent' => $values['platform_fee_percent'],
                ':match_legs' => $values['match_legs'],
                ':start_date' => $values['start_date'],
                ':deadline' => $values['registration_deadline'],
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare(
                "INSERT INTO tournament_players (tournament_id, user_id, status, payment_status)
                 VALUES (:tournament_id, :user_id, 'registered', :payment_status)"
            )->execute([
                ':tournament_id' => $id,
                ':user_id' => $userId,
                ':payment_status' => 'not_required',
            ]);

            return [
                'tournament_id' => $id,
                'share_token' => $token,
                'share_url' => "tournament_detail.html?id={$id}&invite={$token}",
                'redirect' => "tournament_detail.html?id={$id}",
                'prize_distribution' => $this->finance->prizeDistribution($values['format'], $values['max_players'], (float) $values['prize_pool_amount']),
            ];
        });
    }

    public function list(array $filters, ?array $viewer): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(1, (int) ($filters['limit'] ?? DEFAULT_PAGE_LIMIT)));
        $offset = ($page - 1) * $limit;
        $viewerId = (int) ($viewer['id'] ?? 0);
        $isAdmin = ($viewer['role'] ?? '') === 'admin';
        $mine = !empty($filters['mine']) && $viewerId > 0;
        $where = ['1 = 1'];
        $params = [];

        $format = trim((string) ($filters['format'] ?? ''));
        if ($format !== '') {
            if (!in_array($format, TournamentCatalogService::FORMATS, true)) {
                throw new HttpException('Invalid tournament format.', 422);
            }
            $where[] = 't.format = :format';
            $params[':format'] = $format;
        }
        $gameId = (int) ($filters['game_id'] ?? 0);
        if ($gameId > 0) {
            $where[] = 't.game_id = :game_id';
            $params[':game_id'] = $gameId;
        }
        $platformId = (int) ($filters['platform_id'] ?? 0);
        if ($platformId > 0) {
            $where[] = 't.platform_id = :platform_id';
            $params[':platform_id'] = $platformId;
        }
        $paymentFilter = trim((string) ($filters['payment'] ?? ''));
        if ($paymentFilter === 'free') {
            $where[] = "t.entry_fee_amount = 0";
        } elseif ($paymentFilter === 'paid') {
            $where[] = "t.entry_fee_amount > 0";
        }
        if (!empty($filters['available_slots'])) {
            $where[] = 't.current_players < t.max_players';
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            if (!in_array($status, ['draft', 'open', 'active', 'completed', 'cancelled'], true)) {
                throw new HttpException('Invalid tournament status.', 422);
            }
            $where[] = 't.status = :status';
            $params[':status'] = $status;
        } elseif (!$isAdmin) {
            $where[] = "t.status != 'draft'";
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(t.name LIKE :search OR t.description LIKE :search2 OR t.game LIKE :search3 OR creator.username LIKE :search4)';
            $term = '%' . addcslashes(mb_substr($search, 0, 80), '%_\\') . '%';
            $params += [':search' => $term, ':search2' => $term, ':search3' => $term, ':search4' => $term];
        }
        if ($mine) {
            $where[] = '(t.creator_id = :mine_creator OR mine_member.user_id IS NOT NULL)';
            $params[':mine_creator'] = $viewerId;
        } elseif (!$isAdmin) {
            $where[] = "t.visibility = 'public'";
        }

        $membershipJoin = $mine
            ? ' LEFT JOIN tournament_players mine_member ON mine_member.tournament_id = t.id AND mine_member.user_id = :viewer_id_mine_count AND mine_member.status != \'withdrawn\''
            : '';
        $whereSql = implode(' AND ', $where);
        $count = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT t.id) FROM tournaments t JOIN users creator ON creator.id = t.creator_id{$membershipJoin} WHERE {$whereSql}"
        );
        $countParams = $params;
        if ($mine) $countParams[':viewer_id_mine_count'] = $viewerId;
        $count->execute($countParams);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.creator_id, t.name, t.description, t.game, t.game_id, t.platform_id,
                    g.name AS game_name, p.name AS platform_name, cover.file_path AS cover_image_url,
                    t.format, t.status, t.visibility,
                    t.share_token, t.max_players, t.current_players, t.prize_pool, t.match_legs, t.current_round,
                    t.funding_model, t.currency, t.entry_fee_amount, t.prize_pool_amount, t.kickoff_contribution_amount,
                    t.start_date, t.registration_deadline, t.check_in_opens_at, t.check_in_closes_at,
                    t.auto_start_at, t.completed_at, t.winner_id, t.created_at,
                    creator.username AS creator_username, creator.country AS creator_country,
                    creator.avatar_url AS creator_avatar,
                    CASE WHEN member.user_id IS NULL THEN 0 ELSE 1 END AS joined,
                    member.payment_status AS viewer_payment_status,
                    (SELECT pay.checkout_url
                     FROM payments pay
                     WHERE pay.tournament_id = t.id AND pay.user_id = :viewer_payment_id
                       AND pay.status IN ('pending', 'requires_action') AND pay.checkout_url IS NOT NULL
                     ORDER BY pay.created_at DESC LIMIT 1) AS pending_checkout_url
             FROM tournaments t JOIN users creator ON creator.id = t.creator_id
             LEFT JOIN games g ON g.id = t.game_id
             LEFT JOIN platforms p ON p.id = t.platform_id
             LEFT JOIN tournament_cover_images cover ON cover.id = t.cover_image_id
             LEFT JOIN tournament_players member
               ON member.tournament_id = t.id AND member.user_id = :viewer_id AND member.status != 'withdrawn'
             LEFT JOIN tournament_players mine_member
               ON mine_member.tournament_id = t.id AND mine_member.user_id = :viewer_id_mine AND mine_member.status != 'withdrawn'
             WHERE {$whereSql}
             ORDER BY FIELD(t.status, 'active', 'open', 'completed', 'cancelled', 'draft'), t.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_id_mine', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_payment_id', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = array_map(function (array $row) use ($viewerId, $isAdmin): array {
            $row['creator_avatar_url'] = AvatarCatalog::url($row['creator_avatar'] ?? null);
            unset($row['creator_avatar']);
            $maySeeToken = $isAdmin || (int) $row['creator_id'] === $viewerId || (int) $row['joined'] === 1;
            if (!$maySeeToken) {
                unset($row['share_token']);
            }
            $row['joined'] = (bool) $row['joined'];
            $row['cover_image_url'] = CoverImage::resolve($row['cover_image_url'] ?? null, (string) ($row['name'] ?? ''));
            $row['cover_image_path'] = $row['cover_image_url'];
            $row['format_label'] = TournamentCatalogService::formatLabel($row['format'] ?? '');
            $row['funding_label'] = TournamentCatalogService::fundingLabel($row['funding_model'] ?? '');
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        return ['tournaments' => $items, 'pagination' => $this->pagination($total, $page, $limit)];
    }

    public function get(int $id, ?array $viewer, string $inviteToken = ''): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, g.name AS game_name, p.name AS platform_name,
                    cover.name AS cover_image_name, cover.file_path AS cover_image_url,
                    creator.username AS creator_username, creator.country AS creator_country,
                    creator.avatar_url AS creator_avatar, winner.username AS winner_username,
                    winner.avatar_url AS winner_avatar
             FROM tournaments t
             LEFT JOIN games g ON g.id = t.game_id
             LEFT JOIN platforms p ON p.id = t.platform_id
             LEFT JOIN tournament_cover_images cover ON cover.id = t.cover_image_id
             JOIN users creator ON creator.id = t.creator_id
             LEFT JOIN users winner ON winner.id = t.winner_id
             WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tournament || !$this->canView($tournament, $viewer, $inviteToken)) {
            throw new HttpException('Tournament not found.', 404);
        }

        $viewerId = (int) ($viewer['id'] ?? 0);
        $isMember = $this->isMember($id, $viewerId);
        $viewerTournamentState = ['viewer_joined' => $isMember, 'viewer_payment_status' => null, 'pending_checkout_url' => null];
        if ($viewerId > 0) {
            $stateStmt = $this->pdo->prepare(
                "SELECT tp.payment_status AS viewer_payment_status,
                        (SELECT pay.checkout_url
                         FROM payments pay
                         WHERE pay.tournament_id = tp.tournament_id AND pay.user_id = tp.user_id
                           AND pay.status IN ('pending', 'requires_action') AND pay.checkout_url IS NOT NULL
                         ORDER BY pay.created_at DESC LIMIT 1) AS pending_checkout_url
                 FROM tournament_players tp
                 WHERE tp.tournament_id = :tournament AND tp.user_id = :viewer AND tp.status != 'withdrawn'
                 LIMIT 1"
            );
            $stateStmt->execute([':tournament' => $id, ':viewer' => $viewerId]);
            $viewerTournamentState = array_merge($viewerTournamentState, $stateStmt->fetch(PDO::FETCH_ASSOC) ?: []);
        }
        $tournament['creator_avatar_url'] = AvatarCatalog::url($tournament['creator_avatar'] ?? null);
        $tournament['winner_avatar_url'] = $tournament['winner_id'] ? AvatarCatalog::url($tournament['winner_avatar'] ?? null) : null;
        unset($tournament['creator_avatar'], $tournament['winner_avatar']);
        if (!$viewer || (($viewer['role'] ?? '') !== 'admin' && (int) $tournament['creator_id'] !== $viewerId && !$isMember)) {
            unset($tournament['share_token']);
        }
        unset($tournament['share_token_hash']);
        $tournament = array_merge($tournament, $viewerTournamentState);
        $tournament['cover_image_url'] = CoverImage::resolve($tournament['cover_image_url'] ?? null, (string) ($tournament['name'] ?? ''));
        $tournament['cover_image_path'] = $tournament['cover_image_url'];
        $tournament['format_label'] = TournamentCatalogService::formatLabel($tournament['format'] ?? '');
        $tournament['funding_label'] = TournamentCatalogService::fundingLabel($tournament['funding_model'] ?? '');
        $tournament['prize_distribution'] = $this->finance->prizeDistribution(
            (string) $tournament['format'],
            (int) $tournament['max_players'],
            (float) ($tournament['prize_pool_amount'] ?? 0)
        );

        $playersStmt = $this->pdo->prepare(
            "SELECT tp.*, u.username, u.country, u.avatar_url
             FROM tournament_players tp JOIN users u ON u.id = tp.user_id
             WHERE tp.tournament_id = :id AND tp.status != 'withdrawn'
             ORDER BY tp.is_eliminated ASC, tp.league_points DESC, tp.wins DESC, tp.joined_at ASC"
        );
        $playersStmt->execute([':id' => $id]);
        $players = array_map(static fn(array $row): array => AvatarCatalog::decorate($row), $playersStmt->fetchAll(PDO::FETCH_ASSOC));

        $matchesStmt = $this->pdo->prepare(
            'SELECT m.*, ms.status AS schedule_status, ms.proposed_start_utc, ms.proposed_by, ms.confirmed_at,
                    p1.username AS player1_name, p1.avatar_url AS player1_avatar,
                    p1.whatsapp_contact_opt_in AS player1_whatsapp_opt_in, p1.whatsapp_number AS player1_whatsapp_number,
                    p2.username AS player2_name, p2.avatar_url AS player2_avatar,
                    p2.whatsapp_contact_opt_in AS player2_whatsapp_opt_in, p2.whatsapp_number AS player2_whatsapp_number,
                    winner.username AS winner_name
             FROM matches m
             JOIN users p1 ON p1.id = m.player1_id
             JOIN users p2 ON p2.id = m.player2_id
             LEFT JOIN users winner ON winner.id = m.winner_id
             LEFT JOIN match_schedules ms ON ms.match_id = m.id
             WHERE m.tournament_id = :id ORDER BY m.round_number ASC, m.scheduled_at ASC, m.id ASC'
        );
        $matchesStmt->execute([':id' => $id]);
        $matches = array_map(function (array $row): array {
            $row['player1_avatar_url'] = AvatarCatalog::url($row['player1_avatar'] ?? null);
            $row['player2_avatar_url'] = AvatarCatalog::url($row['player2_avatar'] ?? null);
            $row['player1_whatsapp_available'] = (int) ($row['player1_whatsapp_opt_in'] ?? 0) === 1
                && !empty($row['player1_whatsapp_number']);
            $row['player2_whatsapp_available'] = (int) ($row['player2_whatsapp_opt_in'] ?? 0) === 1
                && !empty($row['player2_whatsapp_number']);
            unset(
                $row['player1_avatar'],
                $row['player2_avatar'],
                $row['player1_whatsapp_opt_in'],
                $row['player1_whatsapp_number'],
                $row['player2_whatsapp_opt_in'],
                $row['player2_whatsapp_number']
            );
            return $row;
        }, $matchesStmt->fetchAll(PDO::FETCH_ASSOC));

        $announcementStmt = $this->pdo->prepare(
            'SELECT a.id, a.title, a.body, a.created_at, a.updated_at, u.id AS author_id,
                    u.username AS author_username, u.avatar_url AS author_avatar
             FROM announcements a JOIN users u ON u.id = a.author_id
             WHERE a.tournament_id = :id ORDER BY a.created_at DESC LIMIT 50'
        );
        $announcementStmt->execute([':id' => $id]);
        $announcements = array_map(function (array $row): array {
            $row['author_avatar_url'] = AvatarCatalog::url($row['author_avatar'] ?? null);
            unset($row['author_avatar']);
            return $row;
        }, $announcementStmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'tournament' => $tournament,
            'players' => $players,
            'matches' => $matches,
            'announcements' => $announcements,
            'permissions' => [
                'is_member' => $isMember,
                'can_join' => $viewer && !$isMember && $this->registrationAcceptsJoins($tournament)
                    && (int) $tournament['current_players'] < (int) $tournament['max_players'],
                'can_join_after_auth' => !$viewer && $this->registrationAcceptsJoins($tournament)
                    && (int) $tournament['current_players'] < (int) $tournament['max_players'],
                'can_manage' => $viewer && (($viewer['role'] ?? '') === 'admin'),
                'can_share_invite' => $viewer && (($viewer['role'] ?? '') === 'admin' || (int) $tournament['creator_id'] === $viewerId),
                'can_request_cancellation' => $viewer && (int) $tournament['creator_id'] === $viewerId && !in_array($tournament['status'], ['completed', 'cancelled'], true),
                'can_leave' => $isMember && (int) $tournament['creator_id'] !== $viewerId && $tournament['status'] === 'open',
            ],
        ];
    }

    public function join(int $userId, int $tournamentId, string $inviteToken = ''): array
    {
        return Database::transaction(function () use ($userId, $tournamentId, $inviteToken): array {
            $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $tournamentId]);
            $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tournament || $tournament['status'] !== 'open') {
                throw new HttpException('Tournament is not open for registration.', 409);
            }
            if (!$this->registrationAcceptsJoins($tournament)) {
                throw new HttpException('Tournament registration is not open at this time.', 409);
            }
            $validInvite = empty($tournament['invite_revoked_at']) && $inviteToken !== '' && (
                hash_equals((string) ($tournament['share_token'] ?? ''), $inviteToken)
                || hash_equals((string) ($tournament['share_token_hash'] ?? ''), hash('sha256', $inviteToken))
            );
            if ($tournament['visibility'] === 'private' && !$validInvite) {
                throw new HttpException('A valid private invitation is required.', 403);
            }
            $this->requireCompletedProfile($userId);
            $this->requireCompatibleGameProfile($userId, $tournament);

            $existing = $this->pdo->prepare(
                'SELECT id, status FROM tournament_players WHERE tournament_id = :tournament_id AND user_id = :user_id LIMIT 1'
            );
            $existing->execute([':tournament_id' => $tournamentId, ':user_id' => $userId]);
            $membership = $existing->fetch(PDO::FETCH_ASSOC);
            if ($membership && $membership['status'] !== 'withdrawn') {
                throw new HttpException('You have already joined this tournament.', 409);
            }

            $countStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM tournament_players
                 WHERE tournament_id = :id AND status != 'withdrawn'"
            );
            $countStmt->execute([':id' => $tournamentId]);
            $count = (int) $countStmt->fetchColumn();
            if ($count >= (int) $tournament['max_players']) {
                throw new HttpException('Tournament is full.', 409);
            }

            if ($membership) {
                $this->pdo->prepare(
                    "UPDATE tournament_players SET status = 'registered', joined_at = NOW(), is_eliminated = 0,
                        payment_status = :payment_status, reservation_expires_at = :reservation_expires_at
                     WHERE id = :id"
                )->execute([
                    ':payment_status' => (float) $tournament['entry_fee_amount'] > 0 ? 'pending' : 'not_required',
                    ':reservation_expires_at' => (float) $tournament['entry_fee_amount'] > 0 ? date('Y-m-d H:i:s', time() + RESERVATION_EXPIRY_MINUTES * 60) : null,
                    ':id' => $membership['id'],
                ]);
            } else {
                $this->pdo->prepare(
                    "INSERT INTO tournament_players (tournament_id, user_id, status, payment_status, reservation_expires_at)
                     VALUES (:tournament_id, :user_id, 'registered', :payment_status, :reservation_expires_at)"
                )->execute([
                    ':tournament_id' => $tournamentId,
                    ':user_id' => $userId,
                    ':payment_status' => (float) $tournament['entry_fee_amount'] > 0 ? 'pending' : 'not_required',
                    ':reservation_expires_at' => (float) $tournament['entry_fee_amount'] > 0 ? date('Y-m-d H:i:s', time() + RESERVATION_EXPIRY_MINUTES * 60) : null,
                ]);
            }
            $newCount = $count + 1;
            $this->pdo->prepare('UPDATE tournaments SET current_players = :count WHERE id = :id')
                ->execute([':count' => $newCount, ':id' => $tournamentId]);

            $usernameStmt = $this->pdo->prepare('SELECT username FROM users WHERE id = :id');
            $usernameStmt->execute([':id' => $userId]);
            $username = (string) $usernameStmt->fetchColumn();
            if ((int) $tournament['creator_id'] !== $userId) {
                $this->notifications->create(
                    (int) $tournament['creator_id'],
                    'New player joined',
                    "{$username} joined {$tournament['name']}.",
                    'tournament_joined',
                    "tournament_detail.html?id={$tournamentId}",
                    $userId
                );
            }

            $payment = ['required' => false];
            if ((float) $tournament['entry_fee_amount'] > 0) {
                $userStmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
                $userStmt->execute([':id' => $userId]);
                $payment = $this->payments->createTournamentCheckout($userStmt->fetch(PDO::FETCH_ASSOC), $tournament);
            }
            if ($newCount >= (int) $tournament['max_players']) {
                $this->scheduleCheckIn($tournamentId);
                $scheduled = $this->loadTournamentSchedule($tournamentId);
                $this->notifications->tournamentMembers(
                    $tournamentId,
                    'Tournament Full',
                    "{$tournament['name']} is full and will automatically start at " . $this->displayDateTime($scheduled['auto_start_at'] ?? null) . '. Prepare and check tournament information.',
                    'tournament_update',
                    "tournament_detail.html?id={$tournamentId}",
                    $userId
                );
            }
            return [
                'tournament_id' => $tournamentId,
                'current_players' => $newCount,
                'started' => false,
                'check_in_scheduled' => $newCount >= (int) $tournament['max_players'],
                'payment' => $payment,
                'redirect' => "tournament_detail.html?id={$tournamentId}",
            ];
        });
    }

    public function leave(int $userId, int $tournamentId): void
    {
        Database::transaction(function () use ($userId, $tournamentId): void {
            $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $tournamentId]);
            $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tournament) throw new HttpException('Tournament not found.', 404);
            if ($tournament['status'] !== 'open') throw new HttpException('You cannot leave after the tournament starts.', 409);
            if ((int) $tournament['creator_id'] === $userId) throw new HttpException('The organizer cannot leave their own tournament.', 409);

            $update = $this->pdo->prepare(
                "UPDATE tournament_players SET status = 'withdrawn'
                 WHERE tournament_id = :tournament_id AND user_id = :user_id AND status != 'withdrawn'"
            );
            $update->execute([':tournament_id' => $tournamentId, ':user_id' => $userId]);
            if ($update->rowCount() === 0) throw new HttpException('You are not an active participant.', 409);
            $this->pdo->prepare('UPDATE tournaments SET current_players = GREATEST(0, current_players - 1) WHERE id = :id')
                ->execute([':id' => $tournamentId]);
            if ((int) $tournament['current_players'] >= (int) $tournament['max_players']) {
                $this->resetFullSchedule($tournamentId, 'Tournament is no longer full; automatic start reset.');
            }
        });
    }

    public function edit(array $actor, int $tournamentId, array $data): void
    {
        Database::transaction(function () use ($actor, $tournamentId, $data): void {
            $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $tournamentId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new HttpException('Tournament not found.', 404);
            $this->requireAdmin($actor);
            if (!in_array($current['status'], ['draft', 'open'], true)) {
                throw new HttpException('An active or completed tournament cannot be edited.', 409);
            }

            $merged = array_merge($current, $data);
            $values = $this->validateTournament($merged, true);
            if ($values['max_players'] < (int) $current['current_players']) {
                throw new HttpException('Capacity cannot be lower than the current participant count.', 422);
            }
            if ((int) $current['current_players'] > 1 && $values['format'] !== $current['format']) {
                throw new HttpException('Format cannot change after players have joined.', 409);
            }
            $update = $this->pdo->prepare(
                'UPDATE tournaments SET name = :name, description = :description, game = :game,
                    game_id = :game_id, platform_id = :platform_id, cover_image_id = :cover_image_id,
                    format = :format, visibility = :visibility, max_players = :max_players,
                    prize_pool = :prize_pool, funding_model = :funding_model, currency = :currency,
                    entry_fee_amount = :entry_fee, prize_pool_amount = :prize_amount,
                    kickoff_contribution_amount = :kickoff_contribution, prize_template = :prize_template,
                    platform_fee_percent = :platform_fee_percent, match_legs = :match_legs,
                    start_date = :start_date, registration_deadline = :deadline
                 WHERE id = :id'
            );
            $update->execute([
                ':name' => $values['name'], ':description' => $values['description'], ':game' => $values['game'],
                ':game_id' => $values['game_id'], ':platform_id' => $values['platform_id'], ':cover_image_id' => $values['cover_image_id'],
                ':format' => $values['format'], ':visibility' => $values['visibility'], ':max_players' => $values['max_players'],
                ':prize_pool' => $values['prize_pool'], ':funding_model' => $values['funding_model'], ':currency' => $values['currency'],
                ':entry_fee' => $values['entry_fee_amount'], ':prize_amount' => $values['prize_pool_amount'],
                ':kickoff_contribution' => $values['kickoff_contribution_amount'], ':prize_template' => $values['prize_template'],
                ':platform_fee_percent' => $values['platform_fee_percent'], ':match_legs' => $values['match_legs'],
                ':start_date' => $values['start_date'], ':deadline' => $values['registration_deadline'], ':id' => $tournamentId,
            ]);
            $wasFull = (int) $current['current_players'] >= (int) $current['max_players'];
            $isFull = (int) $current['current_players'] >= (int) $values['max_players'];
            if ($current['status'] === 'open' && $wasFull && !$isFull) {
                $this->resetFullSchedule($tournamentId, 'Capacity changed; automatic start reset.');
            } elseif ($current['status'] === 'open' && !$wasFull && $isFull) {
                $this->scheduleCheckIn($tournamentId);
                $scheduled = $this->loadTournamentSchedule($tournamentId);
                $this->notifications->tournamentMembers(
                    $tournamentId,
                    'Tournament Full',
                    "{$current['name']} is full and will automatically start at " . $this->displayDateTime($scheduled['auto_start_at'] ?? null) . '. Prepare and check tournament information.',
                    'tournament_update',
                    "tournament_detail.html?id={$tournamentId}",
                    (int) $actor['id']
                );
            }
        });
    }

    public function start(array $actor, int $tournamentId): void
    {
        Database::transaction(function () use ($actor, $tournamentId): void {
            $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $tournamentId]);
            $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tournament) throw new HttpException('Tournament not found.', 404);
            $this->requireAdmin($actor);
            $this->engine->start($tournamentId);
        });
        $this->notifications->tournamentMembers(
            $tournamentId,
            'Tournament started',
            'Your tournament is now live. Check the schedule for your matches.',
            'tournament_update',
            "tournament_detail.html?id={$tournamentId}",
            (int) $actor['id']
        );
    }

    public function autoStartDueTournament(int $tournamentId): bool
    {
        $started = false;
        Database::transaction(function () use ($tournamentId, &$started): void {
            $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $tournamentId]);
            $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tournament || $tournament['status'] !== 'open' || empty($tournament['auto_start_at'])) {
                return;
            }
            if (strtotime((string) $tournament['auto_start_at']) > time()) {
                return;
            }
            if ((int) $tournament['current_players'] < (int) $tournament['max_players']) {
                $this->resetFullSchedule($tournamentId, 'Tournament is no longer full; automatic start reset.');
                return;
            }
            $pending = $this->pdo->prepare(
                "SELECT COUNT(*) FROM tournament_players
                 WHERE tournament_id = :id AND status != 'withdrawn' AND payment_status IN ('pending','failed')"
            );
            $pending->execute([':id' => $tournamentId]);
            if ((int) $pending->fetchColumn() > 0) {
                return;
            }
            $this->engine->start($tournamentId);
            $started = true;
        });

        if ($started) {
            $this->notifications->tournamentMembers(
                $tournamentId,
                'Tournament Started',
                'Your tournament is now live. Check your fixtures and match schedule.',
                'tournament_update',
                "tournament_detail.html?id={$tournamentId}"
            );
        }

        return $started;
    }

    public function cancel(array $actor, int $tournamentId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id');
        $stmt->execute([':id' => $tournamentId]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tournament) throw new HttpException('Tournament not found.', 404);
        $this->requireAdmin($actor);
        if ($tournament['status'] === 'completed') throw new HttpException('A completed tournament cannot be cancelled.', 409);
        $this->pdo->prepare("UPDATE tournaments SET status = 'cancelled' WHERE id = :id")
            ->execute([':id' => $tournamentId]);
        $this->notifications->tournamentMembers(
            $tournamentId,
            'Tournament cancelled',
            "{$tournament['name']} was cancelled.",
            'tournament_update',
            'tournaments.html',
            (int) $actor['id']
        );
    }

    public function requestCancellation(array $actor, int $tournamentId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 1000) {
            throw new HttpException('Cancellation reason is required and must be under 1000 characters.', 422);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id');
        $stmt->execute([':id' => $tournamentId]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tournament) throw new HttpException('Tournament not found.', 404);
        if ((int) $tournament['creator_id'] !== (int) $actor['id']) {
            throw new HttpException('Only the creator may request cancellation.', 403);
        }
        if (in_array($tournament['status'], ['completed', 'cancelled'], true)) {
            throw new HttpException('This tournament cannot receive cancellation requests.', 409);
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO tournament_cancellation_requests (tournament_id, requested_by, reason)
             VALUES (:tournament, :user, :reason)"
        );
        $insert->execute([':tournament' => $tournamentId, ':user' => $actor['id'], ':reason' => $reason]);

        $admins = $this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND status != 'banned'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $adminId) {
            $this->notifications->create(
                (int) $adminId,
                'Cancellation requested',
                "{$tournament['name']} has a creator cancellation request awaiting review.",
                'cancellation_requested',
                'admin_tournaments.html',
                (int) $actor['id']
            );
        }

        return ['request_id' => (int) $this->pdo->lastInsertId()];
    }

    public function regenerateInvite(array $actor, int $tournamentId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $tournamentId]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tournament) throw new HttpException('Tournament not found.', 404);
        $this->requireManager($actor, $tournament);

        $token = bin2hex(random_bytes(16));
        $this->pdo->prepare(
            'UPDATE tournaments
             SET share_token = :token, share_token_hash = :hash, invite_revoked_at = NULL, invite_regenerated_at = NOW()
             WHERE id = :id'
        )->execute([
            ':token' => $token,
            ':hash' => hash('sha256', $token),
            ':id' => $tournamentId,
        ]);

        return [
            'share_token' => $token,
            'share_url' => "tournament_detail.html?id={$tournamentId}&invite={$token}",
        ];
    }

    public function revokeInvite(array $actor, int $tournamentId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $tournamentId]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tournament) throw new HttpException('Tournament not found.', 404);
        $this->requireManager($actor, $tournament);
        $this->pdo->prepare('UPDATE tournaments SET invite_revoked_at = NOW() WHERE id = :id')
            ->execute([':id' => $tournamentId]);
    }

    public function announce(array $actor, int $tournamentId, array $data): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id');
        $stmt->execute([':id' => $tournamentId]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tournament) throw new HttpException('Tournament not found.', 404);
        $this->requireAdmin($actor);
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        if ($title === '' || strlen($title) > 120 || $body === '' || strlen($body) > 5000) {
            throw new HttpException('Announcement title and body are required.', 422);
        }
        $insert = $this->pdo->prepare(
            'INSERT INTO announcements (tournament_id, author_id, title, body) VALUES (:tournament, :author, :title, :body)'
        );
        $insert->execute([':tournament' => $tournamentId, ':author' => $actor['id'], ':title' => $title, ':body' => $body]);
        $this->notifications->tournamentMembers(
            $tournamentId,
            $title,
            $body,
            'tournament_update',
            "tournament_detail.html?id={$tournamentId}",
            (int) $actor['id'],
            (int) $actor['id']
        );
        return ['announcement_id' => (int) $this->pdo->lastInsertId()];
    }

    private function validateTournament(array $data, bool $isAdmin = false): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $gameId = (int) ($data['game_id'] ?? 0);
        $platformId = (int) ($data['platform_id'] ?? 0);
        $coverImageId = (int) ($data['cover_image_id'] ?? 0);
        $game = trim((string) ($data['game'] ?? ''));
        $format = trim((string) ($data['format'] ?? ''));
        $visibility = trim((string) ($data['visibility'] ?? 'public'));
        $fundingModel = trim((string) ($data['funding_model'] ?? 'free_casual'));
        $currency = strtoupper(trim((string) ($data['currency'] ?? DEFAULT_CURRENCY)));
        $entryFee = round((float) ($data['entry_fee_amount'] ?? $data['entry_fee'] ?? 0), 2);
        $kickoffContribution = round((float) ($data['kickoff_contribution_amount'] ?? 0), 2);
        $platformFee = round((float) ($data['platform_fee_percent'] ?? PLATFORM_FEE_PERCENT), 2);
        $prizeTemplate = 'auto';
        $legs = trim((string) ($data['match_legs'] ?? 'best_of_1'));
        $maxPlayers = (int) ($data['max_players'] ?? 0);
        $start = trim((string) ($data['start_date'] ?? ''));
        $deadline = trim((string) ($data['registration_deadline'] ?? ''));

        if ($name === '' || strlen($name) > 100) throw new HttpException('Tournament name is required and must be under 100 characters.', 422);
        if (!$this->catalog->gameExists($gameId)) throw new HttpException('Select a supported game.', 422);
        if (!$this->catalog->platformExists($platformId)) throw new HttpException('Select Mobile, PC, or Console.', 422);
        if (!$this->catalog->coverExists($coverImageId)) throw new HttpException('Choose a KICKOFF cover image.', 422);
        if (strlen($description) > 5000) throw new HttpException('Description is too long.', 422);
        if (!in_array($format, TournamentCatalogService::FORMATS, true)) throw new HttpException('Invalid tournament format.', 422);
        if (!in_array($visibility, ['public', 'private'], true)) throw new HttpException('Invalid visibility.', 422);
        if (!in_array($fundingModel, TournamentCatalogService::FUNDING_MODELS, true)) throw new HttpException('Invalid funding model.', 422);
        if (!preg_match('/^[A-Z]{3}$/', $currency)) throw new HttpException('Invalid currency.', 422);
        if ($entryFee < 0 || $kickoffContribution < 0 || $platformFee < 0 || $platformFee > 50) throw new HttpException('Invalid tournament financial values.', 422);
        if (!$isAdmin) {
            $kickoffContribution = 0.0;
        }
        if ($fundingModel === 'free_casual') {
            $entryFee = 0.0;
            $kickoffContribution = 0.0;
        }
        if ($fundingModel === 'participant_funded' && $entryFee <= 0) throw new HttpException('Participant-funded tournaments require an entry fee.', 422);
        if ($fundingModel === 'kickoff_sponsored' && !$isAdmin) throw new HttpException('Only administrators can create KICKOFF-sponsored tournaments.', 403);
        if (!in_array($legs, ['best_of_1', 'best_of_3', 'best_of_5'], true)) throw new HttpException('Invalid match-leg setting.', 422);
        if ($format === '1v1') {
            $maxPlayers = 2;
            $legs = 'best_of_3';
        } else {
            $legs = 'best_of_1';
        }
        if ($maxPlayers < 2 || $maxPlayers > 128) throw new HttpException('Capacity must be between 2 and 128.', 422);
        if ($format === 'full_knockout' && !in_array($maxPlayers, [4, 8, 16, 32, 64], true)) {
            throw new HttpException('Full Knockout tournaments support 4, 8, 16, 32, or 64 players.', 422);
        }
        if ($format === 'group_knockout' && !in_array($maxPlayers, [8, 16, 32], true)) {
            throw new HttpException('Group Stage + Knockout tournaments support 8, 16, or 32 players.', 422);
        }
        if ($format === 'group_knockout' && $legs !== 'best_of_1') {
            throw new HttpException('Group-stage tournaments use Single Game fixtures.', 422);
        }

        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $deadlineDate = DateTimeImmutable::createFromFormat('!Y-m-d', $deadline);
        if (!$startDate || $startDate->format('Y-m-d') !== $start || !$deadlineDate || $deadlineDate->format('Y-m-d') !== $deadline) {
            throw new HttpException('Enter valid tournament dates.', 422);
        }
        if ($deadlineDate > $startDate) throw new HttpException('Registration deadline cannot be after the start date.', 422);

        $gameNameStmt = $this->pdo->prepare('SELECT name FROM games WHERE id = :id');
        $gameNameStmt->execute([':id' => $gameId]);
        $game = (string) $gameNameStmt->fetchColumn();
        $prizeAmount = $this->finance->projectedPrizePool($fundingModel, $maxPlayers, $entryFee, $kickoffContribution, $platformFee);
        $prize = $this->finance->formatMoney($prizeAmount, $currency);

        return [
            'name' => $name, 'description' => $description, 'game' => $game, 'game_id' => $gameId,
            'platform_id' => $platformId, 'cover_image_id' => $coverImageId, 'format' => $format,
            'visibility' => $visibility, 'prize_pool' => $prize !== '' ? $prize : 'Community bragging rights',
            'funding_model' => $fundingModel, 'currency' => $currency, 'entry_fee_amount' => $entryFee,
            'prize_pool_amount' => $prizeAmount, 'kickoff_contribution_amount' => $kickoffContribution,
            'prize_template' => $prizeTemplate, 'platform_fee_percent' => $platformFee,
            'match_legs' => $legs, 'max_players' => $maxPlayers,
            'start_date' => $start, 'registration_deadline' => $deadline,
        ];
    }

    private function canView(array $tournament, ?array $viewer, string $inviteToken): bool
    {
        if ($tournament['visibility'] === 'public') return true;
        if ($inviteToken !== '' && empty($tournament['invite_revoked_at'])) {
            $hash = (string) ($tournament['share_token_hash'] ?? '');
            if (($hash !== '' && hash_equals($hash, hash('sha256', $inviteToken)))
                || hash_equals((string) ($tournament['share_token'] ?? ''), $inviteToken)) {
                return true;
            }
        }
        if (!$viewer) return false;
        if (($viewer['role'] ?? '') === 'admin' || (int) $tournament['creator_id'] === (int) $viewer['id']) return true;
        return $this->isMember((int) $tournament['id'], (int) $viewer['id']);
    }

    private function registrationAcceptsJoins(array $tournament): bool
    {
        if (($tournament['status'] ?? '') !== 'open') return false;
        $now = time();
        $opensAt = !empty($tournament['registration_opens_at']) ? strtotime((string) $tournament['registration_opens_at']) : null;
        $closesAt = !empty($tournament['registration_deadline']) ? strtotime((string) $tournament['registration_deadline'] . ' 23:59:59') : null;
        if ($opensAt !== null && $opensAt !== false && $now < $opensAt) return false;
        if ($closesAt !== null && $closesAt !== false && $now > $closesAt) return false;
        return true;
    }

    private function isMember(int $tournamentId, int $userId): bool
    {
        if ($userId < 1) return false;
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM tournament_players
             WHERE tournament_id = :tournament AND user_id = :user AND status != 'withdrawn' LIMIT 1"
        );
        $stmt->execute([':tournament' => $tournamentId, ':user' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    private function requireManager(array $actor, array $tournament): void
    {
        if (($actor['role'] ?? '') !== 'admin' && (int) $tournament['creator_id'] !== (int) $actor['id']) {
            throw new HttpException('Only the organizer or an administrator may perform this action.', 403);
        }
    }

    private function requireAdmin(array $actor): void
    {
        if (($actor['role'] ?? '') !== 'admin') {
            throw new HttpException('Only an administrator may perform this action.', 403);
        }
    }

    private function isAdminUser(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchColumn() === 'admin';
    }

    private function requireCompletedProfile(int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT profile_setup_completed FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new HttpException('Complete your game profile before joining competitive tournaments.', 403);
        }
    }

    private function requireCompatibleGameProfile(int $userId, array $tournament): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM game_profiles
             WHERE user_id = :user AND game_id = :game AND platform_id = :platform
               AND status = 'active' LIMIT 1"
        );
        $stmt->execute([
            ':user' => $userId,
            ':game' => (int) $tournament['game_id'],
            ':platform' => (int) $tournament['platform_id'],
        ]);
        if (!$stmt->fetchColumn()) {
            throw new HttpException('Add a matching game/platform identity before joining this tournament.', 403);
        }
    }

    private function scheduleCheckIn(int $tournamentId): void
    {
        $filledAt = time();
        $startsAt = $filledAt + max(1, AUTO_START_HOURS_AFTER_FILL) * 3600;
        $closesAt = max($filledAt, $startsAt - max(0, CHECK_IN_CLOSE_MINUTES_BEFORE_AUTO_START) * 60);
        $opensAt = max($filledAt, $closesAt - max(0, CHECK_IN_WINDOW_MINUTES) * 60);
        $opens = date('Y-m-d H:i:s', $opensAt);
        $closes = date('Y-m-d H:i:s', $closesAt);
        $starts = date('Y-m-d H:i:s', $startsAt);
        $this->pdo->prepare(
            "UPDATE tournaments
             SET check_in_opens_at = :opens,
                 check_in_closes_at = :closes,
                 auto_start_at = :starts,
                 lifecycle_note = 'Registration filled; automatic start scheduled within 12 hours.'
             WHERE id = :id"
        )->execute([':opens' => $opens, ':closes' => $closes, ':starts' => $starts, ':id' => $tournamentId]);
    }

    private function resetFullSchedule(int $tournamentId, string $note): void
    {
        $this->pdo->prepare(
            'UPDATE tournaments
             SET check_in_opens_at = NULL,
                 check_in_closes_at = NULL,
                 auto_start_at = NULL,
                 lifecycle_note = :note
             WHERE id = :id AND status = "open"'
        )->execute([':note' => $note, ':id' => $tournamentId]);
    }

    private function loadTournamentSchedule(int $tournamentId): array
    {
        $stmt = $this->pdo->prepare('SELECT check_in_opens_at, check_in_closes_at, auto_start_at FROM tournaments WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $tournamentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function displayDateTime(?string $value): string
    {
        if (!$value) return 'the scheduled time';
        $time = strtotime($value);
        return $time ? date('H:i \o\n F j', $time) : $value;
    }

    private function pagination(int $total, int $page, int $limit): array
    {
        return ['total' => $total, 'page' => $page, 'limit' => $limit,
            'total_pages' => (int) ceil($total / max(1, $limit)),
            'has_next' => $page * $limit < $total, 'has_prev' => $page > 1];
    }
}
