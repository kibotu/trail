-- Migration: Add FULLTEXT search indexes to trail_entries
-- Purpose: Enable fast, forgiving search across entry text and URL preview metadata
-- Date: 2026-02-09

-- Add FULLTEXT index for entry text and preview metadata
-- This enables MySQL FULLTEXT search with natural language mode for relevance ranking
ALTER TABLE trail_entries 
ADD FULLTEXT INDEX idx_search_text (text, preview_title, preview_description, preview_site_name);

-- Note: FULLTEXT indexes in MySQL have a minimum word length of 3-4 characters by default
-- For shorter queries, the application will fall back to LIKE-based searching
