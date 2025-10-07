Title: Admin Dashboard Inventory System Optimization Specification (admin)

Audience: Engineering (Admin-only dashboard)  
Scope: Optimize and harden public/Dashboards/dashboard-Inventory-System-list-of-donations.php, without changing functional workflow (Interviewer → Physician → Phlebotomist)

Objectives
- Reduce file size and complexity by extracting JS/CSS into modules.
- Stabilize modal lifecycle/backdrops; prevent UI lock-ups.
- Improve performance: minimize reflows, duplicate listeners, unnecessary network calls.
- Increase maintainability with clear module boundaries and typed, documented APIs.
- Ensure admin-only usage for new assets/components.

Key Problems Observed (Current State)
- One very large PHP file mixes markup, CSS, and JS (difficult to maintain).
- Multiple backdrop “cleanup” routines fight Bootstrap lifecycles → stuck backdrops.
- Repetitive DOMContentLoaded blocks and event listener duplication.
- Long HTML template strings inline; hard to test and reuse.
- Conditional scripts loaded unconditionally, causing console errors when DOM isn’t present.

High-level Plan
1) Split responsibilities into clearly scoped modules (JS/CSS/partials).
2) Normalize modal lifecycle (open/close) and rely on Bootstrap; remove global tampering.
3) Centralize donor details rendering and data fetching with caching and error handling.
4) Use conditional/dynamic loading for optional modules and styles.
5) Keep the PHP file as a view shell that includes partials and loads assets.

Deliverables and File Layout (admin)
Create the following files. These names are suggestions; keep “admin” context.

1. JS (assets/js/)
- donor-details-admin.js
  - Exports: openDonorDetails(donorId, eligibilityId), openLegacyDonorModal(...)
  - Responsibilities: show/hide donor detail modals, render sections (Interviewer, Physician, Phlebotomist), call API, cache data.
  - Internals: small render* functions per section, skeleton loader builder, status utils.

- workflow-admin.js
  - Exports: openInterviewerScreening(donor), openPhysicianMedicalReview(donor), openPhysicianPhysicalExam(ctx), openPhlebotomistCollection(ctx)
  - Responsibilities: entry points for phases, lightweight guards, navigation fallbacks.

- bootstrap-utils-admin.js
  - Exports: showModal(id, opts), hideModal(id), ensureBackdropCleanup(ids)
  - Responsibilities: one canonical place for Bootstrap modal lifecycle, backdrop/body cleanup post-hide.

- net-admin.js
  - Exports: apiGet(url, { signal, timeoutMs }), withTimeout(promise, ms), buildUrl(base, params)
  - Responsibilities: fetch wrapper, timeout/abort, unified error mapping.

- events-admin.js
  - Initialization: attach delegated listeners for .view-donor, .edit-donor, .donor-row.
  - Uses donor-details-admin to open appropriate modal; debounces if needed.

2. CSS (assets/css/)
- donor-details-admin.css
  - Scoped rules for #donorDetailsModal, #donorModal, role tables, skeletons.
  - Remove global “hide spinners” rules; scope to modals where needed.

- modals-admin.css
  - z-index layering for admin modals only.
  - Backdrop color/opacity tokens.

