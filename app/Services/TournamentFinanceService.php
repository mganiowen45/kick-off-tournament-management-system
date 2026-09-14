<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use PDO;

final class TournamentFinanceService
{
    public function prizeDistribution(string $format, int $maxPlayers, float $prizePool): array
    {
        if ($prizePool <= 0) return [];
        if ($format === '1v1' || $maxPlayers < 8) return [['place' => 'Winner', 'percent' => 100.0, 'amount' => round($prizePool, 2)]];
        if ($maxPlayers < 16) return [
            ['place' => 'Winner', 'percent' => 70.0, 'amount' => round($prizePool * .70, 2)],
            ['place' => 'Runner-up', 'percent' => 30.0, 'amount' => round($prizePool * .30, 2)],
        ];
        return [
            ['place' => 'Winner', 'percent' => 60.0, 'amount' => round($prizePool * .60, 2)],
            ['place' => 'Runner-up', 'percent' => 25.0, 'amount' => round($prizePool * .25, 2)],
            ['place' => 'Semifinalist A', 'percent' => 7.5, 'amount' => round($prizePool * .075, 2)],
            ['place' => 'Semifinalist B', 'percent' => 7.5, 'amount' => round($prizePool * .075, 2)],
        ];
    }

    public function projectedPrizePool(string $fundingModel, int $maxPlayers, float $entryFee, float $kickoffContribution, float $platformFeePercent): float
    {
        if ($fundingModel === 'free_casual') return 0.0;
        $participantPool = $fundingModel === 'participant_funded' ? $maxPlayers * max(0.0, $entryFee) : 0.0;
        $fee = $participantPool * max(0.0, min(100.0, $platformFeePercent)) / 100;
        return round(max(0.0, $participantPool - $fee) + max(0.0, $kickoffContribution), 2);
    }

    public function settledPrizePool(PDO $pdo, array $tournament): float
    {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE tournament_id=:id AND status IN ('paid','settled')");
        $stmt->execute([':id' => (int)$tournament['id']]);
        $entryFees = (float)$stmt->fetchColumn();
        $feePercent = max(0.0, min(100.0, (float)($tournament['platform_fee_percent'] ?? PLATFORM_FEE_PERCENT)));
        $pool = max(0.0, $entryFees - ($entryFees * $feePercent / 100));
        // A configured contribution is only payable if it has actually been funded in the ledger.
        $contrib = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM financial_ledger WHERE tournament_id=:id AND entry_type='kickoff_contribution' AND direction='credit'");
        $contrib->execute([':id'=>(int)$tournament['id']]);
        return round($pool + (float)$contrib->fetchColumn(), 2);
    }

    public function lockPrizeAllocations(PDO $pdo, array $tournament): array
    {
        if (($tournament['status'] ?? '') !== 'completed' || empty($tournament['winner_id'])) throw new HttpException('Tournament must be completed with a champion before prize allocation.', 409);
        $existing = $pdo->prepare('SELECT * FROM tournament_prizes WHERE tournament_id=:id ORDER BY placement ASC');
        $existing->execute([':id'=>(int)$tournament['id']]);
        $rows = $existing->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) return $rows;

        $pool = $this->settledPrizePool($pdo, $tournament);
        $distribution = $this->prizeDistribution((string)$tournament['format'], (int)$tournament['max_players'], $pool);
        if (!$distribution) throw new HttpException('There is no settled prize pool available.', 409);
        $placements = $this->placementUsers($pdo, $tournament, count($distribution));
        $insert = $pdo->prepare("INSERT INTO tournament_prizes (tournament_id,user_id,placement,placement_label,percentage,amount,currency,status) VALUES (:t,:u,:p,:label,:pct,:amount,:currency,'allocated')");
        $out=[];
        foreach ($distribution as $i=>$item) {
            $userId=$placements[$i]['user_id'] ?? null;
            if (!$userId) {
                if ($i===0) $userId=(int)$tournament['winner_id']; else continue;
            }
            $insert->execute([':t'=>(int)$tournament['id'],':u'=>(int)$userId,':p'=>$i+1,':label'=>$item['place'],':pct'=>$item['percent'],':amount'=>$item['amount'],':currency'=>$tournament['currency'] ?? DEFAULT_CURRENCY]);
            $out[]=['id'=>(int)$pdo->lastInsertId(),'tournament_id'=>(int)$tournament['id'],'user_id'=>(int)$userId,'placement'=>$i+1,'placement_label'=>$item['place'],'percentage'=>$item['percent'],'amount'=>$item['amount'],'currency'=>$tournament['currency'] ?? DEFAULT_CURRENCY,'status'=>'allocated'];
        }
        return $out;
    }

    private function placementUsers(PDO $pdo, array $tournament, int $count): array
    {
        $winner=(int)$tournament['winner_id'];
        $out=[['user_id'=>$winner]];
        if ($count <= 1) return $out;
        $final=$pdo->prepare("SELECT player1_id,player2_id,winner_id FROM matches WHERE tournament_id=:t AND stage='knockout' ORDER BY round_number DESC,bracket_position DESC,id DESC LIMIT 1");
        $final->execute([':t'=>$tournament['id']]);
        $f=$final->fetch(PDO::FETCH_ASSOC);
        if($f){
            $runner=(int)$f['player1_id']===(int)$f['winner_id']?(int)$f['player2_id']:(int)$f['player1_id'];
            if($runner>0 && $runner!==$winner)$out[]=['user_id'=>$runner];
        }
        if($count>=4){
            $semiRound=$pdo->prepare("SELECT player1_id,player2_id,winner_id FROM matches WHERE tournament_id=:t AND stage='knockout' AND round_number=(SELECT MAX(round_number)-1 FROM matches WHERE tournament_id=:t2 AND stage='knockout') AND winner_id IS NOT NULL ORDER BY bracket_position ASC,id ASC LIMIT 2");
            $semiRound->execute([':t'=>$tournament['id'],':t2'=>$tournament['id']]);
            foreach($semiRound->fetchAll(PDO::FETCH_ASSOC) as $m){
                $loser=(int)$m['player1_id']===(int)$m['winner_id']?(int)$m['player2_id']:(int)$m['player1_id'];
                if($loser>0 && !array_filter($out,fn($x)=>(int)$x['user_id']===$loser))$out[]=['user_id'=>$loser];
            }
        }
        return array_slice($out,0,$count);
    }

    public function formatMoney(float|string|null $amount, string $currency = 'TZS'): string { return $currency . ' ' . number_format((float)$amount, 0); }
}
