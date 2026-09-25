<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Core/Logger.php';

use App\Core\Logger;

if ($argc < 2) {
    die("Usage: php cli/restore.php <backup_filename>\nExample: php cli/restore.php kickoff_backup_20260919_120000.sql\n");
}

$filename = basename($argv[1]);
$storageDir = __DIR__ . '/../storage/backups';
$filepath = $storageDir . '/' . $filename;

if (!file_exists($filepath)) {
    die("Backup file not found: $filepath\n");
}

echo "WARNING: This will overwrite the entire database '" . DB_NAME . "' with the contents of $filename.\n";
echo "Are you sure you want to proceed? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim(strtolower($line)) !== 'yes') {
    die("Aborting restore.\n");
}
fclose($handle);

echo "Restoring database...\n";

try {
    $dbHost = DB_HOST;
    $dbPort = DB_PORT;
    $dbName = DB_NAME;
    $dbUser = escapeshellarg(DB_USER);
    
    $cmd = sprintf(
        'c:\xampp\mysql\bin\mysql --host=%s --port=%d --user=%s %s < %s',
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
        throw new \Exception('Failed to execute mysql restore command.');
    }
    
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $returnVar = proc_close($process);

    if ($returnVar !== 0) {
        throw new \Exception("Restore failed with code $returnVar. Error: $stderr");
    }

    Logger::warning('Restore', "Database restored successfully from backup", ['filename' => $filename]);
    echo "Restore completed successfully.\n";

} catch (\Throwable $e) {
    Logger::critical('Restore', 'Database restore failed', ['filename' => $filename, 'error' => $e->getMessage()]);
    echo "Restore failed: " . $e->getMessage() . "\n";
    exit(1);
}
