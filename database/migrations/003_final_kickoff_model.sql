-- KICKOFF final tournament/profile/payment model migration.
-- Run after 002_trust_scheduling_whatsapp.sql.
-- Existing prototype data is preserved and mapped into the final active model.

START TRANSACTION;

ALTER TABLE tournaments
  MODIFY format ENUM('1v1','knockout','league','full_knockout','group_knockout') NOT NULL;

UPDATE tournaments SET format = 'full_knockout' WHERE format = 'knockout';
UPDATE tournaments SET format = 'group_knockout' WHERE format = 'league';
UPDATE tournaments SET max_players = 2, match_legs = 'best_of_3' WHERE format = '1v1';

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS profile_setup_completed TINYINT(1) NOT NULL DEFAULT 0 AFTER preferred_game,
  ADD COLUMN IF NOT EXISTS theme_preference VARCHAR(24) NOT NULL DEFAULT 'esport' AFTER profile_setup_completed;

UPDATE users
SET profile_setup_completed = 1
WHERE role = 'admin'
   OR (preferred_game IS NOT NULL AND preferred_game <> '' AND avatar_url IS NOT NULL AND avatar_url <> '');

CREATE TABLE IF NOT EXISTS games (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_games_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS platforms (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(40) NOT NULL,
  slug VARCHAR(40) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_platforms_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO games (name, slug, sort_order) VALUES
  ('eFootball', 'efootball', 10),
  ('EA SPORTS FC', 'ea-sports-fc', 20),
  ('Dream League Soccer', 'dream-league-soccer', 30)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1, sort_order = VALUES(sort_order);

INSERT INTO platforms (name, slug, sort_order) VALUES
  ('Mobile', 'mobile', 10),
  ('PC', 'pc', 20),
  ('Console', 'console', 30)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1, sort_order = VALUES(sort_order);

CREATE TABLE IF NOT EXISTS game_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  game_id INT NOT NULL,
  platform_id INT NOT NULL,
  in_game_name VARCHAR(80) NOT NULL,
  team_name VARCHAR(80) NULL,
  external_game_id VARCHAR(120) NULL,
  status ENUM('active','pending_verification','rejected') NOT NULL DEFAULT 'active',
  is_primary TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_game_profile_identity (user_id, game_id, platform_id),
  KEY idx_game_profiles_game_platform (game_id, platform_id),
  CONSTRAINT fk_game_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_game_profiles_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE RESTRICT,
  CONSTRAINT fk_game_profiles_platform FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO game_profiles (user_id, game_id, platform_id, in_game_name, is_primary)
SELECT u.id, g.id, p.id, u.username, 1
FROM users u
JOIN games g ON g.slug = CASE
  WHEN LOWER(u.preferred_game) LIKE '%dream%' THEN 'dream-league-soccer'
  WHEN LOWER(u.preferred_game) LIKE '%fifa%' OR LOWER(u.preferred_game) LIKE '%fc%' THEN 'ea-sports-fc'
  ELSE 'efootball'
END
JOIN platforms p ON p.slug = 'mobile'
WHERE u.role = 'player';

CREATE TABLE IF NOT EXISTS system_avatars (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  category ENUM('Country','Club','Character') NOT NULL DEFAULT 'Character',
  file_path VARCHAR(160) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_system_avatars_file (file_path),
  KEY idx_system_avatars_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO system_avatars (name, category, file_path, sort_order) VALUES
  ('Neon Gamer', 'Character', 'gamer-neon.svg', 10),
  ('Cyber Gamer', 'Character', 'gamer-cyber.svg', 20),
  ('Star Striker', 'Club', 'football-striker.svg', 30),
  ('Goal Keeper', 'Club', 'football-keeper.svg', 40),
  ('Cartoon Hero', 'Character', 'cartoon-hero.svg', 50),
  ('Club Mascot', 'Club', 'cartoon-mascot.svg', 60),
  ('Viper', 'Character', 'esports-viper.svg', 70),
  ('Titan', 'Character', 'esports-titan.svg', 80),
  ('Blue Captain', 'Country', 'male-blue.svg', 90),
  ('Gold Captain', 'Country', 'male-gold.svg', 100),
  ('Violet Captain', 'Country', 'female-violet.svg', 110),
  ('Cyan Captain', 'Country', 'female-cyan.svg', 120),
  ('Orbit', 'Character', 'neutral-orbit.svg', 130),
  ('Flame', 'Character', 'neutral-flame.svg', 140)
ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), is_active = 1, sort_order = VALUES(sort_order);

CREATE TABLE IF NOT EXISTS tournament_cover_images (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'Arena',
  file_path VARCHAR(160) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tournament_cover_file (file_path),
  KEY idx_tournament_covers_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO tournament_cover_images (name, category, file_path, sort_order) VALUES
  ('Neon Stadium', 'Football', 'assets/avatars/football-striker.svg', 10),
  ('Cyber Arena', 'Esports', 'assets/avatars/esports-viper.svg', 20),
  ('Champion Shield', 'Knockout', 'assets/avatars/esports-titan.svg', 30),
  ('Club Crest', 'Club', 'assets/avatars/cartoon-mascot.svg', 40)
ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), is_active = 1, sort_order = VALUES(sort_order);

ALTER TABLE tournaments
  ADD COLUMN IF NOT EXISTS game_id INT NULL AFTER game,
  ADD COLUMN IF NOT EXISTS platform_id INT NULL AFTER game_id,
  ADD COLUMN IF NOT EXISTS cover_image_id INT NULL AFTER platform_id,
  ADD COLUMN IF NOT EXISTS share_token_hash CHAR(64) NULL AFTER share_token,
  ADD COLUMN IF NOT EXISTS funding_model ENUM('free_casual','participant_funded','kickoff_sponsored') NOT NULL DEFAULT 'free_casual' AFTER prize_pool,
  ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'TZS' AFTER funding_model,
  ADD COLUMN IF NOT EXISTS entry_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER currency,
  ADD COLUMN IF NOT EXISTS prize_pool_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER entry_fee_amount,
  ADD COLUMN IF NOT EXISTS kickoff_contribution_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER prize_pool_amount,
  ADD COLUMN IF NOT EXISTS prize_template VARCHAR(40) NOT NULL DEFAULT 'auto' AFTER kickoff_contribution_amount,
  ADD COLUMN IF NOT EXISTS platform_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER prize_template,
  ADD COLUMN IF NOT EXISTS locked_at DATETIME NULL AFTER registration_deadline,
  ADD COLUMN IF NOT EXISTS registration_opens_at DATETIME NULL AFTER locked_at,
  ADD COLUMN IF NOT EXISTS check_in_opens_at DATETIME NULL AFTER registration_opens_at,
  ADD COLUMN IF NOT EXISTS check_in_closes_at DATETIME NULL AFTER check_in_opens_at,
  ADD COLUMN IF NOT EXISTS auto_start_at DATETIME NULL AFTER check_in_closes_at,
  ADD COLUMN IF NOT EXISTS lifecycle_note VARCHAR(255) NULL AFTER auto_start_at;

UPDATE tournaments t
JOIN games g ON g.slug = CASE
  WHEN LOWER(t.game) LIKE '%dream%' THEN 'dream-league-soccer'
  WHEN LOWER(t.game) LIKE '%fifa%' OR LOWER(t.game) LIKE '%fc%' THEN 'ea-sports-fc'
  ELSE 'efootball'
END
JOIN platforms p ON p.slug = 'mobile'
SET t.game_id = COALESCE(t.game_id, g.id),
    t.platform_id = COALESCE(t.platform_id, p.id),
    t.cover_image_id = COALESCE(t.cover_image_id, (SELECT MIN(id) FROM tournament_cover_images WHERE is_active = 1)),
    t.share_token_hash = COALESCE(t.share_token_hash, SHA2(t.share_token, 256)),
    t.currency = 'TZS',
    t.prize_pool_amount = CASE
      WHEN t.prize_pool REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(t.prize_pool AS DECIMAL(12,2))
      ELSE 0.00
    END;

UPDATE tournaments SET share_token = LOWER(REPLACE(UUID(), '-', '')) WHERE share_token IS NULL OR share_token = '';
UPDATE tournaments SET share_token_hash = SHA2(share_token, 256)
WHERE share_token_hash IS NULL OR share_token_hash = '' OR share_token_hash = SHA2('', 256);

ALTER TABLE tournaments
  MODIFY format ENUM('1v1','full_knockout','group_knockout') NOT NULL;

CREATE INDEX IF NOT EXISTS idx_tournaments_game_platform ON tournaments (game_id, platform_id);
CREATE INDEX IF NOT EXISTS idx_tournaments_visibility_status ON tournaments (visibility, status);
CREATE INDEX IF NOT EXISTS idx_tournaments_funding ON tournaments (funding_model, entry_fee_amount);
CREATE INDEX IF NOT EXISTS idx_tournaments_cover ON tournaments (cover_image_id);
CREATE INDEX IF NOT EXISTS idx_tournaments_share_hash ON tournaments (share_token_hash);

ALTER TABLE tournaments
  ADD CONSTRAINT fk_tournaments_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_tournaments_platform FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_tournaments_cover FOREIGN KEY (cover_image_id) REFERENCES tournament_cover_images(id) ON DELETE SET NULL;

ALTER TABLE tournament_players
  ADD COLUMN IF NOT EXISTS payment_status ENUM('not_required','pending','paid','failed','refunded') NOT NULL DEFAULT 'not_required' AFTER status,
  ADD COLUMN IF NOT EXISTS reservation_expires_at DATETIME NULL AFTER payment_status,
  ADD COLUMN IF NOT EXISTS checked_in_at DATETIME NULL AFTER reservation_expires_at,
  ADD COLUMN IF NOT EXISTS group_id BIGINT UNSIGNED NULL AFTER checked_in_at;

ALTER TABLE matches
  ADD COLUMN IF NOT EXISTS stage VARCHAR(40) NOT NULL DEFAULT 'knockout' AFTER tournament_id,
  ADD COLUMN IF NOT EXISTS group_id BIGINT UNSIGNED NULL AFTER stage,
  ADD COLUMN IF NOT EXISTS match_number INT NOT NULL DEFAULT 0 AFTER round_number,
  ADD COLUMN IF NOT EXISTS bracket_position INT NOT NULL DEFAULT 0 AFTER match_number,
  ADD COLUMN IF NOT EXISTS next_match_id INT NULL AFTER bracket_position,
  ADD COLUMN IF NOT EXISTS next_match_slot TINYINT NULL AFTER next_match_id,
  ADD COLUMN IF NOT EXISTS series_id BIGINT UNSIGNED NULL AFTER next_match_slot,
  ADD COLUMN IF NOT EXISTS series_game_number TINYINT UNSIGNED NULL AFTER series_id;

CREATE INDEX IF NOT EXISTS idx_matches_bracket_order ON matches (tournament_id, stage, round_number, bracket_position);
CREATE INDEX IF NOT EXISTS idx_matches_next ON matches (next_match_id);
CREATE INDEX IF NOT EXISTS idx_matches_series ON matches (series_id, series_game_number);
CREATE INDEX IF NOT EXISTS idx_matches_group ON matches (group_id);

CREATE TABLE IF NOT EXISTS tournament_series (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id INT NOT NULL,
  player1_id INT NOT NULL,
  player2_id INT NOT NULL,
  best_of TINYINT UNSIGNED NOT NULL DEFAULT 3,
  player1_wins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  player2_wins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  winner_id INT NULL,
  status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_series_tournament (tournament_id),
  CONSTRAINT fk_series_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_series_player1 FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_series_player2 FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_series_winner FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS series_games (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  series_id BIGINT UNSIGNED NOT NULL,
  match_id INT NOT NULL,
  game_number TINYINT UNSIGNED NOT NULL,
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_series_game_number (series_id, game_number),
  UNIQUE KEY uq_series_game_match (match_id),
  CONSTRAINT fk_series_games_series FOREIGN KEY (series_id) REFERENCES tournament_series(id) ON DELETE CASCADE,
  CONSTRAINT fk_series_games_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tournament_bracket_slots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id INT NOT NULL,
  stage VARCHAR(40) NOT NULL DEFAULT 'knockout',
  round_number INT NOT NULL,
  bracket_position INT NOT NULL,
  slot_number TINYINT UNSIGNED NOT NULL,
  user_id INT NULL,
  source_match_id INT NULL,
  is_bye TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bracket_slot (tournament_id, stage, round_number, bracket_position, slot_number),
  KEY idx_bracket_user (user_id),
  CONSTRAINT fk_bracket_slot_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_bracket_slot_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_bracket_slot_source FOREIGN KEY (source_match_id) REFERENCES matches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tournament_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id INT NOT NULL,
  name VARCHAR(32) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('scheduled','active','completed') NOT NULL DEFAULT 'scheduled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tournament_group_name (tournament_id, name),
  CONSTRAINT fk_tournament_groups_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tournament_group_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id BIGINT UNSIGNED NOT NULL,
  tournament_id INT NOT NULL,
  user_id INT NOT NULL,
  rank_position INT NULL,
  qualified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_group_member (group_id, user_id),
  KEY idx_group_members_tournament (tournament_id, user_id),
  CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES tournament_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_members_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tournament_cancellation_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id INT NOT NULL,
  requested_by INT NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(1000) NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cancel_requests_status (status, created_at),
  CONSTRAINT fk_cancel_requests_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_cancel_requests_requester FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cancel_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  tournament_id INT NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'clickpesa',
  order_reference VARCHAR(80) NOT NULL,
  provider_reference VARCHAR(120) NULL,
  checkout_url VARCHAR(500) NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TZS',
  status ENUM('pending','requires_action','paid','failed','expired','refunded','cancelled') NOT NULL DEFAULT 'pending',
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  confirmed_at DATETIME NULL,
  expires_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_order_reference (order_reference),
  KEY idx_payments_user (user_id, created_at),
  KEY idx_payments_tournament (tournament_id, status),
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS payment_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider VARCHAR(40) NOT NULL DEFAULT 'clickpesa',
  event_id VARCHAR(160) NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  order_reference VARCHAR(80) NULL,
  payload JSON NOT NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_webhook_event (provider, event_id),
  KEY idx_webhook_order (order_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS financial_ledger (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NULL,
  tournament_id INT NULL,
  payment_id BIGINT UNSIGNED NULL,
  payout_id BIGINT UNSIGNED NULL,
  entry_type ENUM('entry_fee','platform_fee','prize_pool','kickoff_contribution','refund','payout','adjustment') NOT NULL,
  direction ENUM('debit','credit') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TZS',
  reference VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ledger_reference (reference),
  KEY idx_ledger_user (user_id, created_at),
  KEY idx_ledger_tournament (tournament_id, created_at),
  CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ledger_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL,
  CONSTRAINT fk_ledger_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS refunds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TZS',
  reason VARCHAR(255) NULL,
  status ENUM('pending','submitted','processed','failed') NOT NULL DEFAULT 'pending',
  provider_reference VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_refunds_status (status, created_at),
  CONSTRAINT fk_refunds_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS payouts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TZS',
  status ENUM('pending','approved','processing','submitted','paid','failed','reversed','cancelled') NOT NULL DEFAULT 'pending',
  payout_reference VARCHAR(120) NULL,
  provider_reference VARCHAR(120) NULL,
  payout_url VARCHAR(500) NULL,
  admin_note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  submitted_at DATETIME NULL,
  paid_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payout_user_tournament (tournament_id, user_id),
  UNIQUE KEY uq_payout_reference (payout_reference),
  CONSTRAINT fk_payouts_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_payouts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value VARCHAR(500) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO system_settings (setting_key, setting_value) VALUES
  ('default_currency', 'TZS'),
  ('default_theme', 'esport'),
  ('platform_fee_percent', '10'),
  ('reservation_expiry_minutes', '30'),
  ('auto_start_hours_after_fill', '12'),
  ('check_in_window_minutes', '60'),
  ('check_in_close_minutes_before_auto_start', '30'),
  ('inactivity_threshold_minutes', '20'),
  ('large_tournament_threshold', '32'),
  ('medium_tournament_threshold', '16')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

ALTER TABLE notifications
  MODIFY type ENUM(
    'result_confirmed','result_disputed','dispute_resolved','match_reminder','new_message',
    'tournament_update','tournament_joined','round_advanced','account_warning','system',
    'whatsapp_contact','match_time_proposed','match_time_confirmed','match_time_rejected',
    'deadline_approaching','prize_credited','withdrawal_completed','refund_processed',
    'payment_successful','payment_failed','payment_pending','cancellation_requested',
    'cancellation_decision','group_assigned','knockout_qualified','payout_pending'
  ) NOT NULL DEFAULT 'system';

COMMIT;
