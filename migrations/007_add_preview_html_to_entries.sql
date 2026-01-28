-- Trail Service - Add Iframely JSON Preview Support
-- Migration: 007_add_preview_json_to_entries.sql
-- Description: Add columns to store full Iframely JSON response and preview source

ALTER TABLE trail_entries
ADD COLUMN preview_json TEXT NULL AFTER preview_site_name,
ADD COLUMN preview_source ENUM('iframely', 'embed', 'medium') NULL AFTER preview_json;

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('007_add_preview_json_to_entries.sql');
