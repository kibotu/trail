-- Record the custom_created_at migration
-- This is a separate file because DDL statements auto-commit in MySQL
-- and the migration runner tries to manage transactions

INSERT IGNORE INTO trail_migrations (migration_name) 
VALUES ('021_add_custom_created_at.sql');
