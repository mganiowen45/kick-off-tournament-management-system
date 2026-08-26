<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use PDO;

final class PaymentService
{
    private ClickPesaService $clickPesa;

    public function __construct(private readonly PDO $pdo)
    {
        $this->clickPesa = new ClickPesaService();
    }

    public function createTournamentCheckout(array $user, array $tournament): array
    {
        $amount = (float) ($tournament['entry_fee_amount'] ?? 0);
        if ($amount <= 0) {
            return ['required' => false];
        }

        $orderReference = sprintf('KO-%d-%d-%s', (int) $tournament['id'], (int) $user['id'], strtoupper(bin2hex(random_bytes(4))));
        $expiresAt = date('Y-m-d H:i:s', time() + ((int) RESERVATION_EXPIRY_MINUTES * 60));

        $this->pdo->prepare(
            "INSERT INTO payments (user_id, tournament_id, order_reference, amount, currency, status, expires_at)
             VALUES (:user, :tournament, :reference, :amount, :currency, 'pending', :expires)"
        )->execute([
            ':user' => $user['id'],
            ':tournament' => $tournament['id'],
            ':reference' => $orderReference,
            ':amount' => $amount,
            ':currency' => $tournament['currency'] ?? DEFAULT_CURRENCY,
            ':expires' => $expiresAt,
        ]);

        $payment = [
            'id' => (int) $this->pdo->lastInsertId(),
            'order_reference' => $orderReference,
            'amount' => $amount,
            'currency' => $tournament['currency'] ?? DEFAULT_CURRENCY,
        ];
        $checkout = $this->clickPesa->createCheckout($payment, $user, $tournament);

        $this->pdo->prepare(
            "UPDATE payments SET checkout_url = :url, provider_reference = :provider_ref,
                    status = 'requires_action'
             WHERE id = :id"
        )->execute([
            ':url' => $checkout['checkout_url'],
            ':provider_ref' => $checkout['provider_reference'] !== '' ? $checkout['provider_reference'] : null,
            ':id' => $payment['id'],
        ]);

        return [
            'required' => true,
            'payment_id' => $payment['id'],
            'order_reference' => $orderReference,
            'checkout_url' => $checkout['checkout_url'],
            'expires_at' => $expiresAt,
            'mode' => $checkout['mode'],
        ];
    }

    public function handleClickPesaWebhook(array $payload, ?string $checksumHeader = null): array
    {
        $event = trim((string) ($payload['event'] ?? ''));
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $eventId = (string) ($data['id'] ?? hash('sha256', json_encode($payload)));
        $orderReference = (string) ($data['orderReference'] ?? '');
        if ($event === '' || $orderReference === '') {
            throw new HttpException('Invalid ClickPesa webhook payload.', 422);
        }

        $verified = $this->clickPesa->verifyWebhook($payload, $checksumHeader);
        if (!$verified) {
            throw new HttpException('ClickPesa webhook verification failed.', 403);
        }

        return Database::transaction(function () use ($payload, $event, $eventId, $orderReference): array {
            $insert = $this->pdo->prepare(
                "INSERT IGNORE INTO payment_webhook_events
                    (provider, event_id, event_type, order_reference, payload, verified)
                 VALUES ('clickpesa', :event_id, :event_type, :reference, :payload, 1)"
            );
            $insert->execute([
                ':event_id' => $eventId,
                ':event_type' => $event,
                ':reference' => $orderReference,
                ':payload' => json_encode($payload),
            ]);
            if ($insert->rowCount() === 0) {
                return ['duplicate' => true, 'order_reference' => $orderReference];
            }

            $paymentStmt = $this->pdo->prepare('SELECT * FROM payments WHERE order_reference = :ref FOR UPDATE');
            $paymentStmt->execute([':ref' => $orderReference]);
            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$payment) {
                throw new HttpException('Payment reference not found.', 404);
            }

            $data = $payload['data'] ?? [];
            $status = strtoupper((string) ($data['status'] ?? ''));
            $amount = (float) ($data['collectedAmount'] ?? $payment['amount']);
            $currency = (string) ($data['collectedCurrency'] ?? $payment['currency']);

            if ($event === 'PAYMENT RECEIVED' && $status === 'SUCCESS') {
                if (round($amount, 2) !== round((float) $payment['amount'], 2) || $currency !== $payment['currency']) {
                    throw new HttpException('Webhook payment amount or currency does not match.', 409);
                }
                $this->confirmPayment($payment);
            } elseif ($event === 'PAYMENT FAILED') {
                $this->markFailed($payment);
            }

            $this->pdo->prepare('UPDATE payment_webhook_events SET processed_at = NOW() WHERE provider = "clickpesa" AND event_id = :id')
                ->execute([':id' => $eventId]);

            return ['duplicate' => false, 'order_reference' => $orderReference];
        });
    }

    private function confirmPayment(array $payment): void
    {
        $this->pdo->prepare(
            "UPDATE payments SET status = 'paid', confirmed_at = NOW(), updated_at = NOW()
             WHERE id = :id AND status IN ('pending','requires_action')"
        )->execute([':id' => $payment['id']]);

        $this->pdo->prepare(
            "UPDATE tournament_players SET status = 'registered', payment_status = 'paid', reservation_expires_at = NULL
             WHERE tournament_id = :tournament AND user_id = :user AND status = 'registered'"
        )->execute([':tournament' => $payment['tournament_id'], ':user' => $payment['user_id']]);

        $this->pdo->prepare(
            "INSERT INTO financial_ledger
                (user_id, tournament_id, payment_id, entry_type, direction, amount, currency, reference)
             VALUES (:user, :tournament, :payment, 'entry_fee', 'credit', :amount, :currency, :reference)"
        )->execute([
            ':user' => $payment['user_id'],
            ':tournament' => $payment['tournament_id'],
            ':payment' => $payment['id'],
            ':amount' => $payment['amount'],
            ':currency' => $payment['currency'],
            ':reference' => $payment['order_reference'],
        ]);
    }

    private function markFailed(array $payment): void
    {
        $this->pdo->prepare("UPDATE payments SET status = 'failed', updated_at = NOW() WHERE id = :id")
            ->execute([':id' => $payment['id']]);
        $playerUpdate = $this->pdo->prepare(
            "UPDATE tournament_players
             SET status = 'withdrawn', payment_status = 'failed', reservation_expires_at = NULL
             WHERE tournament_id = :tournament AND user_id = :user
               AND status != 'withdrawn' AND payment_status = 'pending'"
        );
        $playerUpdate->execute([':tournament' => $payment['tournament_id'], ':user' => $payment['user_id']]);
        if ($playerUpdate->rowCount() > 0) {
            $this->pdo->prepare(
                "UPDATE tournaments
                 SET current_players = GREATEST(0, current_players - 1),
                     check_in_opens_at = NULL,
                     check_in_closes_at = NULL,
                     auto_start_at = NULL,
                     lifecycle_note = 'Payment failed; automatic start reset.'
                 WHERE id = :tournament AND status = 'open'"
            )->execute([':tournament' => $payment['tournament_id']]);
        }
    }
}
