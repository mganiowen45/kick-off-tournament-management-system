<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ChatService;

final class MessageController
{
    private ChatService $service;

    public function __construct()
    {
        $this->service = new ChatService(Database::connection());
    }

    public function send(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireUser();
        Response::success($this->service->send((int) $user['id'], $request->data()), 'Message sent.', 201);
    }

    public function conversation(Request $request): never
    {
        $request->requireMethod('GET');
        $user = Auth::requireUser();
        $otherId = $request->queryInteger('with');
        Response::success($this->service->conversation(
            (int) $user['id'], $otherId, max(0, $request->queryInteger('after_id')), max(1, $request->queryInteger('limit', 50))
        ));
    }

    public function group(Request $request): never
    {
        $request->requireMethod('GET');
        $user = Auth::requireUser();
        Response::success($this->service->group(
            (int) $user['id'], $request->queryInteger('tournament_id'),
            max(0, $request->queryInteger('after_id')), max(1, $request->queryInteger('limit', 50))
        ));
    }

    public function inbox(Request $request): never
    {
        $request->requireMethod('GET');
        $user = Auth::requireUser();
        Response::success($this->service->inbox((int) $user['id']));
    }

    public function markRead(Request $request): never
    {
        $request->requireMethod('POST');
        $request->requireCsrf();
        $user = Auth::requireUser();
        $senderId = $request->integer('sender_id');
        if ($senderId < 1) throw new HttpException('Sender ID is required.', 422);
        Response::success(['updated' => $this->service->markDirectRead((int) $user['id'], $senderId)]);
    }
}

