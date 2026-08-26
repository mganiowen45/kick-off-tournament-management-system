<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Api;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Services\MatchContactService;

Api::run(function (Request $request): array {
    $request->requireMethod('GET');
    $user = Auth::requireUser();
    $matchId = $request->queryInteger('match_id');
    if ($matchId < 1) {
        throw new HttpException('Match ID is required.', 422);
    }
    return (new MatchContactService(Database::connection()))->availability($matchId, (int) $user['id']);
});
