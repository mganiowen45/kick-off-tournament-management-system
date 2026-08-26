<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Api;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;

Api::run(function (Request $request): never {
    $request->requireMethod('POST');
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $request->data();
    }
    $service = new PaymentService(Database::connection());
    Response::success(
        $service->handleClickPesaWebhook($payload, $request->header('X-ClickPesa-Checksum')),
        'Webhook accepted.'
    );
});
