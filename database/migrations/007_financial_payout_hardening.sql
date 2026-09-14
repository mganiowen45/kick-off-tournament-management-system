-- KICKOFF financial payout and idempotency hardening.
-- Run after 006_cover_paths_and_auto_start.sql.

START TRANSACTION;

ALTER TABLE financial_ledger
  ADD COLUMN IF NOT EXISTS payout_id BIGINT UNSIGNED NULL AFTER payment_id;

CREATE UNIQUE INDEX IF NOT EXISTS uq_ledger_reference ON financial_ledger (reference);

ALTER TABLE payouts
  MODIFY status ENUM('pending','approved','processing','submitted','paid','failed','reversed','cancelled') NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS payout_reference VARCHAR(120) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS payout_url VARCHAR(500) NULL AFTER provider_reference,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN IF NOT EXISTS submitted_at DATETIME NULL AFTER approved_at;

CREATE UNIQUE INDEX IF NOT EXISTS uq_payout_reference ON payouts (payout_reference);

COMMIT;
