# Fix: blood_drive_id Type Mismatch

## 🔴 Problem Identified

Your database has a **type mismatch**:

- `blood_drive_notifications.id` = **UUID** ✅
- `donor_notifications.blood_drive_id` = **INTEGER** ❌ (should be UUID!)

This causes:
1. ❌ INSERTs to fail silently (UUID can't be stored in INTEGER column)
2. ❌ SELECT queries fail with "invalid input syntax for type integer"
3. ❌ No foreign key relationship (can't link INTEGER to UUID)

## ✅ Solution

### Step 1: Run the Migration SQL

Run this file in Supabase SQL Editor:
```
Debugging and test scripts/Sqls/fix_donor_notifications_blood_drive_id_type.sql
```

This will:
1. Drop the existing index
2. Drop any invalid foreign key constraints
3. Change `blood_drive_id` from INTEGER to UUID
4. Add proper foreign key constraint
5. Recreate the index

### Step 2: Verify the Fix

After running the migration, verify:

```sql
-- Check column type
SELECT column_name, data_type, udt_name
FROM information_schema.columns
WHERE table_name = 'donor_notifications' 
  AND column_name = 'blood_drive_id';
-- Should show: uuid

-- Check foreign key
SELECT constraint_name, table_name, column_name
FROM information_schema.key_column_usage
WHERE table_name = 'donor_notifications' 
  AND column_name = 'blood_drive_id';
-- Should show the foreign key constraint
```

### Step 3: Test Again

After the migration:
1. Re-run the test script
2. Schedule a new blood drive
3. Check if notifications are logged with `blood_drive_id` set

## 📊 Current Status

- **Total notifications**: 3
- **With blood_drive_id**: 0 ❌

After fix:
- **With blood_drive_id**: Should match number of notifications ✅

## ⚠️ Important Notes

1. **Existing Data**: The migration will set any existing non-NULL `blood_drive_id` values to NULL (since they're invalid integers). This is expected.

2. **No Data Loss**: Your notification records will remain intact, only the `blood_drive_id` column type will change.

3. **Future Inserts**: After the fix, all new notifications will properly link to blood drives via UUID.

---

**Run the migration SQL file to fix this issue!**

