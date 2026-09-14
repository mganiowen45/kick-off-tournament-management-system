<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
use App\Core\Api; use App\Core\Auth; use App\Core\Database; use App\Core\HttpException; use App\Core\Request; use App\Core\Response; use App\Services\PayoutService;
Api::run(function(Request $request): never {
    $request->requireMethod('POST','GET'); $admin=Auth::requireAdmin(); $service=new PayoutService(Database::connection());
    if($request->method()==='GET'){ $id=$request->queryInteger('payout_id'); if($id<1)throw new HttpException('Payout ID is required.',422); Response::success($service->getPayout($id)); }
    $request->requireCsrf(); $action=trim((string)$request->input('action','process')); $tid=$request->integer('tournament_id'); $pid=$request->integer('payout_id');
    if($action==='preview'){ if($tid<1)throw new HttpException('Tournament ID is required.',422); Response::success($service->previewChampionPayout($admin,$tid),'Payout preview generated.'); }
    if($action==='process'){ if($tid<1)throw new HttpException('Tournament ID is required.',422); Response::success($service->authorizeChampionPayout($admin,$tid,trim((string)$request->input('admin_note',''))),'Payout submitted.'); }
    if($action==='status'){ if($pid<1)throw new HttpException('Payout ID is required.',422); Response::success($service->reconcileOne($pid,(int)$admin['id']),'Payout status reconciled.'); }
    if($action==='verify_method'){ $methodId=$request->integer('method_id'); if($methodId<1)throw new HttpException('Payout method ID is required.',422); Response::success($service->verifyPayoutMethod($admin,$methodId),'Payout destination verified.'); }
    throw new HttpException('Invalid payout action.',422);
});
