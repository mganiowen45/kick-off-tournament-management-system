<?php
require 'config/config.php';
function alterPayoutMethods($db) {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.$db, DB_USER, DB_PASS);
    
    $cols = array_column($pdo->query('DESCRIBE user_payout_methods')->fetchAll(), 'Field');
    if (!in_array('provider', $cols)) {
        $pdo->exec("ALTER TABLE user_payout_methods ADD COLUMN provider VARCHAR(50) DEFAULT 'ClickPesa'");
    }
}
alterPayoutMethods('kick_off');
alterPayoutMethods('kick_off_test');
echo "Altered user_payout_methods";
