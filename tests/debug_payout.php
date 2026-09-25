<?php
require 'config/config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname=kick_off_test', DB_USER, DB_PASS);
$service = new \App\Services\PayoutService($pdo);
// Admin authorizes payout
$admin = ['id' => 1, 'role' => 'admin', 'username' => 'admin'];
$result = $service->authorizeChampionPayout($admin, 1);
print_r($result);
