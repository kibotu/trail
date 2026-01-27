-- Trail Service - Add Photo URL to Users
-- Migration: 003_add_photo_url_to_users.sql
-- Description: Add photo_url column to store Google profile photos with Gravatar fallback

-- Add photo_url column to trail_users table
ALTER TABLE trail_users 
ADD COLUMN photo_url TEXT AFTER gravatar_hash;

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('003_add_photo_url_to_users.sql');
