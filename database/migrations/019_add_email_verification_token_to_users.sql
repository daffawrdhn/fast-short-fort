-- Migration 019: Add dedicated email_verification_token column to users table
-- This separates the email verification token from the remember_token column,
-- which was previously shared (security vulnerability: a verification token could
-- be used as a remember-me cookie or vice-versa).

-- {{DRIVER:sqlite}}
ALTER TABLE users ADD COLUMN email_verification_token VARCHAR(255) NULL;
-- {{END}}

-- {{DRIVER:pgsql}}
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(255) NULL;
-- {{END}}
