-- Add notification system
-- Migration: 018_add_notifications_system.sql
-- Description: Add tables for notifications, preferences, and email queue
-- Note: DDL statements in MySQL auto-commit, so no explicit transaction needed

-- Create notifications table
CREATE TABLE IF NOT EXISTS trail_notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('mention_entry', 'mention_comment', 'comment_on_entry', 'clap_entry', 'clap_comment') NOT NULL,
  actor_user_id INT UNSIGNED NOT NULL,
  entry_id INT UNSIGNED NULL,
  comment_id INT UNSIGNED NULL,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_is_read (is_read),
  INDEX idx_created_at (created_at),
  INDEX idx_user_read (user_id, is_read),
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  FOREIGN KEY (entry_id) REFERENCES trail_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (comment_id) REFERENCES trail_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notification preferences table
CREATE TABLE IF NOT EXISTS trail_notification_preferences (
  user_id INT UNSIGNED PRIMARY KEY,
  email_on_mention BOOLEAN DEFAULT TRUE,
  email_on_comment BOOLEAN DEFAULT FALSE,
  email_on_clap BOOLEAN DEFAULT FALSE,
  email_digest_frequency ENUM('instant', 'daily', 'weekly', 'never') DEFAULT 'instant',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create email queue table (for future batching)
CREATE TABLE IF NOT EXISTS trail_notification_email_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  notification_ids TEXT NOT NULL,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_sent (user_id, sent_at),
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
