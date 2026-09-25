<?php
require 'config/config.php';
function alterPayouts($db) {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.$db, DB_USER, DB_PASS);
    
    $cols = array_column($pdo->query('DESCRIBE payouts')->fetchAll(), 'Field');
    
    $alter = [];
    if (!in_array('payout_method_id', $cols)) $alter[] = "ADD COLUMN payout_method_id INT NULL";
    if (!in_array('recipient_phone', $cols)) $alter[] = "ADD COLUMN recipient_phone VARCHAR(20) NULL";
    if (!in_array('provider_fee', $cols)) $alter[] = "ADD COLUMN provider_fee DECIMAL(10,2) NULL";
    if (!in_array('previewed_at', $cols)) $alter[] = "ADD COLUMN previewed_at DATETIME NULL";
    if (!in_array('last_error', $cols)) $alter[] = "ADD COLUMN last_error TEXT NULL";
    if (!in_array('payout_reference', $cols)) $alter[] = "ADD COLUMN payout_reference VARCHAR(100) NULL";
    if (!in_array('provider_status', $cols)) $alter[] = "ADD COLUMN provider_status VARCHAR(50) NULL";
    if (!in_array('submitted_at', $cols)) $alter[] = "ADD COLUMN submitted_at DATETIME NULL";
    if (!in_array('last_provider_check_at', $cols)) $alter[] = "ADD COLUMN last_provider_check_at DATETIME NULL";
    if (!in_array('failed_at', $cols)) $alter[] = "ADD COLUMN failed_at DATETIME NULL";
    if (!in_array('reversed_at', $cols)) $alter[] = "ADD COLUMN reversed_at DATETIME NULL";
    if (!in_array('updated_at', $cols)) $alter[] = "ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

    if (!empty($alter)) {
        $sql = "ALTER TABLE payouts " . implode(", ", $alter);
        $pdo->exec($sql);
    }
}

alterPayouts('kick_off');
alterPayouts('kick_off_test');
echo "Table payouts altered successfully.";
