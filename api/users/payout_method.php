<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
use App\Core\Api; use App\Core\Auth; use App\Core\Database; use App\Core\HttpException; use App\Core\Request; use App\Core\Response;
Api::run(function(Request $request): never {
    $user=Auth::requireUser();
    if(($user['role'] ?? '') !== 'player') throw new HttpException('Only players can manage payout destinations.',403);
    if($request->method()==='GET'){
        $s=Database::connection()->prepare('SELECT id,provider,phone_number,currency,is_verified,is_default,verified_at,created_at FROM user_payout_methods WHERE user_id=:u ORDER BY is_default DESC,id DESC');$s->execute([':u'=>$user['id']]);Response::success(['methods'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
    }
    $request->requireMethod('POST');$request->requireCsrf();$pdo=Database::connection();$phone=trim((string)$request->input('phone_number',''));$provider=trim((string)$request->input('provider','mobile_money'));$currency=trim((string)$request->input('currency',DEFAULT_CURRENCY));
    $digits=preg_replace('/\D+/','',$phone)??'';if(str_starts_with($digits,'0'))$digits='255'.substr($digits,1);if(!preg_match('/^255\d{9}$/',$digits))throw new HttpException('Enter a valid Tanzanian mobile-money number.',422);if(!in_array($currency,['TZS','USD'],true))throw new HttpException('Unsupported payout currency.',422);
    $pdo->beginTransaction();try{$pdo->prepare('UPDATE user_payout_methods SET is_default=0 WHERE user_id=:u')->execute([':u'=>$user['id']]);$pdo->prepare("INSERT INTO user_payout_methods(user_id,provider,phone_number,currency,is_verified,is_default) VALUES(:u,:p,:phone,:c,0,1) ON DUPLICATE KEY UPDATE provider=VALUES(provider),currency=VALUES(currency),is_verified=0,verified_at=NULL,is_default=1,updated_at=NOW()")->execute([':u'=>$user['id'],':p'=>$provider,':phone'=>$digits,':c'=>$currency]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    Response::success(['phone_number'=>$digits,'is_verified'=>0,'is_default'=>1],'Payout destination saved. An administrator must verify it before a prize can be paid.');
});