3. PHP partials (src/views/admin-modals/)
- donor-details-modal-admin.php
  - Contains only the modern role‑sectioned modal HTML shell (#donorDetailsModal + body container).

- donor-details-legacy-modal-admin.php
  - Contains only the legacy modal HTML shell (#donorModal + #donorDetails container).

4. Documentation
- This document (DASHBOARD_INVENTORY_SYSTEM_OPTIMIZATION_admin.md).

Implementation Details

Step 1: Extract CSS
- Move inline CSS pertaining to donor modals and role sections to assets/css/donor-details-admin.css.
- Keep global Bootstrap layering minimal; scope spinner visibility to #donorDetailsModal and #donorModal.
- Remove/avoid global rules that suppress .spinner-border across all modals.

Step 2: Extract JS – donor-details-admin.js
- Public API:
  - openDonorDetails({ donor_id, eligibility_id }): shows #donorDetailsModal with skeleton, fetches comprehensive_donor_details_api.php (fallback to donor_details_api.php), renders header + 3 sections.
  - openLegacyDonorModal(donorId, eligibilityId): shows #donorModal, sets loading indicator, fetches donor_details_api.php, renders compact layout.
- Internals:
  - renderHeader(donor)
  - renderInterviewer(eligibility)
  - renderPhysician(eligibility)
  - renderPhlebotomist(eligibility)
  - buildSkeleton(state)
  - cache: Map keyed by donorId|eligibilityId with TTL (e.g., 60s) to avoid re-fetching.
  - Robust error UI: alerts in-modal with retry link.

Step 3: Extract JS – workflow-admin.js
- Keep the phase openers (Interviewer/Physician/Phlebotomist) here so donor-details stays presentational.
- Provide fallbacks to page navigation when a JS modal is not available.

Step 4: Extract JS – bootstrap-utils-admin.js
- showModal(id, opts): new bootstrap.Modal + show()
- hideModal(id): getInstance + hide()
- ensureBackdropCleanup(ids): attach hidden/ hide listeners; remove lingering backdrops; clear body.modal-open and overflow/padding.
- Use this utility in donor-details-admin.js and anywhere else that opens modals.

Step 5: Extract JS – net-admin.js
- apiGet(url, { timeoutMs = 10000 }): AbortController; reject on timeout; return JSON; clean error messages.
- buildUrl(base, params): safely adds query params.

Step 6: Extract JS – events-admin.js
- On DOMContentLoaded, attach one delegated click handler on the donations table container for:
  - .view-donor and .edit-donor buttons
  - .donor-row clicks
- Calls donor-details-admin.openDonorDetails or openLegacyDonorModal depending on desired UX.
- Debounce rapid clicks and ignore when a modal is already open, if required.

Step 7: Update dashboard-Inventory-System-list-of-donations.php
- Replace inline CSS/JS with:
  - Includes for src/views/admin-modals/donor-details-modal-admin.php and donor-details-legacy-modal-admin.php
  - <link> donor-details-admin.css and modals-admin.css (admin-only)
  - <script> bootstrap-utils-admin.js, net-admin.js, donor-details-admin.js, workflow-admin.js, events-admin.js (conditionally load some based on DOM presence)
- Remove any remaining interval-based backdrop tampering; rely on Bootstrap + ensureBackdropCleanup.

Step 8: Conditional Loading & Guards (admin-only)
- Only load medical-history-approval.js when its modals exist.
- Only load phlebotomist_blood_collection_details_modal.js when its modal exists.
- Wrap module inits with DOM existence checks; exit early if not present.

Step 9: Performance Enhancements
- Debounce search input (already present); ensure it filters only current page server-side.
- Virtualize rows if page size > 100 (optional future step).
- Cache donor details for quick reopen; invalidate cache on known mutations (approval/decline events).
- Avoid repeated innerHTML concatenations; build DOM via small templates or DocumentFragment.

Step 10: Error Handling & Telemetry
- Centralize error display to an in-modal alert UI.
- Log key failures behind a debug flag (window.__DEBUG) to avoid noisy prod logs.
- Use standardized messages for 4xx/5xx and network timeouts.

Testing & QA Checklist
- Modals:
  - Open/close both role‑sectioned and legacy modals; backdrops disappear; page remains interactive.
  - Loading indicator visible immediately; skeleton replaced with content.
- Flow:
  - Interviewer → Physician → Phlebotomist: all entry points work and respect status gates.
- Network:
  - API fallback from comprehensive_donor_details_api.php to donor_details_api.php works.
  - Error UI shown on non-200 responses.
- Performance:
  - No runaway intervals; no continuous backdrop tampering.
  - No duplicate listeners on repeated navigation.

Rollout Plan
1) Land CSS/JS modules and partials without removing old inline code; toggle usage via a flag on the page.
2) Switch the page to use modules; keep legacy modal available for a sprint as fallback.
3) Remove old inline CSS/JS once stable.

