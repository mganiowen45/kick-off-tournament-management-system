<?php
declare(strict_types=1);

namespace Tests\Tournament;

use Tests\TestCase;
use App\Services\TournamentEngine;
use PDO;

class TournamentEngineTest extends TestCase
{
    public function testKnockoutBracketGeneration(): void
    {
        $adminId = $this->createUser('admin', 'admin', 'admin@test.com');
        $tournId = $this->createTournament($adminId, 'open', 0);
        
        // Add 8 players
        $players = [];
        for ($i=1; $i<=8; $i++) {
            $pid = $this->createUser("p$i", "player", "p$i@test.com");
            $this->pdo->exec("INSERT INTO tournament_players (tournament_id, user_id, status) VALUES ($tournId, $pid, 'registered')");
            $players[] = $pid;
        }

        $engine = new TournamentEngine($this->pdo);
        $engine->start($tournId);

        // Verify 4 matches in round 1
        $stmt = $this->pdo->query("SELECT * FROM matches WHERE tournament_id = $tournId AND round_number = 1");
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(4, $matches);
        
        // Ensure no semi-finals yet (round 2)
        $stmt = $this->pdo->query("SELECT * FROM matches WHERE tournament_id = $tournId AND round_number = 2");
        $this->assertCount(0, $stmt->fetchAll());
        
        // Test valid advancement
        foreach ($matches as $index => $match) {
            // Force status to completed for the engine
            $this->pdo->exec("UPDATE matches SET status = 'confirmed', winner_id = {$match['player1_id']} WHERE id = {$match['id']}");
            $engine->progressAfterConfirmedMatch((int)$match['id']);
        }
        
        // Now there should be 2 matches in round 2
        $stmt = $this->pdo->query("SELECT * FROM matches WHERE tournament_id = $tournId AND round_number = 2");
        $semis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $semis);
    }
}
