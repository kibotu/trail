-- Add views tracking system with counter cache
-- Migration: 027_add_views_table.sql

-- Raw view events table (polymorphic)
CREATE TABLE IF NOT EXISTS trail_views (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  target_type ENUM('entry', 'comment', 'profile') NOT NULL,
  target_id   INT UNSIGNED NOT NULL,
  viewer_id   INT UNSIGNED DEFAULT NULL,          -- NULL = anonymous viewer
  viewer_hash BINARY(32) NOT NULL,                -- SHA-256 of IP + User-Agent + fingerprint
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  -- Indexes for dedup checks and queries
  INDEX idx_target       (target_type, target_id),
  INDEX idx_viewer_user  (viewer_id),
  INDEX idx_created      (created_at DESC),
  INDEX idx_dedup_user   (target_type, target_id, viewer_id, created_at),
  INDEX idx_dedup_hash   (target_type, target_id, viewer_hash, created_at),

  FOREIGN KEY (viewer_id) REFERENCES trail_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Materialized counter cache for O(1) reads
CREATE TABLE IF NOT EXISTS trail_view_counts (
  target_type ENUM('entry', 'comment', 'profile') NOT NULL,
  target_id   INT UNSIGNED NOT NULL,
  view_count  INT UNSIGNED NOT NULL DEFAULT 0,

  PRIMARY KEY (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
