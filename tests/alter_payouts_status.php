<?php
require 'config/config.php';
function alterPayoutsStatus($db) {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.$db, DB_USER, DB_PASS);
    $pdo->exec("ALTER TABLE payouts MODIFY COLUMN status ENUM('pending','approved','processing','submitted','paid','failed','reversed','cancelled') DEFAULT 'pending'");
}
alterPayoutsStatus('kick_off');
alterPayoutsStatus('kick_off_test');
echo "Altered payouts status";
