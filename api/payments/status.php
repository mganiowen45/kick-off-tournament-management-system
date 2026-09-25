<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Api;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;

Api::run(
    function (Request $request): never {
        $request->requireMethod('GET');
        $user = Auth::requireCompletedPlayerProfile();
        $paymentId = $request->queryInteger('payment_id');
        if ($paymentId < 1) {
            throw new HttpException('Payment ID is required.', 422);
        }

        $service = new PaymentService(Database::connection());
        $status = $service->getPaymentStatusForUser(
            (int) $user['id'],
            $paymentId,
            true
        );

        Response::success($status);
    }
);
