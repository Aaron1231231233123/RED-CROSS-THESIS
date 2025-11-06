-- Verify donor_notifications table schema
-- Run this to check if blood_drive_id column type is correct

-- Check column types
SELECT 
    column_name,
    data_type,
    udt_name,
    character_maximum_length,
    is_nullable
FROM information_schema.columns
WHERE table_schema = 'public' 
  AND table_name = 'donor_notifications'
ORDER BY ordinal_position;

-- Check foreign key constraints
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

-- Check if there are any records
SELECT COUNT(*) as total_notifications,
       COUNT(CASE WHEN blood_drive_id IS NOT NULL THEN 1 END) as with_blood_drive_id
FROM donor_notifications;

