# 🩸 Blood Drive Scheduling & Notification System - Complete Flow Explanation

This document explains how the blood drive scheduling and notification system works from start to finish.

## 📋 Table of Contents
1. [Overview](#overview)
2. [Database Structure](#database-structure)
3. [Scheduling Process](#scheduling-process)
4. [Notification Flow](#notification-flow)
5. [Update Mechanisms](#update-mechanisms)
6. [Step-by-Step Flow Diagram](#step-by-step-flow-diagram)

---

## 🎯 Overview

The system allows administrators to schedule blood drives and automatically notify eligible donors within a specified radius. The notification system uses:
- **PWA Push Notifications** (primary method) - for donors who have opted in
- **Email Notifications** (fallback) - for donors without push subscriptions
- **Location-based filtering** - only notifies donors within the specified radius
- **Blood type filtering** - can target specific blood types or all types

---

## 🗄️ Database Structure

### Main Tables

#### 1. `blood_drive_notifications`
Stores scheduled blood drives:
```sql
- id (UUID) - Primary key
- location (VARCHAR) - Location name
- latitude (DECIMAL) - GPS latitude
- longitude (DECIMAL) - GPS longitude
- drive_date (DATE) - Date of blood drive
- drive_time (TIME) - Time of blood drive
- radius_km (INTEGER) - Search radius in kilometers (default: 10km)
- blood_types (TEXT[]) - Array of blood types to target (empty = all)
- message_template (TEXT) - Custom message for notifications
- status (VARCHAR) - 'scheduled', 'active', 'completed', 'cancelled'
- created_by (INTEGER) - User ID who created the drive
- created_at (TIMESTAMP) - Auto-generated
- updated_at (TIMESTAMP) - Auto-updated
```

#### 2. `donor_form`
Stores donor information:
```sql
- donor_id (INTEGER) - Primary key
- surname, first_name, middle_name
- email, mobile
- permanent_latitude, permanent_longitude - For distance calculation
- ... (other donor fields)
```

#### 3. `push_subscriptions`
Stores PWA push notification subscriptions:
```sql
- donor_id (INTEGER) - Foreign key to donor_form
- endpoint (TEXT) - Push service endpoint URL
- keys (JSONB) - Encryption keys (p256dh, auth)
```

#### 4. `screening_form`
Stores donor blood types:
```sql
- donor_form_id (INTEGER) - Foreign key to donor_form
- blood_type (VARCHAR) - 'A+', 'A-', 'B+', etc.
- created_at (TIMESTAMP)
```

#### 5. `notification_logs`
Tracks all notification attempts:
```sql
- id (UUID) - Primary key
- blood_drive_id (UUID) - Foreign key to blood_drive_notifications
- donor_id (INTEGER) - Foreign key to donor_form
- notification_type (VARCHAR) - 'push', 'email', 'sms'
- status (VARCHAR) - 'sent', 'failed', 'skipped'
- reason (TEXT) - Why skipped/failed
- recipient (VARCHAR) - Email address or endpoint
- payload_json (JSONB) - Notification payload
- error_message (TEXT) - Error details if failed
- created_at (TIMESTAMP) - Auto-generated
```

---

## 📅 Scheduling Process

### Step 1: Admin Interface (Frontend)

**Location**: `public/Dashboards/dashboard-Inventory-System.php`

The admin interface includes:
1. **Location Selection** - Admin selects from "Top Donor Locations" (pre-populated from donor data)
2. **Date & Time Input** - Admin sets the blood drive date and time
3. **Schedule Button** - Triggers the notification process

**Code Location** (Lines 1534-1552):
```javascript
<form id="bloodDriveForm">
    <input type="text" id="selectedLocation" readonly />
    <input type="date" id="driveDate" />
    <input type="time" id="driveTime" />
    <button id="scheduleDriveBtn">Schedule Blood Drive</button>
</form>
```

### Step 2: Form Submission (JavaScript)

**Location**: `public/Dashboards/dashboard-Inventory-System.php` (Lines 2262-2359)

When admin clicks "Schedule Blood Drive":

```javascript
async function sendBloodDriveNotification() {
    // 1. Get form values
    const location = selectedLocationInput.value;
    const date = driveDate.value;
    const time = driveTime.value;
    
    // 2. Get coordinates for location
    const coords = getLocationCoordinates(location);
    
    // 3. Send POST request to API
    const response = await fetch('/RED-CROSS-THESIS/public/api/broadcast-blood-drive.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            location: location,
            drive_date: date,
            drive_time: time,
            latitude: coords.lat,
            longitude: coords.lng,
            radius_km: 15, // 15km radius
            blood_types: [], // Empty = all blood types
            custom_message: "🩸 Blood Drive Alert! ..."
        })
    });
    
    // 4. Display results
    showNotificationSuccess(result);
}
```

**Key Points**:
- Default radius: **15km**
- Empty `blood_types` array = **all blood types**
- Custom message is optional
- Uses async/await for non-blocking operation
- 2-minute timeout for large donor lists

---

## 🔔 Notification Flow

### Step 3: API Endpoint Processing

**Location**: `public/api/broadcast-blood-drive.php`

The API processes the request in the following order:

#### 3.1 Input Validation (Lines 108-136)
```php
// Validate required fields
$required_fields = ['location', 'drive_date', 'drive_time', 'latitude', 'longitude'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        throw new Exception("Missing required field: $field");
    }
}
```

#### 3.2 Create Blood Drive Record (Lines 138-209)
```php
// Prepare data
$blood_drive_data = [
    'location' => $location,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'drive_date' => $drive_date,
    'drive_time' => $drive_time,
    'radius_km' => $radius_km,
    'blood_types' => $blood_types, // Array of blood types
    'status' => 'scheduled'
];

// Insert into database
$blood_drive_response = supabaseRequest("blood_drive_notifications", "POST", $blood_drive_data);
$blood_drive_id = $blood_drive_response['data'][0]['id'];
```

**Result**: Blood drive record created with unique UUID

#### 3.3 Find Eligible Donors by Location (Lines 211-264)

**Process**:
1. Fetch donors in batches (2000 per batch, max 3000 total)
2. Filter by geographic distance using Haversine formula
3. Only include donors with valid coordinates within radius

```php
// Fetch donors in batches
do {
    $query = "donor_form?select=donor_id,...,permanent_latitude,permanent_longitude
              &permanent_latitude=not.is.null
              &permanent_longitude=not.is.null
              &limit=2000&offset=$offset";
    
    $donors_response = supabaseRequest($query);
    
    // Calculate distance for each donor
    foreach ($donors_response['data'] as $donor) {
        $distance = calculateDistance($latitude, $longitude, 
                                      $donor_lat, $donor_lng);
        
        if ($distance <= $radius_km) {
            $eligible_donors[] = $donor;
        }
    }
} while ($donors_checked < $max_donors_to_check);
```

**Haversine Formula** (Lines 55-65):
```php
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + 
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
        sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}
```

#### 3.4 Filter by Blood Type (Lines 266-293)

If specific blood types are specified:
```php
// Get blood types from screening_form
$screening_response = supabaseRequest(
    "screening_form?select=donor_form_id,blood_type
     &donor_form_id=in.($donor_ids_param)
     &blood_type=not.is.null
     &order=created_at.desc"
);

// Map blood types to donors (use most recent)
foreach ($screening_response['data'] as $screening) {
    if (!isset($blood_type_map[$donor_id])) {
        $blood_type_map[$donor_id] = $screening['blood_type'];
    }
}

// Filter eligible donors
$eligible_donors = array_filter($eligible_donors, function($donor) 
    use ($blood_type_map, $blood_types) {
    return in_array($blood_type_map[$donor['donor_id']], $blood_types);
});
```

#### 3.5 Get Push Subscriptions (Lines 295-309)

```php
$donor_ids_param = implode(',', $donor_ids);
$subscriptions_response = supabaseRequest(
    "push_subscriptions?select=donor_id,endpoint,keys
     &donor_id=in.($donor_ids_param)"
);

$subscriptions = $subscriptions_response['data'];
$donors_with_push = array_unique(array_column($subscriptions, 'donor_id'));
```

#### 3.6 Create Push Notification Payload (Lines 311-338)

```php
$push_payload = [
    'title' => '🩸 Blood Drive Alert',
    'body' => $custom_message ?: "Blood drive near you! Tap to confirm your slot.",
    'icon' => '/assets/image/PRC_Logo.png',
    'badge' => '/assets/image/PRC_Logo.png',
    'data' => [
        'url' => '/blood-drive-details?id=' . $blood_drive_id,
        'blood_drive_id' => $blood_drive_id,
        'location' => $location,
        'date' => $drive_date,
        'time' => $drive_time,
        'type' => 'blood_drive'
    ],
    'actions' => [
        ['action' => 'rsvp', 'title' => 'RSVP'],
        ['action' => 'dismiss', 'title' => 'Dismiss']
    ],
    'requireInteraction' => true,
    'tag' => 'blood-drive-' . $blood_drive_id
];
```

#### 3.7 Send Push Notifications (Lines 362-421)

**Process**:
1. Process in batches of 50 (to avoid timeout)
2. Send via `WebPushSender` class
3. Log each attempt (sent/failed)
4. Track notified donors to avoid duplicates

```php
$pushSender = new WebPushSender();
$subscriptions_batches = array_chunk($subscriptions, 50);

foreach ($subscriptions_batches as $batch) {
    foreach ($batch as $subscription) {
        $result = $pushSender->sendNotification($subscription, $payload_json);
        
        if ($result['success']) {
            $results['push']['sent']++;
            $notified_donors[$donor_id] = 'push';
            // Log success
            logNotification($blood_drive_id, $donor_id, 'push', 'sent', ...);
        } else {
            $results['push']['failed']++;
            // Log failure
            logNotification($blood_drive_id, $donor_id, 'push', 'failed', ...);
        }
    }
    
    usleep(50000); // 0.05s delay between batches
}
```

#### 3.8 Send Email Notifications (Fallback) (Lines 423-487)

**Process**:
1. Skip donors already notified via push
2. Skip donors with push subscriptions (even if push failed - avoid spam)
3. Check if donor has email address
4. Send via `EmailSender` class
5. Process in batches (delay every 25 emails)

```php
$emailSender = new EmailSender();

foreach ($eligible_donors as $donor) {
    // Skip if already notified via push
    if (isset($notified_donors[$donor_id])) continue;
    
    // Skip if has push subscription (avoid spam)
    if (in_array($donor_id, $donors_with_push)) {
        $results['email']['skipped']++;
        continue;
    }
    
    // Check email exists
    if (empty($donor['email'])) {
        $results['email']['skipped']++;
        continue;
    }
    
    // Send email
    $emailResult = $emailSender->sendEmailNotification($donor, $blood_drive_info);
    
    if ($emailResult['success']) {
        $results['email']['sent']++;
        logNotification($blood_drive_id, $donor_id, 'email', 'sent', ...);
    } else {
        $results['email']['failed']++;
        logNotification($blood_drive_id, $donor_id, 'email', 'failed', ...);
    }
}
```

#### 3.9 Return Results (Lines 489-520)

```php
$response = json_encode([
    'success' => true,
    'message' => "Blood drive notifications processed successfully",
    'blood_drive_id' => $blood_drive_id,
    'summary' => [
        'total_donors_found' => $total_donors_found,
        'push_subscriptions' => $total_push_subscriptions,
        'push_sent' => $total_push_sent,
        'push_failed' => $results['push']['failed'],
        'email_sent' => $total_email_sent,
        'email_failed' => $results['email']['failed'],
        'email_skipped' => $results['email']['skipped'],
        'total_notified' => $total_push_sent + $total_email_sent,
        'total_failed' => $total_failed,
        'total_skipped' => $total_skipped,
        'skip_reasons' => $results['skipped']['reasons']
    ],
    'results' => $results
]);
```

---

## 🔄 Update Mechanisms

### How Status Updates Work

The blood drive record can be updated manually or via scheduled jobs:

#### Manual Updates
```sql
UPDATE blood_drive_notifications 
SET status = 'active' 
WHERE id = 'blood-drive-uuid';
```

#### Status Values
- `scheduled` - Initial state when created
- `active` - Blood drive is currently happening
- `completed` - Blood drive finished
- `cancelled` - Blood drive was cancelled

### Notification Updates

The system doesn't automatically re-send notifications when status changes. To notify donors of updates:

1. **Manual Trigger**: Admin can call the API again with updated information
2. **Status Change Webhook**: (Future enhancement) Could trigger on status change
3. **Database Trigger**: (Future enhancement) Could auto-notify on status update

### Re-notification Prevention

The system tracks notifications in `notification_logs` table. To prevent spam:
- Check `notification_logs` before sending
- Filter by `blood_drive_id` and `donor_id`
- Check if notification was sent recently (e.g., within 24 hours)

---

## 📊 Step-by-Step Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. ADMIN INTERFACE (dashboard-Inventory-System.php)             │
│    - Select location from map                                   │
│    - Enter date and time                                        │
│    - Click "Schedule Blood Drive"                               │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. JAVASCRIPT (sendBloodDriveNotification)                      │
│    - Collect form data                                          │
│    - Get coordinates for location                              │
│    - POST to /api/broadcast-blood-drive.php                    │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. API ENDPOINT (broadcast-blood-drive.php)                     │
│    ┌──────────────────────────────────────────────────────┐   │
│    │ 3.1 Validate Input                                    │   │
│    │    - Check required fields                           │   │
│    │    - Validate data types                             │   │
│    └──────────────────┬───────────────────────────────────┘   │
│                       │                                          │
│    ┌──────────────────▼───────────────────────────────────┐   │
│    │ 3.2 Create Blood Drive Record                         │   │
│    │    - Insert into blood_drive_notifications           │   │
│    │    - Get UUID (blood_drive_id)                        │   │
│    └──────────────────┬───────────────────────────────────┘   │
│                       │                                          │
│    ┌──────────────────▼───────────────────────────────────┐   │
│    │ 3.3 Find Eligible Donors (Location)                   │   │
│    │    - Fetch donors in batches (2000 per batch)        │   │
│    │    - Calculate distance (Haversine formula)          │   │
│    │    - Filter by radius (default: 15km)                │   │
│    └──────────────────┬───────────────────────────────────┘   │
│                       │                                          │
│    ┌──────────────────▼───────────────────────────────────┐   │
│    │ 3.4 Filter by Blood Type (Optional)                   │   │
│    │    - Query screening_form for blood types            │   │
│    │    - Map to donor IDs                                │   │
│    │    - Filter eligible donors                          │   │
│    └──────────────────┬───────────────────────────────────┘   │
│                       │                                          │
│    ┌──────────────────▼───────────────────────────────────┐   │
│    │ 3.5 Get Push Subscriptions                            │   │
│    │    - Query push_subscriptions table                   │   │
│    │    - Get endpoint and keys                           │   │
│    └──────────────────┬───────────────────────────────────┘   │
│                       │                                          │
│    ┌──────────────────▼───────────────────────────────────┐   │
│    │ 3.6 Create Push Payload                               │   │
│    │    - Title, body, icon, badge                        │   │
│    │    - Data (URL, blood_drive_id, etc.)                │   │
│    │    - Actions (RSVP, Dismiss)                         │   │
│    └──────────────────┬───────────────────────────────────┘   │
│                       │                                          │
│    ┌──────────────────▼───────────────────────────────────┐   │
│    │ 3.7 Send Push Notifications                           │   │
│    │    - Process in batches (50 at a time)               │   │
│    │    - Use WebPushSender class                         │   │
│    │    - Log each attempt (sent/failed)                 │   │
│    │    - Track notified donors                           │   │
│    └──────────────────┬───────────────────────────────────┘   │
│                       │                                          │
│    ┌──────────────────▼───────────────────────────────────┐   │
│    │ 3.8 Send Email Notifications (Fallback)              │   │
│    │    - Skip donors with push subscriptions             │   │
│    │    - Skip donors already notified                    │   │
│    │    - Check email exists                              │   │
│    │    - Use EmailSender class                           │   │
│    │    - Process in batches (delay every 25)             │   │
│    └──────────────────┬───────────────────────────────────┘   │
│                       │                                          │
│    ┌──────────────────▼───────────────────────────────────┐   │
│    │ 3.9 Return Results                                    │   │
│    │    - Summary statistics                              │   │
│    │    - Push sent/failed/skipped                         │   │
│    │    - Email sent/failed/skipped                       │   │
│    └──────────────────┬───────────────────────────────────┘   │
└───────────────────────┼───────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. FRONTEND DISPLAY (dashboard-Inventory-System.php)           │
│    - Show success message                                       │
│    - Display summary statistics                                 │
│    - Reset form                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔍 Key Features

### 1. **Geographic Filtering**
- Uses Haversine formula for accurate distance calculation
- Only notifies donors within specified radius (default: 15km)
- Requires donors to have valid coordinates

### 2. **Blood Type Filtering**
- Can target specific blood types or all types
- Uses most recent blood type from `screening_form`
- Empty array = all blood types

### 3. **Dual Notification System**
- **Primary**: PWA Push Notifications (real-time, instant)
- **Fallback**: Email Notifications (for non-subscribed donors)
- **No Duplicates**: Donors with push subscriptions won't get email

### 4. **Batch Processing**
- Processes donors in batches to avoid timeout
- Push: 50 per batch
- Email: 25 per batch with delays
- Handles large donor lists efficiently

### 5. **Comprehensive Logging**
- All notification attempts logged to `notification_logs`
- Tracks: sent, failed, skipped
- Records reasons for skipped/failed
- Stores payload for debugging

### 6. **Error Handling**
- Graceful degradation if tables don't exist
- Continues processing even if some notifications fail
- Detailed error messages in logs
- User-friendly error display

---

## 📝 Example API Request

```json
POST /public/api/broadcast-blood-drive.php
Content-Type: application/json

{
    "location": "Manila City Hall",
    "drive_date": "2025-02-15",
    "drive_time": "09:00",
    "latitude": 14.5995,
    "longitude": 120.9842,
    "radius_km": 15,
    "blood_types": ["O+", "O-", "A+"],
    "custom_message": "🩸 Blood Drive Alert! Your blood type is urgently needed!"
}
```

## 📝 Example API Response

```json
{
    "success": true,
    "message": "Blood drive notifications processed successfully",
    "blood_drive_id": "550e8400-e29b-41d4-a716-446655440000",
    "summary": {
        "total_donors_found": 54,
        "push_subscriptions": 23,
        "push_sent": 22,
        "push_failed": 1,
        "email_sent": 28,
        "email_failed": 2,
        "email_skipped": 3,
        "total_notified": 50,
        "total_failed": 3,
        "total_skipped": 3,
        "skip_reasons": {
            "no_email": 3
        }
    },
    "results": {
        "push": {
            "sent": 22,
            "failed": 1,
            "errors": [...]
        },
        "email": {
            "sent": 28,
            "failed": 2,
            "skipped": 3,
            "errors": [...]
        }
    }
}
```

---

## 🚀 Future Enhancements

1. **Scheduled Re-notifications**: Auto-send reminders before blood drive
2. **Status Change Notifications**: Notify donors when status updates
3. **SMS Fallback**: Add SMS notifications as third fallback option
4. **Analytics Dashboard**: Track notification effectiveness and conversion rates
5. **Donor Preferences**: Allow donors to set notification preferences
6. **Smart Timing**: Send notifications at optimal times for each donor

---

## 📚 Related Files

- **API Endpoint**: `public/api/broadcast-blood-drive.php`
- **Frontend**: `public/Dashboards/dashboard-Inventory-System.php`
- **Email Sender**: `assets/php_func/email_sender.php`
- **Push Sender**: `assets/php_func/web_push_sender.php`
- **Database Schema**: `create_blood_drive_table.sql`
- **Notification Logs Schema**: `create_notification_logs_table.sql`

---

*Last Updated: 2025-01-XX*


