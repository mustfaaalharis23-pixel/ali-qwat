---
paths:
  - 'app/**'
---

# App

## Inactive and soft-deleted items are hidden system-wide
An inactive item (is_active = false) or a soft-deleted item does not appear in lenses, lens counts, or the items page by default. Every item query that lists or counts items for work uses the Item active scope; soft deletes are already excluded by default. Inactive items are reached only on purpose (e.g. the items page inactive filter, the inactive item count card). Explanations and info texts must say "active items" and never "active or not".
