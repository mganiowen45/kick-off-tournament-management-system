-- KICKOFF trust, WhatsApp contact, and match scheduling migration.
-- Run after 001_backend_refactor.sql. Existing user and match rows are preserved.

START TRANSACTION;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS whatsapp_country_code VARCHAR(8) NULL AFTER preferred_game,
  ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20) NULL AFTER whatsapp_country_code,
  ADD COLUMN IF NOT EXISTS whatsapp_verified_at DATETIME NULL AFTER whatsapp_number,
  ADD COLUMN IF NOT EXISTS whatsapp_contact_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp_verified_at,
  ADD COLUMN IF NOT EXISTS whatsapp_contact_updated_at DATETIME NULL AFTER whatsapp_contact_opt_in,
  ADD COLUMN IF NOT EXISTS timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Dar_es_Salaam' AFTER country;

CREATE INDEX IF NOT EXISTS idx_users_whatsapp_opt_in ON users (whatsapp_contact_opt_in);

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id INT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_actor (actor_id, created_at),
  KEY idx_audit_entity (entity_type, entity_id, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS user_blocks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  blocker_id INT NOT NULL,
  blocked_id INT NOT NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_blocks_pair (blocker_id, blocked_id),
  KEY idx_user_blocks_blocked (blocked_id),
  CONSTRAINT fk_user_blocks_blocker FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_user_blocks_blocked FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS contact_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id INT NOT NULL,
  reported_user_id INT NOT NULL,
  match_id INT NULL,
  reason VARCHAR(120) NOT NULL,
  description TEXT NULL,
  status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_contact_reports_status (status, created_at),
  KEY idx_contact_reports_match (match_id),
  CONSTRAINT fk_contact_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_contact_reports_reported FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_contact_reports_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS match_contact_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id INT NOT NULL,
  requester_id INT NOT NULL,
  opponent_id INT NOT NULL,
  channel ENUM('whatsapp') NOT NULL DEFAULT 'whatsapp',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_match_contact_match (match_id, created_at),
  KEY idx_match_contact_requester (requester_id, created_at),
  CONSTRAINT fk_match_contact_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_match_contact_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_match_contact_opponent FOREIGN KEY (opponent_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS match_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id INT NOT NULL,
  status ENUM('not_scheduled','proposed','confirmed','rejected','expired','completed') NOT NULL DEFAULT 'proposed',
  proposed_by INT NOT NULL,
  proposed_start_utc DATETIME NOT NULL,
  display_timezone VARCHAR(64) NOT NULL,
  note VARCHAR(500) NULL,
  proposed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_by INT NULL,
  confirmed_at DATETIME NULL,
  rejected_by INT NULL,
  rejected_at DATETIME NULL,
  rejection_reason VARCHAR(500) NULL,
  reschedule_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_match_schedules_match (match_id),
  KEY idx_match_schedules_status_time (status, proposed_start_utc),
  CONSTRAINT fk_match_schedules_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_match_schedules_proposed_by FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_match_schedules_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_match_schedules_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE notifications
  MODIFY type ENUM(
    'result_confirmed','result_disputed','dispute_resolved','match_reminder','new_message',
    'tournament_update','tournament_joined','round_advanced','account_warning','system',
    'whatsapp_contact','match_time_proposed','match_time_confirmed','match_time_rejected',
    'deadline_approaching','prize_credited','withdrawal_completed','refund_processed'
  ) NOT NULL DEFAULT 'system';

COMMIT;
