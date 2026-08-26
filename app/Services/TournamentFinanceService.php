<?php
declare(strict_types=1);

namespace App\Services;

final class TournamentFinanceService
{
    public function prizeDistribution(string $format, int $maxPlayers, float $prizePool): array
    {
        if ($prizePool <= 0) {
            return [];
        }

        if ($format === '1v1' || $maxPlayers < 8) {
            return [['place' => 'Winner', 'percent' => 100.0, 'amount' => $prizePool]];
        }

        if ($maxPlayers < 16) {
            return [
                ['place' => 'Winner', 'percent' => 70.0, 'amount' => round($prizePool * 0.70, 2)],
                ['place' => 'Runner-up', 'percent' => 30.0, 'amount' => round($prizePool * 0.30, 2)],
            ];
        }

        return [
            ['place' => 'Winner', 'percent' => 60.0, 'amount' => round($prizePool * 0.60, 2)],
            ['place' => 'Runner-up', 'percent' => 25.0, 'amount' => round($prizePool * 0.25, 2)],
            ['place' => 'Semifinalist A', 'percent' => 7.5, 'amount' => round($prizePool * 0.075, 2)],
            ['place' => 'Semifinalist B', 'percent' => 7.5, 'amount' => round($prizePool * 0.075, 2)],
        ];
    }

    public function projectedPrizePool(
        string $fundingModel,
        int $maxPlayers,
        float $entryFee,
        float $kickoffContribution,
        float $platformFeePercent
    ): float {
        if ($fundingModel === 'free_casual') {
            return 0.0;
        }

        $participantPool = $fundingModel === 'participant_funded'
            ? $maxPlayers * max(0.0, $entryFee)
            : 0.0;
        $fee = $participantPool * max(0.0, min(100.0, $platformFeePercent)) / 100;
        return round(max(0.0, $participantPool - $fee) + max(0.0, $kickoffContribution), 2);
    }

    public function formatMoney(float|string|null $amount, string $currency = 'TZS'): string
    {
        return $currency . ' ' . number_format((float) $amount, 0);
    }
}
