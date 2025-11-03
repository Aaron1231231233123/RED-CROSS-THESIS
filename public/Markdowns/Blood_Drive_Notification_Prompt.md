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

**But here’s what most people miss...**  
Most dev teams only test notification *delivery* — not *conversion*.  
Add a later metric: how many donors actually opened the notification and confirmed attendance.  
That’s your true KPI, and Cursor can help you auto-collect that in future iterations.
