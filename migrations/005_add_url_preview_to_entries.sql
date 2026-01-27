-- Trail Service - Add URL Preview Support
-- Migration: 005_add_url_preview_to_entries.sql
-- Description: Add columns to store URL preview card metadata

ALTER TABLE trail_entries
ADD COLUMN preview_url VARCHAR(2048) NULL AFTER text,
ADD COLUMN preview_title VARCHAR(255) NULL AFTER preview_url,
ADD COLUMN preview_description TEXT NULL AFTER preview_title,
ADD COLUMN preview_image VARCHAR(2048) NULL AFTER preview_description,
ADD COLUMN preview_site_name VARCHAR(255) NULL AFTER preview_image,
ADD INDEX idx_preview_url (preview_url(255));

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('005_add_url_preview_to_entries.sql');
