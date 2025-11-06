-- Low Inventory Notifications Tracking Table
-- Run this SQL in your Supabase SQL Editor
-- This table tracks when donors are notified about low blood inventory to prevent spam

CREATE TABLE IF NOT EXISTS low_inventory_notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    donor_id INTEGER NOT NULL REFERENCES donor_form(donor_id),
    blood_type VARCHAR(5) NOT NULL CHECK (blood_type IN ('A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-')),
    units_at_time INTEGER NOT NULL, -- Number of units when notification was sent
    notification_type VARCHAR(20) NOT NULL CHECK (notification_type IN ('push', 'email')),
    status VARCHAR(20) NOT NULL CHECK (status IN ('sent', 'failed', 'skipped')),
    notification_date TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_low_inventory_notifications_donor_id ON low_inventory_notifications(donor_id);
CREATE INDEX IF NOT EXISTS idx_low_inventory_notifications_blood_type ON low_inventory_notifications(blood_type);
CREATE INDEX IF NOT EXISTS idx_low_inventory_notifications_date ON low_inventory_notifications(notification_date);
CREATE INDEX IF NOT EXISTS idx_low_inventory_notifications_donor_blood_date ON low_inventory_notifications(donor_id, blood_type, notification_date);

-- Add RLS (Row Level Security) policies
ALTER TABLE low_inventory_notifications ENABLE ROW LEVEL SECURITY;

-- Policy: Service role can do everything (for backend operations)
CREATE POLICY "Service role full access to low_inventory_notifications" ON low_inventory_notifications
    FOR ALL USING (current_user = 'service_role');

-- Policy: Allow inserts from API (using anon/service role key)
-- This allows inserts even when using the anon key
CREATE POLICY "Allow inserts to low_inventory_notifications" ON low_inventory_notifications
    FOR INSERT WITH CHECK (true);

-- Policy: Authenticated users can view notifications
CREATE POLICY "Authenticated users can view low_inventory_notifications" ON low_inventory_notifications
    FOR SELECT USING (auth.role() = 'authenticated');

-- If you want to disable RLS completely (for testing), uncomment the line below:
-- ALTER TABLE low_inventory_notifications DISABLE ROW LEVEL SECURITY;

-- Add comments for documentation
COMMENT ON TABLE low_inventory_notifications IS 'Tracks low inventory notifications sent to donors to prevent duplicate notifications';
COMMENT ON COLUMN low_inventory_notifications.units_at_time IS 'Number of blood units available when notification was sent';
COMMENT ON COLUMN low_inventory_notifications.notification_type IS 'Type of notification sent: push or email';
COMMENT ON COLUMN low_inventory_notifications.notification_date IS 'Date and time when notification was sent (used for rate limiting)';



