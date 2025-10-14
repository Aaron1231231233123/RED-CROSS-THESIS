## Unified Search Implementation (Admin Dashboards)

This document describes the hybrid, unified search system for the admin dashboards. The goal is to standardize search behavior, enable database-backed queries where needed, and keep performance high on large datasets.

### Objectives
- Consistent UX and behavior across dashboards (Donor Management, Blood Bank, Hospital Requests, Manage Users)
- Hybrid search: fast client-side filtering on already-loaded rows, and optional backend search for full database coverage
- Centralized, maintainable code via a reusable JavaScript module
- Progressive enhancement with graceful fallback

### Scope (Incremental Rollout)
1) Donor Management (start) → 2) Blood Bank → 3) Hospital Requests → 4) Manage Users

### Architecture Overview
- Unified frontend module: `assets/js/unified-search_admin.js`
  - Debounced search input handling
  - Category-based filtering
  - Optional result highlighting
  - Frontend mode (filter current DOM) and backend mode (query API), or hybrid (both)
- Backend API (optional per page): `public/api/unified-search_admin.php`
  - Endpoint accepts: `action` (dataset), `q` (query), `category`, `page`, `limit`
  - Returns JSON with `results` and `pagination`
  - Implement dataset-specific handlers (e.g., donors, blood inventory, hospital requests, users)

### Frontend Module Configuration
Initialize the unified search per dashboard. Each page keeps its own search bar and categories, but uses the same engine.

```html
<!-- Example include (place before closing body) -->
<script src="../../assets/js/unified-search_admin.js"></script>
<script>
  // Example for a table-driven page
  const search = new UnifiedSearch({
    inputId: 'searchInput',              // text input element id
    categoryId: 'searchCategory',        // select element id (optional)
    tableId: 'donationsTable',           // table element id (if table-based)
    rowSelector: 'tbody tr',             // selector for rows (default)
    mode: 'frontend',                    // 'frontend' | 'backend' | 'hybrid'
    debounceMs: 250,                     // input debounce
    highlight: false,                    // enable/disable highlight
    columnsMapping: {                    // map category → column indices
      all: 'all',                        // search all cells
      donor: [1, 2],                     // example: surname, first name
      donor_number: [0],
      donor_type: [3],
      registered_via: [4],
      status: [5]
    },
    backend: {                           // used in backend/hybrid mode
      url: '../api/unified-search_admin.php',
      action: 'donors',
      pageSize: 50,
      // renderResults: (data) => {...} // optional custom renderer
    }
  });
  // If you include with {autobind: false}, call search.bind() manually
  // to attach event listeners.
  // const search = new UnifiedSearch({... , autobind: false});
  // search.bind();
</script>
```

### Backend API Contract (Unified)
Request (GET):
```
unified-search_admin.php?action=<dataset>&q=<query>&category=<category>&page=<n>&limit=<n>
```

Response (JSON):
```json
{
  "success": true,
  "results": [ /* dataset-specific objects or pre-rendered rows */ ],
  "pagination": { "page": 1, "limit": 50, "total": 1234 }
}
```

Datasets (example):
- `action=donors`
- `action=blood_inventory`
- `action=hospital_requests`
- `action=users`

### Rollout Plan (Incremental)
1. Donor Management
   - Add module include, initialize in frontend mode (non-breaking)
   - Verify parity with existing behavior, optionally enable highlight
   - Add backend search endpoint and move to hybrid mode if needed (large data)
2. Blood Bank
   - Replace in-page search with unified module + configs
   - Fix any category/column mapping discrepancies
3. Hospital Requests
   - Standardize search behavior and add optional backend support
4. Manage Users
   - Implement missing search with unified module

### Performance Considerations
- Debounce user input (default 250–300ms)
- Early exits for empty queries
- Use querySelectorAll once and reuse references where possible
- Prefer indexed columns and LIMIT/OFFSET (or keyset pagination) on backend
- Consider full‑text or trigram indexes for fuzzy search when appropriate

### Testing & QA Checklist
- Functional: all categories filter correctly; empty query resets rows
- UX: input responsiveness, consistent results count, optional highlight
- Backend: correct results, pagination, SQL correctness, safe binding
- Reliability: graceful fallback to frontend if API is unavailable
- Performance: large lists remain responsive; backend queries use indexes

### Migration Notes
- Keep existing page-specific search until parity is validated
- Introduce the unified module in parallel, then remove duplicate logic
- Backend endpoints can be added per dataset without breaking existing pages

### Maintenance
- Centralized improvements in `assets/js/unified-search_admin.js` benefit all dashboards
- Page-level configuration files/blocks document category-to-column mappings


