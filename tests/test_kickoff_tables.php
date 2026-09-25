<?php
$pdo = new PDO('mysql:host=localhost;dbname=kick_off', 'root', '');
print_r(array_filter(array_column($pdo->query('SHOW TABLES')->fetchAll(), 0), fn($t) => str_contains($t, 'prize') || str_contains($t, 'payout')));
