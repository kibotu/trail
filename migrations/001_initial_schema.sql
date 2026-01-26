-- Trail Service - Initial Database Schema
-- Migration: 001_initial_schema.sql
-- Description: Create users, entries, and rate_limits tables with optimized indexes

-- Users table with Gravatar support
CREATE TABLE IF NOT EXISTS trail_users (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  google_id VARCHAR(255) UNIQUE NOT NULL,
  email VARCHAR(255) NOT NULL,
  name VARCHAR(255),
  gravatar_hash VARCHAR(64),  -- Pre-computed MD5 for performance
  is_admin BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entries table with optimized indexes
CREATE TABLE IF NOT EXISTS trail_entries (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  text VARCHAR(280) NOT NULL,  -- Single text field supporting URLs and emojis
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  INDEX idx_user_created (user_id, created_at DESC),  -- Composite for user queries
  INDEX idx_created (created_at DESC),  -- For RSS feed generation
  INDEX idx_user_id (user_id)  -- For JOIN operations
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting table
CREATE TABLE IF NOT EXISTS trail_rate_limits (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  identifier VARCHAR(255) NOT NULL,  -- IP address or user_id
  endpoint VARCHAR(255) NOT NULL,
  request_count INT UNSIGNED DEFAULT 1,
  window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_identifier_endpoint (identifier, endpoint),
  INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrations tracking table
CREATE TABLE IF NOT EXISTS trail_migrations (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  migration_name VARCHAR(255) UNIQUE NOT NULL,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('001_initial_schema.sql');
