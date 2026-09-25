START TRANSACTION;

ALTER TABLE system_avatars
  MODIFY category VARCHAR(40) NOT NULL DEFAULT 'male_character';

UPDATE system_avatars SET category = CASE
  WHEN file_path LIKE 'female-%' THEN 'female_character'
  WHEN file_path LIKE 'football-%' OR file_path LIKE 'cartoon-mascot%' THEN 'club'
  WHEN file_path LIKE 'male-%' THEN 'male_character'
  ELSE 'male_character'
END;

ALTER TABLE system_avatars
  MODIFY category ENUM('male_character','female_character','country_flag','club') NOT NULL DEFAULT 'male_character';

INSERT INTO system_avatars (name, category, file_path, sort_order) VALUES
  ('Tanzania Flag', 'country_flag', 'flag-tanzania.svg', 210),
  ('Kenya Flag', 'country_flag', 'flag-kenya.svg', 220),
  ('Uganda Flag', 'country_flag', 'flag-uganda.svg', 230),
  ('Nigeria Flag', 'country_flag', 'flag-nigeria.svg', 240)
ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), is_active = 1, sort_order = VALUES(sort_order);

UPDATE tournament_cover_images SET is_active = 0
WHERE file_path LIKE 'assets/avatars/%';

INSERT INTO tournament_cover_images (name, category, file_path, sort_order) VALUES
  ('Neon Stadium', 'Football Arena', 'assets/tournament-covers/neon-stadium.svg', 10),
  ('Duel Arena', '1V1', 'assets/tournament-covers/duel-arena.svg', 20),
  ('Knockout Bracket', 'Knockout', 'assets/tournament-covers/knockout-bracket.svg', 30),
  ('World Stage', 'Group Stage', 'assets/tournament-covers/world-stage.svg', 40),
  ('Mobile Arena', 'Mobile', 'assets/tournament-covers/mobile-arena.jpg', 50),
  ('Console Night', 'Console', 'assets/tournament-covers/console-night.svg', 60)
ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), is_active = 1, sort_order = VALUES(sort_order);

ALTER TABLE tournaments
  ADD COLUMN IF NOT EXISTS invite_revoked_at DATETIME NULL AFTER share_token_hash,
  ADD COLUMN IF NOT EXISTS invite_regenerated_at DATETIME NULL AFTER invite_revoked_at;

UPDATE tournaments
SET cover_image_id = COALESCE(
  (SELECT MIN(id) FROM tournament_cover_images WHERE file_path LIKE 'assets/tournament-covers/%' AND is_active = 1),
  cover_image_id
)
WHERE cover_image_id IS NULL
   OR cover_image_id IN (SELECT id FROM tournament_cover_images WHERE file_path LIKE 'assets/avatars/%');

ALTER TABLE matches
  ADD COLUMN IF NOT EXISTS play_deadline_at DATETIME NULL AFTER scheduled_at,
  ADD COLUMN IF NOT EXISTS grace_period_ends_at DATETIME NULL AFTER play_deadline_at,
  ADD COLUMN IF NOT EXISTS no_show_status ENUM('none','reported','double_no_show','awaiting_deadline_review','resolved') NOT NULL DEFAULT 'none' AFTER grace_period_ends_at;

UPDATE matches
SET play_deadline_at = COALESCE(play_deadline_at, DATE_ADD(scheduled_at, INTERVAL 24 HOUR)),
    grace_period_ends_at = COALESCE(grace_period_ends_at, DATE_ADD(scheduled_at, INTERVAL 26 HOUR))
WHERE scheduled_at IS NOT NULL;

CREATE TABLE IF NOT EXISTS match_no_show_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id INT NOT NULL,
  reporter_id INT NOT NULL,
  accused_id INT NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  status ENUM('pending','accepted','rejected','duplicate','expired') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(1000) NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_no_show_reporter_match (match_id, reporter_id),
  KEY idx_no_show_status (status, created_at),
  KEY idx_no_show_accused (accused_id, created_at),
  CONSTRAINT fk_no_show_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_no_show_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_no_show_accused FOREIGN KEY (accused_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_no_show_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS player_inactivity_strikes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  match_id INT NULL,
  tournament_id INT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_inactivity_user (user_id, created_at),
  KEY idx_inactivity_tournament (tournament_id, created_at),
  CONSTRAINT fk_inactivity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_inactivity_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL,
  CONSTRAINT fk_inactivity_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE notifications
  MODIFY type ENUM(
    'result_confirmed','result_disputed','dispute_resolved','match_reminder','new_message',
    'tournament_update','tournament_joined','round_advanced','account_warning','system',
    'whatsapp_contact','match_time_proposed','match_time_confirmed','match_time_rejected',
    'deadline_approaching','prize_credited','withdrawal_completed','refund_processed',
    'payment_successful','payment_failed','payment_pending','cancellation_requested',
    'cancellation_decision','group_assigned','knockout_qualified','payout_pending',
    'no_show_reported','no_show_decision'
  ) NOT NULL DEFAULT 'system';

COMMIT;
