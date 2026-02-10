-- Trail Service - Drop Deprecated Preview Columns
-- Migration: 030_drop_entry_preview_columns.sql
-- Description: Remove deprecated preview_* columns from trail_entries after migration to trail_url_previews

-- Drop old indexes if they exist
DROP INDEX IF EXISTS idx_preview_url ON trail_entries;

-- Drop FULLTEXT index that includes preview columns (from migration 025)
DROP INDEX IF EXISTS idx_search_text ON trail_entries;

-- Recreate FULLTEXT index with only the text column
ALTER TABLE trail_entries ADD FULLTEXT INDEX idx_search_text (text);

-- Drop deprecated preview columns
ALTER TABLE trail_entries
  DROP COLUMN IF EXISTS preview_url,
  DROP COLUMN IF EXISTS preview_title,
  DROP COLUMN IF EXISTS preview_description,
  DROP COLUMN IF EXISTS preview_image,
  DROP COLUMN IF EXISTS preview_site_name,
  DROP COLUMN IF EXISTS preview_json,
  DROP COLUMN IF EXISTS preview_source;

-- Record this migration (use INSERT IGNORE in case it's already recorded)
INSERT IGNORE INTO trail_migrations (migration_name) VALUES ('030_drop_entry_preview_columns.sql');
