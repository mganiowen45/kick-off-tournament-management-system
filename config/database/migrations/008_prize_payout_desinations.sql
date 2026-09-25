-- KICKOFF prize allocation, payout destination and provider reconciliation hardening.
-- Run after 007_financial_payout_hardening.sql.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS tournament_prizes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id INT NOT NULL,
  user_id INT NOT NULL,
  placement INT NOT NULL,
  placement_label VARCHAR(80) NOT NULL,
  percentage DECIMAL(6,3) NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TZS',
  status ENUM('allocated','ready','processing','paid','failed','reversed','cancelled') NOT NULL DEFAULT 'allocated',
  payout_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tournament_prize_placement (tournament_id, placement),
  UNIQUE KEY uq_tournament_prize_user (tournament_id, user_id),
  KEY idx_prizes_payout (payout_id),
  CONSTRAINT fk_prize_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_prize_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_prize_payout FOREIGN KEY (payout_id) REFERENCES payouts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS user_payout_methods (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'mobile_money',
  phone_number VARCHAR(30) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TZS',
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_payout_phone (user_id, phone_number),
  KEY idx_payout_method_user (user_id, is_default),
  CONSTRAINT fk_payout_method_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE payouts
  ADD COLUMN IF NOT EXISTS payout_method_id BIGINT UNSIGNED NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS recipient_phone VARCHAR(30) NULL AFTER payout_method_id,
  ADD COLUMN IF NOT EXISTS provider_status VARCHAR(50) NULL AFTER provider_reference,
  ADD COLUMN IF NOT EXISTS provider_fee DECIMAL(12,2) NULL AFTER amount,
  ADD COLUMN IF NOT EXISTS previewed_at DATETIME NULL AFTER approved_at,
  ADD COLUMN IF NOT EXISTS failed_at DATETIME NULL AFTER paid_at,
  ADD COLUMN IF NOT EXISTS reversed_at DATETIME NULL AFTER failed_at,
  ADD COLUMN IF NOT EXISTS last_provider_check_at DATETIME NULL AFTER reversed_at,
  ADD COLUMN IF NOT EXISTS last_error VARCHAR(500) NULL AFTER last_provider_check_at;

CREATE INDEX IF NOT EXISTS idx_payout_reconcile ON payouts (status, last_provider_check_at);

ALTER TABLE financial_ledger
  ADD COLUMN IF NOT EXISTS payout_id BIGINT UNSIGNED NULL AFTER payment_id;

COMMIT;
