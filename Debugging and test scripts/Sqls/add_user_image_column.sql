-- Add user_image column to users table
-- This migration adds a column to store user profile images
-- Matches the pattern used in donor_form.profile_picture (TEXT NULL)
-- Run this SQL in your Supabase SQL Editor

-- Add the user_image column
-- Using TEXT type to store image URL/path, same as donor_form.profile_picture
ALTER TABLE public.users
    ADD COLUMN IF NOT EXISTS user_image TEXT NULL;

-- Optional: Create an index if you plan to query by user_image frequently
-- CREATE INDEX IF NOT EXISTS idx_users_user_image ON public.users(user_image) WHERE user_image IS NOT NULL;

-- Verify the column was added
SELECT 
    column_name,
    data_type,
    udt_name,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_schema = 'public' 
  AND table_name = 'users'
  AND column_name = 'user_image';

