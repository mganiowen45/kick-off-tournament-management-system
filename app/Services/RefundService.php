<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use PDO;

final class RefundService
{
    private NotificationService $notifications;
    private TournamentFinanceService $finance;

    public function __construct(private readonly PDO $pdo)
    {
        $this->notifications = new NotificationService($pdo);
        $this->finance = new TournamentFinanceService();
    }

    /**
     * Process refunds for a cancelled tournament.
     * Only paid participants are refunded.
     */
    public function refundCancelledTournament(int $tournamentId, int $actorId): int
    {
        return Database::transaction(function () use ($tournamentId, $actorId): int {
            // Find all fully paid participants
            $stmt = $this->pdo->prepare("
                SELECT p.id as payment_id, p.amount, p.currency, p.user_id 
                FROM payments p
                JOIN tournament_players tp ON tp.tournament_id = p.tournament_id AND tp.user_id = p.user_id
                WHERE p.tournament_id = :tid 
                  AND p.status = 'paid'
                  AND tp.status != 'withdrawn'
            ");
            $stmt->execute([':tid' => $tournamentId]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $refundCount = 0;
            foreach ($payments as $payment) {
                // Check if refund already exists
                $check = $this->pdo->prepare("SELECT id FROM refunds WHERE payment_id = :pid");
                $check->execute([':pid' => $payment['payment_id']]);
                if ($check->fetchColumn()) {
                    continue;
                }

                $this->pdo->prepare("
                    INSERT INTO refunds (payment_id, amount, currency, reason, status, created_at)
                    VALUES (:pid, :amount, :currency, 'Tournament Cancelled', 'processing', NOW())
                ")->execute([
                    ':pid' => $payment['payment_id'],
                    ':amount' => $payment['amount'],
                    ':currency' => $payment['currency'],
                ]);
                $refundId = (int) $this->pdo->lastInsertId();

                $this->pdo->prepare("
                    UPDATE payments SET status = 'refunded' WHERE id = :pid
                ")->execute([':pid' => $payment['payment_id']]);

                // We document the lack of automated refund API in ClickPesa.
                // The refund remains in 'processing' state until manually resolved by admin.
                
                $this->notifications->create(
                    (int) $payment['user_id'],
                    'Refund Initiated',
                    'A refund has been initiated because the tournament was cancelled.',
                    'system',
                    'payments.html',
                    $actorId
                );
                
                $refundCount++;
            }

            return $refundCount;
        });
    }

    /**
     * Mark a refund as completed (processed manually via ClickPesa dashboard).
     */
    public function markRefundProcessed(int $refundId, int $actorId): void
    {
        Database::transaction(function () use ($refundId, $actorId): void {
            $stmt = $this->pdo->prepare("
                SELECT r.*, p.user_id, p.tournament_id 
                FROM refunds r 
                JOIN payments p ON p.id = r.payment_id 
                WHERE r.id = :id FOR UPDATE
            ");
            $stmt->execute([':id' => $refundId]);
            $refund = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$refund) {
                throw new HttpException('Refund not found.', 404);
            }

            if ($refund['status'] !== 'processing') {
                throw new HttpException('Refund is not in processing state.', 409);
            }

            $this->pdo->prepare("
                UPDATE refunds SET status = 'processed', processed_at = NOW() WHERE id = :id
            ")->execute([':id' => $refundId]);

            // Add an offsetting entry in the ledger
            $reference = 'REF-' . $refundId . '-PROCESSED';
            $this->pdo->prepare("
                INSERT INTO financial_ledger(user_id, tournament_id, payout_id, entry_type, direction, amount, currency, reference) 
                SELECT :u, :t, NULL, 'refund', 'credit', :a, :c, :r 
                WHERE NOT EXISTS(SELECT 1 FROM financial_ledger WHERE reference = :r2)
            ")->execute([
                ':u' => $refund['user_id'],
                ':t' => $refund['tournament_id'],
                ':a' => $refund['amount'],
                ':c' => $refund['currency'],
                ':r' => $reference,
                ':r2' => $reference,
            ]);

            $this->notifications->create(
                (int) $refund['user_id'],
                'Refund Processed',
                'Your refund has been successfully processed.',
                'system',
                'payments.html',
                $actorId
            );
        });
    }
}
