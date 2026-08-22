# AMMS Detail Workflow UI

## Scope

Phase 6 standardizes detail screens without changing FLOW semantics, authorization, API contracts, database behavior, or reporting rules.

## Shared primitives

- `DetailSummaryCard`: structured read-only request/meeting metadata.
- `WorkflowTimeline`: append-only workflow/audit presentation.
- `Flow02DetailView`: FLOW-02 detail composition using shadcn Card, Badge, Button, Textarea, Alert and ConfirmDialog.

## Runtime migration strategy

`frontend/src/flow02_legacy.tsx` preserves the complete pre-refactor FLOW-02 implementation byte-for-byte. `frontend/src/flow02.tsx` acts as a small facade:

- `CreateInterDirectorateRequestV2` and `Flow02SchedulingV2` are re-exported from the legacy implementation unchanged.
- Normal FLOW-02 detail states use the new detail presentation.
- `RETURNED_TO_REQUESTER` and `RETURNED_BY_TARGET` temporarily delegate to the legacy detail implementation so the existing CorrectionPanel behavior remains intact until its dedicated migration.

## Invariants

1. Existing API endpoints and request payloads remain unchanged.
2. Existing role/unit scope checks remain unchanged:
   - requester executive management;
   - requester director;
   - target executive management;
   - target director.
3. No workflow state, approval rule, role, or capability is introduced.
4. Reject/return actions use confirmation dialogs before calling the existing endpoints.
5. Server-side authorization and validation remain authoritative.
6. Legacy implementation remains available for safe rollback and incremental migration.
