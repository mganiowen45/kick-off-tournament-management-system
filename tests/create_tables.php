<?php
require 'config/config.php';
function createMissingTables($db) {
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.$db, DB_USER, DB_PASS);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS tournament_prizes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT NOT NULL,
        user_id INT NOT NULL,
        placement INT NOT NULL,
        placement_label VARCHAR(255),
        percentage DECIMAL(5,2),
        amount DECIMAL(15,2) NOT NULL,
        currency VARCHAR(3) DEFAULT 'TZS',
        status ENUM('pending', 'allocated', 'processing', 'paid', 'failed') DEFAULT 'pending',
        payout_id INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_payout_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        is_verified TINYINT(1) DEFAULT 0,
        is_default TINYINT(1) DEFAULT 0,
        verified_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

createMissingTables('kick_off');
createMissingTables('kick_off_test');
echo "Tables created successfully.";
