-- Record the notifications system migration
-- This is a separate file because DDL statements auto-commit in MySQL
-- and the migration runner tries to manage transactions

INSERT IGNORE INTO trail_migrations (migration_name) 
VALUES ('018_add_notifications_system.sql');
