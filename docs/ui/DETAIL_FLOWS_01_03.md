# Phase 7 — FLOW-01 and FLOW-03 Detail UX

## Scope

This slice extends the approved AMMS detail-screen pattern to FLOW-01 and FLOW-03 only.

## Invariants

- No business workflow, lifecycle state, API contract, role, permission, database behavior, or reporting metric is changed.
- Backend authorization remains authoritative.
- FLOW-01 preserves the existing role checks for `DIRECTOR` approval actions and `EXECUTIVE_MANAGEMENT` scheduling actions.
- FLOW-03 preserves per-directorate scope checks: executive-management scope starts target review; director scope decides for that same target directorate.
- Return/reject actions use the existing endpoints and payloads, with an explicit confirmation dialog added before invocation.
- FLOW-03 correction states `RETURNED_TO_REQUESTER` and `TARGET_ACTION_REQUIRED` continue to use the previous detail implementation so correction/attachment behavior is unchanged.
- FLOW-03 create and scheduling screens are re-exported from the preserved legacy implementation unchanged.

## Shared presentation

Both migrated detail screens use the established shared primitives:

- `PageHeader`
- `DetailSummaryCard`
- `WorkflowTimeline`
- shadcn `Card`, `Badge`, `Button`, `Textarea`, `Alert`
- `ConfirmDialog` for state-changing return/reject actions

## Runtime strategy

FLOW-01 remains routed through `RequestDetail` in `main.tsx`, but the FLOW-01-specific presentation is delegated to `Flow01DetailView`.

FLOW-03 uses a small facade in `flow03.tsx`. The previous implementation is retained in `flow03_legacy.tsx`; create/scheduling are re-exported unchanged, while normal detail states use `Flow03DetailView`.

## Validation

Merge requires the normal AMMS CI gates:

- backend Laravel Feature suite
- frontend frozen install, TypeScript check and production build
- terminal Android contract and debug build
