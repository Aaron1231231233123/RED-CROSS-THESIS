# Auto-Notify Low Inventory Integration Guide

This document explains how the auto-notify low inventory system integrates with the `push_subscriptions` and `donor_notifications` tables.

## 🔗 Integration Overview

The auto-notify system now fully integrates with the existing notification infrastructure:

1. **`push_subscriptions` Table** - Used to find donors who have registered for PWA push notifications
2. **`donor_notifications` Table** - Stores all notification records (push and email) for tracking
3. **`low_inventory_notifications` Table** - Used for rate limiting to prevent duplicate notifications

## 📊 Database Tables Used

### 1. `push_subscriptions` Table
**Purpose**: Find donors with active push notification subscriptions

**Query Location**: `public/api/auto-notify-low-inventory.php` (Lines 236-260)

```php
// Get push subscriptions for eligible donors
$subscriptions_query = "push_subscriptions?select=donor_id,endpoint,keys&donor_id=in.($donor_ids_param)";
$subscriptions_response = supabaseRequest($subscriptions_query);

// Process subscriptions
foreach ($subscriptions as $subscription) {
    $result = $pushSender->sendNotification($subscription, $payload_json);
    // ...
}
```

**Table Structure** (expected):
```sql
- donor_id (INTEGER) - Foreign key to donor_form
- endpoint (TEXT) - Push service endpoint URL
- keys (JSONB) - Encryption keys (p256dh, auth)
```

### 2. `donor_notifications` Table
**Purpose**: Track all notifications sent to donors (same as blood drive notifications)

**Logging Location**: `public/api/auto-notify-low-inventory.php` (Lines 319-328, 335-342, 422-429, 436-443)

**For Push Notifications**:
```php
$notification_data = [
    'donor_id' => $donor_id,
    'payload_json' => $payload_json,  // Full push notification payload
    'status' => 'sent',  // or 'failed'
    'created_at' => date('c')
];
@supabaseRequest("donor_notifications", "POST", $notification_data);
```

**For Email Notifications**:
```php
$notification_data = [
    'donor_id' => $donor_id,
    'payload_json' => json_encode($email_payload),  // Email notification payload
    'status' => 'sent',  // or 'failed'
    'created_at' => date('c')
];
@supabaseRequest("donor_notifications", "POST", $notification_data);
```

**Table Structure** (expected):
```sql
- id (UUID) - Primary key
- donor_id (INTEGER) - Foreign key to donor_form
- payload_json (JSONB) - Notification payload (push or email)
- status (VARCHAR) - 'sent' or 'failed'
- blood_drive_id (UUID, optional) - For blood drive notifications (NULL for low inventory)
- created_at (TIMESTAMP) - Auto-generated
```

### 3. `low_inventory_notifications` Table
**Purpose**: Rate limiting to prevent duplicate notifications

**Logging Location**: `public/api/auto-notify-low-inventory.php` (via `logLowInventoryNotification()` function)

**Used For**:
- Rate limiting checks (prevents sending same notification within X days)
- Tracking which blood types were notified
- Tracking units available at time of notification

## 🔄 Complete Notification Flow

```
┌─────────────────────────────────────────────────────────┐
│ 1. Check Blood Inventory                                │
│    - Query blood_bank_units by blood type               │
│    - Find types with ≤ 25 units                         │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Find Eligible Donors                                 │
│    - Query screening_form for matching blood types     │
│    - Get donor details from donor_form                  │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Get Push Subscriptions                               │
│    - Query push_subscriptions table                    │
│    - Filter by donor_id (in batches)                    │
│    - Get endpoint and keys for each subscription        │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Check Rate Limiting                                  │
│    - Query low_inventory_notifications                  │
│    - Check if notified within rate_limit_days          │
│    - Skip if already notified recently                  │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Send Push Notifications                              │
│    - For each subscription in push_subscriptions        │
│    - Send via WebPushSender                             │
│    - Log to donor_notifications (sent/failed)           │
│    - Log to low_inventory_notifications (rate limiting) │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Send Email Notifications (Fallback)                  │
│    - For donors WITHOUT push subscriptions              │
│    - Send via EmailSender                               │
│    - Log to donor_notifications (sent/failed)           │
│    - Log to low_inventory_notifications (rate limiting) │
└─────────────────────────────────────────────────────────┘
```

## 📝 Notification Payload Structure

### Push Notification Payload (stored in `donor_notifications.payload_json`)
```json
{
  "title": "🩸 Low Blood Inventory Alert",
  "body": "Only X units of O+ blood remaining. Your donation is urgently needed!",
  "icon": "/assets/image/PRC_Logo.png",
  "badge": "/assets/image/PRC_Logo.png",
  "data": {
    "url": "/donation-request",
    "blood_type": "O+",
    "units_available": 15,
    "type": "low_inventory"
  },
  "requireInteraction": true,
  "tag": "low-inventory-O+"
}
```

### Email Notification Payload (stored in `donor_notifications.payload_json`)
```json
{
  "title": "🩸 Low Blood Inventory Alert",
  "body": "URGENT: Only X units of O+ blood remaining in our inventory. Your donation is urgently needed to save lives!",
  "type": "low_inventory",
  "blood_type": "O+",
  "units_available": 15,
  "recipient": "donor@example.com"
}
```

## 🔍 Key Integration Points

### 1. Push Subscriptions Query
**File**: `public/api/auto-notify-low-inventory.php` (Lines 236-260)

