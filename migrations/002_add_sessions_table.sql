-- Trail Service - Add Sessions Table
-- Migration: 002_add_sessions_table.sql
-- Description: Create sessions table for web-based authentication

-- Sessions table for web login
CREATE TABLE IF NOT EXISTS trail_sessions (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  session_id VARCHAR(64) UNIQUE NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  photo_url TEXT,
  is_admin BOOLEAN DEFAULT FALSE,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  INDEX idx_session_id (session_id),
  INDEX idx_expires_at (expires_at),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('002_add_sessions_table.sql');
