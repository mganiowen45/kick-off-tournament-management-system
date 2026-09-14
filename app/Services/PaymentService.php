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

    public function createTournamentCheckout(
    array $user,
    array $tournament
): array {
    $amount = (float) (
        $tournament['entry_fee_amount'] ?? 0
    );

    if ($amount <= 0) {
        return [
            'required' => false,
        ];
    }

    /*
     * Every checkout gets its own unique order reference.
     */
    $orderReference = sprintf(
        'KO-%d-%d-%s',
        (int) $tournament['id'],
        (int) $user['id'],
        strtoupper(bin2hex(random_bytes(4)))
    );

    $expiresAt = date(
        'Y-m-d H:i:s',
        time()
        + (
            (int) RESERVATION_EXPIRY_MINUTES
            * 60
        )
    );

    /*
     * Create our internal payment record FIRST.
     *
     * If ClickPesa fails afterwards, the surrounding
     * TournamentService transaction will roll back.
     */
    $insert = $this->pdo->prepare(
        "INSERT INTO payments
            (
                user_id,
                tournament_id,
                order_reference,
                amount,
                currency,
                status,
                expires_at
            )
         VALUES
            (
                :user,
                :tournament,
                :reference,
                :amount,
                :currency,
                'pending',
                :expires
            )"
    );

    $insert->execute([
        ':user' =>
            (int) $user['id'],

        ':tournament' =>
            (int) $tournament['id'],

        ':reference' =>
            $orderReference,

        ':amount' =>
            $amount,

        ':currency' =>
            (string) (
                $tournament['currency']
                ?? DEFAULT_CURRENCY
            ),

        ':expires' =>
            $expiresAt,
    ]);

    $payment = [
        'id' =>
            (int) $this->pdo->lastInsertId(),

        'order_reference' =>
            $orderReference,

        'amount' =>
            $amount,

        'currency' =>
            (string) (
                $tournament['currency']
                ?? DEFAULT_CURRENCY
            ),
    ];

    /*
     * Ask ClickPesa to create the Hosted Checkout Link.
     */
    $checkout = $this->clickPesa->createCheckout(
        $payment,
        $user,
        $tournament
    );

    /*
     * Store the checkout URL and ClickPesa application
     * reference.
     */
    $update = $this->pdo->prepare(
        "UPDATE payments
         SET
            checkout_url = :url,
            provider_reference = :provider_ref,
            status = 'requires_action',
            updated_at = NOW()
         WHERE id = :id"
    );

    $update->execute([
        ':url' =>
            $checkout['checkout_url'],

        ':provider_ref' =>
            !empty($checkout['provider_reference'])
                ? $checkout['provider_reference']
                : null,

        ':id' =>
            $payment['id'],
    ]);

    return [
        'required' => true,

        'payment_id' =>
            $payment['id'],

        'order_reference' =>
            $orderReference,

        'checkout_url' =>
            $checkout['checkout_url'],

        'expires_at' =>
            $expiresAt,

        'mode' =>
            $checkout['mode'],
    ];
}

    public function handleClickPesaWebhook(
    array $payload,
    ?string $checksumHeader = null
): array {
    $event = strtoupper(
        trim(
            (string) ($payload['event'] ?? '')
        )
    );

    $data = is_array(
        $payload['data'] ?? null
    )
        ? $payload['data']
        : [];

    if ($event === '') {
        throw new HttpException(
            'Invalid ClickPesa webhook event.',
            422
        );
    }

    $orderReference = trim(
        (string) (
            $data['orderReference']
            ?? ''
        )
    );

    if ($orderReference === '') {
        throw new HttpException(
            'ClickPesa webhook does not contain an order reference.',
            422
        );
    }

    /*
     * Verify the ClickPesa signature BEFORE changing
     * any payment records.
     */
    if (
        !$this->clickPesa->verifyWebhook(
            $payload,
            $checksumHeader
        )
    ) {
        throw new HttpException(
            'ClickPesa webhook verification failed.',
            403
        );
    }

    /*
     * Use ClickPesa transaction ID as event ID.
     *
     * If unavailable, hash the complete payload.
     */
    $eventId = trim(
        (string) (
            $data['id']
            ?? $data['paymentReference']
            ?? hash(
                'sha256',
                json_encode(
                    $payload,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                )
            )
        )
    );

    return Database::transaction(
        function () use (
            $payload,
            $event,
            $eventId,
            $orderReference
        ): array {
            /*
             * Idempotency:
             *
             * ClickPesa may retry a webhook. We must not
             * credit the same payment twice.
             */
            $insert = $this->pdo->prepare(
                "INSERT IGNORE INTO payment_webhook_events
                    (
                        provider,
                        event_id,
                        event_type,
                        order_reference,
                        payload,
                        verified
                    )
                 VALUES
                    (
                        'clickpesa',
                        :event_id,
                        :event_type,
                        :reference,
                        :payload,
                        1
                    )"
            );

            $insert->execute([
                ':event_id' =>
                    $eventId,

                ':event_type' =>
                    $event,

                ':reference' =>
                    $orderReference,

                ':payload' =>
                    json_encode(
                        $payload,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ),
            ]);

            /*
             * Already processed.
             */
            if ($insert->rowCount() === 0) {
                return [
                    'duplicate' => true,
                    'order_reference' =>
                        $orderReference,
                ];
            }

            /*
             * Payout events are handled by the existing
             * PayoutService.
             */
            if (
                in_array(
                    $event,
                    [
                        'PAYOUT INITIATED',
                        'PAYOUT REFUNDED',
                        'PAYOUT REVERSED',
                    ],
                    true
                )
            ) {
                $payoutResult =
                    (new PayoutService($this->pdo))
                        ->handleClickPesaWebhook(
                            $payload
                        );

                $this->pdo->prepare(
                    "UPDATE payment_webhook_events
                     SET processed_at = NOW()
                     WHERE provider = 'clickpesa'
                       AND event_id = :id"
                )->execute([
                    ':id' => $eventId,
                ]);

                return [
                    'duplicate' => false,

                    'order_reference' =>
                        $orderReference,

                    'payout' =>
                        $payoutResult,
                ];
            }

            /*
             * Locate our internal payment.
             */
            $paymentStmt = $this->pdo->prepare(
                "SELECT *
                 FROM payments
                 WHERE order_reference = :ref
                 FOR UPDATE"
            );

            $paymentStmt->execute([
                ':ref' =>
                    $orderReference,
            ]);

            $payment =
                $paymentStmt->fetch(
                    \PDO::FETCH_ASSOC
                );

            if (!$payment) {
                throw new HttpException(
                    'Payment reference not found.',
                    404
                );
            }

            $webhookData =
                is_array($payload['data'] ?? null)
                    ? $payload['data']
                    : [];

            $status = strtoupper(
                trim(
                    (string) (
                        $webhookData['status']
                        ?? ''
                    )
                )
            );

            $amount = (float) (
                $webhookData['collectedAmount']
                ?? $payment['amount']
            );

            $currency = strtoupper(
                trim(
                    (string) (
                        $webhookData['collectedCurrency']
                        ?? $payment['currency']
                    )
                )
            );

            /*
             * SUCCESSFUL PAYMENT
             */
            if (
                $event === 'PAYMENT RECEIVED'
                && $status === 'SUCCESS'
            ) {
                /*
                 * Never trust the amount supplied by the
                 * customer/browser.
                 *
                 * Compare ClickPesa amount with our DB amount.
                 */
                if (
                    round($amount, 2)
                    !== round(
                        (float) $payment['amount'],
                        2
                    )
                ) {
                    throw new HttpException(
                        'Webhook payment amount does not match the KICKOFF payment.',
                        409
                    );
                }

                if (
                    strtoupper($currency)
                    !== strtoupper(
                        (string) $payment['currency']
                    )
                ) {
                    throw new HttpException(
                        'Webhook payment currency does not match the KICKOFF payment.',
                        409
                    );
                }

                $this->confirmPayment(
                    $payment
                );
            }

            /*
             * FAILED PAYMENT
             */
            elseif (
                $event === 'PAYMENT FAILED'
            ) {
                $this->markFailed(
                    $payment
                );
            }

            /*
             * Mark webhook as processed.
             */
            $this->pdo->prepare(
                "UPDATE payment_webhook_events
                 SET processed_at = NOW()
                 WHERE provider = 'clickpesa'
                   AND event_id = :id"
            )->execute([
                ':id' => $eventId,
            ]);

            return [
                'duplicate' => false,

                'order_reference' =>
                    $orderReference,

                'event' =>
                    $event,

                'status' =>
                    $status,
            ];
        }
    );
}

    public function reconcilePendingPayments(int $limit=50): int
    {
        $stmt=$this->pdo->prepare("SELECT * FROM payments WHERE status IN ('pending','requires_action') ORDER BY COALESCE(updated_at,created_at) ASC LIMIT :lim");$stmt->bindValue(':lim',max(1,min(200,$limit)),PDO::PARAM_INT);$stmt->execute();$count=0;
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $payment){
            try{
                $provider=$this->clickPesa->queryPaymentStatus((string)$payment['order_reference']);$status=strtoupper((string)($provider['status']??''));
                if(in_array($status,['SUCCESS','SETTLED'],true)){
                    if($provider['amount']!==null && round((float)$provider['amount'],2)!==round((float)$payment['amount'],2)) throw new HttpException('Payment reconciliation amount mismatch.',409);
                    if($provider['currency']!==null && (string)$provider['currency']!==(string)$payment['currency']) throw new HttpException('Payment reconciliation currency mismatch.',409);
                    $locked=$this->pdo->prepare('SELECT * FROM payments WHERE id=:id FOR UPDATE');$locked->execute([':id'=>$payment['id']]);$fresh=$locked->fetch(PDO::FETCH_ASSOC);if($fresh && in_array($fresh['status'],['pending','requires_action'],true)){$this->confirmPayment($fresh);$count++;}
                } elseif($status==='FAILED'){ $locked=$this->pdo->prepare('SELECT * FROM payments WHERE id=:id FOR UPDATE');$locked->execute([':id'=>$payment['id']]);$fresh=$locked->fetch(PDO::FETCH_ASSOC);if($fresh)$this->markFailed($fresh);$count++; }
            }catch(\Throwable $e){error_log('[KICKOFF payment reconciliation] '.$e->getMessage());}
        }
        return $count;
    }

    private function confirmPayment(
    array $payment
): void {
    /*
     * Idempotency protection.
     *
     * If the webhook is delivered twice, only the first
     * request can change the payment to paid.
     */
    $update = $this->pdo->prepare(
        "UPDATE payments
         SET
            status = 'paid',
            confirmed_at = NOW(),
            updated_at = NOW()
         WHERE id = :id
           AND status IN ('pending', 'requires_action')"
    );

    $update->execute([
        ':id' =>
            $payment['id'],
    ]);

    /*
     * Already confirmed.
     */
    if ($update->rowCount() === 0) {
        return;
    }

    /*
     * Mark the tournament participant as paid.
     */
    $this->pdo->prepare(
        "UPDATE tournament_players
         SET
            status = 'registered',
            payment_status = 'paid',
            reservation_expires_at = NULL
         WHERE tournament_id = :tournament
           AND user_id = :user
           AND status = 'registered'"
    )->execute([
        ':tournament' =>
            $payment['tournament_id'],

        ':user' =>
            $payment['user_id'],
    ]);

    /*
     * Get tournament-specific platform fee.
     */
    $tournamentStmt = $this->pdo->prepare(
        "SELECT platform_fee_percent
         FROM tournaments
         WHERE id = :id
         LIMIT 1"
    );

    $tournamentStmt->execute([
        ':id' =>
            $payment['tournament_id'],
    ]);

    $feePercent = (float) (
        $tournamentStmt->fetchColumn()
        ?: PLATFORM_FEE_PERCENT
    );

    $entryAmount =
        (float) $payment['amount'];

    $platformFee = round(
        $entryAmount
        * max(
            0.0,
            min(100.0, $feePercent)
        )
        / 100,
        2
    );

    $prizePool = round(
        max(
            0.0,
            $entryAmount - $platformFee
        ),
        2
    );

    /*
     * Financial ledger.
     *
     * insertLedgerOnce() prevents duplicates.
     */
    $this->insertLedgerOnce(
        (int) $payment['user_id'],
        (int) $payment['tournament_id'],
        (int) $payment['id'],
        'entry_fee',
        'credit',
        $entryAmount,
        (string) $payment['currency'],
        $payment['order_reference']
            . ':entry_fee'
    );

    if ($platformFee > 0) {
        $this->insertLedgerOnce(
            null,
            (int) $payment['tournament_id'],
            (int) $payment['id'],
            'platform_fee',
            'credit',
            $platformFee,
            (string) $payment['currency'],
            $payment['order_reference']
                . ':platform_fee'
        );
    }

    if ($prizePool > 0) {
        $this->insertLedgerOnce(
            null,
            (int) $payment['tournament_id'],
            (int) $payment['id'],
            'prize_pool',
            'credit',
            $prizePool,
            (string) $payment['currency'],
            $payment['order_reference']
                . ':prize_pool'
        );
    }

    /*
     * Notify the player.
     */
    (new NotificationService($this->pdo))
        ->create(
            (int) $payment['user_id'],
            'Payment successful',
            'Your tournament entry payment has been confirmed.',
            'payment_successful',
            'tournament_detail.html?id='
                . $payment['tournament_id']
        );

    /*
     * If every required seat is now paid, schedule
     * the tournament.
     */
    $this->scheduleIfSettledFull(
        (int) $payment['tournament_id']
    );
}

    private function markFailed(
    array $payment
): void {
    /*
     * Only pending/requires_action payments can become failed.
     */
    $paymentUpdate = $this->pdo->prepare(
        "UPDATE payments
         SET
            status = 'failed',
            updated_at = NOW()
         WHERE id = :id
           AND status IN ('pending', 'requires_action')"
    );

    $paymentUpdate->execute([
        ':id' =>
            $payment['id'],
    ]);

    /*
     * Already processed.
     */
    if ($paymentUpdate->rowCount() === 0) {
        return;
    }

    /*
     * Release the tournament seat.
     */
    $playerUpdate = $this->pdo->prepare(
        "UPDATE tournament_players
         SET
            status = 'withdrawn',
            payment_status = 'failed',
            reservation_expires_at = NULL
         WHERE tournament_id = :tournament
           AND user_id = :user
           AND status != 'withdrawn'
           AND payment_status = 'pending'"
    );

    $playerUpdate->execute([
        ':tournament' =>
            $payment['tournament_id'],

        ':user' =>
            $payment['user_id'],
    ]);

    /*
     * If the failed payment had reserved a seat,
     * release that seat.
     */
    if ($playerUpdate->rowCount() > 0) {
        $this->pdo->prepare(
            "UPDATE tournaments
             SET
                current_players =
                    GREATEST(
                        0,
                        current_players - 1
                    ),

                check_in_opens_at = NULL,
                check_in_closes_at = NULL,
                auto_start_at = NULL,

                lifecycle_note =
                    'Payment failed; automatic start reset.'

             WHERE id = :tournament
               AND status = 'open'"
        )->execute([
            ':tournament' =>
                $payment['tournament_id'],
        ]);
    }

    /*
     * Notify the player.
     */
    (new NotificationService($this->pdo))
        ->create(
            (int) $payment['user_id'],
            'Payment failed',
            'Your tournament entry payment could not be confirmed.',
            'payment_failed',
            'tournament_detail.html?id='
                . $payment['tournament_id']
        );
}

    private function insertLedgerOnce(
        ?int $userId,
        ?int $tournamentId,
        ?int $paymentId,
        string $type,
        string $direction,
        float $amount,
        string $currency,
        string $reference
    ): void {
        $this->pdo->prepare(
            "INSERT INTO financial_ledger
                (user_id, tournament_id, payment_id, entry_type, direction, amount, currency, reference)
             SELECT :user, :tournament, :payment, :type, :direction, :amount, :currency, :reference
             WHERE NOT EXISTS (SELECT 1 FROM financial_ledger WHERE reference = :reference_check)"
        )->execute([
            ':user' => $userId,
            ':tournament' => $tournamentId,
            ':payment' => $paymentId,
            ':type' => $type,
            ':direction' => $direction,
            ':amount' => $amount,
            ':currency' => $currency,
            ':reference' => $reference,
            ':reference_check' => $reference,
        ]);
    }

    private function scheduleIfSettledFull(int $tournamentId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tournaments WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $tournamentId]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tournament || $tournament['status'] !== 'open' || !empty($tournament['auto_start_at'])) {
            return;
        }
        $settled = $this->pdo->prepare(
            "SELECT COUNT(*) FROM tournament_players
             WHERE tournament_id = :id AND status != 'withdrawn'
               AND payment_status IN ('not_required','paid')"
        );
        $settled->execute([':id' => $tournamentId]);
        if ((int) $settled->fetchColumn() < (int) $tournament['max_players']) {
            return;
        }

        $filledAt = time();
        $startsAt = $filledAt + max(1, AUTO_START_HOURS_AFTER_FILL) * 3600;
        $closesAt = max($filledAt, $startsAt - max(0, CHECK_IN_CLOSE_MINUTES_BEFORE_AUTO_START) * 60);
        $opensAt = max($filledAt, $closesAt - max(0, CHECK_IN_WINDOW_MINUTES) * 60);
        $schedule = $this->pdo->prepare(
            "UPDATE tournaments
             SET check_in_opens_at = :opens,
                 check_in_closes_at = :closes,
                 auto_start_at = :starts,
                 lifecycle_note = 'Paid seats settled; automatic start scheduled.'
             WHERE id = :id AND status = 'open' AND auto_start_at IS NULL"
        );
        $schedule->execute([
            ':opens' => date('Y-m-d H:i:s', $opensAt),
            ':closes' => date('Y-m-d H:i:s', $closesAt),
            ':starts' => date('Y-m-d H:i:s', $startsAt),
            ':id' => $tournamentId,
        ]);
        if ($schedule->rowCount() > 0) {
            (new NotificationService($this->pdo))->tournamentMembers(
                $tournamentId,
                'Tournament Full',
                "{$tournament['name']} is full with confirmed payments and automatic start is scheduled.",
                'tournament_update',
                "tournament_detail.html?id={$tournamentId}"
            );
        }
    }
}
