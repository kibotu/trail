-- Collections: tag-defined groups that attribute entries in the landing feed
-- Migration: 036_add_collections.sql

CREATE TABLE IF NOT EXISTS trail_collections (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  owner_user_id INT UNSIGNED NOT NULL,
  name VARCHAR(140) NOT NULL,
  slug VARCHAR(64) NOT NULL,
  bio VARCHAR(160) NULL,
  avatar_image_id INT UNSIGNED NULL,
  header_image_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_collections_slug (slug),
  INDEX idx_collections_owner (owner_user_id),

  FOREIGN KEY (owner_user_id) REFERENCES trail_users(id) ON DELETE CASCADE,
  FOREIGN KEY (avatar_image_id) REFERENCES trail_images(id) ON DELETE SET NULL,
  FOREIGN KEY (header_image_id) REFERENCES trail_images(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trail_collection_tags (
  collection_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (collection_id, tag_id),
  INDEX idx_collection_tags_tag (tag_id),

  FOREIGN KEY (collection_id) REFERENCES trail_collections(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES trail_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE trail_views MODIFY target_type ENUM('entry', 'comment', 'profile', 'collection') NOT NULL;
ALTER TABLE trail_view_counts MODIFY target_type ENUM('entry', 'comment', 'profile', 'collection') NOT NULL;
