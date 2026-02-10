-- Trail Service - Add URL Previews Cache Table
-- Migration: 029_add_url_previews_table.sql
-- Description: Create trail_url_previews table for caching link preview data by URL

-- Create URL previews cache table
CREATE TABLE IF NOT EXISTS trail_url_previews (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  url VARCHAR(2048) NOT NULL,
  url_hash CHAR(64) NOT NULL COMMENT 'SHA-256 hash of normalized URL for unique index',
  title VARCHAR(255) NULL,
  description TEXT NULL,
  image VARCHAR(2048) NULL,
  site_name VARCHAR(255) NULL,
  json TEXT NULL COMMENT 'Full API response (Iframely, oEmbed, etc)',
  source ENUM('iframely', 'embed', 'medium') NULL,
  fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_url_hash (url_hash),
  FULLTEXT idx_preview_search (title, description, site_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add url_preview_id FK column to trail_entries
ALTER TABLE trail_entries 
ADD COLUMN url_preview_id INT UNSIGNED NULL AFTER image_ids,
ADD INDEX idx_url_preview_id (url_preview_id);

-- Add foreign key constraint
ALTER TABLE trail_entries 
ADD CONSTRAINT fk_entry_url_preview 
FOREIGN KEY (url_preview_id) REFERENCES trail_url_previews(id) ON DELETE SET NULL;

-- Populate trail_url_previews from existing entry data (deduplicated by URL)
-- Use INSERT IGNORE to handle any potential duplicates
INSERT IGNORE INTO trail_url_previews (url, url_hash, title, description, image, site_name, json, source, fetched_at)
SELECT 
    preview_url,
    SHA2(preview_url, 256),
    preview_title,
    preview_description,
    preview_image,
    preview_site_name,
    preview_json,
    preview_source,
    MIN(created_at) as fetched_at
FROM trail_entries 
WHERE preview_url IS NOT NULL
GROUP BY preview_url, preview_title, preview_description, preview_image, preview_site_name, preview_json, preview_source;

-- Link entries to their corresponding URL previews
UPDATE trail_entries e
INNER JOIN trail_url_previews p ON SHA2(e.preview_url, 256) = p.url_hash
SET e.url_preview_id = p.id
WHERE e.preview_url IS NOT NULL;

-- Record this migration (use INSERT IGNORE in case it's already recorded)
INSERT IGNORE INTO trail_migrations (migration_name) VALUES ('029_add_url_previews_table.sql');
