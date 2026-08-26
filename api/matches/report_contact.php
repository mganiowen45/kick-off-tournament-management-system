<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Api;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Services\MatchContactService;

Api::run(function (Request $request): array {
    $request->requireMethod('POST');
    $request->requireCsrf();
    $user = Auth::requireUser();
    return (new MatchContactService(Database::connection()))->report((int) $user['id'], $request->data());
});
