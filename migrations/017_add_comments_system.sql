-- Add comments system with claps and reporting
-- Migration: 017_add_comments_system.sql
-- Description: Add tables for comments, comment claps, and comment reporting
-- Note: DDL statements in MySQL auto-commit, so no explicit transaction needed

-- Comments table
CREATE TABLE IF NOT EXISTS trail_comments (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  entry_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  text VARCHAR(280) NOT NULL DEFAULT '',
  image_ids TEXT NULL COMMENT 'JSON array of image IDs',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (entry_id) REFERENCES trail_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  INDEX idx_entry_id (entry_id),
  INDEX idx_user_id (user_id),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comment claps table (Medium-style clap system)
CREATE TABLE IF NOT EXISTS trail_comment_claps (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  comment_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  clap_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (comment_id) REFERENCES trail_comments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  UNIQUE KEY idx_comment_user (comment_id, user_id),
  INDEX idx_comment_id (comment_id),
  INDEX idx_user_id (user_id),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comment reports table
CREATE TABLE IF NOT EXISTS trail_comment_reports (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  comment_id INT UNSIGNED NOT NULL,
  reporter_user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (comment_id) REFERENCES trail_comments(id) ON DELETE CASCADE,
  FOREIGN KEY (reporter_user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  UNIQUE KEY idx_comment_reporter (comment_id, reporter_user_id),
  INDEX idx_comment_id (comment_id),
  INDEX idx_reporter (reporter_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hidden comments table (per-user)
CREATE TABLE IF NOT EXISTS trail_hidden_comments (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  comment_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (comment_id) REFERENCES trail_comments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  UNIQUE KEY idx_comment_user (comment_id, user_id),
  INDEX idx_comment_id (comment_id),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