```php
// Query push_subscriptions in batches
foreach ($donor_ids_batches as $batch) {
    $donor_ids_param = implode(',', $batch);
    $subscriptions_query = "push_subscriptions?select=donor_id,endpoint,keys&donor_id=in.($donor_ids_param)";
    $subscriptions_response = supabaseRequest($subscriptions_query);
    
    if (isset($subscriptions_response['data']) && is_array($subscriptions_response['data'])) {
        $subscriptions = array_merge($subscriptions, $subscriptions_response['data']);
    }
}
```

**Key Points**:
- Queries in batches of 100 to avoid URL length issues
- Only gets donors who have push subscriptions
- Uses the same pattern as `broadcast-blood-drive.php`

### 2. Donor Notifications Logging
**File**: `public/api/auto-notify-low-inventory.php` (Lines 319-328, 422-429)

**Push Notifications**:
```php
if ($result['success']) {
    $notification_data = [
        'donor_id' => $donor_id,
        'payload_json' => $payload_json,
        'status' => 'sent',
        'created_at' => date('c')
    ];
    @supabaseRequest("donor_notifications", "POST", $notification_data);
}
```

**Email Notifications**:
```php
if ($emailResult['success']) {
    $notification_data = [
        'donor_id' => $donor_id,
        'payload_json' => json_encode($email_payload),
        'status' => 'sent',
        'created_at' => date('c')
    ];
    @supabaseRequest("donor_notifications", "POST", $notification_data);
}
```

**Key Points**:
- Logs both successful and failed notifications
- Uses same structure as blood drive notifications
- Non-blocking (uses `@` error suppression)
- Does NOT include `blood_drive_id` (NULL for low inventory notifications)

### 3. Dual Logging System

The system logs to **two tables** for different purposes:

1. **`donor_notifications`** - For tracking and displaying notifications to donors
   - Used by PWA to show notification history
   - Same table structure as blood drive notifications
   - Can be queried to show all notifications for a donor

2. **`low_inventory_notifications`** - For rate limiting and tracking
   - Prevents duplicate notifications within rate limit period
   - Tracks specific blood type notifications
   - Tracks units available at time of notification

## 📊 Querying Notifications

### Get All Notifications for a Donor
```sql
SELECT * FROM donor_notifications 
WHERE donor_id = 123 
ORDER BY created_at DESC;
```

### Get Push Notifications Only
```sql
SELECT * FROM donor_notifications 
WHERE donor_id = 123 
AND payload_json->>'type' = 'low_inventory'
ORDER BY created_at DESC;
```

### Get Recent Low Inventory Notifications
```sql
SELECT * FROM low_inventory_notifications 
WHERE donor_id = 123 
AND blood_type = 'O+'
ORDER BY notification_date DESC
LIMIT 10;
```

### Check Rate Limiting Status
```sql
SELECT COUNT(*) FROM low_inventory_notifications 
WHERE donor_id = 123 
AND blood_type = 'O+'
AND notification_date >= NOW() - INTERVAL '1 day'
AND status = 'sent';
```

## ✅ Verification Checklist

- [x] System queries `push_subscriptions` table correctly
- [x] System logs to `donor_notifications` table for all notifications
- [x] System logs to `low_inventory_notifications` for rate limiting
- [x] Push notifications use same payload structure as blood drives
- [x] Email notifications are logged with proper payload
- [x] Failed notifications are logged with 'failed' status
- [x] Non-blocking logging (won't break if tables don't exist)

## 🚀 Usage Example

```php
// Manual trigger via API
POST /public/api/auto-notify-low-inventory.php
Content-Type: application/json

{
    "threshold": 25,
    "rate_limit_days": 1
}

// Response includes:
{
    "success": true,
    "summary": {
        "push_sent": 45,
        "push_failed": 2,
        "email_sent": 28,
        "email_failed": 1
    }
}

// Check donor_notifications table:
SELECT COUNT(*) FROM donor_notifications 
WHERE created_at >= NOW() - INTERVAL '1 hour';
// Should return ~73 (45 push + 28 email)

// Check push_subscriptions usage:
SELECT COUNT(DISTINCT donor_id) FROM push_subscriptions 
WHERE donor_id IN (
    SELECT donor_id FROM donor_notifications 
    WHERE created_at >= NOW() - INTERVAL '1 hour'
);
// Should return number of unique donors notified
```

## 🔧 Troubleshooting

### Notifications Not Being Sent

1. **Check push_subscriptions table**:
   ```sql
   SELECT COUNT(*) FROM push_subscriptions;
   ```

2. **Check donor_notifications table**:
   ```sql
   SELECT * FROM donor_notifications 
   WHERE created_at >= NOW() - INTERVAL '1 hour'
   ORDER BY created_at DESC;
   ```

3. **Check rate limiting**:
   ```sql
   SELECT * FROM low_inventory_notifications 
   WHERE notification_date >= NOW() - INTERVAL '1 day';
   ```

### Notifications Not Logged to donor_notifications

- Check if table exists: `SELECT * FROM donor_notifications LIMIT 1;`
- Check RLS policies allow inserts
- Check API logs for errors
- Verify `supabaseRequest()` function works correctly

### Push Subscriptions Not Found

- Verify donors have subscriptions: `SELECT * FROM push_subscriptions WHERE donor_id = X;`
- Check query syntax in Step 4 of the API
- Verify batch processing is working correctly

---

*Last Updated: 2025-01-XX*


