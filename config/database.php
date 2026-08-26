<?php
// ============================================================
//  KICKOFF — Database Connection (PDO Singleton)
// ============================================================
require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                // SECURITY FIX: Never expose DB error details to client
                $debug = defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Check server logs.';
                error_log('[KICKOFF DB ERROR] ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error'   => 'Database connection failed.',
                    'debug'   => $debug
                ]);
                exit();
            }
        }
        return self::$instance;
    }

    private function __clone() {}
    public function __wakeup() {}
}

function db(): PDO {
    return Database::getInstance();
}
