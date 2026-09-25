<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Database;
use App\Services\PayoutService;
use App\Services\PaymentService;

$limit = isset($argv[1]) ? max(1, min(200, (int) $argv[1])) : 50;
$pdo = Database::connection();

(new \App\Core\JobRunner($pdo))->run('ReconcileClickpesaPayouts', function($context) use ($pdo, $limit) {
    $count = (new PayoutService($pdo))->reconcilePending($limit);
    $payments = (new PaymentService($pdo))->reconcilePendingPayments($limit);
    
    $context->recordsProcessed = $count + $payments;
    $context->recordsSucceeded = $count + $payments;
    
    \App\Core\Logger::info('ReconcileClickpesaPayouts', 'Reconciliation finished', [
        'payouts' => $count,
        'payments' => $payments
    ]);
});
