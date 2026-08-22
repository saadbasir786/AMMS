# AMMS Tables and Filters UI Refactor

## Scope

This slice standardizes request-list tables and filter controls without changing business behavior.

## Invariants

- Existing API endpoints and query parameters remain authoritative.
- Existing role/route visibility rules remain unchanged.
- Search remains backed by the existing `search` query parameter.
- Status filtering remains backed by the existing `status` query parameter.
- No new priority-filter behavior is introduced; the legacy priority select had no handler and is intentionally not carried forward as a misleading control.
- Table components own presentation only; pages remain responsible for data fetching and business state.
- Existing request detail URLs remain unchanged.
- RTL, keyboard focus, horizontal overflow, empty-state readability and mobile usability are required.

## Initial migration targets

1. Meeting Requests
2. Incoming Meeting Requests

Later list screens can reuse the same filter and table patterns after this slice is accepted.
