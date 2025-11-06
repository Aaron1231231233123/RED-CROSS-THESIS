# 🩸 Blood Drive Notification Flow – Cursor Prompt

## Context
We are building a **Blood Drive Scheduling and Notification System** integrated into a **PWA (Progressive Web App)** for donors.

### Current Setup
- Donors log in via PWA (mobile-first design).  
- The app supports **Push Notifications** (users can toggle a push slider).  
- Backend: PHP + Supabase (for donor and event data).  
- Current admin feedback message:  
  > “Blood Drive Scheduled! Location: Selected location | Date: Selected date | Time: Selected time | Donors Found: 54 | Push Subscriptions: 0 | Notifications Sent: 0”

---

## 🧠 Prompt for Cursor

````markdown
You are an expert full-stack engineer and UX strategist.  
We are building a **Blood Drive Scheduling and Notification System** integrated into a **PWA (Progressive Web App)** for donors.

Current setup:
- Donors log in via PWA (mobile-first design).  
- The app supports **Push Notifications** (users can toggle a push slider).  
- Backend: PHP + Supabase (for donor and event data).  
- We currently show this message after scheduling:  
  “Blood Drive Scheduled! Location: Selected location | Date: Selected date | Time: Selected time | Donors Found: 54 | Push Subscriptions: 0 | Notifications Sent: 0”

### Task:
Create a **notification announcement flow** when a blood drive is scheduled.  
The flow should:
1. Send a **push notification** to all donors who have opted in.  
2. If a donor is *not subscribed* to push notifications, **fallback to email**.  
3. Avoid duplicates (no email if push already delivered).  
4. Include smart messaging logic:
   - Push message = short, action-driven (e.g., “Blood drive near you! Tap to confirm your slot.”)
   - Email message = informative, contextual (e.g., includes location, time, and link to confirm attendance).
5. Return a clear summary log (total donors found, push sent, email sent, and skipped due to no contact).

### Output Format:
- PHP + JavaScript hybrid code (PHP backend, JS push integration).  
- Include function names like `sendPushNotification()` and `sendEmailNotification()`.  
- Add inline comments explaining logic and future scaling potential.  
- Add a section on how to later extend this to geo-targeted notifications.

### Bonus:
At the end, summarize:
- How the flow improves donor engagement.
- How to avoid push fatigue and email redundancy.
````

---

## 💡 Optional Add-on Prompt (for refinement)
If you want Cursor to *improve existing code*, append this after pasting the above:

````markdown
Now, refactor our existing blood drive scheduling code to integrate the new notification flow seamlessly.  
Make sure:
- It doesn’t break current response structure (`respond()` function).  
- It keeps database consistency with `notifications_sent` and `push_subscriptions` fields.  
- It logs skipped donors and reasons in the server console or a Supabase table `notification_logs`.  
````

---

## Strategic Notes

**But here's what most people miss...**  
Most dev teams only test notification *delivery* — not *conversion*.  
Add a later metric: how many donors actually opened the notification and confirmed attendance.  
That's your true KPI, and Cursor can help you auto-collect that in future iterations.

---

## 📋 TODO List - Blood Drive Scheduling & Notification Fix

### Phase 1: Database Schema Verification ✅
- [x] Review and verify database schema for blood_drive_notifications, push_subscriptions, donor_notifications, and donor_form tables
- [x] Create notification_logs table structure for tracking all notification attempts

### Phase 2: Fix Core Functionality ✅
- [x] Fix donor query in broadcast-blood-drive.php to correctly fetch eligible donors with proper error handling
- [x] Fix push subscription query to properly retrieve donor push subscriptions without errors
- [x] Fix issues causing "Push Subscriptions: 0" and "Notifications Sent: 0"

### Phase 3: Email Notification Implementation ✅
- [x] Create email notification function (sendEmailNotification) as fallback for donors without push subscriptions
- [x] Implement email template with blood drive details (location, date, time, RSVP link)
- [x] Ensure email functionality uses donor's email or mobile contact information

### Phase 4: Notification Flow Implementation ✅
- [x] Implement notification flow: send push to subscribed donors, then email to non-subscribed donors (avoid duplicates)
- [x] Update notification payload with short, action-driven push messages
- [x] Create detailed email messages with full context
- [x] Ensure no duplicate notifications (push OR email, not both)

### Phase 5: Logging & Reporting ✅
- [x] Create notification_logs table structure and logging functionality for tracking all notification attempts
- [x] Update response to include comprehensive summary: total donors found, push sent, email sent, skipped
- [x] Log reasons for skipped donors (no push subscription, no email, etc.)

### Phase 6: Frontend Updates ✅
- [x] Update frontend dashboard to display improved notification summary with all metrics
- [x] Show breakdown: Donors Found, Push Subscriptions, Push Sent, Email Sent, Failed, Skipped

### Phase 7: Testing & Validation 🔄
- [ ] Test the complete flow end-to-end
- [ ] Verify push notifications are sent correctly
- [ ] Verify email fallback works for non-subscribed donors
- [ ] Verify no duplicates are sent
- [ ] Fix any remaining issues

### Implementation Status
**Current Status:** Implementation Complete - Ready for Testing
**Last Updated:** 2025-01-XX

### Files Created/Modified:
1. ✅ `create_notification_logs_table.sql` - Database schema for notification logs
2. ✅ `assets/php_func/email_sender.php` - Email notification class
3. ✅ `public/api/broadcast-blood-drive.php` - Complete notification flow implementation
4. ✅ `public/Dashboards/dashboard-Inventory-System.php` - Updated frontend display

### Key Features Implemented:
- ✅ Push notifications to subscribed donors with short, action-driven messages
- ✅ Email fallback for donors without push subscriptions
- ✅ Duplicate prevention (no email if push subscription exists)
- ✅ Comprehensive logging to notification_logs table
- ✅ Detailed summary with metrics: total found, push sent, email sent, failed, skipped
- ✅ Error handling and graceful degradation

### Next Steps - Database Setup:
1. **Run the SQL file in Supabase**: Execute `create_notification_logs_table.sql` in your Supabase SQL Editor to create the notification_logs table
2. **Configure Email Settings**: Update email configuration in `assets/php_func/email_sender.php` (fromEmail, fromName, replyTo) to match your organization's email settings
3. **Test the Flow**: Schedule a test blood drive and verify that:
   - Push notifications are sent to subscribed donors
   - Email notifications are sent to non-subscribed donors (with email addresses)
   - No duplicate notifications are sent
   - Summary statistics are displayed correctly

### Notes:
- The system gracefully handles missing tables (notification_logs, donor_notifications) and will continue to function even if they don't exist yet
- Email sending uses PHP's `mail()` function by default. For production, consider using a service like PHPMailer, SendGrid, or AWS SES
- Push notification encryption is simplified in the current implementation - for production, ensure proper Web Push encryption is implemented
