# 🩸 Auto-Notify Low Inventory System - How It Works

This document explains in detail how the auto-notify system works to send notifications when blood inventory drops below the threshold.

---

## 📋 Overview

The auto-notify system automatically monitors blood inventory levels and sends notifications to eligible donors when any blood type drops to 25 units or below. It uses:
- **PWA Push Notifications** (primary) - for donors registered in `push_subscriptions` table
- **Email Notifications** (fallback) - for donors without push subscriptions
- **Rate Limiting** - prevents spam by limiting notifications to once per day (configurable)

---

## 🔄 Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ STEP 1: TRIGGER                                                 │
│ The system is called manually or via cron job                   │
│ Endpoint: /public/api/auto-notify-low-inventory.php            │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 2: CHECK BLOOD INVENTORY                                   │
│ Query blood_bank_units table for each blood type                │
│ Count valid units (status = 'Valid' or 'reserved', not expired) │
│ Compare against threshold (default: 25 units)                   │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 3: IDENTIFY LOW INVENTORY TYPES                            │
│ Filter blood types where count ≤ threshold                      │
│ Example: O+ = 4 units (LOW), A+ = 30 units (OK)                │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 4: FIND DONORS WITH MATCHING BLOOD TYPES                   │
│ Query screening_form table for each low inventory blood type     │
│ Get donor_form_id and blood_type                                │
│ Limit: 500 donors per blood type (prevents timeout)             │
│ Result: Map of donor_id → blood_type                             │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 5: GET DONOR DETAILS                                       │
│ Query donor_form table for eligible donor IDs                   │
│ Get: donor_id, surname, first_name, email, mobile               │
│ Process in batches of 100                                       │
│ Limit: Max 1000 total donors (prevents timeout)                 │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 6: CHECK PUSH SUBSCRIPTIONS                                │
│ Query push_subscriptions table for eligible donor IDs            │
│ Get: donor_id, endpoint, keys                                   │
│ This determines who gets PUSH vs EMAIL                          │
│ Process in batches of 100                                       │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 7: RATE LIMITING CHECK                                     │
│ Query low_inventory_notifications table                          │
│ Check if donor was notified for this blood type recently         │
│ Default: Skip if notified within last 1 day                      │
│ Configurable: 1-45 days                                         │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 8: SEND PUSH NOTIFICATIONS                                 │
│ For each subscription in push_subscriptions:                    │
│   - Create push payload (title, body, data)                     │
│   - Send via WebPushSender class                                │
│   - Log to donor_notifications (status: sent/failed)            │
│   - Log to low_inventory_notifications (rate limiting)           │
│ Process in batches of 50 with delays                             │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 9: SEND EMAIL NOTIFICATIONS (FALLBACK)                     │
│ For donors WITHOUT push subscriptions:                           │
│   - Skip if already notified via push                           │
│   - Check if email exists                                       │
│   - Send via EmailSender class                                  │
│   - Log to donor_notifications (status: sent/failed)            │
│   - Log to low_inventory_notifications (rate limiting)           │
│ Process in batches of 25 with delays                            │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 10: RETURN RESULTS                                         │
│ Return JSON with:                                                │
│   - Inventory counts                                             │
│   - Low inventory types                                          │
│   - Push sent/failed/skipped                                     │
│   - Email sent/failed/skipped                                     │
│   - Total notified                                               │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔍 Step-by-Step Detailed Explanation

### Step 1: Trigger

**How it's called:**
- **Manual**: Via API endpoint or test script
- **Scheduled**: Via cron job (recommended: once per day)
- **URL**: `POST /public/api/auto-notify-low-inventory.php`

**Request Format:**
```json
{
    "threshold": 25,        // Optional, default: 25
    "rate_limit_days": 1    // Optional, default: 1 (1-45 days)
}
```

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 108-136)

---

### Step 2: Check Blood Inventory

**What it does:**
- Queries `blood_bank_units` table for each blood type (A+, A-, B+, B-, O+, O-, AB+, AB-)
- Counts only valid units:
  - Status = 'Valid' or 'reserved'
  - Not expired (`expires_at >= today`)
  - Not handed over

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 56-88)

**Example Query:**
```php
$query = "blood_bank_units?select=unit_id,status&blood_type=eq.O+&status=neq.handed_over&expires_at=gte.2025-01-15";
```

**Result:**
```php
$inventory = [
    'A+' => 7,   // LOW (≤ 25)
    'A-' => 3,   // LOW
    'B+' => 2,   // LOW
    'O+' => 4,   // LOW
    'O-' => 1,   // LOW
    'AB+' => 1,  // LOW
    'AB-' => 5,  // LOW
    'B-' => 30   // OK (> 25)
];
```

---

### Step 3: Identify Low Inventory Types

**What it does:**
- Compares each blood type count against threshold (default: 25)
- Creates list of blood types that need notifications

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 141-161)

