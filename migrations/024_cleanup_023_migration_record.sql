-- Cleanup migration record from failed 023 migration
-- Migration: 024_cleanup_023_migration_record.sql
-- Description: Remove the 023 migration record so it can be re-run cleanly

DELETE FROM trail_migrations WHERE migration_name = '023_simplify_created_at.sql';
