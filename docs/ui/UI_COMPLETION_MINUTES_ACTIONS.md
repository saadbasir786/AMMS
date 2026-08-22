# UI Completion — Minutes & Action Items

This slice completes the primary legacy presentation for FLOW-11 minutes and FLOW-12 action-item list/detail surfaces.

## Scope
- `MeetingMinutesWorkspace` moved to shared AMMS page chrome, cards, form controls, timeline and confirmation dialog.
- `ActionItemsWorkspace` moved to shared filters/table presentation.
- `ActionItemDetailWorkspace` moved to shared summary, action cards, timeline, evidence cards and destructive confirmation.
- Existing modern FLOW-11 execution and FLOW-12 follow-up implementations are preserved byte-for-byte in `flow11_execution.tsx` and `flow12_followup.tsx`.

## Invariants
- no new FLOW, role, lifecycle state, approval path, endpoint, payload field, metric or notification behavior;
- backend validation and authorization remain authoritative;
- minutes draft/update/submit/review/authority/finalize endpoints are unchanged;
- action-item start/progress/block/unblock/completion/cancel/extension endpoints and payloads are unchanged;
- existing required correction, completion, block, return, cancel and extension reasons are preserved;
- official PDF endpoint is unchanged;
- evidence remains read-only in this slice.

## Validation
Full AMMS CI remains the merge gate: Laravel Feature suite, frontend frozen install/lint/build, and Android core contract/debug build.
