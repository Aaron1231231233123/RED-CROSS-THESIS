-- Notification Logs Table for Blood Drive Notifications
-- Run this SQL in your Supabase SQL Editor

-- Table to track all notification attempts (push and email)
CREATE TABLE IF NOT EXISTS notification_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    blood_drive_id UUID REFERENCES blood_drive_notifications(id),
    donor_id INTEGER REFERENCES donor_form(donor_id),
    notification_type VARCHAR(20) NOT NULL CHECK (notification_type IN ('push', 'email', 'sms')),
    status VARCHAR(20) NOT NULL CHECK (status IN ('sent', 'failed', 'skipped')),
    reason TEXT, -- Reason for skipped or failed (e.g., 'no_push_subscription', 'no_email', 'push_failed')
    recipient VARCHAR(255), -- Email address, phone number, or endpoint
    payload_json JSONB, -- Store notification payload for debugging
    error_message TEXT, -- Error details if failed
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_notification_logs_blood_drive_id ON notification_logs(blood_drive_id);
CREATE INDEX IF NOT EXISTS idx_notification_logs_donor_id ON notification_logs(donor_id);
CREATE INDEX IF NOT EXISTS idx_notification_logs_type ON notification_logs(notification_type);
CREATE INDEX IF NOT EXISTS idx_notification_logs_status ON notification_logs(status);
CREATE INDEX IF NOT EXISTS idx_notification_logs_created_at ON notification_logs(created_at);

-- Add RLS (Row Level Security) policies
ALTER TABLE notification_logs ENABLE ROW LEVEL SECURITY;

-- Policy: Service role can do everything (for backend operations)
CREATE POLICY "Service role full access to notification_logs" ON notification_logs
    FOR ALL USING (current_user = 'service_role');

-- Policy: Authenticated users can view notification logs
CREATE POLICY "Authenticated users can view notification_logs" ON notification_logs
    FOR SELECT USING (auth.role() = 'authenticated');

-- Add comments for documentation
COMMENT ON TABLE notification_logs IS 'Tracks all notification attempts (push, email, SMS) for blood drives';
COMMENT ON COLUMN notification_logs.notification_type IS 'Type of notification: push, email, or sms';
COMMENT ON COLUMN notification_logs.status IS 'Status: sent, failed, or skipped';
COMMENT ON COLUMN notification_logs.reason IS 'Reason for skipped/failed status (e.g., no_push_subscription, no_email)';




