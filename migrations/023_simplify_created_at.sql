-- Simplify entries table by removing custom_created_at
-- Migration: 023_simplify_created_at.sql
-- Description: Merge custom_created_at into created_at and remove the custom column

-- NOTE: This migration is a no-op in production because custom_created_at was never added.
-- The column never existed in production, so there's nothing to migrate.
-- The code has been updated to use created_at directly when a custom date is provided.

-- This migration is already recorded in trail_migrations from a previous attempt
-- No SQL statements needed - the migration runner will record it automatically
