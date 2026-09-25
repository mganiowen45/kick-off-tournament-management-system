<?php
require 'config/config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname=kick_off', DB_USER, DB_PASS);
print_r(array_column($pdo->query('DESCRIBE job_runs')->fetchAll(), 'Field'));
print_r(array_column($pdo->query('DESCRIBE job_locks')->fetchAll(), 'Field'));
