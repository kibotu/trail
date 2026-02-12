-- Migration: Add short link resolve tracking
-- Adds a timestamp column to track failed resolution attempts for short URLs
-- This enables smart retry ordering: untried first, then oldest failures

ALTER TABLE trail_url_previews 
ADD COLUMN short_link_resolve_failed_at TIMESTAMP NULL DEFAULT NULL 
COMMENT 'Timestamp of last failed resolution attempt for short URLs';

CREATE INDEX idx_short_link_resolve_failed 
ON trail_url_previews (short_link_resolve_failed_at);