**Result:**
```php
$low_inventory_types = ['A+', 'A-', 'B+', 'O+', 'O-', 'AB+', 'AB-'];
// B- is excluded because it has 30 units (> 25)
```

**Early Exit:**
If no blood types are low, the system returns immediately:
```json
{
    "success": true,
    "message": "No low inventory detected. All blood types are above threshold.",
    "notifications_sent": 0
}
```

---

### Step 4: Find Donors with Matching Blood Types

**What it does:**
- For each low inventory blood type, queries `screening_form` table
- Gets `donor_form_id` and `blood_type` for matching donors
- Uses most recent blood type per donor (ordered by `created_at DESC`)
- **Limit**: 500 donors per blood type (prevents timeout)

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 181-207)

**Example Query:**
```php
$query = "screening_form?select=donor_form_id,blood_type&blood_type=eq.O+&blood_type=not.is.null&order=created_at.desc&limit=500";
```

**Result:**
```php
$donor_blood_type_map = [
    123 => 'O+',
    456 => 'O+',
    789 => 'A+',
    // ... up to 500 per blood type
];
```

**Optimization:**
- Processes each blood type separately with a 0.05s delay between queries
- Prevents overwhelming the database

---

### Step 5: Get Donor Details

**What it does:**
- Queries `donor_form` table to get full donor information
- Gets: `donor_id`, `surname`, `first_name`, `middle_name`, `email`, `mobile`
- Processes in batches of 100
- **Limit**: Max 1000 total donors (prevents timeout)

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 209-243)

**Example Query:**
```php
$query = "donor_form?select=donor_id,surname,first_name,middle_name,email,mobile&donor_id=in.(123,456,789)";
```

**Result:**
```php
$donor_details = [
    123 => [
        'donor_id' => 123,
        'surname' => 'Doe',
        'first_name' => 'John',
        'email' => 'john@example.com',
        'blood_type' => 'O+'
    ],
    // ... more donors
];
```

---

### Step 6: Check Push Subscriptions

**What it does:**
- Queries `push_subscriptions` table for eligible donor IDs
- This is the **source of truth** for who gets push notifications
- Gets: `donor_id`, `endpoint`, `keys` (for Web Push encryption)
- Processes in batches of 100

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 245-278)

**Example Query:**
```php
$query = "push_subscriptions?select=donor_id,endpoint,keys&donor_id=in.(123,456,789)";
```

**Result:**
```php
$subscriptions = [
    [
        'donor_id' => 123,
        'endpoint' => 'https://fcm.googleapis.com/...',
        'keys' => ['p256dh' => '...', 'auth' => '...']
    ],
    // ... more subscriptions
];

$donors_with_push = [123, 456]; // Donor IDs with push subscriptions
```

**Key Point:**
- Only donors in `push_subscriptions` table will receive push notifications
- Donors NOT in this table will receive email notifications (if they have email)

---

### Step 7: Rate Limiting Check

**What it does:**
- Queries `low_inventory_notifications` table
- Checks if donor was already notified for this blood type within the rate limit period
- Default: 1 day (configurable 1-45 days)
- Prevents spam by skipping recent notifications

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 90-106)

**Example Query:**
```php
$cutoff_date = date('Y-m-d\TH:i:s\Z', strtotime("-1 days"));
$query = "low_inventory_notifications?select=id&donor_id=eq.123&blood_type=eq.O+&notification_date=gte.$cutoff_date&status=eq.sent";
```

**Logic:**
```php
if (wasNotifiedRecently($donor_id, $blood_type, 1)) {
    // Skip this donor - already notified within last day
    continue;
}
```

**Why This Matters:**
- Prevents sending the same notification multiple times
- Each blood type is tracked separately
- Donor can be notified for different blood types on different days

---

### Step 8: Send Push Notifications

**What it does:**
- For each subscription in `push_subscriptions`:
  1. Creates push notification payload
  2. Sends via `WebPushSender` class
  3. Logs to `donor_notifications` table (status: sent/failed)
  4. Logs to `low_inventory_notifications` table (for rate limiting)
- Processes in batches of 50 with 0.01s delays

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 280-366)

**Push Payload Example:**
```json
{
    "title": "🩸 Low Blood Inventory Alert",
    "body": "Only 4 units of O+ blood remaining. Your donation is urgently needed!",
    "icon": "/assets/image/PRC_Logo.png",
    "badge": "/assets/image/PRC_Logo.png",
    "data": {
        "url": "/donation-request",
        "blood_type": "O+",
        "units_available": 4,
        "type": "low_inventory"
    },
    "requireInteraction": true,
    "tag": "low-inventory-O+"
}
```

**Logging to `donor_notifications`:**
```php
$notification_data = [
    'donor_id' => 123,
    'payload_json' => $payload_json,
    'status' => 'sent',  // or 'failed'
    'created_at' => date('c')
];
@supabaseRequest("donor_notifications", "POST", $notification_data);
```

