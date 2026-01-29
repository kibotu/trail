-- Add claps table for Medium-style clap system
-- Migration: 015_add_claps_table.sql

CREATE TABLE IF NOT EXISTS trail_claps (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  entry_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  clap_count TINYINT UNSIGNED NOT NULL DEFAULT 1 CHECK (clap_count >= 1 AND clap_count <= 50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Foreign keys with CASCADE delete
  FOREIGN KEY (entry_id) REFERENCES trail_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  
  -- Unique constraint: one record per user per entry
  UNIQUE KEY idx_entry_user (entry_id, user_id),
  
  -- Indexes for performance
  INDEX idx_entry_id (entry_id),
  INDEX idx_user_id (user_id),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
