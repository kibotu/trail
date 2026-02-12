-- Add tags system for entry categorization
-- Migration: 033_add_tags_system.sql

CREATE TABLE IF NOT EXISTS trail_tags (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Indexes for lookup and search
  UNIQUE KEY idx_tag_slug (slug),
  INDEX idx_tag_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trail_entry_tags (
  entry_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Foreign keys with CASCADE delete
  FOREIGN KEY (entry_id) REFERENCES trail_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES trail_tags(id) ON DELETE CASCADE,
  
  -- Unique constraint: one record per entry per tag
  PRIMARY KEY (entry_id, tag_id),
  
  -- Indexes for performance
  INDEX idx_tag_entries (tag_id),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
