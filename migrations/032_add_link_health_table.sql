-- Trail Service - Add Link Health Tracking Table
-- Migration: 032_add_link_health_table.sql
-- Description: Create trail_link_health table for tracking HTTP health status of URLs

-- Create link health tracking table
CREATE TABLE IF NOT EXISTS trail_link_health (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  url_preview_id INT UNSIGNED NOT NULL,
  http_status_code SMALLINT UNSIGNED NULL COMMENT '0 = connection failure',
  error_type ENUM(
    'none','http_error','timeout','dns_error',
    'ssl_error','connection_refused','redirect_loop','unknown'
  ) NOT NULL DEFAULT 'none',
  error_message VARCHAR(500) NULL,
  consecutive_failures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_checked_at TIMESTAMP NULL,
  last_healthy_at TIMESTAMP NULL,
  is_broken BOOLEAN NOT NULL DEFAULT FALSE,
  is_dismissed BOOLEAN NOT NULL DEFAULT FALSE,
  dismissed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_url_preview_id (url_preview_id),
  KEY idx_is_broken (is_broken),
  KEY idx_last_checked (last_checked_at),
  CONSTRAINT fk_link_health_preview
    FOREIGN KEY (url_preview_id) REFERENCES trail_url_previews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record this migration (use INSERT IGNORE in case it's already recorded)
INSERT IGNORE INTO trail_migrations (migration_name) VALUES ('032_add_link_health_table.sql');
