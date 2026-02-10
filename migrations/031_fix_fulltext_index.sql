-- Trail Service - Fix FULLTEXT Index After Column Removal
-- Migration: 031_fix_fulltext_index.sql
-- Description: Recreate FULLTEXT index on trail_entries.text only (preview columns were removed)

-- Drop the broken FULLTEXT index (if it still exists and references removed columns)
DROP INDEX IF EXISTS idx_search_text ON trail_entries;

-- Recreate FULLTEXT index with only the text column
ALTER TABLE trail_entries ADD FULLTEXT INDEX idx_search_text (text);

-- Record this migration (use INSERT IGNORE in case it's already recorded)
INSERT IGNORE INTO trail_migrations (migration_name) VALUES ('031_fix_fulltext_index.sql');
