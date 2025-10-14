## 🩸 Donor Management Tab — Admin Guide

This document explains how the Donor Management tab works in the Admin Dashboard. It maps UI states to process steps and clarifies where data comes from and who updates it.

---

## 🔹 Purpose
- Provide a single place for admins to see a donor’s entire journey.
- Reflect progress across roles: Interviewer/Reviewer, Physician, Phlebotomist.
- Surface pending items, decisions, and final outcomes for quick triage.

---

## 🔹 Data Sources (Supabase)
All records are linked by UUID keys from `donor_form`/`donors_detail` (project uses `donor_id`). The tab aggregates data from:
- `donor_form` (identity + baseline details)
- `medical_history` (Interviewer/Reviewer + Physician decisions)
- `screening_form` (Initial screening vitals)
- `physical_examination` (Physician exam + eligibility)
- `blood_collection` (Collection status + notes)

These sources are used across dashboard modules and views (e.g., `public/Dashboards/module` and `src/views/forms`).

---

## 🔹 UI States
- **Empty State (New Donor)**: Fields show `-` when no record exists for that section. Action: edit/fill buttons for staff to start the step.
- **In‑Progress State**: Completed sections display values (status, vitals, decisions). Pending sections remain `-`. Some actions switch to view-only once a section is finalized.
- **Final State**: All sections filled and locked to view; shows final status across medical review, exam, and collection.

---

## 🔹 Role Responsibilities
- **Interviewer / Reviewer**: Review Medical History; set reviewer decision (e.g., pass, needs review).
- **Physician**: Confirm Medical History (approve/defer); complete Physical Examination and eligibility.
- **Phlebotomist**: Perform Blood Collection; record collection outcome and notes.

Admins see the entire journey; role users update only their sections.

---

## 🔹 Fields Summary per Section
- **Donor**: `full_name`, `age`, `gender`, `blood_type`
- **Medical History**: `status`, `reviewer_decision`, `physician_decision`
- **Initial Screening**: `body_weight`, `specific_gravity`, `blood_type`
- **Physical Examination**: `result`, `physician_decision`, `eligibility_status`
- **Blood Collection**: `status`, `note`

---

## 🔹 How Data Is Loaded
- The dashboard pulls the latest record per table for a given `donor_id`.
- Where `blood_collection` is tied via `screening_id`, the latest `screening_form` is looked up first, then the related collection.
- Existing helpers issue REST requests to Supabase and compose the final view state.

---

## 🔹 Status Logic (At a Glance)
- Medical History: complete if `status` and a final decision are present.
- Screening: complete if required vitals exist.
- Physical Exam: complete if `result` and `physician_decision` (and/or `eligibility_status`) are present.
- Blood Collection: complete if a `status` is present; `note` is optional.

Sections without required fields display `-` and remain actionable.

---

## 🔹 Common Admin Actions
- Open a donor profile to review all sections.
- Identify pending steps (sections with `-`).
- Nudge the responsible role to complete their section.
- Use history pages to audit previous entries when multiple records exist.

---

## 🔹 Error Handling & Edge Cases
- Missing data: display `-` rather than an error.
- Partial progress: show completed sections; keep pending sections editable.
- Multiple records: default to the latest by `created_at`/`submitted_at` per table.
- Orphan collections: if `screening_id` does not match the latest screening, use the most recent valid linkage where available.

---

## 🔹 Performance Notes
- Use minimal field selection for list views; fetch detailed records on demand.
- Prefer existing request helpers with retry/backoff on slow networks.
- Apply basic caching headers in API modules where safe.

---

## 🔹 Security & Permissions
- Admins: read-only across all sections; route follow-ups to staff.
- Staff roles: write access limited to their sections.
- Ensure server-side checks prevent cross‑role edits.

---

## 🔹 Related Docs
- `donor_process_flow.md` — End‑to‑end flow and unified view outline.
- Dashboard modules under `public/Dashboards/` for history and pending queues.

---

✅ The Donor Management tab provides a single, accurate snapshot of a donor’s journey while allowing each role to work independently on their part of the process.
