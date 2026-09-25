<?php
require 'config/config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname=kick_off', DB_USER, DB_PASS);
print_r(array_column($pdo->query('DESCRIBE financial_ledger')->fetchAll(), 'Field'));
