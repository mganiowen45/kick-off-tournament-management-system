<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Api;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Services\MatchSchedulingService;

Api::run(function (Request $request): array {
    $request->requireMethod('POST');
    $request->requireCsrf();
    $user = Auth::requireCompletedPlayerProfile();
    return (new MatchSchedulingService(Database::connection()))->reject((int) $user['id'], $request->data());
});
