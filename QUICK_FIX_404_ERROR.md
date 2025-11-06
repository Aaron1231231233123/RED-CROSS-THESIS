# Quick Fix: 404 Error for low_inventory_notifications Table

## Problem
You're getting a **404 error** when the system tries to log to `low_inventory_notifications` table:
```
POST | 404 | ... | /rest/v1/low_inventory_notifications
```

## Solution
The table doesn't exist in your Supabase database. You need to create it.

## Steps to Fix

### 1. Open Supabase SQL Editor
1. Go to your Supabase project dashboard
2. Click on **SQL Editor** in the left sidebar
3. Click **New Query**

### 2. Run the SQL Script
Copy and paste the entire contents of `create_low_inventory_notifications_table.sql` into the SQL Editor and click **Run**.

Or copy this SQL directly:

```sql
-- Low Inventory Notifications Tracking Table
CREATE TABLE IF NOT EXISTS low_inventory_notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    donor_id INTEGER NOT NULL REFERENCES donor_form(donor_id),
    blood_type VARCHAR(5) NOT NULL CHECK (blood_type IN ('A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-')),
    units_at_time INTEGER NOT NULL,
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

-- Policy: Service role can do everything
CREATE POLICY "Service role full access to low_inventory_notifications" ON low_inventory_notifications
    FOR ALL USING (current_user = 'service_role');

-- Policy: Allow inserts from API (using anon/service role key)
CREATE POLICY "Allow inserts to low_inventory_notifications" ON low_inventory_notifications
    FOR INSERT WITH CHECK (true);

-- Policy: Authenticated users can view notifications
CREATE POLICY "Authenticated users can view low_inventory_notifications" ON low_inventory_notifications
    FOR SELECT USING (auth.role() = 'authenticated');
```

### 3. Verify Table Created
After running the SQL:
1. Go to **Table Editor** in Supabase
2. You should see `low_inventory_notifications` in the list
3. Check that it has all the columns listed above

### 4. Test Again
After creating the table, test the auto-notify system again. The 404 error should be gone.

## If You Still Get Errors

### Option 1: Disable RLS (For Testing)
If you're still getting permission errors, you can temporarily disable RLS:

```sql
ALTER TABLE low_inventory_notifications DISABLE ROW LEVEL SECURITY;
```

### Option 2: Check API Key
Make sure you're using the correct Supabase API key:
- **Service Role Key** for backend operations (has full access)
- **Anon Key** for client-side operations (limited by RLS)

The auto-notify API should use the **Service Role Key**.

## What This Table Does

The `low_inventory_notifications` table:
- Tracks when donors are notified about low inventory
- Prevents duplicate notifications (rate limiting)
- Stores notification type (push/email) and status (sent/failed/skipped)
- Records the number of units available when notification was sent

This is essential for the rate limiting feature that prevents spamming donors with notifications.

---

**File Location:** `create_low_inventory_notifications_table.sql`


