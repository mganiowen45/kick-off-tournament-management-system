<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();
requireGet();

$pdo = db();

$runsStmt = $pdo->query("
    SELECT * FROM job_runs 
    ORDER BY started_at DESC 
    LIMIT 100
");
$runs = $runsStmt->fetchAll();

$alertsStmt = $pdo->query("
    SELECT * FROM operational_alerts 
    WHERE status = 'open' 
    ORDER BY created_at DESC
");
$alerts = $alertsStmt->fetchAll();

$backupsStmt = $pdo->query("
    SELECT * FROM backup_records 
    ORDER BY started_at DESC 
    LIMIT 20
");
$backups = $backupsStmt->fetchAll();

jsonSuccess([
    'runs' => $runs,
    'alerts' => $alerts,
    'backups' => $backups
]);
