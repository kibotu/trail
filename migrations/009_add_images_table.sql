-- Trail Service - Image Upload Support
-- Migration: 009_add_images_table.sql
-- Description: Create images table with user relationships, add image columns to users and entries

-- Images table for storing uploaded images
CREATE TABLE IF NOT EXISTS trail_images (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  filename VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255),
  image_type ENUM('profile', 'header', 'post') NOT NULL,
  mime_type VARCHAR(50) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  width INT UNSIGNED,
  height INT UNSIGNED,
  etag VARCHAR(64),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  INDEX idx_user_type (user_id, image_type),
  INDEX idx_filename (filename),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add image references to users table
ALTER TABLE trail_users 
  ADD COLUMN profile_image_id INT UNSIGNED NULL,
  ADD COLUMN header_image_id INT UNSIGNED NULL,
  ADD FOREIGN KEY fk_profile_image (profile_image_id) REFERENCES trail_images(id) ON DELETE SET NULL,
  ADD FOREIGN KEY fk_header_image (header_image_id) REFERENCES trail_images(id) ON DELETE SET NULL;

-- Add image references to entries table (JSON array of image IDs)
ALTER TABLE trail_entries 
  ADD COLUMN image_ids TEXT NULL COMMENT 'JSON array of image IDs';

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('009_add_images_table.sql');
