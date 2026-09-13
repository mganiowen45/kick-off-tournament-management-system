<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\TournamentCatalogService;
use App\Support\AvatarCatalog;
use App\Support\CoverImage;
use PDO;
use PDOException;

final class UserController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function session(Request $request): never
    {
        $request->requireMethod('GET');
        $user = Auth::user();
        if (!$user) {
            Response::success(['logged_in' => false, 'csrf_token' => Csrf::token()]);
        }

        $counts = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM notifications WHERE user_id = :id1 AND is_read = 0) AS notifications,
                (SELECT COUNT(*) FROM messages WHERE receiver_id = :id2 AND is_read = 0 AND type = 'direct') AS messages"
        );
        $counts->execute([':id1' => $user['id'], ':id2' => $user['id']]);
        $unread = $counts->fetch(PDO::FETCH_ASSOC) ?: ['notifications' => 0, 'messages' => 0];

        Response::success([
            'logged_in' => true,
            'user' => AvatarCatalog::decorate($user),
            'unread_notifications' => (int) $unread['notifications'],
            'unread_messages' => (int) $unread['messages'],
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function avatars(Request $request): never
    {
        $request->requireMethod('GET');
        Response::success(['avatars' => AvatarCatalog::all($this->pdo), 'default' => DEFAULT_AVATAR]);
    }

    public function catalog(Request $request): never
    {
        $request->requireMethod('GET');
        $catalog = new TournamentCatalogService($this->pdo);
        Response::success([
            'games' => $catalog->games(),
            'platforms' => $catalog->platforms(),
            'covers' => $catalog->covers(),
            'formats' => TournamentCatalogService::FORMAT_LABELS,
            'funding_models' => TournamentCatalogService::FUNDING_LABELS,
            'currency' => DEFAULT_CURRENCY,
        ]);
    }

    public function profile(Request $request): never
    {
        $request->requireMethod('GET');
        $viewer = Auth::user();
        $id = $request->queryInteger('id', (int) ($viewer['id'] ?? 0));
        if ($id < 1) {
            throw new HttpException('User ID is required.', 422);
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, first_name, last_name, country, timezone, preferred_game,
                    profile_setup_completed, theme_preference,
                    whatsapp_country_code, whatsapp_number, whatsapp_verified_at, whatsapp_contact_opt_in,
                    points, wins, losses, draws, total_matches, championships, avatar_url, bio, created_at
             FROM users WHERE id = :id AND status != 'banned' LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new HttpException('User not found.', 404);
        }
        if (!$viewer || (int) $viewer['id'] !== $id) {
            unset(
                $user['email'],
                $user['first_name'],
                $user['last_name'],
                $user['whatsapp_country_code'],
                $user['whatsapp_number'],
                $user['whatsapp_verified_at'],
                $user['whatsapp_contact_opt_in']
            );
        }
        $user['win_rate'] = $this->winRate((int) $user['wins'], (int) $user['total_matches']);
        $user = AvatarCatalog::decorate($user);

        $profilesStmt = $this->pdo->prepare(
            'SELECT gp.id, gp.game_id, gp.platform_id, gp.in_game_name, gp.team_name, gp.external_game_id,
                    gp.status, gp.is_primary, g.name AS game_name, p.name AS platform_name
             FROM game_profiles gp
             JOIN games g ON g.id = gp.game_id
             JOIN platforms p ON p.id = gp.platform_id
             WHERE gp.user_id = :id ORDER BY gp.is_primary DESC, gp.updated_at DESC'
        );
        $profilesStmt->execute([':id' => $id]);

        $history = $this->pdo->prepare(
            "SELECT m.id, m.tournament_id, m.player1_score, m.player2_score, m.played_at, t.name AS tournament_name,
                    opponent.id AS opponent_id, opponent.username AS opponent, opponent.avatar_url AS opponent_avatar,
                    CASE WHEN m.is_draw = 1 THEN 'draw' WHEN m.winner_id = :winner_id THEN 'win' ELSE 'loss' END AS result
             FROM matches m
             JOIN tournaments t ON t.id = m.tournament_id
             JOIN users opponent ON opponent.id = CASE WHEN m.player1_id = :side_id THEN m.player2_id ELSE m.player1_id END
             WHERE (m.player1_id = :p1 OR m.player2_id = :p2) AND m.status = 'confirmed'
             ORDER BY COALESCE(m.played_at, m.confirmed_at, m.updated_at) DESC LIMIT 20"
        );
        $history->execute([':winner_id' => $id, ':side_id' => $id, ':p1' => $id, ':p2' => $id]);
        $matchHistory = array_map(function (array $row): array {
            $row['opponent_avatar_url'] = AvatarCatalog::url($row['opponent_avatar'] ?? null);
            unset($row['opponent_avatar']);
            return $row;
        }, $history->fetchAll(PDO::FETCH_ASSOC));

        $tournamentsStmt = $this->pdo->prepare(
            'SELECT t.id, t.name, t.format, t.status, tp.status AS player_status, tp.league_points, tp.wins
             FROM tournament_players tp JOIN tournaments t ON t.id = tp.tournament_id
             WHERE tp.user_id = :id ORDER BY t.created_at DESC LIMIT 20'
        );
        $tournamentsStmt->execute([':id' => $id]);

        $achievementStmt = $this->pdo->prepare(
            'SELECT a.code, a.name, a.description, a.icon, ua.earned_at
             FROM user_achievements ua JOIN achievements a ON a.id = ua.achievement_id
             WHERE ua.user_id = :id ORDER BY ua.earned_at DESC'
        );
        $achievementStmt->execute([':id' => $id]);

        Response::success([
            'user' => $user,
            'gameProfiles' => $profilesStmt->fetchAll(PDO::FETCH_ASSOC),
            'matchHistory' => $matchHistory,
            'tournaments' => $tournamentsStmt->fetchAll(PDO::FETCH_ASSOC),
            'achievements' => $achievementStmt->fetchAll(PDO::FETCH_ASSOC),
            'is_own_profile' => $viewer && (int) $viewer['id'] === $id,
        ]);
    }

    public function update(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireUser();
        $data = $request->data();
        $fields = [];
        $params = [':id' => $user['id']];

        if (array_key_exists('username', $data)) {
            $username = trim((string) $data['username']);
            if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
                throw new HttpException('Invalid username format.', 422);
            }
            $fields[] = 'username = :username';
            $params[':username'] = $username;
        }
        if (array_key_exists('email', $data)) {
            $email = strtolower(trim((string) $data['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
                throw new HttpException('Enter a valid email address.', 422);
            }
            $fields[] = 'email = :email';
            $params[':email'] = $email;
        }
        foreach (['first_name' => 50, 'last_name' => 50, 'country' => 60, 'preferred_game' => 80] as $key => $max) {
            if (array_key_exists($key, $data)) {
                $value = trim((string) $data[$key]);
                if ($value === '' || strlen($value) > $max) {
                    throw new HttpException(ucwords(str_replace('_', ' ', $key)) . ' is invalid.', 422);
                }
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }
        if (array_key_exists('timezone', $data)) {
            $timezone = trim((string) $data['timezone']);
            if (!in_array($timezone, timezone_identifiers_list(), true)) {
                throw new HttpException('Select a valid timezone.', 422);
            }
            $fields[] = 'timezone = :timezone';
            $params[':timezone'] = $timezone;
        }
        if (array_key_exists('whatsapp_number', $data) || array_key_exists('whatsapp_contact_opt_in', $data)) {
            $whatsappNumber = trim((string) ($data['whatsapp_number'] ?? ''));
            $countryCode = trim((string) ($data['whatsapp_country_code'] ?? ''));
            $optIn = !empty($data['whatsapp_contact_opt_in']) ? 1 : 0;
            if ($whatsappNumber !== '' && !preg_match('/^\+[1-9]\d{7,14}$/', $whatsappNumber)) {
                throw new HttpException('WhatsApp number must use E.164 format, for example +255712345678.', 422);
            }
            if ($countryCode !== '' && !preg_match('/^\+\d{1,4}$/', $countryCode)) {
                throw new HttpException('Country calling code is invalid.', 422);
            }
            if ($optIn === 1 && $whatsappNumber === '') {
                throw new HttpException('Add a valid WhatsApp number before enabling opponent contact.', 422);
            }
            $fields[] = 'whatsapp_country_code = :whatsapp_country_code';
            $fields[] = 'whatsapp_number = :whatsapp_number';
            $fields[] = 'whatsapp_contact_opt_in = :whatsapp_contact_opt_in';
            $fields[] = 'whatsapp_contact_updated_at = NOW()';
            $params[':whatsapp_country_code'] = $countryCode !== '' ? $countryCode : null;
            $params[':whatsapp_number'] = $whatsappNumber !== '' ? $whatsappNumber : null;
            $params[':whatsapp_contact_opt_in'] = $optIn;
        }
        if (array_key_exists('bio', $data)) {
            $bio = trim((string) $data['bio']);
            if (strlen($bio) > 1000) {
                throw new HttpException('Bio must be 1000 characters or fewer.', 422);
            }
            $fields[] = 'bio = :bio';
            $params[':bio'] = $bio !== '' ? $bio : null;
        }
        if (array_key_exists('avatar', $data)) {
            $avatar = trim((string) $data['avatar']);
            if (!AvatarCatalog::isValid($avatar, $this->pdo)) {
                throw new HttpException('Select an avatar from the KICKOFF library.', 422);
            }
            $fields[] = 'avatar_url = :avatar';
            $params[':avatar'] = $avatar;
        }
        if (array_key_exists('theme_preference', $data)) {
            $theme = trim((string) $data['theme_preference']);
            if (!in_array($theme, ['esport', 'light', 'midnight'], true)) {
                throw new HttpException('Select a valid theme.', 422);
            }
            $fields[] = 'theme_preference = :theme_preference';
            $params[':theme_preference'] = $theme;
        }

        $gameProfile = $this->validatedGameProfile($data);
        if ($gameProfile !== null) {
            $fields[] = 'profile_setup_completed = 1';
        }

        $newPassword = (string) ($data['new_password'] ?? $data['password'] ?? '');
        if ($newPassword !== '') {
            $currentPassword = (string) ($data['current_password'] ?? '');
            $passwordStmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
            $passwordStmt->execute([':id' => $user['id']]);
            $hash = (string) $passwordStmt->fetchColumn();
            if ($currentPassword === '' || !password_verify($currentPassword, $hash)) {
                throw new HttpException('Your current password is incorrect.', 422);
            }
            if (strlen($newPassword) < 8 || strlen($newPassword) > 200) {
                throw new HttpException('New password must be between 8 and 200 characters.', 422);
            }
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if (!$fields && $gameProfile === null) {
            throw new HttpException('Nothing to update.', 422);
        }

        try {
            if ($fields) {
                $this->pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
            }
            if ($gameProfile !== null) {
                $this->upsertGameProfile((int) $user['id'], $gameProfile);
            }
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new HttpException('Username or email is already in use.', 409);
            }
            throw $exception;
        }

        if (isset($params[':username'])) {
            $_SESSION['username'] = $params[':username'];
        }
        Response::success([
            'avatar_url' => isset($params[':avatar']) ? AvatarCatalog::url($params[':avatar']) : null,
            'profile_setup_completed' => $gameProfile !== null ? true : null,
        ], 'Profile updated successfully.');
    }

    public function dashboard(Request $request): never
    {
        $request->requireMethod('GET');
        $user = AvatarCatalog::decorate(Auth::requireCompletedPlayerProfile());
        $user['win_rate'] = $this->winRate((int) $user['wins'], (int) $user['total_matches']);
        $uid = (int) $user['id'];

        $tournaments = $this->pdo->prepare(
            "SELECT t.id, t.creator_id, t.name, t.game, t.format, t.status, t.visibility,
                    t.prize_pool, t.current_round, t.max_players, t.current_players,
                    t.funding_model, t.currency, t.entry_fee_amount, t.prize_pool_amount,
                    t.kickoff_contribution_amount, t.start_date, t.auto_start_at, t.completed_at,
                    g.name AS game_name, p.name AS platform_name, cover.file_path AS cover_image_url,
                    creator.username AS creator_username,
                    tp.status AS my_status, tp.payment_status AS viewer_payment_status, 1 AS joined,
                    (SELECT pay.checkout_url
                     FROM payments pay
                     WHERE pay.tournament_id = t.id AND pay.user_id = :payment_uid
                       AND pay.status IN ('pending', 'requires_action') AND pay.checkout_url IS NOT NULL
                     ORDER BY pay.created_at DESC LIMIT 1) AS pending_checkout_url
             FROM tournament_players tp JOIN tournaments t ON t.id = tp.tournament_id
             LEFT JOIN games g ON g.id = t.game_id
             LEFT JOIN platforms p ON p.id = t.platform_id
             LEFT JOIN tournament_cover_images cover ON cover.id = t.cover_image_id
             JOIN users creator ON creator.id = t.creator_id
             WHERE tp.user_id = :uid ORDER BY t.created_at DESC LIMIT 10"
        );
        $tournaments->execute([':uid' => $uid, ':payment_uid' => $uid]);

        $matches = $this->pdo->prepare(
            "SELECT m.id, m.tournament_id, m.round_number, m.scheduled_at, m.status, t.name AS tournament_name,
                    t.game, t.format, t.match_legs, ms.status AS schedule_status, ms.proposed_start_utc,
                    ms.proposed_by, ms.confirmed_at,
                    opponent.username AS opponent_username, opponent.id AS opponent_id, opponent.avatar_url AS opponent_avatar,
                    CASE WHEN opponent.whatsapp_contact_opt_in = 1 AND opponent.whatsapp_number IS NOT NULL
                         THEN 1 ELSE 0 END AS opponent_whatsapp_available
             FROM matches m JOIN tournaments t ON t.id = m.tournament_id
             JOIN users opponent ON opponent.id = CASE WHEN m.player1_id = :side THEN m.player2_id ELSE m.player1_id END
             LEFT JOIN match_schedules ms ON ms.match_id = m.id
             WHERE (m.player1_id = :p1 OR m.player2_id = :p2) AND m.status IN ('scheduled', 'pending_result', 'disputed')
             ORDER BY m.scheduled_at IS NOT NULL, m.scheduled_at ASC, m.id ASC LIMIT 10"
        );
        $matches->execute([':side' => $uid, ':p1' => $uid, ':p2' => $uid]);
        $upcomingMatches = array_map(function (array $row): array {
            $row['opponent_avatar_url'] = AvatarCatalog::url($row['opponent_avatar'] ?? null);
            unset($row['opponent_avatar']);
            return $row;
        }, $matches->fetchAll(PDO::FETCH_ASSOC));

        $results = $this->pdo->prepare(
            "SELECT m.id, m.tournament_id, m.player1_score, m.player2_score, m.played_at, m.is_draw,
                    t.name AS tournament_name, opponent.username AS opponent_username, opponent.avatar_url AS opponent_avatar,
                    CASE WHEN m.is_draw = 1 THEN 'draw' WHEN m.winner_id = :winner THEN 'win' ELSE 'loss' END AS result
             FROM matches m JOIN tournaments t ON t.id = m.tournament_id
             JOIN users opponent ON opponent.id = CASE WHEN m.player1_id = :side THEN m.player2_id ELSE m.player1_id END
             WHERE (m.player1_id = :p1 OR m.player2_id = :p2) AND m.status = 'confirmed'
             ORDER BY COALESCE(m.played_at, m.confirmed_at) DESC LIMIT 7"
        );
        $results->execute([':winner' => $uid, ':side' => $uid, ':p1' => $uid, ':p2' => $uid]);
        $recentResults = array_map(function (array $row): array {
            $row['opponent_avatar_url'] = AvatarCatalog::url($row['opponent_avatar'] ?? null);
            unset($row['opponent_avatar']);
            return $row;
        }, $results->fetchAll(PDO::FETCH_ASSOC));

        $notifications = $this->pdo->prepare(
            "SELECT id, title, body, type, is_read, link_url, created_at
             FROM notifications
             WHERE user_id = :uid
             ORDER BY is_read ASC, created_at DESC LIMIT 6"
        );
        $notifications->execute([':uid' => $uid]);

        Response::success([
            'user' => $user,
            'myTournaments' => array_map(static function (array $row): array {
                $row['joined'] = (bool) $row['joined'];
                $row['cover_image_url'] = CoverImage::resolve($row['cover_image_url'] ?? null, (string) ($row['name'] ?? ''));
                $row['cover_image_path'] = $row['cover_image_url'];
                $row['format_label'] = TournamentCatalogService::formatLabel($row['format'] ?? '');
                $row['funding_label'] = TournamentCatalogService::fundingLabel($row['funding_model'] ?? '');
                return $row;
            }, $tournaments->fetchAll(PDO::FETCH_ASSOC)),
            'nextMatch' => $upcomingMatches[0] ?? null,
            'upcomingMatches' => $upcomingMatches,
            'recentResults' => $recentResults,
            'notifications' => $notifications->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function leaderboard(Request $request): never
    {
        $request->requireMethod('GET');
        ['page' => $page, 'limit' => $limit, 'offset' => $offset] = $request->pagination();
        $total = (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND role = 'player'")->fetchColumn();
        $stmt = $this->pdo->prepare(
            "SELECT ranked.* FROM (
                SELECT u.id, u.username, u.country, u.preferred_game, u.points, u.wins, u.losses, u.draws,
                       u.total_matches, u.championships, u.avatar_url,
                       CASE WHEN u.total_matches = 0 THEN 0 ELSE ROUND(u.wins / u.total_matches * 100, 1) END AS win_rate_pct,
                       RANK() OVER (ORDER BY u.points DESC, u.wins DESC, u.id ASC) AS global_rank
                FROM users u WHERE u.status = 'active' AND u.role = 'player'
             ) ranked ORDER BY global_rank ASC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $players = array_map(static fn(array $row): array => AvatarCatalog::decorate($row), $stmt->fetchAll(PDO::FETCH_ASSOC));

        Response::success([
            'players' => $players,
            'pagination' => $this->pagination($total, $page, $limit),
        ]);
    }

    private function winRate(int $wins, int $total): float
    {
        return $total > 0 ? round($wins / $total * 100, 1) : 0.0;
    }

    private function pagination(int $total, int $page, int $limit): array
    {
        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int) ceil($total / max(1, $limit)),
            'has_next' => $page * $limit < $total,
            'has_prev' => $page > 1,
        ];
    }

    private function validatedGameProfile(array $data): ?array
    {
        if (!array_key_exists('game_id', $data) && !array_key_exists('platform_id', $data) && !array_key_exists('in_game_name', $data)) {
            return null;
        }
        $gameId = (int) ($data['game_id'] ?? 0);
        $platformId = (int) ($data['platform_id'] ?? 0);
        $inGameName = trim((string) ($data['in_game_name'] ?? ''));
        $teamName = trim((string) ($data['team_name'] ?? ''));
        $externalId = trim((string) ($data['external_game_id'] ?? ''));

        $catalog = new TournamentCatalogService($this->pdo);
        if ($gameId < 1 || !$catalog->gameExists($gameId)) {
            throw new HttpException('Select a supported game.', 422);
        }
        if ($platformId < 1 || !$catalog->platformExists($platformId)) {
            throw new HttpException('Select Mobile, PC, or Console.', 422);
        }
        if ($inGameName === '' || strlen($inGameName) > 80) {
            throw new HttpException('Enter your in-game identity.', 422);
        }
        if (strlen($teamName) > 80 || strlen($externalId) > 120) {
            throw new HttpException('Game profile details are too long.', 422);
        }

        return [
            'game_id' => $gameId,
            'platform_id' => $platformId,
            'in_game_name' => $inGameName,
            'team_name' => $teamName !== '' ? $teamName : null,
            'external_game_id' => $externalId !== '' ? $externalId : null,
        ];
    }

    private function upsertGameProfile(int $userId, array $profile): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO game_profiles
                (user_id, game_id, platform_id, in_game_name, team_name, external_game_id, status, is_primary)
             VALUES (:user, :game, :platform, :ign, :team, :external, 'active', 1)
             ON DUPLICATE KEY UPDATE
                in_game_name = VALUES(in_game_name),
                team_name = VALUES(team_name),
                external_game_id = VALUES(external_game_id),
                status = 'active',
                is_primary = 1,
                updated_at = NOW()"
        );
        $stmt->execute([
            ':user' => $userId,
            ':game' => $profile['game_id'],
            ':platform' => $profile['platform_id'],
            ':ign' => $profile['in_game_name'],
            ':team' => $profile['team_name'],
            ':external' => $profile['external_game_id'],
        ]);
    }
}
