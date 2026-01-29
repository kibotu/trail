-- Record the error logs migration
-- This is a separate file because DDL statements auto-commit in MySQL
-- and the migration runner tries to manage transactions

INSERT IGNORE INTO trail_migrations (migration_name) 
VALUES ('013_add_error_logs_table.sql');
