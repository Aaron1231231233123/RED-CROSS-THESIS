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

Contracts & Sequencing (parity-preserving with dashboard-Inventory-System-list-of-donations.php)
- Source of truth: preserve the exact legacy flow order and status gates currently implemented in `public/Dashboards/dashboard-Inventory-System-list-of-donations.php`.
- Auto‑chaining: by default, each opener progresses to the next step on successful completion, matching the current UX. Provide an optional flag to disable chaining when needed.
- Hard gates: never allow advancing to a step that is locked by status.
- Safe fallbacks: if a required modal/DOM container is missing, navigate to the legacy page/route for the same action.

Function contracts
- openInterviewerScreening(donor, opts?)
  - Purpose: Enforce Interviewer flow: Medical History (MH) first, then Initial Screening.
  - Behavior:
    - If MH is pending → open MH form (modal or legacy page). On successful submit and if opts.chain !== false → open Initial Screening.
    - If MH is complete and Screening is pending → open Initial Screening.
    - If both MH and Screening complete → open read‑only Interviewer details view.
    - If required UI is unavailable → fallback to legacy routes.
  - Side effects: on successful submit of MH/Screening, emit events to invalidate donor detail cache.

- openPhysicianMedicalReview(donor, opts?)
  - Purpose: Open Physician Medical Review of MH with Approve/Decline controls.
  - Precondition: Interviewer (MH + Screening) completed. If not, show gated message and do nothing else.
  - Behavior:
    - Open MH Review with Approve/Decline (modal or legacy page).
    - On Approve success and if opts.chain !== false → automatically proceed to Physical Examination via openPhysicianPhysicalExam.
    - On Decline → collect reason, update status, stop chain.
  - Side effects: invalidate donor detail cache after Approve/Decline.

- openPhysicianPhysicalExam(ctx, opts?)
  - Purpose: Perform Physical Examination after MH approval.
  - Precondition: Physician MH approved. If not, show gated message and exit.
  - Behavior: Open Physical Examination (modal or legacy). On successful completion and if opts.chain !== false → enable/offer transition to Phlebotomist Collection.
  - Side effects: invalidate donor detail cache after completion.

- openPhlebotomistCollection(ctx)
  - Purpose: Perform blood collection after Physician completion.
  - Precondition: Physical Examination complete. If not, block and show gated message.
  - Behavior: Open Blood Collection (modal or legacy). On success, show View Details when reopened.
  - Side effects: invalidate donor detail cache after completion.

Recommended options shape
```javascript
// Proposed, optional; defaults shown reflect legacy-parity behavior
const defaults = {
  chain: true,          // auto-advance to the next step on success
  debug: window.__DEBUG // log path & fallbacks for verification
};
```

Example sequencing (illustrative only)
```javascript
async function openInterviewerScreening(donor, opts = {}) {
  const options = { chain: true, ...opts };
  if (!donor.interviewer.medicalHistory.completed) {
    const mhOk = await openMedicalHistoryForm(donor); // modal or legacy fallback
    if (!mhOk) return; // user canceled or error
  }
  if (!donor.interviewer.screening.completed) {
    const screeningOk = await openInitialScreeningForm(donor);
    if (!screeningOk) return;
  }
  if (!options.chain) return; // stop here if chaining disabled
  // No automatic progression beyond Interviewer phase
}

async function openPhysicianMedicalReview(donor, opts = {}) {
  const options = { chain: true, ...opts };
  if (!donor.interviewer.completed) return showGate();
  const result = await openMHReviewApproveDecline(donor); // returns { approved: boolean }
  if (!result) return; // canceled
  if (result.approved && options.chain) {
    await openPhysicianPhysicalExam(donor, options);
  }
}

async function openPhysicianPhysicalExam(donor, opts = {}) {
  const options = { chain: true, ...opts };
  if (!donor.physician.mhApproved) return showGate();
  const peOk = await openPhysicalExam(donor);
  if (!peOk || !options.chain) return;
  // Offer transition to Phlebotomist Collection (explicit user action to proceed)
}

async function openPhlebotomistCollection(donor) {
  if (!donor.physician.physicalExam.completed) return showGate();
  await openBloodCollection(donor);
}
```

Notes
- Chaining mirrors the current behavior: Interviewer chains MH → Screening; Physician chains MH Review (Approve) → Physical Exam; Phlebotomist is entered explicitly after Physician completes.
- Cancel paths never auto‑advance.
- All open* functions must gracefully fall back to legacy pages when the modern modal shell is absent.
- All successful mutations trigger cache invalidation so donor details re‑fetch reflects latest state.

