# 🩸 Donor Management Tab — Admin Guide

This document explains how the Donor Management tab works in the Admin Dashboard. It maps UI states to process steps, grouped **by role** (Interviewer, Physician, Phlebotomist), and clarifies where data comes from and who updates it.

---

## 🔹 Purpose
- Provide a single place for admins to see a donor’s entire journey.  
- Reflect progress across **roles**, matching how staff interact with the system.  
- Surface pending items, decisions, and final outcomes for quick triage.  

---

## 🔹 Data Sources (Supabase)
All records are linked by UUID keys from `donor_form`/`donors_detail` (project uses `donor_id`).  

The tab aggregates data from:
- `donor_form` (identity + baseline details)  
- `medical_history` (Interviewer + Physician decisions)  
- `screening_form` (Initial screening vitals)  
- `physical_examination` (Physician exam + eligibility)  
- `blood_collection` (Collection status + notes)  

---

## 🔹 Donor Information (Baseline)
**Source**: `donor_form`  

**Fields**:  
- `full_name`, `age`, `gender`, `blood_type`  
- `birthdate`, `civil_status`, `nationality`, `address`, `mobile_number`, `occupation`  

Admins see all values in read-only form.  

---

## 🔹 Interviewer / Reviewer
### Responsibilities
- Collect and review donor’s **Medical History**  
- Conduct **Initial Screening** (vitals + basic eligibility)  

### Data Sources
- `medical_history` (reviewer status + decision)  
- `screening_form` (weight, blood type, specific gravity, etc.)  

### UI States
- **Medical History**  
  - Empty → `-`  
  - Completed → `Completed`  
  - Decision → `Passed / Needs Review / Deferred`  

- **Initial Screening**  
  - Empty → `-`  
  - Completed → show vitals (`body_weight`, `specific_gravity`, `blood_type`)  
  - Decision → `Passed / Deferred`  

---

## 🔹 Physician
### Responsibilities
- Review Interviewer’s **Medical History** and approve/deny  
- Perform **Physical Examination** and determine eligibility  

### Data Sources
- `medical_history` (physician decision)  
- `physical_examination` (exam + eligibility status)  

### UI States
- **Medical History Review**  
  - Empty → `-`  
  - Approved / Deferred → show decision  

- **Physical Examination**  
  - Empty → `-`  
  - Completed → show `result`, `eligibility_status`  
  - Decision → `Approved / Deferred`  

---

## 🔹 Phlebotomist
### Responsibilities
- Perform **Blood Collection**  
- Record outcome and notes  

### Data Source
- `blood_collection`  

### UI States
- **Blood Collection Status**  
  - Empty → `-`  
  - Completed → `Successful / Unsuccessful + note`  

---

## 🔹 Final Donor Status
A donor’s journey is considered **complete** when:  
- Medical History (Interviewer + Physician) = `Approved`  
- Initial Screening = `Passed`  
- Physical Exam = `Approved`  
- Blood Collection = `Successful`  

Admins see all roles’ updates in one view; staff users only edit their own sections.  

---

## 🔹 Error Handling & Edge Cases
- **Missing data** → display `-` (no errors shown).  
- **Partial progress** → completed sections display values; pending remain editable.  
- **Multiple records** → latest by `created_at`/`submitted_at` is used.  
- **Orphan collections** → if `screening_id` mismatch, fallback to latest valid linkage.  

---

## 🔹 Performance Notes
- Use minimal field selection for list views; fetch details on profile open.  
- Existing helpers issue REST requests with retry/backoff.  
- Basic caching headers applied where safe.  

---

## 🔹 Security & Permissions
- **Admins**: read-only across all sections; route follow-ups to staff.  
- **Staff roles**: write access limited to their assigned sections.  
- Enforced via server-side checks to prevent cross-role edits.  

---

## 🔹 Related Docs
- `donor_process_flow.md` — End-to-end flow and unified view outline  
- Dashboard modules under `public/Dashboards/` for history and pending queues  

---

✅ The Donor Management tab now mirrors the **role-based flow** used on the staff side, giving admins a clear, grouped view of a donor’s progress.  
