# Blood Drive Notification System - Database Connection Guide

## Overview

This document explains how the blood drive notification system connects `blood_drive_notifications`, `donor_notifications`, and `push_subscriptions` tables.

## Database Schema Connections

### 1. `blood_drive_notifications` Table
**Purpose**: Stores scheduled blood drive events

**Key Fields**:
- `id` (UUID) - Primary key, referenced by `donor_notifications`
- `location`, `latitude`, `longitude` - Drive location
- `drive_date`, `drive_time` - When the drive happens
- `radius_km` - Search radius for eligible donors
- `blood_types` - Array of target blood types
- `status` - 'scheduled', 'active', 'completed', 'cancelled'

### 2. `push_subscriptions` Table
**Purpose**: Stores PWA push notification subscriptions for donors

**Key Fields**:
- `donor_id` (INTEGER) - Foreign key to `donor_form`
- `endpoint` (TEXT) - Push service endpoint URL
- `p256dh` (TEXT) - Encryption key
- `auth` (TEXT) - Authentication key

**Connection**: Used to find which donors have registered for push notifications

### 3. `donor_notifications` Table
**Purpose**: Tracks all notifications sent to donors (push and email)

**Key Fields**:
- `id` (UUID) - Primary key
- `donor_id` (INTEGER) - Foreign key to `donor_form`
- `blood_drive_id` (UUID) - **Foreign key to `blood_drive_notifications`** (links notifications to blood drives)
- `payload_json` (JSONB) - Full notification payload
- `status` (VARCHAR) - 'sent' or 'failed'
- `sent_at` (TIMESTAMP) - When notification was sent

**Connection**: Links notifications to specific blood drives via `blood_drive_id`

## How They Work Together

### Flow Diagram

```
┌─────────────────────────────────────┐
│ 1. Admin Schedules Blood Drive      │
│    Creates record in:               │
│    blood_drive_notifications        │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│ 2. System Finds Eligible Donors     │
│    - Queries donor_form              │
│    - Filters by location (radius)    │
│    - Filters by blood type           │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│ 3. System Checks Push Subscriptions  │
│    Queries: push_subscriptions       │
│    WHERE donor_id IN (eligible_ids)  │
└──────────────┬──────────────────────┘
               │
               ├──────────────────────┐
               │                      │
               ▼                      ▼
┌──────────────────────┐  ┌──────────────────────┐
│ 4a. Send Push        │  │ 4b. Send Email       │
│ Notifications        │  │ (Fallback)           │
│                      │  │                      │
│ Uses:                │  │ Uses:                │
│ - endpoint           │  │ - donor.email        │
│ - p256dh             │  │                      │
│ - auth               │  │                      │
└──────────┬───────────┘  └──────────┬───────────┘
           │                         │
           └──────────┬───────────────┘
                      │
                      ▼
┌─────────────────────────────────────┐
│ 5. Log to donor_notifications        │
│    - donor_id                        │
│    - blood_drive_id (links to drive) │
│    - payload_json                    │
│    - status (sent/failed)            │
└─────────────────────────────────────┘
```

## SQL Queries Used

### 1. Find Eligible Donors
```sql
SELECT donor_id, permanent_latitude, permanent_longitude
FROM donor_form
WHERE permanent_latitude IS NOT NULL
  AND permanent_longitude IS NOT NULL
LIMIT 2000;
```

### 2. Get Push Subscriptions
```sql
SELECT donor_id, endpoint, p256dh, auth
FROM push_subscriptions
WHERE donor_id IN (1, 2, 3, ...)
```

### 3. Log Notification
```sql
INSERT INTO donor_notifications (
    donor_id,
    blood_drive_id,
    payload_json,
    status
) VALUES (
    :donor_id,
    :blood_drive_id,
    :payload_json,
    'sent'
);
```

## Query Examples

### Get All Notifications for a Blood Drive
```sql
SELECT 
    dn.*,
    df.first_name,
    df.surname,
    bdn.location,
    bdn.drive_date
FROM donor_notifications dn
JOIN donor_form df ON dn.donor_id = df.donor_id
JOIN blood_drive_notifications bdn ON dn.blood_drive_id = bdn.id
WHERE bdn.id = 'blood-drive-uuid-here'
ORDER BY dn.sent_at DESC;
```

### Get All Notifications for a Donor
```sql
SELECT 
    dn.*,
    bdn.location,
    bdn.drive_date,
    bdn.drive_time
FROM donor_notifications dn
LEFT JOIN blood_drive_notifications bdn ON dn.blood_drive_id = bdn.id
WHERE dn.donor_id = 123
ORDER BY dn.sent_at DESC;
```

### Get Push Subscription Status for Blood Drive
```sql
SELECT 
    ps.donor_id,
    df.first_name,
    df.surname,
    CASE 
        WHEN dn.id IS NOT NULL THEN 'notified'
        ELSE 'not_notified'
    END as notification_status
FROM push_subscriptions ps
JOIN donor_form df ON ps.donor_id = df.donor_id
LEFT JOIN donor_notifications dn ON 
    dn.donor_id = ps.donor_id 
    AND dn.blood_drive_id = 'blood-drive-uuid-here'
WHERE ps.donor_id IN (
    SELECT donor_id 
    FROM eligible_donors_for_drive
);
```

## Setup Instructions

### Step 1: Create Tables

Run these SQL files in Supabase SQL Editor (in order):

1. `create_blood_drive_table.sql` - Creates blood_drive_notifications table
2. `create_donor_notifications_table.sql` - Creates donor_notifications table (with foreign key)
3. `create_notification_logs_table.sql` - Creates notification_logs table (optional, for detailed logging)

### Step 2: Set Up RLS Policies

Run these SQL files:

1. `fix_donor_notifications_rls.sql` - Allows API to insert notifications
2. `fix_push_subscriptions_rls.sql` - Allows API to query subscriptions

### Step 3: Verify Connections

Run this query to verify foreign key relationship:
```sql
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_NAME = 'donor_notifications'
  AND REFERENCED_TABLE_NAME IS NOT NULL;
```

## Verification Checklist

- [ ] `blood_drive_notifications` table exists
- [ ] `donor_notifications` table exists with `blood_drive_id` column
- [ ] Foreign key constraint exists: `donor_notifications.blood_drive_id` → `blood_drive_notifications.id`
- [ ] `push_subscriptions` table exists
- [ ] RLS policies allow API access
- [ ] Test blood drive scheduling works
- [ ] Notifications are logged to `donor_notifications`
- [ ] `blood_drive_id` is properly set in notifications

## Troubleshooting

### Issue: "Foreign key constraint violation"
**Solution**: Ensure `blood_drive_notifications` table exists before creating `donor_notifications`

### Issue: "Cannot insert into donor_notifications"
**Solution**: Run `fix_donor_notifications_rls.sql` to set up RLS policies

### Issue: "Cannot query push_subscriptions"
**Solution**: Run `fix_push_subscriptions_rls.sql` to set up RLS policies

### Issue: "blood_drive_id is NULL"
**Solution**: Check that the API is passing `blood_drive_id` when creating notifications

---

*Last Updated: 2025-01-XX*

