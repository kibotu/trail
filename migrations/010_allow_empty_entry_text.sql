-- Trail Service - Allow Empty Entry Text
-- Migration: 010_allow_empty_entry_text.sql
-- Description: Allow entries to have empty text (image-only posts)

-- Modify text column to allow NULL and empty strings
ALTER TABLE trail_entries 
  MODIFY COLUMN text VARCHAR(280) NOT NULL DEFAULT '';

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('010_allow_empty_entry_text.sql');
