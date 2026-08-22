# Phase 9 — FLOW-07 to FLOW-09 Governance Workspace UX

## Scope

This slice extends the approved AMMS UI system to selected governance/workspace surfaces in FLOW-07, FLOW-08 and FLOW-09.

## Invariants

- No workflow, lifecycle state, API endpoint, payload, role, permission, database behavior, biometric behavior, attendance behavior or reporting semantic is changed.
- Backend authorization remains authoritative.
- Existing endpoint paths and role/unit scope calculations are preserved.
- Existing legacy implementations remain available as rollback/fallback boundaries.

## FLOW-07

The no-active-case reschedule workspace uses the shared PageHeader, DetailSummaryCard, WorkflowTimeline and shadcn form controls.

When an active reschedule case exists, the preserved legacy workspace is used so proposal, acknowledgement, alternative-request resolution and confirmation semantics remain unchanged.

## FLOW-08

The no-active-case participant-change workspace uses the shared PageHeader, DetailSummaryCard, participant presentation, WorkflowTimeline and shadcn form controls.

When an active participant-change case exists, the preserved legacy workspace is used so item mutation, baseline refresh, submit, return, reject and confirmation semantics remain unchanged.

## FLOW-09

TerminalDetail is migrated to the shared detail UI. Credential rotation keeps the same endpoint. Terminal revoke keeps the same endpoint/payload and replaces window.prompt with an explicit typed reason plus ConfirmDialog.

TerminalRegistry and MeetingTerminalWorkspace remain re-exported from the preserved legacy implementation in this slice.

FLOW-09 remains vendor-independent terminal/session foundation only. This UI change makes no physical DERMALOG, biometric or attendance claim.

## Validation

Merge requires the normal AMMS CI gates:

- backend Laravel Feature suite
- frontend frozen install, TypeScript/lint and production build
- terminal Android contract and debug build

Per the current working rule, a fully green CI is approval to merge.