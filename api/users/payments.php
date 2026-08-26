<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Api;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

Api::run(function (Request $request): never {
    $request->requireMethod('GET');
    $user = Auth::requireCompletedPlayerProfile();
    $uid = (int) $user['id'];
    $pdo = Database::connection();

    $payments = $pdo->prepare("
        SELECT p.id, p.order_reference, p.amount, p.currency, p.status, p.created_at,
               t.name AS tournament_name
        FROM payments p
        LEFT JOIN tournaments t ON t.id = p.tournament_id
        WHERE p.user_id = :uid
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $payments->execute([':uid' => $uid]);

    $ledger = $pdo->prepare("
        SELECT l.entry_type, l.direction, l.amount, l.currency, l.reference, l.created_at,
               t.name AS tournament_name
        FROM financial_ledger l
        LEFT JOIN tournaments t ON t.id = l.tournament_id
        WHERE l.user_id = :uid
        ORDER BY l.created_at DESC
        LIMIT 100
    ");
    $ledger->execute([':uid' => $uid]);

    $payouts = $pdo->prepare("
        SELECT po.amount, po.currency, po.status, po.approved_at, po.paid_at, po.created_at,
               t.name AS tournament_name
        FROM payouts po
        LEFT JOIN tournaments t ON t.id = po.tournament_id
        WHERE po.user_id = :uid
        ORDER BY po.created_at DESC
        LIMIT 100
    ");
    $payouts->execute([':uid' => $uid]);

    $summary = $pdo->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) AS paid_total,
          COALESCE(SUM(CASE WHEN p.status IN ('pending','requires_action') THEN p.amount ELSE 0 END), 0) AS pending_total,
          COALESCE(SUM(CASE WHEN p.status = 'refunded' THEN p.amount ELSE 0 END), 0) AS refund_total,
          (SELECT COALESCE(SUM(amount), 0) FROM payouts WHERE user_id = :uid2 AND status IN ('approved','submitted','paid')) AS payout_total
        FROM payments p
        WHERE p.user_id = :uid
    ");
    $summary->execute([':uid' => $uid, ':uid2' => $uid]);

    Response::success([
        'summary' => $summary->fetch(PDO::FETCH_ASSOC) ?: [],
        'payments' => $payments->fetchAll(PDO::FETCH_ASSOC),
        'ledger' => $ledger->fetchAll(PDO::FETCH_ASSOC),
        'payouts' => $payouts->fetchAll(PDO::FETCH_ASSOC),
    ]);
});
