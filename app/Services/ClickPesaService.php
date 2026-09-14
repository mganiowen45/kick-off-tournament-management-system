<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class ClickPesaService
{
    /**
     * Generate a ClickPesa Hosted Checkout Link.
     */
    public function createCheckout(
        array $payment,
        array $user,
        array $tournament
    ): array {
        if (PAYMENT_MODE === 'disabled') {
            throw new HttpException(
                'Online payments are not enabled yet.',
                503
            );
        }

        /*
         * Sandbox mode is useful for local development.
         */
        if (
            PAYMENT_MODE === 'sandbox'
            || PAYMENT_API_KEY === ''
        ) {
            return [
                'checkout_url' =>
                    APP_URL
                    . '/tournament_detail.html?id='
                    . (int) $tournament['id']
                    . '&payment=sandbox&reference='
                    . rawurlencode(
                        (string) $payment['order_reference']
                    ),

                'provider_reference' =>
                    'sandbox-'
                    . (string) $payment['order_reference'],

                'mode' => 'sandbox',
            ];
        }

        if (PAYMENT_API_KEY === '') {
            throw new HttpException(
                'ClickPesa API credentials are not configured.',
                503
            );
        }

        $customerName = trim(
            (string) (
                ($user['first_name'] ?? '')
                . ' '
                . ($user['last_name'] ?? '')
            )
        );

        if ($customerName === '') {
            $customerName = (string) (
                $user['username'] ?? 'KICKOFF Player'
            );
        }

        $phone = $this->normalizeCustomerPhone(
            (string) ($user['whatsapp_number'] ?? ''),
            (string) ($user['whatsapp_country_code'] ?? '255')
        );

        if ($phone === '') {
            throw new HttpException(
                'A valid WhatsApp/mobile phone number is required before making a payment.',
                422
            );
        }

        /*
         * ClickPesa Hosted Checkout API payload.
         *
         * Current ClickPesa documentation requires:
         * totalPrice
         * orderReference
         * orderCurrency
         *
         * Customer fields are optional individually, but supplying
         * them gives the checkout a better customer experience.
         */
        $payload = [
            'totalPrice' => number_format(
                (float) $payment['amount'],
                2,
                '.',
                ''
            ),

            'orderReference' =>
                (string) $payment['order_reference'],

            'orderCurrency' =>
                (string) (
                    $payment['currency']
                    ?? DEFAULT_CURRENCY
                ),

            'customerName' => $customerName,

            'customerEmail' =>
                (string) ($user['email'] ?? ''),

            'customerPhone' => $phone,

            'description' =>
                'KICKOFF tournament entry fee: '
                . (string) ($tournament['name'] ?? 'Tournament'),

            /*
             * This is the optional per-checkout callback.
             *
             * Application-level ClickPesa webhooks should ALSO be
             * configured in the ClickPesa dashboard.
             */
            'callbackUrl' =>
                APP_URL
                . '/api/payments/clickpesa_webhook.php',
        ];

        /*
         * Add checksum when checksum security is enabled/configured.
         */
        $payload = $this->withChecksum($payload);

        $response = $this->postJson(
            CLICKPESA_API_URL
                . '/third-parties/checkout-link/generate-checkout-url',
            $payload,
            PAYMENT_API_KEY
        );

        $checkoutLink = trim(
            (string) ($response['checkoutLink'] ?? '')
        );

        if ($checkoutLink === '') {
            throw new HttpException(
                'ClickPesa did not return a checkout link.',
                502
            );
        }

        return [
            'checkout_url' => $checkoutLink,

            'provider_reference' =>
                (string) ($response['clientId'] ?? ''),

            'mode' => 'live',

            'raw' => $response,
        ];
    }

    /**
     * Verify an incoming ClickPesa webhook.
     */
    public function verifyWebhook(
        array $payload,
        ?string $checksumHeader = null
    ): bool {
        /*
         * Sandbox webhooks are not real ClickPesa requests.
         */
        if (PAYMENT_MODE === 'sandbox') {
            return true;
        }

        $receivedChecksum = trim(
            (string) (
                $checksumHeader
                ?: ($payload['checksum'] ?? '')
            )
        );

        /*
         * If checksum security is enabled in ClickPesa,
         * the webhook must contain a checksum.
         */
        if (
            PAYMENT_WEBHOOK_SECRET === ''
            || $receivedChecksum === ''
        ) {
            return false;
        }

        $payloadForVerification = $payload;

        unset(
            $payloadForVerification['checksum'],
            $payloadForVerification['checksumMethod']
        );

        $computedChecksum = $this->checksum(
            $payloadForVerification,
            PAYMENT_WEBHOOK_SECRET
        );

        return hash_equals(
            $computedChecksum,
            $receivedChecksum
        );
    }

    /**
     * Query a ClickPesa payment using the order reference.
     */
    public function queryPaymentStatus(
        string $orderReference
    ): array {
        if (PAYMENT_MODE === 'disabled') {
            throw new HttpException(
                'ClickPesa payments are not enabled.',
                503
            );
        }

        if (
            PAYMENT_MODE === 'sandbox'
            || PAYMENT_API_KEY === ''
        ) {
            return [
                'status' => 'SUCCESS',
                'provider_reference' =>
                    'sandbox-' . $orderReference,
                'amount' => null,
                'currency' => null,
                'mode' => 'sandbox',
                'raw' => [],
            ];
        }

        $url =
            CLICKPESA_API_URL
            . '/third-parties/payments/'
            . rawurlencode($orderReference);

        $response = $this->getJson(
            $url,
            PAYMENT_API_KEY
        );

        /*
         * ClickPesa currently returns an array for this endpoint.
         */
        $data = $response;

        if (
            isset($response[0])
            && is_array($response[0])
        ) {
            $data = $response[0];
        }

        return [
            'status' =>
                strtoupper(
                    (string) ($data['status'] ?? '')
                ),

            'provider_reference' =>
                $data['id']
                ?? $data['paymentReference']
                ?? null,

            'amount' =>
                isset($data['collectedAmount'])
                    ? (float) $data['collectedAmount']
                    : null,

            'currency' =>
                $data['collectedCurrency']
                ?? null,

            'mode' => 'live',

            'raw' => $response,
        ];
    }

    /**
     * Normalize a Tanzanian customer phone number.
     *
     * Examples:
     * 0712345678 + 255 -> 255712345678
     * 712345678 + 255 -> 255712345678
     * 255712345678 -> 255712345678
     */
    private function normalizeCustomerPhone(
        string $number,
        string $countryCode = '255'
    ): string {
        $countryCode = preg_replace(
            '/\D+/',
            '',
            $countryCode
        ) ?? '';

        $number = preg_replace(
            '/\D+/',
            '',
            $number
        ) ?? '';

        if ($number === '') {
            return '';
        }

        /*
         * Already international.
         */
        if (
            $countryCode !== ''
            && str_starts_with(
                $number,
                $countryCode
            )
        ) {
            return $number;
        }

        /*
         * Number begins with 00.
         */
        if (str_starts_with($number, '00')) {
            return substr($number, 2);
        }

        /*
         * Local Tanzanian number.
         */
        if (str_starts_with($number, '0')) {
            return $countryCode
                . substr($number, 1);
        }

        /*
         * Number entered without leading zero.
         */
        if (
            $countryCode !== ''
            && !str_starts_with(
                $number,
                $countryCode
            )
        ) {
            return $countryCode . $number;
        }

        return $number;
    }

    /**
     * Add ClickPesa checksum to an outgoing payload.
     */
    private function withChecksum(
        array $payload
    ): array {
        /*
         * If checksum security is disabled in ClickPesa,
         * the payload can be sent without a checksum.
         */
        if (PAYMENT_API_SECRET === '') {
            return $payload;
        }

        $payloadForChecksum = $payload;

        unset(
            $payloadForChecksum['checksum'],
            $payloadForChecksum['checksumMethod']
        );

        $payload['checksum'] = $this->checksum(
            $payloadForChecksum,
            PAYMENT_API_SECRET
        );

        return $payload;
    }

    /**
     * Generate ClickPesa HMAC-SHA256 checksum.
     *
     * ClickPesa canonicalizes payload keys recursively,
     * sorts object keys alphabetically, serializes to compact
     * JSON, then signs using HMAC-SHA256.
     */
    private function checksum(
        array $payload,
        string $secret
    ): string {
        $canonicalPayload =
            $this->canonicalize($payload);

        $json = json_encode(
            $canonicalPayload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new HttpException(
                'Could not generate ClickPesa checksum.',
                500
            );
        }

        return hash_hmac(
            'sha256',
            $json,
            $secret
        );
    }

    /**
     * Recursively sort associative-array keys.
     */
    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        /*
         * Preserve indexed arrays.
         */
        if (array_is_list($value)) {
            return array_map(
                fn(mixed $item) =>
                    $this->canonicalize($item),
                $value
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] =
                $this->canonicalize($item);
        }

        return $value;
    }

    /**
     * HTTP POST JSON helper.
     */
    private function postJson(
        string $url,
        array $payload,
        string $token
    ): array {
        $ch = curl_init($url);

        if (!$ch) {
            throw new HttpException(
                'Could not initialize ClickPesa request.',
                500
            );
        }

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            curl_close($ch);

            throw new HttpException(
                'Could not encode ClickPesa request.',
                500
            );
        }

        $authorization = str_starts_with(
            $token,
            'Bearer '
        )
            ? $token
            : 'Bearer ' . $token;

        curl_setopt_array(
            $ch,
            [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . $authorization,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],

                CURLOPT_POSTFIELDS => $json,

                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
            ]
        );

        $raw = curl_exec($ch);

        $status = (int) curl_getinfo(
            $ch,
            CURLINFO_RESPONSE_CODE
        );

        $error = curl_error($ch);

        curl_close($ch);

        if ($raw === false) {
            throw new HttpException(
                'ClickPesa request failed.'
                . ($error !== ''
                    ? ' ' . $error
                    : ''),
                502
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new HttpException(
                'ClickPesa rejected the checkout request. HTTP '
                . $status
                . '.',
                502
            );
        }

        $decoded = json_decode(
            (string) $raw,
            true
        );

        if (!is_array($decoded)) {
            throw new HttpException(
                'ClickPesa returned an invalid response.',
                502
            );
        }

        return $decoded;
    }

    /**
     * HTTP GET JSON helper.
     */
    private function getJson(
        string $url,
        string $token
    ): array {
        $ch = curl_init($url);

        if (!$ch) {
            throw new HttpException(
                'Could not initialize ClickPesa request.',
                500
            );
        }

        $authorization = str_starts_with(
            $token,
            'Bearer '
        )
            ? $token
            : 'Bearer ' . $token;

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . $authorization,
                    'Accept: application/json',
                ],

                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
            ]
        );

        $raw = curl_exec($ch);

        $status = (int) curl_getinfo(
            $ch,
            CURLINFO_RESPONSE_CODE
        );

        $error = curl_error($ch);

        curl_close($ch);

        if ($raw === false) {
            throw new HttpException(
                'ClickPesa status request failed.'
                . ($error !== ''
                    ? ' ' . $error
                    : ''),
                502
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new HttpException(
                'ClickPesa payment status request failed. HTTP '
                . $status
                . '.',
                502
            );
        }

        $decoded = json_decode(
            (string) $raw,
            true
        );

        if (!is_array($decoded)) {
            throw new HttpException(
                'ClickPesa returned an invalid status response.',
                502
            );
        }

        return $decoded;
    }
}