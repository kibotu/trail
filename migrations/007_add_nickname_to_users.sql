-- Trail Service - Add Nickname Support
-- Migration: 007_add_nickname_to_users.sql
-- Description: Add nickname column to users table for display names

-- Add nickname column (nullable initially, will be generated on first access)
ALTER TABLE trail_users 
ADD COLUMN nickname VARCHAR(50) UNIQUE DEFAULT NULL AFTER name,
ADD INDEX idx_nickname (nickname);

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('007_add_nickname_to_users.sql');
