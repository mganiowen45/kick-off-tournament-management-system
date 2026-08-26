-- KICKOFF backend refactor migration
-- Run once after importing kickoff.sql. Existing IDs and data are preserved.

START TRANSACTION;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS championships INT NOT NULL DEFAULT 0 AFTER total_matches,
  ADD COLUMN IF NOT EXISTS remember_token VARCHAR(255) DEFAULT NULL AFTER bio,
  ADD COLUMN IF NOT EXISTS remember_expires TIMESTAMP NULL DEFAULT NULL AFTER remember_token;

UPDATE users SET avatar_url = 'gamer-neon.svg' WHERE avatar_url IS NULL OR avatar_url = '';
ALTER TABLE users
  MODIFY avatar_url VARCHAR(100) NOT NULL DEFAULT 'gamer-neon.svg';

ALTER TABLE tournaments
  ADD COLUMN IF NOT EXISTS winner_id INT NULL AFTER current_round,
  ADD COLUMN IF NOT EXISTS share_token CHAR(32) NULL AFTER visibility;

UPDATE tournaments SET share_token = LOWER(HEX(RANDOM_BYTES(16))) WHERE share_token IS NULL OR share_token = '';
ALTER TABLE tournaments MODIFY share_token CHAR(32) NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_tournaments_share_token ON tournaments (share_token);
CREATE INDEX IF NOT EXISTS idx_tournaments_winner ON tournaments (winner_id);

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS actor_id INT NULL AFTER user_id;
CREATE INDEX IF NOT EXISTS idx_notifications_actor ON notifications (actor_id);

CREATE TABLE IF NOT EXISTS announcements (
  id INT NOT NULL AUTO_INCREMENT,
  tournament_id INT NOT NULL,
  author_id INT NOT NULL,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_announcements_tournament (tournament_id, created_at),
  CONSTRAINT fk_announcements_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_announcements_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS achievements (
  id INT NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NOT NULL,
  icon VARCHAR(20) NOT NULL DEFAULT 'star',
  PRIMARY KEY (id),
  UNIQUE KEY uq_achievements_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS user_achievements (
  user_id INT NOT NULL,
  achievement_id INT NOT NULL,
  earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, achievement_id),
  KEY idx_user_achievements_achievement (achievement_id),
  CONSTRAINT fk_user_achievements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_achievements_achievement FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO achievements (code, name, description, icon) VALUES
  ('first_match', 'Kickoff Debut', 'Complete your first confirmed match.', 'whistle'),
  ('first_win', 'First Victory', 'Win your first confirmed match.', 'trophy'),
  ('ten_wins', 'Double Digits', 'Win ten confirmed matches.', 'medal'),
  ('fifty_matches', 'Arena Veteran', 'Complete fifty confirmed matches.', 'shield'),
  ('champion', 'Tournament Champion', 'Win a KICKOFF tournament.', 'crown')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), icon = VALUES(icon);

CREATE TABLE IF NOT EXISTS login_attempts (
  attempt_key CHAR(64) NOT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  first_attempt_at DATETIME NOT NULL,
  last_attempt_at DATETIME NOT NULL,
  PRIMARY KEY (attempt_key),
  KEY idx_login_attempts_last (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS group_message_reads (
  user_id INT NOT NULL,
  tournament_id INT NOT NULL,
  last_message_id INT NOT NULL DEFAULT 0,
  read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, tournament_id),
  CONSTRAINT fk_group_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_reads_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- These constraints are intentionally in a one-time migration.
ALTER TABLE tournaments
  ADD CONSTRAINT fk_tournaments_winner FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE notifications
  ADD CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;

COMMIT;

