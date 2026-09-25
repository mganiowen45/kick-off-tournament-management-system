<?php
declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = \Database::getInstance();
        $this->clearDatabase();
    }

    protected function clearDatabase(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $stmt = $this->pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $table = $row[0];
            $this->pdo->exec("TRUNCATE TABLE `$table`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function createUser(string $username = 'player', string $role = 'player', string $email = 'player@example.com'): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, status)
            VALUES (:u, :e, :p, :r, 'active')
        ");
        $stmt->execute([
            ':u' => $username,
            ':e' => $email,
            ':p' => password_hash('password123', PASSWORD_DEFAULT),
            ':r' => $role
        ]);
        return (int) $this->pdo->lastInsertId();
    }
    
    protected function createTournament(int $adminId, string $status = 'open', int $fee = 0): int
    {
        $this->pdo->exec("INSERT IGNORE INTO platforms (id, name) VALUES (1, 'PC')");
        $this->pdo->exec("INSERT IGNORE INTO games (id, name) VALUES (1, 'Test Game')");
        
        $stmt = $this->pdo->prepare("
            INSERT INTO tournaments (name, game_id, format, funding_model, entry_fee_amount, max_players, creator_id, status)
            VALUES ('Test Tournament', 1, 'full_knockout', :funding, :fee, 8, :admin, :status)
        ");
        $stmt->execute([
            ':funding' => $fee > 0 ? 'paid' : 'free',
            ':fee' => $fee,
            ':admin' => $adminId,
            ':status' => $status
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
