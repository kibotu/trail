-- Trail Service - Report and Mute System
-- Migration: 011_add_report_and_mute_system.sql
-- Description: Add tables for reporting entries and muting users
-- Note: DDL statements in MySQL auto-commit, so no explicit transaction needed

-- Entry reports table - Tracks which users have reported which entries
CREATE TABLE IF NOT EXISTS trail_entry_reports (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  entry_id INT UNSIGNED NOT NULL,
  reporter_user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (entry_id) REFERENCES trail_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (reporter_user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  UNIQUE KEY idx_entry_reporter (entry_id, reporter_user_id),
  INDEX idx_entry_id (entry_id),
  INDEX idx_reporter_user_id (reporter_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report email tracking table - Tracks when admin emails were sent for reported entries
CREATE TABLE IF NOT EXISTS trail_report_emails (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  entry_id INT UNSIGNED NOT NULL,
  email_sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  report_count INT UNSIGNED NOT NULL,
  FOREIGN KEY (entry_id) REFERENCES trail_entries(id) ON DELETE CASCADE,
  INDEX idx_entry_id (entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hidden entries table - Tracks which entries a user has hidden (via reporting)
CREATE TABLE IF NOT EXISTS trail_hidden_entries (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  entry_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (entry_id) REFERENCES trail_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  UNIQUE KEY idx_entry_user (entry_id, user_id),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Muted users table - Tracks which users have been muted by other users
CREATE TABLE IF NOT EXISTS trail_muted_users (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  muter_user_id INT UNSIGNED NOT NULL,
  muted_user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (muter_user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  FOREIGN KEY (muted_user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  UNIQUE KEY idx_muter_muted (muter_user_id, muted_user_id),
  INDEX idx_muter_user_id (muter_user_id),
  INDEX idx_muted_user_id (muted_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
