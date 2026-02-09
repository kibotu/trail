-- Trail Service - Add API Tokens to Users
-- Migration: 026_add_api_tokens_to_users.sql
-- Description: Add API token fields to users table for persistent API authentication

-- Step 1: Add API token fields to users table (allows NULL initially)
ALTER TABLE trail_users 
ADD COLUMN api_token VARCHAR(64) NULL AFTER is_admin,
ADD COLUMN api_token_created_at TIMESTAMP NULL AFTER api_token;

-- Step 2: Generate initial API tokens for all users
UPDATE trail_users 
SET api_token = CONCAT(
    SUBSTRING(MD5(CONCAT(id, google_id, UNIX_TIMESTAMP())), 1, 32),
    SUBSTRING(SHA1(CONCAT(email, UNIX_TIMESTAMP(), RAND())), 1, 32)
),
api_token_created_at = CURRENT_TIMESTAMP
WHERE api_token IS NULL;

-- Step 3: Add unique constraint and index after populating data
ALTER TABLE trail_users 
ADD UNIQUE INDEX idx_api_token (api_token);

-- Step 4: Make api_token NOT NULL now that all rows have values
ALTER TABLE trail_users 
MODIFY COLUMN api_token VARCHAR(64) NOT NULL;

-- Record this migration
INSERT INTO trail_migrations (migration_name) VALUES ('026_add_api_tokens_to_users.sql');
