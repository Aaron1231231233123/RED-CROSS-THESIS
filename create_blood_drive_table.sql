-- Blood Drive Notifications Table for Supabase
-- Run this SQL in your Supabase SQL Editor

-- Table to store scheduled blood drives
CREATE TABLE IF NOT EXISTS blood_drive_notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    location VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    drive_date DATE NOT NULL,
    drive_time TIME NOT NULL,
    radius_km INTEGER DEFAULT 10,
    blood_types TEXT[], -- Array of blood types to target
    message_template TEXT,
    status VARCHAR(20) DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'active', 'completed', 'cancelled')),
    created_by INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_blood_drive_notifications_location ON blood_drive_notifications(location);
CREATE INDEX IF NOT EXISTS idx_blood_drive_notifications_date ON blood_drive_notifications(drive_date);
CREATE INDEX IF NOT EXISTS idx_blood_drive_notifications_status ON blood_drive_notifications(status);
CREATE INDEX IF NOT EXISTS idx_blood_drive_notifications_created_by ON blood_drive_notifications(created_by);

-- Add spatial index for location-based queries
CREATE INDEX IF NOT EXISTS idx_blood_drive_notifications_geom ON blood_drive_notifications 
USING GIST (ST_SetSRID(ST_MakePoint(longitude, latitude), 4326));

-- Add RLS (Row Level Security) policies
ALTER TABLE blood_drive_notifications ENABLE ROW LEVEL SECURITY;

-- Policy: Service role can do everything (for backend operations)
CREATE POLICY "Service role full access to blood_drive_notifications" ON blood_drive_notifications
    FOR ALL USING (current_setting('request.jwt.claims', true)::json->>'role' = 'service_role' OR current_user = 'service_role');

-- Policy: Allow inserts from API (using anon/service role key)
CREATE POLICY "Allow inserts to blood_drive_notifications" ON blood_drive_notifications
    FOR INSERT WITH CHECK (true);

-- Policy: Authenticated users can view blood drives
CREATE POLICY "Authenticated users can view blood_drive_notifications" ON blood_drive_notifications
    FOR SELECT USING (true);

-- Add comments for documentation
COMMENT ON TABLE blood_drive_notifications IS 'Stores scheduled blood drive notifications';
COMMENT ON COLUMN blood_drive_notifications.radius_km IS 'Radius in kilometers to search for donors';
COMMENT ON COLUMN blood_drive_notifications.blood_types IS 'Array of blood types to target (empty = all types)';
COMMENT ON COLUMN blood_drive_notifications.message_template IS 'Custom message template for notifications';



