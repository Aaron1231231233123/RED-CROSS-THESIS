-- Donor Notifications Table
-- This table stores all notifications sent to donors (push and email)
-- It connects with blood_drive_notifications via blood_drive_id
-- Run this SQL in your Supabase SQL Editor

-- Note: If the table already exists with INTEGER blood_drive_id, run fix_donor_notifications_blood_drive_id_type.sql first
CREATE TABLE IF NOT EXISTS donor_notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    donor_id INTEGER NOT NULL REFERENCES donor_form(donor_id) ON DELETE CASCADE,
    blood_drive_id UUID REFERENCES blood_drive_notifications(id) ON DELETE SET NULL,
    payload_json JSONB NOT NULL, -- Stores the full notification payload (push or email)
    status VARCHAR(20) NOT NULL CHECK (status IN ('sent', 'failed', 'pending')),
    sent_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    error_message TEXT NULL -- Optional error message for failed notifications
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_donor_notifications_donor_id ON donor_notifications(donor_id);
CREATE INDEX IF NOT EXISTS idx_donor_notifications_blood_drive_id ON donor_notifications(blood_drive_id);
CREATE INDEX IF NOT EXISTS idx_donor_notifications_status ON donor_notifications(status);
CREATE INDEX IF NOT EXISTS idx_donor_notifications_sent_at ON donor_notifications(sent_at DESC);
CREATE INDEX IF NOT EXISTS idx_donor_notifications_donor_sent ON donor_notifications(donor_id, sent_at DESC);

-- Add RLS (Row Level Security) policies
ALTER TABLE donor_notifications ENABLE ROW LEVEL SECURITY;

-- Drop existing policies if they exist (to avoid conflicts)
DROP POLICY IF EXISTS "Service role full access to donor_notifications" ON donor_notifications;
DROP POLICY IF EXISTS "Allow inserts to donor_notifications" ON donor_notifications;
DROP POLICY IF EXISTS "Allow reads from API" ON donor_notifications;
DROP POLICY IF EXISTS "Authenticated users can view donor_notifications" ON donor_notifications;
DROP POLICY IF EXISTS "Allow all operations on donor_notifications" ON donor_notifications;
DROP POLICY IF EXISTS "Donors can view own notifications" ON donor_notifications;

-- Policy: Service role can do everything (for backend operations)
CREATE POLICY "Service role full access to donor_notifications" ON donor_notifications
    FOR ALL USING (current_setting('request.jwt.claims', true)::json->>'role' = 'service_role');

-- Policy: Allow inserts from API (using anon/service role key)
-- This allows inserts even when using the anon key
CREATE POLICY "Allow inserts to donor_notifications" ON donor_notifications
    FOR INSERT WITH CHECK (true);

-- Policy: Allow reads from API (using anon/service role key)
-- This allows SELECT queries from backend API calls
CREATE POLICY "Allow reads from API" ON donor_notifications
    FOR SELECT USING (true);

-- Policy: Authenticated users can view their own notifications
CREATE POLICY "Authenticated users can view donor_notifications" ON donor_notifications
    FOR SELECT USING (auth.role() = 'authenticated');

-- Add comments for documentation
COMMENT ON TABLE donor_notifications IS 'Stores all notifications sent to donors (push and email) for blood drives and other events';
COMMENT ON COLUMN donor_notifications.blood_drive_id IS 'Foreign key to blood_drive_notifications table (NULL for non-blood-drive notifications)';
COMMENT ON COLUMN donor_notifications.payload_json IS 'JSON payload of the notification (push or email)';
COMMENT ON COLUMN donor_notifications.status IS 'Status: sent or failed';
COMMENT ON COLUMN donor_notifications.sent_at IS 'Timestamp when notification was sent';

