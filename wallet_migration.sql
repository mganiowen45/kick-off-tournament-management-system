-- ============================================================
--  KICKOFF — Wallet & Paid Tournament Migration
--  Run this on your existing kick_off database
--  Compatible with MariaDB 10.4+ / MySQL 8+
-- ============================================================

-- ── 1. ALTER tournaments: add paid-tournament columns ────────
ALTER TABLE `tournaments`
  ADD COLUMN `is_paid`              tinyint(1)       NOT NULL DEFAULT 0            AFTER `prize_pool`,
  ADD COLUMN `entry_fee`            decimal(10,2)    NOT NULL DEFAULT 0.00          AFTER `is_paid`,
  ADD COLUMN `platform_cut_pct`     decimal(5,2)     NOT NULL DEFAULT 10.00         AFTER `entry_fee`,
  ADD COLUMN `prize_pool_calculated` decimal(10,2)   NOT NULL DEFAULT 0.00          AFTER `platform_cut_pct`,
  ADD COLUMN `payout_done`          tinyint(1)       NOT NULL DEFAULT 0            AFTER `prize_pool_calculated`;

-- ── 2. CREATE wallets table ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `wallets` (
  `id`          int(11)        NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)        NOT NULL,
  `balance`     decimal(10,2)  NOT NULL DEFAULT 0.00,
  `created_at`  timestamp      NOT NULL DEFAULT current_timestamp(),
  `updated_at`  timestamp      NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wallet_user` (`user_id`),
  CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 3. CREATE wallet_transactions table ───────────────────────
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id`            int(11)        NOT NULL AUTO_INCREMENT,
  `user_id`       int(11)        NOT NULL,
  `type`          enum('deposit','withdrawal','entry_fee','prize_payout','refund') NOT NULL,
  `amount`        decimal(10,2)  NOT NULL,
  `balance_after` decimal(10,2)  NOT NULL DEFAULT 0.00,
  `reference_id`  int(11)        DEFAULT NULL COMMENT 'tournament_id or match_id depending on context',
  `description`   varchar(255)   NOT NULL DEFAULT '',
  `status`        enum('pending','completed','failed') NOT NULL DEFAULT 'completed',
  `created_at`    timestamp      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wt_user`       (`user_id`),
  KEY `idx_wt_type`       (`type`),
  KEY `idx_wt_reference`  (`reference_id`),
  KEY `idx_wt_created`    (`created_at`),
  CONSTRAINT `fk_wt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 4. Seed wallets for every existing user ───────────────────
INSERT IGNORE INTO `wallets` (`user_id`, `balance`)
SELECT `id`, 0.00 FROM `users`;

-- ── 5. Update notifications ENUM to include wallet types ──────
-- MariaDB requires re-declaring the full enum when adding values
ALTER TABLE `notifications`
  MODIFY `type` enum(
    'result_confirmed',
    'result_disputed',
    'dispute_resolved',
    'match_reminder',
    'new_message',
    'tournament_update',
    'tournament_joined',
    'round_advanced',
    'account_warning',
    'system',
    'wallet_deposit',
    'wallet_withdrawal',
    'entry_fee_deducted',
    'prize_credited',
    'insufficient_balance'
  ) NOT NULL DEFAULT 'system';

-- ── 6. View: vw_wallet_summary (per-user totals) ─────────────
CREATE OR REPLACE VIEW `vw_wallet_summary` AS
SELECT
  u.id                                                          AS user_id,
  u.username,
  COALESCE(w.balance, 0.00)                                     AS balance,
  COALESCE(SUM(CASE WHEN wt.type IN ('deposit')         THEN wt.amount ELSE 0 END), 0) AS total_deposited,
  COALESCE(SUM(CASE WHEN wt.type IN ('withdrawal')      THEN wt.amount ELSE 0 END), 0) AS total_withdrawn,
  COALESCE(SUM(CASE WHEN wt.type IN ('entry_fee')       THEN wt.amount ELSE 0 END), 0) AS total_entry_fees_paid,
  COALESCE(SUM(CASE WHEN wt.type IN ('prize_payout')    THEN wt.amount ELSE 0 END), 0) AS total_prizes_won
FROM users u
LEFT JOIN wallets w            ON w.user_id = u.id
LEFT JOIN wallet_transactions wt ON wt.user_id = u.id AND wt.status = 'completed'
WHERE u.role = 'player'
GROUP BY u.id, u.username, w.balance;

-- ── 7. View: vw_platform_earnings (admin) ────────────────────
CREATE OR REPLACE VIEW `vw_platform_earnings` AS
SELECT
  t.id                                                AS tournament_id,
  t.name                                              AS tournament_name,
  t.format,
  t.entry_fee,
  t.current_players,
  t.entry_fee * t.current_players                     AS gross_pool,
  t.platform_cut_pct,
  ROUND(t.entry_fee * t.current_players * t.platform_cut_pct / 100, 2) AS platform_cut_amount,
  t.prize_pool_calculated                             AS net_prize_pool,
  t.payout_done,
  t.status,
  t.completed_at
FROM tournaments t
WHERE t.is_paid = 1;

COMMIT;
