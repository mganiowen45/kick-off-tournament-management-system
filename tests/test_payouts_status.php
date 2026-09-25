<?php
require 'config/config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname=kick_off', DB_USER, DB_PASS);
print_r($pdo->query("SHOW COLUMNS FROM payouts WHERE Field='status'")->fetch(PDO::FETCH_ASSOC));
