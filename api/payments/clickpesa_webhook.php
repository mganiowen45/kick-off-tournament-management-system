<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Api;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;

Api::run(
    function (Request $request): never {
        /*
         * ClickPesa sends webhooks using POST.
         */
        $request->requireMethod('POST');

        /*
         * Read the raw request body.
         */
        $raw = file_get_contents(
            'php://input'
        ) ?: '';

        /*
         * Decode JSON payload.
         */
        $payload = json_decode(
            $raw,
            true
        );

        /*
         * Fall back to the framework's parsed data
         * if the raw body wasn't JSON.
         */
        if (!is_array($payload)) {
            $payload = $request->data();
        }

        if (!is_array($payload)) {
            throw new \App\Core\HttpException(
                'Invalid webhook payload.',
                422
            );
        }

        /*
         * ClickPesa may send the checksum as a header
         * depending on the webhook configuration.
         */
        $checksumHeader =
            $request->header(
                'X-ClickPesa-Checksum'
            );

        /*
         * Process the payment.
         */
        $service = new PaymentService(
            Database::connection()
        );

        $result =
            $service->handleClickPesaWebhook(
                $payload,
                $checksumHeader
            );

        /*
         * ClickPesa requires a 2xx response to acknowledge
         * webhook delivery.
         */
        Response::success(
            $result,
            'Webhook accepted.'
        );
    }
);