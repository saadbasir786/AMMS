# Phase 8 — FLOW-04 to FLOW-06 Detail UX

## Scope

This slice extends the established AMMS detail/workflow presentation pattern to FLOW-04, FLOW-05, and FLOW-06.

## Invariants

- No business workflow, lifecycle state, role, permission, API contract, database behavior, or reporting semantic is changed.
- Backend authorization remains authoritative.
- Existing role/scope checks are preserved before rendering state-changing actions.
- Return/reject/clarification actions continue to call the existing endpoints and payloads; confirmation UI is presentation-only.
- Existing create, scheduling, and inbox implementations remain unchanged and are re-exported from preserved legacy modules.

## Risk boundaries

Complex correction states deliberately retain the previous detail implementation:

- FLOW-04: `RETURNED_TO_REQUESTER`, `RETURNED_BY_AUTHORITY_OFFICE`, `RETURNED_BY_HIGH_AUTHORITY`.
- FLOW-05: `RETURNED_TO_REQUESTER`, `DIRECTORATE_ACTION_REQUIRED`, `RETURNED_BY_AUTHORITY_OFFICE`, `RETURNED_BY_HIGH_AUTHORITY`.
- FLOW-06: `DRAFT`, `RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION`, `DIRECTORATE_CLARIFICATION_REQUIRED`.

These states contain editing, attachment, clarification-response, or material-revision behavior that is intentionally not redesigned in this slice.

## Shared presentation

Normal detail states use:

- `PageHeader`
- `DetailSummaryCard`
- `WorkflowTimeline`
- shadcn `Card`, `Badge`, `Button`, `Textarea`, `Alert`
- `ConfirmDialog` for return/reject/clarification operations

## FLOW-specific authorization preserved

### FLOW-04

- requester executive-management / creator scope for requester actions;
- requester `DIRECTOR` for internal approval;
- `AUTHORITY_OFFICE` for office review;
- active authority employee snapshot for authority decision;
- `EXECUTIVE_SECRETARY` for authority scheduling.

### FLOW-05

- requester executive-management / creator scope;
- requester `DIRECTOR` approval;
- each participating Directorate independently uses its own `EXECUTIVE_MANAGEMENT` / `DIRECTOR` scope;
- authority-office, authority-holder, and executive-secretary boundaries remain unchanged.

### FLOW-06

- no requester Directorate is introduced;
- authority holder issues/reissues;
- authority office distributes;
- participating Directorate executive-management coordinates and its Director acknowledges;
- executive secretary starts scheduling.

## Validation

Merge requires the normal AMMS CI gates:

- backend Laravel Feature suite;
- frontend frozen install, TypeScript check and production build;
- terminal Android contract and debug build.
