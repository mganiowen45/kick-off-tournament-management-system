<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use PDO;

final class PayoutService
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->clickPesa = new ClickPesaService();
        $this->notifications = new NotificationService($pdo);
        $this->audit = new AuditService($pdo);
        $this->finance = new TournamentFinanceService();
    }
    private ClickPesaService $clickPesa;
    private NotificationService $notifications;
    private AuditService $audit;
    private TournamentFinanceService $finance;

    public function previewChampionPayout(array $admin, int $tournamentId): array
    {
        $this->assertAdmin($admin);
        return Database::transaction(function() use($admin,$tournamentId){
            [$t,$prize]=$this->lockWinnerPrize($tournamentId);
            $method=$this->getDefaultMethod((int)$t['winner_id']);
            if (!$method) throw new HttpException('Winner has no verified payout destination. Ask the winner to add a mobile-money payout number.',409);
            $reference=$this->ensurePayoutRow($t,$prize,$method);
            $preview=$this->clickPesa->previewMobileMoneyPayout(['amount'=>$prize['amount'],'currency'=>$prize['currency'],'phone_number'=>$method['phone_number'],'order_reference'=>$reference]);
            $this->pdo->prepare("UPDATE payouts SET payout_method_id=:m, recipient_phone=:phone, provider_fee=:fee, previewed_at=NOW(), status=IF(status='pending','approved',status), last_error=NULL WHERE id=:id")->execute([':m'=>$method['id'],':phone'=>$method['phone_number'],':fee'=>$preview['fee']??null,':id'=>$this->payoutId($reference)]);
            $this->audit->record((int)$admin['id'],'payout_previewed','tournament',$tournamentId,['reference'=>$reference,'amount'=>$prize['amount'],'fee'=>$preview['fee']??null]);
            return ['payout_id'=>$this->payoutId($reference),'reference'=>$reference,'winner_id'=>(int)$t['winner_id'],'winner_username'=>$this->winnerName((int)$t['winner_id']),'amount'=>(float)$prize['amount'],'currency'=>$prize['currency'],'phone_number'=>$method['phone_number'],'preview'=>$preview,'status'=>'approved'];
        });
    }

    public function authorizeChampionPayout(array $admin, int $tournamentId, string $note = ''): array
    {
        $this->assertAdmin($admin);
        // Reserve the payout reference and mark it processing BEFORE calling ClickPesa.
        // This makes retries idempotent even if the provider succeeds but our HTTP request times out.
        $row = Database::transaction(function () use ($tournamentId, $note): array {
            [$t, $prize] = $this->lockWinnerPrize($tournamentId);
            $method = $this->getDefaultMethod((int)$t['winner_id']);
            if (!$method) throw new HttpException('Winner has no verified payout destination.', 409);
            $reference = $this->ensurePayoutRow($t, $prize, $method, $note);
            $row = $this->getPayoutByReference($reference, true);
            if ($row['status'] === 'paid') return $row;
            if (in_array($row['status'], ['processing','submitted'], true)) return $row;
            $this->pdo->prepare("UPDATE payouts SET status='processing', payout_method_id=:m, recipient_phone=:phone, approved_at=COALESCE(approved_at,NOW()), last_error=NULL WHERE id=:id AND status IN ('approved','pending','failed','reversed')")
                ->execute([':m'=>$method['id'], ':phone'=>$method['phone_number'], ':id'=>$row['id']]);
            $row = $this->getPayoutByReference($reference, true);
            $row['_method'] = $method;
            return $row;
        });

        if ($row['status'] === 'paid') return $this->result($row, true);
        if (in_array($row['status'], ['processing','submitted'], true) && empty($row['_method'])) return $this->result($row, true);
        $method = $row['_method'] ?? $this->getDefaultMethod((int)$row['user_id']);
        if (!$method) throw new HttpException('Verified payout destination is unavailable.', 409);

        try {
            $preview = $this->clickPesa->previewMobileMoneyPayout(['amount'=>$row['amount'], 'currency'=>$row['currency'], 'phone_number'=>$method['phone_number'], 'order_reference'=>$row['payout_reference']]);
            $provider = $this->clickPesa->createMobileMoneyPayout(['amount'=>$row['amount'], 'currency'=>$row['currency'], 'phone_number'=>$method['phone_number'], 'order_reference'=>$row['payout_reference']]);
            $status = $this->mapProviderStatus($provider['status'] ?? 'PROCESSING');
            $this->pdo->prepare("UPDATE payouts SET status=:status, provider_reference=:provider, provider_status=:provider_status, provider_fee=:fee, submitted_at=COALESCE(submitted_at,NOW()), last_provider_check_at=NOW(), last_error=NULL WHERE id=:id AND status='processing'")
                ->execute([':status'=>$status, ':provider'=>$provider['provider_reference']??null, ':provider_status'=>$provider['status']??null, ':fee'=>$provider['fee']??($preview['fee']??null), ':id'=>$row['id']]);
            $fresh=$this->getPayout((int)$row['id']);
            $this->pdo->prepare("UPDATE tournament_prizes SET status=:status,payout_id=:p,updated_at=NOW() WHERE tournament_id=:t AND user_id=:u AND placement=1")->execute([':status'=>$status==='paid'?'paid':($status==='reversed'?'reversed':($status==='failed'?'failed':'processing')),':p'=>$row['id'],':t'=>$row['tournament_id'],':u'=>$row['user_id']]);
            if ($status==='paid') $this->finalizePaid($fresh,$admin);
            else $this->notifications->create((int)$row['user_id'],'Prize payout initiated','Your prize payout is being processed by ClickPesa.','payout_pending','payments.html',(int)$admin['id']);
            $this->audit->record((int)$admin['id'],'payout_initiated','tournament',$tournamentId,['payout_id'=>$row['id'],'reference'=>$row['payout_reference'],'amount'=>$row['amount'],'provider_status'=>$provider['status']??null]);
            return $this->result($fresh,false);
        } catch (\Throwable $e) {
            $this->pdo->prepare("UPDATE payouts SET status='failed', failed_at=NOW(), last_error=:error WHERE id=:id AND status='processing'")
                ->execute([':error'=>mb_substr($e->getMessage(),0,500), ':id'=>$row['id']]);
            $this->pdo->prepare("UPDATE tournament_prizes SET status='failed' WHERE tournament_id=:t AND user_id=:u AND placement=1")->execute([':t'=>$row['tournament_id'],':u'=>$row['user_id']]);
            $this->notifications->create((int)$row['user_id'],'Prize payout failed','The prize payout could not be submitted. An administrator can retry it.','system','payments.html',(int)$admin['id']);
            throw $e;
        }
    }

    public function verifyPayoutMethod(array $admin, int $methodId): array
    {
        $this->assertAdmin($admin);
        $s=$this->pdo->prepare('SELECT * FROM user_payout_methods WHERE id=:id FOR UPDATE');$s->execute([':id'=>$methodId]);$method=$s->fetch(PDO::FETCH_ASSOC);if(!$method)throw new HttpException('Payout method not found.',404);
        $this->pdo->prepare('UPDATE user_payout_methods SET is_verified=1,verified_at=NOW(),is_default=1,updated_at=NOW() WHERE id=:id')->execute([':id'=>$methodId]);
        $this->pdo->prepare('UPDATE user_payout_methods SET is_default=0 WHERE user_id=:u AND id<>:id')->execute([':u'=>$method['user_id'],':id'=>$methodId]);
        $this->audit->record((int)$admin['id'],'payout_method_verified','user',(int)$method['user_id'],['method_id'=>$methodId,'phone_number'=>$method['phone_number']]);
        return ['id'=>$methodId,'user_id'=>(int)$method['user_id'],'phone_number'=>$method['phone_number'],'is_verified'=>1,'is_default'=>1];
    }

    public function getPayout(int $payoutId): array
    {
        $stmt=$this->pdo->prepare("SELECT po.*,u.username,t.name tournament_name,pm.provider payout_provider,pm.phone_number payout_phone,pm.is_verified FROM payouts po JOIN users u ON u.id=po.user_id JOIN tournaments t ON t.id=po.tournament_id LEFT JOIN user_payout_methods pm ON pm.id=po.payout_method_id WHERE po.id=:id");
        $stmt->execute([':id'=>$payoutId]); $row=$stmt->fetch(PDO::FETCH_ASSOC); if(!$row) throw new HttpException('Payout not found.',404); return $row;
    }

    public function reconcileOne(int $payoutId, ?int $actorId=null): array
    {
        $row=$this->getPayout($payoutId); if(in_array($row['status'],['paid','failed','reversed','cancelled'],true)) return $row;
        $provider=$this->clickPesa->queryPayoutStatus((string)$row['payout_reference']);
        $status=$this->mapProviderStatus($provider['status']??'PROCESSING');
        $this->pdo->prepare("UPDATE payouts SET status=:status,provider_status=:ps,provider_reference=COALESCE(:pr,provider_reference),last_provider_check_at=NOW(),paid_at=IF(:paid=1,COALESCE(paid_at,NOW()),paid_at),failed_at=IF(:failed=1,COALESCE(failed_at,NOW()),failed_at),reversed_at=IF(:reversed=1,COALESCE(reversed_at,NOW()),reversed_at),last_error=NULL WHERE id=:id")->execute([':status'=>$status,':ps'=>$provider['status']??null,':pr'=>$provider['provider_reference']??null,':paid'=>$status==='paid'?1:0,':failed'=>in_array($status,['failed'],true)?1:0,':reversed'=>$status==='reversed'?1:0,':id'=>$payoutId]);
        if($status==='paid') $this->finalizePaid($row,['id'=>$actorId]);
        elseif(in_array($status,['failed','reversed'],true)) $this->finalizeFailure($row,$status);
        if($actorId) $this->audit->record($actorId,'payout_reconciled','payout',$payoutId,['provider_status'=>$provider['status']??null,'local_status'=>$status]);
        return $this->getPayout($payoutId);
    }

    public function reconcilePending(int $limit=50): int
    {
        $stmt=$this->pdo->prepare("SELECT id FROM payouts WHERE status IN ('approved','processing','submitted') ORDER BY COALESCE(last_provider_check_at,created_at) ASC LIMIT :lim");
        $stmt->bindValue(':lim',max(1,min(200,$limit)),PDO::PARAM_INT);$stmt->execute();$count=0;
        foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $id){try{$this->reconcileOne((int)$id);$count++;}catch(\Throwable $e){error_log('[KICKOFF payout reconciliation] '.$e->getMessage());}}
        return $count;
    }

    public function handleClickPesaWebhook(array $payload): ?array
    {
        $event=strtoupper(trim((string)($payload['event']??''))); $data=is_array($payload['data']??null)?$payload['data']:[]; $reference=(string)($data['orderReference']??$data['order_reference']??'');
        if($reference==='') throw new HttpException('Payout reference is required.',422);
        $row=$this->getPayoutByReference($reference,true); $providerStatus=strtoupper((string)($data['status']??''));
        $status=$event==='PAYOUT REVERSED'?'reversed':($event==='PAYOUT REFUNDED'?'failed':$this->mapProviderStatus($providerStatus));
        $this->pdo->prepare("UPDATE payouts SET status=:status,provider_status=:ps,provider_reference=COALESCE(:pr,provider_reference),paid_at=IF(:paid=1,COALESCE(paid_at,NOW()),paid_at),failed_at=IF(:failed=1,COALESCE(failed_at,NOW()),failed_at),reversed_at=IF(:reversed=1,COALESCE(reversed_at,NOW()),reversed_at),last_provider_check_at=NOW() WHERE id=:id")->execute([':status'=>$status,':ps'=>$providerStatus?:null,':pr'=>$data['id']??$data['paymentReference']??null,':paid'=>$status==='paid'?1:0,':failed'=>$status==='failed'?1:0,':reversed'=>$status==='reversed'?1:0,':id'=>$row['id']]);
        $fresh=$this->getPayout((int)$row['id']);
        if($status==='paid') $this->finalizePaid($fresh,null); else if(in_array($status,['failed','reversed'],true)) $this->finalizeFailure($fresh,$status);
        return ['payout_id'=>(int)$row['id'],'status'=>$status,'idempotent'=>$row['status']===$status];
    }

    private function lockWinnerPrize(int $tournamentId): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM tournaments WHERE id=:id FOR UPDATE');$stmt->execute([':id'=>$tournamentId]);$t=$stmt->fetch(PDO::FETCH_ASSOC);if(!$t)throw new HttpException('Tournament not found.',404);if($t['status']!=='completed'||empty($t['winner_id']))throw new HttpException('Tournament must be completed with a champion before payout.',409);
        $this->finance->lockPrizeAllocations($this->pdo,$t);
        $p=$this->pdo->prepare('SELECT * FROM tournament_prizes WHERE tournament_id=:t AND placement=1 FOR UPDATE');$p->execute([':t'=>$tournamentId]);$prize=$p->fetch(PDO::FETCH_ASSOC);if(!$prize||$prize['amount']<=0)throw new HttpException('No settled winner prize is available.',409);return [$t,$prize];
    }
    private function getDefaultMethod(int $userId): ?array { $s=$this->pdo->prepare("SELECT * FROM user_payout_methods WHERE user_id=:u AND is_default=1 AND is_verified=1 ORDER BY id DESC LIMIT 1");$s->execute([':u'=>$userId]);return $s->fetch(PDO::FETCH_ASSOC)?:null; }
    private function ensurePayoutRow(array $t,array $prize,array $method,string $note=''): string { $s=$this->pdo->prepare('SELECT * FROM payouts WHERE tournament_id=:t AND user_id=:u FOR UPDATE');$s->execute([':t'=>$t['id'],':u'=>$t['winner_id']]);$row=$s->fetch(PDO::FETCH_ASSOC);if($row&&in_array($row['status'],['processing','submitted','paid'],true))return (string)$row['payout_reference'];$ref=$row['payout_reference']??sprintf('KOP-%d-%d-%s',(int)$t['id'],(int)$t['winner_id'],strtoupper(bin2hex(random_bytes(4))));if(!$row){$this->pdo->prepare("INSERT INTO payouts(tournament_id,user_id,payout_method_id,recipient_phone,amount,currency,status,payout_reference,admin_note,approved_at) VALUES(:t,:u,:m,:phone,:amount,:currency,'approved',:ref,:note,NOW())")->execute([':t'=>$t['id'],':u'=>$t['winner_id'],':m'=>$method['id'],':phone'=>$method['phone_number'],':amount'=>$prize['amount'],':currency'=>$prize['currency'],':ref'=>$ref,':note'=>$note?:null]);}else{$this->pdo->prepare("UPDATE payouts SET payout_method_id=:m,recipient_phone=:phone,amount=:amount,currency=:currency,status='approved',admin_note=:note,approved_at=NOW() WHERE id=:id AND status IN ('pending','failed','reversed')")->execute([':m'=>$method['id'],':phone'=>$method['phone_number'],':amount'=>$prize['amount'],':currency'=>$prize['currency'],':note'=>$note?:null,':id'=>$row['id']]);}return $ref; }
    private function getPayoutByReference(string $ref,bool $lock=false): array {$s=$this->pdo->prepare('SELECT * FROM payouts WHERE payout_reference=:r'.($lock?' FOR UPDATE':''));$s->execute([':r'=>$ref]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)throw new HttpException('Payout reference not found.',404);return $r;}
    private function payoutId(string $ref): int { $s=$this->pdo->prepare('SELECT id FROM payouts WHERE payout_reference=:r');$s->execute([':r'=>$ref]);return (int)$s->fetchColumn(); }
    private function winnerName(int $id): string {$s=$this->pdo->prepare('SELECT username FROM users WHERE id=:id');$s->execute([':id'=>$id]);return (string)$s->fetchColumn();}
    private function mapProviderStatus(string $status): string { return match(strtoupper($status)){ 'SUCCESS','SETTLED'=>'paid','AUTHORIZED','PENDING','PROCESSING','INITIATED','ON-HOLD'=>'processing','FAILED'=>'failed','REFUNDED'=>'failed','REVERSED'=>'reversed',default=>'processing'}; }
    private function assertAdmin(array $admin): void { if(($admin['role']??'')!=='admin')throw new HttpException('Only an administrator may manage prize payouts.',403); }
    private function result(array $row,bool $idempotent): array {return ['payout_id'=>(int)$row['id'],'status'=>$row['status'],'provider_reference'=>$row['provider_reference']??null,'recipient_phone'=>$row['recipient_phone']??null,'amount'=>(float)$row['amount'],'currency'=>$row['currency'],'idempotent'=>$idempotent];}
    private function finalizePaid(array $row,?array $actor): void { $this->pdo->prepare("UPDATE tournament_prizes SET status='paid',payout_id=:p WHERE tournament_id=:t AND user_id=:u AND placement=1")->execute([':p'=>$row['id'],':t'=>$row['tournament_id'],':u'=>$row['user_id']]);$this->insertLedgerOnce((int)$row['user_id'],(int)$row['tournament_id'],(int)$row['id'],'payout','debit',(float)$row['amount'],(string)$row['currency'],(string)$row['payout_reference'].':paid');$this->notifications->create((int)$row['user_id'],'Prize payout successful','Your tournament prize payout has been confirmed.','prize_credited','payments.html',$actor['id']??null); }
    private function finalizeFailure(array $row,string $status): void {$this->pdo->prepare("UPDATE tournament_prizes SET status=:s WHERE tournament_id=:t AND user_id=:u AND placement=1")->execute([':s'=>$status,':t'=>$row['tournament_id'],':u'=>$row['user_id']]);$this->notifications->create((int)$row['user_id'],'Prize payout '.$status,'Your tournament prize payout requires administrator review.','system','payments.html');}
    private function insertLedgerOnce(?int $userId,?int $tournamentId,?int $payoutId,string $type,string $direction,float $amount,string $currency,string $reference): void {$this->pdo->prepare("INSERT INTO financial_ledger(user_id,tournament_id,payout_id,entry_type,direction,amount,currency,reference) SELECT :u,:t,:p,:type,:dir,:a,:c,:r WHERE NOT EXISTS(SELECT 1 FROM financial_ledger WHERE reference=:r2)")->execute([':u'=>$userId,':t'=>$tournamentId,':p'=>$payoutId,':type'=>$type,':dir'=>$direction,':a'=>$amount,':c'=>$currency,':r'=>$reference,':r2'=>$reference]);}
}
