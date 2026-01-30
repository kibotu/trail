-- Add custom_created_at field to support backdated entries
-- Migration: 021_add_custom_created_at.sql
-- Description: Allow entries to have custom creation timestamps (e.g., imported from Twitter)

ALTER TABLE trail_entries 
ADD COLUMN custom_created_at TIMESTAMP NULL AFTER created_at;

-- Add index for sorting by custom date
-- This supports efficient queries using COALESCE(custom_created_at, created_at)
CREATE INDEX idx_custom_created ON trail_entries(custom_created_at DESC);
