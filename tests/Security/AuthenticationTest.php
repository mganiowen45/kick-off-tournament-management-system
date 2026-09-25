<?php
declare(strict_types=1);

namespace Tests\Security;

use Tests\TestCase;
use App\Core\Auth;
use App\Controllers\PasswordResetController;

class AuthenticationTest extends TestCase
{
    public function testPasswordResetGeneratesTokenAndHashesIt(): void
    {
        $userId = $this->createUser('testuser', 'player', 'test@example.com');
        
        $controller = new PasswordResetController($this->pdo);
        $controller->requestReset(['email' => 'test@example.com']);
        
        $stmt = $this->pdo->query("SELECT * FROM password_reset_tokens WHERE user_id = $userId");
        $tokenRow = $stmt->fetch();
        
        $this->assertNotEmpty($tokenRow);
        $this->assertNotNull($tokenRow['token_hash']);
        // Verify it's not storing plain text (length of SHA256 hex is 64)
        $this->assertEquals(64, strlen($tokenRow['token_hash']));
    }

    public function testPasswordResetWithNonExistentEmailFailsSilently(): void
    {
        $controller = new PasswordResetController($this->pdo);
        // This should not throw an exception (prevent account enumeration)
        $controller->requestReset(['email' => 'nobody@example.com']);
        
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM password_reset_tokens");
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}
