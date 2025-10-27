## Donations Dashboard Optimization Plan

This document tracks the incremental optimization of the donations dashboard and related modules. Changes are guarded behind a feature flag (`perf_mode=on`) for safe rollout.

### Goals
- Preserve existing filters and FIFO semantics.
- Reduce TTFB and external API calls.
- Unify and improve caching without breaking invalidation.

### Phases and Status

1. Feature flag and cache path unification
   - Status: Completed
   - Details:
     - Added `perf_mode` GET flag recognized by dashboard and modules.
     - Unified cache path to `public/Dashboards/cache` when `perf_mode=on`.

2. Pending module batching (scoped in() queries)
   - Status: Completed (first pass)
   - Details:
     - Donor list paginated first; related tables fetched with `in()` filters for current donor IDs.
     - Removed per-donor eligibility curl; replaced with batch fetch.
     - Preserves three pending states and FIFO (oldest-first by submitted_at).
     - Removed duplicate lookup rebuilds.

3. Approved/Declined module consistency
   - Status: Completed (initial consistency)
   - Details:
     - Status-filtered eligibility queries with LIMIT/OFFSET; batch donor lookups with `in()`.
     - Perf-mode flag recognized; behavior consistent with dashboard pagination globals.

4. API endpoint alignment and caching
   - Status: Completed
   - Details:
     - Aligned `public/api/load-donations-data.php` cache dir/TTLs, added cursors, and re-used module logic.

5. Keyset pagination behind flag
   - Status: Completed (Pending + Approved/Declined with prev/next cursors)
   - Details:
     - Added keyset cursor (`cursor_ts`, `cursor_id`) for Pending in module and API under `perf_mode`.
     - Implemented keyset for Approved/Declined with `cursor_dir` (next|prev) and surfaced `prevCursor`/`nextCursor` in API.
     - Added prevCursor for Pending as well.

6. Indexes (Supabase/Postgres)
   - Status: Completed (DDL added to supabase_postgis_setup.sql)
   - Details:
     - Added CREATE INDEX statements for donor_form(submitted_at), eligibility(donor_id, created_at, status), screening_form(donor_form_id), medical_history(donor_id), physical_examination(donor_id), blood_collection(physical_exam_id).

7. "All" tab progressive loading
   - Status: Completed
   - Details:
     - API aggregates small slices from each stream under `perf_mode` using cursors for approved/declined/pending and surfaces per-stream cursors.

8. Logging/diagnostics
   - Status: Completed
   - Details:
     - Verbose logs gated via `?debug=1`. Kept performance headers.

### How to test
- Navigate with `?perf_mode=on` to enable optimizations.
- Validate row counts and order on each tab against baseline.
- Observe response headers: `X-Execution-Time`, `X-Cache-*`.

### Rollout and monitoring (Phase 9)
- perf_mode is ON by default (disable with `?perf_mode=off`).
- API returns `X-Perf-Mode` header for clarity.
- Continue monitoring logs and performance headers during soak.

### Rollback
- Disable with `?perf_mode=off` or revert default setting in code if needed.


