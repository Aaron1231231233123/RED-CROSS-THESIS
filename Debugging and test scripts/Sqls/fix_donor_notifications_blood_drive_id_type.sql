-- Fix donor_notifications.blood_drive_id Type Mismatch
-- This changes the column from INTEGER to UUID to match blood_drive_notifications.id
-- Run this SQL in your Supabase SQL Editor

-- Step 1: Drop the existing index (will be recreated)
DROP INDEX IF EXISTS idx_donor_notifications_blood_drive_id;

-- Step 2: Drop any existing foreign key constraint (if it exists with wrong type)
ALTER TABLE donor_notifications 
    DROP CONSTRAINT IF EXISTS fk_donor_notifications_blood_drive;

-- Step 3: Update existing NULL values (if any) - no action needed for NULL
-- Step 4: Change the column type from INTEGER to UUID
-- Note: This will fail if there are non-NULL integer values
-- If you have data, you'll need to clear it first or convert it

-- Check if there are any non-NULL values that need to be handled
DO $$
DECLARE
    non_null_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO non_null_count 
    FROM donor_notifications 
    WHERE blood_drive_id IS NOT NULL;
    
    IF non_null_count > 0 THEN
        RAISE NOTICE 'Warning: Found % non-NULL blood_drive_id values. These will be lost during type conversion.', non_null_count;
        RAISE NOTICE 'Consider setting them to NULL first if they are invalid.';
    END IF;
END $$;

-- Step 5: Change column type to UUID
ALTER TABLE donor_notifications 
    ALTER COLUMN blood_drive_id TYPE UUID USING NULL;

-- Step 6: Add foreign key constraint
ALTER TABLE donor_notifications
    ADD CONSTRAINT fk_donor_notifications_blood_drive 
    FOREIGN KEY (blood_drive_id) 
    REFERENCES blood_drive_notifications(id) 
    ON DELETE SET NULL;

-- Step 7: Recreate the index
CREATE INDEX IF NOT EXISTS idx_donor_notifications_blood_drive_id 
    ON donor_notifications(blood_drive_id);

-- Step 8: Verify the change
SELECT 
    column_name,
    data_type,
    udt_name,
    is_nullable
FROM information_schema.columns
WHERE table_schema = 'public' 
  AND table_name = 'donor_notifications'
  AND column_name = 'blood_drive_id';

-- Step 9: Verify foreign key constraint
SELECT
    tc.constraint_name,
    tc.table_name, 
    kcu.column_name, 
    ccu.table_name AS foreign_table_name,
    ccu.column_name AS foreign_column_name 
FROM information_schema.table_constraints AS tc 
JOIN information_schema.key_column_usage AS kcu
  ON tc.constraint_name = kcu.constraint_name
  AND tc.table_schema = kcu.table_schema
JOIN information_schema.constraint_column_usage AS ccu
  ON ccu.constraint_name = tc.constraint_name
  AND ccu.table_schema = tc.table_schema
WHERE tc.constraint_type = 'FOREIGN KEY' 
  AND tc.table_name = 'donor_notifications'
  AND kcu.column_name = 'blood_drive_id';

