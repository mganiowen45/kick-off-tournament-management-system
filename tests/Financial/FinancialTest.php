<?php
declare(strict_types=1);

namespace Tests\Financial;

use Tests\TestCase;
use PDO;
use App\Services\PayoutService;

class FinancialTest extends TestCase
{
    public function testPayoutCreationDeductsFromPrizePool(): void
    {
        $adminId = $this->createUser('admin', 'admin', 'admin@test.com');
        $tournId = $this->createTournament($adminId, 'completed', 100);
        $playerId = $this->createUser('winner', 'player', 'winner@test.com');
        
        // Add funds to tournament
        // Add funds to tournament via ledger so settledPrizePool() returns > 0
        $this->pdo->exec("UPDATE tournaments SET prize_pool_amount = 500, winner_id = $playerId WHERE id = $tournId");
        $this->pdo->exec("INSERT INTO financial_ledger (reference, tournament_id, user_id, entry_type, direction, amount, currency) VALUES ('TEST-LEDGER-1', $tournId, $playerId, 'kickoff_contribution', 'credit', 500, 'TZS')");
        
        $this->pdo->exec("INSERT INTO user_payout_methods (user_id, phone_number, is_verified, is_default) VALUES ($playerId, '255700000000', 1, 1)");
        
        $service = new PayoutService($this->pdo);
        
        // Admin authorizes payout
        $admin = ['id' => $adminId, 'role' => 'admin', 'username' => 'admin'];
        try {
            $result = $service->authorizeChampionPayout($admin, $tournId);
        } catch (\Throwable $e) {
            echo "Exception: " . $e->getMessage() . "\n";
            echo "Previous: " . ($e->getPrevious() ? $e->getPrevious()->getMessage() : 'None') . "\n";
            throw $e;
        }
        
        $this->assertEquals('processing', $result['status']);
        $this->assertEquals(350.0, $result['amount']);
        $this->assertEquals('TZS', $result['currency']);
        
        // Payout should be created in the database
        $stmt = $this->pdo->query("SELECT * FROM payouts WHERE tournament_id = $tournId");
        $payout = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($payout);
        $this->assertEquals('processing', $payout['status']);
        $this->assertEquals(350.0, $payout['amount']);
        
        // It should be idempotent
        $result2 = $service->authorizeChampionPayout($admin, $tournId);
        $this->assertEquals('processing', $result2['status']);
        
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM payouts WHERE tournament_id = $tournId");
        $this->assertEquals(1, $stmt->fetchColumn());
    }
}
