-- Record the report system migration
-- This is a separate file because DDL statements auto-commit in MySQL
-- and the migration runner tries to manage transactions

INSERT IGNORE INTO trail_migrations (migration_name) 
VALUES ('011_add_report_and_mute_system.sql');
