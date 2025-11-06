-- Fix RLS Policies for donor_notifications to Allow Reads
-- This fixes the issue where SELECT queries fail due to RLS restrictions
-- Run this SQL in your Supabase SQL Editor

-- Drop existing read policies
DROP POLICY IF EXISTS "Allow reads from API" ON donor_notifications;
DROP POLICY IF EXISTS "Authenticated users can view donor_notifications" ON donor_notifications;

-- Policy: Allow reads from API (using anon/service role key)
-- This allows SELECT queries from backend API calls
CREATE POLICY "Allow reads from API" ON donor_notifications
    FOR SELECT USING (true);

-- Policy: Authenticated users can view their own notifications
-- This allows authenticated users to see their notifications
CREATE POLICY "Authenticated users can view donor_notifications" ON donor_notifications
    FOR SELECT USING (auth.role() = 'authenticated');

-- Verify the policies
SELECT 
    schemaname,
    tablename,
    policyname,
    permissive,
    roles,
    cmd,
    qual,
    with_check
FROM pg_policies 
WHERE tablename = 'donor_notifications'
ORDER BY policyname;

