-- Trail Service - Add JWT Token to Sessions
-- Migration: 004_add_jwt_to_sessions.sql
-- Description: Add jwt_token column to sessions table for API access

ALTER TABLE trail_sessions ADD COLUMN jwt_token TEXT AFTER is_admin;

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('004_add_jwt_to_sessions.sql');
