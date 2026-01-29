-- Trail Service - Add Nickname Support
-- Migration: 008_add_nickname_to_users.sql
-- Description: Add nickname column to users table for display names (duplicate - safe to skip)

-- Add nickname column if it doesn't exist (ignore error if exists)
ALTER TABLE trail_users 
ADD COLUMN IF NOT EXISTS nickname VARCHAR(50) UNIQUE DEFAULT NULL AFTER name;

-- Add index if it doesn't exist (ignore error if exists)
CREATE INDEX IF NOT EXISTS idx_nickname ON trail_users (nickname);
