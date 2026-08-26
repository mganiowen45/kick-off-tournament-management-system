-- KICKOFF MVP patch: remember-me support and payment module disabled.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS remember_token varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS remember_expires timestamp NULL DEFAULT NULL;

-- Existing wallet tables may remain in old databases, but MVP code no longer links to or requires them.