Legacy Fallback Routes Mapping (Exact URLs)
The following URLs are used today in `public/Dashboards/dashboard-Inventory-System-list-of-donations.php`. `workflow-admin.js` must prefer the new modals when present and fall back to these routes when not.

- Interviewer — Medical History (MH)
  - Primary modal partials (content fetch):
    - `../../src/views/forms/medical-history-modal-content-admin.php?donor_id={DONOR_ID}`
    - `../../src/views/forms/medical-history-modal.php?donor_id={DONOR_ID}` (alt)
    - `../../src/views/forms/medical-history-physical-modal-content.php?donor_id={DONOR_ID}` (contextual)
  - Page fallback (review/edit):
    - `{baseUrl}medical-history.php?donor_id={DONOR_ID}` (e.g., set via `baseUrl` in the page)
  - APIs used by legacy flows (must remain compatible):
    - `../../assets/php_func/update_medical_history.php` (submit/update)
    - `../../assets/php_func/fetch_medical_history_info.php?donor_id={DONOR_ID}` (fetch)
    - `../../assets/php_func/get_medical_history.php?donor_id={DONOR_ID}` (verify)
    - `../../src/views/forms/medical-history-process-admin.php` (process, POST)

- Interviewer — Initial Screening
  - Primary modal include:
    - `../../src/views/forms/admin_donor_initial_screening_form_modal.php`
  - Page fallback:
    - `../../src/views/forms/screening-form.php?donor_id={DONOR_ID}`

- Physician — Medical Review (Approve/Decline MH)
  - Primary modal include:
    - `../../src/views/modals/medical-history-approval-modals.php`
  - Page fallback (review):
    - `{baseUrl}medical-history.php?donor_id={DONOR_ID}`

- Physician — Physical Examination
  - Primary modal include/entry:
    - `../../src/views/modals/physical-examination-modal-admin.php` (accessed via `window.physicalExaminationModalAdmin.openModal(...)`)
  - Page fallbacks:
    - Preferred admin form: `../../src/views/forms/physical-examination-form-admin.php?donor_id={DONOR_ID}`
    - Alternate form: `../../src/views/forms/physical-examination-form.php?donor_id={DONOR_ID}`

- Phlebotomist — Blood Collection
  - Primary modal include:
    - `../../src/views/modals/blood-collection-modal-admin.php`
    - Optional details modal loader: `../../assets/js/phlebotomist_blood_collection_details_modal.js` (only when `#phlebotomistBloodCollectionDetailsModal` exists)
  - Page fallbacks (order of preference):
    - `../../src/views/forms/blood-collection-form.php?donor_id={DONOR_ID}`
    - `dashboard-staff-blood-collection-submission.php?donor_id={DONOR_ID}`

Query parameters and encoding
- All fallbacks use `donor_id` as the key parameter; values must be URL‑encoded.
- Where a `source` parameter is used elsewhere (e.g., donor registration), preserve it if present when bouncing between routes.

### Function-to-Modal/Route Mapping (At-a-Glance)

| open* function | Primary modal(s) | Fallback URL(s) | Preconditions/Gates |
|---|---|---|---|
| `openInterviewerScreening(donor, opts?)` | `../../src/views/forms/medical-history-modal-content-admin.php?donor_id={DONOR_ID}` then `../../src/views/forms/admin_donor_initial_screening_form_modal.php` | `{baseUrl}medical-history.php?donor_id={DONOR_ID}`, `../../src/views/forms/screening-form.php?donor_id={DONOR_ID}` | MH must be completed before Screening; if both done → view-only |
| `openPhysicianMedicalReview(donor, opts?)` | `../../src/views/modals/medical-history-approval-modals.php` | `{baseUrl}medical-history.php?donor_id={DONOR_ID}` | Interviewer (MH + Screening) must be completed; Approve enables PE; Decline stops |
| `openPhysicianPhysicalExam(ctx, opts?)` | `../../src/views/modals/physical-examination-modal-admin.php` (via `window.physicalExaminationModalAdmin.openModal(...)`) | `../../src/views/forms/physical-examination-form-admin.php?donor_id={DONOR_ID}`, `../../src/views/forms/physical-examination-form.php?donor_id={DONOR_ID}` | Physician MH must be approved; on completion, Phlebotomist step becomes available |
| `openPhlebotomistCollection(ctx)` | `../../src/views/modals/blood-collection-modal-admin.php` (details: `../../assets/js/phlebotomist_blood_collection_details_modal.js` when `#phlebotomistBloodCollectionDetailsModal` exists) | `../../src/views/forms/blood-collection-form.php?donor_id={DONOR_ID}`, `dashboard-staff-blood-collection-submission.php?donor_id={DONOR_ID}` | Physical Examination must be completed; otherwise gated |

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



