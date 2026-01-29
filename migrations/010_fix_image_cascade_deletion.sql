-- Trail Service - Fix Image Cascade Deletion
-- Migration: 010_fix_image_cascade_deletion.sql
-- Description: Change CASCADE to RESTRICT to prevent accidental image deletion

-- Drop existing foreign key constraints on trail_images
ALTER TABLE trail_images 
  DROP FOREIGN KEY trail_images_ibfk_1;

-- Re-add foreign key with RESTRICT instead of CASCADE
-- This prevents user deletion if they have images
-- Images must be explicitly handled before user deletion
ALTER TABLE trail_images
  ADD CONSTRAINT fk_trail_images_user_id 
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE RESTRICT;

-- Add index for better performance on image_ids queries
ALTER TABLE trail_entries
  ADD INDEX idx_image_ids (image_ids(255));

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('010_fix_image_cascade_deletion.sql');
