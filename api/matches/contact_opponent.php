<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\MatchContactService;

try {
    $user = Auth::requireUser();
    $matchId = (int) ($_GET['match_id'] ?? $_POST['match_id'] ?? 0);
    if ($matchId < 1) {
        throw new HttpException('Match ID is required.', 422);
    }
    (new MatchContactService(Database::connection()))->redirectToOpponent($matchId, (int) $user['id']);
} catch (HttpException $exception) {
    Response::error($exception->getMessage(), $exception->status());
} catch (Throwable $exception) {
    error_log('[KICKOFF contact] ' . $exception->getMessage());
    Response::error('Unable to open WhatsApp contact.', 500);
}
