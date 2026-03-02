-- Migration: Add soft-delete support for user account deletion (GDPR Art. 17)
-- When non-NULL, the user has requested account deletion.
-- Content is filtered from public views via JOINs on this column.
-- Setting back to NULL reverts the deletion request.

ALTER TABLE trail_users
  ADD COLUMN deletion_requested_at DATETIME NULL DEFAULT NULL AFTER api_token_created_at;
