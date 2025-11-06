-- Fix RLS Policies for push_subscriptions Table
-- Run this SQL in your Supabase SQL Editor
-- This will allow the API to query push_subscriptions table

-- Drop existing policies if they exist
DROP POLICY IF EXISTS "Service role full access to push_subscriptions" ON push_subscriptions;
DROP POLICY IF EXISTS "Allow selects from push_subscriptions" ON push_subscriptions;
DROP POLICY IF EXISTS "Allow all operations on push_subscriptions" ON push_subscriptions;

-- Option 1: Allow service role full access (recommended for backend operations)
CREATE POLICY "Service role full access to push_subscriptions" ON push_subscriptions
    FOR ALL USING (current_user = 'service_role');

-- Option 2: Allow selects from API (using anon/service role key)
-- This allows queries even when using the anon key
CREATE POLICY "Allow selects from push_subscriptions" ON push_subscriptions
    FOR SELECT USING (true);

-- Option 3: Allow inserts (for when donors register)
CREATE POLICY "Allow inserts to push_subscriptions" ON push_subscriptions
    FOR INSERT WITH CHECK (true);

-- Option 4: If you want to allow all operations (less secure, but simpler for testing)
-- Uncomment the line below if the above policies don't work:
-- CREATE POLICY "Allow all operations on push_subscriptions" ON push_subscriptions
--     FOR ALL USING (true);

-- Verify RLS is enabled
ALTER TABLE push_subscriptions ENABLE ROW LEVEL SECURITY;

-- Add comments for documentation
COMMENT ON TABLE push_subscriptions IS 'Stores PWA push notification subscriptions for donors';
COMMENT ON COLUMN push_subscriptions.donor_id IS 'Foreign key to donor_form table';
COMMENT ON COLUMN push_subscriptions.endpoint IS 'Push service endpoint URL';
COMMENT ON COLUMN push_subscriptions.keys IS 'Encryption keys (p256dh, auth) for push notifications';


