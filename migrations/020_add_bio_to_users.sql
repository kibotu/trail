-- Migration: 020_add_bio_to_users
-- Date: 2026-01-30
-- Description: Add bio column to users table for profile descriptions

-- Add bio column to trail_users table
ALTER TABLE trail_users
ADD COLUMN bio VARCHAR(160) NULL AFTER nickname;

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('020_add_bio_to_users.sql');
