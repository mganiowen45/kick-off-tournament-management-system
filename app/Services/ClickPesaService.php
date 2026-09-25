<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class ClickPesaService
{
    /**
     * Cached bearer token for this request lifecycle, so a single
     * PHP request that makes more than one ClickPesa call doesn't
     * re-authenticate every time.
     */
    private ?string $authToken = null;

    /**
     * Exchange the ClickPesa client-id/api-key pair for a short-lived
     * bearer token. ClickPesa's API does not accept the raw api-key as
     * an Authorization: Bearer value directly — it must first be
     * exchanged for a token via this endpoint.
     */
    private function getAuthToken(): string
    {
        if ($this->authToken !== null) {
            return $this->authToken;
        }

        if (CLICKPESA_CLIENT_ID === '' || PAYMENT_API_KEY === '') {
            throw new HttpException(
                'ClickPesa API credentials are not configured.',
                503
            );
        }

        $ch = curl_init(CLICKPESA_API_URL . '/third-parties/generate-token');

        if (!$ch) {
            throw new HttpException('Could not initialize ClickPesa authentication request.', 500);
        }

        // ClickPesa token generation is an API request, not a browser GET.
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'client-id: ' . CLICKPESA_CLIENT_ID,
                'api-key: ' . PAYMENT_API_KEY,
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new HttpException(
                'ClickPesa authentication request failed.' . ($error !== '' ? ' ' . $error : ''),
                502
            );
        }

        $decoded = json_decode((string) $raw, true);

        if ($status < 200 || $status >= 300) {
            $providerMessage = '';
            if (is_array($decoded)) {
                $providerMessage = trim((string) (
                    $decoded['message']
                    ?? $decoded['error']
                    ?? $decoded['errorMessage']
                    ?? ($decoded['data']['message'] ?? '')
                ));
            }

            throw new HttpException(
                'ClickPesa rejected the authentication request. HTTP ' . $status
                . ($providerMessage !== '' ? ': ' . $providerMessage : '.'),
                502
            );
        }

        // Accept the common token field variants used by API responses.
        $token = '';
        if (is_array($decoded)) {
            $token = trim((string) (
                $decoded['token']
                ?? $decoded['accessToken']
                ?? $decoded['access_token']
                ?? ($decoded['data']['token'] ?? '')
                ?? ($decoded['data']['accessToken'] ?? '')
                ?? ($decoded['data']['access_token'] ?? '')
            ));
        }

        if ($token === '') {
            throw new HttpException(
                'ClickPesa authentication succeeded but no access token was returned. HTTP ' . $status . '.',
                502
            );
        }

        $this->authToken = $token;
        return $token;
    }

    /**
     * Initiate a ClickPesa USSD-PUSH collection.
     *
     * This sends the payment prompt to the customer's mobile-money
     * handset. The customer enters their PIN on the handset; KICKOFF
     * never receives or stores the PIN.
     */
    public function initiateUssdPush(
        array $payment,
        string $phone
    ): array {
        if (PAYMENT_MODE === 'disabled') {
            throw new HttpException('Online payments are not enabled yet.', 503);
        }

        if (PAYMENT_MODE === 'sandbox') {
            return [
                'provider_reference' => 'sandbox-' . $payment['order_reference'],
                'status' => 'PROCESSING',
                'mode' => 'sandbox',
                'raw' => [],
            ];
        }

        $normalizedPhone = $this->normalizeCustomerPhone($phone, '255');
        if (!preg_match('/^255[67]\d{8}$/', $normalizedPhone)) {
            throw new HttpException(
                'Enter a valid Tanzanian mobile-money number, for example 0712345678.',
                422
            );
        }

        if (PAYMENT_API_KEY === '' || CLICKPESA_CLIENT_ID === '') {
            throw new HttpException('ClickPesa API credentials are not configured.', 503);
        }

        $payload = [
            'amount' => (string) (int) round((float) $payment['amount']),
            'currency' => (string) ($payment['currency'] ?? DEFAULT_CURRENCY),
            'orderReference' => (string) $payment['order_reference'],
            'phoneNumber' => $normalizedPhone,
        ];

        $token = $this->getAuthToken();

        // Validate the phone, amount and available mobile-money methods first.
        $previewPayload = $this->withChecksum(array_merge($payload, ['fetchSenderDetails' => false]));
        $preview = $this->postJson(
            CLICKPESA_API_URL . '/third-parties/payments/preview-ussd-push-request',
            $previewPayload,
            $token
        );

        $methods = is_array($preview['activeMethods'] ?? null)
            ? $preview['activeMethods']
            : [];
        $available = array_filter(
            $methods,
            static fn ($method): bool => is_array($method) && strtoupper((string) ($method['status'] ?? '')) === 'AVAILABLE'
        );
        if ($available === []) {
            throw new HttpException(
                'No mobile-money payment method is currently available for this number.',
                409
            );
        }

        $initiatePayload = $this->withChecksum($payload);
        $response = $this->postJson(
            CLICKPESA_API_URL . '/third-parties/payments/initiate-ussd-push-request',
            $initiatePayload,
            $token
        );

        $status = strtoupper(trim((string) ($response['status'] ?? 'PROCESSING')));
        $providerReference = trim((string) (
            $response['id']
            ?? $response['paymentReference']
            ?? $response['transactionId']
            ?? ''
        ));

        return [
            'provider_reference' => $providerReference !== '' ? $providerReference : null,
            'status' => $status,
            'mode' => 'live',
            'raw' => $response,
        ];
    }

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
        if (PAYMENT_MODE === 'sandbox') {
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

        if (PAYMENT_API_KEY === '' || CLICKPESA_CLIENT_ID === '') {
            throw new HttpException(
                'ClickPesa API credentials are not configured.',
                503
            );
        }

        /*
         * ClickPesa Hosted Checkout API payload.
         *
         * ClickPesa requires:
         * orderItems  — array of {name, product_type, price, quantity}
         * orderReference
         * merchantId  — the CLICKPESA_CLIENT_ID
         *
         * Optional: callbackURL, customer fields via metadata.
         */
        $payload = [
            'orderItems' => [
                [
                    'name' => 'KICKOFF tournament entry fee: '
                        . (string) ($tournament['name'] ?? 'Tournament'),
                    'product_type' => 'DIGITAL_PRODUCT',
                    'unit' => '1 entry',
                    'price' => (int) round(
                        (float) $payment['amount']
                    ),
                    'quantity' => 1,
                ],
            ],

            'orderReference' =>
                (string) $payment['order_reference'],

            'merchantId' => CLICKPESA_CLIENT_ID,

            /*
             * This is the optional per-checkout callback.
             *
             * Application-level ClickPesa webhooks should ALSO be
             * configured in the ClickPesa dashboard.
             */
            'callbackURL' =>
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
            $this->getAuthToken()
        );

        $checkoutLink = trim(
            (string) (
                $response['checkoutUrl']
                ?? $response['checkoutLink']
                ?? ''
            )
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

        if (PAYMENT_MODE === 'sandbox') {
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

        if (PAYMENT_API_KEY === '' || CLICKPESA_CLIENT_ID === '') {
            throw new HttpException(
                'ClickPesa API credentials are not configured.',
                503
            );
        }

        $url =
            CLICKPESA_API_URL
            . '/third-parties/payments/'
            . rawurlencode($orderReference);

        $response = $this->getJson(
            $url,
            $this->getAuthToken()
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
            $providerMessage = '';
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $candidate =
                    $decoded['message']
                    ?? $decoded['error']
                    ?? $decoded['errorMessage']
                    ?? ($decoded['data']['message'] ?? null);

                if (is_array($candidate)) {
                    $providerMessage = json_encode(
                        $candidate,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ) ?: '';
                } elseif ($candidate !== null) {
                    $providerMessage = trim(
                        (string) $candidate
                    );
                }

                /*
                 * If no top-level message, dump the whole
                 * body so we can debug field-level errors.
                 */
                if ($providerMessage === '') {
                    $providerMessage = json_encode(
                        $decoded,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ) ?: '';
                }
            }

            error_log(
                '[KICKOFF ClickPesa] HTTP '
                . $status . ' | '
                . $providerMessage
                . ' | Request URL: ' . $url
            );

            throw new HttpException(
                'ClickPesa rejected the checkout request. HTTP '
                . $status
                . ($providerMessage !== ''
                    ? ': ' . $providerMessage
                    : '.'),
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