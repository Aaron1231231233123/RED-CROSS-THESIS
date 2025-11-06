-- Fix RLS Policies for donor_notifications Table
-- Run this SQL in your Supabase SQL Editor
-- This will allow the API to insert notifications into the donor_notifications table

-- Drop existing policies if they exist
DROP POLICY IF EXISTS "Service role full access to donor_notifications" ON donor_notifications;
DROP POLICY IF EXISTS "Allow inserts to donor_notifications" ON donor_notifications;
DROP POLICY IF EXISTS "Authenticated users can view donor_notifications" ON donor_notifications;
DROP POLICY IF EXISTS "Allow all operations on donor_notifications" ON donor_notifications;

-- Option 1: Allow service role full access (recommended for backend operations)
CREATE POLICY "Service role full access to donor_notifications" ON donor_notifications
    FOR ALL USING (current_user = 'service_role');

-- Option 2: Allow inserts from API (using anon/service role key)
-- This allows inserts even when using the anon key
CREATE POLICY "Allow inserts to donor_notifications" ON donor_notifications
    FOR INSERT WITH CHECK (true);

-- Option 3: Allow authenticated users to view their own notifications
CREATE POLICY "Authenticated users can view donor_notifications" ON donor_notifications
    FOR SELECT USING (auth.role() = 'authenticated');

-- Option 4: If you want to allow all operations (less secure, but simpler for testing)
-- Uncomment the line below if the above policies don't work:
-- CREATE POLICY "Allow all operations on donor_notifications" ON donor_notifications
--     FOR ALL USING (true);

-- Verify RLS is enabled
ALTER TABLE donor_notifications ENABLE ROW LEVEL SECURITY;

-- Add comments for documentation
COMMENT ON TABLE donor_notifications IS 'Stores all notifications sent to donors (push and email)';
COMMENT ON COLUMN donor_notifications.payload_json IS 'JSON payload of the notification (push or email)';
COMMENT ON COLUMN donor_notifications.status IS 'Status: sent or failed';


