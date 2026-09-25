<?php
require 'config/config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname=kick_off', DB_USER, DB_PASS);
$stmt = $pdo->query("SHOW COLUMNS FROM tournaments WHERE Field = 'format'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