**Logging to `low_inventory_notifications`:**
```php
logLowInventoryNotification($donor_id, $blood_type, $units_available, 'push', 'sent');
```

---

### Step 9: Send Email Notifications (Fallback)

**What it does:**
- For donors WITHOUT push subscriptions:
  1. Skip if already notified via push
  2. Check if donor has email address
  3. Send via `EmailSender` class
  4. Log to `donor_notifications` table
  5. Log to `low_inventory_notifications` table
- Processes in batches of 25 with delays

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 368-468)

**Skip Logic:**
```php
// Skip if already notified via push
if (in_array($donor_id, $donors_with_push)) {
    continue; // Don't send email if they have push subscription
}

// Skip if no email
if (empty($donor['email'])) {
    continue; // Can't send email without address
}
```

**Email Content:**
- Uses `EmailSender` class (same as blood drive notifications)
- Adapts blood drive email template for low inventory
- Includes urgent messaging about low inventory

**Logging:**
- Same dual logging as push notifications
- Logs to both `donor_notifications` and `low_inventory_notifications`

---

### Step 10: Return Results

**What it does:**
- Returns comprehensive JSON response with all statistics
- Includes inventory counts, notification counts, errors, etc.

**Code Location**: `public/api/auto-notify-low-inventory.php` (Lines 470-520)

**Example Response:**
```json
{
    "success": true,
    "message": "Low inventory notifications processed",
    "threshold": 25,
    "rate_limit_days": 1,
    "inventory": {
        "A+": 7,
        "A-": 3,
        "B+": 2,
        "O+": 4,
        "O-": 1,
        "AB+": 1,
        "AB-": 5,
        "B-": 30
    },
    "low_inventory_types": ["A+", "A-", "B+", "O+", "O-", "AB+", "AB-"],
    "summary": {
        "push_sent": 45,
        "push_failed": 2,
        "push_skipped": 10,
        "email_sent": 28,
        "email_failed": 1,
        "email_skipped": 5,
        "total_notified": 73
    }
}
```

---

## 🔑 Key Concepts

### 1. Push Subscriptions Table is Source of Truth

The `push_subscriptions` table determines who gets push notifications:
- **If donor is in table** → Gets push notification
- **If donor is NOT in table** → Gets email notification (if email exists)

### 2. Dual Logging System

**`donor_notifications` table:**
- Tracks all notifications (push and email)
- Used for donor notification history
- Same structure as blood drive notifications
- Can be queried to show all notifications per donor

**`low_inventory_notifications` table:**
- Used for rate limiting
- Tracks blood type and units available
- Prevents duplicate notifications
- Tracks notification date for time-based filtering

### 3. Rate Limiting

- Prevents spam by limiting notifications per blood type
- Default: Once per day (1 day)
- Configurable: 1-45 days
- Each blood type tracked separately
- Donor can receive notification for different blood types on different days

### 4. Performance Optimizations

- **Query Limits**: 500 donors per blood type, 1000 total donors
- **Batch Processing**: Processes in batches (100 for queries, 50 for push, 25 for email)
- **Delays**: Small delays between batches to prevent overwhelming server
- **Early Exits**: Returns immediately if no low inventory or no eligible donors

---

## 📊 Database Tables Used

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `blood_bank_units` | Check inventory levels | `blood_type`, `status`, `expires_at` |
| `screening_form` | Find donors by blood type | `donor_form_id`, `blood_type` |
| `donor_form` | Get donor contact info | `donor_id`, `email`, `mobile` |
| `push_subscriptions` | **Source of truth** for push notifications | `donor_id`, `endpoint`, `keys` |
| `donor_notifications` | Log all notifications | `donor_id`, `payload_json`, `status` |
| `low_inventory_notifications` | Rate limiting | `donor_id`, `blood_type`, `notification_date` |

---

## 🚀 How to Use

### Manual Trigger

```bash
curl -X POST http://localhost/RED-CROSS-THESIS/public/api/auto-notify-low-inventory.php \
  -H "Content-Type: application/json" \
  -d '{"threshold": 25, "rate_limit_days": 1}'
```

### Scheduled (Cron Job)

Add to crontab (runs daily at 9 AM):
```bash
0 9 * * * curl -X POST http://localhost/RED-CROSS-THESIS/public/api/auto-notify-low-inventory.php -H "Content-Type: application/json" -d '{}'
```

### Via Test Script

Open in browser:
```
http://localhost/RED-CROSS-THESIS/test-notifications.php
```

---

## ✅ Summary

The auto-notify system:
1. ✅ Monitors blood inventory automatically
2. ✅ Finds eligible donors with matching blood types
3. ✅ Uses `push_subscriptions` table to determine notification method
4. ✅ Sends push notifications to subscribed donors
5. ✅ Falls back to email for non-subscribed donors
6. ✅ Logs everything to `donor_notifications` table
7. ✅ Prevents spam with rate limiting
8. ✅ Handles large donor lists efficiently with limits and batching

---

*Last Updated: 2025-01-XX*


