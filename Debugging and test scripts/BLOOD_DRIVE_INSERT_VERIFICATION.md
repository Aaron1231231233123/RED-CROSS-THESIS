# Blood Drive Insert Format Verification

## ✅ Expected Behavior

When you schedule a blood drive, the system should create a database record exactly like this:

```sql
INSERT INTO "public"."blood_drive_notifications" 
("id", "location", "latitude", "longitude", "drive_date", "drive_time", "radius_km", "blood_types", "message_template", "status", "created_by", "created_at", "updated_at") 
VALUES 
('7ceece3b-0c6b-44e5-8bd9-61afd74661ae', 'Oton', '10.69330000', '122.47330000', '2025-11-08', '09:45:00', '15', '{"A+","A-","B+","B-","O+","O-","AB+","AB-"}', '🩸 Blood Drive Alert! A blood drive is scheduled in Oton on 2025-11-08 at 09:45. Your blood type is urgently needed! Please consider donating.', 'scheduled', '47600', '2025-11-03 13:46:01.99119+00', '2025-11-03 13:46:01.99119+00');
```

## 📋 Field-by-Field Verification

### ✅ Fields That Match

| Field | API Sends | Database Receives | Status |
|-------|-----------|-------------------|--------|
| `location` | String: 'Oton' | VARCHAR: 'Oton' | ✅ Match |
| `latitude` | Float: 10.6933 | DECIMAL(10,8): '10.69330000' | ✅ Match (Supabase converts) |
| `longitude` | Float: 122.4733 | DECIMAL(11,8): '122.47330000' | ✅ Match (Supabase converts) |
| `drive_date` | String: '2025-11-08' | DATE: '2025-11-08' | ✅ Match |
| `drive_time` | String: '09:45:00' | TIME: '09:45:00' | ✅ Match |
| `radius_km` | Integer: 15 | INTEGER: 15 | ✅ Match |
| `blood_types` | Array: ['A+','A-',...] | TEXT[]: '{"A+","A-",...}' | ✅ Match (Supabase converts) |
| `message_template` | String: '...' | TEXT: '...' | ✅ Match |
| `status` | String: 'scheduled' | VARCHAR: 'scheduled' | ✅ Match |
| `created_by` | Integer: 47600 | INTEGER: 47600 | ✅ Match |
| `created_at` | Not sent (DEFAULT) | TIMESTAMP: NOW() | ✅ Match (auto-generated) |
| `updated_at` | Not sent (DEFAULT) | TIMESTAMP: NOW() | ✅ Match (auto-generated) |
| `id` | Not sent (DEFAULT) | UUID: gen_random_uuid() | ✅ Match (auto-generated) |

## 🔄 Data Flow

### Step 1: Frontend Sends Data
```javascript
{
    location: "Oton",
    drive_date: "2025-11-08",
    drive_time: "09:45",
    latitude: 10.6933,
    longitude: 122.4733,
    radius_km: 15,
    blood_types: [],  // Empty = all types
    custom_message: "🩸 Blood Drive Alert!..."
}
```

### Step 2: API Processes Data
```php
// In broadcast-blood-drive.php (lines 124-149)
$latitude = floatval($input['latitude']);        // 10.6933
$longitude = floatval($input['longitude']);      // 122.4733
$radius_km = intval($input['radius_km']);       // 15
$blood_types = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

$blood_drive_data = [
    'location' => 'Oton',
    'latitude' => 10.6933,        // Float
    'longitude' => 122.4733,       // Float
    'drive_date' => '2025-11-08',
    'drive_time' => '09:45:00',
    'radius_km' => 15,             // Integer
    'blood_types' => ['A+', ...], // PHP Array
    'status' => 'scheduled',
    'message_template' => '...',
    'created_by' => 47600          // Integer (if session has user_id)
];
```

### Step 3: Supabase Converts & Stores
```json
{
    "location": "Oton",
    "latitude": "10.69330000",     // Converted to DECIMAL(10,8)
    "longitude": "122.47330000",    // Converted to DECIMAL(11,8)
    "drive_date": "2025-11-08",
    "drive_time": "09:45:00",
    "radius_km": 15,
    "blood_types": ["A+","A-","B+","B-","O+","O-","AB+","AB-"], // Converted to TEXT[]
    "status": "scheduled",
    "message_template": "...",
    "created_by": 47600,
    "id": "7ceece3b-0c6b-44e5-8bd9-61afd74661ae",  // Auto-generated UUID
    "created_at": "2025-11-03T13:46:01.99119+00:00",  // Auto-generated
    "updated_at": "2025-11-03T13:46:01.99119+00:00"    // Auto-generated
}
```

## ✅ Verification Checklist

- [x] **Location**: String sent, VARCHAR stored ✅
- [x] **Latitude**: Float sent, DECIMAL(10,8) stored ✅
- [x] **Longitude**: Float sent, DECIMAL(11,8) stored ✅
- [x] **Drive Date**: Date string sent, DATE stored ✅
- [x] **Drive Time**: Time string sent, TIME stored ✅
- [x] **Radius**: Integer sent, INTEGER stored ✅
- [x] **Blood Types**: PHP array sent, PostgreSQL TEXT[] stored ✅
- [x] **Message Template**: String sent, TEXT stored ✅
- [x] **Status**: 'scheduled' string sent, VARCHAR stored ✅
- [x] **Created By**: Integer sent (if session has user_id), INTEGER stored ✅
- [x] **ID**: Auto-generated UUID ✅
- [x] **Created At**: Auto-generated timestamp ✅
- [x] **Updated At**: Auto-generated timestamp ✅

## 🎯 Expected Result

When you schedule a blood drive, the system will:

1. ✅ Create record in `blood_drive_notifications` table
2. ✅ Generate UUID for `id`
3. ✅ Set `status` to 'scheduled'
4. ✅ Store all location and timing data
5. ✅ Convert blood_types array to PostgreSQL format
6. ✅ Auto-generate timestamps
7. ✅ Link to `donor_notifications` via `blood_drive_id`
8. ✅ Send notifications to donors in `push_subscriptions`

## 🧪 Test It

Run the test script to verify the format:
```
http://localhost/RED-CROSS-THESIS/Debugging and test scripts/test_blood_drive_insert.php
```

This will show you exactly what data format the API sends and how it matches your expected INSERT.

---

**Conclusion**: ✅ Yes, the system should work exactly as intended and create records matching your example INSERT statement!

