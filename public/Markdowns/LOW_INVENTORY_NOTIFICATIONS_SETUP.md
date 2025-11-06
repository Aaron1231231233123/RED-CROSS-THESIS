# Low Inventory Auto-Notifications Setup Guide

This system automatically notifies donors via PWA push notifications (primary) and email (fallback) when blood inventory drops to 25 units or below for any blood type.

## Features

- ✅ Automatic monitoring of blood inventory levels by blood type
- ✅ PWA push notifications (primary method)
- ✅ Email fallback for donors without push subscriptions
- ✅ Rate limiting to prevent spam (default: once per day, configurable up to 45 days)
- ✅ Only notifies donors with matching blood types
- ✅ Tracks all notification attempts in database

## Setup Steps

### 1. Create Database Table

Run the SQL script in your Supabase SQL Editor:

```sql
-- File: create_low_inventory_notifications_table.sql
```

This creates the `low_inventory_notifications` table to track notifications and prevent duplicates.

### 2. Verify API Endpoint

The API endpoint is located at:
- **File**: `public/api/auto-notify-low-inventory.php`
- **URL**: `https://your-domain.com/public/api/auto-notify-low-inventory.php`

### 3. Configure Threshold and Rate Limiting

The system has default settings that can be customized:

- **Threshold**: 25 units (default)
- **Rate Limit**: 1 day (default, configurable from 1 to 45 days)

You can customize these by sending POST request with JSON:

```json
{
  "threshold": 25,
  "rate_limit_days": 1
}
```

Or modify the default values in `auto-notify-low-inventory.php`:

```php
$threshold = isset($input['threshold']) ? intval($input['threshold']) : 25;
$rate_limit_days = isset($input['rate_limit_days']) ? intval($input['rate_limit_days']) : 1;
```

## Scheduling Auto-Notifications

### Option 1: Cron Job (Recommended for Linux/Unix)

Add to your crontab (runs once per day at 9 AM):

```bash
# Edit crontab
crontab -e

# Add this line (adjust path and domain as needed)
0 9 * * * curl -X POST https://your-domain.com/public/api/auto-notify-low-inventory.php -H "Content-Type: application/json" -d '{}' >> /var/log/low-inventory-notifications.log 2>&1
```

### Option 2: Windows Task Scheduler

1. Open Task Scheduler
2. Create Basic Task
3. Set trigger (e.g., Daily at 9:00 AM)
4. Action: Start a program
5. Program: `curl.exe`
6. Arguments: `-X POST https://your-domain.com/public/api/auto-notify-low-inventory.php -H "Content-Type: application/json" -d "{}"`
7. Save and test

### Option 3: Manual Trigger (Testing)

You can test the endpoint manually:

```bash
# Using curl
curl -X POST https://your-domain.com/public/api/auto-notify-low-inventory.php \
  -H "Content-Type: application/json" \
  -d '{"threshold": 25, "rate_limit_days": 1}'

# Using PowerShell (Windows)
Invoke-RestMethod -Uri "https://your-domain.com/public/api/auto-notify-low-inventory.php" `
  -Method Post `
  -ContentType "application/json" `
  -Body '{"threshold": 25, "rate_limit_days": 1}'
```

### Option 4: Database Trigger (Advanced)

You can create a database trigger that calls the API when inventory changes, but this requires:
- pg_net extension in Supabase
- Edge functions or webhook setup

Example trigger (PostgreSQL):

```sql
-- This is a conceptual example - actual implementation depends on your setup
CREATE OR REPLACE FUNCTION trigger_low_inventory_check()
RETURNS TRIGGER AS $$
BEGIN
    -- When blood_bank_units changes, check if we need to notify
    -- This would call a Supabase Edge Function or webhook
    PERFORM net.http_post(
        url := 'https://your-domain.com/public/api/auto-notify-low-inventory.php',
        headers := '{"Content-Type": "application/json"}'::jsonb,
        body := '{}'::jsonb
    );
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
```

## How It Works

1. **Inventory Check**: Queries `blood_bank_units` table to count valid units by blood type
2. **Threshold Detection**: Identifies blood types with ≤ 25 units (or configured threshold)
3. **Donor Matching**: Finds donors in `donor_form` with matching blood types from `screening_form`
4. **Rate Limiting**: Checks `low_inventory_notifications` table to ensure donor wasn't notified recently
5. **Push Notifications**: Sends PWA push notifications to subscribed donors
6. **Email Fallback**: Sends email to donors without push subscriptions
7. **Logging**: Records all notification attempts in `low_inventory_notifications` table

## Response Format

Successful response:

```json
{
  "success": true,
  "message": "Low inventory notifications processed",
  "threshold": 25,
  "rate_limit_days": 1,
  "inventory": {
    "A+": 15,
    "A-": 30,
    "B+": 20,
    "B-": 28,
    "O+": 10,
    "O-": 35,
    "AB+": 8,
    "AB-": 22
  },
  "low_inventory_types": ["A+", "B+", "O+", "AB+"],
  "summary": {
    "push_sent": 45,
    "push_failed": 2,
    "push_skipped": 10,
    "email_sent": 23,
    "email_failed": 1,
    "email_skipped": 5,
    "total_notified": 68
  }
}
```

## Rate Limiting Logic

The system prevents spam by checking the `low_inventory_notifications` table:

- Checks if donor was notified for the same blood type within the rate limit period
- Default: 1 day (24 hours)
- Configurable: 1 to 45 days
- Each blood type is tracked separately (donor can receive notification for different blood types)

## Notification Content

### Push Notification
- **Title**: "🩸 Low Blood Inventory Alert"
- **Body**: "Only X units of [Blood Type] blood remaining. Your donation is urgently needed!"
- **Data**: Includes blood type, units available, and redirect URL

### Email Notification
- Uses the existing `EmailSender` class
- Adapts the blood drive email template for low inventory alerts
- Includes urgent messaging about low inventory levels

## Troubleshooting

### No Notifications Sent

1. Check if inventory is actually low:
   ```bash
   curl https://your-domain.com/public/api/auto-notify-low-inventory.php
   ```

2. Check database logs in `low_inventory_notifications` table

3. Verify donors exist with matching blood types:
   ```sql
   SELECT COUNT(*) FROM screening_form 
   WHERE blood_type = 'O+' AND blood_type IS NOT NULL;
   ```

### Push Notifications Not Working

1. Verify VAPID keys are configured in `assets/php_func/vapid_config.php`
2. Check that donors have push subscriptions in `push_subscriptions` table
3. Review `web_push_sender.php` for errors

### Email Notifications Not Working

1. Verify email configuration in `EmailSender` class
2. Check PHP `mail()` function is working
3. Consider using PHPMailer or SMTP service for production

## Security Considerations

- API endpoint should be protected or use API key authentication for production
- Rate limiting prevents abuse
- Only sends notifications to registered donors in `donor_form` table
- Validates email addresses before sending

## Future Enhancements

- [ ] SMS notifications as additional fallback
- [ ] Dashboard widget showing low inventory alerts
- [ ] Customizable notification messages per blood type
- [ ] Admin interface to manually trigger notifications
- [ ] Analytics dashboard for notification effectiveness