---

## 🧩 Addendum — Localhost (XAMPP + Supabase) Performance & Resilience Enhancements

### 🎯 Purpose
This addendum supplements the *Admin Dashboard Inventory System Optimization Specification* with configurations and safeguards tailored for a **local XAMPP + Supabase** development environment. It ensures modular stability, consistent UI behavior, and measurable performance improvements during offline or LAN-only testing.

### 1. Local Module Guards
When using XAMPP without bundling tools, scripts may load out of order. Add the following guard to prevent undefined modules on slow or partial refresh:

```html
<script>
window.ADMIN_MODULES_READY = false;
Promise.allSettled([
  import('/assets/js/bootstrap-utils-admin.js'),
  import('/assets/js/net-admin.js'),
  import('/assets/js/donor-details-admin.js'),
  import('/assets/js/workflow-admin.js'),
  import('/assets/js/events-admin.js')
]).then(() => window.ADMIN_MODULES_READY = true);
</script>
```

Before any function call:
```js
if (!window.ADMIN_MODULES_READY) {
  console.warn("Modules not fully loaded. Please refresh the page.");
  return;
}
```

### 2. Supabase API Timeout + Retry (Local Context)
Since local network latency may fluctuate, add a compact fetch wrapper to avoid hanging requests during testing:

```js
export async function apiGet(url, opts = {}) {
  const { timeoutMs = 5000, retries = 2 } = opts;
  for (let attempt = 0; attempt <= retries; attempt++) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);
    try {
      const res = await fetch(url, { signal: controller.signal });
      clearTimeout(timeout);
      if (res.ok) return res.json();
    } catch (err) {
      console.warn(`Retrying API call (attempt ${attempt + 1})`, err);
    }
  }
  throw new Error("Local Supabase request failed after retries.");
}
```

### 3. Modal Lifecycle Watchdog
Bootstrap modals can leave scroll lock or ghost backdrops when reloaded locally. Add this in `bootstrap-utils-admin.js`:

```js
export function watchModalLifecycle(modalId) {
  const modal = document.getElementById(modalId);
  if (!modal) return;
  modal.addEventListener('hidden.bs.modal', () => {
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  });
}
```

Call once per modal (e.g., `watchModalLifecycle('donorDetailsModal')`).

### 4. Local Cache Layer
Use lightweight caching to minimize redundant Supabase calls:

```js
const donorCache = new Map();
export async function getDonorDetailsCached(id) {
  const key = String(id);
  if (donorCache.has(key)) return donorCache.get(key);
  const data = await apiGet(`/api/donor_details.php?donor_id=${id}`);
  donorCache.set(key, data);
  setTimeout(() => donorCache.delete(key), 60000); // 1 min TTL
  return data;
}
```

### 5. Listener Leak Debug Tool
XAMPP reloads don’t always clear JS memory cleanly. Use this to detect event handler leaks in DevTools:

```js
if (window.__DEBUG) {
  let clickCountPrev = 0;
  setInterval(() => {
    const clickCount = getEventListeners(document.body)?.click?.length || 0;
    if (clickCount > clickCountPrev + 10) console.warn('Potential duplicate listeners:', clickCount);
    clickCountPrev = clickCount;
  }, 5000);
}
```

### 6. Evaluation Checklist (for Thesis Defense)
For your **System Evaluation Phase**, demonstrate that your optimizations are measurable locally:

| Evaluation Focus | Test Procedure | Expected Outcome |
|------------------|----------------|------------------|
| **Modal Stability** | Rapidly open/close 5 donor modals | No stuck backdrops or scroll freeze |
| **API Response Time** | Measure with Chrome DevTools Network tab | < 2.5s average on localhost |
| **Listener Management** | Reopen modals after page reload | No duplicate console warnings |
| **Cache Efficiency** | Reopen the same donor details | Instant (<0.2s) load after first fetch |
| **Error Resilience** | Disconnect Supabase temporarily | Error handled gracefully in-modal |

### 7. Debug Logging (Local Only)
Replace network telemetry with console-based performance logs:

```js
if (window.__DEBUG) {
  const start = performance.now();
  openDonorDetails(123);
  setTimeout(() => {
    console.log('Modal render time:', performance.now() - start, 'ms');
  }, 1000);
}
```

### 8. Summary
These enhancements are tuned for **thesis evaluation under XAMPP + Supabase** conditions.  
They strengthen **UI stability**, **API responsiveness**, and **error transparency**, ensuring consistent demo behavior without production infrastructure.

---
