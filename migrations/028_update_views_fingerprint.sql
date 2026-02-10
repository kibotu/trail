-- Update views table to use fingerprint hash instead of just IP
-- Migration: 028_update_views_fingerprint.sql
--
-- NOTE: This migration is a no-op for fresh installs where 027 already
-- created the table with viewer_hash. The schema is already correct.
--
-- For systems that had the old viewer_ip schema, manual migration would be:
-- 1. ALTER TABLE trail_views ADD COLUMN viewer_hash BINARY(32) NULL AFTER viewer_ip;
-- 2. UPDATE trail_views SET viewer_hash = UNHEX(SHA2(HEX(viewer_ip), 256));
-- 3. ALTER TABLE trail_views MODIFY COLUMN viewer_hash BINARY(32) NOT NULL;
-- 4. ALTER TABLE trail_views DROP COLUMN viewer_ip;
-- 5. ALTER TABLE trail_views DROP INDEX idx_dedup_ip;
-- 6. ALTER TABLE trail_views ADD INDEX idx_dedup_hash (target_type, target_id, viewer_hash, created_at);

-- No-op: just mark this migration as complete
SELECT 1 AS noop_migration;
