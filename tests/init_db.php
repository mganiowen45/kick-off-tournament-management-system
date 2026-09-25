<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

echo "Creating database kick_off_test...\n";
$pdo->exec("DROP DATABASE IF EXISTS kick_off_test");
$pdo->exec("CREATE DATABASE kick_off_test");

$pdo->exec("USE kick_off");
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$pdo->exec("USE kick_off_test");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

foreach ($tables as $table) {
    $pdoSource = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=kick_off', DB_USER, DB_PASS);
    $createTableStmt = $pdoSource->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    
    if (isset($createTableStmt['Create Table'])) {
        $sql = $createTableStmt['Create Table'];
        $pdo->exec($sql);
        echo "Created table $table\n";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "kick_off_test initialized.\n";
