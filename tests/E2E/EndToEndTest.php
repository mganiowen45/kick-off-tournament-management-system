<?php
declare(strict_types=1);

namespace Tests\E2E;

use Tests\TestCase;
use PDO;
use App\Services\TournamentEngine;
use App\Services\ResultService;
use App\Services\PayoutService;

class EndToEndTest extends TestCase
{
    public function testCompleteTournamentLifecycle(): void
    {
        $adminId = $this->createUser('admin', 'admin', 'admin@test.com');
        $tournId = $this->createTournament($adminId, 'open', 0); // Free tournament
        
        // 1. Players Join
        $players = [];
        for ($i = 1; $i <= 8; $i++) {
            $pid = $this->createUser("player$i", 'player', "player$i@test.com");
            $players[] = $pid;
            $this->pdo->exec("INSERT INTO tournament_players (tournament_id, user_id, status, checked_in_at) VALUES ($tournId, $pid, 'active', NOW())");
        }
        
        // 2. Start Tournament
        $engine = new TournamentEngine($this->pdo);
        $engine->start($tournId);
        
        $stmt = $this->pdo->query("SELECT status, current_round FROM tournaments WHERE id = $tournId");
        $t = $stmt->fetch();
        $this->assertEquals('active', $t['status']);
        $this->assertEquals(1, $t['current_round']);
        
        // 3. Play matches to completion
        $resultService = new ResultService($this->pdo);
        $rounds = [1 => 4, 2 => 2, 3 => 1];
        
        foreach ($rounds as $round => $expectedMatches) {
            $stmt = $this->pdo->query("SELECT * FROM matches WHERE tournament_id = $tournId AND round_number = $round");
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->assertCount($expectedMatches, $matches);
            
            foreach ($matches as $match) {
                // Force status to completed for the engine, or use submitResult if implemented fully for automated tests
                // To avoid complex result negotiation (which needs two players submitting same), we force it
                $winner = $match['player1_id'];
                $this->pdo->exec("UPDATE matches SET status = 'confirmed', winner_id = $winner WHERE id = {$match['id']}");
                $engine->progressAfterConfirmedMatch((int)$match['id']);
            }
        }
        
        // 4. Verify Tournament Completed
        $stmt = $this->pdo->query("SELECT status, winner_id FROM tournaments WHERE id = $tournId");
        $t = $stmt->fetch();
        $this->assertEquals('completed', $t['status']);
        $this->assertNotNull($t['winner_id']);
        
        $winnerId = $t['winner_id'];
        
        // 5. Verify Winner Payout (if prize pool was added)
        // Since it's free, no payout expected, but let's just make sure it doesn't crash
        $payoutService = new PayoutService($this->pdo);
        // Add fake funds to verify the finance process
        $this->pdo->exec("UPDATE tournaments SET prize_pool_amount = 100 WHERE id = $tournId");
        $this->pdo->exec("INSERT INTO financial_ledger (reference, tournament_id, user_id, entry_type, direction, amount, currency) VALUES ('TEST-LEDGER-2', $tournId, $winnerId, 'kickoff_contribution', 'credit', 100, 'TZS')");
        $this->pdo->exec("INSERT INTO user_payout_methods (user_id, phone_number, is_verified, is_default) VALUES ($winnerId, '255700000000', 1, 1)");
        
        $admin = ['id' => $adminId, 'role' => 'admin', 'username' => 'admin'];
        try {
            $result = $payoutService->authorizeChampionPayout($admin, $tournId);
        } catch (\Throwable $e) {
            // catch HttpException
            $this->assertStringContainsString('reconciliation', $e->getMessage());
        }
        
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM payouts WHERE tournament_id = $tournId AND user_id = $winnerId");
        $this->assertEquals(1, $stmt->fetchColumn());
    }
}