Non‑Breaking Implementation Guarantees (admin)
- Feature flag/toggle: add a simple runtime toggle (e.g., window.ADMIN_USE_MODULES = true) so we can fall back to legacy inline logic instantly without redeploy.
- Shadow mode: initially load new modules but keep legacy handlers wired; open the new modal only from a hidden test button or debug flag to validate parity.
- Safe fallbacks: on any error opening the role‑sectioned modal, auto‑fallback to legacy donor modal; on API error, render in‑modal error with a “Use legacy view” link that calls the legacy opener.
- Scoped lifecycle: ensure only donor detail modals are bound to backdrop/body cleanup; do not attach global interval/timers.
- Conditional loading: only load optional scripts when their DOM containers exist; guards return early on missing DOM to avoid runtime errors.
- Idempotent listeners: use delegated, single attachment for table actions to avoid duplicate handlers after partial reloads.
- Cache invalidation: invalidate donor detail cache on known state‑changing events (approve/decline) to prevent stale UI.
- No global CSS suppression: avoid global rules that hide .spinner-border; scope to specific modals only.
- Exit criteria: legacy modal remains available until the new role‑sectioned modal has parity sign‑off.

Backwards Compatibility & Failure Modes (admin)
- If Bootstrap modals fail to instantiate, open the plain legacy modal; if that fails, navigate to existing standalone pages for each phase (screening, physical, collection).
- If comprehensive API fails, fallback to donor_details_api.php automatically; show concise error details for diagnosis.
- If module bundle load fails (network), fall back to inline legacy behavior via the feature flag.

Monitoring & Verification
- Add a lightweight debug flag (window.__DEBUG) to log which path (new vs legacy) was used and any fallbacks taken.
- QA checklist (must-pass before removing legacy):
  - Open/close modals → backdrops removed, dashboard interactive.
  - Role sections render consistently with the legacy view (labels, actions, status badges).
  - Interviewer → Physician → Phlebotomist entry points work identically.
  - Error UIs shown for network failures with functional fallback links.
  - No duplicate listeners; no console errors on pages without optional modals.

Workflow Integrity Guarantees (admin)
- Invariants (must never change):
  - Interviewer actions: Edit Medical History (pending), Edit Initial Screening (MH done, screening pending), View Interviewer Details (both done).
  - Physician actions: Review/View Medical History (gated by interviewer completion), Open Physical Examination; approve/decline MH remains available per status.
  - Phlebotomist actions: Edit Blood Collection (pending), View Phlebotomist Details (completed); gated by physician completion.
  - Status gating logic preserved exactly as in legacy (no early access past locks).
  - API fallback: comprehensive_donor_details_api.php → donor_details_api.php (unchanged URLs and semantics).
  - Legacy donor modal remains callable for parity/fallback during rollout.

- Entry point mapping (old ⇄ new):
  - Interviewer
    - openInterviewerScreening(donor) ⇄ workflow-admin.openInterviewerScreening
  - Physician
    - openPhysicianMedicalReview(donor) ⇄ workflow-admin.openPhysicianMedicalReview
    - openPhysicianPhysicalExam(ctx) ⇄ workflow-admin.openPhysicianPhysicalExam
  - Phlebotomist
    - openPhlebotomistCollection(ctx) ⇄ workflow-admin.openPhlebotomistCollection

- Role flow validation (test matrix):
  1) New donor (MH pending, screening pending)
     - Interviewer: shows Edit MH; screening shows Edit Initial Screening only after MH done
     - Physician: locked until interviewer completed
     - Phlebotomist: locked until physician completed
  2) Interviewer completed, Physician pending
     - Interviewer: View interviewer details
     - Physician: Review MH, Open PE; approve MH path functions; decline path shows reason flow
     - Phlebotomist: locked
  3) Physician completed, Collection pending
     - Phlebotomist: Edit Collection visible; on success shows View Details
  4) Completed/Approved
     - All sections show View buttons; no Edit actions available

- E2E checks per role action:
  - Buttons exist and are enabled/disabled per status
  - Click opens correct modal/page; no console errors; close returns control (no stuck backdrop)
  - Approve/Decline events update state and invalidate donor detail cache; UI reflects changes on reopen

Security & Access Control (admin)
- Serve these assets only within authenticated admin dashboards/routes.
- Place partials under src/views/admin-modals/ and reference from admin pages only.
- If using route-based guards, ensure no public page includes admin modules.

Admin-only Specification (Required)
- All newly created files listed here are intended exclusively for admin usage. Ensure they are only referenced from admin dashboard views and not exposed/linked in public or staff-only pages. If a build bundler is introduced, configure chunk splitting so admin bundles are not loaded outside admin routes.


