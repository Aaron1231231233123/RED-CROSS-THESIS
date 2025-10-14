
# 🩸 Donor Process Flow (Admin Side)

This document describes the **donor process flow** implementation for the **Admin Dashboard**, using **Supabase** as the database backend.  

The system integrates multiple staff roles (Interviewer, Physician, Phlebotomist) into a **single unified admin view**.

---

## 🔹 Process Flow by Staff Role

### 1. **Interviewer / Reviewer**
- **Medical History Approval**
- **Initial Screening** (Body Weight, Specific Gravity, Blood Type)  
✅ Output: *Completed / Passed*

---

### 2. **Physician**
- **Confirm Medical History** (final decision to approve or defer)
- **Physical Examination**  
✅ Output: *Approved / Deferred*

---

### 3. **Phlebotomist**
- **Blood Collection**
  - Collection Status (Successful / Failed)
  - Phlebotomist Notes  
✅ Output: *Successful / Unsuccessful*

---

## 🔹 Database Table Mapping

The donor process uses multiple Supabase tables, all linked by **UUID (`donor_id`)** from the `donors_detail` table.

| **Process Step**       | **Supabase Table**        | **Key Fields** |
|-------------------------|---------------------------|----------------|
| Donor Information       | `donors_detail`          | `uuid`, `full_name`, `age`, `gender`, `blood_type` |
| Medical History         | `medical_history`        | `status`, `reviewer_decision`, `physician_decision` |
| Initial Screening       | `screening_form`         | `body_weight`, `specific_gravity`, `blood_type` |
| Physical Examination    | `physical_examination`   | `result`, `physician_decision`, `eligibility_status` |
| Blood Collection        | `blood_collection`       | `status`, `note` |

---

## 🔹 Unified SQL Query (Admin Dashboard)

This query retrieves a **donor’s full process flow** for display in the Admin Dashboard:

```sql
SELECT d.full_name, d.age, d.gender, d.uuid AS donor_id, d.blood_type,
       mh.status AS medical_history_status,
       mh.reviewer_decision, mh.physician_decision,
       sf.body_weight, sf.specific_gravity, sf.blood_type AS screening_blood_type,
       pe.result AS physical_exam_result, pe.physician_decision AS exam_decision,
       bc.status AS blood_collection_status, bc.note AS phlebotomist_note
FROM donors_detail d
LEFT JOIN medical_history mh ON d.uuid = mh.donor_id
LEFT JOIN screening_form sf ON d.uuid = sf.donor_id
LEFT JOIN physical_examination pe ON d.uuid = pe.donor_id
LEFT JOIN blood_collection bc ON d.uuid = bc.donor_id
WHERE d.uuid = :donor_id;
```

---

## 🔹 Admin Dashboard UI Flow

### **1. Empty State (New Donor)**
- All fields are `-`
- Action = ✏️ (edit/fill)

---

### **2. In-Progress State**
- Completed sections display status (e.g., `Completed`, `Passed`)
- Pending sections still show `-`
- Actions switch between **edit** and **view**

---

### **3. Final State**
- All sections filled with data from Supabase
- Actions = 👁 (view-only)

---

✅ This approach ensures the **admin sees the entire donor journey in one view**, while **staff roles update their respective sections independently**.
