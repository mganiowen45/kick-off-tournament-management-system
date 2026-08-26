<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ResultService;

final class ResultController
{
    private ResultService $service;

    public function __construct()
    {
        $this->service = new ResultService(Database::connection());
    }

    public function submit(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireUser();
        Response::success(
            $this->service->submit((int) $user['id'], $request->data(), $_FILES),
            'Result submitted successfully.',
            201
        );
    }

    public function get(Request $request): never
    {
        $request->requireMethod('GET');
        $user = Auth::requireUser();
        $matchId = $request->queryInteger('match_id');
        if ($matchId < 1) throw new HttpException('Match ID is required.', 422);
        Response::success($this->service->get($matchId, $user));
    }
}

