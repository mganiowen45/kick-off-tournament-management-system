<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class ClickPesaService
{
    public function createCheckout(array $payment, array $user, array $tournament): array
    {
        if (PAYMENT_MODE === 'disabled') {
            throw new HttpException('Online payments are not enabled yet.', 503);
        }

        if (PAYMENT_MODE === 'sandbox' || PAYMENT_API_KEY === '') {
            return [
                'checkout_url' => APP_URL . '/tournament_detail.html?id=' . (int) $tournament['id']
                    . '&payment=sandbox&reference=' . rawurlencode((string) $payment['order_reference']),
                'provider_reference' => 'sandbox-' . (string) $payment['order_reference'],
                'mode' => 'sandbox',
            ];
        }

        $payload = [
            'totalPrice' => number_format((float) $payment['amount'], 2, '.', ''),
            'orderReference' => (string) $payment['order_reference'],
            'orderCurrency' => (string) $payment['currency'],
            'customerName' => trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?: (string) $user['username'],
            'customerEmail' => (string) $user['email'],
            'customerPhone' => $this->phoneWithoutPlus((string) ($user['whatsapp_number'] ?? '')),
            'description' => 'KICKOFF entry fee: ' . (string) $tournament['name'],
            'callbackUrl' => APP_URL . '/api/payments/clickpesa_webhook.php',
        ];

        $response = $this->postJson(
            'https://api.clickpesa.com/third-parties/checkout-link/generate-checkout-url',
            $payload,
            PAYMENT_API_KEY
        );

        if (empty($response['checkoutLink'])) {
            throw new HttpException('ClickPesa did not return a checkout link.', 502);
        }

        return [
            'checkout_url' => (string) $response['checkoutLink'],
            'provider_reference' => (string) ($response['clientId'] ?? ''),
            'mode' => 'live',
        ];
    }

    public function verifyWebhook(array $payload, ?string $checksumHeader = null): bool
    {
        if (PAYMENT_MODE === 'sandbox') {
            return true;
        }

        // ClickPesa webhook payloads can include checksum fields, but the exact
        // merchant checksum configuration must be matched before live acceptance.
        // Keep live mode fail-closed until those dashboard settings are known.
        return false;
    }

    private function postJson(string $url, array $payload, string $token): array
    {
        $ch = curl_init($url);
        if (!$ch) {
            throw new HttpException('Could not initialize ClickPesa request.', 500);
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            throw new HttpException('ClickPesa checkout request failed.' . ($error ? ' ' . $error : ''), 502);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new HttpException('ClickPesa returned an invalid response.', 502);
        }
        return $decoded;
    }

    private function phoneWithoutPlus(string $phone): string
    {
        return ltrim(preg_replace('/\D+/', '', $phone) ?? '', '0');
    }
}
