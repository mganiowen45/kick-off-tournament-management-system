<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Api;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Services\MatchSchedulingService;

Api::run(function (Request $request): array {
    $request->requireMethod('POST');
    $request->requireCsrf();
    $user = Auth::requireCompletedPlayerProfile();
    $matchId = $request->integer('match_id');
    if ($matchId < 1) {
        throw new HttpException('Match ID is required.', 422);
    }
    return (new MatchSchedulingService(Database::connection()))->confirm((int) $user['id'], $matchId);
});
