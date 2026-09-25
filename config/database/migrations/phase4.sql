-- KICKOFF Phase 4 Reliability Migrations

CREATE TABLE IF NOT EXISTS job_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_name VARCHAR(100) NOT NULL,
    run_id VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('PENDING', 'RUNNING', 'SUCCESS', 'FAILED', 'RETRYING') NOT NULL DEFAULT 'PENDING',
    attempt INT NOT NULL DEFAULT 1,
    started_at DATETIME,
    finished_at DATETIME,
    records_processed INT DEFAULT 0,
    records_succeeded INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    error_summary TEXT,
    last_heartbeat DATETIME
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS job_locks (
    job_name VARCHAR(100) PRIMARY KEY,
    locked_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    locked_by VARCHAR(64) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    CONSTRAINT fk_pw_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS backup_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    status ENUM('RUNNING', 'SUCCESS', 'FAILED') NOT NULL DEFAULT 'RUNNING',
    backup_size INT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    error_summary TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS operational_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL,
    resolved_at DATETIME NULL
) ENGINE=InnoDB;
