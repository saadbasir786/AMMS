# FLOW-12 / FLOW-13 UI Migration

## Scope

This slice modernizes selected FLOW-12 and FLOW-13 frontend workspaces while preserving frozen AMMS business semantics.

## FLOW-12

- `MeetingFollowUpWorkspace` migrated to shared `PageHeader`, `DetailSummaryCard`, shadcn `Card`, `Badge`, `Alert`, `Dialog`, `Input`, `Textarea`, and `Button` primitives.
- Existing endpoints and payloads are preserved:
  - `GET /meetings/{id}/follow-up`
  - `POST /meetings/{id}/follow-up/initialize`
  - `POST /decisions/{id}/follow-up/classify`
  - `POST /decisions/{id}/action-items`
- Existing NO_ACTION_REQUIRED reason requirement is preserved.
- Action-item creation still uses the same responsible-unit / employee IDs and due date payload.
- `ActionItemsWorkspace` and `ActionItemDetailWorkspace` remain on the preserved legacy implementation in this slice.

## FLOW-13

- `MeetingExceptionWorkspace` migrated to shared page chrome and shadcn detail/dialog/alert primitives.
- Existing exception case types remain exactly:
  - `POSTPONEMENT`
  - `CANCELLATION`
  - `REOPENING`
  - `RESUMPTION`
- Existing endpoint sequence is preserved for create, submit, review acknowledge/approve, withdraw, apply, and resume.
- Existing required reason and resumption schedule fields remain unchanged.
- Applying a READY_TO_APPLY case continues to use the existing confirmation boundary.

## Invariants

- No new FLOW, role, permission, approval path, lifecycle state, API route, database behavior, metric, notification behavior, or business validation was added.
- Backend authorization and validation remain authoritative.
- Official minutes and meeting history are not rewritten by FLOW-12.
- FLOW-13 remains append-only/history-preserving according to the existing backend contract.
- Legacy source snapshots are retained as `flow12_legacy.tsx` and `flow13_legacy.tsx` for reviewability and rollback.

## Validation

Full AMMS CI remains the merge gate:

1. Laravel Feature tests on MariaDB.
2. Frontend frozen pnpm install, lint, and build.
3. Android core contract and debug build.
