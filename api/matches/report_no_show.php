<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Api;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\NoShowService;

Api::run(function (Request $request): never {
    $request->requireMethod('POST');
    $request->requireCsrf();
    $user = Auth::requireCompletedPlayerProfile();
    $matchId = $request->integer('match_id');
    if ($matchId < 1) throw new HttpException('Match ID is required.', 422);
    Response::success(
        (new NoShowService(App\Core\Database::connection()))->report(
            (int) $user['id'],
            $matchId,
            (string) $request->input('reason', '')
        ),
        'No-show report submitted.',
        201
    );
});
