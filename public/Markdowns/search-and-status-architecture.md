# 🔍 Search & Status System Architecture Guide

This document outlines how to professionally implement **Search Functionality** and **Status Badges** for a Supabase-based system using dynamic columns and interconnected status logic.

---

## 🧠 Overview

The goal is to ensure:
- **Scalable search** — works even if table columns or naming conventions change.
- **Dynamic status handling** — supports multiple badge stages under a single logical state.
- **Separation of logic** — backend handles data consistency; frontend handles display.

---

## ⚙️ 1. Search Functionality Design

### Purpose
To allow flexible querying across renamed or evolving columns (e.g., `registration_channel` → `registered_via`) without breaking the frontend.

### Implementation Strategy
- Build a single **search abstraction layer** (in JS or server-side).
- Map new and old columns internally.
- Use Supabase’s `ilike` or `textSearch` for pattern-based queries.

### Example (Supabase + JavaScript)
```js
// search.js
export const SEARCH_FIELDS = {
  donor: ['name', 'email', 'contact_number'],
  registration: ['registered_via', 'date_registered']
}

export async function searchDonors({ searchTerm, filterStatus, filterRegisteredVia }) {
  let query = supabase.from('donors').select('*')

  if (searchTerm) {
    query = query.or(`name.ilike.%${searchTerm}%,email.ilike.%${searchTerm}%`)
  }

  if (filterStatus) {
    query = query.eq('status', filterStatus)
  }

  if (filterRegisteredVia) {
    query = query.eq('registered_via', filterRegisteredVia)
  }

  const { data, error } = await query
  if (error) throw error
  return data
}
```

### Backend/Config Translation (Optional)
If your schema evolves, add a mapping layer:

```js
export const FIELD_MAP = {
  registration_channel: 'registered_via'
}
```

This ensures legacy data or reports remain consistent even if column names change.

---

## 🧩 2. Status Badge Architecture

### Purpose
To represent multiple process stages (e.g., Screening → Examination → Collection) under unified status categories (Pending, Approved, Declined).

### Design Approach
Use a **data-driven state map** instead of hard-coded `if` statements. This enables easy expansion and visual consistency across files.

### Example Configuration
```js
// statusMap.js
export const STATUS_MAP = {
  pending: {
    label: 'Pending',
    stages: [
      { key: 'screening', label: 'Pending (Screening)', color: 'yellow' },
      { key: 'examination', label: 'Pending (Examination)', color: 'orange' },
      { key: 'collection', label: 'Pending (Collection)', color: 'amber' }
    ]
  },
  approved: { label: 'Approved', color: 'green' },
  declined: {
    label: 'Declined',
    stages: [
      { key: 'screening', label: 'Declined (Screening)', color: 'red' },
      { key: 'examination', label: 'Declined (Examination)', color: 'maroon' },
      { key: 'collection', label: 'Declined (Collection)', color: 'brown' }
    ]
  }
}
```

### Example Badge Function (Frontend)
```jsx
// utils/getBadge.js
import { STATUS_MAP } from './statusMap.js'

export function getBadge(status, stage) {
  const base = STATUS_MAP[status]
  if (base?.stages) {
    return base.stages.find(s => s.key === stage)?.label || base.label
  }
  return base?.label || 'Unknown'
}
```

### Example Usage (React/Vue)
```jsx
<span className={`badge badge-${STATUS_MAP[status]?.color}`}>
  {getBadge(status, stage)}
</span>
```

---

## 🔗 3. Connecting Search and Status Layers

### Data Flow
1. **Backend/Supabase:** Fetch data containing `status`, `stage`, and `registered_via`.
2. **Frontend:** Uses `STATUS_MAP` to determine badge visuals.
3. **Search Layer:** Handles filters for status and registration source seamlessly.

This keeps the frontend **logic-free** — it only displays what the backend provides.

---

## 🧭 Future Scalability

| Timeframe | What Might Break | What Might Scale | What Might Surprise |
|------------|------------------|------------------|----------------------|
| 3 Weeks | Hardcoded field names | Dynamic filters | How easily you can add filters |
| 3 Months | Inconsistent status logic | Shared `STATUS_MAP` config | Filtering by stage without new code |
| 3 Years | Manual transitions | Workflow automation | Integrating analytics from same map |

---

## 🪞 The Version You Didn’t Ask For
In industry, teams sometimes externalize this logic into a **workflow engine** (like Temporal, n8n, or Supabase Edge Functions). Each stage transition is automated and stored, allowing full audit trails and custom approval flows. This is enterprise-level scaling beyond a thesis, but your architecture here makes that transition effortless.

---

## 💡 Hidden Layer: What Most People Miss
Most systems treat **search and status** as *frontend problems*.  
Professionals treat them as **data model problems**.

By defining column mappings and status states at the data/config level, your system becomes resilient to renames, process changes, and growth — turning your thesis into a maintainable foundation for real-world scalability.

---
