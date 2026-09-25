<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Core/Logger.php';

use App\Core\Logger;

$pdo = db();
$storageDir = __DIR__ . '/../storage/backups';

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0700, true);
    file_put_contents($storageDir . '/.htaccess', "Deny from all\n");
}

$filename = 'kickoff_backup_' . date('Ymd_His') . '.sql';
$filepath = $storageDir . '/' . $filename;

$pdo->prepare("INSERT INTO backup_records (filename, status, started_at) VALUES (:f, 'RUNNING', NOW())")
    ->execute([':f' => $filename]);
$recordId = (int) $pdo->lastInsertId();

try {
    $dbHost = DB_HOST;
    $dbPort = DB_PORT;
    $dbName = DB_NAME;
    $dbUser = escapeshellarg(DB_USER);
    
    // WARNING: In production, pass password via environment variable, not CLI args for security.
    // For this implementation, we use environment variable MYSQL_PWD.
    $cmd = sprintf(
        'c:\xampp\mysql\bin\mysqldump --host=%s --port=%d --user=%s %s > %s',
        escapeshellarg($dbHost),
        $dbPort,
        $dbUser,
        escapeshellarg($dbName),
        escapeshellarg($filepath)
    );

    $env = [
        'MYSQL_PWD' => DB_PASS
    ];

    $process = proc_open($cmd, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ], $pipes, null, $env);
    
    if (!is_resource($process)) {
        throw new \Exception('Failed to execute mysqldump command.');
    }
    
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $returnVar = proc_close($process);

    if ($returnVar !== 0) {
        throw new \Exception("mysqldump failed with code $returnVar. Error: $stderr");
    }

    if (!file_exists($filepath) || filesize($filepath) === 0) {
        throw new \Exception("Backup file is empty or missing.");
    }

    $size = filesize($filepath);

    $pdo->prepare("UPDATE backup_records SET status='SUCCESS', backup_size=:s, finished_at=NOW() WHERE id=:id")
        ->execute([':s' => $size, ':id' => $recordId]);
        
    Logger::info('Backup', "Database backup created successfully", ['filename' => $filename, 'size' => $size]);
    echo "Backup completed: $filename ($size bytes)\n";

    // Retention: Keep last 7 successful backups
    $stmt = $pdo->query("SELECT id, filename FROM backup_records WHERE status = 'SUCCESS' ORDER BY started_at DESC LIMIT 100 OFFSET 7");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $oldFile = $storageDir . '/' . $row['filename'];
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
        $pdo->prepare("DELETE FROM backup_records WHERE id = :id")->execute([':id' => $row['id']]);
        Logger::info('Backup', "Deleted old backup due to retention policy", ['filename' => $row['filename']]);
    }
} catch (\Throwable $e) {
    $pdo->prepare("UPDATE backup_records SET status='FAILED', finished_at=NOW(), error_summary=:err WHERE id=:id")
        ->execute([':err' => substr($e->getMessage(), 0, 1000), ':id' => $recordId]);
    Logger::error('Backup', 'Database backup failed', ['error' => $e->getMessage()]);
    echo "Backup failed: " . $e->getMessage() . "\n";
    exit(1);
}
